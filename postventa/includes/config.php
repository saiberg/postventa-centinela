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

// ID de la inmobiliaria para filtrar obras en el formulario de solicitud
define('INMOBILIARIA_ID', '45');

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

// ============================================================
// Configuración de upload de archivos
// ============================================================

// true = acepta cualquier formato sin validar tipo MIME
define('ALLOW_ALL_FORMATS', true);

// Lista de MIME types permitidos (solo se usa si ALLOW_ALL_FORMATS = false)
// Formato: 'mime/type' => 'categoria' (imagen o video)
const ALLOWED_FILE_TYPES = [
    'image/jpeg' => 'imagen',
    'image/png'  => 'imagen',
    'image/gif'  => 'imagen',
    'image/webp' => 'imagen',
    'video/mp4'  => 'video',
    'video/webm' => 'video',
];

// Tamaño máximo por archivo (50 MB)
define('MAX_FILE_SIZE', 50 * 1024 * 1024);

// ============================================================
// Configuración de Correos (PHPMailer)
// ============================================================

// Servidor SMTP
define('EMAIL_SMTP_HOST', 'smtp.gmail.com');
define('EMAIL_SMTP_PORT', '587');       // 587 = TLS, 465 = SSL
define('EMAIL_SMTP_USER', 'esteban.osorio@gmail.com');          // Usuario/correo SMTP
define('EMAIL_SMTP_PASS', 'unlz ctzb hdpl pktn');          // Contraseña o App Password

// Remitente
define('EMAIL_FROM', 'noreply@icentinela.cl');
define('EMAIL_FROM_NAME', 'Postventa Centinela');

// Destinatario para notificaciones al administrador
define('EMAIL_ADMIN_DESTINO', 'marcomoraleschile@gmail.com');

