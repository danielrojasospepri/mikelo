<?php
/**
 * Migración: Tracking de baja individual por bandeja
 * Agrega columnas dado_de_baja e id_movimiento_baja a recepcion_items
 * para evitar doble-baja de bandejas de productos por peso.
 *
 * Ejecutar: php ejecutar_migracion_bandejas.php
 */

require_once __DIR__ . '/api/comun.php';

try {
    $db = getDB();

    // Verificar si la columna ya existe
    $stmt = $db->query("SHOW COLUMNS FROM recepcion_items LIKE 'dado_de_baja'");
    if ($stmt->rowCount() > 0) {
        echo "✓ La columna dado_de_baja ya existe. No se requiere migración.\n";
        exit(0);
    }

    $db->exec("
        ALTER TABLE recepcion_items
          ADD COLUMN dado_de_baja TINYINT(1) NOT NULL DEFAULT 0
              COMMENT 'Indica si esta bandeja ya fue dada de baja en la sucursal',
          ADD COLUMN id_movimiento_baja INT NULL
              COMMENT 'ID del movimiento en stock_sucursal_movimientos que registró la baja',
          ADD INDEX idx_dado_de_baja (dado_de_baja)
    ");

    echo "✓ Migración ejecutada correctamente.\n";
    echo "  - Columna dado_de_baja (TINYINT DEFAULT 0) agregada a recepcion_items\n";
    echo "  - Columna id_movimiento_baja (INT NULL) agregada a recepcion_items\n";
    echo "  - Índice idx_dado_de_baja creado\n";

} catch (\PDOException $e) {
    echo "✗ Error: " . $e->getMessage() . "\n";
    exit(1);
}
