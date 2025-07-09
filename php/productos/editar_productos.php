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
$precio_unitario = floatval($_POST['precio_unitario'] ?? 0);
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
$consulta = mysqli_query($conn, "SELECT imagen FROM productos WHERE id = $id AND estado <> 'eliminado'");
$producto = mysqli_fetch_assoc($consulta);
if (!$producto) {
    echo json_encode(["status" => 0, "mensaje" => "Producto no encontrado."]);
    exit;
}

// Validar que los IDs de referencia existen
$validaciones = [
    'tipo_producto' => "SELECT id FROM tipo_productos WHERE id = $tipo_producto",
    'unidad' => "SELECT id FROM unidades WHERE id = $id_unidad"
];

if ($id_modelo > 0) {
    $validaciones['modelo'] = "SELECT id FROM modelos WHERE id = $id_modelo";
}

foreach ($validaciones as $campo => $query) {
    $result = mysqli_query($conn, $query);
    if (mysqli_num_rows($result) == 0) {
        echo json_encode(["status" => 0, "mensaje" => ucfirst($campo) . " no válido."]);
        exit;
    }
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
        precio_unitario = ?, 
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
    $precio_unitario,
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