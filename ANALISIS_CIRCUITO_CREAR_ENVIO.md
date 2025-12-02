# ANÁLISIS DEL CIRCUITO DE CREAR ENVÍO

## 1. FLUJO FRONTEND (JavaScript)

### Archivo: `js/envios_nuevo.js`

#### Paso 1: Inicialización
- `cargarUbicaciones()` - Rellena el dropdown de destinos
- `cargarEstados()` - Carga estados
- `cargarFamilias()` - Carga familias (agregado recientemente)
- `cargarEnvios()` - Carga lista de envíos

#### Paso 2: Click "Nuevo Envío" (línea 15)
- Evento `#btnNuevoEnvio` dispara `mostrarPanelNuevoEnvio()`
- Muestra panel escondido con selector de destino y búsqueda de productos

#### Paso 3: Seleccionar Destino
- Evento `#destinoEnvio` change → `$('#sectionBusquedaProductos').slideDown()`
- Habilita campo de búsqueda/escaneo

#### Paso 4: Buscar/Escanear Producto (línea ~42)
- Input `#buscarProductoEnvio` con evento `input`
- Llamada a `buscarProductosDisponibles()` con delay de 500ms
- Fetch a `api/envios/productos-disponibles?codigo=X&cantidad=Y&peso=Z&filtro=TEXTO`

#### Paso 5: Agregar Producto al Envío
- Tabla `#tablaProductosEncontrados` con botones de agregar
- Evento click en "Agregar" → `agregarProductoAlEnvio()`
- Agrega a array `productosEnEnvio[]`
- Muestra tabla `#productosEnvio` con productos agregados

#### Paso 6: Guardar Envío (línea 77)
- Evento `#btnGuardarEnvio` → `guardarEnvio()`
- **DATOS ENVIADOS:**
```javascript
{
    destino: <id_ubicacion>,
    productos: [
        {
            id_productos: <id>,
            cantidad: <number>,
            peso: <float>,
            id_movimientos_items_origen: <id> // Opcional (solo si viene de stock)
        }
    ]
}
```
- Método: **POST** a `api/envios`
- Response esperada: `{success: true, id: <envio_id>}`

#### Paso 7: Confirmación Post-Guardado
- Si success: muestra modal con 2 opciones:
  1. "Confirmar Envío Ahora" → `confirmarEnvio(envioId)` → PUT `/envios/{id}/confirmar`
  2. "Volver a Lista" → cierra panel

---

## 2. FLUJO BACKEND (API)

### Archivo: `api/index.php` (línea 252)

```php
$app->post('/envios', function (Request $request, Response $response) use ($db) {
    $controller = new EnvioController($db);
    return $controller->crear($request, $response);
});
```

### Archivo: `api/src/Controller/EnvioController.php` (línea 17)

```php
public function crear(Request $request, Response $response) {
    $data = json_decode($request->getBody()->getContents(), true);
    
    // Validaciones:
    // - $data['destino'] requerido
    // - $data['productos'] requerido y no vacío
    
    try {
        $envioId = $this->envio->crear($data['destino'], $data['productos']);
        return responseJson($response, [
            'success' => true,
            'id' => $envioId,
            'mensaje' => 'Envío creado exitosamente'
        ], 201);
    } catch (\Exception $e) {
        return responseJson($response, ['error' => $e->getMessage()], 500);
    }
}
```

### Archivo: `api/src/Model/Envio.php` (línea 66)

