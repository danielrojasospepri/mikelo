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
}