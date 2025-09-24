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

if (!puedeCrearCitas()) {
    http_response_code(403);
    echo json_encode([
        'success' => false,
        'message' => 'No tiene permisos para crear citas'
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
    $id_admin = $_SESSION['usuario']; // Usuario actual
    $fecha_cita = $input['fecha_cita'];
    $hora_cita = $input['hora_cita'];
    $tipo_cita = $input['tipo_cita'];
    $direccion_cita = trim($input['direccion_cita']);
    $descripcion = isset($input['descripcion']) ? trim($input['descripcion']) : null;
    
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
    
    // Verificar que no haya conflicto de horario para el mismo admin
    $sql_conflicto = "SELECT id FROM citas 
                      WHERE id_admin = ? 
                      AND fecha_cita = ? 
                      AND hora_cita = ? 
                      AND estado NOT IN ('cancelada', 'eliminado')";
    $stmt_conflicto = mysqli_prepare($conn, $sql_conflicto);
    mysqli_stmt_bind_param($stmt_conflicto, 'iss', $id_admin, $fecha_cita, $hora_cita);
    mysqli_stmt_execute($stmt_conflicto);
    $result_conflicto = mysqli_stmt_get_result($stmt_conflicto);
    
    if (mysqli_num_rows($result_conflicto) > 0) {
        echo json_encode([
            'success' => false,
            'message' => 'Ya tienes una cita agendada en esta fecha y hora'
        ]);
        exit();
    }
    
    // Insertar nueva cita
    $sql_insert = "INSERT INTO citas (
                    id_cliente, 
                    id_admin, 
                    fecha_cita, 
                    hora_cita, 
                    tipo_cita, 
                    direccion_cita, 
                    descripcion,
                    estado
                ) VALUES (?, ?, ?, ?, ?, ?, ?, 'pendiente')";
    
    $stmt_insert = mysqli_prepare($conn, $sql_insert);
    mysqli_stmt_bind_param(
        $stmt_insert, 
        'iisssss', 
        $id_cliente, 
        $id_admin, 
        $fecha_cita, 
        $hora_cita, 
        $tipo_cita, 
        $direccion_cita, 
        $descripcion
    );
    
    if (mysqli_stmt_execute($stmt_insert)) {
        $id_cita = mysqli_insert_id($conn);
        
        echo json_encode([
            'success' => true,
            'message' => 'Cita creada exitosamente',
            'id_cita' => $id_cita
        ]);
    } else {
        throw new Exception('Error al insertar la cita: ' . mysqli_error($conn));
    }
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Error del servidor: ' . $e->getMessage()
    ]);
}

// Cerrar statements
if (isset($stmt_cliente)) mysqli_stmt_close($stmt_cliente);
if (isset($stmt_conflicto)) mysqli_stmt_close($stmt_conflicto);
if (isset($stmt_insert)) mysqli_stmt_close($stmt_insert);

mysqli_close($conn);
?>