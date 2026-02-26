# ANÁLISIS PROFUNDO: ARQUITECTURA FASE 2
## Estrategia Integral de Pedidos, Envíos, Roles y Presentación de Datos

**Fecha:** 9 de Diciembre de 2025  
**Contexto:** Basado en apuntes de Daniel Rojas  
**Estado:** En Análisis - Requiere Diálogo Profundo

---

## 🎯 PROBLEMA CENTRAL

**La pregunta madre:**
> "¿Cómo almacenamos pedidos y los relacionamos con envíos existentes, considerando que 1 pedido → múltiples envíos y 1 envío → múltiples pedidos?"

**Implicancias:**
1. Trazabilidad completa (auditoría)
2. Presentación diferenciada por rol
3. Consistencia de datos (evitar orphans)
4. Performance con múltiples sucursales

---

## 📊 ANÁLISIS 1: RELACIÓN PEDIDOS ↔ ENVÍOS

### 1.1 El Mapa Mental de Pedidos

Basándonos en tus apuntes, un **PEDIDO** es:
- **Quién lo hace:** Supervisor/Operario de Sucursal
- **Qué es:** Solicitud formal de productos (con cantidades)
- **A quién:** A la Planta (depósito central)
- **Estados permitidos:** 
  - `PENDIENTE` - Acaba de crearse
  - `RECIBIDO` - Se cumplió totalmente
  - `RECIBIDO_PARCIAL` - Parte llegó, espera más
  - `ANULADO` - Se canceló

**Pregunta clave:** ¿El pedido es un movimiento o es un "documento maestro"?

**Propuesta:** Es un **movimiento tipo PEDIDO** en la tabla `movimientos`, con:
- `tipo_movimiento = 'PEDIDO'`
- `id_ubicacion_origen = NULL` (no viene de un lugar físico)
- `id_ubicacion_destino = depósito central (1)`
- `id_ubicacion_sucursal = sucursal que pide`
- Items en `movimientos_items` con cantidades solicitadas

### 1.2 El Mapa Mental de Envíos

Un **ENVÍO** es:
- **Quién lo hace:** Operario/Supervisor Planta
- **Qué es:** Despacho físico de productos
- **De dónde:** Depósito central (1)
- **A dónde:** Una sucursal
- **Cuándo:** En respuesta a 1+ pedidos

**Estructura actual (Fase 1):**
```
ENVIO = movimiento con:
  - tipo_movimiento = 'ENVIO'
  - id_ubicacion_origen = 1 (depósito)
  - id_ubicacion_destino = N (sucursal)
  - Items: referencias + cantidades
```

**Cambio para Fase 2:**
Agregar relación explícita: `pedido_envio (id_pedido, id_movimiento_envio)`

### 1.3 Relación N:N: Ejemplos Prácticos

#### Caso 1: 1 Pedido → 1 Envío (Simple)
```
Sucursal 5 pide:
  - 10 Frutilla
  - 5 Chocolate
  
Planta prepara 1 envío:
  - ENVIO #201: 10 Frutilla + 5 Chocolate
  
Relación:
  PEDIDO #100 ←→ ENVIO #201
```

#### Caso 2: 1 Pedido → 2+ Envíos (Dividido por falta de stock)
```
Sucursal 5 pide:
  - 100 Frutilla
  - 50 Chocolate
  
Planta tiene:
  - Frutilla: solo 60 disponibles hoy, 40 mañana
  - Chocolate: 50 disponibles hoy
  
Despacha 2 envíos:
  - ENVIO #201: 60 Frutilla + 50 Chocolate (hoy)
  - ENVIO #202: 40 Frutilla (mañana)
  
Relación:
  PEDIDO #100 ←→ ENVIO #201
  PEDIDO #100 ←→ ENVIO #202
```

