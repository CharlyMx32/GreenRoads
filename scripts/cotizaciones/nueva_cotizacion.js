import { parametrosSistema, cargarParametrosSistema } from './core/parametros.js';
import { toggleTerreno } from './componentes/terreno.js';
import { calcularArea } from './core/calculos.js';
import { actualizarRollos, agregarRollo, removerRollo, cargarColoresRollos, actualizarAreasAutomaticas, verificarModoComparativo } from './componentes/rollos.js';
import { actualizarTotales } from './core/totales.js';
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

// Hacer la función disponible globalmente
window.calcularMaterialesAutomaticos = calcularMaterialesAutomaticos;


function configurarEventos() {
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
        verificarModoComparativo();
    });
    
    // Eventos para tipo de instalación (materiales automáticos)
    document.getElementById('tipo_instalacion')?.addEventListener('change', function() {
        calcularMaterialesAutomaticos();
        verificarModoComparativo();
    });

    // Eventos para extras
    document.querySelectorAll('.extra-check').forEach(ck => {
        ck.addEventListener('change', verificarModoComparativo);
    });

    // Evento para IVA opcional
    document.getElementById('aplicar_iva')?.addEventListener('change', verificarModoComparativo);

    // Eventos para rollos
    document.querySelectorAll('#rollos_container .rollo-select').forEach(select => {
        select.addEventListener('change', function () {
            cargarColoresRollos(this);
            actualizarRollos(this);
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
        verificarModoComparativo();

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