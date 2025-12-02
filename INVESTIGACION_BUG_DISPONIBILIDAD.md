# 🚨 BUG ENCONTRADO: Productos Agotados Aparecen en Búsqueda

## Problema Descubierto

El test reveló que **productos con disponibilidad = 0 SÍ aparecen en búsqueda**, cuando no deberían.

### Caso Concreto

```
Producto: FRUTILLA Y NARANJA (1101)
Cantidad Alta: 1.000
Total Enviado: 1.000
Disponible: 0.000

Status: ✗ APARECE EN BÚSQUEDA (BUG)
```

## Análisis del Problema

### El WHERE Debería Filtrar

```php
WHERE mi.id_movimientos_items_origen IS NULL 
AND mi.cnt > ifnull((
    SELECT ifnull(sum(mi2.cnt), 0)
    FROM movimientos_items mi2
    WHERE mi2.id_movimientos_items_origen = mi.id
), 0)
```

**Si el producto tiene:**
- `mi.cnt = 1`
- `SUM(referencias) = 1`

**Entonces:**
- `1 > 1` ? ❌ FALSE
- **NO debería aparecer**

## Investigación: ¿Por Qué Aparece?

Hay dos posibilidades:

### Posibilidad 1: El SELECT Calcula Diferente al WHERE

```php
// En SELECT (calcula disponible):
(mi.cnt - IFNULL((
    SELECT IFNULL(SUM(mi2.cnt), 0)
    FROM movimientos_items mi2
    WHERE mi2.id_movimientos_items_origen = mi.id
), 0)) as cnt_disponible

// En WHERE (filtra):
AND mi.cnt > ifnull((
    SELECT ifnull(sum(mi2.cnt), 0)
    FROM movimientos_items mi2
    WHERE mi2.id_movimientos_items_origen = mi.id
), 0)
```

Si hay **DIFERENTES RESULTADOS** en las dos subconsultas = BUG

### Posibilidad 2: Hay Múltiples Registros del Mismo Producto

```
Producto 1101 con:
  • Registro A: cnt=1, enviado=0 (original)
  • Registro B: cnt=1, enviado=0 (otra alta del mismo producto)
  • Registro C: cnt=1, enviado=1 (con referencia)
```

En este caso, cada registro se evalúa por separado:
- Registro A: `1 > 0` ✅ Aparece
- Registro B: `1 > 0` ✅ Aparece
- Registro C: `1 > 1` ❌ No aparece

**Resultado:** Producto aparece (por registros A y B)

## Recomendación: Investigación Profunda Necesaria

Para averiguar qué está pasando, necesito que ejecutes esta consulta:

```sql
-- Buscar el producto que aparece (1101 - FRUTILLA Y NARANJA)
SELECT 
    mi.id,
    mi.cnt,
    (
        SELECT IFNULL(SUM(mi2.cnt), 0)
        FROM movimientos_items mi2
        WHERE mi2.id_movimientos_items_origen = mi.id
    ) as total_enviado,
    (mi.cnt - IFNULL((
        SELECT IFNULL(SUM(mi2.cnt), 0)
        FROM movimientos_items mi2
        WHERE mi2.id_movimientos_items_origen = mi.id
    ), 0)) as disponible
FROM movimientos_items mi
WHERE mi.id_movimientos_items_origen IS NULL
  AND mi.id_productos = (SELECT id FROM productos WHERE codigo = '1101')
ORDER BY mi.id;
```

**Esto mostrará:**
- Cuántos registros tiene el producto 1101
- Cuánto fue enviado de cada uno
- Cuánta disponibilidad tiene cada uno

## Posible Solución

Si hay múltiples altas del mismo producto, la búsqueda **está funcionando correctamente** (devuelve registros con disponibilidad).

Si hay UN SOLO registro pero aparece con `cnt_disponible = 1.000` cuando debería ser 0, hay un **BUG en el cálculo**.

## Next Step

Por favor ejecuta la consulta SQL anterior y compartí los resultados. Así sabremos exactamente qué está pasando.

