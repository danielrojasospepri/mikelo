<?php
require_once __DIR__ . '/api/comun.php';
$db = getDB();
echo "=== PEDIDOS ===\n";
$r = $db->query('DESCRIBE pedidos');
foreach($r->fetchAll(PDO::FETCH_ASSOC) as $c) {
    echo $c['Field'] . ' - ' . $c['Type'] . "\n";
}
echo "\n=== PEDIDO_ITEMS ===\n";
$r = $db->query('DESCRIBE pedido_items');
foreach($r->fetchAll(PDO::FETCH_ASSOC) as $c) {
    echo $c['Field'] . ' - ' . $c['Type'] . "\n";
}
