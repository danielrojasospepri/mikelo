<?php
/**
 * Test para verificar suma de cantidades en stock
 */

require_once 'api/comun.php';
require_once 'api/src/Model/StockDeposito.php';

try {
    $db = getDB();
    $stockModel = new \App\Model\StockDeposito($db);
    
    echo "🧪 Test: Suma de Cantidades en Stock\n";
    echo "============================================================\n\n";
    
    // Obtener stock agrupado
    $stock = $stockModel->obtenerStockAgrupado([]);
    
    if (empty($stock)) {
        echo "⚠️ No hay productos en stock\n";
        exit(0);
    }
    
    echo "📦 Productos en stock:\n";
    echo str_repeat("-", 80) . "\n";
    printf("%-10s %-30s %10s %15s %15s\n", 
        "Código", "Descripción", "Unidades", "Peso Bruto", "Peso Neto");
    echo str_repeat("-", 80) . "\n";
    
    $totalUnidades = 0;
    $totalPesoBruto = 0;
    $totalPesoNeto = 0;
    
    foreach ($stock as $producto) {
        printf("%-10s %-30s %10s %15s kg %15s kg\n",
            $producto['codigo'],
            substr($producto['descripcion'], 0, 30),
            $producto['total_unidades'],
            number_format($producto['total_peso_bruto'], 3),
            number_format($producto['total_peso_neto'], 3)
        );
        
        $totalUnidades += $producto['total_unidades'];
        $totalPesoBruto += $producto['total_peso_bruto'];
        $totalPesoNeto += $producto['total_peso_neto'];
    }
    
    echo str_repeat("-", 80) . "\n";
    printf("%-10s %-30s %10s %15s kg %15s kg\n",
        "", "TOTALES:",
        $totalUnidades,
        number_format($totalPesoBruto, 3),
        number_format($totalPesoNeto, 3)
    );
    echo str_repeat("-", 80) . "\n\n";
    
    // Verificar el cálculo para un producto específico
    if (count($stock) > 0) {
        $primerProducto = $stock[0];
        echo "🔍 Verificación detallada para: {$primerProducto['codigo']}\n";
        echo "------------------------------------------------------------\n";
        
        $stmt = $db->prepare("
            SELECT 
                mi.id,
                mi.cnt as cantidad,
                mi.cnt_peso as peso,
                c.nombre as contenedor
            FROM movimientos_items mi
            JOIN productos p ON p.id = mi.id_productos
            LEFT JOIN contenedores c ON c.id = mi.id_contenedor
            WHERE p.id = ?
            AND mi.id_movimientos_items_origen IS NULL
            AND NOT EXISTS (
                SELECT 1 FROM movimientos_items mi2 
                WHERE mi2.id_movimientos_items_origen = mi.id
            )
            AND NOT EXISTS (
                SELECT 1 FROM estados_items_movimientos eim
                JOIN estados e ON eim.id_estados = e.id
                WHERE eim.id_movimientos_items = mi.id
                AND e.nombre = 'BAJA'
            )
        ");
        $stmt->execute([$primerProducto['id_producto']]);
        $detalles = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo "Bandejas/Registros:\n";
        $sumaManual = 0;
        foreach ($detalles as $detalle) {
            echo "  - Bandeja #{$detalle['id']}: {$detalle['cantidad']} unidades, {$detalle['peso']} kg";
            if ($detalle['contenedor']) {
                echo " ({$detalle['contenedor']})";
            }
            echo "\n";
            $sumaManual += $detalle['cantidad'];
        }
        
        echo "\n";
        echo "Suma manual de cantidades: {$sumaManual}\n";
        echo "Total_unidades reportado: {$primerProducto['total_unidades']}\n";
        
        if ($sumaManual == $primerProducto['total_unidades']) {
            echo "✅ ¡Correcto! Las cantidades coinciden\n";
        } else {
            echo "❌ ERROR: Las cantidades NO coinciden\n";
        }
    }
    
    echo "\n✅ Test completado\n";
    echo "📝 Ahora usa SUM(mi.cnt) en lugar de COUNT(mi.id)\n";
    
} catch (Exception $e) {
    echo "❌ Error en test: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
}
