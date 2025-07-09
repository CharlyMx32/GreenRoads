<?php
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

// Obtener el estado actual primero (para el historial)
$estado_actual = '';
$query_actual = "SELECT estado FROM cotizaciones WHERE id = ?";
$stmt_actual = mysqli_prepare($conn, $query_actual);
mysqli_stmt_bind_param($stmt_actual, "i", $id);
mysqli_stmt_execute($stmt_actual);
mysqli_stmt_bind_result($stmt_actual, $estado_actual);
mysqli_stmt_fetch($stmt_actual);
mysqli_stmt_close($stmt_actual);

mysqli_begin_transaction($conn);

try {
    $query = "UPDATE cotizaciones SET estado = ? WHERE id = ?";
    $stmt = mysqli_prepare($conn, $query);
    
    if (!$stmt) {
        throw new Exception("Error al preparar la consulta de actualización: " . mysqli_error($conn));
    }
    
    mysqli_stmt_bind_param($stmt, "si", $estado, $id);
    
    if (!mysqli_stmt_execute($stmt)) {
        throw new Exception("Error al ejecutar la actualización: " . mysqli_stmt_error($stmt));
    }
    
    if (!empty($estado_actual)) {
        $queryHistorial = "INSERT INTO historial_cotizaciones 
                            (id_cotizacion, id_admin, estado_anterior, estado_nuevo) 
                            VALUES (?, ?, ?, ?)";
        $stmtHistorial = mysqli_prepare($conn, $queryHistorial);
        
        if ($stmtHistorial) {
            $id_admin = $_SESSION['id_admin'] ?? null;
            $bind_result = mysqli_stmt_bind_param($stmtHistorial, "iiss", $id, $id_admin, $estado_actual, $estado);
            
            if (!$bind_result) {
                throw new Exception("Error al bindear parámetros del historial: " . mysqli_stmt_error($stmtHistorial));
            }
            
            $execute_result = mysqli_stmt_execute($stmtHistorial);
            
            if (!$execute_result) {
                throw new Exception("Error al ejecutar inserción en historial: " . mysqli_stmt_error($stmtHistorial));
            }
            
            mysqli_stmt_close($stmtHistorial);
        }
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
        'message' => 'Error al actualizar el estado',
        'error' => $e->getMessage(),
        'debug_info' => [
            'cotizacion_id' => $id,
            'nuevo_estado' => $estado,
            'estado_actual' => $estado_actual
        ]
    ]);
} finally {
    if (isset($stmt)) {
        mysqli_stmt_close($stmt);
    }
}