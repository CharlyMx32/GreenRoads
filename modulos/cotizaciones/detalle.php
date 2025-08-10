<?php
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

$ROOT = '../..';
$TITULO = "Detalle de Cotización";

include_once "$ROOT/db/conexion.php";
include_once "$ROOT/includes/sesion.php";
include_once "$ROOT/includes/config.php";
include_once "$ROOT/includes/funciones_dibujo.php";

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
$sql_cotizacion = "
    SELECT 
        c.*,
        cli.nombre AS nombre_cliente,
        cli.telefono AS telefono_cliente,
        cli.email AS email_cliente,
        cli.direccion AS direccion_cliente,
        COALESCE(a.nombre, 'Sin asignar') AS nombre_admin,
        COALESCE(a.apellido, '') AS apellido_admin
    FROM cotizaciones c
    LEFT JOIN clientes cli ON c.id_cliente = cli.id
    LEFT JOIN admins a ON c.id_admin = a.id 
    WHERE c.id = ?
";

$stmt = mysqli_prepare($conn, $sql_cotizacion);
mysqli_stmt_bind_param($stmt, "i", $id_cotizacion);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$cotizacion = mysqli_fetch_assoc($result);

if (!$cotizacion) {
    header("Location: lista.php");
    exit();
}

// Obtener detalles de productos
$sql_detalles = "
    SELECT 
        dc.*,
        p.nombre AS nombre_producto,
        p.descripcion AS descripcion_producto,
        p.tipo_inventario,
        COALESCE(c.nombre, 'Sin color') AS nombre_color,
        COALESCE(c.codigo_hex, '#cccccc') AS color_hex
    FROM detalle_cotizacion dc
    LEFT JOIN productos p ON dc.id_producto = p.id
    LEFT JOIN colores c ON dc.id_color = c.id
    WHERE dc.id_cotizacion = ?
    ORDER BY dc.id
";

$stmt_detalles = mysqli_prepare($conn, $sql_detalles);
mysqli_stmt_bind_param($stmt_detalles, "i", $id_cotizacion);
mysqli_stmt_execute($stmt_detalles);
$result_detalles = mysqli_stmt_get_result($stmt_detalles);
$detalles = [];
while ($row = mysqli_fetch_assoc($result_detalles)) {
    $detalles[] = $row;
}

// Obtener extras de la cotización
$sql_extras = "
    SELECT 
        ce.*,
        e.nombre AS nombre_extra,
        e.descripcion AS descripcion_extra
    FROM cotizacion_extras ce
    LEFT JOIN extras e ON ce.id_extra = e.id
    WHERE ce.id_cotizacion = ?
    ORDER BY ce.id
";

$stmt_extras = mysqli_prepare($conn, $sql_extras);
mysqli_stmt_bind_param($stmt_extras, "i", $id_cotizacion);
mysqli_stmt_execute($stmt_extras);
$result_extras = mysqli_stmt_get_result($stmt_extras);
$extras = [];
while ($row = mysqli_fetch_assoc($result_extras)) {
    $extras[] = $row;
}

// Obtener inventario asignado (si existe)
$sql_inventario = "
    SELECT 
        ir.*,
        p.nombre AS nombre_producto,
        c.nombre AS nombre_color
    FROM inventario_rollos ir
    LEFT JOIN productos p ON ir.id_producto = p.id
    LEFT JOIN colores c ON ir.id_color = c.id
    WHERE ir.id_cotizacion_reserva = ?
    ORDER BY ir.fecha_ingreso ASC
";

$stmt_inventario = mysqli_prepare($conn, $sql_inventario);
mysqli_stmt_bind_param($stmt_inventario, "i", $id_cotizacion);
mysqli_stmt_execute($stmt_inventario);
$result_inventario = mysqli_stmt_get_result($stmt_inventario);
$inventario_asignado = [];
while ($row = mysqli_fetch_assoc($result_inventario)) {
    $inventario_asignado[] = $row;
}

// Obtener el dibujo del terreno
$dibujo_terreno = obtenerDibujoTerreno($conn, $id_cotizacion);
?>

<!DOCTYPE html>
<html>

