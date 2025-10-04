let timeoutBusqueda = null;

document.addEventListener('DOMContentLoaded', function() {
    // Inicializar filtros con fechas por defecto
    const hoy = new Date();
    const hace30Dias = new Date();
    hace30Dias.setDate(hoy.getDate() - 30);
    
    document.getElementById('fechaDesde').value = hace30Dias.toISOString().split('T')[0];
    document.getElementById('fechaHasta').value = hoy.toISOString().split('T')[0];

    // Cargar ubicaciones en el filtro
    cargarUbicaciones();
    
    // Agregar eventos a los filtros
    document.getElementById('fechaDesde').addEventListener('change', buscarMovimientos);
    document.getElementById('fechaHasta').addEventListener('change', buscarMovimientos);
    document.getElementById('ubicacion').addEventListener('change', buscarMovimientos);
    document.getElementById('estado').addEventListener('change', buscarMovimientos);
    
    // Agregar evento de búsqueda con delay para el campo producto
    document.getElementById('producto').addEventListener('input', function() {
        clearTimeout(timeoutBusqueda);
        timeoutBusqueda = setTimeout(buscarMovimientos, 500);
    });
    
    // Cargar movimientos iniciales
    buscarMovimientos();
});

function cargarUbicaciones() {
    fetch('api/ubicaciones')
        .then(response => response.json())
        .then(data => {
            if (data.ubicaciones) {
                const select = document.getElementById('ubicacion');
                select.innerHTML = '<option value="">Todas las ubicaciones</option>';
                
                data.ubicaciones.forEach(ubicacion => {
                    select.add(new Option(ubicacion.nombre, ubicacion.id));
                });
            }
        })
        .catch(error => {
            console.error('Error:', error);
            mostrarMensaje('Error al cargar ubicaciones', 'error');
        });
}

function buscarMovimientos() {
    const fechaDesde = document.getElementById('fechaDesde').value;
    const fechaHasta = document.getElementById('fechaHasta').value;
    const ubicacion = document.getElementById('ubicacion').value;
    const estado = document.getElementById('estado').value;
    const producto = document.getElementById('producto').value.trim();
    
    let url = 'api/movimientos/buscar?';
    const params = new URLSearchParams();
    
    if (fechaDesde) params.append('fecha_desde', fechaDesde);
    if (fechaHasta) params.append('fecha_hasta', fechaHasta);
    if (ubicacion) params.append('ubicacion', ubicacion);
    if (estado) params.append('estado', estado);
    if (producto) params.append('producto', producto);
    
    // Mostrar indicador de carga
    const tabla = document.getElementById('movimientosTable');
    tabla.innerHTML = '<tr><td colspan="8" class="text-center"><i class="fas fa-spinner fa-spin"></i> Cargando...</td></tr>';
    
    fetch(url + params.toString())
        .then(response => response.json())
        .then(data => {
            if (data.movimientos) {
                mostrarMovimientos(data.movimientos);
            } else {
                tabla.innerHTML = '<tr><td colspan="8" class="text-center text-muted">No se encontraron movimientos</td></tr>';
                mostrarMensaje(data.error || 'No se encontraron movimientos', 'info');
            }
        })
        .catch(error => {
            tabla.innerHTML = '<tr><td colspan="8" class="text-center text-danger">Error al cargar datos</td></tr>';
            mostrarMensaje('Error de conexión', 'error');
            console.error('Error:', error);
        });
}

function mostrarMovimientos(movimientos) {
    const tabla = document.getElementById('movimientosTable');
    
    if (movimientos.length === 0) {
        tabla.innerHTML = '<tr><td colspan="8" class="text-center text-muted">No se encontraron movimientos con los filtros aplicados</td></tr>';
        return;
    }
    
    tabla.innerHTML = '';
    
    movimientos.forEach(movimiento => {
        const tr = document.createElement('tr');
        tr.innerHTML = `
            <td>${new Date(movimiento.fechaAlta).toLocaleString('es-ES')}</td>
            <td>${movimiento.ubicacion_origen || '-'}</td>
            <td>${movimiento.ubicacion_destino}</td>
            <td><strong>${movimiento.codigo}</strong></td>
            <td>${movimiento.descripcion}</td>
            <td>${movimiento.cnt}</td>
            <td>${movimiento.cnt_peso} kg</td>
            <td><span class="badge badge-${getEstadoBadgeClass(movimiento.estado)}">${movimiento.estado}</span></td>
        `;
        tabla.appendChild(tr);
    });
}

