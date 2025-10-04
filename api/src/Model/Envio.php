<?php
namespace App\Model;

class Envio {
    private $db;

    public function __construct($db) {
        $this->db = $db;
    }

    public function crear($destino, $productos) {
        try {
            $this->db->beginTransaction();

            // 1. Crear el movimiento principal
            $stmt = $this->db->prepare("
                INSERT INTO movimientos (fechaAlta, id_ubicacion_origen, id_ubicacion_destino, usuario_alta)
                VALUES (NOW(), 1, ?, ?)
            ");
            $stmt->execute([$destino, $_SESSION['usuario'] ?? 'sistema']);
            $idMovimiento = $this->db->lastInsertId();

            // 2. Insertar los productos del envío
            foreach ($productos as $producto) {
                // Obtener el contenedor del item origen
                $stmt = $this->db->prepare("
                    SELECT id_contenedor FROM movimientos_items 
                    WHERE id = ?
                ");
                $stmt->execute([$producto['id_movimientos_items_origen']]);
                $itemOrigen = $stmt->fetch();
                $idContenedor = $itemOrigen ? $itemOrigen['id_contenedor'] : null;
                
                // Insertar el item del movimiento
                $stmt = $this->db->prepare("
                    INSERT INTO movimientos_items (
                        id_movimientos, id_productos, cnt, cnt_peso,
                        id_movimientos_items_origen, id_contenedor
                    ) VALUES (?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([
                    $idMovimiento,
                    $producto['id_productos'],
                    $producto['cantidad'],
                    $producto['peso'],
                    $producto['id_movimientos_items_origen'],
                    $idContenedor
                ]);
                $idMovimientoItem = $this->db->lastInsertId();

                // Registrar el estado inicial (NUEVO)
                $stmt = $this->db->prepare("
                    INSERT INTO estados_items_movimientos (
                        id_estados, id_movimientos_items, fecha_alta, usuario_alta
                    ) VALUES (1, ?, NOW(), ?)
                ");
                $stmt->execute([$idMovimientoItem, $_SESSION['usuario'] ?? 'sistema']);
            }

            $this->db->commit();
            return $idMovimiento;
        } catch (\Exception $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    public function obtenerEnvios($filtros = []) {
        $sql = "
            SELECT DISTINCT
                m.id,
                m.fechaAlta,
                uo.nombre as origen,
                ud.nombre as destino,
                (
                    SELECT e.nombre 
                    FROM estados_items_movimientos eim
                    JOIN estados e ON e.id = eim.id_estados
                    WHERE eim.id_movimientos_items IN (
                        SELECT id FROM movimientos_items WHERE id_movimientos = m.id
                    )
                    ORDER BY eim.fecha_alta DESC
                    LIMIT 1
                ) as ultimo_estado,
                COUNT(DISTINCT mi.id) as cantidad_items,
                SUM(mi.cnt_peso) as peso_total
            FROM movimientos m
            JOIN ubicaciones uo ON uo.id = m.id_ubicacion_origen
            JOIN ubicaciones ud ON ud.id = m.id_ubicacion_destino
            JOIN movimientos_items mi ON mi.id_movimientos = m.id
            WHERE 1=1
        ";

        $params = [];

        if (!empty($filtros['fechaDesde'])) {
            $sql .= " AND DATE(m.fechaAlta) >= ?";
            $params[] = $filtros['fechaDesde'];
        }

        if (!empty($filtros['fechaHasta'])) {
            $sql .= " AND DATE(m.fechaAlta) <= ?";
            $params[] = $filtros['fechaHasta'];
        }

        if (!empty($filtros['destino'])) {
            $sql .= " AND m.id_ubicacion_destino = ?";
            $params[] = $filtros['destino'];
        }

        if (!empty($filtros['estado'])) {
            $sql .= " AND EXISTS (
                SELECT 1 FROM estados_items_movimientos eim2
                WHERE eim2.id_movimientos_items IN (
                    SELECT id FROM movimientos_items WHERE id_movimientos = m.id
                )
                AND eim2.id_estados = ?
                AND eim2.fecha_alta = (
                    SELECT MAX(fecha_alta)
                    FROM estados_items_movimientos
                    WHERE id_movimientos_items = eim2.id_movimientos_items
                )
            )";
            $params[] = $filtros['estado'];
        }

        $sql .= " GROUP BY m.id ORDER BY m.fechaAlta DESC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    public function obtenerDetalleEnvio($id) {
        // Obtener información del envío
        $stmt = $this->db->prepare("
            SELECT 
                m.*,
                uo.nombre as origen,
                ud.nombre as destino,
                (
                    SELECT e.nombre 
                    FROM estados_items_movimientos eim
                    JOIN estados e ON e.id = eim.id_estados
                    WHERE eim.id_movimientos_items IN (
                        SELECT id FROM movimientos_items WHERE id_movimientos = m.id
                    )
                    ORDER BY eim.fecha_alta DESC
                    LIMIT 1
                ) as ultimo_estado
            FROM movimientos m
            JOIN ubicaciones uo ON uo.id = m.id_ubicacion_origen
            JOIN ubicaciones ud ON ud.id = m.id_ubicacion_destino
            WHERE m.id = ?
        ");
        $stmt->execute([$id]);
        $envio = $stmt->fetch(\PDO::FETCH_ASSOC);

        if (!$envio) {
            throw new \Exception("Envío no encontrado");
        }

        // Obtener productos del envío
        $stmt = $this->db->prepare("
            SELECT 
                mi.*,
                p.codigo,
                p.descripcion,
                c.nombre as contenedor,
                c.peso as peso_contenedor,
                CASE 
                    WHEN c.peso IS NOT NULL THEN (mi.cnt_peso - c.peso)
                    ELSE mi.cnt_peso
                END as peso_neto,
                (
                    SELECT e.nombre 
                    FROM estados_items_movimientos eim
                    JOIN estados e ON e.id = eim.id_estados
                    WHERE eim.id_movimientos_items = mi.id
                    ORDER BY eim.fecha_alta DESC
                    LIMIT 1
                ) as estado
            FROM movimientos_items mi
            JOIN productos p ON p.id = mi.id_productos
            LEFT JOIN contenedores c ON c.id = mi.id_contenedor
            WHERE mi.id_movimientos = ?
        ");
        $stmt->execute([$id]);
        $productos = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        return [
            'envio' => $envio,
            'productos' => $productos
        ];
    }

    public function obtenerProductosDisponibles($filtros = []) {
        $sql = "
            SELECT 
                mi.id as id_movimiento_item,
                p.id as id_producto,
                p.codigo,
                p.descripcion,
                mi.cnt,
                mi.cnt_peso,
                c.nombre as contenedor,
                c.peso as peso_contenedor,
                CASE 
                    WHEN c.peso IS NOT NULL THEN (mi.cnt_peso - c.peso)
                    ELSE mi.cnt_peso
                END as peso_neto,
                m.fechaAlta,
                (
                    SELECT COUNT(*)
                    FROM movimientos_items
                    WHERE id_movimientos_items_origen = mi.id
                ) as veces_enviado,
                (
                    SELECT e.nombre 
                    FROM estados_items_movimientos eim
                    JOIN estados e ON e.id = eim.id_estados
                    WHERE eim.id_movimientos_items = mi.id
                    ORDER BY eim.fecha_alta DESC
                    LIMIT 1
                ) as estado_actual
            FROM movimientos_items mi
            JOIN productos p ON p.id = mi.id_productos
            JOIN movimientos m ON m.id = mi.id_movimientos
            LEFT JOIN contenedores c ON c.id = mi.id_contenedor
            WHERE mi.id_movimientos_items_origen IS NULL 
            AND NOT EXISTS (
                SELECT 1
                FROM movimientos_items mi2
                WHERE mi2.id_movimientos_items_origen = mi.id
            )
            AND EXISTS (
                SELECT 1 
                FROM estados_items_movimientos eim
                WHERE eim.id_movimientos_items = mi.id
                AND eim.id_estados = 1 -- NUEVO
                AND eim.fecha_alta = (
                    SELECT MAX(fecha_alta)
                    FROM estados_items_movimientos
                    WHERE id_movimientos_items = eim.id_movimientos_items
                )
            )
        ";

        $params = [];

        // Filtro por código de producto
        if (!empty($filtros['codigo'])) {
            $sql .= " AND p.codigo = ?";
            $params[] = $filtros['codigo'];
        }

        // Filtro por cantidad exacta
        if (!empty($filtros['cantidad'])) {
            $sql .= " AND mi.cnt = ?";
            $params[] = $filtros['cantidad'];
        }

        // Filtro por peso (con tolerancia de ±0.1 kg)
        if (!empty($filtros['peso'])) {
            $sql .= " AND mi.cnt_peso BETWEEN ? AND ?";
            $params[] = $filtros['peso'] - 0.1;
            $params[] = $filtros['peso'] + 0.1;
        }

        // Filtro general por texto (código o descripción)
        if (!empty($filtros['filtro'])) {
            $sql .= " AND (p.codigo LIKE ? OR p.descripcion LIKE ?)";
            $filtroTexto = '%' . $filtros['filtro'] . '%';
            $params[] = $filtroTexto;
            $params[] = $filtroTexto;
        }

        $sql .= " ORDER BY m.fechaAlta DESC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    public function obtenerContenedores() {
        $stmt = $this->db->prepare("SELECT * FROM contenedores ORDER BY nombre");
        $stmt->execute();
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    public function exportarPDF($id = null, $filtros = []) {
        require_once __DIR__ . '/../../vendor/autoload.php';

        $mpdf = new \Mpdf\Mpdf([
            'margin_left' => 10,
            'margin_right' => 10,
            'margin_top' => 15,
            'margin_bottom' => 15,
        ]);

        if ($id) {
            $data = $this->obtenerDetalleEnvio($id);
            $html = $this->generarHTMLDetalle($data);
            $nombreArchivo = "envio_" . $id . ".pdf";
        } else {
            $data = $this->obtenerEnvios($filtros);
            $html = $this->generarHTMLLista($data);
            $nombreArchivo = "envios_" . date('Y-m-d') . ".pdf";
        }

        $mpdf->WriteHTML($html);
        
        $rutaArchivo = __DIR__ . '/../../../temp/' . $nombreArchivo;
        $mpdf->Output($rutaArchivo, 'F');
        
        return 'temp/' . $nombreArchivo;
    }

    public function exportarExcel($id = null, $filtros = []) {
        require_once __DIR__ . '/../../vendor/autoload.php';

        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        if ($id) {
            $data = $this->obtenerDetalleEnvio($id);
            $this->generarExcelDetalle($sheet, $data);
            $nombreArchivo = "envio_" . $id . ".xlsx";
        } else {
            $data = $this->obtenerEnvios($filtros);
            $this->generarExcelLista($sheet, $data);
            $nombreArchivo = "envios_" . date('Y-m-d') . ".xlsx";
        }

        $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
        $rutaArchivo = __DIR__ . '/../../../temp/' . $nombreArchivo;
        $writer->save($rutaArchivo);
        
        return 'temp/' . $nombreArchivo;
    }

    private function generarHTMLLista($envios) {
        $totalEnvios = count($envios);
        $fechaGeneracion = date('d/m/Y H:i');
        
        $html = '
        <style>
            body { 
                font-family: Arial, sans-serif; 
                margin: 0; 
                padding: 20px;
                font-size: 12px;
            }
            .header {
                text-align: center;
                margin-bottom: 30px;
                border-bottom: 2px solid #333;
                padding-bottom: 20px;
            }
            .company-name {
                font-size: 24px;
                font-weight: bold;
                color: #333;
                margin-bottom: 5px;
            }
            .document-title {
                font-size: 18px;
                font-weight: bold;
                color: #666;
                margin-top: 15px;
            }
            .info-section {
                margin-bottom: 20px;
                padding: 15px;
                background-color: #f9f9f9;
                border: 1px solid #ddd;
            }
            table { 
                width: 100%; 
                border-collapse: collapse; 
                margin: 20px 0; 
            }
            th { 
                background-color: #333;
                color: white;
                padding: 12px 8px;
                text-align: left;
                font-weight: bold;
                border: 1px solid #333;
            }
            td { 
                border: 1px solid #ddd; 
                padding: 10px 8px; 
                text-align: left; 
            }
            tr:nth-child(even) {
                background-color: #f9f9f9;
            }
            .estado-nuevo { color: #2196F3; font-weight: bold; }
            .estado-enviado { color: #4CAF50; font-weight: bold; }
            .estado-cancelado { color: #f44336; font-weight: bold; }
            .footer {
                margin-top: 30px;
                text-align: center;
                font-size: 10px;
                color: #666;
                border-top: 1px solid #ccc;
                padding-top: 15px;
            }
            .summary {
                text-align: right;
                margin-top: 20px;
                font-weight: bold;
                background-color: #f0f0f0;
                padding: 15px;
                border: 1px solid #ddd;
            }
        </style>
        
        <div class="header">
            <div class="company-name">MIKELO</div>
            <div style="font-size: 14px; color: #666;">Sistema de Gestión de Helados</div>
            <div class="document-title">REPORTE DE ENVÍOS</div>
        </div>
        
        <div class="info-section">
            <strong>Fecha de Generación:</strong> ' . $fechaGeneracion . '<br>
            <strong>Total de Envíos:</strong> ' . $totalEnvios . '
        </div>
        
        <table>
            <thead>
                <tr>
                    <th style="width: 8%;">N° Envío</th>
                    <th style="width: 12%;">Fecha</th>
                    <th style="width: 20%;">Origen</th>
                    <th style="width: 20%;">Destino</th>
                    <th style="width: 12%;">Estado</th>
                    <th style="width: 10%;">Items</th>
                    <th style="width: 18%;">Peso Total</th>
                </tr>
            </thead>
            <tbody>';
        
        $pesoTotalGeneral = 0;
        $itemsTotalGeneral = 0;
        
        foreach ($envios as $envio) {
            $fechaFormateada = date('d/m/Y', strtotime($envio['fechaAlta']));
            $horaFormateada = date('H:i', strtotime($envio['fechaAlta']));
            $estadoClass = 'estado-' . strtolower(str_replace(' ', '-', $envio['ultimo_estado']));
            
            $pesoTotalGeneral += floatval($envio['peso_total']);
            $itemsTotalGeneral += intval($envio['cantidad_items']);
            
            $html .= "
                <tr>
                    <td style='font-weight: bold; text-align: center;'>" . str_pad($envio['id'], 6, '0', STR_PAD_LEFT) . "</td>
                    <td>{$fechaFormateada}<br><small style='color: #666;'>{$horaFormateada}</small></td>
                    <td>{$envio['origen']}</td>
                    <td>{$envio['destino']}</td>
                    <td><span class='{$estadoClass}'>{$envio['ultimo_estado']}</span></td>
                    <td style='text-align: center;'>{$envio['cantidad_items']}</td>
                    <td style='text-align: right;'>" . number_format($envio['peso_total'], 2) . " kg</td>
                </tr>";
        }
        
        $html .= '</tbody></table>
        
        <div class="summary">
            <strong>RESUMEN GENERAL</strong><br>
            Total de Envíos: ' . $totalEnvios . '<br>
            Total de Items: ' . $itemsTotalGeneral . '<br>
            Peso Total: ' . number_format($pesoTotalGeneral, 2) . ' kg
        </div>
        
        <div class="footer">
            <p>Reporte generado automáticamente por Sistema Mikelo - ' . $fechaGeneracion . '</p>
        </div>';
        
        return $html;
    }

    private function generarHTMLDetalle($data) {
        $envio = $data['envio'];
        $productos = $data['productos'];
        
        // Calcular totales
        $totalItems = count($productos);
        $pesoTotal = array_sum(array_column($productos, 'cnt_peso'));
        $fechaFormateada = date('d/m/Y', strtotime($envio['fechaAlta']));
        
        $html = '
        <style>
            body { 
                font-family: Arial, sans-serif; 
                margin: 0; 
                padding: 15px;
                font-size: 11px;
                line-height: 1.2;
            }
            .header {
                text-align: center;
                margin-bottom: 20px;
                border-bottom: 1px solid #333;
                padding-bottom: 10px;
            }
            .company-name {
                font-size: 18px;
                font-weight: bold;
                color: #333;
                margin-bottom: 3px;
            }
            .document-title {
                font-size: 14px;
                font-weight: bold;
                color: #666;
                margin: 5px 0;
            }
            .numero-remito {
                font-size: 16px;
                font-weight: bold;
                color: #d32f2f;
                margin-top: 5px;
            }
            .remito-info {
                display: table;
                width: 100%;
                margin-bottom: 20px;
                border: 1px solid #ccc;
            }
            .info-origen, .info-destino {
                display: table-cell;
                width: 50%;
                padding: 10px;
                vertical-align: top;
                border-right: 1px solid #ccc;
            }
            .info-destino {
                border-right: none;
            }
            .info-title {
                font-weight: bold;
                color: #333;
                margin-bottom: 5px;
                font-size: 12px;
                text-transform: uppercase;
            }
            .info-content {
                line-height: 1.4;
                font-size: 10px;
            }
            .productos-table {
                width: 100%;
                border-collapse: collapse;
                margin: 15px 0;
                font-size: 10px;
            }
            .productos-table th {
                background-color: #333;
                color: white;
                padding: 8px 4px;
                text-align: left;
                font-weight: bold;
                font-size: 9px;
            }
            .productos-table td {
                border: 1px solid #ddd;
                padding: 6px 4px;
                text-align: left;
                font-size: 9px;
            }
            .productos-table tr:nth-child(even) {
                background-color: #f9f9f9;
            }
            .totales {
                margin-top: 15px;
                text-align: right;
            }
            .total-box {
                display: inline-block;
                border: 1px solid #333;
                padding: 8px;
                background-color: #f0f0f0;
                min-width: 150px;
                font-size: 10px;
            }
            .signatures {
                margin-top: 30px;
                display: table;
                width: 100%;
            }
            .signature-box {
                display: table-cell;
                width: 45%;
                text-align: center;
                font-size: 9px;
            }
            .signature-line {
                height: 40px;
                border-bottom: 1px solid #333;
                margin-bottom: 5px;
            }
            .footer {
                margin-top: 20px;
                text-align: center;
                font-size: 8px;
                color: #666;
                border-top: 1px solid #ccc;
                padding-top: 10px;
            }
        </style>
        
        <div class="header">
            <div class="company-name">MIKELO</div>
            <div style="font-size: 12px; color: #666;">Sistema de Gestión de Helados</div>
            <div class="document-title">REMITO</div>
            <div class="numero-remito">N° ' . str_pad($envio['id'], 8, '0', STR_PAD_LEFT) . '</div>
        </div>
        
        <div class="remito-info">
            <div class="info-origen">
                <div class="info-title">ORIGEN</div>
                <div class="info-content">
                    <strong>' . htmlspecialchars($envio['origen']) . '</strong><br>
                    Depósito Central<br>
                    Fecha: ' . $fechaFormateada . '<br>
                    Hora: ' . date('H:i', strtotime($envio['fechaAlta'])) . '
                </div>
            </div>
            
            <div class="info-destino">
                <div class="info-title">DESTINO</div>
                <div class="info-content">
                    <strong>' . htmlspecialchars($envio['destino']) . '</strong><br>
                    Sucursal<br>
                    Usuario: ' . htmlspecialchars($envio['usuario_alta'] ?? 'Sistema') . '<br>
                    &nbsp;
                </div>
            </div>
        </div>
        
        <table class="productos-table">
            <thead>
                <tr>
                    <th style="width: 12%;">Código</th>
                    <th style="width: 40%;">Descripción</th>
                    <th style="width: 8%;">Cant.</th>
                    <th style="width: 15%;">Contenedor</th>
                    <th style="width: 12%;">Peso Bruto</th>
                    <th style="width: 13%;">Peso Neto</th>
                </tr>
            </thead>
            <tbody>';
        
        foreach ($productos as $index => $producto) {
            $html .= "
                <tr>
                    <td style='font-weight: bold;'>{$producto['codigo']}</td>
                    <td>{$producto['descripcion']}</td>
                    <td style='text-align: center;'>{$producto['cnt']}</td>
                    <td style='text-align: center;'>" . ($producto['contenedor'] ?: '-') . "</td>
                    <td style='text-align: right;'>" . number_format($producto['cnt_peso'], 2) . " kg</td>
                    <td style='text-align: right;'>" . number_format($producto['peso_neto'], 2) . " kg</td>
                </tr>";
        }
        
        // Rellenar filas vacías si hay menos de 8 productos (para mantener estructura)
        for ($i = count($productos); $i < 8; $i++) {
            $html .= "
                <tr>
                    <td>&nbsp;</td>
                    <td>&nbsp;</td>
                    <td>&nbsp;</td>
                    <td>&nbsp;</td>
                    <td>&nbsp;</td>
                    <td>&nbsp;</td>
                </tr>";
        }
        
        $html .= '
            </tbody>
        </table>
        
        <div class="totales">
            <div class="total-box">
                <strong>RESUMEN</strong><br>
                Total Items: ' . $totalItems . '<br>
                Peso Total: ' . number_format($pesoTotal, 2) . ' kg
            </div>
        </div>
        
        <div class="signatures">
            <div class="signature-box">
                <div class="signature-line"></div>
                <strong>ENTREGADO POR</strong><br>
                Nombre y Firma
            </div>
            
            <div style="display: table-cell; width: 10%;"></div>
            
            <div class="signature-box">
                <div class="signature-line"></div>
                <strong>RECIBIDO POR</strong><br>
                Nombre y Firma
            </div>
        </div>
        
        <div class="footer">
            <p>Documento generado automáticamente por Sistema Mikelo - ' . date('d/m/Y H:i') . '</p>
        </div>';
        
        return $html;
    }

    private function generarExcelLista($sheet, $envios) {
        $sheet->setTitle('Reporte de Envíos');
        
        // Encabezado de la empresa
        $sheet->setCellValue('A1', 'MIKELO - Sistema de Gestión de Helados');
        $sheet->mergeCells('A1:G1');
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(16);
        $sheet->getStyle('A1')->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
        
        $sheet->setCellValue('A2', 'REPORTE DE ENVÍOS');
        $sheet->mergeCells('A2:G2');
        $sheet->getStyle('A2')->getFont()->setBold(true)->setSize(14);
        $sheet->getStyle('A2')->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
        
        $sheet->setCellValue('A3', 'Generado el: ' . date('d/m/Y H:i'));
        $sheet->mergeCells('A3:G3');
        $sheet->getStyle('A3')->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
        
        // Encabezados
        $headers = ['N° Envío', 'Fecha', 'Hora', 'Origen', 'Destino', 'Estado', 'Items', 'Peso Total (kg)'];
        $cols = ['A', 'B', 'C', 'D', 'E', 'F', 'G', 'H'];
        
        $headerRow = 5;
        foreach ($headers as $index => $header) {
            $sheet->setCellValue($cols[$index] . $headerRow, $header);
            $sheet->getStyle($cols[$index] . $headerRow)->getFont()->setBold(true);
            $sheet->getStyle($cols[$index] . $headerRow)->getFill()
                ->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
                ->getStartColor()->setRGB('CCCCCC');
        }
        
        // Datos
        $row = 6;
        $totalItems = 0;
        $totalPeso = 0;
        
        foreach ($envios as $envio) {
            $sheet->setCellValue('A'.$row, str_pad($envio['id'], 6, '0', STR_PAD_LEFT));
            $sheet->setCellValue('B'.$row, date('d/m/Y', strtotime($envio['fechaAlta'])));
            $sheet->setCellValue('C'.$row, date('H:i', strtotime($envio['fechaAlta'])));
            $sheet->setCellValue('D'.$row, $envio['origen']);
            $sheet->setCellValue('E'.$row, $envio['destino']);
            $sheet->setCellValue('F'.$row, $envio['ultimo_estado']);
            $sheet->setCellValue('G'.$row, $envio['cantidad_items']);
            $sheet->setCellValue('H'.$row, number_format($envio['peso_total'], 2));
            
            $totalItems += intval($envio['cantidad_items']);
            $totalPeso += floatval($envio['peso_total']);
            
            // Colorear estado
            switch (strtolower($envio['ultimo_estado'])) {
                case 'nuevo':
                    $sheet->getStyle('F'.$row)->getFont()->getColor()->setRGB('2196F3');
                    break;
                case 'enviado':
                    $sheet->getStyle('F'.$row)->getFont()->getColor()->setRGB('4CAF50');
                    break;
                case 'cancelado':
                    $sheet->getStyle('F'.$row)->getFont()->getColor()->setRGB('F44336');
                    break;
            }
            
            $row++;
        }
        
        // Totales
        $row += 2;
        $sheet->setCellValue('E'.$row, 'TOTALES:');
        $sheet->getStyle('E'.$row)->getFont()->setBold(true);
        $sheet->setCellValue('F'.$row, count($envios) . ' envíos');
        $sheet->setCellValue('G'.$row, $totalItems . ' items');
        $sheet->setCellValue('H'.$row, number_format($totalPeso, 2) . ' kg');
        $sheet->getStyle('E'.$row.':H'.$row)->getFont()->setBold(true);
        
        // Autoajustar columnas
        $sheet->getColumnDimension('A')->setWidth(12);
        $sheet->getColumnDimension('B')->setWidth(15);
        $sheet->getColumnDimension('C')->setWidth(10);
        $sheet->getColumnDimension('D')->setWidth(25);
        $sheet->getColumnDimension('E')->setWidth(25);
        $sheet->getColumnDimension('F')->setWidth(15);
        $sheet->getColumnDimension('G')->setWidth(10);
        $sheet->getColumnDimension('H')->setWidth(18);
        
        // Agregar bordes
        $styleArray = [
            'borders' => [
                'allBorders' => [
                    'borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN,
                    'color' => ['argb' => '00000000'],
                ],
            ],
        ];
        $sheet->getStyle('A5:H' . ($row - 3))->applyFromArray($styleArray);
    }

    private function generarExcelDetalle($sheet, $data) {
        $envio = $data['envio'];
        $productos = $data['productos'];
        
        $sheet->setTitle('Remito ' . str_pad($envio['id'], 6, '0', STR_PAD_LEFT));
        
        // Encabezado de la empresa
        $sheet->setCellValue('A1', 'MIKELO - Sistema de Gestión de Helados');
        $sheet->mergeCells('A1:F1');
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(16);
        $sheet->getStyle('A1')->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
        
        $sheet->setCellValue('A2', 'REMITO N° ' . str_pad($envio['id'], 8, '0', STR_PAD_LEFT));
        $sheet->mergeCells('A2:F2');
        $sheet->getStyle('A2')->getFont()->setBold(true)->setSize(14);
        $sheet->getStyle('A2')->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
        
        // Información del envío
        $sheet->setCellValue('A4', 'INFORMACIÓN DEL ENVÍO');
        $sheet->getStyle('A4')->getFont()->setBold(true);
        
        $sheet->setCellValue('A5', 'Fecha:');
        $sheet->setCellValue('B5', date('d/m/Y H:i', strtotime($envio['fechaAlta'])));
        $sheet->setCellValue('A6', 'Origen:');
        $sheet->setCellValue('B6', $envio['origen']);
        $sheet->setCellValue('A7', 'Destino:');
        $sheet->setCellValue('B7', $envio['destino']);
        $sheet->setCellValue('A8', 'Usuario:');
        $sheet->setCellValue('B8', $envio['usuario_alta'] ?? 'Sistema');
        
        // Encabezados de productos
        $row = 10;
        $headers = ['Código', 'Descripción', 'Cantidad', 'Contenedor', 'Peso Bruto (kg)', 'Peso Neto (kg)'];
        $cols = ['A', 'B', 'C', 'D', 'E', 'F'];
        
        foreach ($headers as $index => $header) {
            $sheet->setCellValue($cols[$index] . $row, $header);
            $sheet->getStyle($cols[$index] . $row)->getFont()->setBold(true);
            $sheet->getStyle($cols[$index] . $row)->getFill()
                ->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
                ->getStartColor()->setRGB('CCCCCC');
        }
        
        // Datos de productos
        $row++;
        foreach ($productos as $producto) {
            $sheet->setCellValue('A' . $row, $producto['codigo']);
            $sheet->setCellValue('B' . $row, $producto['descripcion']);
            $sheet->setCellValue('C' . $row, $producto['cnt']);
            $sheet->setCellValue('D' . $row, $producto['contenedor'] ?: '-');
            $sheet->setCellValue('E' . $row, number_format($producto['cnt_peso'], 2));
            $sheet->setCellValue('F' . $row, number_format($producto['peso_neto'], 2));
            $row++;
        }
        
        // Totales
        $totalItems = count($productos);
        $pesoTotal = array_sum(array_column($productos, 'cnt_peso'));
        
        $row += 2;
        $sheet->setCellValue('D' . $row, 'TOTALES:');
        $sheet->getStyle('D' . $row)->getFont()->setBold(true);
        $sheet->setCellValue('E' . $row, 'Items: ' . $totalItems);
        $sheet->setCellValue('F' . $row, 'Peso: ' . number_format($pesoTotal, 2) . ' kg');
        
        // Ajustar anchos de columna
        $sheet->getColumnDimension('A')->setWidth(15);
        $sheet->getColumnDimension('B')->setWidth(40);
        $sheet->getColumnDimension('C')->setWidth(12);
        $sheet->getColumnDimension('D')->setWidth(20);
        $sheet->getColumnDimension('E')->setWidth(15);
        $sheet->getColumnDimension('F')->setWidth(15);
        
        // Agregar bordes
        $styleArray = [
            'borders' => [
                'allBorders' => [
                    'borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN,
                    'color' => ['argb' => '00000000'],
                ],
            ],
        ];
        $sheet->getStyle('A10:F' . ($row - 3))->applyFromArray($styleArray);
    }

    public function confirmarEnvio($idEnvio) {
        try {
            $this->db->beginTransaction();

            // Obtener todos los items del envío
            $stmt = $this->db->prepare("
                SELECT id FROM movimientos_items 
                WHERE id_movimientos = ?
            ");
            $stmt->execute([$idEnvio]);
            $items = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            // Cambiar estado de todos los items a ENVIADO (2)
            foreach ($items as $item) {
                $stmt = $this->db->prepare("
                    INSERT INTO estados_items_movimientos (
                        id_estados, id_movimientos_items, fecha_alta, usuario_alta
                    ) VALUES (2, ?, NOW(), ?)
                ");
                $stmt->execute([$item['id'], $_SESSION['usuario'] ?? 'sistema']);
            }

            $this->db->commit();
            return true;
        } catch (\Exception $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    public function cancelarEnvio($idEnvio, $motivo) {
        try {
            $this->db->beginTransaction();

            // Obtener todos los items del envío
            $stmt = $this->db->prepare("
                SELECT id, id_movimientos_items_origen 
                FROM movimientos_items 
                WHERE id_movimientos = ?
            ");
            $stmt->execute([$idEnvio]);
            $items = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            // Cambiar estado de todos los items del envío a CANCELADO (4)
            // MANTENER EL HISTORIAL COMPLETO
            foreach ($items as $item) {
                $stmt = $this->db->prepare("
                    INSERT INTO estados_items_movimientos (
                        id_estados, id_movimientos_items, fecha_alta, usuario_alta
                    ) VALUES (4, ?, NOW(), ?)
                ");
                $stmt->execute([$item['id'], $_SESSION['usuario'] ?? 'sistema']);
            }

            // SOLUCIÓN ÓPTIMA: En lugar de eliminar registros, limpiar las referencias
            // Esto mantiene el historial completo pero libera los productos al stock
            
            // Para cada item del envío cancelado, limpiar su id_movimientos_items_origen
            // Esto hace que el producto original vuelva a estar disponible
            $stmt = $this->db->prepare("
                UPDATE movimientos_items 
                SET id_movimientos_items_origen = NULL 
                WHERE id_movimientos = ?
            ");
            $stmt->execute([$idEnvio]);

            // Los productos ahora vuelven al stock porque:
            // 1. El query de productos disponibles busca items con id_movimientos_items_origen IS NULL
            // 2. El query también verifica que NO sean referenciados por otros items
            // 3. Al limpiar las referencias, los productos originales vuelven a cumplir ambas condiciones

            $this->db->commit();
            return true;
        } catch (\Exception $e) {
            $this->db->rollBack();
            throw $e;
        }
    }
}