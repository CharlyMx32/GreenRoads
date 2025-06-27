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

// Función para obtener datos con manejo de errores
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

// Obtener información del producto y color con manejo de errores
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
    $rollos_stmt = $conn->prepare("SELECT id, largo_metros AS largo, ancho_metros AS ancho, 
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
    <link rel="stylesheet" href="<?= $URL_ROOT ?>/css/material.css">
    <style>
        /* Contenedor principal centrado */
        .contenedor-central {
            max-width: 1200px;
            margin: 0 auto;
            padding: 30px 0;
        }

        /* Tabla de rollos */
        .tabla-rollos {
            border-collapse: collapse;
            width: 100%;
            font-size: 0.95em;
        }

        /* Estilos para celdas y encabezados */
        .tabla-rollos th,
        .tabla-rollos td {
            border: 1px solid #ccc;
            padding: 8px 10px;
            text-align: center;
        }

        /* Encabezados de tabla */
        .tabla-rollos th {
            background-color: #7dc042;
            color: white;
        }

        /* Contenedor con scroll para tabla */
        .contenedor-tabla {
            max-height: 280px;
            overflow-y: auto;
            width: 100%;
        }

        /* Inputs numéricos en tabla */
        .tabla-rollos input[type="number"] {
            width: 80px;
            text-align: center;
            border: 1px solid #ccc;
            border-radius: 4px;
            padding: 4px;
        }

        /* Botón flotante en parte inferior */
        .btn-flotante {
            position: sticky;
            bottom: 0;
            padding: 15px 0;
            text-align: center;
            border-top: 1px solid #eee;
        }
    </style>
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
        <div class="contenedor-central">
            <h1 class="titulo-seccion">Editar rollos del producto</h1>
            <h2 class="subtitulo-seccion" aria-live="polite">
                <?= htmlspecialchars($producto['nombre']) ?> — Color: <?= htmlspecialchars($color['nombre']) ?>
            </h2>

            <form method="POST" action="<?= $URL_ROOT ?>/php/inventario/guardar_edicion_rollos.php"
                id="formEditarRollos" aria-labelledby="tituloFormulario" style="width: auto;">

                <input type="hidden" name="id_producto" value="<?= $id_producto ?>">
                <input type="hidden" name="id_color" value="<?= $id_color ?>">

                <div class="contenedor-tabla" role="region" aria-labelledby="tablaRollosLabel" tabindex="0">
                    <table class="tabla-rollos" aria-describedby="tablaRollosDesc">
                        <caption id="tablaRollosLabel" class="sr-only">Lista de rollos disponibles</caption>
                        <p id="tablaRollosDesc" class="sr-only">Tabla que muestra los rollos disponibles con opciones para editar o eliminar</p>

                        <thead>
                            <tr>
                                <th scope="col">#</th>
                                <th scope="col">Largo (m)</th>
                                <th scope="col">Ancho (m)</th>
                                <th scope="col">Área (m²)</th>
                                <th scope="col">Fecha ingreso</th>
                                <th scope="col">Eliminar</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($rollos)): ?>
                                <tr>
                                    <td colspan="6" style="text-align:center;">No hay rollos disponibles</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($rollos as $i => $r): ?>
                                    <tr>
                                        <td><?= $i + 1 ?></td>
                                        <td>
                                            <input type="number"
                                                name="rollos[<?= $r['id'] ?>][largo]"
                                                step="0.01"
                                                min="0.01"
                                                class="textfield"
                                                value="<?= htmlspecialchars($r['largo']) ?>"
                                                required
                                                aria-label="Largo en metros para rollo <?= $i + 1 ?>">
                                        </td>
                                        <td>
                                            <input type="number"
                                                name="rollos[<?= $r['id'] ?>][ancho]"
                                                step="0.01"
                                                min="0.01"
                                                class="textfield"
                                                value="<?= htmlspecialchars($r['ancho']) ?>"
                                                required
                                                aria-label="Ancho en metros para rollo <?= $i + 1 ?>">
                                        </td>
                                        <td><?= number_format($r['area'], 2) ?></td>
                                        <td><?= date('d/m/Y', strtotime($r['fecha_ingreso'])) ?></td>
                                        <td>
                                            <label class="container-checkbox-tabla">
                                                <input type="checkbox"
                                                    name="rollos[<?= $r['id'] ?>][eliminar]"
                                                    value="1"
                                                    aria-label="Marcar para eliminar rollo <?= $i + 1 ?>">
                                                <span class="checkmark-tabla"></span>
                                            </label>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <?php if (!empty($rollos)): ?>
                    <div class="btn-flotante">
                        <button type="submit" class="btnadd" id="btnGuardar">
                            <span class="btn-text">Guardar cambios</span>
                            <span class="btn-loading" hidden>
                                <i class="fas fa-spinner fa-spin"></i> Procesando...
                            </span>
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
            const btnText = btnGuardar.querySelector('.btn-text');
            const btnLoading = btnGuardar.querySelector('.btn-loading');

            function hayCambios() {
                const inputs = form.querySelectorAll('input[type="number"]');
                let cambios = false;

                inputs.forEach(input => {
                    if (input.defaultValue !== input.value) {
                        cambios = true;
                    }
                });

                return cambios || form.querySelectorAll('input[type="checkbox"]:checked').length > 0;
            }

            // Manejo de envío con async/await
            form.addEventListener('submit', async function(e) {
                e.preventDefault();

                if (!hayCambios()) {
                    displayMensajeError("No hay cambios para guardar.");
                    return;
                }

                // Mostrar estado de carga
                btnText.hidden = true;
                btnLoading.hidden = false;
                btnGuardar.disabled = true;

                try {
                    const formData = new FormData(form);
                    const response = await fetch("../../php/inventario/guardar_edicion_rollos.php", {
                        method: "POST",
                        body: formData
                    });

                    if (!response.ok) {
                        throw new Error(`Error HTTP: ${response.status}`);
                    }

                    const data = await response.json();

                    if (data.status === 1) {
                        displayPopUp();
                        displayMensajeExitoso(data.mensaje || "Cambios guardados", "window.history.back()");
                    } else {
                        throw new Error(data.mensaje || "Error inesperado");
                    }
                } catch (error) {
                    console.error("Error:", error);
                    displayPopUp();
                    displayMensajeError("Error al guardar: " + error.message);
                } finally {
                    btnText.hidden = false;
                    btnLoading.hidden = true;
                    btnGuardar.disabled = false;
                }
            });

            // Validación en tiempo real para inputs
            form.querySelectorAll('input[type="number"]').forEach(input => {
                input.addEventListener('change', function() {
                    if (this.value <= 0) {
                        this.setCustomValidity('El valor debe ser mayor a 0');
                    } else {
                        this.setCustomValidity('');
                    }
                });
            });
        });
    </script>

    <?php include_once "../../includes/popup.php"; ?>
</body>

</html>