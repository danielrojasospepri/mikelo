# GUÍA: Configuración de Stock Mínimo en Sucursales

## 🎯 Objetivo
Establecer niveles de stock que garanticen disponibilidad sin exceso de inventario.

---

## 📊 METODOLOGÍAS RECOMENDADAS

### Metodología 1: Basada en Rotación Histórica ⭐ (RECOMENDADA)

**Fórmula:**
```
Stock Mínimo = (Consumo Promedio Diario) × (Días entre Entregas) + Buffer
```

**Componentes:**
- **Consumo Promedio Diario:** Último mes ÷ 30 días
- **Días entre Entregas:** Típicamente 2-3 días
- **Buffer:** Protección contra variabilidad (10-20%)

**Ejemplo Práctico:**

```
Producto: Pan Salvado
Consumo mes pasado: 300 unidades
Consumo promedio diario: 300 ÷ 30 = 10 unidades/día
Días entre entregas: 3 días
Buffer: 20%

Stock Mínimo = (10 × 3) × 1.2 = 36 unidades
```

**Ventajas:**
- ✅ Basado en datos reales
- ✅ Adapta a patrones estacionales
- ✅ Reduce desabastecimiento
- ✅ Minimiza exceso

**Implementación en Sistema:**
```sql
-- Calcular automáticamente
SELECT 
  id_productos,
  ROUND(AVG(consumo_diario) * dias_entre_entregas * 1.2) as stock_minimo_sugerido
FROM (
  SELECT 
    id_productos,
    SUM(cnt) / 30 as consumo_diario,
    3 as dias_entre_entregas
  FROM movimientos_items
  WHERE fecha >= DATE_SUB(NOW(), INTERVAL 30 DAY)
  GROUP BY id_productos
) t;
```

---

### Metodología 2: Basada en Máximo Almacenamiento

**Fórmula:**
```
Stock Mínimo = Stock Máximo × Porcentaje (30-50%)
Stock Máximo = Capacidad Física de Almacén
```

**Ejemplo:**

```
Helado Limón:
Capacidad almacén: 100 cajas
Stock Máximo: 100 cajas
Stock Mínimo (40%): 40 cajas
Stock Máximo (100%): 100 cajas
Punto de Reorden: 40 cajas
```

**Ventajas:**
- ✅ Considera espacio físico
- ✅ Evita sobreabarrotamiento
- ✅ Fácil de entender

**Desventajas:**
- ❌ No considera rotación real
- ❌ Puede ser insuficiente en picos

---

### Metodología 3: Por Categoría de Producto

Diferentes productos requieren diferentes estrategias:

```
HELADOS (Alta rotación):
├── Stock Mínimo: 30-50 unidades
├── Plazo reorden: 2-3 días
└── Ejemplo: Si promedio = 20/día → Mín = 50-60

PASTELES (Media rotación):
├── Stock Mínimo: 10-20 unidades
├── Plazo reorden: 3-4 días
└── Ejemplo: Si promedio = 5/día → Mín = 20-25

INGREDIENTES (Baja rotación):
├── Stock Mínimo: 3-5 unidades
├── Plazo reorden: 5-7 días
└── Ejemplo: Si promedio = 1/día → Mín = 5-8
```

---

## 🛠️ IMPLEMENTACIÓN EN MIKELO

### Opción A: Manual (Actual)

**Proceso:**
1. Usuario accede a `config_stock_minimo.html`
2. Ingresa cantidad mínima por producto
3. Sistema valida (> 0, < máximo)
4. Guarda en tabla `stock_minimo`

**Ventaja:** Simple, flexible
**Desventaja:** Requiere experiencia del usuario

### Opción B: Sugerencias Automáticas (RECOMENDADO)

**Proceso:**
1. Sistema calcula sugerencia basada en histórico
2. Usuario ve: "Sugerencia: 45 unidades"
3. Puede aceptar o modificar
4. Guarda configuración

**Implementación:**

