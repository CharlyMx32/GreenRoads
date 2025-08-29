<?php
ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
error_reporting(E_ALL);

if (ob_get_level()) {
    ob_clean();
}

header('Content-Type: application/json');

try {
    require_once '../../db/conexion.php';
    require_once '../../includes/sesion.php';
    require_once '../../includes/funciones_corte_rollos.php';
    require_once '../../includes/funciones_tabuladores.php';
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Error al cargar archivos: ' . $e->getMessage()]);
    exit();
}

if (!tieneSesion()) {
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Método no permitido']);
    exit();
}

$input = json_decode(file_get_contents('php://input'), true);

$id_instalacion = isset($input['id_instalacion']) ? (int)$input['id_instalacion'] : 0;
$id_cotizacion = isset($input['id_cotizacion']) ? (int)$input['id_cotizacion'] : 0;
$rollos_seleccionados = isset($input['rollos_seleccionados']) ? $input['rollos_seleccionados'] : [];

if ($id_instalacion <= 0 || $id_cotizacion <= 0 || empty($rollos_seleccionados)) {
    echo json_encode(['success' => false, 'message' => 'Datos incompletos']);
    exit();
}

mysqli_begin_transaction($conn);

try {
    // Verificar que la instalación esté en estado planificada
    $query_check = "SELECT estado FROM instalaciones WHERE id = ? AND id_cotizacion = ?";
    $stmt_check = mysqli_prepare($conn, $query_check);
    mysqli_stmt_bind_param($stmt_check, "ii", $id_instalacion, $id_cotizacion);
    mysqli_stmt_execute($stmt_check);
    $result_check = mysqli_stmt_get_result($stmt_check);
    $instalacion = mysqli_fetch_assoc($result_check);
    mysqli_stmt_close($stmt_check);
    
    if (!$instalacion || $instalacion['estado'] !== 'planificada') {
        throw new Exception('La instalación no está disponible para iniciar');
    }

    // Obtener el área total de la cotización
    $stmt_area = $conn->prepare("SELECT area_total FROM cotizaciones WHERE id = ?");
    $stmt_area->bind_param("i", $id_cotizacion);
    $stmt_area->execute();
    $resultado_area = $stmt_area->get_result()->fetch_assoc();
    $stmt_area->close();
    if (!$resultado_area) {
        throw new Exception('No se encontró el área de la cotización.');
    }
    $area_total = (float)$resultado_area['area_total'];
    
    // Procesar cada rollo seleccionado
    $procesados = [];
    $errores = [];
    
    foreach ($rollos_seleccionados as $seleccion) {
        $id_producto = (int)$seleccion['id_producto'];
        $id_color = (int)$seleccion['id_color'];
        $area_necesaria = (float)$seleccion['area_necesaria'];
        $rollos_usar = $seleccion['rollos'];
        
        if (empty($rollos_usar)) {
            $errores[] = "No se seleccionaron rollos para el producto ID {$id_producto}";
            continue;
        }
        
        $area_total_seleccionada = 0;
        $rollos_procesados = [];
        
        foreach ($rollos_usar as $rollo_config) {
            $id_rollo = (int)$rollo_config['id_rollo'];
            $metros_usar = (float)$rollo_config['metros_usar'];
            $usar_completo = isset($rollo_config['usar_completo']) ? (bool)$rollo_config['usar_completo'] : false;
            
            // Obtener información del rollo
            $query_rollo = "
                SELECT id, largo_metros, ancho_metros, area_m2, estado, costo_unitario
                FROM inventario_rollos 
                WHERE id = ? AND estado = 'disponible'
            ";
            $stmt_rollo = mysqli_prepare($conn, $query_rollo);
            mysqli_stmt_bind_param($stmt_rollo, "i", $id_rollo);
            mysqli_stmt_execute($stmt_rollo);
            $result_rollo = mysqli_stmt_get_result($stmt_rollo);
            $rollo = mysqli_fetch_assoc($result_rollo);
            mysqli_stmt_close($stmt_rollo);
            
            if (!$rollo) {
                $errores[] = "Rollo ID {$id_rollo} no disponible";
                continue;
            }
            
            if ($usar_completo || $metros_usar >= $rollo['largo_metros']) {
                // Usar el rollo completo
                $query_usar = "
                    UPDATE inventario_rollos 
                    SET estado = 'instalado', id_cotizacion_reserva = ? 
                    WHERE id = ?
                ";
                $stmt_usar = mysqli_prepare($conn, $query_usar);
                mysqli_stmt_bind_param($stmt_usar, "ii", $id_cotizacion, $id_rollo);
                mysqli_stmt_execute($stmt_usar);
                mysqli_stmt_close($stmt_usar);
                
                $area_usada = $rollo['area_m2'];
                $rollos_procesados[] = [
                    'id_rollo' => $id_rollo,
                    'tipo' => 'completo',
                    'area_usada' => $area_usada,
                    'metros_usados' => $rollo['largo_metros']
                ];
                
            } else {
                // Cortar el rollo
                $resultado_corte = cortarRollo($conn, $rollo, $metros_usar, $id_cotizacion);
                
                if ($resultado_corte['exito']) {
                    // Marcar el rollo cortado como instalado
                    $query_instalar = "
                        UPDATE inventario_rollos 
                        SET estado = 'instalado' 
                        WHERE id = ?
                    ";
                    $stmt_instalar = mysqli_prepare($conn, $query_instalar);
                    mysqli_stmt_bind_param($stmt_instalar, "i", $resultado_corte['id_rollo_cortado']);
                    mysqli_stmt_execute($stmt_instalar);
                    mysqli_stmt_close($stmt_instalar);
                    
                    $area_usada = $metros_usar * $rollo['ancho_metros'];
                    $rollos_procesados[] = [
                        'id_rollo_original' => $id_rollo,
                        'id_rollo_cortado' => $resultado_corte['id_rollo_cortado'],
                        'tipo' => 'cortado',
                        'area_usada' => $area_usada,
                        'metros_usados' => $metros_usar,
                        'metros_sobrantes' => $resultado_corte['metros_sobrantes']
                    ];
                } else {
                    $errores[] = "Error al cortar rollo ID {$id_rollo}: " . $resultado_corte['mensaje'];
                    continue;
                }
            }
            
            $area_total_seleccionada += $area_usada;
        }
        
        $procesados[] = [
            'id_producto' => $id_producto,
            'id_color' => $id_color,
            'area_necesaria' => $area_necesaria,
            'area_seleccionada' => $area_total_seleccionada,
            'rollos_procesados' => $rollos_procesados,
            'cumple_requerimiento' => $area_total_seleccionada >= $area_necesaria
        ];
    }
    
    // Verificar que no haya errores críticos
    if (!empty($errores)) {
        throw new Exception('Errores al procesar rollos: ' . implode(', ', $errores));
    }
    
    // Calcular días y fecha final estimada
    $dias_instalacion = obtenerDiasInstalacionPorMetros($conn, $area_total);
    
    $query_actualizar = "
        UPDATE instalaciones 
        SET estado = 'en_progreso', 
            fecha_inicio = NOW(),
            fecha_final = DATE_ADD(NOW(), INTERVAL ? DAY),
            observaciones = CONCAT(COALESCE(observaciones, ''), 
                                 'Iniciada automáticamente. Fecha final estimada calculada.')
        WHERE id = ?
    ";
    $stmt_actualizar = mysqli_prepare($conn, $query_actualizar);
    mysqli_stmt_bind_param($stmt_actualizar, "ii", $dias_instalacion, $id_instalacion);
    mysqli_stmt_execute($stmt_actualizar);
    mysqli_stmt_close($stmt_actualizar);
    
    mysqli_commit($conn);
    
    echo json_encode([
        'success' => true,
        'message' => 'Instalación iniciada correctamente',
        'procesados' => $procesados
    ]);
    
} catch (Exception $e) {
    mysqli_rollback($conn);
    error_log("Error al iniciar instalación: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Error al iniciar instalación: ' . $e->getMessage()
    ]);
}
?>
