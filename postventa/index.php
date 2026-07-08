<?php
/**
 * Index - Redirección a la página de login
 * Postventa Centinela
 */
require_once 'includes/config.php';

if (isset($_SESSION['usuario_id'])) {
    header('Location: dashboard.php');
} else {
    header('Location: login.php');
}
exit;
