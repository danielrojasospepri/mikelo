<?php
namespace App\Model;

class Movimiento {
    private $db;

    public function __construct($db) {
        $this->db = $db;
    }

    public function crear($ubicacionOrigen, $ubicacionDestino, $usuario) {
        $this->db->beginTransaction();
        try {
            $sql = "INSERT INTO movimientos (fechaAlta, id_ubicacion_origen, id_ubicacion_destino, usuario_alta) 
                    VALUES (NOW(), :origen, :destino, :usuario)";
            $stmt = $this->db->prepare($sql);
            $stmt->bindParam(':origen', $ubicacionOrigen);
            $stmt->bindParam(':destino', $ubicacionDestino);
            $stmt->bindParam(':usuario', $usuario);
            $stmt->execute();
            
            $movimientoId = $this->db->lastInsertId();
            $this->db->commit();
            return $movimientoId;
        } catch (\Exception $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    public function agregarItem($movimientoId, $productoId, $cantidad, $cantidadPeso = 0, $movimientoItemOrigenId = null) {
        $this->db->beginTransaction();
        try {
            $sql = "INSERT INTO movimientos_items (id_movimientos, id_productos, cnt, cnt_peso, id_movimientos_items_origen) 
                    VALUES (:movimiento, :producto, :cnt, :peso, :origen)";
            $stmt = $this->db->prepare($sql);
            $stmt->bindParam(':movimiento', $movimientoId);
            $stmt->bindParam(':producto', $productoId);
            $stmt->bindParam(':cnt', $cantidad);
            $stmt->bindParam(':peso', $cantidadPeso);
            $stmt->bindParam(':origen', $movimientoItemOrigenId);
            $stmt->execute();
            
            $itemId = $this->db->lastInsertId();
            
            // Registrar estado NUEVO
            $sql = "INSERT INTO estados_items_movimientos (id_estados, id_movimientos_items, fecha_alta) 
                    VALUES (1, :itemId, NOW())";
            $stmt = $this->db->prepare($sql);
            $stmt->bindParam(':itemId', $itemId);
            $stmt->execute();
            
            $this->db->commit();
            return $itemId;
        } catch (\Exception $e) {
            $this->db->rollBack();
            throw $e;
        }
    }
}