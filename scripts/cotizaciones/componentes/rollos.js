import { actualizarTotales, actualizarTotalesComparativo } from '../core/totales.js';

function actualizarRollos(element) {
    console.log('actualizarRollos ejecutado');
    const item = element.closest('.product-item');
    if (!item) return;

    const select = item.querySelector('.rollo-select');
    const areaSpan = item.querySelector('.area-automatica');
    const subtotal = item.querySelector('.product-price');
    const detalles = item.querySelector('.rollo-details');

    if (!select || !areaSpan || !detalles) return;

    // Obtener área total del terreno
    const areaTotalInput = document.getElementById('area_total');
    const areaTotal = areaTotalInput ? parseFloat(areaTotalInput.value) || 0 : 0;

    // Actualizar el área automáticamente
    areaSpan.textContent = `${areaTotal} m²`;

    if (select.value && areaTotal > 0) {
        detalles.style.display = 'block';
        
        const modeloText = detalles.querySelector('.modelo-text');
        const coloresText = detalles.querySelector('.colores-text');
        const areaText = detalles.querySelector('.area-text');
        
        if (modeloText) modeloText.textContent = select.selectedOptions[0].dataset.modelo || '';
        if (coloresText) coloresText.textContent = select.selectedOptions[0].dataset.colores || '';
        if (areaText) areaText.textContent = `${areaTotal} m²`;

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
            
            colorSelect.addEventListener('change', function() {
                calcularCostoCompleto(item);
            });
        }

        cargarColoresRollos(select);
    } else {
        detalles.style.display = 'none';
        areaSpan.textContent = '0 m²';
    }

    calcularCostoCompleto(item);
    verificarModoComparativo();
}

function calcularCostoCompleto(item) {
    const select = item.querySelector('.rollo-select');
    const areaSpan = item.querySelector('.area-automatica');
    const colorSelect = item.querySelector('.color-select');
    const subtotal = item.querySelector('.product-price');
    
    // Obtener área total del terreno
    const areaTotalInput = document.getElementById('area_total');
    const areaTotal = areaTotalInput ? parseFloat(areaTotalInput.value) || 0 : 0;
    
    const tieneModelo = select && select.value;
    const tieneArea = areaTotal > 0;
    const tieneColor = colorSelect && colorSelect.value;
    
    if (tieneModelo && tieneArea && tieneColor) {
        obtenerPrecioInventario(select.value, colorSelect.value, areaTotal)
            .then(costoTotal => {
                // costoTotal ya viene calculado como proporción total
                if (subtotal) {
                    subtotal.textContent = `$${costoTotal.toFixed(2)}`;
                }
                verificarModoComparativo();
            })
            .catch(error => {
                console.error('Error al obtener precio:', error);
                const precioBase = parseFloat(select.selectedOptions[0]?.dataset.precio) || 0;
                const costoTotal = precioBase * areaTotal;
                if (subtotal) {
                    subtotal.textContent = `$${costoTotal.toFixed(2)} (sin inventario)`;
                }
                verificarModoComparativo();
            });
    } else {
        if (subtotal) {
            subtotal.textContent = '$0.00 (pendiente)';
        }
        verificarModoComparativo();
    }
}

async function obtenerPrecioInventario(idProducto, idColor, cantidad) {
    try {
        const response = await fetch(`../../php/cotizaciones/obtener_precio_inventario.php`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                id_producto: idProducto,
                id_color: idColor,
                cantidad: cantidad
            })
        });
        
        const data = await response.json();
        
        if (data.success) {
            // Calcular precio usando la nueva lógica de proporción
            const proporcionUsada = cantidad / data.area_total_rollo;
            const precioTotal = proporcionUsada * data.costo_total_rollo;
            return precioTotal; // Retorna el costo total para esa cantidad
        } else {
            throw new Error(data.message || 'Error al obtener precio');
        }
    } catch (error) {
        console.error('Error en obtenerPrecioInventario:', error);
        throw error;
    }
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
                if (color.codigo_hex) {
                    option.style.color = color.codigo_hex;
                }
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
    const areaSpan = item.querySelector('.area-automatica');
    const price = item.querySelector('.product-price');
    const details = item.querySelector('.rollo-details');
    
    select.value = '';
    areaSpan.textContent = '0 m²';
    if (price) price.textContent = '$0.00';
    if (details) {
        details.style.display = 'none';
        
        const colorContainer = details.querySelector('.color-select-container');
        if (colorContainer) colorContainer.remove();
    }

    select.addEventListener('change', function() {
        actualizarRollos(this);
    });

    container.appendChild(item);
    
    // Actualizar áreas automáticamente para todos los rollos
    actualizarAreasAutomaticas();
    
    // Verificar modo comparativo después de agregar el elemento al DOM
    setTimeout(() => {
        verificarModoComparativo();
    }, 50);
}

function removerRollo(btn) {
    const container = document.getElementById('rollos_container');
    if (container.querySelectorAll('.product-item').length > 1) {
        btn.closest('.product-item').remove();
        // Llamar verificarModoComparativo después de un pequeño delay para que el DOM se actualice
        setTimeout(() => {
            verificarModoComparativo();
        }, 50);
    } else {
        alert('Debe haber al menos un rollo en la cotización.');
    }
}

function actualizarAreasAutomaticas() {
    const areaTotalInput = document.getElementById('area_total');
    const areaTotal = areaTotalInput ? parseFloat(areaTotalInput.value) || 0 : 0;
    
    const container = document.getElementById('rollos_container');
    if (container) {
        const areaSpans = container.querySelectorAll('.area-automatica');
        areaSpans.forEach(span => {
            span.textContent = `${areaTotal} m²`;
        });
    }
}

function verificarModoComparativo() {
    const container = document.getElementById('rollos_container');
    if (!container) return;
    
    const rollosCompletos = Array.from(container.querySelectorAll('.product-item')).filter(item => {
        const select = item.querySelector('.rollo-select');
        const colorSelect = item.querySelector('.color-select');
        const areaTotalInput = document.getElementById('area_total');
        const areaTotal = areaTotalInput ? parseFloat(areaTotalInput.value) || 0 : 0;
        
        return select?.value && colorSelect?.value && areaTotal > 0;
    });
    
    const totalRollos = container.querySelectorAll('.product-item').length;
    const resumenNormal = document.getElementById('resumen-normal');
    const resumenComparativo = document.getElementById('resumen-comparativo');
    const btnAgregarRollo = document.getElementById('btn-agregar-rollo');
    const limiteRollosMensaje = document.getElementById('limite-rollos-mensaje');
    
    // Controlar visibilidad del botón agregar rollo y mensaje
    if (btnAgregarRollo) {
        if (totalRollos >= 2) {
            btnAgregarRollo.style.display = 'none';
            if (limiteRollosMensaje) {
                limiteRollosMensaje.classList.add('visible');
            }
        } else {
            btnAgregarRollo.style.display = 'inline-block';
            if (limiteRollosMensaje) {
                limiteRollosMensaje.classList.remove('visible');
            }
        }
    }
    
    if (rollosCompletos.length === 2) {
        // Modo comparativo
        resumenNormal.style.display = 'none';
        resumenComparativo.style.display = 'block';
        actualizarTotalesComparativo();
    } else {
        // Modo normal
        resumenNormal.style.display = 'block';
        resumenComparativo.style.display = 'none';
        actualizarTotales();
    }
}

export { actualizarRollos, agregarRollo, removerRollo, cargarColoresRollos, actualizarAreasAutomaticas, verificarModoComparativo };