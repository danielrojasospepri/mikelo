# GUÍA: Módulo de Recepciones en Sucursales

## 🎯 Objetivo
Registrar recepción de envíos en sucursales, validando cantidades y generando stock disponible.

---

## 🔄 FLUJO DE RECEPCIÓN

```
Envío Creado (Central)
    ↓
Envío Enviado (Estado: ENVIADO)
    ↓
Sucursal recibe notificación
    ↓
Sucursal abre "Recepciones Pendientes"
    ↓
Escanea códigos o ingresa cantidades recibidas
    ↓
Compara: Cantidad Enviada vs Cantidad Recibida
    ↓
Si diferencia: Registra motivo (pérdida, daño, etc.)
    ↓
Confirma recepción (genera stock en sucursal)
    ↓
Genera alerta si hay discrepancias
```

---

## 📊 BASE DE DATOS

### Tablas Requeridas

```sql
-- Tabla para recepciones en sucursales
CREATE TABLE recepciones (
  id INT PRIMARY KEY AUTO_INCREMENT,
  id_envios INT NOT NULL,
  id_sucursal INT NOT NULL,
  cantidad_enviada DECIMAL(10,3) NOT NULL,
  cantidad_recibida DECIMAL(10,3),
  diferencia DECIMAL(10,3),
  estado ENUM('PENDIENTE', 'PARCIAL', 'COMPLETA', 'RECHAZADA') DEFAULT 'PENDIENTE',
  motivo_diferencia VARCHAR(500),
  usuario_id INT NOT NULL,
  fecha_envio DATETIME,
  fecha_recepcion DATETIME,
  FOREIGN KEY (id_envios) REFERENCES envios(id),
  FOREIGN KEY (id_sucursal) REFERENCES ubicaciones(id),
  FOREIGN KEY (usuario_id) REFERENCES usuarios(id)
);

-- Tabla para items recibidos (detalle por producto)
CREATE TABLE recepcion_items (
  id INT PRIMARY KEY AUTO_INCREMENT,
  id_recepciones INT NOT NULL,
  id_envios_items INT NOT NULL,
  id_productos INT NOT NULL,
  cantidad_enviada DECIMAL(10,3) NOT NULL,
  cantidad_recibida DECIMAL(10,3),
  diferencia DECIMAL(10,3),
  motivo VARCHAR(200), -- "Rotura", "Perdida en tránsito", "Daño", etc.
  FOREIGN KEY (id_recepciones) REFERENCES recepciones(id),
  FOREIGN KEY (id_envios_items) REFERENCES envios_items(id),
  FOREIGN KEY (id_productos) REFERENCES productos(id)
);

-- Modificar envios para incluir estado de recepción
ALTER TABLE envios 
ADD COLUMN estado_recepcion ENUM('ENVIADO', 'RECIBIDO_PARCIAL', 'RECIBIDO_COMPLETO') DEFAULT 'ENVIADO'
ADD COLUMN id_recepciones INT,
ADD FOREIGN KEY (id_recepciones) REFERENCES recepciones(id);

-- Alta stock en sucursal (resultado de recepción)
CREATE TABLE stock_sucursales (
  id INT PRIMARY KEY AUTO_INCREMENT,
  id_sucursal INT NOT NULL,
  id_productos INT NOT NULL,
  cantidad DECIMAL(10,3) DEFAULT 0,
  peso_kg DECIMAL(10,3) DEFAULT 0,
  ultimo_movimiento DATETIME DEFAULT NOW(),
  FOREIGN KEY (id_sucursal) REFERENCES ubicaciones(id),
  FOREIGN KEY (id_productos) REFERENCES productos(id),
  UNIQUE KEY (id_sucursal, id_productos)
);
```

---

## 📱 INTERFAZ: Recepciones Pendientes

### HTML (`recepciones.html`)

