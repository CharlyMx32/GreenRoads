<!-- POPUP ACCIÓN -->
<div class="popup" id="popupAccion">
    <div class="icono-popup" id="iconAccion" style="border:none;"><i class="fas fa-spinner fa-spin"></i></div>
    <div class="mensaje-popup" id="mensajeAccion">Cargando...</div>
    <div class="btn-popup" id="btnAccion" style="display:none;">Continuar</div>
</div>

<script>
    function displayPopUp() {
        $('#popupAccion').css('display', 'block');
    }

    function hidePopup() {
        $('#popupAccion').css('display', 'none');
        $('#iconAccion').html('<i class="fas fa-spinner fa-spin"></i>');
        $('#mensajeAccion').html('Cargando');
        $('#btnAccion').css('display', 'none');
    }

    function displayMensajeError(mensaje, accion = 'hidePopup()') {
        $('#iconAccion').html('<i class="fal fa-times-circle"></i>');
        $('#mensajeAccion').html(mensaje);
        $('#btnAccion').css('display', 'block');
        $('#btnAccion').attr('onclick', accion);
    }

    function displayMensajeExitoso(mensaje, accion = "window.location.reload()") {
        $('#iconAccion').html('<i class="fal fa-check-circle"></i>');
        $('#mensajeAccion').html(mensaje);
        $('#btnAccion').css('display', 'block');
        $('#btnAccion').attr('onclick', accion);
    }
</script>