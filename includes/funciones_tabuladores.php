<?php
/**
 * Funciones para trabajar con tabuladores
 * Estas funciones permiten obtener valores de tabuladores para guardarlos en cotizaciones
 */

/**
 * Obtiene el valor de un tabulador según el tipo y área
 */
function obtenerValorTabulador($conn, $tipo, $area) {
    $sql = "SELECT valor FROM tabuladores 
            WHERE tipo = ? AND activo = 1 
            AND ? BETWEEN rango_min AND rango_max 
            LIMIT 1";
    
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        error_log("Error preparando consulta tabulador: " . $conn->error);
        return 0;
    }
    
    $stmt->bind_param('sd', $tipo, $area);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($row = $result->fetch_assoc()) {
        return floatval($row['valor']);
    }
    
    return 0;
}

/**
 * Obtiene el precio de instalación por m² según el área
 */
function obtenerPrecioInstalacionM2($conn, $area) {
    return obtenerValorTabulador($conn, 'precio_instalacion', $area);
}

/**
 * Obtiene el precio de mano de obra por m² según el área
 */
function obtenerPrecioManoObraM2($conn, $area) {
    return obtenerValorTabulador($conn, 'mano_obra', $area);
}

/**
 * Obtiene el porcentaje de descuento por volumen según el área
 */
function obtenerDescuentoVolumenPorcentaje($conn, $area) {
    return obtenerValorTabulador($conn, 'descuento_volumen', $area);
}

/**
 * Obtiene el precio base del pasto por m² basado en productos específicos
 */
function obtenerPrecioBasePastoM2($conn, $area, $productos_cotizacion = null) {
    
    if (!empty($productos_cotizacion)) {
        $suma_costos = 0;
        $cantidad_productos = 0;
        
        foreach ($productos_cotizacion as $producto) {
            if (!empty($producto['id_producto'])) {
                $sql = "SELECT costo_base FROM productos WHERE id = ? AND tipo_inventario = 'rollo'";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("i", $producto['id_producto']);
                $stmt->execute();
                $result = $stmt->get_result();
                
                if ($row = $result->fetch_assoc()) {
                    $suma_costos += floatval($row['costo_base']);
                    $cantidad_productos++;
                }
                $stmt->close();
            }
        }
        
        if ($cantidad_productos > 0) {
            return $suma_costos / $cantidad_productos;
        }
    }
    $result = $conn->query($sql);
    if ($result && $row = $result->fetch_assoc()) {
        $precio_promedio = floatval($row['precio_promedio']);
        return $precio_promedio > 0 ? $precio_promedio : 1000.00;
    }
    return 1000.00;
}

/**
 * Obtiene todos los valores de tabuladores para una cotización
 */
function obtenerTabuladoresCotizacion($conn, $area, $productos_cotizacion = null) {
    return [
        'precio_instalacion_m2' => obtenerPrecioInstalacionM2($conn, $area),
        'precio_mano_obra_m2' => obtenerPrecioManoObraM2($conn, $area),
        'descuento_volumen_porcentaje' => obtenerDescuentoVolumenPorcentaje($conn, $area),
        'precio_base_pasto_m2' => obtenerPrecioBasePastoM2($conn, $area, $productos_cotizacion)
    ];
}

/**
 * Calcula el IVA según los parámetros del sistema
 */
function obtenerIVA($conn, $subtotal) {
    // Obtener el porcentaje de IVA de la configuración
    $sql = "SELECT valor FROM parametros_sistema WHERE clave = 'sistema_iva_porcentaje' LIMIT 1";
    $result = $conn->query($sql);
    
    if ($result && $row = $result->fetch_assoc()) {
        $iva_porcentaje = floatval($row['valor']) / 100;
        return $subtotal * $iva_porcentaje;
    }
    
    // IVA por defecto del 16% si no está configurado
    return $subtotal * 0.16;
}

/**
 * Obtiene los días de instalación según el área
 */
function obtenerDiasInstalacionPorMetros($conn, $area) {
    $dias = obtenerValorTabulador($conn, 'tiempo_instalacion', $area);
    return $dias > 0 ? $dias : 2; 
}
