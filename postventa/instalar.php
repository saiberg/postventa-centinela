<?php
/**
 * Instalador de Base de Datos - Postventa Centinela
 * Ejecutar UNA SOLA VEZ desde el navegador para crear las tablas
 */
require_once 'includes/config.php';

// Conectar directamente a la base de datos existente
$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
if ($conn->connect_error) {
    die('<div class="alert alert-danger">Error de conexión: ' . $conn->connect_error . '</div>');
}
$conn->set_charset(DB_CHARSET);

// ========== TABLA: icentPventaUsuarios ==========
$sqlUsuarios = "CREATE TABLE IF NOT EXISTS `icentPventaUsuarios` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `rut` VARCHAR(20) DEFAULT NULL,
    `nombre` VARCHAR(150) NOT NULL,
    `email` VARCHAR(150) NOT NULL,
    `password` VARCHAR(255) NOT NULL,
    `telefono` VARCHAR(30) DEFAULT NULL,
    `rol` ENUM('propietario','administrador_edificio','admin_sistema') NOT NULL DEFAULT 'propietario',
    `token_recuperacion` VARCHAR(100) DEFAULT NULL,
    `token_expiracion` DATETIME DEFAULT NULL,
    `activo` TINYINT(1) NOT NULL DEFAULT 1,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `email` (`email`),
    KEY `idx_rut` (`rut`),
    KEY `idx_activo` (`activo`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci";

if (!$conn->query($sqlUsuarios)) {
    die('<div class="alert alert-danger">Error al crear icentPventaUsuarios: ' . $conn->error . '</div>');
}

// ========== TABLA: icentPventaSolicitudes ==========
$sqlSolicitudes = "CREATE TABLE IF NOT EXISTS `icentPventaSolicitudes` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `usuario_id` INT(11) NOT NULL,
    `rut` VARCHAR(20) DEFAULT NULL,
    `nombre` VARCHAR(150) DEFAULT NULL,
    `email` VARCHAR(150) DEFAULT NULL,
    `telefono` VARCHAR(30) DEFAULT NULL,
    `rol_solicitante` ENUM('propietario','administrador_edificio') NOT NULL DEFAULT 'propietario',
    `ubicacion_tipo` ENUM('departamento','estacionamiento','bodega','area_comun') NOT NULL DEFAULT 'departamento',
    `ubicacion_valor` VARCHAR(100) DEFAULT NULL,
    `categoria` VARCHAR(50) NOT NULL,
    `subcategoria` VARCHAR(100) NOT NULL,
    `detalle` TEXT DEFAULT NULL,
    `dias_disponibles` TEXT DEFAULT NULL,
    `estado` ENUM('pendiente','aprobado','no_corresponde','agendado','en_proceso','resuelto') NOT NULL DEFAULT 'pendiente',
    `fecha_agendamiento` DATETIME DEFAULT NULL,
    `equipo_asignado` VARCHAR(150) DEFAULT NULL,
    `comentario_admin` TEXT DEFAULT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_usuario_id` (`usuario_id`),
    KEY `idx_estado` (`estado`),
    KEY `idx_categoria` (`categoria`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci";

if (!$conn->query($sqlSolicitudes)) {
    die('<div class="alert alert-danger">Error al crear icentPventaSolicitudes: ' . $conn->error . '</div>');
}

// ========== TABLA: icentPventaArchivos ==========
$sqlArchivos = "CREATE TABLE IF NOT EXISTS `icentPventaArchivos` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `solicitud_id` INT(11) NOT NULL,
    `nombre_original` VARCHAR(255) NOT NULL,
    `nombre_archivo` VARCHAR(255) NOT NULL,
    `tipo` VARCHAR(20) NOT NULL,
    `tamano` INT(11) NOT NULL,
    `ruta` VARCHAR(500) NOT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_solicitud_id` (`solicitud_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci";

if (!$conn->query($sqlArchivos)) {
    die('<div class="alert alert-danger">Error al crear icentPventaArchivos: ' . $conn->error . '</div>');
}

// ========== TABLA: icentPventaSeguimiento ==========
$sqlSeguimiento = "CREATE TABLE IF NOT EXISTS `icentPventaSeguimiento` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `solicitud_id` INT(11) NOT NULL,
    `usuario_id` INT(11) DEFAULT NULL,
    `comentario` TEXT NOT NULL,
    `tipo` ENUM('sistema','admin','cliente','tecnico') NOT NULL DEFAULT 'sistema',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_solicitud_id` (`solicitud_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci";

if (!$conn->query($sqlSeguimiento)) {
    die('<div class="alert alert-danger">Error al crear icentPventaSeguimiento: ' . $conn->error . '</div>');
}

// ========== Insertar usuarios de prueba ==========
// Admin del sistema
$adminPassword = password_hash('admin123', PASSWORD_BCRYPT);
$sqlAdmin = "INSERT IGNORE INTO `icentPventaUsuarios` (`rut`, `nombre`, `email`, `password`, `telefono`, `rol`) 
             VALUES ('99.999.999-9', 'Admin Postventa', 'admin@icentinela.cl', '$adminPassword', '+56 9 9999 9999', 'admin_sistema')";
$conn->query($sqlAdmin);

// Usuario propietario de prueba
$userPassword = password_hash('cliente123', PASSWORD_BCRYPT);
$sqlUser = "INSERT IGNORE INTO `icentPventaUsuarios` (`rut`, `nombre`, `email`, `password`, `telefono`, `rol`) 
            VALUES ('12.345.678-9', 'Carlos Muñoz R.', 'carlos@email.com', '$userPassword', '+56 9 1234 5678', 'propietario')";
$conn->query($sqlUser);

// Administrador de edificio de prueba
$sqlAdminEdif = "INSERT IGNORE INTO `icentPventaUsuarios` (`rut`, `nombre`, `email`, `password`, `telefono`, `rol`) 
                 VALUES ('11.223.344-5', 'Pedro Soto A.', 'pedro.soto@email.com', '$userPassword', '+56 9 5544 3322', 'administrador_edificio')";
$conn->query($sqlAdminEdif);

$conn->close();

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Instalador - Postventa Centinela</title>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Montserrat', sans-serif; background: #f8f9fa; display: flex; align-items: center; justify-content: center; min-height: 100vh; margin: 0; padding: 20px; }
        .container { background: #fff; border-radius: 10px; box-shadow: 0 8px 32px rgba(0,0,0,0.1); max-width: 650px; width: 100%; overflow: hidden; }
        .header { background: #608418; color: #fff; padding: 28px 32px; text-align: center; }
        .header h1 { margin: 0; font-size: 1.4rem; }
        .header p { margin: 6px 0 0; opacity: 0.9; font-size: 0.9rem; }
        .body { padding: 32px; }
        .success { background: #d4edda; color: #155724; padding: 16px; border-radius: 6px; border-left: 4px solid #28a745; margin-bottom: 20px; }
        .success h3 { margin: 0 0 8px; }
        .success ul { margin: 0; padding-left: 20px; }
        .success li { margin-bottom: 4px; font-size: 0.9rem; }
        .info { background: #e7f1ff; color: #004085; padding: 16px; border-radius: 6px; border-left: 4px solid #0d6efd; margin-bottom: 20px; font-size: 0.9rem; }
        .info strong { display: block; margin-bottom: 4px; }
        table { width: 100%; border-collapse: collapse; margin-top: 16px; }
        th, td { padding: 8px 12px; text-align: left; border-bottom: 1px solid #dee2e6; font-size: 0.85rem; }
        th { background: #f8f9fa; font-weight: 600; }
        code { background: #f4f4f4; padding: 2px 6px; border-radius: 3px; font-size: 0.85rem; }
        .btn { display: inline-block; padding: 12px 24px; background: #608418; color: #fff; text-decoration: none; border-radius: 3px; font-weight: 600; font-size: 0.85rem; text-transform: uppercase; margin-top: 16px; }
        .btn:hover { background: #4a6b10; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>✅ Instalación Completada</h1>
            <p>Base de datos configurada correctamente</p>
        </div>
        <div class="body">
            <div class="success">
                <h3>Tablas creadas:</h3>
                <ul>
                    <li><strong>icentPventaUsuarios</strong> — Usuarios del sistema</li>
                    <li><strong>icentPventaSolicitudes</strong> — Solicitudes de postventa</li>
                    <li><strong>icentPventaArchivos</strong> — Archivos adjuntos (evidencia)</li>
                    <li><strong>icentPventaSeguimiento</strong> — Seguimiento de casos</li>
                </ul>
            </div>
            
            <div class="info">
                <strong>Usuarios de prueba creados:</strong>
            </div>
            <table>
                <thead>
                    <tr><th>Rol</th><th>Email</th><th>Contraseña</th></tr>
                </thead>
                <tbody>
                    <tr><td>Admin Sistema</td><td><code>admin@icentinela.cl</code></td><td><code>admin123</code></td></tr>
                    <tr><td>Propietario</td><td><code>carlos@email.com</code></td><td><code>cliente123</code></td></tr>
                    <tr><td>Admin Edificio</td><td><code>pedro.soto@email.com</code></td><td><code>cliente123</code></td></tr>
                </tbody>
            </table>
            
            <div style="text-align: center;">
                <a href="login.php" class="btn">Ir al Inicio de Sesión</a>
            </div>
            
            <p style="text-align: center; margin-top: 16px; font-size: 0.8rem; color: #999;">
                ⚠️ Por seguridad, elimina o renombra este archivo <code>instalar.php</code> después de la instalación.
            </p>
        </div>
    </div>
</body>
</html>
