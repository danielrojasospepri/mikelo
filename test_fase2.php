<?php
/**
 * TESTS FASE 2 - Mikelo
 * Ejecutar con: php test_fase2.php
 */

require 'api/comun.php';

echo "=== TESTS FASE 2 - MIKELO ===\n";
echo "Fecha: " . date('Y-m-d H:i:s') . "\n\n";

$db = getDB();
$errores = 0;
$exitos = 0;

function test($nombre, $resultado, $esperado = true) {
    global $errores, $exitos;
    $ok = ($resultado === $esperado);
    if ($ok) {
        echo "✓ $nombre\n";
        $exitos++;
    } else {
        echo "✗ $nombre (esperado: " . var_export($esperado, true) . ", obtenido: " . var_export($resultado, true) . ")\n";
        $errores++;
    }
    return $ok;
}

function testApi($metodo, $endpoint, $datos = null, $token = null) {
    $baseUrl = 'http://localhost/test/api';
    $url = $baseUrl . $endpoint;
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    
    $headers = ['Content-Type: application/json'];
    if ($token) {
        $headers[] = "Authorization: Bearer $token";
    }
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    
    if ($metodo === 'POST') {
        curl_setopt($ch, CURLOPT_POST, true);
        if ($datos) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($datos));
        }
    } elseif ($metodo === 'PUT') {
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
        if ($datos) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($datos));
        }
    }
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);
    
    if ($error) {
        return ['error' => true, 'mensaje' => "CURL Error: $error", 'http_code' => 0];
    }
    
    $data = json_decode($response, true);
    $data['http_code'] = $httpCode;
    return $data;
}

// =====================================================
// TEST 1: Verificar estructura de BD
// =====================================================
echo "--- TEST 1: ESTRUCTURA DE BD ---\n";

$tablasRequeridas = [
    'roles', 'usuarios', 'usuario_roles', 'usuario_sucursales', 'sesiones',
    'pedidos', 'pedido_items', 'pedido_envio', 'pedido_envio_items',
    'recepciones', 'recepcion_items', 'stock_sucursal', 'stock_sucursal_movimientos'
];

$stmt = $db->query("SHOW TABLES");
$tablasExistentes = $stmt->fetchAll(PDO::FETCH_COLUMN);

foreach ($tablasRequeridas as $tabla) {
    test("Tabla '$tabla' existe", in_array($tabla, $tablasExistentes));
}

// Verificar columnas nuevas
$stmt = $db->query("SHOW COLUMNS FROM ubicaciones LIKE 'tipo_ubicacion'");
test("Columna 'tipo_ubicacion' en ubicaciones", $stmt->rowCount() > 0);

$stmt = $db->query("SHOW COLUMNS FROM productos LIKE 'disponible_franquicias'");
test("Columna 'disponible_franquicias' en productos", $stmt->rowCount() > 0);

echo "\n";

// =====================================================
// TEST 2: Verificar datos iniciales
// =====================================================
echo "--- TEST 2: DATOS INICIALES ---\n";

// Roles
$stmt = $db->query("SELECT COUNT(*) FROM roles");
test("Roles creados (5)", (int)$stmt->fetchColumn() === 5);

$stmt = $db->query("SELECT * FROM roles WHERE nombre = 'ADMIN'");
$admin = $stmt->fetch();
test("Rol ADMIN existe con nivel 10", $admin && $admin['nivel'] == 10);

// Usuario admin
$stmt = $db->query("SELECT * FROM usuarios WHERE us = 'admin'");
$usuario = $stmt->fetch();
test("Usuario 'admin' existe", $usuario !== false);
test("Usuario admin tiene id_roles = 1", $usuario && $usuario['id_roles'] == 1);
test("Password hasheado correctamente", $usuario && password_verify('admin123', $usuario['ps']));

echo "\n";

