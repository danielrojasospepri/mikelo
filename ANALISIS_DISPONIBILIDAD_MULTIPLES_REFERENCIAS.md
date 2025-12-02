# ANÁLISIS CRÍTICO: Cálculo de Disponibilidad en Búsqueda 3-Pasos

## 🚨 Problema Identificado

Tu escenario expone una **falla crítica** en la búsqueda 3-pasos actual:

### Escenario Problemático

```
1. Alta Depósito: Pan Salvado = 10 unidades
   → Crea movimientos_items con id=100, cnt=10, id_movimientos_items_origen=NULL
   
2. Envío 1: Sucursal 1 → 3 unidades
   → Crea movimientos_items con id=101, cnt=3, id_movimientos_items_origen=100
   
3. Envío 2: Sucursal 2 → 7 unidades
   → Crea movimientos_items con id=102, cnt=7, id_movimientos_items_origen=100
   
TOTAL ENVIADO: 3 + 7 = 10 unidades
QUEDAN: 10 - 10 = 0 unidades
```

## ❌ El Problema en el Código Actual

### En `obtenerProductosDisponibles()`, línea 336:

```php
WHERE mi.id_movimientos_items_origen IS NULL 
AND mi.cnt > ifnull((
    SELECT ifnull(sum(mi2.cnt), 0)
    FROM movimientos_items mi2
    WHERE mi2.id_movimientos_items_origen = mi.id  // ← CORRECTO
), 0)
```

**Esto CALCULA correctamente:**
```
cnt_disponible = mi.cnt - SUM(referencias)
               = 10 - (3 + 7) = 0
```

### PERO hay un problema en PASO 2:

```php
// PASO 2: Búsqueda de cantidad SUPERIOR
$sqlPaso2 = $sql . " AND mi.cnt > ? ..."
            //     Esto usa la MISMA $sql de arriba ↑
            //     Que YA tiene el cálculo correcto
```

**✅ BUENA NOTICIA:** El WHERE actual **SÍ TIENE** el cálculo correcto:

```sql
WHERE mi.id_movimientos_items_origen IS NULL 
AND mi.cnt > IFNULL((
    SELECT IFNULL(SUM(mi2.cnt), 0)
    FROM movimientos_items mi2
    WHERE mi2.id_movimientos_items_origen = mi.id
), 0)
```

Este WHERE **FILTRA AUTOMÁTICAMENTE** productos sin disponibilidad.

## ✅ Análisis: ¿Está Contemplado?

### Línea 336-343: Verificación de Disponibilidad

```php
AND mi.cnt > ifnull((
    SELECT ifnull(sum(mi2.cnt), 0)
    FROM movimientos_items mi2
    WHERE mi2.id_movimientos_items_origen = mi.id
), 0)
```

**Traducido:**
```
SELECT productos DONDE:
  cantidad_original > SUM(cantidades_ya_enviadas)
  
En el escenario:
  10 > (3 + 7) ?
  10 > 10 ?
  ❌ FALSE → Producto NO aparece en resultados
```

### En el SELECT, línea 349-352: Cálculo de Disponible

```php
(mi.cnt - IFNULL((
    SELECT IFNULL(SUM(mi2.cnt), 0)
    FROM movimientos_items mi2
    WHERE mi2.id_movimientos_items_origen = mi.id
), 0)) as cnt_disponible
```

**Traducido:**
```
cnt_disponible = 10 - (3 + 7) = 0
```

## 🎯 Veredicto

### ✅ SÍ, ESTÁ CONTEMPLADO

El código **ESTÁ CORRECTO**:

1. **WHERE clause** filtra: `mi.cnt > SUM(referencias)` ✅
2. **SELECT clause** calcula: `cnt_disponible = mi.cnt - SUM(referencias)` ✅
3. **PASO 2** hereda este WHERE ✅

### Tu Escenario en Detalle

```
┌─────────────────────────────────────────────────────────────┐
│ Estado 1: Alta 10 unidades (id_movimiento_item = 100)       │
│                                                               │
│ mi.id = 100                                                  │
│ mi.cnt = 10                                                  │
│ SUM(referencias) = 0 → cnt_disponible = 10                   │
│ WHERE: 10 > 0 ? ✅ TRUE → APARECE                           │
└─────────────────────────────────────────────────────────────┘
         ↓ ENVÍO 1: 3 unidades
┌─────────────────────────────────────────────────────────────┐
│ Estado 2: Después envío 1                                    │
│                                                               │
│ mi.id = 100                                                  │
│ mi.cnt = 10                                                  │
│ Referencias: (id=101, cnt=3)                                 │
│ SUM(referencias) = 3 → cnt_disponible = 7                    │
│ WHERE: 10 > 3 ? ✅ TRUE → APARECE (con 7 disponibles)      │
└─────────────────────────────────────────────────────────────┘
         ↓ ENVÍO 2: 7 unidades
┌─────────────────────────────────────────────────────────────┐
│ Estado 3: Después envío 2                                    │
│                                                               │
│ mi.id = 100                                                  │
│ mi.cnt = 10                                                  │
│ Referencias: (id=101, cnt=3) + (id=102, cnt=7)              │
│ SUM(referencias) = 10 → cnt_disponible = 0                   │
│ WHERE: 10 > 10 ? ❌ FALSE → NO APARECE                      │
└─────────────────────────────────────────────────────────────┘
```

