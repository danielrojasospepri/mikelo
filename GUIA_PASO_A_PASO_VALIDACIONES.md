# 🔧 GUÍA PASO A PASO: IMPLEMENTAR VALIDACIONES CRÍTICAS

## 📍 LOCALIZACIÓN EXACTA DE CAMBIOS

### ARCHIVO 1: `api/src/Model/Envio.php`

**Ubicación:** Línea 66  
**Método:** `public function crear($destino, $productos)`

#### PASO 1: REEMPLAZAR ENTRADA DEL MÉTODO (línea 66-72)

**BUSCAR:**
```php
    public function crear($destino, $productos) {
        try {
            $this->db->beginTransaction();

            // 1. Crear el movimiento principal
            $stmt = $this->db->prepare("
```

**REEMPLAZAR CON:**
```php
    public function crear($destino, $productos) {
        try {
            // ========== VALIDACIONES PREVIAS ==========
            
            // 1. Convertir destino a entero y validar que existe
            $destino = (int)$destino;
            if ($destino <= 0) {
                throw new \Exception("Destino debe ser un número válido");
            }
            
            $stmt = $this->db->prepare("SELECT id FROM ubicaciones WHERE id = ?");
            $stmt->execute([$destino]);
            if (!$stmt->fetch()) {
                throw new \Exception("Ubicación de destino no encontrada (ID: {$destino})");
            }
            
            // 2. Validar que destino ≠ origen (depósito central)
            if ($destino == 1) {
                throw new \Exception("El destino no puede ser la ubicación de origen (Depósito Central)");
            }
            
            // 3. Validar que hay productos
            if (empty($productos) || !is_array($productos)) {
                throw new \Exception("Debe incluir al menos un producto en el envío");
            }
            
            // 4. Validación previa de cada producto
            foreach ($productos as $idx => $producto) {
                // Validar que tiene id_productos
                if (!isset($producto['id_productos']) || empty($producto['id_productos'])) {
                    throw new \Exception("Producto #{$idx}: Falta id_productos");
                }
                
                // Validar que tiene cantidad
                if (!isset($producto['cantidad'])) {
                    throw new \Exception("Producto #{$idx}: Falta cantidad");
                }
                
                $idProd = (int)$producto['id_productos'];
                $cantidad = (float)$producto['cantidad'];
                $peso = isset($producto['peso']) ? (float)$producto['peso'] : 0;
                
                // Validar cantidad > 0
                if ($cantidad <= 0) {
                    throw new \Exception("Producto #{$idx}: Cantidad debe ser mayor a 0 (recibido: {$cantidad})");
                }
                
                // Validar peso >= 0
                if ($peso < 0) {
                    throw new \Exception("Producto #{$idx}: Peso no puede ser negativo (recibido: {$peso})");
                }
                
                // Validar que producto existe
                $stmt = $this->db->prepare("SELECT id FROM productos WHERE id = ?");
                $stmt->execute([$idProd]);
                if (!$stmt->fetch()) {
                    throw new \Exception("Producto #{$idx}: Producto no encontrado (ID: {$idProd})");
                }
            }
            
            // ========== CREAR MOVIMIENTO ==========
            
            $this->db->beginTransaction();

            // 5. Crear el movimiento principal
            $stmt = $this->db->prepare("
```

#### PASO 2: MODIFICAR BLOQUE DE PRODUCTOS (línea ~85)

**BUSCAR:**
```php
        // 2. Insertar los productos del envio
        foreach ($productos as $producto) {
            if (isset($producto['id_movimientos_items_origen'])) {
                // EDICIÓN: Validar cantidad disponible ANTES de insertar
                $stmt = $this->db->prepare("
                    SELECT 
                        mi.cnt as cnt_original,
                        (mi.cnt - IFNULL((
                            SELECT IFNULL(SUM(mi2.cnt), 0)
                            FROM movimientos_items mi2
                            WHERE mi2.id_movimientos_items_origen = mi.id
                        ), 0)) as cnt_disponible
                    FROM movimientos_items mi
                    WHERE mi.id = ?
                ");
                $stmt->execute([$producto['id_movimientos_items_origen']]);
```

**REEMPLAZAR CON:**
```php
        // 6. Insertar los productos del envío
        foreach ($productos as $producto) {
            // Normalizar datos
            $idProd = (int)$producto['id_productos'];
            $cantidad = (float)$producto['cantidad'];
            $peso = isset($producto['peso']) ? (float)$producto['peso'] : 0;
            
            if (isset($producto['id_movimientos_items_origen']) && !empty($producto['id_movimientos_items_origen'])) {
                $idOrigen = (int)$producto['id_movimientos_items_origen'];
                
                // VALIDACIÓN: Verificar que item origen existe con FOR UPDATE (prevenir race condition)
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
```

