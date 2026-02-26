<?php
namespace App\Controller;

use App\Model\StockMinimo;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

/**
 * Controlador para gestión de Stock Mínimo
 */
class StockMinimoController {
    private $db;

    public function __construct($db) {
        $this->db = $db;
    }

    /**
     * GET /stock-minimo
     * Obtener configuración de stock mínimo para la sucursal del usuario
     */
    public function listar(Request $request, Response $response): Response {
        try {
            $usuario = $request->getAttribute('usuario');
            $params = $request->getQueryParams();
            
            // Obtener sucursal
            $idSucursal = $params['id_sucursal'] ?? null;
            
            if (!$idSucursal && !empty($usuario['sucursales'])) {
                $idSucursal = $usuario['sucursales'][0]['id_sucursal'];
            }
            
            if (!$idSucursal) {
                return $this->jsonResponse($response, [
                    'error' => true,
                    'mensaje' => 'Sucursal no especificada'
                ], 400);
            }

            $model = new StockMinimo($this->db);
            $configuraciones = $model->obtenerPorSucursal($idSucursal);

            return $this->jsonResponse($response, [
                'error' => false,
                'configuraciones' => $configuraciones
            ]);

        } catch (\Exception $e) {
            error_log("Error al listar stock mínimo: " . $e->getMessage());
            return $this->jsonResponse($response, [
                'error' => true,
                'mensaje' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * GET /stock-minimo/faltantes
     * Obtener productos con stock bajo mínimo
     */
    public function faltantes(Request $request, Response $response): Response {
        try {
            $usuario = $request->getAttribute('usuario');
            $params = $request->getQueryParams();
            
            // Obtener sucursal
            $idSucursal = $params['id_sucursal'] ?? null;
            
            if (!$idSucursal && !empty($usuario['sucursales'])) {
                $idSucursal = $usuario['sucursales'][0]['id_sucursal'];
            }
            
            if (!$idSucursal) {
                return $this->jsonResponse($response, [
                    'error' => true,
                    'mensaje' => 'Sucursal no especificada'
                ], 400);
            }

            $model = new StockMinimo($this->db);
            $faltantes = $model->obtenerFaltantes($idSucursal);

            return $this->jsonResponse($response, [
                'error' => false,
                'faltantes' => $faltantes,
                'total' => count($faltantes)
            ]);

        } catch (\Exception $e) {
            error_log("Error al obtener faltantes: " . $e->getMessage());
            return $this->jsonResponse($response, [
                'error' => true,
                'mensaje' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * POST /stock-minimo
     * Configurar stock mínimo para un producto
     */
    public function configurar(Request $request, Response $response): Response {
        try {
            $usuario = $request->getAttribute('usuario');
            $datos = $request->getParsedBody();
            
            // Validar datos
            if (empty($datos['id_producto']) || !isset($datos['stock_minimo'])) {
                return $this->jsonResponse($response, [
                    'error' => true,
                    'mensaje' => 'Faltan datos requeridos (id_producto, stock_minimo)'
                ], 400);
            }

            // Obtener sucursal
            $idSucursal = $datos['id_sucursal'] ?? null;
            
            if (!$idSucursal && !empty($usuario['sucursales'])) {
                $idSucursal = $usuario['sucursales'][0]['id_sucursal'];
            }
            
            if (!$idSucursal) {
                return $this->jsonResponse($response, [
                    'error' => true,
                    'mensaje' => 'Sucursal no especificada'
                ], 400);
            }

            $model = new StockMinimo($this->db);
            $result = $model->configurar(
                $idSucursal,
                (int)$datos['id_producto'],
                (float)$datos['stock_minimo'],
                isset($datos['stock_optimo']) ? (float)$datos['stock_optimo'] : null,
                $usuario['nombre_usuario'] ?? 'sistema'
            );

            return $this->jsonResponse($response, [
                'error' => false,
                'mensaje' => 'Stock mínimo configurado correctamente'
            ]);

        } catch (\Exception $e) {
            error_log("Error al configurar stock mínimo: " . $e->getMessage());
            return $this->jsonResponse($response, [
                'error' => true,
                'mensaje' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * POST /stock-minimo/multiple
     * Configurar stock mínimo para múltiples productos
     */
    public function configurarMultiple(Request $request, Response $response): Response {
        try {
            $usuario = $request->getAttribute('usuario');
            $datos = $request->getParsedBody();
            
            if (empty($datos['productos']) || !is_array($datos['productos'])) {
                return $this->jsonResponse($response, [
                    'error' => true,
                    'mensaje' => 'Debe proporcionar una lista de productos'
                ], 400);
            }

            // Obtener sucursal
            $idSucursal = $datos['id_sucursal'] ?? null;
            
            if (!$idSucursal && !empty($usuario['sucursales'])) {
                $idSucursal = $usuario['sucursales'][0]['id_sucursal'];
            }
            
            if (!$idSucursal) {
                return $this->jsonResponse($response, [
                    'error' => true,
                    'mensaje' => 'Sucursal no especificada'
                ], 400);
            }

            $model = new StockMinimo($this->db);
            $configurados = $model->configurarMultiple(
                $idSucursal,
                $datos['productos'],
                $usuario['nombre_usuario'] ?? 'sistema'
            );

            return $this->jsonResponse($response, [
                'error' => false,
                'mensaje' => "Se configuraron $configurados productos",
                'configurados' => $configurados
            ]);

        } catch (\Exception $e) {
            error_log("Error al configurar múltiples: " . $e->getMessage());
            return $this->jsonResponse($response, [
                'error' => true,
                'mensaje' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * DELETE /stock-minimo/{id}
     * Eliminar configuración de stock mínimo
     */
    public function eliminar(Request $request, Response $response, array $args): Response {
        try {
            $idStockMinimo = (int)$args['id'];

            $model = new StockMinimo($this->db);
            $result = $model->eliminar($idStockMinimo);

            return $this->jsonResponse($response, [
                'error' => false,
                'mensaje' => 'Configuración eliminada'
            ]);

        } catch (\Exception $e) {
            error_log("Error al eliminar stock mínimo: " . $e->getMessage());
            return $this->jsonResponse($response, [
                'error' => true,
                'mensaje' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * GET /stock-minimo/resumen
     * Resumen de faltantes por sucursal (para planta/admin)
     */
    public function resumen(Request $request, Response $response): Response {
        try {
            $model = new StockMinimo($this->db);
            $resumen = $model->resumenFaltantesTodas();

            return $this->jsonResponse($response, [
                'error' => false,
                'resumen' => $resumen
            ]);

        } catch (\Exception $e) {
            error_log("Error al obtener resumen: " . $e->getMessage());
            return $this->jsonResponse($response, [
                'error' => true,
                'mensaje' => $e->getMessage()
            ], 500);
        }
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
