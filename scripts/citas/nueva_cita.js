document.addEventListener('DOMContentLoaded', function() {
    inicializarFormulario();
    configurarValidaciones();
    configurarAutocompletado();
});

function inicializarFormulario() {
    const fechaInput = document.getElementById('fecha_cita');
    if (fechaInput) {
        const hoy = new Date().toISOString().split('T')[0];
        fechaInput.setAttribute('min', hoy);
        fechaInput.value = hoy;
    }
    
    const horaInput = document.getElementById('hora_cita');
    if (horaInput) {
        const ahora = new Date();
        ahora.setHours(ahora.getHours() + 1, 0, 0, 0);
        horaInput.value = ahora.toTimeString().slice(0, 5);
    }
    
    const primerCampo = document.getElementById('id_cliente');
    if (primerCampo) primerCampo.focus();
}

function configurarValidaciones() {
    const form = document.getElementById('form-nueva-cita');
    if (!form) return;
    
    const clienteSelect = document.getElementById('id_cliente');
    if (clienteSelect) clienteSelect.addEventListener('change', validarCliente);
    
    const fechaInput = document.getElementById('fecha_cita');
    if (fechaInput) {
        fechaInput.addEventListener('change', validarFecha);
        fechaInput.addEventListener('blur', validarFecha);
    }
    
    const horaInput = document.getElementById('hora_cita');
    if (horaInput) {
        horaInput.addEventListener('change', validarHora);
        horaInput.addEventListener('blur', validarHora);
    }
    
    const direccionTextarea = document.getElementById('direccion_cita');
    if (direccionTextarea) {
        direccionTextarea.addEventListener('input', validarDireccion);
        direccionTextarea.addEventListener('blur', validarDireccion);
    }
    
    form.addEventListener('submit', function(e) {
        e.preventDefault();
        if (validarFormularioCompleto()) enviarFormulario();
    });
}

