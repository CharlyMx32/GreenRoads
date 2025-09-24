document.addEventListener('DOMContentLoaded', function() {
    inicializarFormulario();
    configurarValidaciones();
    cargarInfoClienteInicial();
});

function inicializarFormulario() {
    const primerCampo = document.getElementById('id_cliente');
    if (primerCampo && !primerCampo.disabled) primerCampo.focus();
    marcarCambiosFormulario();
}

function configurarValidaciones() {
    const form = document.getElementById('form-editar-cita');
    if (!form) return;
    
    const clienteSelect = document.getElementById('id_cliente');
    if (clienteSelect) {
        clienteSelect.addEventListener('change', function() {
            validarCliente();
            mostrarInfoCliente(this);
        });
    }
    
    const fechaInput = document.getElementById('fecha_cita');
    if (fechaInput) {
        fechaInput.addEventListener('change', validarFechaEdicion);
        fechaInput.addEventListener('blur', validarFechaEdicion);
    }
    
    const horaInput = document.getElementById('hora_cita');
    if (horaInput) {
        horaInput.addEventListener('change', validarHoraEdicion);
        horaInput.addEventListener('blur', validarHoraEdicion);
    }
    
    const estadoSelect = document.getElementById('estado');
    if (estadoSelect) {
        estadoSelect.addEventListener('change', validarCambioEstado);
    }
    
    const direccionTextarea = document.getElementById('direccion_cita');
    if (direccionTextarea) {
        direccionTextarea.addEventListener('input', validarDireccion);
        direccionTextarea.addEventListener('blur', validarDireccion);
    }
    
    form.addEventListener('submit', function(e) {
        e.preventDefault();
        if (validarFormularioCompleto()) enviarFormularioEdicion();
    });
}

function cargarInfoClienteInicial() {
    const selectCliente = document.getElementById('id_cliente');
    if (selectCliente && selectCliente.value) mostrarInfoCliente(selectCliente);
}

function marcarCambiosFormulario() {
    const form = document.getElementById('form-editar-cita');
    if (!form) return;
    
    const valoresOriginales = new FormData(form);
    let hayCambios = false;
    
    form.addEventListener('input', function() {
        const valoresActuales = new FormData(form);
        hayCambios = false;
        
        for (let [key, value] of valoresActuales.entries()) {
            if (valoresOriginales.get(key) !== value) {
                hayCambios = true;
                break;
            }
        }
        mostrarIndicadorCambios(hayCambios);
    });
    
    window.addEventListener('beforeunload', function(e) {
        if (hayCambios) {
            e.preventDefault();
            e.returnValue = '¿Seguro que desea salir sin guardar los cambios?';
            return e.returnValue;
        }
    });
}

function mostrarIndicadorCambios(hayCambios) {
    let indicador = document.getElementById('indicador-cambios');
    
    if (hayCambios && !indicador) {
        indicador = document.createElement('div');
        indicador.id = 'indicador-cambios';
        indicador.style.cssText = 'position:fixed;top:80px;right:30px;background:#ffc107;color:#212529;padding:8px 12px;border-radius:20px;font-size:12px;z-index:1000;font-weight:600';
        indicador.innerHTML = '<i class="fa-solid fa-exclamation-triangle"></i> Cambios sin guardar';
        document.body.appendChild(indicador);
    } else if (!hayCambios && indicador) {
        document.body.removeChild(indicador);
    }
}

function validarFechaEdicion() {
    const fechaInput = document.getElementById('fecha_cita');
    const estadoSelect = document.getElementById('estado');
    const mensaje = document.getElementById('error-fecha') || crearMensajeError('error-fecha');
    
    if (!fechaInput.value) {
        mostrarError(fechaInput, 'La fecha es requerida', mensaje);
        return false;
    }
    
    const fechaSeleccionada = new Date(fechaInput.value);
    const hoy = new Date();
    hoy.setHours(0, 0, 0, 0);
    
    if (fechaSeleccionada < hoy && estadoSelect.value === 'pendiente') {
        if (!confirm('La fecha seleccionada ya pasó. ¿Desea cambiar el estado de la cita?')) {
            mostrarError(fechaInput, 'No se pueden agendar citas pendientes en fechas pasadas', mensaje);
            return false;
        }
    }
    
    ocultarError(fechaInput, mensaje);
    return true;
}

function validarHoraEdicion() {
    const horaInput = document.getElementById('hora_cita');
    const fechaInput = document.getElementById('fecha_cita');
    const estadoSelect = document.getElementById('estado');
    const mensaje = document.getElementById('error-hora') || crearMensajeError('error-hora');
    
    if (!horaInput.value) {
        mostrarError(horaInput, 'La hora es requerida', mensaje);
        return false;
    }
    
    if (!/^([01]?[0-9]|2[0-3]):[0-5][0-9]$/.test(horaInput.value)) {
        mostrarError(horaInput, 'Formato de hora no válido', mensaje);
        return false;
    }
    
    if (fechaInput.value && estadoSelect.value === 'pendiente') {
        const fechaHoraCita = new Date(fechaInput.value + 'T' + horaInput.value);
        const ahora = new Date();
        
        if (fechaHoraCita < ahora && !confirm('La fecha y hora seleccionada ya pasó. ¿Desea continuar?')) {
            return false;
        }
    }
    
    ocultarError(horaInput, mensaje);
    return true;
}

