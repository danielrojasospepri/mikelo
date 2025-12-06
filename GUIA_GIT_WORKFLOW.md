# GUÍA DE WORKFLOW GIT - MIKELO
## Control de Versiones y Ramas de Desarrollo

**Fecha:** 6 de Diciembre de 2025  
**Status:** Operativo

---

## ESTRUCTURA ACTUAL DE RAMAS

### Rama `main` (Producción - Fase 2 en desarrollo)
```
main ← Rama principal
      ← Donde vamos a desarrollar Fase 2
      ← Código de producción
```

### Rama `release/v1-fase1` (Respaldo - Fase 1 estable)
```
release/v1-fase1 ← Copia de seguridad de Fase 1
                 ← NO modificar (congelada)
                 ← Punto de referencia si hay que rollback
```

### Tag `v1.0-fase1` (Snapshot - Versión 1.0)
```
v1.0-fase1 ← Marca el exacto commit de Fase 1 completada
           ← Se puede recuperar en cualquier momento
           ← Historial inmutable
```

---

## FLUJO DE TRABAJO - OPERACIONES DIARIAS

### 1️⃣ Asegurarse de estar en `main`

```powershell
cd c:\xampp7.4.30\htdocs\mikelo
git status
# Debería mostrar: "On branch main"
```

### 2️⃣ Crear rama de feature para trabajo específico

```powershell
# Crear rama para módulo de pedidos
git checkout -b feature/pedidos-modulo

# Crear rama para API de bajas
git checkout -b feature/api-bajas-stock

# Crear rama para vistas
git checkout -b feature/vistas-reportes
```

**Nomenclatura de ramas:**
- `feature/nombre-corto` - Nueva funcionalidad
- `bugfix/descripcion-bug` - Corrección de bug
- `hotfix/urgencia` - Cambio urgente de producción

### 3️⃣ Hacer cambios y commits

```powershell
# Editar archivos...

# Ver qué cambió
git status

# Agregar cambios específicos
git add api/src/Controller/PedidosController.php

# O agregar todos los cambios
git add .

# Commit con mensaje descriptivo
git commit -m "Implementar API de pedidos - crear y listar"

# Múltiples commits si hace falta
git commit -m "Agregar validación de stock en pedidos"
git commit -m "Crear vista asistente de stock mínimo"
```

### 4️⃣ Subir rama a GitHub

```powershell
# Primer push de la rama
git push -u origin feature/pedidos-modulo

# Siguientes pushes
git push
```

### 5️⃣ Merge a main (cuando está listo)

```powershell
# Asegurarse que main está actualizado
git checkout main
git pull origin main

# Mergear feature a main
git merge feature/pedidos-modulo

# Push del merge
git push origin main
```

### 6️⃣ Eliminar rama cuando está completa

```powershell
# Local
git branch -d feature/pedidos-modulo

# Remota
git push origin --delete feature/pedidos-modulo
```

---

## ROLLBACK A FASE 1 (Si es necesario)

### Opción A: Revertir todo a v1.0-fase1

```powershell
# Ver estado actual
git log --oneline -3

# Revertir a v1.0-fase1
git reset --hard v1.0-fase1

# Forzar push a main (⚠️ CUIDADO - destruye main)
git push origin main --force
```

⚠️ **SOLO hacer esto si:**
- Hay error crítico en Fase 2
- Se necesita volver a estado conocido
- Coordinado con todo el equipo

### Opción B: Recuperar un archivo específico de v1.0-fase1

```powershell
# Ver archivo específico de Fase 1
git show v1.0-fase1:api/src/Model/Envio.php > Envio_v1.php

# Restaurar archivo completo
git checkout v1.0-fase1 -- api/src/Model/Envio.php
git commit -m "Restaurar Envio.php a versión v1.0"
```

### Opción C: Ver diferencias

```powershell
# Ver qué cambió desde v1.0 a main
git diff v1.0-fase1..main

# Ver un archivo específico
git diff v1.0-fase1..main api/src/Model/Envio.php

# Ver commits desde v1.0
git log v1.0-fase1..main --oneline
```

---

## VERIFICACIÓN Y REFERENCIAS

### Ver ramas locales

```powershell
git branch
# Resultado:
# * main
#   release/v1-fase1
#   feature/pedidos-modulo (si existe)
```

### Ver ramas remotas

```powershell
git branch -a
# Resultado:
# * main
#   release/v1-fase1
#   remotes/origin/main
#   remotes/origin/release/v1-fase1
```

### Ver tags

```powershell
git tag -l
# Resultado:
# v1.0-fase1

# Ver detalles del tag
git show v1.0-fase1
```

### Ver historial

```powershell
# Últimos 10 commits
git log --oneline -10

# Commits en rama actual vs main
git log --oneline main..feature/pedidos-modulo

# Gráfico de ramas
git log --all --graph --oneline --decorate
```

