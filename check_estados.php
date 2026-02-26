<?php
require_once 'api/comun.php';

$db = getDB();

echo "Estructura de estados:\n";
$stmt = $db->query('DESCRIBE estados');
$campos = $stmt->fetchAll();
foreach ($campos as $campo) {
    echo $campo['Field'] . ' - ' . $campo['Type'] . "\n";
}

echo "\nDatos de estados:\n";
$stmt = $db->query('SELECT * FROM estados LIMIT 10');
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