#### Caso 3: 2 Pedidos → 1 Envío (Consolidación por eficiencia)
```
Sucursal 3 pide:
  - 10 Frutilla
  - 5 Chocolate
  
Sucursal 4 pide:
  - 20 Vainilla
  - 3 Fresa
  
Planta consolida:
  - ENVIO #203: 10 Frutilla + 5 Chocolate + 20 Vainilla + 3 Fresa
            (dirigido a sucursal 3, pero con nota que incluye pedido de sucursal 4)
            
CUIDADO: Esto es problemático porque el envío va a UN destino físico.

Mejor:
  - ENVIO #203: dirigido a sucursal 3
  - ENVIO #204: dirigido a sucursal 4
  
O usar un contenedor intermediario... (veremos en Transporte)
```

#### Caso 4: Pedido con Recepción Parcial Múltiple
```
Sucursal 2 pide:
  - 30 Frutilla
  
Planta envía:
  - ENVIO #205: 15 Frutilla (Recibido el lunes)
  - ENVIO #206: 15 Frutilla (Recibido el miércoles)
  
Estados del Pedido:
  - Después ENVIO #205: RECIBIDO_PARCIAL (15/30)
  - Después ENVIO #206: RECIBIDO (30/30)
  
Auditoría:
  - Pedido #101: Solicitó 30
  - Recibió: 15 el lunes, 15 el miércoles
  - Historial de 2 envíos visible
```

### 1.4 Tabla `pedido_envio` - Especificación Exacta

```sql
CREATE TABLE pedido_envio (
    id INT PRIMARY KEY AUTO_INCREMENT,
    
    -- Claves foráneas
    id_pedido INT NOT NULL,                   -- Movimiento tipo PEDIDO
    id_movimiento_envio INT NOT NULL,         -- Movimiento tipo ENVIO
    
    -- Informativa (calculada, pero cacheable)
    cantidad_total_envio INT COMMENT 'SUM de items en este envio que aplican a este pedido',
    
    -- Auditoría
    fecha_relacion DATETIME DEFAULT CURRENT_TIMESTAMP,
    usuario_relaciono VARCHAR(100),           -- JWT claim
    
    -- Constraints
    FOREIGN KEY (id_pedido) REFERENCES movimientos(id) 
        ON DELETE RESTRICT,  -- No permitir eliminar pedido si hay relación
    
    FOREIGN KEY (id_movimiento_envio) REFERENCES movimientos(id) 
        ON DELETE RESTRICT,
    
    -- Evitar duplicados
    UNIQUE KEY unique_pedido_envio (id_pedido, id_movimiento_envio),
    
    -- Índices para búsquedas comunes
    INDEX idx_pedido (id_pedido),
    INDEX idx_envio (id_movimiento_envio),
    INDEX idx_fecha (fecha_relacion)
);
```

---

## 🗄️ ANÁLISIS 2: ALMACENAMIENTO DE PEDIDOS

### 2.1 Decisión: Pedidos = Movimientos tipo PEDIDO

**¿Por qué?**

Alternativa 1: Tabla separada `pedidos` (con campos propios)
- ❌ Redundancia con movimientos
- ❌ Sincronización compleja
- ❌ Auditoría fragmentada

Alternativa 2: **Pedidos = movimientos con tipo 'PEDIDO'** ✅
- ✅ Una única source of truth
- ✅ Auditoría integrada (quien, qué, cuándo)
- ✅ Estados en `estados_items_movimientos`
- ✅ Items en `movimientos_items`

**Estructura del Pedido como Movimiento:**

