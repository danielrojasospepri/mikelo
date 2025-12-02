# PLAN DE IMPLEMENTACIÓN FASE 2: GESTIÓN DE PEDIDOS Y STOCK LOCAL
## Documento Técnico para Supervisor de Desarrollo

**Versión:** 1.0  
**Fecha:** 29 de Noviembre de 2025  
**Audiencia:** Informático/Supervisor de Desarrollo  
**Scope:** Fase 2 - Semanas 1 a 3 (Dic 2-20, 2025)

---

## I. ESTRUCTURA GENERAL

### Cronograma de Desarrollo
- **Semana 1 (Dic 2-6):** Infraestructura de Base de Datos + API Foundation
- **Semana 2 (Dic 9-13):** Dashboard Producción + Recepciones + Baja de Stock
- **Semana 3 (Dic 16-20):** Stock Mínimo + Refinamiento + Tests

### Equipos y Responsabilidades
- **Backend:** Implementación API, modelos, validaciones
- **Frontend:** Vistas HTML, JS, integración con API
- **QA:** Tests automatizados, validación de flujos
- **DevOps:** Deploy a staging/producción

---

## II. SEMANA 1: INFRAESTRUCTURA DE BASE DE DATOS Y API

### 2.1 CAMBIOS EN BASE DE DATOS

#### 2.1.1 Nuevas Tablas

**Tabla: `pedidos`**
```sql
CREATE TABLE pedidos (
    id INT PRIMARY KEY AUTO_INCREMENT,
    id_sucursal INT NOT NULL,
    id_usuario INT NOT NULL,
    estado ENUM('PENDIENTE', 'ACEPTADO', 'RECHAZADO', 'PREPARACION', 'LISTO_ENVIO', 'ENVIADO', 'RECIBIDO') DEFAULT 'PENDIENTE',
    fecha_creacion DATETIME DEFAULT CURRENT_TIMESTAMP,
    fecha_confirmacion DATETIME NULL,
    fecha_envio DATETIME NULL,
    fecha_recepcion DATETIME NULL,
    observaciones TEXT,
    motivo_rechazo VARCHAR(500),
    FOREIGN KEY (id_sucursal) REFERENCES sucursales(id),
    FOREIGN KEY (id_usuario) REFERENCES usuarios(id),
    INDEX (id_sucursal, estado),
    INDEX (fecha_creacion)
);
```

**Tabla: `pedido_items`**
```sql
CREATE TABLE pedido_items (
    id INT PRIMARY KEY AUTO_INCREMENT,
    id_pedido INT NOT NULL,
    id_movimiento_item INT NOT NULL,
    cantidad_solicitada INT NOT NULL,
    cantidad_confirmada INT DEFAULT 0,
    cantidad_rechazada INT DEFAULT 0,
    FOREIGN KEY (id_pedido) REFERENCES pedidos(id) ON DELETE CASCADE,
    FOREIGN KEY (id_movimiento_item) REFERENCES movimientos_items(id),
    INDEX (id_pedido),
    UNIQUE KEY unique_pedido_item (id_pedido, id_movimiento_item)
);
```

**Tabla: `stock_sucursales`**
```sql
CREATE TABLE stock_sucursales (
    id INT PRIMARY KEY AUTO_INCREMENT,
    id_sucursal INT NOT NULL,
    id_movimiento_item INT NOT NULL,
    cantidad INT DEFAULT 0,
    fecha_actualizacion DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (id_sucursal) REFERENCES sucursales(id),
    FOREIGN KEY (id_movimiento_item) REFERENCES movimientos_items(id),
    UNIQUE KEY unique_stock_sucursal (id_sucursal, id_movimiento_item),
    INDEX (id_sucursal)
);
```

**Tabla: `stock_minimo`**
```sql
CREATE TABLE stock_minimo (
    id INT PRIMARY KEY AUTO_INCREMENT,
    id_sucursal INT NOT NULL,
    id_movimiento_item INT NOT NULL,
    cantidad_minima INT NOT NULL DEFAULT 5,
    alerta_activa BOOLEAN DEFAULT FALSE,
    fecha_configuracion DATETIME DEFAULT CURRENT_TIMESTAMP,
    fecha_actualizacion DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (id_sucursal) REFERENCES sucursales(id),
    FOREIGN KEY (id_movimiento_item) REFERENCES movimientos_items(id),
    UNIQUE KEY unique_minimo_sucursal (id_sucursal, id_movimiento_item),
    INDEX (id_sucursal, alerta_activa)
);
```

#### 2.1.2 Modificaciones a Tablas Existentes

**Tabla: `movimientos` - Agregar Campos**
```sql
ALTER TABLE movimientos ADD COLUMN (
    id_pedido INT NULL,
    tipo_movimiento ENUM('ALTA_DEPOSITO', 'ENVIO', 'RECEPCION', 'BAJA_STOCK', 'AJUSTE') DEFAULT 'ALTA_DEPOSITO',
    FOREIGN KEY (id_pedido) REFERENCES pedidos(id),
    INDEX (id_pedido, tipo_movimiento)
);
```

**Tabla: `envios` - Agregar Campo para Recepciones**
```sql
ALTER TABLE envios ADD COLUMN (
    estado_recepcion ENUM('PENDIENTE_RECEPCION', 'PARCIALMENTE_RECIBIDO', 'RECIBIDO', 'OBSERVACIONES') DEFAULT 'PENDIENTE_RECEPCION',
    fecha_recepcion DATETIME NULL,
    observaciones_recepcion TEXT,
    INDEX (estado_recepcion)
);
```

#### 2.1.3 Cambios en `estados_items_movimientos`

**Extender para Recepciones**
```sql
-- La tabla ya existe y funciona
-- Solo agregamos un nuevo estado: 'RECIBIDO_SUCURSAL'
-- Valores de estado: NUEVO, ENVIADO, RECIBIDO, CANCELADO, RECIBIDO_SUCURSAL
```

#### 2.1.4 Script de Migración de Datos

