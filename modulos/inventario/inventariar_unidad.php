<?php
$ROOT = '../..';
$TITULO = "Inventariar producto";

include_once "$ROOT/db/conexion.php";
include_once "$ROOT/includes/sesion.php";
include_once "$ROOT/includes/config.php";

if (!tieneSesion()) {
    header("Location: $URL_ROOT/login");
    exit();
}

$id = isset($_GET['id']) ? intval($_GET['id']) : 0;
if ($id <= 0) {
    header("Location: lista.php?error=id_invalido");
    exit();
}

$sql = "SELECT 
            p.imagen,
            p.id AS id_producto,
            p.nombre, 
            p.descripcion,
            u.nombre AS unidad_nombre, 
            u.simbolo
        FROM productos p
        JOIN unidades u ON u.id = p.id_unidad
        WHERE p.id = ? LIMIT 1";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $id);
$stmt->execute();
$producto = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$producto) {
    header("Location: lista.php?error=producto_no_encontrado");
    exit();
}
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <?php include_once "$ROOT/includes/head.php"; ?>
</head>

<body>
    <?php
    $headerParams = [
        "titulo" => $TITULO,
        "btn_atras" => "window.history.back()"
    ];
    include_once "../../includes/header.php";
    ?>

    <main class="content">
        <div class="formulario active">
            <div class="seccion-formulario">
                <h1 class="subtitulo-formulario" style="margin-top: -100px;">Inventariar: <strong><?= htmlspecialchars($producto['nombre']) ?></strong></h1>

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
                </div>

                <?php if (!$tieneImagen): ?>
                    <div class="contenedor-no-foto" id="contenedorNoFoto" style="margin-top: -10px;">
                        <span><i class="fas fa-image" style="font-size: 80px; color: #aaa;"></i></span>
                    </div>
                <?php endif; ?>

                <form id="formInventarioUnidad" method="POST" action="<?php echo $URL_ROOT; ?>/php/inventario/guardar_unidad.php">
                    <input type="hidden" name="id_producto" value="<?= $producto['id_producto'] ?>">

                    <div class="textfield-container">
                        <input type="number" name="nueva_cantidad" min="0.01" step="0.01" required class="textfield">
                        <label placeholder="Cantidad (<?= htmlspecialchars($producto['unidad_nombre']) ?>) *"></label>
                    </div>

                    <div class="textfield-container">
                        <textarea name="motivo" class="textfield" placeholder="Motivo o comentario (opcional)"></textarea>
                    </div>

                    <div class="btn-row">
                        <button type="submit" class="btnadd">Guardar</button>
                    </div>
                </form>
            </div>
        </div>
        <?php include_once '../../includes/popup.php'; ?>

    </main>

</body>

<script>
    document.addEventListener('DOMContentLoaded', function() {


        const form = document.getElementById('formInventarioUnidad');
        if (!form) {
            console.error("❌ Formulario 'formInventarioUnidad' NO encontrado");
            return;
        }

        const btn = form.querySelector('.btnadd');
        const cantidadInput = form.querySelector('input[name="nueva_cantidad"]');

        // Validación en tiempo real
        cantidadInput.addEventListener('input', function() {
            if (this.value && parseFloat(this.value) > 0) {
                this.classList.remove('error');
            }
        });

        form.addEventListener('submit', function(e) {
            e.preventDefault();

            const formData = new FormData(form);
            const cantidad = parseFloat(formData.get('nueva_cantidad'));

            // Validación cliente
            if (isNaN(cantidad) || cantidad <= 0) {
                displayMensajeError("Ingrese una cantidad válida mayor a cero");
                cantidadInput.classList.add('error');
                cantidadInput.focus();
                return;
            }

            if (!confirm("¿Está seguro que desea actualizar el inventario?")) return;

            btn.disabled = true;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Guardando...';

            displayPopUp();

            fetch(form.action, {
                    method: 'POST',
                    body: formData
                })
                .then(response => {

                    const contentType = response.headers.get('content-type');
                    if (!contentType || !contentType.includes('application/json')) {
                        return response.text().then(text => {
                            console.error("❌ Respuesta no JSON:", text);
                            throw new Error('Respuesta inesperada del servidor');
                        });
                    }
                    return response.json();
                })
                .then(data => {

                    if (data.status === 1) {
                        displayMensajeExitoso(
                            data.mensaje,
                            data.redirect ?
                            "window.location.href='" + data.redirect + "'" :
                            "window.location.reload()"
                        );
                    } else {
                        throw new Error(data.mensaje || "Error al guardar");
                    }
                })
                .catch(error => {
                    console.error("❌ Error en fetch:", error);
                    displayMensajeError(error.message);
                })
                .finally(() => {
                    btn.disabled = false;
                    btn.innerHTML = 'Guardar';
                });
        });
    });

    const inputImagen = document.getElementById('imagenInput');
    if (inputImagen) {
        const contenedorFoto = document.getElementById('contenedorFoto');
        const contenedorNoFoto = document.getElementById('contenedorNoFoto');
        const preview = document.getElementById('imagenPreview');

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
    }
</script>


</html>