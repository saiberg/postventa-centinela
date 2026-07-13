<?php
/**
 * Dashboard Preview v2 - Postventa Centinela
 * Versión con 2 imágenes a la izquierda y 1 a la derecha del contenido.
 * Accesible en: dashboard-preview2.php
 */
require_once 'includes/config.php';
require_once 'includes/api_helper.php';

// Verificar sesión
if (!isset($_SESSION['usuario_id'])) {
    header('Location: login.php');
    exit;
}

$isAdmin = isset($_SESSION['es_admin']) && $_SESSION['es_admin'] == 1;
$isAdminSistema = isset($_SESSION['usuario_rol']) && $_SESSION['usuario_rol'] === 'admin_sistema';

$estadoLabel = array(
    'pendiente'      => 'Pendiente',
    'aprobado'       => 'Aprobado',
    'no_corresponde' => 'No Corresponde',
    'agendado'       => 'Agendado',
    'en_proceso'     => 'En Proceso',
    'resuelto'       => 'Resuelto'
);

if ($isAdminSistema) {
    $apiResponse = apiCall('solicitudes.php?action=todas', array());
    $solicitudesRaw = ($apiResponse['success'] && isset($apiResponse['solicitudes'])) ? $apiResponse['solicitudes'] : array();
    $globalStats = ($apiResponse['success'] && isset($apiResponse['stats'])) ? $apiResponse['stats'] : array(
        'total' => 0, 'pendientes' => 0, 'en_proceso' => 0, 'resueltos' => 0, 'no_corresponde' => 0
    );
} else {
    $apiResponse = apiCall('solicitudes.php?action=mis_solicitudes', array());
    $solicitudesRaw = ($apiResponse['success'] && isset($apiResponse['solicitudes'])) ? $apiResponse['solicitudes'] : array();
}

$casos = array();
foreach ($solicitudesRaw as $row) {
    $casos[] = array(
        'id'             => 'PC-' . date('Y', strtotime($row['created_at'])) . '-' . str_pad($row['id'], 3, '0', STR_PAD_LEFT),
        'id_num'         => $row['id'],
        'fecha'          => date('d/m/Y', strtotime($row['created_at'])),
        'categoria'      => $row['categoria'],
        'subcategoria'   => $row['subcategoria'],
        'ubicacion'      => $row['ubicacion_valor'],
        'estado'         => $row['estado'],
        'estado_label'   => isset($estadoLabel[$row['estado']]) ? $estadoLabel[$row['estado']] : $row['estado'],
        'agendamiento'   => isset($row['fecha_agendamiento']) && $row['fecha_agendamiento'] ? date('d/m/Y - H:i', strtotime($row['fecha_agendamiento'])) : null,
        'equipo'         => isset($row['equipo_asignado']) ? $row['equipo_asignado'] : null,
        'rut'            => isset($row['rut']) ? $row['rut'] : '',
        'nombre_solic'   => isset($row['nombre']) ? $row['nombre'] : '',
        'email_solic'    => isset($row['email']) ? $row['email'] : '',
        'telefono_solic' => isset($row['telefono']) ? $row['telefono'] : '',
        'rol_solicitante'=> isset($row['rol_solicitante']) ? $row['rol_solicitante'] : ''
    );
}

if ($isAdminSistema) {
    $totalCasos     = (int)$globalStats['total'];
    $pendientes     = (int)$globalStats['pendientes'];
    $enProceso      = (int)$globalStats['en_proceso'];
    $resueltos      = (int)$globalStats['resueltos'];
    $noCorresponde  = (int)$globalStats['no_corresponde'];
} else {
    $totalCasos = count($casos);
    $pendientes = count(array_filter($casos, function($c) { return $c['estado'] === 'pendiente'; }));
    $enProceso = count(array_filter($casos, function($c) { return in_array($c['estado'], ['aprobado', 'agendado', 'en_proceso']); }));
    $resueltos = count(array_filter($casos, function($c) { return $c['estado'] === 'resuelto'; }));
    $noCorresponde = count(array_filter($casos, function($c) { return $c['estado'] === 'no_corresponde'; }));
}

