<?php
include_once '../../db/conexion.php';

header('Content-Type: application/json');

if (!isset($_POST['id'], $_POST['status'])) {
    echo json_encode(['status' => 0, 'mensaje' => 'Datos incompletos.']);
    exit();
}

$id = intval($_POST['id']);
$status = $_POST['status'];

$estados_validos = ['activo', 'deshabilitado', 'eliminado'];
if (!in_array($status, $estados_validos)) {
    echo json_encode(['status' => 0, 'mensaje' => 'Estado inválido.']);
    exit();
}

$stmt = $conn->prepare("UPDATE productos SET estado = ? WHERE id = ?");
$stmt->bind_param('si', $status, $id);

if ($stmt->execute()) {
    echo json_encode(['status' => 1, 'mensaje' => 'Estado actualizado correctamente.']);
} else {
    echo json_encode(['status' => 0, 'mensaje' => 'Error al actualizar el estado.']);
}
