import { actualizarTotales } from '../core/totales.js';

function actualizarProductos(element) {
    const item = element.closest('.product-item');
    if (!item) return;

    const select = item.querySelector('.product-select');
    const cantidad = item.querySelector('input[type="number"]');
    const subtotal = item.querySelector('.product-price');

    if (!select || !cantidad || !subtotal) return;

    const precio = parseFloat(select.selectedOptions[0]?.dataset.precio) || 0;
    const cant = parseFloat(cantidad.value) || 0;
    subtotal.textContent = `$${(precio * cant).toFixed(2)}`;
    
    actualizarTotales();
}

function agregarProducto() {
    let container = document.getElementById('productos_container');
    let item = container.querySelector('.product-item').cloneNode(true);

    item.querySelector('.product-select').value = '';
    item.querySelector('input[type="number"]').value = '1';
    item.querySelector('.product-price').textContent = '$0.00';

    item.querySelector('.product-select').addEventListener('change', function() {
        actualizarProductos(this);
    });
    
    item.querySelector('input[type="number"]').addEventListener('change', function() {
        actualizarProductos(this);
    });

    container.appendChild(item);
}

function removerProducto(btn) {
    let container = document.getElementById('productos_container');
    if (container.querySelectorAll('.product-item').length > 1) {
        btn.closest('.product-item').remove();
        actualizarTotales();
    } else {
        alert('Debe haber al menos un producto.');
    }
}

export { actualizarProductos, agregarProducto, removerProducto };