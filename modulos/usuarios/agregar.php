<?php
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

$ROOT = '../../';

$TITULO = "Nuevo usuario";

include_once $ROOT . '/db/conexion.php';
include_once $ROOT . '/includes/sesion.php';
include_once $ROOT . '/includes/config.php';

$sql = "SELECT id, nombre FROM roles";
$result = $conn->query($sql);
$roles = [];
while ($row = $result->fetch_assoc()) {
    $roles[] = $row;
}
$conn->close();


?>

<!DOCTYPE html>
<html>

<head>
    <?php include_once $ROOT . '/includes/head.php'; ?>
</head>

<body>
    <?php
    // HEADER
    $headerParams = [
        "titulo" => $TITULO,
        "btn_atras" => "window.history.back()"
    ];
    //include_once $ROOT . '/includes/header.php';
    include_once '../../includes/header.php';
    ?>

    <div class="formulario active">
        <div class="subtitulo-formulario">Información general</div>
        <div class="seccion-formulario">
            <form>
                <input required type="text" class="textfield" id="nombre">
                <label for="" placeholder="Nombre *"></label>
                <input required type="text" class="textfield" id="apellido">
                <label for="" placeholder="Apellido *"></label>

                <input required type="text" class="textfield" id="usuario">
                <label for="" placeholder="Usuario *"></label>

                <select id="rol" class="textfield" style="margin-bottom: 15px;">
                    <option value="" disabled selected>Seleccione un rol *</option>
                    <?php foreach ($roles as $rol): ?>
                        <option value="<?php echo $rol['id']; ?>"><?php echo $rol['nombre']; ?></option>
                    <?php endforeach; ?>
                </select>

                <input required type="text" class="textfield" id="clave">
                <label for="" placeholder="Contraseña *"></label>
            </form>

            <div class="btnadd" onclick="agregar()">Agregar</div>
        </div>
    </div>

    <!-- POPUP -->
    <?php 
    //include_once $ROOT . '/includes/popup.php'; 
    include_once '../../includes/popup.php';
    ?>

</body>

<script>
    function agregar() {
        let nombre = document.querySelector('#nombre').value.trim();
        let apellido = document.querySelector('#apellido').value.trim();
        let usuario = document.querySelector('#usuario').value.trim();
        let clave = document.querySelector('#clave').value.trim();
        let rol = document.querySelector('#rol').value;

        if (nombre == '') {
            displayPopUp();
            displayMensajeError('Favor de indicar el nombre.');
            document.querySelector('#nombre').focus();
            return false;
        }

        if (apellido == '') {
            displayPopUp();
            displayMensajeError('Favor de indicar el apellido.');
            document.querySelector('#apellido').focus();
            return false;
        }

        if (usuario == '') {
            displayPopUp();
            displayMensajeError('Favor de indicar el usuario.');
            document.querySelector('#usuario').focus();
            return false;
        }

        if (!rol || rol == '') {
            displayPopUp();
            displayMensajeError('Favor de seleccionar un rol.');
            document.querySelector('#rol').focus();
            return false;
        }

        if (clave == '') {
            displayPopUp();
            displayMensajeError('Favor de indicar la contraseña.');
            document.querySelector('#clave').focus();
            return false;
        }

        if (!confirm("¿Está seguro que desea agregar la información?")) return false;

        displayPopUp();

        $.post('../../php/usuarios/agregar', {
                nombre: nombre,
                apellido: apellido,
                usuario: usuario,
                rol: rol,
                clave: clave
            })
            .done(function(data) {
                let respuesta = data;


                if (respuesta.status == 0) displayMensajeError(respuesta.mensaje);
                else displayMensajeExitoso(respuesta.mensaje, "window.history.back()");
            })
            .fail(function() {
                displayMensajeError("Connection error, please try again.");
            });
    }
</script>

</html>