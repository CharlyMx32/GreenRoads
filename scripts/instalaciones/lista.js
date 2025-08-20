// ===== FUNCIONES PARA LISTA DE INSTALACIONES =====

// Cambiar estado de instalación
function cambiarEstadoInstalacion(id, nuevoEstado) {
    const mensajes = {
        'en_progreso': '¿Confirmas que deseas INICIAR esta instalación?',
        'completada': '¿Confirmas que deseas marcar como COMPLETADA esta instalación?',
        'cancelada': '¿Confirmas que deseas CANCELAR esta instalación?',
        'planificada': '¿Confirmas que deseas volver a PLANIFICADA esta instalación?'
    };

    if (confirm(mensajes[nuevoEstado] || '¿Confirmas el cambio de estado?')) {
        displayPopUp();

        const formData = new FormData();
        formData.append('id_instalacion', id);
        formData.append('estado', nuevoEstado);
        formData.append('action', 'cambiar_estado');

        fetch('../../php/instalaciones/gestionar_instalacion.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                window.location.reload();
            } else {
                displayMensajeError(data.message || 'Error al cambiar el estado');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            displayMensajeError("Error de conexión. Intente nuevamente.");
        });
    }
}

// Abrir modal de edición
function abrirModalEdicion(id) {
    document.getElementById('modalEdicion').style.display = 'block';
    document.getElementById('edit_id_instalacion').value = id;
    
    // Cargar datos actuales de la instalación
    fetch(`../../php/instalaciones/obtener_instalacion.php?id=${id}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const instalacion = data.instalacion;
                document.getElementById('edit_tecnico_responsable').value = instalacion.tecnico_responsable || '';
                document.getElementById('edit_fecha_inicio').value = instalacion.fecha_inicio || '';
                document.getElementById('edit_fecha_fin_estimada').value = instalacion.fecha_fin_estimada || '';
                document.getElementById('edit_progreso').value = instalacion.progreso_porcentaje || 0;
                document.getElementById('edit_observaciones').value = instalacion.observaciones || '';
            } else {
                displayMensajeError('Error al cargar los datos de la instalación');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            displayMensajeError('Error de conexión al cargar los datos');
        });
}

// Cerrar modal de edición
function cerrarModalEdicion() {
    document.getElementById('modalEdicion').style.display = 'none';
}

// Guardar edición
function guardarEdicion() {
    const form = document.getElementById('formEditarInstalacion');
    const formData = new FormData(form);
    formData.append('action', 'actualizar_instalacion');

    displayPopUp();

    fetch('../../php/instalaciones/gestionar_instalacion.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            cerrarModalEdicion();
            window.location.reload();
        } else {
            displayMensajeError(data.message || 'Error al actualizar la instalación');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        displayMensajeError("Error de conexión. Intente nuevamente.");
    });
}

// Validar progreso en tiempo real
document.addEventListener('DOMContentLoaded', function() {
    const progresoInput = document.getElementById('edit_progreso');
    if (progresoInput) {
        progresoInput.addEventListener('input', function() {
            if (this.value < 0) this.value = 0;
            if (this.value > 100) this.value = 100;
        });
    }
});
