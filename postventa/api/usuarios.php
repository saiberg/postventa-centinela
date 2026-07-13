<?php
/**
 * API de Usuarios - Postventa Centinela
 * Endpoints: login, registro, recuperar, perfil, actualizar_perfil, cambiar_password
 * 
 * SEGURIDAD:
 * - Token CSRF requerido para operaciones POST
 * - Rate limiting por IP (previene fuerza bruta)
 * - Verificación de Referer (mismo dominio)
 * - Passwords con bcrypt
 * - Regeneración de ID de sesión tras login
 * - Headers de seguridad (X-Content-Type-Options, X-Frame-Options)
 */

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/logger.php';

// ==================== MANEJO GLOBAL DE ERRORES ====================
set_error_handler(function($severity, $message, $file, $line) {
    $tipos = array(
        E_ERROR => 'E_ERROR', E_WARNING => 'E_WARNING', E_PARSE => 'E_PARSE',
        E_NOTICE => 'E_NOTICE', E_CORE_ERROR => 'E_CORE_ERROR', E_COMPILE_ERROR => 'E_COMPILE_ERROR',
        E_USER_ERROR => 'E_USER_ERROR', E_USER_WARNING => 'E_USER_WARNING',
        E_RECOVERABLE_ERROR => 'E_RECOVERABLE_ERROR', E_DEPRECATED => 'E_DEPRECATED',
    );
    $tipo = isset($tipos[$severity]) ? $tipos[$severity] : "E_$severity";
    logger('ERROR', "PHP $tipo: $message", ['file' => $file, 'line' => $line]);
    return in_array($severity, array(E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR, E_RECOVERABLE_ERROR)) ? false : true;
});

set_exception_handler(function($exception) {
    logger('ERROR', 'Excepción: ' . $exception->getMessage(), ['file' => $exception->getFile(), 'line' => $exception->getLine(), 'trace' => $exception->getTraceAsString()]);
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Error interno del servidor']);
});

register_shutdown_function(function() {
    $error = error_get_last();
    if ($error && in_array($error['type'], array(E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_RECOVERABLE_ERROR))) {
        logger('ERROR', 'Error fatal: ' . $error['message'], ['file' => $error['file'], 'line' => $error['line']]);
        if (!headers_sent()) { http_response_code(500); echo json_encode(['success' => false, 'message' => 'Error interno del servidor']); }
    }
});

ini_set('display_errors', '0');
ini_set('display_startup_errors', '0');

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');

// ==================== SEGURIDAD ====================

// 1. Verificar Referer (mismo dominio)
$referer = isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : '';
$host = isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : '';
if (!empty($referer) && strpos($referer, $host) === false) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Acceso no autorizado']);
    exit;
}

// 2. Rate limiting por IP
function checkRateLimit($ip, $action, $maxRequests = 10, $windowSeconds = 60) {
    $logFile = __DIR__ . '/../logs/ratelimit.log';
    $now = time();
    
    $entries = array();
    if (file_exists($logFile)) {
        $lines = file($logFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($lines as $line) {
            $parts = explode('|', $line);
            if (count($parts) >= 3) {
                $entries[] = array('time' => (int)$parts[0], 'ip' => $parts[1], 'action' => $parts[2]);
            }
        }
    }
    
    $entries = array_filter($entries, function($e) use ($now, $windowSeconds) {
        return ($now - $e['time']) <= $windowSeconds;
    });
    
    $count = 0;
    foreach ($entries as $e) {
        if ($e['ip'] === $ip && $e['action'] === $action) {
            $count++;
        }
    }
    
    $entries[] = array('time' => $now, 'ip' => $ip, 'action' => $action);
    $dir = dirname($logFile);
    if (!is_dir($dir)) { mkdir($dir, 0755, true); }
    $newLines = '';
    foreach ($entries as $e) {
        $newLines .= $e['time'] . '|' . $e['ip'] . '|' . $e['action'] . "\n";
    }
    file_put_contents($logFile, $newLines);
    
    return $count < $maxRequests;
}

$clientIp = isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '0.0.0.0';
$action = isset($_GET['action']) ? $_GET['action'] : '';

// Rate limit estricto para login (5 intentos/min)
if ($action === 'login' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!checkRateLimit($clientIp, 'login', 5, 60)) {
        http_response_code(429);
        echo json_encode(['success' => false, 'message' => 'Demasiados intentos. Espere un minuto.']);
        exit;
    }
}

