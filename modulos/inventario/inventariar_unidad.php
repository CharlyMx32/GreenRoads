<?php
$ROOT = '../..';
$TITULO = "Primer ingreso de producto";

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

// Obtener información básica del producto
$sql = "SELECT 
            p.*, 
            u.nombre AS unidad_nombre, 
            u.simbolo,
            tp.nombre AS tipo_producto
        FROM productos p
        JOIN unidades u ON u.id = p.id_unidad
        JOIN tipo_productos tp ON tp.id = p.id_tipo_producto
        WHERE p.id = ? LIMIT 1";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $id);
$stmt->execute();
$producto = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$producto) {
    header("Location: lista.php?error=producto_no_encontrado");
    exit();
}

// Verificar si ya tiene inventario
$sqlInventario = "SELECT COUNT(*) AS total FROM movimientos_inventario WHERE id_producto = ?";
$stmt = $conn->prepare($sqlInventario);
$stmt->bind_param("i", $id);
$stmt->execute();
$tieneInventario = $stmt->get_result()->fetch_assoc()['total'] > 0;
$stmt->close();

// Redirigir si ya tiene inventario
if ($tieneInventario) {
    header("Location: editar_cantidad.php?id=$id");
    exit();
}
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <?php include_once "$ROOT/includes/head.php"; ?>
    <link rel="stylesheet" href="../../css/inventario/editar_inventario.css">
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
                    <h2><?= htmlspecialchars($producto['nombre']) ?></h2>
                    <p><?= htmlspecialchars($producto['descripcion']) ?></p>
                    <p><strong>Tipo:</strong> <?= htmlspecialchars($producto['tipo_producto']) ?> (<?= $producto['tipo_inventario'] === 'unidad' ? 'Por unidad' : 'Por rollo' ?>)</p>

                    <div class="mt-3">
                        <h3>Inventario actual</h3>
                        <div class="cantidad-disponible">
                            0 <?= htmlspecialchars($producto['simbolo']) ?>
                        </div>
                        <div>
                            Valor total: $0.00
                        </div>
                    </div>
                </div>
            </div>

            <h3 class="mt-4">Registrar primer ingreso</h3>
            <form id="formPrimerIngreso" method="POST" action="<?php echo $URL_ROOT; ?>/php/inventario/guardar_primer_ingreso.php" class="formulario-inputs">
                <input type="hidden" name="id_producto" value="<?= $producto['id'] ?>">

                <div class="grid-formulario">
                    <div class="grid-item">
                        <label>Cantidad (<?= htmlspecialchars($producto['unidad_nombre']) ?>)</label>
                        <input type="number" name="cantidad" min="0.01" step="0.01" class="textfield" required>
                    </div>

                    <div class="grid-item">
                        <label>Costo unitario</label>
                        <input type="number" name="costo_unitario" min="0.01" step="0.01" class="textfield" required>
                    </div>

                    <div class="grid-item">
                        <label>Lote (obligatorio)</label>
                        <input type="text" name="lote_descripcion" class="textfield" placeholder="Ej: Compra inicial" required>
                        <small class="text-muted">Identificador único para este lote</small>
                    </div>

                    <div class="grid-item">
                        <label>Motivo/Comentario</label>
                        <input type="text" name="motivo" class="textfield" placeholder="Opcional">
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
            const form = document.getElementById('formPrimerIngreso');
            if (!form) return;

            form.addEventListener('submit', function(e) {
                e.preventDefault();

                displayPopUp('Procesando primer ingreso...');

                const formData = new FormData(form);
                const cantidad = parseFloat(formData.get('cantidad'));
                const costo = parseFloat(formData.get('costo_unitario'));

                // Validaciones
                if (isNaN(cantidad) || cantidad <= 0) {
                    displayMensajeError("La cantidad debe ser mayor a cero");
                    return;
                }

                if (isNaN(costo) || costo <= 0) {
                    displayMensajeError("El costo unitario debe ser mayor a cero");
                    return;
                }

                if (!formData.get('lote_descripcion')) {
                    displayMensajeError("Debe especificar una descripción para el lote");
                    return;
                }

                if (!confirm(`¿Confirmar ingreso inicial de ${cantidad} unidades con costo unitario de $${costo.toFixed(2)}?`)) {
                    hidePopup();
                    return;
                }

                const btn = form.querySelector('button[type="submit"]');
                btn.disabled = true;
                btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Procesando...';

                fetch(form.action, {
                        method: 'POST',
                        body: formData
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.status === 1) {
                            displayMensajeExitoso(data.mensaje, () => {
                                window.location.href = `editar_cantidad.php?id=${<?= $id ?>}`;
                            });
                        } else {
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
        });
    </script>
</body>

</html>