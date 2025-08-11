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
$sql_cotizacion = "SELECT c.*, cl.nombre as nombre_cliente 
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

// Obtener detalles de productos
$productos_detalles = [];
$sql_productos_det = "SELECT dc.*, p.nombre as nombre_producto
                      FROM detalle_cotizacion dc
                      LEFT JOIN productos p ON dc.id_producto = p.id
                      WHERE dc.id_cotizacion = ? AND dc.id_color IS NULL
                      ORDER BY dc.id";
$stmt_productos = mysqli_prepare($conn, $sql_productos_det);
mysqli_stmt_bind_param($stmt_productos, "i", $id_cotizacion);
mysqli_stmt_execute($stmt_productos);
$result_productos = mysqli_stmt_get_result($stmt_productos);
while ($row = mysqli_fetch_assoc($result_productos)) {
    $productos_detalles[] = $row;
}
mysqli_stmt_close($stmt_productos);

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

$productos = [];
$sql_productos = "SELECT p.id, p.nombre, p.costo_base, u.simbolo AS unidad, m.nombre AS modelo
    FROM productos p
    JOIN unidades u ON p.id_unidad = u.id
    LEFT JOIN modelos m ON p.id_modelo = m.id
    WHERE p.tipo_inventario = 'unidad' AND p.estado = 'activo'
    ORDER BY p.nombre";

$result = mysqli_query($conn, $sql_productos);
while ($row = mysqli_fetch_assoc($result)) {
    $productos[] = $row;
}

