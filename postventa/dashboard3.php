<?php
/**
 * Dashboard 3 - Vista administrativa con gráficos ApexCharts.
 * Independiente de dashboard.php y dashboard2.php.
 */
require_once 'includes/config.php';
require_once 'includes/api_helper.php';

if (!isset($_SESSION['usuario_id']) || !isset($_SESSION['usuario_rol']) || $_SESSION['usuario_rol'] !== 'admin_sistema') {
    header('Location: login.php');
    exit;
}

$apiResponse = apiCall('solicitudes.php?action=todas', array());
$solicitudes = ($apiResponse['success'] && isset($apiResponse['solicitudes'])) ? $apiResponse['solicitudes'] : array();
$estados = array('pendiente' => 'Pendiente', 'aprobado' => 'Aprobado', 'resuelto' => 'Resuelto', 'no_corresponde' => 'No corresponde');
$categorias = array('Fallas Estructurales/Estéticas' => 'Estructural', 'Instalaciones (Gas/Agua/Luz)' => 'Instalaciones', 'Terminaciones' => 'Terminaciones');
$estadoTotals = array_fill_keys(array_keys($estados), 0);
$porProyecto = array();
$porCategoriaEstado = array();
$pendientes = array();
$pendientesPorProyecto = array();
$estadosAbiertos = array('pendiente', 'aprobado');
$estadosCerrados = array('resuelto', 'no_corresponde');
$coloresEstado = array('pendiente' => '#e39b32', 'aprobado' => '#608418', 'resuelto' => '#2e936f', 'no_corresponde' => '#c95b6b');

foreach ($solicitudes as $solicitud) {
    $estado = isset($estadoTotals[$solicitud['estado']]) ? $solicitud['estado'] : 'pendiente';
    $categoriaOriginal = isset($solicitud['categoria']) ? $solicitud['categoria'] : 'Sin categoría';
    $categoria = isset($categorias[$categoriaOriginal]) ? $categorias[$categoriaOriginal] : $categoriaOriginal;
    $proyecto = isset($solicitud['obra_nombre']) && trim($solicitud['obra_nombre']) !== '' ? trim($solicitud['obra_nombre']) : 'Sin proyecto';
    $estadoTotals[$estado]++;

    if (!isset($porProyecto[$proyecto])) {
        $porProyecto[$proyecto] = array('total' => 0, 'pendientes' => 0, 'en_gestion' => 0, 'resueltos' => 0, 'rechazados' => 0);
        $pendientesPorProyecto[$proyecto] = array('casos' => 0, 'dias_abiertos' => 0, 'antiguo' => null, 'antiguo_id' => null, 'dias_cerrados' => 0, 'cerrados' => 0);
    }
    $porProyecto[$proyecto]['total']++;
    if ($estado === 'pendiente') $porProyecto[$proyecto]['pendientes']++;
    if ($estado === 'aprobado') $porProyecto[$proyecto]['en_gestion']++;
    if ($estado === 'resuelto') $porProyecto[$proyecto]['resueltos']++;
    if ($estado === 'no_corresponde') $porProyecto[$proyecto]['rechazados']++;

    if (!isset($porCategoriaEstado[$categoria])) $porCategoriaEstado[$categoria] = array_fill_keys(array_keys($estados), 0);
    $porCategoriaEstado[$categoria][$estado]++;

    if ($estado === 'pendiente') {
        $pendientes[] = $solicitud;
        $fecha = strtotime($solicitud['created_at']);
        $pendientesPorProyecto[$proyecto]['casos']++;
        $pendientesPorProyecto[$proyecto]['dias_abiertos'] += max(0, (time() - $fecha) / 86400);
        if ($pendientesPorProyecto[$proyecto]['antiguo'] === null || $fecha < $pendientesPorProyecto[$proyecto]['antiguo']) {
            $pendientesPorProyecto[$proyecto]['antiguo'] = $fecha;
            $pendientesPorProyecto[$proyecto]['antiguo_id'] = $solicitud['id'];
        }
    }
    if ($estado === 'aprobado') $pendientesPorProyecto[$proyecto]['dias_abiertos'] += max(0, (time() - strtotime($solicitud['created_at'])) / 86400);
    if (in_array($estado, $estadosCerrados) && !empty($solicitud['updated_at'])) {
        $duracion = strtotime($solicitud['updated_at']) - strtotime($solicitud['created_at']);
        if ($duracion >= 0) { $pendientesPorProyecto[$proyecto]['dias_cerrados'] += $duracion / 86400; $pendientesPorProyecto[$proyecto]['cerrados']++; }
    }
}

