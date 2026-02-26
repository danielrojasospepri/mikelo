# GUÍA DE MIGRACIÓN - FASE 2

## 📋 Resumen

Este documento describe los pasos para ejecutar la migración de base de datos de Fase 2.

**Archivos de migración:**
- `migracion_fase2.sql` - Script principal de migración
- `migracion_fase2_rollback.sql` - Script de reversión (emergencia)

---

## ⚠️ PRE-REQUISITOS

### Antes de ejecutar:

1. **BACKUP COMPLETO** de la base de datos
   ```sql
   mysqldump -u usuario -p nombre_bd > backup_pre_fase2_$(date +%Y%m%d_%H%M%S).sql
   ```

2. **Verificar conexión** a la base de datos

3. **Ambiente de prueba** - Ejecutar primero en desarrollo

4. **Horario de baja actividad** - Preferiblemente fuera de horario laboral

---

## 🚀 PASOS DE EJECUCIÓN

### Paso 1: Backup
```bash
# Crear backup completo
mysqldump -u [usuario] -p [base_datos] > backup_fase1_completo.sql

# Verificar que el backup no está vacío
ls -la backup_fase1_completo.sql
```

### Paso 2: Ejecutar migración en DESARROLLO
```bash
# Conectar a la base de datos de desarrollo
mysql -u [usuario] -p [base_datos_dev] < database/migracion_fase2.sql

# Verificar resultado
mysql -u [usuario] -p [base_datos_dev] -e "SHOW TABLES LIKE '%pedido%';"
```

### Paso 3: Verificar migración
```sql
-- Verificar tablas creadas
SELECT table_name FROM information_schema.tables 
WHERE table_schema = DATABASE() 
AND table_name IN ('roles', 'usuarios', 'pedidos', 'recepciones', 'stock_sucursal');

-- Verificar roles insertados
SELECT * FROM roles;

-- Verificar usuario admin
SELECT id, nombre, usuario, activo FROM usuarios;

-- Verificar columnas nuevas en ubicaciones
DESCRIBE ubicaciones;

-- Verificar columnas nuevas en productos
DESCRIBE productos;
```

### Paso 4: Probar funcionalidad existente
- [ ] Alta de productos en depósito funciona
- [ ] Crear envío funciona
- [ ] Listar stock en depósito funciona
- [ ] Todas las queries existentes funcionan

### Paso 5: Ejecutar en PRODUCCIÓN
```bash
# Solo después de verificar que todo funciona en desarrollo
mysql -u [usuario] -p [base_datos_prod] < database/migracion_fase2.sql
```

---

## 🔄 ROLLBACK (Solo si hay problemas)

```bash
# Si algo sale mal, revertir todo
mysql -u [usuario] -p [base_datos] < database/migracion_fase2_rollback.sql

# O restaurar desde backup
mysql -u [usuario] -p [base_datos] < backup_fase1_completo.sql
```

---

## 📊 TABLAS CREADAS

| Tabla | Propósito |
|-------|-----------|
| `roles` | Definición de roles del sistema |
| `usuarios` | Usuarios del sistema |
| `usuario_roles` | Relación N:N usuario ↔ roles |
| `usuario_sucursales` | Relación N:N usuario ↔ sucursales |
| `sesiones` | Manejo de sesiones de login |
| `pedidos` | Pedidos de sucursales |
| `pedido_items` | Items de cada pedido |
| `pedido_envio` | Relación N:N pedido ↔ envío |
| `pedido_envio_items` | Detalle de items en cada relación |
| `recepciones` | Confirmación de recepción de envíos |
| `recepcion_items` | Detalle de items recibidos |
| `stock_sucursal` | Stock actual por sucursal |
| `stock_sucursal_movimientos` | Historial de movimientos |

---

## 📝 COLUMNAS AGREGADAS A TABLAS EXISTENTES

### Tabla `ubicaciones`
| Columna | Tipo | Default | Propósito |
|---------|------|---------|-----------|
| `tipo_ubicacion` | ENUM | 'SUCURSAL_PROPIA' | Distinguir franquicias |

### Tabla `productos`
| Columna | Tipo | Default | Propósito |
|---------|------|---------|-----------|
| `disponible_franquicias` | BOOLEAN | TRUE | Filtrar productos para franquicias |

---

## ✅ CHECKLIST POST-MIGRACIÓN

- [ ] Backup realizado y guardado
- [ ] Script ejecutado sin errores
- [ ] Tablas nuevas visibles
- [ ] Roles insertados correctamente
- [ ] Usuario admin creado
- [ ] Login existente NO afectado
- [ ] Alta de productos funciona
- [ ] Crear envío funciona
- [ ] Stock depósito se calcula igual

---

## 🔐 CREDENCIALES INICIALES

**Usuario administrador:**
- Usuario: `admin`
- Password: `admin123`
- **CAMBIAR INMEDIATAMENTE EN PRODUCCIÓN**

Para cambiar la contraseña:
```sql
UPDATE usuarios 
SET password_hash = '$2y$10$[nuevo_hash]' 
WHERE usuario = 'admin';
```

Generar hash en PHP:
```php
echo password_hash('nueva_contraseña_segura', PASSWORD_DEFAULT);
```

---

## 📞 SOPORTE

Si hay problemas durante la migración:
1. NO ejecutar más comandos
2. Documentar el error exacto
3. Ejecutar rollback si es necesario
4. Contactar al equipo de desarrollo

---

**Última actualización:** 23 de Enero de 2026
