// pdf_generator.js - Generador de PDF para cotizaciones

// Función para abrir la vista previa del PDF (solo para desarrollo)
function abrirVistaPrevia(cotizacionId = null) {
    const url = cotizacionId ? 
        `vista_previa_pdf.html?id=${cotizacionId}` : 
        'vista_previa_pdf.html';
    
    window.open(url, '_blank', 'width=1200,height=800,scrollbars=yes,resizable=yes');
}

async function generarPDFCotizacion(cotizacionId) {
    try {
        // Verificar que jsPDF está disponible
        if (!window.jspdf) {
            throw new Error('jsPDF no está cargado. Verifica que las librerías CDN estén disponibles.');
        }
        
        // Obtener datos de la cotización desde el servidor
        const response = await fetch(`../../php/cotizaciones/obtener_datos_pdf.php?id=${cotizacionId}`);
        const data = await response.json();
        
        if (!data.success) {
            throw new Error(data.message || 'Error al obtener datos para el PDF');
        }

        // Obtener parámetros del sistema (incluyendo número de teléfono)
        const parametrosResponse = await fetch(`../../php/configuracion/obtener_parametros.php`);
        const parametrosData = await parametrosResponse.json();
        const numeroTelefono = parametrosData.parametros?.numero_telefono || '449-123-4567';
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
            const logoWidth = 80;
            const logoHeight = 25;
            const logoX = (pageWidth - logoWidth) / 2;
            const logoY = yPosition - 10;
            
            doc.addImage(logoPath, 'PNG', logoX, logoY, logoWidth, logoHeight);
        } catch (error) {
            // No se pudo cargar el logo, usar texto por defecto
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
        // Verificar si es cotización comparativa
        const esComparativa = data.cotizacion.es_comparativa === 1 || data.cotizacion.es_comparativa === 'S';
        
        if (esComparativa) {
            // Para cotizaciones comparativas, generar PDFs separados para cada opción
            return await generarPDFComparativo(doc, data, numeroTelefono, marginLeft, marginRight, pageWidth, contentWidth, yPosition);
        }

        // === CONTINUAR CON COTIZACIÓN SIMPLE ===
        // Información del cliente en una sola línea horizontal
        const infoBoxHeight = 12; // Reducido para una sola línea
        doc.setFillColor(125, 192, 66);
        doc.rect(marginLeft, yPosition, contentWidth, infoBoxHeight, 'F');

        doc.setFontSize(11);
        doc.setTextColor(255, 255, 255);
        doc.setFont('helvetica', 'bold');

        let infoY = yPosition + 8; // Centrado verticalmente
        
        // Cliente en el lado izquierdo
        doc.setFont('helvetica', 'bold');
        doc.text(`Cliente: ${data.cliente.nombre}`, marginLeft + 5, infoY);
        
        // Calcular posiciones dinámicamente para evitar empalme
        const clienteWidth = doc.getTextWidth(`Cliente: ${data.cliente.nombre}`);
        const direccionX = marginLeft + 10 + clienteWidth + 10; // 10mm de margen
        
    // Dirección de la cotización en el centro-izquierda
    doc.setFont('helvetica', 'normal');
    const direccionText = `Dirección: ${data.cotizacion.direccion}`;
    const direccionWidth = doc.getTextWidth(direccionText);
    doc.text(direccionText, direccionX, infoY);
        
        // Teléfono en el lado derecho
        if (data.cliente.telefono) {
            const telefonoText = `Tel: ${data.cliente.telefono}`;
            const telefonoWidth = doc.getTextWidth(telefonoText);
            const telefonoX = pageWidth - marginRight - telefonoWidth - 5;
            doc.text(telefonoText, telefonoX, infoY);
        }
        
        yPosition += 15; // Reducido de 25 a 15

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



        // Construir la tabla principal de pasto, agregando días de instalación justo después de metros cuadrados
        const pastoData = [
            ['Tipo de Pasto', data.pasto.tipo || 'Residencial'],
            ['Garantía por decoloración', `${data.cotizacion.garantia_anios} años`],
            ['Color', data.pasto.color || 'Lima Bambú'],
            ['Tipo de instalación', data.cotizacion.tipo_instalacion || 'Tierra'],
            ['Precio Pasto por m² con instalación', `${parseFloat(data.pasto.precio_completo_m2 || 0).toFixed(0)}`],
            ['Metros cuadrados cotizados', `${data.cotizacion.area_total}`]
        ];
        if (typeof data.dias_instalacion !== 'undefined') {
            pastoData.push(['Tiempo aproximado de instalación', `${data.dias_instalacion} día(s) hábiles`]);
        }

        doc.autoTable({
            startY: yPosition,
            head: [],
            body: pastoData,
            margin: { left: marginLeft, right: marginRight },
            theme: 'striped',
            tableLineWidth: 0.5,
            lineColor: [125, 192, 66], // Verde para el contorno
            lineWidth: 0.5,
            styles: {
                fontSize: 10,
                cellPadding: 2
            },
            columnStyles: {
                0: { 
                    fontStyle: 'normal', 
                    cellWidth: 70,
                },
                1: { 
                    cellWidth: 'auto',
                    halign: 'right',
                    fontStyle: 'bold'
                }
            },
            alternateRowStyles: {
                fillColor: [248, 248, 248]
            },
            tableLineColor: [125, 192, 66],
            tableLineWidth: 0.5
        });

        yPosition = doc.lastAutoTable.finalY + 3;

        // === EXTRAS ===
        if (data.extras && data.extras.length > 0) {
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
                theme: 'striped',
            tableLineColor: [125, 192, 66],
            tableLineWidth: 0.5,
            lineColor: [255, 255, 255],
            lineWidth: 0,
                styles: {
                    fontSize: 10,
                    cellPadding: 2
                },
                columnStyles: {
                    0: { 
                        fontStyle: 'normal', 
                        cellWidth: 70,
        
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

            yPosition = doc.lastAutoTable.finalY + 3;
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
                theme: 'striped',
            tableLineColor: [125, 192, 66],
            tableLineWidth: 0.5,
            lineColor: [255, 255, 255],
            lineWidth: 0,
                styles: {
                    fontSize: 10,
                    cellPadding: 2,
        
        
                },
    
    
                columnStyles: {
                    0: { 
                        fontStyle: 'normal', 
                        cellWidth: 70,
        
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

            yPosition = doc.lastAutoTable.finalY + 3;
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
            // Centrar el texto en el recuadro amarillo
            const pagoTarjetaText = 'PAGO CON TARJETA DE CRÉDITO';
            const pagoTarjetaWidth = doc.getTextWidth(pagoTarjetaText);
            const pagoTarjetaX = marginLeft + (contentWidth - pagoTarjetaWidth) / 2;
            doc.text(pagoTarjetaText, pagoTarjetaX, yPosition + 4.5);
            yPosition += 8;

            // Usar el total final (con o sin IVA) para las opciones de pago
            const totalParaPagos = data.totales.iva_aplicado ? data.totales.total : data.totales.subtotal;
            
                        const pagosData = [
                ['12 MESES', `${((totalParaPagos * 1.25) / 12).toFixed(0)}`],
                ['6 MESES', `${((totalParaPagos * 1.185) / 6).toFixed(0)}`]
            ];

            doc.autoTable({
                startY: yPosition,
                head: [],
                body: pagosData,
                margin: { left: marginLeft, right: marginRight },
                theme: 'striped',
                tableLineColor: [125, 192, 66],
                tableLineWidth: 0.5,
                lineColor: [255, 255, 255],
                lineWidth: 0,
                styles: {
                    fontSize: 10,
                    cellPadding: 2
                },
                columnStyles: {
                    0: { fontStyle: 'bold', cellWidth: 70, fillColor: [255, 255, 102] },
                    1: { cellWidth: 'auto', halign: 'right', fontStyle: 'bold', fillColor: [255, 255, 102] }
                },
                didParseCell: function (data) {
                    // Aplica fondo amarillo solo a las filas de 12 y 6 meses
                    if (data.row.index === 0 || data.row.index === 1) {
                        data.cell.styles.fillColor = [255, 255, 102];
                    }
                },
                alternateRowStyles: {
                    fillColor: [248, 248, 248]
                }
            });

            yPosition = doc.lastAutoTable.finalY + 3;
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
            '-Se requiere un anticipo del 50% para agendar la fecha de instalación, y cubrir el 50% restante al finalizar el proyecto.',
            '-Es necesario que el cliente esté presente al momento de finalizar la instalación.',
            '-El precio total por m² con instalación (en instalación sobre tierra) incluye: pasto seleccionado, plataforma de grava de 2 cms de espesor, andaje y acabado por agregados deportivo.',
            '-Si su instalación requiere retiro de jardín o escombro, este deberá ser indicado como Extra, Retiro de pasto y escombro.',
            '-El cliente es responsable de clausurar o quitar sistemas de riego, o indicar el paso de conexiones de internet, teléfono, agua, gas, etc. Green Roads no se hace responsable por tuberías, conexiones o instalaciones de riego instaladas en el área de jardín que puedan llegar a ser perforadas.',
            '-En caso de no contar con agua, Se hará un cargo adicional.',
            '-El tiempo de instalación podrá clausurar o quitar sistemas de riego.',
            '-Esta cotización tiene vigencia de 30 días naturales.',
            '-En caso de requerir factura agregar el 16% de IVA.'
        ];

        if (!data.totales.iva_aplicado) {
            // Remover la nota de IVA de la lista principal ya que se agregó al final
            notas.pop();
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

    // Información de contacto y teléfono de confirmación/cancelación
    doc.setFontSize(10);
    doc.setTextColor(0, 0, 0);
    doc.setFont('helvetica', 'normal');
    doc.text('Green Roads Laguna - Pasto sintético', marginLeft, yPosition);
    // Teléfono destacado para contacto
    doc.setFont('helvetica', 'bold');
    doc.setTextColor(125, 192, 66);
    doc.text(`Teléfono para confirmar o cancelar su cotización: ${numeroTelefono}`, marginLeft, yPosition + 4);
    doc.setFont('helvetica', 'normal');
    doc.setTextColor(0, 0, 0);
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

// Función para generar PDF comparativo
async function generarPDFComparativo(doc, data, numeroTelefono, marginLeft, marginRight, pageWidth, contentWidth, yPosition) {
    console.log('Generando PDF comparativo...');
    
    try {
        // Obtener productos agrupados por opción
        const productosOpcionA = data.productos?.filter(p => 
            p.opcion_comparativa === 'A' || p.opcion_comparativa === '1' || p.opcion_comparativa === 1
        ) || [];
        
        const productosOpcionB = data.productos?.filter(p => 
            p.opcion_comparativa === 'B' || p.opcion_comparativa === '2' || p.opcion_comparativa === 2
        ) || [];

        // Si no se encuentran productos con opciones definidas, usar división por índices
        if (productosOpcionA.length === 0 && productosOpcionB.length === 0 && data.productos?.length >= 2) {
            productosOpcionA.push(data.productos[0]);
            productosOpcionB.push(data.productos[1]);
        }

        if (productosOpcionA.length === 0 || productosOpcionB.length === 0) {
            throw new Error('No se pudieron determinar las opciones comparativas');
        }

        // Generar página de comparación
        await generarPaginaComparativa(doc, data, productosOpcionA, productosOpcionB, numeroTelefono, 
                                      marginLeft, marginRight, pageWidth, contentWidth, yPosition);

        console.log('PDF comparativo generado exitosamente!');
        
        // Abrir el PDF
        window.open(doc.output('bloburl'));
        alert('PDF comparativo generado exitosamente!');
        
        return true;
    } catch (error) {
        console.error('Error al generar PDF comparativo:', error);
        throw error;
    }
}

// Función para generar una página comparativa
async function generarPaginaComparativa(doc, data, productosOpcionA, productosOpcionB, numeroTelefono, 
                                       marginLeft, marginRight, pageWidth, contentWidth, yPosition) {
    
    // === ENCABEZADO PRINCIPAL ===
    // Nota: En comparativas no agregamos el logo aquí ya que se agrega en generarPDF()
    yPosition = 35;

    // === INFORMACIÓN DEL CLIENTE ===
    const infoBoxHeight = 12; // Reducido para una sola línea
    doc.setFillColor(125, 192, 66);
    doc.rect(marginLeft, yPosition, contentWidth, infoBoxHeight, 'F');

    doc.setFontSize(11);
    doc.setTextColor(255, 255, 255);
    doc.setFont('helvetica', 'bold');

    let infoY = yPosition + 8; // Centrado verticalmente
    
    // Cliente en el lado izquierdo
    doc.setFont('helvetica', 'bold');
    doc.text(`Cliente: ${data.cliente.nombre}`, marginLeft + 5, infoY);
    
    // Calcular posiciones dinámicamente para evitar empalme
    const clienteWidth = doc.getTextWidth(`Cliente: ${data.cliente.nombre}`);
    const direccionX = marginLeft + 10 + clienteWidth + 10; // 10mm de margen
    
    // Dirección de la cotización en el centro-izquierda
    doc.setFont('helvetica', 'normal');
    const direccionText = `Dirección: ${data.cotizacion.direccion}`;
    const direccionWidth = doc.getTextWidth(direccionText);
    doc.text(direccionText, direccionX, infoY);
    
    // Teléfono en el lado derecho
    if (data.cliente.telefono) {
        const telefonoText = `Tel: ${data.cliente.telefono}`;
        const telefonoWidth = doc.getTextWidth(telefonoText);
        const telefonoX = pageWidth - marginRight - telefonoWidth - 5;
        doc.text(telefonoText, telefonoX, infoY);
    }
    
    yPosition += 15; // Reducido de 25 a 15

    // === TABLA COMPARATIVA DE MODELOS DE PASTO ===
    doc.setDrawColor(125, 192, 66);
    doc.setLineWidth(0.3);
    doc.rect(marginLeft, yPosition, contentWidth, 8, 'S');
    doc.setFontSize(12);
    doc.setFont('helvetica', 'bold');
    doc.setTextColor(0, 0, 0); 
    doc.text('MODELO DE PASTO', marginLeft + 5, yPosition + 6);

    // Modelos en la parte superior derecha
    doc.setFont('helvetica', 'normal');
    const modeloA = `${productosOpcionA[0]?.modelo || 'Premier'} ${productosOpcionA[0]?.altura_mm || 35}mm`;
    const modeloB = `${productosOpcionB[0]?.modelo || 'Ultra'} ${productosOpcionB[0]?.altura_mm || 35}mm`;
    
    doc.text(modeloA, pageWidth - marginRight - 120, yPosition + 6);
    doc.text(modeloB, pageWidth - marginRight - 50, yPosition + 6);
    
    yPosition += 10;

    // Crear tabla comparativa con tres columnas
    const datosComparativos = [
        ['Tipo de Pasto', 
         productosOpcionA[0]?.tipo || 'Residencial', 
         productosOpcionB[0]?.tipo || 'Residencial'],
        ['Color', 
         productosOpcionA[0]?.color || 'Lima Bambú', 
         productosOpcionB[0]?.color || '6 tonos de verde y dorado'],
        ['Tipo de instalación', 
         data.cotizacion.tipo_instalacion || 'Tierra', 
         data.cotizacion.tipo_instalacion || 'Tierra'],
        ['Precio Pasto por m² con instalación', 
         `$${Math.round(productosOpcionA[0]?.precio_completo_m2 || 450)}`, 
         `$${Math.round(productosOpcionB[0]?.precio_completo_m2 || 500)}`],
        ['Metros cuadrados cotizados', 
         `${data.cotizacion.area_total}`, 
         `${data.cotizacion.area_total}`]
    ];

    console.log('Generando tabla comparativa...');

    doc.autoTable({
        startY: yPosition,
        head: [],
        body: datosComparativos,
        margin: { left: marginLeft, right: marginRight },
        theme: 'grid',
        tableLineColor: [125, 192, 66],
        tableLineWidth: 0.5,
        lineColor: [125, 192, 66],
        lineWidth: 0.5,
        styles: {
            fontSize: 10,
            cellPadding: 2
        },
        alternateRowStyles: {
            fillColor: [248, 248, 248]
        },
        columnStyles: {
            0: { 
                fontStyle: 'normal', 
                cellWidth: 70
            },
            1: { 
                cellWidth: 'auto',
                halign: 'center',
                fontStyle: 'bold'
            },
            2: { 
                cellWidth: 'auto',
                halign: 'center',
                fontStyle: 'bold'
            }
        }
    });

    yPosition = doc.lastAutoTable.finalY + 3;

    // === EXTRAS (si los hay) ===
    if (data.extras && data.extras.length > 0) {
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
                precio === 0 ? '$0' : `$${precio.toFixed(0)}`,
                precio === 0 ? '$0' : `$${precio.toFixed(0)}`
            ];
        });

        doc.autoTable({
            startY: yPosition,
            head: [],
            body: extrasData,
            margin: { left: marginLeft, right: marginRight },
            theme: 'grid',
            tableLineColor: [125, 192, 66],
            tableLineWidth: 0.5,
            lineColor: [125, 192, 66],
            lineWidth: 0.3,
            styles: {
                fontSize: 10,
                cellPadding: 2,
    

            },


            columnStyles: {
                0: { 
                    fontStyle: 'normal', 
                    cellWidth: 70,
    
                },
                1: { 
                    cellWidth: 'auto',
                    halign: 'center',
                    fontStyle: 'bold'
                },
                2: { 
                    cellWidth: 'auto',
                    halign: 'center',
                    fontStyle: 'bold'
                }
            },
            alternateRowStyles: {
                fillColor: [248, 248, 248]
            }
        });

        yPosition = doc.lastAutoTable.finalY + 3;
    }

    // === SUBTOTALES ===
    const precioOpcionA = calcularPrecioOpcion(productosOpcionA, data);
    const precioOpcionB = calcularPrecioOpcion(productosOpcionB, data);

    // Título según si hay IVA o no
    const tituloSeccion = data.totales.iva_aplicado ? 'Sub-total' : 'Total';
    doc.setFillColor(125, 192, 66);
    doc.rect(marginLeft, yPosition, contentWidth, 6, 'F');
    doc.setFontSize(11);
    doc.setFont('helvetica', 'bold');
    doc.setTextColor(255, 255, 255);
    doc.text(tituloSeccion, marginLeft + 5, yPosition + 4.5);
    yPosition += 8;

    const subtotalData = [
        ['Total Pasto con instalación (pago en efectivo)', 
         `$${Math.round(precioOpcionA)}`, 
         `$${Math.round(precioOpcionB)}`]
    ];

    doc.autoTable({
        startY: yPosition,
        head: [],
        body: subtotalData,
        margin: { left: marginLeft, right: marginRight },
        theme: 'grid',
            tableLineColor: [125, 192, 66],
            tableLineWidth: 0.5,
            lineColor: [125, 192, 66],
            lineWidth: 0.3,
        styles: {
            fontSize: 10,
            cellPadding: 2,


            fillColor: [125, 192, 66],
            textColor: [255, 255, 255],
            fontStyle: 'bold'
        },


        columnStyles: {
            0: { 
                cellWidth: 70,
                fillColor: [125, 192, 66]
            },
            1: { 
                cellWidth: 'auto',
                halign: 'center',
                fillColor: [125, 192, 66]
            },
            2: { 
                cellWidth: 'auto',
                halign: 'center',
                fillColor: [125, 192, 66]
            }
        }
    });

    yPosition = doc.lastAutoTable.finalY + 3;

    // === TOTALES CON IVA (si aplica) ===
    if (data.totales.iva_aplicado) {
        const ivaA = precioOpcionA * 0.16;
        const ivaB = precioOpcionB * 0.16;
        const totalA = precioOpcionA + ivaA;
        const totalB = precioOpcionB + ivaB;

        const totalesData = [
            ['IVA (16%)', `$${Math.round(ivaA)}`, `$${Math.round(ivaB)}`],
            ['TOTAL (iva 16%)', `$${Math.round(totalA)}`, `$${Math.round(totalB)}`]
        ];

        doc.autoTable({
            startY: yPosition,
            head: [],
            body: totalesData,
            margin: { left: marginLeft, right: marginRight },
            theme: 'grid',
            tableLineColor: [125, 192, 66],
            tableLineWidth: 0.5,
            lineColor: [125, 192, 66],
            lineWidth: 0.3,
            styles: {
                fontSize: 10,
                cellPadding: 2
            },
            columnStyles: {
                0: { 
                    fontStyle: 'normal', 
                    cellWidth: 70,
    
                },
                1: { 
                    cellWidth: 'auto',
                    halign: 'center',
                    fontStyle: 'bold'
                },
                2: { 
                    cellWidth: 'auto',
                    halign: 'center',
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

        yPosition = doc.lastAutoTable.finalY + 3;
    }

    // === Anticipo ===
    const anticipoData = [
        ['Anticipo', '$0.00', '$0.00']
    ];

    doc.autoTable({
        startY: yPosition,
        head: [],
        body: anticipoData,
        margin: { left: marginLeft, right: marginRight },
        theme: 'grid',
            tableLineColor: [125, 192, 66],
            tableLineWidth: 0.5,
            lineColor: [125, 192, 66],
            lineWidth: 0.3,
        styles: {
            fontSize: 10,
            cellPadding: 2,


        },


        columnStyles: {
            0: { 
                fontStyle: 'normal', 
                cellWidth: 70
            },
            1: { 
                cellWidth: 'auto',
                halign: 'center',
                fontStyle: 'bold'
            },
            2: { 
                cellWidth: 'auto',
                halign: 'center',
                fontStyle: 'bold'
            }
        },
        alternateRowStyles: {
            fillColor: [248, 248, 248]
        }
    });

    yPosition = doc.lastAutoTable.finalY + 6;

    // === OPCIONES DE PAGO ===
    doc.setFillColor(255, 255, 102); // Amarillo
    doc.rect(marginLeft, yPosition, contentWidth, 6, 'F');
    doc.setFontSize(11);
    doc.setFont('helvetica', 'bold');
    doc.setTextColor(0, 0, 0);
    // Centrar el texto en el recuadro amarillo
    const pagoTarjetaText = 'PAGO CON TARJETA DE CRÉDITO';
    const pagoTarjetaWidth = doc.getTextWidth(pagoTarjetaText);
    const pagoTarjetaX = marginLeft + (contentWidth - pagoTarjetaWidth) / 2;
    doc.text(pagoTarjetaText, pagoTarjetaX, yPosition + 4.5);
    yPosition += 8;

    const totalFinalA = data.totales.iva_aplicado ? precioOpcionA * 1.16 : precioOpcionA;
    const totalFinalB = data.totales.iva_aplicado ? precioOpcionB * 1.16 : precioOpcionB;
    
        const pagosData = [
        ['12 MESES', `${Math.round((totalFinalA * 1.25) / 12)}`, `${Math.round((totalFinalB * 1.25) / 12)}`],
        ['6 MESES', `${Math.round((totalFinalA * 1.185) / 6)}`, `${Math.round((totalFinalB * 1.185) / 6)}`]
    ];

    doc.autoTable({
        startY: yPosition,
        head: [],
        body: pagosData,
        margin: { left: marginLeft, right: marginRight },
        theme: 'grid',
        tableLineColor: [125, 192, 66],
        tableLineWidth: 0.5,
        lineColor: [125, 192, 66],
        lineWidth: 0.3,
        styles: {
            fontSize: 10,
            cellPadding: 2
        },
        columnStyles: {
            0: { fontStyle: 'bold', cellWidth: 70, fillColor: [255, 255, 102] },
            1: { cellWidth: 'auto', halign: 'center', fontStyle: 'bold', fillColor: [255, 255, 102] },
            2: { cellWidth: 'auto', halign: 'center', fontStyle: 'bold', fillColor: [255, 255, 102] }
        },
        didParseCell: function (data) {
            // Aplica fondo amarillo solo a las filas de 12 y 6 meses
            if (data.row.index === 0 || data.row.index === 1) {
                data.cell.styles.fillColor = [255, 255, 102];
            }
        }
    });

    yPosition = doc.lastAutoTable.finalY + 6;

    // === NOTAS ESPECÍFICAS PARA COMPARATIVAS ===
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
    
    const notasComparativas = [
        '-Cualquier cambio en los m² estimados en la cotización, tendrá una modificación en el precio.',
        '-Se requiere un anticipo del 50% para agendar la fecha de instalación, y cubrir el 50% restante al finalizar el proyecto.',
        '-Es necesario que el cliente esté presente al momento de finalizar la instalación.',
        '-El precio total por m² con instalación (en instalación sobre tierra) incluye: pasto seleccionado, plataforma de grava de 2 cms de espesor, andaje y acabado por agregados deportivo.',
        '-Si su instalación requiere retiro de jardín o escombro, este deberá ser indicado como Extra, Retiro de pasto y escombro.',
        '-El cliente es responsable de clausurar o quitar sistemas de riego, o indicar el paso de conexiones de internet, teléfono, agua, gas, etc. Green Roads no se hace responsable por tuberías, conexiones o instalaciones de riego instaladas en el área de jardín que puedan llegar a ser perforadas.',
        '-En caso de no contar con agua, Se hará un cargo adicional.',
        '-El tiempo de instalación podrá clausurar o quitar sistemas de riego.',
        '-Esta cotización tiene vigencia de 30 días naturales.',
        '-En caso de requerir factura agregar el 16% de IVA.',
        '-Debido al manejo de inventario, no se aceptan devoluciones de anticipo.'
    ];

    if (!data.totales.iva_aplicado) {
        // Remover la nota de IVA de la lista principal ya que se agregó al final
        notasComparativas.pop();
    }

    notasComparativas.forEach((nota) => {
        if (yPosition > 270) {
            doc.addPage();
            yPosition = 15;
        }
        
        const lines = doc.splitTextToSize(nota, contentWidth - 10);
        lines.forEach((line) => {
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
    // Teléfono destacado para contacto
    doc.setFont('helvetica', 'bold');
    doc.setTextColor(125, 192, 66);
    doc.text(`Teléfono para confirmar o cancelar su cotización: ${numeroTelefono}`, marginLeft, yPosition + 4);
    doc.setFont('helvetica', 'normal');
    doc.setTextColor(0, 0, 0);

    // Firma
    doc.setFontSize(11);
    doc.setFont('helvetica', 'normal');
    doc.text('Atentamente', pageWidth - marginRight - 40, yPosition);
    doc.setFont('helvetica', 'bold');
    doc.text(data.admin.nombre || 'Jacqueline Lopez Segura', pageWidth - marginRight - 40, yPosition + 8);
}

// Función para generar una opción comparativa en columna
async function generarOpcionComparativa(doc, titulo, producto, data, x, y, width, opcion) {
    const originalY = y;
    
    // Encabezado de opción
    const headerColor = opcion === 'A' ? [76, 175, 80] : [63, 81, 181]; // Verde para A, Azul para B
    doc.setFillColor(...headerColor);
    doc.rect(x, y, width, 8, 'F');
    doc.setFontSize(12);
    doc.setFont('helvetica', 'bold');
    doc.setTextColor(255, 255, 255);
    doc.text(titulo, x + 5, y + 6);
    y += 10;

    // Información del producto en formato más compacto
    doc.setTextColor(0, 0, 0);
    doc.setFontSize(9);
    
    // Modelo con altura
    doc.setFont('helvetica', 'bold');
    const modeloCompleto = `${producto?.modelo || 'No especificado'} ${producto?.altura_mm || 40}mm`;
    doc.text(modeloCompleto, x + 2, y);
    y += 4;
    
    doc.setFont('helvetica', 'normal');
    doc.text(`Tipo de Pasto: ${producto?.tipo || 'Residencial'}`, x + 2, y);
    y += 4;
    
    doc.text(`Garantía por decoloración: ${data.cotizacion.garantia_anios} años`, x + 2, y);
    y += 4;
    
    doc.text(`Color: ${producto?.color || 'No especificado'}`, x + 2, y);
    y += 4;
    
    doc.text(`Tipo de instalación: ${data.cotizacion.tipo_instalacion || 'Tierra'}`, x + 2, y);
    y += 4;
    
    const precioM2 = parseFloat(producto?.precio_completo_m2) || parseFloat(data.pasto.precio_completo_m2) || 0;
    doc.text(`Precio Pasto por m² con instalación: $${precioM2.toFixed(0)}`, x + 2, y);
    y += 4;
    
    doc.text(`Metros cuadrados cotizados: ${data.cotizacion.area_total}`, x + 2, y);
    y += 8;

    // Precio total destacado
    const precio = calcularPrecioOpcion([producto], data);
    doc.setFillColor(245, 245, 245);
    doc.rect(x, y, width, 15, 'F');
    doc.setFontSize(12);
    doc.setFont('helvetica', 'bold');
    doc.setTextColor(...headerColor);
    doc.text(`Total Pasto con instalación`, x + 2, y + 6);
    doc.text(`(pago en efectivo)`, x + 2, y + 10);
    
    // Precio en la esquina derecha
    doc.setFontSize(14);
    const precioText = `$${precio.toFixed(0)}`;
    const precioWidth = doc.getTextWidth(precioText);
    doc.text(precioText, x + width - precioWidth - 2, y + 10);
}

// Función para calcular el precio de una opción
function calcularPrecioOpcion(productos, data) {
    if (!productos || productos.length === 0) return 0;
    
    // Usar el precio completo por m² del producto y multiplicar por área
    const producto = productos[0];
    const areaTotal = parseFloat(data.cotizacion.area_total) || 0;
    const precioCompleto = parseFloat(producto.precio_completo_m2) || parseFloat(data.pasto.precio_completo_m2) || 0;
    
    // Agregar extras si los hay
    let precioExtras = 0;
    if (data.extras && data.extras.length > 0) {
        precioExtras = data.extras.reduce((sum, extra) => sum + parseFloat(extra.precio || 0), 0);
    }
    
    const subtotal = (areaTotal * precioCompleto) + precioExtras;
    
    // Aplicar IVA si corresponde
    if (data.totales.iva_aplicado) {
        return subtotal * 1.16;
    }
    
    return subtotal;
}
