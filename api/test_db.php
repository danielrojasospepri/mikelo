<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require __DIR__ . '/comun.php';

try {
    $db = getDB();
    echo "Conexión exitosa a la base de datos\n";
    
    // Probar una consulta simple
    $stmt = $db->query("SHOW TABLES");
    $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
    echo "Tablas en la base de datos:\n";
    print_r($tables);
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}