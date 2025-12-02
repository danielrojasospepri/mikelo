# MATRIZ DETALLADA DE ROLES Y PERMISOS

## 📊 TABLA MAESTRA

```
╔═════════════════════╦════════╦══════════════╦══════════════╦═══════════════╦═══════════════╦═══════════╗
║ MÓDULO / ACCIÓN     ║ ADMIN  ║ SUPERVISOR   ║ ADMIN PLANTA ║ SUPERVISOR    ║ ADMIN         ║ OPERARIO  ║
║                    ║        ║ PLANTA       ║              ║ SUCURSAL      ║ SUCURSAL      ║           ║
╠═════════════════════╬════════╬══════════════╬══════════════╬═══════════════╬═══════════════╬═══════════╣
║ PRODUCTOS          ║        ║              ║              ║               ║               ║           ║
║ ├─ Crear           ║   ✅   ║      ✅      ║      ✅      ║       ❌      ║       ❌      ║     ❌    ║
║ ├─ Leer            ║   ✅   ║      ✅      ║      ✅      ║       ✅      ║       ✅      ║     ✅    ║
║ ├─ Editar          ║   ✅   ║      ✅      ║      ✅      ║       ❌      ║       ❌      ║     ❌    ║
║ └─ Eliminar        ║   ✅   ║      ❌      ║      ❌      ║       ❌      ║       ❌      ║     ❌    ║
╠═════════════════════╬════════╬══════════════╬══════════════╬═══════════════╬═══════════════╬═══════════╣
║ ENVÍOS             ║        ║              ║              ║               ║               ║           ║
║ ├─ Crear           ║   ✅   ║      ✅      ║      ✅      ║      ✅*      ║      ✅*      ║     ❌    ║
║ ├─ Leer            ║   ✅   ║      ✅      ║      ✅      ║      ✅*      ║      ✅*      ║     ✅*   ║
║ ├─ Editar          ║   ✅   ║      ✅      ║      ✅      ║      ✅*      ║      ✅*      ║     ❌    ║
║ ├─ Confirmar       ║   ✅   ║      ✅      ║      ✅      ║      ✅*      ║      ✅*      ║     ❌    ║
║ └─ Cancelar        ║   ✅   ║      ✅      ║      ✅      ║      ✅*      ║      ✅*      ║     ❌    ║
╠═════════════════════╬════════╬══════════════╬══════════════╬═══════════════╬═══════════════╬═══════════╣
║ PEDIDOS            ║        ║              ║              ║               ║               ║           ║
║ ├─ Crear           ║   ✅   ║      ✅      ║      ✅      ║      ✅*      ║      ✅*      ║     ❌    ║
║ ├─ Leer            ║   ✅   ║      ✅      ║      ✅      ║      ✅*      ║      ✅*      ║     ❌    ║
║ ├─ Editar          ║   ✅   ║      ✅      ║      ✅      ║      ✅*      ║      ✅*      ║     ❌    ║
║ ├─ Recibir         ║   ✅   ║      ✅      ║      ✅      ║      ✅*      ║      ✅*      ║     ❌    ║
║ └─ Ver tablero     ║   ✅   ║      ✅      ║      ✅      ║      ✅*      ║      ✅*      ║     ❌    ║
╠═════════════════════╬════════╬══════════════╬══════════════╬═══════════════╬═══════════════╬═══════════╣
║ STOCK DEPOSITO     ║        ║              ║              ║               ║               ║           ║
║ ├─ Leer            ║   ✅   ║      ✅      ║      ✅      ║       ✅      ║       ❌      ║     ✅    ║
║ ├─ Filtros         ║   ✅   ║      ✅      ║      ✅      ║       ✅      ║       ❌      ║     ✅    ║
║ └─ Reportes        ║   ✅   ║      ✅      ║      ✅      ║       ✅      ║       ❌      ║     ❌    ║
╠═════════════════════╬════════╬══════════════╬══════════════╬═══════════════╬═══════════════╬═══════════╣
║ STOCK SUCURSALES   ║        ║              ║              ║               ║               ║           ║
║ ├─ Leer            ║   ✅   ║      ✅      ║      ❌      ║      ✅*      ║      ✅*      ║     ✅*   ║
║ ├─ Recepciones     ║   ✅   ║      ✅      ║      ❌      ║      ✅*      ║      ✅*      ║     ❌    ║
║ ├─ Bajas           ║   ✅   ║      ✅      ║      ❌      ║      ✅*      ║      ✅*      ║     ✅*   ║
║ └─ Ajustes         ║   ✅   ║      ✅      ║      ✅      ║      ✅*      ║      ✅*      ║     ❌    ║
╠═════════════════════╬════════╬══════════════╬══════════════╬═══════════════╬═══════════════╬═══════════╣
║ USUARIOS           ║        ║              ║              ║               ║               ║           ║
║ ├─ Crear           ║   ✅   ║      ✅      ║      ❌      ║      ✅*      ║      ✅*      ║     ❌    ║
║ ├─ Leer            ║   ✅   ║      ✅      ║      ❌      ║      ✅*      ║      ✅*      ║     ❌    ║
║ ├─ Editar          ║   ✅   ║      ✅      ║      ❌      ║      ✅*      ║      ✅*      ║     ❌    ║
║ ├─ Suspender       ║   ✅   ║      ✅      ║      ❌      ║      ✅*      ║      ❌       ║     ❌    ║
║ └─ Eliminar        ║   ✅   ║      ❌      ║      ❌      ║      ❌       ║      ❌       ║     ❌    ║
╠═════════════════════╬════════╬══════════════╬══════════════╬═══════════════╬═══════════════╬═══════════╣
║ REPORTES           ║        ║              ║              ║               ║               ║           ║
║ ├─ Auditoría       ║   ✅   ║      ✅      ║      ❌      ║      ❌       ║      ❌       ║     ❌    ║
║ ├─ Stock (Central) ║   ✅   ║      ✅      ║      ✅      ║      ❌       ║      ❌       ║     ❌    ║
║ ├─ Stock (Sucursal)║   ✅   ║      ✅      ║      ❌      ║      ✅*      ║      ✅*      ║     ❌    ║
║ ├─ Ventas          ║   ✅   ║      ✅      ║      ❌      ║      ✅*      ║      ✅*      ║     ❌    ║
║ └─ Movimientos     ║   ✅   ║      ✅      ║      ✅      ║      ✅*      ║      ✅*      ║     ❌    ║
╚═════════════════════╩════════╩══════════════╩══════════════╩═══════════════╩═══════════════╩═══════════╝

*  = Solo en sus sucursales asignadas
✅ = Permiso permitido
❌ = Permiso denegado
```

