# 📋 Índice General de Tests - Sistema Mikelo

## 🎯 Suite Principal (RECOMENDADO)

### ✅ api/tests/TestSuiteStockDeposito.php
**Suite completa automatizada** - Ejecuta 7 tests en un solo comando

```bash
php api/tests/TestSuiteStockDeposito.php
```

**Tests incluidos:**
1. Conexión a Base de Datos
2. Exclusión de Productos BAJA
3. Suma de Cantidades (SUM vs COUNT)
4. Exportación PDF (respuesta binaria)
5. Exportación Excel (respuesta binaria)
6. Formato de Números
7. Dar de Baja (estructura BD correcta)

**Resultado esperado:** 7/7 tests pasando ✅

---

## 🔬 Tests Individuales (Legacy)

### Stock Depósito
Estos tests fueron creados durante el desarrollo y ahora están consolidados en la suite principal.

| Archivo | Propósito | Estado |
|---------|-----------|---------|
| `test_baja_productos.php` | Verificar exclusión de productos dados de baja | ✅ Consolidado en Suite |
| `test_suma_cantidades.php` | Verificar SUM vs COUNT | ✅ Consolidado en Suite |
| `test_excel_export.php` | Verificar exportación Excel binaria | ✅ Consolidado en Suite |
| `test_stock_pdf.php` | Test de generación PDF stock | ✅ Consolidado en Suite |
| `test_stock_deposito_pdf.php` | Test completo PDF stock depósito | ✅ Consolidado en Suite |

### Envíos
Tests relacionados con el módulo de envíos.

| Archivo | Propósito | Estado |
|---------|-----------|---------|
| `test_envios.php` | Test general de envíos | ✅ Funcional |
| `test_envios_headers.php` | Verificar headers de exportación | ✅ Funcional |
| `test_envios_pdf.php` | Test de PDF de envíos | ✅ Funcional |
| `test_envio_detalle.php` | Test de detalle de envío | ✅ Funcional |

### Sistema y Configuración
Tests de infraestructura y configuración.

| Archivo | Propósito | Estado |
|---------|-----------|---------|
| `api/test_db.php` | Test de conexión básica a BD | ✅ Funcional |
| `test_timezone_mysql.php` | Verificar sincronización de timezone | ✅ Funcional |
| `test_insert_timestamp.php` | Test de timestamps en INSERT | ✅ Funcional |

### mPDF y Generación de Documentos
Tests relacionados con la generación de PDFs.

| Archivo | Propósito | Estado |
|---------|-----------|---------|
| `test_mpdf_minimal.php` | Test de configuración mínima mPDF | ✅ Funcional |
| `test_imagen.php` | Test de imágenes en PDFs | ✅ Funcional |

### API
Tests específicos de endpoints API.

| Archivo | Propósito | Estado |
|---------|-----------|---------|
| `api/test_detalle_api.php` | Test de endpoint de detalle | ✅ Funcional |
| `api/test_barcode.php` | Test de lectura de códigos de barras | ✅ Funcional |

---

## 📊 Uso Recomendado

### Para Desarrollo
Usar la **Suite Principal** para validación completa:
```bash
php api/tests/TestSuiteStockDeposito.php
```

### Para Debugging Específico
Usar tests individuales cuando necesites aislar un problema:
```bash
php test_excel_export.php
php test_timezone_mysql.php
```

### Pre-Deployment
**SIEMPRE** ejecutar la suite completa antes de subir a producción:
```bash
php api/tests/TestSuiteStockDeposito.php
```
Debe mostrar: `✅ TODOS LOS TESTS PASARON`

---

## 🗂️ Estructura de Archivos

```
mikelo/
├── api/
│   ├── test_db.php              # Test conexión BD
│   ├── test_detalle_api.php     # Test endpoint detalle
│   ├── test_barcode.php         # Test códigos de barras
│   └── tests/
│       ├── TestSuiteStockDeposito.php  # ⭐ SUITE PRINCIPAL
│       └── README.md            # Documentación de tests
├── test_baja_productos.php      # Test exclusión BAJA
├── test_suma_cantidades.php     # Test SUM cantidades
├── test_excel_export.php        # Test export Excel
├── test_stock_pdf.php           # Test PDF stock
├── test_stock_deposito_pdf.php  # Test PDF stock depósito
├── test_envios.php              # Test envíos
├── test_envios_headers.php      # Test headers envíos
├── test_envios_pdf.php          # Test PDF envíos
├── test_envio_detalle.php       # Test detalle envío
├── test_timezone_mysql.php      # Test timezone
├── test_insert_timestamp.php    # Test timestamps
├── test_mpdf_minimal.php        # Test mPDF
├── test_imagen.php              # Test imágenes
└── INDICE_TESTS.md              # 📄 Este archivo
```

---

## 🚀 Quick Start

1. **Verificar que todo funciona:**
   ```bash
   php api/tests/TestSuiteStockDeposito.php
   ```

2. **Si hay fallos, revisar tests individuales:**
   ```bash
   php test_baja_productos.php
   php test_suma_cantidades.php
   ```

3. **Verificar BD:**
   ```bash
   php api/test_db.php
   ```

4. **Verificar timezone:**
   ```bash
   php test_timezone_mysql.php
   ```

---

## 📝 Notas

- La **Suite Principal** es el método preferido para testing
- Los tests individuales se mantienen para debugging específico
- Todos los tests deben pasar antes de deployment
- Los tests legacy permanecen para compatibilidad

---

**Última actualización:** 15/10/2025  
**Versión:** 1.0  
**Autor:** Sistema Mikelo - GitHub Copilot
