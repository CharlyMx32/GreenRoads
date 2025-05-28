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

$id = intval($_POST['id'] ?? 0);
$nombre = trim($_POST['nombre'] ?? '');
$precio_unitario = floatval($_POST['precio_unitario'] ?? 0);
$ancho_metros = floatval($_POST['ancho_metros'] ?? 0);
$id_unidad = intval($_POST['id_unidad'] ?? 0);

if ($id <= 0 || $nombre == '' || !$id_unidad) {
    echo json_encode(["status" => 0, "mensaje" => "Datos incompletos."]);
    exit;
}

// Verificar que el producto existe
$consulta = mysqli_query($conn, "SELECT imagen FROM productos WHERE id = $id AND estado <> 'eliminado'");
$producto = mysqli_fetch_assoc($consulta);
if (!$producto) {
    echo json_encode(["status" => 0, "mensaje" => "Producto no encontrado."]);
    exit;
}

// Validar unidad existente
$qUnidad = mysqli_query($conn, "SELECT id FROM unidades WHERE id = $id_unidad");
if (mysqli_num_rows($qUnidad) == 0) {
    echo json_encode(["status" => 0, "mensaje" => "Unidad no válida."]);
    exit;
}

// Manejo de imagen
$nombreImagen = $producto['imagen']; // Por defecto, conservar la actual

if (isset($_FILES['imagen']) && $_FILES['imagen']['error'] === UPLOAD_ERR_OK) {
    $ext = pathinfo($_FILES['imagen']['name'], PATHINFO_EXTENSION);
    $nuevoNombreImagen = uniqid('producto_') . '.' . strtolower($ext);
    $rutaDestino = $ROOT . "/img/productos/" . $nuevoNombreImagen;

    if (!move_uploaded_file($_FILES['imagen']['tmp_name'], $rutaDestino)) {
        echo json_encode(["status" => 0, "mensaje" => "No se pudo guardar la nueva imagen."]);
        exit;
    }

    // (Opcional) eliminar imagen anterior si existe
    if ($nombreImagen && file_exists($ROOT . "/img/productos/" . $nombreImagen)) {
        unlink($ROOT . "/img/productos/" . $nombreImagen);
    }

    $nombreImagen = $nuevoNombreImagen;
}

// Actualizar en la base de datos
$stmt = mysqli_prepare($conn, "
    UPDATE productos
    SET nombre = ?, precio_unitario = ?, ancho_metros = ?, id_unidad = ?, imagen = ?
    WHERE id = ?
");

mysqli_stmt_bind_param($stmt, 'sddisi', $nombre, $precio_unitario, $ancho_metros, $id_unidad, $nombreImagen, $id);

if (mysqli_stmt_execute($stmt)) {
    echo json_encode(["status" => 1, "mensaje" => "Producto actualizado correctamente."]);
} else {
    echo json_encode(["status" => 0, "mensaje" => "Error al actualizar el producto."]);
}

mysqli_stmt_close($stmt);
