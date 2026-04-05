-- =============================================================================
-- SCRIPT DE MIGRACIÓN: PRODUCCIÓN → ESTADO ACTUAL (DESARROLLO)
-- Generado: 2026-03-20
-- Base destino: u237583611_mikelo (producción en Hostinger)
-- Base referencia: u237583611_mikelo (dev local)
-- =============================================================================
-- CÓMO USAR:
--   1. Hacer backup de producción antes de ejecutar (¡OBLIGATORIO!)
--   2. Revisar sección de ADVERTENCIAS al final antes de ejecutar
--   3. Ejecutar en producción con un usuario con privilegios DDL
--   4. Verificar cada bloque manualmente en staging si es posible
-- =============================================================================

SET FOREIGN_KEY_CHECKS = 0;
SET SQL_MODE = 'NO_AUTO_VALUE_ON_ZERO';

USE `u237583611_test`;

-- =============================================================================
-- BLOQUE 1: ALTERACIONES EN TABLAS EXISTENTES
-- =============================================================================

-- -----------------------------------------------------------------------------
-- 1.1  tabla: roles
--      Producción tiene: id, nombre
--      Desarrollo agrega: nivel, descripcion, fecha_creacion
--      Cambio de tipo:    nombre varchar(255) → varchar(50)
-- ADVERTENCIA: Verificar que ningún rol tenga nombre > 50 chars antes de ejecutar.
-- -----------------------------------------------------------------------------

ALTER TABLE `roles`
    ADD COLUMN `nivel` int(11) NOT NULL DEFAULT 100
        COMMENT 'Menor número = más permisos' AFTER `nombre`,
    ADD COLUMN `descripcion` varchar(255) DEFAULT NULL AFTER `nivel`,
    ADD COLUMN `fecha_creacion` timestamp NOT NULL DEFAULT current_timestamp() AFTER `descripcion`,
    ADD UNIQUE KEY `nombre` (`nombre`),
    MODIFY COLUMN `nombre` varchar(50) NOT NULL;

-- Poblar niveles según los roles existentes en producción:
-- (Ajustar los IDs si en producción difieren de los de dev)
UPDATE `roles` SET `nivel` = 10  WHERE `nombre` = 'Admin';
UPDATE `roles` SET `nivel` = 20  WHERE `nombre` = 'Planta Jefe';
UPDATE `roles` SET `nivel` = 25  WHERE `nombre` = 'Planta Operario';
UPDATE `roles` SET `nivel` = 30  WHERE `nombre` = 'Franquicia Admin';
UPDATE `roles` SET `nivel` = 40  WHERE `nombre` = 'Franquicia Empleado';


-- -----------------------------------------------------------------------------
-- 1.2  tabla: ubicaciones
--      Producción tiene: id, nombre, razon_social, domicilio, localidad,
--                        codigo_postal, provincia, cuit, condicion_iva,
--                        telefono, email
--      Desarrollo agrega: tipo_ubicacion, franquicia
-- -----------------------------------------------------------------------------

ALTER TABLE `ubicaciones`
    ADD COLUMN `tipo_ubicacion` enum('deposito','sucursal') DEFAULT 'sucursal'
        AFTER `nombre`,
    ADD COLUMN `franquicia` int(1) NOT NULL DEFAULT 1
        AFTER `email`;

-- Marcar el depósito central como 'deposito' (ID=1 en dev — ajustar si difiere):
UPDATE `ubicaciones` SET `tipo_ubicacion` = 'deposito' WHERE `id` = 1;

-- Todas las demás son sucursales (ya tienen 'sucursal' por DEFAULT).
-- Ajustar manualmente franquicia=0 para sucursales propias si corresponde.


-- -----------------------------------------------------------------------------
-- 1.3  tabla: usuarios
--      Producción tiene: id, nombre(500), us(255), ps(500), activo, id_roles
--      Desarrollo agrega: apellido, email, ultimo_login, creado_por, fecha_creacion
--      Cambio de tipo:    nombre varchar(500)→varchar(100),
--                         us varchar(255)→varchar(50),
--                         ps varchar(500)→varchar(255)
-- ADVERTENCIA: Los campos se achican. El hash bcrypt ocupa 60 chars (cabe en 255).
--              Verificar que us y nombre actuales no superen los nuevos límites.
-- -----------------------------------------------------------------------------

-- Verificar longitudes antes (ejecutar como consulta de diagnóstico):
-- SELECT id, nombre, LENGTH(nombre) len_nombre, us, LENGTH(us) len_us
-- FROM usuarios WHERE LENGTH(nombre)>100 OR LENGTH(us)>50 OR LENGTH(ps)>255;

