<?php
namespace App\Model;

class StockDeposito {
    private $db;

    public function __construct($db) {
        $this->db = $db;
    }

    public function obtenerStockAgrupado($filtros = []) {
        $whereConditions = ["mi.id_movimientos_items_origen IS NULL"];
        $whereConditions[] = "mi.cnt > IFNULL((
            SELECT IFNULL(SUM(mi2.cnt), 0)
            FROM movimientos_items mi2
            WHERE mi2.id_movimientos_items_origen = mi.id
        ), 0)";
        
        // Excluir productos dados de baja
        $whereConditions[] = "NOT EXISTS (
            SELECT 1 FROM estados_items_movimientos eim
            JOIN estados e ON eim.id_estados = e.id
            WHERE eim.id_movimientos_items = mi.id
            AND e.nombre = 'BAJA'
        )";
        
        $params = [];

        // Aplicar filtros
        if (!empty($filtros['familia'])) {
            $whereConditions[] = "p.id_tipo_producto = ?";
            $params[] = $filtros['familia'];
        }

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
                SUM(mi.cnt) as total_unidades,
                SUM(mi.cnt_peso) as total_peso_bruto,
                SUM(
                    CASE 
                        WHEN c.peso IS NOT NULL THEN (mi.cnt_peso - c.peso)
                        ELSE mi.cnt_peso
                    END
                ) as total_peso_neto,
                SUM(
                    mi.cnt - IFNULL((
                        SELECT SUM(mi2.cnt)
                        FROM movimientos_items mi2
                        WHERE mi2.id_movimientos_items_origen = mi.id
                    ), 0)
                ) as total_disponible,
                SUM(
                    IFNULL((
                        SELECT SUM(mi2.cnt)
                        FROM movimientos_items mi2
                        WHERE mi2.id_movimientos_items_origen = mi.id
                    ), 0)
                ) as total_enviado,
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
        $whereConditions[] = "mi.cnt > IFNULL((
            SELECT IFNULL(SUM(mi2.cnt), 0)
            FROM movimientos_items mi2
            WHERE mi2.id_movimientos_items_origen = mi.id
        ), 0)";
        
        // Excluir productos dados de baja
        $whereConditions[] = "NOT EXISTS (
            SELECT 1 FROM estados_items_movimientos eim
            JOIN estados e ON eim.id_estados = e.id
            WHERE eim.id_movimientos_items = mi.id
            AND e.nombre = 'BAJA'
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
                (
                    mi.cnt - IFNULL((
                        SELECT SUM(mi2.cnt)
                        FROM movimientos_items mi2
                        WHERE mi2.id_movimientos_items_origen = mi.id
                    ), 0)
                ) as cnt_disponible,
                IFNULL((
                    SELECT SUM(mi2.cnt)
                    FROM movimientos_items mi2
                    WHERE mi2.id_movimientos_items_origen = mi.id
                ), 0) as cnt_enviado,
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

            // Obtener el ID del estado "BAJA" (asumiendo que existe)
            $stmt = $this->db->prepare("SELECT id FROM estados WHERE nombre = 'BAJA' LIMIT 1");
            $stmt->execute();
            $estadoBaja = $stmt->fetch(\PDO::FETCH_ASSOC);

            if (!$estadoBaja) {
                // Crear el estado si no existe (tabla estados solo tiene: id, nombre)
                $stmt = $this->db->prepare("INSERT INTO estados (nombre) VALUES ('BAJA')");
                $stmt->execute();
                $idEstadoBaja = $this->db->lastInsertId();
            } else {
                $idEstadoBaja = $estadoBaja['id'];
            }

            foreach ($bandejas as $idBandeja) {
                // Crear registro de estado de baja (tabla NO tiene columna observaciones)
                $stmt = $this->db->prepare("
                    INSERT INTO estados_items_movimientos (id_movimientos_items, id_estados, fecha_alta, usuario_alta)
                    VALUES (?, ?, NOW(), ?)
                ");
                $stmt->execute([$idBandeja, $idEstadoBaja, $_SESSION['usuario'] ?? 'sistema']);

                // Registrar el cambio en auditoría (aquí SÍ va el motivo)
                $stmt = $this->db->prepare("
                    INSERT INTO movimientos_cambios (id_movimientos_items, tipo_cambio, valor_anterior, valor_nuevo, motivo, fecha_cambio, usuario)
                    VALUES (?, 'BAJA', 'DISPONIBLE', 'BAJA', ?, NOW(), ?)
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

        try {
            $data = $this->obtenerStockAgrupado($filtros);
            
            if (!is_array($data) || empty($data)) {
                throw new \Exception("No se encontraron datos de stock");
            }

            // Configuración minimal para máxima compatibilidad (igual que Envío)
            $mpdf = new \Mpdf\Mpdf([
                'mode' => 'utf-8',
                'format' => 'A4',
                'default_font_size' => 12,
                'default_font' => 'helvetica'
            ]);

            $html = $this->generarHTMLStock($data);
            
            // Validar HTML
            if (empty($html) || strlen(trim($html)) < 10) {
                throw new \Exception("Error: HTML generado está vacío o es inválido");
            }

            $mpdf->WriteHTML($html);
            
            $nombreArchivo = "stock_deposito_" . date('Y-m-d') . ".pdf";
            $rutaArchivo = __DIR__ . '/../../../temp/' . $nombreArchivo;
            
            // Verificar que el directorio temp existe y tiene permisos
            $dirTemp = dirname($rutaArchivo);
            if (!is_dir($dirTemp)) {
                if (!mkdir($dirTemp, 0755, true)) {
                    throw new \Exception("No se pudo crear el directorio temp/. Verifique los permisos del servidor.");
                }
            }
            
            // Verificar permisos de escritura
            if (!is_writable($dirTemp)) {
                throw new \Exception("El directorio temp/ no tiene permisos de escritura. Permisos actuales: " . substr(sprintf('%o', fileperms($dirTemp)), -4));
            }
            
            // Intentar guardar el PDF
            try {
                $mpdf->Output($rutaArchivo, 'F');
            } catch (\Exception $e) {
                throw new \Exception("Error al guardar el archivo PDF: " . $e->getMessage() . " - Verifique permisos en: " . $dirTemp);
            }
            
            // Verificar que el archivo se creó correctamente
            if (!file_exists($rutaArchivo)) {
                throw new \Exception("El archivo PDF no se generó. Ruta: " . $rutaArchivo);
            }
            
            error_log("PDF Stock generado exitosamente: " . $rutaArchivo . " (" . filesize($rutaArchivo) . " bytes)");
            
            return 'temp/' . $nombreArchivo;
            
        } catch (\Mpdf\MpdfException $e) {
            error_log("Error específico de mPDF en Stock: " . $e->getMessage());
            throw new \Exception("Error en la generación PDF: " . $e->getMessage());
        } catch (\Exception $e) {
            error_log("Error general PDF Stock: " . $e->getMessage());
            throw new \Exception("Error al generar PDF: " . $e->getMessage());
        }
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
        
        return 'temp/' . $nombreArchivo;
    }

    private function generarHTMLStock($data) {
        $totalProductos = count($data);
        $totalDisponible = array_sum(array_column($data, 'total_disponible'));
        $totalPesoBruto = array_sum(array_column($data, 'total_peso_bruto'));
        $totalPesoNeto = array_sum(array_column($data, 'total_peso_neto'));
        
        // Función para formatear números sin decimales innecesarios
        $formatNumber = function($num) {
            return rtrim(rtrim(number_format($num, 3, '.', ''), '0'), '.');
        };
        
        // Convertir logo a base64 para embedderlo en el PDF
        $logoPath = __DIR__ . '/../../../img/logo_optimized.png';
        $logoBase64 = '';
        if (file_exists($logoPath)) {
            $logoData = file_get_contents($logoPath);
            $logoBase64 = 'data:image/png;base64,' . base64_encode($logoData);
        }
        
        $html = '
        <style>
            body { margin: 0; padding: 20px; }
            .header { text-align: center; margin-bottom: 30px; border-bottom: 2px solid #333; padding-bottom: 20px; }
            .logo { max-width: 150px; margin-bottom: 10px; }
            .title { font-size: 18px; font-weight: bold; margin: 10px 0; }
            .subtitle { font-size: 12px; color: #666; }
            .resumen { background-color: #f0f0f0; padding: 15px; margin: 20px 0; border: 1px solid #333; }
            .resumen-title { font-weight: bold; font-size: 14px; text-align: center; margin-bottom: 10px; }
            .resumen-grid { width: 100%; }
            .resumen-item { display: inline-block; width: 24%; text-align: center; padding: 5px; }
            .resumen-numero { font-weight: bold; font-size: 16px; display: block; }
            .resumen-label { font-size: 9px; }
            table { width: 100%; border-collapse: collapse; margin: 20px 0; }
            th { background-color: #f0f0f0; font-weight: bold; padding: 8px; border: 1px solid #333; text-align: center; }
            td { padding: 6px; border: 1px solid #333; text-align: left; }
            .numero { text-align: center; }
            .peso { text-align: right; }
            .footer { margin-top: 30px; text-align: center; font-size: 10px; color: #666; }
        </style>
        
        <div class="header">';
        
        if ($logoBase64) {
            $html .= '<img src="' . $logoBase64 . '" class="logo" alt="Logo">';
        }
        
        $html .= '
            <div class="title">REPORTE DE STOCK DISPONIBLE EN DEPÓSITO</div>
            <div class="subtitle">Generado el ' . date('d/m/Y H:i') . '</div>
        </div>
        
        <div class="resumen">
            <div class="resumen-title">RESUMEN GENERAL</div>
            <div class="resumen-grid">
                <div class="resumen-item">
                    <span class="resumen-numero">' . $totalProductos . '</span>
                    <span class="resumen-label">Productos</span>
                </div>
                <div class="resumen-item">
                    <span class="resumen-numero">' . $formatNumber($totalDisponible) . '</span>
                    <span class="resumen-label">Unidades Disponibles</span>
                </div>
                <div class="resumen-item">
                    <span class="resumen-numero">' . $formatNumber($totalPesoBruto) . '</span>
                    <span class="resumen-label">Kg Brutos</span>
                </div>
                <div class="resumen-item">
                    <span class="resumen-numero">' . $formatNumber($totalPesoNeto) . '</span>
                    <span class="resumen-label">Kg Netos</span>
                </div>
            </div>
        </div>
        
        <table>
            <thead>
                <tr>
                    <th style="width: 10%;">Código</th>
                    <th style="width: 30%;">Descripción</th>
                    <th style="width: 10%;">Disponible</th>
                    <th style="width: 12%;">Peso Bruto</th>
                    <th style="width: 12%;">Peso Neto</th>
                    <th style="width: 16%;">Contenedores</th>
                    <th style="width: 10%;">Fecha Ant.</th>
                </tr>
            </thead>
            <tbody>';
        
        foreach ($data as $producto) {
            $html .= '
                <tr>
                    <td class="numero">' . htmlspecialchars($producto['codigo']) . '</td>
                    <td>' . htmlspecialchars($producto['descripcion']) . '</td>
                    <td class="numero">' . $formatNumber($producto['total_disponible']) . '</td>
                    <td class="peso">' . $formatNumber($producto['total_peso_bruto']) . ' kg</td>
                    <td class="peso">' . $formatNumber($producto['total_peso_neto']) . ' kg</td>
                    <td>' . htmlspecialchars($producto['contenedores'] ?: '-') . '</td>
                    <td class="numero">' . date('d/m/Y', strtotime($producto['fecha_mas_antigua'])) . '</td>
                </tr>';
        }
        
        $html .= '
            </tbody>
        </table>
        
        <div class="footer">
            Sistema Mikelo - Gestión de Inventario de Helados<br>
            Reporte generado automáticamente el ' . date('d/m/Y H:i:s') . '
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
        $headers = ['Código', 'Descripción', 'Disponible', 'Peso Bruto (kg)', 'Peso Neto (kg)', 'Contenedores', 'Fecha Más Antigua'];
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
        $totalDisponible = 0;
        $totalPesoBruto = 0;
        $totalPesoNeto = 0;
        
        foreach ($data as $producto) {
            $sheet->setCellValue('A' . $row, $producto['codigo']);
            $sheet->setCellValue('B' . $row, $producto['descripcion']);
            $sheet->setCellValue('C' . $row, $producto['total_disponible']);
            $sheet->setCellValue('D' . $row, round($producto['total_peso_bruto'], 2));
            $sheet->setCellValue('E' . $row, round($producto['total_peso_neto'], 2));
            $sheet->setCellValue('F' . $row, $producto['contenedores'] ?: '-');
            $sheet->setCellValue('G' . $row, date('d/m/Y', strtotime($producto['fecha_mas_antigua'])));
            
            $totalDisponible += $producto['total_disponible'];
            $totalPesoBruto += $producto['total_peso_bruto'];
            $totalPesoNeto += $producto['total_peso_neto'];
            
            $row++;
        }
        
        // Totales
        $row++;
        $sheet->setCellValue('A' . $row, 'TOTALES:');
        $sheet->setCellValue('C' . $row, $totalDisponible);
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