<?php
$ROOT = '../..';
$TITULO = "Editar inventario";

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

if (isset($_GET['limpiar_temporal'])) {
    unset($_SESSION['rollos_temporales']);
    header("Location: editar_rollo.php?id=$id&id_color=" . intval($_GET['id_color'] ?? 0));
    exit();
}

$id_color_activo = isset($_GET['id_color']) ? intval($_GET['id_color']) : 0;

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

$rollos = [];
$colores_disponibles = [];
$rollos_del_color = [];

if ($producto['id_tipo_producto'] == 1) {
    $sql_rollos = "SELECT r.id_color, c.nombre AS color, c.codigo_hex, COUNT(*) AS cantidad,
                          SUM(r.largo_metros * r.ancho_metros) AS total_m2
                    FROM inventario_rollos r
                    JOIN colores c ON c.id = r.id_color
                    WHERE r.id_producto = ?
                    GROUP BY r.id_color, c.nombre, c.codigo_hex
                    ORDER BY c.nombre ASC";
    $stmt = $conn->prepare($sql_rollos);
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $colores_disponibles = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    if ($id_color_activo > 0) {
        $sql_detalle = "SELECT largo_metros, ancho_metros, COUNT(*) AS cantidad, (largo_metros * ancho_metros) AS area
                        FROM inventario_rollos
                        WHERE id_producto = ? AND id_color = ?
                        GROUP BY largo_metros, ancho_metros
                        ORDER BY largo_metros ASC";
        $stmt = $conn->prepare($sql_detalle);
        $stmt->bind_param("ii", $id, $id_color_activo);
        $stmt->execute();
        $rollos_del_color = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
    }
}

$temporales = $_SESSION['rollos_temporales'][$id_color_activo] ?? [];
$mostrarTemporales = count($temporales) > 0;
?>

<!DOCTYPE html>
<html lang="es">

<head>
    <?php include_once $ROOT . '/includes/head.php'; ?>
    <style>
        @media (max-width: 1300px) {
            .fila-inventario {
                flex-wrap: wrap;
                justify-content: center;
            }
        }

        .fila-inventario {
            display: flex;
            gap: 20px;
            justify-content: center;
            align-items: flex-start;
            flex-wrap: nowrap;
            padding: 20px;
            max-width: 100%;
            box-sizing: border-box;
        }

        .columna {
            flex: 0 0 auto;
            flex-direction: column;
            gap: 15px;
            width: 100%;
            max-width: 350px;
            box-sizing: border-box;
        }

        .tabla-rollos {
            border-collapse: collapse;
            width: 100%;
            font-size: 0.95em;
        }

        .tabla-rollos th,
        .tabla-rollos td {
            border: 1px solid #ccc;
            padding: 8px 10px;
            text-align: center;
        }

        .tabla-rollos th {
            background-color: #7dc042;
            color: white;
        }

        .contenedor-tabla {
            max-height: 280px;
            overflow-y: auto;
            width: 100%;
        }

        .color-card {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 10px;
            padding: 10px;
            border: 1px solid #ccc;
            border-radius: 8px;
            background-color: #f9f9f9;
            cursor: pointer;
            width: 80%;
        }

        .color-box {
            width: 24px;
            height: 24px;
            border-radius: 4px;
            border: 1px solid #999;
        }

        .colores-grid {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 10px;
        }

        @media (max-width: 992px) {
            .fila-inventario {
                flex-direction: row;
                align-items: stretch;
                gap: 15px;
            }

            .columna {
                max-width: 100%;
            }
        }

        @media (max-width: 768px) {
            .fila-inventario {
                flex-direction: column;
                align-items: center;
                gap: 20px;
            }
        }
    </style>
</head>

