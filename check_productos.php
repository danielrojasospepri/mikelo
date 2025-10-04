<?php
require_once 'api/comun.php';

try {
    $db = getDB();
    
    echo "Estructura de productos:\n";
    $stmt = $db->query('DESCRIBE productos');
    $campos = $stmt->fetchAll();
    foreach ($campos as $campo) {
        echo $campo['Field'] . ' - ' . $campo['Type'] . "\n";
    }
    
    echo "\nPrimeros 3 productos:\n";
    $stmt = $db->query('SELECT id, codigo, descripcion FROM productos LIMIT 3');
    $productos = $stmt->fetchAll();
    foreach ($productos as $producto) {
        echo "ID: {$producto['id']}, Código: {$producto['codigo']}, Descripción: {$producto['descripcion']}\n";
    }
    
    echo "\nPrimeros 3 movimientos_items:\n";
    $stmt = $db->query('SELECT id, id_productos, cnt, cnt_peso FROM movimientos_items LIMIT 3');
    $items = $stmt->fetchAll();
    foreach ($items as $item) {
        echo "ID: {$item['id']}, ID_Productos: {$item['id_productos']}, Cnt: {$item['cnt']}, Peso: {$item['cnt_peso']}\n";
    }
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>