function configurarAutocompletado() {
    const clienteSelect = document.getElementById('id_cliente');
    if (clienteSelect) {
        clienteSelect.addEventListener('change', function() {
            mostrarInfoCliente(this);
            sugerirTipoCita(this.value);
        });
    }
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

function validarFecha() {
    const fechaInput = document.getElementById('fecha_cita');
    const mensaje = document.getElementById('error-fecha') || crearMensajeError('error-fecha');
    
    if (!fechaInput.value) {
        mostrarError(fechaInput, 'La fecha es requerida', mensaje);
        return false;
    }
    
    const fechaSeleccionada = new Date(fechaInput.value);
    const hoy = new Date();
    hoy.setHours(0, 0, 0, 0);
    
    if (fechaSeleccionada < hoy) {
        mostrarError(fechaInput, 'No se pueden agendar citas en fechas pasadas', mensaje);
        return false;
    }
    
    const maxFecha = new Date();
    maxFecha.setFullYear(maxFecha.getFullYear() + 1);
    
    if (fechaSeleccionada > maxFecha) {
        mostrarError(fechaInput, 'La fecha no puede ser mayor a un año', mensaje);
        return false;
    }
    
    ocultarError(fechaInput, mensaje);
    return true;
}

function validarHora() {
    const horaInput = document.getElementById('hora_cita');
    const fechaInput = document.getElementById('fecha_cita');
    const mensaje = document.getElementById('error-hora') || crearMensajeError('error-hora');
    
    if (!horaInput.value) {
        mostrarError(horaInput, 'La hora es requerida', mensaje);
        return false;
    }
    
    if (!/^([01]?[0-9]|2[0-3]):[0-5][0-9]$/.test(horaInput.value)) {
        mostrarError(horaInput, 'Formato de hora no válido', mensaje);
        return false;
    }
    
    if (fechaInput.value) {
        const fechaHoraCita = new Date(fechaInput.value + 'T' + horaInput.value);
        const ahora = new Date();
        
        if (fechaHoraCita < ahora) {
            mostrarError(horaInput, 'No se pueden agendar citas en horas pasadas', mensaje);
            return false;
        }
    }
    
    const [horas, minutos] = horaInput.value.split(':').map(Number);
    const totalMinutos = horas * 60 + minutos;
    
    if (totalMinutos < 480 || totalMinutos > 1080) {
        if (!confirm('La hora seleccionada está fuera del horario laboral normal (8:00 - 18:00). ¿Desea continuar?')) {
            return false;
        }
    }
    
    ocultarError(horaInput, mensaje);
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
    
    return [validarCliente(), validarFecha(), validarHora(), validarDireccion()].every(v => v === true);
}

function enviarFormulario() {
    const form = document.getElementById('form-nueva-cita');
    if (!form) return;
    
    if (typeof displayPopUp === 'function') displayPopUp();
    
    const btnGuardar = form.querySelector('.btn-guardar');
    if (btnGuardar) {
        btnGuardar.disabled = true;
        btnGuardar.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Guardando...';
    }
    
    fetch('../../php/citas/agregar.php', {
        method: 'POST',
        body: new FormData(form)
    })
    .then(response => response.ok ? response.json() : Promise.reject('Error en la red'))
    .then(data => {
        if (data.success) {
            if (typeof displayMensajeExitoso === 'function') {
                displayMensajeExitoso('Cita creada exitosamente', "window.location.href = 'lista.php'");
            } else {
                window.location.href = 'lista.php';
            }
        } else {
            if (typeof displayMensajeError === 'function') {
                displayMensajeError(data.message || 'Error al crear la cita');
            } else {
                alert(data.message || 'Error al crear la cita');
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
        btnGuardar.innerHTML = '<i class="fa-solid fa-save"></i> Crear Cita';
    }
}

function mostrarInfoCliente(selectElement) {
    const option = selectElement.options[selectElement.selectedIndex];
    const infoPanel = document.getElementById('info-cliente');
    
    if (option.value && infoPanel) {
        document.getElementById('cliente-telefono').textContent = option.dataset.telefono || 'No disponible';
        document.getElementById('cliente-email').textContent = option.dataset.email || 'No disponible';
        infoPanel.style.display = 'block';
    } else if (infoPanel) {
        infoPanel.style.display = 'none';
    }
}

function sugerirTipoCita(clienteId) {
    if (!clienteId) return;
    
    fetch(`../../php/citas/obtener_citas.php?cliente=${clienteId}`)
    .then(response => response.json())
    .then(data => {
        if (data.success && data.citas.length > 0) {
            const tipos = data.citas.map(cita => cita.tipo_cita);
            const tipoMasFrecuente = tipos.reduce((a, b, i, arr) =>
                arr.filter(v => v === a).length >= arr.filter(v => v === b).length ? a : b
            );
            
            const tipoSelect = document.getElementById('tipo_cita');
            if (tipoSelect && !tipoSelect.value) {
                tipoSelect.value = tipoMasFrecuente;
                mostrarSugerencia(`Sugerencia: Tipo "${tipoMasFrecuente}" basado en historial del cliente`);
            }
        }
    })
    .catch(error => console.log('No se pudo obtener historial del cliente:', error));
}

function mostrarSugerencia(mensaje) {
    const sugerencia = document.createElement('div');
    sugerencia.className = 'sugerencia-temporal';
    sugerencia.style.cssText = `position:fixed;top:50%;left:50%;transform:translate(-50%,-50%);background:#d4edda;color:#155724;padding:10px 15px;border-radius:6px;border:1px solid #c3e6cb;z-index:1000;font-size:14px`;
    sugerencia.textContent = mensaje;
    document.body.appendChild(sugerencia);
    setTimeout(() => document.body.removeChild(sugerencia), 3000);
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
        const form = document.getElementById('form-nueva-cita');
        if (form) form.dispatchEvent(new Event('submit'));
    }
});