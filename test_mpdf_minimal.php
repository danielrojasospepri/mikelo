<?php
require_once 'api/vendor/autoload.php';

try {
    echo "Probando mPDF con configuración minimal...\n";
    
    // Configuración absolutamente básica
    $mpdf = new \Mpdf\Mpdf([
        'mode' => 'utf-8',
        'format' => 'A4',
        'default_font_size' => 12,
        'default_font' => 'helvetica'
    ]);

    $html = '<h1>Prueba PDF Minimal</h1><p>Si puedes ver esto, mPDF funciona correctamente.</p>';
    
    $mpdf->WriteHTML($html);
    $mpdf->Output('test_minimal.pdf', 'F');
    
    echo "✓ PDF generado exitosamente como test_minimal.pdf\n";
    
} catch (Exception $e) {
    echo "✗ Error: " . $e->getMessage() . "\n";
    echo "Archivo: " . $e->getFile() . " Línea: " . $e->getLine() . "\n";
}
?>