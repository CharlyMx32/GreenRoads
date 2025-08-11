<?php
ob_start();

ini_set('display_errors', 0);
error_reporting(0);

try {
    require_once '../../db/conexion.php';
    
    ob_clean();
    
    header('Content-Type: application/json; charset=utf-8');
    
    $id = intval($_GET['id'] ?? 0);
    
    if ($id <= 0) {
        echo json_encode(['status' => 0, 'mensaje' => 'ID inválido']);
        exit;
    }

    
    // Eliminar tabulador
    $stmt = $conn->prepare("DELETE FROM tabuladores WHERE id = ?");
    
    if (!$stmt) {
        throw new Exception('Error en la consulta de eliminación: ' . $conn->error);
    }
    
    $stmt->bind_param('i', $id);
    
    if ($stmt->execute()) {
        if ($stmt->affected_rows > 0) {
            echo json_encode([
                'status' => 1,
                'mensaje' => 'Tabulador eliminado correctamente'
            ]);
        } else {
            echo json_encode([
                'status' => 0,
                'mensaje' => 'No se encontró el tabulador a eliminar'
            ]);
        }
    } else {
        throw new Exception('Error al ejecutar la eliminación: ' . $stmt->error);
    }
    
} catch (Exception $e) {
    echo json_encode([
        'status' => 0,
        'mensaje' => 'Error del servidor: ' . $e->getMessage()
    ]);
} finally {
    ob_end_flush();
}