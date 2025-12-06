# ESTRATEGIA FASE 2: ARQUITECTURA + MIGRACIÓN SEGURA
## Plan Integral de Implementación con Zero-Risk Migration

**Fecha:** 2 de Diciembre de 2025  
**Versión:** 1.0 - Plan Estratégico  
**Audiencia:** CTO / Arquitecto técnico / Supervisor desarrollo

---

## I. VISIÓN GENERAL

Implementar Fase 2 con estos principios:
1. **Una única source of truth** usando `movimientos` + `movimientos_items`
2. **Relación N:N Pedidos ↔ Envíos** (1 pedido puede dividirse en múltiples envíos, 1 envío puede contener múltiples pedidos)
3. **Migración cero-riesgo** desde versión actual sin disrupciones
4. **Multi-sucursal con contexto JWT** (cada usuario ve solo su sucursal/es)
5. **Vistas + Reportes + Asistente** inteligente para pedidos
6. **Descuentos de stock** (NO ventas aún)

---

## II. RELACIÓN N:N PEDIDOS ↔ ENVÍOS

### 2.1 El Problema de Hoy

**Hoy (Fase 1):**
- Creamos 1 ENVIO = 1 movimiento
- Items vienen de depósito, se despachan a sucursal
- No hay "pedido" explícito (la sucursal no solicita, solo recibe)

**Mañana (Fase 2):**
- Sucursal CREA PEDIDO (solicitud formal)
- Planta REVISA y puede:
  - ✅ Aceptar todo en 1 envío
  - ✅ Aceptar parcial en múltiples envíos
  - ❌ Rechazar parcial
- Sucursal RECIBE 1 o más envíos del pedido
- Sucursal ve HISTORIAL de pedido + envíos asociados

### 2.2 Tabla Relacional: `pedido_envio` (N:N)

```sql
CREATE TABLE pedido_envio (
    id INT PRIMARY KEY AUTO_INCREMENT,
    id_pedido INT NOT NULL,
    id_movimiento_envio INT NOT NULL,
    -- cantidad_incluida es calculada (SUM items de este envío que vienen del pedido)
    fecha_relacion DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (id_pedido) REFERENCES movimientos(id),    -- PEDIDO es movimiento tipo PEDIDO
    FOREIGN KEY (id_movimiento_envio) REFERENCES movimientos(id),  -- ENVIO es movimiento tipo ENVIO
    UNIQUE KEY unique_pedido_envio (id_pedido, id_movimiento_envio),
    INDEX (id_pedido, id_movimiento_envio)
);
```

### 2.3 Ejemplo Práctico

**Scenario:**
- Sucursal 2 pide: 10 Frutilla + 5 Chocolate + 3 Vainilla
- Planta acepta pero tiene stock limitado:
  - Envío 1 (día 1): 10 Frutilla + 5 Chocolate
  - Envío 2 (día 2): 3 Vainilla + reemplazo Chocolate

```sql
-- PEDIDO
INSERT INTO movimientos (tipo_movimiento, id_ubicacion_destino, id_ubicacion_sucursal, ...)
VALUES ('PEDIDO', 1, 2, ...);  -- id_movimiento = 100

-- ENVIO 1
INSERT INTO movimientos (tipo_movimiento, id_ubicacion_origen, id_ubicacion_destino, ...)
VALUES ('ENVIO', 1, 2, ...);  -- id_movimiento = 101

-- ENVIO 2
INSERT INTO movimientos (tipo_movimiento, id_ubicacion_origen, id_ubicacion_destino, ...)
VALUES ('ENVIO', 1, 2, ...);  -- id_movimiento = 102

-- Relación
INSERT INTO pedido_envio (id_pedido, id_movimiento_envio) VALUES (100, 101);
INSERT INTO pedido_envio (id_pedido, id_movimiento_envio) VALUES (100, 102);
```

