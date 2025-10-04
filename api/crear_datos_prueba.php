<?php
require __DIR__ . '/comun.php';

try {
    $db = getDB();
    
    // Verificar si hay datos
    echo "=== VERIFICACIÓN DE DATOS ===\n";
    
    $stmt = $db->query("SELECT COUNT(*) as total FROM movimientos");
    $total = $stmt->fetch(PDO::FETCH_ASSOC);
    echo "Total movimientos: " . $total['total'] . "\n";
    
    $stmt = $db->query("SELECT COUNT(*) as total FROM productos");
    $total = $stmt->fetch(PDO::FETCH_ASSOC);
    echo "Total productos: " . $total['total'] . "\n";
    
    $stmt = $db->query("SELECT COUNT(*) as total FROM ubicaciones");
    $total = $stmt->fetch(PDO::FETCH_ASSOC);
    echo "Total ubicaciones: " . $total['total'] . "\n";
    
    // Si no hay datos, crear algunos de prueba
    if ($total['total'] == 0) {
        echo "\n=== CREANDO DATOS DE PRUEBA ===\n";
        
        // Insertar ubicaciones
        $db->exec("INSERT INTO ubicaciones (id, nombre) VALUES 
            (1, 'Depósito Central'),
            (2, 'Sucursal Norte'),
            (3, 'Sucursal Sur')");
        
        // Insertar productos
        $db->exec("INSERT INTO productos (id, codigo, descripcion) VALUES 
            (1, '001', 'Helado de Vainilla 500g'),
            (2, '002', 'Helado de Chocolate 500g'),
            (3, '003', 'Helado de Fresa 500g')");
        
        // Insertar contenedores
        $db->exec("INSERT INTO contenedores (id, nombre, peso) VALUES 
            (1, 'Pote 500g', 0.1),
            (2, 'Pote 1kg', 0.2)");
        
        // Insertar estados
        $db->exec("INSERT INTO estados (id, nombre) VALUES 
            (1, 'NUEVO'),
            (2, 'ENVIADO'),
            (3, 'RECIBIDO'),
            (4, 'CANCELADO')");
        
        echo "Datos de prueba creados.\n";
    }
    
    // Crear un movimiento de prueba si no existe
    $stmt = $db->query("SELECT COUNT(*) as total FROM movimientos WHERE id_ubicacion_destino != 1");
    $envios = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($envios['total'] == 0) {
        echo "\n=== CREANDO ENVÍO DE PRUEBA ===\n";
        
        $db->beginTransaction();
        
        // Crear movimiento
        $stmt = $db->prepare("
            INSERT INTO movimientos (fechaAlta, id_ubicacion_origen, id_ubicacion_destino, usuario_alta)
            VALUES (NOW(), 1, 2, 'admin_test')
        ");
        $stmt->execute();
        $idMovimiento = $db->lastInsertId();
        
        // Crear items de movimiento
        $productos = [
            ['id_producto' => 1, 'cantidad' => 10, 'peso' => 5.5, 'contenedor' => 1],
            ['id_producto' => 2, 'cantidad' => 8, 'peso' => 4.2, 'contenedor' => 1],
            ['id_producto' => 3, 'cantidad' => 12, 'peso' => 6.1, 'contenedor' => 2]
        ];
        
        foreach ($productos as $producto) {
            $stmt = $db->prepare("
                INSERT INTO movimientos_items (
                    id_movimientos, id_productos, cnt, cnt_peso, id_contenedor
                ) VALUES (?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $idMovimiento,
                $producto['id_producto'],
                $producto['cantidad'],
                $producto['peso'],
                $producto['contenedor']
            ]);
            $idMovimientoItem = $db->lastInsertId();
            
            // Crear estado inicial
            $stmt = $db->prepare("
                INSERT INTO estados_items_movimientos (
                    id_estados, id_movimientos_items, fecha_alta, usuario_alta
                ) VALUES (1, ?, NOW(), 'admin_test')
            ");
            $stmt->execute([$idMovimientoItem]);
        }
        
        $db->commit();
        echo "Envío de prueba creado con ID: $idMovimiento\n";
    }
    
    echo "\n=== PROBANDO EXPORTACIÓN ===\n";
    echo "URL para probar PDF de detalle: http://localhost/mikelo/api/envios/1/pdf\n";
    echo "URL para probar Excel de detalle: http://localhost/mikelo/api/envios/1/excel\n";
    echo "URL para probar PDF de lista: http://localhost/mikelo/api/envios/pdf\n";
    echo "URL para probar Excel de lista: http://localhost/mikelo/api/envios/excel\n";
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>