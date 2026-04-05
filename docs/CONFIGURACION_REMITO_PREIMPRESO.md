# Configuración Remito Preimpreso STARK IND

## 📄 Descripción General

El remito preimpreso está diseñado para imprimirse en papel A4 (210mm x 297mm) con membrete y logo de STARK IND ya impreso en el papel.

El sistema genera automáticamente múltiples páginas cuando hay más productos de los que caben en una hoja, con numeración "Hoja X de Y".

---

## ⚙️ Variables de Configuración

Todas las variables están en el archivo: **`api/src/Model/Envio.php`**  
Método: **`generarHTMLRemitoPreimpreso()`** (línea ~1366)

### 🔢 Paginación

```php
$PRODUCTOS_MAX_POR_HOJA = 12;
```

**Descripción**: Número máximo de productos por página  
**Valores recomendados**:
- `10` = Configuración segura con espacio extra (recomendado con 5 columnas)
- `12` = Compacto, usa más espacio disponible (default)
- `8` = Conservador para productos con nombres largos y contenedores con nombres largos

**Cómo ajustar**:
1. Si los productos se desbordan del pie de página → **REDUCIR** este valor
2. Si queda mucho espacio vacío → **AUMENTAR** este valor
3. Generar PDF de prueba después de cada cambio

**Nota**: Con 5 columnas (Descripción, Contenedor, Cantidad, P.Bruto, P.Neto) se recomienda reducir a 10 productos máximo.

---

### 📐 Posiciones Verticales (en milímetros)

```php
$POS_CLIENTE_TOP = 60;      // Banda información del cliente
$POS_CLIENTE_ALTO = 15;     // Altura de la banda cliente
$POS_TABLA_TOP = 95;        // Inicio de tabla de productos
$POS_FOOTER_BOTTOM = 30;    // Franja gris del pie (desde abajo)
$POS_DATOS_BOTTOM = 10;     // Datos remito (desde abajo)
```

#### Cómo medir y ajustar:

1. **POS_CLIENTE_TOP** (60mm):
   - Medir desde el **borde superior del papel** hasta donde debe aparecer la razón social
   - Si el logo preimpreso es más grande → AUMENTAR valor
   - Si necesitas más espacio arriba → AUMENTAR valor

2. **POS_CLIENTE_ALTO** (15mm):
   - Altura de la franja gris con información del cliente
   - Suficiente para 2-3 líneas de texto
   - Ajustar si la dirección es muy larga

3. **POS_TABLA_TOP** (95mm):
   - Inicio de la tabla de productos
   - Debe estar debajo de `POS_CLIENTE_TOP + POS_CLIENTE_ALTO + margen`
   - Fórmula recomendada: `POS_CLIENTE_TOP + POS_CLIENTE_ALTO + 20mm`

4. **POS_FOOTER_BOTTOM** (30mm):
   - Distancia desde el **borde inferior** a la franja gris decorativa
   - Si el papel preimpreso tiene pie de página → ajustar para que no se solapen

5. **POS_DATOS_BOTTOM** (10mm):
   - Distancia desde el **borde inferior** a "Remito N° / Fecha / Hoja X de Y"
   - Debe estar por encima de `POS_FOOTER_BOTTOM`

---

### 🎨 Estilos de Tabla

```php
$TABLA_FONT_SIZE = '9pt';   // Tamaño de fuente
$TABLA_PADDING = '1mm 2mm'; // Espaciado interno celdas
$TABLA_HEADER_ALTO = '8mm'; // Altura del encabezado
```

#### Efectos de cada variable:

| Variable | Aumentar → | Reducir → |
|----------|-----------|-----------|
| `TABLA_FONT_SIZE` | Texto más grande, menos productos por hoja | Más productos por hoja, difícil de leer |
| `TABLA_PADDING` | Filas más altas, más espacio visual | Filas compactas, mayor densidad |
| `TABLA_HEADER_ALTO` | Encabezado más visible | Más espacio para productos |

