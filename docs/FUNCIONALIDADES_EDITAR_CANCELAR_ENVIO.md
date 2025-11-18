# Funcionalidades: Editar y Cancelar Envío
**Fecha**: 21 de octubre de 2025  
**Módulo**: Envíos  
**Archivos modificados**: `envios.html`, `js/envios_nuevo.js`, `api/index.php`

## 🎯 Objetivo

Agregar dos nuevas funcionalidades al modal de detalle de envíos:
1. **Editar Envío**: Permite modificar productos de un envío en estado NUEVO
2. **Cancelar Envío**: Permite cancelar un envío y devolver productos al stock

---

## ✅ Implementación Completada

### 1. **Botones en Modal de Detalle** (`envios.html`)

#### **Ubicación**: Modal footer (después de botones de impresión)

```html
<button type="button" class="btn btn-primary" id="btnEditarEnvioModal" style="display:none;">
    <i class="fas fa-edit"></i> Editar Envío
</button>
<button type="button" class="btn btn-danger" id="btnCancelarEnvioModal" style="display:none;">
    <i class="fas fa-ban"></i> Cancelar Envío
</button>
```

**Caracteristicas**:
- Inicialmente ocultos (`display:none`)
- Se muestran/ocultan según el estado del envío
- Iconos Font Awesome para mejor UX

---

### 2. **Lógica de Visibilidad** (`js/envios_nuevo.js`)

Ya existía en `mostrarDetalleEnModal()` línea ~863:

```javascript
// Botón Editar Envío: solo visible si estado = 'NUEVO'
if (estado === 'NUEVO') {
    $('#btnEditarEnvioModal').show().off('click').on('click', function() {
        $('#modalDetalleEnvio').modal('hide');
        cargarEnvioParaEdicion(detalle.envio.id);
    });
} else {
    $('#btnEditarEnvioModal').hide();
}

// Botón Cancelar Envío: visible si estado != 'CANCELADO' y != 'RECIBIDO'
if (estado !== 'CANCELADO' && estado !== 'RECIBIDO') {
    $('#btnCancelarEnvioModal').show().off('click').on('click', function() {
        $('#modalDetalleEnvio').modal('hide');
        confirmarCancelacionEnvio(detalle.envio.id);
    });
} else {
    $('#btnCancelarEnvioModal').hide();
}
```

**Reglas**:
- **Editar**: Solo en estado `NUEVO`
- **Cancelar**: En cualquier estado excepto `CANCELADO` o `RECIBIDO`

---

### 3. **Función: cargarEnvioParaEdicion()** (NUEVA)

```javascript
function cargarEnvioParaEdicion(id) {
    $.ajax({
        url: API_URL + '/envios/' + id,
        method: 'GET',
        success: function(response) {
            if (response.success) {
                const detalle = response.data;
                const envio = detalle.envio;
                
                // Verificar que esté en estado NUEVO
                if (envio.ultimo_estado !== 'NUEVO') {
                    Swal.fire('Error', 'Solo se pueden editar envíos en estado NUEVO', 'error');
                    return;
                }
                
                // Cambiar a modo edición
                modoEdicion = true;
                envioIdEdicion = id;
                
                // Llenar el formulario
                $('#ubicacionDestino').val(envio.id_ubicaciones_destino).trigger('change');
                
                // Cargar productos del envío
                productosEnvio = [];
                if (detalle.productos && detalle.productos.length > 0) {
                    detalle.productos.forEach(function(producto) {
                        productosEnvio.push({
                            id_movimiento_item: producto.id_movimiento_item,
                            id_producto: producto.id_producto,
                            codigo: producto.codigo,
                            descripcion: producto.descripcion,
                            cantidad: parseInt(producto.cnt),
                            peso: parseFloat(producto.cnt_peso),
                            contenedor: producto.contenedor || '-',
                            id_contenedor: producto.id_contenedor || null
                        });
                    });
                }
                
                actualizarTablaProductos();
                
                // Mostrar sección de envío
                $('#seccionEnvio').show();
                
                // Cambiar texto del botón
                $('#btnGuardarEnvio').html('<i class="fas fa-save"></i> Actualizar Envío');
                
                // Scroll al formulario
                $('html, body').animate({
                    scrollTop: $('#seccionEnvio').offset().top - 100
                }, 500);
                
                Swal.fire('Éxito', 'Envío cargado para edición', 'success');
            } else {
                Swal.fire('Error', response.message || 'Error al cargar el envío', 'error');
            }
        },
        error: function() {
            Swal.fire('Error', 'Error al cargar el envío para edición', 'error');
        }
    });
}
```

