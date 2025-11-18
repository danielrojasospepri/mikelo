<?php
/**
 * Script para detectar caracteres no-ASCII en Envio.php
 */

$archivo = __DIR__ . '/src/Model/Envio.php';

echo "🔍 Analizando: $archivo\n\n";

$contenido = file_get_contents($archivo);
$lineas = explode("\n", $contenido);

$encontrados = [];
$lineasConProblemas = [];

foreach ($lineas as $numLinea => $linea) {
    // Buscar caracteres no-ASCII
    if (preg_match('/[^\x00-\x7F]/', $linea)) {
        $lineaReal = $numLinea + 1;
        
        // Extraer caracteres problemáticos
        preg_match_all('/[^\x00-\x7F]+/u', $linea, $matches);
        foreach ($matches[0] as $char) {
            if (!isset($encontrados[$char])) {
                $encontrados[$char] = [];
            }
            $encontrados[$char][] = $lineaReal;
        }
        
        $lineasConProblemas[] = [
            'num' => $lineaReal,
            'texto' => substr($linea, 0, 100)
        ];
    }
}

echo "📊 RESUMEN\n";
echo "=========\n";
echo "Total de caracteres no-ASCII únicos: " . count($encontrados) . "\n";
echo "Total de líneas con problemas: " . count($lineasConProblemas) . "\n\n";

if (count($encontrados) > 0) {
    echo "🔤 CARACTERES ENCONTRADOS\n";
    echo "========================\n";
    foreach ($encontrados as $char => $lineas) {
        $hex = bin2hex($char);
        $cantidad = count($lineas);
        $primerasLineas = implode(', ', array_slice($lineas, 0, 5));
        if ($cantidad > 5) {
            $primerasLineas .= "...";
        }
        echo sprintf("'%s' (hex: %s) - %d ocurrencias en líneas: %s\n", 
            $char, $hex, $cantidad, $primerasLineas);
    }
    
    echo "\n📝 PRIMERAS 10 LÍNEAS CON PROBLEMAS\n";
    echo "===================================\n";
    foreach (array_slice($lineasConProblemas, 0, 10) as $info) {
        echo "Línea {$info['num']}: " . trim($info['texto']) . "\n";
    }
} else {
    echo "✅ No se encontraron caracteres no-ASCII\n";
}
