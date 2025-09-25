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
            
            // Determinar el estado según si es un movimiento original o derivado
            $estadoId = $movimientoItemOrigenId ? 2 : 1; // 1=NUEVO, 2=ENVIADO
            
            $sql = "INSERT INTO estados_items_movimientos (id_estados, id_movimientos_items, fecha_alta) 
                    VALUES (:estadoId, :itemId, NOW())";
            $stmt = $this->db->prepare($sql);
            $stmt->bindParam(':estadoId', $estadoId);
            $stmt->bindParam(':itemId', $itemId);
            $stmt->execute();
            
            $this->db->commit();
            return $itemId;
        } catch (\Exception $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    public function obtenerMovimientosDeposito($fecha) {
        $sql = "SELECT m.id, m.fechaAlta, p.codigo, p.descripcion, mi.cnt, mi.cnt_peso, e.nombre as estado
                FROM movimientos m
                JOIN movimientos_items mi ON mi.id_movimientos = m.id
                JOIN productos p ON p.id = mi.id_productos
                JOIN estados_items_movimientos eim ON eim.id_movimientos_items = mi.id
                JOIN estados e ON e.id = eim.id_estados
                WHERE DATE(m.fechaAlta) = :fecha
                AND m.id_ubicacion_destino = 1
                ORDER BY m.fechaAlta DESC";
        
        $stmt = $this->db->prepare($sql);
        $stmt->bindParam(':fecha', $fecha);
        $stmt->execute();
        
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    public function buscarMovimientos($fechaDesde, $fechaHasta, $ubicacion, $estado) {
        $sql = "SELECT m.id, m.fechaAlta, 
                       uo.nombre as ubicacion_origen, ud.nombre as ubicacion_destino,
                       p.codigo, p.descripcion, mi.cnt, mi.cnt_peso, 
                       e.nombre as estado
                FROM movimientos m
                LEFT JOIN ubicaciones uo ON uo.id = m.id_ubicacion_origen
                JOIN ubicaciones ud ON ud.id = m.id_ubicacion_destino
                JOIN movimientos_items mi ON mi.id_movimientos = m.id
                JOIN productos p ON p.id = mi.id_productos
                JOIN estados_items_movimientos eim ON eim.id_movimientos_items = mi.id
                JOIN estados e ON e.id = eim.id_estados
                WHERE 1=1";
        
        $params = [];
        
        if ($fechaDesde) {
            $sql .= " AND DATE(m.fechaAlta) >= :fechaDesde";
            $params[':fechaDesde'] = $fechaDesde;
        }
        
        if ($fechaHasta) {
            $sql .= " AND DATE(m.fechaAlta) <= :fechaHasta";
            $params[':fechaHasta'] = $fechaHasta;
        }
        
        if ($ubicacion) {
            $sql .= " AND (m.id_ubicacion_origen = :ubicacion OR m.id_ubicacion_destino = :ubicacion)";
            $params[':ubicacion'] = $ubicacion;
        }
        
        if ($estado) {
            $sql .= " AND e.id = :estado";
            $params[':estado'] = $estado;
        }
        
        $sql .= " ORDER BY m.fechaAlta DESC";
        
        $stmt = $this->db->prepare($sql);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        $stmt->execute();
        
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    public function verificarDuplicado($productoId, $cantidad, $peso, $fecha) {
        $sql = "SELECT COUNT(*) as total
                FROM movimientos m
                JOIN movimientos_items mi ON mi.id_movimientos = m.id
                WHERE DATE(m.fechaAlta) = :fecha
                AND m.id_ubicacion_destino = 1
                AND mi.id_productos = :producto_id
                AND mi.cnt = :cantidad
                AND ABS(mi.cnt_peso - :peso) < 0.001"; // Tolerancia para comparación de números decimales
        
        $stmt = $this->db->prepare($sql);
        $stmt->bindParam(':fecha', $fecha);
        $stmt->bindParam(':producto_id', $productoId);
        $stmt->bindParam(':cantidad', $cantidad);
        $stmt->bindParam(':peso', $peso);
        $stmt->execute();
        
        $resultado = $stmt->fetch(\PDO::FETCH_ASSOC);
        return $resultado['total'] > 0;
    }
}