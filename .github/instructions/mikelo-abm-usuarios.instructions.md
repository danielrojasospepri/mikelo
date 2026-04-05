---
applyTo: "usuarios.html,api/src/Model/Usuario.php,api/src/Controller/AuthController.php,api/routes_fase2.php"
---

# Skill: ABM de Usuarios

## Visión General

`usuarios.html` permite gestionar usuarios del sistema. Las acciones disponibles dependen del rol del usuario que opera. **No hay un `UsuariosController.php` separado** — la gestión de usuarios se hace a través de `AuthController` o de endpoints específicos en `routes_fase2.php`.

---

## Reglas de Creación por Rol

```
ADMIN (≤10):
  Puede crear: Admin, Planta Jefe, Planta Operario, Franquicia Admin, Franquicia Empleado
  Asignar a: cualquier sucursal

PLANTA_JEFE (≤20):
  Puede crear: Planta Operario, Franquicia Admin, Franquicia Empleado
  No puede: crear Planta Jefe ni Admin

PLANTA_OPERARIO (≤25):
  Puede crear: Franquicia Admin, Franquicia Empleado
  Solo sucursales permitidas

FRANQUICIA_ADMIN (30):
  Puede crear: Franquicia Empleado
  Solo en su propia sucursal

FRANQUICIA_EMPLEADO (40):
  No puede crear usuarios
```

```javascript
// En el frontend, filtrar los roles disponibles según el nivel del operador
function getRolesPermitidos(rolNivelOperador) {
    const todosLosRoles = [
        { nivel: 10, nombre: 'Admin' },
        { nivel: 20, nombre: 'Planta Jefe' },
        { nivel: 25, nombre: 'Planta Operario' },
        { nivel: 30, nombre: 'Franquicia Admin' },
        { nivel: 40, nombre: 'Franquicia Empleado' }
    ];
    // Solo puede crear roles de MAYOR nivel numérico (menos privilegiados)
    return todosLosRoles.filter(r => r.nivel > rolNivelOperador);
}
```

---

## Endpoints de Usuarios

```
GET  /usuarios                  → Listar usuarios (filtrado por rol del operador)
POST /usuarios                  → Crear usuario
GET  /usuarios/{id}             → Detalle de un usuario
PUT  /usuarios/{id}             → Editar usuario
PUT  /usuarios/{id}/estado      → Activar / Suspender
DELETE /usuarios/{id}           → Eliminar (solo ADMIN)
GET  /usuarios/roles            → Listar roles disponibles (filtrado por rol propio)
GET  /usuarios/sucursales       → Listar sucursales para asignar
```

---

## Crear Usuario — Payload

```json
{
  "nombre": "Carlos",
  "apellido": "López",
  "email": "carlos@franquicia.com",
  "usuario": "clopez",
  "password": "contraseñaSegura123",
  "rol_nivel": 30,
  "sucursales": [3, 5]
}
```

**Validaciones requeridas (backend):**
- `usuario` único en la tabla
- `email` único y formato válido
- `password` mínimo 8 caracteres
- `rol_nivel` debe ser mayor al `rol_nivel` del operador (no puede crear igual o superior)
- `sucursales` es array de IDs; usuarios de planta pueden recibir array vacío (acceso global)

---

## Editar Usuario — Restricciones

```
ADMIN:
  Puede editar todos los campos de cualquier usuario

PLANTA_JEFE:
  Puede editar: nombre, apellido, email, sucursales asignadas
  No puede cambiar el rol de alguien de mayor jerarquía (≤ su propio nivel)

FRANQUICIA_ADMIN:
  Puede editar SOLO empleados de su propia sucursal
  No puede cambiar el rol

Cambiar contraseña:
  - Cada usuario puede cambiar SU PROPIA contraseña vía POST /auth/cambiar-password
  - ADMIN puede resetear cualquier contraseña
```

---

## Suspender / Reactivar

```javascript
async function cambiarEstadoUsuario(id, estadoNuevo) {
    // estadoNuevo: 'ACTIVO' o 'SUSPENDIDO'
    const accion = estadoNuevo === 'ACTIVO' ? 'reactivar' : 'suspender';
    const conf = await Swal.fire({
        title: `¿${accion.charAt(0).toUpperCase() + accion.slice(1)} usuario?`,
        icon: 'question', showCancelButton: true, confirmButtonText: 'Confirmar'
    });
    if (!conf.isConfirmed) return;

    const resp = await MikeloAuth.fetch(`/usuarios/${id}/estado`, {
        method: 'PUT',
        body: JSON.stringify({ estado: estadoNuevo })
    });
    const data = await resp.json();
    if (data.success) await cargarUsuarios();
}
```

---

## Frontend `usuarios.html` — Inicialización

