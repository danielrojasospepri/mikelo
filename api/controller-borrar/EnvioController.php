<?php

namespace App\Controller;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use PDO;

class EnvioController {
    private $db;

    public function __construct(PDO $db) {
        $this->db = $db;
    }

    public function obtenerProductosDisponibles(Request $request, Response $response) {
        try {
            $sql = "SELECT mi.id as id_movimiento_item, 
                          mi.id_producto,
                          p.codigo,
                          p.descripcion,
                          mi.cnt,
                          mi.cnt_peso,
                          mi.id_contenedor,
                          c.nombre as contenedor
                   FROM movimientos_items mi
                   LEFT JOIN productos p ON mi.id_producto = p.id
                   LEFT JOIN contenedores c ON mi.id_contenedor = c.id
                   WHERE NOT EXISTS (
                       SELECT 1 
                       FROM movimientos_items mi2 
                       WHERE mi2.id_movimiento_item_origen = mi.id
                   )
                   AND mi.id_movimiento_item_origen IS NULL
                   AND mi.estado = 'activo'
                   ORDER BY p.codigo";

            $stmt = $this->db->prepare($sql);
            $stmt->execute();
            $productos = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $response->getBody()->write(json_encode([
                'success' => true,
                'data' => $productos
            ]));

            return $response->withHeader('Content-Type', 'application/json');
        } catch (\Exception $e) {
            $response->getBody()->write(json_encode([
                'success' => false,
                'error' => $e->getMessage()
            ]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
        }
    }

    public function crear(Request $request, Response $response) {
        try {
            $data = $request->getParsedBody();
            $destino = $data['destino'];
            $productos = $data['productos'];

            $this->db->beginTransaction();

            // Crear el envío
            $sql = "INSERT INTO movimientos (tipo, fecha_alta, destino) VALUES ('envio', NOW(), ?)";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$destino]);
            $idEnvio = $this->db->lastInsertId();

            // Procesar cada producto
            foreach ($productos as $producto) {
                // Insertar el item del envío
                $sql = "INSERT INTO movimientos_items (
                            id_movimiento,
                            id_producto,
                            cnt,
                            cnt_peso,
                            estado,
                            id_movimiento_item_origen
                        ) VALUES (?, ?, ?, ?, 'enviado', ?)";

                $stmt = $this->db->prepare($sql);
                $stmt->execute([
                    $idEnvio,
                    $producto['id_productos'],
                    $producto['cantidad'],
                    $producto['peso'],
                    $producto['id_movimientos_items_origen']
                ]);

                // Actualizar el estado del item original
                $sql = "UPDATE movimientos_items 
                       SET estado = 'enviado'
                       WHERE id = ?";
                $stmt = $this->db->prepare($sql);
                $stmt->execute([$producto['id_movimientos_items_origen']]);
            }

            $this->db->commit();

            $response->getBody()->write(json_encode([
                'success' => true,
                'message' => 'Envío creado correctamente',
                'id' => $idEnvio
            ]));

            return $response->withHeader('Content-Type', 'application/json');
        } catch (\Exception $e) {
            $this->db->rollBack();
            $response->getBody()->write(json_encode([
                'success' => false,
                'error' => $e->getMessage()
            ]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
        }
    }

    public function listar(Request $request, Response $response) {
        try {
            $sql = "SELECT e.id,
                          e.fecha_alta,
                          e.destino,
                          COUNT(mi.id) as cantidad_items,
                          SUM(mi.cnt_peso) as peso_total,
                          mi.estado as ultimo_estado
                   FROM movimientos e
                   LEFT JOIN movimientos_items mi ON e.id = mi.id_movimiento
                   WHERE e.tipo = 'envio'
                   GROUP BY e.id
                   ORDER BY e.fecha_alta DESC";

            $stmt = $this->db->prepare($sql);
            $stmt->execute();
            $envios = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $response->getBody()->write(json_encode([
                'success' => true,
                'data' => $envios
            ]));

            return $response->withHeader('Content-Type', 'application/json');
        } catch (\Exception $e) {
            $response->getBody()->write(json_encode([
                'success' => false,
                'error' => $e->getMessage()
            ]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
        }
    }

    public function obtenerDetalle(Request $request, Response $response, $args) {
        try {
            // Obtener información del envío
            $sql = "SELECT m.id,
                          m.fecha_alta,
                          m.destino,
                          u.nombre as destino_nombre
                   FROM movimientos m
                   LEFT JOIN ubicaciones u ON m.destino = u.id
                   WHERE m.id = ? AND m.tipo = 'envio'";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$args['id']]);
            $envio = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$envio) {
                throw new \Exception('Envío no encontrado');
            }

            // Obtener los productos del envío
            $sql = "SELECT mi.id,
                          mi.cnt,
                          mi.cnt_peso,
                          mi.estado,
                          p.codigo,
                          p.descripcion,
                          c.nombre as contenedor,
                          mio.cnt_peso as peso_neto
                   FROM movimientos_items mi
                   LEFT JOIN productos p ON mi.id_producto = p.id
                   LEFT JOIN movimientos_items mio ON mi.id_movimiento_item_origen = mio.id
                   LEFT JOIN contenedores c ON mio.id_contenedor = c.id
                   WHERE mi.id_movimiento = ?
                   ORDER BY p.codigo";

            $stmt = $this->db->prepare($sql);
            $stmt->execute([$args['id']]);
            $productos = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Calcular totales
            $totales = [
                'cantidad_total' => 0,
                'peso_bruto_total' => 0,
                'peso_neto_total' => 0
            ];

            foreach ($productos as $producto) {
                $totales['cantidad_total'] += $producto['cnt'];
                $totales['peso_bruto_total'] += $producto['cnt_peso'];
                $totales['peso_neto_total'] += $producto['peso_neto'] ?? $producto['cnt_peso'];
            }

            $response->getBody()->write(json_encode([
                'success' => true,
                'data' => [
                    'envio' => $envio,
                    'productos' => $productos,
                    'totales' => $totales
                ]
            ]));

            return $response->withHeader('Content-Type', 'application/json');
        } catch (\Exception $e) {
            $response->getBody()->write(json_encode([
                'success' => false,
                'error' => $e->getMessage()
            ]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
        }
    }
}