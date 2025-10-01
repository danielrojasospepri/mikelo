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
                    $producto['id_contenedor']
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
                ud.nombre as destino
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
                (mi.cnt_peso - c.peso) as peso_neto,
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

    public function obtenerProductosDisponibles() {
        $sql = "
            SELECT 
                mi.id as id_movimiento_item,
                p.id as id_producto,
                p.codigo,
                p.descripcion,
                mi.cnt,
                mi.cnt_peso,
                m.fechaAlta,
                (
                    SELECT COUNT(*)
                    FROM movimientos_items
                    WHERE id_movimientos_items_origen = mi.id
                ) as veces_enviado
            FROM movimientos_items mi
            JOIN productos p ON p.id = mi.id_productos
            JOIN movimientos m ON m.id = mi.id_movimientos
            WHERE mi.id_movimientos_items_origen is null and NOT EXISTS (
                SELECT 1
                FROM movimientos_items mi2
                WHERE mi2.id_movimientos_items_origen = mi.id
            )
            ORDER BY m.fechaAlta DESC
        ";

        $stmt = $this->db->prepare($sql);
        $stmt->execute();
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
        
        return '/mikelo/temp/' . $nombreArchivo;
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
        
        return '/mikelo/temp/' . $nombreArchivo;
    }

    private function generarHTMLLista($envios) {
        $html = '
        <style>
            table { width: 100%; border-collapse: collapse; margin: 20px 0; }
            th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
            th { background-color: #f5f5f5; }
        </style>
        <h1>Lista de Envíos</h1>
        <table>
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Fecha</th>
                    <th>Origen</th>
                    <th>Destino</th>
                    <th>Estado</th>
                    <th>Items</th>
                    <th>Peso Total</th>
                </tr>
            </thead>
            <tbody>';
        
        foreach ($envios as $envio) {
            $html .= "
                <tr>
                    <td>{$envio['id']}</td>
                    <td>{$envio['fechaAlta']}</td>
                    <td>{$envio['origen']}</td>
                    <td>{$envio['destino']}</td>
                    <td>{$envio['ultimo_estado']}</td>
                    <td>{$envio['cantidad_items']}</td>
                    <td>{$envio['peso_total']} kg</td>
                </tr>";
        }
        
        $html .= '</tbody></table>';
        return $html;
    }

    private function generarHTMLDetalle($data) {
        $envio = $data['envio'];
        $productos = $data['productos'];
        
        $html = '
        <style>
            table { width: 100%; border-collapse: collapse; margin: 20px 0; }
            th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
            th { background-color: #f5f5f5; }
            .info { margin-bottom: 20px; }
        </style>
        <h1>Detalle de Envío #' . $envio['id'] . '</h1>
        <div class="info">
            <p><strong>Fecha:</strong> ' . $envio['fechaAlta'] . '</p>
            <p><strong>Origen:</strong> ' . $envio['origen'] . '</p>
            <p><strong>Destino:</strong> ' . $envio['destino'] . '</p>
        </div>
        <h2>Productos</h2>
        <table>
            <thead>
                <tr>
                    <th>Código</th>
                    <th>Descripción</th>
                    <th>Cantidad</th>
                    <th>Contenedor</th>
                    <th>Peso Bruto</th>
                    <th>Peso Neto</th>
                    <th>Estado</th>
                </tr>
            </thead>
            <tbody>';
        
        foreach ($productos as $producto) {
            $html .= "
                <tr>
                    <td>{$producto['codigo']}</td>
                    <td>{$producto['descripcion']}</td>
                    <td>{$producto['cnt']}</td>
                    <td>{$producto['contenedor']}</td>
                    <td>{$producto['cnt_peso']} kg</td>
                    <td>{$producto['peso_neto']} kg</td>
                    <td>{$producto['estado']}</td>
                </tr>";
        }
        
        $html .= '</tbody></table>';
        return $html;
    }

    private function generarExcelLista($sheet, $envios) {
        $sheet->setTitle('Lista de Envíos');
        
        // Encabezados
        $sheet->setCellValue('A1', 'ID');
        $sheet->setCellValue('B1', 'Fecha');
        $sheet->setCellValue('C1', 'Origen');
        $sheet->setCellValue('D1', 'Destino');
        $sheet->setCellValue('E1', 'Estado');
        $sheet->setCellValue('F1', 'Items');
        $sheet->setCellValue('G1', 'Peso Total');
        
        // Datos
        $row = 2;
        foreach ($envios as $envio) {
            $sheet->setCellValue('A'.$row, $envio['id']);
            $sheet->setCellValue('B'.$row, $envio['fechaAlta']);
            $sheet->setCellValue('C'.$row, $envio['origen']);
            $sheet->setCellValue('D'.$row, $envio['destino']);
            $sheet->setCellValue('E'.$row, $envio['ultimo_estado']);
            $sheet->setCellValue('F'.$row, $envio['cantidad_items']);
            $sheet->setCellValue('G'.$row, $envio['peso_total']);
            $row++;
        }
        
        // Autoajustar columnas
        foreach (range('A', 'G') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }
    }

    private function generarExcelDetalle($sheet, $data) {
        $envio = $data['envio'];
        $productos = $data['productos'];
        
        $sheet->setTitle('Detalle de Envío #' . $envio['id']);
        
        // Información del envío
        $sheet->setCellValue('A1', 'Detalle de Envío #' . $envio['id']);
        $sheet->setCellValue('A3', 'Fecha:');
        $sheet->setCellValue('B3', $envio['fechaAlta']);
        $sheet->setCellValue('A4', 'Origen:');
        $sheet->setCellValue('B4', $envio['origen']);
        $sheet->setCellValue('A5', 'Destino:');
        $sheet->setCellValue('B5', $envio['destino']);
        
        // Encabezados de productos
        $sheet->setCellValue('A7', 'Código');
        $sheet->setCellValue('B7', 'Descripción');
        $sheet->setCellValue('C7', 'Cantidad');
        $sheet->setCellValue('D7', 'Contenedor');
        $sheet->setCellValue('E7', 'Peso Bruto');
        $sheet->setCellValue('F7', 'Peso Neto');
        $sheet->setCellValue('G7', 'Estado');
        
        // Datos de productos
        $row = 8;
        foreach ($productos as $producto) {
            $sheet->setCellValue('A'.$row, $producto['codigo']);
            $sheet->setCellValue('B'.$row, $producto['descripcion']);
            $sheet->setCellValue('C'.$row, $producto['cnt']);
            $sheet->setCellValue('D'.$row, $producto['contenedor']);
            $sheet->setCellValue('E'.$row, $producto['cnt_peso']);
            $sheet->setCellValue('F'.$row, $producto['peso_neto']);
            $sheet->setCellValue('G'.$row, $producto['estado']);
            $row++;
        }
        
        // Autoajustar columnas
        foreach (range('A', 'G') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }
    }
}