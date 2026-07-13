<?php
/**
 * API de Solicitudes - Postventa Centinela
 * Endpoints para crear y gestionar solicitudes de postventa
 */

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/logger.php';

// ==================== MANEJO GLOBAL DE ERRORES ====================
// Capturar TODOS los errores y excepciones para registrarlos en el LOG

// 1. Errores de PHP (warnings, notices, fatales)
set_error_handler(function($severity, $message, $file, $line) {
    $tipos = array(
        E_ERROR             => 'E_ERROR',
        E_WARNING           => 'E_WARNING',
        E_PARSE             => 'E_PARSE',
        E_NOTICE            => 'E_NOTICE',
        E_CORE_ERROR        => 'E_CORE_ERROR',
        E_COMPILE_ERROR     => 'E_COMPILE_ERROR',
        E_USER_ERROR        => 'E_USER_ERROR',
        E_USER_WARNING      => 'E_USER_WARNING',
        E_USER_NOTICE       => 'E_USER_NOTICE',
        E_STRICT            => 'E_STRICT',
        E_RECOVERABLE_ERROR => 'E_RECOVERABLE_ERROR',
        E_DEPRECATED        => 'E_DEPRECATED',
    );
    $tipo = isset($tipos[$severity]) ? $tipos[$severity] : "E_$severity";
    
    logger('ERROR', "PHP $tipo: $message", [
        'file' => $file,
        'line' => $line,
        'severity' => $severity
    ]);
    
    // Si es fatal, devolver false para que PHP lo maneje normalmente
    if (in_array($severity, array(E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR, E_RECOVERABLE_ERROR))) {
        return false;
    }
    return true;
});

// 2. Excepciones no capturadas
set_exception_handler(function($exception) {
    logger('ERROR', 'Excepción no capturada: ' . $exception->getMessage(), [
        'file'  => $exception->getFile(),
        'line'  => $exception->getLine(),
        'trace' => $exception->getTraceAsString()
    ]);
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Error interno del servidor']);
});

// 3. Errores fatales al final del script
register_shutdown_function(function() {
    $error = error_get_last();
    if ($error && in_array($error['type'], array(E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_RECOVERABLE_ERROR))) {
        logger('ERROR', 'Error fatal: ' . $error['message'], [
            'file' => $error['file'],
            'line' => $error['line'],
            'type' => $error['type']
        ]);
        
        // Si no se ha enviado respuesta aún, enviar JSON de error
        if (!headers_sent()) {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Error interno del servidor']);
        }
    }
});

// 4. Desactivar display_errors en producción (para que Xdebug no muestre HTML)
// En desarrollo se puede comentar esta línea para ver errores en pantalla
ini_set('display_errors', '0');
ini_set('display_startup_errors', '0');

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');

// Helper: responder error con logging automático
function apiError($message, $httpCode = 400, $context = array()) {
    logger('WARNING', "API Error ($httpCode): $message", $context);
    http_response_code($httpCode);
    echo json_encode(['success' => false, 'message' => $message]);
    exit;
}

// Seguridad: Referer
$referer = isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : '';
$host = isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : '';
if (!empty($referer) && strpos($referer, $host) === false) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Acceso no autorizado']);
    exit;
}

