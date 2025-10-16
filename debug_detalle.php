<?php
require_once 'api/comun.php';
require_once 'api/src/Model/Envio.php';

use App\Model\Envio;

try {
    echo "Debuggeando datos de detalle de envío...\n";
    
    $db = getDB();
    $envio = new Envio($db);
    
    // Obtener datos del envío 13
    $data = $envio->obtenerDetalleEnvio(13);
    
    echo "Información del envío:\n";
    print_r($data['envio']);
    
    echo "\nProductos en el envío:\n";
    foreach ($data['productos'] as $i => $producto) {
        echo "Producto " . ($i+1) . " (estructura completa):\n";
        print_r($producto);
        echo "  ---\n";
        if ($i == 0) break; // Solo mostrar el primer producto completo
    }
    
} catch (Exception $e) {
    echo "✗ Error: " . $e->getMessage() . "\n";
}
?>