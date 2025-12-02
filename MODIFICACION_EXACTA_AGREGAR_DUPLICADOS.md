# 🔧 MODIFICACIÓN EXACTA: PERMITIR AGREGAR MÚLTIPLES VECES MISMO PRODUCTO

## 📍 ARCHIVO Y UBICACIÓN

**Archivo:** `js/envios_nuevo.js`  
**Línea:** 337-376  
**Función:** `agregarProductoAlEnvio()`

---

## 🔴 CÓDIGO ACTUAL (RECHAZA DUPLICADOS)

```javascript
window.agregarProductoAlEnvio = function(producto) {
    // Verificar si ya está en el envío
    const existe = productosEnEnvio.find(p => p.id_movimiento_item === producto.id_movimiento_item);
    if (existe) {
        mostrarEstadoOperacion('Este producto ya está en el envío', 'warning');
        return;
    }

    // Calcular cantidad disponible
    const cantidadDisponible = producto.cnt_disponible !== undefined ? producto.cnt_disponible : producto.cnt;
    
    if (cantidadDisponible <= 0) {
        mostrarEstadoOperacion('No hay stock disponible de este producto', 'warning');
        return;
    }

    // Agregar con cantidad inicial = 1 (o el mínimo disponible)
    const cantidadInicial = Math.min(1, cantidadDisponible);
    const pesoUnitario = producto.cnt_peso / producto.cnt; // Peso por unidad
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
    
    // Mostrar sección de productos si es el primero
    if (productosEnEnvio.length === 1) {
        $('#productosEnvio').slideDown();
    }
};
```

---

## 🟢 CÓDIGO MEJORADO (AGRUPA CANTIDADES)

```javascript
window.agregarProductoAlEnvio = function(producto) {
    // Verificar si ya está en el envío
    const existe = productosEnEnvio.find(p => p.id_movimiento_item === producto.id_movimiento_item);
    
    if (existe) {
        // ========== NUEVA LÓGICA: AGREGAR A CANTIDAD EXISTENTE ==========
        const cantidadDisponible = existe.cnt_disponible !== undefined ? existe.cnt_disponible : existe.cnt;
        const cantidadActual = existe.cantidad || 1;
        const nuevaCantidad = cantidadActual + 1;
        
        // Validar que no exceda disponible
        if (nuevaCantidad > cantidadDisponible) {
            mostrarEstadoOperacion(
                `No hay más stock. Disponible: ${cantidadDisponible}, Solicitado: ${nuevaCantidad}`,
                'warning'
            );
            return;
        }
        
        // Recalcular peso proporcional
        const pesoUnitario = existe.peso_unitario || (existe.cnt_peso / existe.cnt);
        const nuevoPeso = (pesoUnitario * nuevaCantidad).toFixed(3);
        
        // Actualizar el producto existente
        existe.cantidad = nuevaCantidad;
        existe.peso = parseFloat(nuevoPeso);
        
        // Feedback al usuario
        mostrarEstadoOperacion(
            `✓ ${producto.descripcion}: cantidad actualizada a ${nuevaCantidad}`,
            'success'
        );
        
        actualizarTablaProductosEnvio();
        $('#productosEncontrados').hide();
        limpiarBusquedaEnvio();
        return;
    }

    // ========== LÓGICA ORIGINAL: AGREGAR NUEVO PRODUCTO ==========
    
    // Calcular cantidad disponible
    const cantidadDisponible = producto.cnt_disponible !== undefined ? producto.cnt_disponible : producto.cnt;
    
    if (cantidadDisponible <= 0) {
        mostrarEstadoOperacion('No hay stock disponible de este producto', 'warning');
        return;
    }

    // Agregar con cantidad inicial = 1 (o el mínimo disponible)
    const cantidadInicial = Math.min(1, cantidadDisponible);
    const pesoUnitario = producto.cnt_peso / producto.cnt; // Peso por unidad
    const pesoInicial = (pesoUnitario * cantidadInicial).toFixed(3);

    const productoEnEnvio = {
        ...producto,
        cantidad: cantidadInicial,
        peso: parseFloat(pesoInicial),
        cnt_disponible: cantidadDisponible,
        peso_unitario: pesoUnitario
    };

    productosEnEnvio.push(productoEnEnvio);
    
    // Feedback mejorado
    mostrarEstadoOperacion(
        `✓ ${producto.descripcion}: agregado a envío (cantidad: 1)`,
        'success'
    );
    
    actualizarTablaProductosEnvio();
    $('#productosEncontrados').hide();
    limpiarBusquedaEnvio();
    
    // Mostrar sección de productos si es el primero
    if (productosEnEnvio.length === 1) {
        $('#productosEnvio').slideDown();
    }
};
```

