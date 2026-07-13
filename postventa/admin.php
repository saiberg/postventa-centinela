<?php
/**
 * Panel de Administración - Postventa Centinela
 * Solo accesible para usuarios con perfil administrador
 */
require_once 'includes/config.php';

// Verificar sesión y que sea admin
if (!isset($_SESSION['usuario_id']) || !isset($_SESSION['es_admin']) || $_SESSION['es_admin'] != 1) {
    header('Location: login.php');
    exit;
}

// Obtener solicitudes de la BD
$solicitudes = [];
$db = getDB();

// Paginación
$porPagina = 10;
$paginaActual = isset($_GET['pagina']) ? max(1, (int)$_GET['pagina']) : 1;
$offset = ($paginaActual - 1) * $porPagina;

// Contar total
$totalResult = $db->query("SELECT COUNT(*) as total FROM icentPventaSolicitudes");
$totalRow = $totalResult->fetch_assoc();
$totalSolicitudes = $totalRow['total'];
$totalPaginas = ceil($totalSolicitudes / $porPagina);

$result = $db->query("SELECT s.id, s.created_at, s.rut, s.nombre, s.email, s.telefono, s.rol_solicitante, 
                             s.ubicacion_valor, s.categoria, s.subcategoria, s.estado, s.detalle, s.dias_disponibles
                      FROM icentPventaSolicitudes s 
                      ORDER BY s.created_at DESC
                      LIMIT $porPagina OFFSET $offset");

while ($row = $result->fetch_assoc()) {
    $solicitudes[] = [
        'id' => 'PC-' . date('Y') . '-' . str_pad($row['id'], 3, '0', STR_PAD_LEFT),
        'id_num' => $row['id'],
        'fecha' => date('d/m/Y', strtotime($row['created_at'])),
        'rut' => $row['rut'],
        'nombre' => $row['nombre'],
        'email' => $row['email'],
        'telefono' => $row['telefono'],
        'rol' => $row['rol_solicitante'] === 'administrador_edificio' ? 'Administrador' : 'Propietario',
        'ubicacion' => $row['ubicacion_valor'],
        'categoria' => $row['categoria'],
        'subcategoria' => $row['subcategoria'],
        'estado' => $row['estado'],
        'detalle' => $row['detalle'],
        'dias' => $row['dias_disponibles'],
        'evidencia' => 0
    ];
}

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
        'estado' => 'agendado',
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
        'estado' => 'en_proceso',
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
                    <option value="agendado">Agendado</option>
                    <option value="en_proceso">En Proceso</option>
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
            <div class="card-body" style="padding: 0;">
                <div class="table-container">
                    <table class="admin-table">
                        <thead>
                            <tr>
                                <th>N° Caso</th>
                                <th>Fecha</th>
                                <th>Cliente</th>
                                <th>RUT</th>
                                <th>Rol</th>
                                <th>Categoría</th>
                                <th>Ubicación</th>
                                <th>Estado</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($solicitudes as $sol): 
                                $badgeClass = '';
                                switch ($sol['estado']) {
                                    case 'pendiente': $badgeClass = 'badge-pending'; break;
                                    case 'aprobado': $badgeClass = 'badge-approved'; break;
                                    case 'agendado': $badgeClass = 'badge-scheduled'; break;
                                    case 'en_proceso': $badgeClass = 'badge-in-progress'; break;
                                    case 'resuelto': $badgeClass = 'badge-resolved'; break;
                                    case 'no_corresponde': $badgeClass = 'badge-rejected'; break;
                                }
                                $estadoLabel = [
                                    'pendiente' => 'Pendiente',
                                    'aprobado' => 'Aprobado',
                                    'agendado' => 'Agendado',
                                    'en_proceso' => 'En Proceso',
                                    'resuelto' => 'Resuelto',
                                    'no_corresponde' => 'No Corresponde'
                                ][$sol['estado']];
                            ?>
                            <tr class="case-row" 
                                data-estado="<?php echo $sol['estado']; ?>" 
                                data-rol="<?php echo $sol['rol']; ?>"
                                data-categoria="<?php echo strpos($sol['categoria'], 'Estructural') !== false ? 'Estructural' : (strpos($sol['categoria'], 'Instalaciones') !== false ? 'Instalaciones' : 'Terminaciones'); ?>">
                                <td><span class="case-id">#<?php echo $sol['id']; ?></span></td>
                                <td><?php echo $sol['fecha']; ?></td>
                                <td><strong><?php echo $sol['nombre']; ?></strong></td>
                                <td><?php echo $sol['rut']; ?></td>
                                <td><?php echo $sol['rol']; ?></td>
                                <td><?php echo $sol['categoria']; ?></td>
                                <td><?php echo $sol['ubicacion']; ?></td>
                                <td><span class="badge <?php echo $badgeClass; ?>"><?php echo $estadoLabel; ?></span></td>
                                <td>
                                    <div class="action-buttons">
                                        <button class="action-btn view view-case" data-case-id="<?php echo $sol['id_num']; ?>" title="Ver detalle">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                        <?php if ($sol['estado'] === 'pendiente'): ?>
                                        <button class="action-btn approve approve-case" data-case-id="<?php echo $sol['id_num']; ?>" title="Aprobar">
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
            
            <!-- Cambio de Estado -->
            <div class="detail-section">
                <h3><i class="fas fa-exchange-alt"></i> Cambiar Estado</h3>
                <div style="display:flex; align-items:center; gap:12px;">
                    <span>Estado actual:</span>
                    <span class="badge" id="modalStatusBadge">Pendiente</span>
                    <span style="margin: 0 8px; color: var(--color-gray-400);">→</span>
                    <select id="modalStatusSelect" class="status-select" style="width:auto; min-width:180px;">
                        <option value="pendiente">Pendiente</option>
                        <option value="aprobado">Aprobado</option>
                        <option value="no_corresponde">No Corresponde</option>
                        <option value="agendado">Agendado</option>
                        <option value="en_proceso">En Proceso</option>
                        <option value="resuelto">Resuelto</option>
                    </select>
                </div>
                
                <div class="admin-comment-box mt-2">
                    <label style="font-size:0.82rem; font-weight:600; margin-bottom:4px; display:block;">Comentario interno (visible solo para administradores)</label>
                    <textarea placeholder="Agregar un comentario sobre este cambio de estado..."></textarea>
                </div>
            </div>
            
        </div>
        <div class="modal-footer">
            <button class="btn btn-secondary modal-close-btn">
                <i class="fas fa-times"></i> Cerrar
            </button>
            <button class="btn btn-primary" id="saveStatusBtn">
                <i class="fas fa-save"></i> Guardar Cambios
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
    
    // Limpiar filtros
    $('#clearFilters').on('click', function() {
        $('#filterSearch, #filterEstado, #filterRol, #filterCategoria').val('');
        $('.case-row').show();
    });
    
    // Guardar cambios en modal
    $('#saveStatusBtn').on('click', function() {
        alert('Cambios guardados correctamente (simulación).');
        $('#caseModal').removeClass('show');
        $('body').css('overflow', '');
    });
    
    function applyFilters() {
        var estado = $('#filterEstado').val();
        var rol = $('#filterRol').val();
        var categoria = $('#filterCategoria').val();
        var search = $('#filterSearch').val().toLowerCase();
        
        $('.case-row').each(function() {
            var show = true;
            
            if (estado && $(this).data('estado') !== estado) show = false;
            if (rol && $(this).data('rol') !== rol) show = false;
            if (categoria && $(this).data('categoria') !== categoria) show = false;
            if (search && $(this).text().toLowerCase().indexOf(search) === -1) show = false;
            
            $(this).toggle(show);
        });
    }
});
</script>

<?php include 'includes/footer.php'; ?>
