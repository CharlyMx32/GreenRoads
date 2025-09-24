<?php
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

$ROOT = '../..';
$TITULO = "Productos";

include_once $ROOT . '/db/conexion.php';
include_once $ROOT . '/includes/sesion.php';
include_once $ROOT . '/includes/config.php';

if (!tieneSesion()) {
    header("Location: $URL_ROOT/login");
    exit();
}

if (!puedeVerProductos()) {
    header("Location: ../dashboard/menu.php?error=sin_permisos");
    exit();
}

$productos = [];
$sql = "SELECT p.*, u.simbolo as unidad_medida, tp.nombre as tipo_producto 
        FROM productos p
        LEFT JOIN unidades u ON p.id_unidad = u.id
        LEFT JOIN tipo_productos tp ON p.id_tipo_producto = tp.id
        WHERE p.estado <> 'eliminado' 
        ORDER BY p.estado ASC, p.nombre ASC";
$result = mysqli_query($conn, $sql);
while ($row = mysqli_fetch_assoc($result)) {
    $productos[] = $row;
}
?>

<!DOCTYPE html>
<html>

<head>
    <?php include_once $ROOT . '/includes/head.php'; ?>

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
                        <th>Imagen</th>
                        <th>Nombre</th>
                        <th>Tipo</th>
                        <th>Estado</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($productos)): ?>
                        <tr>
                            <td colspan="5">No se encontraron registros.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($productos as $producto): ?>
                            <tr>
                                <!-- Columna de imagen -->
                                <td>
                                    <?php if (empty($producto['imagen'])): ?>
                                        Sin asignar
                                    <?php else: ?>
                                        <img style="width: 70px;"
                                            src="../../img/productos/<?php echo $producto['imagen']; ?>?nocache=<?php echo uniqid(); ?>"
                                            alt="Imagen <?php echo $producto['nombre']; ?>"
                                            class="imagen-tabla">
                                    <?php endif; ?>
                                </td>

                                <!-- Nombre del producto -->
                                <td><?php echo $producto['nombre']; ?></td>

                                <!-- Tipo del producto -->
                                <td><?php echo ucfirst($producto['tipo_producto']); ?></td>

                                <!-- Estado -->
                                <td><?php echo $producto['estado']; ?></td>

                                <!-- Acciones -->
                                <td>
                                    <div class="opciones-tabla-lista">
                                        <div class="editar"
                                            onclick="location.href='<?php echo $ROOT ?>/productos/editar_producto?p=<?php echo $producto['id'] ?>'">
                                            <i class="fa-solid fa-pen-to-square"></i>
                                        </div>

                                        <div class="power"
                                            onclick="changeStatus(<?= $producto['id'] ?>, '<?= ($producto['estado'] == 'activo' ? 'deshabilitado' : 'activo') ?>')"
                                            <?php if ($producto['estado'] == 'activo') echo 'style="color: #00dd0b;"'; ?>>
                                            <i class="fa-solid fa-power-off"></i>
                                        </div>

                                        <div class="eliminar"
                                            onclick="changeStatus(<?php echo $producto['id'] ?>, 'eliminado')">
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

    <div class="btn-nuevo" onclick="location.href='agregar_producto'">
        <i class="fa-solid fa-plus"></i>
    </div>

    <!-- Popup para mensajes -->
    <?php include_once '../../includes/popup.php'; ?>
</body>

<script>
    setSearcher({
        input: ".textfield-buscador-navegador",
        search_element: "table tbody tr",
        display_type: "table-row"
    });

    function changeStatus(id, status) {
        let alertMsg;
        if (status === 'deshabilitado')
            alertMsg = "¿Está seguro que desea deshabilitar el producto?";
        else if (status === 'activo')
            alertMsg = "¿Está seguro que desea habilitar el producto?";
        else if (status === 'eliminado')
            alertMsg = "¿Está seguro que desea eliminar el producto?";

        if (!confirm(alertMsg)) return;

        displayPopUp();

        $.post(
            '<?php echo $ROOT ?>/../php/productos/cambiar_estado.php', {
                id: id,
                status: status
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

</html>