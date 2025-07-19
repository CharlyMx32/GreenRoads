<?php
    session_start();

    function tieneSesion() {
        if(isset($_SESSION['usuario'])) {
            return true;
        } else {
            return false;
        }
    }

    function obtenerRol() {
        if (isset($_SESSION['rol'])) {
            return $_SESSION['rol'];
        } else {
            return null;
        }
    }
?>