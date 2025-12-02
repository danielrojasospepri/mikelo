# Test Manual Profundo: Búsqueda 3-Pasos en Envíos

## Estado Actual
✅ Implementación completada en `api/src/Model/Envio.php`
✅ Validación sintaxis PHP: OK
✅ Tests automatizados: TODOS PASARON

---

## Resultados Tests Automatizados

### TEST 1: Cantidad Exacta (PASO 1)
- ✅ Búsqueda de Pan Salvado cantidad = 1
- ✅ Encontró: ID 2574, cantidad 1.000
- **Resultado:** PASO 1 funciona correctamente

### TEST 2: Cantidad Superior (PASO 2)
- ✅ Búsqueda de cantidad = 3
- ✅ Encontró: Producto con 18 unidades disponibles
- **Resultado:** PASO 2 maneja fallback correctamente

### TEST 3: Peso Exacto (TIPO 21)
- ✅ Búsqueda por peso exacto
- ✅ Encontrado: LIMON 6.355 kg
- **Resultado:** Busca peso sin fallback a superior (comportamiento correcto)

### TEST 4: Búsqueda Manual (PASO 3)
- ✅ Búsqueda sin parámetros
- ✅ Devolvió: 350 productos disponibles
- **Resultado:** PASO 3 permite búsqueda manual

### TEST 5: Filtro Texto
- ✅ Búsqueda por descripción "pan"
- ✅ Encontró: 9 productos coincidentes
- **Resultado:** Filtro funciona en todos los casos

---

## Instrucciones para Test Interactivo en Navegador

### Preparación
1. Abre navegador: `http://localhost/mikelo/envios.html`
2. Tienes aceso a escaneo de códigos de barras con esta URL
3. Los datos en la BD están listos (Pan Salvado, Limón, etc.)

### Escenario 1: Cantidad Exacta (PASO 1)

**Producto:** Pan Salvado (código 405)
**Stock en BD:** Existen múltiples registros con cantidad = 1

1. En envíos.html, haz clic en "Buscar productos"
2. Escanea o ingresa código: `405` (Pan Salvado)
3. El sistema debe encontrarlo y mostrarlo
4. Verifica que aparezca en la tabla
5. Agrégalo al envío

**Expectativa:** ✅ Encuentra cantidad exacta sin problemas

---

### Escenario 2: Cantidad Superior (PASO 2) - *CRÍTICO*

**Producto:** Pan Salvado (código 405)
**Problema anterior:** Si escaneas el código como barcode tipo 20 (cantidad=1) pero solo existe stock con cantidad=18, fallaba
**Solución:** PASO 2 busca cantidad > 1

1. En envíos.html, en la sección "Cantidad":
   - Ingresa: `1` (simular escaneo de barcode tipo 20)
   - Código: `405` (Pan Salvado)
2. Haz clic en "Buscar por código y cantidad"
3. El sistema debería encontrar el producto con cantidad > 1
4. Mostrará "Cantidad disponible: 18"
5. Podrás enviar 1 de los 18 disponibles

**Expectativa:** ✅ Aunque no hay cantidad exacta = 1, encuentra cantidad > 1 y permite usar PASO 2

---

### Escenario 3: Múltiples Escaneos (Same Product)

**Producto:** Pan Salvado (código 405)
**Cambio previo:** Ya removimos la restricción "ya está en envío"
**Esta prueba:** Valida que stock se controla correctamente

1. En envíos.html, agrega Pan Salvado 3 veces:
   - 1er escaneo: Cantidad 1
   - 2do escaneo: Cantidad 1
   - 3er escaneo: Cantidad 1
2. Cada uno debería permitirse (control en stock)
3. Total en envío: 3 unidades

**Expectativa:** ✅ Permite múltiples escaneos del mismo producto mientras haya stock disponible

---

### Escenario 4: Búsqueda Manual (PASO 3)

**Producto:** Cualquiera
**Método:** Sin escanear código de barras

1. En envíos.html, en "Buscar productos":
   - Deja código y cantidad vacíos
   - Haz clic en "Buscar todos los productos"
