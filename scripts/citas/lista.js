document.addEventListener('DOMContentLoaded', function() {
    const buscador = document.querySelector('.textfield-buscador-navegador');
    if (buscador) {
        buscador.addEventListener('input', function() {
            buscarEnTabla(this.value);
        });
    }
    mostrarEstadisticas();
});

function buscarEnTabla(termino) {
    const filas = document.querySelectorAll('.fila-cita');
    const terminoLower = termino.toLowerCase();
    
    filas.forEach(fila => {
        const mostrar = fila.textContent.toLowerCase().includes(terminoLower);
        fila.style.display = mostrar ? '' : 'none';
    });
    
    actualizarContadorResultados();
}

function aplicarFiltros() {
    const estado = document.getElementById('filtro-estado')?.value || 'todos';
    const tipo = document.getElementById('filtro-tipo')?.value || 'todos';
    const fechaDesde = document.getElementById('filtro-fecha-desde')?.value;
    const fechaHasta = document.getElementById('filtro-fecha-hasta')?.value;
    const buscador = document.querySelector('.textfield-buscador-navegador')?.value || '';
    
    document.querySelectorAll('.fila-cita').forEach(fila => {
        let mostrar = true;
        
        if (estado !== 'todos' && fila.dataset.estado !== estado) mostrar = false;
        if (tipo !== 'todos' && fila.dataset.tipo !== tipo) mostrar = false;
        
        const fechaCita = fila.dataset.fecha;
        if (fechaDesde && fechaCita < fechaDesde) mostrar = false;
        if (fechaHasta && fechaCita > fechaHasta) mostrar = false;
        
        if (buscador && !fila.textContent.toLowerCase().includes(buscador.toLowerCase())) mostrar = false;
        
        fila.style.display = mostrar ? '' : 'none';
    });
    
    actualizarContadorResultados();
}

function actualizarContadorResultados() {
    const filasVisibles = document.querySelectorAll('.fila-cita:not([style*="display: none"])').length;
    const totalFilas = document.querySelectorAll('.fila-cita').length;
    
    let contador = document.getElementById('contador-resultados');
    if (!contador) {
        contador = document.createElement('div');
        contador.id = 'contador-resultados';
        contador.style.cssText = 'position:fixed;bottom:100px;right:30px;background:rgba(0,0,0,0.8);color:white;padding:8px 12px;border-radius:20px;font-size:12px;z-index:1000';
        document.body.appendChild(contador);
    }
    
    contador.textContent = `${filasVisibles} de ${totalFilas} citas`;
    contador.style.display = filasVisibles === totalFilas ? 'none' : 'block';
}

function mostrarEstadisticas() {
    const filas = document.querySelectorAll('.fila-cita');
    const estadisticas = { total: filas.length, pendiente: 0, completada: 0, cancelada: 0, proximas: 0, vencidas: 0 };
    
    filas.forEach(fila => {
        const estado = fila.dataset.estado;
        if (estadisticas.hasOwnProperty(estado)) estadisticas[estado]++;
        
        if (fila.style.backgroundColor === 'rgb(255, 243, 205)') estadisticas.proximas++;
        else if (fila.style.backgroundColor === 'rgb(248, 215, 218)') estadisticas.vencidas++;
    });
    
    console.log('Estadísticas de citas:', estadisticas);
}

function confirmChangeStatus(id, estado) {
    const mensajes = {
        'completada': '¿Confirmas que deseas marcar esta cita como COMPLETADA?',
        'cancelada': '¿Confirmas que deseas CANCELAR esta cita?',
        'pendiente': '¿Confirmas que deseas volver a PENDIENTE esta cita?'
    };

    if (confirm(mensajes[estado] || '¿Confirmas el cambio de estado?')) {
        changeStatus(id, estado);
    }
}

function confirmDelete(id) {
    if (confirm('¿Estás seguro de que deseas eliminar esta cita? Esta acción no se puede deshacer.')) {
        deleteCita(id);
    }
}

function changeStatus(id, estado) {
    if (typeof displayPopUp === 'function') displayPopUp();

    fetch(`../../php/citas/cambiar_estado.php`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: `id=${id}&estado=${estado}`
    })
    .then(response => response.ok ? response.json() : Promise.reject('Error en la red'))
    .then(data => {
        if (data.success) {
            showSuccessMessage(data.message || 'Estado actualizado correctamente');
            setTimeout(() => window.location.reload(), 1500);
        } else {
            showErrorMessage(data.message || 'Error al cambiar estado');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showErrorMessage("Error de conexión. Intente nuevamente.");
    });
}

function deleteCita(id) {
    if (typeof displayPopUp === 'function') displayPopUp();

    fetch(`../../php/citas/eliminar.php`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: `id=${id}`
    })
    .then(response => response.ok ? response.json() : Promise.reject('Error en la red'))
    .then(data => {
        if (data.success) {
            showSuccessMessage('Cita eliminada exitosamente');
            setTimeout(() => window.location.reload(), 1500);
        } else {
            showErrorMessage(data.message || 'Error al eliminar cita');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showErrorMessage("Error de conexión. Intente nuevamente.");
    });
}

function showSuccessMessage(message) {
    if (typeof displayMensajeExito === 'function') {
        displayMensajeExito(message);
    } else {
        alert(message);
    }
}

function showErrorMessage(message) {
    if (typeof displayMensajeError === 'function') {
        displayMensajeError(message);
    } else {
        alert(message);
    }
}

function exportarCSV() {
    const filas = document.querySelectorAll('.fila-cita:not([style*="display: none"])');
    const headers = ['ID', 'Cliente', 'Fecha', 'Hora', 'Tipo', 'Estado', 'Direccion'];
    
    let csvContent = headers.join(',') + '\n';
    
    filas.forEach(fila => {
        const celdas = fila.querySelectorAll('td');
        const datos = [
            celdas[0]?.textContent?.trim() || '',
            `"${celdas[1]?.textContent?.trim().replace(/"/g, '""') || ''}"`,
            celdas[2]?.textContent?.trim() || '',
            celdas[3]?.textContent?.trim() || '',
            celdas[4]?.textContent?.trim() || '',
            celdas[6]?.textContent?.trim() || '',
            `"${celdas[5]?.textContent?.trim().replace(/"/g, '""') || ''}"`
        ];
        csvContent += datos.join(',') + '\n';
    });
    
    const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
    const link = document.createElement('a');
    const url = URL.createObjectURL(blob);
    link.setAttribute('href', url);
    link.setAttribute('download', `citas_${new Date().toISOString().split('T')[0]}.csv`);
    link.style.visibility = 'hidden';
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
}

function limpiarFiltros() {
    const filtroEstado = document.getElementById('filtro-estado');
    const filtroTipo = document.getElementById('filtro-tipo');
    const filtroFechaDesde = document.getElementById('filtro-fecha-desde');
    const filtroFechaHasta = document.getElementById('filtro-fecha-hasta');
    const buscador = document.querySelector('.textfield-buscador-navegador');
    
    if (filtroEstado) filtroEstado.value = 'todos';
    if (filtroTipo) filtroTipo.value = 'todos';
    if (filtroFechaDesde) filtroFechaDesde.value = '';
    if (filtroFechaHasta) filtroFechaHasta.value = '';
    if (buscador) buscador.value = '';
    
    document.querySelectorAll('.fila-cita').forEach(fila => fila.style.display = '');
    actualizarContadorResultados();
}

