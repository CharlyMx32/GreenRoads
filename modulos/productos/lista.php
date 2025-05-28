<?php
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);
// $ROOT = $_SERVER['DOCUMENT_ROOT'];

$ROOT = '../..';
$TITULO = "Productos";

include_once $ROOT . '/db/conexion.php';
include_once $ROOT . '/includes/sesion.php';
include_once $ROOT . '/includes/config.php';

if (!tieneSesion()) {
    header("Location: $URL_ROOT/login");
    exit();
}

$productos = [];
$sql = "SELECT
    *
    FROM productos
    WHERE estado <> 'eliminado'
    ORDER BY estado ASC, nombre ASC";
$result = mysqli_query($conn, $sql);
while ($row = mysqli_fetch_assoc($result)) {
    $productos[] = $row;
}
?>

<!DOCTYPE html>
<html>

<head>
    <?php include_once $ROOT . '/includes/head.php'; ?>

    <!-- LINKS JS INTERNO -->
    <script src="<?php echo $ROOT ?>/../js/buscador.js?cache=<?php echo uniqid(); ?>"></script>
</head>

<body>
    <?php
    // HEADER
    $headerParams = [
        "buscador" => true,
        "btn_atras" => 'window.history.back()'
    ];
    // include_once $ROOT.'/includes/header.php';
    include_once '../../includes/header.php';
    ?>

    <div class="content">
        <div class="contenedor-tabla" style="max-height: calc(100vh - 200px); margin-bottom: 60px;">
            <table class="tabla-lista">
                <thead>
                    <tr>
                        <th>Imagen</th>
                        <th>Nombre</th>
                        <th>Precio unitario</th>
                        <th>Estado</th>
                        <th>Acciones</th>
                    </tr>
                </thead>

                <tbody>
                    <?php if (empty($productos)) {  ?>
                        <tr>
                            <td colspan="5">No se encontraron registros.</td>
                        </tr>
                        <?php } else {
                        foreach ($productos as $producto) { ?>
                            <tr>
                                <td>
                                    <?php if ($producto['imagen'] == '') { ?>
                                        Sin asignar
                                    <?php } else { ?>
                                        <img style="width: 70px;" src="../../img/productos/<?php echo $producto['imagen']; ?>?nocache=<?php echo uniqid(); ?>" alt="Imagen <?php echo $producto['nombre']; ?>" class="imagen-tabla">
                                    <?php } ?>
                                </td>
                                <td><?php echo $producto['nombre']; ?></td>
                                <td>$<?php echo number_format($producto['precio_unitario'], 2); ?></td>
                                <td><?php echo $producto['estado']; ?></td>
                                <td>
                                    <div class="opciones-tabla-lista">
                                        <div class="editar" onclick="location.href='<?php echo $ROOT ?>/productos/informacion?p=<?php echo $producto['id'] ?>'"><i class="fa-solid fa-pen-to-square"></i></div>
                                        <div class="power"
                                            onclick="changeStatus(<?= $producto['id'] ?>, '<?= ($producto['estado'] == 'activo' ? 'deshabilitado' : 'activo') ?>')"
                                            <?php if ($producto['estado'] == 'activo') echo 'style="color: #00dd0b;"'; ?>>
                                            <i class="fa-solid fa-power-off"></i>
                                        </div>
                                        <div class="eliminar" onclick="changeStatus(<?php echo $producto['id'] ?>, 'eliminado')"><i class="fa-solid fa-trash"></i></div>
                                    </div>
                                </td>
                            </tr>
                    <?php }
                    } ?>
                </tbody>
            </table>
        </div>
    </div>

    <div class="btn-nuevo" onclick="location.href='agregar'"><i class="fa-solid fa-plus"></i></div>

    <!-- POPUP -->
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
            '<?php echo $ROOT ?>/../php/productos/cambiar_estado.php', // Ruta correcta
            {
                id: id,
                status: status
            }, // Datos
            function(respuesta) { // jQuery ya lo entrega como OBJETO
                if (respuesta.status == 0)
                    displayMensajeError(respuesta.mensaje);
                else
                    window.location.reload();
            },
            'json' // <-- fuerza a jQuery a parsear JSON
        ).fail(function() {
            displayMensajeError("Error de conexión, favor de intentarlo nuevamente.");
        });
    }
</script>

</html>