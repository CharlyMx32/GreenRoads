document.addEventListener('DOMContentLoaded', function () {
    initTabs();
    initParamForms();
});

function initTabs() {
    const tabs = document.querySelectorAll('.tab');
    tabs.forEach(tab => {
        tab.addEventListener('click', () => {
            document.querySelectorAll('.tab').forEach(t => t.classList.remove('active'));
            document.querySelectorAll('.tab-content').forEach(c => c.classList.remove('active'));

            const tipoInicial = document.querySelector('.tab-tabulador.active')?.getAttribute('data-tipo') || 'precio_instalacion';
            cargarTabuladores(tipoInicial);

            tab.classList.add('active');
            const tabId = tab.getAttribute('data-tab');
            document.getElementById(tabId)?.classList.add('active');
        });
    });

    document.querySelectorAll('.tab-tabulador').forEach(tab => {
        tab.addEventListener('click', () => {
            document.querySelectorAll('.tab-tabulador').forEach(t => t.classList.remove('active'));
            tab.classList.add('active');
            const tipo = tab.getAttribute('data-tipo');
            cargarTabuladores(tipo);
        });
    });
}

function initParamForms() {
    document.querySelectorAll('.parametro-card form').forEach(form => {
        form.addEventListener('submit', function (e) {
            e.preventDefault();
            handleFormSubmit(this);
        });
    });
}

function handleFormSubmit(form) {
    const btnSubmit = form.querySelector('button[type="submit"]');
    if (btnSubmit) {
        btnSubmit.disabled = true;
        btnSubmit.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Guardando...';
    }

    const formData = new FormData(form);

    fetch('../../php/configuracion/parametros.php?t=' + Date.now(), {
        method: 'POST',
        body: formData
    })
        .then(handleResponse)
        .then(data => handleSuccess(data, form))
        .catch(error => handleError(error))
        .finally(() => {
            if (btnSubmit) {
                btnSubmit.disabled = false;
                btnSubmit.innerHTML = '<i class="fas fa-save"></i> Guardar';
            }
        });
}

function handleResponse(response) {
    if (!response.ok) throw new Error('Error en la respuesta del servidor');
    return response.json();
}

function handleSuccess(data, form) {
    if (data.status == 0) throw new Error(data.mensaje || "Error al actualizar el parámetro");

    displayMensajeExitoso(data.mensaje);
    updateLastModified(form);
}

function handleError(error) {
    console.error('Error:', error);
    displayMensajeError(error.message);
}

function updateLastModified(form) {
    const fechaElement = form.querySelector('.parametro-info');
    if (fechaElement) {
        fechaElement.textContent = 'Últ. actualización: ' + new Date().toLocaleDateString('es-MX', {
            day: '2-digit',
            month: '2-digit',
            year: 'numeric',
            hour: '2-digit',
            minute: '2-digit'
        });
    }
}

function cargarTabuladores(tipo) {
    console.log("Consultando tabuladores de tipo:", tipo);

    fetch(`../../php/configuracion/obtener_tabulador.php?tipo=${tipo}`)
        .then(handleResponse)
        .then(data => {
            if (data.status === 1) {
                actualizarTablaTabuladores(data.tabuladores);
            } else {
                throw new Error(data.mensaje);
            }
        })
        .catch(error => {
            console.error('Error:', error);
            displayMensajeError('Error al cargar tabuladores');
        });
}
function cerrarModalTabulador() {
    document.getElementById('modalTabulador').style.display = 'none';
    document.body.style.overflow = 'auto';
}

function actualizarTablaTabuladores(tabuladores) {
    const tbody = document.querySelector('.tabla-tabulador tbody');
    if (!tbody) return;

    tbody.innerHTML = tabuladores.length === 0
        ? '<tr><td colspan="9">No hay rangos configurados</td></tr>'
        : tabuladores.map(createTableRow).join('');
}

function createTableRow(tabulador) {
    return `
        <tr data-id="${tabulador.id}" data-tipo="${tabulador.tipo}" class="${tabulador.tipo === 'precio_instalacion' ? '' : 'hidden'}">
            <td>${parseFloat(tabulador.rango_min).toFixed(2)}</td>
            <td>${parseFloat(tabulador.rango_max).toFixed(2)}</td>
            <td>$${parseFloat(tabulador.valor).toFixed(2)}</td>
            <td>${tabulador.descripcion || ''}</td>
            <td><span class="estado ${tabulador.activo ? 'activo' : 'inactivo'}">${tabulador.activo ? 'Activo' : 'Inactivo'}</span></td>
            <td>${new Date(tabulador.fecha_actualizacion).toLocaleDateString('es-MX')}</td>
            <td>
                <button class="btn-editar" onclick="editarTabulador(${tabulador.id})">
                    <i class="fas fa-edit"></i>
                </button>
                <button class="btn-eliminar" onclick="eliminarTabulador(${tabulador.id})">
                    <i class="fas fa-trash"></i>
                </button>
            </td>
        </tr>
    `;
}