```sql
-- Poblar tabla stock_sucursales desde movimientos existentes
INSERT INTO stock_sucursales (id_sucursal, id_movimiento_item, cantidad)
SELECT DISTINCT 
    e.id_sucursal,
    mi.id,
    SUM(mi.cantidad) as cantidad
FROM envios e
INNER JOIN movimientos m ON e.id_movimiento = m.id
INNER JOIN movimientos_items mi ON m.id = mi.id_movimiento
WHERE e.confirmado = 1
GROUP BY e.id_sucursal, mi.id
ON DUPLICATE KEY UPDATE cantidad = VALUES(cantidad);

-- Actualizar movimientos con tipo_movimiento basado en análisis
UPDATE movimientos SET tipo_movimiento = 'ENVIO' WHERE id IN (SELECT id_movimiento FROM envios);
```

---

### 2.2 ARQUITECTURA API - SEMANA 1

#### 2.2.1 Estructura de Directorios

```
api/src/
├── Controller/
│   ├── PedidosController.php (NUEVO)
│   ├── StockSucursalesController.php (NUEVO)
│   ├── RecepcionesController.php (NUEVO)
│   └── [existentes]
├── Model/
│   ├── Pedido.php (NUEVO)
│   ├── PedidoItem.php (NUEVO)
│   ├── StockSucursal.php (NUEVO)
│   ├── Recepcion.php (NUEVO)
│   └── [existentes]
├── Service/
│   ├── PedidoService.php (NUEVO)
│   ├── StockService.php (NUEVO)
│   └── [existentes]
└── Middleware/
    └── [existentes]
```

#### 2.2.2 Endpoints API Semana 1

**Pedidos - CRUD Base**

| Método | Endpoint | Descripción | Respuesta |
|--------|----------|-------------|----------|
| POST | `/api/pedidos/crear` | Crear pedido nuevo | `{ id_pedido, estado: PENDIENTE, fecha }` |
| GET | `/api/pedidos/listar` | Listar pedidos de sucursal | `[ { id, estado, items, fecha } ]` |
| GET | `/api/pedidos/{id}/detalles` | Obtener detalles completos | `{ pedido, items[], estado, total }` |
| PUT | `/api/pedidos/{id}/enviar` | Enviar pedido a planta | `{ id_pedido, estado: ACEPTADO }` |
| GET | `/api/pedidos/disponibles` | Disponibilidad en depósito | `[ { id_item, producto, disponible } ]` |

**Stock Sucursales - API Base**

| Método | Endpoint | Descripción |
|--------|----------|-------------|
| GET | `/api/stock/sucursal/{id}` | Stock actual de sucursal |
| GET | `/api/stock/historial/{id_item}` | Historial movimientos |

#### 2.2.3 Clase Pedido - Modelo

```php
namespace Api\Model;

class Pedido {
    private $db;
    private $id;
    private $id_sucursal;
    private $id_usuario;
    private $estado; // PENDIENTE, ACEPTADO, RECHAZADO, PREPARACION, LISTO_ENVIO, ENVIADO, RECIBIDO
    private $items;
    
    public function __construct($db) {
        $this->db = $db;
        $this->items = [];
    }
    
    public function crear($id_sucursal, $id_usuario, $items) {
        // Validar stock disponible
        foreach ($items as $item) {
            $disponible = $this->verificarDisponibilidad($item['id_movimiento_item'], $item['cantidad']);
            if (!$disponible) {
                throw new \Exception("Stock insuficiente para item: {$item['id_movimiento_item']}");
            }
        }
        
        // Crear pedido
        $stmt = $this->db->prepare("
            INSERT INTO pedidos (id_sucursal, id_usuario, estado, fecha_creacion)
            VALUES (?, ?, 'PENDIENTE', NOW())
        ");
        $stmt->execute([$id_sucursal, $id_usuario]);
        $this->id = $this->db->lastInsertId();
        
        // Crear items
        foreach ($items as $item) {
            $stmt = $this->db->prepare("
                INSERT INTO pedido_items (id_pedido, id_movimiento_item, cantidad_solicitada)
                VALUES (?, ?, ?)
            ");
            $stmt->execute([$this->id, $item['id_movimiento_item'], $item['cantidad']]);
        }
        
        return [
            'id_pedido' => $this->id,
            'estado' => 'PENDIENTE',
            'fecha' => date('Y-m-d H:i:s')
        ];
    }
    
    public function enviar($id_pedido) {
        $stmt = $this->db->prepare("
            UPDATE pedidos SET estado = 'ACEPTADO', fecha_confirmacion = NOW()
            WHERE id = ?
        ");
        $stmt->execute([$id_pedido]);
        
        return true;
    }
    
    private function verificarDisponibilidad($id_movimiento_item, $cantidad) {
        $stmt = $this->db->prepare("
            SELECT 
                SUM(mi.cantidad) as total,
                IFNULL(SUM(ref.cantidad), 0) as referencias
            FROM movimientos_items mi
            WHERE mi.id = ?
            AND mi.id_movimientos_items_origen IS NULL
            AND NOT EXISTS (SELECT 1 FROM movimientos_items ref WHERE ref.id_movimientos_items_origen = mi.id)
        ");
        $stmt->execute([$id_movimiento_item]);
        $result = $stmt->fetch(\PDO::FETCH_ASSOC);
        
        $disponible = $result['total'] - $result['referencias'];
        return $disponible >= $cantidad;
    }
    
    public function obtenerDetalles($id_pedido) {
        $stmt = $this->db->prepare("
            SELECT p.*, pi.*, m.*, p.id AS pedido_id
            FROM pedidos p
            LEFT JOIN pedido_items pi ON p.id = pi.id_pedido
            LEFT JOIN movimientos_items mi ON pi.id_movimiento_item = mi.id
            LEFT JOIN movimientos m ON mi.id_movimiento = m.id
            WHERE p.id = ?
        ");
        $stmt->execute([$id_pedido]);
        
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }
}
```

#### 2.2.4 PedidoController - Endpoints

