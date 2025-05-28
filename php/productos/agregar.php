<?php
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

//$ROOT = $_SERVER['DOCUMENT_ROOT'];
$ROOT = '../..';

include_once $ROOT . '/db/conexion.php';
include_once $ROOT . '/includes/sesion.php';
include_once $ROOT . '/includes/config.php';

header('Content-Type: application/json');

if (!tieneSesion()) {
    echo json_encode(["status" => 0, "mensaje" => "Sesión no válida."]);
    exit;
}

// Validaciones básicas
$nombre = trim($_POST['nombre'] ?? '');
$precio_unitario = $_POST['precio_unitario'] ?? 0;
$ancho_metros = $_POST['ancho_metros'] ?? 0;
$id_unidad = $_POST['id_unidad'] ?? null;

if ($nombre == '' || !$id_unidad) {
    echo json_encode(["status" => 0, "mensaje" => "Nombre e unidad son obligatorios."]);
    exit;
}

// Validar unidad existente
$qUnidad = mysqli_query($conn, "SELECT id FROM unidades WHERE id = $id_unidad");
if (mysqli_num_rows($qUnidad) == 0) {
    echo json_encode(["status" => 0, "mensaje" => "Unidad no válida."]);
    exit;
}

// Manejo de imagen
$nombreImagen = null;

if (isset($_FILES['imagen']) && $_FILES['imagen']['error'] === UPLOAD_ERR_OK) {
    $ext = pathinfo($_FILES['imagen']['name'], PATHINFO_EXTENSION);
    $nombreImagen = uniqid('producto_') . '.' . strtolower($ext);
    $rutaDestino = $ROOT . "/img/productos/" . $nombreImagen;

    if (!move_uploaded_file($_FILES['imagen']['tmp_name'], $rutaDestino)) {
        echo json_encode(["status" => 0, "mensaje" => "No se pudo guardar la imagen."]);
        exit;
    }
}

// Insertar en base de datos
$stmt = mysqli_prepare($conn, "
    INSERT INTO productos (nombre, precio_unitario, ancho_metros, id_unidad, estado, imagen)
    VALUES (?, ?, ?, ?, 'activo', ?)
");

mysqli_stmt_bind_param($stmt, 'sddis', $nombre, $precio_unitario, $ancho_metros, $id_unidad, $nombreImagen);

if (mysqli_stmt_execute($stmt)) {
    echo json_encode(["status" => 1, "mensaje" => "Producto agregado correctamente."]);
} else {
    echo json_encode(["status" => 0, "mensaje" => "Error al guardar el producto."]);
}

mysqli_stmt_close($stmt);
?>
