setSearcher({
    input: ".textfield-buscador-navegador",
    search_element: "table tbody tr",
    display_type: "table-row"
});

function changeStatus(id, status) {
    let alertMsg;
    switch (status) {
        case 'aceptada':
            alertMsg = "¿Está seguro que desea marcar esta cotización como ACEPTADA?";
            break;
        case 'rechazada':
            alertMsg = "¿Está seguro que desea marcar esta cotización como RECHAZADA?";
            break;
        case 'cancelada':
            alertMsg = "¿Está seguro que desea CANCELAR esta cotización?";
            break;
        case 'pendiente':
            alertMsg = "¿Está seguro que desea volver a marcar esta cotización como PENDIENTE?";
            break;
        default:
            alertMsg = "¿Está seguro que desea cambiar el estado de esta cotización?";
    }

    if (!confirm(alertMsg)) return;

    displayPopUp();

    $.ajax({
        url: '../../php/cotizaciones/cambiar_estado.php',
        type: 'POST',
        contentType: 'application/json',
        data: JSON.stringify({
            id: id,
            status: status
        }),
        success: function (respuesta) {
            if (respuesta.status == 0) {
                displayMensajeError(respuesta.mensaje);
            } else {
                window.location.reload();
            }
        },
        error: function () {
            displayMensajeError("Error de conexión, favor de intentarlo nuevamente.");
        },
        dataType: 'json'
    });
}