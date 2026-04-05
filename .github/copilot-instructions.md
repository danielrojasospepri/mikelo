# Mikelo — Sistema de Inventario de Helados

## Visión General

Mikelo gestiona el inventario de una cadena de heladerías: depósito central → envíos → sucursales/franquicias. Cubre el ciclo completo: alta de productos, envíos, recepciones, stock en sucursal, pedidos de reposición, ABM de usuarios y exportaciones.

- **Stack:** PHP 7.4 + Slim Framework 4, MySQL, AdminLTE 3.2, jQuery, Bootstrap 4
- **Timezone:** `America/Argentina/Buenos_Aires` (configurado en `api/index.php`)
- **Ruta local:** `c:\xampp7.4.30\htdocs\mikelo\`
- **Estado:** Fase 1 completa en producción. Fase 2 implementada (auth JWT, pedidos, recepciones, stock sucursal, ABM usuarios).

---

## Estructura de Archivos

```
mikelo/
├── api/
│   ├── index.php              ← Entry point Slim + rutas Fase 1
│   ├── routes_fase2.php       ← Rutas Fase 2 (auth, pedidos, recepciones, stock, usuarios)
│   ├── comun.php              ← Helpers: getDB(), responseJson()
│   └── src/
│       ├── Controller/        ← Un controller por módulo
│       ├── Model/             ← Modelos PDO
│       └── Middleware/        ← AuthMiddleware.php + clase NivelRol
├── js/
│   └── auth.js                ← Objeto MikeloAuth (login, roles, fetch autenticado)
├── css/
│   └── styles.css             ← Estilos compartidos — INCLUIR EN TODOS LOS HTMLs
├── .github/
│   ├── copilot-instructions.md
│   └── instructions/          ← Skills por dominio
├── alta_deposito.html         ← Alta de productos en depósito [Planta]
├── index.html                 ← Movimientos del depósito [Planta]
├── envios.html                ← Listado de envíos [Planta]
├── envios_nuevo.html          ← Crear envío [Planta]
├── stock_deposito.html        ← Stock en depósito central [Planta]
├── panel_produccion.html      ← Tablero de producción [Planta]
├── panel_faltantes.html       ← Panel de faltantes [Planta]
├── pedidos_sucursal.html      ← Crear/ver pedidos [Franquicia]
├── recepciones.html           ← Confirmar recepciones de envíos [Franquicia]
├── baja_stock.html            ← Baja de stock (venta/merma) [Franquicia]
├── carga_inicial_sucursal.html← Inventario inicial de sucursal [Franquicia Admin]
├── stock_sucursal.html        ← Ver stock de sucursal [Franquicia]
├── stock_minimo.html          ← Configurar alertas stock mínimo [Franquicia Admin]
├── productos_abm.html         ← ABM Productos y Familias [Planta Admin]
├── usuarios.html              ← ABM Usuarios [Admin/Supervisor]
└── login.html                 ← Login JWT [Público]
```

---

## Sistema de Autenticación y Roles

### Niveles de Rol (numérico — menor = más privilegio)

```php
class NivelRol {
    const ADMIN              = 10;  // Acceso total al sistema
    const PLANTA_JEFE        = 20;  // Supervisor/Jefe de planta (empleado del dueño)
    const PLANTA_OPERARIO    = 25;  // Operario de planta
    const FRANQUICIA_ADMIN   = 30;  // Admin/Supervisor de sucursal (propietario franquicia)
    const FRANQUICIA_EMPLEADO = 40; // Empleado de sucursal
}
```

El middleware `AuthMiddleware` recibe `nivelRequerido` = nivel MÁXIMO que puede acceder:
```php
->add(new AuthMiddleware($db, NivelRol::PLANTA_OPERARIO))   // Solo planta (≤25)
->add(new AuthMiddleware($db, NivelRol::FRANQUICIA_ADMIN))  // Planta + Franquicia Admin (≤30)
->add(new AuthMiddleware($db))                              // Cualquier autenticado (≤40)
```

El usuario autenticado se obtiene en el controller via:
```php
$usuario = $request->getAttribute('user');
$idSucursal = $usuario['id_sucursal'] ?? null;
$rolNivel   = $usuario['rol_nivel'] ?? 99;
```

### MikeloAuth (js/auth.js)

JWT en `localStorage` (`mikelo_token`). API base auto-detectada desde URL.

```javascript
// Proteger páginas
await MikeloAuth.requireAuth();           // Cualquier usuario autenticado
await MikeloAuth.requirePlanta();         // Solo planta (redirige franquicia → pedidos_sucursal.html)
await MikeloAuth.requireFranquicia();     // Solo franquicia (redirige planta → index.html)