ALTER TABLE `usuarios`
    ADD COLUMN `apellido` varchar(100) DEFAULT NULL AFTER `nombre`,
    ADD COLUMN `email` varchar(255) DEFAULT NULL AFTER `apellido`,
    ADD COLUMN `ultimo_login` datetime DEFAULT NULL AFTER `activo`,
    ADD COLUMN `creado_por` int(11) DEFAULT NULL AFTER `ultimo_login`,
    ADD COLUMN `fecha_creacion` timestamp NOT NULL DEFAULT current_timestamp() AFTER `creado_por`,
    ADD UNIQUE KEY `us` (`us`),
    ADD UNIQUE KEY `email` (`email`),
    ADD KEY `creado_por` (`creado_por`),
    MODIFY COLUMN `nombre` varchar(100) NOT NULL,
    MODIFY COLUMN `us` varchar(50) NOT NULL COMMENT 'Username',
    MODIFY COLUMN `ps` varchar(255) NOT NULL COMMENT 'Password hash',
    ADD CONSTRAINT `usuarios_ibfk_2` FOREIGN KEY (`creado_por`)
        REFERENCES `usuarios` (`id`) ON DELETE SET NULL;

-- Nota: id_roles se mantiene igual (FK existente en prod apunta a roles).


-- -----------------------------------------------------------------------------
-- 1.4  tabla: productos
--      Producción tiene: id, codigo, descripcion, observaciones, activo,
--                        id_tipo_producto, cantidad_predefinida
--      Desarrollo agrega: disponible_franquicias
-- -----------------------------------------------------------------------------

ALTER TABLE `productos`
    ADD COLUMN `disponible_franquicias` tinyint(1) DEFAULT 1
        AFTER `cantidad_predefinida`;


-- =============================================================================
-- BLOQUE 2: NUEVAS TABLAS (FASE 2)
-- Orden respeta dependencias de FK.
-- =============================================================================

-- -----------------------------------------------------------------------------
-- 2.1  sesiones  (depende de: usuarios)
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `sesiones` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `id_usuario` int(11) NOT NULL,
  `token` varchar(64) NOT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` varchar(255) DEFAULT NULL,
  `creada_en` timestamp NOT NULL DEFAULT current_timestamp(),
  `ultima_actividad` timestamp NOT NULL DEFAULT current_timestamp()
      ON UPDATE current_timestamp(),
  `expira_en` datetime NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `token` (`token`),
  KEY `id_usuario` (`id_usuario`),
  KEY `idx_token` (`token`),
  KEY `idx_expira` (`expira_en`),
  CONSTRAINT `sesiones_ibfk_1` FOREIGN KEY (`id_usuario`)
      REFERENCES `usuarios` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


-- -----------------------------------------------------------------------------
-- 2.2  usuario_roles  (depende de: usuarios, roles)
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `usuario_roles` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `id_usuario` int(11) NOT NULL,
  `id_rol` int(11) NOT NULL,
  `asignado_por` int(11) DEFAULT NULL,
  `fecha_asignacion` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_usuario_rol` (`id_usuario`,`id_rol`),
  KEY `id_rol` (`id_rol`),
  KEY `asignado_por` (`asignado_por`),
  CONSTRAINT `usuario_roles_ibfk_1` FOREIGN KEY (`id_usuario`)
      REFERENCES `usuarios` (`id`) ON DELETE CASCADE,
  CONSTRAINT `usuario_roles_ibfk_2` FOREIGN KEY (`id_rol`)
      REFERENCES `roles` (`id`) ON DELETE CASCADE,
  CONSTRAINT `usuario_roles_ibfk_3` FOREIGN KEY (`asignado_por`)
      REFERENCES `usuarios` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Migrar roles actuales (tabla usuarios.id_roles → usuario_roles):
INSERT IGNORE INTO `usuario_roles` (`id_usuario`, `id_rol`)
SELECT `id`, `id_roles` FROM `usuarios` WHERE `id_roles` IS NOT NULL;


