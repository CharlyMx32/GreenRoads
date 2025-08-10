<?php
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

$ROOT = '../..';
$TITULO = "Nuevo cliente";

include_once '../../db/conexion.php';
include_once '../../includes/sesion.php';
include_once '../../includes/config.php';


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
    include_once '../../includes/header.php';
    ?>

    <div class="formulario active">
        <div class="subtitulo-formulario">Información general</div>
        <div class="seccion-formulario">
            <form id="form" class="formulario-agregar">
                <input required type="text" class="textfield" name="nombre" id="nombre">
                <label placeholder="Nombre *"></label>

                <input type="text" class="textfield" name="telefono" id="telefono">
                <label placeholder="Teléfono"></label>

                <input type="email" class="textfield" name="email" id="email">
                <label placeholder="Email"></label>

                <textarea class="textfield" name="direccion" id="direccion" placeholder="Dirección"></textarea>
            </form>

            <button id="btnAdd" type="button" class="btnadd" onclick="agregar()" aria-label="Agregar">
                Agregar
            </button>

        </div>
    </div>


    <?php
    include_once '../../includes/popup.php';
    ?>

</body>

<script>
    window.agregar = function() {
        const btnAdd = document.querySelector('#btnAdd');
        const form = document.querySelector('#form');
        const formData = new FormData(form);
        const nombre = formData.get("nombre")?.trim();

        if (!nombre) {
            displayMensajeError("Favor de indicar el nombre del cliente.");
            form.nombre.focus();
            return;
        }

        if (formData.get("email") && !formData.get("email").includes('@')) {
            displayMensajeError("El correo electrónico no parece válido.");
            return;
        }

        if (!confirm("¿Está seguro que desea agregar este cliente?")) return;

        btnAdd.disabled = true;
        btnAdd.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Procesando...';
        displayPopUp("Agregando cliente...");

        fetch('../../php/clientes/agregar.php?t=' + Date.now(), {
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
                btnAdd.disabled = false;
                btnAdd.innerHTML = 'Agregar';
            });
    };
</script>

</html>