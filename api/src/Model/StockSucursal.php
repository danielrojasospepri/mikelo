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
                f.nombre as familia,
                ss.cantidad_actual as cantidad,
                ss.peso_total as peso,
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
     * @param int $idSucursal
     * @param int $idProducto
     * @param float $cantidad - Cantidad a dar de baja (positivo)
     * @param string $tipoBaja - BAJA_VENTA, BAJA_MERMA, AJUSTE_NEGATIVO
     * @param int $idUsuario
     * @param string $observaciones
     * @return array
     */
    public function registrarBaja($idSucursal, $idProducto, $cantidad, $tipoBaja, $idUsuario, $observaciones = null) {
        try {
            $this->db->beginTransaction();

            $cantidad = abs((float)$cantidad); // Asegurar positivo

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
            
            if ($cantidad > $cantidadAnterior) {
                throw new \Exception("No hay suficiente stock. Disponible: {$cantidadAnterior}");
            }

            $cantidadPosterior = $cantidadAnterior - $cantidad;

            // Actualizar stock
            $stmt = $this->db->prepare("
                UPDATE stock_sucursal 
                SET cantidad_actual = ?,
                    fecha_ultima_salida = NOW(),
                    ultima_actualizacion = NOW()
                WHERE id = ?
            ");
            $stmt->execute([$cantidadPosterior, $stockActual['id']]);

            // Registrar movimiento
            $stmt = $this->db->prepare("
                INSERT INTO stock_sucursal_movimientos 
                (id_sucursal, id_producto, tipo_movimiento, cantidad, cantidad_anterior, cantidad_posterior, id_usuario, observaciones)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $idSucursal,
                $idProducto,
                $tipoBaja,
                -$cantidad, // Negativo para baja
                $cantidadAnterior,
                $cantidadPosterior,
                $idUsuario,
                $observaciones
            ]);
            $movimientoId = $this->db->lastInsertId();

            $this->db->commit();

            return [
                'movimiento_id' => $movimientoId,
                'stock_anterior' => $cantidadAnterior,
                'stock_actual' => $cantidadPosterior
            ];

        } catch (\Exception $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    /**
     * Registrar ajuste de stock (inventario físico)
     * Ajusta el stock al valor real contado
     * @param int $idSucursal
     * @param int $idProducto
     * @param float $cantidadReal - Cantidad real contada en inventario
     * @param int $idUsuario
     * @param string $observaciones
     * @return array
     */
    public function registrarAjuste($idSucursal, $idProducto, $cantidadReal, $idUsuario, $observaciones = null) {
        try {
            $this->db->beginTransaction();

            $cantidadReal = (float)$cantidadReal;

            // Obtener stock actual
            $stmt = $this->db->prepare("
                SELECT id, cantidad_actual 
                FROM stock_sucursal 
                WHERE id_sucursal = ? AND id_producto = ?
                FOR UPDATE
            ");
            $stmt->execute([$idSucursal, $idProducto]);
            $stockActual = $stmt->fetch(\PDO::FETCH_ASSOC);

            $cantidadAnterior = $stockActual ? (float)$stockActual['cantidad_actual'] : 0;
            $diferencia = $cantidadReal - $cantidadAnterior;

            if ($diferencia == 0) {
                $this->db->commit();
                return [
                    'tipo_ajuste' => 'SIN_CAMBIO',
                    'diferencia' => 0,
                    'stock_anterior' => $cantidadAnterior,
                    'stock_actual' => $cantidadReal
                ];
            }

            $tipoAjuste = $diferencia > 0 ? 'AJUSTE_POSITIVO' : 'AJUSTE_NEGATIVO';

            if ($stockActual) {
                // Actualizar existente
                $stmt = $this->db->prepare("
                    UPDATE stock_sucursal 
                    SET cantidad_actual = ?,
                        ultima_actualizacion = NOW()
                    WHERE id = ?
                ");
                $stmt->execute([$cantidadReal, $stockActual['id']]);
            } else {
                // Crear registro si no existe
                $stmt = $this->db->prepare("
                    INSERT INTO stock_sucursal (id_sucursal, id_producto, cantidad_actual, ultima_actualizacion)
                    VALUES (?, ?, ?, NOW())
                ");
                $stmt->execute([$idSucursal, $idProducto, $cantidadReal]);
            }

            // Registrar movimiento
            $stmt = $this->db->prepare("
                INSERT INTO stock_sucursal_movimientos 
                (id_sucursal, id_producto, tipo_movimiento, cantidad, cantidad_anterior, cantidad_posterior, id_usuario, observaciones)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $idSucursal,
                $idProducto,
                $tipoAjuste,
                $diferencia,
                $cantidadAnterior,
                $cantidadReal,
                $idUsuario,
                $observaciones
            ]);

            $this->db->commit();

            return [
                'tipo_ajuste' => $tipoAjuste,
                'diferencia' => $diferencia,
                'stock_anterior' => $cantidadAnterior,
                'stock_actual' => $cantidadReal
            ];

        } catch (\Exception $e) {
            $this->db->rollBack();
            throw $e;
        }
    }
}
