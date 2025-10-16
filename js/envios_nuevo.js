$(document).ready(function() {
    // Variables globales
    let productosEnEnvio = [];
    let envioActual = null;
    let html5QrcodeScanner = null;
    let timeoutBusqueda = null;

    // Inicialización
    cargarUbicaciones();
    cargarEstados();
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

    // Delegación de eventos para botones de la tabla (compatibilidad hosting)
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

    // === FUNCIONES PRINCIPALES ===

    function mostrarPanelNuevoEnvio() {
        $('#panelFiltros, #panelTablaEnvios').hide();
        $('#panelNuevoEnvio').slideDown();
        $('#destinoEnvio').focus();
        limpiarFormularioEnvio();
    }

    function cerrarPanelNuevoEnvio() {
        $('#panelNuevoEnvio').slideUp();
        $('#panelFiltros, #panelTablaEnvios').slideDown();
        cargarEnvios(); // Refrescar tabla
    }

    function iniciarNuevoEnvio() {
        $('#panelPostGuardado').hide();
        $('#sectionBusquedaProductos').show();
        limpiarFormularioEnvio();
        $('#destinoEnvio').focus();
    }

    function limpiarFormularioEnvio() {
        $('#destinoEnvio').val('');
        $('#buscarProductoEnvio').val('');
        $('#sectionBusquedaProductos').hide();
        $('#productosEncontrados').hide();
        $('#productosEnvio').hide();
        $('#panelPostGuardado').hide();
        $('#estadoOperacionEnvio').hide();
        
        productosEnEnvio = [];
        envioActual = null;
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
                // Tipo 20: Unidades
                params.append('codigo', codigoProducto);
                params.append('cantidad', valorCantidadPeso);
                console.log('Parámetros tipo 20:', { codigo: codigoProducto, cantidad: valorCantidadPeso });
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
                console.log('Productos disponibles received:', data); // Debug
                if (data.success) {
                    const productos = data.data || data.productos || []; // Compatibilidad con ambas estructuras
                    if (productos.length === 0) {
                        mostrarEstadoOperacion('No se encontraron productos disponibles', 'warning');
                        $('#productosEncontrados').hide();
                    } else if (productos.length === 1 && esCodigoBarras) {
                        // Un solo producto con código de barras: agregar automáticamente
                        agregarProductoAlEnvio(productos[0]);
                        limpiarBusquedaEnvio();
                        mostrarEstadoOperacion('Producto agregado correctamente', 'success');
                    } else {
                        // Múltiples productos o búsqueda manual: mostrar tabla
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
    window.agregarProductoAlEnvio = function(producto) {
        // Verificar si ya está en el envío
        const existe = productosEnEnvio.find(p => p.id_movimiento_item === producto.id_movimiento_item);
        if (existe) {
            mostrarEstadoOperacion('Este producto ya está en el envío', 'warning');
            return;
        }

        // Calcular cantidad disponible
        const cantidadDisponible = producto.cnt_disponible !== undefined ? producto.cnt_disponible : producto.cnt;
        
        if (cantidadDisponible <= 0) {
            mostrarEstadoOperacion('No hay stock disponible de este producto', 'warning');
            return;
        }

        // Agregar con cantidad inicial = 1 (o el mínimo disponible)
        const cantidadInicial = Math.min(1, cantidadDisponible);
        const pesoUnitario = producto.cnt_peso / producto.cnt; // Peso por unidad
        const pesoInicial = (pesoUnitario * cantidadInicial).toFixed(3);

        const productoEnEnvio = {
            ...producto,
            cantidad: cantidadInicial,
            peso: parseFloat(pesoInicial),
            cnt_disponible: cantidadDisponible,
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
            productos: productosEnEnvio.map(p => ({
                id_movimientos_items_origen: p.id_movimiento_item,
                id_productos: p.id_producto,
                cantidad: p.cantidad || p.cnt,
                peso: p.peso !== undefined ? p.peso : p.cnt_peso
            }))
        };

        console.log('Datos del envío a guardar:', datosEnvio);

        fetch('api/envios', {
            method: 'POST',
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
                envioActual = { id: data.id };
                
                Swal.fire({
                    title: 'Éxito',
                    text: 'Envío creado correctamente',
                    icon: 'success',
                    showCancelButton: true,
                    confirmButtonText: 'Confirmar Envío Ahora',
                    cancelButtonText: 'Volver a Lista',
                    reverseButtons: true
                }).then((result) => {
                    if (result.isConfirmed) {
                        // Confirmar envío inmediatamente
                        confirmarEnvio(data.id);
                    }
                    // En cualquier caso, cerrar panel y volver a la grilla
                    cerrarPanelNuevoEnvio();
                });
            } else {
                Swal.fire('Error', data.error || 'Error al crear el envío', 'error');
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

    function cargarUbicaciones() {
        fetch('api/ubicaciones')
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

    function cargarEnvios() {
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
                    const envios = data.data || data.envios || []; // Compatibilidad con ambas estructuras
                    mostrarEnviosEnTabla(envios);
                } else {
                    console.error('Error en respuesta envíos:', data);
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
                                data-action="exportar-pdf" 
                                data-envio-id="${envio.id}" 
                                title="Remito PDF">
                            <i class="fas fa-file-pdf"></i>
                        </button>
                        <button class="btn btn-sm btn-success" 
                                onclick="exportarDetalle(${envio.id}, 'excel')" 
                                data-action="exportar-excel" 
                                data-envio-id="${envio.id}" 
                                title="Detalle Excel">
                            <i class="fas fa-file-excel"></i>
                        </button>
                        ${envio.ultimo_estado === 'NUEVO' ? `
                            <button class="btn btn-sm btn-warning" 
                                    onclick="confirmarEnvio(${envio.id})" 
                                    data-action="confirmar-envio" 
                                    data-envio-id="${envio.id}" 
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
        const url = `api/envios/${endpoint}?${params.toString()}`;
        
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
        
        // Mostrar botón de confirmar si el estado es NUEVO
        if (detalle.envio.ultimo_estado === 'NUEVO') {
            $('#btnConfirmarEnvioModal').show().off('click').on('click', function() {
                confirmarEnvioDesdeModal(detalle.envio.id);
            });
        } else {
            $('#btnConfirmarEnvioModal').hide();
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
        
        // Mostrar loading
        Swal.fire({
            title: `Generando ${formato.toUpperCase()}...`,
            text: 'Por favor espere mientras se genera el remito',
            allowOutsideClick: false,
            didOpen: () => {
                Swal.showLoading();
            }
        });

        // Usar endpoint directo para descarga
        const url = `api/envios/${id}/${formato}`;
        console.log(`URL de descarga: ${url}`); // Debug
        
        // Crear enlace temporal para descarga
        const link = document.createElement('a');
        link.href = url;
        link.download = `envio_${id}.${formato}`;
        link.style.display = 'none';
        document.body.appendChild(link);
        
        // Simular clic para iniciar descarga
        link.click();
        document.body.removeChild(link);
        
        // Cerrar loading después de un momento
        setTimeout(() => {
            Swal.close();
            Swal.fire({
                title: 'Descarga iniciada',
                text: `El ${formato.toUpperCase()} del remito está siendo descargado`,
                icon: 'success',
                timer: 2000,
                showConfirmButton: false
            });
        }, 500);
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

    // Funciones globales
    window.confirmarEnvio = confirmarEnvio;
    window.exportarDetalle = exportarDetalle;
    window.imprimirDetalle = imprimirDetalle;
    
    // Verificación de carga exitosa
    console.log('✅ Envios Nuevo JS cargado correctamente - v20251010');
    console.log('Funciones disponibles:', {
        confirmarEnvio: typeof window.confirmarEnvio,
        exportarDetalle: typeof window.exportarDetalle,
        imprimirDetalle: typeof window.imprimirDetalle
    });
});