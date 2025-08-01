<?php
header('Content-Type: application/json');
require_once '../../db/conexion.php';

function calcularCostoRollos($idProducto, $areaNecesaria) {
    global $conn;
    $query = "SELECT costo_unitario, area_m2
              FROM inventario_rollos 
              WHERE id_producto = ? AND estado = 'disponible'";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $idProducto);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if (!$result || $result->num_rows === 0) {
        throw new Exception("No hay rollos disponibles para este producto");
    }
    
    $row = $result->fetch_assoc();
    $costoPorM2 = $row['costo_unitario'];
    $areaM2 = $row['area_m2'];
    
    // 2. Calcular costo total para el área solicitada
    $costoTotal = ($areaNecesaria / $areaM2) * $costoPorM2;
    
    return [
        'costo_total' => $costoTotal,
        'rollos_usados' => [
            [
                'id_rollo' => 0, 
                'area_usada' => $areaNecesaria,
                'costo_unitario' => $costoPorM2
            ]
        ]
    ];
}

try {
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input || !isset($input['rollos'])) {
        throw new Exception("Datos inválidos");
    }
    
    $costoTotal = 0;
    $todosRollosUsados = [];
    
    foreach ($input['rollos'] as $rollo) {
        if (empty($rollo['id_producto'])) continue;

        $datos = calcularCostoRollos($rollo['id_producto'], $rollo['area']);
        $costoTotal += $datos['costo_total'];
        $todosRollosUsados = array_merge($todosRollosUsados, $datos['rollos_usados']);
    }
    
    echo json_encode([
        'success' => true,
        'costoTotal' => $costoTotal,
        'rollosUsados' => $todosRollosUsados
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>