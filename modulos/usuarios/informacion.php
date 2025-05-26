<?php 
    ini_set('display_errors', '1');
    ini_set('display_startup_errors', '1');
    error_reporting(E_ALL);
    $ROOT = $_SERVER['DOCUMENT_ROOT'];

    $TITULO = "Información usuario";
    
    include_once $ROOT.'/db/conexion.php';
    include_once $ROOT.'/includes/sesion.php';
    include_once $ROOT.'/includes/config.php';

    if(!tieneSesion()) {
        header("Location: $URL_ROOT/login");
        exit();
    }

    if(!isset($_GET['u'])) {
        header("location: $URL_ROOT/modulos/usuarios/lista");
        exit();
    }

    $sql = "SELECT
    id,
    nombre,
    apellido,
    usuario,
    clave,
    estado
    FROM admins
    WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $_GET['u']);
    $stmt->execute();
    $stmt->store_result();
    $stmt->bind_result($id, $nombre, $apellido, $usuario, $clave, $estado);
    $stmt->fetch();
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
                    <input required type="text" class="textfield" id="nombre" value="<?php echo $nombre; ?>">
                    <label for="" placeholder="Nombre *"></label>

                    <input required type="text" class="textfield" id="apellido" value="<?php echo $apellido; ?>">
                    <label for="" placeholder="Apellido *"></label>

                    <input required type="text" class="textfield" id="usuario" value="<?php echo $usuario; ?>">
                    <label for="" placeholder="Usuario *"></label>
                    
                    <input required type="text" class="textfield" id="clave" value="<?php echo $clave; ?>">
                    <label for="" placeholder="Contraseña *"></label>
                </form>

                <div class="btnadd" onclick="modificar()">Guardar cambios</div>
            </div>
        </div>

        <!-- POPUP -->
        <?php include_once $ROOT.'/includes/popup.php'; ?>

    </body>

    <script>
        function modificar() {
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

            if(!confirm("¿Está seguro que desea guardar los cambios?")) return false;

            displayPopUp();

            $.post('<?php $ROOT; ?>/php/usuarios/modificar', {
                id: <?php echo $id; ?>,
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