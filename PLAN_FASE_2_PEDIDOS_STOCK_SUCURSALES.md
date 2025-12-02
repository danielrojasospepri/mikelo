# FASE 2: SISTEMA DE PEDIDOS Y GESTIÓN DE STOCK EN SUCURSALES

## 📅 Fecha: Noviembre 29, 2025
## 🎯 Objetivo: Expandir sistema de inventario a sucursales con pedidos y control de stock local

---

## 📋 RESUMEN DE CAMBIOS FASE 1 (COMPLETADA)

### ✅ Implementado
- Búsqueda 3-pasos en `obtenerProductosDisponibles()`
- Validación correcta de disponibilidad con múltiples referencias
- Tests automatizados: 5/5 pasados
- Documentación técnica completa

### 📦 Estado para Producción
- **Backend:** Validado ✅
- **Frontend:** Cambios previos ya completos ✅
- **BD:** Esquema correcto con movimientos_items y referencias ✅

---

## 🏗️ ARQUITECTURA FASE 2

### Nuevos Componentes

```
SUCURSAL (Branch)
├── Módulo Pedidos
│   ├── Crear pedido (basado en stock mínimo)
│   ├── Ver pedidos (con estados)
│   └── Aceptar recepción
├── Configuración
│   ├── Stock mínimo por producto
│   └── Alertas
├── Baja de Stock
│   ├── Lectura de etiquetas (rápido)
│   └── Ajuste manual (correcciones)
└── Stock Local
    ├── Tabla stock (sucursal_id, producto_id, cnt)
    └── Histórico en movimientos

PLANTA CENTRAL
├── Tablero de Producción
│   ├── Pedidos pendientes agrupados
│   ├── Cantidades por producto
│   └── Priorización
├── Módulo Envíos (existente + mejoras)
│   ├── Precargar desde pedidos
│   ├── Considerar stock disponible
│   └── Permitir envíos parciales
└── Stock Central (existente)
```

---

## 📊 FLUJO DE PROCESOS

### Flujo 1: Crear Pedido (Sucursal)

```
Sucursal accede a "Crear Pedido"
    ↓
Sistema muestra productos con stock < stock_mínimo
    ↓
Sucursal selecciona cantidades a solicitar
    ↓
Pedido creado con estado = "PENDIENTE"
    ↓
Notificación a planta central
```

### Flujo 2: Tablero de Producción (Planta)

```
Planta ve "Tablero de Producción"
    ↓
Agrupa pedidos pendientes por producto
    ↓
Muestra: Producto X: 50 unidades (Sucursal A=20, B=30)
    ↓
Puede marcar como "EN PREPARACIÓN" o "LISTO"
```

### Flujo 3: Precarga de Envío (Planta)

```
Usuario abre "Crear Envío"
    ↓
Ve opción "Precarga desde Pedidos"
    ↓
Selecciona pedidos a enviar
    ↓
Sistema suma las cantidades
    ↓
Valida contra stock disponible
    ↓
Si hay insuficiencia: "Envío parcial - Pedido quedará pendiente"
    ↓
Crea envío con referencia a pedido
```

### Flujo 4: Aceptar Recepción (Sucursal)

```
Sucursal recibe envío
    ↓
Accede a "Recepciones Pendientes"
    ↓
Revisa producto y cantidad
    ↓
Haz clic "Aceptar"
    ↓
Sistema actualiza:
   • stock (sucursal) += cantidad
   • pedido.estado = "RECIBIDO" / "PARCIAL"
   • movimientos_items con estado RECIBIDO
```

### Flujo 5: Baja de Stock (Sucursal)

```
Opción A: Por Etiqueta (rápido)
  • Escanea código de barras
  • Sistema lee tipo 20 (cantidad) o tipo 21 (peso)
  • Decrementa stock local
  • Crea registro en movimientos (baja)

Opción B: Ajuste Manual (corrección)
  • Ver stock actual por producto
  • Editar cantidad
  • Sistema calcula diferencia
  • Registra baja en movimientos
```

---

## 🗄️ CAMBIOS EN BASE DE DATOS

### Tabla: `pedidos` (NUEVA)

```sql
CREATE TABLE pedidos (
  id INT PRIMARY KEY AUTO_INCREMENT,
  id_sucursal INT NOT NULL,
  id_usuario INT NOT NULL,
  fecha_creacion DATETIME DEFAULT NOW(),
  fecha_envio DATETIME,
  estado ENUM('PENDIENTE', 'PARCIAL', 'COMPLETADO') DEFAULT 'PENDIENTE',
  FOREIGN KEY (id_sucursal) REFERENCES ubicaciones(id),
  FOREIGN KEY (id_usuario) REFERENCES usuarios(id)
);

CREATE TABLE pedido_items (
  id INT PRIMARY KEY AUTO_INCREMENT,
  id_pedidos INT NOT NULL,
  id_productos INT NOT NULL,
  cantidad_solicitada DECIMAL(10,3),
  cantidad_recibida DECIMAL(10,3) DEFAULT 0,
  estado ENUM('PENDIENTE', 'ENVIADO', 'RECIBIDO') DEFAULT 'PENDIENTE',
  FOREIGN KEY (id_pedidos) REFERENCES pedidos(id),
  FOREIGN KEY (id_productos) REFERENCES productos(id)
);
```

