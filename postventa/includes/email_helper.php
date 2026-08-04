<?php
/**
 * Helper de Envío de Correos - Postventa Centinela
 * Utiliza PHPMailer para envío de emails transaccionales.
 * 
 * La configuración se define en includes/config.php mediante constantes:
 *   EMAIL_SMTP_HOST, EMAIL_SMTP_PORT, EMAIL_SMTP_USER, EMAIL_SMTP_PASS
 *   EMAIL_FROM, EMAIL_FROM_NAME, EMAIL_ADMIN_DESTINO
 *   EMAIL_CLIENTE_ASUNTO, EMAIL_CLIENTE_CUERPO
 *   EMAIL_ADMIN_ASUNTO, EMAIL_ADMIN_CUERPO
 * 
 * Placeholders disponibles en las plantillas:
 *   {{NOMBRE}} {{RUT}} {{EMAIL}} {{TELEFONO}} {{SOLICITUD_ID}}
 *   {{CATEGORIA}} {{SUBCATEGORIA}} {{UBICACION}} {{DETALLE}}
 *   {{DIAS}} {{FECHA}} {{URL_BASE}}
 */

require_once __DIR__ . '/PHPMailer/PHPMailerAutoload.php';

// Desactivar verificación SSL para entornos locales sin cert CA
// Esto evita el error "failed loading cafile stream"
if (!defined('PHPMAILER_SSL_OPTIONS_SET')) {
    define('PHPMAILER_SSL_OPTIONS_SET', true);
    // Configurar opciones SSL directamente en el stream context por defecto
    stream_context_set_default(array(
        'ssl' => array(
            'verify_peer'       => false,
            'verify_peer_name'  => false,
            'allow_self_signed' => true,
            'cafile'            => null,
            'capath'            => null,
        )
    ));
}

/**
 * Reemplaza placeholders en una plantilla con los datos de la solicitud.
 */
function replacePlaceholders($template, $data) {
    $replacements = array(
        '{{NOMBRE}}'        => isset($data['nombre']) ? htmlspecialchars($data['nombre']) : '',
        '{{RUT}}'           => isset($data['rut']) ? htmlspecialchars($data['rut']) : '',
        '{{EMAIL}}'         => isset($data['email']) ? htmlspecialchars($data['email']) : '',
        '{{TELEFONO}}'      => isset($data['telefono']) ? htmlspecialchars($data['telefono']) : '',
        '{{SOLICITUD_ID}}'  => isset($data['solicitud_id']) ? htmlspecialchars($data['solicitud_id']) : '',
        '{{CATEGORIA}}'     => isset($data['categoria']) ? htmlspecialchars($data['categoria']) : '',
        '{{SUBCATEGORIA}}'  => isset($data['subcategoria']) ? htmlspecialchars($data['subcategoria']) : '',
        '{{UBICACION}}'     => isset($data['ubicacion']) ? htmlspecialchars($data['ubicacion']) : '',
        '{{DETALLE}}'       => isset($data['detalle']) ? nl2br(htmlspecialchars($data['detalle'])) : '',
        '{{DIAS}}'          => isset($data['dias']) ? htmlspecialchars($data['dias']) : '',
        '{{FECHA}}'         => isset($data['fecha']) ? htmlspecialchars($data['fecha']) : '',
        '{{URL_BASE}}'      => isset($data['url_base']) ? htmlspecialchars($data['url_base']) : '',
    );
    
    return str_replace(array_keys($replacements), array_values($replacements), $template);
}

/**
 * Envía un correo electrónico usando PHPMailer con SMTP.
 * @return array ['success' => bool, 'message' => string]
 */
