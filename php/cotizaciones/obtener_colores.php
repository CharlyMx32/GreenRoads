<?php
header('Content-Type: application/json');
require_once '../../db/conexion.php';

$id_producto = isset($_GET['id_producto']) ? intval($_GET['id_producto']) : 0;

$query = "SELECT DISTINCT c.id, c.nombre 
          FROM colores c
          JOIN inventario_rollos ir ON c.id = ir.id_color
          WHERE ir.id_producto = ? 
            AND ir.estado = 'disponible'
            AND ir.area_m2 > 0
          ORDER BY c.nombre";

$stmt = $conn->prepare($query);
$stmt->bind_param('i', $id_producto);
$stmt->execute();
$result = $stmt->get_result();

$colores = [];
while ($row = $result->fetch_assoc()) {
    $colores[] = $row;
}

echo json_encode($colores ?: []);
?>