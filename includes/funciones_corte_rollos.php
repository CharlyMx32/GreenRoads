<?php
/**
 * Funciones para manejo de corte y liberación de rollos
 * Sistema que permite cortar rollos cuando se necesita menos área de la disponible
 */
function liberarRollosCortados($conn, $id_cotizacion) {
    try {
        // 1. Obtener todos los rollos reservados para esta cotización
        $query_reservados = "
            SELECT id, id_rollo_padre, tipo_rollo, largo_metros, ancho_metros, 
                   id_producto, id_color, id_lote, costo_unitario
            FROM inventario_rollos 
            WHERE id_cotizacion_reserva = ? AND estado = 'reservado'
        ";
        
        $stmt = mysqli_prepare($conn, $query_reservados);
        mysqli_stmt_bind_param($stmt, "i", $id_cotizacion);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        
        $rollos_liberados = [];
        
        while ($rollo = mysqli_fetch_assoc($result)) {
            if ($rollo['tipo_rollo'] == 'cortado' && !empty($rollo['id_rollo_padre'])) {
                // Es un rollo cortado, intentar reunificar
                if (reunificarRolloCortado($conn, $rollo)) {
                    $rollos_liberados[] = $rollo['id'];
                }
            } else {
                // Es un rollo original, simplemente liberar
                $query_liberar = "UPDATE inventario_rollos 
                                 SET estado = 'disponible', id_cotizacion_reserva = NULL 
                                 WHERE id = ?";
                $stmt_lib = mysqli_prepare($conn, $query_liberar);
                mysqli_stmt_bind_param($stmt_lib, "i", $rollo['id']);
                mysqli_stmt_execute($stmt_lib);
                mysqli_stmt_close($stmt_lib);
                
                $rollos_liberados[] = $rollo['id'];
            }
        }
        
        mysqli_stmt_close($stmt);
        
        error_log("Cotización {$id_cotizacion}: Liberados " . count($rollos_liberados) . " rollos");
        return true;
        
    } catch (Exception $e) {
        error_log("Error al liberar rollos cortados: " . $e->getMessage());
        return false;
    }
}

function reunificarRolloCortado($conn, $rollo_cortado) {
    try {
        // Verificar si el rollo padre existe y está disponible
        $query_padre = "
            SELECT id, largo_metros, ancho_metros, estado
            FROM inventario_rollos 
            WHERE id = ? AND estado = 'disponible'
        ";
        
        $stmt = mysqli_prepare($conn, $query_padre);
        mysqli_stmt_bind_param($stmt, "i", $rollo_cortado['id_rollo_padre']);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $rollo_padre = mysqli_fetch_assoc($result);
        mysqli_stmt_close($stmt);
        
        if ($rollo_padre) {
            // El rollo padre existe, reunificar sumando las longitudes
            $nuevo_largo = $rollo_padre['largo_metros'] + $rollo_cortado['largo_metros'];
            
            $query_actualizar = "
                UPDATE inventario_rollos 
                SET largo_metros = ?
                WHERE id = ?
            ";
            
            $stmt_act = mysqli_prepare($conn, $query_actualizar);
            mysqli_stmt_bind_param($stmt_act, "di", $nuevo_largo, $rollo_padre['id']);
            mysqli_stmt_execute($stmt_act);
            mysqli_stmt_close($stmt_act);
            
            // Eliminar el rollo cortado
            $query_eliminar = "UPDATE inventario_rollos SET estado = 'eliminado' WHERE id = ?";
            $stmt_elim = mysqli_prepare($conn, $query_eliminar);
            mysqli_stmt_bind_param($stmt_elim, "i", $rollo_cortado['id']);
            mysqli_stmt_execute($stmt_elim);
            mysqli_stmt_close($stmt_elim);
            
            error_log("Rollo reunificado: ID padre {$rollo_padre['id']} ahora tiene {$nuevo_largo}m");
            return true;
            
        } else {
            // El rollo padre no existe o no está disponible, solo liberar el cortado
            $query_liberar = "
                UPDATE inventario_rollos 
                SET estado = 'disponible', id_cotizacion_reserva = NULL, 
                    tipo_rollo = 'original', id_rollo_padre = NULL
                WHERE id = ?
            ";
            
            $stmt_lib = mysqli_prepare($conn, $query_liberar);
            mysqli_stmt_bind_param($stmt_lib, "i", $rollo_cortado['id']);
            mysqli_stmt_execute($stmt_lib);
            mysqli_stmt_close($stmt_lib);
            
            return true;
        }
        
    } catch (Exception $e) {
        error_log("Error al reunificar rollo cortado: " . $e->getMessage());
        return false;
    }
}

