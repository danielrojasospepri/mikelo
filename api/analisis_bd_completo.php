<?php
// api/analisis_bd_completo.php
require 'comun.php';

$db = getDB();

echo "\n=== ANÁLISIS EXHAUSTIVO DE BASE DE DATOS ===\n\n";

// 1. MOVIMIENTOS
echo "1. TABLA: MOVIMIENTOS\n";
echo str_repeat("-", 80) . "\n";
$result = $db->query("SELECT COUNT(*) as total FROM movimientos");
$row = $result->fetch();
echo "Total registros: {$row['total']}\n";

$result = $db->query("
    SELECT 
        COUNT(*) as cantidad,
        DATE(fechaAlta) as fecha
    FROM movimientos 
    GROUP BY DATE(fechaAlta)
    ORDER BY fecha DESC
    LIMIT 10
");
echo "\nÚltimos 10 días:\n";
foreach ($result->fetchAll() as $r) {
    echo "  {$r['fecha']}: {$r['cantidad']} movimientos\n";
}

// 2. MOVIMIENTOS_ITEMS
echo "\n2. TABLA: MOVIMIENTOS_ITEMS\n";
echo str_repeat("-", 80) . "\n";
$result = $db->query("SELECT COUNT(*) as total FROM movimientos_items");
$row = $result->fetch();
echo "Total items: {$row['total']}\n";

$result = $db->query("
    SELECT COUNT(*) as cantidad_referencias 
    FROM (SELECT DISTINCT id_productos FROM movimientos_items) as t
");
$row = $result->fetch();
echo "Referencias únicas: {$row['cantidad_referencias']}\n";

// 3. RELACIÓN MOVIMIENTOS vs ITEMS
echo "\n3. RELACIÓN MOVIMIENTOS → ITEMS\n";
echo str_repeat("-", 80) . "\n";
$result = $db->query("
    SELECT 
        m.id,
        COUNT(mi.id) as cantidad_items,
        SUM(mi.cnt) as total_unidades,
        m.fechaAlta
    FROM movimientos m
    LEFT JOIN movimientos_items mi ON m.id = mi.id_movimientos
    GROUP BY m.id
    ORDER BY m.fechaAlta DESC
    LIMIT 20
");
echo "Últimos 20 movimientos y sus items:\n";
foreach ($result->fetchAll() as $r) {
    echo "  ID {$r['id']}: {$r['cantidad_items']} items, {$r['total_unidades']} unidades | {$r['fechaAlta']}\n";
}

// 4. UBICACIONES
echo "\n4. TABLA: UBICACIONES\n";
echo str_repeat("-", 80) . "\n";
$result = $db->query("
    SELECT 
        id,
        nombre
    FROM ubicaciones
    ORDER BY id
");
echo "Ubicaciones en el sistema:\n";
foreach ($result->fetchAll() as $r) {
    echo "  ID {$r['id']}: {$r['nombre']}\n";
}

// 5. ANÁLISIS: ¿Hay movimientos con id_ubicacion_origen e id_ubicacion_destino?
echo "\n5. ANÁLISIS: ORIGEN → DESTINO\n";
echo str_repeat("-", 80) . "\n";
$result = $db->query("
    SELECT 
        m.id,
        m.fechaAlta,
        u_origen.nombre as origen,
        u_destino.nombre as destino,
        COUNT(mi.id) as items,
        SUM(mi.cnt) as cantidad
    FROM movimientos m
    LEFT JOIN ubicaciones u_origen ON m.id_ubicacion_origen = u_origen.id
    LEFT JOIN ubicaciones u_destino ON m.id_ubicacion_destino = u_destino.id
    LEFT JOIN movimientos_items mi ON m.id = mi.id_movimientos
    GROUP BY m.id
    ORDER BY m.fechaAlta DESC
    LIMIT 10
");
echo "Últimos 10 movimientos (origen → destino):\n";
foreach ($result->fetchAll() as $r) {
    $origen = $r['origen'] ?? 'NULL';
    $destino = $r['destino'] ?? 'NULL';
    echo "  {$r['fechaAlta']} | {$origen} → {$destino} | Items: {$r['items']} | Cantidad: {$r['cantidad']}\n";
}

// 6. ESTADOS_ITEMS_MOVIMIENTOS
echo "\n6. TABLA: ESTADOS_ITEMS_MOVIMIENTOS\n";
echo str_repeat("-", 80) . "\n";
$result = $db->query("
    SELECT 
        eim.id_estados,
        e.nombre as estado,
        COUNT(*) as cantidad
    FROM estados_items_movimientos eim
    LEFT JOIN estados e ON eim.id_estados = e.id
    GROUP BY eim.id_estados, e.nombre
    ORDER BY eim.id_estados
");
echo "Distribución de estados:\n";
foreach ($result->fetchAll() as $r) {
    echo "  Estado {$r['id_estados']}: {$r['estado']} - {$r['cantidad']} registros\n";
}

// 7. PRODUCTOS
echo "\n7. TABLA: PRODUCTOS\n";
echo str_repeat("-", 80) . "\n";
$result = $db->query("SELECT COUNT(*) as total FROM productos");
$row = $result->fetch();
echo "Total productos: {$row['total']}\n";

$result = $db->query("
    SELECT 
        COUNT(*) as cantidad
    FROM productos
");
$row = $result->fetch();
echo "Total referencias: {$row['cantidad']}\n";

// 8. CONTENEDORES
echo "\n8. TABLA: CONTENEDORES\n";
echo str_repeat("-", 80) . "\n";
$result = $db->query("SELECT COUNT(*) as total FROM contenedores");
$row = $result->fetch();
echo "Total tipos de contenedores: {$row['total']}\n";

$result = $db->query("
    SELECT 
        id,
        nombre,
        peso
    FROM contenedores
    ORDER BY id
");
foreach ($result->fetchAll() as $r) {
    echo "  ID {$r['id']}: {$r['nombre']} ({$r['peso']} kg)\n";
}

// 9. USUARIOS Y ROLES
echo "\n9. TABLA: USUARIOS\n";
echo str_repeat("-", 80) . "\n";
$result = $db->query("SELECT COUNT(*) as total FROM usuarios");
$row = $result->fetch();
echo "Total usuarios: {$row['total']}\n";

$result = $db->query("
    SELECT 
        id,
        usuario,
        email
    FROM usuarios
    LIMIT 5
");
echo "Primeros 5 usuarios:\n";
foreach ($result->fetchAll() as $r) {
    echo "  {$r['usuario']} ({$r['email']})\n";
}

// 10. ANÁLISIS: id_movimientos_items_origen
echo "\n10. ANÁLISIS: TRAZABILIDAD (id_movimientos_items_origen)\n";
echo str_repeat("-", 80) . "\n";
$result = $db->query("
    SELECT 
        COUNT(*) as total_items,
        SUM(CASE WHEN id_movimientos_items_origen IS NOT NULL THEN 1 ELSE 0 END) as con_origen,
        SUM(CASE WHEN id_movimientos_items_origen IS NULL THEN 1 ELSE 0 END) as sin_origen
    FROM movimientos_items
");
$row = $result->fetch();
echo "Total items: {$row['total_items']}\n";
echo "Con id_movimientos_items_origen (trazables): {$row['con_origen']}\n";
echo "Sin id_movimientos_items_origen (origen directo): {$row['sin_origen']}\n";

// 11. MOVIMIENTOS_CAMBIOS
echo "\n11. TABLA: MOVIMIENTOS_CAMBIOS (AUDITORÍA)\n";
echo str_repeat("-", 80) . "\n";
$result = $db->query("SELECT COUNT(*) as total FROM movimientos_cambios");
$row = $result->fetch();
echo "Total registros de auditoría: {$row['total']}\n";

// 12. ESTADÍSTICAS FINALES
echo "\n12. ESTADÍSTICAS FINALES\n";
echo str_repeat("-", 80) . "\n";
$result = $db->query("
    SELECT 
        m.id_ubicacion_destino,
        u.nombre,
        COUNT(DISTINCT m.id) as movimientos,
        COUNT(DISTINCT mi.id) as items,
        SUM(mi.cnt) as unidades
    FROM movimientos m
    LEFT JOIN ubicaciones u ON m.id_ubicacion_destino = u.id
    LEFT JOIN movimientos_items mi ON m.id = mi.id_movimientos
    GROUP BY m.id_ubicacion_destino, u.nombre
    ORDER BY unidades DESC
");
echo "Movimientos por ubicación destino:\n";
foreach ($result->fetchAll() as $r) {
    $nombre = $r['nombre'] ?? 'SIN DESTINO';
    echo "  {$nombre}: {$r['movimientos']} movimientos, {$r['items']} items, {$r['unidades']} unidades\n";
}

echo "\n" . str_repeat("=", 80) . "\n";
echo "FIN DEL ANÁLISIS\n";
echo str_repeat("=", 80) . "\n";
?>
