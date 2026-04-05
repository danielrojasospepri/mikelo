<?php
namespace App\Model;

class Ubicacion {
    private $db;

    public function __construct($db) {
        $this->db = $db;
    }

    public function obtenerTodas() {
        $sql = "SELECT * FROM ubicaciones WHERE 1";
        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public function obtenerDepositoCentral() {
        $sql = "SELECT * FROM ubicaciones WHERE nombre LIKE '%Depósito%' OR nombre LIKE '%Deposito%' LIMIT 1";
        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        return $stmt->fetch();
    }

    public function obtenerSucursales() {
        $stmt = $this->db->prepare("
            SELECT * FROM ubicaciones
            WHERE tipo_ubicacion = 'sucursal'
            ORDER BY nombre ASC
        ");
        $stmt->execute();
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    public function obtenerPorId($id) {
        $stmt = $this->db->prepare("SELECT * FROM ubicaciones WHERE id = ?");
        $stmt->execute([(int)$id]);
        return $stmt->fetch(\PDO::FETCH_ASSOC);
    }

    public function crear($data) {
        $stmt = $this->db->prepare("
            INSERT INTO ubicaciones
                (nombre, tipo_ubicacion, razon_social, domicilio, localidad,
                 codigo_postal, provincia, cuit, condicion_iva, telefono, email, franquicia)
            VALUES (?, 'sucursal', ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            trim($data['nombre']),
            $data['razon_social']  ?? null,
            $data['domicilio']     ?? null,
            $data['localidad']     ?? null,
            $data['codigo_postal'] ?? null,
            $data['provincia']     ?? null,
            $data['cuit']          ?? null,
            $data['condicion_iva'] ?? null,
            $data['telefono']      ?? null,
            $data['email']         ?? null,
            isset($data['franquicia']) ? (int)$data['franquicia'] : 1,
        ]);
        return (int)$this->db->lastInsertId();
    }

    public function actualizar($id, $data) {
        $stmt = $this->db->prepare("
            UPDATE ubicaciones
            SET nombre        = ?,
                razon_social  = ?,
                domicilio     = ?,
                localidad     = ?,
                codigo_postal = ?,
                provincia     = ?,
                cuit          = ?,
                condicion_iva = ?,
                telefono      = ?,
                email         = ?,
                franquicia    = ?
            WHERE id = ? AND tipo_ubicacion = 'sucursal'
        ");
        $stmt->execute([
            trim($data['nombre']),
            $data['razon_social']  ?? null,
            $data['domicilio']     ?? null,
            $data['localidad']     ?? null,
            $data['codigo_postal'] ?? null,
            $data['provincia']     ?? null,
            $data['cuit']          ?? null,
            $data['condicion_iva'] ?? null,
            $data['telefono']      ?? null,
            $data['email']         ?? null,
            isset($data['franquicia']) ? (int)$data['franquicia'] : 1,
            (int)$id,
        ]);
        return $stmt->rowCount() > 0;
    }
}
