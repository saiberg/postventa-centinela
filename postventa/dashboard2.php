<?php
/**
 * Dashboard 2 - Vista de gestión para administrador del sistema.
 * Esta vista es independiente de dashboard.php.
 */
require_once 'includes/config.php';
require_once 'includes/api_helper.php';

if (!isset($_SESSION['usuario_id']) || !isset($_SESSION['usuario_rol']) || $_SESSION['usuario_rol'] !== 'admin_sistema') {
    header('Location: login.php');
    exit;
}

$apiResponse = apiCall('solicitudes.php?action=todas', array());
$solicitudes = ($apiResponse['success'] && isset($apiResponse['solicitudes'])) ? $apiResponse['solicitudes'] : array();

$estados = array(
    'pendiente' => 'Pendiente',
    'aprobado' => 'Aprobado',
    'resuelto' => 'Resuelto',
    'no_corresponde' => 'No corresponde'
);
$categorias = array(
    'Fallas Estructurales/Estéticas' => 'Estructural',
    'Instalaciones (Gas/Agua/Luz)' => 'Instalaciones',
    'Terminaciones' => 'Terminaciones'
);

$estadoTotals = array_fill_keys(array_keys($estados), 0);
$porProyecto = array();
$pendientesPorProyecto = array();
$porCategoriaEstado = array();
$pendientes = array();
$estadosAbiertos = array('pendiente', 'aprobado');
$estadosCerrados = array('resuelto', 'no_corresponde');
$coloresEstado = array(
    'pendiente' => '#e39b32',
    'aprobado' => '#608418',
    'resuelto' => '#2e936f',
    'no_corresponde' => '#c95b6b'
);

foreach ($solicitudes as $solicitud) {
    $estado = isset($solicitud['estado']) && isset($estadoTotals[$solicitud['estado']]) ? $solicitud['estado'] : 'pendiente';
    $categoriaOriginal = isset($solicitud['categoria']) ? $solicitud['categoria'] : 'Sin categoría';
    $categoria = isset($categorias[$categoriaOriginal]) ? $categorias[$categoriaOriginal] : $categoriaOriginal;
    $proyecto = isset($solicitud['obra_nombre']) && trim($solicitud['obra_nombre']) !== '' ? trim($solicitud['obra_nombre']) : 'Sin proyecto';

    $estadoTotals[$estado]++;

    if (!isset($porProyecto[$proyecto])) {
        $porProyecto[$proyecto] = array('total' => 0, 'pendientes' => 0, 'en_gestion' => 0, 'resueltos' => 0, 'rechazados' => 0);
    }
    $porProyecto[$proyecto]['total']++;
    if ($estado === 'pendiente') $porProyecto[$proyecto]['pendientes']++;
    if ($estado === 'aprobado') $porProyecto[$proyecto]['en_gestion']++;
    if ($estado === 'resuelto') $porProyecto[$proyecto]['resueltos']++;
    if ($estado === 'no_corresponde') $porProyecto[$proyecto]['rechazados']++;

    if (!isset($pendientesPorProyecto[$proyecto])) {
        $pendientesPorProyecto[$proyecto] = array(
            'casos_pendientes' => 0,
            'dias_abiertos' => 0,
            'caso_antiguo_fecha' => null,
            'caso_antiguo_id' => null,
            'dias_respuesta_cerrados' => 0,
            'cerrados_con_fecha' => 0
        );
    }
    if ($estado === 'pendiente') {
        $pendientesPorProyecto[$proyecto]['casos_pendientes']++;
        $fechaCreacion = strtotime($solicitud['created_at']);
        $pendientesPorProyecto[$proyecto]['dias_abiertos'] += max(0, (time() - $fechaCreacion) / 86400);
        if ($pendientesPorProyecto[$proyecto]['caso_antiguo_fecha'] === null || $fechaCreacion < $pendientesPorProyecto[$proyecto]['caso_antiguo_fecha']) {
            $pendientesPorProyecto[$proyecto]['caso_antiguo_fecha'] = $fechaCreacion;
            $pendientesPorProyecto[$proyecto]['caso_antiguo_id'] = $solicitud['id'];
        }
    }
    if (in_array($estado, $estadosAbiertos) && $estado !== 'pendiente') {
        $pendientesPorProyecto[$proyecto]['dias_abiertos'] += max(0, (time() - strtotime($solicitud['created_at'])) / 86400);
    }
    if (in_array($estado, $estadosCerrados) && !empty($solicitud['updated_at'])) {
        $tiempoRespuesta = strtotime($solicitud['updated_at']) - strtotime($solicitud['created_at']);
        if ($tiempoRespuesta >= 0) {
            $pendientesPorProyecto[$proyecto]['dias_respuesta_cerrados'] += $tiempoRespuesta / 86400;
            $pendientesPorProyecto[$proyecto]['cerrados_con_fecha']++;
        }
    }

    if (!isset($porCategoriaEstado[$categoria])) {
        $porCategoriaEstado[$categoria] = array_fill_keys(array_keys($estados), 0);
    }
    $porCategoriaEstado[$categoria][$estado]++;

    // Un caso pendiente todavía no tiene una acción de la inmobiliaria.
    if ($estado === 'pendiente') {
        $pendientes[] = $solicitud;
    }
}

