let timeoutBusqueda = null;

document.addEventListener('DOMContentLoaded', function() {
    document.getElementById('buscarProducto').addEventListener('input', buscarProductos);
});

function buscarProductos(e) {
    const termino = e.target.value;
    
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
                <button class="btn btn-primary btn-sm btn-agregar" onclick="altaProducto(${producto.id}, this)">
                    Alta
                </button>
            </td>
        `;
        tabla.appendChild(tr);
    });
}

function altaProducto(productoId, btnElement) {
    const tr = btnElement.closest('tr');
    const cantidad = tr.querySelector('.cantidad-input').value;
    const peso = tr.querySelector('.peso-input').value;
    
    // Primero creamos el movimiento
    const dataMovimiento = {
        ubicacion_destino: 1 // ID del depósito central
    };

    fetch('api/movimientos', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify(dataMovimiento)
    })
    .then(response => response.json())
    .then(movimientoData => {
        if (movimientoData.id) {
            // Si el movimiento se creó exitosamente, agregamos el ítem
            const dataItem = {
                producto_id: productoId,
                cantidad: cantidad,
                cantidad_peso: peso
            };

            return fetch(`api/movimientos/${movimientoData.id}/items`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify(dataItem)
            }).then(response => response.json());
        } else {
            throw new Error(movimientoData.error || 'Error al crear el movimiento');
        }
    })
    .then(itemData => {
        if (itemData.id) {
            tr.remove();
            mostrarMensaje('Producto dado de alta exitosamente', 'success');
        } else {
            mostrarMensaje(itemData.error || 'Error al agregar el item', 'error');
        }
    })
    .catch(error => {
        mostrarMensaje('Error: ' + error.message, 'error');
        console.error('Error:', error);
    });
}

function mostrarMensaje(mensaje, tipo) {
    // Usar toastr o implementar tu propio sistema de notificaciones
    alert(mensaje);
}