<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Función para mostrar resultados formateados
function mostrarResultado($titulo, $resultado, $exito = true) {
    echo "<div style='margin-bottom: 10px; padding: 10px; border: 1px solid " . ($exito ? "#4CAF50" : "#F44336") . "; background-color: " . ($exito ? "#E8F5E9" : "#FFEBEE") . ";'>";
    echo "<h3 style='margin-top: 0; color: " . ($exito ? "#2E7D32" : "#C62828") . ";'>" . $titulo . "</h3>";
    echo "<pre style='margin: 0; white-space: pre-wrap;'>" . $resultado . "</pre>";
    echo "</div>";
}

// Información del sistema
$info_sistema = "PHP Version: " . phpversion() . "\n";
$info_sistema .= "Sistema Operativo: " . PHP_OS . "\n";
$info_sistema .= "Servidor: " . $_SERVER['SERVER_SOFTWARE'] . "\n";
$info_sistema .= "Hostname: " . gethostname() . "\n";

// Comprobar extensiones necesarias
$extensiones = [
    'openssl' => extension_loaded('openssl'),
    'mbstring' => extension_loaded('mbstring'),
    'curl' => extension_loaded('curl'),
    'fileinfo' => extension_loaded('fileinfo'),
    'sockets' => extension_loaded('sockets')
];

$info_extensiones = "";
foreach ($extensiones as $ext => $disponible) {
    $info_extensiones .= $ext . ": " . ($disponible ? "Disponible ✓" : "No disponible ✗") . "\n";
}

// Comprobar función mail()
$test_mail = false;
$mail_info = "";
try {
    $to = "test@example.com"; // Reemplaza con tu correo real para pruebas
    $subject = "Test desde diagnostico_email.php";
    $message = "Este es un mensaje de prueba enviado el " . date('Y-m-d H:i:s');
    $headers = "From: webmaster@" . $_SERVER['SERVER_NAME'] . "\r\n";
    $headers .= "Content-Type: text/plain; charset=UTF-8\r\n";
    
    $test_mail = mail($to, $subject, $message, $headers);
    $mail_info = "Resultado: " . ($test_mail ? "Correo enviado correctamente" : "Fallo al enviar correo") . "\n";
    $mail_info .= "Error: " . (error_get_last() ? error_get_last()['message'] : "Ninguno") . "\n";
} catch (Exception $e) {
    $mail_info = "Excepción: " . $e->getMessage() . "\n";
}

// Comprobar configuración de PHPMailer
$phpmailer_info = "";
if (file_exists(__DIR__ . '/vendor/autoload.php')) {
    require_once __DIR__ . '/vendor/autoload.php';
    $phpmailer_info .= "PHPMailer autoload: Disponible ✓\n";
    
    if (class_exists('PHPMailer\PHPMailer\PHPMailer')) {
        $phpmailer_info .= "Clase PHPMailer: Disponible ✓\n";
    } else {
        $phpmailer_info .= "Clase PHPMailer: No disponible ✗\n";
    }
} else {
    $phpmailer_info .= "PHPMailer autoload: No disponible ✗\n";
}

// Comprobar configuración SMTP
$smtp_info = "";
if (function_exists('fsockopen')) {
    $smtp_servers = [
        'localhost:25' => 'Servidor local (puerto 25)',
        'localhost:587' => 'Servidor local (puerto 587)',
        'smtp.gmail.com:587' => 'Gmail (puerto 587)',
        'smtp.gmail.com:465' => 'Gmail (puerto 465)'
    ];
    
    foreach ($smtp_servers as $server => $descripcion) {
        list($host, $port) = explode(':', $server);
        $errno = 0;
        $errstr = '';
        $timeout = 5;
        
        $connection = @fsockopen($host, $port, $errno, $errstr, $timeout);
        $smtp_info .= "$descripcion: ";
        
        if ($connection) {
            $smtp_info .= "Conexión exitosa ✓\n";
            fclose($connection);
        } else {
            $smtp_info .= "Conexión fallida ✗ (Error: $errstr)\n";
        }
    }
} else {
    $smtp_info .= "Función fsockopen no disponible para pruebas de conexión SMTP\n";
}

// Comprobar archivos de configuración
$config_info = "";
$config_files = [
    'config/config.php',
    'includes/mail_sender.php'
];