---

## 📊 DIFERENCIAS CLAVE

### ❌ ANTES (Línea 341-344)
```javascript
if (existe) {
    mostrarEstadoOperacion('Este producto ya está en el envío', 'warning');
    return;  // ← RECHAZA COMPLETAMENTE
}
```

### ✅ DESPUÉS (Nueva línea 342-368)
```javascript
if (existe) {
    // ... validar stock ...
    // ... actualizar cantidad ...
    // ... actualizar peso ...
    return;  // ← SALE PERO YA ACTUALIZÓ EL PRODUCTO
}
```

---

## 🎯 COMPORTAMIENTO ESPERADO

### Escenario 1: Pan Salvado (cantidad)
```
1er escaneo: 2000405000015 → Tabla: [Pan Salvado: cantidad 1, peso 0]
2do escaneo: 2000405000015 → Tabla: [Pan Salvado: cantidad 2, peso 0]
3er escaneo: 2000405000015 → Tabla: [Pan Salvado: cantidad 3, peso 0]
            ✓ Agrupa en UNA línea
```

### Escenario 2: Helado Fresa (peso)
```
1er escaneo: 2100123003500 → Tabla: [Helado Fresa: cantidad 1, peso 3.5kg]
2do escaneo: 2100123004200 → Tabla: [Helado Fresa: cantidad 2, peso 7kg]
             (si viene de diferente bandeja con diferente peso)
            ✓ Se agrupan porque es el mismo id_movimiento_item
```

### Escenario 3: Sin más stock
```
Stock disponible: 3 unidades
1er escaneo → cantidad 1 ✅
2do escaneo → cantidad 2 ✅
3er escaneo → cantidad 3 ✅
4to escaneo → "No hay más stock. Disponible: 3, Solicitado: 4" ⚠️
            ✓ No permite exceder
```

---

## 💭 NOTAS IMPORTANTES

### 1. ¿Qué identifica si es "el mismo producto"?
Usa `id_movimiento_item` (referencia al item en stock depósito)

### 2. ¿Cómo calcula el peso al agrupar?
Mantiene `peso_unitario` y multiplica por nueva cantidad:
```
peso_nuevo = peso_unitario * cantidad_nueva
```

### 3. ¿Se sigue pudiendo editar cantidad?
**SÍ**, la tabla sigue permitiendo cambios manuales en el campo cantidad

### 4. ¿Qué pasa si el usuario edita y escanea después?
Si hay 2 unidades en la tabla y escanea → suma 1 más → quedan 3

---

## ✅ VENTAJAS DE ESTE CAMBIO

| Aspecto | Antes | Después |
|---------|-------|---------|
| 1er Pan Salvado | ✅ Se agrega | ✅ Se agrega |
| 2do Pan Salvado | ❌ ERROR | ✅ Se agrupa (cantidad 2) |
| 3er Pan Salvado | ❌ ERROR | ✅ Se agrupa (cantidad 3) |
| Edición manual | ⚠️ Sí pero después rechazaba | ✅ Sí, libre |
| Control de stock | ✅ Funciona | ✅ Funciona + al agrupar |
| Experiencia UX | 😞 Confusa | 😊 Natural |

---

## 🔄 FLUJO VISUAL

```
Usuario escanea Pan Salvado (2do escaneo)
            ↓
agregarProductoAlEnvio(producto)
            ↓
¿Ya existe en productosEnEnvio?
   ├─ SÍ (NUEVO COMPORTAMIENTO)
   │  ├─ Cantidad actual = 1
   │  ├─ Nueva cantidad = 2
   │  ├─ ¿Hay stock para 2?
   │  │  ├─ SÍ → Actualiza objeto en array
   │  │  │       Recalcula peso
   │  │  │       Muestra: "✓ Cantidad actualizada a 2"
   │  │  │       Refresca tabla
   │  │  └─ NO → Muestra: "No hay más stock"
   │  │         Retorna sin cambios
   │
   └─ NO (COMPORTAMIENTO ORIGINAL)
      ├─ Crea nuevo objeto
      ├─ Agrega al array
      ├─ Muestra: "✓ Pan Salvado: agregado (cantidad 1)"
      └─ Refresca tabla
```

