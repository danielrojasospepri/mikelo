<?php
require_once 'comun.php';

try {
    $db = getDB();
    
    echo "Estados disponibles:\n";
    $stmt = $db->query("SELECT * FROM estados ORDER BY id");
    $estados = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($estados as $estado) {
        echo "ID: " . $estado['id'] . " | Nombre: " . $estado['nombre'] . "\n";
    }
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>