<?php
$ROOT = '../..';
$TITULO = "Inventario de producto";

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

// Obtener información del producto y su inventario
$sql = "SELECT 
            p.*, 
            u.nombre AS unidad_nombre, 
            u.simbolo,
            COALESCE(SUM(CASE WHEN m.tipo_movimiento = 'entrada' THEN m.cantidad ELSE -m.cantidad END), 0) AS cantidad_disponible,
            COALESCE(SUM(CASE WHEN m.tipo_movimiento = 'entrada' THEN (m.cantidad * m.costo_unitario) ELSE -(m.cantidad * m.costo_unitario) END), 0) AS costo_total_inventario
        FROM productos p
        JOIN unidades u ON u.id = p.id_unidad
        LEFT JOIN movimientos_inventario m ON m.id_producto = p.id
        WHERE p.id = ?
        GROUP BY p.id, u.nombre, u.simbolo";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $id);
$stmt->execute();
$producto = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$producto) {
    header("Location: lista.php?error=producto_no_encontrado");
    exit();
}

// Obtener historial de movimientos con información de lotes
$sqlMovimientos = "SELECT 
                    m.*, 
                    a.nombre AS admin_nombre,
                    a.apellido AS admin_apellido,
                    l.descripcion AS lote_descripcion,
                    l.fecha_creacion AS lote_fecha
                FROM movimientos_inventario m
                LEFT JOIN admins a ON a.id = m.id_admin
                LEFT JOIN lotes l ON l.id = m.id_lote
                WHERE m.id_producto = ?
                ORDER BY m.fecha DESC";
$stmt = $conn->prepare($sqlMovimientos);
$stmt->bind_param("i", $id);
$stmt->execute();
$movimientos = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Obtener lotes disponibles para este producto
$sqlLotes = "SELECT 
                l.id, 
                l.descripcion, 
                l.fecha_creacion,
                COALESCE((
                    SELECT m.costo_unitario 
                    FROM movimientos_inventario m 
                    WHERE m.id_lote = l.id AND m.tipo_movimiento = 'entrada' 
                    LIMIT 1
                ), 0) AS costo_unitario,
                SUM(CASE WHEN m.tipo_movimiento = 'entrada' THEN m.cantidad ELSE -m.cantidad END) AS cantidad_disponible
            FROM lotes l
            JOIN movimientos_inventario m ON m.id_lote = l.id
            WHERE l.id_producto = ? AND m.id_producto = ?
            GROUP BY l.id, l.descripcion, l.fecha_creacion
            HAVING cantidad_disponible > 0
            ORDER BY l.fecha_creacion DESC";
