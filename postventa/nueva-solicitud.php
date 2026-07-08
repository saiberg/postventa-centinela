<?php
/**
 * Nueva Solicitud - Postventa Centinela
 * Formulario de ingreso de solicitudes de reparación/atención
 */
require_once 'includes/config.php';

// Simular sesión para maqueta
if (!isset($_SESSION['usuario_id'])) {
    $_SESSION['usuario_id'] = 1;
    $_SESSION['usuario_nombre'] = 'Carlos Muñoz';
    $_SESSION['usuario_email'] = 'carlos@email.com';
    $_SESSION['es_admin'] = 0;
}

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

include 'includes/header.php';
?>

<style>
/* Estilos específicos del formulario de solicitud */
.solicitud-container {
    max-width: 900px;
    margin: 0 auto;
    padding: 28px 20px;
}

.solicitud-header {
    margin-bottom: 28px;
}

.solicitud-header h1 {
    font-family: var(--font-heading);
    font-size: 1.6rem;
    font-weight: 700;
    color: var(--color-gray-900);
    display: flex;
    align-items: center;
    gap: 10px;
}

.solicitud-header h1 i {
    color: var(--color-primary);
}

.solicitud-header p {
    color: var(--color-gray-600);
    font-size: 0.9rem;
    margin-top: 4px;
}

/* Secciones del formulario */
.form-section {
    background: var(--color-white);
    border-radius: var(--radius-md);
    box-shadow: var(--shadow-sm);
    border: 1px solid var(--color-gray-200);
    margin-bottom: 24px;
    overflow: hidden;
}

.form-section-header {
    background: var(--color-primary-bg);
    padding: 16px 24px;
    border-bottom: 2px solid var(--color-primary);
    display: flex;
    align-items: center;
    gap: 12px;
}

.form-section-header .section-number {
    width: 32px;
    height: 32px;
    background: var(--color-primary);
    color: var(--color-white);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 700;
    font-size: 0.9rem;
    font-family: var(--font-heading);
    flex-shrink: 0;
}

.form-section-header h2 {
    font-family: var(--font-heading);
    font-size: 1.05rem;
    font-weight: 600;
    color: var(--color-gray-900);
}

.form-section-body {
    padding: 24px;
}

/* Drop Zone para archivos */
.drop-zone {
    border: 2px dashed var(--color-gray-400);
    border-radius: var(--radius-md);
    padding: 32px;
    text-align: center;
    cursor: pointer;
    transition: all var(--transition-fast);
    background: var(--color-gray-100);
}

.drop-zone:hover, .drop-zone.dragover {
    border-color: var(--color-primary);
    background: var(--color-primary-bg);
}

.drop-zone .drop-icon {
    font-size: 2.5rem;
    color: var(--color-primary);
    margin-bottom: 8px;
}

.drop-zone .drop-text {
    font-size: 0.9rem;
    color: var(--color-gray-600);
}

.drop-zone .drop-hint {
    font-size: 0.78rem;
    color: var(--color-gray-500);
    margin-top: 4px;
}

/* Lista de archivos */
.file-list {
    margin-top: 16px;
    display: flex;
    flex-direction: column;
    gap: 8px;
}

.file-item {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 10px 14px;
    background: var(--color-gray-100);
    border-radius: var(--radius-sm);
    border: 1px solid var(--color-gray-200);
    font-size: 0.85rem;
}

.file-item i {
    color: var(--color-primary);
    font-size: 1.1rem;
}

.file-item .file-name {
    flex: 1;
    font-weight: 500;
    color: var(--color-gray-800);
}

.file-item .file-size {
    color: var(--color-gray-500);
    font-size: 0.78rem;
}

.file-item .file-remove {
    background: none;
    border: none;
    color: var(--color-danger);
    cursor: pointer;
    padding: 4px;
    font-size: 0.9rem;
}

.file-item .file-remove:hover {
    color: #a71d2a;
}

/* Días disponibles */
.days-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
    gap: 12px;
}

.day-card {
    background: var(--color-gray-100);
    border: 1px solid var(--color-gray-200);
    border-radius: var(--radius-sm);
    padding: 14px;
    transition: all var(--transition-fast);
}

.day-card:hover {
    border-color: var(--color-primary);
}

