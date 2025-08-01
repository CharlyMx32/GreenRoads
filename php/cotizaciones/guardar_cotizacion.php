<?php
header('Content-Type: application/json');
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', 'php_errors.log');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    die(json_encode(['status' => 0, 'mensaje' => 'Método no permitido']));
}

require_once '../../db/conexion.php';
require_once '../../includes/sesion.php';

if (!tieneSesion() || !isset($_SESSION['usuario_id'])) {
    echo json_encode(['status' => 0, 'mensaje' => 'No autorizado']);
    exit();
}

$input = file_get_contents('php://input');
$datos = json_decode($input, true);

if (!$datos || json_last_error() !== JSON_ERROR_NONE) {
    echo json_encode(['status' => 0, 'mensaje' => 'Datos inválidos']);
    exit();
}

// Validar campos obligatorios
$camposRequeridos = ['id_cliente', 'tipo_terreno', 'tipo_instalacion', 'garantia', 'total', 'precio_instalacion', 'area_total'];
foreach ($camposRequeridos as $campo) {
    if (!isset($datos[$campo])) {
        echo json_encode(['status' => 0, 'mensaje' => "Falta el campo requerido: $campo"]);
        exit();
    }
}

function obtenerCostoProducto($conn, $idProducto) {
    $query = "SELECT costo_unitario 
             FROM movimientos_inventario 
             WHERE id_producto = ? AND tipo_movimiento = 'entrada'
             ORDER BY fecha DESC 
             LIMIT 1";
             
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $idProducto);
    $stmt->execute();
    $result = $stmt->get_result();
    
    return $result->fetch_assoc()['costo_unitario'] ?? 0;
}

mysqli_begin_transaction($conn);

try {
    $query = "INSERT INTO cotizaciones (
        id_cliente, 
        id_admin, 
        estado, 
        total, 
        area_total,
        tipo_terreno, 
        tipo_instalacion,   
        garantia_anios,     
        precio_instalacion_m2,
        fecha
    ) VALUES (?, ?, 'pendiente', ?, ?, ?, ?, ?, ?, NOW())";

    $stmt = mysqli_prepare($conn, $query);
    if (!$stmt) {
        throw new Exception("Error al preparar la consulta: " . mysqli_error($conn));
    }

    $id_admin = $_SESSION['usuario_id'];
    mysqli_stmt_bind_param(
        $stmt,
        "iiddsssd",
        $datos['id_cliente'],
        $id_admin,
        $datos['total'],
        $datos['area_total'],
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
    if (!empty($datos['rollos']) && is_array($datos['rollos'])) {
        foreach ($datos['rollos'] as $rollo) {
            if (!isset($rollo['id_producto'], $rollo['id_color'], $rollo['cantidad'], $rollo['precio_unitario'])) {
                continue;
            }

            // Verificar disponibilidad
            $sql_verificar = "SELECT SUM(area_m2) AS area_disponible
                FROM inventario_rollos
                WHERE id_producto = ?
                AND id_color = ?
                AND estado = 'disponible'";

            $stmt_verificar = mysqli_prepare($conn, $sql_verificar);
            mysqli_stmt_bind_param($stmt_verificar, "ii", $rollo['id_producto'], $rollo['id_color']);
            mysqli_stmt_execute($stmt_verificar);
            $result_verificar = mysqli_stmt_get_result($stmt_verificar);
            $disponibilidad = mysqli_fetch_assoc($result_verificar);

            if ($disponibilidad['area_disponible'] < $rollo['cantidad']) {
                throw new Exception("No hay suficiente inventario para el producto {$rollo['id_producto']}, color {$rollo['id_color']}");
            }

            $query = "INSERT INTO detalle_cotizacion (
                id_cotizacion, 
                id_producto, 
                id_color,
                cantidad, 
                area_usada,
                precio_unitario
            ) VALUES (?, ?, ?, ?, ?, ?)";

            $stmt = mysqli_prepare($conn, $query);
            mysqli_stmt_bind_param(
                $stmt,
                "iiiddd",
                $id_cotizacion,
                $rollo['id_producto'],
                $rollo['id_color'],
                $rollo['cantidad'],
                $rollo['cantidad'],
                $rollo['precio_unitario']
            );
            mysqli_stmt_execute($stmt);
            $id_detalle = mysqli_insert_id($conn);
            mysqli_stmt_close($stmt);

            // Reservar rollos (FIFO)
            $m2_requeridos = $rollo['cantidad'];
            $query_rollos = "SELECT id, area_m2 
                FROM inventario_rollos 
                WHERE id_producto = ? 
                AND id_color = ? 
                AND estado = 'disponible'
                ORDER BY fecha_ingreso ASC";

            $stmt = mysqli_prepare($conn, $query_rollos);
            mysqli_stmt_bind_param($stmt, "ii", $rollo['id_producto'], $rollo['id_color']);
            mysqli_stmt_execute($stmt);
            $result = mysqli_stmt_get_result($stmt);

            while (($row = mysqli_fetch_assoc($result)) && $m2_requeridos > 0) {
                $area_a_reservar = min($row['area_m2'], $m2_requeridos);

                $query_reserva = "UPDATE inventario_rollos 
                    SET estado = 'reservado', id_cotizacion_reserva = ?
                    WHERE id = ?";

                $stmt_reserva = mysqli_prepare($conn, $query_reserva);
                mysqli_stmt_bind_param($stmt_reserva, "ii", $id_cotizacion, $row['id']);
                mysqli_stmt_execute($stmt_reserva);
                mysqli_stmt_close($stmt_reserva);

                $m2_requeridos -= $area_a_reservar;
            }
        }
    }

    // En la sección de procesar productos generales:
    if (!empty($datos['productos']) && is_array($datos['productos'])) {
        foreach ($datos['productos'] as $producto) {
            if (empty($producto['id_producto'])) continue;

            // Obtener el costo real del producto
            $sqlCosto = "SELECT costo_unitario 
                    FROM movimientos_inventario 
                    WHERE id_producto = ? AND tipo_movimiento = 'entrada'
                    ORDER BY fecha DESC 
                    LIMIT 1";
            $stmtCosto = $conn->prepare($sqlCosto);
            $stmtCosto->bind_param("i", $producto['id_producto']);
            $stmtCosto->execute();
            $resultCosto = $stmtCosto->get_result();
            $costo = $resultCosto->fetch_assoc()['costo_unitario'] ?? 0;

            // Insertar en detalle_cotizacion con el costo real (sin margen)
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
                $costo // Precio unitario = costo real (sin margen)
            );
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);
        }
    }

    // Procesar extras
    if (!empty($datos['extras']) && is_array($datos['extras'])) {
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
    echo json_encode(['status' => 1, 'mensaje' => 'Cotización guardada correctamente', 'id_cotizacion' => $id_cotizacion]);
} catch (Exception $e) {
    mysqli_rollback($conn);
    error_log("Error al guardar cotización: " . $e->getMessage());
    echo json_encode(['status' => 0, 'mensaje' => 'Error al guardar: ' . $e->getMessage()]);
}
