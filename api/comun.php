<?php
function getDB() {
    $config = require __DIR__ . '/config.php';
    try {
        $dsn = "mysql:host={$config['db']['host']};dbname={$config['db']['dbname']};charset=utf8";
        $pdo = new PDO(
            $dsn,
            $config['db']['user'],
            $config['db']['pass']
        );
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        return $pdo;
    } catch(PDOException $e) {
        error_log("Error de conexión a la base de datos: " . $e->getMessage());
        throw new Exception("Error de conexión a la base de datos: " . $e->getMessage() . " - DSN: " . $dsn);
    } catch(Exception $e) {
        error_log("Error general: " . $e->getMessage());
        throw new Exception("Error general: " . $e->getMessage());
    }
}

function responseJson($response, $data, $status = 200) {
    $response->getBody()->write(json_encode($data));
    return $response
        ->withHeader('Content-Type', 'application/json')
        ->withStatus($status);
}