// =====================================================
// TEST 3: Modelo Usuario - Autenticación directa
// =====================================================
echo "--- TEST 3: MODELO USUARIO ---\n";

require_once 'api/src/Model/Usuario.php';
require_once 'api/src/Model/Sesion.php';

$usuarioModel = new \App\Model\Usuario($db);

// Test autenticar
$resultado = $usuarioModel->autenticar('admin', 'admin123');
test("Autenticación con credenciales correctas", $resultado !== false);
test("Autenticación devuelve id", isset($resultado['id']));
test("Autenticación devuelve rol_nombre", isset($resultado['rol_nombre']));

$resultadoFail = $usuarioModel->autenticar('admin', 'wrongpass');
test("Autenticación con password incorrecto falla", $resultadoFail === false);

$resultadoFail2 = $usuarioModel->autenticar('noexiste', 'admin123');
test("Autenticación con usuario inexistente falla", $resultadoFail2 === false);

echo "\n";

// =====================================================
// TEST 4: Modelo Sesion
// =====================================================
echo "--- TEST 4: MODELO SESION ---\n";

$sesionModel = new \App\Model\Sesion($db);

// Crear sesión
$token = $sesionModel->crear($resultado['id'], '127.0.0.1', 'Test Agent');
test("Crear sesión devuelve token", strlen($token) === 64);

// Validar sesión
$sesionValida = $sesionModel->validar($token);
test("Validar sesión activa", $sesionValida !== false);
test("Sesión contiene id_usuario", isset($sesionValida['id_usuario']));

// Validar token inválido
$sesionInvalida = $sesionModel->validar('token_invalido_123456');
test("Token inválido retorna false", $sesionInvalida === false);

echo "\n";

// =====================================================
// TEST 5: API - Login (si Apache está corriendo)
// =====================================================
echo "--- TEST 5: API ENDPOINTS ---\n";

// Verificar si Apache está corriendo
$testConnection = @file_get_contents('http://localhost/test/api/test');
if ($testConnection === false) {
    echo "⚠ Apache no está corriendo o la ruta no es accesible. Saltando tests de API.\n";
    echo "  Para probar la API, asegúrate de que Apache esté corriendo.\n\n";
} else {
    // Test endpoint /test
    $response = testApi('GET', '/test');
    test("GET /test responde", isset($response['status']) && $response['status'] === 'ok');

    // Test login
    $loginResponse = testApi('POST', '/auth/login', [
        'usuario' => 'admin',
        'password' => 'admin123'
    ]);
    test("POST /auth/login exitoso", !isset($loginResponse['error']) || $loginResponse['error'] === false);
    test("Login devuelve token", isset($loginResponse['token']));
    
    if (isset($loginResponse['token'])) {
        $apiToken = $loginResponse['token'];
        
        // Test /auth/me
        $meResponse = testApi('GET', '/auth/me', null, $apiToken);
        test("GET /auth/me con token válido", isset($meResponse['usuario']));
        
        // Test /auth/validar
        $validarResponse = testApi('GET', '/auth/validar', null, $apiToken);
        test("GET /auth/validar retorna valido=true", isset($validarResponse['valido']) && $validarResponse['valido'] === true);
        
        // Test endpoints protegidos
        $pedidosResponse = testApi('GET', '/pedidos', null, $apiToken);
        test("GET /pedidos con auth", $pedidosResponse['http_code'] === 200);
        
        $stockResponse = testApi('GET', '/stock-sucursal?id_sucursal=2', null, $apiToken);
        // Admin puede ver cualquier sucursal
        test("GET /stock-sucursal con auth", $stockResponse['http_code'] === 200 || isset($stockResponse['stock']));
        
        // Test sin token
        $sinTokenResponse = testApi('GET', '/pedidos');
        test("GET /pedidos sin token retorna 401", $sinTokenResponse['http_code'] === 401);
        
        // Test logout
        $logoutResponse = testApi('POST', '/auth/logout', null, $apiToken);
        test("POST /auth/logout exitoso", !isset($logoutResponse['error']) || $logoutResponse['error'] === false);
        
        // Verificar que el token ya no es válido después de logout
        $postLogoutResponse = testApi('GET', '/auth/validar', null, $apiToken);
        test("Token inválido después de logout", isset($postLogoutResponse['valido']) && $postLogoutResponse['valido'] === false);
    }
    
    // Test login con credenciales incorrectas
    $loginFailResponse = testApi('POST', '/auth/login', [
        'usuario' => 'admin',
        'password' => 'wrongpassword'
    ]);
    test("POST /auth/login con password incorrecto retorna error", isset($loginFailResponse['error']) && $loginFailResponse['error'] === true);
    test("Login fallido retorna 401", $loginFailResponse['http_code'] === 401);
}