uasort($porProyecto, function($a, $b) {
    return $b['total'] - $a['total'];
});
usort($pendientes, function($a, $b) {
    return strtotime($a['created_at']) - strtotime($b['created_at']);
});
$pendientesAntiguos = array_slice($pendientes, 0, 10);

foreach ($pendientesPorProyecto as $proyecto => $indicadores) {
    $casosAbiertos = 0;
    foreach ($solicitudes as $solicitud) {
        $solicitudProyecto = isset($solicitud['obra_nombre']) && trim($solicitud['obra_nombre']) !== '' ? trim($solicitud['obra_nombre']) : 'Sin proyecto';
        if ($solicitudProyecto === $proyecto && in_array($solicitud['estado'], $estadosAbiertos)) {
            $casosAbiertos++;
        }
    }
    $pendientesPorProyecto[$proyecto]['promedio_abiertos'] = $casosAbiertos > 0 ? $indicadores['dias_abiertos'] / $casosAbiertos : null;
    $pendientesPorProyecto[$proyecto]['promedio_cerrados'] = $indicadores['cerrados_con_fecha'] > 0 ? $indicadores['dias_respuesta_cerrados'] / $indicadores['cerrados_con_fecha'] : null;
}
uasort($pendientesPorProyecto, function($a, $b) {
    return $b['casos_pendientes'] - $a['casos_pendientes'];
});

$totalSolicitudes = count($solicitudes);
$totalPendientes = $estadoTotals['pendiente'];
$totalEnGestion = $estadoTotals['aprobado'];
$totalResueltos = $estadoTotals['resuelto'];
$totalRechazados = $estadoTotals['no_corresponde'];

include 'includes/header.php';
?>