function procesarReservaConCorte($conn, $id_cotizacion, $id_producto, $id_color, $area_necesaria) {
    try {
        $area_restante = $area_necesaria;
        $rollos_reservados = [];
        
        // Buscar rollos disponibles del producto y color
        $query_rollos = "
            SELECT id, largo_metros, ancho_metros, area_m2, costo_unitario
            FROM inventario_rollos 
            WHERE id_producto = ? AND id_color = ? AND estado = 'disponible'
            ORDER BY area_m2 ASC
        ";
        
        $stmt = mysqli_prepare($conn, $query_rollos);
        mysqli_stmt_bind_param($stmt, "ii", $id_producto, $id_color);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        
        while (($rollo = mysqli_fetch_assoc($result)) && $area_restante > 0) {
            $area_rollo = $rollo['area_m2'];
            
            if ($area_rollo <= $area_restante) {
                // Usar todo el rollo
                reservarRollo($conn, $rollo['id'], $id_cotizacion);
                $area_restante -= $area_rollo;
                $rollos_reservados[] = $rollo['id'];
                
            } else {
                // Necesitamos cortar el rollo
                $metros_necesarios = $area_restante / $rollo['ancho_metros'];
                
                $resultado_corte = cortarRollo($conn, $rollo, $metros_necesarios, $id_cotizacion);
                
                if ($resultado_corte['exito']) {
                    $rollos_reservados[] = $resultado_corte['id_rollo_cortado'];
                    $area_restante = 0; // Ya tenemos todo lo que necesitamos
                }
            }
        }
        
        mysqli_stmt_close($stmt);
        
        return [
            'exito' => $area_restante <= 0,
            'area_faltante' => $area_restante,
            'rollos_reservados' => $rollos_reservados,
            'mensaje' => $area_restante > 0 ? "Faltan {$area_restante} m²" : "Reserva completada"
        ];
        
    } catch (Exception $e) {
        error_log("Error al procesar reserva con corte: " . $e->getMessage());
        return [
            'exito' => false,
            'area_faltante' => $area_necesaria,
            'rollos_reservados' => [],
            'mensaje' => $e->getMessage()
        ];
    }
}

/**
 * Corta un rollo en dos partes: la necesaria y el sobrante
 * @param mysqli $conn Conexión a la base de datos
 * @param array $rollo_original Datos del rollo original
 * @param float $metros_necesarios Metros de largo que se necesitan
 * @param int $id_cotizacion ID de la cotización
 * @return array Resultado del corte
 */
