<?php
// Convertir imagen a base64 para uso alternativo
$rutaImagen = __DIR__ . '/img/logo.png';

if (file_exists($rutaImagen)) {
    $imageData = file_get_contents($rutaImagen);
    $base64 = base64_encode($imageData);
    $mimeType = mime_content_type($rutaImagen);
    
    echo "<!-- Imagen logo.png en base64 -->\n";
    echo "<!-- Usar como: src=\"data:$mimeType;base64,$base64\" -->\n";
    echo "<!-- Tamaño: " . strlen($base64) . " caracteres -->\n\n";
    
    echo "data:$mimeType;base64,$base64";
} else {
    echo "ERROR: No se encontró img/logo.png";
}
?>