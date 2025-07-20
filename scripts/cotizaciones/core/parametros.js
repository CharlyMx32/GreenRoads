const parametrosSistema = {
    precioInstalacion: 0,
    garantiaDefault: 5,
    ivaPorcentaje: 0.16
};

async function cargarParametrosSistema() {
    try {
        const response = await fetch('../../php/configuracion/obtener_parametros.php');
        
        if (!response.ok) {
            throw new Error(`Error HTTP: ${response.status}`);
        }
        
        const data = await response.json();

        if (data.status === 1) {
            // Actualizar parámetros con valores del servidor
            parametrosSistema.precioInstalacion = parseFloat(data.parametros.precio_instalacion_m2) || 0;
            parametrosSistema.garantiaDefault = parseInt(data.parametros.garantia_default_anios) || 5;
            parametrosSistema.ivaPorcentaje = (parseFloat(data.parametros.iva_porcentaje) || 16) / 100;

            // Aplicar valores por defecto en los campos del formulario
            const precioInstalacionElement = document.getElementById('precio_instalacion');
            const garantiaElement = document.getElementById('garantia');
            
            if (precioInstalacionElement) {
                precioInstalacionElement.value = parametrosSistema.precioInstalacion;
            }
            
            if (garantiaElement) {
                garantiaElement.value = parametrosSistema.garantiaDefault;
            }
        } else {
            console.warn('La respuesta del servidor no tuvo estado 1:', data);
        }
    } catch (error) {
        console.error('Error al cargar parámetros:', error);

    }
}

export { parametrosSistema, cargarParametrosSistema };