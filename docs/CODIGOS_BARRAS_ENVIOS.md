# Códigos de Barras - Sistema de Envíos

## 📋 Formato del Código de Barras (13 dígitos)

```
[TT][CCCCC][VVVVV][V]
│   │      │      └─ Dígito verificador (no usado)
│   │      └──────── Valor (5 dígitos): cantidad o peso en gramos
│   └─────────────── Código de producto (5 dígitos con ceros a la izquierda)
└─────────────────── Tipo (2 dígitos): 20=unidades, 21=peso
```

### Ejemplos:
- `2000123000001` → Tipo 20, Producto 123, Cantidad 1 unidad
- `2100456041250` → Tipo 21, Producto 456, Peso 4.125 kg

---

## 🔍 Tipos de Búsqueda Soportados

### 1️⃣ Búsqueda Manual (texto libre)
**Input del usuario**: "chocolate", "123", "helado"

**Comportamiento**:
- Busca en `productos.codigo` y `productos.descripcion` con `LIKE '%texto%'`
- Devuelve múltiples resultados
- Usuario selecciona manualmente el producto
- Cantidad editable de 1 hasta stock disponible

**Código relevante**:
- Frontend: `js/envios_nuevo.js` línea ~230
- Backend: `api/src/Model/Envio.php` método `obtenerProductosDisponibles()` línea ~305

---

### 2️⃣ Código de Barras TIPO 20 (Unidades)
**Input del usuario**: Escaneo de código `2000123000001`

**Parsing (Frontend)**:
```javascript
// js/envios_nuevo.js línea ~200
const tipo = codigo.substring(0, 2);           // "20"
const codigoProducto = parseInt(codigo.substring(2, 7)).toString();  // "123"
const cantidad = parseInt(codigo.substring(7, 12));  // 1
```

**Llamada API**:
```
GET /api/envios/productos-disponibles?codigo=123&cantidad=1
```

**Comportamiento Backend**:
- Busca producto con `productos.codigo = '123'` exacto
- Verifica stock disponible
- Devuelve producto con cantidad inicial = 1
- **Cantidad editable** en frontend hasta stock disponible

**Código relevante**:
- Backend: `api/src/Model/Envio.php` línea ~285 (filtro por código)

---

### 3️⃣ Código de Barras TIPO 21 (Peso) ⚠️ **PESO EXACTO**
**Input del usuario**: Escaneo de código `2100123041250`

**Parsing (Frontend)**:
```javascript
// js/envios_nuevo.js línea ~213
const tipo = codigo.substring(0, 2);           // "21"
const codigoProducto = parseInt(codigo.substring(2, 7)).toString();  // "123"
const pesoGramos = parseInt(codigo.substring(7, 12));  // 4125
const pesoKg = (pesoGramos / 1000).toFixed(3); // "4.125"
```

**Llamada API**:
```
GET /api/envios/productos-disponibles?codigo=123&peso=4.125
```

**Comportamiento Backend**:
- Busca producto con `productos.codigo = '123'` Y `movimientos_items.cnt_peso = 4.125` **EXACTO**
- ⚠️ **SIN TOLERANCIA**: la etiqueta leída es la misma que se usó al dar de alta
- Si encuentra: devuelve 1 producto, se agrega automáticamente con cantidad=1
- Si NO encuentra: devuelve array vacío → error en frontend

**Mensaje de Error Frontend**:
```
"No hay stock con ese peso (4.125 kg)"
```

**Código relevante**:
- Frontend: `js/envios_nuevo.js` líneas ~245-250 (mensaje de error específico)
- Backend: `api/src/Model/Envio.php` línea ~291 (filtro peso exacto)

---

## ⚙️ Reglas de Disponibilidad (Común a todos los tipos)

Un producto está disponible para envío si cumple:

1. ✅ **Es ítem original**: `movimientos_items.id_movimientos_items_origen IS NULL`
2. ✅ **Tiene stock disponible**: `cnt > suma(ítems derivados)`
3. ✅ **Estado actual = NUEVO**: última entrada en `estados_items_movimientos` con `id_estados = 1`
4. ✅ **No está dado de baja**: sin estado "BAJA"

**SQL relevante** en `api/src/Model/Envio.php` línea ~220-280.

---

## 🛠️ Archivos Modificados (Octubre 2025)

### Backend
**Archivo**: `api/src/Model/Envio.php`

**Cambios**:
```php
// ANTES (tolerancia ±0.1 kg - INCORRECTO)
if (!empty($filtros['peso'])) {
    $sql .= " AND mi.cnt_peso BETWEEN ? AND ?";
    $params[] = $filtros['peso'] - 0.1;
    $params[] = $filtros['peso'] + 0.1;
}

// DESPUÉS (peso exacto - CORRECTO)
if (!empty($filtros['peso'])) {
    $sql .= " AND mi.cnt_peso = ?";
    $params[] = $filtros['peso'];
}
```

**Documentación agregada**: PHPDoc completo en método `obtenerProductosDisponibles()` línea ~221.

---

### Frontend
**Archivo**: `js/envios_nuevo.js`

**Cambios**:
- Línea ~245: Mensaje de error específico para tipo "21" sin stock
- Mensaje: `"No hay stock con ese peso (X.XXX kg)"`

---

## 🧪 Testing

### Caso de Prueba 1: Código Tipo 21 - Producto Existe
```
Código escaneado: 2100123041250
Producto en BD: codigo=123, cnt_peso=4.125
Resultado esperado: ✅ Producto agregado automáticamente al envío
```

### Caso de Prueba 2: Código Tipo 21 - Producto NO Existe
```
Código escaneado: 2100123041250
Producto en BD: codigo=123, cnt_peso=4.126 (diferente)
Resultado esperado: ❌ Error "No hay stock con ese peso (4.125 kg)"
```

### Caso de Prueba 3: Código Tipo 20 - Producto Existe
```
Código escaneado: 2000123000001
Producto en BD: codigo=123, cnt=10
Resultado esperado: ✅ Producto agregado con cantidad=1 (editable hasta 10)
```

---

## 📚 Referencias

- **Instrucciones del proyecto**: `.github/copilot-instructions.md`
- **Arquitectura BD**: `docs/ARQUITECTURA.md` (si existe)
- **Flujo de estados**: `productos → movimientos → movimientos_items → estados_items_movimientos`

---

## ⚠️ IMPORTANTE: NO MODIFICAR

### ❌ NO cambiar la comparación de peso a tolerancia
El peso en el código de barras tipo "21" es **exacto** porque:
1. La etiqueta se genera AL DAR DE ALTA el producto en stock
2. La misma etiqueta se escanea AL CREAR EL ENVÍO
3. No hay variación de balanza entre ambos momentos
4. Cualquier tolerancia causa matches incorrectos

### ❌ NO modificar el parsing en frontend sin actualizar backend
El frontend y backend deben estar sincronizados:
- Frontend parsea y convierte gramos → kg
- Backend recibe kg y busca exacto
- Cambiar uno sin el otro rompe el flujo

---

## 📝 Historial de Cambios

| Fecha | Cambio | Motivo |
|-------|--------|--------|
| Oct 2025 | Peso exacto (sin tolerancia) | Tolerancia de ±0.1 kg devolvía múltiples productos incorrectos |
| Oct 2025 | Documentación completa | Evitar olvido del proceso y cambios accidentales |

---

**Última actualización**: Octubre 2025
**Responsable**: Sistema Mikelo - Gestión de Inventario de Helados
