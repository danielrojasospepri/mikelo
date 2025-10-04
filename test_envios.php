<?php
require_once 'api/comun.php';

try {
    $db = getDB();

    // Verificar productos disponibles para envío
    $query = "
        SELECT 
            mi.id as id_movimiento_item,
            p.id as id_producto,
            p.codigo,
            p.descripcion,
            mi.cnt,
            mi.cnt_peso,
            c.nombre as contenedor,
            em.id_estados
        FROM movimientos_items mi
        INNER JOIN productos p ON mi.id_productos = p.id
        LEFT JOIN contenedores c ON mi.id_contenedor = c.id
        INNER JOIN estados_items_movimientos em ON em.id_movimientos_items = mi.id
        WHERE mi.id_movimientos_items_origen IS NULL
        AND NOT EXISTS (
            SELECT 1 FROM movimientos_items mi2 
            WHERE mi2.id_movimientos_items_origen = mi.id
        )
        AND em.id_estados = 1
        LIMIT 5
    ";

    $stmt = $db->prepare($query);
    $stmt->execute();
    $productos = $stmt->fetchAll();

    echo "Productos disponibles para envío:\n";
    foreach ($productos as $producto) {
        echo "ID: {$producto['id_movimiento_item']}, Código: {$producto['codigo']}, Descripción: {$producto['descripcion']}, Estado: {$producto['id_estados']}\n";
    }

    // Crear un envío de prueba
    if (count($productos) > 0) {
        echo "\nCreando envío de prueba...\n";
        
        // Obtener ubicaciones
        $ubicacionesQuery = "SELECT id, nombre FROM ubicaciones WHERE id != 1 LIMIT 1";
        $ubicacionesStmt = $db->prepare($ubicacionesQuery);
        $ubicacionesStmt->execute();
        $destino = $ubicacionesStmt->fetch();
        
        if ($destino) {
            // Crear movimiento
            $insertMovimiento = "
                INSERT INTO movimientos (id_ubicacion_origen, id_ubicacion_destino, usuario_alta, fechaAlta) 
                VALUES (1, ?, 'usuario_temporal', NOW())
            ";
            $stmtMovimiento = $db->prepare($insertMovimiento);
            $stmtMovimiento->execute([$destino['id']]);
            $movimientoId = $db->lastInsertId();
            
            echo "Movimiento creado con ID: $movimientoId\n";
            
            // Agregar el primer producto al envío
            $producto = $productos[0];
            $insertItem = "
                INSERT INTO movimientos_items (id_movimientos, id_productos, id_movimientos_items_origen, cnt, cnt_peso, id_contenedor)
                VALUES (?, ?, ?, ?, ?, ?)
            ";
            $stmtItem = $db->prepare($insertItem);
            $stmtItem->execute([
                $movimientoId,
                $producto['id_producto'], // Usar el ID real del producto
                $producto['id_movimiento_item'],
                $producto['cnt'],
                $producto['cnt_peso'],
                null
            ]);
            $itemId = $db->lastInsertId();
            
            echo "Item agregado con ID: $itemId\n";
            
            // Crear estado inicial (NUEVO = 1)
            $insertEstado = "
                INSERT INTO estados_items_movimientos (id_movimientos_items, id_estados, usuario_alta, fecha_alta)
                VALUES (?, 1, 'usuario_temporal', NOW())
            ";
            $stmtEstado = $db->prepare($insertEstado);
            $stmtEstado->execute([$itemId]);
            
            echo "Estado inicial creado (NUEVO)\n";
            echo "Envío creado exitosamente!\n";
            echo "Para probar: ve a envios.html y verifica que el envío aparece como NUEVO\n";
        } else {
            echo "No se encontraron ubicaciones de destino\n";
        }
    } else {
        echo "No hay productos disponibles para crear un envío de prueba\n";
    }

} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>