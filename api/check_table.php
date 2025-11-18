<?php
require 'comun.php';

try {
    $db = getDB();
    
    echo "=== Estructura de movimientos_items ===\n\n";
    $stmt = $db->query('DESCRIBE movimientos_items');
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($columns as $col) {
        echo sprintf("%-30s %-20s\n", $col['Field'], $col['Type']);
    }
    
    echo "\n=== Buscando columnas relacionadas con contenedor ===\n\n";
    foreach ($columns as $col) {
        if (stripos($col['Field'], 'contenedor') !== false || stripos($col['Field'], 'container') !== false) {
            echo "ENCONTRADA: {$col['Field']} ({$col['Type']})\n";
        }
    }
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
