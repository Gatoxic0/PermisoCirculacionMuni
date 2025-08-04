<?php
session_start();
require_once '../config/config.php';

header('Content-Type: application/json');

$respuesta = ['exito' => false];

if (isset($_GET['permiso_id'])) {
    $permisoId = intval($_GET['permiso_id']);
    
    if ($permisoId > 0) {
        try {
            // Consultar el estado actual del permiso
            $consultaEstado = "SELECT estado FROM solicitudes WHERE vehiculo_id = ?";
            $stmtEstado = $pdo->prepare($consultaEstado);
            $stmtEstado->execute([$permisoId]);
            $resultado = $stmtEstado->fetch(PDO::FETCH_ASSOC);
            
            if ($resultado) {
                $respuesta = [
                    'exito' => true,
                    'estado' => $resultado['estado']
                ];
            } else {
                $respuesta = [
                    'exito' => false,
                    'mensaje' => 'No se encontró el permiso'
                ];
            }
        } catch (PDOException $e) {
            $respuesta = [
                'exito' => false,
                'mensaje' => 'Error en la base de datos: ' . $e->getMessage()
            ];
        }
    }
}

echo json_encode($respuesta);
?>