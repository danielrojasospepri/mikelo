---
applyTo: "envios.html,envios_nuevo.html,recepciones.html,api/src/Model/Envio.php,api/src/Controller/EnvioController.php,api/src/Model/Recepcion.php,api/src/Controller/RecepcionController.php,js/envios_nuevo.js"
---

# Skill: Envíos y Recepciones

## Circuito Completo

```
Depósito Central                       Sucursal
─────────────────                      ──────────────────
1. Crear Envío (envios_nuevo.html)
   ↓ POST /envios
   ↓ Items: estado ENVIADO
                                    2. Ver Envíos Pendientes
                                       ↓ GET /recepciones/envios-pendientes
                                       ↓ Muestra envíos en estado ENVIADO
                                    3. Confirmar Recepción
                                       ↓ POST /recepciones
                                       ↓ Stock sucursal se actualiza
                                       ↓ Items: estado RECIBIDO
                                    4. Archivar Envío (opcional)
                                       ↓ POST /recepciones/archivar/{id}
                                       ↓ El envío pasa a envios_archivados
                                    5. Desarchivar (si fue error)
                                       ↓ POST /recepciones/desarchivar/{id}
```

---

## Envíos — Backend

### Endpoints (`api/index.php` + `api/routes_fase2.php`)

```
GET  /envios                        → Listar envíos (filtros por estado/sucursal)
GET  /envios/{id}                   → Detalle de un envío con items
POST /envios                        → Crear nuevo envío
PUT  /envios/{id}/estado            → Cambiar estado (ENVIADO, CANCELADO)
GET  /envios/productos-disponibles  → Productos disponibles en depósito para enviar
```

### Crear Envío — Payload
```json
{
  "id_sucursal_destino": 3,
  "observaciones": "Envío semanal",
  "items": [
    { "id_movimientos_items": 1234, "cantidad": 5 },
    { "id_movimientos_items": 1235, "cantidad": 10 }
  ]
}
```

### Obtener Productos Disponibles
```
GET /envios/productos-disponibles?id_sucursal=3&id_producto=456
```

El endpoint ejecuta el **Algoritmo 3 pasos** del modelo `Envio.php`:

```php
// PASO 1 - Exacto: busca referencia exacta del contenedor que usa la sucursal
// PASO 2 - Superior: contenedor más grande si no hay exacto
// PASO 3 - Manual: todos los disponibles del producto
public function obtenerProductosDisponibles($idSucursal, $idProducto): array
```

### Traceabilidad al crear el envío

Cuando se crea un envío, se crea un nuevo `movimiento_item` que apunta al item original:

```php
// Nuevo item derivado del original
INSERT INTO movimientos_items (id_movimiento, id_producto, cantidad, id_movimientos_items_origen)
VALUES ($idMovimientoEnvio, $idProducto, $cantidad, $idItemOriginal)

// El item original queda "referenciado" → ya no aparece como disponible
```

---

## Envíos — Frontend (`envios_nuevo.html`, `js/envios_nuevo.js`)

### Buscar Producto por Código o Barcode

```javascript
// Por código manual
async function buscarProducto(codigo) {
    const resp = await MikeloAuth.fetch(`/envios/productos-disponibles?id_sucursal=${idSucursal}&codigo=${codigo}`);
    const data = await resp.json();
    mostrarProductosEncontrados(data.items);
}

// Por barcode escaneado
function handleBarcode(barcode) {
    const parsed = parseBarcode(barcode);
    if (!parsed) return;
    buscarProducto(parsed.codigo);
}
```

### Prevenir Duplicados en Lista de Envío

```javascript
// Al agregar un producto, verificar que no esté ya en la lista
function agregarProductoAlEnvio(item) {
    const yaAgregado = itemsEnvio.some(i => i.id_movimientos_items === item.id_movimientos_items);
    if (yaAgregado) {
        Swal.fire('Atención', 'Este item ya fue agregado al envío', 'warning');
        return;
    }
    itemsEnvio.push(item);
    actualizarTabla();
}
```

---

## Recepciones — Backend

### Endpoints (`api/routes_fase2.php`)

```
GET  /recepciones/envios-pendientes     → Envíos ENVIADO pendientes de recibir (filtrado por sucursal)
GET  /recepciones                       → Historial de recepciones confirmadas
POST /recepciones                       → Confirmar recepción de un envío
GET  /recepciones/envio/{idEnvio}       → Detalle de un envío para confirmar recepción
GET  /recepciones/archivados            → Envíos archivados
POST /recepciones/archivar/{idEnvio}    → Archivar un envío recibido
POST /recepciones/desarchivar/{idEnvio} → Restaurar envío de archivados
GET  /recepciones/{id}                  → Detalle de una recepción concreta
```

