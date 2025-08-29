import { parametrosSistema, cargarParametrosSistema } from './core/parametros.js';
import { toggleTerreno } from './componentes/terreno.js';
import { calcularArea } from './core/calculos.js';
import { actualizarRollos, agregarRollo, removerRollo, cargarColoresRollos, actualizarAreasAutomaticas, verificarModoComparativo } from './componentes/rollos.js';
import { actualizarTotales, actualizarTotalesComparativo } from './core/totales.js';
import { guardarCotizacion } from './formulario/envio.js';

// Función para calcular materiales automáticos (extraída de totales.js para evitar ciclos)
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

// Hacer funciones disponibles globalmente
window.calcularMaterialesAutomaticos = calcularMaterialesAutomaticos;
window.actualizarTotalesComparativo = actualizarTotalesComparativo;
window.verificarModoComparativo = verificarModoComparativo;

function configurarEventosEdicion() {
    // Eventos para terreno
    document.getElementById('tipo_terreno')?.addEventListener('change', toggleTerreno);
    document.getElementById('forma_terreno')?.addEventListener('change', function() {
        calcularArea();
        actualizarAreasAutomaticas();
    });
    document.getElementById('dimension1')?.addEventListener('change', function() {
        calcularArea();
        actualizarAreasAutomaticas();
    });
    document.getElementById('dimension2')?.addEventListener('change', function() {
        calcularArea();
        actualizarAreasAutomaticas();
    });
    document.getElementById('area_irregular')?.addEventListener('change', function() {
        calcularArea();
        actualizarAreasAutomaticas();
    });

    // Eventos para instalación
    document.getElementById('area_total')?.addEventListener('change', function() {
        actualizarAreasAutomaticas();
        setTimeout(() => verificarModoComparativo(), 100);
    });
    
    // Eventos para tipo de instalación (materiales automáticos)
    document.getElementById('tipo_instalacion')?.addEventListener('change', function() {
        calcularMaterialesAutomaticos();
        setTimeout(() => verificarModoComparativo(), 100);
    });

    // Eventos para extras
    document.querySelectorAll('.extra-check').forEach(ck => {
        ck.addEventListener('change', function() {
            toggleExtraQuantity(this);
            setTimeout(() => verificarModoComparativo(), 100);
        });
    });

    // Eventos para cantidades de extras
    document.querySelectorAll('.extra-cantidad').forEach(input => {
        input.addEventListener('input', function() {
            updateExtraTotal(this);
            setTimeout(() => verificarModoComparativo(), 100);
        });
    });

    // Evento para IVA opcional
    document.getElementById('aplicar_iva')?.addEventListener('change', function() {
        setTimeout(() => verificarModoComparativo(), 100);
    });

    // Event delegation para selects de rollo (dinámicos)
    document.addEventListener('change', function(e) {
        if (e.target.classList.contains('rollo-select') && e.target.closest('#rollos_container')) {
            cargarColoresRollos(e.target);
            actualizarRollos(e.target);
            setTimeout(() => verificarModoComparativo(), 100);
        }
    });

    // Event delegation para selects de color (dinámicos)
    document.addEventListener('change', function(e) {
        if (e.target.classList.contains('color-select') && e.target.closest('#rollos_container')) {
            setTimeout(() => verificarModoComparativo(), 100);
        }
    });

    // Botón agregar rollo
    document.getElementById('btn-agregar-rollo')?.addEventListener('click', function() {
        agregarRollo();
        setTimeout(() => verificarModoComparativo(), 100);
    });

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
            const parentContainer = e.target.closest('#rollos_container');
            if (parentContainer && parentContainer.id === 'rollos_container') {
                removerRollo(e.target);
                setTimeout(() => verificarModoComparativo(), 100);
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
        const colorSelect = item.querySelector('.color-select');

        if (select.value && colorSelect?.value) {
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
    document.querySelectorAll('#rollos_container .rollo-select').forEach(select => {
        if (select.value) {
            cargarColoresRollos(select);
        }
    });
    
    // Verificar modo comparativo inicialmente
    setTimeout(() => {
        verificarModoComparativo();
    }, 1500); // Dar tiempo extra para que se carguen los colores
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
