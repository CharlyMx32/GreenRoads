<?php
$ROOT = '../..';
$TITULO = "Nueva Cita";

include_once "$ROOT/db/conexion.php";
include_once "$ROOT/includes/sesion.php";
include_once "$ROOT/includes/config.php";

if (!tieneSesion()) {
    header("Location: $URL_ROOT/login");
    exit();
}

if (!puedeCrearCitas()) {
    header("Location: ../dashboard/menu.php?error=sin_permisos");
    exit();
}

// Obtener clientes activos para el selector
$clientes = [];
$sql_clientes = "SELECT id, nombre, telefono, email FROM clientes WHERE estado = 'activo' ORDER BY nombre ASC";
$result_clientes = mysqli_query($conn, $sql_clientes);
while ($row = mysqli_fetch_assoc($result_clientes)) {
    $clientes[] = $row;
}
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
        "titulo" => $TITULO,
        "btn_atras" => "window.location.href='lista.php'"
    ];
    include_once '../../includes/header.php';
    ?>

    <div class="content">
        <div class="form-container">
            <div class="form-header">
                <h2><i class="fa-solid fa-calendar-plus"></i> Nueva Cita</h2>
                <p>Complete la información para agendar una nueva cita</p>
            </div>

            <div class="form-content">
                <div class="form-section">
                    <form id="form-nueva-cita" class="form-cita">
                        <div class="form-row">
                            <div class="form-group">
                                <label for="id_cliente">Cliente *</label>
                                <select id="id_cliente" name="id_cliente" required>
                                    <option value="">Seleccionar cliente...</option>
                                    <?php foreach ($clientes as $cliente): ?>
                                        <option value="<?= $cliente['id'] ?>"
                                            data-telefono="<?= htmlspecialchars($cliente['telefono']) ?>"
                                            data-email="<?= htmlspecialchars($cliente['email']) ?>">
                                            <?= htmlspecialchars($cliente['nombre']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="form-group">
                                <label for="tipo_cita">Tipo de cita *</label>
                                <select id="tipo_cita" name="tipo_cita" required>
                                    <option value="">Seleccionar tipo...</option>
                                    <option value="medicion">Medición</option>
                                    <option value="consulta">Consulta</option>
                                    <option value="instalacion">Instalación</option>
                                    <option value="mantenimiento">Mantenimiento</option>
                                    <option value="seguimiento">Seguimiento</option>
                                    <option value="otro">Otro</option>
                                </select>
                            </div>
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label for="fecha_cita">Fecha de la cita *</label>
                                <input type="date" id="fecha_cita" name="fecha_cita" required
                                    min="<?= date('Y-m-d') ?>">
                            </div>

                            <div class="form-group">
                                <label for="hora_cita">Hora de la cita *</label>
                                <input type="time" id="hora_cita" name="hora_cita" required>
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="direccion_cita">Dirección de la cita *</label>
                            <textarea id="direccion_cita" name="direccion_cita"
                                placeholder="Ingrese la dirección completa donde se realizará la cita"
                                required rows="3" style="height: auto;"></textarea>
                        </div>

                        <div class="form-group">
                            <label for="descripcion">Descripción / Notas</label>
                            <textarea id="descripcion" name="descripcion"
                                placeholder="Detalles adicionales sobre la cita (opcional)"
                                rows="4" style="height: auto;"></textarea>
                        </div>

                        <div class="form-actions">
                            <button type="button" class="btn-cancelar" onclick="window.location.href='lista.php'">
                                Cancelar
                            </button>
                            <button type="submit" class="btn-guardar">
                                <i class="fa-solid fa-save"></i>
                                Crear Cita
                            </button>
                        </div>
                    </form>
                </div>

                <div class="info-section">
                    <div id="info-cliente" class="info-panel" style="display: none;">
                        <h4>Información del Cliente</h4>
                        <div class="info-item">
                            <strong>Teléfono</strong>
                            <span id="cliente-telefono"></span>
                        </div>
                        <div class="info-item">
                            <strong>Email</strong>
                            <span id="cliente-email"></span>
                        </div>

                    </div>
                </div>
            </div>
        </div>
    </div>

    <?php include_once '../../includes/popup.php'; ?>

    <script src="../../scripts/citas/nueva_cita.js"></script>
    <script>
        // Mostrar información del cliente seleccionado
        document.getElementById('id_cliente').addEventListener('change', function() {
            const option = this.options[this.selectedIndex];
            const infoPanel = document.getElementById('info-cliente');

            if (option.value) {
                document.getElementById('cliente-telefono').textContent = option.dataset.telefono || 'No disponible';
                document.getElementById('cliente-email').textContent = option.dataset.email || 'No disponible';
                infoPanel.classList.add('show');
                infoPanel.style.display = 'block';
            } else {
                infoPanel.classList.remove('show');
                infoPanel.style.display = 'none';
            }
        });

        // Validar fecha mínima
        document.getElementById('fecha_cita').addEventListener('change', function() {
            const fechaSeleccionada = new Date(this.value);
            const hoy = new Date();
            hoy.setHours(0, 0, 0, 0);

            if (fechaSeleccionada < hoy) {
                alert('No se pueden agendar citas en fechas pasadas');
                this.value = '';
            }
        });

        // Manejar envío del formulario
        document.getElementById('form-nueva-cita').addEventListener('submit', function(e) {
            e.preventDefault();

            // Validaciones adicionales
            const fecha = document.getElementById('fecha_cita').value;
            const hora = document.getElementById('hora_cita').value;

            if (!fecha || !hora) {
                alert('Por favor complete la fecha y hora de la cita');
                return;
            }

            // Verificar que la fecha/hora no sea en el pasado
            const fechaHoraCita = new Date(fecha + 'T' + hora);
            const ahora = new Date();

            if (fechaHoraCita < ahora) {
                alert('No se pueden agendar citas en fechas y horas pasadas');
                return;
            }

            displayPopUp();

            const formData = new FormData(this);

            fetch('../../php/citas/agregar.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => {
                    if (!response.ok) throw new Error('Error en la red');
                    return response.json();
                })
                .then(data => {
                    if (data.success) {
                        displayMensajeExitoso('Cita creada exitosamente', "window.location.href = 'lista.php'");
                        document.getElementById('form-nueva-cita').reset()
                    } else {
                        displayMensajeError(data.message || 'Error al crear la cita');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    displayMensajeError('Error de conexión. Intente nuevamente.');
                });
        });
    </script>
</body>

</html>