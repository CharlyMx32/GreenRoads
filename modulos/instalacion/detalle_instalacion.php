<?php
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

$ROOT = '../..';
$TITULO = "Detalle de Instalación";

include_once "$ROOT/db/conexion.php";
include_once "$ROOT/includes/sesion.php";
include_once "$ROOT/includes/config.php";

if (!tieneSesion()) {
    header("Location: $URL_ROOT/login");
    exit();
}

$id_instalacion = (int)($_GET['id'] ?? 0);

if ($id_instalacion <= 0) {
    header("Location: lista.php");
    exit();
}

// Obtener datos completos de la instalación
$sql = "
    SELECT 
        i.*,
        c.total as precio_total,
        c.tipo_terreno,
        c.tipo_instalacion,
        c.garantia_anios,
        c.area_total as area_cotizacion,
        c.dibujo_terreno as dibujo_cotizacion,
        c.fecha as fecha_cotizacion,
        c.direccion as direccion_cotizacion,
        cli.nombre AS nombre_cliente,
        cli.telefono,
        cli.email,
        cli.direccion as direccion_cliente,
        COALESCE(a.nombre, 'Sin asignar') AS nombre_admin,
        COALESCE(a.apellido, '') AS apellido_admin
    FROM instalaciones i
    INNER JOIN cotizaciones c ON i.id_cotizacion = c.id
    LEFT JOIN clientes cli ON c.id_cliente = cli.id
    LEFT JOIN admins a ON c.id_admin = a.id 
    WHERE i.id = ?
";

$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "i", $id_instalacion);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

if (!$instalacion = mysqli_fetch_assoc($result)) {
    header("Location: lista.php");
    exit();
}

// Obtener materiales de la cotización 
$materiales_cotizacion = [];
$sql_materiales = "
    SELECT 
        p.nombre,
        dc.cantidad,
        dc.area_usada,
        u.simbolo as unidad,
        col.nombre as color
    FROM detalle_cotizacion dc
    INNER JOIN productos p ON dc.id_producto = p.id
    LEFT JOIN unidades u ON p.id_unidad = u.id
    LEFT JOIN colores col ON dc.id_color = col.id
    WHERE dc.id_cotizacion = ?
";

$stmt_materiales = mysqli_prepare($conn, $sql_materiales);
mysqli_stmt_bind_param($stmt_materiales, "i", $instalacion['id_cotizacion']);
mysqli_stmt_execute($stmt_materiales);
$result_materiales = mysqli_stmt_get_result($stmt_materiales);

