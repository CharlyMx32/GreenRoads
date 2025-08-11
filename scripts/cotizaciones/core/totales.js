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
                costoTotalRollo: 1000, // Fallback
                areaTotalRollo: 200, // Fallback
                areaDisponible: 200, // Fallback
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

async function calcularMaterialesAutomaticos() {
    try {
        const tipoInstalacion = document.getElementById('tipo_instalacion')?.value;
        const area = parseFloat(document.getElementById('area_total')?.value || 0);

        if (!tipoInstalacion || area <= 0) {
            return 0; 
        }

        const response = await fetch('/greenroads/php/cotizaciones/calcular_materiales.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                tipo_terreno: tipoInstalacion,
                area: area
            })
        });

        if (!response.ok) {
            throw new Error('Error en la respuesta del servidor');
        }

        const data = await response.json();
        
        if (data.success) {
            agregarProductosCalculados(data.materiales);
            return data.costo_total;
        } else {
            console.error('Error al calcular materiales:', data.error);
            return 0;
        }

    } catch (error) {
        console.error('Error en calcular materiales automáticos:', error);
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

        // 1. Calcular costo REAL de rollos usando información dinámica de la BD
        let costoRollosReal = 0;
        let detalleRollos = [];
        
        const rollosElements = document.querySelectorAll('#rollos_container .product-item');
        for (const item of rollosElements) {
            const select = item.querySelector('.rollo-select');
            const cantidadInput = item.querySelector('input[type="number"]');
            const colorSelect = item.querySelector('.color-select');
            
            if (select?.value && cantidadInput?.value && colorSelect?.value) {
                const areaUsada = parseFloat(cantidadInput.value);
                const idProducto = parseInt(select.value);
                const idColor = parseInt(colorSelect.value);
                
                const infoRollo = await obtenerInfoRollo(idProducto, idColor, areaUsada);
                
                // Calcular costo como proporción: (área_usada / área_total_rollo) × costo_total_rollo
                const proporcionUsada = areaUsada / infoRollo.areaTotalRollo;
                const costoReal = proporcionUsada * infoRollo.costoTotalRollo;
                
                costoRollosReal += costoReal;
                
                detalleRollos.push({
                    nombre: select.options[select.selectedIndex].text,
                    areaUsada: areaUsada,
                    areaTotalRollo: infoRollo.areaTotalRollo,
                    costoTotalRollo: infoRollo.costoTotalRollo,
                    proporcionUsada: proporcionUsada,
                    costoReal: costoReal,
                    tieneInventario: infoRollo.tieneInventario
                });
            }
        }

        // 2. Aplicar DESCUENTO por volumen según área (se resta del costo)
        const descuentoVolumenPorcentaje = obtenerValorTabulador('descuento_volumen', area) / 100;
        const descuentoRollos = costoRollosReal * descuentoVolumenPorcentaje;
        const precioRollosConDescuento = costoRollosReal - descuentoRollos;

        // 3. Calcular materiales automáticos según tipo de instalación
        const costoMaterialesAuto = await calcularMaterialesAutomaticos();

        // 4. Calcular productos manuales (clavos, etc.)
        let costoProductos = 0;
        let detalleProductos = [];
        
        document.querySelectorAll('#productos_container .product-item').forEach(item => {
            const select = item.querySelector('.product-select');
            const input = item.querySelector('input[type="number"]');

            if (select && select.value && input && input.value) {
                const precio = parseFloat(select.selectedOptions[0]?.dataset.precio) || 0;
                const cantidad = parseFloat(input.value) || 0;
                const subtotal = precio * cantidad;
                costoProductos += subtotal;
                
                detalleProductos.push({
                    nombre: select.options[select.selectedIndex].text,
                    cantidad: cantidad,
                    precioUnitario: precio,
                    subtotal: subtotal
                });
            }
        });

        // 5. Calcular extras
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

        // 6. Calcular precio de instalación
        const precioInstalacion = obtenerValorTabulador('precio_instalacion', area);
        const costoInstalacion = area * precioInstalacion;

        // 7. Calcular mano de obra adicional
        const manoObraPorM2 = obtenerValorTabulador('mano_obra', area);
        const costoManoObra = area * manoObraPorM2;

        // 8. Calcular subtotal
        const subtotal = precioRollosConDescuento + costoProductos + costoMaterialesAuto + extras + costoInstalacion + costoManoObra;

        // 9. Verificar si el IVA está aplicado
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

    const rollosElements = document.querySelectorAll('#rollos_container .product-item');
    for (const item of rollosElements) {
        const select = item.querySelector('.rollo-select');
        const input = item.querySelector('input[type="number"]');
        const colorSelect = item.querySelector('.color-select');

        if (select && select.value && input && input.value && colorSelect && colorSelect.value) {
            const cantidad = parseFloat(input.value);
            const idProducto = parseInt(select.value);
            const idColor = parseInt(colorSelect.value);
            
            // Obtener información del rollo desde la BD
            const infoRollo = await obtenerInfoRollo(idProducto, idColor, cantidad);
            
            // Calcular precio unitario como proporción del costo total
            const proporcionUsada = cantidad / infoRollo.areaTotalRollo;
            const precioUnitario = proporcionUsada * infoRollo.costoTotalRollo / cantidad;
            
            rollos.push({
                id_producto: idProducto,
                id_color: idColor,
                cantidad: cantidad,
                precio_unitario: precioUnitario // Precio calculado por proporción
            });
        }
    }

    return rollos;
}

function obtenerDatosProductos() {
    const productos = [];

    document.querySelectorAll('#productos_container .product-item').forEach(item => {
        const select = item.querySelector('.product-select');
        const input = item.querySelector('input[type="number"]');

        if (select && select.value && input && input.value) {
            productos.push({
                id_producto: parseInt(select.value),
                cantidad: parseFloat(input.value)
            });
        }
    });

    return productos;
}

export { actualizarTotales, obtenerDatosRollos, obtenerDatosProductos };