.day-card .day-name {
    font-family: var(--font-heading);
    font-weight: 600;
    font-size: 0.9rem;
    margin-bottom: 8px;
    color: var(--color-gray-800);
    display: flex;
    align-items: center;
    justify-content: space-between;
}

.day-card .day-toggle {
    font-size: 0.7rem;
    color: var(--color-primary);
    cursor: pointer;
    font-weight: 500;
}

.day-card .day-toggle:hover {
    text-decoration: underline;
}

.day-card .day-options {
    display: flex;
    gap: 16px;
}

.day-card .day-options label {
    display: flex;
    align-items: center;
    gap: 6px;
    font-size: 0.82rem;
    font-weight: 400;
    cursor: pointer;
    color: var(--color-gray-700);
}

.day-card .day-options input[type="checkbox"] {
    accent-color: var(--color-primary);
}

/* Navegación del formulario */
.form-navigation {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-top: 24px;
    padding-top: 24px;
    border-top: 1px solid var(--color-gray-200);
}

/* Responsive */
@media (max-width: 768px) {
    .days-grid {
        grid-template-columns: 1fr 1fr;
    }
}

@media (max-width: 576px) {
    .days-grid {
        grid-template-columns: 1fr;
    }
    
    .form-section-body {
        padding: 16px;
    }
    
    .form-navigation {
        flex-direction: column;
        gap: 12px;
    }
    
    .form-navigation .btn {
        width: 100%;
    }
}
</style>

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
                <div class="form-row">
                    <div class="form-group">
                        <label for="rut">RUT/DNI <span class="required">*</span></label>
                        <input type="text" id="rut" name="rut" class="form-control" placeholder="12.345.678-9" value="12.345.678-9">
                    </div>
                    <div class="form-group">
                        <label for="nombre">Nombre Completo <span class="required">*</span></label>
                        <input type="text" id="nombre" name="nombre" class="form-control" placeholder="Nombre y apellidos" value="Carlos Muñoz R.">
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label for="email">Correo Electrónico <span class="required">*</span></label>
                        <input type="email" id="email" name="email" class="form-control" placeholder="correo@ejemplo.com" value="carlos@email.com">
                    </div>
                    <div class="form-group">
                        <label for="telefono">Teléfono <span class="required">*</span></label>
                        <input type="tel" id="telefono" name="telefono" class="form-control" placeholder="+56 9 XXXX XXXX" value="+56 9 1234 5678">
                    </div>
                </div>
                <div class="form-group">
                    <label for="rol">Rol <span class="required">*</span></label>
                    <select id="rol" name="rol" class="form-control">
                        <option value="">Seleccione su rol...</option>
                        <option value="propietario">Propietario / Residente</option>
                        <option value="administrador">Administrador del Edificio</option>
                    </select>
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
                <!-- Campos para Propietario -->
                <div id="propietario-fields" style="display:none;">
                    <div class="form-group">
                        <label for="departamento">N° de Departamento <span class="required">*</span></label>
                        <select id="departamento" name="departamento" class="form-control">
                            <option value="">Seleccione su departamento...</option>
                            <?php foreach ($departamentos as $val => $label): ?>
                            <option value="<?php echo $val; ?>"><?php echo $label; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label for="estacionamiento">Estacionamiento</label>
                            <select id="estacionamiento" name="estacionamiento" class="form-control">
                                <option value="">No aplica / Sin estacionamiento</option>
                                <?php foreach ($estacionamientos as $val => $label): ?>
                                <option value="<?php echo $val; ?>"><?php echo $label; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="bodega">Bodega</label>
                            <select id="bodega" name="bodega" class="form-control">
                                <option value="">No aplica / Sin bodega</option>
                                <?php foreach ($bodegas as $val => $label): ?>
                                <option value="<?php echo $val; ?>"><?php echo $label; ?></option>
                                <?php endforeach; ?>
                            </select>
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
                        <div class="drop-hint">Formatos permitidos: JPG, PNG, GIF, WEBP, MP4, WEBM</div>
                    </div>
                    <input type="file" id="fileInput" name="archivos[]" multiple accept="image/*,video/*" style="display:none;">
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
                <p class="text-muted mb-3">Seleccione los días y horarios en que estaría disponible para recibir la visita técnica. <strong>Debe seleccionar al menos un bloque.</strong></p>
                
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

<?php include 'includes/footer.php'; ?>
