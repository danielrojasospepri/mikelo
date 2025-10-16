# 📦 ARCHIVOS PARA SUBIR A PRODUCCIÓN - Stock Depósito Fixes

## ❌ Problemas Identificados

### 1. Error PDF Export: "Unexpected token '%', "%PDF-1.4" is not valid JSON"
**Causa**: JavaScript en producción intenta parsear PDF binario como JSON
**Solución**: Actualizar `stock_deposito.js` para usar iframe download

### 2. Error Dar de Baja: "Column 'descripcion' not found in INSERT INTO"
**Causa**: Código intenta insertar en columna `descripcion` que no existe en tabla `estados`
**Solución**: Actualizar `StockDeposito.php` para usar solo columna `nombre`

---

## 📋 ARCHIVOS A SUBIR (3 archivos)

### 1. api/src/Model/StockDeposito.php
**Cambios realizados**:
1. Retorna rutas relativas en lugar de absolutas
   - ❌ Antes: `return '/mikelo/temp/' . $nombreArchivo;`
   - ✅ Ahora: `return 'temp/' . $nombreArchivo;`

2. Corrige INSERT en tabla `estados` (línea ~179)
   - ❌ Antes: `INSERT INTO estados (nombre, descripcion) VALUES (...)`
   - ✅ Ahora: `INSERT INTO estados (nombre) VALUES ('BAJA')`
   - **Razón**: Tabla `estados` solo tiene columnas `id` y `nombre`

3. Corrige INSERT en tabla `estados_items_movimientos` (línea ~189)
   - ❌ Antes: `INSERT ... (id_movimientos_items, id_estados, fecha_alta, observaciones, usuario_alta)`
   - ✅ Ahora: `INSERT ... (id_movimientos_items, id_estados, fecha_alta, usuario_alta)`
   - **Razón**: Tabla `estados_items_movimientos` NO tiene columna `observaciones`
   - **Nota**: El motivo se guarda en `movimientos_cambios.motivo`

4. Excluye productos dados de baja del stock (líneas ~19 y ~77)
   - ✅ Agrega condición en `obtenerStockAgrupado()`:
     ```sql
     NOT EXISTS (
         SELECT 1 FROM estados_items_movimientos eim
         JOIN estados e ON eim.id_estados = e.id
         WHERE eim.id_movimientos_items = mi.id
         AND e.nombre = 'BAJA'
     )
     ```
   - ✅ Agrega la misma condición en `obtenerDetalleBandejas()`
   - **Razón**: Productos dados de baja no deben aparecer en stock activo

5. Corrige suma de unidades en stock (línea ~58)
   - ❌ Antes: `COUNT(mi.id) as total_unidades` (contaba registros)
   - ✅ Ahora: `SUM(mi.cnt) as total_unidades` (suma cantidades reales)
   - **Razón**: Debe mostrar la suma total de unidades, no la cantidad de registros
   - **Impacto**: Tabla principal, PDF y Excel ahora muestran totales correctos

6. Formatea números sin decimales innecesarios (líneas ~320 y ~400)
   - ✅ PHP (reportes): Usa función que elimina `.000` → muestra `3` en lugar de `3.000`
   - ✅ JavaScript (tabla): Usa `formatearNumero()` que elimina decimales cero
   - **Ejemplo**: `1` en lugar de `1.000`, pero `1.250` se mantiene
   - **Impacto**: Reportes PDF, tabla HTML y modal de detalles más limpios

**Líneas modificadas**:
- Línea ~179: darDeBaja() - Creación de estado
- Línea ~267: exportarPDF() - Ruta relativa
- Línea ~294: exportarExcel() - Ruta relativa

---

### 2. api/src/Controller/StockDepositoController.php
**Cambios realizados**:

1. **Método `exportarPDF()`** (líneas ~120-148)
   - ❌ Antes: `return responseJson(['url' => $url]);` (JSON)
   - ✅ Ahora: Headers + `file_get_contents()` + write to body (binario)
   - **Content-Type**: `application/pdf`

2. **Método `exportarExcel()`** (líneas ~151-181)
   - ❌ Antes: `return responseJson(['url' => $url]);` (JSON)
   - ✅ Ahora: Headers + `file_get_contents()` + write to body (binario)
   - **Content-Type**: `application/vnd.openxmlformats-officedocument.spreadsheetml.sheet`

**Patrón unificado**: Ambos métodos ahora retornan archivos binarios directamente

