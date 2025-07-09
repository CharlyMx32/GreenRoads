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

// Validar sesión
if (!tieneSesion()) {
    http_response_code(401);
    die(json_encode([
        'status' => 0,
        'mensaje' => 'Sesión no válida'
    ]));
}

try {
    $nombre = trim($_POST['nombre'] ?? '');
    $telefono = trim($_POST['telefono'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $direccion = trim($_POST['direccion'] ?? '');

    if (empty($nombre)) {
        throw new Exception("El nombre es obligatorio.");
    }

    if (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        throw new Exception("El correo electrónico no es válido.");
    }

    $stmt = $conn->prepare("INSERT INTO clientes (nombre, telefono, email, direccion) VALUES (?, ?, ?, ?)");

    if (!$stmt) {
        throw new Exception("Error en prepare: " . $conn->error);
    }

    $stmt->bind_param('ssss', $nombre, $telefono, $email, $direccion);

    if (!$stmt->execute()) {
        throw new Exception("Error en execute: " . $stmt->error);
    }

    echo json_encode([
        'status' => 1,
        'mensaje' => 'Cliente agregado correctamente.',
        'id' => $stmt->insert_id
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'status' => 0,
        'mensaje' => $e->getMessage()
    ]);
}
