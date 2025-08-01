<?php

/**
 * Controlador para edición de rollos por color
 * 
 * Permite modificar las dimensiones (largo/ancho) de rollos disponibles
 * para un producto y color específicos, así como eliminarlos.
 */

$ROOT = '../..';
$TITULO = "Editar rollos por color";

require_once "$ROOT/db/conexion.php";
require_once "$ROOT/includes/sesion.php";
require_once "$ROOT/includes/config.php";

if (!tieneSesion()) {
    header("Location: $URL_ROOT/login");
    exit();
}

// Validación estricta de parámetros
$id_producto = filter_input(INPUT_GET, 'id_producto', FILTER_VALIDATE_INT);
$id_color = filter_input(INPUT_GET, 'id_color', FILTER_VALIDATE_INT);

if (!$id_producto || !$id_color) {
    header("HTTP/1.1 400 Bad Request");
    die("Parámetros inválidos: Se requiere id_producto e id_color válidos");
}

function obtenerDatos($conn, $query, $params, $types)
{
    $stmt = $conn->prepare($query);
    if (!$stmt) {
        throw new Exception("Error al preparar la consulta");
    }

    $stmt->bind_param($types, ...$params);
    if (!$stmt->execute()) {
        throw new Exception("Error al ejecutar la consulta");
    }

    $result = $stmt->get_result();
    $data = $result->fetch_assoc();
    $stmt->close();

    return $data;
}

