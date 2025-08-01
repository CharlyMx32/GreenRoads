<?php
header('Content-Type: application/json');
require_once '../../db/conexion.php';

$sql = "SELECT 
    p.id, 
    p.nombre, 
    m.nombre AS modelo,
    GROUP_CONCAT(DISTINCT c.id ORDER BY c.nombre SEPARATOR '|||') AS ids_colores,
    GROUP_CONCAT(DISTINCT c.nombre ORDER BY c.nombre SEPARATOR '|||') AS nombres_colores,
    GROUP_CONCAT(DISTINCT c.codigo_hex ORDER BY c.nombre SEPARATOR '|||') AS hex_colores,
    SUM(ir.area_m2) AS area_disponible_total,
    MIN(ir.costo_unitario) AS precio_unitario_rollo_completo
FROM productos p
JOIN modelos m ON p.id_modelo = m.id
JOIN inventario_rollos ir ON p.id = ir.id_producto
JOIN colores c ON ir.id_color = c.id
WHERE p.tipo_inventario = 'rollo' 
AND p.estado = 'activo'
AND ir.estado = 'disponible'
GROUP BY p.id, p.nombre, m.nombre";

$result = mysqli_query($conn, $sql);
$rollos = [];

while ($row = mysqli_fetch_assoc($result)) {
    $colores = [];
    $ids = explode('|||', $row['ids_colores'] ?? '');
    $nombres = explode('|||', $row['nombres_colores'] ?? '');
    $hex = explode('|||', $row['hex_colores'] ?? '');
    
    for ($i = 0; $i < count($ids); $i++) {
        if (!empty($ids[$i])) {
            $colores[] = [
                'id' => $ids[$i],
                'nombre' => $nombres[$i] ?? '',
                'codigo_hex' => $hex[$i] ?? '#7DC042'
            ];
        }
    }
    
    $rollos[] = [
        'id' => $row['id'],
        'nombre' => $row['nombre'],
        'modelo' => $row['modelo'],
        'colores' => $colores,
        'area_disponible_total' => (float)$row['area_disponible_total'],
        'precio_unitario_rollo_completo' => (float)$row['precio_unitario_rollo_completo']
    ];
}

echo json_encode($rollos);
?>