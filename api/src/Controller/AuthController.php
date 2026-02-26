<?php
namespace App\Controller;

use App\Model\Usuario;
use App\Model\Sesion;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

/**
 * Controlador de Autenticación
 * Maneja login, logout y validación de sesiones
 */
class AuthController {
    private $db;

    public function __construct($db) {
        $this->db = $db;
    }

    /**
     * POST /auth/login
     * Iniciar sesión
     */
    public function login(Request $request, Response $response): Response {
        try {
            $datos = $request->getParsedBody();

            // Validar datos requeridos
            if (empty($datos['usuario']) || empty($datos['password'])) {
                return $this->jsonResponse($response, [
                    'error' => true,
                    'mensaje' => 'Usuario y contraseña son requeridos'
                ], 400);
            }

            $usuarioModel = new Usuario($this->db);
            $usuario = $usuarioModel->autenticar($datos['usuario'], $datos['password']);

            if (!$usuario) {
                return $this->jsonResponse($response, [
                    'error' => true,
                    'mensaje' => 'Credenciales inválidas'
                ], 401);
            }

            // Crear sesión
            $sesionModel = new Sesion($this->db);
            $ip = $request->getServerParams()['REMOTE_ADDR'] ?? null;
            $userAgent = $request->getHeaderLine('User-Agent');
            $token = $sesionModel->crear($usuario['id'], $ip, $userAgent);

            // Preparar respuesta
            return $this->jsonResponse($response, [
                'error' => false,
                'mensaje' => 'Login exitoso',
                'token' => $token,
                'usuario' => [
                    'id' => $usuario['id'],
                    'nombre' => $usuario['nombre'],
                    'apellido' => $usuario['apellido'] ?? '',
                    'usuario' => $usuario['us'] ?? $datos['usuario'],
                    'email' => $usuario['email'] ?? null,
                    'rol' => $usuario['rol_nombre'],
                    'rol_nivel' => $usuario['rol_nivel'],
                    'sucursales' => $usuario['sucursales'] ?? []
                ]
            ]);

        } catch (\Exception $e) {
            error_log("Error en login: " . $e->getMessage());
            return $this->jsonResponse($response, [
                'error' => true,
                'mensaje' => 'Error interno del servidor'
            ], 500);
        }
    }

    /**
     * POST /auth/logout
     * Cerrar sesión
     */
    public function logout(Request $request, Response $response): Response {
        try {
            $token = $this->obtenerTokenDeRequest($request);

            if (!$token) {
                return $this->jsonResponse($response, [
                    'error' => true,
                    'mensaje' => 'Token no proporcionado'
                ], 400);
            }

            $sesionModel = new Sesion($this->db);
            $sesionModel->cerrar($token);

            return $this->jsonResponse($response, [
                'error' => false,
                'mensaje' => 'Sesión cerrada correctamente'
            ]);

        } catch (\Exception $e) {
            error_log("Error en logout: " . $e->getMessage());
            return $this->jsonResponse($response, [
                'error' => true,
                'mensaje' => 'Error al cerrar sesión'
            ], 500);
        }
    }

    /**
     * GET /auth/me
     * Obtener información del usuario actual
     */
    public function me(Request $request, Response $response): Response {
        try {
            $token = $this->obtenerTokenDeRequest($request);

            if (!$token) {
                return $this->jsonResponse($response, [
                    'error' => true,
                    'mensaje' => 'No autenticado'
                ], 401);
            }

            $sesionModel = new Sesion($this->db);
            $sesion = $sesionModel->validar($token);

            if (!$sesion) {
                return $this->jsonResponse($response, [
                    'error' => true,
                    'mensaje' => 'Sesión inválida o expirada'
                ], 401);
            }

            // Obtener datos completos del usuario
            $usuarioModel = new Usuario($this->db);
            $usuario = $usuarioModel->obtenerPorId($sesion['id_usuario']);

            return $this->jsonResponse($response, [
                'error' => false,
                'usuario' => [
                    'id' => $usuario['id'],
                    'nombre' => $usuario['nombre'],
                    'apellido' => $usuario['apellido'] ?? '',
                    'usuario' => $usuario['usuario'],
                    'email' => $usuario['email'] ?? null,
                    'rol' => $usuario['rol_nombre'],
                    'rol_nivel' => $usuario['rol_nivel'],
                    'sucursales' => $usuario['sucursales'] ?? []
                ],
                'sesion' => [
                    'expira_en' => $sesion['expira_en']
                ]
            ]);

        } catch (\Exception $e) {
            error_log("Error en /auth/me: " . $e->getMessage());
            return $this->jsonResponse($response, [
                'error' => true,
                'mensaje' => 'Error interno del servidor'
            ], 500);
        }
    }

