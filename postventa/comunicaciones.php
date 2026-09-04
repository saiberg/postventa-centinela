<?php
/**
 * Bitacora global de comunicaciones - Postventa Centinela
 * Solo accesible para administradores del sistema.
 */
require_once 'includes/config.php';
require_once 'includes/api_helper.php';

if (!isset($_SESSION['usuario_id']) || !isset($_SESSION['usuario_rol']) || $_SESSION['usuario_rol'] !== 'admin_sistema') {
    header('Location: login.php');
    exit;
}

$filters = array(
    'obra_id' => isset($_GET['obra_id']) ? (int)$_GET['obra_id'] : 0,
    'tipo' => isset($_GET['tipo']) ? trim($_GET['tipo']) : '',
    'estado' => isset($_GET['estado']) ? trim($_GET['estado']) : '',
    'caso' => isset($_GET['caso']) ? trim($_GET['caso']) : '',
    'desde' => isset($_GET['desde']) ? trim($_GET['desde']) : '',
    'hasta' => isset($_GET['hasta']) ? trim($_GET['hasta']) : '',
    'sin_contacto' => isset($_GET['sin_contacto']) ? 1 : 0
);

$apiResponse = apiCall('solicitudes.php?action=comunicaciones', $filters);
$comunicaciones = ($apiResponse['success'] && isset($apiResponse['comunicaciones'])) ? $apiResponse['comunicaciones'] : array();
$casosSinContacto = ($apiResponse['success'] && isset($apiResponse['casos_sin_contacto'])) ? $apiResponse['casos_sin_contacto'] : array();
$stats = ($apiResponse['success'] && isset($apiResponse['stats'])) ? $apiResponse['stats'] : array();
$apiObras = apiCall('solicitudes.php?action=obras', array());
$obras = ($apiObras['success'] && isset($apiObras['obras'])) ? $apiObras['obras'] : array();

$tipos = array(
    'llamada' => 'Llamada',
    'whatsapp' => 'WhatsApp',
    'email' => 'Correo',
    'presencial' => 'Presencial',
    'otro' => 'Otro'
);
$estados = array(
    'pendiente' => 'Pendiente',
    'aprobado' => 'Aprobado',
    'resuelto' => 'Resuelto',
    'no_corresponde' => 'No corresponde'
);