```php
// En ConfigStockMinimo.php

public function obtenerSugerenciaStockMinimo($id_producto, $id_sucursal, $diasEntreEntregas = 3) {
    try {
        // 1. Calcular consumo promedio diario (últimos 30 días)
        $stmt = $this->db->prepare("
            SELECT 
                COALESCE(SUM(cnt) / 30, 0) as consumo_promedio_diario
            FROM movimientos_items mi
            JOIN movimientos m ON m.id = mi.id_movimientos
            WHERE mi.id_productos = ?
              AND m.id_ubicacion_destino = ?
              AND m.fechaAlta >= DATE_SUB(NOW(), INTERVAL 30 DAY)
              AND m.id_ubicacion_origen = 1  -- Desde central
        ");
        $stmt->execute([$id_producto, $id_sucursal]);
        $consumo = $stmt->fetch(\PDO::FETCH_ASSOC);
        
        $consumoDiario = max((float)$consumo['consumo_promedio_diario'], 1); // Mínimo 1
        
        // 2. Aplicar fórmula
        $buffer = 1.2; // 20% de seguridad
        $sugerencia = ceil($consumoDiario * $diasEntreEntregas * $buffer);
        
        // 3. Validar límites sensatos
        $sugerencia = max(1, min($sugerencia, 500)); // Entre 1 y 500
        
        return [
            'consumo_diario' => round($consumoDiario, 2),
            'dias_entre_entregas' => $diasEntreEntregas,
            'buffer' => $buffer * 100 . '%',
            'sugerencia' => $sugerencia
        ];
    } catch (\Exception $e) {
        throw new \Exception("Error calculando sugerencia: " . $e->getMessage());
    }
}
```

### Opción C: Híbrida (RECOMENDADA PARA INICIO)

**Fase 1:**
- Usar Opción A (manual)
- Sucursales definen basado en experiencia

**Fase 2:**
- Agregar Opción B (sugerencias)
- Sistema propone, usuario valida

**Implementación:**
```javascript
// En config_stock_minimo.html
document.getElementById('btnCargarSugerencias').addEventListener('click', async () => {
    const sugerencias = await fetch('/api/stock-minimo/sugerencias?id_sucursal=' + sucursalId);
    sugerencias.forEach(sug => {
        document.getElementById(`input_${sug.id_producto}`).value = sug.sugerencia;
        // Mostrar badge: "Sugerencia del sistema"
    });
});
```

---

## 📋 TABLA COMPARATIVA DE ESTRATEGIAS

| Factor | Manual | Histórica | Máximo | Recomendación |
|--------|--------|-----------|--------|---------------|
| Esfuerzo setup | Bajo | Medio | Bajo | Híbrida (A+B) |
| Precisión | Media | Alta | Media | Histórica |
| Adapta cambios | No | Sí | No | Histórica |
| Facilidad uso | Alta | Alta | Alta | Manual con tips |
| Costo sistemas | Bajo | Medio | Bajo | Bajo |
| Evita desabasto | Medio | Alto | Medio | Alto |

---

## 🎯 RECOMENDACIÓN FINAL PARA MIKELO

### Para Heladería en Argentina

Considerando: Helados, Pasteles, Bandejas (productos perecederos)

**Recomendación:**

```
IMPLEMENTAR: Opción C (Híbrida)

CONFIGURACIÓN INICIAL:

1. Heladería (Ubicación 1 - Sucursal):
   ├── Helados: Stock mínimo = 50 unidades
   │   (Rotación diaria ~30-40, plazo 2-3 días)
   │   Fórmula: 35 × 3 × 1.2 = 126 (pero físicamente caben ~100)
   │   → Mínimo pragmático: 50
   │
   ├── Pasteles: Stock mínimo = 20 unidades
   │   (Rotación diaria ~10-15, plazo 3-4 días)
   │   → Mínimo: 20
   │
   └── Conos/Cucharitas: Stock mínimo = 500 unidades
       (Consumo diario ~200, plazo 5-7 días)
       → Mínimo: 500 (materias primas)

2. Planta Central:
   ├── Materias primas: Stock mínimo = 1000 unidades
   ├── Productos semiterminados: Stock mínimo = 500
   └── Stock de seguridad: Stock mínimo = 200

3. Sistema de Alertas:
   ├── Roja: Stock < 50% del mínimo
   ├── Amarilla: Stock < mínimo
   └── Verde: Stock >= mínimo × 1.5
```

