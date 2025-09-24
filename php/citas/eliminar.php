<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

$ROOT = '../../';
include_once $ROOT . '/db/conexion.php';
include_once $ROOT . '/includes/sesion.php';

if (!tieneSesion()) {
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'message' => 'No autorizado'
    ]);
    exit();
}

try {
    // Obtener ID de la cita a eliminar
    $id_cita = null;
    
    if ($_SERVER['REQUEST_METHOD'] === 'DELETE' || $_SERVER['REQUEST_METHOD'] === 'POST') {
        if ($_SERVER['REQUEST_METHOD'] === 'DELETE') {
            $input = json_decode(file_get_contents('php://input'), true);
            $id_cita = isset($input['id']) ? (int)$input['id'] : null;
        } else {
            $id_cita = isset($_POST['id']) ? (int)$_POST['id'] : null;
        }
        
        // También permitir ID por GET para compatibilidad
        if (!$id_cita && isset($_GET['id'])) {
            $id_cita = (int)$_GET['id'];
        }
    }
    
    if (!$id_cita) {
        echo json_encode([
            'success' => false,
            'message' => 'ID de cita requerido'
        ]);
        exit();
    }
    
    // Verificar que la cita existe y obtener información
    $sql_verificar = "SELECT id, id_admin, estado, fecha_cita, hora_cita FROM citas WHERE id = ?";
    $stmt_verificar = mysqli_prepare($conn, $sql_verificar);
    mysqli_stmt_bind_param($stmt_verificar, 'i', $id_cita);
    mysqli_stmt_execute($stmt_verificar);
    $result_verificar = mysqli_stmt_get_result($stmt_verificar);
    $cita = mysqli_fetch_assoc($result_verificar);
    
    if (!$cita) {
        echo json_encode([
            'success' => false,
            'message' => 'Cita no encontrada'
        ]);
        exit();
    }
    
    // Verificar permisos: solo el admin que creó la cita o un admin general puede eliminarla
    if ($cita['id_admin'] != $_SESSION['usuario'] && !esAdmin()) {
        echo json_encode([
            'success' => false,
            'message' => 'No tienes permisos para eliminar esta cita'
        ]);
        exit();
    }
    
    // No permitir eliminar citas ya completadas
    if ($cita['estado'] === 'completada') {
        echo json_encode([
            'success' => false,
            'message' => 'No se pueden eliminar citas completadas'
        ]);
        exit();
    }
    
    // No permitir eliminar citas que ya pasaron (opcional)
    $fecha_hora_cita = $cita['fecha_cita'] . ' ' . $cita['hora_cita'];
    $timestamp_cita = strtotime($fecha_hora_cita);
    $timestamp_actual = time();
    
    if ($timestamp_cita < $timestamp_actual && $cita['estado'] === 'pendiente') {
        echo json_encode([
            'success' => false,
            'message' => 'No se pueden eliminar citas que ya pasaron. Considera marcarla como cancelada.'
        ]);
        exit();
    }
    
    // Realizar "soft delete" cambiando el estado a 'eliminado'
    $sql_eliminar = "UPDATE citas SET 
                     estado = 'eliminado',
                     fecha_actualizacion = CURRENT_TIMESTAMP
                     WHERE id = ?";
    
    $stmt_eliminar = mysqli_prepare($conn, $sql_eliminar);
    mysqli_stmt_bind_param($stmt_eliminar, 'i', $id_cita);
    
    if (mysqli_stmt_execute($stmt_eliminar)) {
        echo json_encode([
            'success' => true,
            'message' => 'Cita eliminada exitosamente'
        ]);
    } else {
        throw new Exception('Error al eliminar la cita: ' . mysqli_error($conn));
    }
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Error del servidor: ' . $e->getMessage()
    ]);
}

// Cerrar statements
if (isset($stmt_verificar)) mysqli_stmt_close($stmt_verificar);
if (isset($stmt_eliminar)) mysqli_stmt_close($stmt_eliminar);

mysqli_close($conn);
?>