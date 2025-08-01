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
    $largo = isset($_POST['largo']) ? floatval($_POST['largo']) : 0;
    $ancho = isset($_POST['ancho']) ? floatval($_POST['ancho']) : 0;
    $cantidad = isset($_POST['cantidad']) ? intval($_POST['cantidad']) : 0;
    $id_color = isset($_POST['id_color']) ? intval($_POST['id_color']) : 0;
    $costo_unitario = isset($_POST['costo_unitario']) ? floatval($_POST['costo_unitario']) : 0;
    $motivo = isset($_POST['motivo']) ? trim($_POST['motivo']) : 'Primer ingreso';
    $lote_descripcion = isset($_POST['lote_descripcion']) ? trim($_POST['lote_descripcion']) : 'Lote inicial';
    $id_admin = $_SESSION['usuario_id'] ?? null;

    if ($id_producto <= 0) {
        jsonResponse(false, 'ID de producto inválido');
    }

    if ($largo <= 0) {
        jsonResponse(false, 'El largo debe ser mayor a cero');
    }

    if ($ancho <= 0) {
        jsonResponse(false, 'El ancho debe ser mayor a cero');
    }

    if ($cantidad <= 0) {
        jsonResponse(false, 'La cantidad debe ser mayor a cero');
    }

    if ($id_color <= 0) {
        jsonResponse(false, 'Debe seleccionar un color');
    }

    if ($costo_unitario <= 0) {
        jsonResponse(false, 'El costo unitario debe ser mayor a cero');
    }

    if (!$id_admin || !is_numeric($id_admin)) {
        jsonResponse(false, 'ID de administrador no válido');
    }

    $stmt = $conn->prepare("SELECT id, tipo_inventario FROM productos WHERE id = ? LIMIT 1");
    $stmt->bind_param("i", $id_producto);
    $stmt->execute();
    $producto = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$producto) {
        jsonResponse(false, 'Producto no encontrado');
    }

    if ($producto['tipo_inventario'] !== 'rollo') {
        jsonResponse(false, 'Este producto no es de tipo rollo');
    }

    $stmt = $conn->prepare("SELECT COUNT(*) AS total FROM movimientos_inventario WHERE id_producto = ?");
    $stmt->bind_param("i", $id_producto);
    $stmt->execute();
    $tieneMovimientos = $stmt->get_result()->fetch_assoc()['total'] > 0;
    $stmt->close();

    if ($tieneMovimientos) {
        jsonResponse(false, 'Este producto ya tiene movimientos registrados');
    }

    $conn->begin_transaction();

    try {
        // Crear nuevo lote relacionado con el producto
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
        if (!$stmt->execute()) {
            throw new Exception('Error al registrar el movimiento: ' . $stmt->error);
        }
        $stmt->close();

        // Registrar los rollos en inventario_rollos
        $stmt = $conn->prepare("
            INSERT INTO inventario_rollos 
                (id_producto, id_lote, id_color, largo_metros, ancho_metros, costo_unitario, estado) 
            VALUES (?, ?, ?, ?, ?, ?, 'disponible')
        ");
        
        for ($i = 0; $i < $cantidad; $i++) {
            $stmt->bind_param("iiiddd", $id_producto, $id_lote, $id_color, $largo, $ancho, $costo_unitario);
            if (!$stmt->execute()) {
                throw new Exception('Error al registrar el rollo: ' . $stmt->error);
            }
        }
        $stmt->close();

        $conn->commit();

        jsonResponse(true, 'Rollos registrados correctamente', "../../modulos/inventario/editar_rollos.php?id=$id_producto&id_color=$id_color");
    } catch (Exception $e) {
        $conn->rollback();
        throw $e;
    }

} catch (Exception $e) {
    error_log("Error en guardar_rollos.php: " . $e->getMessage());
    jsonResponse(false, 'Error al procesar la solicitud: ' . $e->getMessage());
}