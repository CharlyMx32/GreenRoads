<?php
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

$ROOT = '../..';
$TITULO = "Cotizaciones";

include_once "$ROOT/db/conexion.php";
include_once "$ROOT/includes/sesion.php";
include_once "$ROOT/includes/config.php";

if (!tieneSesion()) {
    header("Location: $URL_ROOT/login");
    exit();
}

// Obtener cotizaciones con cliente y admin
$cotizaciones = [];
$sql = "
    SELECT 
        c.id,
        cli.nombre AS nombre_cliente,
        c.fecha,
        c.estado,
        c.total,
        c.tipo_terreno,
        c.tipo_instalacion,
        c.garantia_anios,
        c.id_admin,  
        COALESCE(a.nombre, 'Sin asignar') AS nombre_admin,
        COALESCE(a.apellido, '') AS apellido_admin
    FROM cotizaciones c
    LEFT JOIN clientes cli ON c.id_cliente = cli.id
    LEFT JOIN admins a ON c.id_admin = a.id 
    ORDER BY c.fecha DESC
";
$result = mysqli_query($conn, $sql);
while ($row = mysqli_fetch_assoc($result)) {
    $cotizaciones[] = $row;
}
?>

<!DOCTYPE html>
<html>

<head>
    <?php include_once "$ROOT/includes/head.php"; ?>
</head>

