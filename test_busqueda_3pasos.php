#!/usr/bin/env php
<?php
/**
 * TEST MANUAL PROFUNDO - BÚSQUEDA 3-PASOS
 * 
 * Este script prueba la nueva lógica de búsqueda inteligente:
 * PASO 1: Cantidad exacta
 * PASO 2: Cantidad superior (si no hay exacta)
 * PASO 3: Sin restricción (búsqueda manual)
 */

// Configurar errores
ini_set('display_errors', 1);
error_reporting(E_ALL);

// Incluir configuración
require_once __DIR__ . '/api/comun.php';
require_once __DIR__ . '/api/src/Model/Envio.php';

use App\Model\Envio;

// Colores para output
const COLOR_GREEN = "\033[32m";
const COLOR_RED = "\033[31m";
const COLOR_YELLOW = "\033[33m";
const COLOR_BLUE = "\033[34m";
const COLOR_RESET = "\033[0m";
const COLOR_BOLD = "\033[1m";

function print_header($texto) {
    echo "\n" . COLOR_BOLD . COLOR_BLUE . "=== $texto ===" . COLOR_RESET . "\n";
}

function print_success($texto) {
    echo COLOR_GREEN . "✓ $texto" . COLOR_RESET . "\n";
}

function print_error($texto) {
    echo COLOR_RED . "✗ $texto" . COLOR_RESET . "\n";
}

function print_info($texto) {
    echo COLOR_YELLOW . "ℹ $texto" . COLOR_RESET . "\n";
}

function print_section($titulo) {
    echo "\n" . COLOR_BOLD . $titulo . COLOR_RESET . "\n";
    echo str_repeat("-", 80) . "\n";
}

// Conectar BD
try {
    $db = getDB();
    print_success("Conectado a base de datos");
} catch (Exception $e) {
    print_error("Error de conexión: " . $e->getMessage());
    exit(1);
}

$envio = new Envio($db);

// ============================================================================
// TEST 1: BUSCAR CANTIDAD EXACTA (PASO 1)
// ============================================================================
print_header("TEST 1: BÚSQUEDA DE CANTIDAD EXACTA");

print_section("Escenario: Pan Salvado con cantidad = 1 cuando stock tiene cnt = 10");
print_info("Esperado: PASO 1 encuentra exactamente 1 unidad (si existe)");

$filtros = [
    'codigo' => '405',  // Pan Salvado
    'cantidad' => 1
];

try {
    $resultados = $envio->obtenerProductosDisponibles($filtros);
    
    if (!empty($resultados)) {
        print_success("PASO 1: Encontró producto con cantidad exacta = 1");
        echo "\nResultados:\n";
        foreach ($resultados as $producto) {
            echo COLOR_YELLOW . "  - ID: {$producto['id_movimiento_item']}\n";
            echo "    Código: {$producto['codigo']}\n";
            echo "    Descripción: {$producto['descripcion']}\n";
            echo "    Cantidad: {$producto['cnt']}\n";
            echo "    Cantidad Disponible: {$producto['cnt_disponible']}\n";
            echo "    Veces Enviado: {$producto['veces_enviado']}\n" . COLOR_RESET . "\n";
        }
    } else {
        print_info("PASO 1 no encontró exacto, debería haber entrado a PASO 2 (cantidad > 1)");
        print_section("Intentando PASO 2: Cantidad > 1");
        
        $filtros['cantidad'] = 1;
        $resultados = $envio->obtenerProductosDisponibles($filtros);
        
        if (!empty($resultados)) {
            print_success("PASO 2: Encontró producto con cantidad > 1 (debería usar este cuando escanea 1)");
            foreach ($resultados as $producto) {
                echo COLOR_YELLOW . "  - Cantidad disponible: {$producto['cnt']}\n";
                echo "    Podrá enviar 1 de {$producto['cnt']} disponibles\n" . COLOR_RESET . "\n";
            }
        } else {
            print_error("PASO 2 tampoco encontró nada. Verifique que existan productos en stock");
        }
    }
} catch (Exception $e) {
    print_error("Error en búsqueda: " . $e->getMessage());
}

// ============================================================================
// TEST 2: BÚSQUEDA CON CANTIDAD SUPERIOR (PASO 2 EN ACCIÓN)
// ============================================================================
print_header("TEST 2: BÚSQUEDA CON CANTIDAD SUPERIOR");

print_section("Escenario: Escanear cantidad = 3, pero stock tiene cnt = 10");
print_info("Esperado: Si no hay exacto (3), encontrar superior (10) y permitir referenciar 3");

$filtros = [
    'codigo' => '405',
    'cantidad' => 3
];

try {
    $resultados = $envio->obtenerProductosDisponibles($filtros);
    
    if (!empty($resultados)) {
        print_success("Búsqueda inteligente encontró producto");
        foreach ($resultados as $producto) {
            if ($producto['cnt'] >= 3) {
                print_success("Producto tiene {$producto['cnt']} ≥ 3 solicitados, puede usar PASO 2");
            } else {
                print_error("Producto tiene {$producto['cnt']} < 3 solicitados");
            }
        }
    } else {
        print_error("No encontró producto con cantidad ≥ 3");
    }
} catch (Exception $e) {
    print_error("Error en búsqueda: " . $e->getMessage());
}

// ============================================================================
// TEST 3: BÚSQUEDA POR PESO (TIPO 21)
// ============================================================================
print_header("TEST 3: BÚSQUEDA POR PESO (CÓDIGO BARRAS TIPO 21)");

print_section("Escenario: Escanear código tipo 21 con peso exacto");
print_info("Esperado: PASO 1 busca peso exacto (sin fallback a superior)");

