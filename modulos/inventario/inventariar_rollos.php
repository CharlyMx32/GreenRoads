<?php
$ROOT = '../..';
$TITULO = "Inventariar rollos";

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

// Limpiar temporal
if (isset($_GET['limpiar_temporal'])) {
    unset($_SESSION['rollos_temporales']);
    header("Location: inventariar_rollos.php?id=$id");
    exit();
}

// Inicializar almacenamiento temporal
if (!isset($_SESSION['rollos_temporales'])) {
    $_SESSION['rollos_temporales'] = [];
}

// Obtener datos producto
$sql = "SELECT p.id AS id_producto, p.nombre, p.descripcion, p.id_tipo_producto, u.simbolo, u.nombre AS unidad_nombre
        FROM productos p
        JOIN unidades u ON u.id = p.id_unidad
        WHERE p.id = ? LIMIT 1";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $id);
$stmt->execute();
$result = $stmt->get_result();
$producto = $result->fetch_assoc();
$stmt->close();

if (!$producto) {
    header("Location: lista.php?error=producto_no_encontrado");
    exit();
}

$id_producto = $producto['id_producto'];

// Procesar POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['agregar_temporal'])) {
    $largo = floatval($_POST['largo'] ?? 0);
    $ancho = floatval($_POST['ancho'] ?? 0);
    $cantidad = intval($_POST['cantidad'] ?? 0);
    $id_color = intval($_POST['id_color'] ?? 0);

    if ($largo <= 0 || $ancho <= 0 || $cantidad <= 0 || $id_color <= 0) {
        header("Location: inventariar_rollos.php?id=$id&error=datos_invalidos");
        exit();
    }

    $stmt_color = $conn->prepare("SELECT nombre FROM colores WHERE id = ?");
    $stmt_color->bind_param("i", $id_color);
    $stmt_color->execute();
    $color = $stmt_color->get_result()->fetch_assoc();
    $stmt_color->close();

    $nombre_color = $color['nombre'] ?? 'Desconocido';

    for ($i = 0; $i < $cantidad; $i++) {
        $_SESSION['rollos_temporales'][] = [
            'largo' => $largo,
            'ancho' => $ancho,
            'area' => $largo * $ancho,
            'id_color' => $id_color,
            'nombre_color' => $nombre_color
        ];
    }

    header("Location: inventariar_rollos.php?id=$id");
    exit();
}
?>

<!DOCTYPE html>
<html lang="es">

