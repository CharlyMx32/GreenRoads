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
                <h1 class="subtitulo-formulario">Inventariar: <strong><?= htmlspecialchars($producto['nombre']) ?></strong></h1>

                <form id="formInventarioUnidad" method="POST" action="../../php/inventario/guardar_unidad.php">
                    <input type="hidden" name="id_producto" value="<?= $producto['id_producto'] ?>">

                    <div class="textfield-container">
                        <input type="number" name="cantidad" min="0.01" step="0.01" required class="textfield">
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
    </main>

    <?php include_once "../../includes/popup.php"; ?>
</body>

<script>
document.getElementById('formInventarioUnidad').addEventListener('submit', function (e) {
    e.preventDefault();
    const form = this;
    const btn = form.querySelector('.btnadd');
    const formData = new FormData(form);

    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Guardando...';
    displayPopUp();

    fetch(form.action, {
        method: 'POST',
        body: formData
    })
    .then(resp => resp.json())
    .then(data => {
        if (data.success) {
            displayMensajeExitoso(data.message, "window.location.href = '../../modulos/inventario/lista.php'");
        } else {
            displayMensajeError(data.message || "Error al guardar", () => {
                btn.disabled = false;
                btn.innerHTML = 'Guardar';
            });
        }
    })
    .catch(error => {
        console.error("Error:", error);
        displayMensajeError("Error de red: " + error.message, () => {
            btn.disabled = false;
            btn.innerHTML = 'Guardar';
        });
    });
});
</script>

</html>
