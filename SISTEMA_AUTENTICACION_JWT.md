# SISTEMA DE AUTENTICACIÓN JWT + ROLES Y PERMISOS

## 📋 INDICE
1. [Estructura de Roles](#estructura-de-roles)
2. [Base de Datos](#base-de-datos)
3. [Implementación JWT](#implementación-jwt)
4. [Flujo de Autenticación](#flujo-de-autenticación)
5. [ABM de Usuarios](#abm-de-usuarios)
6. [Matriz de Roles y Permisos](#matriz-de-roles-y-permisos)

---

## 🔐 ESTRUCTURA DE ROLES

### Jerarquía de Roles

```
┌─────────────────────────────────────────────┐
│          ADMIN GENERAL (Super)              │  ← Acceso total a todo
│    (Gestiona: Todo el sistema)              │
└─────────┬───────────────────────────────────┘
          │
          ├─────────────────────────────┬──────────────────────────┐
          │                             │                          │
     ┌────▼──────────────┐   ┌─────────▼──────────┐   ┌──────────▼──────────┐
     │ SUPERVISOR PLANTA │   │ADMIN PLANTA        │   │    ADMINISTRADOR    │
     │ (Gestiona: Planta │   │ (Gestiona: Planta) │   │ (Gestiona: Sistema) │
     │ + Sucursales)     │   │                    │   │                    │
     └────┬──────────────┘   └──────────────────┘   └────────────────────┘
          │
          ├─────────────────────────────┬──────────────────────────┐
          │                             │
  ┌───────▼────────────────┐  ┌────────▼──────────────┐
  │ SUPERVISOR SUCURSAL    │  │ ADMINISTRATIVO PLANTA │
  │ (Gestiona: Sucursal X) │  │ (Gestiona: Planta)   │
  │ (solo sucursales del   │  │                      │
  │  usuario permitidas)   │  │ (Propiedad del dueño)│
  └────────────────────────┘  └──────────────────────┘
          │
          └─────────────────────────┐
                                    │
                        ┌───────────▼──────────┐
                        │ ADMINISTRATIVO       │
                        │ SUCURSAL             │
                        │ (Gestiona: Sucursal X)
                        │ (solo sucursal X)    │
                        └──────────────────────┘
```

### Definición de Roles

| Rol | Nivel | Contexto | Sucursales Permitidas | Descripción |
|-----|-------|---------|----------------------|-------------|
| **ADMIN** | 0 | Global | Todas | Acceso total al sistema, gestión de usuarios, configuración |
| **SUPERVISOR_PLANTA** | 1 | Planta + Sucursales | Todas | Supervisa toda la planta y sucursales (empleado del dueño) |
| **ADMIN_PLANTA** | 2 | Planta | Todas | Administra operaciones de planta (empleado del dueño) |
| **SUPERVISOR_SUCURSAL** | 3 | Sucursal | Asignadas al usuario | Supervisa una o varias sucursales (propietario/gerente) |
| **ADMIN_SUCURSAL** | 4 | Sucursal | Una única | Administra operaciones en una sucursal |
| **OPERARIO** | 5 | Planta/Sucursal | Asignadas | Ejecuta tareas (escaneo, carga) |

---

## 📊 BASE DE DATOS

### Tablas Necesarias

```sql
-- 1. Tabla de Usuarios
CREATE TABLE usuarios (
  id INT PRIMARY KEY AUTO_INCREMENT,
  nombre VARCHAR(100) NOT NULL,
  apellido VARCHAR(100) NOT NULL,
  email VARCHAR(100) UNIQUE NOT NULL,
  usuario VARCHAR(50) UNIQUE NOT NULL,
  password_hash VARCHAR(255) NOT NULL,
  id_rol INT NOT NULL,
  estado ENUM('ACTIVO', 'INACTIVO', 'SUSPENDIDO') DEFAULT 'ACTIVO',
  ultimo_login DATETIME,
  intentos_fallidos INT DEFAULT 0,
  bloqueado_hasta DATETIME,
  fecha_creacion DATETIME DEFAULT NOW(),
  fecha_modificacion DATETIME DEFAULT NOW() ON UPDATE NOW(),
  creado_por INT,
  FOREIGN KEY (id_rol) REFERENCES roles(id),
  FOREIGN KEY (creado_por) REFERENCES usuarios(id)
);

-- 2. Tabla de Roles (catálogo fijo)
CREATE TABLE roles (
  id INT PRIMARY KEY,
  codigo VARCHAR(50) UNIQUE NOT NULL,
  nombre VARCHAR(100) NOT NULL,
  descripcion TEXT,
  nivel INT, -- Para jerarquía (0=ADMIN, 1=SUPERVISOR_PLANTA, etc)
  UNIQUE KEY (codigo)
);

-- Insertar roles fijos
INSERT INTO roles VALUES
(1, 'ADMIN', 'Administrador General', 'Acceso total al sistema', 0),
(2, 'SUPERVISOR_PLANTA', 'Supervisor de Planta', 'Supervisa planta y sucursales', 1),
(3, 'ADMIN_PLANTA', 'Administrativo de Planta', 'Administra operaciones de planta', 2),
(4, 'SUPERVISOR_SUCURSAL', 'Supervisor de Sucursal', 'Supervisa sucursales asignadas', 3),
(5, 'ADMIN_SUCURSAL', 'Administrativo de Sucursal', 'Administra sucursal asignada', 4),
(6, 'OPERARIO', 'Operario', 'Ejecuta tareas de escaneo', 5);

-- 3. Tabla de Relación Usuario-Sucursal (N:N)
CREATE TABLE usuario_sucursales (
  id INT PRIMARY KEY AUTO_INCREMENT,
  id_usuario INT NOT NULL,
  id_sucursal INT NOT NULL,
  tipo_relacion ENUM('PROPIETARIO', 'GERENTE', 'OPERARIO') DEFAULT 'GERENTE',
  activo BOOLEAN DEFAULT TRUE,
  fecha_asignacion DATETIME DEFAULT NOW(),
  FOREIGN KEY (id_usuario) REFERENCES usuarios(id) ON DELETE CASCADE,
  FOREIGN KEY (id_sucursal) REFERENCES ubicaciones(id) ON DELETE CASCADE,
  UNIQUE KEY (id_usuario, id_sucursal)
);

-- 4. Tabla de Permisos (ABM dinámico)
CREATE TABLE permisos (
  id INT PRIMARY KEY AUTO_INCREMENT,
  codigo VARCHAR(100) UNIQUE NOT NULL,
  nombre VARCHAR(100) NOT NULL,
  modulo VARCHAR(50) NOT NULL, -- 'PRODUCTOS', 'ENVIOS', 'PEDIDOS', etc
  accion VARCHAR(50) NOT NULL, -- 'CREAR', 'LEER', 'EDITAR', 'ELIMINAR'
  descripcion TEXT,
  UNIQUE KEY (modulo, accion)
);

-- 5. Tabla de Relación Rol-Permiso (N:N)
CREATE TABLE rol_permisos (
  id INT PRIMARY KEY AUTO_INCREMENT,
  id_rol INT NOT NULL,
  id_permiso INT NOT NULL,
  FOREIGN KEY (id_rol) REFERENCES roles(id) ON DELETE CASCADE,
  FOREIGN KEY (id_permiso) REFERENCES permisos(id) ON DELETE CASCADE,
  UNIQUE KEY (id_rol, id_permiso)
);

-- 6. Tabla de Auditoría (registra todas las acciones)
CREATE TABLE auditoria (
  id INT PRIMARY KEY AUTO_INCREMENT,
  id_usuario INT NOT NULL,
  id_rol INT NOT NULL,
  id_sucursal INT,
  modulo VARCHAR(50) NOT NULL,
  accion VARCHAR(50) NOT NULL,
  tabla_afectada VARCHAR(100) NOT NULL,
  id_registro INT,
  datos_antes JSON,
  datos_despues JSON,
  ip_address VARCHAR(50),
  user_agent TEXT,
  fecha_hora DATETIME DEFAULT NOW(),
  FOREIGN KEY (id_usuario) REFERENCES usuarios(id),
  FOREIGN KEY (id_rol) REFERENCES roles(id),
  FOREIGN KEY (id_sucursal) REFERENCES ubicaciones(id),
  INDEX (id_usuario, fecha_hora),
  INDEX (modulo, accion)
);

-- 7. Tabla de Tokens JWT (para blacklist/invalidación)
CREATE TABLE jwt_tokens (
  id INT PRIMARY KEY AUTO_INCREMENT,
  id_usuario INT NOT NULL,
  token_hash VARCHAR(255) UNIQUE NOT NULL,
  fecha_creacion DATETIME DEFAULT NOW(),
  fecha_expiracion DATETIME NOT NULL,
  activo BOOLEAN DEFAULT TRUE,
  FOREIGN KEY (id_usuario) REFERENCES usuarios(id) ON DELETE CASCADE,
  INDEX (id_usuario, activo),
  INDEX (token_hash)
);
```

---

## 🔑 IMPLEMENTACIÓN JWT

### Estructura del Token JWT

```json
{
  "header": {
    "alg": "HS256",
    "typ": "JWT"
  },
  "payload": {
    "sub": "1",
    "usuario_id": 1,
    "usuario": "jgarcia",
    "nombre_completo": "Juan García",
    "email": "juan@heladeria.com",
    "id_rol": 2,
    "rol": "SUPERVISOR_PLANTA",
    "sucursales_permitidas": [1, 2, 5], -- IDs de sucursales
    "tipo_relacion": ["PROPIETARIO", "GERENTE"],
    "permisos": ["ENVIOS_CREAR", "ENVIOS_EDITAR", "PEDIDOS_LEER"],
    "iat": 1732900000,
    "exp": 1732986400 -- 24 horas
  },
  "signature": "..."
}
```

### Clase JWT (PHP)

```php
<?php
namespace App\Security;

use Firebase\JWT\JWT;
use Firebase\JWT\Key;

class JWTHandler {
    private $secretKey;
    private $algorithm = 'HS256';
    private $expirationTime = 86400; // 24 horas
    
    public function __construct($secretKey = null) {
        $this->secretKey = $secretKey ?? getenv('JWT_SECRET_KEY');
    }
    
    /**
     * Generar token JWT
     */
    public function generarToken($usuarioData, $sucursales = [], $permisos = []) {
        $ahora = time();
        
        $payload = [
            'sub' => (string)$usuarioData['id'],
            'usuario_id' => $usuarioData['id'],
            'usuario' => $usuarioData['usuario'],
            'nombre_completo' => $usuarioData['nombre'] . ' ' . $usuarioData['apellido'],
            'email' => $usuarioData['email'],
            'id_rol' => $usuarioData['id_rol'],
            'rol' => $usuarioData['codigo_rol'],
            'sucursales_permitidas' => $sucursales,
            'permisos' => $permisos,
            'iat' => $ahora,
            'exp' => $ahora + $this->expirationTime
        ];
        
        $token = JWT::encode($payload, $this->secretKey, $this->algorithm);
        
        return $token;
    }
    
    /**
     * Validar y decodificar token
     */
    public function validarToken($token) {
        try {
            $decoded = JWT::decode($token, new Key($this->secretKey, $this->algorithm));
            return (array)$decoded;
        } catch (\Exception $e) {
            return false;
        }
    }
    
    /**
     * Verificar si token está en blacklist
     */
    public function tokenEnBlacklist($db, $tokenHash, $usuarioId) {
        $sql = "
            SELECT COUNT(*) as count 
            FROM jwt_tokens 
            WHERE token_hash = ? AND id_usuario = ? AND activo = 0
        ";
        $stmt = $db->prepare($sql);
        $stmt->execute([$tokenHash, $usuarioId]);
        $result = $stmt->fetch(\PDO::FETCH_ASSOC);
        return $result['count'] > 0;
    }
    
    /**
     * Invalidar token (logout)
     */
    public function invalidarToken($db, $token, $usuarioId) {
        $tokenHash = hash('sha256', $token);
        
        $sql = "UPDATE jwt_tokens SET activo = 0 WHERE token_hash = ? AND id_usuario = ?";
        $stmt = $db->prepare($sql);
        return $stmt->execute([$tokenHash, $usuarioId]);
    }
}
```

---

## 🔄 FLUJO DE AUTENTICACIÓN

### 1. LOGIN

```php
// POST /api/auth/login
$app->post('/auth/login', function ($request, $response) {
    try {
        $data = $request->getParsedBody();
        $usuario = $data['usuario'] ?? null;
        $password = $data['password'] ?? null;
        
        if (!$usuario || !$password) {
            return responseJson($response, 400, ['error' => 'Usuario y contraseña requeridos'], false);
        }
        
        $db = getDB();
        $auth = new AuthService($db);
        
        // Autenticar usuario
        $resultado = $auth->autenticar($usuario, $password);
        
        if (!$resultado['success']) {
            return responseJson($response, 401, ['error' => $resultado['error']], false);
        }
        
        // Generar token JWT
        $jwt = new JWTHandler();
        $usuarioData = $resultado['usuario'];
        $sucursales = $auth->obtenerSucursalesPermitidas($usuarioData['id']);
        $permisos = $auth->obtenerPermisosUsuario($usuarioData['id']);
        
        $token = $jwt->generarToken($usuarioData, $sucursales, $permisos);
        
        // Registrar token
        $auth->registrarToken($token, $usuarioData['id']);
        
        // Auditoría
        registrarAuditoria($db, $usuarioData['id'], 'AUTH', 'LOGIN', 'usuarios', $usuarioData['id']);
        
        return responseJson($response, 200, [
            'success' => true,
            'token' => $token,
            'usuario' => [
                'id' => $usuarioData['id'],
                'nombre' => $usuarioData['nombre'],
                'rol' => $usuarioData['codigo_rol'],
                'sucursales' => $sucursales
            ]
        ]);
        
    } catch (\Exception $e) {
        return responseJson($response, 500, ['error' => $e->getMessage()], false);
    }
});
```

### 2. MIDDLEWARE DE AUTENTICACIÓN

```php
// Middleware: Verificar token JWT
$app->add(new JWTMiddleware($db));

class JWTMiddleware {
    private $db;
    
    public function __construct($db) {
        $this->db = $db;
    }
    
    public function __invoke($request, $handler) {
        // Rutas públicas (no requieren token)
        $rutasPublicas = [
            '/api/auth/login',
            '/api/auth/registro', // si aplica
        ];
        
        $path = $request->getUri()->getPath();
        
        if (in_array($path, $rutasPublicas)) {
            return $handler->handle($request);
        }
        
        // Obtener token del header Authorization
        $token = null;
        $authHeader = $request->getHeaderLine('Authorization');
        
        if ($authHeader && preg_match('/Bearer\s+(\S+)/', $authHeader, $matches)) {
            $token = $matches[1];
        }
        
        if (!$token) {
            return responseJson(new Response(), 401, ['error' => 'Token no proporcionado'], false);
        }
        
        // Validar token
        $jwt = new JWTHandler();
        $payload = $jwt->validarToken($token);
        
        if (!$payload) {
            return responseJson(new Response(), 401, ['error' => 'Token inválido o expirado'], false);
        }
        
        // Verificar si está en blacklist
        if ($jwt->tokenEnBlacklist($this->db, hash('sha256', $token), $payload['usuario_id'])) {
            return responseJson(new Response(), 401, ['error' => 'Token invalidado'], false);
        }
        
        // Guardar usuario en request
        $request = $request->withAttribute('usuario', $payload);
        
        return $handler->handle($request);
    }
}
```

### 3. LOGOUT

```php
// POST /api/auth/logout
$app->post('/auth/logout', function ($request, $response) {
    try {
        $usuario = $request->getAttribute('usuario');
        $token = obtenerTokenDelHeader($request);
        
        $jwt = new JWTHandler();
        $jwt->invalidarToken($db, $token, $usuario['usuario_id']);
        
        // Auditoría
        registrarAuditoria($db, $usuario['usuario_id'], 'AUTH', 'LOGOUT', 'usuarios', $usuario['usuario_id']);
        
        return responseJson($response, 200, ['success' => true, 'mensaje' => 'Sesión cerrada']);
        
    } catch (\Exception $e) {
        return responseJson($response, 500, ['error' => $e->getMessage()], false);
    }
});
```

---

## 👥 ABM DE USUARIOS

### Creación de Usuarios

**Reglas según Rol del Creador:**

```
ADMIN
  └─ Puede crear: ADMIN, SUPERVISOR_PLANTA, ADMIN_PLANTA, SUPERVISOR_SUCURSAL, ADMIN_SUCURSAL, OPERARIO
     - Asignar a cualquier sucursal

SUPERVISOR_PLANTA
  └─ Puede crear: ADMIN_PLANTA, ADMIN_SUCURSAL, OPERARIO
     - Asignar a cualquier sucursal
     - NO puede crear otros SUPERVISOR_PLANTA ni ADMIN

ADMIN_PLANTA
  └─ Puede crear: OPERARIO
     - Asignar a cualquier sucursal

SUPERVISOR_SUCURSAL
  └─ Puede crear: ADMIN_SUCURSAL, OPERARIO
     - Solo para sus sucursales asignadas

ADMIN_SUCURSAL
  └─ Puede crear: OPERARIO
     - Solo para su sucursal asignada
```

### Endpoint: Crear Usuario

```php
// POST /api/usuarios/crear
$app->post('/usuarios/crear', function ($request, $response) {
    try {
        $usuarioActual = $request->getAttribute('usuario');
        $data = $request->getParsedBody();
        
        $usuariosService = new UsuariosService(getDB());
        
        // Validar permisos del usuario actual
        if (!$usuariosService->puedeCrearUsuario($usuarioActual, $data['id_rol'])) {
            return responseJson($response, 403, ['error' => 'No tienes permiso para crear este tipo de usuario'], false);
        }
        
        // Crear usuario
        $nuevoUsuario = $usuariosService->crearUsuario(
            $data['nombre'],
            $data['apellido'],
            $data['email'],
            $data['usuario'],
            $data['password'],
            $data['id_rol'],
            $data['sucursales'] ?? [], // Array de IDs de sucursales
            $usuarioActual['usuario_id']
        );
        
        // Auditoría
        registrarAuditoria(getDB(), $usuarioActual['usuario_id'], 'USUARIOS', 'CREAR', 'usuarios', $nuevoUsuario['id']);
        
        return responseJson($response, 201, $nuevoUsuario);
        
    } catch (\Exception $e) {
        return responseJson($response, 400, ['error' => $e->getMessage()], false);
    }
});
```

### Formulario HTML: Crear Usuario

```html
<div class="modal" id="modalCrearUsuario">
  <div class="modal-content">
    <h3>Crear Nuevo Usuario</h3>
    
    <form id="formCrearUsuario">
      <div class="form-group">
        <label>Nombre *</label>
        <input type="text" name="nombre" required>
      </div>
      
      <div class="form-group">
        <label>Apellido *</label>
        <input type="text" name="apellido" required>
      </div>
      
      <div class="form-group">
        <label>Email *</label>
        <input type="email" name="email" required>
      </div>
      
      <div class="form-group">
        <label>Usuario (login) *</label>
        <input type="text" name="usuario" required>
      </div>
      
      <div class="form-group">
        <label>Contraseña *</label>
        <input type="password" name="password" required minlength="8">
        <small>Mínimo 8 caracteres, letras + números</small>
      </div>
      
      <div class="form-group">
        <label>Rol *</label>
        <select name="id_rol" id="selectRol" required onchange="cargarSucursalesPermitidas()">
          <option value="">-- Seleccionar --</option>
          <!-- Llenar dinámicamente según permisos del usuario actual -->
        </select>
      </div>
      
      <div class="form-group">
        <label>Sucursales Permitidas</label>
        <div id="checkboxSucursales">
          <!-- Llenar dinámicamente -->
        </div>
        <small id="notaSucursales"></small>
      </div>
      
      <div class="form-actions">
        <button type="submit" class="btn btn-success">Crear Usuario</button>
        <button type="button" class="btn btn-secondary" onclick="cerrarModal('modalCrearUsuario')">Cancelar</button>
      </div>
    </form>
  </div>
</div>
```

### JavaScript: Crear Usuario

```javascript
document.getElementById('formCrearUsuario').addEventListener('submit', async (e) => {
    e.preventDefault();
    
    const datos = new FormData(e.target);
    const sucursales = Array.from(document.querySelectorAll('input[name="sucursales"]:checked'))
        .map(cb => parseInt(cb.value));
    
    const payload = {
        nombre: datos.get('nombre'),
        apellido: datos.get('apellido'),
        email: datos.get('email'),
        usuario: datos.get('usuario'),
        password: datos.get('password'),
        id_rol: parseInt(datos.get('id_rol')),
        sucursales: sucursales
    };
    
    try {
        const response = await fetch('/api/usuarios/crear', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Authorization': `Bearer ${localStorage.getItem('token')}`
            },
            body: JSON.stringify(payload)
        });
        
        if (response.ok) {
            Swal.fire('Éxito', 'Usuario creado correctamente', 'success');
            cerrarModal('modalCrearUsuario');
            cargarUsuarios();
        } else {
            const error = await response.json();
            Swal.fire('Error', error.error, 'error');
        }
    } catch (error) {
        Swal.fire('Error', 'Error al crear usuario', 'error');
    }
});

function cargarSucursalesPermitidas() {
    const rolId = parseInt(document.getElementById('selectRol').value);
    
    // Obtener roles y sucursales según permisos
    fetch(`/api/usuarios/sucursales-permitidas?id_rol=${rolId}`, {
        headers: { 'Authorization': `Bearer ${localStorage.getItem('token')}` }
    })
    .then(r => r.json())
    .then(data => {
        const container = document.getElementById('checkboxSucursales');
        container.innerHTML = '';
        
        if (data.sucursales.length === 0) {
            document.getElementById('notaSucursales').textContent = 'Este rol no requiere asignación de sucursales';
            return;
        }
        
        data.sucursales.forEach(sucursal => {
            const label = document.createElement('label');
            label.innerHTML = `<input type="checkbox" name="sucursales" value="${sucursal.id}"> ${sucursal.nombre}`;
            container.appendChild(label);
        });
    });
}
```

---

## 🗂️ MATRIZ DE ROLES Y PERMISOS

### Estructura General

```
Módulo → [Acciones Disponibles]
  └─ Rol → [Permiso: SÍ/NO]
```

### Detalle por Módulo

#### MÓDULO: PRODUCTOS

| Acción | ADMIN | SUPER_PLANTA | ADMIN_PLANTA | SUPER_SUCURSAL | ADMIN_SUCURSAL | OPERARIO |
|--------|-------|--------------|--------------|----------------|----------------|----------|
| CREAR | ✅ | ✅ | ✅ | ❌ | ❌ | ❌ |
| LEER | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| EDITAR | ✅ | ✅ | ✅ | ❌ | ❌ | ❌ |
| ELIMINAR | ✅ | ❌ | ❌ | ❌ | ❌ | ❌ |

#### MÓDULO: ENVÍOS

| Acción | ADMIN | SUPER_PLANTA | ADMIN_PLANTA | SUPER_SUCURSAL | ADMIN_SUCURSAL | OPERARIO |
|--------|-------|--------------|--------------|----------------|----------------|----------|
| CREAR | ✅ | ✅ | ✅ | ✅* | ✅* | ❌ |
| LEER | ✅ | ✅ | ✅ | ✅* | ✅* | ✅* |
| EDITAR | ✅ | ✅ | ✅ | ✅* | ✅* | ❌ |
| CONFIRMAR | ✅ | ✅ | ✅ | ✅* | ✅* | ❌ |

*_Solo para sus sucursales asignadas_

#### MÓDULO: PEDIDOS

| Acción | ADMIN | SUPER_PLANTA | ADMIN_PLANTA | SUPER_SUCURSAL | ADMIN_SUCURSAL | OPERARIO |
|--------|-------|--------------|--------------|----------------|----------------|----------|
| CREAR | ✅ | ✅ | ✅ | ✅* | ✅* | ❌ |
| LEER | ✅ | ✅ | ✅ | ✅* | ✅* | ❌ |
| EDITAR | ✅ | ✅ | ✅ | ✅* | ✅* | ❌ |
| RECIBIR | ✅ | ✅ | ✅ | ✅* | ✅* | ❌ |

#### MÓDULO: STOCK

| Acción | ADMIN | SUPER_PLANTA | ADMIN_PLANTA | SUPER_SUCURSAL | ADMIN_SUCURSAL | OPERARIO |
|--------|-------|--------------|--------------|----------------|----------------|----------|
| LEER (Central) | ✅ | ✅ | ✅ | ✅ | ❌ | ✅ |
| LEER (Sucursal) | ✅ | ✅ | ❌ | ✅* | ✅* | ✅* |
| BAJAS | ✅ | ✅ | ❌ | ✅* | ✅* | ✅* |
| AJUSTES | ✅ | ✅ | ✅ | ✅* | ✅* | ❌ |

#### MÓDULO: USUARIOS

| Acción | ADMIN | SUPER_PLANTA | ADMIN_PLANTA | SUPER_SUCURSAL | ADMIN_SUCURSAL | OPERARIO |
|--------|-------|--------------|--------------|----------------|----------------|----------|
| CREAR | ✅ | ✅ | ❌ | ✅* | ✅* | ❌ |
| LEER | ✅ | ✅ | ❌ | ✅* | ✅* | ❌ |
| EDITAR | ✅ | ✅ | ❌ | ✅* | ✅* | ❌ |
| SUSPENDER | ✅ | ✅ | ❌ | ✅* | ❌ | ❌ |

#### MÓDULO: REPORTES

| Acción | ADMIN | SUPER_PLANTA | ADMIN_PLANTA | SUPER_SUCURSAL | ADMIN_SUCURSAL | OPERARIO |
|--------|-------|--------------|--------------|----------------|----------------|----------|
| AUDITORIA | ✅ | ✅ | ❌ | ❌ | ❌ | ❌ |
| STOCK (Central) | ✅ | ✅ | ✅ | ❌ | ❌ | ❌ |
| STOCK (Sucursal) | ✅ | ✅ | ❌ | ✅* | ✅* | ❌ |
| VENTAS | ✅ | ✅ | ❌ | ✅* | ✅* | ❌ |

---

## 🎯 CONTEXTO DE ACCESO (Por Rol)

### ADMIN
- ✅ Ve todos los módulos
- ✅ Accede a todas las sucursales
- ✅ Gestiona todos los usuarios
- ✅ Ve auditoría completa

### SUPERVISOR_PLANTA
- ✅ Accede a toda la planta
- ✅ Ve todas las sucursales
- ✅ Puede crear: ADMIN_PLANTA, ADMIN_SUCURSAL, OPERARIO
- ✅ Ve auditoría de operaciones

### ADMIN_PLANTA
- ✅ Operaciones de planta
- ✅ Ve stock central
- ✅ Gestiona envíos (todos)
- ❌ No accede a sucursales

### SUPERVISOR_SUCURSAL
- ✅ Accede a sucursales asignadas
- ✅ Ve stock de sus sucursales
- ✅ Puede crear: ADMIN_SUCURSAL, OPERARIO
- ✅ Solo para sus sucursales

### ADMIN_SUCURSAL
- ✅ Accede a su sucursal asignada
- ✅ Ve stock de su sucursal
- ✅ Puede crear: OPERARIO
- ✅ Solo para su sucursal

### OPERARIO
- ✅ Ejecuta tareas asignadas
- ✅ Escanea, carga datos
- ✅ Ve stock (lectura)
- ✅ Solo su contexto

---

## 🛡️ AUDITORÍA Y REGISTRO

### Campos Registrados en Auditoria

```php
- id_usuario: Quién hizo la acción
- id_rol: Qué rol tenía
- id_sucursal: En qué contexto
- modulo: Qué módulo (PRODUCTOS, ENVIOS, etc)
- accion: Qué acción (CREAR, EDITAR, etc)
- tabla_afectada: Qué tabla cambió
- id_registro: ID del registro modificado
- datos_antes: JSON con valores anteriores
- datos_despues: JSON con valores nuevos
- ip_address: De dónde vino la acción
- user_agent: Qué navegador/app
- fecha_hora: Cuándo sucedió
```

### Función Helper: Registrar Auditoría

```php
function registrarAuditoria($db, $idUsuario, $modulo, $accion, $tablaAfectada, $idRegistro, $datosAntes = null, $datasDespues = null) {
    $usuario = obtenerDatosUsuario($db, $idUsuario);
    
    $sql = "
        INSERT INTO auditoria 
        (id_usuario, id_rol, id_sucursal, modulo, accion, tabla_afectada, id_registro, 
         datos_antes, datos_despues, ip_address, user_agent, fecha_hora)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
    ";
    
    $stmt = $db->prepare($sql);
    $stmt->execute([
        $idUsuario,
        $usuario['id_rol'],
        $usuario['id_sucursal'] ?? null,
        $modulo,
        $accion,
        $tablaAfectada,
        $idRegistro,
        $datosAntes ? json_encode($datosAntes) : null,
        $datosDestpues ? json_encode($datosDestpues) : null,
        $_SERVER['REMOTE_ADDR'] ?? null,
        $_SERVER['HTTP_USER_AGENT'] ?? null
    ]);
    
    return $db->lastInsertId();
}
```

---

## 🚀 IMPLEMENTACIÓN ROADMAP

### Fase 1: Infraestructura (1 semana)
- [ ] Crear tablas: usuarios, roles, usuario_sucursales, permisos, rol_permisos, auditoria, jwt_tokens
- [ ] Clase JWTHandler completa
- [ ] Middleware de autenticación
- [ ] Endpoints: /api/auth/login, /api/auth/logout

### Fase 2: ABM Usuarios (1 semana)
- [ ] Endpoints CRUD usuarios
- [ ] Validación de permisos por rol
- [ ] Formularios de creación/edición
- [ ] Restricciones de sucursales

### Fase 3: Integración (1 semana)
- [ ] Header con usuario + rol + sucursal
- [ ] Filtrar datos por contexto del usuario
- [ ] Registrar auditoría en todas las acciones
- [ ] Tests de permisos