```sql
-- Crear un PEDIDO
INSERT INTO movimientos (
    tipo_movimiento,           -- 'PEDIDO'
    id_ubicacion_origen,       -- NULL (no es physical source)
    id_ubicacion_destino,      -- 1 (depósito/planta)
    id_ubicacion_sucursal,     -- 3 (la sucursal que pide)
    fechaAlta,                 -- hoy
    observaciones,             -- "Pedido semanal Sucursal 3"
    estado                     -- 'ABIERTO'
) VALUES (
    'PEDIDO', NULL, 1, 3, NOW(), 'Pedido urgente', 'ABIERTO'
);
-- Resultado: movimientos.id = 150

-- Agregar items al pedido
INSERT INTO movimientos_items (
    id_movimientos,     -- 150 (el pedido)
    id_productos,       -- 5 (Frutilla)
    cnt                 -- 50 (cantidad solicitada)
) VALUES (150, 5, 50);

INSERT INTO movimientos_items (id_movimientos, id_productos, cnt) 
VALUES (150, 8, 30);  -- Chocolate

-- Registrar estado inicial
INSERT INTO estados_items_movimientos (
    id_movimientos_items,
    id_estados,
    fecha_estado
) VALUES (
    (SELECT id FROM movimientos_items WHERE id_movimientos = 150 AND id_productos = 5),
    1,  -- Estado NUEVO
    NOW()
);
```

### 2.2 Estados del Pedido (Computed)

**Estado de un PEDIDO = función de sus items**

```sql
-- Vista para calcular estado del pedido
CREATE OR REPLACE VIEW v_estado_pedido AS
SELECT
    m.id AS id_pedido,
    CASE 
        -- Pendiente: ningún item recibido aún
        WHEN NOT EXISTS (
            SELECT 1 FROM movimientos_items mi 
            INNER JOIN estados_items_movimientos eim ON mi.id = eim.id_movimientos_items
            WHERE mi.id_movimientos = m.id 
            AND eim.id_estados = 4  -- RECIBIDO
        ) THEN 'PENDIENTE'
        
        -- Parcial: algunos items recibidos
        WHEN EXISTS (
            SELECT 1 FROM movimientos_items mi 
            INNER JOIN estados_items_movimientos eim ON mi.id = eim.id_movimientos_items
            WHERE mi.id_movimientos = m.id 
            AND eim.id_estados = 4
        ) AND EXISTS (
            SELECT 1 FROM movimientos_items mi 
            INNER JOIN estados_items_movimientos eim ON mi.id = eim.id_movimientos_items
            WHERE mi.id_movimientos = m.id 
            AND eim.id_estados IN (1, 2)  -- NUEVO, ENVIADO
        ) THEN 'RECIBIDO_PARCIAL'
        
        -- Completo: todos los items recibidos
        WHEN NOT EXISTS (
            SELECT 1 FROM movimientos_items mi 
            INNER JOIN estados_items_movimientos eim ON mi.id = eim.id_movimientos_items
            WHERE mi.id_movimientos = m.id 
            AND eim.id_estados IN (1, 2)
        ) THEN 'RECIBIDO'
        
        ELSE 'DESCONOCIDO'
    END AS estado,
    
    -- Cantidad total solicitada
    SUM(mi.cnt) AS cantidad_solicitada,
    
    -- Cantidad recibida (SUM de items con estado RECIBIDO)
    (SELECT COALESCE(SUM(cnt), 0) FROM movimientos_items mi2
     WHERE mi2.id_movimientos = m.id
     AND EXISTS (
        SELECT 1 FROM estados_items_movimientos eim 
        WHERE eim.id_movimientos_items = mi2.id 
        AND eim.id_estados = 4
     )) AS cantidad_recibida
    
FROM movimientos m
LEFT JOIN movimientos_items mi ON m.id = mi.id_movimientos
WHERE m.tipo_movimiento = 'PEDIDO'
GROUP BY m.id;
```

### 2.3 Trazabilidad Completa: De Pedido a Envío a Recepción

