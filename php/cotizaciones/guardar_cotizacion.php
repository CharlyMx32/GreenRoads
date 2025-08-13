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

// Iniciar sesión si no está iniciada
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Verificar autenticación
if (!tieneSesion() || !isset($_SESSION['usuario_id'])) {
    echo json_encode(['status' => 0, 'mensaje' => 'No autorizado']);
    exit();
}

// Usar ID admin de la sesión
$id_admin = $_SESSION['usuario_id'];

$input = file_get_contents('php://input');
$datos = json_decode($input, true);

if (!$datos || json_last_error() !== JSON_ERROR_NONE) {
    echo json_encode(['status' => 0, 'mensaje' => 'Datos inválidos']);
    exit();
}

// Validar campos obligatorios
$camposRequeridos = ['id_cliente', 'tipo_terreno', 'tipo_instalacion', 'garantia', 'total', 'precio_instalacion', 'area_total'];
foreach ($camposRequeridos as $campo) {
    if (!isset($datos[$campo])) {
        echo json_encode(['status' => 0, 'mensaje' => "Falta el campo requerido: $campo"]);
        exit();
    }
}

function obtenerCostoProducto($conn, $idProducto) {
    $query = "SELECT costo_base FROM productos WHERE id = ?";
             
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $idProducto);
    $stmt->execute();
    $result = $stmt->get_result();
    
    return $result->fetch_assoc()['costo_base'] ?? 0;
}

mysqli_begin_transaction($conn);

