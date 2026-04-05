---
applyTo: "alta_deposito.html,index.html,stock_deposito.html,api/src/Model/Envio.php,api/src/Model/Movimiento.php,api/src/Controller/MovimientoController.php,js/envios_nuevo.js"
---

# Skill: Circuito de Inventario — Depósito Central

## Flujo Principal

```
Alta Depósito (alta_deposito.html)
   ↓  POST /movimientos
   ↓  Movimiento tipo ALTA → movimientos_items (estado: NUEVO)
   ↓
Inventario Depósito (index.html, stock_deposito.html)
   ↓  GET /stock-deposito/productos
   ↓
Crear Envío (envios_nuevo.html)
   ↓  GET /envios/productos-disponibles?id_sucursal=N
   ↓  Algoritmo 3 pasos → selección de items
   ↓  POST /envios → cambia estado a ENVIADO
```

---

## Alta de Productos en Depósito

**Archivo:** `alta_deposito.html` → endpoint `POST /movimientos`

Campos requeridos en el movimiento:
```json
{
  "tipo": "ALTA",
  "usuario": "nombre_usuario",
  "items": [
    {
      "id_producto": 123,
      "cantidad": 10,
      "id_contenedor": 2   // opcional
    }
  ]
}
```

**Patrón de contenedor en alta:**
- El contenedor se asigna en el momento del alta
- Se guarda en `movimientos_items.id_contenedor`
- El peso total = `producto.peso_kg × cantidad + contenedor.peso_kg` (si aplica)

**Código de barras en alta:**
```javascript
function parseBarcode(barcode) {
    if (barcode.length < 13) return null;
    const tipo   = parseInt(barcode.substring(0, 2));   // 20=unidades, 21=peso
    const codigo = parseInt(barcode.substring(2, 7)).toString();  // id producto
    const valor  = parseFloat(barcode.substring(7, 12)) / 1000;  // cantidad/peso
    return { tipo, codigo, valor };
}
```

---

## Estructura de Base de Datos — Inventario

```sql
-- Movimiento (agrupador)
movimientos
  id, tipo (ALTA/ENVIO/RECEPCION/BAJA), usuario, fecha, id_ubicacion_origen, id_ubicacion_destino

-- Items del movimiento
movimientos_items
  id, id_movimiento, id_producto, cantidad, id_contenedor,
  id_movimientos_items_origen  ← NULL en items originales, apunta al original en derivados

-- Historial de estados (nunca modificar directamente)
estados_items_movimientos
  id, id_movimientos_items, id_estados, usuario, fecha

-- Estados catálogo
estados: NUEVO(1), ENVIADO(2), RECIBIDO(3), CANCELADO(4)
```

---

## Patrón de Estados — REGLA CRÍTICA

**Nunca hacer UPDATE en el estado de un item.** Siempre INSERT en `estados_items_movimientos`:

```php
// ✅ Correcto
$stmt = $db->prepare("
    INSERT INTO estados_items_movimientos (id_movimientos_items, id_estados, usuario, fecha)
    VALUES (?, ?, ?, NOW())
");
$stmt->execute([$idItem, $idEstadoNuevo, $usuarioActual]);

// ❌ Incorrecto — no existe campo estado en movimientos_items
UPDATE movimientos_items SET estado = 'ENVIADO' WHERE id = ?
```

El estado actual de un item es el último registro en `estados_items_movimientos` ordenado por fecha.

---

## Disponibilidad para Envío

Un item está disponible si:
1. Es un item **original** (`id_movimientos_items_origen IS NULL`)
2. **No está referenciado** por ningún otro item (no fue incluido en un envío)

```sql
SELECT mi.id, mi.id_producto, mi.cantidad, p.descripcion
FROM movimientos_items mi
JOIN movimientos m ON m.id = mi.id_movimiento
JOIN productos p ON p.id = mi.id_producto
WHERE m.tipo = 'ALTA'
  AND mi.id_movimientos_items_origen IS NULL
  AND NOT EXISTS (
    SELECT 1 FROM movimientos_items mi2
    WHERE mi2.id_movimientos_items_origen = mi.id
  )
ORDER BY p.descripcion
```

---

## Algoritmo de Búsqueda 3 Pasos (`Envio.php → obtenerProductosDisponibles()`)

Busca productos disponibles para enviar a una sucursal destino dada una lista de productos pedidos.

**PASO 1 — Búsqueda Exacta:**
- Busca si hay items con la referencia de contenedor exacta que la sucursal suele recibir
- Si encuentra → devuelve esos items

**PASO 2 — Búsqueda Superior:**
- Si no hay del tamaño exacto, busca items con contenedor igual o superior
- Selecciona el contenedor más pequeño que cubra la necesidad

**PASO 3 — Búsqueda Manual:**
- Devuelve todos los items disponibles del producto sin filtrar contenedor
- El operario elige manualmente

Fórmula de disponibilidad (cuántas unidades disponibles de un item):
```sql
-- Un item tiene disponibilidad si su cantidad supera sus referencias ya asignadas
SELECT mi.id, mi.cantidad,
       COALESCE(SUM(mi2.cantidad), 0) AS asignado,
       mi.cantidad - COALESCE(SUM(mi2.cantidad), 0) AS disponible
FROM movimientos_items mi
LEFT JOIN movimientos_items mi2 ON mi2.id_movimientos_items_origen = mi.id
WHERE mi.id_movimientos_items_origen IS NULL
GROUP BY mi.id
HAVING disponible > 0
```

---

## Contenedores

```sql
-- Tabla contenedores
contenedores: id, nombre, peso_kg, codigo_barras

-- En alta: se asigna contenedor opcional al item
-- En envío: los contenedores son de solo lectura, se muestran como texto
-- Peso total del contenedor se suma al peso neto del producto al exportar
```

**En frontend, mostrar contenedor:**
```javascript
// Mostrar nombre o "-" si no tiene contenedor
const contenedorDisplay = item.contenedor_nombre || '-';
```

---

## Modelo Movimiento.php — Métodos Clave

```php
// Crear movimiento con sus items
public function crear($tipo, $usuario, $items, $idOrigen = 1, $idDestino = null): int

// Obtener items de un movimiento con estado actual
public function obtenerItems($idMovimiento): array

// Registrar nuevo estado en un item
public function cambiarEstadoItem($idItem, $idEstado, $usuario): void
```

---

## Error Frecuente: BOM en Envio.php

Después de editar `api/src/Model/Envio.php`, ejecutar:
```powershell
[System.IO.File]::WriteAllText(
    'c:\xampp7.4.30\htdocs\mikelo\api\src\Model\Envio.php',
    [System.IO.File]::ReadAllText('c:\xampp7.4.30\htdocs\mikelo\api\src\Model\Envio.php', [System.Text.Encoding]::UTF8),
    (New-Object System.Text.UTF8Encoding $false)
)
```
O usar `.\fix_bom.bat`. Luego verificar: `php -l api/src/Model/Envio.php`
