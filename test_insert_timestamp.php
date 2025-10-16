<?php
require_once 'api/comun.php';

echo "<h3>Test de Registro de Fecha/Hora</h3>";

try {
    $db = getDB();
    
    // Crear tabla de prueba
    $db->exec("
        CREATE TEMPORARY TABLE test_timestamps (
            id INT AUTO_INCREMENT PRIMARY KEY,
            descripcion VARCHAR(100),
            fecha_php VARCHAR(50),
            fecha_mysql DATETIME DEFAULT CURRENT_TIMESTAMP
        )
    ");
    
    // Insertar registro con fecha de PHP
    $fechaPHP = date('Y-m-d H:i:s');
    $stmt = $db->prepare("
        INSERT INTO test_timestamps (descripcion, fecha_php) 
        VALUES ('Test de sincronización', ?)
    ");
    $stmt->execute([$fechaPHP]);
    
    // Leer el registro insertado
    $stmt = $db->query("SELECT * FROM test_timestamps ORDER BY id DESC LIMIT 1");
    $registro = $stmt->fetch(PDO::FETCH_ASSOC);
    
    echo "<strong>Resultados:</strong><br>";
    echo "Fecha desde PHP: " . $fechaPHP . "<br>";
    echo "Fecha registrada en MySQL (CURRENT_TIMESTAMP): " . $registro['fecha_mysql'] . "<br>";
    echo "Fecha PHP almacenada: " . $registro['fecha_php'] . "<br><br>";
    
    // Comparar
    $phpTime = strtotime($fechaPHP);
    $mysqlTime = strtotime($registro['fecha_mysql']);
    $diff = abs($phpTime - $mysqlTime);
    
    if ($diff <= 1) {
        echo "<span style='color: green;'>✓ Las fechas están sincronizadas (diferencia: {$diff} segundos)</span><br>";
    } else {
        echo "<span style='color: red;'>⚠️ Hay diferencia de {$diff} segundos</span><br>";
    }
    
    echo "<br><strong>Zona horaria actual de MySQL:</strong><br>";
    $stmt = $db->query("SELECT @@session.time_zone as tz, NOW() as now");
    $tz = $stmt->fetch(PDO::FETCH_ASSOC);
    echo "Time zone: " . $tz['tz'] . "<br>";
    echo "NOW(): " . $tz['now'] . "<br>";
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
?>
