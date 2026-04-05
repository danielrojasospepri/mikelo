<?php
namespace App\Controller;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use App\Model\Envio;
use App\Model\Pedido;

class EnvioController {
    private $envio;
    private $db;

    public function __construct($db) {
        $this->db = $db;
        $this->envio = new Envio($db);
    }

    public function crear(Request $request, Response $response) {
        $data = json_decode($request->getBody()->getContents(), true);
        
        if (!isset($data['destino']) || !isset($data['productos']) || empty($data['productos'])) {
            return responseJson($response, ['error' => 'Destino y productos son requeridos'], 400);
        }

        try {
            $envioId = $this->envio->crear($data['destino'], $data['productos']);

            // Si viene id_pedido, vincular el envío al pedido y marcarlo como ENVIADO
            if (!empty($data['id_pedido'])) {
                try {
                    $idPedido = (int)$data['id_pedido'];
                    // usuario_id es INT (FK a usuarios), no pasar string como fallback
                    $usuarioId = $request->getAttribute('usuario_id') ?? null;
                    $pedidoModel = new Pedido($this->db);
                    $pedidoModel->enviar($idPedido, $envioId, $usuarioId);
                } catch (\Exception $ePedido) {
                    // No falla la creación del envío si hay error al actualizar el pedido
                    error_log("Advertencia: no se pudo actualizar estado del pedido #{$data['id_pedido']}: " . $ePedido->getMessage());
                }
            }

            return responseJson($response, [
                'success' => true,
                'id' => $envioId,
                'id_envio' => $envioId,
                'mensaje' => 'Envío creado exitosamente'
            ], 201);
        } catch (\Exception $e) {
            return responseJson($response, ['error' => $e->getMessage()], 500);
        }
    }

    public function listar(Request $request, Response $response) {
        $params = $request->getQueryParams();
        $filtros = [
            'familia' => $params['familia'] ?? null,
            'fechaDesde' => $params['fechaDesde'] ?? $params['fecha_desde'] ?? null,
            'fechaHasta' => $params['fechaHasta'] ?? $params['fecha_hasta'] ?? null,
            'destino' => $params['destino'] ?? $params['ubicacion_destino'] ?? null,
            'estado' => $params['estado'] ?? null
        ];

        try {
            $envios = $this->envio->obtenerEnvios($filtros);
            return responseJson($response, [
                'success' => true,
                'data' => $envios
            ]);
        } catch (\Exception $e) {
            return responseJson($response, ['error' => $e->getMessage()], 500);
        }
    }

    public function obtenerDetalle(Request $request, Response $response, $args) {
        try {
            $detalle = $this->envio->obtenerDetalleEnvio($args['id']);
            return responseJson($response, [
                'success' => true,
                'data' => $detalle
            ]);
        } catch (\Exception $e) {
            return responseJson($response, ['error' => $e->getMessage()], 500);
        }
    }

    public function obtenerProductosDisponibles(Request $request, Response $response) {
        $params = $request->getQueryParams();
        $filtros = [
            'codigo' => $params['codigo'] ?? null,
            'cantidad' => $params['cantidad'] ?? null,
            'peso' => $params['peso'] ?? null,
            'filtro' => $params['filtro'] ?? null
        ];

        try {
            $productos = $this->envio->obtenerProductosDisponibles($filtros);
            return responseJson($response, [
                'success' => true,
                'data' => $productos
            ]);
        } catch (\Exception $e) {
            return responseJson($response, ['error' => $e->getMessage()], 500);
        }
    }

    public function obtenerContenedores(Request $request, Response $response) {
        try {
            $contenedores = $this->envio->obtenerContenedores();
            return responseJson($response, [
                'success' => true,
                'data' => $contenedores
            ]);
        } catch (\Exception $e) {
            return responseJson($response, ['error' => $e->getMessage()], 500);
        }
    }

    public function exportarPDF(Request $request, Response $response, $args = []) {
        $params = $request->getQueryParams();
        $id = $args['id'] ?? null;
        $filtros = [
            'fechaDesde' => $params['fechaDesde'] ?? null,
            'fechaHasta' => $params['fechaHasta'] ?? null,
            'destino' => $params['destino'] ?? null,
            'estado' => $params['estado'] ?? null
        ];

        try {
            $rutaRelativa = $this->envio->exportarPDF($id, $filtros);
            $rutaCompleta = __DIR__ . '/../../../' . $rutaRelativa;
            
            if (!file_exists($rutaCompleta)) {
                return responseJson($response, ['error' => 'Archivo no encontrado'], 404);
            }

            $nombreArchivo = basename($rutaRelativa);
            
            // Configurar headers para descarga
            $response = $response
                ->withHeader('Content-Type', 'application/pdf')
                ->withHeader('Content-Disposition', 'attachment; filename="' . $nombreArchivo . '"')
                ->withHeader('Content-Length', filesize($rutaCompleta));

            // Leer y enviar archivo
            $response->getBody()->write(file_get_contents($rutaCompleta));
            
            // Opcional: eliminar archivo temporal despuÃ©s de enviarlo
            // unlink($rutaCompleta);
            
            return $response;
        } catch (\Exception $e) {
            return responseJson($response, ['error' => $e->getMessage()], 500);
        }
    }

