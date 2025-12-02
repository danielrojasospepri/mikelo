# Análisis: Propuesta de Reformulación con Tabla `stock` Dedicada

## 📋 Propuesta Resumida

Cambiar el modelo de inventario de:
- **Actual:** Cálculo dinámico de disponibilidad en `movimientos_items` (con referencias)
- **Propuesto:** Tabla `stock` como fuente única de verdad + `movimientos_items` como LOG

### Cambios Clave:
```
ALTA DE PRODUCTO (sin cambios):
  - Inserta en movimientos (origen=1, destino=1, tipo=NUEVO)
  - Inserta en movimientos_items (id_movimientos_items_origen = NULL)
  - Inserta en estados_items_movimientos (NUEVO)
  - [NUEVO] Actualiza/Inserta en tabla stock (ubicacion=1, cnt=+N)

ENVIOS (cambio fundamental):
  - Búsqueda ahora en tabla stock (no en movimientos_items)
  - Si tipo=20 (cantidad): busca stock > cantidad exacta, no referencia
  - Si tipo=21 (peso): sigue igual, referencia como hoy
  - Inserta en movimientos (origen=1, destino=X, tipo=ENVIADO)
  - Inserta en movimientos_items (sin id_movimientos_items_origen para tipo 20)
  - Inserta en movimientos_items (CON id_movimientos_items_origen para tipo 21)
  - [NUEVO] Actualiza tabla stock (cnt = cnt - cantidad_enviada)
```

---

## 🎯 Ventajas de la Propuesta

### 1. **Simplifica la búsqueda de disponibilidad**
```php
// ACTUAL (complejo)
SELECT mi.id, mi.cnt
FROM movimientos_items mi
WHERE mi.id_movimientos_items_origen IS NULL
  AND NOT EXISTS (SELECT 1 FROM movimientos_items mi2 
                  WHERE mi2.id_movimientos_items_origen = mi.id)

// PROPUESTO (simple)
SELECT id_productos, cnt, cnt_peso
FROM stock
WHERE id_ubicaciones = 1
```

### 2. **Claridad en intención**
- **movimientos_items** = LOG/HISTORIAL de qué se envió (trazabilidad)
- **stock** = REALIDAD actual (estado real de inventario)
- Actualmente stock = derivado de movimientos (difícil de entender)

### 3. **Evita recursión de referencias**
- Hoy: tipo 20 (cantidad) intenta referenciar, genera `id_movimientos_items_origen`
- Propuesta: Tipo 20 NO referencia, solo lee del stock y decrementa
- Tipo 21 (peso): SÍ referencia porque puede haber "fracciones parciales"

### 4. **Búsqueda de cantidad más fácil**
```php
// PROPUESTO
SELECT * FROM stock WHERE id_productos = ? AND cnt >= cantidad_buscada
ORDER BY cnt ASC  // Preferir usar menos primero
```
**Sin necesidad de fallback 3-pasos**, porque `stock` siempre tiene la realidad.

### 5. **Auditoría más clara**
- `movimientos_items` = "se enviaron X unidades/kg a destino Y"
- `stock` = "ahora hay X unidades disponibles"
- Diferencia clara entre movimiento y estado

---

## ⚠️ Desventajas / Complejidad

### 1. **Cambio RADICAL de arquitectura**
- Requiere modificar 3 módulos:
  - ✅ Alta de Depósito (agregar línea en stock)
  - ✅ Envíos (lógica completamente nueva)
  - ✅ Baja de Productos (si existe)

### 2. **Tabla `stock` está INCOMPLETA**
```sql
CREATE TABLE `stock` (
  `id` int(11) NOT NULL,                 -- ⚠️ PRIMARY KEY no definida
  `id_ubicaciones` int(11) NOT NULL,
  `id_productos` int(11) NOT NULL,
  `cnt` decimal(10,3) NOT NULL DEFAULT 0.000,
  `cnt_peso` decimal(10,3) NOT NULL DEFAULT 0.000
) -- ⚠️ Sin PRIMARY KEY, índices, foreign keys
```

