<?php
// Controlador para la lista de inventario
/**
 * Muestra la lista de productos en inventario con sus detalles.
 */


ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

$ROOT = '../..';
$TITULO = "Inventario";

include_once $ROOT . '/db/conexion.php';
include_once $ROOT . '/includes/sesion.php';
include_once $ROOT . '/includes/config.php';

if (!tieneSesion()) {
    header("Location: $URL_ROOT/login");
    exit();
}

define('ESTADO_ACTIVO', 'activo');
define('ESTADO_INACTIVO', 'inactivo');
define('ESTADO_ELIMINADO', 'eliminado');

// Consulta para obtener inventario
$inventario = [];
$sql = "SELECT 
    p.id AS id_producto,
    p.nombre AS nombre_producto,
    p.imagen,
    p.estado,
    p.id_tipo_producto,
    p.tipo_inventario,
    m.altura_mm,
    tp.nombre AS tipo_producto,
    u.simbolo AS unidad,
    m.nombre AS nombre_modelo,
    (
        SELECT COUNT(*) 
        FROM inventario_rollos ir 
        WHERE ir.id_producto = p.id AND ir.estado = 'disponible'
    ) AS cantidad_rollos,
    (
        SELECT SUM(ir.area_m2) 
        FROM inventario_rollos ir 
        WHERE ir.id_producto = p.id AND ir.estado = 'disponible'
    ) AS cantidad_base,
    GREATEST(
        IFNULL((SELECT MAX(ir.fecha_ingreso) FROM inventario_rollos ir WHERE ir.id_producto = p.id), '1970-01-01'),
        IFNULL((SELECT MAX(mi.fecha) FROM movimientos_inventario mi WHERE mi.id_producto = p.id), '1970-01-01')
    ) AS actualizado_en
FROM productos p
JOIN unidades u ON u.id = p.id_unidad
JOIN tipo_productos tp ON tp.id = p.id_tipo_producto
LEFT JOIN modelos m ON m.id = p.id_modelo
WHERE p.estado = 'activo'
AND (
    (p.tipo_inventario = 'rollo' AND EXISTS (
        SELECT 1 FROM inventario_rollos ir WHERE ir.id_producto = p.id
    ))
    OR
    (p.tipo_inventario = 'unidad' AND EXISTS (
        SELECT 1 FROM movimientos_inventario mi WHERE mi.id_producto = p.id
    ))
)
ORDER BY cantidad_base DESC, p.nombre ASC";

$stmt = $conn->prepare($sql);
$stmt->execute();
$result = $stmt->get_result();

while ($row = $result->fetch_assoc()) {
    $row['cantidad'] = calcularCantidadInventario($conn, $row['id_producto']);
    $inventario[] = $row;
}
$stmt->close();

function getDetalleColores($conn, $id_producto)
{
    $sql = "SELECT 
            c.id AS id_color,
            c.nombre AS color, 
            c.codigo_hex,
            SUM(ir.area_m2) AS total_m2, 
            COUNT(*) AS rollos
            FROM inventario_rollos ir
            JOIN colores c ON c.id = ir.id_color
            WHERE ir.id_producto = ? AND ir.estado = 'disponible'
            GROUP BY ir.id_color
            ORDER BY c.nombre ASC";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param('i', $id_producto);
    $stmt->execute();
    $result = $stmt->get_result();

    $detalles = [];
    while ($row = $result->fetch_assoc()) {
        $detalles[] = $row;
    }

    $stmt->close();
    return $detalles;
}

/**
 * Calcula la cantidad actual en inventario basada en movimientos
 */
function calcularCantidadInventario($conn, $id_producto)
{
    $sql = "SELECT 
                SUM(CASE WHEN tipo_movimiento = 'entrada' THEN cantidad ELSE 0 END) - 
                SUM(CASE WHEN tipo_movimiento IN ('salida', 'reserva') THEN cantidad ELSE 0 END) +
                SUM(CASE WHEN tipo_movimiento = 'liberacion' THEN cantidad ELSE 0 END) AS cantidad
            FROM movimientos_inventario
            WHERE id_producto = ?";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param('i', $id_producto);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $stmt->close();

    return $row['cantidad'] ?? 0;
}

/**
 * Formatea la cantidad para mostrar en la tabla
 */
function formatCantidad($item)
{
    $unidad = htmlspecialchars($item['unidad'] ?? 'unidad', ENT_QUOTES, 'UTF-8');
    $cantidadBase = $item['cantidad_base'] ?? 0;
    $rollos = $item['cantidad_rollos'] ?? 0;
    $cantidad = $item['cantidad'] ?? 0;
    $esRollo = ($item['tipo_inventario'] ?? 'unidad') === 'rollo';

    if ($esRollo) {
        if ($cantidadBase <= 0 || $rollos <= 0) {
            return "<strong style='color: #d9534f;'>Agotado</strong>";
        }

        if ($cantidadBase < 10 || $rollos < 2) {
            return "<strong style='color: #f0ad4e;'>" . number_format($cantidadBase, 2) . " m²</strong><br>" .
                number_format($rollos, 0) . " rollo(s)";
        }

        return "<strong>" . number_format($cantidadBase, 2) . " m²</strong><br>" .
            number_format($rollos, 0) . " rollo(s)";
    }

    if ($cantidad <= 0) {
        return "<strong style='color: #d9534f;'>Agotado</strong>";
    }

    if ($cantidad < 5) {
        return "<strong style='color: #f0ad4e;'>" . number_format($cantidad, 2) . " {$unidad}</strong>";
    }

    return "<strong>" . number_format($cantidad, 2) . " {$unidad}</strong>";
}