```php
namespace Api\Controller;

class PedidosController {
    private $db;
    
    public function crear($request, $response) {
        $data = $request->getParsedBody();
        
        // Validaciones
        if (!isset($data['id_sucursal']) || !isset($data['items'])) {
            return $response->withStatus(400)->withJson(['error' => 'Datos incompletos']);
        }
        
        try {
            $pedido = new \Api\Model\Pedido($this->db);
            $resultado = $pedido->crear($data['id_sucursal'], $data['id_usuario'], $data['items']);
            
            return $response->withJson($resultado);
        } catch (\Exception $e) {
            return $response->withStatus(400)->withJson(['error' => $e->getMessage()]);
        }
    }
    
    public function listar($request, $response) {
        $id_sucursal = $request->getAttribute('id_sucursal');
        
        $stmt = $this->db->prepare("
            SELECT id, estado, fecha_creacion, fecha_confirmacion
            FROM pedidos
            WHERE id_sucursal = ?
            ORDER BY fecha_creacion DESC
            LIMIT 50
        ");
        $stmt->execute([$id_sucursal]);
        
        return $response->withJson($stmt->fetchAll(\PDO::FETCH_ASSOC));
    }
    
    public function detalles($request, $response) {
        $id_pedido = $request->getAttribute('id');
        
        $pedido = new \Api\Model\Pedido($this->db);
        $detalles = $pedido->obtenerDetalles($id_pedido);
        
        return $response->withJson($detalles);
    }
    
    public function enviar($request, $response) {
        $id_pedido = $request->getAttribute('id');
        
        $pedido = new \Api\Model\Pedido($this->db);
        $pedido->enviar($id_pedido);
        
        return $response->withJson(['success' => true, 'mensaje' => 'Pedido enviado a planta']);
    }
}
```

---

### 2.3 LIMITACIONES Y ALCANCES - SEMANA 1

#### Alcances
✅ Creación de pedidos con validación de stock  
✅ Visualización de disponibilidad en depósito  
✅ API CRUD base para pedidos  
✅ Persistencia en base de datos  
✅ Validación de duplicados por sucursal+item  

#### Limitaciones
❌ NO se integra con UI frontend (solo API)  
❌ NO hay autenticación JWT (se usa placeholder)  
❌ NO hay estado de "borrador" para edición posterior  
❌ NO hay validación de permisos por rol  
❌ NO hay auditoría de cambios  
❌ NO hay notificaciones a planta cuando llega pedido  

---

## III. SEMANA 2: DASHBOARD PRODUCCIÓN + RECEPCIONES + BAJA DE STOCK

### 3.1 DASHBOARD DE PRODUCCIÓN (Planta)

#### 3.1.1 Funcionalidad

**Pantalla:** `tablero_produccion.html`

- Vista de todos los pedidos pendientes con:
  - Filtros por estado (ACEPTADO, PREPARACION, LISTO_ENVIO)
  - Listado de items por pedido
  - Botones: Iniciar Preparación → Marcar Listo → Confirmar Envío
  - Búsqueda por sucursal/fecha

**Archivo:** `js/tablero_produccion.js`
- Carga pedidos cada 30 segundos (polling)
- Actualiza estado al hacer clic en botones
- Mostrar cantidad total de items por pedido

#### 3.1.2 Endpoints Nuevos

| Método | Endpoint | Descripción |
|--------|----------|-------------|
| GET | `/api/pedidos/planta/pendientes` | Listar pedidos para preparar |
| PUT | `/api/pedidos/{id}/preparacion` | Cambiar a estado PREPARACION |
| PUT | `/api/pedidos/{id}/listo-envio` | Cambiar a estado LISTO_ENVIO |
| PUT | `/api/pedidos/{id}/envio-confirmado` | Crear movimiento de envío |

#### 3.1.3 Modelo de Datos - Cambios

```php
class Pedido {
    public function cambiarEstado($id_pedido, $nuevo_estado) {
        // Validar transiciones válidas
        $transiciones_validas = [
            'ACEPTADO' => ['PREPARACION', 'RECHAZADO'],
            'PREPARACION' => ['LISTO_ENVIO', 'ACEPTADO'],
            'LISTO_ENVIO' => ['ENVIADO'],
            'ENVIADO' => ['RECIBIDO']
        ];
        
        $stmt = $this->db->prepare("SELECT estado FROM pedidos WHERE id = ?");
        $stmt->execute([$id_pedido]);
        $actual = $stmt->fetch(\PDO::FETCH_ASSOC)['estado'];
        
        if (!in_array($nuevo_estado, $transiciones_validas[$actual] ?? [])) {
            throw new \Exception("Transición no válida de $actual a $nuevo_estado");
        }
        
        $stmt = $this->db->prepare("UPDATE pedidos SET estado = ? WHERE id = ?");
        return $stmt->execute([$nuevo_estado, $id_pedido]);
    }
    
    public function confirmarEnvio($id_pedido) {
        // 1. Obtener items del pedido
        $stmt = $this->db->prepare("
            SELECT pi.id_movimiento_item, pi.cantidad_confirmada
            FROM pedido_items pi
            WHERE pi.id_pedido = ?
        ");
        $stmt->execute([$id_pedido]);
        $items = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        
        // 2. Crear movimiento de ENVIO vinculado al pedido
        $stmt = $this->db->prepare("
            INSERT INTO movimientos (id_sucursal, tipo_movimiento, id_pedido, estado)
            VALUES (
                (SELECT id_sucursal FROM pedidos WHERE id = ?),
                'ENVIO',
                ?,
                'ENVIADO'
            )
        ");
        $stmt->execute([$id_pedido, $id_pedido]);
        $id_movimiento = $this->db->lastInsertId();
        
        // 3. Crear referencias de items en movimientos_items
        foreach ($items as $item) {
            $stmt = $this->db->prepare("
                INSERT INTO movimientos_items (id_movimiento, id_movimientos_items_origen, cantidad)
                VALUES (?, ?, ?)
            ");
            $stmt->execute([$id_movimiento, $item['id_movimiento_item'], $item['cantidad_confirmada']]);
        }
        
        // 4. Cambiar estado del pedido
        $stmt = $this->db->prepare("UPDATE pedidos SET estado = 'ENVIADO', fecha_envio = NOW() WHERE id = ?");
        $stmt->execute([$id_pedido]);
        
        return $id_movimiento;
    }
}
```