function escapeValue($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

$currentPage = 'comunicaciones.php';
include 'includes/header.php';
?>

<div class="admin-page">
    <div class="admin-container communications-container">
        <div class="admin-header communications-header">
            <div>
                <h1><i class="fas fa-comments"></i> Bit&aacute;cora de comunicaciones</h1>
                <p>Consulta centralizada de contactos realizados con propietarios y administradores.</p>
            </div>
        </div>

        <div class="communications-stats">
            <div class="communication-stat">
                <span class="communication-stat-icon"><i class="fas fa-phone"></i></span>
                <div><strong><?php echo (int)($stats['total_casos'] ?: 0); ?></strong><span>Casos registrados</span></div>
            </div>
            <div class="communication-stat">
                <span class="communication-stat-icon active"><i class="fas fa-folder-open"></i></span>
                <div><strong><?php echo (int)($stats['casos_activos'] ?: 0); ?></strong><span>Casos activos</span></div>
            </div>
            <div class="communication-stat communication-stat-warning">
                <span class="communication-stat-icon warning"><i class="fas fa-clock"></i></span>
                <div><strong><?php echo (int)($stats['sin_contacto'] ?: 0); ?></strong><span>Activos sin contacto en 30 d&iacute;as</span></div>
            </div>
        </div>

        <form class="admin-filters communications-filters" method="get">
            <div class="form-group">
                <label for="obra_id"><i class="fas fa-building"></i> Proyecto</label>
                <select id="obra_id" name="obra_id" class="form-control">
                    <option value="0">Todos los proyectos</option>
                    <?php foreach ($obras as $obra): ?>
                    <option value="<?php echo (int)$obra['obra_id']; ?>" <?php echo $filters['obra_id'] == $obra['obra_id'] ? 'selected' : ''; ?>><?php echo escapeValue($obra['obra_nombre']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label for="caso"><i class="fas fa-search"></i> Caso o propietario</label>
                <input id="caso" name="caso" class="form-control" value="<?php echo escapeValue($filters['caso']); ?>" placeholder="N&uacute;mero, nombre o correo">
            </div>
            <div class="form-group">
                <label for="tipo"><i class="fas fa-comment-dots"></i> Medio</label>
                <select id="tipo" name="tipo" class="form-control">
                    <option value="">Todos los medios</option>
                    <?php foreach ($tipos as $value => $label): ?>
                    <option value="<?php echo $value; ?>" <?php echo $filters['tipo'] === $value ? 'selected' : ''; ?>><?php echo $label; ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label for="estado"><i class="fas fa-tasks"></i> Estado del caso</label>
                <select id="estado" name="estado" class="form-control">
                    <option value="">Todos los estados</option>
                    <?php foreach ($estados as $value => $label): ?>
                    <option value="<?php echo $value; ?>" <?php echo $filters['estado'] === $value ? 'selected' : ''; ?>><?php echo $label; ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group date-filter">
                <label for="desde">Contacto desde</label>
                <input type="date" id="desde" name="desde" class="form-control" value="<?php echo escapeValue($filters['desde']); ?>">
            </div>
            <div class="form-group date-filter">
                <label for="hasta">Contacto hasta</label>
                <input type="date" id="hasta" name="hasta" class="form-control" value="<?php echo escapeValue($filters['hasta']); ?>">
            </div>
            <label class="communication-check">
                <input type="checkbox" name="sin_contacto" value="1" <?php echo $filters['sin_contacto'] ? 'checked' : ''; ?>>
                <span>Solo activos sin contacto en 30 d&iacute;as</span>
            </label>
            <div class="communication-filter-actions">
                <button type="submit" class="btn btn-primary btn-sm"><i class="fas fa-filter"></i> Filtrar</button>
                <a href="comunicaciones.php" class="btn btn-outline btn-sm"><i class="fas fa-times"></i> Limpiar</a>
            </div>
        </form>

        <?php if (!$apiResponse['success']): ?>
        <div class="alert alert-danger"><i class="fas fa-exclamation-circle"></i> <?php echo escapeValue($apiResponse['message']); ?></div>
        <?php endif; ?>

        <?php if (!$filters['sin_contacto']): ?>
        <div class="card admin-table-card communications-table-card">
            <div class="card-header">
                <h3><i class="fas fa-list-ul"></i> Contactos realizados <span class="badge badge-pending"><?php echo count($comunicaciones); ?></span></h3>
            </div>
            <div class="card-body" style="padding:0;">
                <div class="table-container">
                    <table class="admin-table communications-table">
                        <thead>
                            <tr>
                                <th>Caso</th>
                                <th>Propietario</th>
                                <th>Proyecto / edificio</th>
                                <th>Fecha del caso</th>
                                <th>Contacto</th>
                                <th>Medio</th>
                                <th>Observaci&oacute;n</th>
                                <th>Registrado por</th>
                                <th>Estado</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php if (empty($comunicaciones)): ?>
                            <tr><td colspan="9" class="empty-state"><i class="fas fa-inbox"></i><span>No hay comunicaciones para los filtros seleccionados.</span></td></tr>
                        <?php else: ?>
                            <?php foreach ($comunicaciones as $contacto):
                                $tipo = str_replace('comunicacion:', '', $contacto['tipo']);
                                $estado = isset($estados[$contacto['estado']]) ? $estados[$contacto['estado']] : $contacto['estado'];
                                $badgeClass = $contacto['estado'] === 'resuelto' ? 'badge-resolved' : ($contacto['estado'] === 'no_corresponde' ? 'badge-rejected' : ($contacto['estado'] === 'aprobado' ? 'badge-approved' : 'badge-pending'));
                            ?>
                            <tr>
                                <td><a class="case-id" href="detalle-caso.php?id=<?php echo (int)$contacto['solicitud_id']; ?>">#<?php echo (int)$contacto['solicitud_id']; ?></a></td>
                                <td><strong><?php echo escapeValue($contacto['propietario']); ?></strong><small><?php echo escapeValue($contacto['propietario_email']); ?></small></td>
                                <td><?php echo escapeValue($contacto['proyecto'] ?: 'Sin proyecto'); ?><small><?php echo escapeValue($contacto['edificio'] ?: 'Edificio no informado'); ?></small></td>
                                <td><?php echo date('d/m/Y', strtotime($contacto['fecha_caso'])); ?></td>
                                <td><?php echo date('d/m/Y H:i', strtotime($contacto['created_at'])); ?></td>
                                <td><span class="contact-type contact-type-<?php echo escapeValue($tipo); ?>"><i class="fas fa-<?php echo $tipo === 'llamada' ? 'phone' : ($tipo === 'email' ? 'envelope' : ($tipo === 'whatsapp' ? 'comment' : ($tipo === 'presencial' ? 'building' : 'sticky-note'))); ?>"></i> <?php echo isset($tipos[$tipo]) ? $tipos[$tipo] : 'Otro'; ?></span></td>
                                <td class="communication-comment" title="<?php echo escapeValue($contacto['comentario']); ?>"><?php echo escapeValue($contacto['comentario']); ?></td>
                                <td><?php echo escapeValue($contacto['registrado_por']); ?></td>
                                <td><span class="badge <?php echo $badgeClass; ?>"><?php echo escapeValue($estado); ?></span></td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <div class="card admin-table-card communications-table-card communications-no-contact-card">
            <div class="card-header">
                <h3><i class="fas fa-user-clock"></i> Casos activos sin contacto en 30 d&iacute;as <span class="badge badge-rejected"><?php echo count($casosSinContacto); ?></span></h3>
            </div>
            <div class="card-body" style="padding:0;">
                <div class="table-container">
                    <table class="admin-table communications-table">
                        <thead>
                            <tr>
                                <th>Caso</th>
                                <th>Propietario</th>
                                <th>Proyecto / edificio</th>
                                <th>Fecha del caso</th>
                                <th>Antig&uuml;edad</th>
                                <th>Estado</th>
                                <th>Acci&oacute;n</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php if (empty($casosSinContacto)): ?>
                            <tr><td colspan="7" class="empty-state"><i class="fas fa-check-circle"></i><span>No hay casos activos sin contacto en los &uacute;ltimos 30 d&iacute;as.</span></td></tr>
                        <?php else: ?>
                            <?php foreach ($casosSinContacto as $casoSinContacto):
                                $diasSinGestion = max(0, (int)floor((time() - strtotime($casoSinContacto['created_at'])) / 86400));
                                $estadoCaso = isset($estados[$casoSinContacto['estado']]) ? $estados[$casoSinContacto['estado']] : $casoSinContacto['estado'];
                            ?>
                            <tr>
                                <td><a class="case-id" href="detalle-caso.php?id=<?php echo (int)$casoSinContacto['id']; ?>">#<?php echo (int)$casoSinContacto['id']; ?></a></td>
                                <td><strong><?php echo escapeValue($casoSinContacto['propietario']); ?></strong><small><?php echo escapeValue($casoSinContacto['propietario_email']); ?></small></td>
                                <td><?php echo escapeValue($casoSinContacto['proyecto'] ?: 'Sin proyecto'); ?><small><?php echo escapeValue($casoSinContacto['edificio'] ?: 'Edificio no informado'); ?></small></td>
                                <td><?php echo date('d/m/Y', strtotime($casoSinContacto['created_at'])); ?></td>
                                <td><span class="contact-age"><?php echo $diasSinGestion; ?> d&iacute;as</span></td>
                                <td><span class="badge <?php echo $casoSinContacto['estado'] === 'aprobado' ? 'badge-approved' : 'badge-pending'; ?>"><?php echo escapeValue($estadoCaso); ?></span></td>
                                <td><a class="action-btn view" href="detalle-caso.php?id=<?php echo (int)$casoSinContacto['id']; ?>" title="Ver caso"><i class="fas fa-eye"></i></a></td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
