<?php
// Suprimir output de errores HTML para mantener JSON limpio
ini_set('display_errors', '0');
ini_set('display_startup_errors', '0');
error_reporting(E_ALL);

// Asegurar que siempre devolvamos JSON
header('Content-Type: application/json');
header('Cache-Control: no-cache, must-revalidate');

// Buffer de salida para capturar errores
ob_start();

try {
    $ROOT = '../..';
    include_once "$ROOT/db/conexion.php";
    include_once "$ROOT/includes/sesion.php";

    if (!tieneSesion()) {
        echo json_encode(['success' => false, 'message' => 'No tienes sesión activa']);
        exit();
    }

    $action = $_POST['accion'] ?? $_POST['action'] ?? $_GET['action'] ?? '';

    switch ($action) {
        case 'cambiar_estado':
            cambiarEstadoInstalacion();
            break;
        case 'actualizar_instalacion':
            actualizarInstalacion();
            break;
        case 'actualizar_progreso':
            actualizarProgreso();
            break;
        case 'agregar_material':
            agregarMaterial();
            break;
        case 'actualizar_material':
            actualizarMaterial();
            break;
        case 'eliminar_material':
            eliminarMaterial();
            break;
        case 'agregar_producto':
            agregarProducto();
            break;
        case 'actualizar_producto':
            actualizarProducto();
            break;
        case 'eliminar_producto':
            eliminarProducto();
            break;
        case 'agregar_extra':
            agregarExtra();
            break;
        case 'actualizar_extra':
            actualizarExtra();
            break;
        case 'obtener_otros_productos':
            obtenerOtrosProductos();
            break;
        case 'agregar_producto_adicional':
            agregarProductoAdicional();
            break;
        case 'eliminar_producto_adicional':
            eliminarProductoAdicional();
            break;
        case 'eliminar_extra':
            eliminarExtra();
            break;
        default:
            echo json_encode(['success' => false, 'message' => 'Acción no válida: ' . $action]);
            break;
    }
} catch (Exception $e) {
    // Limpiar cualquier output previo
    ob_clean();
    echo json_encode(['success' => false, 'message' => 'Error del servidor: ' . $e->getMessage()]);
} catch (Error $e) {
    // Limpiar cualquier output previo
    ob_clean();
    echo json_encode(['success' => false, 'message' => 'Error fatal: ' . $e->getMessage()]);
}

// Limpiar buffer
ob_end_flush();

function cambiarEstadoInstalacion() {
    global $conn;
    
    $id_instalacion = (int)$_POST['id_instalacion'];
    $nuevo_estado = $_POST['nuevo_estado'] ?? $_POST['estado'] ?? '';
    
    // Validar estado
    $estados_validos = ['planificada', 'en_progreso', 'completada', 'cancelada'];
    if (!in_array($nuevo_estado, $estados_validos)) {
        echo json_encode(['success' => false, 'message' => 'Estado no válido']);
        return;
    }
    
    // Preparar datos adicionales según el estado
    $fecha_actual = date('Y-m-d');
    $set_adicional = '';
    
    switch ($nuevo_estado) {
        case 'en_progreso':
            $set_adicional = ", fecha_inicio = COALESCE(fecha_inicio, '$fecha_actual')";
            if (isset($_POST['progreso_porcentaje']) && $_POST['progreso_porcentaje'] == 0) {
                $set_adicional .= ", progreso_porcentaje = 1";
            }
            break;
        case 'completada':
            $set_adicional = ", fecha_fin_real = '$fecha_actual', progreso_porcentaje = 100";
            break;
        case 'cancelada':
            // No modificar fechas ni progreso al cancelar
            break;
        case 'planificada':
            $set_adicional = ", fecha_fin_real = NULL";
            break;
    }
    
    $sql = "UPDATE instalaciones SET estado = ? $set_adicional WHERE id = ?";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "si", $nuevo_estado, $id_instalacion);
    
    if (mysqli_stmt_execute($stmt)) {
        echo json_encode(['success' => true, 'message' => 'Estado actualizado correctamente']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Error al actualizar el estado: ' . mysqli_error($conn)]);
    }
}

