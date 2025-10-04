<?php

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Configurar zona horaria para Buenos Aires aaaaaaaaaa
date_default_timezone_set('America/Argentina/Buenos_Aires');

require __DIR__ . '/vendor/autoload.php';
require __DIR__ . '/comun.php';

use Slim\Factory\AppFactory;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use App\Controller\ProductoController;
use App\Controller\MovimientoController;
use App\Controller\UbicacionController;
use App\Controller\EnvioController;
use App\Controller\StockDepositoController;

$app = AppFactory::create();
$app->setBasePath('/mikelo/api');
// $app->setBasePath('/mikelo/api');
$app->addBodyParsingMiddleware();

// Agregar middleware para debug de rutas
$app->add(function ($request, $handler) {
    error_log('Request path: ' . $request->getUri()->getPath());
    error_log('Request method: ' . $request->getMethod());
    return $handler->handle($request);
});

// Configuración detallada del middleware de errores
$errorMiddleware = $app->addErrorMiddleware(true, true, true);
$errorHandler = $errorMiddleware->getDefaultErrorHandler();
$errorHandler->forceContentType('application/json');

// Agregar middleware para manejar errores de manera más detallada
$app->add(function ($request, $handler) {
    try {
        return $handler->handle($request);
    } catch (\Exception $e) {
        error_log($e->getMessage());
        $response = new \Slim\Psr7\Response();
        $response->getBody()->write(json_encode([
            'error' => true,
            'message' => $e->getMessage(),
            'trace' => $e->getTraceAsString()
        ]));
        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus(500);
    }
});

// Middleware para CORS
$app->add(function (Request $request, $handler) {
    $response = $handler->handle($request);
    return $response
        ->withHeader('Access-Control-Allow-Origin', '*')
        ->withHeader('Access-Control-Allow-Headers', 'X-Requested-With, Content-Type, Accept, Origin, Authorization')
        ->withHeader('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, PATCH, OPTIONS');
});

// Obtener instancia de DB
$db = getDB();

// Ruta de prueba
$app->get('/test', function (Request $request, Response $response) {
    $response->getBody()->write(json_encode(['status' => 'ok']));
    return $response->withHeader('Content-Type', 'application/json');
});

// Rutas
$app->get('/ubicaciones', function (Request $request, Response $response) use ($db) {
    $controller = new UbicacionController($db);
    return $controller->listar($request, $response);
});

$app->get('/productos/buscar', function (Request $request, Response $response) use ($db) {
    $controller = new ProductoController($db);
    return $controller->buscar($request, $response);
});

$app->get('/productos/nuevos', function (Request $request, Response $response) use ($db) {
    $controller = new ProductoController($db);
    return $controller->buscar($request, $response);
});

$app->get('/contenedores', function (Request $request, Response $response) use ($db) {
    $sql = "SELECT id, nombre, peso FROM contenedores ORDER BY nombre";
    $stmt = $db->prepare($sql);
    $stmt->execute();
    $contenedores = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $response->getBody()->write(json_encode([
        'success' => true,
        'data' => $contenedores
    ]));
    return $response->withHeader('Content-Type', 'application/json');
});

$app->post('/movimientos', function (Request $request, Response $response) use ($db) {
    $controller = new MovimientoController($db);
    return $controller->crear($request, $response);
});

$app->post('/movimientos/{id}/items', function (Request $request, Response $response, $args) use ($db) {
    $controller = new MovimientoController($db);
    return $controller->agregarItem($request, $response, $args);
});

// Nueva ruta para obtener movimientos por fecha para el depósito
$app->get('/movimientos/deposito/{fecha}', function (Request $request, Response $response, $args) use ($db) {
    $controller = new MovimientoController($db);
    return $controller->obtenerMovimientosDeposito($request, $response, $args);
});

// Nueva ruta para búsqueda de movimientos con filtros
$app->get('/movimientos/buscar', function (Request $request, Response $response) use ($db) {
    $controller = new MovimientoController($db);
    return $controller->buscarMovimientos($request, $response);
});

