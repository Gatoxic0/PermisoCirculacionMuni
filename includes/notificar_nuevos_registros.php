<?php
// Script para notificar nuevos registros de vehículos
// Este script se ejecutará cada 10 minutos mediante cron de cPanel

// Importar clases de PHPMailer al inicio del archivo
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Función para registrar logs (definida primero para poder usarla en todo el script)
function escribirLog($mensaje) {
    $fecha = date('Y-m-d H:i:s');
    $logFile = __DIR__ . '/../logs/notificaciones_' . date('Y-m-d') . '.log';
    
    // Crear directorio de logs si no existe
    if (!file_exists(__DIR__ . '/../logs')) {
        mkdir(__DIR__ . '/../logs', 0755, true);
    }
    
    // Añadir información del entorno
    $infoAdicional = "PHP Version: " . phpversion() . ", OS: " . PHP_OS;
    
    file_put_contents($logFile, "[$fecha] [$infoAdicional] $mensaje" . PHP_EOL, FILE_APPEND);
}

// Función para verificar y crear directorios necesarios
function verificarDirectorios() {
    $directorios = [
        __DIR__ . '/../logs',
        __DIR__ . '/../config'
    ];
    
    foreach ($directorios as $dir) {
        if (!file_exists($dir)) {
            if (!mkdir($dir, 0755, true)) {
                escribirLog("Error: No se pudo crear el directorio $dir");
            } else {
                escribirLog("Directorio creado: $dir");
            }
        }
    }
}

// Verificar directorios al inicio
verificarDirectorios();

// Registrar inicio del script con información de entorno
escribirLog("Iniciando script de notificación - Entorno: " . php_uname());

// Importar configuraciones necesarias
try {
    escribirLog("Cargando archivos de configuración...");
    
    if (!file_exists(__DIR__ . '/../config/config.php')) {
        escribirLog("ERROR: No se encuentra el archivo config.php");
        die("ERROR: No se encuentra el archivo config.php");
    }
    
    if (!file_exists(__DIR__ . '/../config/mail_config.php')) {
        escribirLog("ERROR: No se encuentra el archivo mail_config.php");
        die("ERROR: No se encuentra el archivo mail_config.php");
    }
    
    require_once __DIR__ . '/../config/config.php';
    require_once __DIR__ . '/../config/mail_config.php';
    
    // Verificar que todas las constantes necesarias estén definidas
    $constantesRequeridas = [
        'GMAIL_USERNAME', 
        'GMAIL_PASSWORD', 
        'GMAIL_FROM_NAME',
        'GMAIL_APPROVAL_USERNAME',
        'GMAIL_APPROVAL_PASSWORD',
        'GMAIL_APPROVAL_FROM_NAME'
    ];
    
    $constantesFaltantes = [];
    foreach ($constantesRequeridas as $constante) {
        if (!defined($constante)) {
            $constantesFaltantes[] = $constante;
        }
    }
    
    if (!empty($constantesFaltantes)) {
        $mensaje = "ERROR: Las siguientes constantes no están definidas en mail_config.php: " . implode(', ', $constantesFaltantes);
        escribirLog($mensaje);
        die($mensaje);
    }
    
    escribirLog("Archivos de configuración cargados correctamente");
} catch (Exception $e) {
    escribirLog("ERROR al cargar archivos de configuración: " . $e->getMessage());
    die("ERROR al cargar archivos de configuración: " . $e->getMessage());
}

