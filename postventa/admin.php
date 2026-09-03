<?php
/**
 * Panel de Administración - Postventa Centinela
 * Solo accesible para usuarios con perfil administrador
 */
require_once 'includes/config.php';
require_once 'includes/api_helper.php';

// Verificar sesión y que sea admin
if (!isset($_SESSION['usuario_id']) || !isset($_SESSION['es_admin']) || $_SESSION['es_admin'] != 1) {
    header('Location: login.php');
    exit;
}

// Obtener solicitudes a través de la API
$solicitudes = [];
$totalSolicitudes = 0;
$totalPaginas = 1;
$porPagina = 10;
$paginaActual = isset($_GET['pagina']) ? max(1, (int)$_GET['pagina']) : 1;

$apiResponse = apiCall('solicitudes.php?action=todas', array());
$solicitudesRaw = ($apiResponse['success'] && isset($apiResponse['solicitudes'])) ? $apiResponse['solicitudes'] : array();

// Obtener obras para el filtro a través de la API
$obrasFiltro = array();
$apiObras = apiCall('solicitudes.php?action=obras', array());
if ($apiObras['success'] && isset($apiObras['obras'])) {
    $obrasFiltro = $apiObras['obras'];
}

// Formatear solicitudes para la vista
foreach ($solicitudesRaw as $row) {
    $solicitudes[] = [
        'id' => 'PC-' . date('Y', strtotime($row['created_at'])) . '-' . str_pad($row['id'], 3, '0', STR_PAD_LEFT),
        'id_num' => $row['id'],
        'fecha' => date('d/m/Y', strtotime($row['created_at'])),
        'rut' => $row['rut'],
        'nombre' => $row['nombre'],
        'email' => $row['email'],
        'telefono' => $row['telefono'],
        'rol' => (isset($row['rol_solicitante']) && $row['rol_solicitante'] === 'administrador_edificio') ? 'Administrador' : 'Propietario',
        'ubicacion' => $row['ubicacion_valor'],
        'categoria' => $row['categoria'],
        'subcategoria' => $row['subcategoria'],
        'estado' => $row['estado'],
        'detalle' => $row['detalle'],
        'dias' => $row['dias_disponibles'],
        'urgencia' => isset($row['urgencia']) ? $row['urgencia'] : 0,
        'obra_id' => $row['obra_id'],
        'obra_nombre' => isset($row['obra_nombre']) ? $row['obra_nombre'] : '',
        'evidencia' => 0
    ];
}

$totalSolicitudes = count($solicitudes);
$totalPaginas = ceil($totalSolicitudes / $porPagina);

