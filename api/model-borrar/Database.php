<?php
class Database {
    private static $instance = null;
    private $conn;

    private function __construct() {
        $config = require_once __DIR__ . '/../config.php';
        
        try {
            $this->conn = new PDO(
                "mysql:host=" . $config['db']['host'] . ";dbname=" . $config['db']['dbname'],
                $config['db']['user'],
                $config['db']['pass']
            );
            $this->conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $this->conn->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        } catch(PDOException $e) {
            throw new Exception("Error de conexión: " . $e->getMessage());
        }
    }

    public static function getInstance() {
        if (self::$instance == null) {
            self::$instance = new Database();
        }
        return self::$instance;
    }

    public function getConnection() {
        return $this->conn;
    }
}