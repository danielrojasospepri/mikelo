# 📋 PLAN: PERMITIR AGREGAR MÚLTIPLES VECES MISMO PRODUCTO

## 🎯 OBJETIVO

Permitir escanear varias veces el mismo producto (ej: Pan Salvado) sin rechazarlo, agrupando cantidades en **una sola línea** del envío.

---

## 🔍 PROBLEMA IDENTIFICADO

**Ubicación:** `js/envios_nuevo.js` línea 338-344

**Código actual (rechaza duplicados):**
```javascript
window.agregarProductoAlEnvio = function(producto) {
    // Verificar si ya está en el envío
    const existe = productosEnEnvio.find(p => p.id_movimiento_item === producto.id_movimiento_item);
    if (existe) {
        mostrarEstadoOperacion('Este producto ya está en el envío', 'warning');
        return;  // ← RECHAZA Y SALE
    }
```

## 📊 COMPORTAMIENTO DESEADO

### Escenario: Pan Salvado (código: 2000405000015)

**Primer escaneo:**
```
productosEnEnvio = [
    {
        id_movimiento_item: 123,
        id_productos: 405,
        descripcion: "Pan Salvado",
        cantidad: 1,
        peso: 0,
        ...
    }
]
```

**Segundo escaneo (del mismo Pan Salvado):**
```
productosEnEnvio = [
    {
        id_movimiento_item: 123,
        id_productos: 405,
        descripcion: "Pan Salvado",
        cantidad: 2,  ← SUMA (1+1)
        peso: 0,
        ...
    }
]
```

**Resultado en tabla:**
```
Código   Descripción   Cantidad   Peso      Contenedor   Acción
405      Pan Salvado   2          0 kg      -            [X]
```

---

## ⚙️ SOLUCIÓN TÉCNICA

### CAMBIO: Reemplazar lógica de rechazo por agrupamiento

**UBICACIÓN:** `js/envios_nuevo.js` línea 337-376

**ANTES (rechaza):**
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

**DESPUÉS (agrupa):**
```javascript
window.agregarProductoAlEnvio = function(producto) {
    // Verificar si ya está en el envío (PARA AGRUPAR)
    const existe = productosEnEnvio.find(p => p.id_movimiento_item === producto.id_movimiento_item);
    
    if (existe) {
        // SI YA EXISTE: Aumentar cantidad en 1
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
        
        // Actualizar cantidad y peso
        const pesoUnitario = existe.peso_unitario || (existe.cnt_peso / existe.cnt);
        const nuevoPeso = (pesoUnitario * nuevaCantidad).toFixed(3);
        
        existe.cantidad = nuevaCantidad;
        existe.peso = parseFloat(nuevoPeso);
        
        mostrarEstadoOperacion(`${producto.descripcion}: cantidad actualizada a ${nuevaCantidad}`, 'success');
        actualizarTablaProductosEnvio();
        
        $('#productosEncontrados').hide();
        limpiarBusquedaEnvio();
        return;
    }

    // SI NO EXISTE: Agregar nuevo
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
    
    mostrarEstadoOperacion(`${producto.descripcion}: agregado a envío (cantidad: 1)`, 'success');
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

## 📝 CAMBIOS ADICIONALES

### Mejora 1: Feedback mejorado

Cuando el usuario escanea, ahora verá:
- **1er escaneo:** "Pan Salvado: agregado a envío (cantidad: 1)" ✅
- **2do escaneo:** "Pan Salvado: cantidad actualizada a 2" ✅
- **3er escaneo:** "Pan Salvado: cantidad actualizada a 3" ✅
- **Si no hay más stock:** "No hay más stock. Disponible: 3, Solicitado: 4" ⚠️

### Mejora 2: Control manual de cantidad

El usuario sigue pudiendo editar en la tabla:
- Reducir cantidad si se pasó al escanear
- Aumentar hasta el máximo disponible

---

## 🧪 CASOS DE PRUEBA

### Caso 1: Agregar 3 Pan Salvado por escaneo
1. Escanear Pan Salvado → Cantidad: 1 ✅
2. Escanear Pan Salvado → Cantidad: 2 ✅
3. Escanear Pan Salvado → Cantidad: 3 ✅
4. **Resultado:** Una sola línea con cantidad 3

### Caso 2: Agregar dos productos diferentes
1. Escanear Pan Salvado → Tabla: [Pan Salvado: 1]
2. Escanear Helado Fresa → Tabla: [Pan Salvado: 1, Helado Fresa: 1]

### Caso 3: Exceder stock disponible
1. Helado tiene 5 unidades disponibles
2. Escanear 6 veces → 5to escaneo OK, 6to escaneo rechazado ⚠️

---

## 💡 VENTAJAS

✅ Usuario no recibe "error" por duplicado  
✅ Agrupamiento automático de cantidades  
✅ Preserva control manual (todavía puede editar)  
✅ Feedback claro en pantalla  
✅ Validación de stock al agrupar  

---

## 🚀 IMPLEMENTACIÓN

Solo cambiar la función `agregarProductoAlEnvio` en `js/envios_nuevo.js` línea 337

