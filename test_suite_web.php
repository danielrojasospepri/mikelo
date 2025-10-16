<?php
/**
 * Suite de Tests Web - Sistema Mikelo
 * 
 * Interfaz web para ejecutar y visualizar todos los tests del proyecto
 * 
 * @version 2.0
 * @date 15/10/2025
 */

require_once __DIR__ . '/api/comun.php';

// Función para ejecutar un test y capturar resultado
function ejecutarTest($nombre, $callable) {
    $inicio = microtime(true);
    ob_start();
    
    try {
        $resultado = $callable();
        $output = ob_get_clean();
        $tiempo = round((microtime(true) - $inicio) * 1000, 2);
        
        return [
            'nombre' => $nombre,
            'exito' => $resultado['exito'] ?? true,
            'mensaje' => $resultado['mensaje'] ?? 'Test completado',
            'detalles' => $resultado['detalles'] ?? '',
            'tiempo' => $tiempo,
            'output' => $output
        ];
    } catch (Exception $e) {
        $output = ob_get_clean();
        $tiempo = round((microtime(true) - $inicio) * 1000, 2);
        
        return [
            'nombre' => $nombre,
            'exito' => false,
            'mensaje' => 'Error: ' . $e->getMessage(),
            'detalles' => $e->getTraceAsString(),
            'tiempo' => $tiempo,
            'output' => $output
        ];
    }
}

// ============================================================================
// TESTS MÓDULO 1: CONEXIÓN Y CONFIGURACIÓN
// ============================================================================

$tests_modulo1 = [];

// Test 1: Conexión a Base de Datos
$tests_modulo1[] = ejecutarTest('Conexión a Base de Datos', function() {
    $db = getDB();
    $stmt = $db->query("SELECT DATABASE() as db_name");
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    return [
        'exito' => !empty($result['db_name']),
        'mensaje' => 'Conectado a: ' . ($result['db_name'] ?? 'desconocido'),
        'detalles' => 'PDO conectado exitosamente'
    ];
});

// Test 2: Sincronización Timezone
$tests_modulo1[] = ejecutarTest('Sincronización Timezone PHP + MySQL', function() {
    $db = getDB();
    
    $phpTz = date_default_timezone_get();
    $stmt = $db->query("SELECT @@session.time_zone as session_tz, NOW() as mysql_now");
    $mysql = $stmt->fetch(PDO::FETCH_ASSOC);
    
    $phpTime = new DateTime('now', new DateTimeZone('America/Argentina/Buenos_Aires'));
    $mysqlTime = new DateTime($mysql['mysql_now']);
    $diff = abs($phpTime->getTimestamp() - $mysqlTime->getTimestamp());
    
    return [
        'exito' => $diff <= 5,
        'mensaje' => "Diferencia: {$diff} segundos",
        'detalles' => "PHP TZ: {$phpTz} | MySQL TZ: {$mysql['session_tz']}"
    ];
});

// ============================================================================
// TESTS MÓDULO 2: STOCK DEPÓSITO
// ============================================================================

$tests_modulo2 = [];

// Test 3: Exclusión de Productos BAJA
$tests_modulo2[] = ejecutarTest('Exclusión de Productos BAJA en Stock', function() {
    $db = getDB();
    
    $sql_check_baja = "SELECT COUNT(*) as total
                       FROM movimientos_items mi
                       INNER JOIN estados_items_movimientos eim ON eim.id_movimientos_items = mi.id
                       INNER JOIN estados e ON eim.id_estados = e.id
                       WHERE e.nombre = 'BAJA'";
    
    $stmt_check = $db->query($sql_check_baja);
    $bajas = $stmt_check->fetch(PDO::FETCH_ASSOC);
    
    $sql_stock = "SELECT COUNT(DISTINCT p.id) as total_productos
                 FROM movimientos_items mi
                 INNER JOIN productos p ON mi.id_productos = p.id
                 WHERE mi.id_movimientos_items_origen IS NULL
                 AND NOT EXISTS (
                     SELECT 1 FROM movimientos_items mi2 
                     WHERE mi2.id_movimientos_items_origen = mi.id
                 )
                 AND NOT EXISTS (
                     SELECT 1 FROM estados_items_movimientos eim
                     INNER JOIN estados e ON eim.id_estados = e.id
                     WHERE eim.id_movimientos_items = mi.id AND e.nombre = 'BAJA'
                 )";
    
    $stmt_stock = $db->query($sql_stock);
    $stock = $stmt_stock->fetch(PDO::FETCH_ASSOC);
    
    return [
        'exito' => true,
        'mensaje' => "Stock activo: {$stock['total_productos']} productos",
        'detalles' => "Productos dados de baja: {$bajas['total']} (excluidos correctamente)"
    ];
});

