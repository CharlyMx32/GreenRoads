<?php
$ROOT = '../..';
include_once "$ROOT/db/conexion.php";
include_once "$ROOT/includes/sesion.php";
include_once "$ROOT/includes/config.php";

header('Content-Type: application/json');

// Función para enviar respuestas JSON consistentes
function jsonResponse($success, $message, $redirect = '') {
    echo json_encode([
        'status' => $success ? 1 : 0,
        'mensaje' => $message,
        'redirect' => $redirect
    ]);
    exit;
}

try {
    // Verificar sesión activa
    if (!tieneSesion()) {
        jsonResponse(false, 'Sesión no iniciada', "$URL_ROOT/login");
    }

    // Validar método HTTP
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        jsonResponse(false, 'Método no permitido');
    }

    // Validar y sanitizar entrada
    $id_producto = isset($_POST['id_producto']) ? intval($_POST['id_producto']) : 0;
    $nueva_cantidad = isset($_POST['nueva_cantidad']) ? floatval($_POST['nueva_cantidad']) : null;
    $motivo = isset($_POST['motivo']) ? trim($_POST['motivo']) : 'Ajuste manual';

    if ($id_producto <= 0) {
        jsonResponse(false, 'ID de producto inválido');
    }

    if (!is_numeric($nueva_cantidad) || $nueva_cantidad <= 0) {
        jsonResponse(false, 'La cantidad debe ser un número positivo mayor a cero');
    }

    // Obtener información del producto
    $sql_producto = "SELECT id, nombre FROM productos WHERE id = ? LIMIT 1";
    $stmt = $conn->prepare($sql_producto);
    $stmt->bind_param("i", $id_producto);
    $stmt->execute();
    $producto = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$producto) {
        jsonResponse(false, 'Producto no encontrado');
    }

    // Obtener la cantidad actual de inventario
    $sql_actual = "
        SELECT 
            SUM(CASE WHEN tipo_movimiento = 'entrada' THEN cantidad ELSE 0 END) -
            SUM(CASE WHEN tipo_movimiento IN ('salida', 'reserva') THEN cantidad ELSE 0 END) +
            SUM(CASE WHEN tipo_movimiento = 'liberacion' THEN cantidad ELSE 0 END) AS cantidad
        FROM movimientos_inventario
        WHERE id_producto = ?
    ";
    $stmt = $conn->prepare($sql_actual);
    $stmt->bind_param("i", $id_producto);
    $stmt->execute();
    $res = $stmt->get_result();
    $row = $res->fetch_assoc();
    $stmt->close();

    $cantidad_actual = round(floatval($row['cantidad'] ?? 0), 2);
    $nueva_cantidad = round($nueva_cantidad, 2);
    $diferencia = $nueva_cantidad - $cantidad_actual;

    // Si no hay diferencia, no se realiza ningún cambio
    if (abs($diferencia) < 0.01) {
        jsonResponse(true, 'No se realizaron cambios (la cantidad es la misma)', "../../modulos/inventario/editar_cantidad.php?id=$id_producto");
    }

    // Preparar el ajuste como movimiento de entrada o salida
    $tipo = $diferencia > 0 ? 'entrada' : 'salida';
    $cantidad_ajuste = abs($diferencia);
    $id_admin = $_SESSION['usuario_id'] ?? null;

    if (!$id_admin || !is_numeric($id_admin)) {
        jsonResponse(false, 'ID de administrador no válido');
    }

    // Registrar el movimiento de ajuste
    $sql_mov = "
        INSERT INTO movimientos_inventario 
            (id_producto, cantidad, tipo_movimiento, motivo, id_admin) 
        VALUES (?, ?, ?, ?, ?)
    ";
    $stmt = $conn->prepare($sql_mov);
    $stmt->bind_param("idssi", $id_producto, $cantidad_ajuste, $tipo, $motivo, $id_admin);

    if ($stmt->execute()) {
        jsonResponse(true, 'Inventario actualizado correctamente', "../../modulos/inventario/lista.php");
    } else {
        throw new Exception('Error al guardar el ajuste: ' . $stmt->error);
    }

} catch (Exception $e) {
    error_log("Error en guardar_unidad.php: " . $e->getMessage());
    jsonResponse(false, 'Error al procesar la solicitud: ' . $e->getMessage());
}
?>