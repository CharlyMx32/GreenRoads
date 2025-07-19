<?php
// Asegurar que siempre se devuelva JSON
header('Content-Type: application/json');

// Manejar errores adecuadamente
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', 'php_errors.log');

// Validación inicial estricta
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    die(json_encode(['status' => 0, 'mensaje' => 'Método no permitido']));
}

require_once '../../db/conexion.php';
require_once '../../includes/sesion.php';

// Validación de sesión mejorada
if (!tieneSesion()) {
    echo json_encode(['status' => 0, 'mensaje' => 'No autorizado: Sesión no iniciada']);
    exit();
}

if (!isset($_SESSION['usuario_id'])) {
    echo json_encode(['status' => 0, 'mensaje' => 'No autorizado: Administrador no identificado']);
    exit();
}

// Obtener datos del POST
$input = file_get_contents('php://input');
$datos = json_decode($input, true);

if (!$datos || json_last_error() !== JSON_ERROR_NONE) {
    echo json_encode(['status' => 0, 'mensaje' => 'Datos inválidos: ' . json_last_error_msg()]);
    exit();
}

// Validar campos obligatorios
$camposRequeridos = ['id_cliente', 'tipo_terreno', 'tipo_instalacion', 'garantia', 'total', 'precio_instalacion'];
foreach ($camposRequeridos as $campo) {
    if (!isset($datos[$campo])) {
        echo json_encode(['status' => 0, 'mensaje' => "Falta el campo requerido: $campo"]);
        exit();
    }
}

// Validar que el total sea numérico y positivo
if (!is_numeric($datos['total']) || $datos['total'] <= 0) {
    echo json_encode(['status' => 0, 'mensaje' => 'El total debe ser un valor numérico positivo']);
    exit();
}

mysqli_begin_transaction($conn);

