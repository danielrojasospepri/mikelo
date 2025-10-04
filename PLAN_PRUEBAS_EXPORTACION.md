# Plan de Pruebas - Sistema de Exportación Mikelo

## 📋 Resumen de Cambios Implementados

### ✅ Problemas Resueltos
1. **Librerías faltantes**: Instaladas `mpdf/mpdf` y `phpoffice/phpspreadsheet`
2. **Formato básico**: Mejorado a formato profesional de remito
3. **Estructura de datos**: Optimizada para mejor presentación
4. **Validación**: Agregadas verificaciones de datos existentes

### ✅ Nuevos Formatos Implementados

#### PDF de Remito Individual
- **Encabezado profesional** con logo de empresa
- **Número de remito** con formato (ej: 00000011)
- **Información origen/destino** en cajas separadas
- **Tabla de productos** con todos los detalles necesarios
- **Firmas de entrega/recepción**
- **Formato limpio** siguiendo estándares de remitos comerciales

#### Excel de Remito Individual
- **Hoja profesional** con encabezados corporativos
- **Datos organizados** en filas y columnas claras
- **Colores y bordes** para mejor legibilidad
- **Totales calculados** automáticamente

#### PDF de Lista de Envíos
- **Reporte ejecutivo** con resumen general
- **Tabla detallada** con todos los envíos
- **Totales consolidados**
- **Estados coloreados** para fácil identificación

#### Excel de Lista de Envíos
- **Formato de reporte** con múltiples columnas
- **Colores por estado** (Nuevo: azul, Enviado: verde, Cancelado: rojo)
- **Totales automáticos**
- **Filtros aplicables**

---

## 🧪 Plan de Pruebas Detallado

### Fase 1: Pruebas de Infraestructura

#### 1.1 Verificación de Dependencias
```bash
# Comprobar que las librerías están instaladas
cd c:\xampp7.4.30\htdocs\mikelo\api
composer show | grep mpdf
composer show | grep phpspreadsheet
```

**Resultado Esperado**: Ambas librerías deben aparecer listadas

#### 1.2 Verificación de Base de Datos
```bash
# Ejecutar script de verificación
php test_db.php
```

**Resultado Esperado**: Conexión exitosa y listado de tablas

#### 1.3 Verificación de Datos de Prueba
```bash
# Ejecutar script de datos de prueba
php crear_datos_prueba.php
```

**Resultado Esperado**: Datos de prueba creados si no existen

---

### Fase 2: Pruebas de API

#### 2.1 Endpoints Básicos
| Endpoint | Método | Descripción | Test Command |
|----------|--------|-------------|--------------|
| `/api/envios` | GET | Lista de envíos | `curl "http://localhost/mikelo/api/envios"` |
| `/api/envios/pdf` | GET | PDF lista | `curl "http://localhost/mikelo/api/envios/pdf"` |
| `/api/envios/excel` | GET | Excel lista | `curl "http://localhost/mikelo/api/envios/excel"` |
| `/api/envios/{id}/pdf` | GET | PDF remito | `curl "http://localhost/mikelo/api/envios/11/pdf"` |
| `/api/envios/{id}/excel` | GET | Excel remito | `curl "http://localhost/mikelo/api/envios/11/excel"` |

**Resultado Esperado**: Todos deben retornar JSON con `{"success": true, "url": "..."}`

#### 2.2 Filtros en Lista
```bash
# Test con filtros
curl "http://localhost/mikelo/api/envios/pdf?fechaDesde=2025-10-01&fechaHasta=2025-10-03"
curl "http://localhost/mikelo/api/envios/excel?destino=2&estado=1"
```

**Resultado Esperado**: PDFs/Excel con datos filtrados

---

### Fase 3: Pruebas de Generación de Archivos

#### 3.1 Verificación de Archivos Generados
```bash
# Listar archivos en temp
dir c:\xampp7.4.30\htdocs\mikelo\temp\*.pdf
dir c:\xampp7.4.30\htdocs\mikelo\temp\*.xlsx
```

**Resultado Esperado**: Archivos PDF y Excel presentes con timestamps recientes

#### 3.2 Validación de Contenido PDF
- **Abrir PDF remito**: Verificar formato profesional
- **Verificar datos**: Números de remito, fechas, productos
- **Comprobar firmas**: Espacios para firmas de entrega/recepción
- **Revisar totales**: Cálculos correctos de peso y cantidad

#### 3.3 Validación de Contenido Excel
- **Abrir Excel remito**: Verificar formato tabular
- **Verificar encabezados**: Títulos y logos corporativos
- **Comprobar datos**: Coincidencia con base de datos
- **Revisar fórmulas**: Totales automáticos