function getEstadoBadgeClass(estado) {
    switch (estado.toLowerCase()) {
        case 'nuevo': return 'success';
        case 'enviado': return 'primary';
        case 'cancelado': return 'danger';
        case 'recibido': return 'info';
        default: return 'secondary';
    }
}

function limpiarFiltros() {
    // Resetear fechas a los últimos 30 días
    const hoy = new Date();
    const hace30Dias = new Date();
    hace30Dias.setDate(hoy.getDate() - 30);
    
    document.getElementById('fechaDesde').value = hace30Dias.toISOString().split('T')[0];
    document.getElementById('fechaHasta').value = hoy.toISOString().split('T')[0];
    document.getElementById('ubicacion').value = '';
    document.getElementById('estado').value = '';
    document.getElementById('producto').value = '';
    
    // Buscar con filtros limpiados
    buscarMovimientos();
}

function exportarPDF() {
    const filtros = obtenerFiltrosActuales();
    
    Swal.fire({
        title: 'Generando PDF...',
        text: 'Por favor espere mientras se genera el archivo',
        allowOutsideClick: false,
        didOpen: () => {
            Swal.showLoading();
        }
    });
    
    const params = new URLSearchParams(filtros);
    fetch(`api/movimientos/pdf?${params.toString()}`)
        .then(response => {
            if (response.ok) {
                return response.json();
            }
            throw new Error('Error en la respuesta del servidor');
        })
        .then(data => {
            Swal.close();
            if (data.success) {
                // Descarga automática
                const link = document.createElement('a');
                link.href = data.archivo;
                link.download = data.archivo.split('/').pop();
                document.body.appendChild(link);
                link.click();
                document.body.removeChild(link);
                
                mostrarMensaje('PDF descargado exitosamente', 'success');
            } else {
                throw new Error(data.error || 'Error al generar PDF');
            }
        })
        .catch(error => {
            Swal.close();
            console.error('Error:', error);
            Swal.fire({
                title: 'Error',
                text: 'Error al generar el PDF: ' + error.message,
                icon: 'error'
            });
        });
}

function exportarExcel() {
    const filtros = obtenerFiltrosActuales();
    
    Swal.fire({
        title: 'Generando Excel...',
        text: 'Por favor espere mientras se genera el archivo',
        allowOutsideClick: false,
        didOpen: () => {
            Swal.showLoading();
        }
    });
    
    const params = new URLSearchParams(filtros);
    fetch(`api/movimientos/excel?${params.toString()}`)
        .then(response => {
            if (response.ok) {
                return response.json();
            }
            throw new Error('Error en la respuesta del servidor');
        })
        .then(data => {
            Swal.close();
            if (data.success) {
                // Descarga automática
                const link = document.createElement('a');
                link.href = data.archivo;
                link.download = data.archivo.split('/').pop();
                document.body.appendChild(link);
                link.click();
                document.body.removeChild(link);
                
                mostrarMensaje('Excel descargado exitosamente', 'success');
            } else {
                throw new Error(data.error || 'Error al generar Excel');
            }
        })
        .catch(error => {
            Swal.close();
            console.error('Error:', error);
            Swal.fire({
                title: 'Error',
                text: 'Error al generar el Excel: ' + error.message,
                icon: 'error'
            });
        });
}

function obtenerFiltrosActuales() {
    const filtros = {};
    
    const fechaDesde = document.getElementById('fechaDesde').value;
    const fechaHasta = document.getElementById('fechaHasta').value;
    const ubicacion = document.getElementById('ubicacion').value;
    const estado = document.getElementById('estado').value;
    const producto = document.getElementById('producto').value.trim();
    
    if (fechaDesde) filtros.fecha_desde = fechaDesde;
    if (fechaHasta) filtros.fecha_hasta = fechaHasta;
    if (ubicacion) filtros.ubicacion = ubicacion;
    if (estado) filtros.estado = estado;
    if (producto) filtros.producto = producto;
    
    return filtros;
}

function mostrarMensaje(mensaje, tipo) {
    const icon = tipo === 'error' ? 'error' : tipo === 'success' ? 'success' : 'info';
    
    Swal.fire({
        title: tipo === 'error' ? 'Error' : tipo === 'success' ? 'Éxito' : 'Información',
        text: mensaje,
        icon: icon,
        timer: tipo === 'info' ? 3000 : 5000,
        showConfirmButton: false
    });
}