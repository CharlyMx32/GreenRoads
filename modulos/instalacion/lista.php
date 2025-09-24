<?php
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

$ROOT = '../..';
$TITULO = "Instalaciones";

include_once "$ROOT/db/conexion.php";
include_once "$ROOT/includes/sesion.php";
include_once "$ROOT/includes/config.php";

if (!tieneSesion()) {
    header("Location: $URL_ROOT/login");
    exit();
}

if (!puedeVerInstalaciones()) {
    header("Location: ../dashboard/menu.php?error=sin_permisos");
    exit();
}

// Obtener instalaciones según el rol del usuario
$instalaciones = [];
$sql = "
    SELECT 
        i.id,
        i.id_cotizacion,
        i.estado,
        i.fecha_inicio,
        i.fecha_fin_estimada,
        i.fecha_fin_real,
        i.progreso_porcentaje,
        i.tecnico_responsable,
        c.area_total,
        c.area_total as area_cotizacion,
        c.total as precio_total,
        c.tipo_terreno,
        c.tipo_instalacion,
        c.fecha as fecha_cotizacion,
        cli.nombre AS nombre_cliente,
        cli.telefono,
        cli.email,
        c.direccion as direccion_instalacion,
        COALESCE(a.nombre, 'Sin asignar') AS nombre_admin,
        COALESCE(a.apellido, '') AS apellido_admin,
        COALESCE(CONCAT(t.nombre, ' ', t.apellido), 'Sin asignar') AS nombre_trabajador
    FROM instalaciones i
    INNER JOIN cotizaciones c ON i.id_cotizacion = c.id
    LEFT JOIN clientes cli ON c.id_cliente = cli.id
    LEFT JOIN admins a ON c.id_admin = a.id 
    LEFT JOIN admins t ON i.tecnico_responsable = t.id";

// Si es instalador, solo ver sus instalaciones asignadas
if (esInstalador()) {
    $sql .= " WHERE i.tecnico_responsable = " . $_SESSION['usuario'];
}

$sql .= " ORDER BY 
        CASE i.estado 
            WHEN 'en_progreso' THEN 1
            WHEN 'planificada' THEN 2
            WHEN 'completada' THEN 3
            WHEN 'cancelada' THEN 4
        END,
        i.fecha_inicio ASC,
        i.id DESC
";

$result = mysqli_query($conn, $sql);
while ($row = mysqli_fetch_assoc($result)) {
    $instalaciones[] = $row;
}
?>

<!DOCTYPE html>
<html>

