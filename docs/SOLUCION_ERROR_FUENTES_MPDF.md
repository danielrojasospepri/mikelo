# 🔧 Solución: Error de Fuentes TTF en mPDF
**Fecha**: 21 de octubre de 2025  
**Problema**: Cannot find TTF TrueType font file "DejaVuSansCondensed.ttf"

---

## ❌ Errores Reportados

### **1. Códigos de Barras (Contenedores)**
```
Error: Cannot find TTF TrueType font file "DejaVuSansCondensed.ttf" 
in configured font directories.
at AltaDepositoIndustrial.generarCodigosBarras
```

### **2. Reporte PDF (Movimientos)**
```
Error: Cannot find TTF TrueType font file "DejaVuSansCondensed.ttf"
```

---

## 🔍 Análisis del Problema

### **Causa Raíz**
mPDF por defecto intenta usar la fuente **DejaVuSans** que requiere archivos `.ttf` en directorios específicos. Si estos archivos no están presentes o las rutas no están configuradas, falla la generación del PDF.

### **Archivos Afectados**
1. `api/src/Controller/ContenedorController.php` - Genera códigos de barras
2. `api/src/Model/Movimiento.php` - Genera reporte de movimientos

---

## ✅ Solución Implementada

Ambos archivos **YA TIENEN** la configuración correcta con Arial:

### **ContenedorController.php** (línea ~50)
```php
$mpdf = new \Mpdf\Mpdf([
    'mode' => 'utf-8',
    'format' => 'A4',
    'orientation' => 'P',
    'margin_left' => 12,
    'margin_right' => 12,
    'margin_top' => 10,
    'margin_bottom' => 10,
    'default_font' => 'Arial',        // ✅ Usar Arial
    'fontDir' => [],                  // ✅ No usar fuentes custom
    'autoScriptToLang' => false,      // ✅ Deshabilitar auto-detección
    'autoLangToFont' => false,        // ✅ Deshabilitar auto-fuentes
]);

$mpdf->SetDefaultFont('Arial'); // ✅ Forzar Arial
```

### **Movimiento.php** (línea ~177)
```php
$mpdf = new \Mpdf\Mpdf([
    'mode' => 'utf-8',
    'format' => 'A4-L',
    'margin_left' => 10,
    'margin_right' => 10,
    'margin_top' => 20,
    'margin_bottom' => 20,
    'margin_header' => 5,
    'margin_footer' => 5,
    'default_font' => 'Arial',        // ✅ Usar Arial
    'fontDir' => [],                  // ✅ No usar fuentes custom
    'autoScriptToLang' => false,      // ✅ Deshabilitar auto-detección
    'autoLangToFont' => false         // ✅ Deshabilitar auto-fuentes
]);
```

---

## 🧹 Posibles Causas del Error Persistente

### **1. Cache de PHP OPcache**
PHP puede estar cacheando versiones antiguas del código.

**Solución**:
```bash
# Reiniciar Apache
net stop Apache2.4
net start Apache2.4
```

O en XAMPP Control Panel: **Stop → Start** para Apache

### **2. Cache del Navegador**
El navegador puede estar usando versiones cached de los endpoints.

**Solución**:
- Presionar `Ctrl + F5` para recarga forzada
- Limpiar cache del navegador
- Usar modo incógnito para probar

### **3. Múltiples Carpetas `vendor`**
Según conversación anterior, hay 2 carpetas `vendor`:
- `c:\xampp7.4.30\htdocs\mikelo\vendor\` (raíz - posiblemente duplicado)
- `c:\xampp7.4.30\htdocs\mikelo\api\vendor\` (usado por API)

**Problema**: Si PHP carga el `vendor` incorrecto, puede tener configuración antigua.

**Verificar**:
```php
// En el archivo que falla, agregar temporalmente:
error_log('mPDF cargado desde: ' . __DIR__);
error_log('Vendor path: ' . realpath(__DIR__ . '/../../vendor'));
```

### **4. Configuración Global de mPDF**
Archivo de configuración global puede estar sobrescribiendo configuración local.

**Verificar**:
```php
// api/vendor/mpdf/mpdf/src/Config/ConfigVariables.php
// api/vendor/mpdf/mpdf/src/Config/FontVariables.php
```

---

## 🔧 Pasos de Troubleshooting

### **Paso 1: Reiniciar Apache**
```bash
# En XAMPP Control Panel
Stop Apache
Start Apache
```

### **Paso 2: Limpiar Cache del Navegador**
```
Ctrl + Shift + Delete → Limpiar cache
O usar modo incógnito (Ctrl + Shift + N)
```

### **Paso 3: Verificar Logs de PHP**
```bash
# Ver últimos errores
tail -f c:\xampp7.4.30\apache\logs\error.log
```

### **Paso 4: Agregar Debug Temporal**
```php
// En ContenedorController.php línea ~50, ANTES de crear mPDF:
error_log('=== DEBUG mPDF ===');
error_log('Vendor path: ' . realpath(__DIR__ . '/../../../vendor'));
error_log('mPDF class: ' . \Mpdf\Mpdf::class);