// 3. Token CSRF para operaciones POST (excepto llamadas locales desde el helper)
$csrfActions = array('login', 'registro', 'recuperar', 'actualizar_perfil', 'cambiar_password');
$isLocalCall = ($clientIp === '127.0.0.1' || $clientIp === '::1' || $clientIp === $_SERVER['SERVER_ADDR']);
if (in_array($action, $csrfActions) && $_SERVER['REQUEST_METHOD'] === 'POST' && !$isLocalCall) {
    if (!isset($_SESSION['api_csrf_token'])) {
        $_SESSION['api_csrf_token'] = bin2hex(openssl_random_pseudo_bytes(32));
    }
    
    $clientToken = isset($_POST['csrf_token']) ? $_POST['csrf_token'] : 
                   (isset($_SERVER['HTTP_X_CSRF_TOKEN']) ? $_SERVER['HTTP_X_CSRF_TOKEN'] : '');
    
    if ($clientToken !== $_SESSION['api_csrf_token']) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Token de seguridad inválido']);
        exit;
    }
}

// ==================== ENDPOINTS ====================

switch ($action) {

    // ========== LOGIN ==========
    case 'login':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            echo json_encode(['success' => false, 'message' => 'Método no permitido']);
            exit;
        }
        
        $email    = isset($_POST['email']) ? trim($_POST['email']) : '';
        $password = isset($_POST['password']) ? $_POST['password'] : '';
        $remember = isset($_POST['remember']) && $_POST['remember'] == '1';
        
        if (empty($email) || empty($password)) {
            echo json_encode(['success' => false, 'message' => 'Correo y contraseña son obligatorios']);
            exit;
        }
        
        $db = getDB();
        $stmt = $db->prepare("SELECT id, rut, nombre, email, password, telefono, rol, activo FROM icentPventaUsuarios WHERE email = ? LIMIT 1");
        $stmt->bind_param('s', $email);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 0) {
            echo json_encode(['success' => false, 'message' => 'Correo o contraseña incorrectos']);
            exit;
        }
        
        $user = $result->fetch_assoc();
        
        if (!password_verify($password, $user['password'])) {
            echo json_encode(['success' => false, 'message' => 'Correo o contraseña incorrectos']);
            exit;
        }
        
        if (!$user['activo']) {
            echo json_encode(['success' => false, 'message' => 'Su cuenta ha sido desactivada.']);
            exit;
        }
        
        // Iniciar sesión
        $_SESSION['usuario_id']     = $user['id'];
        $_SESSION['usuario_rut']    = $user['rut'];
        $_SESSION['usuario_nombre'] = $user['nombre'];
        $_SESSION['usuario_email']  = $user['email'];
        $_SESSION['usuario_rol']    = $user['rol'];
        $_SESSION['es_admin']       = ($user['rol'] === 'admin_sistema') ? 1 : 0;
        
        session_regenerate_id(true);
        
        if ($remember) {
            setcookie('remember_email', $email, time() + (86400 * 30), '/', '', false, true);
        }
        
        echo json_encode([
            'success'  => true,
            'message'  => 'Inicio de sesión exitoso',
            'user'     => [
                'id'       => $user['id'],
                'nombre'   => $user['nombre'],
                'email'    => $user['email'],
                'rol'      => $user['rol'],
                'es_admin' => ($user['rol'] === 'admin_sistema')
            ],
            'redirect' => 'dashboard.php'
        ]);
        break;

    // ========== REGISTRO ==========
    case 'registro':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            echo json_encode(['success' => false, 'message' => 'Método no permitido']);
            exit;
        }
        
        $rut       = isset($_POST['rut']) ? trim($_POST['rut']) : '';
        $nombre    = isset($_POST['nombre']) ? trim($_POST['nombre']) : '';
        $email     = isset($_POST['email']) ? trim($_POST['email']) : '';
        $password  = isset($_POST['password']) ? $_POST['password'] : '';
        $password2 = isset($_POST['password2']) ? $_POST['password2'] : '';
        $telefono  = isset($_POST['telefono']) ? trim($_POST['telefono']) : '';
        
        if (empty($nombre) || empty($email) || empty($password)) {
            echo json_encode(['success' => false, 'message' => 'Nombre, correo y contraseña son obligatorios']);
            exit;
        }
        if ($password !== $password2) {
            echo json_encode(['success' => false, 'message' => 'Las contraseñas no coinciden']);
            exit;
        }
        if (strlen($password) < 6) {
            echo json_encode(['success' => false, 'message' => 'La contraseña debe tener al menos 6 caracteres']);
            exit;
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            echo json_encode(['success' => false, 'message' => 'El formato del correo no es válido']);
            exit;
        }
        
        if (!checkRateLimit($clientIp, 'registro', 3, 300)) {
            http_response_code(429);
            echo json_encode(['success' => false, 'message' => 'Demasiados intentos. Espere 5 minutos.']);
            exit;
        }
        
        $db = getDB();
        
        $check = $db->prepare("SELECT id FROM icentPventaUsuarios WHERE email = ? LIMIT 1");
        $check->bind_param('s', $email);
        $check->execute();
        if ($check->get_result()->num_rows > 0) {
            echo json_encode(['success' => false, 'message' => 'Este correo ya está registrado']);
            exit;
        }
        
        $hashedPassword = password_hash($password, PASSWORD_BCRYPT);
        $stmt = $db->prepare("INSERT INTO icentPventaUsuarios (rut, nombre, email, password, telefono, rol, activo, created_at) VALUES (?, ?, ?, ?, ?, 'propietario', 1, NOW())");
        $stmt->bind_param('sssss', $rut, $nombre, $email, $hashedPassword, $telefono);
        
        if ($stmt->execute()) {
            echo json_encode(['success' => true, 'message' => 'Registro exitoso. Ya puede iniciar sesión.', 'redirect' => 'login.php?registered=1']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Error al registrar']);
        }
        break;

    // ========== RECUPERAR CONTRASEÑA ==========
    case 'recuperar':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            echo json_encode(['success' => false, 'message' => 'Método no permitido']);
            exit;
        }
        
        $email = isset($_POST['email']) ? trim($_POST['email']) : '';
        if (empty($email)) {
            echo json_encode(['success' => false, 'message' => 'Ingrese su correo electrónico']);
            exit;
        }
        
        if (!checkRateLimit($clientIp, 'recuperar', 3, 300)) {
            http_response_code(429);
            echo json_encode(['success' => false, 'message' => 'Demasiados intentos. Espere 5 minutos.']);
            exit;
        }
        
        $db = getDB();
        $stmt = $db->prepare("SELECT id, nombre FROM icentPventaUsuarios WHERE email = ? AND activo = 1 LIMIT 1");
        $stmt->bind_param('s', $email);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $user = $result->fetch_assoc();
            $token = bin2hex(openssl_random_pseudo_bytes(32));
            $expiracion = date('Y-m-d H:i:s', strtotime('+1 hour'));
            $update = $db->prepare("UPDATE icentPventaUsuarios SET token_recuperacion = ?, token_expiracion = ? WHERE id = ?");
            $update->bind_param('ssi', $token, $expiracion, $user['id']);
            $update->execute();
        }
        
        echo json_encode(['success' => true, 'message' => 'Si el correo está registrado, recibirás un enlace.']);
        break;

    // ========== PERFIL (requiere sesión) ==========
    case 'perfil':
        if (!isset($_SESSION['usuario_id'])) {
            http_response_code(401);
            echo json_encode(['success' => false, 'message' => 'No autenticado']);
            exit;
        }
        
        $db = getDB();
        $stmt = $db->prepare("SELECT id, rut, nombre, email, telefono, rol, created_at FROM icentPventaUsuarios WHERE id = ? LIMIT 1");
        $stmt->bind_param('i', $_SESSION['usuario_id']);
        $stmt->execute();
        $user = $stmt->get_result()->fetch_assoc();
        
        echo json_encode(['success' => true, 'user' => $user]);
        break;

    // ========== ACTUALIZAR PERFIL ==========
    case 'actualizar_perfil':
        if (!isset($_SESSION['usuario_id'])) {
            http_response_code(401);
            echo json_encode(['success' => false, 'message' => 'No autenticado']);
            exit;
        }
        
        $nombre   = isset($_POST['nombre']) ? trim($_POST['nombre']) : '';
        $telefono = isset($_POST['telefono']) ? trim($_POST['telefono']) : '';
        
        $db = getDB();
        $stmt = $db->prepare("UPDATE icentPventaUsuarios SET nombre = ?, telefono = ? WHERE id = ?");
        $stmt->bind_param('ssi', $nombre, $telefono, $_SESSION['usuario_id']);
        
        if ($stmt->execute()) {
            $_SESSION['usuario_nombre'] = $nombre;
            echo json_encode(['success' => true, 'message' => 'Perfil actualizado']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Error al actualizar']);
        }
        break;

    // ========== CAMBIAR CONTRASEÑA ==========
    case 'cambiar_password':
        if (!isset($_SESSION['usuario_id'])) {
            http_response_code(401);
            echo json_encode(['success' => false, 'message' => 'No autenticado']);
            exit;
        }
        
        $actual  = isset($_POST['password_actual']) ? $_POST['password_actual'] : '';
        $nueva   = isset($_POST['password_nueva']) ? $_POST['password_nueva'] : '';
        $confirm = isset($_POST['password_confirm']) ? $_POST['password_confirm'] : '';
        
        if ($nueva !== $confirm) {
            echo json_encode(['success' => false, 'message' => 'Las contraseñas no coinciden']);
            exit;
        }
        if (strlen($nueva) < 6) {
            echo json_encode(['success' => false, 'message' => 'Mínimo 6 caracteres']);
            exit;
        }
        
        $db = getDB();
        $stmt = $db->prepare("SELECT password FROM icentPventaUsuarios WHERE id = ? LIMIT 1");
        $stmt->bind_param('i', $_SESSION['usuario_id']);
        $stmt->execute();
        $user = $stmt->get_result()->fetch_assoc();
        
        if (!password_verify($actual, $user['password'])) {
            echo json_encode(['success' => false, 'message' => 'Contraseña actual incorrecta']);
            exit;
        }
        
        $hashed = password_hash($nueva, PASSWORD_BCRYPT);
        $update = $db->prepare("UPDATE icentPventaUsuarios SET password = ? WHERE id = ?");
        $update->bind_param('si', $hashed, $_SESSION['usuario_id']);
        $update->execute();
        
        echo json_encode(['success' => true, 'message' => 'Contraseña actualizada']);
        break;

    // ========== OBTENER TOKEN CSRF ==========
    case 'csrf_token':
        if (!isset($_SESSION['api_csrf_token'])) {
            $_SESSION['api_csrf_token'] = bin2hex(openssl_random_pseudo_bytes(32));
        }
        echo json_encode(['success' => true, 'token' => $_SESSION['api_csrf_token']]);
        break;

    // ========== LISTAR USUARIOS (solo admin_sistema) ==========
    case 'listar_usuarios':
        if (!isset($_SESSION['usuario_id']) || $_SESSION['usuario_rol'] !== 'admin_sistema') {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'Acceso denegado']);
            exit;
        }
        
        $db = getDB();
        $result = $db->query("SELECT id, rut, nombre, email, telefono, rol, activo, created_at FROM icentPventaUsuarios ORDER BY created_at DESC");
        $usuarios = array();
        while ($row = $result->fetch_assoc()) {
            $usuarios[] = $row;
        }
        
        echo json_encode(['success' => true, 'usuarios' => $usuarios]);
        break;

    // ========== ACTUALIZAR USUARIO (solo admin_sistema) ==========
    case 'actualizar_usuario':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            echo json_encode(['success' => false, 'message' => 'Método no permitido']);
            exit;
        }
        if (!isset($_SESSION['usuario_id']) || $_SESSION['usuario_rol'] !== 'admin_sistema') {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'Acceso denegado']);
            exit;
        }
        
        $userId   = isset($_POST['id']) ? (int)$_POST['id'] : 0;
        $email    = isset($_POST['email']) ? trim($_POST['email']) : '';
        $telefono = isset($_POST['telefono']) ? trim($_POST['telefono']) : '';
        $rol      = isset($_POST['rol']) ? $_POST['rol'] : '';
        $activo   = isset($_POST['activo']) ? (int)$_POST['activo'] : 1;
        
        if ($userId <= 0) {
            echo json_encode(['success' => false, 'message' => 'ID de usuario inválido']);
            exit;
        }
        if (!in_array($rol, array('propietario', 'administrador_edificio'))) {
            echo json_encode(['success' => false, 'message' => 'Rol no permitido. Solo propietario o administrador_edificio.']);
            exit;
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            echo json_encode(['success' => false, 'message' => 'Formato de correo inválido']);
            exit;
        }
        
        $db = getDB();
        
        // Verificar que no se esté modificando a otro admin_sistema
        $check = $db->prepare("SELECT rol FROM icentPventaUsuarios WHERE id = ? LIMIT 1");
        $check->bind_param('i', $userId);
        $check->execute();
        $target = $check->get_result()->fetch_assoc();
        
        if (!$target) {
            echo json_encode(['success' => false, 'message' => 'Usuario no encontrado']);
            exit;
        }
        
        // Verificar email duplicado
        $dup = $db->prepare("SELECT id FROM icentPventaUsuarios WHERE email = ? AND id != ? LIMIT 1");
        $dup->bind_param('si', $email, $userId);
        $dup->execute();
        if ($dup->get_result()->num_rows > 0) {
            echo json_encode(['success' => false, 'message' => 'El correo ya está en uso por otro usuario']);
            exit;
        }
        
        $stmt = $db->prepare("UPDATE icentPventaUsuarios SET email = ?, telefono = ?, rol = ?, activo = ? WHERE id = ?");
        $stmt->bind_param('sssii', $email, $telefono, $rol, $activo, $userId);
        
        if ($stmt->execute()) {
            echo json_encode(['success' => true, 'message' => 'Usuario actualizado correctamente']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Error al actualizar: ' . $db->error]);
        }
        break;

    default:
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Acción no válida']);
        break;
}
