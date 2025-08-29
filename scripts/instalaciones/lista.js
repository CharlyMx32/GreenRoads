// ===== FUNCIONES PARA LISTA DE INSTALACIONES =====

// Variables globales para el modal de instalación
let datosInstalacionActual = {
    id_instalacion: null,
    id_cotizacion: null,
    rollos_por_producto: []
};

// Abrir modal para iniciar instalación
function abrirModalIniciarInstalacion(idInstalacion, idCotizacion, fechaFinEstimada) {
    datosInstalacionActual.id_instalacion = idInstalacion;
    datosInstalacionActual.id_cotizacion = idCotizacion;
    
    document.getElementById('modalIniciarInstalacion').style.display = 'block';

    // Asignar la fecha estimada al input
    const inputFechaFin = document.getElementById('inicio_fecha_fin_estimada');
    if (inputFechaFin && fechaFinEstimada) {
        // Tomar solo la parte de la fecha (YYYY-MM-DD)
        inputFechaFin.value = fechaFinEstimada.split(' ')[0];
    }
    
    // Cargar rollos disponibles
    cargarRollosDisponibles(idCotizacion);
}

// Cerrar modal de iniciación de instalación
function cerrarModalIniciarInstalacion() {
    const modal = document.getElementById('modalIniciarInstalacion');
    if (modal) {
        modal.style.display = 'none';
    }
    datosInstalacionActual = {
        id_instalacion: null,
        id_cotizacion: null,
        rollos_por_producto: []
    };
}

// Event listeners para fechas
document.addEventListener('DOMContentLoaded', function() {
    // Actualizar fecha mínima de fin cuando cambie fecha de inicio
    const fechaInicio = document.getElementById('inicio_fecha_inicio');
    const fechaFin = document.getElementById('inicio_fecha_fin_estimada');
    
    if (fechaInicio && fechaFin) {
        fechaInicio.addEventListener('change', function() {
            const fechaSeleccionada = this.value;
            if (fechaSeleccionada) {
                // Establecer fecha mínima de fin como el día siguiente
                const fechaMin = new Date(fechaSeleccionada);
                fechaMin.setDate(fechaMin.getDate() + 1);
                fechaFin.min = fechaMin.toISOString().split('T')[0];
                
                // Si la fecha de fin es anterior, limpiarla
                if (fechaFin.value && fechaFin.value <= fechaSeleccionada) {
                    fechaFin.value = '';
                }
            }
        });
    }
});

