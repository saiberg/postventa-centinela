<?php
/**
 * Página de Login - Postventa Centinela
 * Maqueta visual sin conexión a base de datos
 */
require_once 'includes/config.php';

// Si ya está logueado, redirigir al dashboard
if (isset($_SESSION['usuario_id'])) {
    header('Location: dashboard.php');
    exit;
}

// Simulación de login para la maqueta
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = isset($_POST['email']) ? trim($_POST['email']) : '';
    $password = isset($_POST['password']) ? $_POST['password'] : '';
    
    // Simulación: cualquier login con email y contraseña no vacíos funciona
    if (!empty($email) && !empty($password)) {
        $_SESSION['usuario_id'] = 1;
        $_SESSION['usuario_nombre'] = 'Carlos Muñoz';
        $_SESSION['usuario_email'] = $email;
        $_SESSION['es_admin'] = (strpos($email, 'admin') !== false) ? 1 : 0;
        
        // Recordarme (simulado)
        if (isset($_POST['remember'])) {
            setcookie('remember_email', $email, time() + (86400 * 30), '/');
        }
        
        header('Location: dashboard.php');
        exit;
    } else {
        $error = 'Por favor ingrese su correo y contraseña.';
    }
}

$pageTitle = 'Iniciar Sesión';
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
            <!-- Header -->
            <div class="auth-header">
                <a href="https://icentinela.cl" target="_blank">
                    <img src="assets/img/logo-centinela-300x88.png" alt="Centinela Inmobiliaria" class="auth-logo" style="filter: brightness(0) invert(1);">
                </a>
                <h1>Postventa Centinela</h1>
                <p>Sistema de Atención al Cliente</p>
            </div>
            
            <!-- Formulario -->
            <div class="auth-body">
                <?php if ($error): ?>
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-circle"></i> <?php echo $error; ?>
                </div>
                <?php endif; ?>
                
                <form method="POST" action="">
                    <div class="form-group">
                        <label for="email">Correo Electrónico</label>
                        <input type="email" id="email" name="email" class="form-control" 
                               placeholder="Ingrese su correo electrónico" 
                               value="<?php echo isset($_COOKIE['remember_email']) ? htmlspecialchars($_COOKIE['remember_email']) : ''; ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="password">Contraseña</label>
                        <input type="password" id="password" name="password" class="form-control" 
                               placeholder="Ingrese su contraseña" required>
                    </div>
                    
                    <div class="remember-row">
                        <label class="remember-check">
                            <input type="checkbox" name="remember" id="remember">
                            <span>Recordarme</span>
                        </label>
                        <a href="recuperar.php" class="forgot-link">¿Olvidaste tu contraseña?</a>
                    </div>
                    
                    <button type="submit" class="btn btn-primary btn-block btn-lg">
                        <i class="fas fa-sign-in-alt"></i> Iniciar Sesión
                    </button>
                </form>
                
                <div class="auth-divider">
                    <span>¿Primera vez?</span>
                </div>
                
                <a href="registro.php" class="btn btn-outline btn-block">
                    <i class="fas fa-user-plus"></i> Crear una Cuenta
                </a>
            </div>
            
            <!-- Footer -->
            <div class="auth-footer">
                <p>Al iniciar sesión, aceptas nuestros <a href="#">Términos y Condiciones</a>.</p>
                <p class="mt-1">
                    <a href="https://icentinela.cl" target="_blank"><i class="fas fa-arrow-left"></i> Volver a icentinela.cl</a>
                </p>
            </div>
        </div>
        
        <!-- Nota maqueta -->
        <div class="text-center mt-3" style="color: #999; font-size: 0.8rem;">
            <p><i class="fas fa-info-circle"></i> Maqueta visual - Use cualquier correo y contraseña para ingresar.</p>
            <p>Use un correo que contenga "admin" para acceder como administrador.</p>
        </div>
    </div>
    
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="assets/js/main.js"></script>
</body>
</html>
