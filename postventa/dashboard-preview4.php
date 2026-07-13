<?php
/**
 * Dashboard Preview v4 - Postventa Centinela
 * Versión con imágenes integradas como tarjetas tipo "masonry" entre el contenido.
 * Accesible en: dashboard-preview4.php
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
   TARJETAS DE EDIFICIOS ENTRE CONTENIDO - v4
   ============================================ */

.dashboard-container {
    max-width: 1100px;
}

/* Grid de 2 columnas: contenido principal + columna de imágenes */
.dashboard-grid {
    display: grid;
    grid-template-columns: 1fr 280px;
    gap: 24px;
    align-items: start;
}

.dashboard-main-col {
    min-width: 0;
    overflow: hidden;
}

/* Evitar que stats-grid y la tabla desborden el contenedor */
.dashboard-main-col .stats-grid,
.dashboard-main-col .card,
.dashboard-main-col .table-container {
    max-width: 100%;
    overflow: hidden;
}

.dashboard-main-col .table-container {
    overflow-x: auto;
}

/* Columna de imágenes con diseño variado */
.images-column {
    display: flex;
    flex-direction: column;
    gap: 20px;
    position: sticky;
    top: 100px;
}

/* Tarjeta de imagen con overlay de texto */
.image-feature-card {
    position: relative;
    border-radius: var(--radius-md);
    overflow: hidden;
    box-shadow: var(--shadow-sm);
    border: 1px solid var(--color-gray-200);
    transition: transform 0.3s, box-shadow 0.3s;
}

.image-feature-card:hover {
    transform: translateY(-3px);
    box-shadow: var(--shadow-md);
}

.image-feature-card img {
    width: 100%;
    height: auto;
    display: block;
    aspect-ratio: 2 / 3;
    object-fit: cover;
}

.image-feature-card .image-overlay {
    position: absolute;
    bottom: 0;
    left: 0;
    right: 0;
    padding: 20px 16px 12px;
    background: linear-gradient(transparent, rgba(0,0,0,0.75));
    color: #fff;
}

.image-overlay .overlay-title {
    font-family: var(--font-heading);
    font-weight: 600;
    font-size: 0.85rem;
    margin-bottom: 2px;
}

.image-overlay .overlay-sub {
    font-size: 0.72rem;
    opacity: 0.8;
}

/* Variar alturas para efecto masonry */
.image-feature-card.tall img { aspect-ratio: 2 / 3.5; }
.image-feature-card.short img { aspect-ratio: 3 / 4; }

/* Banner horizontal entre stats y tabla */
.promo-banner {
    background: linear-gradient(135deg, var(--color-primary) 0%, #4a6b10 100%);
    border-radius: var(--radius-md);
    padding: 24px 28px;
    margin-bottom: 28px;
    display: flex;
    align-items: center;
    gap: 24px;
    color: #fff;
    box-shadow: var(--shadow-sm);
}

.promo-banner-icon {
    width: 80px;
    height: 80px;
    border-radius: var(--radius-md);
    overflow: hidden;
    flex-shrink: 0;
    border: 2px solid rgba(255,255,255,0.3);
}

.promo-banner-icon img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.promo-banner-text h3 {
    font-family: var(--font-heading);
    font-weight: 600;
    font-size: 1rem;
    margin-bottom: 4px;
}

.promo-banner-text p {
    font-size: 0.82rem;
    opacity: 0.85;
    line-height: 1.4;
}

/* Responsive */
@media (max-width: 1000px) {
    .dashboard-grid {
        grid-template-columns: 1fr;
    }
    .images-column {
        flex-direction: row;
        position: static;
        overflow-x: auto;
        padding-bottom: 4px;
    }
    .image-feature-card {
        min-width: 180px;
        flex-shrink: 0;
    }
}

@media (max-width: 768px) {
    .images-column { display: none; }
    .promo-banner { flex-direction: column; text-align: center; }
}
</style>

<div class="dashboard-page">
    <div class="dashboard-container">
        
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
        
        <?php if (isset($_GET['success'])): ?>
        <div class="alert alert-success"><i class="fas fa-check-circle"></i> ¡Tu solicitud ha sido ingresada exitosamente!</div>
        <?php endif; ?>
        
        <div class="dashboard-grid">
            
            <!-- COLUMNA PRINCIPAL -->
            <div class="dashboard-main-col">
                
                <!-- Stats -->
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
                
                <!-- Banner promocional con imagen de edificio -->
                <div class="promo-banner">
                    <div class="promo-banner-icon">
                        <img src="<?php echo IMG_URL; ?>centinela2.png" alt="Edificio" loading="lazy">
                    </div>
                    <div class="promo-banner-text">
                        <h3><i class="fas fa-building"></i> Proyectos Centinela</h3>
                        <p>Con más de 15 años de experiencia, seguimos construyendo hogares con los más altos estándares de calidad. Tu confianza es nuestro mejor proyecto.</p>
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
                
            </div><!-- /dashboard-main-col -->
            
            <!-- COLUMNA DE IMÁGENES -->
            <aside class="images-column">
                <div class="image-feature-card tall">
                    <img src="<?php echo IMG_URL; ?>centinela1.png" alt="Edificio 1" loading="lazy">
                    <div class="image-overlay">
                        <div class="overlay-title">Proyectos Destacados</div>
                        <div class="overlay-sub">Calidad que perdura</div>
                    </div>
                </div>
                <div class="image-feature-card short">
                    <img src="<?php echo IMG_URL; ?>centinela2.png" alt="Edificio 2" loading="lazy">
                    <div class="image-overlay">
                        <div class="overlay-title">Innovación</div>
                        <div class="overlay-sub">Diseño y confort</div>
                    </div>
                </div>
                <div class="image-feature-card tall">
                    <img src="<?php echo IMG_URL; ?>centinela3.png" alt="Edificio 3" loading="lazy">
                    <div class="image-overlay">
                        <div class="overlay-title">Tu Nuevo Hogar</div>
                        <div class="overlay-sub">Donde la vida sucede</div>
                    </div>
                </div>
            </aside>
            
        </div><!-- /dashboard-grid -->
        
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
