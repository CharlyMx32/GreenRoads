<?php
ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
error_reporting(0);

$ROOT = '../..';
include_once $ROOT . '/db/conexion.php';

header('Content-Type: application/json');

// Validar  DB
if (!$conn) {
    http_response_code(500);
    die(json_encode([
        'status' => 0,
        'mensaje' => 'Error de conexión a la base de datos'
    ]));
}

try {
    $parametros = [];
    $sql = "SELECT clave, valor FROM parametros_sistema 
            WHERE clave IN ('precio_instalacion_m2', 'garantia_default_anios', 'iva_porcentaje')";
    $result = mysqli_query($conn, $sql);
    
    while ($row = mysqli_fetch_assoc($result)) {
        $parametros[$row['clave']] = $row['valor'];
    }
    
    echo json_encode([
        'status' => 1,
        'parametros' => $parametros
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'status' => 0,
        'mensaje' => $e->getMessage()
    ]);
}