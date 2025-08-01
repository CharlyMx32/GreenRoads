<?php

$ROOT = '../..';
$TITULO = "Nuevo Extra";

require_once $ROOT . '/db/conexion.php';
require_once $ROOT . '/includes/sesion.php';
require_once $ROOT . '/includes/config.php';

if (!tieneSesion()) {
    header("Location: $URL_ROOT/login");
    exit();
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <?php include_once $ROOT . '/includes/head.php'; ?>
    <link rel="stylesheet" href="../../css/extras/agregar_extra.css">
</head>

<body>
    <?php
    $headerParams = [
        "titulo" => $TITULO,
        "btn_atras" => "window.history.back()"
    ];
    // include_once $ROOT . '/includes/header.php';
    include_once '../../includes/header.php';
    ?>

    <!-- Formulario principal -->
    <div class="formulario active">
        <div class="subtitulo-formulario">Información del extra</div>

        <form id="formExtra" aria-labelledby="formTitle">
            <div class="fila-inventario">
                <div class="columna">
                    <!-- Campos del formulario -->
                    <div class="textfield-container">
                        <input required type="text" class="textfield" name="nombre" id="nombre"
                            aria-required="true" aria-label="Nombre del extra">
                        <label for="nombre" placeholder="Nombre *"></label>
                    </div>

                    <div class="textfield-container">
                        <input required type="number" class="textfield" name="precio" id="precio"
                            step="0.01" min="0" aria-required="true" aria-label="Precio del extra">
                        <label for="precio" placeholder="Precio *"></label>
                    </div>

                    <div class="textfield-container">
                        <textarea class="textfield" name="descripcion" id="descripcion"
                            aria-label="Descripción del extra" rows="3"></textarea>
                        <label for="descripcion" placeholder="Descripción (opcional)"></label>
                    </div>

                    <div class="checkbox-container" style="margin: 20px 0;">
                        <label class="container-checkbox-filtro">
                            Activar este extra
                            <input type="checkbox" name="activo" id="activo" checked>
                            <span class="checkmark2"></span>
                        </label>
                    </div>
                </div>
            </div>
        </form>

        <button type="button" class="btnadd" style="margin-top: 10px;" onclick="agregarExtra()"
            aria-label="Agregar nuevo extra">
            Agregar
        </button>
    </div>

    <?php 
    //include_once $ROOT . '/../includes/popup.php'; 
    include_once '../../includes/popup.php';
    ?>

    <!-- Scripts -->
    <script>
        function agregarExtra() {
            const nombre = document.getElementById('nombre').value.trim();
            const precio = parseFloat(document.getElementById('precio').value);
            const descripcion = document.getElementById('descripcion').value.trim();
            const activo = document.getElementById('activo').checked ? 1 : 0;

            // Validaciones básicas
            if (!nombre || isNaN(precio)) {
                displayMensajeError('Nombre y precio son campos requeridos');
                return;
            }

            if (precio < 0) {
                displayMensajeError('El precio no puede ser negativo');
                return;
            }

            displayPopUp();

            const datos = {
                nombre,
                precio,
                descripcion,
                activo
            };

            fetch('../../php/extras/agregar.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify(datos)
            })
            .then(response => response.json())
            .then(data => {
                if (data.status === 1) {
                    displayMensajeExitoso(data.mensaje, "window.history.back()");
                } else {
                    displayMensajeError(data.mensaje || 'Error al agregar el extra');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                displayMensajeError('Error de conexión, favor de intentarlo nuevamente');
            });
        }
    </script>
</body>
</html>