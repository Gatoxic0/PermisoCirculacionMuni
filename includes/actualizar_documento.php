<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();
require_once '../config/config.php';

// Verificar si el usuario está logueado
if (!isset($_SESSION['usuario_id'])) {
    header('Location: ../login.php');
    exit;
}

// Verificar si se recibieron los datos necesarios
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['vehiculo_id']) || !isset($_POST['tipo_documento']) || !isset($_FILES['documento'])) {
    $_SESSION['alert'] = [
        'type' => 'danger',
        'message' => 'Error: Datos incompletos para actualizar el documento.'
    ];
    header('Location: ../index.php');
    exit;
}

$vehiculo_id = $_POST['vehiculo_id'];
$tipo_documento = $_POST['tipo_documento'];
// Eliminamos la variable de comentario ya que no se usará
$archivo = $_FILES['documento'];

// Validar el archivo
$allowed_types = ['application/pdf', 'image/jpeg', 'image/png'];
$allowed_extensions = ['pdf', 'jpg', 'jpeg', 'png'];
$max_size = 5 * 1024 * 1024; // 5MB

if ($archivo['size'] > $max_size) {
    $_SESSION['alert'] = [
        'type' => 'danger',
        'message' => 'Error: El archivo excede el tamaño máximo permitido (5MB).'
    ];
    header("Location: ../ver_detalles.php?id=$vehiculo_id");
    exit;
}

// Método alternativo para verificar el tipo de archivo sin usar finfo_open()
$extension = strtolower(pathinfo($archivo['name'], PATHINFO_EXTENSION));
if (!in_array($extension, $allowed_extensions)) {
    $_SESSION['alert'] = [
        'type' => 'danger',
        'message' => 'Error: Tipo de archivo no permitido. Use PDF, JPG o PNG.'
    ];
    header("Location: ../ver_detalles.php?id=$vehiculo_id");
    exit;
}

// Crear directorio de uploads si no existe
$upload_dir = '../../uploads/'; // Subir un nivel más para llegar a la raíz del sitio

// Verificar y crear la ruta si no existe
if (!file_exists($upload_dir)) {
    if (!mkdir($upload_dir, 0777, true)) {
        // Log error if directory creation fails
        error_log("Failed to create directory: $upload_dir");
        
        $_SESSION['alert'] = [
            'type' => 'danger',
            'message' => 'Error: No se pudo crear el directorio para guardar archivos.'
        ];
        
        if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
            header('Content-Type: application/json');
            echo json_encode([
                'success' => false,
                'message' => 'Error: No se pudo crear el directorio para guardar archivos.'
            ]);
            exit;
        } else {
            header("Location: ../ver_detalles.php?id=$vehiculo_id");
            exit;
        }
    }
}

// Log the absolute path for debugging
error_log("Upload directory absolute path: " . realpath($upload_dir));

// Generar nombre único para el archivo
$extension = pathinfo($archivo['name'], PATHINFO_EXTENSION);
$nombre_archivo = uniqid() . '_' . time() . '.' . $extension;
$ruta_archivo = $upload_dir . $nombre_archivo;

// Verificar si ya existe un documento de este tipo para este vehículo
$query = "SELECT id, ruta_archivo FROM documentos WHERE vehiculo_id = :vehiculo_id AND tipo_documento = :tipo_documento";
$stmt = $pdo->prepare($query);
$stmt->bindParam(':vehiculo_id', $vehiculo_id);
$stmt->bindParam(':tipo_documento', $tipo_documento);
$stmt->execute();
$doc_existente = $stmt->fetch(PDO::FETCH_ASSOC);

// Definir los tipos de documentos válidos según el ENUM de la base de datos
$tipos_validos = [
    'permiso_circulacion_a', 'permiso_circulacion_b', 
    'certificado_homologacion', 'factura', 
    'certificado_inscripcion_a', 'certificado_inscripcion_b'
];

// Mapeo de nombres alternativos a valores válidos
$tipos_alternativos = [
    'seguro_obligatorio' => 'certificado_inscripcion_a',
    'certificado_inscripcion' => 'certificado_inscripcion_b',
    'certificado' => 'certificado_inscripcion_b'
];

// Si el tipo de documento no es válido, intentar usar un tipo alternativo
if (!in_array($tipo_documento, $tipos_validos) && isset($tipos_alternativos[$tipo_documento])) {
    $tipo_documento = $tipos_alternativos[$tipo_documento];
    error_log("Tipo de documento convertido a: " . $tipo_documento);
}

// Si no se encuentra el documento con el tipo exacto, verificar si existe con otro nombre
if (!$doc_existente) {
    // Buscar si existe algún documento de este tipo para este vehículo (independiente del nombre)
    $query = "SELECT id, ruta_archivo FROM documentos WHERE vehiculo_id = :vehiculo_id AND tipo_documento = :tipo_documento";
    $stmt = $pdo->prepare($query);
    $stmt->bindParam(':vehiculo_id', $vehiculo_id);
    $stmt->bindParam(':tipo_documento', $tipo_documento);
    $stmt->execute();
    $doc_existente = $stmt->fetch(PDO::FETCH_ASSOC);
}