function getEditarURL($tipo_producto)
{
    return match ($tipo_producto) {
        1 => "editar_rollos.php",
        default => "editar_cantidad.php"
    };
}
?>
<!DOCTYPE html>
<html>

<head>
    <?php include_once $ROOT . '/includes/head.php'; ?>
    <script src="<?php echo $ROOT ?>/../js/buscador.js?cache=<?php echo uniqid(); ?>"></script>
    <style>
        .btn-editar-color {
            display: inline-block;
            padding: 6px 14px;
            background-color: #7dc042;
            color: white;
            border-radius: 6px;
            font-size: 13px;
            text-decoration: none;
            font-family: 'Montserrat', sans-serif;
            font-weight: 500;
            transition: background 0.3s ease;
        }

        .btn-editar-color:hover {
            background-color: #6aa736;
        }

        .color-details-container {
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
            padding: 15px;
            background-color: #f8f9fa;
            border-radius: 8px;
            margin: 10px 0;
        }

        .color-card {
            flex: 1 1 200px;
            background: white;
            border-radius: 8px;
            padding: 15px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
            border-left: 4px solid #7dc042;
            transition: transform 0.2s, box-shadow 0.2s;
        }

        .color-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.12);
        }

        .color-header {
            display: flex;
            align-items: center;
            margin-bottom: 10px;
        }

        .color-sample {
            width: 20px;
            height: 20px;
            border-radius: 50%;
            margin-right: 10px;
            border: 1px solid #ddd;
        }

        .color-name {
            font-weight: 600;
            color: #333;
        }

        .color-stats {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 8px;
        }

        .stat-item {
            text-align: center;
        }

        .stat-value {
            font-size: 1.1em;
            font-weight: 600;
            color: #7dc042;
        }

        .stat-label {
            font-size: 0.8em;
            color: #666;
        }

        .toggle-detalle {
            cursor: pointer;
            margin-right: 8px;
            font-size: 1.1em;
            color: #7dc042;
            transition: transform 0.2s;
        }

        .toggle-detalle.open {
            transform: rotate(90deg);
        }

        .fila-detalle {
            transition: all 0.3s ease-out;
        }
    </style>
</head>

