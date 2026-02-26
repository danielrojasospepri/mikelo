<?php
// Test de la consulta de productos disponibles
require_once 'api/comun.php';

try {
    $pdo = getDB();
    
    echo "=== Test: Productos Disponibles para Franquicias ===\n\n";
    
    // Consulta igual a la del controlador
    $stmt = $pdo->prepare("
        SELECT 
            p.id,
            p.codigo,
            p.descripcion as nombre,
            p.id_tipo_producto as id_tipo,
            tp.nombre as tipo_producto,
            p.disponible_franquicias,
            COALESCE(SUM(
                mi.cnt - IFNULL((
                    SELECT SUM(mi2.cnt)
                    FROM movimientos_items mi2
                    WHERE mi2.id_movimientos_items_origen = mi.id
                ), 0)
            ), 0) as stock_disponible
        FROM productos p
        LEFT JOIN tipo_producto tp ON p.id_tipo_producto = tp.id
        LEFT JOIN movimientos_items mi ON mi.id_productos = p.id 
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
        WHERE p.disponible_franquicias = 1
          AND p.activo = 1
        GROUP BY p.id, p.codigo, p.descripcion, p.id_tipo_producto, tp.nombre, p.disponible_franquicias
        HAVING stock_disponible > 0
        ORDER BY p.descripcion
    ");
    
    $stmt->execute();
    $productos = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "Productos encontrados: " . count($productos) . "\n\n";
    
    if (count($productos) > 0) {
        echo "Primeros 10 productos:\n";
        echo str_repeat("-", 70) . "\n";
        printf("%-10s %-40s %s\n", "Codigo", "Producto", "Stock");
        echo str_repeat("-", 70) . "\n";
        $i = 0;
        foreach ($productos as $p) {
            if ($i++ >= 10) break;
            printf("%-10s %-40s %s\n", $p['codigo'], substr($p['nombre'], 0, 40), $p['stock_disponible']);
        }
    } else {
        echo "No hay productos disponibles.\n";
    }
    
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
