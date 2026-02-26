# FASE 2 - Estado de Implementación

**Fecha:** Junio 2025  
**Versión:** 2.0-alpha

---

## ✅ COMPLETADO

### 1. Base de Datos - Scripts de Migración

| Archivo | Descripción | Estado |
|---------|-------------|--------|
| `database/migracion_fase2.sql` | Script principal de migración v1.1 | ✅ Listo |
| `database/migracion_fase2_rollback.sql` | Script de rollback v1.1 | ✅ Listo |

**Cambios en BD:**
- ALTER `ubicaciones` → nuevo campo `tipo_ubicacion`
- ALTER `productos` → nuevo campo `disponible_franquicias`
- ALTER `usuarios` → campos adicionales (apellido, email, ultimo_login, creado_por)
- ALTER `roles` → campos adicionales (nivel, descripcion)
- CREATE `usuario_roles` → Relación N:N usuarios-roles
- CREATE `usuario_sucursales` → Asignación usuarios a sucursales
- CREATE `sesiones` → Manejo de sesiones PHP
- CREATE `pedidos` + `pedido_items` → Pedidos de franquicias
- CREATE `pedido_envio` + `pedido_envio_items` → Vinculación pedido-envío
- CREATE `recepciones` + `recepcion_items` → Confirmación de recepciones
- CREATE `stock_sucursal` + `stock_sucursal_movimientos` → Stock por sucursal

### 2. Backend PHP - Modelos

| Archivo | Descripción | Estado |
|---------|-------------|--------|
| `api/src/Model/Usuario.php` | Autenticación y gestión usuarios | ✅ Listo |
| `api/src/Model/Sesion.php` | Manejo de sesiones BD | ✅ Listo |
| `api/src/Model/Pedido.php` | CRUD pedidos franquicias | ✅ Listo |
| `api/src/Model/Recepcion.php` | Confirmación recepciones | ✅ Listo |
| `api/src/Model/StockSucursal.php` | Consulta stock sucursales | ✅ Listo |

### 3. Backend PHP - Controladores

| Archivo | Descripción | Estado |
|---------|-------------|--------|
| `api/src/Controller/AuthController.php` | Login, logout, validación | ✅ Listo |
| `api/src/Controller/PedidoController.php` | API pedidos | ✅ Listo |
| `api/src/Controller/RecepcionController.php` | API recepciones | ✅ Listo |
| `api/src/Controller/StockSucursalController.php` | API stock sucursales | ✅ Listo |

### 4. Backend PHP - Middleware

| Archivo | Descripción | Estado |
|---------|-------------|--------|
| `api/src/Middleware/AuthMiddleware.php` | Verificación auth + permisos | ✅ Listo |

### 5. Rutas API

| Archivo | Descripción | Estado |
|---------|-------------|--------|
| `api/routes_fase2.php` | Todas las rutas nuevas | ✅ Listo |
| `api/index.php` | Modificado para incluir Fase 2 | ✅ Listo |

### 6. Frontend HTML/JS

| Archivo | Descripción | Estado |
|---------|-------------|--------|
| `js/auth.js` | Módulo de autenticación JS | ✅ Listo |
| `login.html` | Página de login | ✅ Listo |
| `stock_sucursal.html` | Ver stock de la sucursal | ✅ Listo |
| `pedidos_sucursal.html` | Crear/ver pedidos (franquicias) | ✅ Listo |
| `recepciones.html` | Confirmar recepciones de envíos | ✅ Listo |

---

## 📋 ENDPOINTS API FASE 2

### Autenticación
```
POST   /auth/login              → Login (público)
POST   /auth/logout             → Logout
GET    /auth/validar            → Validar token
GET    /auth/me                 → Info usuario actual
POST   /auth/cambiar-password   → Cambiar contraseña
```

### Pedidos (Franquicias)
```
GET    /pedidos                       → Listar pedidos (filtrado por rol)
POST   /pedidos                       → Crear pedido (solo franquicias)
GET    /pedidos/pendientes            → Pedidos pendientes (solo planta)
GET    /pedidos/productos-disponibles → Productos para pedir
GET    /pedidos/{id}                  → Detalle pedido
PUT    /pedidos/{id}/enviar           → Marcar como enviado (solo planta)
PUT    /pedidos/{id}/anular           → Anular pedido
```

