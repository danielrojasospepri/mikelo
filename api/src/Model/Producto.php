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

    public function listarTodos() {
        $sql = "
            SELECT p.id, p.codigo, p.descripcion, p.observaciones, p.activo,
                   p.id_tipo_producto, tp.nombre as familia,
                   p.cantidad_predefinida, p.disponible_franquicias
            FROM productos p
            LEFT JOIN tipo_producto tp ON p.id_tipo_producto = tp.id
            ORDER BY p.codigo
        ";
        $stmt = $this->db->query($sql);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    public function obtenerPorId($id) {
        $stmt = $this->db->prepare("
            SELECT p.id, p.codigo, p.descripcion, p.observaciones, p.activo,
                   p.id_tipo_producto, tp.nombre as familia,
                   p.cantidad_predefinida, p.disponible_franquicias
            FROM productos p
            LEFT JOIN tipo_producto tp ON p.id_tipo_producto = tp.id
            WHERE p.id = ?
        ");
        $stmt->execute([(int)$id]);
        return $stmt->fetch(\PDO::FETCH_ASSOC);
    }

    public function crear($datos) {
        // Verificar código único
        $stmt = $this->db->prepare("SELECT id FROM productos WHERE codigo = ?");
        $stmt->execute([$datos['codigo']]);
        if ($stmt->fetch()) {
            throw new \Exception("Ya existe un producto con el código '{$datos['codigo']}'");
        }
        $stmt = $this->db->prepare("
            INSERT INTO productos (codigo, descripcion, observaciones, activo, id_tipo_producto, cantidad_predefinida, disponible_franquicias)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            trim($datos['codigo']),
            trim($datos['descripcion']),
            $datos['observaciones'] ?? null,
            isset($datos['activo']) ? (int)$datos['activo'] : 1,
            $datos['id_tipo_producto'] ? (int)$datos['id_tipo_producto'] : null,
            isset($datos['cantidad_predefinida']) ? (int)$datos['cantidad_predefinida'] : 0,
            isset($datos['disponible_franquicias']) ? (int)$datos['disponible_franquicias'] : 1,
        ]);
        return (int)$this->db->lastInsertId();
    }

    public function actualizar($id, $datos) {
        // Verificar código único (excepto propio)
        $stmt = $this->db->prepare("SELECT id FROM productos WHERE codigo = ? AND id != ?");
        $stmt->execute([$datos['codigo'], (int)$id]);
        if ($stmt->fetch()) {
            throw new \Exception("Ya existe otro producto con el código '{$datos['codigo']}'");
        }
        $stmt = $this->db->prepare("
            UPDATE productos SET
                codigo = ?, descripcion = ?, observaciones = ?, activo = ?,
                id_tipo_producto = ?, cantidad_predefinida = ?, disponible_franquicias = ?
            WHERE id = ?
        ");
        $stmt->execute([
            trim($datos['codigo']),
            trim($datos['descripcion']),
            $datos['observaciones'] ?? null,
            isset($datos['activo']) ? (int)$datos['activo'] : 1,
            $datos['id_tipo_producto'] ? (int)$datos['id_tipo_producto'] : null,
            isset($datos['cantidad_predefinida']) ? (int)$datos['cantidad_predefinida'] : 0,
            isset($datos['disponible_franquicias']) ? (int)$datos['disponible_franquicias'] : 1,
            (int)$id
        ]);
        return $stmt->rowCount();
    }

    public function eliminar($id) {
        // Verificar si tiene movimientos asociados
        $stmt = $this->db->prepare("SELECT COUNT(*) FROM movimientos_items WHERE id_productos = ?");
        $stmt->execute([(int)$id]);
        if ($stmt->fetchColumn() > 0) {
            throw new \Exception("No se puede eliminar: el producto tiene movimientos asociados. Use 'desactivar' en su lugar.");
        }
        $stmt = $this->db->prepare("DELETE FROM productos WHERE id = ?");
        $stmt->execute([(int)$id]);
        return $stmt->rowCount();
    }

    // ---- Familia (tipo_producto) CRUD ----

    public function listarFamilias() {
        $sql = "
            SELECT tp.id, tp.nombre, tp.descripcion,
                   COUNT(p.id) as cantidad_productos
            FROM tipo_producto tp
            LEFT JOIN productos p ON p.id_tipo_producto = tp.id
            GROUP BY tp.id
            ORDER BY tp.nombre
        ";
        $stmt = $this->db->query($sql);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    public function crearFamilia($datos) {
        $stmt = $this->db->prepare("SELECT id FROM tipo_producto WHERE nombre = ?");
        $stmt->execute([trim($datos['nombre'])]);
        if ($stmt->fetch()) {
            throw new \Exception("Ya existe una familia con ese nombre");
        }
        $stmt = $this->db->prepare("INSERT INTO tipo_producto (nombre, descripcion) VALUES (?, ?)");
        $stmt->execute([trim($datos['nombre']), $datos['descripcion'] ?? null]);
        return (int)$this->db->lastInsertId();
    }

    public function actualizarFamilia($id, $datos) {
        $stmt = $this->db->prepare("SELECT id FROM tipo_producto WHERE nombre = ? AND id != ?");
        $stmt->execute([trim($datos['nombre']), (int)$id]);
        if ($stmt->fetch()) {
            throw new \Exception("Ya existe otra familia con ese nombre");
        }
        $stmt = $this->db->prepare("UPDATE tipo_producto SET nombre = ?, descripcion = ? WHERE id = ?");
        $stmt->execute([trim($datos['nombre']), $datos['descripcion'] ?? null, (int)$id]);
        return $stmt->rowCount();
    }

    public function eliminarFamilia($id) {
        $stmt = $this->db->prepare("SELECT COUNT(*) FROM productos WHERE id_tipo_producto = ?");
        $stmt->execute([(int)$id]);
        if ($stmt->fetchColumn() > 0) {
            throw new \Exception("No se puede eliminar: la familia tiene productos asociados");
        }
        $stmt = $this->db->prepare("DELETE FROM tipo_producto WHERE id = ?");
        $stmt->execute([(int)$id]);
        return $stmt->rowCount();
    }
}