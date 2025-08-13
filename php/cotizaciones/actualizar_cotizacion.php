<?php
header('Content-Type: application/json');
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    die(json_encode(['status' => 0, 'mensaje' => 'Método no permitido']));
}

require_once '../../db/conexion.php';
require_once '../../includes/sesion.php';
require_once '../../includes/funciones_corte_rollos.php';
require_once '../../includes/funciones_tabuladores.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Verificar autenticación
if (!tieneSesion() || !isset($_SESSION['usuario_id'])) {
    http_response_code(401);
    die(json_encode(['status' => 0, 'mensaje' => 'No autorizado']));
}

$datos = json_decode(file_get_contents('php://input'), true);

if (!$datos || !isset($datos['id_cotizacion'])) {
    die(json_encode(['status' => 0, 'mensaje' => 'Datos inválidos']));
}

$id_cotizacion = (int)$datos['id_cotizacion'];

// Verificar que la cotización existe y está en estado 'pendiente'
$query_verificar = "SELECT estado FROM cotizaciones WHERE id = ?";
$stmt_verificar = mysqli_prepare($conn, $query_verificar);
mysqli_stmt_bind_param($stmt_verificar, "i", $id_cotizacion);
mysqli_stmt_execute($stmt_verificar);
mysqli_stmt_bind_result($stmt_verificar, $estado_actual);
mysqli_stmt_fetch($stmt_verificar);
mysqli_stmt_close($stmt_verificar);

if ($estado_actual !== 'pendiente') {
    die(json_encode(['status' => 0, 'mensaje' => 'Solo se pueden editar cotizaciones pendientes']));
}

// Validar campos requeridos
$campos_requeridos = ['id_cliente', 'area_total', 'tipo_terreno', 'tipo_instalacion', 'garantia_anios', 'precio_instalacion_m2'];
foreach ($campos_requeridos as $campo) {
    if (!isset($datos[$campo]) || $datos[$campo] === '') {
        die(json_encode(['status' => 0, 'mensaje' => "Campo requerido: $campo"]));
    }
}

$id_cliente = (int)$datos['id_cliente'];
$area_total = (float)$datos['area_total'];
$tipo_terreno = $datos['tipo_terreno'];
$tipo_instalacion = $datos['tipo_instalacion'];
$garantia_anios = (int)$datos['garantia_anios'];
$precio_instalacion_m2 = (float)$datos['precio_instalacion_m2'];
$total = isset($datos['total']) ? (float)$datos['total'] : 0.00;
$dibujo_terreno = isset($datos['dibujo_terreno']) ? $datos['dibujo_terreno'] : null;

mysqli_begin_transaction($conn);

