<?php
// auth.php - Archivo de autenticación
// Incluye este archivo en todas las páginas que requieran login

// Iniciar sesión si no está iniciada
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Verificar si el usuario está logueado
if (!isset($_SESSION['id_usuario'])) {
    header("Location: login.php");
    exit();
}

// Verificar timeout de sesión (opcional - 30 minutos)
$timeout = 1800; // 30 minutos en segundos
if (isset($_SESSION['login_time']) && (time() - $_SESSION['login_time'] > $timeout)) {
    // Sesión expirada
    session_unset();
    session_destroy();
    header("Location: login.php?timeout=1");
    exit();
}

// Actualizar el tiempo de última actividad
$_SESSION['login_time'] = time();

// Función helper para verificar si el usuario es admin
function esAdmin() {
    return isset($_SESSION['id_rol']) && $_SESSION['id_rol'] == 1;
}

// Función helper para verificar si el usuario es barbero
function esBarbero() {
    return isset($_SESSION['id_rol']) && $_SESSION['id_rol'] == 2;
}

// Función helper para verificar si el usuario es cliente
function esCliente() {
    return isset($_SESSION['id_rol']) && $_SESSION['id_rol'] == 3;
}

// Función para obtener el nombre del usuario actual
function getNombreUsuario() {
    return isset($_SESSION['nombre_usuario']) ? $_SESSION['nombre_usuario'] : 'Usuario';
}

// Función para obtener el rol del usuario actual
function getRolUsuario() {
    return isset($_SESSION['nombre_rol']) ? $_SESSION['nombre_rol'] : 'Sin rol';
}
?>