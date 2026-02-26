# RESUMEN EJECUTIVO: FASE 2 - PLAN DE DESARROLLO
## Sistema Completo de Pedidos, Stock en Sucursales y Roles

**Fecha:** 13 de Enero de 2026  
**Estado:** ✅ Fase 1 completada | 📋 Fase 2 planificada  
**Documentos base:**
- [ANALISIS_PROFUNDO_FASE2_ARQUITECTURA.md](ANALISIS_PROFUNDO_FASE2_ARQUITECTURA.md)
- [PLAN_FASE_2_PEDIDOS_STOCK_SUCURSALES.md](PLAN_FASE_2_PEDIDOS_STOCK_SUCURSALES.md)
- [ESTRATEGIA_FASE2_MIGRACION_ARQUITECTURA.md](ESTRATEGIA_FASE2_MIGRACION_ARQUITECTURA.md)

---

## 🎯 ALCANCE FASE 2

### Módulos Principales

#### 1️⃣ **PEDIDOS (Sucursales → Planta)**
- Sucursales crean pedidos formales de productos
- Asistente basado en stock mínimo configurable
- Estados: PENDIENTE → RECIBIDO_PARCIAL → RECIBIDO → ANULADO
- Relación N:N con envíos (1 pedido puede ser múltiples envíos)

#### 2️⃣ **TABLERO DE PRODUCCIÓN (Planta)**
- Dashboard consolidado de todos los pedidos pendientes
- Agrupación por producto (suma cantidades de todas las sucursales)
- Vista: "Frutilla: 150 unidades (Sucursal A=50, B=100)"
- Filtros por: sucursal, producto, tipo producto, fecha

#### 3️⃣ **RECEPCIONES (Sucursales)**
- Confirmación de llegada de envíos
- Validación de cantidades recibidas vs enviadas
- Manejo de discrepancias (envío parcial, faltante)
- Actualización automática de stock local

#### 4️⃣ **BAJA DE STOCK (Sucursales)**
**Método A - Etiqueta (Rápido):**
- Escaneo de código de barras
- Acumulación en sesión
- Confirmación al final del día
- Crea movimiento tipo BAJA_STOCK

**Método B - Ajuste Manual:**
- Corrección de diferencias de inventario
- Registro de motivo
- Auditoría completa

#### 5️⃣ **STOCK MÍNIMO (Configuración)**
- Definir stock mínimo por sucursal + producto
- Alertas automáticas cuando stock < mínimo
- Asistente de pedidos sugeridos
- Cálculo: cantidad = mínimo + (consumo_diario × 7 días)

#### 6️⃣ **ABM USUARIOS + ROLES**
- 6 roles: Sistemas, Supervisor Planta, Operario Planta, Supervisor Sucursal, Operario Sucursal, Auditor
- Relación N:N usuario ↔ sucursales (un usuario puede gestionar múltiples sucursales)
- JWT con contexto de sucursal activa
- Configuración granular de permisos por rol

#### 7️⃣ **FRANQUICIAS**
- Ubicaciones: DEPOSITO_CENTRAL, SUCURSAL_PROPIA, FRANQUICIA
- Productos: campo `disponible_franquicias` (bool)
- Validación: franquicias solo pueden pedir productos permitidos
- Trigger previene pedidos de productos restringidos

---

## 🗄️ CAMBIOS EN BASE DE DATOS

### Modificaciones a Tablas Existentes

```sql
-- movimientos: agregar tipo y contexto
ALTER TABLE movimientos ADD (
    tipo_movimiento ENUM(
        'ALTA_DEPOSITO',
        'PEDIDO',
        'ENVIO',
        'RECEPCION',
        'BAJA_STOCK',
        'AJUSTE_INVENTARIO'
    ) DEFAULT 'ALTA_DEPOSITO',
    id_ubicacion_sucursal INT NULL,
    observaciones TEXT,
    estado ENUM('ABIERTO', 'CERRADO', 'CANCELADO') DEFAULT 'ABIERTO',
    fecha_cierre DATETIME NULL
);

-- ubicaciones: tipo y franquicia
ALTER TABLE ubicaciones ADD (
    tipo_ubicacion ENUM('DEPOSITO_CENTRAL', 'SUCURSAL_PROPIA', 'FRANQUICIA') 
        DEFAULT 'SUCURSAL_PROPIA'
);

-- productos: disponibilidad para franquicias
ALTER TABLE productos ADD (
    disponible_franquicias BOOLEAN DEFAULT TRUE
);
```

