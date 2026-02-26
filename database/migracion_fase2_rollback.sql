-- ═══════════════════════════════════════════════════════════════════════════════
-- MIKELO - ROLLBACK MIGRACIÓN FASE 2
-- Sistema de Gestión de Heladerías
-- ═══════════════════════════════════════════════════════════════════════════════
-- Fecha: 23 de Enero de 2026
-- Versión: 1.1 (Ajustado para respetar tablas existentes)
-- 
-- IMPORTANTE: 
-- Este script REVIERTE todos los cambios de la migración Fase 2
-- Las tablas originales (roles, usuarios) NO se eliminan, solo se revierten columnas
-- Solo usar en caso de emergencia
-- ═══════════════════════════════════════════════════════════════════════════════

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ═══════════════════════════════════════════════════════════════════════════════
-- PARTE 1: ELIMINAR VISTAS
-- ═══════════════════════════════════════════════════════════════════════════════

DROP VIEW IF EXISTS v_pedidos_resumen;
DROP VIEW IF EXISTS v_stock_sucursal;
DROP VIEW IF EXISTS v_envios_pendientes_recepcion;

-- ═══════════════════════════════════════════════════════════════════════════════
-- PARTE 2: ELIMINAR TABLAS NUEVAS (en orden por dependencias)
-- ═══════════════════════════════════════════════════════════════════════════════

-- Stock sucursal
DROP TABLE IF EXISTS stock_sucursal_movimientos;
DROP TABLE IF EXISTS stock_sucursal;

-- Recepciones
DROP TABLE IF EXISTS recepcion_items;
DROP TABLE IF EXISTS recepciones;

-- Pedidos
DROP TABLE IF EXISTS pedido_envio_items;
DROP TABLE IF EXISTS pedido_envio;
DROP TABLE IF EXISTS pedido_items;
DROP TABLE IF EXISTS pedidos;

-- Sesiones y relaciones (NUEVAS, se pueden eliminar)
DROP TABLE IF EXISTS sesiones;
DROP TABLE IF EXISTS usuario_sucursales;
DROP TABLE IF EXISTS usuario_roles;

-- ═══════════════════════════════════════════════════════════════════════════════
-- PARTE 3: REVERTIR CAMBIOS EN TABLAS EXISTENTES
-- NOTA: NO eliminamos roles ni usuarios porque ya existían antes
-- ═══════════════════════════════════════════════════════════════════════════════

-- Quitar columnas agregadas a ubicaciones
ALTER TABLE ubicaciones DROP COLUMN IF EXISTS tipo_ubicacion;

-- Quitar columnas agregadas a productos
ALTER TABLE productos DROP COLUMN IF EXISTS disponible_franquicias;

-- Quitar columnas agregadas a roles (pero mantener tabla original)
ALTER TABLE roles DROP COLUMN IF EXISTS descripcion;
ALTER TABLE roles DROP COLUMN IF EXISTS nivel;
ALTER TABLE roles DROP COLUMN IF EXISTS activo;
ALTER TABLE roles DROP COLUMN IF EXISTS fecha_creacion;

-- Eliminar roles nuevos agregados (mantener los originales: ADMIN, USER, PRODUCTOR, INVITADO)
DELETE FROM roles WHERE nombre IN ('SUPERVISOR_PLANTA', 'OPERARIO_PLANTA', 'SUPERVISOR_SUCURSAL', 'OPERARIO_SUCURSAL');

-- Quitar columnas agregadas a usuarios (pero mantener tabla original)
ALTER TABLE usuarios DROP COLUMN IF EXISTS apellido;
ALTER TABLE usuarios DROP COLUMN IF EXISTS email;
ALTER TABLE usuarios DROP COLUMN IF EXISTS ultimo_login;
ALTER TABLE usuarios DROP COLUMN IF EXISTS fecha_creacion;
ALTER TABLE usuarios DROP COLUMN IF EXISTS fecha_actualizacion;
ALTER TABLE usuarios DROP COLUMN IF EXISTS creado_por;

-- Quitar índice si existe
DROP INDEX IF EXISTS idx_usuario_us ON usuarios;
DROP INDEX IF EXISTS idx_destino_fecha ON movimientos;

-- ═══════════════════════════════════════════════════════════════════════════════
-- FIN DEL ROLLBACK
-- ═══════════════════════════════════════════════════════════════════════════════

SET FOREIGN_KEY_CHECKS = 1;

SELECT 'Rollback Fase 2 completado - Sistema restaurado a estado Fase 1' AS resultado;
SELECT 'NOTA: Las tablas roles y usuarios originales se mantienen' AS nota;
