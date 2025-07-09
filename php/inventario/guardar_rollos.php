<?php
/**
 * Controlador para gestión de rollos temporales y definitivos
 * 
 * Maneja operaciones CRUD para rollos antes de ser guardados en la base de datos
 * y procesa el guardado definitivo cuando se confirma.
 */

$ROOT = '../..';
require_once "$ROOT/db/conexion.php";
require_once "$ROOT/includes/sesion.php";
require_once "$ROOT/includes/config.php";

// Configurar respuesta como JSON
header('Content-Type: application/json');

// Limpiar rollos temporales inválidos al inicio
limpiarRollosTemporales();

// Verificar sesión activa
if (!tieneSesion()) {
    sendJsonResponse(false, 'Sesión no iniciada');
}

// Procesar la solicitud según el método
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    procesarSolicitudPost();
} else {
    sendJsonResponse(false, 'Método no permitido');
}

/**
 * Limpia los rollos temporales con datos inválidos
 */
function limpiarRollosTemporales()
{
    if (!empty($_SESSION['rollos_temporales'])) {
        foreach ($_SESSION['rollos_temporales'] as $color_id => $color_data) {
            if (!isset($color_data['rollos'])) {
                unset($_SESSION['rollos_temporales'][$color_id]);
                continue;
            }

            $_SESSION['rollos_temporales'][$color_id]['rollos'] = array_values(array_filter($color_data['rollos'], function ($r) {
                return isset($r['largo'], $r['ancho']) && $r['largo'] > 0 && $r['ancho'] > 0;
            }));

            if (empty($_SESSION['rollos_temporales'][$color_id]['rollos'])) {
                unset($_SESSION['rollos_temporales'][$color_id]);
            }
        }
    }
}

/**
 * Procesa todas las solicitudes POST
 */
function procesarSolicitudPost()
{
    global $conn;

    if (isset($_POST['guardar_definitivo'])) {
        guardarRollosDefinitivos();
    } elseif (isset($_POST['agregar_temporal'])) {
        agregarRolTemporal();
    } elseif (isset($_POST['ajustar_temporal'])) {
        ajustarRollosTemporales();
    } else {
        sendJsonResponse(false, 'Operación no reconocida');
    }
}

/**
 * Guarda los rollos temporales en la base de datos
 */
