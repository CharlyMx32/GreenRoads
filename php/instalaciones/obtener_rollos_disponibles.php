<?php
ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
error_reporting(E_ALL);

if (ob_get_level()) {
    ob_clean();
}

header('Content-Type: application/json');

try {
    require_once '../../db/conexion.php';
    require_once '../../includes/sesion.php';
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Error al cargar archivos: ' . $e->getMessage()]);
    exit();
}

if (!tieneSesion()) {
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit();
}

$id_cotizacion = isset($_GET['id_cotizacion']) ? (int)$_GET['id_cotizacion'] : 0;

if ($id_cotizacion <= 0) {
    echo json_encode(['success' => false, 'message' => 'ID de cotización inválido']);
    exit();
}

try {
    // Primero, verificar si la cotización es comparativa y tiene una opción seleccionada
    $query_cotizacion = "SELECT es_comparativa, opcion_seleccionada FROM cotizaciones WHERE id = ?";
    $stmt_cotizacion = mysqli_prepare($conn, $query_cotizacion);
    mysqli_stmt_bind_param($stmt_cotizacion, "i", $id_cotizacion);
    mysqli_stmt_execute($stmt_cotizacion);
    $result_cotizacion = mysqli_stmt_get_result($stmt_cotizacion);
    $cotizacion_info = mysqli_fetch_assoc($result_cotizacion);
    mysqli_stmt_close($stmt_cotizacion);

    // Obtener los productos/rollos requeridos para esta cotización
    $query_productos = "
        SELECT 
            dc.id,
            dc.id_producto,
            dc.id_color,
            dc.cantidad,
            dc.area_usada as area_necesaria,
            p.nombre as producto_nombre,
            c.nombre as color_nombre,
            c.codigo_hex as color_hex
        FROM detalle_cotizacion dc
        INNER JOIN productos p ON dc.id_producto = p.id
        INNER JOIN colores c ON dc.id_color = c.id
        WHERE dc.id_cotizacion = ? AND p.tipo_inventario = 'rollo'
    ";

    // Si es comparativa y hay una opción seleccionada, filtrar por esa opción
    $es_comparativa = ($cotizacion_info && ($cotizacion_info['es_comparativa'] == 1 || $cotizacion_info['es_comparativa'] == 'S'));
    $opcion_seleccionada = $cotizacion_info ? $cotizacion_info['opcion_seleccionada'] : null;

    if ($es_comparativa && !empty($opcion_seleccionada)) {
        $query_productos .= " AND dc.opcion_comparativa = ?";
    }

    $query_productos .= " ORDER BY dc.id";
    
    $stmt_productos = mysqli_prepare($conn, $query_productos);

    if ($es_comparativa && !empty($opcion_seleccionada)) {
        mysqli_stmt_bind_param($stmt_productos, "is", $id_cotizacion, $opcion_seleccionada);
    } else {
        mysqli_stmt_bind_param($stmt_productos, "i", $id_cotizacion);
    }
    mysqli_stmt_execute($stmt_productos);
    $result_productos = mysqli_stmt_get_result($stmt_productos);
    
    $productos_necesarios = [];
    while ($producto = mysqli_fetch_assoc($result_productos)) {
        $productos_necesarios[] = $producto;
    }
    mysqli_stmt_close($stmt_productos);
    
    if (empty($productos_necesarios)) {
        echo json_encode(['success' => false, 'message' => 'No se encontraron productos de tipo rollo en esta cotización']);
        exit();
    }
    
    // Para cada producto, obtener los rollos disponibles en inventario
    $rollos_por_producto = [];
    
    foreach ($productos_necesarios as $producto) {
        // Obtener rollos disponibles para este producto y color
        $query_rollos = "
            SELECT 
                ir.id,
                ir.largo_metros,
                ir.ancho_metros,
                ir.area_m2,
                ir.id_lote,
                ir.fecha_ingreso,
                ir.costo_unitario,
                ir.estado,
                p.nombre as producto_nombre,
                c.nombre as color_nombre,
                c.codigo_hex as color_hex
            FROM inventario_rollos ir
            INNER JOIN productos p ON ir.id_producto = p.id
            INNER JOIN colores c ON ir.id_color = c.id
            WHERE ir.id_producto = ? 
                AND ir.id_color = ? 
                AND ir.estado = 'disponible'
                AND ir.area_m2 > 0
            ORDER BY ir.area_m2 DESC, ir.fecha_ingreso ASC
        ";
        
        $stmt_rollos = mysqli_prepare($conn, $query_rollos);
        mysqli_stmt_bind_param($stmt_rollos, "ii", $producto['id_producto'], $producto['id_color']);
        mysqli_stmt_execute($stmt_rollos);
        $result_rollos = mysqli_stmt_get_result($stmt_rollos);
        
        $rollos_disponibles = [];
        $area_restante = $producto['area_necesaria'];
        
        while ($rollo = mysqli_fetch_assoc($result_rollos)) {
            // Determinar si este rollo es sugerido usar
            $sugerido_usar = false;
            $area_a_usar = 0;
            $metros_a_usar = 0;
            $metros_sobrantes = $rollo['largo_metros'];
            
            if ($area_restante > 0) {
                $sugerido_usar = true;
                $area_rollo = $rollo['area_m2'];
                
                if ($area_rollo <= $area_restante) {
                    // Usar todo el rollo
                    $area_a_usar = $area_rollo;
                    $metros_a_usar = $rollo['largo_metros'];
                    $metros_sobrantes = 0;
                    $area_restante -= $area_rollo;
                } else {
                    // Usar solo parte del rollo
                    $area_a_usar = $area_restante;
                    $metros_a_usar = $area_restante / $rollo['ancho_metros'];
                    $metros_sobrantes = $rollo['largo_metros'] - $metros_a_usar;
                    $area_restante = 0;
                }
            }
            
            $rollo['sugerido_usar'] = $sugerido_usar;
            $rollo['area_a_usar'] = $area_a_usar;
            $rollo['metros_a_usar'] = $metros_a_usar;
            $rollo['metros_sobrantes'] = $metros_sobrantes;
            
            $rollos_disponibles[] = $rollo;
        }
        mysqli_stmt_close($stmt_rollos);
        
        // Calcular área total disponible
        $area_total_disponible = 0;
        foreach ($rollos_disponibles as $rollo) {
            $area_total_disponible += $rollo['area_m2'];
        }
        
        $rollos_por_producto[] = [
            'producto' => [
                'id_producto' => $producto['id_producto'],
                'id_color' => $producto['id_color'],
                'producto_nombre' => $producto['producto_nombre'],
                'color_nombre' => $producto['color_nombre'],
                'color_hex' => $producto['color_hex'],
                'area_necesaria' => $producto['area_necesaria'],
                'area_total_disponible' => $area_total_disponible
            ],
            'rollos_disponibles' => $rollos_disponibles
        ];
    }
    
    echo json_encode([
        'success' => true,
        'rollos_por_producto' => $rollos_por_producto
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error del servidor: ' . $e->getMessage()
    ]);
}
?>
