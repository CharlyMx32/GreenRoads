<?php

/**
 * Controlador para creación de nuevos productos
 * 
 * Permite registrar nuevos productos en el sistema con sus características básicas
 * y configuración de inventario.
 */

// Configuración inicial
$ROOT = '../..';
$TITULO = "Nuevo producto";

// Incluir archivos necesarios
require_once $ROOT . '/db/conexion.php';
require_once $ROOT . '/includes/sesion.php';
require_once $ROOT . '/includes/config.php';

// Verificar sesión activa
if (!tieneSesion()) {
    header("Location: $URL_ROOT/login");
    exit();
}

// Usar consultas preparadas para obtener datos
$unidades = [];
$tiposProducto = [];

// Obtener tipos de producto
$query = "SELECT id, nombre FROM tipo_productos ORDER BY nombre ASC";
if ($result = mysqli_query($conn, $query)) {
    while ($row = mysqli_fetch_assoc($result)) {
        $tiposProducto[$row['id']] = $row['nombre'];
    }
    mysqli_free_result($result);
} else {
    // Manejo de error en consulta
    error_log("Error al obtener tipos de producto: " . mysqli_error($conn));
}

// Obtener modelos de pasto
$modelos = [];
$query = "SELECT id, nombre FROM modelos ORDER BY nombre ASC";
if ($result = mysqli_query($conn, $query)) {
    while ($row = mysqli_fetch_assoc($result)) {
        $modelos[] = $row;
    }
    mysqli_free_result($result);
} else {
    error_log("Error al obtener modelos: " . mysqli_error($conn));
}


// Obtener unidades de medida
$query = "SELECT id, nombre FROM unidades ORDER BY nombre ASC";
if ($result = mysqli_query($conn, $query)) {
    while ($row = mysqli_fetch_assoc($result)) {
        $unidades[] = $row;
    }
    mysqli_free_result($result);
} else {
    error_log("Error al obtener unidades: " . mysqli_error($conn));
}
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <?php include_once $ROOT . '/includes/head.php'; ?>
    <style>
        /* Estilos para el diseño responsivo */
        .fila-inventario {
            display: flex;
            gap: 20px;
            justify-content: center;
            align-items: flex-start;
            flex-wrap: nowrap;
            padding: 20px;
            max-width: 100%;
            box-sizing: border-box;
        }

        /* Media queries para responsividad */
        @media (max-width: 768px) {
            .fila-inventario {
                flex-direction: column;
                align-items: center;
            }
        }

        .columna {
            flex: 1;
            max-width: 500px;
            min-width: 300px;
            box-sizing: border-box;
        }

        /* Contenedor para campos de formulario */
        .contenedor-campos {
            display: flex;
            flex-wrap: wrap;
            gap: 15px;
        }

        .contenedor-campos .textfield-container {
            flex: 1;
            min-width: 200px;
        }
    </style>
</head>