### Nuevas Tablas

```sql
-- Relación N:N Pedidos ↔ Envíos
CREATE TABLE pedido_envio (
    id INT PRIMARY KEY AUTO_INCREMENT,
    id_pedido INT NOT NULL,
    id_movimiento_envio INT NOT NULL,
    fecha_relacion DATETIME DEFAULT CURRENT_TIMESTAMP,
    usuario_relaciono VARCHAR(100),
    FOREIGN KEY (id_pedido) REFERENCES movimientos(id),
    FOREIGN KEY (id_movimiento_envio) REFERENCES movimientos(id),
    UNIQUE KEY (id_pedido, id_movimiento_envio)
);

-- Configuración Stock Mínimo
CREATE TABLE stock_minimo_config (
    id INT PRIMARY KEY AUTO_INCREMENT,
    id_sucursal INT NOT NULL,
    id_productos INT NOT NULL,
    cantidad_minima DECIMAL(10,3) NOT NULL,
    alerta_activa BOOLEAN DEFAULT TRUE,
    UNIQUE KEY (id_sucursal, id_productos)
);

-- Auditoría Stock Mínimo
CREATE TABLE stock_minimo_auditoria (
    id INT PRIMARY KEY AUTO_INCREMENT,
    id_stock_minimo_config INT NOT NULL,
    cantidad_anterior DECIMAL(10,3),
    cantidad_nueva DECIMAL(10,3),
    fecha_cambio DATETIME DEFAULT NOW(),
    usuario VARCHAR(100)
);

-- Usuarios (JWT)
CREATE TABLE usuarios (
    id INT PRIMARY KEY AUTO_INCREMENT,
    nombre VARCHAR(100) NOT NULL,
    email VARCHAR(150) UNIQUE NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    activo BOOLEAN DEFAULT TRUE,
    fecha_creacion DATETIME DEFAULT NOW()
);

-- Relación N:N Usuario ↔ Sucursales
CREATE TABLE usuario_sucursales (
    id INT PRIMARY KEY AUTO_INCREMENT,
    id_usuario INT NOT NULL,
    id_sucursal INT NOT NULL,
    UNIQUE KEY (id_usuario, id_sucursal)
);

-- Roles
CREATE TABLE roles (
    id INT PRIMARY KEY AUTO_INCREMENT,
    nombre VARCHAR(50) UNIQUE NOT NULL,
    descripcion TEXT
);

-- Usuario → Rol
CREATE TABLE usuario_roles (
    id INT PRIMARY KEY AUTO_INCREMENT,
    id_usuario INT NOT NULL,
    id_rol INT NOT NULL,
    UNIQUE KEY (id_usuario, id_rol)
);
```

### VIEWs para Reportes

```sql
-- Vista: Stock actual por ubicación
CREATE VIEW v_stock_actual AS
SELECT 
    u.id AS id_ubicacion,
    u.nombre AS ubicacion,
    p.id AS id_producto,
    p.nombre AS producto,
    SUM(CASE WHEN m.tipo_movimiento IN ('ALTA_DEPOSITO', 'RECEPCION') 
        THEN mi.cnt ELSE -mi.cnt END) AS stock_actual
FROM movimientos m
INNER JOIN movimientos_items mi ON m.id = mi.id_movimientos
INNER JOIN productos p ON mi.id_productos = p.id
LEFT JOIN ubicaciones u ON m.id_ubicacion_destino = u.id
WHERE m.estado = 'CERRADO'
GROUP BY u.id, p.id;

-- Vista: Resumen de pedidos
CREATE VIEW v_pedidos_resumen AS
SELECT 
    m.id AS id_pedido,
    m.fechaAlta AS fecha_pedido,
    u.nombre AS sucursal,
    COUNT(DISTINCT pe.id_movimiento_envio) AS envios_generados,
    SUM(mi.cnt) AS cantidad_total,
    -- Estado calculado
    CASE 
        WHEN NOT EXISTS (
            SELECT 1 FROM pedido_envio pe2 WHERE pe2.id_pedido = m.id
        ) THEN 'PENDIENTE'
        ELSE 'PROCESADO'
    END AS estado
FROM movimientos m
LEFT JOIN ubicaciones u ON m.id_ubicacion_sucursal = u.id
LEFT JOIN movimientos_items mi ON m.id = mi.id_movimientos
LEFT JOIN pedido_envio pe ON m.id = pe.id_pedido
WHERE m.tipo_movimiento = 'PEDIDO'
GROUP BY m.id;
```

