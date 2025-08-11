// pdf_generator.js
import { jsPDF } from 'jspdf';
import 'jspdf-autotable';

export async function generarPDFCotizacion(cotizacionId) {
    try {
        // Obtener datos de la cotización desde el servidor
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
        doc.setTextColor(0, 100, 0); // Verde oscuro
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
            ['Precio Pasto por m² con instalación', `$${data.pasto.precio_con_instalacion.toFixed(2)}`],
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
        if (data.extras.length > 0) {
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
        
        return true;
    } catch (error) {
        console.error('Error al generar PDF:', error);
        throw error;
    }
}