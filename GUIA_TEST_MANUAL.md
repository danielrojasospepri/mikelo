# GUÍA RÁPIDA: Test Manual de Búsqueda 3-Pasos

## ¿Qué Hicimos?

Modificamos `api/src/Model/Envio.php` para hacer búsqueda INTELIGENTE de productos:

1. **PASO 1:** Busca cantidad EXACTA
2. **PASO 2:** Si no hay exacta, busca cantidad SUPERIOR
3. **PASO 3:** Si tampoco hay, permite búsqueda manual

## ✅ Status Actual

```
✅ Implementación completada
✅ Tests automatizados: TODOS PASADOS (5/5)
✅ Sintaxis PHP: VALIDADA
⏳ Tests manuales: A TU CARGO
```

---

## 🎯 Cómo Hacer el Test Manual

### Opción A: Test en el Navegador (RECOMENDADO)

1. **Abre:** `http://localhost/mikelo/envios.html`

2. **Test 1: Barcode Tipo 20 (Cantidad Exacta)**
   - En "Buscar productos" → Ingresa código: `405` (Pan Salvado)
   - Cantidad: `1`
   - Haz clic: "Buscar"
   - **Esperado:** ✅ Encuentra el producto

3. **Test 2: Barcode Tipo 20 (Cantidad Superior - CRÍTICO)**
   - En "Buscar productos" → Código: `405`
   - Cantidad: `3`
   - Haz clic: "Buscar"
   - **Esperado:** ✅ Encuentra producto aunque no hay exacto = 3 (usa PASO 2 con cantidad > 3)

4. **Test 3: Múltiples Escaneos**
   - Agrega Pan Salvado 3 veces al mismo envío
   - **Esperado:** ✅ Permite todos (controla stock cada vez)

5. **Test 4: Búsqueda Manual**
   - Deja código y cantidad vacíos
   - Haz clic: "Buscar todos los productos"
   - **Esperado:** ✅ Muestra lista completa

### Opción B: Test con cURL (Terminal)

```bash
# Test PASO 1: Cantidad exacta
curl "http://localhost/test/api/envios/productos-disponibles?codigo=405&cantidad=1"

# Test PASO 2: Cantidad superior
curl "http://localhost/test/api/envios/productos-disponibles?codigo=405&cantidad=3"

# Test PASO 3: Manual
curl "http://localhost/test/api/envios/productos-disponibles"

# Test Peso
curl "http://localhost/test/api/envios/productos-disponibles?peso=6.355"
```

### Opción C: REST Client en VSCode

1. Instala extensión: "REST Client" (REST Client by Huachao Mao)
2. Abre: `api/test_busqueda_3pasos.http`
3. Haz clic en "Send Request" para cada test

---

## 🧪 Validar Resultados

### Caso 1: ¿Encuentra exacto?
```
GET /api/envios/productos-disponibles?codigo=405&cantidad=1
```
**Esperado:** 
```json
{
  "id_movimiento_item": 2574,
  "codigo": "405",
  "descripcion": "PAN SALVADO",
  "cnt": 1.000
}
```

### Caso 2: ¿Encuentra superior?
```
GET /api/envios/productos-disponibles?codigo=405&cantidad=3
```
**Esperado:** 
```json
{
  "id_movimiento_item": [...],
  "codigo": "405",
  "descripcion": "PAN SALVADO",
  "cnt": 18.000  // O cualquier valor > 3
}
```

### Caso 3: ¿Devuelve manual?
```
GET /api/envios/productos-disponibles
```
**Esperado:** 
```json
[
  { "codigo": "152", "descripcion": "CHOC C ALMENDRAS", ... },
  { "codigo": "107", "descripcion": "CEREZA ITALIANA", ... },
  ...más 350 productos...
]
```

---

## ✅ Checklist: ¿Funciona Correctamente?

Marca cuando hayas validado cada uno:

- [ ] **Test 1:** Encuentra cantidad = 1 ✓
- [ ] **Test 2:** Encuentra cantidad > 1 cuando no hay exacta ✓
- [ ] **Test 3:** Permite agregar mismo producto 3 veces ✓
- [ ] **Test 4:** Lista manual devuelve todos ✓
- [ ] **Test 5:** Busca por texto funciona ✓
- [ ] **Test 6:** Busca por peso funciona ✓

---

## 🎬 Walkthrough Completo en Navegador

```
INICIO: http://localhost/mikelo/envios.html
│
├─ Seleccionar destino: "Sucursal 1"
│
├─ TEST 1: Buscar cantidad exacta
│  ├─ Código: 405
│  ├─ Cantidad: 1
│  ├─ Clic: "Buscar"
│  └─ Resultado: ✅ Encuentra "PAN SALVADO"
│
├─ TEST 2: Agregar producto 3 veces
│  ├─ Agregar Pan Salvado (1 unidad) → ✅
│  ├─ Agregar Pan Salvado (1 unidad) → ✅
│  ├─ Agregar Pan Salvado (1 unidad) → ✅
│  └─ Total en envío: 3 unidades de Pan Salvado
│
├─ TEST 3: Buscar nuevo producto sin código
│  ├─ Código: (vacío)
│  ├─ Cantidad: (vacío)
│  ├─ Clic: "Buscar todos"
│  └─ Resultado: ✅ Lista de 350+ productos
│
├─ TEST 4: Agregar otro producto
│  ├─ Selecciona "LIMÓN" de la lista
│  └─ Clic: "Agregar"
│
├─ TEST 5: Guardar envío
│  ├─ Clic: "Guardar Envío"
│  └─ Resultado: ✅ Envío creado con 4 items
│
└─ FINAL: Validar que envío se creó correctamente
```

---

## 🔧 Si Algo No Funciona

### Problema: "Producto no encontrado"
```
Verificar:
1. ¿El código existe? (ej: 405 = Pan Salvado)
2. ¿Hay stock disponible?
   SELECT * FROM movimientos_items 
   WHERE id_productos = 405
   AND cnt > SUM(id_movimientos_items_origen)
```

### Problema: "No puedo agregar múltiples"
```
Verificar:
1. ¿Validación en js/envios_nuevo.js?
2. ¿Stock control está activo?
3. Abre F12 → Console (browser)
```

### Problema: "Test HTTP retorna error 500"
```
Verificar:
1. php -l api/src/Model/Envio.php
2. tail -f var/log/error.log
3. phpinfo() en navegador
```

---

## 📊 Expected Results Summary

| Test | Entrada | Esperado | Status |
|------|---------|----------|--------|
| PASO 1 | código=405, cantidad=1 | Encuentra | ? |
| PASO 2 | código=405, cantidad=3 | Encuentra superior | ? |
| PASO 3 | (vacío) | 350+ productos | ? |
| Peso | peso=6.355 | Encuentra LIMÓN | ? |
| Texto | filtro=pan | 9 productos | ? |
| Múltiple | Agregar 3x Pan | Permite | ? |

---

## 🎯 Ahora Tú Decides

Una vez hayas hecho los tests, indícame:

1. ✅ **¿TODO funciona?** → Solución completada
2. ⚠️ **¿Hay issues?** → Documentalos y continuamos
3. 🤔 **¿Dudas?** → Aclaramos

**Adelante, a probar en el navegador.**

