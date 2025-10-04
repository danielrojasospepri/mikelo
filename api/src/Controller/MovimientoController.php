<?php
namespace App\Controller;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use App\Model\Movimiento;

class MovimientoController {
    private $movimiento;
    private $db;

    public function __construct($db) {
        $this->db = $db;
        $this->movimiento = new Movimiento($db);
    }

    public function crear(Request $request, Response $response) {
        $data = json_decode($request->getBody()->getContents(), true);
        
        if (!isset($data['ubicacion_destino'])) {
            return responseJson($response, ['error' => 'La ubicación destino es requerida'], 400);
        }

        try {
            $movimientoId = $this->movimiento->crear(
                $data['ubicacion_origen'] ?? null,
                $data['ubicacion_destino'],
                'usuario_temporal'
            );
            
            return responseJson($response, [
                'id' => $movimientoId,
                'mensaje' => 'Movimiento creado exitosamente'
            ], 201);
        } catch (\Exception $e) {
            return responseJson($response, ['error' => $e->getMessage()], 500);
        }
    }

    public function agregarItem(Request $request, Response $response, $args) {
        $data = json_decode($request->getBody()->getContents(), true);
        
        if (!isset($data['producto_id']) || !isset($data['cantidad'])) {
            return responseJson($response, ['error' => 'Producto y cantidad son requeridos'], 400);
        }

        try {
            $itemId = $this->movimiento->agregarItem(
                $args['id'],
                $data['producto_id'],
                $data['cantidad'],
                $data['cantidad_peso'] ?? 0,
                $data['movimiento_item_origen_id'] ?? null,
                $data['id_contenedor'] ?? null
            );
            
            return responseJson($response, [
                'id' => $itemId,
                'mensaje' => 'Item agregado exitosamente'
            ], 201);
        } catch (\Exception $e) {
            return responseJson($response, ['error' => $e->getMessage()], 500);
        }
    }

    public function obtenerMovimientosDeposito(Request $request, Response $response, $args) {
        try {
            $fecha = $args['fecha'] ?? date('Y-m-d');
            $movimientos = $this->movimiento->obtenerMovimientosDeposito($fecha);
            return responseJson($response, ['movimientos' => $movimientos]);
        } catch (\Exception $e) {
            return responseJson($response, ['error' => $e->getMessage()], 500);
        }
    }

    public function buscarMovimientos(Request $request, Response $response) {
        $params = $request->getQueryParams();
        $fechaDesde = $params['fecha_desde'] ?? null;
        $fechaHasta = $params['fecha_hasta'] ?? null;
        $ubicacion = $params['ubicacion'] ?? null;
        $estado = $params['estado'] ?? null;
        $producto = $params['producto'] ?? null;

        try {
            $movimientos = $this->movimiento->buscarMovimientos($fechaDesde, $fechaHasta, $ubicacion, $estado, $producto);
            return responseJson($response, ['movimientos' => $movimientos]);
        } catch (\Exception $e) {
            return responseJson($response, ['error' => $e->getMessage()], 500);
        }
    }

    public function verificarDuplicado(Request $request, Response $response) {
        $data = json_decode($request->getBody()->getContents(), true);
        
        if (!isset($data['producto_id']) || !isset($data['cantidad']) || !isset($data['peso']) || !isset($data['fecha'])) {
            return responseJson($response, ['error' => 'Faltan datos requeridos'], 400);
        }

        try {
            $duplicado = $this->movimiento->verificarDuplicado(
                $data['producto_id'],
                $data['cantidad'],
                $data['peso'],
                $data['fecha']
            );
            
            return responseJson($response, [
                'duplicado' => $duplicado,
                'mensaje' => $duplicado ? 'Se encontró un registro similar' : 'No hay registros similares'
            ]);
        } catch (\Exception $e) {
            return responseJson($response, ['error' => $e->getMessage()], 500);
        }
    }

    public function exportarPDF(Request $request, Response $response) {
        $params = $request->getQueryParams();
        $filtros = [
            'fecha_desde' => $params['fecha_desde'] ?? null,
            'fecha_hasta' => $params['fecha_hasta'] ?? null,
            'ubicacion' => $params['ubicacion'] ?? null,
            'estado' => $params['estado'] ?? null,
            'producto' => $params['producto'] ?? null
        ];

        try {
            $rutaArchivo = $this->movimiento->exportarPDF($filtros);
            return responseJson($response, [
                'success' => true,
                'url' => $rutaArchivo
            ]);
        } catch (\Exception $e) {
            return responseJson($response, ['error' => $e->getMessage()], 500);
        }
    }

    public function exportarExcel(Request $request, Response $response) {
        $params = $request->getQueryParams();
        $filtros = [
            'fecha_desde' => $params['fecha_desde'] ?? null,
            'fecha_hasta' => $params['fecha_hasta'] ?? null,
            'ubicacion' => $params['ubicacion'] ?? null,
            'estado' => $params['estado'] ?? null,
            'producto' => $params['producto'] ?? null
        ];

        try {
            $rutaArchivo = $this->movimiento->exportarExcel($filtros);
            return responseJson($response, [
                'success' => true,
                'url' => $rutaArchivo
            ]);
        } catch (\Exception $e) {
            return responseJson($response, ['error' => $e->getMessage()], 500);
        }
    }
}