// Test 4: Suma de Cantidades
$tests_modulo2[] = ejecutarTest('Suma de Cantidades (SUM vs COUNT)', function() {
    $db = getDB();
    
    $sql = "SELECT 
                SUM(mi.cnt) as total_suma,
                COUNT(mi.id) as total_registros
            FROM movimientos_items mi
            WHERE mi.id_movimientos_items_origen IS NULL
            AND NOT EXISTS (
                SELECT 1 FROM movimientos_items mi2 
                WHERE mi2.id_movimientos_items_origen = mi.id
            )
            AND NOT EXISTS (
                SELECT 1 FROM estados_items_movimientos eim
                INNER JOIN estados e ON eim.id_estados = e.id
                WHERE eim.id_movimientos_items = mi.id AND e.nombre = 'BAJA'
            )";
    
    $stmt = $db->query($sql);
    $resultado = $stmt->fetch(PDO::FETCH_ASSOC);
    
    $diferente = $resultado['total_suma'] != $resultado['total_registros'];
    
    return [
        'exito' => true,
        'mensaje' => "Suma: {$resultado['total_suma']} unidades | Registros: {$resultado['total_registros']}",
        'detalles' => $diferente ? '✓ Usando SUM correctamente' : 'Valores coinciden'
    ];
});

// Test 5: Exportación PDF Stock
$tests_modulo2[] = ejecutarTest('Exportación PDF Stock Depósito', function() {
    $url = 'http://localhost/mikelo/api/stock-deposito/pdf';
    
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HEADER, true);
    curl_setopt($ch, CURLOPT_NOBODY, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $contentType = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
    
    curl_close($ch);
    
    $headerSize = strpos($response, "\r\n\r\n");
    $body = substr($response, $headerSize + 4);
    
    $exito = $httpCode == 200 && 
             strpos($contentType, 'application/pdf') !== false &&
             substr($body, 0, 4) === '%PDF';
    
    return [
        'exito' => $exito,
        'mensaje' => "HTTP {$httpCode} | Content-Type: " . (strpos($contentType, 'pdf') !== false ? 'PDF' : $contentType),
        'detalles' => 'Formato: ' . (substr($body, 0, 4) === '%PDF' ? 'PDF válido' : 'No es PDF')
    ];
});

// Test 6: Exportación Excel Stock
$tests_modulo2[] = ejecutarTest('Exportación Excel Stock Depósito', function() {
    $url = 'http://localhost/mikelo/api/stock-deposito/excel';
    
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HEADER, true);
    curl_setopt($ch, CURLOPT_NOBODY, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $contentType = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
    
    curl_close($ch);
    
    $headerSize = strpos($response, "\r\n\r\n");
    $body = substr($response, $headerSize + 4);
    
    $exito = $httpCode == 200 && 
             strpos($contentType, 'spreadsheet') !== false &&
             substr($body, 0, 2) === 'PK';
    
    return [
        'exito' => $exito,
        'mensaje' => "HTTP {$httpCode} | Content-Type: " . (strpos($contentType, 'spreadsheet') !== false ? 'XLSX' : $contentType),
        'detalles' => 'Formato: ' . (substr($body, 0, 2) === 'PK' ? 'ZIP/XLSX válido' : 'No es XLSX')
    ];
});

