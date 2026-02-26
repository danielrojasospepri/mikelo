<?php
namespace App\Model;

/**
 * Modelo para gestión de Recepciones
 * Una recepción confirma la llegada de un envío a una sucursal
 */
class Recepcion {
    private $db;

    // Estados posibles de la recepción
    const ESTADO_COMPLETA = 'COMPLETA';
    const ESTADO_PARCIAL = 'PARCIAL';
    const ESTADO_CON_DIFERENCIAS = 'CON_DIFERENCIAS';
    const ESTADO_RECHAZADA = 'RECHAZADA';

    // Estados de items
    const ITEM_OK = 'OK';
    const ITEM_FALTANTE = 'FALTANTE';
    const ITEM_EXCEDENTE = 'EXCEDENTE';
    const ITEM_DANADO = 'DAÑADO';

    public function __construct($db) {
        $this->db = $db;
    }

    /**
     * Confirmar recepción de un envío
     * @param int $idMovimientoEnvio - ID del movimiento (envío) que se recibe
     * @param int $idSucursal - Sucursal que recibe
     * @param int $idUsuario - Usuario que confirma
     * @param array $items - Array de items recibidos con formato:
     *                       ['id_movimiento_item' => X, 'cantidad_recibida' => Y, 'observaciones' => '']
     * @param string $observaciones - Observaciones generales
     * @return int - ID de la recepción creada
     */
    public function confirmar($idMovimientoEnvio, $idSucursal, $idUsuario, $items, $observaciones = null) {
        try {
            $this->db->beginTransaction();

            // 1. Validar que el envío existe y va a esta sucursal
            $stmt = $this->db->prepare("
                SELECT m.id, m.id_ubicacion_destino 
                FROM movimientos m
                WHERE m.id = ? AND m.id_ubicacion_destino = ?
            ");
            $stmt->execute([$idMovimientoEnvio, $idSucursal]);
            $envio = $stmt->fetch(\PDO::FETCH_ASSOC);

            if (!$envio) {
                throw new \Exception("El envío no existe o no corresponde a esta sucursal");
            }

            // 2. Validar que no haya sido recibido antes
            $stmt = $this->db->prepare("
                SELECT id FROM recepciones WHERE id_movimiento_envio = ?
            ");
            $stmt->execute([$idMovimientoEnvio]);
            if ($stmt->fetch()) {
                throw new \Exception("Este envío ya fue recibido anteriormente");
            }

            // 3. Obtener items del envío original
            $stmt = $this->db->prepare("
                SELECT mi.id, mi.id_productos, mi.cnt, mi.cnt_peso, p.descripcion
                FROM movimientos_items mi
                INNER JOIN productos p ON mi.id_productos = p.id
                WHERE mi.id_movimientos = ?
            ");
            $stmt->execute([$idMovimientoEnvio]);
            $itemsEnvio = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            // Indexar por ID
            $itemsEnvioMap = [];
            foreach ($itemsEnvio as $item) {
                $itemsEnvioMap[$item['id']] = $item;
            }

            // 4. Determinar estado de la recepción
            $hayDiferencias = false;
            $hayFaltantes = false;

            foreach ($items as $itemRecibido) {
                $idMovItem = $itemRecibido['id_movimiento_item'];
                if (!isset($itemsEnvioMap[$idMovItem])) {
                    throw new \Exception("Item de movimiento $idMovItem no pertenece a este envío");
                }

                $esperado = (float) $itemsEnvioMap[$idMovItem]['cnt'];
                $recibido = (float) $itemRecibido['cantidad_recibida'];

                if ($recibido < $esperado) {
                    $hayFaltantes = true;
                }
                if ($recibido != $esperado) {
                    $hayDiferencias = true;
                }
            }

            $estadoRecepcion = self::ESTADO_COMPLETA;
            if ($hayFaltantes) {
                $estadoRecepcion = self::ESTADO_PARCIAL;
            } elseif ($hayDiferencias) {
                $estadoRecepcion = self::ESTADO_CON_DIFERENCIAS;
            }

            // 5. Crear la recepción
            $stmt = $this->db->prepare("
                INSERT INTO recepciones 
                (id_movimiento_envio, id_sucursal, id_usuario, estado, observaciones, fecha_recepcion)
                VALUES (?, ?, ?, ?, ?, NOW())
            ");
            $stmt->execute([$idMovimientoEnvio, $idSucursal, $idUsuario, $estadoRecepcion, $observaciones]);
            $idRecepcion = $this->db->lastInsertId();

            // 6. Registrar items recibidos y actualizar stock
            foreach ($items as $itemRecibido) {
                $idMovItem = $itemRecibido['id_movimiento_item'];
                $itemOriginal = $itemsEnvioMap[$idMovItem];

                $cantidadEsperada = (float) $itemOriginal['cnt'];
                $cantidadRecibida = (float) $itemRecibido['cantidad_recibida'];
                $pesoEsperado = (float) $itemOriginal['cnt_peso'];
                $pesoRecibido = (float) ($itemRecibido['peso_recibido'] ?? $pesoEsperado);

                // Determinar estado del item
                $estadoItem = self::ITEM_OK;
                if ($cantidadRecibida < $cantidadEsperada) {
                    $estadoItem = self::ITEM_FALTANTE;
                } elseif ($cantidadRecibida > $cantidadEsperada) {
                    $estadoItem = self::ITEM_EXCEDENTE;
                }

                // Insertar item de recepción
                $stmt = $this->db->prepare("
                    INSERT INTO recepcion_items 
                    (id_recepcion, id_movimiento_item, id_producto, 
                     cantidad_esperada, cantidad_recibida, peso_esperado, peso_recibido,
                     estado_item, observaciones)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([
                    $idRecepcion,
                    $idMovItem,
                    $itemOriginal['id_productos'],
                    $cantidadEsperada,
                    $cantidadRecibida,
                    $pesoEsperado,
                    $pesoRecibido,
                    $estadoItem,
                    $itemRecibido['observaciones'] ?? null
                ]);

                // 7. Actualizar stock de sucursal
                $this->actualizarStockSucursal(
                    $idSucursal,
                    $itemOriginal['id_productos'],
                    $cantidadRecibida,
                    $pesoRecibido,
                    $idRecepcion,
                    $idUsuario
                );
            }

            // 8. Actualizar estado del envío en estados_items_movimientos
            $this->marcarEnvioComoRecibido($idMovimientoEnvio, $idUsuario);

            // 9. Actualizar pedidos relacionados
            $this->actualizarPedidosRelacionados($idMovimientoEnvio, $items);

            $this->db->commit();
            return $idRecepcion;

        } catch (\Exception $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    /**
     * Actualizar stock de sucursal
     */
    private function actualizarStockSucursal($idSucursal, $idProducto, $cantidad, $peso, $idRecepcion, $idUsuario) {
        // Obtener stock actual
        $stmt = $this->db->prepare("
            SELECT id, cantidad, peso FROM stock_sucursal 
            WHERE id_sucursal = ? AND id_producto = ?
            FOR UPDATE
        ");
        $stmt->execute([$idSucursal, $idProducto]);
        $stockActual = $stmt->fetch(\PDO::FETCH_ASSOC);

        $cantidadAnterior = $stockActual ? (float) $stockActual['cantidad'] : 0;
        $cantidadNueva = $cantidadAnterior + $cantidad;
        $pesoNuevo = ($stockActual ? (float) $stockActual['peso'] : 0) + $peso;

        if ($stockActual) {
            // Actualizar
            $stmt = $this->db->prepare("
                UPDATE stock_sucursal 
                SET cantidad = ?, peso = ?, fecha_ultima_entrada = NOW()
                WHERE id = ?
            ");
            $stmt->execute([$cantidadNueva, $pesoNuevo, $stockActual['id']]);
        } else {
            // Insertar
            $stmt = $this->db->prepare("
                INSERT INTO stock_sucursal 
                (id_sucursal, id_producto, cantidad, peso, fecha_ultima_entrada)
                VALUES (?, ?, ?, ?, NOW())
            ");
            $stmt->execute([$idSucursal, $idProducto, $cantidad, $peso]);
        }

        // Registrar movimiento de stock
        $stmt = $this->db->prepare("
            INSERT INTO stock_sucursal_movimientos
            (id_sucursal, id_producto, tipo_movimiento, cantidad, peso,
             cantidad_anterior, cantidad_posterior, id_referencia, tabla_referencia, id_usuario)
            VALUES (?, ?, 'RECEPCION', ?, ?, ?, ?, ?, 'recepciones', ?)
        ");
        $stmt->execute([
            $idSucursal,
            $idProducto,
            $cantidad,
            $peso,
            $cantidadAnterior,
            $cantidadNueva,
            $idRecepcion,
            $idUsuario
        ]);
    }

    /**
     * Marcar items del envío como RECIBIDO en estados_items_movimientos
     */
    private function marcarEnvioComoRecibido($idMovimientoEnvio, $usuario) {
        // Obtener items del envío
        $stmt = $this->db->prepare("
            SELECT id FROM movimientos_items WHERE id_movimientos = ?
        ");
        $stmt->execute([$idMovimientoEnvio]);
        $items = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        // Estado 3 = RECIBIDO (según tabla estados)
        foreach ($items as $item) {
            $stmt = $this->db->prepare("
                INSERT INTO estados_items_movimientos 
                (id_estados, id_movimientos_items, fecha_alta, usuario_alta)
                VALUES (3, ?, NOW(), ?)
            ");
            $stmt->execute([$item['id'], $usuario]);
        }
    }

    /**
     * Actualizar cantidad_recibida en pedidos relacionados
     */
    private function actualizarPedidosRelacionados($idMovimientoEnvio, $itemsRecibidos) {
        // Obtener pedidos relacionados a este envío
        $stmt = $this->db->prepare("
            SELECT pe.id_pedido, pei.id_pedido_item, pei.id_movimiento_item, pei.cantidad_asignada
            FROM pedido_envio pe
            INNER JOIN pedido_envio_items pei ON pe.id = pei.id_pedido_envio
            WHERE pe.id_movimiento_envio = ?
        ");
        $stmt->execute([$idMovimientoEnvio]);
        $relacionesPedido = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        // Indexar items recibidos
        $recibidosMap = [];
        foreach ($itemsRecibidos as $item) {
            $recibidosMap[$item['id_movimiento_item']] = $item['cantidad_recibida'];
        }

        // Actualizar cada pedido_item
        $pedidosActualizados = [];
        foreach ($relacionesPedido as $rel) {
            if (isset($recibidosMap[$rel['id_movimiento_item']])) {
                // Calcular proporción recibida
                $cantidadAsignada = (float) $rel['cantidad_asignada'];
                $cantidadRecibidaTotal = (float) $recibidosMap[$rel['id_movimiento_item']];
                
                // Actualizar pedido_item
                $stmt = $this->db->prepare("
                    UPDATE pedido_items 
                    SET cantidad_recibida = cantidad_recibida + ?
                    WHERE id = ?
                ");
                $stmt->execute([$cantidadRecibidaTotal, $rel['id_pedido_item']]);

                $pedidosActualizados[$rel['id_pedido']] = true;
            }
        }

        // Actualizar estado de cada pedido afectado
        $pedidoModel = new Pedido($this->db);
        foreach (array_keys($pedidosActualizados) as $idPedido) {
            $pedidoModel->actualizarEstadoPedido($idPedido);
        }
    }

    /**
     * Obtener recepción por ID
     */
    public function obtenerPorId($idRecepcion) {
        $stmt = $this->db->prepare("
            SELECT r.*,
                   u.nombre as sucursal,
                   us.nombre as usuario_nombre,
                   m.fechaAlta as fecha_envio
            FROM recepciones r
            INNER JOIN ubicaciones u ON r.id_sucursal = u.id
            INNER JOIN usuarios us ON r.id_usuario = us.id
            INNER JOIN movimientos m ON r.id_movimiento_envio = m.id
            WHERE r.id = ?
        ");
        $stmt->execute([$idRecepcion]);
        $recepcion = $stmt->fetch(\PDO::FETCH_ASSOC);

        if (!$recepcion) {
            return null;
        }

        // Obtener items
        $stmt = $this->db->prepare("
            SELECT ri.*,
                   p.codigo as codigo_producto,
                   p.descripcion as producto
            FROM recepcion_items ri
            INNER JOIN productos p ON ri.id_producto = p.id
            WHERE ri.id_recepcion = ?
        ");
        $stmt->execute([$idRecepcion]);
        $recepcion['items'] = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        return $recepcion;
    }

    /**
     * Listar envíos pendientes de recepción para una sucursal
     */
    public function listarEnviosPendientes($idSucursal) {
        $stmt = $this->db->prepare("
            SELECT 
                m.id as id_envio,
                m.fechaAlta as fecha_envio,
                m.usuario_alta as enviado_por,
                COUNT(DISTINCT mi.id) as total_items,
                SUM(mi.cnt) as total_cantidad,
                SUM(mi.cnt_peso) as total_peso
            FROM movimientos m
            INNER JOIN movimientos_items mi ON m.id = mi.id_movimientos
            LEFT JOIN recepciones r ON m.id = r.id_movimiento_envio
            WHERE m.id_ubicacion_origen = 1
            AND m.id_ubicacion_destino = ?
            AND mi.id_movimientos_items_origen IS NOT NULL
            AND r.id IS NULL
            GROUP BY m.id
            ORDER BY m.fechaAlta DESC
        ");
        $stmt->execute([$idSucursal]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Obtener detalle de un envío para recepción
     */
    public function obtenerDetalleEnvio($idMovimientoEnvio, $idSucursal) {
        // Validar que el envío corresponde a la sucursal
        $stmt = $this->db->prepare("
            SELECT m.id, m.fechaAlta, m.usuario_alta
            FROM movimientos m
            WHERE m.id = ? AND m.id_ubicacion_destino = ?
        ");
        $stmt->execute([$idMovimientoEnvio, $idSucursal]);
        $envio = $stmt->fetch(\PDO::FETCH_ASSOC);

        if (!$envio) {
            return null;
        }

        // Obtener items del envío
        $stmt = $this->db->prepare("
            SELECT 
                mi.id as id_movimiento_item,
                mi.id_productos,
                mi.cnt as cantidad,
                mi.cnt_peso as peso,
                p.codigo as codigo_producto,
                p.descripcion as producto,
                c.nombre as contenedor
            FROM movimientos_items mi
            INNER JOIN productos p ON mi.id_productos = p.id
            LEFT JOIN contenedores c ON mi.id_contenedor = c.id
            WHERE mi.id_movimientos = ?
        ");
        $stmt->execute([$idMovimientoEnvio]);
        $envio['items'] = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        return $envio;
    }

    /**
     * Listar recepciones de una sucursal
     */
    public function listarPorSucursal($idSucursal, $limite = 50, $offset = 0) {
        $stmt = $this->db->prepare("
            SELECT 
                r.id,
                r.id_movimiento_envio,
                r.estado,
                r.fecha_recepcion,
                r.observaciones,
                m.fechaAlta as fecha_envio,
                COUNT(DISTINCT ri.id) as total_items
            FROM recepciones r
            INNER JOIN movimientos m ON r.id_movimiento_envio = m.id
            LEFT JOIN recepcion_items ri ON r.id = ri.id_recepcion
            WHERE r.id_sucursal = ?
            GROUP BY r.id
            ORDER BY r.fecha_recepcion DESC
            LIMIT ? OFFSET ?
        ");
        $stmt->execute([$idSucursal, $limite, $offset]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }
}
