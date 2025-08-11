<?php
ob_start();

ini_set('display_errors', 0);
error_reporting(0);

try {
    require_once '../../db/conexion.php';
    
    ob_clean();
    
    header('Content-Type: application/json; charset=utf-8');
    
    $id = isset($_GET['id']) ? intval($_GET['id']) : null;
    $tipo = $_GET['tipo'] ?? null;

    $tiposPermitidos = ['precio_instalacion', 'clavos', 'pegamento', 'descuento_volumen', 'polvillo', 'mano_obra'];

    // Buscar por ID
    if ($id) {
        $sql = "SELECT * FROM tabuladores WHERE id = ?";
        $stmt = $conn->prepare($sql);
        
        if (!$stmt) {
            throw new Exception('Error en la consulta: ' . $conn->error);
        }
        
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $result = $stmt->get_result();
        $tabulador = $result->fetch_assoc();

        if ($tabulador) {
            echo json_encode([
                'status' => 1,
                'tabuladores' => [$tabulador]
            ]);
        } else {
            echo json_encode([
                'status' => 0,
                'mensaje' => 'Tabulador no encontrado'
            ]);
        }
        exit;
    }

    if ($tipo && !in_array($tipo, $tiposPermitidos)) {
        echo json_encode(['status' => 0, 'mensaje' => 'Tipo de tabulador no válido']);
        exit;
    }

    $sql = "SELECT * FROM tabuladores";
    if ($tipo) {
        $sql .= " WHERE tipo = ? ORDER BY rango_min ASC";
        $stmt = $conn->prepare($sql);
        
        if (!$stmt) {
            throw new Exception('Error en la consulta: ' . $conn->error);
        }
        
        $stmt->bind_param('s', $tipo);
    } else {
        $sql .= " ORDER BY rango_min ASC";
        $stmt = $conn->prepare($sql);
        
        if (!$stmt) {
            throw new Exception('Error en la consulta: ' . $conn->error);
        }
    }

    $stmt->execute();
    $result = $stmt->get_result();
    $tabuladores = $result->fetch_all(MYSQLI_ASSOC);

    echo json_encode([
        'status' => 1,
        'tabuladores' => $tabuladores
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'status' => 0,
        'mensaje' => 'Error al obtener tabulador: ' . $e->getMessage()
    ]);
} finally {
    ob_end_flush();
}