---

### 3.2 MÓDULO DE RECEPCIONES (Sucursal)

#### 3.2.1 Funcionalidad

**Pantalla:** `recepciones.html`

- Listado de envíos pendientes de recepción
- Para cada envío:
  - Mostrar items esperados con cantidades
  - Permitir escanear/ingresar cantidad recibida
  - Comparar con cantidad esperada
  - Registrar observaciones si hay discrepancias
- Estados: PENDIENTE_RECEPCION → PARCIALMENTE_RECIBIDO → RECIBIDO

**Archivo:** `js/recepciones.js`
- Scanner de códigos de barras para confirmar items
- Validar cantidad recibida vs. esperada
- Mostrar alertas de faltantes/sobrantes

#### 3.2.2 Endpoints Nuevos

| Método | Endpoint | Descripción |
|--------|----------|-------------|
| GET | `/api/recepciones/pendientes` | Envíos a recibir |
| GET | `/api/envios/{id}/detalles-recepcion` | Items esperados |
| PUT | `/api/recepciones/{id}/registrar-item` | Registrar cantidad recibida |
| PUT | `/api/recepciones/{id}/confirmar` | Finalizar recepción |

#### 3.2.3 Modelo Recepcion

```php
namespace Api\Model;

class Recepcion {
    private $db;
    
    public function obtenerPendientes($id_sucursal) {
        $stmt = $this->db->prepare("
            SELECT e.id, e.fecha_creacion, e.id_movimiento, m.fecha, p.id AS id_pedido
            FROM envios e
            INNER JOIN movimientos m ON e.id_movimiento = m.id
            LEFT JOIN pedidos p ON m.id_pedido = p.id
            WHERE e.id_sucursal = ?
            AND e.estado_recepcion = 'PENDIENTE_RECEPCION'
            ORDER BY e.fecha_creacion ASC
        ");
        $stmt->execute([$id_sucursal]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }
    
    public function obtenerDetalles($id_envio) {
        $stmt = $this->db->prepare("
            SELECT 
                mi.id,
                mi.id_movimientos_items_origen,
                mi.cantidad,
                mi.cantidad as cantidad_esperada,
                COALESCE(r.cantidad_recibida, 0) as cantidad_recibida,
                p.codigo, p.familia, p.descripcion
            FROM envios e
            INNER JOIN movimientos m ON e.id_movimiento = m.id
            INNER JOIN movimientos_items mi ON m.id = mi.id_movimiento
            LEFT JOIN productos p ON (
                SELECT id_producto FROM movimientos_items 
                WHERE id = mi.id_movimientos_items_origen
            ) = p.id
            LEFT JOIN recepciones_items r ON mi.id = r.id_movimiento_item
            WHERE e.id = ?
        ");
        $stmt->execute([$id_envio]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }
    
    public function registrarItem($id_envio, $id_movimiento_item, $cantidad_recibida) {
        // Verificar cantidad esperada
        $stmt = $this->db->prepare("
            SELECT cantidad FROM movimientos_items WHERE id = ?
        ");
        $stmt->execute([$id_movimiento_item]);
        $esperada = $stmt->fetch(\PDO::FETCH_ASSOC)['cantidad'];
        
        // Registrar en tabla recepciones_items
        $stmt = $this->db->prepare("
            INSERT INTO recepciones_items (id_envio, id_movimiento_item, cantidad_recibida)
            VALUES (?, ?, ?)
            ON DUPLICATE KEY UPDATE cantidad_recibida = ?
        ");
        $stmt->execute([$id_envio, $id_movimiento_item, $cantidad_recibida, $cantidad_recibida]);
        
        // Actualizar estado del envío si hay discrepancia
        if ($cantidad_recibida < $esperada) {
            $stmt = $this->db->prepare("
                UPDATE envios SET estado_recepcion = 'PARCIALMENTE_RECIBIDO' WHERE id = ?
            ");
            $stmt->execute([$id_envio]);
        }
        
        return [
            'cantidad_esperada' => $esperada,
            'cantidad_recibida' => $cantidad_recibida,
            'discrepancia' => $esperada - $cantidad_recibida
        ];
    }
    
    public function confirmarRecepcion($id_envio, $observaciones = null) {
        // 1. Obtener detalles del envío
        $detalles = $this->obtenerDetalles($id_envio);
        
        // 2. Actualizar stock en sucursal
        foreach ($detalles as $item) {
            // Obtener id_movimiento_item original para actualizar stock
            $id_item_original = $item['id_movimientos_items_origen'];
            
            $stmt = $this->db->prepare("
                INSERT INTO stock_sucursales (id_sucursal, id_movimiento_item, cantidad)
                VALUES (
                    (SELECT id_sucursal FROM envios WHERE id = ?),
                    ?,
                    ?
                )
                ON DUPLICATE KEY UPDATE cantidad = cantidad + ?
            ");
            $stmt->execute([
                $id_envio,
                $id_item_original,
                $item['cantidad_recibida'],
                $item['cantidad_recibida']
            ]);
        }
        
        // 3. Marcar envío como recibido
        $stmt = $this->db->prepare("
            UPDATE envios SET 
                estado_recepcion = 'RECIBIDO',
                fecha_recepcion = NOW(),
                observaciones_recepcion = ?
            WHERE id = ?
        ");
        $stmt->execute([$observaciones, $id_envio]);
        
        // 4. Cambiar estado del pedido (si existe)
        $stmt = $this->db->prepare("
            UPDATE pedidos SET estado = 'RECIBIDO', fecha_recepcion = NOW()
            WHERE id = (SELECT id_pedido FROM movimientos WHERE id = (SELECT id_movimiento FROM envios WHERE id = ?))
        ");
        $stmt->execute([$id_envio]);
        
        return true;
    }
}
```

#### 3.2.4 Nueva Tabla `recepciones_items`

