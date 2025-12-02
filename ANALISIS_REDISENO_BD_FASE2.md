# REDISEÑO DE BASE DE DATOS - FASE 2
## Análisis Crítico y Propuesta Coherente

**Fecha:** 29 de Noviembre de 2025  
**Versión:** 1.0 - Análisis Profundo  
**Audiencia:** Arquitecto de BD / Supervisor Técnico

---

## I. ANÁLISIS DEL ESTADO ACTUAL

### 1.1 Estructura Existente (Fase 1)

**Tabla Central: `movimientos`**
```
movimientos
├── id (PK)
├── fechaAlta
├── id_ubicacion_origen (Depósito = 1)
├── id_ubicacion_destino (Sucursal)
├── usuario_alta
```

**Tabla de Trazabilidad: `movimientos_items`**
```
movimientos_items
├── id (PK)
├── id_movimientos (FK → movimientos)
├── id_productos
├── cnt (cantidad)
├── cnt_peso (peso)
├── id_movimientos_items_origen (CLAVE: traceo del producto)
├── id_contenedor
```

**Tabla de Estados: `estados_items_movimientos`**
```
estados_items_movimientos
├── id (PK)
├── id_estados (NUEVO → ENVIADO → RECIBIDO)
├── id_movimientos_items (FK)
├── fecha_alta
├── usuario_alta
```

**Tabla de Disponibilidad: `stock`**
```
stock
├── id (PK)
├── id_ubicaciones (Depósito/Sucursal)
├── id_productos
├── cnt (cantidad)
├── cnt_peso
```

### 1.2 ¿Cómo Funciona Hoy el Cálculo de Stock?

**Consultando la query en Envio.php (búsqueda 3-pasos):**

```sql
SELECT 
    SUM(mi.cnt) as total,
    IFNULL(SUM(referencias), 0) as referencias
FROM movimientos_items mi
WHERE mi.id_movimientos_items_origen IS NULL  -- Items ORIGINALES (Alta Depósito)
AND NOT EXISTS (
    SELECT 1 FROM movimientos_items ref 
    WHERE ref.id_movimientos_items_origen = mi.id  -- Items que referencian
)
```

**Lógica:**
1. `movimientos_items_origen IS NULL` = Alta de producto en depósito (origen)
2. No existe referencia = No ha sido despachado aún
3. **Disponible = SUM(cnt) - SUM(referencias)**

**Conclusión:** El stock actual se calcula **en tiempo real** a partir de:
- Altas originales en depósito
- Menos envíos (referencias) despachados

**La tabla `stock` NO se usa** - es un vestigio anterior.

---

## II. PROBLEMA CON EL PLAN INICIAL

### ¿Qué Creamos en el Plan Anterior?

**Tabla redundante: `stock_sucursales`**
```sql
id | id_sucursal | id_movimiento_item | cantidad | fecha
```

**¿Por qué es problemática?**
1. Duplica lógica de `movimientos_items`
2. Requiere sincronización manual al recibir
3. Riesgo de desincronización (dos fuentes de verdad)
4. Query más lenta (consulta tabla extra)

**Tabla redundante: `pedido_items`**
```sql
id | id_pedido | id_movimiento_item | cantidad_solicitada | cantidad_confirmada
```

**¿Por qué es problemática?**
1. Repite la información que ya está en un futuro `movimientos`
2. Requiere duplicar lógica de transiciones de estado
3. Pérdida de trazabilidad unificada

---

## III. PROPUESTA COHERENTE - UNA SOLA FUENTE DE VERDAD

### 3.1 Principio Central

**Un solo lugar registra TODOS los movimientos: `movimientos` + `movimientos_items`**

Ya existe el mecanismo perfecto:
- `movimientos` = El evento
- `movimientos_items` = Los items dentro del evento
- `id_movimientos_items_origen` = La trazabilidad
- `estados_items_movimientos` = La historia de estado

**¿Cómo extendemos sin duplicar?**

Usando `tipo_movimiento` en `movimientos` para diferenciar:
```
ALTA_DEPOSITO     → Alta de producto nuevo al depósito
ENVIO             → Despacho a sucursal
RECEPCION         → Recepción en sucursal (confirma que llegó)
BAJA_STOCK        → Venta/consumo en sucursal
AJUSTE_INVENTARIO → Corrección manual
PEDIDO            → Solicitud de productos (estado previo)
```

