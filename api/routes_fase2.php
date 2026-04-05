<?php
/**
 * RUTAS FASE 2 - Mikelo
 * 
 * Este archivo contiene las rutas para las nuevas funcionalidades:
 * - Autenticación (login/logout/sesiones)
 * - Pedidos de sucursales
 * - Recepciones en sucursales
 * - Stock de sucursales
 * 
 * Para habilitar: incluir este archivo desde api/index.php
 * require __DIR__ . '/routes_fase2.php';
 * 
 * Para deshabilitar: comentar o eliminar esa línea
 * 
 * @version 1.0
 * @date 2025-06
 */

use App\Controller\AuthController;
use App\Controller\PedidoController;
use App\Controller\ProductoController;
use App\Controller\RecepcionController;
use App\Controller\StockSucursalController;
use App\Controller\UbicacionController;
use App\Middleware\AuthMiddleware;
use App\Middleware\NivelRol;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

// Asegurar que $app y $db existen
if (!isset($app) || !isset($db)) {
    throw new \Exception('routes_fase2.php debe ser incluido después de inicializar $app y $db');
}

// ============================================================================
// RUTAS DE AUTENTICACIÓN (públicas, sin middleware de auth)
// ============================================================================

$app->post('/auth/login', function (Request $request, Response $response) use ($db) {
    $controller = new AuthController($db);
    return $controller->login($request, $response);
});

$app->post('/auth/logout', function (Request $request, Response $response) use ($db) {
    $controller = new AuthController($db);
    return $controller->logout($request, $response);
});

$app->get('/auth/validar', function (Request $request, Response $response) use ($db) {
    $controller = new AuthController($db);
    return $controller->validar($request, $response);
});

$app->get('/auth/me', function (Request $request, Response $response) use ($db) {
    $controller = new AuthController($db);
    return $controller->me($request, $response);
});

$app->post('/auth/cambiar-password', function (Request $request, Response $response) use ($db) {
    $controller = new AuthController($db);
    return $controller->cambiarPassword($request, $response);
});

// ============================================================================
// RUTAS DE PEDIDOS (requieren autenticación)
// ============================================================================

// Rutas estáticas primero
$app->get('/pedidos/pendientes', function (Request $request, Response $response) use ($db) {
    $controller = new PedidoController($db);
    return $controller->pendientes($request, $response);
})->add(new AuthMiddleware($db, NivelRol::PLANTA_OPERARIO));

$app->get('/pedidos/contadores', function (Request $request, Response $response) use ($db) {
    $controller = new PedidoController($db);
    return $controller->contadores($request, $response);
})->add(new AuthMiddleware($db, NivelRol::PLANTA_OPERARIO));

$app->get('/pedidos/demanda-agregada', function (Request $request, Response $response) use ($db) {
    $controller = new PedidoController($db);
    return $controller->demandaAgregada($request, $response);
})->add(new AuthMiddleware($db, NivelRol::PLANTA_OPERARIO));

$app->get('/pedidos/productos-disponibles', function (Request $request, Response $response) use ($db) {
    $controller = new PedidoController($db);
    return $controller->productosDisponibles($request, $response);
})->add(new AuthMiddleware($db));

// Rutas CRUD
$app->get('/pedidos', function (Request $request, Response $response) use ($db) {
    $controller = new PedidoController($db);
    return $controller->listar($request, $response);
})->add(new AuthMiddleware($db));

$app->post('/pedidos', function (Request $request, Response $response) use ($db) {
    $controller = new PedidoController($db);
    return $controller->crear($request, $response);
})->add(new AuthMiddleware($db, NivelRol::FRANQUICIA_EMPLEADO)); // Solo franquicias

// Rutas con parámetros al final
$app->get('/pedidos/{id}', function (Request $request, Response $response, $args) use ($db) {
    $controller = new PedidoController($db);
    return $controller->obtener($request, $response, $args);
})->add(new AuthMiddleware($db));

$app->put('/pedidos/{id}/enviar', function (Request $request, Response $response, $args) use ($db) {
    $controller = new PedidoController($db);
    return $controller->enviar($request, $response, $args);
})->add(new AuthMiddleware($db, NivelRol::PLANTA_OPERARIO)); // Solo planta

$app->put('/pedidos/{id}/anular', function (Request $request, Response $response, $args) use ($db) {
    $controller = new PedidoController($db);
    return $controller->anular($request, $response, $args);
})->add(new AuthMiddleware($db));