**Necesita:**
- `PRIMARY KEY (id_ubicaciones, id_productos)` - COMPOSITE KEY
- Índices en `id_productos`, `id_ubicaciones`
- Restricciones: `cnt >= 0`, `cnt_peso >= 0`

### 3. **Migración de datos existentes**
```sql
-- Para alta depósito que ya existe:
INSERT INTO stock (id_ubicaciones, id_productos, cnt, cnt_peso)
SELECT 1, id_productos, SUM(cnt), SUM(cnt_peso)
FROM movimientos_items mi
WHERE mi.id_movimientos_items_origen IS NULL
  AND mi.id NOT IN (SELECT id_movimientos_items_origen FROM movimientos_items mi2)
GROUP BY id_productos
```

### 4. **Referencia tipo 21 se complica**
Hoy: Tipo 21 usa `id_movimientos_items_origen` para rastrear fracciones
Propuesta: ¿Cómo se registra que una fracción de 10kg fue enviada?

**Opción A:** Mantener referencia para tipo 21
```
stock: Pan Salvado = 10kg
Envío 1: Lee stock (10kg), crea movimientos_items CON id_movimientos_items_origen
stock: Pan Salvado = 9kg
```
→ Complica mezclar lógica (tipo 20 sin referencia, tipo 21 con referencia)

**Opción B:** Nunca referenciar, solo log directo
```
stock: Pan Salvado = 10kg
Envío 1: Lee stock (10kg), decrementa (9kg), crea movimientos_items SIN referencia
stock: Pan Salvado = 9kg
```
→ Pierdes trazabilidad de fracciones

### 5. **Sincronización stock ↔ movimientos_items**
- ¿Qué ocurre si se cancela un envío?
  - Revert stock?
  - Borrar movimientos_items?
  - Ambos?
- ¿Si hay error en base de datos?
  - stock y movimientos desincronizados = CATASTROFE

### 6. **CRUD diferente para cada tipo de movimiento**
```
ALTA: stock += cnt
ENVIO TIPO 20: stock -= cnt (sin referencia)
ENVIO TIPO 21: stock -= cnt_peso (¿con referencia?)
BAJA: stock -= cnt (qué pasa?)
RECEPCION: stock += cnt (pero de dónde viene?)
```
→ Lógica dispersa en múltiples controladores

---

## 🔄 Comparativa: ACTUAL vs PROPUESTO

| Aspecto | ACTUAL | PROPUESTO |
|---------|--------|-----------|
| **Fuente de verdad** | Derivada de movimientos | Tabla stock directa |
| **Búsqueda tipo 20** | Recursivo (referencias) | Simple (SELECT) |
| **Búsqueda tipo 21** | Recursivo (referencias) | ¿Referencia o log? |
| **Referencia en tipo 20** | SÍ (complica) | NO (simplifica) |
| **Referencia en tipo 21** | SÍ (necesaria) | DUDOSO |
| **Log de movimientos** | SÍ (movimientos_items) | SÍ (movimientos_items) |
| **Cancelación envío** | Borrar movimientos_items | Revert stock + borrar items |
| **Auditoría** | Difícil (derivada) | Más clara (directa) |
| **Complejidad código** | ALTA (recursión) | MEDIA (pero operacional) |
| **Sincronización** | Una tabla | DOS tablas = riesgo |

---

## 💡 Alternativa Intermedia (Recomendada)

**¿Qué tal una solución HIBRIDA más simple?**

### Idea: Mantener actual, mejorar búsqueda

