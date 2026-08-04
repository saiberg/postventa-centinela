<?php
/**
 * Nueva Solicitud - Postventa Centinela
 * Formulario de ingreso de solicitudes de reparación/atención
 */
require_once 'includes/config.php';
require_once 'includes/api_helper.php';

// Verificar sesión
if (!isset($_SESSION['usuario_id'])) {
    header('Location: login.php');
    exit;
}

// El admin_sistema no puede generar solicitudes
if (isset($_SESSION['usuario_rol']) && $_SESSION['usuario_rol'] === 'admin_sistema') {
    header('Location: dashboard.php');
    exit;
}

// Obtener datos del usuario a través de la API
$apiResponse = apiCall('usuarios.php?action=perfil', array());
$usuario = ($apiResponse['success'] && isset($apiResponse['user'])) ? $apiResponse['user'] : null;

// Fallback con datos de sesión si la API falla
if (!$usuario) {
    $usuario = array(
        'rut'      => isset($_SESSION['usuario_rut']) ? $_SESSION['usuario_rut'] : '',
        'nombre'   => $_SESSION['usuario_nombre'],
        'email'    => $_SESSION['usuario_email'],
        'telefono' => '',
        'rol'      => isset($_SESSION['usuario_rol']) ? $_SESSION['usuario_rol'] : 'propietario'
    );
}

// Determinar el rol para el selector
$rolSesion = $usuario['rol'];
$esAdminSistema = ($rolSesion === 'admin_sistema');
$esAdminEdificio = ($rolSesion === 'administrador_edificio');
$esPropietario = ($rolSesion === 'propietario');

// El admin_sistema puede elegir rol; los demás lo tienen fijo
$rolFijo = !$esAdminSistema;
$rolDefault = $esAdminEdificio ? 'administrador' : 'propietario';

// Datos de ejemplo para los dropdowns
$departamentos = [
    '101' => 'Departamento 101 - Torre A',
    '102' => 'Departamento 102 - Torre A',
    '201' => 'Departamento 201 - Torre A',
    '202' => 'Departamento 202 - Torre A',
    '301' => 'Departamento 301 - Torre A',
    '302' => 'Departamento 302 - Torre A',
    '401' => 'Departamento 401 - Torre A',
    '402' => 'Departamento 402 - Torre A',
    '501' => 'Departamento 501 - Torre A',
    '502' => 'Departamento 502 - Torre A',
];

$estacionamientos = [
    'E-01' => 'Estacionamiento N° 1',
    'E-02' => 'Estacionamiento N° 2',
    'E-12' => 'Estacionamiento N° 12',
    'E-15' => 'Estacionamiento N° 15',
];

$bodegas = [
    'B-03' => 'Bodega N° 3',
    'B-08' => 'Bodega N° 8',
    'B-12' => 'Bodega N° 12',
];

$areasComunes = [
    'quincho' => 'Quincho / Zona de Parrillas',
    'piscina' => 'Piscina',
    'hall' => 'Hall de Acceso',
    'ascensores' => 'Ascensores',
    'est_visitas' => 'Estacionamiento de Visitas',
    'fachada' => 'Fachada',
    'jardines' => 'Jardines / Áreas Verdes',
    'gimnasio' => 'Gimnasio',
    'sala_eventos' => 'Sala de Eventos',
    'pasillos' => 'Pasillos Comunes',
    'otro' => 'Otra Área Común',
];

// Preparar datos de upload para la vista
$allowedMimes = ALLOWED_FILE_TYPES;
$acceptList = ALLOW_ALL_FORMATS ? '' : implode(',', array_keys($allowedMimes));
if (ALLOW_ALL_FORMATS) {
    $hintText = 'Todos los formatos permitidos';
} else {
    $exts = array_unique(array_map(function($m) { return explode('/', $m)[1]; }, array_keys($allowedMimes)));
    $hintText = 'Formatos permitidos: ' . strtoupper(implode(', ', $exts));
}

include 'includes/header.php';
?>

