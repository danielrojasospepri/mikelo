/**
 * Alta Depósito V2 - Optimizado para Entorno Industrial
 * Sistema automatizado con mínima interacción mouse/teclado
 * Persistencia de contenedores y validaciones avanzadas
 */

class AltaDepositoIndustrial {
    constructor() {
        // Estados del sistema
        this.estado = 'esperando'; // esperando, producto_seleccionado, guardando
        this.productoSeleccionado = null;
        this.contenedores = [];
        
        // Persistencia de sesión
        this.ultimoContenedor = localStorage.getItem('ultimoContenedor') || '';
        this.ultimoContenedorNombre = localStorage.getItem('ultimoContenedorNombre') || 'Sin contenedor';
        this.ultimoContenedorPeso = parseFloat(localStorage.getItem('ultimoContenedorPeso') || '0');
        
        // Estadísticas de sesión
        this.estadisticas = {
            productosRegistrados: 0,
            tiempoInicio: Date.now(),
            tiemposRegistro: []
        };
        
        // Timeouts para auto-procesamiento
        this.timeoutBusqueda = null;
        this.timeoutEstado = null;
        
        this.inicializar();
    }
    
    async inicializar() {
        console.log('🚀 Inicializando Alta Depósito Industrial v2');
        
        try {
            await this.cargarContenedores();
            this.configurarEventos();
            this.aplicarContenedorAnterior();
            this.configurarValidaciones();
            this.inicializarInterfaz();
            await this.cargarRegistrosDia();
            
            // Focus inicial
            this.enfocarCampoPrincipal();
            this.mostrarEstado('✅ Sistema listo para escanear productos o contenedores', 'success');
            
        } catch (error) {
            console.error('❌ Error inicializando sistema:', error);
            this.mostrarError('Error al inicializar el sistema');
        }
    }
    
    configurarEventos() {
        // Campo principal de entrada
        const campoBusqueda = $('#buscarProducto');
        
        // Evento principal de entrada
        campoBusqueda.on('input', (e) => {
            this.procesarEntrada(e.target.value);
        });
        
        // Auto-focus global
        $(document).on('click', (e) => {
            // Solo si no se está haciendo click en un input/button específico
            if (!$(e.target).is('input, button, select, textarea')) {
                setTimeout(() => this.enfocarCampoPrincipal(), 100);
            }
        });
        
        // Atajos de teclado globales
        $(document).on('keydown', (e) => {
            switch(e.key) {
                case 'Escape':
                    e.preventDefault();
                    this.limpiarTodo();
                    break;
                case 'F3':
                    e.preventDefault();
                    this.enfocarCampoPrincipal();
                    break;
            }
            
            // Ctrl+Enter para guardar
            if (e.ctrlKey && e.key === 'Enter' && this.productoSeleccionado) {
                e.preventDefault();
                this.guardarAutomatico();
            }
        });
        
        // Botones
        $('#limpiarTodo').on('click', () => this.limpiarTodo());
        $('#btnGuardar').on('click', () => this.guardarAutomatico());
        $('#btnCancelar').on('click', () => this.cancelarSeleccion());
        $('#btnActualizarRegistros').on('click', () => this.cargarRegistrosDia());
        $('#btnGenerarCodigosBarras').on('click', () => this.generarCodigosBarras());
        $('#btnCambiarContenedor').on('click', () => this.mostrarSelectorContenedor());
        
        // Eventos de formulario
        $('#cantidadProducto, #pesoProducto').on('input', () => this.actualizarPesoTotal());
        $('#contenedorProducto').on('change', (e) => {
            this.actualizarPesoTotal();
            this.actualizarContenedorManual(e.target.value);
        });
    }
    
