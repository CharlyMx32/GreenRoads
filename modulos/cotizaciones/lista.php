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
        COALESCE(a.nombre, 'Sin asignar') AS nombre_admin,
        COALESCE(a.apellido, '') AS apellido_admin
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
                        <?php foreach ($cotizaciones as $cotizacion):
                            $estado_final = in_array($cotizacion['estado'], ['rechazada', 'cancelada', 'aceptada']);
                        ?>
                            <tr>
                                <td>#<?= $cotizacion['id'] ?></td>
                                <td><?= htmlspecialchars($cotizacion['nombre_cliente'] ?? '') ?></td>
                                <td><?= ucfirst($cotizacion['tipo_terreno'] ?? '') ?></td>
                                <td><?= ucfirst($cotizacion['tipo_instalacion'] ?? '') ?></td>
                                <td><?= ($cotizacion['garantia_anios'] ? $cotizacion['garantia_anios'] . ' años' : '-') ?></td>
                                <td><?= date('Y-m-d', strtotime($cotizacion['fecha'])) ?></td>
                                <td><?= ucfirst($cotizacion['estado']) ?></td>
                                <td>$<?= isset($cotizacion['total']) ? number_format((float)$cotizacion['total'], 2) : '0.00' ?></td>
                                <td>
                                    <?php
                                    if (!empty($cotizacion['id_admin'])) {
                                        echo htmlspecialchars(
                                            ($cotizacion['nombre_admin'] ?? 'Admin ID: ') .
                                                (!empty($cotizacion['apellido_admin']) ? ' ' . $cotizacion['apellido_admin'] : '')
                                        );
                                    } else {
                                        echo 'Sin asignar';
                                    }
                                    ?>
                                </td>
                                <td>
                                    <div class="opciones-tabla-lista">
                                        <!-- Botón editar-->
                                        <div class="editar <?= $estado_final ? 'disabled' : '' ?>"
                                            onclick="<?= !$estado_final ? "location.href='{$ROOT}/cotizaciones/editar_cotizacion?id={$cotizacion['id']}'" : '' ?>"
                                            <?= $estado_final ? 'style="opacity: 0.5; cursor: not-allowed;"' : '' ?>>
                                            <i class="fa-solid fa-pen-to-square"></i>
                                        </div>

                                        <!-- Botón aceptar-->
                                        <div class="aceptar <?= $estado_final ? 'disabled' : '' ?>"
                                            onclick="<?= !$estado_final ? "confirmChangeStatus({$cotizacion['id']}, 'aceptada')" : '' ?>"
                                            <?= ($cotizacion['estado'] == 'aceptada') ? 'style="color: #00dd0b;"' : ($estado_final ? 'style="opacity: 0.5; cursor: not-allowed;"' : '') ?>>
                                            <i class="fa-solid fa-check"></i>
                                        </div>

                                        <!-- Botón rechazar-->
                                        <div class="rechazar <?= $estado_final ? 'disabled' : '' ?>"
                                            onclick="<?= !$estado_final ? "confirmChangeStatus({$cotizacion['id']}, 'rechazada')" : '' ?>"
                                            <?= ($cotizacion['estado'] == 'rechazada') ? 'style="color: #ff0000;"' : ($estado_final ? 'style="opacity: 0.5; cursor: not-allowed;"' : '') ?>>
                                            <i class="fa-solid fa-times"></i>
                                        </div>

                                        <!-- Botón cancelar-->
                                        <div class="cancelar <?= $estado_final ? 'disabled' : '' ?>"
                                            onclick="<?= !$estado_final ? "confirmChangeStatus({$cotizacion['id']}, 'cancelada')" : '' ?>"
                                            <?= ($cotizacion['estado'] == 'cancelada') ? 'style="color: #ff9900;"' : ($estado_final ? 'style="opacity: 0.5; cursor: not-allowed;"' : '') ?>>
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
        function confirmChangeStatus(idCotizacion, nuevoEstado) {
            const mensajes = {
                'aceptada': '¿Estás seguro de que deseas ACEPTAR esta cotización?',
                'rechazada': '¿Estás seguro de que deseas RECHAZAR esta cotización?',
                'cancelada': '¿Estás seguro de que deseas CANCELAR esta cotización?'
            };

            if (confirm(mensajes[nuevoEstado])) {
                changeStatus(idCotizacion, nuevoEstado);
            }
        }

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