**Query: Ver todo lo que sucursal 2 pidió**
```sql
SELECT 
    m_pedido.id as id_pedido,
    m_pedido.fechaAlta as fecha_pedido,
    GROUP_CONCAT(DISTINCT m_envio.id) as envios_asociados,
    SUM(mi.cnt) as total_solicitado,
    COUNT(DISTINCT m_envio.id) as total_envios
FROM movimientos m_pedido
LEFT JOIN pedido_envio pe ON m_pedido.id = pe.id_pedido
LEFT JOIN movimientos m_envio ON pe.id_movimiento_envio = m_envio.id
LEFT JOIN movimientos_items mi ON m_pedido.id = mi.id_movimientos
WHERE m_pedido.tipo_movimiento = 'PEDIDO'
AND m_pedido.id_ubicacion_sucursal = 2
GROUP BY m_pedido.id
ORDER BY m_pedido.fechaAlta DESC
```

---

## III. ARQUITECTURA FINAL: TABLAS Y VISTAS

### 3.1 Tablas a Modificar

```sql
-- 1. MOVIMIENTOS - Agregar tipo y contexto
ALTER TABLE movimientos ADD (
    tipo_movimiento ENUM(
        'ALTA_DEPOSITO',      -- Entrada producto nuevo
        'PEDIDO',              -- Solicitud de sucursal
        'ENVIO',               -- Despacho a sucursal (puede dividirse)
        'RECEPCION',           -- Confirmación llegada
        'BAJA_STOCK',          -- Descuento (etiqueta o ajuste)
        'AJUSTE_INVENTARIO'    -- Corrección
    ) DEFAULT 'ALTA_DEPOSITO',
    
    id_ubicacion_sucursal INT NULL,  -- Sucursal solicitante/destino
    
    observaciones TEXT,              -- Notas de la operación
    
    -- Estado del movimiento (complementa estados_items_movimientos)
    estado ENUM('ABIERTO', 'CERRADO', 'CANCELADO') DEFAULT 'ABIERTO',
    
    fecha_cierre DATETIME NULL,      -- Cuándo se cerró
    
    FOREIGN KEY (id_ubicacion_sucursal) REFERENCES ubicaciones(id),
    INDEX (tipo_movimiento, fechaAlta),
    INDEX (id_ubicacion_sucursal, tipo_movimiento)
);
```

### 3.2 Tablas Nuevas

```sql
-- 2. RELACIÓN N:N PEDIDOS ↔ ENVÍOS
CREATE TABLE pedido_envio (
    id INT PRIMARY KEY AUTO_INCREMENT,
    id_pedido INT NOT NULL,
    id_movimiento_envio INT NOT NULL,
    fecha_relacion DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (id_pedido) REFERENCES movimientos(id),
    FOREIGN KEY (id_movimiento_envio) REFERENCES movimientos(id),
    UNIQUE KEY unique_pedido_envio (id_pedido, id_movimiento_envio),
    INDEX (id_pedido),
    INDEX (id_movimiento_envio)
);

-- 3. CONFIGURACIÓN STOCK MÍNIMO POR SUCURSAL
CREATE TABLE stock_minimo_config (
    id INT PRIMARY KEY AUTO_INCREMENT,
    id_sucursal INT NOT NULL,
    id_producto INT NOT NULL,
    cantidad_minima INT NOT NULL DEFAULT 5,
    
    -- Contexto para asistente
    dias_promedio_consumo DECIMAL(10,2),  -- Promedio diario
    frecuencia_reorden ENUM('SEMANAL', 'QUINCENAL', 'MENSUAL') DEFAULT 'SEMANAL',
    cantidad_sugerida_pedido INT,  -- Sugerencia calculada
    
    activo BOOLEAN DEFAULT TRUE,
    fecha_configuracion DATETIME DEFAULT CURRENT_TIMESTAMP,
    fecha_actualizacion DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (id_sucursal) REFERENCES ubicaciones(id),
    FOREIGN KEY (id_producto) REFERENCES productos(id),
    UNIQUE KEY unique_minimo_sucursal (id_sucursal, id_producto),
    INDEX (id_sucursal, activo)
);

-- 4. HISTORIAL DE CAMBIOS EN STOCK MÍNIMO (Auditoría)
CREATE TABLE stock_minimo_auditoria (
    id INT PRIMARY KEY AUTO_INCREMENT,
    id_config INT NOT NULL,
    cantidad_anterior INT,
    cantidad_nueva INT,
    usuario_cambio VARCHAR(255),
    fecha_cambio DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (id_config) REFERENCES stock_minimo_config(id),
    INDEX (id_config, fecha_cambio)
);
```

