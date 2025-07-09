// Función para alternar entre terreno regular e irregular
function toggleTerreno() {
    const tipoTerreno = document.getElementById('tipo_terreno').value;
    const btnAgregarForma = document.getElementById('btn_agregar_forma');
    const areaIrregularInput = document.getElementById('area_irregular');
    const formasContainer = document.getElementById('formas_container');

    const terrenoRegular = document.getElementById('terreno_regular');
    const terrenoIrregular = document.getElementById('terreno_irregular');

    if (terrenoRegular) terrenoRegular.style.display = 'none';
    if (terrenoIrregular) terrenoIrregular.style.display = 'none';
    if (btnAgregarForma) btnAgregarForma.style.display = 'none';
    if (areaIrregularInput) areaIrregularInput.style.display = 'none';

    // Mostrar según selección
    if (tipoTerreno === 'regular' && terrenoRegular) {
        terrenoRegular.style.display = 'block';
    } else if (tipoTerreno === 'irregular' && terrenoIrregular) {
        terrenoIrregular.style.display = 'block';
        if (btnAgregarForma) btnAgregarForma.style.display = 'block';
    }

    const formaTerreno = document.getElementById('forma_terreno');
    const dimension1 = document.getElementById('dimension1');
    const dimension2 = document.getElementById('dimension2');

    if (formaTerreno) formaTerreno.value = '';
    if (dimension1) dimension1.value = '';
    if (dimension2) dimension2.value = '';
    if (areaIrregularInput) areaIrregularInput.value = '';
    if (formasContainer) formasContainer.innerHTML = '';

    calcularArea();
}

// Función para calcular el área según la forma seleccionada
function calcularArea() {
    const tipoTerreno = document.getElementById('tipo_terreno').value;
    let area = 0;

    if (tipoTerreno === 'regular') {
        const forma = document.getElementById('forma_terreno').value;
        const dim1 = parseFloat(document.getElementById('dimension1').value) || 0;
        const dim2 = parseFloat(document.getElementById('dimension2').value) || 0;

        switch (forma) {
            case 'rectangulo':
                area = dim1 * dim2;
                break;
            case 'triangulo':
                area = (dim1 * dim2) / 2;
                break;
            case 'circulo':
                area = Math.PI * Math.pow(dim1, 2);
                break;
            default:
                area = 0;
        }
    } else if (tipoTerreno === 'irregular') {
        area = parseFloat(document.getElementById('area_irregular').value) || 0;
    }

    document.getElementById('area_total').value = area.toFixed(2);
    actualizarTotales();
}

// Funciones para manejar rollos
function actualizarRollos(element) {
    const item = element.closest('.product-item');
    const select = item.querySelector('.rollo-select');
    const cantidad = item.querySelector('input[type="number"]');
    const subtotal = item.querySelector('span');
    const detalles = item.querySelector('.rollo-details');

    // Mostrar detalles si hay producto seleccionado
    if (select.value) {
        detalles.style.display = 'block';
        detalles.querySelector('.modelo-text').textContent = select.selectedOptions[0].dataset.modelo;
        detalles.querySelector('.colores-text').textContent = select.selectedOptions[0].dataset.colores;
        detalles.querySelector('.area-text').textContent = 'Consultar';
    } else {
        detalles.style.display = 'none';
    }

    // Calcular subtotal
    const precio = parseFloat(select.selectedOptions[0]?.dataset.precio) || 0;
    const cant = parseFloat(cantidad.value) || 0;
    const total = (precio * cant).toFixed(2);
    subtotal.textContent = `$${total}`;

    actualizarTotales();
}

function agregarRollo() {
    let container = document.getElementById('rollos_container');
    let item = container.querySelector('.product-item').cloneNode(true);

    // Resetear valores
    item.querySelector('.rollo-select').value = '';
    item.querySelector('input[type="number"]').value = '';
    item.querySelector('span').textContent = '$0.00';
    item.querySelector('.rollo-details').style.display = 'none';

    item.querySelector('.rollo-select').addEventListener('change', function () {
        actualizarRollos(this);
    });
    item.querySelector('input[type="number"]').addEventListener('change', function () {
        actualizarRollos(this);
    });

    container.appendChild(item);
}

function removerRollo(btn) {
    let container = document.getElementById('rollos_container');
    if (container.querySelectorAll('.product-item').length > 1) {
        btn.closest('.product-item').remove();
        actualizarTotales();
    } else {
        alert('Debe haber al menos un rollo en la cotización.');
    }
}

