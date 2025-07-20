<?php
header('Content-Type: application/json');
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

$ROOT =  '../..';
include_once $ROOT . '/db/conexion.php';
include_once $ROOT . '/includes/sesion.php';

if (!tieneSesion()) {
    http_response_code(401);
    die(json_encode(['status' => 0, 'mensaje' => 'No autorizado']));
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    die(json_encode(['status' => 0, 'mensaje' => 'Método no permitido']));
}

$id = isset($_POST['id']) ? intval($_POST['id']) : 0;

if ($id <= 0) {
    http_response_code(400);
    die(json_encode(['status' => 0, 'mensaje' => 'ID inválido']));
}

try {
    $stmt = $conn->prepare("DELETE FROM clientes WHERE id = ?");
    if (!$stmt) {
        throw new Exception("Error en prepare: " . $conn->error);
    }

    $stmt->bind_param("i", $id);
    if (!$stmt->execute()) {
        throw new Exception("Error al eliminar: " . $stmt->error);
    }

    echo json_encode(['status' => 1, 'mensaje' => 'Cliente eliminado correctamente']);
    $stmt->close();
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['status' => 0, 'mensaje' => $e->getMessage()]);
}
