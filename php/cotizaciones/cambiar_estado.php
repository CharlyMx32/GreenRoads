<?php
// Deshabilitar display de errores para JSON válido
ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
error_reporting(E_ALL);

// Limpiar cualquier output previo
if (ob_get_level()) {
    ob_clean();
}

header('Content-Type: application/json');

try {
    require_once '../../db/conexion.php';
    require_once '../../includes/sesion.php';
    require_once '../../includes/funciones_corte_rollos.php';
    require_once '../../includes/funciones_tabuladores.php';
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Error al cargar archivos: ' . $e->getMessage()]);
    exit();
}

if (!tieneSesion()) {
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit();
}

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$estado = isset($_GET['estado']) ? trim($_GET['estado']) : '';

$estadosPermitidos = ['pendiente', 'aceptada', 'rechazada', 'cancelada'];

if ($id <= 0 || !in_array($estado, $estadosPermitidos)) {
    echo json_encode([
        'success' => false,
        'message' => 'Parámetros inválidos',
        'received_id' => $id,
        'received_estado' => $estado
    ]);
    exit();
}

// Obtener el estado actual y verificar si es comparativa
$query_actual = "SELECT estado, es_comparativa, area_total, iva FROM cotizaciones WHERE id = ?";
$stmt_actual = mysqli_prepare($conn, $query_actual);
mysqli_stmt_bind_param($stmt_actual, "i", $id);
mysqli_stmt_execute($stmt_actual);
mysqli_stmt_bind_result($stmt_actual, $estado_actual, $es_comparativa, $area_total, $iva_original);
mysqli_stmt_fetch($stmt_actual);
mysqli_stmt_close($stmt_actual);

// Verificar si ya fue cambiado de pendiente
if ($estado_actual != 'pendiente') {
    echo json_encode([
        'success' => false,
        'message' => 'El estado no puede cambiarse nuevamente',
        'current_status' => $estado_actual
    ]);
    exit();
}

// Si es cotización comparativa y se está aceptando, necesitamos la opción seleccionada
if (($es_comparativa === 'S' || $es_comparativa === '1' || $es_comparativa == 1) && $estado === 'aceptada') {
    $opcion_seleccionada = isset($_GET['opcion']) ? $_GET['opcion'] : null;
    
    // Convertir letras a números si es necesario
    if ($opcion_seleccionada === 'A') $opcion_seleccionada = 1;
    if ($opcion_seleccionada === 'B') $opcion_seleccionada = 2;
    $opcion_seleccionada = (int)$opcion_seleccionada;
    
    if ($opcion_seleccionada === 0 || !in_array($opcion_seleccionada, [1, 2])) {
        // Obtener las opciones disponibles para mostrar al usuario
        $query_opciones = "SELECT 
            dc.opcion_comparativa,
            p.nombre as nombre_producto,
            c.nombre as nombre_color,
            dc.precio_unitario,
            dc.cantidad
        FROM detalle_cotizacion dc
        LEFT JOIN productos p ON dc.id_producto = p.id  
        LEFT JOIN colores c ON dc.id_color = c.id
        WHERE dc.id_cotizacion = ? AND (dc.opcion_comparativa IS NOT NULL OR EXISTS(
            SELECT 1 FROM detalle_cotizacion dc2 
            WHERE dc2.id_cotizacion = dc.id_cotizacion 
            AND dc2.id != dc.id 
            AND dc2.id_producto != dc.id_producto
        ))
        ORDER BY dc.id";
        
        $stmt_opciones = mysqli_prepare($conn, $query_opciones);
        mysqli_stmt_bind_param($stmt_opciones, "i", $id);
        mysqli_stmt_execute($stmt_opciones);
        $result_opciones = mysqli_stmt_get_result($stmt_opciones);
        
        $opciones = [];
        $index = 1;
        while ($row = mysqli_fetch_assoc($result_opciones)) {
            // Si no hay opcion_comparativa, asignarla automáticamente
            if ($row['opcion_comparativa'] === null) {
                $row['opcion_comparativa'] = $index;
            } elseif ($row['opcion_comparativa'] === 'A') {
                $row['opcion_comparativa'] = 1;
            } elseif ($row['opcion_comparativa'] === 'B') {
                $row['opcion_comparativa'] = 2;
            }
            $opciones[] = $row;
            $index++;
        }
        mysqli_stmt_close($stmt_opciones);
        
        echo json_encode([
            'success' => false,
            'message' => 'Selección de opción requerida',
            'es_comparativa' => true,
            'opciones' => $opciones
        ]);
        exit();
    }
}