---

## IV. REDISEÑO SIN REDUNDANCIAS

### 4.1 Cambios Mínimos en `movimientos`

**ADD columns:**
```sql
ALTER TABLE movimientos ADD (
    tipo_movimiento ENUM(
        'ALTA_DEPOSITO', 
        'PEDIDO',           -- Nueva: solicitud de sucursal
        'ENVIO',            -- Ya existía
        'RECEPCION',        -- Nueva: confirmación de llegada
        'BAJA_STOCK',       -- Nueva: venta/consumo
        'AJUSTE_INVENTARIO' -- Nueva: corrección
    ) DEFAULT 'ALTA_DEPOSITO',
    id_ubicacion_sucursal INT NULL,  -- Refuerza: sucursal solicitante
    observaciones TEXT,
    FOREIGN KEY (id_ubicacion_sucursal) REFERENCES ubicaciones(id),
    INDEX (tipo_movimiento, fechaAlta)
);
```

**¿Qué Representan Ahora?**

| tipo_movimiento | id_origen | id_destino | Significado |
|-----------------|-----------|-----------|------------|
| ALTA_DEPOSITO | 1 (Depósito) | 1 (Depósito) | Entrada nueva |
| PEDIDO | Sucursal X | 1 (Depósito) | Solicitud desde X |
| ENVIO | 1 (Depósito) | Sucursal Y | Despacho a Y |
| RECEPCION | 1 (Depósito) | Sucursal Y | Confirmación recibida en Y |
| BAJA_STOCK | Sucursal Z | Sucursal Z | Venta/consumo en Z |
| AJUSTE_INVENTARIO | Sucursal Z | Sucursal Z | Corrección en Z |

---

### 4.2 Extender `estados_items_movimientos` - Nuevos Estados

```sql
-- Agregar nuevos estados a tabla `estados`
INSERT INTO estados (nombre) VALUES
(7, 'RECIBIDO_SUCURSAL'),    -- Item llegó a sucursal
(8, 'DESCUENTADO'),           -- Item fue vendido/consumido
(9, 'RECHAZADO_RECEPCION'),   -- Discrepancia en recepción
(10, 'PENDIENTE_ENVIO');      -- En preparación para enviar
```

**Ciclo de vida completo de un item:**

```
ALTA_DEPOSITO
├─ Estado: NUEVO
├─ Item en depósito, sin comprometer

PEDIDO (sucursal solicita)
├─ Estado: PENDIENTE_ENVIO
├─ Item reservado, en preparación

ENVIO (sale del depósito)
├─ Estado: ENVIADO
├─ En tránsito a sucursal

RECEPCION (llega a sucursal)
├─ Estado: RECIBIDO_SUCURSAL
├─ Confirmado en sucursal
├─ ¿Diferencia? → RECHAZADO_RECEPCION

BAJA_STOCK (venta)
├─ Estado: DESCUENTADO
├─ Item consumido/vendido

AJUSTE_INVENTARIO
├─ Estado: CANCELADO (si es rotura/pérdida)
└─ Estado: NUEVO (si reingresa)
```

---

### 4.3 Tabla MÍNIMA Nueva: `pedidos_control`

**¿La necesitamos?** SÍ, pero SOLO como índice para búsquedas rápidas, no como source of truth.

```sql
CREATE TABLE pedidos_control (
    id INT PRIMARY KEY AUTO_INCREMENT,
    id_movimiento INT NOT NULL UNIQUE,  -- Referencia al movimiento PEDIDO
    id_sucursal INT NOT NULL,
    estado ENUM('PENDIENTE', 'ACEPTADO', 'RECHAZADO', 'PREPARACION', 'LISTO_ENVIO', 'ENVIADO', 'RECIBIDO') 
        DEFAULT 'PENDIENTE',
    fecha_creacion DATETIME DEFAULT CURRENT_TIMESTAMP,
    fecha_confirmacion DATETIME NULL,
    fecha_envio DATETIME NULL,
    observaciones TEXT,
    FOREIGN KEY (id_movimiento) REFERENCES movimientos(id),
    FOREIGN KEY (id_sucursal) REFERENCES ubicaciones(id),
    INDEX (id_sucursal, estado),
    INDEX (fecha_creacion)
);
```

