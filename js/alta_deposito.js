let timeoutBusqueda = 1000;
let fechaActual = new Date().toISOString().split("T")[0];
let productoSeleccionado = null;

let html5QrcodeScanner = null;

document.addEventListener("DOMContentLoaded", function () {
  // Eventos de búsqueda
  document
    .getElementById("buscarProducto")
    .addEventListener("input", buscarProductos);
  document
    .getElementById("limpiarBusqueda")
    .addEventListener("click", limpiarBusqueda);
  document
    .getElementById("btnEscanearQR")
    .addEventListener("click", iniciarEscaneoQR);

  // Cargar contenedores al inicio
  cargarContenedores();

  // Evento para actualizar peso total cuando se selecciona un contenedor
  document
    .getElementById("contenedorProducto")
    .addEventListener("change", actualizarPesoTotal);

  // Eventos de formulario
  document
    .getElementById("btnGuardar")
    .addEventListener("click", guardarRegistro);
  document
    .getElementById("btnCancelar")
    .addEventListener("click", cancelarSeleccion);

  // Evento para detener el escáner cuando se cierra el modal
  $('#qrModal').on('hidden.bs.modal', function () {
    if (html5QrcodeScanner) {
      html5QrcodeScanner.clear();
      html5QrcodeScanner = null;
    }
  });

  // Cargar registros del día
  cargarRegistrosDelDia();
});

function buscarProductos(e) {
  const termino = e.target.value.trim();

  if (timeoutBusqueda) {
    clearTimeout(timeoutBusqueda);
  }

  // Ocultar resultados si el término de búsqueda está vacío
  if (termino.length < 2) {
    document.getElementById("resultadosBusqueda").style.display = "none";
    return;
  }

  if (termino.length >= 10) {
    /// identifico lectura de cod de barras
    document.getElementById("resultadosBusqueda").style.display = "none";
    buscarProductosCodBarras(e);
    return;
  }

  timeoutBusqueda = setTimeout(() => {
    document.getElementById("resultadosBusqueda").style.display = "block";
    document.getElementById("resultadosTable").innerHTML =
      '<tr><td colspan="3" class="text-center"><i class="fas fa-spinner fa-spin"></i> Buscando...</td></tr>';

    fetch(`api/productos/nuevos?q=${encodeURIComponent(termino)}`, {
      method: "GET",
      headers: {
        "Content-Type": "application/json",
      },
    })
      .then((response) => {
        if (!response.ok) {
          throw new Error(
            "Error en la respuesta del servidor: " + response.status
          );
        }
        return response.json();
      })
      .then((data) => {
        if (data.productos) {
          mostrarResultadosBusqueda(data.productos);
        } else {
          document.getElementById("resultadosTable").innerHTML =
            '<tr><td colspan="3" class="text-center">No se encontraron productos</td></tr>';
          mostrarMensaje("No se encontraron productos", "warning");
        }
      })
      .catch((error) => {
        document.getElementById("resultadosTable").innerHTML =
          '<tr><td colspan="3" class="text-center text-danger">Error al buscar productos</td></tr>';
        mostrarMensaje("Error: " + error.message, "error");
        console.error("Error:", error);
      });
  }, 300);
}

