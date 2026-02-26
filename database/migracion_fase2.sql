-- ═══════════════════════════════════════════════════════════════════════════════
-- MIKELO - MIGRACIÓN FASE 2
-- Sistema de Gestión de Heladerías
-- ═══════════════════════════════════════════════════════════════════════════════
-- Fecha: 23 de Enero de 2026
-- Versión: 1.1 (Ajustado para respetar tablas existentes)
-- 
-- IMPORTANTE: 
-- 1. Hacer BACKUP completo de la BD antes de ejecutar
-- 2. Ejecutar en ambiente de desarrollo primero
-- 3. Verificar que no hay errores antes de producción
-- 4. Si algo falla, usar migracion_fase2_rollback.sql
--
-- NOTA: Las tablas 'roles' y 'usuarios' YA EXISTEN en la BD.
--       Este script las MODIFICA agregando columnas, no las recrea.
-- ═══════════════════════════════════════════════════════════════════════════════

-- Configuración inicial
SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ═══════════════════════════════════════════════════════════════════════════════
-- PARTE 1: MODIFICACIONES A TABLAS EXISTENTES (mínimas y seguras)
-- ═══════════════════════════════════════════════════════════════════════════════

-- 1.1 Agregar tipo_ubicacion a ubicaciones (para distinguir franquicias)
-- DEFAULT 'SUCURSAL_PROPIA' para no afectar datos existentes
ALTER TABLE ubicaciones 
ADD COLUMN IF NOT EXISTS tipo_ubicacion ENUM('DEPOSITO_CENTRAL', 'SUCURSAL_PROPIA', 'FRANQUICIA') 
DEFAULT 'SUCURSAL_PROPIA' AFTER nombre;

-- Marcar el depósito central (ID=1)
UPDATE ubicaciones SET tipo_ubicacion = 'DEPOSITO_CENTRAL' WHERE id = 1;

-- 1.2 Agregar campo disponible_franquicias a productos
-- DEFAULT TRUE para que todos los productos existentes sigan disponibles
ALTER TABLE productos 
ADD COLUMN IF NOT EXISTS disponible_franquicias BOOLEAN DEFAULT TRUE;

-- ═══════════════════════════════════════════════════════════════════════════════
-- PARTE 2: SISTEMA DE USUARIOS Y ROLES (MODIFICAR EXISTENTES)
-- ═══════════════════════════════════════════════════════════════════════════════

-- 2.1 Modificar tabla ROLES existente - Agregar columnas faltantes
-- La tabla ya existe con: id, nombre
-- Agregamos: descripcion, nivel, activo, fecha_creacion
ALTER TABLE roles 
ADD COLUMN IF NOT EXISTS descripcion TEXT AFTER nombre,
ADD COLUMN IF NOT EXISTS nivel INT NOT NULL DEFAULT 99 COMMENT 'Menor nivel = más permisos' AFTER descripcion,
ADD COLUMN IF NOT EXISTS activo BOOLEAN DEFAULT TRUE AFTER nivel,
ADD COLUMN IF NOT EXISTS fecha_creacion DATETIME DEFAULT CURRENT_TIMESTAMP AFTER activo;

-- Actualizar roles existentes con descripción y nivel
UPDATE roles SET descripcion = 'Administrador del sistema - Control total', nivel = 0 WHERE nombre = 'ADMIN';
UPDATE roles SET descripcion = 'Usuario estándar', nivel = 50 WHERE nombre = 'USER';
UPDATE roles SET descripcion = 'Productor/Operario de planta', nivel = 20 WHERE nombre = 'PRODUCTOR';
UPDATE roles SET descripcion = 'Usuario invitado - Solo lectura', nivel = 90 WHERE nombre = 'INVITADO';

