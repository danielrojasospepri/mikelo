<?php
namespace App\Controller;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use App\Model\Envio;

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
            return responseJson($response, [
                'success' => true,
                'id' => $envioId,
                'mensaje' => 'Envío creado exitosamente'
            ], 201);
        } catch (\Exception $e) {
            return responseJson($response, ['error' => $e->getMessage()], 500);
        }
    }

    public function listar(Request $request, Response $response) {
        $params = $request->getQueryParams();
        $filtros = [
            'fechaDesde' => $params['fechaDesde'] ?? null,
            'fechaHasta' => $params['fechaHasta'] ?? null,
            'destino' => $params['destino'] ?? null,
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
            $rutaArchivo = $this->envio->exportarPDF($id, $filtros);
            return responseJson($response, [
                'success' => true,
                'url' => $rutaArchivo
            ]);
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
            $rutaArchivo = $this->envio->exportarExcel($id, $filtros);
            return responseJson($response, [
                'success' => true,
                'url' => $rutaArchivo
            ]);
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
                'mensaje' => 'Envío confirmado exitosamente'
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