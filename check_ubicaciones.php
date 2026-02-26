<?php
require 'api/comun.php';

$db = getDB();

echo "=== Estructura de ubicaciones ===\n\n";
$stmt = $db->query("DESCRIBE ubicaciones");
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));

echo "\n=== Datos de ubicaciones ===\n\n";
$stmt = $db->query("SELECT * FROM ubicaciones LIMIT 3");
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
