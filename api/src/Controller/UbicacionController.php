<?php
namespace App\Controller;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use App\Model\Ubicacion;

class UbicacionController {
    private $ubicacion;
    private $db;

    public function __construct($db) {
        $this->db = $db;
        $this->ubicacion = new Ubicacion($db);
    }

    public function listar(Request $request, Response $response) {
        try {
            $ubicaciones = $this->ubicacion->obtenerTodas();
            return responseJson($response, ['ubicaciones' => $ubicaciones]);
        } catch (\Exception $e) {
            return responseJson($response, ['error' => $e->getMessage()], 500);
        }
    }
}