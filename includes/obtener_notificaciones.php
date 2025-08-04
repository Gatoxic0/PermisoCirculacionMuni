<?php
session_start();
require_once '../config/config.php';
require_once 'notificaciones.php';

header('Content-Type: application/json');

// En un sistema real, obtendrías el ID del usuario de la sesión
// Por ahora, usaremos un ID de ejemplo o lo pasaremos como parámetro
$usuarioId = isset($_GET['usuario_id']) ? intval($_GET['usuario_id']) : 1;

$soloNoLeidas = isset($_GET['solo_no_leidas']) && $_GET['solo_no_leidas'] === 'true';
$limite = isset($_GET['limite']) ? intval($_GET['limite']) : 5;

$notificaciones = obtenerNotificaciones($usuarioId, $soloNoLeidas, $limite);
$totalNoLeidas = contarNotificacionesNoLeidas($usuarioId);

echo json_encode([
    'exito' => true,
    'notificaciones' => $notificaciones,
    'total_no_leidas' => $totalNoLeidas
]);
?>