<?php
header('Content-Type: application/json');
require_once '../../db/conexion.php';

$id_producto = isset($_GET['id_producto']) ? intval($_GET['id_producto']) : 0;

if ($id_producto <= 0) {
    echo json_encode([]);
    exit;
}

try {
    $sql = "SELECT c.id, c.nombre, c.codigo_hex 
            FROM colores c
            JOIN producto_colores pc ON c.id = pc.id_color
            WHERE pc.id_producto = ?
            ORDER BY c.nombre ASC";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param('i', $id_producto);
    $stmt->execute();
    $result = $stmt->get_result();

    $colores = [];
    while ($row = $result->fetch_assoc()) {
        $colores[] = [
            'id' => (int)$row['id'],
            'nombre' => $row['nombre'],
            'codigo_hex' => $row['codigo_hex']
        ];
    }

    echo json_encode($colores);

} catch (Exception $e) {
    echo json_encode([]);
}
?>