```sql
-- Query: Ver la trazabilidad completa de un pedido
SELECT
    m_pedido.id AS id_pedido,
    m_pedido.fechaAlta AS fecha_pedido,
    vep.estado AS estado_pedido,
    vep.cantidad_solicitada,
    vep.cantidad_recibida,
    
    -- Items del pedido
    mi.id_productos,
    prod.nombre AS producto,
    mi.cnt AS cantidad_solicitada_item,
    
    -- Envíos relacionados
    pe.id_movimiento_envio,
    m_envio.fechaAlta AS fecha_envio,
    
    -- Estado del item en el envío
    eei.id_estados,
    (SELECT nombre FROM estados WHERE id = eei.id_estados) AS estado_item
    
FROM movimientos m_pedido
LEFT JOIN v_estado_pedido vep ON m_pedido.id = vep.id_pedido
LEFT JOIN movimientos_items mi ON m_pedido.id = mi.id_movimientos
LEFT JOIN productos prod ON mi.id_productos = prod.id
LEFT JOIN pedido_envio pe ON m_pedido.id = pe.id_pedido
LEFT JOIN movimientos m_envio ON pe.id_movimiento_envio = m_envio.id
LEFT JOIN estados_items_movimientos eei ON mi.id = eei.id_movimientos_items
WHERE m_pedido.id = 150  -- El pedido específico
ORDER BY mi.id_productos, m_envio.fechaAlta;
```

---

## 👥 ANÁLISIS 3: ESTRATEGIA DE PRESENTACIÓN POR ROL

### 3.1 Matriz de Acceso y Funcionalidades

| Módulo / Acción | Sistemas | Supervisor Planta | Operario Planta | Supervisor Sucursal | Operario Sucursal |
|---|---|---|---|---|---|
| **PEDIDOS** | | | | | |
| Ver todos | ✅ | ✅ | ❌ | ❌ (solo sus sucursales) | ❌ (solo sus sucursales) |
| Ver sus pedidos | ✅ | ❌ | ❌ | ✅ | ✅ |
| Crear pedido | ✅ | ❌ | ❌ | ✅ | ✅ |
| Estado del pedido | ✅ | ✅ | ❌ | ✅ | ✅ |
| Detalles (items + envíos) | ✅ | ✅ | ❌ | ✅ | ✅ |
| **ENVÍOS** | | | | | |
| Ver todos | ✅ | ✅ | ❌ | ❌ | ❌ |
| Ver sus envíos recibidos | ✅ | ❌ | ❌ | ✅ | ✅ |
| Crear envío | ✅ | ✅ | ✅ | ❌ | ❌ |
| Confirmar recepción | ✅ | ❌ | ❌ | ✅ | ✅ |
| **STOCK** | | | | | |
| Ver stock central | ✅ | ✅ | ✅ | ❌ | ❌ |
| Ver stock sucursal | ✅ | ❌ | ❌ | ✅ | ✅ |
| Dar de baja manual | ✅ | ✅ | ✅ | ✅ | ✅ |
| Dar de baja con lectora | ✅ | ✅ | ✅ | ✅ | ✅ |
| Agregar manual | ✅ | ✅ | ✅ | ✅ | ✅ |
| **PRODUCCIÓN** | | | | | |
| Ver tablero | ✅ | ✅ | ✅ | ❌ | ❌ |
| Filtrar por sucursal | ✅ | ✅ | ✅ | ❌ | ❌ |
| Filtrar por producto | ✅ | ✅ | ✅ | ✅ | ✅ |
| **STOCK MÍNIMO** | | | | | |
| Configurar | ✅ | ✅ | ❌ | ✅ | ❌ |
| Ver alertas | ✅ | ✅ | ✅ | ✅ | ✅ |
| **ROLES** | | | | | |
| Ver roles | ✅ | ❌ | ❌ | ❌ | ❌ |
| Configurar permisos | ✅ | ❌ | ❌ | ❌ | ❌ |

### 3.2 Interfaz de Supervisor Sucursal: Ejemplo "Pedidos"

**Pantalla: MIS PEDIDOS**

