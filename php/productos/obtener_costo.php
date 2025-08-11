<?php
header('Content-Type: application/json');
require_once '../../db/conexion.php';

try {
    $idProducto = $_GET['id'] ?? null;
    
    if (!$idProducto) {
        throw new Exception("ID de producto no proporcionado");
    }

    // Obtener costo base del producto
    $query = "SELECT costo_base FROM productos WHERE id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $idProducto);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        throw new Exception("Producto no encontrado");
    }
    
    $producto = $result->fetch_assoc();
    
    echo json_encode([
        'success' => true,
        'costo' => (float)$producto['costo_base']
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}