// ===== FUNCIONES PARA CAMBIAR ESTADO =====
function cambiarEstadoCotizacion(id, nuevoEstado) {
    const mensajes = {
        'aceptada': '¿Está seguro de aceptar esta cotización?',
        'rechazada': '¿Está seguro de rechazar esta cotización?',
        'cancelada': '¿Está seguro de cancelar esta cotización?'
    };

    if (!confirm(mensajes[nuevoEstado] || `¿Está seguro de cambiar el estado a "${nuevoEstado}"?`)) {
        return;
    }

    const url = `../../php/cotizaciones/cambiar_estado.php?id=${id}&estado=${nuevoEstado}`;

    fetch(url, {
            method: 'GET'
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
