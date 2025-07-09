<?php
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);
// $ROOT = $_SERVER['DOCUMENT_ROOT'];

$ROOT = '../../';
$TITULO = "Clientes";

include_once $ROOT . '/db/conexion.php';
include_once $ROOT . '/includes/sesion.php';
include_once $ROOT . '/includes/config.php';

if (!tieneSesion()) {
    header("Location: $URL_ROOT/login");
    exit();
}

$usuarios = [];
$sql = "SELECT 
    c.* 
FROM clientes c 
ORDER BY c.nombre ASC";

$clientes = [];
$result = mysqli_query($conn, $sql);
while ($row = mysqli_fetch_assoc($result)) {
    $clientes[] = $row;
}

?>

<!DOCTYPE html>
<html>

<head>
    <?php include_once $ROOT . '/includes/head.php'; ?>
    <script src="<?php $ROOT ?>/js/buscador.js?cache=<?php echo uniqid(); ?>"></script>
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
                        <th>Nombre</th>
                        <th>Teléfono</th>
                        <th>Email</th>
                        <th>Dirección</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($clientes)): ?>
                        <tr>
                            <td colspan="8">No hay clientes registrados.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($clientes as $cliente) { ?>
                            <tr>
                                <td><?= htmlspecialchars($cliente['nombre']) ?></td>
                                <td><?= htmlspecialchars($cliente['telefono']) ?></td>
                                <td><?= htmlspecialchars($cliente['email']) ?></td>
                                <td><?= htmlspecialchars($cliente['direccion']) ?></td>
                                <td>
                                    <div class="opciones-tabla-lista">
                                        <div class="opcion-tabla-lista editar" onclick="location.href='../clientes/editar.php?id=<?= $cliente['id'] ?>'">
                                            <i class="fa-solid fa-pen-to-square"></i>
                                        </div>
                                        <div class="opcion-tabla-lista eliminar" onclick="changeStatus(<?= $cliente['id'] ?>)">
                                            <i class="fa-solid fa-trash"></i>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                        <?php } ?>
                    <?php endif; ?>
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

    function changeStatus(id) {
        if (!confirm("¿Está seguro que desea eliminar al cliente?")) return false;

        displayPopUp();

        $.post('<?php echo $ROOT ?>/php/clientes/eliminar.php', {
                id: id
            })
            .done(function(data) {
                let respuesta = JSON.parse(data);

                if (respuesta.status == 0) displayMensajeError(respuesta.mensaje);
                else window.location.reload();
            })
            .fail(function() {
                displayMensajeError("Error de conexión, favor de intentarlo nuevamente.");
            });
    }
</script>

</html>