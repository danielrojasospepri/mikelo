<?php
require_once 'api/comun.php';
require_once 'api/src/Model/StockDeposito.php';

use App\Model\StockDeposito;

try {
    echo "Probando exportación PDF de Stock de Depósito...\n";
    
    $db = getDB();
    $stock = new StockDeposito($db);
    
    // Probar PDF de stock
    echo "1. Generando PDF de stock...\n";
    $rutaPDF = $stock->exportarPDF();
    
    // La ruta devuelta es /mikelo/temp/..., necesitamos convertirla a ruta de archivo
    $nombreArchivo = basename($rutaPDF);
    $rutaArchivo = __DIR__ . '/temp/' . $nombreArchivo;
    
    echo "✓ PDF generado: $rutaPDF\n";
    
    // Verificar que el archivo existe
    if (file_exists($rutaArchivo)) {
        echo "✓ Archivo PDF existe y pesa: " . filesize($rutaArchivo) . " bytes\n";
    } else {
        echo "✗ El archivo PDF no se encontró en: $rutaArchivo\n";
    }
    
} catch (Exception $e) {
    echo "✗ Error: " . $e->getMessage() . "\n";
    echo "Trace: " . $e->getTraceAsString() . "\n";
}
?>
