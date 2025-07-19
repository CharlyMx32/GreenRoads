<?php
header('Content-Type: application/json'); // Asegura respuesta JSON

// Activar errores para debugging (no usar en producción sin control)
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

$ROOT = '../../';
include_once $ROOT . '/db/conexion.php';
include_once $ROOT . '/includes/sesion.php';

if (!tieneSesion()) {
    echo json_encode(['status' => 0, 'mensaje' => 'No autorizado']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = isset($_POST['id']) ? intval($_POST['id']) : 0;

    if ($id <= 0) {
        echo json_encode(['status' => 0, 'mensaje' => 'ID inválido']);
        exit();
    }

    $stmt = $conn->prepare("DELETE FROM clientes WHERE id = ?");
    $stmt->bind_param("i", $id);

    if ($stmt->execute()) {
        echo json_encode(['status' => 1, 'mensaje' => 'Cliente eliminado correctamente']);
    } else {
        echo json_encode(['status' => 0, 'mensaje' => 'Error al eliminar el cliente']);
    }

    $stmt->close();
} else {
    echo json_encode(['status' => 0, 'mensaje' => 'Método no permitido']);
}
?>
