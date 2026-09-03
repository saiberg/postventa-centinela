<?php
/**
 * Dashboard del Cliente - Postventa Centinela
 * Muestra el panel principal con estadísticas y casos del cliente
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
    'resuelto'       => 'Resuelto'
);

if ($isAdminSistema) {
    // Admin sistema: obtener TODAS las solicitudes del sistema
    $apiResponse = apiCall('solicitudes.php?action=todas', array());
    $solicitudesRaw = ($apiResponse['success'] && isset($apiResponse['solicitudes'])) ? $apiResponse['solicitudes'] : array();
    $globalStats = ($apiResponse['success'] && isset($apiResponse['stats'])) ? $apiResponse['stats'] : array(
        'total' => 0, 'pendientes' => 0, 'en_gestion' => 0, 'resueltos' => 0, 'no_corresponde' => 0
    );
} else {
    // Usuario normal: obtener solo sus solicitudes
    $apiResponse = apiCall('solicitudes.php?action=mis_solicitudes', array());
    $solicitudesRaw = ($apiResponse['success'] && isset($apiResponse['solicitudes'])) ? $apiResponse['solicitudes'] : array();
}

$casos = array();
$proyectosDisponibles = array();
foreach ($solicitudesRaw as $row) {
    $proyectoNombre = isset($row['obra_nombre']) && trim($row['obra_nombre']) !== '' ? trim($row['obra_nombre']) : 'Sin proyecto';
    $proyectosDisponibles[] = $proyectoNombre;

    $casos[] = array(
        'id'             => 'PC-' . date('Y', strtotime($row['created_at'])) . '-' . str_pad($row['id'], 3, '0', STR_PAD_LEFT),
        'id_num'         => $row['id'],
        'fecha'          => date('d/m/Y', strtotime($row['created_at'])),
        'categoria'      => $row['categoria'],
        'subcategoria'   => $row['subcategoria'],
        'ubicacion'      => $row['ubicacion_valor'],
        'estado'         => $row['estado'],
        'estado_label'   => isset($estadoLabel[$row['estado']]) ? $estadoLabel[$row['estado']] : $row['estado'],
        'proyecto'       => $proyectoNombre,
        // Campos extra para vista admin
        'rut'            => isset($row['rut']) ? $row['rut'] : '',
        'nombre_solic'   => isset($row['nombre']) ? $row['nombre'] : '',
        'email_solic'    => isset($row['email']) ? $row['email'] : '',
        'telefono_solic' => isset($row['telefono']) ? $row['telefono'] : '',
        'rol_solicitante'=> isset($row['rol_solicitante']) ? $row['rol_solicitante'] : ''
    );
}
$proyectosDisponibles = array_values(array_unique($proyectosDisponibles));

// Estadísticas
if ($isAdminSistema) {
    $totalCasos     = (int)$globalStats['total'];
    $pendientes     = (int)$globalStats['pendientes'];
    $enProceso      = (int)$globalStats['en_gestion'];
    $resueltos      = (int)$globalStats['resueltos'];
    $noCorresponde  = (int)$globalStats['no_corresponde'];
} else {
    $totalCasos = count($casos);
    $pendientes = count(array_filter($casos, function($c) { return $c['estado'] === 'pendiente'; }));
    $enProceso = count(array_filter($casos, function($c) { return $c['estado'] === 'aprobado'; }));
    $resueltos = count(array_filter($casos, function($c) { return $c['estado'] === 'resuelto'; }));
    $noCorresponde = count(array_filter($casos, function($c) { return $c['estado'] === 'no_corresponde'; }));
}

// Distribución por proyecto (solo admin_sistema)
$porProyecto = array();
if ($isAdminSistema && isset($apiResponse['por_proyecto'])) {
    $porProyecto = $apiResponse['por_proyecto'];
}

include 'includes/header.php';
?>

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
        
        <!-- Mensaje de éxito (si viene de crear solicitud) -->
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
        
        <!-- Distribución de Casos por Proyecto -->
        <?php if ($isAdminSistema && !empty($porProyecto)): ?>
        <div class="card cases-table-card" style="margin-bottom: 24px;">
            <div class="card-header">
                <h3><i class="fas fa-chart-bar"></i> Distribución de Casos por Proyecto</h3>
            </div>
            <div class="card-body" style="padding: 0;">
                <div class="table-container">
                    <table class="table-proyectos">
                        <thead>
                            <tr>
                                <th>Proyecto</th>
                                <th style="text-align:center;">Total</th>
                                <th style="text-align:center;">Pendientes</th>
                                <th style="text-align:center;">Resueltos</th>
                                <th style="text-align:center;">Abiertos</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($porProyecto as $proy): ?>
                            <tr>
                                <td><strong><?php echo htmlspecialchars($proy['proyecto']); ?></strong></td>
                                <td style="text-align:center;"><span class="badge badge-pending"><?php echo (int)$proy['total']; ?></span></td>
                                <td style="text-align:center;"><?php echo (int)$proy['pendientes']; ?></td>
                                <td style="text-align:center;"><?php echo (int)$proy['resueltos']; ?></td>
                                <td style="text-align:center;"><?php echo (int)$proy['abiertos']; ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- Tabla de Solicitudes -->
        <div class="card cases-table-card">
            <div class="card-header">
                <h3><i class="fas fa-list-ul"></i> <?php echo $isAdminSistema ? 'Todas las Solicitudes' : 'Mis Solicitudes'; ?></h3>
                <div class="table-actions">
                    <div class="search-box">
                        <i class="fas fa-search"></i>
                        <input type="text" id="tableSearch" placeholder="Buscar solicitudes...">
                    </div>
                    <select class="filter-select" id="filterProyecto">
                        <option value="">Todos los proyectos</option>
                        <?php foreach ($proyectosDisponibles as $proyecto): ?>
                        <option value="<?php echo htmlspecialchars($proyecto); ?>"><?php echo htmlspecialchars($proyecto); ?></option>
                        <?php endforeach; ?>
                    </select>
                    <select class="filter-select" id="filterEstado">
                        <option value="">Todos los estados</option>
                        <option value="pendiente">Pendiente</option>
                        <option value="aprobado">Aprobado</option>
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
                                <th>Rol</th>
                                <?php endif; ?>
                                <th>Categoría</th>
                                <th>Subcategoría</th>
                                <th>Ubicación</th>
                                <th>Proyecto</th>
                                <th>Estado</th>
                                <th>Acción</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($casos as $caso): ?>
                            <tr class="case-row" data-estado="<?php echo $caso['estado']; ?>" data-proyecto="<?php echo htmlspecialchars($caso['proyecto']); ?>">
                                <td><span class="case-id">#<?php echo $caso['id']; ?></span></td>
                                <td><span class="case-date"><?php echo $caso['fecha']; ?></span></td>
                                <?php if ($isAdminSistema): ?>
                                <td><?php echo htmlspecialchars($caso['nombre_solic']); ?></td>
                                <td><?php echo $caso['rol_solicitante'] === 'administrador_edificio' ? 'Admin. Edificio' : 'Propietario'; ?></td>
                                <?php endif; ?>
                                <td><?php echo $caso['categoria']; ?></td>
                                <td><?php echo $caso['subcategoria']; ?></td>
                                <td><?php echo $caso['ubicacion']; ?></td>
                                <td><?php echo htmlspecialchars($caso['proyecto']); ?></td>
                                <td>
                                    <?php
                                    $badgeClass = '';
                                    switch ($caso['estado']) {
                                        case 'pendiente': $badgeClass = 'badge-pending'; break;
                                        case 'aprobado': $badgeClass = 'badge-approved'; break;
                                        case 'resuelto': $badgeClass = 'badge-resolved'; break;
                                        case 'no_corresponde': $badgeClass = 'badge-rejected'; break;
                                    }
                                    ?>
                                    <span class="badge <?php echo $badgeClass; ?>"><?php echo $caso['estado_label']; ?></span>
                                </td>
                                <td class="case-action-cell">
                                    <a href="detalle-caso.php?id=<?php echo $caso['id_num']; ?>" class="btn btn-sm btn-outline">
                                        <i class="fas fa-eye"></i> Ver
                                    </a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                            <?php if (empty($casos)): ?>
                            <tr>
                                <td colspan="<?php echo $isAdminSistema ? '10' : '8'; ?>" style="text-align:center; padding: 40px; color: #888;">
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
        
    </div>
</div>

<script>
// Filtros de tabla
$(document).ready(function() {
    function aplicarFiltros() {
        var estado = $('#filterEstado').val();
        var proyecto = $('#filterProyecto').val();
        var search = $('#tableSearch').val().toLowerCase();

        $('.case-row').each(function() {
            var $row = $(this);
            var rowEstado = $row.data('estado');
            var rowProyecto = ($row.data('proyecto') || '').toLowerCase();
            var text = $row.text().toLowerCase();

            var coincideEstado = !estado || rowEstado === estado;
            var coincideProyecto = !proyecto || rowProyecto === proyecto.toLowerCase();
            var coincideBusqueda = !search || text.indexOf(search) > -1;

            $row.toggle(coincideEstado && coincideProyecto && coincideBusqueda);
        });
    }

    $('#filterEstado, #filterProyecto').on('change', aplicarFiltros);
    $('#tableSearch').on('keyup', aplicarFiltros);
});
</script>

<?php include 'includes/footer.php'; ?>