---

## 📋 ESPECIFICACIONES POR MÓDULO

### 1️⃣ MÓDULO: PRODUCTOS

#### Permisos Detallados

| Rol | Crear | Leer | Editar | Eliminar | Notas |
|-----|-------|------|--------|----------|-------|
| **ADMIN** | ✅ Sí | ✅ Todos | ✅ Sí | ✅ Sí | Acceso total |
| **SUPERVISOR_PLANTA** | ✅ Sí | ✅ Todos | ✅ Sí | ❌ No | Solo anular/cambiar familia |
| **ADMIN_PLANTA** | ✅ Sí | ✅ Todos | ✅ Sí | ❌ No | Crear nuevos productos |
| **SUPERVISOR_SUCURSAL** | ❌ No | ✅ Stock disponible | ❌ No | ❌ No | Ver stock de sucursales |
| **ADMIN_SUCURSAL** | ❌ No | ✅ Stock disponible | ❌ No | ❌ No | Ver stock de su sucursal |
| **OPERARIO** | ❌ No | ✅ Escaneo | ❌ No | ❌ No | Solo lectura para escaneo |

#### Campos Visibles por Rol

```
ADMIN / SUPERVISOR_PLANTA / ADMIN_PLANTA:
  - Código, Descripción, Familia, Familia Detallada, Peso, Precio
  - Stock Depósito (cantidad, peso)
  - Historial de movimientos
  - Contenedor asignado

SUPERVISOR_SUCURSAL / ADMIN_SUCURSAL:
  - Código, Descripción, Familia
  - Stock Depósito (solo lectura)
  - Stock de sus sucursales

OPERARIO:
  - Código, Descripción, Peso
  - Stock Depósito (para validar disponibilidad)
```

---

### 2️⃣ MÓDULO: ENVÍOS

#### Permisos Detallados