<head>
    <?php include_once "$ROOT/includes/head.php"; ?>
    <link rel="stylesheet" href="../../css/cotizaciones/cotizaciones.css">
    <link rel="stylesheet" href="../../css/material.css">
    <style>
        .detalle-container {
            max-width: 1200px;
            margin: 60px auto 100px;
            padding: 20px;
        }

        .detalle-header {
            background-color: var(--color-primario);
            color: white;
            padding: 20px;
            border-radius: var(--radio-borde);
            margin-bottom: 20px;
            box-shadow: var(--sombra);
        }

        .detalle-card {
            background: white;
            border-radius: var(--radio-borde);
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: var(--sombra);
            border-left: 4px solid var(--color-primario);
        }

        .detalle-card h3 {
            color: var(--color-primario);
            margin-bottom: 15px;
            font-size: 1.2em;
            padding-bottom: 8px;
            border-bottom: 2px solid var(--color-primario);
        }

        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 15px;
            margin-bottom: 15px;
        }

        .info-item {
            display: flex;
            flex-direction: column;
        }

        .info-label {
            font-weight: 500;
            color: #6c757d;
            font-size: 0.85em;
            margin-bottom: 5px;
        }

        .info-value {
            color: #495057;
            font-size: 1em;
            padding: 8px 12px;
            background: #f8f9fa;
            border-radius: 5px;
            border-left: 3px solid var(--color-primario);
        }

        .estado-badge {
            display: inline-block;
            padding: 6px 12px;
            border-radius: 20px;
            font-weight: 500;
            font-size: 0.85em;
        }

        .estado-pendiente {
            background: #fff3cd;
            color: #856404;
        }

        .estado-aceptada {
            background: #d4edda;
            color: #155724;
        }

        .estado-rechazada {
            background: #f8d7da;
            color: #721c24;
        }

        .estado-cancelada {
            background: #e2e3e5;
            color: #383d41;
        }

        .productos-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 15px;
        }

        .productos-table th {
            background: #f8f9fa;
            font-weight: 500;
            color: #495057;
            padding: 10px;
            text-align: left;
            border-bottom: 2px solid var(--color-primario);
        }

        .productos-table td {
            padding: 10px;
            text-align: left;
            border-bottom: 1px solid #e9ecef;
        }

        .productos-table tr:hover {
            background: #f8f9fa;
        }

        .color-indicator {
            display: inline-block;
            width: 16px;
            height: 16px;
            border-radius: 3px;
            border: 1px solid #ddd;
            vertical-align: middle;
            margin-right: 6px;
        }

        .btn-group {
            display: flex;
            gap: 10px;
            margin-top: 30px;
            flex-wrap: wrap;
        }

        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 5px;
            text-decoration: none;
            font-weight: 500;
            transition: var(--transicion);
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            font-size: 14px;
        }

        .btn-primary {
            background: var(--color-primario);
            color: white;
        }

        .btn-primary:hover {
            background: #6bb536;
        }

        .btn-secondary {
            background: #6c757d;
            color: white;
        }

        .btn-secondary:hover {
            background: #5a6268;
        }

        .btn-success {
            background: #28a745;
            color: white;
        }

        .btn-success:hover {
            background: #218838;
        }

        .btn-danger {
            background: #dc3545;
            color: white;
        }

        .btn-danger:hover {
            background: #c82333;
        }

        .inventario-item {
            background: #f8f9fa;
            border: 1px solid #e9ecef;
            border-radius: 5px;
            padding: 10px;
            margin-bottom: 10px;
            transition: var(--transicion);
        }

        .inventario-item:hover {
            background: #f0f0f0;
        }

        .inventario-item.reservado {
            border-left: 4px solid var(--color-secundario);
        }

        .inventario-item.instalado {
            border-left: 4px solid #28a745;
        }

        .total-section {
            background: var(--color-primario);
            color: white;
            padding: 20px;
            border-radius: var(--radio-borde);
            text-align: center;
            margin-top: 20px;
            box-shadow: var(--sombra);
        }

        .total-amount {
            font-size: 2em;
            font-weight: 600;
            margin: 10px 0;
        }

        .imagen-terreno {
            transition: transform 0.3s ease;
            cursor: pointer;
        }

        .imagen-terreno:hover {
            transform: scale(1.05);
        }

        .dibujo-container {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 20px;
            display: inline-block;
            border: 2px solid #e9ecef;
            transition: box-shadow 0.3s ease;
        }

        .dibujo-container:hover {
            box-shadow: 0 4px 15px rgba(0,0,0,0.15);
        }

        @media (max-width: 768px) {
            .detalle-container {
                padding: 15px;
                margin-top: 20px;
            }

            .info-grid {
                grid-template-columns: 1fr;
            }

            .btn-group {
                flex-direction: column;
            }

            .btn {
                width: 100%;
                justify-content: center;
            }
        }
    </style>