```javascript
document.addEventListener('DOMContentLoaded', async () => {
    // Solo planta supervisor o admin puede gestionar usuarios
    await MikeloAuth.requirePlanta();

    const user = MikeloAuth.getUser();
    if (!MikeloAuth.isPlantaJefe() && !MikeloAuth.isFranquiciaAdmin()) {
        // Planta operario estándar no accede
        Swal.fire('Sin acceso', 'No tienes permisos para gestionar usuarios', 'error');
        return;
    }

    await cargarUsuarios();
    await cargarRolesDisponibles(user.rol_nivel);
    await cargarSucursales();
});
```

---

## Cargar y Mostrar Usuarios

```javascript
async function cargarUsuarios(filtros = {}) {
    const qs = new URLSearchParams(filtros).toString();
    const resp = await MikeloAuth.fetch(`/usuarios?${qs}`);
    const data = await resp.json();
    // data.usuarios = [{ id, nombre, apellido, usuario, rol_nivel, rol_nombre, estado, sucursales, ultimo_login }]

    if (tablaUsuarios) tablaUsuarios.destroy();
    tablaUsuarios = $('#tabla-usuarios').DataTable({
        data: data.usuarios,
        columns: [
            { data: 'usuario' },
            { data: null, render: (_, __, r) => `${r.nombre} ${r.apellido}` },
            { data: 'rol_nombre' },
            { data: null, render: (_, __, r) => r.sucursales?.map(s => s.nombre).join(', ') || 'Global' },
            { data: null, render: (_, __, r) => `<span class="badge badge-${r.estado === 'ACTIVO' ? 'success' : 'danger'}">${r.estado}</span>` },
            { data: null, render: (_, __, r) => botonesAccionUsuario(r) }
        ],
        language: { url: '//cdn.datatables.net/plug-ins/1.13.6/i18n/es-AR.json' }
    });
}
```

---

## Asignar Sucursales con Select2

```javascript
// Selector múltiple de sucursales para formulario de usuario
$('#select-sucursales').select2({
    placeholder: 'Seleccionar sucursales...',
    allowClear: true,
    data: sucursalesDisponibles.map(s => ({ id: s.id, text: s.nombre }))
});

// Al guardar, obtener IDs seleccionados
const sucursalesSeleccionadas = $('#select-sucursales').val(); // Array de strings
const sucursalesIds = sucursalesSeleccionadas.map(Number);
```

---

## Modelo Usuario.php — Métodos Clave

```php
namespace App\Model;

class Usuario {
    // Listar con filtros (opera respetando visibilidad del operador)
    public function listar(int $rolNivelOperador, ?int $idSucursalOperador): array

    // Crear (password_hash con bcrypt)
    public function crear(array $datos): int

    // Obtener usuario con sucursales asignadas
    public function obtener(int $id): ?array

    // Editar datos básicos
    public function editar(int $id, array $datos): bool

    // Cambiar estado (ACTIVO / SUSPENDIDO)
    public function cambiarEstado(int $id, string $estado): bool

    // Cambiar contraseña (recibe plain, hace hash internamente)
    public function cambiarPassword(int $id, string $passwordPlain): bool

    // Verificar existencia de usuario/email (para validar unicidad)
    public function existeUsuario(string $usuario, int $excludeId = 0): bool
    public function existeEmail(string $email, int $excludeId = 0): bool

    // Asignar sucursales (reemplaza las actuales)
    public function asignarSucursales(int $idUsuario, array $sucursalesIds): void

    // Para autenticación
    public function verificarCredenciales(string $usuario, string $password): ?array
}
```

---

## Password Handling

```php
// Crear usuario — hashear contraseña
'password_hash' => password_hash($datos['password'], PASSWORD_BCRYPT)

// Verificar login
if (!password_verify($input['password'], $usuario['password_hash'])) {
    return responseJson($response, ['error' => true, 'mensaje' => 'Credenciales inválidas'], 401);
}
```

**Nunca:**
- Devolver `password_hash` en respuestas JSON
- Loguear contraseñas en texto plano
- Usar MD5/SHA1 para contraseñas

---

## Auditoría (Opcional pero Recomendado)

Al crear/editar/suspender un usuario, registrar la acción:

```php
// Registrar auditoría en tabla auditoria (si existe)
$stmt = $db->prepare("
    INSERT INTO auditoria (id_usuario, modulo, accion, tabla_afectada, id_registro, datos_despues, fecha_hora)
    VALUES (?, 'USUARIOS', ?, 'usuarios', ?, ?, NOW())
");
$stmt->execute([
    $idOperador,
    $accion,          // 'CREAR', 'EDITAR', 'SUSPENDER', etc.
    $idAfectado,
    json_encode($datosNuevos)
]);
```