$stmt = $conn->prepare($sqlLotes);
$stmt->bind_param("ii", $id, $id);
$stmt->execute();
$lotes = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();
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
                    <p><strong>Tipo:</strong> <?= $producto['tipo_inventario'] === 'unidad' ? 'Por unidad' : 'Por rollo' ?></p>

                    <div class="mt-3">
                        <h3>Inventario actual</h3>
                        <div class="cantidad-disponible">
                            <?= number_format($producto['cantidad_disponible'], 2) ?> <?= htmlspecialchars($producto['simbolo']) ?>
                        </div>
                        <div>
                            Valor total: $<?= number_format($producto['costo_total_inventario'], 2) ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Tabs para Lotes/Historial -->
            <div class="tabs-inventario">
                <div class="tab-inventario active" data-tab="lotes">Lotes</div>
                <div class="tab-inventario" data-tab="historial">Historial</div>
            </div>

            <!-- Contenido de Lotes -->
            <div class="tab-inventario-content active" id="lotes">
                <?php if (!empty($lotes)): ?>
                    <div class="table-responsive">
                        <table class="tabla-lista">
                            <thead>
                                <tr>
                                    <th>Lote</th>
                                    <th>Descripción</th>
                                    <th>Fecha</th>
                                    <th>Cantidad</th>
                                    <th>Costo Unitario</th>
                                    <th>Total</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($lotes as $lote): ?>
                                    <tr>
                                        <td><span class="badge-lote"># <?= $lote['id'] ?></span></td>
                                        <td><?= htmlspecialchars($lote['descripcion'] ?? 'Sin descripción') ?></td>
                                        <td><?= date('d/m/Y', strtotime($lote['fecha_creacion'])) ?></td>
                                        <td><?= number_format($lote['cantidad_disponible'], 2) ?></td>
                                        <td>$<?= number_format($lote['costo_unitario'], 2) ?></td>
                                        <td>$<?= number_format($lote['cantidad_disponible'] * $lote['costo_unitario'], 2) ?></td>
                                        
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="text-center py-4">
                        <p>No hay lotes disponibles para este producto</p>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Contenido de Historial -->
            <div class="tab-inventario-content" id="historial">
                <div class="table-responsive">
                    <table class="tabla-lista">
                        <thead>
                            <tr>
                                <th>Fecha</th>
                                <th>Tipo</th>
                                <th>Lote</th>
                                <th>Cantidad</th>
                                <th>Costo Unitario</th>
                                <th>Total</th>
                                <th>Responsable</th>
                                <th>Motivo</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($movimientos as $mov): ?>
                                <tr>
                                    <td><?= date('d/m/Y H:i', strtotime($mov['fecha'])) ?></td>
                                    <td>
                                        <span class="badge-<?= $mov['tipo_movimiento'] == 'entrada' ? 'entrada' : 'salida' ?>">
                                            <?= ucfirst($mov['tipo_movimiento']) ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php if ($mov['id_lote']): ?>
                                            <span class="badge-lote">Lote #<?= $mov['id_lote'] ?></span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?= number_format($mov['cantidad'], 2) ?></td>
                                    <td>$<?= number_format($mov['costo_unitario'], 2) ?></td>
                                    <td>$<?= number_format($mov['cantidad'] * $mov['costo_unitario'], 2) ?></td>
                                    <td>
                                        <?= $mov['admin_nombre'] ? htmlspecialchars($mov['admin_nombre'] . ' ' . $mov['admin_apellido']) : 'Sistema' ?>
                                    </td>
                                    <td><?= htmlspecialchars($mov['motivo']) ?></td>
                                </tr>
                            <?php endforeach; ?>
                            <?php if (empty($movimientos)): ?>
                                <tr>
                                    <td colspan="8" class="text-center">No hay movimientos registrados</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <h3 class="mt-4">Agregar/Quitar inventario</h3>
            <form id="formMovimientoInventario" method="POST" action="<?php echo $URL_ROOT; ?>/php/inventario/guardar_movimiento.php" class="formulario-inputs">
                <input type="hidden" name="id_producto" value="<?= $producto['id'] ?>">

                <div class="grid-formulario">
                    <div class="grid-item">
                        <label>Tipo de movimiento</label>
                        <select name="tipo_movimiento" class="textfield" required>
                            <option value="entrada">Entrada (Agregar)</option>
                            <option value="salida">Salida (Quitar)</option>
                        </select>
                    </div>

                    <div class="grid-item">
                        <label>Cantidad (<?= htmlspecialchars($producto['unidad_nombre']) ?>)</label>
                        <input type="number" name="cantidad" min="0.01" step="0.01" class="textfield" required>
                    </div>

                    <div class="grid-item">
                        <label>Costo unitario</label>
                        <input type="number" name="costo_unitario" min="0" step="0.01" class="textfield" required>
                    </div>

                    <div class="grid-item">
                        <label>Lote</label>
                        <select name="id_lote" class="textfield">
                            <option value="nuevo">Nuevo lote</option>
                            <?php foreach ($lotes as $lote): ?>
                                <option value="<?= $lote['id'] ?>">Lote #<?= $lote['id'] ?> - <?= htmlspecialchars(substr($lote['descripcion'] ?? 'Sin descripción', 0, 30)) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="grid-item">
                        <label>Descripción del lote</label>
                        <input type="text" name="lote_descripcion" class="textfield" placeholder="Ej: Compra a proveedor X">
                    </div>

                    <div class="grid-item">
                        <label>Motivo/Comentario</label>
                        <input type="text" name="motivo" class="textfield" placeholder="Opcional">
                    </div>

                    <div class="grid-item grid-item-full acciones-formulario">
                        <button type="submit" class="btnadd">
                            <i class="fas fa-save"></i> Guardar movimiento
                        </button>
                    </div>
                </div>
            </form>
        </div>

        <?php include_once '../../includes/popup.php'; ?>
    </main>
    <script src="../../scripts/inventario/editar_cantidad.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const form = document.getElementById('formMovimientoInventario');
            if (form) {
                form.dataset.disponible = <?= json_encode($producto['cantidad_disponible']) ?>;
            }
        });
    </script>
</body>

</html>