    /**
     * POST /auth/cambiar-password
     * Cambiar contraseña del usuario actual
     */
    public function cambiarPassword(Request $request, Response $response): Response {
        try {
            $token = $this->obtenerTokenDeRequest($request);
            $datos = $request->getParsedBody();

            if (!$token) {
                return $this->jsonResponse($response, [
                    'error' => true,
                    'mensaje' => 'No autenticado'
                ], 401);
            }

            // Validar datos
            if (empty($datos['password_actual']) || empty($datos['password_nuevo'])) {
                return $this->jsonResponse($response, [
                    'error' => true,
                    'mensaje' => 'Password actual y nuevo son requeridos'
                ], 400);
            }

            if (strlen($datos['password_nuevo']) < 6) {
                return $this->jsonResponse($response, [
                    'error' => true,
                    'mensaje' => 'El nuevo password debe tener al menos 6 caracteres'
                ], 400);
            }

            // Validar sesión
            $sesionModel = new Sesion($this->db);
            $sesion = $sesionModel->validar($token);

            if (!$sesion) {
                return $this->jsonResponse($response, [
                    'error' => true,
                    'mensaje' => 'Sesión inválida o expirada'
                ], 401);
            }

            // Cambiar password
            $usuarioModel = new Usuario($this->db);
            $usuarioModel->cambiarPassword(
                $sesion['id_usuario'],
                $datos['password_actual'],
                $datos['password_nuevo']
            );

            // Cerrar otras sesiones del usuario (opcional, por seguridad)
            if (!empty($datos['cerrar_otras_sesiones'])) {
                $sesionModel->cerrarTodasDelUsuario($sesion['id_usuario']);
                // Crear nueva sesión
                $ip = $request->getServerParams()['REMOTE_ADDR'] ?? null;
                $userAgent = $request->getHeaderLine('User-Agent');
                $nuevoToken = $sesionModel->crear($sesion['id_usuario'], $ip, $userAgent);
                
                return $this->jsonResponse($response, [
                    'error' => false,
                    'mensaje' => 'Password cambiado correctamente',
                    'nuevo_token' => $nuevoToken
                ]);
            }

            return $this->jsonResponse($response, [
                'error' => false,
                'mensaje' => 'Password cambiado correctamente'
            ]);

        } catch (\Exception $e) {
            error_log("Error al cambiar password: " . $e->getMessage());
            return $this->jsonResponse($response, [
                'error' => true,
                'mensaje' => $e->getMessage()
            ], 400);
        }
    }

    /**
     * GET /auth/validar
     * Validar si un token es válido (para verificaciones JS)
     */
    public function validar(Request $request, Response $response): Response {
        $token = $this->obtenerTokenDeRequest($request);

        if (!$token) {
            return $this->jsonResponse($response, [
                'valido' => false,
                'mensaje' => 'Token no proporcionado'
            ], 401);
        }

        $sesionModel = new Sesion($this->db);
        $sesion = $sesionModel->validar($token);

        if (!$sesion) {
            return $this->jsonResponse($response, [
                'valido' => false,
                'mensaje' => 'Sesión inválida o expirada'
            ], 401);
        }

        return $this->jsonResponse($response, [
            'valido' => true,
            'usuario_id' => $sesion['id_usuario'],
            'rol_nivel' => $sesion['rol_nivel'],
            'expira_en' => $sesion['expira_en']
        ]);
    }

    /**
     * Obtener token del header Authorization o cookie
     */
    private function obtenerTokenDeRequest(Request $request): ?string {
        // Primero intentar desde header Authorization
        $authHeader = $request->getHeaderLine('Authorization');
        if (!empty($authHeader)) {
            if (strpos($authHeader, 'Bearer ') === 0) {
                return substr($authHeader, 7);
            }
            return $authHeader;
        }

        // Luego intentar desde cookie
        $cookies = $request->getCookieParams();
        if (!empty($cookies['mikelo_token'])) {
            return $cookies['mikelo_token'];
        }

        // Finalmente desde query param (solo para debugging)
        $queryParams = $request->getQueryParams();
        if (!empty($queryParams['token'])) {
            return $queryParams['token'];
        }

        return null;
    }

    /**
     * Helper para respuestas JSON
     */
    private function jsonResponse(Response $response, $data, int $status = 200): Response {
        $response->getBody()->write(json_encode($data));
        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus($status);
    }
}
