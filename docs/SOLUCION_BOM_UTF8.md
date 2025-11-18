# 🔧 Solución al Problema del BOM UTF-8

**Problema**: Cada vez que se edita `api/src/Model/Envio.php`, VS Code agrega automáticamente un **BOM (Byte Order Mark)** UTF-8 al inicio del archivo, causando el error:

```
Fatal error: Namespace declaration statement has to be the very first statement
or after any declare call in the script
```

---

## ❓ ¿Por Qué Sucede?

El **BOM UTF-8** son 3 bytes invisibles (`EF BB BF`) que algunos editores agregan al inicio de archivos UTF-8. PHP no los tolera antes de la declaración `<?php` o `namespace`.

**VS Code** automáticamente agrega BOM cuando:
- Se usa la herramienta `replace_string_in_file`
- Se guarda manualmente el archivo
- La configuración del editor lo tiene habilitado

---

## ✅ Soluciones Disponibles

### **Solución 1: Script Batch Rápido** (RECOMENDADO)

Ejecuta este archivo cada vez que ocurra el error:

**Archivo**: `fix_bom.bat` (en la raíz del proyecto)

```batch
@echo off
REM Eliminar BOM UTF-8 de Envio.php
powershell -Command "[System.IO.File]::WriteAllText('c:\xampp7.4.30\htdocs\mikelo\api\src\Model\Envio.php', [System.IO.File]::ReadAllText('c:\xampp7.4.30\htdocs\mikelo\api\src\Model\Envio.php', [System.Text.Encoding]::UTF8), (New-Object System.Text.UTF8Encoding $false))"
php -l c:\xampp7.4.30\htdocs\mikelo\api\src\Model\Envio.php
pause
```

**Uso**:
1. Doble clic en `fix_bom.bat`
2. Esperar mensaje "No syntax errors detected"
3. Listo!

---

### **Solución 2: Comando PowerShell Manual**

Si prefieres ejecutar manualmente:

```powershell
[System.IO.File]::WriteAllText(
    'c:\xampp7.4.30\htdocs\mikelo\api\src\Model\Envio.php', 
    [System.IO.File]::ReadAllText('c:\xampp7.4.30\htdocs\mikelo\api\src\Model\Envio.php', [System.Text.Encoding]::UTF8), 
    (New-Object System.Text.UTF8Encoding $false)
)
```

---

### **Solución 3: Configurar VS Code (Preventivo)**

Para evitar que VS Code agregue BOM en el futuro:

**Archivo**: `.vscode/settings.json` (en la raíz del proyecto)

```json
{
    "files.encoding": "utf8",
    "files.autoGuessEncoding": false,
    "[php]": {
        "files.encoding": "utf8"
    }
}
```

**Nota**: Esta configuración puede no ser 100% efectiva porque las herramientas de edición automática de Copilot pueden seguir agregando BOM.

---

## 🔍 Cómo Detectar el Problema

### **Síntoma 1: Error al cargar página**
```
Fatal error: Namespace declaration statement has to be the very first statement
or after any declare call in the script in .../Envio.php on line 2
```

### **Síntoma 2: Verificación con php -l**
```bash
php -l api/src/Model/Envio.php
# Si hay BOM: Fatal error...
# Si NO hay BOM: No syntax errors detected
```

### **Síntoma 3: Inspección hexadecimal**
```powershell
# Ver primeros 10 bytes del archivo
Get-Content api/src/Model/Envio.php -Encoding Byte -TotalCount 10

# Si empieza con: 239 187 191 60 63 112 104 112...
#                  ^^^ ^^^ ^^^  <- Esto es BOM
# Entonces HAY BOM
```

---

## 📋 Procedimiento de Emergencia

### **Cuando falla la carga de envíos**:

1. **Ejecutar script rápido**:
   ```
   Doble clic en: fix_bom.bat
   ```

2. **Verificar en navegador**:
   - Recargar página de envíos
   - Debería cargar correctamente

3. **Si persiste**:
   - Verificar error en consola del navegador (F12)
   - Revisar logs de PHP en `c:\xampp7.4.30\apache\logs\error.log`

---

## 🎯 Prevención

### **Regla para el Asistente de IA**:

