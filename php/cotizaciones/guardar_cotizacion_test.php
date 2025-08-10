<?php
// Archivo temporal para probar guardado sin autenticación
header('Content-Type: application/json');
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    die(json_encode(['status' => 0, 'mensaje' => 'Método no permitido']));
}

require_once '../../db/conexion.php';

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

mysqli_begin_transaction($conn);

try {
    // Insertar cotización
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
        fecha
    ) VALUES (?, 1, 'pendiente', ?, ?, ?, ?, ?, ?, NOW())";

    $stmt = mysqli_prepare($conn, $query);
    if (!$stmt) {
        throw new Exception("Error al preparar la consulta: " . mysqli_error($conn));
    }

    mysqli_stmt_bind_param(
        $stmt,
        "iddssid",
        $datos['id_cliente'],
        $datos['total'],
        $datos['area_total'],
        $datos['tipo_terreno'],
        $datos['tipo_instalacion'],
        $datos['garantia'],
        $datos['precio_instalacion']
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
            $sql_verificar = "SELECT SUM(area_m2) AS area_disponible, COUNT(*) as num_rollos
                FROM inventario_rollos
                WHERE id_producto = ? AND id_color = ? AND estado = 'disponible'";

            $stmt_verificar = mysqli_prepare($conn, $sql_verificar);
            mysqli_stmt_bind_param($stmt_verificar, "ii", $rollo['id_producto'], $rollo['id_color']);
            mysqli_stmt_execute($stmt_verificar);
            $result_verificar = mysqli_stmt_get_result($stmt_verificar);
            $disponibilidad = mysqli_fetch_assoc($result_verificar);

            $area_disponible = $disponibilidad['area_disponible'] ?? 0;
            $num_rollos = $disponibilidad['num_rollos'] ?? 0;

            // Determinar precio según disponibilidad
            $precio_unitario = $rollo['precio_unitario'];
            $mensaje_inventario = "";
            
            if ($area_disponible <= 0) {
                $precio_unitario = 1000.00;
                $mensaje_inventario = "Sin inventario - usando precio base $1000";
            } else if ($area_disponible < $rollo['cantidad']) {
                $mensaje_inventario = "Inventario insuficiente ({$area_disponible} m² disponibles) - usando precio original";
            } else {
                $mensaje_inventario = "Inventario suficiente ({$area_disponible} m²) - precio normal";
            }

            // Insertar detalle
            $query_detalle = "INSERT INTO detalle_cotizacion (
                id_cotizacion, 
                id_producto, 
                id_color,
                cantidad, 
                area_usada,
                precio_unitario
            ) VALUES (?, ?, ?, ?, ?, ?)";

            $stmt_detalle = mysqli_prepare($conn, $query_detalle);
            mysqli_stmt_bind_param(
                $stmt_detalle,
                "iiiddd",
                $id_cotizacion,
                $rollo['id_producto'],
                $rollo['id_color'],
                $rollo['cantidad'],
                $rollo['cantidad'],
                $precio_unitario
            );
            
            if (!mysqli_stmt_execute($stmt_detalle)) {
                throw new Exception("Error al insertar detalle: " . mysqli_stmt_error($stmt_detalle));
            }
            mysqli_stmt_close($stmt_detalle);
            
            error_log("Cotización $id_cotizacion - Rollo procesado: $mensaje_inventario");
        }
    }

    // Procesar productos generales
    if (!empty($datos['productos']) && is_array($datos['productos'])) {
        foreach ($datos['productos'] as $producto) {
            if (empty($producto['id_producto'])) continue;

            // Obtener el costo real del producto o usar precio base
            $costo = 50.00; // Precio base para productos sin historial
            
            $sqlCosto = "SELECT costo_unitario 
                    FROM movimientos_inventario 
                    WHERE id_producto = ? AND tipo_movimiento = 'entrada'
                    ORDER BY fecha DESC 
                    LIMIT 1";
            $stmtCosto = $conn->prepare($sqlCosto);
            $stmtCosto->bind_param("i", $producto['id_producto']);
            $stmtCosto->execute();
            $resultCosto = $stmtCosto->get_result();
            $row = $resultCosto->fetch_assoc();
            if ($row && $row['costo_unitario'] > 0) {
                $costo = $row['costo_unitario'];
            }

            $query_producto = "INSERT INTO detalle_cotizacion (
                id_cotizacion, 
                id_producto, 
                cantidad, 
                precio_unitario
            ) VALUES (?, ?, ?, ?)";

            $stmt_producto = mysqli_prepare($conn, $query_producto);
            mysqli_stmt_bind_param(
                $stmt_producto,
                "iidd",
                $id_cotizacion,
                $producto['id_producto'],
                $producto['cantidad'],
                $costo
            );
            
            if (!mysqli_stmt_execute($stmt_producto)) {
                throw new Exception("Error al insertar producto: " . mysqli_stmt_error($stmt_producto));
            }
            mysqli_stmt_close($stmt_producto);
        }
    }

    // Procesar extras
    if (!empty($datos['extras']) && is_array($datos['extras'])) {
        foreach ($datos['extras'] as $extra) {
            if (empty($extra['id_extra'])) continue;

            $query_extra = "INSERT INTO cotizacion_extras (
                id_cotizacion, 
                id_extra, 
                precio_aplicado
            ) VALUES (?, ?, ?)";

            $stmt_extra = mysqli_prepare($conn, $query_extra);
            mysqli_stmt_bind_param(
                $stmt_extra,
                "iid",
                $id_cotizacion,
                $extra['id_extra'],
                $extra['precio']
            );
            
            if (!mysqli_stmt_execute($stmt_extra)) {
                throw new Exception("Error al insertar extra: " . mysqli_stmt_error($stmt_extra));
            }
            mysqli_stmt_close($stmt_extra);
        }
    }

    mysqli_commit($conn);
    echo json_encode([
        'status' => 1, 
        'mensaje' => 'Cotización guardada correctamente', 
        'id_cotizacion' => $id_cotizacion,
        'detalles' => [
            'rollos_procesados' => count($datos['rollos'] ?? []),
            'productos_procesados' => count($datos['productos'] ?? []),
            'extras_procesados' => count($datos['extras'] ?? [])
        ]
    ]);
    
} catch (Exception $e) {
    mysqli_rollback($conn);
    error_log("Error al guardar cotización: " . $e->getMessage());
    echo json_encode(['status' => 0, 'mensaje' => 'Error al guardar: ' . $e->getMessage()]);
}
?>
