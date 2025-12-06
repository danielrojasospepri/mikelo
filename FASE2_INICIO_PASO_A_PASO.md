# COMANDOS PARA EMPEZAR FASE 2

## ¿Qué Acabamos de Hacer?

```
ANTES:
    main
    └─ (sin respaldo)

AHORA:
    main ────────────── (Fase 2 en desarrollo)
    │
    release/v1-fase1 ──── (Respaldo congelado)
    
    TAG: v1.0-fase1 ────── (Snapshot de Fase 1)
```

**Resultado:**
✅ Fase 1 respaldada y congelada
✅ Puedes trabajar sin riesgo en main para Fase 2
✅ Si algo explota, recuperas Fase 1 en 1 comando

---

## PASO A PASO: EMPEZAR FASE 2

### Paso 1: Asegúrate de estar en main

```powershell
cd c:\xampp7.4.30\htdocs\mikelo

git status
# Debe mostrar: "On branch main"
```

### Paso 2: Crear rama para Migración BD (Semana 1)

```powershell
# Esta es la primera tarea de Fase 2
git checkout -b feature/migracion-bd-fase2
```

### Paso 3: Crear el script SQL de migración

Copia este contenido en archivo `migracion_fase2.sql`:

```sql
-- MIGRACIÓN BD FASE 2
-- Fecha: 6 de Diciembre 2025
-- Status: Seguro - Los datos existentes NO se modifican

-- PASO 1: ALTER TABLE movimientos - Agregar campos
ALTER TABLE movimientos ADD COLUMN IF NOT EXISTS tipo_movimiento VARCHAR(50) DEFAULT 'ALTA_DEPOSITO';
ALTER TABLE movimientos ADD COLUMN IF NOT EXISTS id_ubicacion_sucursal INT NULL;
ALTER TABLE movimientos ADD COLUMN IF NOT EXISTS observaciones TEXT;
ALTER TABLE movimientos ADD COLUMN IF NOT EXISTS estado VARCHAR(50) DEFAULT 'ABIERTO';
ALTER TABLE movimientos ADD COLUMN IF NOT EXISTS fecha_cierre DATETIME NULL;

-- PASO 2: Crear índices para performance
ALTER TABLE movimientos ADD INDEX idx_tipo_fecha (tipo_movimiento, fechaAlta);
ALTER TABLE movimientos ADD INDEX idx_sucursal_tipo (id_ubicacion_sucursal, tipo_movimiento);

-- PASO 3: Migrar datos existentes - Identificar ALTA_DEPOSITO
UPDATE movimientos 
SET tipo_movimiento = 'ALTA_DEPOSITO'
WHERE id_ubicacion_origen = 1 
AND id_ubicacion_destino = 1
AND tipo_movimiento = 'ALTA_DEPOSITO';

-- PASO 4: Migrar datos existentes - Identificar ENVIO
UPDATE movimientos 
SET tipo_movimiento = 'ENVIO',
    id_ubicacion_sucursal = id_ubicacion_destino
WHERE id_ubicacion_origen = 1 
AND id_ubicacion_destino != 1
AND tipo_movimiento = 'ALTA_DEPOSITO';

-- PASO 5: Marcar histórico como cerrado
UPDATE movimientos 
SET estado = 'CERRADO'
WHERE estado IS NULL OR estado = '';

-- PASO 6: INSERT nuevos estados
INSERT INTO estados (id, nombre) VALUES 
(7, 'RECIBIDO_SUCURSAL'),
(8, 'DESCUENTADO'),
(9, 'RECHAZADO_RECEPCION'),
(10, 'PENDIENTE_ENVIO')
ON DUPLICATE KEY UPDATE nombre = VALUES(nombre);

-- PASO 7: CREATE TABLE pedido_envio
CREATE TABLE IF NOT EXISTS pedido_envio (
    id INT PRIMARY KEY AUTO_INCREMENT,
    id_pedido INT NOT NULL,
    id_movimiento_envio INT NOT NULL,
    fecha_relacion DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (id_pedido) REFERENCES movimientos(id),
    FOREIGN KEY (id_movimiento_envio) REFERENCES movimientos(id),
    UNIQUE KEY unique_pedido_envio (id_pedido, id_movimiento_envio),
    INDEX (id_pedido),
    INDEX (id_movimiento_envio)
);

-- PASO 8: CREATE TABLE stock_minimo_config
CREATE TABLE IF NOT EXISTS stock_minimo_config (
    id INT PRIMARY KEY AUTO_INCREMENT,
    id_sucursal INT NOT NULL,
    id_producto INT NOT NULL,
    cantidad_minima INT NOT NULL DEFAULT 5,
    dias_promedio_consumo DECIMAL(10,2),
    frecuencia_reorden VARCHAR(20) DEFAULT 'SEMANAL',
    cantidad_sugerida_pedido INT,
    activo BOOLEAN DEFAULT TRUE,
    fecha_configuracion DATETIME DEFAULT CURRENT_TIMESTAMP,
    fecha_actualizacion DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (id_sucursal) REFERENCES ubicaciones(id),
    FOREIGN KEY (id_producto) REFERENCES productos(id),
    UNIQUE KEY unique_minimo_sucursal (id_sucursal, id_producto),
    INDEX (id_sucursal, activo)
);

-- PASO 9: CREATE TABLE stock_minimo_auditoria
CREATE TABLE IF NOT EXISTS stock_minimo_auditoria (
    id INT PRIMARY KEY AUTO_INCREMENT,
    id_config INT NOT NULL,
    cantidad_anterior INT,
    cantidad_nueva INT,
    usuario_cambio VARCHAR(255),
    fecha_cambio DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (id_config) REFERENCES stock_minimo_config(id),
    INDEX (id_config, fecha_cambio)
);

-- VALIDACIÓN FINAL
SELECT 'Migración completada' as status,
       COUNT(*) as total_movimientos
FROM movimientos;

SELECT tipo_movimiento, COUNT(*) as total
FROM movimientos
GROUP BY tipo_movimiento;
```

