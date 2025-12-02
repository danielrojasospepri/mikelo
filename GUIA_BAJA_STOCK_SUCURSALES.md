# GUÍA: Módulo de Baja de Stock (Salidas de Sucursal)

## 🎯 Objetivo
Registrar productos vendidos/consumidos en sucursal, permitiendo dos métodos: rápido (etiquetas) y correcciones (manual).

---

## 📊 ANÁLISIS DE MÉTODOS

### Método A: Lectura de Etiquetas (RÁPIDO) ⭐

**Descripción:**
Escanear cada producto vendido al cierre del día o en tiempo real.

**Flujo:**
```
1. Usuario abre "Baja de Stock > Por Etiquetas"
2. Escanea código de barras (barcode gun o app)
3. Sistema interpreta tipo:
   - Tipo 20 (cantidad): Lee cantidad directamente
   - Tipo 21 (peso): Lee peso, calcula unidades
4. Decrementa stock automáticamente
5. Muestra resumen
6. Guarda en movimientos con tipo='BAJA'
```

**Ventajas:**
- ✅ Rápido: ~5 segundos por artículo
- ✅ Automático: menos errores
- ✅ Rastreable: quién, cuándo, qué
- ✅ Ideal para: cierre de caja, inventarios frecuentes

**Desventajas:**
- ❌ Requiere etiquetas correctas
- ❌ No permite correcciones en el momento
- ❌ Si se pierde etiqueta, se pierde registro

**Mejor para:** Operación diaria, cierre de turno

---

### Método B: Ajuste Manual (CORRECCIÓN) 📋

**Descripción:**
Comparar stock teórico vs stock físico (conteo manual) y registrar diferencias.

**Flujo:**
```
1. Usuario abre "Baja de Stock > Ajuste Manual"
2. Carga tabla con:
   - Producto
   - Stock Teórico (en sistema)
   - Stock Real (conteo manual)
3. Calcula automáticamente: Diferencia = Teórico - Real
4. Si diferencia > 0: Crea baja (venta/pérdida)
5. Si diferencia < 0: Crea entrada (error anterior)
6. Usuario puede editar comentario: "Rotura", "Caducidad", etc.
7. Guarda en movimientos con motivo
```

**Ventajas:**
- ✅ Corrige discrepancias
- ✅ Flexible: permite notas sobre por qué
- ✅ Ideal para: reconciliación, correcciones
- ✅ Documentado: por qué cambió el stock

**Desventajas:**
- ❌ Lento: requiere conteo manual
- ❌ Propenso a errores: conteo humano
- ❌ Requiere cierre de ventas

**Mejor para:** Auditoría, correcciones puntuales, semanal/mensual

---

## 🛠️ IMPLEMENTACIÓN TÉCNICA

### Estructura de Base de Datos

```sql
-- Tabla para registrar bajas
CREATE TABLE bajas_stock (
  id INT PRIMARY KEY AUTO_INCREMENT,
  id_sucursal INT NOT NULL,
  id_productos INT NOT NULL,
  cantidad DECIMAL(10,3) NOT NULL,
  peso_kg DECIMAL(10,3),
  tipo_baja ENUM('VENTA', 'ROTURA', 'CADUCIDAD', 'AJUSTE', 'DEVOLUCION') DEFAULT 'VENTA',
  metodo ENUM('ETIQUETA', 'MANUAL') NOT NULL,
  codigo_etiqueta VARCHAR(50),
  usuario_id INT NOT NULL,
  fecha DATETIME DEFAULT NOW(),
  observaciones VARCHAR(500),
  FOREIGN KEY (id_sucursal) REFERENCES ubicaciones(id),
  FOREIGN KEY (id_productos) REFERENCES productos(id),
  FOREIGN KEY (usuario_id) REFERENCES usuarios(id)
);

-- Modificar movimientos para incluir baja
ALTER TABLE movimientos 
ADD COLUMN tipo_movimiento ENUM('ALTA', 'ENVIO', 'RECEPCION', 'BAJA') DEFAULT 'ENVIO';
ALTER TABLE movimientos 
ADD COLUMN id_baja_stock INT,
ADD FOREIGN KEY (id_baja_stock) REFERENCES bajas_stock(id);
```

