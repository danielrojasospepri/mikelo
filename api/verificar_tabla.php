<?php
require_once 'comun.php';

try {
    $db = getDB();
    
    echo "Estructura de la tabla estados_items_movimientos:\n";
    $stmt = $db->query("DESCRIBE estados_items_movimientos");
    $columnas = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($columnas as $columna) {
        echo "Campo: " . $columna['Field'] . " | Tipo: " . $columna['Type'] . " | Null: " . $columna['Null'] . "\n";
    }
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>