uasort($porProyecto, function($a, $b) { return $b['total'] - $a['total']; });
usort($pendientes, function($a, $b) { return strtotime($a['created_at']) - strtotime($b['created_at']); });
$pendientesAntiguos = array_slice($pendientes, 0, 10);
foreach ($pendientesPorProyecto as $proyecto => $datos) {
    $abiertos = 0;
    foreach ($solicitudes as $solicitud) {
        $nombre = isset($solicitud['obra_nombre']) && trim($solicitud['obra_nombre']) !== '' ? trim($solicitud['obra_nombre']) : 'Sin proyecto';
        if ($nombre === $proyecto && in_array($solicitud['estado'], $estadosAbiertos)) $abiertos++;
    }
    $pendientesPorProyecto[$proyecto]['promedio_abiertos'] = $abiertos ? $datos['dias_abiertos'] / $abiertos : null;
    $pendientesPorProyecto[$proyecto]['promedio_cerrados'] = $datos['cerrados'] ? $datos['dias_cerrados'] / $datos['cerrados'] : null;
}
uasort($pendientesPorProyecto, function($a, $b) { return $b['casos'] - $a['casos']; });
$totalSolicitudes = count($solicitudes);
$totalPendientes = $estadoTotals['pendiente'];
$totalEnGestion = $estadoTotals['aprobado'];
$totalResueltos = $estadoTotals['resuelto'];
$totalRechazados = $estadoTotals['no_corresponde'];
include 'includes/header.php';
?>

