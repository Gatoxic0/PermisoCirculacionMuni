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
$permisoId = isset($data['permiso_id']) ? intval($data['permiso_id']) : 0;
$accion = isset($data['accion']) ? $data['accion'] : 'iniciar'; // iniciar o finalizar

if ($permisoId <= 0 || !isset($_SESSION['usuario_id'])) {
    echo json_encode(['exito' => false, 'mensaje' => 'Datos inválidos']);
    exit;
}

$usuarioId = $_SESSION['usuario_id'];
$nombreUsuario = $_SESSION['nombre'] ?? 'Usuario';

try {
    // Obtener información del permiso
    $queryPermiso = "SELECT v.placa_patente, u.nombre, u.apellido_paterno 
                     FROM vehiculos v 
                     JOIN usuarios u ON v.usuario_id = u.id 
                     WHERE v.id = :id";
    $stmtPermiso = $pdo->prepare($queryPermiso);
    $stmtPermiso->bindParam(':id', $permisoId, PDO::PARAM_INT);
    $stmtPermiso->execute();
    $permiso = $stmtPermiso->fetch(PDO::FETCH_ASSOC);
    
    if ($accion === 'iniciar') {
        // Registrar que el usuario está viendo este permiso
        $queryRegistrar = "INSERT INTO visualizaciones_activas (usuario_id, permiso_id, inicio) 
                          VALUES (:usuario_id, :permiso_id, NOW())
                          ON DUPLICATE KEY UPDATE inicio = NOW()";
        $stmtRegistrar = $pdo->prepare($queryRegistrar);
        $stmtRegistrar->bindParam(':usuario_id', $usuarioId, PDO::PARAM_INT);
        $stmtRegistrar->bindParam(':permiso_id', $permisoId, PDO::PARAM_INT);
        $stmtRegistrar->execute();
        
        // Notificar a otros usuarios
        $mensaje = "El usuario {$nombreUsuario} está visualizando el permiso de {$permiso['nombre']} {$permiso['apellido_paterno']} (Patente: {$permiso['placa_patente']})";
        
        // Obtener todos los usuarios excepto el actual
        $queryUsuarios = "SELECT id FROM funcionarios WHERE id != :usuario_id";
        $stmtUsuarios = $pdo->prepare($queryUsuarios);
        $stmtUsuarios->bindParam(':usuario_id', $usuarioId, PDO::PARAM_INT);
        $stmtUsuarios->execute();
        $usuarios = $stmtUsuarios->fetchAll(PDO::FETCH_ASSOC);
        
        // Crear notificación para cada usuario
        foreach ($usuarios as $usuario) {
            crearNotificacion($usuario['id'], $permisoId, $mensaje, 'info');
        }
        
        echo json_encode(['exito' => true, 'mensaje' => 'Visualización registrada']);
    } else {
        // Eliminar el registro de visualización
        $queryEliminar = "DELETE FROM visualizaciones_activas WHERE usuario_id = :usuario_id AND permiso_id = :permiso_id";
        $stmtEliminar = $pdo->prepare($queryEliminar);
        $stmtEliminar->bindParam(':usuario_id', $usuarioId, PDO::PARAM_INT);
        $stmtEliminar->bindParam(':permiso_id', $permisoId, PDO::PARAM_INT);
        $stmtEliminar->execute();
        
        echo json_encode(['exito' => true, 'mensaje' => 'Visualización finalizada']);
    }
} catch (PDOException $e) {
    echo json_encode(['exito' => false, 'mensaje' => 'Error: ' . $e->getMessage()]);
}
?>