<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

$ROOT = '../../';
include_once $ROOT . '/db/conexion.php';
include_once $ROOT . '/includes/sesion.php';

if (!tieneSesion()) {
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'message' => 'No autorizado'
    ]);
    exit();
}

try {
    // Parámetros de filtrado opcional
    $filtro_estado = $_GET['estado'] ?? null;
    $filtro_tipo = $_GET['tipo'] ?? null;
    $filtro_cliente = $_GET['cliente'] ?? null;
    $filtro_fecha_desde = $_GET['fecha_desde'] ?? null;
    $filtro_fecha_hasta = $_GET['fecha_hasta'] ?? null;
    
    // Consulta base con JOINS
    $sql = "SELECT 
                c.id,
                c.id_cliente,
                c.id_admin,
                c.fecha_cita,
                c.hora_cita,
                c.tipo_cita,
                c.direccion_cita,
                c.descripcion,
                c.estado,
                c.fecha_creacion,
                c.fecha_actualizacion,
                cli.nombre AS nombre_cliente,
                cli.telefono AS telefono_cliente,
                cli.email AS email_cliente,
                COALESCE(a.nombre, 'Sin asignar') AS nombre_admin,
                COALESCE(a.apellido, '') AS apellido_admin
            FROM citas c
            LEFT JOIN clientes cli ON c.id_cliente = cli.id
            LEFT JOIN admins a ON c.id_admin = a.id
            WHERE c.estado != 'eliminado'";
    
    $params = [];
    $types = '';
    
    // Aplicar filtros
    if ($filtro_estado && $filtro_estado !== 'todos') {
        $sql .= " AND c.estado = ?";
        $params[] = $filtro_estado;
        $types .= 's';
    }
    
    if ($filtro_tipo && $filtro_tipo !== 'todos') {
        $sql .= " AND c.tipo_cita = ?";
        $params[] = $filtro_tipo;
        $types .= 's';
    }
    
    if ($filtro_cliente) {
        $sql .= " AND cli.nombre LIKE ?";
        $params[] = '%' . $filtro_cliente . '%';
        $types .= 's';
    }
    
    if ($filtro_fecha_desde) {
        $sql .= " AND c.fecha_cita >= ?";
        $params[] = $filtro_fecha_desde;
        $types .= 's';
    }
    
    if ($filtro_fecha_hasta) {
        $sql .= " AND c.fecha_cita <= ?";
        $params[] = $filtro_fecha_hasta;
        $types .= 's';
    }
    
    $sql .= " ORDER BY c.fecha_cita DESC, c.hora_cita DESC LIMIT 500";
    
    // Preparar y ejecutar consulta
    if (!empty($params)) {
        $stmt = mysqli_prepare($conn, $sql);
        if ($stmt) {
            mysqli_stmt_bind_param($stmt, $types, ...$params);
            mysqli_stmt_execute($stmt);
            $result = mysqli_stmt_get_result($stmt);
        } else {
            throw new Exception('Error preparando consulta: ' . mysqli_error($conn));
        }
    } else {
        $result = mysqli_query($conn, $sql);
    }
    
    if (!$result) {
        throw new Exception('Error en la consulta: ' . mysqli_error($conn));
    }
    
    $citas = [];
    while ($row = mysqli_fetch_assoc($result)) {
        // Calcular si la cita está próxima (en las próximas 24 horas)
        $fecha_hora_cita = $row['fecha_cita'] . ' ' . $row['hora_cita'];
        $timestamp_cita = strtotime($fecha_hora_cita);
        $timestamp_actual = time();
        $es_proxima = ($timestamp_cita > $timestamp_actual && $timestamp_cita <= ($timestamp_actual + 86400));
        
        $citas[] = [
            'id' => (int)$row['id'],
            'id_cliente' => (int)$row['id_cliente'],
            'id_admin' => (int)$row['id_admin'],
            'fecha_cita' => $row['fecha_cita'],
            'hora_cita' => substr($row['hora_cita'], 0, 5), // HH:MM
            'tipo_cita' => $row['tipo_cita'],
            'direccion_cita' => $row['direccion_cita'],
            'descripcion' => $row['descripcion'],
            'estado' => $row['estado'],
            'fecha_creacion' => $row['fecha_creacion'],
            'fecha_actualizacion' => $row['fecha_actualizacion'],
            'nombre_cliente' => $row['nombre_cliente'],
            'telefono_cliente' => $row['telefono_cliente'],
            'email_cliente' => $row['email_cliente'],
            'nombre_admin' => $row['nombre_admin'],
            'apellido_admin' => $row['apellido_admin'],
            'es_proxima' => $es_proxima
        ];
    }
    
    echo json_encode([
        'success' => true,
        'citas' => $citas,
        'total' => count($citas)
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Error del servidor: ' . $e->getMessage()
    ]);
}

if (isset($stmt)) {
    mysqli_stmt_close($stmt);
}
mysqli_close($conn);
?>