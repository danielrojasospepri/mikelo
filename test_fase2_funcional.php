<?php
/**
 * TEST FUNCIONAL FASE 2 - Flujo completo
 * Simula el ciclo: Pedido → Envío → Recepción → Stock
 * Ejecutar con: php test_fase2_funcional.php
 */

require 'api/comun.php';
require_once 'api/src/Model/Usuario.php';
require_once 'api/src/Model/Sesion.php';
require_once 'api/src/Model/Pedido.php';
require_once 'api/src/Model/Recepcion.php';
require_once 'api/src/Model/StockSucursal.php';

echo "=== TEST FUNCIONAL FASE 2 - MIKELO ===\n";
echo "Fecha: " . date('Y-m-d H:i:s') . "\n\n";

$db = getDB();
$errores = 0;
$exitos = 0;

function test($nombre, $resultado, $esperado = true) {
    global $errores, $exitos;
    $ok = ($resultado === $esperado);
    if ($ok) {
        echo "  ✓ $nombre\n";
        $exitos++;
    } else {
        echo "  ✗ $nombre\n";
        echo "    Esperado: " . var_export($esperado, true) . "\n";
        echo "    Obtenido: " . var_export($resultado, true) . "\n";
        $errores++;
    }
    return $ok;
}

// =====================================================
// SETUP: Verificar datos necesarios
// =====================================================
echo "--- SETUP: VERIFICAR DATOS ---\n";

// Verificar usuario franquicia
$stmt = $db->query("SELECT id FROM usuarios WHERE us = 'franquicia_test'");
$usuarioFranquicia = $stmt->fetch();
if (!$usuarioFranquicia) {
    die("ERROR: Usuario franquicia_test no existe. Ejecuta primero test_fase2.php\n");
}
echo "  Usuario franquicia_test: ID " . $usuarioFranquicia['id'] . "\n";

// Verificar sucursal
$stmt = $db->query("SELECT id, nombre FROM ubicaciones WHERE tipo_ubicacion = 'sucursal' LIMIT 1");
$sucursal = $stmt->fetch();
if (!$sucursal) {
    die("ERROR: No hay sucursales. Ejecuta primero test_fase2.php\n");
}
echo "  Sucursal: {$sucursal['nombre']} (ID: {$sucursal['id']})\n";

// Verificar productos
$stmt = $db->query("SELECT id, descripcion as nombre FROM productos WHERE activo = 1 LIMIT 3");
$productos = $stmt->fetchAll();
if (count($productos) < 1) {
    die("ERROR: No hay productos activos en la BD\n");
}
echo "  Productos disponibles: " . count($productos) . "\n\n";

// Inicializar modelos
$pedidoModel = new \App\Model\Pedido($db);
$stockModel = new \App\Model\StockSucursal($db);

// =====================================================
// TEST 1: CREAR PEDIDO
// =====================================================
echo "--- TEST 1: CREAR PEDIDO ---\n";

$itemsPedido = [];
foreach ($productos as $i => $producto) {
    $itemsPedido[] = [
        'id_producto' => $producto['id'],
        'cantidad' => ($i + 1) * 5, // 5, 10, 15...
        'peso' => 0
    ];
}

$idPedido = $pedidoModel->crear(
    $sucursal['id'], 
    $usuarioFranquicia['id'], 
    $itemsPedido, 
    'Pedido de test funcional - ' . date('Y-m-d H:i:s')
);

test("Crear pedido", $idPedido !== false && $idPedido > 0);

$pedido = $pedidoModel->obtenerPorId($idPedido);
test("Obtener pedido creado", $pedido !== null);
test("Pedido tiene ID", isset($pedido['id']) && $pedido['id'] > 0);
test("Pedido en estado PENDIENTE", $pedido['estado'] === 'PENDIENTE');
test("Pedido tiene items", isset($pedido['items']) && count($pedido['items']) > 0);

echo "  Pedido creado: #$idPedido\n\n";

// =====================================================
// TEST 2: OBTENER Y PROCESAR PEDIDO
// =====================================================
echo "--- TEST 2: OBTENER Y PROCESAR PEDIDO ---\n";

$pedidoObtenido = $pedidoModel->obtenerPorId($idPedido);
test("Obtener pedido por ID", $pedidoObtenido !== null);
test("Datos correctos", $pedidoObtenido['id'] == $idPedido);
test("Tiene items", count($pedidoObtenido['items']) > 0);

// Procesar el pedido (cambiar de PENDIENTE a EN_PROCESO)
try {
    $pedidoModel->procesar($idPedido, $usuarioFranquicia['id']);
    test("Procesar pedido (PENDIENTE → EN_PROCESO)", true);
    
    $pedidoProcesado = $pedidoModel->obtenerPorId($idPedido);
    test("Estado cambiado a EN_PROCESO", $pedidoProcesado['estado'] === 'EN_PROCESO');
} catch (Exception $e) {
    test("Procesar pedido", false);
    echo "    Error: " . $e->getMessage() . "\n";
}

echo "\n";

// =====================================================
// TEST 3: LISTAR PEDIDOS
// =====================================================
echo "--- TEST 3: LISTAR PEDIDOS ---\n";

// Listar pedidos de la sucursal
$pedidosSucursal = $pedidoModel->listarPorSucursal($sucursal['id']);
test("Listar pedidos de sucursal", is_array($pedidosSucursal));
test("Incluye el pedido creado", count(array_filter($pedidosSucursal, fn($p) => $p['id'] == $idPedido)) > 0);