| Rol | Crear | Leer | Editar | Confirmar | Cancelar | Notas |
|-----|-------|------|--------|-----------|----------|-------|
| **ADMIN** | ✅ Todos | ✅ Todos | ✅ Todos | ✅ Sí | ✅ Sí | Acceso total |
| **SUPERVISOR_PLANTA** | ✅ Todos | ✅ Todos | ✅ Todos | ✅ Sí | ✅ Sí | Supervisa todo |
| **ADMIN_PLANTA** | ✅ Todos | ✅ Todos | ✅ Todos | ✅ Sí | ✅ Sí | Gestiona planta |
| **SUPERVISOR_SUCURSAL** | ✅ A sus sucursales* | ✅ Sus sucursales* | ✅ Sus envíos* | ✅ Sí | ✅ Sí | Destino = sucursal |
| **ADMIN_SUCURSAL** | ✅ A su sucursal* | ✅ Su sucursal* | ✅ Sus envíos* | ✅ Sí | ✅ Sí | Destino = su sucursal |
| **OPERARIO** | ❌ No | ✅ Lectura* | ❌ No | ❌ No | ❌ No | Solo ver estado |

#### Restricciones por Rol

```php
SUPERVISOR_SUCURSAL:
  - Puede crear envío SOLO si destino está en sus sucursales asignadas
  - Ve SOLO envíos de sus sucursales
  - Puede editar envío SOLO si destino es su sucursal

ADMIN_SUCURSAL:
  - Puede crear envío SOLO si destino es su sucursal asignada
  - Ve SOLO envíos a su sucursal
  - Puede editar SOLO si es su sucursal

OPERARIO:
  - Ve estado de envíos de su contexto
  - Puede registrar escaneo de recepción
```

#### Estados que puede transicionar cada rol

```
ADMIN / SUPERVISOR_PLANTA / ADMIN_PLANTA:
  NUEVO → ENVIADO → RECIBIDO ✅
  NUEVO → CANCELADO ✅
  ENVIADO → CANCELADO ✅

SUPERVISOR_SUCURSAL / ADMIN_SUCURSAL:
  ENVIADO → RECIBIDO ✅
  ENVIADO → PARCIAL ✅

OPERARIO:
  Solo lectura (ver estado)
```

---

### 3️⃣ MÓDULO: PEDIDOS

#### Permisos Detallados

| Rol | Crear | Leer | Editar | Recibir | Tablero | Notas |
|-----|-------|------|--------|---------|---------|-------|
| **ADMIN** | ✅ Sí | ✅ Todos | ✅ Sí | ✅ Sí | ✅ Sí | Ver todo |
| **SUPERVISOR_PLANTA** | ✅ Sí | ✅ Todos | ✅ Sí | ✅ Sí | ✅ Sí | Supervisa todo |
| **ADMIN_PLANTA** | ✅ Sí | ✅ Todos | ✅ Sí | ✅ Sí | ✅ Sí | Gestiona producción |
| **SUPERVISOR_SUCURSAL** | ✅ De sus sucursales | ✅ Sus pedidos* | ✅ Sus pedidos* | ✅ Sí | ✅ Sus sucursales* | Sucursal origen |
| **ADMIN_SUCURSAL** | ✅ De su sucursal | ✅ Su sucursal* | ✅ Su sucursal* | ❌ No | ❌ No | Solo su sucursal |
| **OPERARIO** | ❌ No | ❌ No | ❌ No | ❌ No | ❌ No | No accede |

#### Flujo de Estados

```
PENDIENTE (creado en sucursal)
    ↓ (Planta valida stock)
ACEPTADO o RECHAZADO
    ↓ (si aceptado)
PREPARACION (armar pedido)
    ↓
LISTO_ENVIO (espera despacho)
    ↓
ENVIADO (en tránsito)
    ↓
RECIBIDO (en sucursal)

Estados finales: RECHAZADO, RECIBIDO

Transiciones permitidas por rol:
- ADMIN: Cualquier transición
- SUPERVISOR_PLANTA: Cualquier transición
- ADMIN_PLANTA: PENDIENTE → ACEPTADO/RECHAZADO → PREPARACION → LISTO_ENVIO → ENVIADO
- SUPERVISOR_SUCURSAL: Crear pedido, ver estado
- ADMIN_SUCURSAL: Ver estado
```