function buscarProductosCodBarras(e) {
  const termino = e.target.value.trim();

  if (timeoutBusqueda) {
    clearTimeout(timeoutBusqueda);
  }

  // Ocultar resultados si el término de búsqueda está vacío
  if (termino.length < 10) {
    document.getElementById("resultadosBusqueda").style.display = "none";
    return;
  }
  // extraigo del string termino los dos primeros caracteres en una variable tipoCB, luego 5 caracteres en la variable codigoCB y por ultimo los restantes caracteres en una variable cntCB
  const tipoCB = termino.substring(0, 2);
  const codigoCB = termino.substring(2, 7).replace(/^0+/, ""); // a codCB tle debo quitar solo los ceros que tenga adelante
  const cntCB = termino.substring(7, 12);
  console.log("Tipo:", tipoCB);
  console.log("Código:", codigoCB);
  console.log("Cantidad:", cntCB);

  timeoutBusqueda = setTimeout(() => {
    document.getElementById("resultadosBusqueda").style.display = "block";
    document.getElementById("resultadosTable").innerHTML =
      '<tr><td colspan="3" class="text-center"><i class="fas fa-spinner fa-spin"></i> Buscando...</td></tr>';

    fetch(`api/productos/nuevos?q=${encodeURIComponent(codigoCB)}`, {
      method: "GET",
      headers: {
        "Content-Type": "application/json",
      },
    })
      .then((response) => {
        if (!response.ok) {
          throw new Error(
            "Error en la respuesta del servidor: " + response.status
          );
        }
        return response.json();
      })
      .then((data) => {
        if (data.productos) {
          //valido si hay solo 1 producto en data.productos
          if (Array.isArray(data.productos) && data.productos.length === 1) {
            // Solo hay un producto
            console.log(data.productos[0]["descripcion"]);
            seleccionarProducto(
              JSON.parse(JSON.stringify(data.productos[0]))
            );

            setTimeout(() => {
              if (tipoCB == "21") {
                ///  imprime peso
                document.getElementById("pesoProducto").value =
                  parseFloat(cntCB) / 1000; // lo paso a kg
              }
              if (tipoCB == "20") {
                ///  imprime unidades
                document.getElementById("cantidadProducto").value =
                  parseFloat(cntCB); // revisar si estan declarados con fracciones las unidades
              }
            }, 100);
          } else {
            // Hay múltiples productos
            mostrarResultadosBusqueda(data.productos);
          }
        } else {
          document.getElementById("resultadosTable").innerHTML =
            '<tr><td colspan="3" class="text-center">No se encontraron productos</td></tr>';
          mostrarMensaje("No se encontraron productos", "warning");
        }
      })
      .catch((error) => {
        document.getElementById("resultadosTable").innerHTML =
          '<tr><td colspan="3" class="text-center text-danger">Error al buscar productos</td></tr>';
        mostrarMensaje("Error: " + error.message, "error");
        console.error("Error:", error);
      });
  }, 300);
}

function mostrarResultadosBusqueda(productos) {
  const tabla = document.getElementById("resultadosTable");
  tabla.innerHTML = "";

  if (productos.length === 0) {
    tabla.innerHTML =
      '<tr><td colspan="3" class="text-center">No se encontraron productos</td></tr>';
    return;
  }

  productos.forEach((producto) => {
    const tr = document.createElement("tr");
    tr.innerHTML = `
            <td>${producto.codigo || "-"}</td>
            <td>${producto.descripcion || "-"}</td>
            <td>
                <button class="btn btn-primary btn-sm" onclick="seleccionarProducto(${JSON.stringify(
                  producto
                ).replace(/"/g, "&quot;")})">
                    <i class="fas fa-check"></i> Seleccionar
                </button>
            </td>
        `;
    tabla.appendChild(tr);
  });
}

function altaProducto(productoId, btnElement) {
  const tr = btnElement.closest("tr");
  const cantidad = tr.querySelector(".cantidad-input").value;
  const peso = tr.querySelector(".peso-input").value;

  // Primero creamos el movimiento
  const dataMovimiento = {
    ubicacion_destino: 1, // ID del depósito central
  };

  fetch("api/movimientos", {
    method: "POST",
    headers: {
      "Content-Type": "application/json",
    },
    body: JSON.stringify(dataMovimiento),
  })
    .then((response) => response.json())
    .then((movimientoData) => {
      if (movimientoData.id) {
        // Si el movimiento se creó exitosamente, agregamos el ítem
        const dataItem = {
          producto_id: productoId,
          cantidad: cantidad,
          cantidad_peso: peso,
        };

        return fetch(`api/movimientos/${movimientoData.id}/items`, {
          method: "POST",
          headers: {
            "Content-Type": "application/json",
          },
          body: JSON.stringify(dataItem),
        }).then((response) => response.json());
      } else {
        throw new Error(movimientoData.error || "Error al crear el movimiento");
      }
    })
    .then((itemData) => {
      if (itemData.id) {
        tr.remove();
        mostrarMensaje("Producto dado de alta exitosamente", "success");
        document.getElementById("buscarProducto").value = "";
        document.getElementById("resultadosBusqueda").style.display = "none";
        cargarMovimientosDeposito(); // Actualizar la lista de movimientos
      } else {
        mostrarMensaje(itemData.error || "Error al agregar el item", "error");
      }
    })
    .catch((error) => {
      mostrarMensaje("Error: " + error.message, "error");
      console.error("Error:", error);
    });
}

function mostrarMensaje(mensaje, tipo = "info") {
  Swal.fire({
    title:
      tipo === "error"
        ? "Error"
        : tipo === "warning"
        ? "Advertencia"
        : "Información",
    text: mensaje,
    icon: tipo,
    confirmButtonText: "Aceptar",
  });
}

