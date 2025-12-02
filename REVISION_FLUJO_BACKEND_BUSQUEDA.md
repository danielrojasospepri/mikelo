# 🔍 REVISIÓN DEL FLUJO: BÚSQUEDA Y SELECCIÓN DE STOCK

## 📍 UBICACIÓN

**Archivo:** `api/src/Model/Envio.php`  
**Método:** `obtenerProductosDisponibles()` (línea 319-415)  
**Llamada desde:** `api/src/Controller/EnvioController.php` línea 69-79

---

## 🔬 ANÁLISIS DEL CÓDIGO ACTUAL

### Consulta SQL (línea 319-370)

```sql
SELECT 
    mi.id as id_movimiento_item,
    p.id as id_producto,
    p.codigo,
    p.descripcion,
    mi.cnt,                    -- ← Cantidad original
    mi.cnt_peso,               -- ← Peso total
    ... estado_actual ...
    (mi.cnt - IFNULL(SUM(mi2.cnt), 0)) as cnt_disponible  -- ← Cantidad restante
FROM movimientos_items mi
WHERE mi.id_movimientos_items_origen IS NULL   -- ← Solo items origen
  AND mi.cnt > (cantidad_referenciada)         -- ← Debe quedar algo
  AND estado_actual = NUEVO (1)                -- ← Solo estado NUEVO
```

---

### Filtros Aplicados (línea 372-393)

```php
// 1. FILTRO POR CÓDIGO
if (!empty($filtros['codigo'])) {
    $sql .= " AND p.codigo = ?";
}

// 2. FILTRO POR CANTIDAD EXACTA ← LO USA
if (!empty($filtros['cantidad'])) {
    $sql .= " AND mi.cnt = ?";        // ← BUSCA CANTIDAD EXACTA
    $params[] = $filtros['cantidad'];
}

// 3. FILTRO POR PESO EXACTO (tipo 21)
if (!empty($filtros['peso'])) {
    $sql .= " AND mi.cnt_peso = ?";   // ← BUSCA PESO EXACTO
}

// 4. FILTRO GENERAL (búsqueda manual)
if (!empty($filtros['filtro'])) {
    $sql .= " AND (p.codigo LIKE ? OR p.descripcion LIKE ?)";
}
```

**Estado:** ✅ SI usa el parámetro `cantidad`  
**Comportamiento:** Busca EXACTO: `mi.cnt = cantidad_leida`

---

### Ordenamiento (línea 397-404)

```php
if (!empty($filtros['filtro']) && (!empty($filtros['peso'])) || !empty($filtros['cantidad'])) {
    $sql .= " ORDER BY m.fechaAlta ASC LIMIT 1";     // ← Más ANTIGUO, solo 1
} else {
    $sql .= " ORDER BY m.fechaAlta DESC";             // ← Más NUEVO, todos
}
```

**Problema:** Lógica de paréntesis confusa

---

## 📊 FLUJO ACTUAL

### Escaneo tipo 20 (cantidad 1)

**Entrada:**
```javascript
{
    codigo: '405',
    cantidad: 1
}
```

**Backend busca:**
```sql
SELECT * WHERE codigo = 405 AND mi.cnt = 1 AND disponible > 0
ORDER BY fechaAlta ASC
LIMIT 1
```

**Resultados posibles:**

| Escenario | Stock | Resultado |
|-----------|-------|-----------|
| Existe cantidad 1 exacta | id:456, cnt:1 | ✅ Retorna |
| NO existe cantidad 1 | id:123, cnt:10 | ❌ Retorna NADA |
| Busca manual "Pan" | Todos | ✅ Retorna TODOS |

---

## 🎯 PROBLEMA REAL

**Búsqueda BINARIA (demasiado estricta):**
```
Si busca cantidad = 1:
  └─ SOLO retorna items con cnt exacto = 1
  └─ Si no existe = NO RETORNA NADA ❌
```

**Consecuencia:**
- ✅ Pan alta 1 + escaneo 1 → Funciona
- ❌ Pan alta 10 + escaneo 1 → **NO ENCUENTRA**

---

## 💡 PROPUESTA

Cambiar a **búsqueda en 3 pasos:**

```
PASO 1: Buscar cantidad EXACTA
        WHERE cnt = cantidad_buscada

PASO 2: Si no hay exacta, buscar SUPERIOR
        WHERE cnt > cantidad_buscada
        ORDER BY cnt ASC, fechaAlta ASC
        (más pequeño disponible, más antiguo)

PASO 3: Si es búsqueda manual, retornar TODOS
```

---

## ✅ RESULTADO ESPERADO

| Caso | Antes | Después |
|------|-------|---------|
| Alta 10, escaneo 1 | ❌ NO encuentra | ✅ Encuentra (10) |
| Alta 1, escaneo 1 | ✅ Encuentra | ✅ Encuentra (1) |
| Búsqueda manual | ✅ Encuentra todos | ✅ Encuentra todos |
| Múltiples items | Último | ✅ Más pequeño disp |

---

## 🔧 CAMBIO NECESARIO

**Archivo:** `api/src/Model/Envio.php` (línea 319-415)

**Reemplazar la lógica de filtros y ordenamiento:**

Del código actual (EXACTO BINARIO) al código propuesto (3 PASOS INTELIGENTE)

¿Vamos?

