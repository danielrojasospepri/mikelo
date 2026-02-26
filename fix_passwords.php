<?php
require 'api/comun.php';

$db = getDB();

echo "=== Actualizando password de admin ===\n\n";

$hash = password_hash('admin123', PASSWORD_DEFAULT);
$stmt = $db->prepare('UPDATE usuarios SET ps = ? WHERE us = ?');
$stmt->execute([$hash, 'admin']);

echo "Password de 'admin' actualizado a 'admin123'\n";

// También franquicia_test
$stmt->execute([$hash, 'franquicia_test']);
echo "Password de 'franquicia_test' actualizado a 'admin123'\n";
