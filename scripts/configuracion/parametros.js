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
    const subtabsParametros = document.querySelectorAll('.subtabs .subtab');
    subtabsParametros.forEach(tab => {
        tab.addEventListener('click', () => {
            const subtabsContainer = tab.closest('.subtabs');
            subtabsContainer.querySelectorAll('.subtab').forEach(t => t.classList.remove('active'));

            tab.classList.add('active');
            const subtabId = tab.getAttribute('data-subtab');

            const subtabContent = tab.closest('.subtabs-container').nextElementSibling;
            subtabContent.querySelectorAll('.subtab-content').forEach(c => c.classList.remove('active'));
            document.getElementById(subtabId).classList.add('active');
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

            const tabla = document.querySelector('#tabuladores .tabla-lista');
            if (tabla) {
                tabla.querySelectorAll('tbody tr').forEach(row => {
                    if (row.getAttribute('data-tipo') === tipo) {
                        row.classList.remove('hidden');
                    } else {
                        row.classList.add('hidden');
                    }
                });
            }
        });
    });

    // Manejar envío de formularios de parámetros
    document.querySelectorAll('.parametro-card form').forEach(form => {
        form.addEventListener('submit', function (e) {
            e.preventDefault();

            const btnSubmit = this.querySelector('button[type="submit"]');
            if (btnSubmit) {
                btnSubmit.disabled = true;
                btnSubmit.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Guardando...';
            }

            const formData = new FormData(this);

            fetch('../../php/configuracion/parametros.php?t=' + Date.now(), {
                method: 'POST',
                body: formData
            })
                .then(response => {
                    if (!response.ok) throw new Error('Error en la respuesta del servidor');
                    return response.json();
                })
                .then(data => {
                    if (data.status == 0) {
                        throw new Error(data.mensaje || "Error al actualizar el parámetro");
                    }
                    displayMensajeExitoso(data.mensaje || "Parámetro actualizado correctamente");

                    // Actualizar la fecha de modificación
                    const fechaElement = this.querySelector('.parametro-info');
                    if (fechaElement) {
                        fechaElement.textContent = 'Últ. actualización: ' + new Date().toLocaleDateString('es-MX', {
                            day: '2-digit',
                            month: '2-digit',
                            year: 'numeric',
                            hour: '2-digit',
                            minute: '2-digit'
                        });
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    displayMensajeError('Error al actualizar el parámetro: ' + error.message);
                })
                .finally(() => {
                    if (btnSubmit) {
                        btnSubmit.disabled = false;
                        btnSubmit.innerHTML = '<i class="fas fa-save"></i> Guardar';
                    }
                });
        });
    });
});
