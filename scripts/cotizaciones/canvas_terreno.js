// Script para manejar el canvas de dibujo del terreno
class CanvasTerreno {
    constructor() {
        this.canvas = document.getElementById('canvas_terreno');
        this.ctx = this.canvas.getContext('2d');
        this.isDrawing = false;
        this.mode = 'libre'; // 'libre', 'rectangulo', 'triangulo', 'circulo'
        this.startX = 0;
        this.startY = 0;
        this.lastX = 0;
        this.lastY = 0;
        this.savedImageData = null; // Para guardar el estado antes de preview
        
        this.initCanvas();
        this.bindEvents();
        this.setupResponsive();
    }

    initCanvas() {
        // Configuración inicial del canvas
        this.ctx.lineCap = 'round';
        this.ctx.lineJoin = 'round';
        this.ctx.strokeStyle = '#2c5530';
        this.ctx.lineWidth = 2;
        this.ctx.fillStyle = 'rgba(44, 85, 48, 0.1)';
        
        // Fondo blanco con una cuadrícula sutil
        this.drawGrid();
    }

    drawGrid() {
        const gridSize = 20;
        this.ctx.save();
        
        // Fondo blanco
        this.ctx.fillStyle = '#ffffff';
        this.ctx.fillRect(0, 0, this.canvas.width, this.canvas.height);
        
        // Cuadrícula sutil
        this.ctx.strokeStyle = '#f0f0f0';
        this.ctx.lineWidth = 0.5;
        
        for (let x = 0; x <= this.canvas.width; x += gridSize) {
            this.ctx.beginPath();
            this.ctx.moveTo(x, 0);
            this.ctx.lineTo(x, this.canvas.height);
            this.ctx.stroke();
        }
        
        for (let y = 0; y <= this.canvas.height; y += gridSize) {
            this.ctx.beginPath();
            this.ctx.moveTo(0, y);
            this.ctx.lineTo(this.canvas.width, y);
            this.ctx.stroke();
        }
        
        this.ctx.restore();
        
        // Restaurar configuración de dibujo
        this.ctx.strokeStyle = '#2c5530';
        this.ctx.lineWidth = 2;
        this.ctx.fillStyle = 'rgba(44, 85, 48, 0.1)';
    }

    setupResponsive() {
        // Hacer el canvas responsivo
        const resizeCanvas = () => {
            const container = this.canvas.parentElement;
            if (!container) return;
            
            const containerWidth = container.clientWidth - 40; // padding
            const containerHeight = container.clientHeight - 40;
            const aspectRatio = this.canvas.height / this.canvas.width;
            
            // Calcular tamaño manteniendo aspecto ratio
            let newWidth = containerWidth;
            let newHeight = newWidth * aspectRatio;
            
            // Si la altura calculada es mayor al contenedor, ajustar por altura
            if (newHeight > containerHeight) {
                newHeight = containerHeight;
                newWidth = newHeight / aspectRatio;
            }
            
            // Tamaños mínimos
            newWidth = Math.max(newWidth, 300);
            newHeight = Math.max(newHeight, 200);
            
            this.canvas.style.width = newWidth + 'px';
            this.canvas.style.height = newHeight + 'px';
        };
        
        window.addEventListener('resize', resizeCanvas);
        resizeCanvas();
    }

    // Método público para redimensionar el canvas
    resize() {
        const container = this.canvas.parentElement;
        if (!container) return;
        
        const containerWidth = container.clientWidth - 40; // padding
        const containerHeight = container.clientHeight - 40;
        const aspectRatio = this.canvas.height / this.canvas.width;
        
        // Calcular tamaño manteniendo aspecto ratio
        let newWidth = containerWidth;
        let newHeight = newWidth * aspectRatio;
        
        // Si la altura calculada es mayor al contenedor, ajustar por altura
        if (newHeight > containerHeight) {
            newHeight = containerHeight;
            newWidth = newHeight / aspectRatio;
        }
        
        // Tamaños mínimos
        newWidth = Math.max(newWidth, 300);
        newHeight = Math.max(newHeight, 200);
        
        this.canvas.style.width = newWidth + 'px';
        this.canvas.style.height = newHeight + 'px';
    }

