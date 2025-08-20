/**
 * Funciones específicas para cargar datos existentes en modo edición
 */

import { cargarColoresRollos } from './rollos.js';
import { actualizarRollos } from './rollos.js';
import { actualizarProductos } from './productos.js';

/**
 * Carga los datos de rollos existentes en la cotización
 */
export function cargarRollosEdicion(rollosData) {
    const container = document.getElementById('rollos-container');
    if (!container || !rollosData || !Array.isArray(rollosData)) {
        return;
    }

    // Limpiar container
    container.innerHTML = '';

    rollosData.forEach((rollo, index) => {
        const rolloItem = crearElementoRollo(rollo, index);
        container.appendChild(rolloItem);
    });

    // Configurar eventos después de cargar
    configurarEventosRollosCargados();
}

/**
 * Carga los datos de productos existentes en la cotización
 */
export function cargarProductosEdicion(productosData) {
    const container = document.getElementById('productos-container');
    if (!container || !productosData || !Array.isArray(productosData)) {
        return;
    }

    // Limpiar container
    container.innerHTML = '';

    productosData.forEach((producto, index) => {
        const productoItem = crearElementoProducto(producto, index);
        container.appendChild(productoItem);
    });

    // Configurar eventos después de cargar
    configurarEventosProductosCargados();
}

/**
 * Crea un elemento HTML para un rollo existente
 */
function crearElementoRollo(rollo, index) {
    const div = document.createElement('div');
    div.className = 'product-item';
    div.setAttribute('data-index', index);

    div.innerHTML = `
        <div class="product-header">
            <select class="rollo-select textfield">
                <option value="">-- Selecciona Rollo --</option>
                <option value="${rollo.id_producto}" 
                    data-precio="${rollo.precio_unitario}"
                    data-modelo="${rollo.modelo || ''}"
                    data-colores="${rollo.colores_asignados || ''}"
                    selected>
                    ${rollo.nombre_producto}
                </option>
            </select>
            <input type="number" class="textfield" placeholder="m²" min="0.01" step="0.01" 
                value="${rollo.cantidad}" style="width: 80px;">
            <span class="product-price" style="font-weight: bold; color: #7dc042; width: 100px; text-align: right;">
                $${(rollo.precio_unitario * rollo.cantidad).toFixed(2)}
            </span>
            <div class="eliminar">
                <i class="fa-solid fa-trash" type="button"></i>
            </div>
        </div>
        <div class="rollo-details" style="display: block; margin-top: 10px;">
            <div><strong>Modelo:</strong> <span class="modelo-text">${rollo.modelo || ''}</span></div>
            <div><strong>Color:</strong> 
                <select class="color-select textfield">
                    <option value="${rollo.id_color}" selected>
                        ${rollo.nombre_color}
                    </option>
                </select>
            </div>
            <div><strong>Área seleccionada:</strong> <span class="area-text">${rollo.cantidad} m²</span></div>
        </div>
    `;

    return div;
}

/**
 * Crea un elemento HTML para un producto existente
 */
function crearElementoProducto(producto, index) {
    const div = document.createElement('div');
    div.className = 'product-item';
    div.setAttribute('data-index', index);

    div.innerHTML = `
        <div class="product-header">
            <select class="product-select textfield">
                <option value="">-- Selecciona Producto --</option>
                <option value="${producto.id_producto}" 
                    data-precio="${producto.precio_unitario}"
                    data-unidad="${producto.unidad || ''}"
                    selected>
                    ${producto.nombre_producto} (${producto.unidad || ''})
                </option>
            </select>
            <input type="number" class="textfield" placeholder="Cantidad" min="1" 
                value="${producto.cantidad}" style="width: 80px;">
            <div class="eliminar">
                <i class="fa-solid fa-trash" type="button"></i>
            </div>
        </div>
    `;

    return div;
}

/**
 * Configura eventos para rollos cargados
 */
function configurarEventosRollosCargados() {
    document.querySelectorAll('#rollos-container .rollo-select').forEach(select => {
        if (select.value) {
            // Cargar colores disponibles para el rollo seleccionado
            cargarColoresRollos(select);
        }

        select.addEventListener('change', function() {
            cargarColoresRollos(this);
            actualizarRollos(this);
        });
    });

    document.querySelectorAll('#rollos-container input[type="number"]').forEach(input => {
        input.addEventListener('input', function() {
            actualizarRollos(this);
        });
    });

    document.querySelectorAll('#rollos-container .color-select').forEach(select => {
        select.addEventListener('change', function() {
            actualizarRollos(this);
        });
    });
}

/**
 * Configura eventos para productos cargados
 */
function configurarEventosProductosCargados() {
    document.querySelectorAll('#productos-container .product-select').forEach(select => {
        select.addEventListener('change', function() {
            actualizarProductos(this);
        });
    });

    document.querySelectorAll('#productos-container input[type="number"]').forEach(input => {
        input.addEventListener('input', function() {
            actualizarProductos(this);
        });
    });
}

/**
 * Carga datos de formas irregulares si existen
 */
export function cargarFormasIrregularesEdicion(formasData) {
    if (!formasData || !Array.isArray(formasData)) {
        return;
    }

    // Esta función debe existir en formas_irregulares.js
    if (typeof window.cargarFormasIrregulares === 'function') {
        window.cargarFormasIrregulares(formasData);
    }
}

/**
 * Función principal para cargar todos los datos de edición
 */
export function cargarTodosDatosEdicion(datos) {
    if (!datos) return;

    // Cargar rollos
    if (datos.rollos) {
        cargarRollosEdicion(datos.rollos);
    }

    // Cargar productos
    if (datos.productos) {
        cargarProductosEdicion(datos.productos);
    }

    // Cargar formas irregulares
    if (datos.formasIrregulares) {
        cargarFormasIrregularesEdicion(datos.formasIrregulares);
    }
}