</head>

<body>
    <?php
    $headerParams = [
        "titulo" => $TITULO,
        "btn_atras" => "window.location.href='../cotizaciones/lista.php'"
    ];
    include_once '../../includes/header.php';
    ?>

    <div class="detalle-container">
        <div class="detalle-header">
            <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap;">
                <div>
                    <h1 style="margin: 0; font-size: 1.5em;"><i class="fa-solid fa-file-invoice-dollar"></i> Cotización #<?= $cotizacion['id'] ?></h1>
                    <p style="margin: 5px 0; opacity: 0.9; font-size: 0.9em;">
                        <i class="fa-solid fa-calendar"></i>
                        Creada el <?= date('d/m/Y H:i', strtotime($cotizacion['fecha'])) ?>
                    </p>
                </div>
                <div>
                    <span class="estado-badge estado-<?= $cotizacion['estado'] ?>">
                        <?= ucfirst($cotizacion['estado']) ?>
                    </span>
                </div>
            </div>
        </div>

        <!-- Información del cliente -->
        <div class="detalle-card">
            <h3><i class="fa-solid fa-user"></i> Información del Cliente</h3>
            <div class="info-grid">
                <div class="info-item">
                    <span class="info-label">Nombre</span>
                    <span class="info-value"><?= htmlspecialchars($cotizacion['nombre_cliente']) ?></span>
                </div>
                <div class="info-item">
                    <span class="info-label">Teléfono</span>
                    <span class="info-value"><?= htmlspecialchars($cotizacion['telefono_cliente'] ?: 'No registrado') ?></span>
                </div>
                <div class="info-item">
                    <span class="info-label">Email</span>
                    <span class="info-value"><?= htmlspecialchars($cotizacion['email_cliente'] ?: 'No registrado') ?></span>
                </div>
                <div class="info-item">
                    <span class="info-label">Dirección</span>
                    <span class="info-value"><?= htmlspecialchars($cotizacion['direccion_cliente'] ?: 'No registrada') ?></span>
                </div>
            </div>
        </div>

        <!-- Información del proyecto -->
        <div class="detalle-card">
            <h3><i class="fa-solid fa-map"></i> Información del Proyecto</h3>
            <div class="info-grid">
                <div class="info-item">
                    <span class="info-label">Área Total</span>
                    <span class="info-value"><?= number_format($cotizacion['area_total'], 2) ?> m²</span>
                </div>
                <div class="info-item">
                    <span class="info-label">Tipo de Terreno</span>
                    <span class="info-value"><?= ucfirst($cotizacion['tipo_terreno']) ?></span>
                </div>
                <div class="info-item">
                    <span class="info-label">Tipo de Instalación</span>
                    <span class="info-value"><?= htmlspecialchars($cotizacion['tipo_instalacion']) ?></span>
                </div>
                <div class="info-item">
                    <span class="info-label">Garantía</span>
                    <span class="info-value"><?= $cotizacion['garantia_anios'] ?> años</span>
                </div>
                <div class="info-item">
                    <span class="info-label">Precio Instalación</span>
                    <span class="info-value">$<?= number_format($cotizacion['precio_instalacion_m2'], 2) ?> / m²</span>
                </div>
                <div class="info-item">
                    <span class="info-label">Admin Asignado</span>
                    <span class="info-value">
                        <?= htmlspecialchars($cotizacion['nombre_admin'] . ' ' . $cotizacion['apellido_admin']) ?>
                    </span>
                </div>
            </div>
        </div>

        <!-- Dibujo del terreno -->
        <div class="detalle-card">
            <h3><i class="fa-solid fa-pencil-ruler"></i> Diseño del Terreno</h3>
            <?php if (!empty($dibujo_terreno)): ?>
                <div style="text-align: center; padding: 15px;">
                    <div class="dibujo-container">
                        <img src="<?= htmlspecialchars($dibujo_terreno) ?>" 
                             alt="Diseño del terreno" 
                             style="max-width: 100%; max-height: 400px; border-radius: 8px; box-shadow: 0 2px 8px rgba(0,0,0,0.1);"
                             class="imagen-terreno"
                             onclick="ampliarImagen(this)">
                    </div>
                    <p style="margin-top: 10px; color: #6c757d; font-size: 0.9em;">
                        <i class="fa-solid fa-info-circle"></i> Diseño ilustrativo del terreno realizado durante la cotización
                        <br><small>Haz clic en la imagen para ampliar</small>
                    </p>
                </div>
            <?php else: ?>
                <div style="text-align: center; padding: 40px; color: #999;">
                    <i class="fa-solid fa-image" style="font-size: 48px; margin-bottom: 15px; opacity: 0.5;"></i>
                    <p style="margin: 0; font-size: 1.1em;">No hay diseño disponible</p>
                    <p style="margin: 5px 0 0 0; font-size: 0.9em;">El diseño del terreno no fue capturado durante la cotización</p>
                </div>
            <?php endif; ?>
        </div>

        <!-- Productos cotizados -->
        <div class="detalle-card">
            <h3><i class="fa-solid fa-boxes-stacked"></i> Productos Cotizados</h3>
            <?php if (!empty($detalles)): ?>
                <table class="productos-table">
                    <thead>
                        <tr>
                            <th>Producto</th>
                            <th>Color</th>
                            <th>Cantidad</th>
                            <th>Área Usada</th>
                            <th>Precio Unitario</th>
                            <th>Subtotal</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($detalles as $detalle): ?>
                            <tr>
                                <td>
                                    <strong><?= htmlspecialchars($detalle['nombre_producto']) ?></strong>
                                    <br>
                                    <small style="color: #6c757d;">
                                        <?= htmlspecialchars($detalle['descripcion_producto'] ?? '') ?>
                                    </small>
                                </td>
                                <td>
                                    <?php if ($detalle['id_color']): ?>
                                        <span class="color-indicator" style="background-color: <?= $detalle['color_hex'] ?>;"></span>
                                        <?= htmlspecialchars($detalle['nombre_color'] ?? '') ?>
                                    <?php else: ?>
                                        <span style="color: #6c757d;">N/A</span>
                                    <?php endif; ?>
                                </td>
                                <td><?= number_format($detalle['cantidad'], 2) ?></td>
                                <td>
                                    <?php if ($detalle['area_usada']): ?>
                                        <?= number_format($detalle['area_usada'], 2) ?> m²
                                    <?php else: ?>
                                        <span style="color: #6c757d;">N/A</span>
                                    <?php endif; ?>
                                </td>
                                <td>$<?= number_format($detalle['precio_unitario'], 2) ?></td>
                                <td><strong>$<?= number_format($detalle['subtotal'], 2) ?></strong></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <p style="color: #6c757d; text-align: center; padding: 20px;">
                    <i class="fa-solid fa-box-open"></i> No hay productos en esta cotización
                </p>
            <?php endif; ?>
        </div>

        <!-- Extras -->
        <?php if (!empty($extras)): ?>
            <div class="detalle-card">
                <h3><i class="fa-solid fa-plus-circle"></i> Extras</h3>
                <table class="productos-table">
                    <thead>
                        <tr>
                            <th>Nombre</th>
                            <th>Descripción</th>
                            <th>Precio</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($extras as $extra): ?>
                            <tr>
                                <td><strong><?= htmlspecialchars($extra['nombre_extra']) ?></strong></td>
                                <td><?= htmlspecialchars($extra['descripcion_extra'] ?: 'Sin descripción') ?></td>
                                <td><strong>$<?= number_format($extra['precio_aplicado'], 2) ?></strong></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>

        <!-- Inventario asignado -->
        <?php if (!empty($inventario_asignado)): ?>
            <div class="detalle-card">
                <h3><i class="fa-solid fa-warehouse"></i> Inventario Asignado</h3>
                <?php foreach ($inventario_asignado as $item): ?>
                    <div class="inventario-item <?= $item['estado'] ?>">
                        <div style="display: flex; justify-content: space-between; align-items: center;">
                            <div>
                                <strong><?= htmlspecialchars($item['nombre_producto']) ?></strong>
                                - <?= htmlspecialchars($item['nombre_color']) ?>
                                <br>
                                <small>
                                    ID: <?= $item['id'] ?> |
                                    Dimensiones: <?= $item['largo_metros'] ?>m × <?= $item['ancho_metros'] ?>m |
                                    Área: <?= $item['area_m2'] ?> m²
                                </small>
                            </div>
                            <div>
                                <span class="estado-badge estado-<?= $item['estado'] ?>">
                                    <?= ucfirst($item['estado']) ?>
                                </span>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <!-- Total -->
        <div class="total-section">
            <h3><i class="fa-solid fa-calculator"></i> Total de la Cotización</h3>
            <div class="total-amount">$<?= number_format($cotizacion['total'], 2) ?></div>
            <p>Incluye productos, instalación y extras</p>
        </div>

        <!-- Botones de acción -->
        <div class="btn-group">
            <a href="lista.php" class="btn btn-secondary">
                <i class="fa-solid fa-arrow-left"></i> Volver a Lista
            </a>

            <?php if ($cotizacion['estado'] == 'pendiente'): ?>
                <a href="editar_cotizacion.php?id=<?= $cotizacion['id'] ?>" class="btn btn-primary">
                    <i class="fa-solid fa-edit"></i> Editar
                </a>

                <button class="btn btn-success" onclick="cambiarEstado(<?= $cotizacion['id'] ?>, 'aceptada')">
                    <i class="fa-solid fa-check"></i> Aceptar
                </button>

                <button class="btn btn-danger" onclick="cambiarEstado(<?= $cotizacion['id'] ?>, 'rechazada')">
                    <i class="fa-solid fa-times"></i> Rechazar
                </button>
            <?php endif; ?>
        </div>
    </div>

    <script>
        function cambiarEstado(id, estado) {
            const mensajes = {
                'aceptada': '¿Está seguro de que desea aceptar esta cotización?',
                'rechazada': '¿Está seguro de que desea rechazar esta cotización?',
                'cancelada': '¿Está seguro de que desea cancelar esta cotización?'
            };

            if (confirm(mensajes[estado])) {
                window.location.href = `../../php/cotizaciones/cambiar_estado.php?id=${id}&estado=${estado}`;
            }
        }

        function ampliarImagen(img) {
            const modal = document.createElement('div');
            modal.style.cssText = `
                position: fixed;
                top: 0;
                left: 0;
                width: 100%;
                height: 100%;
                background: rgba(0,0,0,0.8);
                display: flex;
                justify-content: center;
                align-items: center;
                z-index: 9999;
                cursor: pointer;
            `;
            
            const modalImg = document.createElement('img');
            modalImg.src = img.src;
            modalImg.style.cssText = `
                max-width: 90%;
                max-height: 90%;
                border-radius: 10px;
                box-shadow: 0 4px 20px rgba(0,0,0,0.3);
                transition: transform 0.3s ease;
            `;
            
            modal.appendChild(modalImg);
            document.body.appendChild(modal);
            
            // Cerrar modal al hacer clic
            modal.addEventListener('click', function() {
                document.body.removeChild(modal);
            });
            
            // Cerrar modal con tecla Escape
            const handleEscape = function(e) {
                if (e.key === 'Escape') {
                    document.body.removeChild(modal);
                    document.removeEventListener('keydown', handleEscape);
                }
            };
            document.addEventListener('keydown', handleEscape);
        }
    </script>
</body>

</html>