// Rate limiting
function checkRateLimit($ip, $action, $maxRequests = 10, $windowSeconds = 60) {
    $logFile = __DIR__ . '/../logs/ratelimit_solicitudes.log';
    $now = time();
    $entries = array();
    if (file_exists($logFile)) {
        $lines = file($logFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($lines as $line) {
            $parts = explode('|', $line);
            if (count($parts) >= 3) {
                $entries[] = array('time' => (int)$parts[0], 'ip' => $parts[1], 'action' => $parts[2]);
            }
        }
    }
    $entries = array_filter($entries, function($e) use ($now, $windowSeconds) {
        return ($now - $e['time']) <= $windowSeconds;
    });
    $count = 0;
    foreach ($entries as $e) {
        if ($e['ip'] === $ip && $e['action'] === $action) $count++;
    }
    $entries[] = array('time' => $now, 'ip' => $ip, 'action' => $action);
    $dir = dirname($logFile);
    if (!is_dir($dir)) { mkdir($dir, 0755, true); }
    $newLines = '';
    foreach ($entries as $e) {
        $newLines .= $e['time'] . '|' . $e['ip'] . '|' . $e['action'] . "\n";
    }
    file_put_contents($logFile, $newLines);
    return $count < $maxRequests;
}

$clientIp = isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '0.0.0.0';
$action = isset($_GET['action']) ? $_GET['action'] : '';

// CSRF para POST
$csrfActions = array('crear');
$isLocalCall = ($clientIp === '127.0.0.1' || $clientIp === '::1' || $clientIp === $_SERVER['SERVER_ADDR']);
if (in_array($action, $csrfActions) && $_SERVER['REQUEST_METHOD'] === 'POST' && !$isLocalCall) {
    if (!isset($_SESSION['api_csrf_token'])) {
        $_SESSION['api_csrf_token'] = bin2hex(openssl_random_pseudo_bytes(32));
    }
    $clientToken = isset($_POST['csrf_token']) ? $_POST['csrf_token'] : 
                   (isset($_SERVER['HTTP_X_CSRF_TOKEN']) ? $_SERVER['HTTP_X_CSRF_TOKEN'] : '');
    if ($clientToken !== $_SESSION['api_csrf_token']) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Token de seguridad inválido']);
        exit;
    }
}

