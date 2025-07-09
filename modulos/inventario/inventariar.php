<?php
$ROOT = '../..';
$TITULO = "Seleccionar producto para inventariar";

include_once $ROOT . '/db/conexion.php';
include_once $ROOT . '/includes/sesion.php';
include_once $ROOT . '/includes/config.php';

if (!tieneSesion()) {
    header("Location: $URL_ROOT/login");
    exit();
}

// Obtener productos activos que no tengan inventario aún
$sql = "SELECT 
    p.id, 
    p.nombre, 
    u.simbolo AS unidad,
    p.id_tipo_producto,
    p.tipo_inventario,
    m.nombre AS nombre_modelo,
    m.altura_mm
FROM productos p
JOIN unidades u ON u.id = p.id_unidad
LEFT JOIN modelos m ON m.id = p.id_modelo
WHERE p.estado = 'activo'
AND NOT EXISTS (
    SELECT 1 FROM movimientos_inventario mi WHERE mi.id_producto = p.id
)
AND NOT EXISTS (
    SELECT 1 FROM inventario_rollos ir WHERE ir.id_producto = p.id
)
ORDER BY p.nombre";

$result = $conn->query($sql);
$productos = $result->fetch_all(MYSQLI_ASSOC);

?>
<!DOCTYPE html>
<html lang="es">

<head>
    <?php include_once $ROOT . '/includes/head.php'; ?>
    <script src="<?php echo $ROOT ?>/../js/buscador.js?cache=<?php echo uniqid(); ?>"></script>
</head>

<body>
    <?php
    $headerParams = [
        "titulo" => $TITULO,
        "btn_atras" => 'window.history.back()'
    ];
    include_once "../../includes/header.php";
    ?>

    <div class="content">
        <div class="contenedor-tabla" style="max-height: calc(100vh - 200px); margin-bottom: 60px;">
            <table class="tabla-lista">
                <thead>
                    <tr>
                        <th>Producto</th>
                        <th>Unidad</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($productos)): ?>
                        <tr>
                            <td colspan="3">Todos los productos han sido inventariados.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($productos as $p): ?>
                            <tr>
                                <td>
                                    <strong><?= htmlspecialchars($p['nombre']) ?></strong>
                                    <?php if (!empty($p['nombre_modelo'])): ?>
                                        <div style="font-size: 0.9em; color: #666; margin-top: 4px;">
                                            Modelo: <?= htmlspecialchars($p['nombre_modelo']) ?>
                                            <?php if (!empty($p['altura_mm'])): ?>
                                                | Altura: <?= htmlspecialchars($p['altura_mm']) ?> mm
                                            <?php endif; ?>
                                        </div>
                                    <?php elseif (!empty($p['altura_mm'])): ?>
                                        <div style="font-size: 0.9em; color: #666; margin-top: 4px;">
                                            Altura: <?= htmlspecialchars($p['altura_mm']) ?> mm
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td><?= htmlspecialchars($p['unidad']) ?></td>
                                <td>
                                    <div class="acciones">
                                        <div class="editar"
                                            onclick="location.href='<?= getInventariarURL($p['id_tipo_producto'], $p['tipo_inventario']) ?>?id=<?= $p['id'] ?>'">
                                            <i class="fa-solid fa-boxes-packing"></i> Inventariar
                                        </div>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <?php include_once "../../includes/popup.php"; ?>
</body>

<script>
    setSearcher({
        input: ".textfield-buscador-navegador",
        search_element: "table tbody tr",
        display_type: "table-row"
    });
</script>

</html>

<?php
function esTipoConRollos(int $tipo_producto_id, string $tipo_inventario): bool
{
    // Considerar tanto el tipo de producto como el tipo de inventario
    return in_array($tipo_producto_id, [1]) || $tipo_inventario === 'rollo';
}

function getInventariarURL(int $tipo_producto_id, string $tipo_inventario): string
{
    return esTipoConRollos($tipo_producto_id, $tipo_inventario)
        ? "inventariar_rollos.php"
        : "inventariar_unidad.php";
}
?>