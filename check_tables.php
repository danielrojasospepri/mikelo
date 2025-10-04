<?php
require_once 'api/comun.php';

try {
    $db = getDB();
    
    echo "Estructura de estados_items_movimientos:\n";
    $stmt = $db->query('DESCRIBE estados_items_movimientos');
    $campos = $stmt->fetchAll();
    foreach ($campos as $campo) {
        echo $campo['Field'] . ' - ' . $campo['Type'] . "\n";
    }
    
    echo "\nEstructura de estados:\n";
    $stmt = $db->query('DESCRIBE estados');
    $campos = $stmt->fetchAll();
    foreach ($campos as $campo) {
        echo $campo['Field'] . ' - ' . $campo['Type'] . "\n";
    }
    
    echo "\nDatos en tabla estados:\n";
    $stmt = $db->query('SELECT * FROM estados');
    $estados = $stmt->fetchAll();
    foreach ($estados as $estado) {
        echo "ID: {$estado['id']}, Nombre: {$estado['nombre']}\n";
    }
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>