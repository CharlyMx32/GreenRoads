<?php
$ROOT = '../..';
include_once $ROOT . '/db/conexion.php';

header('Content-Type: application/json');

try {
    $sql = "SELECT clave, valor, descripcion, tipo, editable FROM parametros_sistema ORDER BY clave";
    $result = mysqli_query($conn, $sql);
    
    $parametros = [];
    if ($result) {
        while ($row = mysqli_fetch_assoc($result)) {
            $parametros[] = $row;
        }
    }
    
    echo json_encode([
        'status' => 1,
        'parametros' => $parametros,
        'total' => count($parametros)
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'status' => 0,
        'mensaje' => $e->getMessage()
    ]);
}
?>
