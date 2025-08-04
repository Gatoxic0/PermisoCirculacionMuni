<?php
session_start();
require_once '../config/config.php';

// Inicializar la variable de última verificación si no existe
if (!isset($_SESSION['ultima_verificacion_estados'])) {
    $_SESSION['ultima_verificacion_estados'] = time() - 300; // 5 minutos atrás para la primera carga
}

try {
    // Consultar permisos que han cambiado de estado desde la última verificación
    $query = "SELECT v.id as vehiculo_id, LOWER(s.estado) as estado
              FROM vehiculos v 
              JOIN solicitudes s ON v.id = s.vehiculo_id 
              WHERE s.fecha_actualizacion > FROM_UNIXTIME(:ultima_verificacion)
              ORDER BY s.fecha_actualizacion DESC";
              
    $stmt = $pdo->prepare($query);
    $stmt->bindValue(':ultima_verificacion', $_SESSION['ultima_verificacion_estados']);
    $stmt->execute();
    
    // Actualizar el tiempo de la última verificación
    $_SESSION['ultima_verificacion_estados'] = time();
    
    // Devolver los resultados como JSON
    header('Content-Type: application/json');
    echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
    
} catch(PDOException $e) {
    header('HTTP/1.1 500 Internal Server Error');
    echo json_encode(['error' => 'Error al verificar cambios de estado: ' . $e->getMessage()]);
}
?>