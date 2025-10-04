# Módulo Stock Depósito - Documentación Completa

## Descripción General

El módulo **Stock Depósito** permite visualizar y gestionar el inventario de productos (bandejas de helado) disponibles en el depósito central. Proporciona una vista agrupada por producto y detalle individual de cada bandeja con sus fechas de fabricación y contenedores.

## Características Principales

### 1. Vista Agrupada de Stock
- **Productos Distintos**: Muestra cuántos productos diferentes hay en stock
- **Unidades Totales**: Suma de todas las bandejas disponibles
- **Peso Bruto/Neto**: Totales considerando deducción de contenedores
- **Contenedores**: Lista de tipos de contenedores utilizados
- **Fecha Más Antigua**: Para control de rotación de inventario

### 2. Detalle de Bandejas
- **Vista Individual**: Cada bandeja con su información específica
- **Fecha de Fabricación**: Corresponde al campo `fechaAlta` del movimiento
- **Contenedor y Peso**: Información completa de contenedor y deducción
- **Estado**: Estado actual de cada bandeja
- **Selección Múltiple**: Para operaciones en lote

### 3. Operaciones Disponibles

#### Cambio de Contenedor
- **Funcionalidad**: Permite cambiar el tipo de contenedor de bandejas seleccionadas
- **Auditoría**: Se registra el motivo del cambio y usuario
- **Recálculo**: Se actualiza automáticamente el peso neto

#### Dar de Baja
- **Funcionalidad**: Marca productos como dados de baja (vencidos, dañados, etc.)
- **Estado**: Cambia el estado a "DADO_DE_BAJA"
- **Trazabilidad**: Se registra motivo y usuario para auditoría

### 4. Filtros y Búsqueda
- **Por Producto**: Código o descripción
- **Por Contenedor**: Tipo específico de contenedor
- **Por Fechas**: Rango de fechas de fabricación

### 5. Exportación
- **PDF**: Reporte profesional con resumen y detalle
- **Excel**: Hoja de cálculo con datos estructurados

## Estructura Técnica

### Backend (PHP)

#### Modelo: `StockDeposito.php`
- `obtenerStockAgrupado()`: Vista agrupada por producto
- `obtenerDetalleBandejas()`: Detalle individual por producto
- `cambiarContenedor()`: Operación de cambio de contenedor
- `darDeBaja()`: Operación de baja de productos
- `exportarPDF()/exportarExcel()`: Generación de reportes

#### Controlador: `StockDepositoController.php`
- Manejo de requests HTTP
- Validación de datos
- Respuestas JSON estándar

#### Rutas API:
```
GET    /api/stock-deposito                    # Stock agrupado
GET    /api/stock-deposito/{id}/bandejas      # Detalle de bandejas
GET    /api/stock-deposito/pdf               # Exportar PDF
GET    /api/stock-deposito/excel             # Exportar Excel
POST   /api/stock-deposito/cambiar-contenedor # Cambiar contenedor
POST   /api/stock-deposito/dar-baja           # Dar de baja
```

### Frontend (HTML/JavaScript)

#### Página: `stock_deposito.html`
- Interfaz AdminLTE responsiva
- Filtros avanzados
- Tarjetas de resumen (KPI)
- Grilla de stock agrupado
- Modales para detalle y operaciones

#### Script: `stock_deposito.js`
- Gestión de filtros y búsqueda
- Carga dinámica de datos
- Operaciones CRUD vía AJAX
- Manejo de selecciones múltiples
- Integración con SweetAlert2

### Base de Datos

#### Tablas Involucradas:
- `movimientos_items`: Items de inventario principales
- `productos`: Catálogo de productos
- `contenedores`: Tipos de contenedores y pesos
- `estados_items_movimientos`: Estados de cada item
- `movimientos_cambios`: **Nueva tabla de auditoría**

#### Auditoría
```sql
CREATE TABLE movimientos_cambios (
    id INT AUTO_INCREMENT PRIMARY KEY,
    id_movimientos_items INT NOT NULL,
    tipo_cambio ENUM('CONTENEDOR', 'BAJA', 'ESTADO') NOT NULL,
    valor_anterior VARCHAR(255),
    valor_nuevo VARCHAR(255),
    motivo TEXT,
    fecha_cambio DATETIME DEFAULT CURRENT_TIMESTAMP,
    usuario VARCHAR(100),
    FOREIGN KEY (id_movimientos_items) REFERENCES movimientos_items(id)
);
```