mysqli_begin_transaction($conn);

try {
    $query = "UPDATE cotizaciones SET estado = ?, id_admin = ? WHERE id = ?";
    $stmt = mysqli_prepare($conn, $query);

    if (!$stmt) {
        throw new Exception("Error al preparar la consulta de actualización: " . mysqli_error($conn));
    }

    $id_admin = $_SESSION['usuario_id'] ?? null;
    mysqli_stmt_bind_param($stmt, "sii", $estado, $id_admin, $id);

    if (!mysqli_stmt_execute($stmt)) {
        throw new Exception("Error al ejecutar la actualización: " . mysqli_stmt_error($stmt));
    }

    // Si es cotización comparativa aceptada, guardar la opción seleccionada
    if (($es_comparativa === 'S' || $es_comparativa === '1' || $es_comparativa == 1) && $estado === 'aceptada' && isset($opcion_seleccionada)) {
        $query_opcion = "UPDATE cotizaciones SET opcion_seleccionada = ? WHERE id = ?";
        $stmt_opcion = mysqli_prepare($conn, $query_opcion);
        mysqli_stmt_bind_param($stmt_opcion, "ii", $opcion_seleccionada, $id);
        
        if (!mysqli_stmt_execute($stmt_opcion)) {
            throw new Exception("Error al guardar la opción seleccionada: " . mysqli_stmt_error($stmt_opcion));
        }
        mysqli_stmt_close($stmt_opcion);
        
        // Calcular y actualizar el total real de la opción seleccionada
        // Ya tenemos el area_total y iva_original de la consulta inicial
        
        // Obtener productos de la opción seleccionada
        $query_productos = "
            SELECT SUM(subtotal) as subtotal_productos
            FROM detalle_cotizacion 
            WHERE id_cotizacion = ? AND (
                opcion_comparativa = ? OR 
                opcion_comparativa = ?
            )
        ";
        $opcion_letra = ($opcion_seleccionada == 1) ? 'A' : 'B';
        
        $stmt_productos = mysqli_prepare($conn, $query_productos);
        mysqli_stmt_bind_param($stmt_productos, "iis", $id, $opcion_seleccionada, $opcion_letra);
        mysqli_stmt_execute($stmt_productos);
        $result_productos = mysqli_stmt_get_result($stmt_productos);
        $datos_productos = mysqli_fetch_assoc($result_productos);
        $subtotal_productos = $datos_productos['subtotal_productos'] ?? 0;
        mysqli_stmt_close($stmt_productos);
        
        // Si no se encontraron productos con opcion_comparativa definida, es una cotización vieja
        // En este caso, necesitamos determinar qué productos corresponden a cada opción
        if ($subtotal_productos == 0) {
            // Para cotizaciones viejas sin opcion_comparativa, usar todos los productos
            // (esto es un fallback para compatibilidad, pero no es ideal)
            $query_productos_legacy = "
                SELECT SUM(subtotal) as subtotal_productos
                FROM detalle_cotizacion 
                WHERE id_cotizacion = ?
            ";
            $stmt_productos_legacy = mysqli_prepare($conn, $query_productos_legacy);
            mysqli_stmt_bind_param($stmt_productos_legacy, "i", $id);
            mysqli_stmt_execute($stmt_productos_legacy);
            $result_productos_legacy = mysqli_stmt_get_result($stmt_productos_legacy);
            $datos_productos_legacy = mysqli_fetch_assoc($result_productos_legacy);
            $subtotal_productos = $datos_productos_legacy['subtotal_productos'] ?? 0;
            mysqli_stmt_close($stmt_productos_legacy);
        }
        
        // Obtener extras
        $query_extras = "
            SELECT SUM(precio_aplicado) as subtotal_extras
            FROM cotizacion_extras 
            WHERE id_cotizacion = ?
        ";
        $stmt_extras = mysqli_prepare($conn, $query_extras);
        mysqli_stmt_bind_param($stmt_extras, "i", $id);
        mysqli_stmt_execute($stmt_extras);
        $result_extras = mysqli_stmt_get_result($stmt_extras);
        $datos_extras = mysqli_fetch_assoc($result_extras);
        $subtotal_extras = $datos_extras['subtotal_extras'] ?? 0;
        mysqli_stmt_close($stmt_extras);
        
        // Calcular instalación y mano de obra
        $precio_instalacion_m2 = obtenerPrecioInstalacionM2($conn, $area_total);
        $costo_instalacion = $area_total * $precio_instalacion_m2;
        
        $precio_mano_obra_m2 = obtenerPrecioManoObraM2($conn, $area_total);
        $costo_mano_obra = $area_total * $precio_mano_obra_m2;
        
        // Calcular total sin IVA
        $subtotal_sin_iva = $subtotal_productos + $subtotal_extras + $costo_instalacion + $costo_mano_obra;
        
        // Verificar si la cotización original tenía IVA aplicado
        $aplicaba_iva_original = $iva_original > 0;
        
        // Calcular IVA solo si la cotización original lo tenía
        if ($aplicaba_iva_original) {
            $iva_monto = obtenerIVA($conn, $subtotal_sin_iva);
        } else {
            $iva_monto = 0;
        }
        
        $total_con_iva = $subtotal_sin_iva + $iva_monto;
        
        // Actualizar el total en la tabla cotizaciones
        $query_update_total = "UPDATE cotizaciones SET total = ?, iva = ? WHERE id = ?";
        $stmt_update_total = mysqli_prepare($conn, $query_update_total);
        mysqli_stmt_bind_param($stmt_update_total, "ddi", $total_con_iva, $iva_monto, $id);
        
        if (!mysqli_stmt_execute($stmt_update_total)) {
            throw new Exception("Error al actualizar el total: " . mysqli_stmt_error($stmt_update_total));
        }
        mysqli_stmt_close($stmt_update_total);
    }

    // Gestionar inventario según el nuevo estado
    if ($estado == 'aceptada') {
        // Cambiar rollos reservados a instalado
        $query_rollos = "UPDATE inventario_rollos 
                        SET estado = 'instalado' 
                        WHERE id_cotizacion_reserva = ? AND estado = 'reservado'";
        
        $stmt_rollos = mysqli_prepare($conn, $query_rollos);
        mysqli_stmt_bind_param($stmt_rollos, "i", $id);
        
        if (!mysqli_stmt_execute($stmt_rollos)) {
            throw new Exception("Error al actualizar estado de rollos");
        }
        
        // Crear instalación automáticamente cuando se acepta la cotización
        // Verificar si ya existe una instalación para esta cotización
        $query_check_instalacion = "SELECT id FROM instalaciones WHERE id_cotizacion = ?";
        $stmt_check = mysqli_prepare($conn, $query_check_instalacion);
        mysqli_stmt_bind_param($stmt_check, "i", $id);
        mysqli_stmt_execute($stmt_check);
        $result_check = mysqli_stmt_get_result($stmt_check);
        
        if (mysqli_num_rows($result_check) == 0) {
            // Solo crear si no existe ya una instalación
            $query_instalacion = "INSERT INTO instalaciones (
                id_cotizacion, 
                estado, 
                progreso_porcentaje, 
                fecha_creacion
            ) VALUES (?, 'planificada', 0, NOW())";
            
            $stmt_instalacion = mysqli_prepare($conn, $query_instalacion);
            mysqli_stmt_bind_param($stmt_instalacion, "i", $id);
            
            if (!mysqli_stmt_execute($stmt_instalacion)) {
                throw new Exception("Error al crear la instalación: " . mysqli_stmt_error($stmt_instalacion));
            }
        }
        
    } elseif ($estado == 'rechazada' || $estado == 'cancelada') {
        if (!liberarRollosCortados($conn, $id)) {
            throw new Exception("Error al liberar rollos cortados");
        }
    }

    mysqli_commit($conn);

    echo json_encode([
        'success' => true,
        'message' => 'Estado actualizado correctamente',
        'new_status' => $estado
    ]);
} catch (Exception $e) {
    mysqli_rollback($conn);

    error_log("Error al cambiar estado de cotización (ID: $id): " . $e->getMessage());

    echo json_encode([
        'success' => false,
        'message' => 'Error al actualizar el estado: ' . $e->getMessage()
    ]);
} finally {
    if (isset($stmt)) {
        mysqli_stmt_close($stmt);
    }
}
?>