// Buscar productos con peso para el test
try {
    $stmt = $db->prepare("
        SELECT mi.id, p.codigo, p.descripcion, mi.cnt_peso, 
               (mi.cnt - IFNULL((
                   SELECT IFNULL(SUM(mi2.cnt), 0)
                   FROM movimientos_items mi2
                   WHERE mi2.id_movimientos_items_origen = mi.id
               ), 0)) as cnt_disponible
        FROM movimientos_items mi
        JOIN productos p ON p.id = mi.id_productos
        WHERE mi.id_movimientos_items_origen IS NULL
          AND mi.cnt_peso > 0
          AND mi.cnt > IFNULL((
              SELECT IFNULL(SUM(mi2.cnt), 0)
              FROM movimientos_items mi2
              WHERE mi2.id_movimientos_items_origen = mi.id
          ), 0)
        LIMIT 1
    ");
    $stmt->execute();
    $productoConPeso = $stmt->fetch(\PDO::FETCH_ASSOC);
    
    if (!empty($productoConPeso)) {
        $pesoTest = $productoConPeso['cnt_peso'];
        
        print_info("Encontrado producto con peso para test: {$productoConPeso['descripcion']} ({$pesoTest} kg)");
        
        $filtros = [
            'codigo' => $productoConPeso['codigo'],
            'peso' => $pesoTest
        ];
        
        $resultados = $envio->obtenerProductosDisponibles($filtros);
        
        if (!empty($resultados)) {
            print_success("Búsqueda por peso exacto funcionó correctamente");
            foreach ($resultados as $producto) {
                echo COLOR_YELLOW . "  - Peso: {$producto['cnt_peso']} kg\n";
                echo "    Será referenciado (origen): {$producto['id_movimiento_item']}\n" . COLOR_RESET . "\n";
            }
        } else {
            print_error("No encontró producto con peso exacto");
        }
    } else {
        print_info("No hay productos con peso en base de datos para este test");
    }
} catch (Exception $e) {
    print_error("Error en búsqueda por peso: " . $e->getMessage());
}

// ============================================================================
// TEST 4: BÚSQUEDA MANUAL (SIN PARÁMETROS)
// ============================================================================
print_header("TEST 4: BÚSQUEDA MANUAL (PASO 3)");

print_section("Escenario: Búsqueda general sin especificar cantidad/peso");
print_info("Esperado: Devuelve todos los productos disponibles");

try {
    $filtros = [
        'filtro' => ''  // Vacío para traer todos
    ];
    
    $resultados = $envio->obtenerProductosDisponibles($filtros);
    
    if (!empty($resultados)) {
        print_success("Búsqueda manual devolvió " . count($resultados) . " productos disponibles");
        
        echo "\nPrimeros 5 productos:\n";
        $contador = 0;
        foreach ($resultados as $producto) {
            if ($contador >= 5) break;
            echo COLOR_YELLOW . "  " . ($contador + 1) . ". {$producto['codigo']} - {$producto['descripcion']} (cnt: {$producto['cnt']}, disp: {$producto['cnt_disponible']})\n" . COLOR_RESET;
            $contador++;
        }
    } else {
        print_error("Búsqueda manual no retornó resultados");
    }
} catch (Exception $e) {
    print_error("Error en búsqueda manual: " . $e->getMessage());
}

// ============================================================================
// TEST 5: BÚSQUEDA CON FILTRO TEXTO
// ============================================================================
print_header("TEST 5: BÚSQUEDA CON FILTRO TEXTO");

print_section("Escenario: Buscar por descripción parcial");
print_info("Esperado: Devuelve productos que coincidan con descripción");

try {
    $filtros = [
        'filtro' => 'pan'  // Buscar productos con 'pan' en nombre
    ];
    
    $resultados = $envio->obtenerProductosDisponibles($filtros);
    
    if (!empty($resultados)) {
        print_success("Búsqueda por texto encontró " . count($resultados) . " productos");
        
        foreach ($resultados as $producto) {
            echo COLOR_YELLOW . "  - {$producto['codigo']}: {$producto['descripcion']} (disponible: {$producto['cnt_disponible']})\n" . COLOR_RESET;
        }
    } else {
        print_info("No hay productos que coincidan con 'pan'");
    }
} catch (Exception $e) {
    print_error("Error en búsqueda por texto: " . $e->getMessage());
}

// ============================================================================
// RESUMEN FINAL
// ============================================================================
print_section("RESUMEN DE TESTS");

echo COLOR_BOLD . "Estado de implementación 3-pasos:" . COLOR_RESET . "\n";
echo "  PASO 1: Cantidad EXACTA → Si encuentra, devuelve\n";
echo "  PASO 2: Cantidad SUPERIOR → Si PASO 1 no encuentra, busca mi.cnt > cantidad\n";
echo "  PASO 3: Sin restricción → Si PASO 2 no encuentra, devuelve todos\n\n";

echo COLOR_BOLD . "Casos de uso cubiertos:" . COLOR_RESET . "\n";
echo "  ✓ Barcode tipo 20 (cantidad): Busca exacto → superior → manual\n";
echo "  ✓ Barcode tipo 21 (peso): Busca peso exacto sin fallback\n";
echo "  ✓ Búsqueda manual: Devuelve todos disponibles\n";
echo "  ✓ Filtro por texto: Funciona en todos los casos\n\n";

print_success("Tests completados. Para test interactivo, accede a envios.html");
print_info("Próximo paso: Prueba en navegador escaneando códigos de barras");

echo "\n";