### Tabla: `stock_minimo` (NUEVA)

```sql
CREATE TABLE stock_minimo (
  id INT PRIMARY KEY AUTO_INCREMENT,
  id_sucursal INT NOT NULL,
  id_productos INT NOT NULL,
  cantidad_minima DECIMAL(10,3) NOT NULL,
  alertar BOOLEAN DEFAULT TRUE,
  UNIQUE KEY (id_sucursal, id_productos),
  FOREIGN KEY (id_sucursal) REFERENCES ubicaciones(id),
  FOREIGN KEY (id_productos) REFERENCES productos(id)
);
```

### Tabla: `stock` (EXISTENTE - A USAR)

```sql
-- Ya existe, usarla para:
-- ubicaciones.id = 1 → Stock Central
-- ubicaciones.id = 2,3,4... → Stock Sucursales
UPDATE stock SET id_ubicaciones = id_sucursal
WHERE id_ubicaciones IS NOT NULL;
```

### Modificar: `movimientos`

```sql
-- Agregar tipo de movimiento
ALTER TABLE movimientos ADD COLUMN 
  tipo_movimiento ENUM('ALTA', 'ENVIO', 'RECEPCION', 'BAJA') DEFAULT 'ENVIO';

-- Agregar referencia a pedido (opcional)
ALTER TABLE movimientos ADD COLUMN 
  id_pedidos INT,
  FOREIGN KEY (id_pedidos) REFERENCES pedidos(id);
```

---

## 🎨 MÓDULOS A CREAR

### 1. Módulo de Pedidos (Sucursal)

**Archivo:** `pedidos_sucursal.html`

```
Sección 1: Crear Pedido
├── Botón "Crear nuevo pedido"
├── Auto-cargar productos con stock < mínimo
├── Tabla editable con cantidades
└── Botón "Guardar pedido"

Sección 2: Mis Pedidos
├── Filtro: Pendientes | Enviados | Completados
├── Tabla con estado
├── Botón "Ver detalles"
└── Botón "Aceptar recepción" (cuando sea ENVIADO)
```

**Archivo:** `js/pedidos_sucursal.js`

```javascript
// Funciones:
// - cargarProductosConStockBajo()
// - crearPedido()
// - aceptarRecepcion()
// - marcarRecibido()
```

### 2. Tablero de Producción (Planta)

**Archivo:** `tablero_produccion.html`

```
Sección 1: Resumen
├── Total productos pedidos
├── Sucursales con pedidos
└── Cantidad total a producir

Sección 2: Pedidos Agrupados
├── Producto X: 50 unidades
│  ├── Sucursal A: 20 (PENDIENTE)
│  ├── Sucursal B: 30 (PENDIENTE)
│  └── Botón "Marcar EN PREPARACIÓN"
└── Producto Y: 30 unidades
```

**Archivo:** `js/tablero_produccion.js`

```javascript
// Funciones:
// - cargarPedidosPendientes()
// - agruparPorProducto()
// - marcarEnPreparacion()
// - marcarListo()
```

### 3. Módulo Configuración Stock (Sucursal)

**Archivo:** `config_stock_minimo.html`

```
Tabla editable:
├── Producto | Stock Actual | Stock Mínimo | Alertar
├── [LIMON]  | 5            | [10]         | ☑
├── [PAN]    | 0            | [5]          | ☑
└── Botón "Guardar"
```

**Archivo:** `js/config_stock_minimo.js`

```javascript
// Funciones:
// - cargarStockMinimo()
// - guardarStockMinimo()
// - verificarAlertas()
```

### 4. Módulo Baja de Stock (Sucursal)

**Archivo:** `baja_stock.html`

```
Sección 1: Por Etiqueta (Rápido)
├── Lector de código de barras
├── Campo: Código | Cantidad | Peso
├── Tabla de bajas realizadas
└── Botón "Finalizar baja"

Sección 2: Ajuste Manual (Corrección)
├── Tabla: Producto | Stock Actual | Stock Correcto
├── Editable: Stock Correcto
├── Calcula diferencia automáticamente
└── Botón "Aplicar correcciones"
```

**Archivo:** `js/baja_stock.js`

```javascript
// Funciones:
// - procesarEtiqueta() // Tipo 20 o 21
// - restarDelStock()
// - cargarStockActual()
// - aplicarAjustesMultiples()
```

### 5. Mejoras Módulo Envíos (Planta)

**Modificar:** `envios.html` y `js/envios_nuevo.js`

```
Agregar sección:
├── Botón "Precarga desde Pedidos"
├── Selector: Sucursal | Pedidos Pendientes
├── Tabla: Producto | Cantidad Pedida | Cantidad Disponible
├── Botón "Envío parcial" (si no hay suficiente)
└── Vincula envío a pedido_items
```