// Incluir PHPMailer con manejo de errores
try {
    escribirLog("Cargando PHPMailer...");
    
    // Verificar si PHPMailer ya está disponible (puede estar incluido en el hosting)
    if (!class_exists('PHPMailer\PHPMailer\PHPMailer')) {
        // Intentar con rutas absolutas para el entorno de producción
        $phpmailer_paths = [
            __DIR__ . '/../vendor/phpmailer/phpmailer/src/',
            '/home/intranetmelipill/public_html/solicitudes.intranetmelipilla.cl/formulario/vendor/phpmailer/phpmailer/src/',
            // Añadir las nuevas rutas posibles
            __DIR__ . '/../src/phpmailer/src/',
            '/home/intranetmelipill/public_html/solicitudes.intranetmelipilla.cl/formulario/src/phpmailer/src/',
            __DIR__ . '/../src/PHPMailer/src/',
            '/home/intranetmelipill/public_html/solicitudes.intranetmelipilla.cl/formulario/src/PHPMailer/src/',
            // Rutas adicionales para cubrir todas las posibilidades
            __DIR__ . '/../phpmailer/src/',
            '/home/intranetmelipill/public_html/solicitudes.intranetmelipilla.cl/phpmailer/src/'
        ];
        
        $phpmailer_loaded = false;
        
        foreach ($phpmailer_paths as $phpmailer_path) {
            escribirLog("Intentando cargar PHPMailer desde: " . $phpmailer_path);
            if (file_exists($phpmailer_path . 'PHPMailer.php')) {
                require_once $phpmailer_path . 'Exception.php';
                require_once $phpmailer_path . 'PHPMailer.php';
                require_once $phpmailer_path . 'SMTP.php';
                escribirLog("PHPMailer cargado exitosamente desde: " . $phpmailer_path);
                $phpmailer_loaded = true;
                break;
            }
        }
        
        if (!$phpmailer_loaded) {
            escribirLog("ERROR: No se encuentra PHPMailer.php en ninguna ruta conocida. Intentando usar mail() nativo");
            // En lugar de morir, continuamos y usaremos mail() nativo como fallback
        }
    } else {
        escribirLog("PHPMailer ya está disponible en el sistema");
    }
    
} catch (Exception $e) {
    escribirLog("ERROR al cargar PHPMailer: " . $e->getMessage());
    // No terminamos el script, intentaremos usar mail() nativo
    escribirLog("Continuando sin PHPMailer, se intentará usar mail() nativo");
}

// Cargar funciones de envío de correo
try {
    escribirLog("Cargando mail_sender.php...");
    
    if (!file_exists(__DIR__ . '/mail_sender.php')) {
        escribirLog("ERROR: No se encuentra el archivo mail_sender.php");
        die("ERROR: No se encuentra el archivo mail_sender.php");
    }
    
    require_once __DIR__ . '/mail_sender.php';
    escribirLog("mail_sender.php cargado correctamente");
} catch (Exception $e) {
    escribirLog("ERROR al cargar mail_sender.php: " . $e->getMessage());
    die("ERROR al cargar mail_sender.php: " . $e->getMessage());
}

// Función para obtener nuevos registros
function obtenerNuevosRegistros($pdo) {
    try {
        // Obtener la última fecha de verificación
        $ultimaVerificacion = obtenerUltimaVerificacion();
        
        // Consulta modificada para usar fecha_solicitud de la tabla solicitudes
        $query = "SELECT v.id, v.placa_patente, s.fecha_solicitud as fecha_registro, 
                         u.nombre, u.apellido_paterno, u.apellido_materno, u.email, u.rut
                  FROM vehiculos v
                  JOIN usuarios u ON v.usuario_id = u.id
                  JOIN solicitudes s ON v.id = s.vehiculo_id
                  WHERE s.fecha_solicitud > :ultima_verificacion
                  ORDER BY s.fecha_solicitud DESC";
        
        $stmt = $pdo->prepare($query);
        $stmt->bindParam(':ultima_verificacion', $ultimaVerificacion);
        $stmt->execute();
        
        $registros = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Actualizar la última fecha de verificación
        actualizarUltimaVerificacion();
        
        return $registros;
    } catch (PDOException $e) {
        escribirLog("Error al consultar nuevos registros: " . $e->getMessage());
        return [];
    }
}

// Función para obtener la última fecha de verificación
function obtenerUltimaVerificacion() {
    $archivoVerificacion = __DIR__ . '/../config/ultima_verificacion.txt';
    
    if (file_exists($archivoVerificacion)) {
        $fecha = trim(file_get_contents($archivoVerificacion));
        return $fecha;
    } else {
        // Si no existe el archivo, usar una fecha anterior (30 minutos atrás)
        $fechaInicial = date('Y-m-d H:i:s', strtotime('-30 minutes'));
        file_put_contents($archivoVerificacion, $fechaInicial);
        return $fechaInicial;
    }
}

// Función para actualizar la última fecha de verificación
function actualizarUltimaVerificacion() {
    $archivoVerificacion = __DIR__ . '/../config/ultima_verificacion.txt';
    $ahora = date('Y-m-d H:i:s');
    file_put_contents($archivoVerificacion, $ahora);
}

