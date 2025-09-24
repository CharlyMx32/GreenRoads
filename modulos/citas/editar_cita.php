<?php
$ROOT = '../..';
$TITULO = "Editar Cita";

include_once "$ROOT/db/conexion.php";
include_once "$ROOT/includes/sesion.php";
include_once "$ROOT/includes/config.php";

if (!tieneSesion()) {
    header("Location: $URL_ROOT/login");
    exit();
}

if (!puedeEditarCitas()) {
    header("Location: ../dashboard/menu.php?error=sin_permisos");
    exit();
}

// Obtener ID de la cita
$id_cita = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$id_cita) {
    header("Location: lista.php");
    exit();
}

// Obtener datos de la cita
$sql_cita = "SELECT * FROM citas WHERE id = ? AND estado != 'eliminado'";
$stmt_cita = mysqli_prepare($conn, $sql_cita);
mysqli_stmt_bind_param($stmt_cita, 'i', $id_cita);
mysqli_stmt_execute($stmt_cita);
$result_cita = mysqli_stmt_get_result($stmt_cita);
$cita = mysqli_fetch_assoc($result_cita);

if (!$cita) {
    header("Location: lista.php");
    exit();
}

// Verificar permisos
if ($cita['id_admin'] != $_SESSION['usuario'] && !esAdmin()) {
    header("Location: lista.php");
    exit();
}

// No permitir editar citas completadas
if ($cita['estado'] === 'completada') {
    header("Location: detalle_cita.php?id=$id_cita");
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
        "titulo" => $TITULO . " #" . $cita['id'],
        "btn_atras" => "window.location.href='lista.php'"
    ];
    include_once '../../includes/header.php';
    ?>

    <div class="content">
        <div class="form-container">
            <div class="form-header">
                <h2><i class="fa-solid fa-pen-to-square"></i> Editar Cita #<?= $cita['id'] ?></h2>
                <p>Modifique la información de la cita según sea necesario</p>
            </div>

            <div class="form-content">
                <div class="form-section">
                    <form id="form-editar-cita" class="form-cita">
                        <input type="hidden" name="id" value="<?= $cita['id'] ?>">

                        <div class="form-row">
                            <div class="form-group">
                                <label for="id_cliente">Cliente *</label>
                                <select id="id_cliente" name="id_cliente" required>
                                    <option value="">Seleccionar cliente...</option>
                                    <?php foreach ($clientes as $cliente): ?>
                                        <option value="<?= $cliente['id'] ?>" 
                                                <?= $cliente['id'] == $cita['id_cliente'] ? 'selected' : '' ?>
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
                                    <option value="medicion" <?= $cita['tipo_cita'] === 'medicion' ? 'selected' : '' ?>>Medición</option>
                                    <option value="consulta" <?= $cita['tipo_cita'] === 'consulta' ? 'selected' : '' ?>>Consulta</option>
                                    <option value="instalacion" <?= $cita['tipo_cita'] === 'instalacion' ? 'selected' : '' ?>>Instalación</option>
                                    <option value="mantenimiento" <?= $cita['tipo_cita'] === 'mantenimiento' ? 'selected' : '' ?>>Mantenimiento</option>
                                    <option value="seguimiento" <?= $cita['tipo_cita'] === 'seguimiento' ? 'selected' : '' ?>>Seguimiento</option>
                                    <option value="otro" <?= $cita['tipo_cita'] === 'otro' ? 'selected' : '' ?>>Otro</option>
                                </select>
                            </div>
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label for="fecha_cita">Fecha de la cita *</label>
                                <input type="date" id="fecha_cita" name="fecha_cita" required 
                                       value="<?= $cita['fecha_cita'] ?>">
                            </div>

                            <div class="form-group">
                                <label for="hora_cita">Hora de la cita *</label>
                                <input type="time" id="hora_cita" name="hora_cita" required
                                       value="<?= substr($cita['hora_cita'], 0, 5) ?>">
                            </div>
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label for="estado">Estado de la cita</label>
                                <select id="estado" name="estado">
                                    <option value="pendiente" <?= $cita['estado'] === 'pendiente' ? 'selected' : '' ?>>Pendiente</option>
                                    <option value="completada" <?= $cita['estado'] === 'completada' ? 'selected' : '' ?>>Completada</option>
                                    <option value="cancelada" <?= $cita['estado'] === 'cancelada' ? 'selected' : '' ?>>Cancelada</option>
                                </select>
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="direccion_cita">Dirección de la cita *</label>
                            <textarea id="direccion_cita" name="direccion_cita" 
                                      placeholder="Ingrese la dirección completa donde se realizará la cita" 
                                      required rows="3" style="height: auto;"><?= htmlspecialchars($cita['direccion_cita']) ?></textarea>
                        </div>

                        <div class="form-group">
                            <label for="descripcion">Descripción / Notas</label>
                            <textarea id="descripcion" name="descripcion" 
                                      placeholder="Detalles adicionales sobre la cita (opcional)" 
                                      rows="4" style="height: auto;"><?= htmlspecialchars($cita['descripcion'] ?? '') ?></textarea>
                        </div>

                        <div class="form-actions">
                            <button type="button" class="btn-cancelar" onclick="window.location.href='lista.php'">
                                Cancelar
                            </button>
                            <button type="submit" class="btn-guardar">
                                <i class="fa-solid fa-save"></i>
                                Actualizar Cita
                            </button>
                        </div>
                    </form>
                </div>

                <div class="info-section">
                    <div id="info-cliente" class="info-panel show" style="display: block;">
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

    <script src="../../scripts/citas/editar_cita.js"></script>
    <script>
        // Mostrar información inicial del cliente
        document.addEventListener('DOMContentLoaded', function() {
            const selectCliente = document.getElementById('id_cliente');
            const option = selectCliente.options[selectCliente.selectedIndex];
            
            if (option.value) {
                document.getElementById('cliente-telefono').textContent = option.dataset.telefono || 'No disponible';
                document.getElementById('cliente-email').textContent = option.dataset.email || 'No disponible';
            }
        });

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

        // Manejar envío del formulario
        document.getElementById('form-editar-cita').addEventListener('submit', function(e) {
            e.preventDefault();
            
            // Validaciones adicionales
            const fecha = document.getElementById('fecha_cita').value;
            const hora = document.getElementById('hora_cita').value;
            const estado = document.getElementById('estado').value;
            
            if (!fecha || !hora) {
                alert('Por favor complete la fecha y hora de la cita');
                return;
            }
            
            // Verificar que la fecha/hora no sea en el pasado para citas pendientes
            if (estado === 'pendiente') {
                const fechaHoraCita = new Date(fecha + 'T' + hora);
                const ahora = new Date();
                
                if (fechaHoraCita < ahora) {
                    if (!confirm('La fecha y hora seleccionada ya pasó. ¿Desea continuar?')) {
                        return;
                    }
                }
            }
            
            displayPopUp();
            
            const formData = new FormData(this);
            
            fetch('../../php/citas/editar.php', {
                method: 'POST',
                body: formData
            })
            .then(response => {
                if (!response.ok) throw new Error('Error en la red');
                return response.json();
            })
            .then(data => {
                if (data.success) {
                    displayMensajeExitoso('Cita actualizada exitosamente', "window.location.href = 'lista.php'");
                } else {
                    displayMensajeError(data.message || 'Error al actualizar la cita');
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