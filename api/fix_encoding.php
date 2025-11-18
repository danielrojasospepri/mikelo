<?php
/**
 * Script para corregir la codificacion incorrecta de acentos en Envio.php
 */

$archivo = __DIR__ . '/src/Model/Envio.php';

// Leer el contenido del archivo
$contenido = file_get_contents($archivo);

// Mapeo de caracteres mal codificados a correctos
$reemplazos = [
    // Acentos comunes
    'GestiÃ³n' => 'Gestion',
    'ENVÃOS' => 'ENVIOS',
    'DescripciÃ³n' => 'Descripcion',
    'GeneraciÃ³n' => 'Generacion',
    'EnvÃ­os' => 'Envios',
    'EnvÃ­o' => 'Envio',
    'CÃ³digo' => 'Codigo',
    'informaciÃ³n' => 'informacion',
    'TÃ­tulo' => 'Titulo',
    'nÃºmero' => 'numero',
    'RealizaciÃ³n' => 'Realizacion',
    
    // Caracteres especiales mal codificados
    '├â┬¡' => 'i',
    '├³' => 'o',
    '├â┬³' => 'o',
    '├â┬®' => 'a',
    '├â┬í' => 'i',
    '├â┬ì' => 'I',
    '├â┬ó' => 'a',
    'ÔåÆ' => '=>',
    
    // Reemplazos directos
    'ENV├â┬ìOS' => 'ENVIOS',
    'env├â┬¡o' => 'envio',
    'c├â┬│digo' => 'codigo',
    'c├│digo' => 'codigo',
    'Dep├â┬│sito' => 'Deposito',
    'autom├â┬íticamente' => 'automaticamente',
    'generaci├â┬│n' => 'generacion',
    'Configuraci├â┬│n' => 'Configuracion',
    'm├â┬¡nima' => 'minima',
    'est├â┬®' => 'esta',
    'vac├â┬¡o' => 'vacio',
    'inv├â┬ílido' => 'invalido',
    'cre├â┬│' => 'creo',
    'gener├â┬│' => 'genero',
    'espec├â┬¡fico' => 'especifico',
    'L├â┬¡nea' => 'Linea',
];

// Aplicar reemplazos
foreach ($reemplazos as $mal => $bien) {
    $antes = substr_count($contenido, $mal);
    $contenido = str_replace($mal, $bien, $contenido);
    $despues = substr_count($contenido, $mal);
    $cambios = $antes - $despues;
    if ($cambios > 0) {
        echo "✓ Reemplazado '$mal' → '$bien' ($cambios ocurrencias)\n";
    }
}

// Guardar sin BOM
file_put_contents($archivo, $contenido);

echo "\n✅ Archivo corregido: $archivo\n";
echo "Verificando sintaxis...\n";

// Verificar sintaxis
exec('php -l ' . escapeshellarg($archivo), $output, $return);
echo implode("\n", $output) . "\n";

if ($return === 0) {
    echo "\n✅ Sintaxis PHP correcta!\n";
} else {
    echo "\n❌ Error de sintaxis PHP\n";
}
