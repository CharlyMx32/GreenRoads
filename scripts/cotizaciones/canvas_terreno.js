// Script para manejar el canvas de dibujo del terreno
class CanvasTerreno {
    constructor() {
        this.canvas = document.getElementById('canvas_terreno');
        this.ctx = this.canvas.getContext('2d');
        this.isDrawing = false;
        this.mode = 'libre'; // 'libre', 'rectangulo', 'triangulo', 'circulo', 'texto', 'eraser'
        this.startX = 0;
        this.startY = 0;
        this.lastX = 0;
        this.lastY = 0;
        this.savedImageData = null; // Para guardar el estado antes de preview
        
        // Configuración para modo texto
        this.fontSize = 16;
        this.fontFamily = 'Arial';
        this.textInputActive = false; // Para evitar doble input
        
        // Configuración de colores
        this.currentColor = '#2c5530'; // Verde oscuro (default)
        this.colors = {
            green: '#2c5530',
            black: '#000000',
            red: '#dc3545',
            blue: '#007bff',
            gray: '#6c757d'
        };
        
        // Sistema de historial (undo)
        this.history = [];
        this.maxHistory = 20; // Máximo 20 acciones guardadas
        
        this.initCanvas();
        this.bindEvents();
        this.setupResponsive();
        this.createTextInput(); // Crear input flotante para texto
        this.saveState(); // Guardar estado inicial
    }

    initCanvas() {
        // Configuración inicial del canvas
        this.ctx.lineCap = 'round';
        this.ctx.lineJoin = 'round';
        this.ctx.strokeStyle = this.currentColor;
        this.ctx.lineWidth = 2;
        this.ctx.fillStyle = `${this.currentColor}1a`; // Color con transparencia
        
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

        // Eventos de botones de control - herramientas
        document.getElementById('btn_limpiar_canvas').addEventListener('click', this.clearCanvas.bind(this));
        document.getElementById('btn_rectangulo').addEventListener('click', () => this.setMode('rectangulo'));
        document.getElementById('btn_triangulo').addEventListener('click', () => this.setMode('triangulo'));
        document.getElementById('btn_circulo').addEventListener('click', () => this.setMode('circulo'));
        document.getElementById('btn_dibujo_libre').addEventListener('click', () => this.setMode('libre'));
        document.getElementById('btn_texto').addEventListener('click', () => this.setMode('texto'));
        
        // Eventos de botones nuevos
        const btnUndo = document.getElementById('btn_undo');
        const btnEraser = document.getElementById('btn_eraser');
        
        if (btnUndo) {
            btnUndo.addEventListener('click', this.undo.bind(this));
        }
        
        if (btnEraser) {
            btnEraser.addEventListener('click', () => this.setMode('eraser'));
        }
        
        // Eventos de selector de color
        const colorButtons = document.querySelectorAll('.color-btn');
        colorButtons.forEach(btn => {
            btn.addEventListener('click', (e) => {
                const color = e.currentTarget.dataset.color;
                this.setColor(color);
            });
        });
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
        const pos = this.getMousePos(e);
        
        // Modo texto: mostrar input flotante
        if (this.mode === 'texto') {
            if (!this.textInputActive) {
                this.showTextInput(e.clientX, e.clientY, pos.x, pos.y);
            }
            return;
        }
        
        this.isDrawing = true;
        this.startX = pos.x;
        this.startY = pos.y;
        this.lastX = pos.x;
        this.lastY = pos.y;

        if (this.mode === 'libre' || this.mode === 'eraser') {
            this.ctx.beginPath();
            this.ctx.moveTo(pos.x, pos.y);
            
            // Configurar modo borrador
            if (this.mode === 'eraser') {
                this.ctx.globalCompositeOperation = 'destination-out';
                this.ctx.lineWidth = 10; // Grosor más grande para borrar
            } else {
                this.ctx.globalCompositeOperation = 'source-over';
                this.ctx.lineWidth = 2;
            }
        } else if (this.mode === 'rectangulo' || this.mode === 'triangulo' || this.mode === 'circulo') {
            // Guardar estado actual antes de empezar el preview
            this.savedImageData = this.ctx.getImageData(0, 0, this.canvas.width, this.canvas.height);
        }
    }

