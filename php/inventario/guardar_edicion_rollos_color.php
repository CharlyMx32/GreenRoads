<?php
require_once '../../db/conexion.php';
require_once '../../includes/sesion.php';
require_once '../../includes/config.php';

// Configuración de errores
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/php_errors.log');

if (ob_get_length()) ob_clean();

header('Content-Type: application/json');

function sendError($message, $code = 500) {
    http_response_code($code);
    die(json_encode([
        'status' => 0,
        'mensaje' => $message,
        'error' => $code
    ]));
}

try {
    if (!tieneSesion()) sendError('Sesión inválida', 401);
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') sendError('Método no permitido', 405);

    $id_producto = filter_input(INPUT_POST, 'id_producto', FILTER_VALIDATE_INT);
    $id_color = filter_input(INPUT_POST, 'id_color', FILTER_VALIDATE_INT);
    
    if (!$id_producto || $id_producto <= 0) sendError('ID de producto inválido', 400);
    if (!$id_color || $id_color <= 0) sendError('ID de color inválido', 400);
    if (!isset($_POST['rollos'])) sendError('No se recibieron datos de rollos', 400);
    
    $rollos_data = $_POST['rollos'];
    if (!is_array($rollos_data)) sendError('Formato de datos de rollos inválido', 400);

    if (!$conn->begin_transaction()) {
        throw new Exception("No se pudo iniciar la transacción");
    }

    $rollos_editados = 0;
    $rollos_eliminados = 0;
    $id_lote = null;

    foreach ($rollos_data as $rollo_id => $datos) {
        $rollo_id = filter_var($rollo_id, FILTER_VALIDATE_INT);
        if (!$rollo_id) continue;

        // Procesar eliminación
        if (isset($datos['eliminar']) && $datos['eliminar'] == '1') {
            $stmt = $conn->prepare("UPDATE inventario_rollos SET estado = 'eliminado' WHERE id = ? AND id_producto = ? AND id_color = ?");
            $stmt->bind_param("iii", $rollo_id, $id_producto, $id_color);
            if ($stmt->execute()) $rollos_eliminados++;
            $stmt->close();
            continue; 
        }
        
        // Procesar edición 
        $costo = filter_var(str_replace([',', ' '], ['.', ''], $datos['costo_unitario'] ?? ''), FILTER_VALIDATE_FLOAT);
        $largo = filter_var(str_replace([',', ' '], ['.', ''], $datos['largo'] ?? ''), FILTER_VALIDATE_FLOAT);
        $ancho = filter_var(str_replace([',', ' '], ['.', ''], $datos['ancho'] ?? ''), FILTER_VALIDATE_FLOAT);

        if ($costo === false || $costo <= 0) throw new Exception("Costo inválido para rollo $rollo_id", 400);
        if ($largo === false || $largo <= 0) throw new Exception("Largo inválido para rollo $rollo_id", 400);
        if ($ancho === false || $ancho <= 0) throw new Exception("Ancho inválido para rollo $rollo_id", 400);

        $stmt = $conn->prepare("UPDATE inventario_rollos SET largo_metros=?, ancho_metros=?, costo_unitario=? WHERE id=? AND id_producto=? AND id_color=?");
        $stmt->bind_param("dddiii", $largo, $ancho, $costo, $rollo_id, $id_producto, $id_color);
        if ($stmt->execute()) $rollos_editados++;
        $stmt->close();


        if (!$id_lote) {
            $stmt = $conn->prepare("SELECT id_lote FROM inventario_rollos WHERE id=? LIMIT 1");
            $stmt->bind_param("i", $rollo_id);
            $stmt->execute();
            $result = $stmt->get_result();
            if ($row = $result->fetch_assoc()) $id_lote = $row['id_lote'];
            $stmt->close();
        }
    }

    // Registrar movimiento si hubo cambios
    if (($rollos_editados > 0 || $rollos_eliminados > 0) && $id_lote) {
        $stmt = $conn->prepare("INSERT INTO movimientos_inventario 
                              (id_producto, id_lote, cantidad, costo_unitario, tipo_movimiento, motivo, id_admin) 
                              VALUES (?, ?, 0, 0, 'ajuste', ?, ?)");
        $motivo = "Edición de rollos (Editados: $rollos_editados, Eliminados: $rollos_eliminados)";
        $id_admin = $_SESSION['id_admin'] ?? null;
        $stmt->bind_param("iisi", $id_producto, $id_lote, $motivo, $id_admin);
        $stmt->execute();
        $stmt->close();
    }

    $conn->commit();

    echo json_encode([
        'status' => 1,
        'mensaje' => "Cambios guardados correctamente",
        'data' => [
            'editados' => $rollos_editados,
            'eliminados' => $rollos_eliminados
        ]
    ]);

} catch (Exception $e) {
    if (isset($conn)) $conn->rollback();
    error_log("Error: " . $e->getMessage());
    sendError($e->getMessage(), $e->getCode() ?: 500);
}