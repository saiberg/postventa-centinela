<?php
/**
 * Detalle de Caso - Postventa Centinela
 */
require_once 'includes/config.php';
require_once 'includes/api_helper.php';

if (!isset($_SESSION['usuario_id'])) {
    header('Location: login.php');
    exit;
}

// Extraer ID numérico del formato PC-YYYY-NNN
$caseId = isset($_GET['id']) ? $_GET['id'] : '';
$idNumerico = 0;
if (preg_match('/PC-\d{4}-(\d+)/', $caseId, $matches)) {
    $idNumerico = (int)$matches[1];
} elseif (is_numeric($caseId)) {
    $idNumerico = (int)$caseId;
}

// Obtener detalle desde la API
$apiResponse = apiCall('solicitudes.php?action=detalle&id=' . $idNumerico, array());
$solicitud = ($apiResponse['success'] && isset($apiResponse['solicitud'])) ? $apiResponse['solicitud'] : null;
$seguimiento = ($apiResponse['success'] && isset($apiResponse['seguimiento'])) ? $apiResponse['seguimiento'] : array();

if (!$solicitud) {
    header('Location: dashboard.php');
    exit;
}

// Labels
$estadoLabels = array(
    'pendiente'      => 'Pendiente',
    'aprobado'       => 'Aprobado',
    'no_corresponde' => 'No Corresponde',
    'agendado'       => 'Agendado',
    'en_proceso'     => 'En Proceso',
    'resuelto'       => 'Resuelto'
);
$estadoBadges = array(
    'pendiente'      => 'badge-pending',
    'aprobado'       => 'badge-approved',
    'no_corresponde' => 'badge-rejected',
    'agendado'       => 'badge-scheduled',
    'en_proceso'     => 'badge-in-progress',
    'resuelto'       => 'badge-resolved'
);
$estadoIcons = array(
    'pendiente'      => 'fa-clock',
    'aprobado'       => 'fa-check',
    'no_corresponde' => 'fa-times-circle',
    'agendado'       => 'fa-calendar-check',
    'en_proceso'     => 'fa-spinner',
    'resuelto'       => 'fa-check-circle'
);

$rolLabels = array(
    'propietario'            => 'Propietario / Residente',
    'administrador_edificio' => 'Administrador del Edificio'
);

$estado = $solicitud['estado'];
$estadoLabel = isset($estadoLabels[$estado]) ? $estadoLabels[$estado] : $estado;
$estadoBadge = isset($estadoBadges[$estado]) ? $estadoBadges[$estado] : 'badge-pending';
$estadoIcon  = isset($estadoIcons[$estado]) ? $estadoIcons[$estado] : 'fa-clock';

include 'includes/header.php';
?>