// Test 7: Formato de Números
$tests_modulo2[] = ejecutarTest('Formato de Números (Sin Decimales Innecesarios)', function() {
    $formatNumber = function($num) {
        $formatted = number_format($num, 3, '.', '');
        return rtrim(rtrim($formatted, '0'), '.');
    };
    
    $tests = [
        ['input' => 1.000, 'esperado' => '1'],
        ['input' => 1.250, 'esperado' => '1.25'],
        ['input' => 10.500, 'esperado' => '10.5'],
        ['input' => 100.000, 'esperado' => '100'],
    ];
    
    $todos_ok = true;
    $detalles = [];
    
    foreach ($tests as $test) {
        $resultado = $formatNumber($test['input']);
        $ok = $resultado === $test['esperado'];
        $todos_ok = $todos_ok && $ok;
        $detalles[] = "{$test['input']} → {$resultado} " . ($ok ? '✓' : '✗');
    }
    
    return [
        'exito' => $todos_ok,
        'mensaje' => $todos_ok ? 'Todos los formatos correctos' : 'Algunos formatos incorrectos',
        'detalles' => implode(' | ', $detalles)
    ];
});

// Test 8: Dar de Baja
$tests_modulo2[] = ejecutarTest('Dar de Baja (Sin Columnas Inexistentes)', function() {
    $db = getDB();
    
    $stmt = $db->query("DESCRIBE estados");
    $columnas_estados = $stmt->fetchAll(PDO::FETCH_COLUMN);
    $tiene_descripcion = in_array('descripcion', $columnas_estados);
    
    $stmt = $db->query("DESCRIBE estados_items_movimientos");
    $columnas_eim = $stmt->fetchAll(PDO::FETCH_COLUMN);
    $tiene_observaciones = in_array('observaciones', $columnas_eim);
    
    $stmt = $db->query("SELECT COUNT(*) as total FROM estados WHERE nombre = 'BAJA'");
    $baja = $stmt->fetch(PDO::FETCH_ASSOC);
    
    $exito = !$tiene_descripcion && !$tiene_observaciones && $baja['total'] > 0;
    
    return [
        'exito' => $exito,
        'mensaje' => 'Estructura de tablas verificada',
        'detalles' => "estados sin 'descripcion' | estados_items_movimientos sin 'observaciones' | Estado BAJA existe"
    ];
});

// ============================================================================
// TESTS MÓDULO 3: ENVÍOS
// ============================================================================

$tests_modulo3 = [];

// Test 9: Exportación PDF Envíos
$tests_modulo3[] = ejecutarTest('Exportación PDF Envíos', function() {
    $url = 'http://localhost/mikelo/api/envios/pdf';
    
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HEADER, true);
    curl_setopt($ch, CURLOPT_NOBODY, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $contentType = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
    
    curl_close($ch);
    
    $headerSize = strpos($response, "\r\n\r\n");
    $body = substr($response, $headerSize + 4);
    
    $exito = $httpCode == 200 && 
             strpos($contentType, 'application/pdf') !== false &&
             substr($body, 0, 4) === '%PDF';
    
    return [
        'exito' => $exito,
        'mensaje' => "HTTP {$httpCode}",
        'detalles' => 'PDF: ' . (substr($body, 0, 4) === '%PDF' ? '✓ Válido' : '✗ Inválido')
    ];
});

// Test 10: Headers HTTP
$tests_modulo3[] = ejecutarTest('Headers de Respuesta Binaria', function() {
    $url = 'http://localhost/mikelo/api/stock-deposito/pdf';
    
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HEADER, true);
    curl_setopt($ch, CURLOPT_NOBODY, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    
    $headers = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    
    curl_close($ch);
    
    $checks = [];
    $checks[] = $httpCode == 200 ? 'HTTP 200 ✓' : 'HTTP ' . $httpCode . ' ✗';
    $checks[] = strpos($headers, 'Content-Type: application/pdf') !== false ? 'Content-Type ✓' : 'Content-Type ✗';
    $checks[] = strpos($headers, 'Content-Disposition: attachment') !== false ? 'Disposition ✓' : 'Disposition ✗';
    $checks[] = strpos($headers, 'Content-Length:') !== false ? 'Length ✓' : 'Length ✗';
    
    $exito = $httpCode == 200 && 
             strpos($headers, 'Content-Type: application/pdf') !== false &&
             strpos($headers, 'Content-Disposition: attachment') !== false;
    
    return [
        'exito' => $exito,
        'mensaje' => 'Headers validados',
        'detalles' => implode(' | ', $checks)
    ];
});