-- Agregar nuevos roles si no existen
INSERT INTO roles (nombre, descripcion, nivel, activo) VALUES
('SUPERVISOR_PLANTA', 'Supervisor de planta/depósito central', 10, TRUE),
('OPERARIO_PLANTA', 'Operario de planta - Alta stock y envíos', 20, TRUE),
('SUPERVISOR_SUCURSAL', 'Supervisor de sucursal(es) - Pedidos y recepciones', 30, TRUE),
('OPERARIO_SUCURSAL', 'Operario de sucursal - Operaciones básicas', 40, TRUE)
ON DUPLICATE KEY UPDATE descripcion = VALUES(descripcion), nivel = VALUES(nivel);

-- 2.2 Modificar tabla USUARIOS existente - Agregar columnas faltantes
-- La tabla ya existe con: id, nombre, us, ps, activo, id_roles
-- Agregamos columnas para compatibilidad con el nuevo sistema
ALTER TABLE usuarios 
ADD COLUMN IF NOT EXISTS apellido VARCHAR(100) AFTER nombre,
ADD COLUMN IF NOT EXISTS email VARCHAR(150) AFTER apellido,
ADD COLUMN IF NOT EXISTS ultimo_login DATETIME AFTER activo,
ADD COLUMN IF NOT EXISTS fecha_creacion DATETIME DEFAULT CURRENT_TIMESTAMP AFTER ultimo_login,
ADD COLUMN IF NOT EXISTS fecha_actualizacion DATETIME ON UPDATE CURRENT_TIMESTAMP AFTER fecha_creacion,
ADD COLUMN IF NOT EXISTS creado_por INT COMMENT 'ID del usuario que lo creó' AFTER fecha_actualizacion;

-- Crear índices si no existen (ignorar error si ya existen)
-- Para el campo 'us' (username)
SET @exist := (SELECT COUNT(*) FROM information_schema.statistics 
               WHERE table_schema = DATABASE() AND table_name = 'usuarios' AND index_name = 'idx_usuario_us');