include 'includes/header.php';
?>

<style>
/* ============================================
   LAYOUT CON IMÁGENES A AMBOS LADOS - v2
   ============================================ */

/* Ampliar el contenedor para dar espacio a los sidebars */
.dashboard-container {
    max-width: 1300px;
}

.dashboard-layout {
    display: flex;
    gap: 24px;
    align-items: flex-start;
    justify-content: center;
}

/* Sidebar izquierdo: 2 imágenes apiladas */
.dashboard-sidebar-left {
    width: 200px;
    flex-shrink: 0;
    display: flex;
    flex-direction: column;
    gap: 16px;
    position: sticky;
    top: 100px;
}

/* Sidebar derecho: 1 imagen */
.dashboard-sidebar-right {
    width: 200px;
    flex-shrink: 0;
    position: sticky;
    top: 100px;
}

/* Contenido central */
.dashboard-main {
    flex: 1;
    max-width: 800px;
}

/* Tarjetas de imagen */
.sidebar-image-card {
    background: var(--color-white);
    border-radius: var(--radius-md);
    overflow: hidden;
    box-shadow: var(--shadow-sm);
    border: 1px solid var(--color-gray-200);
    transition: transform var(--transition-fast), box-shadow var(--transition-fast);
}

.sidebar-image-card:hover {
    transform: translateY(-2px);
    box-shadow: var(--shadow-md);
}

.sidebar-image-card img {
    width: 100%;
    height: auto;
    display: block;
    aspect-ratio: 2 / 3;
    object-fit: cover;
}

/* Responsive */
@media (max-width: 1300px) {
    .dashboard-sidebar-left,
    .dashboard-sidebar-right {
        width: 160px;
    }
}

@media (max-width: 1100px) {
    .dashboard-layout {
        flex-direction: column;
    }
    
    .dashboard-sidebar-left,
    .dashboard-sidebar-right {
        width: 100%;
        flex-direction: row;
        gap: 12px;
        position: static;
        overflow-x: auto;
        padding-bottom: 4px;
    }
    
    .dashboard-sidebar-left {
        display: flex;
        flex-direction: row;
    }
    
    .sidebar-image-card {
        min-width: 160px;
        flex-shrink: 0;
    }
    
    .sidebar-image-card img {
        aspect-ratio: 3 / 4;
    }
}

@media (max-width: 768px) {
    .dashboard-sidebar-left,
    .dashboard-sidebar-right {
        display: none;
    }
}
</style>

