<?php
$ROOT = '../..';
$TITULO = "Primer ingreso de rollos";

include_once "$ROOT/db/conexion.php";
include_once "$ROOT/includes/sesion.php";
include_once "$ROOT/includes/config.php";

if (!tieneSesion()) {
    header("Location: $URL_ROOT/login");
    exit();
}

$id = isset($_GET['id']) ? intval($_GET['id']) : 0;
if ($id <= 0) {
    header("Location: lista.php?error=id_invalido");
    exit();
}

// Consultar información del producto
$sql_producto = "SELECT p.id, p.nombre, p.descripcion, p.id_tipo_producto, u.simbolo, m.nombre AS modelo_nombre, p.imagen, m.altura_mm, p.tipo_inventario
                FROM productos p
                JOIN unidades u ON p.id_unidad = u.id
                LEFT JOIN modelos m ON p.id_modelo = m.id
                WHERE p.id = ? LIMIT 1";
$stmt = $conn->prepare($sql_producto);
$stmt->bind_param("i", $id);
$stmt->execute();
$producto = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$producto) {
    header("Location: lista.php?error=producto_no_encontrado");
    exit();
}

// Verificar si ya tiene inventario
$sql_inventario = "SELECT COUNT(*) AS total FROM movimientos_inventario WHERE id_producto = ?";
$stmt = $conn->prepare($sql_inventario);
$stmt->bind_param("i", $id);
$stmt->execute();
$tieneInventario = $stmt->get_result()->fetch_assoc()['total'] > 0;
$stmt->close();

// Redirigir si ya tiene inventario
if ($tieneInventario) {
    header("Location: editar_rollos.php?id=$id");
    exit();
}