SET @sqlstmt := IF(@exist = 0, 'CREATE INDEX idx_usuario_us ON usuarios(us)', 'SELECT 1');
PREPARE stmt FROM @sqlstmt;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- 2.3 Relación Usuario ↔ Roles (N:N, un usuario puede tener múltiples roles)
-- Nota: La tabla usuarios existente tiene id_roles (relación 1:N), mantenemos ambas
CREATE TABLE IF NOT EXISTS usuario_roles (
    id INT PRIMARY KEY AUTO_INCREMENT,
    id_usuario INT NOT NULL,
    id_rol INT NOT NULL,
    fecha_asignacion DATETIME DEFAULT CURRENT_TIMESTAMP,
    asignado_por INT,
    UNIQUE KEY unique_usuario_rol (id_usuario, id_rol),
    INDEX idx_usuario (id_usuario),
    INDEX idx_rol (id_rol)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Migrar relaciones existentes de id_roles a usuario_roles
INSERT IGNORE INTO usuario_roles (id_usuario, id_rol)
SELECT id, id_roles FROM usuarios WHERE id_roles IS NOT NULL;

-- 2.4 Relación Usuario ↔ Sucursales (N:N, para supervisores/operarios de sucursal)
CREATE TABLE IF NOT EXISTS usuario_sucursales (
    id INT PRIMARY KEY AUTO_INCREMENT,
    id_usuario INT NOT NULL,
    id_sucursal INT NOT NULL,
    es_sucursal_principal BOOLEAN DEFAULT FALSE COMMENT 'Sucursal por defecto al loguearse',
    fecha_asignacion DATETIME DEFAULT CURRENT_TIMESTAMP,
    asignado_por INT,
    UNIQUE KEY unique_usuario_sucursal (id_usuario, id_sucursal),
    INDEX idx_usuario (id_usuario),
    INDEX idx_sucursal (id_sucursal)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2.5 Tabla de Sesiones (para manejo de login)
CREATE TABLE IF NOT EXISTS sesiones (
    id INT PRIMARY KEY AUTO_INCREMENT,
    id_usuario INT NOT NULL,
    token VARCHAR(255) NOT NULL UNIQUE,
    id_sucursal_activa INT COMMENT 'Sucursal seleccionada en la sesión actual',
    ip_address VARCHAR(45),
    user_agent TEXT,
    fecha_creacion DATETIME DEFAULT CURRENT_TIMESTAMP,
    fecha_expiracion DATETIME NOT NULL,
    activa BOOLEAN DEFAULT TRUE,
    INDEX idx_token (token),
    INDEX idx_usuario (id_usuario),
    INDEX idx_expiracion (fecha_expiracion)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ═══════════════════════════════════════════════════════════════════════════════
-- PARTE 3: SISTEMA DE PEDIDOS
-- ═══════════════════════════════════════════════════════════════════════════════

-- 3.1 Tabla principal de Pedidos
CREATE TABLE IF NOT EXISTS pedidos (
    id INT PRIMARY KEY AUTO_INCREMENT,
    id_sucursal INT NOT NULL COMMENT 'Sucursal que realiza el pedido',
    id_usuario INT NOT NULL COMMENT 'Usuario que creó el pedido',
    estado ENUM(
        'BORRADOR',           -- En edición, no enviado
        'PENDIENTE',          -- Enviado, esperando preparación
        'EN_PREPARACION',     -- Planta está preparando
        'PARCIALMENTE_ENVIADO', -- Parte del pedido fue enviada
        'ENVIADO',            -- Todo el pedido fue enviado
        'RECIBIDO_PARCIAL',   -- Parte fue recibida en sucursal
        'RECIBIDO',           -- Todo recibido
        'ANULADO'             -- Cancelado
    ) DEFAULT 'BORRADOR',
    fecha_creacion DATETIME DEFAULT CURRENT_TIMESTAMP,
    fecha_envio DATETIME COMMENT 'Cuando se envió el pedido a planta',
    fecha_actualizacion DATETIME ON UPDATE CURRENT_TIMESTAMP,
    fecha_cierre DATETIME COMMENT 'Cuando se completó o anuló',
    observaciones TEXT,
    motivo_anulacion TEXT,
    FOREIGN KEY (id_sucursal) REFERENCES ubicaciones(id),
    FOREIGN KEY (id_usuario) REFERENCES usuarios(id),
    INDEX idx_sucursal (id_sucursal),
    INDEX idx_estado (estado),
    INDEX idx_fecha (fecha_creacion),
    INDEX idx_sucursal_estado (id_sucursal, estado)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3.2 Items del Pedido
CREATE TABLE IF NOT EXISTS pedido_items (
    id INT PRIMARY KEY AUTO_INCREMENT,
    id_pedido INT NOT NULL,
    id_producto INT NOT NULL,
    cantidad_solicitada DECIMAL(10,3) NOT NULL,
    cantidad_enviada DECIMAL(10,3) DEFAULT 0 COMMENT 'Se actualiza al crear envío',
    cantidad_recibida DECIMAL(10,3) DEFAULT 0 COMMENT 'Se actualiza al confirmar recepción',
    peso_solicitado DECIMAL(10,3) DEFAULT 0,
    peso_enviado DECIMAL(10,3) DEFAULT 0,
    peso_recibido DECIMAL(10,3) DEFAULT 0,
    observaciones TEXT,
    FOREIGN KEY (id_pedido) REFERENCES pedidos(id) ON DELETE CASCADE,
    FOREIGN KEY (id_producto) REFERENCES productos(id),
    UNIQUE KEY unique_pedido_producto (id_pedido, id_producto),
    INDEX idx_pedido (id_pedido),
    INDEX idx_producto (id_producto)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3.3 Relación Pedido ↔ Envío (N:N)
-- Un pedido puede dividirse en múltiples envíos
-- Un envío puede satisfacer múltiples pedidos (consolidación)
CREATE TABLE IF NOT EXISTS pedido_envio (
    id INT PRIMARY KEY AUTO_INCREMENT,
    id_pedido INT NOT NULL,
    id_movimiento_envio INT NOT NULL COMMENT 'FK a movimientos (el envío existente)',
    fecha_relacion DATETIME DEFAULT CURRENT_TIMESTAMP,
    usuario_relaciono VARCHAR(100),
    FOREIGN KEY (id_pedido) REFERENCES pedidos(id) ON DELETE RESTRICT,
    FOREIGN KEY (id_movimiento_envio) REFERENCES movimientos(id) ON DELETE RESTRICT,
    UNIQUE KEY unique_pedido_envio (id_pedido, id_movimiento_envio),
    INDEX idx_pedido (id_pedido),
    INDEX idx_envio (id_movimiento_envio)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3.4 Detalle de qué items del pedido van en qué envío
CREATE TABLE IF NOT EXISTS pedido_envio_items (
    id INT PRIMARY KEY AUTO_INCREMENT,
    id_pedido_envio INT NOT NULL,
    id_pedido_item INT NOT NULL,
    id_movimiento_item INT NOT NULL COMMENT 'FK a movimientos_items (item del envío)',
    cantidad_asignada DECIMAL(10,3) NOT NULL,
    peso_asignado DECIMAL(10,3) DEFAULT 0,
    FOREIGN KEY (id_pedido_envio) REFERENCES pedido_envio(id) ON DELETE CASCADE,
    FOREIGN KEY (id_pedido_item) REFERENCES pedido_items(id) ON DELETE RESTRICT,
    FOREIGN KEY (id_movimiento_item) REFERENCES movimientos_items(id) ON DELETE RESTRICT,
    INDEX idx_pedido_envio (id_pedido_envio),
    INDEX idx_pedido_item (id_pedido_item)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ═══════════════════════════════════════════════════════════════════════════════
-- PARTE 4: SISTEMA DE RECEPCIONES
-- ═══════════════════════════════════════════════════════════════════════════════

-- 4.1 Tabla principal de Recepciones
CREATE TABLE IF NOT EXISTS recepciones (
    id INT PRIMARY KEY AUTO_INCREMENT,
    id_movimiento_envio INT NOT NULL COMMENT 'Envío que se está recibiendo',
    id_sucursal INT NOT NULL,
    id_usuario INT NOT NULL COMMENT 'Usuario que confirma la recepción',
    estado ENUM(
        'COMPLETA',           -- Todo llegó correctamente
        'PARCIAL',            -- Llegó menos de lo enviado
        'CON_DIFERENCIAS',    -- Hay discrepancias
        'RECHAZADA'           -- Se rechazó el envío
    ) DEFAULT 'COMPLETA',
    fecha_recepcion DATETIME DEFAULT CURRENT_TIMESTAMP,
    observaciones TEXT,
    FOREIGN KEY (id_movimiento_envio) REFERENCES movimientos(id),
    FOREIGN KEY (id_sucursal) REFERENCES ubicaciones(id),
    FOREIGN KEY (id_usuario) REFERENCES usuarios(id),
    INDEX idx_envio (id_movimiento_envio),
    INDEX idx_sucursal (id_sucursal),
    INDEX idx_fecha (fecha_recepcion)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 4.2 Detalle de items recibidos
CREATE TABLE IF NOT EXISTS recepcion_items (
    id INT PRIMARY KEY AUTO_INCREMENT,
    id_recepcion INT NOT NULL,
    id_movimiento_item INT NOT NULL COMMENT 'Item del envío original',
    id_producto INT NOT NULL,
    cantidad_esperada DECIMAL(10,3) NOT NULL,
    cantidad_recibida DECIMAL(10,3) NOT NULL,
    peso_esperado DECIMAL(10,3) DEFAULT 0,
    peso_recibido DECIMAL(10,3) DEFAULT 0,
    estado_item ENUM('OK', 'FALTANTE', 'EXCEDENTE', 'DAÑADO') DEFAULT 'OK',
    observaciones TEXT,
    FOREIGN KEY (id_recepcion) REFERENCES recepciones(id) ON DELETE CASCADE,
    FOREIGN KEY (id_movimiento_item) REFERENCES movimientos_items(id),
    FOREIGN KEY (id_producto) REFERENCES productos(id),
    INDEX idx_recepcion (id_recepcion),
    INDEX idx_movimiento_item (id_movimiento_item)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ═══════════════════════════════════════════════════════════════════════════════
-- PARTE 5: STOCK EN SUCURSALES
-- ═══════════════════════════════════════════════════════════════════════════════

-- 5.1 Tabla de Stock actual por sucursal (se actualiza con cada recepción/baja)
CREATE TABLE IF NOT EXISTS stock_sucursal (
    id INT PRIMARY KEY AUTO_INCREMENT,
    id_sucursal INT NOT NULL,
    id_producto INT NOT NULL,
    cantidad DECIMAL(10,3) DEFAULT 0,
    peso DECIMAL(10,3) DEFAULT 0,
    fecha_ultima_entrada DATETIME COMMENT 'Última recepción',
    fecha_ultima_salida DATETIME COMMENT 'Última baja',
    fecha_actualizacion DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY unique_sucursal_producto (id_sucursal, id_producto),
    FOREIGN KEY (id_sucursal) REFERENCES ubicaciones(id),
    FOREIGN KEY (id_producto) REFERENCES productos(id),
    INDEX idx_sucursal (id_sucursal),
    INDEX idx_producto (id_producto)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 5.2 Historial de movimientos de stock en sucursal (para auditoría)
CREATE TABLE IF NOT EXISTS stock_sucursal_movimientos (
    id INT PRIMARY KEY AUTO_INCREMENT,
    id_sucursal INT NOT NULL,
    id_producto INT NOT NULL,
    tipo_movimiento ENUM(
        'RECEPCION',          -- Entrada por recepción de envío
        'BAJA_VENTA',         -- Salida por venta (Fase 3)
        'BAJA_MERMA',         -- Salida por pérdida/vencimiento (Fase 3)
        'AJUSTE_POSITIVO',    -- Corrección que suma (Fase 3)
        'AJUSTE_NEGATIVO',    -- Corrección que resta (Fase 3)
        'DEVOLUCION_SALIDA'   -- Devolución a depósito (Fase 3)
    ) NOT NULL,
    cantidad DECIMAL(10,3) NOT NULL COMMENT 'Positivo=entrada, Negativo=salida',
    peso DECIMAL(10,3) DEFAULT 0,
    cantidad_anterior DECIMAL(10,3) COMMENT 'Stock antes del movimiento',
    cantidad_posterior DECIMAL(10,3) COMMENT 'Stock después del movimiento',
    id_referencia INT COMMENT 'ID de recepcion, baja, ajuste, etc.',
    tabla_referencia VARCHAR(50) COMMENT 'Nombre de la tabla de origen',
    id_usuario INT NOT NULL,
    fecha_movimiento DATETIME DEFAULT CURRENT_TIMESTAMP,
    observaciones TEXT,
    FOREIGN KEY (id_sucursal) REFERENCES ubicaciones(id),
    FOREIGN KEY (id_producto) REFERENCES productos(id),
    FOREIGN KEY (id_usuario) REFERENCES usuarios(id),
    INDEX idx_sucursal (id_sucursal),
    INDEX idx_producto (id_producto),
    INDEX idx_fecha (fecha_movimiento),
    INDEX idx_tipo (tipo_movimiento)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ═══════════════════════════════════════════════════════════════════════════════
-- PARTE 6: VISTAS ÚTILES
-- ═══════════════════════════════════════════════════════════════════════════════

-- 6.1 Vista: Pedidos con información resumida
CREATE OR REPLACE VIEW v_pedidos_resumen AS
SELECT 
    p.id,
    p.id_sucursal,
    u.nombre AS sucursal,
    p.id_usuario,
    CONCAT(us.nombre, ' ', IFNULL(us.apellido, '')) AS usuario,
    p.estado,
    p.fecha_creacion,
    p.fecha_envio,
    p.observaciones,
    COUNT(DISTINCT pi.id) AS total_items,
    SUM(pi.cantidad_solicitada) AS total_cantidad_solicitada,
    SUM(pi.cantidad_enviada) AS total_cantidad_enviada,
    SUM(pi.cantidad_recibida) AS total_cantidad_recibida,
    COUNT(DISTINCT pe.id_movimiento_envio) AS total_envios
FROM pedidos p
INNER JOIN ubicaciones u ON p.id_sucursal = u.id
INNER JOIN usuarios us ON p.id_usuario = us.id
LEFT JOIN pedido_items pi ON p.id = pi.id_pedido
LEFT JOIN pedido_envio pe ON p.id = pe.id_pedido
GROUP BY p.id;

-- 6.2 Vista: Stock en sucursales con info de producto
CREATE OR REPLACE VIEW v_stock_sucursal AS
SELECT 
    ss.id,
    ss.id_sucursal,
    u.nombre AS sucursal,
    ss.id_producto,
    pr.codigo AS codigo_producto,
    pr.descripcion AS producto,
    pr.id_familia,
    f.nombre AS familia,
    ss.cantidad,
    ss.peso,
    ss.fecha_actualizacion
FROM stock_sucursal ss
INNER JOIN ubicaciones u ON ss.id_sucursal = u.id
INNER JOIN productos pr ON ss.id_producto = pr.id
LEFT JOIN familias f ON pr.id_familia = f.id
WHERE ss.cantidad > 0 OR ss.peso > 0;

-- 6.3 Vista: Envíos pendientes de recepción
CREATE OR REPLACE VIEW v_envios_pendientes_recepcion AS
SELECT 
    m.id AS id_envio,
    m.fechaAlta AS fecha_envio,
    m.id_ubicacion_destino AS id_sucursal,
    ud.nombre AS sucursal_destino,
    COUNT(DISTINCT mi.id) AS total_items,
    SUM(mi.cnt) AS total_cantidad,
    SUM(mi.cnt_peso) AS total_peso
FROM movimientos m
INNER JOIN ubicaciones ud ON m.id_ubicacion_destino = ud.id
INNER JOIN movimientos_items mi ON m.id = mi.id_movimientos
LEFT JOIN recepciones r ON m.id = r.id_movimiento_envio
WHERE m.id_ubicacion_origen = 1  -- Viene del depósito
AND m.id_ubicacion_destino != 1  -- Va a una sucursal
AND mi.id_movimientos_items_origen IS NOT NULL  -- Es un envío (tiene origen)
AND r.id IS NULL  -- No tiene recepción
GROUP BY m.id;

-- ═══════════════════════════════════════════════════════════════════════════════
-- PARTE 7: ÍNDICES ADICIONALES PARA PERFORMANCE
-- ═══════════════════════════════════════════════════════════════════════════════

-- Índice para búsqueda de envíos por destino (usado en recepciones)
-- Solo crear si no existe
SET @exist := (SELECT COUNT(*) FROM information_schema.statistics 
               WHERE table_schema = DATABASE() 
               AND table_name = 'movimientos' 
               AND index_name = 'idx_destino_fecha');
SET @sqlstmt := IF(@exist = 0, 
    'CREATE INDEX idx_destino_fecha ON movimientos(id_ubicacion_destino, fechaAlta)', 
    'SELECT ''Index already exists''');
PREPARE stmt FROM @sqlstmt;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- ═══════════════════════════════════════════════════════════════════════════════
-- FIN DE LA MIGRACIÓN
-- ═══════════════════════════════════════════════════════════════════════════════

SET FOREIGN_KEY_CHECKS = 1;

-- Mensaje de confirmación
SELECT 'Migración Fase 2 completada exitosamente' AS resultado;
SELECT COUNT(*) AS total_tablas_nuevas FROM information_schema.tables 
WHERE table_schema = DATABASE() 
AND table_name IN ('roles', 'usuarios', 'usuario_roles', 'usuario_sucursales', 
                   'sesiones', 'pedidos', 'pedido_items', 'pedido_envio', 
                   'pedido_envio_items', 'recepciones', 'recepcion_items',
                   'stock_sucursal', 'stock_sucursal_movimientos');
