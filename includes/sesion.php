<?php
session_start();

function tieneSesion()
{
    if (isset($_SESSION['usuario'])) {
        return true;
    } else {
        return false;
    }
}

function rolUsuario()
{
    return $_SESSION['rol'] ?? null;
}

function esAdmin() {
    return isset($_SESSION['rol']) && $_SESSION['rol'] == 1;
}