echo "\n";

// =====================================================
// TEST 6: Crear usuario de franquicia para testing
// =====================================================
echo "--- TEST 6: CREAR USUARIO FRANQUICIA ---\n";

// Verificar si hay sucursales
$stmt = $db->query("SELECT id, nombre FROM ubicaciones WHERE tipo_ubicacion = 'sucursal' LIMIT 1");
$sucursal = $stmt->fetch();

if (!$sucursal) {
    echo "⚠ No hay sucursales en la BD. Creando una de prueba...\n";
    $db->exec("INSERT INTO ubicaciones (nombre, tipo_ubicacion) VALUES ('Sucursal Test', 'sucursal')");
    $stmt = $db->query("SELECT id, nombre FROM ubicaciones WHERE nombre = 'Sucursal Test'");
    $sucursal = $stmt->fetch();
}

test("Sucursal disponible para testing", $sucursal !== false);
echo "  Sucursal: {$sucursal['nombre']} (ID: {$sucursal['id']})\n";

// Crear usuario franquicia
$stmt = $db->query("SELECT id FROM usuarios WHERE us = 'franquicia_test'");
if (!$stmt->fetch()) {
    $passHash = password_hash('franquicia123', PASSWORD_DEFAULT);
    $db->exec("
        INSERT INTO usuarios (nombre, apellido, us, ps, activo, id_roles) 
        VALUES ('Usuario', 'Franquicia Test', 'franquicia_test', '$passHash', 1, 4)
    ");
    $idUsuarioFranquicia = $db->lastInsertId();
    
    // Asignar sucursal
    $db->exec("
        INSERT INTO usuario_sucursales (id_usuario, id_sucursal, es_sucursal_principal) 
        VALUES ($idUsuarioFranquicia, {$sucursal['id']}, 1)
    ");
    echo "  ✓ Usuario franquicia_test creado (pass: franquicia123)\n";
} else {
    echo "  Usuario franquicia_test ya existe\n";
}

// Verificar
$stmt = $db->query("SELECT u.*, r.nombre as rol FROM usuarios u JOIN roles r ON u.id_roles = r.id WHERE u.us = 'franquicia_test'");
$franquiciaUser = $stmt->fetch();
test("Usuario franquicia tiene rol FRANQUICIA_ADMIN", $franquiciaUser && $franquiciaUser['rol'] === 'FRANQUICIA_ADMIN');

echo "\n";

// =====================================================
// RESUMEN
// =====================================================
echo "=== RESUMEN DE TESTS ===\n";
echo "Exitosos: $exitos\n";
echo "Fallidos: $errores\n";
echo "Total: " . ($exitos + $errores) . "\n\n";

if ($errores > 0) {
    echo "⚠ HAY $errores TEST(S) FALLIDO(S)\n";
    exit(1);
} else {
    echo "✓ TODOS LOS TESTS PASARON\n";
}

echo "\n*** USUARIOS DE PRUEBA ***\n";
echo "Admin:      admin / admin123\n";
echo "Franquicia: franquicia_test / franquicia123\n";
echo "***************************\n";
