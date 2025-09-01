/**
 * Maneja la lógica de la página de edición de cantidad de inventario
 * Incluye:
 * - Control de tabs (lotes/historial)
 * - Validación y envío del formulario de movimientos
 */

document.addEventListener('DOMContentLoaded', function() {
    initTabs();
    initFormMovimiento();
});

/**
 * Inicializa el sistema de tabs para alternar entre lotes e historial
 */
function initTabs() {
    const tabsInventario = document.querySelectorAll('.tab-inventario');
    if (tabsInventario.length > 0) {
        tabsInventario.forEach(tab => {
            tab.addEventListener('click', () => {
                // Remover clase active de todos los tabs y contenidos
                document.querySelectorAll('.tab-inventario').forEach(t => t.classList.remove('active'));
                document.querySelectorAll('.tab-inventario-content').forEach(c => c.classList.remove('active'));

                // Activar el tab clickeado
                tab.classList.add('active');
                const tabId = tab.getAttribute('data-tab');
                const content = document.getElementById(tabId);
                if (content) content.classList.add('active');
            });
        });
    }
}

/**
 * Inicializa el formulario de movimiento de inventario
 */
function initFormMovimiento() {
    const form = document.getElementById('formMovimientoInventario');
    if (!form) return;

    // Mostrar/ocultar campo de costo según tipo de movimiento
    const tipoMovimientoSelect = form.querySelector('[name="tipo_movimiento"]');
    const costoUnitarioInput = form.querySelector('[name="costo_unitario"]');
    const costoUnitarioField = costoUnitarioInput.closest('.grid-item');
    const descripcionField = form.querySelector('[name="lote_descripcion"]').closest('.grid-item');
    const comentarioField = form.querySelector('[name="motivo"]').closest('.grid-item');
    const loteOpcionField = form.querySelector('[name="id_lote"]').closest('.grid-item');

    function updateCostoUnitarioRequired() {
        if (tipoMovimientoSelect.value === 'salida') {
            costoUnitarioField.style.display = 'none';
            costoUnitarioInput.required = false;
        } else {
            costoUnitarioField.style.display = 'flex';
            costoUnitarioInput.required = true;
        }
    }

    tipoMovimientoSelect.addEventListener('change', function() {
        if (this.value === 'salida') {
            costoUnitarioField.style.display = 'none';
            costoUnitarioInput.required = false;
            descripcionField.style.display = 'none';
            comentarioField.className = 'grid-item grid-item-full';

            // si es salida, quitar la opcion nuevo lote
            const nuevoLoteOption = loteOpcionField.querySelector('option[value="nuevo"]');
            if (nuevoLoteOption) {
                nuevoLoteOption.remove();
            }

        } else {
            costoUnitarioField.style.display = 'flex';
            costoUnitarioInput.required = true;
            descripcionField.style.display = 'flex';
            comentarioField.className = 'grid-item ';

            // si es entrada, agregar la opcion nuevo lote
            const select = form.querySelector('[name="id_lote"]');
            if (select && !select.querySelector('option[value="nuevo"]')) {
                const nuevoOption = document.createElement('option');
                nuevoOption.value = 'nuevo';
                nuevoOption.textContent = 'Nuevo lote';
                select.appendChild(nuevoOption);
            }
        }
    });

    updateCostoUnitarioRequired();

    if (tipoMovimientoSelect.value === 'salida') {
        costoUnitarioField.style.display = 'none';
    }

    form.addEventListener('submit', function(e) {
        e.preventDefault();
        handleFormMovimientoSubmit(this);
    });
}


function handleFormMovimientoSubmit(form) {
    displayPopUp('Procesando movimiento...');
    
    const formData = new FormData(form);
    const cantidad = parseFloat(formData.get('cantidad'));
    const costo = parseFloat(formData.get('costo_unitario') || 0);
    const tipoMovimiento = formData.get('tipo_movimiento');
    
    if (!validateFormMovimiento(cantidad, costo, tipoMovimiento)) {
        hidePopup();
        return;
    }
    
    if (tipoMovimiento === 'salida') {
        const disponible = parseFloat(form.dataset.disponible || 0);
        if (cantidad > disponible) {
            displayPopUp();
            displayMensajeError(`No hay suficiente inventario. Disponible: ${disponible}`);
            return;
        }
        
        if (!confirm(`¿Está seguro de quitar ${cantidad} del inventario?`)) {
            hidePopup();
            return;
        }
    } else {
        if (!confirm(`¿Confirmar entrada de ${cantidad} unidades al inventario con costo unitario de $${costo.toFixed(2)}?`)) {
            hidePopup();
            return;
        }
    }
    
    const btn = form.querySelector('button[type="submit"]');
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Procesando...';
    
    fetch(form.action, {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.status === 1) {
            displayMensajeExitoso(data.mensaje, "window.location.reload()");
        } else {
            throw new Error(data.mensaje || "Error al guardar");
        }
    })
    .catch(error => {
        console.error("Error:", error);
        displayMensajeError(error.message);
    })
    .finally(() => {
        btn.disabled = false;
        btn.innerHTML = '<i class="fas fa-save"></i> Guardar movimiento';
    });
}

function validateFormMovimiento(cantidad, costo, tipoMovimiento) {
    if (isNaN(cantidad)) {
        displayPopUp();
        displayMensajeError("La cantidad debe ser un número válido");
        return false;
    }
    
    if (cantidad <= 0) {
        displayPopUp();
        displayMensajeError("La cantidad debe ser mayor a cero");
        return false;
    }
    
    if (tipoMovimiento === 'entrada') {
        if (isNaN(costo)) {
            displayPopUp();
            displayMensajeError("El costo unitario debe ser un número válido");
            return false;
        }
        
        if (costo <= 0) {
            displayPopUp();
            displayMensajeError("El costo unitario debe ser mayor a cero");
            return false;
        }
    }
    
    return true;
}