<div class="solicitud-container">
    
    <div class="solicitud-header">
        <h1><i class="fas fa-plus-circle"></i> Nueva Solicitud de Atención</h1>
        <p>Complete el siguiente formulario para ingresar su requerimiento. Todos los campos marcados con <span class="required">*</span> son obligatorios.</p>
    </div>
    
    <!-- Contenedor de errores -->
    <div id="formErrors"></div>
    
    <form id="formSolicitud" method="POST" action="" enctype="multipart/form-data">
        
        <!-- SECCIÓN A: Identificación -->
        <div class="form-section">
            <div class="form-section-header">
                <span class="section-number">A</span>
                <h2>Identificación del Solicitante</h2>
            </div>
            <div class="form-section-body">
                <p class="text-muted mb-2" style="font-size:0.82rem;">
                    <i class="fas fa-info-circle"></i> Datos de su cuenta. Si necesita modificarlos, hágalo desde <a href="perfil.php">Mi Perfil</a>.
                </p>
                <div class="form-row">
                    <div class="form-group">
                        <label for="rut">RUT/DNI</label>
                        <input type="text" id="rut" name="rut" class="form-control" value="<?php echo htmlspecialchars($usuario['rut']); ?>" readonly>
                    </div>
                    <div class="form-group">
                        <label for="nombre">Nombre Completo</label>
                        <input type="text" id="nombre" name="nombre" class="form-control" value="<?php echo htmlspecialchars($usuario['nombre']); ?>" readonly>
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label for="email">Correo Electrónico</label>
                        <input type="email" id="email" name="email" class="form-control" value="<?php echo htmlspecialchars($usuario['email']); ?>" readonly>
                    </div>
                    <div class="form-group">
                        <label for="telefono">Teléfono</label>
                        <input type="tel" id="telefono" name="telefono" class="form-control" value="<?php echo htmlspecialchars($usuario['telefono']); ?>" readonly>
                    </div>
                </div>
                <div class="form-group">
                    <label for="rol">Rol <?php if ($esAdminSistema): ?><span class="required">*</span><?php endif; ?></label>
                    <select id="rol" name="rol" class="form-control" <?php echo $rolFijo ? 'disabled' : ''; ?>>
                        <option value="">Seleccione su rol...</option>
                        <option value="propietario" <?php echo ($rolDefault === 'propietario') ? 'selected' : ''; ?>>Propietario / Residente</option>
                        <option value="administrador" <?php echo ($rolDefault === 'administrador') ? 'selected' : ''; ?>>Administrador del Edificio</option>
                    </select>
                    <?php if ($rolFijo): ?>
                    <input type="hidden" name="rol" value="<?php echo $rolDefault; ?>">
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <!-- SECCIÓN B: Ubicación del Problema -->
        <div class="form-section">
            <div class="form-section-header">
                <span class="section-number">B</span>
                <h2>Ubicación del Problema</h2>
            </div>
            <div class="form-section-body">
                
                <!-- Selects en cascada: Obra → Edificio → Piso → Departamento -->
                <div class="form-row">
                    <div class="form-group">
                        <label for="obra">Proyecto <span class="required">*</span></label>
                        <select id="obra" name="obra" class="form-control">
                            <option value="">Seleccione una obra...</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="edificio">Edificio <span class="required">*</span></label>
                        <select id="edificio" name="edificio" class="form-control" disabled>
                            <option value="">Primero seleccione una obra...</option>
                        </select>
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label for="piso">Piso <span class="required">*</span></label>
                        <select id="piso" name="piso" class="form-control" disabled>
                            <option value="">Primero seleccione un edificio...</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="departamento">N° de Departamento <span class="required">*</span></label>
                        <select id="departamento" name="departamento" class="form-control" disabled>
                            <option value="">Primero seleccione un piso...</option>
                        </select>
                    </div>
                </div>
                
                <!-- Campos para Propietario -->
                <div id="propietario-fields" style="display:none;">
                    <p class="text-muted mb-2" style="font-size:0.82rem;">
                        <i class="fas fa-info-circle"></i> Si el problema afecta solo a su departamento, no marque ninguna opción adicional.
                    </p>
                    <div class="form-row">
                        <div class="form-group">
                            <label style="display:flex;align-items:center;gap:8px;cursor:pointer;font-weight:600;">
                                <input type="checkbox" id="tiene_estacionamiento" name="tiene_estacionamiento" value="1" style="accent-color:var(--color-primary);width:18px;height:18px;">
                                El problema está en mi Estacionamiento
                            </label>
                            <input type="text" id="estacionamiento_num" name="estacionamiento_num" class="form-control" 
                                   placeholder="N° de estacionamiento" disabled style="margin-top:6px;">
                        </div>
                        <div class="form-group">
                            <label style="display:flex;align-items:center;gap:8px;cursor:pointer;font-weight:600;">
                                <input type="checkbox" id="tiene_bodega" name="tiene_bodega" value="1" style="accent-color:var(--color-primary);width:18px;height:18px;">
                                El problema está en mi Bodega
                            </label>
                            <input type="text" id="bodega_num" name="bodega_num" class="form-control" 
                                   placeholder="N° de bodega" disabled style="margin-top:6px;">
                        </div>
                    </div>
                </div>
                
                <!-- Campos para Administrador -->
                <div id="admin-fields" style="display:none;">
                    <div class="form-group">
                        <label for="area_comun">Área Común Afectada <span class="required">*</span></label>
                        <select id="area_comun" name="area_comun" class="form-control">
                            <option value="">Seleccione el área común...</option>
                            <?php foreach ($areasComunes as $val => $label): ?>
                            <option value="<?php echo $val; ?>"><?php echo $label; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- SECCIÓN C: Clasificación -->
        <div class="form-section">
            <div class="form-section-header">
                <span class="section-number">C</span>
                <h2>Clasificación del Problema</h2>
            </div>
            <div class="form-section-body">
                <div class="form-row">
                    <div class="form-group">
                        <label for="categoria">Categoría <span class="required">*</span></label>
                        <select id="categoria" name="categoria" class="form-control">
                            <option value="">Seleccione una categoría...</option>
                            <option value="estructural">Fallas Estructurales / Estéticas</option>
                            <option value="instalaciones">Instalaciones (Gas / Agua / Luz)</option>
                            <option value="terminaciones">Terminaciones</option>
                            <option value="otro">Otro</option>
                        </select>
                    </div>
                    <div class="form-group" id="subcategoria-group" style="display:none;">
                        <label for="subcategoria">Subcategoría <span class="required">*</span></label>
                        <select id="subcategoria" name="subcategoria" class="form-control">
                            <option value="">Primero seleccione una categoría...</option>
                        </select>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- SECCIÓN D: Evidencia y Descripción -->
        <div class="form-section">
            <div class="form-section-header">
                <span class="section-number">D</span>
                <h2>Evidencia y Descripción</h2>
            </div>
            <div class="form-section-body">
                <div class="form-group">
                    <label>Adjuntar Fotos o Videos <small class="text-muted">(máx. 50MB por archivo)</small></label>
                    <div class="drop-zone" id="dropZone">
                        <div class="drop-icon"><i class="fas fa-cloud-upload-alt"></i></div>
                        <div class="drop-text">Arrastra aquí tus archivos o haz clic para seleccionar</div>
                        <div class="drop-hint"><?php echo $hintText; ?></div>
                    </div>
                    <input type="file" id="fileInput" name="archivos[]" multiple accept="<?php echo $acceptList; ?>" style="display:none;">
                    <div class="file-list" id="fileList"></div>
                </div>
                
                <div class="form-group">
                    <label for="detalle">Descripción Detallada del Problema</label>
                    <textarea id="detalle" name="detalle" class="form-control" rows="4" placeholder="Describa el problema con el mayor detalle posible. Incluya información sobre cuándo comenzó, cómo se manifiesta y cualquier otro dato relevante..."></textarea>
                </div>
            </div>
        </div>
        
        <!-- SECCIÓN E: Días Disponibles -->
        <div class="form-section">
            <div class="form-section-header">
                <span class="section-number">E</span>
                <h2>Días Disponibles para Visita</h2>
            </div>
            <div class="form-section-body">
                <p class="text-muted mb-3">Seleccione los días y horarios en que estaría disponible para recibir la visita técnica. <strong>Debe seleccionar al menos 2 períodos en días distintos.</strong></p>
                
                <div class="availability-notice">
                    <i class="fas fa-info-circle"></i>
                    <span><strong>Importante:</strong> La disponibilidad indicada está sujeta a disponibilidad y confirmación de la inmobiliaria.</span>
                </div>
                
                <div class="days-grid">
                    <?php
                    $dias = ['Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes'];
                    foreach ($dias as $dia):
                        $diaKey = strtolower(str_replace('é', 'e', str_replace('í', 'i', $dia)));
                    ?>
                    <div class="day-card">
                        <div class="day-name">
                            <?php echo $dia; ?>
                            <span class="day-toggle" data-day="<?php echo $diaKey; ?>">Todo el día</span>
                        </div>
                        <div class="day-options">
                            <label>
                                <input type="checkbox" name="dias[<?php echo $diaKey; ?>][]" value="AM" class="day-check" id="<?php echo $diaKey; ?>-manana">
                                AM <small>(9:00-13:00)</small>
                            </label>
                            <label>
                                <input type="checkbox" name="dias[<?php echo $diaKey; ?>][]" value="PM" class="day-check" id="<?php echo $diaKey; ?>-tarde">
                                PM <small>(14:00-18:00)</small>
                            </label>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
        
        <!-- Navegación -->
        <div class="form-navigation">
            <a href="dashboard.php" class="btn btn-secondary">
                <i class="fas fa-arrow-left"></i> Volver al Panel
            </a>
            <button type="submit" class="btn btn-primary btn-lg">
                <i class="fas fa-paper-plane"></i> Enviar Solicitud
            </button>
        </div>
        
    </form>
</div>
<script>
var APP_CONFIG = {
    allowAllFormats: <?php echo ALLOW_ALL_FORMATS ? 'true' : 'false'; ?>,
    allowedTypes: <?php echo json_encode(array_keys($allowedMimes)); ?>,
    maxFileSize: <?php echo MAX_FILE_SIZE; ?>
};
</script>
<?php include 'includes/footer.php'; ?>
