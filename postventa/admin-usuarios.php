<?php
/**
 * Gestión de Usuarios - Postventa Centinela
 * Solo accesible para admin_sistema
 */
require_once 'includes/config.php';
require_once 'includes/api_helper.php';

if (!isset($_SESSION['usuario_id']) || !isset($_SESSION['usuario_rol']) || $_SESSION['usuario_rol'] !== 'admin_sistema') {
    header('Location: dashboard2.php');
    exit;
}

// Obtener lista de usuarios desde la API
$apiResponse = apiCall('usuarios.php?action=listar_usuarios', array());
$usuarios = ($apiResponse['success'] && isset($apiResponse['usuarios'])) ? $apiResponse['usuarios'] : array();

$mensaje = '';
$error = '';

// Procesar actualización
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'actualizar') {
    $updateResponse = apiCall('usuarios.php?action=actualizar_usuario', array(
        'id'       => $_POST['user_id'],
        'email'    => $_POST['email'],
        'telefono' => $_POST['telefono'],
        'rol'      => $_POST['rol'],
        'activo'   => isset($_POST['activo']) ? 1 : 0
    ));
    
    if ($updateResponse['success']) {
        $mensaje = $updateResponse['message'];
        // Refrescar lista
        $apiResponse = apiCall('usuarios.php?action=listar_usuarios', array());
        $usuarios = ($apiResponse['success'] && isset($apiResponse['usuarios'])) ? $apiResponse['usuarios'] : array();
    } else {
        $error = $updateResponse['message'];
    }
}

$rolLabels = array(
    'admin_sistema'          => 'Admin Sistema',
    'administrador_edificio' => 'Admin Edificio',
    'propietario'            => 'Propietario'
);

$usuariosExport = array();
foreach ($usuarios as $u) {
    $rolTexto = isset($rolLabels[$u['rol']]) ? $rolLabels[$u['rol']] : $u['rol'];
    $usuariosExport[] = array(
        'id' => (int)$u['id'],
        'rut' => isset($u['rut']) ? $u['rut'] : '',
        'nombre' => isset($u['nombre']) ? $u['nombre'] : '',
        'email' => isset($u['email']) ? $u['email'] : '',
        'telefono' => isset($u['telefono']) && $u['telefono'] ? $u['telefono'] : '',
        'rol' => $rolTexto,
        'activo' => !empty($u['activo']) ? 'Activo' : 'Inactivo',
        'registro' => !empty($u['created_at']) ? date('d/m/Y', strtotime($u['created_at'])) : ''
    );
}

include 'includes/header.php';
?>

<div class="admin-page">
    <div class="admin-container">
        
        <div class="admin-header">
            <h1><i class="fas fa-users-cog"></i> Gestión de Usuarios</h1>
            <p class="text-muted">Administre los usuarios del sistema. Solo puede modificar roles entre Propietario y Admin Edificio.</p>
        </div>
        
        <?php if ($mensaje): ?>
        <div class="alert alert-success"><i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($mensaje); ?></div>
        <?php endif; ?>
        <?php if ($error): ?>
        <div class="alert alert-danger"><i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        
        <!-- Tabla de usuarios -->
        <div class="card admin-table-card">
            <div class="card-header">
                <h3><i class="fas fa-list-ul"></i> Usuarios Registrados 
                    <span class="badge badge-pending" style="margin-left:8px;"><?php echo count($usuarios); ?> total</span>
                </h3>
                <div style="display:flex; align-items:center; gap:10px; flex-wrap:wrap;">
                    <input type="text" id="searchUsers" class="form-control" placeholder="Buscar usuario..." style="width:220px; display:inline-block; padding:8px 12px; font-size:0.85rem;">
                    <button type="button" class="btn btn-sm btn-outline" id="exportUsersBtn" style="cursor:pointer; display:inline-flex; align-items:center; gap:6px; font-weight:600; padding:8px 14px; border-radius:4px;">
                        <i class="fas fa-download"></i> Exportar
                    </button>
                </div>
            </div>
            <div class="card-body" style="padding: 0;">
                <div class="table-container">
                    <table class="admin-table">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>RUT</th>
                                <th>Nombre</th>
                                <th>Email</th>
                                <th>Teléfono</th>
                                <th>Rol</th>
                                <th>Activo</th>
                                <th>Registro</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($usuarios as $u): 
                                $esAdminSistema = ($u['rol'] === 'admin_sistema');
                                $esUsuarioActual = ($u['id'] == $_SESSION['usuario_id']);
                            ?>
                            <tr class="user-row" 
                                data-user-id="<?php echo $u['id']; ?>"
                                data-search="<?php echo strtolower($u['nombre'] . ' ' . $u['email'] . ' ' . $u['rut']); ?>">
                                <td>#<?php echo $u['id']; ?></td>
                                <td><?php echo htmlspecialchars($u['rut']); ?></td>
                                <td>
                                    <strong><?php echo htmlspecialchars($u['nombre']); ?></strong>
                                    <?php if ($esUsuarioActual): ?>
                                    <span class="badge badge-info" style="font-size:0.65rem;">Tú</span>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo htmlspecialchars($u['email']); ?></td>
                                <td><?php echo htmlspecialchars($u['telefono'] ? $u['telefono'] : '—'); ?></td>
                                <td>
                                    <span class="badge <?php echo $esAdminSistema ? 'badge-scheduled' : 'badge-approved'; ?>">
                                        <?php echo isset($rolLabels[$u['rol']]) ? $rolLabels[$u['rol']] : $u['rol']; ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="badge <?php echo $u['activo'] ? 'badge-approved' : 'badge-rejected'; ?>">
                                        <?php echo $u['activo'] ? 'Activo' : 'Inactivo'; ?>
                                    </span>
                                </td>
                                <td><?php echo date('d/m/Y', strtotime($u['created_at'])); ?></td>
                                <td>
                                    <?php if (!$esAdminSistema): ?>
                                    <button class="action-btn view edit-user-btn" 
                                            data-id="<?php echo $u['id']; ?>"
                                            data-nombre="<?php echo htmlspecialchars($u['nombre']); ?>"
                                            data-email="<?php echo htmlspecialchars($u['email']); ?>"
                                            data-telefono="<?php echo htmlspecialchars($u['telefono']); ?>"
                                            data-rol="<?php echo $u['rol']; ?>"
                                            data-activo="<?php echo $u['activo']; ?>"
                                            title="Editar usuario">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <?php else: ?>
                                    <span class="text-muted" style="font-size:0.78rem;">Protegido</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                            <?php if (empty($usuarios)): ?>
                            <tr>
                                <td colspan="9" class="text-center" style="padding:40px; color:var(--color-gray-500);">
                                    <i class="fas fa-users" style="font-size:2rem; display:block; margin-bottom:8px;"></i>
                                    No hay usuarios registrados.
                                </td>
                            </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        
    </div>
