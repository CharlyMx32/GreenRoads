<?php
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

$ROOT = '../../';
$TITULO = "Editar Cotización";

include_once $ROOT . '/db/conexion.php';
include_once $ROOT . '/includes/sesion.php';
include_once $ROOT . '/includes/config.php';

if (!tieneSesion()) {
    header("Location: $URL_ROOT/login");
    exit();
}

$id_cotizacion = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($id_cotizacion <= 0) {
    header("Location: lista.php");
    exit();
}

// Obtener datos de la cotización
$sql_cotizacion = "SELECT c.*, c.area_total, cl.nombre as nombre_cliente 
                   FROM cotizaciones c 
                   JOIN clientes cl ON c.id_cliente = cl.id 
                   WHERE c.id = ?";
$stmt = mysqli_prepare($conn, $sql_cotizacion);
mysqli_stmt_bind_param($stmt, "i", $id_cotizacion);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$cotizacion = mysqli_fetch_assoc($result);
mysqli_stmt_close($stmt);

if (!$cotizacion) {
    header("Location: lista.php");
    exit();
}

// Obtener detalles de rollos
$rollos_detalles = [];
$sql_rollos = "SELECT dc.*, p.nombre as nombre_producto, c.nombre as nombre_color, c.codigo_hex
               FROM detalle_cotizacion dc
               LEFT JOIN productos p ON dc.id_producto = p.id
               LEFT JOIN colores c ON dc.id_color = c.id
               WHERE dc.id_cotizacion = ? AND dc.id_color IS NOT NULL
               ORDER BY dc.id";
$stmt_rollos = mysqli_prepare($conn, $sql_rollos);
mysqli_stmt_bind_param($stmt_rollos, "i", $id_cotizacion);
mysqli_stmt_execute($stmt_rollos);
$result_rollos = mysqli_stmt_get_result($stmt_rollos);
while ($row = mysqli_fetch_assoc($result_rollos)) {
    $rollos_detalles[] = $row;
}
mysqli_stmt_close($stmt_rollos);

// Obtener extras
$extras_cotizacion = [];
$sql_extras = "SELECT ce.*, e.nombre, e.precio as precio_base
               FROM cotizacion_extras ce
               LEFT JOIN extras e ON ce.id_extra = e.id
               WHERE ce.id_cotizacion = ?";
$stmt_extras = mysqli_prepare($conn, $sql_extras);
mysqli_stmt_bind_param($stmt_extras, "i", $id_cotizacion);
mysqli_stmt_execute($stmt_extras);
$result_extras = mysqli_stmt_get_result($stmt_extras);
while ($row = mysqli_fetch_assoc($result_extras)) {
    $extras_cotizacion[] = $row;
}
mysqli_stmt_close($stmt_extras);

// Obtener listas para formulario
$clientes = [];
$sql = "SELECT id, nombre FROM clientes WHERE estado = 'activo' ORDER BY nombre ASC";
$result = mysqli_query($conn, $sql);
while ($row = mysqli_fetch_assoc($result)) {
    $clientes[] = $row;
}

$rollos = [];
$sql_rollos_disponibles = "SELECT p.id, p.nombre, p.costo_base, m.nombre AS modelo,
                           (SELECT GROUP_CONCAT(DISTINCT c.nombre SEPARATOR ', ') 
                            FROM producto_colores pc
                            JOIN colores c ON pc.id_color = c.id 
                            WHERE pc.id_producto = p.id) AS colores_asignados
    FROM productos p
    LEFT JOIN modelos m ON p.id_modelo = m.id
    WHERE p.tipo_inventario = 'rollo' AND p.estado = 'activo'
    ORDER BY p.nombre";

$result = mysqli_query($conn, $sql_rollos_disponibles);
while ($row = mysqli_fetch_assoc($result)) {
    $rollos[] = $row;
}

$extras = [];
$sql_extras_disponibles = "SELECT id, nombre, precio FROM extras WHERE estado = 'activo' ORDER BY nombre";
$result = mysqli_query($conn, $sql_extras_disponibles);
while ($row = mysqli_fetch_assoc($result)) {
    $extras[] = $row;
}
?>

<!DOCTYPE html>
<html>

<head>
    <?php include_once $ROOT . '/includes/head.php'; ?>
    <link rel="stylesheet" href="../../css/cotizaciones/cotizaciones.css">
</head>

