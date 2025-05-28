<?php
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

$ROOT = '../..';
$TITULO = "Editar producto";

include_once $ROOT . '/db/conexion.php';
include_once $ROOT . '/includes/sesion.php';
include_once $ROOT . '/includes/config.php';

if (!tieneSesion()) {
    header("Location: $URL_ROOT/login");
    exit();
}

if (!isset($_GET['p'])) {
    header("Location: $URL_ROOT/productos");
    exit();
}

$id = intval($_GET['p']);
$sql = "SELECT * FROM productos WHERE id = $id AND estado <> 'eliminado'";
$resultado = mysqli_query($conn, $sql);
$producto = mysqli_fetch_assoc($resultado);

if (!$producto) {
    header("Location: $URL_ROOT/productos");
    exit();
}

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
        <div class="seccion-formulario">

            <div class="subtitulo-formulario">Información del producto</div>

            <!-- Imagen centrada arriba -->
            <?php if ($producto['imagen']): ?>
                <div style="display: flex; justify-content: center; align-items: center; width: 100%; flex-direction: column; margin-bottom: 20px;">
                    <img id="imgProducto"
                        src="../../img/productos/<?= $producto['imagen'] ?>?nocache=<?= uniqid() ?>"
                        style="max-width: 200px; max-height: 200px; border-radius: 10px; cursor: pointer; transition: 0.3s; margin: auto;"
                        title="Haz clic para cambiar la imagen" />
                    <input type="file" name="imagen" id="inputImagen" style="display: none;" accept="image/*">
                    <p style="margin-top: 10px;">Haz clic en la imagen para cambiarla.</p>
                </div>
            <?php endif; ?>

            <form id="formProducto" enctype="multipart/form-data">
                <input required type="text" class="textfield" name="nombre" id="nombre" value="<?= htmlspecialchars($producto['nombre']) ?>">
                <label for="nombre" placeholder="Nombre *"></label>

                <input type="number" class="textfield" name="precio_unitario" step="0.01" value="<?= $producto['precio_unitario'] ?>">
                <label placeholder="Precio unitario"></label>

                <input type="number" class="textfield" name="ancho_metros" step="0.01" value="<?= $producto['ancho_metros'] ?>">
                <label placeholder="Ancho en metros"></label>
                
                <select name="id_unidad" class="textfield" required>
                        <option value=""></option>
                        <?php foreach ($unidades as $unidad): ?>
                        <option value="<?= $unidad['id'] ?>" <?= $unidad['id'] == $producto['id_unidad'] ? 'selected' : '' ?>>
                            <?= $unidad['nombre'] ?>
                        </option>
                    <?php endforeach; ?>
                    </select>
                    <label placeholder="Unidad *"></label>

                <label placeholder="Imagen del producto (opcional)"></label>
                <input type="hidden" name="id" value="<?= $producto['id'] ?>">
            </form>

            <div class="btnadd" onclick="editar()">Guardar cambios</div>
        </div>
    </div>

    <?php include_once $ROOT . '/../includes/popup.php'; ?>
</body>

<script>
    // Hacer clic en imagen para seleccionar nueva, con confirmación
    document.querySelector('#imgProducto')?.addEventListener('click', () => {
        if (confirm("¿Estás seguro de que deseas cambiar la imagen del producto?")) {
            document.querySelector('#inputImagen').click();
        }
    });


    // Subida automática de imagen
    document.querySelector('#inputImagen')?.addEventListener('change', function() {
        const input = this;
        const archivo = input.files[0];
        if (!archivo) return;

        const formData = new FormData();
        formData.append('id', document.querySelector('input[name="id"]').value);
        formData.append('imagen', archivo);

        displayPopUp("Subiendo imagen...");

        fetch('../../php/productos/editar_imagen.php', {
                method: 'POST',
                body: formData
            })
            .then(res => res.json())
            .then(data => {
                if (data.status === 1) {
                    const img = document.querySelector('#imgProducto');
                    img.src = `../../img/productos/${data.imagen}?nocache=${Math.random()}`;
                    displayMensajeExitoso("Imagen actualizada.");
                } else {
                    displayMensajeError(data.mensaje);
                }
            })
            .catch(() => {
                displayMensajeError("Error de conexión al subir la imagen.");
            });
    });

    function editar() {
        const form = document.querySelector('#formProducto');
        const formData = new FormData(form);

        if (!formData.get("nombre")) {
            alert("Favor de indicar el nombre del producto.");
            return;
        }

        if (!confirm("¿Desea guardar los cambios?")) return;

        displayPopUp();

        fetch('../../php/productos/editar.php', {
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