```php
public function crear($destino, $productos) {
    try {
        $this->db->beginTransaction();
        
        // 1. CREAR MOVIMIENTO PRINCIPAL
        INSERT INTO movimientos (
            fechaAlta, id_ubicacion_origen, id_ubicacion_destino, usuario_alta
        ) VALUES (NOW(), 1, $destino, 'sistema')
        
        $idMovimiento = lastInsertId();
        
        // 2. PARA CADA PRODUCTO:
        foreach ($productos) {
            // Si tiene id_movimientos_items_origen (viene de stock):
            //   - Validar cantidad disponible
            //   - Obtener contenedor del item origen
            //   - Usar la referencia
            // Sino (nueva alta):
            //   - idContenedor = NULL
            //   - idMovItemOrigen = NULL
            
            INSERT INTO movimientos_items (
                id_movimientos, id_productos, cnt, cnt_peso,
                id_movimientos_items_origen, id_contenedor
            ) VALUES ($idMovimiento, $id_prod, $cantidad, $peso, $origen, $contenedor)
            
            // 3. REGISTRAR ESTADO INICIAL (NUEVO = estado 1)
            INSERT INTO estados_items_movimientos (
                id_estados, id_movimientos_items, fecha_alta, usuario_alta
            ) VALUES (1, $idMovimientoItem, NOW(), 'sistema')
        }
        
        $this->db->commit();
        return $idMovimiento;
    } catch (\Exception $e) {
        $this->db->rollBack();
        throw $e;
    }
}
```

---

## 3. FLUJO DE CONFIRMACIÓN

### Archivo: `js/envios_nuevo.js` (línea ~560)

```javascript
function confirmarEnvio(envioId) {
    fetch(`api/envios/${envioId}/confirmar`, {
        method: 'PUT'
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            Swal.fire('Éxito', 'Envío confirmado correctamente', 'success');
            cargarEnvios(); // Recargar tabla
        }
    })
}
```

### Backend: `api/src/Controller/EnvioController.php`

```php
public function confirmarEnvio(Request $request, Response $response, $args) {
    try {
        $this->envio->confirmarEnvio($args['id']);
        return responseJson($response, ['success' => true]);
    } catch (\Exception $e) {
        return responseJson($response, ['error' => $e->getMessage()], 500);
    }
}
```

### Model: `api/src/Model/Envio.php`

```php
public function confirmarEnvio($idEnvio) {
    try {
        $this->db->beginTransaction();
        
        // Obtener todos los items del envío
        SELECT id FROM movimientos_items WHERE id_movimientos = $idEnvio
        
        // Para cada item: cambiar estado a ENVIADO (estado 2)
        INSERT INTO estados_items_movimientos (
            id_estados, id_movimientos_items, fecha_alta, usuario_alta
        ) VALUES (2, $itemId, NOW(), 'sistema')
        
        $this->db->commit();
        return true;
    } catch (\Exception $e) {
        $this->db->rollBack();
        throw $e;
    }
}
```

---

## 4. DIAGRAMA DE ESTADOS

```
CREAR ENVÍO → Estado NUEVO (1)
             ↓
        (Mostrado en lista)
             ↓
    Confirmar Envío → Estado ENVIADO (2)
             ↓
        (Item referenciado por otros envíos)
```

---

## 5. PUNTOS CRÍTICOS A REVISAR

### Frontend:
- ✓ `destinoEnvio` seleccionado?
- ✓ Al menos 1 producto agregado?
- ✓ Mapeo correcto de `id_productos`, `cantidad`, `peso`
- ✓ `id_movimientos_items_origen` solo si viene de stock

### Backend:
- ✓ Validar que `destino` es válido (existe en ubicaciones)
- ✓ Validar que `id_productos` existen
- ✓ Validar `id_movimientos_items_origen` si se proporciona
- ✓ Validar cantidad disponible si es referencia a stock
- ✓ Transacción con rollback en caso de error

### Base de Datos:
- ✓ `movimientos`: origen=1, destino=input
- ✓ `movimientos_items`: todos con estado inicial NUEVO (1)
- ✓ `estados_items_movimientos`: registro de cada cambio de estado

---

## 6. VALIDACIONES A MEJORAR

1. **Validación de destino**: Verificar que no es el depósito central (id=1)
2. **Validación de producto**: Verificar que existe y está activo
3. **Validación de referencias**: Si `id_movimientos_items_origen` está vacía en algunos
4. **Control de duplicados**: ¿Se puede crear envío vacío?

