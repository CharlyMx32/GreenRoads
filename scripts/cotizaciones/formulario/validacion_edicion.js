/**
 * Validaciones específicas para el modo edición de cotizaciones
 */

/**
 * Valida que los datos de edición sean correctos
 */
export function validarEdicion() {
    const errores = [];

    // Validar cliente
    const cliente = document.getElementById('cliente');
    if (!cliente || !cliente.value) {
        errores.push('Debe seleccionar un cliente');
    }

    // Validar terreno
    const tipoTerreno = document.getElementById('tipo_terreno').value;
    if (!tipoTerreno) {
        errores.push('Debe seleccionar el tipo de terreno');
    }

    const areaTotal = parseFloat(document.getElementById('area_total').value) || 0;
    if (areaTotal <= 0) {
        errores.push('El área del terreno debe ser mayor que cero');
    }

    // Validar instalación
    const tipoInstalacion = document.getElementById('tipo_instalacion').value;
    if (!tipoInstalacion) {
        errores.push('Debe seleccionar el tipo de instalación');
    }

    // Nota: La garantía no se valida en edición porque no es modificable

    // Validar rollos
    const rollosValidos = validarRollosEdicion();
    if (!rollosValidos.valido) {
        errores.push(rollosValidos.mensaje);
    }

    // Validar coherencia de área
    const areaRollos = calcularAreaTotalRollos();
    if (areaRollos > areaTotal * 1.15) { // 15% de tolerancia
        errores.push(`El área de pasto (${areaRollos.toFixed(2)} m²) excede significativamente el área del terreno (${areaTotal.toFixed(2)} m²)`);
    }

    return {
        valido: errores.length === 0,
        errores: errores
    };
}

/**
 * Valida los rollos en modo edición
 */
function validarRollosEdicion() {
    const rollos = document.querySelectorAll('#rollos-container .product-item');
    
    if (rollos.length === 0) {
        return {
            valido: false,
            mensaje: 'Debe agregar al menos un rollo de pasto'
        };
    }

    let rollosValidos = 0;
    
    for (const rollo of rollos) {
        const select = rollo.querySelector('.rollo-select');
        const input = rollo.querySelector('input[type="number"]');
        const colorSelect = rollo.querySelector('.color-select');

        if (select && select.value && input && input.value && colorSelect && colorSelect.value) {
            const cantidad = parseFloat(input.value);
            if (cantidad > 0) {
                rollosValidos++;
            }
        }
    }

    if (rollosValidos === 0) {
        return {
            valido: false,
            mensaje: 'Debe tener al menos un rollo válido con color seleccionado'
        };
    }

    return { valido: true };
}

/**
 * Calcula el área total de todos los rollos
 */
function calcularAreaTotalRollos() {
    let areaTotal = 0;
    
    document.querySelectorAll('#rollos-container .product-item').forEach(item => {
        const input = item.querySelector('input[type="number"]');
        if (input && input.value) {
            areaTotal += parseFloat(input.value) || 0;
        }
    });

    return areaTotal;
}

/**
 * Valida que los cambios sean significativos para justificar la actualización
 */
export function validarCambiosSignificativos() {
    // Esta función podría comparar valores originales vs actuales
    // Por ahora, asumimos que cualquier edición es válida
    return true;
}

/**
 * Muestra errores de validación al usuario
 */
export function mostrarErroresValidacion(errores) {
    if (!errores || errores.length === 0) return;

    const mensaje = 'Se encontraron los siguientes errores:\n\n' + 
                   errores.map((error, index) => `${index + 1}. ${error}`).join('\n');
    
    alert(mensaje);
}

/**
 * Validación completa para edición
 */
export function validarFormularioEdicion() {
    const resultadoValidacion = validarEdicion();
    
    if (!resultadoValidacion.valido) {
        mostrarErroresValidacion(resultadoValidacion.errores);
        return false;
    }

    if (!validarCambiosSignificativos()) {
        alert('No se detectaron cambios significativos en la cotización');
        return false;
    }

    return true;
}