// Función para enviar notificación por correo
function enviarNotificacionCorreo($registros) {
    if (empty($registros)) {
        escribirLog("No hay nuevos registros para notificar");
        return false;
    }
    
    // Registrar información detallada
    escribirLog("Preparando para enviar notificación de " . count($registros) . " registros");
    
    // Destinatario (cuenta de solicitudes)
    $to = GMAIL_USERNAME; // solicitudes@munimelipilla.cl
    escribirLog("Destinatario configurado: " . $to);
    
    // Asunto del correo
    $subject = "Nuevos registros de vehículos - " . date('d/m/Y H:i');
    
    // Construir el cuerpo del mensaje
    $message = "<h2>Nuevos Registros de Vehículos</h2>";
    $message .= "<p>Se han detectado " . count($registros) . " nuevos registros desde la última verificación:</p>";
    $message .= "<table border='1' cellpadding='5' cellspacing='0' style='border-collapse: collapse;'>";
    $message .= "<tr style='background-color: #f2f2f2;'>";
    $message .= "<th>Patente</th>";
    $message .= "<th>Nombre</th>";
    $message .= "<th>RUT</th>";
    $message .= "<th>Email</th>";
    $message .= "<th>Fecha Registro</th>";
    $message .= "</tr>";
    
    foreach ($registros as $registro) {
        $message .= "<tr>";
        $message .= "<td>" . $registro['placa_patente'] . "</td>";
        $message .= "<td>" . $registro['nombre'] . " " . $registro['apellido_paterno'] . " " . $registro['apellido_materno'] . "</td>";
        $message .= "<td>" . $registro['rut'] . "</td>";
        $message .= "<td>" . $registro['email'] . "</td>";
        $message .= "<td>" . date('d/m/Y H:i', strtotime($registro['fecha_registro'])) . "</td>";
        $message .= "</tr>";
    }
    
    $message .= "</table>";
    $message .= "<p>Para ver los detalles completos, acceda al sistema de administración.</p>";
    $message .= "<p>Este es un mensaje automático, por favor no responda a este correo.</p>";
    
    // Enviar el correo usando la cuenta no-reply
    try {
        // Verificar nuevamente que las constantes estén definidas
        if (!defined('GMAIL_APPROVAL_USERNAME') || !defined('GMAIL_APPROVAL_PASSWORD')) {
            escribirLog("ERROR: Constantes GMAIL_APPROVAL_* no definidas. Verificando mail_config.php");
            
            // Intentar cargar nuevamente el archivo de configuración
            if (file_exists(__DIR__ . '/../config/mail_config.php')) {
                require_once __DIR__ . '/../config/mail_config.php';
                
                // Verificar después de cargar
                if (!defined('GMAIL_APPROVAL_USERNAME') || !defined('GMAIL_APPROVAL_PASSWORD')) {
                    escribirLog("ERROR: Constantes GMAIL_APPROVAL_* siguen sin estar definidas después de recargar mail_config.php");
                    return false;
                }
            } else {
                escribirLog("ERROR: No se encuentra el archivo mail_config.php para recargar");
                return false;
            }
        }
        
        // Intentar primero con la función del mail_sender.php
        escribirLog("Intentando enviar correo con la función enviarCorreoAprobacion()...");
        
        if (function_exists('enviarCorreoAprobacion')) {
            // Verificar que las constantes estén definidas
            if (!defined('GMAIL_APPROVAL_USERNAME') || !defined('GMAIL_APPROVAL_PASSWORD')) {
                escribirLog("ERROR: Constantes GMAIL_APPROVAL_* no definidas");
                return false;
            }
            
            escribirLog("Usando remitente: " . GMAIL_APPROVAL_USERNAME);
            $resultado = enviarCorreoAprobacion($to, $subject, $message);
            if ($resultado) {
                escribirLog("Notificación enviada correctamente usando enviarCorreoAprobacion() para " . count($registros) . " nuevos registros");
                return true;
            } else {
                escribirLog("Error al usar enviarCorreoAprobacion(), intentando método alternativo...");
            }
        } else {
            escribirLog("La función enviarCorreoAprobacion() no está disponible");
        }
        
        // Si la función no existe o falló, usar método directo
        if (class_exists('PHPMailer\PHPMailer\PHPMailer')) {
            escribirLog("Usando método directo de PHPMailer...");
            $mail = new PHPMailer(true);
            
            // Habilitar modo debug
            $mail->SMTPDebug = 2; // Nivel de debug: 2 = mensajes cliente/servidor
            $debugOutput = '';
            $mail->Debugoutput = function($str, $level) use (&$debugOutput) {
                escribirLog("PHPMailer Debug: $str");
                $debugOutput .= "Debug: $str\n";
            };
            
            // Configuración del servidor
            $mail->isSMTP();
            $mail->Host       = 'smtp.gmail.com';
            $mail->SMTPAuth   = true;
            
            // Corregir formato de dirección de correo (error detectado en logs)
            $mail->Username   = GMAIL_APPROVAL_USERNAME; // Asegurarse que sea solo no-reply@munimelipilla.cl
            $mail->Password   = GMAIL_APPROVAL_PASSWORD;
            $mail->SMTPSecure = 'tls';
            $mail->Port       = 587;
            $mail->CharSet    = 'UTF-8';
            
            // Opciones SMTP para evitar problemas de certificados
            $mail->SMTPOptions = array(
                'ssl' => array(
                    'verify_peer' => false,
                    'verify_peer_name' => false,
                    'allow_self_signed' => true
                )
            );
            
            // Remitente y destinatario
            $mail->setFrom(GMAIL_APPROVAL_USERNAME, 'Sistema de Notificaciones - Permisos de Circulación');
            $mail->addAddress($to);
            
            // Contenido del correo
            $mail->isHTML(true);
            $mail->Subject = $subject;
            $mail->Body    = $message;
            
            // Enviar el correo
            $mail->send();
            escribirLog("Notificación enviada correctamente usando método directo para " . count($registros) . " nuevos registros");
            return true;
        } else {
            // Último recurso: usar mail() nativo de PHP
            escribirLog("PHPMailer no disponible, intentando con mail() nativo...");
            $headers = "From: Sistema de Notificaciones <" . GMAIL_APPROVAL_USERNAME . ">\r\n";
            $headers .= "Reply-To: " . GMAIL_APPROVAL_USERNAME . "\r\n";
            $headers .= "MIME-Version: 1.0\r\n";
            $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
            
            $resultado = mail($to, $subject, $message, $headers);
            if ($resultado) {
                escribirLog("Correo enviado correctamente usando mail() nativo");
                return true;
            } else {
                escribirLog("Falló el envío usando mail() nativo. Error de PHP: " . error_get_last()['message']);
                return false;
            }
        }
    } catch (Exception $e) {
        escribirLog("Error al enviar notificación por correo: " . $e->getMessage());
        if (isset($mail) && property_exists($mail, 'ErrorInfo')) {
            escribirLog("Detalles del error SMTP: " . $mail->ErrorInfo);
        }
        
        // Intentar con método nativo de PHP como último recurso
        escribirLog("Intentando enviar con mail() nativo de PHP como último recurso...");
        $headers = "From: Sistema de Notificaciones <" . GMAIL_APPROVAL_USERNAME . ">\r\n";
        $headers .= "Reply-To: " . GMAIL_APPROVAL_USERNAME . "\r\n";
        $headers .= "MIME-Version: 1.0\r\n";
        $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
        
        $resultado = mail($to, $subject, $message, $headers);
        if ($resultado) {
            escribirLog("Correo enviado correctamente usando mail() nativo");
            return true;
        } else {
            escribirLog("Falló el envío usando mail() nativo. Error de PHP: " . error_get_last()['message']);
            return false;
        }
    }
}

// Ejecución principal
try {
    escribirLog("Iniciando verificación de nuevos registros");
    
    // Verificar conexión a la base de datos
    if (!isset($pdo) || !($pdo instanceof PDO)) {
        escribirLog("ERROR: No hay conexión a la base de datos disponible");
        die("ERROR: No hay conexión a la base de datos disponible");
    }
    
    // Probar conexión a la base de datos
    try {
        $pdo->query("SELECT 1");
        escribirLog("Conexión a la base de datos verificada correctamente");
    } catch (PDOException $e) {
        escribirLog("ERROR: Falló la prueba de conexión a la base de datos: " . $e->getMessage());
        die("ERROR: Falló la prueba de conexión a la base de datos: " . $e->getMessage());
    }
    
    // Obtener nuevos registros
    $nuevosRegistros = obtenerNuevosRegistros($pdo);
    
    // Enviar notificación por correo
    if (!empty($nuevosRegistros)) {
        enviarNotificacionCorreo($nuevosRegistros);
    }
    
    escribirLog("Verificación completada. Se encontraron " . count($nuevosRegistros) . " nuevos registros");
} catch (Exception $e) {
    escribirLog("Error general en el script: " . $e->getMessage());
}