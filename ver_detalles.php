<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Función para corregir rutas de archivos
function corregirRuta($ruta) {
    // Si la ruta contiene 'formulario/uploads', reemplazarla por la nueva ubicación
    $ruta = str_replace('formulario/uploads', 'uploads', $ruta);
    // También manejar rutas con barras invertidas (Windows)
    $ruta = str_replace('formulario\\uploads', 'uploads', $ruta);
    
    // Si la ruta es relativa y comienza con 'uploads/', añadir la URL base
    if (strpos($ruta, 'uploads/') === 0 || strpos($ruta, 'uploads\\') === 0) {
        // Obtener el protocolo y host actual
        $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https://' : 'http://';
        $host = $_SERVER['HTTP_HOST'];
        
        // Construir URL completa
        $ruta = $protocol . $host . '/' . $ruta;
    }
    
    return $ruta;
}

session_start();
require_once 'config/config.php';

$id = $_GET['id'];

// Fetch permit details
try {
    $query = "SELECT v.id as vehiculo_id, v.placa_patente, 
                     u.rut, u.nombre, u.apellido_paterno, u.apellido_materno, 
                     u.comuna, u.calle, u.numero, u.aclaratoria, u.telefono, u.email,
                     s.estado, s.fecha_solicitud
              FROM vehiculos v
              JOIN usuarios u ON v.usuario_id = u.id
              JOIN solicitudes s ON v.id = s.vehiculo_id
              WHERE v.id = :id";
    $stmt = $pdo->prepare($query);
    $stmt->bindParam(':id', $id);
    $stmt->execute();
    $permit = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$permit) {
        header('Location: index.php');
        exit;
    }
    
    // Fetch documents
    $queryDocs = "SELECT * FROM documentos WHERE vehiculo_id = :vehiculo_id";
    $stmtDocs = $pdo->prepare($queryDocs);
    $stmtDocs->bindParam(':vehiculo_id', $permit['vehiculo_id']);
    $stmtDocs->execute();
    $documents = $stmtDocs->fetchAll(PDO::FETCH_ASSOC);
    
    // Organize documents by type
    $docs = [];
    foreach ($documents as $doc) {
        $docs[$doc['tipo_documento']] = $doc;
    }
    
} catch(PDOException $e) {
    die("Error al obtener datos: " . $e->getMessage());
}