// Mover el archivo subido
if (move_uploaded_file($archivo['tmp_name'], $ruta_archivo)) {
    // Establecer permisos de lectura para todos
    chmod($ruta_archivo, 0644);
    
    $ruta_relativa = 'uploads/' . $nombre_archivo; // Guardar ruta relativa desde la raíz
    
    if ($doc_existente) {
        // Actualizar documento existente
        $query = "UPDATE documentos SET 
                nombre_archivo = :nombre_archivo,
                ruta_archivo = :ruta_archivo,
                fecha_subida = NOW()
                WHERE id = :id";
        $stmt = $pdo->prepare($query);
        $stmt->bindParam(':nombre_archivo', $archivo['name']);
        $stmt->bindParam(':ruta_archivo', $ruta_relativa);
        $stmt->bindParam(':id', $doc_existente['id']);
        $stmt->execute();
        
        // Intentar eliminar el archivo anterior
        $ruta_anterior = '../../' . str_replace('formulario/', '', $doc_existente['ruta_archivo']);
        if (file_exists($ruta_anterior)) {
            @unlink($ruta_anterior);
        }
    } else {
        // Insertar nuevo documento
        $query = "INSERT INTO documentos (vehiculo_id, tipo_documento, nombre_archivo, ruta_archivo) 
        VALUES (:vehiculo_id, :tipo_documento, :nombre_archivo, :ruta_archivo)";
        $stmt = $pdo->prepare($query);
        $stmt->bindParam(':vehiculo_id', $vehiculo_id);
        $stmt->bindParam(':tipo_documento', $tipo_documento);
        $stmt->bindParam(':nombre_archivo', $archivo['name']);
        $stmt->bindParam(':ruta_archivo', $ruta_relativa);
        $stmt->execute();
    }
    
    // Cambiar el estado de la solicitud a "pendiente" si estaba rechazada
    $query = "UPDATE solicitudes SET 
            estado = 'pendiente', 
            fecha_actualizacion = NOW(),
            comentarios = CONCAT(comentarios, '\n\nDocumento actualizado: ', :tipo_doc)
            WHERE vehiculo_id = :vehiculo_id AND estado = 'rechazada'";
    $stmt = $pdo->prepare($query);
    $stmt->bindParam(':vehiculo_id', $vehiculo_id);
    $stmt->bindParam(':tipo_doc', $tipo_documento);
    // Eliminamos el parámetro de comentario
    $stmt->execute();
    
    // Crear notificación para los funcionarios
    $query = "SELECT placa_patente FROM vehiculos WHERE id = :vehiculo_id";
    $stmt = $pdo->prepare($query);
    $stmt->bindParam(':vehiculo_id', $vehiculo_id);
    $stmt->execute();
    $vehiculo = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($vehiculo) {
        require_once 'notificaciones.php';
        $mensaje = "Se ha actualizado un documento para el vehículo con patente {$vehiculo['placa_patente']}. La solicitud ha vuelto a estado pendiente.";
        
        // Obtener el usuario_id desde la tabla vehiculos
        $queryUsuario = "SELECT usuario_id FROM vehiculos WHERE id = :vehiculo_id";
        $stmtUsuario = $pdo->prepare($queryUsuario);
        $stmtUsuario->bindParam(':vehiculo_id', $vehiculo_id);
        $stmtUsuario->execute();
        $usuario = $stmtUsuario->fetch(PDO::FETCH_ASSOC);
        
        if ($usuario && isset($usuario['usuario_id'])) {
            crearNotificacion($usuario['usuario_id'], $vehiculo_id, $mensaje, 'info');
        }
    }
    
    $_SESSION['alert'] = [
        'type' => 'success',
        'message' => 'Documento actualizado correctamente. La solicitud ha vuelto a estado pendiente.'
    ];
    
    // Verificar si la solicitud es AJAX
    if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
        // Si es AJAX, devolver una respuesta JSON en lugar de redireccionar
        header('Content-Type: application/json');
        echo json_encode([
            'success' => true,
            'message' => 'Documento actualizado correctamente',
            'ruta_archivo' => $ruta_relativa,
            'tipo_documento' => $tipo_documento
        ]);
        exit;
    } else {
        // Si no es AJAX, redireccionar como antes
        header("Location: ../ver_detalles.php?id=$vehiculo_id");
        exit;
    }
} else {
    $_SESSION['alert'] = [
        'type' => 'danger',
        'message' => 'Error al subir el archivo.'
    ];
    
    // Verificar si la solicitud es AJAX
    if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
        // Si es AJAX, devolver una respuesta JSON en lugar de redireccionar
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false,
            'message' => 'Error al subir el archivo'
        ]);
        exit;
    } else {
        // Si no es AJAX, redireccionar como antes
        header("Location: ../ver_detalles.php?id=$vehiculo_id");
        exit;
    }
}
