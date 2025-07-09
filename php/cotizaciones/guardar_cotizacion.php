<?php
header('Content-Type: application/json');
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once '../../db/conexion.php';
require_once '../../includes/sesion.php';

if (!tieneSesion()) {
    echo json_encode(['status' => 0, 'mensaje' => 'No autorizado']);
    exit();
}

// Obtener datos del POST
$datos = json_decode(file_get_contents('php://input'), true);

if (!$datos) {
    echo json_encode(['status' => 0, 'mensaje' => 'Datos inválidos']);
    exit();
}

// Validar campos obligatorios
$camposRequeridos = ['id_cliente', 'tipo_terreno', 'tipo_instalacion', 'garantia', 'total'];
foreach ($camposRequeridos as $campo) {
    if (!isset($datos[$campo])) {
        echo json_encode(['status' => 0, 'mensaje' => "Falta el campo requerido: $campo"]);
        exit();
    }
}

mysqli_begin_transaction($conn);

try {
    $query = "INSERT INTO cotizaciones (
            id_cliente, 
            id_admin, 
            estado, 
            total, 
            tipo_terreno, 
            tipo_instalacion,   
            garantia_anios,     
            precio_instalacion_m2 
        ) VALUES (?, ?, 'pendiente', ?, ?, ?, ?, ?)";

    $stmt = mysqli_prepare($conn, $query);
    if (!$stmt) {
        throw new Exception("Error al preparar la consulta: " . mysqli_error($conn));
    }

    $id_admin = $_SESSION['id_admin'] ?? null;
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

    if (!empty($datos['rollos'])) {
        foreach ($datos['rollos'] as $rollo) {
            if (empty($rollo['id_producto'])) continue;

            $query = "INSERT INTO detalle_cotizacion (
                        id_cotizacion, 
                        id_producto, 
                        cantidad, 
                        precio_unitario
                    ) VALUES (?, ?, ?, ?)";

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
        }
    }

    if (!empty($datos['productos'])) {
        foreach ($datos['productos'] as $producto) {
            if (empty($producto['id_producto'])) continue;

            $query = "INSERT INTO detalle_cotizacion (
                        id_cotizacion, 
                        id_producto, 
                        cantidad, 
                        precio_unitario
                    ) VALUES (?, ?, ?, ?)";

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
        'id_cotizacion' => $id_cotizacion
    ]);
} catch (Exception $e) {
    mysqli_rollback($conn);
    error_log("Error al guardar cotización: " . $e->getMessage());

    echo json_encode([
        'status' => 0,
        'mensaje' => 'Error al guardar la cotización',
        'error' => $e->getMessage() 
    ]);
}
