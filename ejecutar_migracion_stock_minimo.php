<?php
/**
 * Script para ejecutar migración de stock_minimo_sucursal
 */
require_once __DIR__ . '/api/comun.php';

try {
    $db = getDB();
    
    // Verificar si la tabla ya existe
    $result = $db->query("SHOW TABLES LIKE 'stock_minimo_sucursal'");
    if ($result->fetch()) {
        echo "✓ La tabla stock_minimo_sucursal ya existe.\n";
    } else {
        // Crear la tabla (ubicaciones = sucursales)
        $sql = "
        CREATE TABLE IF NOT EXISTS stock_minimo_sucursal (
            id_stock_minimo INT AUTO_INCREMENT PRIMARY KEY,
            id_sucursal INT NOT NULL,
            id_producto INT NOT NULL,
            stock_minimo DECIMAL(10,3) NOT NULL DEFAULT 0,
            stock_optimo DECIMAL(10,3) DEFAULT NULL,
            activo TINYINT(1) NOT NULL DEFAULT 1,
            fecha_creacion DATETIME DEFAULT CURRENT_TIMESTAMP,
            fecha_modificacion DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            usuario_modificacion VARCHAR(100) DEFAULT NULL,
            UNIQUE KEY uk_sucursal_producto (id_sucursal, id_producto),
            FOREIGN KEY (id_sucursal) REFERENCES ubicaciones(id),
            FOREIGN KEY (id_producto) REFERENCES productos(id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
        
        $db->exec($sql);
        echo "✓ Tabla stock_minimo_sucursal creada exitosamente.\n";
    }
    
    // Crear índices si no existen
    try {
        $db->exec("CREATE INDEX idx_stock_minimo_sucursal ON stock_minimo_sucursal(id_sucursal)");
        echo "✓ Índice idx_stock_minimo_sucursal creado.\n";
    } catch (PDOException $e) {
        if (strpos($e->getMessage(), 'Duplicate') !== false) {
            echo "✓ Índice idx_stock_minimo_sucursal ya existe.\n";
        }
    }
    
    try {
        $db->exec("CREATE INDEX idx_stock_minimo_producto ON stock_minimo_sucursal(id_producto)");
        echo "✓ Índice idx_stock_minimo_producto creado.\n";
    } catch (PDOException $e) {
        if (strpos($e->getMessage(), 'Duplicate') !== false) {
            echo "✓ Índice idx_stock_minimo_producto ya existe.\n";
        }
    }
    
    echo "\n¡Migración completada!\n";
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    exit(1);
}
