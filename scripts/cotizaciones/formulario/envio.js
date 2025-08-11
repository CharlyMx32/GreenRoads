import { parametrosSistema, obtenerPrecioPorM2 } from '../core/parametros.js';
import { actualizarTotales } from '../core/totales.js';

function validarFormulario() {
    const cliente = document.getElementById('cliente');
    if (!cliente || !cliente.value) {
        alert('Seleccione un cliente');
        return false;
    }

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

    if (areaRollosTotal > areaTerreno * 1.1) {
        if (!confirm(`El área de pasto (${areaRollosTotal.toFixed(2)} m²) es mayor que el área del terreno (${areaTerreno.toFixed(2)} m²). ¿Desea continuar?`)) {
            return false;
        }
    }

    const rollos = document.querySelectorAll('#rollos_container .product-item');
    if (rollos.length === 0) {
        alert('Agregue al menos un rollo de pasto');
        return false;
    }

    rollos.forEach(item => {
        const cantidadInput = item.querySelector('input[name*="[cantidad]"]');
        if (cantidadInput && cantidadInput.value) {
            areaRollosTotal += parseFloat(cantidadInput.value);
        }
    });

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

    // Verificar si es modo edición
    const modoEdicion = window.modoEdicion || false;
    const idCotizacion = window.idCotizacion || null;

    // Obtener parámetros del sistema
    const garantia = document.getElementById('garantia')?.value || parametrosSistema.garantiaDefault;
    const areaTerreno = parseFloat(document.getElementById('area_total').value) || 0;
    const tipoInstalacion = document.getElementById('tipo_instalacion').value;
    const precioInstalacion = obtenerPrecioPorM2(areaTerreno, tipoInstalacion);

    displayPopUp();
    $('#iconAccion').html('<i class="fas fa-spinner fa-spin"></i>');
    $('#mensajeAccion').html(modoEdicion ? 'Actualizando cotización...' : 'Guardando cotización...');
    $('#btnAccion').css('display', 'none');

    // Recolectar datos de rollos
    const rollos = [];
    document.querySelectorAll('#rollos_container .product-item').forEach(item => {
        const select = item.querySelector('.rollo-select');
        const input = item.querySelector('input[type="number"]');
        const colorSelect = item.querySelector('.color-select');

        if (select.value && input.value && colorSelect?.value) {
            const cantidad = parseFloat(input.value);
            const precioUnitario = parseFloat(select.selectedOptions[0].dataset.precio);
            
            rollos.push({
                id_producto: parseInt(select.value),
                cantidad: cantidad,
                precio_unitario: precioUnitario,
                id_color: parseInt(colorSelect.value),
                subtotal: cantidad * precioUnitario
            });
        }
    });

    // Recolectar datos de productos
    const productos = [];
    document.querySelectorAll('#productos_container .product-item').forEach(item => {
        const select = item.querySelector('.product-select');
        const input = item.querySelector('input[type="number"]');

        if (select.value && input.value) {
            const cantidad = parseFloat(input.value);
            const precioUnitario = parseFloat(select.selectedOptions[0].dataset.precio);
            
            productos.push({
                id_producto: parseInt(select.value),
                cantidad: cantidad,
                precio_unitario: precioUnitario,
                subtotal: cantidad * precioUnitario
            });
        }
    });

    // Recolectar extras seleccionados
    const extras = [];
    document.querySelectorAll('.extra-check:checked').forEach(ck => {
        extras.push({
            id_extra: parseInt(ck.dataset.id),
            precio: parseFloat(ck.dataset.precio)
        });
    });

    // Obtener total actual del DOM
    const totalDisplay = document.getElementById('total').textContent;
    const total = parseFloat(totalDisplay.replace('$', '').replace(',', '')) || 0;

    // Preparar datos para enviar
    const datos = {
        id_cliente: parseInt(document.getElementById('cliente').value),
        tipo_terreno: document.getElementById('tipo_terreno').value,
        tipo_instalacion: tipoInstalacion,
        garantia_anios: parseInt(garantia),
        precio_instalacion_m2: precioInstalacion,
        area_total: areaTerreno,
        total: total,
        rollos: rollos,
        materiales: productos, // Para compatibilidad con el backend
        extras: extras,
        dibujo_terreno: null
    };

    // Agregar campos específicos del terreno si es regular
    if (datos.tipo_terreno === 'regular') {
        datos.forma_terreno = document.getElementById('forma_terreno')?.value;
        datos.dimension1 = document.getElementById('dimension1')?.value;
        datos.dimension2 = document.getElementById('dimension2')?.value;
    }

    // Agregar el dibujo del canvas si existe
    if (window.canvasData) {
        datos.dibujo_terreno = window.canvasData;
    } else if (window.canvasTerreno && window.canvasTerreno.hasContent()) {
        datos.dibujo_terreno = window.canvasTerreno.getCanvasAsBase64();
    }

    // Si es modo edición, agregar ID de cotización
    if (modoEdicion && idCotizacion) {
        datos.id_cotizacion = idCotizacion;
    }

    try {
        // Seleccionar endpoint según el modo
        const endpoint = modoEdicion 
            ? '../../php/cotizaciones/actualizar_cotizacion.php'
            : '../../php/cotizaciones/guardar_cotizacion.php';

        const response = await fetch(endpoint, {
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
            const mensaje = modoEdicion 
                ? 'Cotización actualizada correctamente'
                : 'Cotización guardada correctamente';
                
            displayMensajeExitoso(
                mensaje,
                `window.location.href = 'lista.php';`
            );
        } else {
            displayMensajeError(data.mensaje || 'Error desconocido al procesar la cotización');
            $('#btnAccion').css('display', 'block');
        }
    } catch (err) {
        console.error('Error:', err);
        displayMensajeError(`Error al procesar: ${err.message}`);
        $('#btnAccion').css('display', 'block');
    }
}

export { guardarCotizacion, validarFormulario };