> **IMPORTANTE**: Después de cada edición de `api/src/Model/Envio.php` con `replace_string_in_file`, SIEMPRE ejecutar automáticamente:
> 
> ```powershell
> [System.IO.File]::WriteAllText('c:\xampp7.4.30\htdocs\mikelo\api\src\Model\Envio.php', [System.IO.File]::ReadAllText('c:\xampp7.4.30\htdocs\mikelo\api\src\Model\Envio.php', [System.Text.Encoding]::UTF8), (New-Object System.Text.UTF8Encoding $false))
> ```
> 
> Y verificar con `php -l` antes de dar por terminada la tarea.

---

## 📊 Comparación de Encodings

| Encoding | BOM | Compatible PHP | Recomendado |
|----------|-----|----------------|-------------|
| **UTF-8 sin BOM** | ❌ No | ✅ Sí | ✅ **Usar este** |
| **UTF-8 con BOM** | ✅ Sí (EF BB BF) | ❌ No | ❌ Evitar |
| **ANSI/ASCII** | ❌ No | ✅ Sí | ⚠️ Limita caracteres |

**Conclusión**: PHP requiere **UTF-8 sin BOM** para archivos con `namespace`.

---

## 🛠️ Herramientas Útiles

### **Verificar BOM en múltiples archivos**:
```powershell
Get-ChildItem -Path "api/src" -Filter "*.php" -Recurse | ForEach-Object {
    $bytes = Get-Content $_.FullName -Encoding Byte -TotalCount 3
    if ($bytes[0] -eq 239 -and $bytes[1] -eq 187 -and $bytes[2] -eq 191) {
        Write-Host "BOM encontrado: $($_.FullName)" -ForegroundColor Red
    }
}
```

### **Eliminar BOM de todos los archivos PHP**:
```powershell
Get-ChildItem -Path "api/src" -Filter "*.php" -Recurse | ForEach-Object {
    $content = [System.IO.File]::ReadAllText($_.FullName, [System.Text.Encoding]::UTF8)
    [System.IO.File]::WriteAllText($_.FullName, $content, (New-Object System.Text.UTF8Encoding $false))
    Write-Host "Procesado: $($_.Name)"
}
```

---

## 📝 Historial del Problema

| Fecha | Evento | Solución |
|-------|--------|----------|
| 20/10/2025 | Primera aparición BOM | PowerShell manual |
| 20/10/2025 | BOM después de fix_encoding.php | PowerShell manual |
| 20/10/2025 | BOM después de fix_utf8_to_ascii.php | PowerShell manual |
| 20/10/2025 | BOM después de fix_ascii_final.php | PowerShell manual |
| 20/10/2025 | BOM después de agregar estado reportes | PowerShell manual |
| 20/10/2025 | Creación de fix_bom.bat | ✅ Script automatizado |

**Patrón detectado**: BOM aparece **cada vez** que se usa `replace_string_in_file`.

---

## ✅ Checklist de Verificación

Después de editar `Envio.php`, verificar:

- [ ] Ejecutar `fix_bom.bat` o comando PowerShell
- [ ] Verificar: `php -l api/src/Model/Envio.php` → "No syntax errors"
- [ ] Probar en navegador: cargar página de envíos
- [ ] Confirmar que no hay error de namespace

---

## 🎓 Para el Usuario

**Cada vez que veas el error "Namespace declaration..."**:

1. **No te preocupes**, es el BOM de nuevo
2. Ejecuta: `fix_bom.bat` (doble clic)
3. Recarga la página
4. Listo!

**Ubicación del script**: `c:\xampp7.4.30\htdocs\mikelo\fix_bom.bat`

---

## 📞 Contacto de Emergencia

Si el script no funciona:

```powershell
# Opción A: PowerShell directo
[System.IO.File]::WriteAllText('c:\xampp7.4.30\htdocs\mikelo\api\src\Model\Envio.php', [System.IO.File]::ReadAllText('c:\xampp7.4.30\htdocs\mikelo\api\src\Model\Envio.php', [System.Text.Encoding]::UTF8), (New-Object System.Text.UTF8Encoding $false))

# Opción B: Restaurar desde backup (si existe)
Copy-Item "api/src/Model/Envio.php.bak" -Destination "api/src/Model/Envio.php"
```

---

✅ **Problema documentado y solución automatizada lista para usar**
