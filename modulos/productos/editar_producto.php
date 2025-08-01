<?php

/**
 * Controlador para edición de productos
 * 
 * Permite modificar información básica de productos y su imagen
 */
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

// Obtener y validar ID del producto
$id = intval($_GET['p']);
$stmt = $conn->prepare("SELECT * FROM productos WHERE id = ? AND estado <> 'eliminado'");
$stmt->bind_param("i", $id);
$stmt->execute();
$resultado = $stmt->get_result();
$producto = $resultado->fetch_assoc();

if (!$producto) {
    header("Location: $URL_ROOT/productos");
    exit();
}

$tiposProducto = [];
$res = mysqli_query($conn, "SELECT id, nombre FROM tipo_productos ORDER BY nombre ASC");
while ($row = mysqli_fetch_assoc($res)) {
    $tiposProducto[] = $row;
}

$modelos = [];
$res = mysqli_query($conn, "SELECT id, nombre FROM modelos ORDER BY nombre ASC");
while ($row = mysqli_fetch_assoc($res)) {
    $modelos[] = $row;
}

$unidades = [];
$res = mysqli_query($conn, "SELECT id, nombre FROM unidades ORDER BY nombre ASC");
while ($row = mysqli_fetch_assoc($res)) {
    $unidades[] = $row;
}
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

    <!-- Formulario de edición -->
    <div class="formulario active">
        <div class="subtitulo-formulario">Información del producto</div>
        <div class="seccion-formulario">
            <!-- Sección de imagen del producto -->
            <?php
            $tieneImagen = !empty($producto['imagen']);
            ?>
            <div class="<?= $tieneImagen ? 'contenedor-foto' : 'contenedor-no-foto' ?>" id="contenedorFoto" style="<?= $tieneImagen ? 'display:flex;' : 'display:none;' ?>">
                <?php if ($tieneImagen): ?>
                    <img id="imagenPreview" src="../../img/productos/<?= $producto['imagen'] ?>?nocache=<?= uniqid() ?>" alt="Previsualización">
                <?php else: ?>
                    <img id="imagenPreview" src="" alt="Previsualización">
                <?php endif; ?>

                <div class="contenedor-spinner" id="spinnerImg" style="display:none;">
                    <i class="fas fa-spinner fa-spin"></i>
                </div>

                <div class="btn-img" id="btnSubirImg" title="Subir imagen">
                    <i class="fas fa-camera"></i>
                </div>
            </div>

            <?php if (!$tieneImagen): ?>
                <div class="contenedor-no-foto" id="contenedorNoFoto" style="margin-top: -10px;">
                    <span><i class="fas fa-image" style="font-size: 80px; color: #aaa;"></i></span>
                    <div class="btn-img" id="btnSubirSinImg" title="Subir imagen">
                        <i class="fas fa-camera"></i>
                    </div>
                </div>
            <?php endif; ?>

            <input type="file" name="imagen" accept="image/*" id="imagenInput" style="display:none;">


            <!-- Formulario principal -->
            <form id="formProducto" enctype="multipart/form-data">
                <input required type="text" class="textfield" name="nombre" id="nombre" value="<?= htmlspecialchars($producto['nombre']) ?>">
                <label for="nombre" placeholder="Nombre *"></label>

                <select name="tipo_producto" class="textfield" required>
                    <option value=""></option>
                    <?php foreach ($tiposProducto as $tipo): ?>
                        <option value="<?= $tipo['id'] ?>" <?= $tipo['id'] == $producto['id_tipo_producto'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($tipo['nombre']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <label placeholder="Tipo de producto *"></label>

                <!-- Tipo de inventario -->
                <select name="tipo_inventario" class="textfield" required>
                    <option value="unidad" <?= $producto['tipo_inventario'] == 'unidad' ? 'selected' : '' ?>>Unidad</option>
                    <option value="rollo" <?= $producto['tipo_inventario'] == 'rollo' ? 'selected' : '' ?>>Rollo</option>
                </select>
                <label placeholder="Tipo de inventario *"></label>

                <select name="id_modelo" class="textfield">
                    <option value="">Sin modelo</option>
                    <?php foreach ($modelos as $modelo): ?>
                        <option value="<?= $modelo['id'] ?>" <?= $modelo['id'] == $producto['id_modelo'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($modelo['nombre']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <label placeholder="Modelo (opcional)"></label>

                <select name="id_unidad" class="textfield" required>
                    <option value=""></option>
                    <?php foreach ($unidades as $unidad): ?>
                        <option value="<?= $unidad['id'] ?>" <?= $unidad['id'] == $producto['id_unidad'] ? 'selected' : '' ?>>
                            <?= $unidad['nombre'] ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <label placeholder="Unidad *"></label>

                <input type="hidden" name="id" value="<?= $producto['id'] ?>">
            </form>

            <div class="btnadd" onclick="editar()">Guardar cambios</div>
        </div>
    </div>

    <?php include_once $ROOT . '/../includes/popup.php'; ?>
</body>

<script>
    const inputImagen = document.getElementById('imagenInput');
    const contenedorFoto = document.getElementById('contenedorFoto');
    const contenedorNoFoto = document.getElementById('contenedorNoFoto');
    const preview = document.getElementById('imagenPreview');
    const spinner = document.getElementById('spinnerImg');
    const subirConImagen = document.getElementById('btnSubirImg');
    const subirSinImagen = document.getElementById('btnSubirSinImg');

    // Asignar eventos de click para subir imagen
    if (subirConImagen) {
        subirConImagen.onclick = () => inputImagen.click();
    }
    if (subirSinImagen) {
        subirSinImagen.onclick = () => inputImagen.click();
    }

    // Mostrar preview al seleccionar una imagen
    inputImagen.addEventListener('change', function(e) {
        const file = e.target.files[0];
        if (file && file.type.startsWith('image/')) {
            const reader = new FileReader();
            reader.onload = function(ev) {
                preview.src = ev.target.result;

                if (contenedorFoto) contenedorFoto.style.display = 'flex';
                if (contenedorNoFoto) contenedorNoFoto.style.display = 'none';
            };
            reader.readAsDataURL(file);
        }
    });

    // Subir imagen
    inputImagen.addEventListener('change', function() {
        const archivo = this.files[0];
        if (!archivo) return;

        const formData = new FormData();
        formData.append('id', document.querySelector('input[name="id"]').value);
        formData.append('imagen', archivo);

        if (spinner) spinner.style.display = 'flex';
        displayPopUp("Subiendo imagen...");

        fetch('../../php/productos/editar_imagen.php', {
                method: 'POST',
                body: formData
            })
            .then(res => res.json())
            .then(data => {
                if (spinner) spinner.style.display = 'none';

                if (data.status === 1) {
                    preview.src = `../../img/productos/${data.imagen}?nocache=${Math.random()}`;
                    if (contenedorFoto) contenedorFoto.style.display = 'flex';
                    if (contenedorNoFoto) contenedorNoFoto.style.display = 'none';
                    displayMensajeExitoso("Imagen actualizada.");
                } else {
                    displayMensajeError(data.mensaje);
                }
            })
            .catch(() => {
                if (spinner) spinner.style.display = 'none';
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

        fetch('../../php/productos/editar_productos.php', {
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