**¿Por qué es diferente ahora?**
- Es ÍNDICE, no source of truth
- NO duplica items (esos están en movimientos_items)
- Solo registra transiciones de estado a nivel de PEDIDO
- Query única SELECT en movimientos + movimientos_items

---

### 4.4 Tabla Stock CALCULADA (NO Física)

**Eliminamos `stock_sucursales` física. Creamos VIEW que la calcula en tiempo real:**

```sql
CREATE VIEW v_stock_actual AS
SELECT 
    ub.id as id_ubicacion,
    ub.nombre as ubicacion,
    p.id as id_producto,
    p.codigo,
    p.descripcion,
    COUNT(DISTINCT CASE 
        WHEN esim.id_estados = 1 AND m.tipo_movimiento = 'ALTA_DEPOSITO' 
        THEN mi.id 
    END) as entrada_deposito,
    COUNT(DISTINCT CASE 
        WHEN esim.id_estados = 2 AND m.tipo_movimiento = 'ENVIO' AND m.id_ubicacion_destino = ub.id
        THEN mi.id 
    END) as salidas_confirmadas,
    COUNT(DISTINCT CASE 
        WHEN esim.id_estados = 7 AND m.tipo_movimiento = 'RECEPCION' AND m.id_ubicacion_destino = ub.id
        THEN mi.id 
    END) as entradas_sucursal,
    COUNT(DISTINCT CASE 
        WHEN esim.id_estados = 8 AND m.tipo_movimiento = 'BAJA_STOCK' AND m.id_ubicacion_destino = ub.id
        THEN mi.id 
    END) as salidas_venta
FROM ubicaciones ub
CROSS JOIN productos p
LEFT JOIN movimientos m ON (
    (ub.id = 1 AND m.tipo_movimiento = 'ALTA_DEPOSITO') OR
    (ub.id = m.id_ubicacion_destino AND m.tipo_movimiento IN ('ENVIO', 'RECEPCION', 'BAJA_STOCK'))
)
LEFT JOIN movimientos_items mi ON m.id = mi.id_movimientos AND mi.id_productos = p.id
LEFT JOIN estados_items_movimientos esim ON mi.id = esim.id_movimientos_items
GROUP BY ub.id, p.id
ORDER BY ub.nombre, p.codigo;
```

**Disponible en Depósito (ubicacion_id = 1):**
```sql
SELECT 
    id_producto,
    codigo,
    (entrada_deposito - salidas_confirmadas) as disponible
FROM v_stock_actual
WHERE id_ubicacion = 1
```

**Stock en Sucursal (ej: sucursal_id = 2):**
```sql
SELECT 
    id_producto,
    codigo,
    (entradas_sucursal - salidas_venta) as stock_actual
FROM v_stock_actual
WHERE id_ubicacion = 2
```

---

### 4.5 Configuración de Stock Mínimo - TABLA SIMPLE

```sql
CREATE TABLE stock_minimo_config (
    id INT PRIMARY KEY AUTO_INCREMENT,
    id_sucursal INT NOT NULL,
    id_producto INT NOT NULL,
    cantidad_minima INT NOT NULL DEFAULT 5,
    alerta_activa BOOLEAN DEFAULT FALSE,
    fecha_configuracion DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (id_sucursal) REFERENCES ubicaciones(id),
    FOREIGN KEY (id_producto) REFERENCES productos(id),
    UNIQUE KEY unique_minimo_sucursal (id_sucursal, id_producto),
    INDEX (id_sucursal, alerta_activa)
);
```

**Consultarla con stock actual:**
```sql
SELECT 
    smc.id,
    smc.id_producto,
    p.codigo,
    smc.cantidad_minima,
    vsa.stock_actual,
    (smc.cantidad_minima - COALESCE(vsa.stock_actual, 0)) as deficit
FROM stock_minimo_config smc
LEFT JOIN productos p ON smc.id_producto = p.id
LEFT JOIN v_stock_actual vsa ON vsa.id_ubicacion = smc.id_sucursal 
    AND vsa.id_producto = smc.id_producto
WHERE smc.id_sucursal = ? 
AND COALESCE(vsa.stock_actual, 0) <= smc.cantidad_minima
```

---

## V. FLUJOS REVISADOS - Bajo el Nuevo Modelo

### 5.1 Crear Pedido (Sucursal solicita productos)