<body>
    <?php
    $headerParams = [
        "buscador" => true,
        "btn_atras" => "location.href='../../modulos/dashboard/menu.php'",
    ];
    include_once '../../includes/header.php';
    ?>

    <div class="content">
        <div class="contenedor-tabla" style="max-height: calc(100vh - 200px); margin-bottom: 60px;">
            <table class="tabla-lista">
                <thead>
                    <tr>
                        <th>Producto</th>
                        <th>Cantidad</th>
                        <th>Última actualización</th>
                        <th>Estado</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($inventario)): ?>
                        <tr>
                            <td colspan="6">No hay productos en inventario</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($inventario as $item): ?>
                            <?php
                            $esRollo = ($item['tipo_inventario'] ?? 'unidad') === 'rollo';
                            $detalles = $esRollo ? getDetalleColores($conn, $item['id_producto']) : [];
                            ?>
                            <tr class="fila-principal <?= $item['estado'] === 'inactivo' ? 'color-gris' : '' ?>" data-id="<?= $item['id_producto'] ?>" data-es-rollo="<?= $esRollo ? '1' : '0' ?>">
                                <td>
                                    <?php if ($esRollo): ?>
                                        <span class="toggle-detalle">Ver mas detalle...</span>
                                    <?php endif; ?>
                                    <strong><?= htmlspecialchars($item['nombre_producto']) ?></strong>
                                    <?php if (!empty($item['nombre_modelo'])): ?>
                                        <div style="font-size: 0.9em; color: #666; margin-top: 4px;">
                                            Modelo: <?= htmlspecialchars($item['nombre_modelo']) ?>
                                            <?php if (!empty($item['altura_mm'])): ?>
                                                | Altura: <?= htmlspecialchars($item['altura_mm']) ?> mm
                                            <?php endif; ?>
                                        </div>
                                    <?php elseif (!empty($item['altura_mm'])): ?>
                                        <div style="font-size: 0.9em; color: #666; margin-top: 4px;">
                                            Altura: <?= htmlspecialchars($item['altura_mm']) ?> mm
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td><?= $esRollo ? formatCantidad($item) : formatCantidad($item) ?></td>
                                <td><?= date('d/m/Y H:i', strtotime($item['actualizado_en'])) ?></td>
                                <td>
                                    <div class="acciones">
                                        <div class="editar" onclick="location.href='<?= getEditarURL($item['id_tipo_producto']) ?>?id=<?= $item['id_producto'] ?>'">
                                            <i class="fa-solid fa-pen-to-square"></i>
                                        </div>
                                    </div>
                                </td>
                            </tr>

                            <?php if ($esRollo): ?>
                                <tr class="fila-detalle" id="detalle-<?= $item['id_producto'] ?>" style="display:none;">
                                    <td colspan="4">
                                        <?php if (!empty($detalles)): ?>
                                            <div class="color-details-container">
                                                <?php foreach ($detalles as $d): ?>
                                                    <div class="color-card">
                                                        <div class="color-header">
                                                            <div class="color-sample" style="background-color: <?= $d['codigo_hex'] ?? '#7dc042' ?>"></div>
                                                            <div class="color-name"><?= htmlspecialchars($d['color']) ?></div>
                                                        </div>
                                                        <div class="color-stats">
                                                            <div class="stat-item">
                                                                <div class="stat-value"><?= number_format($d['total_m2'], 2) ?></div>
                                                                <div class="stat-label">m² totales</div>
                                                            </div>
                                                            <div class="stat-item">
                                                                <div class="stat-value"><?= $d['rollos'] ?></div>
                                                                <div class="stat-label">rollos</div>
                                                            </div>
                                                            <div class="stat-item">
                                                                <div class="stat-value"><?= $d['rollos'] > 0 ? number_format($d['total_m2'] / $d['rollos'], 2) : '0.00' ?></div>
                                                                <div class="stat-label">promedio</div>
                                                            </div>
                                                        </div>
                                                        <div style="text-align: center; margin-top: 10px;">
                                                            <a href="editar_rollo_color.php?id_producto=<?= $item['id_producto'] ?>&id_color=<?= $d['id_color'] ?>"
                                                                class="btn-editar-color">
                                                                Editar
                                                            </a>
                                                        </div>
                                                    </div>
                                                <?php endforeach; ?>

                                            </div>
                                        <?php else: ?>
                                            <div style="padding: 15px; text-align: center; color: #666;">
                                                No hay rollos disponibles en inventario
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        <?php endforeach; ?>

                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div class="btn-nuevo" onclick="location.href='inventariar'">
        <i class="fa-solid fa-plus"></i>
    </div>

    <?php include_once '../../includes/popup.php'; ?>
</body>
<script>
    document.addEventListener('DOMContentLoaded', function() {
        const input = document.querySelector(".textfield-buscador-navegador");
        const filas = document.querySelectorAll("tr.fila-principal");

        input.addEventListener("input", function() {
            const texto = input.value.trim().toLowerCase();

            filas.forEach(fila => {
                const contenido = fila.textContent.toLowerCase();
                const id = fila.getAttribute("data-id");
                const filaDetalle = document.getElementById("detalle-" + id);

                if (contenido.includes(texto)) {
                    fila.style.display = "table-row";
                    if (filaDetalle) filaDetalle.style.display = "none";
                } else {
                    fila.style.display = "none";
                    if (filaDetalle) filaDetalle.style.display = "none";
                }
            });
        });
    });
    document.addEventListener('DOMContentLoaded', function() {
        const detalles = document.querySelectorAll('.fila-detalle');
        detalles.forEach(detalle => {
            detalle.style.display = 'none';
            detalle.style.opacity = '0';
            detalle.style.transform = 'translateY(-10px)';
        });

        function toggleDetalle(toggleElement) {
            const row = toggleElement.closest('.fila-principal');
            const id = row.getAttribute('data-id');
            const detalle = document.getElementById('detalle-' + id);

            const isVisible = window.getComputedStyle(detalle).display !== 'none';

            toggleElement.classList.toggle('open');

            if (!isVisible) {
                detalle.style.display = 'table-row';
                setTimeout(() => {
                    detalle.style.opacity = '1';
                    detalle.style.transform = 'translateY(0)';
                }, 10);
            } else {
                detalle.style.opacity = '0';
                detalle.style.transform = 'translateY(-10px)';
                setTimeout(() => {
                    detalle.style.display = 'none';
                }, 300);
            }
        }

        document.addEventListener('click', function(e) {
            if (e.target.classList.contains('toggle-detalle')) {
                e.stopPropagation();
                toggleDetalle(e.target);
                return;
            }

            if (e.target.closest('.fila-principal') && !e.target.closest('.acciones')) {
                const fila = e.target.closest('.fila-principal');
                if (fila.getAttribute('data-es-rollo') === '1') {
                    const toggle = fila.querySelector('.toggle-detalle');
                    if (toggle) {
                        if (!e.target.classList.contains('toggle-detalle')) {
                            toggleDetalle(toggle);
                        }
                    }
                }
            }
        });
    });
</script>

</html>