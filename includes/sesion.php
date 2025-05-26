<?php
    session_start();

    function tieneSesion() {
        if(isset($_SESSION['usuario'])) {
            return true;
        } else {
            return false;
        }
    }
?>