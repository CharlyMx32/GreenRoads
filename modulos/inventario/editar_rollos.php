<?php
$ROOT = '../..';
$TITULO = "Editar inventario de rollos";

include_once $ROOT . '/db/conexion.php';
include_once $ROOT . '/includes/sesion.php';
include_once $ROOT . '/includes/config.php';

if (!tieneSesion()) {
    header("Location: $URL_ROOT/login");
    exit();
}

$id = isset($_GET['id']) ? intval($_GET['id']) : 0;
if ($id <= 0) {
    header("Location: lista.php?error=id_invalido");
    exit();
}

$id_color_activo = isset($_GET['id_color']) ? intval($_GET['id_color']) : 0;

// Consultar información del producto
$sql_producto = "SELECT p.id, p.nombre, p.descripcion, p.id_tipo_producto, u.simbolo, 
                m.nombre AS modelo_nombre, p.imagen, m.altura_mm, p.tipo_inventario
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

// Consultar colores disponibles con su inventario
$sql_rollos = "SELECT 
                c.id, 
                c.nombre, 
                c.codigo_hex,
                COUNT(r.id) AS cantidad_rollos,
                COALESCE(SUM(r.largo_metros * r.ancho_metros), 0) AS total_m2,
                COALESCE(SUM(r.costo_unitario), 0) AS costo_total
            FROM colores c
            JOIN producto_colores pc ON c.id = pc.id_color AND pc.id_producto = ?
            LEFT JOIN inventario_rollos r ON c.id = r.id_color AND r.id_producto = ? AND r.estado = 'disponible'
            GROUP BY c.id, c.nombre, c.codigo_hex
            ORDER BY c.nombre ASC";
$stmt = $conn->prepare($sql_rollos);
$stmt->bind_param("ii", $id, $id);
$stmt->execute();
$colores_disponibles = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Si no hay colores asignados, mostrar mensaje y no permitir continuar
if (empty($colores_disponibles)) {
    header("Location: lista.php?error=producto_sin_colores");
    exit();
}

// Si hay color seleccionado, obtener sus rollos disponibles
$rollos_del_color = [];
if ($id_color_activo > 0) {
    $sql_detalle = "SELECT 
        r.largo_metros, 
        r.ancho_metros, 
        COUNT(*) AS cantidad, 
        (r.largo_metros * r.ancho_metros) AS area,
        r.costo_unitario,
        l.id AS lote_id,
        l.descripcion AS lote_descripcion,
        SUM(r.largo_metros * r.ancho_metros * r.costo_unitario) AS costo_total
    FROM inventario_rollos r
    LEFT JOIN lotes l ON r.id_lote = l.id
    WHERE r.id_producto = ? AND r.id_color = ? AND r.estado = 'disponible'
    GROUP BY r.largo_metros, r.ancho_metros, r.costo_unitario, l.id, l.descripcion
    ORDER BY r.largo_metros ASC";
    $stmt = $conn->prepare($sql_detalle);
    $stmt->bind_param("ii", $id, $id_color_activo);
    $stmt->execute();
    $rollos_del_color = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
}

if ($id_color_activo <= 0 && count($colores_disponibles)) {
    $id_color_activo = $colores_disponibles[0]['id'];
    header("Location: editar_rollos.php?id=$id&id_color=$id_color_activo");
    exit();
}

$color_activo_nombre = '';
$color_activo_hex = '';
foreach ($colores_disponibles as $c) {
    if ($c['id'] == $id_color_activo) {
        $color_activo_nombre = $c['nombre'];
        $color_activo_hex = $c['codigo_hex'];
        break;
    }
}

// Consultar historial de movimientos
$historial = [];
if ($id_color_activo > 0) {
$sql_historial = "SELECT 
                    m.fecha,
                    'entrada' AS tipo_movimiento,
                    m.cantidad,
                    SUM(r.largo_metros * r.ancho_metros) AS area,
                    m.costo_total,
                    m.id_lote,
                    l.descripcion AS lote_descripcion,
                    CONCAT(a.nombre, ' ', a.apellido) AS responsable
                FROM movimientos_inventario m
                JOIN inventario_rollos r ON m.id_producto = r.id_producto AND m.id_lote = r.id_lote
                LEFT JOIN lotes l ON m.id_lote = l.id
                LEFT JOIN admins a ON m.id_admin = a.id
                WHERE m.id_producto = ? AND r.id_color = ?
                GROUP BY m.id
                ORDER BY m.fecha DESC";
    $stmt = $conn->prepare($sql_historial);
    $stmt->bind_param("ii", $id, $id_color_activo);
    $stmt->execute();
    $historial = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
}

// Consultar lotes disponibles
$sql_lotes = "SELECT id, descripcion FROM lotes ORDER BY fecha_creacion DESC";
$lotes = $conn->query($sql_lotes)->fetch_all(MYSQLI_ASSOC);
?>

