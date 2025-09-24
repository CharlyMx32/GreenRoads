<?php
$ROOT = '../..';
$TITULO = "Citas";

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

// Obtener citas según el rol del usuario
$citas = [];
$sql = "
    SELECT 
        c.id,
        c.id_cliente,
        c.id_admin,
        c.fecha_cita,
        c.hora_cita,
        c.tipo_cita,
        c.direccion_cita,
        c.descripcion,
        c.estado,
        c.fecha_creacion,
        cli.nombre AS nombre_cliente,
        cli.telefono AS telefono_cliente,
        cli.email AS email_cliente,
        COALESCE(a.nombre, 'Sin asignar') AS nombre_admin,
        COALESCE(a.apellido, '') AS apellido_admin
    FROM citas c
    LEFT JOIN clientes cli ON c.id_cliente = cli.id
    LEFT JOIN admins a ON c.id_admin = a.id 
    WHERE c.estado != 'eliminado'";

// Si es vendedor, solo ver sus citas
if (esVendedor()) {
    $sql .= " AND c.id_admin = " . $_SESSION['usuario'];
}

$sql .= " ORDER BY c.fecha_cita DESC, c.hora_cita DESC";

$result = mysqli_query($conn, $sql);
while ($row = mysqli_fetch_assoc($result)) {
    $citas[] = $row;
}
?>

<!DOCTYPE html>
<html>

