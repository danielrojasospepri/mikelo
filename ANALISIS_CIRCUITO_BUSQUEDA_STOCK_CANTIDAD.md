# 📋 ANÁLISIS: BÚSQUEDA INTELIGENTE DE STOCK CON CANTIDAD EXACTA

## 🎯 PROBLEMA IDENTIFICADO

**Escenario actual:**
```
Alta en stock (manual):
  └─ Pan Salvado: cantidad 10 → 1 id_movimiento_item

Etiqueta impresa:
  └─ Código: 2000405000001 (20=cantidad, 00405=producto, 00001=cant 1)

Al escanear 10 veces:
  ├─ Escaneo 1 → Busca cantidad 1 → Encuentra id_movimiento_item (con 10 disponible)
  ├─ Escaneo 2 → Busca cantidad 1 → Encuentra MISMO id_movimiento_item (ahora 9 disponible)
  ├─ Escaneo 3 → Busca cantidad 1 → Encuentra MISMO id_movimiento_item (ahora 8 disponible)
  └─ ... repetir ...

Validación actual:
  └─ Filtra por id_movimiento_item → SÍ, ya está en envío
  └─ Suma cantidades: 1+1+1+... hasta 10 ✅
```

**El problema real:**
La búsqueda en `obtenerProductosDisponibles()` (backend) retorna 1 sola línea con:
- `id_movimiento_item`: El ÚNICO item en stock
- `cnt`: 10 (cantidad original)
- `cnt_disponible`: 10 (menos las que ya se enviaron)

Entonces la validación funciona, pero podría optimizarse.

---

## 💡 PROPUESTA DE MEJORA

### Cambio 1: Búsqueda Exacta por Cantidad (Frontend)

**Ubicación:** `buscarProductosDisponibles()` línea 220-240

**Situación actual:**
```javascript
if (tipoProducto === '20') {
    // Tipo 20: Unidades
    params.append('codigo', codigoProducto);
    params.append('cantidad', valorCantidadPeso);  // ← Envía cantidad leída
}
```

**Problema:** El backend probablemente ignore este parámetro `cantidad` y retorne todos los items disponibles del producto.

**Propuesta:** Documentar y usar parámetro `cantidadExacta` para priorizar búsqueda:
```javascript
if (tipoProducto === '20') {
    // Tipo 20: Unidades - Buscar cantidad EXACTA
    params.append('codigo', codigoProducto);
    params.append('cantidad', valorCantidadPeso);           // Cantidad a enviar
    params.append('cantidadExacta', valorCantidadPeso);     // NUEVO: Priorizar búsqueda
}
```

---

### Cambio 2: Algoritmo de Búsqueda Inteligente (Backend)

**Ubicación:** `api/src/Controller/EnvioController.php` → `obtenerProductosDisponibles()`

**Lógica propuesta:**

**PASO 1: Búsqueda exacta**
```sql
-- Buscar items CON cantidad EXACTA disponible
SELECT * FROM movimientos_items
WHERE id_productos = ?
  AND cnt_disponible = cantidad_buscada
ORDER BY fecha_alta ASC
LIMIT 1
```

**PASO 2: Si no hay exacta, buscar cantidad superior**
```sql
-- Si no hay cantidad exacta, buscar item CON cantidad SUPERIOR
SELECT * FROM movimientos_items
WHERE id_productos = ?
  AND cnt_disponible > cantidad_buscada
  AND cnt_disponible >= cantidad_buscada  -- Puede satisfacer parcialmente
ORDER BY cnt_disponible ASC, fecha_alta ASC  -- Más antiguo con cantidad mínima
LIMIT 1
```

---

### Cambio 3: Documentar Formato de Código de Barras

**Archivo a crear:** `docs/CODIGO_BARRAS_ENVIOS.md`

```
# Formato Código de Barras para Envíos

## Tipo 20: Cantidades (Unidades)
Formato: 20 + 5 dígitos código + 5 dígitos cantidad
Ejemplo: 2000405000001
         └─ Tipo 20
            └─ Código 00405
               └─ Cantidad 00001 (1 unidad)

Interpretación: "Enviar 1 Pan Salvado (código 405)"

Búsqueda en stock:
  1. Intenta encontrar id_movimiento_item CON cantidad 1
  2. Si no existe, busca id_movimiento_item CON cantidad > 1
  3. Utiliza ese item como origen, descontando 1

## Tipo 21: Pesos (kg)
Formato: 21 + 5 dígitos código + 5 dígitos peso (en gramos)
Ejemplo: 2100123003500
         └─ Tipo 21
            └─ Código 00123
               └─ Peso 03500 (3.5 kg)

Interpretación: "Enviar 1 bandeja Helado (código 123) con peso 3.5 kg"

Búsqueda en stock:
  1. Busca id_movimiento_item CON peso = 3.5 kg
  2. Si no existe, busca id_movimiento_item CON peso > 3.5 kg
  3. Utiliza ese item como origen
```

---

## 🔄 FLUJO COMPLETO PROPUESTO

