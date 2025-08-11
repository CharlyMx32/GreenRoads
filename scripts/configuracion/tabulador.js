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

    // Manejar las pestañas de subtabs para tabuladores
    document.querySelectorAll('.subtab').forEach(tab => {
        tab.addEventListener('click', () => {
            // Solo para pestañas dentro de tabuladores
            if (tab.closest('#tabuladores')) {
                document.querySelectorAll('#tabuladores .subtab').forEach(t => t.classList.remove('active'));
                tab.classList.add('active');
                const tipo = tab.getAttribute('data-subtab');
                cargarTabuladores(tipo);
                mostrarFilasPorTipo(tipo);
            }
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

function mostrarFilasPorTipo(tipo) {
    const filas = document.querySelectorAll('.tabla-lista tbody tr');
    filas.forEach(fila => {
        const tipoFila = fila.getAttribute('data-tipo');
        if (tipoFila === tipo) {
            fila.style.display = '';
            fila.classList.remove('hidden');
        } else {
            fila.style.display = 'none';
            fila.classList.add('hidden');
        }
    });
}

function cargarTabuladores(tipo) {
    fetch(`../../php/configuracion/obtener_tabulador.php?tipo=${tipo}&t=${Date.now()}`, {
        method: 'GET',
        headers: {
            'Accept': 'application/json',
            'Content-Type': 'application/json'
        }
    })
        .then(async response => {
            const text = await response.text();
            
            try {
                const data = JSON.parse(text);
                return data;
            } catch (e) {
                console.error('Error parsing JSON:', e);
                console.error('Texto recibido:', text);
                throw new Error('El servidor devolvió una respuesta inválida al cargar tabuladores.');
            }
        })
        .then(data => {
            if (data.status === 1) {
                actualizarTablaTabuladores(data.tabuladores, tipo);
                mostrarFilasPorTipo(tipo);
            } else {
                throw new Error(data.mensaje || 'Error al cargar tabuladores');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            displayPopUp();
            displayMensajeError('Error al cargar tabuladores: ' + error.message, 'hidePopup()');
        });
}
function cerrarModalTabulador() {
    const modal = document.getElementById('modalTabulador');
    modal.style.display = 'none';
    document.body.style.overflow = 'auto';
    
    // Limpiar formulario
    const form = document.getElementById('formTabulador');
    if (form) {
        form.reset();
    }
    
    // Limpiar errores
    const errorContainer = document.getElementById('error-container');
    if (errorContainer) {
        errorContainer.remove();
    }
}

function actualizarTablaTabuladores(tabuladores, tipoActual = null) {
    const tbody = document.querySelector('.tabla-lista tbody');
    if (!tbody) return;

    if (tabuladores.length === 0) {
        tbody.innerHTML = '<tr><td colspan="7">No hay rangos configurados para este tipo</td></tr>';
        return;
    }

    const tabuladoresFiltrados = tipoActual 
        ? tabuladores.filter(t => t.tipo === tipoActual)
        : tabuladores;

    tbody.innerHTML = tabuladoresFiltrados.length === 0
        ? '<tr><td colspan="7">No hay rangos configurados para este tipo</td></tr>'
        : tabuladoresFiltrados.map(createTableRow).join('');
}

function createTableRow(tabulador) {
    let simbolo = '$';
    if (tabulador.tipo === 'descuento_volumen') {
        simbolo = ''; 
    }
    
    let valorFormateado = parseFloat(tabulador.valor || 0).toFixed(2);
    if (tabulador.tipo === 'descuento_volumen') {
        valorFormateado += '%';  
    } else {
        valorFormateado = simbolo + valorFormateado;
    }

    return `
        <tr data-id="${tabulador.id}" data-tipo="${tabulador.tipo}">
            <td>${parseFloat(tabulador.rango_min).toFixed(2)}</td>
            <td>${parseFloat(tabulador.rango_max).toFixed(2)}</td>
            <td>${valorFormateado}</td>
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
        const rangoMin = parseFloat(document.getElementById('rango_min').value);
        const rangoMax = parseFloat(document.getElementById('rango_max').value);
        const precioM2 = parseFloat(document.getElementById('valor').value);

        if (isNaN(rangoMin)) throw new Error('Rango mínimo inválido');
        if (isNaN(rangoMax)) throw new Error('Rango máximo inválido');
        if (isNaN(precioM2)) throw new Error('Precio inválido');
        if (rangoMin >= rangoMax) throw new Error('Rango mínimo debe ser menor al máximo');

        const data = {
            id: document.getElementById('tabulador_id').value || 0,
            rango_min: rangoMin,
            rango_max: rangoMax,
            valor: precioM2, 
            descripcion: document.getElementById('descripcion').value,
            activo: document.getElementById('activo').checked ? 1 : 0,
            tipo: document.getElementById('tipo').value
        };

        const response = await fetch(`../../php/configuracion/guardar_tabulador.php?t=${Date.now()}`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json'
            },
            body: JSON.stringify(data)
        });

        const text = await response.text();
        
        let result;
        try {
            result = JSON.parse(text);
        } catch (e) {
            console.error('Error parsing JSON:', e);
            console.error('Texto recibido:', text);
            throw new Error('El servidor devolvió una respuesta inválida al guardar.');
        }

        if (!response.ok || !result.status) {
            throw new Error(result.mensaje || 'Error al guardar');
        }

        cerrarModalTabulador();
        
        setTimeout(() => {
            displayPopUp();
            displayMensajeExitosoSinRecargar(result.mensaje || 'Cambios guardados correctamente', function() {
                const pestanaActiva = document.querySelector('#tabuladores .subtab.active');
                const tipo = pestanaActiva ? pestanaActiva.getAttribute('data-subtab') : 'precio_instalacion';
                cargarTabuladores(tipo);
            });
        }, 100);

    } catch (error) {
        console.error('Error:', error);
        displayPopUp();
        displayMensajeError(error.message, 'hidePopup()');
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

    if (!tipo) {
        const pestanaActiva = document.querySelector('#tabuladores .subtab.active');
        tipo = pestanaActiva ? pestanaActiva.getAttribute('data-subtab') : 'precio_instalacion';
    }

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
                document.getElementById('valor').value = parseFloat(tabulador.valor || 0).toFixed(2);
                document.getElementById('descripcion').value = tabulador.descripcion || '';
                document.getElementById('activo').checked = tabulador.activo == 1;
                document.getElementById('tipo').value = tabulador.tipo || 'precio_instalacion';
            } else {
                throw new Error(data.mensaje || 'No se encontraron datos del tabulador');
            }
        } catch (error) {
            console.error('Error:', error);
            displayPopUp();
            displayMensajeError('Error al cargar datos: ' + error.message, 'hidePopup()');
        } finally {
            const loader = modal.querySelector('.modal-loader');
            if (loader) loader.remove();
        }
    } else {
        titulo.textContent = 'Nuevo Tabulador';
        document.getElementById('tabulador_id').value = '';
        document.getElementById('tipo').value = tipo;
    }

    modal.style.display = 'block';
    document.body.style.overflow = 'hidden';
}

window.mostrarModalTabulador = mostrarModalTabulador;

function editarTabulador(id) {
    mostrarModalTabulador(id);
}

function eliminarTabulador(id) {
    if (!confirm('¿Está seguro que desea eliminar este rango de precio?')) return;

    displayPopUp("Eliminando rango...");

    fetch(`../../php/configuracion/eliminar_tabulador.php?id=${id}&t=${Date.now()}`, {
        method: 'GET',
        headers: {
            'Accept': 'application/json',
            'Content-Type': 'application/json'
        }
    })
        .then(async response => {
            const text = await response.text();
            
            try {
                const data = JSON.parse(text);
                return data;
            } catch (e) {
                console.error('Error parsing JSON:', e);
                console.error('Texto recibido:', text);
                throw new Error('El servidor devolvió una respuesta inválida. Por favor revise la consola del navegador.');
            }
        })
        .then(data => {
            if (data.status === 1) {
                displayPopUp();
                displayMensajeExitosoSinRecargar(data.mensaje, function() {
                    const pestanaActiva = document.querySelector('#tabuladores .subtab.active');
                    const tipo = pestanaActiva ? pestanaActiva.getAttribute('data-subtab') : 'precio_instalacion';
                    cargarTabuladores(tipo);
                });
            } else {
                throw new Error(data.mensaje || 'Error desconocido al eliminar el tabulador');
            }
        })
        .catch(error => {
            console.error('Error completo:', error);
            displayPopUp();
            displayMensajeError(error.message, 'hidePopup()');
        });
}

window.editarTabulador = editarTabulador;
window.eliminarTabulador = eliminarTabulador;
window.guardarTabulador = guardarTabulador;
window.cerrarModalTabulador = cerrarModalTabulador;

window.onclick = function (event) {
    if (event.target === document.getElementById('modalTabulador')) {
        cerrarModalTabulador();
    }
};