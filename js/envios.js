$(document).ready(function() {
    // Variables globales
    let productosSeleccionados = [];
    let tablaProductos = null;
    let html5QrcodeScanner = null;

    // InicializaciÃ³n
    cargarUbicaciones();
    cargarEnvios();

    // Event Listeners
    $('#btnNuevoEnvio').click(function() {
        $('#modalNuevoEnvio').modal('show');
    });

    $('#btnAgregarProducto').click(function() {
        // Limpiar bÃºsqueda anterior
        $('#buscarProducto').val('');
        $('#productosDisponiblesTable').empty();
        
        $('#modalSeleccionProductos').modal('show');
        
        // Focus en el input despuÃ©s de que se muestre el modal
        $('#modalSeleccionProductos').on('shown.bs.modal', function () {
            $('#buscarProducto').focus();
            $(this).off('shown.bs.modal'); // Remover el evento para no acumularlo
        });
    });

    $('#btnEscanearCodigo').click(function() {
        iniciarEscaneoCodigoBarras();
    });

    // Modal events para limpiar el escÃ¡ner
    $('#modalEscaneo').on('hidden.bs.modal', function () {
        if (html5QrcodeScanner) {
            html5QrcodeScanner.clear().catch(error => {
                console.error("Error al limpiar el escÃ¡ner:", error);
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

    // NUEVA FUNCIONALIDAD: BÃºsqueda mejorada con soporte para cÃ³digos de barras
    $('#buscarProducto').on('keyup', function() {
        const texto = $(this).val().trim();
        
        if (texto.length === 0) {
            // Si no hay texto, limpiar la tabla
            $('#productosDisponiblesTable').empty();
            return;
        }
        
        // Verificar si es un cÃ³digo de barras
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

    // NUEVA FUNCIÃ“N: Buscar productos desde el servidor con filtro
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
    
    // NUEVA FUNCIÃ“N: Procesar cÃ³digos de barras en la selecciÃ³n de productos
    function procesarCodigoBarrasEnSeleccion(codigo) {
        try {
            const tipo = codigo.substring(0, 2);
            const codigoProducto = parseInt(codigo.substring(2, 7)).toString();
            const cantidadRaw = codigo.substring(7, 12);
            
            let cantidad, peso;
            
            if (tipo === '20') {
                // CÃ³digo de cantidad (unidades) - valor directo, no se divide por 1000
                cantidad = parseInt(cantidadRaw);
                peso = null;
            } else if (tipo === '21') {
                // CÃ³digo de peso (kilogramos) - se divide por 1000 para convertir gramos a kg
                cantidad = null;
                peso = parseInt(cantidadRaw) / 1000; // Los 5 dÃ­gitos representan peso * 1000
            } else {
                throw new Error(`Tipo de cÃ³digo no reconocido: ${tipo}`);
            }
            
            console.log('CÃ³digo de barras procesado en selecciÃ³n:', {
                tipo,
                codigoProducto,
                cantidad,
                peso,
                codigoOriginal: codigo
            });
            
            // Buscar productos que coincidan con el cÃ³digo
            buscarYSeleccionarProductoPorCodigo(codigoProducto, cantidad, peso);
            
        } catch (error) {
            console.error('Error procesando cÃ³digo de barras:', error);
            Swal.fire({
                title: 'Error',
                text: `Error al procesar el cÃ³digo de barras: ${error.message}`,
                icon: 'error'
            });
        }
    }
    
    // NUEVA FUNCIÃ“N: Buscar y seleccionar automÃ¡ticamente productos por cÃ³digo de barras
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
        
        // Para productos por peso, filtrar por peso exacto
        if (peso !== null) {
            url += `&peso=${peso}`;
        }
        
        // Para productos por unidades, NO filtrar por cantidad - buscar cualquier cantidad disponible
        // Solo usar el cÃ³digo del producto para encontrar stock disponible
        
        console.log('URL de bÃºsqueda:', url);
        
        $.get(url)
        .done(function(response) {
            Swal.close();
            
            if (response.success && response.data && response.data.length > 0) {
                const productosCoincidentes = response.data;
                
                if (productosCoincidentes.length === 1) {
                    // Solo hay un producto que coincide exactamente
                    const producto = productosCoincidentes[0];
                    
                    // Verificar si ya estÃ¡ en la lista
                    const yaSeleccionado = productosSeleccionados.find(p => p.id_movimiento_item === producto.id_movimiento_item);
                    if (yaSeleccionado) {
                        Swal.fire({
                            title: 'Producto ya seleccionado',
                            text: `El producto ${producto.codigo} - ${producto.descripcion} ya estÃ¡ en la lista de envÃ­o.`,
                            icon: 'warning'
                        });
                        return;
                    }
                    
                    // Agregar automÃ¡ticamente
                    agregarProductoAEnvio(producto);
                    $('#modalSeleccionProductos').modal('hide');
                    $('#buscarProducto').val('');
                    
                    Swal.fire({
                        title: 'Producto agregado',
                        text: `${producto.codigo} - ${producto.descripcion} agregado al envÃ­o.`,
                        icon: 'success',
                        timer: 2000,
                        showConfirmButton: false
                    });
                } else {
                    // MÃºltiples productos coinciden, mostrar para selecciÃ³n manual
                    mostrarProductosDisponibles(productosCoincidentes);
                    Swal.fire({
                        title: 'MÃºltiples productos encontrados',
                        text: `Se encontraron ${productosCoincidentes.length} productos que coinciden. Seleccione el producto deseado.`,
                        icon: 'info'
                    });
                }
            } else {
                console.log('Respuesta del servidor:', response);
                Swal.fire({
                    title: 'Producto no encontrado',
                    html: `
                        <p>No se encontrÃ³ stock disponible para:</p>
                        <strong>CÃ³digo: ${codigo}</strong><br>
                        ${peso ? `<strong>Peso: ${peso.toFixed(3)} kg</strong><br>` : ''}
                        ${cantidad ? `<strong>Cantidad: ${cantidad} unidades</strong><br>` : ''}
                        <br>
                        <small>Verifique que el producto estÃ© en estado NUEVO en el depÃ³sito central.</small>
                    `,
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

    // Funciones de escaneo de cÃ³digo de barras
    function iniciarEscaneoCodigoBarras() {
        $('#modalEscaneo').modal('show');
        
        // ConfiguraciÃ³n del escÃ¡ner optimizada para cÃ³digos de barras
        const config = {
            fps: 10,
            qrbox: { width: 400, height: 150 }, // MÃ¡s ancho para cÃ³digos de barras
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
        console.log(`CÃ³digo escaneado: ${decodedText}`);
        
        // Cerrar el modal primero
        $('#modalEscaneo').modal('hide');
        
        // Procesar el cÃ³digo de barras
        procesarCodigoBarras(decodedText);
    }

    function onScanFailure(error) {
        // No hacer nada, simplemente continuar escaneando
    }

    function procesarCodigoBarras(codigo) {
        try {
            // Validar que sea un cÃ³digo de 13 dÃ­gitos
            if (!/^\d{13}$/.test(codigo)) {
                throw new Error("El cÃ³digo debe tener exactamente 13 dÃ­gitos numÃ©ricos");
            }
            
            const tipo = codigo.substring(0, 2);
            const codigoProducto = parseInt(codigo.substring(2, 7)).toString();
            const cantidadRaw = codigo.substring(7, 12);
            
            let cantidad, peso;
            
            if (tipo === '20') {
                // CÃ³digo de cantidad (unidades) - valor directo
                cantidad = parseInt(cantidadRaw);
                peso = null;
            } else if (tipo === '21') {
                // CÃ³digo de peso (kilogramos) - dividir por 1000 para convertir gramos a kg
                cantidad = null;
                peso = parseInt(cantidadRaw) / 1000;
            } else {
                throw new Error(`Tipo de cÃ³digo no reconocido: ${tipo}`);
            }
            
            console.log('CÃ³digo procesado:', {
                tipo,
                codigoProducto,
                cantidad,
                peso,
                codigoOriginal: codigo
            });
            
            // Buscar el producto por cÃ³digo
            buscarProductoPorCodigo(codigoProducto, cantidad, peso);
            
        } catch (error) {
            console.error('Error procesando cÃ³digo de barras:', error);
            Swal.fire({
                title: 'Error',
                text: `Error al procesar el cÃ³digo de barras: ${error.message}`,
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
        
        let url = `api/envios/productos-disponibles?codigo=${encodeURIComponent(codigo)}`;
        
        // Para productos por peso, filtrar por peso exacto
        if (peso !== null) {
            url += `&peso=${peso}`;
        }
        
        // Para productos por unidades, NO filtrar por cantidad - buscar cualquier cantidad disponible
        console.log('URL de bÃºsqueda (segunda funciÃ³n):', url);
        
        $.get(url)
            .done(function(response) {
                Swal.close();
                
                if (response.success && response.data && response.data.length > 0) {
                    // Producto encontrado
                    const productosCoincidentes = response.data;
                    
                    if (productosCoincidentes.length === 1) {
                        // Solo hay un producto que coincide exactamente
                        const producto = productosCoincidentes[0];
                        
                        // Verificar si ya estÃ¡ en la lista
                        const yaSeleccionado = productosSeleccionados.find(p => p.id_movimiento_item === producto.id_movimiento_item);
                        if (yaSeleccionado) {
                            Swal.fire({
                                title: 'Producto ya seleccionado',
                                text: `El producto ${producto.codigo} - ${producto.descripcion} ya estÃ¡ en la lista de envÃ­o.`,
                                icon: 'warning'
                            });
                            return;
                        }
                        
                        // Agregar automÃ¡ticamente el producto
                        agregarProductoAEnvio(producto);
                        
                        Swal.fire({
                            title: 'Producto agregado',
                            text: `${producto.codigo} - ${producto.descripcion} agregado exitosamente.`,
                            icon: 'success',
                            timer: 2000,
                            showConfirmButton: false
                        });
                    } else {
                        // MÃºltiples productos coinciden, mostrar modal de selecciÃ³n
                        mostrarProductosDisponibles(productosCoincidentes);
                        $('#modalSeleccionProductos').modal('show');
                        
                        Swal.fire({
                            title: 'MÃºltiples productos encontrados',
                            text: `Se encontraron ${productosCoincidentes.length} productos que coinciden. Seleccione el producto deseado.`,
                            icon: 'info'
                        });
                    }
                } else {
                    Swal.fire({
                        title: 'Producto no encontrado',
                        text: `No se encontrÃ³ ningÃºn producto con el cÃ³digo ${codigo}.`,
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
                    // No mostrar el depÃ³sito central (ID 1) como destino
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
            mostrarError('Error al cargar envÃ­os');
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

    // Funciones de visualizaciÃ³n
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
                        <button class="btn btn-sm btn-warning" onclick="event.stopPropagation(); exportarRemitoPreimpreso(${envio.id})" title="Remito Preimpreso">
                            <i class="fas fa-print"></i>
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

            // Calcular cantidad disponible (si no viene del backend, usar cnt)
            const cantidadDisponible = producto.cnt_disponible !== undefined ? producto.cnt_disponible : producto.cnt;

            html += `
                <tr>
                    <td>${producto.codigo}</td>
                    <td>${producto.descripcion}</td>
                    <td class="text-center">
                        <strong>${cantidadDisponible}</strong>
                        ${producto.cnt !== cantidadDisponible ? `<br><small class="text-muted">(Original: ${producto.cnt})</small>` : ''}
                    </td>
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
                        <input type="number" step="0.001" min="1" max="${producto.cnt_disponible}" 
                               value="${producto.cnt}" class="form-control form-control-sm" 
                               onchange="actualizarCantidadProducto(${index}, this.value)">
                        <small class="text-muted">Disponible: <strong>${producto.cnt_disponible}</strong></small>
                    </td>
                    <td>
                        <input type="number" step="0.001" min="0" max="${producto.peso_disponible}" 
                               value="${producto.cnt_peso.toFixed(3)}" class="form-control form-control-sm" 
                               onchange="actualizarPesoProducto(${index}, this.value)" readonly>
                        <small class="text-muted">Auto-calculado</small>
                    </td>
                    <td class="text-right">${producto.peso_neto.toFixed(3)} kg</td>
                    <td class="text-center">
                        <button class="btn btn-sm btn-danger" onclick="quitarProductoDeEnvio(${index})">
                            <i class="fas fa-trash"></i>
                        </button>
                    </td>
                </tr>
            `;
        });
        $('#productosEnvioTable').html(html);
    }

    // Funciones de gestiÃ³n de productos
    window.agregarProductoAEnvio = function(producto) {
        // Verificar si el producto ya estÃ¡ seleccionado
        const yaSeleccionado = productosSeleccionados.find(p => p.id_movimiento_item === producto.id_movimiento_item);
        if (yaSeleccionado) {
            Swal.fire({
                title: 'Producto ya seleccionado',
                text: 'Este producto ya estÃ¡ en la lista de envÃ­o.',
                icon: 'warning'
            });
            return;
        }

        // Calcular cantidad disponible
        const cantidadDisponible = producto.cnt_disponible !== undefined ? parseFloat(producto.cnt_disponible) : parseFloat(producto.cnt);
        
        // Verificar que haya stock disponible
        if (cantidadDisponible <= 0) {
            Swal.fire({
                title: 'Sin stock disponible',
                text: 'Este producto no tiene unidades disponibles para enviar.',
                icon: 'error'
            });
            return;
        }

        // Agregar producto con cantidad inicial de 1
        producto.cnt_disponible = cantidadDisponible;
        producto.peso_disponible = parseFloat(producto.cnt_peso);
        producto.peso_contenedor = parseFloat(producto.peso_contenedor) || 0;
        
        // Establecer cantidad inicial en 1 (o la disponible si es menor)
        producto.cnt = Math.min(1, cantidadDisponible);
        
        // Calcular peso proporcional
        const pesoUnitario = producto.peso_disponible / cantidadDisponible;
        producto.cnt_peso = pesoUnitario * producto.cnt;
        producto.peso_neto = producto.cnt_peso - producto.peso_contenedor;

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
        
        // Validar cantidad mÃ­nima
        if (nuevaCantidad < 1) {
            Swal.fire({
                title: 'Cantidad invÃ¡lida',
                text: 'La cantidad mÃ­nima es 1 unidad.',
                icon: 'warning'
            });
            nuevaCantidad = 1;
        }
        
        // Validar cantidad mÃ¡xima
        if (nuevaCantidad > producto.cnt_disponible) {
            Swal.fire({
                title: 'Cantidad no disponible',
                text: `Solo hay ${producto.cnt_disponible} unidades disponibles.`,
                icon: 'warning'
            });
            nuevaCantidad = producto.cnt_disponible;
        }
        
        // Actualizar cantidad
        producto.cnt = nuevaCantidad;
        
        // Recalcular peso proporcionalmente
        const pesoUnitario = producto.peso_disponible / producto.cnt_disponible;
        producto.cnt_peso = pesoUnitario * nuevaCantidad;
        producto.peso_neto = producto.cnt_peso - producto.peso_contenedor;
        
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

    // Funciones de guardado y exportaciÃ³n
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
                    title: 'EnvÃ­o creado',
                    text: 'El envÃ­o se ha creado exitosamente.'
                }).then(() => {
                    $('#modalNuevoEnvio').modal('hide');
                    cargarEnvios();
                    limpiarFormulario();
                });
            } else {
                mostrarError(response.error || 'Error al crear el envÃ­o');
            }
        })
        .fail(function(xhr) {
            console.error('Error:', xhr);
            mostrarError('Error al guardar el envÃ­o: ' + (xhr.responseJSON?.error || xhr.statusText));
        });
    }

    function validarEnvio() {
        if (!$('#selectDestino').val()) {
            Swal.fire({
                title: 'Error de validaciÃ³n',
                text: 'Debe seleccionar un destino.',
                icon: 'error'
            });
            return false;
        }

        if (productosSeleccionados.length === 0) {
            Swal.fire({
                title: 'Error de validaciÃ³n',
                text: 'Debe agregar al menos un producto al envÃ­o.',
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
        // Mostrar loading
        Swal.fire({
            title: `Generando ${formato.toUpperCase()}...`,
            text: 'Por favor espere mientras se genera el archivo',
            allowOutsideClick: false,
            didOpen: () => {
                Swal.showLoading();
            }
        });

        fetch(`api/envios/${formato}`)
            .then(response => {
                if (response.ok) {
                    return response.json();
                }
                throw new Error('Error en la respuesta del servidor');
            })
            .then(data => {
                Swal.close();
                if (data.success) {
                    // Descarga automÃ¡tica
                    const link = document.createElement('a');
                    link.href = data.archivo;
                    link.download = data.archivo.split('/').pop();
                    document.body.appendChild(link);
                    link.click();
                    document.body.removeChild(link);
                    
                    mostrarExito(`${formato.toUpperCase()} descargado exitosamente`);
                } else {
                    throw new Error(data.error || `Error al generar ${formato.toUpperCase()}`);
                }
            })
            .catch(error => {
                Swal.close();
                console.error('Error:', error);
                mostrarError(`Error al generar ${formato.toUpperCase()}: ` + error.message);
            });
    }

    function exportarDetalle(id, formato) {
        // Mostrar loading
        Swal.fire({
            title: `Generando ${formato.toUpperCase()}...`,
            text: 'Por favor espere mientras se genera el archivo',
            allowOutsideClick: false,
            didOpen: () => {
                Swal.showLoading();
            }
        });

        fetch(`api/envios/${id}/${formato}`)
            .then(response => {
                if (response.ok) {
                    return response.json();
                }
                throw new Error('Error en la respuesta del servidor');
            })
            .then(data => {
                Swal.close();
                if (data.success) {
                    // Descarga automÃ¡tica
                    const link = document.createElement('a');
                    link.href = data.archivo;
                    link.download = data.archivo.split('/').pop();
                    document.body.appendChild(link);
                    link.click();
                    document.body.removeChild(link);
                    
                    mostrarExito(`${formato.toUpperCase()} descargado exitosamente`);
                } else {
                    throw new Error(data.error || `Error al generar ${formato.toUpperCase()}`);
                }
            })
            .catch(error => {
                Swal.close();
                console.error('Error:', error);
                mostrarError(`Error al generar ${formato.toUpperCase()}: ` + error.message);
            });
    }

    // Exponer la funciÃ³n exportarDetalle globalmente para los botones HTML
    window.exportarDetalle = exportarDetalle;

    // Funciones de gestiÃ³n de envÃ­os
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
                mostrarError(response.error || 'Error al cargar el detalle del envÃ­o');
            }
        })
        .fail(function(xhr) {
            console.error('Error in verDetalleEnvio:', xhr);
            Swal.close();
            mostrarError('Error al cargar el detalle del envÃ­o: ' + (xhr.responseJSON?.error || xhr.statusText));
        });
    };

    function mostrarDetalleEnvio(data) {
        console.log('mostrarDetalleEnvio called with data:', data);
        
        let envio = data.envio;
        let productos = data.productos;

        // Rellenar informaciÃ³n del envÃ­o
        $('#detalleEnvioFecha').text(envio.fechaAlta || envio.fecha_alta);
        $('#detalleEnvioDestino').text(envio.destino);
        
        // Manejar estado del envÃ­o
        const estado = envio.ultimo_estado || 'NUEVO';
        $('#detalleEnvioEstado').html(`<span class="badge badge-${getBadgeClass(estado)}">${estado}</span>`);
        $('#detalleEnvioUsuario').text(envio.usuario_alta || 'Sistema');

        // Establecer ID del envÃ­o seleccionado para los botones
        window.envioSeleccionadoId = envio.id;
        
        // Mostrar/ocultar botones segÃºn el estado
        actualizarBotonesConfirmacion(estado);

        // Calcular totales
        let totalCantidad = 0;
        let totalPesoBruto = 0;
        let totalPesoNeto = 0;

        let productosHtml = '';
        productos.forEach(function(producto) {
            // Convertir a nÃºmeros para cÃ¡lculos
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

    // FunciÃ³n para mostrar/ocultar botones segÃºn el estado del envÃ­o
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

    // Event listeners para los botones de confirmaciÃ³n
    $('#btnConfirmarEnvio').click(function() {
        confirmarEnvio();
    });

    $('#btnCancelarEnvio').click(function() {
        cancelarEnvio();
    });

    $('#btnImprimirDetalle').click(function() {
        imprimirDetalle();
    });

    $('#btnRemitoPreimpresoModal').click(function() {
        if (window.envioSeleccionadoId) {
            exportarRemitoPreimpreso(window.envioSeleccionadoId);
        } else {
            mostrarError('No hay envio seleccionado');
        }
    });

    function confirmarEnvio() {
        if (!window.envioSeleccionadoId) {
            mostrarError('No hay envÃ­o seleccionado');
            return;
        }

        Swal.fire({
            title: 'Â¿Confirmar envÃ­o?',
            text: 'Esta acciÃ³n marcarÃ¡ el envÃ­o como enviado y no se podrÃ¡ deshacer.',
            icon: 'question',
            showCancelButton: true,
            confirmButtonText: 'SÃ­, confirmar',
            cancelButtonText: 'Cancelar'
        }).then((result) => {
            if (result.isConfirmed) {
                $.ajax({
                    url: `api/envios/${window.envioSeleccionadoId}/confirmar`,
                    method: 'PUT'
                })
                .done(function(response) {
                    if (response.success) {
                        Swal.fire({
                            title: 'EnvÃ­o confirmado',
                            text: 'El envÃ­o ha sido marcado como enviado.',
                            icon: 'success'
                        }).then(() => {
                            $('#modalDetalleEnvio').modal('hide');
                            cargarEnvios();
                        });
                    } else {
                        mostrarError(response.error || 'Error al confirmar el envÃ­o');
                    }
                })
                .fail(function(xhr) {
                    mostrarError('Error al confirmar el envÃ­o: ' + (xhr.responseJSON?.error || xhr.statusText));
                });
            }
        });
    }

    function cancelarEnvio() {
        if (!window.envioSeleccionadoId) {
            mostrarError('No hay envÃ­o seleccionado');
            return;
        }

        Swal.fire({
            title: 'Â¿Cancelar envÃ­o?',
            text: 'Esta acciÃ³n cancelarÃ¡ el envÃ­o y devolverÃ¡ los productos al stock.',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: 'SÃ­, cancelar',
            cancelButtonText: 'No cancelar',
            confirmButtonColor: '#d33'
        }).then((result) => {
            if (result.isConfirmed) {
                $.ajax({
                    url: `api/envios/${window.envioSeleccionadoId}/cancelar`,
                    method: 'PUT',
                    data: JSON.stringify({ motivo: 'Cancelado por usuario' }),
                    contentType: 'application/json'
                })
                .done(function(response) {
                    if (response.success) {
                        Swal.fire({
                            title: 'EnvÃ­o cancelado',
                            text: 'El envÃ­o ha sido cancelado y los productos devueltos al stock.',
                            icon: 'success'
                        }).then(() => {
                            $('#modalDetalleEnvio').modal('hide');
                            cargarEnvios();
                        });
                    } else {
                        mostrarError(response.error || 'Error al cancelar el envÃ­o');
                    }
                })
                .fail(function(xhr) {
                    mostrarError('Error al cancelar el envÃ­o: ' + (xhr.responseJSON?.error || xhr.statusText));
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

    function imprimirDetalle() {
        if (!window.envioSeleccionadoId) {
            mostrarError('No hay envÃ­o seleccionado');
            return;
        }

        Swal.fire({
            title: 'Generando PDF...',
            text: 'Por favor espere mientras se genera el documento',
            allowOutsideClick: false,
            didOpen: () => {
                Swal.showLoading();
            }
        });

        // Generar PDF del envÃ­o especÃ­fico
        $.ajax({
            url: `api/envios/${window.envioSeleccionadoId}/pdf`,
            method: 'GET'
        })
        .done(function(response) {
            Swal.close();
            if (response.success) {
                // Abrir el PDF en una nueva ventana para imprimir
                const pdfWindow = window.open(response.url, '_blank');
                
                // Intentar imprimir automÃ¡ticamente cuando se cargue el PDF
                pdfWindow.onload = function() {
                    setTimeout(() => {
                        pdfWindow.print();
                    }, 1000);
                };

                Swal.fire({
                    title: 'PDF Generado',
                    text: 'El documento se ha abierto en una nueva ventana. Puede imprimirlo desde allÃ­.',
                    icon: 'success',
                    timer: 3000,
                    showConfirmButton: false
                });
            } else {
                mostrarError(response.error || 'Error al generar el PDF');
            }
        })
        .fail(function(xhr) {
            Swal.close();
            console.error('Error al generar PDF:', xhr);
            mostrarError('Error al generar el PDF: ' + (xhr.responseJSON?.error || xhr.statusText));
        });
    }

    function mostrarExito(mensaje) {
        Swal.fire({
            icon: 'success',
            title: 'Ã‰xito',
            text: mensaje,
            timer: 3000
        });
    }

    function mostrarError(mensaje) {
        Swal.fire({
            icon: 'error',
            title: 'Error',
            text: mensaje
        });
    }
});

    // Exportar remito preimpreso (formato STARK IND)
    function exportarRemitoPreimpreso(idEnvio) {
        Swal.fire({
            title: 'Generando remito preimpreso...',
            text: 'Por favor espere',
            allowOutsideClick: false,
            didOpen: () => {
                Swal.showLoading();
            }
        });

        const url = `api/envios/${idEnvio}/pdf-preimpreso`;
        
        fetch(url)
            .then(response => {
                if (!response.ok) {
                    return response.json().then(err => {
                        throw new Error(err.error || 'Error al generar el remito');
                    });
                }
                return response.blob();
            })
            .then(blob => {
                Swal.close();
                
                // Crear URL temporal para el blob
                const url = window.URL.createObjectURL(blob);
                
                // Abrir en nueva ventana para previsualizar/imprimir
                window.open(url, '_blank');
                
                // Limpiar despuÃ©s de un momento
                setTimeout(() => {
                    window.URL.revokeObjectURL(url);
                }, 100);
                
                Swal.fire({
                    title: 'Remito generado',
                    text: 'El remito se ha abierto en una nueva ventana. ImprÃ­malo sobre papel preimpreso STARK IND.',
                    icon: 'success',
                    timer: 4000,
                    showConfirmButton: true
                });
            })
            .catch(error => {
                Swal.close();
                console.error('Error al generar remito preimpreso:', error);
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: error.message || 'Error al generar el remito preimpreso'
                });
            });
    }

    // Hacer la funciÃ³n global
    window.exportarRemitoPreimpreso = exportarRemitoPreimpreso;

});
