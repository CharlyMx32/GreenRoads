<?php
ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
error_reporting(0);

$ROOT = '../..';
include_once $ROOT . '/db/conexion.php';
include_once $ROOT . '/includes/sesion.php';

header('Content-Type: application/json');

if (!$conn) {
    die(json_encode(['status' => 0, 'mensaje' => 'Error de conexión a la base de datos']));
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    die(json_encode(['status' => 0, 'mensaje' => 'Método no permitido']));
}

if (!tieneSesion()) {
    http_response_code(401);
    die(json_encode(['status' => 0, 'mensaje' => 'Sesión no válida']));
}

$id = intval($_POST['id'] ?? 0);
$nombre = trim($_POST['nombre'] ?? '');
$telefono = trim($_POST['telefono'] ?? '');
$email = trim($_POST['email'] ?? '');
$direccion = trim($_POST['direccion'] ?? '');

if ($id <= 0) {
    die(json_encode(['status' => 0, 'mensaje' => 'ID inválido']));
}

if ($nombre === '') {
    die(json_encode(['status' => 0, 'mensaje' => 'El nombre es obligatorio']));
}

if ($email && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    die(json_encode(['status' => 0, 'mensaje' => 'El correo electrónico no es válido']));
}

try {
    $stmt = $conn->prepare("UPDATE clientes SET nombre = ?, telefono = ?, email = ?, direccion = ? WHERE id = ?");
    if (!$stmt) {
        throw new Exception('Error al preparar la consulta: ' . $conn->error);
    }

    $stmt->bind_param('ssssi', $nombre, $telefono, $email, $direccion, $id);

    if (!$stmt->execute()) {
        throw new Exception('Error al ejecutar la consulta: ' . $stmt->error);
    }

    echo json_encode([
        'status' => 1,
        'mensaje' => 'Cliente actualizado correctamente'
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'status' => 0,
        'mensaje' => $e->getMessage()
    ]);
}
