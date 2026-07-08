<?php
/**
 * API de Solicitudes - Postventa Centinela
 * Endpoints para crear y gestionar solicitudes de postventa
 */

require_once __DIR__ . '/../includes/config.php';

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');

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
            
            echo json_encode([
                'success'  => true,
                'message'  => 'Solicitud registrada exitosamente',
                'id'       => $solicitudId,
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

    default:
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Acción no válida']);
        break;
}
