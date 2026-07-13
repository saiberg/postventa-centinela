<?php
/**
 * Sistema de Logging - Postventa Centinela
 * 
 * Niveles: DEBUG, INFO, WARNING, ERROR
 * 
 * Uso:
 *   logger('INFO', 'Mensaje', ['data' => $algo]);
 *   logger('ERROR', 'Error al procesar', ['error' => $e->getMessage()]);
 */

define('LOG_LEVEL', 'DEBUG');  // Cambiar a 'ERROR' en producción
define('LOG_DIR', __DIR__ . '/../logs/');

function logger($level, $message, $context = array()) {
    static $levels = array('DEBUG' => 0, 'INFO' => 1, 'WARNING' => 2, 'ERROR' => 3);
    
    $currentLevel = defined('LOG_LEVEL') ? LOG_LEVEL : 'INFO';
    if (!isset($levels[$level]) || !isset($levels[$currentLevel])) return;
    if ($levels[$level] < $levels[$currentLevel]) return;
    
    if (!is_dir(LOG_DIR)) {
        @mkdir(LOG_DIR, 0755, true);
    }
    
    $date = date('Y-m-d');
    $time = date('H:i:s');
    $ip = isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : 'cli';
    $method = isset($_SERVER['REQUEST_METHOD']) ? $_SERVER['REQUEST_METHOD'] : 'CLI';
    $uri = isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : '';
    $userId = isset($_SESSION['usuario_id']) ? $_SESSION['usuario_id'] : '-';
    
    $contextStr = !empty($context) ? ' ' . json_encode($context, JSON_UNESCAPED_UNICODE) : '';
    
    $line = sprintf("[%s %s] %s | %s | %s | User:%s | %s%s\n",
        $date, $time, $level, $ip, $method, $userId, $message, $contextStr
    );
    
    $logFile = LOG_DIR . 'app_' . $date . '.log';
    @file_put_contents($logFile, $line, FILE_APPEND | LOCK_EX);
    
    // También loguear a un archivo separado para errores
    if ($level === 'ERROR') {
        $errorFile = LOG_DIR . 'error_' . $date . '.log';
        @file_put_contents($errorFile, $line, FILE_APPEND | LOCK_EX);
    }
}
