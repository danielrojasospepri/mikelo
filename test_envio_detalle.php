<?php
require_once 'api/comun.php';
require_once 'api/src/Model/Envio.php';

use App\Model\Envio;

try {
    echo "Probando exportación PDF de detalle de envío...\n";
    
    $db = getDB();
    $envio = new Envio($db);
    
    // Probar PDF de detalle para el envío ID 13
    echo "1. Probando PDF de detalle del envío #13...\n";
    $rutaPDF = $envio->exportarPDF(13);
    echo "✓ PDF de detalle generado: $rutaPDF\n";
    
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