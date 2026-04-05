---
applyTo: "stock_sucursal.html,baja_stock.html,carga_inicial_sucursal.html,stock_minimo.html,api/src/Model/StockSucursal.php,api/src/Controller/StockSucursalController.php,api/src/Model/StockMinimo.php,api/src/Controller/StockMinimoController.php"
---

# Skill: Stock de Sucursal

## Módulos Involucrados

| HTML | Acceso | Descripción |
|------|--------|-------------|
| `stock_sucursal.html` | Franquicia | Ver stock actual |
| `baja_stock.html` | Franquicia | Registrar ventas / mermas |
| `carga_inicial_sucursal.html` | Franquicia Admin | Inventario inicial |
| `stock_minimo.html` | Franquicia Admin | Configurar alertas de mínimo |

---

## Tablas de Base de Datos

```sql
-- Stock actual por sucursal y producto
stock_sucursal (
    id, id_sucursal, id_producto,
    cantidad,           -- unidades actuales en stock
    fecha_actualizacion
)

-- Historial de movimientos de stock (audit trail)
stock_sucursal_movimientos (
    id, id_sucursal, id_producto, id_usuario,
    tipo_movimiento,    -- RECEPCION, BAJA_VENTA, BAJA_MERMA, AJUSTE, CARGA_INICIAL
    cantidad_antes, cantidad_cambio, cantidad_despues,
    observacion, fecha
)

-- Stock mínimo configurado
stock_minimo (
    id, id_sucursal, id_producto,
    cantidad_minima,
    alerta_activa,      -- true si stock_actual < cantidad_minima
    fecha_configuracion, fecha_actualizacion
)
```

---

## API Endpoints

### Stock Sucursal (`/stock-sucursal/*`)

```
GET  /stock-sucursal              → Stock de la sucursal del usuario autenticado
GET  /stock-sucursal/buscar       → Buscar producto en el stock (?q=texto)
GET  /stock-sucursal/resumen      → Resumen: total productos, alertas, etc.
GET  /stock-sucursal/historial    → Historial de movimientos
GET  /stock-sucursal/todas        → Stock de TODAS las sucursales [solo Planta]
POST /stock-sucursal/baja         → Registrar baja (venta o merma)
POST /stock-sucursal/ajuste       → Ajuste manual de inventario [Franquicia Admin]
GET  /stock-sucursal/carga-inicial → Productos disponibles para carga inicial [Franquicia Admin]
POST /stock-sucursal/carga-inicial → Guardar inventario inicial [Franquicia Admin]
```

### Stock Mínimo (`/stock-minimo/*`)

```
GET  /stock-minimo                → Lista stock mínimo configurado
GET  /stock-minimo/faltantes      → Productos cuyo stock < mínimo configurado
GET  /stock-minimo/resumen        → Resumen alertas (cuántos productos faltan)
POST /stock-minimo/multiple       → Configurar mínimos en lote [Franquicia Admin]
GET|PUT|DELETE /stock-minimo/{id} → CRUD individual
```

---

## Baja de Stock (`baja_stock.html`)

### Dos métodos de baja

**Método A — Por Etiqueta (barcode):**
```javascript
// El scanner lee el código y registra automáticamente
input.addEventListener('keypress', async (e) => {
    if (e.key !== 'Enter') return;
    const barcode = e.target.value.trim();
    e.target.value = '';
    const parsed = parseBarcode(barcode);
    if (!parsed) { mostrarError('Código inválido'); return; }
    agregarBaja({ codigo: parsed.codigo, cantidad: parsed.valor, tipo: 'ETIQUETA' });
});
```

**Método B — Manual (ajuste):**
```javascript
// Se muestra tabla con stock actual y se permite editar cantidad real
// La diferencia (teórico - real) genera una baja
```

### Payload para registrar baja
```json
{
  "id_sucursal": 3,
  "tipo": "BAJA_VENTA",
  "observacion": "Cierre de turno",
  "items": [
    { "id_producto": 456, "cantidad": 5, "metodo": "ETIQUETA" },
    { "id_producto": 789, "cantidad": 2, "metodo": "MANUAL" }
  ]
}
```

### Tipos de baja (`tipo_movimiento`)
```
BAJA_VENTA    → Producto vendido normalmente
BAJA_MERMA    → Rotura, caducidad, pérdida
AJUSTE        → Corrección de inventario
CARGA_INICIAL → Inventario inicial
RECEPCION     → Generado automáticamente al recibir un envío
```

---