**Valores recomendados para maximizar productos**:
```php
$TABLA_FONT_SIZE = '8pt';     // Mínimo legible
$TABLA_PADDING = '0.5mm 1mm'; // Muy compacto
$TABLA_HEADER_ALTO = '7mm';   // Mínimo funcional
```

---

## 📊 Cálculo de Espacio Disponible

### Fórmula para calcular productos máximos teóricos:

```
Espacio disponible = (297mm - POS_TABLA_TOP - POS_FOOTER_BOTTOM)
                  = 297 - 95 - 30 = 172mm

Altura encabezado = TABLA_HEADER_ALTO = 8mm
Altura fila = (padding superior + padding inferior + texto)
            ≈ 2mm + fuente + 2mm ≈ 7-8mm

Altura totales = 10mm

Productos máximos = (172 - 8 - 10) / 8 ≈ 19 productos
```

**Nota**: Se recomienda usar 60-70% del máximo teórico para margen de seguridad.

---

## 🎯 Configuración Recomendada por Escenario

### Escenario 1: Productos con nombres cortos (máximo espacio)
```php
$PRODUCTOS_MAX_POR_HOJA = 16;
$TABLA_FONT_SIZE = '8pt';
$TABLA_PADDING = '0.5mm 1.5mm';
$TABLA_HEADER_ALTO = '7mm';
```

### Escenario 2: Productos con nombres largos (balance)
```php
$PRODUCTOS_MAX_POR_HOJA = 12;  // ← Configuración actual
$TABLA_FONT_SIZE = '9pt';
$TABLA_PADDING = '1mm 2mm';
$TABLA_HEADER_ALTO = '8mm';
```

### Escenario 3: Máxima legibilidad (espacioso)
```php
$PRODUCTOS_MAX_POR_HOJA = 10;
$TABLA_FONT_SIZE = '10pt';
$TABLA_PADDING = '2mm 3mm';
$TABLA_HEADER_ALTO = '10mm';
```

---

## 🧪 Procedimiento de Prueba

### Paso 1: Generar PDF de prueba
```bash
# Endpoint API
GET http://localhost/test/api/envios/{id}/pdf-preimpreso

# Ejemplo con envío #1
GET http://localhost/test/api/envios/1/pdf-preimpreso
```

### Paso 2: Verificar en PDF
- [ ] ¿Los datos del cliente están en la posición correcta?
- [ ] ¿La tabla comienza donde corresponde?
- [ ] ¿Los productos entran sin cortarse?
- [ ] ¿El footer gris está alineado con el papel preimpreso?
- [ ] ¿Los datos (remito/fecha/hoja) están visibles?
- [ ] Si hay múltiples páginas, ¿la numeración es correcta?

### Paso 3: Probar con diferentes volúmenes
- **Pocos productos** (3-5): Verificar que no quede demasiado vacío
- **Cantidad media** (10-15): Verificar que cabe en una hoja
- **Muchos productos** (20-30): Verificar paginación correcta

### Paso 4: Imprimir en papel preimpreso
1. Generar PDF
2. Imprimir en papel STARK IND
3. Verificar alineación física con regla
4. Ajustar variables según resultado

---

## 🔧 Ajuste Fino de Posiciones

### Si el texto del cliente está muy arriba:
```php
$POS_CLIENTE_TOP = 70;  // Era 60, aumentar 10mm
```

### Si la tabla choca con la banda cliente:
```php
$POS_TABLA_TOP = 105;  // Era 95, aumentar 10mm
```

### Si los productos se pasan del footer:
```php
$PRODUCTOS_MAX_POR_HOJA = 10;  // Reducir de 12 a 10
// O
$POS_FOOTER_BOTTOM = 40;  // Aumentar espacio inferior
```

### Si los datos remito están ocultos:
```php
$POS_DATOS_BOTTOM = 15;  // Subir 5mm más
```

---

## 📱 Acceso a la Función

### Desde la Grilla de Envíos:
- Botón **amarillo** con icono de impresora en cada fila

### Desde el Modal de Detalle:
- Botón **"Remito Preimpreso"** en el footer del modal  
  (junto a "Imprimir Remito" normal)

---

## 📝 Características del Remito

