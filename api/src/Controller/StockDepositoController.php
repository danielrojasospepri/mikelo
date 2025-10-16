<?php
namespace App\Controller;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use App\Model\StockDeposito;

class StockDepositoController {
    private $stockDeposito;
    private $db;

    public function __construct($db) {
        $this->db = $db;
        $this->stockDeposito = new StockDeposito($db);
        
        // Crear tablas de auditoría si no existen
        $this->stockDeposito->crearTablasAuditoria();
    }

    public function obtenerStock(Request $request, Response $response) {
        $params = $request->getQueryParams();
        $filtros = [
            'producto' => $params['producto'] ?? null,
            'contenedor' => $params['contenedor'] ?? null,
            'fechaDesde' => $params['fechaDesde'] ?? null,
            'fechaHasta' => $params['fechaHasta'] ?? null
        ];

        try {
            $stock = $this->stockDeposito->obtenerStockAgrupado($filtros);
            return responseJson($response, [
                'success' => true,
                'data' => $stock
            ]);
        } catch (\Exception $e) {
            return responseJson($response, ['error' => $e->getMessage()], 500);
        }
    }

    public function obtenerDetalleBandejas(Request $request, Response $response, $args) {
        $idProducto = $args['id'];
        $params = $request->getQueryParams();
        $filtros = [
            'fechaDesde' => $params['fechaDesde'] ?? null,
            'fechaHasta' => $params['fechaHasta'] ?? null
        ];

        try {
            $bandejas = $this->stockDeposito->obtenerDetalleBandejas($idProducto, $filtros);
            return responseJson($response, [
                'success' => true,
                'data' => $bandejas
            ]);
        } catch (\Exception $e) {
            return responseJson($response, ['error' => $e->getMessage()], 500);
        }
    }

    public function cambiarContenedor(Request $request, Response $response) {
        $data = json_decode($request->getBody()->getContents(), true);
        
        if (!isset($data['bandejas']) || !isset($data['nuevo_contenedor']) || !isset($data['motivo'])) {
            return responseJson($response, ['error' => 'Bandejas, nuevo contenedor y motivo son requeridos'], 400);
        }

        if (empty($data['bandejas'])) {
            return responseJson($response, ['error' => 'Debe seleccionar al menos una bandeja'], 400);
        }

        if (empty(trim($data['motivo']))) {
            return responseJson($response, ['error' => 'El motivo es requerido'], 400);
        }

        try {
            $resultado = $this->stockDeposito->cambiarContenedor(
                $data['bandejas'], 
                $data['nuevo_contenedor'], 
                $data['motivo']
            );
            
            return responseJson($response, [
                'success' => true,
                'mensaje' => 'Contenedor cambiado exitosamente para ' . count($data['bandejas']) . ' bandejas'
            ]);
        } catch (\Exception $e) {
            return responseJson($response, ['error' => $e->getMessage()], 500);
        }
    }

    public function darDeBaja(Request $request, Response $response) {
        $data = json_decode($request->getBody()->getContents(), true);
        
        if (!isset($data['bandejas']) || !isset($data['motivo'])) {
            return responseJson($response, ['error' => 'Bandejas y motivo son requeridos'], 400);
        }

        if (empty($data['bandejas'])) {
            return responseJson($response, ['error' => 'Debe seleccionar al menos una bandeja'], 400);
        }

        if (empty(trim($data['motivo']))) {
            return responseJson($response, ['error' => 'El motivo es requerido'], 400);
        }

        try {
            $resultado = $this->stockDeposito->darDeBaja($data['bandejas'], $data['motivo']);
            
            return responseJson($response, [
                'success' => true,
                'mensaje' => 'Se han dado de baja ' . count($data['bandejas']) . ' bandejas exitosamente'
            ]);
        } catch (\Exception $e) {
            return responseJson($response, ['error' => $e->getMessage()], 500);
        }
    }

    public function exportarPDF(Request $request, Response $response) {
        $params = $request->getQueryParams();
        $filtros = [
            'producto' => $params['producto'] ?? null,
            'contenedor' => $params['contenedor'] ?? null,
            'fechaDesde' => $params['fechaDesde'] ?? null,
            'fechaHasta' => $params['fechaHasta'] ?? null
        ];

        try {
            $rutaRelativa = $this->stockDeposito->exportarPDF($filtros);
            $rutaCompleta = __DIR__ . '/../../../' . $rutaRelativa;
            
            if (!file_exists($rutaCompleta)) {
                return responseJson($response, ['error' => 'Archivo no encontrado'], 404);
            }

            $nombreArchivo = basename($rutaRelativa);
            
            // Configurar headers para descarga directa (igual que EnvioController)
            $response = $response
                ->withHeader('Content-Type', 'application/pdf')
                ->withHeader('Content-Disposition', 'attachment; filename="' . $nombreArchivo . '"')
                ->withHeader('Content-Length', filesize($rutaCompleta));

            // Leer y enviar archivo
            $response->getBody()->write(file_get_contents($rutaCompleta));
            
            return $response;
        } catch (\Exception $e) {
            return responseJson($response, ['error' => $e->getMessage()], 500);
        }
    }

    public function exportarExcel(Request $request, Response $response) {
        $params = $request->getQueryParams();
        $filtros = [
            'producto' => $params['producto'] ?? null,
            'contenedor' => $params['contenedor'] ?? null,
            'fechaDesde' => $params['fechaDesde'] ?? null,
            'fechaHasta' => $params['fechaHasta'] ?? null
        ];

        try {
            $rutaRelativa = $this->stockDeposito->exportarExcel($filtros);
            $rutaCompleta = __DIR__ . '/../../../' . $rutaRelativa;
            
            if (!file_exists($rutaCompleta)) {
                return responseJson($response, ['error' => 'Archivo no encontrado'], 404);
            }

            $nombreArchivo = basename($rutaRelativa);
            
            // Configurar headers para descarga directa (igual que PDF)
            $response = $response
                ->withHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet')
                ->withHeader('Content-Disposition', 'attachment; filename="' . $nombreArchivo . '"')
                ->withHeader('Content-Length', filesize($rutaCompleta));

            // Leer y enviar archivo
            $response->getBody()->write(file_get_contents($rutaCompleta));
            
            return $response;
        } catch (\Exception $e) {
            return responseJson($response, ['error' => $e->getMessage()], 500);
        }
    }
}
?>