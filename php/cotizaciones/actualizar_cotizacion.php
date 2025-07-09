<?php
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

include_once '../../db/conexion.php';

// Obtener datos del POST
$data = json_decode(file_get_contents('php://input'), true);

if (empty($data) || !isset($data['id_cotizacion'])) {
    echo json_encode(['exito' => false, 'mensaje' => 'Datos inválidos']);
    exit;
}

// Iniciar transacción
mysqli_begin_transaction($conn);

try {
    // Actualizar datos principales de la cotización
    $sql = "UPDATE cotizaciones SET 
            id_cliente = ?,
            tipo_terreno = ?,
            forma_terreno = ?,
            dimension1 = ?,
            dimension2 = ?,
            area_irregular = ?,
            area_total = ?,
            tipo_instalacion = ?,
            garantia = ?,
            precio_instalacion = ?,
            total_sin_iva = ?,
            iva = ?,
            total_con_iva = ?,
            fecha_actualizacion = NOW()
            WHERE id = ?";
    
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "isssdddsdddddi", 
        $data['cliente'],
        $data['tipoTerreno'],
        $data['formaTerreno'],
        $data['dimension1'],
        $data['dimension2'],
        $data['areaIrregular'],
        $data['areaTotal'],
        $data['tipoInstalacion'],
        $data['garantia'],
        $data['precioInstalacion'],
        $data['totalSinIVA'],
        $data['iva'],
        $data['totalConIVA'],
        $data['id_cotizacion']
    );
    mysqli_stmt_execute($stmt);

    // Eliminar rollos anteriores
    $sql = "DELETE FROM cotizaciones_rollos WHERE id_cotizacion = ?";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "i", $data['id_cotizacion']);
    mysqli_stmt_execute($stmt);

    // Insertar nuevos rollos
    foreach ($data['rollos'] as $rollo) {
        $sql = "INSERT INTO cotizaciones_rollos (id_cotizacion, id_producto, cantidad, subtotal) 
                VALUES (?, ?, ?, ?)";
        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, "iidd", 
            $data['id_cotizacion'],
            $rollo['id'],
            $rollo['cantidad'],
            $rollo['subtotal']
        );
        mysqli_stmt_execute($stmt);
    }

    // Eliminar productos anteriores
    $sql = "DELETE FROM cotizaciones_productos WHERE id_cotizacion = ?";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "i", $data['id_cotizacion']);
    mysqli_stmt_execute($stmt);

    // Insertar nuevos productos
    foreach ($data['productos'] as $producto) {
        $sql = "INSERT INTO cotizaciones_productos (id_cotizacion, id_producto, cantidad, subtotal) 
                VALUES (?, ?, ?, ?)";
        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, "iidd", 
            $data['id_cotizacion'],
            $producto['id'],
            $producto['cantidad'],
            $producto['subtotal']
        );
        mysqli_stmt_execute($stmt);
    }

    // Eliminar extras anteriores
    $sql = "DELETE FROM cotizaciones_extras WHERE id_cotizacion = ?";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "i", $data['id_cotizacion']);
    mysqli_stmt_execute($stmt);

    foreach ($data['extras'] as $extra) {
        $sql = "INSERT INTO cotizaciones_extras (id_cotizacion, id_extra) 
                VALUES (?, ?)";
        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, "ii", 
            $data['id_cotizacion'],
            $extra['id']
        );
        mysqli_stmt_execute($stmt);
    }

    mysqli_commit($conn);
    echo json_encode(['exito' => true, 'mensaje' => 'Cotización actualizada correctamente']);
    
} catch (Exception $e) {
    mysqli_rollback($conn);
    echo json_encode(['exito' => false, 'mensaje' => 'Error al actualizar la cotización: ' . $e->getMessage()]);
}
?>