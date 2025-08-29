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
?>

<!DOCTYPE html>
<html>

<head>
    <?php include_once $ROOT . '/includes/head.php'; ?>
    <link rel="stylesheet" href="../../css/cotizaciones/cotizaciones.css">
    <style>
        .estado-vacio {
            text-align: center;
            color: #666;
            padding: 30px 20px;
            background: #f8f9fa;
            border-radius: 8px;
            border: 2px dashed #dee2e6;
            grid-column: 1 / -1;
        }
    </style>
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
                            <!-- Nuevo selector de clientes con búsqueda -->
                            <div class="cliente-selector-container" style="width: 100%; position: relative;">
                                <input type="text"
                                    id="cliente-search"
                                    class="textfield cliente-search-input"
                                    placeholder="Buscar cliente por nombre..."
                                    autocomplete="off"
                                    required>
                                <input type="hidden" id="cliente" name="cliente" required>

                                <!-- Lista desplegable de resultados -->
                                <div class="cliente-dropdown" id="cliente-dropdown">
                                    <div class="cliente-dropdown-list" id="cliente-dropdown-list">
                                        <!-- Los clientes se cargarán aquí dinámicamente -->
                                    </div>
                                    <div class="cliente-dropdown-empty" style="display: none;">
                                        <i class="fas fa-search"></i> No se encontraron clientes
                                    </div>
                                </div>

                                <!-- Cliente seleccionado -->
                                <div class="cliente-selected" id="cliente-selected" style="display: none;">
                                    <div class="cliente-selected-info">
                                        <i class="fas fa-user"></i>
                                        <span class="cliente-selected-name"></span>
                                    </div>
                                    <button type="button" class="cliente-clear-btn" id="cliente-clear">
                                        <i class="fas fa-times"></i>
                                    </button>
                                </div>
                            </div>

                            <button onclick="window.location.href='../clientes/agregar.php'" class="btnadd" style="width: 165px; height: 43px;">+ Nuevo cliente</button>
                        </div>
                    </div>
                    <!-- NUEVO CAMPO DIRECCIÓN DE LA COTIZACIÓN -->
                    <div class="form-group">
                        <label for="direccion_cotizacion">
                            <span class="sr-only">Dirección de la cotización</span>
                            <span aria-hidden="true">Dirección *</span>
                        </label>
                        <input type="text" id="direccion_cotizacion" name="direccion_cotizacion" class="textfield" placeholder="Dirección donde se realizará la instalación" required style="width: 100%;">
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

                                <span class="area-automatica" style="font-weight: bold; color: #4a7c59; width: 80px; text-align: center; font-size: 14px;">0 m²</span>
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

                <!-- SECCIÓN EXTRAS -->
                <div class="form-section" role="region" aria-labelledby="seccion-extras">
                    <div class="titulo-formulario" style="margin-top: -7px;">Extras</div>
                    <div class="form-group">
                        <?php
                        $sql_extras = "SELECT id, nombre, precio FROM extras WHERE activo = 1";
                        $res_extras = mysqli_query($conn, $sql_extras);
                        $hay_extras = mysqli_num_rows($res_extras) > 0;
                        
                        if (!$hay_extras): ?>
                            <div class="estado-vacio">
                                <p>No hay extras disponibles</p>
                            </div>
                        <?php else: ?>
                            <?php while ($extra = mysqli_fetch_assoc($res_extras)) : ?>
                                <div class="extra-item" style="display: flex; align-items: center; margin-bottom: 12px; padding: 8px; border-radius: 5px; transition: background-color 0.3s;">
                                    <label class="checkbox-text" style="flex: 1; margin: 0; display: flex; align-items: center;">
                                        <input type="checkbox" class="extra-check"
                                            data-id="<?= $extra['id'] ?>"
                                            data-precio="<?= $extra['precio'] ?>"
                                            style="margin-right: 8px;">
                                        <span style="flex: 1;"><?= htmlspecialchars($extra['nombre']) ?></span>
                                    </label>
                                    <div class="extra-cantidad-container" style="display: none; margin-left: 15px; align-items: center;">
                                        <label for="extra_cantidad_<?= $extra['id'] ?>" style="margin-right: 5px; font-size: 12px; color: #666;">Cantidad:</label>
                                        <input type="number"
                                            id="extra_cantidad_<?= $extra['id'] ?>"
                                            class="extra-cantidad"
                                            data-extra-id="<?= $extra['id'] ?>"
                                            min="1"
                                            value="1"
                                            style="width: 60px; padding: 4px 6px; border: 1px solid #ddd; border-radius: 3px; text-align: center; font-size: 12px;">
                                    </div>
                                </div>
                            <?php endwhile; ?>
                        <?php endif; ?>

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

                        <!-- Resumen normal (1 rollo) -->
                        <div id="resumen-normal">
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
                                    <span>IVA (<span class="iva-percent-a">0</span>%):</span>
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
                                <div class="summary-item" style="display: flex; justify-content: space-between; gap: 16px;">
                                    <span style="margin-right: 10px;">Subtotal:</span>
                                    <span id="subtotal-b" style="margin-left: 10px;">$0.00</span>
                                </div>
                                <div class="summary-item iva-container-b" style="display: flex; justify-content: space-between; gap: 16px;">
                                    <span style="margin-right: 10px;">IVA (<span class="iva-percent-b">0</span>%):</span>
                                    <span id="iva-b" style="margin-left: 10px;">$0.00</span>
                                </div>
                                <div class="summary-item" style="font-weight: bold; font-size: 16px; display: flex; justify-content: space-between; gap: 16px;">
                                    <span style="margin-right: 10px;">Total:</span>
                                    <span id="total-b" style="margin-left: 10px;">$0.00</span>
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
        <div class="modal-content" style="background-color: #fefefe; margin: 1% auto; padding: 20px; border-radius: 10px; width: 95%; max-width: 1000px; height: 90%; display: flex; flex-direction: column; min-height: 600px;">
            <div class="modal-header" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; border-bottom: 2px solid #7dc042; padding-bottom: 15px;">
                <h2 style="color: #7dc042; margin: 0;">Diseñador de Terreno</h2>
                <button type="button" id="btn_cerrar_canvas" style="background: #dc3545; color: white; border: none; padding: 10px 15px; border-radius: 5px; cursor: pointer; font-size: 18px;">✕</button>
            </div>

            <div class="canvas-controls" style="display: flex; gap: 10px; margin-bottom: 15px; flex-wrap: wrap; justify-content: center;">
                <button type="button" id="btn_limpiar_canvas" class="canvas-btn">
                    <i class="fas fa-trash"></i> Limpiar
                </button>
                <button type="button" id="btn_rectangulo" class="canvas-btn">
                    <i class="far fa-square"></i> Rectángulo
                </button>
                <button type="button" id="btn_triangulo" class="canvas-btn">
                    <i class="fas fa-draw-polygon"></i> Triángulo
                </button>
                <button type="button" id="btn_circulo" class="canvas-btn">
                    <i class="far fa-circle"></i> Círculo
                </button>
                <button type="button" id="btn_dibujo_libre" class="canvas-btn active">
                    <i class="fas fa-pencil-alt"></i> Dibujo Libre
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
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.3);
        }
    </style>
</body>
<script src="../../scripts/cotizaciones/formas_irregulares.js"></script>
<script src="../../scripts/cotizaciones/canvas_terreno.js"></script>
<script src="../../scripts/cotizaciones/modal_canvas.js"></script>
<script>
    // Inicializar canvas después de cargar la página
    document.addEventListener('DOMContentLoaded', function() {
        if (document.getElementById('canvas_terreno')) {
            window.canvasTerreno = new CanvasTerreno();
        }
    });
</script>
<script src="../../scripts/cotizaciones/componentes/selector_clientes.js"></script>
<script type="module" src="../../scripts/cotizaciones/nueva_cotizacion.js"></script>


</html>