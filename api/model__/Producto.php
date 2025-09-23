<?php
namespace App\Model;

class Producto {
    private $db;

    public function __construct($db) {
        $this->db = $db;
    }

    public function buscarPorCodigoONombre($termino) {
        $sql = "SELECT * FROM productos WHERE (codigo LIKE :termino OR descripcion LIKE :termino) AND activo = 1";
        $stmt = $this->db->prepare($sql);
        $termino = "%{$termino}%";
        $stmt->bindParam(':termino', $termino);
        $stmt->execute();
        return $stmt->fetchAll();
    }
}