// Checks de rol
MikeloAuth.isPlantaJefe()    // rol_nivel <= 20
MikeloAuth.isPlanta()        // rol_nivel <= 25
MikeloAuth.isFranquicia()    // rol_nivel >= 30
MikeloAuth.isFranquiciaAdmin() // rol_nivel === 30 || rol_nivel <= 10

// Fetch autenticado (agrega Bearer token automáticamente)
const resp = await MikeloAuth.fetch('/endpoint', { method: 'POST', body: JSON.stringify(data) });

// Datos del usuario
const user = MikeloAuth.getUser();   // { nombre, rol_nivel, sucursales, ... }
const suc  = MikeloAuth.getSucursalPrincipal();
```

### Sidebar Visibility Pattern (previene flash)
```css
/* styles.css — YA EXISTE */
.nav-sidebar { visibility: hidden; }
.nav-sidebar.sidebar-ready { visibility: visible; }
```
```javascript
// Al final de updateUI() en auth.js — ya implementado
document.querySelector('.nav-sidebar')?.classList.add('sidebar-ready');
```
**Todas las páginas deben incluir `<link rel="stylesheet" href="css/styles.css">`.**

---

## Base de Datos

### Tablas Core (Fase 1)

| Tabla | Propósito |
|-------|-----------|
| `productos` | Catálogo (código, descripción, familia, peso_kg) |
| `movimientos` | Agrupador de operaciones (tipo) |
| `movimientos_items` | Items individuales (id_producto, cantidad, contenedor) |
| `estados_items_movimientos` | Historial de estados por item |
| `estados` | Catálogo: NUEVO, ENVIADO, RECIBIDO, CANCELADO |
| `contenedores` | Tipos de contenedor (nombre, peso_kg) |
| `ubicaciones` | Depósito central (ID=1) + sucursales |
| `familias` | Clasificación de productos |
| `tipo_producto` | Tipos de producto |

### Tablas Fase 2

| Tabla | Propósito |
|-------|-----------|
| `usuarios` | Usuarios con password_hash bcrypt |
| `roles` | Roles con nivel numérico |
| `usuario_sucursales` | N:N usuario-sucursal |
| `sesiones` | Sesiones PHP (complemento JWT) |
| `pedidos` | Pedidos de sucursales al depósito |
| `pedido_items` | Items de cada pedido |
| `pedido_envio` | Relación N:N pedido-envío |
| `recepciones` | Confirmaciones de envíos en sucursal |
| `recepcion_items` | Detalle de recepción por producto |
| `stock_sucursal` | Stock actual por sucursal y producto |
| `stock_sucursal_movimientos` | Historial de movimientos de stock |
| `stock_minimo` | Alertas de stock mínimo por sucursal/producto |
| `envios_archivados` | Envíos archivados en recepciones |

### Patrón de Estados

**NUNCA actualizar estado directamente.** Siempre insertar en `estados_items_movimientos`:
```sql
INSERT INTO estados_items_movimientos (id_movimientos_items, id_estados, usuario, fecha)
VALUES (?, ?, ?, NOW())
```

### Traceabilidad de Items (disponibilidad para envío)
```sql
-- Items originales no referenciados = disponibles para envío
WHERE mi.id_movimientos_items_origen IS NULL
  AND NOT EXISTS (
    SELECT 1 FROM movimientos_items mi2
    WHERE mi2.id_movimientos_items_origen = mi.id
  )
```

---

## Patrones de API

### Orden de Rutas — CRÍTICO

**Rutas estáticas SIEMPRE antes que rutas con parámetros:**
```php
// ✅ Correcto
$app->get('/pedidos/pendientes', ...);           // estática
$app->get('/pedidos/productos-disponibles', ...); // estática
$app->get('/pedidos/{id}', ...);                  // parámetro AL FINAL
```

### Helper DB y Respuestas
```php
$db = getDB();  // PDO con FETCH_ASSOC y excepciones activadas

return responseJson($response, ['success' => true, 'data' => $data]);
return responseJson($response, ['success' => false, 'error' => 'Mensaje'], 400);
```

### Estructura de Controlador (patrón estándar)
```php
namespace App\Controller;

class MiController {
    private $db;
    public function __construct($db) { $this->db = $db; }

