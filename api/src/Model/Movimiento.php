<?php
namespace App\Model;

class Movimiento {
    private $db;

    public function __construct($db) {
        $this->db = $db;
    }

    public function crear($ubicacionOrigen, $ubicacionDestino, $usuario) {
        $this->db->beginTransaction();
        try {
            $sql = "INSERT INTO movimientos (fechaAlta, id_ubicacion_origen, id_ubicacion_destino, usuario_alta) 
                    VALUES (NOW(), :origen, :destino, :usuario)";
            $stmt = $this->db->prepare($sql);
            $stmt->bindParam(':origen', $ubicacionOrigen);
            $stmt->bindParam(':destino', $ubicacionDestino);
            $stmt->bindParam(':usuario', $usuario);
            $stmt->execute();
            
            $movimientoId = $this->db->lastInsertId();
            $this->db->commit();
            return $movimientoId;
        } catch (\Exception $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    public function agregarItem($movimientoId, $productoId, $cantidad, $cantidadPeso = 0, $movimientoItemOrigenId = null, $idContenedor = null) {
        $this->db->beginTransaction();
        try {
            $sql = "INSERT INTO movimientos_items (id_movimientos, id_productos, cnt, cnt_peso, id_movimientos_items_origen, id_contenedor) 
                    VALUES (:movimiento, :producto, :cnt, :peso, :origen, :contenedor)";
            $stmt = $this->db->prepare($sql);
            $stmt->bindParam(':movimiento', $movimientoId);
            $stmt->bindParam(':producto', $productoId);
            $stmt->bindParam(':cnt', $cantidad);
            $stmt->bindParam(':peso', $cantidadPeso);
            $stmt->bindParam(':origen', $movimientoItemOrigenId);
            $stmt->bindParam(':contenedor', $idContenedor);
            $stmt->execute();
            
            $itemId = $this->db->lastInsertId();
            
            // Todos los items nuevos comienzan en estado NUEVO (1)
            // El estado ENVIADO (2) debe ser asignado explícitamente cuando se confirme el envío
            $estadoId = 1; // 1=NUEVO
            
            $sql = "INSERT INTO estados_items_movimientos (id_estados, id_movimientos_items, fecha_alta) 
                    VALUES (:estadoId, :itemId, NOW())";
            $stmt = $this->db->prepare($sql);
            $stmt->bindParam(':estadoId', $estadoId);
            $stmt->bindParam(':itemId', $itemId);
            $stmt->execute();
            
            $this->db->commit();
            return $itemId;
        } catch (\Exception $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    public function obtenerMovimientosDeposito($fecha) {
        $sql = "SELECT m.id, m.fechaAlta, p.codigo, p.descripcion, mi.cnt, mi.cnt_peso, e.nombre as estado
                FROM movimientos m
                JOIN movimientos_items mi ON mi.id_movimientos = m.id
                JOIN productos p ON p.id = mi.id_productos
                JOIN estados_items_movimientos eim ON eim.id_movimientos_items = mi.id
                JOIN estados e ON e.id = eim.id_estados
                WHERE DATE(m.fechaAlta) = :fecha
                AND m.id_ubicacion_destino = 1
                ORDER BY m.fechaAlta DESC";
        
        $stmt = $this->db->prepare($sql);
        $stmt->bindParam(':fecha', $fecha);
        $stmt->execute();
        
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    public function buscarMovimientos($fechaDesde, $fechaHasta, $ubicacion, $estado, $producto = null) {
        $sql = "SELECT m.id, m.fechaAlta, 
                       uo.nombre as ubicacion_origen, ud.nombre as ubicacion_destino,
                       p.codigo, p.descripcion, mi.cnt, mi.cnt_peso, 
                       e.nombre as estado
                FROM movimientos m
                LEFT JOIN ubicaciones uo ON uo.id = m.id_ubicacion_origen
                JOIN ubicaciones ud ON ud.id = m.id_ubicacion_destino
                JOIN movimientos_items mi ON mi.id_movimientos = m.id
                JOIN productos p ON p.id = mi.id_productos
                JOIN estados_items_movimientos eim ON eim.id_movimientos_items = mi.id
                JOIN estados e ON e.id = eim.id_estados
                WHERE 1=1";
        
        $params = [];
        
        if ($fechaDesde) {
            $sql .= " AND DATE(m.fechaAlta) >= :fechaDesde";
            $params[':fechaDesde'] = $fechaDesde;
        }
        
        if ($fechaHasta) {
            $sql .= " AND DATE(m.fechaAlta) <= :fechaHasta";
            $params[':fechaHasta'] = $fechaHasta;
        }
        
        if ($ubicacion) {
            $sql .= " AND (m.id_ubicacion_origen = :ubicacion OR m.id_ubicacion_destino = :ubicacion)";
            $params[':ubicacion'] = $ubicacion;
        }
        
        if ($estado) {
            $sql .= " AND e.id = :estado";
            $params[':estado'] = $estado;
        }
        
        if ($producto) {
            $sql .= " AND (p.codigo LIKE :producto OR p.descripcion LIKE :producto)";
            $params[':producto'] = '%' . $producto . '%';
        }
        
        $sql .= " ORDER BY m.fechaAlta DESC";
        
        $stmt = $this->db->prepare($sql);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        $stmt->execute();
        
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    public function verificarDuplicado($productoId, $cantidad, $peso, $fecha) {
        $sql = "SELECT COUNT(*) as total
                FROM movimientos m
                JOIN movimientos_items mi ON mi.id_movimientos = m.id
                WHERE DATE(m.fechaAlta) = :fecha
                AND m.id_ubicacion_destino = 1
                AND mi.id_productos = :producto_id
                AND mi.cnt = :cantidad
                AND ABS(mi.cnt_peso - :peso) < 0.001"; // Tolerancia para comparación de números decimales
        
        $stmt = $this->db->prepare($sql);
        $stmt->bindParam(':fecha', $fecha);
        $stmt->bindParam(':producto_id', $productoId);
        $stmt->bindParam(':cantidad', $cantidad);
        $stmt->bindParam(':peso', $peso);
        $stmt->execute();
        
        $resultado = $stmt->fetch(\PDO::FETCH_ASSOC);
        return $resultado['total'] > 0;
    }

    public function exportarPDF($filtros = []) {
        $movimientos = $this->buscarMovimientos(
            $filtros['fecha_desde'] ?? null,
            $filtros['fecha_hasta'] ?? null,
            $filtros['ubicacion'] ?? null,
            $filtros['estado'] ?? null,
            $filtros['producto'] ?? null
        );

        // Crear instancia de mPDF
        $mpdf = new \Mpdf\Mpdf([
            'mode' => 'utf-8',
            'format' => 'A4-L', // Formato apaisado para mejor visualización
            'margin_left' => 10,
            'margin_right' => 10,
            'margin_top' => 20,
            'margin_bottom' => 20,
            'margin_header' => 5,
            'margin_footer' => 5
        ]);

        // CSS para el documento
        $css = "
        body { font-family: Arial, sans-serif; font-size: 10px; }
        .header { text-align: center; margin-bottom: 20px; }
        .header h1 { color: #2c3e50; margin: 0; font-size: 18px; }
        .header h2 { color: #7f8c8d; margin: 5px 0; font-size: 14px; }
        .filters { background-color: #f8f9fa; padding: 10px; margin-bottom: 15px; border-radius: 5px; }
        .filters strong { color: #2c3e50; }
        table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        th { background-color: #3498db; color: white; padding: 8px; text-align: left; font-weight: bold; }
        td { padding: 6px; border-bottom: 1px solid #ecf0f1; }
        tr:nth-child(even) { background-color: #f8f9fa; }
        .text-center { text-align: center; }
        .badge { padding: 2px 6px; border-radius: 3px; font-size: 9px; font-weight: bold; }
        .badge-success { background-color: #27ae60; color: white; }
        .badge-primary { background-color: #3498db; color: white; }
        .badge-danger { background-color: #e74c3c; color: white; }
        .badge-info { background-color: #17a2b8; color: white; }
        .summary { margin-top: 20px; background-color: #f8f9fa; padding: 10px; border-radius: 5px; }
        ";

        // Generar filtros aplicados para mostrar en el reporte
        $filtrosAplicados = [];
        if (!empty($filtros['fecha_desde'])) $filtrosAplicados[] = "Desde: " . date('d/m/Y', strtotime($filtros['fecha_desde']));
        if (!empty($filtros['fecha_hasta'])) $filtrosAplicados[] = "Hasta: " . date('d/m/Y', strtotime($filtros['fecha_hasta']));
        if (!empty($filtros['producto'])) $filtrosAplicados[] = "Producto: " . $filtros['producto'];
        
        $filtrosTexto = !empty($filtrosAplicados) ? implode(' | ', $filtrosAplicados) : 'Sin filtros aplicados';

        // Generar contenido HTML
        $html = "
        <div class='header'>
            <h1>SISTEMA MIKELO - REPORTE DE MOVIMIENTOS</h1>
            <h2>Fecha de generación: " . date('d/m/Y H:i:s') . "</h2>
        </div>
        
        <div class='filters'>
            <strong>Filtros aplicados:</strong> $filtrosTexto
        </div>

        <table>
            <thead>
                <tr>
                    <th>Fecha/Hora</th>
                    <th>Origen</th>
                    <th>Destino</th>
                    <th>Código</th>
                    <th>Descripción</th>
                    <th>Cantidad</th>
                    <th>Peso (kg)</th>
                    <th>Estado</th>
                </tr>
            </thead>
            <tbody>";

        $totalItems = 0;
        $pesoTotal = 0;

        foreach ($movimientos as $movimiento) {
            $fechaFormateada = date('d/m/Y H:i', strtotime($movimiento['fechaAlta']));
            $estadoBadgeClass = $this->getEstadoBadgeClass($movimiento['estado']);
            
            $html .= "
                <tr>
                    <td>$fechaFormateada</td>
                    <td>" . ($movimiento['ubicacion_origen'] ?: '-') . "</td>
                    <td>{$movimiento['ubicacion_destino']}</td>
                    <td><strong>{$movimiento['codigo']}</strong></td>
                    <td>{$movimiento['descripcion']}</td>
                    <td class='text-center'>{$movimiento['cnt']}</td>
                    <td class='text-center'>{$movimiento['cnt_peso']}</td>
                    <td class='text-center'><span class='badge badge-$estadoBadgeClass'>{$movimiento['estado']}</span></td>
                </tr>";
            
            $totalItems += $movimiento['cnt'];
            $pesoTotal += $movimiento['cnt_peso'];
        }

        $html .= "
            </tbody>
        </table>
        
        <div class='summary'>
            <strong>Resumen:</strong> 
            Total de registros: " . count($movimientos) . " | 
            Total items: " . number_format($totalItems, 2) . " | 
            Peso total: " . number_format($pesoTotal, 3) . " kg
        </div>";

        $mpdf->WriteHTML($css, \Mpdf\HTMLParserMode::HEADER_CSS);
        $mpdf->WriteHTML($html, \Mpdf\HTMLParserMode::HTML_BODY);

        // Generar nombre de archivo único
        $nombreArchivo = 'movimientos_' . date('Y-m-d_H-i-s') . '.pdf';
        $rutaCompleta = __DIR__ . '/../../../temp/' . $nombreArchivo;

        // Crear directorio si no existe
        if (!is_dir(dirname($rutaCompleta))) {
            mkdir(dirname($rutaCompleta), 0755, true);
        }

        $mpdf->Output($rutaCompleta, \Mpdf\Output\Destination::FILE);

        return 'temp/' . $nombreArchivo;
    }

    public function exportarExcel($filtros = []) {
        $movimientos = $this->buscarMovimientos(
            $filtros['fecha_desde'] ?? null,
            $filtros['fecha_hasta'] ?? null,
            $filtros['ubicacion'] ?? null,
            $filtros['estado'] ?? null,
            $filtros['producto'] ?? null
        );

        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        // Configurar título y metadatos
        $sheet->setTitle('Movimientos');
        $spreadsheet->getProperties()
            ->setCreator('Sistema Mikelo')
            ->setTitle('Reporte de Movimientos')
            ->setDescription('Reporte generado el ' . date('d/m/Y H:i:s'));

        // Establecer encabezados
        $encabezados = [
            'A1' => 'Fecha/Hora',
            'B1' => 'Origen', 
            'C1' => 'Destino',
            'D1' => 'Código',
            'E1' => 'Descripción',
            'F1' => 'Cantidad',
            'G1' => 'Peso (kg)',
            'H1' => 'Estado'
        ];

        foreach ($encabezados as $celda => $valor) {
            $sheet->setCellValue($celda, $valor);
        }

        // Aplicar estilo a los encabezados
        $headerStyle = [
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID, 'color' => ['rgb' => '3498db']],
            'alignment' => ['horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER]
        ];
        $sheet->getStyle('A1:H1')->applyFromArray($headerStyle);

        // Agregar datos
        $fila = 2;
        $totalItems = 0;
        $pesoTotal = 0;

        foreach ($movimientos as $movimiento) {
            $fechaFormateada = date('d/m/Y H:i', strtotime($movimiento['fechaAlta']));
            
            $sheet->setCellValue('A' . $fila, $fechaFormateada);
            $sheet->setCellValue('B' . $fila, $movimiento['ubicacion_origen'] ?: '-');
            $sheet->setCellValue('C' . $fila, $movimiento['ubicacion_destino']);
            $sheet->setCellValue('D' . $fila, $movimiento['codigo']);
            $sheet->setCellValue('E' . $fila, $movimiento['descripcion']);
            $sheet->setCellValue('F' . $fila, $movimiento['cnt']);
            $sheet->setCellValue('G' . $fila, $movimiento['cnt_peso']);
            $sheet->setCellValue('H' . $fila, $movimiento['estado']);
            
            $totalItems += $movimiento['cnt'];
            $pesoTotal += $movimiento['cnt_peso'];
            $fila++;
        }

        // Agregar totales
        if ($fila > 2) {
            $filaTotal = $fila + 1;
            $sheet->setCellValue('E' . $filaTotal, 'TOTALES:');
            $sheet->setCellValue('F' . $filaTotal, $totalItems);
            $sheet->setCellValue('G' . $filaTotal, $pesoTotal);
            
            // Estilo para totales
            $totalStyle = [
                'font' => ['bold' => true],
                'fill' => ['fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID, 'color' => ['rgb' => 'f8f9fa']]
            ];
            $sheet->getStyle('E' . $filaTotal . ':G' . $filaTotal)->applyFromArray($totalStyle);
        }

        // Ajustar anchos de columnas
        $sheet->getColumnDimension('A')->setWidth(18);
        $sheet->getColumnDimension('B')->setWidth(15);
        $sheet->getColumnDimension('C')->setWidth(15);
        $sheet->getColumnDimension('D')->setWidth(12);
        $sheet->getColumnDimension('E')->setWidth(30);
        $sheet->getColumnDimension('F')->setWidth(12);
        $sheet->getColumnDimension('G')->setWidth(12);
        $sheet->getColumnDimension('H')->setWidth(12);

        // Aplicar bordes a toda la tabla
        if ($fila > 2) {
            $borderStyle = [
                'borders' => [
                    'allBorders' => [
                        'borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN,
                        'color' => ['rgb' => 'CCCCCC']
                    ]
                ]
            ];
            $sheet->getStyle('A1:H' . ($fila - 1))->applyFromArray($borderStyle);
        }

        // Generar archivo
        $nombreArchivo = 'movimientos_' . date('Y-m-d_H-i-s') . '.xlsx';
        $rutaCompleta = __DIR__ . '/../../../temp/' . $nombreArchivo;

        // Crear directorio si no existe
        if (!is_dir(dirname($rutaCompleta))) {
            mkdir(dirname($rutaCompleta), 0755, true);
        }

        $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
        $writer->save($rutaCompleta);

        return 'temp/' . $nombreArchivo;
    }

    private function getEstadoBadgeClass($estado) {
        switch (strtolower($estado)) {
            case 'nuevo': return 'success';
            case 'enviado': return 'primary';
            case 'cancelado': return 'danger';
            case 'recibido': return 'info';
            default: return 'secondary';
        }
    }
}