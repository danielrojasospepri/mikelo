<?php
require __DIR__ . '/api/vendor/autoload.php';
require __DIR__ . '/api/comun.php';
$db = getDB();
$m = new App\Model\Usuario($db);
$r = $m->listar([]);
echo json_encode(array_slice($r, 0, 2), JSON_PRETTY_PRINT);
