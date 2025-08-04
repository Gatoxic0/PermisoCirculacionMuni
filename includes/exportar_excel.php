<?php
session_start();

// Verificar si el usuario está autenticado
if (!isset($_SESSION['usuario_id'])) {
    header('Location: ../login.php');
    exit;
}

// Corregir la ruta del archivo de configuración
require_once '../config/config.php';

// Función para corregir rutas de archivos
function corregirRuta($ruta) {
    // Si la ruta contiene 'formulario/uploads', reemplazarla por la nueva ubicación
    $ruta = str_replace('formulario/uploads', 'uploads', $ruta);
    // También manejar rutas con barras invertidas (Windows)
    $ruta = str_replace('formulario\\uploads', 'uploads', $ruta);
    
    // Si la ruta es relativa, convertirla a absoluta desde la raíz del sitio
    if (strpos($ruta, '/') !== 0 && strpos($ruta, 'http') !== 0) {
        $ruta = '/' . $ruta;
    }
    
    return $ruta;
}

// Configurar headers para UTF-8 y Excel
header('Content-Type: application/vnd.ms-excel; charset=UTF-8');
header('Content-Disposition: attachment; filename="permisos_circulacion_' . date('Y-m-d') . '.xls"');
header("Content-Transfer-Encoding: binary");
header('Pragma: no-cache');
header('Expires: 0');

// Agregar BOM para UTF-8
echo chr(239) . chr(187) . chr(191);

try {
    // Consulta para obtener todos los permisos de circulación con información completa
    $query = "SELECT v.id as vehiculo_id, v.placa_patente, 
                     u.rut, u.nombre, u.apellido_paterno, u.apellido_materno, 
                     u.comuna, u.calle, u.numero, u.aclaratoria, u.telefono, u.email,
                     s.estado, s.fecha_solicitud, s.comentarios
              FROM vehiculos v
              JOIN usuarios u ON v.usuario_id = u.id
              JOIN solicitudes s ON v.id = s.vehiculo_id
              ORDER BY s.fecha_solicitud DESC";
    $stmt = $pdo->prepare($query);
    $stmt->execute();
    $permisos = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Crear el contenido del Excel con codificación UTF-8
    echo '<html xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:x="urn:schemas-microsoft-com:office:excel">';
    echo '<head><meta charset="UTF-8"></head>';
    echo '<body>';
    echo '<table border="1">';
    
    // Encabezados
    echo '<tr>';
    echo '<th style="background-color: #1d6f42; color: white;">ID</th>';
    echo '<th style="background-color: #1d6f42; color: white;">PLACA PATENTE</th>';
    echo '<th style="background-color: #1d6f42; color: white;">RUT</th>';
    echo '<th style="background-color: #1d6f42; color: white;">NOMBRE COMPLETO</th>';
    echo '<th style="background-color: #1d6f42; color: white;">EMAIL</th>';
    echo '<th style="background-color: #1d6f42; color: white;">TELÉFONO</th>';
    echo '<th style="background-color: #1d6f42; color: white;">DIRECCIÓN</th>';
    echo '<th style="background-color: #1d6f42; color: white;">COMUNA</th>';
    echo '<th style="background-color: #1d6f42; color: white;">ESTADO</th>';
    echo '<th style="background-color: #1d6f42; color: white;">FECHA SOLICITUD</th>';
    echo '<th style="background-color: #1d6f42; color: white;">ACLARATORIA</th>';
    echo '<th style="background-color: #1d6f42; color: white;">PERMISO CIRCULACIÓN</th>';
    echo '<th style="background-color: #1d6f42; color: white;">CERTIFICADO HOMOLOGACIÓN</th>';
    echo '<th style="background-color: #1d6f42; color: white;">SEGURO OBLIGATORIO</th>';
    echo '<th style="background-color: #1d6f42; color: white;">CERTIFICADO INSCRIPCIÓN</th>';
    echo '<th style="background-color: #1d6f42; color: white;">FACTURA</th>';
    echo '</tr>';

    // Datos
    foreach($permisos as $permiso) {
        $nombreCompleto = $permiso['nombre'] . ' ' . $permiso['apellido_paterno'] . ' ' . $permiso['apellido_materno'];
        $direccion = $permiso['calle'] . ' ' . $permiso['numero'];
        if (!empty($permiso['aclaratoria'])) {
            $direccion .= ', ' . $permiso['aclaratoria'];
        }
        
        // Obtener documentos para este vehículo
        $queryDocs = "SELECT * FROM documentos WHERE vehiculo_id = :vehiculo_id";
        $stmtDocs = $pdo->prepare($queryDocs);
        $stmtDocs->bindParam(':vehiculo_id', $permiso['vehiculo_id']);
        $stmtDocs->execute();
        $documents = $stmtDocs->fetchAll(PDO::FETCH_ASSOC);
        
        // Organizar documentos por tipo
        $docs = [];
        foreach ($documents as $doc) {
            $docs[$doc['tipo_documento']] = $doc;
        }
        
        echo '<tr>';
        echo '<td>' . htmlspecialchars($permiso['vehiculo_id']) . '</td>';
        echo '<td>' . htmlspecialchars($permiso['placa_patente']) . '</td>';
        echo '<td>' . htmlspecialchars($permiso['rut']) . '</td>';
        echo '<td>' . htmlspecialchars($nombreCompleto) . '</td>';
        echo '<td>' . htmlspecialchars($permiso['email']) . '</td>';
        echo '<td>' . htmlspecialchars($permiso['telefono']) . '</td>';
        echo '<td>' . htmlspecialchars($direccion) . '</td>';
        echo '<td>' . htmlspecialchars($permiso['comuna']) . '</td>';
        echo '<td>' . htmlspecialchars(ucfirst($permiso['estado'])) . '</td>';
        echo '<td>' . date('d/m/Y H:i', strtotime($permiso['fecha_solicitud'])) . '</td>';
        echo '<td>' . htmlspecialchars($permiso['comentarios'] ?? '') . '</td>';
        
        // Enlaces a documentos
        // Permiso de Circulación
        echo '<td>';
        if (isset($docs['permiso_circulacion_a'])) {
            echo 'Disponible';
        } else {
            echo 'No disponible';
        }
        echo '</td>';
        
        // Certificado de Homologación
        echo '<td>';
        if (isset($docs['certificado_homologacion'])) {
            echo 'Disponible';
        } else {
            echo 'No disponible';
        }
        echo '</td>';
        
        // Seguro Obligatorio
        echo '<td>';
        if (isset($docs['certificado_inscripcion_a'])) {
            echo 'Disponible';
        } else {
            echo 'No disponible';
        }
        echo '</td>';
        
        // Certificado de Inscripción
        echo '<td>';
        if (isset($docs['certificado_inscripcion_b'])) {
            echo 'Disponible';
        } else {
            echo 'No disponible';
        }
        echo '</td>';
        
        // Factura
        echo '<td>';
        if (isset($docs['factura'])) {
            echo 'Disponible';
        } else {
            echo 'No disponible';
        }
        echo '</td>';
        
        echo '</tr>';
    }

    echo '</table>';
    echo '</body>';
    echo '</html>';
    
} catch(PDOException $e) {
    die("Error al obtener datos: " . $e->getMessage());
}

exit;