// Cargar rollos disponibles para la cotización
function cargarRollosDisponibles(idCotizacion) {
    const contenido = document.getElementById('contenido-modal-instalacion');
    
    fetch(`../../php/instalaciones/obtener_rollos_disponibles.php?id_cotizacion=${idCotizacion}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                datosInstalacionActual.rollos_por_producto = data.rollos_por_producto;
                mostrarRollosEnModal(data.rollos_por_producto);
            } else {
                contenido.innerHTML = `<div class="error-message">Error: ${data.message}</div>`;
            }
        })
        .catch(error => {
            contenido.innerHTML = '<div class="error-message">Error de conexión al cargar los rollos</div>';
        });
}

// Mostrar rollos en el modal
function mostrarRollosEnModal(rollosPorProducto) {
    const contenido = document.getElementById('contenido-modal-instalacion');
    let html = '';
    
    rollosPorProducto.forEach((grupo, indiceGrupo) => {
        const producto = grupo.producto;
        const rollosDisponibles = grupo.rollos_disponibles;
        
        html += `
            <div class="producto-grupo" data-producto-id="${producto.id_producto}" data-color-id="${producto.id_color}">
                <div class="producto-header">
                    <h4>${producto.producto_nombre}</h4>
                    <div class="color-info">
                        <span class="color-muestra" style="background-color: ${producto.color_hex}"></span>
                        <span>${producto.color_nombre}</span>
                    </div>
                    <div class="area-info">
                        <span class="requerida">Área requerida: ${parseFloat(producto.area_necesaria).toFixed(2)} m²</span>
                        <span class="disponible">Área disponible: ${parseFloat(producto.area_total_disponible).toFixed(2)} m²</span>
                        ${parseFloat(producto.area_total_disponible) < parseFloat(producto.area_necesaria) ? 
                            '<span class="advertencia">⚠️ Área insuficiente</span>' : ''}
                    </div>
                </div>
                
                <div class="rollos-lista">
        `;
        
        if (rollosDisponibles.length === 0) {
            html += '<div class="no-rollos">No hay rollos disponibles para este producto y color.</div>';
        } else {
            rollosDisponibles.forEach((rollo, indiceRollo) => {
                const sugerido = rollo.sugerido_usar;
                
                html += `
                    <div class="rollo-item ${sugerido ? 'sugerido' : ''}" data-rollo-id="${rollo.id}">
                        <div class="rollo-info">
                            <div class="rollo-dimensiones">
                                <strong>Rollo #${rollo.id}</strong> - ${parseFloat(rollo.area_m2).toFixed(2)} m²
                                ${sugerido ? '<span class="badge-sugerido">RECOMENDADO</span>' : ''}
                            </div>
                            <div class="rollo-detalles">
                                Lote: ${rollo.id_lote || 'N/A'} | 
                                Entrada: ${rollo.fecha_ingreso ? new Date(rollo.fecha_ingreso).toLocaleDateString() : 'N/A'} |
                                Costo: $${parseFloat(rollo.costo_unitario || 0).toFixed(2)}
                            </div>
                            ${sugerido ? `
                            ` : `
                            `}
                        </div>
                        
                        <div class="rollo-seleccion">
                            <label class="checkbox-container">
                                <input type="checkbox" 
                                       onchange="toggleRollo(${indiceGrupo}, ${indiceRollo})"
                                       class="rollo-checkbox"
                                       ${sugerido ? 'checked' : ''}>
                                
                                <span class="checkbox-label">Seleccionar este rollo</span>
                            </label>
                        </div>
                    </div>
                `;
            });
        }
        
        html += `
                </div>
                <div class="resumen-seleccion">
                    <span class="area-seleccionada">Área seleccionada: <strong>0 m²</strong></span>
                    <span class="status-cumple" data-cumple="false">❌ Insuficiente</span>
                </div>
            </div>
        `;
    });
    
    contenido.innerHTML = html;
    
    // Calcular automáticamente las selecciones iniciales
    rollosPorProducto.forEach((grupo, indiceGrupo) => {
        actualizarResumenSeleccion(indiceGrupo);
    });
    
    validarSeleccionCompleta();
}

// Toggle de selección de rollo
function toggleRollo(indiceGrupo, indiceRollo) {
    // Solo actualizar el resumen cuando se selecciona/deselecciona
    actualizarResumenSeleccion(indiceGrupo);
    validarSeleccionCompleta();
}

// Actualizar resumen de selección para un grupo de productos
function actualizarResumenSeleccion(indiceGrupo) {
    const grupo = document.querySelector(`[data-producto-id="${datosInstalacionActual.rollos_por_producto[indiceGrupo].producto.id_producto}"][data-color-id="${datosInstalacionActual.rollos_por_producto[indiceGrupo].producto.id_color}"]`);
    const areaRequerida = parseFloat(datosInstalacionActual.rollos_por_producto[indiceGrupo].producto.area_necesaria) || 0;
    
    let areaSeleccionada = 0;
    const checkboxes = grupo.querySelectorAll('.rollo-checkbox:checked');
    
    checkboxes.forEach((checkbox) => {
        const indiceRollo = Array.from(grupo.querySelectorAll('.rollo-checkbox')).indexOf(checkbox);
        const rollo = datosInstalacionActual.rollos_por_producto[indiceGrupo].rollos_disponibles[indiceRollo];
        
        // Calcular correctamente cuánto área se usará de este rollo
        if (rollo.sugerido_usar) {
            // Si está sugerido, usar la cantidad pre-calculada
            areaSeleccionada += parseFloat(rollo.area_a_usar) || 0;
        } else {
            // Si no está sugerido, calcular cuánto se necesita realmente
            const areaRestante = Math.max(0, areaRequerida - areaSeleccionada);
            const areaRollo = parseFloat(rollo.area_m2) || 0;
            const areaUsar = Math.min(areaRollo, areaRestante);
            areaSeleccionada += areaUsar;
        }
    });
    
    const areaSpan = grupo.querySelector('.area-seleccionada strong');
    const statusSpan = grupo.querySelector('.status-cumple');
    
    if (areaSpan) {
        areaSpan.textContent = areaSeleccionada.toFixed(2) + ' m²';
    }
    
    if (statusSpan) {
        if (areaSeleccionada >= areaRequerida) {
            statusSpan.textContent = '✅ Suficiente';
            statusSpan.setAttribute('data-cumple', 'true');
            statusSpan.style.color = '#4CAF50';
        } else {
            statusSpan.textContent = '❌ Insuficiente';
            statusSpan.setAttribute('data-cumple', 'false');
            statusSpan.style.color = '#f44336';
        }
    }
}

// Validar si la selección está completa
function validarSeleccionCompleta() {
    const todosCumplen = document.querySelectorAll('.status-cumple[data-cumple="true"]').length === 
                        datosInstalacionActual.rollos_por_producto.length;
    
    const btnIniciar = document.getElementById('btnIniciarInstalacion');
    if (btnIniciar) {
        btnIniciar.disabled = !todosCumplen;
        btnIniciar.style.opacity = todosCumplen ? '1' : '0.6';
    }
}

// Procesar inicio de instalación
function procesarInicioInstalacion() {
    if (!datosInstalacionActual.id_instalacion) {
        displayMensajeError('Error: No se ha seleccionado una instalación');
        return;
    }
    
    // Validar campos de instalación
    const tecnicoResponsable = document.getElementById('inicio_tecnico_responsable').value;
    const fechaInicio = document.getElementById('inicio_fecha_inicio').value;
    const fechaFinEstimada = document.getElementById('inicio_fecha_fin_estimada').value;
    
    if (!tecnicoResponsable) {
        displayMensajeError('Debe seleccionar un técnico responsable');
        return;
    }
    
    if (!fechaInicio) {
        displayMensajeError('Debe especificar la fecha de inicio');
        return;
    }
    
    if (!fechaFinEstimada) {
        displayMensajeError('Debe especificar la fecha estimada de finalización');
        return;
    }
    
    if (new Date(fechaFinEstimada) <= new Date(fechaInicio)) {
        displayMensajeError('La fecha de finalización debe ser posterior a la fecha de inicio');
        return;
    }
    
    // Recopilar los rollos seleccionados
    const rollosSeleccionados = [];
    
    datosInstalacionActual.rollos_por_producto.forEach((grupo, indiceGrupo) => {
        const grupoElement = document.querySelector(`[data-producto-id="${grupo.producto.id_producto}"][data-color-id="${grupo.producto.id_color}"]`);
        const checkboxes = grupoElement.querySelectorAll('.rollo-checkbox:checked');
        
        checkboxes.forEach((checkbox) => {
            const indiceRollo = Array.from(grupoElement.querySelectorAll('.rollo-checkbox')).indexOf(checkbox);
            const rollo = grupo.rollos_disponibles[indiceRollo];
            
            // Calcular cuánto área y metros se necesitan realmente de este rollo
            let areaUsar, metrosUsar;
            
            if (rollo.sugerido_usar) {
                // Si está pre-calculado, usar esos valores
                areaUsar = rollo.area_a_usar;
                metrosUsar = rollo.metros_a_usar;
            } else {
                // Calcular cuánto del rollo se necesita para cubrir el área requerida
                const areaRequerida = parseFloat(grupo.producto.area_necesaria) || 0;
                const areaRolloCompleto = parseFloat(rollo.area_m2) || 0;
                const anchoRollo = parseFloat(rollo.ancho_metros) || 1;
                
                // Usar solo el área necesaria, no todo el rollo
                areaUsar = Math.min(areaRolloCompleto, areaRequerida);
                
                // Calcular metros lineales necesarios
                metrosUsar = areaUsar / anchoRollo;
            }
            
            rollosSeleccionados.push({
                id_rollo: rollo.id,
                id_producto: grupo.producto.id_producto,
                id_color: grupo.producto.id_color,
                area_usar: areaUsar,
                metros_usar: metrosUsar
            });
        });
    });
    
    if (rollosSeleccionados.length === 0) {
        displayMensajeError('Debe seleccionar al menos un rollo');
        return;
    }
    
    // Enviar datos al servidor
    displayPopUp();
    
    const formData = new FormData();
    formData.append('id_instalacion', datosInstalacionActual.id_instalacion);
    formData.append('rollos_seleccionados', JSON.stringify(rollosSeleccionados));
    formData.append('tecnico_responsable', tecnicoResponsable);
    formData.append('fecha_inicio', fechaInicio);
    formData.append('fecha_fin_estimada', fechaFinEstimada);
    formData.append('action', 'iniciar_instalacion');
    
    fetch('../../php/instalaciones/gestionar_instalacion.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            hidePopup();
            setTimeout(() => {
                cerrarModalIniciarInstalacion();
                setTimeout(() => {
                    window.location.reload();
                }, 500);
            }, 100);
        } else {
            displayMensajeError(data.message || 'Error al iniciar la instalación');
        }
    })
    .catch(error => {
        displayMensajeError("Error de conexión. Intente nuevamente.");
    });
}

// Cambiar estado de instalación
function cambiarEstadoInstalacion(id, nuevoEstado) {
    const mensajes = {
        'en_progreso': '¿Iniciar esta instalación?',
        'completada': '¿Marcar esta instalación como completada?',
        'cancelada': '¿Cancelar esta instalación?'
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
                // Usar el ID del técnico si está disponible, de lo contrario buscar por nombre
                const tecnicoValue = instalacion.id_tecnico_responsable || instalacion.tecnico_responsable || '';
                document.getElementById('edit_tecnico_responsable').value = tecnicoValue;
                document.getElementById('edit_fecha_inicio').value = instalacion.fecha_inicio || '';
                document.getElementById('edit_fecha_fin_estimada').value = instalacion.fecha_fin_estimada || '';
                document.getElementById('edit_progreso').value = instalacion.progreso_porcentaje || 0;
                document.getElementById('edit_observaciones').value = instalacion.observaciones || '';
            } else {
                displayMensajeError('Error al cargar los datos de la instalación');
            }
        })
        .catch(error => {
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
