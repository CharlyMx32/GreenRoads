import { parametrosSistema, cargarParametrosSistema } from './core/parametros.js';
import { toggleTerreno } from './componentes/terreno.js';
import { calcularArea } from './core/calculos.js';
import { actualizarRollos, agregarRollo, removerRollo, cargarColoresRollos } from './componentes/rollos.js';
import { actualizarTotales } from './core/totales.js';
import { guardarCotizacion } from './formulario/envio.js';
import { actualizarProductos, agregarProducto, removerProducto } from './componentes/productos.js';


function configurarEventos() {
    // Eventos para terreno
    document.getElementById('tipo_terreno')?.addEventListener('change', toggleTerreno);
    document.getElementById('forma_terreno')?.addEventListener('change', calcularArea);
    document.getElementById('dimension1')?.addEventListener('change', calcularArea);
    document.getElementById('dimension2')?.addEventListener('change', calcularArea);
    document.getElementById('area_irregular')?.addEventListener('change', calcularArea);

    // Eventos para instalación
    document.getElementById('area_total')?.addEventListener('change', actualizarTotales);

    // Eventos para extras
    document.querySelectorAll('.extra-check').forEach(ck => {
        ck.addEventListener('change', actualizarTotales);
    });

    // Eventos para rollos
    document.querySelectorAll('#rollos_container .rollo-select').forEach(select => {
        select.addEventListener('change', function () {
            cargarColoresRollos(this);
            actualizarRollos(this);
        });
    });

    document.querySelectorAll('#rollos_container input[type="number"]').forEach(input => {
        input.addEventListener('input', function () {
            actualizarRollos(this);
        });
    });

    // Eventos para productos
    document.querySelectorAll('#productos_container .product-select').forEach(select => {
        select.addEventListener('change', function () {
            actualizarProductos(this);
            actualizarTotales();
        });
    });

    document.querySelectorAll('#productos_container input[type="number"]').forEach(input => {
        input.addEventListener('input', function () {
            actualizarProductos(this);
            actualizarTotales();
        });
    });

    document.getElementById('btn-agregar-rollo')?.addEventListener('click', agregarRollo);

    const btnGuardar = document.getElementById('btn-guardar-cotizacion');
    if (btnGuardar) {
        btnGuardar.addEventListener('click', async (e) => {
            e.preventDefault();
            try {
                await guardarCotizacion();
            } catch (error) {
                console.error('Error al guardar:', error);
                alert('Error al guardar: ' + error.message);
            }
        });
    } else {
        console.error('Botón guardar no encontrado');
    }

    document.addEventListener('click', function (e) {
        if (e.target.closest('.fa-trash') && e.target.closest('#rollos_container')) {
            removerRollo(e.target);
        }
        if (e.target.closest('.fa-trash') && e.target.closest('#productos_container')) {
            removerProducto(e.target);
        }
    });
}

async function inicializarAplicacion() {
    try {
        // Cargar parámetros primero y esperar a que terminen
        await cargarParametrosSistema();

        if (parametrosSistema.garantiaDefault <= 0 || parametrosSistema.precioInstalacion <= 0) {
            throw new Error('Los parámetros del sistema no se cargaron correctamente');
        }

        configurarEventos();
        toggleTerreno();
        actualizarTotales();

        const areaTotalInput = document.getElementById('area_total');
        if (areaTotalInput) {
            areaTotalInput.value = '0.00';
        }
    } catch (error) {
        console.error('Error en inicialización:', error);
        alert('Error al cargar la aplicación: ' + error.message);
    }
}


document.addEventListener('DOMContentLoaded', inicializarAplicacion);