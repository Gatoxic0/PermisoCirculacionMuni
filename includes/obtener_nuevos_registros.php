<?php
require_once '../config/config.php';

// Obtener el último ID conocido por el cliente
$ultimo_id = isset($_GET['ultimo_id']) ? intval($_GET['ultimo_id']) : 0;

try {
    // Consultar nuevos registros
    $query = "SELECT v.id as vehiculo_id, v.placa_patente, 
                     u.rut, u.nombre, u.apellido_paterno, u.apellido_materno,
                     s.estado, s.fecha_solicitud
              FROM vehiculos v
              JOIN usuarios u ON v.usuario_id = u.id
              JOIN solicitudes s ON v.id = s.vehiculo_id
              WHERE v.id > :ultimo_id
              ORDER BY v.id DESC";  // Changed to DESC to show newest first
    
    $stmt = $pdo->prepare($query);
    $stmt->bindParam(':ultimo_id', $ultimo_id);
    $stmt->execute();
    
    $nuevos_registros = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Devolver los resultados como JSON
    header('Content-Type: application/json');
    echo json_encode([
        'exito' => true,
        'registros' => $nuevos_registros
    ]);
    
} catch(PDOException $e) {
    header('Content-Type: application/json');
    echo json_encode([
        'exito' => false,
        'mensaje' => 'Error al obtener registros: ' . $e->getMessage()
    ]);
}
?>