```html
<!DOCTYPE html>
<html>
<head>
    <title>Recepciones de Envíos</title>
    <link rel="stylesheet" href="css/adminlte.min.css">
    <link rel="stylesheet" href="css/custom.css">
</head>
<body>
<div class="wrapper">
    <div class="content-wrapper">
        <div class="content-header">
            <h1>Recepciones de Envíos</h1>
        </div>
        
        <section class="content">
            <!-- Filtros -->
            <div class="box">
                <div class="box-header">
                    <h3>Filtros</h3>
                </div>
                <div class="box-body">
                    <label>Estado:
                        <select id="filtro-estado" onchange="cargarRecepciones()">
                            <option value="">Todos</option>
                            <option value="PENDIENTE">Pendiente</option>
                            <option value="PARCIAL">Parcial</option>
                            <option value="COMPLETA">Completa</option>
                        </select>
                    </label>
                </div>
            </div>
            
            <!-- Tabla de recepciones -->
            <div class="box">
                <div class="box-header">
                    <h3>Envíos Pendientes de Recepción</h3>
                </div>
                <div class="box-body">
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th>Envío ID</th>
                                <th>Cantidad Enviada</th>
                                <th>Enviado</th>
                                <th>Estado</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody id="tabla-recepciones">
                        </tbody>
                    </table>
                </div>
            </div>
        </section>
    </div>
</div>

<!-- Modal para recibir envío -->
<div class="modal" id="modal-recibir">
    <div class="modal-content">
        <div class="modal-header">
            <h4>Recibir Envío #<span id="modal-envio-id"></span></h4>
            <button class="close" onclick="cerrarModal()">&times;</button>
        </div>
        
        <div class="modal-body">
            <!-- Detalles del envío -->
            <table class="table" id="tabla-detalle-envio">
                <thead>
                    <tr>
                        <th>Producto</th>
                        <th>Enviado</th>
                        <th>Recibido</th>
                        <th>Diferencia</th>
                        <th>Motivo</th>
                    </tr>
                </thead>
                <tbody id="detalles-recepcion">
                </tbody>
            </table>
            
            <!-- Resumen -->
            <div class="summary">
                <p>Total Enviado: <strong id="total-enviado">0</strong></p>
                <p>Total Recibido: <strong id="total-recibido">0</strong></p>
                <p>Diferencia: <strong id="total-diferencia">0</strong></p>
            </div>
        </div>
        
        <div class="modal-footer">
            <button class="btn btn-secondary" onclick="cerrarModal()">Cancelar</button>
            <button class="btn btn-success" onclick="guardarRecepcion()">Confirmar Recepción</button>
        </div>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="js/recepciones.js"></script>
</body>
</html>
```

### JavaScript (`js/recepciones.js`)

