<?php
require __DIR__ . '/comun.php';

try {
    $db = getDB();
    
    echo "=== TEST API DETALLE ENVÍO ===\n";
    
    // Simular la llamada del controller
    require_once __DIR__ . '/src/Model/Envio.php';
    $envio = new App\Model\Envio($db);
    
    $detalle = $envio->obtenerDetalleEnvio(11);
    echo "Estructura de respuesta:\n";
    echo json_encode($detalle, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>