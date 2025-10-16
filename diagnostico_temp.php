<?php
/**
 * Diagnóstico de permisos y escritura en carpeta temp/
 * Subir este archivo al servidor de producción para verificar permisos
 */

echo "<h1>🔍 Diagnóstico de Carpeta temp/</h1>";
echo "<pre>";
echo str_repeat("=", 70) . "\n\n";

// 1. Información del sistema
echo "📋 INFORMACIÓN DEL SISTEMA\n";
echo str_repeat("-", 70) . "\n";
echo "Fecha/Hora: " . date('Y-m-d H:i:s') . "\n";
echo "PHP Version: " . PHP_VERSION . "\n";
echo "Usuario PHP: " . get_current_user() . "\n";
echo "Directorio actual: " . __DIR__ . "\n\n";

// 2. Verificar carpeta temp
$dirTemp = __DIR__ . '/temp';
echo "📁 VERIFICACIÓN CARPETA temp/\n";
echo str_repeat("-", 70) . "\n";
echo "Ruta temp: $dirTemp\n";

if (file_exists($dirTemp)) {
    echo "✅ La carpeta temp/ EXISTE\n";
    
    if (is_dir($dirTemp)) {
        echo "✅ Es un directorio válido\n";
    } else {
        echo "❌ NO es un directorio (es un archivo?)\n";
    }
    
    if (is_readable($dirTemp)) {
        echo "✅ Tiene permisos de LECTURA\n";
    } else {
        echo "❌ NO tiene permisos de lectura\n";
    }
    
    if (is_writable($dirTemp)) {
        echo "✅ Tiene permisos de ESCRITURA\n";
    } else {
        echo "❌ NO tiene permisos de escritura\n";
    }
    
    // Mostrar permisos actuales
    $perms = fileperms($dirTemp);
    $info = decoct($perms & 0777);
    echo "📊 Permisos actuales: $info (octal)\n";
    
} else {
    echo "❌ La carpeta temp/ NO EXISTE\n";
    echo "🔧 Intentando crearla...\n";
    
    if (mkdir($dirTemp, 0755, true)) {
        echo "✅ Carpeta creada exitosamente con permisos 0755\n";
    } else {
        echo "❌ ERROR: No se pudo crear la carpeta\n";
        echo "Error: " . error_get_last()['message'] . "\n";
    }
}

echo "\n";

// 3. Test de escritura
echo "✍️ TEST DE ESCRITURA\n";
echo str_repeat("-", 70) . "\n";

$archivoTest = $dirTemp . '/test_' . time() . '.txt';
$contenidoTest = "Test de escritura - " . date('Y-m-d H:i:s');

try {
    $resultado = file_put_contents($archivoTest, $contenidoTest);
    
    if ($resultado !== false) {
        echo "✅ Escritura exitosa!\n";
        echo "   Archivo: $archivoTest\n";
        echo "   Bytes escritos: $resultado\n";
        
        // Verificar lectura
        if (file_exists($archivoTest)) {
            $leido = file_get_contents($archivoTest);
            echo "✅ Lectura exitosa: $leido\n";
            
            // Limpiar archivo de test
            if (unlink($archivoTest)) {
                echo "✅ Eliminación exitosa\n";
            } else {
                echo "⚠️ No se pudo eliminar el archivo de prueba\n";
            }
        } else {
            echo "❌ El archivo no existe después de escribirlo\n";
        }
        
    } else {
        echo "❌ Error al escribir el archivo\n";
        echo "Error: " . error_get_last()['message'] . "\n";
    }
    
} catch (Exception $e) {
    echo "❌ Excepción al escribir: " . $e->getMessage() . "\n";
}

echo "\n";

// 4. Listar archivos existentes en temp/
echo "📂 ARCHIVOS EN temp/\n";
echo str_repeat("-", 70) . "\n";

if (is_dir($dirTemp)) {
    $archivos = scandir($dirTemp);
    $archivos = array_diff($archivos, ['.', '..']);
    
    if (count($archivos) > 0) {
        echo "Total de archivos: " . count($archivos) . "\n\n";
        
        foreach ($archivos as $archivo) {
            $rutaCompleta = $dirTemp . '/' . $archivo;
            $size = filesize($rutaCompleta);
            $perms = substr(sprintf('%o', fileperms($rutaCompleta)), -4);
            $fecha = date('Y-m-d H:i:s', filemtime($rutaCompleta));
            
            echo "📄 $archivo\n";
            echo "   Tamaño: " . number_format($size) . " bytes\n";
            echo "   Permisos: $perms\n";
            echo "   Modificado: $fecha\n\n";
        }
    } else {
        echo "⚠️ La carpeta está vacía\n";
    }
} else {
    echo "❌ No se puede listar (no es un directorio)\n";
}

echo "\n";

// 5. Información de PHP sobre upload y temp
echo "⚙️ CONFIGURACIÓN PHP\n";
echo str_repeat("-", 70) . "\n";
echo "upload_tmp_dir: " . ini_get('upload_tmp_dir') . "\n";
echo "sys_temp_dir: " . sys_get_temp_dir() . "\n";
echo "open_basedir: " . (ini_get('open_basedir') ?: 'No configurado') . "\n";
echo "disable_functions: " . (ini_get('disable_functions') ?: 'Ninguna') . "\n";

echo "\n";

// 6. Recomendaciones
echo "💡 RECOMENDACIONES\n";
echo str_repeat("-", 70) . "\n";

if (!file_exists($dirTemp)) {
    echo "1. Crear manualmente la carpeta temp/ en el servidor\n";
    echo "2. Desde terminal/SSH ejecutar: mkdir " . $dirTemp . "\n";
}

if (file_exists($dirTemp) && !is_writable($dirTemp)) {
    echo "1. Cambiar permisos de la carpeta temp/\n";
    echo "2. Desde terminal/SSH ejecutar: chmod 755 " . $dirTemp . "\n";
    echo "   O para más permisos: chmod 775 " . $dirTemp . "\n";
    echo "3. Si usa Apache, verificar que el usuario www-data tenga acceso\n";
    echo "   chown www-data:www-data " . $dirTemp . "\n";
}

echo "\n";
echo str_repeat("=", 70) . "\n";
echo "✅ Diagnóstico completado\n";
echo "</pre>";
?>
