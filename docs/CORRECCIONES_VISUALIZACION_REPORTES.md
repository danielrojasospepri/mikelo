# 🔧 Correcciones de Visualización en Reportes

**Fecha**: 20 de octubre de 2025  
**Archivos modificados**: `api/src/Model/Envio.php`

---

## 🐛 Problemas Corregidos

### **Problema 1: Estado aparece como "N/A" en reporte PDF individual**

**Síntoma**: En el reporte PDF individual, la línea "Estado:" siempre mostraba "N/A" en lugar del estado real del envío (NUEVO, ENVIADO, RECIBIDO, etc.).

**Causa**: 
- La función `generarHTMLDetalleMinimal()` buscaba `$envio['estado']`
- Pero la función `obtenerDetalleEnvio()` devuelve el estado como `$envio['ultimo_estado']`
- El nombre del campo no coincidía

**Código incorrecto** (línea ~1242):
```php
<span class="label">Estado:</span> ' . htmlspecialchars($envio['estado'] ?? 'N/A') . '
```

**Código corregido**:
```php
<span class="label">Estado:</span> ' . htmlspecialchars($envio['ultimo_estado'] ?? 'N/A') . '
```

---

### **Problema 2: "Ndeg" en lugar de "N°" o "Nro" en reportes**

**Síntoma**: Los reportes mostraban "Remito Ndeg: 00000123" en lugar de "Remito Nro: 00000123" o "Remito N°: 00000123".

**Causa**: 
- Originalmente se usó "N°" (símbolo de número)
- El script de conversión a ASCII cambió "°" por "deg"
- Resultado: "Ndeg" (número + grado = texto sin sentido)

**Solución**: Cambiar a "Nro" (abreviatura de "Número" en ASCII puro)

---

## 🔧 Cambios Aplicados

### **Cambio 1: Campo de estado en PDF individual**

**Función**: `generarHTMLDetalleMinimal()` (línea ~1242)

**Antes**:
```php
$envio['estado']  // Campo que no existe
```

**Después**:
```php
$envio['ultimo_estado']  // Campo correcto de obtenerDetalleEnvio()
```

**Visualización**:
```
Informacion del Envio
  Fecha:   20/10/2025 18:30
  Origen:  Deposito Central
  Destino: Sucursal Norte
  Estado:  ENVIADO          ← Ahora muestra el estado real
```

---

### **Cambio 2: "Ndeg" → "Nro" en Excel individual**

**Función**: `generarExcelDetalle()` (línea ~939)

**Antes**:
```php
$sheet->setCellValue('A2', 'REMITO Ndeg ' . str_pad($envio['id'], 8, '0', STR_PAD_LEFT));
```

**Después**:
```php
$sheet->setCellValue('A2', 'REMITO Nro ' . str_pad($envio['id'], 8, '0', STR_PAD_LEFT));
```

**Visualización en Excel**:
```
Celda A2: REMITO Nro 00000123
```

---

### **Cambio 3: "Ndeg" → "Nro" en remito preimpreso**

**Función**: `generarHTMLRemitoPreimpreso()` (línea ~1640)

**Antes**:
```php
$html .= 'Remito Ndeg: ' . str_pad($idMovimiento, 8, '0', STR_PAD_LEFT);
```

**Después**:
```php
$html .= 'Remito Nro: ' . str_pad($idMovimiento, 8, '0', STR_PAD_LEFT);
```

**Visualización en remito**:
```
Footer de página:
Remito Nro: 00000123 - Fecha: 20/10/2025 - Hoja 1 de 3
```

---

## 📊 Resumen de Campos Corregidos

| Reporte | Campo/Texto Anterior | Campo/Texto Corregido | Ubicación |
|---------|---------------------|----------------------|-----------|
| **PDF Individual** | `$envio['estado']` (N/A) | `$envio['ultimo_estado']` (ENVIADO) | Línea ~1242 |
| **Excel Individual** | "Ndeg" | "Nro" | Línea ~939 |
| **Remito Preimpreso** | "Ndeg" | "Nro" | Línea ~1640 |

---

## 🔍 Explicación Técnica

### **Por qué aparecía "N/A" en el estado**

La función `obtenerDetalleEnvio()` ejecuta esta consulta SQL:

```sql
SELECT 
    m.*,
    uo.nombre as origen,
    ud.nombre as destino,
    (
        SELECT e.nombre 
        FROM estados_items_movimientos eim
        JOIN estados e ON e.id = eim.id_estados
        WHERE eim.id_movimientos_items IN (
            SELECT id FROM movimientos_items WHERE id_movimientos = m.id
        )
        ORDER BY eim.fecha_alta DESC
        LIMIT 1
    ) as ultimo_estado    ← Este es el alias del campo
FROM movimientos m
...
```

El estado se obtiene con alias **`ultimo_estado`**, no `estado`.

Por eso:
- ✅ `$envio['ultimo_estado']` → Devuelve "ENVIADO"
- ❌ `$envio['estado']` → No existe, devuelve NULL → muestra "N/A"

