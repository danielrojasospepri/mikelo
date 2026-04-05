<?php
namespace App\Controller;

use App\Model\Recepcion;
use App\Model\StockSucursal;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

/**
 * Controlador de Recepciones
 * Gestiona la confirmación de recepción de envíos en sucursales
 */
class RecepcionController {
    private $db;

    public function __construct($db) {
        $this->db = $db;
    }

    /**
     * GET /recepciones/envios-pendientes
     * Listar envíos pendientes de recibir en la sucursal del usuario
     */
    public function enviosPendientes(Request $request, Response $response): Response {
        try {
            $usuarioId = $request->getAttribute('usuario_id');
            $usuarioRolNivel = $request->getAttribute('usuario_rol_nivel');

            // Obtener sucursal del usuario
            $idSucursal = $this->obtenerSucursalUsuario($usuarioId, $usuarioRolNivel);
            
            if (!$idSucursal && $usuarioRolNivel >= 30) {
                return $this->jsonResponse($response, [
                    'error' => true,
                    'mensaje' => 'Usuario no tiene sucursal asignada'
                ], 400);
            }

            $queryParams = $request->getQueryParams();
            $fechaDesde = !empty($queryParams['fecha_desde']) ? $queryParams['fecha_desde'] : null;

            $recepcionModel = new Recepcion($this->db);
            $envios = $recepcionModel->listarEnviosPendientes($idSucursal, $fechaDesde);

            return $this->jsonResponse($response, [
                'error' => false,
                'envios' => $envios,
                'total' => count($envios)
            ]);

        } catch (\Exception $e) {
            error_log("Error al listar envíos pendientes: " . $e->getMessage());
            return $this->jsonResponse($response, [
                'error' => true,
                'mensaje' => 'Error al obtener envíos pendientes'
            ], 500);
        }
    }

    /**
     * GET /recepciones/envio/{idEnvio}
     * Obtener detalle de un envío para recepcionar
     */
    public function detalleEnvio(Request $request, Response $response, array $args): Response {
        try {
            $idEnvio = (int)$args['idEnvio'];
            $usuarioId = $request->getAttribute('usuario_id');
            $usuarioRolNivel = $request->getAttribute('usuario_rol_nivel');

            $idSucursal = $this->obtenerSucursalUsuario($usuarioId, $usuarioRolNivel);

            $recepcionModel = new Recepcion($this->db);
            $envio = $recepcionModel->obtenerDetalleEnvio($idEnvio, $idSucursal);

            if (!$envio) {
                return $this->jsonResponse($response, [
                    'error' => true,
                    'mensaje' => 'Envío no encontrado o no corresponde a su sucursal'
                ], 404);
            }

            return $this->jsonResponse($response, [
                'error' => false,
                'envio' => $envio
            ]);

        } catch (\Exception $e) {
            error_log("Error al obtener detalle de envío: " . $e->getMessage());
            return $this->jsonResponse($response, [
                'error' => true,
                'mensaje' => 'Error al obtener detalle'
            ], 500);
        }
    }

    /**
     * POST /recepciones
     * Confirmar recepción de un envío
     */
    public function confirmar(Request $request, Response $response): Response {
        try {
            $datos = $request->getParsedBody();
            $usuarioId = $request->getAttribute('usuario_id');
            $usuarioRolNivel = $request->getAttribute('usuario_rol_nivel');

            // Validar datos requeridos
            if (empty($datos['id_envio'])) {
                return $this->jsonResponse($response, [
                    'error' => true,
                    'mensaje' => 'Debe especificar el ID del envío'
                ], 400);
            }

            if (empty($datos['items']) || !is_array($datos['items'])) {
                return $this->jsonResponse($response, [
                    'error' => true,
                    'mensaje' => 'Debe incluir los items recibidos'
                ], 400);
            }

            // Obtener sucursal del usuario
            $idSucursal = $this->obtenerSucursalUsuario($usuarioId, $usuarioRolNivel);

            if (!$idSucursal && $usuarioRolNivel >= 30) {
                return $this->jsonResponse($response, [
                    'error' => true,
                    'mensaje' => 'Usuario no tiene sucursal asignada'
                ], 400);
            }

            $recepcionModel = new Recepcion($this->db);
            $idRecepcion = $recepcionModel->confirmar(
                $datos['id_envio'],
                $idSucursal,
                $usuarioId,
                $datos['items'],
                $datos['observaciones'] ?? null
            );

            return $this->jsonResponse($response, [
                'error' => false,
                'mensaje' => 'Recepción confirmada exitosamente',
                'id_recepcion' => $idRecepcion
            ], 201);

        } catch (\Exception $e) {
            error_log("Error al confirmar recepción: " . $e->getMessage());
            return $this->jsonResponse($response, [
                'error' => true,
                'mensaje' => $e->getMessage()
            ], 400);
        }
    }