function actualizarInstalacion() {
    global $conn;
    
    $id_instalacion = (int)$_POST['id_instalacion'];
    $tecnico_responsable = $_POST['tecnico_responsable'] ?? '';
    $fecha_inicio = $_POST['fecha_inicio'] ?? null;
    $fecha_fin_estimada = $_POST['fecha_fin_estimada'] ?? null;
    $progreso_porcentaje = (float)($_POST['progreso_porcentaje'] ?? 0);
    $observaciones = $_POST['observaciones'] ?? '';
    
    // Validar progreso
    if ($progreso_porcentaje < 0) $progreso_porcentaje = 0;
    if ($progreso_porcentaje > 100) $progreso_porcentaje = 100;
    
    // Si tecnico_responsable es un ID, obtener el nombre completo
    $nombre_tecnico = '';
    if (!empty($tecnico_responsable) && is_numeric($tecnico_responsable)) {
        $sql_tecnico = "SELECT CONCAT(nombre, ' ', apellido) as nombre_completo FROM admins WHERE id = ? AND id_rol = 3";
        $stmt_tecnico = mysqli_prepare($conn, $sql_tecnico);
        mysqli_stmt_bind_param($stmt_tecnico, "i", $tecnico_responsable);
        mysqli_stmt_execute($stmt_tecnico);
        $result_tecnico = mysqli_stmt_get_result($stmt_tecnico);
        if ($row = mysqli_fetch_assoc($result_tecnico)) {
            $nombre_tecnico = $row['nombre_completo'];
        }
    } else {
        $nombre_tecnico = $tecnico_responsable;
    }
    
    $sql = "UPDATE instalaciones SET 
                tecnico_responsable = ?, 
                fecha_inicio = ?, 
                fecha_fin_estimada = ?, 
                progreso_porcentaje = ?, 
                observaciones = ?
            WHERE id = ?";
    
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "sssdsi", 
        $nombre_tecnico, 
        $fecha_inicio, 
        $fecha_fin_estimada, 
        $progreso_porcentaje, 
        $observaciones, 
        $id_instalacion
    );
    
    if (mysqli_stmt_execute($stmt)) {
        echo json_encode(['success' => true, 'message' => 'Instalación actualizada correctamente']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Error al actualizar la instalación']);
    }
}

function actualizarProgreso() {
    global $conn;
    
    $id_instalacion = (int)$_POST['id_instalacion'];
    $progreso = (float)($_POST['progreso_porcentaje'] ?? $_POST['progreso'] ?? 0);
    $notas = $_POST['notas'] ?? '';
    
    // Validar progreso
    if ($progreso < 0) $progreso = 0;
    if ($progreso > 100) $progreso = 100;
    
    // Si el progreso es 100%, marcar como completada
    $estado_update = '';
    if ($progreso == 100) {
        $estado_update = ", estado = 'completada', fecha_fin_real = CURDATE()";
    } elseif ($progreso > 0) {
        // Si hay progreso y no estaba en progreso, cambiar estado
        $estado_update = ", estado = 'en_progreso'";
    }
    
    $sql = "UPDATE instalaciones SET 
                progreso_porcentaje = ?, 
                notas_instalacion = CONCAT(COALESCE(notas_instalacion, ''), '\n[', NOW(), '] ', ?)
                $estado_update
            WHERE id = ?";
    
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "dsi", $progreso, $notas, $id_instalacion);
    
    if (mysqli_stmt_execute($stmt)) {
        echo json_encode(['success' => true, 'message' => 'Progreso actualizado correctamente']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Error al actualizar el progreso: ' . mysqli_error($conn)]);
    }
}

