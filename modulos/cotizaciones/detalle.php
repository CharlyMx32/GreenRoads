<?php
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

$ROOT = '../..';

include_once "$ROOT/db/conexion.php";
include_once "$ROOT/includes/sesion.php";
include_once "$ROOT/includes/config.php";
include_once "$ROOT/includes/funciones_tabuladores.php";

if (!tieneSesion()) {
    header("Location: $URL_ROOT/login");
    exit();
}

$id_cotizacion = (int)($_GET['id'] ?? 0);

if ($id_cotizacion <= 0) {
    header("Location: lista.php");
    exit();
}

// Función para obtener costo base de productos
function obtenerCostoBaseProducto($conn, $id_producto)
{
    $sql = "SELECT costo_base FROM productos WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $id_producto);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    return $row ? floatval($row['costo_base']) : 0;
}

// Obtener datos completos de la cotización
$sql = "
    SELECT 
        c.*,
        cli.nombre AS nombre_cliente,
        cli.telefono,
        cli.email,
        cli.direccion,
        COALESCE(a.nombre, 'Sin asignar') AS nombre_admin,
        COALESCE(a.apellido, '') AS apellido_admin
    FROM cotizaciones c
    LEFT JOIN clientes cli ON c.id_cliente = cli.id
    LEFT JOIN admins a ON c.id_admin = a.id 
    WHERE c.id = ?
";

$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "i", $id_cotizacion);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

if (!$cotizacion = mysqli_fetch_assoc($result)) {
    header("Location: lista.php");
    exit();
}

// Obtener productos de la cotización
$productos_cotizacion = [];
$sql_productos = "
    SELECT 
        p.id as id_producto,
        p.nombre,
        p.costo_base,
        p.tipo_inventario, 
        dc.cantidad,
        dc.area_usada,
        dc.precio_unitario,
        dc.subtotal,
        dc.opcion_comparativa,
        u.simbolo as unidad,
        col.nombre as color,
        col.codigo_hex
    FROM detalle_cotizacion dc
    INNER JOIN productos p ON dc.id_producto = p.id
    LEFT JOIN unidades u ON p.id_unidad = u.id
    LEFT JOIN colores col ON dc.id_color = col.id
    WHERE dc.id_cotizacion = ?
    ORDER BY dc.opcion_comparativa, dc.id
";

$stmt_productos = mysqli_prepare($conn, $sql_productos);
mysqli_stmt_bind_param($stmt_productos, "i", $id_cotizacion);
mysqli_stmt_execute($stmt_productos);
$result_productos = mysqli_stmt_get_result($stmt_productos);

while ($row = mysqli_fetch_assoc($result_productos)) {
    $productos_cotizacion[] = $row;
}

// Obtener extras de la cotización
$extras_cotizacion = [];
$sql_extras_cotizacion = "
    SELECT e.id, e.nombre, e.descripcion, ce.precio_aplicado
    FROM cotizacion_extras ce
    INNER JOIN extras e ON ce.id_extra = e.id
    WHERE ce.id_cotizacion = ?
";
$stmt_extras_cotizacion = mysqli_prepare($conn, $sql_extras_cotizacion);
mysqli_stmt_bind_param($stmt_extras_cotizacion, "i", $id_cotizacion);
mysqli_stmt_execute($stmt_extras_cotizacion);
$result_extras_cotizacion = mysqli_stmt_get_result($stmt_extras_cotizacion);
while ($row = mysqli_fetch_assoc($result_extras_cotizacion)) {
    $extras_cotizacion[] = $row;
}

// Verificar si tiene instalación asociada
$sql_instalacion = "SELECT id, estado, progreso_porcentaje FROM instalaciones WHERE id_cotizacion = ?";
$stmt_instalacion = mysqli_prepare($conn, $sql_instalacion);
mysqli_stmt_bind_param($stmt_instalacion, "i", $id_cotizacion);
mysqli_stmt_execute($stmt_instalacion);
$result_instalacion = mysqli_stmt_get_result($stmt_instalacion);
$instalacion_asociada = mysqli_fetch_assoc($result_instalacion);
?>

<!DOCTYPE html>
<html>

<head>
    <?php include_once "$ROOT/includes/head.php"; ?>
    <link rel="stylesheet" href="../../css/material.css">
    <link rel="stylesheet" href="../../css/cotizaciones/cotizaciones.css">
    <link rel="stylesheet" href="../../css/cotizaciones/detalle.css">
    <link rel="stylesheet" href="../../css/instalaciones/instalaciones.css">
    <link rel="stylesheet" href="../../css/responsive.css">
</head>

