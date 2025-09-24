<?php
$ROOT = '../..';
$TITULO = "Detalle de Cita";

include_once "$ROOT/db/conexion.php";
include_once "$ROOT/includes/sesion.php";
include_once "$ROOT/includes/config.php";

if (!tieneSesion()) {
    header("Location: $URL_ROOT/login");
    exit();
}

if (!puedeVerCitas()) {
    header("Location: ../dashboard/menu.php?error=sin_permisos");
    exit();
}

// Obtener ID de la cita
$id_cita = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$id_cita) {
    header("Location: lista.php");
    exit();
}

// Obtener datos completos de la cita
$sql_cita = "
    SELECT 
        c.*,
        cli.nombre AS nombre_cliente,
        cli.telefono AS telefono_cliente,
        cli.email AS email_cliente,
        cli.direccion AS direccion_cliente,
        COALESCE(a.nombre, 'Sin asignar') AS nombre_admin,
        COALESCE(a.apellido, '') AS apellido_admin
    FROM citas c
    LEFT JOIN clientes cli ON c.id_cliente = cli.id
    LEFT JOIN admins a ON c.id_admin = a.id 
    WHERE c.id = ? AND c.estado != 'eliminado'
";
$stmt_cita = mysqli_prepare($conn, $sql_cita);
mysqli_stmt_bind_param($stmt_cita, 'i', $id_cita);
mysqli_stmt_execute($stmt_cita);
$result_cita = mysqli_stmt_get_result($stmt_cita);
$cita = mysqli_fetch_assoc($result_cita);

if (!$cita) {
    header("Location: lista.php");
    exit();
}

// Verificar si el usuario tiene permiso para ver esta cita específica
if (!puedeVerCita($cita['id_admin'])) {
    header("Location: lista.php?error=sin_permisos");
    exit();
}

// Calcular información adicional
$fecha_hora_cita = $cita['fecha_cita'] . ' ' . $cita['hora_cita'];
$timestamp_cita = strtotime($fecha_hora_cita);
$timestamp_actual = time();
$es_proxima = ($timestamp_cita > $timestamp_actual && $timestamp_cita <= ($timestamp_actual + 86400));
$ya_paso = $timestamp_cita < $timestamp_actual;
?>

<!DOCTYPE html>
<html>

<head>
    <?php include_once "$ROOT/includes/head.php"; ?>
    <link rel="stylesheet" href="../../css/citas/citas.css?v=<?= time() ?>">
</head>