function agregarMaterial() {
    global $conn;
    
    $id_instalacion = (int)$_POST['id_instalacion'];
    $nombre_material = $_POST['nombre_material'];
    $cantidad_necesaria = (float)$_POST['cantidad_necesaria'];
    $cantidad_utilizada = (float)($_POST['cantidad_utilizada'] ?? 0);
    $unidad = $_POST['unidad'] ?? '';
    
    // Obtener materiales actuales
    $sql = "SELECT materiales_utilizados FROM instalaciones WHERE id = ?";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "i", $id_instalacion);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $row = mysqli_fetch_assoc($result);
    
    $materiales = json_decode($row['materiales_utilizados'] ?? '[]', true);
    
    // Agregar nuevo material
    $nuevo_material = [
        'nombre' => $nombre_material,
        'cantidad_necesaria' => $cantidad_necesaria,
        'cantidad_utilizada' => $cantidad_utilizada,
        'unidad' => $unidad,
        'fecha_agregado' => date('Y-m-d H:i:s')
    ];
    
    $materiales[] = $nuevo_material;
    
    // Actualizar en la base de datos
    $sql = "UPDATE instalaciones SET materiales_utilizados = ? WHERE id = ?";
    $stmt = mysqli_prepare($conn, $sql);
    $materiales_json = json_encode($materiales);
    mysqli_stmt_bind_param($stmt, "si", $materiales_json, $id_instalacion);
    
    if (mysqli_stmt_execute($stmt)) {
        echo json_encode(['success' => true, 'message' => 'Material agregado correctamente']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Error al agregar el material']);
    }
}

function actualizarMaterial() {
    global $conn;
    
    $id_instalacion = (int)$_POST['id_instalacion'];
    $indice_material = (int)$_POST['indice_material'];
    $cantidad_utilizada = (float)$_POST['cantidad_utilizada'];
    
    // Obtener materiales actuales
    $sql = "SELECT materiales_utilizados FROM instalaciones WHERE id = ?";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "i", $id_instalacion);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $row = mysqli_fetch_assoc($result);
    
    $materiales = json_decode($row['materiales_utilizados'] ?? '[]', true);
    
    // Actualizar material específico
    if (isset($materiales[$indice_material])) {
        $materiales[$indice_material]['cantidad_utilizada'] = $cantidad_utilizada;
        $materiales[$indice_material]['fecha_actualizado'] = date('Y-m-d H:i:s');
        
        // Actualizar en la base de datos
        $sql = "UPDATE instalaciones SET materiales_utilizados = ? WHERE id = ?";
        $stmt = mysqli_prepare($conn, $sql);
        $materiales_json = json_encode($materiales);
        mysqli_stmt_bind_param($stmt, "si", $materiales_json, $id_instalacion);
        
        if (mysqli_stmt_execute($stmt)) {
            echo json_encode(['success' => true, 'message' => 'Material actualizado correctamente']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Error al actualizar el material']);
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'Material no encontrado']);
    }
}

function eliminarMaterial() {
    global $conn;
    
    $id_instalacion = (int)$_POST['id_instalacion'];
    $indice_material = (int)$_POST['indice_material'];
    
    // Obtener materiales actuales
    $sql = "SELECT materiales_utilizados FROM instalaciones WHERE id = ?";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "i", $id_instalacion);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $row = mysqli_fetch_assoc($result);
    
    $materiales = json_decode($row['materiales_utilizados'] ?? '[]', true);
    
    // Eliminar material específico
    if (isset($materiales[$indice_material])) {
        array_splice($materiales, $indice_material, 1);
        
        // Actualizar en la base de datos
        $sql = "UPDATE instalaciones SET materiales_utilizados = ? WHERE id = ?";
        $stmt = mysqli_prepare($conn, $sql);
        $materiales_json = json_encode($materiales);
        mysqli_stmt_bind_param($stmt, "si", $materiales_json, $id_instalacion);
        
        if (mysqli_stmt_execute($stmt)) {
            echo json_encode(['success' => true, 'message' => 'Material eliminado correctamente']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Error al eliminar el material']);
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'Material no encontrado']);
    }
}

