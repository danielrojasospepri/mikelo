<?php
/**
 * Script para eliminar TODOS los acentos y caracteres especiales UTF-8 de Envio.php
 * Convierte UTF-8 válido a ASCII puro para evitar problemas con mPDF
 */

$archivo = __DIR__ . '/src/Model/Envio.php';

echo "🔍 Leyendo archivo...\n";
$contenido = file_get_contents($archivo);

// Reemplazos de caracteres UTF-8 válidos a ASCII
$reemplazos = [
    // Vocales con acento (minúsculas)
    'á' => 'a',
    'é' => 'e',
    'í' => 'i',
    'ó' => 'o',
    'ú' => 'u',
    
    // Vocales con acento (mayúsculas)
    'Á' => 'A',
    'É' => 'E',
    'Í' => 'I',
    'Ó' => 'O',
    'Ú' => 'U',
    
    // Letra ñ
    'ñ' => 'n',
    'Ñ' => 'N',
    
    // Símbolos especiales
    '→' => '=>',     // Flecha a símbolo PHP
    '°' => 'deg',    // Grado a texto
    '"' => '"',      // Comilla tipográfica izquierda
    '"' => '"',      // Comilla tipográfica derecha
    '–' => '-',      // Guión largo
    '—' => '-',      // Guión más largo
    '…' => '...',    // Puntos suspensivos
    '' => '',       // Soft hyphen (invisible)
    '×' => 'x',      // Multiplicación
    '÷' => '/',      // División
];

echo "\n📝 Aplicando reemplazos...\n\n";

$totalCambios = 0;
foreach ($reemplazos as $utf8 => $ascii) {
    $antes = substr_count($contenido, $utf8);
    if ($antes > 0) {
        $contenido = str_replace($utf8, $ascii, $contenido);
        $despues = substr_count($contenido, $utf8);
        $cambios = $antes - $despues;
        if ($cambios > 0) {
            echo "✓ '$utf8' => '$ascii' ($cambios ocurrencias)\n";
            $totalCambios += $cambios;
        }
    }
}

echo "\n📊 Total de reemplazos: $totalCambios\n\n";

if ($totalCambios > 0) {
    echo "💾 Guardando archivo...\n";
    file_put_contents($archivo, $contenido);
    echo "✅ Archivo guardado\n\n";
} else {
    echo "ℹ️  No se encontraron caracteres para reemplazar\n\n";
}

echo "🔍 Verificando sintaxis PHP...\n";
exec('php -l ' . escapeshellarg($archivo), $output, $return);
echo implode("\n", $output) . "\n";

if ($return === 0) {
    echo "\n✅ ¡Todo correcto!\n";
    
    // Verificar si quedan caracteres no-ASCII
    echo "\n🔍 Verificando caracteres restantes...\n";
    $contenido = file_get_contents($archivo);
    $lineas = explode("\n", $contenido);
    $problemas = 0;
    
    foreach ($lineas as $numLinea => $linea) {
        if (preg_match('/[^\x00-\x7F]/', $linea)) {
            $problemas++;
            if ($problemas <= 5) {
                echo "  Línea " . ($numLinea + 1) . ": " . substr($linea, 0, 80) . "\n";
            }
        }
    }
    
    if ($problemas > 0) {
        echo "\n⚠️  Aún hay $problemas líneas con caracteres no-ASCII\n";
    } else {
        echo "✅ No quedan caracteres no-ASCII\n";
    }
} else {
    echo "\n❌ Error de sintaxis. Revisar manualmente.\n";
}
