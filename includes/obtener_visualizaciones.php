<?php
session_start();
require_once '../config/config.php';

header('Content-Type: application/json');

if (!isset($_SESSION['usuario_id'])) {
    echo json_encode(['exito' => false, 'mensaje' => 'No autorizado']);
    exit;
}

try {
    // Obtener visualizaciones activas agrupadas por permiso
    $query = "SELECT va.permiso_id, COUNT(*) as total, 
              GROUP_CONCAT(f.nombre SEPARATOR ', ') as usuarios
              FROM visualizaciones_activas va
              JOIN funcionarios f ON va.usuario_id = f.id
              GROUP BY va.permiso_id";
    $stmt = $pdo->prepare($query);
    $stmt->execute();
    $visualizaciones = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'exito' => true,
        'visualizaciones' => $visualizaciones
    ]);
} catch (PDOException $e) {
    echo json_encode([
        'exito' => false,
        'mensaje' => 'Error: ' . $e->getMessage()
    ]);
}
?>