<!DOCTYPE html>
<html lang="es">

<head>
    <?php include_once $ROOT . '/includes/head.php'; ?>
    <link rel="stylesheet" href="../../css/inventario/editar_inventario.css">
    <title><?= $TITULO ?> - <?= htmlspecialchars($producto['nombre']) ?></title>
</head>

<body>
    <?php
    $headerParams = [
        "titulo" => $TITULO,
        "btn_atras" => "window.location.href='lista.php'"
    ];
    include_once '../../includes/header.php';
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

                    <div class="mt-3">
                        <h3>Inventario actual</h3>
                        <?php if ($id_color_activo > 0): ?>
                            <?php
                            $color_actual = array_filter($colores_disponibles, function ($c) use ($id_color_activo) {
                                return $c['id'] == $id_color_activo;
                            });
                            $color_actual = reset($color_actual);
                            ?>
                            <div class="cantidad-disponible">
                                <?= $color_actual['cantidad_rollos'] ?> rollos (<?= number_format($color_actual['total_m2'], 2) ?> m²)
                                <span class="text-muted" style="font-size: 0.9em;">
                                    - <?= htmlspecialchars($color_actual['nombre']) ?>
                                </span>
                            </div>
                        <?php else: ?>
                            <div class="cantidad-disponible">
                                <?= $total_rollos ?> rollos (<?= number_format($total_m2, 2) ?> m²)
                                <span class="text-muted" style="font-size: 0.9em;">
                                    - Todos los colores
                                </span>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Selector de colores -->
                <div class="selector-colores" style="width: auto;">
                    <?php if ($id_color_activo > 0): ?>
                        <div class="color-seleccionado" onclick="mostrarSelectorColores()">
                            <div class="color-muestra" style="background-color: <?= htmlspecialchars($color_activo_hex) ?>"></div>
                            <span style="margin-right: 10px;"><?= htmlspecialchars($color_activo_nombre) ?></span>
                            <button type="button" class="btn-cambiar-color">
                                Cambiar Color
                            </button>
                        </div>
                    <?php else: ?>
                        <div class="color-seleccionado" onclick="mostrarSelectorColores()">
                            <div class="icono">🎨</div>
                            <span>Selecciona un color</span>
                            <button type="button" class="btn-cambiar-color">
                                Seleccionar
                            </button>
                        </div>
                    <?php endif; ?>

                    <div id="dropdown-colores" class="dropdown-colores <?= $id_color_activo <= 0 ? 'show' : '' ?>">
                        <?php foreach ($colores_disponibles as $c): ?>
                            <form method="GET" class="color-option">
                                <input type="hidden" name="id" value="<?= $id ?>">
                                <input type="hidden" name="id_color" value="<?= $c['id'] ?>">
                                <button type="submit" class="color-btn">
                                    <div class="color-circle" style="background-color: <?= htmlspecialchars($c['codigo_hex'] ?? '#CCC') ?>"></div>
                                    <div class="color-info">
                                        <div class="color-name"><?= htmlspecialchars($c['nombre']) ?></div>
                                        <div class="color-stats">
                                            <?= $c['cantidad_rollos'] > 0 ?
                                                $c['cantidad_rollos'] . ' rollos • ' . number_format($c['total_m2'], 2) . ' m² • $' . number_format($c['costo_total'], 2) :
                                                'Sin rollos' ?>
                                        </div>
                                    </div>
                                </button>
                            </form>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

            <?php if ($id_color_activo > 0): ?>
                <!-- Tabs para Lotes/Historial -->
                <div class="tabs-inventario">
                    <div class="tab-inventario active" data-tab="rollos">Rollos Existentes</div>
                    <div class="tab-inventario" data-tab="historial">Historial</div>
                </div>

                <!-- Contenido de Rollos -->
                <div class="tab-inventario-content active" id="rollos">
                    <div class="table-responsive" style="margin-top: 30px;">
                        <table class="tabla-lista">
                            <thead>
                                <tr>
                                    <th>Lote</th>
                                    <th>Cantidad</th>
                                    <th>Dimensiones</th>
                                    <th>Área total</th>
                                    <th>Costo unitario</th>
                                    <th>Costo total</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($rollos_del_color as $r): ?>
                                    <tr>
                                        <td>
                                            <?php if ($r['lote_id']): ?>
                                                <span class="badge-lote">#<?= $r['lote_id'] ?></span>
                                                <?= htmlspecialchars($r['lote_descripcion'] ?? '') ?>
                                            <?php endif; ?>
                                        </td>
                                        <td><?= $r['cantidad'] ?></td>
                                        <td><?= number_format($r['ancho_metros'], 2) ?> × <?= number_format($r['largo_metros'], 2) ?> m</td>
                                        <td><?= number_format($r['area'], 2) ?> m²</td>
                                        <td>$<?= number_format($r['costo_unitario'], 2) ?></td>
                                        <td>$<?= number_format($r['cantidad'] * $r['costo_unitario'], 2) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                                <?php if (empty($rollos_del_color)): ?>
                                    <tr>
                                        <td colspan="5" class="text-center">No hay rollos disponibles para este color</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Contenido de Historial -->
                <div class="tab-inventario-content" id="historial">
                    <div class="table-responsive">
                        <table class="tabla-lista">
                            <thead>
                                <tr>
                                    <th>Fecha</th>
                                    <th>Tipo</th>
                                    <th>Cantidad</th>
                                    <th>Área</th>
                                    <th>Costo</th>
                                    <th>Responsable</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($historial as $mov): ?>
                                    <tr>
                                        <td><?= date('d/m/Y H:i', strtotime($mov['fecha'])) ?></td>
                                        <td>
                                            <span class="badge-<?= $mov['tipo_movimiento'] == 'entrada' ? 'entrada' : 'salida' ?>">
                                                <?= ucfirst($mov['tipo_movimiento']) ?>
                                            </span>
                                        </td>
                                        <td><?= $mov['cantidad'] ?></td>
                                        <td><?= number_format($mov['area'], 2) ?> m²</td>
                                        <td>$<?= number_format($mov['costo_total'], 2) ?></td>
                                        <td><?= htmlspecialchars($mov['responsable'] ?? 'Sistema') ?></td>
                                    </tr>
                                <?php endforeach; ?>
                                <?php if (empty($historial)): ?>
                                    <tr>
                                        <td colspan="6" class="text-center">No hay historial de movimientos</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <form method="POST" action="../../php/inventario/guardar_edicion_rollos.php" class="formulario-inputs">
                    <input type="hidden" name="id_producto" value="<?= $id ?>">
                    <input type="hidden" name="id_color" value="<?= $id_color_activo ?>">
                    <input type="hidden" name="tipo_movimiento" value="entrada">

                    <div class="grid-formulario">
                        <div class="grid-item">
                            <label>Lote</label>
                            <select name="id_lote" class="textfield" id="selectLote" required>
                                <option value="nuevo">Nuevo lote</option>
                                <?php foreach ($lotes as $l): ?>
                                    <option value="<?= $l['id'] ?>">Lote #<?= $l['id'] ?> - <?= htmlspecialchars($l['descripcion']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="grid-item" id="lote-desc-group">
                            <label>Descripción del lote *</label>
                            <input type="text" name="lote_descripcion" class="textfield" required>
                        </div>

                        <div class="grid-item">
                            <label>Largo (metros)</label>
                            <input type="number" name="largo" class="textfield" step="0.01" min="0.01" required>
                        </div>

                        <div class="grid-item">
                            <label>Ancho (metros)</label>
                            <input type="number" name="ancho" class="textfield" step="0.01" min="0.01" required>
                        </div>

                        <div class="grid-item">
                            <label>Cantidad</label>
                            <input type="number" name="cantidad" class="textfield" min="1" required>
                        </div>

                        <div class="grid-item">
                            <label>Costo por unidad</label>
                            <input type="number" name="costo_unitario" class="textfield" step="0.01" min="0.01" required>
                        </div>

                        <div class="grid-item grid-item-full">
                            <label>Motivo/Notas</label>
                            <input type="text" name="motivo" class="textfield" placeholder="Opcional">
                        </div>

                        <div class="grid-item grid-item-full acciones-formulario">
                            <button type="submit" class="btnadd">
                                <i class="fas fa-plus-circle"></i> Agregar Rollos
                            </button>
                        </div>
                    </div>
                </form>
            <?php endif; ?>
        </div>

        <?php include_once "../../includes/popup.php"; ?>

        <script src="../../scripts/inventario/editar_rollos.js"></script>
        <script>
            document.getElementById('selectLote').addEventListener('change', function() {
                document.getElementById('lote-desc-group').style.display =
                    this.value === 'nuevo' ? 'flex' : 'none';
            });

            // Inicializar tabs
            document.querySelectorAll('.tab-inventario').forEach(tab => {
                tab.addEventListener('click', function() {
                    document.querySelectorAll('.tab-inventario').forEach(t => t.classList.remove('active'));
                    document.querySelectorAll('.tab-inventario-content').forEach(c => c.classList.remove('active'));

                    this.classList.add('active');
                    const tabId = this.getAttribute('data-tab');
                    document.getElementById(tabId).classList.add('active');
                });
            });

            document.getElementById('lote-desc-group').style.display =
                document.getElementById('selectLote').value === 'nuevo' ? 'flex' : 'none';
        </script>
    </main>
</body>

</html>