✅ **Paginación automática**: Divide productos en múltiples hojas si es necesario  
✅ **Numeración de páginas**: "Hoja 1 de 3", "Hoja 2 de 3", etc.  
✅ **Totales en última página**: Suma de cantidades, pesos brutos y pesos netos  
✅ **Información repetida**: Cliente y datos del remito en cada página  
✅ **Formato compacto**: Optimizado para máxima densidad de productos  
✅ **Contenedores**: Muestra el tipo de contenedor de cada producto  
✅ **Peso neto**: Descuenta automáticamente el peso del contenedor del peso bruto  

**Columnas de la tabla**:
- **Descripción**: Nombre del producto (35% ancho)
- **Contenedor**: Tipo de contenedor o "-" si no tiene (20% ancho)
- **Cantidad**: Unidades del producto (15% ancho)
- **P. Bruto**: Peso bruto en kg (producto + contenedor) (15% ancho)
- **P. Neto**: Peso neto en kg (producto sin contenedor) (15% ancho)  

---

## 🐛 Solución de Problemas

### Problema: Los productos se cortan / desbordan
**Solución**: Reducir `$PRODUCTOS_MAX_POR_HOJA` de 2 en 2 hasta que quede bien

### Problema: Queda mucho espacio vacío
**Solución**: Aumentar `$PRODUCTOS_MAX_POR_HOJA` gradualmente

### Problema: La banda cliente no se alinea con el papel preimpreso
**Solución**: Ajustar `$POS_CLIENTE_TOP` midiendo con regla física

### Problema: La tabla está muy alta o muy baja
**Solución**: Ajustar `$POS_TABLA_TOP` en incrementos de 5mm

### Problema: El footer gris no coincide con el pie del papel
**Solución**: Ajustar `$POS_FOOTER_BOTTOM` midiendo desde el borde inferior

### Problema: No se ve "Hoja X de Y"
**Solución**: Verificar que `$totalPaginas > 1`, revisar `$POS_DATOS_BOTTOM`

---

## 📍 Referencia Rápida de Ubicaciones

```
┌─────────────────────────────────────┐ ← 0mm (borde superior)
│      LOGO STARK IND (preimpreso)    │
│                                     │
├─────────────────────────────────────┤ ← 60mm (POS_CLIENTE_TOP)
│ ░ RAZON SOCIAL ░░░░░░░░░░░░░░░░░░░ │
│ ░ Domicilio - Localidad (CP) ░░░░░ │ 15mm alto
├─────────────────────────────────────┤ ← 75mm
│                                     │
│         (espacio)                   │
│                                     │
├─────────────────────────────────────┤ ← 95mm (POS_TABLA_TOP)
│Descripcion│Contenedor│Cant│Bruto│Neto│ ← Header 8mm
├───────────┼──────────┼────┼─────┼───┤
│Producto 1 │Balde 10L │ 10 │5.500│4.5│
│Producto 2 │Tarrina 5L│ 25 │13.75│11 │
│...        │...       │... │...  │...│
├───────────┴──────────┼────┼─────┼───┤
│         TOTALES:     │135 │68.25│55 │ ← Solo última página
└──────────────────────┴────┴─────┴───┘
│                                     │
├─────────────────────────────────────┤ ← 267mm (297-30 = POS_FOOTER_BOTTOM)
│ ░░░░░░░░░ (franja gris) ░░░░░░░░░░ │ 15mm alto
├─────────────────────────────────────┤ ← 282mm
│                                     │
│   Remito N°: 00000001 - Fecha:      │ ← 287mm (297-10)
│   01/01/2025 - Hoja 1 de 2          │
└─────────────────────────────────────┘ ← 297mm (borde inferior)
```

---

## 🔄 Historial de Cambios

| Fecha | Cambio | Valor Anterior | Valor Nuevo |
|-------|--------|----------------|-------------|
| 20/10/2025 | Implementación inicial con paginación | N/A | PRODUCTOS_MAX=12 |

---

## 👤 Contacto

Para consultas sobre ajustes específicos del papel STARK IND, consultar con el proveedor del papel preimpreso las medidas exactas de las zonas imprimibles.