### 3.3 VISTAS para Consultas Rápidas

```sql
-- VIEW 1: STOCK ACTUAL (por ubicación)
CREATE VIEW v_stock_actual AS
SELECT 
    ub.id as id_ubicacion,
    ub.nombre as ubicacion,
    p.id as id_producto,
    p.codigo,
    p.descripcion,
    
    -- DEPÓSITO: Altas que NO fueron enviadas
    (SELECT COUNT(DISTINCT mi.id)
     FROM movimientos_items mi
     INNER JOIN movimientos m ON mi.id_movimientos = m.id
     WHERE m.tipo_movimiento = 'ALTA_DEPOSITO'
     AND mi.id_productos = p.id
     AND mi.id_movimientos_items_origen IS NULL
     AND NOT EXISTS (
        SELECT 1 FROM movimientos_items ref 
        WHERE ref.id_movimientos_items_origen = mi.id
     )
    ) as stock_deposito,
    
    -- SUCURSAL: Recibidas - Descuentadas
    (SELECT COUNT(DISTINCT mi.id)
     FROM movimientos_items mi
     INNER JOIN movimientos m ON mi.id_movimientos = m.id
     INNER JOIN estados_items_movimientos esim ON mi.id = esim.id_movimientos_items
     WHERE m.tipo_movimiento = 'RECEPCION'
     AND m.id_ubicacion_destino = ub.id
     AND mi.id_productos = p.id
     AND esim.id_estados IN (3, 7)  -- RECIBIDO, RECIBIDO_SUCURSAL
    ) 
    - 
    (SELECT COUNT(DISTINCT mi.id)
     FROM movimientos_items mi
     INNER JOIN movimientos m ON mi.id_movimientos = m.id
     INNER JOIN estados_items_movimientos esim ON mi.id = esim.id_movimientos_items
     WHERE m.tipo_movimiento = 'BAJA_STOCK'
     AND m.id_ubicacion_destino = ub.id
     AND mi.id_productos = p.id
     AND esim.id_estados = 8  -- DESCUENTADO
    ) as stock_sucursal
    
FROM ubicaciones ub
CROSS JOIN productos p
WHERE p.activo = 1
ORDER BY ub.nombre, p.codigo;

-- VIEW 2: PEDIDOS CON RESUMEN
CREATE VIEW v_pedidos_resumen AS
SELECT 
    m.id as id_pedido,
    u.nombre as sucursal_solicitante,
    m.fechaAlta as fecha_creacion,
    COUNT(DISTINCT pe.id_movimiento_envio) as total_envios,
    COUNT(DISTINCT mi.id) as total_items,
    SUM(mi.cnt) as cantidad_total,
    GROUP_CONCAT(DISTINCT pe.id_movimiento_envio) as envios_ids,
    m.estado,
    m.observaciones
FROM movimientos m
INNER JOIN ubicaciones u ON m.id_ubicacion_sucursal = u.id
LEFT JOIN movimientos_items mi ON m.id = mi.id_movimientos
LEFT JOIN pedido_envio pe ON m.id = pe.id_pedido
WHERE m.tipo_movimiento = 'PEDIDO'
GROUP BY m.id
ORDER BY m.fechaAlta DESC;

-- VIEW 3: ENVÍOS CON PEDIDOS ASOCIADOS
CREATE VIEW v_envios_resumen AS
SELECT 
    m.id as id_envio,
    u_dest.nombre as sucursal_destino,
    m.fechaAlta as fecha_envio,
    GROUP_CONCAT(DISTINCT pe.id_pedido) as pedidos_ids,
    COUNT(DISTINCT pe.id_pedido) as total_pedidos,
    COUNT(DISTINCT mi.id) as total_items,
    SUM(mi.cnt) as cantidad_total,
    m.estado
FROM movimientos m
INNER JOIN ubicaciones u_dest ON m.id_ubicacion_destino = u_dest.id
LEFT JOIN movimientos_items mi ON m.id = mi.id_movimientos
LEFT JOIN pedido_envio pe ON m.id = pe.id_movimiento_envio
WHERE m.tipo_movimiento = 'ENVIO'
GROUP BY m.id
ORDER BY m.fechaAlta DESC;

-- VIEW 4: BAJAS DE STOCK (Historial de descuentos)
CREATE VIEW v_bajas_stock_historial AS
SELECT 
    m.id as id_baja,
    u.nombre as sucursal,
    m.fechaAlta as fecha_baja,
    p.codigo as producto_codigo,
    p.descripcion as producto_descripcion,
    mi.cnt as cantidad,
    mi.cnt_peso as peso_total,
    m.observaciones,
    m.usuario_alta
FROM movimientos m
INNER JOIN ubicaciones u ON m.id_ubicacion_destino = u.id
INNER JOIN movimientos_items mi ON m.id = mi.id_movimientos
INNER JOIN productos p ON mi.id_productos = p.id
WHERE m.tipo_movimiento = 'BAJA_STOCK'
ORDER BY m.fechaAlta DESC;

-- VIEW 5: ASISTENTE STOCK MÍNIMO (Qué pedir)
CREATE VIEW v_asistente_pedidos AS
SELECT 
    smc.id,
    u.id as id_sucursal,
    u.nombre as sucursal,
    p.id as id_producto,
    p.codigo,
    p.descripcion,
    smc.cantidad_minima,
    COALESCE(vsa.stock_sucursal, 0) as stock_actual,
    (smc.cantidad_minima - COALESCE(vsa.stock_sucursal, 0)) as deficit,
    CASE 
        WHEN COALESCE(vsa.stock_sucursal, 0) <= 0 THEN 'CRÍTICO'
        WHEN COALESCE(vsa.stock_sucursal, 0) < smc.cantidad_minima THEN 'BAJO'
        ELSE 'OK'
    END as estado_stock,
    smc.cantidad_sugerida_pedido,
    smc.frecuencia_reorden,
    smc.dias_promedio_consumo
FROM stock_minimo_config smc
INNER JOIN ubicaciones u ON smc.id_sucursal = u.id
INNER JOIN productos p ON smc.id_producto = p.id
LEFT JOIN v_stock_actual vsa ON vsa.id_ubicacion = u.id 
    AND vsa.id_producto = p.id
WHERE smc.activo = TRUE
AND COALESCE(vsa.stock_sucursal, 0) < smc.cantidad_minima
ORDER BY u.nombre, FIELD(estado_stock, 'CRÍTICO', 'BAJO'), p.codigo;
```