// Consultar colores disponibles para este producto
$colores_disponibles = [];
if ($producto['tipo_inventario'] === 'rollo') {
    $sql_colores = "SELECT c.id, c.nombre, c.codigo_hex 
                   FROM colores c
                   JOIN producto_colores pc ON c.id = pc.id_color
                   WHERE pc.id_producto = ?
                   ORDER BY c.nombre ASC";
    $stmt = $conn->prepare($sql_colores);
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $colores_disponibles = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    // Si no hay colores asignados pero es rollo, mostrar error
    if (empty($colores_disponibles)) {
        header("Location: lista.php?error=producto_sin_colores");
        exit();
    }
} else {
    // Si no es rollo, redirigir a inventariar normal
    header("Location: inventariar.php?id=$id");
    exit();
}
// Manejar envío del formulario
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $largo = floatval($_POST['largo'] ?? 0);
    $ancho = floatval($_POST['ancho'] ?? 0);
    $cantidad = intval($_POST['cantidad'] ?? 0);
    $id_color = intval($_POST['id_color'] ?? 0);
    $costo_unitario = floatval($_POST['costo_unitario'] ?? 0);
    $motivo = trim($_POST['motivo'] ?? 'Primer ingreso');
    $lote_descripcion = trim($_POST['lote_descripcion'] ?? 'Lote inicial');
    $id_admin = $_SESSION['usuario_id'] ?? null;

    // Validaciones básicas
    if ($largo <= 0 || $ancho <= 0 || $cantidad <= 0 || $id_color <= 0 || $costo_unitario <= 0) {
        header("Location: inventariar_rollos.php?id=$id&error=datos_invalidos");
        exit();
    }

    try {
        // Iniciar transacción
        $conn->begin_transaction();

        // Crear nuevo lote
        $stmt = $conn->prepare("INSERT INTO lotes (descripcion, id_admin, id_producto) VALUES (?, ?, ?)");
        $stmt->bind_param("sii", $lote_descripcion, $id_admin, $id);
        if (!$stmt->execute()) {
            throw new Exception('Error al crear el lote: ' . $stmt->error);
        }
        $id_lote = $stmt->insert_id;
        $stmt->close();

        // Registrar el movimiento de entrada
        $stmt = $conn->prepare("
            INSERT INTO movimientos_inventario 
                (id_producto, id_lote, cantidad, costo_unitario, tipo_movimiento, motivo, id_admin) 
            VALUES (?, ?, ?, ?, 'entrada', ?, ?)
        ");
        $stmt->bind_param("iiddsi", $id, $id_lote, $cantidad, $costo_unitario, $motivo, $id_admin);
        if (!$stmt->execute()) {
            throw new Exception('Error al registrar el movimiento: ' . $stmt->error);
        }
        $stmt->close();

        // Registrar los rollos en inventario_rollos
        $stmt = $conn->prepare("
            INSERT INTO inventario_rollos 
                (id_producto, id_lote, id_color, largo_metros, ancho_metros, costo_unitario, estado) 
            VALUES (?, ?, ?, ?, ?, ?, 'disponible')
        ");

        for ($i = 0; $i < $cantidad; $i++) {
            $stmt->bind_param("iiiddd", $id, $id_lote, $id_color, $largo, $ancho, $costo_unitario);
            if (!$stmt->execute()) {
                throw new Exception('Error al registrar el rollo: ' . $stmt->error);
            }
        }
        $stmt->close();

        $conn->commit();

        header("Location: editar_rollos.php?id=$id&id_color=$id_color");
        exit();
    } catch (Exception $e) {
        $conn->rollback();
        error_log("Error en inventariar_rollos.php: " . $e->getMessage());
        header("Location: inventariar_rollos.php?id=$id&error=procesamiento");
        exit();
    }
}
?>

<!DOCTYPE html>
<html lang="es">

<head>
    <?php include_once "$ROOT/includes/head.php"; ?>
    <link rel="stylesheet" href="../../css/inventario/editar_inventario.css">
    <title><?= $TITULO ?> - <?= htmlspecialchars($producto['nombre']) ?></title>
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
        <div class="card-inventario-detalle">
            <div class="info-inventario">
                <?php if (!empty($producto['imagen'])): ?>
                    <img src="../../img/productos/<?= $producto['imagen'] ?>?nocache=<?= uniqid() ?>" class="imagen-producto" alt="<?= htmlspecialchars($producto['nombre']) ?>">
                <?php else: ?>
                    <div class="no-imagen">
                        <i class="fas fa-box-open fa-3x"></i>
                    </div>
                <?php endif; ?>

                <div class="detalles-producto">
                    <h2>
                        <?= htmlspecialchars($producto['nombre']) ?>
                        <span style="font-style: italic; font-size: 0.9em; font-weight: normal;">
                            <?= htmlspecialchars($producto['modelo_nombre']) ?>
                            <?php if (!empty($producto['altura_mm'])): ?>
                                &mdash; <?= htmlspecialchars($producto['altura_mm']) ?> mm
                            <?php endif; ?>
                        </span>
                    </h2>
                    <p><?= htmlspecialchars($producto['descripcion']) ?></p>

                    <div class="mt-3">
                        <h3>Inventario actual</h3>
                        <div class="cantidad-disponible">
                            0 rollos (0.00 m²)
                        </div>
                    </div>
                </div>
            </div>

            <h3 class="mt-4">Registrar primer ingreso de rollos</h3>
            <form id="formPrimerIngresoRollos" method="POST" action="../../php/inventario/guardar_primer_ingreso_rollos.php?id=<?= $id ?>" class="formulario-inputs">
                <input type="hidden" name="id_producto" value="<?= $id ?>">

                <div class="grid-formulario">
                    <div class="grid-item">
                        <label>Largo (metros)</label>
                        <input type="number" name="largo" step="0.01" min="0.01" required class="textfield">
                    </div>

                    <div class="grid-item">
                        <label>Ancho (metros)</label>
                        <input type="number" name="ancho" step="0.01" min="0.01" required class="textfield">
                    </div>

                    <div class="grid-item">
                        <label>Cantidad de rollos</label>
                        <input type="number" name="cantidad" min="1" required class="textfield">
                    </div>

                    <div class="grid-item">
                        <label>Color</label>
                        <select name="id_color" required class="textfield">
                            <option value="">Seleccionar color</option>
                            <?php foreach ($colores_disponibles as $color): ?>
                                <option value="<?= $color['id'] ?>"><?= htmlspecialchars($color['nombre']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="grid-item">
                        <label>Costo por rollo</label>
                        <input type="number" name="costo_unitario" step="0.01" min="0.01" required class="textfield">
                    </div>

                    <div class="grid-item">
                        <label>Descripción del lote</label>
                        <input type="text" name="lote_descripcion" class="textfield" placeholder="Ej: Compra inicial" required>
                    </div>

                    <div class="grid-item">
                        <label>Motivo/Comentario</label>
                        <input type="text" name="motivo" class="textfield" placeholder="Opcional">
                    </div>

                    <div class="grid-item grid-item-full">
                        <div id="costo-total-display" class="costo-total">Costo total: $0.00</div>
                    </div>

                    <div class="grid-item grid-item-full acciones-formulario">
                        <button type="submit" class="btnadd">
                            <i class="fas fa-save"></i> Registrar ingreso inicial
                        </button>
                    </div>
                </div>
            </form>
        </div>

        <?php include_once '../../includes/popup.php'; ?>
    </main>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Inicializar cálculo de costo total
            const form = document.getElementById('formPrimerIngresoRollos');
            if (form) {
                const largoInput = form.querySelector('[name="largo"]');
                const anchoInput = form.querySelector('[name="ancho"]');
                const cantidadInput = form.querySelector('[name="cantidad"]');
                const costoInput = form.querySelector('[name="costo_unitario"]');
                const costoTotalDisplay = document.getElementById('costo-total-display');

                function calcularCostoTotal() {
                    const largo = parseFloat(largoInput.value) || 0;
                    const ancho = parseFloat(anchoInput.value) || 0;
                    const cantidad = parseInt(cantidadInput.value) || 0;
                    const costo = parseFloat(costoInput.value) || 0;

                    const area = largo * ancho;
                    const costoTotal = cantidad * costo;

                    if (costoTotalDisplay) {
                        costoTotalDisplay.textContent = `Costo total: $${costoTotal.toFixed(2)}`;
                    }
                }

                [largoInput, anchoInput, cantidadInput, costoInput].forEach(input => {
                    if (input) input.addEventListener('input', calcularCostoTotal);
                });

                // Manejar envío del formulario con AJAX
                form.addEventListener('submit', function(e) {
                    e.preventDefault();

                    const formData = new FormData(form);
                    const largo = parseFloat(formData.get('largo'));
                    const ancho = parseFloat(formData.get('ancho'));
                    const cantidad = parseInt(formData.get('cantidad'));
                    const costo = parseFloat(formData.get('costo_unitario'));
                    const id_color = parseInt(formData.get('id_color'));

                    // Validaciones
                    if (isNaN(largo) || largo <= 0) {
                        displayMensajeError("El largo debe ser mayor a cero");
                        return;
                    }

                    if (isNaN(ancho) || ancho <= 0) {
                        displayMensajeError("El ancho debe ser mayor a cero");
                        return;
                    }

                    if (isNaN(cantidad) || cantidad <= 0) {
                        displayMensajeError("La cantidad debe ser mayor a cero");
                        return;
                    }

                    if (isNaN(costo) || costo <= 0) {
                        displayMensajeError("El costo por m² debe ser mayor a cero");
                        return;
                    }

                    if (isNaN(id_color) || id_color <= 0) {
                        displayMensajeError("Debe seleccionar un color");
                        return;
                    }

                    const area = largo * ancho;
                    const costoTotal = cantidad * costo;

                    if (!confirm(`¿Confirmar ${cantidad} rollos de ${largo}m × ${ancho}m (${area.toFixed(2)}m² cada uno) con costo individual de $${costo.toFixed(2)} y un total de $${costoTotal.toFixed(2)}?`)) {
                        return;
                    }

                    displayPopUp('Procesando primer ingreso...');

                    const btn = form.querySelector('button[type="submit"]');
                    btn.disabled = true;
                    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Procesando...';

                    fetch(form.action, {
                            method: 'POST',
                            body: formData
                        })
                        .then(response => {
                            if (response.redirected) {
                                window.location.href = response.url;
                            } else {
                                return response.json();
                            }
                        })
                        .then(data => {
                            if (data && data.status === 1) {
                                displayMensajeExitoso(data.mensaje, () => {
                                    window.location.href = `editar_rollos.php?id=${<?= $id ?>}&id_color=${id_color}`;
                                });
                            } else if (data) {
                                throw new Error(data.mensaje || "Error al guardar");
                            }
                        })
                        .catch(error => {
                            console.error("Error:", error);
                            displayMensajeError(error.message);
                        })
                        .finally(() => {
                            btn.disabled = false;
                            btn.innerHTML = '<i class="fas fa-save"></i> Registrar ingreso inicial';
                        });
                });
            }
        });
    </script>
</body>

</html>