switch ($action) {

    // ========== CREAR SOLICITUD ==========
    case 'crear':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            echo json_encode(['success' => false, 'message' => 'Método no permitido']);
            exit;
        }
        if (!isset($_SESSION['usuario_id'])) {
            http_response_code(401);
            echo json_encode(['success' => false, 'message' => 'Debe iniciar sesión']);
            exit;
        }
        
        // El admin_sistema no puede generar solicitudes
        if (isset($_SESSION['usuario_rol']) && $_SESSION['usuario_rol'] === 'admin_sistema') {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'El administrador del sistema no puede generar solicitudes.']);
            exit;
        }
        
        if (!checkRateLimit($clientIp, 'crear', 5, 300)) {
            http_response_code(429);
            echo json_encode(['success' => false, 'message' => 'Demasiadas solicitudes. Espere 5 minutos.']);
            exit;
        }
        
        $usuarioId   = $_SESSION['usuario_id'];
        $rut         = isset($_POST['rut']) ? trim($_POST['rut']) : '';
        $nombre      = isset($_POST['nombre']) ? trim($_POST['nombre']) : '';
        $email       = isset($_POST['email']) ? trim($_POST['email']) : '';
        $telefono    = isset($_POST['telefono']) ? trim($_POST['telefono']) : '';
        $rolSolic   = isset($_POST['rol']) ? $_POST['rol'] : 'propietario';
        $ubicTipo    = isset($_POST['ubicacion_tipo']) ? $_POST['ubicacion_tipo'] : '';
        $ubicValor   = isset($_POST['ubicacion_valor']) ? trim($_POST['ubicacion_valor']) : '';
        $categoria   = isset($_POST['categoria']) ? $_POST['categoria'] : '';
        $subcategoria = isset($_POST['subcategoria']) ? $_POST['subcategoria'] : '';
        $detalle     = isset($_POST['detalle']) ? trim($_POST['detalle']) : '';
        $dias        = isset($_POST['dias']) ? $_POST['dias'] : '';
        
        // Validaciones
        $errors = array();
        if (empty($categoria)) $errors[] = 'Debe seleccionar una categoría';
        if (empty($subcategoria)) $errors[] = 'Debe seleccionar una subcategoría';
        if (empty($ubicTipo)) $errors[] = 'Debe seleccionar una ubicación';
        if (empty($dias)) $errors[] = 'Debe seleccionar al menos un día para visita';
        if (!in_array($rolSolic, array('propietario', 'administrador'))) $errors[] = 'Rol no válido';
        
        if (!empty($errors)) {
            echo json_encode(['success' => false, 'message' => implode('. ', $errors)]);
            exit;
        }
        
        // Mapear categorías a labels legibles
        $catLabels = array(
            'estructural'   => 'Fallas Estructurales/Estéticas',
            'instalaciones' => 'Instalaciones (Gas/Agua/Luz)',
            'terminaciones' => 'Terminaciones'
        );
        $categoriaLabel = isset($catLabels[$categoria]) ? $catLabels[$categoria] : $categoria;
        
        // Mapear subcategorías
        $subcatLabels = array(
            'fisuras'              => 'Fisuras en muros o losa',
            'pintura'              => 'Desprendimiento de pintura',
            'desprendimientos'     => 'Desprendimientos de revestimiento',
            'humedad'              => 'Humedad en muros o cielos',
            'otro_estructural'     => 'Otra falla estructural',
            'filtraciones'         => 'Filtraciones de agua',
            'electricidad'         => 'Cortocircuitos / falla eléctrica',
            'presion_agua'         => 'Falta de presión de agua',
            'calefaccion'          => 'Problemas de calefacción',
            'gas'                  => 'Fuga o problema de gas',
            'otro_instalaciones'   => 'Otra falla de instalaciones',
            'puertas'              => 'Puertas descuadradas o que no cierran',
            'ventanas'             => 'Ventanas que no cierran / filtran',
            'pisos'                => 'Pisos flotantes levantados / dañados',
            'ceramica'             => 'Cerámica suelta o quebrada',
            'muebles'              => 'Muebles de cocina/baño dañados',
            'otro_terminaciones'   => 'Otra falla de terminaciones'
        );
        $subcategoriaLabel = isset($subcatLabels[$subcategoria]) ? $subcatLabels[$subcategoria] : $subcategoria;
        
        $db = getDB();
        $stmt = $db->prepare(
            "INSERT INTO icentPventaSolicitudes 
             (usuario_id, rut, nombre, email, telefono, rol_solicitante, ubicacion_tipo, ubicacion_valor, 
              categoria, subcategoria, detalle, dias_disponibles, estado, created_at) 
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pendiente', NOW())"
        );
        $stmt->bind_param('isssssssssss', 
            $usuarioId, $rut, $nombre, $email, $telefono, $rolSolic, 
            $ubicTipo, $ubicValor, $categoriaLabel, $subcategoriaLabel, 
            $detalle, $dias
        );
        
        if ($stmt->execute()) {
            $solicitudId = $db->insert_id;
            
            // Insertar seguimiento inicial
            $seg = $db->prepare("INSERT INTO icentPventaSeguimiento (solicitud_id, usuario_id, comentario, tipo, created_at) VALUES (?, ?, 'Solicitud ingresada al sistema.', 'sistema', NOW())");
            $seg->bind_param('ii', $solicitudId, $usuarioId);
            $seg->execute();
            
            // Procesar archivos adjuntos
            $archivosSubidos = 0;
            
            logger('DEBUG', 'Procesando archivos adjuntos', [
                'solicitud_id' => $solicitudId,
                'FILES_count' => count($_FILES),
                'FILES_keys' => array_keys($_FILES),
                'CONTENT_TYPE' => isset($_SERVER['CONTENT_TYPE']) ? $_SERVER['CONTENT_TYPE'] : 'no definido'
            ]);
            
            if (!empty($_FILES) && isset($_FILES['archivos'])) {
                $uploadDir = __DIR__ . '/../uploads/' . $solicitudId . '/';
                if (!is_dir($uploadDir)) {
                    if (!mkdir($uploadDir, 0755, true)) {
                        logger('ERROR', 'No se pudo crear directorio de uploads', ['dir' => $uploadDir]);
                    }
                }
                
                $tiposPermitidos = array(
                    'image/jpeg'   => 'imagen',
                    'image/png'    => 'imagen',
                    'image/gif'    => 'imagen',
                    'image/webp'   => 'imagen',
                    'video/mp4'    => 'video',
                    'video/webm'   => 'video'
                );
                $maxSize = 50 * 1024 * 1024;
                
                $files = $_FILES['archivos'];
                $fileCount = is_array($files['name']) ? count($files['name']) : 1;
                
                logger('DEBUG', 'Archivos recibidos', [
                    'fileCount' => $fileCount,
                    'names' => is_array($files['name']) ? $files['name'] : [$files['name']],
                    'sizes' => is_array($files['size']) ? $files['size'] : [$files['size']],
                    'errors' => is_array($files['error']) ? $files['error'] : [$files['error']]
                ]);
                
                for ($i = 0; $i < $fileCount; $i++) {
                    $fileName = is_array($files['name']) ? $files['name'][$i] : $files['name'];
                    $fileTmp  = is_array($files['tmp_name']) ? $files['tmp_name'][$i] : $files['tmp_name'];
                    $fileSize = is_array($files['size']) ? $files['size'][$i] : $files['size'];
                    $fileType = is_array($files['type']) ? $files['type'][$i] : $files['type'];
                    $fileError = is_array($files['error']) ? $files['error'][$i] : $files['error'];
                    
                    logger('DEBUG', "Procesando archivo $i", [
                        'name' => $fileName, 'tmp' => $fileTmp, 'size' => $fileSize,
                        'type' => $fileType, 'error' => $fileError
                    ]);
                    
                    if ($fileError !== UPLOAD_ERR_OK || empty($fileTmp)) {
                        logger('WARNING', "Archivo $i con error o sin tmp_name", ['error' => $fileError, 'tmp' => $fileTmp]);
                        continue;
                    }
                    if ($fileSize > $maxSize) {
                        logger('WARNING', "Archivo $i excede tamaño máximo", ['size' => $fileSize, 'max' => $maxSize]);
                        continue;
                    }
                    if (!isset($tiposPermitidos[$fileType])) {
                        logger('WARNING', "Archivo $i tipo no permitido", ['type' => $fileType]);
                        continue;
                    }
                    
                    $ext = pathinfo($fileName, PATHINFO_EXTENSION);
                    if (empty($ext)) $ext = 'bin';
                    $uniqueName = uniqid('ev_') . '.' . $ext;
                    $destPath = $uploadDir . $uniqueName;
                    
                    if (move_uploaded_file($fileTmp, $destPath)) {
                        $tipo = $tiposPermitidos[$fileType];
                        $rutaRel = 'uploads/' . $solicitudId . '/' . $uniqueName;
                        
                        $archStmt = $db->prepare("INSERT INTO icentPventaArchivos (solicitud_id, nombre_original, nombre_archivo, tipo, tamano, ruta, created_at) VALUES (?, ?, ?, ?, ?, ?, NOW())");
                        $archStmt->bind_param('isssis', $solicitudId, $fileName, $uniqueName, $tipo, $fileSize, $rutaRel);
                        
                        if ($archStmt->execute()) {
                            $archivosSubidos++;
                            logger('INFO', "Archivo guardado: $uniqueName", ['solicitud_id' => $solicitudId, 'original' => $fileName, 'ruta' => $rutaRel]);
                        } else {
                            logger('ERROR', "Error al insertar archivo en BD", ['error' => $db->error, 'file' => $fileName]);
                        }
                    } else {
                        logger('ERROR', "No se pudo mover archivo upload", ['from' => $fileTmp, 'to' => $destPath]);
                    }
                }
            } else {
                logger('DEBUG', 'No se recibieron archivos (FILES vacío o sin clave "archivos")', [
                    'FILES_empty' => empty($_FILES) ? 'si' : 'no',
                    'FILES_has_archivos' => isset($_FILES['archivos']) ? 'si' : 'no'
                ]);
            }
            
            logger('INFO', "Solicitud #$solicitudId creada", ['archivos' => $archivosSubidos]);
            
            echo json_encode([
                'success'  => true,
                'message'  => 'Solicitud registrada exitosamente' . ($archivosSubidos > 0 ? ' (' . $archivosSubidos . ' archivo(s) adjunto(s))' : ''),
                'id'       => $solicitudId,
                'archivos' => $archivosSubidos,
                'redirect' => 'dashboard.php?success=1'
            ]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Error al registrar la solicitud: ' . $db->error]);
        }
        break;

    // ========== LISTAR SOLICITUDES DEL USUARIO ==========
    case 'mis_solicitudes':
        if (!isset($_SESSION['usuario_id'])) {
            http_response_code(401);
            echo json_encode(['success' => false, 'message' => 'Debe iniciar sesión']);
            exit;
        }
        
        $db = getDB();
        $stmt = $db->prepare(
            "SELECT id, created_at, categoria, subcategoria, ubicacion_valor, estado, 
                    fecha_agendamiento, equipo_asignado, detalle, dias_disponibles
             FROM icentPventaSolicitudes 
             WHERE usuario_id = ? 
             ORDER BY created_at DESC"
        );
        $stmt->bind_param('i', $_SESSION['usuario_id']);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $solicitudes = array();
        while ($row = $result->fetch_assoc()) {
            $solicitudes[] = $row;
        }
        
        echo json_encode(['success' => true, 'solicitudes' => $solicitudes]);
        break;

    // ========== LISTAR TODAS LAS SOLICITUDES (admin_sistema) ==========
    case 'todas':
        if (!isset($_SESSION['usuario_id']) || !isset($_SESSION['usuario_rol']) || $_SESSION['usuario_rol'] !== 'admin_sistema') {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'Acceso denegado']);
            exit;
        }
        
        $db = getDB();
        $result = $db->query(
            "SELECT s.id, s.created_at, s.rut, s.nombre, s.email, s.telefono, s.rol_solicitante,
                    s.ubicacion_valor, s.categoria, s.subcategoria, s.estado, s.detalle,
                    s.dias_disponibles, s.fecha_agendamiento, s.equipo_asignado,
                    u.nombre as nombre_usuario
             FROM icentPventaSolicitudes s
             LEFT JOIN icentPventaUsuarios u ON s.usuario_id = u.id
             ORDER BY s.created_at DESC"
        );
        
        $solicitudes = array();
        while ($row = $result->fetch_assoc()) {
            $solicitudes[] = $row;
        }
        
        // Estadísticas globales
        $statsResult = $db->query(
            "SELECT 
                COUNT(*) as total,
                SUM(CASE WHEN estado = 'pendiente' THEN 1 ELSE 0 END) as pendientes,
                SUM(CASE WHEN estado IN ('aprobado','agendado','en_proceso') THEN 1 ELSE 0 END) as en_proceso,
                SUM(CASE WHEN estado = 'resuelto' THEN 1 ELSE 0 END) as resueltos,
                SUM(CASE WHEN estado = 'no_corresponde' THEN 1 ELSE 0 END) as no_corresponde
             FROM icentPventaSolicitudes"
        );
        $stats = $statsResult->fetch_assoc();
        
        echo json_encode([
            'success'     => true,
            'solicitudes' => $solicitudes,
            'stats'       => $stats
        ]);
        break;

    // ========== DETALLE DE SOLICITUD ==========
    case 'detalle':
        if (!isset($_SESSION['usuario_id'])) {
            http_response_code(401);
            echo json_encode(['success' => false, 'message' => 'Debe iniciar sesión']);
            exit;
        }
        
        $solicitudId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
        if ($solicitudId <= 0) {
            echo json_encode(['success' => false, 'message' => 'ID de solicitud inválido']);
            exit;
        }
        
        $db = getDB();
        $stmt = $db->prepare(
            "SELECT s.*, u.nombre as nombre_usuario 
             FROM icentPventaSolicitudes s 
             LEFT JOIN icentPventaUsuarios u ON s.usuario_id = u.id 
             WHERE s.id = ? LIMIT 1"
        );
        $stmt->bind_param('i', $solicitudId);
        $stmt->execute();
        $solicitud = $stmt->get_result()->fetch_assoc();
        
        if (!$solicitud) {
            echo json_encode(['success' => false, 'message' => 'Solicitud no encontrada']);
            exit;
        }
        
        // Verificar que el usuario tenga acceso (admin o dueño)
        $esAdmin = (isset($_SESSION['usuario_rol']) && $_SESSION['usuario_rol'] === 'admin_sistema');
        if (!$esAdmin && $solicitud['usuario_id'] != $_SESSION['usuario_id']) {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'Acceso denegado']);
            exit;
        }
        
        // Obtener seguimiento
        $seg = $db->prepare("SELECT * FROM icentPventaSeguimiento WHERE solicitud_id = ? ORDER BY created_at ASC");
        $seg->bind_param('i', $solicitudId);
        $seg->execute();
        $seguimiento = array();
        $segResult = $seg->get_result();
        while ($row = $segResult->fetch_assoc()) {
            $seguimiento[] = $row;
        }
        
        echo json_encode([
            'success'     => true,
            'solicitud'   => $solicitud,
            'seguimiento' => $seguimiento
        ]);
        break;

    // ========== APROBAR SOLICITUD Y CREAR CASO EN SIGRO ==========
    case 'aprobar':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            apiError('Método no permitido', 405);
        }
        if (!isset($_SESSION['usuario_id']) || !isset($_SESSION['usuario_rol']) || $_SESSION['usuario_rol'] !== 'admin_sistema') {
            apiError('Acceso denegado', 403);
        }
        
        $solicitudId = isset($_POST['id']) ? (int)$_POST['id'] : 0;
        $comentario  = isset($_POST['comentario']) ? trim($_POST['comentario']) : '';
        
        if ($solicitudId <= 0) {
            apiError('ID de solicitud inválido', 400, ['id' => $_POST['id']]);
        }
        
        $db = getDB();
        
        // Verificar que la solicitud existe y está pendiente
        $check = $db->prepare("SELECT * FROM icentPventaSolicitudes WHERE id = ? AND estado = 'pendiente' LIMIT 1");
        $check->bind_param('i', $solicitudId);
        $check->execute();
        $solicitud = $check->get_result()->fetch_assoc();
        
        if (!$solicitud) {
            apiError('Solicitud no encontrada o ya fue procesada', 404, ['solicitud_id' => $solicitudId]);
        }
        
        // ========== 1. Obtener usuario_id de SIGRO ==========
        $sqlUsuario = "SELECT usuarios.usuario_id FROM usuarios_obras, usuarios 
                       WHERE usuarios_obras.usuario_id = usuarios.usuario_id 
                       AND usuarios.usuario_estado = 0 
                       AND usuarios_obras.obra_id = '" . SIGRO_OBRA_ID . "' 
                       LIMIT 1";
        $resUsuario = $db->query($sqlUsuario);
        
        if (!$resUsuario || $resUsuario->num_rows === 0) {
            logger('ERROR', 'No se encontró usuario SIGRO para la obra', ['obra_id' => SIGRO_OBRA_ID, 'solicitud_id' => $solicitudId]);
            apiError('Error: No se encontró usuario en SIGRO para esta obra.', 500, ['obra_id' => SIGRO_OBRA_ID]);
        }
        $rowUsuario = $resUsuario->fetch_assoc();
        $sigroUsuarioId = $rowUsuario['usuario_id'];
        
        // ========== 2. INSERT en tabla casos ==========
        $detalleCaso = substr($solicitud['ubicacion_valor'] . ' - ' . $solicitud['categoria'] . ' - ' . $solicitud['subcategoria'] . ': ' . $solicitud['detalle'], 0, 500);
        
        // Variables para bind_param (deben ser referencias, no constantes)
        $sigroInmobiliariaId       = SIGRO_INMOBILIARIA_ID;
        $sigroObraId               = SIGRO_OBRA_ID;
        $sigroCategoriaId          = SIGRO_CATEGORIA_ID;
        $sigroCategoriaDetalleId   = SIGRO_CATEGORIA_DETALLE_ID;
        $sigroInmobiliariaUsuarioId = SIGRO_INMOBILIARIA_USUARIO_ID;
        $sigroUsuarioIdRef         = SIGRO_USUARIO_ID;
        
        $stmtCaso = $db->prepare(
            "INSERT INTO casos 
             (caso_padre, caso_automatico, inmobiliaria_id, obra_id, caso_categoria_id, 
              caso_categoria_detalle_id, caso_estado_id, caso_ciclo_id, caso_usuario_id, 
              caso_acceso, caso_detalle, caso_estimado, caso_avance, caso_urgencia, 
              caso_ot_firmada, caso_fecha_creacion, inmobiliaria_usuario_id, caso_origen, 
              usuario_id, caso_icentpventa_id_solicitud) 
             VALUES ('0', '0', ?, ?, ?, ?, '1', '0', ?, 'SI', ?, '0', '0', '0', '0', NOW(), ?, 'P', ?, ?)"
        );
        $stmtCaso->bind_param('ssssssssi',
            $sigroInmobiliariaId,
            $sigroObraId,
            $sigroCategoriaId,
            $sigroCategoriaDetalleId,
            $sigroUsuarioId,
            $detalleCaso,
            $sigroInmobiliariaUsuarioId,
            $sigroUsuarioIdRef,
            $solicitudId
        );
        
        if (!$stmtCaso->execute()) {
            logger('ERROR', 'Error al insertar caso en SIGRO', ['error' => $db->error, 'solicitud_id' => $solicitudId]);
            apiError('Error al crear el caso en SIGRO: ' . $db->error, 500, ['solicitud_id' => $solicitudId]);
        }
        
        $casoId = $db->insert_id;
        logger('INFO', "Caso SIGRO #$casoId creado desde solicitud #$solicitudId");
        
        // ========== 3. Copiar archivos adjuntos al caso SIGRO ==========
        $archivosCopiados = 0;
        $archivos = $db->prepare("SELECT * FROM icentPventaArchivos WHERE solicitud_id = ?");
        $archivos->bind_param('i', $solicitudId);
        $archivos->execute();
        $archivosResult = $archivos->get_result();
        
        while ($archivo = $archivosResult->fetch_assoc()) {
            $origen = __DIR__ . '/../' . $archivo['ruta'];
            $destinoDir = SIGRO_ARCHIVOS_PATH . $casoId . '/';
            
            if (!is_dir($destinoDir)) {
                @mkdir($destinoDir, 0755, true);
            }
            
            $destino = $destinoDir . $archivo['nombre_archivo'];
            
            if (file_exists($origen) && @copy($origen, $destino)) {
                // Insertar comentario en casos_comentarios
                $stmtCom = $db->prepare(
                    "INSERT INTO casos_comentarios 
                     (caso_id, caso_comentario_detalle, caso_comentario_archivo, 
                      caso_comentario_fecha_creacion, usuario_id) 
                     VALUES (?, 'Foto adjuntada a caso', ?, NOW(), ?)"
                );
                $stmtCom->bind_param('isi', $casoId, $archivo['nombre_archivo'], $sigroUsuarioIdRef);
                $stmtCom->execute();
                $archivosCopiados++;
                
                logger('INFO', "Archivo copiado a caso SIGRO #$casoId", [
                    'archivo' => $archivo['nombre_archivo'],
                    'destino' => $destino
                ]);
            } else {
                logger('WARNING', "No se pudo copiar archivo a caso SIGRO", [
                    'origen' => $origen,
                    'destino' => $destino
                ]);
            }
        }
        
        // ========== 4. Actualizar estado de la solicitud (solo si el caso se creó) ==========
        $comentarioAdmin = !empty($comentario) ? $comentario : 'Aprobado. Caso SIGRO #' . $casoId . ' creado.';
        $update = $db->prepare("UPDATE icentPventaSolicitudes SET estado = 'aprobado', comentario_admin = ? WHERE id = ?");
        $update->bind_param('si', $comentarioAdmin, $solicitudId);
        $update->execute();
        
        // Insertar seguimiento
        $segComentario = 'Caso aprobado. Se creó caso #' . $casoId . ' en SIGRO.' . ($archivosCopiados > 0 ? ' Se adjuntaron ' . $archivosCopiados . ' archivo(s).' : '');
        $seg = $db->prepare("INSERT INTO icentPventaSeguimiento (solicitud_id, usuario_id, comentario, tipo, created_at) VALUES (?, ?, ?, 'admin', NOW())");
        $seg->bind_param('iis', $solicitudId, $_SESSION['usuario_id'], $segComentario);
        $seg->execute();
        
        logger('INFO', "Solicitud #$solicitudId aprobada", [
            'caso_sigro_id' => $casoId,
            'archivos_copiados' => $archivosCopiados
        ]);
        
        echo json_encode([
            'success'           => true,
            'message'           => 'Solicitud aprobada. Caso SIGRO #' . $casoId . ' creado correctamente.',
            'caso_sigro_id'     => $casoId,
            'archivos_copiados' => $archivosCopiados
        ]);
        break;

    // ========== RECHAZAR SOLICITUD ==========
    case 'rechazar':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            apiError('Método no permitido', 405);
        }
        if (!isset($_SESSION['usuario_id']) || !isset($_SESSION['usuario_rol']) || $_SESSION['usuario_rol'] !== 'admin_sistema') {
            apiError('Acceso denegado', 403);
        }
        
        $solicitudId = isset($_POST['id']) ? (int)$_POST['id'] : 0;
        if ($solicitudId <= 0) {
            apiError('ID de solicitud inválido', 400);
        }
        
        $db = getDB();
        $check = $db->prepare("SELECT * FROM icentPventaSolicitudes WHERE id = ? AND estado = 'pendiente' LIMIT 1");
        $check->bind_param('i', $solicitudId);
        $check->execute();
        if ($check->get_result()->num_rows === 0) {
            apiError('Solicitud no encontrada o ya fue procesada', 404, ['solicitud_id' => $solicitudId]);
        }
        
        $update = $db->prepare("UPDATE icentPventaSolicitudes SET estado = 'no_corresponde' WHERE id = ?");
        $update->bind_param('i', $solicitudId);
        $update->execute();
        
        $seg = $db->prepare("INSERT INTO icentPventaSeguimiento (solicitud_id, usuario_id, comentario, tipo, created_at) VALUES (?, ?, 'Caso rechazado. No corresponde a postventa.', 'admin', NOW())");
        $seg->bind_param('ii', $solicitudId, $_SESSION['usuario_id']);
        $seg->execute();
        
        logger('INFO', "Solicitud #$solicitudId rechazada (no corresponde)");
        
        echo json_encode([
            'success' => true,
            'message' => 'Caso #' . $solicitudId . ' marcado como "No Corresponde".'
        ]);
        break;

    // ========== LISTAR ARCHIVOS DE UNA SOLICITUD ==========
    case 'archivos':
        if (!isset($_SESSION['usuario_id'])) {
            apiError('Debe iniciar sesión', 401);
        }
        
        $solicitudId = isset($_GET['solicitud_id']) ? (int)$_GET['solicitud_id'] : 0;
        if ($solicitudId <= 0) {
            apiError('ID de solicitud inválido', 400);
        }
        
        $db = getDB();
        $stmt = $db->prepare("SELECT * FROM icentPventaArchivos WHERE solicitud_id = ? ORDER BY id ASC");
        $stmt->bind_param('i', $solicitudId);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $archivos = array();
        while ($row = $result->fetch_assoc()) {
            $archivos[] = $row;
        }
        
        echo json_encode(['success' => true, 'archivos' => $archivos]);
        break;

    default:
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Acción no válida']);
        break;
}