### Interfaces UI Recomendadas

**Interfaz 1: Configuración Inicial (Setup)**
```
┌─────────────────────────────────────┐
│ Configurar Stock Mínimo             │
├─────────────────────────────────────┤
│                                     │
│ Sucursal: [ ] Heladería María      │
│                                     │
│ ┌──────────────────────────────────┐│
│ │ Producto    │ Actual │ Sugerencia ││
│ ├──────────────────────────────────┤│
│ │ HELADO      │ 45    │ 50 ✓      ││
│ │ LIMON       │ 10    │ 42        ││
│ │ PASTEL      │ 8     │ 20        ││
│ └──────────────────────────────────┘│
│                                     │
│ [Usar Sugerencias] [Guardar]       │
└─────────────────────────────────────┘
```

**Interfaz 2: Monitoreo Diario**
```
┌─────────────────────────────────────┐
│ Estado de Stock - Heladería María   │
├─────────────────────────────────────┤
│                                     │
│ 🟢 HELADO: 75 (Mín: 50) OK         │
│ 🟡 LIMON: 48 (Mín: 50) BAJO        │
│ 🔴 PASTEL: 5 (Mín: 20) CRÍTICO     │
│                                     │
│ [Crear Pedido] [Detalles]          │
└─────────────────────────────────────┘
```

---

## 🔄 AJUSTES ESTACIONALES

Considerar cambios por temporada:

```
VERANO (Nov-Feb): Mayor consumo helados
├── Incrementar stock mínimo +30%
├── Aumentar frecuencia entregas
└── Ejemplo: HELADO mín: 50 → 65

INVIERNO (Jun-Aug): Menor consumo helados
├── Reducir stock mínimo -20%
├── Mantener entregas normales
└── Ejemplo: HELADO mín: 50 → 40

FERIADOS: Mayor volatilidad
├── Aumentar buffer +50%
├── Monitoreo diario
└── Personal disponible para reorden rápido
```

---

## 📊 MONITOREO Y AJUSTES

### Métricas a Trackear

```sql
-- Tasa de desabastecimiento
SELECT 
  COUNT(*) as veces_sin_stock,
  100 * COUNT(*) / 30 as porcentaje_mes
FROM pedidos
WHERE id_producto = ? AND cantidad_solicitada > stock_disponible;

-- Promedio de días de stock
SELECT AVG(dias_en_stock)
FROM (
  SELECT 
    (SUM(cantidad) / consumo_promedio_diario) as dias_en_stock
  FROM stock
  WHERE id_sucursal = ?
) t;

-- Exactitud del pronóstico
SELECT 
  COUNT(*) as total_pedidos,
  SUM(CASE WHEN estado = 'CANCELADO' THEN 1 ELSE 0 END) as cancelados,
  ROUND(100 * SUM(CASE WHEN estado = 'CANCELADO' THEN 1 ELSE 0 END) / COUNT(*)) 
    as tasa_cancelacion
FROM pedidos
WHERE id_sucursal = ? AND mes = DATE_TRUNC(NOW(), MONTH);
```

### Revisión Trimestral

```
Cada 3 meses:
1. Revisar consumo real vs proyectado
2. Ajustar stock mínimo según tendencias
3. Verificar exactitud de proyecciones
4. Actualizar buffer de seguridad
```

---

## ✅ CONCLUSIÓN

**Para Mikelo (Heladería):**

1. **Implementar Opción C (Híbrida) - Manual + Sugerencias**
2. **Usar fórmula histórica para sugerencias**
3. **Permitir override manual por experiencia local**
4. **Monitorear mensualmente**
5. **Ajustar por temporada**

**Inicio práctico:**
- Mes 1: Manual basado en experiencia
- Mes 2: Agregar sugerencias del sistema
- Mes 3+: Refinamiento y ajustes

