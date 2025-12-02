#!/usr/bin/env php
<?php
/**
 * INVESTIGACIÓN DEL BUG: ¿Por qué producto 1101 aparece si está agotado?
 */

ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/api/comun.php';

$db = getDB();

echo "\n╔═══════════════════════════════════════════════════════════════╗\n";
echo "║  INVESTIGACIÓN: Producto 1101 (FRUTILLA Y NARANJA)            ║\n";
echo "╚═══════════════════════════════════════════════════════════════╝\n\n";

// Paso 1: Obtener todos los registros de este producto
echo "PASO 1: Buscar todos los registros de producto 1101\n";
echo "───────────────────────────────────────────────────────────────\n";

$stmt = $db->prepare("
    SELECT 
        mi.id,
        mi.id_productos,
        mi.cnt,
        mi.id_movimientos_items_origen,
        p.codigo,
        p.descripcion
    FROM movimientos_items mi
    JOIN productos p ON p.id = mi.id_productos
    WHERE p.codigo = '1101'
    ORDER BY mi.id
");
$stmt->execute();
$registros = $stmt->fetchAll(\PDO::FETCH_ASSOC);

echo "Registros encontrados: " . count($registros) . "\n\n";

foreach ($registros as $reg) {
    $origen = $reg['id_movimientos_items_origen'] ? "referencia a {$reg['id_movimientos_items_origen']}" : "ORIGINAL (alta)";
    echo "  • ID: {$reg['id']}\n";
    echo "    Cantidad: {$reg['cnt']}\n";
    echo "    Origen: $origen\n\n";
}

// Paso 2: Para cada registro original, calcular disponibilidad
echo "\nPASO 2: Calcular disponibilidad para cada registro original\n";
echo "───────────────────────────────────────────────────────────────\n";

$stmt = $db->prepare("
    SELECT 
        mi.id as id_original,
        mi.cnt,
        (
            SELECT IFNULL(SUM(mi2.cnt), 0)
            FROM movimientos_items mi2
            WHERE mi2.id_movimientos_items_origen = mi.id
        ) as total_enviado,
        (mi.cnt - IFNULL((
            SELECT IFNULL(SUM(mi2.cnt), 0)
            FROM movimientos_items mi2
            WHERE mi2.id_movimientos_items_origen = mi.id
        ), 0)) as disponible
    FROM movimientos_items mi
    WHERE mi.id_movimientos_items_origen IS NULL
      AND mi.id_productos = (SELECT id FROM productos WHERE codigo = '1101')
    ORDER BY mi.id
");
$stmt->execute();
$disponibilidades = $stmt->fetchAll(\PDO::FETCH_ASSOC);

foreach ($disponibilidades as $disp) {
    echo "  ID Original: {$disp['id_original']}\n";
    echo "    Cantidad Alta: {$disp['cnt']}\n";
    echo "    Total Enviado: {$disp['total_enviado']}\n";
    echo "    Disponible: {$disp['disponible']}\n";
    echo "    ¿Debería aparecer? " . ($disp['disponible'] > 0 ? "✅ SÍ" : "❌ NO") . "\n\n";
}

// Paso 3: Validar el WHERE que debería filtrar
echo "\nPASO 3: Aplicar WHERE (debería filtrar agotados)\n";
echo "───────────────────────────────────────────────────────────────\n";

$stmt = $db->prepare("
    SELECT 
        mi.id as id_original,
        mi.cnt,
        (
            SELECT IFNULL(SUM(mi2.cnt), 0)
            FROM movimientos_items mi2
            WHERE mi2.id_movimientos_items_origen = mi.id
        ) as total_enviado,
        (mi.cnt - IFNULL((
            SELECT IFNULL(SUM(mi2.cnt), 0)
            FROM movimientos_items mi2
            WHERE mi2.id_movimientos_items_origen = mi.id
        ), 0)) as disponible
    FROM movimientos_items mi
    WHERE mi.id_movimientos_items_origen IS NULL
      AND mi.id_productos = (SELECT id FROM productos WHERE codigo = '1101')
      AND mi.cnt > IFNULL((
          SELECT IFNULL(SUM(mi2.cnt), 0)
          FROM movimientos_items mi2
          WHERE mi2.id_movimientos_items_origen = mi.id
      ), 0)
    ORDER BY mi.id
");
$stmt->execute();
$filtrados = $stmt->fetchAll(\PDO::FETCH_ASSOC);

echo "Registros después de WHERE (solo con disponibilidad > 0):\n";
if (empty($filtrados)) {
    echo "  ❌ NINGUNO (correcto)\n";
} else {
    echo "  ⚠️  " . count($filtrados) . " (debería ser 0!)\n";
    foreach ($filtrados as $f) {
        echo "    - ID: {$f['id_original']}, disponible: {$f['disponible']}\n";
    }
}

// Paso 4: Ver qué devuelve la API
echo "\n\nPASO 4: Resultado actual de obtenerProductosDisponibles()\n";
echo "───────────────────────────────────────────────────────────────\n";

require_once __DIR__ . '/api/src/Model/Envio.php';
$envio = new \App\Model\Envio($db);
$resultado = $envio->obtenerProductosDisponibles(['codigo' => '1101']);

echo "Registros retornados: " . count($resultado) . "\n";
if (!empty($resultado)) {
    foreach ($resultado as $r) {
        echo "  • ID mov_item: {$r['id_movimiento_item']}\n";
        echo "    Cantidad: {$r['cnt']}\n";
        echo "    Disponible: {$r['cnt_disponible']}\n";
        echo "    Estado: {$r['estado_actual']}\n\n";
    }
}

// Conclusión
echo "\n╔═══════════════════════════════════════════════════════════════╗\n";
echo "║  CONCLUSIÓN                                                    ║\n";
echo "╚═══════════════════════════════════════════════════════════════╝\n\n";

if (count($disponibilidades) > count($filtrados)) {
    echo "⚠️  HAY MÚLTIPLES REGISTROS DEL MISMO PRODUCTO\n\n";
    echo "Explicación:\n";
    echo "  El producto 1101 fue creado " . count($disponibilidades) . " veces\n";
    echo "  En la búsqueda:\n";
    foreach ($disponibilidades as $d) {
        if ($d['disponible'] > 0) {
            echo "    → Registro {$d['id_original']}: disponible={$d['disponible']} ✅ APARECE\n";
        } else {
            echo "    → Registro {$d['id_original']}: disponible={$d['disponible']} ❌ NO aparece\n";
        }
    }
    echo "\n  ESTO ES CORRECTO. El producto aparece porque hay registros con stock\n";
} else {
    echo "🚨 BUG CONFIRMADO\n\n";
    echo "El producto aparece aunque está 100% agotado.\n";
    echo "Hay un problema en el cálculo de disponibilidad.\n";
}

echo "\n";
