<?php
require_once 'api/comun.php';
require_once 'api/src/Model/Envio.php';

use App\Model\Envio;

try {
    echo "Probando exportación PDF de envíos...\n";
    
    $db = getDB();
    $envio = new Envio($db);
    
    // Probar PDF de lista
    echo "1. Probando PDF de lista de envíos...\n";
    $rutaPDF = $envio->exportarPDF();
    echo "✓ PDF de lista generado: $rutaPDF\n";
    
    // Verificar que el archivo existe
    if (file_exists($rutaPDF)) {
        echo "✓ Archivo PDF existe y pesa: " . filesize($rutaPDF) . " bytes\n";
    } else {
        echo "✗ El archivo PDF no se encontró\n";
    }
    
} catch (Exception $e) {
    echo "✗ Error: " . $e->getMessage() . "\n";
}
?>