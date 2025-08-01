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
        "btn_atras" => "window.location.href='../dashboard/menu.php'"
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
                        <!--<th>Terreno</th>
                        <th>Instalación</th>
                        <th>Garantía</th>-->
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
                                <!-- <td><?= ucfirst($cotizacion['tipo_terreno'] ?? '') ?></td>
                                <td><?= ucfirst($cotizacion['tipo_instalacion'] ?? '') ?></td>
                                <td><?= ($cotizacion['garantia_anios'] ? $cotizacion['garantia_anios'] . ' años' : '-') ?></td> -->
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
                                        <!-- Botón editar -->
                                        <div class="editar <?= $cotizacion['estado'] != 'pendiente' ? 'disabled' : '' ?>"
                                            onclick="<?= $cotizacion['estado'] == 'pendiente' ? "location.href='{$ROOT}/cotizaciones/editar_cotizacion?id={$cotizacion['id']}'" : '' ?>"
                                            <?= $cotizacion['estado'] != 'pendiente' ? 'style="opacity: 0.5; cursor: not-allowed;"' : '' ?>>
                                            <i class="fa-solid fa-pen-to-square"></i>
                                        </div>

                                        <!-- Botón aceptar -->
                                        <div class="aceptar <?= $cotizacion['estado'] != 'pendiente' ? 'disabled' : '' ?>"
                                            onclick="<?= $cotizacion['estado'] == 'pendiente' ? "confirmChangeStatus({$cotizacion['id']}, 'aceptada')" : '' ?>"
                                            style="<?= $cotizacion['estado'] == 'aceptada' ? 'color: #00dd0b;' : ($cotizacion['estado'] != 'pendiente' ? 'opacity: 0.5; cursor: not-allowed;' : '') ?>">
                                            <i class="fa-solid fa-check"></i>
                                        </div>

                                        <!-- Botón rechazar -->
                                        <div class="rechazar <?= $cotizacion['estado'] != 'pendiente' ? 'disabled' : '' ?>"
                                            onclick="<?= $cotizacion['estado'] == 'pendiente' ? "confirmChangeStatus({$cotizacion['id']}, 'rechazada')" : '' ?>"
                                            style="<?= $cotizacion['estado'] == 'rechazada' ? 'color: #ff0000;' : ($cotizacion['estado'] != 'pendiente' ? 'opacity: 0.5; cursor: not-allowed;' : '') ?>">
                                            <i class="fa-solid fa-times"></i>
                                        </div>

                                        <!-- Botón cancelar -->
                                        <div class="cancelar <?= $cotizacion['estado'] != 'pendiente' ? 'disabled' : '' ?>"
                                            onclick="<?= $cotizacion['estado'] == 'pendiente' ? "confirmChangeStatus({$cotizacion['id']}, 'cancelada')" : '' ?>"
                                            style="<?= $cotizacion['estado'] == 'cancelada' ? 'color: #ff9900;' : ($cotizacion['estado'] != 'pendiente' ? 'opacity: 0.5; cursor: not-allowed;' : '') ?>">
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

    <script src="../../scripts/cotizaciones/lista.js"></script>
    <script>
        function confirmChangeStatus(id, estado) {
            const mensajes = {
                'aceptada': '¿Confirmas que deseas ACEPTAR esta cotización?',
                'rechazada': '¿Confirmas que deseas RECHAZAR esta cotización?',
                'cancelada': '¿Confirmas que deseas CANCELAR esta cotización?',
                'pendiente': '¿Confirmas que deseas volver a PENDIENTE esta cotización?'
            };

            if (confirm(mensajes[estado] || '¿Confirmas el cambio de estado?')) {
                changeStatus(id, estado);
            }
        }

        function changeStatus(id, estado) {
            displayPopUp();

            fetch(`../../php/cotizaciones/cambiar_estado.php?id=${id}&estado=${estado}`)
                .then(response => {
                    if (!response.ok) throw new Error('Error en la red');
                    return response.json();
                })
                .then(data => {
                    if (data.success) {
                        window.location.reload();
                    } else {
                        displayMensajeError(data.message || 'No se puede cambiar el estado nuevamente');
                        // Deshabilitar botones después de un error
                        document.querySelectorAll(`[onclick*="confirmChangeStatus(${id},"]`).forEach(btn => {
                            btn.classList.add('disabled');
                            btn.style.opacity = '0.5';
                            btn.style.cursor = 'not-allowed';
                            btn.setAttribute('onclick', '');
                        });
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    displayMensajeError("Error de conexión. Intente nuevamente.");
                });
        }
    </script>
</body>

</html>