function guardarRollosDefinitivos()
{
    global $conn;

    $id_producto = validarIdProducto();

    // Verificar existencia del producto
    $producto = obtenerProducto($id_producto);
    if (!$producto) {
        sendJsonResponse(false, 'Producto no encontrado o eliminado');
    }

    // Verificar que hay rollos para guardar
    if (empty($_SESSION['rollos_temporales'])) {
        sendJsonResponse(false, 'No hay rollos para guardar');
    }

    try {
        $conn->begin_transaction();
        $stmt = $conn->prepare("INSERT INTO inventario_rollos 
                            (id_producto, largo_metros, ancho_metros, id_color, estado, fecha_ingreso) 
                            VALUES (?, ?, ?, ?, 'disponible', NOW())");

        $contador = 0;
        foreach ($_SESSION['rollos_temporales'] as $id_color => $color_data) {
            foreach ($color_data['rollos'] as $rollo) {
                if (!validarDatosRollo($rollo, $id_color)) {
                    throw new Exception("Datos inválidos en rollo del color $id_color");
                }

                $stmt->bind_param(
                    "iddi",
                    $id_producto,
                    $rollo['largo'],
                    $rollo['ancho'],
                    $id_color
                );

                if (!$stmt->execute()) {
                    throw new Exception("Error al guardar rollo del color $id_color");
                }
                $contador++;
            }
        }

        unset($_SESSION['rollos_temporales']);
        $conn->commit();

        sendJsonResponse(true, "Se guardaron $contador rollos exitosamente", '../../modulos/inventario/lista.php');
    } catch (Exception $e) {
        $conn->rollback();
        error_log("Error en guardar_rollos.php: " . $e->getMessage());
        sendJsonResponse(false, 'Error al guardar: ' . $e->getMessage());
    }
}

/**
 * Agrega un nuevo rollo temporal
 */
function agregarRolTemporal()
{
    $id_producto = validarIdProducto();
    $largo = filter_input(INPUT_POST, 'largo', FILTER_VALIDATE_FLOAT);
    $ancho = filter_input(INPUT_POST, 'ancho', FILTER_VALIDATE_FLOAT);
    $cantidad = filter_input(INPUT_POST, 'cantidad', FILTER_VALIDATE_INT);
    $id_color = filter_input(INPUT_POST, 'id_color', FILTER_VALIDATE_INT);

    if ($largo <= 0 || $ancho <= 0 || $cantidad <= 0 || $id_color <= 0) {
        sendJsonResponse(false, 'Datos inválidos para el rollo');
    }

    // Obtener nombre del color
    global $conn;
    $stmt_color = $conn->prepare("SELECT nombre FROM colores WHERE id = ?");
    $stmt_color->bind_param("i", $id_color);
    $stmt_color->execute();
    $color = $stmt_color->get_result()->fetch_assoc();
    $stmt_color->close();

    $nombre_color = $color['nombre'] ?? 'Desconocido';

    // Inicializar array si no existe
    if (!isset($_SESSION['rollos_temporales'][$id_color])) {
        $_SESSION['rollos_temporales'][$id_color] = [
            'nombre_color' => $nombre_color,
            'rollos' => []
        ];
    }

    // Agregar rollos temporales
    for ($i = 0; $i < $cantidad; $i++) {
        $_SESSION['rollos_temporales'][$id_color]['rollos'][] = [
            'largo' => $largo,
            'ancho' => $ancho,
            'area' => $largo * $ancho
        ];
    }

    sendJsonResponse(true, "$cantidad rollos temporales agregados", "../../modulos/inventario/inventariar_rollos.php?id=$id_producto");
}

/**
 * Ajusta los rollos temporales (eliminar individual o por grupo)
 */
function ajustarRollosTemporales()
{
    $id_producto = validarIdProducto();
    $operacion = $_POST['operacion'] ?? '';
    $id_color = filter_input(INPUT_POST, 'id_color', FILTER_VALIDATE_INT);

    if ($id_color === false) {
        sendJsonResponse(false, 'Color inválido');
    }

    if ($operacion === 'eliminar' && isset($_POST['index'])) {
        $index = intval($_POST['index']);
        if (isset($_SESSION['rollos_temporales'][$id_color]['rollos'][$index])) {
            array_splice($_SESSION['rollos_temporales'][$id_color]['rollos'], $index, 1);
            // Eliminar grupo si queda vacío
            if (empty($_SESSION['rollos_temporales'][$id_color]['rollos'])) {
                unset($_SESSION['rollos_temporales'][$id_color]);
            }
            sendJsonResponse(true, 'Rollo eliminado', "../../modulos/inventario/inventariar_rollos.php?id=$id_producto");
        }
    } elseif ($operacion === 'eliminar_grupo' && isset($_POST['grupo_key'])) {
        $grupo_key = $_POST['grupo_key'];
        foreach ($_SESSION['rollos_temporales'] ?? [] as $color_id => $color_data) {
            $_SESSION['rollos_temporales'][$color_id]['rollos'] = array_values(array_filter($color_data['rollos'], function ($r) use ($grupo_key) {
                return ($r['largo'] . '-' . $r['ancho']) !== $grupo_key;
            }));

            if (empty($_SESSION['rollos_temporales'][$color_id]['rollos'])) {
                unset($_SESSION['rollos_temporales'][$color_id]);
            }
        }
        sendJsonResponse(true, 'Grupo de rollos eliminado', "../../modulos/inventario/inventariar_rollos.php?id=$id_producto");
    } else {
        sendJsonResponse(false, 'Operación no válida');
    }
}

/**
 * Valida y retorna el ID de producto
 */
function validarIdProducto()
{
    $id_producto = filter_input(INPUT_POST, 'id_producto', FILTER_VALIDATE_INT);
    if (!$id_producto || $id_producto <= 0) {
        sendJsonResponse(false, 'ID de producto inválido');
    }
    return $id_producto;
}

/**
 * Obtiene un producto de la base de datos
 */
function obtenerProducto($id_producto)
{
    global $conn;

    $stmt = $conn->prepare("SELECT id FROM productos WHERE id = ? AND estado != 'eliminado' LIMIT 1");
    $stmt->bind_param("i", $id_producto);

    if (!$stmt->execute()) {
        sendJsonResponse(false, 'Error al verificar producto');
    }

    return $stmt->get_result()->fetch_assoc();
}

/**
 * Valida los datos de un rollo individual
 */
function validarDatosRollo($rollo, $id_color)
{
    return isset($rollo['largo'], $rollo['ancho']) &&
        $id_color > 0 &&
        $rollo['largo'] > 0 &&
        $rollo['ancho'] > 0;
}

/**
 * Envía una respuesta JSON estandarizada
 */
function sendJsonResponse($success, $message, $redirect = null)
{
    echo json_encode([
        'status' => $success ? 1 : 0,
        'mensaje' => $message,
        'redirect' => $redirect
    ]);
    exit;
}