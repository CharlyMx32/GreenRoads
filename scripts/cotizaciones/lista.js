document.addEventListener('DOMContentLoaded', function() {    
    if (typeof confirmChangeStatus === 'undefined') {
        window.confirmChangeStatus = function(id, estado) {
            const mensajes = {
                'aceptada': '¿Confirmas que deseas ACEPTAR esta cotización?',
                'rechazada': '¿Confirmas que deseas RECHAZAR esta cotización?',
                'cancelada': '¿Confirmas que deseas CANCELAR esta cotización?',
                'pendiente': '¿Confirmas que deseas volver a PENDIENTE esta cotización?'
            };

            if (confirm(mensajes[estado] || '¿Confirmas el cambio de estado?')) {
                changeStatus(id, estado);
            }
        };
    }
    
    if (typeof changeStatus === 'undefined') {
        window.changeStatus = function(id, estado) {
            displayPopUp();

            fetch(`../../php/cotizaciones/cambiar_estado.php?id=${id}&estado=${estado}`)
                .then(response => {
                    if (!response.ok) throw new Error('Error en la red');
                    return response.json();
                })
                .then(data => {
                    if (data.success) {
                        window.location.reload();
                    } else {
                        displayMensajeError(data.message || 'Error al cambiar estado');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    displayMensajeError("Error de conexión. Intente nuevamente.");
                });
        };
    }
});