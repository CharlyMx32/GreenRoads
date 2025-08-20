// ===== FUNCIONES PARA DETALLE DE INSTALACIÓN =====

// Variables globales
let dibujoCanvasVer = null;
let dibujoCtxVer = null;

// Inicializar al cargar la página
document.addEventListener('DOMContentLoaded', function() {
    console.log('Detalle de instalación cargado');
    console.log('Window.instalacionData:', window.instalacionData);
    cargarProductosAdicionales();
    
    // Función de prueba global
    window.testModals = function() {
        console.log('=== TEST DE MODALES ===');
        console.log('Modal Progreso:', document.getElementById('modalProgreso'));
        console.log('Modal Ver Dibujo:', document.getElementById('modalVerDibujo'));
        console.log('Modal Agregar Producto:', document.getElementById('modalAgregarProducto'));
        console.log('Funciones disponibles:', {
            abrirModalProgreso: typeof abrirModalProgreso,
            verDibujoTerreno: typeof verDibujoTerreno,
            abrirModalAgregarProducto: typeof abrirModalAgregarProducto
        });
    };
    
    // Ejecutar test automáticamente
    setTimeout(() => {
        window.testModals();
    }, 1000);
});

// ===== FUNCIONES PARA VER DIBUJO =====
function verDibujoTerreno() {
    console.log('verDibujoTerreno llamada');
    const modal = document.getElementById('modalVerDibujo');
    console.log('Modal encontrado:', modal);
    
    if (modal && window.instalacionData && window.instalacionData.dibujo_cotizacion) {
        modal.style.display = 'block';
        console.log('Modal mostrado');
        
        // Inicializar canvas después de mostrar el modal
        setTimeout(() => {
            inicializarCanvasVisualizacion();
            cargarDibujoEnCanvas(window.instalacionData.dibujo_cotizacion);
        }, 100);
    } else {
        console.log('No hay dibujo disponible o modal no encontrado');
        console.log('Modal:', modal);
        console.log('instalacionData:', window.instalacionData);
        alert('No hay dibujo de terreno disponible');
    }
}

function cerrarModalVerDibujo() {
    console.log('cerrarModalVerDibujo llamada');
    const modal = document.getElementById('modalVerDibujo');
    if (modal) {
        modal.style.display = 'none';
        console.log('Modal cerrado');
    }
}

function inicializarCanvasVisualizacion() {
    console.log('inicializarCanvasVisualizacion llamada');
    dibujoCanvasVer = document.getElementById('canvasVisualizacion');
    if (dibujoCanvasVer) {
        dibujoCtxVer = dibujoCanvasVer.getContext('2d');
        dibujoCtxVer.fillStyle = 'white';
        dibujoCtxVer.fillRect(0, 0, dibujoCanvasVer.width, dibujoCanvasVer.height);
        console.log('Canvas inicializado');
    } else {
        console.error('Canvas no encontrado');
    }
}

function cargarDibujoEnCanvas(dibujoData) {
    if (!dibujoCtxVer || !dibujoData) return;
    
    try {
        const imagen = new Image();
        imagen.onload = function() {
            dibujoCtxVer.clearRect(0, 0, dibujoCanvasVer.width, dibujoCanvasVer.height);
            dibujoCtxVer.drawImage(imagen, 0, 0, dibujoCanvasVer.width, dibujoCanvasVer.height);
        };
        imagen.src = dibujoData;
    } catch (error) {
        console.error('Error al cargar el dibujo:', error);
    }
}

// ===== FUNCIONES PARA PROGRESO =====
function abrirModalProgreso() {
    console.log('abrirModalProgreso llamada');
    const modal = document.getElementById('modalProgreso');
    console.log('Modal progreso encontrado:', modal);
    
    if (modal) {
        modal.style.display = 'block';
    } else {
        console.error('Modal de progreso no encontrado');
    }
}

function cerrarModalProgreso() {
    const modal = document.getElementById('modalProgreso');
    if (modal) {
        modal.style.display = 'none';
    }
}

function guardarProgreso() {
    const progreso = document.getElementById('progreso_porcentaje').value;
    const notas = document.getElementById('notas_progreso').value;
    
    if (!window.instalacionData || !window.instalacionData.id) {
        alert('Error: No se pudo obtener el ID de la instalación');
        return;
    }
    
    const formData = new FormData();
    formData.append('accion', 'actualizar_progreso');
    formData.append('id_instalacion', window.instalacionData.id);
    formData.append('progreso_porcentaje', progreso);
    formData.append('notas', notas);
    
    fetch('../../php/instalaciones/gestionar_instalacion.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('Progreso actualizado correctamente');
            cerrarModalProgreso();
            location.reload();
        } else {
            alert('Error al actualizar progreso: ' + (data.message || 'Error desconocido'));
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Error al actualizar el progreso');
    });
}

// ===== FUNCIONES PARA CAMBIAR ESTADO =====
function cambiarEstadoInstalacion(id, nuevoEstado) {
    if (!confirm(`¿Está seguro de cambiar el estado a "${nuevoEstado}"?`)) {
        return;
    }
    
    const formData = new FormData();
    formData.append('accion', 'cambiar_estado');
    formData.append('id_instalacion', id);
    formData.append('nuevo_estado', nuevoEstado);
    
    fetch('../../php/instalaciones/gestionar_instalacion.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('Estado actualizado correctamente');
            location.reload();
        } else {
            alert('Error al actualizar estado: ' + (data.message || 'Error desconocido'));
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Error al actualizar el estado');
    });
}

