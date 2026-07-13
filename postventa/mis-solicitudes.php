<?php
/**
 * Mis Solicitudes - Postventa Centinela
 * Vista detallada del estado de solicitudes
 */
require_once 'includes/config.php';
require_once 'includes/api_helper.php';

if (!isset($_SESSION['usuario_id'])) {
    header('Location: login.php');
    exit;
}

// El admin_sistema no tiene solicitudes propias
if (isset($_SESSION['usuario_rol']) && $_SESSION['usuario_rol'] === 'admin_sistema') {
    header('Location: dashboard.php');
    exit;
}

// Obtener solicitudes desde la API
$apiResponse = apiCall('solicitudes.php?action=mis_solicitudes', array());
$solicitudesRaw = ($apiResponse['success'] && isset($apiResponse['solicitudes'])) ? $apiResponse['solicitudes'] : array();

// ========== FILTROS ==========
$filtroEstado = isset($_GET['estado']) ? trim($_GET['estado']) : '';
$filtroBuscar = isset($_GET['buscar']) ? trim($_GET['buscar']) : '';
$filtroFechaDesde = isset($_GET['fecha_desde']) ? trim($_GET['fecha_desde']) : '';
$filtroFechaHasta = isset($_GET['fecha_hasta']) ? trim($_GET['fecha_hasta']) : '';

// Aplicar filtros sobre los datos crudos
$solicitudesFiltradas = $solicitudesRaw;

if ($filtroEstado !== '') {
    $solicitudesFiltradas = array_filter($solicitudesFiltradas, function($row) use ($filtroEstado) {
        return $row['estado'] === $filtroEstado;
    });
}

if ($filtroBuscar !== '') {
    $buscarLower = strtolower($filtroBuscar);
    $solicitudesFiltradas = array_filter($solicitudesFiltradas, function($row) use ($buscarLower) {
        $texto = strtolower($row['categoria'] . ' ' . $row['subcategoria'] . ' ' . $row['ubicacion_valor'] . ' ' . $row['detalle']);
        return strpos($texto, $buscarLower) !== false;
    });
}

if ($filtroFechaDesde !== '') {
    $solicitudesFiltradas = array_filter($solicitudesFiltradas, function($row) use ($filtroFechaDesde) {
        return date('Y-m-d', strtotime($row['created_at'])) >= $filtroFechaDesde;
    });
}

if ($filtroFechaHasta !== '') {
    $solicitudesFiltradas = array_filter($solicitudesFiltradas, function($row) use ($filtroFechaHasta) {
        return date('Y-m-d', strtotime($row['created_at'])) <= $filtroFechaHasta;
    });
}

// Reindexar array después de filtros
$solicitudesFiltradas = array_values($solicitudesFiltradas);

// ========== PAGINACIÓN ==========
$porPagina = 5;
$totalFiltrados = count($solicitudesFiltradas);
$totalPaginas = ceil($totalFiltrados / $porPagina);
$paginaActual = isset($_GET['pagina']) ? max(1, min((int)$_GET['pagina'], max(1, $totalPaginas))) : 1;
$offset = ($paginaActual - 1) * $porPagina;
$solicitudesPaginadas = array_slice($solicitudesFiltradas, $offset, $porPagina);

// Formatear para la vista
$estadoLabels = array(
    'pendiente'      => 'Pendiente de Revisión',
    'aprobado'       => 'Aprobado',
    'no_corresponde' => 'No Corresponde',
    'agendado'       => 'Visita Agendada',
    'en_proceso'     => 'En Proceso',
    'resuelto'       => 'Resuelto'
);
$estadoIcons = array(
    'pendiente'      => 'fa-clock',
    'aprobado'       => 'fa-check',
    'no_corresponde' => 'fa-times-circle',
    'agendado'       => 'fa-calendar-check',
    'en_proceso'     => 'fa-spinner',
    'resuelto'       => 'fa-check-circle'
);