**Paso 1: Validar disponibilidad en depósito**
```sql
SELECT (entrada_deposito - salidas_confirmadas) as disponible
FROM v_stock_actual
WHERE id_ubicacion = 1 AND id_producto = ?
```

**Paso 2: Crear movimiento TIPO=PEDIDO**
```sql
INSERT INTO movimientos (
    fechaAlta, 
    id_ubicacion_origen, id_ubicacion_destino,
    id_ubicacion_sucursal,
    tipo_movimiento, 
    usuario_alta
) VALUES (
    NOW(),
    NULL,              -- Sin origen (es una solicitud)
    1,                 -- Destino: Depósito
    ?,                 -- Sucursal solicitante
    'PEDIDO',
    ?                  -- Usuario
);
```

**Paso 3: Insertar items con estado PENDIENTE_ENVIO**
```sql
INSERT INTO movimientos_items (id_movimientos, id_productos, cnt, cnt_peso)
VALUES (?, ?, ?, ?);

INSERT INTO estados_items_movimientos (
    id_estados, id_movimientos_items, fecha_alta, usuario_alta
) VALUES (10, ?, NOW(), ?); -- Estado 10 = PENDIENTE_ENVIO
```

**Paso 4: Registrar en pedidos_control (para tracking UI)**
```sql
INSERT INTO pedidos_control (
    id_movimiento, 
    id_sucursal, 
    estado, 
    fecha_creacion
) VALUES (?, ?, 'PENDIENTE', NOW());
```

---

### 5.2 Confirmar Pedido en Planta (Supervisor Planta)

**Paso 1: Validar que toda la cantidad está disponible**
```sql
SELECT SUM(mi.cnt) as solicitado
FROM movimientos_items mi
WHERE mi.id_movimientos = ?
```

```sql
SELECT (entrada_deposito - salidas_confirmadas) as disponible
FROM v_stock_actual
WHERE id_ubicacion = 1 AND id_producto = ?
```

**Paso 2: Cambiar estado a ACEPTADO y crear movimiento ENVIO**
```sql
UPDATE pedidos_control SET estado = 'ACEPTADO' WHERE id_movimiento = ?;

-- El movimiento ENVIO reutiliza items del PEDIDO
INSERT INTO movimientos (
    fechaAlta,
    id_ubicacion_origen, id_ubicacion_destino,
    id_ubicacion_sucursal,
    tipo_movimiento,
    usuario_alta
) VALUES (
    NOW(),
    1,                 -- Origen: Depósito
    ?,                 -- Destino: Sucursal
    ?,                 -- Sucursal destino
    'ENVIO',
    ?
);
```

**Paso 3: Copiar items del PEDIDO al ENVIO como referencias**
```sql
-- Los items creados en ENVIO van con id_movimientos_items_origen 
-- apuntando a los de PEDIDO

INSERT INTO movimientos_items (
    id_movimientos, 
    id_productos, 
    cnt, 
    cnt_peso,
    id_movimientos_items_origen   -- ← Apunta a item del PEDIDO
)
SELECT 
    ? as id_movimientos_nuevo,    -- ENVIO
    mi.id_productos,
    mi.cnt,
    mi.cnt_peso,
    mi.id                         -- ← Item del PEDIDO
FROM movimientos_items mi
WHERE mi.id_movimientos = ?       -- PEDIDO original
```

**Paso 4: Cambiar estado de items a ENVIADO**
```sql
INSERT INTO estados_items_movimientos (
    id_estados, 
    id_movimientos_items, 
    fecha_alta, 
    usuario_alta
)
SELECT 
    2,                            -- Estado = ENVIADO
    mi.id,
    NOW(),
    ?
FROM movimientos_items mi
WHERE mi.id_movimientos = ?       -- El nuevo ENVIO
```

**Paso 5: Actualizar pedidos_control**
```sql
UPDATE pedidos_control 
SET estado = 'LISTO_ENVIO', fecha_envio = NOW()
WHERE id_movimiento = ?
```

---

### 5.3 Recibir en Sucursal

**Paso 1: Obtener detalles del envío**
```sql
SELECT 
    m.id as id_envio,
    mi.id as id_item_envio,
    mi.cnt as cantidad_enviada,
    p.codigo, p.descripcion
FROM movimientos m
INNER JOIN movimientos_items mi ON m.id = mi.id_movimientos
LEFT JOIN productos p ON mi.id_productos = p.id
WHERE m.id = ? AND m.tipo_movimiento = 'ENVIO'
```

