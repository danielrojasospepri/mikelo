<?php
namespace App\Model;

/**
 * Modelo para gestión de Stock en Sucursales
 * Consultas y operaciones sobre el stock local de cada sucursal
 */
class StockSucursal {
    private $db;

    public function __construct($db) {
        $this->db = $db;
    }

    /**
     * Obtener stock actual de una sucursal
     * @param int $idSucursal
     * @param int|null $idFamilia - Filtrar por familia de producto
     * @return array
     */
    public function obtenerStock($idSucursal, $idFamilia = null) {
        $sql = "
            SELECT 
                ss.id,
                ss.id_producto,
                p.codigo,
                p.descripcion as producto,
                f.nombre as tipo_producto,
                ss.cantidad_actual,
                ss.peso_total,
                ss.ultima_actualizacion
            FROM stock_sucursal ss
            INNER JOIN productos p ON ss.id_producto = p.id
            LEFT JOIN tipo_producto f ON p.id_tipo_producto = f.id
            WHERE ss.id_sucursal = ?
            AND ss.cantidad_actual > 0
        ";
        $params = [$idSucursal];

        if ($idFamilia) {
            $sql .= " AND p.id_tipo_producto = ?";
            $params[] = $idFamilia;
        }

        $sql .= " ORDER BY f.nombre, p.descripcion";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Obtener stock de un producto específico en una sucursal
     */
    public function obtenerStockProducto($idSucursal, $idProducto) {
        $stmt = $this->db->prepare("
            SELECT ss.*, p.descripcion as producto, p.codigo
            FROM stock_sucursal ss
            INNER JOIN productos p ON ss.id_producto = p.id
            WHERE ss.id_sucursal = ? AND ss.id_producto = ?
        ");
        $stmt->execute([$idSucursal, $idProducto]);
        return $stmt->fetch(\PDO::FETCH_ASSOC);
    }

    /**
     * Obtener historial de movimientos de stock de una sucursal
     */
    public function obtenerHistorial($idSucursal, $limite = 100, $idProducto = null) {
        $limite = (int)$limite;
        $sql = "
            SELECT 
                ssm.id,
                ssm.tipo_movimiento,
                ssm.cantidad,
                ssm.peso,
                ssm.referencia,
                ssm.fecha,
                ss.id_producto,
                p.codigo,
                p.descripcion as producto,
                us.nombre as usuario_nombre
            FROM stock_sucursal_movimientos ssm
            INNER JOIN stock_sucursal ss ON ssm.id_stock_sucursal = ss.id
            INNER JOIN productos p ON ss.id_producto = p.id
            LEFT JOIN usuarios us ON ssm.usuario = us.id
            WHERE ss.id_sucursal = ?
        ";
        $params = [$idSucursal];

        if ($idProducto) {
            $sql .= " AND ss.id_producto = ?";
            $params[] = $idProducto;
        }

        $sql .= " ORDER BY ssm.fecha DESC LIMIT $limite";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Obtener resumen de stock de todas las sucursales (para planta)
     */
    public function obtenerResumenTodasSucursales($idProducto = null) {
        $sql = "
            SELECT 
                u.id as id_sucursal,
                u.nombre as sucursal,
                u.tipo_ubicacion,
                COUNT(DISTINCT ss.id_producto) as total_productos,
                SUM(ss.cantidad_actual) as total_cantidad,
                SUM(ss.peso_total) as total_peso
            FROM ubicaciones u
            LEFT JOIN stock_sucursal ss ON u.id = ss.id_sucursal
            WHERE u.id != 1
        ";
        $params = [];

        if ($idProducto) {
            $sql .= " AND (ss.id_producto = ? OR ss.id_producto IS NULL)";
            $params[] = $idProducto;
        }

        $sql .= " GROUP BY u.id ORDER BY u.nombre";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Verificar si hay stock suficiente para una operación
     */
    public function verificarDisponibilidad($idSucursal, $idProducto, $cantidadRequerida) {
        $stock = $this->obtenerStockProducto($idSucursal, $idProducto);
        
        if (!$stock) {
            return [
                'disponible' => false,
                'cantidad_actual' => 0,
                'cantidad_requerida' => $cantidadRequerida,
                'faltante' => $cantidadRequerida
            ];
        }

        $cantidadActual = (float) $stock['cantidad_actual'];
        $disponible = $cantidadActual >= $cantidadRequerida;

        return [
            'disponible' => $disponible,
            'cantidad_actual' => $cantidadActual,
            'cantidad_requerida' => $cantidadRequerida,
            'faltante' => $disponible ? 0 : ($cantidadRequerida - $cantidadActual)
        ];
    }

    /**
     * Buscar productos en stock por código o descripción
     */
    public function buscarProducto($idSucursal, $termino) {
        $stmt = $this->db->prepare("
            SELECT 
                ss.id,
                ss.id_producto,
                p.codigo,
                p.descripcion as producto,
                ss.cantidad_actual as cantidad,
                ss.peso_total as peso
            FROM stock_sucursal ss
            INNER JOIN productos p ON ss.id_producto = p.id
            WHERE ss.id_sucursal = ?
            AND (p.codigo LIKE ? OR p.descripcion LIKE ?)
            AND ss.cantidad_actual > 0
            ORDER BY p.descripcion
            LIMIT 20
        ");
        $busqueda = "%{$termino}%";
        $stmt->execute([$idSucursal, $busqueda, $busqueda]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Obtener productos con stock bajo (comparado con promedio de movimientos)
     * Nota: Esta es una versión simplificada. En Fase 3 se usa stock_minimo_config
     */
    public function obtenerStockBajo($idSucursal, $umbralMinimo = 5) {
        $stmt = $this->db->prepare("
            SELECT 
                ss.id,
                ss.id_producto,
                p.codigo,
                p.descripcion as producto,
                ss.cantidad_actual as cantidad,
                ss.peso_total as peso,
                ? as umbral
            FROM stock_sucursal ss
            INNER JOIN productos p ON ss.id_producto = p.id
            WHERE ss.id_sucursal = ?
            AND ss.cantidad_actual <= ?
            AND ss.cantidad_actual > 0
            ORDER BY ss.cantidad_actual ASC
        ");
        $stmt->execute([$umbralMinimo, $idSucursal, $umbralMinimo]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Obtener totales de stock de una sucursal
     */
    public function obtenerTotales($idSucursal) {
        $stmt = $this->db->prepare("
            SELECT 
                COUNT(DISTINCT id_producto) as total_productos,
                SUM(cantidad_actual) as total_cantidad,
                SUM(peso_total) as total_peso
            FROM stock_sucursal
            WHERE id_sucursal = ?
            AND cantidad_actual > 0
        ");
        $stmt->execute([$idSucursal]);
        return $stmt->fetch(\PDO::FETCH_ASSOC);
    }

    /**
     * Registrar baja de stock (venta, merma)
     * @param int    $idSucursal
     * @param int    $idProducto
     * @param float  $cantidad   - Unidades a dar de baja (0 para productos de peso)
     * @param float  $peso       - Kilos a dar de baja (0 para productos de unidades)
     * @param string $tipoBaja   - BAJA_VENTA, BAJA_MERMA, AJUSTE_NEGATIVO
     * @param int    $idUsuario
     * @param string $observaciones
     * @return array
     */
    public function registrarBaja($idSucursal, $idProducto, $cantidad, $peso, $tipoBaja, $idUsuario, $observaciones = null) {
        try {
            $this->db->beginTransaction();

            $cantidad = abs((float)$cantidad);
            $peso     = abs((float)$peso);
            $esPeso   = $peso > 0;

            // Obtener stock actual
            $stmt = $this->db->prepare("
                SELECT id, cantidad_actual, peso_total
                FROM stock_sucursal
                WHERE id_sucursal = ? AND id_producto = ?
                FOR UPDATE
            ");
            $stmt->execute([$idSucursal, $idProducto]);
            $stockActual = $stmt->fetch(\PDO::FETCH_ASSOC);

            if (!$stockActual) {
                throw new \Exception("No existe stock de este producto en la sucursal");
            }

            $cantidadAnterior = (float)$stockActual['cantidad_actual'];
            $pesoAnterior     = (float)$stockActual['peso_total'];

            if ($esPeso) {
                // Producto por peso: validar y descontar peso_total Y cantidad_actual (bandejas)
                if ($peso > $pesoAnterior + 0.0001) {
                    throw new \Exception("No hay suficiente stock de peso. Disponible: {$pesoAnterior} kg");
                }
                if ($cantidad > 0 && $cantidad > $cantidadAnterior) {
                    throw new \Exception("No hay suficiente stock de bandejas. Disponible: " . (int)$cantidadAnterior);
                }
                $cantidadPosterior = max(0, $cantidadAnterior - $cantidad); // decrementar bandejas
                $pesoPosterior     = round($pesoAnterior - $peso, 4);
                $stockAnteriorDisplay = $pesoAnterior;
                $stockActualDisplay   = $pesoPosterior;
            } else {
                // Producto por unidades: validar y descontar de cantidad_actual
                if ($cantidad > $cantidadAnterior) {
                    throw new \Exception("No hay suficiente stock. Disponible: {$cantidadAnterior}");
                }
                $cantidadPosterior = $cantidadAnterior - $cantidad;
                $pesoPosterior     = $pesoAnterior; // peso_total sin cambio
                $stockAnteriorDisplay = $cantidadAnterior;
                $stockActualDisplay   = $cantidadPosterior;
            }

            // Actualizar stock (ambas columnas)
            $stmt = $this->db->prepare("
                UPDATE stock_sucursal
                SET cantidad_actual = ?,
                    peso_total = ?,
                    ultima_actualizacion = NOW()
                WHERE id = ?
            ");
            $stmt->execute([$cantidadPosterior, $pesoPosterior, $stockActual['id']]);

            // Registrar movimiento incluyendo peso
            $stmt = $this->db->prepare("
                INSERT INTO stock_sucursal_movimientos
                (id_stock_sucursal, tipo_movimiento, cantidad, peso, referencia, usuario)
                VALUES (?, 'salida', ?, ?, ?, ?)
            ");
            $stmt->execute([
                $stockActual['id'],
                -$cantidad,                    // siempre registra el nro de bandejas/unidades
                $esPeso ? -$peso    : 0,      // peso solo para productos por peso
                $observaciones ?? $tipoBaja,
                $idUsuario
            ]);
            $movimientoId = $this->db->lastInsertId();

            $this->db->commit();

            return [
                'movimiento_id'  => $movimientoId,
                'stock_anterior' => $stockAnteriorDisplay,
                'stock_actual'   => $stockActualDisplay,
                'es_peso'        => $esPeso
            ];

        } catch (\Exception $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    /**
     * Obtener lista de bandejas disponibles para un producto por peso
     * Reconstruye las bandejas individuales usando FIFO desde stock_sucursal_movimientos
     * @param int $idSucursal
     * @param int $idProducto
     * @return array ['bandejas' => array[['id'=>int,'peso'=>float]], 'peso_total' => float]
     */
    public function obtenerBandejas($idSucursal, $idProducto) {
        // Obtiene directamente de recepcion_items las bandejas recibidas
        // que aún no fueron dadas de baja (dado_de_baja = 0), en orden FIFO.
        $stmt = $this->db->prepare("
            SELECT ri.id AS id_recepcion_item, ri.peso_recibido AS peso
            FROM recepcion_items ri
            INNER JOIN recepciones r ON ri.id_recepcion = r.id
            INNER JOIN movimientos m ON r.id_envio = m.id
            WHERE ri.id_producto         = ?
              AND m.id_ubicacion_destino  = ?
              AND ri.dado_de_baja         = 0
              AND ri.peso_recibido        > 0
            ORDER BY r.fecha_recepcion ASC, ri.id ASC
        ");
        $stmt->execute([$idProducto, $idSucursal]);
        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        $bandejas  = array_map(fn($r) => [
            'id'   => (int)$r['id_recepcion_item'],
            'peso' => round((float)$r['peso'], 3)
        ], $rows);

        return [
            'bandejas'   => $bandejas,
            'peso_total' => round(array_sum(array_column($rows, 'peso')), 3)
        ];
    }

    /**
     * Registrar baja de una bandeja por peso escaneada con código de barras.
     * Busca en recepcion_items (FIFO) una bandeja no consumida con ese peso
     * y la marca como dada de baja. Previene doble-baja.
     */
    public function registrarBajaBarcodePeso($idSucursal, $idProducto, $peso, $tipoBaja, $idUsuario, $observaciones = null) {
        try {
            $this->db->beginTransaction();
            $peso = round((float)$peso, 3);

            // 1. Buscar bandeja recibida con ese peso que no haya sido dada de baja (FIFO)
            $stmt = $this->db->prepare("
                SELECT ri.id, ri.peso_recibido
                FROM recepcion_items ri
                INNER JOIN recepciones r ON ri.id_recepcion = r.id
                INNER JOIN movimientos m ON r.id_envio = m.id
                WHERE ri.id_producto         = ?
                  AND m.id_ubicacion_destino  = ?
                  AND ABS(ri.peso_recibido - ?) < 0.002
                  AND ri.dado_de_baja          = 0
                ORDER BY r.fecha_recepcion ASC, ri.id ASC
                LIMIT 1
                FOR UPDATE
            ");
            $stmt->execute([$idProducto, $idSucursal, $peso]);
            $bandeja = $stmt->fetch(\PDO::FETCH_ASSOC);

            if (!$bandeja) {
                throw new \Exception("Esta bandeja no fue recibida en la sucursal o ya fue dada de baja");
            }

            $pesoReal = round((float)$bandeja['peso_recibido'], 3);

            // 2. Obtener y bloquear stock
            $stmt = $this->db->prepare("
                SELECT id, cantidad_actual, peso_total
                FROM stock_sucursal
                WHERE id_sucursal = ? AND id_producto = ?
                FOR UPDATE
            ");
            $stmt->execute([$idSucursal, $idProducto]);
            $stock = $stmt->fetch(\PDO::FETCH_ASSOC);

            if (!$stock) {
                throw new \Exception("No existe stock de este producto en la sucursal");
            }

            $pesoAnterior  = (float)$stock['peso_total'];
            $cantAnterior  = (float)$stock['cantidad_actual'];
            $pesoPosterior = max(0.0, round($pesoAnterior - $pesoReal, 4));
            $cantPosterior = max(0, $cantAnterior - 1);

            // 3. Actualizar stock
            $stmt = $this->db->prepare("
                UPDATE stock_sucursal
                SET cantidad_actual = ?, peso_total = ?, ultima_actualizacion = NOW()
                WHERE id = ?
            ");
            $stmt->execute([$cantPosterior, $pesoPosterior, $stock['id']]);

            // 4. Registrar movimiento
            $stmt = $this->db->prepare("
                INSERT INTO stock_sucursal_movimientos
                    (id_stock_sucursal, tipo_movimiento, cantidad, peso, referencia, usuario)
                VALUES (?, 'salida', -1, ?, ?, ?)
            ");
            $stmt->execute([$stock['id'], -$pesoReal, $observaciones ?? $tipoBaja, $idUsuario]);
            $idMovimiento = (int)$this->db->lastInsertId();

            // 5. Marcar bandeja como dada de baja
            $stmt = $this->db->prepare("
                UPDATE recepcion_items
                SET dado_de_baja = 1, id_movimiento_baja = ?
                WHERE id = ?
            ");
            $stmt->execute([$idMovimiento, $bandeja['id']]);

            $this->db->commit();

            return [
                'stock_anterior' => $pesoAnterior,
                'stock_actual'   => $pesoPosterior,
                'peso_bajado'    => $pesoReal,
                'es_peso'        => true
            ];

        } catch (\Exception $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    /**
     * Registrar baja de un conjunto de bandejas seleccionadas manualmente.
     * Valida que los recepcion_item_ids pertenezcan al producto/sucursal
     * y que ninguno haya sido dado de baja anteriormente.
     * @param array $recepcionItemIds  IDs de recepcion_items a consumir
     */
    public function registrarBajaBandejas($idSucursal, $idProducto, array $recepcionItemIds, $tipoBaja, $idUsuario, $observaciones = null) {
        if (empty($recepcionItemIds)) {
            throw new \Exception("Debe seleccionar al menos una bandeja");
        }

        try {
            $this->db->beginTransaction();

            $placeholders = implode(',', array_fill(0, count($recepcionItemIds), '?'));

            // 1. Bloquear y validar items
            $stmt = $this->db->prepare("
                SELECT ri.id, ri.peso_recibido, ri.dado_de_baja
                FROM recepcion_items ri
                INNER JOIN recepciones r ON ri.id_recepcion = r.id
                INNER JOIN movimientos m ON r.id_envio = m.id
                WHERE ri.id IN ($placeholders)
                  AND ri.id_producto         = ?
                  AND m.id_ubicacion_destino  = ?
                FOR UPDATE
            ");
            $params = array_merge($recepcionItemIds, [$idProducto, $idSucursal]);
            $stmt->execute($params);
            $items = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            if (count($items) !== count($recepcionItemIds)) {
                throw new \Exception("Algunas bandejas no corresponden a este producto o sucursal");
            }

            foreach ($items as $item) {
                if ((int)$item['dado_de_baja'] === 1) {
                    throw new \Exception("La bandeja #" . $item['id'] . " ya fue dada de baja anteriormente");
                }
            }

            $pesoBajar = round(array_sum(array_column($items, 'peso_recibido')), 4);
            $cantBajar = count($items);

            // 2. Obtener y bloquear stock
            $stmt = $this->db->prepare("
                SELECT id, cantidad_actual, peso_total
                FROM stock_sucursal
                WHERE id_sucursal = ? AND id_producto = ?
                FOR UPDATE
            ");
            $stmt->execute([$idSucursal, $idProducto]);
            $stock = $stmt->fetch(\PDO::FETCH_ASSOC);

            if (!$stock) {
                throw new \Exception("No existe stock de este producto en la sucursal");
            }

            $pesoAnterior  = (float)$stock['peso_total'];
            $cantAnterior  = (float)$stock['cantidad_actual'];
            $pesoPosterior = max(0.0, round($pesoAnterior - $pesoBajar, 4));
            $cantPosterior = max(0, $cantAnterior - $cantBajar);

            // 3. Actualizar stock
            $stmt = $this->db->prepare("
                UPDATE stock_sucursal
                SET cantidad_actual = ?, peso_total = ?, ultima_actualizacion = NOW()
                WHERE id = ?
            ");
            $stmt->execute([$cantPosterior, $pesoPosterior, $stock['id']]);

            // 4. Registrar movimiento
            $stmt = $this->db->prepare("
                INSERT INTO stock_sucursal_movimientos
                    (id_stock_sucursal, tipo_movimiento, cantidad, peso, referencia, usuario)
                VALUES (?, 'salida', ?, ?, ?, ?)
            ");
            $stmt->execute([$stock['id'], -$cantBajar, -$pesoBajar, $observaciones ?? $tipoBaja, $idUsuario]);
            $idMovimiento = (int)$this->db->lastInsertId();

            // 5. Marcar todas las bandejas como dadas de baja
            $stmt = $this->db->prepare("
                UPDATE recepcion_items
                SET dado_de_baja = 1, id_movimiento_baja = ?
                WHERE id IN ($placeholders)
            ");
            $stmt->execute(array_merge([$idMovimiento], $recepcionItemIds));

            $this->db->commit();

            return [
                'stock_anterior' => $pesoAnterior,
                'stock_actual'   => $pesoPosterior,
                'peso_bajado'    => $pesoBajar,
                'bandejas'       => $cantBajar,
                'es_peso'        => true
            ];

        } catch (\Exception $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    /**
     * Registrar ajuste de stock (inventario físico)
     * Ajusta el stock al valor real contado
     * @param int    $idSucursal
     * @param int    $idProducto
     * @param float  $cantidadReal - Cantidad real contada (0 para productos de peso)
     * @param int    $idUsuario
     * @param string $observaciones
     * @param float|null $pesoReal - Peso real en kg (null = producto de unidades)
     * @return array
     */
    public function registrarAjuste($idSucursal, $idProducto, $cantidadReal, $idUsuario, $observaciones = null, $pesoReal = null) {
        try {
            $this->db->beginTransaction();

            $cantidadReal = (float)$cantidadReal;
            $esPeso = $pesoReal !== null;
            $pesoReal = $esPeso ? (float)$pesoReal : null;

            // Obtener stock actual
            $stmt = $this->db->prepare("
                SELECT id, cantidad_actual, peso_total
                FROM stock_sucursal
                WHERE id_sucursal = ? AND id_producto = ?
                FOR UPDATE
            ");
            $stmt->execute([$idSucursal, $idProducto]);
            $stockActual = $stmt->fetch(\PDO::FETCH_ASSOC);

            $cantidadAnterior = $stockActual ? (float)$stockActual['cantidad_actual'] : 0;
            $pesoAnterior     = $stockActual ? (float)$stockActual['peso_total']      : 0;

            if ($esPeso) {
                // Producto por peso: ajustar peso_total
                $diferencia = round($pesoReal - $pesoAnterior, 4);
                $stockAnteriorDisplay = $pesoAnterior;
                $stockNuevoDisplay    = $pesoReal;
                $nuevaCantidad        = $cantidadAnterior;
                $nuevoPeso            = $pesoReal;
                $movCantidad          = 0;
                $movPeso              = $diferencia;
            } else {
                // Producto por unidades: ajustar cantidad_actual
                $diferencia = $cantidadReal - $cantidadAnterior;
                $stockAnteriorDisplay = $cantidadAnterior;
                $stockNuevoDisplay    = $cantidadReal;
                $nuevaCantidad        = $cantidadReal;
                $nuevoPeso            = $pesoAnterior;
                $movCantidad          = $diferencia;
                $movPeso              = 0;
            }

            if (abs($diferencia) < 0.0001) {
                $this->db->commit();
                return [
                    'tipo_ajuste'    => 'SIN_CAMBIO',
                    'diferencia'     => 0,
                    'stock_anterior' => $stockAnteriorDisplay,
                    'stock_actual'   => $stockNuevoDisplay
                ];
            }

            $tipoAjuste = $diferencia > 0 ? 'AJUSTE_POSITIVO' : 'AJUSTE_NEGATIVO';

            if ($stockActual) {
                $stmt = $this->db->prepare("
                    UPDATE stock_sucursal
                    SET cantidad_actual = ?,
                        peso_total = ?,
                        ultima_actualizacion = NOW()
                    WHERE id = ?
                ");
                $stmt->execute([$nuevaCantidad, $nuevoPeso, $stockActual['id']]);
                $idStockSucursal = $stockActual['id'];
            } else {
                $stmt = $this->db->prepare("
                    INSERT INTO stock_sucursal (id_sucursal, id_producto, cantidad_actual, peso_total, ultima_actualizacion)
                    VALUES (?, ?, ?, ?, NOW())
                ");
                $stmt->execute([$idSucursal, $idProducto, $nuevaCantidad, $nuevoPeso]);
                $idStockSucursal = $this->db->lastInsertId();
            }

            // Registrar movimiento incluyendo peso
            $stmt = $this->db->prepare("
                INSERT INTO stock_sucursal_movimientos
                (id_stock_sucursal, tipo_movimiento, cantidad, peso, referencia, usuario)
                VALUES (?, 'ajuste', ?, ?, ?, ?)
            ");
            $stmt->execute([
                $idStockSucursal,
                $movCantidad,
                $movPeso,
                $observaciones ?? $tipoAjuste,
                $idUsuario
            ]);

            $this->db->commit();

            return [
                'tipo_ajuste'    => $tipoAjuste,
                'diferencia'     => $diferencia,
                'stock_anterior' => $stockAnteriorDisplay,
                'stock_actual'   => $stockNuevoDisplay
            ];

        } catch (\Exception $e) {
            $this->db->rollBack();
            throw $e;
        }
    }
}
