<?php
header('Content-Type: application/json');
require_once '../../db/conexion.php';

$id = $_POST['id'] ?? null;

if (!$id) {
    echo json_encode(['status' => 0, 'mensaje' => 'ID no proporcionado']);
    exit();
}

$sql = "SELECT COUNT(*) FROM cotizacion_extras WHERE id_extra = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $id);
$stmt->execute();
$stmt->bind_result($count);
$stmt->fetch();
$stmt->close();

if ($count > 0) {
    echo json_encode(['status' => 0, 'mensaje' => 'No se puede eliminar este extra porque está siendo usado en una o más cotizaciones']);
    exit();
}

$sql = "UPDATE extras SET estado = 'eliminado', activo = 0 WHERE id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $id);

if ($stmt->execute()) {
    echo json_encode(['status' => 1, 'mensaje' => 'Extra eliminado correctamente']);
} else {
    echo json_encode(['status' => 0, 'mensaje' => 'Error al eliminar el extra']);
}

$stmt->close();
$conn->close();
?>