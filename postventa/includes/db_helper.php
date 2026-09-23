<?php
/**
 * Helpers de base de datos - Postventa Centinela
 *
 * Motivo: mysqli::prepare() y mysqli::query() devuelven `false` cuando MySQL
 * rechaza la sentencia (tabla inexistente, columna faltante, permisos, etc.).
 * Llamar ->bind_param() / ->fetch_assoc() sobre `false` lanza una excepción
 * fatal sin que quede registrado el motivo real en el log.
 *
 * Estos helpers loguean $db->error y responden 500 con JSON, en vez de fallar
 * en cascada.
 *
 * Uso:
 *   $stmt = dbPrepare($db, "SELECT ... WHERE id = ?");
 *   $res  = dbQuery($db, "SELECT ...");
 */

require_once __DIR__ . '/logger.php';

/**
 * Prepara una consulta validando que prepare() no falle.
 *
 * @param mysqli $db
 * @param string $sql
 * @return mysqli_stmt
 */
function dbPrepare($db, $sql) {
    $stmt = $db->prepare($sql);
    if (!$stmt) {
        dbFail($db, 'Error al preparar consulta', $sql);
    }
    return $stmt;
}

/**
 * Ejecuta una consulta sin placeholders validando que query() no falle.
 *
 * @param mysqli $db
 * @param string $sql
 * @return mysqli_result|bool
 */
function dbQuery($db, $sql) {
    $result = $db->query($sql);
    if ($result === false) {
        dbFail($db, 'Error al ejecutar consulta', $sql);
    }
    return $result;
}

/**
 * Registra el error real de MySQL y termina con una respuesta JSON 500.
 * Nunca retorna.
 *
 * @param mysqli   $db
 * @param string   $mensaje
 * @param string   $sql
 * @return void
 */
function dbFail($db, $mensaje, $sql) {
    logger('ERROR', $mensaje . ': ' . $db->error, [
        'sql' => $sql,
        'php' => PHP_VERSION,
        'db'  => defined('DB_NAME') ? DB_NAME : '',
    ]);

    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Error interno del servidor']);
    exit;
}
