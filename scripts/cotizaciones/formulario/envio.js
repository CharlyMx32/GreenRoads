import { parametrosSistema, obtenerPrecioPorM2 } from '../core/parametros.js';
import { actualizarTotales } from '../core/totales.js';

// Función de validación que detecta el modo automáticamente
function validarFormulario() {
    const modoEdicion = window.modoEdicion || false;
    
    if (modoEdicion) {
        // Usar validación específica para edición si está disponible
        if (typeof window.validarFormularioEdicion === 'function') {
            return window.validarFormularioEdicion();
        }
    }
    
    // Validación estándar
    return validarFormularioEstandar();
}

function validarFormularioEstandar() {
    // Validar cliente - ahora usamos el campo oculto
    const clienteId = document.getElementById('cliente');
    if (!clienteId || !clienteId.value) {
        alert('Seleccione un cliente');
        if (window.selectorClientes) {
            document.getElementById('cliente-search').focus();
        }
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

    // Validar rollos - ahora sin input de cantidad manual
    let rollosValidos = false;
    const rollosContainer = document.getElementById('rollos_container');
    
    if (rollosContainer) {
        const rollosItems = rollosContainer.querySelectorAll('.product-item');
        
        rollosItems.forEach(item => {
            const select = item.querySelector('.rollo-select');
            const colorSelect = item.querySelector('.color-select');

            if (select?.value && colorSelect?.value) {
                rollosValidos = true;
            }
        });
    }

    if (!rollosValidos) {
        alert('Agregue al menos un rollo de pasto válido con color seleccionado');
        return false;
    }

    return true;
}

// Función principal para guardar la cotización
async function guardarCotizacion() {
    if (!validarFormulario()) {
        return;
    }

    // Verificar si está en modo comparativo y confirmar
    const modoComparativo = document.getElementById('resumen-comparativo');
    const esComparativa = modoComparativo && modoComparativo.style.display !== 'none';
    
    if (esComparativa) {
        const totalA = document.getElementById('total-a')?.textContent || '$0.00';
        const totalB = document.getElementById('total-b')?.textContent || '$0.00';
        const nombreA = document.querySelector('.nombre-rollo-a')?.textContent || 'Opción A';
        const nombreB = document.querySelector('.nombre-rollo-b')?.textContent || 'Opción B';
        
        const confirmar = confirm(
            `COTIZACIÓN COMPARATIVA DETECTADA\n\n` +
            `Opción A: ${nombreA} - ${totalA}\n` +
            `Opción B: ${nombreB} - ${totalB}\n\n` +
            `Al aceptar la cotización se preguntará cuál opción escogió el cliente.\n\n` +
            `¿Desea continuar?`
        );
        
        if (!confirmar) {
            return;
        }
    }

    // Verificar si es modo edición
    const modoEdicion = window.modoEdicion || false;
    const idCotizacion = window.idCotizacion || null;

    // Obtener parámetros del sistema
    const garantiaElement = document.getElementById('garantia');
    const garantia = modoEdicion ? 
        (garantiaElement ? parseInt(garantiaElement.value) : parametrosSistema.garantiaDefault) : 
        (garantiaElement?.value || parametrosSistema.garantiaDefault);
    
    const areaTerreno = parseFloat(document.getElementById('area_total').value) || 0;
    const tipoInstalacion = document.getElementById('tipo_instalacion').value;
    const precioInstalacion = obtenerPrecioPorM2(areaTerreno, tipoInstalacion);

    displayPopUp();
    $('#iconAccion').html('<i class="fas fa-spinner fa-spin"></i>');
    $('#mensajeAccion').html(modoEdicion ? 'Actualizando cotización...' : 'Guardando cotización...');
    $('#btnAccion').css('display', 'none');

    const rollos = [];
    const rollosContainer = modoEdicion ? '#rollos-container' : '#rollos_container';
    const rollosItems = document.querySelectorAll(`${rollosContainer} .product-item`);
    
    rollosItems.forEach((item, index) => {
        const select = item.querySelector('.rollo-select');
        const colorSelect = item.querySelector('.color-select');

        if (select?.value && colorSelect?.value && areaTerreno > 0) {
            // Usar el área total del terreno como cantidad
            const cantidad = areaTerreno;
            const precioUnitario = parseFloat(select.selectedOptions[0]?.dataset.precio) || 0;
            
            // En modo comparativo, marcar las opciones como A y B
            const opcionComparativa = esComparativa ? (index === 0 ? 'A' : 'B') : null;
            
            rollos.push({
                id_producto: parseInt(select.value),
                cantidad: cantidad,
                precio_unitario: precioUnitario,
                id_color: parseInt(colorSelect.value),
                subtotal: cantidad * precioUnitario,
                opcion_comparativa: opcionComparativa
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

    // Obtener total actual del DOM - manejar modo comparativo
    let total = 0;
    const resumenComparativo = document.getElementById('resumen-comparativo');
    const resumenNormal = document.getElementById('resumen-normal');
    
    if (esComparativa) {
        // Modo comparativo - usar el promedio de ambas opciones o la opción A como referencia
        const totalA = document.getElementById('total-a');
        if (totalA) {
            total = parseFloat(totalA.textContent.replace('$', '').replace(',', '')) || 0;
        }
    } else if (resumenNormal && resumenNormal.style.display !== 'none') {
        // Modo normal
        const totalDisplay = document.getElementById('total');
        if (totalDisplay) {
            total = parseFloat(totalDisplay.textContent.replace('$', '').replace(',', '')) || 0;
        }
    }

    // Preparar datos para enviar
    const datos = {
        id_cliente: parseInt(document.getElementById('cliente').value),
        tipo_terreno: document.getElementById('tipo_terreno').value,
        tipo_instalacion: tipoInstalacion,
        garantia_anios: parseInt(garantia),
        garantia: parseInt(garantia), 
        precio_instalacion_m2: precioInstalacion,
        precio_instalacion: precioInstalacion,
        area_total: areaTerreno,
        total: total,
        aplicar_iva: document.getElementById('aplicar_iva')?.checked || false,
        es_comparativa: esComparativa ? 1 : 0, // Nuevo campo
        rollos: rollos,
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