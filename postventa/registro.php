<?php
/**
 * Página de Registro - Postventa Centinela
 */
require_once 'includes/config.php';
require_once 'includes/api_helper.php';

if (isset($_SESSION['usuario_id'])) {
    header('Location: dashboard.php');
    exit;
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nombre = isset($_POST['nombre']) ? trim($_POST['nombre']) : '';
    $email = isset($_POST['email']) ? trim($_POST['email']) : '';
    $password = isset($_POST['password']) ? $_POST['password'] : '';
    $password2 = isset($_POST['password2']) ? $_POST['password2'] : '';
    $rut = isset($_POST['rut']) ? trim($_POST['rut']) : '';
    $telefono = isset($_POST['telefono']) ? trim($_POST['telefono']) : '';
    
    if (empty($nombre) || empty($email) || empty($password)) {
        $error = 'Todos los campos marcados con * son obligatorios.';
    } elseif ($password !== $password2) {
        $error = 'Las contraseñas no coinciden.';
    } elseif (strlen($password) < 6) {
        $error = 'La contraseña debe tener al menos 6 caracteres.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'El formato del correo no es válido.';
    } else {
        // Llamar a la API
        $data = apiCall('usuarios.php?action=registro', array(
            'rut'       => $rut,
            'nombre'    => $nombre,
            'email'     => $email,
            'password'  => $password,
            'password2' => $password2,
            'telefono'  => $telefono
        ));
        
        if ($data['success']) {
            $success = '¡Registro exitoso! Redirigiendo al inicio de sesión...';
        } else {
            $error = $data['message'];
        }
    }
}

$pageTitle = 'Crear Cuenta';
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
    <div class="auth-container wide">
        <div class="auth-card">
            <div class="auth-header">
                <a href="https://icentinela.cl" target="_blank">
                    <img src="assets/img/logo-centinela-300x88.png" alt="Centinela Inmobiliaria" class="auth-logo" style="filter: brightness(0) invert(1);">
                </a>
                <h1>Crear Cuenta</h1>
                <p>Regístrese para acceder al sistema de postventa</p>
            </div>
            
            <div class="auth-body">
                <?php if ($error): ?>
                <div class="alert alert-danger"><i class="fas fa-exclamation-circle"></i> <?php echo $error; ?></div>
                <?php endif; ?>
                
                <?php if ($success): ?>
                <div class="alert alert-success"><i class="fas fa-check-circle"></i> <?php echo $success; ?></div>
                <script>setTimeout(function(){ window.location.href='login.php'; }, 2500);</script>
                <?php else: ?>
                
                <form method="POST" action="">
                    <div class="form-row">
                        <div class="form-group">
                            <label for="rut">RUT/DNI</label>
                            <input type="text" id="rut" name="rut" class="form-control" placeholder="Ej: 12.345.678-9">
                        </div>
                        <div class="form-group">
                            <label for="telefono">Teléfono</label>
                            <input type="tel" id="telefono" name="telefono" class="form-control" placeholder="+56 9 XXXX XXXX">
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="nombre">Nombre Completo <span class="required">*</span></label>
                        <input type="text" id="nombre" name="nombre" class="form-control" placeholder="Ingrese su nombre completo" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="email">Correo Electrónico <span class="required">*</span></label>
                        <input type="email" id="email" name="email" class="form-control" placeholder="su@correo.com" required>
                        <small class="form-text">Usaremos este correo para notificaciones del sistema.</small>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="password">Contraseña <span class="required">*</span></label>
                            <input type="password" id="password" name="password" class="form-control" placeholder="Mínimo 6 caracteres" required minlength="6">
                        </div>
                        <div class="form-group">
                            <label for="password2">Confirmar Contraseña <span class="required">*</span></label>
                            <input type="password" id="password2" name="password2" class="form-control" placeholder="Repita su contraseña" required minlength="6">
                        </div>
                    </div>
                    
                    <div class="form-group mt-3">
                        <button type="submit" class="btn btn-primary btn-block btn-lg">
                            <i class="fas fa-user-plus"></i> Crear Cuenta
                        </button>
                    </div>
                </form>
                
                <?php endif; ?>
            </div>
            
            <div class="auth-footer">
                <p>¿Ya tienes cuenta? <a href="login.php">Inicia sesión aquí</a></p>
                <p class="mt-1">
                    <a href="https://icentinela.cl" target="_blank"><i class="fas fa-arrow-left"></i> Volver a icentinela.cl</a>
                </p>
            </div>
        </div>
    </div>
    
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="assets/js/main.js"></script>
</body>
</html>