---

### **Por qué aparecía "Ndeg"**

**Historial del problema**:

1. **Código original**: Usaba "N°" (símbolo de número)
   ```php
   'Remito N°: 00000123'
   ```

2. **Conversión a ASCII** (script `fix_utf8_to_ascii.php`):
   - Reemplazó `°` (símbolo de grado) por `deg` (texto)
   ```php
   'Remito Ndeg: 00000123'  // N + deg = Ndeg (sin sentido)
   ```

3. **Corrección actual**: Cambiado a "Nro" (abreviatura estándar)
   ```php
   'Remito Nro: 00000123'  // Claro y ASCII puro
   ```

**Nota**: "Nro" es la abreviatura estándar de "Número" en español, ampliamente usada en documentos comerciales.

---

## ✅ Verificaciones Realizadas

### **Test 1: Sintaxis PHP**
```bash
php -l api/src/Model/Envio.php
# Resultado: ✅ No syntax errors detected
```

### **Test 2: BOM UTF-8**
```powershell
[System.IO.File]::WriteAllText(...)
# Resultado: ✅ BOM eliminado automáticamente
```

---

## 🧪 Pruebas Recomendadas

### **Test 1: Estado en PDF Individual**
```
1. Abrir modal de detalle de un envío
2. Verificar que el envío tenga un estado (NUEVO, ENVIADO, etc.)
3. Clic en "Exportar PDF"
4. Abrir el PDF generado
5. Verificar sección "Informacion del Envio"
   ✅ Estado: ENVIADO (o el estado actual)
   ❌ NO debe decir "Estado: N/A"
```

### **Test 2: "Nro" en Excel Individual**
```
1. Mismo envío del test anterior
2. Clic en "Exportar Excel"
3. Abrir archivo .xlsx
4. Verificar celda A2:
   ✅ "REMITO Nro 00000123"
   ❌ NO debe decir "REMITO Ndeg 00000123"
```

### **Test 3: "Nro" en Remito Preimpreso**
```
1. Generar remito preimpreso: GET /api/envios/{id}/pdf-preimpreso
2. Abrir el PDF generado
3. Verificar footer de la página:
   ✅ "Remito Nro: 00000123 - Fecha: 20/10/2025"
   ❌ NO debe decir "Remito Ndeg: 00000123"
   
4. Si hay múltiples hojas:
   ✅ "Remito Nro: 00000123 - Fecha: 20/10/2025 - Hoja 1 de 3"
```

### **Test 4: Estado en Remito Preimpreso**
```
1. Mismo remito del test anterior
2. Verificar banda de cliente (superior, fondo gris):
   ✅ "NOMBRE CLIENTE - Estado: ENVIADO"
   ✅ El estado debe coincidir con el real
```

---

## 📝 Notas Técnicas

### **Consistencia de Campos de Estado**

Ahora los 3 reportes usan el campo correcto:

| Reporte | Campo PHP | Origen SQL |
|---------|-----------|------------|
| **PDF Individual** | `$envio['ultimo_estado']` | `obtenerDetalleEnvio()` |
| **Excel Individual** | `$envio['ultimo_estado']` | `obtenerDetalleEnvio()` |
| **Remito Preimpreso** | `$movimiento['estado_actual']` | Consulta propia (mismo SQL) |

**Nota**: El remito preimpreso usa `estado_actual` porque tiene su propia consulta SQL, pero es **el mismo subconsulta** que `ultimo_estado`.

---

### **Formato de Número de Remito**

**Estándares aplicados**:
- ✅ "Nro" - Abreviatura estándar en español
- ✅ ASCII puro (compatible con todos los sistemas)
- ✅ Padding de 8 dígitos con ceros: `00000123`

**Alternativas descartadas**:
- ❌ "N°" - Requiere UTF-8, causó problema con conversión ASCII
- ❌ "Ndeg" - Sin sentido semántico
- ❌ "Num" - Anglicismo, menos estándar en español

---

## 🎉 Estado Final

### **Cambios Completados**
1. ✅ Estado corregido en PDF individual: `$envio['ultimo_estado']`
2. ✅ "Ndeg" → "Nro" en Excel individual
3. ✅ "Ndeg" → "Nro" en remito preimpreso
4. ✅ BOM UTF-8 eliminado automáticamente
5. ✅ Sintaxis PHP verificada

### **Archivos Afectados**
- `api/src/Model/Envio.php`:
  - Línea ~939: "Ndeg" → "Nro" (Excel)
  - Línea ~1242: `estado` → `ultimo_estado` (PDF)
  - Línea ~1640: "Ndeg" → "Nro" (Remito preimpreso)

### **Próximos Pasos**
1. 🧪 Probar PDF individual con diferentes estados
2. 🧪 Verificar Excel con "Nro" en celda A2
3. 🧪 Confirmar remito preimpreso con "Nro" en footer

---

✅ **¡Problemas de visualización corregidos!**

El estado ahora se muestra correctamente en todos los reportes, y "Nro" reemplaza al incorrecto "Ndeg".