while ($row = mysqli_fetch_assoc($result_materiales)) {
    $materiales_cotizacion[] = $row;
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
mysqli_stmt_bind_param($stmt_extras_cotizacion, "i", $instalacion['id_cotizacion']);
mysqli_stmt_execute($stmt_extras_cotizacion);
$result_extras_cotizacion = mysqli_stmt_get_result($stmt_extras_cotizacion);
while ($row = mysqli_fetch_assoc($result_extras_cotizacion)) {
    $extras_cotizacion[] = $row;
}

// Obtener rollos utilizados en la instalación
$rollos_utilizados = [];
$sql_rollos_utilizados = "
    SELECT 
        ri.*,
        p.nombre as producto_nombre,
        col.nombre as color_nombre,
        col.codigo_hex as color_hex,
        ir.largo_metros as largo_original,
        ir.ancho_metros as ancho_original,
        ir.costo_unitario,
        ir.id_lote
    FROM rollos_instalacion ri
    INNER JOIN productos p ON ri.id_producto = p.id
    INNER JOIN colores col ON ri.id_color = col.id
    INNER JOIN inventario_rollos ir ON ri.id_rollo = ir.id
    WHERE ri.id_instalacion = ?
    ORDER BY ri.fecha_asignacion ASC
";
$stmt_rollos = mysqli_prepare($conn, $sql_rollos_utilizados);
mysqli_stmt_bind_param($stmt_rollos, "i", $id_instalacion);
mysqli_stmt_execute($stmt_rollos);
$result_rollos = mysqli_stmt_get_result($stmt_rollos);
while ($row = mysqli_fetch_assoc($result_rollos)) {
    $rollos_utilizados[] = $row;
}

$extras_adicionales = json_decode($instalacion['extras_adicionales'] ?? '[]', true);
?>

<!DOCTYPE html>
<html>

<head>
    <?php include_once "$ROOT/includes/head.php"; ?>
    <link rel="stylesheet" href="../../css/material.css">
    <link rel="stylesheet" href="../../css/cotizaciones/cotizaciones.css">
    <link rel="stylesheet" href="../../css/instalaciones/instalaciones.css">
    <link rel="stylesheet" href="../../css/responsive.css">
</head>

<body>
    <?php
    $headerParams = [
        "titulo" => "Instalación #" . $instalacion['id'],
        "btn_atras" => "window.location.href='lista.php'"
    ];
    include_once '../../includes/header.php';
    ?>

    <div class="content">
        <div class="main-container">

            <div class="instalacion-header">

                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 15px; align-items: center;">
                    <div class="info-item-header">
                        <span class="estado-badge estado-<?= $instalacion['estado'] ?>">
                            <?= ucfirst(str_replace('_', ' ', $instalacion['estado'])) ?>
                        </span>
                    </div>
                    <div class="info-item-header">
                        Cliente: <strong><?= htmlspecialchars($instalacion['nombre_cliente']) ?></strong>
                    </div>
                    <div class="info-item-header">
                        <label>Progreso:</label>
                        <span style="font-weight: 600; color: #333;"><?= number_format($instalacion['progreso_porcentaje'], 1) ?>%</span>
                    </div>
                    <div class="info-item-header">
                        <label>Área:</label>
                        <span><?= number_format($instalacion['area_cotizacion'] ?? 0, 2) ?> m²</span>
                    </div>
                    <div class="info-item-header">
                        <label>Total:</label>
                        <span style="font-weight: 600; color: #333;">$<?= number_format($instalacion['precio_total'], 2) ?></span>
                    </div>
                    <div class="info-item-header">
                        <label>Técnico:</label>
                        <span><?= htmlspecialchars($instalacion['tecnico_responsable'] ?: 'Sin asignar') ?></span>
                    </div>
                    <div class="info-item-header">
                        <label>Cotización:</label>
                        <span>#<?= $instalacion['id_cotizacion'] ?></span>
                    </div>
                </div>
            </div>

            <div class="card-principal">
                <div class="progreso-header" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 20px; align-items: center; padding: 20px; background: linear-gradient(135deg, #4CAF50 0%, #66BB6A 100%); border-radius: 12px;">
                    <div class="medida-item" style="text-align: center; background: rgba(255,255,255,0.15); backdrop-filter: blur(10px); border-radius: 10px; padding: 10px;">
                        <div class="medida-valor" style="font-size: 1rem; font-weight: 700; color: white; margin-bottom: 5px;"><?= number_format($instalacion['area_cotizacion'] ?? 0, 2) ?> m²</div>
                        <div class="medida-label" style="font-size: 0.9rem; color: rgba(255,255,255,0.9); font-weight: 500;">Área Total</div>
                    </div>
                    <div class="medida-item" style="text-align: center; background: rgba(255,255,255,0.15); backdrop-filter: blur(10px); border-radius: 10px; padding: 10px;">
                        <div class="medida-valor" style="font-size: 1rem; font-weight: 600; color: white; margin-bottom: 5px;"><?= htmlspecialchars($instalacion['tipo_terreno'] ?? 'No especificado') ?></div>
                        <div class="medida-label" style="font-size: 0.9rem; color: rgba(255,255,255,0.9); font-weight: 500;">Tipo de Terreno</div>
                    </div>
                    <div class="medida-item" style="text-align: center; background: rgba(255,255,255,0.15); backdrop-filter: blur(10px); border-radius: 10px; padding: 10px;">
                        <div class="medida-valor" style="font-size: 1rem; font-weight: 600; color: white; margin-bottom: 5px;"><?= htmlspecialchars($instalacion['tipo_instalacion'] ?? 'No especificado') ?></div>
                        <div class="medida-label" style="font-size: 0.9rem; color: rgba(255,255,255,0.9); font-weight: 500;">Tipo de Instalación</div>
                    </div>
                    <div class="medida-item" style="text-align: center; background: rgba(255,255,255,0.15); backdrop-filter: blur(10px); border-radius: 10px; padding: 10px;">
                        <div class="medida-valor" style="font-size: 1rem; font-weight: 700; color: white; margin-bottom: 5px;"><?= $instalacion['garantia_anios'] ?? 0 ?> años</div>
                        <div class="medida-label" style="font-size: 0.9rem; color: rgba(255,255,255,0.9); font-weight: 500;">Garantía</div>
                    </div>
                    <?php if ($instalacion['dibujo_cotizacion']): ?>
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
                                    <span><?= htmlspecialchars($instalacion['nombre_cliente']) ?></span>
                                </div>
                                <div class="info-item">
                                    <label>Teléfono:</label>
                                    <span><?= htmlspecialchars($instalacion['telefono'] ?: 'No especificado') ?></span>
                                </div>
                                <div class="info-item">
                                    <label>Email:</label>
                                    <span><?= htmlspecialchars($instalacion['email'] ?: 'No especificado') ?></span>
                                </div>
                                <div class="info-item">
                                    <label>Dirección de Instalación:</label>
                                    <span><?= htmlspecialchars($instalacion['direccion_cotizacion'] ?: 'No especificada') ?></span>
                                </div>
                            </div>
                        </div>

                        <!-- Timeline de progreso con botón de progreso -->
                        <div class="progreso-container">
                            <div class="progreso-header">
                                <h3 class="progreso-title">Cronograma</h3>
                                <button class="btn-accion btn-actualizar" onclick="abrirModalProgreso()">
                                    <i class="fa-solid fa-clock"></i> Actualizar Progreso
                                </button>
                            </div>

                            <div class="timeline-instalacion">
                                <div class="timeline-item <?= $instalacion['estado'] == 'planificada' ? 'en-progreso' : 'completado' ?>">
                                    <div class="timeline-step-title">Planificación</div>
                                    <div class="timeline-step-description">Instalación programada</div>
                                    <div class="timeline-step-date">
                                        <?= $instalacion['fecha_creacion'] ? date('d/m/Y', strtotime($instalacion['fecha_creacion'])) : 'Sin fecha' ?>
                                    </div>
                                </div>

                                <div class="timeline-item <?= in_array($instalacion['estado'], ['en_progreso', 'completada']) ? 'completado' : 'pendiente' ?>">
                                    <div class="timeline-step-title">Inicio</div>
                                    <div class="timeline-step-description">Trabajos iniciados</div>
                                    <div class="timeline-step-date">
                                        <?= $instalacion['fecha_inicio'] ? date('d/m/Y', strtotime($instalacion['fecha_inicio'])) : 'Sin iniciar' ?>
                                    </div>
                                </div>

                                <div class="timeline-item <?= $instalacion['estado'] == 'en_progreso' ? 'en-progreso' : ($instalacion['estado'] == 'completada' ? 'completado' : 'pendiente') ?>">
                                    <div class="timeline-step-title">En Progreso</div>
                                    <div class="timeline-step-description"><?= number_format($instalacion['progreso_porcentaje'], 1) ?>% completado</div>
                                    <div class="timeline-step-date">
                                        <?= $instalacion['fecha_fin_estimada'] ? 'Est: ' . date('d/m/Y', strtotime($instalacion['fecha_fin_estimada'])) : 'Sin estimar' ?>
                                    </div>
                                </div>

                                <div class="timeline-item <?= $instalacion['estado'] == 'completada' ? 'completado' : 'pendiente' ?>">
                                    <div class="timeline-step-title">Finalización</div>
                                    <div class="timeline-step-description">Completada y entregada</div>
                                    <div class="timeline-step-date">
                                        <?= $instalacion['fecha_fin_real'] ? date('d/m/Y', strtotime($instalacion['fecha_fin_real'])) : 'Pendiente' ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Columna 2 -->
                    <div class="grid-col">
                        <!-- Materiales de la cotización -->
                        <div class="progreso-container">
                            <div class="progreso-header">
                                <h3 class="progreso-title">Rollos/Productos de la Cotización</h3>
                            </div>

                            <div class="materiales-lista">
                                <?php if (empty($materiales_cotizacion)): ?>
                                    <div class="estado-vacio">
                                        <i class="fa-solid fa-boxes-stacked"></i>
                                        <p>No hay materiales en la cotización</p>
                                    </div>
                                <?php else: ?>
                                    <?php foreach ($materiales_cotizacion as $index => $material): ?>
                                        <div class="material-card">
                                            <div class="material-nombre">
                                                <?= htmlspecialchars($material['nombre']) ?>
                                                <?php if ($material['color']): ?>
                                                    <span style="font-size: 0.8rem; color: #666;"> - <?= htmlspecialchars($material['color']) ?></span>
                                                <?php endif; ?>
                                            </div>
                                            <div class="material-cantidad">
                                                <span class="cantidad-necesaria">Cantidad: <?= $material['cantidad'] ?> <?= $material['unidad'] ?></span>
                                                <?php if ($material['area_usada']): ?>
                                                    <span class="cantidad-usada">Área: <?= $material['area_usada'] ?> m²</span>
                                                <?php endif; ?>
                                            </div>
                                            <div class="material-progreso">
                                                <div class="material-progreso-bar" style="width: 100%; background: #2196F3;"></div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- Rollos Utilizados en la Instalación -->
                        <div class="progreso-container" style="margin-top: 20px;">
                            <div class="progreso-header">
                                <h3 class="progreso-title">Rollos Utilizados en la Instalación</h3>
                                <span class="badge-info"><?= count($rollos_utilizados) ?> rollos</span>
                            </div>

                            <div class="materiales-lista">
                                <?php if (empty($rollos_utilizados)): ?>
                                    <div class="estado-vacio">
                                        <i class="fa-solid fa-tape"></i>
                                        <p>No se han asignado rollos a esta instalación</p>
                                        <?php if ($instalacion['estado'] == 'planificada'): ?>
                                            <small style="color: #666;">Los rollos se asignarán al iniciar la instalación</small>
                                        <?php endif; ?>
                                    </div>
                                <?php else: ?>
                                    <?php foreach ($rollos_utilizados as $rollo): ?>
                                        <div class="material-card rollo-utilizado">
                                            <div class="rollo-header">
                                                <div class="rollo-info-principal">
                                                    <span class="rollo-id">#<?= $rollo['id_rollo'] ?></span>
                                                    <span class="producto-nombre"><?= htmlspecialchars($rollo['producto_nombre']) ?></span>
                                                </div>
                                                <div class="color-info">
                                                    <span class="color-muestra" style="background-color: <?= $rollo['color_hex'] ?>"></span>
                                                    <span class="color-nombre"><?= htmlspecialchars($rollo['color_nombre']) ?></span>
                                                </div>
                                            </div>
                                            
                                            <div class="rollo-detalles">
                                                <div class="detalle-grupo">
                                                    <label>Área Utilizada:</label>
                                                    <span class="valor-destacado"><?= number_format($rollo['area_usada'], 2) ?> m²</span>
                                                </div>
                                                <div class="detalle-grupo">
                                                    <label>Metros Utilizados:</label>
                                                    <span class="valor-destacado"><?= number_format($rollo['metros_usados'], 2) ?> m</span>
                                                </div>
                                                <div class="detalle-grupo">
                                                    <label>Dimensiones Originales:</label>
                                                    <span><?= number_format($rollo['largo_original'], 2) ?> × <?= number_format($rollo['ancho_original'], 2) ?> m</span>
                                                </div>
                                                <div class="detalle-grupo">
                                                    <label>Lote:</label>
                                                    <span><?= $rollo['id_lote'] ? '#' . $rollo['id_lote'] : 'Sin lote' ?></span>
                                                </div>
                                                <div class="detalle-grupo">
                                                    <label>Estado:</label>
                                                    <span class="estado-rollo estado-<?= $rollo['estado_uso'] ?>"><?= ucfirst($rollo['estado_uso']) ?></span>
                                                </div>
                                                <div class="detalle-grupo">
                                                    <label>Fecha Asignación:</label>
                                                    <span><?= date('d/m/Y H:i', strtotime($rollo['fecha_asignacion'])) ?></span>
                                                </div>
                                            </div>
                                            
                                            <?php if ($rollo['observaciones']): ?>
                                                <div class="rollo-observaciones">
                                                    <label>Observaciones:</label>
                                                    <p><?= htmlspecialchars($rollo['observaciones']) ?></p>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    <?php endforeach; ?>
                                    
                                    <!-- Resumen de rollos utilizados -->
                                    <div class="resumen-rollos">
                                        <?php
                                        $total_area = array_sum(array_column($rollos_utilizados, 'area_usada'));
                                        $total_metros = array_sum(array_column($rollos_utilizados, 'metros_usados'));
                                        ?>
                                        <div class="resumen-item">
                                            <label>Total Área Utilizada:</label>
                                            <span class="valor-total"><?= number_format($total_area, 2) ?> m²</span>
                                        </div>
                                        <div class="resumen-item">
                                            <label>Total Metros Utilizados:</label>
                                            <span class="valor-total"><?= number_format($total_metros, 2) ?> m</span>
                                        </div>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <!-- Columna 3 -->
                    <div class="grid-col">

                        <div class="progreso-container">
                            <h3 class="progreso-title">Extras de la Cotización</h3>
                            <div class="materiales-lista">
                                <?php if (empty($extras_cotizacion)): ?>
                                    <div class="estado-vacio">
                                        <i class="fa-solid fa-plus-circle"></i>
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
                </div>
            </div>
        </div>

        <!-- Acciones -->
        <div class="actions-container">
            <?php if ($instalacion['observaciones'] || $instalacion['notas_instalacion']): ?>
                <button class="btn-accion btn-ver" onclick="abrirModalNotas()">
                    <i class="fa-solid fa-clipboard-list"></i> Ver Notas y Observaciones
                </button>
            <?php endif; ?>

            <?php if ($instalacion['estado'] == 'planificada'): ?>
                <button class="btn-accion btn-actualizar" onclick="cambiarEstadoInstalacion(<?= $instalacion['id'] ?>, 'en_progreso')">
                    <i class="fa-solid fa-play"></i> Iniciar Instalación
                </button>
            <?php endif; ?>

            <?php if ($instalacion['estado'] == 'en_progreso'): ?>
                <button class="btn-accion btn-completar" onclick="cambiarEstadoInstalacion(<?= $instalacion['id'] ?>, 'completada')">
                    <i class="fa-solid fa-check"></i> Marcar como Completada
                </button>
            <?php endif; ?>

            <?php if ($instalacion['estado'] != 'completada' && $instalacion['estado'] != 'cancelada'): ?>
                <button class="btn-accion btn-cancelar" onclick="cambiarEstadoInstalacion(<?= $instalacion['id'] ?>, 'cancelada')">
                    <i class="fa-solid fa-times"></i> Cancelar Instalación
                </button>
            <?php endif; ?>

            <button class="btn-accion btn-actualizar" onclick="location.href='lista.php'">
                <i class="fa-solid fa-list"></i> Volver a Lista
            </button>
        </div>

    </div>
    </div>

    <!-- Modal para ver notas y observaciones -->
    <div id="modalNotas" class="modal-edicion">
        <div class="modal-content-edicion" style="max-width: 700px;">
            <div class="modal-header">
                <h3>
                    <i class="fa-solid fa-clipboard-list"></i>
                    Notas y Observaciones - Instalación #<?= $instalacion['id'] ?>
                </h3>
                <span class="modal-close" onclick="cerrarModalNotas()">&times;</span>
            </div>
            
            <div class="modal-body-notas">
                <?php if ($instalacion['observaciones'] || $instalacion['notas_instalacion']): ?>
                    
                    <?php if ($instalacion['observaciones']): ?>
                        <div class="nota-seccion">
                            <div class="nota-header">
                                <i class="fa-solid fa-comment-dots"></i>
                                <h4>Observaciones Generales</h4>
                                <span class="nota-badge observaciones">General</span>
                            </div>
                            <div class="nota-contenido">
                                <?= nl2br(htmlspecialchars($instalacion['observaciones'])) ?>
                            </div>
                            <div class="nota-footer">
                                <small>
                                    <i class="fa-solid fa-calendar"></i>
                                    Creado: <?= date('d/m/Y H:i', strtotime($instalacion['fecha_creacion'])) ?>
                                </small>
                            </div>
                        </div>
                    <?php endif; ?>

                    <?php if ($instalacion['notas_instalacion']): ?>
                        <div class="nota-seccion">
                            <div class="nota-header">
                                <i class="fa-solid fa-sticky-note"></i>
                                <h4>Notas de Progreso</h4>
                                <span class="nota-badge progreso">Progreso</span>
                            </div>
                            <div class="nota-contenido">
                                <?= nl2br(htmlspecialchars($instalacion['notas_instalacion'])) ?>
                            </div>
                            <div class="nota-footer">
                                <small>
                                    <i class="fa-solid fa-clock"></i>
                                    Última actualización: <?= date('d/m/Y H:i') ?>
                                </small>
                            </div>
                        </div>
                    <?php endif; ?>
                    
                <?php else: ?>
                    <div class="sin-notas">
                        <i class="fa-solid fa-clipboard"></i>
                        <p>No hay notas u observaciones registradas para esta instalación.</p>
                    </div>
                <?php endif; ?>
            </div>
            
            <div class="modal-footer-notas">
                <button type="button" class="btn-accion btn-actualizar" onclick="abrirModalProgreso()">
                    <i class="fa-solid fa-plus"></i> Añadir Nota de Progreso
                </button>
                <button type="button" class="btn-accion btn-cancelar" onclick="cerrarModalNotas()">
                    <i class="fa-solid fa-times"></i> Cerrar
                </button>
            </div>
        </div>
    </div>

    <!-- Modal para actualizar progreso -->
    <div id="modalProgreso" class="modal-edicion">
        <div class="modal-content-edicion">
            <div class="modal-header">
                <h3>Actualizar Progreso</h3>
                <span class="modal-close" onclick="cerrarModalProgreso()">&times;</span>
            </div>
            <form id="formProgreso">
                <div class="form-group">
                    <label for="progreso_porcentaje">Progreso (%):</label>
                    <input type="range" id="progreso_porcentaje" name="progreso_porcentaje"
                        min="0" max="100" step="1" value="<?= $instalacion['progreso_porcentaje'] ?>"
                        oninput="document.getElementById('progreso_valor').textContent = this.value + '%'">
                    <div style="text-align: center; margin-top: 5px; font-weight: bold; color: #4CAF50;">
                        <span id="progreso_valor"><?= number_format($instalacion['progreso_porcentaje'], 0) ?>%</span>
                    </div>
                </div>

                <div class="form-group">
                    <label for="notas_progreso">Notas del Progreso:</label>
                    <textarea id="notas_progreso" name="notas" placeholder="Describe el progreso realizado..."></textarea>
                </div>

                <div class="acciones-instalacion">
                    <button type="button" class="btn-accion btn-actualizar" onclick="guardarProgreso()">
                        <i class="fa-solid fa-save"></i> Guardar Progreso
                    </button>
                    <button type="button" class="btn-accion btn-cancelar" onclick="cerrarModalProgreso()">
                        <i class="fa-solid fa-times"></i> Cancelar
                    </button>
                </div>
            </form>
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

    <script>

        let dibujoCanvasVer = null;
        let dibujoCtxVer = null;

        window.instalacionData = <?= json_encode($instalacion) ?>;
        window.materialesData = <?= json_encode($materiales_cotizacion) ?>;

        console.log('Datos cargados:', {
            instalacion: window.instalacionData,
            materiales: window.materialesData
        });

        function verDibujoTerreno() {
            console.log('verDibujoTerreno llamada');
            const modal = document.getElementById('modalVerDibujo');
            console.log('Modal encontrado:', modal);

            if (modal && window.instalacionData && window.instalacionData.dibujo_cotizacion) {
                modal.style.display = 'block';
                console.log('Modal mostrado');

                setTimeout(() => {
                    inicializarCanvasVisualizacion();
                    cargarDibujoEnCanvas(window.instalacionData.dibujo_cotizacion);
                }, 100);
            } else {
                console.log('No hay dibujo disponible o modal no encontrado');
                console.log('Modal:', modal);
                console.log('instalacionData:', window.instalacionData);
                alert('No hay dibujo de terreno disponible');
            }
        }

        function cerrarModalVerDibujo() {
            console.log('cerrarModalVerDibujo llamada');
            const modal = document.getElementById('modalVerDibujo');
            if (modal) {
                modal.style.display = 'none';
                console.log('Modal cerrado');
            }
        }

        function inicializarCanvasVisualizacion() {
            console.log('inicializarCanvasVisualizacion llamada');
            dibujoCanvasVer = document.getElementById('canvasVisualizacion');
            if (dibujoCanvasVer) {
                dibujoCtxVer = dibujoCanvasVer.getContext('2d');
                dibujoCtxVer.fillStyle = 'white';
                dibujoCtxVer.fillRect(0, 0, dibujoCanvasVer.width, dibujoCanvasVer.height);
                console.log('Canvas inicializado');
            } else {
                console.error('Canvas no encontrado');
            }
        }

        function cargarDibujoEnCanvas(dibujoData) {
            if (!dibujoCtxVer || !dibujoData) return;

            try {
                const imagen = new Image();
                imagen.onload = function() {
                    dibujoCtxVer.clearRect(0, 0, dibujoCanvasVer.width, dibujoCanvasVer.height);
                    dibujoCtxVer.drawImage(imagen, 0, 0, dibujoCanvasVer.width, dibujoCanvasVer.height);
                };
                imagen.src = 'data:image/png;base64,' + dibujoData;
            } catch (error) {
                console.error('Error al cargar el dibujo:', error);
            }
        }

        function abrirModalProgreso() {
            console.log('abrirModalProgreso llamada');
            const modal = document.getElementById('modalProgreso');
            console.log('Modal progreso encontrado:', modal);

            if (modal) {
                modal.style.display = 'block';
            } else {
                console.error('Modal de progreso no encontrado');
            }
        }

        function cerrarModalProgreso() {
            const modal = document.getElementById('modalProgreso');
            if (modal) {
                modal.style.display = 'none';
            }
        }

        function abrirModalNotas() {
            const modal = document.getElementById('modalNotas');
            if (modal) {
                modal.style.display = 'block';
                modal.style.animation = 'fadeIn 0.3s ease';
            }
        }

        function cerrarModalNotas() {
            const modal = document.getElementById('modalNotas');
            if (modal) {
                modal.style.animation = 'fadeOut 0.3s ease';
                setTimeout(() => {
                    modal.style.display = 'none';
                }, 250);
            }
        }

        function guardarProgreso() {
            const progreso = document.getElementById('progreso_porcentaje').value;
            const notas = document.getElementById('notas_progreso').value;

            if (!window.instalacionData || !window.instalacionData.id) {
                alert('Error: No se pudo obtener el ID de la instalación');
                return;
            }

            const formData = new FormData();
            formData.append('accion', 'actualizar_progreso');
            formData.append('id_instalacion', window.instalacionData.id);
            formData.append('progreso_porcentaje', progreso);
            formData.append('notas', notas);

            console.log('Enviando datos:', {
                accion: 'actualizar_progreso',
                id_instalacion: window.instalacionData.id,
                progreso_porcentaje: progreso,
                notas: notas
            });

            fetch('../../php/instalaciones/gestionar_instalacion.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => {
                    console.log('Response status:', response.status);
                    console.log('Response headers:', response.headers);
                    
                    const contentType = response.headers.get('content-type');
                    if (!contentType || !contentType.includes('application/json')) {
                        throw new Error('La respuesta no es JSON válido');
                    }
                    
                    return response.text();
                })
                .then(text => {
                    console.log('Response text:', text);
                    
                    try {
                        const data = JSON.parse(text);
                        if (data.success) {
                            alert('Progreso actualizado correctamente');
                            cerrarModalProgreso();
                            location.reload();
                        } else {
                            alert('Error al actualizar progreso: ' + (data.message || 'Error desconocido'));
                        }
                    } catch (e) {
                        console.error('Error parsing JSON:', e);
                        console.error('Raw response:', text);
                        alert('Error del servidor: Respuesta no válida');
                    }
                })
                .catch(error => {
                    console.error('Fetch error:', error);
                    alert('Error al actualizar el progreso: ' + error.message);
                });
        }

        function cambiarEstadoInstalacion(id, nuevoEstado) {
            if (!confirm(`¿Está seguro de cambiar el estado a "${nuevoEstado}"?`)) {
                return;
            }

            const formData = new FormData();
            formData.append('accion', 'cambiar_estado');
            formData.append('id_instalacion', id);
            formData.append('nuevo_estado', nuevoEstado);

            console.log('Cambiando estado:', {
                accion: 'cambiar_estado',
                id_instalacion: id,
                nuevo_estado: nuevoEstado
            });

            fetch('../../php/instalaciones/gestionar_instalacion.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => {
                    console.log('Response status:', response.status);
                    
                    const contentType = response.headers.get('content-type');
                    if (!contentType || !contentType.includes('application/json')) {
                        throw new Error('La respuesta no es JSON válido');
                    }
                    
                    return response.text();
                })
                .then(text => {
                    console.log('Response text:', text);
                    
                    try {
                        const data = JSON.parse(text);
                        if (data.success) {
                            alert('Estado actualizado correctamente');
                            location.reload();
                        } else {
                            alert('Error al actualizar estado: ' + (data.message || 'Error desconocido'));
                        }
                    } catch (e) {
                        console.error('Error parsing JSON:', e);
                        console.error('Raw response:', text);
                        alert('Error del servidor: Respuesta no válida');
                    }
                })
                .catch(error => {
                    console.error('Fetch error:', error);
                    alert('Error al actualizar el estado: ' + error.message);
                });
        }

        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                cerrarModalProgreso();
                cerrarModalVerDibujo();
                cerrarModalNotas();
            }
        });

        window.onclick = function(event) {
            const modalProgreso = document.getElementById('modalProgreso');
            const modalVerDibujo = document.getElementById('modalVerDibujo');
            const modalNotas = document.getElementById('modalNotas');

            if (event.target === modalProgreso) {
                cerrarModalProgreso();
            }
            if (event.target === modalVerDibujo) {
                cerrarModalVerDibujo();
            }
            if (event.target === modalNotas) {
                cerrarModalNotas();
            }
        };

        document.addEventListener('DOMContentLoaded', function() {
            console.log('Detalle de instalación cargado');
            console.log('Window.instalacionData:', window.instalacionData);
        });
    </script>
</body>

</html>