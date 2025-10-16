<?php
// Archivo de verificación de zona horaria - Subir a Hostinger
require_once 'api/comun.php';

header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Verificación de Zona Horaria - Sistema Mikelo</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        .success { color: green; font-weight: bold; }
        .warning { color: red; font-weight: bold; }
        .info { background: #f0f0f0; padding: 10px; margin: 10px 0; border-left: 4px solid #333; }
        table { border-collapse: collapse; margin: 20px 0; }
        th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
        th { background-color: #333; color: white; }
    </style>
</head>
<body>
    <h2>🕐 Verificación de Zona Horaria - Sistema Mikelo</h2>
    
    <?php
    try {
        $db = getDB();
        
        echo "<h3>Configuración de PHP</h3>";
        echo "<table>";
        echo "<tr><th>Parámetro</th><th>Valor</th></tr>";
        echo "<tr><td>Zona Horaria</td><td>" . date_default_timezone_get() . "</td></tr>";
        echo "<tr><td>Fecha y Hora Actual</td><td>" . date('Y-m-d H:i:s') . "</td></tr>";
        echo "<tr><td>Offset UTC</td><td>" . date('P') . "</td></tr>";
        echo "</table>";
        
        echo "<h3>Configuración de MySQL</h3>";
        $stmt = $db->query("
            SELECT 
                @@global.time_zone as global_tz, 
                @@session.time_zone as session_tz,
                NOW() as mysql_now,
                UTC_TIMESTAMP() as utc_now
        ");
        $mysql = $stmt->fetch(PDO::FETCH_ASSOC);
        
        echo "<table>";
        echo "<tr><th>Parámetro</th><th>Valor</th></tr>";
        echo "<tr><td>Zona Horaria Global</td><td>" . $mysql['global_tz'] . "</td></tr>";
        echo "<tr><td>Zona Horaria Sesión</td><td>" . $mysql['session_tz'] . "</td></tr>";
        echo "<tr><td>NOW()</td><td>" . $mysql['mysql_now'] . "</td></tr>";
        echo "<tr><td>UTC_TIMESTAMP()</td><td>" . $mysql['utc_now'] . "</td></tr>";
        echo "</table>";
        
        echo "<h3>Verificación de Sincronización</h3>";
        $phpTime = strtotime(date('Y-m-d H:i:s'));
        $mysqlTime = strtotime($mysql['mysql_now']);
        $diff = abs($phpTime - $mysqlTime);
        
        echo "<div class='info'>";
        if ($diff <= 2) {
            echo "<span class='success'>✓ CORRECTO:</span> PHP y MySQL están sincronizados (diferencia: {$diff} segundos)";
        } else {
            $diffHours = round($diff / 3600, 1);
            echo "<span class='warning'>⚠️ ADVERTENCIA:</span> Hay una diferencia de {$diffHours} horas entre PHP y MySQL";
        }
        echo "</div>";
        
        echo "<h3>Zona Horaria Esperada</h3>";
        echo "<div class='info'>";
        echo "<strong>Argentina (Buenos Aires):</strong> UTC-3<br>";
        echo "Offset esperado: -03:00<br>";
        echo "Zona horaria: America/Argentina/Buenos_Aires";
        echo "</div>";
        
        // Test de inserción
        echo "<h3>Test de Inserción</h3>";
        $db->exec("
            CREATE TEMPORARY TABLE IF NOT EXISTS test_tz (
                id INT AUTO_INCREMENT PRIMARY KEY,
                fecha_registro DATETIME DEFAULT CURRENT_TIMESTAMP
            )
        ");
        
        $db->exec("INSERT INTO test_tz () VALUES ()");
        $stmt = $db->query("SELECT fecha_registro FROM test_tz ORDER BY id DESC LIMIT 1");
        $test = $stmt->fetch(PDO::FETCH_ASSOC);
        
        echo "<div class='info'>";
        echo "<strong>Fecha actual PHP:</strong> " . date('Y-m-d H:i:s') . "<br>";
        echo "<strong>Fecha en MySQL (CURRENT_TIMESTAMP):</strong> " . $test['fecha_registro'] . "<br>";
        
        $insertDiff = abs(strtotime(date('Y-m-d H:i:s')) - strtotime($test['fecha_registro']));
        if ($insertDiff <= 2) {
            echo "<span class='success'>✓ Los registros se guardarán con la hora correcta</span>";
        } else {
            echo "<span class='warning'>⚠️ Los registros tendrán una diferencia de tiempo</span>";
        }
        echo "</div>";
        
    } catch (Exception $e) {
        echo "<div class='info'><span class='warning'>Error:</span> " . htmlspecialchars($e->getMessage()) . "</div>";
    }
    ?>
    
    <hr>
    <p><small>Sistema Mikelo - Gestión de Inventario | Generado el <?= date('Y-m-d H:i:s') ?></small></p>
</body>
</html>
