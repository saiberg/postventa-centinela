<?php
/**
 * Detalle de Caso - Postventa Centinela
 */
require_once 'includes/config.php';

if (!isset($_SESSION['usuario_id'])) {
    header('Location: login.php');
    exit;
}

$caseId = isset($_GET['id']) ? $_GET['id'] : 'PC-2024-001';

include 'includes/header.php';
?>

<style>
.detalle-container {
    max-width: 800px;
    margin: 0 auto;
    padding: 28px 20px;
}
</style>

<div class="detalle-container">
    <div class="mb-3">
        <a href="dashboard.php" class="btn btn-secondary btn-sm">
            <i class="fas fa-arrow-left"></i> Volver al Panel
        </a>
    </div>
    
    <div class="card">
        <div class="card-header">
            <h3><i class="fas fa-folder-open"></i> Detalle del Caso #<?php echo htmlspecialchars($caseId); ?></h3>
            <span class="badge badge-pending">Pendiente</span>
        </div>
        <div class="card-body">
            <p class="text-muted text-center" style="padding: 40px 0;">
                <i class="fas fa-info-circle" style="font-size: 2rem; display:block; margin-bottom:12px;"></i>
                Esta vista mostrará el detalle completo del caso cuando se implemente la base de datos.<br>
                Mientras tanto, puedes ver el detalle desde el panel de <a href="mis-solicitudes.php">Mis Solicitudes</a>.
            </p>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