```
┌─────────────────────────────────────────────────────────────────┐
│ PEDIDOS - Sucursal: Rosario (ID 3)                    [+ NUEVO] │
├─────────────────────────────────────────────────────────────────┤
│ Filtros: [Estado ▼] [Fecha ▼] [Buscar]                          │
├─────────────────────────────────────────────────────────────────┤
│ ID  │ Fecha   │ Productos   │ Total │ Recibido │ Estado         │
├─────┼─────────┼─────────────┼───────┼──────────┼────────────────┤
│ 150 │ 06/12   │ Frutilla... │ 2 ref │ 80/50    │ RECIBIDO_PARC. │
│ 151 │ 05/12   │ Chocolate...(3) │ 3 ref │ 100% │ RECIBIDO       │
│ 152 │ 03/12   │ Vainilla... │ 1 ref │ 50/100  │ PENDIENTE      │
└─────────────────────────────────────────────────────────────────┘

>> Click en Pedido 150:
┌─────────────────────────────────────────────────────────────────┐
│ DETALLES PEDIDO #150                                            │
├─────────────────────────────────────────────────────────────────┤
│ Solicitado: 06/12/2025 por Sofia Martinez                       │
│ Estado General: RECIBIDO PARCIAL (80/50 unidades)               │
│                                                                 │
│ PRODUCTOS SOLICITADOS:                                          │
│  ✓ Frutilla         50 unidades  │ 50 recibidas     │ 100%      │
│  ⏳ Chocolate        30 unidades  │ 30 recibidas     │ 100%      │
│  ⏳ Fresa             50 unidades  │ 0 recibidas      │ 0%        │
│                                                                 │
│ ENVÍOS ASOCIADOS:                                               │
│  📦 Envío #205 (06/12 09:30)                                    │
│      - Frutilla: 50 ✓ (RECIBIDO)                                │
│      - Chocolate: 30 ✓ (RECIBIDO)                               │
│                                                                 │
│  📦 Envío #206 (pendiente)                                      │
│      - Fresa: 50 ⏳ (ENVIADO - en tránsito)                     │
│                                                                 │
│ [CONFIRMAR RECEPCIÓN ENVIO #205]  [ANULAR PEDIDO]              │
└─────────────────────────────────────────────────────────────────┘
```

### 3.3 Interfaz de Supervisor Planta: "Producción" con Asistente

**Pantalla: TABLERO PRODUCCIÓN**

```
┌──────────────────────────────────────────────────────────────────┐
│ PRODUCCIÓN - Planta | [Filtro Sucursales ▼] [Filtro Tipo ▼]    │
├──────────────────────────────────────────────────────────────────┤
│                                                                  │
│ 🔴 URGENTE (Bajo Stock)                                          │
│  • Frutilla (todas sucursales): 150 pedidas | 60 en stock       │
│    └─ Sucursal 3 (Rosario): 50 pedidas | 20 en stock ⚠️        │
│    └─ Sucursal 5 (Córdoba): 100 pedidas | 40 en stock ⚠️       │
│                                                                  │
│ 🟡 DISPONIBLE (Stock suficiente)                                 │
│  • Chocolate (todas sucursales): 80 pedidas | 200 en stock ✓    │
│                                                                  │
│ 🟢 GENERADO (Ya enviado)                                         │
│  • Vainilla (todas sucursales): 60 solicitadas | 60 enviadas ✓  │
│                                                                  │
│ [Mostrar Detalles] [Generar Remitos] [Exportar]                 │
└──────────────────────────────────────────────────────────────────┘
```

**Datos subyacentes (desde Vista `v_asistente_pedidos`):**

```sql
SELECT
    prod.id,
    prod.nombre,
    SUM(mi.cnt) as total_solicitado,
    -- Stock actual en central
    (SELECT COALESCE(SUM(cnt), 0) 
     FROM movimientos_items 
     WHERE id_productos = prod.id 
     AND id_movimientos IN (
        SELECT id FROM movimientos 
        WHERE tipo_movimiento = 'ALTA_DEPOSITO' 
        AND estado = 'CERRADO'
     )
    ) as stock_central,
    COUNT(DISTINCT m.id_ubicacion_sucursal) as sucursales_afectadas
    
FROM movimientos m
INNER JOIN movimientos_items mi ON m.id = mi.id_movimientos
INNER JOIN productos prod ON mi.id_productos = prod.id
WHERE m.tipo_movimiento = 'PEDIDO'
AND m.estado = 'ABIERTO'  -- Solo pedidos sin completar
GROUP BY prod.id, prod.nombre
ORDER BY (total_solicitado - stock_central) DESC;  -- Faltante primero
```

