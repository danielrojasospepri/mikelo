$(document).ready(function() {
    // Variables globales
    let productosEnEnvio = [];
    let envioActual = null;
    let html5QrcodeScanner = null;
    let timeoutBusqueda = null;
    let pedidoOrigenId = null;  // ID del pedido desde el que se originó este envío
    let pedidoItems = [];        // Ítems originales del pedido, para calcular faltantes

    // Establecer fechas por defecto
    establecerFechasPorDefecto();

    // Inicialización
    const promesaUbicaciones = cargarUbicaciones();
    cargarEstados();
    cargarFamilias();
    cargarEnvios();

    // === EVENTOS PRINCIPALES ===

    // Botón Nuevo Envío
    $('#btnNuevoEnvio').click(function() {
        mostrarPanelNuevoEnvio();
    });

    // Cerrar panel nuevo envío
    $('#btnCerrarNuevoEnvio').click(function() {
        cerrarPanelNuevoEnvio();
    });

    // Cambio en selector de destino
    $('#destinoEnvio').change(function() {
        if ($(this).val()) {
            $('#sectionBusquedaProductos').slideDown();
            $('#buscarProductoEnvio').focus();
        } else {
            $('#sectionBusquedaProductos').slideUp();
        }
    });

    // Búsqueda de productos con códigos de barras
    $('#buscarProductoEnvio').on('input', function() {
        const valor = $(this).val().trim();
        
        if (timeoutBusqueda) {
            clearTimeout(timeoutBusqueda);
        }

        if (valor.length === 0) {
            $('#productosEncontrados').hide();
            $('#estadoOperacionEnvio').hide();
            return;
        }

        // Mostrar estado de búsqueda
        mostrarEstadoOperacion('Buscando producto...', 'info');

        timeoutBusqueda = setTimeout(() => {
            buscarProductosDisponibles(valor);
        }, 500);
    });

    // Limpiar búsqueda
    $('#limpiarBusquedaEnvio').click(function() {
        limpiarBusquedaEnvio();
    });

    // Eventos de teclado
    $(document).keydown(function(e) {
        // Escape - limpiar búsqueda si está activa
        if (e.key === 'Escape') {
            if ($('#buscarProductoEnvio').is(':focus')) {
                limpiarBusquedaEnvio();
            }
        }
    });

    // Guardar envío
    $('#btnGuardarEnvio').click(function() {
        guardarEnvio();
    });

    // Cancelar envío
    $('#btnCancelarEnvio').click(function() {
        cancelarEnvio();
    });

    // Confirmar envío inmediato
    $('#btnConfirmarEnvioInmediato').click(function() {
        confirmarEnvio(envioActual.id);
        cerrarPanelNuevoEnvio();
    });

    // Crear otro envío
    $('#btnCrearOtroEnvio').click(function() {
        iniciarNuevoEnvio();
    });

    // Filtros y tabla
    $('#btnFiltrar').click(function() {
        cargarEnvios();
    });

    $('#btnExportarPDF, #btnExportarExcel').click(function() {
        const tipo = $(this).attr('id').includes('PDF') ? 'pdf' : 'excel';
        exportarEnvios(tipo);
    });

    // Botón imprimir detalle en modal
    $('#btnImprimirDetalle').click(function() {
        if (window.envioSeleccionadoId) {
            imprimirDetalle(window.envioSeleccionadoId);
        }
    });

    // Botón remito preimpreso en modal
    $('#btnRemitoPreimpresoModal').click(function() {
        if (window.envioSeleccionadoId) {
            exportarRemitoPreimpreso(window.envioSeleccionadoId);
        } else {
            Swal.fire('Error', 'No hay envío seleccionado', 'error');
        }
    });

    // Delegación de eventos para botones de la tabla (ya no necesarios - se usa onclick directo)
    /*
    $(document).on('click', '[data-action="ver-detalle"]', function() {
        const envioId = $(this).data('envio-id');
        verDetalleEnvio(envioId);
    });

    $(document).on('click', '[data-action="exportar-pdf"]', function() {
        const envioId = $(this).data('envio-id');
        exportarDetalle(envioId, 'pdf');
    });

    $(document).on('click', '[data-action="exportar-excel"]', function() {
        const envioId = $(this).data('envio-id');
        exportarDetalle(envioId, 'excel');
    });

    $(document).on('click', '[data-action="confirmar-envio"]', function() {
        const envioId = $(this).data('envio-id');
        confirmarEnvio(envioId);
    });
    */

    // === FUNCIONES PRINCIPALES ===

    function mostrarPanelNuevoEnvio() {
        $('#panelFiltros, #panelTablaEnvios').hide();
        $('#panelNuevoEnvio').slideDown();
        $('#destinoEnvio').focus();
        // Solo limpiar si no estamos editando
        if (!window._editandoEnvio) {
            limpiarFormularioEnvio();
            // Mostrar pedidos pendientes para referencia (solo si no viene de ?pedido= param)
            const urlParams = new URLSearchParams(window.location.search);
            if (!urlParams.get('pedido')) {
                cargarPedidosPendientesPanel();
            }
        }
    }

    function cerrarPanelNuevoEnvio() {
        $('#panelNuevoEnvio').slideUp();
        $('#panelFiltros, #panelTablaEnvios').slideDown();
        $('#panelPedidosPendientes').empty();
        cargarEnvios(); // Refrescar tabla
    }

    function iniciarNuevoEnvio() {
        $('#panelPostGuardado').hide();
        $('#sectionBusquedaProductos').show();
        limpiarFormularioEnvio();
        $('#destinoEnvio').focus();
        $('#panelPedidosPendientes').empty(); // limpiar panel de referencia al crear otro
    }

    function limpiarFormularioEnvio() {
        $('#destinoEnvio').val('');
        $('#buscarProductoEnvio').val('');
        $('#sectionBusquedaProductos').hide();
        $('#productosEncontrados').hide();
        $('#productosEnvio').hide();
        $('#panelPostGuardado').hide();
        $('#estadoOperacionEnvio').hide();
        $('#panelFaltantesPedido').remove();
        
        productosEnEnvio = [];
        envioActual = null;
        pedidoOrigenId = null;
        pedidoItems = [];
        actualizarTablaProductosEnvio();
    }

    function limpiarBusquedaEnvio() {
        $('#buscarProductoEnvio').val('').focus();
        $('#productosEncontrados').hide();
        $('#estadoOperacionEnvio').hide();
    }

    function mostrarEstadoOperacion(mensaje, tipo = 'info') {
        const alertClass = `alert-${tipo}`;
        $('#estadoOperacionEnvio')
            .removeClass('alert-info alert-success alert-warning alert-danger')
            .addClass(alertClass)
            .show();
        $('#estadoTextoEnvio').text(mensaje);
    }

    // === PEDIDOS PENDIENTES PANEL DE REFERENCIA ===

    async function cargarPedidosPendientesPanel() {
        const $panel = $('#panelPedidosPendientes');
        $panel.html(`
            <div class="alert alert-light border py-2 px-3" id="spinnerPedidosPendientes">
                <i class="fas fa-spinner fa-spin mr-2"></i>Cargando pedidos pendientes...
            </div>`);

        try {
            const resp = await MikeloAuth.fetch('/pedidos?estado=PENDIENTE');
            if (!resp) { $panel.empty(); return; }
            const data = await resp.json();

            const pedidos = (data.pedidos || data.data || []).filter(p =>
                p.estado === 'PENDIENTE' || p.ultimo_estado === 'PENDIENTE'
            );

            if (pedidos.length === 0) {
                $panel.empty();
                return;
            }

            let filas = pedidos.map(p => {
                const fecha = p.fecha_pedido || p.fechaAlta || p.fecha || '';
                const fechaFmt = fecha ? new Date(fecha).toLocaleDateString('es-AR', {day:'2-digit', month:'2-digit', year:'numeric'}) : '—';
                const sucursal = p.sucursal || p.nombre_sucursal || 'Sucursal #' + p.id_sucursal;
                const items = p.cantidad_items || (p.items ? p.items.length : '?');
                return `
                    <tr>
                        <td><span class="badge badge-secondary">#${p.id}</span></td>
                        <td>${sucursal}</td>
                        <td class="text-center">${fechaFmt}</td>
                        <td class="text-center">${items}</td>
                        <td class="text-center">
                            <button class="btn btn-xs btn-primary" onclick="usarPedidoPendiente(${p.id})" title="Crear envío para este pedido">
                                <i class="fas fa-truck mr-1"></i>Usar
                            </button>
                        </td>
                    </tr>`;
            }).join('');

            $panel.html(`
                <div class="card card-outline card-warning mb-3" id="cardPedidosPendientes">
                    <div class="card-header py-2">
                        <h3 class="card-title">
                            <i class="fas fa-clipboard-list mr-2"></i>
                            Pedidos pendientes de envío
                            <span class="badge badge-warning ml-2">${pedidos.length}</span>
                        </h3>
                        <div class="card-tools">
                            <button type="button" class="btn btn-tool" data-card-widget="collapse" title="Minimizar">
                                <i class="fas fa-minus"></i>
                            </button>
                        </div>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-sm table-hover mb-0">
                                <thead class="thead-light">
                                    <tr>
                                        <th style="width:60px">Pedido</th>
                                        <th>Sucursal</th>
                                        <th class="text-center" style="width:110px">Fecha</th>
                                        <th class="text-center" style="width:70px">Ítems</th>
                                        <th class="text-center" style="width:80px">Acción</th>
                                    </tr>
                                </thead>
                                <tbody>${filas}</tbody>
                            </table>
                        </div>
                    </div>
                    <div class="card-footer text-muted small py-1">
                        <i class="fas fa-info-circle mr-1"></i>Hacé clic en <strong>Usar</strong> para pre-cargar el destino y ver qué falta enviar.
                    </div>
                </div>`);
        } catch(e) {
            console.warn('No se pudieron cargar pedidos pendientes:', e);
            $panel.empty();
        }
    }

    window.usarPedidoPendiente = function(idPedido) {
        // Redirigir a la misma página con el parámetro del pedido
        window.location.href = 'envios_nuevo.html?pedido=' + idPedido;
    };

    // === BÚSQUEDA DE PRODUCTOS ===

    function buscarProductosDisponibles(termino) {
        const destinoId = $('#destinoEnvio').val();
        
        if (!destinoId) {
            mostrarEstadoOperacion('Debe seleccionar un destino primero', 'warning');
            return;
        }

        // Detectar si es código de barras
        const esCodigoBarras = /^\d{13}$/.test(termino);
        
        let url = `api/envios/productos-disponibles`;
        let params = new URLSearchParams();

        if (esCodigoBarras) {
            // Procesar código de barras
            const tipoProducto = termino.substring(0, 2);
            const codigoProducto = parseInt(termino.substring(2, 7)).toString();
            const valorCantidadPeso = parseInt(termino.substring(7, 12));
            
            console.log('Procesando código de barras:', {
                codigo_completo: termino,
                tipo_producto: tipoProducto,
                codigo_producto: codigoProducto,
                valor_cantidad_peso: valorCantidadPeso
            });
            
            if (tipoProducto === '20') {
                // Tipo 20: Unidades - NO filtrar por cantidad, el frontend maneja el incremento
                params.append('codigo', codigoProducto);
                // Nota: NO enviamos 'cantidad' para que backend devuelva todos los items disponibles
                console.log('Parámetros tipo 20:', { codigo: codigoProducto });
            } else if (tipoProducto === '21') {
                // Tipo 21: Peso (dividir por 1000)
                const peso = (valorCantidadPeso / 1000).toFixed(3);
                params.append('codigo', codigoProducto);
                params.append('peso', peso);
                console.log('Parámetros tipo 21:', { codigo: codigoProducto, peso: peso });
            } else {
                mostrarEstadoOperacion('Código de barras no válido', 'danger');
                return;
            }
        } else {
            // Búsqueda por término
            params.append('filtro', termino);
            console.log('Parámetros búsqueda manual:', { filtro: termino });
        }

        const urlCompleta = `${url}?${params.toString()}`;
        console.log('URL de llamada:', urlCompleta);

        fetch(urlCompleta)
            .then(response => {
                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }
                return response.json();
            })
            .then(data => {
                if (data.success) {
                    const productos = data.data || data.productos || []; // Compatibilidad con ambas estructuras
                    
                    if (productos.length === 0) {
                        // Mensaje específico según el tipo de búsqueda
                        if (esCodigoBarras) {
                            const tipoProducto = termino.substring(0, 2);
                            if (tipoProducto === '21') {
                                const peso = (parseInt(termino.substring(7, 12)) / 1000).toFixed(3);
                                mostrarEstadoOperacion(`No hay stock con ese peso (${peso} kg)`, 'danger');
                            } else {
                                mostrarEstadoOperacion('No se encontró el producto en stock', 'warning');
                            }
                        } else {
                            mostrarEstadoOperacion('No se encontraron productos disponibles', 'warning');
                        }
                        $('#productosEncontrados').hide();
                    } else if (esCodigoBarras) {
                        // Código de barras: agregar automáticamente el primero (puede ser 1 o múltiples)
                        agregarProductoAlEnvio(productos[0]);
                        limpiarBusquedaEnvio();
                        mostrarEstadoOperacion('Producto agregado correctamente', 'success');
                    } else {
                        // Búsqueda manual: mostrar tabla
                        mostrarProductosEncontrados(productos);
                        mostrarEstadoOperacion(`${productos.length} producto(s) encontrado(s)`, 'success');
                    }
                } else {
                    mostrarEstadoOperacion(data.error || 'Error en la búsqueda', 'danger');
                    $('#productosEncontrados').hide();
                }
            })
            .catch(error => {
                console.error('Error:', error);
                mostrarEstadoOperacion('Error de conexión', 'danger');
                $('#productosEncontrados').hide();
            });
    }

    function mostrarProductosEncontrados(productos) {
        const tbody = $('#tablaProductosEncontrados');
        tbody.empty();

        productos.forEach(producto => {
            // Calcular cantidad disponible
            const cantidadDisponible = producto.cnt_disponible !== undefined ? producto.cnt_disponible : producto.cnt;
            
            // No mostrar productos sin stock
            if (cantidadDisponible <= 0) {
                return;
            }
            
            // NUEVO: Verificar si el producto ya está agregado al envío
            const yaAgregado = productosEnEnvio.some(p => p.id_movimiento_item === producto.id_movimiento_item);
            
            // NUEVO: Si ya está agregado, NO mostrar en la lista de búsqueda
            if (yaAgregado) {
                return;
            }
            
            const row = `
                <tr>
                    <td>${producto.codigo}</td>
                    <td>${producto.descripcion}</td>
                    <td>
                        <strong>${cantidadDisponible}</strong>
                        ${producto.cnt !== cantidadDisponible ? `<br><small class="text-muted">(Original: ${producto.cnt})</small>` : ''}
                        <br><small>${producto.cnt_peso} kg</small>
                    </td>
                    <td>${producto.contenedor || '-'}</td>
                    <td>
                        <button class="btn btn-sm btn-primary" onclick="agregarProductoAlEnvio(${JSON.stringify(producto).replace(/"/g, '&quot;')})">
                            <i class="fas fa-plus"></i> Agregar
                        </button>
                    </td>
                </tr>
            `;
            tbody.append(row);
        });

        $('#productosEncontrados').slideDown();
    }

    // Función global para agregar producto (llamada desde botones)
    window.agregarProductoAlEnvio = async function(producto) {
        if (!producto || !producto.id_movimiento_item) {
            mostrarEstadoOperacion('Producto inválido', 'error');
            return;
        }

        // Calcular cantidad disponible en stock (convertir a número)
        const cantidadDisponible = parseFloat(producto.cnt_disponible !== undefined ? producto.cnt_disponible : producto.cnt);
        
        if (cantidadDisponible <= 0) {
            mostrarEstadoOperacion('No hay stock disponible de este producto', 'warning');
            return;
        }

        // Buscar si ya existe este item en el envío
        const itemExistente = productosEnEnvio.find(p => p.id_movimiento_item === producto.id_movimiento_item);
        
        if (itemExistente) {
            // PASO 1: Intentar incrementar cantidad del item existente
            const cantidadActual = parseFloat(itemExistente.cantidad || 1);
            
            if (cantidadActual < cantidadDisponible) {
                // ✅ Puede incrementar
                itemExistente.cantidad = cantidadActual + 1;
                itemExistente.peso = (itemExistente.peso_unitario * itemExistente.cantidad).toFixed(3);
                
                actualizarTablaProductosEnvio();
                mostrarEstadoOperacion(`Cantidad incrementada a ${itemExistente.cantidad}`, 'success');
                return;
            } else {
                // ❌ Item agotado, buscar siguiente disponible
                // Obtener IDs ya en uso del mismo producto
                const idsEnUso = productosEnEnvio
                    .filter(p => p.codigo === producto.codigo)
                    .map(p => p.id_movimiento_item);
                
                // Buscar siguiente item disponible
                // const siguienteItem = await buscarSiguienteItemDisponible(producto.codigo, idsEnUso);
                const siguienteItem = await buscarSiguienteItemDisponible(producto.codigo, idsEnUso, producto.cnt_peso);
                if (!siguienteItem) {
                    //mostrarEstadoOperacion('No hay más stock disponible de este producto', 'warning');
                    mostrarEstadoOperacion('Producto ya incluido en el envío', 'warning');
                    return;
                }
                
                // Usar el siguiente item disponible
                producto = siguienteItem;
            }
        }

        // PASO 2: Agregar nuevo item (primera vez o siguiente disponible)
        const cantidadInicial = Math.min(
            producto._cantidadSugerida || 1,
            parseFloat(producto.cnt_disponible !== undefined ? producto.cnt_disponible : producto.cnt)
        );
        const pesoUnitario = producto.cnt_peso / producto.cnt; // Peso por unidad
        const pesoInicial = (pesoUnitario * cantidadInicial).toFixed(3);

        const productoEnEnvio = {
            ...producto,
            cantidad: cantidadInicial,
            peso: parseFloat(pesoInicial),
            cnt_disponible: producto.cnt_disponible !== undefined ? producto.cnt_disponible : producto.cnt,
            peso_unitario: pesoUnitario
        };

        productosEnEnvio.push(productoEnEnvio);
        actualizarTablaProductosEnvio();
        
        $('#productosEncontrados').hide();
        limpiarBusquedaEnvio();
        
        // Mostrar sección de productos si es el primero
        if (productosEnEnvio.length === 1) {
            $('#productosEnvio').slideDown();
        }
    };

    function actualizarTablaProductosEnvio() {
        const tbody = $('#tablaProductosEnvio');
        tbody.empty();

        if (productosEnEnvio.length === 0) {
            $('#productosEnvio').hide();
            actualizarPanelFaltantes();
            return;
        }

        productosEnEnvio.forEach((producto, index) => {
            const cantidadActual = producto.cantidad || producto.cnt;
            const pesoActual = producto.peso !== undefined ? producto.peso : producto.cnt_peso;
            const cantidadDisponible = producto.cnt_disponible !== undefined ? producto.cnt_disponible : producto.cnt;
            
            const row = `
                <tr>
                    <td>${producto.codigo}</td>
                    <td>
                        ${producto.descripcion}
                        <br><small class="text-muted">Disponible: ${cantidadDisponible}</small>
                    </td>
                    <td>
                        <input type="number" 
                               class="form-control form-control-sm cantidad-producto" 
                               value="${cantidadActual}" 
                               min="1" 
                               max="${cantidadDisponible}"
                               data-index="${index}"
                               style="width: 80px;">
                    </td>
                    <td>
                        <input type="number" 
                               class="form-control form-control-sm peso-producto" 
                               value="${pesoActual}" 
                               step="0.001"
                               readonly
                               style="width: 100px;"> kg
                    </td>
                    <td>${producto.contenedor || '-'}</td>
                    <td>
                        <button class="btn btn-sm btn-danger" onclick="quitarProductoDelEnvio(${index})">
                            <i class="fas fa-trash"></i>
                        </button>
                    </td>
                </tr>
            `;
            tbody.append(row);
        });

        // Agregar event listener para cambios de cantidad
        $('.cantidad-producto').on('change', function() {
            const index = $(this).data('index');
            const nuevaCantidad = parseInt($(this).val());
            actualizarCantidadProducto(index, nuevaCantidad);
        });

        $('#productosEnvio').show();

        // Reflejar cambios en el panel de faltantes (si existe)
        actualizarPanelFaltantes();
    }

    function actualizarPanelFaltantes() {
        const $panel = $('#panelFaltantesPedido');
        if (!$panel.length || pedidoItems.length === 0) return;

        $panel.find('tr[data-id-producto]').each(function() {
            const $tr         = $(this);
            const idProducto  = parseInt($tr.data('id-producto'));
            const cantPedida  = parseFloat($tr.data('cant-pedida')  || 0);
            const cantEnviada = parseFloat($tr.data('cant-enviada') || 0);
            const stockDisp   = parseFloat($tr.data('stock-disp')   || 0);
            const codigo      = $tr.data('codigo');

            // Sumar lo que ya está agregado en el envío para este producto
            // Usamos == (loose) para evitar mismatch string/número desde la API
            const enEnvio = productosEnEnvio
                .filter(p => parseInt(p.id_productos || p.id_producto) == idProducto)
                .reduce((s, p) => s + parseFloat(p.cantidad || 1), 0);

            const pendienteOriginal = Math.max(0, cantPedida - cantEnviada);
            const pendienteActual   = Math.max(0, pendienteOriginal - enEnvio);
            const completo          = pendienteActual === 0;

            // Actualizar celda Pendiente
            $tr.find('.td-pendiente').html(
                completo
                    ? '<span class="badge badge-success"><i class="fas fa-check mr-1"></i>Completo</span>'
                    : `<strong>${pendienteActual}</strong>`
            );

            // Colorear fila
            $tr.removeClass('table-warning table-success');
            if (completo) {
                $tr.addClass('table-success');
            } else if (stockDisp <= 0) {
                $tr.addClass('table-warning');
            }

            // Actualizar botón
            if (completo) {
                $tr.find('.td-accion').html(
                    '<button class="btn btn-sm btn-success" disabled>' +
                    '<i class="fas fa-check"></i> Listo</button>'
                );
            } else if (stockDisp > 0) {
                // Restaurar botón si fue deshabilitado y ahora hay pendiente (ej. se quitó un producto)
                const $btn = $tr.find('.td-accion .btn-agregar-faltante');
                if (!$btn.length) {
                    $tr.find('.td-accion').html(
                        `<button class="btn btn-sm btn-primary btn-agregar-faltante"` +
                        ` data-codigo="${codigo}" data-cantidad="${pendienteActual}"` +
                        ` title="Buscar y agregar al envío">` +
                        `<i class="fas fa-plus"></i> Agregar</button>`
                    );
                } else {
                    $btn.data('cantidad', pendienteActual);
                }
            }
        });
    }

    // Función para actualizar cantidad y recalcular peso
    function actualizarCantidadProducto(index, nuevaCantidad) {
        const producto = productosEnEnvio[index];
        const cantidadDisponible = producto.cnt_disponible !== undefined ? producto.cnt_disponible : producto.cnt;
        
        // Validar mínimo y máximo
        if (nuevaCantidad < 1) {
            nuevaCantidad = 1;
            mostrarEstadoOperacion('La cantidad mínima es 1', 'warning');
        }
        
        if (nuevaCantidad > cantidadDisponible) {
            nuevaCantidad = cantidadDisponible;
            mostrarEstadoOperacion(`La cantidad máxima disponible es ${cantidadDisponible}`, 'warning');
        }
        
        // Calcular peso proporcional
        const pesoUnitario = producto.peso_unitario || (producto.cnt_peso / producto.cnt);
        const nuevoPeso = (pesoUnitario * nuevaCantidad).toFixed(3);
        
        // Actualizar el producto en el array
        productosEnEnvio[index].cantidad = nuevaCantidad;
        productosEnEnvio[index].peso = parseFloat(nuevoPeso);
        
        // Actualizar la tabla
        actualizarTablaProductosEnvio();
    }

    // Función global para quitar producto
    window.quitarProductoDelEnvio = function(index) {
        productosEnEnvio.splice(index, 1);
        actualizarTablaProductosEnvio();
        
        if (productosEnEnvio.length === 0) {
            $('#buscarProductoEnvio').focus();
        }
    };

    // === GESTIÓN DE ENVÍOS ===

    function guardarEnvio() {
        const destinoId = $('#destinoEnvio').val();
        
        if (!destinoId) {
            Swal.fire('Error', 'Debe seleccionar un destino', 'error');
            return;
        }

        if (productosEnEnvio.length === 0) {
            Swal.fire('Error', 'Debe agregar al menos un producto', 'error');
            return;
        }

        const datosEnvio = {
            destino: destinoId,
            productos: productosEnEnvio.map(p => {
                const producto = {
                    id_productos: p.id_productos || p.id_producto,
                    cantidad: p.cantidad || p.cnt,
                    peso: p.peso !== undefined ? p.peso : p.cnt_peso
                };
                // Solo para productos ya existentes en el envío (edición)
                if (p.id_movimiento_item) {
                    producto.id_movimientos_items_origen = p.id_movimiento_item;
                }
                return producto;
            })
        };
        // Si viene de un pedido, vincularlo automáticamente
        if (pedidoOrigenId) {
            datosEnvio.id_pedido = pedidoOrigenId;
        }

        console.log('Datos del envío a guardar:', datosEnvio);

        // Si estamos editando un envío existente, usar PUT y el endpoint de edición
        let url = 'api/envios';
        let method = 'POST';
        let exitoMsg = 'Envío creado correctamente';
        if (envioActual && envioActual.id) {
            url = `api/envios/${envioActual.id}`;
            method = 'PUT';
            exitoMsg = 'Envío editado correctamente';
        }

        MikeloAuth.fetch(url.replace(/^api\//, '/'), {
            method: method,
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify(datosEnvio)
        })
        .then(response => {
            console.log('Response status:', response.status);
            if (!response.ok) {
                return response.json().then(err => {
                    throw new Error(err.error || `HTTP error! status: ${response.status}`);
                });
            }
            return response.json();
        })
        .then(data => {
            console.log('Respuesta del servidor:', data);
            if (data.success) {
                envioActual = { id: data.id || (envioActual && envioActual.id) };
                Swal.fire({
                    title: 'Éxito',
                    text: exitoMsg,
                    icon: 'success',
                    showCancelButton: true,
                    confirmButtonText: 'Confirmar Envío Ahora',
                    cancelButtonText: 'Volver a Lista',
                    reverseButtons: true
                }).then((result) => {
                    if (result.isConfirmed) {
                        // Confirmar envío inmediatamente
                        confirmarEnvio(envioActual.id);
                    }
                    // En cualquier caso, cerrar panel y volver a la grilla
                    cerrarPanelNuevoEnvio();
                });
            } else {
                Swal.fire('Error', data.error || 'Error al guardar el envío', 'error');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            Swal.fire('Error', error.message || 'Error de conexión', 'error');
        });
    }

    function cancelarEnvio() {
        Swal.fire({
            title: '¿Está seguro?',
            text: 'Se perderán todos los datos del envío',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: 'Sí, cancelar',
            cancelButtonText: 'No, continuar'
        }).then((result) => {
            if (result.isConfirmed) {
                cerrarPanelNuevoEnvio();
            }
        });
    }

    function confirmarEnvio(envioId) {
        fetch(`api/envios/${envioId}/confirmar`, {
            method: 'PUT'
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                Swal.fire('Éxito', 'Envío confirmado correctamente', 'success');
                cargarEnvios(); // Recargar la tabla
            } else {
                Swal.fire('Error', data.error || 'Error al confirmar el envío', 'error');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            Swal.fire('Error', 'Error de conexión', 'error');
        });
    }

    // === CARGA DE DATOS ===

    function establecerFechasPorDefecto() {
        // Fecha de hoy
        const hoy = new Date();
        const fechaHoy = hoy.toISOString().split('T')[0];
        
        // Fecha de ayer
        const ayer = new Date();
        ayer.setDate(ayer.getDate() - 1);
        const fechaAyer = ayer.toISOString().split('T')[0];
        
        // Establecer valores
        $('#fechaDesde').val(fechaAyer);
        $('#fechaHasta').val(fechaHoy);
    }

    function cargarUbicaciones() {
        return fetch('api/ubicaciones')
            .then(response => {
                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }
                return response.json();
            })
            .then(data => {
                console.log('Ubicaciones received:', data); // Debug
                if (data.success) {
                    const selectDestino = $('#destinoEnvio');
                    const filtroDestino = $('#filtroDestino');
                    
                    selectDestino.empty().append('<option value="">Seleccionar destino...</option>');
                    filtroDestino.empty().append('<option value="">Todas</option>');
                    
                    data.ubicaciones.forEach(ubicacion => {
                        if (parseInt(ubicacion.id) !== 1) { // Excluir depósito central
                            const option = `<option value="${ubicacion.id}">${ubicacion.nombre}</option>`;
                            selectDestino.append(option);
                            filtroDestino.append(option);
                        }
                    });
                } else {
                    console.error('Error en respuesta ubicaciones:', data);
                    Swal.fire('Error', data.error || 'Error al cargar ubicaciones', 'error');
                }
            })
            .catch(error => {
                console.error('Error cargando ubicaciones:', error);
                Swal.fire('Error', 'Error de conexión al cargar ubicaciones', 'error');
            });
    }

    function cargarEstados() {
        fetch('api/estados')
            .then(response => {
                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }
                return response.json();
            })
            .then(data => {
                console.log('Estados received:', data); // Debug
                if (data.success) {
                    const selectEstado = $('#estado');
                    selectEstado.empty().append('<option value="">Todos</option>');
                    
                    data.estados.forEach(estado => {
                        selectEstado.append(`<option value="${estado.id}">${estado.descripcion}</option>`);
                    });
                } else {
                    console.error('Error en respuesta estados:', data);
                    Swal.fire('Error', data.error || 'Error al cargar estados', 'error');
                }
            })
            .catch(error => {
                console.error('Error cargando estados:', error);
                Swal.fire('Error', 'Error de conexión al cargar estados', 'error');
            });
    }

    function cargarFamilias() {
        fetch('api/tipos-producto')
            .then(response => {
                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }
                return response.json();
            })
            .then(data => {
                if (data.success) {
                    const selectFamilia = $('#filtroFamilia');
                    selectFamilia.empty().append('<option value="">Todas</option>');
                    
                    data.data.forEach(tipo => {
                        selectFamilia.append(`<option value="${tipo.id}">${tipo.nombre}</option>`);
                    });
                }
            })
            .catch(error => {
                console.error('Error cargando familias:', error);
            });
    }

    function cargarEnvios() {
        const filtros = {
            familia: $('#filtroFamilia').val(),
            fecha_desde: $('#fechaDesde').val(),
            fecha_hasta: $('#fechaHasta').val(),
            ubicacion_destino: $('#filtroDestino').val(),
            estado: $('#estado').val()
        };

        let params = new URLSearchParams();
        // Mapear nombres de parámetros para que coincidan con el backend (camelCase)
        if (filtros.familia) params.append('familia', filtros.familia);
        if (filtros.fecha_desde) params.append('fechaDesde', filtros.fecha_desde);
        if (filtros.fecha_hasta) params.append('fechaHasta', filtros.fecha_hasta);
        if (filtros.ubicacion_destino) params.append('destino', filtros.ubicacion_destino);
        if (filtros.estado) params.append('estado', filtros.estado);

        fetch(`api/envios?${params.toString()}`)
            .then(response => {
                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }
                return response.json();
            })
            .then(data => {
                console.log('Envios received:', data); // Debug
                if (data.success) {
                    const envios = data.data || data.envios || [];
                    mostrarEnviosEnTabla(envios);
                } else {
                    console.error('Error en respuesta envíos:', data);
                    // Mostrar error si no es la carga inicial
                    if ($('#fechaDesde').val() || $('#fechaHasta').val() || $('#filtroDestino').val() || $('#estado').val()) {
                        Swal.fire('Error', data.error || 'No se pudieron cargar los envíos', 'error');
                    }
                }
            })
            .catch(error => {
                console.error('Error cargando envíos:', error);
                // No mostrar error al usuario para la carga inicial de envíos
            });
    }

    function mostrarEnviosEnTabla(envios) {
        const tbody = $('#enviosTable');
        tbody.empty();

        envios.forEach(envio => {
            const row = `
                <tr>
                    <td>${formatearFecha(envio.fechaAlta)}</td>
                    <td>${envio.destino}</td>
                    <td><span class="badge badge-${getBadgeClass(envio.ultimo_estado)}">${envio.ultimo_estado}</span></td>
                    <td>${envio.cantidad_items}</td>
                    <td>${envio.peso_total} kg</td>
                    <td>
                        <button class="btn btn-sm btn-info" 
                                onclick="verDetalleEnvio(${envio.id})" 
                                data-action="ver-detalle" 
                                data-envio-id="${envio.id}" 
                                title="Ver detalle">
                            <i class="fas fa-eye"></i>
                        </button>
                        <button class="btn btn-sm btn-primary" 
                                onclick="exportarDetalle(${envio.id}, 'pdf')" 
                                title="Remito PDF">
                            <i class="fas fa-file-pdf"></i>
                        </button>
                        <button class="btn btn-sm btn-success" 
                                onclick="exportarDetalle(${envio.id}, 'excel')" 
                                title="Detalle Excel">
                            <i class="fas fa-file-excel"></i>
                        </button>
                        <button class="btn btn-sm btn-secondary" 
                                onclick="exportarRemitoPreimpreso(${envio.id})" 
                                title="Remito Preimpreso STARK IND">
                            <i class="fas fa-print"></i>
                        </button>
                        ${envio.ultimo_estado === 'NUEVO' ? `
                            <button class="btn btn-sm btn-warning" 
                                    onclick="confirmarEnvio(${envio.id})" 
                                    title="Confirmar envío">
                                <i class="fas fa-paper-plane"></i>
                            </button>
                        ` : ''}
                    </td>
                </tr>
            `;
            tbody.append(row);
        });
    }

    // === FUNCIONES AUXILIARES ===

    function formatearFecha(fecha) {
        return new Date(fecha).toLocaleDateString('es-AR', {
            day: '2-digit',
            month: '2-digit',
            year: 'numeric',
            hour: '2-digit',
            minute: '2-digit'
        });
    }

    function getBadgeClass(estado) {
        const clases = {
            'NUEVO': 'primary',
            'ENVIADO': 'warning',
            'RECIBIDO': 'success',
            'CANCELADO': 'danger'
        };
        return clases[estado] || 'secondary';
    }

    function exportarEnvios(tipo) {
        const filtros = {
            fecha_desde: $('#fechaDesde').val(),
            fecha_hasta: $('#fechaHasta').val(),
            ubicacion_destino: $('#filtroDestino').val(),
            estado: $('#estado').val()
        };

        let params = new URLSearchParams();
        Object.keys(filtros).forEach(key => {
            if (filtros[key]) {
                params.append(key, filtros[key]);
            }
        });

    // Usar el endpoint correcto según el tipo
    const endpoint = tipo === 'pdf' ? 'pdf' : 'excel';
    // Mapear nombres de parámetros para backend
    const filtrosBackend = new URLSearchParams();
    if ($('#fechaDesde').val()) filtrosBackend.append('fechaDesde', $('#fechaDesde').val());
    if ($('#fechaHasta').val()) filtrosBackend.append('fechaHasta', $('#fechaHasta').val());
    if ($('#filtroDestino').val()) filtrosBackend.append('destino', $('#filtroDestino').val());
    if ($('#estado').val()) filtrosBackend.append('estado', $('#estado').val());
    const url = `api/envios/${endpoint}?${filtrosBackend.toString()}`;
    // Abrir directamente en nueva ventana para descarga
    window.open(url, '_blank');
    }

    // Funciones globales para botones de tabla
    window.verDetalleEnvio = function(envioId) {
        // Guardar el ID del envío seleccionado para funciones del modal
        window.envioSeleccionadoId = envioId;
        
        // Cargar detalle del envío
        fetch(`api/envios/${envioId}`)
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    mostrarDetalleEnModal(data.data);
                    $('#modalDetalleEnvio').modal('show');
                } else {
                    Swal.fire('Error', 'No se pudo cargar el detalle del envío', 'error');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                Swal.fire('Error', 'Error de conexión al cargar el detalle', 'error');
            });
    };

    function mostrarDetalleEnModal(detalle) {
        // Calcular peso total
        const pesoTotal = detalle.productos.reduce((total, producto) => {
            return total + parseFloat(producto.cnt_peso || 0);
        }, 0).toFixed(3);
        
        const contenido = `
            <div class="row">
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="card-title">Información del Envío</h5>
                        </div>
                        <div class="card-body">
                            <table class="table table-sm">
                                <tr><td><strong>ID:</strong></td><td>${detalle.envio.id}</td></tr>
                                <tr><td><strong>Fecha:</strong></td><td>${formatearFecha(detalle.envio.fechaAlta)}</td></tr>
                                <tr><td><strong>Origen:</strong></td><td>${detalle.envio.origen}</td></tr>
                                <tr><td><strong>Destino:</strong></td><td>${detalle.envio.destino}</td></tr>
                                <tr><td><strong>Estado:</strong></td><td><span class="badge badge-${getBadgeClass(detalle.envio.ultimo_estado)}">${detalle.envio.ultimo_estado}</span></td></tr>
                                <tr><td><strong>Peso Total:</strong></td><td>${pesoTotal} kg</td></tr>
                            </table>
                        </div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="card-title">Productos (${detalle.productos.length})</h5>
                        </div>
                        <div class="card-body" style="max-height: 300px; overflow-y: auto;">
                            <table class="table table-sm">
                                <thead>
                                    <tr>
                                        <th>Código</th>
                                        <th>Producto</th>
                                        <th>Cantidad</th>
                                        <th>Peso</th>
                                        <th>Contenedor</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    ${detalle.productos.map(producto => `
                                        <tr>
                                            <td>${producto.codigo}</td>
                                            <td>${producto.descripcion}</td>
                                            <td>${producto.cnt}</td>
                                            <td>${producto.cnt_peso} kg</td>
                                            <td>${producto.contenedor || '-'}</td>
                                        </tr>
                                    `).join('')}
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        `;
        
        $('#detalleEnvioContenido').html(contenido);
        
        // Obtener el estado del envío
        const estado = detalle.envio.ultimo_estado;
        
        // Mostrar/ocultar botones según el estado
        
        // Botón Confirmar Envío: solo visible si estado = 'NUEVO'
        if (estado === 'NUEVO') {
            $('#btnConfirmarEnvioModal').show().off('click').on('click', function() {
                confirmarEnvioDesdeModal(detalle.envio.id);
            });
        } else {
            $('#btnConfirmarEnvioModal').hide();
        }
        
        // Botón Editar Envío: solo visible si estado = 'NUEVO'
        if (estado === 'NUEVO') {
            $('#btnEditarEnvioModal').show().off('click').on('click', function() {
                $('#modalDetalleEnvio').modal('hide');
                cargarEnvioParaEdicion(detalle.envio.id);
            });
        } else {
            $('#btnEditarEnvioModal').hide();
        }
        
        // Botón Cancelar Envío: visible si estado != 'CANCELADO' y != 'RECIBIDO'
        if (estado !== 'CANCELADO' && estado !== 'RECIBIDO') {
            $('#btnCancelarEnvioModal').show().off('click').on('click', function() {
                $('#modalDetalleEnvio').modal('hide');
                confirmarCancelacionEnvio(detalle.envio.id);
            });
        } else {
            $('#btnCancelarEnvioModal').hide();
        }
    }

    function confirmarEnvioDesdeModal(envioId) {
        Swal.fire({
            title: '¿Confirmar envío?',
            text: 'Esta acción cambiará el estado del envío a ENVIADO',
            icon: 'question',
            showCancelButton: true,
            confirmButtonText: 'Sí, confirmar',
            cancelButtonText: 'Cancelar'
        }).then(result => {
            if (result.isConfirmed) {
                confirmarEnvio(envioId);
                $('#modalDetalleEnvio').modal('hide');
            }
        });
    }

    // === EXPORTAR DETALLE DE ENVÍO ===
    
    function exportarDetalle(id, formato) {
        console.log(`Exportando ${formato} para envío ${id}`); // Debug
        
        const url = `api/envios/${id}/${formato}`;
        console.log(`URL de descarga: ${url}`); // Debug
        
        // Abrir directamente en nueva ventana - el navegador gestiona la descarga
        window.open(url, '_blank');
    }

    // === IMPRIMIR DETALLE DESDE MODAL ===
    
    function imprimirDetalle(envioId) {
        Swal.fire({
            title: 'Generando PDF para imprimir...',
            text: 'Por favor espere mientras se genera el documento',
            allowOutsideClick: false,
            didOpen: () => {
                Swal.showLoading();
            }
        });

        // Abrir PDF en nueva ventana para imprimir
        const pdfWindow = window.open(`api/envios/${envioId}/pdf`, '_blank');
        
        Swal.close();
        
        // Mostrar mensaje de éxito
        Swal.fire({
            title: 'PDF abierto',
            text: 'El remito se ha abierto en una nueva ventana. Puede imprimirlo desde allí.',
            icon: 'success',
            timer: 3000,
            showConfirmButton: false
        });
    }

    // === REMITO PREIMPRESO (STARK IND) ===
    
    function exportarRemitoPreimpreso(envioId) {
        Swal.fire({
            title: 'Generando remito preimpreso...',
            text: 'Por favor espere',
            allowOutsideClick: false,
            didOpen: () => {
                Swal.showLoading();
            }
        });

        const url = `api/envios/${envioId}/pdf-preimpreso`;
        
        // Abrir en nueva pestaña directamente
        const pdfWindow = window.open(url, '_blank');
        
        Swal.close();

        if (pdfWindow) {
            pdfWindow.focus();
            Swal.fire({
                title: '¡Remito generado!',
                text: 'El remito se ha abierto en una nueva pestaña. Imprímalo sobre papel preimpreso STARK IND.',
                icon: 'success',
                timer: 4000,
                showConfirmButton: false
            });
        } else {
            Swal.fire({
                title: 'Error',
                text: 'No se pudo abrir la ventana para el remito. Por favor, deshabilite el bloqueador de pop-ups.',
                icon: 'error'
            });
        }
    }

    /**
     * Carga un envío existente en el formulario para editarlo
     */
    function cargarEnvioParaEdicion(id) {
        // Cargar detalle del envío
        fetch(`api/envios/${id}`)
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    const detalle = data.data;
                    const envio = detalle.envio;
                    
                    // Verificar que esté en estado NUEVO
                    if (envio.ultimo_estado !== 'NUEVO') {
                        Swal.fire('Error', 'Solo se pueden editar envíos en estado NUEVO', 'error');
                        return;
                    }
                    

                    // Indicar que estamos editando para evitar limpiar el destino
                    window._editandoEnvio = true;
                    mostrarPanelNuevoEnvio();
                    // Al terminar la edición, quitar el flag
                    setTimeout(() => { window._editandoEnvio = false; }, 1000);
                    

                    // Esperar a que el select de destino esté poblado antes de seleccionar
                    const setDestino = () => {
                        if ($('#destinoEnvio option').length > 1) {
                            // Usar el campo correcto del backend
                            $('#destinoEnvio').val(envio.id_ubicacion_destino);
                            $('#destinoEnvio').trigger('change');
                        } else {
                            setTimeout(setDestino, 100);
                        }
                    };
                    setDestino();

                    // Cargar productos del envío en el array global
                    productosEnEnvio = [];
                    if (detalle.productos && detalle.productos.length > 0) {
                        detalle.productos.forEach(function(producto) {
                            productosEnEnvio.push({
                                id_movimiento_item: producto.id_movimiento_item,
                                id_producto: producto.id_producto,
                                id_productos: producto.id_productos || producto.id_producto,
                                id_movimientos: producto.id_movimientos || envio.id,
                                codigo: producto.codigo,
                                descripcion: producto.descripcion,
                                cantidad: parseInt(producto.cnt),
                                peso: parseFloat(producto.cnt_peso),
                                cnt_peso: parseFloat(producto.cnt_peso),
                                cnt: parseInt(producto.cnt),
                                contenedor: producto.contenedor || '-',
                                id_contenedor: producto.id_contenedor || null,
                                cnt_disponible: parseInt(producto.cnt), // Para el formulario
                                peso_unitario: parseFloat(producto.cnt_peso) / parseInt(producto.cnt)
                            });
                        });
                        // Mostrar la tabla de productos directamente
                        actualizarTablaProductosEnvio();
                        $('#productosEnvio').show();
                    } else {
                        // Si no hay productos, ocultar la tabla
                        actualizarTablaProductosEnvio();
                        $('#productosEnvio').hide();
                    }
                    
                    // Scroll al formulario
                    setTimeout(() => {
                        $('html, body').animate({
                            scrollTop: $('#panelNuevoEnvio').offset().top - 100
                        }, 500);
                    }, 300);
                    
                    Swal.fire('Éxito', 'Envío cargado para edición. Puede agregar o quitar productos.', 'success');
                } else {
                    Swal.fire('Error', data.error || 'Error al cargar el envío', 'error');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                Swal.fire('Error', 'Error al cargar el envío para edición', 'error');
            });
    }

    /**
     * Confirma y ejecuta la cancelación de un envío
     */
    function confirmarCancelacionEnvio(id) {
        Swal.fire({
            title: '¿Cancelar Envío?',
            html: 'Esta acción:<br>' +
                  '- Cambiará el estado a <strong>CANCELADO</strong><br>' +
                  '- Devolverá todos los productos al stock<br>' +
                  '<br>¿Desea continuar?',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            cancelButtonColor: '#3085d6',
            confirmButtonText: 'Sí, cancelar envío',
            cancelButtonText: 'No, volver'
        }).then((result) => {
            if (result.isConfirmed) {
                cancelarEnvio(id);
            }
        });
    }

    /**
     * Ejecuta la cancelación del envío via API
     */
    function cancelarEnvio(id) {
        // Preguntar motivo
        Swal.fire({
            title: 'Motivo de cancelación',
            input: 'textarea',
            inputLabel: 'Ingrese el motivo de la cancelación',
            inputPlaceholder: 'Ej: Cliente canceló pedido, error en productos, etc.',
            inputAttributes: {
                'aria-label': 'Motivo de cancelación'
            },
            showCancelButton: true,
            confirmButtonText: 'Confirmar cancelación',
            cancelButtonText: 'Volver',
            inputValidator: (value) => {
                if (!value || value.trim() === '') {
                    return 'Debe ingresar un motivo'
                }
            }
        }).then((result) => {
            if (result.isConfirmed) {
                // Enviar cancelación con motivo
                $.ajax({
                    url: 'api/envios/' + id + '/cancelar',
                    method: 'POST',
                    contentType: 'application/json',
                    data: JSON.stringify({
                        motivo: result.value
                    }),
                    success: function(response) {
                        if (response.success) {
                            Swal.fire('Éxito', 'Envío cancelado correctamente. Los productos han vuelto al stock.', 'success');
                            cargarEnvios(); // Recargar grilla
                        } else {
                            Swal.fire('Error', response.message || 'Error al cancelar el envío', 'error');
                        }
                    },
                    error: function(xhr) {
                        const errorMsg = xhr.responseJSON && xhr.responseJSON.message 
                            ? xhr.responseJSON.message 
                            : 'Error al cancelar el envío';
                        Swal.fire('Error', errorMsg, 'error');
                    }
                });
            }
        });
    }

    // NUEVA FUNCIÓN: Buscar siguiente item disponible excluyendo IDs en uso
    async function buscarSiguienteItemDisponible(codigo, idsExcluir, peso = null) {
        try {
            const params = new URLSearchParams({
                codigo: codigo
            });
            if (peso !== null && peso !== undefined) {
                params.append('peso', parseFloat(peso).toFixed(3));
            }
            
            const response = await fetch(`api/envios/productos-disponibles?${params}`);
            const data = await response.json();
            
            if (data.success && data.data.length > 0) {
                // Filtrar items ya en uso en el frontend
                const itemsDisponibles = data.data.filter(item => 
                    !idsExcluir.includes(item.id_movimiento_item)
                );
                
                if (itemsDisponibles.length > 0) {
                    return itemsDisponibles[0]; // Retorna el siguiente disponible
                }
            }
            
            return null;
        } catch (error) {
            console.error('Error buscando siguiente item:', error);
            return null;
        }
    }

    // === PRECARGA DESDE PEDIDO (por query param ?pedido=ID) ===
    (function() {
        const urlParams = new URLSearchParams(window.location.search);
        const pedidoParam = urlParams.get('pedido');
        if (!pedidoParam) return;

        const idPedido = parseInt(pedidoParam);
        if (!idPedido) return;

        MikeloAuth.fetch(`/pedidos/${idPedido}`)
            .then(r => {
                if (!r) return null; // 401 → MikeloAuth ya redirigió al login
                return r.json();
            })
            .then(async (data) => {
                if (!data) return;
                if (data.error || !data.pedido) {
                    Swal.fire('Error', 'No se pudo cargar el pedido #' + idPedido, 'error');
                    return;
                }

                const pedido = data.pedido;
                pedidoOrigenId = pedido.id;
                pedidoItems = pedido.items || [];

                // Esperar a que el select de destino esté poblado
                await promesaUbicaciones;

                // Mostrar panel de nuevo envío y setear destino
                mostrarPanelNuevoEnvio();
                $('#destinoEnvio').val(String(pedido.id_sucursal));
                $('#sectionBusquedaProductos').slideDown();
                setTimeout(() => $('#buscarProductoEnvio').focus(), 400);

                // ── Construir panel de faltantes ──────────────────────────────
                const items = pedido.items || [];

                let filasHtml = '';
                for (const item of items) {
                    const cantPedida   = parseFloat(item.cantidad || 0);
                    const stockDisp    = parseFloat(item.stock_disponible || 0);
                    const cantEnviada  = parseFloat(item.cantidad_enviada || 0);
                    const pendiente    = Math.max(0, cantPedida - cantEnviada);
                    const codigo       = item.codigo_producto || item.codigo || '';
                    const nombre       = item.nombre || item.producto || item.descripcion || codigo;

                    const sinStock     = stockDisp <= 0;
                    const rowClass     = sinStock ? 'table-warning' : '';
                    const stockBadge   = sinStock
                        ? `<span class="badge badge-warning">Sin stock</span>`
                        : `<strong class="text-success">${stockDisp}</strong>`;

                    const btnAgregar   = sinStock
                        ? `<button class="btn btn-sm btn-secondary" disabled title="Sin stock disponible">
                               <i class="fas fa-plus"></i>
                           </button>`
                        : `<button class="btn btn-sm btn-primary btn-agregar-faltante"
                               data-codigo="${codigo}"
                               data-cantidad="${pendiente}"
                               title="Buscar y agregar al envío">
                               <i class="fas fa-plus"></i> Agregar
                           </button>`;

                    filasHtml += `
                        <tr class="${rowClass}" data-id-producto="${item.id_producto}" data-cant-pedida="${cantPedida}" data-cant-enviada="${cantEnviada}" data-stock-disp="${stockDisp}" data-codigo="${codigo}">
                            <td><code>${codigo}</code></td>
                            <td>${nombre}</td>
                            <td class="text-center">${cantPedida}</td>
                            <td class="text-center">${cantEnviada > 0 ? cantEnviada : '-'}</td>
                            <td class="text-center td-pendiente"><strong>${pendiente}</strong></td>
                            <td class="text-center td-stock">${stockBadge}</td>
                            <td class="text-center td-accion">${btnAgregar}</td>
                        </tr>`;
                }

                if (!filasHtml) {
                    filasHtml = '<tr><td colspan="7" class="text-center text-muted">Sin ítems en el pedido</td></tr>';
                }

                const panelFaltantes = `
                    <div class="card card-outline card-warning mb-3" id="panelFaltantesPedido">
                        <div class="card-header">
                            <h3 class="card-title">
                                <i class="fas fa-clipboard-list mr-2"></i>
                                Faltante del Pedido <strong>#${pedido.id}</strong>
                                <span class="ml-2 text-muted" style="font-size:0.9em;">
                                    — ${pedido.sucursal || 'Sucursal #' + pedido.id_sucursal}
                                </span>
                            </h3>
                            <div class="card-tools">
                                <button type="button" class="btn btn-tool" data-card-widget="collapse" title="Minimizar">
                                    <i class="fas fa-minus"></i>
                                </button>
                            </div>
                        </div>
                        <div class="card-body p-0">
                            <div class="table-responsive">
                                <table class="table table-sm table-hover mb-0">
                                    <thead class="thead-light">
                                        <tr>
                                            <th>Código</th>
                                            <th>Producto</th>
                                            <th class="text-center">Pedido</th>
                                            <th class="text-center">Ya enviado</th>
                                            <th class="text-center">Pendiente</th>
                                            <th class="text-center">Stock depósito</th>
                                            <th class="text-center">Acción</th>
                                        </tr>
                                    </thead>
                                    <tbody>${filasHtml}</tbody>
                                </table>
                            </div>
                        </div>
                        <div class="card-footer text-muted small">
                            <i class="fas fa-info-circle mr-1"></i>
                            Usá el buscador de abajo para agregar productos al envío. El botón <em>Agregar</em> de cada fila carga automáticamente ese producto.
                        </div>
                    </div>`;

                // Insertar el panel ANTES del campo de búsqueda
                $('#sectionBusquedaProductos').prepend(panelFaltantes);

                // ── Evento: botón Agregar de cada fila de faltantes ──────────
                $(document).on('click', '.btn-agregar-faltante', function() {
                    const codigo = $(this).data('codigo');

                    // Poner el código en el buscador y disparar búsqueda normal.
                    // Así el usuario ve TODOS los batches disponibles (crítico para
                    // productos por peso, donde cada movimiento_item tiene su propio
                    // cnt_peso) y elige el correcto, igual que si lo hubiese tipeado.
                    $('#buscarProductoEnvio').val(codigo);
                    buscarProductosDisponibles(codigo);

                    // Hacer scroll hasta el buscador para que sea visible
                    $('html, body').animate({
                        scrollTop: $('#buscarProductoEnvio').offset().top - 120
                    }, 300, function() {
                        $('#buscarProductoEnvio').focus();
                    });
                });
            })
            .catch(e => {
                console.error('Error cargando pedido:', e);
                Swal.fire('Error', 'No se pudieron cargar los datos del pedido', 'error');
            });
    })();

    // Funciones globales
    window.confirmarEnvio = confirmarEnvio;
    window.exportarDetalle = exportarDetalle;
    window.imprimirDetalle = imprimirDetalle;
    window.exportarRemitoPreimpreso = exportarRemitoPreimpreso;
    window.cargarEnvioParaEdicion = cargarEnvioParaEdicion;
    window.confirmarCancelacionEnvio = confirmarCancelacionEnvio;
    
    // Verificación de carga exitosa
    console.log('✅ Envios Nuevo JS cargado correctamente - v20251021_edicion');
    console.log('Funciones disponibles:', {
        confirmarEnvio: typeof window.confirmarEnvio,
        exportarDetalle: typeof window.exportarDetalle,
        imprimirDetalle: typeof window.imprimirDetalle,
        exportarRemitoPreimpreso: typeof window.exportarRemitoPreimpreso,
        cargarEnvioParaEdicion: typeof window.cargarEnvioParaEdicion,
        confirmarCancelacionEnvio: typeof window.confirmarCancelacionEnvio
    });
});