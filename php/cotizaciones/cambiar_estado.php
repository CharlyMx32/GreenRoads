<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

header('Content-Type: application/json');
require_once '../../db/conexion.php';
require_once '../../includes/sesion.php';

if (!tieneSesion()) {
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit();
}

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$estado = isset($_GET['estado']) ? trim($_GET['estado']) : '';

$estadosPermitidos = ['pendiente', 'aceptada', 'rechazada', 'cancelada'];

if ($id <= 0 || !in_array($estado, $estadosPermitidos)) {
    echo json_encode([
        'success' => false,
        'message' => 'Parámetros inválidos',
        'received_id' => $id,
        'received_estado' => $estado
    ]);
    exit();
}

// Obtener el estado actual
$query_actual = "SELECT estado FROM cotizaciones WHERE id = ?";
$stmt_actual = mysqli_prepare($conn, $query_actual);
mysqli_stmt_bind_param($stmt_actual, "i", $id);
mysqli_stmt_execute($stmt_actual);
mysqli_stmt_bind_result($stmt_actual, $estado_actual);
mysqli_stmt_fetch($stmt_actual);
mysqli_stmt_close($stmt_actual);

// Verificar si ya fue cambiado de pendiente
if ($estado_actual != 'pendiente') {
    echo json_encode([
        'success' => false,
        'message' => 'El estado no puede cambiarse nuevamente',
        'current_status' => $estado_actual
    ]);
    exit();
}

mysqli_begin_transaction($conn);

try {
    $query = "UPDATE cotizaciones SET estado = ?, id_admin = ? WHERE id = ?";
    $stmt = mysqli_prepare($conn, $query);

    if (!$stmt) {
        throw new Exception("Error al preparar la consulta de actualización: " . mysqli_error($conn));
    }

    $id_admin = $_SESSION['usuario_id'] ?? null;
    mysqli_stmt_bind_param($stmt, "sii", $estado, $id_admin, $id);

    if (!mysqli_stmt_execute($stmt)) {
        throw new Exception("Error al ejecutar la actualización: " . mysqli_stmt_error($stmt));
    }

    mysqli_commit($conn);

    echo json_encode([
        'success' => true,
        'message' => 'Estado actualizado correctamente',
        'new_status' => $estado
    ]);
} catch (Exception $e) {
    mysqli_rollback($conn);

    error_log("Error al cambiar estado de cotización (ID: $id): " . $e->getMessage());

    echo json_encode([
        'success' => false,
        'message' => 'Error al actualizar el estado: ' . $e->getMessage()
    ]);
} finally {
    if (isset($stmt)) {
        mysqli_stmt_close($stmt);
    }
}