<style>
.detalle-container {
    max-width: 800px;
    margin: 0 auto;
    padding: 28px 20px;
}
.detalle-section {
    margin-bottom: 24px;
}
.detalle-section:last-child { margin-bottom: 0; }
.detalle-section h3 {
    font-family: var(--font-heading);
    font-size: 0.95rem;
    font-weight: 600;
    color: var(--color-gray-900);
    margin-bottom: 12px;
    padding-bottom: 8px;
    border-bottom: 2px solid var(--color-primary);
    display: flex;
    align-items: center;
    gap: 8px;
}
.detalle-section h3 i { color: var(--color-primary); }
.detalle-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 12px 20px;
}
.detalle-item { display: flex; flex-direction: column; }
.detalle-item.full { grid-column: 1 / -1; }
.detalle-item .detalle-label {
    font-size: 0.72rem;
    color: var(--color-gray-600);
    text-transform: uppercase;
    letter-spacing: 0.3px;
    font-weight: 600;
    margin-bottom: 2px;
}
.detalle-item .detalle-value {
    font-size: 0.88rem;
    color: var(--color-gray-800);
}
.comment-item {
    background: var(--color-gray-100);
    border-radius: var(--radius-sm);
    padding: 12px 16px;
    margin-bottom: 8px;
    border-left: 3px solid var(--color-primary);
}
.comment-item .comment-header {
    display: flex;
    justify-content: space-between;
    margin-bottom: 4px;
    font-size: 0.8rem;
}
.comment-item .comment-author { font-weight: 600; color: var(--color-gray-800); }
.comment-item .comment-date { color: var(--color-gray-500); }
.comment-item .comment-text { font-size: 0.85rem; color: var(--color-gray-700); }
@media (max-width: 768px) {
    .detalle-grid { grid-template-columns: 1fr; }
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
            <h3><i class="fas fa-folder-open"></i> Caso #<?php echo htmlspecialchars($caseId); ?></h3>
            <span class="badge <?php echo $estadoBadge; ?>">
                <i class="fas <?php echo $estadoIcon; ?>"></i> <?php echo $estadoLabel; ?>
            </span>
        </div>
        <div class="card-body">
            
            <!-- Identificación -->
            <div class="detalle-section">
                <h3><i class="fas fa-user"></i> Identificación del Solicitante</h3>
                <div class="detalle-grid">
                    <div class="detalle-item">
                        <span class="detalle-label">RUT/DNI</span>
                        <span class="detalle-value"><?php echo htmlspecialchars($solicitud['rut']); ?></span>
                    </div>
                    <div class="detalle-item">
                        <span class="detalle-label">Nombre Completo</span>
                        <span class="detalle-value"><?php echo htmlspecialchars($solicitud['nombre']); ?></span>
                    </div>
                    <div class="detalle-item">
                        <span class="detalle-label">Correo Electrónico</span>
                        <span class="detalle-value"><?php echo htmlspecialchars($solicitud['email']); ?></span>
                    </div>
                    <div class="detalle-item">
                        <span class="detalle-label">Teléfono</span>
                        <span class="detalle-value"><?php echo htmlspecialchars($solicitud['telefono'] ? $solicitud['telefono'] : '—'); ?></span>
                    </div>
                    <div class="detalle-item">
                        <span class="detalle-label">Rol</span>
                        <span class="detalle-value"><?php echo isset($rolLabels[$solicitud['rol_solicitante']]) ? $rolLabels[$solicitud['rol_solicitante']] : $solicitud['rol_solicitante']; ?></span>
                    </div>
                    <div class="detalle-item">
                        <span class="detalle-label">Fecha de Ingreso</span>
                        <span class="detalle-value"><?php echo date('d/m/Y H:i', strtotime($solicitud['created_at'])); ?></span>
                    </div>
                </div>
            </div>
            
            <!-- Ubicación -->
            <div class="detalle-section">
                <h3><i class="fas fa-map-marker-alt"></i> Ubicación del Problema</h3>
                <p style="font-size:0.9rem; color: var(--color-gray-700);">
                    <?php echo htmlspecialchars($solicitud['ubicacion_valor']); ?>
                </p>
            </div>
            
            <!-- Clasificación -->
            <div class="detalle-section">
                <h3><i class="fas fa-tags"></i> Clasificación</h3>
                <div class="detalle-grid">
                    <div class="detalle-item">
                        <span class="detalle-label">Categoría</span>
                        <span class="detalle-value"><?php echo htmlspecialchars($solicitud['categoria']); ?></span>
                    </div>
                    <div class="detalle-item">
                        <span class="detalle-label">Subcategoría</span>
                        <span class="detalle-value"><?php echo htmlspecialchars($solicitud['subcategoria']); ?></span>
                    </div>
                </div>
            </div>
            
            <!-- Descripción -->
            <div class="detalle-section">
                <h3><i class="fas fa-align-left"></i> Descripción</h3>
                <p style="font-size:0.9rem; color: var(--color-gray-700); line-height:1.6;">
                    <?php echo nl2br(htmlspecialchars($solicitud['detalle'] ? $solicitud['detalle'] : 'Sin descripción.')); ?>
                </p>
            </div>
            
            <!-- Días disponibles -->
            <div class="detalle-section">
                <h3><i class="fas fa-calendar-week"></i> Días Disponibles para Visita</h3>
                <p style="font-size:0.9rem; color: var(--color-gray-700);">
                    <?php echo htmlspecialchars($solicitud['dias_disponibles'] ? $solicitud['dias_disponibles'] : '—'); ?>
                </p>
            </div>
            
            <!-- Agendamiento y Equipo -->
            <?php if ($solicitud['fecha_agendamiento'] || $solicitud['equipo_asignado']): ?>
            <div class="detalle-section">
                <h3><i class="fas fa-hard-hat"></i> Atención Programada</h3>
                <div class="detalle-grid">
                    <?php if ($solicitud['fecha_agendamiento']): ?>
                    <div class="detalle-item">
                        <span class="detalle-label">Fecha de Visita</span>
                        <span class="detalle-value">
                            <i class="fas fa-calendar-alt"></i> 
                            <?php echo date('d/m/Y - H:i', strtotime($solicitud['fecha_agendamiento'])); ?>
                        </span>
                    </div>
                    <?php endif; ?>
                    <?php if ($solicitud['equipo_asignado']): ?>
                    <div class="detalle-item">
                        <span class="detalle-label">Equipo Asignado</span>
                        <span class="detalle-value"><?php echo htmlspecialchars($solicitud['equipo_asignado']); ?></span>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>
            
            <!-- Comentario del administrador -->
            <?php if (!empty($solicitud['comentario_admin'])): ?>
            <div class="detalle-section">
                <h3><i class="fas fa-comment-dots"></i> Comentario del Administrador</h3>
                <div class="comment-item">
                    <div class="comment-text"><?php echo nl2br(htmlspecialchars($solicitud['comentario_admin'])); ?></div>
                </div>
            </div>
            <?php endif; ?>
            
            <!-- Seguimiento -->
            <?php if (!empty($seguimiento)): ?>
            <div class="detalle-section">
                <h3><i class="fas fa-history"></i> Historial de Seguimiento</h3>
                <?php foreach ($seguimiento as $seg): 
                    $tipoLabels = array(
                        'sistema' => 'Sistema',
                        'admin'   => 'Admin Postventa',
                        'cliente' => 'Cliente',
                        'tecnico' => 'Técnico'
                    );
                    $tipoLabel = isset($tipoLabels[$seg['tipo']]) ? $tipoLabels[$seg['tipo']] : $seg['tipo'];
                ?>
                <div class="comment-item">
                    <div class="comment-header">
                        <span class="comment-author"><i class="fas fa-user-circle"></i> <?php echo $tipoLabel; ?></span>
                        <span class="comment-date"><?php echo date('d/m/Y H:i', strtotime($seg['created_at'])); ?></span>
                    </div>
                    <div class="comment-text"><?php echo nl2br(htmlspecialchars($seg['comentario'])); ?></div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
            
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>