<head>
    <?php include_once "$ROOT/includes/head.php"; ?>
    <link rel="stylesheet" href="../../css/citas/citas.css">
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
        <!-- Filtros simplificados -->
        <div class="filtros-container">
            <div class="filtro-item">
                <label for="filtro-estado">Estado:</label>
                <select id="filtro-estado" onchange="aplicarFiltros()">
                    <option value="todos">Todos los estados</option>
                    <option value="pendiente">Pendiente</option>
                    <option value="completada">Completada</option>
                    <option value="cancelada">Cancelada</option>
                </select>
            </div>
            <div class="filtro-item">
                <label for="filtro-tipo">Tipo:</label>
                <select id="filtro-tipo" onchange="aplicarFiltros()">
                    <option value="todos">Todos los tipos</option>
                    <option value="medicion">Medición</option>
                    <option value="consulta">Consulta</option>
                    <option value="instalacion">Instalación</option>
                    <option value="mantenimiento">Mantenimiento</option>
                    <option value="seguimiento">Seguimiento</option>
                    <option value="otro">Otro</option>
                </select>
            </div>
            <div class="filtro-item">
                <label for="filtro-fecha-desde">Fecha desde:</label>
                <input type="date" id="filtro-fecha-desde" onchange="aplicarFiltros()">
            </div>
            <div class="filtro-item">
                <label for="filtro-fecha-hasta">Fecha hasta:</label>
                <input type="date" id="filtro-fecha-hasta" onchange="aplicarFiltros()">
            </div>
        </div>

        <div class="contenedor-tabla" style="max-height: calc(100vh - 200px); margin-bottom: 80px;">
            <table class="tabla-lista">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Cliente</th>
                        <th>Fecha</th>
                        <th>Hora</th>
                        <th>Tipo</th>
                        <th>Dirección</th>
                        <th>Estado</th>
                        <th>Admin</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody id="tabla-citas">
                    <?php if (empty($citas)): ?>
                        <tr>
                            <td colspan="9">No hay citas registradas.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($citas as $cita):
                            // Verificar si la cita es próxima (en las próximas 24 horas)
                            $fecha_hora_cita = $cita['fecha_cita'] . ' ' . $cita['hora_cita'];
                            $timestamp_cita = strtotime($fecha_hora_cita);
                            $timestamp_actual = time();
                            $es_proxima = ($timestamp_cita > $timestamp_actual && $timestamp_cita <= ($timestamp_actual + 86400));
                            $ya_paso = $timestamp_cita < $timestamp_actual;
                            
                            $clase_fila = '';
                            if ($es_proxima) {
                                $clase_fila = 'style="background-color: #fff3cd;"';
                            } elseif ($ya_paso && $cita['estado'] === 'pendiente') {
                                $clase_fila = 'style="background-color: #f8d7da;"';
                            }
                        ?>
                            <tr class="fila-cita" 
                                data-estado="<?= $cita['estado'] ?>" 
                                data-tipo="<?= $cita['tipo_cita'] ?>"
                                data-fecha="<?= $cita['fecha_cita'] ?>"
                                <?= $clase_fila ?>>
                                <td>#<?= $cita['id'] ?></td>
                                <td>
                                    <div style="font-weight: 600; font-size: 11px;"><?= htmlspecialchars($cita['nombre_cliente'] ?? '') ?></div>
                                    <div style="font-size: 10px; color: #666;"><?= htmlspecialchars($cita['telefono_cliente'] ?? '') ?></div>
                                </td>
                                <td>
                                    <?= date('d/m/Y', strtotime($cita['fecha_cita'])) ?>
                                    <?php if ($es_proxima): ?>
                                        <br><small style="color: #856404; font-weight: bold; font-size: 9px;">Próxima</small>
                                    <?php elseif ($ya_paso && $cita['estado'] === 'pendiente'): ?>
                                        <br><small style="color: #721c24; font-weight: bold; font-size: 9px;">Vencida</small>
                                    <?php endif; ?>
                                </td>
                                <td><?= substr($cita['hora_cita'], 0, 5) ?></td>
                                <td>
                                    <span class="badge-tipo tipo-<?= $cita['tipo_cita'] ?>">
                                        <?= ucfirst($cita['tipo_cita']) ?>
                                    </span>
                                </td>
                                <td>
                                    <div style="max-width: 150px; word-wrap: break-word; font-size: 11px;">
                                        <?= htmlspecialchars(substr($cita['direccion_cita'], 0, 30)) ?>
                                        <?= strlen($cita['direccion_cita']) > 30 ? '...' : '' ?>
                                    </div>
                                </td>
                                <td>
                                    <span class="badge-estado estado-<?= $cita['estado'] ?>">
                                        <?= ucfirst($cita['estado']) ?>
                                    </span>
                                </td>
                                <td style="font-size: 11px;">
                                    <?php
                                    if (!empty($cita['id_admin'])) {
                                        echo htmlspecialchars(
                                            ($cita['nombre_admin'] ?? 'Admin ID: ') .
                                                (!empty($cita['apellido_admin']) ? ' ' . $cita['apellido_admin'] : '')
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
                                            onclick="location.href='detalle_cita.php?id=<?= $cita['id'] ?>'"
                                            title="Ver detalle"
                                            style="color: #5facffff;">
                                            <i class="fa-solid fa-eye"></i>
                                        </div>

                                        <!-- Botón editar -->
                                        <div class="editar <?= $cita['estado'] === 'completada' ? 'disabled' : '' ?>"
                                            onclick="<?= $cita['estado'] !== 'completada' ? "location.href='editar_cita.php?id={$cita['id']}'" : '' ?>"
                                            <?= $cita['estado'] === 'completada' ? 'style="opacity: 0.5; cursor: not-allowed;"' : '' ?>
                                            title="<?= $cita['estado'] === 'completada' ? 'No se puede editar' : 'Editar cita' ?>">
                                            <i class="fa-solid fa-pen-to-square"></i>
                                        </div>

                                        <!-- Botón completar -->
                                        <div class="completar <?= $cita['estado'] !== 'pendiente' ? 'disabled' : '' ?>"
                                            onclick="<?= $cita['estado'] === 'pendiente' ? "confirmChangeStatus({$cita['id']}, 'completada')" : '' ?>"
                                            style="<?= $cita['estado'] === 'completada' ? 'color: #00dd0b;' : ($cita['estado'] !== 'pendiente' ? 'opacity: 0.5; cursor: not-allowed;' : '') ?>"
                                            title="<?= $cita['estado'] === 'pendiente' ? 'Marcar como completada' : 'No disponible' ?>">
                                            <i class="fa-solid fa-check"></i>
                                        </div>

                                        <!-- Botón cancelar -->
                                        <div class="cancelar <?= $cita['estado'] !== 'pendiente' ? 'disabled' : '' ?>"
                                            onclick="<?= $cita['estado'] === 'pendiente' ? "confirmChangeStatus({$cita['id']}, 'cancelada')" : '' ?>"
                                            style="<?= $cita['estado'] === 'cancelada' ? 'color: #ff9900;' : ($cita['estado'] !== 'pendiente' ? 'opacity: 0.5; cursor: not-allowed;' : '') ?>"
                                            title="<?= $cita['estado'] === 'pendiente' ? 'Cancelar cita' : 'No disponible' ?>">
                                            <i class="fa-solid fa-ban"></i>
                                        </div>

                                        <!-- Botón eliminar -->
                                        <div class="eliminar <?= $cita['estado'] === 'completada' ? 'disabled' : '' ?>"
                                            onclick="<?= $cita['estado'] !== 'completada' ? "confirmDelete({$cita['id']})" : '' ?>"
                                            style="<?= $cita['estado'] === 'completada' ? 'opacity: 0.5; cursor: not-allowed;' : 'color: #dc3545;' ?>"
                                            title="<?= $cita['estado'] === 'completada' ? 'No se puede eliminar' : 'Eliminar cita' ?>">
                                            <i class="fa-solid fa-trash"></i>
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

    <div class="btn-nuevo" onclick="location.href='nueva_cita.php'">
        <i class="fa-solid fa-plus"></i>
    </div>

    <?php include_once '../../includes/popup.php'; ?>

    <script src="../../scripts/citas/lista.js"></script>
    <script>
        function aplicarFiltros() {
            const estado = document.getElementById('filtro-estado').value;
            const tipo = document.getElementById('filtro-tipo').value;
            const fechaDesde = document.getElementById('filtro-fecha-desde').value;
            const fechaHasta = document.getElementById('filtro-fecha-hasta').value;
            
            const filas = document.querySelectorAll('.fila-cita');
            
            filas.forEach(fila => {
                let mostrar = true;
                
                // Filtro por estado
                if (estado !== 'todos' && fila.dataset.estado !== estado) {
                    mostrar = false;
                }
                
                // Filtro por tipo
                if (tipo !== 'todos' && fila.dataset.tipo !== tipo) {
                    mostrar = false;
                }
                
                // Filtro por fecha
                const fechaCita = fila.dataset.fecha;
                if (fechaDesde && fechaCita < fechaDesde) {
                    mostrar = false;
                }
                if (fechaHasta && fechaCita > fechaHasta) {
                    mostrar = false;
                }
                
                fila.style.display = mostrar ? '' : 'none';
            });
        }

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
                    window.location.reload();
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
                    window.location.reload();
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