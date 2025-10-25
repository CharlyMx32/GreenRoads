<?php
$ROOT = '../../';
$TITULO = "Editar Cotización";

include_once $ROOT . '/db/conexion.php';
include_once $ROOT . '/includes/sesion.php';
include_once $ROOT . '/includes/config.php';

if (!tieneSesion()) {
    header("Location: $URL_ROOT/login");
    exit();
}

if (!puedeEditarCotizaciones()) {
    header("Location: ../dashboard/menu.php?error=sin_permisos");
    exit();
}

$id_cotizacion = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($id_cotizacion <= 0) {
    header("Location: lista.php");
    exit();
}

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

$rollos_detalles = [];
$sql_rollos = "SELECT dc.*, p.nombre as nombre_producto, c.nombre as nombre_color, c.codigo_hex, m.nombre as modelo
               FROM detalle_cotizacion dc
               LEFT JOIN productos p ON dc.id_producto = p.id
               LEFT JOIN colores c ON dc.id_color = c.id
               LEFT JOIN modelos m ON p.id_modelo = m.id
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
                            WHERE pc.id_producto = p.id) AS colores_asignados,
                           
                           (SELECT SUM(ir.area_m2)
                            FROM inventario_rollos ir
                            WHERE ir.id_producto = p.id AND ir.estado = 'disponible') AS area_disponible_total,
                            
                           (SELECT MIN(ir.costo_unitario)
                            FROM inventario_rollos ir
                            WHERE ir.id_producto = p.id) AS precio_unitario_rollo_completo
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
    <div class="main-wrapper" id="main-content">
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
                            <div class="cliente-selector-container" style="width: 100%; position: relative;">
                                <input type="text"
                                    id="cliente-search"
                                    class="textfield cliente-search-input"
                                    placeholder="Buscar cliente por nombre..."
                                    autocomplete="off"
                                    value="<?= htmlspecialchars($cotizacion['nombre_cliente']) ?>"
                                    style="display: none;">
                                <input type="hidden" id="cliente" name="cliente" value="<?= $cotizacion['id_cliente'] ?>" required>

                                <div class="cliente-dropdown" id="cliente-dropdown">
                                    <div class="cliente-dropdown-list" id="cliente-dropdown-list">
                                    </div>
                                    <div class="cliente-dropdown-empty" style="display: none;">
                                        <i class="fas fa-search"></i> No se encontraron clientes
                                    </div>
                                </div>

                                <div class="cliente-selected" id="cliente-selected" style="display: flex;">
                                    <div class="cliente-selected-info">
                                        <i class="fas fa-user"></i>
                                        <span class="cliente-selected-name"><?= htmlspecialchars($cotizacion['nombre_cliente']) ?></span>
                                    </div>
                                    <button type="button" class="cliente-clear-btn" id="cliente-clear">
                                        <i class="fas fa-times"></i>
                                    </button>
                                </div>
                            </div>

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

                    <div class="form-group">
                        <label>Diseño del Terreno (Ilustrativo)</label>
                        <button type="button" id="btn_abrir_canvas" class="btn-secondary" style="width: 100%; padding: 15px; margin: 10px 0;" onclick="abrirDisenadorTerreno()">
                            📐 Abrir Diseñador de Terreno
                        </button>
                        <div id="canvas_preview" style="<?= !empty($cotizacion['dibujo_terreno']) ? 'display: block;' : 'display: none;' ?> border: 1px solid #ddd; border-radius: 5px; padding: 10px; background: #f9f9f9; margin-top: 10px;">
                            <small>Vista previa del diseño guardado</small>
                            <canvas id="canvas_preview_small" width="250" height="150" style="border: 1px solid #ccc; width: 100%;"></canvas>
                        </div>
                        <?php if (empty($cotizacion['dibujo_terreno'])): ?>
                            <div id="canvas-no-preview" style="margin-top: 10px;">
                                <p style="color: #999; font-size: 14px;">Sin diseño</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Columna 2 -->
            <div class="grid-col">
                <!-- SECCIÓN ROLLOS -->
                <div class="form-section" role="region" aria-labelledby="seccion-rollos">
                    <div class="titulo-formulario" style="margin-top: -7px;">Rollos de Pasto</div>
                    <div id="rollos_container">
                        <?php if (!empty($rollos_detalles)): ?>
                            <?php foreach ($rollos_detalles as $index => $rollo): ?>
                                <div class="product-item" data-index="<?= $index ?>">
                                    <div class="product-header">
                                        <select class="rollo-select textfield">
                                            <option value="">-- Selecciona Rollo --</option>
                                            <?php foreach ($rollos as $rollo_opt): ?>
                                                <option value="<?= $rollo_opt['id'] ?>"
                                                    data-precio="<?= $rollo_opt['precio_unitario_rollo_completo'] ?? $rollo_opt['costo_base'] ?>"
                                                    data-modelo="<?= htmlspecialchars($rollo_opt['modelo'] ?? '') ?>"
                                                    data-colores="<?= htmlspecialchars($rollo_opt['colores_asignados'] ?? '') ?>"
                                                    data-area-disponible="<?= $rollo_opt['area_disponible_total'] ?? 0 ?>"
                                                    <?= $rollo_opt['id'] == $rollo['id_producto'] ? 'selected' : '' ?>>
                                                    <?= htmlspecialchars($rollo_opt['nombre']) ?> (<?= htmlspecialchars($rollo_opt['modelo'] ?? '') ?>)
                                                </option>
                                            <?php endforeach; ?>
                                        </select>

                                        <span class="area-automatica" style="font-weight: bold; color: #4a7c59; width: 80px; text-align: center; font-size: 14px;"><?= $rollo['cantidad'] ?> m²</span>
                                        <span class="product-price" style="font-weight: bold; color: #7dc042; width: 100px; text-align: right;">
                                            $<?= number_format($rollo['precio_unitario'] * $rollo['cantidad'], 2) ?>
                                        </span>
                                        <div class="eliminar">
                                            <i class="fa-solid fa-trash" type="button"></i>
                                        </div>
                                    </div>
                                    <div class="rollo-details" style="display: block; margin-top: 10px;">
                                        <div><strong>Modelo:</strong> <span class="modelo-text"><?= htmlspecialchars($rollo['modelo'] ?? 'No especificado') ?></span></div>
                                        <div><strong>Color:</strong>
                                            <select class="color-select textfield" data-color-actual="<?= $rollo['id_color'] ?>">
                                                <option value="">-- Selecciona Color --</option>
                                                <?php if ($rollo['id_color']): ?>
                                                    <option value="<?= $rollo['id_color'] ?>" selected>
                                                        <?= htmlspecialchars($rollo['nombre_color'] ?? 'Color no disponible') ?>
                                                    </option>
                                                <?php endif; ?>
                                            </select>
                                        </div>
                                        <div><strong>Área seleccionada:</strong> <span class="area-text"><?= $rollo['cantidad'] ?> m²</span></div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="product-item">
                                <div class="product-header">
                                    <select class="rollo-select textfield">
                                        <option value="">-- Selecciona Rollo --</option>
                                        <?php foreach ($rollos as $rollo_opt): ?>
                                            <option value="<?= $rollo_opt['id'] ?>"
                                                data-precio="<?= $rollo_opt['precio_unitario_rollo_completo'] ?? $rollo_opt['costo_base'] ?>"
                                                data-modelo="<?= htmlspecialchars($rollo_opt['modelo'] ?? '') ?>"
                                                data-colores="<?= htmlspecialchars($rollo_opt['colores_asignados'] ?? '') ?>"
                                                data-area-disponible="<?= $rollo_opt['area_disponible_total'] ?? 0 ?>">
                                                <?= htmlspecialchars($rollo_opt['nombre']) ?> (<?= htmlspecialchars($rollo_opt['modelo'] ?? '') ?>)
                                            </option>
                                        <?php endforeach; ?>
                                    </select>

                                    <span class="area-automatica" style="font-weight: bold; color: #4a7c59; width: 80px; text-align: center; font-size: 14px;">0 m²</span>
                                    <span class="product-price" style="font-weight: bold; color: #7dc042; width: 100px; text-align: right;">$0.00</span>
                                    <div class="eliminar">
                                        <i class="fa-solid fa-trash" type="button"></i>
                                    </div>
                                </div>

                                <div class="rollo-details" style="display: none; margin-top: 10px;">
                                    <div><strong>Modelo:</strong> <span class="modelo-text"></span></div>
                                    <div><strong>Colores:</strong> <span class="colores-text"></span></div>
                                    <div><strong>Área seleccionada:</strong> <span class="area-text"></span></div>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                    <button type="button" id="btn-agregar-rollo" class="btnadd">+ Agregar rollo</button>
                    <div id="limite-rollos-mensaje" class="limite-mensaje" style="display: none; margin-top: 10px; padding: 10px; background: #fff3cd; border: 1px solid #ffeaa7; border-radius: 4px; color: #856404; font-size: 13px;">
                        <i class="fas fa-info-circle"></i> Máximo 2 rollos para cotización comparativa
                    </div>
                </div>

                <!-- SECCIÓN EXTRAS -->
                <div class="form-section" role="region" aria-labelledby="seccion-extras">
                    <div class="titulo-formulario" style="margin-top: -7px;">Extras</div>
                    <div class="form-group">
                        <?php foreach ($extras as $extra): ?>
                            <?php
                            $isChecked = in_array($extra['id'], array_column($extras_cotizacion, 'id_extra'));
                            $precioAplicado = 0;
                            foreach ($extras_cotizacion as $extra_cot) {
                                if ($extra_cot['id_extra'] == $extra['id']) {
                                    $precioAplicado = $extra_cot['precio_aplicado'];
                                    break;
                                }
                            }
                            $cantidad = $isChecked ? max(1, intval($precioAplicado / $extra['precio'])) : 1;
                            ?>
                            <div class="extra-item <?= $isChecked ? 'selected' : '' ?>" style="display: flex; align-items: center; margin-bottom: 12px; padding: 8px; border-radius: 5px; transition: background-color 0.3s;">
                                <label class="checkbox-text" style="flex: 1; margin: 0; display: flex; align-items: center;">
                                    <input type="checkbox" class="extra-check"
                                        data-id="<?= $extra['id'] ?>"
                                        data-precio="<?= $extra['precio'] ?>"
                                        style="margin-right: 8px;"
                                        <?= $isChecked ? 'checked' : '' ?>>
                                    <span style="flex: 1;"><?= htmlspecialchars($extra['nombre']) ?> ($<?= number_format($extra['precio'], 2) ?>)</span>
                                </label>
                                <div class="extra-cantidad-container" style="<?= $isChecked ? 'display: flex;' : 'display: none;' ?> margin-left: 15px; align-items: center;">
                                    <label for="extra_cantidad_<?= $extra['id'] ?>" style="margin-right: 5px; font-size: 12px; color: #666;">Cantidad:</label>
                                    <input type="number" 
                                        id="extra_cantidad_<?= $extra['id'] ?>"
                                        class="extra-cantidad" 
                                        data-extra-id="<?= $extra['id'] ?>"
                                        min="1" 
                                        value="<?= $cantidad ?>" 
                                        style="width: 60px; padding: 4px 6px; border: 1px solid #ddd; border-radius: 3px; text-align: center; font-size: 12px;">
                                </div>
                            </div>
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

                        <!-- Resumen normal (1 rollo) -->
                        <div id="resumen-normal">
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

                        <!-- Resumen comparativo (2 rollos) -->
                        <div id="resumen-comparativo" style="display: none;">
                            <div class="cotizacion-opcion" style="border: 2px solid #7dc042; border-radius: 8px; margin-bottom: 15px; padding: 15px; background: #f8fff8;">
                                <h4 style="margin: 0 0 10px 0; color: #7dc042; font-size: 16px;">Opción A</h4>
                                <div class="nombre-rollo-a" style="font-weight: bold; margin-bottom: 8px; color: #4a7c59;"></div>
                                <div class="summary-item">
                                    <span>Subtotal:</span>
                                    <span id="subtotal-a">$0.00</span>
                                </div>
                                <div class="summary-item iva-container-a">
                                    <span>IVA (<span class="iva-percent-a">16</span>%):</span>
                                    <span id="iva-a">$0.00</span>
                                </div>
                                <div class="summary-item" style="font-weight: bold; font-size: 16px;">
                                    <span>Total:</span>
                                    <span id="total-a">$0.00</span>
                                </div>
                            </div>

                            <div class="cotizacion-opcion" style="border: 2px solid #6c757d; border-radius: 8px; margin-bottom: 15px; padding: 15px; background: #f8f9fa;">
                                <h4 style="margin: 0 0 10px 0; color: #6c757d; font-size: 16px;">Opción B</h4>
                                <div class="nombre-rollo-b" style="font-weight: bold; margin-bottom: 8px; color: #495057;"></div>
                                <div class="summary-item">
                                    <span>Subtotal:</span>
                                    <span id="subtotal-b">$0.00</span>
                                </div>
                                <div class="summary-item iva-container-b">
                                    <span>IVA (<span class="iva-percent-b">16</span>%):</span>
                                    <span id="iva-b">$0.00</span>
                                </div>
                                <div class="summary-item" style="font-weight: bold; font-size: 16px;">
                                    <span>Total:</span>
                                    <span id="total-b">$0.00</span>
                                </div>
                            </div>

                            <div style="margin-top: 15px; padding: 10px; background: #e8f5e8; border-radius: 5px; text-align: center;">
                                <strong style="color: #7dc042;">Diferencia: <span id="diferencia-precio">$0.00</span></strong>
                            </div>
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
        </div>

    <div id="canvasModal" class="modal" style="display: none; position: fixed; z-index: 1000; left: 0; top: 0; width: 100%; height: 100%; background-color: rgba(0,0,0,0.8);">';
        <div class="modal-content" style="background-color: #fefefe; margin: 1% auto; padding: 20px; border-radius: 10px; width: 95%; max-width: 1000px; height: 90%; display: flex; flex-direction: column; min-height: 600px;">
            <div class="modal-header" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; border-bottom: 2px solid #7dc042; padding-bottom: 15px;">
                <h2 style="color: #7dc042; margin: 0;">Diseñador de Terreno</h2>
                <button type="button" id="btn_cerrar_canvas" style="background: #dc3545; color: white; border: none; padding: 10px 15px; border-radius: 5px; cursor: pointer; font-size: 18px;">✕</button>
            </div>

            <div class="canvas-controls" style="display: flex; gap: 10px; margin-bottom: 15px; flex-wrap: wrap; justify-content: center;">
                <!-- Herramientas de dibujo -->
                <button type="button" id="btn_dibujo_libre" class="canvas-btn active" title="Dibujo Libre">
                    <i class="fas fa-pencil-alt"></i> Dibujo Libre
                </button>
                <button type="button" id="btn_rectangulo" class="canvas-btn" title="Rectángulo">
                    <i class="far fa-square"></i> Rectángulo
                </button>
                <button type="button" id="btn_triangulo" class="canvas-btn" title="Triángulo">
                    <i class="fas fa-draw-polygon"></i> Triángulo
                </button>
                <button type="button" id="btn_circulo" class="canvas-btn" title="Círculo">
                    <i class="far fa-circle"></i> Círculo
                </button>
                <button type="button" id="btn_texto" class="canvas-btn" title="Agregar Texto">
                    <i class="fas fa-font"></i> Texto
                </button>
                <button type="button" id="btn_eraser" class="canvas-btn" title="Goma de Borrar">
                    <i class="fas fa-eraser"></i> Borrador
                </button>
                
                <!-- Separador -->
                <div style="width: 2px; background: #ddd; margin: 0 5px;"></div>
                
                <!-- Selector de color -->
                <div style="display: flex; gap: 5px; align-items: center;">
                    <button type="button" class="color-btn active" data-color="green" title="Verde" style="background: #2c5530; width: 35px; height: 35px; border: 2px solid #ddd; border-radius: 5px; cursor: pointer; position: relative;">
                        <i class="fas fa-check" style="color: white; font-size: 14px;"></i>
                    </button>
                    <button type="button" class="color-btn" data-color="black" title="Negro" style="background: #000000; width: 35px; height: 35px; border: 2px solid #ddd; border-radius: 5px; cursor: pointer;">
                        <i class="fas fa-check" style="color: white; font-size: 14px;"></i>
                    </button>
                    <button type="button" class="color-btn" data-color="red" title="Rojo" style="background: #dc3545; width: 35px; height: 35px; border: 2px solid #ddd; border-radius: 5px; cursor: pointer;">
                        <i class="fas fa-check" style="color: white; font-size: 14px;"></i>
                    </button>
                    <button type="button" class="color-btn" data-color="blue" title="Azul" style="background: #007bff; width: 35px; height: 35px; border: 2px solid #ddd; border-radius: 5px; cursor: pointer;">
                        <i class="fas fa-check" style="color: white; font-size: 14px;"></i>
                    </button>
                    <button type="button" class="color-btn" data-color="gray" title="Gris" style="background: #6c757d; width: 35px; height: 35px; border: 2px solid #ddd; border-radius: 5px; cursor: pointer;">
                        <i class="fas fa-check" style="color: white; font-size: 14px;"></i>
                    </button>
                </div>
                
                <!-- Separador -->
                <div style="width: 2px; background: #ddd; margin: 0 5px;"></div>
                
                <!-- Acciones -->
                <button type="button" id="btn_undo" class="canvas-btn" title="Deshacer" disabled style="opacity: 0.5;">
                    <i class="fas fa-undo"></i> Deshacer
                </button>
                <button type="button" id="btn_limpiar_canvas" class="canvas-btn" title="Limpiar Todo" style="background: #dc3545;">
                    <i class="fas fa-trash"></i> Limpiar
                </button>
            </div>

            <div style="flex: 1; display: flex; justify-content: center; align-items: center; border: 2px solid #7dc042; border-radius: 10px; background: white; padding: 20px; min-height: 450px;">
                <canvas id="canvas_terreno" width="800" height="400" style="border: 1px solid #ccc; max-width: 100%; max-height: 100%;"></canvas>
            </div>

            <div class="modal-footer" style="display: flex; gap: 10px; margin-top: 15px; justify-content: flex-end;">
                <button type="button" id="btn_guardar_canvas" class="btnadd">
                    <i class="fas fa-save"></i> Guardar Diseño
                </button>
                <button type="button" id="btn_cancelar_canvas" class="btncancel">
                    <i class="fas fa-times"></i> Cancelar
                </button>
            </div>
        </div>
    </div>

    <?php include_once '../../includes/popup.php'; ?>

    <!-- Scripts -->
    <script src="../../scripts/cotizaciones/formas_irregulares.js"></script>
    <script src="../../scripts/cotizaciones/canvas_terreno.js"></script>
    <script src="../../scripts/cotizaciones/modal_canvas.js"></script>
    <script src="../../scripts/cotizaciones/componentes/selector_clientes.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            if (document.getElementById('canvas_terreno')) {
                window.canvasTerreno = new CanvasTerreno();
                
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
        
        window.modoEdicion = true;
        window.idCotizacion = <?= $id_cotizacion ?>;
        window.dibujoTerreno = <?= json_encode($cotizacion['dibujo_terreno']) ?>;
        
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
            transition: background-color 0.3s, opacity 0.3s;
        }

        .canvas-btn:hover:not(:disabled),
        .btn-canvas:hover {
            background: #6bb032;
        }

        .canvas-btn.active {
            background: #5a9427;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.2);
        }
        
        .canvas-btn:disabled {
            cursor: not-allowed;
            opacity: 0.5;
        }
        
        .color-btn {
            transition: transform 0.2s, border-color 0.2s;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .color-btn:hover {
            transform: scale(1.1);
            border-color: #7dc042 !important;
        }
        
        .color-btn.active {
            border-color: #7dc042 !important;
            border-width: 3px !important;
            box-shadow: 0 0 8px rgba(125, 192, 66, 0.5);
        }
        
        .color-btn .fa-check {
            display: none;
        }
        
        .color-btn.active .fa-check {
            display: block;
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

        .extra-item.selected {
            background-color: #f8fff8;
            border: 1px solid #7dc042;
            border-radius: 5px;
        }

        .cliente-selector-container {
            position: relative;
        }

        .cliente-dropdown {
            position: absolute;
            top: 100%;
            left: 0;
            right: 0;
            background: white;
            border: 1px solid #ddd;
            border-radius: 4px;
            max-height: 300px;
            overflow-y: auto;
            z-index: 1000;
            display: none;
        }

        .cliente-dropdown.visible {
            display: block;
        }

        .cliente-dropdown-item {
            padding: 12px;
            border-bottom: 1px solid #eee;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .cliente-dropdown-item:hover,
        .cliente-dropdown-item.highlighted {
            background-color: #f8f9fa;
        }

        .cliente-selected {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 12px;
            border: 1px solid #ddd;
            border-radius: 4px;
            background: #f8f9fa;
        }

        .cliente-selected-info {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .cliente-clear-btn {
            background: none;
            border: none;
            color: #dc3545;
            cursor: pointer;
            padding: 4px;
        }

        .canvas-btn,
        .btn-canvas,
        .btn-secondary {
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
        .btn-canvas:hover,
        .btn-secondary:hover {
            background: #6bb032;
        }

        .canvas-btn.active {
            background: #5a9427;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.2);
        }

        .cotizacion-opcion {
            margin-bottom: 15px;
        }

        .limite-mensaje {
            transition: all 0.3s ease;
            opacity: 0;
            max-height: 0;
            overflow: hidden;
        }

        .limite-mensaje.visible {
            opacity: 1;
            max-height: 100px;
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

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            if (document.getElementById('canvas_terreno')) {
                window.canvasTerreno = new CanvasTerreno();
                
                if (window.dibujoTerreno) {
                    setTimeout(() => {
                        try {
                            const img = new Image();
                            img.onload = function() {
                                const canvas = document.getElementById('canvas_terreno');
                                if (canvas) {
                                    const ctx = canvas.getContext('2d');
                                    ctx.clearRect(0, 0, canvas.width, canvas.height);
                                    ctx.drawImage(img, 0, 0, canvas.width, canvas.height);
                                }
                                
                                const previewCanvas = document.getElementById('canvas_preview_small');
                                if (previewCanvas) {
                                    const previewCtx = previewCanvas.getContext('2d');
                                    previewCtx.clearRect(0, 0, previewCanvas.width, previewCanvas.height);
                                    previewCtx.drawImage(img, 0, 0, previewCanvas.width, previewCanvas.height);
                                    document.getElementById('canvas_preview').style.display = 'block';
                                    const noPreview = document.getElementById('canvas-no-preview');
                                    if (noPreview) noPreview.style.display = 'none';
                                }
                            };
                            img.src = window.dibujoTerreno;
                        } catch (error) {
                        }
                    }, 100);
                }
            }

            if (window.selectorClientes && window.idCotizacion) {
                const clienteActual = <?= $cotizacion['id_cliente'] ?>;
                if (clienteActual) {
                    setTimeout(() => {
                        if (window.selectorClientes) {
                            window.selectorClientes.establecerCliente(clienteActual);
                        }
                    }, 500);
                }
            }
        });
        
        <?php if (!empty($cotizacion['formas_irregulares'])): ?>
            window.formasIrregulares = <?= json_encode($cotizacion['formas_irregulares']) ?>;
        <?php endif; ?>

        function toggleExtraQuantity(checkbox) {
            const extraItem = checkbox.closest('.extra-item');
            const cantidadContainer = extraItem.querySelector('.extra-cantidad-container');
            const cantidadInput = extraItem.querySelector('.extra-cantidad');
            
            if (checkbox.checked) {
                cantidadContainer.style.display = 'flex';
                extraItem.classList.add('selected');
                if (!cantidadInput.value || cantidadInput.value < 1) {
                    cantidadInput.value = 1;
                }
            } else {
                cantidadContainer.style.display = 'none';
                extraItem.classList.remove('selected');
            }
        }

        function updateExtraTotal(cantidadInput) {
            const cantidad = parseInt(cantidadInput.value) || 1;
            if (cantidad < 1) {
                cantidadInput.value = 1;
            }
        }

        window.abrirDisenadorTerreno = function() {
            document.getElementById('canvasModal').style.display = 'block';
        };
        
        document.addEventListener('DOMContentLoaded', function() {
            document.querySelectorAll('.extra-check').forEach(ck => {
                ck.addEventListener('change', function() {
                    toggleExtraQuantity(this);
                });
            });

            document.querySelectorAll('.extra-cantidad').forEach(input => {
                input.addEventListener('input', function() {
                    updateExtraTotal(this);
                });
            });

            setTimeout(() => {
                const areaTotalInput = document.getElementById('area_total');
                const areaOriginal = <?= $cotizacion['area_total'] ?>;
                if (areaTotalInput && areaOriginal > 0) {
                    areaTotalInput.value = areaOriginal;
                }
                
                document.querySelectorAll('#rollos_container .rollo-select').forEach((select, index) => {
                    if (select.value) {
                        cargarColoresRolloEdicion(select);
                        
                        const item = select.closest('.product-item');
                        const areaSpan = item.querySelector('.area-automatica');
                        if (areaSpan && areaOriginal > 0) {
                            areaSpan.textContent = areaOriginal + ' m²';
                        }
                    }
                });
                
                setTimeout(() => {
                    if (typeof verificarModoComparativo === 'function') {
                        verificarModoComparativo();
                    }
                    
                    const areaTotalInput = document.getElementById('area_total');
                    if (areaTotalInput) {
                        areaTotalInput.addEventListener('input', function() {
                            setTimeout(() => {
                                actualizarAreasAutomaticasEdicion();
                                if (typeof calcularTotales === 'function') {
                                    calcularTotales();
                                }
                            }, 100);
                        });
                    }
                    
                    const observer = new MutationObserver(function(mutations) {
                        mutations.forEach(function(mutation) {
                            if (mutation.type === 'childList') {
                                setTimeout(() => {
                                    actualizarAreasAutomaticasEdicion();
                                }, 200);
                            }
                        });
                    });
                    
                    const rollosContainer = document.getElementById('rollos_container');
                    if (rollosContainer) {
                        observer.observe(rollosContainer, { childList: true, subtree: true });
                    }
                }, 500);
            }, 1000);
        });

        async function cargarColoresRolloEdicion(selectRollo) {
            const productId = selectRollo.value;
            if (!productId) return;

            const item = selectRollo.closest('.product-item');
            const colorSelect = item.querySelector('.color-select');
            const colorActual = colorSelect.dataset.colorActual;
            
            try {
                const response = await fetch('../../php/cotizaciones/obtener_colores.php?id_producto=' + productId);
                const colores = await response.json();
                
                if (Array.isArray(colores)) {
                    colorSelect.innerHTML = '<option value="">-- Selecciona Color --</option>';
                    
                    colores.forEach(color => {
                        const option = document.createElement('option');
                        option.value = color.id;
                        option.textContent = color.nombre;
                        option.style.backgroundColor = color.codigo_hex;
                        option.style.color = getContrastColor(color.codigo_hex);
                        
                        if (color.id == colorActual) {
                            option.selected = true;
                        }
                        
                        colorSelect.appendChild(option);
                    });
                    
                    if (colorActual && colorSelect.value === '') {
                        setTimeout(() => {
                            colorSelect.value = colorActual;
                        }, 100);
                    }
                    
                    const modeloText = item.querySelector('.modelo-text');
                    const selectedOption = selectRollo.selectedOptions[0];
                    if (modeloText && selectedOption) {
                        modeloText.textContent = selectedOption.dataset.modelo || 'No especificado';
                    }
                }
            } catch (error) {
            }
        }

        // Función auxiliar para determinar color de contraste
        function getContrastColor(hexColor) {
            if (!hexColor) return '#000000';
            
            // Remover el # si está presente
            hexColor = hexColor.replace('#', '');
            
            // Convertir a RGB
            const r = parseInt(hexColor.substr(0, 2), 16);
            const g = parseInt(hexColor.substr(2, 2), 16);
            const b = parseInt(hexColor.substr(4, 2), 16);
            
            // Calcular luminancia
            const luminance = (0.299 * r + 0.587 * g + 0.114 * b) / 255;
            
            return luminance > 0.5 ? '#000000' : '#ffffff';
        }

        function actualizarAreasAutomaticasEdicion() {
            const areaTotalInput = document.getElementById('area_total');
            const areaTotal = areaTotalInput ? parseFloat(areaTotalInput.value) || 0 : 0;
            
            const areaAUsar = areaTotal > 0 ? areaTotal : <?= $cotizacion['area_total'] ?>;
            
            const container = document.getElementById('rollos_container');
            if (!container || areaAUsar <= 0) return;
            
            container.querySelectorAll('.product-item').forEach(item => {
                const areaSpan = item.querySelector('.area-automatica');
                const areaTextSpan = item.querySelector('.area-text');
                const priceSpan = item.querySelector('.product-price');
                const select = item.querySelector('.rollo-select');
                
                if (areaSpan) {
                    areaSpan.textContent = areaAUsar + ' m²';
                }
                
                if (areaTextSpan) {
                    areaTextSpan.textContent = areaAUsar + ' m²';
                }
                
                if (select && select.value && priceSpan) {
                    const selectedOption = select.selectedOptions[0];
                    if (selectedOption) {
                        const precioUnitario = parseFloat(selectedOption.dataset.precio) || 0;
                        const subtotal = areaAUsar * precioUnitario;
                        priceSpan.textContent = '$' + subtotal.toFixed(2);
                    }
                }
            });
        }
        
        window.toggleExtraQuantity = toggleExtraQuantity;
        window.updateExtraTotal = updateExtraTotal;
        window.actualizarAreasAutomaticasEdicion = actualizarAreasAutomaticasEdicion;
    </script>
    <script type="module" src="../../scripts/cotizaciones/editar_cotizacion.js"></script>
</body>

</html>