**Funcionalidad**:
1. Obtiene detalle del envío via API
2. Valida que esté en estado NUEVO
3. Activa modo edición (`modoEdicion = true`)
4. Carga destino en select
5. Carga productos en array `productosEnvio`
6. Actualiza tabla de productos
7. Muestra sección de formulario
8. Cambia botón a "Actualizar Envío"
9. Scroll automático al formulario

---

### 4. **Funciones: Cancelar Envío** (NUEVAS)

#### **confirmarCancelacionEnvio()**
```javascript
function confirmarCancelacionEnvio(id) {
    Swal.fire({
        title: '¿Cancelar Envío?',
        html: 'Esta acción:<br>' +
              '- Cambiará el estado a <strong>CANCELADO</strong><br>' +
              '- Devolverá todos los productos al stock<br>' +
              '<br>¿Desea continuar?',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        cancelButtonColor: '#3085d6',
        confirmButtonText: 'Sí, cancelar envío',
        cancelButtonText: 'No, volver'
    }).then((result) => {
        if (result.isConfirmed) {
            cancelarEnvio(id);
        }
    });
}
```

**Funcionalidad**: Muestra confirmación con advertencias claras

#### **cancelarEnvio()**
```javascript
function cancelarEnvio(id) {
    $.ajax({
        url: API_URL + '/envios/' + id + '/cancelar',
        method: 'POST',
        success: function(response) {
            if (response.success) {
                Swal.fire('Éxito', 'Envío cancelado correctamente. Los productos han vuelto al stock.', 'success');
                cargarEnvios(); // Recargar grilla
            } else {
                Swal.fire('Error', response.message || 'Error al cancelar el envío', 'error');
            }
        },
        error: function(xhr) {
            const errorMsg = xhr.responseJSON && xhr.responseJSON.message 
                ? xhr.responseJSON.message 
                : 'Error al cancelar el envío';
            Swal.fire('Error', errorMsg, 'error');
        }
    });
}
```

**Funcionalidad**: Llama al endpoint POST `/envios/{id}/cancelar`

---

### 5. **Endpoint API** (`api/index.php`)

```php
// Ruta para cancelar envío
$app->post('/envios/{id}/cancelar', function (Request $request, Response $response, $args) use ($db) {
    $controller = new EnvioController($db);
    return $controller->cancelarEnvio($request, $response, $args);
});
```

**Ubicación**: Después de ruta `pdf-preimpreso`, antes de `excel`

---

### 6. **Backend Existente**

#### **EnvioController::cancelarEnvio()** (YA EXISTÍA)
```php
public function cancelarEnvio(Request $request, Response $response, $args) {
    $data = json_decode($request->getBody()->getContents(), true);
    $id = $args['id'];

    if (!isset($data['motivo']) || empty(trim($data['motivo']))) {
        return responseJson($response, ['error' => 'El motivo es requerido'], 400);
    }

    try {
        $this->envio->cancelarEnvio($id, $data['motivo']);
        return responseJson($response, [
            'success' => true,
            'mensaje' => 'Envío cancelado exitosamente'
        ]);
    } catch (\Exception $e) {
        return responseJson($response, ['error' => $e->getMessage()], 500);
    }
}
```

**Nota**: Requiere campo `motivo` en el body. **PENDIENTE**: Actualizar frontend para enviar motivo.

#### **Envio::cancelarEnvio()** (YA EXISTÍA - línea 1044)
Implementación completa ya existe en el modelo.

---

## 🔧 Ajustes Pendientes

### **Frontend debe enviar motivo**

Actualizar `cancelarEnvio()` en JS:

