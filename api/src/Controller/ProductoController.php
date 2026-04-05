<?php
namespace App\Controller;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use App\Model\Producto;

class ProductoController {
    private $producto;
    private $db;

    public function __construct($db) {
        $this->db = $db;
        $this->producto = new Producto($db);
    }

    public function buscar(Request $request, Response $response) {
        $params = $request->getQueryParams();
        $termino = $params['q'] ?? '';
        
        if (strlen($termino) < 2) {
            return responseJson($response, [
                'success' => false, 
                'error' => 'El término de búsqueda debe tener al menos 2 caracteres'
            ], 400);
        }

        try {
            $productos = $this->producto->buscarPorCodigoONombre($termino);
            return responseJson($response, [
                'success' => true,
                'data' => $productos
            ]);
        } catch (\Exception $e) {
            return responseJson($response, [
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function buscarNuevos(Request $request, Response $response) {
        $params = $request->getQueryParams();
        $termino = $params['q'] ?? '';
        
        if (strlen($termino) < 2) {
            return responseJson($response, [
                'success' => false,
                'error' => 'El término de búsqueda debe tener al menos 2 caracteres'
            ], 400);
        }

        try {
            // Obtener el ID del depósito central
            $ubicacion = new \App\Model\Ubicacion($this->db);
            $depositoCentral = $ubicacion->obtenerDepositoCentral();
            
            if (!$depositoCentral) {
                return responseJson($response, [
                    'success' => false,
                    'error' => 'No se encontró el depósito central'
                ], 404);
            }

            $productos = $this->producto->buscarProductosNuevosEnDeposito($termino, $depositoCentral['id']);
            return responseJson($response, [
                'success' => true,
                'data' => $productos
            ]);
        } catch (\Exception $e) {
            return responseJson($response, [
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }

    // ─── ABM Productos ───────────────────────────────────────────────────────

    public function listar(Request $request, Response $response) {
        try {
            $productos = $this->producto->listarTodos();
            return $this->json($response, ['success' => true, 'data' => $productos]);
        } catch (\Exception $e) {
            return $this->json($response, ['success' => false, 'error' => $e->getMessage()], 500);
        }
    }

    public function crear(Request $request, Response $response) {
        try {
            $datos = $request->getParsedBody();
            if (empty($datos['codigo']) || empty($datos['descripcion'])) {
                return $this->json($response, ['success' => false, 'error' => 'Código y descripción son obligatorios'], 400);
            }
            $id = $this->producto->crear($datos);
            $nuevo = $this->producto->obtenerPorId($id);
            return $this->json($response, ['success' => true, 'data' => $nuevo, 'mensaje' => 'Producto creado correctamente'], 201);
        } catch (\Exception $e) {
            return $this->json($response, ['success' => false, 'error' => $e->getMessage()], 400);
        }
    }

    public function actualizar(Request $request, Response $response, array $args) {
        try {
            $id = (int)$args['id'];
            $datos = $request->getParsedBody();
            if (empty($datos['codigo']) || empty($datos['descripcion'])) {
                return $this->json($response, ['success' => false, 'error' => 'Código y descripción son obligatorios'], 400);
            }
            $this->producto->actualizar($id, $datos);
            $actualizado = $this->producto->obtenerPorId($id);
            return $this->json($response, ['success' => true, 'data' => $actualizado, 'mensaje' => 'Producto actualizado correctamente']);
        } catch (\Exception $e) {
            return $this->json($response, ['success' => false, 'error' => $e->getMessage()], 400);
        }
    }

    public function eliminar(Request $request, Response $response, array $args) {
        try {
            $id = (int)$args['id'];
            $this->producto->eliminar($id);
            return $this->json($response, ['success' => true, 'mensaje' => 'Producto eliminado correctamente']);
        } catch (\Exception $e) {
            return $this->json($response, ['success' => false, 'error' => $e->getMessage()], 400);
        }
    }

    // ─── ABM Familias (tipo_producto) ─────────────────────────────────────────

    public function listarFamilias(Request $request, Response $response) {
        try {
            $familias = $this->producto->listarFamilias();
            return $this->json($response, ['success' => true, 'data' => $familias]);
        } catch (\Exception $e) {
            return $this->json($response, ['success' => false, 'error' => $e->getMessage()], 500);
        }
    }

    public function crearFamilia(Request $request, Response $response) {
        try {
            $datos = $request->getParsedBody();
            if (empty($datos['nombre'])) {
                return $this->json($response, ['success' => false, 'error' => 'El nombre es obligatorio'], 400);
            }
            $id = $this->producto->crearFamilia($datos);
            return $this->json($response, ['success' => true, 'data' => ['id' => $id, 'nombre' => $datos['nombre']], 'mensaje' => 'Familia creada correctamente'], 201);
        } catch (\Exception $e) {
            return $this->json($response, ['success' => false, 'error' => $e->getMessage()], 400);
        }
    }

    public function actualizarFamilia(Request $request, Response $response, array $args) {
        try {
            $id = (int)$args['id'];
            $datos = $request->getParsedBody();
            if (empty($datos['nombre'])) {
                return $this->json($response, ['success' => false, 'error' => 'El nombre es obligatorio'], 400);
            }
            $this->producto->actualizarFamilia($id, $datos);
            return $this->json($response, ['success' => true, 'mensaje' => 'Familia actualizada correctamente']);
        } catch (\Exception $e) {
            return $this->json($response, ['success' => false, 'error' => $e->getMessage()], 400);
        }
    }

    public function eliminarFamilia(Request $request, Response $response, array $args) {
        try {
            $id = (int)$args['id'];
            $this->producto->eliminarFamilia($id);
            return $this->json($response, ['success' => true, 'mensaje' => 'Familia eliminada correctamente']);
        } catch (\Exception $e) {
            return $this->json($response, ['success' => false, 'error' => $e->getMessage()], 400);
        }
    }

    private function json(Response $response, $data, int $status = 200): Response {
        $response->getBody()->write(json_encode($data));
        return $response->withHeader('Content-Type', 'application/json')->withStatus($status);
    }
}
