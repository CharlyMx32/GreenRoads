<?php
    ini_set('display_errors', '1');
    ini_set('display_startup_errors', '1');
    error_reporting(E_ALL);
    // $ROOT = $_SERVER['DOCUMENT_ROOT'];
    
    include_once 'db/conexion.php';
    include_once 'includes/sesion.php';

    if(tieneSesion()) {
        header("location: modulos/dashboard/menu");
        exit();
    }
?>

<!DOCTYPE html>
<html>
    <head>
        <title>Green Roads | Log In</title>
        <meta charset="UTF-8">
        <meta name="description" content="Sistema administrativo para control de eventos y recompensas." />
        <meta name="viewport" content="width=device-width, initial-scale=1.0 maximum-scale=1.0, user-scalable=no">
        <meta name='author' content="SOMM Technologies">
        <meta property="og:title" content="PUNTOS CULTURALES | Log In" />
        <meta property="og:description" content="Sistema administrativo para control de eventos y recompensas." />
        <meta property="og:image" content="img/iconos/favicon-192.png" />
        <meta property="og:url" content="https://admin.puntosculturales.com/" />
        <meta property="og:site_name" content="PUNTOS CULTURALES" /><link rel="shortcut icon" href="/img/iconos/favicon.ico">
        <link rel="icon" sizes="16x16 32x32 64x64" href="img/iconos/favicon.ico">
        <link rel="icon" type="image/png" sizes="196x196" href="img/iconos/favicon-192.png">
        <link rel="icon" type="image/png" sizes="160x160" href="img/iconos/favicon-160.png">
        <link rel="icon" type="image/png" sizes="96x96" href="img/iconos/favicon-96.png">
        <link rel="icon" type="image/png" sizes="64x64" href="img/iconos/favicon-64.png">
        <link rel="icon" type="image/png" sizes="32x32" href="img/iconos/favicon-32.png">
        <link rel="icon" type="image/png" sizes="16x16" href="img/iconos/favicon-16.png">
        <link rel="apple-touch-icon" href="img/iconos/favicon-57.png">
        <link rel="apple-touch-icon" sizes="114x114" href="img/iconos/favicon-114.png">
        <link rel="apple-touch-icon" sizes="72x72" href="img/iconos/favicon-72.png">
        <link rel="apple-touch-icon" sizes="144x144" href="img/iconos/favicon-144.png">
        <link rel="apple-touch-icon" sizes="60x60" href="img/iconos/favicon-60.png">
        <link rel="apple-touch-icon" sizes="120x120" href="img/iconos/favicon-120.png">
        <link rel="apple-touch-icon" sizes="76x76" href="img/iconos/favicon-76.png">
        <link rel="apple-touch-icon" sizes="152x152" href="img/iconos/favicon-152.png">
        <link rel="apple-touch-icon" sizes="180x180" href="img/iconos/favicon-180.png">
        <meta name="msapplication-TileColor" content="#FFFFFF">
        <meta name="msapplication-TileImage" content="img/iconos/favicon-144.png">
        <meta name="msapplication-config" content="img/iconos/browserconfig.xml">

        <!-- CSS -->
        <link rel="stylesheet" type="text/css" href="css/login.css?cache=<?php echo uniqid(); ?>">

        <!-- GOOGLE FONTS -->
        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@100;200;300;400;600;700&display=swap" rel="stylesheet">
        <link href="https://fonts.googleapis.com/css2?family=Rajdhani&display=swap" rel="stylesheet">

        <!-- JQUERY -->
        <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.3.1/jquery.min.js"></script>

        <!-- FONTAWESOME -->
        <script src="https://kit.fontawesome.com/22dc07990c.js" crossorigin="anonymous"></script>
    </head>
    
    <body>

        <!-- CONTENIDO -->
        
        <div class="contenido">
            <div class="logo-contenido"></div>
            <form>
                <div class="input">
                    <div class="icono-input"><i class="fal fa-user"></i></div>
                    <input type="text" class="textfield" id="usuario" placeholder='usuario'>
                </div>
                <div class="input">
                    <div class="icono-input"><i class="fal fa-lock"></i></div>
                    <input type="password" class="textfield" id="pwd" placeholder='contraseña'>
                    <div class="mostrarclave" id="ojo" onclick="mostrar()"><i class="fal fa-eye"></i></div>
                </div>
                <div class="btn1" onclick="login()">LOG IN</div>
            </form>
        </div>
        <div class="trademark">Powered by<spam class="somm">SOMM Technologies</spam></div>
    </body>

    <!-- POPUP -->
    <?php include_once 'includes/popup.php'; ?>
</html>

<script>
    function mostrar() {
        let campo = document.getElementById("pwd");
        if (campo.type === "password") {
            campo.type = 'text';
            $('#ojo').html('<i class="fal fa-eye-slash"></i>');
        } else {
            campo.type = 'password';
            $('#ojo').html('<i class="fal fa-eye"></i>');
        }
    }
    
    document.addEventListener("keydown", function(e) {
        if (e.keyCode === 13) {
            login();
        }
    });

    function login() {
        let usuario = document.querySelector('#usuario').value.trim();
        let pwd = document.querySelector('#pwd').value.trim();

        if(usuario == '') {
            alert('Favor de indicar su usuario.');
            return false;
        }

        if(pwd == '') {
            alert('Favor de indicar su contraseña.');
            return false;
        }

        displayPopUp();

        $.post('php/sesion/login', {
            usuario: usuario,
            pwd: pwd
        })
        .done(function(data) {
            let respuesta = JSON.parse(data);

            if (respuesta.status == 0) displayMensajeError(respuesta.mensaje);
            else location.href='modulos/dashboard/menu';
        })
        .fail(function() {
            displayMensajeError("Connection error, please try again.");
        });
    }
</script>