---

### 4️⃣ MÓDULO: STOCK (Depósito Central)

#### Permisos Detallados

| Rol | Leer | Filtrar | Exportar | Ajustes | Notas |
|-----|------|---------|----------|---------|-------|
| **ADMIN** | ✅ Todo | ✅ Sí | ✅ Sí | ✅ Sí | Acceso total |
| **SUPERVISOR_PLANTA** | ✅ Todo | ✅ Sí | ✅ Sí | ✅ Sí | Monitoreo total |
| **ADMIN_PLANTA** | ✅ Todo | ✅ Sí | ✅ Sí | ✅ Sí | Gestión de stock |
| **SUPERVISOR_SUCURSAL** | ✅ Lectura | ✅ Disponible | ✅ PDF | ❌ No | Ver disponibilidad |
| **ADMIN_SUCURSAL** | ❌ No | ❌ No | ❌ No | ❌ No | Sin acceso |
| **OPERARIO** | ✅ Lectura | ❌ No | ❌ No | ❌ No | Solo para validar |

#### Filtros Disponibles por Rol

```
ADMIN / SUPERVISOR_PLANTA / ADMIN_PLANTA:
  - Por familia
  - Por código de producto
  - Por cantidad (mayor que, menor que)
  - Por estado (agotado, bajo, normal)
  - Por contenedor
  - Por fecha de entrada

SUPERVISOR_SUCURSAL:
  - Ver solo stock disponible (>0)
  - Filtrar por familia
  - Filtrar por cantidad

OPERARIO:
  - Búsqueda simple por código
  - Ver cantidad y peso
```

---

### 5️⃣ MÓDULO: STOCK (Sucursales)

#### Permisos Detallados

| Acción | ADMIN | SUPER_PLANTA | ADMIN_PLANTA | SUPER_SUCURSAL | ADMIN_SUCURSAL | OPERARIO |
|--------|-------|--------------|--------------|----------------|----------------|----------|
| Leer Stock | ✅ Todas | ✅ Todas | ❌ No | ✅ Sus sucursales* | ✅ Su sucursal* | ✅ Solo lectura* |
| Recibir Envío | ✅ Todas | ✅ Todas | ❌ No | ✅ Sus sucursales* | ✅ Su sucursal* | ❌ No |
| Baja de Stock | ✅ Todas | ✅ Todas | ❌ No | ✅ Sus sucursales* | ✅ Su sucursal* | ✅ Por etiqueta* |
| Ajuste Manual | ✅ Todas | ✅ Todas | ✅ Todas | ✅ Sus sucursales* | ✅ Su sucursal* | ❌ No |

#### Restricciones por Rol - Baja de Stock

```
SUPERVISOR_SUCURSAL:
  - Baja por etiqueta en sus sucursales
  - Baja manual (reconciliación) en sus sucursales
  - Ver historial de bajas

ADMIN_SUCURSAL:
  - Baja por etiqueta en su sucursal
  - Ver historial de bajas
  - NO puede hacer ajustes manuales

OPERARIO (en sucursal):
  - Baja por etiqueta únicamente
  - NO puede ajustar manualmente
```

---

### 6️⃣ MÓDULO: USUARIOS (ABM)

#### Permisos de Gestión

| Acción | ADMIN | SUPER_PLANTA | ADMIN_PLANTA | SUPER_SUCURSAL | ADMIN_SUCURSAL | OPERARIO |
|--------|-------|--------------|--------------|----------------|----------------|----------|
| Crear usuario | ✅ | ✅ | ❌ | ✅* | ✅* | ❌ |
| Leer usuarios | ✅ | ✅ | ❌ | ✅* | ✅* | ❌ |
| Editar usuario | ✅ | ✅ | ❌ | ✅* | ✅* | ❌ |
| Cambiar rol | ✅ | ✅ | ❌ | ✅* | ❌ | ❌ |
| Suspender | ✅ | ✅ | ❌ | ✅* | ❌ | ❌ |
| Reactivar | ✅ | ✅ | ❌ | ✅* | ❌ | ❌ |
| Eliminar | ✅ | ❌ | ❌ | ❌ | ❌ | ❌ |

#### Restricciones de Creación por Rol