$solicitudes = array();
foreach ($solicitudesPaginadas as $row) {
    // Obtener seguimiento para esta solicitud
    $detalleResp = apiCall('solicitudes.php?action=detalle&id=' . $row['id'], array());
    $comentarios = array();
    if ($detalleResp['success'] && isset($detalleResp['seguimiento'])) {
        foreach ($detalleResp['seguimiento'] as $seg) {
            $tipoLabels = array('sistema' => 'Postventa Centinela', 'admin' => 'Admin Postventa', 'cliente' => 'Cliente', 'tecnico' => 'Técnico');
            $comentarios[] = array(
                'fecha' => date('d/m/Y', strtotime($seg['created_at'])),
                'autor' => isset($tipoLabels[$seg['tipo']]) ? $tipoLabels[$seg['tipo']] : $seg['tipo'],
                'texto' => $seg['comentario']
            );
        }
    }
    
    $solicitudes[] = array(
        'id'             => 'PC-' . date('Y', strtotime($row['created_at'])) . '-' . str_pad($row['id'], 3, '0', STR_PAD_LEFT),
        'id_num'         => $row['id'],
        'fecha'          => date('d/m/Y', strtotime($row['created_at'])),
        'categoria'      => $row['categoria'],
        'subcategoria'   => $row['subcategoria'],
        'ubicacion'      => $row['ubicacion_valor'],
        'estado'         => $row['estado'],
        'estado_label'   => isset($estadoLabels[$row['estado']]) ? $estadoLabels[$row['estado']] : $row['estado'],
        'estado_icon'    => isset($estadoIcons[$row['estado']]) ? $estadoIcons[$row['estado']] : 'fa-clock',
        'agendamiento'   => $row['fecha_agendamiento'] ? date('d/m/Y - H:i', strtotime($row['fecha_agendamiento'])) : null,
        'equipo'         => $row['equipo_asignado'],
        'detalle'        => $row['detalle'],
        'evidencia'      => 0,
        'comentarios'    => $comentarios
    );
}

include 'includes/header.php';
?>

<style>
.solicitudes-container {
    max-width: 1000px;
    margin: 0 auto;
    padding: 28px 20px;
}

.solicitudes-header {
    margin-bottom: 28px;
}

.solicitudes-header h1 {
    font-family: var(--font-heading);
    font-size: 1.6rem;
    font-weight: 700;
    color: var(--color-gray-900);
    display: flex;
    align-items: center;
    gap: 10px;
}

/* Timeline de solicitudes */
.timeline {
    position: relative;
    padding-left: 40px;
}

.timeline::before {
    content: '';
    position: absolute;
    left: 15px;
    top: 0;
    bottom: 0;
    width: 3px;
    background: var(--color-gray-300);
    border-radius: 2px;
}

.timeline-item {
    position: relative;
    margin-bottom: 32px;
}

.timeline-marker {
    position: absolute;
    left: -40px;
    top: 20px;
    width: 33px;
    height: 33px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    color: var(--color-white);
    font-size: 0.9rem;
    z-index: 1;
}

.timeline-marker.pending { background: var(--color-pending); }
.timeline-marker.approved { background: var(--color-approved); }
.timeline-marker.scheduled { background: var(--color-scheduled); }
.timeline-marker.in-progress { background: var(--color-in-progress); }
.timeline-marker.resolved { background: var(--color-resolved); }
.timeline-marker.rejected { background: var(--color-rejected); }

.timeline-card {
    background: var(--color-white);
    border-radius: var(--radius-md);
    box-shadow: var(--shadow-sm);
    border: 1px solid var(--color-gray-200);
    overflow: hidden;
}

.timeline-card-header {
    padding: 16px 20px;
    border-bottom: 1px solid var(--color-gray-200);
    display: flex;
    align-items: center;
    justify-content: space-between;
    flex-wrap: wrap;
    gap: 10px;
}

.timeline-card-header .case-title {
    font-family: var(--font-heading);
    font-weight: 600;
    font-size: 1rem;
    color: var(--color-gray-900);
    display: flex;
    align-items: center;
    gap: 10px;
}

.timeline-card-header .case-date {
    font-size: 0.8rem;
    color: var(--color-gray-600);
}

.timeline-card-body {
    padding: 20px;
}

.timeline-card-body .info-row {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 12px 24px;
    margin-bottom: 16px;
}

.timeline-card-body .info-item {
    display: flex;
    flex-direction: column;
}

.info-item .info-label {
    font-size: 0.72rem;
    color: var(--color-gray-600);
    text-transform: uppercase;
    letter-spacing: 0.3px;
    font-weight: 600;
}

