# REPORTE DE AUDITORÍA: CIRCUITO DE CREAR ENVÍO

**Fecha de Auditoría:** 2025-01-18  
**Módulo Auditado:** Crear Envío (Post-Selección de Productos)  
**Versión:** Post Filtro Familia

---

## ✅ PUNTOS POSITIVOS (FUNCIONANDO CORRECTAMENTE)

### 1. **Validación Básica en Frontend**
- ✅ Valida que se seleccione un `destino`
- ✅ Valida que se agregue al menos 1 producto
- ✅ Mapeo correcto de campos: `id_productos`, `cantidad`, `peso`
- ✅ Manejo de `id_movimientos_items_origen` solo cuando es edición

### 2. **Validación en Controller**
- ✅ Verifica que `destino` y `productos` existan en el JSON
- ✅ Rechaza envíos vacíos (sin productos)
- ✅ Manejo de excepciones con respuesta JSON clara

### 3. **Transacción en Base de Datos**
- ✅ Inicia transacción con `beginTransaction()`
- ✅ Rollback automático en caso de error
- ✅ Crear movimiento, items y estados en secuencia correcta

### 4. **Validación de Stock**
- ✅ Si tiene `id_movimientos_items_origen`:
  - ✅ Verifica cantidad disponible
  - ✅ Toma contenedor del item origen
  - ✅ Rechaza si hay inconsistencia

### 5. **Estado Tracking**
- ✅ Crea registro inicial en `estados_items_movimientos` (estado NUEVO = 1)
- ✅ Permite posterior confirmación a estado ENVIADO

### 6. **Manejo de Errores**
- ✅ Excepciones en modelo -> controller -> frontend (SweetAlert)
- ✅ Mensajes de error descriptivos

---

## ⚠️ HALLAZGOS: CASOS NO CUBIERTOS

### **CRÍTICO - FALTA DE VALIDACIÓN**

#### 1. **No valida que `destino` sea una ubicación válida**
- **Ubicación:** `EnvioController.php` línea 17, `Envio.php` línea 66
- **Problema:** Acepta cualquier número en `destino`, podría no existir en tabla `ubicaciones`
- **Riesgo:** INSERT fallará con error genérico de BD
- **Escenario:**
  ```javascript
  destino: 99999  // ← Ubicación inexistente
  ```
- **Impacto:** Transacción fallará pero usuario solo ve "Error del servidor"

#### 2. **No valida que `destino ≠ origen (1)`**
- **Ubicación:** No existe validación
- **Problema:** Sistema no verifica que no estamos creando envío a la misma ubicación origen
- **Riesgo:** Lógica de negocio violada (un envío debe tener destino diferente)
- **Escenario:**
  ```javascript
  destino: 1  // ← Central, mismo que origen
  ```

#### 3. **No valida que `id_productos` exista**
- **Ubicación:** `Envio.php` línea 86
- **Problema:** INSERT directo sin verificación previa
- **Riesgo:** Falla silenciosa o error de FK si existe la restricción
- **Escenario:**
  ```javascript
  productos: [{ id_productos: 99999, cantidad: 10, peso: 5 }]
  ```

#### 4. **No valida cantidad mínima (> 0)**
- **Ubicación:** `Envio.php` línea 99
- **Problema:** Acepta cantidades ≤ 0 o NULL
- **Riesgo:** Item creado con cantidad 0 (item fantasma)
- **Escenario:**
  ```javascript
  cantidad: 0  // ← O cantidad: null
  ```

#### 5. **No valida peso (debe ser > 0)**
- **Ubicación:** `Envio.php` línea 100
- **Problema:** Acepta pesos negativos o 0
- **Riesgo:** Inconsistencia en reportes de peso total
- **Escenario:**
  ```javascript
  peso: -5  // ← O peso: null
  ```

