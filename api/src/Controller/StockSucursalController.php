<?php
namespace App\Controller;

use App\Model\StockSucursal;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

/**
 * Controlador de Stock de Sucursales
 * Gestiona consultas de stock y movimientos en sucursales
 */
class StockSucursalController {
    private $db;

    public function __construct($db) {
        $this->db = $db;
    }

    /**
     * GET /stock-sucursal
     * Obtener stock de la sucursal del usuario
     */
    public function obtenerStock(Request $request, Response $response): Response {
        try {
            $queryParams = $request->getQueryParams();
            $usuarioId = $request->getAttribute('usuario_id');
            $usuarioRolNivel = $request->getAttribute('usuario_rol_nivel');

            // Determinar sucursal
            $idSucursal = $queryParams['id_sucursal'] ?? null;
            
            if (!$idSucursal) {
                $idSucursal = $this->obtenerSucursalUsuario($usuarioId, $usuarioRolNivel);
            }

            // Verificar acceso
            if ($usuarioRolNivel >= 30 && $idSucursal) {
                if (!$this->verificarAccesoSucursal($usuarioId, $idSucursal)) {
                    return $this->jsonResponse($response, [
                        'error' => true,
                        'mensaje' => 'No tiene acceso a esta sucursal'
                    ], 403);
                }
            }

            if (!$idSucursal) {
                return $this->jsonResponse($response, [
                    'error' => true,
                    'mensaje' => 'Debe especificar una sucursal'
                ], 400);
            }

            $stockModel = new StockSucursal($this->db);
            $stock = $stockModel->obtenerStock($idSucursal);

            // Obtener nombre de sucursal
            $stmt = $this->db->prepare("SELECT nombre FROM ubicaciones WHERE id = ?");
            $stmt->execute([$idSucursal]);
            $sucursal = $stmt->fetch(\PDO::FETCH_ASSOC);

            return $this->jsonResponse($response, [
                'error' => false,
                'sucursal' => [
                    'id' => $idSucursal,
                    'nombre' => $sucursal['nombre'] ?? 'Desconocida'
                ],
                'stock' => $stock,
                'total_items' => count($stock)
            ]);

        } catch (\Exception $e) {
            error_log("Error al obtener stock: " . $e->getMessage());
            return $this->jsonResponse($response, [
                'error' => true,
                'mensaje' => 'Error al obtener stock'
            ], 500);
        }
    }