-- -----------------------------------------------------------------------------
-- 2.3  usuario_sucursales  (depende de: usuarios, ubicaciones)
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `usuario_sucursales` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `id_usuario` int(11) NOT NULL,
  `id_sucursal` int(11) NOT NULL,
  `es_sucursal_principal` tinyint(1) DEFAULT 0,
  `asignado_por` int(11) DEFAULT NULL,
  `fecha_asignacion` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_usuario_sucursal` (`id_usuario`,`id_sucursal`),
  KEY `id_sucursal` (`id_sucursal`),
  KEY `asignado_por` (`asignado_por`),
  CONSTRAINT `usuario_sucursales_ibfk_1` FOREIGN KEY (`id_usuario`)
      REFERENCES `usuarios` (`id`) ON DELETE CASCADE,
  CONSTRAINT `usuario_sucursales_ibfk_2` FOREIGN KEY (`id_sucursal`)
      REFERENCES `ubicaciones` (`id`) ON DELETE CASCADE,
  CONSTRAINT `usuario_sucursales_ibfk_3` FOREIGN KEY (`asignado_por`)
      REFERENCES `usuarios` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- NOTA: Asignar usuarios a sucursales manualmente desde el ABM de usuarios
-- o ejecutar INSERTs adicionales según la realidad operativa.


-- -----------------------------------------------------------------------------
-- 2.4  pedidos  (depende de: ubicaciones, usuarios)
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `pedidos` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `id_sucursal` int(11) NOT NULL,
  `fecha_pedido` timestamp NOT NULL DEFAULT current_timestamp(),
  `estado` enum('PENDIENTE','EN_PROCESO','ENVIADO','RECIBIDO','ANULADO') DEFAULT 'PENDIENTE',
  `prioridad` enum('baja','normal','alta') DEFAULT 'normal',
  `observaciones` text DEFAULT NULL,
  `creado_por` int(11) NOT NULL,
  `procesado_por` int(11) DEFAULT NULL,
  `fecha_procesado` datetime DEFAULT NULL,
  `anulado_por` int(11) DEFAULT NULL,
  `fecha_anulacion` datetime DEFAULT NULL,
  `motivo_anulacion` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `creado_por` (`creado_por`),
  KEY `procesado_por` (`procesado_por`),
  KEY `anulado_por` (`anulado_por`),
  KEY `idx_sucursal_estado` (`id_sucursal`,`estado`),
  KEY `idx_fecha` (`fecha_pedido`),
  CONSTRAINT `pedidos_ibfk_1` FOREIGN KEY (`id_sucursal`)
      REFERENCES `ubicaciones` (`id`),
  CONSTRAINT `pedidos_ibfk_2` FOREIGN KEY (`creado_por`)
      REFERENCES `usuarios` (`id`),
  CONSTRAINT `pedidos_ibfk_3` FOREIGN KEY (`procesado_por`)
      REFERENCES `usuarios` (`id`),
  CONSTRAINT `pedidos_ibfk_4` FOREIGN KEY (`anulado_por`)
      REFERENCES `usuarios` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


-- -----------------------------------------------------------------------------
-- 2.5  pedido_items  (depende de: pedidos, productos)
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `pedido_items` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `id_pedido` int(11) NOT NULL,
  `id_producto` int(11) NOT NULL,
  `cantidad` int(11) NOT NULL,
  `cantidad_enviada` int(11) DEFAULT 0,
  `observaciones` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_pedido_producto` (`id_pedido`,`id_producto`),
  KEY `id_producto` (`id_producto`),
  CONSTRAINT `pedido_items_ibfk_1` FOREIGN KEY (`id_pedido`)
      REFERENCES `pedidos` (`id`) ON DELETE CASCADE,
  CONSTRAINT `pedido_items_ibfk_2` FOREIGN KEY (`id_producto`)
      REFERENCES `productos` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


-- -----------------------------------------------------------------------------
-- 2.6  pedido_envio  (depende de: pedidos, movimientos, usuarios)
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `pedido_envio` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `id_pedido` int(11) NOT NULL,
  `id_envio` int(11) NOT NULL COMMENT 'ID del movimiento tipo envio',
  `fecha_asociacion` timestamp NOT NULL DEFAULT current_timestamp(),
  `asociado_por` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `id_pedido` (`id_pedido`),
  KEY `id_envio` (`id_envio`),
  KEY `asociado_por` (`asociado_por`),
  CONSTRAINT `pedido_envio_ibfk_1` FOREIGN KEY (`id_pedido`)
      REFERENCES `pedidos` (`id`),
  CONSTRAINT `pedido_envio_ibfk_2` FOREIGN KEY (`id_envio`)
      REFERENCES `movimientos` (`id`),
  CONSTRAINT `pedido_envio_ibfk_3` FOREIGN KEY (`asociado_por`)
      REFERENCES `usuarios` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