```php
// NUEVO CÓDIGO (líneas 120-148)
public function exportarPDF(Request $request, Response $response) {
    $params = $request->getQueryParams();
    $filtros = [
        'producto' => $params['producto'] ?? null,
        'contenedor' => $params['contenedor'] ?? null,
        'fechaDesde' => $params['fechaDesde'] ?? null,
        'fechaHasta' => $params['fechaHasta'] ?? null
    ];

    try {
        $rutaRelativa = $this->stockDeposito->exportarPDF($filtros);
        $rutaCompleta = __DIR__ . '/../../../' . $rutaRelativa;
        
        if (!file_exists($rutaCompleta)) {
            return responseJson($response, ['error' => 'Archivo no encontrado'], 404);
        }

        $nombreArchivo = basename($rutaRelativa);
        
        // Configurar headers para descarga directa
        $response = $response
            ->withHeader('Content-Type', 'application/pdf')
            ->withHeader('Content-Disposition', 'attachment; filename="' . $nombreArchivo . '"')
            ->withHeader('Content-Length', filesize($rutaCompleta));

        // Leer y enviar archivo
        $response->getBody()->write(file_get_contents($rutaCompleta));
        
        return $response;
    } catch (\Exception $e) {
        return responseJson($response, ['error' => $e->getMessage()], 500);
    }
}
```

---

### 3. js/stock_deposito.js
**Cambio**: Usa iframe para descarga en lugar de fetch con JSON
- ❌ Antes: `fetch().then(r => r.json()).then(data => window.open(data.url))`
- ✅ Ahora: `iframe.src = 'api/stock-deposito/pdf'` (descarga automática)

**Función modificada**: `exportarStock()` (líneas 410-438)

```javascript
// NUEVO CÓDIGO (líneas 410-438)
function exportarStock(formato) {
    let filtros = obtenerFiltros();
    let queryString = Object.keys(filtros)
        .filter(key => filtros[key])
        .map(key => `${key}=${encodeURIComponent(filtros[key])}`)
        .join('&');

    mostrarCargando('Generando reporte...');

    // Crear un iframe oculto para la descarga
    const iframe = document.createElement('iframe');
    iframe.style.display = 'none';
    iframe.src = `api/stock-deposito/${formato}?${queryString}`;
    
    document.body.appendChild(iframe);
    
    // Esperar un momento y cerrar el loading
    setTimeout(() => {
        Swal.close();
        Swal.fire({
            icon: 'success',
            title: 'Reporte generado',
            text: 'El reporte se está descargando',
            timer: 2000,
            showConfirmButton: false
        });
        
        // Limpiar iframe después de 10 segundos
        setTimeout(() => {
            document.body.removeChild(iframe);
        }, 10000);
    }, 1500);
}
```

---

## 🔍 VERIFICACIÓN EN PRODUCCIÓN

### Paso 1: Verificar versión del archivo JavaScript
Abre DevTools (F12) → Console → Ejecuta:

```javascript
fetch('js/stock_deposito.js')
    .then(r => r.text())
    .then(code => {
        const usaIframe = code.includes('iframe.src = `api/stock-deposito/${formato}');
        const usaFetch = code.includes('fetch(`api/stock-deposito/pdf');
        console.log('Usa iframe:', usaIframe ? 'SÍ ✅' : 'NO ❌');
        console.log('Usa fetch JSON:', usaFetch ? 'SÍ ❌' : 'NO ✅');
    });
```

**Resultado esperado**:
- Usa iframe: **SÍ ✅**
- Usa fetch JSON: **NO ✅**

### Paso 2: Probar exportación PDF
1. Ir a `stock_deposito.html`
2. Click en "Exportar PDF"
3. Debe descargarse automáticamente sin errores

---

## 📁 ESTRUCTURA DE CARPETAS EN PRODUCCIÓN

Hostinger (raíz del proyecto):
```
/
├── api/
│   └── src/
│       ├── Controller/
│       │   └── StockDepositoController.php  ← SUBIR
│       └── Model/
│           └── StockDeposito.php  ← SUBIR
├── js/
│   └── stock_deposito.js  ← SUBIR
└── stock_deposito.html
```

---

## ⚠️ NOTAS IMPORTANTES

1. **Rutas relativas**: El código usa rutas relativas (`api/...`) que funcionan tanto en:
   - Local: `localhost/mikelo/api/...`
   - Producción: `tudominio.com/api/...`

2. **No hay diferencia entre local y producción** en cuanto a rutas porque son relativas al documento HTML

3. **El error actual en producción** es por archivo JavaScript desactualizado, NO por diferencia de carpetas

4. **Permisos temp/**: Verificar que la carpeta `temp/` tenga permisos 755 o 775 en Hostinger

---

## ✅ CHECKLIST DE SUBIDA

- [ ] Subir `api/src/Model/StockDeposito.php`
- [ ] Subir `api/src/Controller/StockDepositoController.php`
- [ ] Subir `js/stock_deposito.js`
- [ ] Verificar permisos carpeta `temp/` (755 o 775)
- [ ] Probar exportación PDF en producción
- [ ] Verificar en DevTools que no hay error de JSON

---

## 🐛 SI SIGUE FALLANDO

1. Limpiar caché del navegador (Ctrl+Shift+Del)
2. Verificar en Network tab que descarga `stock_deposito.js` actualizado
3. Revisar logs de error en `api/` si existe
4. Verificar que la carpeta `temp/` existe y tiene permisos correctos

---

**Generado**: 15/10/2025
**Versión**: 2.0 - Unificación arquitectura PDF
