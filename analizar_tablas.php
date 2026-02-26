<?php
require 'api/comun.php';
$db = getDB();

echo "=== ANALIZANDO TABLAS EXISTENTES ===\n\n";

// Analizar ROLES
echo "--- TABLA ROLES ---\n";
$stmt = $db->query('DESCRIBE roles');
$columnas = $stmt->fetchAll(PDO::FETCH_ASSOC);
foreach ($columnas as $col) {
    echo "  {$col['Field']} - {$col['Type']} - {$col['Null']} - {$col['Key']}\n";
}
echo "\nDatos actuales:\n";
$stmt = $db->query('SELECT * FROM roles');
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));

// Analizar USUARIOS
echo "\n--- TABLA USUARIOS ---\n";
$stmt = $db->query('DESCRIBE usuarios');
$columnas = $stmt->fetchAll(PDO::FETCH_ASSOC);
foreach ($columnas as $col) {
    echo "  {$col['Field']} - {$col['Type']} - {$col['Null']} - {$col['Key']}\n";
}
echo "\nDatos actuales:\n";
$stmt = $db->query('SELECT id, nombre, us, id_roles FROM usuarios');
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));

echo "\n=== FIN ANÁLISIS ===\n";
