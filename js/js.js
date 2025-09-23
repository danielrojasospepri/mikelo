let movimientoActualId = null;
let timeoutBusqueda = null;

document.addEventListener('DOMContentLoaded', function() {
    cargarUbicaciones();
    document.getElementById('movimientoForm').addEventListener('submit', crearMovimiento);
    document.getElementById('buscarProducto').addEventListener('input', buscarProductos);
});

function cargarUbicaciones() {
    fetch('api/ubicaciones')
        .then(response => response.json())
        .then(data => {
            if (data.ubicaciones) {
                const selectOrigen = document.getElementById('ubicacionOrigen');
                const selectDestino = document.getElementById('ubicacionDestino');
                
                // Establecer Deposito Central como origen por defecto
                const depositoCentral = data.ubicaciones.find(u => u.nombre.toLowerCase().includes('deposito'));
                if (depositoCentral) {
                    selectOrigen.innerHTML = `<option value="${depositoCentral.id}" selected>${depositoCentral.nombre}</option>`;
                }
                
                // Cargar destinos (excluyendo el depósito central)
                data.ubicaciones.forEach(ubicacion => {
                    if (!depositoCentral || ubicacion.id !== depositoCentral.id) {
                        selectDestino.add(new Option(ubicacion.nombre, ubicacion.id));
                    }
                });
            }
        })
        .catch(error => {
            console.error('Error:', error);
            mostrarMensaje('Error al cargar ubicaciones', 'error');
        });
}

function crearMovimiento(e) {
    e.preventDefault();
    
    const data = {
        ubicacion_origen: document.getElementById('ubicacionOrigen').value || null,
        ubicacion_destino: document.getElementById('ubicacionDestino').value
    };

    fetch('api/movimientos', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify(data)
    })
    .then(response => response.json())
    .then(data => {
        if (data.id) {
            movimientoActualId = data.id;
            document.getElementById('movimientoForm').style.display = 'none';
            document.getElementById('itemsCard').style.display = 'block';
            mostrarMensaje('Movimiento creado exitosamente', 'success');
        } else {
            mostrarMensaje(data.error || 'Error al crear el movimiento', 'error');
        }
    })
    .catch(error => {
        mostrarMensaje('Error de conexión', 'error');
        console.error('Error:', error);
    });
}

function buscarProductos(e) {
    const termino = e.target.value;
    
    // Cancelar búsqueda anterior
    if (timeoutBusqueda) {
        clearTimeout(timeoutBusqueda);
    }
    
    if (termino.length < 2) return;
    
    timeoutBusqueda = setTimeout(() => {
        fetch(`api/productos/buscar?q=${encodeURIComponent(termino)}`)
            .then(response => response.json())
            .then(data => {
                if (data.productos) {
                    mostrarResultadosBusqueda(data.productos);
                } else {
                    mostrarMensaje(data.error || 'Error al buscar productos', 'error');
                }
            })
            .catch(error => {
                mostrarMensaje('Error de conexión', 'error');
                console.error('Error:', error);
            });
    }, 300);
}

function mostrarResultadosBusqueda(productos) {
    const tabla = document.getElementById('itemsTable');
    tabla.innerHTML = '';
    
    productos.forEach(producto => {
        const tr = document.createElement('tr');
        tr.innerHTML = `
            <td>${producto.codigo}</td>
            <td>${producto.descripcion}</td>
            <td><input type="number" class="form-control cantidad-input" value="1" min="0" step="1"></td>
            <td><input type="number" class="form-control peso-input" value="0" min="0" step="0.001"></td>
            <td>
                <button class="btn btn-primary btn-sm btn-agregar" onclick="agregarItem(${producto.id}, this)">
                    Agregar
                </button>
            </td>
        `;
        tabla.appendChild(tr);
    });
}

function agregarItem(productoId, btnElement) {
    const tr = btnElement.closest('tr');
    const cantidad = tr.querySelector('.cantidad-input').value;
    const peso = tr.querySelector('.peso-input').value;
    
    const data = {
        producto_id: productoId,
        cantidad: cantidad,
        cantidad_peso: peso
    };

    fetch(`api/movimientos/${movimientoActualId}/items`, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify(data)
    })
    .then(response => response.json())
    .then(data => {
        if (data.id) {
            tr.remove();
            mostrarMensaje('Item agregado exitosamente', 'success');
        } else {
            mostrarMensaje(data.error || 'Error al agregar el item', 'error');
        }
    })
    .catch(error => {
        mostrarMensaje('Error de conexión', 'error');
        console.error('Error:', error);
    });
}

function mostrarMensaje(mensaje, tipo) {
    // Usar toastr o implementar tu propio sistema de notificaciones
    alert(mensaje);
}