### Caso 1: Pan Salvado (tipo 20 - cantidad)

**Stock inicial:**
```
id_movimiento_item: 123
id_productos: 405
cnt: 10
cnt_disponible: 10
```

**Escaneo 1: Código 2000405000001**
```
Frontend:
  ├─ Tipo: 20
  ├─ Código: 405
  ├─ Cantidad: 1
  └─ Envía: {codigo: 405, cantidad: 1, cantidadExacta: 1}

Backend búsqueda:
  ├─ Paso 1: ¿Existe cantidad EXACTA 1?
  │  └─ NO (tiene 10)
  ├─ Paso 2: ¿Existe cantidad SUPERIOR a 1?
  │  └─ SÍ (id_movimiento_item: 123, cnt_disponible: 10)
  └─ Retorna: {id_movimiento_item: 123, cnt_disponible: 10, ...}

Frontend agregar:
  ├─ Ya en envío (id 123): 0
  ├─ Total con nuevo: 1
  ├─ ¿1 ≤ 10? SÍ ✅
  └─ Agrega línea: cantidad 1, id_movimiento_item: 123
```

**Escaneo 2: Código 2000405000001 (mismo)**
```
Frontend:
  └─ Mismo parámetro: {codigo: 405, cantidad: 1}

Backend búsqueda:
  └─ Retorna: MISMO {id_movimiento_item: 123, cnt_disponible: 10}
    (porque aún tiene 10 disponible)

Frontend agregar:
  ├─ Ya en envío (id 123): 1
  ├─ Total con nuevo: 2
  ├─ ¿2 ≤ 10? SÍ ✅
  └─ Agrega línea: cantidad 1, id_movimiento_item: 123
```

**Resultado (10 escaneos):**
```
Tabla envío:
┌─────────────────────────────────────────────┐
│ Descripción  │ Cantidad │ id_movimiento_item │
├─────────────────────────────────────────────┤
│ Pan Salvado  │    1     │        123         │
│ Pan Salvado  │    1     │        123         │
│ Pan Salvado  │    1     │        123         │
│ ...          │    ...   │        ...         │
│ Pan Salvado  │    1     │        123         │
├─────────────────────────────────────────────┤
│ Total: 10 líneas, mismo id_movimiento_item  │
└─────────────────────────────────────────────┘

Stock después (al guardar):
  └─ id_movimiento_item: 123
  └─ cnt_disponible: 0 (10 - 10 enviados)
```

---

### Caso 2: Envío parcial luego otro

**Stock inicial:**
```
id_movimiento_item: 123
cnt: 10
cnt_disponible: 10
```

**Primer envío: Se envían 5 Pan Salvado**
```
Después:
  └─ id_movimiento_item: 456 (NUEVO - referencia al 123)
  └─ cnt: 5 (enviados)
  
  └─ id_movimiento_item: 123
  └─ cnt_disponible: 5 (10 - 5 que se referenciaron)
```

**Segundo envío: Se escanea Pan Salvado nuevamente**
```
Búsqueda backend:
  ├─ Paso 1: ¿Cantidad EXACTA 1? NO
  ├─ Paso 2: ¿Cantidad SUPERIOR? SÍ (id 123, disponible 5)
  └─ Retorna: id_movimiento_item: 123

Frontend:
  └─ Valida: ¿1 ≤ 5? SÍ ✅
  └─ Agrega con id_movimiento_item: 123
```

---

## 📊 CAMBIOS PROPUESTOS

| Componente | Cambio | Razón |
|------------|--------|-------|
| Frontend (buscarProductosDisponibles) | Agregar parámetro `cantidadExacta` | Comunicar al backend qué buscar primero |
| Backend (obtenerProductosDisponibles) | Implementar búsqueda en 2 pasos | Priorizar cantidad exacta, luego superior |
| Documentación | Crear `docs/CODIGO_BARRAS_ENVIOS.md` | Explicar formato y lógica |
| Validación Frontend | Ya implementada ✅ | Funciona correctamente |

---

## ❓ PREGUNTAS ANTES DE IMPLEMENTAR

1. **¿El backend ya soporta parámetro `cantidad` o necesita cambios?**
   - ¿Lo ignora actualmente?
   - ¿O ya lo usa de alguna forma?

2. **¿El algoritmo de 2 pasos (exacta → superior) es correcto?**
   - ¿Hay orden preferente entre items? (¿fecha_alta o cnt_disponible?)

3. **¿El id_movimiento_item se calcula bien para items fraccionados?**
   - ¿Referencia correctamente al item origen?

4. **¿Está documentado en algún lado el diseño de código de barras?**
   - ¿O creo nuevo documento?

---

## 🎯 RESUMEN

**Estado actual:** ✅ Funciona (valida stock correctamente)

**Mejora propuesta:** 🔄 Optimizar búsqueda para priorizar cantidad exacta

**Impacto:** Mejor rendimiento y más inteligente al elegir qué item referenciar

**Riesgo:** Bajo (no cambia lógica de validación, solo de selección)

¿Vamos con estos cambios?

