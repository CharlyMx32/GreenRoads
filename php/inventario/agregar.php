<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once '../../db/conexion.php';
require_once '../../includes/sesion.php';

if (!tieneSesion()) {
    header('Location: ../../login');
    exit();
}

// Obtener datos del formulario
$data = json_decode(file_get_contents('php://input'), true) ?? $_POST;
$id_producto = intval($data['id_producto'] ?? 0);
$cantidad = floatval($data['cantidad'] ?? 0);
$cantidad_rollos = intval($data['cantidad_rollos'] ?? 0);
$largo = floatval($data['largo'] ?? 0);
$ancho = floatval($data['ancho'] ?? 0);
$id_admin = $_SESSION['usuario']; 

// Validaciones básicas
if ($id_producto <= 0) {
    die(json_encode(['error' => 'Selecciona un producto válido']));
}

// Verificar tipo de producto
$stmt = $conn->prepare("SELECT id_tipo_producto FROM productos WHERE id = ? AND estado = 'activo'");
$stmt->bind_param('i', $id_producto);
$stmt->execute();
$producto = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$producto) {
    die(json_encode(['error' => 'Producto no encontrado o inactivo']));
}

$id_tipo = $producto['id_tipo_producto'];
$conn->begin_transaction();

try {
    // Crear entrada en inventario si no existe
    $stmt = $conn->prepare("INSERT IGNORE INTO inventario (id_producto) VALUES (?)");
    $stmt->bind_param("i", $id_producto);
    $stmt->execute();
    $stmt->close();

    if ($id_tipo == 1) { // Productos que requieren medidas (rollos)
        if ($largo <= 0 || $ancho <= 0 || $cantidad_rollos <= 0) {
            throw new Exception("Medidas inválidas. Largo, ancho y cantidad de rollos deben ser mayores a 0");
        }

        // Insertar rollos individuales
        for ($i = 0; $i < $cantidad_rollos; $i++) {
            $stmt = $conn->prepare("INSERT INTO inventario_rollos (id_producto, largo_metros, ancho_metros, estado) VALUES (?, ?, ?, 'disponible')");
            $stmt->bind_param("idd", $id_producto, $largo, $ancho);
            $stmt->execute();
            $stmt->close();
        }

        $area_total = $largo * $ancho * $cantidad_rollos;
        $tipo = 'entrada';
        $motivo = "Inventario manual (rollos)";
        
        $stmt = $conn->prepare("INSERT INTO movimientos_inventario (id_producto, cantidad, tipo_movimiento, motivo, id_admin) VALUES (?, ?, ?, ?, ?)");
        $stmt->bind_param("idssi", $id_producto, $area_total, $tipo, $motivo, $id_admin);
        $stmt->execute();
        $stmt->close();
    } else { // Productos normales
        if ($cantidad <= 0) {
            throw new Exception('La cantidad debe ser mayor a 0');
        }

        $tipo = 'entrada';
        $motivo = "Inventario manual";
        $stmt = $conn->prepare("INSERT INTO movimientos_inventario (id_producto, cantidad, tipo_movimiento, motivo, id_admin) VALUES (?, ?, ?, ?, ?)");
        $stmt->bind_param("idssi", $id_producto, $cantidad, $tipo, $motivo, $id_admin);
        $stmt->execute();
        $stmt->close();
    }

    $conn->commit();
    echo json_encode([
        'success' => true,
        'message' => 'Inventario actualizado correctamente',
        'redirect' => '../../modulos/inventario/lista.php?success=1'
    ]);
} catch (Exception $e) {
    $conn->rollback();
    http_response_code(500);
    echo json_encode(['error' => 'Error al guardar: ' . $e->getMessage()]);
}