// ============================================================
// Plantilla: Correo al CLIENTE (respaldo de solicitud)
// ============================================================
// Asunto
define('EMAIL_CLIENTE_ASUNTO', 'Confirmación de Solicitud de Postventa - {{SOLICITUD_ID}}');
// Cuerpo HTML (placeholders: {{NOMBRE}} {{RUT}} {{EMAIL}} {{TELEFONO}} {{SOLICITUD_ID}}
//   {{CATEGORIA}} {{SUBCATEGORIA}} {{UBICACION}} {{DETALLE}} {{DIAS}} {{FECHA}} {{URL_BASE}})
define('EMAIL_CLIENTE_CUERPO',
'<h2>Estimado/a {{NOMBRE}},</h2>
<p>Su solicitud de postventa ha sido registrada exitosamente con el número <strong>{{SOLICITUD_ID}}</strong>.</p>
<h3>Detalle de la Solicitud:</h3>
<table style="border-collapse:collapse;width:100%">
<tr><td style="padding:8px;border:1px solid #ddd;background:#f5f5f5"><strong>Categoría:</strong></td><td style="padding:8px;border:1px solid #ddd">{{CATEGORIA}}</td></tr>
<tr><td style="padding:8px;border:1px solid #ddd;background:#f5f5f5"><strong>Subcategoría:</strong></td><td style="padding:8px;border:1px solid #ddd">{{SUBCATEGORIA}}</td></tr>
<tr><td style="padding:8px;border:1px solid #ddd;background:#f5f5f5"><strong>Ubicación:</strong></td><td style="padding:8px;border:1px solid #ddd">{{UBICACION}}</td></tr>
<tr><td style="padding:8px;border:1px solid #ddd;background:#f5f5f5"><strong>Detalle:</strong></td><td style="padding:8px;border:1px solid #ddd">{{DETALLE}}</td></tr>
<tr><td style="padding:8px;border:1px solid #ddd;background:#f5f5f5"><strong>Días disponibles:</strong></td><td style="padding:8px;border:1px solid #ddd">{{DIAS}}</td></tr>
<tr><td style="padding:8px;border:1px solid #ddd;background:#f5f5f5"><strong>Fecha:</strong></td><td style="padding:8px;border:1px solid #ddd">{{FECHA}}</td></tr>
</table>
<p>Puede hacer seguimiento de su solicitud ingresando a <a href="{{URL_BASE}}dashboard.php">{{URL_BASE}}dashboard.php</a></p>
<p>Saludos cordiales,<br>Equipo de Postventa Centinela</p>');

// ============================================================
// Plantilla: Correo al ADMINISTRADOR (notificación de nueva solicitud)
// ============================================================
// Asunto
define('EMAIL_ADMIN_ASUNTO', 'Nueva Solicitud de Postventa - {{SOLICITUD_ID}} - {{NOMBRE}}');
// Cuerpo HTML
define('EMAIL_ADMIN_CUERPO',
'<h2>Nueva Solicitud de Postventa</h2>
<p>Se ha registrado una nueva solicitud en el sistema.</p>
<h3>Datos del Solicitante:</h3>
<table style="border-collapse:collapse;width:100%">
<tr><td style="padding:8px;border:1px solid #ddd;background:#f5f5f5"><strong>Nombre:</strong></td><td style="padding:8px;border:1px solid #ddd">{{NOMBRE}}</td></tr>
<tr><td style="padding:8px;border:1px solid #ddd;background:#f5f5f5"><strong>RUT:</strong></td><td style="padding:8px;border:1px solid #ddd">{{RUT}}</td></tr>
<tr><td style="padding:8px;border:1px solid #ddd;background:#f5f5f5"><strong>Email:</strong></td><td style="padding:8px;border:1px solid #ddd">{{EMAIL}}</td></tr>
<tr><td style="padding:8px;border:1px solid #ddd;background:#f5f5f5"><strong>Teléfono:</strong></td><td style="padding:8px;border:1px solid #ddd">{{TELEFONO}}</td></tr>
</table>
<h3>Detalle de la Solicitud:</h3>
<table style="border-collapse:collapse;width:100%">
<tr><td style="padding:8px;border:1px solid #ddd;background:#f5f5f5"><strong>N° Solicitud:</strong></td><td style="padding:8px;border:1px solid #ddd">{{SOLICITUD_ID}}</td></tr>
<tr><td style="padding:8px;border:1px solid #ddd;background:#f5f5f5"><strong>Categoría:</strong></td><td style="padding:8px;border:1px solid #ddd">{{CATEGORIA}}</td></tr>
<tr><td style="padding:8px;border:1px solid #ddd;background:#f5f5f5"><strong>Subcategoría:</strong></td><td style="padding:8px;border:1px solid #ddd">{{SUBCATEGORIA}}</td></tr>
<tr><td style="padding:8px;border:1px solid #ddd;background:#f5f5f5"><strong>Ubicación:</strong></td><td style="padding:8px;border:1px solid #ddd">{{UBICACION}}</td></tr>
<tr><td style="padding:8px;border:1px solid #ddd;background:#f5f5f5"><strong>Detalle:</strong></td><td style="padding:8px;border:1px solid #ddd">{{DETALLE}}</td></tr>
<tr><td style="padding:8px;border:1px solid #ddd;background:#f5f5f5"><strong>Días disponibles:</strong></td><td style="padding:8px;border:1px solid #ddd">{{DIAS}}</td></tr>
<tr><td style="padding:8px;border:1px solid #ddd;background:#f5f5f5"><strong>Fecha:</strong></td><td style="padding:8px;border:1px solid #ddd">{{FECHA}}</td></tr>
</table>
<p>Ingrese al panel de administración para revisar: <a href="{{URL_BASE}}admin.php">{{URL_BASE}}admin.php</a></p>');

// ============================================================
// Plantilla: Correo al CLIENTE - Solicitud APROBADA
// ============================================================
define('EMAIL_CLIENTE_APROBADO_ASUNTO', 'Su solicitud {{SOLICITUD_ID}} ha sido APROBADA - Postventa Centinela');
define('EMAIL_CLIENTE_APROBADO_CUERPO',
'<h2>Estimado/a {{NOMBRE}},</h2>
<p>Nos complace informarle que su solicitud de postventa <strong>{{SOLICITUD_ID}}</strong> ha sido <strong style="color:#608418;">APROBADA</strong>.</p>
<h3>Detalle de la Solicitud:</h3>
<table style="border-collapse:collapse;width:100%">
<tr><td style="padding:8px;border:1px solid #ddd;background:#f5f5f5"><strong>N° Solicitud:</strong></td><td style="padding:8px;border:1px solid #ddd">{{SOLICITUD_ID}}</td></tr>
<tr><td style="padding:8px;border:1px solid #ddd;background:#f5f5f5"><strong>Categoría:</strong></td><td style="padding:8px;border:1px solid #ddd">{{CATEGORIA}}</td></tr>
<tr><td style="padding:8px;border:1px solid #ddd;background:#f5f5f5"><strong>Subcategoría:</strong></td><td style="padding:8px;border:1px solid #ddd">{{SUBCATEGORIA}}</td></tr>
<tr><td style="padding:8px;border:1px solid #ddd;background:#f5f5f5"><strong>Ubicación:</strong></td><td style="padding:8px;border:1px solid #ddd">{{UBICACION}}</td></tr>
<tr><td style="padding:8px;border:1px solid #ddd;background:#f5f5f5"><strong>Fecha:</strong></td><td style="padding:8px;border:1px solid #ddd">{{FECHA}}</td></tr>
</table>
<p>Nuestro equipo se contactará con usted para coordinar la visita técnica en los días indicados.</p>
<p>Puede hacer seguimiento en: <a href="{{URL_BASE}}dashboard.php">{{URL_BASE}}dashboard.php</a></p>
<p>Saludos cordiales,<br>Equipo de Postventa Centinela</p>');

// ============================================================
// Plantilla: Correo al CLIENTE - Solicitud RECHAZADA
// ============================================================
define('EMAIL_CLIENTE_RECHAZADO_ASUNTO', 'Su solicitud {{SOLICITUD_ID}} ha sido RECHAZADA - Postventa Centinela');
define('EMAIL_CLIENTE_RECHAZADO_CUERPO',
'<h2>Estimado/a {{NOMBRE}},</h2>
<p>Lamentamos informarle que su solicitud de postventa <strong>{{SOLICITUD_ID}}</strong> ha sido <strong style="color:#CC3366;">RECHAZADA</strong> por no corresponder a materias de postventa.</p>
<h3>Detalle de la Solicitud:</h3>
<table style="border-collapse:collapse;width:100%">
<tr><td style="padding:8px;border:1px solid #ddd;background:#f5f5f5"><strong>N° Solicitud:</strong></td><td style="padding:8px;border:1px solid #ddd">{{SOLICITUD_ID}}</td></tr>
<tr><td style="padding:8px;border:1px solid #ddd;background:#f5f5f5"><strong>Categoría:</strong></td><td style="padding:8px;border:1px solid #ddd">{{CATEGORIA}}</td></tr>
<tr><td style="padding:8px;border:1px solid #ddd;background:#f5f5f5"><strong>Subcategoría:</strong></td><td style="padding:8px;border:1px solid #ddd">{{SUBCATEGORIA}}</td></tr>
<tr><td style="padding:8px;border:1px solid #ddd;background:#f5f5f5"><strong>Ubicación:</strong></td><td style="padding:8px;border:1px solid #ddd">{{UBICACION}}</td></tr>
<tr><td style="padding:8px;border:1px solid #ddd;background:#f5f5f5"><strong>Motivo de rechazo:</strong></td><td style="padding:8px;border:1px solid #ddd">{{MOTIVO_RECHAZO}}</td></tr>
<tr><td style="padding:8px;border:1px solid #ddd;background:#f5f5f5"><strong>Fecha:</strong></td><td style="padding:8px;border:1px solid #ddd">{{FECHA}}</td></tr>
</table>
<p>Si tiene dudas sobre esta resolución, puede contactarnos a través de los canales oficiales.</p>
<p>Saludos cordiales,<br>Equipo de Postventa Centinela</p>');

// ============================================================
// Plantilla: Correo de RECUPERACIÓN DE CONTRASEÑA
// ============================================================
define('EMAIL_RECUPERAR_ASUNTO', 'Recuperación de Contraseña - Postventa Centinela');
define('EMAIL_RECUPERAR_CUERPO',
'<h2>Estimado/a {{NOMBRE}},</h2>
<p>Hemos recibido una solicitud para restablecer la contraseña de su cuenta en <strong>Postventa Centinela</strong>.</p>
<p>Para continuar con el proceso, haga clic en el siguiente enlace:</p>
<p style="text-align:center;margin:25px 0;">
  <a href="{{ENLACE}}" style="background-color:#608418;color:white;padding:12px 30px;text-decoration:none;border-radius:5px;font-weight:bold;display:inline-block;">Restablecer mi Contraseña</a>
</p>
<p>O copie y pegue el siguiente enlace en su navegador:</p>
<p style="word-break:break-all;color:#666;">{{ENLACE}}</p>
<p><strong>Este enlace expirará en 1 hora.</strong></p>
<p>Si usted no solicitó restablecer su contraseña, ignore este mensaje. Su cuenta permanece segura.</p>
<p>Saludos cordiales,<br>Equipo de Postventa Centinela</p>');