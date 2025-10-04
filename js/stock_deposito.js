$(document).ready(function() {
    // Variables globales
    let stockData = [];
    let bandejasSeleccionadas = [];

    // Inicializar la página
    inicializarPagina();

    // Event Listeners
    $('#btnFiltrar').click(function() {
        cargarStock();
    });

    $('#btnLimpiar').click(function() {
        limpiarFiltros();
    });

    $('#btnExportarPDF').click(function() {
        exportarStock('pdf');
    });

    $('#btnExportarExcel').click(function() {
        exportarStock('excel');
    });

    // Modal de detalle
    $('#btnCambiarContenedor').click(function() {
        if (bandejasSeleccionadas.length === 0) {
            mostrarError('Debe seleccionar al menos una bandeja');
            return;
        }
        $('#cantidadBandejasSeleccionadas').text(bandejasSeleccionadas.length);
        $('#modalCambiarContenedor').modal('show');
    });

    $('#btnDarDeBaja').click(function() {
        if (bandejasSeleccionadas.length === 0) {
            mostrarError('Debe seleccionar al menos una bandeja');
            return;
        }
        $('#cantidadBandejasSeleccionadasBaja').text(bandejasSeleccionadas.length);
        $('#modalDarDeBaja').modal('show');
    });

    $('#btnConfirmarCambioContenedor').click(function() {
        confirmarCambioContenedor();
    });

    $('#btnConfirmarBaja').click(function() {
        confirmarBaja();
    });

    // Seleccionar todas las bandejas
    $('#selectAllBandejas').change(function() {
        const isChecked = $(this).is(':checked');
        $('#detalleBandejasTable input[type=\"checkbox\"]').prop('checked', isChecked);
        actualizarBandejasSeleccionadas();
    });

    // Funciones principales
    function inicializarPagina() {
        cargarContenedores();
        cargarStock();
        
        // Establecer fecha por defecto (últimos 30 días)
        const hoy = new Date();
        const hace30Dias = new Date();
        hace30Dias.setDate(hoy.getDate() - 30);
        
        $('#fechaHasta').val(hoy.toISOString().split('T')[0]);
        $('#fechaDesde').val(hace30Dias.toISOString().split('T')[0]);
    }

    function cargarContenedores() {
        fetch('api/envios/contenedores')
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    const selectores = ['#filtroContenedor', '#nuevoContenedor'];
                    
                    selectores.forEach(selector => {
                        const $select = $(selector);
                        $select.empty();
                        if (selector === '#filtroContenedor') {
                            $select.append('<option value=\"\">Todos</option>');
                        } else {
                            $select.append('<option value=\"\">Seleccione contenedor...</option>');
                        }
                        
                        data.data.forEach(contenedor => {
                            $select.append(`<option value=\"${contenedor.id}\">${contenedor.nombre} (${contenedor.peso}kg)</option>`);
                        });
                    });
                }
            })
            .catch(error => {
                mostrarError('Error al cargar contenedores: ' + error.message);
            });
    }

    function cargarStock() {
        let filtros = obtenerFiltros();
        let queryString = Object.keys(filtros)
            .filter(key => filtros[key])
            .map(key => `${key}=${encodeURIComponent(filtros[key])}`)
            .join('&');

        mostrarCargando('Cargando stock...');

        fetch(`api/stock-deposito?${queryString}`)
            .then(response => response.json())
            .then(data => {
                Swal.close();
                if (data.success) {
                    stockData = data.data;
                    mostrarStock(stockData);
                    actualizarResumen(stockData);
                } else {
                    mostrarError(data.error || 'Error al cargar el stock');
                }
            })
            .catch(error => {
                Swal.close();
                mostrarError('Error de conexión: ' + error.message);
            });
    }

    function obtenerFiltros() {
        return {
            producto: $('#filtroProducto').val(),
            contenedor: $('#filtroContenedor').val(),
            fechaDesde: $('#fechaDesde').val(),
            fechaHasta: $('#fechaHasta').val()
        };
    }

    function mostrarStock(stock) {
        const $tbody = $('#stockTable');
        $tbody.empty();

        if (stock.length === 0) {
            $tbody.append(`
                <tr>
                    <td colspan=\"8\" class=\"text-center text-muted\">
                        <i class=\"fas fa-inbox fa-2x mb-2\"></i><br>
                        No hay productos en stock con los filtros aplicados
                    </td>
                </tr>
            `);
            return;
        }

        stock.forEach(producto => {
            const contenedores = Array.isArray(producto.contenedores) 
                ? producto.contenedores.join(', ') 
                : (producto.contenedores || '-');

            $tbody.append(`
                <tr>
                    <td><strong>${producto.codigo}</strong></td>
                    <td>${producto.descripcion}</td>
                    <td class=\"text-center\">
                        <span class=\"badge badge-primary\">${producto.total_unidades}</span>
                    </td>
                    <td class=\"text-right\">${formatearPeso(producto.total_peso_bruto)}</td>
                    <td class=\"text-right\">${formatearPeso(producto.total_peso_neto)}</td>
                    <td><small>${contenedores}</small></td>
                    <td><small>${formatearFecha(producto.fecha_mas_antigua)}</small></td>
                    <td>
                        <button class=\"btn btn-sm btn-info\" onclick=\"verDetalleBandejas('${producto.id_producto}', '${producto.codigo}', '${producto.descripcion}')\">
                            <i class=\"fas fa-eye\"></i> Ver Bandejas
                        </button>
                    </td>
                </tr>
            `);
        });
    }

    function actualizarResumen(stock) {
        const resumen = stock.reduce((acc, producto) => {
            acc.productos += 1;
            acc.unidades += parseInt(producto.total_unidades);
            acc.kilosBrutos += parseFloat(producto.total_peso_bruto);
            acc.kilosNetos += parseFloat(producto.total_peso_neto);
            return acc;
        }, { productos: 0, unidades: 0, kilosBrutos: 0, kilosNetos: 0 });

        $('#totalProductos').text(resumen.productos);
        $('#totalUnidades').text(resumen.unidades);
        $('#totalKilos').text(formatearPeso(resumen.kilosBrutos));
        $('#totalKilosNetos').text(formatearPeso(resumen.kilosNetos));
    }

    window.verDetalleBandejas = function(idProducto, codigo, descripcion) {
        $('#detalleProductoCodigo').text(codigo);
        $('#detalleProductoDescripcion').text(descripcion);
        
        mostrarCargando('Cargando detalle de bandejas...');

        fetch(`api/stock-deposito/${idProducto}/bandejas`)
            .then(response => response.json())
            .then(data => {
                Swal.close();
                if (data.success) {
                    mostrarDetalleBandejas(data.data);
                    $('#modalDetalleBandejas').modal('show');
                } else {
                    mostrarError(data.error || 'Error al cargar detalle de bandejas');
                }
            })
            .catch(error => {
                Swal.close();
                mostrarError('Error de conexión: ' + error.message);
            });
    };

    function mostrarDetalleBandejas(bandejas) {
        const $tbody = $('#detalleBandejasTable');
        $tbody.empty();

        let totalCantidad = 0;
        let totalPesoBruto = 0;
        let totalPesoContenedor = 0;
        let totalPesoNeto = 0;

        bandejas.forEach(bandeja => {
            const pesoContenedor = parseFloat(bandeja.peso_contenedor) || 0;
            const pesoBruto = parseFloat(bandeja.cnt_peso) || 0;
            const pesoNeto = bandeja.peso_contenedor !== null ? (pesoBruto - pesoContenedor) : pesoBruto;
            
            totalCantidad += parseInt(bandeja.cnt) || 0;
            totalPesoBruto += pesoBruto;
            totalPesoContenedor += pesoContenedor;
            totalPesoNeto += pesoNeto;

            $tbody.append(`
                <tr data-id=\"${bandeja.id_movimiento_item}\">
                    <td>
                        <input type=\"checkbox\" class=\"bandeja-checkbox\" value=\"${bandeja.id_movimiento_item}\">
                    </td>
                    <td>${formatearFecha(bandeja.fechaAlta)}</td>
                    <td>${bandeja.contenedor || '-'}</td>
                    <td class=\"text-center\">${bandeja.cnt}</td>
                    <td class=\"text-right\">${formatearPeso(pesoBruto)}</td>
                    <td class=\"text-right\">${pesoContenedor > 0 ? formatearPeso(pesoContenedor) : '-'}</td>
                    <td class=\"text-right\">${formatearPeso(pesoNeto)}</td>
                    <td>
                        <span class=\"badge badge-success\">${bandeja.estado || 'Disponible'}</span>
                    </td>
                    <td>
                        <button class=\"btn btn-xs btn-warning\" onclick=\"cambiarContenedorIndividual('${bandeja.id_movimiento_item}')\">
                            <i class=\"fas fa-box\"></i>
                        </button>
                        <button class=\"btn btn-xs btn-danger\" onclick=\"darDeBajaIndividual('${bandeja.id_movimiento_item}')\">
                            <i class=\"fas fa-trash\"></i>
                        </button>
                    </td>
                </tr>
            `);
        });

        // Actualizar totales
        $('#detalleTotalCantidad').text(totalCantidad);
        $('#detalleTotalPesoBruto').text(formatearPeso(totalPesoBruto));
        $('#detalleTotalPesoContenedor').text(formatearPeso(totalPesoContenedor));
        $('#detalleTotalPesoNeto').text(formatearPeso(totalPesoNeto));

        // Actualizar resumen
        $('#detalleResumen').text(`${bandejas.length} bandejas - ${totalCantidad} unidades - ${formatearPeso(totalPesoNeto)} netos`);

        // Event listener para checkboxes
        $('.bandeja-checkbox').change(function() {
            actualizarBandejasSeleccionadas();
        });

        // Limpiar selección
        bandejasSeleccionadas = [];
        $('#selectAllBandejas').prop('checked', false);
    }

    function actualizarBandejasSeleccionadas() {
        bandejasSeleccionadas = [];
        $('.bandeja-checkbox:checked').each(function() {
            bandejasSeleccionadas.push($(this).val());
        });
        
        // Actualizar estado del checkbox principal
        const totalCheckboxes = $('.bandeja-checkbox').length;
        const checkedCheckboxes = $('.bandeja-checkbox:checked').length;
        
        if (checkedCheckboxes === 0) {
            $('#selectAllBandejas').prop('indeterminate', false).prop('checked', false);
        } else if (checkedCheckboxes === totalCheckboxes) {
            $('#selectAllBandejas').prop('indeterminate', false).prop('checked', true);
        } else {
            $('#selectAllBandejas').prop('indeterminate', true);
        }
    }

    function confirmarCambioContenedor() {
        const nuevoContenedor = $('#nuevoContenedor').val();
        const motivo = $('#motivoCambio').val().trim();

        if (!nuevoContenedor) {
            mostrarError('Debe seleccionar un contenedor');
            return;
        }

        if (!motivo) {
            mostrarError('Debe ingresar el motivo del cambio');
            return;
        }

        mostrarCargando('Cambiando contenedor...');

        const data = {
            bandejas: bandejasSeleccionadas,
            nuevo_contenedor: nuevoContenedor,
            motivo: motivo
        };

        fetch('api/stock-deposito/cambiar-contenedor', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify(data)
        })
        .then(response => response.json())
        .then(data => {
            Swal.close();
            if (data.success) {
                $('#modalCambiarContenedor').modal('hide');
                $('#motivoCambio').val('');
                $('#nuevoContenedor').val('');
                
                Swal.fire({
                    icon: 'success',
                    title: 'Contenedor cambiado',
                    text: 'El contenedor se ha cambiado exitosamente',
                    timer: 2000,
                    showConfirmButton: false
                });

                // Recargar datos
                cargarStock();
                // Cerrar modal de detalle y volver a abrirlo actualizado
                $('#modalDetalleBandejas').modal('hide');
            } else {
                mostrarError(data.error || 'Error al cambiar el contenedor');
            }
        })
        .catch(error => {
            Swal.close();
            mostrarError('Error de conexión: ' + error.message);
        });
    }

    function confirmarBaja() {
        const motivo = $('#motivoBaja').val().trim();

        if (!motivo) {
            mostrarError('Debe ingresar el motivo de la baja');
            return;
        }

        mostrarCargando('Dando de baja productos...');

        const data = {
            bandejas: bandejasSeleccionadas,
            motivo: motivo
        };

        fetch('api/stock-deposito/dar-baja', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify(data)
        })
        .then(response => response.json())
        .then(data => {
            Swal.close();
            if (data.success) {
                $('#modalDarDeBaja').modal('hide');
                $('#motivoBaja').val('');
                
                Swal.fire({
                    icon: 'success',
                    title: 'Productos dados de baja',
                    text: 'Los productos se han dado de baja exitosamente',
                    timer: 2000,
                    showConfirmButton: false
                });

                // Recargar datos
                cargarStock();
                // Cerrar modal de detalle
                $('#modalDetalleBandejas').modal('hide');
            } else {
                mostrarError(data.error || 'Error al dar de baja los productos');
            }
        })
        .catch(error => {
            Swal.close();
            mostrarError('Error de conexión: ' + error.message);
        });
    }

    function exportarStock(formato) {
        let filtros = obtenerFiltros();
        let queryString = Object.keys(filtros)
            .filter(key => filtros[key])
            .map(key => `${key}=${encodeURIComponent(filtros[key])}`)
            .join('&');

        mostrarCargando('Generando reporte...');

        fetch(`api/stock-deposito/${formato}?${queryString}`)
            .then(response => response.json())
            .then(data => {
                Swal.close();
                if (data.success) {
                    // Descargar el archivo
                    const link = document.createElement('a');
                    link.href = data.url;
                    link.download = data.url.split('/').pop();
                    document.body.appendChild(link);
                    link.click();
                    document.body.removeChild(link);
                    
                    Swal.fire({
                        icon: 'success',
                        title: 'Reporte generado',
                        text: 'El reporte se ha descargado exitosamente',
                        timer: 2000,
                        showConfirmButton: false
                    });
                } else {
                    mostrarError(data.error || 'Error al generar el reporte');
                }
            })
            .catch(error => {
                Swal.close();
                mostrarError('Error de conexión: ' + error.message);
            });
    }

    function limpiarFiltros() {
        $('#filtroProducto').val('');
        $('#filtroContenedor').val('');
        $('#fechaDesde').val('');
        $('#fechaHasta').val('');
        cargarStock();
    }

    // Funciones auxiliares
    function formatearPeso(peso) {
        return parseFloat(peso).toFixed(2) + ' kg';
    }

    function formatearFecha(fecha) {
        return new Date(fecha).toLocaleDateString('es-AR', {
            day: '2-digit',
            month: '2-digit',
            year: 'numeric',
            hour: '2-digit',
            minute: '2-digit'
        });
    }

    function mostrarCargando(mensaje) {
        Swal.fire({
            title: mensaje,
            text: 'Por favor espere...',
            allowOutsideClick: false,
            showConfirmButton: false,
            willOpen: () => {
                Swal.showLoading();
            }
        });
    }

    function mostrarError(mensaje) {
        Swal.fire({
            icon: 'error',
            title: 'Error',
            text: mensaje
        });
    }

    // Funciones individuales (acciones rápidas)
    window.cambiarContenedorIndividual = function(idBandeja) {
        bandejasSeleccionadas = [idBandeja];
        $('#cantidadBandejasSeleccionadas').text(1);
        $('#modalCambiarContenedor').modal('show');
    };

    window.darDeBajaIndividual = function(idBandeja) {
        bandejasSeleccionadas = [idBandeja];
        $('#cantidadBandejasSeleccionadasBaja').text(1);
        $('#modalDarDeBaja').modal('show');
    };
});