## Lógica de Negocio

### Criterios de Stock Disponible
Un producto está disponible en stock si:
1. `id_movimientos_items_origen IS NULL` (es un item original, no un envío)
2. No existe ningún `movimientos_items` que lo referencie como origen
3. No tiene estado "DADO_DE_BAJA"

### Cálculo de Peso Neto
```sql
CASE 
    WHEN c.peso IS NOT NULL THEN (mi.cnt_peso - c.peso)
    ELSE mi.cnt_peso
END as peso_neto
```
- Si hay contenedor: Peso Neto = Peso Bruto - Peso Contenedor
- Sin contenedor: Peso Neto = Peso Bruto

### Agrupación por Producto
- Se agrupan todas las bandejas del mismo producto
- Se suman cantidades y pesos
- Se listan todos los contenedores utilizados
- Se muestra la fecha de fabricación más antigua

## Casos de Uso

### 1. Control de Inventario
**Usuario**: Supervisor de depósito
**Objetivo**: Revisar stock actual y rotación
**Flujo**:
1. Accede al módulo Stock Depósito
2. Revisa tarjetas de resumen
3. Filtra por fechas para ver productos antiguos
4. Exporta reporte para análisis

### 2. Gestión de Contenedores
**Usuario**: Operario de depósito
**Objetivo**: Cambiar contenedores dañados
**Flujo**:
1. Busca producto específico
2. Ve detalle de bandejas
3. Selecciona bandejas con contenedor dañado
4. Cambia a nuevo contenedor con motivo

### 3. Control de Calidad
**Usuario**: Inspector de calidad
**Objetivo**: Dar de baja productos en mal estado
**Flujo**:
1. Identifica productos con problemas
2. Ve detalle de bandejas específicas
3. Selecciona bandejas afectadas
4. Da de baja con motivo detallado

## Pruebas y Validación

### Archivo de Pruebas: `test_stock_deposito.html`
Permite probar todas las APIs independientemente:
- Stock agrupado
- Detalle de bandejas
- Exportadores PDF/Excel
- Operaciones de cambio y baja

### URLs de Prueba:
- http://localhost/mikelo/stock_deposito.html
- http://localhost/mikelo/test_stock_deposito.html

## Integración con Otros Módulos

### Con Envíos
- Los productos disponibles en stock son origen para envíos
- Cuando se crea un envío, se actualiza el stock automáticamente

### Con Alta Depósito
- Los productos ingresados aparecen inmediatamente en stock
- Se respeta la asignación de contenedores realizada

### Con Movimientos
- Todos los cambios se reflejan en el historial de movimientos
- La auditoría permite rastrear modificaciones

## Configuración y Deployment

### Requisitos:
- PHP 7.4+
- MySQL 5.7+
- Composer (mpdf, phpspreadsheet)
- Permisos de escritura en `/temp/`

### Instalación:
1. Archivos copiados al directorio del proyecto
2. Rutas agregadas al `api/index.php`
3. Enlaces agregados a menús de navegación
4. Tabla de auditoría se crea automáticamente

## Futuras Mejoras

### Fase 2:
- **Alertas Automáticas**: Notificaciones por productos próximos a vencer
- **Dashboard Avanzado**: Gráficos de rotación y tendencias
- **Códigos de Barras**: Integración con scanning para operaciones
- **Workflows**: Procesos automáticos de revisión y control

### Fase 3:
- **Mobile App**: Aplicación móvil para operarios
- **IoT Integration**: Sensores de temperatura y humedad
- **Machine Learning**: Predicción de demanda y optimización

## Documentación de APIs

Ver archivo separado: `API_STOCK_DEPOSITO.md` para documentación técnica detallada de endpoints, parámetros y respuestas.

---

**Fecha de Creación**: 3 de octubre de 2025  
**Versión**: 1.0  
**Estado**: Funcional y listo para producción