---

## 📱 INTERFAZ A: Por Etiquetas

### HTML (`baja_stock_etiquetas.html`)

```html
<div class="container">
  <h1>Baja de Stock - Lectura de Etiquetas</h1>
  
  <div class="input-group">
    <label>Sucursal: <span id="sucursal-actual">María</span></label>
  </div>
  
  <!-- Scanner input (invisible, recibe focus) -->
  <input type="hidden" id="scanner-input" autofocus>
  
  <!-- Tabla de bajas -->
  <table class="table">
    <thead>
      <tr>
        <th>Código</th>
        <th>Producto</th>
        <th>Cantidad/Peso</th>
        <th>Hora</th>
        <th>Acción</th>
      </tr>
    </thead>
    <tbody id="tabla-bajas">
      <!-- Se llena dinámicamente -->
    </tbody>
  </table>
  
  <!-- Totales -->
  <div class="summary">
    <h3>Resumen de Baja</h3>
    <p>Total productos: <strong id="total-items">0</strong></p>
    <p>Total líneas: <strong id="total-lineas">0</strong></p>
    <button class="btn btn-success" onclick="finalizarBaja()">Finalizar y Guardar</button>
  </div>
</div>
```

### JavaScript (`js/baja_stock_etiquetas.js`)

```javascript
let bajas = [];

document.getElementById('scanner-input').addEventListener('keypress', async (e) => {
    if (e.key === 'Enter') {
        const barcode = e.target.value;
        e.target.value = '';
        
        try {
            // Parsear barcode
            const parsedBarcode = parseBarcode(barcode);
            
            if (!parsedBarcode) {
                alert('Código inválido');
                return;
            }
            
            // Buscar producto
            const producto = await fetch(`/api/productos/${parsedBarcode.codigo}`).then(r => r.json());
            
            if (!producto) {
                alert('Producto no encontrado');
                return;
            }
            
            // Crear registro de baja
            const baja = {
                id_productos: producto.id,
                codigo: producto.codigo,
                descripcion: producto.descripcion,
                cantidad: parsedBarcode.tipo === 20 ? parsedBarcode.valor : null,
                peso: parsedBarcode.tipo === 21 ? parsedBarcode.valor : null,
                codigo_etiqueta: barcode,
                metodo: 'ETIQUETA',
                hora: new Date().toLocaleTimeString()
            };
            
            // Calcular unidades si es peso
            if (baja.peso) {
                // Asumir promedio: 1 unidad = 0.3 kg (ajustable por producto)
                baja.cantidad = Math.round(baja.peso / 0.3);
            }
            
            bajas.push(baja);
            agregarFilaTabla(baja);
            actualizarTotales();
            
        } catch (error) {
            console.error('Error:', error);
            alert('Error procesando código');
        }
    }
});

function parseBarcode(barcode) {
    // Formato: [tipo(2)][codigo(5)][valor(5)]
    if (barcode.length !== 13) return null;
    
    const tipo = parseInt(barcode.substring(0, 2));
    const codigo = parseInt(barcode.substring(2, 7));
    const valor = parseInt(barcode.substring(7, 12)) / 100; // Últimos 2 dígitos = decimales
    
    if (tipo === 20 || tipo === 21) {
        return { tipo, codigo, valor };
    }
    return null;
}

function agregarFilaTabla(baja) {
    const tabla = document.getElementById('tabla-bajas');
    const fila = tabla.insertRow();
    
    fila.innerHTML = `
        <td>${baja.codigo}</td>
        <td>${baja.descripcion}</td>
        <td>${baja.cantidad ? baja.cantidad + ' un.' : baja.peso + ' kg'}</td>
        <td>${baja.hora}</td>
        <td><button onclick="eliminarBaja(${bajas.length - 1})">✕</button></td>
    `;
}

function actualizarTotales() {
    document.getElementById('total-items').textContent = bajas.length;
    const total = bajas.reduce((sum, b) => sum + (b.cantidad || 0), 0);
    document.getElementById('total-lineas').textContent = total;
}

async function finalizarBaja() {
    if (bajas.length === 0) {
        alert('No hay bajas registradas');
        return;
    }
    
    const response = await fetch('/api/stock/baja-por-etiqueta', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            id_sucursal: suculralId,
            bajas: bajas
        })
    });
    
    if (response.ok) {
        alert('Baja registrada correctamente');
        bajas = [];
        document.getElementById('tabla-bajas').innerHTML = '';
        actualizarTotales();
    } else {
        alert('Error al guardar baja');
    }
}
```