### Confirmar Recepción — Payload
```json
{
  "id_movimiento": 789,
  "items": [
    {
      "id_movimientos_items": 1234,
      "cantidad_recibida": 5,
      "observacion": ""
    },
    {
      "id_movimientos_items": 1235,
      "cantidad_recibida": 8,
      "observacion": "2 unidades dañadas"
    }
  ],
  "observaciones_generales": ""
}
```

**Al confirmar:**
1. Se crea registro en `recepciones` + `recepcion_items`
2. Los items confirman estado RECIBIDO en `estados_items_movimientos`
3. El stock en `stock_sucursal` se incrementa para cada producto

### Archivar Envío

```
POST /recepciones/archivar/{idEnvio}
```
- Mueve el envío a `envios_archivados`
- El envío desaparece de "Pendientes" y de "Historial" normal
- Sigue visible en tab "Archivados"

### Desarchivar Envío

```
POST /recepciones/desarchivar/{idEnvio}
```
- Elimina registro de `envios_archivados`
- El envío vuelve a ser visible en su estado anterior

---

## Recepciones — Frontend (`recepciones.html`)

### Estructura de Tabs

```html
<!-- Tab 1: Envíos Pendientes -->
<li class="nav-item"><a data-toggle="tab" href="#pendientes">Pendientes <span class="badge badge-warning" id="contadorPendientes">0</span></a></li>
<!-- Tab 2: Historial -->
<li class="nav-item"><a data-toggle="tab" href="#historial">Historial</a></li>
<!-- Tab 3: Archivados -->
<li class="nav-item"><a data-toggle="tab" href="#archivados">Archivados <span class="badge badge-secondary" id="contadorArchivados">0</span></a></li>
```

### Cargar Envíos Pendientes

```javascript
async function cargarPendientes() {
    const resp = await MikeloAuth.fetch('/recepciones/envios-pendientes');
    const data = await resp.json();
    // data.envios = [{ id, fecha_envio, sucursal_origen, items_count, ... }]
    renderizarPendientes(data.envios);
    document.getElementById('contadorPendientes').textContent = data.envios.length;
}
```

### Confirmar Recepción — Flujo

```javascript
async function abrirModalRecepcion(idMovimiento) {
    // 1. Cargar detalle del envío
    const resp = await MikeloAuth.fetch(`/recepciones/envio/${idMovimiento}`);
    const data = await resp.json();

    // 2. Pre-llenar modal con items enviados
    renderizarItemsRecepcion(data.items);

    // 3. Al confirmar
    $('#btnConfirmar').on('click', async () => {
        const itemsRecepcion = recogerDatosFormulario();
        await MikeloAuth.fetch('/recepciones', {
            method: 'POST',
            body: JSON.stringify({ id_movimiento: idMovimiento, items: itemsRecepcion })
        });
    });
}
```

### Archivados — Carga Lazy

```javascript
let archivadosCargados = false;

$('a[href="#archivados"]').on('shown.bs.tab', () => {
    if (!archivadosCargados) {
        cargarArchivados();
        archivadosCargados = true;
    }
});

async function cargarArchivados() {
    const resp = await MikeloAuth.fetch('/recepciones/archivados');
    const data = await resp.json();
    renderizarArchivados(data.envios);
}

async function desarchivarEnvio(idMovimiento) {
    const confirm = await Swal.fire({
        title: '¿Restaurar envío?',
        text: 'El envío volverá a estar visible en el historial.',
        icon: 'question', showCancelButton: true, confirmButtonText: 'Restaurar'
    });
    if (!confirm.isConfirmed) return;

    await MikeloAuth.fetch(`/recepciones/desarchivar/${idMovimiento}`, { method: 'POST' });
    archivadosCargados = false;   // Forzar reload
    cargarArchivados();
}
```

---

## Consultas SQL Clave

### Envíos pendientes de recepción para una sucursal

```sql
SELECT m.id, m.fecha, m.usuario,
       u_origen.nombre AS origen, u_destino.nombre AS destino,
       COUNT(mi.id) AS cantidad_items
FROM movimientos m
JOIN ubicaciones u_origen ON u_origen.id = m.id_ubicacion_origen
JOIN ubicaciones u_destino ON u_destino.id = m.id_ubicacion_destino
JOIN movimientos_items mi ON mi.id_movimiento = m.id
JOIN estados_items_movimientos eim ON eim.id_movimientos_items = mi.id
JOIN estados e ON e.id = eim.id_estados
WHERE m.tipo = 'ENVIO'
  AND m.id_ubicacion_destino = ?   -- id_sucursal del usuario
  AND e.nombre = 'ENVIADO'
  AND eim.fecha = (
      SELECT MAX(eim2.fecha) FROM estados_items_movimientos eim2
      WHERE eim2.id_movimientos_items = mi.id
  )
  AND m.id NOT IN (SELECT id_movimiento FROM envios_archivados)
GROUP BY m.id
```
