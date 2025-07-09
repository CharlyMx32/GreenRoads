<?php
/**
 * Edición de inventario de rollos
 * Muestra y permite editar el inventario de productos tipo rollo
 */

// Configuración inicial
$ROOT = '../..';
$TITULO = "Editar inventario";

// Incluir archivos necesarios
include_once $ROOT . '/db/conexion.php';
include_once $ROOT . '/includes/sesion.php';
include_once $ROOT . '/includes/config.php';

// Verificar sesión
if (!tieneSesion()) {
    header("Location: $URL_ROOT/login");
    exit();
}

// Validar ID de producto
$id = isset($_GET['id']) ? intval($_GET['id']) : 0;
if ($id <= 0) {
    header("Location: lista.php?error=id_invalido");
    exit();
}

// Limpiar temporales si se solicita
if (isset($_GET['limpiar_temporal'])) {
    unset($_SESSION['rollos_temporales']);
    header("Location: editar_rollos.php?id=$id&id_color=" . intval($_GET['id_color'] ?? 0));
    exit();
}

// Obtener color activo
$id_color_activo = isset($_GET['id_color']) ? intval($_GET['id_color']) : 0;

// Consultar información del producto
$sql_producto = "SELECT id, nombre, id_tipo_producto FROM productos WHERE id = ?";
$stmt = $conn->prepare($sql_producto);
$stmt->bind_param("i", $id);
$stmt->execute();
$producto = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$producto) {
    header("Location: lista.php?error=producto_no_encontrado");
    exit();
}

// Inicializar variables
$rollos = [];
$colores_disponibles = [];
$rollos_del_color = [];
$agrupados = [];

// Procesar solo si es producto tipo rollo (id_tipo_producto == 1)
if ($producto['id_tipo_producto'] == 1) {
    // Consultar colores disponibles con su inventario
    $sql_rollos = "SELECT 
                c.id, 
                c.nombre, 
                c.codigo_hex,
                COUNT(r.id) AS cantidad_rollos,
                COALESCE(SUM(r.largo_metros * r.ancho_metros), 0) AS total_m2
            FROM colores c
            LEFT JOIN inventario_rollos r ON c.id = r.id_color AND r.id_producto = ? AND r.estado = 'disponible'
            GROUP BY c.id, c.nombre, c.codigo_hex
            ORDER BY c.nombre ASC";
    $stmt = $conn->prepare($sql_rollos);
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $colores_disponibles = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    // Si hay color seleccionado, obtener sus rollos disponibles
    if ($id_color_activo > 0) {
        $sql_detalle = "SELECT largo_metros, ancho_metros, COUNT(*) AS cantidad, (largo_metros * ancho_metros) AS area
                FROM inventario_rollos
                WHERE id_producto = ? AND id_color = ? AND estado = 'disponible'
                GROUP BY largo_metros, ancho_metros
                ORDER BY largo_metros ASC";
        $stmt = $conn->prepare($sql_detalle);
        $stmt->bind_param("ii", $id, $id_color_activo);
        $stmt->execute();
        $rollos_del_color = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
    }
}

// Obtener rollos temporales de la sesión
$temporales = $_SESSION['rollos_temporales'][$id_color_activo] ?? [];
$mostrarTemporales = count($temporales) > 0;

// Agrupar rollos temporales por dimensiones
if ($mostrarTemporales) {
    $agrupados = [];
    foreach ($temporales as $idx => $r) {
        if (!is_array($r) || !isset($r['largo'], $r['ancho'], $r['area'])) continue;

        $k = $r['largo'] . '-' . $r['ancho'];
        if (!isset($agrupados[$k])) {
            $agrupados[$k] = [
                'cantidad' => 0,
                'largo' => $r['largo'],
                'ancho' => $r['ancho'],
                'area' => $r['area'],
                'indices' => []
            ];
        }
        $agrupados[$k]['cantidad']++;
        $agrupados[$k]['indices'][] = $idx;
    }
}

// Seleccionar primer color si no hay uno seleccionado
if ($id_color_activo <= 0 && count($colores_disponibles)) {
    $id_color_activo = $colores_disponibles[0]['id'];
    header("Location: editar_rollos.php?id=$id&id_color=$id_color_activo");
    exit();
}

// Obtener nombre y código hex del color activo
$color_activo_nombre = '';
$color_activo_hex = '';
foreach ($colores_disponibles as $c) {
    if ($c['id'] == $id_color_activo) {
        $color_activo_nombre = $c['nombre'];
        $color_activo_hex = $c['codigo_hex'];
        break;
    }
}
?>

