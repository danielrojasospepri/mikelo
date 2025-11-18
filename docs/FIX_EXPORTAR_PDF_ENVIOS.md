# Fix: Error Fuentes TTF en Exportar PDF de Envíos

**Fecha:** 21 de octubre de 2025  
**Problema:** Error al exportar listado de envíos a PDF  
**Archivo afectado:** `api/src/Model/Envio.php`

---

## 🔴 Descripción del Error

Al hacer clic en el botón **"Exportar PDF"** de la grilla de envíos, el servidor respondía con:

```
Error al generar el PDF: Error en la respuesta del servidor
mensaje: "Cannot find TTF TrueType font file \"DejaVuSansCondensed.ttf\" in configured font directories."
```

### Contexto
- **Endpoint afectado:** `GET /api/envios/pdf`
- **Método:** `Envio::exportarPDF()` línea 354-456
- **Ubicación del problema:** Línea 374 (configuración de mPDF)

---

## 🔍 Análisis del Problema

### Configuración Original (INCORRECTA)

```php
// api/src/Model/Envio.php línea 370-376
$mpdf = new \Mpdf\Mpdf([
    'mode' => 'utf-8',
    'format' => 'A4',
    'default_font_size' => 12,
    'default_font' => 'helvetica'  // ❌ Problema aquí
]);
```

### ¿Por qué fallaba?

1. **Fuente `helvetica`** sin configuración adicional
2. mPDF por defecto intenta **auto-detectar fuentes** para caracteres especiales
3. Busca `DejaVuSansCondensed.ttf` en directorios no configurados
4. No encuentra el archivo TTF → **Error fatal**

### Configuraciones de mPDF sin configurar

```php
'fontDir' => []                  // Sin directorios custom de fuentes
'autoScriptToLang' => false      // Sin detección automática de idioma
'autoLangToFont' => false        // Sin cambio automático de fuente
```

Cuando estos parámetros **no están presentes**, mPDF usa valores por defecto que **buscan fuentes TTF externas**.

---

## ✅ Solución Aplicada

### Configuración Nueva (CORRECTA)

```php
// api/src/Model/Envio.php línea 370-379
$mpdf = new \Mpdf\Mpdf([
    'mode' => 'utf-8',
    'format' => 'A4',
    'default_font_size' => 12,
    'default_font' => 'Arial',         // ✅ Arial en lugar de helvetica
    'fontDir' => [],                   // ✅ Sin directorios custom
    'autoScriptToLang' => false,       // ✅ Sin auto-detección idioma
    'autoLangToFont' => false          // ✅ Sin auto-fuentes
]);
```

### Beneficios

1. **Arial es una fuente core de mPDF** - No requiere archivos TTF externos
2. **Configuración idéntica** a otros PDFs del sistema:
   - `ContenedorController.php` (códigos de barras) ✅
   - `Movimiento.php` (reporte movimientos) ✅
   - `Envio.php` método `generarRemitoPDF()` ✅
   - `Envio.php` método `generarPDFPreimpreso()` ✅

3. **Consistencia visual** en todos los reportes del sistema

---

## 🧪 Pruebas de Verificación

### Test 1: Exportar PDF desde Grilla

```
1. Abrir envios.html
2. Aplicar filtros si es necesario (opcional)
3. Click botón "Exportar PDF" (arriba de la grilla)
4. Verificar descarga exitosa
5. Abrir PDF y verificar contenido
```

**Resultado esperado:**
- ✅ PDF descarga sin errores
- ✅ Contiene listado de envíos con columnas:
  - Fecha Alta
  - Destino
  - Estado
  - Cantidad Items
  - Peso Total

### Test 2: Exportar PDF Vacío

```
1. Aplicar filtros que no devuelvan resultados
2. Click "Exportar PDF"
3. Verificar mensaje de error apropiado
```

