<?php
// Cambiar la ruta relativa por una ruta absoluta
require_once __DIR__ . '/../config/config.php';

/**
 * Crea una nueva notificación
 * 
 * @param int $usuarioId ID del usuario destinatario
 * @param int $vehiculoId ID del vehículo relacionado
 * @param string $mensaje Contenido de la notificación
 * @param string $tipo Tipo de notificación (success, warning, danger, info)
 * @return bool True si se creó correctamente, False en caso contrario
 */
function crearNotificacion($usuarioId, $vehiculoId, $mensaje, $tipo = 'info') {
    global $pdo;
    
    try {
        $query = "INSERT INTO notificaciones (usuario_id, vehiculo_id, mensaje, tipo) 
                  VALUES (:usuario_id, :vehiculo_id, :mensaje, :tipo)";
        $stmt = $pdo->prepare($query);
        $stmt->bindParam(':usuario_id', $usuarioId, PDO::PARAM_INT);
        $stmt->bindParam(':vehiculo_id', $vehiculoId, PDO::PARAM_INT);
        $stmt->bindParam(':mensaje', $mensaje, PDO::PARAM_STR);
        $stmt->bindParam(':tipo', $tipo, PDO::PARAM_STR);
        
        return $stmt->execute();
    } catch (PDOException $e) {
        error_log("Error al crear notificación: " . $e->getMessage());
        return false;
    }
}

/**
 * Obtiene las notificaciones de un usuario
 * 
 * @param int $usuarioId ID del usuario
 * @param bool $soloNoLeidas Si es true, solo devuelve notificaciones no leídas
 * @param int $limite Número máximo de notificaciones a devolver
 * @return array Array con las notificaciones
 */
function obtenerNotificaciones($usuarioId, $soloNoLeidas = false, $limite = 10) {
    global $pdo;
    
    try {
        $query = "SELECT n.*, v.placa_patente 
                  FROM notificaciones n
                  JOIN vehiculos v ON n.vehiculo_id = v.id
                  WHERE n.usuario_id = :usuario_id";
        
        if ($soloNoLeidas) {
            $query .= " AND n.leida = 0";
        }
        
        $query .= " ORDER BY n.fecha_creacion DESC LIMIT :limite";
        
        $stmt = $pdo->prepare($query);
        $stmt->bindParam(':usuario_id', $usuarioId, PDO::PARAM_INT);
        $stmt->bindParam(':limite', $limite, PDO::PARAM_INT);
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Error al obtener notificaciones: " . $e->getMessage());
        return [];
    }
}

/**
 * Marca una notificación como leída
 * 
 * @param int $notificacionId ID de la notificación
 * @return bool True si se actualizó correctamente, False en caso contrario
 */
function marcarNotificacionLeida($notificacionId) {
    global $pdo;
    
    try {
        $query = "UPDATE notificaciones SET leida = 1 WHERE id = :id";
        $stmt = $pdo->prepare($query);
        $stmt->bindParam(':id', $notificacionId, PDO::PARAM_INT);
        
        return $stmt->execute();
    } catch (PDOException $e) {
        error_log("Error al marcar notificación como leída: " . $e->getMessage());
        return false;
    }
}

/**
 * Cuenta las notificaciones no leídas de un usuario
 * 
 * @param int $usuarioId ID del usuario
 * @return int Número de notificaciones no leídas
 */
function contarNotificacionesNoLeidas($usuarioId) {
    global $pdo;
    
    try {
        $query = "SELECT COUNT(*) FROM notificaciones WHERE usuario_id = :usuario_id AND leida = 0";
        $stmt = $pdo->prepare($query);
        $stmt->bindParam(':usuario_id', $usuarioId, PDO::PARAM_INT);
        $stmt->execute();
        
        return (int)$stmt->fetchColumn();
    } catch (PDOException $e) {
        error_log("Error al contar notificaciones no leídas: " . $e->getMessage());
        return 0;
    }
}
?>