    /**
     * GET /recepciones
     * Listar recepciones (historial)
     */
    public function listar(Request $request, Response $response): Response {
        try {
            $queryParams = $request->getQueryParams();
            $usuarioId = $request->getAttribute('usuario_id');
            $usuarioRolNivel = $request->getAttribute('usuario_rol_nivel');

            $idSucursal = $this->obtenerSucursalUsuario($usuarioId, $usuarioRolNivel);
            $limite = !empty($queryParams['limite']) ? (int)$queryParams['limite'] : 50;

            $recepcionModel = new Recepcion($this->db);
            $recepciones = $recepcionModel->listarPorSucursal($idSucursal, $limite, 0);

            return $this->jsonResponse($response, [
                'error' => false,
                'recepciones' => $recepciones,
                'total' => count($recepciones)
            ]);

        } catch (\Exception $e) {
            error_log("Error al listar recepciones: " . $e->getMessage());
            return $this->jsonResponse($response, [
                'error' => true,
                'mensaje' => 'Error al obtener recepciones'
            ], 500);
        }
    }

    /**
     * GET /recepciones/{id}
     * Obtener detalle de una recepción
     */
    public function obtener(Request $request, Response $response, array $args): Response {
        try {
            $idRecepcion = (int)$args['id'];

            $recepcionModel = new Recepcion($this->db);
            $recepcion = $recepcionModel->obtenerPorId($idRecepcion);

            if (!$recepcion) {
                return $this->jsonResponse($response, [
                    'error' => true,
                    'mensaje' => 'Recepción no encontrada'
                ], 404);
            }

            return $this->jsonResponse($response, [
                'error' => false,
                'recepcion' => $recepcion
            ]);

        } catch (\Exception $e) {
            error_log("Error al obtener recepción: " . $e->getMessage());
            return $this->jsonResponse($response, [
                'error' => true,
                'mensaje' => 'Error al obtener recepción'
            ], 500);
        }
    }

    /**
     * POST /recepciones/archivar/{idEnvio}
     * Archivar un envío (ocultarlo de pendientes sin recibirlo)
     */
    public function archivar(Request $request, Response $response, array $args): Response {
        try {
            $idEnvio = (int)$args['idEnvio'];
            $usuarioId = $request->getAttribute('usuario_id');
            $datos = $request->getParsedBody();
            $motivo = $datos['motivo'] ?? null;

            $recepcionModel = new Recepcion($this->db);
            $recepcionModel->archivarEnvio($idEnvio, $usuarioId, $motivo);

            return $this->jsonResponse($response, [
                'error' => false,
                'mensaje' => 'Envío archivado correctamente'
            ]);

        } catch (\Exception $e) {
            error_log('Error al archivar envío: ' . $e->getMessage());
            return $this->jsonResponse($response, [
                'error' => true,
                'mensaje' => $e->getMessage()
            ], 400);
        }
    }

    /**
     * GET /recepciones/archivados
     * Listar envíos archivados
     */
    public function archivados(Request $request, Response $response): Response {
        try {
            $usuarioId = $request->getAttribute('usuario_id');
            $usuarioRolNivel = $request->getAttribute('usuario_rol_nivel');
            $idSucursal = $this->obtenerSucursalUsuario($usuarioId, $usuarioRolNivel);

            $recepcionModel = new Recepcion($this->db);
            $envios = $recepcionModel->listarArchivados($idSucursal);

            return $this->jsonResponse($response, [
                'error' => false,
                'envios' => $envios,
                'total' => count($envios)
            ]);
        } catch (\Exception $e) {
            return $this->jsonResponse($response, [
                'error' => true,
                'mensaje' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * POST /recepciones/desarchivar/{idEnvio}
     * Restaurar un envío archivado a pendientes
     */
    public function desarchivar(Request $request, Response $response, array $args): Response {
        try {
            $idEnvio = (int)$args['idEnvio'];
            $recepcionModel = new Recepcion($this->db);
            $recepcionModel->desarchivarEnvio($idEnvio);

            return $this->jsonResponse($response, [
                'error' => false,
                'mensaje' => 'Envío restaurado a pendientes correctamente'
            ]);
        } catch (\Exception $e) {
            return $this->jsonResponse($response, [
                'error' => true,
                'mensaje' => $e->getMessage()
            ], 400);
        }
    }

    /**
     * Obtener sucursal del usuario según su rol
     */
    private function obtenerSucursalUsuario($usuarioId, $rolNivel) {
        // Planta/Admin puede ver todo
        if ($rolNivel < 30) {
            return null;
        }

        $stmt = $this->db->prepare("
            SELECT id_sucursal FROM usuario_sucursales 
            WHERE id_usuario = ? 
            ORDER BY es_sucursal_principal DESC
            LIMIT 1
        ");
        $stmt->execute([$usuarioId]);
        $result = $stmt->fetch(\PDO::FETCH_ASSOC);
        
        return $result ? $result['id_sucursal'] : null;
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
