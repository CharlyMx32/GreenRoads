<?php
/**
 * Manejador de operaciones de rollos de inventario
 * Procesa peticiones AJAX para operaciones temporales y permanentes de rollos
 */

// Configuración e includes
$ROOT = '../..';
include_once $ROOT . '/db/conexion.php';
include_once $ROOT . '/includes/sesion.php';
include_once $ROOT . '/includes/config.php';

// Establecer cabecera de respuesta JSON
header('Content-Type: application/json');

/**
 * Helper para respuestas JSON estandarizadas
 * 
 * @param bool $success Indica si la operación fue exitosa
 * @param string $message Mensaje de retroalimentación
 * @param array $data Datos adicionales de respuesta
 */
function jsonResponse($success, $message, $data = []) {
    $response = [
        'status' => $success ? 1 : 0,
        'mensaje' => $message,
        'data' => $data
    ];
    
    if ($success && isset($data['redirect'])) {
        $response['redirect'] = $data['redirect'];
    }
    
    echo json_encode($response);
    exit;
}

try {
    // Validar sesión de usuario
    if (!tieneSesion()) {
        jsonResponse(false, 'Sesión no iniciada', ['redirect' => "$URL_ROOT/login"]);
    }

    // Solo permitir peticiones POST
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        jsonResponse(false, 'Método no permitido');
    }

    // Validar y sanitizar datos de entrada
    $id_producto = filter_input(INPUT_POST, 'id_producto', FILTER_VALIDATE_INT, [
        'options' => ['min_range' => 1]
    ]);
    
    $id_color = filter_input(INPUT_POST, 'id_color', FILTER_VALIDATE_INT, [
        'options' => ['min_range' => 1]
    ]);
    
    $id_admin = $_SESSION['usuario_id'] ?? null;

    if (!$id_producto || !$id_color || !$id_admin) {
        jsonResponse(false, 'Datos inválidos o incompletos');
    }

    // Procesar diferentes tipos de operaciones
    if (isset($_POST['agregar_temporal'])) {
        manejarAgregarTemporal();
    } elseif (isset($_POST['ajustar_temporal'])) {
        manejarAjustarTemporal();
    } elseif (isset($_POST['guardar_definitivo'])) {
        manejarGuardarPermanente();
    } else {
        jsonResponse(false, 'Operación no reconocida');
    }

} catch (Exception $e) {
    error_log("Error en editar_rollos.php: " . $e->getMessage());
    jsonResponse(false, 'Error en el servidor: ' . $e->getMessage());
}

/**
 * Maneja la adición de rollos temporales a la sesión
 */
function manejarAgregarTemporal() {
    global $id_producto, $id_color;
    
    // Validar dimensiones y cantidad
    $largo = filter_input(INPUT_POST, 'largo', FILTER_VALIDATE_FLOAT, [
        'options' => ['min_range' => 0.01]
    ]);
    
    $ancho = filter_input(INPUT_POST, 'ancho', FILTER_VALIDATE_FLOAT, [
        'options' => ['min_range' => 0.01]
    ]);
    
    $cantidad = filter_input(INPUT_POST, 'cantidad', FILTER_VALIDATE_INT, [
        'options' => ['min_range' => 1]
    ]);

    if (!$largo || !$ancho || !$cantidad) {
        jsonResponse(false, 'Dimensiones o cantidad inválidas');
    }

    // Inicializar almacenamiento en sesión si es necesario
    if (!isset($_SESSION['rollos_temporales'])) {
        $_SESSION['rollos_temporales'] = [];
    }

    if (!isset($_SESSION['rollos_temporales'][$id_color])) {
        $_SESSION['rollos_temporales'][$id_color] = [];
    }

    // Agregar rollos temporales con marca de tiempo
    $now = date('Y-m-d H:i:s');
    for ($i = 0; $i < $cantidad; $i++) {
        $_SESSION['rollos_temporales'][$id_color][] = [
            'largo' => $largo,
            'ancho' => $ancho,
            'area' => $largo * $ancho,
            'id_color' => $id_color,
            'fecha_agregado' => $now
        ];
    }

    jsonResponse(true, "$cantidad rollo(s) agregado(s) temporalmente", [
        'redirect' => "editar_rollos.php?id=$id_producto&id_color=$id_color",
        'total_temporales' => count($_SESSION['rollos_temporales'][$id_color])
    ]);
}

