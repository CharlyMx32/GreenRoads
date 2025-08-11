// Script para manejar el modal del canvas
document.addEventListener('DOMContentLoaded', function() {
    const btnAbrirCanvas = document.getElementById('btn_abrir_canvas');
    const btnCerrarCanvas = document.getElementById('btn_cerrar_canvas');
    const btnGuardarCanvas = document.getElementById('btn_guardar_canvas');
    const btnCancelarCanvas = document.getElementById('btn_cancelar_canvas');
    const modal = document.getElementById('canvasModal');
    const canvasPreview = document.getElementById('canvas_preview');
    const canvasPreviewSmall = document.getElementById('canvas_preview_small');
    const canvasTerreno = document.getElementById('canvas_terreno');

    let canvasDataGuardado = null;

    // Abrir modal
    if (btnAbrirCanvas) {
        btnAbrirCanvas.addEventListener('click', function() {
            modal.style.display = 'block';
            // Si hay datos guardados, cargarlos
            if (canvasDataGuardado) {
                cargarDatosCanvas(canvasTerreno, canvasDataGuardado);
            }
            // Reinicializar el canvas si es necesario
            if (window.canvasTerreno) {
                window.canvasTerreno.resize();
            }
        });
    }

    // Cerrar modal (X)
    if (btnCerrarCanvas) {
        btnCerrarCanvas.addEventListener('click', cerrarModal);
    }

    // Cancelar
    if (btnCancelarCanvas) {
        btnCancelarCanvas.addEventListener('click', cerrarModal);
    }

    // Guardar diseño
    if (btnGuardarCanvas) {
        btnGuardarCanvas.addEventListener('click', function() {
            if (window.canvasTerreno && window.canvasTerreno.hasContent()) {
                canvasDataGuardado = window.canvasTerreno.getCanvasAsBase64();
                actualizarPreview();
                cerrarModal();
                
                // Mostrar mensaje de éxito
                alert('✅ Diseño guardado correctamente');
            } else {
                if (confirm('No hay contenido dibujado. ¿Desea guardar un canvas vacío?')) {
                    canvasDataGuardado = null;
                    ocultarPreview();
                    cerrarModal();
                }
            }
        });
    }

    // Cerrar modal al hacer clic fuera
    modal?.addEventListener('click', function(e) {
        if (e.target === modal) {
            cerrarModal();
        }
    });

    // Cerrar con Escape
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape' && modal.style.display === 'block') {
            cerrarModal();
        }
    });

    function cerrarModal() {
        modal.style.display = 'none';
    }

    function actualizarPreview() {
        if (canvasDataGuardado && canvasPreviewSmall) {
            const ctx = canvasPreviewSmall.getContext('2d');
            const img = new Image();
            img.onload = function() {
                ctx.clearRect(0, 0, canvasPreviewSmall.width, canvasPreviewSmall.height);
                ctx.drawImage(img, 0, 0, canvasPreviewSmall.width, canvasPreviewSmall.height);
            };
            img.src = canvasDataGuardado;
            canvasPreview.style.display = 'block';
        }
    }

    function ocultarPreview() {
        canvasPreview.style.display = 'none';
    }

    function cargarDatosCanvas(canvas, dataUrl) {
        if (canvas && dataUrl) {
            const ctx = canvas.getContext('2d');
            const img = new Image();
            img.onload = function() {
                ctx.clearRect(0, 0, canvas.width, canvas.height);
                ctx.drawImage(img, 0, 0);
            };
            img.src = dataUrl;
        }
    }

    // Exponer función para obtener datos del canvas
    window.obtenerDatosCanvas = function() {
        return canvasDataGuardado;
    };
});
