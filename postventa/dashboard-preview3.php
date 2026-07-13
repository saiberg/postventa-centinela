<?php
/**
 * Dashboard Preview v3 - Postventa Centinela
 * Versión con imagen hero full-width de fondo y contenido flotante encima.
 * Accesible en: dashboard-preview3.php
 */
require_once 'includes/config.php';
require_once 'includes/api_helper.php';

if (!isset($_SESSION['usuario_id'])) { header('Location: login.php'); exit; }

$isAdmin = isset($_SESSION['es_admin']) && $_SESSION['es_admin'] == 1;
$isAdminSistema = isset($_SESSION['usuario_rol']) && $_SESSION['usuario_rol'] === 'admin_sistema';

$estadoLabel = array(
    'pendiente' => 'Pendiente', 'aprobado' => 'Aprobado', 'no_corresponde' => 'No Corresponde',
    'agendado' => 'Agendado', 'en_proceso' => 'En Proceso', 'resuelto' => 'Resuelto'
);

if ($isAdminSistema) {
    $apiResponse = apiCall('solicitudes.php?action=todas', array());
    $solicitudesRaw = ($apiResponse['success'] && isset($apiResponse['solicitudes'])) ? $apiResponse['solicitudes'] : array();
    $globalStats = ($apiResponse['success'] && isset($apiResponse['stats'])) ? $apiResponse['stats'] : array('total' => 0, 'pendientes' => 0, 'en_proceso' => 0, 'resueltos' => 0, 'no_corresponde' => 0);
} else {
    $apiResponse = apiCall('solicitudes.php?action=mis_solicitudes', array());
    $solicitudesRaw = ($apiResponse['success'] && isset($apiResponse['solicitudes'])) ? $apiResponse['solicitudes'] : array();
}

$casos = array();
foreach ($solicitudesRaw as $row) {
    $casos[] = array(
        'id' => 'PC-' . date('Y', strtotime($row['created_at'])) . '-' . str_pad($row['id'], 3, '0', STR_PAD_LEFT),
        'id_num' => $row['id'], 'fecha' => date('d/m/Y', strtotime($row['created_at'])),
        'categoria' => $row['categoria'], 'subcategoria' => $row['subcategoria'],
        'ubicacion' => $row['ubicacion_valor'], 'estado' => $row['estado'],
        'estado_label' => isset($estadoLabel[$row['estado']]) ? $estadoLabel[$row['estado']] : $row['estado'],
        'agendamiento' => isset($row['fecha_agendamiento']) && $row['fecha_agendamiento'] ? date('d/m/Y - H:i', strtotime($row['fecha_agendamiento'])) : null,
        'equipo' => isset($row['equipo_asignado']) ? $row['equipo_asignado'] : null,
        'rut' => isset($row['rut']) ? $row['rut'] : '', 'nombre_solic' => isset($row['nombre']) ? $row['nombre'] : '',
        'email_solic' => isset($row['email']) ? $row['email'] : '', 'telefono_solic' => isset($row['telefono']) ? $row['telefono'] : '',
        'rol_solicitante' => isset($row['rol_solicitante']) ? $row['rol_solicitante'] : ''
    );
}