foreach ($config_files as $file) {
    $full_path = __DIR__ . '/' . $file;
    if (file_exists($full_path)) {
        $config_info .= "$file: Existe ✓\n";
        
        // Verificar contenido básico
        $content = file_get_contents($full_path);
        if (strpos($content, 'GMAIL_USERNAME') !== false) {
            $config_info .= "  - Contiene configuración de correo ✓\n";
        } else {
            $config_info .= "  - No contiene configuración de correo ✗\n";
        }
    } else {
        $config_info .= "$file: No existe ✗\n";
    }
}

// Comprobar permisos de directorios
$permisos_info = "";
$directories = [
    '.',
    'logs',
    'vendor',
    'includes'
];

foreach ($directories as $dir) {
    $full_path = __DIR__ . '/' . $dir;
    if (is_dir($full_path)) {
        $permisos = substr(sprintf('%o', fileperms($full_path)), -4);
        $permisos_info .= "$dir: $permisos\n";
        $permisos_info .= "  - Lectura: " . (is_readable($full_path) ? "Sí ✓" : "No ✗") . "\n";
        $permisos_info .= "  - Escritura: " . (is_writable($full_path) ? "Sí ✓" : "No ✗") . "\n";
    } else {
        $permisos_info .= "$dir: No existe ✗\n";
    }
}

// Mostrar resultados en HTML
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Diagnóstico de Envío de Correos</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            line-height: 1.6;
            margin: 0;
            padding: 20px;
            background-color: #f5f5f5;
        }
        .container {
            max-width: 800px;
            margin: 0 auto;
            background-color: #fff;
            padding: 20px;
            border-radius: 5px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }
        h1 {
            color: #333;
            border-bottom: 2px solid #eee;
            padding-bottom: 10px;
        }
        h2 {
            color: #555;
            margin-top: 20px;
        }
        pre {
            background-color: #f9f9f9;
            padding: 10px;
            border-radius: 3px;
            overflow-x: auto;
        }
        .actions {
            margin-top: 20px;
            padding: 15px;
            background-color: #e3f2fd;
            border-radius: 5px;
        }
        .btn {
            display: inline-block;
            padding: 8px 16px;
            background-color: #2196F3;
            color: white;
            text-decoration: none;
            border-radius: 4px;
            margin-right: 10px;
            margin-bottom: 10px;
        }
        .btn:hover {
            background-color: #0b7dda;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>Diagnóstico de Envío de Correos</h1>
        
        <h2>Información del Sistema</h2>
        <?php mostrarResultado("Detalles del Sistema", $info_sistema); ?>
        
        <h2>Extensiones PHP Requeridas</h2>
        <?php mostrarResultado("Estado de Extensiones", $info_extensiones, !in_array(false, $extensiones)); ?>
        
        <h2>Prueba de función mail()</h2>
        <?php mostrarResultado("Función mail() nativa", $mail_info, $test_mail); ?>
        
        <h2>Configuración de PHPMailer</h2>
        <?php mostrarResultado("Estado de PHPMailer", $phpmailer_info, strpos($phpmailer_info, "No disponible") === false); ?>
        
        <h2>Prueba de Conexiones SMTP</h2>
        <?php mostrarResultado("Conexiones SMTP", $smtp_info); ?>
        
        <h2>Archivos de Configuración</h2>
        <?php mostrarResultado("Estado de Archivos", $config_info, strpos($config_info, "No existe") === false); ?>
        
        <h2>Permisos de Directorios</h2>
        <?php mostrarResultado("Permisos", $permisos_info); ?>
        
        <div class="actions">
            <h3>Acciones Recomendadas</h3>
            <p>Basado en los resultados anteriores, aquí hay algunas acciones que puedes tomar:</p>
            <ul>
                <?php if (!$test_mail): ?>
                <li>Contacta a tu proveedor de hosting para verificar si la función mail() está habilitada y configurada correctamente.</li>
                <?php endif; ?>
                
                <?php if (strpos($smtp_info, "Conexión fallida") !== false): ?>
                <li>Tu servidor parece tener restricciones para conexiones SMTP externas. Considera usar el servidor SMTP local o solicitar a tu proveedor que habilite estas conexiones.</li>
                <?php endif; ?>
                
                <?php if (strpos($phpmailer_info, "No disponible") !== false): ?>
                <li>Asegúrate de que PHPMailer esté correctamente instalado. Ejecuta <code>composer require phpmailer/phpmailer</code> en la raíz de tu proyecto.</li>
                <?php endif; ?>
            </ul>
            
            <h3>Pruebas Adicionales</h3>
            <a href="?test_mail=1" class="btn">Probar mail() nuevamente</a>
            <a href="?test_phpmailer=1" class="btn">Probar PHPMailer</a>
        </div>
    </div>
</body>
</html>