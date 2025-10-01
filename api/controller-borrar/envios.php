<?php
require_once '../config.php';
require_once '../model/Database.php';
require_once '../model/Envio.php';
require_once '../comun.php';

class EnvioController {
    private $model;

    public function __construct() {
        $this->model = new Envio();
    }

    public function crearEnvio() {
        $data = json_decode(file_get_contents('php://input'), true);
        
        if (!isset($data['destino']) || !isset($data['productos'])) {
            http_response_code(400);
            return ['error' => 'Datos incompletos'];
        }

        try {
            $result = $this->model->crearEnvio($data['destino'], $data['productos']);
            return ['success' => true, 'id' => $result];
        } catch (Exception $e) {
            http_response_code(500);
            return ['error' => $e->getMessage()];
        }
    }

    public function obtenerEnvios() {
        $filtros = [];
        if (isset($_GET['fechaDesde'])) $filtros['fechaDesde'] = $_GET['fechaDesde'];
        if (isset($_GET['fechaHasta'])) $filtros['fechaHasta'] = $_GET['fechaHasta'];
        if (isset($_GET['destino'])) $filtros['destino'] = $_GET['destino'];
        if (isset($_GET['estado'])) $filtros['estado'] = $_GET['estado'];

        try {
            $envios = $this->model->obtenerEnvios($filtros);
            return ['success' => true, 'data' => $envios];
        } catch (Exception $e) {
            http_response_code(500);
            return ['error' => $e->getMessage()];
        }
    }

    public function obtenerDetalleEnvio($id) {
        try {
            $detalle = $this->model->obtenerDetalleEnvio($id);
            return ['success' => true, 'data' => $detalle];
        } catch (Exception $e) {
            http_response_code(500);
            return ['error' => $e->getMessage()];
        }
    }

    public function obtenerProductosDisponibles() {
        try {
            $productos = $this->model->obtenerProductosDisponibles();
            return ['success' => true, 'data' => $productos];
        } catch (Exception $e) {
            http_response_code(500);
            return ['error' => $e->getMessage()];
        }
    }

    public function obtenerContenedores() {
        try {
            $contenedores = $this->model->obtenerContenedores();
            return ['success' => true, 'data' => $contenedores];
        } catch (Exception $e) {
            http_response_code(500);
            return ['error' => $e->getMessage()];
        }
    }

    public function exportarPDF($id = null) {
        try {
            $filtros = [];
            if (isset($_GET['fechaDesde'])) $filtros['fechaDesde'] = $_GET['fechaDesde'];
            if (isset($_GET['fechaHasta'])) $filtros['fechaHasta'] = $_GET['fechaHasta'];
            if (isset($_GET['destino'])) $filtros['destino'] = $_GET['destino'];
            if (isset($_GET['estado'])) $filtros['estado'] = $_GET['estado'];

            $rutaArchivo = $this->model->exportarPDF($id, $filtros);
            return ['success' => true, 'url' => $rutaArchivo];
        } catch (Exception $e) {
            http_response_code(500);
            return ['error' => $e->getMessage()];
        }
    }

    public function exportarExcel($id = null) {
        try {
            $filtros = [];
            if (isset($_GET['fechaDesde'])) $filtros['fechaDesde'] = $_GET['fechaDesde'];
            if (isset($_GET['fechaHasta'])) $filtros['fechaHasta'] = $_GET['fechaHasta'];
            if (isset($_GET['destino'])) $filtros['destino'] = $_GET['destino'];
            if (isset($_GET['estado'])) $filtros['estado'] = $_GET['estado'];

            $rutaArchivo = $this->model->exportarExcel($id, $filtros);
            return ['success' => true, 'url' => $rutaArchivo];
        } catch (Exception $e) {
            http_response_code(500);
            return ['error' => $e->getMessage()];
        }
    }
}

// Manejo de la solicitud
$controller = new EnvioController();
$action = $_GET['action'] ?? '';

header('Content-Type: application/json');

switch ($action) {
    case 'crear':
        echo json_encode($controller->crearEnvio());
        break;
    
    case 'listar':
        echo json_encode($controller->obtenerEnvios());
        break;
    
    case 'detalle':
        $id = $_GET['id'] ?? null;
        if (!$id) {
            http_response_code(400);
            echo json_encode(['error' => 'ID no proporcionado']);
            break;
        }
        echo json_encode($controller->obtenerDetalleEnvio($id));
        break;
    
    case 'productos-disponibles':
        echo json_encode($controller->obtenerProductosDisponibles());
        break;
    
    case 'contenedores':
        echo json_encode($controller->obtenerContenedores());
        break;
    
    case 'exportar-pdf':
        $id = $_GET['id'] ?? null;
        echo json_encode($controller->exportarPDF($id));
        break;
    
    case 'exportar-excel':
        $id = $_GET['id'] ?? null;
        echo json_encode($controller->exportarExcel($id));
        break;
    
    default:
        http_response_code(404);
        echo json_encode(['error' => 'Acción no encontrada']);
        break;
}