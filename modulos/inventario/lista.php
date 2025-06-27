<?php
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

// Configuración de rutas y título
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

// Consulta para obtener inventario (excluyendo productos eliminados)
$inventario = [];
$sql = "SELECT i.id, i.cantidad, i.largo_metros, i.ancho_metros, i.cantidad_base,
                i.actualizado_en, p.nombre AS nombre_producto, p.imagen, p.estado,
                u.simbolo AS unidad
        FROM inventario i
        JOIN productos p ON p.id = i.id_producto
        JOIN unidades u ON u.id = p.id_unidad
        WHERE p.estado <> ?
        ORDER BY p.estado ASC, p.nombre ASC";

$stmt = $conn->prepare($sql);
$estadoEliminado = ESTADO_ELIMINADO;
$stmt->bind_param('s', $estadoEliminado);
$stmt->execute();
$result = $stmt->get_result();

while ($row = $result->fetch_assoc()) {
    $inventario[] = $row; 
}
$stmt->close();

/**
 * 
 * @param array $item 
 * @return string 
 */
function formatCantidad($item)
{
    $unidad = htmlspecialchars($item['unidad'] ?? 'unidad', ENT_QUOTES, 'UTF-8');

    // Productos con medidas (rollos)
    if ($item['largo_metros'] !== null || $item['ancho_metros'] !== null) {
        return number_format($item['cantidad'], 0) . " rollo(s) de " .
            number_format($item['largo_metros'], 2) . "m × " .
            number_format($item['ancho_metros'], 2) . "m<br>" .
            "<strong>" . number_format($item['cantidad_base'], 2) . " {$unidad}</strong>";
    }

    // Productos sin medidas (unidades simples)
    return "<strong>" . number_format($item['cantidad'], 2) . " {$unidad}</strong>";
}
?>
<!DOCTYPE html>
<html>

<head>
    <?php include_once $ROOT . '/includes/head.php'; ?>
    <script src="<?php echo $ROOT ?>/../js/buscador.js?cache=<?php echo uniqid(); ?>"></script>
</head>

<body>
    <?php
    $headerParams = [
        "buscador" => true,
        "btn_atras" => 'window.history.back()'
    ];
    include_once '../../includes/header.php';
    ?>

    <div class="content">
        <div class="contenedor-tabla" style="max-height: calc(100vh - 200px); margin-bottom: 60px;">
            <table class="tabla-lista">
                <thead>
                    <tr>
                        <th>Imagen</th>
                        <th>Producto</th>
                        <th>Cantidad</th>
                        <th>Última actualización</th>
                        <th>Estado</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($inventario)): ?>
                        <tr>
                            <td colspan="6">No hay productos en inventario</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($inventario as $item): ?>
                            <?php $estadoClase = $item['estado'] === 'inactivo' ? 'color-gris' : ''; ?>
                            <tr class="<?php echo $estadoClase; ?>">
                                <!-- Columna de imagen -->
                                <td>
                                    <?php if (empty($item['imagen'])): ?>
                                        <span class="texto-muted">Sin imagen</span>
                                    <?php else: ?>
                                        <img style="width: 70px;"
                                            src="../../img/productos/<?php echo $item['imagen']; ?>?nocache=<?php echo uniqid(); ?>"
                                            alt="Imagen <?php echo $item['nombre_producto']; ?>"
                                            class="imagen-tabla">
                                    <?php endif; ?>
                                </td>

                                <!-- Nombre del producto -->
                                <td><strong><?php echo $item['nombre_producto']; ?></strong></td>

                                <!-- Cantidad formateada -->
                                <td><?php echo formatCantidad($item); ?></td>

                                <!-- Fecha de actualización -->
                                <td><?php echo date('d/m/Y H:i', strtotime($item['actualizado_en'])); ?></td>

                                <!-- Estado (activo/inactivo) -->
                                <td>
                                    <?php if ($item['estado'] === 'activo'): ?>
                                        <span class="estado-activo">Activo</span>
                                    <?php else: ?>
                                        <span class="estado-inactivo">Inactivo</span>
                                    <?php endif; ?>
                                </td>

                                <!-- Botón de edición -->
                                <td>
                                    <div class="acciones">
                                        <div class="editar" onclick="location.href='editar.php?id=<?php echo $item['id'] ?>'">
                                            <i class="fa-solid fa-pen-to-square"></i>
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

    <!-- Incluir popup para mensajes emergentes -->
    <?php include_once '../../includes/popup.php'; ?>
</body>
<script>
    setSearcher({
        input: ".textfield-buscador-navegador",
        search_element: "table tbody tr",
        display_type: "table-row"
    });
</script>

</html>