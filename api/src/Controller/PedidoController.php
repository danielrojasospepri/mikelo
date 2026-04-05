<?php
namespace App\Controller;

use App\Model\Pedido;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

/**
 * Controlador de Pedidos
 * Gestiona pedidos de sucursales al depósito central
 */
class PedidoController {
    private $db;

    public function __construct($db) {
        $this->db = $db;
    }

    /**
     * GET /pedidos
     * Listar pedidos (filtrado por rol)
     */
    public function listar(Request $request, Response $response): Response {
        try {
            $queryParams = $request->getQueryParams();
            $usuarioRolNivel = $request->getAttribute('usuario_rol_nivel');
            $usuarioId = $request->getAttribute('usuario_id');

            $filtros = [
                'estado' => $queryParams['estado'] ?? null,
                'fecha_desde' => $queryParams['fecha_desde'] ?? null,
                'fecha_hasta' => $queryParams['fecha_hasta'] ?? null
            ];

            $pedidoModel = new Pedido($this->db);

            // Si es franquicia, solo ve sus pedidos
            if ($usuarioRolNivel >= 30) {
                // Obtener sucursal del usuario
                $stmt = $this->db->prepare("
                    SELECT id_sucursal FROM usuario_sucursales 
                    WHERE id_usuario = ? AND es_sucursal_principal = 1
                    LIMIT 1
                ");
                $stmt->execute([$usuarioId]);
                $sucursal = $stmt->fetch(\PDO::FETCH_ASSOC);
                
                if (!$sucursal) {
                    return $this->jsonResponse($response, [
                        'error' => true,
                        'mensaje' => 'Usuario no tiene sucursal asignada'
                    ], 400);
                }
                
                $pedidos = $pedidoModel->listarPorSucursal(
                    $sucursal['id_sucursal'], 
                    $filtros['estado'] ?? null
                );
            } else {
                // Planta/Admin ve todos los pedidos
                $pedidos = $pedidoModel->listarTodos($filtros);
            }

            return $this->jsonResponse($response, [
                'error' => false,
                'pedidos' => $pedidos,
                'total' => count($pedidos)
            ]);

        } catch (\Exception $e) {
            error_log("Error al listar pedidos: " . $e->getMessage());
            return $this->jsonResponse($response, [
                'error' => true,
                'mensaje' => 'Error al obtener pedidos'
            ], 500);
        }
    }

    /**
     * GET /pedidos/{id}
     * Obtener detalle de un pedido
     */
    public function obtener(Request $request, Response $response, array $args): Response {
        try {
            $idPedido = (int)$args['id'];
            $pedidoModel = new Pedido($this->db);
            
            $pedido = $pedidoModel->obtenerPorId($idPedido);

            if (!$pedido) {
                return $this->jsonResponse($response, [
                    'error' => true,
                    'mensaje' => 'Pedido no encontrado'
                ], 404);
            }

            // Verificar acceso si es franquicia
            $usuarioRolNivel = $request->getAttribute('usuario_rol_nivel');
            $usuarioId = $request->getAttribute('usuario_id');
            
            if ($usuarioRolNivel >= 30) {
                // Verificar que el pedido sea de su sucursal
                $stmt = $this->db->prepare("
                    SELECT 1 FROM usuario_sucursales 
                    WHERE id_usuario = ? AND id_sucursal = ?
                ");
                $stmt->execute([$usuarioId, $pedido['id_sucursal']]);
                if (!$stmt->fetch()) {
                    return $this->jsonResponse($response, [
                        'error' => true,
                        'mensaje' => 'No tiene acceso a este pedido'
                    ], 403);
                }
            }

            return $this->jsonResponse($response, [
                'error' => false,
                'pedido' => $pedido
            ]);

        } catch (\Exception $e) {
            error_log("Error al obtener pedido: " . $e->getMessage());
            return $this->jsonResponse($response, [
                'error' => true,
                'mensaje' => 'Error al obtener pedido'
            ], 500);
        }
    }

    /**
     * POST /pedidos
     * Crear nuevo pedido (solo franquicias)
     */
    public function crear(Request $request, Response $response): Response {
        try {
            $datos = $request->getParsedBody();
            $usuarioId = $request->getAttribute('usuario_id');
            $usuarioRolNivel = $request->getAttribute('usuario_rol_nivel');

            // Admin puede crear pedidos en nombre de cualquier sucursal (para testing y supervisión)
            $esAdmin = $usuarioRolNivel <= 10;

            // Validar que sea franquicia o admin
            if (!$esAdmin && $usuarioRolNivel < 30) {
                return $this->jsonResponse($response, [
                    'error' => true,
                    'mensaje' => 'Solo las franquicias pueden crear pedidos'
                ], 403);
            }

            // Validar items
            if (empty($datos['items']) || !is_array($datos['items'])) {
                return $this->jsonResponse($response, [
                    'error' => true,
                    'mensaje' => 'Debe incluir al menos un producto'
                ], 400);
            }

            // Obtener sucursal: admin puede pasar id_sucursal explícito, sino usar la del usuario
            $idSucursal = null;

            if (!empty($datos['id_sucursal'])) {
                // Sucursal explícita enviada desde el frontend (admin o multi-sucursal)
                $idSucursal = (int)$datos['id_sucursal'];
            } else {
                // Buscar la sucursal principal del usuario
                $stmt = $this->db->prepare("
                    SELECT id_sucursal FROM usuario_sucursales 
                    WHERE id_usuario = ? AND es_sucursal_principal = 1
                    LIMIT 1
                ");
                $stmt->execute([$usuarioId]);
                $sucursal = $stmt->fetch(\PDO::FETCH_ASSOC);
                
                if (!$sucursal) {
                    return $this->jsonResponse($response, [
                        'error' => true,
                        'mensaje' => 'Usuario no tiene sucursal asignada. Seleccione una sucursal.'
                    ], 400);
                }
                $idSucursal = $sucursal['id_sucursal'];
            }

            $pedidoModel = new Pedido($this->db);
            $idPedido = $pedidoModel->crear(
                $idSucursal,
                $usuarioId,
                $datos['items'],
                $datos['observaciones'] ?? null,
                $datos['prioridad'] ?? 'normal'
            );

            return $this->jsonResponse($response, [
                'error' => false,
                'mensaje' => 'Pedido creado exitosamente',
                'id_pedido' => $idPedido
            ], 201);

        } catch (\Exception $e) {
            error_log("Error al crear pedido: " . $e->getMessage());
            return $this->jsonResponse($response, [
                'error' => true,
                'mensaje' => $e->getMessage()
            ], 400);
        }
    }

    /**
     * PUT /pedidos/{id}/enviar
     * Marcar pedido como enviado y asociar envío (solo planta)
     */
    public function enviar(Request $request, Response $response, array $args): Response {
        try {
            $idPedido = (int)$args['id'];
            $datos = $request->getParsedBody();
            $usuarioId = $request->getAttribute('usuario_id');

            $idEnvio = !empty($datos['id_envio']) ? (int)$datos['id_envio'] : null;

            $pedidoModel = new Pedido($this->db);
            $pedidoModel->enviar($idPedido, $idEnvio, $usuarioId);

            return $this->jsonResponse($response, [
                'error' => false,
                'mensaje' => 'Pedido marcado como enviado'
            ]);

        } catch (\Exception $e) {
            error_log("Error al enviar pedido: " . $e->getMessage());
            return $this->jsonResponse($response, [
                'error' => true,
                'mensaje' => $e->getMessage()
            ], 400);
        }
    }

    /**
     * PUT /pedidos/{id}/anular
     * Anular pedido
     */
    public function anular(Request $request, Response $response, array $args): Response {
        try {
            $idPedido = (int)$args['id'];
            $datos = $request->getParsedBody();
            $usuarioId = $request->getAttribute('usuario_id');

            $pedidoModel = new Pedido($this->db);
            $pedidoModel->anular($idPedido, $usuarioId, $datos['motivo'] ?? null);

            return $this->jsonResponse($response, [
                'error' => false,
                'mensaje' => 'Pedido anulado'
            ]);

        } catch (\Exception $e) {
            error_log("Error al anular pedido: " . $e->getMessage());
            return $this->jsonResponse($response, [
                'error' => true,
                'mensaje' => $e->getMessage()
            ], 400);
        }
    }

    /**
     * PUT /pedidos/{id}/recibir
     * Marcar pedido como recibido (sucursal confirma recepción)
     */
    public function recibir(Request $request, Response $response, array $args): Response {
        try {
            $idPedido = (int)$args['id'];
            $usuarioId = $request->getAttribute('usuario_id');
            $usuarioRolNivel = $request->getAttribute('usuario_rol_nivel');

            // Verificar acceso si es franquicia
            if ($usuarioRolNivel >= 30) {
                $stmt = $this->db->prepare("SELECT id_sucursal FROM pedidos WHERE id = ?");
                $stmt->execute([$idPedido]);
                $pedido = $stmt->fetch(\PDO::FETCH_ASSOC);
                if (!$pedido) throw new \Exception("Pedido no encontrado");

                $stmt = $this->db->prepare("
                    SELECT 1 FROM usuario_sucursales WHERE id_usuario = ? AND id_sucursal = ?
                ");
                $stmt->execute([$usuarioId, $pedido['id_sucursal']]);
                if (!$stmt->fetch()) throw new \Exception("No tiene acceso a este pedido");
            }

            $pedidoModel = new Pedido($this->db);
            $pedidoModel->marcarRecibido($idPedido, $usuarioId);

            return $this->jsonResponse($response, [
                'error' => false,
                'mensaje' => 'Pedido marcado como recibido'
            ]);

        } catch (\Exception $e) {
            error_log("Error al recibir pedido: " . $e->getMessage());
            return $this->jsonResponse($response, [
                'error' => true,
                'mensaje' => $e->getMessage()
            ], 400);
        }
    }

    /**
     * GET /pedidos/pendientes
     * Obtener pedidos pendientes de procesar (para planta)
     */
    public function pendientes(Request $request, Response $response): Response {
        try {
            $pedidoModel = new Pedido($this->db);
            $pendientes = $pedidoModel->listarTodos([
                'estado' => 'PENDIENTE'
            ]);

            return $this->jsonResponse($response, [
                'error' => false,
                'pedidos' => $pendientes,
                'total' => count($pendientes)
            ]);

        } catch (\Exception $e) {
            error_log("Error al obtener pedidos pendientes: " . $e->getMessage());
            return $this->jsonResponse($response, [
                'error' => true,
                'mensaje' => 'Error al obtener pedidos'
            ], 500);
        }
    }

    /**
     * GET /pedidos/contadores
     * Obtener contadores rápidos para badges/notificaciones (endpoint ligero)
     */
    public function contadores(Request $request, Response $response): Response {
        try {
            // Query optimizada solo para contar
            $stmt = $this->db->prepare("
                SELECT 
                    COUNT(*) as total_pendientes,
                    SUM(CASE WHEN prioridad IN ('urgente', 'alta') THEN 1 ELSE 0 END) as urgentes,
                    COUNT(DISTINCT id_sucursal) as sucursales
                FROM pedidos 
                WHERE estado = 'PENDIENTE'
            ");
            $stmt->execute();
            $contadores = $stmt->fetch(\PDO::FETCH_ASSOC);

            return $this->jsonResponse($response, [
                'error' => false,
                'pendientes' => (int)$contadores['total_pendientes'],
                'urgentes' => (int)$contadores['urgentes'],
                'sucursales' => (int)$contadores['sucursales']
            ]);

        } catch (\Exception $e) {
            error_log("Error al obtener contadores: " . $e->getMessage());
            return $this->jsonResponse($response, [
                'error' => false,
                'pendientes' => 0,
                'urgentes' => 0,
                'sucursales' => 0
            ]);
        }
    }

    /**
     * GET /pedidos/productos-disponibles
     * Obtener productos disponibles para pedir (habilitados para franquicias)
     * NOTA: Los pedidos son SOLICITUDES - no dependen del stock actual.
     * La planta debe ver qué se necesita para planificar producción.
     */
    public function productosDisponibles(Request $request, Response $response): Response {
        try {
            $queryParams = $request->getQueryParams();
            $idSucursal  = isset($queryParams['id_sucursal']) ? (int)$queryParams['id_sucursal'] : null;

            // Determinar si la sucursal es franquicia (default: sí, para ser conservador)
            $esFranquicia = true;
            if ($idSucursal) {
                $stmt = $this->db->prepare("
                    SELECT franquicia FROM ubicaciones
                    WHERE id = ? AND tipo_ubicacion = 'sucursal'
                ");
                $stmt->execute([$idSucursal]);
                $ub = $stmt->fetch(\PDO::FETCH_ASSOC);
                if ($ub !== false) {
                    $esFranquicia = (int)$ub['franquicia'] === 1;
                }
            }

            // Si es franquicia solo traer productos habilitados; si es sucursal propia, traer todos
            $whereFranquicia = $esFranquicia ? "AND p.disponible_franquicias = 1" : "";

            // Obtener productos habilitados (sin filtrar por stock — los pedidos son solicitudes)
            $stmt = $this->db->prepare("
                SELECT
                    p.id,
                    p.codigo,
                    p.descripcion as nombre,
                    p.id_tipo_producto as id_tipo,
                    tp.nombre as tipo_producto,
                    p.disponible_franquicias,
                    COALESCE(SUM(
                        mi.cnt - IFNULL((
                            SELECT SUM(mi2.cnt)
                            FROM movimientos_items mi2
                            WHERE mi2.id_movimientos_items_origen = mi.id
                        ), 0)
                    ), 0) as stock_disponible
                FROM productos p
                LEFT JOIN tipo_producto tp ON p.id_tipo_producto = tp.id
                LEFT JOIN movimientos_items mi ON mi.id_productos = p.id
                    AND mi.id_movimientos_items_origen IS NULL
                    AND mi.cnt > IFNULL((
                        SELECT IFNULL(SUM(mi3.cnt), 0)
                        FROM movimientos_items mi3
                        WHERE mi3.id_movimientos_items_origen = mi.id
                    ), 0)
                    AND NOT EXISTS (
                        SELECT 1 FROM estados_items_movimientos eim
                        JOIN estados e ON eim.id_estados = e.id
                        WHERE eim.id_movimientos_items = mi.id
                        AND e.nombre = 'BAJA'
                    )
                WHERE p.activo = 1 $whereFranquicia
                GROUP BY p.id, p.codigo, p.descripcion, p.id_tipo_producto, tp.nombre, p.disponible_franquicias
                ORDER BY p.descripcion
            ");
            $stmt->execute();
            $productos = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            return $this->jsonResponse($response, [
                'error' => false,
                'productos' => $productos
            ]);

        } catch (\Exception $e) {
            error_log("Error al obtener productos disponibles: " . $e->getMessage());
            return $this->jsonResponse($response, [
                'error' => true,
                'mensaje' => 'Error al obtener productos'
            ], 500);
        }
    }

    /**
     * GET /pedidos/demanda-agregada
     * Obtener demanda agregada de todos los pedidos pendientes (agrupada por producto)
     * Solo para planta/admin
     */
    public function demandaAgregada(Request $request, Response $response): Response {
        try {
            // Obtener demanda agregada de pedidos PENDIENTE
            $sql = "
                SELECT 
                    p.id as id_producto,
                    p.codigo,
                    p.descripcion AS producto,
                    tp.nombre AS familia,
                    SUM(pi.cantidad) as cantidad_total,
                    COUNT(DISTINCT ped.id) as num_pedidos,
                    GROUP_CONCAT(DISTINCT u.nombre SEPARATOR ', ') as sucursales,
                    COALESCE((
                        SELECT SUM(mi.cnt) - COALESCE((
                            SELECT SUM(mi2.cnt)
                            FROM movimientos_items mi2
                            WHERE mi2.id_movimientos_items_origen = mi.id
                        ), 0)
                        FROM movimientos_items mi
                        INNER JOIN movimientos m ON mi.id_movimientos = m.id
                        WHERE mi.id_productos = p.id
                          AND m.id_ubicacion_origen = 1
                          AND mi.id_movimientos_items_origen IS NULL
                          AND NOT EXISTS (
                              SELECT 1 FROM movimientos_items mi3
                              WHERE mi3.id_movimientos_items_origen = mi.id
                          )
                          AND NOT EXISTS (
                              SELECT 1 FROM estados_items_movimientos eim
                              INNER JOIN estados e ON eim.id_estados = e.id
                              WHERE eim.id_movimientos_items = mi.id
                                AND e.nombre = 'BAJA'
                          )
                    ), 0) as stock_disponible
                FROM pedido_items pi
                INNER JOIN pedidos ped ON pi.id_pedido = ped.id
                INNER JOIN productos p ON pi.id_producto = p.id
                INNER JOIN ubicaciones u ON ped.id_sucursal = u.id
                LEFT JOIN tipo_producto tp ON p.id_tipo_producto = tp.id
                WHERE ped.estado = 'PENDIENTE'
                GROUP BY p.id, p.codigo, p.descripcion, tp.nombre
                ORDER BY (SUM(pi.cantidad) - COALESCE((
                        SELECT SUM(mi.cnt) - COALESCE((
                            SELECT SUM(mi2.cnt)
                            FROM movimientos_items mi2
                            WHERE mi2.id_movimientos_items_origen = mi.id
                        ), 0)
                        FROM movimientos_items mi
                        INNER JOIN movimientos m ON mi.id_movimientos = m.id
                        WHERE mi.id_productos = p.id
                          AND m.id_ubicacion_origen = 1
                          AND mi.id_movimientos_items_origen IS NULL
                    ), 0)) DESC, tp.nombre, p.descripcion
            ";

            $stmt = $this->db->prepare($sql);
            $stmt->execute();
            $demanda = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            // Contar pedidos pendientes
            $stmtCount = $this->db->query("SELECT COUNT(*) FROM pedidos WHERE estado = 'PENDIENTE'");
            $totalPedidos = $stmtCount->fetchColumn();

            return $this->jsonResponse($response, [
                'error' => false,
                'demanda' => $demanda,
                'total_pedidos' => (int)$totalPedidos
            ]);

        } catch (\Exception $e) {
            error_log("Error al obtener demanda agregada: " . $e->getMessage());
            return $this->jsonResponse($response, [
                'error' => true,
                'mensaje' => 'Error al obtener demanda: ' . $e->getMessage()
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
