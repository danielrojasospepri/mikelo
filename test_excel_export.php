<?php
/**
 * Test para verificar exportación Excel
 */

require_once 'api/comun.php';

try {
    echo "🧪 Test: Exportación Excel - Stock Depósito\n";
    echo "============================================================\n\n";
    
    echo "📊 Probando endpoint: http://localhost/test/api/stock-deposito/excel\n";
    echo "------------------------------------------------------------\n\n";
    
    // Usar curl para probar
    $ch = curl_init('http://localhost/test/api/stock-deposito/excel');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HEADER, true);
    curl_setopt($ch, CURLOPT_NOBODY, false);
    
    $response = curl_exec($ch);
    $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    
    $headers = substr($response, 0, $headerSize);
    $body = substr($response, $headerSize);
    
    curl_close($ch);
    
    echo "📄 Código HTTP: $httpCode\n\n";
    
    if ($httpCode == 200) {
        // Verificar headers
        if (strpos($headers, 'Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet') !== false) {
            echo "✅ Content-Type correcto: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet\n";
        } else {
            echo "❌ Content-Type incorrecto\n";
        }
        
        if (preg_match('/Content-Disposition: attachment; filename="([^"]+)"/', $headers, $matches)) {
            echo "✅ Content-Disposition correcto: {$matches[1]}\n";
        } else {
            echo "❌ Content-Disposition no encontrado\n";
        }
        
        if (preg_match('/Content-Length: (\d+)/', $headers, $matches)) {
            $size = intval($matches[1]);
            echo "✅ Content-Length: " . number_format($size) . " bytes (" . round($size/1024, 2) . " KB)\n";
        }
        
        // Verificar que el body es un archivo Excel válido (comienza con PK)
        if (substr($body, 0, 2) === 'PK') {
            echo "✅ Contenido es un archivo ZIP válido (formato XLSX)\n";
        } else {
            echo "❌ Contenido NO es un archivo XLSX válido\n";
            echo "Primeros 100 caracteres:\n";
            echo substr($body, 0, 100) . "\n";
        }
        
    } else {
        echo "❌ Error HTTP $httpCode\n";
        echo "Respuesta:\n";
        echo $body . "\n";
    }
    
    echo "\n✅ Test completado\n";
    
} catch (Exception $e) {
    echo "❌ Error en test: " . $e->getMessage() . "\n";
}