---

## 🎛️ ANÁLISIS 4: MULTI-SUCURSAL Y FRANQUICIA

### 4.1 Regla de Negocio: Franquicia vs Local Propio

**Apunte:** "En sucursales, agregar propiedad que determine si es franquicia. En productos, agregar propiedad que determine si se muestra a franquicias."

**Estructura:**

```sql
-- Modificar tabla ubicaciones
ALTER TABLE ubicaciones ADD (
    tipo_ubicacion ENUM('DEPOSITO_CENTRAL', 'SUCURSAL_PROPIA', 'FRANQUICIA') 
        DEFAULT 'SUCURSAL_PROPIA',
    es_franquicia BOOLEAN DEFAULT FALSE COMMENT 'Legacy, usar tipo_ubicacion'
);

-- Modificar tabla productos
ALTER TABLE productos ADD (
    disponible_franquicias BOOLEAN DEFAULT TRUE,
    COMMENT 'Si FALSE, solo sucursales propias pueden pedirlo'
);
```

### 4.2 Validación: Al crear un Pedido

```sql
-- Trigger: Validar que sucursal puede pedir estos productos
DELIMITER //
CREATE TRIGGER validar_pedido_franquicia
BEFORE INSERT ON movimientos_items
FOR EACH ROW
BEGIN
    DECLARE es_franquicia BOOLEAN;
    DECLARE producto_permitido BOOLEAN;
    
    -- Obtener si sucursal es franquicia
    SELECT tipo_ubicacion = 'FRANQUICIA' 
    INTO es_franquicia
    FROM movimientos m
    INNER JOIN ubicaciones u ON m.id_ubicacion_sucursal = u.id
    WHERE m.id = NEW.id_movimientos;
    
    -- Si es franquicia, verificar producto
    IF es_franquicia THEN
        SELECT disponible_franquicias 
        INTO producto_permitido
        FROM productos
        WHERE id = NEW.id_productos;
        
        IF NOT producto_permitido THEN
            SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'Franquicia no puede pedir este producto';
        END IF;
    END IF;
END //
DELIMITER ;
```

### 4.3 UI: Filtrado de Productos por Tipo de Sucursal

```javascript
// En el crear pedido (frontend)
async function cargarProductosDisponibles(idSucursal) {
    // Obtener tipo de sucursal
    const { tipo } = await fetch(`/api/sucursales/${idSucursal}`).then(r => r.json());
    
    const esF franquicia = (tipo === 'FRANQUICIA');
    
    // Cargar productos disponibles
    const productos = await fetch('/api/productos')
        .then(r => r.json())
        .then(prods => 
            esF franquicia 
                ? prods.filter(p => p.disponible_franquicias === true)
                : prods
        );
    
    // Mostrar solo disponibles
    mostrarProductos(productos);
}
```

---

## 🔐 ANÁLISIS 5: FILTRADO POR SUCURSAL EN JWT CONTEXT

### 5.1 JWT Token con Contexto Multi-Sucursal

```json
{
    "sub": "user_123",
    "nombre": "Sofia Martinez",
    "email": "sofia@mikelo.com",
    "rol": "SUPERVISOR_SUCURSAL",
    
    "sucursales": [3, 5],  // IDs de sucursales permitidas
    "sucursal_activa": 3,  // La actual (puede cambiar)
    
    "permisos": {
        "crear_pedido": true,
        "confirmar_recepcion": true,
        "dar_de_baja": true
    },
    
    "iat": 1702207200,
    "exp": 1702293600
}
```

