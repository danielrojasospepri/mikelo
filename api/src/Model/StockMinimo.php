<?php
namespace App\Model;

/**
 * Modelo para gestión de Stock Mínimo por Sucursal
 */
class StockMinimo {
    private $db;

    public function __construct(\PDO $db) {
        $this->db = $db;
    }

    /**
     * Obtener configuración de stock mínimo de una sucursal
     */
    public function obtenerPorSucursal(int $idSucursal): array {
        $sql = "
            SELECT 
                sm.id_stock_minimo,
                sm.id_sucursal,
                sm.id_producto,
                sm.stock_minimo,
                sm.stock_optimo,
                sm.activo,
                p.codigo,
                p.descripcion AS producto,
                tp.nombre AS familia,
                COALESCE(ss.cantidad_actual, 0) as stock_actual
            FROM stock_minimo_sucursal sm
            INNER JOIN productos p ON sm.id_producto = p.id
            LEFT JOIN tipo_producto tp ON p.id_tipo_producto = tp.id
            LEFT JOIN stock_sucursal ss ON ss.id_sucursal = sm.id_sucursal 
                                        AND ss.id_producto = sm.id_producto
            WHERE sm.id_sucursal = :id_sucursal
              AND sm.activo = 1
            ORDER BY tp.nombre, p.descripcion
        ";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':id_sucursal' => $idSucursal]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Obtener productos faltantes (stock < mínimo) de una sucursal
     */
    public function obtenerFaltantes(int $idSucursal): array {
        $sql = "
            SELECT 
                sm.id_stock_minimo,
                sm.id_producto,
                sm.stock_minimo,
                sm.stock_optimo,
                p.codigo,
                p.descripcion AS producto,
                tp.nombre AS familia,
                COALESCE(ss.cantidad_actual, 0) as stock_actual,
                GREATEST(0, sm.stock_minimo - COALESCE(ss.cantidad_actual, 0)) as cantidad_faltante,
                COALESCE(sm.stock_optimo, sm.stock_minimo) - COALESCE(ss.cantidad_actual, 0) as cantidad_recomendada
            FROM stock_minimo_sucursal sm
            INNER JOIN productos p ON sm.id_producto = p.id
            LEFT JOIN tipo_producto tp ON p.id_tipo_producto = tp.id
            LEFT JOIN stock_sucursal ss ON ss.id_sucursal = sm.id_sucursal 
                                        AND ss.id_producto = sm.id_producto
            WHERE sm.id_sucursal = :id_sucursal
              AND sm.activo = 1
              AND COALESCE(ss.cantidad_actual, 0) < sm.stock_minimo
            ORDER BY (sm.stock_minimo - COALESCE(ss.cantidad_actual, 0)) DESC, tp.nombre, p.descripcion
        ";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':id_sucursal' => $idSucursal]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Configurar stock mínimo para un producto
     */
    public function configurar(int $idSucursal, int $idProducto, float $stockMinimo, ?float $stockOptimo = null, string $usuario = null): bool {
        $sql = "
            INSERT INTO stock_minimo_sucursal 
                (id_sucursal, id_producto, stock_minimo, stock_optimo, usuario_modificacion)
            VALUES 
                (:id_sucursal, :id_producto, :stock_minimo, :stock_optimo, :usuario)
            ON DUPLICATE KEY UPDATE
                stock_minimo = VALUES(stock_minimo),
                stock_optimo = VALUES(stock_optimo),
                activo = 1,
                usuario_modificacion = VALUES(usuario_modificacion)
        ";
        
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([
            ':id_sucursal' => $idSucursal,
            ':id_producto' => $idProducto,
            ':stock_minimo' => $stockMinimo,
            ':stock_optimo' => $stockOptimo,
            ':usuario' => $usuario
        ]);
    }

    /**
     * Configurar múltiples productos
     */
    public function configurarMultiple(int $idSucursal, array $productos, string $usuario = null): int {
        $this->db->beginTransaction();
        try {
            $count = 0;
            foreach ($productos as $p) {
                if ($this->configurar(
                    $idSucursal,
                    $p['id_producto'],
                    $p['stock_minimo'],
                    $p['stock_optimo'] ?? null,
                    $usuario
                )) {
                    $count++;
                }
            }
            $this->db->commit();
            return $count;
        } catch (\Exception $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    /**
     * Eliminar configuración de stock mínimo
     */
    public function eliminar(int $idStockMinimo): bool {
        $sql = "UPDATE stock_minimo_sucursal SET activo = 0 WHERE id_stock_minimo = :id";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([':id' => $idStockMinimo]);
    }

    /**
     * Obtener resumen de faltantes para todas las sucursales (admin/planta)
     */
    public function resumenFaltantesTodas(): array {
        $sql = "
            SELECT 
                u.id as id_sucursal,
                u.nombre as sucursal,
                COUNT(CASE WHEN COALESCE(ss.cantidad_actual, 0) < sm.stock_minimo THEN 1 END) as productos_faltantes,
                COUNT(sm.id_stock_minimo) as total_configurados
            FROM ubicaciones u
            LEFT JOIN stock_minimo_sucursal sm ON u.id = sm.id_sucursal AND sm.activo = 1
            LEFT JOIN stock_sucursal ss ON ss.id_sucursal = sm.id_sucursal AND ss.id_producto = sm.id_producto
            WHERE u.tipo_ubicacion = 'sucursal'
            GROUP BY u.id, u.nombre
            ORDER BY productos_faltantes DESC
        ";
        
        $stmt = $this->db->query($sql);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }
}
