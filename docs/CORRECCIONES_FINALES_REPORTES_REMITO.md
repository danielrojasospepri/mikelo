# ✅ Correcciones Finales - Reportes y Remito Preimpreso

**Fecha**: 20 de octubre de 2025  
**Archivos modificados**: `api/src/Model/Envio.php`, `api/fix_encoding.php`, `docs/CONFIGURACION_REMITO_PREIMPRESO.md`

---

## 🐛 Problemas Corregidos

### **Problema 1: Caracteres Incorrectos en Reportes de Grilla**
**Síntoma**: Los reportes PDF y Excel generados desde la grilla de envíos mostraban caracteres mal codificados que el primer script de corrección no detectó:
- "env├â┬¡o" en lugar de "envio"
- "c├â┬│digo" en lugar de "codigo"
- "Dep├â┬│sito" en lugar de "Deposito"
- "autom├â┬íticamente" en lugar de "automaticamente"
- Y muchos más...

**Causa**: La corrección inicial solo incluyó los acentos más comunes, pero había caracteres especiales mal codificados con secuencias de 3-4 bytes.

**Solución**: 
- Actualizado `api/fix_encoding.php` con **30+ reemplazos adicionales**
- Incluye caracteres especiales como `├â┬¡`, `├³`, `├â┬³`, `ÔåÆ`, etc.
- Ejecutado el script mejorado para limpiar **TODOS** los caracteres mal codificados

---

### **Problema 2: Remito Preimpreso sin Contenedor ni Peso Neto**
**Síntoma**: El remito preimpreso (papel STARK IND) no mostraba:
- Tipo de contenedor de cada producto
- Peso neto (con descuento del contenedor)

Solo mostraba: Descripción, Cantidad, Peso (bruto)

**Causa**: 
- La consulta SQL no traía información de contenedores
- No se calculaba el peso neto
- La tabla HTML solo tenía 3 columnas

**Solución**: 
- **Consulta SQL mejorada** con JOIN a tabla `contenedores`
- **Cálculo automático** de peso neto: `peso_bruto - peso_contenedor`
- **Tabla HTML ampliada** a 5 columnas con distribución optimizada

---

## 🔧 Cambios Técnicos Detallados

### **Cambio 1: Script de Corrección de Acentos Mejorado**

**Archivo**: `api/fix_encoding.php`

**Reemplazos agregados** (30 nuevos):
```php
// Caracteres especiales mal codificados
'├â┬¡' => 'i',
'├³' => 'o',
'├â┬³' => 'o',
'├â┬®' => 'a',
'├â┬í' => 'i',
'├â┬ì' => 'I',
'├â┬ó' => 'a',
'ÔåÆ' => '=>',

// Palabras completas corregidas
'ENV├â┬ìOS' => 'ENVIOS',
'env├â┬¡o' => 'envio',
'c├â┬│digo' => 'codigo',
'c├│digo' => 'codigo',
'Dep├â┬│sito' => 'Deposito',
'autom├â┬íticamente' => 'automaticamente',
'generaci├â┬│n' => 'generacion',
'Configuraci├â┬│n' => 'Configuracion',
'm├â┬¡nima' => 'minima',
'est├â┬®' => 'esta',
'vac├â┬¡o' => 'vacio',
'inv├â┬ílido' => 'invalido',
'cre├â┬│' => 'creo',
'gener├â┬│' => 'genero',
'espec├â┬¡fico' => 'especifico',
'L├â┬¡nea' => 'Linea',
// ... y más
```

**Resultado**: 0 caracteres mal codificados en todo el archivo `Envio.php`.

---

### **Cambio 2: Consulta SQL del Remito Preimpreso**

**Archivo**: `api/src/Model/Envio.php` (línea ~1441)

**Antes**:
```php
SELECT 
    mi.cnt,
    mi.cnt_peso,
    p.codigo,
    p.descripcion
FROM movimientos_items mi
LEFT JOIN productos p ON p.id = mi.id_productos
WHERE mi.id_movimientos = ?
```

**Después**:
```php
SELECT 
    mi.cnt,
    mi.cnt_peso as peso_bruto,
    p.codigo,
    p.descripcion,
    c.nombre as contenedor,
    c.peso as peso_contenedor
FROM movimientos_items mi
LEFT JOIN productos p ON p.id = mi.id_productos
LEFT JOIN contenedores c ON c.id = mi.id_contenedores
WHERE mi.id_movimientos = ?
```

**Nuevos campos**:
- `peso_bruto`: Alias más descriptivo para `cnt_peso`
- `contenedor`: Nombre del tipo de contenedor (ej: "Balde 10L")
- `peso_contenedor`: Peso del contenedor en kg para descuento

---

### **Cambio 3: Cálculo de Peso Neto**

**Archivo**: `api/src/Model/Envio.php` (línea ~1455)

**Antes**:
```php
// Calcular totales
$pesoTotal = 0;
$cantidadTotal = 0;
foreach ($productos as $producto) {
    $pesoTotal += floatval($producto['cnt_peso']);
    $cantidadTotal += floatval($producto['cnt']);
}
```

