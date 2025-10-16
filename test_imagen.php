<?php
// Script de prueba para verificar imagen en Hostinger
header('Content-Type: text/html; charset=utf-8');

echo "<h2>Test de imagen logo.png</h2>";

$rutaImagen = __DIR__ . '/img/logo.png';
$rutaRelativa = 'img/logo.png';

echo "<h3>Información del archivo:</h3>";
echo "<p><strong>Ruta absoluta:</strong> $rutaImagen</p>";
echo "<p><strong>Ruta relativa:</strong> $rutaRelativa</p>";

// Verificar si existe
if (file_exists($rutaImagen)) {
    echo "<p>✅ <strong>El archivo existe</strong></p>";
    
    // Información del archivo
    $size = filesize($rutaImagen);
    $permisos = substr(sprintf('%o', fileperms($rutaImagen)), -4);
    
    echo "<p><strong>Tamaño:</strong> " . number_format($size) . " bytes</p>";
    echo "<p><strong>Permisos:</strong> $permisos</p>";
    
    // Verificar si es imagen válida
    $imageInfo = getimagesize($rutaImagen);
    if ($imageInfo !== false) {
        echo "<p>✅ <strong>Es una imagen válida</strong></p>";
        echo "<p><strong>Dimensiones:</strong> {$imageInfo[0]} x {$imageInfo[1]} px</p>";
        echo "<p><strong>Tipo MIME:</strong> {$imageInfo['mime']}</p>";
        
        // Mostrar la imagen
        echo "<h3>Preview de la imagen:</h3>";
        echo "<img src='$rutaRelativa' alt='Logo' style='max-width: 200px; border: 1px solid #ccc;'>";
        
    } else {
        echo "<p>❌ <strong>ERROR: No es una imagen válida o está corrupta</strong></p>";
    }
    
} else {
    echo "<p>❌ <strong>ERROR: El archivo NO existe</strong></p>";
    
    // Verificar directorio
    $dirImagen = dirname($rutaImagen);
    if (is_dir($dirImagen)) {
        echo "<p>📁 El directorio existe: $dirImagen</p>";
        
        // Listar contenido del directorio
        $archivos = scandir($dirImagen);
        echo "<p><strong>Archivos en el directorio:</strong></p>";
        echo "<ul>";
        foreach ($archivos as $archivo) {
            if ($archivo != '.' && $archivo != '..') {
                echo "<li>$archivo</li>";
            }
        }
        echo "</ul>";
    } else {
        echo "<p>❌ El directorio tampoco existe: $dirImagen</p>";
    }
}

echo "<hr>";
echo "<p><em>Script creado para debugging en Hostinger</em></p>";
?>