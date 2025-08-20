import { verificarModoComparativo } from '../componentes/rollos.js';

function calcularArea() {
    const tipoTerreno = document.getElementById('tipo_terreno')?.value;
    let area = 0;

    if (tipoTerreno === 'regular') {
        const forma = document.getElementById('forma_terreno')?.value;
        const dim1 = parseFloat(document.getElementById('dimension1')?.value) || 0;
        const dim2 = parseFloat(document.getElementById('dimension2')?.value) || 0;

        switch (forma) {
            case 'rectangulo': area = dim1 * dim2; break;
            case 'triangulo': area = (dim1 * dim2) / 2; break;
            case 'circulo': area = Math.PI * Math.pow(dim1, 2); break;
            default: area = 0;
        }
    } else if (tipoTerreno === 'irregular') {
        // Calcular área basada en formas irregulares
        const formas = document.querySelectorAll('.forma-item');
        
        if (formas.length > 0) {
            formas.forEach(forma => {
                const areaText = forma.querySelector('.forma-resultado span')?.textContent;
                area += parseFloat(areaText) || 0;
            });
        } else {
            area = parseFloat(document.getElementById('area_irregular')?.value) || 0;
        }
    }

    const areaTotalInput = document.getElementById('area_total');
    if (areaTotalInput) {
        areaTotalInput.value = area.toFixed(2);
    }
    
    verificarModoComparativo();
}

export { calcularArea };