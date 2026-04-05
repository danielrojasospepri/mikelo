---
applyTo: "api/src/Middleware/AuthMiddleware.php,api/src/Controller/AuthController.php,api/src/Model/Usuario.php,js/auth.js,login.html,api/routes_fase2.php"
---

# Skill: Autenticación JWT y Sistema de Roles

## Arquitectura General

El sistema usa **JWT (HS256)** emitido por PHP (`firebase/php-jwt`), almacenado en `localStorage` del browser y verificado en cada request por `AuthMiddleware`. Los roles son numéricos (menor = más privilegio).

---

## Roles y Niveles

```php
// api/src/Middleware/AuthMiddleware.php
class NivelRol {
    const ADMIN              = 10;  // Acceso total al sistema
    const PLANTA_JEFE        = 20;  // Supervisor/Jefe de planta
    const PLANTA_OPERARIO    = 25;  // Operario de planta
    const FRANQUICIA_ADMIN   = 30;  // Admin/Supervisor de sucursal
    const FRANQUICIA_EMPLEADO = 40; // Empleado de sucursal
}
```

**Regla de acceso:** Si `nivelRequerido` = 25, pueden acceder usuarios con `rol_nivel ≤ 25` (Admin, Planta Jefe, Planta Operario). Franquicia (30, 40) queda excluida.

---

## AuthMiddleware — Protegiendo Rutas

```php
// Con nivel requerido — nivel MÁXIMO que puede acceder
->add(new AuthMiddleware($db, NivelRol::PLANTA_OPERARIO))   // Solo planta (≤25)
->add(new AuthMiddleware($db, NivelRol::FRANQUICIA_ADMIN))  // Planta + Franquicia Admin (≤30)
->add(new AuthMiddleware($db, NivelRol::FRANQUICIA_EMPLEADO)) // Todos (≤40)
->add(new AuthMiddleware($db))                              // Sin restricción de nivel

// Acceso planta que también franquicia admin puede ver:
->add(new AuthMiddleware($db, NivelRol::FRANQUICIA_ADMIN))  // Franquicia admin ≤30 puede ver datos propios
```

**Obtener usuario en controller:**
```php
$usuario  = $request->getAttribute('user');
$idUsuario  = $usuario['id'];
$rolNivel   = $usuario['rol_nivel'];    // Número (10, 20, 25, 30, 40)
$idSucursal = $usuario['id_sucursal'] ?? null;  // null para planta
$sucursales = $usuario['sucursales'] ?? [];      // array de IDs permitidos
```

---

## MikeloAuth — API JavaScript

```javascript
// js/auth.js — objeto global MikeloAuth
// API_BASE se auto-detecta: '/' + window.location.pathname.split('/')[1] + '/api'

// ─── AUTENTICACIÓN ────────────────────────────────────────────────
await MikeloAuth.login(usuario, password);   // Guarda token + user en localStorage
await MikeloAuth.logout();                   // Limpia localStorage → login.html

// ─── GUARDS — usar en DOMContentLoaded ───────────────────────────
await MikeloAuth.requireAuth();              // Redirige a login si no autenticado
await MikeloAuth.requirePlanta();            // Solo planta (≤25); redirige franquicia → pedidos_sucursal.html
await MikeloAuth.requireFranquicia();        // Solo franquicia (≥30); redirige planta → index.html

// ─── CHECKS DE ROL (síncronos) ────────────────────────────────────
MikeloAuth.isPlanta()          // rol_nivel <= 25
MikeloAuth.isPlantaJefe()      // rol_nivel <= 20 (Supervisor / Admin)
MikeloAuth.isFranquicia()      // rol_nivel >= 30
MikeloAuth.isFranquiciaAdmin() // rol_nivel === 30 || rol_nivel <= 10

// ─── DATOS DE USUARIO ─────────────────────────────────────────────
const user = MikeloAuth.getUser();
// user = { id, nombre, usuario, rol_nivel, rol_nombre, sucursales: [{id, nombre, es_sucursal_principal}] }

const suc = MikeloAuth.getSucursalPrincipal();
// suc = { id, nombre } o null

// ─── FETCH AUTENTICADO ────────────────────────────────────────────
const resp = await MikeloAuth.fetch('/endpoint', {
    method: 'POST',
    body: JSON.stringify({ clave: 'valor' })
});
if (!resp) return;  // null = 401 → ya redirigió a login
const data = await resp.json();
```

---

## JWT Payload Structure

