<?php
/**
 * Página de Recuperación de Contraseña - Postventa Centinela
 * 
 * Flujo:
 * 1. GET sin token → Muestra formulario para ingresar email
 * 2. POST → Procesa solicitud, envía correo con enlace
 * 3. GET con token → Muestra formulario para nueva contraseña
 * 4. POST con token → Cambia la contraseña
 */
require_once 'includes/config.php';
require_once 'includes/api_helper.php';

if (isset($_SESSION['usuario_id'])) {
    header('Location: ' . ((isset($_SESSION['usuario_rol']) && $_SESSION['usuario_rol'] === 'admin_sistema') ? 'dashboard2.php' : 'dashboard.php'));
    exit;
}

$step = 'solicitar'; // solicitar | enviado | resetear | completado
$error = '';
$successMsg = '';
$token = isset($_GET['token']) ? trim($_GET['token']) : '';
$email = '';

// ==================== PASO 3: Verificar token y mostrar formulario de nueva contraseña ====================
if (!empty($token) && $_SERVER['REQUEST_METHOD'] === 'GET') {
    $result = apiCall('usuarios.php?action=verificar_token', array('token' => $token));
    
    if ($result['success']) {
        $step = 'resetear';
        $email = isset($result['email']) ? $result['email'] : '';
    } else {
        $error = $result['message'] ?: 'El enlace de recuperación no es válido o ha expirado.';
    }
}

// ==================== PASO 4: Procesar cambio de contraseña ====================
if (!empty($token) && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['nueva_password'])) {
    $password = isset($_POST['nueva_password']) ? $_POST['nueva_password'] : '';
    $password2 = isset($_POST['confirmar_password']) ? $_POST['confirmar_password'] : '';
    
    if (empty($password) || strlen($password) < 6) {
        $error = 'La contraseña debe tener al menos 6 caracteres.';
        $step = 'resetear';
    } elseif ($password !== $password2) {
        $error = 'Las contraseñas no coinciden.';
        $step = 'resetear';
    } else {
        $result = apiCall('usuarios.php?action=cambiar_password_token', array(
            'token' => $token,
            'password' => $password
        ));
        
        if ($result['success']) {
            $step = 'completado';
            $successMsg = 'Tu contraseña ha sido restablecida exitosamente.';
        } else {
            $error = $result['message'] ?: 'Error al cambiar la contraseña. Intenta de nuevo.';
            $step = 'resetear';
        }
    }
}

// ==================== PASO 1-2: Solicitar enlace de recuperación ====================
if (empty($token) && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = isset($_POST['email']) ? trim($_POST['email']) : '';
    
    if (empty($email)) {
        $error = 'Por favor ingrese su correo electrónico.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'El formato del correo no es válido.';
    } else {
        $result = apiCall('usuarios.php?action=recuperar', array('email' => $email));
        
        if ($result['success']) {
            $step = 'enviado';
        } else {
            $error = $result['message'] ?: 'Error al procesar la solicitud. Intente nuevamente.';
        }
    }
}

$pageTitle = 'Recuperar Contraseña';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle; ?> - Postventa Centinela</title>
    <link rel="icon" type="image/png" sizes="32x32" href="assets/img/favicon.png">
    <link rel="apple-touch-icon" href="assets/img/favicon.png">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <link rel="stylesheet" href="assets/css/main.css">
    <link rel="stylesheet" href="assets/css/auth.css">
</head>
<body class="auth-page">
    <div class="auth-container">
        <div class="auth-card">
            <div class="auth-header">
                <a href="https://icentinela.cl" target="_blank">
                    <img src="assets/img/logo-centinela-300x88.png" alt="Centinela Inmobiliaria" class="auth-logo" style="filter: brightness(0) invert(1);">
                </a>
                <h1>Recuperar Contraseña</h1>
                <p>Te ayudamos a recuperar el acceso</p>
            </div>
            
            <div class="auth-body">
                <?php if ($step === 'enviado'): ?>
                <div class="recovery-info">
                    <i class="fas fa-paper-plane"></i>
                    <p>Hemos enviado un enlace de recuperación a <strong><?php echo htmlspecialchars($email); ?></strong>.</p>
                    <p class="mt-1">Revisa tu bandeja de entrada y sigue las instrucciones. Si no lo encuentras, revisa tu carpeta de spam.</p>
                </div>
                <div class="text-center mt-2">
                    <a href="login.php" class="btn btn-primary">
                        <i class="fas fa-arrow-left"></i> Volver al Inicio de Sesión
                    </a>
                </div>
                
                <?php elseif ($step === 'resetear'): ?>
                <?php if ($error): ?>
                <div class="alert alert-danger"><i class="fas fa-exclamation-circle"></i> <?php echo $error; ?></div>
                <?php endif; ?>
                
                <div class="recovery-info">
                    <i class="fas fa-lock"></i>
                    <p>Ingresa tu nueva contraseña para <strong><?php echo htmlspecialchars($email); ?></strong>.</p>
                </div>
                
                <form method="POST" action="?token=<?php echo htmlspecialchars($token); ?>">
                    <input type="hidden" name="token" value="<?php echo htmlspecialchars($token); ?>">
                    <div class="form-group">
                        <label for="nueva_password">Nueva Contraseña</label>
                        <input type="password" id="nueva_password" name="nueva_password" class="form-control" placeholder="Mínimo 6 caracteres" required minlength="6">
                    </div>
                    <div class="form-group">
                        <label for="confirmar_password">Confirmar Contraseña</label>
                        <input type="password" id="confirmar_password" name="confirmar_password" class="form-control" placeholder="Repite la contraseña" required minlength="6">
                    </div>
                    
                    <button type="submit" class="btn btn-primary btn-block btn-lg">
                        <i class="fas fa-save"></i> Restablecer Contraseña
                    </button>
                </form>
                
                <div class="back-to-login mt-3">
                    <a href="login.php"><i class="fas fa-arrow-left"></i> Volver al Inicio de Sesión</a>
                </div>
                
                <?php elseif ($step === 'completado'): ?>
                <div class="recovery-info">
                    <i class="fas fa-check-circle" style="color:#608418;"></i>
                    <p><?php echo $successMsg; ?></p>
                    <p class="mt-1">Ahora puedes iniciar sesión con tu nueva contraseña.</p>
                </div>
                <div class="text-center mt-2">
                    <a href="login.php" class="btn btn-primary">
                        <i class="fas fa-sign-in-alt"></i> Ir al Inicio de Sesión
                    </a>
                </div>
                
                <?php else: ?>
                
                <?php if ($error): ?>
                <div class="alert alert-danger"><i class="fas fa-exclamation-circle"></i> <?php echo $error; ?></div>
                <?php endif; ?>
                
                <div class="recovery-info">
                    <i class="fas fa-lock"></i>
                    <p>Ingresa tu correo electrónico y te enviaremos un enlace para restablecer tu contraseña.</p>
                </div>
                
                <form method="POST" action="">
                    <div class="form-group">
                        <label for="email">Correo Electrónico</label>
                        <input type="email" id="email" name="email" class="form-control" placeholder="su@correo.com" required>
                    </div>
                    
                    <button type="submit" class="btn btn-primary btn-block btn-lg">
                        <i class="fas fa-paper-plane"></i> Enviar Enlace de Recuperación
                    </button>
                </form>
                
                <div class="back-to-login mt-3">
                    <a href="login.php"><i class="fas fa-arrow-left"></i> Volver al Inicio de Sesión</a>
                </div>
                
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="assets/js/main.js"></script>
</body>
</html>