if ($isAdminSistema) {
    $totalCasos = (int)$globalStats['total']; $pendientes = (int)$globalStats['pendientes'];
    $enProceso = (int)$globalStats['en_proceso']; $resueltos = (int)$globalStats['resueltos'];
    $noCorresponde = (int)$globalStats['no_corresponde'];
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
   HERO FULL-WIDTH CON PARALLAX - v3
   ============================================ */

/* Override del fondo del dashboard */
.dashboard-page {
    background: var(--color-gray-100);
}

/* Sección hero con imagen de fondo */
.dashboard-hero {
    position: relative;
    width: 100%;
    min-height: 320px;
    background: url('<?php echo IMG_URL; ?>centinela1.png') center/cover no-repeat;
    background-attachment: fixed;
    display: flex;
    align-items: center;
    justify-content: center;
    margin-bottom: -40px;
}

.dashboard-hero::before {
    content: '';
    position: absolute;
    inset: 0;
    background: linear-gradient(180deg, rgba(0,0,0,0.55) 0%, rgba(96,132,24,0.7) 100%);
}

.hero-content {
    position: relative;
    z-index: 2;
    text-align: center;
    color: #fff;
    padding: 40px 20px;
}

.hero-content h1 {
    font-family: var(--font-heading);
    font-size: 2.2rem;
    font-weight: 700;
    margin-bottom: 8px;
    text-shadow: 0 2px 10px rgba(0,0,0,0.4);
}

.hero-content p {
    font-size: 1.05rem;
    opacity: 0.9;
    max-width: 600px;
    margin: 0 auto;
    text-shadow: 0 1px 4px rgba(0,0,0,0.3);
}

/* Franja de miniaturas debajo del hero */
.hero-thumbs {
    display: flex;
    justify-content: center;
    gap: 16px;
    position: relative;
    z-index: 3;
    margin-top: -40px;
    margin-bottom: 32px;
    padding: 0 20px;
}

.hero-thumb {
    width: 100px;
    height: 130px;
    border-radius: var(--radius-md);
    overflow: hidden;
    box-shadow: var(--shadow-md);
    border: 3px solid var(--color-white);
    transition: transform 0.3s;
    cursor: pointer;
}

.hero-thumb:hover {
    transform: translateY(-4px);
}

.hero-thumb img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

/* Contenedor principal elevado */
.dashboard-container {
    position: relative;
    z-index: 2;
    max-width: 1100px;
}

/* Responsive */
@media (max-width: 768px) {
    .dashboard-hero {
        min-height: 220px;
        background-attachment: scroll;
    }
    .hero-content h1 { font-size: 1.5rem; }
    .hero-content p { font-size: 0.9rem; }
    .hero-thumb { width: 70px; height: 95px; }
}
</style>

<div class="dashboard-page">
    
    <!-- HERO CON IMAGEN DE FONDO -->
    <section class="dashboard-hero">
        <div class="hero-content">
            <h1><?php echo $isAdminSistema ? 'Panel de Control General' : 'Mi Panel de Postventa'; ?></h1>
            <p>Bienvenido, <?php echo htmlspecialchars($_SESSION['usuario_nombre']); ?>. 
               <?php echo $isAdminSistema ? 'Monitorea todas las solicitudes del sistema.' : 'Revisa el estado de tus solicitudes.'; ?></p>
        </div>
    </section>
    
    <!-- MINIATURAS DE EDIFICIOS -->
    <div class="hero-thumbs">
        <div class="hero-thumb">
            <img src="<?php echo IMG_URL; ?>centinela1.png" alt="Edificio 1" loading="lazy">
        </div>
        <div class="hero-thumb">
            <img src="<?php echo IMG_URL; ?>centinela2.png" alt="Edificio 2" loading="lazy">
        </div>
        <div class="hero-thumb">
            <img src="<?php echo IMG_URL; ?>centinela3.png" alt="Edificio 3" loading="lazy">
        </div>
    </div>
    
    <div class="dashboard-container">
        
        <!-- Mensaje de éxito -->
        <?php if (isset($_GET['success'])): ?>
        <div class="alert alert-success">
            <i class="fas fa-check-circle"></i> ¡Tu solicitud ha sido ingresada exitosamente!
        </div>
        <?php endif; ?>
        
        <!-- Tarjetas de Estadísticas -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon blue"><i class="fas fa-clipboard-list"></i></div>
                <div class="stat-info"><div class="stat-value"><?php echo $totalCasos; ?></div><div class="stat-label">Total Solicitudes</div></div>
            </div>
            <div class="stat-card">
                <div class="stat-icon orange"><i class="fas fa-clock"></i></div>
                <div class="stat-info"><div class="stat-value"><?php echo $pendientes; ?></div><div class="stat-label">Pendientes</div></div>
            </div>
            <div class="stat-card">
                <div class="stat-icon blue"><i class="fas fa-spinner"></i></div>
                <div class="stat-info"><div class="stat-value"><?php echo $enProceso; ?></div><div class="stat-label">En Proceso</div></div>
            </div>
            <div class="stat-card">
                <div class="stat-icon green"><i class="fas fa-check-circle"></i></div>
                <div class="stat-info"><div class="stat-value"><?php echo $resueltos; ?></div><div class="stat-label">Resueltos</div></div>
            </div>
        </div>
        
        <!-- Tabla -->
        <div class="card cases-table-card">
            <div class="card-header">
                <h3><i class="fas fa-list-ul"></i> <?php echo $isAdminSistema ? 'Todas las Solicitudes' : 'Mis Solicitudes'; ?></h3>
                <div class="table-actions">
                    <div class="search-box"><i class="fas fa-search"></i><input type="text" id="tableSearch" placeholder="Buscar solicitudes..."></div>
                    <select class="filter-select" id="filterEstado">
                        <option value="">Todos los estados</option>
                        <option value="pendiente">Pendiente</option><option value="aprobado">Aprobado</option>
                        <option value="agendado">Agendado</option><option value="en_proceso">En Proceso</option>
                        <option value="resuelto">Resuelto</option><option value="no_corresponde">No Corresponde</option>
                    </select>
                    <?php if (!$isAdminSistema): ?>
                    <a href="nueva-solicitud.php" class="btn btn-primary"><i class="fas fa-plus-circle"></i> Nueva Solicitud</a>
                    <?php endif; ?>
                </div>
            </div>
            <div class="card-body" style="padding:0;">
                <div class="table-container"><table>
                    <thead><tr>
                        <th>N° Caso</th><th>Fecha</th>
                        <?php if ($isAdminSistema): ?><th>Solicitante</th><th>RUT</th><th>Rol</th><?php endif; ?>
                        <th>Categoría</th><th>Subcategoría</th><th>Ubicación</th><th>Estado</th>
                        <th>Agendamiento</th><th>Equipo</th><th>Acción</th>
                    </tr></thead>
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
                            <td><?php echo $caso['categoria']; ?></td><td><?php echo $caso['subcategoria']; ?></td>
                            <td><?php echo $caso['ubicacion']; ?></td>
                            <td><?php
                                $badgeClass = ''; switch ($caso['estado']) {
                                    case 'pendiente': $badgeClass = 'badge-pending'; break;
                                    case 'aprobado': $badgeClass = 'badge-approved'; break;
                                    case 'agendado': $badgeClass = 'badge-scheduled'; break;
                                    case 'en_proceso': $badgeClass = 'badge-in-progress'; break;
                                    case 'resuelto': $badgeClass = 'badge-resolved'; break;
                                    case 'no_corresponde': $badgeClass = 'badge-rejected'; break;
                                }
                            ?><span class="badge <?php echo $badgeClass; ?>"><?php echo $caso['estado_label']; ?></span></td>
                            <td><?php if ($caso['agendamiento']): ?><span class="case-schedule"><i class="fas fa-calendar-alt"></i> <?php echo $caso['agendamiento']; ?></span><?php else: ?><span class="text-muted">—</span><?php endif; ?></td>
                            <td><?php if ($caso['equipo']): ?><span class="case-schedule"><strong><?php echo $caso['equipo']; ?></strong></span><?php else: ?><span class="text-muted">—</span><?php endif; ?></td>
                            <td><a href="detalle-caso.php?id=<?php echo $caso['id_num']; ?>" class="btn btn-sm btn-outline"><i class="fas fa-eye"></i> Ver</a></td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if (empty($casos)): ?>
                        <tr><td colspan="<?php echo $isAdminSistema ? '12' : '9'; ?>" style="text-align:center;padding:40px;color:#888;"><i class="fas fa-inbox" style="font-size:2rem;display:block;margin-bottom:10px;"></i>No hay solicitudes registradas.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table></div>
            </div>
        </div>
        
    </div>
</div>

<script>
$(document).ready(function(){
    $('#filterEstado').on('change',function(){
        var e=$(this).val();
        if(e){$('.case-row').hide();$('.case-row[data-estado="'+e+'"]').show();}else{$('.case-row').show();}
    });
    $('#tableSearch').on('keyup',function(){
        var s=$(this).val().toLowerCase();
        $('.case-row').each(function(){$(this).toggle($(this).text().toLowerCase().indexOf(s)>-1);});
    });
});
</script>

<?php include 'includes/footer.php'; ?>
