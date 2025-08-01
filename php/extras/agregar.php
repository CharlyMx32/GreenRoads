<?php
header('Content-Type: application/json');
require_once '../../db/conexion.php';

$data = json_decode(file_get_contents('php://input'), true);

if (empty($data['nombre']) || !isset($data['precio'])) {
    echo json_encode(['status' => 0, 'mensaje' => 'Nombre y precio son campos requeridos']);
    exit();
}

$nombre = trim($data['nombre']);
$precio = floatval($data['precio']);
$descripcion = isset($data['descripcion']) ? trim($data['descripcion']) : null;
$activo = isset($data['activo']) ? intval($data['activo']) : 1;

if ($precio < 0) {
    echo json_encode(['status' => 0, 'mensaje' => 'El precio no puede ser negativo']);
    exit();
}

$sql = "INSERT INTO extras (nombre, precio, descripcion, activo, estado) VALUES (?, ?, ?, ?, ?)";
$stmt = $conn->prepare($sql);

$estado = $activo ? 'activo' : 'inactivo';

$stmt->bind_param("sdsis", $nombre, $precio, $descripcion, $activo, $estado);

if ($stmt->execute()) {
    echo json_encode([
        'status' => 1,
        'mensaje' => 'Extra creado correctamente',
        'id' => $stmt->insert_id
    ]);
} else {
    echo json_encode([
        'status' => 0,
        'mensaje' => 'Error al crear el extra: ' . $conn->error
    ]);
}

$stmt->close();
$conn->close();
?>