<!DOCTYPE html>
<html lang="es">

<head>
    <?php include_once $ROOT . '/includes/head.php'; ?>
    <link rel="stylesheet" href="../../css/editar_rollos.css">
</head>

<body>
    <?php
    $headerParams = ["titulo" => $TITULO, "btn_atras" => "window.history.back()"];
    include_once '../../includes/header.php';
    ?>

    <main class="content">
        <div class="formulario active" style="margin-top: -100px;">
            <div class="seccion-formulario centrado">
                <h1 class="subtitulo-formulario">Editar inventario de: <strong><?= htmlspecialchars($producto['nombre']) ?></strong></h1>

                <?php if ($producto['id_tipo_producto'] == 1): ?>
                    <!-- Selector de colores -->
                    <div class="selector-colores">
                        <?php if ($id_color_activo > 0): ?>
                            <div class="color-seleccionado">
                                <div class="color-muestra" style="background-color: <?= htmlspecialchars($color_activo_hex) ?>"></div>
                                <span>Editando: <strong><?= htmlspecialchars($color_activo_nombre) ?></strong></span>
                                <button type="button" class="btn-cambiar-color" onclick="mostrarSelectorColores()">
                                    Cambiar Color
                                </button>
                            </div>
                        <?php else: ?>
                            <div class="estado-inicial">
                                <div class="icono">🎨</div>
                                <h3>Selecciona un color para editar</h3>
                                <button type="button" class="btn-cambiar-color" onclick="mostrarSelectorColores()">
                                    Seleccionar Color
                                </button>
                            </div>
                        <?php endif; ?>

                        <!-- Lista desplegable de colores -->
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
                                                    $c['cantidad_rollos'] . ' rollos • ' . number_format($c['total_m2'], 2) . ' m²' :
                                                    'Sin rollos' ?>
                                            </div>
                                        </div>
                                    </button>
                                </form>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <?php if ($id_color_activo > 0): ?>
                        <div class="fila-inventario">
                            <!-- Tabla de rollos existentes -->
                            <div class="columna">
                                <div class="titulo-formulario">Rollos existentes</div>
                                <div class="contenedor-tabla">
                                    <table class="tabla-rollos">
                                        <thead>
                                            <tr>
                                                <th>Cantidad</th>
                                                <th>Ancho</th>
                                                <th>Largo</th>
                                                <th>Área</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php if (!empty($rollos_del_color)): ?>
                                                <?php foreach ($rollos_del_color as $r): ?>
                                                    <tr>
                                                        <td><?= $r['cantidad'] ?></td>
                                                        <td><?= number_format($r['ancho_metros'], 2) ?></td>
                                                        <td><?= number_format($r['largo_metros'], 2) ?></td>
                                                        <td><?= number_format($r['area'], 2) ?></td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            <?php else: ?>
                                                <tr>
                                                    <td colspan="4" class="text-center">No hay rollos</td>
                                                </tr>
                                            <?php endif; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>

                            <!-- Formulario para agregar nuevos rollos -->
                            <div class="columna">
                                <div class="titulo-formulario">Agregar rollos</div>
                                <form method="POST" action="../../php/inventario/editar_rollos.php" class="formulario-rollos">
                                    <input type="hidden" name="id_producto" value="<?= $id ?>">
                                    <input type="hidden" name="id_color" value="<?= $id_color_activo ?>">
                                    <input type="hidden" name="agregar_temporal" value="1">

                                    <div class="textfield-container">
                                        <input type="number" name="largo" class="textfield" step="0.01" min="0.01" required>
                                        <label placeholder="Largo (m)"></label>
                                    </div>
                                    <div class="textfield-container">
                                        <input type="number" name="ancho" class="textfield" step="0.01" min="0.01" required>
                                        <label placeholder="Ancho (m)"></label>
                                    </div>
                                    <div class="textfield-container">
                                        <input type="number" name="cantidad" class="textfield" min="1" required>
                                        <label placeholder="Cantidad"></label>
                                    </div>

                                    <div>
                                        <button type="submit" class="btnadd">Agregar</button>
                                    </div>
                                </form>
                            </div>

                            <!-- Rollos temporales por agregar -->
                            <?php if ($mostrarTemporales): ?>
                                <div class="columna">
                                    <div class="titulo-formulario">Por agregar</div>
                                    <div class="contenedor-tabla">
                                        <table class="tabla-rollos">
                                            <thead>
                                                <tr>
                                                    <th>Cant.</th>
                                                    <th>Ancho</th>
                                                    <th>Largo</th>
                                                    <th>Área</th>
                                                    <th>Acción</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($agrupados as $key => $grupo): ?>
                                                    <tr>
                                                        <td><?= $grupo['cantidad'] ?></td>
                                                        <td><?= number_format($grupo['ancho'], 2) ?></td>
                                                        <td><?= number_format($grupo['largo'], 2) ?></td>
                                                        <td><?= number_format($grupo['area'], 2) ?></td>
                                                        <td style="text-align: center; vertical-align: middle;">
                                                            <form method="POST" action="../../php/inventario/editar_rollos.php" style="width: auto;">
                                                                <input type="hidden" name="id_producto" value="<?= $id ?>">
                                                                <input type="hidden" name="id_color" value="<?= $id_color_activo ?>">
                                                                <input type="hidden" name="ajustar_temporal" value="1">
                                                                <input type="hidden" name="operacion" value="eliminar_grupo">
                                                                <input type="hidden" name="grupo_key" value="<?= htmlspecialchars($key) ?>">
                                                                <button type="submit" class="btn-icon" title="Eliminar">🗑️</button>
                                                            </form>
                                                        </td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                    <form action="../../php/inventario/editar_rollos.php" method="POST" class="btn-row">
                                        <input type="hidden" name="id_producto" value="<?= $id ?>">
                                        <input type="hidden" name="id_color" value="<?= $id_color_activo ?>">
                                        <input type="hidden" name="guardar_definitivo" value="1">
                                        <div>
                                            <button type="submit" class="btnadd">Guardar</button>
                                            <button type="button" class="btnadd-filtro" onclick="window.location.href='?id=<?= $id ?>&id_color=<?= $id_color_activo ?>&limpiar_temporal=1'">Limpiar</button>
                                        </div>
                                    </form>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>
    </main>
    <?php include_once "../../includes/popup.php"; ?>

    <script>
        // Mostrar/ocultar selector de colores
        function mostrarSelectorColores() {
            const dropdown = document.getElementById('dropdown-colores');
            dropdown.classList.toggle('show');
        }

        // Cerrar dropdown al hacer clic fuera
        window.onclick = function(event) {
            if (!event.target.matches('.btn-cambiar-color') && !event.target.closest('.dropdown-colores')) {
                const dropdown = document.getElementById('dropdown-colores');
                dropdown.classList.remove('show');
            }
        }

        // Manejo de formularios con AJAX
        $(function() {
            // Formulario para agregar rollos temporales
            $('.formulario-rollos').on('submit', function(e) {
                e.preventDefault();
                const $form = $(this);
                const $btn = $form.find('button[type="submit"]');
                const originalText = $btn.text();

                $btn.prop('disabled', true).text('Agregando...');

                $.ajax({
                    url: $form.attr('action'),
                    method: 'POST',
                    data: $form.serialize(),
                    dataType: 'json',
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    success: function(data) {
                        if (data.status === 1) {
                            window.location.href = data.redirect || window.location.href;
                        } else {
                            displayMensajeError(data.mensaje || 'Error al agregar');
                            $btn.prop('disabled', false).text(originalText);
                        }
                    },
                    error: function(xhr, status, error) {
                        displayMensajeError('Error en la comunicación: ' + error);
                        $btn.prop('disabled', false).text(originalText);
                    }
                });
            });

            // Formulario para guardar cambios definitivos
            $('form[action="../../php/inventario/editar_rollos.php"]').has('input[name="guardar_definitivo"]').on('submit', function(e) {
                e.preventDefault();
                const $btn = $(this).find('button[type="submit"]');
                const originalText = $btn.text();

                $btn.prop('disabled', true).text('Guardando...');

                displayPopUp();

                $.ajax({
                    url: $(this).attr('action'),
                    method: 'POST',
                    data: $(this).serialize(),
                    dataType: 'json',
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    success: function(data) {
                        if (data.status === 1) {
                            displayMensajeExitoso(data.mensaje || 'Guardado exitoso', data.redirect ? `window.location='${data.redirect}'` : 'hidePopup()');
                        } else {
                            displayMensajeError(data.mensaje || 'Error desconocido');
                        }
                    },
                    error: function(xhr, status, error) {
                        displayMensajeError('Error en la comunicación: ' + error);
                    },
                    complete: function() {
                        $btn.prop('disabled', false).text(originalText);
                    }
                });
            });
        });
    </script>
</body>
</html>