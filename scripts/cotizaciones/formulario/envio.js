import { parametrosSistema } from '../core/parametros.js';
import { actualizarTotales } from '../core/totales.js';

// Función para validar el formulario antes de enviar
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

    const areaTerreno = parseFloat(document.getElementById('area_total').value) || 0;
    if (areaTerreno <= 0) {
        alert('El área del terreno debe ser mayor que cero');
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
        const formas = document.querySelectorAll('.forma-item');
        const areaIrregular = parseFloat(document.getElementById('area_irregular')?.value) || 0;
        
        if (formas.length === 0 && areaIrregular <= 0) {
            alert('Agregue formas para calcular el área o ingrese un área estimada');
            return false;
        }
    }

    if (!document.getElementById('tipo_instalacion').value) {
        alert('Seleccione el tipo de instalación');
        return false;
    }

    // Validar al menos un rollo con color seleccionado
    let rollosValidos = false;
    let areaRollosTotal = 0;

    document.querySelectorAll('#rollos_container .product-item').forEach(item => {
        const select = item.querySelector('.rollo-select');
        const input = item.querySelector('input[type="number"]');
        const colorSelect = item.querySelector('.color-select');

        if (select.value && input.value && colorSelect?.value) {
            rollosValidos = true;
            areaRollosTotal += parseFloat(input.value) || 0;
        }
    });

    if (!rollosValidos) {
        alert('Agregue al menos un rollo de pasto válido con color seleccionado');
        return false;
    }

    // Validar que el área de rollos no exceda el área del terreno
    if (areaRollosTotal > areaTerreno * 1.1) {
        if (!confirm(`El área de pasto (${areaRollosTotal.toFixed(2)} m²) es mayor que el área del terreno (${areaTerreno.toFixed(2)} m²). ¿Desea continuar?`)) {
            return false;
        }
    }

    return true;
}

// Función principal para guardar la cotización
async function guardarCotizacion() {
    if (!validarFormulario()) {
        return;
    }

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

    const areaTerreno = parseFloat(document.getElementById('area_total').value) || 0;
    const precioInst = parseFloat(document.getElementById('precio_instalacion').value) || 0;
    let totalSinIVA = 0;
    
    const rollos = [];
    document.querySelectorAll('#rollos_container .product-item').forEach(item => {
        const select = item.querySelector('.rollo-select');
        const input = item.querySelector('input[type="number"]');
        const colorSelect = item.querySelector('.color-select');

        if (select.value && input.value && colorSelect?.value) {
            const cantidad = parseFloat(input.value);
            rollos.push({
                id_producto: select.value,
                cantidad: cantidad,
                area_usada: cantidad,
                precio_unitario: parseFloat(select.selectedOptions[0].dataset.precio),
                id_color: colorSelect.value,
                color_nombre: colorSelect.options[colorSelect.selectedIndex].text
            });
        }
    });

    // Recolectar datos de productos
    const productos = [];
    document.querySelectorAll('#productos_container .product-item').forEach(item => {
        const select = item.querySelector('.product-select');
        const input = item.querySelector('input[type="number"]');

        if (select.value && input.value) {
            productos.push({
                id_producto: select.value,
                cantidad: parseFloat(input.value),
                precio_unitario: parseFloat(select.selectedOptions[0].dataset.precio)
            });
        }
    });

    // Recolectar extras seleccionados
    const extras = [];
    document.querySelectorAll('.extra-check:checked').forEach(ck => {
        extras.push({
            id_extra: ck.dataset.id,
            precio: parseFloat(ck.dataset.precio)
        });
    });

    // Calcular total
    let area = parseFloat(document.getElementById('area_total').value) || 0;

    // Sumar rollos
    rollos.forEach(rollo => {
        totalSinIVA += rollo.precio_unitario * rollo.cantidad;
    });

    // Sumar productos
    productos.forEach(producto => {
        totalSinIVA += producto.precio_unitario * producto.cantidad;
    });

    // Sumar extras
    extras.forEach(extra => {
        totalSinIVA += extra.precio;
    });

    // Sumar instalación
    totalSinIVA += areaTerreno * precioInst;

    // Preparar datos para enviar
    const datos = {
        id_cliente: document.getElementById('cliente').value,
        tipo_terreno: document.getElementById('tipo_terreno').value,
        forma_terreno: document.getElementById('forma_terreno').value,
        dimension1: document.getElementById('dimension1').value,
        dimension2: document.getElementById('dimension2').value,
        area_total: areaTerreno, 
        tipo_instalacion: document.getElementById('tipo_instalacion').value,
        garantia: document.getElementById('garantia').value,
        precio_instalacion: precioInst,
        total: totalSinIVA,
        rollos: rollos,
        productos: productos,
        extras: extras
    };

    try {
        const response = await fetch('../../php/cotizaciones/guardar_cotizacion.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: JSON.stringify(datos)
        });

        if (!response.ok) {
            throw new Error(`Error HTTP: ${response.status}`);
        }

        const data = await response.json();

        if (data.status === 1) {
            displayMensajeExitoso(
                'Cotización guardada correctamente',
                `window.location.href = 'lista.php';`
            );
        } else {
            displayMensajeError(data.mensaje || 'Error desconocido al guardar la cotización');
            $('#btnAccion').css('display', 'block');
        }
    } catch (err) {
        console.error('Error:', err);
        displayMensajeError(`Error al guardar: ${err.message}`);
        $('#btnAccion').css('display', 'block');
    }
}

export { guardarCotizacion, validarFormulario };