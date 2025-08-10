<?php
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

$ROOT = '../..';

include_once $ROOT . '/db/conexion.php';
include_once $ROOT . '/includes/sesion.php';
include_once $ROOT . '/includes/config.php';

header('Content-Type: application/json');

if (!tieneSesion()) {
    echo json_encode(["status" => 0, "mensaje" => "Sesión no válida."]);
    exit;
}

// Obtener datos del formulario
$id = intval($_POST['id'] ?? 0);
$nombre = trim($_POST['nombre'] ?? '');
$costo_base = floatval($_POST['costo_base'] ?? 0);
// $precio_unitario = floatval($_POST['precio_unitario'] ?? 0);
$tipo_producto = intval($_POST['tipo_producto'] ?? 0);
$tipo_inventario = in_array($_POST['tipo_inventario'] ?? '', ['unidad', 'rollo']) ? $_POST['tipo_inventario'] : 'unidad';
$id_modelo = intval($_POST['id_modelo'] ?? 0);
$id_unidad = intval($_POST['id_unidad'] ?? 0);

// Validaciones básicas
if ($id <= 0 || empty($nombre) || $tipo_producto <= 0 || $id_unidad <= 0) {
    echo json_encode(["status" => 0, "mensaje" => "Datos incompletos o inválidos."]);
    exit;
}

// Verificar que el producto existe
$stmt = $conn->prepare("SELECT imagen FROM productos WHERE id = ? AND estado <> 'eliminado'");
$stmt->bind_param("i", $id);
$stmt->execute();
$producto = $stmt->get_result()->fetch_assoc();
if (!$producto) {
    echo json_encode(["status" => 0, "mensaje" => "Producto no encontrado."]);
    exit;
}

// Validar que los IDs de referencia existen usando prepared statements
$stmt = $conn->prepare("SELECT id FROM tipo_productos WHERE id = ?");
$stmt->bind_param("i", $tipo_producto);
$stmt->execute();
if ($stmt->get_result()->num_rows == 0) {
    echo json_encode(["status" => 0, "mensaje" => "Tipo de producto no válido."]);
    exit;
}
$stmt->close();

$stmt = $conn->prepare("SELECT id FROM unidades WHERE id = ?");
$stmt->bind_param("i", $id_unidad);
$stmt->execute();
if ($stmt->get_result()->num_rows == 0) {
    echo json_encode(["status" => 0, "mensaje" => "Unidad no válida."]);
    exit;
}
$stmt->close();

if ($id_modelo > 0) {
    $stmt = $conn->prepare("SELECT id FROM modelos WHERE id = ?");
    $stmt->bind_param("i", $id_modelo);
    $stmt->execute();
    if ($stmt->get_result()->num_rows == 0) {
        echo json_encode(["status" => 0, "mensaje" => "Modelo no válido."]);
        exit;
    }
    $stmt->close();
}

$nombreImagen = $producto['imagen'];

if (isset($_FILES['imagen']) && $_FILES['imagen']['error'] === UPLOAD_ERR_OK) {
    $ext = pathinfo($_FILES['imagen']['name'], PATHINFO_EXTENSION);
    $ext = in_array(strtolower($ext), ['jpg', 'jpeg', 'png', 'gif', 'webp']) ? strtolower($ext) : 'jpg';
    $nuevoNombreImagen = uniqid('producto_') . '.' . $ext;
    $rutaDestino = $ROOT . "/img/productos/" . $nuevoNombreImagen;

    if (!move_uploaded_file($_FILES['imagen']['tmp_name'], $rutaDestino)) {
        echo json_encode(["status" => 0, "mensaje" => "No se pudo guardar la nueva imagen."]);
        exit;
    }

    // Eliminar imagen anterior si existe
    if ($nombreImagen && file_exists($ROOT . "/img/productos/" . $nombreImagen)) {
        @unlink($ROOT . "/img/productos/" . $nombreImagen);
    }

    $nombreImagen = $nuevoNombreImagen;
}

// Actualizar en la base de datos
$stmt = mysqli_prepare($conn, "
    UPDATE productos 
    SET 
        nombre = ?, 
        costo_base = ?,
        id_tipo_producto = ?,
        tipo_inventario = ?,
        id_modelo = ?,
        id_unidad = ?,
        imagen = ?
    WHERE id = ?
");

$id_modelo = $id_modelo > 0 ? $id_modelo : NULL;

mysqli_stmt_bind_param(
    $stmt, 
    'sdissssi',
    $nombre,
    $costo_base,
    $tipo_producto,
    $tipo_inventario,
    $id_modelo,
    $id_unidad,
    $nombreImagen,
    $id
);

if (mysqli_stmt_execute($stmt)) {
    echo json_encode(["status" => 1, "mensaje" => "Producto actualizado correctamente."]);
} else {
    echo json_encode(["status" => 0, "mensaje" => "Error al actualizar el producto: " . mysqli_error($conn)]);
}

mysqli_stmt_close($stmt);