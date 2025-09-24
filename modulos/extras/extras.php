<?php
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

$ROOT = '../..';
$TITULO = "Gestión de Extras";

include_once $ROOT . '/db/conexion.php';
include_once $ROOT . '/includes/sesion.php';
include_once $ROOT . '/includes/config.php';

if (!tieneSesion()) {
    header("Location: $URL_ROOT/login");
    exit();
}

if (!puedeVerExtras()) {
    header("Location: ../dashboard/menu.php?error=sin_permisos");
    exit();
}

// Obtener todos los extras (excluyendo los eliminados)
$extras = [];
$sql = "SELECT * FROM extras WHERE estado <> 'eliminado' ORDER BY 
        CASE estado 
            WHEN 'activo' THEN 1 
            WHEN 'inactivo' THEN 2 
            ELSE 3 
        END, nombre ASC";
$result = mysqli_query($conn, $sql);
while ($row = mysqli_fetch_assoc($result)) {
    $extras[] = $row;
}
mysqli_close($conn);
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <?php include_once $ROOT . '/includes/head.php'; ?>
    <link rel="stylesheet" href="../../css/extras/extras.css">
</head>
<body>
    <?php
    $headerParams = [
        "titulo" => $TITULO,
        "buscador" => true,
        "btn_atras" => "window.history.back()"
    ];
    //include_once $ROOT . '/includes/header.php';
    include_once '../../includes/header.php';
    ?>

    <!-- Contenido principal -->
    <div class="content">
        <div class="contenedor-tabla" style="max-height: calc(100vh - 200px); margin-bottom: 60px;">
            <table class="tabla-lista">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Nombre</th>
                        <th>Precio</th>
                        <th>Descripción</th>
                        <th>Estado</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($extras)): ?>
                        <tr>
                            <td colspan="6">No se encontraron extras registrados.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($extras as $extra): ?>
                            <tr>
                                <td><?php echo $extra['id']; ?></td>
                                <td><?php echo htmlspecialchars($extra['nombre']); ?></td>
                                <td>$<?php echo number_format($extra['precio'], 2); ?></td>
                                <td><?php echo !empty($extra['descripcion']) ? htmlspecialchars($extra['descripcion']) : 'Sin descripción'; ?></td>
                                <td>
                                    <span class="estado-badge <?php echo $extra['estado']; ?>">
                                        <?php echo ucfirst($extra['estado']); ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="opciones-tabla-lista">
                                        <!-- Editar -->
                                        <div class="editar" onclick="location.href='editar?id=<?php echo $extra['id']; ?>'">
                                            <i class="fa-solid fa-pen-to-square"></i>
                                        </div>
                                        
                                        <!-- Cambiar estado -->
                                        <div class="power" 
                                             onclick="cambiarEstado(<?php echo $extra['id']; ?>, '<?php echo $extra['estado'] === 'activo' ? 'inactivo' : 'activo'; ?>')"
                                             style="color: <?php echo $extra['estado'] === 'activo' ? '#00dd0b' : '#ccc'; ?>">
                                            <i class="fa-solid fa-power-off"></i>
                                        </div>
                                        
                                        <!-- Eliminar -->
                                        <div class="eliminar" onclick="eliminarExtra(<?php echo $extra['id']; ?>)">
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


    <div class="btn-nuevo" onclick="location.href='agregar'">
        <i class="fa-solid fa-plus"></i>
    </div>

    <?php include_once '../../includes/popup.php'; ?>
    <script src="../../scripts/extras/extras.js"></script>
    <script>

        function cambiarEstado(id, nuevoEstado) {
            const accion = nuevoEstado === 'activo' ? 'habilitar' : 'deshabilitar';
            const mensaje = `¿Está seguro que desea ${accion} este extra?`;
            
            if (!confirm(mensaje)) return;
            
            displayPopUp();
            
            $.post(
                '../../php/extras/cambiar_estado.php', {
                    id: id,
                    estado: nuevoEstado
                },
                function(respuesta) {
                    if (respuesta.status == 0) {
                        displayMensajeError(respuesta.mensaje);
                    } else {
                        window.location.reload();
                    }
                },
                'json'
            ).fail(function() {
                displayMensajeError("Error de conexión, favor de intentarlo nuevamente.");
            });
        }

        function eliminarExtra(id) {
            if (!confirm('¿Está seguro que desea eliminar este extra? Esta acción no se puede deshacer.')) {
                return;
            }
            
            displayPopUp();
            
            $.post(
                '../../php/extras/eliminar.php', {
                    id: id
                },
                function(respuesta) {
                    if (respuesta.status == 0) {
                        displayMensajeError(respuesta.mensaje);
                    } else {
                        window.location.reload();
                    }
                },
                'json'
            ).fail(function() {
                displayMensajeError("Error de conexión, favor de intentarlo nuevamente.");
            });
        }
    </script>
</body>
</html>