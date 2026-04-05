<?php
// Test del endpoint productos-disponibles via HTTP
echo "Probando endpoint HTTP...\n\n";

$ch = curl_init('https://localhost/test/api/pedidos/productos-disponibles');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Authorization: Bearer test-token-admin']);
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "HTTP Code: $httpCode\n\n";

$data = json_decode($response, true);
if ($data) {
    if (isset($data['error']) && $data['error']) {
        echo "Error: " . ($data['mensaje'] ?? 'Sin mensaje') . "\n";
    } else {
        $productos = $data['productos'] ?? [];
        echo "Productos: " . count($productos) . "\n\n";
        if (count($productos) > 0) {
            echo "Primeros 5:\n";
            for ($i = 0; $i < min(5, count($productos)); $i++) {
                $p = $productos[$i];
                echo "- {$p['codigo']} {$p['nombre']}: Stock={$p['stock_disponible']}\n";
            }
        }
    }
} else {
    echo "Respuesta no JSON:\n";
    echo substr($response, 0, 500);
}