<div class="dashboard-page">
    <div class="dashboard-container">
        
        <div class="dashboard-layout">
            
            <!-- SIDEBAR IZQUIERDO: 2 imágenes -->
            <aside class="dashboard-sidebar-left">
                <div class="sidebar-image-card">
                    <img src="<?php echo IMG_URL; ?>centinela1.png" alt="Edificio Centinela 1" loading="lazy">
                </div>
                <div class="sidebar-image-card">
                    <img src="<?php echo IMG_URL; ?>centinela2.png" alt="Edificio Centinela 2" loading="lazy">
                </div>
            </aside>
            
            <!-- CONTENIDO PRINCIPAL -->
            <div class="dashboard-main">
        
        <!-- Cabecera -->
        <div class="dashboard-header">
            <?php if ($isAdminSistema): ?>
            <h1><i class="fas fa-tachometer-alt"></i> Panel de Control General</h1>
            <p class="welcome-text">Bienvenido, <?php echo htmlspecialchars($_SESSION['usuario_nombre']); ?>. Aquí puedes monitorear el estado de <strong>todas las solicitudes</strong> del sistema.</p>
            <?php else: ?>
            <h1><i class="fas fa-tachometer-alt"></i> Mi Panel de Postventa</h1>
            <p class="welcome-text">Bienvenido, <?php echo htmlspecialchars($_SESSION['usuario_nombre']); ?>. Aquí puedes revisar el estado de tus solicitudes.</p>
            <?php endif; ?>
        </div>
        
        <!-- Mensaje de éxito -->
        <?php if (isset($_GET['success'])): ?>
        <div class="alert alert-success">
            <i class="fas fa-check-circle"></i> ¡Tu solicitud ha sido ingresada exitosamente! Te notificaremos cuando sea revisada.
        </div>
        <?php endif; ?>
        
        <!-- Tarjetas de Estadísticas -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon blue">
                    <i class="fas fa-clipboard-list"></i>
                </div>
                <div class="stat-info">
                    <div class="stat-value"><?php echo $totalCasos; ?></div>
                    <div class="stat-label">Total Solicitudes</div>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon orange">
                    <i class="fas fa-clock"></i>
                </div>
                <div class="stat-info">
                    <div class="stat-value"><?php echo $pendientes; ?></div>
                    <div class="stat-label">Pendientes</div>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon blue">
                    <i class="fas fa-spinner"></i>
                </div>
                <div class="stat-info">
                    <div class="stat-value"><?php echo $enProceso; ?></div>
                    <div class="stat-label">En Proceso</div>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon green">
                    <i class="fas fa-check-circle"></i>
                </div>
                <div class="stat-info">
                    <div class="stat-value"><?php echo $resueltos; ?></div>
                    <div class="stat-label">Resueltos</div>
                </div>
            </div>
        </div>
        
        <!-- Tabla de Solicitudes -->
        <div class="card cases-table-card">
            <div class="card-header">
                <h3><i class="fas fa-list-ul"></i> <?php echo $isAdminSistema ? 'Todas las Solicitudes' : 'Mis Solicitudes'; ?></h3>
                <div class="table-actions">
                    <div class="search-box">
                        <i class="fas fa-search"></i>
                        <input type="text" id="tableSearch" placeholder="Buscar solicitudes...">
                    </div>
                    <select class="filter-select" id="filterEstado">
                        <option value="">Todos los estados</option>
                        <option value="pendiente">Pendiente</option>
                        <option value="aprobado">Aprobado</option>
                        <option value="agendado">Agendado</option>
                        <option value="en_proceso">En Proceso</option>
                        <option value="resuelto">Resuelto</option>
                        <option value="no_corresponde">No Corresponde</option>
                    </select>
                    <?php if (!$isAdminSistema): ?>
                    <a href="nueva-solicitud.php" class="btn btn-primary">
                        <i class="fas fa-plus-circle"></i> Nueva Solicitud
                    </a>
                    <?php endif; ?>
                </div>
            </div>
            <div class="card-body" style="padding: 0;">
                <div class="table-container">
                    <table>
                        <thead>
                            <tr>
                                <th>N° Caso</th>
                                <th>Fecha</th>
                                <?php if ($isAdminSistema): ?>
                                <th>Solicitante</th>
                                <th>RUT</th>
                                <th>Rol</th>
                                <?php endif; ?>
                                <th>Categoría</th>
                                <th>Subcategoría</th>
                                <th>Ubicación</th>
                                <th>Estado</th>
                                <th>Agendamiento</th>
                                <th>Equipo</th>
                                <th>Acción</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($casos as $caso): ?>
                            <tr class="case-row" data-estado="<?php echo $caso['estado']; ?>">
                                <td><span class="case-id">#<?php echo $caso['id']; ?></span></td>
                                <td><span class="case-date"><?php echo $caso['fecha']; ?></span></td>
                                <?php if ($isAdminSistema): ?>
                                <td><?php echo htmlspecialchars($caso['nombre_solic']); ?></td>
                                <td><?php echo htmlspecialchars($caso['rut']); ?></td>
                                <td><?php echo $caso['rol_solicitante'] === 'administrador_edificio' ? 'Admin. Edificio' : 'Propietario'; ?></td>
                                <?php endif; ?>
                                <td><?php echo $caso['categoria']; ?></td>
                                <td><?php echo $caso['subcategoria']; ?></td>
                                <td><?php echo $caso['ubicacion']; ?></td>
                                <td>
                                    <?php
                                    $badgeClass = '';
                                    switch ($caso['estado']) {
                                        case 'pendiente': $badgeClass = 'badge-pending'; break;
                                        case 'aprobado': $badgeClass = 'badge-approved'; break;
                                        case 'agendado': $badgeClass = 'badge-scheduled'; break;
                                        case 'en_proceso': $badgeClass = 'badge-in-progress'; break;
                                        case 'resuelto': $badgeClass = 'badge-resolved'; break;
                                        case 'no_corresponde': $badgeClass = 'badge-rejected'; break;
                                    }
                                    ?>
                                    <span class="badge <?php echo $badgeClass; ?>"><?php echo $caso['estado_label']; ?></span>
                                </td>
                                <td>
                                    <?php if ($caso['agendamiento']): ?>
                                    <span class="case-schedule">
                                        <i class="fas fa-calendar-alt"></i> <?php echo $caso['agendamiento']; ?>
                                    </span>
                                    <?php else: ?>
                                    <span class="text-muted">—</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($caso['equipo']): ?>
                                    <span class="case-schedule"><strong><?php echo $caso['equipo']; ?></strong></span>
                                    <?php else: ?>
                                    <span class="text-muted">—</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <a href="detalle-caso.php?id=<?php echo $caso['id_num']; ?>" class="btn btn-sm btn-outline">
                                        <i class="fas fa-eye"></i> Ver
                                    </a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                            <?php if (empty($casos)): ?>
                            <tr>
                                <td colspan="<?php echo $isAdminSistema ? '12' : '9'; ?>" style="text-align:center; padding: 40px; color: #888;">
                                    <i class="fas fa-inbox" style="font-size: 2rem; display: block; margin-bottom: 10px;"></i>
                                    No hay solicitudes registradas.
                                </td>
                            </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
                
                <!-- Paginación -->
                <?php 
                $porPagina = 10;
                $totalPaginas = ceil($totalCasos / $porPagina);
                if ($totalPaginas > 1): 
                ?>
                <div style="padding: 16px 24px;">
                    <ul class="pagination">
                        <li class="page-item disabled"><span class="page-link"><i class="fas fa-chevron-left"></i></span></li>
                        <?php for ($p = 1; $p <= $totalPaginas; $p++): ?>
                        <li class="page-item <?php echo $p == 1 ? 'active' : ''; ?>">
                            <span class="page-link"><?php echo $p; ?></span>
                        </li>
                        <?php endfor; ?>
                        <li class="page-item disabled"><span class="page-link"><i class="fas fa-chevron-right"></i></span></li>
                    </ul>
                </div>
                <?php endif; ?>
            </div>
        </div>
        
            </div><!-- /dashboard-main -->
            
            <!-- SIDEBAR DERECHO: 1 imagen -->
            <aside class="dashboard-sidebar-right">
                <div class="sidebar-image-card">
                    <img src="<?php echo IMG_URL; ?>centinela3.png" alt="Edificio Centinela 3" loading="lazy">
                </div>
            </aside>
            
        </div><!-- /dashboard-layout -->
        
    </div>
</div>

<script>
$(document).ready(function() {
    $('#filterEstado').on('change', function() {
        var estado = $(this).val();
        if (estado) {
            $('.case-row').hide();
            $('.case-row[data-estado="' + estado + '"]').show();
        } else {
            $('.case-row').show();
        }
    });
    
    $('#tableSearch').on('keyup', function() {
        var search = $(this).val().toLowerCase();
        $('.case-row').each(function() {
            var text = $(this).text().toLowerCase();
            $(this).toggle(text.indexOf(search) > -1);
        });
    });
});
</script>

<?php include 'includes/footer.php'; ?>
