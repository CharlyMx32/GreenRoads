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
                    console.log('Respuesta del servidor:', data); // Debug
                    
                    if (data.success) {
                        window.location.reload();
                    } else if (data.es_comparativa && data.opciones) {
                        console.log('Detectada cotización comparativa con opciones:', data.opciones); // Debug
                        // Mostrar modal de selección de opciones para cotizaciones comparativas
                        mostrarModalSeleccionOpcion(id, estado, data.opciones);
                    } else {
                        console.log('Error o no es comparativa:', data); // Debug
                        displayMensajeError(data.message || 'Error al cambiar estado');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    displayMensajeError("Error de conexión. Intente nuevamente.");
                });
        };
    }

    // Función para mostrar modal de selección de opción comparativa
    if (typeof mostrarModalSeleccionOpcion === 'undefined') {
        window.mostrarModalSeleccionOpcion = function(id, estado, opciones) {
            // Ocultar popup de carga si existe
            try {
                if (typeof hidePopUp === 'function') {
                    hidePopUp();
                }
            } catch (e) {
                console.log('hidePopUp no disponible:', e);
            }
            
            console.log('Mostrando modal con opciones:', opciones);
            
            // Crear el modal dinámicamente
            const modalHTML = `
                <div id="modalSeleccionOpcion" class="modal-overlay" style="display: flex; z-index: 9999;">
                    <div class="modal-content" style="max-width: 600px; width: 90%;">
                        <div class="modal-header">
                            <h3>Seleccionar Opción de Rollo</h3>
                            <button type="button" class="modal-close" onclick="cerrarModalSeleccionOpcion()">&times;</button>
                        </div>
                        <div class="modal-body">
                            <p><strong>Esta cotización tiene 2 opciones de rollo. Seleccione cuál desea aceptar:</strong></p>
                            <div class="opciones-comparativas">
                                ${generarOpcionesHTML(opciones)}
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn-cancelar" onclick="cerrarModalSeleccionOpcion()">Cancelar</button>
                            <button type="button" class="btn-aceptar" onclick="confirmarSeleccionOpcion(${id}, '${estado}')" disabled>Aceptar Selección</button>
                        </div>
                    </div>
                </div>`;

            // Insertar modal en el DOM
            document.body.insertAdjacentHTML('beforeend', modalHTML);
            
            // Agregar eventos para selección
            document.querySelectorAll('.opcion-rollo').forEach(opcion => {
                opcion.addEventListener('click', function() {
                    // Deseleccionar todas las opciones
                    document.querySelectorAll('.opcion-rollo').forEach(o => o.classList.remove('selected'));
                    // Seleccionar la opción actual
                    this.classList.add('selected');
                    // Habilitar botón de aceptar
                    document.querySelector('.btn-aceptar').disabled = false;
                });
            });
        };
    }

    // Generar HTML para las opciones
    if (typeof generarOpcionesHTML === 'undefined') {
        window.generarOpcionesHTML = function(opciones) {
            let html = '';
            let opcionesPorGrupo = {};
            
            // Agrupar por opción comparativa
            opciones.forEach(opcion => {
                if (!opcionesPorGrupo[opcion.opcion_comparativa]) {
                    opcionesPorGrupo[opcion.opcion_comparativa] = [];
                }
                opcionesPorGrupo[opcion.opcion_comparativa].push(opcion);
            });

            Object.keys(opcionesPorGrupo).forEach(numeroOpcion => {
                const items = opcionesPorGrupo[numeroOpcion];
                const totalPrecio = items.reduce((sum, item) => sum + (parseFloat(item.precio_unitario) * parseFloat(item.cantidad)), 0);
                
                html += `
                    <div class="opcion-rollo" data-opcion="${numeroOpcion}">
                        <div class="opcion-header">
                            <strong>Opción ${numeroOpcion}</strong>
                            <span class="precio-total">$${totalPrecio.toFixed(2)}</span>
                        </div>
                        <div class="opcion-detalles">`;
                
                items.forEach(item => {
                    html += `
                        <div class="item-detalle">
                            <span class="producto">${item.nombre_producto}</span>
                            <span class="color">${item.nombre_color}</span>
                            <span class="cantidad">${item.cantidad} m²</span>
                            <span class="precio">$${parseFloat(item.precio_unitario).toFixed(2)}/m²</span>
                        </div>`;
                });
                
                html += `
                        </div>
                    </div>`;
            });

            return html;
        };
    }

    // Confirmar selección de opción
    if (typeof confirmarSeleccionOpcion === 'undefined') {
        window.confirmarSeleccionOpcion = function(id, estado) {
            const opcionSeleccionada = document.querySelector('.opcion-rollo.selected');
            if (!opcionSeleccionada) {
                alert('Por favor, seleccione una opción');
                return;
            }

            const numeroOpcion = opcionSeleccionada.dataset.opcion;
            
            displayPopUp();
            cerrarModalSeleccionOpcion();

            fetch(`../../php/cotizaciones/cambiar_estado.php?id=${id}&estado=${estado}&opcion=${numeroOpcion}`)
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

    // Cerrar modal de selección
    if (typeof cerrarModalSeleccionOpcion === 'undefined') {
        window.cerrarModalSeleccionOpcion = function() {
            const modal = document.getElementById('modalSeleccionOpcion');
            if (modal) {
                modal.remove();
            }
        };
    }
});