---

## IV. PLAN DE MIGRACIÓN CERO-RIESGO

### 4.1 Fase 0: Validación Previa (1-2 días)

**Objetivo:** Garantizar que los datos existentes sean válidos

```sql
-- CHECK 1: Verificar integridad de movimientos_items
SELECT COUNT(*) as orfanos
FROM movimientos_items mi
WHERE NOT EXISTS (SELECT 1 FROM movimientos WHERE id = mi.id_movimientos);
-- Debe ser: 0

-- CHECK 2: Verificar disponibilidad actual funciona
SELECT SUM(cnt) as total_altas
FROM movimientos_items mi
WHERE id_movimientos_items_origen IS NULL
AND NOT EXISTS (
    SELECT 1 FROM movimientos_items ref 
    WHERE ref.id_movimientos_items_origen = mi.id
);
-- Debe ser > 0

-- CHECK 3: Backup automático
mysqldump -u root -p mikelo > /backup/mikelo_previa_fase2_$(date +%Y%m%d_%H%M%S).sql

-- CHECK 4: Validar estados existentes
SELECT id, nombre FROM estados ORDER BY id;
-- Debe haber: 1-NUEVO, 2-ENVIADO, 3-RECIBIDO, 4-CANCELADO
```

### 4.2 Fase 1: Migración de Datos Existentes (1-2 días)

