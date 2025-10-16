# 🔧 Resumen de Correcciones - Stock Depósito

**Fecha**: 15/10/2025  
**Módulo**: Stock Depósito (`stock_deposito.html`)

---

## 🐛 Problemas Corregidos

### 1. ❌ Error al Exportar PDF
**Síntoma**: "Unexpected token '%', "%PDF-1.4" is not valid JSON"  
**Causa**: JavaScript esperaba respuesta JSON pero servidor retornaba PDF binario  
**Solución**: Cambiar de `fetch().then(r=>r.json())` a descarga con `<iframe>`

### 2. ❌ Error al Dar de Baja - Columna "descripcion"
**Síntoma**: "Column 'descripcion' not found in INSERT INTO estados"  
**Causa**: Tabla `estados` solo tiene columnas `id` y `nombre`  
**Solución**: Remover columna `descripcion` del INSERT

### 3. ❌ Error al Dar de Baja - Columna "observaciones"
**Síntoma**: "Column 'observaciones' not found in field list"  
**Causa**: Tabla `estados_items_movimientos` no tiene columna `observaciones`  
**Solución**: Remover columna del INSERT, el motivo va en `movimientos_cambios`

### 4. ⚠️ Productos de Baja Aparecen en Stock
**Síntoma**: Productos dados de baja siguen mostrándose en el stock activo  
**Causa**: Consultas SQL no excluían items con estado "BAJA"  
**Solución**: Agregar condición `NOT EXISTS` para excluir estado "BAJA"

---

## 📁 Archivos Modificados (3 archivos)

### 1️⃣ `api/src/Model/StockDeposito.php`
**Cambios**:
- ✅ Línea ~19: Excluir productos BAJA en `obtenerStockAgrupado()`
- ✅ Línea ~77: Excluir productos BAJA en `obtenerDetalleBandejas()`
- ✅ Línea ~179: INSERT sin columna `descripcion` en tabla `estados`
- ✅ Línea ~189: INSERT sin columna `observaciones` en tabla `estados_items_movimientos`
- ✅ Línea ~267: Retornar ruta relativa `temp/archivo.pdf` en `exportarPDF()`
- ✅ Línea ~294: Retornar ruta relativa `temp/archivo.pdf` en `exportarExcel()`

### 2️⃣ `api/src/Controller/StockDepositoController.php`
**Cambio**:
- ✅ Línea ~120: Método `exportarPDF()` retorna PDF binario con headers (igual que EnvioController)

### 3️⃣ `js/stock_deposito.js`
**Cambio**:
- ✅ Línea ~410: Función `exportarStock()` usa iframe para descarga binaria (en lugar de fetch+JSON)

---

## 🔍 Detalles Técnicos

### Exclusión de Productos Dados de Baja
```sql
NOT EXISTS (
    SELECT 1 FROM estados_items_movimientos eim
    JOIN estados e ON eim.id_estados = e.id
    WHERE eim.id_movimientos_items = mi.id
    AND e.nombre = 'BAJA'
)
```
Esta condición se agregó en:
- `obtenerStockAgrupado()` - Para el listado principal
- `obtenerDetalleBandejas()` - Para el detalle por producto

### Estado de Baja
- **Nombre**: `BAJA` (simple y claro)
- **Tabla**: `estados`
- **Se crea automáticamente** si no existe al dar de baja el primer producto
- **Motivo de baja**: Se guarda en `movimientos_cambios.motivo` (no en estados_items_movimientos)

### Arquitectura PDF Unificada
Tanto `envios` como `stock_deposito` ahora usan:
1. **Backend**: Respuesta binaria con headers (`Content-Type: application/pdf`)
2. **Frontend**: Descarga con iframe oculto
3. **Rutas**: Relativas (`temp/archivo.pdf`) para compatibilidad local/producción

---

## ✅ Testing Local

1. **Exportar PDF**: ✅ Descarga automática sin errores
2. **Dar de Baja**: ✅ No genera errores SQL
3. **Stock después de baja**: ✅ Productos dados de baja NO aparecen
4. **Detalle de bandejas**: ✅ Solo muestra bandejas activas

---

## 🚀 Deployment a Producción

### Archivos a Subir:
```
api/src/Model/StockDeposito.php
api/src/Controller/StockDepositoController.php
js/stock_deposito.js
```

### Verificación Post-Deploy:
1. Verificar versión JS: `fetch('js/stock_deposito.js').then(r=>r.text()).then(c=>console.log(c.includes('iframe.src') ? '✅' : '❌'))`
2. Probar exportar PDF
3. Probar dar de baja un producto
4. Verificar que producto de baja NO aparezca en stock

---

## 📊 Impacto

| Funcionalidad | Antes | Después |
|--------------|-------|---------|
| Exportar PDF | ❌ Error JSON | ✅ Descarga automática |
| Dar de Baja | ❌ Error SQL | ✅ Funciona correctamente |
| Stock Activo | ⚠️ Incluye bajas | ✅ Solo productos activos |
| Producción/Local | ⚠️ Rutas diferentes | ✅ Rutas relativas |

---

## 🔐 Seguridad y Auditoría

- ✅ Todas las bajas se registran en `movimientos_cambios`
- ✅ Se guarda usuario y fecha de baja
- ✅ Se guarda motivo de la baja
- ✅ Transacciones SQL para integridad de datos
- ✅ Rollback automático si hay error

---

**Generado**: 15/10/2025 16:30  
**Versión**: 3.0 - Stock Depósito Completo