<body>
    <?php
    $headerParams = [
        "titulo" => $TITULO . " #" . $id_cotizacion,
        "btn_atras" => "window.history.back()"
    ];
    include_once '../../includes/header.php';
    ?>

    <div class="main-container" style="width: 100%;">
        <div class="form-container-grid">
            <!-- Columna 1 -->
            <div class="grid-col">
                <!-- SECCIÓN CLIENTE -->
                <div class="form-section" role="region" aria-labelledby="seccion-cliente">
                    <div class="titulo-formulario" style="margin-top: -7px;" id="seccion-cliente">Cliente</div>
                    <div class="form-group">
                        <label for="cliente">
                            <span class="sr-only">Seleccionar Cliente</span>
                            <span aria-hidden="true">Cliente *</span>
                        </label>
                        <div style="display: flex; gap: 10px;">
                            <select id="cliente" class="textfield" style="width: 100%;" required>
                                <option value="">-- Selecciona --</option>
                                <?php foreach ($clientes as $cliente) : ?>
                                    <option value="<?= $cliente['id'] ?>" <?= $cliente['id'] == $cotizacion['id_cliente'] ? 'selected' : '' ?>><?= htmlspecialchars($cliente['nombre']) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <button onclick="window.location.href='../clientes/agregar.php'" class="btnadd" style="width: 165px; height: 50px;">+ Nuevo cliente</button>
                        </div>
                    </div>
                </div>

                <!-- SECCIÓN TERRENO -->
                <div class="form-section" role="region" aria-labelledby="seccion-terreno">
                    <div class="titulo-formulario" style="margin-top: -7px;">Terreno</div>
                    <div class="form-group">
                        <label for="tipo_terreno">Tipo de Terreno *</label>
                        <select id="tipo_terreno" class="textfield">
                            <option value="">-- Selecciona --</option>
                            <option value="regular" <?= $cotizacion['tipo_terreno'] == 'regular' ? 'selected' : '' ?>>Regular</option>
                            <option value="irregular" <?= $cotizacion['tipo_terreno'] == 'irregular' ? 'selected' : '' ?>>Irregular</option>
                        </select>
                    </div>

                    <div id="terreno_regular" class="hidden-section" style="display: <?= $cotizacion['tipo_terreno'] == 'regular' ? 'block' : 'none' ?>;">
                        <div class="form-group">
                            <label for="forma_terreno">Forma *</label>
                            <select id="forma_terreno" class="textfield">
                                <option value="">-- Selecciona --</option>
                                <option value="rectangulo" <?= ($cotizacion['forma_terreno'] ?? '') == 'rectangulo' ? 'selected' : '' ?>>Rectángulo</option>
                                <option value="triangulo" <?= ($cotizacion['forma_terreno'] ?? '') == 'triangulo' ? 'selected' : '' ?>>Triángulo</option>
                                <option value="circulo" <?= ($cotizacion['forma_terreno'] ?? '') == 'circulo' ? 'selected' : '' ?>>Círculo</option>
                            </select>
                            <small style="color: #ff6b35; font-style: italic; display: block; margin-top: 5px;">
                                ⚠️ Las formas específicas no están disponibles en cotizaciones existentes
                            </small>
                        </div>

                        <div class="form-group">
                            <label>Dimensiones *</label>
                            <div style="display: flex; gap: 10px;">
                                <input type="number" id="dimension1" class="textfield" placeholder="Dimensión 1 (m)" step="0.01" min="0" value="<?= $cotizacion['dimension1'] ?? '' ?>">
                                <input type="number" id="dimension2" class="textfield" placeholder="Dimensión 2 (m)" step="0.01" min="0" value="<?= $cotizacion['dimension2'] ?? '' ?>">
                            </div>
                            <small style="color: #ff6b35; font-style: italic; display: block; margin-top: 5px;">
                                ⚠️ Las dimensiones específicas no están disponibles en cotizaciones existentes
                            </small>
                        </div>
                    </div>

                    <div id="terreno_irregular" class="hidden-section" style="display: <?= $cotizacion['tipo_terreno'] == 'irregular' ? 'block' : 'none' ?>;">
                        <div class="form-group">
                            <label for="area_irregular">Área estimada (m²)</label>
                            <input type="number" id="area_irregular" class="textfield" placeholder="0.00" step="0.01" min="0" value="<?= $cotizacion['area_irregular'] ?? '' ?>" style="display: none;">

                            <div class="formas-section">
                                <div class="section-header">
                                    <label>Formas geométricas</label>
                                    <button type="button" id="btn_agregar_forma" class="btn-secondary" style="margin-bottom: 10px;">+ Agregar Forma</button>
                                </div>
                                <div id="formas_container"></div>
                            </div>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="area_cotizada_original">Área cotizada original (m²)</label>
                        <input type="number" id="area_cotizada_original" class="textfield info-field" readonly value="<?= $cotizacion['area_total'] ?>">
                        <small style="color: #6c757d; font-style: italic; display: block; margin-top: 5px;">
                            ℹ️ Esta es el área de la cotización original. Las dimensiones y formas específicas no están disponibles en cotizaciones existentes.
                        </small>
                    </div>

                    <div class="form-group">
                        <label for="area_total">Área total actual (m²) *</label>
                        <input type="number" id="area_total" class="textfield" readonly value="<?= $cotizacion['area_total'] ?>">
                    </div>

                    <!-- Canvas para dibujar el terreno -->
                    <div class="form-group">
                        <label>Diseño del Terreno (Ilustrativo)</label>
                        <button type="button" class="btn-canvas" style="width: 100%; padding: 15px; margin: 10px 0;" onclick="abrirDisenadorTerreno()">
                            📐 Abrir Diseñador de Terreno
                        </button>
                        <div id="canvas-preview" style="margin-top: 10px;">
                            <?php if (!empty($cotizacion['dibujo_terreno'])): ?>
                                <p style="color: #4CAF50; font-size: 14px;">✅ Diseño guardado</p>
                            <?php else: ?>
                                <p style="color: #999; font-size: 14px;">Sin diseño</p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Columna 2 -->
            <div class="grid-col">
                <!-- SECCIÓN ROLLOS -->
                <div class="form-section" role="region" aria-labelledby="seccion-rollos">
                    <div class="titulo-formulario" style="margin-top: -7px;">Rollos de Pasto</div>
                    <div id="rollos-container">
                        <?php if (!empty($rollos_detalles)): ?>
                            <?php foreach ($rollos_detalles as $index => $rollo): ?>
                                <div class="product-item" data-index="<?= $index ?>">
                                    <div class="product-header">
                                        <select class="rollo-select textfield">
                                            <option value="">-- Selecciona Rollo --</option>
                                            <?php foreach ($rollos as $rollo_opt): ?>
                                                <option value="<?= $rollo_opt['id'] ?>"
                                                    data-precio="<?= $rollo_opt['costo_base'] ?>"
                                                    data-modelo="<?= htmlspecialchars($rollo_opt['modelo'] ?? '') ?>"
                                                    data-colores="<?= htmlspecialchars($rollo_opt['colores_asignados'] ?? '') ?>"
                                                    <?= $rollo_opt['id'] == $rollo['id_producto'] ? 'selected' : '' ?>>
                                                    <?= htmlspecialchars($rollo_opt['nombre']) ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                        <input type="number" class="textfield" placeholder="m²" min="0.01" step="0.01" value="<?= $rollo['cantidad'] ?>">
                                        <span class="product-price" style="font-weight: bold; color: #7dc042; width: 100px; text-align: right;">
                                            $<?= number_format($rollo['precio_unitario'] * $rollo['cantidad'], 2) ?>
                                        </span>
                                        <div class="eliminar">
                                            <i class="fa-solid fa-trash" type="button" onclick="removerRollo(this)"></i>
                                        </div>
                                    </div>
                                    <div class="rollo-details" style="display: block; margin-top: 10px;">
                                        <div><strong>Modelo:</strong> <span class="modelo-text"><?= htmlspecialchars($rollo['modelo'] ?? 'No especificado') ?></span></div>
                                        <div><strong>Color:</strong>
                                            <select class="color-select textfield" data-color-actual="<?= $rollo['id_color'] ?>">
                                                <option value="">-- Selecciona Color --</option>
                                                <option value="<?= $rollo['id_color'] ?>" selected>
                                                    <?= htmlspecialchars($rollo['nombre_color'] ?? 'Color no disponible') ?>
                                                </option>
                                            </select>
                                        </div>
                                        <div><strong>Área seleccionada:</strong> <span class="area-text"><?= $rollo['cantidad'] ?> m²</span></div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                    <button type="button" id="btn-agregar-rollo" class="btnadd">+ Agregar rollo</button>
                </div>

                <!-- SECCIÓN EXTRAS -->
                <div class="form-section" role="region" aria-labelledby="seccion-extras">
                    <div class="titulo-formulario" style="margin-top: -7px;">Extras</div>
                    <div class="form-group">
                        <?php foreach ($extras as $extra): ?>
                            <label class="checkbox-text">
                                <input type="checkbox" class="extra-check"
                                    data-id="<?= $extra['id'] ?>"
                                    data-precio="<?= $extra['precio'] ?>"
                                    <?= in_array($extra['id'], array_column($extras_cotizacion, 'id_extra')) ? 'checked' : '' ?>>
                                <?= htmlspecialchars($extra['nombre']) ?> ($<?= number_format($extra['precio'], 2) ?>)
                            </label>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

            <!-- Columna 3 -->
            <div class="grid-col">
                <!-- SECCIÓN INSTALACIÓN -->
                <div class="form-section" role="region" aria-labelledby="seccion-instalacion">
                    <div class="titulo-formulario" style="margin-top: -7px;">Instalación</div>
                    <div class="form-group">
                        <label for="tipo_instalacion">Tipo de instalación *</label>
                        <select id="tipo_instalacion" class="textfield" required>
                            <option value="">-- Selecciona --</option>
                            <option value="tierra" <?= $cotizacion['tipo_instalacion'] == 'tierra' ? 'selected' : '' ?>>Tierra</option>
                            <option value="concreto" <?= $cotizacion['tipo_instalacion'] == 'concreto' ? 'selected' : '' ?>>Concreto</option>
                            <option value="mixto" <?= $cotizacion['tipo_instalacion'] == 'mixto' ? 'selected' : '' ?>>Mixto</option>
                        </select>
                    </div>

                </div>

                <!-- SECCIÓN RESUMEN -->
                <div class="form-section" role="region" aria-labelledby="seccion-resumen">
                    <div class="titulo-formulario" style="margin-top: -7px;">Resumen</div>
                    <div class="form-group">
                        <label class="checkbox-text">
                            <input type="checkbox" id="aplicar_iva" <?= !empty($cotizacion['iva']) ? 'checked' : '' ?>> Aplicar IVA
                        </label>
                        <div class="summary-item">
                            <span>Subtotal:</span>
                            <span id="subtotal">$0.00</span>
                        </div>
                        <div class="summary-item" id="iva-container">
                            <span>IVA (<span id="iva-percent">16</span>%):</span>
                            <span id="iva">$0.00</span>
                        </div>
                        <div class="summary-item" style="font-weight: bold;">
                            <span>Total:</span>
                            <span id="total">$<?= number_format($cotizacion['total'], 2) ?></span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Botones de acción fijos -->
        <div class="actions-container">
            <button type="button" class="btnadd" id="btn-guardar-cotizacion">
                <i class="fas fa-save"></i> Guardar cambios
            </button>
            <button type="button" onclick="window.history.back()" class="btncancel">
                <i class="fas fa-times-circle"></i> Cancelar
            </button>
        </div>
    </div>

    <!-- Modal para Canvas -->
    <div id="canvasModal" class="modal" style="display: none; position: fixed; z-index: 1000; left: 0; top: 0; width: 100%; height: 100%; background-color: rgba(0,0,0,0.8);">
        <div class="modal-content" style="background-color: #fefefe; margin: 2% auto; padding: 20px; border-radius: 10px; width: 90%; max-width: 900px; height: 85%; display: flex; flex-direction: column;">
            <div class="modal-header" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; border-bottom: 2px solid #7dc042; padding-bottom: 15px;">
                <h2 style="color: #7dc042; margin: 0;">Diseñador de Terreno</h2>
                <button type="button" id="btn_cerrar_canvas" style="background: #dc3545; color: white; border: none; padding: 10px 15px; border-radius: 5px; cursor: pointer; font-size: 18px;">✕</button>
            </div>

            <div class="canvas-container" style="flex: 1; display: flex; flex-direction: column;">
                <div class="canvas-controls" style="display: flex; gap: 10px; margin-bottom: 15px; flex-wrap: wrap; justify-content: center;">
                    <button type="button" id="btn_limpiar_canvas" class="canvas-btn">🗑️ Limpiar</button>
                    <button type="button" id="btn_rectangulo" class="canvas-btn">⬜ Rectángulo</button>
                    <button type="button" id="btn_triangulo" class="canvas-btn">🔺 Triángulo</button>
                    <button type="button" id="btn_circulo" class="canvas-btn">⭕ Círculo</button>
                    <button type="button" id="btn_dibujo_libre" class="canvas-btn active">✏️ Dibujo Libre</button>
                </div>

                <div style="flex: 1; display: flex; justify-content: center; align-items: center; border: 2px solid #7dc042; border-radius: 10px; background: white;">
                    <canvas id="canvas_terreno" width="800" height="500" style="border: 1px solid #ccc; max-width: 100%; max-height: 100%;"></canvas>
                </div>

                <div class="modal-footer" style="display: flex; gap: 10px; margin-top: 15px; justify-content: flex-end;">
                    <button type="button" id="btn_guardar_canvas" class="btnadd">💾 Guardar Diseño</button>
                    <button type="button" id="btn_cancelar_canvas" class="btncancel">❌ Cancelar</button>
                </div>
            </div>
        </div>
    </div>

    <?php include_once '../../includes/popup.php'; ?>

    <!-- Scripts -->
    <script src="../../scripts/cotizaciones/formas_irregulares.js"></script>
    <script src="../../scripts/cotizaciones/canvas_terreno.js"></script>
    <script src="../../scripts/cotizaciones/modal_canvas.js"></script>
    <script>
        // Inicializar canvas después de cargar la página
        document.addEventListener('DOMContentLoaded', function() {
            if (document.getElementById('canvas_terreno')) {
                window.canvasTerreno = new CanvasTerreno();
                
                // Cargar dibujo existente si hay uno
                if (window.dibujoTerreno) {
                    setTimeout(() => {
                        const img = new Image();
                        img.onload = function() {
                            const canvas = document.getElementById('canvas_terreno');
                            const ctx = canvas.getContext('2d');
                            ctx.clearRect(0, 0, canvas.width, canvas.height);
                            ctx.drawImage(img, 0, 0);
                        };
                        img.src = window.dibujoTerreno;
                    }, 100);
                }
            }
        });
        
        // Variables globales para modo edición
        window.modoEdicion = true;
        window.idCotizacion = <?= $id_cotizacion ?>;
        window.dibujoTerreno = <?= json_encode($cotizacion['dibujo_terreno']) ?>;

        // Debug: mostrar datos de la cotización
        console.log('Datos de cotización:', <?= json_encode($cotizacion) ?>);

        // Cargar formas irregulares si existen
        <?php if (!empty($cotizacion['formas_irregulares'])): ?>
            window.formasIrregulares = <?= json_encode($cotizacion['formas_irregulares']) ?>;
        <?php endif; ?>
    </script>
    <script type="module" src="../../scripts/cotizaciones/editar_cotizacion.js"></script>

    <style>
        .canvas-btn,
        .btn-canvas {
            background: #7dc042;
            color: white;
            border: none;
            padding: 10px 15px;
            border-radius: 5px;
            cursor: pointer;
            font-size: 14px;
            transition: background-color 0.3s;
        }

        .canvas-btn:hover,
        .btn-canvas:hover {
            background: #6bb032;
        }

        .canvas-btn.active {
            background: #5a9427;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.2);
        }

        .btn-secondary {
            background: #6c757d;
            color: white;
            border: none;
            padding: 8px 12px;
            border-radius: 4px;
            cursor: pointer;
            font-size: 13px;
            transition: background-color 0.3s;
        }

        .btn-secondary:hover {
            background: #5a6268;
        }

        .product-item {
            margin-bottom: 15px;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
            background: #f9f9f9;
        }

        .product-header {
            display: flex;
            gap: 10px;
            align-items: center;
        }

        .product-header select {
            flex: 1;
        }

        .eliminar {
            color: #dc3545;
            cursor: pointer;
            padding: 5px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .eliminar:hover {
            color: #c82333;
        }

        .summary-item {
            display: flex;
            justify-content: space-between;
            margin: 5px 0;
            padding: 3px 0;
        }

        .checkbox-text {
            display: flex;
            align-items: center;
            gap: 8px;
            margin: 8px 0;
            font-size: 14px;
        }

        .rollo-details {
            background: #f0f0f0;
            padding: 10px;
            border-radius: 4px;
            font-size: 13px;
        }

        .rollo-details>div {
            margin: 5px 0;
        }

        .info-field {
            background: #f8f9fa;
            border: 1px solid #dee2e6;
            padding: 10px;
            border-radius: 4px;
            color: #6c757d;
            font-style: italic;
        }

        .hidden-section {
            transition: all 0.3s ease;
        }

        .formas-section {
            margin-top: 15px;
        }

        .section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 10px;
        }

        @media (max-width: 768px) {
            .form-container-grid {
                grid-template-columns: 1fr;
            }

            .actions-container {
                position: relative;
                bottom: auto;
                right: auto;
                margin-top: 20px;
                justify-content: center;
            }
        }
    </style>
</body>

</html>