<head>
    <?php include_once "$ROOT/includes/head.php"; ?>
    <link rel="stylesheet" href="../../css/instalaciones/instalaciones.css">
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
                        <th>Cotización</th>
                        <th>Estado</th>
                        <th>Progreso</th>
                        <th>Área (m²)</th>
                        <th>Trabajador</th>
                        <th>Fecha Inicio</th>
                        <th>Precio</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($instalaciones)): ?>
                        <tr>
                            <td colspan="10">No hay instalaciones registradas.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($instalaciones as $instalacion): ?>
                            <tr>
                                <td>#<?= $instalacion['id'] ?></td>
                                <td>
                                    <div style="font-weight: 600;"><?= htmlspecialchars($instalacion['nombre_cliente'] ?? '') ?></div>
                                    <div style="font-size: 0.8rem; color: #666;"><?= htmlspecialchars($instalacion['telefono'] ?? '') ?></div>
                                </td>
                                <td>#<?= $instalacion['id_cotizacion'] ?></td>
                                <td>
                                    <span class="estado-instalacion <?= $instalacion['estado'] ?>">
                                        <?= ucfirst(str_replace('_', ' ', $instalacion['estado'])) ?>
                                    </span>
                                </td>
                                <td>
                                    <div style="display: flex; align-items: center; gap: 10px;">
                                        <div style="background: #e0e0e0; height: 6px; width: 60px; border-radius: 3px; overflow: hidden;">
                                            <div style="background: #4CAF50; height: 100%; width: <?= $instalacion['progreso_porcentaje'] ?>%; transition: width 0.3s ease;"></div>
                                        </div>
                                        <span style="font-size: 0.8rem; font-weight: 500;"><?= number_format($instalacion['progreso_porcentaje'], 0) ?>%</span>
                                    </div>
                                </td>
                                <td><?= number_format($instalacion['area_cotizacion'] ?? 0, 2) ?></td>
                                <td><?= htmlspecialchars($instalacion['nombre_trabajador'] ?? 'Sin asignar') ?></td>
                                <td><?= $instalacion['fecha_inicio'] ? date('d/m/Y', strtotime($instalacion['fecha_inicio'])) : 'Sin programar' ?></td>
                                <td>$<?= number_format((float)$instalacion['precio_total'], 2) ?></td>
                                <td>
                                    <div class="opciones-tabla-lista">
                                        <!-- Botón ver instalación -->
                                        <div class="ver-detalle" 
                                            onclick="location.href='detalle_instalacion.php?id=<?= $instalacion['id'] ?>'"
                                            title="Ver instalación"
                                            style="color: #4CAF50;">
                                            <i class="fa-solid fa-tools"></i>
                                        </div>

                                        <!-- Botón editar -->
                                        <div class="editar <?= $instalacion['estado'] == 'completada' || $instalacion['estado'] == 'cancelada' ? 'disabled' : '' ?>"
                                            onclick="<?= $instalacion['estado'] != 'completada' && $instalacion['estado'] != 'cancelada' ? "abrirModalEdicion({$instalacion['id']})" : '' ?>"
                                            <?= $instalacion['estado'] == 'completada' || $instalacion['estado'] == 'cancelada' ? 'style="opacity: 0.5; cursor: not-allowed;"' : '' ?>
                                            title="Editar instalación">
                                            <i class="fa-solid fa-pen-to-square"></i>
                                        </div>

                                        <!-- Botón iniciar/continuar -->
                                        <?php if ($instalacion['estado'] == 'planificada'): ?>
                                        <div class="iniciar"
                                            onclick="abrirModalIniciarInstalacion(<?= $instalacion['id'] ?>, <?= $instalacion['id_cotizacion'] ?>, '<?= $instalacion['fecha_fin_estimada'] ?>')"
                                            title="Iniciar instalación"
                                            style="color: #ff9800;">
                                            <i class="fa-solid fa-play"></i>
                                        </div>
                                        <?php endif; ?>

                                        <!-- Botón completar -->
                                        <?php if ($instalacion['estado'] == 'en_progreso'): ?>
                                        <div class="completar"
                                            onclick="cambiarEstadoInstalacion(<?= $instalacion['id'] ?>, 'completada')"
                                            title="Completar instalación"
                                            style="color: #4CAF50;">
                                            <i class="fa-solid fa-check"></i>
                                        </div>
                                        <?php endif; ?>

                                        <!-- Botón cancelar -->
                                        <?php if ($instalacion['estado'] != 'completada' && $instalacion['estado'] != 'cancelada'): ?>
                                        <div class="cancelar"
                                            onclick="cambiarEstadoInstalacion(<?= $instalacion['id'] ?>, 'cancelada')"
                                            title="Cancelar instalación"
                                            style="color: #f44336;">
                                            <i class="fa-solid fa-times"></i>
                                        </div>
                                        <?php endif; ?>


                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Modal para iniciar instalación y seleccionar rollos -->
    <div id="modalIniciarInstalacion" class="modal-edicion">
        <div class="modal-content-edicion" style="max-width: 900px; max-height: 80vh; overflow-y: auto;">
            <div class="modal-header">
                <h3>Iniciar Instalación - Seleccionar Rollos del Inventario</h3>
                <span class="modal-close" onclick="cerrarModalIniciarInstalacion()">&times;</span>
            </div>
            
            <!-- Información de la instalación -->
            <div class="form-instalacion" style="background: #f8f9fa; padding: 15px; margin-bottom: 20px; border-radius: 5px;">
                <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 15px;">
                    <div class="form-group">
                        <label for="inicio_tecnico_responsable">Trabajador Responsable:</label>
                        <select id="inicio_tecnico_responsable" name="tecnico_responsable" required>
                            <option value="">Seleccionar trabajador...</option>
                            <?php
                            // Obtener trabajadores para el select
                            $sql_tecnicos = "SELECT a.id, CONCAT(a.nombre, ' ', a.apellido) as nombre_completo FROM admins a WHERE a.id_rol = 3 AND a.estado = 'activo' ORDER BY a.nombre ASC";
                            $result_tecnicos = mysqli_query($conn, $sql_tecnicos);
                            while ($tecnico = mysqli_fetch_assoc($result_tecnicos)) {
                                echo "<option value=\"{$tecnico['id']}\">{$tecnico['nombre_completo']}</option>";
                            }
                            ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="inicio_fecha_inicio">Fecha de Inicio:</label>
                        <input type="date" id="inicio_fecha_inicio" name="fecha_inicio" value="<?= date('Y-m-d') ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="inicio_fecha_fin_estimada">Fecha Fin Estimada:</label>
                        <input type="date" id="inicio_fecha_fin_estimada" name="fecha_fin_estimada" required>
                    </div>
                </div>
            </div>
            
            <div id="contenido-modal-instalacion">
                <div class="loading-spinner" style="text-align: center; padding: 20px;">
                    <i class="fa-solid fa-spinner fa-spin"></i> Cargando rollos disponibles...
                </div>
            </div>
            
            <div class="acciones-instalacion" style="margin-top: 20px; border-top: 1px solid #ddd; padding-top: 15px;">
                <button type="button" class="btn-accion btn-actualizar" onclick="procesarInicioInstalacion()" id="btnIniciarInstalacion" disabled>
                    <i class="fa-solid fa-play"></i> Iniciar Instalación
                </button>
                <button type="button" class="btn-accion btn-cancelar" onclick="cerrarModalIniciarInstalacion()">
                    <i class="fa-solid fa-times"></i> Cancelar
                </button>
            </div>
        </div>
    </div>

    <!-- Modal para edición rápida -->
    <div id="modalEdicion" class="modal-edicion">
        <div class="modal-content-edicion">
            <div class="modal-header">
                <h3>Editar Instalación</h3>
                <span class="modal-close" onclick="cerrarModalEdicion()">&times;</span>
            </div>
            <form id="formEditarInstalacion">
                <input type="hidden" id="edit_id_instalacion" name="id_instalacion">
                
                <div class="form-group">
                    <label for="edit_tecnico_responsable">Trabajador Responsable:</label>
                    <select id="edit_tecnico_responsable" name="tecnico_responsable">
                        <option value="">Sin asignar</option>
                        <?php
                        // Obtener trabajadores para el select
                        $sql_tecnicos = "SELECT a.id, CONCAT(a.nombre, ' ', a.apellido) as nombre_completo FROM admins a WHERE a.id_rol = 3 AND a.estado = 'activo' ORDER BY a.nombre ASC";
                        $result_tecnicos = mysqli_query($conn, $sql_tecnicos);
                        while ($tecnico = mysqli_fetch_assoc($result_tecnicos)) {
                            echo "<option value=\"{$tecnico['id']}\">{$tecnico['nombre_completo']}</option>";
                        }
                        ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="edit_fecha_inicio">Fecha de Inicio:</label>
                    <input type="date" id="edit_fecha_inicio" name="fecha_inicio">
                </div>
                
                <div class="form-group">
                    <label for="edit_fecha_fin_estimada">Fecha Fin Estimada:</label>
                    <input type="date" id="edit_fecha_fin_estimada" name="fecha_fin_estimada">
                </div>
                
                <div class="form-group">
                    <label for="edit_progreso">Progreso (%):</label>
                    <input type="number" id="edit_progreso" name="progreso_porcentaje" min="0" max="100" step="0.01">
                </div>
                
                <div class="form-group">
                    <label for="edit_observaciones">Observaciones:</label>
                    <textarea id="edit_observaciones" name="observaciones" placeholder="Notas adicionales sobre la instalación..."></textarea>
                </div>
                
                <div class="acciones-instalacion">
                    <button type="button" class="btn-accion btn-actualizar" onclick="guardarEdicion()">
                        <i class="fa-solid fa-save"></i> Guardar Cambios
                    </button>
                    <button type="button" class="btn-accion btn-cancelar" onclick="cerrarModalEdicion()">
                        <i class="fa-solid fa-times"></i> Cancelar
                    </button>
                </div>
            </form>
        </div>
    </div>

    <?php include_once '../../includes/popup.php'; ?>

    <script src="../../scripts/instalaciones/lista.js"></script>
</body>

</html>
