const parametrosSistema = {
    garantiaDefault: null,
    ivaPorcentaje: null,
    tabuladorPrecios: [],
    tabuladorMargenes: []
};

async function cargarParametrosSistema() {
    try {
        // Cargar parámetros básicos
        const responseParametros = await fetch('../../php/configuracion/obtener_parametros.php');
        const dataParametros = await responseParametros.json();
        
        if (dataParametros.status !== 1) {
            throw new Error(dataParametros.mensaje || 'Error en respuesta del servidor');
        }

        // Asignar parámetros básicos
        parametrosSistema.garantiaDefault = parseInt(dataParametros.parametros.garantia_default);
        parametrosSistema.ivaPorcentaje = parseFloat(dataParametros.parametros.iva_porcentaje) / 100;

        // Cargar tabulador de precios de instalación
        const responseTabuladorPrecios = await fetch('../../php/configuracion/obtener_tabulador.php?tipo=precio_instalacion');
        const dataTabuladorPrecios = await responseTabuladorPrecios.json();
        
        if (dataTabuladorPrecios.status === 1) {
            parametrosSistema.tabuladorPrecios = dataTabuladorPrecios.tabuladores;
        }


        const responseTabuladorMargenes = await fetch('../../php/configuracion/obtener_tabulador.php?tipo=margen_utilidad');
        const dataTabuladorMargenes = await responseTabuladorMargenes.json();
        console.log("Datos del tabulador de márgenes:", dataTabuladorMargenes); // Para depuración
        if (dataTabuladorMargenes.status === 1) {
            parametrosSistema.tabuladorMargenes = dataTabuladorMargenes.tabuladores;
        }

    } catch (error) {
        console.error('Error al cargar parámetros:', error);
        throw error;
    }
}

function obtenerPrecioInstalacion(area) {
    if (!parametrosSistema.tabuladorPrecios?.length) {
        return 0;
    }

    // Buscar el rango activo que corresponda al área
    const rango = parametrosSistema.tabuladorPrecios.find(t => 
        t.activo && area >= t.rango_min && area <= t.rango_max
    );

    return rango ? rango.valor : 0; 
}

// Función genérica para obtener cualquier valor de tabulador
function obtenerValorTabulador(tipo, area) {
    const propertyMap = {
        'margen_utilidad': 'tabuladorMargenes',
        'precio_instalacion': 'tabuladorPrecios'
    };
    
    const propertyName = propertyMap[tipo];
    if (!propertyName) {
        console.warn(`Tipo de tabulador no reconocido: ${tipo}`);
        return 0;
    }

    const tabuladores = parametrosSistema[propertyName] || [];
    console.log(`Buscando en tabuladores (${tipo}):`, tabuladores);

    if (!tabuladores || !Array.isArray(tabuladores) || tabuladores.length === 0) {
        console.warn(`No hay tabuladores para el tipo: ${tipo}`);
        return 0;
    }

    const rango = tabuladores.find(t => 
        t.activo && area >= parseFloat(t.rango_min) && area <= parseFloat(t.rango_max)
    );

    console.log(`Rango encontrado para área ${area}:`, rango);
    return rango ? parseFloat(rango.valor) : 0;
}
function obtenerPrecioPorM2(area) {
    if (!parametrosSistema.tabuladorPrecios?.length) {
        return 0;
    }

    const rango = parametrosSistema.tabuladorPrecios.find(t => 
        t.activo && area >= t.rango_min && area <= t.rango_max
    );

    return rango ? rango.valor : 0;
}

export { 
    parametrosSistema, 
    cargarParametrosSistema,
    obtenerPrecioInstalacion,
    obtenerValorTabulador,
    obtenerPrecioPorM2
};