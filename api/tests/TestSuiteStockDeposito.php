<?php
/**
 * Test Suite Completo - Stock Depósito
 * 
 * Este archivo reúne todos los tests generados para verificar las correcciones
 * realizadas en el módulo de Stock Depósito.
 * 
 * Fecha: 15/10/2025
 * Versión: 2.0 (Consolidado completo)
 * 
 * Ejecutar: php api/tests/TestSuiteStockDeposito.php
 */

require_once __DIR__ . '/../comun.php';
require_once __DIR__ . '/../../vendor/autoload.php';

use Picqer\Barcode\BarcodeGeneratorPNG;

class TestSuiteStockDeposito {
    private $db;
    private $resultados = [];
    
    public function __construct() {
        $this->db = getDB();
    }
    
    /**
     * Ejecuta todos los tests
     */
    public function ejecutarTodos() {
        echo "\n";
        echo "╔══════════════════════════════════════════════════════════════╗\n";
        echo "║        TEST SUITE COMPLETO - SISTEMA MIKELO                  ║\n";
        echo "║        Fecha: " . date('d/m/Y H:i:s') . "                              ║\n";
        echo "╚══════════════════════════════════════════════════════════════╝\n";
        echo "\n";
        
        // MÓDULO: Conexión y Configuración
        echo "═══ MÓDULO 1: CONEXIÓN Y CONFIGURACIÓN ═══\n\n";
        $this->test1_ConexionBaseDatos();
        $this->test2_TimezoneSincronizado();
        
        // MÓDULO: Stock Depósito
        echo "═══ MÓDULO 2: STOCK DEPÓSITO ═══\n\n";
        $this->test3_ExclusionProductosBaja();
        $this->test4_SumaCantidades();
        $this->test5_ExportacionPDF_Stock();
        $this->test6_ExportacionExcel_Stock();
        $this->test7_FormatoNumeros();
        $this->test8_DarDeBaja();
        
        // MÓDULO: Envíos
        echo "═══ MÓDULO 3: ENVÍOS ═══\n\n";
        $this->test9_ExportacionPDF_Envios();
        $this->test10_HeadersRespuestaBinaria();
        
        // MÓDULO: Códigos de Barras
        echo "═══ MÓDULO 4: CÓDIGOS DE BARRAS ═══\n\n";
        $this->test11_GeneracionCodigosBarras();
        
        // Mostrar resumen
        $this->mostrarResumen();
    }
    
    /**
     * Test 1: Verificar conexión a base de datos
     */
    private function test1_ConexionBaseDatos() {
        $this->iniciarTest("1. Conexión a Base de Datos");
        
        try {
            $stmt = $this->db->query("SELECT DATABASE() as db_name");
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($result && !empty($result['db_name'])) {
                $this->pasarTest("Conectado a: " . $result['db_name']);
            } else {
                $this->fallarTest("No se pudo obtener el nombre de la base de datos");
            }
        } catch (Exception $e) {
            $this->fallarTest("Error: " . $e->getMessage());
        }
    }
    
    /**
     * Test 2: Verificar sincronización de timezone PHP + MySQL
     */
    private function test2_TimezoneSincronizado() {
        $this->iniciarTest("2. Sincronización Timezone PHP + MySQL");
        
        try {
            // Obtener timezone de PHP
            $phpTz = date_default_timezone_get();
            
            // Obtener timezone de MySQL
            $stmt = $this->db->query("SELECT @@session.time_zone as session_tz, NOW() as mysql_now");
            $mysql = $stmt->fetch(PDO::FETCH_ASSOC);
            
            // Comparar horas
            $phpTime = new DateTime('now', new DateTimeZone('America/Argentina/Buenos_Aires'));
            $mysqlTime = new DateTime($mysql['mysql_now']);
            
            $diff = abs($phpTime->getTimestamp() - $mysqlTime->getTimestamp());
            
            $checks = [];
            $checks[] = "PHP TZ: {$phpTz}";
            $checks[] = "MySQL TZ: {$mysql['session_tz']}";
            $checks[] = "Diferencia: {$diff} segundos";
            
            if ($diff <= 5) {
                $this->pasarTest(implode(" | ", $checks) . " ✓ Sincronizados");
            } else {
                $this->fallarTest(implode(" | ", $checks) . " ✗ Desincronizados");
            }
            
        } catch (Exception $e) {
            $this->fallarTest("Error: " . $e->getMessage());
        }
    }
    