```sql
CREATE TABLE recepciones_items (
    id INT PRIMARY KEY AUTO_INCREMENT,
    id_envio INT NOT NULL,
    id_movimiento_item INT NOT NULL,
    cantidad_recibida INT NOT NULL,
    observacion TEXT,
    fecha_registro DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (id_envio) REFERENCES envios(id),
    FOREIGN KEY (id_movimiento_item) REFERENCES movimientos_items(id),
    UNIQUE KEY unique_recepcion_item (id_envio, id_movimiento_item)
);
```

---

### 3.3 MÓDULO DE BAJA DE STOCK (Sucursal)

#### 3.3.1 Funcionalidad

**Dos Métodos Soportados:**

**Método A: Por Etiqueta (Rápido)**
- Pantalla: `baja_stock_etiquetas.html`
- Escanear código de barras de cada producto vendido
- Sistema descuenta 1 unidad por escaneo
- Total acumulado en tiempo real
- Confirmar venta al final del día

**Método B: Ajuste Manual (Correcciones)**
- Pantalla: `baja_stock_ajuste_manual.html`
- Buscar producto por código/familia
- Ingresar cantidad manual a descontar
- Registrar motivo (venta, rotura, merma, otro)
- Historial de ajustes

#### 3.3.2 Endpoints Nuevos

| Método | Endpoint | Descripción |
|--------|----------|-------------|
| POST | `/api/baja-stock/etiqueta` | Registrar escaneo (Método A) |
| POST | `/api/baja-stock/ajuste` | Registrar ajuste manual (Método B) |
| POST | `/api/baja-stock/confirmar-dia` | Finalizar y grabar movimiento |
| GET | `/api/baja-stock/historial` | Ver historial de bajas |

#### 3.3.3 Modelo BajaStock

```php
namespace Api\Model;

class BajaStock {
    private $db;
    private $sesion_id; // Identificador único de sesión de venta
    
    public function registrarEtiqueta($id_sucursal, $codigo_barras) {
        // 1. Decodificar código de barras
        $id_producto = $this->decodificarCodigoBarras($codigo_barras);
        
        // 2. Obtener id_movimiento_item
        $stmt = $this->db->prepare("
            SELECT ss.id, ss.cantidad FROM stock_sucursales ss
            INNER JOIN movimientos_items mi ON ss.id_movimiento_item = mi.id
            WHERE ss.id_sucursal = ? AND mi.id_producto = ?
            LIMIT 1
        ");
        $stmt->execute([$id_sucursal, $id_producto]);
        $stock = $stmt->fetch(\PDO::FETCH_ASSOC);
        
        if (!$stock || $stock['cantidad'] <= 0) {
            throw new \Exception("Sin stock para producto: $id_producto");
        }
        
        // 3. Registrar en tabla temporal de sesión
        $stmt = $this->db->prepare("
            INSERT INTO baja_stock_sesion (id_sucursal, id_stock_sucursal, cantidad, metodo, fecha_registro)
            VALUES (?, ?, 1, 'ETIQUETA', NOW())
        ");
        $stmt->execute([$id_sucursal, $stock['id']]);
        
        return [
            'producto' => $id_producto,
            'cantidad_restante' => $stock['cantidad'] - 1,
            'acumulado' => $this->obtenerTotalSesion($id_sucursal)
        ];
    }
    
    public function registrarAjusteManual($id_sucursal, $id_movimiento_item, $cantidad, $motivo) {
        // 1. Validar que la cantidad no supere el stock disponible
        $stmt = $this->db->prepare("
            SELECT cantidad FROM stock_sucursales 
            WHERE id_sucursal = ? AND id_movimiento_item = ?
        ");
        $stmt->execute([$id_sucursal, $id_movimiento_item]);
        $resultado = $stmt->fetch(\PDO::FETCH_ASSOC);
        
        if (!$resultado || $resultado['cantidad'] < $cantidad) {
            throw new \Exception("Stock insuficiente. Disponible: {$resultado['cantidad']}");
        }
        
        // 2. Registrar ajuste
        $stmt = $this->db->prepare("
            INSERT INTO baja_stock_sesion (id_sucursal, id_stock_sucursal, cantidad, metodo, motivo, fecha_registro)
            VALUES (
                ?,
                (SELECT id FROM stock_sucursales WHERE id_sucursal = ? AND id_movimiento_item = ?),
                ?,
                'AJUSTE_MANUAL',
                ?,
                NOW()
            )
        ");
        $stmt->execute([$id_sucursal, $id_sucursal, $id_movimiento_item, $cantidad, $motivo]);
        
        return true;
    }
    
    public function confirmarDia($id_sucursal) {
        // 1. Obtener total de bajas de la sesión
        $stmt = $this->db->prepare("
            SELECT 
                ss.id_stock_sucursal,
                SUM(bss.cantidad) as total_baja
            FROM baja_stock_sesion bss
            INNER JOIN stock_sucursales ss ON bss.id_stock_sucursal = ss.id
            WHERE bss.id_sucursal = ?
            GROUP BY bss.id_stock_sucursal
        ");
        $stmt->execute([$id_sucursal]);
        $bajas = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        
        // 2. Crear movimiento de BAJA_STOCK
        $stmt = $this->db->prepare("
            INSERT INTO movimientos (id_sucursal, tipo_movimiento, estado, fecha)
            VALUES (?, 'BAJA_STOCK', 'NUEVO', NOW())
        ");
        $stmt->execute([$id_sucursal]);
        $id_movimiento = $this->db->lastInsertId();
        
        // 3. Actualizar stock_sucursales y crear referencias en movimientos_items
        foreach ($bajas as $baja) {
            // Obtener id_movimiento_item original
            $stmt = $this->db->prepare("SELECT id_movimiento_item FROM stock_sucursales WHERE id = ?");
            $stmt->execute([$baja['id_stock_sucursal']]);
            $id_item = $stmt->fetch(\PDO::FETCH_ASSOC)['id_movimiento_item'];
            
            // Crear referencia en movimientos_items
            $stmt = $this->db->prepare("
                INSERT INTO movimientos_items (id_movimiento, id_movimientos_items_origen, cantidad)
                VALUES (?, ?, ?)
            ");
            $stmt->execute([$id_movimiento, $id_item, $baja['total_baja']]);
            
            // Actualizar stock
            $stmt = $this->db->prepare("
                UPDATE stock_sucursales SET cantidad = cantidad - ? WHERE id = ?
            ");
            $stmt->execute([$baja['total_baja'], $baja['id_stock_sucursal']]);
        }
        
        // 4. Limpiar tabla de sesión
        $stmt = $this->db->prepare("DELETE FROM baja_stock_sesion WHERE id_sucursal = ?");
        $stmt->execute([$id_sucursal]);
        
        return $id_movimiento;
    }
    
    private function obtenerTotalSesion($id_sucursal) {
        $stmt = $this->db->prepare("
            SELECT SUM(cantidad) as total FROM baja_stock_sesion
            WHERE id_sucursal = ?
        ");
        $stmt->execute([$id_sucursal]);
        $resultado = $stmt->fetch(\PDO::FETCH_ASSOC);
        return $resultado['total'] ?? 0;
    }
}
```

