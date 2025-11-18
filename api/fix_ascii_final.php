<?php
/**
 * Script final para eliminar TODOS los caracteres no-ASCII
 */

$archivo = __DIR__ . '/src/Model/Envio.php';

echo "🔍 Leyendo archivo...\n";
$contenido = file_get_contents($archivo);
$original = $contenido;

// Reemplazos específicos de líneas problemáticas
$reemplazosEspecificos = [
    'INFORMACII"N DEL ENVIO' => 'INFORMACION DEL ENVIO',
    'INFORMACION DEL ENVIO' => 'INFORMACION DEL ENVIO', // Por si ya estaba parcialmente corregido
    'SOLUCII"N I"PTIMA' => 'SOLUCION OPTIMA',
    'SOLUCION OPTIMA' => 'SOLUCION OPTIMA',
];

echo "\n📝 Aplicando reemplazos específicos...\n";
foreach ($reemplazosEspecificos as $mal => $bien) {
    if (strpos($contenido, $mal) !== false) {
        $contenido = str_replace($mal, $bien, $contenido);
        echo "✓ '$mal' => '$bien'\n";
    }
}

// Ahora reemplazar CUALQUIER carácter no-ASCII que quede
echo "\n🔧 Limpiando caracteres no-ASCII restantes...\n";

$lineas = explode("\n", $contenido);
$cambios = 0;

foreach ($lineas as $i => $linea) {
    if (preg_match('/[^\x00-\x7F]/', $linea)) {
        $lineaOriginal = $linea;
        
        // Reemplazar caracteres no-ASCII comunes
        $linea = str_replace(
            ['Í', 'Ó', 'Ú', 'Á', 'É', 'í', 'ó', 'ú', 'á', 'é', 'ñ', 'Ñ', '"', '"'],
            ['I', 'O', 'U', 'A', 'E', 'i', 'o', 'u', 'a', 'e', 'n', 'N', '"', '"'],
            $linea
        );
        
        // Si aún quedan caracteres no-ASCII, reemplazarlos por '?'
        $linea = preg_replace('/[^\x00-\x7F]/', '', $linea);
        
        if ($linea !== $lineaOriginal) {
            $lineas[$i] = $linea;
            $cambios++;
        }
    }
}

$contenido = implode("\n", $lineas);

echo "✓ Limpiadas $cambios líneas\n";

if ($contenido !== $original) {
    echo "\n💾 Guardando archivo...\n";
    file_put_contents($archivo, $contenido);
    echo "✅ Archivo guardado\n\n";
} else {
    echo "\nℹ️  No hubo cambios\n\n";
}

echo "🔍 Verificando sintaxis PHP...\n";
exec('php -l ' . escapeshellarg($archivo), $output, $return);
echo implode("\n", $output) . "\n";

if ($return === 0) {
    echo "\n🔍 Verificando caracteres no-ASCII restantes...\n";
    $contenido = file_get_contents($archivo);
    $lineas = explode("\n", $contenido);
    $problemas = [];
    
    foreach ($lineas as $numLinea => $linea) {
        if (preg_match('/[^\x00-\x7F]/', $linea)) {
            $problemas[] = ($numLinea + 1) . ': ' . substr($linea, 0, 80);
        }
    }
    
    if (count($problemas) > 0) {
        echo "⚠️  Aún hay " . count($problemas) . " líneas con caracteres no-ASCII:\n";
        foreach (array_slice($problemas, 0, 10) as $problema) {
            echo "  $problema\n";
        }
    } else {
        echo "✅ ¡Archivo 100% ASCII puro!\n";
    }
} else {
    echo "\n❌ Error de sintaxis\n";
}
