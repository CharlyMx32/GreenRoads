<?php
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

$ROOT = '../..';
$TITULO = "Editar Cliente";

include_once $ROOT . '/db/conexion.php';
include_once $ROOT . '/includes/sesion.php';
include_once $ROOT . '/includes/config.php';

$id_cliente = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($id_cliente <= 0) {
    header("Location: lista.php");
    exit();
}

$stmt = $conn->prepare("SELECT * FROM clientes WHERE id = ?");
$stmt->bind_param("i", $id_cliente);
$stmt->execute();
$result = $stmt->get_result();
$cliente = $result->fetch_assoc();

if (!$cliente) {
    header("Location: lista.php");
    exit();
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <?php include_once $ROOT . '/includes/head.php'; ?>
</head>
<body>
<?php
$headerParams = [
    "titulo" => $TITULO,
    "btn_atras" => "window.history.back()"
];
include_once '../../includes/header.php';
?>

<div class="formulario active">
    <div class="subtitulo-formulario">Información general</div>
    <div class="seccion-formulario">
        <form id="formEditar" class="formulario-editar">
            <input type="hidden" name="id" value="<?= htmlspecialchars($cliente['id']) ?>">

            <input required type="text" class="textfield" name="nombre" value="<?= htmlspecialchars($cliente['nombre']) ?>">
            <label placeholder="Nombre *"></label>

            <input type="text" class="textfield" name="telefono" value="<?= htmlspecialchars($cliente['telefono']) ?>">
            <label placeholder="Teléfono"></label>

            <input type="email" class="textfield" name="email" value="<?= htmlspecialchars($cliente['email']) ?>">
            <label placeholder="Email"></label>

            <textarea class="textfield" name="direccion" placeholder="Dirección"><?= htmlspecialchars($cliente['direccion']) ?></textarea>
        </form>

        <button id="btnEditar" type="button" class="btnadd" onclick="editarCliente()">
            Guardar Cambios
        </button>
    </div>
</div>

<?php include_once '../../includes/popup.php'; ?>
</body>

<script>
    window.editarCliente = function() {
        const btnEditar = document.querySelector('#btnEditar');
        const form = document.querySelector('#formEditar');
        const formData = new FormData(form);

        const nombre = formData.get("nombre")?.trim();
        const email = formData.get("email")?.trim();

        if (!nombre) {
            displayMensajeError("Favor de indicar el nombre del cliente.");
            return;
        }

        if (email && !email.includes('@')) {
            displayMensajeError("El correo electrónico no parece válido.");
            return;
        }

        if (!confirm("¿Está seguro que desea guardar los cambios?")) return;

        btnEditar.disabled = true;
        btnEditar.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Guardando...';
        displayPopUp("Guardando cambios...");

        fetch('../../php/clientes/editar.php?t=' + Date.now(), {
            method: 'POST',
            body: formData
        })
        .then(response => {
            if (!response.ok) throw new Error('Error en la respuesta del servidor');
            return response.json();
        })
        .then(data => {
            if (data.status == 0) {
                throw new Error(data.mensaje || "Error desconocido del servidor");
            }
            displayMensajeExitoso(data.mensaje, "window.history.back()");
        })
        .catch(error => {
            console.error('Error:', error);
            displayMensajeError("Error: " + error.message);
        })
        .finally(() => {
            btnEditar.disabled = false;
            btnEditar.innerHTML = 'Guardar Cambios';
        });
    }
</script>

</html>
