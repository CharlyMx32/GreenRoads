<?php
$ROOT = '../..';
include_once "$ROOT/db/conexion.php";
include_once "$ROOT/includes/sesion.php";
include_once "$ROOT/includes/config.php";

header('Content-Type: application/json');

function jsonResponse($success, $message, $redirect = '')
{
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
    $tipo_movimiento = isset($_POST['tipo_movimiento']) ? trim($_POST['tipo_movimiento']) : '';
    $motivo = isset($_POST['motivo']) ? trim($_POST['motivo']) : 'Movimiento de inventario';
    $id_lote = isset($_POST['id_lote']) && $_POST['id_lote'] !== 'nuevo' ? intval($_POST['id_lote']) : null;
    $lote_descripcion = isset($_POST['lote_descripcion']) ? trim($_POST['lote_descripcion']) : null;
    $id_admin = $_SESSION['usuario_id'] ?? null;

    // Validaciones básicas
    if ($id_producto <= 0) {
        jsonResponse(false, 'ID de producto inválido');
    }

    if ($cantidad <= 0) {
        jsonResponse(false, 'La cantidad debe ser mayor a cero');
    }

    if (!in_array($tipo_movimiento, ['entrada', 'salida'])) {
        jsonResponse(false, 'Tipo de movimiento inválido');
    }

    if (!$id_admin || !is_numeric($id_admin)) {
        jsonResponse(false, 'ID de administrador no válido');
    }

    // Para entradas, validar costo
    if ($tipo_movimiento === 'entrada' && $costo_unitario <= 0) {
        jsonResponse(false, 'El costo unitario debe ser mayor a cero');
    }

    // Verificar si el producto existe
    $stmt = $conn->prepare("SELECT id, tipo_inventario FROM productos WHERE id = ? LIMIT 1");
    $stmt->bind_param("i", $id_producto);
    $stmt->execute();
    $producto = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$producto) {
        jsonResponse(false, 'Producto no encontrado');
    }

    // Manejo de lotes para entradas
    if ($tipo_movimiento === 'entrada') {
        if ($id_lote === null && empty($lote_descripcion)) {
            jsonResponse(false, 'Debe proporcionar una descripción para el nuevo lote');
        }

        if ($id_lote === null) {
            // Crear nuevo lote relacionado con el producto
            $stmt = $conn->prepare("INSERT INTO lotes (descripcion, id_admin, id_producto) VALUES (?, ?, ?)");
            $stmt->bind_param("sii", $lote_descripcion, $id_admin, $id_producto);
            if (!$stmt->execute()) {
                throw new Exception('Error al crear el lote: ' . $stmt->error);
            }
            $id_lote = $stmt->insert_id;
            $stmt->close();
        }
    }

    // Validar salidas contra inventario disponible
    if ($tipo_movimiento === 'salida') {
        $sql_disponible = "SELECT 
            COALESCE(SUM(
                CASE 
                    WHEN tipo_movimiento = 'entrada' THEN cantidad
                    WHEN tipo_movimiento IN ('salida', 'reserva') THEN -cantidad
                    ELSE 0 
                END), 0) AS disponible
        FROM movimientos_inventario
        WHERE id_producto = ?";

        if ($id_lote) {
            $sql_disponible .= " AND id_lote = ?";
            $stmt = $conn->prepare($sql_disponible);
            $stmt->bind_param("ii", $id_producto, $id_lote);
        } else {
            $stmt = $conn->prepare($sql_disponible);
            $stmt->bind_param("i", $id_producto);
        }

        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if ($result['disponible'] < $cantidad) {
            jsonResponse(false, 'No hay suficiente inventario disponible');
        }

        // Para salidas, obtener el costo del lote si existe
        if ($id_lote) {
            $stmt = $conn->prepare("
                SELECT m.costo_unitario 
                FROM movimientos_inventario m
                WHERE m.id_lote = ? AND m.id_producto = ? AND m.tipo_movimiento = 'entrada'
                LIMIT 1
            ");
            $stmt->bind_param("ii", $id_lote, $id_producto);
            $stmt->execute();
            $result = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            if ($result) {
                $costo_unitario = $result['costo_unitario'];
            }
        }
    }

    $stmt = $conn->prepare("
        INSERT INTO movimientos_inventario 
            (id_producto, id_lote, cantidad, costo_unitario, tipo_movimiento, motivo, id_admin) 
        VALUES (?, ?, ?, ?, ?, ?, ?)
    ");
    $stmt->bind_param("iiddssi", $id_producto, $id_lote, $cantidad, $costo_unitario, $tipo_movimiento, $motivo, $id_admin);

    if ($stmt->execute()) {
        jsonResponse(true, 'Movimiento registrado correctamente', "../../modulos/inventario/editar_cantidad.php?id=$id_producto");
    } else {
        throw new Exception('Error al registrar el movimiento: ' . $stmt->error);
    }
} catch (Exception $e) {
    error_log("Error en guardar_movimiento.php: " . $e->getMessage());
    jsonResponse(false, 'Error al procesar la solicitud: ' . $e->getMessage());
}