---

## 🔌 API ENDPOINTS A CREAR

### Pedidos

```
POST   /api/pedidos/crear
GET    /api/pedidos/mis-pedidos?id_sucursal=X
GET    /api/pedidos/{id}
PUT    /api/pedidos/{id}/aceptar-recepcion
GET    /api/pedidos/pendientes (para tablero)
```

### Stock Mínimo

```
GET    /api/stock-minimo/por-sucursal?id_sucursal=X
POST   /api/stock-minimo/guardar
GET    /api/stock-minimo/alertas?id_sucursal=X
```

### Baja de Stock

```
POST   /api/stock/baja-por-etiqueta
POST   /api/stock/ajuste-manual
GET    /api/stock/actual?id_sucursal=X
```

### Mejoras Envíos

```
GET    /api/pedidos/para-precargar?id_sucursal=X
POST   /api/envios/crear-desde-pedido
```

---

## 📈 PRIORIDAD DE IMPLEMENTACIÓN

### Semana 1
1. ✅ Crear tablas (`pedidos`, `pedido_items`, `stock_minimo`)
2. ✅ API Pedidos básica (crear, listar, aceptar recepción)
3. ✅ Frontend Crear Pedido (sucursal)
4. ✅ Frontend Mis Pedidos (sucursal)

### Semana 2
5. ✅ Tablero de Producción (visualización)
6. ✅ Precarga de Envíos desde Pedidos
7. ✅ API Baja de Stock
8. ✅ Frontend Baja de Stock (por etiqueta)

### Semana 3
9. ✅ Frontend Ajuste Manual de Stock
10. ✅ Config Stock Mínimo (API + Frontend)
11. ✅ Tests integración
12. ✅ Documentación

---

## 💡 CONSIDERACIONES ESPECIALES

### Stock Mínimo - Sugerencias de Implementación

**Opción 1: Basado en Histórico de Ventas (Recomendado)**
- Analizar últimos 30 días
- Calcular: promedio_diario * días_entre_entregas
- Sugerir: promedio_diario * 5 (buffer 5 días)

**Opción 2: Basado en Capacidad de Almacenamiento**
- Considerar espacio físico en sucursal
- Definir máximo por tipo de producto
- Stock mínimo = máximo * 0.3

**Opción 3: Configuración Manual (Actual)**
- Usuario define según su experiencia
- Más flexible pero menos automático

**Recomendación:** Implementar Opción 1 + permitir override manual

### Baja de Stock - Técnicas

**Técnica A: Lectura de Etiquetas (RECOMENDADA)**
- Más rápida: escanea código
- Menos errores: automática
- Registra quién y cuándo
- Ideal para: operaciones de cierre de día

**Técnica B: Ajuste Manual**
- Más flexible: permite correcciones
- Documenta diferencias
- Registra motivo
- Ideal para: correcciones, cambios

**Implementación:** Ambas + opción de "reconciliación" que compara ambas técnicas

### Envíos Parciales de Pedidos

```
Pedido solicita: 100 unidades
Stock disponible: 70 unidades

Sistema:
  ✅ Crea envío con 70 unidades
  ✅ Marca pedido como "PARCIAL"
  ✅ Pedido permanece abierto para próximo envío
  ✅ Usuario ve: "Falta recibir: 30 unidades"
```

---

## 🔒 SEGURIDAD Y VALIDACIONES

### Validaciones a Implementar

1. **Crear Pedido**
   - Solo sucursales pueden crear
   - Validar stock mínimo > 0
   - No duplicar pedidos del mismo día

2. **Aceptar Recepción**
   - Solo sucursal receptora
   - Validar cantidad recibida vs enviada
   - Registrar quién acepta

3. **Baja de Stock**
   - Solo personal autorizado
   - Validar stock suficiente
   - Registrar motivo de baja

4. **Tablero de Producción**
   - Solo personal de planta
   - Ver datos de todos los pedidos
   - No modificar pedidos (solo ver estado)

---

## 📝 DOCUMENTACIÓN A ACTUALIZAR

1. Diagrama de flujos completo
2. Guía de usuario por módulo
3. Tabla de permisos por rol
4. Procedimiento de backups
5. Planes de contingencia

---

## ✅ CHECKLIST DE PRODUCCIÓN

Antes de subir a producción:

- [ ] Todas las tablas creadas y validadas
- [ ] API endpoints testeados (Postman/REST Client)
- [ ] Frontend validado en navegador
- [ ] Datos de prueba cargados
- [ ] Backups programados
- [ ] Permisos de usuarios configurados
- [ ] Documentación actualizada
- [ ] Tests de carga ejecutados
- [ ] Plan de rollback definido

---

## 🎯 MÉTRICAS DE ÉXITO

- Tiempo promedio de crear pedido: < 2 minutos
- Precisión de stock: > 98%
- Tiempo de procesamiento de baja: < 10 segundos por artículo
- Tasa de envíos parciales: < 20%