**Paso 2: Registrar cantidad recibida (escaneos + confirmación)**
- Escanear código de barras de cada item
- Sumar en sesión
- Confirmar cantidad vs. esperada

**Paso 3: Crear movimiento RECEPCION**
```sql
INSERT INTO movimientos (
    fechaAlta,
    id_ubicacion_origen, id_ubicacion_destino,
    id_ubicacion_sucursal,
    tipo_movimiento,
    usuario_alta,
    observaciones
) VALUES (
    NOW(),
    1,                -- Origen: Depósito
    ?,                -- Destino: Sucursal receptora
    ?,                -- Sucursal receptora
    'RECEPCION',
    ?,
    ?                 -- Observaciones de discrepancias
);
```

**Paso 4: Copiar items con nuevo estado**
```sql
-- Si cantidad recibida = cantidad enviada
INSERT INTO movimientos_items (
    id_movimientos, 
    id_productos, 
    cnt,
    cnt_peso,
    id_movimientos_items_origen
)
SELECT 
    ?,               -- RECEPCION
    mi_envio.id_productos,
    ?,               -- Cantidad recibida (podría variar)
    ?,
    mi_envio.id_movimientos_items_origen  -- Apunta al PEDIDO original
FROM movimientos_items mi_envio
WHERE mi_envio.id_movimientos = ?  -- ENVIO

-- Cambiar estado a RECIBIDO_SUCURSAL
INSERT INTO estados_items_movimientos (
    id_estados,
    id_movimientos_items,
    fecha_alta,
    usuario_alta
)
SELECT 
    7,               -- RECIBIDO_SUCURSAL
    mi.id,
    NOW(),
    ?
FROM movimientos_items mi
WHERE mi.id_movimientos = ?  -- RECEPCION
```

**Paso 5: Si hay discrepancia**
```sql
-- Si cantidad recibida < cantidad enviada
INSERT INTO movimientos_items (
    id_movimientos,
    id_productos,
    cnt,
    id_movimientos_items_origen
) VALUES (?, ?, ?, ?);  -- Item rechazado

INSERT INTO estados_items_movimientos (
    id_estados, id_movimientos_items, fecha_alta, usuario_alta
) VALUES (9, ?, NOW(), ?);  -- Estado = RECHAZADO_RECEPCION
```

---

### 5.4 Baja de Stock por Venta (Sucursal)

**Método A: Por Etiqueta (Rápido)**

**Paso 1: Escanear código**
```php
// El código de barras trae id_producto + cantidad
// Validar que existe en stock_minimo_config de la sucursal
```

**Paso 2: Acumular en sesión temporal**
```php
$_SESSION['baja_stock_sesion'][] = [
    'id_producto' => $id,
    'cantidad' => $qty,
    'timestamp' => time()
];
```

**Paso 3: Al confirmar día, crear movimiento BAJA_STOCK**
```sql
INSERT INTO movimientos (
    fechaAlta,
    id_ubicacion_origen, id_ubicacion_destino,
    tipo_movimiento,
    usuario_alta
) VALUES (
    NOW(),
    ?,                 -- Sucursal
    ?,                 -- Sucursal (mismo lugar)
    'BAJA_STOCK',
    ?
);

-- Por cada item vendido
INSERT INTO movimientos_items (
    id_movimientos,
    id_productos,
    cnt
) VALUES (?, ?, ?);

-- Cambiar estado a DESCUENTADO
INSERT INTO estados_items_movimientos (
    id_estados,
    id_movimientos_items,
    fecha_alta,
    usuario_alta
) VALUES (8, ?, NOW(), ?);  -- Estado 8 = DESCUENTADO
```

**Método B: Ajuste Manual**

Si al cerrar el día el stock físico no coincide con el teórico:

```sql
INSERT INTO movimientos (
    fechaAlta,
    id_ubicacion_origen, id_ubicacion_destino,
    tipo_movimiento,
    usuario_alta,
    observaciones
) VALUES (
    NOW(),
    ?,
    ?,
    'AJUSTE_INVENTARIO',
    ?,
    'Rotura / Merma / Corrección'
);

INSERT INTO movimientos_items (
    id_movimientos,
    id_productos,
    cnt
) VALUES (?, ?, ?);  -- Cantidad NEGATIVA si es faltante

INSERT INTO estados_items_movimientos (
    id_estados,
    id_movimientos_items,
    fecha_alta,
    usuario_alta
) VALUES (4, ?, NOW(), ?);  -- Estado 4 = CANCELADO si es pérdida
```

