<?php
// Establecer zona horaria correcta
date_default_timezone_set('America/Santiago');

// Función para escribir en el log
function escribirLogTest($mensaje) {
    $fecha = date('Y-m-d H:i:s');
    $logFile = __DIR__ . '/../logs/test_notificacion_' . date('Y-m-d') . '.log';
    
    // Crear directorio de logs si no existe
    if (!file_exists(__DIR__ . '/../logs')) {
        mkdir(__DIR__ . '/../logs', 0755, true);
    }
    
    file_put_contents($logFile, "[$fecha] $mensaje" . PHP_EOL, FILE_APPEND);
}

// Cargar configuración de base de datos
require_once __DIR__ . '/../config/config.php';

// Modificar la fecha de última verificación para forzar la detección de registros recientes
$archivoVerificacion = __DIR__ . '/../config/ultima_verificacion.txt';
$fechaAnterior = date('Y-m-d H:i:s', strtotime('-1 day')); // Fecha de ayer
file_put_contents($archivoVerificacion, $fechaAnterior);

escribirLogTest("Fecha de última verificación modificada a: $fechaAnterior");
echo "Fecha de última verificación modificada a: $fechaAnterior<br>";

// Ejecutar el script de notificación
echo "Ejecutando script de notificación...<br>";
require_once __DIR__ . '/notificar_nuevos_registros.php';

echo "Proceso completado. Revisa los logs para más detalles.<br>";
echo "Ruta del log: " . __DIR__ . '/../logs/notificaciones_' . date('Y-m-d') . '.log';
?>