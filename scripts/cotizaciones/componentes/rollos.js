import { actualizarTotales } from '../core/totales.js';

function actualizarRollos(element) {
    console.log('actualizarRollos ejecutado');
    const item = element.closest('.product-item');
    if (!item) return;

    const select = item.querySelector('.rollo-select');
    const cantidad = item.querySelector('input[type="number"]');
    const subtotal = item.querySelector('.product-price');
    const detalles = item.querySelector('.rollo-details');

    if (!select || !cantidad || !detalles) return;

    if (select.value) {
        detalles.style.display = 'block';
        
        const modeloText = detalles.querySelector('.modelo-text');
        const coloresText = detalles.querySelector('.colores-text');
        const areaText = detalles.querySelector('.area-text');
        
        if (modeloText) modeloText.textContent = select.selectedOptions[0].dataset.modelo || '';
        if (coloresText) coloresText.textContent = select.selectedOptions[0].dataset.colores || '';
        if (areaText) areaText.textContent = `${cantidad.value} m² disponibles`;

        let colorSelectContainer = detalles.querySelector('.color-select-container');
        if (!colorSelectContainer) {
            colorSelectContainer = document.createElement('div');
            colorSelectContainer.className = 'color-select-container';
            colorSelectContainer.style.marginTop = '10px';
            detalles.appendChild(colorSelectContainer);
        }

        let colorSelect = detalles.querySelector('.color-select');
        if (!colorSelect) {
            colorSelect = document.createElement('select');
            colorSelect.style.display = 'block';
            colorSelect.className = 'color-select textfield';
            colorSelect.innerHTML = '<option value="">-- Selecciona color --</option>';
            colorSelectContainer.appendChild(colorSelect);
        }

        cargarColoresRollos(select);
    } else {
        detalles.style.display = 'none';
    }

    const precio = parseFloat(select.selectedOptions[0]?.dataset.precio) || 0;
    const cant = parseFloat(cantidad.value) || 0;
    if (subtotal) subtotal.textContent = `$${(precio * cant).toFixed(2)}`;

    actualizarTotales();
}

function cargarColoresRollos(selectElement) {
    if (!selectElement) return;

    const productoId = selectElement.value;
    const contenedor = selectElement.closest('.product-item');
    const colorSelect = contenedor?.querySelector('.color-select');

    if (!colorSelect) return;

    colorSelect.innerHTML = '<option value="">-- Selecciona color --</option>';

    if (!productoId) return;

    const loadingMsg = document.createElement('div');
    loadingMsg.textContent = 'Cargando colores...';
    loadingMsg.className = 'loading-msg';
    colorSelect.parentNode.appendChild(loadingMsg);

    fetch(`../../php/cotizaciones/obtener_colores.php?id_producto=${productoId}`)
        .then(response => {
            if (!response.ok) {
                throw new Error('Error en la respuesta del servidor');
            }
            return response.json();
        })
        .then(colores => {
            if (loadingMsg.parentNode) {
                loadingMsg.parentNode.removeChild(loadingMsg);
            }

            if (!Array.isArray(colores)) {
                console.error('Respuesta inesperada del servidor:', colores);
                return;
            }
            
            colores.forEach(color => {
                const option = document.createElement('option');
                option.value = color.id;
                option.textContent = color.nombre;
                colorSelect.appendChild(option);
            });
            
            colorSelect.style.display = 'block';
        })
        .catch(error => {
            console.error('Error al cargar colores:', error);
            const errorMsg = document.createElement('div');
            errorMsg.className = 'error-msg';
            errorMsg.textContent = 'Error al cargar colores. Intente nuevamente.';
            colorSelect.parentNode.appendChild(errorMsg);
        });
}

function agregarRollo() {
    const container = document.getElementById('rollos_container');
    const template = container.querySelector('.product-item');
    const item = template.cloneNode(true);

    const select = item.querySelector('.rollo-select');
    const input = item.querySelector('input[type="number"]');
    const price = item.querySelector('.product-price');
    const details = item.querySelector('.rollo-details');
    
    select.value = '';
    input.value = '';
    if (price) price.textContent = '$0.00';
    if (details) {
        details.style.display = 'none';
        
        const colorContainer = details.querySelector('.color-select-container');
        if (colorContainer) colorContainer.remove();
    }

    select.addEventListener('change', function() {
        actualizarRollos(this);
    });
    
    input.addEventListener('input', function() {
        actualizarRollos(this);
    });

    container.appendChild(item);
}

function removerRollo(btn) {
    const container = document.getElementById('rollos_container');
    if (container.querySelectorAll('.product-item').length > 1) {
        btn.closest('.product-item').remove();
        actualizarTotales();
    } else {
        alert('Debe haber al menos un rollo en la cotización.');
    }
}

export { actualizarRollos, agregarRollo, removerRollo, cargarColoresRollos };