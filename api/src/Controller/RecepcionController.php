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

            $recepcionModel = new Recepcion($this->db);
            $envios = $recepcionModel->listarEnviosPendientes($idSucursal);

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
            
            // Obtener detalle del envío con items
            $stmt = $this->db->prepare("
                SELECT 
                    m.id,
                    m.fecha,
                    m.id_ubicaciones as id_sucursal_destino,
                    u.nombre as sucursal_destino,
                    m.observaciones
                FROM movimientos m
                INNER JOIN ubicaciones u ON m.id_ubicaciones = u.id
                WHERE m.id = ? AND m.tipo = 'envio'
            ");
            $stmt->execute([$idEnvio]);
            $envio = $stmt->fetch(\PDO::FETCH_ASSOC);

            if (!$envio) {
                return $this->jsonResponse($response, [
                    'error' => true,
                    'mensaje' => 'Envío no encontrado'
                ], 404);
            }

            // Obtener items del envío
            $stmt = $this->db->prepare("
                SELECT 
                    mi.id as id_movimiento_item,
                    mi.id_productos,
                    p.codigo,
                    p.nombre as producto,
                    mi.cantidad,
                    mi.peso,
                    c.nombre as contenedor
                FROM movimientos_items mi
                INNER JOIN productos p ON mi.id_productos = p.id
                LEFT JOIN contenedores c ON mi.id_contenedores = c.id
                WHERE mi.id_movimientos = ?
            ");
            $stmt->execute([$idEnvio]);
            $envio['items'] = $stmt->fetchAll(\PDO::FETCH_ASSOC);

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
            
            if (!$idSucursal) {
                return $this->jsonResponse($response, [
                    'error' => true,
                    'mensaje' => 'Usuario no tiene sucursal asignada'
                ], 400);
            }

            // Verificar que el envío sea para esta sucursal
            $stmt = $this->db->prepare("
                SELECT id_ubicaciones FROM movimientos WHERE id = ? AND tipo = 'envio'
            ");
            $stmt->execute([$datos['id_envio']]);
            $envio = $stmt->fetch(\PDO::FETCH_ASSOC);

            if (!$envio) {
                return $this->jsonResponse($response, [
                    'error' => true,
                    'mensaje' => 'Envío no encontrado'
                ], 404);
            }

            if ($envio['id_ubicaciones'] != $idSucursal && $usuarioRolNivel >= 30) {
                return $this->jsonResponse($response, [
                    'error' => true,
                    'mensaje' => 'El envío no corresponde a su sucursal'
                ], 403);
            }

            $recepcionModel = new Recepcion($this->db);
            $idRecepcion = $recepcionModel->confirmar(
                $datos['id_envio'],
                $datos['items'],
                $usuarioId,
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

            $sql = "
                SELECT 
                    r.id,
                    r.id_envio,
                    r.fecha_recepcion,
                    r.recibido_por,
                    CONCAT(u.nombre, ' ', COALESCE(u.apellido, '')) as recibido_por_nombre,
                    r.observaciones,
                    (SELECT COUNT(*) FROM recepcion_items WHERE id_recepcion = r.id) as total_items
                FROM recepciones r
                LEFT JOIN usuarios u ON r.recibido_por = u.id
                WHERE 1=1
            ";
            $params = [];

            // Filtrar por sucursal si es franquicia
            if ($idSucursal && $usuarioRolNivel >= 30) {
                $sql .= " AND r.id_envio IN (
                    SELECT id FROM movimientos WHERE id_ubicaciones = ? AND tipo = 'envio'
                )";
                $params[] = $idSucursal;
            }

            // Filtros opcionales
            if (!empty($queryParams['fecha_desde'])) {
                $sql .= " AND r.fecha_recepcion >= ?";
                $params[] = $queryParams['fecha_desde'];
            }
            if (!empty($queryParams['fecha_hasta'])) {
                $sql .= " AND r.fecha_recepcion <= ?";
                $params[] = $queryParams['fecha_hasta'] . ' 23:59:59';
            }

            $sql .= " ORDER BY r.fecha_recepcion DESC";

            if (!empty($queryParams['limite'])) {
                $sql .= " LIMIT " . (int)$queryParams['limite'];
            } else {
                $sql .= " LIMIT 50";
            }

            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            $recepciones = $stmt->fetchAll(\PDO::FETCH_ASSOC);

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

            $stmt = $this->db->prepare("
                SELECT 
                    r.*,
                    CONCAT(u.nombre, ' ', COALESCE(u.apellido, '')) as recibido_por_nombre
                FROM recepciones r
                LEFT JOIN usuarios u ON r.recibido_por = u.id
                WHERE r.id = ?
            ");
            $stmt->execute([$idRecepcion]);
            $recepcion = $stmt->fetch(\PDO::FETCH_ASSOC);

            if (!$recepcion) {
                return $this->jsonResponse($response, [
                    'error' => true,
                    'mensaje' => 'Recepción no encontrada'
                ], 404);
            }

            // Obtener items
            $stmt = $this->db->prepare("
                SELECT 
                    ri.*,
                    p.codigo,
                    p.nombre as producto
                FROM recepcion_items ri
                INNER JOIN productos p ON ri.id_producto = p.id
                WHERE ri.id_recepcion = ?
            ");
            $stmt->execute([$idRecepcion]);
            $recepcion['items'] = $stmt->fetchAll(\PDO::FETCH_ASSOC);

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
