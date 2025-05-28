<?php
ini_set('display_errors', '1');
error_reporting(E_ALL);

$ROOT = '../..';
include_once $ROOT.'/db/conexion.php';
include_once $ROOT.'/includes/sesion.php';
include_once $ROOT.'/includes/config.php';

header('Content-Type: application/json');

if (!tieneSesion()) {
    echo json_encode(["status" => 0, "mensaje" => "Sesión no válida."]);
    exit;
}

$id = intval($_POST['id'] ?? 0);
if ($id <= 0) {
    echo json_encode(["status" => 0, "mensaje" => "ID inválido."]);
    exit;
}

$producto = mysqli_fetch_assoc(mysqli_query($conn, "SELECT imagen FROM productos WHERE id = $id"));
if (!$producto) {
    echo json_encode(["status" => 0, "mensaje" => "Producto no encontrado."]);
    exit;
}

if (!isset($_FILES['imagen']) || $_FILES['imagen']['error'] !== UPLOAD_ERR_OK) {
    echo json_encode(["status" => 0, "mensaje" => "Imagen inválida."]);
    exit;
}

$ext = pathinfo($_FILES['imagen']['name'], PATHINFO_EXTENSION);
$nombreImagen = uniqid('producto_') . '.' . strtolower($ext);
$rutaDestino = $ROOT . "/img/productos/" . $nombreImagen;

if (!move_uploaded_file($_FILES['imagen']['tmp_name'], $rutaDestino)) {
    echo json_encode(["status" => 0, "mensaje" => "Error al guardar la imagen."]);
    exit;
}

// Eliminar imagen anterior si existe
if ($producto['imagen'] && file_exists($ROOT . "/img/productos/" . $producto['imagen'])) {
    unlink($ROOT . "/img/productos/" . $producto['imagen']);
}

mysqli_query($conn, "UPDATE productos SET imagen = '$nombreImagen' WHERE id = $id");

echo json_encode(["status" => 1, "mensaje" => "Imagen actualizada correctamente.", "imagen" => $nombreImagen]);
