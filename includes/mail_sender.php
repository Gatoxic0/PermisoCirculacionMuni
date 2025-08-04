<?php
// Import PHPMailer classes at the top level
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

// Cargar PHPMailer
if (file_exists(__DIR__ . '/../vendor/autoload.php')) {
    // Si usaste Composer
    require_once __DIR__ . '/../vendor/autoload.php';
} else {
    // Si subiste manualmente los archivos
    $phpmailer_path = __DIR__ . '/../vendor/phpmailer/phpmailer/src/';
    
    if (!file_exists($phpmailer_path . 'PHPMailer.php')) {
        error_log('Error: No se encuentra el archivo PHPMailer.php');
        die('No se pudo encontrar PHPMailer. Por favor, verifica la instalación.');
    }
    
    require_once $phpmailer_path . 'PHPMailer.php';
    require_once $phpmailer_path . 'SMTP.php';
    require_once $phpmailer_path . 'Exception.php';
}

// Verificar si existe el archivo de configuración
$config_file = __DIR__ . '/../config/mail_config.php';
if (!file_exists($config_file)) {
    error_log('Error: No se encuentra el archivo mail_config.php');
    die('No se pudo encontrar el archivo de configuración de correo.');
}

require_once $config_file;

// Verificar que las constantes necesarias estén definidas
if (!defined('GMAIL_USERNAME') || !defined('GMAIL_PASSWORD') || !defined('GMAIL_FROM_NAME')) {
    error_log('Error: Faltan constantes en mail_config.php');
    die('Configuración de correo incompleta. Verifica el archivo mail_config.php.');
}

/**
 * Envía un correo electrónico utilizando Gmail
 * 
 * @param string $to Correo del destinatario
 * @param string $subject Asunto del correo
 * @param string $message Cuerpo del mensaje (HTML)
 * @return bool True si se envió correctamente, False en caso contrario
 */
function enviarCorreo($to, $subject, $message) {
    try {
        $mail = new PHPMailer(true);
        
        // Configuración del servidor
        $mail->isSMTP();
        $mail->Host       = 'smtp.gmail.com';
        $mail->SMTPAuth   = true;
        $mail->Username   = GMAIL_USERNAME;
        $mail->Password   = GMAIL_PASSWORD;
        $mail->SMTPSecure = 'tls';
        $mail->Port       = 587;
        $mail->CharSet    = 'UTF-8';
        
        // Activar modo debug para hosting
        $mail->SMTPDebug  = 0; // Cambiar a 2 para ver mensajes detallados
        
        // Remitente y destinatario
        $mail->setFrom(GMAIL_USERNAME, GMAIL_FROM_NAME);
        $mail->addAddress($to);
        
        // Contenido del correo
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body    = $message;
        
        // Enviar el correo
        $mail->send();
        return true;
    } catch (Exception $e) {
        // Registrar el error
        error_log('Error al enviar correo: ' . (isset($mail) ? $mail->ErrorInfo : $e->getMessage()));
        return false;
    }
    } // Esta llave de cierre faltaba para la función enviarCorreo()

/**
 * Envía un correo electrónico de aprobación utilizando Gmail
 * 
 * @param string $to Correo del destinatario
 * @param string $subject Asunto del correo
 * @param string $message Cuerpo del mensaje (HTML)
 * @return bool True si se envió correctamente, False en caso contrario
 */
function enviarCorreoAprobacion($to, $subject, $message) {
    try {
        $mail = new PHPMailer(true);
        
        // Configuración del servidor
        $mail->isSMTP();
        $mail->Host       = 'smtp.gmail.com';
        $mail->SMTPAuth   = true;
        $mail->Username   = GMAIL_APPROVAL_USERNAME;
        $mail->Password   = GMAIL_APPROVAL_PASSWORD;
        $mail->SMTPSecure = 'tls';
        $mail->Port       = 587;
        $mail->CharSet    = 'UTF-8';

        // Añadir opciones SMTP para evitar problemas de certificados y proxy
        $mail->SMTPOptions = array(
            'ssl' => array(
                'verify_peer' => false,
                'verify_peer_name' => false,
                'allow_self_signed' => true
            )
        );
        
        // Activar modo debug para hosting
        $mail->SMTPDebug  = 0; // Cambiar a 2 para ver mensajes detallados
        
        // Remitente y destinatario
        $mail->setFrom(GMAIL_APPROVAL_USERNAME, GMAIL_APPROVAL_FROM_NAME);
        $mail->addAddress($to);
        
        // Contenido del correo
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body    = $message;
        
        // Enviar el correo
        $mail->send();
        return true;
    } catch (Exception $e) {
        // Si falla, intentar con mail() nativo que parece funcionar según los logs
        $headers = "From: " . GMAIL_APPROVAL_FROM_NAME . " <" . GMAIL_APPROVAL_USERNAME . ">\r\n";
        $headers .= "Reply-To: " . GMAIL_APPROVAL_USERNAME . "\r\n";
        $headers .= "MIME-Version: 1.0\r\n";
        $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
        
        if (mail($to, $subject, $message, $headers)) {
            return true;
        }
        
        // Registrar el error si ambos métodos fallan
        error_log('Error al enviar correo de aprobación: ' . (isset($mail) ? $mail->ErrorInfo : $e->getMessage()));
        return false;
    }
}