**Objetivo:** Asignar tipo_movimiento a movimientos existentes

```sql
-- PASO 1: Identificar ALTA_DEPOSITO (origen = 1, destino = 1)
UPDATE movimientos 
SET tipo_movimiento = 'ALTA_DEPOSITO'
WHERE id_ubicacion_origen = 1 AND id_ubicacion_destino = 1
AND tipo_movimiento = 'ALTA_DEPOSITO';  -- Ya tiene

-- PASO 2: Identificar ENVIO (origen = 1, destino != 1)
UPDATE movimientos 
SET tipo_movimiento = 'ENVIO'
WHERE id_ubicacion_origen = 1 
AND id_ubicacion_destino != 1
AND tipo_movimiento IS NULL;

-- PASO 3: Asignar id_ubicacion_sucursal para envíos
UPDATE movimientos 
SET id_ubicacion_sucursal = id_ubicacion_destino
WHERE tipo_movimiento = 'ENVIO';

-- PASO 4: Establecer todos como 'CERRADO' (histórico)
UPDATE movimientos 
SET estado = 'CERRADO'
WHERE estado IS NULL OR estado = '';

-- PASO 5: Validar integridad
SELECT tipo_movimiento, COUNT(*) as total
FROM movimientos
GROUP BY tipo_movimiento;
-- Esperado: ALTA_DEPOSITO: X, ENVIO: Y
```

### 4.3 Fase 2: Crear Infraestructura Nueva (1 día)

```bash
# Script SQL: migracion_fase2.sql

1. ALTER TABLE movimientos (ADD campos)
2. UPDATE movimientos (migrar datos)
3. INSERT INTO estados (nuevos estados: 7-RECIBIDO_SUCURSAL, 8-DESCUENTADO, 9-RECHAZADO_RECEPCION, 10-PENDIENTE_ENVIO)
4. CREATE TABLE pedido_envio
5. CREATE TABLE stock_minimo_config
6. CREATE TABLE stock_minimo_auditoria
7. CREATE VIEW v_stock_actual
8. CREATE VIEW v_pedidos_resumen
9. CREATE VIEW v_envios_resumen
10. CREATE VIEW v_bajas_stock_historial
11. CREATE VIEW v_asistente_pedidos

# Validar integridad
mysql -u root -p mikelo < /backup/integridad_post_migracion.sql
```

### 4.4 Fase 3: Tests de Regresión (1-2 días)

```sql
-- TEST 1: Disponibilidad del depósito NO cambió
SELECT 'TEST 1: Stock Depósito' as test,
  (SELECT COUNT(*) FROM movimientos_items mi
   WHERE id_movimientos_items_origen IS NULL
   AND NOT EXISTS (SELECT 1 FROM movimientos_items ref WHERE ref.id_movimientos_items_origen = mi.id)
  ) as disponible_actual
;

-- TEST 2: Envíos existentes siguen siendo válidos
SELECT COUNT(*) as envios_existentes
FROM movimientos
WHERE tipo_movimiento = 'ENVIO';
-- Debe ser igual a envíos de antes

-- TEST 3: Vista de stock actual calcula correctamente
SELECT * FROM v_stock_actual
WHERE stock_deposito > 0
LIMIT 5;

-- TEST 4: Datos históricos no cambiaron
SELECT COUNT(DISTINCT id) as total_movimientos_antes_migrar
FROM movimientos;
-- Debe ser igual a conteo anterior
```

