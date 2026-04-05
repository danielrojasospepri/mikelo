---
applyTo: "api/src/Controller/EnvioController.php,api/src/Controller/StockDepositoController.php,api/src/Controller/StockSucursalController.php"
---

# Skill: Exportaciones PDF y Excel

## Librerías Utilizadas

| Librería | Uso | Instalación |
|----------|-----|-------------|
| **mPDF** | Generación de PDFs | `composer require mpdf/mpdf` |
| **PHPSpreadsheet** | Generación de Excel | `composer require phpoffice/phpspreadsheet` |

Ambas están listadas en `api/composer.json` y disponibles en `api/vendor/`.

---

## Exportar a PDF con mPDF

### Patrón Básico

```php
use Mpdf\Mpdf;

function generarPDF(Response $response, string $html, string $nombreArchivo): Response {
    $mpdf = new Mpdf([
        'mode'          => 'utf-8',
        'format'        => 'A4',
        'margin_top'    => 15,
        'margin_bottom' => 15,
        'margin_left'   => 15,
        'margin_right'  => 15,
    ]);

    $mpdf->SetTitle($nombreArchivo);
    $mpdf->WriteHTML($html);

    $pdfContent = $mpdf->Output('', 'S');  // 'S' = devolver como string

    $response->getBody()->write($pdfContent);
    return $response
        ->withHeader('Content-Type', 'application/pdf')
        ->withHeader('Content-Disposition', "attachment; filename=\"{$nombreArchivo}.pdf\"");
}
```

### HTML Template para PDF

```php
function buildHTMLReport(string $titulo, array $datos): string {
    $filas = '';
    foreach ($datos as $row) {
        $filas .= "<tr>
            <td>{$row['codigo']}</td>
            <td>{$row['descripcion']}</td>
            <td>{$row['cantidad']}</td>
        </tr>";
    }

    return "
    <html>
    <head>
    <style>
        body { font-family: Arial, sans-serif; font-size: 11px; }
        h1   { font-size: 16px; text-align: center; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #ccc; padding: 4px 6px; }
        th { background: #f0f0f0; font-weight: bold; }
        tr:nth-child(even) { background: #f9f9f9; }
    </style>
    </head>
    <body>
        <h1>{$titulo}</h1>
        <p>Generado: " . date('d/m/Y H:i') . "</p>
        <table>
            <thead>
                <tr><th>Código</th><th>Descripción</th><th>Cantidad</th></tr>
            </thead>
            <tbody>{$filas}</tbody>
        </table>
    </body>
    </html>";
}
```

### Endpoint para descargar PDF

```php
$app->get('/envios/{id}/pdf', function (Request $request, Response $response, $args) use ($db) {
    $controller = new EnvioController($db);
    return $controller->exportarPDF($request, $response, $args);
})->add(new AuthMiddleware($db));
```

```php
// En EnvioController.php
public function exportarPDF(Request $request, Response $response, $args): Response {
    $id    = (int)$args['id'];
    $model = new Envio($this->db);
    $envio = $model->obtener($id);
    if (!$envio) {
        return responseJson($response, ['success' => false, 'error' => 'Envío no encontrado'], 404);
    }

    $html = buildHTMLReport("Envío #{$id}", $envio['items']);
    return generarPDF($response, $html, "envio_{$id}");
}
```

---

## Exportar a Excel con PHPSpreadsheet

### Patrón Básico

