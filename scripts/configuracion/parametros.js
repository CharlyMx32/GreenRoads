document.addEventListener('DOMContentLoaded', function () {
    // Manejar tabs principales
    const tabsPrincipales = document.querySelectorAll('.tab-principal');
    if (tabsPrincipales.length > 0) {
        tabsPrincipales.forEach(tab => {
            tab.addEventListener('click', () => {
                document.querySelectorAll('.tab-principal').forEach(t => t.classList.remove('active'));
                document.querySelectorAll('.tab-principal-content').forEach(c => c.classList.remove('active'));

                tab.classList.add('active');
                const tabId = tab.getAttribute('data-tab');
                const content = document.getElementById(tabId);
                if (content) content.classList.add('active');
            });
        });
    }
    
    // Manejar subtabs de parámetros
    const subtabsParametros = document.querySelectorAll('#parametros .subtabs .subtab');
    subtabsParametros.forEach(tab => {
        tab.addEventListener('click', () => {
            const subtabsContainer = tab.closest('.subtabs');
            subtabsContainer.querySelectorAll('.subtab').forEach(t => t.classList.remove('active'));

            tab.classList.add('active');
            const subtabId = tab.getAttribute('data-subtab');

            const subtabContent = tab.closest('.subtabs-container').nextElementSibling;
            subtabContent.querySelectorAll('.subtab-content').forEach(c => c.classList.remove('active'));
            const targetContent = document.getElementById(subtabId);
            if (targetContent) targetContent.classList.add('active');
        });
    });

    // Manejar subtabs de tabuladores
    const subtabsTabuladores = document.querySelectorAll('#tabuladores .subtabs .subtab');
    subtabsTabuladores.forEach(tab => {
        tab.addEventListener('click', () => {
            const subtabsContainer = tab.closest('.subtabs');
            subtabsContainer.querySelectorAll('.subtab').forEach(t => t.classList.remove('active'));

            tab.classList.add('active');
            const tipo = tab.getAttribute('data-subtab');

            // Cargar tabuladores del tipo seleccionado
            if (typeof cargarTabuladores === 'function') {
                cargarTabuladores(tipo);
            }

            // Filtrar filas de la tabla
            const tabla = document.querySelector('#tabuladores .tabla-lista');
            if (tabla) {
                tabla.querySelectorAll('tbody tr').forEach(row => {
                    const tipoFila = row.getAttribute('data-tipo');
                    if (tipoFila === tipo) {
                        row.style.display = '';
                        row.classList.remove('hidden');
                    } else {
                        row.style.display = 'none';
                        row.classList.add('hidden');
                    }
                });
            }
        });
    });

    // Inicializar formularios de parámetros
    initParametrosForms();

    // Cargar tabuladores por defecto al inicializar
    if (document.getElementById('tabuladores')) {
        setTimeout(() => {
            if (typeof cargarTabuladores === 'function') {
                cargarTabuladores('precio_instalacion');
            }
        }, 100);
    }
});

function initParametrosForms() {
    // Solo manejar formularios de parámetros (no de tabuladores)
    const forms = document.querySelectorAll('#parametros .parametro-card form');
    
    forms.forEach((form, index) => {
        form.addEventListener('submit', function (e) {
            e.preventDefault();
            handleParametroFormSubmit(this);
        });
    });
}

function handleParametroFormSubmit(form) {
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
        .then(handleParametroResponse)
        .then(data => handleParametroSuccess(data, form))
        .catch(error => handleParametroError(error))
        .finally(() => {
            if (btnSubmit) {
                btnSubmit.disabled = false;
                btnSubmit.innerHTML = '<i class="fas fa-save"></i> Guardar';
            }
        });
}

function handleParametroResponse(response) {
    if (!response.ok) {
        // Intentar obtener el mensaje de error del servidor
        return response.text().then(text => {
            try {
                const errorData = JSON.parse(text);
                throw new Error(errorData.mensaje || `Error ${response.status}: ${response.statusText}`);
            } catch (parseError) {
                throw new Error(`Error ${response.status}: ${response.statusText}. Respuesta del servidor: ${text}`);
            }
        });
    }
    return response.json().catch(error => {
        throw new Error('El servidor devolvió una respuesta inválida');
    });
}

function handleParametroSuccess(data, form) {
    if (data.status == 0) {
        throw new Error(data.mensaje || "Error al actualizar el parámetro");
    }

    // Mostrar popup si existe la función global
    if (typeof displayPopUp === 'function') {
        displayPopUp();
        displayMensajeExitoso(data.mensaje, 'hidePopup()');
    } else {
        // Fallback si no hay popup
        alert(data.mensaje);
    }
    
    updateParametroLastModified(form);
}

function handleParametroError(error) {
    // Mostrar popup si existe la función global
    if (typeof displayPopUp === 'function') {
        displayPopUp();
        displayMensajeError(error.message, 'hidePopup()');
    } else {
        // Fallback si no hay popup
        alert('Error: ' + error.message);
    }
}

function updateParametroLastModified(form) {
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
