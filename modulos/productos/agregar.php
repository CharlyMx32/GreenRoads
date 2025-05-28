<?php
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);
// $ROOT = $_SERVER['DOCUMENT_ROOT'];
$ROOT = '../..';
$TITULO = "Nuevo producto";

include_once $ROOT . '/db/conexion.php';
include_once $ROOT . '/includes/sesion.php';
include_once $ROOT . '/includes/config.php';

if (!tieneSesion()) {
    header("Location: $URL_ROOT/login");
    exit();
}

// Obtener unidades para el select
$unidades = [];
$res = mysqli_query($conn, "SELECT id, nombre FROM unidades ORDER BY nombre ASC");
while ($row = mysqli_fetch_assoc($res)) $unidades[] = $row;
?>
<!DOCTYPE html>
<html>

<head>
    <?php include_once $ROOT . '/includes/head.php'; ?>
</head>

<body>
    <?php
    $headerParams = [
        "titulo" => $TITULO,
        "btn_atras" => "window.history.back()"
    ];
    include_once $ROOT . '/../includes/header.php';
    ?>

    <div class="formulario active">
        <div class="subtitulo-formulario">Información del producto</div>
        <div class="seccion-formulario">
            <form id="formProducto" enctype="multipart/form-data">
                    <input required type="text" class="textfield" name="nombre" id="nombre">
                    <label for="nombre" placeholder="Nombre *"></label>

                    <input type="number" class="textfield" name="precio_unitario" step="0.01">
                    <label placeholder="Precio unitario"></label>

                    <input type="number" class="textfield" name="ancho_metros" step="0.01">
                    <label placeholder="Ancho en metros"></label>

                    <select name="id_unidad" class="textfield" required>
                        <option value=""></option>
                        <?php foreach ($unidades as $unidad): ?>
                            <option value="<?= $unidad['id'] ?>"><?= $unidad['nombre'] ?></option>
                        <?php endforeach; ?>
                    </select>
                    <label placeholder="Unidad *"></label>

                    <input type="file" class="textfield" name="imagen" accept="image/*">
                    <label placeholder="Imagen del producto (opcional)"></label>
            </form>


            <div class="btnadd" onclick="agregar()">Agregar</div>
        </div>
    </div>

    <?php include_once $ROOT . '/../includes/popup.php'; ?>
</body>

<script>
    function agregar() {
        const form = document.querySelector('#formProducto');
        const formData = new FormData(form);

        if (!formData.get("nombre")) {
            alert("Favor de indicar el nombre del producto.");
            return;
        }

        if (!confirm("¿Está seguro que desea agregar este producto?")) return;

        displayPopUp();

        fetch('../../php/productos/agregar.php', {
                method: 'POST',
                body: formData
            })
            .then(res => res.json())
            .then(data => {
                if (data.status == 0) {
                    displayMensajeError(data.mensaje);
                } else {
                    displayMensajeExitoso(data.mensaje, "window.history.back()");
                }
            })
            .catch(() => {
                displayMensajeError("Error de conexión, intente nuevamente.");
            });
    }
</script>

</html>