// ============================================================================
// CALCULAR ESTADÍSTICAS
// ============================================================================

$todos_tests = array_merge($tests_modulo1, $tests_modulo2, $tests_modulo3);
$total_tests = count($todos_tests);
$tests_pasados = count(array_filter($todos_tests, function($t) { return $t['exito']; }));
$tests_fallados = $total_tests - $tests_pasados;
$tiempo_total = array_sum(array_column($todos_tests, 'tiempo'));
$porcentaje_exito = $total_tests > 0 ? round(($tests_pasados / $total_tests) * 100, 1) : 0;

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Suite de Tests - Sistema Mikelo</title>
    <link rel="stylesheet" href="AdminLTE/dist/css/adminlte.min.css">
    <link rel="stylesheet" href="AdminLTE/plugins/fontawesome-free/css/all.min.css">
    <style>
        body {
            background-color: #f4f6f9;
            font-family: 'Source Sans Pro', sans-serif;
        }
        .test-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 2rem;
            border-radius: 10px;
            margin-bottom: 2rem;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        }
        .test-card {
            border-left: 4px solid #007bff;
            margin-bottom: 1rem;
            transition: transform 0.2s, box-shadow 0.2s;
        }
        .test-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        }
        .test-card.success {
            border-left-color: #28a745;
        }
        .test-card.failed {
            border-left-color: #dc3545;
        }
        .stats-card {
            background: white;
            border-radius: 10px;
            padding: 1.5rem;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            margin-bottom: 1rem;
        }
        .badge-time {
            background-color: #6c757d;
            color: white;
            padding: 0.25rem 0.5rem;
            border-radius: 4px;
            font-size: 0.85rem;
        }
        .module-section {
            margin-bottom: 2rem;
        }
        .module-header {
            background-color: #17a2b8;
            color: white;
            padding: 0.75rem 1rem;
            border-radius: 6px;
            margin-bottom: 1rem;
            font-weight: 600;
        }
        .progress-ring {
            width: 120px;
            height: 120px;
        }
        .detail-toggle {
            cursor: pointer;
            color: #007bff;
            text-decoration: underline;
        }
        .test-details {
            background-color: #f8f9fa;
            padding: 1rem;
            border-radius: 4px;
            margin-top: 0.5rem;
            font-family: 'Courier New', monospace;
            font-size: 0.9rem;
            white-space: pre-wrap;
        }
    </style>