### 4.5 Rollback Plan (Si algo falla)

```sql
-- Si hay error, restaurar backup
mysql -u root -p mikelo < /backup/mikelo_previa_fase2_FECHA.sql

-- Opción 2: Revertir cambios manualmente
ALTER TABLE movimientos DROP COLUMN tipo_movimiento;
ALTER TABLE movimientos DROP COLUMN id_ubicacion_sucursal;
-- etc...
```

---

## V. ENDPOINTS API - FASE 2

### 5.1 Módulo Pedidos (Sucursal)

```
POST   /api/pedidos/crear
       {
         "items": [
           {"id_producto": 2, "cantidad": 10},
           {"id_producto": 5, "cantidad": 5}
         ]
       }
       → id_pedido, estado, fecha_creacion

GET    /api/pedidos/mis-pedidos
       → Lista de pedidos de la sucursal
       
GET    /api/pedidos/{id}/detalles
       → Detalles del pedido + envíos asociados
       
GET    /api/pedidos/asistente
       → Vista v_asistente_pedidos (stock bajo)
```

### 5.2 Módulo Bajas de Stock (Sucursal)

```
POST   /api/baja-stock/registrar-etiqueta
       {
         "id_producto": 2,
         "cantidad": 1
       }
       → Acumula en sesión
       
GET    /api/baja-stock/sesion-actual
       → Items en sesión del día
       
POST   /api/baja-stock/confirmar-dia
       → Cierra movimiento BAJA_STOCK
       
GET    /api/baja-stock/historial
       → Histórico de bajas (VIEW v_bajas_stock_historial)
```

### 5.3 Módulo Dashboard Planta

```
GET    /api/planta/pedidos-pendientes
       → Pedidos sin enviar
       
PUT    /api/planta/pedido/{id}/aceptar
       → Cambiar a estado ACEPTADO
       
PUT    /api/planta/pedido/{id}/enviar
       → Crear movimiento ENVIO + relación pedido_envio
```

### 5.4 Módulo Stock Mínimo (Admin)

```
POST   /api/stock-minimo/configurar
       {
         "id_sucursal": 2,
         "id_producto": 5,
         "cantidad_minima": 20,
         "frecuencia_reorden": "SEMANAL"
       }
       
PUT    /api/stock-minimo/{id}/actualizar
       
GET    /api/stock-minimo/alertas
       → Productos bajo mínimo
```

---

## VI. CAPAS DE SEGURIDAD: CONTEXTO MULTI-SUCURSAL

### 6.1 JWT + Contexto Sucursal

**Payload del token:**
```json
{
  "id_usuario": 5,
  "nombre": "Juan Operario",
  "id_rol": 6,
  "rol_nombre": "Operario",
  "sucursales_permitidas": [2, 3],  // Array de IDs de sucursales
  "sucursal_principal": 2,
  "iat": 1701518400,
  "exp": 1701604800
}
```

### 6.2 Filtro de Sucursal en Endpoints

**Principio:** TODO endpoint que devuelve datos de sucursal debe validar:

```php
// Middleware: ValidarSucursalContext
public function __invoke($request, $response, $next) {
    $usuario = $request->getAttribute('usuario');
    $id_sucursal_solicitada = $request->getQueryParams()['id_sucursal'] ?? null;
    
    if ($id_sucursal_solicitada && 
        !in_array($id_sucursal_solicitada, $usuario['sucursales_permitidas'])) {
        return $response->withStatus(403)
            ->withJson(['error' => 'Sin permisos para esta sucursal']);
    }
    
    // Si no especifica, usar principal
    $request = $request->withAttribute('id_sucursal', 
        $id_sucursal_solicitada ?? $usuario['sucursal_principal']
    );
    
    return $next($request, $response);
}

// Uso en Controller:
public function misPedidos($request, $response) {
    $id_sucursal = $request->getAttribute('id_sucursal');
    
    $query = "SELECT * FROM v_pedidos_resumen WHERE sucursal_solicitante_id = ?";
    // ...
}
```