---

## 📅 ORDEN DE DESARROLLO PROPUESTO

### 🔹 SPRINT 1: Migración + Pedidos Básicos (2 semanas)

#### Semana 1: Base de Datos
- [ ] Ejecutar script de migración BD (ALTER tables + CREATE tables)
- [ ] Validar migración: 0 data loss, schema correcto
- [ ] Crear VIEWs (v_stock_actual, v_pedidos_resumen, etc.)
- [ ] Testing de integridad referencial

#### Semana 2: API Pedidos
- [ ] `POST /api/pedidos/crear` - Crear pedido desde sucursal
- [ ] `GET /api/pedidos/mis-pedidos` - Listar pedidos de sucursal activa
- [ ] `GET /api/pedidos/{id}/detalles` - Detalle pedido + envíos asociados
- [ ] `GET /api/pedidos/asistente` - Productos bajo stock mínimo
- [ ] Tests unitarios de cada endpoint

**Entregables:**
- BD migrada y validada
- 4 endpoints de pedidos funcionando
- Tests pasando

---

### 🔹 SPRINT 2: Tablero Producción + Recepciones (2 semanas)

#### Semana 1: Dashboard Planta
- [ ] `GET /api/planta/pedidos-pendientes` - Pedidos agrupados por producto
- [ ] Frontend: `tablero_produccion.html` + JS
- [ ] Filtros: sucursal, producto, tipo, fecha
- [ ] Vista consolidada: "Frutilla: 150 unidades (Sucursal A=50, B=100)"
- [ ] Botón "Generar Envío" desde tablero

#### Semana 2: Recepciones
- [ ] `GET /api/recepciones/pendientes` - Envíos pendientes de confirmación
- [ ] `PUT /api/recepciones/{id}/confirmar` - Confirmar recepción
- [ ] Frontend: `recepciones.html` + JS
- [ ] Manejo de discrepancias (recibir menos de lo enviado)
- [ ] Actualización automática de pedido (PENDIENTE → RECIBIDO_PARCIAL → RECIBIDO)

**Entregables:**
- Tablero producción funcional
- Módulo de recepciones completo
- Actualización de estados de pedidos

---

### 🔹 SPRINT 3: Bajas de Stock + Stock Mínimo (2 semanas)

#### Semana 1: Bajas de Stock
- [ ] `POST /api/baja-stock/etiqueta` - Registrar baja por escaneo
- [ ] `POST /api/baja-stock/ajuste` - Corrección manual
- [ ] `POST /api/baja-stock/confirmar-dia` - Cierre de día
- [ ] `GET /api/baja-stock/historial` - Ver bajas históricas
- [ ] Frontend: `bajas_stock.html` + JS con scanner

#### Semana 2: Stock Mínimo
- [ ] `POST /api/stock-minimo/configurar` - Definir mínimo por sucursal/producto
- [ ] `PUT /api/stock-minimo/{id}/actualizar` - Modificar config
- [ ] `GET /api/stock-minimo/alertas` - Productos bajo mínimo
- [ ] `GET /api/stock-minimo/sugerencias` - Cantidades sugeridas
- [ ] Frontend: configuración en modal/página dedicada

**Entregables:**
- 2 métodos de baja implementados
- Sistema de stock mínimo configurable
- Asistente de pedidos automático

---

### 🔹 SPRINT 4: Autenticación JWT + Usuarios (2 semanas)