---

## VI. RESUMEN DE CAMBIOS EN BD

### 6.1 Tablas a MODIFICAR (NO crear nuevas)

```sql
-- 1. ALTER movimientos - Agregar campos
ALTER TABLE movimientos ADD (
    tipo_movimiento ENUM(...) DEFAULT 'ALTA_DEPOSITO',
    id_ubicacion_sucursal INT NULL,
    observaciones TEXT,
    FOREIGN KEY (id_ubicacion_sucursal) REFERENCES ubicaciones(id),
    INDEX (tipo_movimiento, fechaAlta)
);

-- 2. ALTER estados - Agregar nuevos valores
INSERT INTO estados (nombre) VALUES 
(7, 'RECIBIDO_SUCURSAL'),
(8, 'DESCUENTADO'),
(9, 'RECHAZADO_RECEPCION'),
(10, 'PENDIENTE_ENVIO');
```

### 6.2 Tabla NUEVA (ÍNDICE solamente)

```sql
-- 3. CREATE pedidos_control - Tracking UI, NO source of truth
CREATE TABLE pedidos_control (
    id INT PRIMARY KEY AUTO_INCREMENT,
    id_movimiento INT NOT NULL UNIQUE,
    id_sucursal INT NOT NULL,
    estado ENUM('PENDIENTE', 'ACEPTADO', 'RECHAZADO', 'PREPARACION', 'LISTO_ENVIO', 'ENVIADO', 'RECIBIDO') DEFAULT 'PENDIENTE',
    fecha_creacion DATETIME DEFAULT CURRENT_TIMESTAMP,
    fecha_confirmacion DATETIME NULL,
    fecha_envio DATETIME NULL,
    observaciones TEXT,
    FOREIGN KEY (id_movimiento) REFERENCES movimientos(id),
    FOREIGN KEY (id_sucursal) REFERENCES ubicaciones(id),
    INDEX (id_sucursal, estado),
    INDEX (fecha_creacion)
);
```

### 6.3 Tabla NUEVA (Configuración)

```sql
-- 4. CREATE stock_minimo_config
CREATE TABLE stock_minimo_config (
    id INT PRIMARY KEY AUTO_INCREMENT,
    id_sucursal INT NOT NULL,
    id_producto INT NOT NULL,
    cantidad_minima INT NOT NULL DEFAULT 5,
    alerta_activa BOOLEAN DEFAULT FALSE,
    fecha_configuracion DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (id_sucursal) REFERENCES ubicaciones(id),
    FOREIGN KEY (id_producto) REFERENCES productos(id),
    UNIQUE KEY unique_minimo_sucursal (id_sucursal, id_producto),
    INDEX (id_sucursal, alerta_activa)
);
```

### 6.4 VIEW para Consultas Rápidas

```sql
-- 5. CREATE VIEW v_stock_actual
CREATE VIEW v_stock_actual AS
SELECT 
    ub.id as id_ubicacion,
    ub.nombre as ubicacion,
    p.id as id_producto,
    p.codigo,
    p.descripcion,
    COUNT(DISTINCT CASE WHEN ... THEN mi.id END) as entrada_deposito,
    ...  -- Detalles en apartado anterior
FROM ubicaciones ub
CROSS JOIN productos p
LEFT JOIN ... [QUERY COMPLETA ANTERIOR]
```

### 6.5 Tablas a ELIMINAR

```sql
-- NO crear:
-- - stock_sucursales (redundante, usamos VIEW)
-- - baja_stock_sesion (usamos $_SESSION o tabla temporal)
-- - pedido_items (items están en movimientos_items)
-- - recepciones_items (items están en movimientos_items)
```

---

## VII. QUERIES CLAVE POR CASO DE USO

### Disponibilidad en Depósito (búsqueda 3-pasos)

```sql
-- SIGUE IGUAL QUE HOY (sin cambios)
SELECT 
    SUM(mi.cnt) as total,
    IFNULL(SUM(referencias), 0) as referencias
FROM movimientos_items mi
WHERE mi.id_movimientos_items_origen IS NULL
AND NOT EXISTS (...)
```