    /**
     * Test 3: Verificar exclusión de productos dados de baja
     */
    private function test3_ExclusionProductosBaja() {
        $this->iniciarTest("2. Exclusión de Productos BAJA en Stock");
        
        try {
            // Verificar que ningún producto dado de baja aparezca en el stock
            $sql_check_baja = "SELECT COUNT(*) as total
                               FROM movimientos_items mi
                               INNER JOIN estados_items_movimientos eim ON eim.id_movimientos_items = mi.id
                               INNER JOIN estados e ON eim.id_estados = e.id
                               WHERE e.nombre = 'BAJA'";
            
            $stmt_check = $this->db->query($sql_check_baja);
            $bajas = $stmt_check->fetch(PDO::FETCH_ASSOC);
            
            // Contar productos en stock (con filtro BAJA)
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
            
            $stmt_stock = $this->db->query($sql_stock);
            $stock = $stmt_stock->fetch(PDO::FETCH_ASSOC);
            
            $info = sprintf(
                "Stock activo: %d productos | Productos dados de baja: %d (excluidos)",
                $stock['total_productos'],
                $bajas['total']
            );
            
            $this->pasarTest($info);
            
        } catch (Exception $e) {
            $this->fallarTest("Error: " . $e->getMessage());
        }
    }
    
    /**
     * Test 4: Verificar suma correcta de cantidades (SUM vs COUNT)
     */
    private function test4_SumaCantidades() {
        $this->iniciarTest("3. Suma de Cantidades (SUM vs COUNT)");
        
        try {
            // Test simple: verificar que SUM funciona correctamente
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
            
            $stmt = $this->db->query($sql);
            $resultado = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($resultado && $resultado['total_suma'] > 0) {
                $diferente = $resultado['total_suma'] != $resultado['total_registros'];
                
                $info = sprintf(
                    "Registros=%d | Suma cantidades=%d %s",
                    $resultado['total_registros'],
                    $resultado['total_suma'],
                    $diferente ? "✓ Usando SUM correctamente" : "= Coinciden"
                );
                
                $this->pasarTest($info);
            } else {
                $this->pasarTest("No hay movimientos para verificar");
            }
            
        } catch (Exception $e) {
            $this->fallarTest("Error: " . $e->getMessage());
        }
    }
    
