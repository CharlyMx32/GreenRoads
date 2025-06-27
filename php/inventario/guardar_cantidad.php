<?php
$ROOT = '../..';
include_once "$ROOT/db/conexion.php";
include_once "$ROOT/includes/sesion.php";
include_once "$ROOT/includes/config.php";

header('Content-Type: application/json');

$response = [
    'success' => false,
    'message' => 'Error desconocido',
    'redirect' => ''
];

try {
    // Verificar sesión activa
    if (!tieneSesion()) {
        throw new Exception('Sesión no iniciada');
    }

    // Validar método HTTP
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Método no permitido');
    }

    // Validar y sanitizar entrada
    $id_producto = isset($_POST['id_producto']) ? intval($_POST['id_producto']) : 0;
    $nueva_cantidad = isset($_POST['nueva_cantidad']) ? floatval($_POST['nueva_cantidad']) : null;

    if ($id_producto <= 0) {
        throw new Exception('ID de producto inválido');
    }

    if (!is_numeric($nueva_cantidad) || $nueva_cantidad < 0) {
        throw new Exception('La cantidad debe ser un número positivo');
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
        $response['success'] = true;
        $response['message'] = 'No se realizaron cambios (la cantidad es la misma)';
        $response['redirect'] = "../../modulos/inventario/editar_cantidad.php?id=$id_producto";
        echo json_encode($response);
        exit;
    }

    // Preparar el ajuste como movimiento de entrada o salida
    $tipo = $diferencia > 0 ? 'entrada' : 'salida';
    $cantidad_ajuste = abs($diferencia);
    $id_admin = $_SESSION['usuario_id'] ?? null;


    if (!$id_admin || !is_numeric($id_admin)) {
        throw new Exception('ID de administrador no definido en sesión');
    }


    // Registrar el movimiento de ajuste manual
    $sql_mov = "
        INSERT INTO movimientos_inventario 
            (id_producto, cantidad, tipo_movimiento, motivo, id_admin) 
        VALUES (?, ?, ?, 'Ajuste manual', ?)
    ";
    $stmt = $conn->prepare($sql_mov);
    $stmt->bind_param("idsi", $id_producto, $cantidad_ajuste, $tipo, $id_admin);

        if ($stmt->execute()) {
        echo json_encode(['status' => 1, 'mensaje' => 'Producto actualizado correctamente']);
    } else {
        throw new Exception('Error al guardar el ajuste en la base de datos');
    }

    $stmt->close();

} catch (Exception $e) {
    if ($_ENV['APP_ENV'] ?? 'dev' === 'dev') {
        error_log("Error en guardar_cantidad.php: " . $e->getMessage());
    }

    echo json_encode(['status' => 0, 'mensaje' => 'Error al actualizar el producto']);

}