<style>
.dashboard3-page{background:linear-gradient(135deg,#f3f5f2,#f8faf7 52%,#edf2ec);min-height:100vh}.dashboard3-container{max-width:1240px;margin:auto;padding:34px 20px 48px}.dashboard3-header{display:flex;justify-content:space-between;align-items:flex-end;border-bottom:1px solid #dce5d9;padding-bottom:22px;margin-bottom:26px}.dashboard3-header h1{margin:0;color:#26342b;font-family:var(--font-heading);font-size:1.8rem}.dashboard3-header h1 i{color:#608418;margin-right:8px}.dashboard3-header p{margin:7px 0 0;color:#68746b;font-size:.9rem}.dashboard3-kpis{display:grid;grid-template-columns:repeat(5,1fr);gap:16px;margin-bottom:22px}.dashboard3-kpi{background:rgba(255,255,255,.94);border:1px solid #e1e7df;border-left:4px solid #608418;border-radius:6px;padding:18px 16px;box-shadow:0 3px 10px rgba(32,48,37,.055)}.dashboard3-kpi:nth-child(2){border-left-color:#e39b32}.dashboard3-kpi:nth-child(3){border-left-color:#3d86a8}.dashboard3-kpi:nth-child(4){border-left-color:#2e936f}.dashboard3-kpi:nth-child(5){border-left-color:#c95b6b}.dashboard3-kpi-content{display:flex;align-items:center;gap:12px}.dashboard3-kpi-icon{width:42px;height:42px;flex:0 0 42px;display:flex;align-items:center;justify-content:center;border-radius:50%;background:#e8f0dd;color:#608418}.dashboard3-kpi:nth-child(2) .dashboard3-kpi-icon{background:#fff0d5;color:#c27b16}.dashboard3-kpi:nth-child(3) .dashboard3-kpi-icon{background:#e1f0f5;color:#3d86a8}.dashboard3-kpi:nth-child(4) .dashboard3-kpi-icon{background:#dff2ea;color:#2e936f}.dashboard3-kpi:nth-child(5) .dashboard3-kpi-icon{background:#f8e1e5;color:#c95b6b}.dashboard3-kpi-label{color:#68746b;font-size:.7rem;text-transform:uppercase;font-weight:700;letter-spacing:.04em}.dashboard3-kpi-value{color:#26342b;font-size:1.8rem;font-weight:700;margin-top:5px}.dashboard3-grid{display:grid;grid-template-columns:1fr 1fr;gap:20px}.dashboard3-panel{background:rgba(255,255,255,.95);border:1px solid #e1e7df;border-radius:6px;box-shadow:0 3px 10px rgba(32,48,37,.055);overflow:hidden}.dashboard3-wide{grid-column:1/-1}.dashboard3-panel-header{padding:17px 20px 15px;border-bottom:1px solid #e8ece7;background:#fbfcfa}.dashboard3-panel-header h2{margin:0;font-size:1rem;color:#26342b;font-family:var(--font-heading)}.dashboard3-panel-header p{margin:4px 0 0;color:#7b857d;font-size:.78rem}.dashboard3-chart-area{min-height:360px;padding:18px 22px}.dashboard3-chart-layout{display:grid;grid-template-columns:minmax(0,1fr) 220px;align-items:center;gap:20px;min-height:320px}.dashboard3-chart{height:310px}.dashboard3-chart-side{border-left:1px solid #e8ece7;padding:4px 0 4px 18px}.dashboard3-side-title{color:#7b857d;font-size:.68rem;text-transform:uppercase;letter-spacing:.05em;font-weight:700;margin-bottom:10px}.dashboard3-stat{display:flex;align-items:center;gap:8px;padding:7px 0;border-bottom:1px solid #f0f2ef}.dashboard3-dot{width:9px;height:9px;border-radius:50%;flex:0 0 9px}.dashboard3-stat-label{color:#536057;font-size:.72rem;line-height:1.25;flex:1}.dashboard3-stat-percent{display:block;color:#89928b;font-size:.66rem;margin-top:2px}.dashboard3-stat-value{color:#26342b;font-size:.78rem;font-weight:700}.dashboard3-category-summary{display:grid;gap:12px}.dashboard3-category-item{padding:10px;background:#f7f9f6;border-left:3px solid #608418}.dashboard3-category-item:nth-child(2){border-left-color:#3d86a8}.dashboard3-category-item:nth-child(3){border-left-color:#c95b6b}.dashboard3-category-name{color:#344238;font-size:.72rem;font-weight:700}.dashboard3-category-total{color:#26342b;font-size:1.15rem;font-weight:700;margin-top:3px}.dashboard3-category-meta{color:#7b857d;font-size:.66rem;margin-top:3px}.dashboard3-table-wrap{overflow-x:auto}.dashboard3-table{width:100%;border-collapse:collapse}.dashboard3-table th,.dashboard3-table td{padding:12px 14px;border-bottom:1px solid #edf0ec;text-align:left;font-size:.8rem;white-space:nowrap}.dashboard3-table th{background:#f4f7f2;color:#5c685f;font-size:.69rem;text-transform:uppercase;letter-spacing:.035em}.dashboard3-table tbody tr:nth-child(even){background:#fcfdfb}.dashboard3-table tbody tr:hover{background:#edf5e9}.dashboard3-number{font-weight:700;color:#26342b}.dashboard3-badge{display:inline-block;padding:4px 8px;background:#fff0d5;color:#926018;font-size:.7rem;font-weight:700;border-radius:3px}.dashboard3-status-pendiente{background:#fff0d5;color:#926018}.dashboard3-status-aprobado{background:#dff2ea;color:#237454}.dashboard3-status-resuelto{background:#d9f1e3;color:#237044}.dashboard3-status-no_corresponde{background:#f8e1e5;color:#a13f50}.dashboard3-filters{display:flex;flex-wrap:wrap;gap:10px;padding:14px 20px;border-bottom:1px solid #e8ece7}.dashboard3-filters input,.dashboard3-filters select{min-width:180px;padding:8px 10px;border:1px solid #d8e0d7;border-radius:3px;color:#344238;font-size:.8rem}.dashboard3-action{color:#608418;font-weight:700;text-decoration:none;white-space:nowrap}@media(max-width:900px){.dashboard3-kpis{grid-template-columns:repeat(3,1fr)}.dashboard3-chart-layout{grid-template-columns:1fr}.dashboard3-chart-side{border-left:0;border-top:1px solid #e8ece7;padding:14px 0 0}}@media(max-width:650px){.dashboard3-kpis{grid-template-columns:1fr 1fr}.dashboard3-grid{grid-template-columns:1fr}.dashboard3-wide{grid-column:auto}.dashboard3-chart-area{padding:14px}.dashboard3-chart-side{padding-top:14px}}@media(max-width:480px){.dashboard3-kpis{grid-template-columns:1fr}.dashboard3-container{padding:22px 12px 36px}}
</style>
<style>
.dashboard3-chart { background:linear-gradient(145deg,#ffffff 0%,#f4f8f1 100%); box-shadow:inset 0 1px 0 rgba(255,255,255,.95),0 9px 20px rgba(38,52,43,.08); }
.dashboard3-chart .apexcharts-canvas { filter:drop-shadow(0 8px 7px rgba(38,52,43,.12)); }
.dashboard3-chart .apexcharts-svg { overflow:visible; }
.dashboard3-chart .apexcharts-series path { filter:drop-shadow(0 3px 2px rgba(38,52,43,.16)); }
.dashboard3-chart .apexcharts-datalabel { font-weight:700; text-shadow:0 1px 1px rgba(255,255,255,.75); }
.dashboard3-chart .apexcharts-tooltip { border:0!important; box-shadow:0 10px 24px rgba(38,52,43,.18)!important; border-radius:6px!important; }
</style>

<div class="dashboard3-page"><div class="dashboard3-container">
    <div class="dashboard3-header"><div><h1><i class="fas fa-chart-line"></i> Dashboard de gestión</h1><p>Indicadores operativos para apoyar la gestión de solicitudes de postventa.</p></div></div>
    <div class="dashboard3-kpis">
        <div class="dashboard3-kpi"><div class="dashboard3-kpi-content"><div class="dashboard3-kpi-icon"><i class="fas fa-clipboard-list"></i></div><div><div class="dashboard3-kpi-label">Solicitudes totales</div><div class="dashboard3-kpi-value"><?php echo $totalSolicitudes; ?></div></div></div></div>
        <div class="dashboard3-kpi"><div class="dashboard3-kpi-content"><div class="dashboard3-kpi-icon"><i class="fas fa-clock"></i></div><div><div class="dashboard3-kpi-label">Sin acción</div><div class="dashboard3-kpi-value"><?php echo $totalPendientes; ?></div></div></div></div>
        <div class="dashboard3-kpi"><div class="dashboard3-kpi-content"><div class="dashboard3-kpi-icon"><i class="fas fa-spinner"></i></div><div><div class="dashboard3-kpi-label">En gestión</div><div class="dashboard3-kpi-value"><?php echo $totalEnGestion; ?></div></div></div></div>
        <div class="dashboard3-kpi"><div class="dashboard3-kpi-content"><div class="dashboard3-kpi-icon"><i class="fas fa-check-circle"></i></div><div><div class="dashboard3-kpi-label">Resueltas</div><div class="dashboard3-kpi-value"><?php echo $totalResueltos; ?></div></div></div></div>
        <div class="dashboard3-kpi"><div class="dashboard3-kpi-content"><div class="dashboard3-kpi-icon"><i class="fas fa-times-circle"></i></div><div><div class="dashboard3-kpi-label">Rechazadas</div><div class="dashboard3-kpi-value"><?php echo $totalRechazados; ?></div></div></div></div>
    </div>
    <div class="dashboard3-grid">
        <section class="dashboard3-panel"><div class="dashboard3-panel-header"><h2>Estado general de solicitudes</h2><p>Distribución total con porcentaje y cantidad por estado</p></div><div class="dashboard3-chart-area"><div class="dashboard3-chart-layout"><div id="estadoGeneralChart" class="dashboard3-chart"></div><div class="dashboard3-chart-side"><div class="dashboard3-side-title">Detalle por estado</div><?php foreach ($estados as $clave => $nombre): $porcentaje = $totalSolicitudes ? ($estadoTotals[$clave] / $totalSolicitudes) * 100 : 0; ?><div class="dashboard3-stat"><span class="dashboard3-dot" style="background:<?php echo $coloresEstado[$clave]; ?>"></span><span class="dashboard3-stat-label"><?php echo htmlspecialchars($nombre); ?><span class="dashboard3-stat-percent"><?php echo number_format($porcentaje, 1, ',', '.'); ?>%</span></span><span class="dashboard3-stat-value"><?php echo $estadoTotals[$clave]; ?></span></div><?php endforeach; ?></div></div></div></section>
        <section class="dashboard3-panel"><div class="dashboard3-panel-header"><h2>Estado por tipo de falla</h2><p>Casos agrupados por categoría y estado</p></div><div class="dashboard3-chart-area"><div class="dashboard3-chart-layout"><div id="estadoCategoriaChart" class="dashboard3-chart"></div><div class="dashboard3-chart-side"><div class="dashboard3-side-title">Resumen por categoría</div><div class="dashboard3-category-summary"><?php foreach ($porCategoriaEstado as $nombre => $valores): $total = array_sum($valores); $abiertos = $valores['pendiente'] + $valores['aprobado']; $cerrados = $valores['resuelto'] + $valores['no_corresponde']; $porcentaje = $totalSolicitudes ? ($total / $totalSolicitudes) * 100 : 0; ?><div class="dashboard3-category-item"><div class="dashboard3-category-name"><?php echo htmlspecialchars($nombre); ?></div><div class="dashboard3-category-total"><?php echo $total; ?> casos</div><div class="dashboard3-category-meta"><?php echo number_format($porcentaje, 1, ',', '.'); ?>% del total · <?php echo $abiertos; ?> abiertos · <?php echo $cerrados; ?> cerrados</div></div><?php endforeach; ?></div></div></div></div></section>

        <section class="dashboard3-panel dashboard3-wide"><div class="dashboard3-panel-header"><h2>Resumen por proyecto</h2><p>Volumen, gestión, resolución y rechazo por proyecto</p></div><div class="dashboard3-table-wrap"><table class="dashboard3-table"><thead><tr><th>Proyecto</th><th>Total</th><th>Sin acción</th><th>En gestión</th><th>Resueltas</th><th>Rechazadas</th></tr></thead><tbody><?php foreach ($porProyecto as $proyecto => $datos): ?><tr><td><strong><?php echo htmlspecialchars($proyecto); ?></strong></td><td class="dashboard3-number"><?php echo $datos['total']; ?></td><td><?php echo $datos['pendientes']; ?></td><td><?php echo $datos['en_gestion']; ?></td><td><?php echo $datos['resueltos']; ?></td><td><?php echo $datos['rechazados']; ?></td></tr><?php endforeach; ?></tbody></table></div></section>
        <section class="dashboard3-panel dashboard3-wide"><div class="dashboard3-panel-header"><h2>Pendientes por proyecto</h2><p>Antigüedad de casos abiertos y respuesta promedio de casos cerrados</p></div><div class="dashboard3-table-wrap"><table class="dashboard3-table"><thead><tr><th>Proyecto</th><th>Casos pendientes</th><th>Tiempo promedio de casos abiertos</th><th>Caso pendiente más antiguo</th><th>Tiempo promedio de respuesta casos cerrados</th></tr></thead><tbody><?php foreach ($pendientesPorProyecto as $proyecto => $datos): ?><tr><td><strong><?php echo htmlspecialchars($proyecto); ?></strong></td><td class="dashboard3-number"><?php echo $datos['casos']; ?></td><td><?php echo $datos['promedio_abiertos'] === null ? '—' : number_format($datos['promedio_abiertos'], 1, ',', '.') . ' días'; ?></td><td><?php echo $datos['antiguo'] === null ? '—' : number_format((time() - $datos['antiguo']) / 86400, 1, ',', '.') . ' días (#' . (int)$datos['antiguo_id'] . ')'; ?></td><td><?php echo $datos['promedio_cerrados'] === null ? '—' : number_format($datos['promedio_cerrados'], 1, ',', '.') . ' días'; ?></td></tr><?php endforeach; ?></tbody></table></div></section>
        <section class="dashboard3-panel dashboard3-wide"><div class="dashboard3-panel-header"><h2>Solicitudes más antiguas sin acción</h2><p>Casos pendientes ordenados desde el más antiguo</p></div><div class="dashboard3-table-wrap"><table class="dashboard3-table"><thead><tr><th>N° caso</th><th>Fecha ingreso</th><th>Proyecto</th><th>Cliente</th><th>Categoría</th><th>Antigüedad</th></tr></thead><tbody><?php foreach ($pendientesAntiguos as $caso): $proyecto = isset($caso['obra_nombre']) && trim($caso['obra_nombre']) !== '' ? trim($caso['obra_nombre']) : 'Sin proyecto'; ?><tr><td class="dashboard3-number">#<?php echo (int)$caso['id']; ?></td><td><?php echo date('d/m/Y', strtotime($caso['created_at'])); ?></td><td><?php echo htmlspecialchars($proyecto); ?></td><td><?php echo htmlspecialchars(isset($caso['nombre']) ? $caso['nombre'] : '—'); ?></td><td><?php echo htmlspecialchars(isset($caso['categoria']) ? $caso['categoria'] : '—'); ?></td><td><span class="dashboard3-badge"><?php echo (int)floor((time() - strtotime($caso['created_at'])) / 86400); ?> días</span></td></tr><?php endforeach; ?></tbody></table></div></section>
        <section class="dashboard3-panel dashboard3-wide"><div class="dashboard3-panel-header"><h2>Todas las solicitudes</h2><p>Listado completo con búsqueda y filtros</p></div><div class="dashboard3-filters"><input id="dashboard3Search" type="text" placeholder="Buscar por caso, cliente o categoría..."><select id="dashboard3Status"><option value="">Todos los estados</option><?php foreach ($estados as $clave => $nombre): ?><option value="<?php echo htmlspecialchars($clave); ?>"><?php echo htmlspecialchars($nombre); ?></option><?php endforeach; ?></select><select id="dashboard3Project"><option value="">Todos los proyectos</option><?php foreach (array_keys($porProyecto) as $proyecto): ?><option value="<?php echo htmlspecialchars($proyecto); ?>"><?php echo htmlspecialchars($proyecto); ?></option><?php endforeach; ?></select></div><div class="dashboard3-table-wrap"><table class="dashboard3-table"><thead><tr><th>N° caso</th><th>Fecha</th><th>Solicitante</th><th>Rol</th><th>Categoría</th><th>Subcategoría</th><th>Ubicación</th><th>Proyecto</th><th>Estado</th><th>Acción</th></tr></thead><tbody><?php foreach ($solicitudes as $caso): $estado = isset($estados[$caso['estado']]) ? $caso['estado'] : 'pendiente'; $proyecto = isset($caso['obra_nombre']) && trim($caso['obra_nombre']) !== '' ? trim($caso['obra_nombre']) : 'Sin proyecto'; $rol = isset($caso['rol_solicitante']) && $caso['rol_solicitante'] === 'administrador_edificio' ? 'Administrador' : 'Propietario'; ?><tr class="dashboard3-request-row" data-status="<?php echo htmlspecialchars($estado); ?>" data-project="<?php echo htmlspecialchars($proyecto); ?>"><td class="dashboard3-number">#<?php echo (int)$caso['id']; ?></td><td><?php echo date('d/m/Y', strtotime($caso['created_at'])); ?></td><td><?php echo htmlspecialchars(isset($caso['nombre']) ? $caso['nombre'] : '—'); ?></td><td><?php echo htmlspecialchars($rol); ?></td><td><?php echo htmlspecialchars(isset($caso['categoria']) ? $caso['categoria'] : '—'); ?></td><td><?php echo htmlspecialchars(isset($caso['subcategoria']) ? $caso['subcategoria'] : '—'); ?></td><td><?php echo htmlspecialchars(isset($caso['ubicacion_valor']) ? $caso['ubicacion_valor'] : '—'); ?></td><td><?php echo htmlspecialchars($proyecto); ?></td><td><span class="dashboard3-badge dashboard3-status-<?php echo htmlspecialchars($estado); ?>"><?php echo htmlspecialchars($estados[$estado]); ?></span></td><td><a class="dashboard3-action" href="detalle-caso.php?id=<?php echo (int)$caso['id']; ?>"><i class="fas fa-eye"></i> Ver</a></td></tr><?php endforeach; ?></tbody></table></div></section>
    </div>
</div></div>

<script src="https://cdn.jsdelivr.net/npm/apexcharts@3.49.1"></script>
<script>
(function(){
    var colors = <?php echo json_encode(array_values($coloresEstado)); ?>;
    var stateLabels = <?php echo json_encode(array_values($estados)); ?>;
    var stateData = <?php echo json_encode(array_values($estadoTotals)); ?>;
    var categoryLabels = <?php echo json_encode(array_keys($porCategoriaEstado)); ?>;
    var categoryData = <?php echo json_encode(array_values($porCategoriaEstado)); ?>;
    var stateKeys = <?php echo json_encode(array_keys($estados)); ?>;
    new ApexCharts(document.querySelector('#estadoGeneralChart'), { chart:{type:'donut',height:310,fontFamily:'Montserrat, sans-serif',toolbar:{show:false}}, series:stateData, labels:stateLabels, colors:colors, fill:{type:'gradient',gradient:{shade:'light',type:'vertical',shadeIntensity:.35,opacityFrom:.98,opacityTo:.78,stops:[0,90,100]}}, stroke:{width:3,colors:['#fff']}, plotOptions:{pie:{donut:{size:'70%',labels:{show:true,total:{show:true,label:'Solicitudes',fontSize:'13px',color:'#68746b',formatter:function(){return stateData.reduce(function(a,b){return a+b;},0);}}}}}}, dataLabels:{enabled:true,formatter:function(value){return value.toFixed(1)+'%';}}, legend:{show:false}, tooltip:{y:{formatter:function(value){return value+' casos';}}}}).render();
    var series = stateKeys.map(function(key,index){return {name:stateLabels[index],data:categoryData.map(function(item){return item[key] || 0;})};});
    new ApexCharts(document.querySelector('#estadoCategoriaChart'), { chart:{type:'bar',height:310,fontFamily:'Montserrat, sans-serif',toolbar:{show:false}}, series:series, colors:colors, fill:{type:'gradient',gradient:{shade:'light',type:'vertical',shadeIntensity:.3,opacityFrom:.96,opacityTo:.7,stops:[0,88,100]}}, plotOptions:{bar:{horizontal:false,columnWidth:'58%',borderRadius:4,dataLabels:{position:'top'}}}, dataLabels:{enabled:true,offsetY:-16,style:{fontSize:'10px',colors:['#536057']},formatter:function(value){return value || ''; }}, xaxis:{categories:categoryLabels,labels:{style:{fontSize:'10px',colors:'#536057'}}}, yaxis:{min:0,forceNiceScale:true,labels:{style:{colors:'#7b857d'}}}, grid:{borderColor:'#edf0ec',strokeDashArray:4}, legend:{show:false}, tooltip:{shared:true,intersect:false,y:{formatter:function(value){return value+' casos';}}}}).render();
    function filter(){var text=document.getElementById('dashboard3Search').value.toLowerCase(),status=document.getElementById('dashboard3Status').value,project=document.getElementById('dashboard3Project').value;document.querySelectorAll('.dashboard3-request-row').forEach(function(row){var okText=!text||row.textContent.toLowerCase().indexOf(text)!==-1,okStatus=!status||row.getAttribute('data-status')===status,okProject=!project||row.getAttribute('data-project')===project;row.style.display=okText&&okStatus&&okProject?'':'none';});}
    document.getElementById('dashboard3Search').addEventListener('input',filter);document.getElementById('dashboard3Status').addEventListener('change',filter);document.getElementById('dashboard3Project').addEventListener('change',filter);
})();
</script>
<?php include 'includes/footer.php'; ?>