    configurarValidaciones() {
        // Validación en tiempo real del peso
        $('#pesoProducto').on('input', (e) => {
            const peso = parseFloat(e.target.value) || 0;
            const pesoContenedor = this.obtenerPesoContenedorSeleccionado();
            
            // El peso leído YA incluye el contenedor, validamos que sea mayor al peso del contenedor vacío
            if (peso > 0 && peso <= pesoContenedor) {
                $(e.target).addClass('is-invalid');
                this.mostrarEstado(`⚠️ El peso total debe ser mayor al contenedor vacío (${pesoContenedor.toFixed(3)}kg)`, 'warning');
            } else {
                $(e.target).removeClass('is-invalid');
            }
        });
    }
    
    procesarEntrada(valor) {
        if (!valor || valor.length === 0) {
            this.limpiarResultados();
            return;
        }
        
        // Limpiar timeout anterior
        if (this.timeoutBusqueda) {
            clearTimeout(this.timeoutBusqueda);
        }
        
        // Detectar tipo de código
        if (this.esCodigoContenedor(valor)) {
            this.procesarContenedor(valor);
        } else if (this.esCodigoBarrasCompleto(valor)) {
            this.procesarCodigoBarras(valor);
        } else {
            // Búsqueda manual con delay
            this.timeoutBusqueda = setTimeout(() => {
                this.buscarProductosManual(valor);
            }, 800);
        }
    }
    
    esCodigoContenedor(codigo) {
        // Formato: 0000000 (sin contenedor) o 0000001, 0000002, etc.
        return /^0000000$/.test(codigo) || /^00000\d{1,2}$/.test(codigo);
    }
    
    esCodigoBarrasCompleto(codigo) {
        // Códigos tipo 20 o 21 con 13 dígitos
        return /^(20|21)\d{11}$/.test(codigo);
    }
    
    async procesarContenedor(codigo) {
        try {
            console.log('🔍 Procesando contenedor:', codigo);
            if (codigo === '0000000') {
                // Código especial para "Sin Contenedor"
                this.limpiarContenedorAnterior();
                this.limpiarCampoEntrada();
                this.mostrarEstado(`🚫 Sin contenedor seleccionado`, 'warning', 2000);
            } else {
                const idContenedor = parseInt(codigo.substring(5)); // Quitar '00000' y convertir a entero
                console.log('🆔 ID extraído:', idContenedor);
                console.log('📦 Contenedores disponibles:', this.contenedores);
                const contenedor = this.contenedores.find(c => c.id == idContenedor);
                if (contenedor) {
                    this.seleccionarContenedor(contenedor);
                    this.limpiarCampoEntrada();
                    this.mostrarEstado(`📦 Contenedor seleccionado: ${contenedor.nombre}`, 'info', 2000);
                } else {
                    this.mostrarEstado(`❌ Contenedor no encontrado: ${codigo} (ID: ${idContenedor})`, 'error');
                }
            }
        } catch (error) {
            this.seleccionarContenedor('');
            console.error('Error procesando contenedor:', error);
            this.mostrarEstado('❌ Error al procesar contenedor', 'error');
        }
    }
    
    async procesarCodigoBarras(codigo) {
        try {
            const tipo = codigo.substring(0, 2);
            const codigoProducto = parseInt(codigo.substring(2, 7)).toString();
            const valorCantidad = codigo.substring(7, 12);
            
            let cantidad = 1;
            let peso = 0;
            
            if (tipo === '20') {
                // Código por unidades
                cantidad = parseInt(valorCantidad);
                peso = 0;
            } else if (tipo === '21') {
                // Código por peso
                cantidad = 1;
                peso = parseFloat(valorCantidad) / 1000; // Convertir gramos a kilos
            }
            
            // Buscar producto
            const producto = await this.buscarProductoPorCodigo(codigoProducto);
            if (producto) {
                this.seleccionarProducto(producto, cantidad, peso);
                this.limpiarCampoEntrada();
                
                // Si ya teníamos un producto seleccionado, guardar automáticamente
                if (this.estado === 'producto_seleccionado') {
                    await this.guardarAutomatico();
                } else {
                    // Para códigos de barras, guardado automático si tiene contenedor o es sin contenedor
                    if (this.ultimoContenedor || peso === 0) {
                        await this.guardarAutomatico();
                    }
                }
            } else {
                this.mostrarEstado(`❌ Producto no encontrado: ${codigoProducto}`, 'error');
            }
            
        } catch (error) {
            console.error('Error procesando código de barras:', error);
            this.mostrarEstado('❌ Error al procesar código de barras', 'error');
        }
    }
    
