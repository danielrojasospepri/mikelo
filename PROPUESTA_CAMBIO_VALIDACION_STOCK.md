# 📋 PROPUESTA DE CAMBIO: VALIDACIÓN EN STOCK (No en "ya existe")

## 🎯 OBJETIVO

**Permitir agregar el mismo producto múltiples veces**, pero validando que la **suma de cantidad agregada + lo que ya está en envío** NO exceda el **stock disponible**.

---

## 📊 LÓGICA DEL CAMBIO

### ANTES (Rechaza por duplicado)
```
Escaneo Pan Salvado 1ª vez → ✅ Agregado (cant 1)
Escaneo Pan Salvado 2ª vez → ❌ RECHAZADO ("ya está en envío")
                               ↑ Validación: "existe en array"
```

### DESPUÉS (Valida en stock)
```
Escaneo Pan Salvado 1ª vez → ✅ Agregado (cant 1)
Escaneo Pan Salvado 2ª vez → ¿Stock disponible > cantidad total?
                              ├─ SÍ → ✅ Agregado (cant 1, nueva línea)
                              │       (Total en envío: 2)
                              └─ NO → ❌ RECHAZADO (cantidad insuficiente)
```

---

## 🔧 CAMBIO ESPECÍFICO

**Archivo:** `js/envios_nuevo.js`  
**Línea:** 337-376  
**Función:** `agregarProductoAlEnvio()`

### ❌ CÓDIGO ACTUAL (Rechaza por duplicado)

```javascript
window.agregarProductoAlEnvio = function(producto) {
    // Verificar si ya está en el envío
    const existe = productosEnEnvio.find(p => p.id_movimiento_item === producto.id_movimiento_item);
    if (existe) {
        mostrarEstadoOperacion('Este producto ya está en el envío', 'warning');
        return;  // ← RECHAZA SI EXISTE
    }

    // Calcular cantidad disponible
    const cantidadDisponible = producto.cnt_disponible !== undefined ? producto.cnt_disponible : producto.cnt;
    
    if (cantidadDisponible <= 0) {
        mostrarEstadoOperacion('No hay stock disponible de este producto', 'warning');
        return;
    }

    // Agregar con cantidad inicial = 1
    const cantidadInicial = Math.min(1, cantidadDisponible);
    const pesoUnitario = producto.cnt_peso / producto.cnt;
    const pesoInicial = (pesoUnitario * cantidadInicial).toFixed(3);

    const productoEnEnvio = {
        ...producto,
        cantidad: cantidadInicial,
        peso: parseFloat(pesoInicial),
        cnt_disponible: cantidadDisponible,
        peso_unitario: pesoUnitario
    };

    productosEnEnvio.push(productoEnEnvio);
    actualizarTablaProductosEnvio();
    
    $('#productosEncontrados').hide();
    limpiarBusquedaEnvio();
    
    if (productosEnEnvio.length === 1) {
        $('#productosEnvio').slideDown();
    }
};
```

### ✅ CÓDIGO MEJORADO (Valida stock, NO por duplicado)

```javascript
window.agregarProductoAlEnvio = function(producto) {
    // Calcular cantidad disponible en stock
    const cantidadDisponible = producto.cnt_disponible !== undefined ? producto.cnt_disponible : producto.cnt;
    
    if (cantidadDisponible <= 0) {
        mostrarEstadoOperacion('No hay stock disponible de este producto', 'warning');
        return;
    }

    // NUEVO: Calcular cantidad ya agregada al envío (del mismo producto)
    const cantidadYaEnEnvio = productosEnEnvio
        .filter(p => p.id_movimiento_item === producto.id_movimiento_item)
        .reduce((total, p) => total + (p.cantidad || 1), 0);

    // NUEVO: Calcular cantidad total si agregamos 1 más
    const cantidadTotalConNuevo = cantidadYaEnEnvio + 1;

    // NUEVO: Validar que no exceda disponible
    if (cantidadTotalConNuevo > cantidadDisponible) {
        mostrarEstadoOperacion(
            `Stock insuficiente. Ya agregado: ${cantidadYaEnEnvio}, ` +
            `disponible: ${cantidadDisponible}, solicitado: ${cantidadTotalConNuevo}`,
            'warning'
        );
        return;
    }

    // Si pasa validación de stock: agregar (sea primera vez o duplicado)
    const cantidadInicial = 1;
    const pesoUnitario = producto.cnt_peso / producto.cnt;
    const pesoInicial = (pesoUnitario * cantidadInicial).toFixed(3);

    const productoEnEnvio = {
        ...producto,
        cantidad: cantidadInicial,
        peso: parseFloat(pesoInicial),
        cnt_disponible: cantidadDisponible,
        peso_unitario: pesoUnitario
    };

    productosEnEnvio.push(productoEnEnvio);
    actualizarTablaProductosEnvio();
    
    $('#productosEncontrados').hide();
    limpiarBusquedaEnvio();
    
    if (productosEnEnvio.length === 1) {
        $('#productosEnvio').slideDown();
    }
};
```

