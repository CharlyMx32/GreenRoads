<?php
header('Content-Type: application/json');
require_once '../../db/conexion.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Método no permitido']);
    exit;
}

$input = file_get_contents('php://input');
$data = json_decode($input, true);

if (!$data || !isset($data['id_producto'], $data['id_color'], $data['cantidad'])) {
    echo json_encode(['success' => false, 'message' => 'Datos incompletos']);
    exit;
}

$idProducto = intval($data['id_producto']);
$idColor = intval($data['id_color']);
$cantidad = floatval($data['cantidad']);

try {
    /* Lógica original de inventario
    // Verificar disponibilidad en inventario para el color específico
    $sql = "SELECT ir.costo_unitario, ir.area_m2, ir.id
            FROM inventario_rollos ir
            WHERE ir.id_producto = ? 
            AND ir.id_color = ? 
            AND ir.estado = 'disponible'
            AND ir.area_m2 >= ?
            ORDER BY ir.costo_unitario ASC, ir.area_m2 ASC
            LIMIT 1";
            
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("iid", $idProducto, $idColor, $cantidad);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($row = $result->fetch_assoc()) {
        echo json_encode([
            'success' => true,
            'costo_total_rollo' => floatval($row['costo_unitario']), // Costo total del rollo
            'area_total_rollo' => floatval($row['area_m2']), // Área total del rollo
            'area_disponible' => floatval($row['area_m2']),
            'inventario_id' => intval($row['id']),
            'tiene_inventario' => true
        ]);
    } else {
        // No hay inventario suficiente, buscar si hay algo disponible en menor cantidad
        $sqlParcial = "SELECT SUM(ir.area_m2) as area_total, AVG(ir.costo_unitario) as precio_promedio
                       FROM inventario_rollos ir
                       WHERE ir.id_producto = ? 
                       AND ir.id_color = ? 
                       AND ir.estado = 'disponible'";
                       
        $stmtParcial = $conn->prepare($sqlParcial);
        $stmtParcial->bind_param("ii", $idProducto, $idColor);
        $stmtParcial->execute();
        $resultParcial = $stmtParcial->get_result();
        $parcial = $resultParcial->fetch_assoc();
        
        if ($parcial && $parcial['area_total'] > 0) {
            echo json_encode([
                'success' => true,
                'costo_total_rollo' => floatval($parcial['precio_promedio']),
                'area_total_rollo' => floatval($parcial['area_total']), 
                'area_disponible' => floatval($parcial['area_total']),
                'inventario_id' => null,
                'tiene_inventario' => false,
                'inventario_parcial' => true,
                'message' => "Solo hay {$parcial['area_total']} m² disponibles de {$cantidad} m² solicitados"
            ]);
        } else {
            // No hay inventario, usar costo base del producto
            $sqlBase = "SELECT costo_base FROM productos WHERE id = ?";
            $stmtBase = $conn->prepare($sqlBase);
            $stmtBase->bind_param("i", $idProducto);
            $stmtBase->execute();
            $resultBase = $stmtBase->get_result();
            $base = $resultBase->fetch_assoc();
            
            $costoBasePorM2 = $base ? floatval($base['costo_base']) : 1000.00;
            $costoTotal = $costoBasePorM2 * $cantidad; // Multiplicar por la cantidad solicitada
            
            echo json_encode([
                'success' => true,
                'costo_total_rollo' => $costoTotal, // Costo total basado en cantidad solicitada
                'area_total_rollo' => $cantidad, // Área solicitada
                'area_disponible' => 0,
                'inventario_id' => null,
                'tiene_inventario' => false,
                'inventario_parcial' => false,
                'message' => "Sin inventario disponible. Usando precio base: $" . number_format($costoBasePorM2, 2) . "/m²"
            ]);
        }
    }
    */
    
    // Siempre usar costo base del producto
    $sqlBase = "SELECT costo_base FROM productos WHERE id = ?";
    $stmtBase = $conn->prepare($sqlBase);
    $stmtBase->bind_param("i", $idProducto);
    $stmtBase->execute();
    $resultBase = $stmtBase->get_result();
    $base = $resultBase->fetch_assoc();
    
    $costoBasePorM2 = $base ? floatval($base['costo_base']) : 1000.00;
    $costoTotal = $costoBasePorM2 * $cantidad;
    
    echo json_encode([
        'success' => true,
        'costo_total_rollo' => $costoTotal,
        'area_total_rollo' => $cantidad,
        'area_disponible' => $cantidad,
        'inventario_id' => null,
        'tiene_inventario' => false,
        'inventario_parcial' => false,
        'message' => "Usando precio base del producto: $" . number_format($costoBasePorM2, 2) . "/m²"
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error al consultar inventario: ' . $e->getMessage()
    ]);
}
?>