#### 6. **Inconsistencia en mapeo de campos**
- **Ubicación:** `js/envios_nuevo.js` línea 492-498
- **Problema:** Código acepta variaciones de nombres (`id_producto` vs `id_productos`, `cnt` vs `cantidad`)
```javascript
id_productos: p.id_productos || p.id_producto,  // ← Dual mapping
cantidad: p.cantidad || p.cnt,                   // ← Dual mapping
peso: p.peso !== undefined ? p.peso : p.cnt_peso  // ← Dual mapping
```
- **Riesgo:** Datos inconsistentes dependiendo de fuente
- **Impacto:** Debug difícil si datos vienen de diferentes orígenes

---

### **IMPORTANTE - FALTA DE CONTEXTO**

#### 7. **Usuario no registrado adecuadamente**
- **Ubicación:** `Envio.php` línea 72
- **Código:**
  ```php
  $_SESSION['usuario'] ?? 'sistema'
  ```
- **Problema:** Si sesión no está configurada, siempre registra como "sistema"
- **Riesgo:** No hay trazabilidad de quién creó el envío
- **Nota:** Marca de Faltantes de Autenticación (fase 2 según docs)

#### 8. **No registra IP ni fecha con precisión**
- **Ubicación:** `Envio.php` línea 67
- **Problema:** Usa `NOW()` que depende del servidor MySQL (timezone debe ser correcto)
- **Información de Mitigación:** Ya configurado Argentina/Buenos_Aires en `api/index.php`

---

### **MODERADO - CASOS EDGE**

#### 9. **¿Qué pasa si `id_movimientos_items_origen` es inválido?**
- **Ubicación:** `Envio.php` línea 81-88
- **Código:**
  ```php
  if (!$disponibilidad) {
      throw new \Exception("Producto origen no encontrado: {$producto['id_movimientos_items_origen']}");
  }
  ```
- **Estado:** ✅ Bien manejado, lanza excepción clara
- **Pero:** El JavaScript podría enviar items con IDs inválidos sin que el usuario lo sepa

#### 10. **¿Qué pasa si items ya fueron referenciados?**
- **Ubicación:** `Envio.php` línea 84
- **Problema:** La query de disponibilidad `SELECT cnt_disponible` puede dar 0 si item ya fue completamente referenciado
- **Escenario:** Usuario ve cantidad disponible = 10, pero mientras escribía, alguien más lo reservó todo
- **Estado:** ⚠️ Race condition posible (poco probable pero posible en producción concurrente)
- **Mitigación:** Usar row locking (FOR UPDATE) en transacción
- **Implementación:** Cambiar query a:
  ```sql
  SELECT ... FROM movimientos_items mi WHERE mi.id = ? FOR UPDATE
  ```

---

## 🔧 RECOMENDACIONES DE MEJORA

### ALTA PRIORIDAD (Implementar antes de versión intermedia)

#### **A. Agregar validación de ubicación**
```php
// En EnvioController.crear() o Envio.crear()
$stmt = $this->db->prepare("SELECT id FROM ubicaciones WHERE id = ?");
$stmt->execute([$destino]);
if (!$stmt->fetch()) {
    throw new \Exception("Ubicación de destino no encontrada");
}
```

#### **B. Agregar validación de producto**
```php
// Para cada producto en el foreach
$stmt = $this->db->prepare("SELECT id FROM productos WHERE id = ?");
$stmt->execute([$producto['id_productos']]);
if (!$stmt->fetch()) {
    throw new \Exception("Producto no encontrado: {$producto['id_productos']}");
}
```

#### **C. Validar cantidades y pesos**
```php
if (!isset($producto['cantidad']) || $producto['cantidad'] <= 0) {
    throw new \Exception("Cantidad debe ser mayor a 0");
}
if (!isset($producto['peso']) || $producto['peso'] < 0) {
    throw new \Exception("Peso no puede ser negativo");
}
```

#### **D. Prevenir row locking race condition**
```php
$stmt = $this->db->prepare("
    SELECT id, cnt FROM movimientos_items 
    WHERE id = ?
    FOR UPDATE  -- ← Bloquea la fila durante la transacción
");
$stmt->execute([$producto['id_movimientos_items_origen']]);
```