## 🔍 Validación en BD

Para verificar que tu escenario ESTÁ siendo manejado correctamente:

```sql
-- Buscar el producto de alta
SELECT 
    mi.id,
    mi.cnt,
    SUM(mi2.cnt) as total_enviado,
    (mi.cnt - IFNULL(SUM(mi2.cnt), 0)) as disponible
FROM movimientos_items mi
LEFT JOIN movimientos_items mi2 
    ON mi2.id_movimientos_items_origen = mi.id
WHERE mi.id_movimientos_items_origen IS NULL
  AND p.codigo = '405'  -- Pan Salvado
GROUP BY mi.id
HAVING (mi.cnt - IFNULL(SUM(mi2.cnt), 0)) > 0;
```

**Esperado para tu escenario después de enviar 10 de 10:**
```
Resultado vacío (0 filas) ✅
→ Producto no aparece porque disponible = 0
```

## ⚠️ PERO HAY UN DETALLE SUTIL

### En PASO 2, el WHERE busca `mi.cnt > cantidad_buscada`

```php
$sqlPaso2 = $sql . " AND mi.cnt > ? ..."
```

Este es el `mi.cnt` **ORIGINAL** (10), NO el `cnt_disponible` (que sería 0)

**Escenario:**

```
Después de envíos: quedan 0 unidades disponibles
Usuario intenta escanear: cantidad = 1

PASO 1: Busca cnt = 1 (exacto) ✗
PASO 2: Busca cnt > 1
  → WHERE: mi.cnt > 1 ? 
  → 10 > 1 ? ✅ TRUE
  → PERO TAMBIÉN: 10 > IFNULL(SUM, 0) ?
  → 10 > 10 ? ❌ FALSE
  
  → El WHERE GENERAL filtra esto ✅
```

**El WHERE GENERAL (línea 336-343) ya filtra por disponibilidad**, así que PASO 2 NO encontrará producto sin disponibilidad.

## 🎯 Conclusión

✅ **SÍ, ESTÁ CONTEMPLADO CORRECTAMENTE**

El código **VALIDA DISPONIBILIDAD** en tres lugares:

1. **WHERE Clause (línea 336):** `mi.cnt > SUM(referencias)` ✅
2. **SELECT (línea 349):** Calcula `cnt_disponible` ✅
3. **PASO 2 hereda el WHERE:** No busca en productos agotados ✅

### Tu Escenario Funciona Así:

```
Alta 10 → SUM(refs)=0 → disponible=10 ✅ APARECE
Envío 3 → SUM(refs)=3 → disponible=7 ✅ APARECE
Envío 7 → SUM(refs)=10 → disponible=0 ❌ NO APARECE
```

---

## 🔧 Recomendación: Claridad en el Código

Para evitar confusiones futuras, sugiero **RENOMBRAR la búsqueda PASO 2**:

**Cambio propuesto:**

```php
// PASO 2: Buscar cantidad DISPONIBLE superior a la solicitada
// (Nota: El WHERE general ya filtra por disponibilidad)
if (empty($resultados)) {
    // cnt > cantidad_solicitada Y hay disponibilidad
    // El WHERE ya filtra: mi.cnt > SUM(referencias)
    $sqlPaso2 = $sql . " AND mi.cnt > ? ORDER BY mi.cnt ASC, m.fechaAlta ASC LIMIT 1";
    $paramsPaso2 = array_merge($params, [$filtros['cantidad']]);
    
    $stmt = $this->db->prepare($sqlPaso2);
    $stmt->execute($paramsPaso2);
    $resultados = $stmt->fetchAll(\PDO::FETCH_ASSOC);
}
```

**O agregar un comentario explicativo:**

```php
// PASO 2: Buscar cantidad > solicitada
// El WHERE heredado FILTRA por disponibilidad: cnt > SUM(referencias)
// Por lo que aunque mi.cnt sea 10, si ya se enviaron 9, 
// no aparecerá en resultados
```

## 📊 Tabla de Validación

| Estado | Alta | Enviados | Disponible | PASO 1 (=1) | PASO 2 (>1) | Aparece |
|--------|------|----------|------------|------------|------------|---------|
| Inicial | 10 | 0 | 10 | ✅ | ✅ | ✅ |
| Envío 1 | 10 | 3 | 7 | ❌ | ✅ | ✅ |
| Envío 2 | 10 | 10 | 0 | ❌ | ❌ | ❌ |