<body>
    <?php
    $headerParams = [
        "titulo" => $TITULO . " #" . $cita['id'],
        "btn_atras" => "window.location.href='lista.php'"
    ];
    include_once '../../includes/header.php';
    ?>

    <div class="content">
        <div class="detalle-container">
            <!-- Información principal de la cita -->
            <div class="card-detalle">
                <div class="card-header">
                    <h3>Información de la Cita</h3>
                    <div class="estado-badge">
                        <span class="badge-estado estado-<?= $cita['estado'] ?>">
                            <?= ucfirst($cita['estado']) ?>
                        </span>
                        <?php if ($es_proxima): ?>
                            <span class="badge-urgencia urgencia-proxima">Próxima</span>
                        <?php elseif ($ya_paso && $cita['estado'] === 'pendiente'): ?>
                            <span class="badge-urgencia urgencia-vencida">Vencida</span>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="card-body">
                    <div class="detalle-row">
                        <div class="detalle-item">
                            <label>ID de Cita:</label>
                            <span>#<?= $cita['id'] ?></span>
                        </div>
                        <div class="detalle-item">
                            <label>Tipo de Cita:</label>
                            <span class="badge-tipo tipo-<?= $cita['tipo_cita'] ?>">
                                <?= ucfirst($cita['tipo_cita']) ?>
                            </span>
                        </div>
                    </div>

                    <div class="detalle-row">
                        <div class="detalle-item">
                            <label>Fecha:</label>
                            <span><?= date('d/m/Y', strtotime($cita['fecha_cita'])) ?></span>
                        </div>
                        <div class="detalle-item">
                            <label>Hora:</label>
                            <span><?= substr($cita['hora_cita'], 0, 5) ?></span>
                        </div>
                    </div>

                    <div class="detalle-item full-width">
                        <label>Dirección de la Cita:</label>
                        <p><?= htmlspecialchars($cita['direccion_cita']) ?></p>
                    </div>

                    <?php if ($cita['descripcion']): ?>
                    <div class="detalle-item full-width">
                        <label>Descripción / Notas:</label>
                        <p><?= nl2br(htmlspecialchars($cita['descripcion'])) ?></p>
                    </div>
                    <?php endif; ?>

                    <div class="detalle-row">
                        <div class="detalle-item">
                            <label>Fecha de Creación:</label>
                            <span><?= date('d/m/Y H:i', strtotime($cita['fecha_creacion'])) ?></span>
                        </div>
                        <?php if ($cita['fecha_actualizacion'] && $cita['fecha_actualizacion'] !== $cita['fecha_creacion']): ?>
                        <div class="detalle-item">
                            <label>Última Actualización:</label>
                            <span><?= date('d/m/Y H:i', strtotime($cita['fecha_actualizacion'])) ?></span>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Información del cliente -->
            <div class="card-detalle">
                <div class="card-header">
                    <h3>Información del Cliente</h3>
                </div>
                <div class="card-body">
                    <div class="detalle-row">
                        <div class="detalle-item">
                            <label>Nombre:</label>
                            <span><?= htmlspecialchars($cita['nombre_cliente']) ?></span>
                        </div>
                        <div class="detalle-item">
                            <label>Teléfono:</label>
                            <span>
                                <a href="tel:<?= $cita['telefono_cliente'] ?>" class="link-contacto">
                                    <?= htmlspecialchars($cita['telefono_cliente']) ?>
                                </a>
                            </span>
                        </div>
                    </div>

                    <?php if ($cita['email_cliente']): ?>
                    <div class="detalle-row">
                        <div class="detalle-item">
                            <label>Email:</label>
                            <span>
                                <a href="mailto:<?= $cita['email_cliente'] ?>" class="link-contacto">
                                    <?= htmlspecialchars($cita['email_cliente']) ?>
                                </a>
                            </span>
                        </div>
                    </div>
                    <?php endif; ?>

                    <?php if ($cita['direccion_cliente']): ?>
                    <div class="detalle-item full-width">
                        <label>Dirección del Cliente:</label>
                        <p><?= htmlspecialchars($cita['direccion_cliente']) ?></p>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Información del administrador -->
            <div class="card-detalle">
                <div class="card-header">
                    <h3>Administrador Asignado</h3>
                </div>
                <div class="card-body">
                    <div class="detalle-row">
                        <div class="detalle-item">
                            <label>Nombre:</label>
                            <span>
                                <?= htmlspecialchars($cita['nombre_admin']) ?>
                                <?= !empty($cita['apellido_admin']) ? ' ' . htmlspecialchars($cita['apellido_admin']) : '' ?>
                            </span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Acciones -->
            <div class="acciones-detalle">
                <?php if ($cita['estado'] !== 'completada'): ?>
                    <?php if ($cita['id_admin'] == $_SESSION['usuario'] || esAdmin()): ?>
                        <button class="btn-accion btn-editar" onclick="location.href='editar_cita.php?id=<?= $cita['id'] ?>'">
                            <i class="fa-solid fa-pen-to-square"></i>
                            Editar Cita
                        </button>
                    <?php endif; ?>
                <?php endif; ?>

                <?php if ($cita['estado'] === 'pendiente'): ?>
                    <button class="btn-accion btn-completar" onclick="confirmChangeStatus(<?= $cita['id'] ?>, 'completada')">
                        <i class="fa-solid fa-check"></i>
                        Marcar como Completada
                    </button>
                    <button class="btn-accion btn-cancelar" onclick="confirmChangeStatus(<?= $cita['id'] ?>, 'cancelada')">
                        <i class="fa-solid fa-ban"></i>
                        Cancelar Cita
                    </button>
                <?php endif; ?>

                <?php if ($cita['estado'] !== 'completada' && ($cita['id_admin'] == $_SESSION['usuario'] || esAdmin())): ?>
                    <button class="btn-accion btn-eliminar" onclick="confirmDelete(<?= $cita['id'] ?>)">
                        <i class="fa-solid fa-trash"></i>
                        Eliminar Cita
                    </button>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <?php include_once '../../includes/popup.php'; ?>

    <script>
        function confirmChangeStatus(id, estado) {
            const mensajes = {
                'completada': '¿Confirmas que deseas marcar esta cita como COMPLETADA?',
                'cancelada': '¿Confirmas que deseas CANCELAR esta cita?',
                'pendiente': '¿Confirmas que deseas volver a PENDIENTE esta cita?'
            };

            if (confirm(mensajes[estado] || '¿Confirmas el cambio de estado?')) {
                changeStatus(id, estado);
            }
        }

        function confirmDelete(id) {
            if (confirm('¿Estás seguro de que deseas eliminar esta cita? Esta acción no se puede deshacer.')) {
                deleteCita(id);
            }
        }

        function changeStatus(id, estado) {
            displayPopUp();

            fetch(`../../php/citas/cambiar_estado.php`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `id=${id}&estado=${estado}`
            })
            .then(response => {
                if (!response.ok) throw new Error('Error en la red');
                return response.json();
            })
            .then(data => {
                if (data.success) {
                    const mensajes = {
                        'completada': 'Cita marcada como completada exitosamente',
                        'cancelada': 'Cita cancelada exitosamente',
                        'pendiente': 'Cita marcada como pendiente exitosamente'
                    };
                    displayMensajeExitoso(mensajes[estado] || 'Estado actualizado exitosamente', 'window.location.reload()');
                } else {
                    displayMensajeError(data.message || 'Error al cambiar estado');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                displayMensajeError("Error de conexión. Intente nuevamente.");
            });
        }

        function deleteCita(id) {
            displayPopUp();

            fetch(`../../php/citas/eliminar.php`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `id=${id}`
            })
            .then(response => {
                if (!response.ok) throw new Error('Error en la red');
                return response.json();
            })
            .then(data => {
                if (data.success) {
                    displayMensajeExitoso('Cita eliminada exitosamente', "window.location.href = 'lista.php'");
                } else {
                    displayMensajeError(data.message || 'Error al eliminar cita');
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