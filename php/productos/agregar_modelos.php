<?php
/**
 * Controlador para agregar nuevos modelos de productos
 */

// Configuración inicial
require_once __DIR__ . '/../../db/conexion.php';
require_once __DIR__ . '/../../includes/config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['status' => 0, 'mensaje' => 'Método no permitido']);
    exit();
}

$json = file_get_contents('php://input');
$data = json_decode($json, true);

if (json_last_error() !== JSON_ERROR_NONE || !$data) {
    http_response_code(400);
    echo json_encode(['status' => 0, 'mensaje' => 'Datos JSON inválidos']);
    exit();
}

if (empty($data['nombre'])) {
    http_response_code(400);
    echo json_encode(['status' => 0, 'mensaje' => 'El nombre del modelo es requerido']);
    exit();
}

$nombre = trim($data['nombre']);
$altura_mm = isset($data['altura_mm']) ? floatval($data['altura_mm']) : null;

if (strlen($nombre) > 100) {
    http_response_code(400);
    echo json_encode(['status' => 0, 'mensaje' => 'El nombre no puede exceder los 100 caracteres']);
    exit();
}

if ($altura_mm !== null && ($altura_mm < 0 || $altura_mm > 999.99)) {
    http_response_code(400);
    echo json_encode(['status' => 0, 'mensaje' => 'La altura debe estar entre 0 y 999.99 mm']);
    exit();
}

try {
    $query = "INSERT INTO modelos (nombre, altura_mm) VALUES (?, ?)";
    $stmt = mysqli_prepare($conn, $query);
    
    if (!$stmt) {
        throw new Exception("Error al preparar la consulta: " . mysqli_error($conn));
    }
    
    mysqli_stmt_bind_param($stmt, "sd", $nombre, $altura_mm);
    
    if (!mysqli_stmt_execute($stmt)) {
        throw new Exception("Error al ejecutar la consulta: " . mysqli_stmt_error($stmt));
    }
    
    $nuevo_id = mysqli_insert_id($conn);
    
    mysqli_stmt_close($stmt);
    
    echo json_encode([
        'status' => 1,
        'mensaje' => 'Modelo agregado correctamente',
        'id' => $nuevo_id,
        'nombre' => $nombre,
        'altura_mm' => $altura_mm
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'status' => 0,
        'mensaje' => 'Error en el servidor: ' . $e->getMessage()
    ]);
    
    error_log('Error al agregar modelo: ' . $e->getMessage());
}