try {
    // Obtener valores actuales de los tabuladores
    $area_total = floatval($datos['area_total']);
    
    // Preparar lista de productos para calcular precio base correcto
    $productos_para_tabulador = [];
    if (!empty($datos['rollos'])) {
        $productos_para_tabulador = array_merge($productos_para_tabulador, $datos['rollos']);
    }
    
    $tabuladores = obtenerTabuladoresCotizacion($conn, $area_total, $productos_para_tabulador);
    
    // Calcular IVA si es necesario
    // El total que viene del frontend ya incluye IVA si está aplicado
    $total_con_iva = floatval($datos['total']);
    $aplicar_iva = isset($datos['aplicar_iva']) && $datos['aplicar_iva'];
    
    // Si se aplica IVA, calcular el subtotal y el IVA por separado
    if ($aplicar_iva) {
        // Total = Subtotal + IVA, donde IVA = Subtotal * 0.16
        // Entonces: Total = Subtotal * 1.16
        // Subtotal = Total / 1.16
        $iva_porcentaje = 0.16; // Obtener de configuración si es necesario
        $subtotal = $total_con_iva / (1 + $iva_porcentaje);
        $iva = $total_con_iva - $subtotal;
    } else {
        $subtotal = $total_con_iva;
        $iva = null;
    }

    // Incluir dibujo_terreno y los nuevos campos de tabuladores en la consulta
    $query = "INSERT INTO cotizaciones (
        id_cliente, 
        id_admin, 
        estado, 
        total, 
        area_total,
        tipo_terreno, 
        tipo_instalacion,   
        garantia_anios,     
        precio_instalacion_m2,
        precio_mano_obra_m2,
        descuento_volumen_porcentaje,
        precio_base_pasto_m2,
        iva,
        dibujo_terreno,
        fecha
    ) VALUES (?, ?, 'pendiente', ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())";

    $stmt = mysqli_prepare($conn, $query);
    if (!$stmt) {
        throw new Exception("Error al preparar la consulta: " . mysqli_error($conn));
    }

    $id_admin = isset($_SESSION['usuario_id']) ? $_SESSION['usuario_id'] : 1;
    
    mysqli_stmt_bind_param(
        $stmt,
        "iiddssiddddds",
        $datos['id_cliente'],
        $id_admin,
        $total_con_iva,
        $datos['area_total'],
        $datos['tipo_terreno'],
        $datos['tipo_instalacion'],
        $datos['garantia'],
        $tabuladores['precio_instalacion_m2'],
        $tabuladores['precio_mano_obra_m2'],
        $tabuladores['descuento_volumen_porcentaje'],
        $tabuladores['precio_base_pasto_m2'],
        $iva,
        $datos['dibujo_terreno']
    );

    if (!mysqli_stmt_execute($stmt)) {
        throw new Exception("Error al ejecutar la consulta: " . mysqli_stmt_error($stmt));
    }

    $id_cotizacion = mysqli_insert_id($conn);
    mysqli_stmt_close($stmt);

    // Procesar rollos de pasto
    if (!empty($datos['rollos']) && is_array($datos['rollos'])) {
        foreach ($datos['rollos'] as $rollo) {
            if (!isset($rollo['id_producto'], $rollo['id_color'], $rollo['cantidad'], $rollo['precio_unitario'])) {
                continue;
            }

            // Verificar disponibilidad
            $sql_verificar = "SELECT SUM(area_m2) AS area_disponible
                FROM inventario_rollos
                WHERE id_producto = ?
                AND id_color = ?
                AND estado = 'disponible'";

            $stmt_verificar = mysqli_prepare($conn, $sql_verificar);
            mysqli_stmt_bind_param($stmt_verificar, "ii", $rollo['id_producto'], $rollo['id_color']);
            mysqli_stmt_execute($stmt_verificar);
            $result_verificar = mysqli_stmt_get_result($stmt_verificar);
            $disponibilidad = mysqli_fetch_assoc($result_verificar);

            // Si no hay inventario disponible, usar precio base del producto
            $precio_unitario = $rollo['precio_unitario'];
            if ($disponibilidad['area_disponible'] <= 0) {
                $sql_precio_base = "SELECT costo_base FROM productos WHERE id = ?";
                $stmt_precio = mysqli_prepare($conn, $sql_precio_base);
                mysqli_stmt_bind_param($stmt_precio, "i", $rollo['id_producto']);
                mysqli_stmt_execute($stmt_precio);
                $result_precio = mysqli_stmt_get_result($stmt_precio);
                $producto_info = mysqli_fetch_assoc($result_precio);
                $precio_unitario = $producto_info['costo_base'] ?? 1000.00;
                mysqli_stmt_close($stmt_precio);
                
            }

            $query = "INSERT INTO detalle_cotizacion (
                id_cotizacion, 
                id_producto, 
                id_color,
                cantidad, 
                area_usada,
                precio_unitario
            ) VALUES (?, ?, ?, ?, ?, ?)";

            $stmt = mysqli_prepare($conn, $query);
            mysqli_stmt_bind_param(
                $stmt,
                "iiiddd",
                $id_cotizacion,
                $rollo['id_producto'],
                $rollo['id_color'],
                $rollo['cantidad'],
                $rollo['cantidad'],
                $precio_unitario
            );
            mysqli_stmt_execute($stmt);
            $id_detalle = mysqli_insert_id($conn);
            mysqli_stmt_close($stmt);

            // Solo procesar reserva si hay inventario disponible
            if ($disponibilidad['area_disponible'] > 0) {
                // Procesamiento con corte de rollos habilitado
                $resultado_corte = procesarReservaConCorte(
                    $conn, 
                    $id_cotizacion, 
                    $rollo['id_producto'], 
                    $rollo['id_color'], 
                    min($rollo['cantidad'], $disponibilidad['area_disponible'])
                );
                
                if (!$resultado_corte['exito'] && $resultado_corte['area_faltante'] > 0) {
                    error_log("Cotización {$id_cotizacion}: Inventario insuficiente. " . $resultado_corte['mensaje']);
                } else {
                    error_log("Cotización {$id_cotizacion}: Rollos reservados con corte automático: " . implode(', ', $resultado_corte['rollos_reservados']));
                }
            } else {
                error_log("Cotización {$id_cotizacion}: Rollo agregado sin inventario - usando precio base");
            }
        }
    }

    if (!empty($datos['productos']) && is_array($datos['productos'])) {
        foreach ($datos['productos'] as $producto) {
            if (empty($producto['id_producto'])) continue;

            // Obtener el costo base del producto
            $sqlCosto = "SELECT costo_base FROM productos WHERE id = ?";
            $stmtCosto = $conn->prepare($sqlCosto);
            $stmtCosto->bind_param("i", $producto['id_producto']);
            $stmtCosto->execute();
            $resultCosto = $stmtCosto->get_result();
            $costo = $resultCosto->fetch_assoc()['costo_base'] ?? 0;

            // Insertar en detalle_cotizacion con el costo base del producto
            $query = "INSERT INTO detalle_cotizacion (
            id_cotizacion, 
            id_producto, 
            cantidad, 
            precio_unitario
        ) VALUES (?, ?, ?, ?)";

            $stmt = mysqli_prepare($conn, $query);
            mysqli_stmt_bind_param(
                $stmt,
                "iidd",
                $id_cotizacion,
                $producto['id_producto'],
                $producto['cantidad'],
                $costo // Precio unitario = costo base del producto
            );
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);
        }
    }

    // Procesar extras
    if (!empty($datos['extras']) && is_array($datos['extras'])) {
        foreach ($datos['extras'] as $extra) {
            if (empty($extra['id_extra'])) continue;

            $query = "INSERT INTO cotizacion_extras (
                id_cotizacion, 
                id_extra, 
                precio_aplicado
            ) VALUES (?, ?, ?)";

            $stmt = mysqli_prepare($conn, $query);
            mysqli_stmt_bind_param(
                $stmt,
                "iid",
                $id_cotizacion,
                $extra['id_extra'],
                $extra['precio']
            );
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);
        }
    }

    mysqli_commit($conn);
    echo json_encode(['status' => 1, 'mensaje' => 'Cotización guardada correctamente', 'id_cotizacion' => $id_cotizacion]);
} catch (Exception $e) {
    mysqli_rollback($conn);
    error_log("Error al guardar cotización: " . $e->getMessage());
    echo json_encode(['status' => 0, 'mensaje' => 'Error al guardar: ' . $e->getMessage()]);
}
