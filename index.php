<?php
// index.php - Página de inicio
session_start();

// Si el usuario ya está logueado, redirigir al dashboard
if (isset($_SESSION['id_usuario'])) {
    header("Location: dashboard.php");
    exit();
}

// Si no está logueado, redirigir al login
header("Location: login.php");
exit();
?>