### Recepciones (Sucursales)
```
GET    /recepciones                    → Historial recepciones
POST   /recepciones                    → Confirmar recepción
GET    /recepciones/envios-pendientes  → Envíos pendientes de recibir
GET    /recepciones/envio/{idEnvio}    → Detalle envío a recepcionar
GET    /recepciones/{id}               → Detalle recepción
```

### Stock Sucursales
```
GET    /stock-sucursal                      → Stock de mi sucursal
GET    /stock-sucursal/buscar               → Buscar productos
GET    /stock-sucursal/resumen              → Dashboard resumen
GET    /stock-sucursal/todas                → Stock todas sucursales (admin)
GET    /stock-sucursal/producto/{id}        → Detalle + historial producto
```

### Usuarios y Roles
```
GET    /usuarios    → Listar usuarios (admin)
GET    /roles       → Listar roles
GET    /sucursales  → Listar sucursales
```

---

## ⏳ PENDIENTE

### Testing
- [ ] Ejecutar migración de BD en entorno de pruebas
- [ ] Tests manuales de endpoints
- [ ] Tests de flujo completo (pedido → envío → recepción)

### Mejoras Futuras (Fase 3)
- [ ] Tests automatizados
- [ ] Notificaciones push/email
- [ ] Reportes avanzados
- [ ] Auditoría completa

---

## 🚀 PASOS PARA DEPLOY

### Pre-requisitos
1. Backup completo de la BD actual
2. Verificar que no hay transacciones en curso

### Ejecución
```bash
# 1. Subir archivos PHP nuevos
# - api/src/Model/*.php (nuevos)
# - api/src/Controller/*.php (nuevos)
# - api/src/Middleware/AuthMiddleware.php
# - api/routes_fase2.php
# - api/index.php (modificado)

# 2. Ejecutar migración de BD
mysql -u usuario -p mikelo < database/migracion_fase2.sql

# 3. Verificar que Fase 1 sigue funcionando
curl https://dominio/api/test
curl https://dominio/api/productos/buscar?q=helado

# 4. Probar endpoints nuevos
curl https://dominio/api/auth/login -X POST -d '{"usuario":"admin","password":"xxx"}'
```

### Rollback (si hay problemas)
```bash
# 1. Descomentar require en api/index.php o quitar routes_fase2.php

# 2. Ejecutar rollback de BD
mysql -u usuario -p mikelo < database/migracion_fase2_rollback.sql

# 3. Restaurar api/index.php original
```

---

## 📊 Niveles de Rol

| Nivel | Rol | Permisos |
|-------|-----|----------|
| 10 | ADMIN | Acceso total |
| 20 | PLANTA_JEFE | Depósito central, operaciones completas |
| 25 | PLANTA_OPERARIO | Depósito central, operaciones limitadas |
| 30 | FRANQUICIA_ADMIN | Administrador de sucursal |
| 40 | FRANQUICIA_EMPLEADO | Empleado de sucursal |

---

## 🔐 Autenticación

### Flujo
1. Login → `POST /auth/login` con `{usuario, password}`
2. Recibe token → Guardar en localStorage o cookie
3. Peticiones → Header `Authorization: Bearer {token}`
4. Token expira → 8 horas (configurable en Sesion.php)

### Headers requeridos
```http
Authorization: Bearer {token}
Content-Type: application/json
```

---

## 📝 Notas Importantes

1. **BOM UTF-8**: Si se edita `Envio.php`, ejecutar `fix_bom.bat`

2. **Orden de rutas**: En Slim, rutas estáticas van ANTES que rutas con parámetros

3. **Migración gradual**: El sistema detecta passwords en texto plano y los hashea automáticamente al primer login

4. **Compatibilidad**: Las rutas de Fase 1 siguen funcionando sin autenticación

5. **BasePath**: Actualmente `/test/api` - cambiar a `/api` en producción