try {
    $producto = obtenerDatos(
        $conn,
        "SELECT nombre FROM productos WHERE id = ? AND estado != 'eliminado'",
        [$id_producto],
        "i"
    );

    $color = obtenerDatos(
        $conn,
        "SELECT nombre FROM colores WHERE id = ?",
        [$id_color],
        "i"
    );

    if (!$producto || !$color) {
        header("HTTP/1.1 404 Not Found");
        die("Producto o color no encontrado");
    }

    // Obtener rollos disponibles
    $rollos_stmt = $conn->prepare("SELECT id, costo_unitario, largo_metros AS largo, ancho_metros AS ancho, 
                                area_m2 AS area, fecha_ingreso 
                                FROM inventario_rollos 
                                WHERE id_producto = ? AND id_color = ? AND estado = 'disponible'
                                ORDER BY fecha_ingreso DESC");
    $rollos_stmt->bind_param("ii", $id_producto, $id_color);
    $rollos_stmt->execute();
    $rollos = $rollos_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $rollos_stmt->close();
} catch (Exception $e) {
    error_log("Error en editar_rollos.php: " . $e->getMessage());
    die("Error al obtener datos: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="es">

<head>
    <?php include_once "../../includes/head.php"; ?>
    <link rel="stylesheet" href="<?= $URL_ROOT ?>/css/inventario/editar_rollo_color.css">
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
        <div class="contenedor-edicion">

            <div class="info-producto">
                <h3>Producto y color seleccionado</h3>
                <div style="display: flex; gap: 15px;">
                    <span class="badge badge-producto">
                        <i class="fas fa-box"></i> <?= htmlspecialchars($producto['nombre']) ?>
                    </span>
                    <span class="badge badge-color">
                        <i class="fas fa-palette"></i> <?= htmlspecialchars($color['nombre']) ?>
                    </span>
                </div>
            </div>

            <form method="POST" action="<?= $URL_ROOT ?>/php/inventario/guardar_edicion_rollos_color.php" id="formEditarRollos" style="width: 90%;">
                <input type="hidden" name="id_producto" value="<?= $id_producto ?>">
                <input type="hidden" name="id_color" value="<?= $id_color ?>">

                <h3 style="margin-bottom: 15px;">Rollos disponibles</h3>

                <?php if (empty($rollos)): ?>
                    <div class="sin-rollos">
                        <i class="fas fa-roll" style="font-size: 2em; margin-bottom: 10px; opacity: 0.5;"></i>
                        <p>No hay rollos disponibles para este producto y color</p>
                    </div>
                <?php else: ?>
                    <div class="contenedor-tabla">
                        <table class="tabla-rollos">
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>Costo</th>
                                    <th>Largo (m)</th>
                                    <th>Ancho (m)</th>
                                    <th>Área (m²)</th>
                                    <th>Fecha ingreso</th>
                                    <th>Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($rollos as $i => $r): ?>
                                    <tr data-rollo-id="<?= $r['id'] ?>">
                                        <td><?= $i + 1 ?></td>
                                        <td>
                                            $ <input type="number"
                                                name="rollos[<?= $r['id'] ?>][costo_unitario]"
                                                value="<?= htmlspecialchars($r['costo_unitario']) ?>"
                                                step="0.01"
                                                min="0.01"
                                                class="input-costo"
                                                data-original="<?= htmlspecialchars($r['costo_unitario']) ?>">
                                        </td>
                                        <td>
                                            <input type="number"
                                                name="rollos[<?= $r['id'] ?>][largo]"
                                                value="<?= htmlspecialchars($r['largo']) ?>"
                                                step="0.01"
                                                min="0.01"
                                                class="input-largo"
                                                data-original="<?= htmlspecialchars($r['largo']) ?>">
                                        </td>

                                        <td>
                                            <input type="number"
                                                name="rollos[<?= $r['id'] ?>][ancho]"
                                                value="<?= htmlspecialchars($r['ancho']) ?>"
                                                step="0.01"
                                                min="0.01"
                                                class="input-ancho"
                                                data-original="<?= htmlspecialchars($r['ancho']) ?>">
                                        </td>
                                        <td><?= number_format($r['area'], 2) ?></td>
                                        <td><?= date('d/m/Y', strtotime($r['fecha_ingreso'])) ?></td>
                                        <td>
                                            <button type="button" class="btn-eliminar" data-action="eliminar">
                                                <i class="fas fa-trash"></i> Eliminar
                                            </button>
                                            <input type="hidden"
                                                name="rollos[<?= $r['id'] ?>][eliminar]"
                                                value="0"
                                                class="input-eliminar">
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                    <div class="acciones-footer">
                        <button type="button" class="btncancel" onclick="window.history.back()">
                            <i class="fas fa-times-circle"></i> Cancelar
                        </button>
                        <button class="btnadd" style="margin: 0;" type="submit" id="btnGuardar">
                            <i class="fas fa-save"></i> Guardar Cambios
                        </button>
                    </div>
                <?php endif; ?>
            </form>
        </div>
    </main>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const form = document.getElementById('formEditarRollos');
            const btnGuardar = document.getElementById('btnGuardar');

            // Manejar eliminación de rollos
            document.querySelectorAll('[data-action="eliminar"]').forEach(btn => {
                btn.addEventListener('click', function() {
                    const row = this.closest('tr');
                    const inputEliminar = row.querySelector('.input-eliminar');

                    if (inputEliminar.value === '1') {
                        inputEliminar.value = '0';
                        row.classList.remove('rollo-eliminado');
                        row.querySelectorAll('input[type="number"]').forEach(input => {
                            input.disabled = false;
                        });
                        this.innerHTML = '<i class="fas fa-trash"></i> Eliminar';
                    } else {
                        inputEliminar.value = '1';
                        row.classList.add('rollo-eliminado');
                        row.querySelectorAll('input[type="number"]').forEach(input => {
                            input.disabled = true;
                        });
                        this.innerHTML = '<i class="fas fa-undo"></i> Deshacer';
                    }

                    actualizarEstadoGuardado();
                });
            });

            // Detectar cambios en inputs
            document.querySelectorAll('.input-largo, .input-ancho, .input-costo').forEach(input => {
                input.addEventListener('change', function() {
                    const row = this.closest('tr');
                    const original = parseFloat(this.dataset.original);
                    const actual = parseFloat(this.value);

                    if (actual !== original) {
                        row.classList.add('rollo-modificado');
                    } else {
                        row.classList.remove('rollo-modificado');
                    }

                    if (this.classList.contains('input-largo') || this.classList.contains('input-ancho')) {
                        const largoInput = row.querySelector('.input-largo');
                        const anchoInput = row.querySelector('.input-ancho');
                        const areaCell = row.querySelector('td:nth-child(4)');

                        if (largoInput && anchoInput && areaCell) {
                            const largo = parseFloat(largoInput.value) || 0;
                            const ancho = parseFloat(anchoInput.value) || 0;
                            const area = (largo * ancho).toFixed(2);
                            areaCell.textContent = area;
                        }
                    }

                    actualizarEstadoGuardado();
                });
            });

            document.querySelectorAll('input[type="number"]').forEach(input => {
                input.addEventListener('change', function() {
                    if (this.value <= 0) {
                        this.setCustomValidity('El valor debe ser mayor a 0');
                    } else {
                        this.setCustomValidity('');
                    }
                });
            });

            function actualizarEstadoGuardado() {
                const hayCambios = document.querySelectorAll('.rollo-modificado, .rollo-eliminado').length > 0;
                btnGuardar.disabled = !hayCambios;

                if (hayCambios) {
                    btnGuardar.innerHTML = '<i class="fas fa-save"></i> Guardar cambios';
                } else {
                    btnGuardar.innerHTML = '<i class="fas fa-check"></i> Sin cambios';
                }
            }

            // Manejar envío del formulario
            form.addEventListener('submit', async function(e) {
                e.preventDefault();

                const btnText = btnGuardar.innerHTML;
                btnGuardar.disabled = true;
                btnGuardar.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Guardando...';

                try {
                    const formData = new FormData(form);
                    const response = await fetch(form.action, {
                        method: "POST",
                        body: formData
                    });

                    const contentType = response.headers.get('content-type');
                    if (!contentType || !contentType.includes('application/json')) {
                        const text = await response.text();
                        throw new Error(`Respuesta inesperada del servidor: ${text.substring(0, 100)}`);
                    }

                    const text = await response.text();
                    console.log("Respuesta cruda del servidor:", text);

                    let data;
                    try {
                        data = JSON.parse(text);
                    } catch (e) {
                        throw new Error("Respuesta no es JSON válido:\n" + text);
                    }


                    if (!response.ok) {
                        throw new Error(data.mensaje || `Error ${response.status}`);
                    }

                    if (data.status === 1) {
                        displayPopUp();
                        displayMensajeExitoso(data.mensaje || "Cambios guardados correctamente", "window.history.back()");
                    } else {
                        throw new Error(data.mensaje || "Error al guardar los cambios");
                    }
                } catch (error) {
                    console.error("Error:", error);
                    displayPopUp();
                    displayMensajeError(error.message);
                    btnGuardar.innerHTML = '<i class="fas fa-save"></i> Guardar Cambios';
                    btnGuardar.disabled = false;
                }
            });
            actualizarEstadoGuardado();
        });
    </script>

    <?php include_once "../../includes/popup.php"; ?>
</body>

</html>