function cortarRollo($conn, $rollo_original, $metros_necesarios, $id_cotizacion) {
    try {
        $metros_sobrantes = $rollo_original['largo_metros'] - $metros_necesarios;
        
        // 1. Crear el rollo cortado (el que se va a usar)
        $costo_proporcional = ($rollo_original['costo_unitario'] / $rollo_original['largo_metros']) * $metros_necesarios;
        
        $query_cortado = "
            INSERT INTO inventario_rollos 
            (id_producto, id_lote, id_color, largo_metros, ancho_metros, 
             costo_unitario, costo_total, estado, id_cotizacion_reserva, 
             id_rollo_padre, tipo_rollo)
            SELECT id_producto, id_lote, id_color, ?, ?, 
                   ?, ?, 'reservado', ?, 
                   ?, 'cortado'
            FROM inventario_rollos WHERE id = ?
        ";
        
        $stmt_cortado = mysqli_prepare($conn, $query_cortado);
        mysqli_stmt_bind_param($stmt_cortado, "ddddiii", 
            $metros_necesarios, 
            $rollo_original['ancho_metros'], 
            $costo_proporcional,
            $costo_proporcional,
            $id_cotizacion,
            $rollo_original['id'],
            $rollo_original['id']
        );
        
        mysqli_stmt_execute($stmt_cortado);
        $id_rollo_cortado = mysqli_insert_id($conn);
        mysqli_stmt_close($stmt_cortado);
        
        // 2. Actualizar el rollo original con las dimensiones del sobrante
        if ($metros_sobrantes > 0.1) { // Solo si quedan más de 10cm
            $costo_sobrante = ($rollo_original['costo_unitario'] / $rollo_original['largo_metros']) * $metros_sobrantes;
            
            $query_actualizar = "
                UPDATE inventario_rollos 
                SET largo_metros = ?, costo_unitario = ?, costo_total = ?
                WHERE id = ?
            ";
            
            $stmt_act = mysqli_prepare($conn, $query_actualizar);
            mysqli_stmt_bind_param($stmt_act, "dddi", 
                $metros_sobrantes, 
                $costo_sobrante, 
                $costo_sobrante, 
                $rollo_original['id']
            );
            mysqli_stmt_execute($stmt_act);
            mysqli_stmt_close($stmt_act);
            
        } else {
            // El sobrante es muy pequeño, marcarlo como usado
            $query_usar = "UPDATE inventario_rollos SET estado = 'instalado' WHERE id = ?";
            $stmt_usar = mysqli_prepare($conn, $query_usar);
            mysqli_stmt_bind_param($stmt_usar, "i", $rollo_original['id']);
            mysqli_stmt_execute($stmt_usar);
            mysqli_stmt_close($stmt_usar);
        }
        
        error_log("Rollo cortado: Original ID {$rollo_original['id']} ({$metros_sobrantes}m restantes), Cortado ID {$id_rollo_cortado} ({$metros_necesarios}m)");
        
        return [
            'exito' => true,
            'id_rollo_cortado' => $id_rollo_cortado,
            'metros_usados' => $metros_necesarios,
            'metros_sobrantes' => $metros_sobrantes
        ];
        
    } catch (Exception $e) {
        error_log("Error al cortar rollo: " . $e->getMessage());
        return [
            'exito' => false,
            'mensaje' => $e->getMessage()
        ];
    }
}

function reservarRollo($conn, $id_rollo, $id_cotizacion) {
    try {
        $query = "UPDATE inventario_rollos 
                 SET estado = 'reservado', id_cotizacion_reserva = ? 
                 WHERE id = ?";
        
        $stmt = mysqli_prepare($conn, $query);
        mysqli_stmt_bind_param($stmt, "ii", $id_cotizacion, $id_rollo);
        $resultado = mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
        
        return $resultado;
        
    } catch (Exception $e) {
        error_log("Error al reservar rollo: " . $e->getMessage());
        return false;
    }
}

function obtenerRollosDisponiblesParaCorte($conn, $id_producto, $id_color) {
    try {
        $query = "
            SELECT id, largo_metros, ancho_metros, area_m2, 
                   costo_unitario, tipo_rollo
            FROM inventario_rollos 
            WHERE id_producto = ? AND id_color = ? AND estado = 'disponible'
            ORDER BY area_m2 ASC
        ";
        
        $stmt = mysqli_prepare($conn, $query);
        mysqli_stmt_bind_param($stmt, "ii", $id_producto, $id_color);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        
        $rollos = [];
        while ($row = mysqli_fetch_assoc($result)) {
            $rollos[] = $row;
        }
        
        mysqli_stmt_close($stmt);
        return $rollos;
        
    } catch (Exception $e) {
        error_log("Error al obtener rollos disponibles: " . $e->getMessage());
        return [];
    }
}
?>