// Si no hay datos, usar datos de ejemplo
if (empty($solicitudes)) {
    $solicitudes = [
    [
        'id' => 'PC-2024-001',
        'id_num' => '001',
        'fecha' => '15/03/2024',
        'rut' => '12.345.678-9',
        'nombre' => 'Carlos Muñoz R.',
        'email' => 'carlos.munoz@email.com',
        'telefono' => '+56 9 1234 5678',
        'rol' => 'Propietario',
        'ubicacion' => 'Depto 502, Torre A',
        'categoria' => 'Fallas Estructurales/Estéticas',
        'subcategoria' => 'Fisuras en muros',
        'estado' => 'pendiente',
        'detalle' => 'Se observa una fisura de aproximadamente 30cm en el muro del living, cercana a la ventana.',
        'dias' => 'Lunes AM, Miércoles AM/PM',
        'evidencia' => 2
    ],
    [
        'id' => 'PC-2024-002',
        'fecha' => '18/03/2024',
        'rut' => '9.876.543-2',
        'nombre' => 'María González L.',
        'email' => 'maria.gonzalez@email.com',
        'telefono' => '+56 9 8765 4321',
        'rol' => 'Propietario',
        'ubicacion' => 'Depto 1104, Torre B',
        'categoria' => 'Instalaciones (Gas/Agua/Luz)',
        'subcategoria' => 'Filtraciones de agua',
        'estado' => 'aprobado',
        'detalle' => 'Hay una filtración de agua en el baño principal que moja el piso constantemente.',
        'dias' => 'Martes PM, Jueves AM',
        'evidencia' => 3
    ],
    [
        'id' => 'PC-2024-003',
        'fecha' => '22/03/2024',
        'rut' => '12.345.678-9',
        'nombre' => 'Carlos Muñoz R.',
        'email' => 'carlos.munoz@email.com',
        'telefono' => '+56 9 1234 5678',
        'rol' => 'Propietario',
        'ubicacion' => 'Depto 502, Torre A',
        'categoria' => 'Terminaciones',
        'subcategoria' => 'Puertas descuadradas',
        'estado' => 'aprobado',
        'detalle' => 'La puerta del dormitorio principal no cierra correctamente.',
        'dias' => 'Lunes PM, Viernes AM',
        'evidencia' => 1
    ],
    [
        'id' => 'PC-2024-004',
        'fecha' => '25/03/2024',
        'rut' => '12.345.678-9',
        'nombre' => 'Carlos Muñoz R.',
        'email' => 'carlos.munoz@email.com',
        'telefono' => '+56 9 1234 5678',
        'rol' => 'Propietario',
        'ubicacion' => 'Estacionamiento N° 12',
        'categoria' => 'Instalaciones (Gas/Agua/Luz)',
        'subcategoria' => 'Cortocircuitos',
        'estado' => 'no_corresponde',
        'detalle' => 'El enchufe del estacionamiento no funciona.',
        'dias' => 'Miércoles AM',
        'evidencia' => 0
    ],
    [
        'id' => 'PC-2024-005',
        'fecha' => '28/03/2024',
        'rut' => '12.345.678-9',
        'nombre' => 'Carlos Muñoz R.',
        'email' => 'carlos.munoz@email.com',
        'telefono' => '+56 9 1234 5678',
        'rol' => 'Propietario',
        'ubicacion' => 'Bodega N° 8',
        'categoria' => 'Fallas Estructurales/Estéticas',
        'subcategoria' => 'Humedad en cielos',
        'estado' => 'resuelto',
        'detalle' => 'Mancha de humedad en el cielo de la bodega.',
        'dias' => 'Jueves PM, Viernes AM/PM',
        'evidencia' => 4
    ],
    [
        'id' => 'PC-2024-006',
        'fecha' => '02/04/2024',
        'rut' => '9.876.543-2',
        'nombre' => 'María González L.',
        'email' => 'maria.gonzalez@email.com',
        'telefono' => '+56 9 8765 4321',
        'rol' => 'Propietario',
        'ubicacion' => 'Depto 1104, Torre B',
        'categoria' => 'Terminaciones',
        'subcategoria' => 'Pisos flotantes levantados',
        'estado' => 'aprobado',
        'detalle' => 'Piso flotante del living se está levantando en las uniones.',
        'dias' => 'Lunes AM/PM, Martes AM',
        'evidencia' => 2
    ],
    [
        'id' => 'PC-2024-007',
        'fecha' => '05/04/2024',
        'rut' => '11.223.344-5',
        'nombre' => 'Pedro Soto A.',
        'email' => 'pedro.soto@email.com',
        'telefono' => '+56 9 5544 3322',
        'rol' => 'Administrador',
        'ubicacion' => 'Hall de Acceso',
        'categoria' => 'Fallas Estructurales/Estéticas',
        'subcategoria' => 'Desprendimientos de revestimiento',
        'estado' => 'pendiente',
        'detalle' => 'Revestimiento del muro del hall de acceso se está desprendiendo.',
        'dias' => 'Miércoles AM/PM, Jueves AM',
        'evidencia' => 3
    ],
    [
        'id' => 'PC-2024-008',
        'fecha' => '08/04/2024',
        'rut' => '11.223.344-5',
        'nombre' => 'Pedro Soto A.',
        'email' => 'pedro.soto@email.com',
        'telefono' => '+56 9 5544 3322',
        'rol' => 'Administrador',
        'ubicacion' => 'Ascensores',
        'categoria' => 'Instalaciones (Gas/Agua/Luz)',
        'subcategoria' => 'Cortocircuitos / falla eléctrica',
        'estado' => 'pendiente',
        'detalle' => 'El ascensor B presenta fallas intermitentes, se detiene entre pisos.',
        'dias' => 'Todos los días AM',
        'evidencia' => 1
    ],
];
} // fin if empty($solicitudes)

