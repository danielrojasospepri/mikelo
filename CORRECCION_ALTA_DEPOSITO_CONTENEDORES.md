# Corrección: Alta en Depósito - Contenedores y Cálculo de Peso

## Problemas Identificados

### 1. El contenedor no se registraba correctamente
**Causa**: El backend no estaba procesando ni guardando el campo `id_contenedor`

### 2. Lógica incorrecta en el resumen de peso
**Causa**: Se sumaba el peso del contenedor en lugar de restarlo para mostrar el peso neto

## Soluciones Implementadas

### 1. Backend - Controlador (`MovimientoController.php`)
**ANTES:**
```php
$itemId = $this->movimiento->agregarItem(
    $args['id'],
    $data['producto_id'],
    $data['cantidad'],
    $data['cantidad_peso'] ?? 0,
    $data['movimiento_item_origen_id'] ?? null
);
```

**DESPUÉS:**
```php
$itemId = $this->movimiento->agregarItem(
    $args['id'],
    $data['producto_id'],
    $data['cantidad'],
    $data['cantidad_peso'] ?? 0,
    $data['movimiento_item_origen_id'] ?? null,
    $data['id_contenedor'] ?? null
);
```

### 2. Backend - Modelo (`Movimiento.php`)
**ANTES:**
```sql
INSERT INTO movimientos_items (id_movimientos, id_productos, cnt, cnt_peso, id_movimientos_items_origen) 
VALUES (:movimiento, :producto, :cnt, :peso, :origen)
```

**DESPUÉS:**
```sql
INSERT INTO movimientos_items (id_movimientos, id_productos, cnt, cnt_peso, id_movimientos_items_origen, id_contenedor) 
VALUES (:movimiento, :producto, :cnt, :peso, :origen, :contenedor)
```

### 3. Frontend - Cálculo de Peso (`alta_deposito.js`)
**ANTES:**
```javascript
// Sumaba incorrectamente: peso bruto + peso contenedor
const pesoTotal = pesoBruto + pesoContenedor;
document.getElementById('pesoTotalDisplay').textContent = `Peso Total: ${pesoTotal.toFixed(3)} kg`;
```

**DESPUÉS:**
```javascript
// Resta correctamente: peso bruto - peso contenedor = peso neto
const pesoNeto = pesoBruto - pesoContenedor;
document.getElementById('pesoTotalDisplay').textContent = `Peso Neto: ${pesoNeto.toFixed(3)} kg (Bruto: ${pesoBruto.toFixed(3)} kg - Contenedor: ${pesoContenedor.toFixed(3)} kg)`;
```

### 4. Frontend - Mensaje de Confirmación
**Mejorado** para mostrar la información del contenedor seleccionado en el mensaje de éxito:
```javascript
${contenedorInfo}  // Muestra el contenedor seleccionado o "Sin contenedor"
```

## Lógica Corregida

### Entendimiento del Peso:
- **Peso Especificado (Bruto)**: Incluye el peso del producto + contenedor
- **Peso Neto**: Peso del producto sin contenedor
- **Fórmula**: `Peso Neto = Peso Bruto - Peso Contenedor`

### Ejemplo:
- Usuario especifica: **5.440 kg** (peso bruto total)
- Contenedor "Acrílico": **0.440 kg**
- **Peso Neto resultante**: 5.440 - 0.440 = **5.000 kg** (producto puro)

## Validación

### Casos de Prueba:
1. **Con Contenedor**:
   - Peso especificado: 5.440 kg
   - Contenedor Acrílico: 0.440 kg
   - Resultado: "Peso Neto: 5.000 kg (Bruto: 5.440 kg - Contenedor: 0.440 kg)"

2. **Sin Contenedor**:
   - Peso especificado: 5.000 kg
   - Resultado: "Peso Neto: 5.000 kg (Sin contenedor)"

## Archivos Modificados
- ✅ `api/src/Controller/MovimientoController.php` - Pasa id_contenedor al modelo
- ✅ `api/src/Model/Movimiento.php` - Guarda id_contenedor en BD
- ✅ `js/alta_deposito.js` - Corrige cálculo de peso y mensaje de confirmación

## Resultado Final
- ✅ El contenedor se registra correctamente en la base de datos
- ✅ El resumen muestra el peso neto correcto (bruto - contenedor)
- ✅ El mensaje de confirmación incluye información del contenedor
- ✅ La lógica de negocio es consistente con el resto del sistema

## Fecha de Corrección
3 de octubre de 2025