## Stock Sucursal — Frontend (`stock_sucursal.html`)

### Inicialización
```javascript
document.addEventListener('DOMContentLoaded', async () => {
    await MikeloAuth.requireFranquicia();  // Guardia: solo franquicia

    const user = MikeloAuth.getUser();
    const sucursal = MikeloAuth.getSucursalPrincipal();

    await cargarStockSucursal(sucursal.id);
    await cargarAlertas();
});
```

### Cargar Stock
```javascript
async function cargarStockSucursal() {
    const resp = await MikeloAuth.fetch('/stock-sucursal');
    const data = await resp.json();
    // data.stock = [{ id_producto, codigo, descripcion, familia, cantidad, alerta_minimo }]
    renderizarTabla(data.stock);
}
```

### Resaltar alertas de stock mínimo
```javascript
// En la tabla, marcar filas con stock bajo
function renderizarFila(item) {
    const alertaClass = item.alerta_minimo ? 'table-danger' : '';
    return `<tr class="${alertaClass}">
        <td>${item.codigo}</td>
        <td>${item.descripcion}</td>
        <td>${item.cantidad}</td>
        <td>${item.cantidad_minima || '-'}</td>
    </tr>`;
}
```

---

## Carga Inicial de Stock (`carga_inicial_sucursal.html`)

Solo disponible para `FRANQUICIA_ADMIN` (nivel ≤ 30). Se usa para cargar el inventario de apertura de una nueva sucursal o después de un inventario físico.

```javascript
// 1. Cargar productos disponibles para la sucursal
const resp = await MikeloAuth.fetch('/stock-sucursal/carga-inicial');
const data = await resp.json();
// data.productos = lista de todos los productos activos

// 2. Usuario ingresa cantidades en la tabla

// 3. Guardar inventario inicial
await MikeloAuth.fetch('/stock-sucursal/carga-inicial', {
    method: 'POST',
    body: JSON.stringify({
        items: [
            { id_producto: 123, cantidad: 50 },
            { id_producto: 456, cantidad: 20 }
        ]
    })
});
```

---

## Stock Mínimo (`stock_minimo.html`)

### Configurar mínimos en lote
```javascript
// Obtener configuración actual
const resp = await MikeloAuth.fetch('/stock-minimo');
const data = await resp.json();
// data.minimos = [{ id, id_producto, codigo, descripcion, cantidad_minima, alerta_activa }]

// Guardar mínimos editados
await MikeloAuth.fetch('/stock-minimo/multiple', {
    method: 'POST',
    body: JSON.stringify({
        minimos: [
            { id_producto: 123, cantidad_minima: 10 },
            { id_producto: 456, cantidad_minima: 5 }
        ]
    })
});
```

### Panel de Faltantes
```javascript
// Productos con stock < mínimo configurado
const resp = await MikeloAuth.fetch('/stock-minimo/faltantes');
const data = await resp.json();
// data.faltantes = [{ id_producto, descripcion, cantidad_actual, cantidad_minima, diferencia }]
```

---

## StockSucursal.php — Métodos Clave

```php
// Obtener stock actual de la sucursal
public function obtenerStock(int $idSucursal): array

// Registrar baja de stock (actualiza stock_sucursal + inserta en stock_sucursal_movimientos)
public function registrarBaja(int $idSucursal, int $idProducto, float $cantidad, 
                               string $tipo, string $usuario, string $obs = ''): bool

// Ajuste de inventario (puede sumar o restar)
public function registrarAjuste(int $idSucursal, int $idProducto, float $cantidadNueva,
                                 string $usuario, string $motivo): bool

// Incrementar stock al recibir una recepción
public function incrementarStock(int $idSucursal, int $idProducto, float $cantidad,
                                  string $usuario): bool
```

---

## Consideraciones de Seguridad

1. **Aislamiento por sucursal:** `GET /stock-sucursal` filtra automáticamente por `id_sucursal` del usuario autenticado. Un usuario de franquicia **nunca** puede ver el stock de otra sucursal.

2. **Ajustes requieren FRANQUICIA_ADMIN:** `POST /stock-sucursal/ajuste` usa `AuthMiddleware($db, NivelRol::FRANQUICIA_ADMIN)`.

3. **Bajas requieren solo FRANQUICIA_EMPLEADO:** `POST /stock-sucursal/baja` permite nivel ≤ 40 (todos los de sucursal).

4. **Vista global solo para Planta:** `GET /stock-sucursal/todas` requiere `NivelRol::PLANTA_OPERARIO` (≤25).
