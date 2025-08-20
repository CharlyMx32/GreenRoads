<?php
include_once '../../db/conexion.php';
include_once '../../includes/sesion.php';
header('Content-Type: application/json');

if (!tieneSesion()) {
    echo json_encode(["status" => 0, "mensaje" => "Sesión inválida"]);
    exit;
}

$id_cotizacion = $_POST['id'] ?? null;

if (!$id_cotizacion || !is_numeric($id_cotizacion)) {
    echo json_encode(["status" => 0, "mensaje" => "ID de cotización inválido"]);
    exit;
}

$sql = "UPDATE cotizaciones SET estado = 'aceptada' WHERE id = ? AND estado = 'pendiente'";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $id_cotizacion);
$stmt->execute();

if ($stmt->affected_rows > 0) {
    // Crear instalación automáticamente cuando se acepta la cotización
    // Verificar si ya existe una instalación para esta cotización
    $sql_check_instalacion = "SELECT id FROM instalaciones WHERE id_cotizacion = ?";
    $stmt_check = $conn->prepare($sql_check_instalacion);
    $stmt_check->bind_param("i", $id_cotizacion);
    $stmt_check->execute();
    $result_check = $stmt_check->get_result();
    
    if ($result_check->num_rows == 0) {
        // Solo crear si no existe ya una instalación
        $sql_instalacion = "INSERT INTO instalaciones (
            id_cotizacion, 
            estado, 
            progreso_porcentaje, 
            fecha_creacion
        ) VALUES (?, 'planificada', 0, NOW())";
        
        $stmt_instalacion = $conn->prepare($sql_instalacion);
        $stmt_instalacion->bind_param("i", $id_cotizacion);
        $stmt_instalacion->execute();
    }

    // Obtener productos de la cotización para descontar del inventario
    $sql_productos = "SELECT dc.id_producto, dc.cantidad, dc.id_color, dc.area_usada,
                             p.tipo_inventario, p.nombre
                      FROM detalle_cotizacion dc
                      JOIN productos p ON dc.id_producto = p.id
                      WHERE dc.id_cotizacion = ?";
    $stmt_productos = $conn->prepare($sql_productos);
    $stmt_productos->bind_param("i", $id_cotizacion);
    $stmt_productos->execute();
    $result_productos = $stmt_productos->get_result();

    while ($producto = $result_productos->fetch_assoc()) {
        if ($producto['tipo_inventario'] == 'rollo') {
            // Descontar rollos del inventario
            if ($producto['id_color']) {
                $sql_inventario = "UPDATE inventario_rollos 
                                   SET estado = 'reservado'
                                   WHERE id_producto = ? AND id_color = ? AND estado = 'disponible'
                                   AND area_m2 >= ?
                                   ORDER BY costo_unitario ASC, area_m2 ASC
                                   LIMIT 1";
                $stmt_inventario = $conn->prepare($sql_inventario);
                $stmt_inventario->bind_param("iid", $producto['id_producto'], $producto['id_color'], $producto['area_usada']);
                $stmt_inventario->execute();
            }
        } else {
            // Descontar productos de unidad del inventario
            $sql_stock = "INSERT INTO movimientos_inventario 
                          (id_producto, tipo_movimiento, cantidad, fecha, motivo, id_cotizacion)
                          VALUES (?, 'salida', ?, NOW(), 'Venta - Cotización confirmada', ?)";
            $stmt_stock = $conn->prepare($sql_stock);
            $stmt_stock->bind_param("idi", $producto['id_producto'], $producto['cantidad'], $id_cotizacion);
            $stmt_stock->execute();
        }
    }
    
    echo json_encode(["status" => 1, "mensaje" => "Cotización confirmada y productos descontados del inventario"]);
} else {
    echo json_encode(["status" => 0, "mensaje" => "No se pudo confirmar la cotización. ¿Ya estaba confirmada?"]);
}