async function guardarTabulador() {
    const form = document.getElementById('formTabulador');
    if (!form) return;

    const btnGuardar = document.querySelector('#modalTabulador .btn-guardar');
    btnGuardar.disabled = true;
    btnGuardar.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Guardando...';

    try {
        // Validaciones básicas
        const rangoMin = parseFloat(document.getElementById('rango_min').value);
        const rangoMax = parseFloat(document.getElementById('rango_max').value);
        const precioM2 = parseFloat(document.getElementById('valor').value);

        if (isNaN(rangoMin)) throw new Error('Rango mínimo inválido');
        if (isNaN(rangoMax)) throw new Error('Rango máximo inválido');
        if (isNaN(precioM2)) throw new Error('Precio inválido');
        if (rangoMin >= rangoMax) throw new Error('Rango mínimo debe ser menor al máximo');

        // Preparar datos
        const data = {
            id: document.getElementById('tabulador_id').value || 0,
            rango_min: rangoMin,
            rango_max: rangoMax,
            valor: precioM2,
            descripcion: document.getElementById('descripcion').value,
            activo: document.getElementById('activo').checked ? 1 : 0,
            tipo: document.getElementById('tipo').value
        };

        const response = await fetch('../../php/configuracion/guardar_tabulador.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify(data)
        });

        const result = await response.json();

        if (!response.ok || !result.status) {
            throw new Error(result.mensaje || 'Error al guardar');
        }

        displayMensajeExitoso(result.mensaje || 'Cambios guardados correctamente');

        setTimeout(() => {
            cerrarModalTabulador();
            setTimeout(() => location.reload(), 500);
        }, 1500);

    } catch (error) {
        console.error('Error:', error);
        mostrarErrorEnModal(error.message);
    } finally {
        btnGuardar.disabled = false;
        btnGuardar.innerHTML = 'Guardar';
    }
}
function validateTabuladorForm(formData) {
    const rangoMin = parseFloat(formData.get('rango_min'));
    const rangoMax = parseFloat(formData.get('rango_max'));
    const precioM2 = parseFloat(formData.get('valor'));

    if (isNaN(rangoMin) || isNaN(rangoMax) || isNaN(precioM2)) {
        mostrarErrorEnModal('Todos los campos deben tener valores numéricos válidos.');
        return false;
    }

    if (rangoMin >= rangoMax) {
        mostrarErrorEnModal('El rango mínimo debe ser menor que el máximo');
        document.getElementById('rango_min').classList.add('error-input');
        document.getElementById('rango_max').classList.add('error-input');
        return false;
    }

    return true;
}

async function parseResponse(response) {
    const text = await response.text();
    try {
        return JSON.parse(text);
    } catch {
        throw new Error('Respuesta inválida del servidor');
    }
}

function mostrarErrorEnModal(mensaje) {
    let errorContainer = document.getElementById('error-container') || createErrorContainer();
    errorContainer.innerHTML = `<i class="fas fa-exclamation-circle"></i> ${mensaje}`;
    errorContainer.style.display = 'block';
}

function createErrorContainer() {
    const errorContainer = document.createElement('div');
    errorContainer.id = 'error-container';
    errorContainer.className = 'error-message';
    document.querySelector('.modal-body')?.insertBefore(errorContainer, document.querySelector('.modal-body').firstChild);
    return errorContainer;
}

async function mostrarModalTabulador(id, tipo = null) {
    const modal = document.getElementById('modalTabulador');
    const titulo = document.getElementById('tituloModalTabulador');
    const form = document.getElementById('formTabulador');

    const errorContainer = document.getElementById('error-container');
    if (errorContainer) errorContainer.remove();

    form.reset(); 
    document.getElementById('activo').checked = true; 

    if (id) {
        titulo.textContent = 'Editar Tabulador';
        document.getElementById('tabulador_id').value = id;

        try {
            const loader = document.createElement('div');
            loader.className = 'modal-loader';
            loader.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Cargando datos...';
            modal.querySelector('.modal-body').prepend(loader);

            const response = await fetch(`../../php/configuracion/obtener_tabulador.php?id=${id}`);
            if (!response.ok) throw new Error('Error en la respuesta del servidor');

            const data = await response.json();

            if (data.status === 1 && data.tabuladores && data.tabuladores.length > 0) {
                const tabulador = data.tabuladores[0];
                document.getElementById('rango_min').value = parseFloat(tabulador.rango_min).toFixed(2);
                document.getElementById('rango_max').value = parseFloat(tabulador.rango_max).toFixed(2);
                document.getElementById('valor').value = parseFloat(tabulador.valor).toFixed(2);
                document.getElementById('descripcion').value = tabulador.descripcion || '';
                document.getElementById('activo').checked = tabulador.activo == 1;
                document.getElementById('tipo').value = tabulador.tipo || 'precio_instalacion';
            } else {
                throw new Error(data.mensaje || 'No se encontraron datos del tabulador');
            }
        } catch (error) {
            console.error('Error:', error);
            mostrarErrorEnModal('Error al cargar datos: ' + error.message);
        } finally {
            const loader = modal.querySelector('.modal-loader');
            if (loader) loader.remove();
        }
    } else {
        titulo.textContent = 'Nuevo Tabulador';
        document.getElementById('tabulador_id').value = '';
    }

    modal.style.display = 'block';
    document.body.style.overflow = 'hidden';
}

function editarTabulador(id) {
    console.log("Consultando tabulador con ID:", id);
    mostrarModalTabulador(id);
}

function eliminarTabulador(id) {
    if (!confirm('¿Está seguro que desea eliminar este rango de precio?')) return;

    displayPopUp("Eliminando rango...");

    fetch(`../../php/configuracion/eliminar_tabulador.php?id=${id}`)
        .then(response => response.json())
        .then(data => {
            if (data.status === 1) {
                displayMensajeExitoso(data.mensaje);
                setTimeout(() => location.reload(), 1000);
            } else {
                throw new Error(data.mensaje);
            }
        })
        .catch(error => {
            displayMensajeError(error.message);
        });
}

window.onclick = function (event) {
    if (event.target === document.getElementById('modalTabulador')) {
        cerrarModalTabulador();
    }
};