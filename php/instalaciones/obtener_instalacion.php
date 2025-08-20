<?php
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

header('Content-Type: application/json');

$ROOT = '../..';
include_once "$ROOT/db/conexion.php";
include_once "$ROOT/includes/sesion.php";

if (!tieneSesion()) {
    echo json_encode(['success' => false, 'message' => 'No tienes sesión activa']);
    exit();
}

$id_instalacion = (int)($_GET['id'] ?? 0);

if ($id_instalacion <= 0) {
    echo json_encode(['success' => false, 'message' => 'ID de instalación no válido']);
    exit();
}

// Obtener datos completos de la instalación
$sql = "
    SELECT 
        i.*,
        c.total as precio_total,
        c.tipo_terreno,
        c.tipo_instalacion,
        c.largo,
        c.ancho,
        c.fecha as fecha_cotizacion,
        cli.nombre AS nombre_cliente,
        cli.telefono,
        cli.email,
        cli.direccion,
        COALESCE(a.nombre, 'Sin asignar') AS nombre_admin,
        COALESCE(a.apellido, '') AS apellido_admin
    FROM instalaciones i
    INNER JOIN cotizaciones c ON i.id_cotizacion = c.id
    LEFT JOIN clientes cli ON c.id_cliente = cli.id
    LEFT JOIN admins a ON c.id_admin = a.id 
    WHERE i.id = ?
";

$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "i", $id_instalacion);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

if ($row = mysqli_fetch_assoc($result)) {
    // Decodificar JSON de materiales si existe
    if ($row['materiales_utilizados']) {
        $row['materiales_utilizados'] = json_decode($row['materiales_utilizados'], true);
    } else {
        $row['materiales_utilizados'] = [];
    }
    
    echo json_encode([
        'success' => true, 
        'instalacion' => $row
    ]);
} else {
    echo json_encode([
        'success' => false, 
        'message' => 'Instalación no encontrada'
    ]);
}
?>