$app->put('/pedidos/{id}/recibir', function (Request $request, Response $response, $args) use ($db) {
    $controller = new PedidoController($db);
    return $controller->recibir($request, $response, $args);
})->add(new AuthMiddleware($db));

// ============================================================================
// RUTAS DE RECEPCIONES (requieren autenticación)
// ============================================================================

// Rutas estáticas primero
$app->get('/recepciones/envios-pendientes', function (Request $request, Response $response) use ($db) {
    $controller = new RecepcionController($db);
    return $controller->enviosPendientes($request, $response);
})->add(new AuthMiddleware($db));

// Rutas CRUD
$app->get('/recepciones', function (Request $request, Response $response) use ($db) {
    $controller = new RecepcionController($db);
    return $controller->listar($request, $response);
})->add(new AuthMiddleware($db));

$app->post('/recepciones', function (Request $request, Response $response) use ($db) {
    $controller = new RecepcionController($db);
    return $controller->confirmar($request, $response);
})->add(new AuthMiddleware($db));

// Rutas con parámetros
$app->get('/recepciones/envio/{idEnvio}', function (Request $request, Response $response, $args) use ($db) {
    $controller = new RecepcionController($db);
    return $controller->detalleEnvio($request, $response, $args);
})->add(new AuthMiddleware($db));

$app->post('/recepciones/archivar/{idEnvio}', function (Request $request, Response $response, $args) use ($db) {
    $controller = new RecepcionController($db);
    return $controller->archivar($request, $response, $args);
})->add(new AuthMiddleware($db));

$app->get('/recepciones/archivados', function (Request $request, Response $response) use ($db) {
    $controller = new RecepcionController($db);
    return $controller->archivados($request, $response);
})->add(new AuthMiddleware($db));

$app->post('/recepciones/desarchivar/{idEnvio}', function (Request $request, Response $response, $args) use ($db) {
    $controller = new RecepcionController($db);
    return $controller->desarchivar($request, $response, $args);
})->add(new AuthMiddleware($db));

$app->get('/recepciones/{id}', function (Request $request, Response $response, $args) use ($db) {
    $controller = new RecepcionController($db);
    return $controller->obtener($request, $response, $args);
})->add(new AuthMiddleware($db));

// ============================================================================
// RUTAS DE STOCK DE SUCURSALES (requieren autenticación)
// ============================================================================

// Rutas estáticas primero
$app->get('/stock-sucursal/buscar', function (Request $request, Response $response) use ($db) {
    $controller = new StockSucursalController($db);
    return $controller->buscar($request, $response);
})->add(new AuthMiddleware($db));

$app->get('/stock-sucursal/resumen', function (Request $request, Response $response) use ($db) {
    $controller = new StockSucursalController($db);
    return $controller->resumen($request, $response);
})->add(new AuthMiddleware($db));

$app->get('/stock-sucursal/historial', function (Request $request, Response $response) use ($db) {
    $controller = new StockSucursalController($db);
    return $controller->historial($request, $response);
})->add(new AuthMiddleware($db));

$app->get('/stock-sucursal/todas', function (Request $request, Response $response) use ($db) {
    $controller = new StockSucursalController($db);
    return $controller->stockTodas($request, $response);
})->add(new AuthMiddleware($db, NivelRol::PLANTA_OPERARIO)); // Solo planta/admin

// Ruta básica
$app->get('/stock-sucursal', function (Request $request, Response $response) use ($db) {
    $controller = new StockSucursalController($db);
    return $controller->obtenerStock($request, $response);
})->add(new AuthMiddleware($db));

// Bajas de stock (FRANQUICIA_EMPLEADO puede registrar ventas/mermas)
$app->post('/stock-sucursal/baja', function (Request $request, Response $response) use ($db) {
    $controller = new StockSucursalController($db);
    return $controller->registrarBaja($request, $response);
})->add(new AuthMiddleware($db, NivelRol::FRANQUICIA_EMPLEADO));

// Baja de bandeja por escaneo de código de barras (valida contra recepcion_items, previene doble-baja)
$app->post('/stock-sucursal/baja-barcode', function (Request $request, Response $response) use ($db) {
    $controller = new StockSucursalController($db);
    return $controller->registrarBajaBarcode($request, $response);
})->add(new AuthMiddleware($db, NivelRol::FRANQUICIA_EMPLEADO));

// Baja de múltiples bandejas seleccionadas manualmente (valida recepcion_item_ids)
$app->post('/stock-sucursal/baja-bandejas', function (Request $request, Response $response) use ($db) {
    $controller = new StockSucursalController($db);
    return $controller->registrarBajaBandejas($request, $response);
})->add(new AuthMiddleware($db, NivelRol::FRANQUICIA_EMPLEADO));