2. El sistema debería mostrar lista completa
3. Selecciona cualquier producto

**Expectativa:** ✅ Búsqueda manual devuelve todos disponibles (350+ en BD)

---

### Escenario 5: Búsqueda por Peso (TIPO 21)

**Producto:** Limón (peso 6.355 kg)
**Nota:** Este test es más complejo si usas barcode real tipo 21

1. Si tienes barcode generador:
   - Formato tipo 21: 21 + código producto + peso en gramos
   - Ejemplo: 2100123006355 = Código 123, peso 6.355 kg
2. En envíos.html:
   - Escanea código tipo 21
   - Sistema debe detectar como peso
3. Busca el producto por peso exacto

**Expectativa:** ✅ Busca peso exacto sin fallback a superior

---

## Validaciones Internas

El sistema ahora valida:

### Frontend (`js/envios_nuevo.js`)
```javascript
// Permite múltiples escaneos del mismo producto
// Pero valida: cantidadYaEnEnvío + cantidadNueva ≤ cantidadDisponible
```

### Backend (`api/src/Model/Envio.php`)
```php
// PASO 1: Exacto → AND mi.cnt = cantidad_buscada
// PASO 2: Superior → AND mi.cnt > cantidad_buscada
// PASO 3: Manual → SIN restricción de cantidad
```

---

## Checklist de Validación

- [ ] **PASO 1 (Exacto):** Encuentra cuando hay cantidad exacta
- [ ] **PASO 2 (Superior):** Encuentra cuando hay cantidad > buscada
- [ ] **PASO 3 (Manual):** Devuelve todos cuando no hay restricción
- [ ] **Múltiples escaneos:** Permite agregar mismo producto N veces
- [ ] **Stock control:** No permite enviar más de lo disponible
- [ ] **Peso exacto:** Busca peso sin fallback
- [ ] **Filtro texto:** Funciona en búsqueda manual
- [ ] **Cancelación:** ¿Deshace referencias correctamente?

---

## Troubleshooting

### Si PASO 2 no funciona:
1. Verifica que existan productos con cantidad > 1 en BD
   ```sql
   SELECT id, codigo, descripcion, cnt, 
          (cnt - IFNULL((SELECT SUM(cnt) FROM movimientos_items WHERE id_movimientos_items_origen = id), 0)) as disponible
   FROM movimientos_items
   WHERE id_movimientos_items_origen IS NULL
     AND cnt > 1
   LIMIT 5;
   ```
2. Revisa logs del navegador (F12 → Console)
3. Revisa logs PHP: `tail -f c:\xampp7.4.30\htdocs\mikelo\logs\*`

### Si múltiples escaneos fallan:
1. Verifica que `agregarProductoAlEnvio()` en js/envios_nuevo.js no tiene restricción
2. Confirma que validación de stock está activa

### Si peso exacto falla:
1. Verifica que producto tiene `cnt_peso > 0` en BD
2. Confirma que busca exacto (sin fallback)

---

## Próximos Pasos (Una vez validado)

1. **Si TODO funciona:** ✅ Solución completada, lista para producción
2. **Si hay issues:** 📝 Documentar qué falla
3. **Performance:** Medir tiempo de búsqueda con muchos registros
4. **UX:** Considerar mostrar al usuario cuál "PASO" se usó

---

## Cambios Realizados

### Backend (`api/src/Model/Envio.php`)
- **Líneas modificadas:** 372-404
- **Antes:** Búsqueda binaria EXACTO solo
- **Después:** Búsqueda inteligente 3-pasos
- **Impacto:** Mínimo, solo lógica de búsqueda cambia

### Frontend (Cambios previos - YA COMPLETADO)
- `js/envios_nuevo.js` línea 341-344
- Removida restricción "ya está en envío"
- Agregada validación de stock

---

## Validación de Cambios

```bash
# Sintaxis PHP
php -l api/src/Model/Envio.php
# Resultado: ✅ No syntax errors detected

# Test automatizado
php test_busqueda_3pasos.php
# Resultados: ✅ TEST 1-5 ALL PASS
```