</div>

<!-- MODAL DE EDICIÓN -->
<div class="modal-overlay" id="editUserModal">
    <div class="modal-dialog" style="max-width:500px;">
        <div class="modal-header">
            <h2><i class="fas fa-user-edit"></i> Editar Usuario</h2>
            <button class="modal-close" onclick="closeModal()">&times;</button>
        </div>
        <form method="POST" action="">
            <input type="hidden" name="action" value="actualizar">
            <input type="hidden" name="user_id" id="editUserId">
            
            <div class="modal-body">
                <div class="detail-section">
                    <h3><i class="fas fa-user"></i> <span id="editUserName">—</span></h3>
                </div>
                
                <div class="form-group">
                    <label for="editEmail">Correo Electrónico <span class="required">*</span></label>
                    <input type="email" id="editEmail" name="email" class="form-control" required>
                </div>
                
                <div class="form-group">
                    <label for="editTelefono">Teléfono</label>
                    <input type="text" id="editTelefono" name="telefono" class="form-control">
                </div>
                
                <div class="form-group">
                    <label for="editRol">Rol <span class="required">*</span></label>
                    <select id="editRol" name="rol" class="form-control" required>
                        <option value="propietario">Propietario / Residente</option>
                        <option value="administrador_edificio">Administrador del Edificio</option>
                    </select>
                    <small class="form-text">No se puede asignar el rol Admin Sistema.</small>
                </div>
                
                <div class="form-group">
                    <label>
                        <input type="checkbox" id="editActivo" name="activo" value="1" style="accent-color: var(--color-primary);">
                        Usuario activo
                    </label>
                    <small class="form-text" style="display:block;">Desmarque para desactivar el acceso del usuario al sistema.</small>
                </div>
            </div>
            
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal()">
                    <i class="fas fa-times"></i> Cancelar
                </button>
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-save"></i> Guardar Cambios
                </button>
            </div>
        </form>
    </div>
</div>

<script>
$(document).ready(function() {
    // Búsqueda
    $('#searchUsers').on('keyup', function() {
        var search = $(this).val().toLowerCase();
        $('.user-row').each(function() {
            $(this).toggle($(this).data('search').indexOf(search) > -1);
        });
    });
    
    // Abrir modal de edición
    $('.edit-user-btn').on('click', function() {
        var btn = $(this);
        $('#editUserId').val(btn.data('id'));
        $('#editUserName').text(btn.data('nombre'));
        $('#editEmail').val(btn.data('email'));
        $('#editTelefono').val(btn.data('telefono'));
        $('#editRol').val(btn.data('rol'));
        $('#editActivo').prop('checked', btn.data('activo') == 1);
        $('#editUserModal').addClass('show');
        $('body').css('overflow', 'hidden');
    });
});

function closeModal() {
    $('#editUserModal').removeClass('show');
    $('body').css('overflow', '');
}

