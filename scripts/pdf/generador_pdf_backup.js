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
        
        try {
            const logoPath = '../../img/logo.png';
            const logoWidth = 100;
            const logoHeight = 25;
            const logoX = (pageWidth - logoWidth) / 2;
            const logoY = yPosition - 10;
            
            doc.addImage(logoPath, 'PNG', logoX, logoY, logoWidth, logoHeight);
        } catch (error) {
            console.warn('No se pudo cargar el logo, usando texto por defecto:', error);
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
        }

        yPosition = 35;

        // === SECCIÓN DE INFORMACIÓN SUPERIOR ===
        // Reducir la altura del área verde a 22mm para más espacio
        const infoBoxHeight = 22;
        doc.setFillColor(125, 192, 66);
        doc.rect(marginLeft, yPosition, contentWidth, infoBoxHeight, 'F');

        doc.setFontSize(11);
        doc.setTextColor(255, 255, 255);
        doc.setFont('helvetica', 'bold');

        let infoY = yPosition + 6; 
        doc.text(`Cliente: ${data.cliente.nombre}`, marginLeft + 5, infoY);
        infoY += 6;
        doc.setFont('helvetica', 'normal');
        doc.text(`Direccion: ${data.cliente.direccion}`, marginLeft + 5, infoY);
        infoY += 6;
        if (data.cliente.telefono) {
            doc.text(`Tel: ${data.cliente.telefono}`, marginLeft + 5, infoY);
        }
        infoY += 4;
        yPosition += 25;

        // === TABLA PRINCIPAL DE PASTO ===
        doc.setDrawColor(125, 192, 66);
        doc.setLineWidth(0.3);
        doc.rect(marginLeft, yPosition, contentWidth, 8, 'S');
        doc.setFontSize(12);
        doc.setFont('helvetica', 'bold');
        doc.setTextColor(0, 0, 0); 
        doc.text('MODELO DE PASTO', marginLeft + 5, yPosition + 6);

        // Información del modelo en la parte superior derecha
        doc.setFont('helvetica', 'normal');
        let modeloInfo = data.pasto.modelo || 'Premier';
        // Agregar mm si no están incluidos
        if (data.pasto.altura_mm && !modeloInfo.includes('mm')) {
            modeloInfo = `${modeloInfo} ${data.pasto.altura_mm}mm`;
        } else if (!modeloInfo.includes('mm')) {
            modeloInfo = `${modeloInfo} 40mm`; // Valor por defecto
        }
        const modeloWidth = doc.getTextWidth(modeloInfo);
        doc.text(modeloInfo, pageWidth - marginRight - modeloWidth - 5, yPosition + 6);
        
        yPosition += 10;

        const pastoData = [
            ['Tipo de Pasto', data.pasto.tipo || 'Residencial'],
            ['Garantía por decoloración', `${data.cotizacion.garantia_anios} años`],
            ['Color', data.pasto.color || 'Lima Bambú'],
            ['Tipo de instalación', data.cotizacion.tipo_instalacion || 'Tierra'],
            ['Precio Pasto por m² con instalación', `$${parseFloat(data.pasto.precio_completo_m2 || 0).toFixed(0)}`],
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
                fontSize: 10,
                cellPadding: 2,
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

        yPosition = doc.lastAutoTable.finalY + 5;

        // === EXTRAS ===
        if (data.extras && data.extras.length > 0) {
            console.log('Agregando extras al PDF...', data.extras);
            
            // Encabezado de extras
            doc.setFillColor(125, 192, 66);
            doc.rect(marginLeft, yPosition, contentWidth, 6, 'F');
            doc.setFontSize(11);
            doc.setFont('helvetica', 'bold');
            doc.setTextColor(255, 255, 255);
            doc.text('Extras', marginLeft + 5, yPosition + 4.5);
            yPosition += 8;

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
                    fontSize: 10,
                    cellPadding: 2,
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

            yPosition = doc.lastAutoTable.finalY + 5;
        }

        console.log('Generando totales...');

        // === TOTALES ===
        // Fondo verde para totales
        doc.setFillColor(125, 192, 66);
        doc.rect(marginLeft, yPosition, contentWidth, 6, 'F');
        doc.setFontSize(11);
        doc.setFont('helvetica', 'bold');
        doc.setTextColor(255, 255, 255);
        doc.text('Total Pasto con instalación (pago en efectivo)', marginLeft + 5, yPosition + 4.5);
        
        // Precio total destacado
        const totalPrecio = `$${data.totales.subtotal.toFixed(0)}`;
        const precioTotalWidth = doc.getTextWidth(totalPrecio);
        doc.text(totalPrecio, pageWidth - marginRight - precioTotalWidth - 5, yPosition + 4.5);
        yPosition += 8;

        // Generar tabla de totales solo si hay IVA aplicado
        if (data.totales.iva_aplicado) {
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
                    fontSize: 10,
                    cellPadding: 2,
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
                        fontSize: 11
                    }
                ]
            });

            yPosition = doc.lastAutoTable.finalY + 5;
        }

        // === OPCIONES DE PAGO ===
        if (data.cotizacion.total > 0) {
            console.log('Agregando opciones de pago...');
            
            // Fondo amarillo para opciones de pago
            doc.setFillColor(255, 255, 102); // Amarillo
            doc.rect(marginLeft, yPosition, contentWidth, 6, 'F');
            doc.setFontSize(11);
            doc.setFont('helvetica', 'bold');
            doc.setTextColor(0, 0, 0);
            doc.text('PAGO CON TARJETA DE CRÉDITO', marginLeft + 5, yPosition + 4.5);
            yPosition += 8;

            // Usar el total final (con o sin IVA) para las opciones de pago
            const totalParaPagos = data.totales.iva_aplicado ? data.totales.total : data.totales.subtotal;
            
            const pagosData = [
                ['12 MESES', `$${(totalParaPagos / 12).toFixed(0)}`],
                ['6 MESES', `$${(totalParaPagos / 6).toFixed(0)}`]
            ];

            doc.autoTable({
                startY: yPosition,
                head: [],
                body: pagosData,
                margin: { left: marginLeft, right: marginRight },
                theme: 'grid',
                styles: {
                    fontSize: 10,
                    cellPadding: 2,
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

            yPosition = doc.lastAutoTable.finalY + 5;
        }

        console.log('Agregando notas y condiciones...');

        // === NOTAS Y CONDICIONES ===
        // Encabezado de notas
        doc.setFillColor(125, 192, 66);
        doc.rect(marginLeft, yPosition, contentWidth, 6, 'F');
        doc.setFontSize(11);
        doc.setFont('helvetica', 'bold');
        doc.setTextColor(255, 255, 255);
        doc.text('NOTAS Y CONDICIONES DE PAGO', marginLeft + 5, yPosition + 4.5);
        yPosition += 9.6;

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
            '-Esta cotización tiene vigencia de 30 días naturales.'
        ];

        if (!data.totales.iva_aplicado) {
            notas.push('-En caso de requerir factura agregar el 16% de IVA.');
        }

        notas.forEach((nota, index) => {
            if (yPosition > 270) {
                doc.addPage();
                yPosition = 15;
            }
            
            const lines = doc.splitTextToSize(nota, contentWidth - 10);
            lines.forEach((line, i) => {
                if (yPosition > 270) {
                    doc.addPage();
                    yPosition = 15;
                }
                doc.text(line, marginLeft + 5, yPosition);
                yPosition += 3.5;
            });
            yPosition += 0.5;
        });

        // Si no hay IVA aplicado, agregar leyenda destacada sobre facturación
        if (!data.totales.iva_aplicado) {
            yPosition += 5;
            
            // Fondo amarillo para la leyenda del IVA
            doc.setFillColor(255, 255, 102); // Amarillo
            doc.rect(marginLeft, yPosition, contentWidth, 8, 'F');
            doc.setFontSize(10);
            doc.setFont('helvetica', 'bold');
            doc.setTextColor(0, 0, 0);
            doc.text('IMPORTANTE: Para facturación fiscal agregar 16% de IVA', marginLeft + 5, yPosition + 5.5);
            yPosition += 10;
        }

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