### Stock Actual en Sucursal X

```sql
SELECT 
    codigo,
    descripcion,
    (entradas_sucursal - salidas_venta) as stock_actual
FROM v_stock_actual
WHERE id_ubicacion = ?
```

### Pedidos Pendientes de Preparación

```sql
SELECT 
    pc.id,
    u.nombre as sucursal,
    COUNT(DISTINCT mi.id) as total_items,
    SUM(mi.cnt) as cantidad_total,
    pc.fecha_creacion
FROM pedidos_control pc
INNER JOIN ubicaciones u ON pc.id_sucursal = u.id
INNER JOIN movimientos m ON pc.id_movimiento = m.id
INNER JOIN movimientos_items mi ON m.id = mi.id_movimientos
WHERE pc.estado IN ('PENDIENTE', 'ACEPTADO', 'PREPARACION')
GROUP BY pc.id
ORDER BY pc.fecha_creacion ASC
```

### Recepciones Pendientes en Sucursal X

```sql
SELECT 
    m.id as id_recepcion,
    m.fechaAlta,
    COUNT(DISTINCT mi.id) as total_items,
    SUM(mi.cnt) as cantidad_total
FROM movimientos m
INNER JOIN movimientos_items mi ON m.id = mi.id_movimientos
WHERE m.tipo_movimiento = 'ENVIO'
AND m.id_ubicacion_destino = ?
AND NOT EXISTS (
    SELECT 1 FROM movimientos m2
    WHERE m2.tipo_movimiento = 'RECEPCION'
    AND m2.id_ubicacion_destino = m.id_ubicacion_destino
    AND m2.fechaAlta > m.fechaAlta
)
ORDER BY m.fechaAlta ASC
```

### Historial de Movimientos de Producto X en Sucursal Y

```sql
SELECT 
    m.id,
    m.tipo_movimiento,
    e.nombre as estado_actual,
    mi.cnt,
    m.fechaAlta,
    m.usuario_alta
FROM movimientos m
INNER JOIN movimientos_items mi ON m.id = mi.id_movimientos
INNER JOIN estados_items_movimientos esim ON mi.id = esim.id_movimientos_items
INNER JOIN estados e ON esim.id_estados = e.id
WHERE mi.id_productos = ?
AND m.id_ubicacion_destino = ?
ORDER BY m.fechaAlta DESC
```

---

## VIII. FLUJO COMPLETO - EJEMPLO PRÁCTICO

### Scenario: Sucursal 2 pide 10 unidades de Helado Frutilla (id=2)

**DÍA 1 - Mañana: Crear Pedido**

```
Sucursal 2 hace pedido:
├─ Crear movimiento: tipo=PEDIDO, origen=NULL, destino=1, sucursal=2
├─ Insertar item: id_producto=2, cantidad=10, estado=PENDIENTE_ENVIO
└─ Registrar en pedidos_control: estado=PENDIENTE
```

**DÍA 1 - Tarde: Supervisor Planta Confirma**

```
Planta Valida:
├─ Disponible en Depósito: 30 - (10 ref otras sucursales) = 20 ✓
├─ Cambiar pedidos_control: estado=ACEPTADO
├─ Crear movimiento ENVIO: origen=1, destino=2, sucursal=2
├─ Copiar items con id_movimientos_items_origen referenciando PEDIDO
├─ Estado items: ENVIADO
├─ Cambiar pedidos_control: estado=LISTO_ENVIO
└─ Avisar a Sucursal 2: "Tu pedido está listo"
```

**DÍA 2 - Mañana: Recepción en Sucursal 2**

```
Sucursal 2 recibe:
├─ Escanea etiquetas: 10 items confirman
├─ Crear movimiento RECEPCION: origen=1, destino=2, sucursal=2
├─ Items: estado=RECIBIDO_SUCURSAL
├─ Cambiar pedidos_control: estado=RECIBIDO
└─ Query v_stock_actual muestra: "Frutilla: stock = 10"
```

**DÍA 3-4: Venta**

```
Sucursal 2 vende (vendedor escanea cada venta):
├─ Sesión acumula: 5 vendidas
├─ Al cierre: Confirmar BAJA_STOCK
├─ Crear movimiento BAJA_STOCK: origen=2, destino=2
├─ Items: estado=DESCUENTADO
└─ Query v_stock_actual: "Frutilla: stock = 5"
```