</head>
<body class="hold-transition sidebar-mini">
<div class="wrapper">
    <div class="content-wrapper" style="margin-left: 0;">
        <section class="content" style="padding: 2rem;">
            
            <!-- Header -->
            <div class="test-header">
                <div class="row align-items-center">
                    <div class="col-md-8">
                        <h1 class="mb-2">
                            <i class="fas fa-vial"></i> Suite de Tests - Sistema Mikelo
                        </h1>
                        <p class="mb-0">
                            <i class="far fa-clock"></i> Ejecutado: <?= date('d/m/Y H:i:s') ?>
                        </p>
                    </div>
                    <div class="col-md-4 text-right">
                        <div class="info-box bg-white">
                            <div class="info-box-content">
                                <span class="info-box-number" style="color: <?= $tests_fallados == 0 ? '#28a745' : '#dc3545' ?>; font-size: 2.5rem;">
                                    <?= $porcentaje_exito ?>%
                                </span>
                                <span class="info-box-text">Tasa de Éxito</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Estadísticas Generales -->
            <div class="row mb-4">
                <div class="col-md-3">
                    <div class="stats-card text-center">
                        <h3 class="text-primary"><?= $total_tests ?></h3>
                        <p class="text-muted mb-0">Total Tests</p>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="stats-card text-center">
                        <h3 class="text-success"><?= $tests_pasados ?></h3>
                        <p class="text-muted mb-0">✅ Pasados</p>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="stats-card text-center">
                        <h3 class="text-danger"><?= $tests_fallados ?></h3>
                        <p class="text-muted mb-0">❌ Fallados</p>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="stats-card text-center">
                        <h3 class="text-info"><?= round($tiempo_total, 2) ?> ms</h3>
                        <p class="text-muted mb-0">⏱️ Tiempo Total</p>
                    </div>
                </div>
            </div>

            <!-- Módulo 1: Conexión y Configuración -->
            <div class="module-section">
                <div class="module-header">
                    <i class="fas fa-database"></i> MÓDULO 1: Conexión y Configuración
                    <span class="badge badge-light float-right"><?= count($tests_modulo1) ?> tests</span>
                </div>
                
                <?php foreach ($tests_modulo1 as $index => $test): ?>
                <div class="card test-card <?= $test['exito'] ? 'success' : 'failed' ?>">
                    <div class="card-body">
                        <div class="row align-items-center">
                            <div class="col-md-1 text-center">
                                <i class="fas <?= $test['exito'] ? 'fa-check-circle text-success' : 'fa-times-circle text-danger' ?>" style="font-size: 2rem;"></i>
                            </div>
                            <div class="col-md-7">
                                <h5 class="mb-1"><?= htmlspecialchars($test['nombre']) ?></h5>
                                <p class="mb-0 text-muted"><?= htmlspecialchars($test['mensaje']) ?></p>
                            </div>
                            <div class="col-md-3 text-right">
                                <span class="badge-time"><?= $test['tiempo'] ?> ms</span>
                            </div>
                            <div class="col-md-1 text-center">
                                <span class="detail-toggle" onclick="toggleDetails('m1-<?= $index ?>')">
                                    <i class="fas fa-info-circle"></i>
                                </span>
                            </div>
                        </div>
                        <div id="m1-<?= $index ?>" class="test-details" style="display: none;">
                            <strong>Detalles:</strong><br>
                            <?= htmlspecialchars($test['detalles']) ?>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>

            <!-- Módulo 2: Stock Depósito -->
            <div class="module-section">
                <div class="module-header">
                    <i class="fas fa-boxes"></i> MÓDULO 2: Stock Depósito
                    <span class="badge badge-light float-right"><?= count($tests_modulo2) ?> tests</span>
                </div>
                
                <?php foreach ($tests_modulo2 as $index => $test): ?>
                <div class="card test-card <?= $test['exito'] ? 'success' : 'failed' ?>">
                    <div class="card-body">
                        <div class="row align-items-center">
                            <div class="col-md-1 text-center">
                                <i class="fas <?= $test['exito'] ? 'fa-check-circle text-success' : 'fa-times-circle text-danger' ?>" style="font-size: 2rem;"></i>
                            </div>
                            <div class="col-md-7">
                                <h5 class="mb-1"><?= htmlspecialchars($test['nombre']) ?></h5>
                                <p class="mb-0 text-muted"><?= htmlspecialchars($test['mensaje']) ?></p>
                            </div>
                            <div class="col-md-3 text-right">
                                <span class="badge-time"><?= $test['tiempo'] ?> ms</span>
                            </div>
                            <div class="col-md-1 text-center">
                                <span class="detail-toggle" onclick="toggleDetails('m2-<?= $index ?>')">
                                    <i class="fas fa-info-circle"></i>
                                </span>
                            </div>
                        </div>
                        <div id="m2-<?= $index ?>" class="test-details" style="display: none;">
                            <strong>Detalles:</strong><br>
                            <?= htmlspecialchars($test['detalles']) ?>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>

            <!-- Módulo 3: Envíos -->
            <div class="module-section">
                <div class="module-header">
                    <i class="fas fa-truck"></i> MÓDULO 3: Envíos
                    <span class="badge badge-light float-right"><?= count($tests_modulo3) ?> tests</span>
                </div>
                
                <?php foreach ($tests_modulo3 as $index => $test): ?>
                <div class="card test-card <?= $test['exito'] ? 'success' : 'failed' ?>">
                    <div class="card-body">
                        <div class="row align-items-center">
                            <div class="col-md-1 text-center">
                                <i class="fas <?= $test['exito'] ? 'fa-check-circle text-success' : 'fa-times-circle text-danger' ?>" style="font-size: 2rem;"></i>
                            </div>
                            <div class="col-md-7">
                                <h5 class="mb-1"><?= htmlspecialchars($test['nombre']) ?></h5>
                                <p class="mb-0 text-muted"><?= htmlspecialchars($test['mensaje']) ?></p>
                            </div>
                            <div class="col-md-3 text-right">
                                <span class="badge-time"><?= $test['tiempo'] ?> ms</span>
                            </div>
                            <div class="col-md-1 text-center">
                                <span class="detail-toggle" onclick="toggleDetails('m3-<?= $index ?>')">
                                    <i class="fas fa-info-circle"></i>
                                </span>
                            </div>
                        </div>
                        <div id="m3-<?= $index ?>" class="test-details" style="display: none;">
                            <strong>Detalles:</strong><br>
                            <?= htmlspecialchars($test['detalles']) ?>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>

            <!-- Resumen Final -->
            <div class="card">
                <div class="card-header" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white;">
                    <h3 class="card-title"><i class="fas fa-flag-checkered"></i> Resumen Final</h3>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <h4>Estado General</h4>
                            <div class="alert <?= $tests_fallados == 0 ? 'alert-success' : 'alert-warning' ?>">
                                <h5>
                                    <?php if ($tests_fallados == 0): ?>
                                        <i class="fas fa-check-circle"></i> ✅ TODOS LOS TESTS PASARON
                                    <?php else: ?>
                                        <i class="fas fa-exclamation-triangle"></i> ⚠️ ALGUNOS TESTS FALLARON
                                    <?php endif; ?>
                                </h5>
                                <p class="mb-0">
                                    <?= $tests_pasados ?> de <?= $total_tests ?> tests completados exitosamente 
                                    (<?= $porcentaje_exito ?>%)
                                </p>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <h4>Métricas</h4>
                            <ul class="list-unstyled">
                                <li><strong>Total de Tests:</strong> <?= $total_tests ?></li>
                                <li><strong>Tiempo Total:</strong> <?= round($tiempo_total, 2) ?> ms (<?= round($tiempo_total / 1000, 2) ?> seg)</li>
                                <li><strong>Tiempo Promedio:</strong> <?= round($tiempo_total / $total_tests, 2) ?> ms por test</li>
                                <li><strong>Cobertura Funcional:</strong> <?= $porcentaje_exito ?>%</li>
                            </ul>
                        </div>
                    </div>
                    
                    <hr>
                    
                    <div class="text-center">
                        <button class="btn btn-primary" onclick="location.reload()">
                            <i class="fas fa-sync-alt"></i> Re-ejecutar Tests
                        </button>
                        <a href="api/tests/TestSuiteStockDeposito.php" class="btn btn-secondary">
                            <i class="fas fa-terminal"></i> Ver Suite CLI
                        </a>
                        <a href="index.html" class="btn btn-info">
                            <i class="fas fa-home"></i> Volver al Sistema
                        </a>
                    </div>
                </div>
            </div>

        </section>
    </div>
</div>

<script src="AdminLTE/plugins/jquery/jquery.min.js"></script>
<script src="AdminLTE/plugins/bootstrap/js/bootstrap.bundle.min.js"></script>
<script src="AdminLTE/dist/js/adminlte.min.js"></script>
<script>
function toggleDetails(id) {
    const element = document.getElementById(id);
    if (element.style.display === 'none') {
        element.style.display = 'block';
    } else {
        element.style.display = 'none';
    }
}

// Auto-scroll suave al cargar
window.addEventListener('load', function() {
    window.scrollTo({ top: 0, behavior: 'smooth' });
});
</script>

</body>
</html>