#### Semana 1: JWT Infrastructure
- [ ] Crear tablas: usuarios, roles, usuario_sucursales, usuario_roles
- [ ] Implementar JWTHandler (generar, validar, refresh tokens)
- [ ] `POST /api/auth/login` - Login con JWT
- [ ] `POST /api/auth/refresh` - Renovar token
- [ ] Middleware: JWTMiddleware + SucursalContextMiddleware
- [ ] Proteger todos los endpoints existentes

#### Semana 2: ABM Usuarios
- [ ] `GET /api/usuarios` - Listar usuarios
- [ ] `POST /api/usuarios` - Crear usuario
- [ ] `PUT /api/usuarios/{id}` - Editar usuario
- [ ] `DELETE /api/usuarios/{id}` - Desactivar usuario
- [ ] `POST /api/usuarios/{id}/sucursales` - Asignar sucursales
- [ ] `POST /api/usuarios/{id}/roles` - Asignar roles
- [ ] Frontend: `usuarios.html` + JS

**Entregables:**
- Sistema JWT completo
- Login funcional con roles
- ABM usuarios operativo

---

### 🔹 SPRINT 5: Franquicias + Reportes (1 semana)

#### Día 1-2: Franquicias
- [ ] Agregar campo `tipo_ubicacion` en ubicaciones
- [ ] Agregar campo `disponible_franquicias` en productos
- [ ] Trigger: validar pedidos de franquicias
- [ ] UI: configuración de franquicias

#### Día 3-5: Reportes
- [ ] `GET /api/reportes/pedidos` - Historial de pedidos con filtros
- [ ] `GET /api/reportes/bajas` - Historial de bajas
- [ ] `GET /api/reportes/stock-minimo` - Cumplimiento %
- [ ] Exportación a Excel/PDF
- [ ] Frontend: `reportes.html`

**Entregables:**
- Validación de franquicias funcional
- 3 reportes principales

---

## 🧪 PLAN DE TESTING

### Testing por Sprint

**Sprint 1:**
- [ ] Validar migración: queries antes/después
- [ ] Test unitarios API Pedidos (POST, GET, PUT, DELETE)
- [ ] Test integración: crear pedido → verificar en BD

**Sprint 2:**
- [ ] Test dashboard: filtros funcionan correctamente
- [ ] Test recepción: actualización de stock y estados
- [ ] Test caso edge: recepción parcial múltiple

**Sprint 3:**
- [ ] Test baja etiqueta: scanner + acumulación + confirmación
- [ ] Test ajuste manual: cálculo de diferencias
- [ ] Test alertas: productos bajo mínimo detectados

**Sprint 4:**
- [ ] Test JWT: login, refresh, expiración
- [ ] Test permisos: roles acceden solo a lo permitido
- [ ] Test multi-sucursal: filtrado correcto

**Sprint 5:**
- [ ] Test franquicia: validación de productos restringidos
- [ ] Test reportes: datos correctos, exportación funcional

---

## 🚨 RIESGOS IDENTIFICADOS

### Riesgo 1: Migración de Datos
**Probabilidad:** Media  
**Impacto:** Alto  
**Mitigación:**
- Script de rollback incluido
- Backup completo antes de ejecutar
- Testing exhaustivo en ambiente de desarrollo primero

### Riesgo 2: Race Conditions en Stock
**Probabilidad:** Alta  
**Impacto:** Alto  
**Mitigación:**
- ✅ Ya implementado: `FOR UPDATE` en confirmación de envíos
- Aplicar mismo patrón en recepciones y bajas

### Riesgo 3: Performance con Múltiples Sucursales
**Probabilidad:** Media  
**Impacto:** Medio  
**Mitigación:**
- Índices en campos clave (id_ubicacion_sucursal, tipo_movimiento)
- VIEWs optimizadas con JOIN selectivos
- Cache en frontend para datos estáticos

### Riesgo 4: Complejidad de Roles
**Probabilidad:** Baja  
**Impacto:** Medio  
**Mitigación:**
- Middleware robusto valida contexto antes de queries
- Tests exhaustivos de permisos
- Documentación clara de matriz de acceso

---

