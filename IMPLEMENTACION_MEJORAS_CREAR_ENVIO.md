# IMPLEMENTACIÓN: MEJORAS AL CIRCUITO DE CREAR ENVÍO

## 1. MEJORAS A `api/src/Model/Envio.php`

### Cambio 1: Reemplazar método `crear()` con validaciones completas

**ANTES (línea 66):**
```php
public function crear($destino, $productos) {
    try {
        $this->db->beginTransaction();

        // 1. Crear el movimiento principal
        $stmt = $this->db->prepare("
            INSERT INTO movimientos (fechaAlta, id_ubicacion_origen, id_ubicacion_destino, usuario_alta)
            VALUES (NOW(), 1, ?, ?)
        ");
        $stmt->execute([$destino, $_SESSION['usuario'] ?? 'sistema']);
        $idMovimiento = $this->db->lastInsertId();

        // 2. Insertar los productos del envio
        foreach ($productos as $producto) {
            // ... resto del código
```

**DESPUÉS (con validaciones):**
```php
public function crear($destino, $productos) {
    try {
        $this->db->beginTransaction();

        // ========== VALIDACIONES PREVIAS ==========
        
        // 1. Validar que destino es número y existe
        $destino = (int)$destino;
        $stmt = $this->db->prepare("SELECT id FROM ubicaciones WHERE id = ?");
        $stmt->execute([$destino]);
        if (!$stmt->fetch()) {
            throw new \Exception("Ubicación de destino no encontrada (ID: {$destino})");
        }
        
        // 2. Validar que destino ≠ origen
        if ($destino == 1) {
            throw new \Exception("El destino no puede ser la ubicación de origen (Depósito Central)");
        }
        
        // 3. Validar que hay productos
        if (empty($productos)) {
            throw new \Exception("Debe incluir al menos un producto");
        }
        
        // 4. Validar cada producto ANTES de crear la transacción
        foreach ($productos as $idx => $producto) {
            // Validar estructura básica
            if (!isset($producto['id_productos'])) {
                throw new \Exception("Producto #{$idx}: Falta id_productos");
            }
            if (!isset($producto['cantidad'])) {
                throw new \Exception("Producto #{$idx}: Falta cantidad");
            }
            
            $idProd = (int)$producto['id_productos'];
            $cantidad = (float)$producto['cantidad'];
            $peso = isset($producto['peso']) ? (float)$producto['peso'] : 0;
            
            // Validar cantidad
            if ($cantidad <= 0) {
                throw new \Exception("Producto #{$idx}: Cantidad debe ser mayor a 0 (recibido: {$cantidad})");
            }
            
            // Validar peso
            if ($peso < 0) {
                throw new \Exception("Producto #{$idx}: Peso no puede ser negativo (recibido: {$peso})");
            }
            
            // Validar que producto existe
            $stmt = $this->db->prepare("SELECT id, descripcion FROM productos WHERE id = ?");
            $stmt->execute([$idProd]);
            $prodExiste = $stmt->fetch();
            if (!$prodExiste) {
                throw new \Exception("Producto #{$idx}: Producto no encontrado (ID: {$idProd})");
            }
        }
        
        // ========== CREAR MOVIMIENTO ==========
        
        // 5. Crear el movimiento principal
        $stmt = $this->db->prepare("
            INSERT INTO movimientos (fechaAlta, id_ubicacion_origen, id_ubicacion_destino, usuario_alta)
            VALUES (NOW(), 1, ?, ?)
        ");
        $stmt->execute([$destino, $_SESSION['usuario'] ?? 'sistema']);
        $idMovimiento = $this->db->lastInsertId();

        // ========== PROCESAR PRODUCTOS ==========

        // 6. Insertar los productos del envío
        foreach ($productos as $producto) {
            // Normalizar datos
            $idProd = (int)$producto['id_productos'];
            $cantidad = (float)$producto['cantidad'];
            $peso = isset($producto['peso']) ? (float)$producto['peso'] : 0;
            
            // Determinar si es edición (tiene referencia a item origen)
            if (isset($producto['id_movimientos_items_origen']) && !empty($producto['id_movimientos_items_origen'])) {
                $idOrigen = (int)$producto['id_movimientos_items_origen'];
                
                // VALIDACIÓN: Verificar que item origen existe y obtener disponibilidad
                $stmt = $this->db->prepare("
                    SELECT 
                        mi.cnt as cnt_original,
                        mi.id_contenedor,
                        (mi.cnt - IFNULL((
                            SELECT IFNULL(SUM(mi2.cnt), 0)
                            FROM movimientos_items mi2
                            WHERE mi2.id_movimientos_items_origen = mi.id
                        ), 0)) as cnt_disponible
                    FROM movimientos_items mi
                    WHERE mi.id = ?
                    FOR UPDATE
                ");
                $stmt->execute([$idOrigen]);
                $itemOrigen = $stmt->fetch(\PDO::FETCH_ASSOC);
                
                if (!$itemOrigen) {
                    throw new \Exception("Item origen no encontrado (ID: {$idOrigen})");
                }
                
                // VALIDACIÓN: Verificar cantidad disponible
                if ($cantidad > $itemOrigen['cnt_disponible']) {
                    throw new \Exception(
                        "Cantidad insuficiente. Solicitado: {$cantidad}, Disponible: {$itemOrigen['cnt_disponible']}"
                    );
                }
                
                $idContenedor = $itemOrigen['id_contenedor'];
                $idMovItemOrigen = $idOrigen;
            } else {
                // Alta nueva sin referencia
                $idContenedor = null;
                $idMovItemOrigen = null;
            }
            
            // Insertar el item del movimiento
            $stmt = $this->db->prepare("
                INSERT INTO movimientos_items (
                    id_movimientos, id_productos, cnt, cnt_peso,
                    id_movimientos_items_origen, id_contenedor
                ) VALUES (?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $idMovimiento,
                $idProd,
                $cantidad,
                $peso,
                $idMovItemOrigen,
                $idContenedor
            ]);
            $idMovimientoItem = $this->db->lastInsertId();

            // Registrar el estado inicial (NUEVO = 1)
            $stmt = $this->db->prepare("
                INSERT INTO estados_items_movimientos (
                    id_estados, id_movimientos_items, fecha_alta, usuario_alta
                ) VALUES (1, ?, NOW(), ?)
            ");
            $stmt->execute([$idMovimientoItem, $_SESSION['usuario'] ?? 'sistema']);
        }

        $this->db->commit();
        return $idMovimiento;
    } catch (\Exception $e) {
        $this->db->rollBack();
        // Registrar error en log
        error_log("Error en Envio.crear(): " . $e->getMessage());
        throw $e;
    }
}
```