### MEDIA PRIORIDAD (Implementar en siguiente iteración)

#### **E. Validar destino ≠ origen**
```php
if ($destino == 1) {
    throw new \Exception("El destino no puede ser la ubicación de origen");
}
```

#### **F. Estandarizar nombres de campos**
En `js/envios_nuevo.js`, normalizar antes de enviar:
```javascript
const datosEnvio = {
    destino: destinoId,
    productos: productosEnEnvio.map(p => ({
        id_productos: p.id_productos,      // ← Solo este nombre
        cantidad: p.cantidad,                // ← Solo este nombre
        peso: p.peso,                        // ← Solo este nombre
        id_movimientos_items_origen: p.id_movimientos_items_origen || null
    }))
};
```

#### **G. Logging de errores**
En `Envio.crear()`, registrar excepciones en archivo log:
```php
catch (\Exception $e) {
    error_log("Error en crear envío: " . $e->getMessage());
    $this->db->rollBack();
    throw $e;
}
```

---

## 📋 CHECKLIST ANTES DE VERSIÓN INTERMEDIA

- [ ] Implementar validación de ubicación (ALTA)
- [ ] Implementar validación de productos (ALTA)
- [ ] Implementar validación de cantidades (ALTA)
- [ ] Agregar FOR UPDATE a queries de stock (ALTA)
- [ ] Agregar validación destino ≠ origen (MEDIA)
- [ ] Normalizar nombres de campos (MEDIA)
- [ ] Agregar logging de errores (MEDIA)
- [ ] Pruebas de casos edge (TODAS PRIORIDADES)

---

## 🧪 CASOS DE PRUEBA SUGERIDOS

### Caso 1: Crear envío con datos válidos
```
✓ DEBE PASAR
Destino: 2 (Sucursal A)
Productos: 1 HELADO, cantidad 10, peso 5kg
Resultado: Envío creado con ID X, estado NUEVO
```

### Caso 2: Intentar crear con destino inválido
```
✗ DEBE FALLAR
Destino: 99999
Productos: 1 HELADO, cantidad 10, peso 5kg
Resultado: "Ubicación de destino no encontrada"
```

### Caso 3: Intentar crear con cantidad 0
```
✗ DEBE FALLAR
Destino: 2
Productos: 1 HELADO, cantidad 0, peso 5kg
Resultado: "Cantidad debe ser mayor a 0"
```

### Caso 4: Intentar crear con peso negativo
```
✗ DEBE FALLAR
Destino: 2
Productos: 1 HELADO, cantidad 10, peso -5kg
Resultado: "Peso no puede ser negativo"
```

### Caso 5: Intentar crear envío vacío
```
✗ DEBE FALLAR (YA FUNCIONA)
Destino: 2
Productos: []
Resultado: "Error: Debe agregar al menos un producto"
```

### Caso 6: Crear envío sin destino
```
✗ DEBE FALLAR (YA FUNCIONA)
Destino: null
Productos: 1 HELADO, cantidad 10, peso 5kg
Resultado: "Error: Debe seleccionar un destino"
```

---

## 📊 RESUMEN GENERAL

| Aspecto | Estado | Prioridad |
|---------|--------|-----------|
| Validación básica | ✅ OK | - |
| Transacciones | ✅ OK | - |
| Validación ubicación | ❌ FALTA | ALTA |
| Validación productos | ❌ FALTA | ALTA |
| Validación cantidades | ❌ FALTA | ALTA |
| Validación pesos | ❌ FALTA | ALTA |
| Row locking | ⚠️ RIESGO | ALTA |
| Autenticación | ⚠️ PENDIENTE | (Fase 2) |
| Logging | ⚠️ MINIMAL | MEDIA |
| Normalización campos | ⚠️ INCONSISTE | MEDIA |

---

## 🚀 SIGUIENTE PASO

¿Procedo a implementar las validaciones de ALTA PRIORIDAD antes de publicar la versión intermedia?

