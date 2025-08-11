<?php
// Configuración inicial
ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
error_reporting(0);

$ROOT = '../..';
include_once $ROOT . '/db/conexion.php';
include_once $ROOT . '/includes/sesion.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    die(json_encode([
        'status' => 0,
        'mensaje' => 'Método no permitido'
    ]));
}

if (!tieneSesion()) {
    http_response_code(401);
    die(json_encode([
        'status' => 0,
        'mensaje' => 'Sesión no válida'
    ]));
}

// Obtener datos
$nombre = trim($_POST['nombre'] ?? '');
$costo_base = floatval($_POST['costo_base'] ?? 0);
$tipo_producto = intval($_POST['tipo_producto'] ?? 0);
$id_unidad = intval($_POST['id_unidad'] ?? 0);
$tipo_inventario = $_POST['tipo_inventario'] ?? 'unidad';
$tipo_inventario = in_array($tipo_inventario, ['unidad', 'rollo']) ? $tipo_inventario : 'unidad';
$id_modelo = null;
$colores_rollo = isset($_POST['color_rollo']) ? $_POST['color_rollo'] : [];

// Validaciones
if (empty($nombre)) {
    die(json_encode(['status' => 0, 'mensaje' => 'El nombre del producto es obligatorio']));
}

if ($id_unidad <= 0) {
    die(json_encode(['status' => 0, 'mensaje' => 'Seleccione una unidad válida']));
}

if ($tipo_producto <= 0) {
    die(json_encode(['status' => 0, 'mensaje' => 'Seleccione un tipo de producto válido']));
}

if (!in_array($tipo_inventario, ['unidad', 'rollo'])) {
    die(json_encode(['status' => 0, 'mensaje' => 'Tipo de inventario inválido']));
}

if ($tipo_inventario === 'rollo') {
    $id_modelo = isset($_POST['id_modelo']) ? intval($_POST['id_modelo']) : null;

    if ($id_modelo <= 0) {
        die(json_encode(['status' => 0, 'mensaje' => 'Seleccione un modelo válido para el pasto']));
    }

    // Validar colores para rollos
    if (empty($colores_rollo)) {
        die(json_encode(['status' => 0, 'mensaje' => 'Seleccione al menos un color para el rollo']));
    }
}

// Manejo de imagen
$nombreImagen = null;
try {
    if (isset($_FILES['imagen']) && $_FILES['imagen']['error'] === UPLOAD_ERR_OK) {
        $permitidos = ['image/jpeg', 'image/png', 'image/gif'];
        $tipo = mime_content_type($_FILES['imagen']['tmp_name']);

        if (!in_array($tipo, $permitidos)) {
            die(json_encode(['status' => 0, 'mensaje' => 'Solo se permiten imágenes JPEG, PNG o GIF']));
        }

        if ($_FILES['imagen']['size'] > 2 * 1024 * 1024) {
            die(json_encode(['status' => 0, 'mensaje' => 'La imagen no debe exceder 2MB']));
        }

        $ext = pathinfo($_FILES['imagen']['name'], PATHINFO_EXTENSION);
        $nombreImagen = uniqid('prod_') . '.' . $ext;
        $destino = $ROOT . '/img/productos/' . $nombreImagen;

        if (!move_uploaded_file($_FILES['imagen']['tmp_name'], $destino)) {
            die(json_encode(['status' => 0, 'mensaje' => 'Error al guardar la imagen']));
        }
    }

    // Iniciar transacción
    $conn->begin_transaction();

    // Insertar producto
    $stmt = $conn->prepare("INSERT INTO productos (
        nombre, costo_base, id_unidad, id_tipo_producto, tipo_inventario, imagen, id_modelo
    ) VALUES (?, ?, ?, ?, ?, ?, ?)");

    if (!$stmt) {
        throw new Exception('Error al preparar la consulta: ' . $conn->error);
    }

    $id_modelo_sql = $id_modelo > 0 ? $id_modelo : null;

    $stmt->bind_param(
        'sdiissi', 
        $nombre,
        $costo_base,
        $id_unidad,
        $tipo_producto,
        $tipo_inventario,
        $nombreImagen,
        $id_modelo_sql
    );

    if (!$stmt->execute()) {
        throw new Exception('Error al ejecutar la consulta: ' . $stmt->error);
    }

    $id_producto = $stmt->insert_id;

    // Si es un rollo, guardar los colores asociados
    if ($tipo_inventario === 'rollo' && !empty($colores_rollo)) {
        foreach ($colores_rollo as $id_color) {
            $id_color = intval($id_color);
            if ($id_color > 0) {
                $stmt_color = $conn->prepare("INSERT INTO producto_colores (id_producto, id_color) VALUES (?, ?)");
                if (!$stmt_color) {
                    throw new Exception('Error al preparar consulta de colores: ' . $conn->error);
                }
                $stmt_color->bind_param('ii', $id_producto, $id_color);
                if (!$stmt_color->execute()) {
                    throw new Exception('Error al guardar colores: ' . $stmt_color->error);
                }
                $stmt_color->close();
            }
        }
    }

    // Confirmar transacción
    $conn->commit();

    echo json_encode([
        'status' => 1,
        'mensaje' => 'Producto agregado correctamente',
        'id' => $id_producto
    ]);
} catch (Exception $e) {
    // Revertir transacción en caso de error
    $conn->rollback();
    
    if ($nombreImagen && file_exists($destino)) {
        unlink($destino);
    }

    error_log('Error en agregar.php: ' . $e->getMessage());

    http_response_code(500);
    echo json_encode([
        'status' => 0,
        'mensaje' => 'Error interno del servidor: ' . $e->getMessage()
    ]);
}