function sendEmail($to, $toName, $subject, $body) {
    if (empty(EMAIL_SMTP_HOST) || empty(EMAIL_SMTP_USER)) {
        logger('WARNING', 'Email no enviado: configuración SMTP incompleta', [
            'to' => $to, 'subject' => $subject
        ]);
        return array('success' => false, 'message' => 'Configuración SMTP incompleta. Configure EMAIL_SMTP_HOST y EMAIL_SMTP_USER en config.php');
    }
    
    try {
        $mail = new PHPMailer(true);
        
        $mail->isSMTP();
        $mail->Host       = EMAIL_SMTP_HOST;
        $mail->SMTPAuth   = true;
        $mail->Username   = EMAIL_SMTP_USER;
        $mail->Password   = EMAIL_SMTP_PASS;
        $mail->SMTPSecure = (EMAIL_SMTP_PORT == '465') ? 'ssl' : 'tls';
        $mail->Port       = EMAIL_SMTP_PORT;
        $mail->CharSet    = 'UTF-8';
        
        $mail->setFrom(EMAIL_FROM, EMAIL_FROM_NAME);
        $mail->addAddress($to, $toName);
        
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body    = $body;
        $mail->AltBody = strip_tags(str_replace(
            array('<br>', '<br/>', '<br />', '</p>', '</tr>', '</h1>', '</h2>', '</h3>'),
            "\n", $body
        ));
        
        $mail->send();
        
        logger('INFO', 'Email enviado exitosamente', ['to' => $to, 'subject' => $subject]);
        
        return array('success' => true, 'message' => 'Email enviado correctamente');
        
    } catch (phpmailerException $e) {
        logger('ERROR', 'Error PHPMailer al enviar email', ['to' => $to, 'error' => $e->getMessage()]);
        return array('success' => false, 'message' => 'Error al enviar email: ' . $e->getMessage());
    } catch (Exception $e) {
        logger('ERROR', 'Error general al enviar email', ['to' => $to, 'error' => $e->getMessage()]);
        return array('success' => false, 'message' => 'Error al enviar email: ' . $e->getMessage());
    }
}

/**
 * Envía el correo de confirmación al cliente tras crear una solicitud.
 */
function enviarCorreoCliente($solicitudData) {
    $subject = replacePlaceholders(EMAIL_CLIENTE_ASUNTO, $solicitudData);
    $body    = replacePlaceholders(EMAIL_CLIENTE_CUERPO, $solicitudData);
    $toName  = isset($solicitudData['nombre']) ? $solicitudData['nombre'] : 'Cliente';
    
    return sendEmail($solicitudData['email'], $toName, $subject, $body);
}

/**
 * Envía el correo de notificación al administrador tras crear una solicitud.
 */
function enviarCorreoAdmin($solicitudData) {
    $subject = replacePlaceholders(EMAIL_ADMIN_ASUNTO, $solicitudData);
    $body    = replacePlaceholders(EMAIL_ADMIN_CUERPO, $solicitudData);
    
    return sendEmail(EMAIL_ADMIN_DESTINO, 'Administrador Postventa', $subject, $body);
}

/**
 * Envía correo al cliente notificando que su solicitud fue APROBADA.
 */
function enviarCorreoClienteAprobado($solicitudData) {
    $subject = replacePlaceholders(EMAIL_CLIENTE_APROBADO_ASUNTO, $solicitudData);
    $body    = replacePlaceholders(EMAIL_CLIENTE_APROBADO_CUERPO, $solicitudData);
    $toName  = isset($solicitudData['nombre']) ? $solicitudData['nombre'] : 'Cliente';
    
    return sendEmail($solicitudData['email'], $toName, $subject, $body);
}

/**
 * Envía correo al cliente notificando que su solicitud fue RECHAZADA.
 */
function enviarCorreoClienteRechazado($solicitudData) {
    $subject = replacePlaceholders(EMAIL_CLIENTE_RECHAZADO_ASUNTO, $solicitudData);
    $body    = replacePlaceholders(EMAIL_CLIENTE_RECHAZADO_CUERPO, $solicitudData);
    $toName  = isset($solicitudData['nombre']) ? $solicitudData['nombre'] : 'Cliente';
    
    return sendEmail($solicitudData['email'], $toName, $subject, $body);
}