### 6.3 Endpoint Ubicaciones Dinámico

**GET /api/ubicaciones/permitidas**
```php
// Devuelve solo las ubicaciones que el usuario puede ver
$id_usuario = $usuario['id_usuario'];
$sucursales = $usuario['sucursales_permitidas'];

$stmt = $db->prepare("
    SELECT id, nombre FROM ubicaciones 
    WHERE id IN (" . implode(',', $sucursales) . ")
    ORDER BY nombre
");

// Resultado: [{id: 2, nombre: "Sucursal 1"}, {id: 3, nombre: "Sucursal 2"}]
```

---

## VII. VISTAS Y REPORTES

### 7.1 Dashboard Sucursal (Frontend)

**Pantalla principal:**
```
┌─────────────────────────────────────────────────────┐
│ DASHBOARD - Sucursal [Nombre Sucursal]              │
├─────────────────────────────────────────────────────┤
│                                                       │
│ ┌─ MIS PEDIDOS ─────────────────────────────────┐  │
│ │ Estado: Abiertos (5) | Entregados (23)        │  │
│ │ Pedido | Fecha | Items | Estado | Envíos     │  │
│ │ #1024  | Dec 1 |  18   | Enviado|    2       │  │
│ │ #1025  | Dec 2 |  10   | Pendiente| 0        │  │
│ └────────────────────────────────────────────────┘  │
│                                                       │
│ ┌─ STOCK CRÍTICO ────────────────────────────────┐  │
│ │ Usar asistente para reponer                   │  │
│ │ Producto | Actual | Mínimo | Falta           │  │
│ │ Frutilla | 2      | 10     | 8        [PEDIR]│  │
│ └────────────────────────────────────────────────┘  │
│                                                       │
│ ┌─ BAJAS DE HOY ────────────────────────────────────┐  │
│ │ Total: 45 unidades descuentadas                   │  │
│ │ Producto | Cantidad | Hora | Usuario              │  │
│ │ Frutilla | 5        | 09:30| Juan                 │  │
│ └────────────────────────────────────────────────────┘  │
│                                                       │
└─────────────────────────────────────────────────────┘
```

### 7.2 Pantalla Crear Pedido (Asistente Inteligente)

**Flujo:**
```
1. Cargar v_asistente_pedidos (productos bajo mínimo)
2. Mostrar en tabla:
   - Producto | Stock Actual | Mínimo | Deficit | Sugerencia
3. Usuario puede:
   - Usar cantidad sugerida (checkbox)
   - Editar manualmente
   - Agregar/quitar productos
4. Revisar totales
5. Enviar pedido
```

**Cálculo de sugerencia:**
```
cantidad_sugerida = 
    cantidad_minima              -- Llegar al mínimo
    + (dias_promedio_consumo * 7)  -- + semana de stock extra
```

### 7.3 Pantalla Bajas de Stock

**Método A: Por Etiqueta**
```
┌────────────────────────────┐
│ REGISTRAR VENTA POR ETIQUETA
├────────────────────────────┤
│ Escanear o ingresar código:│
│ [────────────────────────] │
│                            │
│ Sesión del día:           │
│ Producto | Cantidad        │
│ Frutilla | 5               │
│ Chocolate| 3               │
│          │                 │
│ Total: 8 artículos        │
│                            │
│ [Confirmar Día] [Limpiar]  │
└────────────────────────────┘
```