    async buscarProductosManual(termino) {
        if (termino.length < 2) return;
        
        try {
            this.mostrarEstado('🔍 Buscando productos...', 'info');
            
            const response = await fetch(`api/productos/buscar?q=${encodeURIComponent(termino)}`);
            const data = await response.json();
            
            if (data.success && data.data.length > 0) {
                this.mostrarResultadosBusqueda(data.data);
                this.mostrarEstado(`📋 ${data.data.length} productos encontrados`, 'success');
            } else {
                this.limpiarResultados();
                this.mostrarEstado('❌ No se encontraron productos', 'warning');
            }
        } catch (error) {
            console.error('Error buscando productos:', error);
            this.mostrarEstado('❌ Error en búsqueda', 'error');
        }
    }
    
    async buscarProductoPorCodigo(codigo) {
        try {
            const response = await fetch(`api/productos/buscar?q=${encodeURIComponent(codigo)}`);
            const data = await response.json();
            
            if (data.success && data.data.length > 0) {
                // Buscar coincidencia exacta por código
                return data.data.find(p => p.codigo === codigo) || data.data[0];
            }
            return null;
        } catch (error) {
            console.error('Error buscando producto por código:', error);
            return null;
        }
    }
    
    seleccionarContenedor(contenedor) {
        this.ultimoContenedor = contenedor.id;
        this.ultimoContenedorNombre = contenedor.nombre;
        this.ultimoContenedorPeso = parseFloat(contenedor.peso || 0);
        
        // Persistir en localStorage
        localStorage.setItem('ultimoContenedor', this.ultimoContenedor);
        localStorage.setItem('ultimoContenedorNombre', this.ultimoContenedorNombre);
        localStorage.setItem('ultimoContenedorPeso', this.ultimoContenedorPeso);
        
        // Actualizar interfaz
        this.actualizarInterfazContenedor();
        this.actualizarPesoTotal();
    }
    
    seleccionarProducto(producto, cantidad = 1, peso = 0) {
        this.productoSeleccionado = producto;
        this.estado = 'producto_seleccionado';
        
        // Actualizar interfaz
        $('#productoSeleccionadoDisplay').val(`${producto.codigo} - ${producto.descripcion}`);
        $('#cantidadProducto').val(cantidad);
        $('#pesoProducto').val(peso.toFixed(3));
        
        // Aplicar contenedor anterior si existe
        if (this.ultimoContenedor) {
            $('#contenedorProducto').val(this.ultimoContenedor);
        }
        
        this.actualizarPesoTotal();
        this.mostrarFormularioProducto(true);
        
        this.mostrarEstado(`✅ Producto seleccionado: ${producto.descripcion}`, 'success');
    }
    
