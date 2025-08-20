import { parametrosSistema, cargarParametrosSistema } from './core/parametros.js';
import { toggleTerreno } from './componentes/terreno.js';
import { calcularArea } from './core/calculos.js';
import { actualizarRollos, agregarRollo, removerRollo, cargarColoresRollos } from './componentes/rollos.js';
import { actualizarTotales } from './core/totales.js';
import { guardarCotizacion } from './formulario/envio.js';

function configurarEventosEdicion() {
    // Eventos para terreno
    document.getElementById('tipo_terreno')?.addEventListener('change', toggleTerreno);
    document.getElementById('forma_terreno')?.addEventListener('change', calcularArea);
    document.getElementById('dimension1')?.addEventListener('change', calcularArea);
    document.getElementById('dimension2')?.addEventListener('change', calcularArea);
    document.getElementById('area_irregular')?.addEventListener('change', calcularArea);

    // Eventos para instalación
    document.getElementById('area_total')?.addEventListener('change', actualizarTotales);
    
    // Eventos para tipo de instalación (materiales automáticos)
    document.getElementById('tipo_instalacion')?.addEventListener('change', function() {
        actualizarTotales();
    });

    // Eventos para extras
    document.querySelectorAll('.extra-check').forEach(ck => {
        ck.addEventListener('change', actualizarTotales);
    });

    // Evento para IVA opcional
    document.getElementById('aplicar_iva')?.addEventListener('change', actualizarTotales);

    // Eventos para rollos existentes
    document.querySelectorAll('#rollos-container .rollo-select').forEach(select => {
        select.addEventListener('change', function () {
            cargarColoresRollos(this);
            actualizarRollos(this);
        });
    });

    document.querySelectorAll('#rollos-container input[type="number"]').forEach(input => {
        input.addEventListener('input', function () {
            actualizarRollos(this);
        });
    });

    // Botón agregar rollo
    document.getElementById('btn-agregar-rollo')?.addEventListener('click', agregarRollo);

    // Botón guardar
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
    }

    // Event delegation para botones de eliminar
    document.addEventListener('click', function (e) {
        if (e.target.closest('.fa-trash')) {
            const parentContainer = e.target.closest('#rollos-container');
            if (parentContainer && parentContainer.id === 'rollos-container') {
                removerRollo(e.target);
            }
        }
    });
}

// Función de validación específica para edición
function validarFormularioEdicion() {
    const cliente = document.getElementById('cliente');
    if (!cliente || !cliente.value) {
        alert('Seleccione un cliente');
        return false;
    }

    const tipoTerreno = document.getElementById('tipo_terreno').value;
    if (!tipoTerreno) {
        alert('Seleccione el tipo de terreno');
        return false;
    }

    const areaTerreno = parseFloat(document.getElementById('area_total').value) || 0;
    if (areaTerreno <= 0) {
        alert('El área del terreno debe ser mayor que cero');
        return false;
    }

    if (!document.getElementById('tipo_instalacion').value) {
        alert('Seleccione el tipo de instalación');
        return false;
    }

    // Validar rollos
    let rollosValidos = false;
    document.querySelectorAll('#rollos-container .product-item').forEach(item => {
        const select = item.querySelector('.rollo-select');
        const input = item.querySelector('input[type="number"]');
        const colorSelect = item.querySelector('.color-select');

        if (select.value && input.value && colorSelect?.value) {
            rollosValidos = true;
        }
    });

    if (!rollosValidos) {
        alert('Debe tener al menos un rollo de pasto válido con color seleccionado');
        return false;
    }

    return true;
}

// Función para cargar datos existentes
function cargarDatosExistentes() {
    // Cargar colores para rollos existentes
    document.querySelectorAll('#rollos-container .rollo-select').forEach(select => {
        if (select.value) {
            cargarColoresRollos(select);
        }
    });
    
    // Actualizar totales iniciales
    setTimeout(() => {
        actualizarTotales();
    }, 500);
}

async function inicializarEdicion() {
    try {
        // Cargar parámetros del sistema
        await cargarParametrosSistema();

        if (parametrosSistema.garantiaDefault <= 0 || parametrosSistema.precioInstalacion <= 0) {
            throw new Error('Los parámetros del sistema no se cargaron correctamente');
        }

        // Hacer disponible la función de validación globalmente
        window.validarFormularioEdicion = validarFormularioEdicion;

        // Configurar eventos
        configurarEventosEdicion();
        
        // Configurar terreno
        toggleTerreno();
        
        // Cargar datos existentes
        cargarDatosExistentes();
        
        console.log('Modo edición inicializado correctamente');

    } catch (error) {
        console.error('Error en inicialización de edición:', error);
        alert('Error al cargar la aplicación: ' + error.message);
    }
}

// Inicializar cuando el DOM esté listo
document.addEventListener('DOMContentLoaded', inicializarEdicion);
