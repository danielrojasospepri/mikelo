<?php
/**
 * Script para corregir TODOS los caracteres mal codificados en Envio.php
 * Ejecutar: php api/fix_encoding_v2.php
 */

$archivo = __DIR__ . '/src/Model/Envio.php';

if (!file_exists($archivo)) {
    die("❌ Error: No se encuentra el archivo $archivo\n");
}

echo "🔍 Leyendo archivo...\n";
$contenido = file_get_contents($archivo);

// Array completo de reemplazos
$reemplazos = [
    // Acentos codificados como Ã + otro caracter
    'envÃ­o' => 'envio',
    'EnvÃ­o' => 'Envio',
    'ENVÃO' => 'ENVIO',
    'estÃ¡' => 'esta',
    'vÃ¡lidos' => 'validos',
    'vÃ¡lido' => 'valido',
    'InformaciÃ³n' => 'Informacion',
    'informaciÃ³n' => 'informacion',
    'DescripciÃ³n' => 'Descripcion',
    'GeneraciÃ³n' => 'Generacion',
    'ConfiguraciÃ³n' => 'Configuracion',
    'GestiÃ³n' => 'Gestion',
    'ENVÃOS' => 'ENVIOS',
    'EnvÃ­os' => 'Envios',
    'CÃ³digo' => 'Codigo',
    'TÃ­tulo' => 'Titulo',
    'nÃºmero' => 'numero',
    'RealizaciÃ³n' => 'Realizacion',
    'ValidaciÃ³n' => 'Validacion',
    'ExportaciÃ³n' => 'Exportacion',
    'NotificaciÃ³n' => 'Notificacion',
    'OperaciÃ³n' => 'Operacion',
    'PresentaciÃ³n' => 'Presentacion',
    'CreaciÃ³n' => 'Creacion',
    
    // Caracteres especiales con secuencias largas
    '├â┬¡' => 'i',
    '├³' => 'o',
    '├â┬³' => 'o',
    '├â┬®' => 'a',
    '├â┬í' => 'i',
    '├â┬ì' => 'I',
    '├â┬ó' => 'a',
    'ÔåÆ' => '=>',
    '├â┬¡' => 'i',
    
    // Palabras completas mal codificadas
    'ENV├â┬ìOS' => 'ENVIOS',
    'env├â┬¡o' => 'envio',
    'c├â┬³digo' => 'codigo',
    'c├³digo' => 'codigo',
    'Dep├â┬³sito' => 'Deposito',
    'autom├â┬íticamente' => 'automaticamente',
    'generaci├â┬³n' => 'generacion',
    'Configuraci├â┬³n' => 'Configuracion',
    'm├â┬¡nima' => 'minima',
    'est├â┬®' => 'esta',
    'vac├â┬¡o' => 'vacio',
    'inv├â┬ílido' => 'invalido',
    'cre├â┬³' => 'creo',
    'gener├â┬³' => 'genero',
    'espec├â┬¡fico' => 'especifico',
    'L├â┬¡nea' => 'Linea',
    
    // Acentos básicos individuales (por si acaso)
    'Ã¡' => 'a',
    'Ã©' => 'e',
    'Ã­' => 'i',
    'Ã³' => 'o',
    'Ãº' => 'u',
    'Ã±' => 'n',
    'Ã' => 'A',
    'Ã‰' => 'E',
    'Ã' => 'I',
    'Ã"' => 'O',
    'Ãš' => 'U',
];

echo "\n📝 Aplicando reemplazos...\n\n";

$totalCambios = 0;
foreach ($reemplazos as $mal => $bien) {
    $antes = substr_count($contenido, $mal);
    if ($antes > 0) {
        $contenido = str_replace($mal, $bien, $contenido);
        $despues = substr_count($contenido, $mal);
        $cambios = $antes - $despues;
        if ($cambios > 0) {
            echo "✓ '$mal' → '$bien' ($cambios ocurrencias)\n";
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
} else {
    echo "\n❌ Error de sintaxis. Revisar manualmente.\n";
}
