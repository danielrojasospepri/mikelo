<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require __DIR__ . '/vendor/autoload.php';
require __DIR__ . '/comun.php';

use Slim\Factory\AppFactory;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use App\Controller\ProductoController;
use App\Controller\MovimientoController;
use App\Controller\UbicacionController;

$app = AppFactory::create();
$app->setBasePath('/mikelo/api');
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
    return $controller->buscarNuevos($request, $response);
});

$app->post('/movimientos', function (Request $request, Response $response) use ($db) {
    $controller = new MovimientoController($db);
    return $controller->crear($request, $response);
});

$app->post('/movimientos/{id}/items', function (Request $request, Response $response, $args) use ($db) {
    $controller = new MovimientoController($db);
    return $controller->agregarItem($request, $response, $args);
});

$app->run();
