<?php
/**
 * Perfil de Usuario - Postventa Centinela
 */
require_once 'includes/config.php';

if (!isset($_SESSION['usuario_id'])) {
    $_SESSION['usuario_id'] = 1;
    $_SESSION['usuario_nombre'] = 'Carlos Muñoz';
    $_SESSION['usuario_email'] = 'carlos@email.com';
    $_SESSION['es_admin'] = 0;
}

include 'includes/header.php';
?>

<style>
.perfil-container {
    max-width: 700px;
    margin: 0 auto;
    padding: 28px 20px;
}
.perfil-avatar {
    width: 80px;
    height: 80px;
    border-radius: 50%;
    background: var(--color-primary);
    color: var(--color-white);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 2rem;
    margin: 0 auto 16px;
}
</style>

<div class="perfil-container">
    <div class="card">
        <div class="card-header">
            <h3><i class="fas fa-id-card"></i> Mi Perfil</h3>
        </div>
        <div class="card-body text-center">
            <div class="perfil-avatar">
                <i class="fas fa-user"></i>
            </div>
            <h2 style="font-family: var(--font-heading); margin-bottom:4px;"><?php echo htmlspecialchars($_SESSION['usuario_nombre']); ?></h2>
            <p class="text-muted"><?php echo htmlspecialchars($_SESSION['usuario_email']); ?></p>
            <p class="text-muted">Rol: <?php echo $_SESSION['es_admin'] ? 'Administrador' : 'Propietario/Residente'; ?></p>
            
            <div class="mt-3">
                <a href="dashboard.php" class="btn btn-primary">
                    <i class="fas fa-arrow-left"></i> Volver al Panel
                </a>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
