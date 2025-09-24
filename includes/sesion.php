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

function esVendedor() {
    return isset($_SESSION['rol']) && $_SESSION['rol'] == 2;
}

function esInstalador() {
    return isset($_SESSION['rol']) && $_SESSION['rol'] == 3;
}

/**
 * Funciones para verificar permisos específicos por módulo
 */

// Permisos para cotizaciones
function puedeVerCotizaciones() {
    $rol = rolUsuario();
    return $rol == 1 || $rol == 2; // Admin o Vendedor
}

function puedeCrearCotizaciones() {
    $rol = rolUsuario();
    return $rol == 1 || $rol == 2; // Admin o Vendedor
}

function puedeEditarCotizaciones() {
    $rol = rolUsuario();
    return $rol == 1 || $rol == 2; // Admin o Vendedor
}

// Permisos para citas
function puedeVerCitas() {
    $rol = rolUsuario();
    return $rol == 1 || $rol == 2; // Admin o Vendedor
}

function puedeCrearCitas() {
    $rol = rolUsuario();
    return $rol == 1 || $rol == 2; // Admin o Vendedor
}

function puedeEditarCitas() {
    $rol = rolUsuario();
    return $rol == 1 || $rol == 2; // Admin o Vendedor
}

// Permisos para instalaciones
function puedeVerInstalaciones() {
    $rol = rolUsuario();
    return $rol == 1 || $rol == 3; // Admin o Instalador
}

function puedeGestionarInstalaciones() {
    $rol = rolUsuario();
    return $rol == 1 || $rol == 3; // Admin o Instalador
}

function puedeVerTodasLasInstalaciones() {
    $rol = rolUsuario();
    return $rol == 1; // Solo Admin puede ver todas
}

function puedeVerInstalacion($id_tecnico_responsable) {
    $rol = rolUsuario();
    $usuario_id = $_SESSION['usuario'] ?? null;
    
    if ($rol == 1) return true; // Admin puede ver todas
    if ($rol == 3 && $id_tecnico_responsable == $usuario_id) return true; // Instalador puede ver solo las suyas
    
    return false;
}

// Permisos para inventario
function puedeVerInventario() {
    $rol = rolUsuario();
    return $rol == 1; // Solo Admin
}

function puedeEditarInventario() {
    $rol = rolUsuario();
    return $rol == 1; // Solo Admin
}

// Permisos para productos
function puedeVerProductos() {
    $rol = rolUsuario();
    return $rol == 1; // Solo Admin
}

function puedeEditarProductos() {
    $rol = rolUsuario();
    return $rol == 1; // Solo Admin
}

// Permisos para usuarios
function puedeVerUsuarios() {
    $rol = rolUsuario();
    return $rol == 1; // Solo Admin
}

function puedeEditarUsuarios() {
    $rol = rolUsuario();
    return $rol == 1; // Solo Admin
}

// Permisos para clientes
function puedeVerClientes() {
    $rol = rolUsuario();
    return $rol == 1 || $rol == 2; // Admin o Vendedor
}

function puedeEditarClientes() {
    $rol = rolUsuario();
    return $rol == 1 || $rol == 2; // Admin o Vendedor
}

function puedeVerTodasLasCotizaciones() {
    $rol = rolUsuario();
    return $rol == 1; // Solo Admin puede ver todas
}

function puedeVerCotizacion($id_admin_cotizacion) {
    $rol = rolUsuario();
    $usuario_id = $_SESSION['usuario'] ?? null;
    
    if ($rol == 1) return true; // Admin puede ver todas
    if ($rol == 2 && $id_admin_cotizacion == $usuario_id) return true; // Vendedor puede ver solo las suyas
    
    return false;
}

function puedeVerTodasLasCitas() {
    $rol = rolUsuario();
    return $rol == 1; // Solo Admin puede ver todas
}

function puedeVerCita($id_admin_cita) {
    $rol = rolUsuario();
    $usuario_id = $_SESSION['usuario'] ?? null;
    
    if ($rol == 1) return true; // Admin puede ver todas
    if ($rol == 2 && $id_admin_cita == $usuario_id) return true; // Vendedor puede ver solo las suyas
    
    return false;
}

// Permisos para configuración
function puedeVerConfiguracion() {
    $rol = rolUsuario();
    return $rol == 1; // Solo Admin
}

// Permisos para extras
function puedeVerExtras() {
    $rol = rolUsuario();
    return $rol == 1; // Solo Admin
}