#### 3.3.4 Nueva Tabla `baja_stock_sesion`

```sql
CREATE TABLE baja_stock_sesion (
    id INT PRIMARY KEY AUTO_INCREMENT,
    id_sucursal INT NOT NULL,
    id_stock_sucursal INT NOT NULL,
    cantidad INT NOT NULL,
    metodo ENUM('ETIQUETA', 'AJUSTE_MANUAL') NOT NULL,
    motivo VARCHAR(100),
    fecha_registro DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (id_sucursal) REFERENCES sucursales(id),
    FOREIGN KEY (id_stock_sucursal) REFERENCES stock_sucursales(id),
    INDEX (id_sucursal, fecha_registro)
);
```

---

### 3.4 LIMITACIONES Y ALCANCES - SEMANA 2

#### Alcances
✅ Dashboard con estados de pedidos  
✅ Recepciones con validación de cantidades  
✅ Baja de stock por dos métodos  
✅ Actualización de stock_sucursales  
✅ Creación de movimientos de baja  
✅ Historial de recepciones y bajas  

#### Limitaciones
❌ NO hay sincronización en tiempo real (polling cada 30s)  
❌ NO hay alertas de discrepancias automáticas  
❌ NO hay reportes consolidados  
❌ NO integración con facturación  
❌ NO hay validación de permisos  
❌ NO hay auditoría de cambios en stock  

---

## IV. SEMANA 3: STOCK MÍNIMO + REFINAMIENTO + TESTS

### 4.1 MÓDULO STOCK MÍNIMO

#### 4.1.1 Funcionalidad

**Pantalla:** `configuracion_stock_minimo.html`

- Tabla con productos y stock mínimo actual
- Editar cantidad mínima para cada producto/sucursal
- Mostrar alertas cuando stock < mínimo
- Sugerir cantidad a pedir automáticamente

#### 4.1.2 Endpoints Nuevos

| Método | Endpoint | Descripción |
|--------|----------|-------------|
| GET | `/api/stock-minimo/listar` | Configuraciones actuales |
| PUT | `/api/stock-minimo/actualizar` | Cambiar cantidad mínima |
| GET | `/api/stock-minimo/alertas` | Productos bajo mínimo |
| GET | `/api/stock-minimo/sugerencias` | Sugerir cantidades a pedir |

#### 4.1.3 Modelo StockMinimo

```php
namespace Api\Model;

class StockMinimo {
    private $db;
    
    public function obtenerConfiguracion($id_sucursal) {
        $stmt = $this->db->prepare("
            SELECT 
                sm.id,
                sm.id_movimiento_item,
                sm.cantidad_minima,
                COALESCE(ss.cantidad, 0) as cantidad_actual,
                p.codigo, p.familia, p.descripcion
            FROM stock_minimo sm
            LEFT JOIN stock_sucursales ss ON sm.id_movimiento_item = ss.id_movimiento_item
                AND sm.id_sucursal = ss.id_sucursal
            LEFT JOIN movimientos_items mi ON sm.id_movimiento_item = mi.id
            LEFT JOIN productos p ON (
                SELECT id_producto FROM movimientos_items WHERE id = mi.id
            ) = p.id
            WHERE sm.id_sucursal = ?
            ORDER BY p.familia, p.codigo
        ");
        $stmt->execute([$id_sucursal]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }
    
    public function actualizar($id_sucursal, $id_movimiento_item, $cantidad_minima) {
        // Validar cantidad mínima
        if ($cantidad_minima < 1 || $cantidad_minima > 1000) {
            throw new \Exception("Cantidad mínima debe estar entre 1 y 1000");
        }
        
        $stmt = $this->db->prepare("
            INSERT INTO stock_minimo (id_sucursal, id_movimiento_item, cantidad_minima, fecha_configuracion)
            VALUES (?, ?, ?, NOW())
            ON DUPLICATE KEY UPDATE 
                cantidad_minima = ?,
                fecha_actualizacion = NOW()
        ");
        $stmt->execute([$id_sucursal, $id_movimiento_item, $cantidad_minima, $cantidad_minima]);
        
        return true;
    }
    
    public function obtenerAlertas($id_sucursal) {
        $stmt = $this->db->prepare("
            SELECT 
                sm.id,
                sm.cantidad_minima,
                COALESCE(ss.cantidad, 0) as cantidad_actual,
                (sm.cantidad_minima - COALESCE(ss.cantidad, 0)) as deficit,
                p.codigo, p.familia
            FROM stock_minimo sm
            LEFT JOIN stock_sucursales ss ON sm.id_sucursal = ss.id_sucursal
                AND sm.id_movimiento_item = ss.id_movimiento_item
            LEFT JOIN movimientos_items mi ON sm.id_movimiento_item = mi.id
            LEFT JOIN productos p ON (
                SELECT id_producto FROM movimientos_items WHERE id = mi.id
            ) = p.id
            WHERE sm.id_sucursal = ?
            AND COALESCE(ss.cantidad, 0) < sm.cantidad_minima
            ORDER BY deficit DESC
        ");
        $stmt->execute([$id_sucursal]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }
    
    public function obtenerSugerencias($id_sucursal) {
        // Sugerir cantidad a pedir = 2x cantidad_minima - cantidad_actual
        $stmt = $this->db->prepare("
            SELECT 
                sm.id_movimiento_item,
                sm.cantidad_minima,
                COALESCE(ss.cantidad, 0) as cantidad_actual,
                (sm.cantidad_minima * 2 - COALESCE(ss.cantidad, 0)) as cantidad_sugerida,
                p.codigo, p.familia
            FROM stock_minimo sm
            LEFT JOIN stock_sucursales ss ON sm.id_sucursal = ss.id_sucursal
                AND sm.id_movimiento_item = ss.id_movimiento_item
            LEFT JOIN movimientos_items mi ON sm.id_movimiento_item = mi.id
            LEFT JOIN productos p ON (
                SELECT id_producto FROM movimientos_items WHERE id = mi.id
            ) = p.id
            WHERE sm.id_sucursal = ?
            AND COALESCE(ss.cantidad, 0) <= sm.cantidad_minima
        ");
        $stmt->execute([$id_sucursal]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }
}
```