$rollos = [];
$sql_rollos_disponibles = "SELECT p.id, p.nombre, p.costo_base, m.nombre AS modelo
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
                                <option value="rectangulo">Rectángulo</option>
                                <option value="triangulo">Triángulo</option>
                                <option value="circulo">Círculo</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label for="dimension1" id="label_dimension1">Dimensión 1 (m)</label>
                            <input type="number" id="dimension1" class="textfield" placeholder="0.00" step="0.01" min="0">
                        </div>

                        <div class="form-group" id="grupo_dimension2">
                            <label for="dimension2" id="label_dimension2">Dimensión 2 (m)</label>
                            <input type="number" id="dimension2" class="textfield" placeholder="0.00" step="0.01" min="0">
                        </div>
                    </div>

                    <div id="terreno_irregular" class="hidden-section" style="display: <?= $cotizacion['tipo_terreno'] == 'irregular' ? 'block' : 'none' ?>;">
                        <div class="form-group">
                            <label for="area_irregular">Área estimada (m²)</label>
                            <input type="number" id="area_irregular" class="textfield" placeholder="0.00" step="0.01" min="0">
                        </div>
                        
                        <div class="formas-section">
                            <div class="section-header">
                                <label>Formas geométricas</label>
                                <button type="button" id="btn_agregar_forma" class="btnadd">+ Agregar forma</button>
                            </div>
                            <div id="formas_container"></div>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="area_total">Área total (m²) *</label>
                        <input type="number" id="area_total" class="textfield" readonly value="<?= $cotizacion['area_total'] ?>">
                    </div>

                    <div class="form-group">
                        <label for="tipo_instalacion">Tipo de instalación *</label>
                        <select id="tipo_instalacion" class="textfield" required>
                            <option value="">-- Selecciona --</option>
                            <option value="tierra" <?= $cotizacion['tipo_instalacion'] == 'tierra' ? 'selected' : '' ?>>Tierra</option>
                            <option value="concreto" <?= $cotizacion['tipo_instalacion'] == 'concreto' ? 'selected' : '' ?>>Concreto</option>
                            <option value="mixto" <?= $cotizacion['tipo_instalacion'] == 'mixto' ? 'selected' : '' ?>>Mixto</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <button type="button" onclick="abrirDisenadorTerreno()" class="btn-canvas">
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
                    <div class="titulo-formulario" style="margin-top: -7px;">Rollos de césped artificial</div>
                    <div id="rollos-container">
                        <?php if (!empty($rollos_detalles)): ?>
                            <?php foreach ($rollos_detalles as $index => $rollo): ?>
                                <div class="rollo-item" data-index="<?= $index ?>">
                                    <div class="form-group">
                                        <label>Producto *</label>
                                        <select class="textfield producto-select" required>
                                            <option value="">-- Selecciona --</option>
                                            <?php foreach ($rollos as $rollo_opt): ?>
                                                <option value="<?= $rollo_opt['id'] ?>" <?= $rollo_opt['id'] == $rollo['id_producto'] ? 'selected' : '' ?>><?= htmlspecialchars($rollo_opt['nombre']) ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="form-group">
                                        <label>Color *</label>
                                        <select class="textfield color-select" required>
                                            <option value="<?= $rollo['id_color'] ?>" selected><?= htmlspecialchars($rollo['nombre_color']) ?></option>
                                        </select>
                                    </div>
                                    <div class="form-group">
                                        <label>Cantidad (m²) *</label>
                                        <input type="number" class="textfield cantidad-input" placeholder="0.00" step="0.01" min="0" required value="<?= $rollo['cantidad'] ?>">
                                    </div>
                                    <div class="form-group">
                                        <button type="button" onclick="removerRollo(this)" class="btn-remove">Eliminar</button>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                    <button type="button" onclick="agregarRollo()" class="btnadd">+ Agregar rollo</button>
                </div>

                <!-- SECCIÓN MATERIALES -->
                <div class="form-section" role="region" aria-labelledby="seccion-materiales">
                    <div class="titulo-formulario">Materiales adicionales</div>
                    <div id="materiales-container">
                        <?php if (!empty($productos_detalles)): ?>
                            <?php foreach ($productos_detalles as $index => $producto): ?>
                                <div class="material-item" data-index="<?= $index ?>">
                                    <div class="form-group">
                                        <label>Producto *</label>
                                        <select class="textfield producto-select" required>
                                            <option value="">-- Selecciona --</option>
                                            <?php foreach ($productos as $prod_opt): ?>
                                                <option value="<?= $prod_opt['id'] ?>" <?= $prod_opt['id'] == $producto['id_producto'] ? 'selected' : '' ?>><?= htmlspecialchars($prod_opt['nombre']) ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="form-group">
                                        <label>Cantidad *</label>
                                        <input type="number" class="textfield cantidad-input" placeholder="0.00" step="0.01" min="0" required value="<?= $producto['cantidad'] ?>">
                                    </div>
                                    <div class="form-group">
                                        <button type="button" onclick="removerMaterial(this)" class="btn-remove">Eliminar</button>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                    <button type="button" onclick="agregarMaterial()" class="btnadd">+ Agregar material</button>
                </div>
            </div>

            <!-- Columna 3 -->
            <div class="grid-col">
                <!-- SECCIÓN RESUMEN -->
                <div class="form-section" role="region" aria-labelledby="seccion-resumen">
                    <div class="titulo-formulario" style="margin-top: -7px;">Resumen</div>
                    <div id="resumen-container">
                        <div class="form-group" style="margin-top: 15px;">
                            <label>
                                <input type="checkbox" id="aplicar_iva" <?= !empty($cotizacion['total']) && (float)$cotizacion['total'] > ((float)$cotizacion['area_total'] * (float)$cotizacion['precio_instalacion_m2']) ? 'checked' : '' ?>>
                                Aplicar IVA (16%)
                            </label>
                        </div>
                        <div class="resumen-item total">
                            <span>Total:</span>
                            <span id="total-final">$<?= isset($cotizacion['total']) ? number_format((float)$cotizacion['total'], 2) : '0.00' ?></span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Botones de acción fijos -->
        <div class="actions-container">
            <button type="button" onclick="guardarCotizacion()" class="btnadd">
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

    <!-- Scripts -->
    <script src="../../scripts/cotizaciones/nueva_cotizacion.js"></script>
    <script src="../../scripts/cotizaciones/modal_canvas.js"></script>
    <script src="../../scripts/cotizaciones/canvas_terreno.js"></script>
    <script src="../../scripts/cotizaciones/componentes/rollos.js"></script>
    <script src="../../scripts/cotizaciones/core/totales.js"></script>

    <script>
        // Variables globales para modo edición
        window.modoEdicion = true;
        window.idCotizacion = <?= $id_cotizacion ?>;
        window.dibujoTerreno = <?= json_encode($cotizacion['dibujo_terreno']) ?>;

        document.addEventListener('DOMContentLoaded', function() {
            // Configurar preview inicial si hay dibujo
            if (window.dibujoTerreno) {
                const preview = document.getElementById('canvas-preview');
                if (preview) {
                    preview.innerHTML = '<p style="color: #4CAF50; font-size: 14px;">✅ Diseño guardado</p>';
                }
            }
            
            // Configurar eventos básicos
            configurarEventosCalculos();
            actualizarTotales();
        });

        function configurarEventosCalculos() {
            document.getElementById('tipo_terreno')?.addEventListener('change', function() {
                const tipo = this.value;
                const regular = document.getElementById('terreno_regular');
                const irregular = document.getElementById('terreno_irregular');
                
                if (regular) regular.style.display = tipo === 'regular' ? 'block' : 'none';
                if (irregular) irregular.style.display = tipo === 'irregular' ? 'block' : 'none';
            });
        }

        // Función personalizada para edición
        function guardarCotizacion() {
            const datosFormulario = recopilarDatosFormulario();
            datosFormulario.id_cotizacion = window.idCotizacion;
            
            fetch('../../php/cotizaciones/actualizar_cotizacion.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify(datosFormulario)
            })
            .then(response => response.json())
            .then(data => {
                if (data.status === 1) {
                    alert('✅ Cotización actualizada correctamente');
                    window.location.href = 'lista.php';
                } else {
                    alert('❌ Error al actualizar: ' + data.mensaje);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('❌ Error al guardar la cotización');
            });
        }

        function recopilarDatosFormulario() {
            const datos = {
                id_cliente: document.getElementById('cliente').value,
                tipo_terreno: document.getElementById('tipo_terreno').value,
                tipo_instalacion: document.getElementById('tipo_instalacion').value,
                area_total: document.getElementById('area_total').value,
                total: parseFloat(document.getElementById('total-final').textContent.replace('$', '').replace(',', '')) || 0,
                rollos: [],
                materiales: [],
                dibujo_terreno: window.canvasData || window.dibujoTerreno || null
            };

            // Recolectar rollos
            document.querySelectorAll('#rollos-container .rollo-item').forEach(item => {
                const producto = item.querySelector('.producto-select');
                const color = item.querySelector('.color-select');
                const cantidad = item.querySelector('.cantidad-input');
                
                if (producto?.value && color?.value && cantidad?.value) {
                    datos.rollos.push({
                        id_producto: parseInt(producto.value),
                        id_color: parseInt(color.value),
                        cantidad: parseFloat(cantidad.value),
                        precio_unitario: 0,
                        subtotal: 0
                    });
                }
            });

            // Recolectar materiales
            document.querySelectorAll('#materiales-container .material-item').forEach(item => {
                const producto = item.querySelector('.producto-select');
                const cantidad = item.querySelector('.cantidad-input');
                
                if (producto?.value && cantidad?.value) {
                    datos.materiales.push({
                        id_producto: parseInt(producto.value),
                        cantidad: parseFloat(cantidad.value),
                        precio_unitario: 0,
                        subtotal: 0
                    });
                }
            });

            return datos;
        }

        // Funciones básicas para agregar/remover
        function agregarRollo() {
            console.log('Agregar rollo - usando sistema de nueva cotización');
        }

        function removerRollo(btn) {
            const item = btn.closest('.rollo-item');
            if (item) {
                item.remove();
                actualizarTotales();
            }
        }

        function agregarMaterial() {
            console.log('Agregar material - usando sistema de nueva cotización');
        }

        function removerMaterial(btn) {
            const item = btn.closest('.material-item');
            if (item) {
                item.remove();
                actualizarTotales();
            }
        }

        function actualizarTotales() {
            console.log('Actualizando totales...');
            // El total ya está pre-calculado desde PHP
        }

        // Función para abrir el canvas (reutilizando sistema de nueva cotización)
        function abrirDisenadorTerreno() {
            const modal = document.getElementById('canvasModal');
            modal.style.display = 'block';
            
            // Esperar a que el canvas esté inicializado
            setTimeout(() => {
                if (window.dibujoTerreno && window.canvasTerreno) {
                    window.canvasTerreno.loadCanvasFromBase64('data:image/png;base64,' + window.dibujoTerreno);
                }
            }, 100);
        }
    </script>

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
            background: #6bb032;
        }

        .canvas-btn.active {
            background: #5a9427;
            box-shadow: 0 2px 4px rgba(0,0,0,0.2);
        }
    </style>
</body>
</html>