    public function exportarExcel(Request $request, Response $response, $args = []) {
        $params = $request->getQueryParams();
        $id = $args['id'] ?? null;
        $filtros = [
            'fechaDesde' => $params['fechaDesde'] ?? null,
            'fechaHasta' => $params['fechaHasta'] ?? null,
            'destino' => $params['destino'] ?? null,
            'estado' => $params['estado'] ?? null
        ];

        try {
            $rutaRelativa = $this->envio->exportarExcel($id, $filtros);
            $rutaCompleta = __DIR__ . '/../../../' . $rutaRelativa;
            
            if (!file_exists($rutaCompleta)) {
                return responseJson($response, ['error' => 'Archivo no encontrado'], 404);
            }

            $nombreArchivo = basename($rutaRelativa);
            
            // Configurar headers para descarga
            $response = $response
                ->withHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet')
                ->withHeader('Content-Disposition', 'attachment; filename="' . $nombreArchivo . '"')
                ->withHeader('Content-Length', filesize($rutaCompleta));

            // Leer y enviar archivo
            $response->getBody()->write(file_get_contents($rutaCompleta));
            
            // Opcional: eliminar archivo temporal despuÃ©s de enviarlo
            // unlink($rutaCompleta);
            
            return $response;
        } catch (\Exception $e) {
            return responseJson($response, ['error' => $e->getMessage()], 500);
        }
    }

    public function exportarPDFPreimpreso(Request $request, Response $response, $args) {
        $params = $request->getQueryParams();
        $id = $args['id'] ?? null;
        $filtros = [
            'fechaDesde' => $params['fechaDesde'] ?? null,
            'fechaHasta' => $params['fechaHasta'] ?? null,
            'destino' => $params['destino'] ?? null,
            'estado' => $params['estado'] ?? null
        ];

        try {
            $rutaRelativa = $this->envio->exportarPDFPreimpreso($id, $filtros);
            $rutaCompleta = __DIR__ . '/../../../' . $rutaRelativa;
            
            if (!file_exists($rutaCompleta)) {
                return responseJson($response, ['error' => 'Archivo no encontrado'], 404);
            }

            $nombreArchivo = basename($rutaRelativa);
            
            // Configurar headers para mostrar el PDF en el navegador
            $response = $response
                ->withHeader('Content-Type', 'application/pdf')
                ->withHeader('Content-Disposition', 'inline; filename="' . $nombreArchivo . '"')
                ->withHeader('Content-Length', filesize($rutaCompleta));

            // Leer y enviar archivo
            $response->getBody()->write(file_get_contents($rutaCompleta));
            
            // Opcional: eliminar archivo temporal después de enviarlo
            // unlink($rutaCompleta);
            
            return $response;
        } catch (\Exception $e) {
            return responseJson($response, ['error' => $e->getMessage()], 500);
        }
    }

    public function confirmarEnvio(Request $request, Response $response, $args) {
        $id = $args['id'];

        try {
            $this->envio->confirmarEnvio($id);
            return responseJson($response, [
                'success' => true,
                'mensaje' => 'EnvÃ­o confirmado exitosamente'
            ]);
        } catch (\Exception $e) {
            return responseJson($response, ['error' => $e->getMessage()], 500);
        }
    }

    public function cancelarEnvio(Request $request, Response $response, $args) {
        $data = json_decode($request->getBody()->getContents(), true);
        $id = $args['id'];

        if (!isset($data['motivo']) || empty(trim($data['motivo']))) {
            return responseJson($response, ['error' => 'El motivo es requerido'], 400);
        }

        try {
            $this->envio->cancelarEnvio($id, $data['motivo']);
            return responseJson($response, [
                'success' => true,
                'mensaje' => 'Envío cancelado exitosamente'
            ]);
        } catch (\Exception $e) {
            return responseJson($response, ['error' => $e->getMessage()], 500);
        }
    }
}