    public function listar(Request $request, Response $response): Response {
        $model   = new MiModel($this->db);
        $usuario = $request->getAttribute('user');
        $data    = $model->listar($usuario['id_sucursal'] ?? null);
        return responseJson($response, ['success' => true, 'data' => $data]);
    }
}
```

---

## Patrones de Frontend

### Incluir en Cada Página HTML
```html
<link rel="stylesheet" href="css/styles.css">
<!-- En body: -->
<script src="js/auth.js"></script>
```

### Inicialización Estándar de Página
```javascript
document.addEventListener('DOMContentLoaded', async () => {
    await MikeloAuth.requirePlanta();   // o requireFranquicia() o requireAuth()
    // Luego cargar datos...
});
```

### Notificaciones al Usuario
- **SweetAlert2** para confirmaciones y errores
- **Toastr / AdminLTE alerts** para mensajes informativos breves

### Barcode — Formato (13 dígitos)
```
Chars 0-1  : Tipo  → 20 = por unidades | 21 = por peso
Chars 2-6  : Código producto (quitar ceros a la izquierda)
Chars 7-12 : Valor × 1000 (cantidad o peso en gramos)
```
```javascript
function parseBarcode(barcode) {
    if (barcode.length < 13) return null;
    const tipo   = parseInt(barcode.substring(0, 2));
    const codigo = parseInt(barcode.substring(2, 7)).toString();
    const valor  = parseFloat(barcode.substring(7, 12)) / 1000;
    return { tipo, codigo, valor };
}
```

---

## Algoritmo de Búsqueda 3 Pasos (Disponibilidad para Envíos)

`api/src/Model/Envio.php → obtenerProductosDisponibles()`:

1. **PASO 1 — Exacta:** Busca referencia de la sucursal destino exacta
2. **PASO 2 — Superior:** Busca contenedor más grande si no hay del tamaño exacto
3. **PASO 3 — Manual:** Retorna todos los disponibles para selección manual

Fórmula de disponibilidad: `cnt > SUM(referencias_asignadas)`

---

## Endpoints API Resumen

### Auth (`/auth/*`) — Público
```
POST /auth/login          POST /auth/logout
GET  /auth/validar        GET  /auth/me
POST /auth/cambiar-password
```

### Pedidos (`/pedidos/*`) — Requiere auth
```
GET  /pedidos                    GET  /pedidos/pendientes     [Planta]
GET  /pedidos/productos-disponibles  GET  /pedidos/contadores
GET  /pedidos/demanda-agregada   GET  /pedidos/{id}
POST /pedidos [Franquicia]       PUT  /pedidos/{id}/enviar [Planta]
PUT  /pedidos/{id}/anular        PUT  /pedidos/{id}/recibir
```

### Recepciones (`/recepciones/*`)
```
GET  /recepciones/envios-pendientes    GET  /recepciones
POST /recepciones                      GET  /recepciones/archivados
GET  /recepciones/envio/{idEnvio}      POST /recepciones/archivar/{idEnvio}
POST /recepciones/desarchivar/{idEnvio} GET /recepciones/{id}
```

### Stock Sucursal (`/stock-sucursal/*`)
```
GET  /stock-sucursal           GET  /stock-sucursal/buscar
GET  /stock-sucursal/resumen   GET  /stock-sucursal/historial
GET  /stock-sucursal/todas [Planta]    POST /stock-sucursal/baja
POST /stock-sucursal/ajuste [Admin]    GET|POST /stock-sucursal/carga-inicial [Admin]
```

### Stock Mínimo (`/stock-minimo/*`)
```
GET  /stock-minimo             GET  /stock-minimo/faltantes
GET  /stock-minimo/resumen     POST /stock-minimo/multiple
GET|PUT|DELETE /stock-minimo/{id}
```

### Fase 1 (`api/index.php`)
```
GET  /ubicaciones      GET  /estados        GET  /contenedores
GET  /productos/buscar POST /movimientos    GET|POST /envios/*
GET  /stock-deposito/* 
```

---

## Issue Crítico: BOM UTF-8 en Envio.php

`api/src/Model/Envio.php` DEBE ser **UTF-8 sin BOM**. VS Code puede reintroducir BOM al editar, causando:
```
Fatal error: Namespace declaration statement has to be the very first statement
```

**Después de CADA edición a Envio.php, ejecutar:**
```powershell
[System.IO.File]::WriteAllText('c:\xampp7.4.30\htdocs\mikelo\api\src\Model\Envio.php', [System.IO.File]::ReadAllText('c:\xampp7.4.30\htdocs\mikelo\api\src\Model\Envio.php', [System.Text.Encoding]::UTF8), (New-Object System.Text.UTF8Encoding $false))
```
O usar `fix_bom.bat`. Verificar: `php -l api/src/Model/Envio.php`

---

## Comandos Útiles
```powershell
php -l api/src/Model/Envio.php    # Verificar sintaxis PHP
php api/test_db.php               # Test conexión DB
cd api ; composer install         # Instalar dependencias
.\fix_bom.bat                     # Reparar BOM en Envio.php
```

## Librerías de Terceros
- **AdminLTE 3.2** — UI (Bootstrap 4)
- **SweetAlert2** — Notificaciones
- **Select2** — Dropdowns con búsqueda
- **DataTables** — Tablas con paginación y filtros
- **Html5QrcodeScanner** — Escaneo de códigos de barras
- **mPDF** — Exportación PDF
- **PHPSpreadsheet** — Exportación Excel