    /**
     * GET /stock-sucursal/producto/{idProducto}
     * Obtener stock y historial de un producto específico
     */
    public function stockProducto(Request $request, Response $response, array $args): Response {
        try {
            $idProducto = (int)$args['idProducto'];
            $queryParams = $request->getQueryParams();
            $usuarioId = $request->getAttribute('usuario_id');
            $usuarioRolNivel = $request->getAttribute('usuario_rol_nivel');

            $idSucursal = $queryParams['id_sucursal'] ?? null;
            
            if (!$idSucursal) {
                $idSucursal = $this->obtenerSucursalUsuario($usuarioId, $usuarioRolNivel);
            }

            if (!$idSucursal) {
                return $this->jsonResponse($response, [
                    'error' => true,
                    'mensaje' => 'Debe especificar una sucursal'
                ], 400);
            }

            // Verificar acceso
            if ($usuarioRolNivel >= 30) {
                if (!$this->verificarAccesoSucursal($usuarioId, $idSucursal)) {
                    return $this->jsonResponse($response, [
                        'error' => true,
                        'mensaje' => 'No tiene acceso a esta sucursal'
                    ], 403);
                }
            }

            $stockModel = new StockSucursal($this->db);
            
            // Obtener stock actual
            $stmt = $this->db->prepare("
                SELECT 
                    ss.cantidad_actual,
                    ss.peso_total,
                    ss.ultima_actualizacion,
                    p.codigo,
                    p.descripcion as producto
                FROM stock_sucursal ss
                INNER JOIN productos p ON ss.id_producto = p.id
                WHERE ss.id_sucursal = ? AND ss.id_producto = ?
            ");
            $stmt->execute([$idSucursal, $idProducto]);
            $stockActual = $stmt->fetch(\PDO::FETCH_ASSOC);

            if (!$stockActual) {
                // Producto sin movimientos en la sucursal
                $stmt = $this->db->prepare("SELECT codigo, descripcion FROM productos WHERE id = ?");
                $stmt->execute([$idProducto]);
                $producto = $stmt->fetch(\PDO::FETCH_ASSOC);
                
                $stockActual = [
                    'codigo' => $producto['codigo'] ?? '',
                    'producto' => $producto['descripcion'] ?? 'Producto no encontrado',
                    'cantidad_actual' => 0,
                    'peso_total' => 0,
                    'ultima_actualizacion' => null
                ];
            }

            // Obtener historial
            $historial = $stockModel->obtenerHistorial($idSucursal, 100, $idProducto);

            return $this->jsonResponse($response, [
                'error' => false,
                'producto' => $stockActual,
                'historial' => $historial
            ]);

        } catch (\Exception $e) {
            error_log("Error al obtener stock de producto: " . $e->getMessage());
            return $this->jsonResponse($response, [
                'error' => true,
                'mensaje' => 'Error al obtener stock'
            ], 500);
        }
    }

    /**
     * GET /stock-sucursal/buscar
     * Buscar productos en stock
     */
    public function buscar(Request $request, Response $response): Response {
        try {
            $queryParams = $request->getQueryParams();
            $usuarioId = $request->getAttribute('usuario_id');
            $usuarioRolNivel = $request->getAttribute('usuario_rol_nivel');

            if (empty($queryParams['q'])) {
                return $this->jsonResponse($response, [
                    'error' => true,
                    'mensaje' => 'Debe proporcionar un término de búsqueda'
                ], 400);
            }

            $idSucursal = $queryParams['id_sucursal'] ?? null;
            
            if (!$idSucursal) {
                $idSucursal = $this->obtenerSucursalUsuario($usuarioId, $usuarioRolNivel);
            }

            if (!$idSucursal) {
                return $this->jsonResponse($response, [
                    'error' => true,
                    'mensaje' => 'Debe especificar una sucursal'
                ], 400);
            }

            $stockModel = new StockSucursal($this->db);
            $resultados = $stockModel->buscarProducto($idSucursal, $queryParams['q']);

            return $this->jsonResponse($response, [
                'error' => false,
                'resultados' => $resultados,
                'total' => count($resultados)
            ]);

        } catch (\Exception $e) {
            error_log("Error en búsqueda: " . $e->getMessage());
            return $this->jsonResponse($response, [
                'error' => true,
                'mensaje' => 'Error en la búsqueda'
            ], 500);
        }
    }

    /**
     * GET /stock-sucursal/resumen
     * Obtener resumen de stock (para dashboard)
     */
    public function resumen(Request $request, Response $response): Response {
        try {
            $queryParams = $request->getQueryParams();
            $usuarioId = $request->getAttribute('usuario_id');
            $usuarioRolNivel = $request->getAttribute('usuario_rol_nivel');

            $idSucursal = $queryParams['id_sucursal'] ?? null;
            
            if (!$idSucursal) {
                $idSucursal = $this->obtenerSucursalUsuario($usuarioId, $usuarioRolNivel);
            }

            if (!$idSucursal) {
                return $this->jsonResponse($response, [
                    'error' => true,
                    'mensaje' => 'Debe especificar una sucursal'
                ], 400);
            }

            // Total de productos con stock
            $stmt = $this->db->prepare("
                SELECT 
                    COUNT(DISTINCT id_producto) as total_productos,
                    SUM(cantidad_actual) as total_unidades,
                    SUM(peso_total) as peso_total
                FROM stock_sucursal
                WHERE id_sucursal = ? AND cantidad_actual > 0
            ");
            $stmt->execute([$idSucursal]);
            $resumen = $stmt->fetch(\PDO::FETCH_ASSOC);

            // Recepciones recientes
            $stmt = $this->db->prepare("
                SELECT COUNT(*) as total_recepciones
                FROM recepciones r
                INNER JOIN movimientos m ON r.id_envio = m.id
                WHERE m.id_ubicacion_destino = ?
                  AND r.fecha_recepcion >= DATE_SUB(NOW(), INTERVAL 30 DAY)
            ");
            $stmt->execute([$idSucursal]);
            $recepciones = $stmt->fetch(\PDO::FETCH_ASSOC);

            // Envíos pendientes de recibir (FROM depósito central = 1, sin recepción aún)
            $stmt = $this->db->prepare("
                SELECT COUNT(*) as pendientes
                FROM movimientos m
                LEFT JOIN recepciones r ON m.id = r.id_envio
                WHERE m.id_ubicacion_origen = 1
                  AND m.id_ubicacion_destino = ?
                  AND r.id IS NULL
            ");
            $stmt->execute([$idSucursal]);
            $pendientes = $stmt->fetch(\PDO::FETCH_ASSOC);

            return $this->jsonResponse($response, [
                'error' => false,
                'resumen' => [
                    'total_productos' => (int)($resumen['total_productos'] ?? 0),
                    'total_unidades' => (int)($resumen['total_unidades'] ?? 0),
                    'peso_total_kg' => round((float)($resumen['peso_total'] ?? 0), 3),
                    'recepciones_mes' => (int)($recepciones['total_recepciones'] ?? 0),
                    'envios_pendientes' => (int)($pendientes['pendientes'] ?? 0)
                ]
            ]);

        } catch (\Exception $e) {
            error_log("Error al obtener resumen: " . $e->getMessage());
            return $this->jsonResponse($response, [
                'error' => true,
                'mensaje' => 'Error al obtener resumen'
            ], 500);
        }
    }

    /**
     * GET /stock-sucursal/todas
     * Obtener stock de todas las sucursales (solo admin/planta)
     */
    public function stockTodas(Request $request, Response $response): Response {
        try {
            $usuarioRolNivel = $request->getAttribute('usuario_rol_nivel');

            if ($usuarioRolNivel >= 30) {
                return $this->jsonResponse($response, [
                    'error' => true,
                    'mensaje' => 'No tiene permisos para ver stock de todas las sucursales'
                ], 403);
            }

            $stmt = $this->db->prepare("
                SELECT 
                    u.id as id_sucursal,
                    u.nombre as sucursal,
                    COUNT(DISTINCT ss.id_producto) as productos_diferentes,
                    SUM(ss.cantidad_actual) as total_unidades,
                    SUM(ss.peso_total) as peso_total
                FROM ubicaciones u
                LEFT JOIN stock_sucursal ss ON u.id = ss.id_sucursal AND ss.cantidad_actual > 0
                WHERE u.tipo_ubicacion = 'sucursal'
                GROUP BY u.id, u.nombre
                ORDER BY u.nombre
            ");
            $stmt->execute();
            $stocks = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            // Formatear pesos
            foreach ($stocks as &$s) {
                $s['peso_total_kg'] = round((float)($s['peso_total'] ?? 0), 3);
                unset($s['peso_total']);
            }

            return $this->jsonResponse($response, [
                'error' => false,
                'sucursales' => $stocks
            ]);

        } catch (\Exception $e) {
            error_log("Error al obtener stock de todas: " . $e->getMessage());
            return $this->jsonResponse($response, [
                'error' => true,
                'mensaje' => 'Error al obtener stock'
            ], 500);
        }
    }

    /**
     * Obtener sucursal del usuario
     */
    private function obtenerSucursalUsuario($usuarioId, $rolNivel) {
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
     * Verificar acceso a sucursal
     */
    private function verificarAccesoSucursal($usuarioId, $idSucursal) {
        $stmt = $this->db->prepare("
            SELECT 1 FROM usuario_sucursales 
            WHERE id_usuario = ? AND id_sucursal = ?
        ");
        $stmt->execute([$usuarioId, $idSucursal]);
        return $stmt->fetch() !== false;
    }

    /**
     * POST /stock-sucursal/baja
     * Registrar baja de stock (venta, merma, ajuste)
     * Rol: FRANQUICIA_EMPLEADO (40)
     */
    public function registrarBaja(Request $request, Response $response): Response {
        try {
            $datos = $request->getParsedBody();
            $usuarioId = $request->getAttribute('usuario_id');
            $usuarioRolNivel = $request->getAttribute('usuario_rol_nivel');

            // Validar datos requeridos
            if (empty($datos['id_producto']) || empty($datos['cantidad']) || empty($datos['tipo_baja'])) {
                return $this->jsonResponse($response, [
                    'error' => true,
                    'mensaje' => 'Debe especificar producto, cantidad y tipo de baja'
                ], 400);
            }

            $tiposPermitidos = ['BAJA_VENTA', 'BAJA_MERMA', 'AJUSTE_NEGATIVO'];
            if (!in_array($datos['tipo_baja'], $tiposPermitidos)) {
                return $this->jsonResponse($response, [
                    'error' => true,
                    'mensaje' => 'Tipo de baja no válido. Use: BAJA_VENTA, BAJA_MERMA o AJUSTE_NEGATIVO'
                ], 400);
            }

            // Obtener sucursal del usuario
            $idSucursal = $datos['id_sucursal'] ?? $this->obtenerSucursalUsuario($usuarioId, $usuarioRolNivel);
            
            if (!$idSucursal) {
                return $this->jsonResponse($response, [
                    'error' => true,
                    'mensaje' => 'No se pudo determinar la sucursal'
                ], 400);
            }

            // Verificar acceso
            if ($usuarioRolNivel >= 30) {
                if (!$this->verificarAccesoSucursal($usuarioId, $idSucursal)) {
                    return $this->jsonResponse($response, [
                        'error' => true,
                        'mensaje' => 'No tiene acceso a esta sucursal'
                    ], 403);
                }
            }

            $stockModel = new StockSucursal($this->db);
            $resultado = $stockModel->registrarBaja(
                $idSucursal,
                $datos['id_producto'],
                $datos['cantidad'] ?? 0,
                $datos['peso']    ?? 0,
                $datos['tipo_baja'],
                $usuarioId,
                $datos['observaciones'] ?? null
            );

            return $this->jsonResponse($response, [
                'error'          => false,
                'mensaje'        => 'Baja registrada exitosamente',
                'movimiento_id'  => $resultado['movimiento_id'],
                'stock_anterior' => $resultado['stock_anterior'],
                'stock_actual'   => $resultado['stock_actual'],
                'es_peso'        => $resultado['es_peso']
            ]);

        } catch (\Exception $e) {
            error_log("Error al registrar baja: " . $e->getMessage());
            return $this->jsonResponse($response, [
                'error' => true,
                'mensaje' => $e->getMessage()
            ], 400);
        }
    }

    /**
     * POST /stock-sucursal/ajuste
     * Registrar ajuste de stock (positivo o negativo) - Inventario físico
     * Rol: FRANQUICIA_ADMIN (30)
     */
    public function registrarAjuste(Request $request, Response $response): Response {
        try {
            $datos = $request->getParsedBody();
            $usuarioId = $request->getAttribute('usuario_id');
            $usuarioRolNivel = $request->getAttribute('usuario_rol_nivel');

            // Validar datos requeridos
            if (empty($datos['id_producto']) || !isset($datos['cantidad_real'])) {
                return $this->jsonResponse($response, [
                    'error' => true,
                    'mensaje' => 'Debe especificar producto y cantidad real del inventario'
                ], 400);
            }

            // Obtener sucursal del usuario
            $idSucursal = $datos['id_sucursal'] ?? $this->obtenerSucursalUsuario($usuarioId, $usuarioRolNivel);
            
            if (!$idSucursal) {
                return $this->jsonResponse($response, [
                    'error' => true,
                    'mensaje' => 'No se pudo determinar la sucursal'
                ], 400);
            }

            // Verificar acceso
            if ($usuarioRolNivel >= 30) {
                if (!$this->verificarAccesoSucursal($usuarioId, $idSucursal)) {
                    return $this->jsonResponse($response, [
                        'error' => true,
                        'mensaje' => 'No tiene acceso a esta sucursal'
                    ], 403);
                }
            }

            $stockModel = new StockSucursal($this->db);
            $resultado = $stockModel->registrarAjuste(
                $idSucursal,
                $datos['id_producto'],
                $datos['cantidad_real'] ?? 0,
                $usuarioId,
                $datos['observaciones'] ?? 'Ajuste por inventario físico',
                isset($datos['peso_real']) ? (float)$datos['peso_real'] : null
            );

            return $this->jsonResponse($response, [
                'error' => false,
                'mensaje' => 'Ajuste registrado exitosamente',
                'tipo_ajuste' => $resultado['tipo_ajuste'],
                'diferencia' => $resultado['diferencia'],
                'stock_anterior' => $resultado['stock_anterior'],
                'stock_actual' => $resultado['stock_actual']
            ]);

        } catch (\Exception $e) {
            error_log("Error al registrar ajuste: " . $e->getMessage());
            return $this->jsonResponse($response, [
                'error' => true,
                'mensaje' => $e->getMessage()
            ], 400);
        }
    }

    /**
     * Obtener historial de movimientos de stock de la sucursal
     * GET /stock-sucursal/historial
     */
    public function historial(Request $request, Response $response): Response {
        try {
            $usuarioId = $request->getAttribute('usuario_id');
            $usuarioRolNivel = $request->getAttribute('usuario_rol_nivel');
            $params = $request->getQueryParams();
            $limite = isset($params['limite']) ? intval($params['limite']) : 50;
            $idProductoFiltro = isset($params['id_producto']) ? intval($params['id_producto']) : null;

            // Obtener sucursal del querystring o del usuario
            $idSucursal = $params['id_sucursal'] ?? null;
            if (!$idSucursal) {
                $idSucursal = $this->obtenerSucursalUsuario($usuarioId, $usuarioRolNivel);
            }

            if (!$idSucursal) {
                return $this->jsonResponse($response, [
                    'error' => true,
                    'mensaje' => 'Usuario sin sucursal asignada'
                ], 400);
            }
            
            // Construir filtro opcional por producto
            $filtroProducto = $idProductoFiltro ? "AND ss.id_producto = :id_producto" : "";

            // Consultar movimientos
            $sql = "SELECT 
                        m.id AS id_movimiento,
                        m.tipo_movimiento,
                        m.cantidad,
                        m.peso,
                        m.referencia AS observaciones,
                        m.fecha AS fecha_movimiento,
                        m.usuario,
                        p.id AS id_producto,
                        p.codigo,
                        p.descripcion AS producto
                    FROM stock_sucursal_movimientos m
                    INNER JOIN stock_sucursal ss ON m.id_stock_sucursal = ss.id
                    INNER JOIN productos p ON ss.id_producto = p.id
                    WHERE ss.id_sucursal = :id_sucursal
                    $filtroProducto
                    ORDER BY m.fecha DESC
                    LIMIT :limite";
            
            $stmt = $this->db->prepare($sql);
            $stmt->bindValue(':id_sucursal', $idSucursal, \PDO::PARAM_INT);
            if ($idProductoFiltro) {
                $stmt->bindValue(':id_producto', $idProductoFiltro, \PDO::PARAM_INT);
            }
            $stmt->bindValue(':limite', $limite, \PDO::PARAM_INT);
            $stmt->execute();
            $movimientos = $stmt->fetchAll();
            
            return $this->jsonResponse($response, [
                'error' => false,
                'movimientos' => $movimientos
            ]);
            
        } catch (\Exception $e) {
            error_log("Error al obtener historial: " . $e->getMessage());
            return $this->jsonResponse($response, [
                'error' => true,
                'mensaje' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * GET /stock-sucursal/carga-inicial
     * Obtener todos los productos con su stock actual para carga inicial
     */
    public function obtenerProductosCargaInicial(Request $request, Response $response): Response {
        try {
            $usuarioId = $request->getAttribute('usuario_id');
            $usuarioRolNivel = $request->getAttribute('usuario_rol_nivel');
            $queryParams = $request->getQueryParams();

            $idSucursal = $queryParams['id_sucursal'] ?? $this->obtenerSucursalUsuario($usuarioId, $usuarioRolNivel);

            if (!$idSucursal) {
                return $this->jsonResponse($response, [
                    'error' => true,
                    'mensaje' => 'No se pudo determinar la sucursal'
                ], 400);
            }

            if ($usuarioRolNivel >= 30) {
                if (!$this->verificarAccesoSucursal($usuarioId, $idSucursal)) {
                    return $this->jsonResponse($response, [
                        'error' => true,
                        'mensaje' => 'No tiene acceso a esta sucursal'
                    ], 403);
                }
            }

            $stmt = $this->db->prepare("
                SELECT
                    p.id as id_producto,
                    p.codigo,
                    p.descripcion,
                    tp.nombre as familia,
                    tp.id as id_familia,
                    COALESCE(ss.cantidad_actual, 0) as cantidad_actual,
                    ss.ultima_actualizacion
                FROM productos p
                LEFT JOIN tipo_producto tp ON p.id_tipo_producto = tp.id
                LEFT JOIN stock_sucursal ss ON ss.id_producto = p.id AND ss.id_sucursal = ?
                WHERE p.activo = 1 AND p.disponible_franquicias = 1
                ORDER BY tp.nombre, p.descripcion
            ");
            $stmt->execute([$idSucursal]);
            $productos = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            return $this->jsonResponse($response, [
                'error' => false,
                'productos' => $productos,
                'total' => count($productos),
                'id_sucursal' => (int)$idSucursal
            ]);

        } catch (\Exception $e) {
            error_log("Error en carga inicial GET: " . $e->getMessage());
            return $this->jsonResponse($response, [
                'error' => true,
                'mensaje' => 'Error al obtener productos'
            ], 500);
        }
    }

    /**
     * POST /stock-sucursal/carga-inicial
     * Carga masiva de stock inicial para una sucursal
     */
    public function cargaInicial(Request $request, Response $response): Response {
        try {
            $datos = json_decode($request->getBody()->getContents(), true);
            $usuarioId = $request->getAttribute('usuario_id');
            $usuarioRolNivel = $request->getAttribute('usuario_rol_nivel');

            if (empty($datos['productos']) || !is_array($datos['productos'])) {
                return $this->jsonResponse($response, [
                    'error' => true,
                    'mensaje' => 'Se requiere lista de productos'
                ], 400);
            }

            $idSucursal = $datos['id_sucursal'] ?? $this->obtenerSucursalUsuario($usuarioId, $usuarioRolNivel);

            if (!$idSucursal) {
                return $this->jsonResponse($response, [
                    'error' => true,
                    'mensaje' => 'No se pudo determinar la sucursal'
                ], 400);
            }

            if ($usuarioRolNivel >= 30) {
                if (!$this->verificarAccesoSucursal($usuarioId, $idSucursal)) {
                    return $this->jsonResponse($response, [
                        'error' => true,
                        'mensaje' => 'No tiene acceso a esta sucursal'
                    ], 403);
                }
            }

            $stockModel = new StockSucursal($this->db);
            $actualizados = 0;
            $sinCambio = 0;
            $errores = [];
            $observaciones = $datos['observaciones'] ?? 'Carga de stock inicial';

            foreach ($datos['productos'] as $item) {
                if (!isset($item['id_producto']) || !isset($item['cantidad'])) continue;
                $cantidad = (float)$item['cantidad'];
                if ($cantidad < 0) continue;

                try {
                    $resultado = $stockModel->registrarAjuste(
                        (int)$idSucursal,
                        (int)$item['id_producto'],
                        $cantidad,
                        $usuarioId,
                        $observaciones
                    );
                    if ($resultado['tipo_ajuste'] === 'SIN_CAMBIO') {
                        $sinCambio++;
                    } else {
                        $actualizados++;
                    }
                } catch (\Exception $e) {
                    $errores[] = "Producto #{$item['id_producto']}: " . $e->getMessage();
                }
            }

            return $this->jsonResponse($response, [
                'error' => false,
                'mensaje' => "Stock inicial guardado: {$actualizados} producto(s) actualizado(s)",
                'actualizados' => $actualizados,
                'sin_cambio' => $sinCambio,
                'errores' => $errores
            ]);

        } catch (\Exception $e) {
            error_log("Error en carga inicial POST: " . $e->getMessage());
            return $this->jsonResponse($response, [
                'error' => true,
                'mensaje' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * GET /stock-sucursal/bandejas/{idProducto}
     * Lista de bandejas individuales disponibles para un producto por peso
     */
    public function obtenerBandejas(Request $request, Response $response, array $args): Response {
        try {
            $idProducto = (int)$args['idProducto'];
            $queryParams = $request->getQueryParams();
            $usuarioId = $request->getAttribute('usuario_id');
            $usuarioRolNivel = $request->getAttribute('usuario_rol_nivel');

            $idSucursal = $queryParams['id_sucursal'] ?? $this->obtenerSucursalUsuario($usuarioId, $usuarioRolNivel);

            if (!$idSucursal) {
                return $this->jsonResponse($response, [
                    'error' => true,
                    'mensaje' => 'No se pudo determinar la sucursal'
                ], 400);
            }

            if ($usuarioRolNivel >= 30 && !$this->verificarAccesoSucursal($usuarioId, $idSucursal)) {
                return $this->jsonResponse($response, [
                    'error' => true,
                    'mensaje' => 'No tiene acceso a esta sucursal'
                ], 403);
            }

            $stockModel = new StockSucursal($this->db);
            $resultado  = $stockModel->obtenerBandejas($idSucursal, $idProducto);

            return $this->jsonResponse($response, [
                'error'      => false,
                'id_producto' => $idProducto,
                'bandejas'   => $resultado['bandejas'],
                'cantidad'   => count($resultado['bandejas']),
                'peso_total' => $resultado['peso_total']
            ]);

        } catch (\Exception $e) {
            error_log("Error al obtener bandejas: " . $e->getMessage());
            return $this->jsonResponse($response, [
                'error'  => true,
                'mensaje' => 'Error al obtener bandejas'
            ], 500);
        }
    }

    /**
     * POST /stock-sucursal/baja-barcode
     * Baja de una sola bandeja escaneada por código de barras (tipo 21, peso).
     * Valida contra recepcion_items para prevenir doble-baja.
     * Body: { id_producto, peso, tipo_baja, observaciones? }
     */
    public function registrarBajaBarcode(Request $request, Response $response): Response {
        try {
            $datos = $request->getParsedBody() ?? [];
            $usuarioId       = $request->getAttribute('usuario_id');
            $usuarioRolNivel = $request->getAttribute('usuario_rol_nivel');

            if (empty($datos['id_producto']) || !isset($datos['peso']) || empty($datos['tipo_baja'])) {
                return $this->jsonResponse($response, [
                    'error'  => true,
                    'mensaje' => 'Faltan datos requeridos (id_producto, peso, tipo_baja)'
                ], 400);
            }

            $queryParams = $request->getQueryParams();
            $idSucursal  = $datos['id_sucursal'] ?? $queryParams['id_sucursal'] ?? $this->obtenerSucursalUsuario($usuarioId, $usuarioRolNivel);
            if (!$idSucursal) {
                return $this->jsonResponse($response, [
                    'error'  => true,
                    'mensaje' => 'No se pudo determinar la sucursal'
                ], 400);
            }

            if ($usuarioRolNivel >= 30 && !$this->verificarAccesoSucursal($usuarioId, $idSucursal)) {
                return $this->jsonResponse($response, [
                    'error'  => true,
                    'mensaje' => 'No tiene acceso a esta sucursal'
                ], 403);
            }

            $stockModel = new StockSucursal($this->db);
            $resultado  = $stockModel->registrarBajaBarcodePeso(
                (int)$idSucursal,
                (int)$datos['id_producto'],
                (float)$datos['peso'],
                $datos['tipo_baja'],
                $usuarioId,
                $datos['observaciones'] ?? null
            );

            return $this->jsonResponse($response, [
                'error'          => false,
                'mensaje'        => 'Baja registrada exitosamente',
                'stock_anterior' => $resultado['stock_anterior'],
                'stock_actual'   => $resultado['stock_actual'],
                'peso_bajado'    => $resultado['peso_bajado'],
                'es_peso'        => true
            ]);

        } catch (\Exception $e) {
            error_log("Error en baja barcode: " . $e->getMessage());
            return $this->jsonResponse($response, [
                'error'  => true,
                'mensaje' => $e->getMessage()
            ], 400);
        }
    }

    /**
     * POST /stock-sucursal/baja-bandejas
     * Baja de múltiples bandejas seleccionadas manualmente desde la UI.
     * Valida que no hayan sido dadas de baja anteriormente.
     * Body: { id_producto, recepcion_item_ids: [1,2,...], tipo_baja, observaciones? }
     */
    public function registrarBajaBandejas(Request $request, Response $response): Response {
        try {
            $datos = $request->getParsedBody() ?? [];
            $usuarioId       = $request->getAttribute('usuario_id');
            $usuarioRolNivel = $request->getAttribute('usuario_rol_nivel');

            if (empty($datos['id_producto']) || empty($datos['recepcion_item_ids']) || empty($datos['tipo_baja'])) {
                return $this->jsonResponse($response, [
                    'error'  => true,
                    'mensaje' => 'Faltan datos requeridos (id_producto, recepcion_item_ids, tipo_baja)'
                ], 400);
            }

            if (!is_array($datos['recepcion_item_ids'])) {
                return $this->jsonResponse($response, [
                    'error'  => true,
                    'mensaje' => 'recepcion_item_ids debe ser un arreglo'
                ], 400);
            }

            $queryParams = $request->getQueryParams();
            $idSucursal  = $datos['id_sucursal'] ?? $queryParams['id_sucursal'] ?? $this->obtenerSucursalUsuario($usuarioId, $usuarioRolNivel);
            if (!$idSucursal) {
                return $this->jsonResponse($response, [
                    'error'  => true,
                    'mensaje' => 'No se pudo determinar la sucursal'
                ], 400);
            }

            if ($usuarioRolNivel >= 30 && !$this->verificarAccesoSucursal($usuarioId, $idSucursal)) {
                return $this->jsonResponse($response, [
                    'error'  => true,
                    'mensaje' => 'No tiene acceso a esta sucursal'
                ], 403);
            }

            $ids = array_map('intval', $datos['recepcion_item_ids']);

            $stockModel = new StockSucursal($this->db);
            $resultado  = $stockModel->registrarBajaBandejas(
                (int)$idSucursal,
                (int)$datos['id_producto'],
                $ids,
                $datos['tipo_baja'],
                $usuarioId,
                $datos['observaciones'] ?? null
            );

            return $this->jsonResponse($response, [
                'error'          => false,
                'mensaje'        => 'Baja registrada exitosamente',
                'stock_anterior' => $resultado['stock_anterior'],
                'stock_actual'   => $resultado['stock_actual'],
                'peso_bajado'    => $resultado['peso_bajado'],
                'bandejas'       => $resultado['bandejas'],
                'es_peso'        => true
            ]);

        } catch (\Exception $e) {
            error_log("Error en baja bandejas: " . $e->getMessage());
            return $this->jsonResponse($response, [
                'error'  => true,
                'mensaje' => $e->getMessage()
            ], 400);
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
