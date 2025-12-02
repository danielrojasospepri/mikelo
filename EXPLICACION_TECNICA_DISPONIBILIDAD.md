# EXPLICACIÓN TÉCNICA: Cómo se Calcula la Disponibilidad

## El Problema que Solucionaste

Tu pregunta fue: **"¿Cómo se calcula cuánto queda disponible cuando hay múltiples referencias?"**

## La Respuesta en el Código

### En `api/src/Model/Envio.php` línea 336-343

```php
WHERE mi.id_movimientos_items_origen IS NULL 
AND mi.cnt > ifnull((
    SELECT ifnull(sum(mi2.cnt), 0)
    FROM movimientos_items mi2
    WHERE mi2.id_movimientos_items_origen = mi.id
), 0)
```

### Desglose Línea por Línea

```php
// 1. Seleccionar SOLO registros originales (no referencias)
WHERE mi.id_movimientos_items_origen IS NULL 

// 2. Contar TODAS las referencias que apuntan a este registro
AND mi.cnt > ifnull((
    SELECT ifnull(sum(mi2.cnt), 0)
    FROM movimientos_items mi2
    WHERE mi2.id_movimientos_items_origen = mi.id  // ← Apuntan a este registro
), 0)

// 3. Validar que la cantidad original es MAYOR que las referencias
//    (Si no, no hay disponibilidad)
```

---

## Ejemplo Paso a Paso

### Estado Inicial

```
Tabla: movimientos_items

id   | id_movimientos_items_origen | id_productos | cnt
─────┼─────────────────────────────┼──────────────┼─────
100  | NULL (es original)          | 405          | 10    ← ALTA
101  | 100 (referencia a 100)      | 405          | 3     ← ENVÍO 1
102  | 100 (referencia a 100)      | 405          | 7     ← ENVÍO 2
```

### Evaluación en WHERE

```
Para el registro original (id=100):

1. ¿Es original? 
   id_movimientos_items_origen IS NULL ? 
   → NULL IS NULL ? ✅ YES

2. ¿Cuántas referencias tiene?
   SELECT SUM(cnt) FROM movimientos_items 
   WHERE id_movimientos_items_origen = 100
   → SUM(3 + 7) = 10

3. ¿Hay disponibilidad?
   mi.cnt > SUM(referencias) ?
   10 > 10 ? 
   → ❌ FALSE → NO PASA EL WHERE

RESULTADO: Este registro (100) NO aparece en búsqueda
```

---

## La Clave: La Subconsulta

### Subconsulta que Suma Referencias

```php
SELECT ifnull(sum(mi2.cnt), 0)
FROM movimientos_items mi2
WHERE mi2.id_movimientos_items_origen = mi.id
```

**¿Qué hace?**

Busca TODOS los registros que tienen `id_movimientos_items_origen = id_del_registro_actual`

**¿Por qué es importante?**

Porque permite saber exactamente cuánto fue enviado desde este original, sin importar cuántos envíos hubo.

**¿Qué si no hay referencias?**

La subconsulta retorna NULL/0, y el IFNULL lo convierte a 0.

---

## Tres Escenarios Validados

### Escenario 1: Sin Referencias

```
Original: id=100, cnt=10, referencias=0
Disponible: 10 - 0 = 10 ✅
WHERE: 10 > 0 ? ✅ APARECE
```

### Escenario 2: Parcialmente Enviado

```
Original: id=100, cnt=10, referencias=3+7=10
Disponible: 10 - 10 = 0 ✅
WHERE: 10 > 10 ? ❌ NO APARECE
```

### Escenario 3: Parcialmente Disponible

```
Original: id=100, cnt=10, referencias=3
Disponible: 10 - 3 = 7 ✅
WHERE: 10 > 3 ? ✅ APARECE (con 7 disponibles)
```

---

## Cómo Esto Se Conecta con la Búsqueda 3-Pasos

### El WHERE es Heredado

```php
// La búsqueda base tiene el WHERE correcto
$sql = "SELECT ... FROM movimientos_items mi ... 
        WHERE mi.id_movimientos_items_origen IS NULL 
        AND mi.cnt > ifnull((SELECT SUM...), 0)";

// PASO 1: Búsqueda exacta HEREDA el WHERE
$sqlPaso1 = $sql . " AND mi.cnt = ?";
// Resultado: WHERE ... AND ... AND mi.cnt = ?

// PASO 2: Búsqueda superior HEREDA el WHERE
$sqlPaso2 = $sql . " AND mi.cnt > ?";
// Resultado: WHERE ... AND ... AND mi.cnt > ?

// PASO 3: Búsqueda manual HEREDA el WHERE
$sqlPaso3 = $sql . " ORDER BY ...";
// Resultado: WHERE ... AND ...
```

**Todos incluyen la validación de disponibilidad.** ✅

---

## El Cálculo en el SELECT

### Además de Filtrar, También se Calcula

```php
(mi.cnt - IFNULL((
    SELECT IFNULL(SUM(mi2.cnt), 0)
    FROM movimientos_items mi2
    WHERE mi2.id_movimientos_items_origen = mi.id
), 0)) as cnt_disponible
```

**¿Por qué se calcula dos veces?**

1. **En WHERE:** Filtra productos sin disponibilidad
2. **En SELECT:** Devuelve el valor exacto al frontend

Así el frontend sabe cuánto está disponible para mostrar en la UI.

---

## Seguridad: Por Qué No Hay Riesgo

### El Sistema no Puede Permitir Sobreventa

```
Usuario quiere enviar: 15 unidades
Original disponible:   0 unidades

Proceso:
1. Búsqueda con cantidad=15
   → WHERE: 10 > 10 ? ❌ FALSE
   → Producto no aparece

2. Usuario intenta forzar con id=100 (no es posible desde UI)
3. Al crear envío, se valida:
   if (cantidad > disponible) throw new Exception(...)
   
RESULTADO: ❌ BLOQUEADO (no se crea el envío)
```

---

## Visualización Completa

```
┌────────────────────────────────────────────────────────┐
│ CONSULTA SQL: obtenerProductosDisponibles()            │
└────────────────────────────────────────────────────────┘

FROM movimientos_items mi
WHERE mi.id_movimientos_items_origen IS NULL     ← Solo originales
  AND mi.cnt > (                                  ← Hay disponibilidad
      SELECT SUM(mi2.cnt)
      FROM movimientos_items mi2
      WHERE mi2.id_movimientos_items_origen = mi.id
  )

SELECT 
    mi.cnt,
    (mi.cnt - SUM(referencias)) as cnt_disponible
FROM movimientos_items
...

PASO 1: Añade     AND mi.cnt = ?                  ← Exacto
PASO 2: Añade     AND mi.cnt > ?                  ← Superior
PASO 3: Añade     (nada, usa WHERE base)          ← Manual
```

---

## Conclusión

Tu pregunta fue crucial porque identificaste un punto crítico:

> "¿El sistema suma correctamente TODAS las referencias para calcular disponibilidad?"

La respuesta es: **✅ SÍ, Y LO HACE CORRECTAMENTE**

El código usa una subconsulta que automáticamente suma todas las referencias, sin importar cuántas haya, y valida que no se permita sobrevender.

