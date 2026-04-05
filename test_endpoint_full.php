<?php
// Test del endpoint productos-disponibles via HTTP con login
echo "=== Test completo del endpoint productos-disponibles ===\n\n";

// Primero hacer login
echo "1. Haciendo login...\n";
$ch = curl_init('https://localhost/test/api/auth/login');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
    'usuario' => 'admin',
    'password' => 'admin123'
]));
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "Login HTTP Code: $httpCode\n";
$loginData = json_decode($response, true);

if (!$loginData || !isset($loginData['token'])) {
    echo "Error en login:\n";
    print_r($loginData);
    exit;
}

$token = $loginData['token'];
echo "Token obtenido: " . substr($token, 0, 30) . "...\n\n";

// Ahora probar productos-disponibles
echo "2. Consultando productos disponibles...\n";
$ch = curl_init('https://localhost/test/api/pedidos/productos-disponibles');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Authorization: Bearer ' . $token]);
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
        echo "✓ Productos disponibles: " . count($productos) . "\n\n";
        if (count($productos) > 0) {
            echo "Primeros 5:\n";
            echo str_repeat("-", 60) . "\n";
            for ($i = 0; $i < min(5, count($productos)); $i++) {
                $p = $productos[$i];
                printf("- %-8s %-35s Stock: %s\n", $p['codigo'], substr($p['nombre'], 0, 35), $p['stock_disponible']);
            }
        }
    }
} else {
    echo "Respuesta no JSON:\n";
    echo substr($response, 0, 500);
}
