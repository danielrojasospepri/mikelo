<?php
/**
 * Test de generación de PDF de Stock Depósito
 */

// Simular una petición HTTP al endpoint
$url = 'http://localhost/test/api/stock-deposito/pdf';

echo "🧪 Probando endpoint: $url\n";
echo str_repeat("=", 60) . "\n\n";

// Hacer la petición
$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HEADER, false);
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$error = curl_error($ch);
curl_close($ch);

echo "📊 Código HTTP: $httpCode\n";

if ($error) {
    echo "❌ Error cURL: $error\n";
}

echo "\n📄 Respuesta del servidor:\n";
echo str_repeat("-", 60) . "\n";
echo $response;
echo "\n" . str_repeat("-", 60) . "\n\n";

// Intentar decodificar como JSON
$json = json_decode($response, true);
if ($json) {
    echo "✅ Respuesta JSON válida:\n";
    print_r($json);
    
    if (isset($json['url'])) {
        echo "\n📎 URL del archivo: " . $json['url'] . "\n";
        
        // Verificar si el archivo existe
        $rutaCompleta = __DIR__ . str_replace('/mikelo', '', $json['url']);
        echo "📁 Ruta completa: $rutaCompleta\n";
        
        if (file_exists($rutaCompleta)) {
            $size = filesize($rutaCompleta);
            echo "✅ Archivo existe! Tamaño: " . number_format($size) . " bytes\n";
        } else {
            echo "❌ Archivo NO existe en el sistema de archivos\n";
        }
    }
} else {
    echo "⚠️ La respuesta no es JSON válido\n";
}

echo "\n✅ Test completado\n";
?>
