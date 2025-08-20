// totales.js
import {
    parametrosSistema,
    obtenerValorTabulador
} from './parametros.js';

async function obtenerInfoRollo(idProducto, idColor, cantidad) {
    try {
        const response = await fetch('../../php/cotizaciones/obtener_precio_inventario.php', {
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
            return {
                costoTotalRollo: data.costo_total_rollo,
                areaTotalRollo: data.area_total_rollo,
                areaDisponible: data.area_disponible,
                tieneInventario: data.tiene_inventario
            };
        } else {
            console.error('Error al obtener info del rollo:', data.message);
            return {
                costoTotalRollo: 1000, 
                areaTotalRollo: 200,
                areaDisponible: 200,
                tieneInventario: false
            };
        }
    } catch (error) {
        console.error('Error:', error);
        return {
            costoUnitario: 1000, 
            areaDisponible: 200, 
            tieneInventario: false
        };
    }
}

async function obtenerCostoProducto(idProducto) {
    try {
        const response = await fetch(`../../php/productos/obtener_costo.php?id=${idProducto}`);
        const data = await response.json();
        return data.success ? data.costo : 0;
    } catch (error) {
        console.error('Error al obtener costo:', error);
        return 0;
    }
}

function agregarProductosCalculados(materiales) {
    document.querySelectorAll('#productos_container .product-item[data-auto="true"]').forEach(item => {
        item.remove();
    });

    materiales.forEach(material => {
        agregarProductoAuto(material.nombre, material.cantidad, material.precio_unitario, material.id_producto);
    });
}

function agregarProductoAuto(nombre, cantidad, precio, idProducto) {
    const container = document.getElementById('productos_container');
    if (!container) return;

    const productItem = document.createElement('div');
    productItem.className = 'product-item';
    productItem.setAttribute('data-auto', 'true');
    
    productItem.innerHTML = `
        <div class="form-group">
            <label>Producto (Auto)</label>
            <select class="product-select textfield" disabled>
                <option value="${idProducto}" data-precio="${precio}" selected>${nombre}</option>
            </select>
        </div>
        <div class="form-group" style="max-width: 120px;">
            <label>Cantidad</label>
            <input type="number" value="${cantidad}" min="0" step="0.01" readonly>
        </div>
        <div class="product-controls">
            <span class="auto-badge">AUTO</span>
        </div>
    `;
    
    container.appendChild(productItem);
}

async function actualizarTotales() {
    try {
        const areaInput = document.getElementById('area_total');
        let area = 0;
        if (areaInput) {
            const value = areaInput.value.trim();
            area = value === '' ? 0 : parseFloat(value);
        }
        if (isNaN(area)) {
            console.warn('Área total no válida:', area);
            return;
        }

        // 1. Calcular costo REAL de rollos
        let costoRollosReal = 0;
        let detalleRollos = [];
        
        const rollosContainer = document.getElementById('rollos-container') || document.getElementById('rollos_container');
        if (rollosContainer) {
            const rollosElements = rollosContainer.querySelectorAll('.product-item');
            for (const item of rollosElements) {
                const select = item.querySelector('.rollo-select');
                const areaSpan = item.querySelector('.area-automatica');
                const colorSelect = item.querySelector('.color-select');
                
                if (select?.value && colorSelect?.value && area > 0) {
                    const areaUsada = area; // Usar área total del terreno
                    const idProducto = parseInt(select.value);
                    const idColor = parseInt(colorSelect.value);
                    
                    const infoRollo = await obtenerInfoRollo(idProducto, idColor, areaUsada);
                    
                    const costoReal = infoRollo.costoTotalRollo;
                    
                    costoRollosReal = costoReal;
                    
                    detalleRollos.push({
                        nombre: select.options[select.selectedIndex].text,
                        areaUsada: areaUsada,
                        areaTotalRollo: infoRollo.areaTotalRollo,
                        costoTotalRollo: infoRollo.costoTotalRollo,
                        costoReal: costoReal,
                        tieneInventario: infoRollo.tieneInventario
                    });
                }
            }
        }

        // 2. Aplicar DESCUENTO por volumen según área (se resta del costo)
        const descuentoVolumenPorcentaje = obtenerValorTabulador('descuento_volumen', area) / 100;
        const descuentoRollos = costoRollosReal * descuentoVolumenPorcentaje;
        const precioRollosConDescuento = costoRollosReal - descuentoRollos;

        // 3. Calcular materiales automáticos según tipo de instalación
        const costoMaterialesAuto = window.calcularMaterialesAutomaticos ? await window.calcularMaterialesAutomaticos() : 0;

        // 4. Calcular extras
        let extras = 0;
        let detalleExtras = [];
        document.querySelectorAll('.extra-check:checked').forEach(ck => {
            const precio = parseFloat(ck.dataset.precio) || 0;
            extras += precio;
            detalleExtras.push({
                nombre: ck.nextSibling.textContent.trim(),
                precio: precio
            });
        });

        // 5. Calcular precio de instalación
        const precioInstalacion = obtenerValorTabulador('precio_instalacion', area);
        const costoInstalacion = area * precioInstalacion;

        // 6. Calcular mano de obra adicional
        const manoObraPorM2 = obtenerValorTabulador('mano_obra', area);
        const costoManoObra = area * manoObraPorM2;

        // 7. Calcular subtotal (SIN productos manuales)
        const subtotal = precioRollosConDescuento + costoMaterialesAuto + extras + costoInstalacion + costoManoObra;

        // 8. Verificar si el IVA está aplicado
        const aplicarIva = document.getElementById('aplicar_iva')?.checked ?? true;
        const iva = aplicarIva ? subtotal * parametrosSistema.ivaPorcentaje : 0;
        const total = subtotal + iva;

        // Actualizar la interfaz
        document.getElementById('subtotal').textContent = `$${subtotal.toFixed(2)}`;
        document.getElementById('iva').textContent = `$${iva.toFixed(2)}`;
        document.getElementById('total').textContent = `$${total.toFixed(2)}`;
        document.getElementById('iva-percent').textContent = (parametrosSistema.ivaPorcentaje * 100).toFixed(2);

        // Mostrar/ocultar IVA según checkbox
        const ivaContainer = document.getElementById('iva-container');
        if (ivaContainer) {
            ivaContainer.style.display = aplicarIva ? 'flex' : 'none';
        }

    } catch (error) {
        console.error('Error al actualizar totales:', error);
    }
}

async function obtenerDatosRollos() {
    const rollos = [];

    // Detectar el contenedor correcto (edición o nueva cotización)
    const rollosContainer = document.getElementById('rollos-container') || document.getElementById('rollos_container');
    if (rollosContainer) {
        const rollosElements = rollosContainer.querySelectorAll('.product-item');
        for (const item of rollosElements) {
            const select = item.querySelector('.rollo-select');
            const areaSpan = item.querySelector('.area-automatica');
            const colorSelect = item.querySelector('.color-select');

            if (select && select.value && colorSelect && colorSelect.value) {
                // Obtener área del terreno
                const areaTotalInput = document.getElementById('area_total');
                const cantidad = areaTotalInput ? parseFloat(areaTotalInput.value) || 0 : 0;
                
                if (cantidad > 0) {
                    const idProducto = parseInt(select.value);
                    const idColor = parseInt(colorSelect.value);
                    
                    // Obtener información del rollo desde la BD
                    const infoRollo = await obtenerInfoRollo(idProducto, idColor, cantidad);
                    
                    // El precio unitario es siempre el costo por m² (no total)
                    const precioUnitario = infoRollo.costoTotalRollo;
                    
                    rollos.push({
                        id_producto: idProducto,
                        id_color: idColor,
                        cantidad: cantidad,
                        precio_unitario: precioUnitario // Precio calculado por proporción
                    });
                }
            }
        }
    }

    return rollos;
}

async function actualizarTotalesComparativo() {
    try {
        const container = document.getElementById('rollos_container');
        if (!container) return;
        
        const rollosElements = Array.from(container.querySelectorAll('.product-item'));
        const rollosCompletos = rollosElements.filter(item => {
            const select = item.querySelector('.rollo-select');
            const colorSelect = item.querySelector('.color-select');
            const areaTotalInput = document.getElementById('area_total');
            const areaTotal = areaTotalInput ? parseFloat(areaTotalInput.value) || 0 : 0;
            
            return select?.value && colorSelect?.value && areaTotal > 0;
        });

        if (rollosCompletos.length !== 2) return;

        const areaTotalInput = document.getElementById('area_total');
        const area = areaTotalInput ? parseFloat(areaTotalInput.value) || 0 : 0;

        // Calcular costos base (materiales, instalación, etc.) una sola vez
        const costoMaterialesAuto = window.calcularMaterialesAutomaticos ? await window.calcularMaterialesAutomaticos() : 0;
        
        let extras = 0;
        document.querySelectorAll('.extra-check:checked').forEach(ck => {
            extras += parseFloat(ck.dataset.precio) || 0;
        });

        const precioInstalacion = obtenerValorTabulador('precio_instalacion', area);
        const costoInstalacion = area * precioInstalacion;
        const manoObraPorM2 = obtenerValorTabulador('mano_obra', area);
        const costoManoObra = area * manoObraPorM2;

        const aplicarIva = document.getElementById('aplicar_iva')?.checked ?? true;

        // Calcular para cada rollo
        for (let i = 0; i < 2; i++) {
            const item = rollosCompletos[i];
            const select = item.querySelector('.rollo-select');
            const colorSelect = item.querySelector('.color-select');
            const sufijo = i === 0 ? 'a' : 'b';

            // Obtener información del rollo
            const infoRollo = await obtenerInfoRollo(
                parseInt(select.value), 
                parseInt(colorSelect.value), 
                area
            );

            // Calcular descuento por volumen
            const descuentoVolumenPorcentaje = obtenerValorTabulador('descuento_volumen', area) / 100;
            const descuentoRollos = infoRollo.costoTotalRollo * descuentoVolumenPorcentaje;
            const precioRollosConDescuento = infoRollo.costoTotalRollo - descuentoRollos;

            // Subtotal para esta opción
            const subtotal = precioRollosConDescuento + costoMaterialesAuto + extras + costoInstalacion + costoManoObra;
            const iva = aplicarIva ? subtotal * parametrosSistema.ivaPorcentaje : 0;
            const total = subtotal + iva;

            // Actualizar interfaz
            const nombreRollo = select.options[select.selectedIndex].text;
            document.querySelector(`.nombre-rollo-${sufijo}`).textContent = nombreRollo;
            document.getElementById(`subtotal-${sufijo}`).textContent = `$${subtotal.toFixed(2)}`;
            document.getElementById(`iva-${sufijo}`).textContent = `$${iva.toFixed(2)}`;
            document.getElementById(`total-${sufijo}`).textContent = `$${total.toFixed(2)}`;
            document.querySelector(`.iva-percent-${sufijo}`).textContent = (parametrosSistema.ivaPorcentaje * 100).toFixed(2);

            // Mostrar/ocultar IVA
            const ivaContainer = document.querySelector(`.iva-container-${sufijo}`);
            if (ivaContainer) {
                ivaContainer.style.display = aplicarIva ? 'flex' : 'none';
            }

            // Guardar totales para calcular diferencia
            if (i === 0) window.totalA = total;
            if (i === 1) window.totalB = total;
        }

        // Calcular y mostrar diferencia
        if (window.totalA !== undefined && window.totalB !== undefined) {
            const diferencia = Math.abs(window.totalA - window.totalB);
            const diferenciaElement = document.getElementById('diferencia-precio');
            if (diferenciaElement) {
                const masBarato = window.totalA < window.totalB ? 'A' : 'B';
                diferenciaElement.textContent = `$${diferencia.toFixed(2)} (Opción ${masBarato} es más económica)`;
            }
        }

    } catch (error) {
        console.error('Error al actualizar totales comparativos:', error);
    }
}

// Función vacía para compatibilidad - ya no se usan productos manuales
function obtenerDatosProductos() {
    return [];
}

export { actualizarTotales, actualizarTotalesComparativo, obtenerDatosRollos, obtenerDatosProductos };