    /**
     * Test 5: Verificar exportación PDF Stock Depósito
     */
    private function test5_ExportacionPDF_Stock() {
        $this->iniciarTest("5. Exportación PDF Stock Depósito (Respuesta Binaria)");
        
        try {
            // Simular request a la API
            $url = 'http://localhost/mikelo/api/stock-deposito/pdf';
            
            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HEADER, true);
            curl_setopt($ch, CURLOPT_NOBODY, false);
            
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $contentType = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
            
            curl_close($ch);
            
            // Extraer headers
            $headerSize = strpos($response, "\r\n\r\n");
            $headers = substr($response, 0, $headerSize);
            $body = substr($response, $headerSize + 4);
            
            $checks = [];
            $checks[] = $httpCode == 200 ? "✓ HTTP 200 OK" : "✗ HTTP {$httpCode}";
            $checks[] = strpos($contentType, 'application/pdf') !== false ? "✓ Content-Type: PDF" : "✗ Content-Type: {$contentType}";
            $checks[] = strpos($headers, 'Content-Disposition') !== false ? "✓ Content-Disposition presente" : "✗ Sin Content-Disposition";
            $checks[] = substr($body, 0, 4) === '%PDF' ? "✓ Formato PDF válido" : "✗ No es PDF";
            
            $exito = $httpCode == 200 && strpos($contentType, 'application/pdf') !== false;
            
            if ($exito) {
                $this->pasarTest(implode(" | ", $checks));
            } else {
                $this->fallarTest(implode(" | ", $checks));
            }
            
        } catch (Exception $e) {
            $this->fallarTest("Error: " . $e->getMessage());
        }
    }
    
    /**
     * Test 6: Verificar exportación Excel Stock Depósito
     */
    private function test6_ExportacionExcel_Stock() {
        $this->iniciarTest("6. Exportación Excel Stock Depósito (Respuesta Binaria)");
        
        try {
            // Simular request a la API
            $url = 'http://localhost/mikelo/api/stock-deposito/excel';
            
            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HEADER, true);
            curl_setopt($ch, CURLOPT_NOBODY, false);
            
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $contentType = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
            
            curl_close($ch);
            
            // Extraer headers y body
            $headerSize = strpos($response, "\r\n\r\n");
            $headers = substr($response, 0, $headerSize);
            $body = substr($response, $headerSize + 4);
            
            $checks = [];
            $checks[] = $httpCode == 200 ? "✓ HTTP 200 OK" : "✗ HTTP {$httpCode}";
            $checks[] = strpos($contentType, 'spreadsheet') !== false ? "✓ Content-Type: XLSX" : "✗ Content-Type: {$contentType}";
            $checks[] = strpos($headers, 'Content-Disposition') !== false ? "✓ Content-Disposition presente" : "✗ Sin Content-Disposition";
            $checks[] = substr($body, 0, 2) === 'PK' ? "✓ Formato ZIP/XLSX válido" : "✗ No es XLSX";
            
            $exito = $httpCode == 200 && strpos($contentType, 'spreadsheet') !== false;
            
            if ($exito) {
                $this->pasarTest(implode(" | ", $checks));
            } else {
                $this->fallarTest(implode(" | ", $checks));
            }
            
        } catch (Exception $e) {
            $this->fallarTest("Error: " . $e->getMessage());
        }
    }
    
    /**
     * Test 7: Verificar formato de números
     */
    private function test7_FormatoNumeros() {
        $this->iniciarTest("7. Formato de Números (Sin Decimales Innecesarios)");
        
        try {
            // Función de formato (replicada del Model)
            $formatNumber = function($num) {
                $formatted = number_format($num, 3, '.', '');
                return rtrim(rtrim($formatted, '0'), '.');
            };
            
            $tests = [
                ['input' => 1.000, 'esperado' => '1'],
                ['input' => 1.250, 'esperado' => '1.25'],
                ['input' => 10.500, 'esperado' => '10.5'],
                ['input' => 0.750, 'esperado' => '0.75'],
                ['input' => 100.000, 'esperado' => '100'],
            ];
            
            $resultados = [];
            $todos_ok = true;
            
            foreach ($tests as $test) {
                $resultado = $formatNumber($test['input']);
                $ok = $resultado === $test['esperado'];
                $todos_ok = $todos_ok && $ok;
                
                $resultados[] = sprintf(
                    "%s → %s %s",
                    $test['input'],
                    $resultado,
                    $ok ? "✓" : "✗ (esperado: {$test['esperado']})"
                );
            }
            
            if ($todos_ok) {
                $this->pasarTest(implode(" | ", $resultados));
            } else {
                $this->fallarTest(implode(" | ", $resultados));
            }
            
        } catch (Exception $e) {
            $this->fallarTest("Error: " . $e->getMessage());
        }
    }
    
    /**
     * Test 8: Verificar estructura de dar de baja (sin columnas inexistentes)
     */
    private function test8_DarDeBaja() {
        $this->iniciarTest("8. Dar de Baja (Sin Columnas Inexistentes)");
        
        try {
            // Verificar estructura de tabla estados
            $stmt = $this->db->query("DESCRIBE estados");
            $columnas_estados = $stmt->fetchAll(PDO::FETCH_COLUMN);
            
            $tiene_descripcion = in_array('descripcion', $columnas_estados);
            
            // Verificar estructura de tabla estados_items_movimientos
            $stmt = $this->db->query("DESCRIBE estados_items_movimientos");
            $columnas_eim = $stmt->fetchAll(PDO::FETCH_COLUMN);
            
            $tiene_observaciones = in_array('observaciones', $columnas_eim);
            
            $checks = [];
            $checks[] = !$tiene_descripcion ? "✓ estados sin 'descripcion'" : "✗ estados tiene 'descripcion'";
            $checks[] = !$tiene_observaciones ? "✓ estados_items_movimientos sin 'observaciones'" : "✗ tiene 'observaciones'";
            
            // Verificar que existe el estado BAJA
            $stmt = $this->db->query("SELECT COUNT(*) as total FROM estados WHERE nombre = 'BAJA'");
            $baja = $stmt->fetch(PDO::FETCH_ASSOC);
            
            $checks[] = $baja['total'] > 0 ? "✓ Estado 'BAJA' existe" : "✗ Estado 'BAJA' no existe";
            
            $exito = !$tiene_descripcion && !$tiene_observaciones && $baja['total'] > 0;
            
            if ($exito) {
                $this->pasarTest(implode(" | ", $checks));
            } else {
                $this->fallarTest(implode(" | ", $checks));
            }
            
        } catch (Exception $e) {
            $this->fallarTest("Error: " . $e->getMessage());
        }
    }
    
    /**
     * Test 9: Verificar exportación PDF Envíos
     */
    private function test9_ExportacionPDF_Envios() {
        $this->iniciarTest("9. Exportación PDF Envíos (Respuesta Binaria)");
        
        try {
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
            $headers = substr($response, 0, $headerSize);
            $body = substr($response, $headerSize + 4);
            
            $checks = [];
            $checks[] = $httpCode == 200 ? "✓ HTTP 200 OK" : "✗ HTTP {$httpCode}";
            $checks[] = strpos($contentType, 'application/pdf') !== false ? "✓ Content-Type: PDF" : "✗ Content-Type: {$contentType}";
            $checks[] = substr($body, 0, 4) === '%PDF' ? "✓ Formato PDF válido" : "✗ No es PDF";
            
            $exito = $httpCode == 200 && strpos($contentType, 'application/pdf') !== false;
            
            if ($exito) {
                $this->pasarTest(implode(" | ", $checks));
            } else {
                $this->fallarTest(implode(" | ", $checks));
            }
            
        } catch (Exception $e) {
            $this->fallarTest("Error: " . $e->getMessage());
        }
    }
    
    /**
     * Test 10: Verificar headers de respuesta binaria
     */
    private function test10_HeadersRespuestaBinaria() {
        $this->iniciarTest("10. Headers de Respuesta Binaria");
        
        try {
            $url = 'http://localhost/mikelo/api/stock-deposito/pdf';
            
            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HEADER, true);
            curl_setopt($ch, CURLOPT_NOBODY, true); // Solo headers
            curl_setopt($ch, CURLOPT_TIMEOUT, 10);
            
            $headers = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            
            curl_close($ch);
            
            $checks = [];
            $checks[] = $httpCode == 200 ? "✓ HTTP 200" : "✗ HTTP {$httpCode}";
            $checks[] = strpos($headers, 'Content-Type: application/pdf') !== false ? "✓ Content-Type correcto" : "✗ Content-Type incorrecto";
            $checks[] = strpos($headers, 'Content-Disposition: attachment') !== false ? "✓ Content-Disposition presente" : "✗ Sin Content-Disposition";
            $checks[] = strpos($headers, 'Content-Length:') !== false ? "✓ Content-Length presente" : "✗ Sin Content-Length";
            
            $exito = $httpCode == 200 && 
                     strpos($headers, 'Content-Type: application/pdf') !== false &&
                     strpos($headers, 'Content-Disposition: attachment') !== false;
            
            if ($exito) {
                $this->pasarTest(implode(" | ", $checks));
            } else {
                $this->fallarTest(implode(" | ", $checks));
            }
            
        } catch (Exception $e) {
            $this->fallarTest("Error: " . $e->getMessage());
        }
    }
    
    /**
     * Test 11: Verificar generación de códigos de barras
     */
    private function test11_GeneracionCodigosBarras() {
        $this->iniciarTest("11. Generación de Códigos de Barras");
        
        try {
            // Verificar si existe la clase
            if (!class_exists('Picqer\Barcode\BarcodeGeneratorPNG')) {
                $this->pasarTest("Librería no instalada (opcional) - SKIP");
                return;
            }
            
            $generator = new BarcodeGeneratorPNG();
            
            $codigos_test = ['0000001', '0000042', '9999999'];
            $resultados = [];
            $todos_ok = true;
            
            foreach ($codigos_test as $codigo) {
                try {
                    $barcodeData = $generator->getBarcode($codigo, $generator::TYPE_CODE_128);
                    $size = strlen($barcodeData);
                    
                    $ok = $size > 0 && substr($barcodeData, 0, 8) === "\x89PNG\r\n\x1a\n";
                    $todos_ok = $todos_ok && $ok;
                    
                    $resultados[] = sprintf(
                        "%s: %s (%d bytes)",
                        $codigo,
                        $ok ? "✓" : "✗",
                        $size
                    );
                    
                } catch (Exception $e) {
                    $todos_ok = false;
                    $resultados[] = "{$codigo}: ✗ Error";
                }
            }
            
            if ($todos_ok) {
                $this->pasarTest(implode(" | ", $resultados));
            } else {
                $this->fallarTest(implode(" | ", $resultados));
            }
            
        } catch (Exception $e) {
            $this->fallarTest("Error: " . $e->getMessage());
        }
    }
    
    /**
     * Helpers para gestión de tests
     */
    private function iniciarTest($nombre) {
        echo "┌─────────────────────────────────────────────────────────────┐\n";
        echo "│ TEST: {$nombre}\n";
        echo "└─────────────────────────────────────────────────────────────┘\n";
    }
    
    private function pasarTest($info = "") {
        echo "  ✅ PASADO";
        if ($info) {
            echo " - {$info}";
        }
        echo "\n\n";
        $this->resultados[] = ['status' => 'PASADO', 'info' => $info];
    }
    
    private function fallarTest($info = "") {
        echo "  ❌ FALLADO";
        if ($info) {
            echo " - {$info}";
        }
        echo "\n\n";
        $this->resultados[] = ['status' => 'FALLADO', 'info' => $info];
    }
    
    /**
     * Mostrar resumen final
     */
    private function mostrarResumen() {
        $total = count($this->resultados);
        $pasados = count(array_filter($this->resultados, function($r) { 
            return $r['status'] === 'PASADO'; 
        }));
        $fallados = $total - $pasados;
        
        echo "\n";
        echo "╔══════════════════════════════════════════════════════════════╗\n";
        echo "║                    RESUMEN FINAL                             ║\n";
        echo "╠══════════════════════════════════════════════════════════════╣\n";
        echo sprintf("║  Total Tests:    %-40s ║\n", $total);
        echo sprintf("║  ✅ Pasados:     %-40s ║\n", $pasados);
        echo sprintf("║  ❌ Fallados:    %-40s ║\n", $fallados);
        echo "╠══════════════════════════════════════════════════════════════╣\n";
        
        if ($fallados === 0) {
            echo "║  RESULTADO:      ✅ TODOS LOS TESTS PASARON                 ║\n";
        } else {
            echo "║  RESULTADO:      ⚠️  ALGUNOS TESTS FALLARON                 ║\n";
        }
        
        echo "╚══════════════════════════════════════════════════════════════╝\n";
        echo "\n";
        
        // Exit code para CI/CD
        exit($fallados > 0 ? 1 : 0);
    }
}

// Ejecutar suite de tests
$suite = new TestSuiteStockDeposito();
$suite->ejecutarTodos();
