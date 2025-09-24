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

if (!puedeEditarCitas()) {
    http_response_code(403);
    echo json_encode([
        'success' => false,
        'message' => 'No tiene permisos para editar citas'
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
    // Recibir datos del formulario
    $input = json_decode(file_get_contents('php://input'), true);
    
    // Si no es JSON, usar $_POST
    if (!$input) {
        $input = $_POST;
    }
    
    // Validar campos requeridos
    $id_cita = (int)$input['id'];
    if (!$id_cita) {
        echo json_encode([
            'success' => false,
            'message' => 'ID de cita requerido'
        ]);
        exit();
    }
    
    $campos_requeridos = ['id_cliente', 'fecha_cita', 'hora_cita', 'tipo_cita', 'direccion_cita'];
    foreach ($campos_requeridos as $campo) {
        if (empty($input[$campo])) {
            echo json_encode([
                'success' => false,
                'message' => "El campo {$campo} es requerido"
            ]);
            exit();
        }
    }
    
    $id_cliente = (int)$input['id_cliente'];
    $fecha_cita = $input['fecha_cita'];
    $hora_cita = $input['hora_cita'];
    $tipo_cita = $input['tipo_cita'];
    $direccion_cita = trim($input['direccion_cita']);
    $descripcion = isset($input['descripcion']) ? trim($input['descripcion']) : null;
    $estado = isset($input['estado']) ? $input['estado'] : 'pendiente';
    
    // Verificar que la cita existe y pertenece al usuario actual o es admin
    $sql_verificar = "SELECT id, id_admin, estado FROM citas WHERE id = ?";
    $stmt_verificar = mysqli_prepare($conn, $sql_verificar);
    mysqli_stmt_bind_param($stmt_verificar, 'i', $id_cita);
    mysqli_stmt_execute($stmt_verificar);
    $result_verificar = mysqli_stmt_get_result($stmt_verificar);
    $cita_actual = mysqli_fetch_assoc($result_verificar);
    
    if (!$cita_actual) {
        echo json_encode([
            'success' => false,
            'message' => 'Cita no encontrada'
        ]);
        exit();
    }
    
    // Solo permitir edición si es el admin que creó la cita o si es admin general
    if ($cita_actual['id_admin'] != $_SESSION['usuario'] && !esAdmin()) {
        echo json_encode([
            'success' => false,
            'message' => 'No tienes permisos para editar esta cita'
        ]);
        exit();
    }
    
    // No permitir editar citas completadas
    if ($cita_actual['estado'] === 'completada') {
        echo json_encode([
            'success' => false,
            'message' => 'No se pueden editar citas completadas'
        ]);
        exit();
    }
    
    // Validar que el cliente existe
    $sql_cliente = "SELECT id FROM clientes WHERE id = ? AND estado = 'activo'";
    $stmt_cliente = mysqli_prepare($conn, $sql_cliente);
    mysqli_stmt_bind_param($stmt_cliente, 'i', $id_cliente);
    mysqli_stmt_execute($stmt_cliente);
    $result_cliente = mysqli_stmt_get_result($stmt_cliente);
    
    if (mysqli_num_rows($result_cliente) === 0) {
        echo json_encode([
            'success' => false,
            'message' => 'Cliente no válido'
        ]);
        exit();
    }
    
    // Validar tipos de cita permitidos
    $tipos_permitidos = ['medicion', 'consulta', 'instalacion', 'mantenimiento', 'seguimiento', 'otro'];
    if (!in_array($tipo_cita, $tipos_permitidos)) {
        echo json_encode([
            'success' => false,
            'message' => 'Tipo de cita no válido'
        ]);
        exit();
    }
    
    // Validar estados permitidos
    $estados_permitidos = ['pendiente', 'completada', 'cancelada'];
    if (!in_array($estado, $estados_permitidos)) {
        echo json_encode([
            'success' => false,
            'message' => 'Estado no válido'
        ]);
        exit();
    }
    
    // Validar formato de fecha y hora
    if (!DateTime::createFromFormat('Y-m-d', $fecha_cita)) {
        echo json_encode([
            'success' => false,
            'message' => 'Formato de fecha no válido'
        ]);
        exit();
    }
    
    if (!DateTime::createFromFormat('H:i', $hora_cita)) {
        echo json_encode([
            'success' => false,
            'message' => 'Formato de hora no válido'
        ]);
        exit();
    }
    
    // Verificar conflicto de horario (excluyendo la cita actual)
    $sql_conflicto = "SELECT id FROM citas 
                      WHERE id_admin = ? 
                      AND fecha_cita = ? 
                      AND hora_cita = ? 
                      AND id != ?
                      AND estado NOT IN ('cancelada', 'eliminado')";
    $stmt_conflicto = mysqli_prepare($conn, $sql_conflicto);
    mysqli_stmt_bind_param($stmt_conflicto, 'issi', $cita_actual['id_admin'], $fecha_cita, $hora_cita, $id_cita);
    mysqli_stmt_execute($stmt_conflicto);
    $result_conflicto = mysqli_stmt_get_result($stmt_conflicto);
    
    if (mysqli_num_rows($result_conflicto) > 0) {
        echo json_encode([
            'success' => false,
            'message' => 'Ya tienes otra cita agendada en esta fecha y hora'
        ]);
        exit();
    }
    
    // Actualizar cita
    $sql_update = "UPDATE citas SET 
                    id_cliente = ?, 
                    fecha_cita = ?, 
                    hora_cita = ?, 
                    tipo_cita = ?, 
                    direccion_cita = ?, 
                    descripcion = ?,
                    estado = ?,
                    fecha_actualizacion = CURRENT_TIMESTAMP
                   WHERE id = ?";
    
    $stmt_update = mysqli_prepare($conn, $sql_update);
    mysqli_stmt_bind_param(
        $stmt_update, 
        'issssssi', 
        $id_cliente, 
        $fecha_cita, 
        $hora_cita, 
        $tipo_cita, 
        $direccion_cita, 
        $descripcion,
        $estado,
        $id_cita
    );
    
    if (mysqli_stmt_execute($stmt_update)) {
        echo json_encode([
            'success' => true,
            'message' => 'Cita actualizada exitosamente'
        ]);
    } else {
        throw new Exception('Error al actualizar la cita: ' . mysqli_error($conn));
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
if (isset($stmt_cliente)) mysqli_stmt_close($stmt_cliente);
if (isset($stmt_conflicto)) mysqli_stmt_close($stmt_conflicto);
if (isset($stmt_update)) mysqli_stmt_close($stmt_update);

mysqli_close($conn);
?>