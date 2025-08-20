<?php
header('Content-Type: application/json');
require_once '../../db/conexion.php';

/**
 * Calcula los materiales necesarios según el tipo de terreno y área
 */
function calcularMaterialesNecesarios($tipoTerreno, $area) {
    global $conn;
    
    $materialesNecesarios = [];
    
    // Mapeo de tipo de terreno a tipos de materiales necesarios
    $materialesPorTerreno = [
        'tierra' => ['clavos'],
        'concreto' => ['adhesivo'], // pegamento
        'mixto' => ['clavos', 'adhesivo', 'arena sílica'] 
    ];
    
    // Mapeo de nombres a IDs de tipo_producto
    $tiposProducto = [
        'clavos' => 5,
        'adhesivo' => 4, // pegamento
        'arena sílica' => 6 // polvillo
    ];
    
    if (!isset($materialesPorTerreno[$tipoTerreno])) {
        throw new Exception("Tipo de terreno no válido: $tipoTerreno");
    }
    
    foreach ($materialesPorTerreno[$tipoTerreno] as $tipoMaterial) {
        if (!isset($tiposProducto[$tipoMaterial])) {
            continue;
        }
        
        $idTipoProducto = $tiposProducto[$tipoMaterial];
        
        // Obtener cantidad necesaria del tabulador
        $cantidad = obtenerCantidadTabulador($tipoMaterial, $area, $tipoTerreno);
        
        if ($cantidad > 0) {
            // Obtener productos disponibles de este tipo
            $productosDisponibles = obtenerProductosPorTipo($idTipoProducto);
            
            $materialesNecesarios[] = [
                'tipo' => $tipoMaterial,
                'id_tipo_producto' => $idTipoProducto,
                'cantidad_necesaria' => $cantidad,
                'productos_disponibles' => $productosDisponibles,
                'inventario_suficiente' => verificarInventarioSuficiente($productosDisponibles, $cantidad)
            ];
        }
    }
    
    return $materialesNecesarios;
}

/**
 * Obtiene la cantidad necesaria desde el tabulador
 */
function obtenerCantidadTabulador($tipoMaterial, $area, $tipoSuperficie = null) {
    global $conn;
    
    // Mapear tipos de material a tipos de tabulador según superficie
    $mapaTabuladores = [
        'clavos' => [
            'tierra' => 'clavos',
            'mixto' => 'clavos'
        ],
        'adhesivo' => [
            'concreto' => 'pegamento',
            'mixto' => 'pegamento'
        ],
        'arena sílica' => [
            'mixto' => 'polvillo'
        ]
    ];
    
    // Determinar el tipo de tabulador a usar
    $tipoTabulador = null;
    if (isset($mapaTabuladores[$tipoMaterial])) {
        if ($tipoSuperficie && isset($mapaTabuladores[$tipoMaterial][$tipoSuperficie])) {
            $tipoTabulador = $mapaTabuladores[$tipoMaterial][$tipoSuperficie];
        } else {
            // Tomar el primer disponible
            $tipoTabulador = array_values($mapaTabuladores[$tipoMaterial])[0];
        }
    }
    
    if (!$tipoTabulador) {
        return 0;
    }
    
    $sql = "SELECT valor FROM tabuladores 
            WHERE tipo = ? AND activo = 1 
            AND ? BETWEEN rango_min AND rango_max 
            LIMIT 1";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('sd', $tipoTabulador, $area);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($row = $result->fetch_assoc()) {
        return floatval($row['valor']);
    }
    
    return 0;
}

/**
 * Obtiene productos disponibles por tipo
 */
function obtenerProductosPorTipo($idTipoProducto) {
    global $conn;
    
    // Obtener productos SIEMPRE, incluso sin inventario
    $sql = "SELECT 
                p.id, 
                p.nombre,
                p.costo_base,
                u.simbolo AS unidad,
                COALESCE(
                    (SUM(CASE WHEN mi.tipo_movimiento = 'entrada' THEN mi.cantidad ELSE 0 END) -
                     SUM(CASE WHEN mi.tipo_movimiento = 'salida' THEN mi.cantidad ELSE 0 END)), 
                    0
                ) AS stock_disponible
            FROM productos p
            JOIN unidades u ON p.id_unidad = u.id
            LEFT JOIN movimientos_inventario mi ON mi.id_producto = p.id
            WHERE p.id_tipo_producto = ? AND p.estado = 'activo'
            GROUP BY p.id, p.nombre, p.costo_base, u.simbolo
            ORDER BY stock_disponible DESC, p.costo_base ASC";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('i', $idTipoProducto);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $productos = [];
    while ($row = $result->fetch_assoc()) {
        $productos[] = [
            'id' => $row['id'],
            'nombre' => $row['nombre'],
            'costo_base' => floatval($row['costo_base']),
            'unidad' => $row['unidad'],
            'stock_disponible' => floatval($row['stock_disponible'])
        ];
    }
    
    return $productos;
}