function cargarContenedores() {
  fetch('api/contenedores')
    .then(response => response.json())
    .then(data => {
      if (data.success) {
        const select = document.getElementById('contenedorProducto');
        select.innerHTML = '<option value="">Sin contenedor</option>';
        data.contenedores.forEach(contenedor => {
          select.innerHTML += `<option value="${contenedor.id}" data-peso="${contenedor.peso}">${contenedor.nombre}</option>`;
        });
      }
    })
    .catch(error => {
      console.error('Error al cargar contenedores:', error);
      mostrarMensaje('Error al cargar los contenedores', 'error');
    });
}

function actualizarPesoTotal() {
  const pesoProductoInput = document.getElementById('pesoProducto');
  const contenedorSelect = document.getElementById('contenedorProducto');
  const opcionSeleccionada = contenedorSelect.options[contenedorSelect.selectedIndex];

  // Obtener el peso bruto actual
  const pesoBruto = parseFloat(pesoProductoInput.value) || 0;

  // Si hay un contenedor seleccionado, mostrar el peso bruto + peso del contenedor
  if (opcionSeleccionada && opcionSeleccionada.value) {
    const pesoContenedor = parseFloat(opcionSeleccionada.dataset.peso) || 0;
    const pesoTotal = pesoBruto + pesoContenedor;
    document.getElementById('pesoTotalDisplay').textContent = `Peso Total: ${pesoTotal.toFixed(3)} kg`;
  } else {
    document.getElementById('pesoTotalDisplay').textContent = `Peso Total: ${pesoBruto.toFixed(3)} kg`;
  }
}

function seleccionarProducto(producto) {
  productoSeleccionado = producto;

  console.log('Producto seleccionado:');
  console.log(typeof productoSeleccionado);
  console.log(productoSeleccionado);

  // Llenar el formulario
  document.getElementById("codigoProducto").value = producto.codigo || "";
  document.getElementById("descripcionProducto").value =
    producto.descripcion || "";
  document.getElementById("cantidadProducto").value = "1";
  document.getElementById("pesoProducto").value = "0";

  // Mostrar sección de producto seleccionado y ocultar resultados
  document.getElementById("productoSeleccionado").style.display = "block";
  document.getElementById("resultadosBusqueda").style.display = "none";
  document.getElementById("buscarProducto").value = "";
}

function limpiarBusqueda() {
  document.getElementById("buscarProducto").value = "";
  document.getElementById("resultadosBusqueda").style.display = "none";
}

function cancelarSeleccion() {
  productoSeleccionado = null;
  document.getElementById("productoSeleccionado").style.display = "none";
  document.getElementById("altaProductoForm").reset();
}

async function verificarRegistroDuplicado(producto_id, cantidad, peso) {
  try {
    const response = await fetch(`api/movimientos/verificar-duplicado`, {
      method: "POST",
      headers: {
        "Content-Type": "application/json",
      },
      body: JSON.stringify({
        producto_id: producto_id,
        cantidad: cantidad,
        peso: peso,
        fecha: fechaActual,
      }),
    });

    if (!response.ok) {
      throw new Error("Error al verificar duplicados");
    }

    const data = await response.json();
    return data.duplicado || false;
  } catch (error) {
    console.error("Error al verificar duplicados:", error);
    return false;
  }
}

