<?php
namespace App\Controller;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use App\Model\Producto;

class ProductoController {
    private $producto;

    public function __construct($db) {
        $this->producto = new Producto($db);
    }

    public function buscar(Request $request, Response $response) {
        $params = $request->getQueryParams();
        $termino = $params['q'] ?? '';
        
        if (strlen($termino) < 2) {
            return responseJson($response, ['error' => 'El término de búsqueda debe tener al menos 2 caracteres'], 400);
        }

        try {
            $productos = $this->producto->buscarPorCodigoONombre($termino);
            return responseJson($response, ['productos' => $productos]);
        } catch (\Exception $e) {
            return responseJson($response, ['error' => $e->getMessage()], 500);
        }
    }
}