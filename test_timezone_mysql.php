<?php
require_once 'api/comun.php';

echo "<h3>Diagnóstico de Zona Horaria</h3>";

// Zona horaria de PHP
echo "<strong>PHP:</strong><br>";
echo "Zona horaria: " . date_default_timezone_get() . "<br>";
echo "Fecha y hora actual: " . date('Y-m-d H:i:s') . "<br><br>";

// Zona horaria de MySQL
try {
    $db = getDB();
    
    echo "<strong>MySQL:</strong><br>";
    
    // Verificar zona horaria global
    $stmt = $db->query("SELECT @@global.time_zone as global_tz, @@session.time_zone as session_tz");
    $tz = $stmt->fetch(PDO::FETCH_ASSOC);
    echo "Zona horaria global: " . $tz['global_tz'] . "<br>";
    echo "Zona horaria de sesión: " . $tz['session_tz'] . "<br>";
    
    // Verificar hora actual de MySQL
    $stmt = $db->query("SELECT NOW() as mysql_now, UTC_TIMESTAMP() as utc_now");
    $time = $stmt->fetch(PDO::FETCH_ASSOC);
    echo "NOW(): " . $time['mysql_now'] . "<br>";
    echo "UTC_TIMESTAMP(): " . $time['utc_now'] . "<br><br>";
    
    // Comparación
    echo "<strong>Comparación:</strong><br>";
    $phpTime = new DateTime('now', new DateTimeZone('America/Argentina/Buenos_Aires'));
    $mysqlTime = new DateTime($time['mysql_now']);
    
    $diff = $phpTime->getTimestamp() - $mysqlTime->getTimestamp();
    $diffHours = $diff / 3600;
    
    echo "Diferencia: " . abs($diffHours) . " horas<br>";
    
    if (abs($diffHours) > 0.5) {
        echo "<span style='color: red;'>⚠️ Hay una diferencia significativa entre PHP y MySQL</span><br>";
    } else {
        echo "<span style='color: green;'>✓ PHP y MySQL están sincronizados</span><br>";
    }
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
?>
