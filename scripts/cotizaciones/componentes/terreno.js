import { calcularArea } from '../core/calculos.js';

function toggleTerreno() {
    const tipoTerreno = document.getElementById('tipo_terreno').value;
    const btnAgregarForma = document.getElementById('btn_agregar_forma');
    const areaIrregularInput = document.getElementById('area_irregular');
    const formasContainer = document.getElementById('formas_container');

    const terrenoRegular = document.getElementById('terreno_regular');
    const terrenoIrregular = document.getElementById('terreno_irregular');

    [terrenoRegular, terrenoIrregular, btnAgregarForma, areaIrregularInput].forEach(el => {
        if (el) el.style.display = 'none';
    });

    if (tipoTerreno === 'regular' && terrenoRegular) {
        terrenoRegular.style.display = 'block';
    } else if (tipoTerreno === 'irregular' && terrenoIrregular) {
        terrenoIrregular.style.display = 'block';
        if (btnAgregarForma) btnAgregarForma.style.display = 'block';
    }

    const formaTerreno = document.getElementById('forma_terreno');
    const dimension1 = document.getElementById('dimension1');
    const dimension2 = document.getElementById('dimension2');

    if (formaTerreno) formaTerreno.value = '';
    if (dimension1) dimension1.value = '';
    if (dimension2) dimension2.value = '';
    if (areaIrregularInput) areaIrregularInput.value = '';
    if (formasContainer) formasContainer.innerHTML = '';

    calcularArea();
}

export { toggleTerreno };