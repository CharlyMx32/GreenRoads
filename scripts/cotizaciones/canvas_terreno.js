// Script para manejar el canvas de dibujo del terreno
class CanvasTerreno {
    constructor() {
        this.canvas = document.getElementById('canvas_terreno');
        this.ctx = this.canvas.getContext('2d');
        this.isDrawing = false;
        this.mode = 'libre';
        this.startX = 0;
        this.startY = 0;
        this.lastX = 0;
        this.lastY = 0;
        this.savedImageData = null;
        this.fontSize = 16;
        this.fontFamily = 'Arial';
        this.textInputActive = false;

        this.currentColor = '#2c5530';
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
        this.createTextInput();
        this.saveState();
    }

    initCanvas() {
        this.ctx.lineCap = 'round';
        this.ctx.lineJoin = 'round';
        this.ctx.strokeStyle = this.currentColor;
        this.ctx.lineWidth = 2;
        this.ctx.fillStyle = `${this.currentColor}1a`;

        this.drawGrid();
    }

    drawGrid() {
        const gridSize = 20;
        this.ctx.save();

        this.ctx.fillStyle = '#ffffff';
        this.ctx.fillRect(0, 0, this.canvas.width, this.canvas.height);

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

        this.ctx.strokeStyle = '#2c5530';
        this.ctx.lineWidth = 2;
        this.ctx.fillStyle = 'rgba(44, 85, 48, 0.1)';
    }

    setupResponsive() {
        const resizeCanvas = () => {
            const container = this.canvas.parentElement;
            if (!container) return;

            const containerWidth = container.clientWidth - 40;
            const containerHeight = container.clientHeight - 40;
            const aspectRatio = this.canvas.height / this.canvas.width;

            let newWidth = containerWidth;
            let newHeight = newWidth * aspectRatio;

            if (newHeight > containerHeight) {
                newHeight = containerHeight;
                newWidth = newHeight / aspectRatio;
            }

            newWidth = Math.max(newWidth, 300);
            newHeight = Math.max(newHeight, 200);

            this.canvas.style.width = newWidth + 'px';
            this.canvas.style.height = newHeight + 'px';
        };

        window.addEventListener('resize', resizeCanvas);
        resizeCanvas();
    }

    resize() {
        const container = this.canvas.parentElement;
        if (!container) return;

        const containerWidth = container.clientWidth - 40;
        const containerHeight = container.clientHeight - 40;
        const aspectRatio = this.canvas.height / this.canvas.width;

        let newWidth = containerWidth;
        let newHeight = newWidth * aspectRatio;

        if (newHeight > containerHeight) {
            newHeight = containerHeight;
            newWidth = newHeight / aspectRatio;
        }

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

        const btnUndo = document.getElementById('btn_undo');
        const btnEraser = document.getElementById('btn_eraser');

        if (btnUndo) {
            btnUndo.addEventListener('click', this.undo.bind(this));
        }

        if (btnEraser) {
            btnEraser.addEventListener('click', () => this.setMode('eraser'));
        }

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

            if (this.mode === 'eraser') {
                this.ctx.globalCompositeOperation = 'destination-out';
                this.ctx.lineWidth = 10;
            } else {
                this.ctx.globalCompositeOperation = 'source-over';
                this.ctx.lineWidth = 2;
            }
        } else if (this.mode === 'rectangulo' || this.mode === 'triangulo' || this.mode === 'circulo') {
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
            if (this.savedImageData) {
                this.ctx.putImageData(this.savedImageData, 0, 0);
            }
            this.drawPreview(this.startX, this.startY, pos.x, pos.y);
        }

