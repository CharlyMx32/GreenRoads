<?php
include_once '../../db/conexion.php';
include_once '../../includes/sesion.php';
header('Content-Type: application/json');

if (!tieneSesion()) {
    echo json_encode(["status" => 0, "mensaje" => "Sesión inválida"]);
    exit;
}

$id_cotizacion = $_POST['id'] ?? null;

if (!$id_cotizacion || !is_numeric($id_cotizacion)) {
    echo json_encode(["status" => 0, "mensaje" => "ID de cotización inválido"]);
    exit;
}

// Cambiar estado a 'aceptada'
$sql = "UPDATE cotizaciones SET estado = 'aceptada' WHERE id = ? AND estado = 'pendiente'";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $id_cotizacion);
$stmt->execute();

if ($stmt->affected_rows > 0) {
    echo json_encode(["status" => 1, "mensaje" => "Cotización confirmada y productos descontados del inventario"]);
} else {
    echo json_encode(["status" => 0, "mensaje" => "No se pudo confirmar la cotización. ¿Ya estaba confirmada?"]);
}