---

## 2. MEJORAS A `api/src/Controller/EnvioController.php`

### Cambio 1: Mejorar validación en método `crear()`

**ANTES (línea 17):**
```php
public function crear(Request $request, Response $response) {
    $data = json_decode($request->getBody()->getContents(), true);
    
    if (!isset($data['destino']) || !isset($data['productos']) || empty($data['productos'])) {
        return responseJson($response, ['error' => 'Destino y productos son requeridos'], 400);
    }

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

**DESPUÉS (con validación mejorada):**
```php
public function crear(Request $request, Response $response) {
    try {
        $body = $request->getBody()->getContents();
        if (empty($body)) {
            return responseJson($response, ['error' => 'Body vacío'], 400);
        }
        
        $data = json_decode($body, true);
        
        // Validación de estructura JSON
        if (json_last_error() !== JSON_ERROR_NONE) {
            return responseJson($response, ['error' => 'JSON inválido: ' . json_last_error_msg()], 400);
        }
        
        // Validación de campos requeridos
        if (!isset($data['destino'])) {
            return responseJson($response, ['error' => 'Campo requerido: destino'], 400);
        }
        
        if (!isset($data['productos'])) {
            return responseJson($response, ['error' => 'Campo requerido: productos'], 400);
        }
        
        if (!is_array($data['productos']) || empty($data['productos'])) {
            return responseJson($response, ['error' => 'Productos debe ser un array no vacío'], 400);
        }
        
        // Validación de destino
        if (!is_numeric($data['destino'])) {
            return responseJson($response, ['error' => 'Destino debe ser un número'], 400);
        }
        
        try {
            $envioId = $this->envio->crear($data['destino'], $data['productos']);
            return responseJson($response, [
                'success' => true,
                'id' => $envioId,
                'mensaje' => 'Envío creado exitosamente'
            ], 201);
        } catch (\Exception $e) {
            // Error de negocio (validación fallida)
            return responseJson($response, ['error' => $e->getMessage()], 400);
        }
    } catch (\Exception $e) {
        // Error inesperado
        error_log("Error en EnvioController.crear(): " . $e->getMessage());
        return responseJson($response, ['error' => 'Error del servidor'], 500);
    }
}
```

---

## 3. MEJORAS A `js/envios_nuevo.js`

### Cambio 1: Normalizar y validar datos en frontend (línea ~477)

**ANTES:**
```javascript
const datosEnvio = {
    destino: destinoId,
    productos: productosEnEnvio.map(p => {
        const producto = {
            id_productos: p.id_productos || p.id_producto,
            cantidad: p.cantidad || p.cnt,
            peso: p.peso !== undefined ? p.peso : p.cnt_peso
        };
        // Solo para productos ya existentes en el envío (edición)
        if (p.id_movimiento_item) {
            producto.id_movimientos_items_origen = p.id_movimiento_item;
        }
        return producto;
    })
};
```

**DESPUÉS (normalizado y validado):**
```javascript
// Validar y normalizar antes de enviar
const productosNormalizados = productosEnEnvio.map((p, idx) => {
    // Validar id_productos
    const idProd = p.id_productos || p.id_producto;
    if (!idProd || isNaN(parseInt(idProd))) {
        throw new Error(`Producto ${idx}: ID de producto inválido`);
    }
    
    // Validar cantidad
    const cantidad = parseFloat(p.cantidad || p.cnt || 0);
    if (isNaN(cantidad) || cantidad <= 0) {
        throw new Error(`Producto ${idx}: Cantidad debe ser mayor a 0`);
    }
    
    // Validar peso
    const peso = parseFloat(p.peso !== undefined ? p.peso : p.cnt_peso || 0);
    if (isNaN(peso) || peso < 0) {
        throw new Error(`Producto ${idx}: Peso no puede ser negativo`);
    }
    
    // Construir producto normalizado
    const producto = {
        id_productos: parseInt(idProd),
        cantidad: cantidad,
        peso: peso
    };
    
    // Agregar origen si existe
    if (p.id_movimiento_item) {
        producto.id_movimientos_items_origen = parseInt(p.id_movimiento_item);
    }
    
    return producto;
});

