<?php
namespace App\Middleware;

use App\Model\Sesion;
use App\Model\Usuario;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Middleware de Autenticación
 * Verifica que el usuario tenga una sesión válida
 */
class AuthMiddleware implements MiddlewareInterface {
    private $db;
    private $nivelRequerido;
    private $verificarSucursal;

    /**
     * @param PDO $db - Conexión a BD
     * @param int $nivelRequerido - Nivel máximo de rol permitido (menor = más permisos)
     *                              null = cualquier usuario autenticado
     * @param bool $verificarSucursal - Si debe verificar acceso a sucursal
     */
    public function __construct($db, $nivelRequerido = null, $verificarSucursal = false) {
        $this->db = $db;
        $this->nivelRequerido = $nivelRequerido;
        $this->verificarSucursal = $verificarSucursal;
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface {
        $token = $this->obtenerToken($request);

        if (!$token) {
            return $this->respuestaNoAutorizado('Token no proporcionado');
        }

        // Validar sesión
        $sesionModel = new Sesion($this->db);
        $sesion = $sesionModel->validar($token);

        if (!$sesion) {
            return $this->respuestaNoAutorizado('Sesión inválida o expirada');
        }

        // Verificar nivel de rol si se especificó
        if ($this->nivelRequerido !== null && $sesion['rol_nivel'] > $this->nivelRequerido) {
            return $this->respuestaProhibido('No tiene permisos para esta acción');
        }

        // Verificar acceso a sucursal si se requiere
        if ($this->verificarSucursal) {
            $idSucursal = $this->obtenerIdSucursalDeRequest($request);
            if ($idSucursal) {
                $usuarioModel = new Usuario($this->db);
                if (!$usuarioModel->tieneAccesoSucursal($sesion['id_usuario'], $idSucursal)) {
                    return $this->respuestaProhibido('No tiene acceso a esta sucursal');
                }
            }
        }

        // Agregar información de sesión al request para uso en controladores
        $request = $request
            ->withAttribute('usuario_id', $sesion['id_usuario'])
            ->withAttribute('usuario_nombre', $sesion['nombre'])
            ->withAttribute('usuario_rol', $sesion['rol'])
            ->withAttribute('usuario_rol_nivel', $sesion['rol_nivel'])
            ->withAttribute('sesion', $sesion);

        return $handler->handle($request);
    }

    /**
     * Obtener token del request
     */
    private function obtenerToken(ServerRequestInterface $request): ?string {
        // Header Authorization
        $authHeader = $request->getHeaderLine('Authorization');
        if (!empty($authHeader)) {
            if (strpos($authHeader, 'Bearer ') === 0) {
                return substr($authHeader, 7);
            }
            return $authHeader;
        }

        // Cookie
        $cookies = $request->getCookieParams();
        if (!empty($cookies['mikelo_token'])) {
            return $cookies['mikelo_token'];
        }

        return null;
    }

    /**
     * Obtener ID de sucursal del request (de ruta o body)
     */
    private function obtenerIdSucursalDeRequest(ServerRequestInterface $request): ?int {
        // Desde ruta
        $route = $request->getAttribute('__route__');
        if ($route) {
            $args = $route->getArguments();
            if (isset($args['idSucursal'])) {
                return (int)$args['idSucursal'];
            }
        }

        // Desde body
        $body = $request->getParsedBody();
        if (isset($body['id_sucursal'])) {
            return (int)$body['id_sucursal'];
        }

        return null;
    }

    /**
     * Respuesta 401 No autorizado
     */
    private function respuestaNoAutorizado(string $mensaje): ResponseInterface {
        $response = new \Slim\Psr7\Response();
        $response->getBody()->write(json_encode([
            'error' => true,
            'mensaje' => $mensaje,
            'codigo' => 'NO_AUTORIZADO'
        ]));
        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus(401);
    }

    /**
     * Respuesta 403 Prohibido
     */
    private function respuestaProhibido(string $mensaje): ResponseInterface {
        $response = new \Slim\Psr7\Response();
        $response->getBody()->write(json_encode([
            'error' => true,
            'mensaje' => $mensaje,
            'codigo' => 'PROHIBIDO'
        ]));
        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus(403);
    }
}

/**
 * Función helper para crear middleware con nivel específico
 * Uso: $app->get('/ruta', ...)->add(authRequerido($db, 20));
 */
function authRequerido($db, $nivelRequerido = null, $verificarSucursal = false) {
    return new AuthMiddleware($db, $nivelRequerido, $verificarSucursal);
}

/**
 * Constantes de niveles de rol para uso en rutas
 * Referencia de roles:
 * - ADMIN (nivel 10): Acceso total
 * - PLANTA_JEFE (nivel 20): Depósito central, operaciones completas
 * - PLANTA_OPERARIO (nivel 25): Depósito central, operaciones limitadas
 * - FRANQUICIA_ADMIN (nivel 30): Administrador de sucursal
 * - FRANQUICIA_EMPLEADO (nivel 40): Empleado de sucursal
 */
class NivelRol {
    const ADMIN = 10;
    const PLANTA_JEFE = 20;
    const PLANTA_OPERARIO = 25;
    const FRANQUICIA_ADMIN = 30;
    const FRANQUICIA_EMPLEADO = 40;
    
    // Helpers para verificar nivel
    public static function esAdmin($nivel) {
        return $nivel <= self::ADMIN;
    }
    
    public static function esPlanta($nivel) {
        return $nivel <= self::PLANTA_OPERARIO;
    }
    
    public static function esFranquicia($nivel) {
        return $nivel >= self::FRANQUICIA_ADMIN;
    }
}
