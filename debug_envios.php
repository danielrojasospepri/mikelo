<?php
require_once 'api/comun.php';
require_once 'api/src/Model/Envio.php';

use App\Model\Envio;

try {
    echo "Debuggeando datos de envíos...\n";
    
    $db = getDB();
    $envio = new Envio($db);
    
    // Obtener datos
    $data = $envio->obtenerEnvios();
    
    echo "Cantidad de envíos encontrados: " . count($data) . "\n";
    
    if (!empty($data)) {
        echo "\nPrimer envío (estructura):\n";
        print_r($data[0]);
        
        echo "\nColumnas disponibles:\n";
        foreach (array_keys($data[0]) as $key) {
            echo "- $key\n";
        }
    } else {
        echo "No se encontraron envíos en la base de datos.\n";
    }
    
} catch (Exception $e) {
    echo "✗ Error: " . $e->getMessage() . "\n";
}
?>