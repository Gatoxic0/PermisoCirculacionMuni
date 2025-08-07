<?php
// Archivo de prueba para verificar la funcionalidad de notificación por correo
// cuando se crea una nueva solicitud

// Establecer zona horaria
date_default_timezone_set('America/Santiago');

// Función para escribir en el log
function escribirLogTest($mensaje) {
    $fecha = date('Y-m-d H:i:s');
    $logFile = __DIR__ . '/logs/test_creacion_solicitud_' . date('Y-m-d') . '.log';
    
    // Crear directorio de logs si no existe
    if (!file_exists(__DIR__ . '/logs')) {
        mkdir(__DIR__ . '/logs', 0755, true);
    }
    
    file_put_contents($logFile, "[$fecha] $mensaje" . PHP_EOL, FILE_APPEND);
}

// Cargar configuración
require_once 'config/config.php';
require_once 'includes/mail_sender.php';

escribirLogTest("Iniciando prueba de notificación de creación de solicitud");

// Datos de prueba
$datosPrueba = [
    'nombre' => 'Usuario de Prueba',
    'email' => 'test@example.com', // Cambiar por un email real para pruebas
    'placa_patente' => 'ABCD12',
    'comuna' => 'Melipilla'
];

// Probar función de envío de correo
try {
    $subject = "Prueba - Confirmación de Solicitud de Permiso de Circulación";
    $htmlMessage = "Estimado/a {$datosPrueba['nombre']},<br><br>";
    $htmlMessage .= "Esta es una prueba del sistema de notificaciones.<br><br>";
    $htmlMessage .= "<strong>Detalles de la solicitud de prueba:</strong><br>";
    $htmlMessage .= "- Patente del vehículo: {$datosPrueba['placa_patente']}<br>";
    $htmlMessage .= "- Comuna: {$datosPrueba['comuna']}<br>";
    $htmlMessage .= "- Estado: Pendiente de revisión<br>";
    $htmlMessage .= "- Fecha de solicitud: " . date('d/m/Y H:i') . "<br><br>";
    $htmlMessage .= "Esta es una prueba del sistema de notificaciones por correo.<br><br>";
    $htmlMessage .= "Saludos cordiales,<br>";
    $htmlMessage .= "Departamento de Permisos de Circulación<br>";
    $htmlMessage .= "Municipalidad de Melipilla";
    
    escribirLogTest("Intentando enviar correo de prueba a: " . $datosPrueba['email']);
    
    $resultado = enviarCorreo($datosPrueba['email'], $subject, $htmlMessage);
    
    if ($resultado) {
        escribirLogTest("✓ Correo de prueba enviado exitosamente");
        echo "<div style='background-color: #d4edda; color: #155724; padding: 20px; border-radius: 8px; margin: 20px;'>";
        echo "<h3>✓ Prueba exitosa</h3>";
        echo "<p>El correo de confirmación se envió correctamente a: {$datosPrueba['email']}</p>";
        echo "<p>Verifica tu bandeja de entrada (y carpeta de spam) para confirmar la recepción.</p>";
        echo "</div>";
    } else {
        escribirLogTest("✗ Error al enviar correo de prueba");
        echo "<div style='background-color: #f8d7da; color: #721c24; padding: 20px; border-radius: 8px; margin: 20px;'>";
        echo "<h3>✗ Error en la prueba</h3>";
        echo "<p>No se pudo enviar el correo de prueba. Revisa los logs para más detalles.</p>";
        echo "</div>";
    }
    
} catch (Exception $e) {
    escribirLogTest("✗ Excepción durante la prueba: " . $e->getMessage());
    echo "<div style='background-color: #f8d7da; color: #721c24; padding: 20px; border-radius: 8px; margin: 20px;'>";
    echo "<h3>✗ Error en la prueba</h3>";
    echo "<p>Excepción: " . $e->getMessage() . "</p>";
    echo "</div>";
}

// Mostrar información de configuración
echo "<div style='background-color: #e2e3e5; color: #383d41; padding: 20px; border-radius: 8px; margin: 20px;'>";
echo "<h3>Información de configuración</h3>";
echo "<p><strong>Servidor SMTP:</strong> smtp.gmail.com:587</p>";
echo "<p><strong>Cuenta de envío:</strong> " . (defined('GMAIL_USERNAME') ? GMAIL_USERNAME : 'No definida') . "</p>";
echo "<p><strong>Nombre remitente:</strong> " . (defined('GMAIL_FROM_NAME') ? GMAIL_FROM_NAME : 'No definido') . "</p>";
echo "<p><strong>Fecha de prueba:</strong> " . date('d/m/Y H:i:s') . "</p>";
echo "</div>";

// Enlace para volver
echo "<div style='text-align: center; margin: 20px;'>";
echo "<a href='index.php' style='background-color: #007bff; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>Volver al inicio</a>";
echo "</div>";
?> 