include 'includes/header.php';
?>

<div class="admin-page">
    <div class="admin-container">
        
        <div class="admin-header">
            <h1><i class="fas fa-cogs"></i> Panel de Administración</h1>
            <p class="text-muted">Gestione y apruebe las solicitudes de postventa. Solo accesible para administradores.</p>
        </div>
        
        <!-- Filtros -->
        <div class="admin-filters">
            <div class="form-group">
                <label for="filterSearch"><i class="fas fa-search"></i> Buscar</label>
                <input type="text" id="filterSearch" class="form-control" placeholder="Buscar por nombre, RUT, caso...">
            </div>
            <div class="form-group">
                <label for="filterEstado"><i class="fas fa-filter"></i> Estado</label>
                <select id="filterEstado" class="form-control">
                    <option value="">Todos los estados</option>
                    <option value="pendiente">Pendiente</option>
                    <option value="aprobado">Aprobado</option>
                    <option value="resuelto">Resuelto</option>
                    <option value="no_corresponde">No Corresponde</option>
                </select>
            </div>
            <div class="form-group">
                <label for="filterRol"><i class="fas fa-user-tag"></i> Rol</label>
                <select id="filterRol" class="form-control">
                    <option value="">Todos</option>
                    <option value="Propietario">Propietario</option>
                    <option value="Administrador">Administrador</option>
                </select>
            </div>
            <div class="form-group">
                <label for="filterCategoria"><i class="fas fa-tag"></i> Categoría</label>
                <select id="filterCategoria" class="form-control">
                    <option value="">Todas</option>
                    <option value="Estructural">Fallas Estructurales</option>
                    <option value="Instalaciones">Instalaciones</option>
                    <option value="Terminaciones">Terminaciones</option>
                </select>
            </div>
            <div class="form-group">
                <label for="filterObra"><i class="fas fa-building"></i> Proyecto</label>
                <select id="filterObra" class="form-control">
                    <option value="">Todos los proyectos</option>
                    <?php foreach ($obrasFiltro as $obra): ?>
                        <option value="<?php echo $obra['obra_id']; ?>"><?php echo htmlspecialchars($obra['obra_nombre']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <button type="button" class="btn btn-outline btn-sm" id="clearFilters">
                <i class="fas fa-times"></i> Limpiar
            </button>
        </div>
        
        <!-- Tabla de solicitudes -->
        <div class="card admin-table-card">
            <div class="card-header">
                <h3><i class="fas fa-list-ul"></i> Solicitudes Recibidas <span class="badge badge-pending" style="margin-left:8px;"><?php echo count($solicitudes); ?> total</span></h3>
                <div>
                    <button class="btn btn-sm btn-outline" id="exportBtn">
                        <i class="fas fa-download"></i> Exportar
                    </button>
                </div>
            </div>
            <div style="background:#e7f1ff; border-left:4px solid #0d6efd; padding:10px 16px; margin:0; font-size:0.82rem; color:#004085;">
                <i class="fas fa-info-circle"></i>
                En la columna <strong>Acciones</strong> puede 
                <span style="color:#28a745; font-weight:600;"><i class="fas fa-check"></i> Aprobar</span> o 
                <span style="color:#dc3545; font-weight:600;"><i class="fas fa-times"></i> Rechazar</span> cada solicitud. 
                <strong>Al aprobar, la solicitud será enviada al sistema de postventa de la constructora (SIGRO).</strong>
            </div>
            <div class="card-body" style="padding: 0;">
                <div class="table-container">
                    <table class="admin-table">
                        <thead>
                            <tr>
                                <th>N° Caso</th>
                                <th>Fecha</th>
                                <th>Cliente</th>
                                <th>Rol</th>
                                <th>Categoría</th>
                                <th>Proyecto</th>
                                <th>Ubicación</th>
                                <th>Estado</th>
                                <th>Prioridad</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($solicitudes as $sol): 
                                $badgeClass = '';
                                switch ($sol['estado']) {
                                    case 'pendiente': $badgeClass = 'badge-pending'; break;
                                    case 'aprobado': $badgeClass = 'badge-approved'; break;
                                    case 'resuelto': $badgeClass = 'badge-resolved'; break;
                                    case 'no_corresponde': $badgeClass = 'badge-rejected'; break;
                                }
                                $estadoLabel = [
                                    'pendiente' => 'Pendiente',
                                    'aprobado' => 'Aprobado',
                                    'resuelto' => 'Resuelto',
                                    'no_corresponde' => 'No Corresponde'
                                ][$sol['estado']];
                            ?>
                            <tr class="case-row" 
                                data-estado="<?php echo $sol['estado']; ?>" 
                                data-rol="<?php echo $sol['rol']; ?>"
                                data-obra="<?php echo $sol['obra_id']; ?>"
                                data-categoria="<?php echo strpos($sol['categoria'], 'Estructural') !== false ? 'Estructural' : (strpos($sol['categoria'], 'Instalaciones') !== false ? 'Instalaciones' : 'Terminaciones'); ?>">
                                <td><span class="case-id">#<?php echo $sol['id']; ?></span></td>
                                <td><?php echo $sol['fecha']; ?></td>
                                <td><strong><?php echo $sol['nombre']; ?></strong></td>
                                <td><?php echo $sol['rol']; ?></td>
                                <td><?php echo $sol['categoria']; ?></td>
                                <td><?php echo htmlspecialchars($sol['obra_nombre'] ?: '—'); ?></td>
                                <td><?php echo $sol['ubicacion']; ?></td>
                                <td><span class="badge <?php echo $badgeClass; ?>"><?php echo $estadoLabel; ?></span></td>
                                <td>
                                    <?php if ($sol['estado'] === 'pendiente'): ?>
                                    <select class="urgencia-select form-control" data-case-id="<?php echo $sol['id_num']; ?>" style="width:100px; padding:4px 6px; font-size:0.78rem;">
                                        <option value="0" <?php echo (isset($sol['urgencia']) && $sol['urgencia'] == 0) ? 'selected' : ''; ?>>Normal</option>
                                        <option value="1" <?php echo (isset($sol['urgencia']) && $sol['urgencia'] == 1) ? 'selected' : ''; ?>>Urgente</option>
                                    </select>
                                    <?php else: ?>
                                    <span class="badge <?php echo (isset($sol['urgencia']) && $sol['urgencia'] == 1) ? 'badge-rejected' : 'badge-pending'; ?>" style="font-size:0.75rem;">
                                        <?php echo (isset($sol['urgencia']) && $sol['urgencia'] == 1) ? 'Urgente' : 'Normal'; ?>
                                    </span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="action-buttons">
                                        <button class="action-btn view view-case" data-case-id="<?php echo $sol['id_num']; ?>" title="Ver detalle">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                        <?php if ($sol['estado'] === 'pendiente'): ?>
                                        <button class="action-btn approve approve-case" data-case-id="<?php echo $sol['id_num']; ?>" title="Aprobar - Se enviará el caso al sistema de la constructora">
                                            <i class="fas fa-check"></i>
                                        </button>
                                        <button class="action-btn reject reject-case" data-case-id="<?php echo $sol['id_num']; ?>" title="Rechazar">
                                            <i class="fas fa-times"></i>
                                        </button>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                
                <!-- Paginación -->
                <?php if ($totalPaginas > 1): ?>
                <div style="padding: 16px 24px;">
                    <ul class="pagination">
                        <li class="page-item <?php echo $paginaActual <= 1 ? 'disabled' : ''; ?>">
                            <a class="page-link" href="?pagina=<?php echo $paginaActual - 1; ?>"><i class="fas fa-chevron-left"></i></a>
                        </li>
                        <?php for ($p = 1; $p <= $totalPaginas; $p++): ?>
                        <li class="page-item <?php echo $p == $paginaActual ? 'active' : ''; ?>">
                            <a class="page-link" href="?pagina=<?php echo $p; ?>"><?php echo $p; ?></a>
                        </li>
                        <?php endfor; ?>
                        <li class="page-item <?php echo $paginaActual >= $totalPaginas ? 'disabled' : ''; ?>">
                            <a class="page-link" href="?pagina=<?php echo $paginaActual + 1; ?>"><i class="fas fa-chevron-right"></i></a>
                        </li>
                    </ul>
                </div>
                <?php endif; ?>
            </div>
        </div>
        
    </div>
</div>

<!-- MODAL DE DETALLE -->
<div class="modal-overlay" id="caseModal">
    <div class="modal-dialog">
        <div class="modal-header">
            <h2 id="modalCaseId"><i class="fas fa-folder-open"></i> Caso #PC-2024-001</h2>
            <button class="modal-close" title="Cerrar">&times;</button>
        </div>
        <div class="modal-body">
            
            <!-- Identificación -->
            <div class="detail-section">
                <h3><i class="fas fa-user"></i> Identificación del Solicitante</h3>
                <div class="detail-grid">
                    <div class="detail-item">
                        <span class="detail-label">RUT/DNI</span>
                        <span class="detail-value" id="modalRut">—</span>
                    </div>
                    <div class="detail-item">
                        <span class="detail-label">Nombre Completo</span>
                        <span class="detail-value" id="modalNombre">—</span>
                    </div>
                    <div class="detail-item">
                        <span class="detail-label">Correo Electrónico</span>
                        <span class="detail-value" id="modalEmail">—</span>
                    </div>
                    <div class="detail-item">
                        <span class="detail-label">Teléfono</span>
                        <span class="detail-value" id="modalTelefono">—</span>
                    </div>
                    <div class="detail-item">
                        <span class="detail-label">Rol</span>
                        <span class="detail-value" id="modalRol">—</span>
                    </div>
                    <div class="detail-item">
                        <span class="detail-label">Fecha de Ingreso</span>
                        <span class="detail-value" id="modalFecha">—</span>
                    </div>
                </div>
            </div>
            
            <!-- Ubicación -->
            <div class="detail-section">
                <h3><i class="fas fa-map-marker-alt"></i> Ubicación del Problema</h3>
                <div class="detail-grid">
                    <div class="detail-item full">
                        <span class="detail-label">Ubicación</span>
                        <span class="detail-value" id="modalUbicacion">—</span>
                    </div>
                </div>
            </div>
            
            <!-- Clasificación -->
            <div class="detail-section">
                <h3><i class="fas fa-tags"></i> Clasificación</h3>
                <div class="detail-grid">
                    <div class="detail-item">
                        <span class="detail-label">Categoría</span>
                        <span class="detail-value" id="modalCategoria">—</span>
                    </div>
                    <div class="detail-item">
                        <span class="detail-label">Subcategoría</span>
                        <span class="detail-value" id="modalSubcategoria">—</span>
                    </div>
                </div>
            </div>
            
            <!-- Descripción -->
            <div class="detail-section">
                <h3><i class="fas fa-align-left"></i> Descripción</h3>
                <p id="modalDetalle" style="font-size:0.9rem; color: var(--color-gray-700);">—</p>
            </div>
            
            <!-- Evidencia -->
            <div class="detail-section">
                <h3><i class="fas fa-paperclip"></i> Evidencia Adjunta</h3>
                <div class="evidence-gallery">
                    <div class="evidence-thumb"><i class="fas fa-image"></i></div>
                    <div class="evidence-thumb"><i class="fas fa-image"></i></div>
                    <div class="evidence-thumb"><i class="fas fa-video"></i></div>
                </div>
            </div>
            
            <!-- Días disponibles -->
            <div class="detail-section">
                <h3><i class="fas fa-calendar-week"></i> Días Disponibles para Visita</h3>
                <p id="modalDias" style="font-size:0.9rem; color: var(--color-gray-700);">—</p>
            </div>
            
            <!-- Estado de la solicitud -->
            <div class="detail-section">
                <h3><i class="fas fa-info-circle"></i> Estado de la Solicitud</h3>
                
                <div style="display:flex; align-items:center; gap:12px;">
                    <span>Estado actual:</span>
                    <span class="badge" id="modalStatusBadge">Pendiente</span>
                    <span><br/><br/></span>
                </div>

                <!-- Selector de Urgencia (solo admin_sistema) -->
                <div style="display:flex; align-items:center; gap:12px; margin-bottom: 16px;">
                    <span style="font-weight:600; font-size:0.85rem;">Nivel de Urgencia:</span>
                    <select id="modalUrgenciaSelect" class="status-select" style="width:auto; min-width:140px;">
                        <option value="0">Normal</option>
                        <option value="1">Urgente</option>
                    </select>
                </div>
                
                <div class="admin-comment-box mt-2">
                    <label style="font-size:0.82rem; font-weight:600; margin-bottom:4px; display:block;">Comentario interno (visible solo para administradores)</label>
                    <textarea placeholder="Agregar un comentario sobre este cambio de nivel de urgencia..."></textarea>
                </div>
            </div>
            
            <!-- Bitácora de Comunicaciones -->
            <div class="detail-section">
                <h3><i class="fas fa-comments"></i> Bitácora de Comunicaciones con el Cliente</h3>
                
                <!-- Historial de comunicaciones -->
                <div id="comunicacionesList" style="max-height:250px; overflow-y:auto; margin-bottom:16px;">
                    <span class="text-muted" style="font-size:0.85rem;">Cargando historial...</span>
                </div>
                
                <!-- Formulario para registrar nueva comunicación -->
                <div style="background:#f8f9fa; border:1px solid #dee2e6; border-radius:8px; padding:12px;">
                    <label style="font-size:0.82rem; font-weight:600; margin-bottom:6px; display:block;">
                        <i class="fas fa-plus-circle"></i> Registrar nueva comunicación
                    </label>
                    <div style="display:flex; gap:8px; margin-bottom:8px; flex-wrap:wrap;">
                        <select id="comunicacionSubtipo" class="form-control" style="width:auto; min-width:170px; padding:6px 10px; font-size:0.82rem;">
                            <option value="llamada">📞 Llamada telefónica</option>
                            <option value="whatsapp">💬 WhatsApp</option>
                            <option value="email">📧 Correo electrónico</option>
                            <option value="presencial">🏢 Visita presencial</option>
                            <option value="otro">📝 Otro medio</option>
                        </select>
                    </div>
                    <textarea id="comunicacionTexto" class="form-control" rows="2" placeholder="Describa la comunicación con el cliente... (ej. 'Se llamó al cliente para coordinar visita, acordamos el jueves AM')" style="width:100%; margin-bottom:8px; font-size:0.82rem;"></textarea>
                    <button class="btn btn-primary btn-sm" id="registrarComunicacionBtn">
                        <i class="fas fa-save"></i> Registrar
                    </button>
                </div>
            </div>
            
        </div>
        <div class="modal-footer">
            <button class="btn btn-secondary modal-close-btn">
                <i class="fas fa-times"></i> Cerrar
            </button>
        </div>
    </div>
</div>

<script>
// Filtros adicionales del admin
$(document).ready(function() {
    // Filtro por rol
    $('#filterRol').on('change', function() {
        applyFilters();
    });
    
    // Filtro por categoría
    $('#filterCategoria').on('change', function() {
        applyFilters();
    });
    
    // Filtro por obra
    $('#filterObra').on('change', function() {
        applyFilters();
    });
    
    // Limpiar filtros
    $('#clearFilters').on('click', function() {
        $('#filterSearch, #filterEstado, #filterRol, #filterCategoria, #filterObra').val('');
        $('.case-row').show();
    });
    
    function applyFilters() {
        var estado = $('#filterEstado').val();
        var rol = $('#filterRol').val();
        var categoria = $('#filterCategoria').val();
        var obra = $('#filterObra').val();
        var search = $('#filterSearch').val().toLowerCase();
        
        $('.case-row').each(function() {
            var show = true;
            
            if (estado && $(this).data('estado') !== estado) show = false;
            if (rol && $(this).data('rol') !== rol) show = false;
            if (categoria && $(this).data('categoria') !== categoria) show = false;
            if (obra && String($(this).data('obra')) !== obra) show = false;
            if (search && $(this).text().toLowerCase().indexOf(search) === -1) show = false;
            
            $(this).toggle(show);
        });
    }
});
</script>

<?php include 'includes/footer.php'; ?>