function agregarProducto() {
    global $conn;
    
    $id_instalacion = (int)$_POST['id_instalacion'];
    $nombre = $_POST['nombre'];
    $cantidad_necesaria = (float)$_POST['cantidad_necesaria'];
    $cantidad_utilizada = (float)($_POST['cantidad_utilizada'] ?? 0);
    $unidad = $_POST['unidad'] ?? '';
    
    // Obtener productos actuales
    $sql = "SELECT materiales_utilizados FROM instalaciones WHERE id = ?";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "i", $id_instalacion);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $row = mysqli_fetch_assoc($result);
    
    $productos = json_decode($row['materiales_utilizados'] ?? '[]', true);
    
    // Agregar nuevo producto
    $nuevo_producto = [
        'nombre' => $nombre,
        'cantidad_necesaria' => $cantidad_necesaria,
        'cantidad_utilizada' => $cantidad_utilizada,
        'unidad' => $unidad,
        'fecha_agregado' => date('Y-m-d H:i:s')
    ];
    
    $productos[] = $nuevo_producto;
    
    // Actualizar en la base de datos
    $sql = "UPDATE instalaciones SET materiales_utilizados = ? WHERE id = ?";
    $stmt = mysqli_prepare($conn, $sql);
    $productos_json = json_encode($productos);
    mysqli_stmt_bind_param($stmt, "si", $productos_json, $id_instalacion);
    
    if (mysqli_stmt_execute($stmt)) {
        echo json_encode(['success' => true, 'message' => 'Producto agregado correctamente']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Error al agregar el producto']);
    }
}

function actualizarProducto() {
    global $conn;
    
    $id_instalacion = (int)$_POST['id_instalacion'];
    $indice = (int)$_POST['indice'];
    $nombre = $_POST['nombre'];
    $cantidad_necesaria = (float)$_POST['cantidad_necesaria'];
    $cantidad_utilizada = (float)$_POST['cantidad_utilizada'];
    $unidad = $_POST['unidad'];
    
    // Obtener productos actuales
    $sql = "SELECT materiales_utilizados FROM instalaciones WHERE id = ?";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "i", $id_instalacion);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $row = mysqli_fetch_assoc($result);
    
    $productos = json_decode($row['materiales_utilizados'] ?? '[]', true);
    
    // Actualizar producto específico
    if (isset($productos[$indice])) {
        $productos[$indice]['nombre'] = $nombre;
        $productos[$indice]['cantidad_necesaria'] = $cantidad_necesaria;
        $productos[$indice]['cantidad_utilizada'] = $cantidad_utilizada;
        $productos[$indice]['unidad'] = $unidad;
        $productos[$indice]['fecha_actualizado'] = date('Y-m-d H:i:s');
        
        // Actualizar en la base de datos
        $sql = "UPDATE instalaciones SET materiales_utilizados = ? WHERE id = ?";
        $stmt = mysqli_prepare($conn, $sql);
        $productos_json = json_encode($productos);
        mysqli_stmt_bind_param($stmt, "si", $productos_json, $id_instalacion);
        
        if (mysqli_stmt_execute($stmt)) {
            echo json_encode(['success' => true, 'message' => 'Producto actualizado correctamente']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Error al actualizar el producto']);
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'Producto no encontrado']);
    }
}