---

## 📊 DIFERENCIA CLAVE

| Aspecto | Antes | Después |
|---------|-------|---------|
| **Validación** | "¿Ya existe en array?" | "¿Cantidad total excede stock?" |
| **Si existe 1ª vez** | ✅ Agregado | ✅ Agregado |
| **Si existe 2ª vez** | ❌ RECHAZADO | ⚠️ Valida stock |
| **Si hay stock** | N/A | ✅ Agregado (nueva línea) |
| **Si NO hay stock** | N/A | ❌ RECHAZADO |

---

## 🎯 CASOS DE USO

### Caso 1: Pan Salvado (cantidad disponible: 5)
```
Escaneo 1 → Ya en envío: 0, Total si agrega: 1 → ✅ OK (1 ≤ 5)
Escaneo 2 → Ya en envío: 1, Total si agrega: 2 → ✅ OK (2 ≤ 5)
Escaneo 3 → Ya en envío: 2, Total si agrega: 3 → ✅ OK (3 ≤ 5)
Escaneo 4 → Ya en envío: 3, Total si agrega: 4 → ✅ OK (4 ≤ 5)
Escaneo 5 → Ya en envío: 4, Total si agrega: 5 → ✅ OK (5 ≤ 5)
Escaneo 6 → Ya en envío: 5, Total si agrega: 6 → ❌ NO (6 > 5)
            "Stock insuficiente. Ya agregado: 5, disponible: 5, solicitado: 6"
```

**Resultado en tabla:**
```
Código   Descripción     Cantidad  Peso
405      Pan Salvado     1         0
405      Pan Salvado     1         0
405      Pan Salvado     1         0
405      Pan Salvado     1         0
405      Pan Salvado     1         0
(5 líneas separadas, suma total: 5)
```

### Caso 2: Helado Fresa (cantidad disponible: 3)
```
Escaneo 1 → Ya en envío: 0, Total si agrega: 1 → ✅ OK (1 ≤ 3)
Escaneo 2 → Ya en envío: 1, Total si agrega: 2 → ✅ OK (2 ≤ 3)
Escaneo 3 → Ya en envío: 2, Total si agrega: 3 → ✅ OK (3 ≤ 3)
Escaneo 4 → Ya en envío: 3, Total si agrega: 4 → ❌ NO (4 > 3)
```

### Caso 3: Dos productos diferentes
```
Escaneo Pan (5 disponibles) → Ya en envío: 0, Total: 1 → ✅ OK
Escaneo Helado (3 disponibles) → Es diferente, Ya: 0, Total: 1 → ✅ OK
Escaneo Pan (5 disponibles) → Ya en envío: 1, Total: 2 → ✅ OK
```

**Resultado en tabla:**
```
Código   Descripción     Cantidad  Peso
405      Pan Salvado     1         0
100      Helado Fresa    1         3.5
405      Pan Salvado     1         0
(Pan aparece 2 veces: cantidad 1 + 1 = 2 total)
```

---

## 🔐 VALIDACIÓN EN ENVÍO (No cambia)

Cuando el usuario hace click en "Guardar Envío", el backend TAMBIÉN valida:
- Que el stock disponible actual siga siendo ≥ cantidad solicitada
- (Porque otro usuario pudo comprar stock mientras se preparaba el envío)

---

## 💡 VENTAJAS

✅ **Permite duplicados:** Sin restricción de "ya existe"  
✅ **Valida stock:** No permite sobrevender  
✅ **Líneas separadas:** Cada escaneo = 1 línea (ideal para control de bandejas)  
✅ **Feedback claro:** Dice exactamente qué hay en envío vs disponible  
✅ **Seguro:** Backend también valida al guardar  

---

## ⚠️ CONSIDERACIÓN

Las líneas en la tabla aparecerán **separadas** (una por escaneo):
```
Pan Salvado   1
Pan Salvado   1
Pan Salvado   1
```

Si prefieres que se **agrupen automáticamente** en una sola línea, necesitaríamos lógica adicional.

¿Este cambio te parece correcto?

