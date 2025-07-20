import { parametrosSistema } from './parametros.js';

function actualizarTotales() {
    // Sumar rollos
    let subtotalRollos = 0;
    document.querySelectorAll('#rollos_container .product-item').forEach(item => {
        const precio = parseFloat(item.querySelector('.rollo-select').selectedOptions[0]?.dataset.precio) || 0;
        const cantidad = parseFloat(item.querySelector('input[type="number"]').value) || 0;
        subtotalRollos += precio * cantidad;
    });

    // Sumar productos
    let subtotalProductos = 0;
    document.querySelectorAll('#productos_container .product-item').forEach(item => {
        const precio = parseFloat(item.querySelector('.product-select').selectedOptions[0]?.dataset.precio) || 0;
        const cantidad = parseFloat(item.querySelector('input[type="number"]').value) || 0;
        subtotalProductos += precio * cantidad;
    });

    // Sumar extras
    let extras = 0;
    document.querySelectorAll('.extra-check:checked').forEach(ck => {
        extras += parseFloat(ck.dataset.precio);
    });

    // Sumar instalación
    const area = parseFloat(document.getElementById('area_total').value) || 0;
    const precioInstalacion = parseFloat(document.getElementById('precio_instalacion').value) || 0;
    const instalacion = area * precioInstalacion;

    // Calcular total sin IVA
    const totalSinIVA = subtotalRollos + subtotalProductos + extras + instalacion;

    // Actualizar la interfaz
    document.getElementById('total_sin_iva').textContent = `$${totalSinIVA.toFixed(2)}`;
}

export { actualizarTotales };