**Si hay faltante:**

```
Sucursal 2 inventario físico:
├─ Teórico: 5 unidades
├─ Físico: 4 unidades (1 rota)
├─ Crear movimiento AJUSTE_INVENTARIO
├─ Registrar: -1 cantidad
├─ Item: estado=CANCELADO
└─ Query v_stock_actual: "Frutilla: stock = 4"
```

---

## IX. VENTAJAS DEL NUEVO DISEÑO

✅ **Una sola source of truth:** movimientos + movimientos_items

✅ **Sin redundancia:** No duplicamos items en múltiples tablas

✅ **Trazabilidad completa:** id_movimientos_items_origen mantiene historial completo

✅ **Estados consistentes:** estados_items_movimientos registra toda transición

✅ **Escalable:** Agregar nuevos tipos de movimiento sin cambiar estructura

✅ **Rápido:** VIEWs calculan stock en tiempo real sin tablas físicas

✅ **Auditable:** Cada acción registrada en el histórico

✅ **Menos queries:** Consolidamos 6 tablas en 1 lógica central

---

## X. IMPLEMENTACIÓN POR FASES

### Fase 2.0 - Semana 1: Preparar BD (2-3 días)

```sql
1. ALTER TABLE movimientos (ADD tipo_movimiento, etc)
2. INSERT INTO estados (nuevos valores)
3. CREATE TABLE pedidos_control
4. CREATE TABLE stock_minimo_config
5. CREATE VIEW v_stock_actual
6. VALIDATE: SELECT * FROM v_stock_actual LIMIT 5
```

### Fase 2.1 - Semana 1-2: API Pedidos

Endpoints:
- POST /api/pedidos/crear
- GET /api/pedidos/listar
- PUT /api/pedidos/{id}/aceptar
- PUT /api/pedidos/{id}/rechazar

### Fase 2.2 - Semana 2: Dashboard + Recepciones

Endpoints:
- GET /api/planta/pendientes
- PUT /api/planta/pedido/{id}/enviar
- GET /api/recepciones/pendientes
- PUT /api/recepciones/{id}/confirmar

### Fase 2.3 - Semana 2-3: Baja de Stock

Endpoints:
- POST /api/baja-stock/etiqueta
- POST /api/baja-stock/ajuste
- POST /api/baja-stock/confirmar

### Fase 2.4 - Semana 3: Stock Mínimo

Endpoints:
- GET /api/stock-minimo/listar
- PUT /api/stock-minimo/actualizar
- GET /api/stock-minimo/alertas

---

## XI. TESTS DE VALIDACIÓN

### Query: Stock Depósito vs. View

```sql
-- Antes (Fase 1 - debe seguir funcionando):
SELECT disponible FROM ... -- Query actual 3-pasos

-- Después (Fase 2 - validar que da igual):
SELECT (entrada_deposito - salidas_confirmadas) FROM v_stock_actual 
WHERE id_ubicacion = 1

-- DEBEN SER IGUALES
```

### Query: Disponibilidad Pedido

```sql
-- Validar que items del PEDIDO no afectan disponibilidad aún
SELECT (entrada_deposito - salidas_confirmadas) FROM v_stock_actual
WHERE id_ubicacion = 1 AND id_producto = ?
-- Solo resta items en ENVIO (estado ENVIADO), NO PENDIENTE_ENVIO
```

### Query: Stock Sucursal Post-Recepción

```sql
-- Después de RECEPCION, debe aparecer:
SELECT (entradas_sucursal - salidas_venta) FROM v_stock_actual
WHERE id_ubicacion = ? AND id_producto = ?
```

---

## XII. DEUDA TÉCNICA IDENTIFICADA

⚠️ **Tabla `stock` original:** No se usa. Eliminar en limpieza post-Fase 2.

⚠️ **Performance VIEW:** Si hay >100k movimientos, considerar materializar con UPDATE trigger en lugar de calculo dinámico.

⚠️ **Trigger de auditoría:** Considerar agregar trigger en movimientos_items para registrar cambios automáticamente.

---

**Documento Validado:** 29 de Noviembre de 2025  
**Estado:** Listo para implementación  
**Próximo Paso:** Feedback arquitectura + validar queries con datos reales
