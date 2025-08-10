<!-- Ejemplo de uso en una página de cotización o instalación -->
<?php
// En el archivo donde quieras mostrar el dibujo (ej: ver_cotizacion.php o instalacion.php)
include_once 'includes/funciones_dibujo.php';

// Obtener el ID de la cotización
$id_cotizacion = $_GET['id'] ?? 0;

// Obtener el dibujo
$dibujo = obtenerDibujoTerreno($conn, $id_cotizacion);
?>

<div class="seccion-terreno">
    <h3>Diseño del Terreno</h3>
    <?= mostrarDibujoTerreno($dibujo, "400", "300") ?>
    
    <!-- Botón para ampliar imagen (opcional) -->
    <?php if (!empty($dibujo)): ?>
        <button type="button" class="btn btn-secondary" onclick="ampliarDibujo()">
            <i class="fas fa-expand"></i> Ver más grande
        </button>
    <?php endif; ?>
</div>

<!-- Modal para mostrar imagen ampliada -->
<div id="modalDibujo" class="modal" style="display: none;">
    <div class="modal-content" style="max-width: 800px;">
        <div class="modal-header">
            <h4>Diseño del Terreno</h4>
            <span class="close-modal" onclick="cerrarModal()">&times;</span>
        </div>
        <div class="modal-body" style="text-align: center;">
            <?= mostrarDibujoTerreno($dibujo, "700", "500") ?>
        </div>
    </div>
</div>

<script>
function ampliarDibujo() {
    document.getElementById('modalDibujo').style.display = 'block';
}

function cerrarModal() {
    document.getElementById('modalDibujo').style.display = 'none';
}

// Cerrar modal al hacer clic fuera de él
window.onclick = function(event) {
    const modal = document.getElementById('modalDibujo');
    if (event.target === modal) {
        modal.style.display = 'none';
    }
}
</script>