// Funciones para manejar productos
function actualizarProductos(element) {
    const item = element.closest('.product-item');
    const select = item.querySelector('.product-select');
    const cantidad = item.querySelector('input[type="number"]');
    const subtotal = item.querySelector('span');

    const precio = parseFloat(select.selectedOptions[0]?.dataset.precio) || 0;
    const cant = parseFloat(cantidad.value) || 0;
    const total = (precio * cant).toFixed(2);

    subtotal.textContent = `$${total}`;
    actualizarTotales();
}

function agregarProducto() {
    let container = document.getElementById('productos_container');
    let item = container.querySelector('.product-item').cloneNode(true);

    item.querySelector('.product-select').value = '';
    item.querySelector('input[type="number"]').value = '1';
    item.querySelector('span').textContent = '$0.00';

    item.querySelector('.product-select').addEventListener('change', function () {
        actualizarProductos(this);
    });
    item.querySelector('input[type="number"]').addEventListener('change', function () {
        actualizarProductos(this);
    });

    container.appendChild(item);
}

function removerProducto(btn) {
    let container = document.getElementById('productos_container');
    if (container.querySelectorAll('.product-item').length > 1) {
        btn.closest('.product-item').remove();
        actualizarTotales();
    } else {
        alert('Debe haber al menos un producto.');
    }
}

// Función para actualizar totales
function actualizarTotales() {
    // Sumar rollos
    let subtotalRollos = 0;
    document.querySelectorAll('#rollos_container .product-item').forEach(item => {
        const precio = parseFloat(item.querySelector('.rollo-select').selectedOptions[0]?.dataset.precio) || 0;
        const cantidad = parseFloat(item.querySelector('input[type="number"]').value) || 0;
        subtotalRollos += precio * cantidad;
    });

    // Sumar productos
    let subtotalProductos = 0;
    document.querySelectorAll('#productos_container .product-item').forEach(item => {
        const precio = parseFloat(item.querySelector('.product-select').selectedOptions[0]?.dataset.precio) || 0;
        const cantidad = parseFloat(item.querySelector('input[type="number"]').value) || 0;
        subtotalProductos += precio * cantidad;
    });

    // Sumar extras
    let extras = 0;
    document.querySelectorAll('.extra-check:checked').forEach(ck => {
        extras += parseFloat(ck.dataset.precio);
    });

    // Sumar instalación
    let area = parseFloat(document.getElementById('area_total').value) || 0;
    let precioInstalacion = parseFloat(document.getElementById('precio_instalacion').value) || 0;
    let instalacion = area * precioInstalacion;

    // Calcular totales
    let totalSinIVA = subtotalRollos + subtotalProductos + extras + instalacion;
    let iva = totalSinIVA * 0.16;
    let totalConIVA = totalSinIVA + iva;

    document.getElementById('total_sin_iva').textContent = `$${totalSinIVA.toFixed(2)}`;
    document.getElementById('iva').textContent = `$${iva.toFixed(2)}`;
    document.getElementById('total_con_iva').textContent = `$${totalConIVA.toFixed(2)}`;
}

// Función para validar el formulario
function validarFormulario() {
    const cliente = document.getElementById('cliente');
    if (!cliente || !cliente.value) {
        alert('Seleccione un cliente');
        return false;
    }

    // Validar terreno
    const tipoTerreno = document.getElementById('tipo_terreno').value;
    if (!tipoTerreno) {
        alert('Seleccione el tipo de terreno');
        return false;
    }

    if (tipoTerreno === 'regular') {
        if (!document.getElementById('forma_terreno').value) {
            alert('Seleccione la forma del terreno');
            return false;
        }
        if (!document.getElementById('dimension1').value ||
            (document.getElementById('forma_terreno').value !== 'circulo' && !document.getElementById('dimension2').value)) {
            alert('Complete las dimensiones del terreno');
            return false;
        }
    } else {
        if (!document.getElementById('area_irregular').value) {
            alert('Ingrese el área estimada del terreno');
            return false;
        }
    }

    if (!document.getElementById('tipo_instalacion').value) {
        alert('Seleccione el tipo de instalación');
        return false;
    }

    // Validar al menos un rollo
    const rollos = document.querySelectorAll('#rollos_container .product-item');
    let rollosValidos = false;
    rollos.forEach(item => {
        if (item.querySelector('.rollo-select').value && item.querySelector('input[type="number"]').value) {
            rollosValidos = true;
        }
    });

    if (!rollosValidos) {
        alert('Agregue al menos un rollo de pasto válido');
        return false;
    }

    return true;
}

