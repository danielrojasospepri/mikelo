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

    public function buscarProductosNuevosEnDeposito($termino, $idDeposito) {
        $sql = "SELECT p.*, mi.id as movimiento_item_id 
                FROM productos p 
                INNER JOIN movimientos_items mi ON mi.id_productos = p.id 
                INNER JOIN movimientos m ON m.id = mi.id_movimientos 
                INNER JOIN estados_items_movimientos eim ON eim.id_movimientos_items = mi.id 
                WHERE (p.codigo LIKE :termino OR p.descripcion LIKE :termino) 
                AND p.activo = 1 
                AND m.id_ubicacion_destino = :deposito 
                AND eim.id_estados = 1 -- Estado NUEVO
                AND NOT EXISTS (
                    SELECT 1 FROM movimientos_items mi2 
                    WHERE mi2.id_movimientos_items_origen = mi.id
                )";
        
        $stmt = $this->db->prepare($sql);
        $termino = "%{$termino}%";
        $stmt->bindParam(':termino', $termino);
        $stmt->bindParam(':deposito', $idDeposito);
        $stmt->execute();
        return $stmt->fetchAll();
    }
}