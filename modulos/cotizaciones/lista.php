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
        c.es_comparativa,
        c.opcion_seleccionada,
        c.area_total,
        c.precio_instalacion_m2,
        c.precio_mano_obra_m2,
        c.iva,
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
    <link rel="stylesheet" href="../../css/cotizaciones/cotizaciones.css?v=<?= time() ?>">
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
                                <td><?= date('Y-m-d', strtotime($cotizacion['fecha'])) ?></td>
                                <td><?= ucfirst($cotizacion['estado']) ?></td>
                                <td>
                                    <?php if ($cotizacion['es_comparativa'] === 'S' || $cotizacion['es_comparativa'] === '1' || $cotizacion['es_comparativa'] == 1): ?>
                                        <!-- Cotización comparativa -->
                                        <?php if ($cotizacion['estado'] === 'aceptada' && !empty($cotizacion['opcion_seleccionada'])): ?>
                                            <!-- Mostrar total de la opción aceptada (ya calculado en BD) -->
                                            <?php 
                                            $opcion_letra = ($cotizacion['opcion_seleccionada'] == 1) ? 'A' : 'B';
                                            ?>
                                            <div style="font-size: 12px; line-height: 1.2;">
                                                <strong style="color: #28a745;">
                                                    Opción <?= $opcion_letra ?> Aceptada
                                                </strong><br>
                                                <span style="font-weight: 600; color: #333;">
                                                    $<?= number_format($cotizacion['total'], 2) ?>
                                                </span>
                                            </div>
                                        <?php else: ?>
                                            <!-- Mostrar "Pendiente" para comparativas no aceptadas -->
                                            <div style="font-size: 14px; text-align: center;">
                                                <small style="color: #666;">Esperando selección</small>
                                            </div>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <!-- Cotización simple -->
                                        <span style="font-weight: 600; color: #333;">
                                            $<?= number_format($cotizacion['total'], 2) ?>
                                        </span>
                                    <?php endif; ?>
                                </td>
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
                                        <!-- Botón ver detalle -->
                                        <div class="ver-detalle" 
                                            onclick="location.href='detalle.php?id=<?= $cotizacion['id'] ?>'"
                                            title="Ver detalle"
                                            style="color: #5facffff;">
                                            <i class="fa-solid fa-eye"></i>
                                        </div>

                                        <!-- Botón editar -->
                                        <div class="editar <?= $cotizacion['estado'] != 'pendiente' ? 'disabled' : '' ?>"
                                            onclick="<?= $cotizacion['estado'] == 'pendiente' ? "location.href='{$ROOT}/cotizaciones/editar_cotizacion?id={$cotizacion['id']}'" : '' ?>"
                                            <?= $cotizacion['estado'] != 'pendiente' ? 'style="opacity: 0.5; cursor: not-allowed;"' : '' ?>>
                                            <i class="fa-solid fa-pen-to-square"></i>
                                        </div>

                                        <!-- Botón aceptar -->
                                        <div class="aceptar <?= $cotizacion['estado'] != 'pendiente' ? 'disabled' : '' ?>"
                                            onclick="<?= $cotizacion['estado'] == 'pendiente' ? "location.href='detalle.php?id={$cotizacion['id']}&action=accept'" : '' ?>"
                                            style="<?= $cotizacion['estado'] == 'aceptada' ? 'color: #00dd0b;' : ($cotizacion['estado'] != 'pendiente' ? 'opacity: 0.5; cursor: not-allowed;' : '') ?>"
                                            title="<?= $cotizacion['estado'] == 'pendiente' ? 'Ver detalle para aceptar' : 'No disponible' ?>">
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

                                        <!-- Botón generar PDF -->
                                        <div class="generar-pdf"
                                            onclick="generarPDFCotizacion(<?= $cotizacion['id'] ?>)"
                                            title="Generar PDF"
                                            style="color: #ff6b35;">
                                            <i class="fa-solid fa-file-pdf"></i>
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
    <script src="../../scripts/pdf/generador_pdf.js"></script>
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