async function guardarRegistro() {
  if (!productoSeleccionado) {
    mostrarMensaje("No hay producto seleccionado", "error");
    return;
  }

  const cantidad = parseInt(document.getElementById("cantidadProducto").value);
  const peso = parseFloat(document.getElementById("pesoProducto").value);

  if (cantidad < 1) {
    mostrarMensaje("La cantidad debe ser mayor a 0", "error");
    return;
  }

  if (peso < 0) {
    mostrarMensaje("El peso no puede ser negativo", "error");
    return;
  }

  // Verificar duplicados
  const duplicado = await verificarRegistroDuplicado(
    productoSeleccionado.id,
    cantidad,
    peso
  );

  if (duplicado) {
    const confirmar = await Swal.fire({
      title: "¡Registro duplicado!",
      text: "Ya existe un registro con los mismos datos hoy. ¿Desea continuar?",
      icon: "warning",
      showCancelButton: true,
      confirmButtonText: "Sí, registrar",
      cancelButtonText: "No, cancelar",
    });

    if (!confirmar.isConfirmed) {
      return;
    }
  }

  // Crear el movimiento
  try {
    const responseMovimiento = await fetch("api/movimientos", {
      method: "POST",
      headers: {
        "Content-Type": "application/json",
      },
      body: JSON.stringify({
        ubicacion_destino: 1, // ID del depósito central
      }),
    });

    if (!responseMovimiento.ok) {
      throw new Error("Error al crear el movimiento");
    }

    const movimientoData = await responseMovimiento.json();

    // Agregar el ítem al movimiento
    const responseItem = await fetch(
      `api/movimientos/${movimientoData.id}/items`,
      {
        method: "POST",
        headers: {
          "Content-Type": "application/json",
        },
        body: JSON.stringify({
          producto_id: productoSeleccionado.id,
          cantidad: cantidad,
          cantidad_peso: peso,
          id_contenedor: document.getElementById('contenedorProducto').value || null
        }),
      }
    );

    if (!responseItem.ok) {
      throw new Error("Error al agregar el item");
    }

    const itemData = await responseItem.json();

    // Mostrar mensaje de éxito
    await Swal.fire({
      title: "¡Registro exitoso!",
      html: `
                <p>Se registró correctamente:</p>
                <ul>
                    <li><strong>Producto:</strong> ${productoSeleccionado.descripcion}</li>
                    <li><strong>Cantidad:</strong> ${cantidad}</li>
                    <li><strong>Peso:</strong> ${peso} kg</li>
                </ul>
            `,
      icon: "success",
    });

    // Limpiar formulario y actualizar registros
    cancelarSeleccion();
    cargarRegistrosDelDia();
  } catch (error) {
    mostrarMensaje("Error: " + error.message, "error");
    console.error("Error:", error);
  }
}

function iniciarEscaneoQR() {
  if (html5QrcodeScanner) {
    html5QrcodeScanner.clear();
  }

  html5QrcodeScanner = new Html5QrcodeScanner(
    "qr-reader",
    { 
      fps: 10,
      qrbox: { width: 300, height: 100 }, // Más ancho y menos alto para códigos de barras
      formatsToSupport: [ 
        Html5QrcodeSupportedFormats.EAN_13,
        Html5QrcodeSupportedFormats.EAN_8,
        Html5QrcodeSupportedFormats.CODE_128,
        Html5QrcodeSupportedFormats.CODE_39,
        Html5QrcodeSupportedFormats.UPC_A,
        Html5QrcodeSupportedFormats.UPC_E,
        Html5QrcodeSupportedFormats.ITF
      ],
      experimentalFeatures: {
        useBarCodeDetectorIfSupported: true
      }
    }
  );

  html5QrcodeScanner.render((decodedText, decodedResult) => {
    // Al escanear un código exitosamente
    console.log(`Código escaneado: ${decodedText}`);
    
    // Detener el escáner
    html5QrcodeScanner.clear();
    
    // Cerrar el modal
    $('#qrModal').modal('hide');
    
    // Colocar el código en el campo de búsqueda y disparar la búsqueda
    const inputBusqueda = document.getElementById("buscarProducto");
    inputBusqueda.value = decodedText;
    buscarProductos({ target: inputBusqueda });
  });

  // Mostrar el modal
  $('#qrModal').modal('show');
}

function cargarRegistrosDelDia() {
  const tabla = document.getElementById("registrosDiaTable");
  tabla.innerHTML =
    '<tr><td colspan="5" class="text-center"><i class="fas fa-spinner fa-spin"></i> Cargando registros...</td></tr>';

  fetch(`api/movimientos/deposito/${fechaActual}`)
    .then((response) => {
      if (!response.ok) {
        throw new Error("Error al cargar los registros");
      }
      return response.json();
    })
    .then((data) => {
      if (data.movimientos && data.movimientos.length > 0) {
        tabla.innerHTML = "";
        data.movimientos.forEach((movimiento) => {
          const fecha = new Date(movimiento.fechaAlta);
          const tr = document.createElement("tr");
          tr.innerHTML = `
                        <td>${fecha.toLocaleTimeString()}</td>
                        <td>${movimiento.codigo || "-"}</td>
                        <td>${movimiento.descripcion || "-"}</td>
                        <td>${movimiento.cnt}</td>
                        <td>${movimiento.cnt_peso} kg</td>
                    `;
          tabla.appendChild(tr);
        });
      } else {
        tabla.innerHTML =
          '<tr><td colspan="5" class="text-center">No hay registros para hoy</td></tr>';
      }
    })
    .catch((error) => {
      tabla.innerHTML =
        '<tr><td colspan="5" class="text-center text-danger">Error al cargar los registros</td></tr>';
      mostrarMensaje(
        "Error al cargar los registros: " + error.message,
        "error"
      );
      console.error("Error:", error);
    });
}
