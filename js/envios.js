$(document).ready(function() {
    // Variables globales
    let productosSeleccionados = [];
    let tablaProductos = null;

    // Inicialización
    cargarUbicaciones();
    cargarEnvios();

    // Evs
    $('#btnNuevoEnvio').click(function() {
        $('#modalNuevoEnvio').modal('show');
    });

    $('#btnAgregarProducto').click(function() {
        $('#modalSeleccionProductos').modal('show');
        cargarProductosDisponibles();
    });

    $('#btnFiltrar').click(function() {
        cargarEnvios();
    });

    $('#btnLimpiarFiltros').click(function() {
        $('#fechaDesde').val('');
        $('#fechaHasta').val('');
        $('#selectDestino').val('');
        $('#selectEstado').val('');
        cargarEnvios();
    });

    $('#btnGuardarEnvio').click(function() {
        guardarEnvio();
    });

    $('#buscarProducto').on('keyup', function() {
        filtrarProductos($(this).val());
    });

    $('#btnExportarPDF').click(function() {
        exportarLista('pdf');
    });

    $('#btnExportarExcel').click(function() {
        exportarLista('excel');
    });

    // Funciones de carga de datos
    function cargarUbicaciones() {
        $.get('api/ubicaciones')
            .done(function(response) {
                let ubicaciones = response.ubicaciones;
                let options = '<option value="">Seleccione...</option>';
                ubicaciones.forEach(function(ubicacion) {
                    options += `<option value="${ubicacion.id}">${ubicacion.nombre}</option>`;
                });
                $('#selectDestino, #filtroDestino').html(options);
            })
            .fail(function(xhr) {
                mostrarError('Error al cargar ubicaciones');
            });
    }

    function cargarEnvios() {
        let filtros = {
            fechaDesde: $('#fechaDesde').val(),
            fechaHasta: $('#fechaHasta').val(),
            destino: $('#filtroDestino').val(),
            estado: $('#filtroEstado').val()
        };

        $.get('api/envios', filtros)
        .done(function(response) {
            if (response.success) {
                mostrarEnvios(response.data);
            }
        })
        .fail(function(xhr) {
            mostrarError('Error al cargar envíos');
        });
    }

    function cargarProductosDisponibles() {
        $.get('api/envios/productos-disponibles')
        .done(function(response) {
            if (response.success) {
                mostrarProductosDisponibles(response.data);
            }
        })
        .fail(function(xhr) {
            mostrarError('Error al cargar productos disponibles');
        });
    }


    // Funciones de visualización
    function mostrarEnvios(envios) {
        let html = '';
        envios.forEach(function(envio) {
            html += `
                <tr data-id="${envio.id}" style="cursor: pointer;" onclick="verDetalleEnvio(${envio.id})">
                    <td>${envio.fechaAlta}</td>
                    <td>${envio.origen}</td>
                    <td>${envio.destino}</td>
                    <td>${envio.cantidad_items}</td>
                    <td>${envio.peso_total} kg</td>
                    <td><span class="badge badge-${getBadgeClass(envio.ultimo_estado)}">${envio.ultimo_estado}</span></td>
                    <td>
                        <button class="btn btn-sm btn-info" onclick="event.stopPropagation(); verDetalleEnvio(${envio.id})">
                            <i class="fas fa-eye"></i>
                        </button>
                        <button class="btn btn-sm btn-primary" onclick="event.stopPropagation(); exportarDetalle(${envio.id}, 'pdf')">
                            <i class="fas fa-file-pdf"></i>
                        </button>
                        <button class="btn btn-sm btn-success" onclick="event.stopPropagation(); exportarDetalle(${envio.id}, 'excel')">
                            <i class="fas fa-file-excel"></i>
                        </button>
                    </td>
                </tr>
            `;
        });
        $('#enviosTable').html(html);
    }

    function mostrarProductosDisponibles(productos) {
        let html = '';
        productos.forEach(function(producto) {
            // No mostrar productos ya seleccionados
            if (productosSeleccionados.find(p => p.id_movimiento_item === producto.id_movimiento_item)) {
                return;
            }

            html += `
                <tr data-id="${producto.id_movimiento_item}">
                    <td>${producto.codigo}</td>
                    <td>${producto.descripcion}</td>
                    <td>${producto.cnt}</td>
                    <td>${producto.cnt_peso} kg</td>
                    <td>${producto.contenedor || '-'}</td>
                    <td>
                        <button class="btn btn-sm btn-primary" onclick="seleccionarProducto(${JSON.stringify(producto).replace(/"/g, '&quot;')})">
                            <i class="fas fa-plus"></i>
                        </button>
                    </td>
                </tr>
            `;
        });
        $('#productosDisponiblesTable').html(html);
    }

    function actualizarTablaProductosSeleccionados() {
        let html = '';
        let totalPeso = 0;
        let totalItems = 0;

        productosSeleccionados.forEach(function(producto, index) {
            totalPeso += parseFloat(producto.cnt_peso);
            totalItems += parseInt(producto.cnt);

            html += `
                <tr>
                    <td>${producto.codigo}</td>
                    <td>${producto.descripcion}</td>
                    <td>${producto.contenedor || '-'}</td>
                    <td>${producto.cnt}</td>
                    <td>${producto.cnt_peso} kg</td>
                    <td>
                        <button class="btn btn-sm btn-danger" onclick="quitarProducto(${index})">
                            <i class="fas fa-trash"></i>
                        </button>
                    </td>
                </tr>
            `;
        });

        // Agregar fila de totales
        if (productosSeleccionados.length > 0) {
            html += `
                <tr class="font-weight-bold">
                    <td colspan="3">TOTALES:</td>
                    <td>${totalItems}</td>
                    <td>${totalPeso.toFixed(2)} kg</td>
                    <td></td>
                </tr>
            `;
        }

        $('#productosEnvioTable').html(html);

        // Si no hay productos seleccionados, deshabilitar el botón de guardar
        $('#btnGuardarEnvio').prop('disabled', productosSeleccionados.length === 0);
    }

    // Funciones de manipulación de productos
    window.seleccionarProducto = function(producto) {
        productosSeleccionados.push(producto);
        actualizarTablaProductosSeleccionados();
        
        // Actualizar tabla de productos disponibles
        $(`#productosDisponiblesTable tr[data-id="${producto.id_movimiento_item}"]`).remove();
    };

    window.quitarProducto = function(index) {
        productosSeleccionados.splice(index, 1);
        actualizarTablaProductosSeleccionados();
    };

    window.actualizarPesoProducto = function(index, contenedorId) {
        let producto = productosSeleccionados[index];
        let select = $(`.contenedor-select[data-index="${index}"]`);
        let pesoContenedor = select.find(`option[value="${contenedorId}"]`).data('peso') || 0;
        
        producto.id_contenedor = contenedorId;
        producto.peso_contenedor = pesoContenedor;
        producto.peso_total = producto.cnt_peso + pesoContenedor;
        
        actualizarTablaProductosSeleccionados();
    };

    // Funciones de guardado y exportación
    function guardarEnvio() {
        if (!validarEnvio()) {
            return;
        }

        let data = {
            destino: $('#selectDestino').val(),
            productos: productosSeleccionados.map(p => ({
                id_productos: p.id_producto,
                id_movimientos_items_origen: p.id_movimiento_item,
                cantidad: p.cnt,
                peso: p.cnt_peso
            }))
        };

        $.ajax({
            url: 'api/envios',
            method: 'POST',
            contentType: 'application/json',
            data: JSON.stringify(data)
        })
        .done(function(response) {
            if (response.success) {
                Swal.fire({
                    icon: 'success',
                    title: 'Éxito',
                    text: 'Envío creado correctamente'
                }).then(() => {
                    $('#modalNuevoEnvio').modal('hide');
                    limpiarFormulario();
                    cargarEnvios();
                });
            }
        })
        .fail(function(xhr) {
            mostrarError('Error al guardar el envío');
        });
    }

    window.verDetalleEnvio = function(id) {
        $.get(`api/envios/${id}`)
        .done(function(response) {
            if (response.success) {
                mostrarDetalleEnvio(response.data);
                $('#modalDetalleEnvio').modal('show');
            }
        })
        .fail(function(xhr) {
            mostrarError('Error al cargar el detalle del envío');
        });
    };

    function mostrarDetalleEnvio(data) {
        let envio = data.envio;
        let productos = data.productos;
        let totales = data.totales;

        $('#detalleEnvioFecha').text(envio.fecha_alta);
        $('#detalleEnvioDestino').text(envio.destino_nombre);
        $('#detalleEnvioEstado').html(`<span class="badge badge-info">Enviado</span>`);

        let productosHtml = '';
        productos.forEach(function(producto) {
            productosHtml += `
                <tr>
                    <td>${producto.codigo}</td>
                    <td>${producto.descripcion}</td>
                    <td>${producto.contenedor || '-'}</td>
                    <td>${producto.cnt}</td>
                    <td>${producto.cnt_peso} kg</td>
                    <td>${producto.peso_neto || producto.cnt_peso} kg</td>
                </tr>
            `;
        });

        // Agregar totales
        if (productos.length > 0) {
            productosHtml += `
                <tr class="font-weight-bold">
                    <td colspan="3">TOTALES:</td>
                    <td>${totales.cantidad_total}</td>
                    <td>${totales.peso_bruto_total} kg</td>
                    <td>${totales.peso_neto_total} kg</td>
                </tr>
            `;
        }

        $('#detalleEnvioProductosTable').html(productosHtml);
    }

    window.exportarDetalle = function(id, formato) {
        window.location.href = `api/envios/${id}/${formato}`;
    };

    function exportarLista(formato) {
        let filtros = {
            fechaDesde: $('#fechaDesde').val(),
            fechaHasta: $('#fechaHasta').val(),
            destino: $('#filtroDestino').val(),
            estado: $('#filtroEstado').val()
        };

        let queryString = Object.keys(filtros)
            .filter(key => filtros[key])
            .map(key => `${key}=${filtros[key]}`)
            .join('&');

        window.location.href = `api/envios/${formato}?${queryString}`;
    }

    // Funciones auxiliares
    function validarEnvio() {
        if (!$('#selectDestino').val()) {
            mostrarError('Debe seleccionar un destino');
            return false;
        }

        if (productosSeleccionados.length === 0) {
            mostrarError('Debe seleccionar al menos un producto');
            return false;
        }

        return true;
    }

    function limpiarFormulario() {
        $('#selectDestino').val('');
        productosSeleccionados = [];
        actualizarTablaProductosSeleccionados();
    }

    function filtrarProductos(texto) {
        texto = texto.toLowerCase();
        $('#productosDisponiblesTable tr').each(function() {
            let codigo = $(this).find('td:first').text().toLowerCase();
            let descripcion = $(this).find('td:eq(1)').text().toLowerCase();
            $(this).toggle(codigo.includes(texto) || descripcion.includes(texto));
        });
    }

    function getBadgeClass(estado) {
        switch (estado.toLowerCase()) {
            case 'nuevo': return 'info';
            case 'enviado': return 'success';
            case 'cancelado': return 'danger';
            default: return 'secondary';
        }
    }

    function mostrarError(mensaje) {
        Swal.fire({
            icon: 'error',
            title: 'Error',
            text: mensaje
        });
    }
});