// Process form submission (approve/reject)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        try {
            if ($_POST['action'] === 'approve') {
                // Update status to approved
                $updateQuery = "UPDATE solicitudes SET estado = 'aprobada', fecha_actualizacion = NOW() WHERE vehiculo_id = :vehiculo_id";
                $updateStmt = $pdo->prepare($updateQuery);
                $updateStmt->bindParam(':vehiculo_id', $permit['vehiculo_id']);
                $updateStmt->execute();
                
                // Obtener el usuario_id desde la tabla vehiculos
                $queryUsuario = "SELECT usuario_id FROM vehiculos WHERE id = :vehiculo_id";
                $stmtUsuario = $pdo->prepare($queryUsuario);
                $stmtUsuario->bindParam(':vehiculo_id', $permit['vehiculo_id']);
                $stmtUsuario->execute();
                $usuario = $stmtUsuario->fetch(PDO::FETCH_ASSOC);
                
                if ($usuario && isset($usuario['usuario_id'])) {
                    require_once 'includes/notificaciones.php';
                    $mensaje = "Su solicitud para el vehículo con patente {$permit['placa_patente']} ha sido aprobada.";
                    crearNotificacion($usuario['usuario_id'], $permit['vehiculo_id'], $mensaje, 'success');
                }
                
                // Enviar correo de aprobación
                if (!empty($permit['email'])) {
                    require_once 'includes/mail_sender.php';
                    $subject = "Aprobación de Solicitud de Traslado";
                    $htmlMessage = "Estimado/a {$permit['nombre']},<br><br>";
                    $htmlMessage .= "Sus documentos fueron aprobados existosamente por el departamento, ahora puede proceder a pagar en el siguiente link:<br><br>";
                    $htmlMessage .= "<a href='https://ww3.e-com.cl/Pagos/PermisoCirculacion/renovacion/ecomv3/vista/?id=88&plebcas=12&portal=%2707/03/2025%27&html=70&opc=1'>Realizar pago del permiso</a><br><br>";
                    $htmlMessage .= "Saludos cordiales,<br>";
                    $htmlMessage .= "Departamento de Permisos de Circulación<br>";
                    $htmlMessage .= "Municipalidad de Melipilla";
                    
                    if (enviarCorreoAprobacion($permit['email'], $subject, $htmlMessage)) {
                        $_SESSION['alert'] = [
                            'type' => 'success',
                            'message' => 'La solicitud ha sido aprobada y se ha enviado el correo.'
                        ];
                    } else {
                        $_SESSION['alert'] = [
                            'type' => 'warning',
                            'message' => 'La solicitud ha sido aprobada pero hubo un problema al enviar el correo.'
                        ];
                    }
                }
                
                echo '<script>window.location.href = "index.php";</script>';
                exit;
            } elseif ($_POST['action'] === 'reject') {
                // Update status to rejected
                $updateQuery = "UPDATE solicitudes SET estado = 'rechazada', 
                               fecha_actualizacion = NOW(), 
                               comentarios = :comentarios 
                               WHERE vehiculo_id = :vehiculo_id";
                $updateStmt = $pdo->prepare($updateQuery);
                $updateStmt->bindParam(':vehiculo_id', $permit['vehiculo_id']);
                $updateStmt->bindParam(':comentarios', $_POST['email_message']);
                $updateStmt->execute();

                // Obtener el usuario_id desde la tabla vehiculos
                $queryUsuario = "SELECT usuario_id FROM vehiculos WHERE id = :vehiculo_id";
                $stmtUsuario = $pdo->prepare($queryUsuario);
                $stmtUsuario->bindParam(':vehiculo_id', $permit['vehiculo_id']);
                $stmtUsuario->execute();
                $usuario = $stmtUsuario->fetch(PDO::FETCH_ASSOC);
                
                if ($usuario && isset($usuario['usuario_id'])) {
                    require_once 'includes/notificaciones.php';
                    $mensaje = "Su solicitud para el vehículo con patente {$permit['placa_patente']} ha sido rechazada.";
                    crearNotificacion($usuario['usuario_id'], $permit['vehiculo_id'], $mensaje, 'danger');
                }

                // Enviar correo de rechazo
                if (!empty($permit['email'])) {
                    require_once 'includes/mail_sender.php';
                    $subject = "Rechazo de Solicitud de Permiso de Circulación";
                    $htmlMessage = nl2br($_POST['email_message']);
                    
                    if (enviarCorreo($permit['email'], $subject, $htmlMessage)) {
                        $_SESSION['alert'] = [
                            'type' => 'success',
                            'message' => 'La solicitud ha sido rechazada y se ha enviado el correo.'
                        ];
                    } else {
                        $_SESSION['alert'] = [
                            'type' => 'warning',
                            'message' => 'La solicitud ha sido rechazada pero hubo un problema al enviar el correo.'
                        ];
                    }
                }
                echo '<script>window.location.href = "index.php";</script>';
                exit;
            }
        } catch(PDOException $e) {
            die("Error al actualizar estado: " . $e->getMessage());
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Detalles del Permiso de Circulación</title>
    <link rel="icon" type="image/png" href="imagenes/icono_web.png">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <style>
        :root {
            --primary-color: #0F0758;
            --primary-light: #1a0d7a;
            --primary-dark: #0a0545;
            --secondary-color: #f8fafc;
            --accent-color: #3b82f6;
            --success-color: #10b981;
            --warning-color: #f59e0b;
            --error-color: #ef4444;
            --text-primary: #1f2937;
            --text-secondary: #6b7280;
            --border-color: #e5e7eb;
            --border-radius: 12px;
            --shadow-sm: 0 1px 2px 0 rgb(0 0 0 / 0.05);
            --shadow-md: 0 4px 6px -1px rgb(0 0 0 / 0.1), 0 2px 4px -2px rgb(0 0 0 / 0.1);
            --shadow-lg: 0 10px 15px -3px rgb(0 0 0 / 0.1), 0 4px 6px -4px rgb(0 0 0 / 0.1);
            --transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            background-image: url('fondo.svg');
            background-repeat: repeat-y;
            background-size: 100% auto;
            background-position: center top;
            background-attachment: fixed;
            min-height: 100vh;
            color: var(--text-primary);
            line-height: 1.6;
        }

        /* Navigation */
        .navbar {
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--primary-light) 100%);
            padding: 0.9rem 0;
            box-shadow: var(--shadow-lg);
            position: sticky;
            top: 0;
            z-index: 100;
        }

        .navbar-container {
            max-width: 1647px;
            margin: 0 auto;
            padding: 0 2rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .navbar-brand {
            display: flex;
            align-items: center;
            color: white;
            text-decoration: none;
        }

        .navbar-logo {
            width: 180px;
            height: auto;
            filter: brightness(0) invert(1);
            transition: var(--transition);
        }

        .navbar-actions {
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .btn-back {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.75rem 1.5rem;
            background: rgba(255, 255, 255, 0.1);
            color: white;
            text-decoration: none;
            border-radius: var(--border-radius);
            font-weight: 500;
            transition: var(--transition);
            border: 1px solid rgba(255, 255, 255, 0.2);
            white-space: nowrap;
            font-size: 0.9rem;
        }

        .btn-back:hover {
            background: rgba(255, 255, 255, 0.2);
            color: white;
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
        }

        .btn-back:active {
            transform: translateY(0);
            box-shadow: 0 2px 6px rgba(0, 0, 0, 0.2);
        }

        .btn-back i {
            font-size: 1rem;
        }

        /* Responsive styles for the back button */
        @media (max-width: 768px) {
            .navbar-container {
                gap: 1rem;
                padding: 1rem;
            }
            
            .navbar-logo {
                width: 150px;
            }
            
            .navbar-actions {
                width: 100%;
                justify-content: flex-end;
            }
            
            .btn-back {
                padding: 0.6rem 1.2rem;
                font-size: 0.85rem;
                min-width: 140px;
                justify-content: center;
            }
            
            .btn-back i {
                font-size: 0.9rem;
            }
        }

        @media (max-width: 480px) {
            .navbar-container {
                padding: 0.75rem;
            }
            
            .navbar-logo {
                width: 120px;
            }
            
            .btn-back {
                padding: 0.5rem 1rem;
                font-size: 0.8rem;
                min-width: 120px;
            }
            
            .btn-back i {
                font-size: 0.85rem;
            }
        }

        @media (max-width: 360px) {
            .navbar-container {
                padding: 0.5rem;
            }
            
            .navbar-logo {
                width: 100px;
            }
            
            .btn-back {
                padding: 0.4rem 0.8rem;
                font-size: 0.75rem;
                min-width: 100px;
                width: 122px;
            }
            
            .btn-back i {
                font-size: 0.8rem;
            }
        }

        /* Touch device optimizations */
        @media (hover: none) and (pointer: coarse) {
            .btn-back {
                min-height: 44px; /* Minimum touch target size */
                padding: 0.75rem 1.5rem;
            }
            
            .btn-back:hover {
                transform: none;
            }
            
            .btn-back:active {
                background: rgba(255, 255, 255, 0.3);
                transform: scale(0.98);
            }
        }

        /* Main Container */
        .main-container {
            max-width: 1200px;
            margin: 2rem auto;
            padding: 0 2rem;
        }

        .page-header {
            margin-bottom: 2rem;
        }

        .page-title {
            font-size: 2.5rem;
            font-weight: 700;
            color: var(--text-primary);
            margin-bottom: 0.5rem;
        }

        .page-subtitle {
            color: var(--text-secondary);
            font-size: 1.1rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        /* Alert Messages */
        .alert {
            padding: 1rem 1.5rem;
            border-radius: var(--border-radius);
            margin-bottom: 2rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
            animation: slideIn 0.5s ease-out;
            border: 1px solid;
        }

        .alert-success {
            background: rgba(16, 185, 129, 0.1);
            color: #065f46;
            border-color: rgba(16, 185, 129, 0.2);
        }

        .alert-warning {
            background: rgba(245, 158, 11, 0.1);
            color: #92400e;
            border-color: rgba(245, 158, 11, 0.2);
        }

        @keyframes slideIn {
            from { opacity: 0; transform: translateY(-20px); }
            to { opacity: 1; transform: translateY(0); }
        }

        /* Cards */
        .info-card {
            background: white;
            border-radius: 20px;
            box-shadow: var(--shadow-lg);
            overflow: hidden;
            margin-bottom: 2rem;
        }

        .card-header {
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--primary-light) 100%);
            padding: 1.5rem 2rem;
            color: white;
        }

        .card-title {
            font-size: 1.25rem;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .card-body {
            padding: 2rem;
        }

        /* Info Grid */
        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 2rem;
            margin-bottom: 2rem;
        }
        
        /* En pantallas de escritorio (más de 1024px), mostrar en 2 columnas */
        @media (min-width: 1024px) {
            .info-grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }

        .info-section {
            background: var(--secondary-color);
            padding: 1.5rem;
            border-radius: var(--border-radius);
            border: 1px solid var(--border-color);
        }

        .section-title {
            font-size: 1.1rem;
            font-weight: 600;
            color: var(--primary-color);
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .info-item {
            margin-bottom: 1rem;
        }

        .info-label {
            font-size: 0.85rem;
            font-weight: 500;
            color: var(--text-secondary);
            text-transform: uppercase;
            letter-spacing: 0.05em;
            margin-bottom: 0.25rem;
        }

        .info-value {
            font-size: 1rem;
            font-weight: 500;
            color: var(--text-primary);
        }

        /* Status Badge */
        .status-badge {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.75rem 1.25rem;
            border-radius: 50px;
            font-weight: 600;
            font-size: 0.9rem;
            text-transform: capitalize;
        }

        .status-badge.success {
            background: rgba(16, 185, 129, 0.1);
            color: #065f46;
            border: 2px solid rgba(16, 185, 129, 0.2);
        }

        .status-badge.warning {
            background: rgba(245, 158, 11, 0.1);
            color: #92400e;
            border: 2px solid rgba(245, 158, 11, 0.2);
        }

        .status-badge.danger {
            background: rgba(239, 68, 68, 0.1);
            color: #991b1b;
            border: 2px solid rgba(239, 68, 68, 0.2);
        }

        /* Documents Section */
        .documents-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(350px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .document-card {
            background: white;
            border: 2px solid var(--border-color);
            border-radius: var(--border-radius);
            padding: 1.5rem;
            transition: var(--transition);
            position: relative;
        }

        .document-card:hover {
            border-color: var(--primary-color);
            box-shadow: var(--shadow-md);
            transform: translateY(-2px);
        }

        .document-card.available {
            border-color: var(--success-color);
            background: rgba(16, 185, 129, 0.02);
        }

        .document-card.missing {
            border-color: var(--error-color);
            background: rgba(239, 68, 68, 0.02);
        }

        .document-title {
            font-size: 1rem;
            font-weight: 600;
            color: var(--text-primary);
            margin-bottom: 1rem;
            display: flex;
            align-items: flex-start;
            gap: 0.5rem;
        }

        .document-required {
            color: var(--error-color);
            font-size: 0.75rem;
            font-weight: 700;
        }

        .document-status {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            margin-bottom: 1rem;
            font-size: 0.9rem;
        }

        .document-status.available {
            color: var(--success-color);
        }

        .document-status.missing {
            color: var(--error-color);
        }

        .document-actions {
            display: flex;
            gap: 0.75rem;
            flex-wrap: wrap;
        }

        /* Buttons */
        .btn {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.75rem 1.25rem;
            border: none;
            border-radius: var(--border-radius);
            font-weight: 500;
            font-size: 0.9rem;
            cursor: pointer;
            transition: var(--transition);
            text-decoration: none;
            text-align: center;
            justify-content: center;
        }

        .btn-primary {
            background: var(--primary-color);
            color: white;
        }

        .btn-primary:hover {
            background: var(--primary-light);
            color: white;
            transform: translateY(-1px);
            box-shadow: var(--shadow-md);
        }

        .btn-success {
            background: var(--success-color);
            color: white;
            width: 100%;
        }

        .btn-success:hover {
            background: #059669;
            color: white;
            transform: translateY(-1px);
            box-shadow: var(--shadow-md);
        }

        .btn-danger {
            background: var(--error-color);
            color: white;
        }

        .btn-danger:hover {
            background: #dc2626;
            color: white;
            transform: translateY(-1px);
            box-shadow: var(--shadow-md);
        }

        .btn-warning {
            background: var(--warning-color);
            color: white;
        }

        .btn-warning:hover {
            background: #d97706;
            color: white;
            transform: translateY(-1px);
            box-shadow: var(--shadow-md);
        }

        .btn-outline {
            background: transparent;
            border: 2px solid var(--border-color);
            color: var(--text-primary);
        }

        .btn-outline:hover {
            border-color: var(--primary-color);
            color: var(--primary-color);
        }

        /* Action Buttons Section */
        .action-section {
            background: white;
            border-radius: 20px;
            box-shadow: var(--shadow-lg);
            padding: 2rem;
            margin-bottom: 2rem;
        }

        .action-buttons {
            display: flex;
            gap: 1rem;
            justify-content: center;
            flex-wrap: wrap;
        }

        .action-info {
            background: rgba(59, 130, 246, 0.1);
            border: 1px solid rgba(59, 130, 246, 0.2);
            border-radius: var(--border-radius);
            padding: 1.5rem;
            margin-bottom: 1.5rem;
            color: #1e40af;
        }

        /* Modal Styles */
        .modal-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            display: none;
            justify-content: center;
            align-items: center;
            z-index: 1000;
            backdrop-filter: blur(4px);
        }

        .modal-content {
            background: white;
            border-radius: 20px;
            box-shadow: var(--shadow-lg);
            max-width: 600px;
            width: 90%;
            max-height: 90vh;
            overflow-y: auto;
        }

        .modal-header {
            background: linear-gradient(135deg, var(--error-color) 0%, #dc2626 100%);
            padding: 1.5rem 2rem;
            color: white;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .modal-title {
            font-size: 1.25rem;
            font-weight: 600;
        }

        .modal-close {
            background: none;
            border: none;
            color: white;
            font-size: 1.5rem;
            cursor: pointer;
            padding: 0.25rem;
            border-radius: 50%;
            transition: var(--transition);
        }

        .modal-close:hover {
            background: rgba(255, 255, 255, 0.1);
        }

        .modal-body {
            padding: 2rem;
        }

        .modal-footer {
            padding: 1.5rem 2rem;
            border-top: 1px solid var(--border-color);
            display: flex;
            gap: 1rem;
            justify-content: flex-end;
        }

        /* Form Elements */
        .form-group {
            margin-bottom: 1.5rem;
        }

        .form-label {
            display: block;
            font-weight: 500;
            color: var(--text-primary);
            margin-bottom: 0.5rem;
        }

        .form-input {
            width: 100%;
            padding: 0.875rem 1rem;
            border: 2px solid var(--border-color);
            border-radius: var(--border-radius);
            font-size: 1rem;
            transition: var(--transition);
            background: white;
            font-family: inherit;
        }

        .form-input:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(15, 7, 88, 0.1);
        }

        .form-textarea {
            resize: vertical;
            min-height: 120px;
        }

        /* Loading Overlay */
        .loading-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(255, 255, 255, 0.95);
            display: none;
            justify-content: center;
            align-items: center;
            z-index: 1000;
            backdrop-filter: blur(4px);
        }

        .loading-content {
            text-align: center;
            background: white;
            padding: 2rem;
            border-radius: 20px;
            box-shadow: var(--shadow-lg);
        }

        .loading-spinner {
            width: 50px;
            height: 50px;
            border: 4px solid var(--border-color);
            border-top: 4px solid var(--primary-color);
            border-radius: 50%;
            animation: spin 1s linear infinite;
            margin: 0 auto 1rem;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        /* Responsive Design */
        @media (max-width: 1024px) {
            .main-container {
                padding: 0 1rem;
            }

            .navbar-container {
                padding: 0 1rem;
            }

            .info-grid {
                grid-template-columns: 1fr;
            }

            .documents-grid {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 768px) {
            .page-title {
                font-size: 2rem;
            }

            .card-body {
                padding: 1.5rem;
            }

            .action-buttons {
                flex-direction: column;
            }

            .btn {
                justify-content: center;
            }

            .navbar-logo {
                width: 120px;
            }

            .modal-content {
                width: 95%;
            }

            .modal-body {
                padding: 1.5rem;
            }
        }
    </style>
</head>
<body>
    <!-- Hidden field for JavaScript -->
    <input type="hidden" id="permiso_id" value="<?php echo $permit['vehiculo_id']; ?>">
    
    <!-- Navigation -->
    <nav class="navbar">
        <div class="navbar-container">
            <a href="index.php" class="navbar-brand">
                <img src="imagenes/logo_blanco.png" alt="Logo Melipilla" class="navbar-logo">
            </a>
            <div class="navbar-actions">
                <a href="index.php" class="btn-back">
                    <i class="fas fa-arrow-left"></i>
                    Volver al listado
                </a>
            </div>
        </div>
    </nav>

    <!-- Main Container -->
    <div class="main-container">
        <!-- Page Header -->
        <div class="page-header">
            <h1 class="page-title">Detalles del Permiso</h1>
            <p class="page-subtitle">
                <i class="fas fa-id-card"></i>
                Solicitud #<?php echo $permit['vehiculo_id']; ?> - <?php echo $permit['placa_patente']; ?>
            </p>
        </div>
        
        <!-- Alert Messages -->
        <?php if (isset($_SESSION['alert'])): ?>
        <div class="alert alert-<?php echo $_SESSION['alert']['type']; ?>">
            <i class="fas <?php echo $_SESSION['alert']['type'] == 'success' ? 'fa-check-circle' : 'fa-exclamation-triangle'; ?>"></i>
            <?php echo $_SESSION['alert']['message']; ?>
        </div>
        <?php 
        unset($_SESSION['alert']);
        endif; 
        ?>
        
        <!-- Information Cards -->
        <div class="info-grid">
            <!-- Personal Information -->
            <div class="info-section">
                <div class="section-title">
                    <i class="fas fa-user"></i>
                    Información Personal
                </div>
                <div class="info-item">
                    <div class="info-label">RUT</div>
                    <div class="info-value"><?php echo $permit['rut']; ?></div>
                </div>
                <div class="info-item">
                    <div class="info-label">Nombre Completo</div>
                    <div class="info-value"><?php echo $permit['nombre'] . ' ' . $permit['apellido_paterno'] . ' ' . $permit['apellido_materno']; ?></div>
                </div>
                <div class="info-item">
                    <div class="info-label">Email</div>
                    <div class="info-value"><?php echo $permit['email']; ?></div>
                </div>
                <div class="info-item">
                    <div class="info-label">Teléfono</div>
                    <div class="info-value">+56 <?php echo $permit['telefono']; ?></div>
                </div>
            </div>

            <!-- Address Information -->
            <div class="info-section">
                <div class="section-title">
                    <i class="fas fa-map-marker-alt"></i>
                    Dirección
                </div>
                <div class="info-item">
                    <div class="info-label">Comuna</div>
                    <div class="info-value"><?php echo $permit['comuna']; ?></div>
                </div>
                <div class="info-item">
                    <div class="info-label">Dirección</div>
                    <div class="info-value">
                        <?php echo $permit['calle'] . ' ' . $permit['numero']; ?>
                        <?php if (!empty($permit['aclaratoria'])): ?>
                            <br><small style="color: var(--text-secondary);"><?php echo $permit['aclaratoria']; ?></small>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Vehicle Information -->
            <div class="info-section">
                <div class="section-title">
                    <i class="fas fa-car"></i>
                    Información del Vehículo
                </div>
                <div class="info-item">
                    <div class="info-label">Placa Patente</div>
                    <div class="info-value" style="font-size: 1.25rem; font-weight: 700;"><?php echo $permit['placa_patente']; ?></div>
                </div>
            </div>

            <!-- Status Information -->
            <div class="info-section">
                <div class="section-title">
                    <i class="fas fa-info-circle"></i>
                    Estado de la Solicitud
                </div>
                <div class="info-item">
                    <div class="info-label">Estado Actual</div>
                    <div class="info-value">
                        <?php 
                        $badgeClass = '';
                        switch($permit['estado']) {
                            case 'aprobada':
                                $badgeClass = 'success';
                                $icon = 'fa-check-circle';
                                break;
                            case 'rechazada':
                                $badgeClass = 'danger';
                                $icon = 'fa-times-circle';
                                break;
                            default:
                                $badgeClass = 'warning';
                                $icon = 'fa-clock';
                        }
                        ?>
                        <span class="status-badge <?php echo $badgeClass; ?>">
                            <i class="fas <?php echo $icon; ?>"></i>
                            <?php echo ucfirst($permit['estado']); ?>
                        </span>
                    </div>
                </div>
                <div class="info-item">
                    <div class="info-label">Fecha de Solicitud</div>
                    <div class="info-value"><?php echo date('d/m/Y H:i', strtotime($permit['fecha_solicitud'])); ?></div>
                </div>
            </div>
        </div>

        <!-- Documents Section -->
        <div class="info-card">
            <div class="card-header">
                <div class="card-title">
                    <i class="fas fa-file-alt"></i>
                    Documentos Adjuntos
                </div>
            </div>
            <div class="card-body">
                <div class="documents-grid">
                    <!-- Permiso de Circulación -->
                    <div class="document-card <?php echo isset($docs['permiso_circulacion_a']) ? 'available' : 'missing'; ?>">
                        <div class="document-title">
                            <i class="fas fa-file-pdf"></i>
                            Permiso de Circulación año anterior
                            <span class="document-required">*</span>
                        </div>
                        <div class="document-status <?php echo isset($docs['permiso_circulacion_a']) ? 'available' : 'missing'; ?>">
                            <i class="fas <?php echo isset($docs['permiso_circulacion_a']) ? 'fa-check-circle' : 'fa-times-circle'; ?>"></i>
                            <?php echo isset($docs['permiso_circulacion_a']) ? 'Documento disponible' : 'Documento no disponible'; ?>
                        </div>
                        <div class="document-actions">
                            <?php if (isset($docs['permiso_circulacion_a'])): ?>
                                <a href="<?php echo corregirRuta($docs['permiso_circulacion_a']['ruta_archivo']); ?>" class="btn btn-primary" target="_blank">
                                    <i class="fas fa-eye"></i> Ver Documento
                                </a>
                                <?php if ($permit['estado'] === 'rechazada'): ?>
                                    <button type="button" class="btn btn-warning" 
                                            data-bs-toggle="modal" 
                                            data-bs-target="#updateDocModal" 
                                            data-doc-type="permiso_circulacion_a"
                                            data-doc-name="Permiso de Circulación año anterior">
                                        <i class="fas fa-upload"></i> Actualizar
                                    </button>
                                <?php endif; ?>
                            <?php else: ?>
                                <?php if ($permit['estado'] === 'rechazada'): ?>
                                    <button type="button" class="btn btn-warning" 
                                            data-bs-toggle="modal" 
                                            data-bs-target="#updateDocModal" 
                                            data-doc-type="permiso_circulacion_a"
                                            data-doc-name="Permiso de Circulación año anterior">
                                        <i class="fas fa-upload"></i> Subir Documento
                                    </button>
                                <?php endif; ?>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Certificado de Homologación -->
                    <div class="document-card <?php echo isset($docs['certificado_homologacion']) ? 'available' : 'missing'; ?>">
                        <div class="document-title">
                            <i class="fas fa-certificate"></i>
                            Certificado de Homologación
                            <span class="document-required">*</span>
                        </div>
                        <div class="document-status <?php echo isset($docs['certificado_homologacion']) ? 'available' : 'missing'; ?>">
                            <i class="fas <?php echo isset($docs['certificado_homologacion']) ? 'fa-check-circle' : 'fa-times-circle'; ?>"></i>
                            <?php echo isset($docs['certificado_homologacion']) ? 'Documento disponible' : 'Documento no disponible'; ?>
                        </div>
                        <div class="document-actions">
                            <?php if (isset($docs['certificado_homologacion'])): ?>
                                <a href="<?php echo corregirRuta($docs['certificado_homologacion']['ruta_archivo']); ?>?t=<?php echo time(); ?>" class="btn btn-primary" target="_blank">
                                    <i class="fas fa-eye"></i> Ver Documento
                                </a>
                                <?php if ($permit['estado'] === 'rechazada'): ?>
                                    <button type="button" class="btn btn-warning" 
                                            data-bs-toggle="modal" 
                                            data-bs-target="#updateDocModal" 
                                            data-doc-type="certificado_homologacion"
                                            data-doc-name="Certificado de Homologación">
                                        <i class="fas fa-upload"></i> Actualizar
                                    </button>
                                <?php endif; ?>
                            <?php else: ?>
                                <?php if ($permit['estado'] === 'rechazada'): ?>
                                    <button type="button" class="btn btn-warning" 
                                            data-bs-toggle="modal" 
                                            data-bs-target="#updateDocModal" 
                                            data-doc-type="certificado_homologacion"
                                            data-doc-name="Certificado de Homologación">
                                        <i class="fas fa-upload"></i> Subir Documento
                                    </button>
                                <?php endif; ?>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Seguro Obligatorio -->
                    <div class="document-card <?php echo (isset($docs['certificado_inscripcion_a']) || isset($docs['seguro_obligatorio'])) ? 'available' : 'missing'; ?>">
                        <div class="document-title">
                            <i class="fas fa-shield-alt"></i>
                            Seguro Obligatorio
                            <span class="document-required">*</span>
                        </div>
                        <div class="document-status <?php echo (isset($docs['certificado_inscripcion_a']) || isset($docs['seguro_obligatorio'])) ? 'available' : 'missing'; ?>">
                            <i class="fas <?php echo (isset($docs['certificado_inscripcion_a']) || isset($docs['seguro_obligatorio'])) ? 'fa-check-circle' : 'fa-times-circle'; ?>"></i>
                            <?php echo (isset($docs['certificado_inscripcion_a']) || isset($docs['seguro_obligatorio'])) ? 'Documento disponible' : 'Documento no disponible'; ?>
                        </div>
                        <div class="document-actions">
                            <?php if (isset($docs['certificado_inscripcion_a']) || isset($docs['seguro_obligatorio'])): ?>
                                <?php $docKey = isset($docs['seguro_obligatorio']) ? 'seguro_obligatorio' : 'certificado_inscripcion_a'; ?>
                                <a href="<?php echo corregirRuta($docs[$docKey]['ruta_archivo']); ?>" class="btn btn-primary" target="_blank">
                                    <i class="fas fa-eye"></i> Ver Documento
                                </a>
                                <?php if ($permit['estado'] === 'rechazada'): ?>
                                    <button type="button" class="btn btn-warning" 
                                            data-bs-toggle="modal" 
                                            data-bs-target="#updateDocModal" 
                                            data-doc-type="seguro_obligatorio"
                                            data-doc-name="Seguro Obligatorio">
                                        <i class="fas fa-upload"></i> Actualizar
                                    </button>
                                <?php endif; ?>
                            <?php else: ?>
                                <?php if ($permit['estado'] === 'rechazada'): ?>
                                    <button type="button" class="btn btn-warning" 
                                            data-bs-toggle="modal" 
                                            data-bs-target="#updateDocModal" 
                                            data-doc-type="seguro_obligatorio"
                                            data-doc-name="Seguro Obligatorio">
                                        <i class="fas fa-upload"></i> Subir Documento
                                    </button>
                                <?php endif; ?>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Certificado de Inscripción -->
                    <div class="document-card <?php echo (isset($docs['certificado_inscripcion']) || isset($docs['certificado_inscripcion_b'])) ? 'available' : 'missing'; ?>">
                        <div class="document-title">
                            <i class="fas fa-file-contract"></i>
                            Certificado de Inscripción
                        </div>
                        <div class="document-status <?php echo (isset($docs['certificado_inscripcion']) || isset($docs['certificado_inscripcion_b'])) ? 'available' : 'missing'; ?>">
                            <i class="fas <?php echo (isset($docs['certificado_inscripcion']) || isset($docs['certificado_inscripcion_b'])) ? 'fa-check-circle' : 'fa-times-circle'; ?>"></i>
                            <?php echo (isset($docs['certificado_inscripcion']) || isset($docs['certificado_inscripcion_b'])) ? 'Documento disponible' : 'Documento no disponible'; ?>
                        </div>
                        <div class="document-actions">
                            <?php if (isset($docs['certificado_inscripcion']) || isset($docs['certificado_inscripcion_b'])): ?>
                                <?php $docKey = isset($docs['certificado_inscripcion']) ? 'certificado_inscripcion' : 'certificado_inscripcion_b'; ?>
                                <a href="<?php echo corregirRuta($docs[$docKey]['ruta_archivo']); ?>" class="btn btn-primary" target="_blank">
                                    <i class="fas fa-eye"></i> Ver Documento
                                </a>
                                <?php if ($permit['estado'] === 'rechazada'): ?>
                                    <button type="button" class="btn btn-warning" 
                                            data-bs-toggle="modal" 
                                            data-bs-target="#updateDocModal" 
                                            data-doc-type="certificado_inscripcion"
                                            data-doc-name="Certificado de Inscripción">
                                        <i class="fas fa-upload"></i> Actualizar
                                    </button>
                                <?php endif; ?>
                            <?php else: ?>
                                <?php if ($permit['estado'] === 'rechazada'): ?>
                                    <button type="button" class="btn btn-warning" 
                                            data-bs-toggle="modal" 
                                            data-bs-target="#updateDocModal" 
                                            data-doc-type="certificado_inscripcion"
                                            data-doc-name="Certificado de Inscripción">
                                        <i class="fas fa-upload"></i> Subir Documento
                                    </button>
                                <?php endif; ?>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Factura -->
                    <div class="document-card <?php echo isset($docs['factura']) ? 'available' : 'missing'; ?>">
                        <div class="document-title">
                            <i class="fas fa-receipt"></i>
                            Factura (vehículos 2025)
                        </div>
                        <div class="document-status <?php echo isset($docs['factura']) ? 'available' : 'missing'; ?>">
                            <i class="fas <?php echo isset($docs['factura']) ? 'fa-check-circle' : 'fa-times-circle'; ?>"></i>
                            <?php echo isset($docs['factura']) ? 'Documento disponible' : 'Documento no disponible'; ?>
                        </div>
                        <div class="document-actions">
                            <?php if (isset($docs['factura'])): ?>
                                <a href="<?php echo corregirRuta($docs['factura']['ruta_archivo']); ?>" class="btn btn-primary" target="_blank">
                                    <i class="fas fa-eye"></i> Ver Documento
                                </a>
                                <?php if ($permit['estado'] === 'rechazada'): ?>
                                    <button type="button" class="btn btn-warning" 
                                            data-bs-toggle="modal" 
                                            data-bs-target="#updateDocModal" 
                                            data-doc-type="factura"
                                            data-doc-name="Factura">
                                        <i class="fas fa-upload"></i> Actualizar
                                    </button>
                                <?php endif; ?>
                            <?php else: ?>
                                <?php if ($permit['estado'] === 'rechazada'): ?>
                                    <button type="button" class="btn btn-warning" 
                                            data-bs-toggle="modal" 
                                            data-bs-target="#updateDocModal" 
                                            data-doc-type="factura"
                                            data-doc-name="Factura">
                                        <i class="fas fa-upload"></i> Subir Documento
                                    </button>
                                <?php endif; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Action Buttons -->
        <?php if ($permit['estado'] === 'pendiente'): ?>
        <div class="action-section">
            <div class="action-buttons">
                <form method="POST" class="d-inline">
                    <input type="hidden" name="action" value="approve">
                    <button type="submit" class="btn btn-success">
                        <i class="fas fa-check-circle"></i> Aprobar Solicitud
                    </button>
                </form>
                <button type="button" class="btn btn-danger" onclick="openRejectModal()">
                    <i class="fas fa-times-circle"></i> Rechazar Solicitud
                </button>
            </div>
        </div>
        <?php elseif ($permit['estado'] === 'rechazada'): ?>
        <div class="action-section">
            <div class="action-info">
                <i class="fas fa-info-circle"></i>
                <strong>Solicitud Rechazada:</strong> Actualice los documentos necesarios y luego apruebe la solicitud.
            </div>
            <div class="action-buttons">
                <form method="POST" class="d-inline">
                    <input type="hidden" name="action" value="approve">
                    <button type="submit" class="btn btn-success">
                        <i class="fas fa-check-circle"></i> Aprobar con Documentos Actualizados
                    </button>
                </form>
            </div>
        </div>
        <?php endif; ?>
    </div>
    
    <!-- Reject Modal -->
    <div class="modal-overlay" id="rejectModal">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Rechazar Solicitud</h5>
                <button type="button" class="modal-close" onclick="closeRejectModal()">&times;</button>
            </div>
            <div class="modal-body">
                <form id="rejectForm" method="post">
                    <input type="hidden" name="action" value="reject">
                    
                    <div class="form-group">
                        <label for="email" class="form-label">Correo del solicitante:</label>
                        <input type="email" class="form-input" id="email" name="email" value="<?php echo isset($permit['email']) ? $permit['email'] : ''; ?>" readonly>
                    </div>
                    
                    <div class="form-group">
                        <label for="email_message" class="form-label">Mensaje de rechazo:</label>
                        <textarea class="form-input form-textarea" id="email_message" name="email_message" required>Estimado/a <?php 
                        $nombre = '';
                        if (isset($permit['nombre'])) {
                            $nombre = $permit['nombre'];
                        } elseif (isset($permit['name'])) {
                            $nombre = $permit['name'];
                        }
                        echo $nombre;
                        ?>,

Lamentamos informarle que su solicitud de permiso de circulación para el vehículo con placa <?php 
echo isset($permit['placa_patente']) ? $permit['placa_patente'] : (isset($permit['plate']) ? $permit['plate'] : ''); 
?> ha sido rechazada.

Motivo del rechazo:
[Por favor, indique aquí el motivo específico del rechazo]

Si tiene alguna pregunta, no dude en contactarnos.

Saludos cordiales,
Departamento de Permisos de Circulación</textarea>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" onclick="closeRejectModal()">Cancelar</button>
                <button type="button" class="btn btn-danger" onclick="confirmReject()">Rechazar y Enviar Correo</button>
            </div>
        </div>
    </div>

    <!-- Update Document Modal -->
    <div class="modal-overlay" id="updateDocModal">
        <div class="modal-content">
            <div class="modal-header" style="background: linear-gradient(135deg, var(--warning-color) 0%, #d97706 100%);">
                <h5 class="modal-title" id="updateDocModalLabel">Actualizar Documento</h5>
                <button type="button" class="modal-close" onclick="closeUpdateModal()">&times;</button>
            </div>
            <div class="modal-body">
                <form method="POST" action="includes/actualizar_documento.php" enctype="multipart/form-data" class="update-doc-form">
                    <input type="hidden" name="vehiculo_id" value="<?php echo $permit['vehiculo_id']; ?>">
                    <input type="hidden" name="tipo_documento" id="tipo_documento" value="">
                    
                    <div class="form-group">
                        <label for="documento" class="form-label">Seleccione el nuevo documento:</label>
                        <input type="file" class="form-input" id="documento" name="documento" required>
                        <small style="color: var(--text-secondary); font-size: 0.85rem;">Formatos permitidos: PDF, JPG, PNG (máx. 5MB)</small>
                    </div>
                    
                    <div class="form-group">
                        <button type="submit" class="btn btn-primary" style="width: 100%;">
                            <i class="fas fa-upload"></i> Actualizar Documento
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Loading Overlay -->
    <div class="loading-overlay" id="loadingOverlay">
        <div class="loading-content">
            <div class="loading-spinner"></div>
            <h3>Procesando solicitud...</h3>
            <p>Por favor espere mientras procesamos su solicitud.</p>
        </div>
    </div>

    <script>
    // Modal functions
    function openRejectModal() {
        document.getElementById('rejectModal').style.display = 'flex';
    }

    function closeRejectModal() {
        document.getElementById('rejectModal').style.display = 'none';
    }

    function closeUpdateModal() {
        document.getElementById('updateDocModal').style.display = 'none';
    }

    function confirmReject() {
        document.getElementById('rejectForm').submit();
    }

    // Document update modal handling
    document.addEventListener('DOMContentLoaded', function() {
        // Mapeo de nombres de documentos para la interfaz
        const documentLabels = {
            'permiso_circulacion_a': 'Permiso de Circulación',
            'certificado_homologacion': 'Certificado de Homologación',
            'certificado_inscripcion_a': 'Seguro Obligatorio',
            'certificado_inscripcion_b': 'Certificado de Inscripción',
            'factura': 'Factura'
        };
        
        // Handle update document buttons
        document.querySelectorAll('[data-bs-toggle="modal"]').forEach(button => {
            button.addEventListener('click', function() {
                const docType = this.getAttribute('data-doc-type');
                const docName = this.getAttribute('data-doc-name') || documentLabels[docType] || docType;
                
                document.getElementById('updateDocModalLabel').textContent = 'Actualizar: ' + docName;
                document.getElementById('tipo_documento').value = docType;
                document.getElementById('updateDocModal').style.display = 'flex';
            });
        });

        // Handle form submissions with loading overlay
        document.querySelectorAll('form').forEach(form => {
            form.addEventListener('submit', function() {
                document.getElementById('loadingOverlay').style.display = 'flex';
            });
        });

        // Auto-dismiss alerts
        const alerts = document.querySelectorAll('.alert');
        alerts.forEach(alert => {
            setTimeout(() => {
                alert.style.opacity = '0';
                alert.style.transform = 'translateY(-20px)';
                setTimeout(() => {
                    alert.remove();
                }, 300);
            }, 5000);
        });

        // Sistema de visualización de permisos
        const permisoId = document.getElementById('permiso_id').value;
        
        // Registrar que el usuario está viendo este permiso
        fetch('includes/registrar_visualizacion.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                permiso_id: permisoId,
                accion: 'iniciar'
            })
        });
        
        // Registrar que el usuario dejó de ver el permiso cuando cierra la página
        window.addEventListener('beforeunload', function() {
            navigator.sendBeacon('includes/registrar_visualizacion.php', JSON.stringify({
                permiso_id: permisoId,
                accion: 'finalizar'
            }));
        });
    });

    // Close modals when clicking outside
    document.addEventListener('click', function(e) {
        if (e.target.classList.contains('modal-overlay')) {
            e.target.style.display = 'none';
        }
    });
    </script>
</body>
</html>
