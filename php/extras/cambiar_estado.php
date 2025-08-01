<?php
header('Content-Type: application/json');
require_once '../../db/conexion.php';

$id = $_POST['id'] ?? null;
$estado = $_POST['estado'] ?? null;

if (!$id || !$estado) {
    echo json_encode(['status' => 0, 'mensaje' => 'Datos incompletos']);
    exit();
}

$sql = "SELECT id FROM extras WHERE id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    echo json_encode(['status' => 0, 'mensaje' => 'Extra no encontrado']);
    exit();
}

$activo = ($estado === 'activo') ? 1 : 0;
$sql = "UPDATE extras SET estado = ?, activo = ? WHERE id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("sii", $estado, $activo, $id);

if ($stmt->execute()) {
    echo json_encode(['status' => 1, 'mensaje' => 'Estado actualizado correctamente']);
} else {
    echo json_encode(['status' => 0, 'mensaje' => 'Error al actualizar el estado']);
}

$stmt->close();
$conn->close();
?>