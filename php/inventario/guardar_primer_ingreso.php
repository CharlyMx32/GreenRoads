<?php
$ROOT = '../..';
include_once "$ROOT/db/conexion.php";
include_once "$ROOT/includes/sesion.php";
include_once "$ROOT/includes/config.php";

header('Content-Type: application/json');

function jsonResponse($success, $message, $redirect = '') {
    echo json_encode([
        'status' => $success ? 1 : 0,
        'mensaje' => $message,
        'redirect' => $redirect
    ]);
    exit;
}

try {
    if (!tieneSesion()) {
        jsonResponse(false, 'Sesión no iniciada', "$URL_ROOT/login");
    }

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        jsonResponse(false, 'Método no permitido');
    }

    $id_producto = isset($_POST['id_producto']) ? intval($_POST['id_producto']) : 0;
    $cantidad = isset($_POST['cantidad']) ? floatval($_POST['cantidad']) : 0;
    $costo_unitario = isset($_POST['costo_unitario']) ? floatval($_POST['costo_unitario']) : 0;
    $motivo = isset($_POST['motivo']) ? trim($_POST['motivo']) : 'Primer ingreso';
    $lote_descripcion = isset($_POST['lote_descripcion']) ? trim($_POST['lote_descripcion']) : null;
    $id_admin = $_SESSION['usuario_id'] ?? null;

    // Validaciones básicas
    if ($id_producto <= 0) {
        jsonResponse(false, 'ID de producto inválido');
    }

    if ($cantidad <= 0) {
        jsonResponse(false, 'La cantidad debe ser mayor a cero');
    }

    if ($costo_unitario <= 0) {
        jsonResponse(false, 'El costo unitario debe ser mayor a cero');
    }

    if (!$id_admin || !is_numeric($id_admin)) {
        jsonResponse(false, 'ID de administrador no válido');
    }

    // Verificar si el producto existe y obtener su tipo
    $stmt = $conn->prepare("SELECT id, tipo_inventario FROM productos WHERE id = ? LIMIT 1");
    $stmt->bind_param("i", $id_producto);
    $stmt->execute();
    $producto = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$producto) {
        jsonResponse(false, 'Producto no encontrado');
    }

    // Verificar que no tenga movimientos previos
    $stmt = $conn->prepare("SELECT COUNT(*) AS total FROM movimientos_inventario WHERE id_producto = ?");
    $stmt->bind_param("i", $id_producto);
    $stmt->execute();
    $tieneMovimientos = $stmt->get_result()->fetch_assoc()['total'] > 0;
    $stmt->close();

    if ($tieneMovimientos) {
        jsonResponse(false, 'Este producto ya tiene movimientos registrados');
    }

    $id_lote = null;
    $lote_descripcion = $lote_descripcion ?: 'Lote inicial - ' . date('Y-m-d');
    
    // Crear nuevo lote
    $stmt = $conn->prepare("INSERT INTO lotes (descripcion, id_admin, id_producto) VALUES (?, ?, ?)");
    $stmt->bind_param("sii", $lote_descripcion, $id_admin, $id_producto);
    if (!$stmt->execute()) {
        throw new Exception('Error al crear el lote: ' . $stmt->error);
    }
    $id_lote = $stmt->insert_id;
    $stmt->close();

    // Registrar el movimiento de entrada
    $stmt = $conn->prepare("
        INSERT INTO movimientos_inventario 
            (id_producto, id_lote, cantidad, costo_unitario, tipo_movimiento, motivo, id_admin) 
        VALUES (?, ?, ?, ?, 'entrada', ?, ?)
    ");
    $stmt->bind_param("iiddsi", $id_producto, $id_lote, $cantidad, $costo_unitario, $motivo, $id_admin);

    if ($stmt->execute()) {
        jsonResponse(true, 'Primer ingreso registrado correctamente', "../../modulos/inventario/editar_cantidad.php?id=$id_producto");
    } else {
        throw new Exception('Error al registrar el movimiento: ' . $stmt->error);
    }

} catch (Exception $e) {
    error_log("Error en guardar_primer_ingreso.php: " . $e->getMessage());
    jsonResponse(false, 'Error al procesar la solicitud: ' . $e->getMessage());
}