**BUSCAR (continuación):**
```php
                $disponibilidad = $stmt->fetch(\PDO::FETCH_ASSOC);
                
                if (!$disponibilidad) {
                    throw new \Exception("Producto origen no encontrado: {$producto['id_movimientos_items_origen']}");
                }
                
                if ($producto['cantidad'] > $disponibilidad['cnt_disponible']) {
                    throw new \Exception(
                        "Cantidad solicitada ({$producto['cantidad']}) excede cantidad disponible ({$disponibilidad['cnt_disponible']})"
                    );
                }
                
                // Obtener el contenedor del item origen
                $stmt = $this->db->prepare("
                    SELECT id_contenedor FROM movimientos_items 
                    WHERE id = ?
                ");
                $stmt->execute([$producto['id_movimientos_items_origen']]);
                $itemOrigen = $stmt->fetch();
                $idContenedor = $itemOrigen ? $itemOrigen['id_contenedor'] : null;
                $idMovItemOrigen = $producto['id_movimientos_items_origen'];
```

**REEMPLAZAR CON:**
```php
                $itemOrigen = $stmt->fetch(\PDO::FETCH_ASSOC);
                
                if (!$itemOrigen) {
                    throw new \Exception("Item origen no encontrado (ID: {$idOrigen})");
                }
                
                // Validar cantidad disponible
                if ($cantidad > $itemOrigen['cnt_disponible']) {
                    throw new \Exception(
                        "Cantidad insuficiente. Solicitado: {$cantidad}, Disponible: {$itemOrigen['cnt_disponible']}"
                    );
                }
                
                $idContenedor = $itemOrigen['id_contenedor'];
                $idMovItemOrigen = $idOrigen;
```

**BUSCAR:**
```php
            } else {
                // ALTA NUEVA: No hay referencia, ni validación de stock ni contenedor
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
                isset($producto['id_productos']) ? $producto['id_productos'] : null,
                isset($producto['cantidad']) ? $producto['cantidad'] : null,
                isset($producto['peso']) ? $producto['peso'] : null,
                $idMovItemOrigen,
                $idContenedor
            ]);
```

**REEMPLAZAR CON:**
```php
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
```

#### PASO 3: MEJORAR MANEJO DE ERROR (línea ~160)

**BUSCAR:**
```php
        } catch (\Exception $e) {
            $this->db->rollBack();
            throw $e;
        }
    }
```

**REEMPLAZAR CON:**
```php
        } catch (\Exception $e) {
            $this->db->rollBack();
            error_log("Error en Envio.crear(): " . $e->getMessage());
            throw $e;
        }
    }
```

---

### ARCHIVO 2: `api/src/Controller/EnvioController.php`

**Ubicación:** Línea 17  
**Método:** `public function crear(Request $request, Response $response)`

**BUSCAR:**
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
                'mensaje' => 'EnvÃ­o creado exitosamente'
            ], 201);
        } catch (\Exception $e) {
            return responseJson($response, ['error' => $e->getMessage()], 500);
        }
    }
```

**REEMPLAZAR CON:**
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
            
            // Validación básica de destino
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
                // Error de validación de negocio (ya registrado en log)
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

### ARCHIVO 3: `js/envios_nuevo.js`

**Ubicación:** Línea 477  
**Función:** `guardarEnvio()`

**BUSCAR:**
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

**REEMPLAZAR CON:**
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

            // Validar y normalizar productos
            const productosNormalizados = productosEnEnvio.map((p, idx) => {
                const idProd = p.id_productos || p.id_producto;
                if (!idProd || isNaN(parseInt(idProd))) {
                    throw new Error(`Producto ${idx + 1}: ID de producto inválido`);
                }
                
                const cantidad = parseFloat(p.cantidad || p.cnt || 0);
                if (isNaN(cantidad) || cantidad <= 0) {
                    throw new Error(`Producto ${idx + 1}: Cantidad debe ser mayor a 0`);
                }
                
                const peso = parseFloat(p.peso !== undefined ? p.peso : p.cnt_peso || 0);
                if (isNaN(peso) || peso < 0) {
                    throw new Error(`Producto ${idx + 1}: Peso no puede ser negativo`);
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
```

**Después, BUSCAR:**
```javascript
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
```

**REEMPLAZAR CON:**
```javascript
            console.log('Datos del envío validados:', datosEnvio);

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
```

**BUSCAR:**
```javascript
        .catch(error => {
            console.error('Error:', error);
            Swal.fire('Error', error.message || 'Error de conexión', 'error');
        });
    }
```

**REEMPLAZAR CON:**
```javascript
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

## ✅ VERIFICACIÓN POST-IMPLEMENTACIÓN

Después de implementar, ejecutar:

```bash
# 1. Validar sintaxis PHP
php -l api/src/Model/Envio.php
php -l api/src/Controller/EnvioController.php

# 2. Validar que no hay errores de BD
php api/test_db.php

# 3. Crear un envío de prueba
# Acceder a http://localhost/mikelo/envios_nuevo.html
# Llenar formulario con datos válidos
# Click Guardar
# Verificar que aparezca el envío en lista
```

---

## 📊 CHECKLIST DE CAMBIOS

- [ ] Línea 66: Agregar validaciones previas en Envio.php
- [ ] Línea 85: Modificar bloque de procesamiento de productos
- [ ] Línea 160: Agregar logging en error handler
- [ ] Línea 17: Reemplazar método crear en Controller
- [ ] Línea 477: Reemplazar función guardarEnvio en JavaScript
- [ ] Validar sintaxis de todos los archivos
- [ ] Hacer prueba manual: Crear envío válido
- [ ] Hacer prueba manual: Intentar con datos inválidos
- [ ] Revisar logs de error