**Método B: Ajuste Manual**
```
┌────────────────────────────┐
│ AJUSTE MANUAL DE STOCK     │
├────────────────────────────┤
│ Producto: [Frutilla ▼]     │
│ Stock Actual: 10           │
│ Stock Físico: 8            │
│ Diferencia: -2             │
│ Motivo: [Rotura ▼]         │
│ Observaciones: [________]  │
│                            │
│ [Aplicar Ajuste]           │
└────────────────────────────┘
```

### 7.4 Reportes

**Reporte 1: Pedidos por Sucursal**
```
GET /api/reportes/pedidos
?id_sucursal=2&desde=2025-12-01&hasta=2025-12-31
→ JSON con estadísticas
```

**Reporte 2: Historial de Bajas**
```
GET /api/reportes/bajas
?id_sucursal=2&desde=2025-12-01&hasta=2025-12-31
→ CSV o PDF con detalle de bajas
```

**Reporte 3: Cumplimiento Stock Mínimo**
```
GET /api/reportes/stock-minimo
?id_sucursal=2
→ % de tiempo en crítico, bajo, ok
```

---

## VIII. CALENDARIO DE IMPLEMENTACIÓN REVISADO

### Semana 1 (Dic 2-6)

**Lunes-Martes:** Migración segura
- [ ] Backup completo
- [ ] ALTER TABLE movimientos
- [ ] INSERT nuevos estados
- [ ] CREATE pedido_envio
- [ ] CREATE stock_minimo_config
- [ ] Tests de regresión

**Miércoles-Viernes:** API base + Endpoints Pedidos
- [ ] POST /api/pedidos/crear
- [ ] GET /api/pedidos/mis-pedidos
- [ ] GET /api/pedidos/asistente
- [ ] POST /api/stock-minimo/configurar

### Semana 2 (Dic 9-13)

**Lunes-Martes:** Bajas de Stock
- [ ] POST /api/baja-stock/registrar-etiqueta
- [ ] POST /api/baja-stock/confirmar-dia
- [ ] GET /api/baja-stock/historial
- [ ] Frontend bajas (etiqueta + ajuste)

**Miércoles-Viernes:** Dashboard Planta
- [ ] GET /api/planta/pedidos-pendientes
- [ ] PUT /api/planta/pedido/{id}/aceptar
- [ ] PUT /api/planta/pedido/{id}/enviar
- [ ] Frontend dashboard

### Semana 3 (Dic 16-20)

**Lunes-Martes:** Vistas + Reportes
- [ ] CREATE/UPDATE VIEWs
- [ ] GET /api/reportes/pedidos
- [ ] GET /api/reportes/bajas
- [ ] Frontend reportes

**Miércoles-Viernes:** Tests + Refinamiento
- [ ] Tests de integración
- [ ] Validar contexto multi-sucursal
- [ ] Performance tuning
- [ ] Documentación

---

## IX. CONSIDERACIONES A LARGO PLAZO

### Escalabilidad

**Si llegan >1M movimientos:**
- Particionar `movimientos` por año
- Materializar VIEWs en tablas físicas con UPDATE trigger
- Crear índices adicionales en (id_ubicacion_sucursal, tipo_movimiento, fechaAlta)

### Futuras Extensiones

- **Facturas:** Crear tabla `facturas` que referencie `pedidos`
- **Devoluciones:** Nuevo tipo_movimiento `DEVOLUCION`
- **Multi-contenedor:** Seguimiento por contenedor/palé
- **Predicción de demanda:** ML sobre histórico de bajas

---

## X. MÉTRICAS DE ÉXITO

✅ Migración sin downtime
✅ Todos los datos históricos accesibles
✅ Contexto multi-sucursal funcional
✅ Disponibilidad del depósito sin cambios
✅ Pedidos creables y trackeables
✅ Bajas registrables con historial
✅ Asistente de stock mínimo preciso

---

**Documento Validado:** 2 de Diciembre de 2025  
**Status:** Listo para implementación  
**Próximo Paso:** Validar plan de migración con datos reales + inicio desarrollo Semana 1
