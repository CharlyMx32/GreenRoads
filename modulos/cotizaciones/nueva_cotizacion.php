<?php
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

$ROOT = '../../';
$TITULO = "Nueva Cotización";

include_once $ROOT . '/db/conexion.php';
include_once $ROOT . '/includes/sesion.php';
include_once $ROOT . '/includes/config.php';

if (!tieneSesion()) {
    header("Location: $URL_ROOT/login");
    exit();
}

$clientes = [];
$sql = "SELECT id, nombre FROM clientes WHERE estado = 'activo' ORDER BY nombre ASC";
$result = mysqli_query($conn, $sql);
while ($row = mysqli_fetch_assoc($result)) {
    $clientes[] = $row;
}

$productos = [];
$sql_productos = "SELECT p.id, p.nombre, u.simbolo AS unidad
    FROM productos p
    JOIN unidades u ON p.id_unidad = u.id
    WHERE p.tipo_inventario = 'unidad' AND p.estado = 'activo'";

$result = mysqli_query($conn, $sql_productos);
while ($row = mysqli_fetch_assoc($result)) {
    $productos[] = $row;
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
        "titulo" => $TITULO,
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
                                    <option value="<?= $cliente['id'] ?>"><?= htmlspecialchars($cliente['nombre']) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <button onclick="window.location.href='../clientes/agregar.php'" class="btnadd" style="width: 165px;
                            height: 50px;">+ Nuevo cliente</button>
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
                            <option value="regular">Regular</option>
                            <option value="irregular">Irregular</option>
                        </select>
                    </div>

                    <div id="terreno_regular" class="hidden-section">
                        <div class="form-group">
                            <label for="forma_terreno">Forma *</label>
                            <select id="forma_terreno" class="textfield">
                                <option value="">-- Selecciona --</option>
                                <option value="rectangulo">Rectángulo</option>
                                <option value="triangulo">Triángulo</option>
                                <option value="circulo">Círculo</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label>Dimensiones *</label>
                            <div style="display: flex; gap: 10px;">
                                <input type="number" id="dimension1" class="textfield" placeholder="Dimensión 1 (m)" step="0.01" min="0">
                                <input type="number" id="dimension2" class="textfield" placeholder="Dimensión 2 (m)" step="0.01" min="0">
                            </div>
                        </div>
                    </div>

                    <div id="terreno_irregular" class="hidden-section">
                        <div class="form-group">
                            <input type="number" id="area_irregular" class="textfield" placeholder="Área estimada (m2)" step="0.01" min="0" style="display: none;">
                            <button type="button" id="btn_agregar_forma" class="btn-secondary" style="margin-bottom: 10px;">+ Agregar Forma</button>
                            <div id="formas_container"></div>
                        </div>
                    </div>

                    <div class="form-group">
                        <label>Área Total (m2)</label>
                        <input type="number" id="area_total" class="textfield" readonly>
                    </div>

                    <!-- Canvas para dibujar el terreno -->
                    <div class="form-group">
                        <label>Diseño del Terreno (Ilustrativo)</label>
                        <button type="button" id="btn_abrir_canvas" class="btn-secondary" style="width: 100%; padding: 15px; margin: 10px 0;">
                            Abrir Diseñador de Terreno
                        </button>
                        <div id="canvas_preview" style="display: none; border: 1px solid #ddd; border-radius: 5px; padding: 10px; background: #f9f9f9;">
                            <small>Vista previa del diseño guardado</small>
                            <canvas id="canvas_preview_small" width="250" height="150" style="border: 1px solid #ccc; width: 100%;"></canvas>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Columna 2 -->
            <div class="grid-col">
                <!-- SECCIÓN ROLLOS DE PASTO -->
                <div class="form-section" role="region" aria-labelledby="seccion-rollos">
                    <div class="titulo-formulario" style="margin-top: -7px;">Rollos de Pasto</div>
                    <div id="rollos_container">
                        <div class="product-item">
                            <div class="product-header">
                                <select class="rollo-select textfield">
                                    <option value="">-- Selecciona Rollo --</option>
                                    <?php
                                    $sql_rollos = "SELECT 
                                        p.id, 
                                        p.nombre, 
                                        m.nombre AS modelo,
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
                                    JOIN modelos m ON p.id_modelo = m.id
                                    WHERE p.tipo_inventario = 'rollo' 
                                    AND p.estado = 'activo'";
                                    $result_rollos = mysqli_query($conn, $sql_rollos);
                                    while ($rollo = mysqli_fetch_assoc($result_rollos)) :
                                        $precio = $rollo['precio_unitario_rollo_completo'] ?? 0;
                                        $modelo = htmlspecialchars($rollo['modelo'] ?? '');
                                        $colores = htmlspecialchars($rollo['colores_asignados'] ?? '');
                                        $area = $rollo['area_disponible_total'] ?? 0;
                                        $nombre = htmlspecialchars($rollo['nombre'] ?? '');
                                    ?>
                                        <option value="<?= $rollo['id'] ?>"
                                            data-precio="<?= $precio ?>"
                                            data-modelo="<?= $modelo ?>"
                                            data-colores="<?= $colores ?>"
                                            data-area-disponible="<?= $area ?>">
                                            <?= $nombre ?> (<?= $modelo ?>)
                                        </option>
                                    <?php endwhile; ?>
                                </select>

                                <input type="number" class="textfield" placeholder="m²" min="0.01" step="0.01" style="width: 80px;">
                                <span class="product-price" style="font-weight: bold; color: #7dc042; width: 100px; text-align: right;">$0.00</span>
                                <div class="eliminar">
                                    <i class="fa-solid fa-trash" type="button" id="btn-remover-rollo"></i>
                                </div>
                            </div>

                            <div class="rollo-details" style="display: none; margin-top: 10px;">
                                <div><strong>Modelo:</strong> <span class="modelo-text"></span></div>
                                <div><strong>Colores:</strong> <span class="colores-text"></span></div>
                                <div><strong>Área seleccionada:</strong> <span class="area-text"></span></div>
                            </div>

                        </div>
                    </div>
                    <button type="button" id="btn-agregar-rollo" style="margin-top: 10px;" class="btnadd">+ Agregar rollo</button>
                </div>

                <!-- SECCIÓN PRODUCTOS GENERALES -->
                <div class="form-section" role="region" aria-labelledby="seccion-productos">
                    <div class="titulo-formulario" style="margin-top: -7px;">Otros Productos</div>
                    <div id="productos_container">
                        <div class="product-item">
                            <div class="product-header">
                                <select class="product-select textfield">
                                    <option value="">-- Selecciona Producto --</option>
                                    <?php
                                    $sql_productos = "SELECT p.id, p.nombre, u.simbolo AS unidad, mi.costo_unitario
                                        FROM productos p
                                        JOIN unidades u ON p.id_unidad = u.id
                                        LEFT JOIN movimientos_inventario mi ON mi.id_producto = p.id
                                        WHERE p.tipo_inventario = 'unidad' AND p.estado = 'activo'
                                        AND mi.fecha = (
                                        SELECT MAX(fecha) FROM movimientos_inventario WHERE id_producto = p.id
                                        )";
                                    $result_productos = mysqli_query($conn, $sql_productos);
                                    while ($producto = mysqli_fetch_assoc($result_productos)) : ?>
                                        <option value="<?= $producto['id'] ?>"
                                            data-precio="<?= number_format($producto['costo_unitario'], 2, '.', '') ?>"
                                            data-unidad="<?= $producto['unidad'] ?>">
                                            <?= htmlspecialchars($producto['nombre']) ?> (<?= $producto['unidad'] ?>)
                                        </option>
                                    <?php endwhile; ?>
                                </select>
                                <input type="number" class="textfield" placeholder="Cantidad" min="1" value="1" style="width: 80px;">
                                <div class="eliminar">
                                    <i class="fa-solid fa-trash" type="button" id="removerProducto(this)"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- SECCIÓN EXTRAS -->
                <div class="form-section" role="region" aria-labelledby="seccion-extras">
                    <div class="titulo-formulario" style="margin-top: -7px;">Extras</div>
                    <div class="form-group">
                        <?php
                        $sql_extras = "SELECT id, nombre, precio FROM extras WHERE activo = 1";
                        $res_extras = mysqli_query($conn, $sql_extras);
                        while ($extra = mysqli_fetch_assoc($res_extras)) : ?>
                            <label class="checkbox-text">
                                <input type="checkbox" class="extra-check"
                                    data-id="<?= $extra['id'] ?>"
                                    data-precio="<?= $extra['precio'] ?>">
                                <?= htmlspecialchars($extra['nombre']) ?>
                            </label>
                        <?php endwhile; ?>
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
                            <option value="tierra">Tierra</option>
                            <option value="concreto">Concreto</option>
                            <option value="mixto">Mixto</option>
                        </select>
                    </div>
                </div>

                <!-- SECCIÓN RESUMEN -->
                <div class="form-section" role="region" aria-labelledby="seccion-resumen">
                    <div class="titulo-formulario" style="margin-top: -7px;">Resumen</div>
                    <div class="form-group">
                        <label class="checkbox-text">
                            <input type="checkbox" id="aplicar_iva" checked> Aplicar IVA
                        </label>
                        <div class="summary-item">
                            <span>Subtotal:</span>
                            <span id="subtotal">$0.00</span>
                        </div>
                        <div class="summary-item" id="iva-container">
                            <span>IVA (<span id="iva-percent">0</span>%):</span>
                            <span id="iva">$0.00</span>
                        </div>
                        <div class="summary-item" style="font-weight: bold;">
                            <span>Total:</span>
                            <span id="total">$0.00</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

    </div>
    <div class="actions-container">
        <button class="btncancel" type="button" onclick="window.history.back()">
            <i class="fas fa-times-circle"></i> Cancelar
        </button>
        <button id="btn-guardar-cotizacion" class="btnadd" style="margin: 0;" type="button">
            <i class="fas fa-save"></i> Guardar
        </button>
    </div>

    <?php include_once '../../includes/popup.php'; ?>

    <!-- Modal para Canvas de Terreno -->
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

    <style>
        .canvas-btn {
            background: #7dc042;
            color: white;
            border: none;
            padding: 10px 15px;
            border-radius: 5px;
            cursor: pointer;
            font-size: 14px;
            transition: background-color 0.3s;
        }
        .canvas-btn:hover {
            background: #4a7c59;
        }
        .canvas-btn.active {
            background: #8bd792ff;
            box-shadow: 0 2px 4px rgba(0,0,0,0.3);
        }
    </style>
</body>
<script src="../../scripts/cotizaciones/formas_irregulares.js"></script>
<script src="../../scripts/cotizaciones/canvas_terreno.js"></script>
<script src="../../scripts/cotizaciones/modal_canvas.js"></script>
<script type="module" src="../../scripts/cotizaciones/nueva_cotizacion.js"></script>


</html>