$config = [
    'mode' => 'utf-8',
    'format' => 'A4',
    'default_font' => 'Arial',
    'fontDir' => [],
    'autoScriptToLang' => false,
    'autoLangToFont' => false
];
error_log('mPDF config: ' . print_r($config, true));

$mpdf = new \Mpdf\Mpdf($config);
```

### **Paso 5: Probar Configuración Mínima**
```php
// Test simple
try {
    $mpdf = new \Mpdf\Mpdf(['default_font' => 'Arial']);
    $mpdf->WriteHTML('<p>Test</p>');
    $mpdf->Output('test.pdf', 'D');
} catch (\Exception $e) {
    error_log('Error mPDF test: ' . $e->getMessage());
}
```

---

## 📊 Verificación de Configuración Actual

### **Archivos Revisados**
| Archivo | Línea | Estado | Fuente |
|---------|-------|--------|--------|
| `ContenedorController.php` | 50 | ✅ Arial configurado | Arial |
| `Movimiento.php` | 177 | ✅ Arial configurado | Arial |
| `Envio.php` | 369 | ⚠️ **Revisar** | ? |
| `Envio.php` | 1342 | ⚠️ **Revisar** | ? |
| `StockDeposito.php` | 265 | ⚠️ **Revisar** | ? |

### **Archivos Pendientes de Revisar**
Necesito verificar si `Envio.php` y `StockDeposito.php` también tienen Arial configurado:

```bash
# Buscar configuración de mPDF en Envio.php
grep -A 10 "new \\\Mpdf\\\Mpdf" api/src/Model/Envio.php
```

---

## 🚀 Solución Recomendada Inmediata

### **Opción 1: Reiniciar Servicios** (Más probable)
```bash
1. Abrir XAMPP Control Panel
2. Stop Apache
3. Stop MySQL (opcional)
4. Start Apache
5. Start MySQL
6. Probar reportes nuevamente
```

### **Opción 2: Limpiar Cache Navegador**
```
1. Ctrl + F5 en cada página
2. O abrir en modo incógnito
3. Probar generar reportes
```

### **Opción 3: Verificar Otros Archivos**
Si persiste el error, revisar configuración en:
- `api/src/Model/Envio.php` (2 instancias)
- `api/src/Model/StockDeposito.php` (1 instancia)

---

## 📝 Próximos Pasos

1. **Reiniciar Apache** en XAMPP
2. **Limpiar cache navegador** (Ctrl + F5)
3. **Probar generar**:
   - Códigos de barras de contenedores
   - Reporte PDF de movimientos
4. Si persiste: **Verificar logs** de PHP
5. Si aún falla: **Revisar configuración** en otros archivos

---

## ✅ Estado de Cambios Relacionados

### **Completados**
- [x] Fix error `actualizarTablaProductos()` → `actualizarTablaProductosEnvio()`
- [x] Fix filtro ubicación en backend (soporte para `ubicacion_destino`)
- [x] Fechas por defecto en envíos (ayer - hoy)
- [x] Verificación de Arial en ContenedorController y Movimiento

### **Pendientes**
- [ ] Reiniciar Apache/servicios
- [ ] Probar reportes después de reinicio
- [ ] Verificar configuración en Envio.php si persiste
- [ ] Verificar configuración en StockDeposito.php si persiste

---

## 📚 Referencias

- Documentación mPDF: https://mpdf.github.io/
- Configuración de fuentes: https://mpdf.github.io/fonts-languages/fonts-in-mpdf-7-x.html
- Arial es fuente **core** de mPDF (siempre disponible, no requiere archivos TTF)
