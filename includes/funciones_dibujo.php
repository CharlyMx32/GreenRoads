<?php
/**
 * Función para obtener el dibujo del terreno de una cotización
 */
function obtenerDibujoTerreno($conn, $id_cotizacion) {
    $sql = "SELECT dibujo_terreno FROM cotizaciones WHERE id = ?";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "i", $id_cotizacion);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    
    if ($row = mysqli_fetch_assoc($result)) {
        return $row['dibujo_terreno'];
    }
    
    return null;
}

/**
 * Función para mostrar el dibujo del terreno en HTML
 */
function mostrarDibujoTerreno($dibujo_base64, $width = "300", $height = "200") {
    if (empty($dibujo_base64)) {
        return '<div class="sin-dibujo">
                    <p style="text-align: center; color: #999; padding: 20px;">
                        <i class="fas fa-image" style="font-size: 24px;"></i><br>
                        No hay diseño disponible
                    </p>
                </div>';
    }
    
    return '<div class="dibujo-terreno">
                <img src="' . htmlspecialchars($dibujo_base64) . '" 
                     alt="Diseño del terreno" 
                     style="width: ' . $width . 'px; height: ' . $height . 'px; border: 1px solid #ddd; border-radius: 5px;"
                     class="imagen-terreno">
            </div>';
}

/**
 * Función para actualizar el dibujo de una cotización
 */
function actualizarDibujoTerreno($conn, $id_cotizacion, $dibujo_base64) {
    $sql = "UPDATE cotizaciones SET dibujo_terreno = ? WHERE id = ?";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "si", $dibujo_base64, $id_cotizacion);
    
    return mysqli_stmt_execute($stmt);
}
?>