```javascript
function cancelarEnvio(id) {
    // Preguntar motivo
    Swal.fire({
        title: 'Motivo de cancelación',
        input: 'textarea',
        inputLabel: 'Ingrese el motivo de la cancelación',
        inputPlaceholder: 'Ej: Cliente canceló pedido, error en productos, etc.',
        inputAttributes: {
            'aria-label': 'Motivo de cancelación'
        },
        showCancelButton: true,
        confirmButtonText: 'Confirmar cancelación',
        cancelButtonText: 'Volver',
        inputValidator: (value) => {
            if (!value || value.trim() === '') {
                return 'Debe ingresar un motivo'
            }
        }
    }).then((result) => {
        if (result.isConfirmed) {
            // Enviar cancelación con motivo
            $.ajax({
                url: API_URL + '/envios/' + id + '/cancelar',
                method: 'POST',
                contentType: 'application/json',
                data: JSON.stringify({
                    motivo: result.value
                }),
                success: function(response) {
                    if (response.success) {
                        Swal.fire('Éxito', 'Envío cancelado correctamente. Los productos han vuelto al stock.', 'success');
                        cargarEnvios();
                    } else {
                        Swal.fire('Error', response.message || 'Error al cancelar el envío', 'error');
                    }
                },
                error: function(xhr) {
                    const errorMsg = xhr.responseJSON && xhr.responseJSON.message 
                        ? xhr.responseJSON.message 
                        : 'Error al cancelar el envío';
                    Swal.fire('Error', errorMsg, 'error');
                }
            });
        }
    });
}
```

---

## 🧪 Testing

### **Prueba 1: Editar Envío**
1. Crear envío en estado NUEVO con 3 productos
2. Abrir detalle → Click "Editar Envío"
3. Verificar que formulario se carga con:
   - Destino correcto
   - 3 productos en tabla
   - Botón dice "Actualizar Envío"
4. Agregar 2 productos más
5. Guardar
6. Verificar envío actualizado con 5 productos

### **Prueba 2: Cancelar Envío**
1. Crear envío en estado NUEVO
2. Verificar productos consumieron stock
3. Abrir detalle → Click "Cancelar Envío"
4. Ingresar motivo: "Prueba de cancelación"
5. Confirmar
6. Verificar:
   - Estado cambió a CANCELADO
   - Productos volvieron al stock disponible
   - Botón "Cancelar" ya no visible

### **Prueba 3: Visibilidad Botones**
| Estado     | Editar  | Cancelar |
|-----------|---------|----------|
| NUEVO     | ✅ Sí   | ✅ Sí    |
| ENVIADO   | ❌ No   | ✅ Sí    |
| RECIBIDO  | ❌ No   | ❌ No    |
| CANCELADO | ❌ No   | ❌ No    |

---

## 📋 Archivos Modificados

### **Frontend**
- `envios.html`: 
  - Agregados botones `btnEditarEnvioModal` y `btnCancelarEnvioModal`
  - Actualizado cache: `?v=20251021_edicion`
  
- `js/envios_nuevo.js`:
  - Nueva función: `cargarEnvioParaEdicion()`
  - Nueva función: `confirmarCancelacionEnvio()`
  - Nueva función: `cancelarEnvio()`
  - Exportadas a window global
  - Actualizada versión: `v20251021_edicion`

### **Backend**
- `api/index.php`:
  - Nueva ruta: `POST /envios/{id}/cancelar`
  
- **NO MODIFICADOS** (evitando BOM):
  - `api/src/Controller/EnvioController.php` (ya tenía método)
  - `api/src/Model/Envio.php` (ya tenía método)

---

## ⚠️ Importante: Problema BOM

**NO se modificó `Envio.php`** para evitar introducir BOM UTF-8.

Los métodos necesarios **ya existían**:
- `EnvioController::cancelarEnvio()` (línea 228)
- `Envio::cancelarEnvio()` (línea 1044)

---

## 🚀 Siguiente Paso

**Actualizar frontend** para enviar campo `motivo` al cancelar (ver sección "Ajustes Pendientes").