    bindEvents() {
        // Eventos del canvas
        this.canvas.addEventListener('mousedown', this.startDrawing.bind(this));
        this.canvas.addEventListener('mousemove', this.draw.bind(this));
        this.canvas.addEventListener('mouseup', this.stopDrawing.bind(this));
        this.canvas.addEventListener('mouseout', this.stopDrawing.bind(this));

        // Eventos táctiles para dispositivos móviles
        this.canvas.addEventListener('touchstart', this.handleTouch.bind(this));
        this.canvas.addEventListener('touchmove', this.handleTouch.bind(this));
        this.canvas.addEventListener('touchend', this.stopDrawing.bind(this));

        // Eventos de botones de control
        document.getElementById('btn_limpiar_canvas').addEventListener('click', this.clearCanvas.bind(this));
        document.getElementById('btn_rectangulo').addEventListener('click', () => this.setMode('rectangulo'));
        document.getElementById('btn_triangulo').addEventListener('click', () => this.setMode('triangulo'));
        document.getElementById('btn_circulo').addEventListener('click', () => this.setMode('circulo'));
        document.getElementById('btn_dibujo_libre').addEventListener('click', () => this.setMode('libre'));
    }

    getMousePos(e) {
        const rect = this.canvas.getBoundingClientRect();
        const scaleX = this.canvas.width / rect.width;
        const scaleY = this.canvas.height / rect.height;
        
        return {
            x: (e.clientX - rect.left) * scaleX,
            y: (e.clientY - rect.top) * scaleY
        };
    }

    getTouchPos(e) {
        const rect = this.canvas.getBoundingClientRect();
        const scaleX = this.canvas.width / rect.width;
        const scaleY = this.canvas.height / rect.height;
        return {
            x: (e.touches[0].clientX - rect.left) * scaleX,
            y: (e.touches[0].clientY - rect.top) * scaleY
        };
    }

    startDrawing(e) {
        this.isDrawing = true;
        const pos = this.getMousePos(e);
        this.startX = pos.x;
        this.startY = pos.y;
        this.lastX = pos.x;
        this.lastY = pos.y;

        if (this.mode === 'libre') {
            this.ctx.beginPath();
            this.ctx.moveTo(pos.x, pos.y);
        } else if (this.mode === 'rectangulo' || this.mode === 'triangulo' || this.mode === 'circulo') {
            // Guardar estado actual antes de empezar el preview
            this.savedImageData = this.ctx.getImageData(0, 0, this.canvas.width, this.canvas.height);
        }
    }

    draw(e) {
        if (!this.isDrawing) return;

        const pos = this.getMousePos(e);

        if (this.mode === 'libre') {
            this.ctx.lineTo(pos.x, pos.y);
            this.ctx.stroke();
            this.ctx.beginPath();
            this.ctx.moveTo(pos.x, pos.y);
        } else if (this.mode === 'rectangulo' || this.mode === 'triangulo' || this.mode === 'circulo') {
            // Restaurar imagen guardada antes del preview
            if (this.savedImageData) {
                this.ctx.putImageData(this.savedImageData, 0, 0);
            }
            // Dibujar preview
            this.drawPreview(this.startX, this.startY, pos.x, pos.y);
        }

        this.lastX = pos.x;
        this.lastY = pos.y;
    }

    stopDrawing(e) {
        if (!this.isDrawing) return;
        this.isDrawing = false;

        if (this.mode === 'rectangulo' || this.mode === 'triangulo' || this.mode === 'circulo') {
            // Restaurar imagen guardada
            if (this.savedImageData) {
                this.ctx.putImageData(this.savedImageData, 0, 0);
            }
            const pos = this.getMousePos(e);
            this.drawShape(this.startX, this.startY, pos.x, pos.y);
        }
        
        this.savedImageData = null;
    }

    drawPreview(startX, startY, endX, endY) {
        this.ctx.strokeStyle = '#2c5530';
        this.ctx.setLineDash([5, 5]); // Línea punteada para preview
        
        if (this.mode === 'rectangulo') {
            const width = endX - startX;
            const height = endY - startY;
            this.ctx.strokeRect(startX, startY, width, height);
        } else if (this.mode === 'triangulo') {
            // Triángulo: punto inicial arriba, base abajo
            const centerX = (startX + endX) / 2;
            this.ctx.beginPath();
            this.ctx.moveTo(centerX, startY); // Punto superior (centro)
            this.ctx.lineTo(startX, endY);    // Punto inferior izquierdo
            this.ctx.lineTo(endX, endY);      // Punto inferior derecho
            this.ctx.closePath();
            this.ctx.stroke();
        } else if (this.mode === 'circulo') {
            const radius = Math.sqrt(Math.pow(endX - startX, 2) + Math.pow(endY - startY, 2));
            this.ctx.beginPath();
            this.ctx.arc(startX, startY, radius, 0, 2 * Math.PI);
            this.ctx.stroke();
        }
        
        this.ctx.setLineDash([]); // Restaurar línea sólida
    }