function eliminarProducto() {
    global $conn;
    
    $id_instalacion = (int)$_POST['id_instalacion'];
    $indice = (int)$_POST['indice'];
    
    // Obtener productos actuales
    $sql = "SELECT materiales_utilizados FROM instalaciones WHERE id = ?";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "i", $id_instalacion);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $row = mysqli_fetch_assoc($result);
    
    $productos = json_decode($row['materiales_utilizados'] ?? '[]', true);
    
    // Eliminar producto específico
    if (isset($productos[$indice])) {
        array_splice($productos, $indice, 1);
        
        // Actualizar en la base de datos
        $sql = "UPDATE instalaciones SET materiales_utilizados = ? WHERE id = ?";
        $stmt = mysqli_prepare($conn, $sql);
        $productos_json = json_encode($productos);
        mysqli_stmt_bind_param($stmt, "si", $productos_json, $id_instalacion);
        
        if (mysqli_stmt_execute($stmt)) {
            echo json_encode(['success' => true, 'message' => 'Producto eliminado correctamente']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Error al eliminar el producto']);
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'Producto no encontrado']);
    }
}

function agregarExtra() {
    global $conn;
    
    $id_instalacion = (int)$_POST['id_instalacion'];
    $nombre = $_POST['nombre'];
    $descripcion = $_POST['descripcion'] ?? '';
    $precio = (float)$_POST['precio'];
    
    // Obtener extras actuales
    $sql = "SELECT extras_adicionales FROM instalaciones WHERE id = ?";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "i", $id_instalacion);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $row = mysqli_fetch_assoc($result);
    
    $extras = json_decode($row['extras_adicionales'] ?? '[]', true);
    
    // Agregar nuevo extra
    $nuevo_extra = [
        'nombre' => $nombre,
        'descripcion' => $descripcion,
        'precio' => $precio,
        'fecha_agregado' => date('Y-m-d H:i:s')
    ];
    
    $extras[] = $nuevo_extra;
    
    // Actualizar en la base de datos
    $sql = "UPDATE instalaciones SET extras_adicionales = ? WHERE id = ?";
    $stmt = mysqli_prepare($conn, $sql);
    $extras_json = json_encode($extras);
    mysqli_stmt_bind_param($stmt, "si", $extras_json, $id_instalacion);
    
    if (mysqli_stmt_execute($stmt)) {
        echo json_encode(['success' => true, 'message' => 'Extra agregado correctamente']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Error al agregar el extra']);
    }
}

function actualizarExtra() {
    global $conn;
    
    $id_instalacion = (int)$_POST['id_instalacion'];
    $indice = (int)$_POST['indice'];
    $nombre = $_POST['nombre'];
    $descripcion = $_POST['descripcion'] ?? '';
    $precio = (float)$_POST['precio'];
    
    // Obtener extras actuales
    $sql = "SELECT extras_adicionales FROM instalaciones WHERE id = ?";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "i", $id_instalacion);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $row = mysqli_fetch_assoc($result);
    
    $extras = json_decode($row['extras_adicionales'] ?? '[]', true);
    
    // Actualizar extra específico
    if (isset($extras[$indice])) {
        $extras[$indice]['nombre'] = $nombre;
        $extras[$indice]['descripcion'] = $descripcion;
        $extras[$indice]['precio'] = $precio;
        $extras[$indice]['fecha_actualizado'] = date('Y-m-d H:i:s');
        
        // Actualizar en la base de datos
        $sql = "UPDATE instalaciones SET extras_adicionales = ? WHERE id = ?";
        $stmt = mysqli_prepare($conn, $sql);
        $extras_json = json_encode($extras);
        mysqli_stmt_bind_param($stmt, "si", $extras_json, $id_instalacion);
        
        if (mysqli_stmt_execute($stmt)) {
            echo json_encode(['success' => true, 'message' => 'Extra actualizado correctamente']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Error al actualizar el extra']);
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'Extra no encontrado']);
    }
}

