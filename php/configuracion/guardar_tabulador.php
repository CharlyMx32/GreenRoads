<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);


require_once __DIR__ . '/../../db/conexion.php';
require_once __DIR__ . '/../../includes/sesion.php';

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Método no permitido', 405);
    }

    $input = json_decode(file_get_contents('php://input'), true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception('Datos JSON inválidos', 400);
    }

    $id = isset($input['id']) ? (int)$input['id'] : 0;
    $rangoMin = (float)($input['rango_min'] ?? 0);
    $rangoMax = (float)($input['rango_max'] ?? 0);
    $precioM2 = (float)($input['precio_m2'] ?? 0);
    $descripcion = trim($input['descripcion'] ?? '');
    $activo = isset($input['activo']) ? (int)$input['activo'] : 0;
    $tipo = trim($input['tipo'] ?? 'precio_instalacion');
    
    $tiposPermitidos = ['precio_instalacion', 'clavos', 'pegamento', 'ganancias', 'polvillo'];
    
    if (!in_array($tipo, $tiposPermitidos)) {
        throw new Exception('Tipo de tabulador no válido', 400);
    }
    
    if ($rangoMin <= 0 || $rangoMax <= 0 || $precioM2 <= 0) {
        throw new Exception('Todos los valores deben ser mayores que cero', 400);
    }
    
    if ($rangoMin >= $rangoMax) {
        throw new Exception('El rango mínimo debe ser menor que el máximo', 400);
    }

    // Verificación de superposición
    $sqlCheck = "SELECT id FROM tabuladores 
                WHERE tipo = ? AND activo = 1 AND id != ? 
                AND ((? BETWEEN rango_min AND rango_max) 
                OR (? BETWEEN rango_min AND rango_max)
                OR (rango_min BETWEEN ? AND ?)
                OR (rango_max BETWEEN ? AND ?))";
    
    $stmt = $conn->prepare($sqlCheck);
    $stmt->bind_param('sidddddd', $tipo, $id, $rangoMin, $rangoMax, $rangoMin, $rangoMax, $rangoMin, $rangoMax);
    $stmt->execute();
    
    if ($stmt->get_result()->num_rows > 0) {
        throw new Exception('Los rangos no pueden superponerse con otros tabuladores activos del mismo tipo', 400);
    }

    if ($id > 0) {
        $sql = "UPDATE tabuladores SET 
                rango_min = ?, rango_max = ?, precio_m2 = ?, 
                descripcion = ?, activo = ?, tipo = ?, fecha_actualizacion = NOW() 
                WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param('dddsisi', $rangoMin, $rangoMax, $precioM2, $descripcion, $activo, $tipo, $id);
    } else {
        $sql = "INSERT INTO tabuladores 
                (rango_min, rango_max, precio_m2, descripcion, activo, tipo, fecha_creacion, fecha_actualizacion) 
                VALUES (?, ?, ?, ?, ?, ?, NOW(), NOW())";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param('dddsis', $rangoMin, $rangoMax, $precioM2, $descripcion, $activo, $tipo);
    }

    if (!$stmt->execute()) {
        throw new Exception('Error al guardar el tabulador: ' . $stmt->error, 500);
    }

    echo json_encode([
        'status' => 1,
        'mensaje' => 'Tabulador guardado correctamente',
        'id' => $id > 0 ? $id : $stmt->insert_id
    ]);

} catch (Exception $e) {
    http_response_code($e->getCode() >= 400 && $e->getCode() < 600 ? $e->getCode() : 500);
    echo json_encode([
        'status' => 0,
        'mensaje' => $e->getMessage(),
        'error' => $e->getCode()
    ]);
}