const datosEnvio = {
    destino: parseInt(destinoId),
    productos: productosNormalizados
};
```

### Cambio 2: Mejorar manejo de errores en guardarEnvio() (línea ~477)

**ANTES:**
```javascript
function guardarEnvio() {
    const destinoId = $('#destinoEnvio').val();
    
    if (!destinoId) {
        Swal.fire('Error', 'Debe seleccionar un destino', 'error');
        return;
    }

    if (productosEnEnvio.length === 0) {
        Swal.fire('Error', 'Debe agregar al menos un producto', 'error');
        return;
    }

    // ... resto del código
```

**DESPUÉS (con try-catch para validación):**
```javascript
function guardarEnvio() {
    try {
        const destinoId = $('#destinoEnvio').val();
        
        if (!destinoId || isNaN(parseInt(destinoId))) {
            Swal.fire('Error', 'Debe seleccionar un destino válido', 'error');
            return;
        }

        if (productosEnEnvio.length === 0) {
            Swal.fire('Error', 'Debe agregar al menos un producto', 'error');
            return;
        }

        // Validar y normalizar productos (ver cambio anterior)
        const productosNormalizados = productosEnEnvio.map((p, idx) => {
            const idProd = p.id_productos || p.id_producto;
            if (!idProd || isNaN(parseInt(idProd))) {
                throw new Error(`Producto ${idx}: ID de producto inválido`);
            }
            const cantidad = parseFloat(p.cantidad || p.cnt || 0);
            if (isNaN(cantidad) || cantidad <= 0) {
                throw new Error(`Producto ${idx}: Cantidad debe ser mayor a 0`);
            }
            const peso = parseFloat(p.peso !== undefined ? p.peso : p.cnt_peso || 0);
            if (isNaN(peso) || peso < 0) {
                throw new Error(`Producto ${idx}: Peso no puede ser negativo`);
            }
            const producto = {
                id_productos: parseInt(idProd),
                cantidad: cantidad,
                peso: peso
            };
            if (p.id_movimiento_item) {
                producto.id_movimientos_items_origen = parseInt(p.id_movimiento_item);
            }
            return producto;
        });

        const datosEnvio = {
            destino: parseInt(destinoId),
            productos: productosNormalizados
        };

        console.log('Datos del envío a guardar:', datosEnvio);

        // Si estamos editando un envío existente, usar PUT y el endpoint de edición
        let url = 'api/envios';
        let method = 'POST';
        let exitoMsg = 'Envío creado correctamente';
        if (envioActual && envioActual.id) {
            url = `api/envios/${envioActual.id}`;
            method = 'PUT';
            exitoMsg = 'Envío editado correctamente';
        }

        fetch(url, {
            method: method,
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify(datosEnvio)
        })
        .then(response => {
            console.log('Response status:', response.status);
            if (!response.ok) {
                return response.json().then(err => {
                    throw new Error(err.error || `HTTP error! status: ${response.status}`);
                });
            }
            return response.json();
        })
        .then(data => {
            console.log('Respuesta del servidor:', data);
            if (data.success) {
                envioActual = { id: data.id || (envioActual && envioActual.id) };
                Swal.fire({
                    title: 'Éxito',
                    text: exitoMsg,
                    icon: 'success',
                    showCancelButton: true,
                    confirmButtonText: 'Confirmar Envío Ahora',
                    cancelButtonText: 'Volver a Lista',
                    reverseButtons: true
                }).then((result) => {
                    if (result.isConfirmed) {
                        confirmarEnvio(envioActual.id);
                    }
                    cerrarPanelNuevoEnvio();
                });
            } else {
                Swal.fire('Error', data.error || 'Error al guardar el envío', 'error');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            Swal.fire('Error', error.message || 'Error de conexión', 'error');
        });
    } catch (validationError) {
        // Errores de validación capturados antes de enviar
        Swal.fire('Error de validación', validationError.message, 'error');
    }
}
```

---

## 4. ARCHIVO DE CONFIGURACIÓN: `.env.example`

Crear archivo `.env` con:
```
ERROR_LOG_FILE=/var/log/mikelo/envios.log
DEBUG_MODE=false
VALIDATE_STRICT=true
```

---

## RESUMEN DE CAMBIOS

| Archivo | Cambios | Líneas |
|---------|---------|--------|
| `Envio.php` | +40 validaciones, +FOR UPDATE | 66-186 |
| `EnvioController.php` | +JSON validation, +error codes | 17-33 |
| `js/envios_nuevo.js` | +data normalization, +try-catch | 477-597 |