---

## FLUJO RECOMENDADO PARA FASE 2

### Semana 1: Infraestructura + API Pedidos

```powershell
# Crear rama
git checkout -b feature/migracion-bd-fase2

# Trabajar en migración
# ... editar archivos ...
# ... commits ...

# Subir
git push -u origin feature/migracion-bd-fase2

# Cuando esté listo
git checkout main
git pull origin main
git merge feature/migracion-bd-fase2
git push origin main

# Crear tag de hito
git tag -a v1.1-migracion -m "Migración BD Fase 2 completada"
git push origin v1.1-migracion
```

### Semana 2: Bajas de Stock + Dashboard

```powershell
git checkout -b feature/bajas-stock

# Trabajar...

git checkout main
git pull origin main
git merge feature/bajas-stock
git push origin main

git tag -a v1.2-bajas-stock -m "Módulo bajas de stock completado"
git push origin v1.2-bajas-stock
```

### Semana 3: Stock Mínimo + Reportes

```powershell
git checkout -b feature/stock-minimo-reportes

# Trabajar...

git checkout main
git pull origin main
git merge feature/stock-minimo-reportes
git push origin main

git tag -a v2.0-fase2-completa -m "Fase 2 completada"
git push origin v2.0-fase2-completa
```

---

## CHECKLIST DE SEGURIDAD

### Antes de cada push

```powershell
# ✅ Verificar qué va a subir
git diff main origin/main

# ✅ Ver commits que van
git log origin/main..main --oneline

# ✅ Asegurarse que no hay código roto
php -l api/src/Model/*.php

# ✅ Luego hacer push
git push
```

### Antes de mergear a main

```powershell
# ✅ Actualizar main
git checkout main
git pull origin main

# ✅ Ver diferencias de la feature
git diff main feature/pedidos-modulo

# ✅ Mergear
git merge feature/pedidos-modulo

# ✅ Revisar que compila
php -l api/src/**/*.php

# ✅ Hacer push
git push origin main
```

---

## RECUPERACIÓN DE EMERGENCIA

### Si hiciste commit incorrecto

```powershell
# Ver últimos commits
git log --oneline -5

# Deshacer último commit (mantener cambios)
git reset --soft HEAD~1

# O deshacer y perder cambios
git reset --hard HEAD~1
```

### Si hiciste push incorrecto a origin

```powershell
# Revertir el commit (crea nuevo commit que revierte)
git revert COMMIT_HASH
git push origin main

# O forzar reset (⚠️ SOLO si no hay otros trabajando)
git reset --hard COMMIT_HASH
git push origin main --force
```

### Si necesitas cambios de otra rama

```powershell
# Cherry-pick: copiar commit específico
git cherry-pick COMMIT_HASH

# O mergear solo cambios específicos
git merge --no-commit --no-ff feature/otra-rama
# ... revisar cambios ...
git commit
```

---

## REFERENCIAS RÁPIDAS

| Comando | Función |
|---------|---------|
| `git checkout -b rama-nueva` | Crear y cambiar a nueva rama |
| `git checkout rama` | Cambiar a rama existente |
| `git branch -d rama` | Eliminar rama local |
| `git push origin --delete rama` | Eliminar rama remota |
| `git pull` | Traer cambios de origin |
| `git push` | Subir cambios a origin |
| `git merge rama` | Mergear rama actual en HEAD |
| `git tag nombre` | Crear tag |
| `git push origin tag` | Subir tag |
| `git show COMMIT` | Ver detalles del commit |
| `git diff rama1 rama2` | Diferencias entre ramas |
| `git log --oneline -N` | Ver últimos N commits |
| `git reset --hard COMMIT` | Volver a commit específico |
| `git revert COMMIT` | Deshacer commit (crea nuevo) |

---

## ESTADO ACTUAL DEL REPOSITORIO

```
GitHub: https://github.com/danielrojasospepri/mikelo

Ramas:
- main (Fase 2 en desarrollo)
- release/v1-fase1 (Respaldo Fase 1)

Tags:
- v1.0-fase1 (Snapshot de Fase 1)

Último commit: "estrategia fase 2"
HEAD: main (actualizado con origin)
```

---

## PRÓXIMOS PASOS

1. ✅ **Rama de seguridad creada** (release/v1-fase1)
2. ✅ **Tag de versión creado** (v1.0-fase1)
3. ⏳ **Empezar desarrollo de Fase 2** en rama feature/...
4. ⏳ **Mergear features a main** cuando estén listas
5. ⏳ **Crear tags de hitos** (v1.1, v1.2, v2.0, etc)

---

**Documento Creado:** 6 de Diciembre de 2025  
**Estado:** Operativo - Listo para desarrollo Fase 2