/**
 * Maneja la eliminación de grupos de rollos temporales
 */
function manejarAjustarTemporal() {
    global $id_producto, $id_color;
    
    $grupo_key = $_POST['grupo_key'] ?? '';
    $operacion = $_POST['operacion'] ?? '';

    if (empty($grupo_key)) {
        jsonResponse(false, 'Identificador de grupo inválido');
    }

    if (!isset($_SESSION['rollos_temporales'][$id_color])) {
        jsonResponse(false, 'No hay rollos temporales para este color');
    }

    // Parsear clave de grupo de dimensiones (formato "largo-ancho")
    $partes = explode('-', $grupo_key);
    if (count($partes) !== 2) {
        jsonResponse(false, 'Formato de grupo inválido');
    }

    $largo = (float)$partes[0];
    $ancho = (float)$partes[1];

    // Contar antes de eliminar para retroalimentación
    $total_antes = count($_SESSION['rollos_temporales'][$id_color]);

    // Filtrar rollos que coincidan con este grupo de dimensiones
    $_SESSION['rollos_temporales'][$id_color] = array_values(array_filter(
        $_SESSION['rollos_temporales'][$id_color],
        function($r) use ($largo, $ancho) {
            return $r['largo'] != $largo || $r['ancho'] != $ancho;
        }
    ));

    $total_eliminados = $total_antes - count($_SESSION['rollos_temporales'][$id_color]);

    jsonResponse(true, "Se eliminaron $total_eliminados rollo(s)", [
        'redirect' => "editar_rollos.php?id=$id_producto&id_color=$id_color",
        'total_temporales' => count($_SESSION['rollos_temporales'][$id_color])
    ]);
}

/**
 * Maneja el guardado permanente de rollos temporales en la base de datos
 */
function manejarGuardarPermanente() {
    global $conn, $id_producto, $id_color, $id_admin;
    
    if (empty($_SESSION['rollos_temporales'][$id_color])) {
        jsonResponse(false, 'No hay rollos temporales para guardar');
    }

    $conn->begin_transaction();

    try {
        // Validar que existe el producto
        $stmt = $conn->prepare("SELECT 1 FROM productos WHERE id = ? LIMIT 1");
        $stmt->bind_param("i", $id_producto);
        $stmt->execute();
        if (!$stmt->get_result()->fetch_assoc()) {
            throw new Exception("Producto no encontrado");
        }
        $stmt->close();

        // Validar que existe el color
        $stmt = $conn->prepare("SELECT 1 FROM colores WHERE id = ? LIMIT 1");
        $stmt->bind_param("i", $id_color);
        $stmt->execute();
        if (!$stmt->get_result()->fetch_assoc()) {
            throw new Exception("Color no encontrado");
        }
        $stmt->close();

        // Insertar todos los rollos temporales
        $insertados = 0;
        $rollos_temporales = $_SESSION['rollos_temporales'][$id_color];
        
        foreach ($rollos_temporales as $rollo) {
            $stmt = $conn->prepare("INSERT INTO inventario_rollos 
                (id_producto, largo_metros, ancho_metros, id_color, estado, fecha_ingreso) 
                VALUES (?, ?, ?, ?, 'disponible', NOW())");
            $stmt->bind_param("iddi", $id_producto, $rollo['largo'], $rollo['ancho'], $id_color);
            if (!$stmt->execute()) {
                throw new Exception("Error al guardar rollo");
            }
            $insertados++;
            $stmt->close();
        }

        // Registrar movimiento en inventario
        $stmt = $conn->prepare("INSERT INTO movimientos_inventario 
            (id_producto, cantidad, tipo_movimiento, motivo, id_admin) 
            VALUES (?, ?, 'entrada', 'Agregado desde interfaz de edición', ?)");
        $cantidad_total = count($rollos_temporales);
        $stmt->bind_param("iii", $id_producto, $cantidad_total, $id_admin);
        $stmt->execute();
        $stmt->close();

        $conn->commit();

        // Limpiar almacenamiento temporal
        unset($_SESSION['rollos_temporales'][$id_color]);

        jsonResponse(true, "Se guardaron $insertados rollo(s) exitosamente", [
            'redirect' => "editar_rollos.php?id=$id_producto&id_color=$id_color"
        ]);

    } catch (Exception $e) {
        $conn->rollback();
        jsonResponse(false, "Error al guardar: " . $e->getMessage());
    }
}
?>