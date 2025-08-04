<?php
session_start();
require_once '../config/config.php';
require_once 'notificaciones.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['exito' => false, 'mensaje' => 'Método no permitido']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);
$notificacionId = isset($data['notificacion_id']) ? intval($data['notificacion_id']) : 0;

if ($notificacionId <= 0) {
    echo json_encode(['exito' => false, 'mensaje' => 'ID de notificación inválido']);
    exit;
}

$resultado = marcarNotificacionLeida($notificacionId);

echo json_encode([
    'exito' => $resultado,
    'mensaje' => $resultado ? 'Notificación marcada como leída' : 'Error al marcar la notificación'
]);
?>