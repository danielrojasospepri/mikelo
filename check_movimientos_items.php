<?php
require_once 'api/comun.php';

try {
    $db = getDB();
    
    echo "Estructura de movimientos_items:\n";
    $stmt = $db->query('DESCRIBE movimientos_items');
    $campos = $stmt->fetchAll();
    foreach ($campos as $campo) {
        echo $campo['Field'] . ' - ' . $campo['Type'] . "\n";
    }
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>