    async guardarAutomatico() {
        if (!this.productoSeleccionado) {
            this.mostrarEstado('❌ No hay producto seleccionado', 'error');
            return;
        }
        
        const tiempoInicio = Date.now();
        this.estado = 'guardando';
        
        try {
            // Validaciones
            const cantidad = parseInt($('#cantidadProducto').val()) || 1;
            const peso = parseFloat($('#pesoProducto').val()) || 0;
            const contenedorId = $('#contenedorProducto').val() || null;
            
            // Validación crítica: peso vs contenedor (peso ya incluye contenedor)
            if (peso > 0 && contenedorId) {
                const pesoContenedor = this.obtenerPesoContenedorSeleccionado();
                if (peso <= pesoContenedor) {
                    this.mostrarEstado(`❌ El peso total (${peso}kg) debe ser mayor al contenedor vacío (${pesoContenedor}kg)`, 'error');
                    this.estado = 'producto_seleccionado';
                    return;
                }
            }
            
            // Verificar duplicados antes de guardar
            this.mostrarEstado('🔍 Verificando duplicados...', 'info');
            const esUnDuplicado = await this.verificarDuplicado(
                this.productoSeleccionado.id,
                cantidad,
                peso
            );
            
            if (esUnDuplicado) {
                console.log('⚠️ Duplicado detectado, solicitando confirmación');
                const confirmacion = await this.mostrarConfirmacionDuplicado(
                    this.productoSeleccionado.descripcion,
                    peso
                );
                
                if (!confirmacion) {
                    this.estado = 'producto_seleccionado';
                    this.mostrarEstado('⚠️ Guardado cancelado por el usuario', 'warning', 3000);
                    return;
                }
                console.log('✅ Usuario confirmó guardar a pesar del duplicado');
            }
            
            this.mostrarEstado('💾 Guardando producto...', 'info');
            
            // Preparar datos
            const datos = {
                producto_id: this.productoSeleccionado.id,
                cantidad: cantidad,
                peso: peso,
                contenedor_id: contenedorId,
                ubicacion_destino: 1 // Depósito central
            };
            
            console.log('💾 Enviando datos:', datos);
            
            // Enviar al servidor
            const response = await fetch('api/movimientos/alta-deposito', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify(datos)
            });
            
            const result = await response.json();
            
            if (result.success) {
                // Éxito - mostrar feedback y preparar siguiente
                const tiempoRegistro = Date.now() - tiempoInicio;
                this.registrarEstadistica(tiempoRegistro);
                
                this.mostrarFeedbackExito(
                    this.productoSeleccionado.descripcion,
                    peso,
                    this.obtenerNombreContenedorSeleccionado()
                );
                
                await this.cargarRegistrosDia();
                this.prepararSiguienteProducto();
                
                // Focus inmediato para seguir escaneando
                setTimeout(() => this.enfocarCampoPrincipal(), 50);
                
            } else {
                throw new Error(result.error || 'Error al guardar producto');
            }
            
        } catch (error) {
            console.error('Error guardando producto:', error);
            this.mostrarEstado(`❌ Error: ${error.message}`, 'error');
            this.estado = 'producto_seleccionado';
        }
    }
    
    mostrarFeedbackExito(descripcion, peso, contenedor) {
        // Toast de éxito no invasivo
        const contenedorTexto = contenedor || 'Sin contenedor';
        const pesoTexto = peso > 0 ? `${peso.toFixed(3)}kg` : 'Por unidad';
        
        const toast = $(`
            <div class="alert alert-success alert-dismissible fade show position-fixed" 
                 style="top: 20px; right: 20px; z-index: 9999; width: 350px; box-shadow: 0 4px 12px rgba(0,0,0,0.15);">
                <strong><i class="fas fa-check-circle mr-1"></i> ¡Guardado!</strong><br>
                <small>${descripcion}</small><br>
                <small><strong>Peso:</strong> ${pesoTexto} | <strong>Contenedor:</strong> ${contenedorTexto}</small>
                <div class="progress mt-2" style="height: 3px;">
                    <div class="progress-bar bg-success" style="width: 100%; animation: countdown 10s linear;"></div>
                </div>
            </div>
        `);
        
        $('body').append(toast);
        
        // Auto-remover después de 10 segundos
        setTimeout(() => {
            toast.fadeOut(500, () => toast.remove());
        }, 10000);
    }
    
    prepararSiguienteProducto() {
        // Limpiar estado pero mantener contenedor
        this.productoSeleccionado = null;
        this.estado = 'esperando';
        
        // Limpiar interfaz
        this.mostrarFormularioProducto(false);
        this.limpiarResultados();
        this.limpiarCampoEntrada();
        
        // Mantener contenedor seleccionado
        this.actualizarInterfazContenedor();
        
        // Focus para siguiente producto (inmediato y con timeout)
        this.enfocarCampoPrincipal();
        setTimeout(() => this.enfocarCampoPrincipal(), 200);
        
        // Estado listo
        const contenedorActual = this.ultimoContenedorNombre !== 'Sin contenedor' 
            ? ` con ${this.ultimoContenedorNombre}` 
            : '';
        this.mostrarEstado(`🚀 Listo para siguiente producto${contenedorActual}`, 'success');
    }
    
    async verificarDuplicado(productoId, cantidad, peso) {
        try {
            const hoy = new Date().toISOString().split('T')[0];
            
            const response = await fetch('api/movimientos/verificar-duplicado', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    producto_id: productoId,
                    cantidad: cantidad,
                    peso: peso,
                    fecha: hoy
                })
            });
            
            const data = await response.json();
            return data.duplicado || false;
            
        } catch (error) {
            console.error('Error verificando duplicado:', error);
            // En caso de error, no bloquear el guardado
            return false;
        }
    }
    
    async mostrarConfirmacionDuplicado(descripcionProducto, peso) {
        return new Promise((resolve) => {
            Swal.fire({
                icon: 'warning',
                title: '⚠️ Posible Duplicado Detectado',
                html: `
                    <div style="text-align: left;">
                        <p><strong>Se encontró un registro similar hoy:</strong></p>
                        <ul>
                            <li><strong>Producto:</strong> ${descripcionProducto}</li>
                            <li><strong>Peso:</strong> ${peso.toFixed(3)} kg</li>
                            <li><strong>Fecha:</strong> ${new Date().toLocaleDateString()}</li>
                        </ul>
                        <hr>
                        <p style="color: #e74c3c;"><strong>¿Está seguro que desea continuar?</strong></p>
                        <p><small>Esta validación previene registros duplicados por error de doble escaneo.</small></p>
                    </div>
                `,
                showCancelButton: true,
                confirmButtonText: '<i class="fas fa-check"></i> Sí, Guardar',
                cancelButtonText: '<i class="fas fa-times"></i> Cancelar',
                confirmButtonColor: '#28a745',
                cancelButtonColor: '#dc3545',
                reverseButtons: true,
                focusCancel: true
            }).then((result) => {
                resolve(result.isConfirmed);
            });
        });
    }
    
    registrarEstadistica(tiempoRegistro) {
        this.estadisticas.productosRegistrados++;
        this.estadisticas.tiemposRegistro.push(tiempoRegistro);
        
        // Calcular tiempo promedio
        const tiempoPromedio = this.estadisticas.tiemposRegistro.reduce((a, b) => a + b, 0) / this.estadisticas.tiemposRegistro.length;
        
        // Actualizar interfaz
        $('#contadorProductos').text(this.estadisticas.productosRegistrados);
        $('#tiempoPromedio').text(`${(tiempoPromedio / 1000).toFixed(1)}s`);
    }
    
    mostrarSelectorContenedor() {
        if (this.contenedores.length === 0) {
            this.mostrarError('No hay contenedores disponibles');
            return;
        }

        // Crear opciones para el selector
        let opciones = '<option value="">Sin contenedor</option>';
        this.contenedores.forEach(contenedor => {
            const selected = contenedor.id == this.ultimoContenedor ? 'selected' : '';
            opciones += `<option value="${contenedor.id}" ${selected}>${contenedor.nombre} (${contenedor.peso}kg)</option>`;
        });

        Swal.fire({
            title: 'Seleccionar Contenedor',
            html: `
                <div class="form-group">
                    <label for="selectorContenedor">Contenedor:</label>
                    <select class="form-control" id="selectorContenedor">
                        ${opciones}
                    </select>
                </div>
            `,
            showCancelButton: true,
            confirmButtonText: 'Aplicar',
            cancelButtonText: 'Cancelar',
            preConfirm: () => {
                const contenedorId = document.getElementById('selectorContenedor').value;
                return contenedorId;
            }
        }).then((result) => {
            if (result.isConfirmed) {
                this.actualizarContenedorManual(result.value);
                // También actualizar el select del formulario
                $('#contenedorProducto').val(result.value);
            }
        });
    }
    
    // Métodos de interfaz
    mostrarEstado(mensaje, tipo = 'info', duracion = null) {
        const iconos = {
            'info': 'fas fa-info-circle',
            'success': 'fas fa-check-circle',
            'warning': 'fas fa-exclamation-triangle',
            'error': 'fas fa-times-circle'
        };
        
        const colores = {
            'info': 'alert-info',
            'success': 'alert-success',
            'warning': 'alert-warning',
            'error': 'alert-danger'
        };
        
        const estadoDiv = $('#estadoOperacion');
        estadoDiv.removeClass('alert-info alert-success alert-warning alert-danger')
                 .addClass(colores[tipo])
                 .find('#estadoTexto')
                 .html(`<i class="${iconos[tipo]} mr-2"></i>${mensaje}`);
        
        estadoDiv.show();
        
        // Auto-ocultar si se especifica duración
        if (duracion) {
            if (this.timeoutEstado) clearTimeout(this.timeoutEstado);
            this.timeoutEstado = setTimeout(() => {
                estadoDiv.fadeOut();
            }, duracion);
        }
    }
    
    mostrarFormularioProducto(mostrar) {
        if (mostrar) {
            $('#formularioProducto').slideDown();
        } else {
            $('#formularioProducto').slideUp();
        }
    }
    
    mostrarResultadosBusqueda(productos) {
        const tabla = $('#tablaResultados');
        tabla.empty();
        
        productos.forEach(producto => {
            const fila = $(`
                <tr style="cursor: pointer;" data-producto='${JSON.stringify(producto)}'>
                    <td>${producto.codigo}</td>
                    <td>${producto.descripcion}</td>
                    <td>
                        <button class="btn btn-sm btn-primary seleccionar-producto">
                            <i class="fas fa-check"></i> Seleccionar
                        </button>
                    </td>
                </tr>
            `);
            
            // Evento click en fila o botón
            fila.on('click', () => {
                this.seleccionarProducto(producto);
                this.limpiarResultados();
            });
            
            tabla.append(fila);
        });
        
        $('#resultadosBusqueda').show();
    }
    
    limpiarResultados() {
        $('#resultadosBusqueda').hide();
        $('#tablaResultados').empty();
    }
    
    limpiarCampoEntrada() {
        $('#buscarProducto').val('');
    }
    
    enfocarCampoPrincipal() {
        setTimeout(() => {
            $('#buscarProducto').focus().select();
        }, 100);
    }
    
    limpiarTodo() {
        this.productoSeleccionado = null;
        this.estado = 'esperando';
        
        this.limpiarCampoEntrada();
        this.limpiarResultados();
        this.mostrarFormularioProducto(false);
        this.enfocarCampoPrincipal();
        
        this.mostrarEstado('🔄 Sistema reiniciado', 'info', 2000);
    }
    
    cancelarSeleccion() {
        this.limpiarTodo();
    }
    
    // Métodos auxiliares
    actualizarContenedorManual(contenedorId) {
        if (contenedorId) {
            const contenedor = this.contenedores.find(c => c.id == contenedorId);
            if (contenedor) {
                this.seleccionarContenedor(contenedor);
                this.mostrarEstado(`📦 Contenedor cambiado manualmente: ${contenedor.nombre}`, 'info', 2000);
            }
        } else {
            // Sin contenedor seleccionado
            this.limpiarContenedorAnterior();
            this.mostrarEstado(`🚫 Sin contenedor seleccionado manualmente`, 'warning', 2000);
        }
    }
    
    obtenerPesoContenedorSeleccionado() {
        const contenedorId = $('#contenedorProducto').val();
        if (!contenedorId) return 0;
        
        const contenedor = this.contenedores.find(c => c.id == contenedorId);
        return contenedor ? parseFloat(contenedor.peso || 0) : 0;
    }
    
    obtenerNombreContenedorSeleccionado() {
        const contenedorId = $('#contenedorProducto').val();
        if (!contenedorId) return null;
        
        const contenedor = this.contenedores.find(c => c.id == contenedorId);
        return contenedor ? contenedor.nombre : null;
    }
    
    actualizarPesoTotal() {
        const peso = parseFloat($('#pesoProducto').val()) || 0;
        const pesoContenedor = this.obtenerPesoContenedorSeleccionado();
        
        // El peso ya incluye el contenedor, por lo que NO se suma
        // Solo mostramos el peso del contenedor como referencia
        $('#pesoContenedorDisplay').val(`${pesoContenedor.toFixed(3)} kg`);
        $('#pesoTotalDisplay').val(`${peso.toFixed(3)} kg`); // Solo el peso leído
    }
    
    actualizarInterfazContenedor() {
        // Actualizar panel de contenedor activo
        $('#contenedorActivoNombre').text(this.ultimoContenedorNombre);
        $('#contenedorActivoPeso').text(`Peso: ${this.ultimoContenedorPeso.toFixed(3)} kg`);
        
        // Seleccionar en dropdown si existe, o resetear a vacío si no hay contenedor
        if (this.ultimoContenedor) {
            $('#contenedorProducto').val(this.ultimoContenedor);
        } else {
            $('#contenedorProducto').val(''); // Resetear a "Sin contenedor"
        }
    }
    
    aplicarContenedorAnterior() {
        if (this.ultimoContenedor) {
            const contenedor = this.contenedores.find(c => c.id == this.ultimoContenedor);
            if (contenedor) {
                this.actualizarInterfazContenedor();
                this.mostrarEstado(`📦 Contenedor anterior aplicado: ${this.ultimoContenedorNombre}`, 'info', 3000);
            } else {
                // Limpiar si el contenedor ya no existe
                this.limpiarContenedorAnterior();
            }
        }
    }
    
    limpiarContenedorAnterior() {
        this.ultimoContenedor = '';
        this.ultimoContenedorNombre = 'Sin contenedor';
        this.ultimoContenedorPeso = 0;
        
        localStorage.removeItem('ultimoContenedor');
        localStorage.removeItem('ultimoContenedorNombre');
        localStorage.removeItem('ultimoContenedorPeso');
        
        this.actualizarInterfazContenedor();
    }
    
    inicializarInterfaz() {
        // Ocultar elementos iniciales
        $('#estadoOperacion').hide();
        $('#resultadosBusqueda').hide();
        $('#formularioProducto').hide();
        
        // Inicializar estadísticas
        $('#contadorProductos').text('0');
        $('#tiempoPromedio').text('0s');
    }
    
    // Métodos de carga de datos
    async cargarContenedores() {
        try {
            const response = await fetch('api/contenedores');
            const data = await response.json();
            
            if (data.success) {
                this.contenedores = data.data;
                console.log('📦 Contenedores cargados:', this.contenedores);
                this.cargarContenedoresEnSelect();
            } else {
                throw new Error('Error cargando contenedores');
            }
        } catch (error) {
            console.error('Error cargando contenedores:', error);
            this.mostrarError('Error cargando contenedores');
        }
    }
    
    cargarContenedoresEnSelect() {
        const select = $('#contenedorProducto');
        select.empty().append('<option value="">Sin contenedor</option>');
        
        this.contenedores.forEach(contenedor => {
            select.append(`<option value="${contenedor.id}">${contenedor.nombre} (${contenedor.peso}kg)</option>`);
        });
    }
    
    async cargarRegistrosDia() {
        try {
            const hoy = new Date().toISOString().split('T')[0];
            const response = await fetch(`api/movimientos/alta-deposito/registros?fecha=${hoy}`);
            const data = await response.json();
            
            if (data.success) {
                this.mostrarRegistrosDia(data.data);
            }
        } catch (error) {
            console.error('Error cargando registros del día:', error);
        }
    }
    
    mostrarRegistrosDia(registros) {
        const tabla = $('#tablaRegistros');
        tabla.empty();
        
        if (registros.length === 0) {
            tabla.append('<tr><td colspan="7" class="text-center text-muted">No hay registros para hoy</td></tr>');
            return;
        }
        
        registros.forEach(registro => {
            const hora = new Date(registro.fechaAlta).toLocaleTimeString();
            const contenedor = registro.contenedor_nombre || '-';
            const estado = registro.estado || 'NUEVO';
            
            tabla.append(`
                <tr>
                    <td>${hora}</td>
                    <td>${registro.codigo}</td>
                    <td>${registro.descripcion}</td>
                    <td>${registro.cnt}</td>
                    <td>${parseFloat(registro.cnt_peso).toFixed(3)} kg</td>
                    <td>${contenedor}</td>
                    <td><span class="badge badge-success">${estado}</span></td>
                </tr>
            `);
        });
    }
    
    // Método para generar códigos de barras (mantener funcionalidad existente)
    async generarCodigosBarras() {
        try {
            // Mostrar loading
            Swal.fire({
                title: 'Generando códigos de barras...',
                text: 'Por favor espere mientras se genera el PDF',
                allowOutsideClick: false,
                didOpen: () => {
                    Swal.showLoading();
                }
            });

            const response = await fetch('api/contenedores/codigos-barras');
            const data = await response.json();

            Swal.close();

            if (data.success) {
                // Descarga automática
                const link = document.createElement('a');
                link.href = data.archivo;
                link.download = data.archivo.split('/').pop();
                document.body.appendChild(link);
                link.click();
                document.body.removeChild(link);
                
                this.mostrarExito('PDF de códigos de barras descargado exitosamente');
                
                // Mostrar información adicional
                if (data.detalles) {
                    setTimeout(() => {
                        Swal.fire({
                            icon: 'info',
                            title: 'Códigos generados',
                            html: `
                                <div style="text-align: left;">
                                    <p><strong>📊 Detalles:</strong></p>
                                    <ul>
                                        <li><strong>Formato:</strong> ${data.detalles.formato}</li>
                                        <li><strong>Contenedores:</strong> ${data.detalles.contenedores} + Sin contenedor</li>
                                        <li><strong>Patrón:</strong> ${data.detalles.patron}</li>
                                        <li><strong>Especial:</strong> 0000000 = Sin contenedor</li>
                                    </ul>
                                </div>
                            `,
                            confirmButtonText: 'Entendido'
                        });
                    }, 1000);
                }
            } else {
                throw new Error(data.error || 'Error al generar códigos de barras');
            }
        } catch (error) {
            Swal.close();
            console.error('Error generando códigos de barras:', error);
            this.mostrarError('Error al generar códigos de barras: ' + error.message);
        }
    }
    
    // Métodos de utilidad
    mostrarError(mensaje) {
        Swal.fire({
            icon: 'error',
            title: 'Error',
            text: mensaje,
            timer: 3000,
            timerProgressBar: true
        });
    }
    
    mostrarExito(mensaje) {
        Swal.fire({
            icon: 'success',
            title: 'Éxito',
            text: mensaje,
            timer: 2000,
            timerProgressBar: true,
            showConfirmButton: false
        });
    }
}

// CSS adicional para animaciones
const estilosAdicionales = `
<style>
@keyframes countdown {
    from { width: 100%; }
    to { width: 0%; }
}

.form-control-lg {
    font-size: 1.25rem;
}

.info-box-content {
    display: flex;
    flex-direction: column;
}

@media (max-width: 768px) {
    .form-control-lg {
        font-size: 1.1rem;
    }
    
    .card-body {
        padding: 1rem;
    }
    
    .btn-lg {
        padding: 0.5rem 1rem;
        font-size: 1rem;
    }
}
</style>
`;

// Inyectar estilos
$('head').append(estilosAdicionales);

// Inicializar cuando el DOM esté listo
$(document).ready(() => {
    window.altaDepositoIndustrial = new AltaDepositoIndustrial();
});