### 5.2 Middleware: Validar Contexto Sucursal

```php
// En api/src/Middleware/SucursalContextMiddleware.php
namespace Mikelo\Middleware;

class SucursalContextMiddleware {
    public function __invoke($request, $handler) {
        $token = $request->getAttribute('token');  // JWT decodificado
        
        // Obtener sucursal activa del request
        $sucursalActiva = $request->getQueryParams()['sucursal'] ?? $token['sucursal_activa'];
        
        // Validar que usuario tiene acceso a esta sucursal
        if (!in_array($sucursalActiva, $token['sucursales'])) {
            return $this->errorResponse('Acceso denegado a esta sucursal', 403);
        }
        
        // Inyectar en request
        $request = $request->withAttribute('sucursal_activa', $sucursalActiva);
        $request = $request->withAttribute('sucursales_permitidas', $token['sucursales']);
        
        return $handler($request);
    }
}
```

### 5.3 Queries Automáticamente Filtradas

```php
// En ModelPedidos, GET /mis-pedidos
public function obtenerMisPedidos($request) {
    $sucursal = $request->getAttribute('sucursal_activa');  // Validado por middleware
    
    // Query automáticamente filtrada
    $pedidos = $this->db->query(
        "SELECT * FROM movimientos 
         WHERE tipo_movimiento = 'PEDIDO'
         AND id_ubicacion_sucursal = ?",
        [$sucursal]
    );
    
    return $pedidos;
}

// Incluso si usuario intenta manipular:
// GET /mis-pedidos?sucursal=999
// El middleware lo rechaza ANTES de llegar a la query
```

---

## 📋 ANÁLISIS 6: REMITO DE PEDIDO

### 6.1 Contenido del Remito (Documento)

Un remito de pedido debe incluir:

```
┌─────────────────────────────────────────────────────────┐
│                   REMITO DE PEDIDO                      │
├─────────────────────────────────────────────────────────┤
│ Nro Pedido: 150                                         │
│ Fecha: 06/12/2025                                       │
│ Solicitante: Sofia Martinez (Supervisor Sucursal 3)    │
│                                                         │
│ DESTINO:                                                │
│  Sucursal: Rosario (ID 3)                               │
│  Dirección: Calle X 123                                 │
│                                                         │
│ ARTÍCULOS:                                              │
│ ┌──────────────────────────────────────────────────┐   │
│ │ Código │ Producto    │ Cantidad │ Precio │ Total │   │
│ ├──────────────────────────────────────────────────┤   │
│ │ 001    │ Frutilla    │ 50       │ $5.00  │ $250 │   │
│ │ 002    │ Chocolate   │ 30       │ $7.00  │ $210 │   │
│ │ 003    │ Fresa       │ 50       │ $6.00  │ $300 │   │
│ ├──────────────────────────────────────────────────┤   │
│ │                 TOTAL:                    $760  │   │
│ └──────────────────────────────────────────────────┘   │
│                                                         │
│ OBSERVACIONES:                                          │
│ Urgente - Stock en Rosario bajo                         │
│                                                         │
│ Autorizó: Sofia Martinez        Fecha: 06/12/2025      │
└─────────────────────────────────────────────────────────┘
```

### 6.2 Generación de Remito (API)

```php
// GET /api/pedidos/{id}/remito
public function obtenerRemito($id) {
    $pedido = $this->db->queryOne(
        "SELECT m.*, u.nombre as sucursal 
         FROM movimientos m
         LEFT JOIN ubicaciones u ON m.id_ubicacion_sucursal = u.id
         WHERE m.id = ? AND tipo_movimiento = 'PEDIDO'",
        [$id]
    );
    
    $items = $this->db->query(
        "SELECT mi.*, p.nombre, p.precio
         FROM movimientos_items mi
         INNER JOIN productos p ON mi.id_productos = p.id
         WHERE mi.id_movimientos = ?",
        [$id]
    );
    
    // Generar PDF/HTML
    return $this->generarPDF([
        'pedido' => $pedido,
        'items' => $items,
        'total' => array_sum(array_column($items, 'total'))
    ]);
}
```