try {
    // Insertar la cotización principal
    $query = "INSERT INTO cotizaciones (
        id_cliente, 
        id_admin, 
        estado, 
        total, 
        tipo_terreno, 
        tipo_instalacion,   
        garantia_anios,     
        precio_instalacion_m2,
        fecha
    ) VALUES (?, ?, 'pendiente', ?, ?, ?, ?, ?, NOW())";

    $stmt = mysqli_prepare($conn, $query);
    if (!$stmt) {
        throw new Exception("Error al preparar la consulta: " . mysqli_error($conn));
    }

    $id_admin = $_SESSION['id_admin'];
    mysqli_stmt_bind_param(
        $stmt,
        "iidsssd",
        $datos['id_cliente'],
        $id_admin,
        $datos['total'],
        $datos['tipo_terreno'],
        $datos['tipo_instalacion'],
        $datos['garantia'],
        $datos['precio_instalacion']
    );

    if (!mysqli_stmt_execute($stmt)) {
        throw new Exception("Error al ejecutar la consulta: " . mysqli_stmt_error($stmt));
    }

    $id_cotizacion = mysqli_insert_id($conn);
    mysqli_stmt_close($stmt);

    // Procesar rollos de pasto
    if (!empty($datos['rollos'])) {
        foreach ($datos['rollos'] as $rollo) {
            // 1. Verificar disponibilidad del color
            $sql_verificar = "SELECT 
                SUM(area_m2) AS area_disponible,
                COUNT(*) AS rollos_disponibles
                FROM inventario_rollos
                WHERE id_producto = ?
                AND id_color = ?
                AND estado = 'disponible'";
            
            $stmt_verificar = mysqli_prepare($conn, $sql_verificar);
            mysqli_stmt_bind_param($stmt_verificar, "ii", $rollo['id_producto'], $rollo['id_color']);
            mysqli_stmt_execute($stmt_verificar);
            $result_verificar = mysqli_stmt_get_result($stmt_verificar);
            $disponibilidad = mysqli_fetch_assoc($result_verificar);
            
            // 2. Validar disponibilidad
            if ($disponibilidad['area_disponible'] < $rollo['cantidad']) {
                throw new Exception("No hay suficiente inventario para el producto {$rollo['id_producto']}, color {$rollo['id_color']}");
            }

            // Insertar en detalle_cotizacion
            $query = "INSERT INTO detalle_cotizacion (
                id_cotizacion, 
                id_producto, 
                cantidad, 
                precio_unitario,
                tipo_producto
            ) VALUES (?, ?, ?, ?, 'rollo')";

            $stmt = mysqli_prepare($conn, $query);
            mysqli_stmt_bind_param(
                $stmt,
                "iidd",
                $id_cotizacion,
                $rollo['id_producto'],
                $rollo['cantidad'],
                $rollo['precio_unitario']
            );
            mysqli_stmt_execute($stmt);
            $id_detalle = mysqli_insert_id($conn);
            mysqli_stmt_close($stmt);

            // Insertar en detalle_cotizacion_pasto
            $query_pasto = "INSERT INTO detalle_cotizacion_pasto (
                id_detalle, 
                id_color,
                area_usada
            ) VALUES (?, ?, ?)";

            $stmt_pasto = mysqli_prepare($conn, $query_pasto);
            mysqli_stmt_bind_param(
                $stmt_pasto,
                "iid",
                $id_detalle,
                $rollo['id_color'],
                $rollo['cantidad']
            );
            mysqli_stmt_execute($stmt_pasto);
            mysqli_stmt_close($stmt_pasto);

            // Reservar rollos en inventario
            if (isset($rollo['cantidad_rollos']) && $rollo['cantidad_rollos'] > 0) {
                $query_reserva = "UPDATE inventario_rollos 
                SET estado = 'reservado', 
                    id_cotizacion = ?  // Nuevo campo para rastrear
                WHERE id IN (
                    SELECT id FROM (
                        SELECT id 
                        FROM inventario_rollos 
                        WHERE id_producto = ? 
                        AND id_color = ?
                        AND estado = 'disponible'
                        ORDER BY fecha_ingreso  // Primero los más antiguos
                        LIMIT ?  // Solo la cantidad necesaria
                    ) AS tmp
                )";

                $stmt_reserva = mysqli_prepare($conn, $query_reserva);
                mysqli_stmt_bind_param(
                    $stmt_reserva,
                    "iii",
                    $rollo['id_producto'],
                    $rollo['id_color'],
                    $rollo['cantidad_rollos']
                );
                mysqli_stmt_execute($stmt_reserva);
                mysqli_stmt_close($stmt_reserva);
            }
        }
    }

    // Procesar productos generales
    if (!empty($datos['productos'])) {
        foreach ($datos['productos'] as $producto) {
            if (empty($producto['id_producto'])) continue;

            $query = "INSERT INTO detalle_cotizacion (
                id_cotizacion, 
                id_producto, 
                cantidad, 
                precio_unitario,
                tipo_producto
            ) VALUES (?, ?, ?, ?, 'producto')";

            $stmt = mysqli_prepare($conn, $query);
            mysqli_stmt_bind_param(
                $stmt,
                "iidd",
                $id_cotizacion,
                $producto['id_producto'],
                $producto['cantidad'],
                $producto['precio_unitario']
            );
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);
        }
    }

    // Procesar extras
    if (!empty($datos['extras'])) {
        foreach ($datos['extras'] as $extra) {
            if (empty($extra['id_extra'])) continue;

            $query = "INSERT INTO cotizacion_extras (
                id_cotizacion, 
                id_extra, 
                precio_aplicado
            ) VALUES (?, ?, ?)";

            $stmt = mysqli_prepare($conn, $query);
            mysqli_stmt_bind_param(
                $stmt,
                "iid",
                $id_cotizacion,
                $extra['id_extra'],
                $extra['precio']
            );
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);
        }
    }

    mysqli_commit($conn);

    echo json_encode([
        'status' => 1,
        'mensaje' => 'Cotización guardada correctamente',
        'id_cotizacion' => $id_cotizacion,
        'admin_id' => $id_admin,
        'total' => $datos['total']
    ]);

} catch (Exception $e) {
    mysqli_rollback($conn);
    error_log("Error al guardar cotización: " . $e->getMessage());
    
    echo json_encode([
        'status' => 0,
        'mensaje' => 'Error al guardar la cotización',
        'error' => $e->getMessage(),
        'datos_recibidos' => $datos
    ]);
}