```python
ADMIN:
  Puede crear: ADMIN, SUPERVISOR_PLANTA, ADMIN_PLANTA, SUPERVISOR_SUCURSAL, ADMIN_SUCURSAL, OPERARIO
  Asignar a: Cualquier sucursal

SUPERVISOR_PLANTA:
  Puede crear: ADMIN_PLANTA, SUPERVISOR_SUCURSAL, ADMIN_SUCURSAL, OPERARIO
  NO puede crear: ADMIN, otro SUPERVISOR_PLANTA
  Asignar a: Cualquier sucursal

ADMIN_PLANTA:
  Puede crear: Solo OPERARIO
  Asignar a: Solo depósito (id_ubicacion = 1)

SUPERVISOR_SUCURSAL:
  Puede crear: ADMIN_SUCURSAL, OPERARIO
  Asignar a: SOLO sus sucursales asignadas
  NO puede crear: SUPERVISOR_PLANTA, ADMIN_PLANTA

ADMIN_SUCURSAL:
  Puede crear: OPERARIO
  Asignar a: SOLO su sucursal asignada
  NO puede cambiar roles
```

#### Campos Editables por Rol

```
ADMIN / SUPERVISOR_PLANTA:
  - Nombre, Apellido, Email, Usuario, Contraseña
  - Rol, Estado (Activo/Inactivo/Suspendido)
  - Sucursales asignadas

SUPERVISOR_SUCURSAL:
  - Nombre, Apellido, Email, Contraseña (del usuario)
  - Solo de usuarios en sus sucursales
  - NO puede cambiar rol

ADMIN_SUCURSAL:
  - Solo ver usuarios
  - NO puede editar
```

---

### 7️⃣ MÓDULO: REPORTES

#### Auditoría

| Rol | Acceso |
|-----|--------|
| ADMIN | ✅ Auditoría completa |
| SUPERVISOR_PLANTA | ✅ Auditoría de operaciones (excluye usuarios/seguridad) |
| ADMIN_PLANTA | ❌ Sin acceso |
| SUPERVISOR_SUCURSAL | ❌ Sin acceso |
| ADMIN_SUCURSAL | ❌ Sin acceso |
| OPERARIO | ❌ Sin acceso |

#### Stock Central

| Rol | Acceso | Campos |
|-----|--------|--------|
| ADMIN | ✅ Completo | Todos |
| SUPERVISOR_PLANTA | ✅ Completo | Todos |
| ADMIN_PLANTA | ✅ Completo | Todos |
| SUPERVISOR_SUCURSAL | ✅ Lecturasolamente | Código, Descripción, Cantidad, Disponible |
| ADMIN_SUCURSAL | ❌ Sin acceso | - |
| OPERARIO | ❌ Sin acceso | - |

#### Ventas/Bajas por Sucursal

| Rol | Acceso | Ver |
|-----|--------|-----|
| ADMIN | ✅ Todas | Todas las sucursales |
| SUPERVISOR_PLANTA | ✅ Todas | Todas las sucursales |
| ADMIN_PLANTA | ❌ No | - |
| SUPERVISOR_SUCURSAL | ✅ Sus sucursales* | Sus sucursales |
| ADMIN_SUCURSAL | ✅ Su sucursal* | Su sucursal solamente |
| OPERARIO | ❌ No | - |

---

## 🔒 REGLAS DE CONTEXTO

### Regla 1: Sucursales Permitidas

```php
// En cada request, validar:

if ($usuario['rol'] === 'SUPERVISOR_SUCURSAL') {
    // Solo permite acceso a datos de sus sucursales
    $sucursalesDelUsuario = obtenerSucursalesDelUsuario($usuario['id']);
    
    if (!in_array($idSucursalSolicitada, $sucursalesDelUsuario)) {
        throw new Exception("Acceso denegado a esta sucursal");
    }
}

if ($usuario['rol'] === 'ADMIN_SUCURSAL') {
    // Solo acceso a su ÚNICA sucursal
    $sucursalDelUsuario = obtenerSucursalDelUsuario($usuario['id']);
    
    if ($sucursalDelUsuario !== $idSucursalSolicitada) {
        throw new Exception("Acceso denegado");
    }
}
```

### Regla 2: Visibilidad de Datos

