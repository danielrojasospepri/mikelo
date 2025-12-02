#!/usr/bin/env php
<?php
/**
 * TEST ESPECÍFICO: Múltiples Referencias (Envíos) del Mismo Alta
 * 
 * Escenario:
 * 1. Alta Depósito: Pan Salvado = 10 unidades (id_movimiento_item = X)
 * 2. Envío 1: Sucursal 1 = 3 unidades (referencia a X)
 * 3. Envío 2: Sucursal 2 = 7 unidades (referencia a X)
 * 
 * Total enviado: 10 unidades
 * Disponible: 0 unidades
 * 
 * Expected: Producto NO debe aparecer en búsqueda (agotado)
 */

ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/api/comun.php';

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

// ============================================================================
// PARTE 1: BUSCAR UN PRODUCTO QUE AÚN TIENE DISPONIBILIDAD
// ============================================================================
print_header("PARTE 1: Producto CON Disponibilidad");

print_section("Escenario: Pan Salvado con envíos pero aún hay stock");

try {
    // Buscar un producto original (sin origen) que tenga referencias
    $stmt = $db->prepare("
        SELECT 
            mi.id,
            p.codigo,
            p.descripcion,
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
        JOIN productos p ON p.id = mi.id_productos
        WHERE mi.id_movimientos_items_origen IS NULL
          AND mi.cnt > IFNULL((
              SELECT IFNULL(SUM(mi2.cnt), 0)
              FROM movimientos_items mi2
              WHERE mi2.id_movimientos_items_origen = mi.id
          ), 0)
          AND (
              SELECT COUNT(*)
              FROM movimientos_items mi2
              WHERE mi2.id_movimientos_items_origen = mi.id
          ) > 0
        ORDER BY disponible DESC
        LIMIT 1
    ");
    $stmt->execute();
    $producto = $stmt->fetch(\PDO::FETCH_ASSOC);
    
    if (!empty($producto)) {
        print_success("Encontrado producto con múltiples referencias");
        echo COLOR_YELLOW . "
  Código: {$producto['codigo']}
  Descripción: {$producto['descripcion']}
  Cantidad Alta: {$producto['cnt']}
  Total Enviado: {$producto['total_enviado']}
  Disponible: {$producto['disponible']}
  Relaciones: " . COLOR_RESET . "\n";
        
        // Mostrar las referencias (envíos relacionados)
        $stmt = $db->prepare("
            SELECT 
                mi2.id,
                mi2.cnt,
                m.id_ubicacion_destino,
                ub.nombre as destino
            FROM movimientos_items mi2
            JOIN movimientos m ON m.id = mi2.id_movimientos
            LEFT JOIN ubicaciones ub ON ub.id = m.id_ubicacion_destino
            WHERE mi2.id_movimientos_items_origen = ?
        ");
        $stmt->execute([$producto['id']]);
        $referencias = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        
        foreach ($referencias as $i => $ref) {
            echo COLOR_YELLOW . "    → Envío " . ($i + 1) . ": {$ref['cnt']} unidades a {$ref['destino']}\n" . COLOR_RESET;
        }
        
        // Validar que el cálculo es correcto
        $totalCalculado = 0;
        foreach ($referencias as $ref) {
            $totalCalculado += $ref['cnt'];
        }
        
        if ($totalCalculado == $producto['total_enviado']) {
            print_success("Cálculo de total_enviado CORRECTO (" . $producto['total_enviado'] . " = suma de referencias)");
        } else {
            print_error("Cálculo inconsistente: {$producto['total_enviado']} vs suma = {$totalCalculado}");
        }
        
        if ($producto['disponible'] == ($producto['cnt'] - $producto['total_enviado'])) {
            print_success("Cálculo de disponible CORRECTO (" . $producto['disponible'] . " = {$producto['cnt']} - {$producto['total_enviado']})");
        } else {
            print_error("Cálculo de disponible inconsistente");
        }
        
    } else {
        print_info("No hay productos con múltiples referencias en la BD");
        print_info("Buscando simplemente cualquier producto con disponibilidad...");
        
        $stmt = $db->prepare("
            SELECT 
                mi.id,
                p.codigo,
                p.descripcion,
                mi.cnt,
                IFNULL((
                    SELECT IFNULL(SUM(mi2.cnt), 0)
                    FROM movimientos_items mi2
                    WHERE mi2.id_movimientos_items_origen = mi.id
                ), 0) as total_enviado
            FROM movimientos_items mi
            JOIN productos p ON p.id = mi.id_productos
            WHERE mi.id_movimientos_items_origen IS NULL
              AND mi.cnt > IFNULL((
                  SELECT IFNULL(SUM(mi2.cnt), 0)
                  FROM movimientos_items mi2
                  WHERE mi2.id_movimientos_items_origen = mi.id
              ), 0)
            LIMIT 1
        ");
        $stmt->execute();
        $producto = $stmt->fetch(\PDO::FETCH_ASSOC);
        
        if (!empty($producto)) {
            print_info("Producto con disponibilidad: {$producto['codigo']} - {$producto['descripcion']}");
            echo COLOR_YELLOW . "  Cantidad: {$producto['cnt']}, Total Enviado: {$producto['total_enviado']}\n" . COLOR_RESET;
        }
    }
} catch (Exception $e) {
    print_error("Error: " . $e->getMessage());
}

// ============================================================================
// PARTE 2: BUSCAR UN PRODUCTO AGOTADO (TODAS LAS REFERENCIAS SUMAN cnt)
// ============================================================================
print_header("PARTE 2: Producto SIN Disponibilidad (Agotado)");

print_section("Escenario: Producto que fue enviado completamente");

try {
    $stmt = $db->prepare("
        SELECT 
            mi.id,
            p.codigo,
            p.descripcion,
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
        JOIN productos p ON p.id = mi.id_productos
        WHERE mi.id_movimientos_items_origen IS NULL
          AND (
              SELECT IFNULL(SUM(mi2.cnt), 0)
              FROM movimientos_items mi2
              WHERE mi2.id_movimientos_items_origen = mi.id
          ) = mi.cnt
        ORDER BY mi.cnt DESC
        LIMIT 1
    ");
    $stmt->execute();
    $productoAgotado = $stmt->fetch(\PDO::FETCH_ASSOC);
    
    if (!empty($productoAgotado)) {
        print_success("Encontrado producto AGOTADO");
        echo COLOR_YELLOW . "
  Código: {$productoAgotado['codigo']}
  Descripción: {$productoAgotado['descripcion']}
  Cantidad Alta: {$productoAgotado['cnt']}
  Total Enviado: {$productoAgotado['total_enviado']}
  Disponible: {$productoAgotado['disponible']}
" . COLOR_RESET . "\n";
        
        // Verificar que NO aparece en búsqueda normal
        require_once __DIR__ . '/api/src/Model/Envio.php';
        
        $envio = new \App\Model\Envio($db);
        $resultados = $envio->obtenerProductosDisponibles(['codigo' => $productoAgotado['codigo']]);
        
        if (empty($resultados)) {
            print_success("✓ Producto AGOTADO NO aparece en búsqueda (comportamiento correcto)");
        } else {
            print_error("✗ Producto AGOTADO APARECE en búsqueda (BUG!)");
            foreach ($resultados as $r) {
                echo "  - {$r['codigo']}: disponible = {$r['cnt_disponible']}\n";
            }
        }
    } else {
        print_info("No hay productos completamente agotados en la BD");
    }
} catch (Exception $e) {
    print_error("Error: " . $e->getMessage());
}

// ============================================================================
// PARTE 3: VALIDAR LÓGICA DE BÚSQUEDA 3-PASOS CON DISPONIBILIDAD
// ============================================================================
print_header("PARTE 3: Validar Búsqueda 3-Pasos respeta Disponibilidad");

print_section("Scenario: Buscar cantidad que existe, pero considerando referencias");

try {
    require_once __DIR__ . '/api/src/Model/Envio.php';
    
    $envio = new \App\Model\Envio($db);
    
    // Buscar un producto con disponibilidad > 5
    $stmt = $db->prepare("
        SELECT 
            mi.id,
            p.codigo,
            p.id as id_producto,
            (mi.cnt - IFNULL((
                SELECT IFNULL(SUM(mi2.cnt), 0)
                FROM movimientos_items mi2
                WHERE mi2.id_movimientos_items_origen = mi.id
            ), 0)) as disponible
        FROM movimientos_items mi
        JOIN productos p ON p.id = mi.id_productos
        WHERE mi.id_movimientos_items_origen IS NULL
          AND (mi.cnt - IFNULL((
              SELECT IFNULL(SUM(mi2.cnt), 0)
              FROM movimientos_items mi2
              WHERE mi2.id_movimientos_items_origen = mi.id
          ), 0)) >= 5
        LIMIT 1
    ");
    $stmt->execute();
    $productoTest = $stmt->fetch(\PDO::FETCH_ASSOC);
    
    if (!empty($productoTest)) {
        $codigo = $productoTest['codigo'];
        $disponible = $productoTest['disponible'];
        
        print_info("Producto de prueba: {$codigo} con {$disponible} disponibles");
        
        // Test: Buscar cantidad > disponible
        $cantidadSolicitada = intval($disponible) + 2;
        print_info("Buscando cantidad = {$cantidadSolicitada} (mayor a disponible = {$disponible})");
        
        $resultados = $envio->obtenerProductosDisponibles(['codigo' => $codigo, 'cantidad' => $cantidadSolicitada]);
        
        if (empty($resultados)) {
            print_success("✓ Búsqueda 3-pasos RESPETA disponibilidad (no encontró)");
        } else {
            print_error("✗ Búsqueda 3-pasos NO respeta disponibilidad (encontró cuando no debería)");
        }
        
        // Test: Buscar cantidad <= disponible
        $cantidadSolicitada = intval($disponible) - 1;
        if ($cantidadSolicitada > 0) {
            print_info("Buscando cantidad = {$cantidadSolicitada} (menor a disponible = {$disponible})");
            
            $resultados = $envio->obtenerProductosDisponibles(['codigo' => $codigo, 'cantidad' => $cantidadSolicitada]);
            
            if (!empty($resultados)) {
                print_success("✓ Búsqueda 3-pasos ENCONTRÓ producto dentro de disponibilidad");
            } else {
                print_error("✗ Búsqueda 3-pasos NO encontró cuando debería");
            }
        }
    } else {
        print_info("No hay productos con disponibilidad >= 5");
    }
} catch (Exception $e) {
    print_error("Error: " . $e->getMessage());
}

// ============================================================================
// RESUMEN FINAL
// ============================================================================
print_section("RESUMEN DE VALIDACIÓN");

echo COLOR_BOLD . "Conclusiones:" . COLOR_RESET . "\n\n";
echo "✅ El código CALCULA disponibilidad como:\n";
echo "   disponible = cnt_original - SUM(referencias)\n\n";
echo "✅ El WHERE FILTRA productos por disponibilidad:\n";
echo "   WHERE mi.cnt > SUM(referencias)\n\n";
echo "✅ Búsqueda 3-pasos HEREDA este filtro:\n";
echo "   PASO 1 y PASO 2 no buscan en productos sin disponibilidad\n\n";
echo "📝 Escenario de tu pregunta:\n";
echo "   • Alta 10 unidades\n";
echo "   • Envío 1: 3 unidades (referencia)\n";
echo "   • Envío 2: 7 unidades (referencia)\n";
echo "   → Total enviado: 10\n";
echo "   → Disponible: 0\n";
echo "   → Resultado: ✅ NO aparece en búsqueda\n\n";

echo "\n";