// ===== FUNCIONES PARA PRODUCTOS ADICIONALES =====
function cargarProductosAdicionales() {
    fetch('../../php/instalaciones/gestionar_instalacion.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: 'accion=obtener_otros_productos&id_instalacion=' + (window.instalacionData ? window.instalacionData.id : 0)
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            mostrarProductosAdicionales(data.productos);
        }
    })
    .catch(error => {
        console.error('Error al cargar productos:', error);
    });
}

function mostrarProductosAdicionales(productos) {
    const contenedor = document.getElementById('productosAdicionalesLista');
    if (!contenedor) return;
    
    if (!productos || productos.length === 0) {
        contenedor.innerHTML = `
            <div class="estado-vacio">
                <i class="fa-solid fa-boxes-stacked"></i>
                <p>No hay productos adicionales</p>
                <button class="btn-accion btn-actualizar" onclick="abrirModalAgregarProducto()">
                    <i class="fa-solid fa-plus"></i> Agregar Producto
                </button>
            </div>
        `;
        return;
    }
    
    contenedor.innerHTML = productos.map(producto => `
        <div class="material-card">
            <div style="display: flex; justify-content: space-between; align-items: start;">
                <div class="material-nombre">${producto.nombre}</div>
                <button class="btn-eliminar-mini" onclick="eliminarProductoAdicional(${producto.id})" title="Eliminar">
                    <i class="fa-solid fa-trash"></i>
                </button>
            </div>
            <div class="material-cantidad">
                <span class="cantidad-necesaria">Cantidad: ${producto.cantidad} ${producto.unidad}</span>
                <span class="cantidad-usada">Precio: $${parseFloat(producto.precio_unitario).toFixed(2)}</span>
            </div>
            <div class="material-progreso">
                <div class="material-progreso-bar" style="width: 100%; background: #9c27b0;"></div>
            </div>
        </div>
    `).join('') + `
        <button class="btn-accion btn-actualizar" onclick="abrirModalAgregarProducto()" style="width: 100%; margin-top: 10px;">
            <i class="fa-solid fa-plus"></i> Agregar Producto
        </button>
    `;
}

function abrirModalAgregarProducto() {
    const modal = document.getElementById('modalAgregarProducto');
    if (modal) {
        modal.style.display = 'block';
    }
}

function cerrarModalAgregarProducto() {
    const modal = document.getElementById('modalAgregarProducto');
    if (modal) {
        modal.style.display = 'none';
        // Limpiar formulario
        document.getElementById('selectProducto').value = '';
        document.getElementById('cantidadProducto').value = '';
    }
}

function agregarProducto() {
    const selectProducto = document.getElementById('selectProducto');
    const cantidad = document.getElementById('cantidadProducto').value;
    
    if (!selectProducto.value) {
        alert('Por favor selecciona un producto');
        return;
    }
    
    if (!cantidad || cantidad <= 0) {
        alert('Por favor ingresa una cantidad válida');
        return;
    }
    
    const formData = new FormData();
    formData.append('accion', 'agregar_producto_adicional');
    formData.append('id_instalacion', window.instalacionData.id);
    formData.append('id_producto', selectProducto.value);
    formData.append('cantidad', cantidad);
    
    fetch('../../php/instalaciones/gestionar_instalacion.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('Producto agregado correctamente');
            cerrarModalAgregarProducto();
            cargarProductosAdicionales(); // Recargar lista
        } else {
            alert('Error al agregar producto: ' + (data.message || 'Error desconocido'));
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Error al agregar producto');
    });
}

function eliminarProductoAdicional(idProducto) {
    if (!confirm('¿Está seguro de eliminar este producto?')) {
        return;
    }
    
    const formData = new FormData();
    formData.append('accion', 'eliminar_producto_adicional');
    formData.append('id_producto_instalacion', idProducto);
    
    fetch('../../php/instalaciones/gestionar_instalacion.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('Producto eliminado correctamente');
            cargarProductosAdicionales(); // Recargar lista
        } else {
            alert('Error al eliminar producto: ' + (data.message || 'Error desconocido'));
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Error al eliminar producto');
    });
}

// ===== CERRAR MODALES CON ESCAPE =====
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        cerrarModalProgreso();
        cerrarModalVerDibujo();
        cerrarModalAgregarProducto();
    }
});

// ===== CERRAR MODALES CLICANDO FUERA =====
window.onclick = function(event) {
    const modalProgreso = document.getElementById('modalProgreso');
    const modalVerDibujo = document.getElementById('modalVerDibujo');
    const modalAgregarProducto = document.getElementById('modalAgregarProducto');
    
    if (event.target === modalProgreso) {
        cerrarModalProgreso();
    }
    if (event.target === modalVerDibujo) {
        cerrarModalVerDibujo();
    }
    if (event.target === modalAgregarProducto) {
        cerrarModalAgregarProducto();
    }
};