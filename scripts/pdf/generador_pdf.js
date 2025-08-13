// pdf_generator.js - Generador de PDF para cotizaciones

// Función para abrir la vista previa del PDF (solo para desarrollo)
function abrirVistaPrevia(cotizacionId = null) {
    const url = cotizacionId ? 
        `vista_previa_pdf.html?id=${cotizacionId}` : 
        'vista_previa_pdf.html';
    
    window.open(url, '_blank', 'width=1200,height=800,scrollbars=yes,resizable=yes');
}

async function generarPDFCotizacion(cotizacionId) {
    console.log('Iniciando generación de PDF para cotización:', cotizacionId);
    
    try {
        // Verificar que jsPDF está disponible
        if (!window.jspdf) {
            throw new Error('jsPDF no está cargado. Verifica que las librerías CDN estén disponibles.');
        }
        
        console.log('jsPDF disponible, obteniendo datos...');
        
        // Obtener datos de la cotización desde el servidor
        const response = await fetch(`../../php/cotizaciones/obtener_datos_pdf.php?id=${cotizacionId}`);
        const data = await response.json();
        
        console.log('Datos recibidos del servidor:', data);
        
        if (!data.success) {
            throw new Error(data.message || 'Error al obtener datos para el PDF');
        }

        console.log('Creando documento PDF...');

        // Crear el documento PDF
        const { jsPDF } = window.jspdf;
        const doc = new jsPDF({
            orientation: 'portrait',
            unit: 'mm',
            format: 'a4'
        });

        // Configuración inicial
        const marginLeft = 15;
        const marginRight = 15;
        const pageWidth = doc.internal.pageSize.getWidth();
        const contentWidth = pageWidth - marginLeft - marginRight;
        let yPosition = 15;

        // === ENCABEZADO PRINCIPAL ===
        // Fondo verde para el encabezado
        doc.setFillColor(125, 192, 66); // Verde principal
        doc.rect(0, 0, pageWidth, 35, 'F');

        // Logo y texto principal
        doc.setFontSize(28);
        doc.setFont('helvetica', 'bold');
        doc.setTextColor(255, 255, 255); // Blanco

        const greenText = 'Green';
        const roadsText = ' ROADS';
        const greenWidth = doc.getTextWidth(greenText);
        const roadsWidth = doc.getTextWidth(roadsText);
        const totalWidth = greenWidth + roadsWidth;
        const startX = (pageWidth - totalWidth) / 2;

        doc.text(greenText, startX, yPosition + 5);
        doc.text(roadsText, startX + greenWidth, yPosition + 5);

        // Subtítulo
        doc.setFontSize(14);
        doc.setFont('helvetica', 'normal');
        const subtitleWidth = doc.getTextWidth('PASTO SINTÉTICO');
        const subtitleX = (pageWidth - subtitleWidth) / 2;
        doc.text('PASTO SINTÉTICO', subtitleX, yPosition + 15);

        yPosition = 45; // Después del encabezado verde

        // === SECCIÓN DE INFORMACIÓN SUPERIOR ===
        // Fondo gris claro para información
        doc.setFillColor(240, 240, 240);
        doc.rect(marginLeft, yPosition, contentWidth, 25, 'F');
        
        // Borde
        doc.setDrawColor(125, 192, 66);
        doc.setLineWidth(0.5);
        doc.rect(marginLeft, yPosition, contentWidth, 25, 'S');
        
        // Información del cliente y cotización en el área gris
        doc.setFontSize(11);
        doc.setTextColor(0, 0, 0);
        doc.setFont('helvetica', 'bold');
        
        // Lado izquierdo - Cliente
        doc.text('Cliente:', marginLeft + 5, yPosition + 8);
        doc.setFont('helvetica', 'normal');
        doc.text(`${data.cliente.nombre}`, marginLeft + 5, yPosition + 13);
        
        if (data.cliente.telefono) {
            doc.text(`Tel: ${data.cliente.telefono}`, marginLeft + 5, yPosition + 18);
        }

        // Lado derecho - Cotización
        doc.setFont('helvetica', 'bold');
        doc.text(`Cotización #${data.cotizacion.id}`, marginLeft + 100, yPosition + 8);
        doc.setFont('helvetica', 'normal');
        doc.text(`Fecha: ${new Date(data.cotizacion.fecha).toLocaleDateString()}`, marginLeft + 100, yPosition + 13);
        doc.text(`Válido hasta: ${new Date(data.cotizacion.fecha_vencimiento).toLocaleDateString()}`, marginLeft + 100, yPosition + 18);
        
        yPosition += 35;

        // === TABLA PRINCIPAL DE PASTO ===
        // Encabezado de sección
        doc.setFillColor(125, 192, 66);
        doc.rect(marginLeft, yPosition, contentWidth, 8, 'F');
        doc.setFontSize(12);
        doc.setFont('helvetica', 'bold');
        doc.setTextColor(255, 255, 255);
        doc.text('MODELO DE PASTO', marginLeft + 5, yPosition + 6);
        
        // Información del modelo en la parte superior derecha
        doc.setTextColor(255, 255, 255);
        doc.setFont('helvetica', 'normal');
        const modeloInfo = data.pasto.modelo || 'Premier 40mm';
        const modeloWidth = doc.getTextWidth(modeloInfo);
        doc.text(modeloInfo, pageWidth - marginRight - modeloWidth - 5, yPosition + 6);
        
        yPosition += 12;

        const pastoData = [
            ['Tipo de Pasto', data.pasto.tipo || 'Residencial'],
            ['Garantía por decoloración', `${data.cotizacion.garantia_anios} años`],
            ['Color', data.pasto.color || 'Lima Bambú'],
            ['Tipo de instalación', data.cotizacion.tipo_instalacion || 'Tierra'],
            ['Precio Pasto por m² con instalación', `$${parseFloat(data.pasto.precio_con_instalacion || 0).toFixed(0)}`],
            ['Metros cuadrados cotizados', `${data.cotizacion.area_total}`]
        ];

        console.log('Generando tabla de pasto...');

        doc.autoTable({
            startY: yPosition,
            head: [],
            body: pastoData,
            margin: { left: marginLeft, right: marginRight },
            theme: 'grid',
            styles: {
                fontSize: 11,
                cellPadding: 3,
                lineColor: [125, 192, 66],
                lineWidth: 0.3
            },
            columnStyles: {
                0: { 
                    fontStyle: 'normal', 
                    cellWidth: 70,
                    fillColor: [250, 250, 250]
                },
                1: { 
                    cellWidth: 'auto',
                    halign: 'right',
                    fontStyle: 'bold'
                }
            },
            alternateRowStyles: {
                fillColor: [248, 248, 248]
            }
        });

        yPosition = doc.lastAutoTable.finalY + 10;

        // === EXTRAS ===
        if (data.extras && data.extras.length > 0) {
            console.log('Agregando extras al PDF...', data.extras);
            
            // Encabezado de extras
            doc.setFillColor(125, 192, 66);
            doc.rect(marginLeft, yPosition, contentWidth, 8, 'F');
            doc.setFontSize(12);
            doc.setFont('helvetica', 'bold');
            doc.setTextColor(255, 255, 255);
            doc.text('Extras', marginLeft + 5, yPosition + 6);
            yPosition += 12;

            const extrasData = data.extras.map(extra => {
                const precio = parseFloat(extra.precio || 0);
                return [
                    extra.nombre,
                    precio === 0 ? '$0' : `$${precio.toFixed(0)}`
                ];
            });

            doc.autoTable({
                startY: yPosition,
                head: [],
                body: extrasData,
                margin: { left: marginLeft, right: marginRight },
                theme: 'grid',
                styles: {
                    fontSize: 11,
                    cellPadding: 3,
                    lineColor: [125, 192, 66],
                    lineWidth: 0.3
                },
                columnStyles: {
                    0: { 
                        fontStyle: 'normal', 
                        cellWidth: 70,
                        fillColor: [250, 250, 250]
                    },
                    1: { 
                        cellWidth: 'auto',
                        halign: 'right',
                        fontStyle: 'bold'
                    }
                },
                alternateRowStyles: {
                    fillColor: [248, 248, 248]
                }
            });

            yPosition = doc.lastAutoTable.finalY + 10;
        }

        console.log('Generando totales...');

        // === TOTALES ===
        // Fondo verde para totales
        doc.setFillColor(125, 192, 66);
        doc.rect(marginLeft, yPosition, contentWidth, 8, 'F');
        doc.setFontSize(12);
        doc.setFont('helvetica', 'bold');
        doc.setTextColor(255, 255, 255);
        doc.text('Total Pasto con instalación (pago en efectivo)', marginLeft + 5, yPosition + 6);
        
        // Precio total destacado
        const totalPrecio = `$${data.totales.subtotal.toFixed(0)}`;
        const precioTotalWidth = doc.getTextWidth(totalPrecio);
        doc.text(totalPrecio, pageWidth - marginRight - precioTotalWidth - 5, yPosition + 6);
        yPosition += 12;

        const totalesData = [
            ['IVA', `$${data.totales.iva.toFixed(0)}`],
            ['TOTAL (iva 16%)', `$${data.totales.total.toFixed(0)}`]
        ];

        doc.autoTable({
            startY: yPosition,
            head: [],
            body: totalesData,
            margin: { left: marginLeft, right: marginRight },
            theme: 'grid',
            styles: {
                fontSize: 11,
                cellPadding: 3,
                lineColor: [125, 192, 66],
                lineWidth: 0.3
            },
            columnStyles: {
                0: { 
                    fontStyle: 'normal', 
                    cellWidth: 70,
                    fillColor: [250, 250, 250]
                },
                1: { 
                    cellWidth: 'auto',
                    halign: 'right',
                    fontStyle: 'bold'
                }
            },
            bodyStyles: [
                {},
                { 
                    fillColor: [255, 255, 102], // Amarillo para el total
                    fontStyle: 'bold',
                    fontSize: 12
                }
            ]
        });

        yPosition = doc.lastAutoTable.finalY + 10;

        // === OPCIONES DE PAGO ===
        if (data.cotizacion.total > 0) {
            console.log('Agregando opciones de pago...');
            
            // Fondo amarillo para opciones de pago
            doc.setFillColor(255, 255, 102); // Amarillo
            doc.rect(marginLeft, yPosition, contentWidth, 8, 'F');
            doc.setFontSize(12);
            doc.setFont('helvetica', 'bold');
            doc.setTextColor(0, 0, 0);
            doc.text('PAGO CON TARJETA DE CRÉDITO', marginLeft + 5, yPosition + 6);
            yPosition += 12;

            const pagosData = [
                ['12 MESES', `$${(data.totales.total / 12).toFixed(0)}`],
                ['6 MESES', `$${(data.totales.total / 6).toFixed(0)}`]
            ];

            doc.autoTable({
                startY: yPosition,
                head: [],
                body: pagosData,
                margin: { left: marginLeft, right: marginRight },
                theme: 'grid',
                styles: {
                    fontSize: 11,
                    cellPadding: 3,
                    lineColor: [125, 192, 66],
                    lineWidth: 0.3,
                    fillColor: [255, 255, 102] // Fondo amarillo
                },
                columnStyles: {
                    0: { 
                        fontStyle: 'bold', 
                        cellWidth: 70
                    },
                    1: { 
                        cellWidth: 'auto',
                        halign: 'right',
                        fontStyle: 'bold'
                    }
                }
            });

            yPosition = doc.lastAutoTable.finalY + 10;
        }

        console.log('Agregando notas y condiciones...');

        // === NOTAS Y CONDICIONES ===
        // Encabezado de notas
        doc.setFillColor(125, 192, 66);
        doc.rect(marginLeft, yPosition, contentWidth, 8, 'F');
        doc.setFontSize(12);
        doc.setFont('helvetica', 'bold');
        doc.setTextColor(255, 255, 255);
        doc.text('NOTAS Y CONDICIONES DE PAGO', marginLeft + 5, yPosition + 6);
        yPosition += 12;

        doc.setFont('helvetica', 'normal');
        doc.setFontSize(9);
        doc.setTextColor(0, 0, 0);
        
        const notas = [
            '-Cualquier cambio en los m² estimados en la cotización, tendrá una modificación en el precio.',
            '-Se requiere un anticipo del 50% para agendar la fecha de instalación, y cubrir el 50% restante finalizar el proyecto',
            '-Es necesario que el cliente esté presente al momento de finalizar la instalación',
            '-El precio total por m² con instalación (en instalación sobre tierra) incluye: pasto seleccionado, plataforma de grava de 2 cms de espesor, andaje y acabado por agregados deportivo.',
            '-Si su instalación requiere retiro de jardín o escombro, este deberá ser indicado como Extra, Retiro de pasto y escombro.',
            '-El tiempo de instalación es de 3 día(s) hábiles.',
            '-El cliente es responsable de clausurar o quitar sistemas de riego, o indicar el paso de conexiones de internet, teléfono, agua, gas, etc. Green Roads no se hace responsable por tuberías, conexiones o instalaciones de riego instaladas en el área de jardín que puedan llegar a ser perforadas.',
            '-Esta cotización tiene vigencia de 30 días naturales.',
            '-En caso de requerir factura agregar el 16% de IVA.'
        ];

        notas.forEach((nota, index) => {
            // Verificar si necesitamos nueva página
            if (yPosition > 260) {
                doc.addPage();
                yPosition = 15;
            }
            
            // Dividir texto si es muy largo
            const lines = doc.splitTextToSize(nota, contentWidth - 10);
            lines.forEach((line, i) => {
                if (yPosition > 260) {
                    doc.addPage();
                    yPosition = 15;
                }
                doc.text(line, marginLeft + 5, yPosition);
                yPosition += 4;
            });
            yPosition += 1;
        });

        // === PIE DE PÁGINA ===
        // Asegurar que estemos en la parte inferior
        if (yPosition < 250) {
            yPosition = 250;
        }
        
        // Línea divisoria
        doc.setDrawColor(125, 192, 66);
        doc.setLineWidth(0.5);
        doc.line(marginLeft, yPosition, pageWidth - marginRight, yPosition);
        yPosition += 5;

        // Información de contacto
        doc.setFontSize(10);
        doc.setTextColor(0, 0, 0);
        doc.setFont('helvetica', 'normal');
        doc.text('Green Roads Laguna - Pasto sintético', marginLeft, yPosition);
        doc.text('Visita www.greenroads.mx', marginLeft, yPosition + 5);

        // Firma
        doc.setFontSize(11);
        doc.setFont('helvetica', 'normal');
        doc.text('Atentamente', pageWidth - marginRight - 40, yPosition);
        doc.setFont('helvetica', 'bold');
        doc.text(data.admin.nombre || 'Jacqueline Lopez Segura', pageWidth - marginRight - 40, yPosition + 8);

        console.log('Guardando PDF...');

        // Guardar el PDF
        
        window.open(doc.output('bloburl'));
        //doc.save(`Cotizacion_GreenRoads_${data.cotizacion.id}.pdf`);
        
        console.log('PDF generado exitosamente!');
        
        alert('PDF generado exitosamente!');
        
        return true;
    } catch (error) {
        console.error('Error al generar PDF:', error);
        alert('Error al generar el PDF: ' + error.message);
        throw error;
    }
}