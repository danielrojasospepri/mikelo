<?php
namespace App\Model;

class Envio {
    /**
     * Edita un envío existente: actualiza destino y productos
     * @param int $idMovimiento
     * @param int $destino
     * @param array $productos
     * @return bool
     */
    public function editar($idMovimiento, $destino, $productos) {
        try {
            $this->db->beginTransaction();

            // 1. Actualizar destino del movimiento
            $stmt = $this->db->prepare("UPDATE movimientos SET id_ubicacion_destino = ? WHERE id = ?");
            $stmt->execute([$destino, $idMovimiento]);

            // 2. Eliminar productos actuales del envío
            $stmt = $this->db->prepare("DELETE FROM movimientos_items WHERE id_movimientos = ?");
            $stmt->execute([$idMovimiento]);

            // 3. Insertar productos nuevos/actualizados
            foreach ($productos as $producto) {
                $idContenedor = isset($producto['id_contenedor']) ? $producto['id_contenedor'] : null;
                $idMovItemOrigen = isset($producto['id_movimientos_items_origen']) ? $producto['id_movimientos_items_origen'] : null;
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
                    $idMovItemOrigen,
                    $idContenedor
                ]);
                $idMovimientoItem = $this->db->lastInsertId();

                // Registrar el estado inicial (ENVIADO - items despachados desde depósito)
                $stmt = $this->db->prepare("
                    INSERT INTO estados_items_movimientos (
                        id_estados, id_movimientos_items, fecha_alta, usuario_alta
                    ) VALUES (2, ?, NOW(), ?)
                ");
                $stmt->execute([$idMovimientoItem, $_SESSION['usuario'] ?? 'sistema']);
            }

            $this->db->commit();
            return true;
        } catch (\Exception $e) {
            $this->db->rollBack();
            throw $e;
        }
    }
    private $db;

    public function __construct($db) {
        $this->db = $db;
    }

    public function crear($destino, $productos) {
        try {
            $this->db->beginTransaction();

            // VALIDACIÓN PREVIA: Verificar stock disponible con bloqueo pesimista
            foreach ($productos as $producto) {
                if (isset($producto['id_movimientos_items_origen'])) {
                    $stmt = $this->db->prepare("
                        SELECT 
                            mi.id,
                            mi.cnt,
                            mi.cnt - IFNULL((
                                SELECT SUM(mi2.cnt)
                                FROM movimientos_items mi2
                                WHERE mi2.id_movimientos_items_origen = mi.id
                            ), 0) AS disponible
                        FROM movimientos_items mi
                        WHERE mi.id = ?
                        FOR UPDATE
                    ");
                    $stmt->execute([$producto['id_movimientos_items_origen']]);
                    $item = $stmt->fetch(\PDO::FETCH_ASSOC);
                    
                    if (!$item) {
                        throw new \Exception("Producto ID {$producto['id_movimientos_items_origen']} no encontrado");
                    }
                    
                    $disponible = (float) $item['disponible'];
                    $solicitado = (float) ($producto['cantidad'] ?? 0);
                    
                    if ($solicitado > $disponible) {
                        throw new \Exception(
                            "Stock insuficiente para producto ID {$producto['id_movimientos_items_origen']}. " .
                            "Disponible: {$disponible}, solicitado: {$solicitado}"
                        );
                    }
                }
            }

            // 1. Crear el movimiento principal
            $stmt = $this->db->prepare("
                INSERT INTO movimientos (fechaAlta, id_ubicacion_origen, id_ubicacion_destino, usuario_alta)
                VALUES (NOW(), 1, ?, ?)
            ");
            $stmt->execute([$destino, $_SESSION['usuario'] ?? 'sistema']);
            $idMovimiento = $this->db->lastInsertId();

            // 2. Insertar los productos del envio
            foreach ($productos as $producto) {
                if (isset($producto['id_movimientos_items_origen'])) {
                    // EDICIÓN: Validar cantidad disponible ANTES de insertar
                    $stmt = $this->db->prepare("
                        SELECT 
                            mi.cnt as cnt_original,
                            (mi.cnt - IFNULL((
                                SELECT IFNULL(SUM(mi2.cnt), 0)
                                FROM movimientos_items mi2
                                WHERE mi2.id_movimientos_items_origen = mi.id
                            ), 0)) as cnt_disponible
                        FROM movimientos_items mi
                        WHERE mi.id = ?
                    ");
                    $stmt->execute([$producto['id_movimientos_items_origen']]);
                    $disponibilidad = $stmt->fetch(\PDO::FETCH_ASSOC);
                    
                    if (!$disponibilidad) {
                        throw new \Exception("Producto origen no encontrado: {$producto['id_movimientos_items_origen']}");
                    }
                    
                    if ($producto['cantidad'] > $disponibilidad['cnt_disponible']) {
                        throw new \Exception(
                            "Cantidad solicitada ({$producto['cantidad']}) excede cantidad disponible ({$disponibilidad['cnt_disponible']})"
                        );
                    }
                    
                    // Obtener el contenedor y el peso del item origen
                    $stmt = $this->db->prepare("
                        SELECT id_contenedor, cnt_peso FROM movimientos_items 
                        WHERE id = ?
                    ");
                    $stmt->execute([$producto['id_movimientos_items_origen']]);
                    $itemOrigen = $stmt->fetch();
                    $idContenedor = $itemOrigen ? $itemOrigen['id_contenedor'] : null;
                    // Usar el peso del item origen si no se proveyó explícitamente
                    $pesoOrigen = $itemOrigen ? (float)$itemOrigen['cnt_peso'] : 0;
                    $idMovItemOrigen = $producto['id_movimientos_items_origen'];
                } else {
                    // ALTA NUEVA: No hay referencia, ni validación de stock ni contenedor
                    $idContenedor = null;
                    $idMovItemOrigen = null;
                    $pesoOrigen = 0;
                }
                // Insertar el item del movimiento
                $stmt = $this->db->prepare("
                    INSERT INTO movimientos_items (
                        id_movimientos, id_productos, cnt, cnt_peso,
                        id_movimientos_items_origen, id_contenedor
                    ) VALUES (?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([
                    $idMovimiento,
                    isset($producto['id_productos']) ? $producto['id_productos'] : null,
                    isset($producto['cantidad']) ? $producto['cantidad'] : null,
                    isset($producto['peso']) ? (float)$producto['peso'] : $pesoOrigen,
                    $idMovItemOrigen,
                    $idContenedor
                ]);
                $idMovimientoItem = $this->db->lastInsertId();

                // Registrar el estado inicial (ENVIADO - items despachados desde depósito)
                $stmt = $this->db->prepare("
                    INSERT INTO estados_items_movimientos (
                        id_estados, id_movimientos_items, fecha_alta, usuario_alta
                    ) VALUES (2, ?, NOW(), ?)
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
            JOIN productos p ON p.id = mi.id_productos
            WHERE 1=1
        ";

        $params = [];

        if (!empty($filtros['familia'])) {
            $sql .= " AND p.id_tipo_producto = ?";
            $params[] = $filtros['familia'];
        }

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
                        AND eim2.id = (
                            SELECT MAX(id)
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
        // Obtener informacion del envio
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
            throw new \Exception("Envio no encontrado");
        }

        // Obtener productos del envio
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

    /**
     * Obtiene productos disponibles para envio desde el deposito central
     * 
     * Soporta 3 tipos de busqueda:
     * 1. BUSQUEDA MANUAL: $filtros['filtro'] = texto libre
     *    - Busca en p.codigo y p.descripcion con LIKE
     *    - Devuelve multiples resultados para seleccion
     * 
     * 2. CODIGO DE BARRAS TIPO 20 (UNIDADES): $filtros['codigo'] + $filtros['cantidad']
     *    - Frontend parsea: "20" + codigo(5) + unidades(5) + verificador(1)
     *    - Ejemplo: 2000123000001 => codigo=123, cantidad=1
     *    - Backend busca producto por codigo exacto
     *    - Cantidad inicial=1, editable hasta stock disponible
     * 
     * 3. CODIGO DE BARRAS TIPO 21 (PESO): $filtros['codigo'] + $filtros['peso']
     *    - Frontend parsea: "21" + codigo(5) + gramos(5) + verificador(1)
     *    - Ejemplo: 2100123041250 => codigo=123, peso=4125g => 4.125kg
     *    - Backend busca producto con PESO EXACTO (sin tolerancia)
     *    - Cantidad siempre=1 (no editable, peso especifico)
     *    - Si no encuentra: error "No hay stock con ese peso"
     * 
     * REGLAS DE DISPONIBILIDAD:
     * - Solo items originales (id_movimientos_items_origen IS NULL)
     * - Con stock disponible (cnt > suma de items derivados)
     * - Estado actual = NUEVO
     * 
     * @param array $filtros ['codigo'=>string, 'cantidad'=>int, 'peso'=>float, 'filtro'=>string]
     * @return array Lista de productos disponibles con campos: id_movimiento_item, id_producto, 
     *               codigo, descripcion, cnt, cnt_peso, contenedor, peso_contenedor, peso_neto, etc.
     */
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
                ) as estado_actual,
                (mi.cnt - IFNULL((
                    SELECT IFNULL(SUM(mi2.cnt), 0)
                    FROM movimientos_items mi2
                    WHERE mi2.id_movimientos_items_origen = mi.id
                ), 0)) as cnt_disponible
            FROM movimientos_items mi
            JOIN productos p ON p.id = mi.id_productos
            JOIN movimientos m ON m.id = mi.id_movimientos
            LEFT JOIN contenedores c ON c.id = mi.id_contenedor
            WHERE mi.id_movimientos_items_origen IS NULL 
            AND mi.cnt > ifnull((
                SELECT ifnull(sum(mi2.cnt), 0)
                FROM movimientos_items mi2
                WHERE mi2.id_movimientos_items_origen = mi.id
            ), 0)
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
        $hayBusquedaPorCantidad = !empty($filtros['cantidad']);
        $hayBusquedaPorPeso = !empty($filtros['peso']);
        $hayBusquedaPorCodigo = !empty($filtros['codigo']);

        // Filtro por codigo de producto (SIEMPRE se aplica si viene)
        if ($hayBusquedaPorCodigo) {
            $sql .= " AND p.codigo = ?";
            $params[] = $filtros['codigo'];
        }

        // Filtro por cantidad: BÚSQUEDA INTELIGENTE 3-PASOS
        if ($hayBusquedaPorCantidad) {
            // PASO 1: Buscar cantidad EXACTA
            $sqlPaso1 = $sql . " AND mi.cnt = ? ORDER BY m.fechaAlta ASC LIMIT 1";
            $paramsPaso1 = array_merge($params, [$filtros['cantidad']]);
            
            $stmt = $this->db->prepare($sqlPaso1);
            $stmt->execute($paramsPaso1);
            $resultados = $stmt->fetchAll(\PDO::FETCH_ASSOC);
            
            // Si no encuentra exacto, PASO 2: Buscar cantidad SUPERIOR
            if (empty($resultados)) {
                $sqlPaso2 = $sql . " AND mi.cnt > ? ORDER BY mi.cnt ASC, m.fechaAlta ASC LIMIT 1";
                $paramsPaso2 = array_merge($params, [$filtros['cantidad']]);
                
                $stmt = $this->db->prepare($sqlPaso2);
                $stmt->execute($paramsPaso2);
                $resultados = $stmt->fetchAll(\PDO::FETCH_ASSOC);
            }
            
            // Si tampoco encuentra, PASO 3: Buscar SIN restricción de cantidad (búsqueda manual)
            if (empty($resultados)) {
                $sqlPaso3 = $sql . " ORDER BY m.fechaAlta DESC";
                
                $stmt = $this->db->prepare($sqlPaso3);
                $stmt->execute($params);
                $resultados = $stmt->fetchAll(\PDO::FETCH_ASSOC);
            }
            
            return $resultados;
        }

        // Filtro por peso EXACTO (codigo de barras tipo 21)
        // Sin tolerancia: la etiqueta leida es la misma que se uso al dar de alta
        if ($hayBusquedaPorPeso) {
            $sql .= " AND mi.cnt_peso = ?";
            $params[] = $filtros['peso'];
        }

        // Filtro general por texto (codigo o descripcion)
        if (!empty($filtros['filtro'])) {
            $sql .= " AND (p.codigo LIKE ? OR p.descripcion LIKE ?)";
            $filtroTexto = '%' . $filtros['filtro'] . '%';
            $params[] = $filtroTexto;
            $params[] = $filtroTexto;
        }

        // Ordenamiento por defecto
        if (!empty($filtros['peso'])) {
            // Para búsqueda por peso exacto, tomar el más antiguo
            $sql .= " ORDER BY m.fechaAlta ASC LIMIT 1";
        } else {
            // Para búsqueda manual o general, tomar más recientes primero
            $sql .= " ORDER BY m.fechaAlta DESC";
        }
        
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

        try {
            // Validar datos antes de procesar
            if ($id) {
                $data = $this->obtenerDetalleEnvio($id);
                if (!$data || !isset($data['envio']) || !isset($data['productos'])) {
                    throw new \Exception("No se encontraron datos para el envio #$id");
                }
            } else {
                $data = $this->obtenerEnvios($filtros);
                if (!is_array($data) || empty($data)) {
                    throw new \Exception("No se encontraron envios con los filtros especificados");
                }
            }

            // Configuracion absolutamente minima para evitar errores de arrays
            $mpdf = new \Mpdf\Mpdf([
                'mode' => 'utf-8',
                'format' => 'A4',
                'default_font_size' => 12,
                'default_font' => 'helvetica'
            ]);

            // Generar HTML segun el tipo
            if ($id) {
                $html = $this->generarHTMLDetalleMinimal($data);
                $nombreArchivo = "envio_" . $id . ".pdf";
            } else {
                $html = $this->generarHTMLListaMinimal($data);
                $nombreArchivo = "envios_" . date('Y-m-d') . ".pdf";
            }

            // Validar que el HTML no este vacio
            if (empty($html) || strlen(trim($html)) < 10) {
                throw new \Exception("Error: HTML generado esta vacio o es invalido");
            }

            $mpdf->WriteHTML($html);
            
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
            
            // Verificar que el archivo se creo correctamente
            if (!file_exists($rutaArchivo)) {
                throw new \Exception("El archivo PDF no se genero. Ruta: " . $rutaArchivo);
            }
            
            error_log("PDF Envio generado exitosamente: " . $rutaArchivo . " (" . filesize($rutaArchivo) . " bytes)");
            
            return 'temp/' . $nombreArchivo;
            
        } catch (\Mpdf\MpdfException $e) {
            error_log("Error especifico de mPDF: " . $e->getMessage());
            throw new \Exception("Error en la generacion PDF: " . $e->getMessage());
        } catch (\Exception $e) {
            error_log("Error general PDF: " . $e->getMessage());
            error_log("Archivo: " . $e->getFile() . " Linea: " . $e->getLine());
            
            throw new \Exception("Error al generar PDF: " . $e->getMessage());
        }
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
                font-family: courier; 
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
            <div style="font-size: 14px; color: #666;">Sistema de Gestion de Helados</div>
            <div class="document-title">REPORTE DE ENVIOS</div>
        </div>
        
        <div class="info-section">
            <strong>Fecha de Generacion:</strong> ' . $fechaGeneracion . '<br>
            <strong>Total de Envios:</strong> ' . $totalEnvios . '
        </div>
        
        <table>
            <thead>
                <tr>
                    <th style="width: 8%;">Ndeg Envio</th>
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
            Total de Envios: ' . $totalEnvios . '<br>
            Total de Items: ' . $itemsTotalGeneral . '<br>
            Peso Total: ' . number_format($pesoTotalGeneral, 2) . ' kg
        </div>
        
        <div class="footer">
            <p>Reporte generado automaticamente por Sistema Mikelo - ' . $fechaGeneracion . '</p>
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
                font-family: courier; 
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
            <div style="font-size: 12px; color: #666;">Sistema de Gestion de Helados</div>
            <div class="document-title">REMITO</div>
            <div class="numero-remito">Ndeg ' . str_pad($envio['id'], 8, '0', STR_PAD_LEFT) . '</div>
        </div>
        
        <div class="remito-info">
            <div class="info-origen">
                <div class="info-title">ORIGEN</div>
                <div class="info-content">
                    <strong>' . htmlspecialchars($envio['origen']) . '</strong><br>
                    Deposito Central<br>
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
                    <th style="width: 12%;">Codigo</th>
                    <th style="width: 40%;">Descripcion</th>
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
        
        // Rellenar filas vacias si hay menos de 8 productos (para mantener estructura)
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
            <p>Documento generado automaticamente por Sistema Mikelo - ' . date('d/m/Y H:i') . '</p>
        </div>';
        
        return $html;
    }

    private function generarExcelLista($sheet, $envios) {
        $sheet->setTitle('Reporte de Envios');
        
        // Encabezado de la empresa
        $sheet->setCellValue('A1', 'MIKELO - Sistema de Gestion de Helados');
        $sheet->mergeCells('A1:G1');
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(16);
        $sheet->getStyle('A1')->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
        
        $sheet->setCellValue('A2', 'REPORTE DE ENVIOS');
        $sheet->mergeCells('A2:G2');
        $sheet->getStyle('A2')->getFont()->setBold(true)->setSize(14);
        $sheet->getStyle('A2')->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
        
        $sheet->setCellValue('A3', 'Generado el: ' . date('d/m/Y H:i'));
        $sheet->mergeCells('A3:G3');
        $sheet->getStyle('A3')->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
        
        // Encabezados
        $headers = ['Ndeg Envio', 'Fecha', 'Hora', 'Origen', 'Destino', 'Estado', 'Items', 'Peso Total (kg)'];
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
            $sheet->setCellValue('H'.$row, number_format($envio['peso_total'], 3));
            
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
        $sheet->setCellValue('F'.$row, count($envios) . ' envios');
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
        $sheet->setCellValue('A1', 'MIKELO - Sistema de Gestion de Helados');
        $sheet->mergeCells('A1:F1');
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(16);
        $sheet->getStyle('A1')->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
        
        $sheet->setCellValue('A2', 'REMITO Nro ' . str_pad($envio['id'], 8, '0', STR_PAD_LEFT));
        $sheet->mergeCells('A2:F2');
        $sheet->getStyle('A2')->getFont()->setBold(true)->setSize(14);
        $sheet->getStyle('A2')->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
        
        // Informacion del envio
        $sheet->setCellValue('A4', 'INFORMACION DEL ENVIO');
        $sheet->getStyle('A4')->getFont()->setBold(true);
        
        $sheet->setCellValue('A5', 'Fecha:');
        $sheet->setCellValue('B5', date('d/m/Y H:i', strtotime($envio['fechaAlta'])));
        $sheet->setCellValue('A6', 'Origen:');
        $sheet->setCellValue('B6', $envio['origen']);
        $sheet->setCellValue('A7', 'Destino:');
        $sheet->setCellValue('B7', $envio['destino']);
        $sheet->setCellValue('A8', 'Estado:');
        $sheet->setCellValue('B8', $envio['ultimo_estado'] ?? 'N/A');
        $sheet->setCellValue('A9', 'Usuario:');
        $sheet->setCellValue('B9', $envio['usuario_alta'] ?? 'Sistema');
        
        // Encabezados de productos
        $row = 11;
        $headers = ['Codigo', 'Descripcion', 'Cantidad', 'Contenedor', 'Peso Bruto (kg)', 'Peso Neto (kg)'];
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
            $sheet->setCellValue('E' . $row, number_format($producto['cnt_peso'], 3));
            $sheet->setCellValue('F' . $row, number_format($producto['peso_neto'], 3));
            $row++;
        }
        
        // Totales
        $totalItems = count($productos);
        $pesoTotal = array_sum(array_column($productos, 'cnt_peso'));
        
        $row += 2;
        $sheet->setCellValue('D' . $row, 'TOTALES:');
        $sheet->getStyle('D' . $row)->getFont()->setBold(true);
        $sheet->setCellValue('E' . $row, 'Items: ' . $totalItems);
        $sheet->setCellValue('F' . $row, 'Peso: ' . number_format($pesoTotal, 3) . ' kg');
        
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

            // Obtener todos los items del envio
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

            // Obtener todos los items del envio
            $stmt = $this->db->prepare("
                SELECT id, id_movimientos_items_origen 
                FROM movimientos_items 
                WHERE id_movimientos = ?
            ");
            $stmt->execute([$idEnvio]);
            $items = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            // Cambiar estado de todos los items del envio a CANCELADO (4)
            // MANTENER EL HISTORIAL COMPLETO
            foreach ($items as $item) {
                $stmt = $this->db->prepare("
                    INSERT INTO estados_items_movimientos (
                        id_estados, id_movimientos_items, fecha_alta, usuario_alta
                    ) VALUES (4, ?, NOW(), ?)
                ");
                $stmt->execute([$item['id'], $_SESSION['usuario'] ?? 'sistema']);
            }

            // SOLUCIIN IPTIMA: En lugar de eliminar registros, limpiar las referencias
            // Esto mantiene el historial completo pero libera los productos al stock
            
            // Para cada item del envio cancelado, limpiar su id_movimientos_items_origen
            // Esto hace que el producto original vuelva a estar disponible
            $stmt = $this->db->prepare("
                UPDATE movimientos_items 
                SET id_movimientos_items_origen = NULL 
                WHERE id_movimientos = ?
            ");
            $stmt->execute([$idEnvio]);

            // Los productos ahora vuelven al stock porque:
            // 1. El query de productos disponibles busca items con id_movimientos_items_origen IS NULL
            // 2. El query tambien verifica que NO sean referenciados por otros items
            // 3. Al limpiar las referencias, los productos originales vuelven a cumplir ambas condiciones

            $this->db->commit();
            return true;
        } catch (\Exception $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    private function generarHTMLListaMinimal($envios) {
        if (!is_array($envios) || empty($envios)) {
            return '<h1>Lista de Envios</h1><p>No hay envios para mostrar.</p>';
        }

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
            .fecha-generacion { font-size: 10px; color: #666; margin-bottom: 20px; }
            table { width: 100%; border-collapse: collapse; margin-top: 20px; }
            th { background-color: #f0f0f0; font-weight: bold; padding: 8px; border: 1px solid #333; text-align: center; }
            td { padding: 6px 8px; border: 1px solid #333; text-align: left; }
            .numero { text-align: center; }
            .peso { text-align: right; }
            .footer { margin-top: 30px; text-align: center; font-size: 10px; color: #666; }
        </style>
        
        <div class="header">';
        
        if ($logoBase64) {
            $html .= '<img src="' . $logoBase64 . '" class="logo" alt="Logo">';
        }
        
        $html .= '
            <div class="title">Lista de Envios</div>
            <div class="fecha-generacion">Generado el: ' . date('d/m/Y H:i') . '</div>
        </div>
        
        <table>
            <thead>
                <tr>
                    <th style="width: 8%;">ID</th>
                    <th style="width: 15%;">Fecha</th>
                    <th style="width: 20%;">Origen</th>
                    <th style="width: 20%;">Destino</th>
                    <th style="width: 15%;">Estado</th>
                    <th style="width: 10%;">Items</th>
                    <th style="width: 12%;">Peso Total</th>
                </tr>
            </thead>
            <tbody>';
        
        foreach ($envios as $envio) {
            if (!is_array($envio)) continue;
            
            $fecha = isset($envio['fechaAlta']) ? date('d/m/Y', strtotime($envio['fechaAlta'])) : 'N/A';
            
            $html .= '<tr>';
            $html .= '<td class="numero">' . htmlspecialchars($envio['id'] ?? 'N/A') . '</td>';
            $html .= '<td class="numero">' . htmlspecialchars($fecha) . '</td>';
            $html .= '<td>' . htmlspecialchars($envio['origen'] ?? 'N/A') . '</td>';
            $html .= '<td>' . htmlspecialchars($envio['destino'] ?? 'N/A') . '</td>';
            $html .= '<td>' . htmlspecialchars($envio['ultimo_estado'] ?? 'N/A') . '</td>';
            $html .= '<td class="numero">' . htmlspecialchars($envio['cantidad_items'] ?? '0') . '</td>';
            $html .= '<td class="peso">' . number_format($envio['peso_total'] ?? 0, 2) . ' kg</td>';
            $html .= '</tr>';
        }
        
        $totalEnvios = count($envios);
        $pesoTotal = array_sum(array_column($envios, 'peso_total'));
        
        $html .= '
            </tbody>
            <tfoot>
                <tr style="background-color: #e0e0e0; font-weight: bold;">
                    <td colspan="5" style="text-align: right; padding-right: 10px;">TOTALES:</td>
                    <td class="numero">' . $totalEnvios . '</td>
                    <td class="peso">' . number_format($pesoTotal, 2) . ' kg</td>
                </tr>
            </tfoot>
        </table>
        
        <div class="footer">
            Sistema Mikelo - Gestion de Inventario de Helados<br>
            Documento generado automaticamente
        </div>';
        
        return $html;
    }

    private function generarHTMLDetalleMinimal($data) {
        if (!is_array($data) || !isset($data['envio']) || !isset($data['productos'])) {
            return '<h1>Error</h1><p>Datos de envio no validos.</p>';
        }

        $envio = $data['envio'];
        $productos = $data['productos'];
        
        // Convertir logo a base64
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
            .subtitle { font-size: 14px; font-weight: bold; margin: 15px 0 10px 0; }
            .info-section { margin: 20px 0; }
            .info-row { margin: 5px 0; }
            .label { font-weight: bold; display: inline-block; width: 100px; }
            table { width: 100%; border-collapse: collapse; margin-top: 15px; }
            th { background-color: #f0f0f0; font-weight: bold; padding: 8px; border: 1px solid #333; text-align: center; }
            td { padding: 6px 8px; border: 1px solid #333; text-align: left; }
            .numero { text-align: center; }
            .peso { text-align: right; }
            .footer { margin-top: 30px; text-align: center; font-size: 10px; color: #666; border-top: 1px solid #ccc; padding-top: 15px; }
            .totals { background-color: #f8f8f8; padding: 10px; margin-top: 20px; border: 1px solid #333; }
        </style>
        
        <div class="header">';
        
        if ($logoBase64) {
            $html .= '<img src="' . $logoBase64 . '" class="logo" alt="Logo">';
        }
        
        $html .= '
            <div class="title">REMITO DE ENVIO #' . htmlspecialchars($envio['id'] ?? 'N/A') . '</div>
        </div>
        
        <div class="info-section">
            <div class="subtitle">Informacion del Envio</div>
            <div class="info-row">
                <span class="label">Fecha:</span> ' . (isset($envio['fechaAlta']) ? date('d/m/Y H:i', strtotime($envio['fechaAlta'])) : 'N/A') . '
            </div>
            <div class="info-row">
                <span class="label">Origen:</span> ' . htmlspecialchars($envio['origen'] ?? 'N/A') . '
            </div>
            <div class="info-row">
                <span class="label">Destino:</span> ' . htmlspecialchars($envio['destino'] ?? 'N/A') . '
            </div>
            <div class="info-row">
                <span class="label">Estado:</span> ' . htmlspecialchars($envio['ultimo_estado'] ?? 'N/A') . '
            </div>
        </div>
        
        <div class="subtitle">Detalle de Productos</div>';
        
        if (!is_array($productos) || empty($productos)) {
            $html .= '<p>No hay productos en este envio.</p>';
        } else {
            $html .= '
            <table>
                <thead>
                    <tr>
                        <th style="width: 12%;">Codigo</th>
                        <th style="width: 35%;">Descripcion</th>
                        <th style="width: 8%;">Cant.</th>
                        <th style="width: 15%;">Contenedor</th>
                        <th style="width: 10%;">Peso Bruto</th>
                        <th style="width: 10%;">Peso Cont.</th>
                        <th style="width: 10%;">Peso Neto</th>
                    </tr>
                </thead>
                <tbody>';
            
            $totalItems = 0;
            $pesoBrutoTotal = 0;
            $pesoContenedorTotal = 0;
            $pesoNetoTotal = 0;
            
            foreach ($productos as $producto) {
                if (!is_array($producto)) continue;
                
                $cantidad = $producto['cnt'] ?? 0;
                $pesoBruto = $producto['cnt_peso'] ?? 0;
                $pesoContenedor = $producto['peso_contenedor'] ?? 0;
                $pesoNeto = $producto['peso_neto'] ?? $pesoBruto;
                $contenedor = $producto['contenedor'] ?? '-';
                
                // Si el peso neto es negativo, usar el peso bruto
                if ($pesoNeto < 0) {
                    $pesoNeto = $pesoBruto;
                    $pesoContenedor = 0; // Resetear peso contenedor si da negativo
                }
                
                $totalItems += $cantidad;
                $pesoBrutoTotal += $pesoBruto;
                $pesoContenedorTotal += $pesoContenedor;
                $pesoNetoTotal += $pesoNeto;
                
                $html .= '<tr>';
                $html .= '<td class="numero">' . htmlspecialchars($producto['codigo'] ?? 'N/A') . '</td>';
                $html .= '<td>' . htmlspecialchars($producto['descripcion'] ?? 'N/A') . '</td>';
                $html .= '<td class="numero">' . number_format($cantidad, 3) . '</td>';
                $html .= '<td>' . htmlspecialchars($contenedor) . '</td>';
                $html .= '<td class="peso">' . number_format($pesoBruto, 3) . ' kg</td>';
                $html .= '<td class="peso">' . number_format($pesoContenedor, 3) . ' kg</td>';
                $html .= '<td class="peso">' . number_format($pesoNeto, 3) . ' kg</td>';
                $html .= '</tr>';
            }
            
            $html .= '
                </tbody>
                <tfoot>
                    <tr style="background-color: #e0e0e0; font-weight: bold;">
                        <td colspan="2" style="text-align: right; padding-right: 10px;">TOTALES:</td>
                        <td class="numero">' . number_format($totalItems) . '</td>
                        <td></td>
                        <td class="peso">' . number_format($pesoBrutoTotal, 3) . ' kg</td>
                        <td class="peso">' . number_format($pesoContenedorTotal, 3) . ' kg</td>
                        <td class="peso">' . number_format($pesoNetoTotal, 3) . ' kg</td>
                    </tr>
                </tfoot>
            </table>';
        }
        
        $html .= '
        <div class="footer">
            Sistema Mikelo - Gestion de Inventario de Helados<br>
            Remito generado el ' . date('d/m/Y H:i') . '<br>
            Documento valido para control de envios
        </div>';
        
        return $html;
    }

    /**
     * Exportar remito preimpreso en PDF (guarda en temp/ como los otros reportes)
     */
    public function exportarPDFPreimpreso($id = null, $filtros = []) {
        require_once __DIR__ . '/../../vendor/autoload.php';

        try {
            // Debe tener un ID especifico
            if (!$id) {
                throw new \Exception("El remito preimpreso requiere un ID de envio especifico");
            }

            // Generar HTML
            $html = $this->generarHTMLRemitoPreimpreso($id);

            // Configurar mPDF
            $mpdf = new \Mpdf\Mpdf([
                'mode' => 'utf-8',
                'format' => 'A4',
                'margin_left' => 0,
                'margin_right' => 0,
                'margin_top' => 0,
                'margin_bottom' => 0
            ]);

            $mpdf->WriteHTML($html);

            $nombreArchivo = "remito_preimpreso_" . str_pad($id, 8, '0', STR_PAD_LEFT) . ".pdf";
            $rutaArchivo = __DIR__ . '/../../../temp/' . $nombreArchivo;

            // Verificar directorio temp
            $dirTemp = dirname($rutaArchivo);
            if (!is_dir($dirTemp)) {
                mkdir($dirTemp, 0755, true);
            }

            // Guardar PDF
            $mpdf->Output($rutaArchivo, 'F');

            return 'temp/' . $nombreArchivo;

        } catch (\Exception $e) {
            error_log("Error al generar PDF preimpreso: " . $e->getMessage());
            throw new \Exception("Error al generar PDF: " . $e->getMessage());
        }
    }

    /**
     * Generar HTML para remito preimpreso STARK IND (Formato A4)
     * 
     * CONFIGURACION DE POSICIONES Y PAGINACION:
     * ==========================================
     * Variables configurables al inicio del metodo para ajustar el diseno:
     * 
     * - PRODUCTOS_MAX_POR_HOJA: Numero maximo de productos por pagina (default: 12)
     *   Aumentar este valor si el papel tiene mas espacio vertical
     *   Reducir si hay desbordamientos
     * 
     * - POS_CLIENTE_TOP: Distancia desde borde superior a banda cliente (default: 60mm)
     * - POS_CLIENTE_ALTO: Altura de la banda de informacion del cliente (default: 15mm)
     * - POS_TABLA_TOP: Distancia desde borde superior a inicio tabla productos (default: 95mm)
     * - POS_FOOTER_BOTTOM: Distancia desde borde inferior a franja gris (default: 30mm)
     * - POS_DATOS_BOTTOM: Distancia desde borde inferior a datos remito (default: 10mm)
     * 
     * - TABLA_FONT_SIZE: Tamano de fuente en tabla (default: 9pt)
     * - TABLA_PADDING: Padding interno de celdas (default: 1mm 2mm)
     * - TABLA_HEADER_ALTO: Altura del encabezado de tabla (default: 8mm)
     * 
     * PAGINACION:
     * ===========
     * Si el envio tiene mas productos que PRODUCTOS_MAX_POR_HOJA, se generan
     * multiples paginas con el mismo header/footer y numeracion "Hoja X de Y"
     */
    public function generarHTMLRemitoPreimpreso($idMovimiento) {
        // ========== CONFIGURACION DE PAGINACION ==========
        // AJUSTAR ESTE VALOR segun espacio disponible en papel preimpreso
        $PRODUCTOS_MAX_POR_HOJA = 25;
        
        // ========== CONFIGURACION DE POSICIONES (en mm) ==========
        $POS_CLIENTE_TOP = 60;      // Distancia desde arriba a banda cliente
        $POS_CLIENTE_ALTO = 15;     // Alto de banda cliente
        $POS_TABLA_TOP = 95;        // Distancia desde arriba a tabla productos
        $POS_FOOTER_BOTTOM = 30;    // Distancia desde abajo a footer gris
        $POS_DATOS_BOTTOM = 35;     // Distancia desde abajo a datos remito (subir 2 renglones)
        
        // ========== CONFIGURACION DE TABLA ==========
$TABLA_FONT_SIZE = '8pt';        // Achicar letra
$TABLA_PADDING = '0.5mm 1mm';    // Menos espacio vertical
$TABLA_HEADER_ALTO = '6mm';      // Encabezado más bajo
        
        // Obtener datos del envio
        $stmt = $this->db->prepare("
            SELECT 
                m.*,
                uo.nombre as ubicacion_origen,
                ud.id as id_destino,
                ud.nombre as ubicacion_destino,
                ud.razon_social,
                ud.domicilio,
                ud.localidad,
                ud.codigo_postal,
                ud.provincia,
                ud.cuit,
                ud.condicion_iva,
                (
                    SELECT e.nombre 
                    FROM estados_items_movimientos eim
                    JOIN estados e ON e.id = eim.id_estados
                    WHERE eim.id_movimientos_items IN (
                        SELECT id FROM movimientos_items WHERE id_movimientos = m.id
                    )
                    ORDER BY eim.fecha_alta DESC
                    LIMIT 1
                ) as estado_actual
            FROM movimientos m
            LEFT JOIN ubicaciones uo ON uo.id = m.id_ubicacion_origen
            LEFT JOIN ubicaciones ud ON ud.id = m.id_ubicacion_destino
            WHERE m.id = ?
        ");
        $stmt->execute([$idMovimiento]);
        $movimiento = $stmt->fetch(\PDO::FETCH_ASSOC);

        if (!$movimiento) {
            throw new \Exception('Envio no encontrado');
        }

        // Obtener productos del envio
        $stmt = $this->db->prepare("
            SELECT 
                mi.cnt,
                mi.cnt_peso as peso_bruto,
                p.codigo,
                p.descripcion,
                c.nombre as contenedor,
                c.peso as peso_contenedor
            FROM movimientos_items mi
            LEFT JOIN productos p ON p.id = mi.id_productos
            LEFT JOIN contenedores c ON c.id = mi.id_contenedor
            WHERE mi.id_movimientos = ?
            ORDER BY p.descripcion
        ");
        $stmt->execute([$idMovimiento]);
        $productos = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        // Calcular totales
        $pesoTotalBruto = 0;
        $pesoTotalNeto = 0;
        $cantidadTotal = 0;
        foreach ($productos as &$producto) {
            $pesoBruto = floatval($producto['peso_bruto']);
            $pesoContenedor = floatval($producto['peso_contenedor'] ?? 0);
            $pesoNeto = $pesoBruto - $pesoContenedor;
            
            // Agregar peso neto al array
            $producto['peso_neto'] = $pesoNeto;
            
            $pesoTotalBruto += $pesoBruto;
            $pesoTotalNeto += $pesoNeto;
            $cantidadTotal += floatval($producto['cnt']);
        }
        unset($producto); // Romper referencia
        
        // Calcular paginacion
        $totalProductos = count($productos);
        $totalPaginas = ceil($totalProductos / $PRODUCTOS_MAX_POR_HOJA);
        $paginasProductos = array_chunk($productos, $PRODUCTOS_MAX_POR_HOJA);

        // ========== INICIO HTML CON ESTILOS ==========
        $html = '
        <html>
        <head>
            <style>
                @page {
                    margin: 0;
                }
                body { 
                    font-family: Arial, sans-serif; 
                    margin: 0 15mm; 
                    padding: 0;
                    box-sizing: border-box;
                }
                .cliente-info {
                    position: absolute;
                    top: ' . $POS_CLIENTE_TOP . 'mm;
                    left: 15mm;
                    right: 15mm;
                    height: ' . $POS_CLIENTE_ALTO . 'mm;
                    background-color: #f0f0f0;
                    padding: 3mm 5mm;
                    box-sizing: border-box;
                    font-size: 11pt;
                    line-height: 1.3;
                    border: 1px solid #999;
                    overflow: hidden;
                }
                .tabla-productos {
                    position: absolute;
                    top: ' . $POS_TABLA_TOP . 'mm;
                    left: 15mm;
                    right: 15mm;
                    font-size: ' . $TABLA_FONT_SIZE . ';
                }
                .tabla-productos table {
                    width: 100%;
                    border-collapse: collapse;
                }
                .tabla-productos th {
        background-color: #e8e8e8;
        height: <?= $TABLA_HEADER_ALTO ?>;
        text-align: left;
        padding: <?= $TABLA_PADDING ?>;
        border: 1px solid #000;
        font-weight: bold;
        font-size: <?= $TABLA_FONT_SIZE ?>;
        line-height: 1.1;
    }
    .tabla-productos td {
        padding: <?= $TABLA_PADDING ?>;
        border: 1px solid #000;
        vertical-align: middle;
        font-size: <?= $TABLA_FONT_SIZE ?>;
        line-height: 1.1;
    }
                .footer-info {
                    position: absolute;
                    bottom: ' . $POS_FOOTER_BOTTOM . 'mm;
                    left: 15mm;
                    right: 15mm;
                    height: 15mm;
                    background-color: #f0f0f0;
                }
                .remito-datos {
                    position: absolute;
                    bottom: ' . $POS_DATOS_BOTTOM . 'mm;
                    right: 15mm;
                    font-size: 8pt;
                    color: #666;
                }
                .page-break {
                    page-break-after: always;
                }
            </style>
        </head>
        <body>';
        
        // ========== GENERAR PAGINAS ==========
        $paginaActual = 1;
        
        foreach ($paginasProductos as $productosPagina) {
            // Si no es la primera pagina, agregar salto de pagina
            if ($paginaActual > 1) {
                $html .= '<div class="page-break"></div>';
            }
            
            // 1. Banda de informacion del cliente
            $html .= '<div class="cliente-info">';
            $html .= '<b>' . htmlspecialchars($movimiento['razon_social'] ?: $movimiento['ubicacion_destino']) . '</b>';

            
            
            // Agregar estado del envio
            if (isset($movimiento['estado_actual'])) {
                $html .= ' - <b>Estado: ' . htmlspecialchars($movimiento['estado_actual']) . '</b>';
            }
            
            $html .= '<br>';
///cuit----------------------------------
            if (isset($movimiento['cuit'])) {
                $html .= '<b>Cuit: ' . htmlspecialchars($movimiento['cuit']) . '</b>';
                $html .= '<br>';
            }


            if ($movimiento['domicilio']) {
                $html .= '<span style="font-size:10pt">';
                $html .= htmlspecialchars($movimiento['domicilio']);
                if ($movimiento['localidad']) $html .= ' - ' . htmlspecialchars($movimiento['localidad']);
                if ($movimiento['codigo_postal']) $html .= ' (' . htmlspecialchars($movimiento['codigo_postal']) . ')';
                $html .= '</span>';
            }
            $html .= '</div>';

            // 2. Tabla de productos
            $html .= '<div class="tabla-productos">';
            $html .= '<table>';
            $html .= '<thead>';
            $html .= '<tr>';
            $html .= '<th style="width:40%; text-align:left;">Descripcion</th>';
            $html .= '<th style="width:20%;">Contenedor</th>';
            $html .= '<th style="width:13%; text-align:center;">Cantidad</th>';
            $html .= '<th style="width:13%; text-align:center;">P. Bruto</th>';
            $html .= '<th style="width:14%; text-align:center;">P. Neto</th>';
            $html .= '</tr>';
            $html .= '</thead>';
            $html .= '<tbody>';

            // Productos de esta pagina
            foreach ($productosPagina as $producto) {
                $cantidad = number_format($producto['cnt'], 0, ',', '.');
                
                // Si peso es cero, dejar celda vacia
                $pesoBruto = ($producto['peso_bruto'] > 0) 
                    ? number_format($producto['peso_bruto'], 3, ',', '.') 
                    : '';
                $pesoNeto = ($producto['peso_neto'] > 0) 
                    ? number_format($producto['peso_neto'], 3, ',', '.') 
                    : '';
                
                $contenedor = $producto['contenedor'] ?: '-';
                
                $html .= '<tr>';
                $html .= '<td style="text-align:left;">' . htmlspecialchars($producto['descripcion']) . '</td>';
                $html .= '<td>' . htmlspecialchars($contenedor) . '</td>';
                $html .= '<td style="text-align:center;">' . $cantidad . '</td>';
                $html .= '<td style="text-align:right;">' . $pesoBruto . '</td>';
                $html .= '<td style="text-align:right;">' . $pesoNeto . '</td>';
                $html .= '</tr>';
            }

            // Solo en la ultima pagina: linea de totales
            if ($paginaActual === $totalPaginas) {
                $html .= '<tr style="background-color:#e8e8e8; font-weight:bold; border-top: 2px solid #000;">';
                $html .= '<td colspan="2" style="text-align:right;">TOTALES:</td>';
                $html .= '<td style="text-align:center;">' . number_format($cantidadTotal, 0, ',', '.') . '</td>';
                $html .= '<td style="text-align:right;">' . number_format($pesoTotalBruto, 3, ',', '.') . '</td>';
                $html .= '<td style="text-align:right;">' . number_format($pesoTotalNeto, 3, ',', '.') . '</td>';
                $html .= '</tr>';
            }
            
            $html .= '</tbody></table></div>';

            // 3. Franja gris del pie de pagina
            //$html .= '<div class="footer-info"></div>';

            // 4. Datos del remito con numeracion de paginas
            $html .= '<div class="remito-datos">';
            $html .= 'Remito Nro: ' . str_pad($idMovimiento, 8, '0', STR_PAD_LEFT);
            $html .= ' - Fecha: ' . date('d/m/Y', strtotime($movimiento['fechaAlta']));
            
            // Agregar numero de pagina si hay multiples paginas
            if ($totalPaginas > 1) {
                $html .= ' - Hoja ' . $paginaActual . ' de ' . $totalPaginas;
            }
            
            $html .= '</div>';
            
            $paginaActual++;
        }

        $html .= '</body></html>';
        return $html;
    }

}