<body>
    <?php
    $headerParams = [
        "titulo" => "Cotización #" . $cotizacion['id'],
        "btn_atras" => "window.location.href='lista.php'"
    ];
    include_once '../../includes/header.php';
    ?>

    <div class="content">
        <div class="main-container">

            <div class="instalacion-header">
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 15px; align-items: center;">
                    <div class="info-item-header">
                        <label>Cliente:</label>
                        <span style="font-weight: 600; color: #333;"><?= htmlspecialchars($cotizacion['nombre_cliente']) ?></span>
                    </div>
                    <div class="info-item-header">
                        <label for="estado">Estado</label>
                        <span class="estado-detalle <?= $cotizacion['estado'] ?>">
                            <?= ucfirst(str_replace('_', ' ', $cotizacion['estado'])) ?>
                        </span>
                    </div>
                    <div class="info-item-header">
                        <label>Fecha:</label>
                        <span style="font-weight: 600; color: #333;"><?= date('d/m/Y', strtotime($cotizacion['fecha'])) ?></span>
                    </div>
                    <div class="info-item-header">
                        <label>Área:</label>
                        <span><?= number_format($cotizacion['area_total'] ?? 0, 2) ?> m²</span>
                    </div>
                    <div class="info-item-header">
                        <label>Total:</label>
                        <span style="font-weight: 600; color: #333;">$<?= number_format($cotizacion['total'], 2) ?></span>
                    </div>
                    <div class="info-item-header">
                        <label>Admin:</label>
                        <span><?= htmlspecialchars($cotizacion['nombre_admin'] . ' ' . $cotizacion['apellido_admin']) ?></span>
                    </div>
                    <?php if ($instalacion_asociada): ?>
                        <div class="info-item-header">
                            <label>Instalación:</label>
                            <a href="../instalacion/detalle_instalacion.php?id=<?= $instalacion_asociada['id'] ?>"
                                style="color: #ffffffff; text-decoration: none; font-weight: 600;">
                                #<?= $instalacion_asociada['id'] ?>
                            </a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <div class="card-principal">
                <div class="progreso-header" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 20px; align-items: center; padding: 20px; background: linear-gradient(135deg, #2196F3 0%, #42A5F5 100%); border-radius: 12px; margin-bottom: 25px;">
                    <div class="medida-item" style="text-align: center; background: rgba(255,255,255,0.15); backdrop-filter: blur(10px); border-radius: 10px; padding: 15px;">
                        <div class="medida-valor" style="font-size: 1.8rem; font-weight: 700; color: white; margin-bottom: 5px;"><?= number_format($cotizacion['area_total'] ?? 0, 2) ?> m²</div>
                        <div class="medida-label" style="font-size: 0.9rem; color: rgba(255,255,255,0.9); font-weight: 500;">Área Total</div>
                    </div>
                    <div class="medida-item" style="text-align: center; background: rgba(255,255,255,0.15); backdrop-filter: blur(10px); border-radius: 10px; padding: 15px;">
                        <div class="medida-valor" style="font-size: 1.2rem; font-weight: 600; color: white; margin-bottom: 5px;"><?= htmlspecialchars($cotizacion['tipo_terreno'] ?? 'No especificado') ?></div>
                        <div class="medida-label" style="font-size: 0.9rem; color: rgba(255,255,255,0.9); font-weight: 500;">Tipo de Terreno</div>
                    </div>
                    <div class="medida-item" style="text-align: center; background: rgba(255,255,255,0.15); backdrop-filter: blur(10px); border-radius: 10px; padding: 15px;">
                        <div class="medida-valor" style="font-size: 1.2rem; font-weight: 600; color: white; margin-bottom: 5px;"><?= htmlspecialchars($cotizacion['tipo_instalacion'] ?? 'No especificado') ?></div>
                        <div class="medida-label" style="font-size: 0.9rem; color: rgba(255,255,255,0.9); font-weight: 500;">Tipo de Instalación</div>
                    </div>
                    <div class="medida-item" style="text-align: center; background: rgba(255,255,255,0.15); backdrop-filter: blur(10px); border-radius: 10px; padding: 15px;">
                        <div class="medida-valor" style="font-size: 1.8rem; font-weight: 700; color: white; margin-bottom: 5px;"><?= $cotizacion['garantia_anios'] ?? 0 ?> años</div>
                        <div class="medida-label" style="font-size: 0.9rem; color: rgba(255,255,255,0.9); font-weight: 500;">Garantía</div>
                    </div>
                    <div class="medida-item" style="text-align: center; background: rgba(255,255,255,0.15); backdrop-filter: blur(10px); border-radius: 10px; padding: 15px;">
                        <div class="medida-valor" style="font-size: 1.5rem; font-weight: 700; color: white; margin-bottom: 5px;">$<?= number_format($cotizacion['precio_instalacion_m2'] ?? 0, 2) ?></div>
                        <div class="medida-label" style="font-size: 0.9rem; color: rgba(255,255,255,0.9); font-weight: 500;">Precio/m²</div>
                    </div>
                    <?php if ($cotizacion['dibujo_terreno']): ?>
                        <div class="medida-item" style="text-align: center;">
                            <button class="btn-accion btn-ver" onclick="verDibujoTerreno()" style="background: rgba(255,255,255,0.2); border: 2px solid rgba(255,255,255,0.3); color: white; padding: 12px 20px; border-radius: 10px; font-weight: 600; transition: all 0.3s ease; backdrop-filter: blur(10px);" onmouseover="this.style.background='rgba(255,255,255,0.3)'" onmouseout="this.style.background='rgba(255,255,255,0.2)'">
                                <i class="fa-solid fa-map" style="margin-right: 8px;"></i> Ver Terreno
                            </button>
                        </div>
                    <?php endif; ?>
                </div>

                <div class="form-container-grid">
                    <!-- Columna 1 -->
                    <div class="grid-col">
                        <!-- Información del cliente -->
                        <div class="progreso-container">
                            <div class="progreso-header">
                                <h3 class="progreso-title">Información del Cliente</h3>
                            </div>
                            <div class="instalacion-info">
                                <div class="info-item">
                                    <label>Nombre:</label>
                                    <span><?= htmlspecialchars($cotizacion['nombre_cliente']) ?></span>
                                </div>
                                <div class="info-item">
                                    <label>Teléfono:</label>
                                    <span><?= htmlspecialchars($cotizacion['telefono'] ?: 'No especificado') ?></span>
                                </div>
                                <div class="info-item">
                                    <label>Email:</label>
                                    <span><?= htmlspecialchars($cotizacion['email'] ?: 'No especificado') ?></span>
                                </div>
                                <div class="info-item">
                                    <label>Dirección:</label>
                                    <span><?= htmlspecialchars($cotizacion['direccion'] ?: 'No especificada') ?></span>
                                </div>
                            </div>
                        </div>

                        <!-- Estado de la cotización -->
                        <div class="progreso-container">
                            <div class="progreso-header">
                                <h3 class="progreso-title">Estado de la Cotización</h3>
                            </div>

                            <div class="timeline-instalacion">
                                <div class="timeline-item completado">
                                    <div class="timeline-step-title">Creada</div>
                                    <div class="timeline-step-description">Cotización generada</div>
                                    <div class="timeline-step-date">
                                        <?= date('d/m/Y', strtotime($cotizacion['fecha'])) ?>
                                    </div>
                                </div>

                                <div class="timeline-item <?= in_array($cotizacion['estado'], ['aceptada', 'en_instalacion']) ? 'completado' : ($cotizacion['estado'] == 'pendiente' ? 'en-progreso' : 'pendiente') ?>">
                                    <div class="timeline-step-title">Revisión</div>
                                    <div class="timeline-step-description">
                                        <?php if ($cotizacion['estado'] == 'pendiente'): ?>
                                            En espera de aprobación
                                        <?php elseif ($cotizacion['estado'] == 'aceptada'): ?>
                                            Aprobada por el cliente
                                        <?php elseif ($cotizacion['estado'] == 'rechazada'): ?>
                                            Rechazada
                                        <?php else: ?>
                                            Estado: <?= $cotizacion['estado'] ?>
                                        <?php endif; ?>
                                    </div>
                                    <div class="timeline-step-date">
                                        <?= $cotizacion['fecha_actualizacion'] ? date('d/m/Y', strtotime($cotizacion['fecha_actualizacion'])) : 'Pendiente' ?>
                                    </div>
                                </div>

                                <?php if ($instalacion_asociada): ?>
                                    <div class="timeline-item <?= $instalacion_asociada['estado'] == 'completada' ? 'completado' : 'en-progreso' ?>">
                                        <div class="timeline-step-title">Instalación</div>
                                        <div class="timeline-step-description">
                                            <?= ucfirst(str_replace('_', ' ', $instalacion_asociada['estado'])) ?>
                                            <?php if ($instalacion_asociada['progreso_porcentaje'] > 0): ?>
                                                - <?= number_format($instalacion_asociada['progreso_porcentaje'], 1) ?>%
                                            <?php endif; ?>
                                        </div>
                                        <div class="timeline-step-date">
                                            <a href="../instalacion/detalle_instalacion.php?id=<?= $instalacion_asociada['id'] ?>"
                                                style="color: inherit; text-decoration: none;">
                                                Ver instalación
                                            </a>
                                        </div>
                                    </div>
                                <?php else: ?>
                                    <div class="timeline-item pendiente">
                                        <div class="timeline-step-title">Instalación</div>
                                        <div class="timeline-step-description">Sin instalación programada</div>
                                        <div class="timeline-step-date">Pendiente</div>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <!-- Columna 2 -->
                    <div class="grid-col">
                        <!-- Productos cotizados -->
                        <div class="progreso-container">
                            <div class="progreso-header">
                                <h3 class="progreso-title">Productos Cotizados</h3>
                            </div>

                            <div class="materiales-lista">
                                <?php if (empty($productos_cotizacion)): ?>
                                    <div class="estado-vacio">
                                        <i class="fa-solid fa-boxes-stacked"></i>
                                        <p>No hay productos en la cotización</p>
                                    </div>
                                <?php else: ?>
                                    <?php foreach ($productos_cotizacion as $producto): ?>
                                        <div class="material-card">
                                            <div class="material-nombre">
                                                <?= htmlspecialchars($producto['nombre']) ?>
                                                <?php if ($producto['color']): ?>
                                                    <span style="font-size: 0.8rem; color: #666;">
                                                        - <span class="color-indicator" style="background-color: <?= $producto['codigo_hex'] ?: '#ccc' ?>; display: inline-block; width: 12px; height: 12px; border-radius: 2px; margin-right: 4px;"></span>
                                                        <?= htmlspecialchars($producto['color']) ?>
                                                    </span>
                                                <?php endif; ?>
                                            </div>
                                            <div class="material-cantidad">
                                                <span class="cantidad-necesaria">Cantidad: <?= $producto['cantidad'] ?> <?= $producto['unidad'] ?></span>
                                                <?php if ($producto['area_usada']): ?>
                                                    <span class="cantidad-usada">Área: <?= $producto['area_usada'] ?> m²</span>
                                                <?php endif; ?>
                                                <span class="cantidad-precio">
                                                    $<?= number_format($producto['costo_base'], 2) ?> / <?= $producto['unidad'] ?>
                                                    = <strong>$<?= number_format($producto['subtotal'], 2) ?></strong>
                                                </span>
                                            </div>
                                            <div class="material-progreso">
                                                <div class="material-progreso-bar" style="width: 100%; background: #2196F3;"></div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                        </div>
                        <!-- Extras de la cotización -->
                        <div class="progreso-container">
                            <h3 class="progreso-title">Extras de la Cotización</h3>
                            <div class="materiales-lista">
                                <?php if (empty($extras_cotizacion)): ?>
                                    <div class="estado-vacio">
                                        <p>No hay extras en la cotización</p>
                                    </div>
                                <?php else: ?>
                                    <?php foreach ($extras_cotizacion as $extra): ?>
                                        <div class="material-card">
                                            <div class="material-nombre"><?= htmlspecialchars($extra['nombre']) ?></div>
                                            <div class="material-cantidad">
                                                <span class="cantidad-necesaria">Precio: $<?= number_format($extra['precio_aplicado'], 2) ?></span>
                                            </div>
                                            <?php if ($extra['descripcion']): ?>
                                                <div style="font-size: 0.8rem; color: #666; margin-top: 5px;">
                                                    <?= htmlspecialchars($extra['descripcion']) ?>
                                                </div>
                                            <?php endif; ?>
                                            <div class="material-progreso">
                                                <div class="material-progreso-bar" style="width: 100%; background: #ff9800;"></div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                        </div>

                    </div>

                    <!-- Columna 3 -->
                    <div class="grid-col">
                        <!-- Resumen de precios -->
                        <?php if ($cotizacion['es_comparativa'] != 1 && $cotizacion['es_comparativa'] != 'S'): ?>
                            <div class="progreso-container" style="background: #f8f9fa; border: 2px solid #4CAF50;">
                                <div class="progreso-header">
                                    <h3 class="progreso-title" style="color: #4CAF50;">Resumen de Precios</h3>
                                </div>

                                <div style="padding: 15px;">
                                    <?php
                                    // Recalcular correctamente usando tabuladores y descuentos
                                    $area_total = $cotizacion['area_total'];

                                    // 1. Calcular productos (rollos con descuento aplicado)
                                    $subtotal_productos_sin_descuento = 0;
                                    foreach ($productos_cotizacion as $producto) {
                                        if ($producto['tipo_inventario'] === 'rollo') {
                                            // Para rollos: área × costo_base del producto
                                            $costo_base = obtenerCostoBaseProducto($conn, $producto['id_producto']);
                                            $subtotal_productos_sin_descuento += $area_total * $costo_base;
                                        } else {
                                            // Para otros productos usar subtotal original
                                            $subtotal_productos_sin_descuento += $producto['subtotal'];
                                        }
                                    }

                                    // 2. Aplicar descuento por volumen solo a rollos
                                    $descuento_porcentaje = obtenerDescuentoVolumenPorcentaje($conn, $area_total);
                                    $descuento_monto = $subtotal_productos_sin_descuento * ($descuento_porcentaje / 100);
                                    $subtotal_productos = $subtotal_productos_sin_descuento - $descuento_monto;

                                    // 3. Extras (sin descuento)
                                    $subtotal_extras = array_sum(array_column($extras_cotizacion, 'precio_aplicado'));

                                    // 4. Instalación
                                    $precio_instalacion_m2 = obtenerPrecioInstalacionM2($conn, $area_total);
                                    $costo_instalacion = $area_total * $precio_instalacion_m2;

                                    // 5. Mano de obra
                                    $precio_mano_obra_m2 = obtenerPrecioManoObraM2($conn, $area_total);
                                    $costo_mano_obra = $area_total * $precio_mano_obra_m2;

                                    // 6. Subtotal antes de IVA
                                    $subtotal_sin_iva = $subtotal_productos + $subtotal_extras + $costo_instalacion + $costo_mano_obra;

                                    // 7. IVA
                                    // Verificar si la cotización original tenía IVA
                                    $aplicaba_iva_original = ($cotizacion['iva'] > 0);
                                    
                                    if ($aplicaba_iva_original) {
                                        $iva_monto = obtenerIVA($conn, $subtotal_sin_iva);
                                    } else {
                                        $iva_monto = 0;
                                    }
                                    
                                    $total_con_iva = $subtotal_sin_iva + $iva_monto;
                                    ?>

                                    <div style="display: flex; justify-content: space-between; margin-bottom: 8px;">
                                        <span>Productos:</span>
                                        <span>$<?= number_format($subtotal_productos, 2) ?></span>
                                    </div>

                                    <?php if ($subtotal_extras > 0): ?>
                                        <div style="display: flex; justify-content: space-between; margin-bottom: 8px;">
                                            <span>Extras:</span>
                                            <span>$<?= number_format($subtotal_extras, 2) ?></span>
                                        </div>
                                    <?php endif; ?>

                                    <div style="display: flex; justify-content: space-between; margin-bottom: 8px;">
                                        <span>Instalación:</span>
                                        <span>$<?= number_format($costo_instalacion, 2) ?></span>
                                    </div>

                                    <div style="display: flex; justify-content: space-between; margin-bottom: 8px;">
                                        <span>Mano de obra:</span>
                                        <span>$<?= number_format($costo_mano_obra, 2) ?></span>
                                    </div>

                                    <?php if ($iva_monto > 0): ?>
                                        <div style="display: flex; justify-content: space-between; margin-bottom: 8px;">
                                            <span>Subtotal:</span>
                                            <span>$<?= number_format($subtotal_sin_iva, 2) ?></span>
                                        </div>

                                        <div style="display: flex; justify-content: space-between; margin-bottom: 8px;">
                                            <span>IVA:</span>
                                            <span>$<?= number_format($iva_monto, 2) ?></span>
                                        </div>
                                    <?php endif; ?>

                                    <hr style="border: 1px solid #4CAF50; margin: 10px 0;">

                                    <div style="display: flex; justify-content: space-between; font-weight: 600; font-size: 1.1em; color: #4CAF50;">
                                        <span>Total<?= $iva_monto > 0 ? ' (con IVA)' : '' ?>:</span>
                                        <span>$<?= number_format($total_con_iva, 2) ?></span>
                                    </div>
                                </div>
                            </div>
                        <?php else: ?>
                            <!-- Comparación de Opciones Integrada -->
                            <div class="progreso-container">
                                <div class="progreso-header">
                                    <?php if ($cotizacion['estado'] == 'aceptada'): ?>
                                        <h3 class="progreso-title">Opción Aceptada</h3>
                                    <?php else: ?>
                                        <h3 class="progreso-title">Comparación de Opciones</h3>
                                    <?php endif; ?>
                                </div>

                                <div class="materiales-lista">
                                    <?php
                                    // Agrupar productos por opción
                                    $productos_opcion_a = [];
                                    $productos_opcion_b = [];

                                    foreach ($productos_cotizacion as $index => $producto) {
                                        // Verificar diferentes formatos de opcion_comparativa
                                        $opcion = $producto['opcion_comparativa'];

                                        // Normalizar opciones: 1/A = Opción A, 2/B = Opción B
                                        if ($opcion == 'A' || $opcion == '1' || $opcion == 1) {
                                            $productos_opcion_a[] = $producto;
                                        } elseif ($opcion == 'B' || $opcion == '2' || $opcion == 2) {
                                            $productos_opcion_b[] = $producto;
                                        } else {
                                            // Si no tiene opción asignada, intentar agrupar por posición
                                            $index_fallback = count($productos_opcion_a) + count($productos_opcion_b);
                                            if ($index_fallback % 2 == 0) {
                                                $productos_opcion_a[] = $producto;
                                            } else {
                                                $productos_opcion_b[] = $producto;
                                            }
                                        }
                                    }

                                    // Recalcular correctamente las opciones comparativas
                                    $area_total = $cotizacion['area_total'];

                                    // Calcular rollos con descuentos para cada opción
                                    $subtotal_productos_a_sin_descuento = 0;
                                    $subtotal_productos_b_sin_descuento = 0;

                                    $subtotal_productos_sin_descuento = 0;
                                    foreach ($productos_opcion_a as $producto) {
                                        if ($producto['tipo_inventario'] === 'rollo') {
                                            // Use costo_base directly from query results
                                            $subtotal_productos_a_sin_descuento += $area_total * $producto['costo_base'];
                                        } else {
                                            $subtotal_productos_a_sin_descuento += $producto['subtotal'];
                                        }
                                    }

                                    foreach ($productos_opcion_b as $producto) {
                                        if ($producto['tipo_inventario'] === 'rollo') {
                                            // Use costo_base directly from query results
                                            $subtotal_productos_b_sin_descuento += $area_total * $producto['costo_base'];
                                        } else {
                                            $subtotal_productos_b_sin_descuento += $producto['subtotal'];
                                        }
                                    }

                                    // Aplicar descuento por volumen
                                    $descuento_porcentaje = obtenerDescuentoVolumenPorcentaje($conn, $area_total);
                                    $descuento_monto_a = $subtotal_productos_a_sin_descuento * ($descuento_porcentaje / 100);
                                    $descuento_monto_b = $subtotal_productos_b_sin_descuento * ($descuento_porcentaje / 100);

                                    $subtotal_productos_a = $subtotal_productos_a_sin_descuento - $descuento_monto_a;
                                    $subtotal_productos_b = $subtotal_productos_b_sin_descuento - $descuento_monto_b;

                                    // Costos compartidos
                                    $subtotal_extras = array_sum(array_column($extras_cotizacion, 'precio_aplicado'));
                                    $precio_instalacion_m2 = obtenerPrecioInstalacionM2($conn, $area_total);
                                    $costo_instalacion = $area_total * $precio_instalacion_m2;
                                    $precio_mano_obra_m2 = obtenerPrecioManoObraM2($conn, $area_total);
                                    $costo_mano_obra = $area_total * $precio_mano_obra_m2;

                                    // Subtotales antes de IVA
                                    $subtotal_sin_iva_a = $subtotal_productos_a + $subtotal_extras + $costo_instalacion + $costo_mano_obra;
                                    $subtotal_sin_iva_b = $subtotal_productos_b + $subtotal_extras + $costo_instalacion + $costo_mano_obra;

                                    // Verificar si la cotización original tenía IVA
                                    $aplicaba_iva_original = ($cotizacion['iva'] > 0);
                                    
                                    // IVA y totales finales
                                    if ($aplicaba_iva_original) {
                                        $iva_monto_a = obtenerIVA($conn, $subtotal_sin_iva_a);
                                        $iva_monto_b = obtenerIVA($conn, $subtotal_sin_iva_b);
                                    } else {
                                        $iva_monto_a = 0;
                                        $iva_monto_b = 0;
                                    }

                                    $total_opcion_a = $subtotal_sin_iva_a + $iva_monto_a;
                                    $total_opcion_b = $subtotal_sin_iva_b + $iva_monto_b;

                                    // Determinar cuál es la mejor opción (más económica)
                                    $mejor_opcion = ($total_opcion_a <= $total_opcion_b) ? 'A' : 'B';
                                    ?>

                                    <div class="materiales-lista">
                                        <?php if ($cotizacion['estado'] === 'aceptada'): ?>
                                            <!-- Mostrar solo la opción aceptada -->
                                            <?php
                                            $opcion_aceptada = $cotizacion['opcion_seleccionada'];
                                            
                                            // Normalizar opción aceptada: 1/A = Opción A, 2/B = Opción B
                                            $es_opcion_a = ($opcion_aceptada === 'A' || $opcion_aceptada === '1' || $opcion_aceptada === 1);
                                            
                                            $productos_aceptada = $es_opcion_a ? $productos_opcion_a : $productos_opcion_b;
                                            $total_aceptada = $es_opcion_a ? $total_opcion_a : $total_opcion_b;
                                            $subtotal_aceptada = $es_opcion_a ? $subtotal_productos_a : $subtotal_productos_b;
                                            
                                            // Determinar etiqueta para mostrar (siempre mostrar A/B en interfaz)
                                            $etiqueta_opcion = $es_opcion_a ? 'A' : 'B';
                                            ?>

                                            <div class="opcion-card opcion-aceptada" id="opcion-aceptada">
                                                <div class="opcion-header">
                                                    <h4 class="opcion-titulo titulo-aceptada">
                                                        <i class="fa-solid fa-check-circle text-success"></i>
                                                        Opción <?= strtoupper($etiqueta_opcion) ?> - Aceptada
                                                    </h4>
                                                    <div class="precio-badge badge-aceptada">
                                                        $<?= number_format($total_aceptada, 2) ?>
                                                    </div>
                                                </div>

                                                <div class="productos-lista">
                                                    <h5 class="productos-titulo">
                                                        <i class="fa-solid fa-boxes-stacked"></i>
                                                        Rollo :
                                                    </h5>
                                                    <?php foreach ($productos_aceptada as $producto): ?>
                                                        <div class="producto-item">
                                                            <span class="producto-nombre">
                                                                <?= htmlspecialchars($producto['nombre']) ?>
                                                                <?php if ($producto['color']): ?>
                                                                    <small style="color: #666;">- <?= htmlspecialchars($producto['color']) ?></small>
                                                                <?php endif; ?>
                                                            </span>
                                                            <span class="producto-precio">$<?= number_format($producto['subtotal'], 2) ?></span>
                                                        </div>
                                                    <?php endforeach; ?>

                                                    <hr class="subtotal-divider">
                                                    <div class="subtotal-row subtotal-aceptada">
                                                        <span>Subtotal productos:</span>
                                                        <span>$<?= number_format($subtotal_aceptada, 2) ?></span>
                                                    </div>
                                                </div>
                                            </div>
                                        <?php else: ?>
                                            <!-- Mostrar ambas opciones para comparación -->
                                            <!-- Opción A -->
                                            <div class="opcion-card opcion-a" id="opcion-a">
                                                <div class="opcion-header">
                                                    <h4 class="opcion-titulo titulo-a">
                                                        <i class="fa-solid fa-circle-1"></i>
                                                        Opción A
                                                    </h4>
                                                    <div class="precio-badge badge-a">
                                                        $<?= number_format($total_opcion_a, 2) ?>
                                                    </div>
                                                </div>

                                                <div class="productos-lista">
                                                    <h5 class="productos-titulo">
                                                        <i class="fa-solid fa-boxes-stacked"></i>
                                                        Rollo :
                                                    </h5>
                                                    <?php foreach ($productos_opcion_a as $producto): ?>
                                                        <div class="producto-item">
                                                            <span class="producto-nombre">
                                                                <?= htmlspecialchars($producto['nombre']) ?>
                                                                <?php if ($producto['color']): ?>
                                                                    <small style="color: #666;">- <?= htmlspecialchars($producto['color']) ?></small>
                                                                <?php endif; ?>
                                                            </span>
                                                            <span class="producto-precio">$<?= number_format($producto['subtotal'], 2) ?></span>
                                                        </div>
                                                    <?php endforeach; ?>

                                                    <hr class="subtotal-divider">
                                                    <div class="subtotal-row subtotal-a">
                                                        <span>Subtotal productos:</span>
                                                        <span>$<?= number_format($subtotal_productos_a, 2) ?></span>
                                                    </div>
                                                </div>
                                            </div>

                                            <!-- Opción B -->
                                            <div class="opcion-card opcion-b" id="opcion-b">
                                                <div class="opcion-header">
                                                    <h4 class="opcion-titulo titulo-b">
                                                        <i class="fa-solid fa-circle-2"></i>
                                                        Opción B
                                                    </h4>
                                                    <div class="precio-badge badge-b">
                                                        $<?= number_format($total_opcion_b, 2) ?>
                                                    </div>
                                                </div>

                                                <div class="productos-lista">
                                                    <h5 class="productos-titulo">
                                                        <i class="fa-solid fa-boxes-stacked"></i>
                                                        Rollo :
                                                    </h5>
                                                    <?php foreach ($productos_opcion_b as $producto): ?>
                                                        <div class="producto-item">
                                                            <span class="producto-nombre">
                                                                <?= htmlspecialchars($producto['nombre']) ?>
                                                                <?php if ($producto['color']): ?>
                                                                    <small style="color: #666;">- <?= htmlspecialchars($producto['color']) ?></small>
                                                                <?php endif; ?>
                                                            </span>
                                                            <span class="producto-precio">$<?= number_format($producto['subtotal'], 2) ?></span>
                                                        </div>
                                                    <?php endforeach; ?>

                                                    <hr class="subtotal-divider">
                                                    <div class="subtotal-row subtotal-b">
                                                        <span>Subtotal productos:</span>
                                                        <span>$<?= number_format($subtotal_productos_b, 2) ?></span>
                                                    </div>
                                                </div>
                                            </div>
                                        <?php endif; ?>
                                    </div>

                                    <!-- Costos compartidos -->
                                    <div class="costos-compartidos">
                                        <h5 class="costos-titulo">
                                            <i class="fa-solid fa-plus-circle"></i>
                                            Costos adicionales (aplicados a ambas opciones):
                                        </h5>

                                        <?php if ($subtotal_extras > 0): ?>
                                            <div class="costo-item">
                                                <span>Extras:</span>
                                                <span>$<?= number_format($subtotal_extras, 2) ?></span>
                                            </div>
                                        <?php endif; ?>

                                        <div class="costo-item">
                                            <span>Instalación:</span>
                                            <span>$<?= number_format($costo_instalacion, 2) ?></span>
                                        </div>

                                        <div class="costo-item">
                                            <span>Mano de obra:</span>
                                            <span>$<?= number_format($costo_mano_obra, 2) ?></span>
                                        </div>

                                        <?php if ($iva_monto_a > 0 || $iva_monto_b > 0): ?>
                                            <div class="costo-item">
                                                <span>IVA:</span>
                                                <span>Opción A: $<?= number_format($iva_monto_a, 2) ?> | Opción B: $<?= number_format($iva_monto_b, 2) ?></span>
                                            </div>
                                        <?php endif; ?>

                                        <hr class="subtotal-divider">

                                        <div class="resumen-comparacion">
                                            <div class="resumen-opcion">
                                                <strong>Opción A<?= $iva_monto_a > 0 ? ' (con IVA)' : '' ?>: $<?= number_format($total_opcion_a, 2) ?></strong>
                                            </div>

                                            <div class="resumen-opcion" style="margin-top: 15px;">
                                                <strong>Opción B<?= $iva_monto_b > 0 ? ' (con IVA)' : '' ?>: $<?= number_format($total_opcion_b, 2) ?></strong>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Selección de opción -->
                                    <div class="seleccion-container">
                                        <?php if ($cotizacion['estado'] === 'aceptada'): ?>
                                            <h3 class="seleccion-titulo" style="margin-top:-5px;">
                                                <i class="fa-solid fa-check-circle text-success"></i>
                                                Opción <?= (($cotizacion['opcion_seleccionada'] === 'A' || $cotizacion['opcion_seleccionada'] === '1' || $cotizacion['opcion_seleccionada'] === 1) ? 'A' : 'B') ?> Aceptada
                                            </h3>

                                            <div class="opcion-aceptada-info">
                                                <p class="text-success">
                                                    <i class="fa-solid fa-calendar-check"></i>
                                                    Total aceptado: $<?= number_format(($cotizacion['opcion_seleccionada'] === 'A' || $cotizacion['opcion_seleccionada'] === '1' || $cotizacion['opcion_seleccionada'] === 1) ? $total_opcion_a : $total_opcion_b, 2) ?>
                                                </p>
                                            </div>
                                        <?php else: ?>
                                            <h3 class="seleccion-titulo" style="margin-top:-5px;">
                                                <i class="fa-solid fa-hand-pointer"></i>
                                                Seleccione una opción
                                            </h3>

                                            <div class="botones-seleccion">
                                                <button class="btn-seleccionar btn-opcion-a" onclick="seleccionarOpcion('A', <?= $total_opcion_a ?>)">
                                                    <i class="fa-solid fa-check-circle"></i>
                                                    Aceptar Opción A - $<?= number_format($total_opcion_a, 2) ?>
                                                </button>

                                                <button class="btn-seleccionar btn-opcion-b" onclick="seleccionarOpcion('B', <?= $total_opcion_b ?>)">
                                                    <i class="fa-solid fa-check-circle"></i>
                                                    Aceptar Opción B - $<?= number_format($total_opcion_b, 2) ?>
                                                </button>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Acciones -->
        <div class="actions-container">
            <?php if ($cotizacion['estado'] == 'pendiente'): ?>
                <?php if ($cotizacion['es_comparativa'] != 1 && $cotizacion['es_comparativa'] != 'S'): ?>
                    <button class="btn-accion btn-completar" onclick="cambiarEstadoCotizacion(<?= $cotizacion['id'] ?>, 'aceptada')">
                        <i class="fa-solid fa-check"></i> Aceptar Cotización
                    </button>
                <?php endif; ?>

                <button class="btn-accion btn-cancelar" onclick="cambiarEstadoCotizacion(<?= $cotizacion['id'] ?>, 'rechazada')">
                    <i class="fa-solid fa-times"></i> Rechazar Cotización
                </button>

                <a href="editar_cotizacion.php?id=<?= $cotizacion['id'] ?>" class="btn-accion btn-actualizar">
                    <i class="fa-solid fa-edit"></i> Editar Cotización
                </a>
            <?php endif; ?>

            <?php if ($cotizacion['estado'] == 'aceptada' && !$instalacion_asociada): ?>
                <a href="../instalacion/crear_instalacion.php?id_cotizacion=<?= $cotizacion['id'] ?>" class="btn-accion btn-completar">
                    <i class="fa-solid fa-hammer"></i> Crear Instalación
                </a>
            <?php endif; ?>

            <button class="btn-accion btn-actualizar" onclick="location.href='lista.php'">
                <i class="fa-solid fa-list"></i> Volver a Lista
            </button>
        </div>

    </div>

    <!-- Modal solo para ver dibujo (sin edición) -->
    <div id="modalVerDibujo" class="modal-edicion" style="display: none;">
        <div class="modal-content-edicion" style="max-width: 800px;">
            <div class="modal-header">
                <h3>Dibujo del Terreno</h3>
                <span class="modal-close" onclick="cerrarModalVerDibujo()">&times;</span>
            </div>

            <div style="padding: 20px; text-align: center;">
                <canvas id="canvasVisualizacion" width="600" height="400"
                    style="border: 2px solid #ddd; border-radius: 8px; background: white;"></canvas>
            </div>
        </div>
    </div>

    <?php include_once '../../includes/popup.php'; ?>

    <script src="../../scripts/cotizaciones/detalle/comparacion.js"></script>

    <script src="../../scripts/cotizaciones/detalle/dibujo.js"></script>
    <script src="../../scripts/cotizaciones/detalle/estado.js"></script>
    <script src="../../scripts/cotizaciones/detalle/eventos.js"></script>

    <script>

        window.cotizacionData = <?= json_encode($cotizacion) ?>;
        window.productosData = <?= json_encode($productos_cotizacion) ?>;

        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                cerrarModalVerDibujo();
            }
        });

        window.onclick = function(event) {
            const modalVerDibujo = document.getElementById('modalVerDibujo');

            if (event.target === modalVerDibujo) {
                cerrarModalVerDibujo();
            }
        };
    </script>
</body>

</html>