-- -----------------------------------------------------------------------------
-- 2.7  pedido_envio_items  (depende de: pedido_envio, pedido_items, movimientos_items)
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `pedido_envio_items` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `id_pedido_envio` int(11) NOT NULL,
  `id_pedido_item` int(11) NOT NULL,
  `id_movimiento_item` int(11) NOT NULL,
  `cantidad` int(11) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `id_pedido_envio` (`id_pedido_envio`),
  KEY `id_pedido_item` (`id_pedido_item`),
  KEY `id_movimiento_item` (`id_movimiento_item`),
  CONSTRAINT `pedido_envio_items_ibfk_1` FOREIGN KEY (`id_pedido_envio`)
      REFERENCES `pedido_envio` (`id`) ON DELETE CASCADE,
  CONSTRAINT `pedido_envio_items_ibfk_2` FOREIGN KEY (`id_pedido_item`)
      REFERENCES `pedido_items` (`id`),
  CONSTRAINT `pedido_envio_items_ibfk_3` FOREIGN KEY (`id_movimiento_item`)
      REFERENCES `movimientos_items` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


-- -----------------------------------------------------------------------------
-- 2.8  recepciones  (depende de: movimientos, usuarios)
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `recepciones` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `id_envio` int(11) NOT NULL COMMENT 'ID del movimiento tipo envio',
  `fecha_recepcion` timestamp NOT NULL DEFAULT current_timestamp(),
  `recibido_por` int(11) NOT NULL,
  `observaciones` text DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_envio` (`id_envio`),
  KEY `recibido_por` (`recibido_por`),
  CONSTRAINT `recepciones_ibfk_1` FOREIGN KEY (`id_envio`)
      REFERENCES `movimientos` (`id`),
  CONSTRAINT `recepciones_ibfk_2` FOREIGN KEY (`recibido_por`)
      REFERENCES `usuarios` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


-- -----------------------------------------------------------------------------
-- 2.9  recepcion_items  (depende de: recepciones, movimientos_items, productos)
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `recepcion_items` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `id_recepcion` int(11) NOT NULL,
  `id_movimiento_item` int(11) NOT NULL,
  `id_producto` int(11) NOT NULL,
  `cantidad_enviada` int(11) NOT NULL,
  `cantidad_recibida` int(11) NOT NULL,
  `diferencia` int(11) GENERATED ALWAYS AS (`cantidad_recibida` - `cantidad_enviada`) STORED,
  `peso_recibido` decimal(10,2) DEFAULT NULL,
  `observaciones` varchar(255) DEFAULT NULL,
  `dado_de_baja` tinyint(1) NOT NULL DEFAULT 0
      COMMENT 'Indica si esta bandeja ya fue dada de baja en la sucursal',
  `id_movimiento_baja` int(11) DEFAULT NULL
      COMMENT 'ID del movimiento en stock_sucursal_movimientos que registró la baja',
  PRIMARY KEY (`id`),
  KEY `id_recepcion` (`id_recepcion`),
  KEY `id_movimiento_item` (`id_movimiento_item`),
  KEY `id_producto` (`id_producto`),
  KEY `idx_dado_de_baja` (`dado_de_baja`),
  CONSTRAINT `recepcion_items_ibfk_1` FOREIGN KEY (`id_recepcion`)
      REFERENCES `recepciones` (`id`) ON DELETE CASCADE,
  CONSTRAINT `recepcion_items_ibfk_2` FOREIGN KEY (`id_movimiento_item`)
      REFERENCES `movimientos_items` (`id`),
  CONSTRAINT `recepcion_items_ibfk_3` FOREIGN KEY (`id_producto`)
      REFERENCES `productos` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


-- -----------------------------------------------------------------------------
-- 2.10  envios_archivados  (depende de: movimientos indirectamente, usuarios)
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `envios_archivados` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `id_envio` int(11) NOT NULL,
  `id_usuario` int(11) DEFAULT NULL,
  `fecha_archivado` datetime DEFAULT current_timestamp(),
  `motivo` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `id_envio` (`id_envio`),
  KEY `idx_id_envio` (`id_envio`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


-- -----------------------------------------------------------------------------
-- 2.11  stock_sucursal  (depende de: ubicaciones, productos)
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `stock_sucursal` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `id_sucursal` int(11) NOT NULL,
  `id_producto` int(11) NOT NULL,
  `cantidad_actual` int(11) DEFAULT 0,
  `peso_total` decimal(10,2) DEFAULT 0.00,
  `ultima_actualizacion` timestamp NOT NULL DEFAULT current_timestamp()
      ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_sucursal_producto` (`id_sucursal`,`id_producto`),
  KEY `id_producto` (`id_producto`),
  KEY `idx_sucursal` (`id_sucursal`),
  CONSTRAINT `stock_sucursal_ibfk_1` FOREIGN KEY (`id_sucursal`)
      REFERENCES `ubicaciones` (`id`),
  CONSTRAINT `stock_sucursal_ibfk_2` FOREIGN KEY (`id_producto`)
      REFERENCES `productos` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- NOTA: La tabla `stock` (producción) contiene stock histórico por ubicación.
