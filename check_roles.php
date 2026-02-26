<?php
require __DIR__ . '/api/comun.php';

$db = getDB();

echo "=== Verificando tablas ===\n";

// Ver si existe tabla roles
$stmt = $db->query("SHOW TABLES LIKE 'roles'");
$roles = $stmt->fetch();
echo "Tabla roles: " . ($roles ? "SI existe" : "NO existe") . "\n";

// Ver si existe tabla usuarios
$stmt = $db->query("SHOW TABLES LIKE 'usuarios'");
$usuarios = $stmt->fetch();
echo "Tabla usuarios: " . ($usuarios ? "SI existe" : "NO existe") . "\n";

if ($roles) {
    echo "\n=== Estructura de roles ===\n";
    $stmt = $db->query("DESCRIBE roles");
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $col) {
        echo $col['Field'] . " - " . $col['Type'] . "\n";
    }
    
    echo "\n=== Datos de roles ===\n";
    $stmt = $db->query("SELECT * FROM roles");
    $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
    print_r($data);
}

if ($usuarios) {
    echo "\n=== Estructura de usuarios ===\n";
    $stmt = $db->query("DESCRIBE usuarios");
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $col) {
        echo $col['Field'] . " - " . $col['Type'] . "\n";
    }
    
    echo "\n=== Usuarios (primeros 3) ===\n";
    $stmt = $db->query("SELECT id, nombre, us, id_roles, activo FROM usuarios LIMIT 3");
    $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
    print_r($data);
}