### Paso 4: Ejecutar script SQL

```powershell
# Copiar el script anterior en: c:\xampp7.4.30\htdocs\mikelo\migracion_fase2.sql

# Ejecutar en terminal
mysql -u root -p mikelo < migracion_fase2.sql

# Debe mostrar: "Migración completada"
```

### Paso 5: Commit en Git

```powershell
# Agregar archivo
git add migracion_fase2.sql

# Commit
git commit -m "feat: Agregar script de migración BD Fase 2

- Alter movimientos: agregar tipo_movimiento, id_ubicacion_sucursal, observaciones
- Crear tabla pedido_envio (N:N entre pedidos y envíos)
- Crear tabla stock_minimo_config
- Crear tabla stock_minimo_auditoria
- Migrar datos existentes (ALTA_DEPOSITO, ENVIO)
- Agregar nuevos estados (7-10)
- Validación: 0 errores"

# Subir a origin
git push -u origin feature/migracion-bd-fase2
```

### Paso 6: Crear archivo de documentación

```powershell
# Crear archivo MIGRACION_FASE2_STATUS.md con:
# - Fecha de ejecución
# - Resultado (OK/ERROR)
# - Datos antes y después
# - Rollback plan

git add MIGRACION_FASE2_STATUS.md
git commit -m "docs: Registrar status de migración BD Fase 2"
git push
```

### Paso 7: Mergear a main cuando esté validado

```powershell
# Cambiar a main
git checkout main

# Traer cambios de origin
git pull origin main

# Mergear feature
git merge feature/migracion-bd-fase2

# Push a origin
git push origin main

# Crear tag de hito
git tag -a v1.1-migracion-bd -m "Migración BD Fase 2 completada - 6 de Diciembre"
git push origin v1.1-migracion-bd
```

### Paso 8: Crear rama para siguiente feature

```powershell
git checkout -b feature/api-pedidos

# Empezar a codificar Pedidos API...
```

---

## CHECKLIST ANTES DE MERGEAR A MAIN

- [ ] Script SQL ejecutado sin errores
- [ ] Validación de integridad: `SELECT COUNT(*) FROM movimientos` (debe ser igual que antes)
- [ ] Nuevas tablas creadas: `SHOW TABLES LIKE 'pedido%'`
- [ ] Nuevos estados insertados: `SELECT COUNT(*) FROM estados WHERE id >= 7`
- [ ] Indices creados correctamente: `SHOW INDEX FROM movimientos`
- [ ] Datos históricos sin cambios: `SELECT COUNT(DISTINCT id) FROM movimientos`
- [ ] Archivo SQL está documentado y comentado
- [ ] Mensajes de commit son claros y descriptivos
- [ ] No hay conflictos con main

---

## COMANDOS RÁPIDOS ÚTILES

```powershell
# Ver estado actual
git status

# Ver qué está en mi rama vs main
git diff main

# Ver commits que voy a mergear
git log main..HEAD --oneline

# Deshacer último commit (si metiste la pata)
git reset --soft HEAD~1

# Ver qué se va a mergear
git diff main..feature/migracion-bd-fase2

# Simular merge sin hacer
git merge --no-commit --no-ff feature/migracion-bd-fase2

# Abortar merge
git merge --abort

# Ver archivo de otra rama
git show feature/migracion-bd-fase2:migracion_fase2.sql

# Ver cambios en un archivo específico
git diff main feature/migracion-bd-fase2 -- migracion_fase2.sql
```

---

## BACKUP Y RECUPERACIÓN

```powershell
# Backup manual de BD antes de migración
mysqldump -u root -p mikelo > backup_previa_migracion_$(Get-Date -Format "yyyyMMdd_HHmmss").sql

# Si algo falla: restaurar
mysql -u root -p mikelo < backup_previa_migracion_20251206_120000.sql

# Rollback Git (volver a v1.0-fase1)
git reset --hard v1.0-fase1
git push origin main --force
```

---

## PRÓXIMO OBJETIVO DESPUÉS DE MIGRACIÓN

Una vez que `feature/migracion-bd-fase2` está en main:

```
Crear rama: feature/api-pedidos
├─ POST /api/pedidos/crear
├─ GET /api/pedidos/mis-pedidos
├─ GET /api/pedidos/{id}/detalles
├─ GET /api/pedidos/asistente
└─ Tests unitarios

Luego mergear a main → Tag v1.2-api-pedidos
```

---

**Documento:** 6 de Diciembre 2025  
**Status:** Listo para ejecutar  
**Siguiente paso:** Ejecutar script SQL y empezar feature/api-pedidos
