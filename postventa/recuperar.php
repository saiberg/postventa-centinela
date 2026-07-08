<?php
/**
 * Página de Recuperación de Contraseña - Postventa Centinela
 * Maqueta visual sin conexión a base de datos
 */
require_once 'includes/config.php';

if (isset($_SESSION['usuario_id'])) {
    header('Location: dashboard.php');
    exit;
}

$sent = false;
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = isset($_POST['email']) ? trim($_POST['email']) : '';
    
    if (empty($email)) {
        $error = 'Por favor ingrese su correo electrónico.';
    } else {
        // Simulación de envío
        $sent = true;
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
                <?php if ($sent): ?>
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