```javascript
// Frontend debe filtrar datos según rol

if (usuarioActual.rol === 'SUPERVISOR_SUCURSAL') {
    // Mostrar solo stock de sus sucursales
    datos = datos.filter(item => 
        sucursalesPermitidas.includes(item.id_sucursal)
    );
}

if (usuarioActual.rol === 'ADMIN_SUCURSAL') {
    // Mostrar solo su sucursal
    datos = datos.filter(item => 
        item.id_sucursal === suculrsalDelUsuario
    );
}
```

### Regla 3: Auditoría Obligatoria

```php
// TODA acción debe registrarse

registrarAuditoria(
    db: $db,
    idUsuario: $usuario['id'],
    modulo: 'ENVIOS',
    accion: 'CREAR',
    tabla: 'envios',
    idRegistro: $idEnvio,
    datosAntes: null,
    datosDespues: $envioData
);
```

### Regla 4: Validación de Permisos en API

```php
// En CADA endpoint, validar:

// 1. ¿Usuario autenticado?
if (!$usuario = $request->getAttribute('usuario')) {
    return responseJson($response, 401, ['error' => 'No autenticado'], false);
}

// 2. ¿Tiene permiso para este módulo/acción?
if (!tienePermiso($usuario, 'ENVIOS', 'CREAR')) {
    return responseJson($response, 403, ['error' => 'Sin permiso'], false);
}

// 3. ¿Acceso al contexto (sucursal)?
if (!puedeAccederA($usuario, $idSucursal)) {
    return responseJson($response, 403, ['error' => 'Sin acceso a esta sucursal'], false);
}

// 4. Proceder...
```

---

## 📝 RESUMEN DE RESTRICCIONES ESPECIALES

### Franquicias vs Propiedad del Dueño

```
PROPIETARIO (Sucursales 2,5):
  - SUPERVISOR_SUCURSAL puede crear ADMIN_SUCURSAL
  - Ve todas las operaciones de sus sucursales
  - Reportes completos

FRANQUICIA (Sucursales 3,4,6...):
  - Acceso limitado a su sucursal
  - No puede ver sucursales de otros
  - Reportes solo de su sucursal
```

### Jerarquía de Creación de Usuarios

```
ADMIN (nivel 0)
  ↓
SUPERVISOR_PLANTA (nivel 1)
  ├─→ ADMIN_PLANTA (nivel 2)
  └─→ SUPERVISOR_SUCURSAL (nivel 3)
        ├─→ ADMIN_SUCURSAL (nivel 4)
        └─→ OPERARIO (nivel 5)

Un usuario SOLO PUEDE CREAR usuarios de nivel superior al suyo.
```

---

## 🎯 CASOS DE USO EJEMPLOS

### Caso 1: ADMIN crea SUPERVISOR_SUCURSAL para franquicia

```php
$admin->crearUsuario([
    'nombre' => 'Carlos',
    'rol' => 'SUPERVISOR_SUCURSAL',
    'sucursales' => [3] // Franquicia #3
]);

Resultado: Carlos ve SOLO sucursal #3, puede crear ADMIN_SUCURSAL y OPERARIOS
```

### Caso 2: SUPERVISOR_PLANTA crea ADMIN_PLANTA

```php
$supervisor->crearUsuario([
    'nombre' => 'María',
    'rol' => 'ADMIN_PLANTA',
    'sucursales' => [1] // Depósito central
]);

Resultado: María gestiona operaciones de planta, ve stock central, envíos
```

### Caso 3: SUPERVISOR_SUCURSAL intenta crear ADMIN_PLANTA

```php
// BLOQUEADO - Error: "No puedes crear usuarios de nivel superior"
```

### Caso 4: OPERARIO intenta leer auditoría

```php
// BLOQUEADO - Error: "No tienes permiso para acceder a reportes"
```

---

## ✅ CHECKLIST IMPLEMENTACIÓN

- [ ] Crear todas las tablas
- [ ] Insertar roles fijos
- [ ] Implementar clase JWTHandler
- [ ] Crear endpoints LOGIN/LOGOUT
- [ ] Crear middleware de autenticación
- [ ] Implementar validaciones de permisos
- [ ] Crear ABM de usuarios
- [ ] Implementar auditoría en todas las acciones
- [ ] Tests de permisos por rol
- [ ] Tests de contexto por sucursal
- [ ] Actualizar header con usuario/rol
- [ ] Documentar en Postman/Swagger