```php
// En lugar de reformular TODO, solo cambiar búsqueda:

// PASO 1: Cantidad EXACTA
SELECT * FROM movimientos_items 
WHERE id_movimientos_items_origen IS NULL
  AND NOT EXISTS (SELECT 1 FROM movimientos_items mi2 
                  WHERE mi2.id_movimientos_items_origen = mi.id)
  AND cnt = ?
  AND id_ubicaciones = 1

// PASO 2: Si no hay, cantidad SUPERIOR
SELECT * FROM movimientos_items 
WHERE id_movimientos_items_origen IS NULL
  AND NOT EXISTS (...)
  AND cnt > ?
  AND id_ubicaciones = 1
ORDER BY cnt ASC, fecha_alta ASC
LIMIT 1

// PASO 3: Si tampoco, permitir búsqueda manual
```

**Ventajas:**
- ✅ Resuelve el problema (barcode scanning fallaba)
- ✅ Mínimos cambios (solo `Envio.php`)
- ✅ No requiere migración de datos
- ✅ Mantiene trazabilidad actual
- ✅ No introduce nuevas dependencias

**Desventajas:**
- ⚠️ Sigue siendo búsqueda compleja (derivada)
- ⚠️ No mejora arquitectura a largo plazo

---

## 🎯 Preguntas Clave Antes de Decidir

### 1. **¿Qué pasa con envíos parciales de productos pesables?**
   - Si tengo 10kg de helado y envío 3.5kg, ¿cómo se rastrea?
   - Propuesta: ¿Mantener referencia para tipo 21?

### 2. **¿Cómo manejas cancelaciones?**
   - Hoy: Borras movimientos_items, recuperas stock automático
   - Propuesta: ¿Actualizas stock manualmente?

### 3. **¿Qué de devoluciones o reposiciones?**
   - Si un envío vuelve, ¿stock += cantidad?
   - ¿O creas nuevo movimiento?

### 4. **¿Necesitas auditoría de dónde vino cada unidad?**
   - Hoy: Puedes rastrear movimientos_items_origen → origen
   - Propuesta: ¿Pierdes eso?

---

## 📊 Mi Recomendación

### **Corto Plazo (AHORA):** 
✅ Implementar búsqueda inteligente 3-pasos en `Envio.php`
- Soluciona problema inmediato (barcode scanning)
- Mínimo impacto
- Costo: ~1 hora de cambios

### **Mediano Plazo (próximas versiones):**
⚠️ Evaluar si tabla `stock` es necesaria
- Si crece complejidad de envíos
- Si necesitas consultas de disponibilidad rápidas
- Si quieres simplificar lógica de búsqueda

### **Largo Plazo (arquitectura):**
🔄 Refactorizar si es necesario
- Pero PRIMERO estabilizar funcionalidad actual
- DESPUÉS migrar datos
- Mantener histórico en movimientos_items

---

## 🔴 Riesgo Principal de Reformulación

**Dos Tablas Desincronizadas = PESADILLA:**

```
Escenario de horror:
1. Alta Producto: stock += 10, movimientos_items += registro
2. Envío 1: stock -= 5, movimientos_items += referencia
3. ❌ ERROR EN BD: Stock se actualiza pero movimientos falla
4. Resultado: stock = 5, pero movimientos_items muestra 15 (inconsistencia)
```

**Solución:** Usar transacciones atómicas
```php
BEGIN TRANSACTION
  UPDATE stock
  INSERT movimientos_items
  INSERT estados_items_movimientos
COMMIT (o ROLLBACK si algo falla)
```

---

## ✅ Conclusión

| Opción | Costo | Riesgo | Beneficio | Recomendación |
|--------|-------|--------|-----------|-------------|
| **Búsqueda 3-pasos** | 1-2 hrs | Bajo | Soluciona problema actual | ✅ AHORA |
| **Tabla `stock` nueva** | 6-8 hrs | ALTO | Simplifica, pero complica | ⚠️ DESPUÉS |
| **Refactorización total** | 2-3 días | CRÍTICO | Mejora arquitectura | 🔴 FASE 2 |