---

## 📋 INTERFAZ B: Ajuste Manual

### HTML (`baja_stock_ajuste.html`)

```html
<div class="container">
  <h1>Baja de Stock - Ajuste Manual</h1>
  
  <div class="form-group">
    <label>Sucursal: <span id="sucursal">María</span></label>
    <label>Fecha: <input type="date" id="fecha-ajuste" required></label>
    <label>Motivo: 
      <select id="motivo-ajuste">
        <option value="VENTA">Venta (conteo físico)</option>
        <option value="ROTURA">Rotura/Daño</option>
        <option value="CADUCIDAD">Caducidad</option>
        <option value="DEVOLUCION">Devolución</option>
        <option value="AJUSTE">Ajuste por error anterior</option>
      </select>
    </label>
  </div>
  
  <table class="table editable">
    <thead>
      <tr>
        <th>Producto</th>
        <th>Stock Teórico</th>
        <th>Stock Real</th>
        <th>Diferencia</th>
        <th>Observaciones</th>
      </tr>
    </thead>
    <tbody id="tabla-ajuste">
      <!-- Se llena dinámicamente -->
    </tbody>
  </table>
  
  <div class="summary">
    <p>Total bajas a registrar: <strong id="total-bajas">0</strong></p>
    <button class="btn btn-primary" onclick="cargarStockActual()">Cargar Stock Actual</button>
    <button class="btn btn-success" onclick="guardarAjustes()">Guardar Ajustes</button>
  </div>
</div>
```

### JavaScript (`js/baja_stock_ajuste.js`)

```javascript
let ajustes = [];

async function cargarStockActual() {
    const response = await fetch('/api/stock/actual?id_sucursal=' + sucursalId);
    const stocks = await response.json();
    
    const tabla = document.getElementById('tabla-ajuste');
    tabla.innerHTML = '';
    
    stocks.forEach(stock => {
        const fila = tabla.insertRow();
        
        fila.innerHTML = `
            <td>${stock.descripcion}</td>
            <td>${stock.cnt}</td>
            <td><input type="number" value="${stock.cnt}" class="stock-real" 
                       data-id-producto="${stock.id_productos}"
                       data-teorico="${stock.cnt}"
                       onchange="calcularDiferencia(this)"></td>
            <td class="diferencia">0</td>
            <td><input type="text" placeholder="ej: Conteo físico" class="observaciones"></td>
        `;
    });
}

function calcularDiferencia(input) {
    const teorico = parseInt(input.dataset.teorico);
    const real = parseInt(input.value);
    const diferencia = teorico - real;
    
    input.parentElement.parentElement.cells[3].textContent = diferencia;
    
    actualizarTotalBajas();
}

function actualizarTotalBajas() {
    let total = 0;
    document.querySelectorAll('input.stock-real').forEach(input => {
        const teorico = parseInt(input.dataset.teorico);
        const real = parseInt(input.value);
        total += Math.max(0, teorico - real);
    });
    document.getElementById('total-bajas').textContent = total;
}

async function guardarAjustes() {
    const ajustesData = [];
    
    document.querySelectorAll('#tabla-ajuste tr').forEach(fila => {
        const input = fila.querySelector('input.stock-real');
        const teorico = parseInt(input.dataset.teorico);
        const real = parseInt(input.value);
        
        if (teorico !== real) {
            ajustesData.push({
                id_productos: input.dataset.idProducto,
                cantidad: teorico - real,
                tipo_baja: document.getElementById('motivo-ajuste').value,
                observaciones: fila.querySelector('input.observaciones').value,
                metodo: 'MANUAL'
            });
        }
    });
    
    if (ajustesData.length === 0) {
        alert('No hay diferencias para registrar');
        return;
    }
    
    const response = await fetch('/api/stock/ajuste-manual', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            id_sucursal: sucursalId,
            fecha: document.getElementById('fecha-ajuste').value,
            motivo: document.getElementById('motivo-ajuste').value,
            ajustes: ajustesData
        })
    });
    
    if (response.ok) {
        alert('Ajustes guardados correctamente');
        // Recargar
        document.getElementById('fecha-ajuste').value = '';
        await cargarStockActual();
    }
}
```

