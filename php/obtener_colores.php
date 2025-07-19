<?php
require_once '../../db/conexion.php';

$idProducto = $_GET['id_producto'] ?? 0;

$sql = "SELECT DISTINCT 
            c.id, 
            c.nombre,
            SUM(ir.area_m2) AS area_disponible
        FROM inventario_rollos ir
        JOIN colores c ON ir.id_color = c.id
        WHERE ir.id_producto = ?
        AND ir.estado = 'disponible'
        GROUP BY c.id
        HAVING area_disponible > 0";

$stmt = $conn->prepare($sql);
$stmt->bind_param('i', $idProducto);
$stmt->execute();
$result = $stmt->get_result();

$colores = [];
while ($row = $result->fetch_assoc()) {
    $colores[] = $row;
}

echo json_encode($colores);