```php
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

function generarExcel(Response $response, array $datos, string $nombreArchivo): Response {
    $spreadsheet = new Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();
    $sheet->setTitle('Datos');

    // Headers
    $headers = ['Código', 'Descripción', 'Cantidad', 'Familia'];
    foreach ($headers as $col => $header) {
        $sheet->setCellValueByColumnAndRow($col + 1, 1, $header);
        // Negrita en header
        $sheet->getStyleByColumnAndRow($col + 1, 1)->getFont()->setBold(true);
    }

    // Datos
    foreach ($datos as $rowNum => $row) {
        $sheet->setCellValueByColumnAndRow(1, $rowNum + 2, $row['codigo']);
        $sheet->setCellValueByColumnAndRow(2, $rowNum + 2, $row['descripcion']);
        $sheet->setCellValueByColumnAndRow(3, $rowNum + 2, $row['cantidad']);
        $sheet->setCellValueByColumnAndRow(4, $rowNum + 2, $row['familia'] ?? '-');
    }

    // Autoajustar columnas
    foreach (range('A', 'D') as $col) {
        $sheet->getColumnDimension($col)->setAutoSize(true);
    }

    // Generar y devolver
    $writer = new Xlsx($spreadsheet);
    ob_start();
    $writer->save('php://output');
    $excelContent = ob_get_clean();

    $response->getBody()->write($excelContent);
    return $response
        ->withHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet')
        ->withHeader('Content-Disposition', "attachment; filename=\"{$nombreArchivo}.xlsx\"");
}
```

---

## Frontend — Botón de Descarga

### Descarga directa (link)

```javascript
// PDF
function descargarPDF(idEnvio) {
    // Construir URL con token para autenticación
    const token = MikeloAuth.getToken();
    const url = `${MikeloAuth.API_BASE}/envios/${idEnvio}/pdf?token=${token}`;
    window.open(url, '_blank');
}

// Excel
function descargarExcel(idEnvio) {
    const token = MikeloAuth.getToken();
    const url = `${MikeloAuth.API_BASE}/envios/${idEnvio}/excel?token=${token}`;
    window.open(url, '_blank');
}
```

### Descarga via fetch (para endpoints con auth por Bearer)

```javascript
async function descargarArchivo(url, nombreArchivo, mimeType) {
    const resp = await MikeloAuth.fetch(url);
    if (!resp || !resp.ok) {
        Swal.fire('Error', 'No se pudo generar el archivo', 'error');
        return;
    }

    const blob = await resp.blob();
    const urlBlob = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = urlBlob;
    a.download = nombreArchivo;
    a.click();
    URL.revokeObjectURL(urlBlob);
}

// Uso
await descargarArchivo(
    `/envios/${id}/excel`,
    `envio_${id}.xlsx`,
    'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
);
```

---

## Exportaciones Disponibles en el Sistema

| Módulo | PDF | Excel | Estado |
|--------|-----|-------|--------|
| Stock Depósito | ✅ | ✅ | Implementado |
| Envíos (listado) | ✅ | ✅ | Implementado |
| Envío (detalle/remito) | ✅ | - | Implementado |
| Códigos de barras contenedores | ✅ | - | Implementado |
| Stock Sucursal | Planificado | Planificado | Pendiente |
| Historial de movimientos | Planificado | Planificado | Pendiente |

---

## Remito de Envío

El remito es un PDF de detalle del envío con formato listo para imprimir:

```php
// Genera PDF con logo, datos del envío y tabla de items
GET /envios/{id}/remito    → Retorna application/pdf
```

Estructura típica del remito:
- Encabezado: N° de envío, fecha, origen → destino, usuario
- Tabla: código, descripción, cantidad, contenedor, peso
- Totales: cantidad total de items, peso total (neto + contenedor)
- Footer: espacio para firma

---

## Consideraciones Técnicas

1. **Timeout:** Los PDF complejos pueden tardar. Usar `set_time_limit(120)` si hay muchos datos.

2. **Memoria:** mPDF puede consumir mucha RAM con imágenes. Usar `ini_set('memory_limit', '256M')` si hay problemas.

3. **Charset:** Siempre configurar `'mode' => 'utf-8'` en mPDF para evitar problemas con caracteres especiales (ñ, tildes).

4. **Temp dirs:** mPDF necesita un directorio temporal. Si hay error de permisos:
   ```php
   $mpdf = new Mpdf(['tempDir' => __DIR__ . '/../../tmp']);
   ```

5. **Exportación de tablas grandes:** Para más de 1000 filas, considerar paginar o hacer la exportación asíncrona con descarga posterior.
