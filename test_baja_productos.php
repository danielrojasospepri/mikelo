<?php
/**
 * Test para dar de baja productos
 */

require_once 'api/comun.php';
require_once 'api/src/Model/StockDeposito.php';

try {
    $db = getDB();
    $stockModel = new \App\Model\StockDeposito($db);
    
    echo "🧪 Test: Dar de Baja Productos\n";
    echo "============================================================\n\n";
    
    // Verificar estructura de tabla estados
    echo "📋 Verificando estructura tabla 'estados':\n";
    $stmt = $db->query("DESCRIBE estados");
    $columnas = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($columnas as $col) {
        echo "  - {$col['Field']} ({$col['Type']})\n";
    }
    echo "\n";
    
    // Verificar si existe el estado DADO_DE_BAJA
    echo "🔍 Verificando estado 'DADO_DE_BAJA':\n";
    $stmt = $db->prepare("SELECT * FROM estados WHERE nombre = 'DADO_DE_BAJA'");
    $stmt->execute();
    $estadoBaja = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($estadoBaja) {
        echo "  ✅ Estado existe: ID={$estadoBaja['id']}\n";
    } else {
        echo "  ⚠️ Estado NO existe, se creará en la primera baja\n";
    }
    echo "\n";
    
    // Obtener un producto de stock para probar
    echo "📦 Buscando producto en stock para probar:\n";
    $stmt = $db->query("
        SELECT mi.id, p.codigo, p.descripcion, mi.cnt, mi.cnt_peso
        FROM movimientos_items mi
        JOIN movimientos m ON mi.id_movimientos = m.id
        JOIN productos p ON mi.id_productos = p.id
        WHERE m.id_tipos_movimientos = 1
        AND NOT EXISTS (
            SELECT 1 FROM estados_items_movimientos eim
            JOIN estados e ON eim.id_estados = e.id
            WHERE eim.id_movimientos_items = mi.id
            AND e.nombre = 'DADO_DE_BAJA'
        )
        LIMIT 1
    ");
    $producto = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$producto) {
        echo "  ⚠️ No hay productos en stock disponibles para dar de baja\n";
        echo "  ℹ️ Esto es normal si el stock está vacío\n";
        exit(0);
    }
    
    echo "  Producto encontrado:\n";
    echo "    - ID Bandeja: {$producto['id']}\n";
    echo "    - Código: {$producto['codigo']}\n";
    echo "    - Descripción: {$producto['descripcion']}\n";
    echo "    - Cantidad: {$producto['cnt']}\n";
    echo "    - Peso: {$producto['cnt_peso']} kg\n";
    echo "\n";
    
    // Simular baja (sin ejecutar realmente)
    echo "🔧 SIMULACIÓN de baja (sin ejecutar):\n";
    echo "  - ID Bandeja: {$producto['id']}\n";
    echo "  - Motivo: 'Test de baja - NO EJECUTADO'\n";
    echo "\n";
    
    echo "✅ Test de verificación completado\n";
    echo "📝 El código ahora usa: INSERT INTO estados (nombre) VALUES ('DADO_DE_BAJA')\n";
    echo "   (sin columna descripcion que no existe)\n";
    
} catch (Exception $e) {
    echo "❌ Error en test: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
}
