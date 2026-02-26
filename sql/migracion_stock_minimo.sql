-- Migración: Crear tabla stock_minimo_sucursal
-- Fecha: 2025
-- Descripción: Permite configurar stock mínimo por producto y sucursal

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
    FOREIGN KEY (id_sucursal) REFERENCES sucursales(id_sucursal),
    FOREIGN KEY (id_producto) REFERENCES productos(id_producto)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Índices para mejorar rendimiento
CREATE INDEX idx_stock_minimo_sucursal ON stock_minimo_sucursal(id_sucursal);
CREATE INDEX idx_stock_minimo_producto ON stock_minimo_sucursal(id_producto);
CREATE INDEX idx_stock_minimo_activo ON stock_minimo_sucursal(activo);
