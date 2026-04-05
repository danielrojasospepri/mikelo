<?php
namespace App\Model;

/**
 * Columnas reales de BD:
 *   recepciones: id, id_envio, fecha_recepcion, recibido_por, observaciones
 *   recepcion_items: id, id_recepcion, id_movimiento_item, id_producto,
 *                    cantidad_enviada, cantidad_recibida, diferencia, peso_recibido, observaciones
 *   stock_sucursal: id, id_sucursal, id_producto, cantidad_actual, peso_total, ultima_actualizacion
 *   stock_sucursal_movimientos: id, id_stock_sucursal, tipo_movimiento, cantidad, peso,
 *                               id_recepcion, id_baja, referencia, fecha, usuario
 */
class Recepcion {
    private $db;

    public function __construct($db) {
        $this->db = $db;
    }

    public function listarEnviosPendientes($idSucursal, $fechaDesde = null) {
        $sql = "
            SELECT
                m.id,
                m.id as id_envio,
                m.fechaAlta as fecha_envio,
                m.usuario_alta as enviado_por,
                u.nombre as sucursal_destino,
                COUNT(DISTINCT mi.id) as total_items,
                SUM(mi.cnt) as total_cantidad,
                SUM(mi.cnt_peso) as total_peso
            FROM movimientos m
            INNER JOIN movimientos_items mi ON m.id = mi.id_movimientos
            INNER JOIN ubicaciones u ON m.id_ubicacion_destino = u.id
            LEFT JOIN recepciones r ON m.id = r.id_envio
            WHERE m.id_ubicacion_origen = 1
            AND r.id IS NULL
            AND m.id NOT IN (SELECT id_envio FROM envios_archivados)
        ";
        $params = [];
        if ($idSucursal) {
            $sql .= " AND m.id_ubicacion_destino = ?";
            $params[] = (int) $idSucursal;
        }
        if ($fechaDesde) {
            $sql .= " AND m.fechaAlta >= ?";
            $params[] = $fechaDesde . ' 00:00:00';
        }
        $sql .= " GROUP BY m.id ORDER BY m.fechaAlta DESC";
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    public function archivarEnvio($idEnvio, $idUsuario, $motivo = null) {
        // Verificar que el envío existe y no fue recibido
        $stmt = $this->db->prepare("SELECT id FROM movimientos WHERE id = ? AND id_ubicacion_origen = 1");
        $stmt->execute([(int)$idEnvio]);
        if (!$stmt->fetch()) {
            throw new \Exception("Envío no encontrado");
        }
        $stmt = $this->db->prepare("SELECT id FROM recepciones WHERE id_envio = ?");
        $stmt->execute([(int)$idEnvio]);
        if ($stmt->fetch()) {
            throw new \Exception("No se puede archivar un envío ya recibido");
        }
        $stmt = $this->db->prepare("
            INSERT INTO envios_archivados (id_envio, id_usuario, motivo)
            VALUES (?, ?, ?)
            ON DUPLICATE KEY UPDATE id_usuario = VALUES(id_usuario), motivo = VALUES(motivo), fecha_archivado = NOW()
        ");
        $stmt->execute([(int)$idEnvio, (int)$idUsuario, $motivo]);
        return true;
    }

    public function desarchivarEnvio($idEnvio) {
        $stmt = $this->db->prepare("DELETE FROM envios_archivados WHERE id_envio = ?");
        $stmt->execute([(int)$idEnvio]);
        return $stmt->rowCount() > 0;
    }

    public function listarArchivados($idSucursal) {
        if ($idSucursal) {
            $stmt = $this->db->prepare("
                SELECT m.id, m.fechaAlta as fecha_envio, u.nombre as sucursal_destino,
                       ea.motivo, ea.fecha_archivado,
                       COUNT(mi.id) as total_items
                FROM movimientos m
                INNER JOIN ubicaciones u ON m.id_ubicacion_destino = u.id
                INNER JOIN envios_archivados ea ON ea.id_envio = m.id
                LEFT JOIN movimientos_items mi ON mi.id_movimientos = m.id
                WHERE m.id_ubicacion_destino = ?
                GROUP BY m.id, m.fechaAlta, u.nombre, ea.motivo, ea.fecha_archivado
                ORDER BY ea.fecha_archivado DESC
            ");
            $stmt->execute([(int)$idSucursal]);
        } else {
            $stmt = $this->db->prepare("
                SELECT m.id, m.fechaAlta as fecha_envio, u.nombre as sucursal_destino,
                       ea.motivo, ea.fecha_archivado,
                       COUNT(mi.id) as total_items
                FROM movimientos m
                INNER JOIN ubicaciones u ON m.id_ubicacion_destino = u.id
                INNER JOIN envios_archivados ea ON ea.id_envio = m.id
                LEFT JOIN movimientos_items mi ON mi.id_movimientos = m.id
                GROUP BY m.id, m.fechaAlta, u.nombre, ea.motivo, ea.fecha_archivado
                ORDER BY ea.fecha_archivado DESC
            ");
            $stmt->execute();
        }
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    public function obtenerDetalleEnvio($idEnvio, $idSucursal) {
        if ($idSucursal) {
            $stmt = $this->db->prepare("
                SELECT m.id, m.fechaAlta as fecha_envio, m.usuario_alta,
                       u.nombre as sucursal_destino
                FROM movimientos m
                INNER JOIN ubicaciones u ON m.id_ubicacion_destino = u.id
                WHERE m.id = ? AND m.id_ubicacion_destino = ?
            ");
            $stmt->execute([(int)$idEnvio, (int)$idSucursal]);
        } else {
            $stmt = $this->db->prepare("
                SELECT m.id, m.fechaAlta as fecha_envio, m.usuario_alta,
                       u.nombre as sucursal_destino
                FROM movimientos m
                INNER JOIN ubicaciones u ON m.id_ubicacion_destino = u.id
                WHERE m.id = ?
            ");
            $stmt->execute([(int)$idEnvio]);
        }
        $envio = $stmt->fetch(\PDO::FETCH_ASSOC);
        if (!$envio) return null;

        $stmt = $this->db->prepare("
            SELECT
                mi.id as id_movimiento_item,
                mi.id_productos,
                mi.cnt as cantidad,
                mi.cnt_peso as peso,
                mi.id_contenedor,
                p.codigo as codigo_producto,
                p.descripcion as producto,
                c.id as contenedor_id,
                c.nombre as contenedor,
                c.peso as contenedor_peso
            FROM movimientos_items mi
            INNER JOIN productos p ON mi.id_productos = p.id
            LEFT JOIN contenedores c ON mi.id_contenedor = c.id
            WHERE mi.id_movimientos = ?
            ORDER BY p.descripcion, mi.cnt_peso DESC, mi.cnt DESC
        ");
        $stmt->execute([(int)$idEnvio]);
        $envio['items'] = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        return $envio;
    }

    public function confirmar($idEnvio, $idSucursal, $idUsuario, $items, $observaciones = null) {
        try {
            $this->db->beginTransaction();

            $stmt = $this->db->prepare("
                SELECT m.id FROM movimientos m WHERE m.id = ? AND m.id_ubicacion_destino = ?
            ");
            $stmt->execute([(int)$idEnvio, (int)$idSucursal]);
            if (!$stmt->fetch()) {
                throw new \Exception("El envio no existe o no corresponde a esta sucursal");
            }

            $stmt = $this->db->prepare("SELECT id FROM recepciones WHERE id_envio = ?");
            $stmt->execute([(int)$idEnvio]);
            if ($stmt->fetch()) {
                throw new \Exception("Este envio ya fue recibido anteriormente");
            }

            $stmt = $this->db->prepare("
                SELECT id, id_productos, cnt, cnt_peso FROM movimientos_items WHERE id_movimientos = ?
            ");
            $stmt->execute([(int)$idEnvio]);
            $itemsMap = [];
            foreach ($stmt->fetchAll(\PDO::FETCH_ASSOC) as $i) $itemsMap[$i['id']] = $i;

            $stmt = $this->db->prepare("
                INSERT INTO recepciones (id_envio, recibido_por, observaciones) VALUES (?, ?, ?)
            ");
            $stmt->execute([(int)$idEnvio, (int)$idUsuario, $observaciones]);
            $idRecepcion = (int) $this->db->lastInsertId();

            foreach ($items as $itemRecibido) {
                $idMovItem    = (int) $itemRecibido['id_movimiento_item'];
                if (!isset($itemsMap[$idMovItem])) {
                    throw new \Exception("Item $idMovItem no pertenece a este envio");
                }
                $orig         = $itemsMap[$idMovItem];
                $cantEnviada  = (float) $orig['cnt'];
                $cantRecibida = (float) $itemRecibido['cantidad_recibida'];
                $pesoRecibido = (float) ($itemRecibido['peso_recibido'] ?? $orig['cnt_peso']);
                $diferencia   = $cantRecibida - $cantEnviada;

                $stmt = $this->db->prepare("
                    INSERT INTO recepcion_items
                    (id_recepcion, id_movimiento_item, id_producto,
                     cantidad_enviada, cantidad_recibida, diferencia, peso_recibido, observaciones)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([
                    $idRecepcion, $idMovItem, (int)$orig['id_productos'],
                    $cantEnviada, $cantRecibida, $diferencia, $pesoRecibido,
                    $itemRecibido['observaciones'] ?? null
                ]);

                $this->actualizarStockSucursal(
                    $idSucursal, (int)$orig['id_productos'],
                    $cantRecibida, $pesoRecibido, $idRecepcion, $idUsuario
                );
            }

            $this->marcarEnvioComoRecibido($idEnvio, $idUsuario);
            $this->actualizarPedidosRelacionados($idEnvio, $items);

            $this->db->commit();
            return $idRecepcion;

        } catch (\Exception $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    private function actualizarStockSucursal($idSucursal, $idProducto, $cantidad, $peso, $idRecepcion, $idUsuario) {
        $stmt = $this->db->prepare("
            SELECT id, cantidad_actual, peso_total FROM stock_sucursal
            WHERE id_sucursal = ? AND id_producto = ? FOR UPDATE
        ");
        $stmt->execute([$idSucursal, $idProducto]);
        $stock = $stmt->fetch(\PDO::FETCH_ASSOC);

        if ($stock) {
            $stmt = $this->db->prepare("
                UPDATE stock_sucursal SET cantidad_actual = ?, peso_total = ?, ultima_actualizacion = NOW()
                WHERE id = ?
            ");
            $stmt->execute([(float)$stock['cantidad_actual'] + $cantidad, (float)$stock['peso_total'] + $peso, (int)$stock['id']]);
            $idSS = (int)$stock['id'];
        } else {
            $stmt = $this->db->prepare("
                INSERT INTO stock_sucursal (id_sucursal, id_producto, cantidad_actual, peso_total) VALUES (?, ?, ?, ?)
            ");
            $stmt->execute([$idSucursal, $idProducto, $cantidad, $peso]);
            $idSS = (int)$this->db->lastInsertId();
        }

        $stmt = $this->db->prepare("
            INSERT INTO stock_sucursal_movimientos
            (id_stock_sucursal, tipo_movimiento, cantidad, peso, id_recepcion, usuario)
            VALUES (?, 'entrada', ?, ?, ?, ?)
        ");
        $stmt->execute([$idSS, $cantidad, $peso, $idRecepcion, $idUsuario]);
    }

    private function marcarEnvioComoRecibido($idEnvio, $usuario) {
        $stmt = $this->db->prepare("SELECT id FROM movimientos_items WHERE id_movimientos = ?");
        $stmt->execute([(int)$idEnvio]);
        foreach ($stmt->fetchAll(\PDO::FETCH_ASSOC) as $item) {
            $stmt2 = $this->db->prepare("
                INSERT INTO estados_items_movimientos (id_estados, id_movimientos_items, fecha_alta, usuario_alta)
                VALUES (3, ?, NOW(), ?)
            ");
            $stmt2->execute([(int)$item['id'], $usuario]);
        }
    }

    private function actualizarPedidosRelacionados($idEnvio, $itemsRecibidos) {
        $stmt = $this->db->prepare("
            SELECT pe.id_pedido, pei.id_pedido_item, pei.id_movimiento_item, pei.cantidad
            FROM pedido_envio pe
            INNER JOIN pedido_envio_items pei ON pe.id = pei.id_pedido_envio
            WHERE pe.id_envio = ?
        ");
        $stmt->execute([(int)$idEnvio]);
        $relaciones = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        if (empty($relaciones)) return;

        $recibidosMap = [];
        foreach ($itemsRecibidos as $i) $recibidosMap[(int)$i['id_movimiento_item']] = (float)$i['cantidad_recibida'];

        $pedidosActualizados = [];
        foreach ($relaciones as $rel) {
            $idMovItem = (int)$rel['id_movimiento_item'];
            if (isset($recibidosMap[$idMovItem])) {
                $stmt = $this->db->prepare("
                    UPDATE pedido_items SET cantidad_enviada = COALESCE(cantidad_enviada,0) + ? WHERE id = ?
                ");
                $stmt->execute([$recibidosMap[$idMovItem], (int)$rel['id_pedido_item']]);
                $pedidosActualizados[(int)$rel['id_pedido']] = true;
            }
        }

        // Actualizar estado de cada pedido afectado
        foreach (array_keys($pedidosActualizados) as $idPedido) {
            $this->actualizarEstadoPedidoRecibido($idPedido);
        }
    }

    private function actualizarEstadoPedidoRecibido($idPedido) {
        $stmt = $this->db->prepare("
            SELECT
                SUM(pi.cantidad) as total_solicitada,
                SUM(COALESCE(pi.cantidad_enviada, 0)) as total_enviada,
                COALESCE(
                    (SELECT SUM(ri.cantidad_recibida)
                     FROM recepcion_items ri
                     INNER JOIN pedido_envio_items pei ON ri.id_movimiento_item = pei.id_movimiento_item
                     INNER JOIN pedido_envio pe ON pei.id_pedido_envio = pe.id
                     WHERE pe.id_pedido = pi.id_pedido
                    ), 0) as total_recibida
            FROM pedido_items pi
            WHERE pi.id_pedido = ?
        ");
        $stmt->execute([$idPedido]);
        $totales = $stmt->fetch(\PDO::FETCH_ASSOC);

        $solicitada = (float)($totales['total_solicitada'] ?? 0);
        $recibida   = (float)($totales['total_recibida']  ?? 0);
        $enviada    = (float)($totales['total_enviada']   ?? 0);

        if ($solicitada <= 0) return;

        if ($recibida >= $solicitada) {
            $nuevoEstado = 'RECIBIDO';
        } elseif ($recibida > 0) {
            $nuevoEstado = 'RECIBIDO_PARCIAL';
        } elseif ($enviada >= $solicitada) {
            $nuevoEstado = 'ENVIADO';
        } elseif ($enviada > 0) {
            $nuevoEstado = 'EN_PROCESO';
        } else {
            $nuevoEstado = 'PENDIENTE';
        }

        $stmt = $this->db->prepare("UPDATE pedidos SET estado = ? WHERE id = ? AND estado != 'ANULADO'");
        $stmt->execute([$nuevoEstado, $idPedido]);
    }

    public function obtenerPorId($idRecepcion) {
        $stmt = $this->db->prepare("
            SELECT r.id, r.id_envio, r.fecha_recepcion, r.observaciones,
                   u_dest.nombre as sucursal,
                   TRIM(CONCAT(us.nombre, ' ', COALESCE(us.apellido, ''))) as recibido_por_nombre,
                   m.fechaAlta as fecha_envio
            FROM recepciones r
            INNER JOIN movimientos m ON r.id_envio = m.id
            INNER JOIN ubicaciones u_dest ON m.id_ubicacion_destino = u_dest.id
            LEFT JOIN usuarios us ON r.recibido_por = us.id
            WHERE r.id = ?
        ");
        $stmt->execute([(int)$idRecepcion]);
        $rec = $stmt->fetch(\PDO::FETCH_ASSOC);
        if (!$rec) return null;

        $stmt = $this->db->prepare("
            SELECT ri.id, ri.id_movimiento_item, ri.cantidad_enviada, ri.cantidad_recibida,
                   ri.diferencia, ri.peso_recibido, ri.observaciones,
                   p.codigo as codigo_producto, p.descripcion as producto
            FROM recepcion_items ri
            INNER JOIN productos p ON ri.id_producto = p.id
            WHERE ri.id_recepcion = ?
        ");
        $stmt->execute([(int)$idRecepcion]);
        $rec['items'] = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        return $rec;
    }

    public function listarPorSucursal($idSucursal, $limite = 50, $offset = 0) {
        $limite = (int)$limite;
        $offset = (int)$offset;

        if ($idSucursal) {
            $stmt = $this->db->prepare("
                SELECT r.id, r.id_envio, r.fecha_recepcion, r.observaciones,
                       m.fechaAlta as fecha_envio, u_dest.nombre as sucursal,
                       COUNT(DISTINCT ri.id) as total_items,
                       TRIM(CONCAT(us.nombre, ' ', COALESCE(us.apellido, ''))) as recibido_por_nombre
                FROM recepciones r
                INNER JOIN movimientos m ON r.id_envio = m.id
                INNER JOIN ubicaciones u_dest ON m.id_ubicacion_destino = u_dest.id
                LEFT JOIN recepcion_items ri ON r.id = ri.id_recepcion
                LEFT JOIN usuarios us ON r.recibido_por = us.id
                WHERE m.id_ubicacion_destino = ?
                GROUP BY r.id ORDER BY r.fecha_recepcion DESC
                LIMIT $limite OFFSET $offset
            ");
            $stmt->execute([(int)$idSucursal]);
        } else {
            $stmt = $this->db->prepare("
                SELECT r.id, r.id_envio, r.fecha_recepcion, r.observaciones,
                       m.fechaAlta as fecha_envio, u_dest.nombre as sucursal,
                       COUNT(DISTINCT ri.id) as total_items,
                       TRIM(CONCAT(us.nombre, ' ', COALESCE(us.apellido, ''))) as recibido_por_nombre
                FROM recepciones r
                INNER JOIN movimientos m ON r.id_envio = m.id
                INNER JOIN ubicaciones u_dest ON m.id_ubicacion_destino = u_dest.id
                LEFT JOIN recepcion_items ri ON r.id = ri.id_recepcion
                LEFT JOIN usuarios us ON r.recibido_por = us.id
                GROUP BY r.id ORDER BY r.fecha_recepcion DESC
                LIMIT $limite OFFSET $offset
            ");
            $stmt->execute([]);
        }
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }
}