<body>
    <?php
    $headerParams = [
        "buscador" => true,
        "btn_atras" => "window.location.href='../dashboard/menu.php'"
    ];
    include_once '../../includes/header.php';
    ?>

    <div class="content">
        <div class="contenedor-tabla" style="max-height: calc(100vh - 200px); margin-bottom: 60px;">
            <table class="tabla-lista">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Cliente</th>
                        <th>Fecha</th>
                        <th>Estado</th>
                        <th>Total</th>
                        <th>Admin</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($cotizaciones)): ?>
                        <tr>
                            <td colspan="11">No hay cotizaciones registradas.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($cotizaciones as $cotizacion):
                            $estado_final = in_array($cotizacion['estado'], ['rechazada', 'cancelada', 'aceptada']);
                        ?>
                            <tr>
                                <td>#<?= $cotizacion['id'] ?></td>
                                <td><?= htmlspecialchars($cotizacion['nombre_cliente'] ?? '') ?></td>
                                <td><?= date('Y-m-d', strtotime($cotizacion['fecha'])) ?></td>
                                <td><?= ucfirst($cotizacion['estado']) ?></td>
                                <td>$<?= isset($cotizacion['total']) ? number_format((float)$cotizacion['total'], 2) : '0.00' ?></td>
                                <td>
                                    <?php
                                    if (!empty($cotizacion['id_admin'])) {
                                        echo htmlspecialchars(
                                            ($cotizacion['nombre_admin'] ?? 'Admin ID: ') .
                                                (!empty($cotizacion['apellido_admin']) ? ' ' . $cotizacion['apellido_admin'] : '')
                                        );
                                    } else {
                                        echo 'Sin asignar';
                                    }
                                    ?>
                                </td>
                                <td>
                                    <div class="opciones-tabla-lista">
                                        <!-- Botón ver detalle -->
                                        <div class="ver-detalle" 
                                            onclick="location.href='detalle.php?id=<?= $cotizacion['id'] ?>'"
                                            title="Ver detalle"
                                            style="color: #5facffff;">
                                            <i class="fa-solid fa-eye"></i>
                                        </div>

                                        <!-- Botón editar -->
                                        <div class="editar <?= $cotizacion['estado'] != 'pendiente' ? 'disabled' : '' ?>"
                                            onclick="<?= $cotizacion['estado'] == 'pendiente' ? "location.href='{$ROOT}/cotizaciones/editar_cotizacion?id={$cotizacion['id']}'" : '' ?>"
                                            <?= $cotizacion['estado'] != 'pendiente' ? 'style="opacity: 0.5; cursor: not-allowed;"' : '' ?>>
                                            <i class="fa-solid fa-pen-to-square"></i>
                                        </div>

                                        <!-- Botón aceptar -->
                                        <div class="aceptar <?= $cotizacion['estado'] != 'pendiente' ? 'disabled' : '' ?>"
                                            onclick="<?= $cotizacion['estado'] == 'pendiente' ? "confirmChangeStatus({$cotizacion['id']}, 'aceptada')" : '' ?>"
                                            style="<?= $cotizacion['estado'] == 'aceptada' ? 'color: #00dd0b;' : ($cotizacion['estado'] != 'pendiente' ? 'opacity: 0.5; cursor: not-allowed;' : '') ?>">
                                            <i class="fa-solid fa-check"></i>
                                        </div>

                                        <!-- Botón rechazar -->
                                        <div class="rechazar <?= $cotizacion['estado'] != 'pendiente' ? 'disabled' : '' ?>"
                                            onclick="<?= $cotizacion['estado'] == 'pendiente' ? "confirmChangeStatus({$cotizacion['id']}, 'rechazada')" : '' ?>"
                                            style="<?= $cotizacion['estado'] == 'rechazada' ? 'color: #ff0000;' : ($cotizacion['estado'] != 'pendiente' ? 'opacity: 0.5; cursor: not-allowed;' : '') ?>">
                                            <i class="fa-solid fa-times"></i>
                                        </div>

                                        <!-- Botón cancelar -->
                                        <div class="cancelar <?= $cotizacion['estado'] != 'pendiente' ? 'disabled' : '' ?>"
                                            onclick="<?= $cotizacion['estado'] == 'pendiente' ? "confirmChangeStatus({$cotizacion['id']}, 'cancelada')" : '' ?>"
                                            style="<?= $cotizacion['estado'] == 'cancelada' ? 'color: #ff9900;' : ($cotizacion['estado'] != 'pendiente' ? 'opacity: 0.5; cursor: not-allowed;' : '') ?>">
                                            <i class="fa-solid fa-ban"></i>
                                        </div>

                                        <!-- Botón generar PDF -->
                                        <div class="generar-pdf"
                                            onclick="generarPDF(<?= $cotizacion['id'] ?>)"
                                            title="Generar PDF"
                                            style="color: #ff6b35;">
                                            <i class="fa-solid fa-file-pdf"></i>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div class="btn-nuevo" onclick="location.href='nueva_cotizacion.php'">
        <i class="fa-solid fa-plus"></i>
    </div>

    <?php include_once '../../includes/popup.php'; ?>

    <script src="../../scripts/cotizaciones/lista.js"></script>
    <script>
        function confirmChangeStatus(id, estado) {
            const mensajes = {
                'aceptada': '¿Confirmas que deseas ACEPTAR esta cotización?',
                'rechazada': '¿Confirmas que deseas RECHAZAR esta cotización?',
                'cancelada': '¿Confirmas que deseas CANCELAR esta cotización?',
                'pendiente': '¿Confirmas que deseas volver a PENDIENTE esta cotización?'
            };

            if (confirm(mensajes[estado] || '¿Confirmas el cambio de estado?')) {
                changeStatus(id, estado);
            }
        }

        function changeStatus(id, estado) {
            displayPopUp();

            fetch(`../../php/cotizaciones/cambiar_estado.php?id=${id}&estado=${estado}`)
                .then(response => {
                    if (!response.ok) throw new Error('Error en la red');
                    return response.json();
                })
                .then(data => {
                    if (data.success) {
                        window.location.reload();
                    } else {
                        displayMensajeError(data.message || 'No se puede cambiar el estado nuevamente');
                        // Deshabilitar botones después de un error
                        document.querySelectorAll(`[onclick*="confirmChangeStatus(${id},"]`).forEach(btn => {
                            btn.classList.add('disabled');
                            btn.style.opacity = '0.5';
                            btn.style.cursor = 'not-allowed';
                            btn.setAttribute('onclick', '');
                        });
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    displayMensajeError("Error de conexión. Intente nuevamente.");
                });
        }

        async function generarPDF(cotizacionId) {
            try {
                displayPopUp();
                
                // Importar jsPDF dinámicamente
                const jsPDFModule = await import('https://unpkg.com/jspdf@2.5.1/dist/jspdf.umd.min.js');
                const jsPDF = jsPDFModule.jsPDF;
                
                // Importar autotable
                await import('https://unpkg.com/jspdf-autotable@3.5.25/dist/jspdf.plugin.autotable.min.js');
                
                // Obtener datos de la cotización
                const response = await fetch(`../../php/cotizaciones/obtener_datos_pdf.php?id=${cotizacionId}`);
                const data = await response.json();
                
                if (!data.success) {
                    throw new Error(data.message || 'Error al obtener datos para el PDF');
                }

                // Crear el documento PDF
                const doc = new jsPDF({
                    orientation: 'portrait',
                    unit: 'mm',
                    format: 'a4'
                });

                // Configuración inicial
                const marginLeft = 15;
                let yPosition = 15;

                // Logo y encabezado
                doc.setFontSize(16);
                doc.setTextColor(0, 100, 0);
                doc.setFont('helvetica', 'bold');
                doc.text('Green ROADS', marginLeft, yPosition);
                
                doc.setFontSize(12);
                doc.setTextColor(100);
                doc.text('PASTO SINTÉTICO', marginLeft, yPosition + 5);
                
                // Línea divisoria
                yPosition += 10;
                doc.setDrawColor(0, 100, 0);
                doc.setLineWidth(0.5);
                doc.line(marginLeft, yPosition, 200 - marginLeft, yPosition);
                yPosition += 5;

                // Información del cliente
                doc.setFontSize(12);
                doc.setTextColor(0);
                doc.setFont('helvetica', 'bold');
                doc.text('Cliente:', marginLeft, yPosition);
                
                doc.setFont('helvetica', 'normal');
                doc.text(`${data.cliente.nombre}`, marginLeft + 20, yPosition);
                
                if (data.cliente.telefono) {
                    yPosition += 5;
                    doc.text(`Teléfono: ${data.cliente.telefono}`, marginLeft, yPosition);
                }
                
                if (data.cliente.direccion) {
                    yPosition += 5;
                    doc.text(`Dirección: ${data.cliente.direccion}`, marginLeft, yPosition);
                }
                
                yPosition += 10;

                // Detalles de la cotización
                doc.setFont('helvetica', 'bold');
                doc.text(`Cotización #${data.cotizacion.id}`, marginLeft, yPosition);
                
                doc.setFont('helvetica', 'normal');
                doc.text(`Fecha: ${new Date(data.cotizacion.fecha).toLocaleDateString()}`, marginLeft + 50, yPosition);
                
                yPosition += 5;
                doc.text(`Válido hasta: ${new Date(data.cotizacion.fecha_vencimiento).toLocaleDateString()}`, marginLeft + 50, yPosition);
                
                yPosition += 10;

                // Tabla de productos principales (pasto)
                doc.setFont('helvetica', 'bold');
                doc.text('Detalle del Pasto Sintético', marginLeft, yPosition);
                yPosition += 5;

                const pastoData = [
                    ['Modelo de Pasto', data.pasto.modelo || 'No especificado'],
                    ['Tipo de Pasto', data.pasto.tipo || 'Residencial'],
                    ['Garantía por decoloración', `${data.cotizacion.garantia_anios} años`],
                    ['Color', data.pasto.color || 'No especificado'],
                    ['Tipo de instalación', data.cotizacion.tipo_instalacion],
                    ['Precio Pasto por m² con instalación', `$${data.pasto.precio_con_instalacion ? data.pasto.precio_con_instalacion.toFixed(2) : '0.00'}`],
                    ['Metros cuadrados cotizados', `${data.cotizacion.area_total} m²`]
                ];

                doc.autoTable({
                    startY: yPosition,
                    head: [],
                    body: pastoData,
                    margin: { left: marginLeft },
                    theme: 'grid',
                    headStyles: {
                        fillColor: [255, 255, 255],
                        textColor: [0, 0, 0],
                        fontStyle: 'bold'
                    },
                    bodyStyles: {
                        textColor: [0, 0, 0]
                    },
                    columnStyles: {
                        0: { fontStyle: 'bold', cellWidth: 70 },
                        1: { cellWidth: 'auto' }
                    },
                    styles: {
                        lineColor: [0, 100, 0],
                        lineWidth: 0.2
                    }
                });

                yPosition = doc.lastAutoTable.finalY + 10;

                // Extras
                if (data.extras && data.extras.length > 0) {
                    doc.setFont('helvetica', 'bold');
                    doc.text('Extras incluidos:', marginLeft, yPosition);
                    yPosition += 5;

                    const extrasData = data.extras.map(extra => [
                        extra.nombre,
                        extra.precio === 0 ? 'Incluido' : `$${extra.precio.toFixed(2)}`
                    ]);

                    doc.autoTable({
                        startY: yPosition,
                        head: [],
                        body: extrasData,
                        margin: { left: marginLeft },
                        theme: 'grid',
                        columnStyles: {
                            0: { fontStyle: 'bold', cellWidth: 70 },
                            1: { cellWidth: 'auto' }
                        },
                        styles: {
                            lineColor: [0, 100, 0],
                            lineWidth: 0.2
                        }
                    });

                    yPosition = doc.lastAutoTable.finalY + 10;
                }

                // Totales
                doc.setFont('helvetica', 'bold');
                doc.text('Resumen de Costos', marginLeft, yPosition);
                yPosition += 5;

                const totalesData = [
                    ['Total Pasto con instalación', `$${data.totales.subtotal.toFixed(2)}`],
                    ['IVA (16%)', `$${data.totales.iva.toFixed(2)}`],
                    ['TOTAL', `$${data.totales.total.toFixed(2)}`]
                ];

                doc.autoTable({
                    startY: yPosition,
                    head: [],
                    body: totalesData,
                    margin: { left: marginLeft },
                    theme: 'grid',
                    columnStyles: {
                        0: { fontStyle: 'bold', cellWidth: 70 },
                        1: { cellWidth: 'auto', fontStyle: 'bold' }
                    },
                    styles: {
                        lineColor: [0, 100, 0],
                        lineWidth: 0.2
                    },
                    bodyStyles: [
                        {}, 
                        {},
                        { textColor: [0, 100, 0], fontStyle: 'bold', fontSize: 12 }
                    ]
                });

                yPosition = doc.lastAutoTable.finalY + 10;

                // Opciones de pago
                if (data.cotizacion.total > 0) {
                    doc.setFont('helvetica', 'bold');
                    doc.text('OPCIONES DE PAGO', marginLeft, yPosition);
                    yPosition += 5;

                    const pagosData = [
                        ['12 MESES', `$${(data.totales.total / 12).toFixed(2)}`],
                        ['6 MESES', `$${(data.totales.total / 6).toFixed(2)}`]
                    ];

                    doc.autoTable({
                        startY: yPosition,
                        head: [],
                        body: pagosData,
                        margin: { left: marginLeft },
                        theme: 'grid',
                        columnStyles: {
                            0: { fontStyle: 'bold', cellWidth: 70 },
                            1: { cellWidth: 'auto' }
                        },
                        styles: {
                            lineColor: [0, 100, 0],
                            lineWidth: 0.2
                        }
                    });

                    yPosition = doc.lastAutoTable.finalY + 10;
                }

                // Notas y condiciones
                doc.setFont('helvetica', 'bold');
                doc.text('NOTAS Y CONDICIONES DE PAGO', marginLeft, yPosition);
                yPosition += 5;

                doc.setFont('helvetica', 'normal');
                doc.setFontSize(10);
                
                const notas = [
                    'Cualquier cambio en los m² estimados en la cotización, tendrá una modificación en el precio.',
                    'Se requiere un anticipo del 50% para agendar la fecha de instalación, y cubrir el 50% restante al finalizar el proyecto.',
                    'Es necesario que el cliente esté presente al momento de finalizar la instalación.',
                    'El precio total por m² con instalación incluye: pasto seleccionado, plataforma de grava de 2 cms de espesor, andaje y acabado.',
                    'Si su instalación requiere retiro de jardín o escombro, este deberá ser indicado como Extra.',
                    'El tiempo de instalación es de 3 días hábiles (aproximado).',
                    'El cliente es responsable de clausurar o quitar sistemas de riego, o indicar el paso de conexiones de internet, teléfono, agua, gas, etc.',
                    'Green Roads no se hace responsable por tuberías, conexiones o instalaciones de riego instaladas en el área de jardín que puedan llegar a ser perforadas.',
                    'Esta cotización tiene vigencia de 30 días naturales.',
                    'En caso de requerir factura agregar el 16% de IVA.'
                ];

                notas.forEach((nota, index) => {
                    // Dividir texto si es muy largo
                    const lines = doc.splitTextToSize(`• ${nota}`, 180);
                    lines.forEach((line, i) => {
                        if (yPosition > 270) {
                            doc.addPage();
                            yPosition = 15;
                        }
                        doc.text(line, marginLeft + 5, yPosition);
                        yPosition += 5;
                    });
                    yPosition += 2;
                });

                // Pie de página
                yPosition = 280;
                doc.setFontSize(10);
                doc.setTextColor(100);
                doc.text('Green Roads Laguna - Pasto sintético', marginLeft, yPosition);
                doc.text('Visita www.greenroads.mx', marginLeft, yPosition + 5);

                // Firma
                doc.setFontSize(12);
                doc.setTextColor(0);
                doc.text('Atentamente', 150, yPosition);
                doc.text(data.admin.nombre || 'Equipo Green Roads', 150, yPosition + 10);

                // Guardar el PDF
                doc.save(`Cotizacion_GreenRoads_${data.cotizacion.id}.pdf`);
                
                hidePopup();
                displayMensajeExitoso('PDF generado correctamente', 'hidePopup()');
                
            } catch (error) {
                console.error('Error al generar PDF:', error);
                hidePopup();
                displayMensajeError('Error al generar el PDF: ' + error.message);
            }
        }
    </script>
</body>

</html>