<body>
    <?php
    $headerParams = ["titulo" => $TITULO, "btn_atras" => "window.history.back()"];
    include_once '../../includes/header.php';
    ?>

    <main class="content">
        <div class="formulario active" style="margin-top: -100px;">
            <div class="seccion-formulario">
                <h1 class="subtitulo-formulario">Editar inventario de: <strong><?= htmlspecialchars($producto['nombre']) ?></strong></h1>
                <?php if ($producto['id_tipo_producto'] == 1): ?>
                    <div class="fila-inventario">
                        <!-- Colores disponibles -->
                        <div class="columna" style="max-width: 250px;">
                            <div class="titulo-formulario">Colores disponibles</div>
                            <div class="colores-grid">
                                <?php foreach ($colores_disponibles as $c): ?>
                                    <form method="GET" onsubmit="return confirmarCambioColor();">
                                        <input type="hidden" name="id" value="<?= $id ?>">
                                        <input type="hidden" name="id_color" value="<?= $c['id_color'] ?>">
                                        <button type="submit" class="color-card">
                                            <div class="color-box" style="background-color: <?= htmlspecialchars($c['codigo_hex'] ?? '#CCC') ?>"></div>
                                            <span><?= htmlspecialchars($c['color']) ?></span>
                                            <span><?= number_format($c['total_m2'], 2) ?> m²</span>
                                        </button>
                                    </form>
                                <?php endforeach; ?>
                            </div>
                        </div>

                        <?php if ($id_color_activo > 0): ?>
                            <!-- Rollos actuales por color -->
                            <div class="columna">
                                <div class="titulo-formulario">Rollos del color seleccionado</div>
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
                                            <?php foreach ($rollos_del_color as $r): ?>
                                                <tr>
                                                    <td><?= $r['cantidad'] ?></td>
                                                    <td><?= number_format($r['ancho_metros'], 2) ?></td>
                                                    <td><?= number_format($r['largo_metros'], 2) ?></td>
                                                    <td><?= number_format($r['area'], 2) ?></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>

                            <!-- Inputs para agregar -->
                            <div class="columna">
                                <div class="titulo-formulario">Agregar rollos</div>
                                <form method="POST" action="../../php/inventario/guardar_editar.php" class="formulario-rollos" onsubmit="modificacionHecha=true;">
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
                                        <label placeholder="Cantidad de rollos"></label>
                                    </div>
                                    <div>
                                        <button type="submit" class="btnadd">Agregar</button>
                                    </div>
                                </form>
                            </div>

                            <?php if ($mostrarTemporales): ?>
                                <!-- Rollos por agregar -->
                                <div class="columna">
                                    <div class="titulo-formulario">Rollos por agregar</div>
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
                                                <?php
                                                $agrupados = [];
                                                foreach ($temporales as $idx => $r) {
                                                    if (!is_array($r) || !isset($r['largo'], $r['ancho'], $r['area'])) {
                                                        continue;
                                                    }

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

                                                foreach ($agrupados as $key => $grupo): ?>
                                                    <tr>
                                                        <td><?= $grupo['cantidad'] ?></td>
                                                        <td><?= number_format($grupo['ancho'], 2) ?></td>
                                                        <td><?= number_format($grupo['largo'], 2) ?></td>
                                                        <td><?= number_format($grupo['area'], 2) ?></td>
                                                        <td style="width:1%; white-space: nowrap;">
                                                            <form method="POST" action="../../php/inventario/guardar_editar.php" style="width:1%; margin: 5px;">
                                                                <input type="hidden" name="id_producto" value="<?= $id ?>">
                                                                <input type="hidden" name="id_color" value="<?= $id_color_activo ?>">
                                                                <input type="hidden" name="ajustar_temporal" value="1">
                                                                <input type="hidden" name="operacion" value="eliminar_grupo">
                                                                <input type="hidden" name="grupo_key" value="<?= htmlspecialchars($key) ?>">
                                                                <button type="submit" class="btn-icon" title="Eliminar todos">🗑️</button>
                                                            </form>
                                                        </td>

                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                    <form action="../../php/inventario/guardar_editar.php" method="POST" class="btn-row">
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
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </main>
    <?php include_once "../../includes/popup.php"; ?>

    <script>
        $(function() {
            const $formGuardar = $('form[action="../../php/inventario/guardar_editar.php"]').has('input[name="guardar_definitivo"]');

            $formGuardar.on('submit', function(e) {
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