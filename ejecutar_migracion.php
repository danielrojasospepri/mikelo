<?php
/**
 * MIGRACIÓN FASE 2 - Mikelo
 * Ejecutar con: php ejecutar_migracion.php
 */

require 'api/comun.php';

echo "=== MIGRACIÓN FASE 2 - MIKELO ===\n";
echo "Fecha: " . date('Y-m-d H:i:s') . "\n\n";

try {
    $db = getDB();
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // =====================================================
    // PASO 1: Eliminar tablas existentes que no sirven
    // =====================================================
    echo "PASO 1: Limpiando tablas obsoletas...\n";
    
    // Desactivar FK checks temporalmente
    $db->exec("SET FOREIGN_KEY_CHECKS = 0");
    
    // Eliminar tablas que vamos a recrear
    $tablasEliminar = ['usuarios', 'roles', 'stock'];
    foreach ($tablasEliminar as $tabla) {
        $db->exec("DROP TABLE IF EXISTS `$tabla`");
        echo "  - Eliminada tabla: $tabla\n";
    }
    
    $db->exec("SET FOREIGN_KEY_CHECKS = 1");
    echo "  ✓ Tablas obsoletas eliminadas\n\n";

    // =====================================================
    // PASO 2: Modificar tablas existentes
    // =====================================================
    echo "PASO 2: Modificando tablas existentes...\n";
    
    // Agregar tipo_ubicacion a ubicaciones
    $stmt = $db->query("SHOW COLUMNS FROM ubicaciones LIKE 'tipo_ubicacion'");
    if ($stmt->rowCount() == 0) {
        $db->exec("ALTER TABLE ubicaciones ADD COLUMN tipo_ubicacion ENUM('deposito', 'sucursal') DEFAULT 'sucursal' AFTER nombre");
        $db->exec("UPDATE ubicaciones SET tipo_ubicacion = 'deposito' WHERE id = 1");
        $db->exec("UPDATE ubicaciones SET tipo_ubicacion = 'sucursal' WHERE id > 1");
        echo "  - Agregado tipo_ubicacion a ubicaciones\n";
    } else {
        echo "  - tipo_ubicacion ya existe en ubicaciones\n";
    }
    
    // Agregar disponible_franquicias a productos
    $stmt = $db->query("SHOW COLUMNS FROM productos LIKE 'disponible_franquicias'");
    if ($stmt->rowCount() == 0) {
        $db->exec("ALTER TABLE productos ADD COLUMN disponible_franquicias TINYINT(1) DEFAULT 1");
        echo "  - Agregado disponible_franquicias a productos\n";
    } else {
        echo "  - disponible_franquicias ya existe en productos\n";
    }
    
    echo "  ✓ Tablas existentes modificadas\n\n";

    // =====================================================
    // PASO 3: Crear tabla ROLES
    // =====================================================
    echo "PASO 3: Creando tabla roles...\n";
    
    $db->exec("
        CREATE TABLE roles (
            id INT AUTO_INCREMENT PRIMARY KEY,
            nombre VARCHAR(50) NOT NULL UNIQUE,
            nivel INT NOT NULL DEFAULT 100 COMMENT 'Menor número = más permisos',
            descripcion VARCHAR(255),
            fecha_creacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    
    // Insertar roles predefinidos
    $db->exec("
        INSERT INTO roles (id, nombre, nivel, descripcion) VALUES
        (1, 'ADMIN', 10, 'Administrador del sistema - Acceso total'),
        (2, 'PLANTA_JEFE', 20, 'Jefe de planta - Gestión completa del depósito'),
        (3, 'PLANTA_OPERARIO', 25, 'Operario de planta - Operaciones de depósito'),
        (4, 'FRANQUICIA_ADMIN', 30, 'Administrador de franquicia'),
        (5, 'FRANQUICIA_EMPLEADO', 40, 'Empleado de franquicia')
    ");
    echo "  ✓ Tabla roles creada con 5 roles\n\n";

    // =====================================================
    // PASO 4: Crear tabla USUARIOS
    // =====================================================
    echo "PASO 4: Creando tabla usuarios...\n";
    
    $db->exec("
        CREATE TABLE usuarios (
            id INT AUTO_INCREMENT PRIMARY KEY,
            nombre VARCHAR(100) NOT NULL,
            apellido VARCHAR(100),
            email VARCHAR(255) UNIQUE,
            us VARCHAR(50) NOT NULL UNIQUE COMMENT 'Username',
            ps VARCHAR(255) NOT NULL COMMENT 'Password hash',
            activo TINYINT(1) DEFAULT 1,
            id_roles INT NOT NULL,
            ultimo_login DATETIME,
            creado_por INT,
            fecha_creacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (id_roles) REFERENCES roles(id),
            FOREIGN KEY (creado_por) REFERENCES usuarios(id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    
    // Crear usuario admin por defecto
    $adminPass = password_hash('admin123', PASSWORD_DEFAULT);
    $db->exec("
        INSERT INTO usuarios (nombre, apellido, us, ps, activo, id_roles) 
        VALUES ('Administrador', 'Sistema', 'admin', '$adminPass', 1, 1)
    ");
    echo "  ✓ Tabla usuarios creada\n";
    echo "  ✓ Usuario admin creado (user: admin, pass: admin123)\n\n";

    // =====================================================
    // PASO 5: Crear tablas de relación usuarios
    // =====================================================
    echo "PASO 5: Creando tablas de relación...\n";
    
    // usuario_roles (N:N)
    $db->exec("
        CREATE TABLE usuario_roles (
            id INT AUTO_INCREMENT PRIMARY KEY,
            id_usuario INT NOT NULL,
            id_rol INT NOT NULL,
            asignado_por INT,
            fecha_asignacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (id_usuario) REFERENCES usuarios(id) ON DELETE CASCADE,
            FOREIGN KEY (id_rol) REFERENCES roles(id) ON DELETE CASCADE,
            FOREIGN KEY (asignado_por) REFERENCES usuarios(id) ON DELETE SET NULL,
            UNIQUE KEY unique_usuario_rol (id_usuario, id_rol)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    echo "  - Tabla usuario_roles creada\n";
    
    // usuario_sucursales
    $db->exec("
        CREATE TABLE usuario_sucursales (
            id INT AUTO_INCREMENT PRIMARY KEY,
            id_usuario INT NOT NULL,
            id_sucursal INT NOT NULL,
            es_sucursal_principal TINYINT(1) DEFAULT 0,
            asignado_por INT,
            fecha_asignacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (id_usuario) REFERENCES usuarios(id) ON DELETE CASCADE,
            FOREIGN KEY (id_sucursal) REFERENCES ubicaciones(id) ON DELETE CASCADE,
            FOREIGN KEY (asignado_por) REFERENCES usuarios(id) ON DELETE SET NULL,
            UNIQUE KEY unique_usuario_sucursal (id_usuario, id_sucursal)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    echo "  - Tabla usuario_sucursales creada\n";
    
    // sesiones
    $db->exec("
        CREATE TABLE sesiones (
            id INT AUTO_INCREMENT PRIMARY KEY,
            id_usuario INT NOT NULL,
            token VARCHAR(64) NOT NULL UNIQUE,
            ip_address VARCHAR(45),
            user_agent VARCHAR(255),
            creada_en TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            ultima_actividad TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            expira_en DATETIME NOT NULL,
            FOREIGN KEY (id_usuario) REFERENCES usuarios(id) ON DELETE CASCADE,
            INDEX idx_token (token),
            INDEX idx_expira (expira_en)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    echo "  - Tabla sesiones creada\n";
    echo "  ✓ Tablas de relación creadas\n\n";

    // =====================================================
    // PASO 6: Crear tablas de PEDIDOS
    // =====================================================
    echo "PASO 6: Creando tablas de pedidos...\n";
    
    $db->exec("
        CREATE TABLE pedidos (
            id INT AUTO_INCREMENT PRIMARY KEY,
            id_sucursal INT NOT NULL,
            fecha_pedido TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            estado ENUM('PENDIENTE', 'EN_PROCESO', 'ENVIADO', 'RECIBIDO', 'ANULADO') DEFAULT 'PENDIENTE',
            prioridad ENUM('baja', 'normal', 'alta') DEFAULT 'normal',
            observaciones TEXT,
            creado_por INT NOT NULL,
            procesado_por INT,
            fecha_procesado DATETIME,
            anulado_por INT,
            fecha_anulacion DATETIME,
            motivo_anulacion VARCHAR(255),
            FOREIGN KEY (id_sucursal) REFERENCES ubicaciones(id),
            FOREIGN KEY (creado_por) REFERENCES usuarios(id),
            FOREIGN KEY (procesado_por) REFERENCES usuarios(id),
            FOREIGN KEY (anulado_por) REFERENCES usuarios(id),
            INDEX idx_sucursal_estado (id_sucursal, estado),
            INDEX idx_fecha (fecha_pedido)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    echo "  - Tabla pedidos creada\n";
    
    $db->exec("
        CREATE TABLE pedido_items (
            id INT AUTO_INCREMENT PRIMARY KEY,
            id_pedido INT NOT NULL,
            id_producto INT NOT NULL,
            cantidad INT NOT NULL,
            cantidad_enviada INT DEFAULT 0,
            observaciones VARCHAR(255),
            FOREIGN KEY (id_pedido) REFERENCES pedidos(id) ON DELETE CASCADE,
            FOREIGN KEY (id_producto) REFERENCES productos(id),
            UNIQUE KEY unique_pedido_producto (id_pedido, id_producto)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    echo "  - Tabla pedido_items creada\n";
    
    $db->exec("
        CREATE TABLE pedido_envio (
            id INT AUTO_INCREMENT PRIMARY KEY,
            id_pedido INT NOT NULL,
            id_envio INT NOT NULL COMMENT 'ID del movimiento tipo envio',
            fecha_asociacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            asociado_por INT,
            FOREIGN KEY (id_pedido) REFERENCES pedidos(id),
            FOREIGN KEY (id_envio) REFERENCES movimientos(id),
            FOREIGN KEY (asociado_por) REFERENCES usuarios(id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    echo "  - Tabla pedido_envio creada\n";
    
    $db->exec("
        CREATE TABLE pedido_envio_items (
            id INT AUTO_INCREMENT PRIMARY KEY,
            id_pedido_envio INT NOT NULL,
            id_pedido_item INT NOT NULL,
            id_movimiento_item INT NOT NULL,
            cantidad INT NOT NULL,
            FOREIGN KEY (id_pedido_envio) REFERENCES pedido_envio(id) ON DELETE CASCADE,
            FOREIGN KEY (id_pedido_item) REFERENCES pedido_items(id),
            FOREIGN KEY (id_movimiento_item) REFERENCES movimientos_items(id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    echo "  - Tabla pedido_envio_items creada\n";
    echo "  ✓ Tablas de pedidos creadas\n\n";

    // =====================================================
    // PASO 7: Crear tablas de RECEPCIONES
    // =====================================================
    echo "PASO 7: Creando tablas de recepciones...\n";
    
    $db->exec("
        CREATE TABLE recepciones (
            id INT AUTO_INCREMENT PRIMARY KEY,
            id_envio INT NOT NULL COMMENT 'ID del movimiento tipo envio',
            fecha_recepcion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            recibido_por INT NOT NULL,
            observaciones TEXT,
            FOREIGN KEY (id_envio) REFERENCES movimientos(id),
            FOREIGN KEY (recibido_por) REFERENCES usuarios(id),
            UNIQUE KEY unique_envio (id_envio)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    echo "  - Tabla recepciones creada\n";
    
    $db->exec("
        CREATE TABLE recepcion_items (
            id INT AUTO_INCREMENT PRIMARY KEY,
            id_recepcion INT NOT NULL,
            id_movimiento_item INT NOT NULL,
            id_producto INT NOT NULL,
            cantidad_enviada INT NOT NULL,
            cantidad_recibida INT NOT NULL,
            diferencia INT GENERATED ALWAYS AS (cantidad_recibida - cantidad_enviada) STORED,
            peso_recibido DECIMAL(10,2),
            observaciones VARCHAR(255),
            FOREIGN KEY (id_recepcion) REFERENCES recepciones(id) ON DELETE CASCADE,
            FOREIGN KEY (id_movimiento_item) REFERENCES movimientos_items(id),
            FOREIGN KEY (id_producto) REFERENCES productos(id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    echo "  - Tabla recepcion_items creada\n";
    echo "  ✓ Tablas de recepciones creadas\n\n";

    // =====================================================
    // PASO 8: Crear tablas de STOCK SUCURSAL
    // =====================================================
    echo "PASO 8: Creando tablas de stock sucursal...\n";
    
    $db->exec("
        CREATE TABLE stock_sucursal (
            id INT AUTO_INCREMENT PRIMARY KEY,
            id_sucursal INT NOT NULL,
            id_producto INT NOT NULL,
            cantidad_actual INT DEFAULT 0,
            peso_total DECIMAL(10,2) DEFAULT 0,
            ultima_actualizacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (id_sucursal) REFERENCES ubicaciones(id),
            FOREIGN KEY (id_producto) REFERENCES productos(id),
            UNIQUE KEY unique_sucursal_producto (id_sucursal, id_producto),
            INDEX idx_sucursal (id_sucursal)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    echo "  - Tabla stock_sucursal creada\n";
    
    $db->exec("
        CREATE TABLE stock_sucursal_movimientos (
            id INT AUTO_INCREMENT PRIMARY KEY,
            id_stock_sucursal INT NOT NULL,
            tipo_movimiento ENUM('entrada', 'salida', 'ajuste') NOT NULL,
            cantidad INT NOT NULL,
            peso DECIMAL(10,2),
            id_recepcion INT COMMENT 'Si es entrada por recepcion',
            id_baja INT COMMENT 'Si es salida por baja',
            referencia VARCHAR(100),
            fecha TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            usuario INT,
            FOREIGN KEY (id_stock_sucursal) REFERENCES stock_sucursal(id),
            FOREIGN KEY (id_recepcion) REFERENCES recepciones(id),
            FOREIGN KEY (usuario) REFERENCES usuarios(id),
            INDEX idx_fecha (fecha)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    echo "  - Tabla stock_sucursal_movimientos creada\n";
    echo "  ✓ Tablas de stock sucursal creadas\n\n";

    // =====================================================
    // PASO 9: Asignar rol admin al usuario admin
    // =====================================================
    echo "PASO 9: Configuración final...\n";
    
    $db->exec("INSERT INTO usuario_roles (id_usuario, id_rol) VALUES (1, 1)");
    echo "  - Rol ADMIN asignado al usuario admin\n";
    echo "  ✓ Configuración completada\n\n";

    // =====================================================
    // VERIFICACIÓN FINAL
    // =====================================================
    echo "=== VERIFICACIÓN FINAL ===\n";
    $stmt = $db->query("SHOW TABLES");
    $tablas = $stmt->fetchAll(PDO::FETCH_COLUMN);
    echo "Total de tablas: " . count($tablas) . "\n";
    echo "Tablas: " . implode(', ', $tablas) . "\n\n";
    
    echo "=== MIGRACIÓN COMPLETADA EXITOSAMENTE ===\n";
    echo "\n*** CREDENCIALES DE ACCESO ***\n";
    echo "Usuario: admin\n";
    echo "Password: admin123\n";
    echo "******************************\n";

} catch (Exception $e) {
    echo "\n!!! ERROR EN MIGRACIÓN !!!\n";
    echo $e->getMessage() . "\n";
    echo "\nTraza:\n" . $e->getTraceAsString() . "\n";
    exit(1);
}
