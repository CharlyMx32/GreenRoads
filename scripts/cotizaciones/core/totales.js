// totales.js
import {
    parametrosSistema,
    obtenerValorTabulador
} from './parametros.js';

async function calcularCostoRollos(rollos) {
    try {
        const response = await fetch('../../php/cotizaciones/calcular_costo_rollos.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ rollos })
        });

        const data = await response.json();

        if (!data.success) {
            console.error('Error al calcular costo de rollos:', data.error);
            return { costoTotal: 0, rollosUsados: [] };
        }

        return {
            costoTotal: data.costoTotal,
            rollosUsados: data.rollosUsados
        };
    } catch (error) {
        console.error('Error:', error);
        return { costoTotal: 0, rollosUsados: [] };
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

        // 1. Calcular costo real de los rollos seleccionados
        const rollosSeleccionados = obtenerDatosRollos();
        const { costoTotal: costoRollos } = await calcularCostoRollos(rollosSeleccionados);

        // 2. Obtener margen de utilidad según área (solo para rollos)
        const margenUtilidad = obtenerValorTabulador('margen_utilidad', area) / 100;
        const precioRollos = costoRollos * (1 + margenUtilidad);

        // 3. Calcular productos (sumar directamente su precio)
        let costoProductos = 0;
        const productos = obtenerDatosProductos();
        

        document.querySelectorAll('#productos_container .product-item').forEach(item => {
            const select = item.querySelector('.product-select');
            const input = item.querySelector('input[type="number"]');

            if (select && select.value && input && input.value) {
                const precio = parseFloat(select.selectedOptions[0]?.dataset.precio) || 0;
                const cantidad = parseFloat(input.value) || 0;
                costoProductos += precio * cantidad;
            }
        });

        // 4. Calcular extras
        let extras = 0;
        document.querySelectorAll('.extra-check:checked').forEach(ck => {
            extras += parseFloat(ck.dataset.precio) || 0;
        });

        // 5. Calcular precio de instalación
        const precioInstalacion = obtenerValorTabulador('precio_instalacion', area);
        const costoInstalacion = area * precioInstalacion;

        // 6. Calcular total
        const subtotal = precioRollos + costoProductos + extras + costoInstalacion;

        // Verificar si el IVA está aplicado
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

        mostrarDesglose({
            area,
            costoRollos,
            margenUtilidad: margenUtilidad * 100,
            precioRollos,
            costoProductos,
            extras,
            precioInstalacion,
            costoInstalacion,
            subtotal,
            iva,
            total
        });

    } catch (error) {
        console.error('Error al actualizar totales:', error);
    }
}

function obtenerDatosRollos() {
    const rollos = [];

    document.querySelectorAll('#rollos_container .product-item').forEach(item => {
        const select = item.querySelector('.rollo-select');
        const input = item.querySelector('input[type="number"]');

        if (select && select.value && input && input.value) {
            rollos.push({
                id_producto: parseInt(select.value),
                id_color: parseInt(select.selectedOptions[0]?.dataset.idColor) || null,
                area: parseFloat(input.value)
            });
        }
    });

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

function mostrarDesglose(desglose) {
    const html = `
        <div class="desglose-cotizacion">
            <h4>Detalles de cotización</h4>
            <div class="desglose-item">
                <span>Área total:</span>
                <span>${desglose.area.toFixed(2)} m²</span>
            </div>
            
            <div class="desglose-seccion">
                <h5>Rollos de pasto</h5>
                <div class="desglose-item">
                    <span>Costo real:</span>
                    <span>$${desglose.costoRollos.toFixed(2)}</span>
                </div>
                <div class="desglose-item">
                    <span>Margen (${desglose.margenUtilidad.toFixed(2)}%):</span>
                    <span>$${(desglose.precioRollos - desglose.costoRollos).toFixed(2)}</span>
                </div>
                <div class="desglose-item">
                    <span>Precio con margen:</span>
                    <span>$${desglose.precioRollos.toFixed(2)}</span>
                </div>
            </div>
            
            <div class="desglose-seccion">
                <h5>Otros productos</h5>
                <div class="desglose-item">
                    <span>Costo total:</span>
                    <span>$${desglose.costoProductos.toFixed(2)}</span>
                </div>
            </div>
            
            <div class="desglose-seccion">
                <h5>Instalación</h5>
                <div class="desglose-item">
                    <span>Precio (${desglose.precioInstalacion.toFixed(2)}/m² × ${desglose.area.toFixed(2)}m²):</span>
                    <span>$${desglose.costoInstalacion.toFixed(2)}</span>
                </div>
            </div>
            
            <div class="desglose-seccion">
                <h5>Extras</h5>
                <div class="desglose-item">
                    <span>Total:</span>
                    <span>$${desglose.extras.toFixed(2)}</span>
                </div>
            </div>
            
            <div class="desglose-total">
                <div class="desglose-item">
                    <strong>Subtotal:</strong>
                    <strong>$${desglose.subtotal.toFixed(2)}</strong>
                </div>
                <div class="desglose-item">
                    <span>IVA (${(parametrosSistema.ivaPorcentaje * 100).toFixed(2)}%):</span>
                    <span>$${desglose.iva.toFixed(2)}</span>
                </div>
                <div class="desglose-item">
                    <strong>Total:</strong>
                    <strong>$${desglose.total.toFixed(2)}</strong>
                </div>
            </div>
        </div>
    `;

    const contenedor = document.getElementById('resumen_cotizacion');
    if (contenedor) {
        contenedor.innerHTML = html;
    }
}

export { actualizarTotales, obtenerDatosRollos, obtenerDatosProductos };