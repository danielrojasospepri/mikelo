<?php

namespace App\Controller;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Picqer\Barcode\BarcodeGeneratorPNG;

class ContenedorController {
    private $db;

    public function __construct($db) {
        $this->db = $db;
    }

    public function listarContenedores(Request $request, Response $response) {
        try {
            $sql = "SELECT id, nombre, peso FROM contenedores ORDER BY nombre";
            $stmt = $this->db->prepare($sql);
            $stmt->execute();
            $contenedores = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            return responseJson($response, [
                'success' => true,
                'data' => $contenedores
            ]);
        } catch (\Exception $e) {
            return responseJson($response, ['error' => $e->getMessage()], 500);
        }
    }

    public function generarPDFCodigosBarras(Request $request, Response $response) {
        try {
            // Obtener contenedores
            $sql = "SELECT id, nombre, peso FROM contenedores ORDER BY nombre";
            $stmt = $this->db->prepare($sql);
            $stmt->execute();
            $contenedores = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            // Crear generador de códigos de barras
            $generator = new BarcodeGeneratorPNG();
            
            // Crear directorio para códigos de barras si no existe
            $barcodeDir = __DIR__ . '/../../../temp/barcodes/';
            if (!is_dir($barcodeDir)) {
                mkdir($barcodeDir, 0755, true);
            }

            // Crear instancia de mPDF
            $mpdf = new \Mpdf\Mpdf([
                'mode' => 'utf-8',
                'format' => 'A4', // A4 Portrait (vertical)
                'orientation' => 'P', // Portrait
                'margin_left' => 12,
                'margin_right' => 12,
                'margin_top' => 10,
                'margin_bottom' => 10,
                'default_font' => 'Arial',        // Usar Arial en lugar de DejaVu
                'fontDir' => [],                  // No usar directorio de fuentes custom
                'autoScriptToLang' => false,      // Deshabilitar auto-detección
                'autoLangToFont' => false,        // Deshabilitar auto-fuentes
            ]);

            // Configurar fuente explícitamente
            $mpdf->SetDefaultFont('Arial');

            // CSS para el documento
            $css = "
            body { 
                font-family: Arial, sans-serif; 
                font-size: 7px;
                line-height: 0.9;
                margin: 0;
                padding: 0;
            }
            .header { 
                text-align: center; 
                margin-bottom: 4px; 
                border-bottom: 1px solid #2c3e50;
                padding-bottom: 2px;
            }
            .header h1 { 
                color: #2c3e50; 
                margin: 0; 
                font-size: 10px; 
                font-weight: bold;
            }
            .header h2 { 
                color: #7f8c8d; 
                margin: 1px 0; 
                font-size: 6px; 
            }
            .contenedores-grid {
                width: 100%;
            }
            .contenedor-card {
                border: 1px solid #3498db;
                border-radius: 3px;
                padding: 5px;
                background-color: #f8f9fa;
                width: 48%; /* 2 columnas para A4 vertical */
                margin: 3px 1%;
                display: inline-block;
                vertical-align: top;
                box-sizing: border-box;
                min-height: 120px; /* Más espacio en vertical */
            }
            .contenedor-card.sin-contenedor {
                width: 48%;
                background-color: #fff3cd; 
                border-color: #ffc107;
            }
            .contenedor-titulo {
                font-size: 9px;
                font-weight: bold;
                color: #2c3e50;
                text-align: center;
                margin-bottom: 2px;
                text-transform: uppercase;
            }
            .contenedor-info {
                text-align: center;
                margin-bottom: 3px;
                font-size: 7px;
            }
            .info-item {
                display: inline-block;
                margin: 0 3px;
            }
            .info-label {
                font-weight: bold;
                color: #7f8c8d;
                font-size: 6px;
                text-transform: uppercase;
            }
            .info-value {
                font-size: 8px;
                color: #2c3e50;
                font-weight: bold;
            }
            .codigo-barras {
                text-align: center;
                background-color: white;
                border: 1px solid #bdc3c7;
                border-radius: 1px;
                padding: 2px;
                margin: 2px 0;
            }
            .codigo-barras-label {
                font-size: 7px;
                color: #7f8c8d;
                margin-bottom: 2px;
                font-weight: bold;
            }
            .codigo-barras-numero {
                font-size: 10px;
                font-weight: bold;
                color: #e74c3c;
                font-family: monospace;
                letter-spacing: 0.5px;
                margin: 2px 0 3px 0;
            }
            .barcode-image {
                margin: 2px 0;
                max-height: 30px;
            }
            .instrucciones {
                background-color: #e8f5e8;
                border: 1px solid #27ae60;
                border-radius: 3px;
                padding: 6px;
                margin: 6px 0;
                font-size: 8px;
            }
            .instrucciones-titulo {
                font-weight: bold;
                color: #27ae60;
                margin-bottom: 3px;
                font-size: 9px;
            }
            .footer {
                text-align: center;
                margin-top: 6px;
                padding-top: 4px;
                border-top: 1px solid #bdc3c7;
                font-size: 6px;
                color: #7f8c8d;
            }
            ";

            // Generar contenido HTML
            $html = "
            <div class='header'>
                <h1>🧊 SISTEMA MIKELO - Códigos de Barras Contenedores</h1>
                <h2>Generado el " . date('d/m/Y H:i:s') . "</h2>
            </div>

            <div class='instrucciones'>
                <div class='instrucciones-titulo'>📋 INSTRUCCIONES:</div>
                <div>
                    <strong>1. Producto:</strong> Escanear código normal del producto |
                    <strong>2. Contenedor:</strong> Escanear código \"00000XX\" |
                    <strong>3. Automático:</strong> Guardado y limpieza automáticos |
                    <strong>4. Repetir:</strong> Focus automático para siguiente
                </div>
            </div>

            <div class='contenedores-grid'>
            ";

            // Agregar primero el código especial para "Sin Contenedor"
            $codigoSinContenedor = '0000000'; // Código especial para "Sin Contenedor"
            
            try {
                $barcodeData = $generator->getBarcode($codigoSinContenedor, $generator::TYPE_CODE_128, 2, 30);
                $barcodeBase64 = base64_encode($barcodeData);
                $barcodeImage = 'data:image/png;base64,' . $barcodeBase64;
            } catch (\Exception $e) {
                $barcodeData = $generator->getBarcode($codigoSinContenedor, $generator::TYPE_CODE_39, 2, 30);
                $barcodeBase64 = base64_encode($barcodeData);
                $barcodeImage = 'data:image/png;base64,' . $barcodeBase64;
            }
            
            $html .= "
            <div class='contenedor-card sin-contenedor'>
                <div class='contenedor-titulo' style='color: #856404;'>🚫 SIN CONTENEDOR</div>
                
                <div class='contenedor-info'>
                    <div class='info-item'>
                        <div class='info-label'>Tipo</div>
                        <div class='info-value'>Especial</div>
                    </div>
                    <div class='info-item'>
                        <div class='info-label'>Peso</div>
                        <div class='info-value'>0.000kg</div>
                    </div>
                </div>

                <div class='codigo-barras'>
                    <div class='codigo-barras-label'>🔍 ESCANEAR PARA QUITAR CONTENEDOR</div>
                    <div class='codigo-barras-numero'>{$codigoSinContenedor}</div>
                    <div class='barcode-image'>
                        <img src='{$barcodeImage}' alt='Código {$codigoSinContenedor}' style='height: 30px; width: auto;'>
                    </div>
                </div>
            </div>
            ";

            foreach ($contenedores as $index => $contenedor) {
                // Generar código de barras con patrón 00000 + ID del contenedor (2 dígitos)
                $codigoBarras = '00000' . str_pad($contenedor['id'], 2, '0', STR_PAD_LEFT);
                
                // Generar imagen del código de barras (Code 128) 
                try {
                    $barcodeData = $generator->getBarcode($codigoBarras, $generator::TYPE_CODE_128, 2, 30);
                    $barcodeBase64 = base64_encode($barcodeData);
                    $barcodeImage = 'data:image/png;base64,' . $barcodeBase64;
                } catch (\Exception $e) {
                    // Si falla Code 128, intentar con Code 39
                    $barcodeData = $generator->getBarcode($codigoBarras, $generator::TYPE_CODE_39, 2, 30);
                    $barcodeBase64 = base64_encode($barcodeData);
                    $barcodeImage = 'data:image/png;base64,' . $barcodeBase64;
                }
                
                $html .= "
                <div class='contenedor-card'>
                    <div class='contenedor-titulo'>{$contenedor['nombre']}</div>
                    
                    <div class='contenedor-info'>
                        <div class='info-item'>
                            <div class='info-label'>ID</div>
                            <div class='info-value'>{$contenedor['id']}</div>
                        </div>
                        <div class='info-item'>
                            <div class='info-label'>Peso</div>
                            <div class='info-value'>{$contenedor['peso']}kg</div>
                        </div>
                    </div>

                    <div class='codigo-barras'>
                        <div class='codigo-barras-label'>🔍 ESCANEAR</div>
                        <div class='codigo-barras-numero'>{$codigoBarras}</div>
                        <div class='barcode-image'>
                            <img src='{$barcodeImage}' alt='Código {$codigoBarras}' style='height: 30px; width: auto;'>
                        </div>
                    </div>
                </div>
                ";
            }

            $html .= "
            </div>

            <div class='footer'>
                <div><strong>Sistema Mikelo</strong> | Code 128 - Compatible con cualquier lector | <strong>Patrón:</strong> 00000 + ID (2 dígitos)</div>
            </div>
            ";

            $mpdf->WriteHTML($css, \Mpdf\HTMLParserMode::HEADER_CSS);
            $mpdf->WriteHTML($html, \Mpdf\HTMLParserMode::HTML_BODY);

            // Generar nombre de archivo único
            $nombreArchivo = 'contenedores_codigos_barras_' . date('Y-m-d_H-i-s') . '.pdf';
            $rutaCompleta = __DIR__ . '/../../../temp/' . $nombreArchivo;

            // Crear directorio si no existe
            if (!is_dir(dirname($rutaCompleta))) {
                mkdir(dirname($rutaCompleta), 0755, true);
            }

            $mpdf->Output($rutaCompleta, \Mpdf\Output\Destination::FILE);

            return responseJson($response, [
                'success' => true,
                'archivo' => 'temp/' . $nombreArchivo,
                'mensaje' => 'PDF de códigos de barras generado exitosamente con códigos reales',
                'detalles' => [
                    'formato' => 'Code 128',
                    'contenedores' => count($contenedores),
                    'patron' => '00000 + ID (2 dígitos)'
                ]
            ]);

        } catch (\Exception $e) {
            return responseJson($response, ['error' => $e->getMessage()], 500);
        }
    }
}