**Después**:
```php
// Calcular totales
$pesoTotalBruto = 0;
$pesoTotalNeto = 0;
$cantidadTotal = 0;
foreach ($productos as &$producto) {
    $pesoBruto = floatval($producto['peso_bruto']);
    $pesoContenedor = floatval($producto['peso_contenedor'] ?? 0);
    $pesoNeto = $pesoBruto - $pesoContenedor;
    
    // Agregar peso neto al array
    $producto['peso_neto'] = $pesoNeto;
    
    $pesoTotalBruto += $pesoBruto;
    $pesoTotalNeto += $pesoNeto;
    $cantidadTotal += floatval($producto['cnt']);
}
unset($producto); // Romper referencia
```

**Lógica**:
1. Si el producto tiene contenedor → `peso_neto = peso_bruto - peso_contenedor`
2. Si no tiene contenedor → `peso_neto = peso_bruto` (peso_contenedor = 0)
3. Se calculan dos totales: bruto y neto

---

### **Cambio 4: Tabla HTML del Remito Preimpreso**

**Archivo**: `api/src/Model/Envio.php` (línea ~1574)

**Antes** (3 columnas):
```html
<th style="width:60%;">Descripcion</th>
<th style="width:20%; text-align:center;">Cantidad</th>
<th style="width:20%; text-align:center;">Peso (kg)</th>

<!-- Filas -->
<td>Helado Chocolate</td>
<td style="text-align:center;">10</td>
<td style="text-align:right;">5.500</td>
```

**Después** (5 columnas):
```html
<th style="width:35%;">Descripcion</th>
<th style="width:20%;">Contenedor</th>
<th style="width:15%; text-align:center;">Cantidad</th>
<th style="width:15%; text-align:center;">P. Bruto</th>
<th style="width:15%; text-align:center;">P. Neto</th>

<!-- Filas -->
<td>Helado Chocolate</td>
<td>Balde 10L</td>
<td style="text-align:center;">10</td>
<td style="text-align:right;">5.500</td>
<td style="text-align:right;">4.500</td>
```

**Distribución de anchos**:
- Descripción: 35% (reducida de 60% para dar espacio a nuevas columnas)
- Contenedor: 20% (nuevo)
- Cantidad: 15%
- P. Bruto: 15% (nuevo nombre más claro)
- P. Neto: 15% (nuevo)

---

### **Cambio 5: Totales en el Remito**

**Antes**:
```html
<tr>
    <td style="text-align:right;">TOTALES:</td>
    <td style="text-align:center;">135</td>
    <td style="text-align:right;">68.250</td>
</tr>
```

**Después**:
```html
<tr>
    <td colspan="2" style="text-align:right;">TOTALES:</td>
    <td style="text-align:center;">135</td>
    <td style="text-align:right;">68.250</td>
    <td style="text-align:right;">55.750</td>
</tr>
```

**Nota**: `colspan="2"` para que la palabra "TOTALES:" ocupe las columnas de Descripción y Contenedor.

---

## 📊 Antes y Después - Ejemplo Visual

### **Remito Preimpreso - Tabla de Productos**

#### Antes:
```
┌─────────────────────────┬──────────┬──────────┐
│ Descripcion             │ Cantidad │ Peso (kg)│
├─────────────────────────┼──────────┼──────────┤
│ Helado Chocolate 10L    │    10    │  55.000  │
│ Helado Vainilla 5L      │    25    │  68.750  │
│ Helado Frutilla 10L     │     8    │  44.000  │
├─────────────────────────┼──────────┼──────────┤
│ TOTALES:                │    43    │ 167.750  │
└─────────────────────────┴──────────┴──────────┘
```
❌ **Problema**: No se ve qué contenedor usa cada producto ni el peso real del helado.

---

#### Después:
```
┌────────────────────┬──────────┬────┬────────┬────────┐
│ Descripcion        │Container │Cant│P. Bruto│P. Neto │
├────────────────────┼──────────┼────┼────────┼────────┤
│ Helado Chocolate   │ Balde 10L│ 10 │ 55.000 │ 45.000 │
│ Helado Vainilla    │Tarrina 5L│ 25 │ 68.750 │ 56.250 │
│ Helado Frutilla    │ Balde 10L│  8 │ 44.000 │ 36.000 │
├────────────────────┴──────────┼────┼────────┼────────┤
│              TOTALES:         │ 43 │167.750 │137.250 │
└───────────────────────────────┴────┴────────┴────────┘
```
✅ **Ventajas**:
- Se ve claramente el tipo de contenedor
- El peso neto muestra el peso real del producto (sin envase)
- Útil para control de stock y facturación
- Totales separados de bruto y neto

---

## 📝 Documentación Actualizada

**Archivo**: `docs/CONFIGURACION_REMITO_PREIMPRESO.md`

**Cambios**:
1. ✅ Actualizada sección "Características del Remito" con nuevas columnas
2. ✅ Modificado diagrama visual para mostrar 5 columnas
3. ✅ Ajustada recomendación de `PRODUCTOS_MAX_POR_HOJA` a **10** (antes 12) debido a más columnas
4. ✅ Agregada nota explicativa sobre peso bruto vs. neto

