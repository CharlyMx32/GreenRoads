<?php 
    ini_set('display_errors', '1');
    ini_set('display_startup_errors', '1');
    error_reporting(E_ALL);
    $ROOT = $_SERVER['DOCUMENT_ROOT'];

    $TITULO = "Nuevo producto";
    
    include_once $ROOT.'/db/conexion.php';
    include_once $ROOT.'/includes/sesion.php';
    include_once $ROOT.'/includes/config.php';

    if(!tieneSesion()) {
        header("Location: $URL_ROOT/login");
        exit();
    }
?>

<!DOCTYPE html>
<html>

    <head>
        <?php include_once $ROOT.'/includes/head.php'; ?>
    </head>

    <body>
        <?php 
            // HEADER
            $headerParams = [
                "titulo" => $TITULO,
                "btn_atras" => "window.history.back()"
            ];
            include_once $ROOT.'/includes/header.php';
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
                    
                    <input required type="text" class="textfield" id="clave">
                    <label for="" placeholder="Contraseña *"></label>
                </form>

                <div class="btnadd" onclick="agregar()">Agregar</div>
            </div>
        </div>

        <!-- POPUP -->
        <?php include_once $ROOT.'/includes/popup.php'; ?>

    </body>

    <script>
        function agregar() {
            let nombre = document.querySelector('#nombre').value.trim();
            let apellido = document.querySelector('#apellido').value.trim();
            let usuario = document.querySelector('#usuario').value.trim();
            let clave = document.querySelector('#clave').value.trim();

            if(nombre == '') {
                alert('Favor de indicar el nombre.');
                return false;
            }

            if(apellido == '') {
                alert('Favor de indicar el apellido.');
                return false;
            }

            if(usuario == '') {
                alert('Favor de indicar el usuario.');
                return false;
            }

            if(clave == '') {
                alert('Favor de indicar la contraseña.');
                return false;
            }

            if(!confirm("¿Está seguro que desea agregar la información?")) return false;

            displayPopUp();

            $.post('<?php $ROOT; ?>/php/usuarios/agregar', {
                nombre: nombre,
                apellido: apellido,
                usuario: usuario,
                clave: clave
            })
            .done(function(data) {
                let respuesta = JSON.parse(data);

                if (respuesta.status == 0) displayMensajeError(respuesta.mensaje);
                else displayMensajeExitoso(respuesta.mensaje, "window.history.back()");
            })
            .fail(function() {
                displayMensajeError("Connection error, please try again.");
            });
        }
    </script>
</html>