<head>
    <?php include_once $ROOT . '/includes/head.php'; ?>
    <style>
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
            white-space: nowrap;
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

        .formulario-rollos .textfield-container {
            width: 100%;
        }

        .formulario-rollos .btn-row {
            display: flex;
            justify-content: space-between;
            gap: 10px;
            margin-top: 10px;
        }

        .btn-row {
            display: flex;
            justify-content: center;
            gap: 15px;
            margin-top: 20px;
        }

        .titulo-formulario {
            font-weight: bold;
            color: #555;
            text-align: center;
            margin-bottom: 10px;
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

            .columna {
                max-width: 100%;
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
                <h1 class="subtitulo-formulario">Inventariar: <strong><?= htmlspecialchars($producto['nombre']) ?></strong></h1>

                <div class="fila-inventario">
                    <!-- Formulario -->
                    <div class="columna">
                        <div class="titulo-formulario">Agregar rollos</div>
                        <form method="POST" class="formulario-rollos">
                            <input type="hidden" name="id_producto" value="<?= $id_producto ?>">
                            <input type="hidden" name="agregar_temporal" value="1">

                            <div class="textfield-container">
                                <input type="number" name="largo" step="0.01" min="0.01" required class="textfield">
                                <label placeholder="Largo (m)"></label>
                            </div>
                            <div class="textfield-container">
                                <input type="number" name="ancho" step="0.01" min="0.01" required class="textfield">
                                <label placeholder="Ancho (m)"></label>
                            </div>
                            <div class="textfield-container">
                                <input type="number" name="cantidad" min="1" required class="textfield">
                                <label placeholder="Cantidad de rollos"></label>
                            </div>
                            <div class="textfield-container">
                                <select name="id_color" required class="textfield">
                                    <option value="">Selecciona un color</option>
                                    <?php
                                    $colores = $conn->query("SELECT id, nombre FROM colores ORDER BY nombre ASC")->fetch_all(MYSQLI_ASSOC);
                                    foreach ($colores as $color): ?>
                                        <option value="<?= $color['id'] ?>"><?= htmlspecialchars($color['nombre']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div>
                                <button type="submit" class="btnadd">Agregar</button>
                            </div>
                        </form>
                    </div>

                    <!-- Tabla temporal -->
                    <?php if (!empty($_SESSION['rollos_temporales'])): ?>
                        <?php
                        $agrupados = [];
                        foreach ($_SESSION['rollos_temporales'] as $index => $r) {
                            if (!isset($r['id_color']) || !isset($r['nombre_color'])) {
                                error_log("Invalid rollo at index $index: " . print_r($r, true));
                                continue;
                            }
                            $key = $r['largo'] . '-' . $r['ancho'] . '-' . $r['id_color'];
                            if (!isset($agrupados[$key])) {
                                $agrupados[$key] = [
                                    'cantidad' => 0,
                                    'largo' => $r['largo'],
                                    'ancho' => $r['ancho'],
                                    'area_unitaria' => $r['area'],
                                    'area_total' => 0,
                                    'id_color' => $r['id_color'],
                                    'nombre_color' => $r['nombre_color']
                                ];
                            }
                            $agrupados[$key]['cantidad']++;
                            $agrupados[$key]['area_total'] += $r['area'];
                        }

                        ?>
                        <div class="columna">
                            <div class="titulo-formulario">Rollos por agregar</div>
                            <div class="contenedor-tabla">
                                <table class="tabla-rollos">
                                    <thead>
                                        <tr>
                                            <th>Cantidad</th>
                                            <th>Ancho</th>
                                            <th>Largo</th>
                                            <th>Área Total (m²)</th>
                                            <th>Color</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($agrupados as $grupo): ?>
                                            <tr>
                                                <td><?= $grupo['cantidad'] ?></td>
                                                <td><?= number_format($grupo['ancho'], 2) ?></td>
                                                <td><?= number_format($grupo['largo'], 2) ?></td>
                                                <td><?= number_format($grupo['area_total'], 2) ?></td>
                                                <td><?= isset($grupo['nombre_color']) ? htmlspecialchars($grupo['nombre_color']) : 'N/A' ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                    <tfoot>
                                        <tr>
                                            <td colspan="3" style="text-align: right;"><strong>Total general:</strong></td>
                                            <td><strong><?= number_format(array_sum(array_column($agrupados, 'area_total')), 2) ?></strong></td>
                                            <td></td>
                                        </tr>
                                    </tfoot>
                                </table>
                            </div>
                            <div class="btn-row">
                                <form method="POST" action="../../php/inventario/guardar_editar.php">
                                    <input type="hidden" name="id_producto" value="<?= $id_producto ?>">
                                    <input type="hidden" name="guardar_definitivo" value="1">
                                    <input type="hidden" name="origen" value="inventariar_rollos">
                                    <button type="submit" class="btnadd">Inventariar</button>
                                </form>
                                <form method="GET" action="inventariar_rollos.php">
                                    <input type="hidden" name="id" value="<?= $id ?>">
                                    <input type="hidden" name="limpiar_temporal" value="1">
                                    <button type="submit" class="btnadd-filtro">Limpiar</button>
                                </form>
                            </div>

                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php include_once '../../includes/popup.php'; ?>
    </main>

    <script>
        // Manejo del formulario de guardado
        document.querySelector('form[action*="guardar_editar.php"]')?.addEventListener('submit', function(e) {
            e.preventDefault();

            const form = e.target;
            const formData = new FormData(form);
            const btn = form.querySelector('button[type="submit"]');

            btn.disabled = true;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Procesando...';
            displayPopUp();

            fetch(form.action, {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.status == 0) {
                        throw new Error(data.mensaje || "Error desconocido del servidor");
                    }
                    displayMensajeExitoso(data.mensaje, "location.href='../../modulos/inventario/lista.php'");
                })
                .catch(error => {
                    console.error('Error:', error);
                    displayMensajeError(error.message);
                })
                .finally(() => {
                    btn.disabled = false;
                    btn.innerHTML = 'Inventariar';
                });
        });
    </script>
</body>

</html>