    drawShape(startX, startY, endX, endY) {
        this.ctx.strokeStyle = '#2c5530';
        this.ctx.setLineDash([]);
        
        if (this.mode === 'rectangulo') {
            const width = endX - startX;
            const height = endY - startY;
            this.ctx.strokeRect(startX, startY, width, height);
            this.ctx.fillRect(startX, startY, width, height);
        } else if (this.mode === 'triangulo') {
            // Triángulo: punto inicial arriba, base abajo
            const centerX = (startX + endX) / 2;
            this.ctx.beginPath();
            this.ctx.moveTo(centerX, startY); // Punto superior (centro)
            this.ctx.lineTo(startX, endY);    // Punto inferior izquierdo
            this.ctx.lineTo(endX, endY);      // Punto inferior derecho
            this.ctx.closePath();
            this.ctx.fill();
            this.ctx.stroke();
        } else if (this.mode === 'circulo') {
            const radius = Math.sqrt(Math.pow(endX - startX, 2) + Math.pow(endY - startY, 2));
            this.ctx.beginPath();
            this.ctx.arc(startX, startY, radius, 0, 2 * Math.PI);
            this.ctx.fill();
            this.ctx.stroke();
        }
    }

    redrawCanvas() {
        // Guardar el contenido actual
        const imageData = this.ctx.getImageData(0, 0, this.canvas.width, this.canvas.height);
        
        // Limpiar canvas
        this.ctx.clearRect(0, 0, this.canvas.width, this.canvas.height);
        this.ctx.fillStyle = '#ffffff';
        this.ctx.fillRect(0, 0, this.canvas.width, this.canvas.height);
        
        // Restaurar contenido
        this.ctx.putImageData(imageData, 0, 0);
        this.ctx.fillStyle = 'rgba(44, 85, 48, 0.1)';
    }

    clearCanvas() {
        this.ctx.clearRect(0, 0, this.canvas.width, this.canvas.height);
        this.drawGrid();
    }

    // Función para obtener el dibujo como base64
    getCanvasAsBase64() {
        return this.canvas.toDataURL('image/png');
    }

    // Función para cargar un dibujo desde base64
    loadCanvasFromBase64(base64Data) {
        const img = new Image();
        img.onload = () => {
            this.clearCanvas();
            this.ctx.drawImage(img, 0, 0);
        };
        img.src = base64Data;
    }

    // Función para verificar si el canvas tiene contenido
    hasContent() {
        const imageData = this.ctx.getImageData(0, 0, this.canvas.width, this.canvas.height);
        const pixels = imageData.data;
        
        // Verificar si hay píxeles que no sean transparentes o del fondo blanco/cuadrícula
        for (let i = 0; i < pixels.length; i += 4) {
            const r = pixels[i];
            const g = pixels[i + 1];
            const b = pixels[i + 2];
            const a = pixels[i + 3];
            
            // Si encuentra un píxel que no sea blanco o transparente (ignorando la cuadrícula)
            if (a > 0 && !(r === 255 && g === 255 && b === 255) && !(r === 240 && g === 240 && b === 240)) {
                return true;
            }
        }
        return false;
    }

    setMode(mode) {
        this.mode = mode;
        
        // Actualizar estilos de botones para mostrar el modo activo
        const buttons = [
            { id: 'btn_rectangulo', mode: 'rectangulo' },
            { id: 'btn_triangulo', mode: 'triangulo' },
            { id: 'btn_circulo', mode: 'circulo' },
            { id: 'btn_dibujo_libre', mode: 'libre' }
        ];

        buttons.forEach(btn => {
            const element = document.getElementById(btn.id);
            if (btn.mode === mode) {
                element.classList.add('active');
            } else {
                element.classList.remove('active');
            }
        });

        // Cambiar cursor del canvas según el modo
        if (mode === 'libre') {
            this.canvas.style.cursor = 'crosshair';
        } else {
            this.canvas.style.cursor = 'pointer';
        }
    }

    handleTouch(e) {
        e.preventDefault();
        const touch = e.touches[0];
        const mouseEvent = new MouseEvent(e.type === 'touchstart' ? 'mousedown' : 
                                        e.type === 'touchmove' ? 'mousemove' : 'mouseup', {
            clientX: touch.clientX,
            clientY: touch.clientY
        });
        this.canvas.dispatchEvent(mouseEvent);
    }
}

// Inicializar el canvas cuando el DOM esté listo
document.addEventListener('DOMContentLoaded', function() {
    // Hacer el canvas accesible globalmente
    window.canvasTerreno = new CanvasTerreno();
    
    // Configurar modo inicial
    window.canvasTerreno.setMode('libre');
});