        this.lastX = pos.x;
        this.lastY = pos.y;
    }

    stopDrawing(e) {
        if (!this.isDrawing) return;
        this.isDrawing = false;

        if (this.mode === 'rectangulo' || this.mode === 'triangulo' || this.mode === 'circulo') {
            if (this.savedImageData) {
                this.ctx.putImageData(this.savedImageData, 0, 0);
            }
            const pos = this.getMousePos(e);
            this.drawShape(this.startX, this.startY, pos.x, pos.y);
        }

        if (this.mode === 'eraser') {
            this.ctx.globalCompositeOperation = 'source-over';
            this.ctx.lineWidth = 2;
        }

        this.savedImageData = null;

        this.saveState();
    }

    drawPreview(startX, startY, endX, endY) {
        this.ctx.strokeStyle = '#2c5530';
        this.ctx.setLineDash([5, 5]);

        if (this.mode === 'rectangulo') {
            const width = endX - startX;
            const height = endY - startY;
            this.ctx.strokeRect(startX, startY, width, height);
        } else if (this.mode === 'triangulo') {
            const centerX = (startX + endX) / 2;
            this.ctx.beginPath();
            this.ctx.moveTo(centerX, startY);
            this.ctx.lineTo(startX, endY);
            this.ctx.lineTo(endX, endY);
            this.ctx.closePath();
            this.ctx.stroke();
        } else if (this.mode === 'circulo') {
            const radius = Math.sqrt(Math.pow(endX - startX, 2) + Math.pow(endY - startY, 2));
            this.ctx.beginPath();
            this.ctx.arc(startX, startY, radius, 0, 2 * Math.PI);
            this.ctx.stroke();
        }

        this.ctx.setLineDash([]);
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
            const centerX = (startX + endX) / 2;
            this.ctx.beginPath();
            this.ctx.moveTo(centerX, startY);
            this.ctx.lineTo(startX, endY);
            this.ctx.lineTo(endX, endY);
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
        const texto = prompt('Ingrese el texto que desea agregar:', '');

        if (texto && texto.trim() !== '') {
            this.ctx.font = `${this.fontSize}px ${this.fontFamily}`;
            this.ctx.fillStyle = '#2c5530';
            this.ctx.textBaseline = 'top';

            this.ctx.fillText(texto.trim(), x, y);

            this.ctx.strokeStyle = '#2c5530';
            this.ctx.lineWidth = 0.5;
            this.ctx.strokeText(texto.trim(), x, y);

            this.ctx.lineWidth = 2;
        }
    }

    createTextInput() {
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

        this.textInput.style.display = 'block';
        this.textInput.value = '';

        let left = clientX + 10;
        let top = clientY - 20;

        const inputWidth = 400;
        if (left + inputWidth > window.innerWidth) {
            left = window.innerWidth - inputWidth - 20;
        }

        if (top + 50 > window.innerHeight) {
            top = clientY - 50;
        }

        if (top < 10) {
            top = 10;
        }

        if (left < 10) {
            left = 10;
        }

        this.textInput.style.left = left + 'px';
        this.textInput.style.top = top + 'px';
        this.textInput.focus();

        setTimeout(() => {
            document.addEventListener('click', this.handleOutsideClick.bind(this), { once: true });
        }, 100);
    }

    handleOutsideClick(e) {
        if (this.textInputActive && e.target !== this.textInput) {
            this.cancelText();
        }
    }

    confirmText() {
        const texto = this.textInput.value.trim();

        this.hideTextInput();

        if (texto !== '') {
            try {
                this.ctx.font = `${this.fontSize}px ${this.fontFamily}`;
                this.ctx.fillStyle = this.currentColor;
                this.ctx.textBaseline = 'top';

                this.ctx.fillText(texto, this.pendingTextX, this.pendingTextY);

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

        document.removeEventListener('click', this.handleOutsideClick.bind(this));
    }

    redrawCanvas() {
        const imageData = this.ctx.getImageData(0, 0, this.canvas.width, this.canvas.height);

        this.ctx.clearRect(0, 0, this.canvas.width, this.canvas.height);
        this.ctx.fillStyle = '#ffffff';
        this.ctx.fillRect(0, 0, this.canvas.width, this.canvas.height);

        this.ctx.putImageData(imageData, 0, 0);
        this.ctx.fillStyle = 'rgba(44, 85, 48, 0.1)';
    }

    clearCanvas() {
        this.ctx.clearRect(0, 0, this.canvas.width, this.canvas.height);
        this.drawGrid();
        this.saveState();
    }

    getCanvasAsBase64() {
        return this.canvas.toDataURL('image/png');
    }

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

        this.updateCursor();
    }

    updateCursor() {
        // Cambiar cursor del canvas según el modo
        const cursors = {
            'libre': 'crosshair',
            'texto': 'text',
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
            this.ctx.fillStyle = `${this.currentColor}1a`;

            document.querySelectorAll('.color-btn').forEach(btn => {
                btn.classList.remove('active');
            });

            const activeBtn = document.querySelector(`[data-color="${colorName}"]`);
            if (activeBtn) {
                activeBtn.classList.add('active');
            }
        }
    }

    saveState() {
        try {
            const state = this.ctx.getImageData(0, 0, this.canvas.width, this.canvas.height);
            this.history.push(state);

            if (this.history.length > this.maxHistory) {
                this.history.shift();
            }

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

document.addEventListener('DOMContentLoaded', function () {
    // Hacer el canvas accesible globalmente
    window.canvasTerreno = new CanvasTerreno();

    // Configurar modo inicial
    window.canvasTerreno.setMode('libre');
});