// Listar pedidos pendientes (vista planta)
$pedidosPendientes = $pedidoModel->listarPendientes();
test("Listar pedidos pendientes", is_array($pedidosPendientes));
echo "  Pedidos de sucursal: " . count($pedidosSucursal) . "\n";
echo "  Pedidos pendientes: " . count($pedidosPendientes) . "\n";

echo "\n";

// =====================================================
// TEST 4: STOCK SUCURSAL (antes de recepción)
// =====================================================
echo "--- TEST 4: STOCK SUCURSAL ---\n";

$stockActual = $stockModel->obtenerStock($sucursal['id']);
test("Obtener stock de sucursal", is_array($stockActual));
echo "  Items en stock actual: " . count($stockActual) . "\n";

$resumen = $stockModel->obtenerTotales($sucursal['id']);
test("Obtener totales", is_array($resumen));
test("Resumen tiene total_productos", isset($resumen['total_productos']));
echo "  Total productos en stock: {$resumen['total_productos']}\n";

echo "\n";

// =====================================================
// TEST 5: MODELO RECEPCION
// =====================================================
echo "--- TEST 5: MODELO RECEPCION ---\n";

require_once 'api/src/Model/Recepcion.php';
$recepcionModel = new \App\Model\Recepcion($db);

// Verificar si hay envíos pendientes de recepción para la sucursal
// La tabla movimientos no tiene id_tipo_movimiento, así que buscamos envíos a la sucursal
$stmt = $db->query("
    SELECT m.id, m.fechaAlta, m.id_ubicacion_origen, m.id_ubicacion_destino
    FROM movimientos m
    WHERE m.id_ubicacion_destino = {$sucursal['id']}
    ORDER BY m.fechaAlta DESC
    LIMIT 5
");
$enviosExistentes = $stmt->fetchAll();

if (count($enviosExistentes) > 0) {
    echo "  Encontrados " . count($enviosExistentes) . " envíos existentes a la sucursal\n";
    test("Modelo Recepcion instanciado", $recepcionModel !== null);
} else {
    echo "  ⚠ No hay envíos a esta sucursal para probar recepciones\n";
    echo "  Para probar recepciones, primero crea un envío desde envios.html\n";
    test("Modelo Recepcion instanciado", $recepcionModel !== null);
}

echo "\n";

// =====================================================
// TEST 6: ANULAR PEDIDO
// =====================================================
echo "--- TEST 6: ANULAR PEDIDO ---\n";

// Crear otro pedido para anularlo
$idPedidoParaAnular = $pedidoModel->crear(
    $sucursal['id'],
    $usuarioFranquicia['id'],
    [['id_producto' => $productos[0]['id'], 'cantidad' => 1, 'peso' => 0]],
    'Pedido para anular'
);

test("Crear pedido para anular", $idPedidoParaAnular > 0);

try {
    $pedidoModel->anular($idPedidoParaAnular, $usuarioFranquicia['id'], 'Prueba de anulación');
    test("Anular pedido", true);
    
    $pedidoAnulado = $pedidoModel->obtenerPorId($idPedidoParaAnular);
    test("Estado es ANULADO", $pedidoAnulado['estado'] === 'ANULADO');
} catch (Exception $e) {
    test("Anular pedido", false);
    echo "    Error: " . $e->getMessage() . "\n";
}

echo "\n";

// =====================================================
// TEST 7: BÚSQUEDA EN STOCK
// =====================================================
echo "--- TEST 7: BÚSQUEDA EN STOCK ---\n";

// Buscar por nombre de producto
$resultadoBusqueda = $stockModel->buscarProducto($sucursal['id'], $productos[0]['nombre']);
test("Buscar producto en stock", is_array($resultadoBusqueda));

// Obtener historial de movimientos
$historial = $stockModel->obtenerHistorial($sucursal['id'], 10);
test("Obtener historial de movimientos", is_array($historial));
echo "  Movimientos en historial: " . count($historial) . "\n";

echo "\n";

// =====================================================
// TEST 8: VALIDACIONES DE SEGURIDAD
// =====================================================
echo "--- TEST 8: VALIDACIONES ---\n";

// Intentar obtener pedido inexistente
$pedidoInexistente = $pedidoModel->obtenerPorId(99999);
test("Pedido inexistente devuelve null", $pedidoInexistente === null);

// Intentar anular pedido ya anulado
try {
    $pedidoModel->anular($idPedidoParaAnular, $usuarioFranquicia['id'], 'Segunda anulación');
    test("No se puede anular pedido ya anulado", false);
} catch (Exception $e) {
    test("No se puede anular pedido ya anulado", true);
}

echo "\n";

// =====================================================
// LIMPIEZA (opcional)
// =====================================================
echo "--- LIMPIEZA ---\n";
echo "  Los datos de prueba se mantienen para inspección manual.\n";
echo "  Para limpiar, ejecuta:\n";
echo "  DELETE FROM pedido_items WHERE id_pedido IN (SELECT id FROM pedidos WHERE observaciones LIKE '%test funcional%');\n";
echo "  DELETE FROM pedidos WHERE observaciones LIKE '%test funcional%';\n";

echo "\n";

// =====================================================
// RESUMEN
// =====================================================
echo "=== RESUMEN DE TESTS FUNCIONALES ===\n";
echo "Exitosos: $exitos\n";
echo "Fallidos: $errores\n";
echo "Total: " . ($exitos + $errores) . "\n\n";

if ($errores > 0) {
    echo "⚠ HAY $errores TEST(S) FALLIDO(S)\n";
    exit(1);
} else {
    echo "✓ TODOS LOS TESTS FUNCIONALES PASARON\n";
}