try {
    // Obtener valores actuales de los tabuladores
    // Preparar lista de productos para calcular precio base correcto
    $productos_para_tabulador = [];
    if (!empty($datos['rollos'])) {
        $productos_para_tabulador = array_merge($productos_para_tabulador, $datos['rollos']);
    }
    
    $tabuladores = obtenerTabuladoresCotizacion($conn, $area_total, $productos_para_tabulador);
    
    // Calcular IVA si es necesario
    $total_con_iva = $total;
    $aplicar_iva = isset($datos['aplicar_iva']) && $datos['aplicar_iva'];
    
    if ($aplicar_iva) {
        $iva_porcentaje = 0.16;
        $subtotal = $total_con_iva / (1 + $iva_porcentaje);
        $iva = $total_con_iva - $subtotal;
    } else {
        $iva = null;
    }

    // 1. Liberar rollos actuales de la cotización
    if (!liberarRollosCortados($conn, $id_cotizacion)) {
        throw new Exception("Error al liberar rollos actuales");
    }

    // 2. Eliminar detalles existentes
    $query_eliminar = "DELETE FROM detalle_cotizacion WHERE id_cotizacion = ?";
    $stmt_eliminar = mysqli_prepare($conn, $query_eliminar);
    mysqli_stmt_bind_param($stmt_eliminar, "i", $id_cotizacion);
    if (!mysqli_stmt_execute($stmt_eliminar)) {
        throw new Exception("Error al eliminar detalles existentes");
    }
    mysqli_stmt_close($stmt_eliminar);

    // 3. Actualizar datos principales de la cotización
    $query_actualizar = "UPDATE cotizaciones SET 
                        id_cliente = ?, 
                        area_total = ?, 
                        tipo_terreno = ?, 
                        tipo_instalacion = ?, 
                        garantia_anios = ?, 
                        precio_instalacion_m2 = ?,
                        precio_mano_obra_m2 = ?,
                        descuento_volumen_porcentaje = ?,
                        precio_base_pasto_m2 = ?,
                        iva = ?,
                        total = ?, 
                        dibujo_terreno = ?
                        WHERE id = ?";
                        
    $stmt_actualizar = mysqli_prepare($conn, $query_actualizar);
    mysqli_stmt_bind_param($stmt_actualizar, "idssiidddddsi", 
        $id_cliente, 
        $area_total, 
        $tipo_terreno, 
        $tipo_instalacion, 
        $garantia_anios, 
        $tabuladores['precio_instalacion_m2'],
        $tabuladores['precio_mano_obra_m2'],
        $tabuladores['descuento_volumen_porcentaje'],
        $tabuladores['precio_base_pasto_m2'],
        $iva,
        $total, 
        $dibujo_terreno, 
        $id_cotizacion
    );

    if (!mysqli_stmt_execute($stmt_actualizar)) {
        throw new Exception("Error al actualizar cotización: " . mysqli_stmt_error($stmt_actualizar));
    }
    mysqli_stmt_close($stmt_actualizar);

    // 4. Insertar nuevos detalles (rollos)
    if (!empty($datos['rollos']) && is_array($datos['rollos'])) {
        $query_detalle = "INSERT INTO detalle_cotizacion (id_cotizacion, id_producto, id_color, cantidad, precio_unitario, subtotal) VALUES (?, ?, ?, ?, ?, ?)";
        $stmt_detalle = mysqli_prepare($conn, $query_detalle);

        foreach ($datos['rollos'] as $rollo) {
            if (empty($rollo['id_producto']) || empty($rollo['cantidad'])) continue;

            $precio_unitario = (float)($rollo['precio_unitario'] ?? 0);
            $subtotal = (float)($rollo['subtotal'] ?? 0);

            mysqli_stmt_bind_param($stmt_detalle, "iiiddd", 
                $id_cotizacion, 
                $rollo['id_producto'], 
                $rollo['id_color'], 
                $rollo['cantidad'], 
                $precio_unitario, 
                $subtotal
            );

            if (!mysqli_stmt_execute($stmt_detalle)) {
                throw new Exception("Error al insertar rollo");
            }

            // Procesar reserva con corte para el rollo actualizado
            if (!empty($rollo['id_color'])) {
                $resultado_corte = procesarReservaConCorte(
                    $conn, 
                    $id_cotizacion, 
                    $rollo['id_producto'], 
                    $rollo['id_color'], 
                    $rollo['cantidad']
                );
                
                if (!$resultado_corte['exito'] && $resultado_corte['area_faltante'] > 0) {
                    error_log("Cotización actualizada {$id_cotizacion}: Inventario insuficiente para rollo. " . $resultado_corte['mensaje']);
                }
            }
        }
        mysqli_stmt_close($stmt_detalle);
    }

    // 5. Insertar nuevos materiales
    if (!empty($datos['materiales']) && is_array($datos['materiales'])) {
        $query_material = "INSERT INTO detalle_cotizacion (id_cotizacion, id_producto, cantidad, precio_unitario, subtotal) VALUES (?, ?, ?, ?, ?)";
        $stmt_material = mysqli_prepare($conn, $query_material);

        foreach ($datos['materiales'] as $material) {
            if (empty($material['id_producto']) || empty($material['cantidad'])) continue;

            $precio_unitario = (float)($material['precio_unitario'] ?? 0);
            $subtotal = (float)($material['subtotal'] ?? 0);

            mysqli_stmt_bind_param($stmt_material, "iiddd", 
                $id_cotizacion, 
                $material['id_producto'], 
                $material['cantidad'], 
                $precio_unitario, 
                $subtotal
            );

            if (!mysqli_stmt_execute($stmt_material)) {
                throw new Exception("Error al insertar material");
            }
        }
        mysqli_stmt_close($stmt_material);
    }

    mysqli_commit($conn);

    error_log("Cotización {$id_cotizacion} actualizada exitosamente por usuario {$_SESSION['usuario_id']}");

    echo json_encode([
        'status' => 1,
        'mensaje' => 'Cotización actualizada correctamente',
        'id_cotizacion' => $id_cotizacion
    ]);

} catch (Exception $e) {
    mysqli_rollback($conn);
    
    error_log("Error al actualizar cotización {$id_cotizacion}: " . $e->getMessage());
    
    echo json_encode([
        'status' => 0,
        'mensaje' => 'Error al actualizar la cotización: ' . $e->getMessage()
    ]);
}
?>