<body>
    <?php
    $headerParams = [
        "titulo" => $TITULO,
        "btn_atras" => "window.history.back()"
    ];
    include_once $ROOT . '/../includes/header.php';
    ?>

    <!-- Formulario principal -->
    <div class="formulario active">
        <div class="subtitulo-formulario">Información del producto</div>

        <form id="formProducto" enctype="multipart/form-data" aria-labelledby="formTitle">
            <div class="fila-inventario">
                <!-- Columna 1: Datos generales -->
                <div class="columna">
                    <!-- Sección de imagen -->
                    <div class="contenedor-no-foto" id="contenedorNoFoto" style="margin-top: -10px;">
                        <span aria-hidden="true"><i class="fas fa-image" style="font-size: 80px; color: #aaa;"></i></span>
                        <button type="button" class="btn-img" id="btnSubirSinImg" aria-label="Subir imagen del producto">
                            <i class="fas fa-camera"></i>
                        </button>
                    </div>

                    <div class="contenedor-foto" id="contenedorFoto" style="display:none;">
                        <img id="imagenPreview" src="" alt="Previsualización de imagen del producto">
                        <div class="contenedor-spinner" id="spinnerImg" style="display:none;" aria-hidden="true">
                            <i class="fas fa-spinner fa-spin"></i>
                        </div>
                        <button type="button" class="btn-img" id="btnSubirImg" aria-label="Cambiar imagen del producto">
                            <i class="fas fa-camera"></i>
                        </button>
                    </div>

                    <input type="file" name="imagen" accept="image/*" id="imagenInput" style="display:none;">

                    <!-- Campos del formulario -->
                    <div class="textfield-container">
                        <input required type="text" class="textfield" name="nombre" id="nombre"
                            aria-required="true" aria-label="Nombre del producto">
                        <label for="nombre" placeholder="Nombre *"></label>
                    </div>

                    <div class="textfield-container">
                        <select name="tipo_producto" class="textfield" required id="tipoProducto"
                            aria-required="true" aria-label="Tipo de producto">
                            <option value=""></option>
                            <?php foreach ($tiposProducto as $valor => $texto): ?>
                                <option value="<?= htmlspecialchars($valor) ?>"><?= htmlspecialchars($texto) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <label for="tipoProducto" placeholder="Tipo de producto *"></label>
                    </div>

                    <div class="textfield-container">
                        <select name="id_unidad" class="textfield" required
                            aria-required="true" aria-label="Unidad de medida">
                            <option value=""></option>
                            <?php foreach ($unidades as $unidad): ?>
                                <option value="<?= htmlspecialchars($unidad['id']) ?>"><?= htmlspecialchars($unidad['nombre']) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <label placeholder="Unidad *"></label>
                    </div>

                    <div class="textfield-container">
                        <select name="tipo_inventario" class="textfield" id="tipoInventario" required
                            aria-required="true">
                            <option value="">Selecciona tipo de inventario</option>
                            <option value="unidad">Unidad</option>
                            <option value="rollo">Rollo</option>
                        </select>
                        <label for="tipoInventario" placeholder="Tipo de inventario *"></label>
                    </div>

                    <div class="textfield-container" id="campoModelo" style="display:none;">
                        <select name="id_modelo" class="textfield" aria-label="Modelo de pasto">
                            <option value="">Selecciona modelo</option>
                            <?php foreach ($modelos as $modelo): ?>
                                <option value="<?= htmlspecialchars($modelo['id']) ?>">
                                    <?= htmlspecialchars($modelo['nombre']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <label placeholder="Modelo (solo para pasto)"></label>
                    </div>


                    <div class="textfield-container">
                        <input type="number" class="textfield" name="precio_unitario" step="0.01" min="0"
                            aria-label="Precio unitario">
                        <label placeholder="Precio unitario"></label>
                    </div>
                </div>
            </div>
        </form>

        <button type="button" class="btnadd" style="margin-top: -15px;" onclick="agregar()"
            aria-label="Agregar nuevo producto">
            Agregar
        </button>
    </div>

    <?php include_once $ROOT . '/../includes/popup.php'; ?>

    <!-- JavaScript más organizado y con manejo de errores -->
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Elementos del DOM
            const inputImagen = document.getElementById('imagenInput');
            const contenedorFoto = document.getElementById('contenedorFoto');
            const contenedorNoFoto = document.getElementById('contenedorNoFoto');
            const preview = document.getElementById('imagenPreview');
            const tipoProducto = document.getElementById('tipoProducto');
            const btnAdd = document.querySelector('.btnadd');
            const form = document.getElementById('formProducto');

            // Configurar eventos
            document.getElementById('btnSubirImg').onclick =
                document.getElementById('btnSubirSinImg').onclick = () => inputImagen.click();

            // Manejar cambio de imagen
            inputImagen.addEventListener('change', function(e) {
                const file = e.target.files[0];
                const maxSize = 2 * 1024 * 1024; // 2MB

                if (!file) return;

                // Validar tipo de archivo
                if (!file.type.startsWith('image/')) {
                    displayMensajeError("Por favor seleccione un archivo de imagen válido.");
                    return;
                }

                // Validar tamaño de archivo
                if (file.size > maxSize) {
                    displayMensajeError("La imagen es demasiado grande (máximo 2MB).");
                    return;
                }

                // Mostrar previsualización
                const reader = new FileReader();
                reader.onload = function(ev) {
                    preview.src = ev.target.result;
                    preview.alt = "Previsualización de nueva imagen del producto";
                    contenedorFoto.style.display = 'flex';
                    contenedorNoFoto.style.display = 'none';
                };
                reader.readAsDataURL(file);
            });

            // Actualizar campos según tipo de producto
            tipoProducto.addEventListener('change', actualizarCamposPasto);
            actualizarCamposPasto();

            // Función para actualizar campos según tipo de producto
            function actualizarCamposPasto() {
                const tipoProductoValue = parseInt(tipoProducto.value);
                const tipoInventario = document.getElementById('tipoInventario');

                if (tipoProductoValue === 1) { // Si es tipo rollo
                    tipoInventario.value = 'rollo';
                    tipoInventario.disabled = false;

                    campoModelo.style.display = 'block';

                } else {
                    tipoInventario.value = 'unidad';
                    tipoInventario.disabled = false;

                    campoModelo.style.display = 'none';

                }
            }

            // Función para agregar nuevo producto
            window.agregar = function() {
                const formData = new FormData(form);
                const nombre = formData.get("nombre")?.trim();

                // Validaciones básicas
                if (!nombre) {
                    displayMensajeError("Favor de indicar el nombre del producto.");
                    form.nombre.focus();
                    return;
                }

                if (!formData.get("id_unidad")) {
                    displayMensajeError("Favor de seleccionar una unidad de medida.");
                    return;
                }

                if (!formData.get("tipo_inventario")) {
                    displayMensajeError("Seleccione el tipo de inventario: unidad o rollo.");
                    return;
                }

                if (tipoProducto.value == '1' && !formData.get("id_modelo")) {
                    displayMensajeError("Seleccione un modelo para el pasto.");
                    return;
                }


                if (!confirm("¿Está seguro que desea agregar este producto?")) return;

                btnAdd.disabled = true;
                btnAdd.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Procesando...';
                displayPopUp("Agregando producto...");

                // Enviar datos
                fetch('../../php/productos/agregar.php?t=' + Date.now(), {
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
        });
    </script>
</body>

</html>