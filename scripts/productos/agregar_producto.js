document.addEventListener('DOMContentLoaded', function () {
    const inputImagen = document.getElementById('imagenInput');
    const contenedorFoto = document.getElementById('contenedorFoto');
    const contenedorNoFoto = document.getElementById('contenedorNoFoto');
    const preview = document.getElementById('imagenPreview');
    const tipoProducto = document.getElementById('tipoProducto');
    const btnAdd = document.querySelector('.btnadd');
    const form = document.getElementById('formProducto');
    const campoModelo = document.getElementById('campoModelo');
    const modalModelo = document.getElementById('modalModelo');
    const btnAgregarModelo = document.getElementById('btnAgregarModelo');
    const btnGuardarModelo = document.getElementById('btnGuardarModelo');
    const nombreModelo = document.getElementById('nombreModelo');
    const alturaModelo = document.getElementById('alturaModelo');
    const selectModelo = document.getElementById('selectModelo');

    document.getElementById('btnSubirImg').onclick =
        document.getElementById('btnSubirSinImg').onclick = () => inputImagen.click();

    inputImagen.addEventListener('change', function (e) {
        const file = e.target.files[0];
        const maxSize = 2 * 1024 * 1024; // 2MB

        if (!file) return;

        if (!file.type.startsWith('image/')) {
            displayMensajeError("Por favor seleccione un archivo de imagen válido.");
            return;
        }

        if (file.size > maxSize) {
            displayMensajeError("La imagen es demasiado grande (máximo 2MB).");
            return;
        }

        const reader = new FileReader();
        reader.onload = function (ev) {
            preview.src = ev.target.result;
            preview.alt = "Previsualización de nueva imagen del producto";
            contenedorFoto.style.display = 'flex';
            contenedorNoFoto.style.display = 'none';
        };
        reader.readAsDataURL(file);
    });

    tipoProducto.addEventListener('change', actualizarCamposPasto);
    actualizarCamposPasto();

    function actualizarCamposPasto() {
        const tipoProductoValue = parseInt(tipoProducto.value);
        const tipoInventario = document.getElementById('tipoInventario');
        const unidad = document.getElementById('unidad');

        if (tipoProductoValue === 1) {
            tipoInventario.value = 'rollo';
            tipoInventario.disabled = false;
            campoModelo.style.display = 'block';

            unidad.value = 1;
        } else {
            tipoInventario.value = 'unidad';
            tipoInventario.disabled = false;
            campoModelo.style.display = 'none';

            unidad.value = 2; 
        }
    }

    btnAgregarModelo.addEventListener('click', () => {
        console.log("Opening modal");
        nombreModelo.value = '';
        alturaModelo.value = '';
        modalModelo.style.display = 'flex';
        document.body.style.overflow = 'hidden';
    });

    modalModelo.addEventListener('click', (e) => {
        if (e.target === modalModelo) {
            cerrarModal();
        }
    });

    function cerrarModal() {
        modalModelo.style.display = 'none';
        document.body.style.overflow = 'auto';
    }

    btnGuardarModelo.addEventListener('click', async () => {
        const nombre = nombreModelo.value.trim();
        const altura = parseFloat(alturaModelo.value) || 0;

        if (!nombre) {
            displayMensajeError("El nombre del modelo es requerido");
            nombreModelo.focus();
            return;
        }

        btnGuardarModelo.disabled = true;
        btnGuardarModelo.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Guardando...';

        try {
            const response = await fetch('../../php/productos/agregar_modelos.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    nombre: nombre,
                    altura_mm: altura
                })
            });

            const data = await response.json();

            if (data.status === 1) {
                const option = document.createElement('option');
                option.value = data.id;
                option.textContent = nombre + (altura ? ` (${altura}mm)` : '');
                selectModelo.appendChild(option);

                selectModelo.value = data.id;

                cerrarModal();
            } else {
                throw new Error(data.mensaje || "Error al guardar el modelo");
            }
        } catch (error) {
            displayMensajeError(error.message);
        } finally {
            btnGuardarModelo.disabled = false;
            btnGuardarModelo.innerHTML = 'Guardar';
        }
    });

    window.agregar = function () {
        const formData = new FormData(form);
        const nombre = formData.get("nombre")?.trim();

        if (!nombre) {
            displayMensajeError("Favor de indicar el nombre del producto.");
            form.nombre.focus();
            return;
        }

        if (!formData.get("id_unidad")) {
            displayMensajeError("Favor de seleccionar una unidad de medida.");
            return;
        }

        if (!formData.get("tipo_inventario")) {
            displayMensajeError("Seleccione el tipo de inventario: unidad o rollo.");
            return;
        }

        if (tipoProducto.value == '1' && !formData.get("id_modelo")) {
            displayMensajeError("Seleccione un modelo para el pasto.");
            return;
        }

        if (!confirm("¿Está seguro que desea agregar este producto?")) return;

        btnAdd.disabled = true;
        btnAdd.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Procesando...';
        displayPopUp("Agregando producto...");

        fetch('../../php/productos/agregar.php?t=' + Date.now(), {
            method: 'POST',
            body: formData
        })
            .then(response => {
                if (!response.ok) throw new Error('Error en la respuesta del servidor');
                return response.json();
            })
            .then(data => {
                if (data.status == 0) {
                    throw new Error(data.mensaje || "Error desconocido del servidor");
                }
                displayMensajeExitoso(data.mensaje, "window.history.back()");
            })
            .catch(error => {
                console.error('Error:', error);
                displayMensajeError("Error: " + error.message);
            })
            .finally(() => {
                btnAdd.disabled = false;
                btnAdd.innerHTML = 'Agregar';
            });
    };
});