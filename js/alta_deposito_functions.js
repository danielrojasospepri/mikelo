function cargarMovimientosDeposito() {
    const estado = document.getElementById('filtroEstado').value;
    let url = `api/movimientos/deposito/${fechaActual}`;
    if (estado) {
        url += `?estado=${estado}`;
    }
    
    fetch(url)
        .then(response => response.json())
        .then(data => {
            if (data.movimientos) {
                mostrarMovimientosDeposito(data.movimientos);
            } else {
                mostrarMensaje(data.error || 'Error al cargar los movimientos', 'error');
            }
        })
        .catch(error => {
            mostrarMensaje('Error de conexión', 'error');
            console.error('Error:', error);
        });
}

function mostrarMovimientosDeposito(movimientos) {
    const tabla = document.getElementById('movimientosTable');
    tabla.innerHTML = '';
    
    if (movimientos.length === 0) {
        const tr = document.createElement('tr');
        tr.innerHTML = '<td colspan="6" class="text-center">No hay movimientos para mostrar</td>';
        tabla.appendChild(tr);
        return;
    }
    
    movimientos.forEach(movimiento => {
        const tr = document.createElement('tr');
        tr.innerHTML = `
            <td>${new Date(movimiento.fechaAlta).toLocaleTimeString('es-ES')}</td>
            <td>${movimiento.codigo}</td>
            <td>${movimiento.descripcion}</td>
            <td>${movimiento.cnt}</td>
            <td>${movimiento.cnt_peso || '0'}</td>
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
    alert(mensaje);
}