// Ajustes de stock (FRANQUICIA_ADMIN puede hacer inventario)
$app->post('/stock-sucursal/ajuste', function (Request $request, Response $response) use ($db) {
    $controller = new StockSucursalController($db);
    return $controller->registrarAjuste($request, $response);
})->add(new AuthMiddleware($db, NivelRol::FRANQUICIA_ADMIN));

// Carga inicial de stock (FRANQUICIA_ADMIN lleva el inventario inicial)
$app->get('/stock-sucursal/carga-inicial', function (Request $request, Response $response) use ($db) {
    $controller = new StockSucursalController($db);
    return $controller->obtenerProductosCargaInicial($request, $response);
})->add(new AuthMiddleware($db, NivelRol::FRANQUICIA_ADMIN));

$app->post('/stock-sucursal/carga-inicial', function (Request $request, Response $response) use ($db) {
    $controller = new StockSucursalController($db);
    return $controller->cargaInicial($request, $response);
})->add(new AuthMiddleware($db, NivelRol::FRANQUICIA_ADMIN));

// Rutas con parámetros
$app->get('/stock-sucursal/bandejas/{idProducto}', function (Request $request, Response $response, $args) use ($db) {
    $controller = new StockSucursalController($db);
    return $controller->obtenerBandejas($request, $response, $args);
})->add(new AuthMiddleware($db));

$app->get('/stock-sucursal/producto/{idProducto}', function (Request $request, Response $response, $args) use ($db) {
    $controller = new StockSucursalController($db);
    return $controller->stockProducto($request, $response, $args);
})->add(new AuthMiddleware($db));

// ============================================================================
// RUTAS DE STOCK MÍNIMO (configuración por sucursal)
// ============================================================================
use App\Controller\StockMinimoController;

// Rutas estáticas primero
$app->get('/stock-minimo/faltantes', function (Request $request, Response $response) use ($db) {
    $controller = new StockMinimoController($db);
    return $controller->faltantes($request, $response);
})->add(new AuthMiddleware($db));

$app->get('/stock-minimo/resumen', function (Request $request, Response $response) use ($db) {
    $controller = new StockMinimoController($db);
    return $controller->resumen($request, $response);
})->add(new AuthMiddleware($db, NivelRol::PLANTA_OPERARIO)); // Solo planta/admin

$app->post('/stock-minimo/multiple', function (Request $request, Response $response) use ($db) {
    $controller = new StockMinimoController($db);
    return $controller->configurarMultiple($request, $response);
})->add(new AuthMiddleware($db, NivelRol::FRANQUICIA_ADMIN));

// Rutas CRUD básicas
$app->get('/stock-minimo', function (Request $request, Response $response) use ($db) {
    $controller = new StockMinimoController($db);
    return $controller->listar($request, $response);
})->add(new AuthMiddleware($db));

$app->post('/stock-minimo', function (Request $request, Response $response) use ($db) {
    $controller = new StockMinimoController($db);
    return $controller->configurar($request, $response);
})->add(new AuthMiddleware($db, NivelRol::FRANQUICIA_ADMIN));

// Rutas con parámetros al final
$app->delete('/stock-minimo/{id}', function (Request $request, Response $response, $args) use ($db) {
    $controller = new StockMinimoController($db);
    return $controller->eliminar($request, $response, $args);
})->add(new AuthMiddleware($db, NivelRol::FRANQUICIA_ADMIN));

// ============================================================================
// RUTAS DE USUARIOS (solo admin)
// ============================================================================

$app->get('/usuarios', function (Request $request, Response $response) use ($db) {
    try {
        $usuarioModel = new \App\Model\Usuario($db);
        $filtros = $request->getQueryParams();
        $usuarios = $usuarioModel->listar($filtros);
        
        $response->getBody()->write(json_encode([
            'error' => false,
            'usuarios' => $usuarios
        ]));
        return $response->withHeader('Content-Type', 'application/json');
    } catch (\Exception $e) {
        $response->getBody()->write(json_encode([
            'error' => true,
            'mensaje' => $e->getMessage()
        ]));
        return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
    }
})->add(new AuthMiddleware($db, NivelRol::ADMIN));

