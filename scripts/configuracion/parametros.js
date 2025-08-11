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

    // Cargar tabuladores por defecto al inicializar
    if (document.getElementById('tabuladores')) {
        setTimeout(() => {
            if (typeof cargarTabuladores === 'function') {
                cargarTabuladores('precio_instalacion');
            }
        }, 100);
    }
});
