<?php
/**
 * Index - Redirección a la página de login
 * Postventa Centinela
 */
require_once 'includes/config.php';

if (isset($_SESSION['usuario_id'])) {
    $paginaInicio = (isset($_SESSION['usuario_rol']) && $_SESSION['usuario_rol'] === 'admin_sistema') ? 'dashboard2.php' : 'dashboard.php';
    header('Location: ' . $paginaInicio);
} else {
    header('Location: login.php');
}
exit;
