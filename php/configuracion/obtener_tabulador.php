<?php
require_once '../../db/conexion.php';
header('Content-Type: application/json');

try {
    $id = isset($_GET['id']) ? intval($_GET['id']) : null;
    $tipo = $_GET['tipo'] ?? null;

    $tiposPermitidos = ['precio_instalacion', 'clavos', 'pegamento', 'margen_utilidad', 'polvillo'];

    // Buscar por ID
    if ($id) {
        $sql = "SELECT * FROM tabuladores WHERE id = ?";
        $stmt = $conn->prepare($sql);
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

    // Validar tipo si se usa
    if ($tipo && !in_array($tipo, $tiposPermitidos)) {
        echo json_encode(['status' => 0, 'mensaje' => 'Tipo de tabulador no válido']);
        exit;
    }

    $sql = "SELECT * FROM tabuladores";
    if ($tipo) {
        $sql .= " WHERE tipo = ? ORDER BY rango_min ASC";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param('s', $tipo);
    } else {
        $sql .= " ORDER BY rango_min ASC";
        $stmt = $conn->prepare($sql);
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
}