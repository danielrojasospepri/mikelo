# Corrección: Select de Contenedores en Alta Depósito

## Problema Identificado
El select de contenedores en el módulo "Alta en Depósito" no se llenaba con las opciones disponibles.

## Causa del Problema
1. **Ruta API Incorrecta**: El JavaScript estaba llamando a `api/contenedores` 
2. **Campo Inexistente**: La consulta SQL usaba `WHERE activo = 1` pero la tabla `contenedores` no tiene el campo `activo`
3. **Estructura de Respuesta Inconsistente**: La respuesta usaba `data.contenedores` en lugar de `data.data`

## Solución Implementada

### 1. Corrección en el Backend (`api/index.php`)
**ANTES:**
```sql
SELECT id, nombre, peso FROM contenedores WHERE activo = 1 ORDER BY nombre
```
```php
'contenedores' => $contenedores
```

**DESPUÉS:**
```sql
SELECT id, nombre, peso FROM contenedores ORDER BY nombre
```
```php
'data' => $contenedores
```

### 2. Corrección en el Frontend (`js/alta_deposito.js`)
**ANTES:**
```javascript
fetch('api/contenedores')
// ...
data.contenedores.forEach(contenedor => {
```

**DESPUÉS:**
```javascript
fetch('api/contenedores')
// ...
data.data.forEach(contenedor => {
```

## Resultado
- ✅ La ruta `api/contenedores` ahora funciona correctamente
- ✅ El select se llena con todas las opciones disponibles: Acrílico, Balde 10lts, Balde 5lts, Inoxidable, etc.
- ✅ La estructura de respuesta es consistente con otros endpoints
- ✅ Mantiene compatibilidad con la ruta alternativa `api/envios/contenedores`

## URLs de Prueba
- **Módulo**: http://localhost/mikelo/alta_deposito.html
- **API**: http://localhost/mikelo/api/contenedores

## Archivos Modificados
- ✅ `api/index.php` - Corregida consulta SQL y estructura de respuesta
- ✅ `js/alta_deposito.js` - Corregida estructura de procesamiento de datos

## Fecha de Corrección
3 de octubre de 2025