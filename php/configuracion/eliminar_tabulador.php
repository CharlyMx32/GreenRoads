<?php
require_once '../../db/conexion.php';
header('Content-Type: application/json');

$id = intval($_GET['id'] ?? 0);

if ($id <= 0) {
    echo json_encode(['status' => 0, 'mensaje' => 'ID inválido']);
    exit;
}

// Verificar si el tabulador está en uso
$sqlCheck = "SELECT COUNT(*) AS total FROM cotizaciones 
             WHERE precio_instalacion_m2 IN 
             (SELECT precio_m2 FROM tabuladores WHERE id = ?)";
$stmt = $conn->prepare($sqlCheck);
$stmt->bind_param('i', $id);
$stmt->execute();
$result = $stmt->get_result();
$row = $result->fetch_assoc();

if ($row['total'] > 0) {
    echo json_encode([
        'status' => 0, 
        'mensaje' => 'No se puede eliminar, el tabulador está en uso por cotizaciones existentes'
    ]);
    exit;
}

// Eliminar tabulador
$stmt = $conn->prepare("DELETE FROM tabuladores WHERE id = ?");
$stmt->bind_param('i', $id);

if ($stmt->execute()) {
    echo json_encode([
        'status' => 1,
        'mensaje' => 'Tabulador eliminado correctamente'
    ]);
} else {
    echo json_encode(['status' => 0, 'mensaje' => 'Error al eliminar el tabulador']);
}