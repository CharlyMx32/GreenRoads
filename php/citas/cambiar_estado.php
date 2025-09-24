<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
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

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'message' => 'Método no permitido'
    ]);
    exit();
}

try {
    // Obtener parámetros
    $id_cita = isset($_POST['id']) ? (int)$_POST['id'] : (isset($_GET['id']) ? (int)$_GET['id'] : 0);
    $nuevo_estado = isset($_POST['estado']) ? trim($_POST['estado']) : (isset($_GET['estado']) ? trim($_GET['estado']) : '');
    
    if (!$id_cita) {
        echo json_encode([
            'success' => false,
            'message' => 'ID de cita requerido'
        ]);
        exit();
    }
    
    // Validar estados permitidos
    $estados_permitidos = ['pendiente', 'completada', 'cancelada'];
    if (!in_array($nuevo_estado, $estados_permitidos)) {
        echo json_encode([
            'success' => false,
            'message' => 'Estado no válido'
        ]);
        exit();
    }
    
    // Verificar que la cita existe y obtener información
    $sql_verificar = "SELECT id, id_admin, estado, fecha_cita, hora_cita FROM citas WHERE id = ? AND estado != 'eliminado'";
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
    
    // Verificar permisos: solo el admin que creó la cita o un admin general pueden cambiar el estado
    if ($cita['id_admin'] != $_SESSION['usuario'] && !esAdmin()) {
        echo json_encode([
            'success' => false,
            'message' => 'No tienes permisos para modificar esta cita'
        ]);
        exit();
    }
    
    // Verificar si el cambio de estado es válido
    $estado_actual = $cita['estado'];
    
    // Reglas de negocio para cambios de estado
    if ($estado_actual === 'completada') {
        echo json_encode([
            'success' => false,
            'message' => 'No se puede cambiar el estado de una cita completada'
        ]);
        exit();
    }
    
    if ($estado_actual === $nuevo_estado) {
        echo json_encode([
            'success' => false,
            'message' => 'La cita ya tiene este estado'
        ]);
        exit();
    }
    
    // Verificar fecha/hora para completar cita
    if ($nuevo_estado === 'completada') {
        $fecha_hora_cita = $cita['fecha_cita'] . ' ' . $cita['hora_cita'];
        $timestamp_cita = strtotime($fecha_hora_cita);
        $timestamp_actual = time();
        
        // Solo permitir completar citas que ya pasaron o están en curso
        if ($timestamp_cita > ($timestamp_actual + 3600)) { // 1 hora de margen
            echo json_encode([
                'success' => false,
                'message' => 'Solo se pueden completar citas que ya ocurrieron o están en curso'
            ]);
            exit();
        }
    }
    
    // Actualizar estado de la cita
    $sql_update = "UPDATE citas SET 
                   estado = ?,
                   fecha_actualizacion = CURRENT_TIMESTAMP
                   WHERE id = ?";
    
    $stmt_update = mysqli_prepare($conn, $sql_update);
    mysqli_stmt_bind_param($stmt_update, 'si', $nuevo_estado, $id_cita);
    
    if (mysqli_stmt_execute($stmt_update)) {
        $mensajes_estado = [
            'pendiente' => 'Cita marcada como pendiente',
            'completada' => 'Cita marcada como completada',
            'cancelada' => 'Cita cancelada exitosamente'
        ];
        
        echo json_encode([
            'success' => true,
            'message' => $mensajes_estado[$nuevo_estado] ?? 'Estado actualizado',
            'nuevo_estado' => $nuevo_estado
        ]);
    } else {
        throw new Exception('Error al actualizar el estado: ' . mysqli_error($conn));
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
if (isset($stmt_update)) mysqli_stmt_close($stmt_update);

mysqli_close($conn);
?>