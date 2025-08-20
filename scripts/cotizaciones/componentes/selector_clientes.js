class SelectorClientes {
    constructor() {
        this.clientes = [];
        this.clientesFiltrados = [];
        this.clienteSeleccionado = null;
        this.indicePressSelected = -1;
        
        this.elements = {
            searchInput: document.getElementById('cliente-search'),
            hiddenInput: document.getElementById('cliente'),
            dropdown: document.getElementById('cliente-dropdown'),
            dropdownList: document.getElementById('cliente-dropdown-list'),
            selectedDiv: document.getElementById('cliente-selected'),
            clearBtn: document.getElementById('cliente-clear')
        };
        
        this.init();
    }
    
    async init() {
        await this.cargarClientes();
        this.configurarEventos();
        this.mostrarTodosLosClientes();
    }
    
    async cargarClientes() {
        try {
            
            const response = await fetch('../../php/clientes/obtener_clientes.php');
            const data = await response.json();
            
            if (data.success) {
                this.clientes = data.clientes;
                this.clientesFiltrados = [...this.clientes];
                
                // Actualizar placeholder con número de clientes
                this.elements.searchInput.placeholder = `Buscar entre ${data.total} clientes...`;
            } else {
                console.error('Error al cargar clientes:', data.message);
                this.clientes = [];
                this.elements.searchInput.placeholder = "Error al cargar clientes";
            }
        } catch (error) {
            console.error('Error al cargar clientes:', error);
            this.clientes = [];
            this.elements.searchInput.placeholder = "Error de conexión";
        } finally {
            // Ocultar estado de carga
            this.elements.searchInput.parentElement.classList.remove('loading');
        }
    }
    
    configurarEventos() {
        // Evento de búsqueda
        this.elements.searchInput.addEventListener('input', (e) => {
            this.buscarClientes(e.target.value);
        });
        
        // Eventos de teclado
        this.elements.searchInput.addEventListener('keydown', (e) => {
            this.manejarTeclado(e);
        });
        
        // Mostrar dropdown al hacer focus
        this.elements.searchInput.addEventListener('focus', () => {
            if (!this.clienteSeleccionado) {
                this.mostrarDropdown();
            }
        });
        
        // Ocultar dropdown al perder focus (con delay para permitir clicks)
        this.elements.searchInput.addEventListener('blur', () => {
            setTimeout(() => {
                this.ocultarDropdown();
            }, 200);
        });
        
        // Limpiar selección
        this.elements.clearBtn.addEventListener('click', () => {
            this.limpiarSeleccion();
        });
        
        // Cerrar dropdown al hacer click fuera
        document.addEventListener('click', (e) => {
            if (!e.target.closest('.cliente-selector-container')) {
                this.ocultarDropdown();
            }
        });
    }
    
    buscarClientes(termino) {
        const terminoLimpio = termino.toLowerCase().trim();
        
        if (terminoLimpio === '') {
            this.clientesFiltrados = [...this.clientes];
        } else {
            this.clientesFiltrados = this.clientes.filter(cliente => 
                cliente.nombre.toLowerCase().includes(terminoLimpio) ||
                cliente.telefono?.includes(terminoLimpio) ||
                cliente.email?.toLowerCase().includes(terminoLimpio)
            );
        }
        
        this.indicePressSelected = -1;
        this.renderizarResultados();
        this.mostrarDropdown();
    }
    
    mostrarTodosLosClientes() {
        this.clientesFiltrados = [...this.clientes];
        this.renderizarResultados();
    }
    
    renderizarResultados() {
        const container = this.elements.dropdownList;
        container.innerHTML = '';
        
        if (this.clientesFiltrados.length === 0) {
            container.innerHTML = `
                <div class="cliente-dropdown-empty">
                    <i class="fas fa-search"></i> No se encontraron clientes
                    <br><small>Intenta con otro término de búsqueda</small>
                </div>
            `;
            return;
        }
        
        // Agrupar clientes recientes
        const clientesRecientes = this.clientesFiltrados.filter(c => c.es_reciente);
        const clientesAntiguos = this.clientesFiltrados.filter(c => !c.es_reciente);
        
        // Mostrar clientes recientes primero
        if (clientesRecientes.length > 0 && this.elements.searchInput.value.trim() === '') {
            const header = document.createElement('div');
            header.className = 'cliente-dropdown-header';
            header.innerHTML = `<i class="fas fa-clock"></i> Clientes Recientes`;
            container.appendChild(header);
            
            clientesRecientes.forEach((cliente, index) => {
                container.appendChild(this.crearItemCliente(cliente, index, true));
            });
            
            if (clientesAntiguos.length > 0) {
                const divider = document.createElement('div');
                divider.className = 'cliente-dropdown-divider';
                container.appendChild(divider);
                
                const headerTodos = document.createElement('div');
                headerTodos.className = 'cliente-dropdown-header';
                headerTodos.innerHTML = `<i class="fas fa-users"></i> Todos los Clientes`;
                container.appendChild(headerTodos);
            }
        }
        
        // Mostrar todos los clientes (o los filtrados)
        const clientesAMostrar = this.elements.searchInput.value.trim() === '' ? clientesAntiguos : this.clientesFiltrados;
        clientesAMostrar.forEach((cliente, index) => {
            const indexReal = clientesRecientes.length + index;
            container.appendChild(this.crearItemCliente(cliente, indexReal, false));
        });
    }
    
    crearItemCliente(cliente, index, esReciente) {
        const item = document.createElement('div');
        item.className = `cliente-dropdown-item ${esReciente ? 'cliente-reciente' : ''}`;
        item.setAttribute('data-id', cliente.id);
        item.setAttribute('data-index', index);
        
        const iconoCliente = esReciente ? 'fa-star' : 'fa-user';
        const badgeReciente = esReciente ? '<span class="badge-reciente">Nuevo</span>' : '';
        
        item.innerHTML = `
            <i class="fas ${iconoCliente} cliente-icon"></i>
            <div style="flex: 1;">
                <div class="cliente-name">
                    ${this.resaltarTermino(cliente.nombre, this.elements.searchInput.value)}
                    ${badgeReciente}
                </div>
                <div class="cliente-info">
                    ${cliente.telefono ? `<span><i class="fas fa-phone"></i> ${cliente.telefono}</span>` : ''}
                    ${cliente.email ? `<span><i class="fas fa-envelope"></i> ${cliente.email}</span>` : ''}
                </div>
            </div>
        `;
        
        item.addEventListener('click', () => {
            this.seleccionarCliente(cliente);
        });
        
        return item;
    }
    
    resaltarTermino(texto, termino) {
        if (!termino.trim()) return texto;
        
        const regex = new RegExp(`(${termino.trim()})`, 'gi');
        return texto.replace(regex, '<mark style="background: #7dc042; color: white; padding: 1px 3px; border-radius: 2px;">$1</mark>');
    }
    
    seleccionarCliente(cliente) {
        this.clienteSeleccionado = cliente;
        this.elements.hiddenInput.value = cliente.id;
        this.elements.searchInput.style.display = 'none';
        this.elements.selectedDiv.style.display = 'flex';
        this.elements.selectedDiv.querySelector('.cliente-selected-name').textContent = cliente.nombre;
        
        this.ocultarDropdown();
        
        // Trigger evento change para formularios
        this.elements.hiddenInput.dispatchEvent(new Event('change'));
    }
    
    limpiarSeleccion() {
        this.clienteSeleccionado = null;
        this.elements.hiddenInput.value = '';
        this.elements.searchInput.value = '';
        this.elements.searchInput.style.display = 'block';
        this.elements.selectedDiv.style.display = 'none';
        this.elements.searchInput.focus();
        
        this.mostrarTodosLosClientes();
    }
    
    manejarTeclado(e) {
        const items = this.elements.dropdownList.querySelectorAll('.cliente-dropdown-item');
        
        switch (e.key) {
            case 'ArrowDown':
                e.preventDefault();
                this.indicePressSelected = Math.min(this.indicePressSelected + 1, items.length - 1);
                this.actualizarSeleccionTeclado(items);
                break;
                
            case 'ArrowUp':
                e.preventDefault();
                this.indicePressSelected = Math.max(this.indicePressSelected - 1, -1);
                this.actualizarSeleccionTeclado(items);
                break;
                
            case 'Enter':
                e.preventDefault();
                if (this.indicePressSelected >= 0 && items[this.indicePressSelected]) {
                    const clienteId = items[this.indicePressSelected].getAttribute('data-id');
                    const cliente = this.clientesFiltrados.find(c => c.id == clienteId);
                    if (cliente) {
                        this.seleccionarCliente(cliente);
                    }
                }
                break;
                
            case 'Escape':
                this.ocultarDropdown();
                this.elements.searchInput.blur();
                break;
        }
    }
    
    actualizarSeleccionTeclado(items) {
        items.forEach((item, index) => {
            item.classList.toggle('highlighted', index === this.indicePressSelected);
        });
        
        // Scroll automático para mantener el elemento visible
        if (this.indicePressSelected >= 0 && items[this.indicePressSelected]) {
            items[this.indicePressSelected].scrollIntoView({ block: 'nearest' });
        }
    }
    
    mostrarDropdown() {
        this.elements.dropdown.classList.add('visible');
    }
    
    ocultarDropdown() {
        this.elements.dropdown.classList.remove('visible');
        this.indicePressSelected = -1;
    }
    
    // Método público para establecer un cliente externamente
    establecerCliente(clienteId) {
        const cliente = this.clientes.find(c => c.id == clienteId);
        if (cliente) {
            this.seleccionarCliente(cliente);
        }
    }
    
    // Método público para obtener el cliente seleccionado
    obtenerClienteSeleccionado() {
        return this.clienteSeleccionado;
    }
}

// Auto-inicializar cuando el DOM esté listo
document.addEventListener('DOMContentLoaded', () => {
    if (document.getElementById('cliente-search')) {
        window.selectorClientes = new SelectorClientes();
    }
});