// Cerrar modal al hacer clic fuera
$(document).on('click', '.modal-overlay', function(e) {
    if (e.target === this) closeModal();
});
</script>

<!-- Librería SheetJS para exportación a Excel (.xlsx) -->
<script src="https://cdn.jsdelivr.net/npm/xlsx@0.18.5/dist/xlsx.full.min.js"></script>
<script>
window.adminUsuariosData = <?php echo json_encode($usuariosExport, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE); ?>;

// Evento para exportar a Excel
$(document).on('click', '#exportUsersBtn', function(e) {
    e.preventDefault();
    
    if (typeof XLSX === 'undefined') {
        if (typeof showToast === 'function') {
            showToast('La librería para exportar a Excel no se ha cargado. Verifique su conexión.', 'error');
        } else {
            alert('La librería para exportar a Excel no se ha cargado.');
        }
        return;
    }
    
    var $btn = $(this);
    var originalHtml = $btn.html();
    $btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Exportando...');
    
    try {
        var visibleUserIds = [];
        var totalRows = $('.user-row').length;
        
        $('.user-row:visible').each(function() {
            var uid = $(this).data('user-id');
            if (uid !== undefined && uid !== null) {
                visibleUserIds.push(String(uid));
            }
        });
        
        if (totalRows > 0 && $('.user-row:visible').length === 0) {
            if (typeof showToast === 'function') {
                showToast('No hay usuarios visibles que coincidan con la búsqueda.', 'warning');
            } else {
                alert('No hay usuarios visibles que coincidan con la búsqueda.');
            }
            $btn.prop('disabled', false).html(originalHtml);
            return;
        }
        
        var allData = window.adminUsuariosData || [];
        var exportRows = [];
        
        if (allData.length > 0) {
            var dataToExport = allData.filter(function(item) {
                if (visibleUserIds.length === 0) return true;
                return visibleUserIds.indexOf(String(item.id)) !== -1;
            });
            
            if (dataToExport.length === 0) {
                if (typeof showToast === 'function') {
                    showToast('No hay usuarios para exportar.', 'warning');
                }
                $btn.prop('disabled', false).html(originalHtml);
                return;
            }
            
            exportRows = dataToExport.map(function(item) {
                return {
                    'ID': '#' + item.id,
                    'RUT': item.rut || '',
                    'Nombre Completo': item.nombre || '',
                    'Email': item.email || '',
                    'Teléfono': item.telefono || '',
                    'Rol': item.rol || '',
                    'Estado': item.activo || '',
                    'Fecha Registro': item.registro || ''
                };
            });
        } else {
            // Fallback por DOM
            $('.user-row:visible').each(function() {
                var cells = $(this).find('td');
                if (cells.length >= 8) {
                    exportRows.push({
                        'ID': $(cells[0]).text().trim(),
                        'RUT': $(cells[1]).text().trim(),
                        'Nombre Completo': $(cells[2]).text().replace('Tú', '').trim(),
                        'Email': $(cells[3]).text().trim(),
                        'Teléfono': $(cells[4]).text().trim(),
                        'Rol': $(cells[5]).text().trim(),
                        'Estado': $(cells[6]).text().trim(),
                        'Fecha Registro': $(cells[7]).text().trim()
                    });
                }
            });
        }
        
        if (exportRows.length === 0) {
            if (typeof showToast === 'function') {
                showToast('No se encontraron registros de usuarios para exportar.', 'warning');
            }
            $btn.prop('disabled', false).html(originalHtml);
            return;
        }
        
        var ws = XLSX.utils.json_to_sheet(exportRows);
        ws['!cols'] = [
            { wch: 10 }, // ID
            { wch: 14 }, // RUT
            { wch: 28 }, // Nombre Completo
            { wch: 28 }, // Email
            { wch: 16 }, // Teléfono
            { wch: 18 }, // Rol
            { wch: 12 }, // Estado
            { wch: 16 }  // Fecha Registro
        ];
        
        var wb = XLSX.utils.book_new();
        XLSX.utils.book_append_sheet(wb, ws, 'Usuarios');
        
        var now = new Date();
        var yyyy = now.getFullYear();
        var mm = String(now.getMonth() + 1).padStart(2, '0');
        var dd = String(now.getDate()).padStart(2, '0');
        var fileName = 'Usuarios_Centinela_' + yyyy + '-' + mm + '-' + dd + '.xlsx';
        
        XLSX.writeFile(wb, fileName);
        
        if (typeof showToast === 'function') {
            showToast('Se han exportado ' + exportRows.length + ' usuario(s) a Excel con éxito.', 'success');
        }
    } catch (err) {
        console.error('Error al exportar usuarios:', err);
        if (typeof showToast === 'function') {
            showToast('Error al generar el archivo Excel: ' + (err.message || ''), 'error');
        }
    } finally {
        $btn.prop('disabled', false).html(originalHtml);
    }
});
</script>

<?php include 'includes/footer.php'; ?>
