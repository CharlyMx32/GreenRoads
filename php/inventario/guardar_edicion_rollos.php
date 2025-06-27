<?php
require_once '../../db/conexion.php';
require_once '../../includes/sesion.php';
require_once '../../includes/config.php';

header('Content-Type: application/json');

if (!tieneSesion()) {
    http_response_code(401);
    echo json_encode(['status' => 0, 'mensaje' => 'Sesión inválida']);
    exit;
}

$id_producto = filter_input(INPUT_POST, 'id_producto', FILTER_VALIDATE_INT);
$id_color = filter_input(INPUT_POST, 'id_color', FILTER_VALIDATE_INT);
$rollos = $_POST['rollos'] ?? [];

if (!$id_producto || !$id_color || empty($rollos)) {
    echo json_encode(['status' => 0, 'mensaje' => 'Datos incompletos']);
    exit;
}

try {
    foreach ($rollos as $id_rollo => $datos) {
        $id_rollo = intval($id_rollo);
        $largo = floatval($datos['largo'] ?? 0);
        $ancho = floatval($datos['ancho'] ?? 0);
        $eliminar = isset($datos['eliminar']) && $datos['eliminar'] == '1';

        if ($eliminar) {
            $stmt = $conn->prepare("UPDATE inventario_rollos SET estado = 'eliminado' WHERE id = ? AND id_producto = ? AND id_color = ?");
            $stmt->bind_param("iii", $id_rollo, $id_producto, $id_color);
            $stmt->execute();
            $stmt->close();
        } elseif ($largo > 0 && $ancho > 0) {
            $stmt = $conn->prepare("UPDATE inventario_rollos 
                                    SET largo_metros = ?, ancho_metros = ? 
                                    WHERE id = ? AND id_producto = ? AND id_color = ?");
            $stmt->bind_param("ddiii", $largo, $ancho, $id_rollo, $id_producto, $id_color);
            $stmt->execute();
            $stmt->close();
        }
    }

    echo json_encode(['status' => 1, 'mensaje' => 'Rollos actualizados correctamente']);
} catch (Exception $e) {
    echo json_encode(['status' => 0, 'mensaje' => 'Error al actualizar los rollos']);
}