    draw(e) {
        if (!this.isDrawing) return;

        const pos = this.getMousePos(e);

        if (this.mode === 'libre' || this.mode === 'eraser') {
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
        
        // Restaurar modo normal si estaba en eraser
        if (this.mode === 'eraser') {
            this.ctx.globalCompositeOperation = 'source-over';
            this.ctx.lineWidth = 2;
        }
        
        this.savedImageData = null;
        
        // Guardar estado en historial después de completar la acción
        this.saveState();
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

    addText(x, y) {
        // Crear un prompt personalizado para el texto
        const texto = prompt('Ingrese el texto que desea agregar:', '');
        
        if (texto && texto.trim() !== '') {
            // Configurar estilo de texto
            this.ctx.font = `${this.fontSize}px ${this.fontFamily}`;
            this.ctx.fillStyle = '#2c5530';
            this.ctx.textBaseline = 'top';
            
            // Dibujar el texto
            this.ctx.fillText(texto.trim(), x, y);
            
            // Opcional: agregar un borde al texto para mejor visibilidad
            this.ctx.strokeStyle = '#2c5530';
            this.ctx.lineWidth = 0.5;
            this.ctx.strokeText(texto.trim(), x, y);
            
            // Restaurar configuración
            this.ctx.lineWidth = 2;
        }
    }

    createTextInput() {
        // Crear el input flotante para texto
        this.textInput = document.createElement('input');
        this.textInput.type = 'text';
        this.textInput.className = 'canvas-text-input';
        this.textInput.style.cssText = `
            position: fixed;
            display: none;
            padding: 8px 12px;
            border: 2px solid #7dc042;
            border-radius: 5px;
            font-size: 14px;
            font-family: Arial;
            background: white;
            box-shadow: 0 2px 8px rgba(0,0,0,0.2);
            z-index: 10000;
            min-width: 200px;
            max-width: 400px;
            width: auto;
        `;
        this.textInput.placeholder = 'Escribe aquí y presiona Enter...';
        document.body.appendChild(this.textInput);

        // Eventos del input
        this.textInput.addEventListener('keydown', (e) => {
            if (e.key === 'Enter') {
                e.preventDefault();
                e.stopPropagation();
                this.confirmText();
            } else if (e.key === 'Escape') {
                e.preventDefault();
                e.stopPropagation();
                this.cancelText();
            }
        });

        // Evitar que el blur cierre inmediatamente
        this.textInput.addEventListener('mousedown', (e) => {
            e.stopPropagation();
        });
        
        this.textInput.addEventListener('click', (e) => {
            e.stopPropagation();
        });
    }

    showTextInput(clientX, clientY, canvasX, canvasY) {
        this.textInputActive = true;
        this.pendingTextX = canvasX;
        this.pendingTextY = canvasY;
        
        // Mostrar el input primero para obtener sus dimensiones
        this.textInput.style.display = 'block';
        this.textInput.value = '';
        
        // Calcular posición para que no se salga de la pantalla
        let left = clientX + 10;
        let top = clientY - 20;
        
        // Ajustar si se sale por la derecha
        const inputWidth = 400; // max-width del input
        if (left + inputWidth > window.innerWidth) {
            left = window.innerWidth - inputWidth - 20;
        }
        
        // Ajustar si se sale por abajo
        if (top + 50 > window.innerHeight) {
            top = clientY - 50;
        }
        
        // Ajustar si se sale por arriba
        if (top < 10) {
            top = 10;
        }
        
        // Ajustar si se sale por la izquierda
        if (left < 10) {
            left = 10;
        }
        
        this.textInput.style.left = left + 'px';
        this.textInput.style.top = top + 'px';
        this.textInput.focus();
        
        // Agregar listener para cerrar al hacer clic fuera
        setTimeout(() => {
            document.addEventListener('click', this.handleOutsideClick.bind(this), { once: true });
        }, 100);
    }
    
    handleOutsideClick(e) {
        // Si el clic fue fuera del input, cancelar
        if (this.textInputActive && e.target !== this.textInput) {
            this.cancelText();
        }
    }

    confirmText() {
        const texto = this.textInput.value.trim();
        
        // Cerrar el input inmediatamente
        this.hideTextInput();
        
        if (texto !== '') {
            try {
                // Configurar estilo de texto más fino
                this.ctx.font = `${this.fontSize}px ${this.fontFamily}`;
                this.ctx.fillStyle = this.currentColor;
                this.ctx.textBaseline = 'top';
                
                // Dibujar el texto sin borde (para que sea más fino)
                this.ctx.fillText(texto, this.pendingTextX, this.pendingTextY);
                
                // Guardar estado
                this.saveState();
            } catch (error) {
                console.error('Error al agregar texto:', error);
                alert('Error al agregar texto. Por favor intenta de nuevo.');
            }
        }
    }

    cancelText() {
        this.hideTextInput();
    }

    hideTextInput() {
        this.textInput.style.display = 'none';
        this.textInput.value = '';
        this.textInputActive = false;
        
        // Remover listener de clic fuera si existe
        document.removeEventListener('click', this.handleOutsideClick.bind(this));
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
        this.saveState();
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
            { id: 'btn_dibujo_libre', mode: 'libre' },
            { id: 'btn_texto', mode: 'texto' },
            { id: 'btn_eraser', mode: 'eraser' }
        ];

        buttons.forEach(btn => {
            const element = document.getElementById(btn.id);
            if (element) {
                if (btn.mode === mode) {
                    element.classList.add('active');
                } else {
                    element.classList.remove('active');
                }
            }
        });

        // Actualizar cursor del canvas según el modo
        this.updateCursor();
    }
    
    updateCursor() {
        // Cambiar cursor del canvas según el modo
        const cursors = {
            'libre': 'crosshair',
            'texto': 'text',
            'eraser': 'pointer',
            'rectangulo': 'crosshair',
            'triangulo': 'crosshair',
            'circulo': 'crosshair'
        };
        
        this.canvas.style.cursor = cursors[this.mode] || 'default';
    }
    
    setColor(colorName) {
        if (this.colors[colorName]) {
            this.currentColor = this.colors[colorName];
            this.ctx.strokeStyle = this.currentColor;
            this.ctx.fillStyle = `${this.currentColor}1a`; // Con transparencia
            
            // Actualizar botones de color
            document.querySelectorAll('.color-btn').forEach(btn => {
                btn.classList.remove('active');
            });
            
            const activeBtn = document.querySelector(`[data-color="${colorName}"]`);
            if (activeBtn) {
                activeBtn.classList.add('active');
            }
        }
    }
    
    // Sistema de historial (Undo)
    saveState() {
        try {
            // Guardar estado actual del canvas
            const state = this.ctx.getImageData(0, 0, this.canvas.width, this.canvas.height);
            this.history.push(state);
            
            // Limitar historial a máximo definido
            if (this.history.length > this.maxHistory) {
                this.history.shift(); // Eliminar el más antiguo
            }
            
            // Actualizar estado del botón undo
            this.updateUndoButton();
        } catch (error) {
            console.error('Error al guardar estado:', error);
        }
    }
    
    undo() {
        try {
            if (this.history.length > 1) {
                // Remover estado actual
                this.history.pop();
                
                // Obtener estado anterior
                const previousState = this.history[this.history.length - 1];
                
                // Restaurar estado anterior
                this.ctx.putImageData(previousState, 0, 0);
                
                // Actualizar botón
                this.updateUndoButton();
            }
        } catch (error) {
            console.error('Error al deshacer:', error);
        }
    }
    
    updateUndoButton() {
        const btnUndo = document.getElementById('btn_undo');
        if (btnUndo) {
            if (this.history.length <= 1) {
                btnUndo.disabled = true;
                btnUndo.style.opacity = '0.5';
                btnUndo.style.cursor = 'not-allowed';
            } else {
                btnUndo.disabled = false;
                btnUndo.style.opacity = '1';
                btnUndo.style.cursor = 'pointer';
            }
        }
    }

    handleTouch(e) {
        e.preventDefault();
        
        // En modo texto, no simular eventos de mouse para evitar duplicados
        if (this.mode === 'texto' && e.type === 'touchstart') {
            const rect = this.canvas.getBoundingClientRect();
            const scaleX = this.canvas.width / rect.width;
            const scaleY = this.canvas.height / rect.height;
            const touch = e.touches[0];
            const canvasX = (touch.clientX - rect.left) * scaleX;
            const canvasY = (touch.clientY - rect.top) * scaleY;
            
            if (!this.textInputActive) {
                this.showTextInput(touch.clientX, touch.clientY, canvasX, canvasY);
            }
            return;
        }
        
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
