<?php
namespace App\Model;

class StockDeposito {
    private $db;

    public function __construct($db) {
        $this->db = $db;
    }

    public function obtenerStockAgrupado($filtros = []) {
        $whereConditions = ["mi.id_movimientos_items_origen IS NULL"];
        $whereConditions[] = "NOT EXISTS (
            SELECT 1 FROM movimientos_items mi2 
            WHERE mi2.id_movimientos_items_origen = mi.id
        )";
        
        $params = [];

        // Aplicar filtros
        if (!empty($filtros['producto'])) {
            $whereConditions[] = "(p.codigo LIKE ? OR p.descripcion LIKE ?)";
            $searchTerm = '%' . $filtros['producto'] . '%';
            $params[] = $searchTerm;
            $params[] = $searchTerm;
        }

        if (!empty($filtros['contenedor'])) {
            $whereConditions[] = "mi.id_contenedor = ?";
            $params[] = $filtros['contenedor'];
        }

        if (!empty($filtros['fechaDesde'])) {
            $whereConditions[] = "DATE(m.fechaAlta) >= ?";
            $params[] = $filtros['fechaDesde'];
        }

        if (!empty($filtros['fechaHasta'])) {
            $whereConditions[] = "DATE(m.fechaAlta) <= ?";
            $params[] = $filtros['fechaHasta'];
        }

        $whereClause = "WHERE " . implode(" AND ", $whereConditions);

