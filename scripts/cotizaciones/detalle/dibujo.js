// ===== FUNCIONES PARA VER DIBUJO =====
let dibujoCanvasVer = null;
let dibujoCtxVer = null;

function verDibujoTerreno() {
    const modal = document.getElementById('modalVerDibujo');
    
    if (modal && window.cotizacionData && window.cotizacionData.dibujo_terreno) {
        modal.style.display = 'block';

        // Inicializar canvas después de mostrar el modal
        setTimeout(() => {
            inicializarCanvasVisualizacion();
            cargarDibujoEnCanvas(window.cotizacionData.dibujo_terreno);
        }, 100);
    } else {
        alert('No hay dibujo de terreno disponible');
    }
}

function cerrarModalVerDibujo() {
    const modal = document.getElementById('modalVerDibujo');
    if (modal) {
        modal.style.display = 'none';
    }
}

function inicializarCanvasVisualizacion() {
    dibujoCanvasVer = document.getElementById('canvasVisualizacion');
    
    if (dibujoCanvasVer) {
        dibujoCtxVer = dibujoCanvasVer.getContext('2d');
        dibujoCtxVer.fillStyle = 'white';
        dibujoCtxVer.fillRect(0, 0, dibujoCanvasVer.width, dibujoCanvasVer.height);
    }
}

function cargarDibujoEnCanvas(dibujoData) {
    if (!dibujoCtxVer || !dibujoData) {
        return;
    }

    try {
        const imagen = new Image();
        imagen.onload = function() {
            dibujoCtxVer.clearRect(0, 0, dibujoCanvasVer.width, dibujoCanvasVer.height);
            dibujoCtxVer.drawImage(imagen, 0, 0, dibujoCanvasVer.width, dibujoCanvasVer.height);
        };
        imagen.onerror = function() {
            console.error('Error al cargar la imagen');
        };
        
        // El dibujo ya viene como data URI completo, no necesita prefijo
        if (dibujoData.startsWith('data:')) {
            imagen.src = dibujoData;
        } else {
            // Si no tiene prefijo, asumimos que es base64
            imagen.src = 'data:image/png;base64,' + dibujoData;
        }
    } catch (error) {
        console.error('Error al cargar el dibujo:', error);
    }
}
