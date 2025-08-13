<?php
header('Content-Type: application/json');
require_once '../../db/conexion.php';

$response = ['success' => false, 'message' => ''];

try {
    if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
        throw new Exception('ID de cotización no válido');
    }

    $cotizacionId = intval($_GET['id']);

    // Obtener datos básicos de la cotización
    $sql = "SELECT c.*, cli.nombre AS cliente_nombre, cli.telefono, cli.direccion, 
                   a.nombre AS admin_nombre, a.apellido AS admin_apellido,
                   DATE_ADD(c.fecha, INTERVAL 30 DAY) AS fecha_vencimiento
            FROM cotizaciones c
            LEFT JOIN clientes cli ON c.id_cliente = cli.id
            LEFT JOIN admins a ON c.id_admin = a.id
            WHERE c.id = ?";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('i', $cotizacionId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        throw new Exception('Cotización no encontrada');
    }
    
    $cotizacion = $result->fetch_assoc();
    
    // Obtener detalles del pasto (producto principal)
    $sqlPasto = "SELECT p.nombre AS modelo, m.nombre AS tipo, col.nombre AS color, 
                        dc.precio_unitario, dc.area_usada,
                        c.precio_base_pasto_m2, c.descuento_volumen_porcentaje, 
                        c.precio_instalacion_m2, c.precio_mano_obra_m2
                 FROM detalle_cotizacion dc
                 JOIN productos p ON dc.id_producto = p.id
                 LEFT JOIN modelos m ON p.id_modelo = m.id
                 LEFT JOIN colores col ON dc.id_color = col.id
                 JOIN cotizaciones c ON dc.id_cotizacion = c.id
                 WHERE dc.id_cotizacion = ? AND p.tipo_inventario = 'rollo'
                 LIMIT 1";
    
    $stmtPasto = $conn->prepare($sqlPasto);
    $stmtPasto->bind_param('i', $cotizacionId);
    $stmtPasto->execute();
    $resultPasto = $stmtPasto->get_result();
    
    if ($resultPasto->num_rows > 0) {
        $pastoData = $resultPasto->fetch_assoc();
        
        // Calcular el precio completo por m² según la nueva fórmula:
        // Precio base - descuento por volumen + precio instalación + precio mano de obra
        $precio_base = floatval($pastoData['precio_base_pasto_m2']);
        $descuento_porcentaje = floatval($pastoData['descuento_volumen_porcentaje']);
        $precio_instalacion = floatval($pastoData['precio_instalacion_m2']);
        $precio_mano_obra = floatval($pastoData['precio_mano_obra_m2']);
        
        $precio_con_descuento = $precio_base * (1 - ($descuento_porcentaje / 100));
        $precio_completo_m2 = $precio_con_descuento + $precio_instalacion + $precio_mano_obra;
        
        $pasto = [
            'modelo' => $pastoData['modelo'],
            'tipo' => $pastoData['tipo'] ?: 'Residencial',
            'color' => $pastoData['color'] ?: 'No especificado',
            'precio_unitario' => floatval($pastoData['precio_unitario']),
            'precio_completo_m2' => $precio_completo_m2,
            'precio_base' => $precio_base,
            'descuento_porcentaje' => $descuento_porcentaje,
            'precio_instalacion' => $precio_instalacion,
            'precio_mano_obra' => $precio_mano_obra
        ];
    } else {
        $pasto = [
            'modelo' => 'No especificado',
            'tipo' => 'Residencial',
            'color' => 'No especificado',
            'precio_unitario' => 0,
            'precio_completo_m2' => 0,
            'precio_base' => 0,
            'descuento_porcentaje' => 0,
            'precio_instalacion' => 0,
            'precio_mano_obra' => 0
        ];
    }
    
    // Obtener extras
    $sqlExtras = "SELECT e.nombre, ce.precio_aplicado AS precio
                  FROM cotizacion_extras ce
                  JOIN extras e ON ce.id_extra = e.id
                  WHERE ce.id_cotizacion = ?";
    
    $stmtExtras = $conn->prepare($sqlExtras);
    $stmtExtras->bind_param('i', $cotizacionId);
    $stmtExtras->execute();
    $resultExtras = $stmtExtras->get_result();
    $extrasRaw = $resultExtras->fetch_all(MYSQLI_ASSOC);
    
    // Convertir precios a float
    $extras = array_map(function($extra) {
        return [
            'nombre' => $extra['nombre'],
            'precio' => floatval($extra['precio'])
        ];
    }, $extrasRaw);
    
    // Calcular totales basándose en si la cotización tiene IVA aplicado
    $total = floatval($cotizacion['total']);
    $iva_aplicado = !is_null($cotizacion['iva']) && floatval($cotizacion['iva']) > 0;
    
    if ($iva_aplicado) {
        // Si tiene IVA, usar los valores directos de la base de datos
        $iva = floatval($cotizacion['iva']);
        $subtotal = $total - $iva;
    } else {
        // Si no tiene IVA, el total es el subtotal
        $subtotal = $total;
        $iva = 0;
    }
    
    $response = [
        'success' => true,
        'cotizacion' => [
            'id' => $cotizacion['id'],
            'fecha' => $cotizacion['fecha'],
            'fecha_vencimiento' => $cotizacion['fecha_vencimiento'],
            'total' => floatval($cotizacion['total']),
            'area_total' => floatval($cotizacion['area_total']),
            'tipo_instalacion' => $cotizacion['tipo_instalacion'],
            'garantia_anios' => intval($cotizacion['garantia_anios'])
        ],
        'cliente' => [
            'nombre' => $cotizacion['cliente_nombre'],
            'telefono' => $cotizacion['telefono'],
            'direccion' => $cotizacion['direccion']
        ],
        'admin' => [
            'nombre' => $cotizacion['admin_nombre'] . ' ' . $cotizacion['admin_apellido']
        ],
        'pasto' => $pasto,
        'extras' => $extras,
        'totales' => [
            'subtotal' => $subtotal,
            'iva' => $iva,
            'total' => $total,
            'iva_aplicado' => $iva_aplicado
        ]
    ];
} catch (Exception $e) {
    $response['message'] = $e->getMessage();
} finally {
    if (isset($stmt)) $stmt->close();
    if (isset($stmtPasto)) $stmtPasto->close();
    if (isset($stmtExtras)) $stmtExtras->close();
    $conn->close();
}

echo json_encode($response);
?>