<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

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
    // Consulta optimizada para obtener clientes
    $sql = "SELECT 
                id, 
                nombre, 
                telefono, 
                email,
                direccion,
                fecha_creacion
            FROM clientes 
            WHERE estado = 'activo' 
            ORDER BY 
                CASE 
                    WHEN fecha_creacion >= DATE_SUB(NOW(), INTERVAL 30 DAY) THEN 0 
                    ELSE 1 
                END,
                nombre ASC
            LIMIT 200";
    
    $result = mysqli_query($conn, $sql);
    
    if (!$result) {
        throw new Exception('Error en la consulta: ' . mysqli_error($conn));
    }
    
    $clientes = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $clientes[] = [
            'id' => (int)$row['id'],
            'nombre' => $row['nombre'],
            'telefono' => $row['telefono'],
            'email' => $row['email'],
            'direccion' => $row['direccion'],
            'es_reciente' => (strtotime($row['fecha_creacion']) > strtotime('-30 days'))
        ];
    }
    
    echo json_encode([
        'success' => true,
        'clientes' => $clientes,
        'total' => count($clientes)
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Error del servidor: ' . $e->getMessage()
    ]);
}

mysqli_close($conn);
?>