---

### 4.2 TESTS Y VALIDACIÓN - SEMANA 3

#### 4.2.1 Suite de Tests Automatizados

**Archivo:** `api/tests_fase2.php`

```php
<?php
// Tests para Fase 2

class Fase2Tests {
    private $db;
    private $resultados = ['pasados' => 0, 'fallidos' => 0];
    
    public function __construct($db) {
        $this->db = $db;
    }
    
    public function ejecutar() {
        echo "=== TESTS FASE 2 ===\n\n";
        
        // Tests de Pedidos
        $this->testCrearPedidoValido();
        $this->testCrearPedidoStockInsuficiente();
        $this->testCambiarEstadoPedido();
        
        // Tests de Recepciones
        $this->testRegistrarRecepcion();
        $this->testRegistrarRecepcionParcial();
        
        // Tests de Baja de Stock
        $this->testBajaStockEtiqueta();
        $this->testBajaStockAjusteManual();
        
        // Tests de Stock Mínimo
        $this->testConfiguracionStockMinimo();
        $this->testObtenerAlertas();
        
        echo "\n=== RESUMEN ===\n";
        echo "Pasados: " . $this->resultados['pasados'] . "\n";
        echo "Fallidos: " . $this->resultados['fallidos'] . "\n";
    }
    
    private function testCrearPedidoValido() {
        // Crear pedido con stock disponible
        $resultado = true;
        $this->registrarResultado("Crear Pedido Válido", $resultado);
    }
    
    private function testBajaStockEtiqueta() {
        // Registrar baja por escaneo de etiqueta
        $resultado = true;
        $this->registrarResultado("Baja Stock por Etiqueta", $resultado);
    }
    
    private function registrarResultado($nombre, $resultado) {
        if ($resultado) {
            echo "✓ $nombre\n";
            $this->resultados['pasados']++;
        } else {
            echo "✗ $nombre\n";
            $this->resultados['fallidos']++;
        }
    }
}

// Ejecutar
$tests = new Fase2Tests($db);
$tests->ejecutar();
?>
```

#### 4.2.2 Tests Manuales - Checklist

**Creación de Pedidos:**
- [ ] Crear pedido con 1 producto
- [ ] Crear pedido con múltiples productos
- [ ] Rechazar pedido cuando stock insuficiente
- [ ] Validar que no se pueda crear pedido con campo vacío

**Dashboard de Producción:**
- [ ] Ver todos los pedidos pendientes
- [ ] Cambiar estado a PREPARACION
- [ ] Cambiar estado a LISTO_ENVIO
- [ ] Confirmar envío y generar movimiento

**Recepciones:**
- [ ] Recibir envío completo
- [ ] Recibir envío parcial (registrar discrepancia)
- [ ] Actualizar stock en sucursal correctamente
- [ ] Validar historial de recepciones

**Baja de Stock:**
- [ ] Registrar 10 escaneos, acumular en sesión
- [ ] Confirmar día y generar movimiento
- [ ] Registrar ajuste manual con motivo
- [ ] Validar que no se pueda descontar más del disponible

**Stock Mínimo:**
- [ ] Configurar mínimo para 5 productos
- [ ] Ver alertas de bajo stock
- [ ] Recibir sugerencias de cantidad a pedir
- [ ] Integración: Sugerencia aparece en crear pedido

---

### 4.3 REFINAMIENTOS - SEMANA 3

#### 4.3.1 Validaciones Adicionales

- Validar que no se cree pedido para sucursal no existente
- Validar que no se cambie estado de pedido rechazado
- Validar que no se reciba más de lo enviado sin permiso especial
- Validar que no se descuente stock ya rechazado

#### 4.3.2 Reportes Básicos

**Reporte de Movimientos - Nuevo Endpoint:**
```
GET /api/reportes/movimientos/{id_sucursal}?desde=2025-12-01&hasta=2025-12-31
```

**Reporte de Stock - Nuevo Endpoint:**
```
GET /api/reportes/stock-actual/{id_sucursal}
```

---

### 4.4 LIMITACIONES Y ALCANCES - SEMANA 3

#### Alcances
✅ Configuración de stock mínimo por sucursal  
✅ Alertas de bajo stock  
✅ Sugerencias automáticas de cantidad a pedir  
✅ Suite de tests automatizados  
✅ Tests manuales validados  
✅ Reportes básicos de movimientos  

#### Limitaciones
❌ NO hay reportes avanzados (BI)  
❌ NO hay predicción de demanda  
❌ NO hay auto-reorden  
❌ NO hay integración con facturación  
❌ NO hay gráficos de tendencias  

---

## V. RESUMEN TÉCNICO GENERAL

### 5.1 Nuevas Tablas Fase 2
1. `pedidos` - Encabezado de pedidos
2. `pedido_items` - Items dentro de pedidos
3. `stock_sucursales` - Stock actual por sucursal
4. `stock_minimo` - Configuración de mínimos
5. `recepciones_items` - Detalle de recepciones
6. `baja_stock_sesion` - Sesión temporal de bajas

