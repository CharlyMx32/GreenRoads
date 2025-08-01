<?php
require_once '../../db/conexion.php';
require_once '../../includes/sesion.php';
require_once '../../includes/config.php';

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

header('Content-Type: application/json');

try {
    if (!tieneSesion()) {
        throw new Exception('Sesión inválida', 401);
    }

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Método no permitido', 405);
    }

    // Obtener y validar datos básicos
    $id_producto = filter_input(INPUT_POST, 'id_producto', FILTER_VALIDATE_INT);
    $id_color = filter_input(INPUT_POST, 'id_color', FILTER_VALIDATE_INT);
    $cantidad = filter_input(INPUT_POST, 'cantidad', FILTER_VALIDATE_INT);
    $motivo = filter_input(INPUT_POST, 'motivo');
    $id_admin = $_SESSION['id_admin'] ?? $_SESSION['usuario_id'] ?? null;

    // Validaciones básicas
    if (!$id_producto) {
        throw new Exception('Producto no especificado', 400);
    }
    if (!$id_color) {
        throw new Exception('Color no especificado', 400);
    }
    if (!$cantidad || $cantidad <= 0) {
        throw new Exception('La cantidad debe ser mayor a cero', 400);
    }
    if (!$id_admin) {
        throw new Exception('ID de administrador no válido', 400);
    }

    $largo = filter_input(INPUT_POST, 'largo', FILTER_VALIDATE_FLOAT);
    $ancho = filter_input(INPUT_POST, 'ancho', FILTER_VALIDATE_FLOAT);
    $costo_unitario = filter_input(INPUT_POST, 'costo_unitario', FILTER_VALIDATE_FLOAT);
    $id_lote = filter_input(INPUT_POST, 'id_lote');
    $lote_descripcion = filter_input(INPUT_POST, 'lote_descripcion');

    if (!$largo || $largo <= 0) {
        throw new Exception('El largo debe ser mayor a cero', 400);
    }
    if (!$ancho || $ancho <= 0) {
        throw new Exception('El ancho debe ser mayor a cero', 400);
    }
    if (!$costo_unitario || $costo_unitario <= 0) {
        throw new Exception('El costo unitario debe ser mayor a cero', 400);
    }

    // Verificar que el producto existe y es de tipo rollo
    $stmt = $conn->prepare("SELECT id, nombre, tipo_inventario FROM productos WHERE id = ? LIMIT 1");
    $stmt->bind_param("i", $id_producto);
    $stmt->execute();
    $producto = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$producto) {
        throw new Exception('Producto no encontrado', 404);
    }
    if ($producto['tipo_inventario'] !== 'rollo') {
        throw new Exception('El producto no es de tipo rollo', 400);
    }

    // Verificar que el color existe
    $stmt = $conn->prepare("SELECT id, nombre FROM colores WHERE id = ? LIMIT 1");
    $stmt->bind_param("i", $id_color);
    $stmt->execute();
    $color = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$color) {
        throw new Exception('Color no encontrado', 404);
    }

    $conn->begin_transaction();

    // Proceso para ENTRADAS (Agregar rollos)
    $lote_id = null;

    // Manejo de lotes
    if ($id_lote === 'nuevo') {
        if (empty($lote_descripcion)) {
            throw new Exception('Debe proporcionar una descripción para el nuevo lote', 400);
        }

        // Crear nuevo lote
        $stmt = $conn->prepare("INSERT INTO lotes (descripcion, id_admin, id_producto) VALUES (?, ?, ?)");
        $stmt->bind_param("sii", $lote_descripcion, $id_admin, $id_producto);

        if (!$stmt->execute()) {
            throw new Exception('Error al crear el lote: ' . $stmt->error);
        }

        $lote_id = $stmt->insert_id;
        $stmt->close();
    } elseif (is_numeric($id_lote)) {
        // Verificar que el lote existe
        $stmt = $conn->prepare("SELECT id FROM lotes WHERE id = ? LIMIT 1");
        $stmt->bind_param("i", $id_lote);
        $stmt->execute();
        $lote = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$lote) {
            throw new Exception('Lote no encontrado', 404);
        }
        $lote_id = (int)$id_lote;
    }

    // Insertar rollos
    for ($i = 0; $i < $cantidad; $i++) {
        $stmt = $conn->prepare("INSERT INTO inventario_rollos 
                             (id_producto, id_lote, id_color, largo_metros, ancho_metros, costo_unitario, estado) 
                             VALUES (?, ?, ?, ?, ?, ?, 'disponible')");
        $stmt->bind_param("iidddd", $id_producto, $lote_id, $id_color, $largo, $ancho, $costo_unitario);

        if (!$stmt->execute()) {
            throw new Exception('Error al insertar rollo: ' . $stmt->error);
        }
        $stmt->close();
    }

    // Registrar movimiento de entrada
    $stmt = $conn->prepare("INSERT INTO movimientos_inventario 
                         (id_producto, id_lote, cantidad, costo_unitario, tipo_movimiento, motivo, id_admin) 
                         VALUES (?, ?, ?, ?, 'entrada', ?, ?)");
    $stmt->bind_param("iiddsi", $id_producto, $lote_id, $cantidad, $costo_unitario, $motivo, $id_admin);

    if (!$stmt->execute()) {
        throw new Exception('Error al registrar movimiento: ' . $stmt->error);
    }
    $stmt->close();

    $conn->commit();

    echo json_encode([
        'status' => 1,
        'mensaje' => "Rollos agregados correctamente",
        'data' => [
            'cantidad' => $cantidad,
            'producto' => $producto['nombre'],
            'lote_id' => $lote_id
        ]
    ]);
} catch (Exception $e) {
    if (isset($conn) && method_exists($conn, 'rollback')) {
        $conn->rollback();
    }

    http_response_code($e->getCode() >= 400 ? $e->getCode() : 500);
    echo json_encode([
        'status' => 0,
        'mensaje' => $e->getMessage(),
        'error' => $e->getCode()
    ]);
}