```javascript
let envioActual = null;
const sucursalId = localStorage.getItem('id_sucursal'); // Debe estar seteado al login

async function cargarRecepciones() {
    const estado = document.getElementById('filtro-estado').value;
    
    const response = await fetch(`/api/recepciones/pendientes?id_sucursal=${sucursalId}&estado=${estado}`);
    const data = await response.json();
    
    const tabla = document.getElementById('tabla-recepciones');
    tabla.innerHTML = '';
    
    data.recepciones.forEach(recepcion => {
        const fila = tabla.insertRow();
        
        fila.innerHTML = `
            <td>#${recepcion.id_envios}</td>
            <td>${recepcion.cantidad_enviada}</td>
            <td>${recepcion.fecha_envio}</td>
            <td><span class="badge badge-${getEstadoBadge(recepcion.estado)}">${recepcion.estado}</span></td>
            <td>
                <button class="btn btn-sm btn-primary" onclick="abrirRecepcion(${recepcion.id_envios})">Recibir</button>
                ${recepcion.estado === 'PENDIENTE' ? 
                    `<button class="btn btn-sm btn-danger" onclick="rechazarRecepcion(${recepcion.id})">Rechazar</button>` 
                    : ''}
            </td>
        `;
    });
}

async function abrirRecepcion(idEnvio) {
    envioActual = idEnvio;
    
    const response = await fetch(`/api/envios/${idEnvio}/detalles`);
    const data = await response.json();
    
    document.getElementById('modal-envio-id').textContent = idEnvio;
    
    const detalles = document.getElementById('detalles-recepcion');
    detalles.innerHTML = '';
    
    let totalEnviado = 0;
    let totalRecibido = 0;
    
    data.envio.envios_items.forEach(item => {
        totalEnviado += parseFloat(item.cantidad);
        
        const fila = detalles.insertRow();
        fila.innerHTML = `
            <td>${item.producto_descripcion}</td>
            <td>${item.cantidad}</td>
            <td>
                <input type="number" class="cantidad-recibida" 
                       data-id-item="${item.id_envios_items}"
                       value="${item.cantidad}"
                       step="0.001"
                       onchange="calcularDiferencia()">
            </td>
            <td class="diferencia">0</td>
            <td>
                <select class="motivo-diferencia" data-id-item="${item.id_envios_items}">
                    <option value="">Ninguno</option>
                    <option value="ROTURA">Rotura</option>
                    <option value="PERDIDA">Pérdida en tránsito</option>
                    <option value="DAÑO">Daño por humedad</option>
                    <option value="ERROR">Error en empaque</option>
                </select>
            </td>
        `;
    });
    
    document.getElementById('total-enviado').textContent = totalEnviado;
    document.getElementById('total-recibido').textContent = totalRecibido;
    
    document.getElementById('modal-recibir').style.display = 'block';
}

function calcularDiferencia() {
    let totalEnviado = 0;
    let totalRecibido = 0;
    
    document.querySelectorAll('input.cantidad-recibida').forEach(input => {
        const enviado = parseFloat(input.closest('tr').cells[1].textContent);
        const recibido = parseFloat(input.value) || 0;
        
        const diferencia = enviado - recibido;
        input.closest('tr').cells[3].textContent = diferencia.toFixed(3);
        
        totalEnviado += enviado;
        totalRecibido += recibido;
    });
    
    document.getElementById('total-recibido').textContent = totalRecibido.toFixed(3);
    document.getElementById('total-diferencia').textContent = (totalEnviado - totalRecibido).toFixed(3);
}

async function guardarRecepcion() {
    const items = [];
    
    document.querySelectorAll('input.cantidad-recibida').forEach(input => {
        const idItem = input.dataset.idItem;
        const cantidadRecibida = parseFloat(input.value);
        const motivo = input.parentElement.parentElement.querySelector('select.motivo-diferencia').value;
        
        items.push({
            id_envios_items: idItem,
            cantidad_recibida: cantidadRecibida,
            motivo_diferencia: motivo
        });
    });
    
    const response = await fetch('/api/recepciones/registrar', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            id_envios: envioActual,
            id_sucursal: sucursalId,
            items: items
        })
    });
    
    if (response.ok) {
        alert('Recepción registrada correctamente');
        cerrarModal();
        cargarRecepciones();
    } else {
        alert('Error al registrar recepción');
    }
}

function cerrarModal() {
    document.getElementById('modal-recibir').style.display = 'none';
}

function getEstadoBadge(estado) {
    const badges = {
        'PENDIENTE': 'warning',
        'PARCIAL': 'info',
        'COMPLETA': 'success',
        'RECHAZADA': 'danger'
    };
    return badges[estado] || 'secondary';
}

// Cargar al abrir página
cargarRecepciones();
```

---

## 🔌 API Endpoints

### Endpoint 1: Recepciones Pendientes

```php
// GET /api/recepciones/pendientes?id_sucursal=X&estado=Y
$app->get('/recepciones/pendientes', function ($request, $response) {
    try {
        $params = $request->getQueryParams();
        $recepcion = new Recepcion(getDB());
        
        $resultado = $recepcion->obtenerPendientes(
            $params['id_sucursal'],
            $params['estado'] ?? null
        );
        
        return responseJson($response, 200, ['recepciones' => $resultado]);
    } catch (\Exception $e) {
        return responseJson($response, 400, ['error' => $e->getMessage()], false);
    }
});
```

