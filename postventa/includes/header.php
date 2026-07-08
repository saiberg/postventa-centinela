<?php
require_once __DIR__ . '/config.php';

// Determinar si el usuario está logueado (simulado para maqueta)
$isLoggedIn = isset($_SESSION['usuario_id']);
$isAdmin = isset($_SESSION['es_admin']) && $_SESSION['es_admin'] == 1;

// Página actual para marcar menú activo
$currentPage = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo SITE_NAME; ?> - Inmobiliaria Centinela</title>
    
    <!-- Google Fonts - Montserrat (misma fuente del sitio principal) -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    
    <!-- Font Awesome 5 -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    
    <!-- jQuery (cargado en head para disponibilidad en scripts inline) -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    
    <!-- CSS Principal -->
    <link rel="stylesheet" href="<?php echo ASSETS_URL; ?>css/main.css">
    
    <!-- CSS específico de página -->
    <?php if ($currentPage == 'login.php' || $currentPage == 'registro.php' || $currentPage == 'recuperar.php'): ?>
    <link rel="stylesheet" href="<?php echo ASSETS_URL; ?>css/auth.css">
    <?php endif; ?>
    <?php if ($currentPage == 'dashboard.php' || $currentPage == 'nueva-solicitud.php' || $currentPage == 'mis-solicitudes.php'): ?>
    <link rel="stylesheet" href="<?php echo ASSETS_URL; ?>css/dashboard.css">
    <?php endif; ?>
    <?php if ($currentPage == 'admin.php' || $currentPage == 'admin-detalle.php'): ?>
    <link rel="stylesheet" href="<?php echo ASSETS_URL; ?>css/admin.css">
    <?php endif; ?>
</head>
<body>
    <!-- Header / Barra superior -->
    <header class="site-header">
        <div class="header-container">
            <div class="header-logo">
                <a href="<?php echo $isLoggedIn ? 'dashboard.php' : 'login.php'; ?>">
                    <img src="<?php echo IMG_URL; ?>logo-centinela-300x88.png" alt="Centinela Inmobiliaria" class="logo-img">
                </a>
            </div>
            
            <?php if ($isLoggedIn): ?>
            <nav class="header-nav">
                <button class="mobile-menu-toggle" id="mobileMenuToggle" aria-label="Menú">
                    <span class="menu-icon-bar"></span>
                    <span class="menu-icon-bar"></span>
                    <span class="menu-icon-bar"></span>
                </button>
                <ul class="nav-menu" id="navMenu">
                    <li class="<?php echo $currentPage == 'dashboard.php' ? 'active' : ''; ?>">
                        <a href="dashboard.php"><i class="fas fa-tachometer-alt"></i> Mi Panel</a>
                    </li>
                    <li class="<?php echo $currentPage == 'nueva-solicitud.php' ? 'active' : ''; ?>">
                        <a href="nueva-solicitud.php"><i class="fas fa-plus-circle"></i> Nueva Solicitud</a>
                    </li>
                    <li class="<?php echo $currentPage == 'mis-solicitudes.php' ? 'active' : ''; ?>">
                        <a href="mis-solicitudes.php"><i class="fas fa-list-alt"></i> Mis Solicitudes</a>
                    </li>
                    <?php if ($isAdmin): ?>
                    <li class="<?php echo $currentPage == 'admin.php' ? 'active' : ''; ?>">
                        <a href="admin.php"><i class="fas fa-cogs"></i> Administración</a>
                    </li>
                    <?php endif; ?>
                    <li class="nav-user-menu">
                        <a href="#" class="user-dropdown-toggle" id="userDropdownToggle">
                            <i class="fas fa-user-circle"></i> 
                            <span><?php echo isset($_SESSION['usuario_nombre']) ? htmlspecialchars($_SESSION['usuario_nombre']) : 'Usuario'; ?></span>
                            <i class="fas fa-chevron-down"></i>
                        </a>
                        <ul class="user-dropdown" id="userDropdown">
                            <li><a href="perfil.php"><i class="fas fa-id-card"></i> Mi Perfil</a></li>
                            <li><a href="logout.php"><i class="fas fa-sign-out-alt"></i> Cerrar Sesión</a></li>
                        </ul>
                    </li>
                </ul>
            </nav>
            <?php endif; ?>
        </div>
    </header>
    
    <!-- Contenido Principal -->
    <main class="site-main">