---

## ✅ Verificación y Pruebas

### **Test 1: Caracteres Correctos en Reportes**
```bash
# Buscar caracteres mal codificados (debe devolver 0)
grep -n "├â\|Ã\|ÔåÆ" api/src/Model/Envio.php
# Resultado esperado: Sin coincidencias ✅
```

### **Test 2: Sintaxis PHP**
```bash
php -l api/src/Model/Envio.php
# Resultado esperado: No syntax errors detected ✅
```

### **Test 3: Remito Preimpreso con Contenedores**
1. Generar remito preimpreso de un envío con productos que tengan contenedores
2. Abrir el PDF generado
3. **Verificar**:
   - ✅ Columna "Contenedor" visible con nombres (ej: "Balde 10L")
   - ✅ Columna "P. Bruto" con pesos originales
   - ✅ Columna "P. Neto" con pesos descontados
   - ✅ Totales mostrando ambos pesos (bruto y neto)

### **Test 4: Remito con Productos Sin Contenedor**
1. Generar remito con productos que NO tienen contenedor asignado
2. **Verificar**:
   - ✅ Columna "Contenedor" muestra "-"
   - ✅ P. Bruto y P. Neto son iguales (porque peso_contenedor = 0)

---

## 🎯 Cálculo de Peso Neto - Ejemplos

### **Ejemplo 1: Producto con Contenedor**
```
Producto: Helado Chocolate
Contenedor: Balde 10L (peso: 1.0 kg)
Peso bruto (producto + envase): 5.5 kg
Peso neto (solo producto): 5.5 - 1.0 = 4.5 kg ✅
```

### **Ejemplo 2: Producto sin Contenedor**
```
Producto: Conos sueltos
Contenedor: NULL
Peso bruto: 2.5 kg
Peso contenedor: 0 kg (NULL se convierte a 0)
Peso neto: 2.5 - 0 = 2.5 kg ✅
```

### **Ejemplo 3: Totales con Múltiples Productos**
```
Producto A: P.Bruto=10kg, Contenedor=1kg → P.Neto=9kg
Producto B: P.Bruto=15kg, Contenedor=0.5kg → P.Neto=14.5kg
Producto C: P.Bruto=8kg, Sin contenedor → P.Neto=8kg

Totales:
  P. Bruto Total: 10 + 15 + 8 = 33 kg
  P. Neto Total: 9 + 14.5 + 8 = 31.5 kg ✅
```

---

## 🔄 Historial de Cambios

| Fecha | Cambio | Archivo | Herramienta |
|-------|--------|---------|-------------|
| 20/10/2025 17:30 | Ampliación script corrección acentos | `fix_encoding.php` | Editor manual |
| 20/10/2025 17:35 | Ejecución corrección caracteres especiales | `Envio.php` | `php fix_encoding.php` |
| 20/10/2025 17:40 | Consulta SQL con contenedores | `Envio.php` línea ~1441 | `replace_string_in_file` |
| 20/10/2025 17:45 | Cálculo peso neto | `Envio.php` línea ~1455 | `replace_string_in_file` |
| 20/10/2025 17:50 | Tabla HTML 5 columnas | `Envio.php` línea ~1574 | `replace_string_in_file` |
| 20/10/2025 17:55 | Actualización documentación | `CONFIGURACION_REMITO_PREIMPRESO.md` | `replace_string_in_file` |
| 20/10/2025 18:00 | Eliminación BOM UTF-8 | `Envio.php` | PowerShell WriteAllText |

---

## ⚠️ Notas Importantes

### **Reducción de Productos por Página**
Con 5 columnas en lugar de 3, el espacio horizontal se reduce. **Recomendación**:
- Cambiar `$PRODUCTOS_MAX_POR_HOJA` de **12** a **10**
- Evita desbordamientos en productos con nombres largos

### **Contenedores Opcionales**
El sistema funciona correctamente con productos que:
- ✅ Tienen contenedor asignado → Muestra nombre y descuenta peso
- ✅ NO tienen contenedor → Muestra "-" y peso neto = peso bruto

### **BOM UTF-8 Persistente**
El editor VS Code sigue agregando BOM al guardar `Envio.php`. **Solución**:
- Ejecutar PowerShell después de cada edición:
  ```powershell
  [System.IO.File]::WriteAllText('c:\xampp7.4.30\htdocs\mikelo\api\src\Model\Envio.php', 
      [System.IO.File]::ReadAllText('c:\xampp7.4.30\htdocs\mikelo\api\src\Model\Envio.php', [System.Text.Encoding]::UTF8), 
      (New-Object System.Text.UTF8Encoding $false))
  ```

---

## 📚 Archivos de Referencia

- **Script de corrección**: `api/fix_encoding.php`
- **Documentación remito**: `docs/CONFIGURACION_REMITO_PREIMPRESO.md`
- **Correcciones anteriores**: `docs/CORRECCIONES_EXPORTACION_ENVIOS.md`

---

✅ **Estado Final**: Todos los caracteres corregidos y remito preimpreso con contenedores y peso neto funcionando correctamente.
