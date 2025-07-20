<?php

/**
 * Controlador para edición de cantidades de inventario
 * 
 * Permite modificar manualmente la cantidad disponible de un producto
 */

$ROOT = '../..';
$TITULO = "Editar inventario";

require_once $ROOT . '/db/conexion.php';
require_once $ROOT . '/includes/sesion.php';
require_once $ROOT . '/includes/config.php';

if (!tieneSesion()) {
    header("Location: $URL_ROOT/login");
    exit();
}


$id = isset($_GET['id']) ? intval($_GET['id']) : 0;
if ($id <= 0) {
    mostrarErrorYRedirigir('ID inválido', 'lista.php');
    exit();
}

/**
 * Función reutilizable para mostrar errores
 */
function mostrarErrorYRedirigir($mensaje, $pagina)
{
    echo "<script>
        alert('" . addslashes($mensaje) . "');
        window.location.href = '" . htmlspecialchars($pagina) . "';
    </script>";
    exit();
}


$sql = "SELECT 
            p.id AS id_producto,
            p.nombre,
            p.descripcion,
            p.id_unidad,
            u.nombre AS unidad_nombre,
            u.simbolo,
            p.estado,
            p.id_tipo_producto,
            COALESCE((
                SELECT SUM(CASE WHEN tipo_movimiento = 'entrada' THEN cantidad ELSE 0 END) -
                    SUM(CASE WHEN tipo_movimiento IN ('salida', 'reserva') THEN cantidad ELSE 0 END) +
                    SUM(CASE WHEN tipo_movimiento = 'liberacion' THEN cantidad ELSE 0 END)
                FROM movimientos_inventario mi WHERE mi.id_producto = p.id
                ), 0) AS cantidad_actual
        FROM productos p
        JOIN unidades u ON u.id = p.id_unidad
        WHERE p.id = ?
        LIMIT 1";

try {
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        throw new Exception("Error al preparar la consulta");
    }

    $stmt->bind_param("i", $id);
    if (!$stmt->execute()) {
        throw new Exception("Error al ejecutar la consulta");
    }

    $result = $stmt->get_result();
    $producto = $result->fetch_assoc();
    $stmt->close();

    if (!$producto) {
        mostrarErrorYRedirigir('Producto no encontrado', 'lista.php');
    }
} catch (Exception $e) {
    error_log("Error en editar_cantidad.php: " . $e->getMessage());
    mostrarErrorYRedirigir('Error al obtener datos del producto', 'lista.php');
}

$cantidad_actual = $producto['cantidad_actual'];

if (!is_numeric($cantidad_actual)) {
    mostrarErrorYRedirigir('Cantidad inválida', 'lista.php');
}

$cantidad_actual = floatval($cantidad_actual);


$id_producto = intval($producto['id_producto']);
$unidad = htmlspecialchars($producto['simbolo']);
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <?php include_once $ROOT . '/includes/head.php'; ?>
    <style>
        .mensaje-error {
            display: none;
            padding: 10px;
            margin: 10px 0;
            background-color: #ffebee;
            color: #c62828;
            border-radius: 4px;
        }
    </style>
</head>

<body>
    <?php
    $headerParams = [
        "titulo" => $TITULO,
        "btn_atras" => "window.history.back()"
    ];
    include_once '../../includes/header.php';
    ?>

    <main class="content">
        <div class="formulario active">
            <div class="seccion-formulario">
                <h1 class="subtitulo-formulario" style="margin-top: -100px;">
                    Editar cantidad de: <strong><?= htmlspecialchars($producto['nombre']) ?></strong>
                </h1>

                <div class="mensaje-error" id="mensajeError" role="alert" aria-live="assertive"></div>

                <form id="formEditarCantidad" aria-labelledby="tituloFormulario">
                    <input type="hidden" name="id_producto" value="<?= $id_producto ?>">
                    <input type="hidden" name="cantidad_actual" value="<?= $cantidad_actual ?>">

                    <div class="campo-formulario">
                        <label for="cantidad-actual">Cantidad actual (<?= $unidad ?>):</label>
                        <input type="number" step="0.01" class="textfield" id="cantidad-actual"
                            disabled value="<?= number_format($cantidad_actual, 2) ?>">
                    </div>

                    <div class="campo-formulario">
                        <label for="nueva_cantidad">Nueva cantidad (<?= $unidad ?>):</label>
                        <input type="number" step="0.01" min="0" class="textfield"
                            name="nueva_cantidad" id="nueva_cantidad" required
                            aria-describedby="ayuda-cantidad">
                        <small id="ayuda-cantidad" class="texto-ayuda">Ingrese la nueva cantidad en <?= $unidad ?></small>
                    </div>

                    <div class="acciones-formulario">
                        <button type="submit" class="btnadd" id="btnGuardar">
                            <i class="fas fa-save"></i> Guardar
                        </button>
                        <button type="button" class="btnadd-filtro" onclick="window.history.back()">
                            <i class="fas fa-times"></i> Cancelar
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </main>
    <?php include_once $ROOT . '/../includes/popup.php'; ?>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const formulario = document.getElementById('formEditarCantidad');
            const btnGuardar = document.getElementById('btnGuardar');
            const mensajeError = document.getElementById('mensajeError');
            const inputNuevaCantidad = document.getElementById('nueva_cantidad');

            // Validación en tiempo real
            inputNuevaCantidad.addEventListener('input', function() {
                const valor = parseFloat(this.value);
                if (isNaN(valor)) {
                    mostrarError('Ingrese un número válido');
                } else if (valor < 0) {
                    mostrarError('La cantidad no puede ser negativa');
                } else {
                    ocultarError();
                }
            });

            formulario.addEventListener('submit', async function(e) {
                e.preventDefault();

                // Validación básica
                const nuevaCantidad = parseFloat(inputNuevaCantidad.value);
                if (isNaN(nuevaCantidad)) {
                    mostrarError('Ingrese una cantidad válida');
                    inputNuevaCantidad.focus();
                    return;
                }

                if (nuevaCantidad < 0) {
                    mostrarError('La cantidad no puede ser negativa');
                    inputNuevaCantidad.focus();
                    return;
                }

                btnGuardar.disabled = true;
                btnGuardar.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Procesando...';

                try {
                    const formData = new FormData(formulario);
                    const response = await fetch('../../php/inventario/guardar_unidad.php', {
                        method: 'POST',
                        body: formData
                    });

                    if (!response.ok) {
                        throw new Error('Error en la respuesta del servidor');
                    }

                    const data = await response.json();

                    if (data.status === 1) {
                        displayPopUp();
                        displayMensajeExitoso(data.mensaje || "Cambios guardados", "window.history.back()");
                    } else {
                        throw new Error(data.message || 'Error desconocido');
                    }
                } catch (error) {
                    console.error('Error:', error);
                    mostrarError('Error: ' + error.message);
                    btnGuardar.disabled = false;
                    btnGuardar.innerHTML = '<i class="fas fa-save"></i> Guardar';
                }
            });

            function mostrarError(mensaje) {
                mensajeError.textContent = mensaje;
                mensajeError.style.display = 'block';
            }

            function ocultarError() {
                mensajeError.style.display = 'none';
            }
        });
    </script>
</body>

</html>