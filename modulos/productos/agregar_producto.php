<?php

$ROOT = '../..';
$TITULO = "Nuevo producto";

require_once $ROOT . '/db/conexion.php';
require_once $ROOT . '/includes/sesion.php';
require_once $ROOT . '/includes/config.php';

if (!tieneSesion()) {
    header("Location: $URL_ROOT/login");
    exit();
}

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

// Obtener colores disponibles
$colores = [];
$query = "SELECT id, nombre, codigo_hex FROM colores ORDER BY nombre ASC";
if ($result = mysqli_query($conn, $query)) {
    while ($row = mysqli_fetch_assoc($result)) {
        $colores[] = $row;
    }
    mysqli_free_result($result);
} else {
    error_log("Error al obtener colores: " . mysqli_error($conn));
}



?>
<!DOCTYPE html>
<html lang="es">

<head>
    <?php include_once $ROOT . '/includes/head.php'; ?>
    <link rel="stylesheet" href="../../css/productos/agregar_producto.css">
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
                        <input type="number" class="textfield" name="costo_base" id="costo_base"
                            step="0.01" min="0" aria-label="Costo base del producto">
                        <label for="costo_base" placeholder="Costo base"></label>
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
                            aria-required="true" aria-label="Unidad de medida" id="unidad">
                            <option value=""></option>
                            <?php foreach ($unidades as $unidad): ?>
                                <option value="<?= htmlspecialchars($unidad['id']) ?>"><?= htmlspecialchars($unidad['nombre']) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <label placeholder="Unidad *"></label>
                    </div>

                    <div class="textfield-container" style="display:none;">
                        <select name="tipo_inventario" class="textfield" id="tipoInventario" required
                            aria-required="true">
                            <option value="">Selecciona tipo de inventario</option>
                            <option value="unidad">Unidad</option>
                            <option value="rollo">Rollo</option>
                        </select>
                        <label for="tipoInventario" placeholder="Tipo de inventario *"></label>
                    </div>

                    <div class="textfield-container" id="campoModelo" style="display:none; margin-bottom: 15px;">
                        <div style="display: flex; align-items: center; gap: 10px;">
                            <select name="id_modelo" class="textfield" aria-label="Modelo de pasto" id="selectModelo" style="flex: 1;">
                                <option value="">Selecciona modelo</option>
                                <?php foreach ($modelos as $modelo): ?>
                                    <option value="<?= htmlspecialchars($modelo['id']) ?>">
                                        <?= htmlspecialchars($modelo['nombre']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <button type="button" id="btnAgregarModelo"
                                style="margin: 0; width: 40px; height: 40px;"
                                aria-label="Agregar nuevo modelo">
                                <i class="fas fa-plus"></i>
                            </button>
                        </div>
                        <label placeholder="Modelo (solo para pasto)"></label>
                    </div>

                    <!-- campo para escojer los colores del ROLLO -->
                    <div class="textfield-container" id="divcolorRollo" style="display:none;">
                        <label style="display:block; margin-bottom: 5px;">Colores del rollo *</label>
                        <div class="color-grid">
                            <?php foreach ($colores as $color): ?>
                                <label class="color-option">
                                    <input type="checkbox" name="color_rollo[]" value="<?= $color['id'] ?>">
                                    <span class="color-box" style="background-color: <?= $color['codigo_hex'] ?>"></span>
                                    <?= htmlspecialchars($color['nombre']) ?>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    </div>


                </div>
            </div>
        </form>

        <button type="button" class="btnadd" style="margin-top: 10px;" onclick="agregar()"
            aria-label="Agregar nuevo producto">
            Agregar
        </button>
    </div>

    <?php include_once $ROOT . '/../includes/popup.php'; ?>

    <div id="modalModelo" class="modal" style="display: none;">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Agregar nuevo modelo</h3>
            </div>
            <div class="modal-body">
                <div class="textfield-container">
                    <input type="text" class="textfield" id="nombreModelo"
                        aria-label="Nombre del modelo" required>
                    <label for="nombreModelo" placeholder="Nombre del modelo *"></label>
                </div>
                <div class="textfield-container">
                    <input type="number" class="textfield" id="alturaModelo"
                        step="0.01" min="0" aria-label="Altura en mm">
                    <label for="alturaModelo" placeholder="Altura (mm)"></label>
                </div>
            </div>
            <div class="modal-footer" style="display: flex; justify-content: space-between;">
                <button type="button" class="btnadd" id="btnGuardarModelo">Guardar</button>
            </div>
        </div>
    </div>

    <script src="../../scripts/productos/agregar_producto.js"></script>

</body>

</html>