.info-item .info-value {
    font-size: 0.88rem;
    color: var(--color-gray-800);
}

/* Comentarios */
.comments-section {
    border-top: 1px solid var(--color-gray-200);
    padding: 16px 20px;
    background: var(--color-gray-100);
}

.comments-section h4 {
    font-family: var(--font-heading);
    font-size: 0.85rem;
    font-weight: 600;
    margin-bottom: 12px;
    color: var(--color-gray-700);
}

.comment-item {
    background: var(--color-white);
    border-radius: var(--radius-sm);
    padding: 12px 16px;
    margin-bottom: 8px;
    border-left: 3px solid var(--color-primary);
}

.comment-item .comment-header {
    display: flex;
    justify-content: space-between;
    margin-bottom: 4px;
}

.comment-item .comment-author {
    font-weight: 600;
    font-size: 0.82rem;
    color: var(--color-gray-800);
}

.comment-item .comment-date {
    font-size: 0.75rem;
    color: var(--color-gray-500);
}

.comment-item .comment-text {
    font-size: 0.85rem;
    color: var(--color-gray-700);
    line-height: 1.5;
}

/* Evidencia */
.evidence-badge {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 6px 12px;
    background: var(--color-primary-bg);
    color: var(--color-primary);
    border-radius: var(--radius-sm);
    font-size: 0.82rem;
    font-weight: 500;
}

@media (max-width: 768px) {
    .timeline-card-body .info-row {
        grid-template-columns: 1fr;
    }
    
    .timeline {
        padding-left: 32px;
    }
    
    .timeline-marker {
        left: -32px;
        width: 28px;
        height: 28px;
        font-size: 0.75rem;
    }
}
</style>