// Ruta para verificar duplicados
$app->post('/movimientos/verificar-duplicado', function (Request $request, Response $response) use ($db) {
    $controller = new MovimientoController($db);
    return $controller->verificarDuplicado($request, $response);
});

// Rutas de exportación para movimientos
$app->get('/movimientos/pdf', function (Request $request, Response $response) use ($db) {
    $controller = new MovimientoController($db);
    return $controller->exportarPDF($request, $response);
});

$app->get('/movimientos/excel', function (Request $request, Response $response) use ($db) {
    $controller = new MovimientoController($db);
    return $controller->exportarExcel($request, $response);
});

// Rutas para stock de depósito
$app->get('/stock-deposito', function (Request $request, Response $response) use ($db) {
    $controller = new StockDepositoController($db);
    return $controller->obtenerStock($request, $response);
});

$app->get('/stock-deposito/pdf', function (Request $request, Response $response) use ($db) {
    $controller = new StockDepositoController($db);
    return $controller->exportarPDF($request, $response);
});

$app->get('/stock-deposito/excel', function (Request $request, Response $response) use ($db) {
    $controller = new StockDepositoController($db);
    return $controller->exportarExcel($request, $response);
});

$app->get('/stock-deposito/{id}/bandejas', function (Request $request, Response $response, $args) use ($db) {
    $controller = new StockDepositoController($db);
    return $controller->obtenerDetalleBandejas($request, $response, $args);
});

$app->post('/stock-deposito/cambiar-contenedor', function (Request $request, Response $response) use ($db) {
    $controller = new StockDepositoController($db);
    return $controller->cambiarContenedor($request, $response);
});

$app->post('/stock-deposito/dar-baja', function (Request $request, Response $response) use ($db) {
    $controller = new StockDepositoController($db);
    return $controller->darDeBaja($request, $response);
});

// Rutas para envíos
// Primero las rutas estáticas
$app->get('/envios/productos-disponibles', function (Request $request, Response $response) use ($db) {
    $controller = new EnvioController($db);
    return $controller->obtenerProductosDisponibles($request, $response);
});

$app->get('/envios/contenedores', function (Request $request, Response $response) use ($db) {
    $controller = new EnvioController($db);
    return $controller->obtenerContenedores($request, $response);
});

$app->get('/envios/pdf', function (Request $request, Response $response) use ($db) {
    $controller = new EnvioController($db);
    return $controller->exportarPDF($request, $response);
});

$app->get('/envios/excel', function (Request $request, Response $response) use ($db) {
    $controller = new EnvioController($db);
    return $controller->exportarExcel($request, $response);
});

// Luego las rutas básicas
$app->post('/envios', function (Request $request, Response $response) use ($db) {
    $controller = new EnvioController($db);
    return $controller->crear($request, $response);
});

$app->get('/envios', function (Request $request, Response $response) use ($db) {
    $controller = new EnvioController($db);
    return $controller->listar($request, $response);
});

// Finalmente las rutas con parámetros variables
$app->get('/envios/{id}', function (Request $request, Response $response, $args) use ($db) {
    $controller = new EnvioController($db);
    return $controller->obtenerDetalle($request, $response, $args);
});

$app->get('/envios/{id}/pdf', function (Request $request, Response $response, $args) use ($db) {
    $controller = new EnvioController($db);
    return $controller->exportarPDF($request, $response, $args);
});

$app->get('/envios/{id}/excel', function (Request $request, Response $response, $args) use ($db) {
    $controller = new EnvioController($db);
    return $controller->exportarExcel($request, $response, $args);
});

// Rutas para confirmar y cancelar envíos
$app->put('/envios/{id}/confirmar', function (Request $request, Response $response, $args) use ($db) {
    $controller = new EnvioController($db);
    return $controller->confirmarEnvio($request, $response, $args);
});

$app->put('/envios/{id}/cancelar', function (Request $request, Response $response, $args) use ($db) {
    $controller = new EnvioController($db);
    return $controller->cancelarEnvio($request, $response, $args);
});

$app->run();
