let parametrosSistema = {
    precioInstalacion: 0,
    garantiaDefault: 5,
    ivaPorcentaje: 0.16
};

function obtenerIdColorPorNombre(nombreColor) {
    // Colores de pasto
    const colores = {
        "Verde Claro": 1,
        "Verde Oscuro": 2,
        "Verde Olivo": 3,
        "Verde Pasto": 4,
        "Verde Limón": 5,
        "Verde Natural": 6,
        "Verde Primavera": 7,
        "Verde Jade": 8,
        "Verde Esmeralda": 9,
        "Verde Césped": 10
    };
    return colores[nombreColor] || null;
}

// Función para cargar parámetros del sistema
async function cargarParametrosSistema() {
    try {
        const response = await fetch('../../php/configuracion/obtener_parametros.php');
        const data = await response.json();

        if (data.status === 1) {
            parametrosSistema.precioInstalacion = parseFloat(data.parametros.precio_instalacion_m2) || 0;
            parametrosSistema.garantiaDefault = parseInt(data.parametros.garantia_default_anios) || 5;
            parametrosSistema.ivaPorcentaje = (parseFloat(data.parametros.iva_porcentaje) || 16) / 100;

            // Aplicar valores por defecto
            document.getElementById('precio_instalacion').value = parametrosSistema.precioInstalacion;
            document.getElementById('garantia').value = parametrosSistema.garantiaDefault;
        }
    } catch (error) {
        console.error('Error al cargar parámetros:', error);
    }
}

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
    const subtotal = item.querySelector('.product-price');
    const detalles = item.querySelector('.rollo-details');

    // Mostrar detalles si hay producto seleccionado
    if (select.value) {
        detalles.style.display = 'block';
        detalles.querySelector('.modelo-text').textContent = select.selectedOptions[0].dataset.modelo;
        detalles.querySelector('.colores-text').textContent = select.selectedOptions[0].dataset.colores;
        detalles.querySelector('.area-text').textContent = `${cantidad.value} m² disponibles`;

        // Limpiar cualquier selector de color existente
        const existingColorSelect = detalles.querySelector('.color-select');
        if (existingColorSelect) {
            existingColorSelect.remove();
        }

        // Permitir seleccionar el color del Rollo
        const coloresDisponibles = select.selectedOptions[0].dataset.colores.split(', ');
        const colorSelect = document.createElement('select');
        colorSelect.className = 'color-select textfield';
        colorSelect.setAttribute('required', 'true');

        // Agregar opción por defecto
        const defaultOption = document.createElement('option');
        defaultOption.value = '';
        defaultOption.textContent = '-- Selecciona Color --';
        colorSelect.appendChild(defaultOption);

        coloresDisponibles.forEach(color => {
            const option = document.createElement('option');
            option.value = color.trim();
            option.textContent = color.trim();
            option.dataset.id = obtenerIdColorPorNombre(color.trim()); // Asignar ID
            colorSelect.appendChild(option);
        });

        detalles.appendChild(colorSelect);
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
    item.querySelector('.product-price').textContent = '$0.00';
    item.querySelector('.rollo-details').style.display = 'none';

    // Limpiar cualquier selector de color existente
    const existingColorSelect = item.querySelector('.color-select');
    if (existingColorSelect) {
        existingColorSelect.remove();
    }

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
    const subtotal = item.querySelector('.product-price');

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
    item.querySelector('.product-price').textContent = '$0.00';

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

    // Calcular total sin IVA
    let totalSinIVA = subtotalRollos + subtotalProductos + extras + instalacion;

    // Calcular IVA y total con IVA usando los parámetros
    // let iva = totalSinIVA * parametrosSistema.ivaPorcentaje;
    // let totalConIVA = totalSinIVA + iva;

    // Actualizar la interfaz
    document.getElementById('total_sin_iva').textContent = `$${totalSinIVA.toFixed(2)}`;
    // document.getElementById('iva').textContent = `$${iva.toFixed(2)}`;
    // document.getElementById('total_con_iva').textContent = `$${totalConIVA.toFixed(2)}`;
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

    // Validar precio de instalación mínimo
    const precioInstalacion = parseFloat(document.getElementById('precio_instalacion').value);
    if (precioInstalacion < (parametrosSistema.precioInstalacion * 0.8)) {
        if (!confirm(`El precio de instalación es menor que el 80% del valor recomendado ($${parametrosSistema.precioInstalacion}). ¿Desea continuar?`)) {
            return;
        }
    }

    displayPopUp();
    $('#iconAccion').html('<i class="fas fa-spinner fa-spin"></i>');
    $('#mensajeAccion').html('Guardando cotización...');
    $('#btnAccion').css('display', 'none');

    // Calcular total correctamente
    let totalSinIVA = 0;
    const rollos = [];
    document.querySelectorAll('#rollos_container .product-item').forEach(item => {
        const select = item.querySelector('.rollo-select');
        const input = item.querySelector('input[type="number"]');
        const colorSelect = item.querySelector('.color-select'); // Nuevo selector de color
        
        if (select.value && input.value && colorSelect.value) {
            rollos.push({
                id_producto: select.value,
                cantidad: parseFloat(input.value),
                precio_unitario: parseFloat(select.selectedOptions[0].dataset.precio),
                id_color: colorSelect.value  // Color seleccionado
            });
        }
    });s

    document.querySelectorAll('#productos_container .product-item').forEach(item => {
        const precio = parseFloat(item.querySelector('.product-select').selectedOptions[0]?.dataset.precio) || 0;
        const cantidad = parseFloat(item.querySelector('input[type="number"]').value) || 0;
        totalSinIVA += precio * cantidad;
    });

    document.querySelectorAll('.extra-check:checked').forEach(ck => {
        totalSinIVA += parseFloat(ck.dataset.precio);
    });

    let area = parseFloat(document.getElementById('area_total').value) || 0;
    let precioInst = parseFloat(document.getElementById('precio_instalacion').value) || 0;
    totalSinIVA += area * precioInst;

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
        precio_instalacion: precioInst,
        total: totalSinIVA, // Usar el total calculado aquí
        rollos: [],
        productos: [],
        extras: []
    };

    $('#rollos_container .product-item').each(function () {
        const select = $(this).find('.rollo-select');
        const input = $(this).find('input[type="number"]');
        const colorSelect = $(this).find('.color-select');
        const precio = parseFloat(select.find('option:selected').data('precio')) || 0;
        const cantidad = parseFloat(input.val()) || 0;
        const id_color = obtenerIdColorPorNombre(colorSelect.val()); // Función nueva

        if (select.val() && cantidad > 0 && id_color) {
            datos.rollos.push({
                id_producto: select.val(),
                cantidad: cantidad,
                precio_unitario: precio,
                id_color: id_color,
                cantidad_rollos: Math.ceil(cantidad / areaPorRollo) // Calcula cuántos rollos completos necesita
            });
        }
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
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest'
        },
        body: JSON.stringify(datos)
    })
        .then(response => {
            // Primero verificar si la respuesta es JSON
            const contentType = response.headers.get('content-type');
            if (!contentType || !contentType.includes('application/json')) {
                return response.text().then(text => {
                    throw new Error(`Respuesta no JSON: ${text}`);
                });
            }
            return response.json();
        })
        .then(data => {
            if (data.status === 1) {
                displayMensajeExitoso(
                    'Cotización guardada correctamente',
                    `window.location.href = 'lista.php';`
                );
            } else {
                displayMensajeError(data.mensaje || 'Error desconocido al guardar la cotización');
            }
        })
        .catch(err => {
            console.error('Error:', err);
            displayMensajeError(`Error al guardar: ${err.message}`);
            $('#btnAccion').css('display', 'block');
        });
}

function cargarColoresRollos(selectElement) {
    const productoId = selectElement.value;
    const contenedor = selectElement.closest('.product-item');
    const colorSelect = contenedor.querySelector('.color-select');
    
    // Limpiar selector
    colorSelect.innerHTML = '<option value="">-- Selecciona color --</option>';
    
    if (!productoId) return;
    
    // Obtener colores disponibles desde el backend
    fetch(`../../php/inventario/obtener_colores.php?id_producto=${productoId}`)
        .then(response => response.json())
        .then(colores => {
            colores.forEach(color => {
                const option = document.createElement('option');
                option.value = color.id;
                option.textContent = color.nombre;
                colorSelect.appendChild(option);
            });
        });
}

// Asignar eventos cuando el DOM esté listo
document.addEventListener('DOMContentLoaded', async function () {
    await cargarParametrosSistema();

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

    // Inicializar
    toggleTerreno();
    actualizarTotales();
});