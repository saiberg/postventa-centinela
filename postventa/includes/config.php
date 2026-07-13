<?php
/**
 * Configuración de la aplicación de Postventa
 * Inmobiliaria Centinela
 */

// Configuración de base de datos
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', 'charly2000');
define('DB_NAME', 'postventa');
define('DB_CHARSET', 'utf8');

// Configuración SIGRO (para creación de casos al aprobar solicitudes)
define('SIGRO_OBRA_ID', '223');
define('SIGRO_INMOBILIARIA_ID', '45');
define('SIGRO_CATEGORIA_ID', '4471');
define('SIGRO_CATEGORIA_DETALLE_ID', '8197');
define('SIGRO_INMOBILIARIA_USUARIO_ID', '212');
define('SIGRO_USUARIO_ID', '1');
define('SIGRO_ARCHIVOS_PATH', __DIR__ . '/../../../postventa/archivos/casos/');

// Conexión a la base de datos
function getDB() {
    static $db = null;
    if ($db === null) {
        $db = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
        if ($db->connect_error) {
            die('Error de conexión a la base de datos: ' . $db->connect_error);
        }
        $db->set_charset(DB_CHARSET);
    }
    return $db;
}

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