## 📊 MATRIZ DE ROLES Y PERMISOS

| Módulo / Acción | Sistemas | Supervisor Planta | Operario Planta | Supervisor Sucursal | Operario Sucursal |
|---|:---:|:---:|:---:|:---:|:---:|
| **PEDIDOS** |
| Ver todos | ✅ | ✅ | ❌ | ❌ | ❌ |
| Ver sus pedidos | ✅ | ❌ | ❌ | ✅ | ✅ |
| Crear pedido | ✅ | ❌ | ❌ | ✅ | ✅ |
| Anular pedido | ✅ | ✅ | ❌ | ✅ | ❌ |
| **ENVÍOS** |
| Ver todos | ✅ | ✅ | ❌ | ❌ | ❌ |
| Ver sus envíos | ✅ | ❌ | ❌ | ✅ | ✅ |
| Crear envío | ✅ | ✅ | ✅ | ❌ | ❌ |
| **RECEPCIONES** |
| Confirmar recepción | ✅ | ❌ | ❌ | ✅ | ✅ |
| **STOCK** |
| Ver stock central | ✅ | ✅ | ✅ | ❌ | ❌ |
| Ver stock sucursal | ✅ | ❌ | ❌ | ✅ | ✅ |
| Dar de baja | ✅ | ✅ | ✅ | ✅ | ✅ |
| **PRODUCCIÓN** |
| Ver tablero | ✅ | ✅ | ✅ | ❌ | ❌ |
| **STOCK MÍNIMO** |
| Configurar | ✅ | ✅ | ❌ | ✅ | ❌ |
| Ver alertas | ✅ | ✅ | ✅ | ✅ | ✅ |
| **USUARIOS** |
| ABM Usuarios | ✅ | ❌ | ❌ | ❌ | ❌ |
| Ver usuarios | ✅ | ✅ | ❌ | ❌ | ❌ |

---

## 🎯 MÉTRICAS DE ÉXITO

### Sprint 1
- [ ] Migración ejecutada sin errores
- [ ] 100% de datos preservados (validación)
- [ ] 4 endpoints de pedidos con tests pasando

### Sprint 2
- [ ] Dashboard muestra pedidos consolidados correctamente
- [ ] Recepciones actualizan stock y estados

### Sprint 3
- [ ] Bajas registradas en < 2 segundos
- [ ] Alertas de stock mínimo funcionan

### Sprint 4
- [ ] Login funcional con JWT
- [ ] Todos los endpoints protegidos
- [ ] Usuarios solo ven su contexto

### Sprint 5
- [ ] Franquicias validadas correctamente
- [ ] Reportes generan datos precisos

---

## 📚 DOCUMENTACIÓN COMPLEMENTARIA

- [ANALISIS_PROFUNDO_FASE2_ARQUITECTURA.md](ANALISIS_PROFUNDO_FASE2_ARQUITECTURA.md) - Arquitectura detallada con casos de uso
- [ESTRATEGIA_FASE2_MIGRACION_ARQUITECTURA.md](ESTRATEGIA_FASE2_MIGRACION_ARQUITECTURA.md) - Plan de migración zero-risk
- [PLAN_FASE_2_PEDIDOS_STOCK_SUCURSALES.md](PLAN_FASE_2_PEDIDOS_STOCK_SUCURSALES.md) - Especificaciones funcionales
- [MATRIZ_ROLES_PERMISOS.md](MATRIZ_ROLES_PERMISOS.md) - Detalle de permisos por rol
- [ABM_USUARIOS_COMPLETO.md](ABM_USUARIOS_COMPLETO.md) - Especificación ABM usuarios

---

## ✅ PRÓXIMOS PASOS INMEDIATOS

1. **Revisar este plan** - Validar orden de sprints y prioridades
2. **Ajustar timeline** - ¿2 semanas por sprint es realista?
3. **Definir fechas** - ¿Cuándo arrancamos Sprint 1?
4. **Preparar ambiente** - BD de desarrollo lista para migración
5. **Crear branches** - `feature/sprint1-migracion`, etc.

---

**Documento actualizado:** 13 de Enero de 2026  
**Próxima revisión:** Inicio de Sprint 1
