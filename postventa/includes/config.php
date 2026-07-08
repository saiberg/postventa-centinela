<?php
/**
 * Configuración de la aplicación de Postventa
 * Inmobiliaria Centinela
 */

// Configuración de base de datos (para uso futuro)
define('DB_HOST', '127.0.0.1');
define('DB_USER', 'root');
define('DB_PASS', 'S1gr04040');
define('DB_NAME', 'postventa_centinela');
define('DB_CHARSET', 'utf8');

// Rutas base - detección automática del directorio
// $_SERVER['SCRIPT_NAME'] = /icentinela.cl/postventa/login.php → dirname = /icentinela.cl/postventa
$basePath = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/') . '/';
define('BASE_URL', $basePath);
define('ASSETS_URL', BASE_URL . 'assets/');
define('IMG_URL', ASSETS_URL . 'img/');

// Título del sitio
define('SITE_NAME', 'Postventa Centinela');

// Iniciar sesión
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
