<?php
/**
 * Dashboard del Cliente - Postventa Centinela
 * Muestra el panel principal con estadísticas y casos del cliente
 */
require_once 'includes/config.php';

// Verificar sesión
if (!isset($_SESSION['usuario_id'])) {
    header('Location: login.php');
    exit;
}

$isAdmin = isset($_SESSION['es_admin']) && $_SESSION['es_admin'] == 1;

// Obtener casos reales de la BD (o datos de ejemplo si no hay)
$casos = [];
$db = getDB();
$stmt = $db->prepare("SELECT id, created_at, categoria, subcategoria, ubicacion_valor, estado, fecha_agendamiento, equipo_asignado FROM icentPventaSolicitudes WHERE usuario_id = ? ORDER BY created_at DESC");
$stmt->bind_param('i', $_SESSION['usuario_id']);
$stmt->execute();
$result = $stmt->get_result();

while ($row = $result->fetch_assoc()) {
    $estadoLabel = [
        'pendiente' => 'Pendiente',
        'aprobado' => 'Aprobado',
        'no_corresponde' => 'No Corresponde',
        'agendado' => 'Agendado',
        'en_proceso' => 'En Proceso',
        'resuelto' => 'Resuelto'
    ];
    $casos[] = [
        'id' => 'PC-' . date('Y') . '-' . str_pad($row['id'], 3, '0', STR_PAD_LEFT),
        'fecha' => date('d/m/Y', strtotime($row['created_at'])),
        'categoria' => $row['categoria'],
        'subcategoria' => $row['subcategoria'],
        'ubicacion' => $row['ubicacion_valor'],
        'estado' => $row['estado'],
        'estado_label' => isset($estadoLabel[$row['estado']]) ? $estadoLabel[$row['estado']] : $row['estado'],
        'agendamiento' => $row['fecha_agendamiento'] ? date('d/m/Y - H:i', strtotime($row['fecha_agendamiento'])) : null,
        'equipo' => $row['equipo_asignado']
    ];
}

// Si no hay casos, usar datos de ejemplo para la demo visual
if (empty($casos)) {
    $casos = [
    [
        'id' => 'PC-2024-001',
        'fecha' => '15/03/2024',
        'categoria' => 'Fallas Estructurales',
        'subcategoria' => 'Fisuras en muros',
        'ubicacion' => 'Depto 502, Torre A',
        'estado' => 'pendiente',
        'estado_label' => 'Pendiente',
        'agendamiento' => null,
        'equipo' => null
    ],
    [
        'id' => 'PC-2024-002',
        'fecha' => '18/03/2024',
        'categoria' => 'Instalaciones',
        'subcategoria' => 'Filtraciones de agua',
        'ubicacion' => 'Depto 502, Torre A',
        'estado' => 'aprobado',
        'estado_label' => 'Aprobado',
        'agendamiento' => null,
        'equipo' => null
    ],
    [
        'id' => 'PC-2024-003',
        'fecha' => '22/03/2024',
        'categoria' => 'Terminaciones',
        'subcategoria' => 'Puertas descuadradas',
        'ubicacion' => 'Depto 502, Torre A',
        'estado' => 'agendado',
        'estado_label' => 'Agendado',
        'agendamiento' => '28/03/2024 - 10:00 AM',
        'equipo' => 'Equipo Técnico A (Juan Pérez)'
    ],
    [
        'id' => 'PC-2024-004',
        'fecha' => '25/03/2024',
        'categoria' => 'Instalaciones',
        'subcategoria' => 'Cortocircuitos',
        'ubicacion' => 'Estacionamiento N° 12',
        'estado' => 'no_corresponde',
        'estado_label' => 'No Corresponde',
        'agendamiento' => null,
        'equipo' => null
    ],
    [
        'id' => 'PC-2024-005',
        'fecha' => '28/03/2024',
        'categoria' => 'Fallas Estructurales',
        'subcategoria' => 'Humedad en cielos',
        'ubicacion' => 'Bodega N° 8',
        'estado' => 'resuelto',
        'estado_label' => 'Resuelto',
        'agendamiento' => '05/04/2024 - 15:00 PM',
        'equipo' => 'Equipo Técnico B (María Soto)'
    ],
    [
        'id' => 'PC-2024-006',
        'fecha' => '02/04/2024',
        'categoria' => 'Terminaciones',
        'subcategoria' => 'Pisos flotantes levantados',
        'ubicacion' => 'Depto 502, Torre A',
        'estado' => 'en_proceso',
        'estado_label' => 'En Proceso',
        'agendamiento' => '10/04/2024 - 09:00 AM',
        'equipo' => 'Equipo Técnico A (Juan Pérez)'
    ],
];
} // fin if empty($casos)

