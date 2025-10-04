$(document).ready(function() {
    // Variables globales
    let productosSeleccionados = [];
    let tablaProductos = null;
    let html5QrcodeScanner = null;

    // Inicialización
    cargarUbicaciones();
    cargarEnvios();

    // Event Listeners
    $('#btnNuevoEnvio').click(function() {
        $('#modalNuevoEnvio').modal('show');
    });

    $('#btnAgregarProducto').click(function() {
        // Limpiar búsqueda anterior
        $('#buscarProducto').val('');
        $('#productosDisponiblesTable').empty();
        
        $('#modalSeleccionProductos').modal('show');
        
        // Focus en el input después de que se muestre el modal
        $('#modalSeleccionProductos').on('shown.bs.modal', function () {
            $('#buscarProducto').focus();
            $(this).off('shown.bs.modal'); // Remover el evento para no acumularlo
        });
    });

    $('#btnEscanearCodigo').click(function() {
        iniciarEscaneoCodigoBarras();
    });

    // Modal events para limpiar el escáner
    $('#modalEscaneo').on('hidden.bs.modal', function () {
        if (html5QrcodeScanner) {
            html5QrcodeScanner.clear().catch(error => {
                console.error("Error al limpiar el escáner:", error);
            });
            html5QrcodeScanner = null;
        }
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

    // NUEVA FUNCIONALIDAD: Búsqueda mejorada con soporte para códigos de barras
    $('#buscarProducto').on('keyup', function() {
        const texto = $(this).val().trim();
        
        if (texto.length === 0) {
            // Si no hay texto, limpiar la tabla
            $('#productosDisponiblesTable').empty();
            return;
        }
        
        // Verificar si es un código de barras
        if (texto.length === 13 && /^\d{13}$/.test(texto)) {
            procesarCodigoBarrasEnSeleccion(texto);
            return;
        }
        
        // Si hay al menos una letra, buscar productos
        if (texto.length >= 1) {
            buscarProductosDisponibles(texto);
        }
    });

    $('#btnExportarPDF').click(function() {
        exportarLista('pdf');
    });

    $('#btnExportarExcel').click(function() {
        exportarLista('excel');
    });

    // NUEVA FUNCIÓN: Buscar productos desde el servidor con filtro
    function buscarProductosDisponibles(filtro) {
        $.get(`api/envios/productos-disponibles?filtro=${encodeURIComponent(filtro)}`)
        .done(function(response) {
            if (response.success) {
                mostrarProductosDisponibles(response.data);
            }
        })
        .fail(function(xhr) {
            console.error('Error al buscar productos:', xhr);
        });
    }
    
    // NUEVA FUNCIÓN: Procesar códigos de barras en la selección de productos
    function procesarCodigoBarrasEnSeleccion(codigo) {
        try {
            const tipo = codigo.substring(0, 2);
            const codigoProducto = parseInt(codigo.substring(2, 7)).toString();
            const cantidadRaw = codigo.substring(7, 12);
            
            let cantidad, peso;
            
            if (tipo === '20') {
                // Código de cantidad (unidades)
                cantidad = parseInt(cantidadRaw) / 1000; // Los 5 dígitos representan cantidad * 1000
                peso = null;
            } else if (tipo === '21') {
                // Código de peso (kilogramos)
                cantidad = null;
                peso = parseInt(cantidadRaw) / 1000; // Los 5 dígitos representan peso * 1000
            } else {
                throw new Error(`Tipo de código no reconocido: ${tipo}`);
            }
            
            console.log('Código de barras procesado en selección:', {
                tipo,
                codigoProducto,
                cantidad,
                peso,
                codigoOriginal: codigo
            });
            
            // Buscar productos que coincidan con el código
            buscarYSeleccionarProductoPorCodigo(codigoProducto, cantidad, peso);
            
        } catch (error) {
            console.error('Error procesando código de barras:', error);
            Swal.fire({
                title: 'Error',
                text: `Error al procesar el código de barras: ${error.message}`,
                icon: 'error'
            });
        }
    }
    
    // NUEVA FUNCIÓN: Buscar y seleccionar automáticamente productos por código de barras
    function buscarYSeleccionarProductoPorCodigo(codigo, cantidad, peso) {
        // Mostrar indicador de carga
        Swal.fire({
            title: 'Buscando producto...',
            allowOutsideClick: false,
            didOpen: () => {
                Swal.showLoading();
            }
        });
        
        let url = `api/envios/productos-disponibles?codigo=${encodeURIComponent(codigo)}`;
        if (cantidad !== null) {
            url += `&cantidad=${cantidad}`;
        }
        if (peso !== null) {
            url += `&peso=${peso}`;
        }
        
        $.get(url)
        .done(function(response) {
            Swal.close();
            
            if (response.success && response.data && response.data.length > 0) {
                const productosCoincidentes = response.data;
                
                if (productosCoincidentes.length === 1) {
                    // Solo hay un producto que coincide exactamente
                    const producto = productosCoincidentes[0];
                    
                    // Verificar si ya está en la lista
                    const yaSeleccionado = productosSeleccionados.find(p => p.id_movimiento_item === producto.id_movimiento_item);
                    if (yaSeleccionado) {
                        Swal.fire({
                            title: 'Producto ya seleccionado',
                            text: `El producto ${producto.codigo} - ${producto.descripcion} ya está en la lista de envío.`,
                            icon: 'warning'
                        });
                        return;
                    }
                    
                    // Agregar automáticamente
                    agregarProductoAEnvio(producto);
                    $('#modalSeleccionProductos').modal('hide');
                    $('#buscarProducto').val('');
                    
                    Swal.fire({
                        title: 'Producto agregado',
                        text: `${producto.codigo} - ${producto.descripcion} agregado al envío.`,
                        icon: 'success',
                        timer: 2000,
                        showConfirmButton: false
                    });
                } else {
                    // Múltiples productos coinciden, mostrar para selección manual
                    mostrarProductosDisponibles(productosCoincidentes);
                    Swal.fire({
                        title: 'Múltiples productos encontrados',
                        text: `Se encontraron ${productosCoincidentes.length} productos que coinciden. Seleccione el producto deseado.`,
                        icon: 'info'
                    });
                }
            } else {
                Swal.fire({
                    title: 'Producto no encontrado',
                    text: `No se encontró ningún producto disponible con el código ${codigo}.`,
                    icon: 'warning'
                });
            }
        })
        .fail(function(xhr) {
            Swal.close();
            console.error('Error buscando producto:', xhr);
            Swal.fire({
                title: 'Error',
                text: 'Error al buscar el producto. Intente nuevamente.',
                icon: 'error'
            });
        });
    }

    // Funciones de escaneo de código de barras
    function iniciarEscaneoCodigoBarras() {
        $('#modalEscaneo').modal('show');
        
        // Configuración del escáner optimizada para códigos de barras
        const config = {
            fps: 10,
            qrbox: { width: 400, height: 150 }, // Más ancho para códigos de barras
            aspectRatio: 2.0,
            formatsToSupport: [
                Html5QrcodeSupportedFormats.EAN_13,
                Html5QrcodeSupportedFormats.EAN_8,
                Html5QrcodeSupportedFormats.CODE_128,
                Html5QrcodeSupportedFormats.CODE_39,
                Html5QrcodeSupportedFormats.UPC_A,
                Html5QrcodeSupportedFormats.UPC_E,
                Html5QrcodeSupportedFormats.ITF
            ]
        };
        
        html5QrcodeScanner = new Html5QrcodeScanner("reader", config, false);
        html5QrcodeScanner.render(onScanSuccess, onScanFailure);
    }

    function onScanSuccess(decodedText, decodedResult) {
        console.log(`Código escaneado: ${decodedText}`);
        
        // Cerrar el modal primero
        $('#modalEscaneo').modal('hide');
        
        // Procesar el código de barras
        procesarCodigoBarras(decodedText);
    }

    function onScanFailure(error) {
        // No hacer nada, simplemente continuar escaneando
    }

    function procesarCodigoBarras(codigo) {
        try {
            // Validar que sea un código de 13 dígitos
            if (!/^\d{13}$/.test(codigo)) {
                throw new Error("El código debe tener exactamente 13 dígitos numéricos");
            }
            
            const tipo = codigo.substring(0, 2);
            const codigoProducto = parseInt(codigo.substring(2, 7)).toString();
            const cantidadRaw = codigo.substring(7, 12);
            
            let cantidad, peso;
            
            if (tipo === '20') {
                // Código de cantidad (unidades)
                cantidad = parseInt(cantidadRaw) / 1000;
                peso = null;
            } else if (tipo === '21') {
                // Código de peso (kilogramos)
                cantidad = null;
                peso = parseInt(cantidadRaw) / 1000;
            } else {
                throw new Error(`Tipo de código no reconocido: ${tipo}`);
            }
            
            console.log('Código procesado:', {
                tipo,
                codigoProducto,
                cantidad,
                peso,
                codigoOriginal: codigo
            });
            
            // Buscar el producto por código
            buscarProductoPorCodigo(codigoProducto, cantidad, peso);
            
        } catch (error) {
            console.error('Error procesando código de barras:', error);
            Swal.fire({
                title: 'Error',
                text: `Error al procesar el código de barras: ${error.message}`,
                icon: 'error'
            });
        }
    }

    function buscarProductoPorCodigo(codigo, cantidad, peso) {
        // Mostrar indicador de carga
        Swal.fire({
            title: 'Buscando producto...',
            allowOutsideClick: false,
            didOpen: () => {
                Swal.showLoading();
            }
        });
        
        $.get(`api/envios/productos-disponibles?codigo=${encodeURIComponent(codigo)}&cantidad=${cantidad}&peso=${peso}`)
            .done(function(response) {
                Swal.close();
                
                if (response.success && response.data && response.data.length > 0) {
                    // Producto encontrado
                    const productosCoincidentes = response.data;
                    
                    if (productosCoincidentes.length === 1) {
                        // Solo hay un producto que coincide exactamente
                        const producto = productosCoincidentes[0];
                        
                        // Verificar si ya está en la lista
                        const yaSeleccionado = productosSeleccionados.find(p => p.id_movimiento_item === producto.id_movimiento_item);
                        if (yaSeleccionado) {
                            Swal.fire({
                                title: 'Producto ya seleccionado',
                                text: `El producto ${producto.codigo} - ${producto.descripcion} ya está en la lista de envío.`,
                                icon: 'warning'
                            });
                            return;
                        }
                        
                        // Agregar automáticamente el producto
                        agregarProductoAEnvio(producto);
                        
                        Swal.fire({
                            title: 'Producto agregado',
                            text: `${producto.codigo} - ${producto.descripcion} agregado exitosamente.`,
                            icon: 'success',
                            timer: 2000,
                            showConfirmButton: false
                        });
                    } else {
                        // Múltiples productos coinciden, mostrar modal de selección
                        mostrarProductosDisponibles(productosCoincidentes);
                        $('#modalSeleccionProductos').modal('show');
                        
                        Swal.fire({
                            title: 'Múltiples productos encontrados',
                            text: `Se encontraron ${productosCoincidentes.length} productos que coinciden. Seleccione el producto deseado.`,
                            icon: 'info'
                        });
                    }
                } else {
                    Swal.fire({
                        title: 'Producto no encontrado',
                        text: `No se encontró ningún producto con el código ${codigo}.`,
                        icon: 'warning'
                    });
                }
            })
            .fail(function(xhr) {
                Swal.close();
                console.error('Error buscando producto:', xhr);
                Swal.fire({
                    title: 'Error',
                    text: 'Error al buscar el producto. Intente nuevamente.',
                    icon: 'error'
                });
            });
    }

    // Funciones principales
    function cargarUbicaciones() {
        $.get('api/ubicaciones')
        .done(function(response) {
            if (response.ubicaciones) {
                let opciones = '<option value="">Seleccione destino...</option>';
                response.ubicaciones.forEach(function(ubicacion) {
                    // No mostrar el depósito central (ID 1) como destino
                    if (ubicacion.id != 1) {
                        opciones += `<option value="${ubicacion.id}">${ubicacion.nombre}</option>`;
                    }
                });
                $('#selectDestino').html(opciones);
                $('#selectDestinoFiltro').html('<option value="">Todos los destinos</option>' + opciones);
            }
        })
        .fail(function(xhr) {
            console.error('Error al cargar ubicaciones:', xhr);
        });
    }

    function cargarEnvios() {
        let filtros = {
            fechaDesde: $('#fechaDesde').val(),
            fechaHasta: $('#fechaHasta').val(),
            destino: $('#selectDestinoFiltro').val(),
            estado: $('#selectEstado').val()
        };

        let url = 'api/envios';
        let params = [];
        
        Object.keys(filtros).forEach(key => {
            if (filtros[key]) {
                params.push(`${key}=${encodeURIComponent(filtros[key])}`);
            }
        });
        
        if (params.length > 0) {
            url += '?' + params.join('&');
        }

        $.get(url)
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
                <tr>
                    <td>${producto.codigo}</td>
                    <td>${producto.descripcion}</td>
                    <td>${producto.cnt}</td>
                    <td>${producto.cnt_peso} kg</td>
                    <td>${producto.contenedor || '-'}</td>
                    <td>
                        <button class="btn btn-sm btn-primary" onclick="agregarProductoAEnvio(${JSON.stringify(producto).replace(/"/g, '&quot;')})">
                            <i class="fas fa-plus"></i> Agregar
                        </button>
                    </td>
                </tr>
            `;
        });
        $('#productosDisponiblesTable').html(html);
    }

    function mostrarProductosEnvio() {
        let html = '';
        productosSeleccionados.forEach(function(producto, index) {
            html += `
                <tr>
                    <td>${producto.codigo}</td>
                    <td>${producto.descripcion}</td>
                    <td>${producto.contenedor || '-'}</td>
                    <td>
                        <input type="number" step="0.001" min="0" max="${producto.cnt_disponible}" 
                               value="${producto.cnt}" class="form-control form-control-sm" 
                               onchange="actualizarCantidadProducto(${index}, this.value)">
                        <small>Disponible: ${producto.cnt_disponible}</small>
                    </td>
                    <td>
                        <input type="number" step="0.001" min="0" max="${producto.peso_disponible}" 
                               value="${producto.cnt_peso}" class="form-control form-control-sm" 
                               onchange="actualizarPesoProducto(${index}, this.value)">
                        <small>Disponible: ${producto.peso_disponible} kg</small>
                    </td>
                    <td>${producto.peso_neto.toFixed(3)} kg</td>
                    <td>
                        <button class="btn btn-sm btn-danger" onclick="quitarProductoDeEnvio(${index})">
                            <i class="fas fa-trash"></i>
                        </button>
                    </td>
                </tr>
            `;
        });
        $('#productosEnvioTable').html(html);
    }

    // Funciones de gestión de productos
    window.agregarProductoAEnvio = function(producto) {
        // Verificar si el producto ya está seleccionado
        const yaSeleccionado = productosSeleccionados.find(p => p.id_movimiento_item === producto.id_movimiento_item);
        if (yaSeleccionado) {
            Swal.fire({
                title: 'Producto ya seleccionado',
                text: 'Este producto ya está en la lista de envío.',
                icon: 'warning'
            });
            return;
        }

        // Agregar producto con valores por defecto
        producto.cnt_disponible = parseFloat(producto.cnt);
        producto.peso_disponible = parseFloat(producto.cnt_peso);
        producto.peso_contenedor = parseFloat(producto.peso_contenedor) || 0;
        producto.peso_neto = producto.peso_disponible - producto.peso_contenedor;
        producto.cnt = producto.cnt_disponible;
        producto.cnt_peso = producto.peso_disponible;

        productosSeleccionados.push(producto);
        mostrarProductosEnvio();
        mostrarProductosDisponibles([]); // Actualizar tabla para ocultar producto seleccionado
        $('#modalSeleccionProductos').modal('hide');
    };

    window.quitarProductoDeEnvio = function(index) {
        productosSeleccionados.splice(index, 1);
        mostrarProductosEnvio();
    };

    window.actualizarCantidadProducto = function(index, nuevaCantidad) {
        const producto = productosSeleccionados[index];
        nuevaCantidad = parseFloat(nuevaCantidad) || 0;
        
        if (nuevaCantidad > producto.cnt_disponible) {
            Swal.fire({
                title: 'Cantidad no disponible',
                text: `Solo hay ${producto.cnt_disponible} unidades disponibles.`,
                icon: 'warning'
            });
            nuevaCantidad = producto.cnt_disponible;
        }
        
        producto.cnt = nuevaCantidad;
        mostrarProductosEnvio();
    };

    window.actualizarPesoProducto = function(index, nuevoPeso) {
        const producto = productosSeleccionados[index];
        nuevoPeso = parseFloat(nuevoPeso) || 0;
        
        if (nuevoPeso > producto.peso_disponible) {
            Swal.fire({
                title: 'Peso no disponible',
                text: `Solo hay ${producto.peso_disponible} kg disponibles.`,
                icon: 'warning'
            });
            nuevoPeso = producto.peso_disponible;
        }
        
        producto.cnt_peso = nuevoPeso;
        producto.peso_neto = nuevoPeso - producto.peso_contenedor;
        mostrarProductosEnvio();
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
                    title: 'Envío creado',
                    text: 'El envío se ha creado exitosamente.'
                }).then(() => {
                    $('#modalNuevoEnvio').modal('hide');
                    cargarEnvios();
                    limpiarFormulario();
                });
            } else {
                mostrarError(response.error || 'Error al crear el envío');
            }
        })
        .fail(function(xhr) {
            console.error('Error:', xhr);
            mostrarError('Error al guardar el envío: ' + (xhr.responseJSON?.error || xhr.statusText));
        });
    }

    function validarEnvio() {
        if (!$('#selectDestino').val()) {
            Swal.fire({
                title: 'Error de validación',
                text: 'Debe seleccionar un destino.',
                icon: 'error'
            });
            return false;
        }

        if (productosSeleccionados.length === 0) {
            Swal.fire({
                title: 'Error de validación',
                text: 'Debe agregar al menos un producto al envío.',
                icon: 'error'
            });
            return false;
        }

        return true;
    }

    function limpiarFormulario() {
        $('#selectDestino').val('');
        productosSeleccionados = [];
        mostrarProductosEnvio();
    }

    function exportarLista(formato) {
        window.open(`api/envios/${formato}`, '_blank');
    }

    function exportarDetalle(id, formato) {
        window.open(`api/envios/${id}/${formato}`, '_blank');
    }

    // Funciones de gestión de envíos
    window.verDetalleEnvio = function(id) {
        console.log('verDetalleEnvio called with id:', id);
        
        // Mostrar loading
        Swal.fire({
            title: 'Cargando detalle...',
            text: 'Por favor espere',
            allowOutsideClick: false,
            showConfirmButton: false,
            willOpen: () => {
                Swal.showLoading();
            }
        });

        $.get(`api/envios/${id}`)
        .done(function(response) {
            console.log('Response received:', response);
            Swal.close();
            
            if (response.success) {
                mostrarDetalleEnvio(response.data);
                $('#modalDetalleEnvio').modal('show');
            } else {
                mostrarError(response.error || 'Error al cargar el detalle del envío');
            }
        })
        .fail(function(xhr) {
            console.error('Error in verDetalleEnvio:', xhr);
            Swal.close();
            mostrarError('Error al cargar el detalle del envío: ' + (xhr.responseJSON?.error || xhr.statusText));
        });
    };

    function mostrarDetalleEnvio(data) {
        console.log('mostrarDetalleEnvio called with data:', data);
        
        let envio = data.envio;
        let productos = data.productos;

        // Rellenar información del envío
        $('#detalleEnvioFecha').text(envio.fechaAlta || envio.fecha_alta);
        $('#detalleEnvioDestino').text(envio.destino);
        
        // Manejar estado del envío
        const estado = envio.ultimo_estado || 'NUEVO';
        $('#detalleEnvioEstado').html(`<span class="badge badge-${getBadgeClass(estado)}">${estado}</span>`);
        $('#detalleEnvioUsuario').text(envio.usuario_alta || 'Sistema');

        // Establecer ID del envío seleccionado para los botones
        window.envioSeleccionadoId = envio.id;
        
        // Mostrar/ocultar botones según el estado
        actualizarBotonesConfirmacion(estado);

        // Calcular totales
        let totalCantidad = 0;
        let totalPesoBruto = 0;
        let totalPesoNeto = 0;

        let productosHtml = '';
        productos.forEach(function(producto) {
            // Convertir a números para cálculos
            const cantidad = parseFloat(producto.cnt) || 0;
            const pesoBruto = parseFloat(producto.cnt_peso) || 0;
            const pesoContenedor = parseFloat(producto.peso_contenedor) || 0;
            // Si no hay contenedor (peso_contenedor es null), el peso neto = peso bruto
            const pesoNeto = producto.peso_contenedor !== null ? (pesoBruto - pesoContenedor) : pesoBruto;
            
            totalCantidad += cantidad;
            totalPesoBruto += pesoBruto;
            totalPesoNeto += pesoNeto;

            productosHtml += `
                <tr>
                    <td>${producto.codigo}</td>
                    <td>${producto.descripcion}</td>
                    <td>${producto.contenedor || '-'}</td>
                    <td>${cantidad.toFixed(3)}</td>
                    <td>${pesoBruto.toFixed(3)} kg</td>
                    <td>${pesoNeto.toFixed(3)} kg</td>
                </tr>
            `;
        });

        $('#detalleEnvioProductosTable').html(productosHtml);
        
        // Mostrar totales
        $('#detalleTotalCantidad').text(totalCantidad.toFixed(3));
        $('#detalleTotalPesoBruto').text(totalPesoBruto.toFixed(3) + ' kg');
        $('#detalleTotalPesoNeto').text(totalPesoNeto.toFixed(3) + ' kg');
    }

    // Función para mostrar/ocultar botones según el estado del envío
    function actualizarBotonesConfirmacion(estado) {
        const btnConfirmar = $('#btnConfirmarEnvio');
        const btnCancelar = $('#btnCancelarEnvio');
        
        if (estado === 'NUEVO') {
            btnConfirmar.show();
            btnCancelar.show();
        } else {
            btnConfirmar.hide();
            btnCancelar.hide();
        }
    }

    // Event listeners para los botones de confirmación
    $('#btnConfirmarEnvio').click(function() {
        confirmarEnvio();
    });

    $('#btnCancelarEnvio').click(function() {
        cancelarEnvio();
    });

    function confirmarEnvio() {
        if (!window.envioSeleccionadoId) {
            mostrarError('No hay envío seleccionado');
            return;
        }

        Swal.fire({
            title: '¿Confirmar envío?',
            text: 'Esta acción marcará el envío como enviado y no se podrá deshacer.',
            icon: 'question',
            showCancelButton: true,
            confirmButtonText: 'Sí, confirmar',
            cancelButtonText: 'Cancelar'
        }).then((result) => {
            if (result.isConfirmed) {
                $.ajax({
                    url: `api/envios/${window.envioSeleccionadoId}/confirmar`,
                    method: 'POST'
                })
                .done(function(response) {
                    if (response.success) {
                        Swal.fire({
                            title: 'Envío confirmado',
                            text: 'El envío ha sido marcado como enviado.',
                            icon: 'success'
                        }).then(() => {
                            $('#modalDetalleEnvio').modal('hide');
                            cargarEnvios();
                        });
                    } else {
                        mostrarError(response.error || 'Error al confirmar el envío');
                    }
                })
                .fail(function(xhr) {
                    mostrarError('Error al confirmar el envío: ' + (xhr.responseJSON?.error || xhr.statusText));
                });
            }
        });
    }

    function cancelarEnvio() {
        if (!window.envioSeleccionadoId) {
            mostrarError('No hay envío seleccionado');
            return;
        }

        Swal.fire({
            title: '¿Cancelar envío?',
            text: 'Esta acción cancelará el envío y devolverá los productos al stock.',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: 'Sí, cancelar',
            cancelButtonText: 'No cancelar',
            confirmButtonColor: '#d33'
        }).then((result) => {
            if (result.isConfirmed) {
                $.ajax({
                    url: `api/envios/${window.envioSeleccionadoId}/cancelar`,
                    method: 'POST'
                })
                .done(function(response) {
                    if (response.success) {
                        Swal.fire({
                            title: 'Envío cancelado',
                            text: 'El envío ha sido cancelado y los productos devueltos al stock.',
                            icon: 'success'
                        }).then(() => {
                            $('#modalDetalleEnvio').modal('hide');
                            cargarEnvios();
                        });
                    } else {
                        mostrarError(response.error || 'Error al cancelar el envío');
                    }
                })
                .fail(function(xhr) {
                    mostrarError('Error al cancelar el envío: ' + (xhr.responseJSON?.error || xhr.statusText));
                });
            }
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