### Endpoint 2: Detalles de Envío

```php
// GET /api/envios/{id}/detalles
$app->get('/envios/{id}/detalles', function ($request, $response, $args) {
    try {
        $envio = new Envio(getDB());
        
        $resultado = $envio->obtenerConDetalles($args['id']);
        
        return responseJson($response, 200, ['envio' => $resultado]);
    } catch (\Exception $e) {
        return responseJson($response, 400, ['error' => $e->getMessage()], false);
    }
});
```

### Endpoint 3: Registrar Recepción

```php
// POST /api/recepciones/registrar
$app->post('/recepciones/registrar', function ($request, $response) {
    try {
        $data = $request->getParsedBody();
        $recepcion = new Recepcion(getDB());
        
        $resultado = $recepcion->registrar(
            $data['id_envios'],
            $data['id_sucursal'],
            $data['items'],
            $_SESSION['usuario_id']
        );
        
        return responseJson($response, 200, $resultado);
    } catch (\Exception $e) {
        return responseJson($response, 400, ['error' => $e->getMessage()], false);
    }
});
```

---

## 🎯 LÓGICA DE NEGOCIO: Recepción.php

```php
<?php
namespace App\Model;

class Recepcion {
    private $db;
    
    public function __construct($db) {
        $this->db = $db;
    }
    
    /**
     * Obtener recepciones pendientes para sucursal
     */
    public function obtenerPendientes($idSucursal, $estado = null) {
        $sql = "
            SELECT 
                r.id,
                r.id_envios,
                r.cantidad_enviada,
                r.cantidad_recibida,
                r.diferencia,
                r.estado,
                e.fecha_creacion as fecha_envio
            FROM recepciones r
            JOIN envios e ON r.id_envios = e.id
            WHERE r.id_sucursal = ?
            AND r.estado IN ('PENDIENTE', 'PARCIAL')
        ";
        
        $params = [$idSucursal];
        
        if ($estado) {
            $sql .= " AND r.estado = ?";
            $params[] = $estado;
        }
        
        $sql .= " ORDER BY e.fecha_creacion ASC";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }
    
    /**
     * Registrar recepción completa
     */
    public function registrar($idEnvios, $idSucursal, $items, $usuarioId) {
        try {
            $this->db->beginTransaction();
            
            // Validar que el envío existe y está en estado ENVIADO
            $envio = $this->obtenerEnvio($idEnvios);
            if (!$envio || $envio['estado'] !== 'ENVIADO') {
                throw new \Exception('Envío no válido para recepción');
            }
            
            // Crear registro de recepción
            $totalEnviado = 0;
            $totalRecibido = 0;
            $diferenciasExisten = false;
            
            // Procesar items recibidos
            $sqlRecepcion = "
                INSERT INTO recepcion_items 
                (id_recepciones, id_envios_items, id_productos, cantidad_enviada, cantidad_recibida, motivo)
                SELECT NULL, ?, ?, ?, ?, ?
                FROM envios_items ei
                WHERE ei.id = ?
            ";
            
            foreach ($items as $item) {
                $cantidadEnviada = $this->getCantidadEnviada($item['id_envios_items']);
                $cantidadRecibida = $item['cantidad_recibida'];
                $diferencia = $cantidadEnviada - $cantidadRecibida;
                
                if ($diferencia !== 0) {
                    $diferenciasExisten = true;
                }
                
                $totalEnviado += $cantidadEnviada;
                $totalRecibido += $cantidadRecibida;
            }
            
            // Insertar cabecera de recepción
            $estado = $totalRecibido === $totalEnviado ? 'COMPLETA' : 'PARCIAL';
            
            $sqlInsert = "
                INSERT INTO recepciones 
                (id_envios, id_sucursal, cantidad_enviada, cantidad_recibida, 
                 diferencia, estado, usuario_id, fecha_recepcion)
                VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
            ";
            
            $stmt = $this->db->prepare($sqlInsert);
            $stmt->execute([
                $idEnvios,
                $idSucursal,
                $totalEnviado,
                $totalRecibida,
                ($totalEnviado - $totalRecibida),
                $estado,
                $usuarioId
            ]);
            
            $idRecepcion = $this->db->lastInsertId();
            
            // Insertar items de recepción
            foreach ($items as $item) {
                $stmtItem = $this->db->prepare($sqlRecepcion);
                $stmtItem->execute([
                    $idRecepcion,
                    $item['id_envios_items'],
                    $item['id_productos'] ?? null,
                    $this->getCantidadEnviada($item['id_envios_items']),
                    $item['cantidad_recibida'],
                    $item['motivo_diferencia'] ?? null
                ]);
            }
            
            // Actualizar stock en sucursal
            $this->actualizarStockSucursal($idSucursal, $items);
            
            // Actualizar estado de envío
            $sqlUpdateEnvio = "
                UPDATE envios 
                SET estado_recepcion = ?, id_recepciones = ?
                WHERE id = ?
            ";
            $stmtUpdate = $this->db->prepare($sqlUpdateEnvio);
            $stmtUpdate->execute([$estado, $idRecepcion, $idEnvios]);
            
            // Registrar en movimientos
            $this->registrarMovimientoRecepcion($idSucursal, $idRecepcion, $items, $usuarioId);
            
            $this->db->commit();
            
            return [
                'success' => true,
                'id_recepcion' => $idRecepcion,
                'estado' => $estado,
                'mensaje' => $diferenciasExisten 
                    ? 'Recepción completada con discrepancias. Revisar motivos.'
                    : 'Recepción completada exitosamente.'
            ];
            
        } catch (\Exception $e) {
            $this->db->rollBack();
            throw $e;
        }
    }
    
    /**
     * Actualizar stock en sucursal
     */
    private function actualizarStockSucursal($idSucursal, $items) {
        foreach ($items as $item) {
            $cantidadRecibida = $item['cantidad_recibida'];
            
            $sql = "
                INSERT INTO stock_sucursales (id_sucursal, id_productos, cantidad)
                VALUES (?, ?, ?)
                ON DUPLICATE KEY UPDATE 
                    cantidad = cantidad + VALUES(cantidad),
                    ultimo_movimiento = NOW()
            ";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                $idSucursal,
                $item['id_productos'],
                $cantidadRecibida
            ]);
        }
    }
    
    /**
     * Registrar en movimientos para auditoría
     */
    private function registrarMovimientoRecepcion($idSucursal, $idRecepcion, $items, $usuarioId) {
        $sql = "
            INSERT INTO movimientos 
            (id_ubicaciones, tipo_movimiento, id_recepciones, usuario_id, fechaAlta)
            VALUES (?, 'RECEPCION', ?, ?, NOW())
        ";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$idSucursal, $idRecepcion, $usuarioId]);
    }
    
    private function getCantidadEnviada($idEnviosItems) {
        $sql = "SELECT cantidad FROM envios_items WHERE id = ?";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$idEnviosItems]);
        $result = $stmt->fetch(\PDO::FETCH_ASSOC);
        return $result['cantidad'] ?? 0;
    }
    
    private function obtenerEnvio($id) {
        $sql = "SELECT * FROM envios WHERE id = ?";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$id]);
        return $stmt->fetch(\PDO::FETCH_ASSOC);
    }
}
```

---

## ✅ CHECKLIST DE IMPLEMENTACIÓN

- [ ] Crear tabla `recepciones`
- [ ] Crear tabla `recepcion_items`
- [ ] Crear tabla `stock_sucursales`
- [ ] Modificar tabla `envios`
- [ ] Crear clase `Recepcion.php`
- [ ] Implementar endpoint GET `/api/recepciones/pendientes`
- [ ] Implementar endpoint GET `/api/envios/{id}/detalles`
- [ ] Implementar endpoint POST `/api/recepciones/registrar`
- [ ] Crear `recepciones.html`
- [ ] Crear `js/recepciones.js`
- [ ] Tests en navegador
- [ ] Tests con datos reales