        $sql = "
            SELECT 
                p.id as id_producto,
                p.codigo,
                p.descripcion,
                COUNT(mi.id) as total_unidades,
                SUM(mi.cnt_peso) as total_peso_bruto,
                SUM(
                    CASE 
                        WHEN c.peso IS NOT NULL THEN (mi.cnt_peso - c.peso)
                        ELSE mi.cnt_peso
                    END
                ) as total_peso_neto,
                GROUP_CONCAT(DISTINCT c.nombre ORDER BY c.nombre SEPARATOR ', ') as contenedores,
                MIN(m.fechaAlta) as fecha_mas_antigua
            FROM movimientos_items mi
            JOIN productos p ON p.id = mi.id_productos
            JOIN movimientos m ON m.id = mi.id_movimientos
            LEFT JOIN contenedores c ON c.id = mi.id_contenedor
            $whereClause
            GROUP BY p.id, p.codigo, p.descripcion
            ORDER BY p.codigo
        ";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    public function obtenerDetalleBandejas($idProducto, $filtros = []) {
        $whereConditions = [
            "mi.id_productos = ?",
            "mi.id_movimientos_items_origen IS NULL"
        ];
        $whereConditions[] = "NOT EXISTS (
            SELECT 1 FROM movimientos_items mi2 
            WHERE mi2.id_movimientos_items_origen = mi.id
        )";
        
        $params = [$idProducto];

        // Aplicar filtros adicionales si existen
        if (!empty($filtros['fechaDesde'])) {
            $whereConditions[] = "DATE(m.fechaAlta) >= ?";
            $params[] = $filtros['fechaDesde'];
        }

        if (!empty($filtros['fechaHasta'])) {
            $whereConditions[] = "DATE(m.fechaAlta) <= ?";
            $params[] = $filtros['fechaHasta'];
        }

        $whereClause = "WHERE " . implode(" AND ", $whereConditions);

        $sql = "
            SELECT 
                mi.id as id_movimiento_item,
                mi.cnt,
                mi.cnt_peso,
                mi.id_contenedor,
                c.nombre as contenedor,
                c.peso as peso_contenedor,
                m.fechaAlta,
                (
                    SELECT e.nombre 
                    FROM estados_items_movimientos eim
                    JOIN estados e ON e.id = eim.id_estados
                    WHERE eim.id_movimientos_items = mi.id
                    ORDER BY eim.fecha_alta DESC
                    LIMIT 1
                ) as estado
            FROM movimientos_items mi
            JOIN movimientos m ON m.id = mi.id_movimientos
            LEFT JOIN contenedores c ON c.id = mi.id_contenedor
            $whereClause
            ORDER BY m.fechaAlta ASC
        ";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    public function cambiarContenedor($bandejas, $nuevoContenedor, $motivo) {
        try {
            $this->db->beginTransaction();

            foreach ($bandejas as $idBandeja) {
                // Actualizar el contenedor del movimiento_item
                $stmt = $this->db->prepare("
                    UPDATE movimientos_items 
                    SET id_contenedor = ? 
                    WHERE id = ?
                ");
                $stmt->execute([$nuevoContenedor, $idBandeja]);

                // Registrar el cambio en una tabla de auditoría o como comentario
                $stmt = $this->db->prepare("
                    INSERT INTO movimientos_cambios (id_movimientos_items, tipo_cambio, valor_anterior, valor_nuevo, motivo, fecha_cambio, usuario)
                    SELECT 
                        mi.id,
                        'CONTENEDOR',
                        COALESCE(c_ant.nombre, 'Sin contenedor'),
                        c_nuevo.nombre,
                        ?,
                        NOW(),
                        ?
                    FROM movimientos_items mi
                    LEFT JOIN contenedores c_ant ON c_ant.id = mi.id_contenedor
                    JOIN contenedores c_nuevo ON c_nuevo.id = ?
                    WHERE mi.id = ?
                ");
                $stmt->execute([$motivo, $_SESSION['usuario'] ?? 'sistema', $nuevoContenedor, $idBandeja]);
            }

            $this->db->commit();
            return true;
        } catch (\Exception $e) {
            $this->db->rollback();
            throw $e;
        }
    }

    public function darDeBaja($bandejas, $motivo) {
        try {
            $this->db->beginTransaction();

            // Obtener el ID del estado "DADO_DE_BAJA" (asumiendo que existe)
            $stmt = $this->db->prepare("SELECT id FROM estados WHERE nombre = 'DADO_DE_BAJA' LIMIT 1");
            $stmt->execute();
            $estadoBaja = $stmt->fetch(\PDO::FETCH_ASSOC);

            if (!$estadoBaja) {
                // Crear el estado si no existe
                $stmt = $this->db->prepare("INSERT INTO estados (nombre, descripcion) VALUES ('DADO_DE_BAJA', 'Producto dado de baja')");
                $stmt->execute();
                $idEstadoBaja = $this->db->lastInsertId();
            } else {
                $idEstadoBaja = $estadoBaja['id'];
            }

            foreach ($bandejas as $idBandeja) {
                // Crear registro de estado de baja
                $stmt = $this->db->prepare("
                    INSERT INTO estados_items_movimientos (id_movimientos_items, id_estados, fecha_alta, observaciones, usuario_alta)
                    VALUES (?, ?, NOW(), ?, ?)
                ");
                $stmt->execute([$idBandeja, $idEstadoBaja, $motivo, $_SESSION['usuario'] ?? 'sistema']);

                // Registrar el cambio en auditoría
                $stmt = $this->db->prepare("
                    INSERT INTO movimientos_cambios (id_movimientos_items, tipo_cambio, valor_anterior, valor_nuevo, motivo, fecha_cambio, usuario)
                    VALUES (?, 'BAJA', 'DISPONIBLE', 'DADO_DE_BAJA', ?, NOW(), ?)
                ");
                $stmt->execute([$idBandeja, $motivo, $_SESSION['usuario'] ?? 'sistema']);
            }

            $this->db->commit();
            return true;
        } catch (\Exception $e) {
            $this->db->rollback();
            throw $e;
        }
    }

    public function exportarPDF($filtros = []) {
        require_once __DIR__ . '/../../vendor/autoload.php';

        $data = $this->obtenerStockAgrupado($filtros);
        $html = $this->generarHTMLStock($data);

        $mpdf = new \Mpdf\Mpdf([
            'margin_left' => 10,
            'margin_right' => 10,
            'margin_top' => 15,
            'margin_bottom' => 15,
        ]);

        $mpdf->WriteHTML($html);
        
        $nombreArchivo = "stock_deposito_" . date('Y-m-d') . ".pdf";
        $rutaArchivo = __DIR__ . '/../../../temp/' . $nombreArchivo;
        $mpdf->Output($rutaArchivo, 'F');
        
        return '/mikelo/temp/' . $nombreArchivo;
    }

    public function exportarExcel($filtros = []) {
        require_once __DIR__ . '/../../vendor/autoload.php';

        $data = $this->obtenerStockAgrupado($filtros);

        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        $this->generarExcelStock($sheet, $data);

        $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
        $nombreArchivo = "stock_deposito_" . date('Y-m-d') . ".xlsx";
        $rutaArchivo = __DIR__ . '/../../../temp/' . $nombreArchivo;
        
        $writer->save($rutaArchivo);
        
        return '/mikelo/temp/' . $nombreArchivo;
    }

    private function generarHTMLStock($data) {
        $totalProductos = count($data);
        $totalUnidades = array_sum(array_column($data, 'total_unidades'));
        $totalPesoBruto = array_sum(array_column($data, 'total_peso_bruto'));
        $totalPesoNeto = array_sum(array_column($data, 'total_peso_neto'));
        
        $html = '
        <style>
            body { 
                font-family: Arial, sans-serif; 
                margin: 0; 
                padding: 15px;
                font-size: 11px;
                line-height: 1.3;
            }
            .header {
                text-align: center;
                margin-bottom: 20px;
                border-bottom: 2px solid #333;
                padding-bottom: 10px;
            }
            .company-name {
                font-size: 20px;
                font-weight: bold;
                color: #333;
                margin-bottom: 5px;
            }
            .document-title {
                font-size: 16px;
                font-weight: bold;
                color: #666;
                margin: 8px 0;
            }
            .fecha-reporte {
                font-size: 12px;
                color: #666;
                margin-top: 5px;
            }
            .resumen-box {
                background-color: #f8f9fa;
                border: 1px solid #dee2e6;
                padding: 15px;
                margin: 15px 0;
                border-radius: 5px;
            }
            .resumen-titulo {
                font-weight: bold;
                font-size: 14px;
                color: #333;
                margin-bottom: 8px;
                text-align: center;
            }
            .resumen-contenido {
                display: table;
                width: 100%;
            }
            .resumen-item {
                display: table-cell;
                width: 25%;
                text-align: center;
                padding: 5px;
                font-size: 11px;
            }
            .resumen-numero {
                font-weight: bold;
                font-size: 14px;
                color: #007bff;
                display: block;
            }
            .stock-table {
                width: 100%;
                border-collapse: collapse;
                margin: 15px 0;
                font-size: 10px;
            }
            .stock-table th {
                background-color: #333;
                color: white;
                padding: 8px 4px;
                text-align: left;
                font-weight: bold;
                font-size: 9px;
            }
            .stock-table td {
                border: 1px solid #ddd;
                padding: 6px 4px;
                text-align: left;
                font-size: 9px;
            }
            .stock-table tr:nth-child(even) {
                background-color: #f9f9f9;
            }
            .text-right { text-align: right; }
            .text-center { text-align: center; }
            .footer {
                margin-top: 30px;
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
            <div class="document-title">REPORTE DE STOCK EN DEPÓSITO</div>
            <div class="fecha-reporte">Generado el ' . date('d/m/Y H:i') . '</div>
        </div>
        
        <div class="resumen-box">
            <div class="resumen-titulo">RESUMEN GENERAL</div>
            <div class="resumen-contenido">
                <div class="resumen-item">
                    <span class="resumen-numero">' . $totalProductos . '</span>
                    Productos Distintos
                </div>
                <div class="resumen-item">
                    <span class="resumen-numero">' . $totalUnidades . '</span>
                    Total Unidades
                </div>
                <div class="resumen-item">
                    <span class="resumen-numero">' . number_format($totalPesoBruto, 2) . '</span>
                    Kg Brutos
                </div>
                <div class="resumen-item">
                    <span class="resumen-numero">' . number_format($totalPesoNeto, 2) . '</span>
                    Kg Netos
                </div>
            </div>
        </div>
        
        <table class="stock-table">
            <thead>
                <tr>
                    <th style="width: 10%;">Código</th>
                    <th style="width: 35%;">Descripción</th>
                    <th style="width: 8%;">Unidades</th>
                    <th style="width: 12%;">Peso Bruto</th>
                    <th style="width: 12%;">Peso Neto</th>
                    <th style="width: 15%;">Contenedores</th>
                    <th style="width: 8%;">Fecha Más Antigua</th>
                </tr>
            </thead>
            <tbody>';
        
        foreach ($data as $producto) {
            $html .= "
                <tr>
                    <td style='font-weight: bold;'>{$producto['codigo']}</td>
                    <td>{$producto['descripcion']}</td>
                    <td class='text-center'>{$producto['total_unidades']}</td>
                    <td class='text-right'>" . number_format($producto['total_peso_bruto'], 2) . " kg</td>
                    <td class='text-right'>" . number_format($producto['total_peso_neto'], 2) . " kg</td>
                    <td style='font-size: 8px;'>" . ($producto['contenedores'] ?: '-') . "</td>
                    <td class='text-center' style='font-size: 8px;'>" . date('d/m/Y', strtotime($producto['fecha_mas_antigua'])) . "</td>
                </tr>";
        }
        
        $html .= '
            </tbody>
        </table>
        
        <div class="footer">
            Sistema MIKELO - Reporte generado automáticamente<br>
            Fecha: ' . date('d/m/Y H:i:s') . ' | Usuario: ' . ($_SESSION['usuario'] ?? 'Sistema') . '
        </div>';
        
        return $html;
    }

    private function generarExcelStock($sheet, $data) {
        // Configurar encabezados
        $sheet->setTitle('Stock Depósito');
        
        // Título principal
        $sheet->setCellValue('A1', 'MIKELO - REPORTE DE STOCK EN DEPÓSITO');
        $sheet->mergeCells('A1:G1');
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(16);
        $sheet->getStyle('A1')->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
        
        // Fecha
        $sheet->setCellValue('A2', 'Generado el: ' . date('d/m/Y H:i'));
        $sheet->mergeCells('A2:G2');
        $sheet->getStyle('A2')->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
        
        // Encabezados de columnas
        $headers = ['Código', 'Descripción', 'Total Unidades', 'Peso Bruto (kg)', 'Peso Neto (kg)', 'Contenedores', 'Fecha Más Antigua'];
        $col = 'A';
        foreach ($headers as $header) {
            $sheet->setCellValue($col . '4', $header);
            $sheet->getStyle($col . '4')->getFont()->setBold(true);
            $sheet->getStyle($col . '4')->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
                  ->getStartColor()->setRGB('333333');
            $sheet->getStyle($col . '4')->getFont()->getColor()->setRGB('FFFFFF');
            $col++;
        }
        
        // Datos
        $row = 5;
        $totalUnidades = 0;
        $totalPesoBruto = 0;
        $totalPesoNeto = 0;
        
        foreach ($data as $producto) {
            $sheet->setCellValue('A' . $row, $producto['codigo']);
            $sheet->setCellValue('B' . $row, $producto['descripcion']);
            $sheet->setCellValue('C' . $row, $producto['total_unidades']);
            $sheet->setCellValue('D' . $row, round($producto['total_peso_bruto'], 2));
            $sheet->setCellValue('E' . $row, round($producto['total_peso_neto'], 2));
            $sheet->setCellValue('F' . $row, $producto['contenedores'] ?: '-');
            $sheet->setCellValue('G' . $row, date('d/m/Y', strtotime($producto['fecha_mas_antigua'])));
            
            $totalUnidades += $producto['total_unidades'];
            $totalPesoBruto += $producto['total_peso_bruto'];
            $totalPesoNeto += $producto['total_peso_neto'];
            
            $row++;
        }
        
        // Totales
        $row++;
        $sheet->setCellValue('A' . $row, 'TOTALES:');
        $sheet->setCellValue('C' . $row, $totalUnidades);
        $sheet->setCellValue('D' . $row, round($totalPesoBruto, 2));
        $sheet->setCellValue('E' . $row, round($totalPesoNeto, 2));
        $sheet->getStyle('A' . $row . ':G' . $row)->getFont()->setBold(true);
        
        // Ajustar ancho de columnas
        foreach (range('A', 'G') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }
        
        // Aplicar bordes
        $styleArray = [
            'borders' => [
                'allBorders' => [
                    'borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN,
                ],
            ],
        ];
        $sheet->getStyle('A4:G' . ($row - 1))->applyFromArray($styleArray);
    }

    // Función para crear tabla de auditoría si no existe
    public function crearTablasAuditoria() {
        $sql = "
            CREATE TABLE IF NOT EXISTS movimientos_cambios (
                id INT AUTO_INCREMENT PRIMARY KEY,
                id_movimientos_items INT NOT NULL,
                tipo_cambio ENUM('CONTENEDOR', 'BAJA', 'ESTADO') NOT NULL,
                valor_anterior VARCHAR(255),
                valor_nuevo VARCHAR(255),
                motivo TEXT,
                fecha_cambio DATETIME DEFAULT CURRENT_TIMESTAMP,
                usuario VARCHAR(100),
                FOREIGN KEY (id_movimientos_items) REFERENCES movimientos_items(id)
            )
        ";
        $this->db->exec($sql);
    }
}
?>