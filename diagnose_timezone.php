<?php
require_once 'api/comun.php';

try {
    echo "=== DIAGNÓSTICO DE ZONA HORARIA ===\n\n";
    
    // 1. Zona horaria de PHP
    echo "1. Configuración de PHP:\n";
    echo "   - Zona horaria: " . date_default_timezone_get() . "\n";
    echo "   - Fecha/hora PHP: " . date('Y-m-d H:i:s') . "\n\n";
    
    // 2. Zona horaria de MySQL
    $db = getDB();
    
    echo "2. Configuración de MySQL:\n";
    
    // Verificar zona horaria del sistema MySQL
    $stmt = $db->query("SELECT @@system_time_zone as system_tz, @@time_zone as session_tz");
    $timezones = $stmt->fetch(PDO::FETCH_ASSOC);
    echo "   - Sistema MySQL: " . $timezones['system_tz'] . "\n";
    echo "   - Sesión MySQL: " . $timezones['session_tz'] . "\n";
    
    // Verificar fecha/hora actual en MySQL
    $stmt = $db->query("SELECT NOW() as mysql_now, UTC_TIMESTAMP() as mysql_utc");
    $times = $stmt->fetch(PDO::FETCH_ASSOC);
    echo "   - NOW() MySQL: " . $times['mysql_now'] . "\n";
    echo "   - UTC MySQL: " . $times['mysql_utc'] . "\n\n";
    
    // 3. Comparar con un INSERT de prueba
    echo "3. Prueba de inserción:\n";
    
    $fechaPHP = date('Y-m-d H:i:s');
    echo "   - Fecha PHP: " . $fechaPHP . "\n";
    
    // Crear tabla de prueba temporal
    $db->exec("CREATE TEMPORARY TABLE test_timezone (
        id INT AUTO_INCREMENT PRIMARY KEY,
        fecha_php VARCHAR(50),
        fecha_mysql DATETIME DEFAULT CURRENT_TIMESTAMP
    )");
    
    // Insertar registro de prueba
    $stmt = $db->prepare("INSERT INTO test_timezone (fecha_php) VALUES (?)");
    $stmt->execute([$fechaPHP]);
    
    // Leer el registro insertado
    $stmt = $db->query("SELECT * FROM test_timezone ORDER BY id DESC LIMIT 1");
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    echo "   - Fecha guardada en DB: " . $result['fecha_mysql'] . "\n";
    echo "   - Diferencia: " . (strtotime($result['fecha_mysql']) - strtotime($fechaPHP)) . " segundos\n\n";
    
    // 4. Recomendación
    echo "4. SOLUCIÓN:\n";
    if ($timezones['session_tz'] !== '+00:00' && $timezones['session_tz'] !== 'SYSTEM') {
        echo "   MySQL ya tiene zona horaria configurada.\n";
    } else {
        echo "   Necesitas configurar la zona horaria de MySQL en la conexión.\n";
        echo "   Agrega esto al archivo comun.php después de la conexión:\n";
        echo "   \$db->exec(\"SET time_zone = '-03:00'\");\n";
    }
    
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}
?>