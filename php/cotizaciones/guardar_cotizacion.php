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
$camposRequeridos = ['id_cliente', 'tipo_terreno', 'tipo_instalacion', 'garantia', 'total', 'precio_instalacion', 'area_total', 'direccion_cotizacion'];
foreach ($camposRequeridos as $campo) {
    if (!isset($datos[$campo]) || trim($datos[$campo]) === '') {
        echo json_encode(['status' => 0, 'mensaje' => "Falta el campo requerido: $campo"]);
        exit();
    }
}

mysqli_begin_transaction($conn);

try {
    // Obtener parámetro de vigencia de cotizaciones
    $dias_vigencia = 30; // Default por si no existe el parámetro
    $query_vigencia = "SELECT valor FROM parametros_sistema WHERE clave = 'vigencia_cotizacion_dias'";
    $result_vigencia = mysqli_query($conn, $query_vigencia);
    if ($result_vigencia && $row_vigencia = mysqli_fetch_assoc($result_vigencia)) {
        $dias_vigencia = intval($row_vigencia['valor']);
    }
    
    // Obtener valores actuales de los tabuladores
    $area_total = floatval($datos['area_total']);
    
    // Preparar lista de productos para calcular precio base correcto
    $productos_para_tabulador = [];
    if (!empty($datos['rollos'])) {
        $productos_para_tabulador = array_merge($productos_para_tabulador, $datos['rollos']);
    }
    
    $tabuladores = obtenerTabuladoresCotizacion($conn, $area_total, $productos_para_tabulador);
    
    // Determinar el total a guardar según el tipo de cotización
    if ($datos['es_comparativa'] === 'S' || $datos['es_comparativa'] === 1 || $datos['es_comparativa'] == 1) {
        // Para cotizaciones comparativas, usar un valor temporal
        // El total real se calculará cuando se seleccione una opción específica
        $total_con_iva = 1000.00; // Valor temporal
        $iva = null; // IVA se calculará después
    } else {
        // Para cotizaciones simples, usar el total calculado del frontend
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
    }

    // Incluir dibujo_terreno y los nuevos campos de tabuladores en la consulta

    $query = "INSERT INTO cotizaciones (
        id_cliente, 
        direccion,
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
        es_comparativa,
        dibujo_terreno,
        fecha,
        fecha_vencimiento
    ) VALUES (?, ?, ?, 'pendiente', ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), DATE_ADD(NOW(), INTERVAL ? DAY))";

    $stmt = mysqli_prepare($conn, $query);
    if (!$stmt) {
        throw new Exception("Error al preparar la consulta: " . mysqli_error($conn));
    }

    $id_admin = isset($_SESSION['usuario_id']) ? $_SESSION['usuario_id'] : 1;

    mysqli_stmt_bind_param(
        $stmt,
        "issddssiddddsdsi",
        $datos['id_cliente'],
        $datos['direccion_cotizacion'],
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
        $datos['es_comparativa'],
        $datos['dibujo_terreno'],
        $dias_vigencia
    );

    if (!mysqli_stmt_execute($stmt)) {
        throw new Exception("Error al ejecutar la consulta: " . mysqli_stmt_error($stmt));
    }

    $id_cotizacion = mysqli_insert_id($conn);
    mysqli_stmt_close($stmt);

    // Procesar rollos de pasto
    if (!empty($datos['rollos']) && is_array($datos['rollos'])) {
        $opcion_comparativa_index = 1; // Para cotizaciones comparativas, numerar las opciones
        
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

            // Obtener precio unitario correcto basado en tabuladores y costo base
            $sql_precio_base = "SELECT costo_base FROM productos WHERE id = ?";
            $stmt_precio = mysqli_prepare($conn, $sql_precio_base);
            mysqli_stmt_bind_param($stmt_precio, "i", $rollo['id_producto']);
            mysqli_stmt_execute($stmt_precio);
            $result_precio = mysqli_stmt_get_result($stmt_precio);
            $producto_info = mysqli_fetch_assoc($result_precio);
            $costo_base = $producto_info['costo_base'] ?? 0;
            mysqli_stmt_close($stmt_precio);
            
            // Usar el costo base del producto como precio unitario
            // Los tabuladores se aplican en el cálculo del total, no en el precio unitario
            $precio_unitario = $costo_base;
            
            // Si no hay inventario y no tenemos costo base, usar fallback
            if ($precio_unitario <= 0) {
                $precio_unitario = 1000.00; // Fallback para casos extremos
            }

            $query = "INSERT INTO detalle_cotizacion (
                id_cotizacion, 
                id_producto, 
                id_color,
                cantidad, 
                area_usada,
                precio_unitario,
                opcion_comparativa
            ) VALUES (?, ?, ?, ?, ?, ?, ?)";

            $stmt = mysqli_prepare($conn, $query);
            
            // Determinar opción comparativa: usar el valor del frontend si está disponible
            if (isset($rollo['opcion_comparativa']) && !is_null($rollo['opcion_comparativa'])) {
                // Convertir A/B a 1/2 si es necesario
                if ($rollo['opcion_comparativa'] === 'A') {
                    $opcion_actual = 1;
                } elseif ($rollo['opcion_comparativa'] === 'B') {
                    $opcion_actual = 2;
                } else {
                    $opcion_actual = (int)$rollo['opcion_comparativa'];
                }
            } else {
                // Fallback para compatibilidad: null para simples, usar índice para comparativas
                $opcion_actual = ($datos['es_comparativa'] === 'S' || $datos['es_comparativa'] === '1' || $datos['es_comparativa'] == 1) ? $opcion_comparativa_index : null;
            }
            
            mysqli_stmt_bind_param(
                $stmt,
                "iiidddi",
                $id_cotizacion,
                $rollo['id_producto'],
                $rollo['id_color'],
                $rollo['cantidad'],
                $rollo['cantidad'], // area_usada = cantidad para rollos
                $precio_unitario,
                $opcion_actual
            );
            mysqli_stmt_execute($stmt);
            $id_detalle = mysqli_insert_id($conn);
            mysqli_stmt_close($stmt);

            // Ya no procesamos corte de rollos en cotizaciones
            // Los rollos se cortarán cuando se inicie la instalación
            if ($disponibilidad['area_disponible'] > 0) {
                error_log("Cotización {$id_cotizacion}: Rollo agregado con inventario disponible: {$disponibilidad['area_disponible']} m²");
            } else {
                error_log("Cotización {$id_cotizacion}: Rollo agregado sin inventario - usando precio base");
            }
            
            // Incrementar índice solo para fallback de cotizaciones comparativas SIN opción definida
            if (($datos['es_comparativa'] === 'S' || $datos['es_comparativa'] === '1' || $datos['es_comparativa'] == 1) && 
                (!isset($rollo['opcion_comparativa']) || is_null($rollo['opcion_comparativa']))) {
                $opcion_comparativa_index++;
            }
        }
    }

    // Procesar extras
    if (!empty($datos['extras']) && is_array($datos['extras'])) {
        foreach ($datos['extras'] as $extra) {
            if (empty($extra['id_extra'])) continue;

            // Si se envía cantidad y precio_aplicado (nueva estructura)
            if (isset($extra['cantidad']) && isset($extra['precio_aplicado'])) {
                $cantidad = intval($extra['cantidad']);
                $precio_aplicado = floatval($extra['precio_aplicado']);
            } else {
                // Fallback para compatibilidad con estructura anterior
                $cantidad = 1;
                $precio_aplicado = floatval($extra['precio'] ?? 0);
            }

            $query = "INSERT INTO cotizacion_extras (
                id_cotizacion, 
                id_extra, 
                cantidad,
                precio_aplicado
            ) VALUES (?, ?, ?, ?)";

            $stmt = mysqli_prepare($conn, $query);
            mysqli_stmt_bind_param(
                $stmt,
                "iiid",
                $id_cotizacion,
                $extra['id_extra'],
                $cantidad,
                $precio_aplicado
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
