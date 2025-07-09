// formas_irregulares.js
document.addEventListener('DOMContentLoaded', function() {
    const btnAgregarForma = document.getElementById('btn_agregar_forma');
    const formasContainer = document.getElementById('formas_container');
    const areaIrregularInput = document.getElementById('area_irregular');

    btnAgregarForma.addEventListener('click', agregarForma);

    function agregarForma() {
        const formaId = Date.now();
        const formaHTML = `
            <div class="forma-item" id="forma_${formaId}" style="margin-bottom: 15px; padding: 10px; border: 1px solid #ddd; border-radius: 4px;">
                <div style="display: flex; justify-content: space-between; margin-bottom: 10px;">
                    <select class="textfield forma-select" onchange="actualizarForma('${formaId}')">
                        <option value="">-- Selecciona Forma --</option>
                        <option value="rectangulo">Rectángulo</option>
                        <option value="triangulo">Triángulo</option>
                        <option value="circulo">Círculo</option>
                        <option value="cuadrado">Cuadrado</option>
                    </select>
                    <button type="button" onclick="removerForma('${formaId}')" class="btn-danger" style="margin-left: 10px;">
                        <i class="fa-solid fa-trash"></i>
                    </button>
                </div>
                <div class="forma-inputs" id="inputs_${formaId}" style="display: none;"></div>
                <div class="forma-resultado" style="margin-top: 5px; font-size: 0.9em;">
                    Área: <span id="area_${formaId}">0.00</span> m²
                </div>
            </div>
        `;
        
        formasContainer.insertAdjacentHTML('beforeend', formaHTML);
        areaIrregularInput.style.display = 'none';
    }

    window.actualizarForma = function(formaId) {
        const formaSelect = document.querySelector(`#forma_${formaId} .forma-select`);
        const inputsContainer = document.querySelector(`#inputs_${formaId}`);
        const areaSpan = document.querySelector(`#area_${formaId}`);
        const forma = formaSelect.value;

        inputsContainer.innerHTML = '';
        inputsContainer.style.display = 'none';

        if (!forma) {
            areaSpan.textContent = '0.00';
            actualizarAreaTotal();
            return;
        }

        let inputsHTML = '';
        switch(forma) {
            case 'rectangulo':
                inputsHTML = `
                    <div style="display: flex; gap: 10px; margin-top: 5px;">
                        <input type="number" class="textfield dimension1" placeholder="Largo (m)" step="0.01" min="0" onchange="calcularAreaForma('${formaId}')">
                        <input type="number" class="textfield dimension2" placeholder="Ancho (m)" step="0.01" min="0" onchange="calcularAreaForma('${formaId}')">
                    </div>
                `;
                break;
            case 'triangulo':
                inputsHTML = `
                    <div style="display: flex; gap: 10px; margin-top: 5px;">
                        <input type="number" class="textfield dimension1" placeholder="Base (m)" step="0.01" min="0" onchange="calcularAreaForma('${formaId}')">
                        <input type="number" class="textfield dimension2" placeholder="Altura (m)" step="0.01" min="0" onchange="calcularAreaForma('${formaId}')">
                    </div>
                `;
                break;
            case 'circulo':
                inputsHTML = `
                    <div style="margin-top: 5px;">
                        <input type="number" class="textfield dimension1" placeholder="Radio (m)" step="0.01" min="0" onchange="calcularAreaForma('${formaId}')">
                    </div>
                `;
                break;
            case 'cuadrado':
                inputsHTML = `
                    <div style="margin-top: 5px;">
                        <input type="number" class="textfield dimension1" placeholder="Lado (m)" step="0.01" min="0" onchange="calcularAreaForma('${formaId}')">
                    </div>
                `;
                break;
        }

        inputsContainer.innerHTML = inputsHTML;
        inputsContainer.style.display = 'block';
    };

    window.calcularAreaForma = function(formaId) {
        const formaSelect = document.querySelector(`#forma_${formaId} .forma-select`);
        const areaSpan = document.querySelector(`#area_${formaId}`);
        const forma = formaSelect.value;
        let area = 0;

        if (forma === 'rectangulo' || forma === 'triangulo') {
            const dim1 = parseFloat(document.querySelector(`#forma_${formaId} .dimension1`).value) || 0;
            const dim2 = parseFloat(document.querySelector(`#forma_${formaId} .dimension2`).value) || 0;
            area = forma === 'rectangulo' ? dim1 * dim2 : (dim1 * dim2) / 2;
        } else if (forma === 'circulo') {
            const radio = parseFloat(document.querySelector(`#forma_${formaId} .dimension1`).value) || 0;
            area = Math.PI * Math.pow(radio, 2);
        } else if (forma === 'cuadrado') {
            const lado = parseFloat(document.querySelector(`#forma_${formaId} .dimension1`).value) || 0;
            area = Math.pow(lado, 2);
        }

        areaSpan.textContent = area.toFixed(2);
        actualizarAreaTotal();
    };

    window.removerForma = function(formaId) {
        document.getElementById(`forma_${formaId}`).remove();
        actualizarAreaTotal();
        
        if (document.querySelectorAll('.forma-item').length === 0) {
            areaIrregularInput.style.display = 'block';
        }
    };

    window.actualizarAreaTotal = function() {
        const formas = document.querySelectorAll('.forma-item');
        let areaTotal = 0;

        if (formas.length > 0) {
            formas.forEach(forma => {
                const areaText = forma.querySelector('.forma-resultado span').textContent;
                areaTotal += parseFloat(areaText) || 0;
            });
            document.getElementById('area_total').value = areaTotal.toFixed(2);
        } else {
            const areaSimple = parseFloat(areaIrregularInput.value) || 0;
            document.getElementById('area_total').value = areaSimple.toFixed(2);
        }

        if (typeof actualizarTotales === 'function') {
            actualizarTotales();
        }
    };

    areaIrregularInput.addEventListener('input', actualizarAreaTotal);
});