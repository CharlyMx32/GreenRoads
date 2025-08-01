<?php
header('Content-Type: application/json');
require_once '../../db/conexion.php';

try {
    $parametros = [];
    $sql = "SELECT clave, valor FROM parametros_sistema 
            WHERE clave IN ('garantia_default', 'iva_porcentaje')";
    
    $result = mysqli_query($conn, $sql);
    
    if (!$result) {
        throw new Exception("Error en consulta: " . mysqli_error($conn));
    }
    
    while ($row = mysqli_fetch_assoc($result)) {
        $parametros[$row['clave']] = $row['valor'];
    }
    
    $required = ['garantia_default', 'iva_porcentaje'];
    foreach ($required as $key) {
        if (!isset($parametros[$key])) {
            throw new Exception("Falta el parámetro requerido: $key");
        }
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