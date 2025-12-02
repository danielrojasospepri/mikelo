# ABM DE USUARIOS - ESPECIFICACIÓN COMPLETA

## 📋 INDICE
1. [Visión General](#visión-general)
2. [Flujos de Negocio](#flujos-de-negocio)
3. [Endpoints API](#endpoints-api)
4. [Clases PHP](#clases-php)
5. [Interfaz Frontend](#interfaz-frontend)
6. [Validaciones](#validaciones)
7. [Auditoría](#auditoría)

---

## 🎯 VISIÓN GENERAL

### Objetivo
Permitir a Supervisores crear y gestionar usuarios en su contexto (planta o sucursales), respetando jerarquía de roles y restricciones de acceso.

### Restricciones Clave

```
✅ ADMIN:
   Puede crear: ADMIN, SUPERVISOR_PLANTA, ADMIN_PLANTA, SUPERVISOR_SUCURSAL, ADMIN_SUCURSAL, OPERARIO
   Asignar a: Cualquier ubicación

✅ SUPERVISOR_PLANTA:
   Puede crear: ADMIN_PLANTA, SUPERVISOR_SUCURSAL, ADMIN_SUCURSAL, OPERARIO
   NO puede: Crear ADMIN ni otro SUPERVISOR_PLANTA
   Asignar a: Cualquier ubicación

✅ ADMIN_PLANTA:
   Puede crear: Ninguno (sin ABM de usuarios)
   NO puede: Crear usuarios
   
✅ SUPERVISOR_SUCURSAL:
   Puede crear: ADMIN_SUCURSAL, OPERARIO
   SOLO para: Sus sucursales asignadas
   Restricción: No puede cambiar roles

✅ ADMIN_SUCURSAL:
   Puede crear: OPERARIO
   SOLO para: Su sucursal asignada
   Restricción: No puede editar/eliminar

✅ OPERARIO:
   Puede crear: Ninguno
   Acceso ABM: NO
```

---

## 🔄 FLUJOS DE NEGOCIO

### Flujo 1: ADMIN crea SUPERVISOR_PLANTA

```
ADMIN (Acceso: sistemas)
  ↓
Abre módulo "Gestión de Usuarios"
  ↓
Click "Nuevo Usuario"
  ↓
Completa formulario:
  - Nombre: Juan García
  - Apellido: González
  - Email: juan@heladeria.com
  - Usuario (login): jgarcia
  - Contraseña: ****** (mín 8 caracteres)
  - Rol: SUPERVISOR_PLANTA
  - Sucursales: (No aplica - acceso a todas)
  ↓
Click "Crear"
  ↓
Sistema valida:
  - ✅ Usuario único
  - ✅ Email válido
  - ✅ Contraseña fuerte (min 8, letras + números)
  - ✅ ADMIN puede crear SUPERVISOR_PLANTA
  ↓
Registro creado
  ↓
Auditoría: "ADMIN usuario_id:1 CREO usuario jgarcia con rol SUPERVISOR_PLANTA"
  ↓
Juan puede loguearse: usuario=jgarcia, password=***
```

### Flujo 2: SUPERVISOR_PLANTA crea SUPERVISOR_SUCURSAL

```
SUPERVISOR_PLANTA (Acceso: jgarcia)
  ↓
Abre "Gestión de Usuarios"
  ↓
Click "Nuevo Usuario"
  ↓
Completa formulario:
  - Nombre: Carlos López
  - Email: carlos@franquicia.com
  - Usuario: clopez
  - Rol: SUPERVISOR_SUCURSAL
  - Sucursales: 
    ☑ Sucursal #3 (Franquicia - Zona Sur)
    ☑ Sucursal #5 (Franquicia - Zona Oeste)
  ↓
Click "Crear"
  ↓
Sistema valida:
  - ✅ SUPERVISOR_PLANTA puede crear SUPERVISOR_SUCURSAL
  - ✅ Sucursales asignadas correctamente
  - ✅ Usuario único
  ↓
Carlos accede SOLO a sucursales #3 y #5
```

### Flujo 3: SUPERVISOR_SUCURSAL intenta crear ADMIN_PLANTA (Bloqueado)

```
SUPERVISOR_SUCURSAL (Acceso: clopez)
  ↓
Abre "Gestión de Usuarios"
  ↓
Click "Nuevo Usuario"
  ↓
Intenta seleccionar rol "ADMIN_PLANTA"
  ↓
⚠️ BLOQUEADO: Solo puede ver [ADMIN_SUCURSAL, OPERARIO]
  ↓
Mensaje: "Tu rol solo puede crear: ADMIN_SUCURSAL, OPERARIO"
```

### Flujo 4: ADMIN_SUCURSAL intenta editar usuario

```
ADMIN_SUCURSAL (Acceso: sucursal #3)
  ↓
Abre "Gestión de Usuarios"
  ↓
Ve lista de OPERARIOS de su sucursal
  ↓
Intenta hacer click en editar
  ↓
⚠️ BLOQUEADO
  ↓
Mensaje: "No tienes permiso para editar usuarios"
  ↓
Nota: ADMIN_SUCURSAL solo puede VER, no editar
```

---

## 🔌 ENDPOINTS API

### 1. OBTENER LISTA DE USUARIOS

```http
GET /api/usuarios/listar?pagina=1&per_pagina=20&filtro=activos
Authorization: Bearer {token}
```

**Parámetros:**
- `pagina`: Número de página (default 1)
- `per_pagina`: Items por página (default 20, máx 100)
- `filtro`: "activos", "inactivos", "todos" (default "activos")
- `rol`: Filtrar por rol específico (opcional)
- `busqueda`: Buscar en nombre/email/usuario (opcional)

**Respuesta (200):**
```json
{
  "success": true,
  "usuarios": [
    {
      "id": 1,
      "nombre": "Juan",
      "apellido": "García",
      "email": "juan@heladeria.com",
      "usuario": "jgarcia",
      "id_rol": 2,
      "rol": "SUPERVISOR_PLANTA",
      "estado": "ACTIVO",
      "sucursales": [],
      "ultimo_login": "2025-11-29 14:30:00",
      "creado_por": {
        "nombre": "Admin",
        "usuario": "admin"
      },
      "fecha_creacion": "2025-11-28 10:00:00"
    }
  ],
  "paginacion": {
    "pagina": 1,
    "per_pagina": 20,
    "total": 45,
    "total_paginas": 3
  }
}
```

**Validaciones:**
- ✅ Usuario autenticado
- ✅ Si es SUPERVISOR_SUCURSAL: Solo ve usuarios de sus sucursales
- ✅ Si es ADMIN_SUCURSAL: Solo ve OPERARIOS de su sucursal

---

### 2. OBTENER USUARIO POR ID

```http
GET /api/usuarios/{id}
Authorization: Bearer {token}
```

**Respuesta (200):**
```json
{
  "success": true,
  "usuario": {
    "id": 5,
    "nombre": "Carlos",
    "apellido": "López",
    "email": "carlos@franquicia.com",
    "usuario": "clopez",
    "id_rol": 4,
    "rol": "SUPERVISOR_SUCURSAL",
    "estado": "ACTIVO",
    "sucursales": [
      {
        "id": 3,
        "nombre": "Sucursal Zona Sur",
        "tipo_relacion": "PROPIETARIO"
      },
      {
        "id": 5,
        "nombre": "Sucursal Zona Oeste",
        "tipo_relacion": "GERENTE"
      }
    ],
    "permisos": ["ENVIOS_CREAR", "PEDIDOS_LEER", "STOCK_LEER"],
    "ultimo_login": "2025-11-29 15:45:00",
    "creado_por": {
      "id": 1,
      "usuario": "jgarcia"
    },
    "fecha_creacion": "2025-11-28 11:00:00",
    "fecha_modificacion": "2025-11-28 11:00:00"
  }
}
```

---

### 3. CREAR USUARIO

```http
POST /api/usuarios/crear
Content-Type: application/json
Authorization: Bearer {token}

{
  "nombre": "Juan",
  "apellido": "García",
  "email": "juan@heladeria.com",
  "usuario": "jgarcia",
  "password": "MiPassword123",
  "id_rol": 2,
  "sucursales": []
}
```

**Respuesta (201):**
```json
{
  "success": true,
  "usuario": {
    "id": 10,
    "usuario": "jgarcia",
    "email": "juan@heladeria.com",
    "rol": "SUPERVISOR_PLANTA",
    "estado": "ACTIVO"
  },
  "mensaje": "Usuario creado exitosamente"
}
```

**Validaciones:**
```php
// 1. Autenticación
if (!$usuario = $request->getAttribute('usuario')) {
    return responseJson($response, 401, ['error' => 'No autenticado'], false);
}

// 2. Permiso
if (!puedeCrearUsuarios($usuario['id_rol'])) {
    return responseJson($response, 403, ['error' => 'No tienes permiso para crear usuarios'], false);
}

// 3. Permiso de ROL
if (!puedeCrearRol($usuario['id_rol'], $data['id_rol'])) {
    return responseJson($response, 403, ['error' => "No puedes crear usuarios con rol {$data['id_rol']}"], false);
}

// 4. Validaciones de datos
if (!validarEmailUnico($data['email'])) {
    return responseJson($response, 400, ['error' => 'Email ya existe'], false);
}

if (!validarUsuarioUnico($data['usuario'])) {
    return responseJson($response, 400, ['error' => 'Usuario (login) ya existe'], false);
}

if (!validarPassword($data['password'])) {
    return responseJson($response, 400, ['error' => 'Contraseña débil (mín 8, letras + números)'], false);
}

// 5. Validar sucursales
if ($usuario['id_rol'] === 4) { // SUPERVISOR_SUCURSAL
    foreach ($data['sucursales'] as $idSucursal) {
        if (!tieneAccesoASucursal($usuario['id'], $idSucursal)) {
            return responseJson($response, 403, ['error' => "No tienes acceso a sucursal {$idSucursal}"], false);
        }
    }
}
```

**Errores Posibles:**
```json
// 400: Datos inválidos
{
  "success": false,
  "error": "Contraseña débil (mín 8, letras + números)"
}

// 403: Sin permiso
{
  "success": false,
  "error": "No puedes crear usuarios con rol ADMIN"
}

// 409: Conflicto (email/usuario duplicado)
{
  "success": false,
  "error": "Email ya existe en el sistema"
}
```

---

### 4. EDITAR USUARIO

```http
PUT /api/usuarios/{id}
Content-Type: application/json
Authorization: Bearer {token}

{
  "nombre": "Juan Carlos",
  "apellido": "García López",
  "email": "jcarlos@heladeria.com",
  "password": "NuevaPassword123",
  "estado": "ACTIVO",
  "sucursales": [3, 5]
}
```

**Respuesta (200):**
```json
{
  "success": true,
  "usuario": {
    "id": 10,
    "nombre": "Juan Carlos",
    "email": "jcarlos@heladeria.com",
    "usuario": "jgarcia",
    "estado": "ACTIVO"
  },
  "mensaje": "Usuario actualizado exitosamente"
}
```

**Restricciones:**
```php
// 1. Validar permiso
if ($usuarioActual['id'] == $id) {
    // Puede editar su propio perfil (solo nombre/email/password)
} else if (esAdmin($usuarioActual) || esSupervisorPlanta($usuarioActual)) {
    // Puede editar otros usuarios
} else if (esSupervisorSucursal($usuarioActual)) {
    // Solo puede editar ADMIN_SUCURSAL y OPERARIO de sus sucursales
} else {
    return responseJson($response, 403, ['error' => 'Sin permiso'], false);
}

// 2. No puede cambiar su propio rol (excepto ADMIN)
if ($usuarioActual['id'] !== $id || $usuarioActual['id_rol'] !== 1) {
    if (isset($data['id_rol']) && $data['id_rol'] !== $usuarioAnterior['id_rol']) {
        return responseJson($response, 403, ['error' => 'No puedes cambiar el rol'], false);
    }
}

// 3. No puede cambiar rol de alguien de nivel superior
if ($usuarioAnterior['nivel_rol'] < $usuarioActual['nivel_rol']) {
    return responseJson($response, 403, ['error' => 'No puedes cambiar rol de usuario de nivel superior'], false);
}
```

---

### 5. CAMBIAR ESTADO DE USUARIO

```http
PATCH /api/usuarios/{id}/estado
Content-Type: application/json
Authorization: Bearer {token}

{
  "estado": "SUSPENDIDO",
  "motivo": "Validación pendiente",
  "dias_hasta_reactivar": 30
}
```

**Estados permitidos:**
- `ACTIVO`: Usuario puede loguearse
- `INACTIVO`: Usuario no puede loguearse
- `SUSPENDIDO`: Temporalmente bloqueado (con días de reactivación)

**Respuesta (200):**
```json
{
  "success": true,
  "mensaje": "Usuario suspendido hasta 2025-12-29",
  "usuario": {
    "id": 10,
    "estado": "SUSPENDIDO",
    "bloqueado_hasta": "2025-12-29 00:00:00"
  }
}
```

**Restricciones:**
```php
// Solo ADMIN y SUPERVISOR_PLANTA pueden suspender
if (!esAdmin($usuarioActual) && !esSupervisorPlanta($usuarioActual)) {
    return responseJson($response, 403, ['error' => 'Sin permiso'], false);
}

// No puede suspender a alguien de nivel superior
if ($usuarioActual['nivel_rol'] > $usuarioAAfectar['nivel_rol']) {
    return responseJson($response, 403, ['error' => 'No puedes suspender a usuario de nivel superior'], false);
}
```

---

### 6. RESETEAR CONTRASEÑA

```http
POST /api/usuarios/{id}/resetear-password
Content-Type: application/json
Authorization: Bearer {token}

{
  "nueva_password": "NuevaPass123"
}
```

**Respuesta (200):**
```json
{
  "success": true,
  "mensaje": "Contraseña actualizada. Usuario deberá loguearse con nueva contraseña"
}
```

---

### 7. OBTENER ROLES CREABLES

```http
GET /api/usuarios/roles-permitidos
Authorization: Bearer {token}
```

**Respuesta (200):**
```json
{
  "success": true,
  "roles": [
    {
      "id": 3,
      "codigo": "ADMIN_PLANTA",
      "nombre": "Administrativo de Planta",
      "nivel": 2
    },
    {
      "id": 4,
      "codigo": "SUPERVISOR_SUCURSAL",
      "nombre": "Supervisor de Sucursal",
      "nivel": 3
    }
  ],
  "mensaje": "Como SUPERVISOR_PLANTA puedes crear: ADMIN_PLANTA, SUPERVISOR_SUCURSAL, ADMIN_SUCURSAL, OPERARIO"
}
```

---

### 8. OBTENER SUCURSALES PERMITIDAS

```http
GET /api/usuarios/sucursales-permitidas?id_rol=4
Authorization: Bearer {token}
```

**Respuesta (200):**
```json
{
  "success": true,
  "id_rol": 4,
  "rol": "SUPERVISOR_SUCURSAL",
  "sucursales": [
    {
      "id": 1,
      "nombre": "Depósito Central",
      "tipo": "central"
    },
    {
      "id": 2,
      "nombre": "Sucursal Centro (Propiedad)",
      "tipo": "propiedad",
      "provincia": "Buenos Aires"
    },
    {
      "id": 5,
      "nombre": "Sucursal Zona Oeste",
      "tipo": "franquicia",
      "provincia": "Buenos Aires"
    }
  ],
  "nota": "Puedes asignar a cualquiera de estas sucursales"
}
```

---

## 💻 CLASES PHP

### Clase: UsuariosService

```php
<?php
namespace App\Service;

use App\Model\Usuario;

class UsuariosService {
    private $db;
    private $usuario; // Usuario actual autenticado
    
    public function __construct($db, $usuarioActual = null) {
        $this->db = $db;
        $this->usuario = $usuarioActual;
    }
    
    /**
     * Verificar si usuario actual puede crear un usuario de cierto rol
     */
    public function puedeCrearRol($rolActual, $rolACrear) {
        // ADMIN puede crear cualquier rol
        if ($rolActual === 1) return true;
        
        // Mapeo de qué roles puede crear cada rol
        $puedeCrear = [
            2 => [3, 4, 5, 6], // SUPERVISOR_PLANTA → [ADMIN_PLANTA, SUPERVISOR_SUCURSAL, ADMIN_SUCURSAL, OPERARIO]
            3 => [6],           // ADMIN_PLANTA → [OPERARIO]
            4 => [5, 6],        // SUPERVISOR_SUCURSAL → [ADMIN_SUCURSAL, OPERARIO]
            5 => [6],           // ADMIN_SUCURSAL → [OPERARIO]
        ];
        
        return isset($puedeCrear[$rolActual]) && in_array($rolACrear, $puedeCrear[$rolActual]);
    }
    
    /**
     * Obtener sucursales permitidas para asignar usuario
     */
    public function obtenerSucursalesPermitidas($rolACrear) {
        $rolActual = $this->usuario['id_rol'];
        
        if ($rolActual === 1) { // ADMIN
            // Acceso a todas
            $sql = "SELECT id, nombre FROM ubicaciones WHERE id > 0 ORDER BY nombre";
        } else if ($rolActual === 2) { // SUPERVISOR_PLANTA
            // Acceso a todas
            $sql = "SELECT id, nombre FROM ubicaciones ORDER BY nombre";
        } else if ($rolActual === 4) { // SUPERVISOR_SUCURSAL
            // Solo sus sucursales
            $sql = "
                SELECT u.id, u.nombre
                FROM ubicaciones u
                INNER JOIN usuario_sucursales us ON u.id = us.id_sucursal
                WHERE us.id_usuario = ? AND u.id > 0
                ORDER BY u.nombre
            ";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$this->usuario['usuario_id']]);
            return $stmt->fetchAll(\PDO::FETCH_ASSOC);
        }
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }
    
    /**
     * Crear usuario
     */
    public function crearUsuario($nombre, $apellido, $email, $usuario, $password, $idRol, $sucursales = [], $creadoPor) {
        // Validar permisos
        if (!$this->puedeCrearRol($this->usuario['id_rol'], $idRol)) {
            throw new \Exception("No tienes permiso para crear usuarios con este rol");
        }
        
        // Validar datos
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new \Exception("Email inválido");
        }
        
        if (!$this->validarPassword($password)) {
            throw new \Exception("Contraseña débil: mínimo 8 caracteres, letras y números");
        }
        
        if (strlen($usuario) < 4) {
            throw new \Exception("Usuario debe tener al menos 4 caracteres");
        }
        
        // Verificar duplicados
        $sql = "SELECT COUNT(*) as count FROM usuarios WHERE email = ? OR usuario = ?";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$email, $usuario]);
        $result = $stmt->fetch(\PDO::FETCH_ASSOC);
        
        if ($result['count'] > 0) {
            throw new \Exception("Email o usuario ya existe");
        }
        
        try {
            $this->db->beginTransaction();
            
            // Insertar usuario
            $passwordHash = password_hash($password, PASSWORD_BCRYPT);
            
            $sql = "
                INSERT INTO usuarios (nombre, apellido, email, usuario, password_hash, id_rol, estado, creado_por, fecha_creacion)
                VALUES (?, ?, ?, ?, ?, ?, 'ACTIVO', ?, NOW())
            ";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                $nombre,
                $apellido,
                $email,
                $usuario,
                $passwordHash,
                $idRol,
                $creadoPor
            ]);
            
            $nuevoUsuarioId = $this->db->lastInsertId();
            
            // Asignar sucursales
            if (!empty($sucursales)) {
                $sqlSucursal = "INSERT INTO usuario_sucursales (id_usuario, id_sucursal, tipo_relacion) VALUES (?, ?, ?)";
                $stmtSucursal = $this->db->prepare($sqlSucursal);
                
                foreach ($sucursales as $idSucursal) {
                    $stmtSucursal->execute([
                        $nuevoUsuarioId,
                        $idSucursal,
                        'GERENTE' // Por defecto
                    ]);
                }
            }
            
            // Registrar auditoría
            registrarAuditoria(
                $this->db,
                $this->usuario['usuario_id'],
                'USUARIOS',
                'CREAR',
                'usuarios',
                $nuevoUsuarioId,
                null,
                ['usuario' => $usuario, 'email' => $email, 'rol_id' => $idRol]
            );
            
            $this->db->commit();
            
            return [
                'id' => $nuevoUsuarioId,
                'usuario' => $usuario,
                'email' => $email,
                'rol_id' => $idRol
            ];
            
        } catch (\Exception $e) {
            $this->db->rollBack();
            throw $e;
        }
    }
    
    /**
     * Editar usuario
     */
    public function editarUsuario($idUsuario, $datos) {
        // Validar permiso
        if ($idUsuario !== $this->usuario['usuario_id']) {
            if (!in_array($this->usuario['id_rol'], [1, 2])) {
                throw new \Exception("No tienes permiso para editar otros usuarios");
            }
        }
        
        // Obtener usuario actual
        $sql = "SELECT * FROM usuarios WHERE id = ?";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$idUsuario]);
        $usuarioActual = $stmt->fetch(\PDO::FETCH_ASSOC);
        
        if (!$usuarioActual) {
            throw new \Exception("Usuario no encontrado");
        }
        
        $datosBefore = $usuarioActual;
        
        try {
            $this->db->beginTransaction();
            
            // Actualizar campos permitidos
            $campos = [];
            $valores = [];
            
            if (isset($datos['nombre'])) {
                $campos[] = "nombre = ?";
                $valores[] = $datos['nombre'];
            }
            
            if (isset($datos['apellido'])) {
                $campos[] = "apellido = ?";
                $valores[] = $datos['apellido'];
            }
            
            if (isset($datos['email'])) {
                if (!filter_var($datos['email'], FILTER_VALIDATE_EMAIL)) {
                    throw new \Exception("Email inválido");
                }
                $campos[] = "email = ?";
                $valores[] = $datos['email'];
            }
            
            if (isset($datos['password']) && !empty($datos['password'])) {
                if (!$this->validarPassword($datos['password'])) {
                    throw new \Exception("Contraseña débil");
                }
                $campos[] = "password_hash = ?";
                $valores[] = password_hash($datos['password'], PASSWORD_BCRYPT);
            }
            
            // Solo ADMIN puede cambiar rol
            if (isset($datos['id_rol']) && $this->usuario['id_rol'] === 1) {
                $campos[] = "id_rol = ?";
                $valores[] = $datos['id_rol'];
            }
            
            if (!empty($campos)) {
                $campos[] = "fecha_modificacion = NOW()";
                $sql = "UPDATE usuarios SET " . implode(", ", $campos) . " WHERE id = ?";
                $valores[] = $idUsuario;
                
                $stmt = $this->db->prepare($sql);
                $stmt->execute($valores);
            }
            
            // Actualizar sucursales si es necesario
            if (isset($datos['sucursales'])) {
                // Eliminar asignaciones actuales
                $sql = "DELETE FROM usuario_sucursales WHERE id_usuario = ?";
                $stmt = $this->db->prepare($sql);
                $stmt->execute([$idUsuario]);
                
                // Insertar nuevas
                if (!empty($datos['sucursales'])) {
                    $sql = "INSERT INTO usuario_sucursales (id_usuario, id_sucursal, tipo_relacion) VALUES (?, ?, ?)";
                    $stmt = $this->db->prepare($sql);
                    
                    foreach ($datos['sucursales'] as $idSucursal) {
                        $stmt->execute([$idUsuario, $idSucursal, 'GERENTE']);
                    }
                }
            }
            
            // Registrar auditoría
            registrarAuditoria(
                $this->db,
                $this->usuario['usuario_id'],
                'USUARIOS',
                'EDITAR',
                'usuarios',
                $idUsuario,
                $datosBefore,
                $datos
            );
            
            $this->db->commit();
            
            return true;
            
        } catch (\Exception $e) {
            $this->db->rollBack();
            throw $e;
        }
    }
    
    /**
     * Validar contraseña
     */
    private function validarPassword($password) {
        // Mínimo 8 caracteres, debe tener letras y números
        return strlen($password) >= 8 
            && preg_match('/[a-zA-Z]/', $password) 
            && preg_match('/[0-9]/', $password);
    }
    
    /**
     * Obtener lista de usuarios
     */
    public function obtenerLista($filtro = 'activos', $pagina = 1, $perPagina = 20) {
        $offset = ($pagina - 1) * $perPagina;
        
        $where = [];
        $params = [];
        
        if ($filtro === 'activos') {
            $where[] = "u.estado = 'ACTIVO'";
        } else if ($filtro === 'inactivos') {
            $where[] = "u.estado IN ('INACTIVO', 'SUSPENDIDO')";
        }
        
        // Restricción por rol actual
        if ($this->usuario['id_rol'] === 4) { // SUPERVISOR_SUCURSAL
            $where[] = "us.id_usuario = ?";
            $params[] = $this->usuario['usuario_id'];
        }
        
        $whereSQL = !empty($where) ? "WHERE " . implode(" AND ", $where) : "";
        
        $sql = "
            SELECT u.*, r.codigo as codigo_rol, COUNT(DISTINCT us.id_sucursal) as num_sucursales
            FROM usuarios u
            LEFT JOIN roles r ON u.id_rol = r.id
            LEFT JOIN usuario_sucursales us ON u.id = us.id_usuario
            {$whereSQL}
            GROUP BY u.id
            ORDER BY u.fecha_creacion DESC
            LIMIT ? OFFSET ?
        ";
        
        $params[] = $perPagina;
        $params[] = $offset;
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }
}
```

---

## 🎨 INTERFAZ FRONTEND

### Vista: Gestión de Usuarios

```html
<!DOCTYPE html>
<html>
<head>
    <title>Gestión de Usuarios</title>
    <link rel="stylesheet" href="css/adminlte.min.css">
    <link rel="stylesheet" href="css/custom.css">
</head>
<body>
<div class="wrapper">
    <div class="content-wrapper">
        <div class="content-header">
            <div class="row">
                <div class="col-sm-6">
                    <h1>Gestión de Usuarios</h1>
                </div>
                <div class="col-sm-6 text-right">
                    <button class="btn btn-success" id="btnNuevoUsuario" onclick="abrirFormularioNuevo()">
                        <i class="fas fa-user-plus"></i> Nuevo Usuario
                    </button>
                </div>
            </div>
        </div>
        
        <section class="content">
            <!-- Filtros -->
            <div class="box">
                <div class="box-header">
                    <h3>Filtros</h3>
                </div>
                <div class="box-body">
                    <div class="row">
                        <div class="col-md-3">
                            <label>Estado:</label>
                            <select id="filtroEstado" onchange="cargarUsuarios()">
                                <option value="activos">Activos</option>
                                <option value="inactivos">Inactivos</option>
                                <option value="todos">Todos</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label>Búsqueda:</label>
                            <input type="text" id="busquedaUsuario" placeholder="Nombre, email, usuario..." 
                                   onkeyup="cargarUsuarios()">
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Tabla de usuarios -->
            <div class="box">
                <div class="box-header">
                    <h3>Usuarios</h3>
                </div>
                <div class="box-body">
                    <table class="table table-striped table-hover">
                        <thead>
                            <tr>
                                <th>Usuario</th>
                                <th>Email</th>
                                <th>Rol</th>
                                <th>Sucursales</th>
                                <th>Estado</th>
                                <th>Último Acceso</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody id="tablaUsuarios">
                            <!-- Se llena dinámicamente -->
                        </tbody>
                    </table>
                </div>
            </div>
        </section>
    </div>
</div>

<!-- Modal: Crear/Editar Usuario -->
<div class="modal" id="modalUsuario">
    <div class="modal-content" style="width: 500px;">
        <div class="modal-header">
            <h4 id="modalTitulo">Nuevo Usuario</h4>
            <button class="close" onclick="cerrarModal()">&times;</button>
        </div>
        
        <form id="formUsuario">
            <div class="modal-body">
                <!-- Nombre -->
                <div class="form-group">
                    <label>Nombre *</label>
                    <input type="text" name="nombre" class="form-control" required>
                </div>
                
                <!-- Apellido -->
                <div class="form-group">
                    <label>Apellido *</label>
                    <input type="text" name="apellido" class="form-control" required>
                </div>
                
                <!-- Email -->
                <div class="form-group">
                    <label>Email *</label>
                    <input type="email" name="email" class="form-control" required>
                </div>
                
                <!-- Usuario (login) -->
                <div class="form-group">
                    <label>Usuario (login) *</label>
                    <input type="text" name="usuario" class="form-control" id="inputUsuario" required>
                    <small class="text-muted">Mínimo 4 caracteres, sin espacios</small>
                </div>
                
                <!-- Contraseña -->
                <div class="form-group">
                    <label id="labelPassword">Contraseña *</label>
                    <input type="password" name="password" class="form-control" id="inputPassword" required>
                    <small class="text-muted" id="notaPassword">Mínimo 8 caracteres, letras + números</small>
                </div>
                
                <!-- Rol -->
                <div class="form-group">
                    <label>Rol *</label>
                    <select name="id_rol" id="selectRol" class="form-control" required onchange="cargarSucursalesPermitidas()">
                        <option value="">-- Seleccionar --</option>
                        <!-- Llenar dinámicamente según permisos -->
                    </select>
                </div>
                
                <!-- Sucursales -->
                <div class="form-group" id="groupSucursales" style="display: none;">
                    <label>Sucursales Permitidas</label>
                    <div id="checkboxSucursales">
                        <!-- Llenar dinámicamente -->
                    </div>
                </div>
            </div>
            
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="cerrarModal()">Cancelar</button>
                <button type="submit" class="btn btn-primary" id="btnGuardarUsuario">Crear Usuario</button>
            </div>
        </form>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="js/usuarios.js"></script>
</body>
</html>
```

### JavaScript: usuarios.js

```javascript
let usuarioEnEdicion = null;
const tokenJWT = localStorage.getItem('token');

document.getElementById('formUsuario').addEventListener('submit', async (e) => {
    e.preventDefault();
    await guardarUsuario();
});

async function cargarUsuarios() {
    const filtro = document.getElementById('filtroEstado').value;
    const busqueda = document.getElementById('busquedaUsuario').value;
    
    const url = `/api/usuarios/listar?filtro=${filtro}&busqueda=${busqueda}`;
    
    try {
        const response = await fetch(url, {
            headers: { 'Authorization': `Bearer ${tokenJWT}` }
        });
        
        const data = await response.json();
        
        if (!data.success) {
            Swal.fire('Error', data.error, 'error');
            return;
        }
        
        const tabla = document.getElementById('tablaUsuarios');
        tabla.innerHTML = '';
        
        data.usuarios.forEach(usuario => {
            const fila = tabla.insertRow();
            
            const sucursalesTexto = usuario.sucursales && usuario.sucursales.length > 0
                ? usuario.sucursales.map(s => s.nombre).join(', ')
                : 'N/A';
            
            const estadoBadge = usuario.estado === 'ACTIVO' 
                ? '<span class="badge badge-success">ACTIVO</span>'
                : `<span class="badge badge-warning">${usuario.estado}</span>`;
            
            fila.innerHTML = `
                <td>${usuario.usuario}</td>
                <td>${usuario.email}</td>
                <td><span class="badge badge-primary">${usuario.rol}</span></td>
                <td>${sucursalesTexto}</td>
                <td>${estadoBadge}</td>
                <td>${usuario.ultimo_login || 'Nunca'}</td>
                <td>
                    <button class="btn btn-sm btn-primary" onclick="abrirFormularioEditar(${usuario.id})">
                        <i class="fas fa-edit"></i>
                    </button>
                    ${usuario.estado === 'ACTIVO' 
                        ? `<button class="btn btn-sm btn-warning" onclick="suspenderUsuario(${usuario.id})">
                             <i class="fas fa-ban"></i>
                           </button>`
                        : `<button class="btn btn-sm btn-success" onclick="reactivarUsuario(${usuario.id})">
                             <i class="fas fa-check"></i>
                           </button>`
                    }
                </td>
            `;
        });
        
    } catch (error) {
        console.error('Error:', error);
        Swal.fire('Error', 'Error al cargar usuarios', 'error');
    }
}

async function abrirFormularioNuevo() {
    usuarioEnEdicion = null;
    document.getElementById('modalTitulo').textContent = 'Nuevo Usuario';
    document.getElementById('inputUsuario').disabled = false;
    document.getElementById('inputPassword').required = true;
    document.getElementById('labelPassword').textContent = 'Contraseña *';
    document.getElementById('notaPassword').textContent = 'Mínimo 8 caracteres, letras + números';
    document.getElementById('btnGuardarUsuario').textContent = 'Crear Usuario';
    
    document.getElementById('formUsuario').reset();
    
    // Cargar roles permitidos
    await cargarRolesPermitidos();
    
    document.getElementById('modalUsuario').style.display = 'block';
}

async function cargarRolesPermitidos() {
    try {
        const response = await fetch('/api/usuarios/roles-permitidos', {
            headers: { 'Authorization': `Bearer ${tokenJWT}` }
        });
        
        const data = await response.json();
        
        if (!data.success) {
            Swal.fire('Error', data.error, 'error');
            return;
        }
        
        const select = document.getElementById('selectRol');
        select.innerHTML = '<option value="">-- Seleccionar --</option>';
        
        data.roles.forEach(rol => {
            const option = document.createElement('option');
            option.value = rol.id;
            option.textContent = rol.nombre;
            select.appendChild(option);
        });
        
    } catch (error) {
        console.error('Error:', error);
        Swal.fire('Error', 'Error al cargar roles', 'error');
    }
}

async function cargarSucursalesPermitidas() {
    const rolId = document.getElementById('selectRol').value;
    
    if (!rolId) {
        document.getElementById('groupSucursales').style.display = 'none';
        return;
    }
    
    try {
        const response = await fetch(`/api/usuarios/sucursales-permitidas?id_rol=${rolId}`, {
            headers: { 'Authorization': `Bearer ${tokenJWT}` }
        });
        
        const data = await response.json();
        
        if (!data.success) {
            return;
        }
        
        const container = document.getElementById('checkboxSucursales');
        container.innerHTML = '';
        
        if (data.sucursales && data.sucursales.length > 0) {
            document.getElementById('groupSucursales').style.display = 'block';
            
            data.sucursales.forEach(sucursal => {
                const label = document.createElement('label');
                label.style.display = 'block';
                label.innerHTML = `<input type="checkbox" name="sucursales" value="${sucursal.id}"> ${sucursal.nombre}`;
                container.appendChild(label);
            });
        } else {
            document.getElementById('groupSucursales').style.display = 'none';
        }
        
    } catch (error) {
        console.error('Error:', error);
    }
}

async function guardarUsuario() {
    const formData = new FormData(document.getElementById('formUsuario'));
    const datos = Object.fromEntries(formData);
    
    const sucursales = Array.from(document.querySelectorAll('input[name="sucursales"]:checked'))
        .map(cb => parseInt(cb.value));
    
    const payload = {
        nombre: datos.nombre,
        apellido: datos.apellido,
        email: datos.email,
        usuario: datos.usuario,
        id_rol: parseInt(datos.id_rol),
        sucursales: sucursales
    };
    
    if (datos.password && datos.password.trim()) {
        payload.password = datos.password;
    }
    
    const url = usuarioEnEdicion 
        ? `/api/usuarios/${usuarioEnEdicion}`
        : '/api/usuarios/crear';
    
    const metodo = usuarioEnEdicion ? 'PUT' : 'POST';
    
    try {
        const response = await fetch(url, {
            method: metodo,
            headers: {
                'Content-Type': 'application/json',
                'Authorization': `Bearer ${tokenJWT}`
            },
            body: JSON.stringify(payload)
        });
        
        const data = await response.json();
        
        if (data.success) {
            Swal.fire('Éxito', data.mensaje, 'success');
            cerrarModal();
            cargarUsuarios();
        } else {
            Swal.fire('Error', data.error, 'error');
        }
    } catch (error) {
        console.error('Error:', error);
        Swal.fire('Error', 'Error al guardar usuario', 'error');
    }
}

function cerrarModal() {
    document.getElementById('modalUsuario').style.display = 'none';
}

async function abrirFormularioEditar(idUsuario) {
    try {
        const response = await fetch(`/api/usuarios/${idUsuario}`, {
            headers: { 'Authorization': `Bearer ${tokenJWT}` }
        });
        
        const data = await response.json();
        const usuario = data.usuario;
        
        usuarioEnEdicion = idUsuario;
        
        document.getElementById('modalTitulo').textContent = `Editar Usuario: ${usuario.usuario}`;
        document.getElementById('inputUsuario').disabled = true;
        document.getElementById('inputPassword').required = false;
        document.getElementById('labelPassword').textContent = 'Contraseña (dejar vacío para no cambiar)';
        document.getElementById('notaPassword').textContent = 'Si deseas cambiar, mínimo 8 caracteres';
        document.getElementById('btnGuardarUsuario').textContent = 'Actualizar Usuario';
        
        // Llenar formulario
        document.getElementById('formUsuario').nombre.value = usuario.nombre;
        document.getElementById('formUsuario').apellido.value = usuario.apellido;
        document.getElementById('formUsuario').email.value = usuario.email;
        document.getElementById('formUsuario').usuario.value = usuario.usuario;
        document.getElementById('formUsuario').id_rol.value = usuario.id_rol;
        
        // Cargar sucursales
        await cargarSucursalesPermitidas();
        
        // Marcar sucursales del usuario
        usuario.sucursales.forEach(sucursal => {
            const checkbox = document.querySelector(`input[name="sucursales"][value="${sucursal.id}"]`);
            if (checkbox) checkbox.checked = true;
        });
        
        document.getElementById('modalUsuario').style.display = 'block';
        
    } catch (error) {
        console.error('Error:', error);
        Swal.fire('Error', 'Error al cargar usuario', 'error');
    }
}

async function suspenderUsuario(idUsuario) {
    const { value: dias } = await Swal.fire({
        title: 'Suspender Usuario',
        input: 'number',
        inputLabel: 'Días hasta reactivación (0 = permanente)',
        inputValue: '30',
        showCancelButton: true,
        inputValidator: (value) => {
            if (value < 0) return 'Debe ser mayor o igual a 0';
        }
    });
    
    if (dias === undefined) return;
    
    try {
        const response = await fetch(`/api/usuarios/${idUsuario}/estado`, {
            method: 'PATCH',
            headers: {
                'Content-Type': 'application/json',
                'Authorization': `Bearer ${tokenJWT}`
            },
            body: JSON.stringify({
                estado: 'SUSPENDIDO',
                dias_hasta_reactivar: parseInt(dias)
            })
        });
        
        const data = await response.json();
        
        if (data.success) {
            Swal.fire('Éxito', data.mensaje, 'success');
            cargarUsuarios();
        } else {
            Swal.fire('Error', data.error, 'error');
        }
    } catch (error) {
        console.error('Error:', error);
        Swal.fire('Error', 'Error al suspender usuario', 'error');
    }
}

// Cargar usuarios al iniciar
cargarUsuarios();
```

---

## ✅ VALIDACIONES

### Cliente (Frontend)

```javascript
✅ Email válido (RFC 5322)
✅ Contraseña mínimo 8 caracteres
✅ Contraseña debe tener letras y números
✅ Usuario mínimo 4 caracteres
✅ No espacios en usuario
✅ Sucursales requeridas para ciertos roles
```

### Servidor (Backend)

```php
✅ Usuario autenticado (JWT válido)
✅ Tiene permiso para crear/editar
✅ Email único en BD
✅ Usuario (login) único en BD
✅ Contraseña cumple criterios
✅ Sucursales permitidas según rol
✅ No puede editar usuario de nivel superior
✅ No puede cambiar rol (excepto ADMIN)
```

---

## 📝 AUDITORÍA

```sql
-- Se registra en tabla auditoria

INSERT INTO auditoria 
(id_usuario, id_rol, modulo, accion, tabla_afectada, id_registro, datos_despues)
VALUES 
(1, 2, 'USUARIOS', 'CREAR', 'usuarios', 10, 
 '{"usuario":"jgarcia","email":"juan@heladeria.com","rol_id":2}');

-- Ejemplo de datos guardados:
{
  "accion": "CREAR",
  "usuario_anterior": null,
  "usuario_nuevo": {
    "nombre": "Juan",
    "apellido": "García",
    "email": "juan@heladeria.com",
    "usuario": "jgarcia",
    "id_rol": 2,
    "estado": "ACTIVO"
  },
  "sucursales": [],
  "ejecutado_por": "admin",
  "fecha": "2025-11-29 14:30:00"
}
```

---

## ✅ CHECKLIST IMPLEMENTACIÓN

- [ ] Crear tabla `usuarios` con campos requeridos
- [ ] Crear tabla `usuario_sucursales` (N:N)
- [ ] Insertar roles fijos en tabla `roles`
- [ ] Implementar clase `UsuariosService`
- [ ] Crear endpoints API (CRUD)
- [ ] Crear formulario HTML
- [ ] Implementar validaciones frontend
- [ ] Implementar validaciones backend
- [ ] Tests de permisos por rol
- [ ] Tests de restricciones de sucursales
- [ ] Registrar auditoría en todas las acciones
- [ ] Tests de contraseña fuerte
- [ ] Tests de email único/usuario único