// Estadísticas
$totalCasos = count($casos);
$pendientes = count(array_filter($casos, function($c) { return $c['estado'] === 'pendiente'; }));
$enProceso = count(array_filter($casos, function($c) { return in_array($c['estado'], ['aprobado', 'agendado', 'en_proceso']); }));
$resueltos = count(array_filter($casos, function($c) { return $c['estado'] === 'resuelto'; }));
$noCorresponde = count(array_filter($casos, function($c) { return $c['estado'] === 'no_corresponde'; }));

include 'includes/header.php';
?>

<div class="dashboard-page">
    <div class="dashboard-container">
        
        <!-- Cabecera -->
        <div class="dashboard-header">
            <h1><i class="fas fa-tachometer-alt"></i> Mi Panel de Postventa</h1>
            <p class="welcome-text">Bienvenido, <?php echo htmlspecialchars($_SESSION['usuario_nombre']); ?>. Aquí puedes revisar el estado de tus solicitudes.</p>
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
        
        <!-- Tiempos Promedio de Resolución -->
        <div class="card avg-time-card">
            <div class="card-header">
                <h3><i class="fas fa-chart-line"></i> Tiempos Promedio de Resolución</h3>
            </div>
            <div class="card-body">
                <div class="avg-time-grid">
                    <div class="avg-time-item">
                        <span class="time-value">48h</span>
                        <span class="time-label">Primera Respuesta</span>
                    </div>
                    <div class="avg-time-item">
                        <span class="time-value">5 días</span>
                        <span class="time-label">Agendamiento de Visita</span>
                    </div>
                    <div class="avg-time-item">
                        <span class="time-value">15 días</span>
                        <span class="time-label">Resolución Total</span>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Tabla de Mis Casos -->
        <div class="card cases-table-card">
            <div class="card-header">
                <h3><i class="fas fa-list-ul"></i> Mis Solicitudes</h3>
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
                    <a href="nueva-solicitud.php" class="btn btn-primary">
                        <i class="fas fa-plus-circle"></i> Nueva Solicitud
                    </a>
                </div>
            </div>
            <div class="card-body" style="padding: 0;">
                <div class="table-container">
                    <table>
                        <thead>
                            <tr>
                                <th>N° Caso</th>
                                <th>Fecha</th>
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
                                    <a href="detalle-caso.php?id=<?php echo $caso['id']; ?>" class="btn btn-sm btn-outline">
                                        <i class="fas fa-eye"></i> Ver
                                    </a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                
                <!-- Paginación -->
                <div style="padding: 16px 24px;">
                    <ul class="pagination">
                        <li class="page-item disabled"><span class="page-link"><i class="fas fa-chevron-left"></i></span></li>
                        <li class="page-item active"><span class="page-link">1</span></li>
                        <li class="page-item"><span class="page-link">2</span></li>
                        <li class="page-item"><span class="page-link"><i class="fas fa-chevron-right"></i></span></li>
                    </ul>
                </div>
            </div>
        </div>
        
    </div>
</div>

<script>
// Filtros de tabla
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
