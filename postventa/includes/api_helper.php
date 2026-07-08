<?php
/**
 * Helper para consumir la API REST interna
 * Postventa Centinela
 * 
 * Usa cURL como método principal, file_get_contents como fallback.
 */

function apiCall($endpoint, $postData = array()) {
    // Construir URL absoluta
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https://' : 'http://';
    $host = $_SERVER['HTTP_HOST'];
    $url = $protocol . $host . BASE_URL . 'api/' . $endpoint;
    
    // Token CSRF
    if (!isset($_SESSION['api_csrf_token'])) {
        $_SESSION['api_csrf_token'] = bin2hex(openssl_random_pseudo_bytes(32));
    }
    $postData['csrf_token'] = $_SESSION['api_csrf_token'];
    $postFields = http_build_query($postData);
    
    // Cerrar sesión para evitar deadlock (PHP bloquea sesión por request)
    $cookieData = session_name() . '=' . session_id();
    session_write_close();
    
    // Usar cURL
    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, array(
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $postFields,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 10,
            CURLOPT_HTTPHEADER     => array(
                'Content-Type: application/x-www-form-urlencoded',
                'Referer: ' . $protocol . $host . '/'
            ),
            CURLOPT_COOKIE         => $cookieData
        ));
        
        $response = curl_exec($ch);
        $curlError = curl_error($ch);
        curl_close($ch);
        
        // Reabrir sesión
        session_start();
        
        if ($curlError) {
            return array('success' => false, 'message' => 'Error de conexión: ' . $curlError);
        }
        
        $data = json_decode($response, true);
        if ($data === null) {
            return array('success' => false, 'message' => 'Error al procesar la respuesta.');
        }
        return $data;
    }
    
    // Fallback: file_get_contents
    $options = array(
        'http' => array(
            'method'  => 'POST',
            'header'  => "Content-Type: application/x-www-form-urlencoded\r\n" .
                         "Referer: " . $protocol . $host . "/\r\n" .
                         "Cookie: " . $cookieData . "\r\n",
            'content' => $postFields,
            'timeout' => 10
        )
    );
    
    $context = stream_context_create($options);
    $response = @file_get_contents($url, false, $context);
    
    session_start();
    
    if ($response === false) {
        return array('success' => false, 'message' => 'Error de conexión con el servidor.');
    }
    
    $data = json_decode($response, true);
    if ($data === null) {
        return array('success' => false, 'message' => 'Error al procesar la respuesta.');
    }
    
    return $data;
}
