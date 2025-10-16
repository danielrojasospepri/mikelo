<?php
$url = 'http://localhost/mikelo/api/envios/pdf';

$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HEADER, true);
curl_setopt($ch, CURLOPT_NOBODY, false);

$response = curl_exec($ch);
$headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
$header = substr($response, 0, $headerSize);
$body = substr($response, $headerSize);

echo "=== HEADERS ===\n";
echo $header;
echo "\n=== BODY (primeros 200 caracteres) ===\n";
echo substr($body, 0, 200);
echo "\n\n=== Content-Type ===\n";
echo curl_getinfo($ch, CURLINFO_CONTENT_TYPE);

curl_close($ch);
?>
