<?php
/**
 * Cerrar Sesión - Postventa Centinela
 */
require_once 'includes/config.php';

// Destruir sesión
session_destroy();

// Eliminar cookie de recordarme
if (isset($_COOKIE['remember_email'])) {
    setcookie('remember_email', '', time() - 3600, '/');
}

// Redirigir al login
header('Location: login.php?logout=1');
exit;