$app->get('/usuarios/{id}', function (Request $request, Response $response, $args) use ($db) {
    try {
        $usuarioModel = new \App\Model\Usuario($db);
        $usuario = $usuarioModel->obtenerPorId($args['id']);
        
        if (!$usuario) {
            $response->getBody()->write(json_encode([
                'error' => true,
                'mensaje' => 'Usuario no encontrado'
            ]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(404);
        }
        
        $response->getBody()->write(json_encode([
            'error' => false,
            'usuario' => $usuario
        ]));
        return $response->withHeader('Content-Type', 'application/json');
    } catch (\Exception $e) {
        $response->getBody()->write(json_encode([
            'error' => true,
            'mensaje' => $e->getMessage()
        ]));
        return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
    }
})->add(new AuthMiddleware($db, NivelRol::ADMIN));

$app->post('/usuarios', function (Request $request, Response $response) use ($db) {
    try {
        $datos = $request->getParsedBody();
        
        // Validaciones básicas
        if (empty($datos['nombre']) || empty($datos['usuario']) || empty($datos['password']) || empty($datos['id_rol'])) {
            $response->getBody()->write(json_encode([
                'error' => true,
                'mensaje' => 'Nombre, usuario, password y rol son requeridos'
            ]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
        }
        
        // Obtener ID del usuario autenticado
        $token = str_replace('Bearer ', '', $request->getHeaderLine('Authorization'));
        $sesionModel = new \App\Model\Sesion($db);
        $sesion = $sesionModel->validar($token);
        $datos['creado_por'] = $sesion ? $sesion['id_usuario'] : null;
        
        $usuarioModel = new \App\Model\Usuario($db);
        $idUsuario = $usuarioModel->crear($datos);
        
        $response->getBody()->write(json_encode([
            'error' => false,
            'mensaje' => 'Usuario creado exitosamente',
            'id' => $idUsuario
        ]));
        return $response->withHeader('Content-Type', 'application/json');
    } catch (\Exception $e) {
        $response->getBody()->write(json_encode([
            'error' => true,
            'mensaje' => $e->getMessage()
        ]));
        return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
    }
})->add(new AuthMiddleware($db, NivelRol::ADMIN));

$app->put('/usuarios/{id}', function (Request $request, Response $response, $args) use ($db) {
    try {
        $datos = $request->getParsedBody();
        $usuarioModel = new \App\Model\Usuario($db);
        
        $usuarioModel->actualizar($args['id'], $datos);
        
        // Actualizar sucursales si se proporcionan
        if (isset($datos['sucursales'])) {
            // Eliminar asignaciones actuales
            $stmt = $db->prepare("DELETE FROM usuario_sucursales WHERE id_usuario = ?");
            $stmt->execute([$args['id']]);
            
            // Agregar nuevas
            foreach ($datos['sucursales'] as $idSucursal) {
                $usuarioModel->asignarSucursal($args['id'], $idSucursal);
            }
        }
        
        $response->getBody()->write(json_encode([
            'error' => false,
            'mensaje' => 'Usuario actualizado exitosamente'
        ]));
        return $response->withHeader('Content-Type', 'application/json');
    } catch (\Exception $e) {
        $response->getBody()->write(json_encode([
            'error' => true,
            'mensaje' => $e->getMessage()
        ]));
        return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
    }
})->add(new AuthMiddleware($db, NivelRol::ADMIN));

$app->delete('/usuarios/{id}', function (Request $request, Response $response, $args) use ($db) {
    try {
        $usuarioModel = new \App\Model\Usuario($db);
        $usuarioModel->desactivar($args['id']);
        
        $response->getBody()->write(json_encode([
            'error' => false,
            'mensaje' => 'Usuario desactivado exitosamente'
        ]));
        return $response->withHeader('Content-Type', 'application/json');
    } catch (\Exception $e) {
        $response->getBody()->write(json_encode([
            'error' => true,
            'mensaje' => $e->getMessage()
        ]));
        return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
    }
})->add(new AuthMiddleware($db, NivelRol::ADMIN));

$app->put('/usuarios/{id}/activar', function (Request $request, Response $response, $args) use ($db) {
    try {
        $usuarioModel = new \App\Model\Usuario($db);
        $usuarioModel->activar($args['id']);
        
        $response->getBody()->write(json_encode([
            'error' => false,
            'mensaje' => 'Usuario activado exitosamente'
        ]));
        return $response->withHeader('Content-Type', 'application/json');
    } catch (\Exception $e) {
        $response->getBody()->write(json_encode([
            'error' => true,
            'mensaje' => $e->getMessage()
        ]));
        return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
    }
})->add(new AuthMiddleware($db, NivelRol::ADMIN));

$app->put('/usuarios/{id}/password', function (Request $request, Response $response, $args) use ($db) {
    try {
        $datos = $request->getParsedBody();
        
        if (empty($datos['password'])) {
            $response->getBody()->write(json_encode([
                'error' => true,
                'mensaje' => 'Password requerido'
            ]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
        }
        
        $hash = password_hash($datos['password'], PASSWORD_DEFAULT);
        $stmt = $db->prepare("UPDATE usuarios SET ps = ? WHERE id = ?");
        $stmt->execute([$hash, $args['id']]);
        
        $response->getBody()->write(json_encode([
            'error' => false,
            'mensaje' => 'Password actualizado exitosamente'
        ]));
        return $response->withHeader('Content-Type', 'application/json');
    } catch (\Exception $e) {
        $response->getBody()->write(json_encode([
            'error' => true,
            'mensaje' => $e->getMessage()
        ]));
        return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
    }
})->add(new AuthMiddleware($db, NivelRol::ADMIN));

$app->get('/roles', function (Request $request, Response $response) use ($db) {
    try {
        $stmt = $db->prepare("SELECT id, nombre, nivel, descripcion FROM roles ORDER BY nivel");
        $stmt->execute();
        $roles = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        
        $response->getBody()->write(json_encode([
            'error' => false,
            'roles' => $roles
        ]));
        return $response->withHeader('Content-Type', 'application/json');
    } catch (\Exception $e) {
        $response->getBody()->write(json_encode([
            'error' => true,
            'mensaje' => $e->getMessage()
        ]));
        return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
    }
});

// ============================================================================
// ============================================================================
// SUCURSALES ABM (GET público para selects; POST/PUT solo admin)
// ============================================================================

$app->get('/sucursales', function (Request $request, Response $response) use ($db) {
    $controller = new UbicacionController($db);
    return $controller->listarSucursales($request, $response);
})->add(new AuthMiddleware($db));

$app->post('/sucursales', function (Request $request, Response $response) use ($db) {
    $controller = new UbicacionController($db);
    return $controller->crearSucursal($request, $response);
})->add(new AuthMiddleware($db, NivelRol::ADMIN));

// Rutas con parámetro al final
$app->get('/sucursales/{id}', function (Request $request, Response $response, $args) use ($db) {
    $controller = new UbicacionController($db);
    return $controller->obtenerSucursal($request, $response, $args);
})->add(new AuthMiddleware($db, NivelRol::ADMIN));

$app->put('/sucursales/{id}', function (Request $request, Response $response, $args) use ($db) {
    $controller = new UbicacionController($db);
    return $controller->actualizarSucursal($request, $response, $args);
})->add(new AuthMiddleware($db, NivelRol::ADMIN));

// ============================================================================
// RUTAS ABM PRODUCTOS (requieren autenticación, planta jefe)
// ============================================================================

$app->get('/productos', function (Request $request, Response $response) use ($db) {
    $controller = new ProductoController($db);
    return $controller->listar($request, $response);
})->add(new AuthMiddleware($db));

$app->post('/productos', function (Request $request, Response $response) use ($db) {
    $controller = new ProductoController($db);
    return $controller->crear($request, $response);
})->add(new AuthMiddleware($db));

$app->put('/productos/{id}', function (Request $request, Response $response, $args) use ($db) {
    $controller = new ProductoController($db);
    return $controller->actualizar($request, $response, $args);
})->add(new AuthMiddleware($db));

$app->delete('/productos/{id}', function (Request $request, Response $response, $args) use ($db) {
    $controller = new ProductoController($db);
    return $controller->eliminar($request, $response, $args);
})->add(new AuthMiddleware($db));

// ============================================================================
// RUTAS ABM FAMILIAS (tipo_producto) (requieren autenticación)
// ============================================================================

$app->get('/familias', function (Request $request, Response $response) use ($db) {
    $controller = new ProductoController($db);
    return $controller->listarFamilias($request, $response);
})->add(new AuthMiddleware($db));

$app->post('/familias', function (Request $request, Response $response) use ($db) {
    $controller = new ProductoController($db);
    return $controller->crearFamilia($request, $response);
})->add(new AuthMiddleware($db));

$app->put('/familias/{id}', function (Request $request, Response $response, $args) use ($db) {
    $controller = new ProductoController($db);
    return $controller->actualizarFamilia($request, $response, $args);
})->add(new AuthMiddleware($db));

$app->delete('/familias/{id}', function (Request $request, Response $response, $args) use ($db) {
    $controller = new ProductoController($db);
    return $controller->eliminarFamilia($request, $response, $args);
})->add(new AuthMiddleware($db));