---

## 🔌 API Endpoints

### Endpoint 1: Baja por Etiqueta

```php
// POST /api/stock/baja-por-etiqueta
$app->post('/stock/baja-por-etiqueta', function ($request, $response) {
    try {
        $data = $request->getParsedBody();
        $stock = new Stock(getDB());
        
        $resultado = $stock->registrarBajasPorEtiqueta(
            $data['id_sucursal'],
            $data['bajas'],
            $_SESSION['usuario_id']
        );
        
        return responseJson($response, 200, $resultado);
    } catch (\Exception $e) {
        return responseJson($response, 400, ['error' => $e->getMessage()], false);
    }
});
```

### Endpoint 2: Ajuste Manual

```php
// POST /api/stock/ajuste-manual
$app->post('/stock/ajuste-manual', function ($request, $response) {
    try {
        $data = $request->getParsedBody();
        $stock = new Stock(getDB());
        
        $resultado = $stock->registrarAjustesManual(
            $data['id_sucursal'],
            $data['ajustes'],
            $_SESSION['usuario_id'],
            $data['motivo'] ?? 'AJUSTE'
        );
        
        return responseJson($response, 200, $resultado);
    } catch (\Exception $e) {
        return responseJson($response, 400, ['error' => $e->getMessage()], false);
    }
});
```

### Endpoint 3: Stock Actual

```php
// GET /api/stock/actual?id_sucursal=X
$app->get('/stock/actual', function ($request, $response) {
    try {
        $id_sucursal = $request->getQueryParams()['id_sucursal'];
        $stock = new Stock(getDB());
        
        $resultado = $stock->obtenerStockActual($id_sucursal);
        
        return responseJson($response, 200, ['stock' => $resultado]);
    } catch (\Exception $e) {
        return responseJson($response, 400, ['error' => $e->getMessage()], false);
    }
});
```

---

## 🎯 FLUJO DE USO RECOMENDADO

### Por Sucursal (Heladería)

**Opción 1: Solo Etiquetas (Rápido)**
- Cierre de turno: 10 minutos
- Escanear cada venta del día
- Guardar automáticamente
- Auditoría fácil

**Opción 2: Solo Manual (Flexible)**
- Fin de día: Conteo físico
- Registrar diferencias
- Permite correcciones
- Más lento pero más flexible

**Opción 3: Híbrida (RECOMENDADA)**
- Día normal: Lectura de etiquetas
- Fin de semana: Ajuste manual (reconciliación)
- Mensual: Auditoria física completa
- Combina velocidad + precisión

---

## ✅ CHECKLIST DE IMPLEMENTACIÓN

**Fase 1: Lectura de Etiquetas**
- [ ] Crear tabla `bajas_stock`
- [ ] Implementar endpoint POST `/api/stock/baja-por-etiqueta`
- [ ] Crear interfaz `baja_stock_etiquetas.html`
- [ ] Crear `js/baja_stock_etiquetas.js`
- [ ] Validar parseo de barcodes
- [ ] Tests en navegador

**Fase 2: Ajuste Manual**
- [ ] Implementar endpoint POST `/api/stock/ajuste-manual`
- [ ] Crear interfaz `baja_stock_ajuste.html`
- [ ] Crear `js/baja_stock_ajuste.js`
- [ ] Endpoint GET `/api/stock/actual`
- [ ] Tests con datos reales

**Fase 3: Integración**
- [ ] Vincular con movimientos
- [ ] Reportes de bajas
- [ ] Auditoría de cambios
- [ ] Alertas de discrepancias