---

### Fase 4: Pruebas de Frontend

#### 4.1 Prueba de Página de Test
**URL**: `http://localhost/mikelo/test_exportadores.html`

**Validaciones**:
- [ ] API de envíos carga correctamente
- [ ] Botones de exportación funcionan
- [ ] Links directos abren archivos
- [ ] Filtros se aplican correctamente
- [ ] Mensajes de éxito/error aparecen

#### 4.2 Prueba de Página Principal
**URL**: `http://localhost/mikelo/envios.html`

**Validaciones**:
- [ ] Botones de exportación en tabla de envíos
- [ ] Exportación de lista desde filtros
- [ ] Descarga automática de archivos
- [ ] Manejo de errores

---

### Fase 5: Pruebas de Integración

#### 5.1 Flujo Completo Usuario
1. **Crear envío** desde alta_deposito
2. **Ver en lista** de envíos
3. **Exportar remito** individual
4. **Verificar contenido** del PDF/Excel
5. **Exportar lista** filtrada
6. **Validar totales**

#### 5.2 Casos Edge
- **Envío sin productos**: ¿Maneja correctamente?
- **Productos sin contenedor**: ¿Muestra "-"?
- **Fechas inválidas**: ¿Valida filtros?
- **ID inexistente**: ¿Retorna error apropiado?

---

### Fase 6: Pruebas de Performance

#### 6.1 Volumen de Datos
```bash
# Generar muchos envíos para probar performance
# TODO: Script para crear 100+ envíos de prueba
```

#### 6.2 Tiempo de Generación
- **PDF pequeño** (1-5 productos): < 2 segundos
- **PDF grande** (50+ productos): < 10 segundos
- **Excel pequeño**: < 1 segundo
- **Excel grande**: < 5 segundos

---

## 📝 Checklist de Validación Final

### ✅ Funcionalidad
- [ ] PDF de remito tiene formato profesional
- [ ] Excel de remito es legible y útil
- [ ] PDF de lista incluye todos los envíos
- [ ] Excel de lista permite análisis de datos
- [ ] Filtros funcionan correctamente
- [ ] Errores se manejan apropiadamente

### ✅ Calidad
- [ ] Datos coinciden con base de datos
- [ ] Cálculos son precisos
- [ ] Formato es consistente
- [ ] Información está completa
- [ ] Archivos se descargan correctamente

### ✅ Usabilidad
- [ ] Interfaz es intuitiva
- [ ] Mensajes de usuario son claros
- [ ] Tiempo de respuesta es aceptable
- [ ] Archivos tienen nombres descriptivos
- [ ] URLs son predecibles

---

## 🚀 Comandos de Test Rápido

```bash
# Test completo rápido
cd c:\xampp7.4.30\htdocs\mikelo\api

# 1. Verificar datos
php crear_datos_prueba.php

# 2. Test APIs
curl "http://localhost/mikelo/api/envios" | head
curl "http://localhost/mikelo/api/envios/pdf" -I
curl "http://localhost/mikelo/api/envios/excel" -I
curl "http://localhost/mikelo/api/envios/11/pdf" -I
curl "http://localhost/mikelo/api/envios/11/excel" -I

# 3. Verificar archivos generados
dir ..\temp\*.pdf
dir ..\temp\*.xlsx

# 4. Abrir página de test
start http://localhost/mikelo/test_exportadores.html
```

---

## 📋 Reporte de Estado Actual

### ✅ Completado
- [x] Instalación de librerías PDF/Excel
- [x] Nuevo formato de remito profesional
- [x] API de exportación funcionando
- [x] Generación de archivos exitosa
- [x] Página de test creada
- [x] Documentación de pruebas

### 🔄 En Proceso
- [ ] Validación exhaustiva de todos los casos edge
- [ ] Optimización de performance para grandes volúmenes
- [ ] Pruebas de estrés del sistema

### 📋 Pendiente para Fases Futuras
- [ ] Autenticación en exportadores
- [ ] Plantillas personalizables
- [ ] Exportación a otros formatos (CSV, etc.)
- [ ] Envío por email automático
- [ ] Histórico de exportaciones

---

## 🎯 Próximos Pasos Recomendados

1. **Ejecutar todas las pruebas** del plan documentado
2. **Validar formato de remito** con usuarios finales
3. **Optimizar queries** para mejor performance
4. **Agregar logs** para troubleshooting
5. **Documentar configuración** de producción