// ===== INICIALIZACIÓN Y EVENTOS =====
document.addEventListener('DOMContentLoaded', function() {
    console.log('Detalle de cotización cargado');
});

// ===== CERRAR MODALES CON ESCAPE =====
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        cerrarModalVerDibujo();
    }
});

// ===== CERRAR MODALES CLICANDO FUERA =====
window.onclick = function(event) {
    const modalVerDibujo = document.getElementById('modalVerDibujo');

    if (event.target === modalVerDibujo) {
        cerrarModalVerDibujo();
    }
};
