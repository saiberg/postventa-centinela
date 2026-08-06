<?php
/**
 * API de Solicitudes - Postventa Centinela
 * Endpoints para crear y gestionar solicitudes de postventa
 */

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/logger.php';
require_once __DIR__ . '/../includes/email_helper.php';

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
        $ubicValor   = isset($_POST['ubicacion_valor']) ? trim($_POST['ubicacion_valor']) : '';
        $categoria   = isset($_POST['categoria']) ? $_POST['categoria'] : '';
        $subcategoria = isset($_POST['subcategoria']) ? $_POST['subcategoria'] : '';
        $detalle     = isset($_POST['detalle']) ? trim($_POST['detalle']) : '';
        $dias        = isset($_POST['dias']) ? $_POST['dias'] : '';
        $obraId      = isset($_POST['obra_id']) ? (int)$_POST['obra_id'] : 0;
        $edificioId  = isset($_POST['edificio_id']) ? (int)$_POST['edificio_id'] : 0;
        $pisoId      = isset($_POST['piso_id']) ? (int)$_POST['piso_id'] : 0;
        $deptoId     = isset($_POST['departamento_id']) ? (int)$_POST['departamento_id'] : 0;
        
        // Validaciones
        $errors = array();
        if (empty($categoria)) $errors[] = 'Debe seleccionar una categoría';
        if ($categoria !== 'otro' && empty($subcategoria)) $errors[] = 'Debe seleccionar una subcategoría';
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
            "INSERT INTO icentpventasolicitudes 
             (usuario_id, ubicacion_valor, 
              categoria, subcategoria, detalle, dias_disponibles, estado, obra_id, edificio_id, piso_id, departamento_id, created_at) 
             VALUES (?, ?, ?, ?, ?, ?, 'pendiente', ?, ?, ?, ?, NOW())"
        );
        $stmt->bind_param('isssssiiii', 
            $usuarioId, 
            $ubicValor, $categoriaLabel, $subcategoriaLabel, 
            $detalle, $dias, $obraId, $edificioId, $pisoId, $deptoId
        );
        
        if ($stmt->execute()) {
            $solicitudId = $db->insert_id;
            
            // Insertar seguimiento inicial
            $seg = $db->prepare("INSERT INTO icentpventaseguimiento (solicitud_id, usuario_id, comentario, tipo, created_at) VALUES (?, ?, 'Solicitud ingresada al sistema.', 'sistema', NOW())");
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
                
                $tiposPermitidos = ALLOWED_FILE_TYPES;
                $maxSize = MAX_FILE_SIZE;
                
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
                    if (!ALLOW_ALL_FORMATS && !isset($tiposPermitidos[$fileType])) {
                        logger('WARNING', "Archivo $i tipo no permitido", ['type' => $fileType]);
                        continue;
                    }
                    
                    $ext = pathinfo($fileName, PATHINFO_EXTENSION);
                    if (empty($ext)) $ext = 'bin';
                    $uniqueName = uniqid('ev_') . '.' . $ext;
                    $destPath = $uploadDir . $uniqueName;
                    
                    if (move_uploaded_file($fileTmp, $destPath)) {
                        $tipo = ALLOW_ALL_FORMATS 
                            ? (strpos($fileType, 'video') === 0 ? 'video' : 'imagen')
                            : $tiposPermitidos[$fileType];
                        $rutaRel = 'uploads/' . $solicitudId . '/' . $uniqueName;
                        
                        $archStmt = $db->prepare("INSERT INTO icentpventaarchivos (solicitud_id, nombre_original, nombre_archivo, tipo, tamano, ruta, created_at) VALUES (?, ?, ?, ?, ?, ?, NOW())");
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
            
            // ========== ENVÍO DE CORREOS ==========
            // Preparar datos para las plantillas de email
            $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https://' : 'http://';
            $host = isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : 'localhost';
            // Construir URL base apuntando a la raíz de postventa (no a api/)
            $urlBase = $protocol . $host . rtrim(dirname(BASE_URL), '/') . '/';
            
            $solicitudFormato = 'PC-' . date('Y') . '-' . str_pad($solicitudId, 3, '0', STR_PAD_LEFT);
            
            $emailData = array(
                'nombre'        => $nombre,
                'rut'           => $rut,
                'email'         => $email,
                'telefono'      => $telefono,
                'solicitud_id'  => $solicitudFormato,
                'categoria'     => $categoriaLabel,
                'subcategoria'  => $subcategoriaLabel,
                'ubicacion'     => $ubicValor,
                'detalle'       => $detalle,
                'dias'          => $dias,
                'fecha'         => date('d/m/Y H:i'),
                'url_base'      => $urlBase,
            );
            
            // Enviar correo al cliente (respaldo de la solicitud)
            $resultadoCliente = enviarCorreoCliente($emailData);
            
            // Enviar correo de notificación al administrador
            $resultadoAdmin = enviarCorreoAdmin($emailData);
            
            $mensajeEmail = '';
            if ($resultadoCliente['success']) {
                $mensajeEmail .= ' Se envió un correo de confirmación.';
            } else {
                logger('WARNING', 'No se pudo enviar correo al cliente', ['error' => $resultadoCliente['message']]);
            }
            if ($resultadoAdmin['success']) {
                $mensajeEmail .= ' Se notificó al administrador.';
            } else {
                logger('WARNING', 'No se pudo enviar correo al administrador', ['error' => $resultadoAdmin['message']]);
            }
            
            echo json_encode([
                'success'  => true,
                'message'  => 'Solicitud registrada exitosamente' . ($archivosSubidos > 0 ? ' (' . $archivosSubidos . ' archivo(s) adjunto(s))' : '') . $mensajeEmail,
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
            "SELECT s.id, s.created_at, s.categoria, s.subcategoria, s.ubicacion_valor, s.estado, 
                    s.detalle, s.dias_disponibles,
                    u.nombre, u.rut, u.email, u.telefono, u.rol as rol_solicitante
             FROM icentpventasolicitudes s
             LEFT JOIN icentpventausuarios u ON s.usuario_id = u.id
             WHERE s.usuario_id = ? 
             ORDER BY s.created_at DESC"
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
            "SELECT s.id, s.created_at,
                    s.ubicacion_valor, s.categoria, s.subcategoria, s.estado, s.detalle,
                    s.dias_disponibles, s.urgencia, s.obra_id,
                    o.obra_nombre,
                    u.id as usuario_id, u.nombre as nombre, u.rut, u.email, u.telefono, u.rol as rol_solicitante
             FROM icentpventasolicitudes s
             LEFT JOIN obras o ON s.obra_id = o.obra_id
             LEFT JOIN icentpventausuarios u ON s.usuario_id = u.id
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
             FROM icentpventasolicitudes"
        );
        $stats = $statsResult->fetch_assoc();
        
        // Distribución por proyecto
        $proyectoResult = $db->query(
            "SELECT o.obra_nombre as proyecto,
                    COUNT(*) as total,
                    SUM(CASE WHEN s.estado = 'pendiente' THEN 1 ELSE 0 END) as pendientes,
                    SUM(CASE WHEN s.estado = 'resuelto' THEN 1 ELSE 0 END) as resueltos,
                    SUM(CASE WHEN s.estado IN ('aprobado','agendado','en_proceso') THEN 1 ELSE 0 END) as abiertos
             FROM icentpventasolicitudes s
             LEFT JOIN obras o ON s.obra_id = o.obra_id
             GROUP BY s.obra_id, o.obra_nombre
             ORDER BY total DESC"
        );
        $porProyecto = array();
        while ($row = $proyectoResult->fetch_assoc()) {
            $row['proyecto'] = $row['proyecto'] ?: 'Sin proyecto';
            $porProyecto[] = $row;
        }
        
        echo json_encode([
            'success'     => true,
            'solicitudes' => $solicitudes,
            'stats'       => $stats,
            'por_proyecto' => $porProyecto
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
            "SELECT s.*, u.nombre, u.rut, u.email, u.telefono, u.rol as rol_solicitante
             FROM icentpventasolicitudes s 
             LEFT JOIN icentpventausuarios u ON s.usuario_id = u.id 
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
        $seg = $db->prepare("SELECT * FROM icentpventaseguimiento WHERE solicitud_id = ? ORDER BY created_at ASC");
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
        $urgencia    = isset($_POST['urgencia']) ? (int)$_POST['urgencia'] : 0;
        $casoCategoriaId = isset($_POST['caso_categoria_id']) ? (int)$_POST['caso_categoria_id'] : 0;
        $casoCategoriaDetalleId = isset($_POST['caso_categoria_detalle_id']) ? (int)$_POST['caso_categoria_detalle_id'] : 0;
        
        // Validar urgencia (0 = normal, 1 = urgente)
        if (!in_array($urgencia, array(0, 1))) {
            $urgencia = 0;
        }
        
        // Validar categorías requeridas
        if ($casoCategoriaId <= 0) {
            apiError('Debe seleccionar una Categoría para aprobar la solicitud.', 400);
        }
        if ($casoCategoriaDetalleId <= 0) {
            apiError('Debe seleccionar un Detalle de categoría para aprobar la solicitud.', 400);
        }
        
        logger('DEBUG', "Aprobar solicitud - urgencia recibida", [
            'solicitud_id' => $solicitudId,
            'POST_urgencia' => isset($_POST['urgencia']) ? $_POST['urgencia'] : 'NO ENVIADO',
            'urgencia_int' => $urgencia
        ]);
        
        if ($solicitudId <= 0) {
            apiError('ID de solicitud inválido', 400, ['id' => $_POST['id']]);
        }
        
        $db = getDB();
        
        // Verificar que la solicitud existe y está pendiente
        $check = $db->prepare(
            "SELECT s.*, u.nombre, u.rut, u.email, u.telefono, u.rol as rol_solicitante
             FROM icentpventasolicitudes s
             LEFT JOIN icentpventausuarios u ON s.usuario_id = u.id
             WHERE s.id = ? AND s.estado = 'pendiente' LIMIT 1"
        );
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
        $detalleCaso = substr($solicitud['detalle'], 0, 500);
        
        // Variables para bind_param (deben ser referencias, no constantes)
        $sigroInmobiliariaId       = SIGRO_INMOBILIARIA_ID;
        $sigroObraId               = SIGRO_OBRA_ID;
        $sigroCategoriaId          = $casoCategoriaId;          // Usar el valor seleccionado por el admin
        $sigroCategoriaDetalleId   = $casoCategoriaDetalleId;  // Usar el valor seleccionado por el admin
        $sigroInmobiliariaUsuarioId = SIGRO_INMOBILIARIA_USUARIO_ID;
        $sigroUsuarioIdRef         = SIGRO_USUARIO_ID;
        
        // Usar el obra_id de la solicitud (el que eligió el usuario), o el de SIGRO como fallback
        $casoObraId = !empty($solicitud['obra_id']) ? $solicitud['obra_id'] : SIGRO_OBRA_ID;
        $casoEdificioId = !empty($solicitud['edificio_id']) ? (int)$solicitud['edificio_id'] : 0;
        $casoPisoId = !empty($solicitud['piso_id']) ? (int)$solicitud['piso_id'] : 0;
        $casoDeptoId = !empty($solicitud['departamento_id']) ? (int)$solicitud['departamento_id'] : 0;
        
        $stmtCaso = $db->prepare(
            "INSERT INTO casos 
             (caso_padre, caso_automatico, inmobiliaria_id, obra_id, edificio_id, piso_id, departamento_id,
              caso_categoria_id, caso_categoria_detalle_id, caso_estado_id, caso_ciclo_id, caso_usuario_id, 
              caso_acceso, caso_detalle, caso_estimado, caso_avance, caso_urgencia, 
              caso_ot_firmada, caso_fecha_creacion, inmobiliaria_usuario_id, caso_origen, 
              usuario_id, caso_icentpventa_id_solicitud) 
             VALUES ('0', '0', ?, ?, ?, ?, ?, ?, ?, '1', '0', ?, 'SI', ?, '0', '0', ?, '0', NOW(), ?, 'P', ?, ?)"
        );
        $stmtCaso->bind_param('sssssssssisii',
            $sigroInmobiliariaId,    // s  → inmobiliaria_id
            $casoObraId,             // s  → obra_id
            $casoEdificioId,         // s  → edificio_id
            $casoPisoId,             // s  → piso_id
            $casoDeptoId,            // s  → departamento_id
            $sigroCategoriaId,       // s  → caso_categoria_id
            $sigroCategoriaDetalleId,// s  → caso_categoria_detalle_id
            $sigroUsuarioId,         // s  → caso_usuario_id
            $detalleCaso,            // s  → caso_detalle
            $urgencia,               // i  → caso_urgencia
            $sigroInmobiliariaUsuarioId, // s → inmobiliaria_usuario_id
            $sigroUsuarioIdRef,      // i  → usuario_id
            $solicitudId             // i  → caso_icentpventa_id_solicitud
        );
        
        if (!$stmtCaso->execute()) {
            logger('ERROR', 'Error al insertar caso en SIGRO', ['error' => $db->error, 'solicitud_id' => $solicitudId]);
            apiError('Error al crear el caso en SIGRO: ' . $db->error, 500, ['solicitud_id' => $solicitudId]);
        }
        
        $casoId = $db->insert_id;
        logger('INFO', "Caso SIGRO #$casoId creado desde solicitud #$solicitudId");
        
        // ========== 3. Copiar archivos adjuntos al caso SIGRO ==========
        $archivosCopiados = 0;
        $archivos = $db->prepare("SELECT * FROM icentpventaarchivos WHERE solicitud_id = ?");
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
        $update = $db->prepare("UPDATE icentpventasolicitudes SET estado = 'aprobado', comentario_admin = ?, urgencia = ?, caso_categoria_id = ?, caso_categoria_detalle_id = ? WHERE id = ?");
        $update->bind_param('siiii', $comentarioAdmin, $urgencia, $casoCategoriaId, $casoCategoriaDetalleId, $solicitudId);
        $update->execute();
        
        logger('DEBUG', "UPDATE solicitud ejecutado", [
            'solicitud_id' => $solicitudId,
            'urgencia' => $urgencia,
            'affected_rows' => $db->affected_rows
        ]);
        
        // Insertar seguimiento
        $segComentario = 'Caso aprobado. Se creó caso #' . $casoId . ' en SIGRO.' . ($archivosCopiados > 0 ? ' Se adjuntaron ' . $archivosCopiados . ' archivo(s).' : '');
        $seg = $db->prepare("INSERT INTO icentpventaseguimiento (solicitud_id, usuario_id, comentario, tipo, created_at) VALUES (?, ?, ?, 'admin', NOW())");
        $seg->bind_param('iis', $solicitudId, $_SESSION['usuario_id'], $segComentario);
        $seg->execute();
        
        logger('INFO', "Solicitud #$solicitudId aprobada", [
            'caso_sigro_id' => $casoId,
            'archivos_copiados' => $archivosCopiados,
            'urgencia' => $urgencia
        ]);
        
        // ========== 5. Enviar correo de notificación al cliente ==========
        $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https://' : 'http://';
        $host = isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : 'localhost';
        $urlBase = $protocol . $host . rtrim(dirname(BASE_URL), '/') . '/';
        $solicitudFormato = 'PC-' . date('Y') . '-' . str_pad($solicitudId, 3, '0', STR_PAD_LEFT);
        
        $emailData = array(
            'nombre'        => $solicitud['nombre'],
            'rut'           => $solicitud['rut'],
            'email'         => $solicitud['email'],
            'telefono'      => $solicitud['telefono'],
            'solicitud_id'  => $solicitudFormato,
            'categoria'     => $solicitud['categoria'],
            'subcategoria'  => $solicitud['subcategoria'],
            'ubicacion'     => $solicitud['ubicacion_valor'],
            'detalle'       => $solicitud['detalle'],
            'dias'          => $solicitud['dias_disponibles'],
            'fecha'         => date('d/m/Y H:i'),
            'url_base'      => $urlBase,
        );
        
        $resultadoEmail = enviarCorreoClienteAprobado($emailData);
        $mensajeEmail = '';
        if ($resultadoEmail['success']) {
            $mensajeEmail = ' Se ha notificado al cliente por correo.';
        } else {
            logger('WARNING', 'No se pudo enviar correo de aprobación al cliente', ['error' => $resultadoEmail['message']]);
        }
        
        echo json_encode([
            'success'           => true,
            'message'           => 'Solicitud aprobada. Caso SIGRO #' . $casoId . ' creado correctamente.' . $mensajeEmail,
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
        $check = $db->prepare(
            "SELECT s.*, u.nombre, u.rut, u.email, u.telefono, u.rol as rol_solicitante
             FROM icentpventasolicitudes s
             LEFT JOIN icentpventausuarios u ON s.usuario_id = u.id
             WHERE s.id = ? AND s.estado = 'pendiente' LIMIT 1"
        );
        $check->bind_param('i', $solicitudId);
        $check->execute();
        $solicitud = $check->get_result()->fetch_assoc();
        if (!$solicitud) {
            apiError('Solicitud no encontrada o ya fue procesada', 404, ['solicitud_id' => $solicitudId]);
        }
        
        $update = $db->prepare("UPDATE icentpventasolicitudes SET estado = 'no_corresponde' WHERE id = ?");
        $update->bind_param('i', $solicitudId);
        $update->execute();
        
        $seg = $db->prepare("INSERT INTO icentpventaseguimiento (solicitud_id, usuario_id, comentario, tipo, created_at) VALUES (?, ?, 'Caso rechazado. No corresponde a postventa.', 'admin', NOW())");
        $seg->bind_param('ii', $solicitudId, $_SESSION['usuario_id']);
        $seg->execute();
        
        logger('INFO', "Solicitud #$solicitudId rechazada (no corresponde)");
        
        // ========== Enviar correo de notificación al cliente ==========
        $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https://' : 'http://';
        $host = isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : 'localhost';
        $urlBase = $protocol . $host . rtrim(dirname(BASE_URL), '/') . '/';
        $solicitudFormato = 'PC-' . date('Y') . '-' . str_pad($solicitudId, 3, '0', STR_PAD_LEFT);
        
        $emailData = array(
            'nombre'        => $solicitud['nombre'],
            'rut'           => $solicitud['rut'],
            'email'         => $solicitud['email'],
            'telefono'      => $solicitud['telefono'],
            'solicitud_id'  => $solicitudFormato,
            'categoria'     => $solicitud['categoria'],
            'subcategoria'  => $solicitud['subcategoria'],
            'ubicacion'     => $solicitud['ubicacion_valor'],
            'detalle'       => $solicitud['detalle'],
            'dias'          => $solicitud['dias_disponibles'],
            'fecha'         => date('d/m/Y H:i'),
            'url_base'      => $urlBase,
        );
        
        $resultadoEmail = enviarCorreoClienteRechazado($emailData);
        $mensajeEmail = '';
        if ($resultadoEmail['success']) {
            $mensajeEmail = ' Se ha notificado al cliente por correo.';
        } else {
            logger('WARNING', 'No se pudo enviar correo de rechazo al cliente', ['error' => $resultadoEmail['message']]);
        }
        
        echo json_encode([
            'success' => true,
            'message' => 'Caso #' . $solicitudId . ' marcado como "No Corresponde".' . $mensajeEmail
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
        $stmt = $db->prepare("SELECT * FROM icentpventaarchivos WHERE solicitud_id = ? ORDER BY id ASC");
        $stmt->bind_param('i', $solicitudId);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $archivos = array();
        while ($row = $result->fetch_assoc()) {
            $archivos[] = $row;
        }
        
        echo json_encode(['success' => true, 'archivos' => $archivos]);
        break;

    // ========== CASCADA: TIPOS EDIFICIO / OBRAS / EDIFICIOS / PISOS / DEPARTAMENTOS ==========
    case 'cascada':
        if (!isset($_SESSION['usuario_id'])) {
            apiError('Debe iniciar sesión', 401);
        }
        
        $tipo = isset($_GET['tipo']) ? $_GET['tipo'] : '';
        $db = getDB();
        
        if ($tipo === 'tipos_edificio') {
            // Obtener todos los tipos de edificio
            $result = $db->query("SELECT edificio_tipo_id, edificio_tipo_nombre FROM edificios_tipos ORDER BY edificio_tipo_nombre ASC");
            $items = array();
            while ($row = $result->fetch_assoc()) {
                $items[] = $row;
            }
            echo json_encode(['success' => true, 'items' => $items]);
            
        } elseif ($tipo === 'obras') {
            $tipoEdificioId = isset($_GET['tipo_edificio_id']) ? (int)$_GET['tipo_edificio_id'] : 0;
            $inmobiliariaId = (int)INMOBILIARIA_ID;
            
            if ($tipoEdificioId > 0) {
                // Obras que tengan edificios del tipo seleccionado
                $stmt = $db->prepare(
                    "SELECT DISTINCT o.obra_id, o.obra_nombre 
                     FROM obras o 
                     INNER JOIN edificios e ON o.obra_id = e.obra_id 
                     WHERE o.inmobiliaria_id = ? AND o.obra_estado_sistema = 1 AND e.edificio_estado = 1 AND e.edificio_tipo_id = ? 
                     ORDER BY o.obra_nombre ASC"
                );
                $stmt->bind_param('ii', $inmobiliariaId, $tipoEdificioId);
            } else {
                // Sin filtro de tipo: todas las obras
                $stmt = $db->prepare("SELECT obra_id, obra_nombre FROM obras WHERE inmobiliaria_id = ? AND obra_estado_sistema = 1 ORDER BY obra_nombre ASC");
                $stmt->bind_param('i', $inmobiliariaId);
            }
            $stmt->execute();
            $result = $stmt->get_result();
            $items = array();
            while ($row = $result->fetch_assoc()) {
                $items[] = $row;
            }
            echo json_encode(['success' => true, 'items' => $items]);
            
        } elseif ($tipo === 'edificios') {
            $obraId = isset($_GET['obra_id']) ? (int)$_GET['obra_id'] : 0;
            if ($obraId <= 0) {
                echo json_encode(['success' => false, 'message' => 'obra_id requerido']);
                break;
            }
            $stmt = $db->prepare("SELECT edificio_id, edificio_nombre FROM edificios WHERE obra_id = ? AND edificio_estado = 1 ORDER BY edificio_nombre ASC");
            $stmt->bind_param('i', $obraId);
            $stmt->execute();
            $result = $stmt->get_result();
            $items = array();
            while ($row = $result->fetch_assoc()) {
                $items[] = $row;
            }
            echo json_encode(['success' => true, 'items' => $items]);
            
        } elseif ($tipo === 'pisos') {
            $edificioId = isset($_GET['edificio_id']) ? (int)$_GET['edificio_id'] : 0;
            if ($edificioId <= 0) {
                echo json_encode(['success' => false, 'message' => 'edificio_id requerido']);
                break;
            }
            $stmt = $db->prepare("SELECT piso_id, piso_nombre FROM pisos WHERE edificio_id = ? ORDER BY piso_nombre ASC");
            $stmt->bind_param('i', $edificioId);
            $stmt->execute();
            $result = $stmt->get_result();
            $items = array();
            while ($row = $result->fetch_assoc()) {
                $items[] = $row;
            }
            echo json_encode(['success' => true, 'items' => $items]);
            
        } elseif ($tipo === 'departamentos') {
            $obraId = isset($_GET['obra_id']) ? (int)$_GET['obra_id'] : 0;
            $edificioId = isset($_GET['edificio_id']) ? (int)$_GET['edificio_id'] : 0;
            
            if ($obraId > 0) {
                // Todos los departamentos de todos los edificios de la obra
                $stmt = $db->prepare(
                    "SELECT d.departamento_id, d.departamento_nombre, d.piso_id, p.edificio_id
                     FROM departamentos d 
                     INNER JOIN pisos p ON d.piso_id = p.piso_id 
                     INNER JOIN edificios e ON p.edificio_id = e.edificio_id 
                     WHERE e.obra_id = ? AND d.departamento_tipo = 'Departamento' AND e.edificio_estado = 1 
                     ORDER BY LOWER(d.departamento_nombre) ASC"
                );
                $stmt->bind_param('i', $obraId);
            } elseif ($edificioId > 0) {
                // Departamentos de un edificio específico (todos los pisos)
                $stmt = $db->prepare(
                    "SELECT d.departamento_id, d.departamento_nombre, d.piso_id, p.edificio_id
                     FROM departamentos d 
                     INNER JOIN pisos p ON d.piso_id = p.piso_id 
                     WHERE p.edificio_id = ? AND d.departamento_tipo = 'Departamento' 
                     ORDER BY p.piso_nombre ASC, LOWER(d.departamento_nombre) ASC"
                );
                $stmt->bind_param('i', $edificioId);
            } else {
                echo json_encode(['success' => false, 'message' => 'obra_id o edificio_id requerido']);
                break;
            }
            $stmt->execute();
            $result = $stmt->get_result();
            $items = array();
            while ($row = $result->fetch_assoc()) {
                $items[] = $row;
            }
            echo json_encode(['success' => true, 'items' => $items]);
            
        } else {
            echo json_encode(['success' => false, 'message' => 'Tipo no válido. Use: tipos_edificio, obras, edificios, pisos, departamentos']);
        }
        break;

    // ========== OBTENER CATEGORÍAS SIGRO (CASCADA) ==========
    case 'categorias':
        if (!isset($_SESSION['usuario_id']) || !isset($_SESSION['usuario_rol']) || $_SESSION['usuario_rol'] !== 'admin_sistema') {
            apiError('Acceso denegado', 403);
        }
        
        $db = getDB();
        $tipo = isset($_GET['tipo']) ? $_GET['tipo'] : 'categorias';
        $categoriaId = isset($_GET['categoria_id']) ? (int)$_GET['categoria_id'] : 0;
        
        if ($tipo === 'categorias') {
            // Obtener todas las categorías padre
            $result = $db->query("SELECT caso_categoria_id, caso_categoria_nombre FROM casos_categorias ORDER BY caso_categoria_nombre ASC");
            $items = array();
            while ($row = $result->fetch_assoc()) {
                $items[] = $row;
            }
            echo json_encode(['success' => true, 'items' => $items]);
            
        } elseif ($tipo === 'detalles' && $categoriaId > 0) {
            // Obtener detalles de una categoría específica
            $stmt = $db->prepare("SELECT caso_categoria_detalle_id, caso_categoria_detalle_nombre FROM casos_categorias_detalles WHERE caso_categoria_id = ? ORDER BY caso_categoria_detalle_nombre ASC");
            $stmt->bind_param('i', $categoriaId);
            $stmt->execute();
            $result = $stmt->get_result();
            $items = array();
            while ($row = $result->fetch_assoc()) {
                $items[] = $row;
            }
            echo json_encode(['success' => true, 'items' => $items]);
            
        } else {
            echo json_encode(['success' => false, 'message' => 'Parámetros inválidos. Use tipo=categorias o tipo=detalles&categoria_id=X']);
        }
        break;

    // ========== REGISTRAR COMUNICACIÓN CON EL CLIENTE ==========
    case 'comunicacion':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            apiError('Método no permitido', 405);
        }
        if (!isset($_SESSION['usuario_id']) || !isset($_SESSION['usuario_rol']) || $_SESSION['usuario_rol'] !== 'admin_sistema') {
            apiError('Acceso denegado', 403);
        }
        
        $solicitudId = isset($_POST['id']) ? (int)$_POST['id'] : 0;
        $comentario  = isset($_POST['comentario']) ? trim($_POST['comentario']) : '';
        $subtipo     = isset($_POST['subtipo']) ? trim($_POST['subtipo']) : 'otro';
        
        // Validar subtipo
        $subtiposValidos = ['whatsapp', 'llamada', 'email', 'presencial', 'otro'];
        if (!in_array($subtipo, $subtiposValidos)) {
            $subtipo = 'otro';
        }
        
        if ($solicitudId <= 0) {
            apiError('ID de solicitud inválido', 400);
        }
        if (empty($comentario)) {
            apiError('Debe ingresar un comentario sobre la comunicación.', 400);
        }
        
        $db = getDB();
        
        // Verificar que la solicitud existe
        $check = $db->prepare("SELECT id FROM icentpventasolicitudes WHERE id = ? LIMIT 1");
        $check->bind_param('i', $solicitudId);
        $check->execute();
        if (!$check->get_result()->fetch_assoc()) {
            apiError('Solicitud no encontrada', 404);
        }
        
        // Guardar el tipo compuesto: "comunicacion:whatsapp", "comunicacion:llamada", etc.
        $tipoCompuesto = 'comunicacion:' . $subtipo;
        
        $seg = $db->prepare("INSERT INTO icentpventaseguimiento (solicitud_id, usuario_id, comentario, tipo, created_at) VALUES (?, ?, ?, ?, NOW())");
        $seg->bind_param('iiss', $solicitudId, $_SESSION['usuario_id'], $comentario, $tipoCompuesto);
        
        if ($seg->execute()) {
            logger('INFO', "Comunicación registrada para solicitud #$solicitudId", [
                'subtipo' => $subtipo,
                'usuario_id' => $_SESSION['usuario_id']
            ]);
            
            echo json_encode([
                'success' => true,
                'message' => 'Comunicación registrada correctamente.',
                'id' => $db->insert_id
            ]);
        } else {
            apiError('Error al registrar la comunicación: ' . $db->error, 500);
        }
        break;

    // ========== LISTAR OBRAS PARA FILTROS ==========
    case 'obras':
        if (!isset($_SESSION['usuario_id']) || !isset($_SESSION['usuario_rol']) || $_SESSION['usuario_rol'] !== 'admin_sistema') {
            apiError('Acceso denegado', 403);
        }
        
        $db = getDB();
        $result = $db->query("SELECT obra_id, obra_nombre FROM obras WHERE inmobiliaria_id = " . (int)INMOBILIARIA_ID . " AND obra_estado_sistema = 1 ORDER BY obra_nombre ASC");
        $obras = array();
        while ($row = $result->fetch_assoc()) {
            $obras[] = $row;
        }
        
        echo json_encode(['success' => true, 'obras' => $obras]);
        break;

    default:
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Acción no válida']);
        break;
}