function eliminarExtra() {
    global $conn;
    
    $id_instalacion = (int)$_POST['id_instalacion'];
    $indice = (int)$_POST['indice'];
    
    // Obtener extras actuales
    $sql = "SELECT extras_adicionales FROM instalaciones WHERE id = ?";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "i", $id_instalacion);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $row = mysqli_fetch_assoc($result);
    
    $extras = json_decode($row['extras_adicionales'] ?? '[]', true);
    
    // Eliminar extra específico
    if (isset($extras[$indice])) {
        array_splice($extras, $indice, 1);
        
        // Actualizar en la base de datos
        $sql = "UPDATE instalaciones SET extras_adicionales = ? WHERE id = ?";
        $stmt = mysqli_prepare($conn, $sql);
        $extras_json = json_encode($extras);
        mysqli_stmt_bind_param($stmt, "si", $extras_json, $id_instalacion);
        
        if (mysqli_stmt_execute($stmt)) {
            echo json_encode(['success' => true, 'message' => 'Extra eliminado correctamente']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Error al eliminar el extra']);
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'Extra no encontrado']);
    }
}

// ===== FUNCIONES PARA PRODUCTOS ADICIONALES =====

function obtenerOtrosProductos() {
    global $conn;
    
    $id_instalacion = $_POST['id_instalacion'] ?? 0;
    
    if (!$id_instalacion) {
        echo json_encode(['success' => false, 'message' => 'ID de instalación requerido']);
        return;
    }
    
    // Obtener productos adicionales de la tabla productos_instalacion
    $sql = "SELECT pi.id, pi.cantidad, p.nombre, p.precio_unitario, p.unidad 
            FROM productos_instalacion pi 
            JOIN productos p ON pi.id_producto = p.id 
            WHERE pi.id_instalacion = ?";
    
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "i", $id_instalacion);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    
    $productos = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $productos[] = $row;
    }
    
    echo json_encode(['success' => true, 'productos' => $productos]);
}

function agregarProductoAdicional() {
    global $conn;
    
    $id_instalacion = $_POST['id_instalacion'] ?? 0;
    $id_producto = $_POST['id_producto'] ?? 0;
    $cantidad = $_POST['cantidad'] ?? 0;
    
    if (!$id_instalacion || !$id_producto || !$cantidad) {
        echo json_encode(['success' => false, 'message' => 'Datos incompletos']);
        return;
    }
    
    // Verificar si el producto ya existe para esta instalación
    $sql_check = "SELECT id FROM productos_instalacion WHERE id_instalacion = ? AND id_producto = ?";
    $stmt_check = mysqli_prepare($conn, $sql_check);
    mysqli_stmt_bind_param($stmt_check, "ii", $id_instalacion, $id_producto);
    mysqli_stmt_execute($stmt_check);
    $result_check = mysqli_stmt_get_result($stmt_check);
    
    if (mysqli_num_rows($result_check) > 0) {
        // Actualizar cantidad existente
        $sql = "UPDATE productos_instalacion SET cantidad = cantidad + ? WHERE id_instalacion = ? AND id_producto = ?";
        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, "dii", $cantidad, $id_instalacion, $id_producto);
    } else {
        // Insertar nuevo producto
        $sql = "INSERT INTO productos_instalacion (id_instalacion, id_producto, cantidad) VALUES (?, ?, ?)";
        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, "iid", $id_instalacion, $id_producto, $cantidad);
    }
    
    if (mysqli_stmt_execute($stmt)) {
        echo json_encode(['success' => true, 'message' => 'Producto agregado correctamente']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Error al agregar producto: ' . mysqli_error($conn)]);
    }
}

function eliminarProductoAdicional() {
    global $conn;
    
    $id_producto_instalacion = $_POST['id_producto_instalacion'] ?? 0;
    
    if (!$id_producto_instalacion) {
        echo json_encode(['success' => false, 'message' => 'ID de producto requerido']);
        return;
    }
    
    $sql = "DELETE FROM productos_instalacion WHERE id = ?";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "i", $id_producto_instalacion);
    
    if (mysqli_stmt_execute($stmt)) {
        echo json_encode(['success' => true, 'message' => 'Producto eliminado correctamente']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Error al eliminar producto: ' . mysqli_error($conn)]);
    }
}

?>