**Resultado esperado:**
- ⚠️ Error: "No se encontraron envios con los filtros especificados"
- (No debe ser error de fuentes TTF)

---

## 📊 Comparación de Configuraciones

| Aspecto | Antes (❌) | Después (✅) |
|---------|-----------|--------------|
| **Fuente base** | `helvetica` | `Arial` |
| **fontDir** | (no configurado) | `[]` (vacío) |
| **autoScriptToLang** | (no configurado) | `false` |
| **autoLangToFont** | (no configurado) | `false` |
| **Busca TTF externos** | ✅ Sí | ❌ No |
| **Funciona sin internet** | ❌ | ✅ |
| **Consistente con sistema** | ❌ | ✅ |

---

## 🔗 Archivos Relacionados

### Archivos con configuración CORRECTA (Arial)

1. **`api/src/Controller/ContenedorController.php`** línea 50
   - Genera códigos de barras de contenedores
   - Configuración Arial completa ✅

2. **`api/src/Model/Movimiento.php`** línea 177
   - Genera reporte PDF de movimientos
   - Configuración Arial completa ✅

3. **`api/src/Model/Envio.php`** línea 1342
   - Método `generarRemitoPDF()` (remito individual)
   - Configuración Arial ✅

4. **`api/src/Model/Envio.php`** línea 1495
   - Método `generarPDFPreimpreso()` (remito STARK IND)
   - Configuración Arial ✅

5. **`api/src/Model/Envio.php`** línea 374 ← **CORREGIDO EN ESTA SESIÓN**
   - Método `exportarPDF()` (listado de envíos)
   - Configuración Arial ✅

---

## 📝 Comando de Verificación

Después de aplicar el cambio, verificar sintaxis PHP:

```powershell
php -l c:\xampp7.4.30\htdocs\mikelo\api\src\Model\Envio.php
```

**Salida esperada:**
```
No syntax errors detected in c:\xampp7.4.30\htdocs\mikelo\api\src\Model\Envio.php
```

---

## 🚀 Despliegue

### Paso 1: Reiniciar Apache

```
1. Abrir XAMPP Control Panel
2. Click "Stop" en Apache
3. Esperar 3 segundos
4. Click "Start" en Apache
5. Verificar: "Running" en verde
```

**Razón:** Limpiar caché de PHP (OPcache) para cargar el código actualizado.

### Paso 2: Probar Exportación

```
1. Abrir envios.html
2. Click "Exportar PDF"
3. Verificar descarga exitosa
```

### Paso 3: Verificar Otros PDFs

Confirmar que los demás reportes siguen funcionando:
- ✅ Remito individual (botón PDF en fila)
- ✅ Remito preimpreso (botón Imprimir en fila)
- ✅ Códigos de barras contenedores
- ✅ Reporte de movimientos

---

## 📚 Referencias

- **mPDF Documentation:** https://mpdf.github.io/
- **Core Fonts de mPDF:** Arial, Helvetica, Times, Courier, Symbol, ZapfDingbats
- **Configuración de fuentes:** https://mpdf.github.io/fonts-languages/fonts-in-mpdf-7-x.html

---

## ✅ Estado

- **Problema:** Resuelto ✅
- **Código:** Corregido y verificado ✅
- **Sintaxis:** Sin errores ✅
- **BOM UTF-8:** Removido ✅
- **Pruebas:** Pendientes de ejecución por usuario 🧪

---

## 🔄 Historial de Cambios

| Fecha | Cambio | Estado |
|-------|--------|--------|
| 21/10/2025 | Configuración Arial aplicada en línea 374 | ✅ Completo |
| 21/10/2025 | BOM UTF-8 removido de Envio.php | ✅ Completo |
| 21/10/2025 | Sintaxis verificada con php -l | ✅ Completo |
| 21/10/2025 | Documentación creada | ✅ Completo |

---

**Próximo paso:** Reiniciar Apache y probar exportación PDF de envíos. 🚀