// Función para guardar cotización
function guardarCotizacion() {
    if (!validarFormulario()) return;

    displayPopUp();
    $('#iconAccion').html('<i class="fas fa-spinner fa-spin"></i>');
    $('#mensajeAccion').html('Guardando cotización...');
    $('#btnAccion').css('display', 'none');

    // Recolectar datos principales
    const datos = {
        id_cliente: $('#cliente').val(),
        tipo_terreno: $('#tipo_terreno').val(),
        forma_terreno: $('#forma_terreno').val(),
        dimension1: $('#dimension1').val(),
        dimension2: $('#dimension2').val(),
        area_total: $('#area_total').val(),
        tipo_instalacion: $('#tipo_instalacion').val(),
        garantia: $('#garantia').val(),
        precio_instalacion: parseFloat($('#precio_instalacion').val()) || 0,
        total: parseFloat($('#total_con_iva').text().replace('$', '')) || 0,

        rollos: [],
        productos: [],
        extras: []
    };

    $('#rollos_container .product-item').each(function () {
        const select = $(this).find('.rollo-select');
        const input = $(this).find('input[type="number"]');
        const precio = parseFloat(select.find('option:selected').data('precio')) || 0;
        const cantidad = parseFloat(input.val()) || 0;

    });

    $('#productos_container .product-item').each(function () {
        const select = $(this).find('.product-select');
        const input = $(this).find('input[type="number"]');
        const precio = parseFloat(select.find('option:selected').data('precio')) || 0;
        const cantidad = parseFloat(input.val()) || 0;

        if (select.val() && cantidad > 0) {
            datos.productos.push({
                id_producto: select.val(),
                cantidad: cantidad,
                precio_unitario: precio
            });
        }
    });

    $('.extra-check:checked').each(function () {
        datos.extras.push({
            id_extra: $(this).data('id'),
            precio: parseFloat($(this).data('precio')) || 0
        });
    });

    fetch('../../php/cotizaciones/guardar_cotizacion.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify(datos)
    })
    .then(response => {
        if (!response.ok) {
            throw new Error('Error en la respuesta del servidor');
        }
        return response.json();
    })
    .then(data => {
        if (data.status === 1) {
            displayMensajeExitoso(
                'Cotización guardada correctamente',
                `window.location.href = 'ver_cotizacion.php?id=${data.id_cotizacion}'`
            );
        } else {
            displayMensajeError(data.mensaje || 'Error desconocido al guardar la cotización');
        }
    })
    .catch(err => {
        console.error('Error:', err);
        displayMensajeError('Error al guardar la cotización: ' + err.message);
        $('#btnAccion').css('display', 'block');
    });
}
// Asignar eventos cuando el DOM esté listo
document.addEventListener('DOMContentLoaded', function () {
    // Eventos para terreno
    document.getElementById('tipo_terreno').addEventListener('change', toggleTerreno);
    document.getElementById('forma_terreno').addEventListener('change', calcularArea);
    document.getElementById('dimension1').addEventListener('change', calcularArea);
    document.getElementById('dimension2').addEventListener('change', calcularArea);
    document.getElementById('area_irregular').addEventListener('change', calcularArea);

    // Eventos para instalación
    document.getElementById('precio_instalacion').addEventListener('change', actualizarTotales);

    // Eventos para extras
    document.querySelectorAll('.extra-check').forEach(ck => {
        ck.addEventListener('change', actualizarTotales);
    });

    // Eventos para rollos existentes
    document.querySelectorAll('#rollos_container .rollo-select').forEach(select => {
        select.addEventListener('change', function () {
            actualizarRollos(this);
        });
    });
    document.querySelectorAll('#rollos_container input[type="number"]').forEach(input => {
        input.addEventListener('change', function () {
            actualizarRollos(this);
        });
    });

    // Eventos para productos existentes
    document.querySelectorAll('#productos_container .product-select').forEach(select => {
        select.addEventListener('change', function () {
            actualizarProductos(this);
        });
    });
    document.querySelectorAll('#productos_container input[type="number"]').forEach(input => {
        input.addEventListener('change', function () {
            actualizarProductos(this);
        });
    });

    // Botón guardar
    document.querySelector('.btnadd').addEventListener('click', guardarCotizacion);

    // Inicializar
    toggleTerreno();
    actualizarTotales();
});