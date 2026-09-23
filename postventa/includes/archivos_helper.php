<?php
/**
 * Helper de archivos adjuntos - Postventa Centinela
 *
 * Consultas y utilidades sobre la tabla icentpventaarchivos.
 * Separado de la API para poder reutilizarlo desde las páginas
 * del sitio sin ejecutar el router de la API.
 */

require_once __DIR__ . '/db_helper.php';

/**
 * Devuelve la URL base de la aplicación Postventa (termina en '/'),
 * es decir, la carpeta que contiene mis-solicitudes.php, api/, etc.
 *
 * No se puede usar BASE_URL directamente porque se define con
 * dirname(SCRIPT_NAME): cuando el script es postventa/api/solicitudes.php
 * BASE_URL apuesta a postventa/api/ y produciría rutas duplicadas
 * tipo postventa/api/api/solicitudes.php.
 *
 * @return string
 */
function baseUrlPostventa() {
    $script = isset($_SERVER['SCRIPT_NAME']) ? $_SERVER['SCRIPT_NAME'] : '';
    if ($script === '') {
        return '';
    }
    $dir = str_replace('\\', '/', dirname($script));
    // Si el script corre desde /api/, subir un nivel hasta la raíz de postventa
    if (basename($dir) === 'api') {
        $dir = dirname($dir);
    }
    return rtrim($dir, '/') . '/';
}

/**
 * Devuelve los archivos adjuntos de una solicitud, ya con URLs de
 * visualización/descarga, tamaño y fecha formateados, y banderas de
 * tipo de vista previa (imagen / video).
 *
 * @param mysqli      $db
 * @param int         $solicitudId
 * @param string|null $baseUrl      Base URL opcional. Si es null se calcula
 *                                  automáticamente con baseUrlPostventa().
 * @return array
 */
function obtenerArchivos($db, $solicitudId, $baseUrl = null) {
    if ($baseUrl === null) {
        $baseUrl = baseUrlPostventa();
    }

    $stmt = dbPrepare($db, "SELECT id, nombre_original, nombre_archivo, tipo, tamano, ruta, created_at
                            FROM icentpventaarchivos WHERE solicitud_id = ? ORDER BY id ASC");
    $stmt->bind_param('i', $solicitudId);
    $stmt->execute();
    $result = $stmt->get_result();

    $archivos = array();
    while ($row = $result->fetch_assoc()) {
        $ext = strtolower(pathinfo($row['nombre_original'], PATHINFO_EXTENSION));
        if ($ext === '') {
            $ext = strtolower(pathinfo($row['nombre_archivo'], PATHINFO_EXTENSION));
        }
        // Clasificar por extensión: la columna 'tipo' solo distingue imagen/video
        $esImagen = in_array($ext, array('jpg','jpeg','png','gif','webp','bmp','svg','ico'), true);
        $esVideo  = in_array($ext, array('mp4','webm','ogg','mov','avi','mkv','m4v'), true);

        $row['extension']    = $ext;
        $row['es_imagen']    = $esImagen;
        $row['es_video']     = $esVideo;
        $row['es_preview']   = $esImagen || $esVideo;
        $row['url']          = $baseUrl . 'api/solicitudes.php?action=descargar&archivo_id=' . (int)$row['id'];
        $row['tamano_texto'] = formatTamano($row['tamano']);
        $row['fecha_texto']  = date('d/m/Y H:i', strtotime($row['created_at']));
        $archivos[] = $row;
    }
    return $archivos;
}

/**
 * Cuenta los archivos adjuntos de una solicitud.
 *
 * @param mysqli $db
 * @param int    $solicitudId
 * @return int
 */
function contarArchivos($db, $solicitudId) {
    $stmt = dbPrepare($db, "SELECT COUNT(*) AS total FROM icentpventaarchivos WHERE solicitud_id = ?");
    $stmt->bind_param('i', $solicitudId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    return isset($row['total']) ? (int)$row['total'] : 0;
}

/**
 * Devuelve el conteo de archivos agrupado por solicitud:
 * array(solicitud_id => total). Útil para listar muchas solicitudes
 * en una sola consulta (evita N+1).
 *
 * @param mysqli $db
 * @return array
 */
function contarArchivosPorSolicitud($db) {
    $result = dbQuery($db, "SELECT solicitud_id, COUNT(*) AS total FROM icentpventaarchivos GROUP BY solicitud_id");
    $mapa = array();
    while ($row = $result->fetch_assoc()) {
        $mapa[(int)$row['solicitud_id']] = (int)$row['total'];
    }
    return $mapa;
}

/**
 * Formatea bytes a una representación legible (B, KB, MB, GB).
 *
 * @param int|float $bytes
 * @return string
 */
function formatTamano($bytes) {
    $bytes = (float)$bytes;
    if ($bytes <= 0) return '0 B';
    $unidades = array('B', 'KB', 'MB', 'GB', 'TB');
    $i = (int)floor(log($bytes, 1024));
    $i = min($i, count($unidades) - 1);
    return round($bytes / pow(1024, $i), 1) . ' ' . $unidades[$i];
}
