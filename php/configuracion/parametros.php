<?php
ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
error_reporting(0);

$ROOT = '../..';
include_once $ROOT . '/db/conexion.php';
include_once $ROOT . '/includes/sesion.php';

header('Content-Type: application/json');

// Validar conexión DB
if (!$conn) {
    http_response_code(500);
    die(json_encode([
        'status' => 0,
        'mensaje' => 'Error de conexión a la base de datos'
    ]));
}

// Solo POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    die(json_encode([
        'status' => 0,
        'mensaje' => 'Método no permitido'
    ]));
}

// Validar sesión y permisos (descomentar cuando esté listo)
// if (!tieneSesion() || !tienePermiso('admin_parametros')) {
//     http_response_code(403);
//     die(json_encode([
//         'status' => 0,
//         'mensaje' => 'No tiene permisos para esta acción'
//     ]));
// }

try {
    // Validar datos recibidos
    $clave = trim($_POST['clave'] ?? '');
    $valor = trim($_POST['valor'] ?? '');

    if (empty($clave)) {
        throw new Exception("La clave del parámetro es requerida.");
    }

    // Obtener información del parámetro
    $stmt = $conn->prepare("SELECT tipo, editable FROM parametros_sistema WHERE clave = ?");
    $stmt->bind_param('s', $clave);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        throw new Exception("Parámetro no encontrado.");
    }

    $parametro = $result->fetch_assoc();

    // Verificar si es editable
    if (!$parametro['editable']) {
        throw new Exception("Este parámetro no es editable.");
    }

    // Validar según el tipo
    switch ($parametro['tipo']) {
        case 'entero':
            if (!is_numeric($valor) || strpos($valor, '.') !== false) {
                throw new Exception("El valor debe ser un número entero.");
            }
            $valor = (int)$valor;
            break;
            
        case 'decimal':
            if (!is_numeric($valor)) {
                throw new Exception("El valor debe ser un número decimal.");
            }
            $valor = (float)$valor;
            break;
            
        case 'boolean':
            $valor = in_array(strtolower($valor), ['1', 'true', 'on', 'yes']) ? '1' : '0';
            break;
            
        default:
            $valor = htmlspecialchars($valor);
    }

    // Actualizar parámetro
    $stmt = $conn->prepare("UPDATE parametros_sistema 
                           SET valor = ?, fecha_actualizacion = NOW() 
                           WHERE clave = ?");
    $stmt->bind_param('ss', $valor, $clave);

    if (!$stmt->execute()) {
        throw new Exception("Error al actualizar el parámetro: " . $stmt->error);
    }

    // Registrar en bitácora (opcional)
    if (isset($_SESSION['usuario_id'])) {
        $accion = "Actualización de parámetro: $clave";
        $conn->query("INSERT INTO bitacora (id_admin, accion, fecha) 
                      VALUES ('{$_SESSION['usuario_id']}', '$accion', NOW())");
    }

    echo json_encode([
        'status' => 1,
        'mensaje' => 'Parámetro actualizado correctamente.',
        'valor_actualizado' => $valor
    ]);

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'status' => 0,
        'mensaje' => $e->getMessage()
    ]);
}