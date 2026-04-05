<?php
namespace App\Controller;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use App\Model\Ubicacion;

class UbicacionController {
    private $ubicacion;
    private $db;

    public function __construct($db) {
        $this->db = $db;
        $this->ubicacion = new Ubicacion($db);
    }

    public function listar(Request $request, Response $response) {
        try {
            $ubicaciones = $this->ubicacion->obtenerTodas();
            return responseJson($response, [
                'success' => true,
                'ubicaciones' => $ubicaciones
            ]);
        } catch (\Exception $e) {
            return responseJson($response, [
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /** GET /sucursales — lista todas las sucursales (sin depósito central) */
    public function listarSucursales(Request $request, Response $response): Response {
        try {
            $sucursales = $this->ubicacion->obtenerSucursales();
            return $this->json($response, ['error' => false, 'sucursales' => $sucursales]);
        } catch (\Exception $e) {
            return $this->json($response, ['error' => true, 'mensaje' => $e->getMessage()], 500);
        }
    }

    /** GET /sucursales/{id} */
    public function obtenerSucursal(Request $request, Response $response, array $args): Response {
        try {
            $sucursal = $this->ubicacion->obtenerPorId((int)$args['id']);
            if (!$sucursal || $sucursal['tipo_ubicacion'] !== 'sucursal') {
                return $this->json($response, ['error' => true, 'mensaje' => 'Sucursal no encontrada'], 404);
            }
            return $this->json($response, ['error' => false, 'sucursal' => $sucursal]);
        } catch (\Exception $e) {
            return $this->json($response, ['error' => true, 'mensaje' => $e->getMessage()], 500);
        }
    }

    /** POST /sucursales */
    public function crearSucursal(Request $request, Response $response): Response {
        try {
            $datos = $request->getParsedBody() ?? [];
            if (empty(trim($datos['nombre'] ?? ''))) {
                return $this->json($response, ['error' => true, 'mensaje' => 'El nombre es requerido'], 400);
            }
            $id = $this->ubicacion->crear($datos);
            $sucursal = $this->ubicacion->obtenerPorId($id);
            return $this->json($response, [
                'error'    => false,
                'mensaje'  => 'Sucursal creada correctamente',
                'sucursal' => $sucursal
            ], 201);
        } catch (\Exception $e) {
            return $this->json($response, ['error' => true, 'mensaje' => $e->getMessage()], 500);
        }
    }

    /** PUT /sucursales/{id} */
    public function actualizarSucursal(Request $request, Response $response, array $args): Response {
        try {
            $id    = (int)$args['id'];
            $datos = $request->getParsedBody() ?? [];
            if (empty(trim($datos['nombre'] ?? ''))) {
                return $this->json($response, ['error' => true, 'mensaje' => 'El nombre es requerido'], 400);
            }
            $existente = $this->ubicacion->obtenerPorId($id);
            if (!$existente || $existente['tipo_ubicacion'] !== 'sucursal') {
                return $this->json($response, ['error' => true, 'mensaje' => 'Sucursal no encontrada'], 404);
            }
            $this->ubicacion->actualizar($id, $datos);
            $sucursal = $this->ubicacion->obtenerPorId($id);
            return $this->json($response, [
                'error'    => false,
                'mensaje'  => 'Sucursal actualizada correctamente',
                'sucursal' => $sucursal
            ]);
        } catch (\Exception $e) {
            return $this->json($response, ['error' => true, 'mensaje' => $e->getMessage()], 500);
        }
    }

    private function json(Response $response, $data, int $status = 200): Response {
        $response->getBody()->write(json_encode($data, JSON_UNESCAPED_UNICODE));
        return $response->withHeader('Content-Type', 'application/json')->withStatus($status);
    }
}
