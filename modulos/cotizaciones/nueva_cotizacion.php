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

// Obtener lista de clientes y productos
$clientes = [];
$sql = "SELECT id, nombre FROM clientes ORDER BY nombre ASC";
$result = mysqli_query($conn, $sql);
while ($row = mysqli_fetch_assoc($result)) {
    $clientes[] = $row;
}

$productos = [];
$sql = "SELECT id, nombre, precio_unitario FROM productos WHERE estado = 'activo'";
$result = mysqli_query($conn, $sql);
while ($row = mysqli_fetch_assoc($result)) {
    $productos[] = $row;
}
?>

<!DOCTYPE html>
<html>

<head>
    <?php include_once $ROOT . '/includes/head.php'; ?>
    <link rel="stylesheet" href="../../css/cotizaciones.css">
</head>

<body>
    <?php
    $headerParams = [
        "titulo" => $TITULO,
        "btn_atras" => "window.history.back()"
    ];
    include_once '../../includes/header.php';
    ?>
    <div class="main-container">
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
                            <button onclick="window.location.href='../clientes/agregar.php'">+ Nuevo</button>
                        </div>
                    </div>
                </div>

                <!-- SECCIÓN TERRENO -->
                <div class="form-section" role="region" aria-labelledby="seccion-terreno">
                    <div class="titulo-formulario" style="margin-top: -7px;">Terreno</div>
                    <div class="form-group">
                        <label for="tipo_terreno">Tipo de Terreno *</label>
                        <select id="tipo_terreno" class="textfield" onchange="toggleTerreno()" required>
                            <option value="">-- Selecciona --</option>
                            <option value="regular">Regular</option>
                            <option value="irregular">Irregular</option>
                        </select>
                    </div>

                    <div id="terreno_regular" class="hidden-section">
                        <div class="form-group">
                            <label for="forma_terreno">Forma *</label>
                            <select id="forma_terreno" class="textfield" onchange="calcularArea()">
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
                </div>
            </div>

            <!-- Columna 2 -->
            <div class="grid-col">
                <!-- SECCIÓN ROLLOS DE PASTO -->
                <div class="form-section" role="region" aria-labelledby="seccion-rollos">
                    <div class="titulo-formulario" style="margin-top: -7px;">Rollos de Pasto</div>
                    <div id="rollos_container">
                        <div class="product-item">
                            <select class="rollo-select textfield" onchange="actualizarRollos(this)">
                                <option value="">-- Selecciona Rollo --</option>
                                <?php
                                $sql_rollos = "SELECT p.id, p.nombre, p.precio_unitario, m.nombre AS modelo, 
                            GROUP_CONCAT(DISTINCT c.nombre SEPARATOR ', ') AS colores
                            FROM productos p
                            JOIN modelos m ON p.id_modelo = m.id
                            JOIN inventario_rollos ir ON p.id = ir.id_producto
                            JOIN colores c ON ir.id_color = c.id
                            WHERE p.tipo_inventario = 'rollo' AND p.estado = 'activo'
                            AND ir.estado = 'disponible'
                            GROUP BY p.id";
                                $result_rollos = mysqli_query($conn, $sql_rollos);
                                while ($rollo = mysqli_fetch_assoc($result_rollos)) : ?>
                                    <option value="<?= $rollo['id'] ?>"
                                        data-precio="<?= $rollo['precio_unitario'] ?>"
                                        data-modelo="<?= $rollo['modelo'] ?>"
                                        data-colores="<?= $rollo['colores'] ?>">
                                        <?= htmlspecialchars($rollo['nombre']) ?> (<?= $rollo['modelo'] ?>)
                                    </option>
                                <?php endwhile; ?>
                            </select>
                            <input type="number" class="textfield" placeholder="m²" min="1" step="0.01" style="width: 80px;" onchange="actualizarRollos(this)">
                            <div class="eliminar">
                                <i class="fa-solid fa-trash" type="button" onclick="removerRollo(this)"></i>
                            </div>

                            <div class="rollo-details" style="display: none; margin-top: 10px; width: 100%;">
                                <div><small>Modelo: <span class="modelo-text"></span></small></div>
                                <div><small>Colores disponibles: <span class="colores-text"></span></small></div>
                                <div><small>Área disponible: <span class="area-text"></span> m²</small></div>
                            </div>
                        </div>
                    </div>
                    <button type="button" onclick="agregarRollo()" style="margin-top: 10px;">+ Agregar rollo</button>
                </div>

                <!-- SECCIÓN PRODUCTOS GENERALES -->
                <div class="form-section" role="region" aria-labelledby="seccion-productos">
                    <div class="titulo-formulario" style="margin-top: -7px;">Otros Productos</div>
                    <div id="productos_container">
                        <div class="product-item">
                            <select class="product-select textfield" onchange="actualizarProductos(this)">
                                <option value="">-- Selecciona Producto --</option>
                                <?php
                                $sql_productos = "SELECT p.id, p.nombre, p.precio_unitario, u.simbolo AS unidad
                                 FROM productos p
                                 JOIN unidades u ON p.id_unidad = u.id
                                 WHERE p.tipo_inventario = 'unidad' AND p.estado = 'activo'";
                                $result_productos = mysqli_query($conn, $sql_productos);
                                while ($producto = mysqli_fetch_assoc($result_productos)) : ?>
                                    <option value="<?= $producto['id'] ?>"
                                        data-precio="<?= $producto['precio_unitario'] ?>"
                                        data-unidad="<?= $producto['unidad'] ?>">
                                        <?= htmlspecialchars($producto['nombre']) ?> (<?= $producto['unidad'] ?>)
                                    </option>
                                <?php endwhile; ?>
                            </select>
                            <input type="number" class="textfield" placeholder="Cantidad" min="1" value="1" style="width: 80px;" onchange="actualizarProductos(this)">
                            <div class="eliminar">
                                <i class="fa-solid fa-trash" type="button" onclick="removerProducto(this)"></i>
                            </div>
                        </div>
                    </div>
                    <button type="button" onclick="agregarProducto()" style="margin-top: 10px;">+ Agregar producto</button>
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
                            </label><br>
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

                    <div class="form-group">
                        <label for="garantia">Garantía (años)</label>
                        <select id="garantia" class="textfield">
                            <option value="3">3</option>
                            <option value="5" selected>5</option>
                            <option value="8">8</option>
                            <option value="10">10</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label>Precio instalación por m2</label>
                        <input type="number" id="precio_instalacion" class="textfield" placeholder="$ por m2" step="0.01" min="0">
                    </div>
                </div>

                <!-- SECCIÓN RESUMEN -->
                <div class="form-section" role="region" aria-labelledby="seccion-resumen">
                    <div class="titulo-formulario" style="margin-top: -7px;">Resumen</div>
                    <div class="form-group">
                        <div class="summary-item">
                            <span>Total sin IVA:</span>
                            <span id="total_sin_iva">$0.00</span>
                        </div>
                        <div class="summary-item">
                            <span>IVA (16%):</span>
                            <span id="iva">$0.00</span>
                        </div>
                        <div class="summary-item" style="font-weight: bold;">
                            <span>Total con IVA:</span>
                            <span id="total_con_iva">$0.00</span>
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
        <button class="btnadd" style="margin: 0;" type="button" onclick="guardarCotizacion()">
            <i class="fas fa-save"></i> Guardar
        </button>
    </div>

    <?php include_once '../../includes/popup.php'; ?>
</body>
<script src="../../scripts/cotizaciones/formas_irregulares.js"></script>
<script src="../../scripts/cotizaciones/nueva_cotizacion.js"></script>

</html>