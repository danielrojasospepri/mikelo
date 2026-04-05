---
applyTo: "productos_abm.html,api/src/Controller/ProductoController.php,api/src/Model/Producto.php"
---

# Skill: ABM de Productos y Familias

## Visión General

`productos_abm.html` es la pantalla para gestionar el catálogo de productos y las familias de clasificación. Acceso: **solo Planta Admin** (`NivelRol::PLANTA_JEFE` ≤ 20 para crear/editar/eliminar, ≤ 25 para leer).

---

## Módulo Productos

### Estructura de Tabla

```sql
productos (
    id          INT AUTO_INCREMENT PK,
    codigo      VARCHAR(20) UNIQUE NOT NULL,  -- Código interno (ej: "1101")
    descripcion VARCHAR(200) NOT NULL,
    id_familia  INT,                          -- FK → familias
    peso_kg     DECIMAL(10,3),               -- Peso unitario
    activo      TINYINT(1) DEFAULT 1,
    fecha_creacion DATETIME DEFAULT NOW()
)

familias (
    id          INT AUTO_INCREMENT PK,
    nombre      VARCHAR(100) NOT NULL,
    descripcion TEXT,
    activo      TINYINT(1) DEFAULT 1
)
```

### Endpoints de Productos

```
GET    /productos/buscar          → Buscar productos (?q=texto&familia=N&activo=1)
POST   /productos                 → Crear producto [Planta Admin]
PUT    /productos/{id}            → Editar producto [Planta Admin]
DELETE /productos/{id}            → Desactivar (soft delete) [Solo Admin]
GET    /productos/{id}            → Detalle de un producto
```

### Endpoints de Familias

```
GET    /familias                  → Listar familias activas
POST   /familias                  → Crear familia [Planta Admin]
PUT    /familias/{id}             → Editar familia [Planta Admin]
DELETE /familias/{id}             → Desactivar familia [Solo Admin]
```

---

## Permisos por Rol

| Acción | Admin (≤10) | Planta Jefe (≤20) | Planta Operario (≤25) | Franquicia |
|--------|-------------|-------------------|-----------------------|------------|
| Leer productos | ✅ | ✅ | ✅ | ✅ (solo código + descripción) |
| Crear producto | ✅ | ✅ | ✅ | ❌ |
| Editar producto | ✅ | ✅ | ✅ | ❌ |
| Eliminar producto | ✅ | ❌ | ❌ | ❌ |
| Gestionar familias | ✅ | ✅ | ❌ | ❌ |

En el frontend, mostrar/ocultar controles según rol:
```javascript
const user = MikeloAuth.getUser();
if (!MikeloAuth.isPlanta()) {
    document.getElementById('btn-nuevo-producto').style.display = 'none';
}
if (!MikeloAuth.isPlantaJefe()) {
    // Ocultar gestión de familias
    document.getElementById('tab-familias').style.display = 'none';
}
```

---

## Producto — CRUD Frontend

### Crear / Editar Producto

```javascript
async function guardarProducto(form) {
    const datos = {
        codigo:      form.codigo.value.trim(),
        descripcion: form.descripcion.value.trim(),
        id_familia:  parseInt(form.familia.value) || null,
        peso_kg:     parseFloat(form.peso.value) || 0
    };

    const esEdicion = !!productoEditandoId;
    const url    = esEdicion ? `/productos/${productoEditandoId}` : '/productos';
    const method = esEdicion ? 'PUT' : 'POST';

    const resp = await MikeloAuth.fetch(url, {
        method,
        body: JSON.stringify(datos)
    });
    const data = await resp.json();

    if (data.success) {
        Swal.fire('Guardado', 'Producto guardado correctamente', 'success');
        await cargarProductos();
    } else {
        Swal.fire('Error', data.error || 'Error al guardar', 'error');
    }
}
```

### Buscar Productos (con DataTables)