```json
{
  "sub": "1",
  "id": 1,
  "usuario": "jgarcia",
  "nombre": "Juan García",
  "email": "juan@heladeria.com",
  "rol_nivel": 20,
  "rol_nombre": "PLANTA_JEFE",
  "id_sucursal": null,
  "sucursales": [{"id": 3, "nombre": "Sucursal Norte", "es_sucursal_principal": true}],
  "iat": 1732900000,
  "exp": 1732986400
}
```

---

## Endpoints de Auth (`api/routes_fase2.php`)

Todas las rutas de auth son públicas (sin AuthMiddleware):

```
POST /auth/login           → Login; devuelve { token, usuario }
POST /auth/logout          → Invalida sesión (si aplica)
GET  /auth/validar         → Verifica token; devuelve { valido: true/false }
GET  /auth/me              → Info del usuario actual (requiere token)
POST /auth/cambiar-password → Cambiar contraseña propia
```

**Respuesta de login exitoso:**
```json
{
  "token": "eyJ...",
  "usuario": {
    "id": 1,
    "nombre": "Juan García",
    "usuario": "jgarcia",
    "rol_nivel": 20,
    "rol_nombre": "PLANTA_JEFE",
    "sucursales": [...]
  }
}
```

**Respuesta de error:**
```json
{ "error": true, "mensaje": "Credenciales inválidas" }
```

---

## Sidebar Visibility Pattern

Para prevenir "flash" del sidebar antes de aplicar filtros por rol:

```css
/* css/styles.css — ya existe */
.nav-sidebar { visibility: hidden; }
.nav-sidebar.sidebar-ready { visibility: visible; }
```

```javascript
// js/auth.js → updateUI() ya implementa esto al final:
document.querySelector('.nav-sidebar')?.classList.add('sidebar-ready');
```

**Todas las páginas deben incluir `css/styles.css` para que esto funcione.**

Items del sidebar se muestran/ocultan según rol con `data-require-*` atributos:
```html
<li class="nav-item" data-require-planta>  <!-- Solo visible para planta -->
<li class="nav-item" data-require-franquicia>  <!-- Solo visible para franquicia -->
<li class="nav-item" data-require-admin>  <!-- Solo visible para admin -->
```

---

## Inicialización Standard de una Página

```javascript
document.addEventListener('DOMContentLoaded', async () => {
    // 1. Guard de autenticación
    await MikeloAuth.requirePlanta();     // ó requireFranquicia() ó requireAuth()

    // 2. Obtener datos del usuario para personalizar UI
    const user = MikeloAuth.getUser();
    document.getElementById('usuario-nombre').textContent = user?.nombre || '';

    // 3. Cargar datos de la página
    await cargarDatos();
});
```

---

## Restricciones por Rol en Controller (Backend)

```php
public function listar(Request $request, Response $response): Response {
    $usuario  = $request->getAttribute('user');
    $rolNivel = $usuario['rol_nivel'];

    // Franquicia solo ve sus propias sucursales
    if ($rolNivel >= NivelRol::FRANQUICIA_ADMIN) {
        $idSucursal = $usuario['id_sucursal'];
        if (!$idSucursal) {
            return responseJson($response, ['success' => false, 'error' => 'Sin sucursal asignada'], 403);
        }
        $data = $this->model->listarPorSucursal($idSucursal);
    } else {
        // Planta ve todo
        $data = $this->model->listarTodos();
    }

    return responseJson($response, ['success' => true, 'data' => $data]);
}
```

---

## Seguridad: Validaciones Importantes

1. **Verificar propiedad de sucursal:** Si un usuario de franquicia hace una operación sobre una sucursal, verificar que esa sucursal esté en su lista `sucursales[]`.

2. **Password hashing:** Usar `password_hash($plain, PASSWORD_BCRYPT)` para guardar, `password_verify($plain, $hash)` para verificar.

3. **No exponer datos sensibles:** El JWT solo lleva lo necesario (no incluir password_hash ni datos privados).

4. **Tokens inválidos:** Si `validateSession()` falla (401), `MikeloAuth.fetch()` redirige automáticamente a `login.html`.

---

## Tablas de Base de Datos — Auth

```sql
usuarios (id, nombre, apellido, email, usuario, password_hash, id_rol, estado, ultimo_login)
roles    (id, codigo, nombre, nivel)
usuario_sucursales (id_usuario, id_sucursal, activo)
sesiones (id, id_usuario, token_hash, expire, activo)
```