<style>
.dashboard2-page { background:linear-gradient(135deg,#f3f5f2 0%,#f8faf7 48%,#edf2ec 100%); min-height:100vh; }
.dashboard2-container { max-width:1240px; margin:0 auto; padding:34px 20px 48px; }
.dashboard2-header { display:flex; justify-content:space-between; align-items:flex-end; gap:20px; margin-bottom:26px; padding-bottom:22px; border-bottom:1px solid #dce5d9; }
.dashboard2-header h1 { margin:0; color:#26342b; font-family:var(--font-heading); font-size:1.8rem; letter-spacing:.01em; }
.dashboard2-header h1 i { color:#608418; margin-right:8px; }
.dashboard2-header p { margin:7px 0 0; color:#68746b; font-size:.9rem; }
.dashboard2-link { color:#608418; font-size:.82rem; font-weight:700; text-decoration:none; padding:9px 12px; border:1px solid #cbd9c4; border-radius:4px; background:rgba(255,255,255,.65); }
.dashboard2-link:hover { background:#fff; color:#4a6b10; }
.dashboard2-kpis { display:grid; grid-template-columns:repeat(5,1fr); gap:16px; margin-bottom:22px; }
.dashboard2-kpi { background:rgba(255,255,255,.92); border:1px solid #e1e7df; border-left:4px solid #608418; border-radius:6px; padding:18px 16px; box-shadow:0 3px 10px rgba(32,48,37,.055); transition:transform .18s ease,box-shadow .18s ease; }
.dashboard2-kpi:hover { transform:translateY(-2px); box-shadow:0 7px 16px rgba(32,48,37,.09); }
.dashboard2-kpi:nth-child(2) { border-left-color:#e39b32; }
.dashboard2-kpi:nth-child(3) { border-left-color:#3d86a8; }
.dashboard2-kpi:nth-child(4) { border-left-color:#2e936f; }
.dashboard2-kpi:nth-child(5) { border-left-color:#c95b6b; }
.dashboard2-kpi-content { display:flex; align-items:center; gap:12px; min-width:0; }
.dashboard2-kpi-icon { width:42px; height:42px; flex:0 0 42px; display:flex; align-items:center; justify-content:center; border-radius:50%; background:#e8f0dd; color:#608418; font-size:1.05rem; }
.dashboard2-kpi:nth-child(2) .dashboard2-kpi-icon { background:#fff0d5; color:#c27b16; }
.dashboard2-kpi:nth-child(3) .dashboard2-kpi-icon { background:#e1f0f5; color:#3d86a8; }
.dashboard2-kpi:nth-child(4) .dashboard2-kpi-icon { background:#dff2ea; color:#2e936f; }
.dashboard2-kpi:nth-child(5) .dashboard2-kpi-icon { background:#f8e1e5; color:#c95b6b; }
.dashboard2-kpi-label { color:#68746b; font-size:.7rem; text-transform:uppercase; font-weight:700; letter-spacing:.04em; line-height:1.25; }
.dashboard2-kpi-value { color:#26342b; font-size:1.8rem; font-weight:700; margin-top:5px; }
.dashboard2-grid { display:grid; grid-template-columns:1fr 1fr; gap:20px; margin-bottom:22px; }
.dashboard2-panel { background:rgba(255,255,255,.94); border:1px solid #e1e7df; border-radius:6px; box-shadow:0 3px 10px rgba(32,48,37,.055); overflow:hidden; }
.dashboard2-panel-wide { grid-column:1 / -1; }
.dashboard2-panel-header { padding:17px 20px 15px; border-bottom:1px solid #e8ece7; background:#fbfcfa; }
.dashboard2-panel-header h2 { margin:0; font-size:1rem; color:#26342b; font-family:var(--font-heading); font-weight:700; }
.dashboard2-panel-header p { margin:4px 0 0; color:#7b857d; font-size:.78rem; }
.dashboard2-chart { min-height:340px; height:auto; padding:18px 24px; position:relative; }
.dashboard2-chart canvas { max-height:100%; }
.dashboard2-chart-layout { display:grid; grid-template-columns:minmax(0,1fr) 220px; align-items:center; gap:20px; min-height:300px; height:auto; }
.dashboard2-chart-layout .dashboard2-chart { height:300px; min-height:0; padding:8px 0 8px 8px; }
.dashboard2-chart-side { min-width:0; border-left:1px solid #e8ece7; padding:4px 0 4px 18px; }
.dashboard2-chart-side-title { color:#7b857d; font-size:.68rem; text-transform:uppercase; letter-spacing:.05em; font-weight:700; margin-bottom:10px; }
.dashboard2-chart-stat { display:flex; align-items:center; gap:8px; padding:6px 0; border-bottom:1px solid #f0f2ef; }
.dashboard2-chart-stat:last-child { border-bottom:0; }
.dashboard2-chart-dot { width:9px; height:9px; flex:0 0 9px; border-radius:50%; }
.dashboard2-chart-stat-label { color:#536057; font-size:.72rem; line-height:1.25; flex:1; }
.dashboard2-chart-stat-value { color:#26342b; font-size:.78rem; font-weight:700; }
.dashboard2-chart-stat-percent { display:block; color:#89928b; font-size:.66rem; margin-top:2px; }
.dashboard2-category-summary { display:grid; gap:12px; }
.dashboard2-category-item { padding:9px 10px; background:#f7f9f6; border-left:3px solid #608418; }
.dashboard2-category-item:nth-child(2) { border-left-color:#3d86a8; }
.dashboard2-category-item:nth-child(3) { border-left-color:#c95b6b; }
.dashboard2-category-name { color:#344238; font-size:.72rem; font-weight:700; }
.dashboard2-category-total { color:#26342b; font-size:1.15rem; font-weight:700; margin-top:3px; }
.dashboard2-category-meta { color:#7b857d; font-size:.66rem; margin-top:3px; }
.dashboard2-table-wrap { overflow-x:auto; }
.dashboard2-table { width:100%; border-collapse:collapse; }
.dashboard2-table th, .dashboard2-table td { padding:12px 14px; border-bottom:1px solid #edf0ec; text-align:left; font-size:.8rem; white-space:nowrap; }
.dashboard2-table th { background:#f4f7f2; color:#5c685f; font-size:.69rem; text-transform:uppercase; letter-spacing:.035em; }
.dashboard2-table td { color:#344238; }
.dashboard2-table tbody tr:nth-child(even) { background:#fcfdfb; }
.dashboard2-table tbody tr:hover { background:#f1f6ed; }
.dashboard2-table tr:last-child td { border-bottom:0; }
.dashboard2-number { font-weight:700; color:#26342b; }
.dashboard2-badge { display:inline-block; padding:4px 8px; background:#fff0d5; color:#926018; font-size:.7rem; font-weight:700; border-radius:3px; }
.dashboard2-status { min-width:94px; text-align:center; }
.dashboard2-status-pendiente { background:#fff0d5; color:#926018; }
.dashboard2-status-aprobado { background:#dff2ea; color:#237454; }
.dashboard2-status-resuelto { background:#d9f1e3; color:#237044; }
.dashboard2-status-no_corresponde { background:#f8e1e5; color:#a13f50; }
.dashboard2-empty { padding:28px 20px; text-align:center; color:#7b857d; font-size:.85rem; }
.dashboard2-filters { display:flex; flex-wrap:wrap; gap:10px; padding:14px 20px; border-bottom:1px solid #e8ece7; }
.dashboard2-filters input, .dashboard2-filters select { min-width:180px; padding:8px 10px; border:1px solid #d8e0d7; border-radius:3px; color:#344238; font-size:.8rem; }
.dashboard2-project-select { min-width:200px; padding:8px 10px; border:1px solid #d8e0d7; border-radius:3px; color:#344238; font-size:.8rem; background:rgba(255,255,255,.95); }
.dashboard2-project-select:hover { background:#fff; }
.dashboard2-project-select:focus { outline:none; border-color:#608418; background:#fff; }
.dashboard2-requests-table tbody tr:hover { background:#edf5e9; }
.dashboard2-requests-table .dashboard2-action { color:#608418; font-weight:700; text-decoration:none; white-space:nowrap; }
@media (max-width:800px) { .dashboard2-kpis { grid-template-columns:1fr 1fr; } .dashboard2-grid { grid-template-columns:1fr; } .dashboard2-panel-wide { grid-column:auto; } .dashboard2-header { align-items:flex-start; flex-direction:column; } .dashboard2-header-filters { width:100%; margin-top:10px; } .dashboard2-header-filters select { width:100%; } }
@media (max-width:650px) { .dashboard2-chart { min-height:0; } .dashboard2-chart-layout { grid-template-columns:1fr; gap:14px; } .dashboard2-chart-layout .dashboard2-chart { height:260px; } .dashboard2-chart-side { border-left:0; border-top:1px solid #e8ece7; padding:14px 0 0; } .dashboard2-category-summary { grid-template-columns:1fr 1fr; } }
@media (max-width:520px) { .dashboard2-kpis { grid-template-columns:1fr; } .dashboard2-container { padding:20px 12px 30px; } }
</style>

<div class="dashboard2-page">
    <div class="dashboard2-container">
        <div class="dashboard2-header">
            <div>
                <h1><i class="fas fa-chart-line"></i> Dashboard de gestión</h1>
                <p>Indicadores operativos para apoyar la gestión de solicitudes de postventa.</p>
            </div>
            <div class="dashboard2-header-filters">
                <select id="dashboard2ProjectFilter" class="dashboard2-project-select">
                    <option value="">Todos los proyectos</option>
                    <?php foreach (array_keys($porProyecto) as $nombreProyecto): ?>
                        <option value="<?php echo htmlspecialchars($nombreProyecto); ?>"><?php echo htmlspecialchars($nombreProyecto); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>

        <div class="dashboard2-kpis">
            <div class="dashboard2-kpi"><div class="dashboard2-kpi-content"><div class="dashboard2-kpi-icon" aria-hidden="true"><i class="fas fa-clipboard-list"></i></div><div><div class="dashboard2-kpi-label">Solicitudes totales</div><div class="dashboard2-kpi-value"><?php echo $totalSolicitudes; ?></div></div></div></div>
            <div class="dashboard2-kpi"><div class="dashboard2-kpi-content"><div class="dashboard2-kpi-icon" aria-hidden="true"><i class="fas fa-clock"></i></div><div><div class="dashboard2-kpi-label">Sin acción</div><div class="dashboard2-kpi-value"><?php echo $totalPendientes; ?></div></div></div></div>
            <div class="dashboard2-kpi"><div class="dashboard2-kpi-content"><div class="dashboard2-kpi-icon" aria-hidden="true"><i class="fas fa-spinner"></i></div><div><div class="dashboard2-kpi-label">En gestión</div><div class="dashboard2-kpi-value"><?php echo $totalEnGestion; ?></div></div></div></div>
            <div class="dashboard2-kpi"><div class="dashboard2-kpi-content"><div class="dashboard2-kpi-icon" aria-hidden="true"><i class="fas fa-check-circle"></i></div><div><div class="dashboard2-kpi-label">Resueltas</div><div class="dashboard2-kpi-value"><?php echo $totalResueltos; ?></div></div></div></div>
            <div class="dashboard2-kpi"><div class="dashboard2-kpi-content"><div class="dashboard2-kpi-icon" aria-hidden="true"><i class="fas fa-times-circle"></i></div><div><div class="dashboard2-kpi-label">Rechazadas</div><div class="dashboard2-kpi-value"><?php echo $totalRechazados; ?></div></div></div></div>
        </div>

        <div class="dashboard2-grid">
            <section class="dashboard2-panel">
                <div class="dashboard2-panel-header"><h2>Estado general de solicitudes</h2><p>Distribución de todos los casos registrados</p></div>
                <div class="dashboard2-chart">
                    <div class="dashboard2-chart-layout">
                        <div class="dashboard2-chart"><canvas id="estadoGeneralChart"></canvas></div>
                        <div class="dashboard2-chart-side">
                            <div class="dashboard2-chart-side-title">Detalle por estado</div>
                            <?php foreach ($estados as $estadoClave => $estadoNombre):
                                $porcentajeEstado = $totalSolicitudes > 0 ? ($estadoTotals[$estadoClave] / $totalSolicitudes) * 100 : 0;
                            ?>
                            <div class="dashboard2-chart-stat">
                                <span class="dashboard2-chart-dot" style="background:<?php echo isset($coloresEstado[$estadoClave]) ? $coloresEstado[$estadoClave] : '#608418'; ?>;"></span>
                                <span class="dashboard2-chart-stat-label"><?php echo htmlspecialchars($estadoNombre); ?><span class="dashboard2-chart-stat-percent"><?php echo number_format($porcentajeEstado, 1, ',', '.'); ?>%</span></span>
                                <span class="dashboard2-chart-stat-value"><?php echo $estadoTotals[$estadoClave]; ?></span>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </section>
            <section class="dashboard2-panel">
                <div class="dashboard2-panel-header"><h2>Estado por tipo de falla</h2><p>Comparación según la categoría de solicitud</p></div>
                <div class="dashboard2-chart">
                    <div class="dashboard2-chart-layout">
                        <div class="dashboard2-chart"><canvas id="estadoCategoriaChart"></canvas></div>
                        <div class="dashboard2-chart-side">
                            <div class="dashboard2-chart-side-title">Resumen por categoría</div>
                            <div class="dashboard2-category-summary">
                            <?php foreach ($porCategoriaEstado as $categoriaNombre => $valoresCategoria):
                                $totalCategoria = array_sum($valoresCategoria);
                                $abiertosCategoria = $valoresCategoria['pendiente'] + $valoresCategoria['aprobado'];
                                $cerradosCategoria = $valoresCategoria['resuelto'] + $valoresCategoria['no_corresponde'];
                                $porcentajeCategoria = $totalSolicitudes > 0 ? ($totalCategoria / $totalSolicitudes) * 100 : 0;
                            ?>
                                <div class="dashboard2-category-item">
                                    <div class="dashboard2-category-name"><?php echo htmlspecialchars($categoriaNombre); ?></div>
                                    <div class="dashboard2-category-total"><?php echo $totalCategoria; ?> casos</div>
                                    <div class="dashboard2-category-meta"><?php echo number_format($porcentajeCategoria, 1, ',', '.'); ?>% del total · <?php echo $abiertosCategoria; ?> abiertos · <?php echo $cerradosCategoria; ?> cerrados</div>
                                </div>
                            <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <section class="dashboard2-panel dashboard2-panel-wide">
                <div class="dashboard2-panel-header"><h2>Resumen por proyecto</h2><p>Volumen, casos pendientes, en gestión, resueltos y rechazados</p></div>
                <div class="dashboard2-table-wrap">
                    <table class="dashboard2-table">
                        <thead><tr><th>Proyecto</th><th>Total</th><th>Sin acción</th><th>En gestión</th><th>Resueltas</th><th>Rechazadas</th></tr></thead>
                        <tbody>
                        <?php if (empty($porProyecto)): ?>
                            <tr><td colspan="6" class="dashboard2-empty">No hay datos de proyectos disponibles.</td></tr>
                        <?php else: foreach ($porProyecto as $proyecto => $indicadores): ?>
                            <tr><td><strong><?php echo htmlspecialchars($proyecto); ?></strong></td><td class="dashboard2-number"><?php echo $indicadores['total']; ?></td><td><?php echo $indicadores['pendientes']; ?></td><td><?php echo $indicadores['en_gestion']; ?></td><td><?php echo $indicadores['resueltos']; ?></td><td><?php echo $indicadores['rechazados']; ?></td></tr>
                        <?php endforeach; endif; ?>
                        </tbody>
                    </table>
                </div>
            </section>

            <section class="dashboard2-panel dashboard2-panel-wide">
                <div class="dashboard2-panel-header"><h2>Pendientes por proyecto</h2><p>Antigüedad de casos abiertos y tiempo promedio de respuesta de casos cerrados</p></div>
                <div class="dashboard2-table-wrap">
                    <table class="dashboard2-table">
                        <thead><tr><th>Proyecto</th><th>Casos pendientes</th><th>Tiempo promedio de casos abiertos</th><th>Caso pendiente más antiguo</th><th>Tiempo promedio de respuesta casos cerrados</th></tr></thead>
                        <tbody>
                        <?php if (empty($pendientesPorProyecto)): ?>
                            <tr><td colspan="5" class="dashboard2-empty">No hay datos de proyectos disponibles.</td></tr>
                        <?php else: foreach ($pendientesPorProyecto as $proyecto => $indicadores): ?>
                            <tr>
                                <td><strong><?php echo htmlspecialchars($proyecto); ?></strong></td>
                                <td class="dashboard2-number"><?php echo $indicadores['casos_pendientes']; ?></td>
                                <td><?php echo $indicadores['promedio_abiertos'] === null ? '—' : number_format($indicadores['promedio_abiertos'], 1, ',', '.') . ' días'; ?></td>
                                <td><?php echo $indicadores['caso_antiguo_fecha'] === null ? '—' : number_format(max(0, (time() - $indicadores['caso_antiguo_fecha']) / 86400), 1, ',', '.') . ' días (#' . (int)$indicadores['caso_antiguo_id'] . ')'; ?></td>
                                <td><?php echo $indicadores['promedio_cerrados'] === null ? '—' : number_format($indicadores['promedio_cerrados'], 1, ',', '.') . ' días'; ?></td>
                            </tr>
                        <?php endforeach; endif; ?>
                        </tbody>
                    </table>
                </div>
            </section>

            <section class="dashboard2-panel dashboard2-panel-wide">
                <div class="dashboard2-panel-header"><h2>Solicitudes más antiguas sin acción</h2><p>Casos pendientes ordenados desde el más antiguo. Se considera sin acción un caso en estado pendiente.</p></div>
                <div class="dashboard2-table-wrap">
                    <table class="dashboard2-table">
                        <thead><tr><th>N° caso</th><th>Fecha ingreso</th><th>Proyecto</th><th>Cliente</th><th>Categoría</th><th>Antigüedad</th></tr></thead>
                        <tbody>
                        <?php if (empty($pendientesAntiguos)): ?>
                            <tr><td colspan="6" class="dashboard2-empty">No hay solicitudes pendientes sin acción.</td></tr>
                        <?php else: foreach ($pendientesAntiguos as $pendiente):
                            $diasAntiguedad = max(0, (int)floor((time() - strtotime($pendiente['created_at'])) / 86400));
                            $nombreProyecto = isset($pendiente['obra_nombre']) && trim($pendiente['obra_nombre']) !== '' ? trim($pendiente['obra_nombre']) : 'Sin proyecto';
                        ?>
                            <tr class="dashboard2-antiguos-row" data-request-project="<?php echo htmlspecialchars($nombreProyecto); ?>"><td class="dashboard2-number">#<?php echo (int)$pendiente['id']; ?></td><td><?php echo date('d/m/Y', strtotime($pendiente['created_at'])); ?></td><td><?php echo htmlspecialchars($nombreProyecto); ?></td><td><?php echo htmlspecialchars(isset($pendiente['nombre']) ? $pendiente['nombre'] : '—'); ?></td><td><?php echo htmlspecialchars(isset($pendiente['categoria']) ? $pendiente['categoria'] : '—'); ?></td><td><span class="dashboard2-badge"><?php echo $diasAntiguedad; ?> días</span></td></tr>
                        <?php endforeach; endif; ?>
                        </tbody>
                    </table>
                </div>
            </section>

            <section class="dashboard2-panel dashboard2-panel-wide">
                <div class="dashboard2-panel-header"><h2>Todas las solicitudes</h2><p>Listado completo de solicitudes recibidas, con acceso al detalle de cada caso.</p></div>
                <div class="dashboard2-filters">
                    <input type="text" id="dashboard2RequestSearch" placeholder="Buscar por caso, cliente o categoría...">
                    <select id="dashboard2RequestStatus">
                        <option value="">Todos los estados</option>
                        <?php foreach ($estados as $estadoClave => $estadoNombre): ?>
                            <option value="<?php echo htmlspecialchars($estadoClave); ?>"><?php echo htmlspecialchars($estadoNombre); ?></option>
                        <?php endforeach; ?>
                    </select>
                    <select id="dashboard2RequestProject">
                        <option value="">Todos los proyectos</option>
                        <?php foreach (array_keys($porProyecto) as $nombreProyecto): ?>
                            <option value="<?php echo htmlspecialchars($nombreProyecto); ?>"><?php echo htmlspecialchars($nombreProyecto); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="dashboard2-table-wrap">
                    <table class="dashboard2-table dashboard2-requests-table">
                        <thead><tr><th>N° caso</th><th>Fecha</th><th>Solicitante</th><th>Rol</th><th>Categoría</th><th>Subcategoría</th><th>Ubicación</th><th>Proyecto</th><th>Estado</th><th>Acción</th></tr></thead>
                        <tbody>
                        <?php foreach ($solicitudes as $solicitud):
                            $estadoSolicitud = isset($solicitud['estado']) ? $solicitud['estado'] : '';
                            $estadoNombre = isset($estados[$estadoSolicitud]) ? $estados[$estadoSolicitud] : $estadoSolicitud;
                            $proyectoSolicitud = isset($solicitud['obra_nombre']) && trim($solicitud['obra_nombre']) !== '' ? trim($solicitud['obra_nombre']) : 'Sin proyecto';
                            $rolSolicitud = isset($solicitud['rol_solicitante']) && $solicitud['rol_solicitante'] === 'administrador_edificio' ? 'Administrador' : 'Propietario';
                        ?>
                            <tr class="dashboard2-request-row" data-request-status="<?php echo htmlspecialchars($estadoSolicitud); ?>" data-request-project="<?php echo htmlspecialchars($proyectoSolicitud); ?>">
                                <td class="dashboard2-number">#<?php echo (int)$solicitud['id']; ?></td>
                                <td><?php echo date('d/m/Y', strtotime($solicitud['created_at'])); ?></td>
                                <td><?php echo htmlspecialchars(isset($solicitud['nombre']) ? $solicitud['nombre'] : '—'); ?></td>
                                <td><?php echo htmlspecialchars($rolSolicitud); ?></td>
                                <td><?php echo htmlspecialchars(isset($solicitud['categoria']) ? $solicitud['categoria'] : '—'); ?></td>
                                <td><?php echo htmlspecialchars(isset($solicitud['subcategoria']) ? $solicitud['subcategoria'] : '—'); ?></td>
                                <td><?php echo htmlspecialchars(isset($solicitud['ubicacion_valor']) ? $solicitud['ubicacion_valor'] : '—'); ?></td>
                                <td><?php echo htmlspecialchars($proyectoSolicitud); ?></td>
                                <td><span class="dashboard2-badge dashboard2-status dashboard2-status-<?php echo htmlspecialchars($estadoSolicitud); ?>"><?php echo htmlspecialchars($estadoNombre); ?></span></td>
                                <td><a class="dashboard2-action" href="detalle-caso.php?id=<?php echo (int)$solicitud['id']; ?>"><i class="fas fa-eye"></i> Ver</a></td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (empty($solicitudes)): ?>
                            <tr><td colspan="10" class="dashboard2-empty">No hay solicitudes registradas.</td></tr>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </section>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@3.9.1/dist/chart.min.js"></script>
<script>
(function() {
    var estadoLabels = <?php echo json_encode(array_values($estados)); ?>;
    var estadoData = <?php echo json_encode(array_values($estadoTotals)); ?>;
    var categoriaLabels = <?php echo json_encode(array_keys($porCategoriaEstado)); ?>;
    var categoriaData = <?php echo json_encode(array_values($porCategoriaEstado)); ?>;
    var estadoKeys = <?php echo json_encode(array_keys($estados)); ?>;
    var colores = ['#e39b32', '#608418', '#2e936f', '#c95b6b'];

    new Chart(document.getElementById('estadoGeneralChart'), {
        type: 'doughnut',
        data: { labels: estadoLabels, datasets: [{ data: estadoData, backgroundColor: colores, borderWidth: 2, borderColor: '#fff' }] },
        options: { responsive:true, maintainAspectRatio:false, plugins:{ legend:{ display:false } } }
    });

    var datasets = estadoKeys.map(function(key, index) {
        return { label: estadoLabels[index], data: categoriaData.map(function(item) { return item[key] || 0; }), backgroundColor: colores[index], borderRadius: 2 };
    });
    new Chart(document.getElementById('estadoCategoriaChart'), {
        type: 'bar',
        data: { labels: categoriaLabels, datasets: datasets },
        options: { responsive:true, maintainAspectRatio:false, scales:{ x:{ stacked:false, ticks:{ font:{ size:10 } } }, y:{ beginAtZero:true, ticks:{ precision:0 } } }, plugins:{ legend:{ display:false } } }
    });

    function filtrarSolicitudesDashboard2() {
        var texto = document.getElementById('dashboard2RequestSearch').value.toLowerCase();
        var estado = document.getElementById('dashboard2RequestStatus').value;
        var proyecto = document.getElementById('dashboard2RequestProject').value;
        document.querySelectorAll('.dashboard2-request-row').forEach(function(row) {
            var coincideTexto = !texto || row.textContent.toLowerCase().indexOf(texto) !== -1;
            var coincideEstado = !estado || row.getAttribute('data-request-status') === estado;
            var coincideProyecto = !proyecto || row.getAttribute('data-request-project') === proyecto;
            row.style.display = coincideTexto && coincideEstado && coincideProyecto ? '' : 'none';
        });
    }
    document.getElementById('dashboard2RequestSearch').addEventListener('input', filtrarSolicitudesDashboard2);
    document.getElementById('dashboard2RequestStatus').addEventListener('change', filtrarSolicitudesDashboard2);
    
    function filtrarPorProyecto(proyectoSeleccionado) {
        var filasSolicitudes = document.querySelectorAll('.dashboard2-request-row');
        var kpiTotal = document.querySelector('.dashboard2-kpi:nth-child(1) .dashboard2-kpi-value');
        var kpiPendientes = document.querySelector('.dashboard2-kpi:nth-child(2) .dashboard2-kpi-value');
        var kpiAprobados = document.querySelector('.dashboard2-kpi:nth-child(3) .dashboard2-kpi-value');
        var kpiResueltos = document.querySelector('.dashboard2-kpi:nth-child(4) .dashboard2-kpi-value');
        var kpiNoCorresponde = document.querySelector('.dashboard2-kpi:nth-child(5) .dashboard2-kpi-value');

        var total = 0;
        var totalPendientes = 0;
        var totalAprobados = 0;
        var totalResueltos = 0;
        var totalNoCorresponde = 0;

        filasSolicitudes.forEach(function(fila) {
            var proyectoFila = fila.getAttribute('data-request-project');
            var coincide = !proyectoSeleccionado || proyectoFila === proyectoSeleccionado;
            if (!coincide) return;

            total++;
            var estadoFila = fila.getAttribute('data-request-status');
            if (estadoFila === 'pendiente') totalPendientes++;
            if (estadoFila === 'aprobado') totalAprobados++;
            if (estadoFila === 'resuelto') totalResueltos++;
            if (estadoFila === 'no_corresponde') totalNoCorresponde++;
        });

        document.querySelectorAll('.dashboard2-antiguos-row').forEach(function(fila) {
            var proyectoFila = fila.getAttribute('data-request-project');
            var coincide = !proyectoSeleccionado || proyectoFila === proyectoSeleccionado;
            fila.style.display = coincide ? '' : 'none';
        });

        kpiTotal.textContent = total;
        kpiPendientes.textContent = totalPendientes;
        kpiAprobados.textContent = totalAprobados;
        kpiResueltos.textContent = totalResueltos;
        kpiNoCorresponde.textContent = totalNoCorresponde;
    }

    document.getElementById('dashboard2ProjectFilter').addEventListener('change', function() {
        var proyecto = this.value;
        document.getElementById('dashboard2RequestProject').value = proyecto;
        filtrarPorProyecto(proyecto);
        filtrarSolicitudesDashboard2();
    });
    document.getElementById('dashboard2RequestProject').addEventListener('change', function() {
        document.getElementById('dashboard2ProjectFilter').value = this.value;
        filtrarPorProyecto(this.value);
        filtrarSolicitudesDashboard2();
    });
})();
</script>

<?php include 'includes/footer.php'; ?>
