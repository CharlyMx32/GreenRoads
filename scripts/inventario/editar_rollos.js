/**
 * Manejo de la interfaz de edición de rollos (solo entradas)
 */

document.addEventListener('DOMContentLoaded', function () {
    initTabs();
    initCalculoCosto();
    initFormularioRollos();
    initCalculoArea();
    initManejoLotes();
    initSelectorColores();
});

/**
 * Inicializa el sistema de tabs
 */
function initTabs() {
    const tabs = document.querySelectorAll('.tab-inventario');
    tabs.forEach(tab => {
        tab.addEventListener('click', function () {
            document.querySelectorAll('.tab-inventario').forEach(t => t.classList.remove('active'));
            document.querySelectorAll('.tab-inventario-content').forEach(c => c.classList.remove('active'));

            this.classList.add('active');
            const tabId = this.getAttribute('data-tab');
            document.getElementById(tabId).classList.add('active');
        });
    });
}

/**
 * Inicializa el cálculo automático de área
 */
function initCalculoArea() {
    const largoInput = document.querySelector('[name="largo"]');
    const anchoInput = document.querySelector('[name="ancho"]');

    function calcularArea() {
        const largo = parseFloat(largoInput.value) || 0;
        const ancho = parseFloat(anchoInput.value) || 0;
        const area = largo * ancho;
    }

    if (largoInput && anchoInput) {
        largoInput.addEventListener('input', calcularArea);
        anchoInput.addEventListener('input', calcularArea);
    }
}

/**
 * Inicializa el formulario principal de rollos
 */
function initFormularioRollos() {
    const form = document.querySelector('.formulario-inputs');
    if (!form) return;

    form.addEventListener('submit', function (e) {
        e.preventDefault();
        handleFormRollosSubmit(this);
    });
}

/**
 * Maneja el envío del formulario de rollos
 */
function handleFormRollosSubmit(form) {
    const cantidad = parseFloat(form.querySelector('[name="cantidad"]').value);
    const idLote = form.querySelector('[name="id_lote"]').value;

    if (!validateFormRollos(cantidad)) {
        return;
    }

    if (idLote === 'nuevo' && !form.querySelector('[name="lote_descripcion"]').value.trim()) {
        displayMensajeError("Debe proporcionar una descripción para el nuevo lote");
        return;
    }

    displayPopUp('Procesando movimiento...');
    const btn = form.querySelector('button[type="submit"]');
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Procesando...';

    fetch(form.action, {
        method: 'POST',
        body: new FormData(form)
    })
    .then(response => response.json())
    .then(data => {
        if (data.status === 1) {
            displayMensajeExitoso(data.mensaje, () => {
                window.location.reload();
            });
        } else {
            throw new Error(data.mensaje || "Error al procesar el movimiento");
        }
    })
    .catch(error => {
        console.error("Error:", error);
        displayMensajeError(error.message || "Ocurrió un error al procesar la solicitud.");
    })
    .finally(() => {
        btn.disabled = false;
        btn.innerHTML = '<i class="fas fa-plus-circle"></i> Agregar Rollos';
    });
}

/**
 * Valida los datos del formulario de rollos
 */
function validateFormRollos(cantidad) {
    if (isNaN(cantidad) || cantidad <= 0) {
        displayMensajeError("La cantidad debe ser mayor a cero");
        return false;
    }
    return true;
}

/**
 * Inicializa el manejo de lotes
 */
function initManejoLotes() {
    const loteSelect = document.querySelector('[name="id_lote"]');
    const loteDescGroup = document.getElementById('lote-desc-group');
    
    if (loteSelect && loteDescGroup) {
        loteSelect.addEventListener('change', function() {
            const showDesc = this.value === 'nuevo';
            loteDescGroup.style.display = showDesc ? 'flex' : 'none';
        });
    }
}

/**
 * Inicializa el cálculo de costo total
 */
function initCalculoCosto() {
    const largoInput = document.querySelector('[name="largo"]');
    const anchoInput = document.querySelector('[name="ancho"]');
    const costoInput = document.querySelector('[name="costo_unitario"]');
    const cantidadInput = document.querySelector('[name="cantidad"]');
    const costoTotalDisplay = document.getElementById('costo-total-display');

    function calcularCostoTotal() {
        const largo = parseFloat(largoInput.value) || 0;
        const ancho = parseFloat(anchoInput.value) || 0;
        const costo = parseFloat(costoInput.value) || 0;
        const cantidad = parseInt(cantidadInput.value) || 0;

        const area = largo * ancho;
        const costoTotal = costo * cantidad;

        if (costoTotalDisplay) {
            costoTotalDisplay.textContent = `Costo total: $${costoTotal.toFixed(2)}`;
        }
    }

    [largoInput, anchoInput, costoInput, cantidadInput].forEach(input => {
        if (input) input.addEventListener('input', calcularCostoTotal);
    });
}

/**
 * Inicializa el selector de colores
 */
function initSelectorColores() {
    const dropdown = document.getElementById('dropdown-colores');
    const btnCambiar = document.querySelector('.btn-cambiar-color');

    if (btnCambiar && dropdown) {
        btnCambiar.addEventListener('click', function (e) {
            e.stopPropagation();
            dropdown.classList.toggle('show');
        });

        document.addEventListener('click', function () {
            dropdown.classList.remove('show');
        });
    }
}