--       Considerar migrar los datos a stock_sucursal si se quiere conservar el saldo:
--
-- INSERT IGNORE INTO stock_sucursal (id_sucursal, id_producto, cantidad_actual, peso_total)
-- SELECT id_ubicaciones, id_productos, cnt, cnt_peso
-- FROM stock
-- WHERE id_ubicaciones <> 1;  -- excluir depósito central


-- -----------------------------------------------------------------------------
-- 2.12  stock_sucursal_movimientos  (depende de: stock_sucursal, recepciones, usuarios)
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `stock_sucursal_movimientos` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `id_stock_sucursal` int(11) NOT NULL,
  `tipo_movimiento` enum('entrada','salida','ajuste') NOT NULL,
  `cantidad` int(11) NOT NULL,
  `peso` decimal(10,2) DEFAULT NULL,
  `id_recepcion` int(11) DEFAULT NULL COMMENT 'Si es entrada por recepcion',
  `id_baja` int(11) DEFAULT NULL COMMENT 'Si es salida por baja',
  `referencia` varchar(100) DEFAULT NULL,
  `fecha` timestamp NOT NULL DEFAULT current_timestamp(),
  `usuario` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `id_stock_sucursal` (`id_stock_sucursal`),
  KEY `id_recepcion` (`id_recepcion`),
  KEY `usuario` (`usuario`),
  KEY `idx_fecha` (`fecha`),
  CONSTRAINT `stock_sucursal_movimientos_ibfk_1` FOREIGN KEY (`id_stock_sucursal`)
      REFERENCES `stock_sucursal` (`id`),
  CONSTRAINT `stock_sucursal_movimientos_ibfk_2` FOREIGN KEY (`id_recepcion`)
      REFERENCES `recepciones` (`id`),
  CONSTRAINT `stock_sucursal_movimientos_ibfk_3` FOREIGN KEY (`usuario`)
      REFERENCES `usuarios` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


-- -----------------------------------------------------------------------------
-- 2.13  stock_minimo_sucursal  (depende de: ubicaciones, productos)
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `stock_minimo_sucursal` (
  `id_stock_minimo` int(11) NOT NULL AUTO_INCREMENT,
  `id_sucursal` int(11) NOT NULL,
  `id_producto` int(11) NOT NULL,
  `stock_minimo` decimal(10,3) NOT NULL DEFAULT 0.000,
  `stock_optimo` decimal(10,3) DEFAULT NULL,
  `activo` tinyint(1) NOT NULL DEFAULT 1,
  `fecha_creacion` datetime DEFAULT current_timestamp(),
  `fecha_modificacion` datetime DEFAULT current_timestamp()
      ON UPDATE current_timestamp(),
  `usuario_modificacion` varchar(100) DEFAULT NULL,
  PRIMARY KEY (`id_stock_minimo`),
  UNIQUE KEY `uk_sucursal_producto` (`id_sucursal`,`id_producto`),
  KEY `idx_stock_minimo_sucursal` (`id_sucursal`),
  KEY `idx_stock_minimo_producto` (`id_producto`),
  CONSTRAINT `stock_minimo_sucursal_ibfk_1` FOREIGN KEY (`id_sucursal`)
      REFERENCES `ubicaciones` (`id`),
  CONSTRAINT `stock_minimo_sucursal_ibfk_2` FOREIGN KEY (`id_producto`)
      REFERENCES `productos` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


-- =============================================================================
-- BLOQUE 3: TABLA OBSOLETA EN PRODUCCIÓN
-- =============================================================================
-- La tabla `stock` de producción fue reemplazada por stock_sucursal en Fase 2.
-- Se MANTIENE intacta para no perder datos históricos.
-- Puede eliminarse en una versión futura una vez validado el saldo en stock_sucursal.
--
-- DROP TABLE IF EXISTS `stock`;  -- ← NO ejecutar todavía


-- =============================================================================
-- FIN DE MIGRACIÓN
-- =============================================================================

SET FOREIGN_KEY_CHECKS = 1;
