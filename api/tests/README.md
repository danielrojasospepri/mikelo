# 🧪 Suite de Tests - Stock Depósito

## Descripción

Suite completa de tests automatizados para verificar todas las correcciones implementadas en el módulo de Stock Depósito del sistema Mikelo.

## Archivos

### TestSuiteStockDeposito.php
**Suite principal** que ejecuta 7 tests automatizados:

1. ✅ **Conexión a Base de Datos** - Verifica conectividad y acceso
2. ✅ **Exclusión de Productos BAJA** - Confirma que productos dados de baja no aparecen en stock
3. ✅ **Suma de Cantidades** - Valida uso de SUM() en lugar de COUNT()
4. ✅ **Exportación PDF** - Verifica respuesta binaria con headers correctos
5. ✅ **Exportación Excel** - Verifica respuesta binaria XLSX
6. ✅ **Formato de Números** - Confirma eliminación de decimales innecesarios
7. ✅ **Dar de Baja** - Valida estructura de tablas sin columnas inexistentes

### Tests Individuales (legacy)
Estos tests fueron creados durante el desarrollo y ahora están consolidados en la suite:

- `test_db.php` - Test básico de conexión
- `test_baja_productos.php` - Test de exclusión BAJA
- `test_suma_cantidades.php` - Test de agregación SUM
- `test_pdf_export.php` - Test de PDF binario
- `test_excel_export.php` - Test de Excel binario

## Uso

### Ejecutar Suite Completa
```bash
php api/tests/TestSuiteStockDeposito.php
```

### Resultado Esperado
```
╔══════════════════════════════════════════════════════════════╗
║        TEST SUITE - STOCK DEPÓSITO                           ║
║        Fecha: 15/10/2025 20:24:52                            ║
╚══════════════════════════════════════════════════════════════╝

...7 tests ejecutados...

╔══════════════════════════════════════════════════════════════╗
║                    RESUMEN FINAL                             ║
╠══════════════════════════════════════════════════════════════╣
║  Total Tests:    7                                           ║
║  ✅ Pasados:     7                                           ║
║  ❌ Fallados:    0                                           ║
╠══════════════════════════════════════════════════════════════╣
║  RESULTADO:      ✅ TODOS LOS TESTS PASARON                  ║
╚══════════════════════════════════════════════════════════════╝
```

## Interpretación de Resultados

### ✅ Tests Pasados
- **Exit Code**: 0
- **Resultado**: Todas las correcciones funcionan correctamente
- **Acción**: Listo para deployment

### ❌ Tests Fallados
- **Exit Code**: 1
- **Resultado**: Hay problemas que requieren atención
- **Acción**: Revisar el mensaje de error específico

## Detalles de Verificación

### Test 2: Exclusión BAJA
```
Stock activo: 3 productos | Productos dados de baja: 2 (excluidos)
```
Confirma que 2 productos están dados de baja pero NO aparecen en el stock disponible.

### Test 3: Suma Cantidades
```
Registros=6 | Suma cantidades=13 ✓ Usando SUM correctamente
```
Valida que se suma la cantidad real (13 unidades) y no se cuenta registros (6).

### Test 4: PDF Export
```
✓ HTTP 200 OK | ✓ Content-Type: PDF | ✓ Content-Disposition presente | ✓ Formato PDF válido
```
Todos los headers y formato binario correctos.

### Test 5: Excel Export
```
✓ HTTP 200 OK | ✓ Content-Type: XLSX | ✓ Content-Disposition presente | ✓ Formato ZIP/XLSX válido
```
Archivo XLSX válido con headers correctos (firma 'PK').

### Test 6: Formato Números
```
1 → 1 ✓ | 1.25 → 1.25 ✓ | 10.5 → 10.5 ✓ | 0.75 → 0.75 ✓ | 100 → 100 ✓
```
Elimina decimales innecesarios (1.000 → 1) pero mantiene significativos (1.25).

### Test 7: Dar de Baja
```
✓ estados sin 'descripcion' | ✓ estados_items_movimientos sin 'observaciones' | ✓ Estado 'BAJA' existe
```
Estructura de BD correcta sin columnas inexistentes.

## Integración CI/CD

La suite puede integrarse en pipelines de CI/CD:

```yaml
# Ejemplo GitHub Actions
- name: Run Test Suite
  run: php api/tests/TestSuiteStockDeposito.php
  
# Ejemplo GitLab CI
test:
  script:
    - php api/tests/TestSuiteStockDeposito.php
```

El exit code indica éxito (0) o fallo (1).

## Mantenimiento

### Agregar Nuevos Tests

1. Crear método `private function testX_NombreTest()` en la clase
2. Usar `$this->iniciarTest()`, `$this->pasarTest()`, `$this->fallarTest()`
3. Llamar el nuevo test en `ejecutarTodos()`

### Actualizar Tests Existentes

Modificar directamente los métodos de test manteniendo la estructura:
- try/catch para manejo de errores
- Mensajes informativos descriptivos
- Verificaciones múltiples cuando sea posible

## Historial

- **15/10/2025**: Creación inicial con 7 tests
  - Consolidación de tests individuales
  - 100% de tests pasando
  - Listo para producción

## Autor

Sistema Mikelo - GitHub Copilot  
Versión: 1.0
