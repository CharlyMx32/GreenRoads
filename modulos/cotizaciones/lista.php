<?php
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

$ROOT = '../..';
$TITULO = "Cotizaciones";

include_once "$ROOT/db/conexion.php";
include_once "$ROOT/includes/sesion.php";
include_once "$ROOT/includes/config.php";

if (!tieneSesion()) {
    header("Location: $URL_ROOT/login");
    exit();
}

// Obtener cotizaciones con cliente y admin
$cotizaciones = [];
$sql = "
    SELECT 
        c.id,
        cli.nombre AS nombre_cliente,
        c.fecha,
        c.estado,
        c.total,
        c.tipo_terreno,
        c.tipo_instalacion,
        c.garantia_anios,
        c.id_admin,  
        a.nombre AS nombre_admin,
        a.apellido AS apellido_admin
    FROM cotizaciones c
    LEFT JOIN clientes cli ON c.id_cliente = cli.id
    LEFT JOIN admins a ON c.id_admin = a.id 
    ORDER BY c.fecha DESC
";
$result = mysqli_query($conn, $sql);
while ($row = mysqli_fetch_assoc($result)) {
    $cotizaciones[] = $row;
}
?>

<!DOCTYPE html>
<html>

<head>
    <?php include_once "$ROOT/includes/head.php"; ?>
    <script src="<?php echo $ROOT ?>/../js/buscador.js?cache=<?php echo uniqid(); ?>"></script>
</head>

<body>
    <?php
    $headerParams = [
        "buscador" => true,
        "btn_atras" => 'window.history.back()'
    ];
    include_once '../../includes/header.php';
    ?>

    <div class="content">
        <div class="contenedor-tabla" style="max-height: calc(100vh - 200px); margin-bottom: 60px;">
            <table class="tabla-lista">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Cliente</th>
                        <th>Terreno</th>
                        <th>Instalación</th>
                        <th>Garantía</th>
                        <th>Fecha</th>
                        <th>Estado</th>
                        <th>Total</th>
                        <th>Admin</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($cotizaciones)): ?>
                        <tr>
                            <td colspan="11">No hay cotizaciones registradas.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($cotizaciones as $cotizacion): ?>
                            <tr>
                                <td>#<?= $cotizacion['id'] ?></td>
                                <td><?= htmlspecialchars($cotizacion['nombre_cliente'] ?? '') ?></td>
                                <td><?= ucfirst($cotizacion['tipo_terreno'] ?? '') ?></td>
                                <td><?= ucfirst($cotizacion['tipo_instalacion'] ?? '') ?></td>
                                <td><?= ($cotizacion['garantia_anios'] ? $cotizacion['garantia_anios'] . ' años' : '-') ?></td>
                                <td><?= date('Y-m-d', strtotime($cotizacion['fecha'])) ?></td>
                                <td><?= ucfirst($cotizacion['estado']) ?></td>
                                <td>$<?= number_format($cotizacion['total'], 2) ?></td>
                                <td>
                                    <?php
                                    if (!empty($cotizacion['nombre_admin'])) {
                                        echo htmlspecialchars($cotizacion['nombre_admin'] . ' ' . $cotizacion['apellido_admin']);
                                    } else {
                                        echo htmlspecialchars($cotizacion['id_admin'] ?? 'Sin asignar');
                                    }
                                    ?>
                                </td>
                                <td>
                                    <div class="opciones-tabla-lista">
                                        <!-- Botón editar -->
                                        <div class="editar"
                                            onclick="location.href='<?php echo $ROOT ?>/cotizaciones/editar_cotizacion?id=<?php echo $cotizacion['id'] ?>'">
                                            <i class="fa-solid fa-pen-to-square"></i>
                                        </div>

                                        <!-- Botón aceptar -->
                                        <div class="aceptar"
                                            onclick="changeStatus(<?= $cotizacion['id'] ?>, 'aceptada')"
                                            <?php if ($cotizacion['estado'] == 'aceptada') echo 'style="color: #00dd0b;"'; ?>>
                                            <i class="fa-solid fa-check"></i>
                                        </div>

                                        <!-- Botón rechazar -->
                                        <div class="rechazar"
                                            onclick="changeStatus(<?= $cotizacion['id'] ?>, 'rechazada')"
                                            <?php if ($cotizacion['estado'] == 'rechazada') echo 'style="color: #ff0000;"'; ?>>
                                            <i class="fa-solid fa-times"></i>
                                        </div>

                                        <!-- Botón cancelar -->
                                        <div class="cancelar"
                                            onclick="changeStatus(<?= $cotizacion['id'] ?>, 'cancelada')"
                                            <?php if ($cotizacion['estado'] == 'cancelada') echo 'style="color: #ff9900;"'; ?>>
                                            <i class="fa-solid fa-ban"></i>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div class="btn-nuevo" onclick="location.href='nueva_cotizacion.php'">
        <i class="fa-solid fa-plus"></i>
    </div>

    <?php include_once '../../includes/popup.php'; ?>

    <script>
        function changeStatus(idCotizacion, nuevoEstado) {
            fetch(`../../php/cotizaciones/cambiar_estado.php?id=${idCotizacion}&estado=${nuevoEstado}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        location.reload();
                    } else {
                        alert('Error: ' + (data.message || 'No se pudo cambiar el estado'));
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Error al cambiar el estado');
                });
        }
    </script>
</body>

</html>