```javascript
async function cargarProductos(filtros = {}) {
    const qs = new URLSearchParams(filtros).toString();
    const resp = await MikeloAuth.fetch(`/productos/buscar?${qs}`);
    const data = await resp.json();

    // Recargar DataTable
    if (tablaProductos) tablaProductos.destroy();
    tablaProductos = $('#tabla-productos').DataTable({
        data: data.productos,
        columns: [
            { data: 'codigo' },
            { data: 'descripcion' },
            { data: 'familia_nombre', defaultContent: '-' },
            { data: 'peso_kg', render: v => v ? `${v} kg` : '-' },
            { data: null, render: (_, __, row) => botonesAccion(row) }
        ],
        language: { url: '//cdn.datatables.net/plug-ins/1.13.6/i18n/es-AR.json' }
    });
}
```

### Desactivar (Soft Delete)

```javascript
async function desactivarProducto(id) {
    const conf = await Swal.fire({
        title: '¿Desactivar producto?',
        text: 'El producto no podrá ser usado en nuevas altas.',
        icon: 'warning', showCancelButton: true,
        confirmButtonText: 'Sí, desactivar', confirmButtonColor: '#d33'
    });
    if (!conf.isConfirmed) return;

    const resp = await MikeloAuth.fetch(`/productos/${id}`, { method: 'DELETE' });
    const data = await resp.json();
    if (data.success) await cargarProductos();
}
```

---

## Familias — CRUD Frontend

### Listar y Gestionar Familias

```javascript
async function cargarFamilias() {
    const resp = await MikeloAuth.fetch('/familias');
    const data = await resp.json();
    // data.familias = [{ id, nombre, descripcion, activo }]

    // Render en tab de familias
    renderizarFamilias(data.familias);

    // También poblar el select en el form de productos
    const select = document.getElementById('producto-familia');
    select.innerHTML = '<option value="">Sin familia</option>';
    data.familias.forEach(f => {
        select.innerHTML += `<option value="${f.id}">${f.nombre}</option>`;
    });
}

async function guardarFamilia(datos) {
    const url    = datos.id ? `/familias/${datos.id}` : '/familias';
    const method = datos.id ? 'PUT' : 'POST';
    await MikeloAuth.fetch(url, { method, body: JSON.stringify(datos) });
    await cargarFamilias();
}
```

---

## Producto.php — Modelo PHP

```php
namespace App\Model;

class Producto {
    private $db;
    public function __construct($db) { $this->db = $db; }

    // Busca con filtros opcionales: q (texto), id_familia, activo (1/0/todos)
    public function buscar(array $filtros = []): array

    // Crea producto (valida código único)
    public function crear(array $datos): int  // Retorna ID creado

    // Edita producto existente
    public function editar(int $id, array $datos): bool

    // Soft delete (marca inactivo)
    public function desactivar(int $id): bool

    // Listar familias activas
    public function listarFamilias(): array

    // Crear/editar familia
    public function crearFamilia(array $datos): int
    public function editarFamilia(int $id, array $datos): bool
}
```

---

## Validaciones

### Backend (siempre validar en servidor)

```php
// Código único al crear
$stmt = $db->prepare("SELECT id FROM productos WHERE codigo = ?");
$stmt->execute([$datos['codigo']]);
if ($stmt->fetch()) {
    return responseJson($response, ['success' => false, 'error' => 'Código ya existe'], 409);
}

// Peso no negativo
if (isset($datos['peso_kg']) && $datos['peso_kg'] < 0) {
    return responseJson($response, ['success' => false, 'error' => 'El peso no puede ser negativo'], 400);
}
```

### Frontend (UX)

```javascript
function validarFormProducto(form) {
    if (!form.codigo.value.trim()) { mostrarError('El código es obligatorio'); return false; }
    if (!form.descripcion.value.trim()) { mostrarError('La descripción es obligatoria'); return false; }
    const peso = parseFloat(form.peso.value);
    if (form.peso.value && (isNaN(peso) || peso < 0)) { mostrarError('Peso inválido'); return false; }
    return true;
}
```

---

## Consideraciones

- **Eliminar vs Desactivar:** Solo ADMIN puede eliminar físicamente. Los demás usan soft delete (campo `activo = 0`).
- **Familia obligatoria:** No es obligatorio asignar familia, se acepta `null`.
- **Productos en uso:** Si un producto tiene movimientos activos, no debe poder eliminarse físicamente, solo desactivarse.
- **Código de barras:** El `codigo` del producto se usa en los bytes 2-6 del formato de barcode (ver skill inventario).
