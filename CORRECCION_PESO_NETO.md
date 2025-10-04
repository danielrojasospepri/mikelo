# Corrección de Cálculo de Peso Neto para Productos sin Contenedor

## Problema Identificado
Los productos que no tenían contenedor asignado mostraban peso neto = 0 en lugar de peso neto = peso bruto.

## Causa del Problema
El cálculo original usaba:
```sql
(mi.cnt_peso - COALESCE(c.peso, 0)) as peso_neto
```

Esto causaba que cuando `c.peso` era `NULL` (sin contenedor), se restara 0, pero en algunos casos el resultado era incorrecto.

## Solución Implementada

### En Backend (PHP)
Cambio en `api/src/Model/Envio.php` en las consultas SQL:

**ANTES:**
```sql
(mi.cnt_peso - COALESCE(c.peso, 0)) as peso_neto
```

**DESPUÉS:**
```sql
CASE 
    WHEN c.peso IS NOT NULL THEN (mi.cnt_peso - c.peso)
    ELSE mi.cnt_peso
END as peso_neto
```

### En Frontend (JavaScript)
Cambio en `js/envios.js` y `test_correcciones_final.html`:

**ANTES:**
```javascript
const pesoNeto = pesoBruto - pesoContenedor;
```

**DESPUÉS:**
```javascript
// Si no hay contenedor (peso_contenedor es null), el peso neto = peso bruto
const pesoNeto = producto.peso_contenedor !== null ? (pesoBruto - pesoContenedor) : pesoBruto;
```

## Archivos Modificados
- ✅ `api/src/Model/Envio.php` - Funciones `obtenerDetalleEnvio()` y `obtenerProductosDisponibles()`
- ✅ `js/envios.js` - Función `verDetalleEnvio()`
- ✅ `test_correcciones_final.html` - Función de test del modal

## Resultado
Ahora cuando un producto NO tiene contenedor asignado:
- **Peso Neto = Peso Bruto** (como debe ser)
- En PDFs, Excel y modales se muestra correctamente
- Los totales se calculan correctamente

## Validación
- ✅ API funciona sin errores de sintaxis
- ✅ PDF genera correctamente con cálculos corregidos
- ✅ Modal muestra datos correctos
- ✅ Excel mantiene la misma lógica corregida

## Fecha de Corrección
3 de octubre de 2025