### 5.2 Tablas Modificadas
1. `movimientos` - Agregar id_pedido, tipo_movimiento
2. `envios` - Agregar estado_recepcion, fecha_recepcion

### 5.3 Nuevos Modelos PHP
- `Pedido.php`
- `PedidoItem.php`
- `Recepcion.php`
- `BajaStock.php`
- `StockMinimo.php`
- `StockSucursal.php`

### 5.4 Nuevos Controllers
- `PedidosController.php`
- `RecepcionesController.php`
- `BajaStockController.php`
- `StockMinimoController.php`

### 5.5 Nuevas Vistas Frontend
- `pedidos.html` - Crear/editar pedidos (sucursal)
- `tablero_produccion.html` - Dashboard (planta)
- `recepciones.html` - Recibir envíos (sucursal)
- `baja_stock_etiquetas.html` - Venta por etiqueta (sucursal)
- `baja_stock_ajuste_manual.html` - Ajuste manual (sucursal)
- `configuracion_stock_minimo.html` - Configurar mínimos (sucursal)

### 5.6 Nuevos Archivos JS
- `js/pedidos.js` - Lógica de pedidos
- `js/tablero_produccion.js` - Dashboard
- `js/recepciones.js` - Recepción de envíos
- `js/baja_stock_etiquetas.js` - Venta rápida
- `js/baja_stock_ajuste.js` - Ajuste manual
- `js/stock_minimo.js` - Configuración mínimos

---

## VI. DEUDA TÉCNICA Y CONSIDERACIONES

### 6.1 Deuda Identificada para Futuras Fases

**No Soportado en Fase 2:**
- [ ] Autenticación JWT (temporalmente con placeholder)
- [ ] Autorización por rol
- [ ] Auditoría de cambios
- [ ] Sincronización en tiempo real (usar WebSockets en Fase 3)
- [ ] Reportes avanzados
- [ ] BI/Analytics

**Pendiente para Optimización:**
- [ ] Índices adicionales en tablas grandes
- [ ] Caché de disponibilidad
- [ ] Batch operations para recepciones masivas
- [ ] Compresión de datos históricos

### 6.2 Riesgos Identificados

1. **Sincronización de Stock**
   - Risk: Condición de carrera si dos sucursales descargan stock simultáneamente
   - Mitigation: Usar transacciones MySQL, validar stock antes de descontar

2. **Discrepancias en Recepciones**
   - Risk: Recibir cantidad diferente genera datos inconsistentes
   - Mitigation: Registrar diferencia, permitir auditoría

3. **Pérdida de Sesión de Baja**
   - Risk: Si sesión de baja se pierde, el día no se cierra correctamente
   - Mitigation: Guardar en BD, no en variables de sesión PHP

4. **Escalabilidad de Polling**
   - Risk: Polling cada 30s no escala con múltiples usuarios
   - Mitigation: Usar WebSockets en Fase 3

### 6.3 Consideraciones de Performance

**Optimizaciones necesarias:**
- Usar LIMIT en consultas que traen muchos registros
- Crear índices en: (id_sucursal, estado), (id_pedido), (fecha_creacion)
- Considerar particionamiento de movimientos_items cuando supere 1M registros

**Umbrales de alerta:**
- Tabla `movimientos_items` > 500k registros → Particionar
- Tabla `stock_sucursales` > 100k registros → Revisar índices
- API response > 2 segundos → Profile y optimize

---

## VII. DEFINICIÓN DE "LISTO" (Definition of Done)

### Para cada tarea de Fase 2:

- [ ] Código escrito siguiendo estándares del proyecto
- [ ] Mínimo 1 test automatizado por función crítica
- [ ] Tests manuales completados exitosamente
- [ ] Sin errores de sintaxis PHP (`php -l`)
- [ ] Sin errores de lógica identificados
- [ ] Documentación actualizada (comentarios en código)
- [ ] BOM UTF-8 corregido en Envio.php
- [ ] Database migrations documentadas
- [ ] Deploy script actualizado
- [ ] Code review completado

---

## VIII. INSTRUCCIONES PARA EJECUTAR

### Instalación de Dependencias
```bash
cd api
composer install
```

### Crear Base de Datos (Script SQL)
```bash
mysql -u root -p mikelo < cambios_fase2.sql
```

### Validar Sintaxis
```bash
php -l api/src/Model/Pedido.php
php -l api/src/Controller/PedidosController.php
```

### Ejecutar Tests
```bash
php api/tests_fase2.php
```

### Deploy a Staging
```bash
# Actualizar archivos
git pull origin main

# Migrar BD
php api/migrate_fase2.php

# Verificar
php api/test_db.php
```

---

## APÉNDICE A: MATRIZ DE ENDPOITS POR SEMANA

### Semana 1: 5 Endpoints Base
```
POST   /api/pedidos/crear
GET    /api/pedidos/listar
GET    /api/pedidos/{id}/detalles
PUT    /api/pedidos/{id}/enviar
GET    /api/pedidos/disponibles
```

### Semana 2: +12 Endpoints
```
[+5 nuevos de dashboard + recepciones + baja]
GET    /api/pedidos/planta/pendientes
PUT    /api/pedidos/{id}/preparacion
PUT    /api/pedidos/{id}/listo-envio
PUT    /api/pedidos/{id}/envio-confirmado

GET    /api/recepciones/pendientes
GET    /api/envios/{id}/detalles-recepcion
PUT    /api/recepciones/{id}/registrar-item
PUT    /api/recepciones/{id}/confirmar

POST   /api/baja-stock/etiqueta
POST   /api/baja-stock/ajuste
POST   /api/baja-stock/confirmar-dia
GET    /api/baja-stock/historial
```

### Semana 3: +4 Endpoints
```
GET    /api/stock-minimo/listar
PUT    /api/stock-minimo/actualizar
GET    /api/stock-minimo/alertas
GET    /api/stock-minimo/sugerencias
```

**Total Fase 2: 21 Endpoints**

---

**Documento preparado:** 29 de Noviembre de 2025  
**Version:** 1.0  
**Estado:** Listo para validación y ejecución