function validarCambioEstado() {
    const estadoSelect = document.getElementById('estado');
    const fechaInput = document.getElementById('fecha_cita');
    const horaInput = document.getElementById('hora_cita');
    
    if (estadoSelect.value === 'completada' && fechaInput.value && horaInput.value) {
        const fechaHoraCita = new Date(fechaInput.value + 'T' + horaInput.value);
        const ahora = new Date();
        
        if (fechaHoraCita > ahora && !confirm('La cita aún no ha ocurrido. ¿Está seguro de marcarla como completada?')) {
            estadoSelect.value = 'pendiente';
            return false;
        }
    }
    return true;
}

function validarCliente() {
    const clienteSelect = document.getElementById('id_cliente');
    const mensaje = document.getElementById('error-cliente') || crearMensajeError('error-cliente');
    
    if (!clienteSelect.value) {
        mostrarError(clienteSelect, 'Debe seleccionar un cliente', mensaje);
        return false;
    }
    
    ocultarError(clienteSelect, mensaje);
    return true;
}

function validarDireccion() {
    const direccionTextarea = document.getElementById('direccion_cita');
    const mensaje = document.getElementById('error-direccion') || crearMensajeError('error-direccion');
    
    if (!direccionTextarea.value.trim()) {
        mostrarError(direccionTextarea, 'La dirección es requerida', mensaje);
        return false;
    }
    
    if (direccionTextarea.value.trim().length < 10) {
        mostrarError(direccionTextarea, 'La dirección debe ser más específica (mínimo 10 caracteres)', mensaje);
        return false;
    }
    
    ocultarError(direccionTextarea, mensaje);
    return true;
}

function validarFormularioCompleto() {
    const tipoSelect = document.getElementById('tipo_cita');
    if (!tipoSelect.value) {
        alert('Debe seleccionar un tipo de cita');
        tipoSelect.focus();
        return false;
    }
    
    return [validarCliente(), validarFechaEdicion(), validarHoraEdicion(), validarDireccion(), validarCambioEstado()].every(v => v === true);
}

function enviarFormularioEdicion() {
    const form = document.getElementById('form-editar-cita');
    if (!form) return;
    
    if (typeof displayPopUp === 'function') displayPopUp();
    
    const btnGuardar = form.querySelector('.btn-guardar');
    if (btnGuardar) {
        btnGuardar.disabled = true;
        btnGuardar.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Actualizando...';
    }
    
    fetch('../../php/citas/editar.php', {
        method: 'POST',
        body: new FormData(form)
    })
    .then(response => response.ok ? response.json() : Promise.reject('Error en la red'))
    .then(data => {
        if (data.success) {
            const indicador = document.getElementById('indicador-cambios');
            if (indicador) document.body.removeChild(indicador);
            
            window.removeEventListener('beforeunload', function(){});
            
            if (typeof displayMensajeExitoso === 'function') {
                displayMensajeExitoso('Cita actualizada exitosamente', "window.location.href = 'lista.php'");
            } else {
                alert('Cita actualizada exitosamente');
                window.location.href = 'lista.php';
            }
        } else {
            if (typeof displayMensajeError === 'function') {
                displayMensajeError(data.message || 'Error al actualizar la cita');
            } else {
                alert(data.message || 'Error al actualizar la cita');
            }
            restaurarBotonEnvio();
        }
    })
    .catch(error => {
        console.error('Error:', error);
        if (typeof displayMensajeError === 'function') {
            displayMensajeError('Error de conexión. Intente nuevamente.');
        } else {
            alert('Error de conexión. Intente nuevamente.');
        }
        restaurarBotonEnvio();
    });
}

function restaurarBotonEnvio() {
    const btnGuardar = document.querySelector('.btn-guardar');
    if (btnGuardar) {
        btnGuardar.disabled = false;
        btnGuardar.innerHTML = '<i class="fa-solid fa-save"></i> Actualizar Cita';
    }
}

function mostrarInfoCliente(selectElement) {
    const option = selectElement.options[selectElement.selectedIndex];
    if (option.value) {
        document.getElementById('cliente-telefono').textContent = option.dataset.telefono || 'No disponible';
        document.getElementById('cliente-email').textContent = option.dataset.email || 'No disponible';
    }
}

function crearMensajeError(id) {
    const mensaje = document.createElement('div');
    mensaje.id = id;
    mensaje.className = 'mensaje-error';
    mensaje.style.cssText = 'color:#dc3545;font-size:12px;margin-top:5px;display:none';
    return mensaje;
}

function mostrarError(campo, texto, mensajeElement) {
    campo.style.borderColor = '#dc3545';
    mensajeElement.textContent = texto;
    mensajeElement.style.display = 'block';
    
    if (!campo.parentNode.contains(mensajeElement)) {
        campo.parentNode.appendChild(mensajeElement);
    }
}

function ocultarError(campo, mensajeElement) {
    campo.style.borderColor = '';
    mensajeElement.style.display = 'none';
}

function showSuccessMessage(message) {
    if (typeof displayMensajeExitoso === 'function') {
        displayMensajeExitoso(message);
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

document.addEventListener('keydown', function(e) {
    if (e.key === 'Enter' && e.target.tagName !== 'TEXTAREA') {
        e.preventDefault();
        const form = document.getElementById('form-editar-cita');
        if (form) form.dispatchEvent(new Event('submit'));
    }
});