---

## 🚨 ANÁLISIS 7: CASOS EDGE Y VALIDACIÓN

### 7.1 ¿Qué pasa si se anula un pedido parcialmente recibido?

**Escenario:**
- Pedido #100: 50 Frutilla
- Envío #200: 30 Frutilla (RECIBIDO)
- Envío #201: 20 Frutilla (en tránsito)
- Usuario intenta ANULAR el pedido

**Validación:**
```sql
-- Verificar: ¿hay items recibidos?
SELECT COUNT(*) as items_recibidos
FROM movimientos_items mi
INNER JOIN estados_items_movimientos eim ON mi.id = eim.id_movimientos_items
WHERE mi.id_movimientos = 100
AND eim.id_estados = 4;  -- RECIBIDO

-- Si > 0: NO PERMITIR ANULACIÓN directo
-- Opciones:
-- 1. Devolución explícita
-- 2. Anulación solo de items pendientes
-- 3. Requerir Supervisor Planta para anular lo recibido
```

### 7.2 ¿Qué pasa con un envío que se perdió?

**Escenario:**
- Envío #205: 30 unidades enviadas
- Sucursal dice: "no llegó"

**Flujo:**
```
1. Sucursal abre RECLAMACIÓN
   - UI: Modal "¿Envío no recibido?" 
   
2. Sistema crea evento en auditoría
   - estado_items_movimientos: registro especial
   
3. Supervisor Planta interviene
   - Opción A: Re-enviar (nuevo envío)
   - Opción B: Revertar (descontar del pedido)

4. Auditoría completa del incidente
```

### 7.3 ¿Qué pasa si se genera un envío sin pedido?

**Escenario (Fase 1 Legacy):**
- Hoy podemos enviar sin que haya un "pedido"
- Ejemplo: Reemplazo urgente

**Solución:**
```
- Permitir envíos SIN pedido asociado
- En tabla pedido_envio: id_pedido puede ser NULL para legacy
- O crear "PEDIDO IMPLÍCITO" automáticamente
```

---

## 📚 RESUMEN DE DECISIONES ARQUITECTÓNICAS

| Decisión | Opción Elegida | Razón |
|---|---|---|
| Pedidos = ¿tabla nueva o movimientos? | **Movimientos tipo PEDIDO** | Una source of truth |
| Relación Pedido-Envío | **Tabla `pedido_envio` N:N** | Flexible, auditable |
| Estado del Pedido | **Computed (vista)** | No redundante |
| Filtrado por sucursal | **JWT + Middleware** | Seguridad integrada |
| Envíos sin pedido | **Permitidos (legacy)** | Sin romper Fase 1 |
| Franquicia + Productos | **Triggers + Validación** | Integridad garantizada |

---

## 🎯 PRÓXIMOS PASOS (Para Diálogo)

1. **¿Validar que esta arquitectura responde tus preocupaciones?**
   - Almacenamiento de pedidos: ✅ Movimientos tipo PEDIDO
   - Relación con envíos: ✅ Tabla N:N explicit
   - Presentación sin cambios: ✅ Por roles y contexto

2. **¿Hay edge cases que no consideré?**
   - ¿Devolucion de productos?
   - ¿Pedidos con múltiples destinos?
   - ¿Integración con proveedores?

3. **¿Modificar algo de la estrategia de roles?**
   - ¿Agregamos "Auditor" para ver todo?
   - ¿Limitar Supervisor Sucursal a ver solo SU sucursal o múltiples?

4. **¿Plan de migración para Fase 1 → Fase 2?**
   - ¿Ignorar envíos actuales o "retroactivos" como ALTA_DEPOSITO?

---

**FIN DEL ANÁLISIS**  
*Documento abierto para feedback y profundización*
