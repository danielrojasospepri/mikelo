<?php
namespace App\Model;

/**
 * Modelo para gestión de Pedidos
 * Un pedido es una solicitud formal de productos que una sucursal hace al depósito central
 */
class Pedido {
    private $db;

    // Estados posibles del pedido
    const ESTADO_BORRADOR = 'BORRADOR';
    const ESTADO_PENDIENTE = 'PENDIENTE';
    const ESTADO_EN_PREPARACION = 'EN_PREPARACION';
    const ESTADO_PARCIALMENTE_ENVIADO = 'PARCIALMENTE_ENVIADO';
    const ESTADO_ENVIADO = 'ENVIADO';
    const ESTADO_RECIBIDO_PARCIAL = 'RECIBIDO_PARCIAL';
    const ESTADO_RECIBIDO = 'RECIBIDO';
    const ESTADO_ANULADO = 'ANULADO';

    public function __construct($db) {
        $this->db = $db;
    }

    /**
     * Crear un nuevo pedido
     * @param int $idSucursal - Sucursal que hace el pedido
     * @param int $idUsuario - Usuario que crea el pedido
     * @param array $items - Array de ['id_producto' => X, 'cantidad' => Y, 'peso' => Z]
     * @param string $observaciones - Observaciones opcionales
     * @param string $prioridad - Prioridad del pedido (normal, urgente, baja)
     * @return int - ID del pedido creado
     */
    public function crear($idSucursal, $idUsuario, $items, $observaciones = null, $prioridad = 'normal') {
        try {
            $this->db->beginTransaction();

            // 1. Validar que la sucursal no sea el depósito central
            if ($idSucursal == 1) {
                throw new \Exception("El depósito central no puede crear pedidos");
            }

            // 2. Validar que hay items
            if (empty($items)) {
                throw new \Exception("El pedido debe tener al menos un producto");
            }

            // 3. Validar productos para franquicias
            $this->validarProductosFranquicia($idSucursal, $items);

            // 4. Crear el pedido (columnas reales: creado_por, fecha_pedido, estado, prioridad)
            $stmt = $this->db->prepare("
                INSERT INTO pedidos (id_sucursal, creado_por, estado, observaciones, prioridad, fecha_pedido)
                VALUES (?, ?, 'PENDIENTE', ?, ?, NOW())
            ");
            $stmt->execute([$idSucursal, $idUsuario, $observaciones, $prioridad]);
            $idPedido = $this->db->lastInsertId();

            // 5. Agregar items al pedido
            foreach ($items as $item) {
                $stmt = $this->db->prepare("
                    INSERT INTO pedido_items (id_pedido, id_producto, cantidad, observaciones)
                    VALUES (?, ?, ?, ?)
                ");
                $stmt->execute([
                    $idPedido,
                    $item['id_producto'],
                    $item['cantidad'],
                    $item['observaciones'] ?? ''
                ]);
            }

            $this->db->commit();
            return $idPedido;

        } catch (\Exception $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    /**
     * Validar que una franquicia solo pida productos permitidos
     */
    private function validarProductosFranquicia($idSucursal, $items) {
        // Verificar si la sucursal es franquicia
        $stmt = $this->db->prepare("SELECT tipo_ubicacion FROM ubicaciones WHERE id = ?");
        $stmt->execute([$idSucursal]);
        $ubicacion = $stmt->fetch(\PDO::FETCH_ASSOC);

        if ($ubicacion && $ubicacion['tipo_ubicacion'] === 'FRANQUICIA') {
            // Verificar cada producto
            foreach ($items as $item) {
                $stmt = $this->db->prepare("
                    SELECT disponible_franquicias FROM productos WHERE id = ?
                ");
                $stmt->execute([$item['id_producto']]);
                $producto = $stmt->fetch(\PDO::FETCH_ASSOC);

                if ($producto && !$producto['disponible_franquicias']) {
                    throw new \Exception(
                        "El producto ID {$item['id_producto']} no está disponible para franquicias"
                    );
                }
            }
        }
    }

    /**
     * Procesar pedido (cambiar de PENDIENTE a EN_PROCESO)
     */
    public function procesar($idPedido, $idUsuario) {
        $stmt = $this->db->prepare("
            UPDATE pedidos 
            SET estado = 'EN_PROCESO', procesado_por = ?, fecha_procesado = NOW()
            WHERE id = ? AND estado = 'PENDIENTE'
        ");
        $stmt->execute([$idUsuario, $idPedido]);
        
        if ($stmt->rowCount() === 0) {
            throw new \Exception("El pedido no existe o no está en estado PENDIENTE");
        }
        
        return true;
    }

    /**
     * Anular pedido
     */
    public function anular($idPedido, $idUsuario, $motivo) {
        $stmt = $this->db->prepare("
            UPDATE pedidos 
            SET estado = 'ANULADO', motivo_anulacion = ?, anulado_por = ?, fecha_anulacion = NOW()
            WHERE id = ? AND estado IN ('PENDIENTE', 'EN_PROCESO')
        ");
        $stmt->execute([$motivo, $idUsuario, $idPedido]);
        
        if ($stmt->rowCount() === 0) {
            throw new \Exception("El pedido no existe o no puede ser anulado en su estado actual");
        }
        
        return true;
    }

    /**
     * Marcar pedido como recibido manualmente (por la sucursal)
     */
    public function marcarRecibido($idPedido, $idUsuario) {
        $stmt = $this->db->prepare("
            UPDATE pedidos
            SET estado = 'RECIBIDO'
            WHERE id = ? AND estado NOT IN ('ANULADO', 'RECIBIDO')
        ");
        $stmt->execute([$idPedido]);

        if ($stmt->rowCount() === 0) {
            throw new \Exception("El pedido no existe, ya está recibido o está anulado");
        }
        return true;
    }

    /**
     * Obtener pedido por ID con sus items
     */
    public function obtenerPorId($idPedido) {
        // Obtener pedido
        $stmt = $this->db->prepare("
            SELECT p.*, 
                   u.nombre as sucursal,
                   us.nombre as usuario_nombre
            FROM pedidos p
            INNER JOIN ubicaciones u ON p.id_sucursal = u.id
            INNER JOIN usuarios us ON p.creado_por = us.id
            WHERE p.id = ?
        ");
        $stmt->execute([$idPedido]);
        $pedido = $stmt->fetch(\PDO::FETCH_ASSOC);

        if (!$pedido) {
            return null;
        }

        // Obtener items con stock disponible
        $stmt = $this->db->prepare("
            SELECT pi.*, 
                   pr.codigo,
                   pr.descripcion as nombre,
                   pr.codigo as codigo_producto,
                   pr.descripcion as producto,
                   COALESCE((
                       SELECT SUM(
                           mi.cnt - IFNULL((
                               SELECT SUM(mi2.cnt)
                               FROM movimientos_items mi2
                               WHERE mi2.id_movimientos_items_origen = mi.id
                           ), 0)
                       )
                       FROM movimientos_items mi
                       WHERE mi.id_productos = pr.id 
                         AND mi.id_movimientos_items_origen IS NULL
                         AND mi.cnt > IFNULL((
                               SELECT IFNULL(SUM(mi3.cnt), 0)
                               FROM movimientos_items mi3
                               WHERE mi3.id_movimientos_items_origen = mi.id
                           ), 0)
                         AND NOT EXISTS (
                               SELECT 1 FROM estados_items_movimientos eim
                               JOIN estados e ON eim.id_estados = e.id
                               WHERE eim.id_movimientos_items = mi.id
                               AND e.nombre = 'BAJA'
                           )
                   ), 0) as stock_disponible
            FROM pedido_items pi
            INNER JOIN productos pr ON pi.id_producto = pr.id
            WHERE pi.id_pedido = ?
        ");
        $stmt->execute([$idPedido]);
        $pedido['items'] = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        // Obtener envíos asociados
        $stmt = $this->db->prepare("
            SELECT pe.id_envio, 
                   pe.fecha_asociacion,
                   m.fechaAlta as fecha_envio,
                   ud.nombre as destino
            FROM pedido_envio pe
            INNER JOIN movimientos m ON pe.id_envio = m.id
            INNER JOIN ubicaciones ud ON m.id_ubicacion_destino = ud.id
            WHERE pe.id_pedido = ?
        ");
        $stmt->execute([$idPedido]);
        $pedido['envios'] = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        return $pedido;
    }

    /**
     * Listar pedidos de una sucursal
     */
    public function listarPorSucursal($idSucursal, $estado = null, $limite = 50, $offset = 0) {
        // Asegurar que idSucursal sea un entero
        $idSucursal = (int)$idSucursal;
        
        $sql = "
            SELECT p.id, p.estado, p.fecha_pedido, p.observaciones, p.prioridad,
                   COUNT(DISTINCT pi.id) as total_items,
                   SUM(pi.cantidad) as total_cantidad,
                   SUM(pi.cantidad_enviada) as total_enviada
            FROM pedidos p
            LEFT JOIN pedido_items pi ON p.id = pi.id_pedido
            WHERE p.id_sucursal = ?
        ";
        $params = [$idSucursal];

        if ($estado && is_string($estado)) {
            $sql .= " AND p.estado = ?";
            $params[] = $estado;
        }

        $limite = (int)$limite;
        $offset = (int)$offset;
        $sql .= " GROUP BY p.id ORDER BY p.fecha_pedido DESC LIMIT $limite OFFSET $offset";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Listar todos los pedidos (para planta/admin)
     */
    public function listarTodos($filtros = [], $limite = 100, $offset = 0) {
        $sql = "
            SELECT p.id, p.id_sucursal, p.estado, p.fecha_pedido, p.observaciones, p.prioridad,
                   u.nombre as sucursal,
                   COUNT(DISTINCT pi.id) as total_items,
                   SUM(pi.cantidad) as total_cantidad,
                   SUM(pi.cantidad_enviada) as total_enviada
            FROM pedidos p
            INNER JOIN ubicaciones u ON p.id_sucursal = u.id
            LEFT JOIN pedido_items pi ON p.id = pi.id_pedido
            WHERE 1=1
        ";
        $params = [];

        if (!empty($filtros['estado'])) {
            $sql .= " AND p.estado = ?";
            $params[] = $filtros['estado'];
        }

        if (!empty($filtros['id_sucursal'])) {
            $sql .= " AND p.id_sucursal = ?";
            $params[] = $filtros['id_sucursal'];
        }

        if (!empty($filtros['fecha_desde'])) {
            $sql .= " AND DATE(p.fecha_pedido) >= ?";
            $params[] = $filtros['fecha_desde'];
        }

        if (!empty($filtros['fecha_hasta'])) {
            $sql .= " AND DATE(p.fecha_pedido) <= ?";
            $params[] = $filtros['fecha_hasta'];
        }

        $limite = (int)$limite;
        $offset = (int)$offset;
        $sql .= " GROUP BY p.id ORDER BY p.fecha_pedido DESC LIMIT $limite OFFSET $offset";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Listar todos los pedidos pendientes (para planta)
     */
    public function listarPendientes($limite = 100) {
        $limite = (int)$limite;
        $stmt = $this->db->prepare("
            SELECT p.id, p.id_sucursal, p.estado, p.fecha_pedido, p.prioridad,
                   u.nombre as sucursal,
                   COUNT(DISTINCT pi.id) as total_items,
                   SUM(pi.cantidad) as total_cantidad
            FROM pedidos p
            INNER JOIN ubicaciones u ON p.id_sucursal = u.id
            LEFT JOIN pedido_items pi ON p.id = pi.id_pedido
            WHERE p.estado IN ('PENDIENTE', 'EN_PROCESO')
            GROUP BY p.id
            ORDER BY p.prioridad DESC, p.fecha_pedido ASC
            LIMIT $limite
        ");
        $stmt->execute();
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Marcar pedido como enviado al asociarle un envío (usado por Planta)
     */
    public function enviar($idPedido, $idEnvio, $usuario) {
        // Verificar que el pedido exista y esté en estado válido
        $stmt = $this->db->prepare("SELECT id, estado FROM pedidos WHERE id = ?");
        $stmt->execute([$idPedido]);
        $pedido = $stmt->fetch(\PDO::FETCH_ASSOC);

        if (!$pedido) {
            throw new \Exception("Pedido no encontrado");
        }
        if ($pedido['estado'] === 'ANULADO') {
            throw new \Exception("No se puede enviar un pedido anulado");
        }
        if ($pedido['estado'] === 'RECIBIDO') {
            throw new \Exception("El pedido ya fue recibido");
        }

        try {
            $this->db->beginTransaction();

            // Vincular envío al pedido (si se proporcionó id_envio)
            if ($idEnvio !== null) {
                $stmt = $this->db->prepare(
                    "SELECT id FROM pedido_envio WHERE id_pedido = ? AND id_envio = ?"
                );
                $stmt->execute([$idPedido, $idEnvio]);
                if (!$stmt->fetch()) {
                    $stmt = $this->db->prepare(
                        "INSERT INTO pedido_envio (id_pedido, id_envio, asociado_por) VALUES (?, ?, ?)"
                    );
                    $stmt->execute([$idPedido, $idEnvio, $usuario]);
                }
            }

            // Cambiar estado a ENVIADO
            $stmt = $this->db->prepare(
                "UPDATE pedidos SET estado = 'ENVIADO'
                 WHERE id = ? AND estado NOT IN ('ANULADO','RECIBIDO','RECIBIDO_PARCIAL')"
            );
            $stmt->execute([$idPedido]);

            $this->db->commit();
            return true;

        } catch (\Exception $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    /**
     * Asociar un envío existente a un pedido
     * @param int $idPedido
     * @param int $idEnvio - ID del movimiento (envío existente)
     * @param array $itemsAsociados - Array de ['id_pedido_item' => X, 'id_movimiento_item' => Y, 'cantidad' => Z]
     */
    public function asociarEnvio($idPedido, $idEnvio, $itemsAsociados, $usuario) {
        try {
            $this->db->beginTransaction();

            // 1. Crear relación pedido-envío
            $stmt = $this->db->prepare("
                INSERT INTO pedido_envio (id_pedido, id_envio, asociado_por)
                VALUES (?, ?, ?)
            ");
            $stmt->execute([$idPedido, $idEnvio, $usuario]);
            $idPedidoEnvio = $this->db->lastInsertId();

            // 2. Registrar detalle de items asociados
            foreach ($itemsAsociados as $item) {
                $stmt = $this->db->prepare("
                    INSERT INTO pedido_envio_items 
                    (id_pedido_envio, id_pedido_item, id_movimiento_item, cantidad)
                    VALUES (?, ?, ?, ?)
                ");
                $stmt->execute([
                    $idPedidoEnvio,
                    $item['id_pedido_item'],
                    $item['id_movimiento_item'],
                    $item['cantidad']
                ]);

                // 3. Actualizar cantidad_enviada en pedido_items
                $stmt = $this->db->prepare("
                    UPDATE pedido_items 
                    SET cantidad_enviada = COALESCE(cantidad_enviada, 0) + ?
                    WHERE id = ?
                ");
                $stmt->execute([$item['cantidad'], $item['id_pedido_item']]);
            }

            // 4. Actualizar estado del pedido
            $this->actualizarEstadoPedido($idPedido);

            $this->db->commit();
            return $idPedidoEnvio;

        } catch (\Exception $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    /**
     * Actualizar estado del pedido basado en cantidades enviadas
     */
    public function actualizarEstadoPedido($idPedido) {
        // Obtener totales
        $stmt = $this->db->prepare("
            SELECT 
                SUM(cantidad) as total_solicitada,
                SUM(COALESCE(cantidad_enviada, 0)) as total_enviada
            FROM pedido_items
            WHERE id_pedido = ?
        ");
        $stmt->execute([$idPedido]);
        $totales = $stmt->fetch(\PDO::FETCH_ASSOC);

        $solicitada = (float) $totales['total_solicitada'];
        $enviada = (float) $totales['total_enviada'];

        // Determinar nuevo estado (PENDIENTE, EN_PROCESO, ENVIADO)
        // Nota: RECIBIDO lo gestiona Recepcion::actualizarEstadoPedidoRecibido()
        $nuevoEstado = 'PENDIENTE';

        if ($enviada >= $solicitada) {
            $nuevoEstado = 'ENVIADO';
        } elseif ($enviada > 0) {
            $nuevoEstado = 'EN_PROCESO';
        }

        // Actualizar solo si no está ya en RECIBIDO/RECIBIDO_PARCIAL/ANULADO
        $stmt = $this->db->prepare("
            UPDATE pedidos SET estado = ? 
            WHERE id = ? AND estado NOT IN ('RECIBIDO', 'RECIBIDO_PARCIAL', 'ANULADO')
        ");
        $stmt->execute([$nuevoEstado, $idPedido]);
    }

    /**
     * Agregar item a un pedido existente (solo si está en BORRADOR)
     */
    public function agregarItem($idPedido, $idProducto, $cantidad, $peso = 0) {
        // Verificar estado
        $stmt = $this->db->prepare("SELECT estado, id_sucursal FROM pedidos WHERE id = ?");
        $stmt->execute([$idPedido]);
        $pedido = $stmt->fetch(\PDO::FETCH_ASSOC);

        if (!$pedido || $pedido['estado'] !== self::ESTADO_BORRADOR) {
            throw new \Exception("Solo se pueden agregar items a pedidos en estado BORRADOR");
        }

        // Validar producto para franquicia
        $this->validarProductosFranquicia($pedido['id_sucursal'], [
            ['id_producto' => $idProducto]
        ]);

        // Verificar si ya existe el producto
        $stmt = $this->db->prepare("
            SELECT id, cantidad_solicitada FROM pedido_items 
            WHERE id_pedido = ? AND id_producto = ?
        ");
        $stmt->execute([$idPedido, $idProducto]);
        $itemExistente = $stmt->fetch(\PDO::FETCH_ASSOC);

        if ($itemExistente) {
            // Actualizar cantidad
            $stmt = $this->db->prepare("
                UPDATE pedido_items 
                SET cantidad_solicitada = cantidad_solicitada + ?,
                    peso_solicitado = peso_solicitado + ?
                WHERE id = ?
            ");
            $stmt->execute([$cantidad, $peso, $itemExistente['id']]);
        } else {
            // Insertar nuevo
            $stmt = $this->db->prepare("
                INSERT INTO pedido_items (id_pedido, id_producto, cantidad_solicitada, peso_solicitado)
                VALUES (?, ?, ?, ?)
            ");
            $stmt->execute([$idPedido, $idProducto, $cantidad, $peso]);
        }

        return true;
    }

    /**
     * Eliminar item de un pedido (solo si está en BORRADOR)
     */
    public function eliminarItem($idPedido, $idPedidoItem) {
        $stmt = $this->db->prepare("SELECT estado FROM pedidos WHERE id = ?");
        $stmt->execute([$idPedido]);
        $pedido = $stmt->fetch(\PDO::FETCH_ASSOC);

        if (!$pedido || $pedido['estado'] !== self::ESTADO_BORRADOR) {
            throw new \Exception("Solo se pueden eliminar items de pedidos en estado BORRADOR");
        }

        $stmt = $this->db->prepare("DELETE FROM pedido_items WHERE id = ? AND id_pedido = ?");
        $stmt->execute([$idPedidoItem, $idPedido]);

        return $stmt->rowCount() > 0;
    }
}
