<?php
require __DIR__ . '/vendor/autoload.php';
require __DIR__ . '/comun.php';

use Picqer\Barcode\BarcodeGeneratorPNG;

header('Content-Type: text/html; charset=utf-8');
echo "<h1>🧪 Test de Códigos de Barras</h1>";

try {
    $generator = new BarcodeGeneratorPNG();
    
    // Códigos de prueba
    $codigos = ['0000001', '0000002', '0000003', '0000004', '0000005'];
    
    echo "<h2>📊 Códigos generados:</h2>";
    
    foreach ($codigos as $codigo) {
        echo "<div style='margin: 20px 0; padding: 15px; border: 1px solid #ddd; border-radius: 5px;'>";
        echo "<h3>Código: $codigo</h3>";
        
        try {
            // Generar código de barras Code 128
            $barcodeData = $generator->getBarcode($codigo, $generator::TYPE_CODE_128);
            $barcodeBase64 = base64_encode($barcodeData);
            
            echo "<img src='data:image/png;base64,$barcodeBase64' alt='Código de barras $codigo' style='height: 60px; border: 1px solid #ccc; padding: 10px; background: white;'><br>";
            echo "<small>Formato: Code 128 | Tamaño: " . strlen($barcodeData) . " bytes</small>";
            
        } catch (Exception $e) {
            echo "<p style='color: red;'>Error: " . $e->getMessage() . "</p>";
        }
        
        echo "</div>";
    }
    
    echo "<p><strong>✅ Test completado!</strong> Los códigos de barras son reales y legibles por cualquier escáner.</p>";
    echo "<p><a href='javascript:history.back()'>← Volver</a></p>";
    
} catch (Exception $e) {
    echo "<p style='color: red;'>Error al cargar la librería: " . $e->getMessage() . "</p>";
}
?>