let timeoutBusqueda = null;

document.addEventListener('DOMContentLoaded', function() {
    // Inicializar filtros con fechas por defecto
    const hoy = new Date();
    document.getElementById('fechaDesde').value = hoy.toISOString().split('T')[0];
    document.getElementById('fechaHasta').value = hoy.toISOString().split('T')[0];

    // Cargar ubicaciones en el filtro
    cargarUbicaciones();
    
    // Agregar eventos a los filtros
    document.getElementById('fechaDesde').addEventListener('change', buscarMovimientos);
    document.getElementById('fechaHasta').addEventListener('change', buscarMovimientos);
    document.getElementById('ubicacion').addEventListener('change', buscarMovimientos);
    document.getElementById('estado').addEventListener('change', buscarMovimientos);
    
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
    
    let url = 'api/movimientos/buscar?';
    const params = new URLSearchParams();
    
    if (fechaDesde) params.append('fecha_desde', fechaDesde);
    if (fechaHasta) params.append('fecha_hasta', fechaHasta);
    if (ubicacion) params.append('ubicacion', ubicacion);
    if (estado) params.append('estado', estado);
    
    fetch(url + params.toString())
        .then(response => response.json())
        .then(data => {
            if (data.movimientos) {
                mostrarMovimientos(data.movimientos);
            } else {
                mostrarMensaje(data.error || 'Error al buscar movimientos', 'error');
            }
        })
        .catch(error => {
            mostrarMensaje('Error de conexión', 'error');
            console.error('Error:', error);
        });
}

function mostrarMovimientos(movimientos) {
    const tabla = document.getElementById('movimientosTable');
    tabla.innerHTML = '';
    
    movimientos.forEach(movimiento => {
        const tr = document.createElement('tr');
        tr.innerHTML = `
            <td>${new Date(movimiento.fechaAlta).toLocaleString('es-ES')}</td>
            <td>${movimiento.ubicacion_origen || '-'}</td>
            <td>${movimiento.ubicacion_destino}</td>
            <td>${movimiento.codigo}</td>
            <td>${movimiento.descripcion}</td>
            <td>${movimiento.cnt}</td>
            <td>${movimiento.cnt_peso}</td>
            <td><span class="badge bg-${getEstadoBadgeClass(movimiento.estado)}">${movimiento.estado}</span></td>
        `;
        tabla.appendChild(tr);
    });
}

function getEstadoBadgeClass(estado) {
    switch (estado.toLowerCase()) {
        case 'nuevo': return 'success';
        case 'enviado': return 'primary';
        case 'cancelado': return 'danger';
        default: return 'secondary';
    }
}

function mostrarMensaje(mensaje, tipo) {
    // Implementar con toastr o similar
    alert(mensaje);
}