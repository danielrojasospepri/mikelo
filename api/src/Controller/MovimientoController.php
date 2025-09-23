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
                $data['movimiento_item_origen_id'] ?? null
            );
            
            return responseJson($response, [
                'id' => $itemId,
                'mensaje' => 'Item agregado exitosamente'
            ], 201);
        } catch (\Exception $e) {
            return responseJson($response, ['error' => $e->getMessage()], 500);
        }
    }
}