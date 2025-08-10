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
    // DESHABILITADO: Los formularios de parámetros usan el sistema PHP tradicional
    // document.querySelectorAll('.parametro-card form').forEach(form => {
    //     form.addEventListener('submit', function (e) {
    //         e.preventDefault();
    //         // ... resto del código AJAX comentado
    //     });
    // });
});
