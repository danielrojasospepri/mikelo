<?php
require 'api/comun.php';

$db = getDB();

echo "=== Verificando usuarios ===\n\n";

$stmt = $db->query("SELECT id, us, ps, activo, nombre FROM usuarios");
$usuarios = $stmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($usuarios as $u) {
    echo "ID: {$u['id']}, Usuario: {$u['us']}, Nombre: {$u['nombre']}, Activo: {$u['activo']}\n";
    echo "  Password almacenado: " . substr($u['ps'], 0, 40) . "...\n";
    echo "  ¿Es hash? " . (preg_match('/^\$2[ayb]\$.{56}$/', $u['ps']) ? 'SI' : 'NO (texto plano)') . "\n";
    echo "  password_verify('test123'): " . (password_verify('test123', $u['ps']) ? 'VÁLIDO' : 'INVÁLIDO') . "\n";
    echo "\n";
}
