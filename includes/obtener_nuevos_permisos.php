<?php
session_start();
require_once '../config/config.php';

try {
    $query = "SELECT v.id as vehiculo_id, v.placa_patente, u.nombre, u.apellido_paterno, 
              u.apellido_materno, LOWER(s.estado) as estado, s.fecha_solicitud, u.rut 
              FROM vehiculos v 
              JOIN usuarios u ON v.usuario_id = u.id 
              JOIN solicitudes s ON v.id = s.vehiculo_id 
              WHERE s.fecha_solicitud > FROM_UNIXTIME(:ultima_verificacion)
              ORDER BY s.fecha_solicitud DESC";
              
    $stmt = $pdo->prepare($query);
    $stmt->bindValue(':ultima_verificacion', isset($_SESSION['ultima_verificacion']) ? $_SESSION['ultima_verificacion'] : 0);
    $stmt->execute();
    
    $_SESSION['ultima_verificacion'] = time();
    
    header('Content-Type: application/json');
    echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
    
} catch(PDOException $e) {
    header('HTTP/1.1 500 Internal Server Error');
    echo json_encode(['error' => 'Error al obtener datos']);
}
?>