function verificarInventarioSuficiente($productos, $cantidadNecesaria) {
    $stockTotal = 0;
    foreach ($productos as $producto) {
        $stockTotal += $producto['stock_disponible'];
    }
    
    return $stockTotal >= $cantidadNecesaria;
}

/**
 * Calcula el costo de los materiales y asigna productos
 */
function calcularCostoMateriales($materialesNecesarios) {
    $resultado = [
        'materiales' => [],
        'costo_total' => 0,
        'productos_asignados' => [],
        'faltantes' => []
    ];
    
    foreach ($materialesNecesarios as $material) {
        $cantidadPendiente = $material['cantidad_necesaria'];
        $costoMaterial = 0;
        $productosUsados = [];
        
        // Asignar productos en orden de disponibilidad y precio
        foreach ($material['productos_disponibles'] as $producto) {
            if ($cantidadPendiente <= 0) break;
            
            $cantidadUsar = min($cantidadPendiente, $producto['stock_disponible']);
            
            if ($cantidadUsar > 0) {
                $costoProducto = $cantidadUsar * $producto['costo_base'];
                $costoMaterial += $costoProducto;
                
                $productosUsados[] = [
                    'id_producto' => $producto['id'],
                    'nombre' => $producto['nombre'],
                    'cantidad_usada' => $cantidadUsar,
                    'costo_unitario' => $producto['costo_base'],
                    'costo_total' => $costoProducto,
                    'unidad' => $producto['unidad']
                ];
                
                $cantidadPendiente -= $cantidadUsar;
            }
        }
        
        if ($cantidadPendiente > 0 && !empty($material['productos_disponibles'])) {
            $productoMasBarato = $material['productos_disponibles'][0];
            foreach ($material['productos_disponibles'] as $producto) {
                if ($producto['costo_base'] < $productoMasBarato['costo_base']) {
                    $productoMasBarato = $producto;
                }
            }
            
            $costoFaltante = $cantidadPendiente * $productoMasBarato['costo_base'];
            $costoMaterial += $costoFaltante;
            
            $productosUsados[] = [
                'id_producto' => $productoMasBarato['id'],
                'nombre' => $productoMasBarato['nombre'] . ' (SIN STOCK)',
                'cantidad_usada' => $cantidadPendiente,
                'costo_unitario' => $productoMasBarato['costo_base'],
                'costo_total' => $costoFaltante,
                'unidad' => $productoMasBarato['unidad'],
                'sin_stock' => true
            ];
            
            $resultado['faltantes'][] = [
                'tipo' => $material['tipo'],
                'producto' => $productoMasBarato['nombre'],
                'cantidad_faltante' => $cantidadPendiente,
                'unidad' => $productoMasBarato['unidad']
            ];
        }
        
        $resultado['materiales'][] = [
            'tipo' => $material['tipo'],
            'cantidad_necesaria' => $material['cantidad_necesaria'],
            'costo_total' => $costoMaterial,
            'productos_usados' => $productosUsados
        ];
        
        $resultado['costo_total'] += $costoMaterial;
        $resultado['productos_asignados'] = array_merge($resultado['productos_asignados'], $productosUsados);
    }
    
    return $resultado;
}

try {
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input) {
        throw new Exception("Datos inválidos");
    }
    
    // Aceptar tanto tipo_terreno como tipo_superficie para compatibilidad
    $tipoTerreno = $input['tipo_instalacion'] ?? $input['tipo_superficie'] ?? '';
    $area = floatval($input['area'] ?? 0);
    
    if (empty($tipoTerreno) || $area <= 0) {
        throw new Exception("Tipo de terreno/superficie y área son requeridos");
    }
    
    // Calcular materiales necesarios
    $materialesNecesarios = calcularMaterialesNecesarios($tipoTerreno, $area);
    
    // Calcular costos y asignación
    $resultado = calcularCostoMateriales($materialesNecesarios);
    
    $materialesFormateados = [];
    foreach ($resultado['productos_asignados'] as $producto) {
        $materialesFormateados[] = [
            'id_producto' => $producto['id_producto'],
            'nombre' => $producto['nombre'],
            'cantidad' => $producto['cantidad_usada'],
            'precio_unitario' => $producto['costo_unitario'],
            'subtotal' => $producto['costo_total']
        ];
    }
    
    echo json_encode([
        'success' => true,
        'tipo_superficie' => $tipoTerreno,
        'area' => $area,
        'materiales' => $materialesFormateados,
        'costo_total' => $resultado['costo_total'],
        'faltantes' => $resultado['faltantes'],
        'resultado_completo' => $resultado
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>