<div class="solicitudes-container">
    
    <div class="solicitudes-header">
        <h1><i class="fas fa-list-alt"></i> Estado de Mis Solicitudes</h1>
        <p class="text-muted">Seguimiento detallado de cada uno de sus requerimientos.</p>
    </div>
    
    <div class="mb-3" style="display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:10px;">
        <div>
            <a href="dashboard.php" class="btn btn-secondary btn-sm">
                <i class="fas fa-arrow-left"></i> Volver al Panel
            </a>
            <a href="nueva-solicitud.php" class="btn btn-primary btn-sm">
                <i class="fas fa-plus-circle"></i> Nueva Solicitud
            </a>
        </div>
        <span class="text-muted" style="font-size:0.82rem;">
            Mostrando <?php echo count($solicitudesPaginadas); ?> de <?php echo $totalFiltrados; ?> solicitudes
        </span>
    </div>
    
    <!-- Filtros -->
    <div class="card" style="margin-bottom: 24px;">
        <div class="card-body" style="padding: 16px 20px;">
            <form method="GET" action="mis-solicitudes.php" id="filtrosForm">
                <div style="display:flex; align-items:flex-end; flex-wrap:wrap; gap:12px;">
                    <div style="flex:1; min-width:140px;">
                        <label for="filtroEstado" style="font-size:0.75rem; font-weight:600; color:var(--color-gray-600); display:block; margin-bottom:4px;">Estado</label>
                        <select name="estado" id="filtroEstado" class="form-control form-control-sm" style="width:100%;" onchange="this.form.submit()">
                            <option value="">Todos los estados</option>
                            <option value="pendiente" <?php echo $filtroEstado === 'pendiente' ? 'selected' : ''; ?>>Pendiente</option>
                            <option value="aprobado" <?php echo $filtroEstado === 'aprobado' ? 'selected' : ''; ?>>Aprobado</option>
                            <option value="agendado" <?php echo $filtroEstado === 'agendado' ? 'selected' : ''; ?>>Agendado</option>
                            <option value="en_proceso" <?php echo $filtroEstado === 'en_proceso' ? 'selected' : ''; ?>>En Proceso</option>
                            <option value="resuelto" <?php echo $filtroEstado === 'resuelto' ? 'selected' : ''; ?>>Resuelto</option>
                            <option value="no_corresponde" <?php echo $filtroEstado === 'no_corresponde' ? 'selected' : ''; ?>>No Corresponde</option>
                        </select>
                    </div>
                    <div style="flex:1.5; min-width:180px;">
                        <label for="filtroBuscar" style="font-size:0.75rem; font-weight:600; color:var(--color-gray-600); display:block; margin-bottom:4px;">Buscar</label>
                        <input type="text" name="buscar" id="filtroBuscar" class="form-control form-control-sm" style="width:100%;" placeholder="Categoría, ubicación, detalle..." value="<?php echo htmlspecialchars($filtroBuscar); ?>">
                    </div>
                    <div style="flex:1; min-width:130px;">
                        <label for="filtroFechaDesde" style="font-size:0.75rem; font-weight:600; color:var(--color-gray-600); display:block; margin-bottom:4px;">Desde</label>
                        <input type="date" name="fecha_desde" id="filtroFechaDesde" class="form-control form-control-sm" style="width:100%;" value="<?php echo htmlspecialchars($filtroFechaDesde); ?>">
                    </div>
                    <div style="flex:1; min-width:130px;">
                        <label for="filtroFechaHasta" style="font-size:0.75rem; font-weight:600; color:var(--color-gray-600); display:block; margin-bottom:4px;">Hasta</label>
                        <input type="date" name="fecha_hasta" id="filtroFechaHasta" class="form-control form-control-sm" style="width:100%;" value="<?php echo htmlspecialchars($filtroFechaHasta); ?>">
                    </div>
                    <div style="display:flex; gap:6px;">
                        <button type="submit" class="btn btn-primary btn-sm">
                            <i class="fas fa-filter"></i> Filtrar
                        </button>
                        <a href="mis-solicitudes.php" class="btn btn-secondary btn-sm" title="Limpiar filtros">
                            <i class="fas fa-times"></i>
                        </a>
                    </div>
                </div>
            </form>
        </div>
    </div>
    
    <?php if (empty($solicitudesPaginadas)): ?>
    <div class="card" style="text-align:center; padding: 48px 20px;">
        <i class="fas fa-search" style="font-size:2.5rem; color:var(--color-gray-400); display:block; margin-bottom:12px;"></i>
        <p style="color:var(--color-gray-600); font-size:0.95rem;">No se encontraron solicitudes con los filtros aplicados.</p>
        <a href="mis-solicitudes.php" class="btn btn-secondary btn-sm" style="margin-top:8px;">
            <i class="fas fa-times"></i> Limpiar filtros
        </a>
    </div>
    <?php endif; ?>
    
    <div class="timeline">
        <?php foreach ($solicitudes as $sol): 
            $markerClass = '';
            switch ($sol['estado']) {
                case 'pendiente': $markerClass = 'pending'; break;
                case 'aprobado': $markerClass = 'approved'; break;
                case 'agendado': $markerClass = 'scheduled'; break;
                case 'en_proceso': $markerClass = 'in-progress'; break;
                case 'resuelto': $markerClass = 'resolved'; break;
                case 'no_corresponde': $markerClass = 'rejected'; break;
            }
        ?>
        <div class="timeline-item">
            <div class="timeline-marker <?php echo $markerClass; ?>">
                <i class="fas <?php echo $sol['estado_icon']; ?>"></i>
            </div>
            <div class="timeline-card">
                <div class="timeline-card-header">
                    <div class="case-title">
                        <span class="case-id">#<?php echo $sol['id']; ?></span>
                        <?php echo $sol['categoria']; ?>
                    </div>
                    <div class="case-date">
                        <i class="far fa-calendar-alt"></i> <?php echo $sol['fecha']; ?>
                    </div>
                </div>
                <div class="timeline-card-body">
                    <div class="info-row">
                        <div class="info-item">
                            <span class="info-label">Subcategoría</span>
                            <span class="info-value"><?php echo $sol['subcategoria']; ?></span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">Ubicación</span>
                            <span class="info-value"><?php echo $sol['ubicacion']; ?></span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">Estado</span>
                            <span class="info-value">
                                <?php
                                $badgeClass = '';
                                switch ($sol['estado']) {
                                    case 'pendiente': $badgeClass = 'badge-pending'; break;
                                    case 'aprobado': $badgeClass = 'badge-approved'; break;
                                    case 'agendado': $badgeClass = 'badge-scheduled'; break;
                                    case 'en_proceso': $badgeClass = 'badge-in-progress'; break;
                                    case 'resuelto': $badgeClass = 'badge-resolved'; break;
                                    case 'no_corresponde': $badgeClass = 'badge-rejected'; break;
                                }
                                ?>
                                <span class="badge <?php echo $badgeClass; ?>"><?php echo $sol['estado_label']; ?></span>
                            </span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">Agendamiento</span>
                            <span class="info-value"><?php echo isset($sol['agendamiento']) ? $sol['agendamiento'] : '—'; ?></span>
                        </div>
                        <?php if ($sol['equipo']): ?>
                        <div class="info-item">
                            <span class="info-label">Equipo Asignado</span>
                            <span class="info-value"><i class="fas fa-hard-hat"></i> <?php echo $sol['equipo']; ?></span>
                        </div>
                        <?php endif; ?>
                        <div class="info-item">
                            <span class="info-label">Evidencia</span>
                            <span class="info-value">
                                <span class="evidence-badge">
                                    <i class="fas fa-paperclip"></i> <?php echo $sol['evidencia']; ?> archivo(s)
                                </span>
                            </span>
                        </div>
                    </div>
                    
                    <div class="info-item" style="grid-column: 1/-1;">
                        <span class="info-label">Descripción</span>
                        <span class="info-value"><?php echo $sol['detalle']; ?></span>
                    </div>
                </div>
                
                <?php if (!empty($sol['comentarios'])): ?>
                <div class="comments-section">
                    <h4><i class="fas fa-comments"></i> Seguimiento</h4>
                    <?php foreach ($sol['comentarios'] as $com): ?>
                    <div class="comment-item">
                        <div class="comment-header">
                            <span class="comment-author"><?php echo $com['autor']; ?></span>
                            <span class="comment-date"><?php echo $com['fecha']; ?></span>
                        </div>
                        <div class="comment-text"><?php echo $com['texto']; ?></div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    
    <!-- Paginación -->
    <?php if ($totalPaginas > 1): 
        // Construir query string base con filtros actuales
        $queryParams = $_GET;
        unset($queryParams['pagina']);
        $baseQuery = http_build_query($queryParams);
        $baseQuery = $baseQuery ? '?' . $baseQuery . '&' : '?';
    ?>
    <div style="display:flex; justify-content:center; margin-top:24px;">
        <ul class="pagination">
            <?php if ($paginaActual > 1): ?>
            <li class="page-item">
                <a class="page-link" href="mis-solicitudes.php<?php echo $baseQuery; ?>pagina=<?php echo $paginaActual - 1; ?>">
                    <i class="fas fa-chevron-left"></i>
                </a>
            </li>
            <?php else: ?>
            <li class="page-item disabled"><span class="page-link"><i class="fas fa-chevron-left"></i></span></li>
            <?php endif; ?>
            
            <?php
            // Mostrar páginas con elipsis
            $rango = 2;
            for ($p = 1; $p <= $totalPaginas; $p++):
                if ($p == 1 || $p == $totalPaginas || ($p >= $paginaActual - $rango && $p <= $paginaActual + $rango)):
            ?>
            <li class="page-item <?php echo $p == $paginaActual ? 'active' : ''; ?>">
                <a class="page-link" href="mis-solicitudes.php<?php echo $baseQuery; ?>pagina=<?php echo $p; ?>"><?php echo $p; ?></a>
            </li>
            <?php elseif ($p == $paginaActual - $rango - 1 || $p == $paginaActual + $rango + 1): ?>
            <li class="page-item disabled"><span class="page-link">...</span></li>
            <?php endif;
            endfor; ?>
            
            <?php if ($paginaActual < $totalPaginas): ?>
            <li class="page-item">
                <a class="page-link" href="mis-solicitudes.php<?php echo $baseQuery; ?>pagina=<?php echo $paginaActual + 1; ?>">
                    <i class="fas fa-chevron-right"></i>
                </a>
            </li>
            <?php else: ?>
            <li class="page-item disabled"><span class="page-link"><i class="fas fa-chevron-right"></i></span></li>
            <?php endif; ?>
        </ul>
    </div>
    <?php endif; ?>
    
</div>

<?php include 'includes/footer.php'; ?>
