// ===== FUNCIONES PARA COMPARACIÓN DE OPCIONES =====
function seleccionarOpcion(opcion, total) {
    if (!confirm(`¿Está seguro de aceptar la Opción ${opcion} por $${total.toFixed(2).replace(/\d(?=(\d{3})+\.)/g, '$&,')}?`)) {
        return;
    }

    // Deshabilitar botones para evitar doble click
    const botones = document.querySelectorAll('.btn-seleccionar');
    botones.forEach(btn => {
        btn.disabled = true;
        btn.style.opacity = '0.6';
        btn.style.cursor = 'not-allowed';
    });

    // Usar el sistema existente de cambio de estado
    const url = `../../php/cotizaciones/cambiar_estado.php?id=${window.cotizacionData.id}&estado=aceptada&opcion=${opcion}`;
    
    fetch(url, {
            method: 'GET'
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert(`Opción ${opcion} aceptada correctamente`);
                location.reload();
            } else {
                alert('Error al aceptar la opción: ' + (data.message || 'Error desconocido'));
                // Rehabilitar botones en caso de error
                botones.forEach(btn => {
                    btn.disabled = false;
                    btn.style.opacity = '1';
                    btn.style.cursor = 'pointer';
                });
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Error al procesar la selección');
            // Rehabilitar botones en caso de error
            botones.forEach(btn => {
                btn.disabled = false;
                btn.style.opacity = '1';
                btn.style.cursor = 'pointer';
            });
        });
}
