<?php
require_once 'config/config.php';
require_once 'includes/mail_sender.php';
$mensaje = '';

// Solo mostrar mensajes si viene de un POST
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    try {
        // Verificar si la placa patente ya existe en la base de datos
        $checkPlaca = "SELECT COUNT(*) FROM vehiculos WHERE placa_patente = :placa_patente";
        $stmtCheck = $pdo->prepare($checkPlaca);
        $stmtCheck->bindParam(':placa_patente', $_POST['placa_patente']);
        $stmtCheck->execute();
        
        if ($stmtCheck->fetchColumn() > 0) {
            // La placa patente ya existe, mostrar mensaje de error
            $mensaje = '<div class="alert alert-error">
                            <i class="fas fa-exclamation-triangle"></i>
                            <h3>¡Error al procesar su solicitud!</h3>
                            <p>La placa patente ingresada ya existe en nuestro sistema. Por favor verifique la información.</p>
                        </div>';
        } else {
            // Iniciar transacción
            $pdo->beginTransaction();
            
            // Verificar si el usuario ya existe por RUT o por EMAIL
            $checkUsuario = "SELECT id FROM usuarios WHERE rut = :rut OR email = :email";
            $stmtCheckUsuario = $pdo->prepare($checkUsuario);
            $stmtCheckUsuario->bindParam(':rut', $_POST['rut']);
            $stmtCheckUsuario->bindParam(':email', $_POST['email']);
            $stmtCheckUsuario->execute();
            $usuarioExistente = $stmtCheckUsuario->fetch(PDO::FETCH_ASSOC);
            
            if ($usuarioExistente) {
                // Si el usuario ya existe, actualizar sus datos
                $sqlUsuario = "UPDATE usuarios SET 
                               nombre = :nombre, 
                               apellido_paterno = :apellido_paterno, 
                               apellido_materno = :apellido_materno, 
                               comuna = :comuna, 
                               calle = :calle, 
                               numero = :numero, 
                               aclaratoria = :aclaratoria, 
                               telefono = :telefono
                               WHERE id = :id";
                
                $stmtUsuario = $pdo->prepare($sqlUsuario);
                $stmtUsuario->bindParam(':id', $usuarioExistente['id']);
                $stmtUsuario->bindParam(':nombre', $_POST['nombre']);
                $stmtUsuario->bindParam(':apellido_paterno', $_POST['apellido_paterno']);
                $stmtUsuario->bindParam(':apellido_materno', $_POST['apellido_materno']);
                $stmtUsuario->bindParam(':comuna', $_POST['comuna']);
                $stmtUsuario->bindParam(':calle', $_POST['calle']);
                $stmtUsuario->bindParam(':numero', $_POST['numero']);
                $stmtUsuario->bindParam(':aclaratoria', $_POST['aclaratoria']);
                $stmtUsuario->bindParam(':telefono', $_POST['telefono']);
                $stmtUsuario->execute();
                
                $usuario_id = $usuarioExistente['id'];
            } else {
                // Si el usuario no existe, insertarlo
                $sqlUsuario = "INSERT INTO usuarios (rut, nombre, apellido_paterno, apellido_materno, comuna, calle, numero, aclaratoria, telefono, email) 
                              VALUES (:rut, :nombre, :apellido_paterno, :apellido_materno, :comuna, :calle, :numero, :aclaratoria, :telefono, :email)";
                
                $stmtUsuario = $pdo->prepare($sqlUsuario);
                $stmtUsuario->bindParam(':rut', $_POST['rut']);
                $stmtUsuario->bindParam(':nombre', $_POST['nombre']);
                $stmtUsuario->bindParam(':apellido_paterno', $_POST['apellido_paterno']);
                $stmtUsuario->bindParam(':apellido_materno', $_POST['apellido_materno']);
                $stmtUsuario->bindParam(':comuna', $_POST['comuna']);
                $stmtUsuario->bindParam(':calle', $_POST['calle']);
                $stmtUsuario->bindParam(':numero', $_POST['numero']);
                $stmtUsuario->bindParam(':aclaratoria', $_POST['aclaratoria']);
                $stmtUsuario->bindParam(':telefono', $_POST['telefono']);
                $stmtUsuario->bindParam(':email', $_POST['email']);
                $stmtUsuario->execute();
                
                $usuario_id = $pdo->lastInsertId();
            }
            
            // 2. Insertar en la tabla vehículos
            $sqlVehiculo = "INSERT INTO vehiculos (placa_patente, usuario_id) VALUES (:placa_patente, :usuario_id)";
            $stmtVehiculo = $pdo->prepare($sqlVehiculo);
            $stmtVehiculo->bindParam(':placa_patente', $_POST['placa_patente']);
            $stmtVehiculo->bindParam(':usuario_id', $usuario_id);
            $stmtVehiculo->execute();
            
            $vehiculo_id = $pdo->lastInsertId();
            
            // 3. Insertar en la tabla solicitudes
            $sqlSolicitud = "INSERT INTO solicitudes (vehiculo_id, estado) VALUES (:vehiculo_id, 'pendiente')";
            $stmtSolicitud = $pdo->prepare($sqlSolicitud);
            $stmtSolicitud->bindParam(':vehiculo_id', $vehiculo_id);
            $stmtSolicitud->execute();
            
            // 4. Procesar y subir documentos
            $uploadDir = 'uploads/';
            
            // Crear directorio si no existe
            if (!file_exists($uploadDir)) {
                mkdir($uploadDir, 0777, true);
            }
            
            $documentTypes = [
                'permiso_circulacion_a',
                'permiso_circulacion_b',
                'certificado_homologacion',
                'factura',
                'certificado_inscripcion_a',
                'certificado_inscripcion_b'
            ];
            
            foreach ($documentTypes as $docType) {
                if (isset($_FILES[$docType]) && $_FILES[$docType]['error'] == 0) {
                    $fileName = $_FILES[$docType]['name'];
                    $tmpName = $_FILES[$docType]['tmp_name'];
                    $fileNameNew = uniqid() . '_' . $fileName;
                    $fileDestination = $uploadDir . $fileNameNew;
                    
                    if (move_uploaded_file($tmpName, $fileDestination)) {
                        // Insertar información del documento en la tabla documentos
                        $sqlDocumento = "INSERT INTO documentos (vehiculo_id, tipo_documento, nombre_archivo, ruta_archivo) 
                                        VALUES (:vehiculo_id, :tipo_documento, :nombre_archivo, :ruta_archivo)";
                        $stmtDocumento = $pdo->prepare($sqlDocumento);
                        $stmtDocumento->bindParam(':vehiculo_id', $vehiculo_id);
                        $stmtDocumento->bindParam(':tipo_documento', $docType);
                        $stmtDocumento->bindParam(':nombre_archivo', $fileName);
                        $stmtDocumento->bindParam(':ruta_archivo', $fileDestination);
                        $stmtDocumento->execute();
                    }
                }
            }
            
            // Confirmar transacción
            $pdo->commit();
            
            // Enviar correo de confirmación al usuario
            if (!empty($_POST['email'])) {
                $subject = "Confirmación de Solicitud de Permiso de Circulación";
                $htmlMessage = "Estimado/a {$_POST['nombre']},<br><br>";
                $htmlMessage .= "Su solicitud de permiso de circulación ha sido recibida exitosamente.<br><br>";
                $htmlMessage .= "<strong>Detalles de la solicitud:</strong><br>";
                $htmlMessage .= "- Patente del vehículo: {$_POST['placa_patente']}<br>";
                $htmlMessage .= "- Estado: Pendiente de revisión<br>";
                $htmlMessage .= "- Fecha de solicitud: " . date('d/m/Y H:i') . "<br><br>";
                $htmlMessage .= "Su solicitud será revisada por nuestro equipo y recibirá una notificación cuando sea procesada.<br><br>";
                $htmlMessage .= "Si tiene alguna pregunta, puede contactarnos a través de los canales oficiales.<br><br>";
                $htmlMessage .= "Saludos cordiales,<br>";
                $htmlMessage .= "Departamento de Permisos de Circulación<br>";
                $htmlMessage .= "Municipalidad de Melipilla";
                
                $correoEnviado = enviarCorreo($_POST['email'], $subject, $htmlMessage);
                
                if ($correoEnviado) {
                    $mensaje = '<div class="alert alert-success">
                                    <i class="fas fa-check-circle"></i>
                                    <h3>¡Formulario enviado exitosamente!</h3>
                                    <p>Su solicitud ha sido recibida y está en proceso de revisión.</p>
                                    <p><strong>Se ha enviado un correo de confirmación a su dirección de email.</strong></p>
                                </div>';
                } else {
                    $mensaje = '<div class="alert alert-success">
                                    <i class="fas fa-check-circle"></i>
                                    <h3>¡Formulario enviado exitosamente!</h3>
                                    <p>Su solicitud ha sido recibida y está en proceso de revisión.</p>
                                    <p><strong>Nota:</strong> Hubo un problema al enviar el correo de confirmación, pero su solicitud fue procesada correctamente.</p>
                                </div>';
                }
            } else {
                $mensaje = '<div class="alert alert-success">
                                <i class="fas fa-check-circle"></i>
                                <h3>¡Formulario enviado exitosamente!</h3>
                                <p>Su solicitud ha sido recibida y está en proceso de revisión.</p>
                            </div>';
            }
            
            // Limpiar el formulario después de enviar
            $_POST = array();
        }
    } catch(PDOException $e) {
        // Revertir transacción en caso de error
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $mensaje = '<div class="alert alert-error">
                        <i class="fas fa-exclamation-triangle"></i>
                        <h3>¡Error al procesar su solicitud!</h3>
                        <p>Detalles: ' . $e->getMessage() . '</p>
                    </div>';
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Solicitud de Traslado Permisos de Circulación</title>
    <link rel="icon" type="image/png" href="imagenes/icono_web.png">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://code.jquery.com/ui/1.12.1/themes/base/jquery-ui.css">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://code.jquery.com/ui/1.12.1/jquery-ui.min.js"></script>
    
    <!-- Script de Microsoft Clarity -->
    <script type="text/javascript">
    (function(c,l,a,r,i,t,y){
        c[a]=c[a]||function(){(c[a].q=c[a].q||[]).push(arguments)};
        t=l.createElement(r);t.async=1;t.src="https://www.clarity.ms/tag/"+i;
        y=l.getElementsByTagName(r)[0];y.parentNode.insertBefore(t,y);
    })(window, document, "clarity", "script", "qo4ft3zpwa");
    </script>
    
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

        .container {
            max-width: 900px;
            margin: 2rem auto;
            padding: 0 1rem;
        }

        .form-wrapper {
            background: white;
            border-radius: 20px;
            box-shadow: var(--shadow-lg);
            overflow: hidden;
            position: relative;
        }

        .form-header {
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--primary-light) 100%);
            padding: 3rem 2rem;
            text-align: center;
            color: white;
            position: relative;
            overflow: hidden;
        }

        .form-header::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><defs><pattern id="grain" width="100" height="100" patternUnits="userSpaceOnUse"><circle cx="25" cy="25" r="1" fill="white" opacity="0.1"/><circle cx="75" cy="75" r="1" fill="white" opacity="0.1"/><circle cx="50" cy="10" r="0.5" fill="white" opacity="0.1"/><circle cx="10" cy="60" r="0.5" fill="white" opacity="0.1"/><circle cx="90" cy="40" r="0.5" fill="white" opacity="0.1"/></pattern></defs><rect width="100" height="100" fill="url(%23grain)"/></svg>');
            pointer-events: none;
        }

        .header-logo {
            width: 120px;
            height: auto;
            margin-bottom: 1.5rem;
            filter: brightness(0) invert(1);
            position: relative;
            z-index: 1;
        }

        .form-header h1 {
            font-size: 2rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
            position: relative;
            z-index: 1;
        }

        .form-header p {
            font-size: 1.1rem;
            opacity: 0.9;
            font-weight: 400;
            position: relative;
            z-index: 1;
        }

        /* Progress Stepper */
        .progress-stepper {
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 2rem;
            background: var(--secondary-color);
            position: relative;
        }

        .step {
            display: flex;
            align-items: center;
            position: relative;
        }

        .step-circle {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
            font-size: 1.1rem;
            transition: var(--transition);
            position: relative;
            z-index: 2;
        }

        .step-circle.active {
            background: var(--primary-color);
            color: white;
            box-shadow: 0 0 0 4px rgba(15, 7, 88, 0.2);
        }

        .step-circle.completed {
            background: var(--success-color);
            color: white;
        }

        .step-circle.inactive {
            background: #e5e7eb;
            color: var(--text-secondary);
        }

        .step-label {
            margin-left: 1rem;
            font-weight: 500;
            color: var(--text-primary);
        }

        .step-connector {
            width: 100px;
            height: 2px;
            background: #e5e7eb;
            margin: 0 2rem;
            position: relative;
        }

        .step-connector.completed {
            background: var(--success-color);
        }

        /* Form Content */
        .form-content {
            padding: 3rem 2rem;
        }

        .step-content {
            display: none;
            animation: fadeIn 0.5s ease-in-out;
        }

        .step-content.active {
            display: block;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .step-title {
            font-size: 1.5rem;
            font-weight: 600;
            color: var(--text-primary);
            margin-bottom: 0.5rem;
        }

        .step-description {
            color: var(--text-secondary);
            margin-bottom: 2rem;
            font-size: 1rem;
        }

        /* Form Groups */
        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1.5rem;
            margin-bottom: 1.5rem;
        }

        .form-group {
            position: relative;
        }

        .form-group.full-width {
            grid-column: 1 / -1;
        }

        .form-label {
            display: block;
            font-weight: 500;
            color: var(--text-primary);
            margin-bottom: 0.5rem;
            font-size: 0.95rem;
        }

        .form-input {
            width: 100%;
            padding: 0.875rem 1rem;
            border: 2px solid var(--border-color);
            border-radius: var(--border-radius);
            font-size: 1rem;
            transition: var(--transition);
            background: white;
        }

        .form-input:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(15, 7, 88, 0.1);
        }

        .form-input.valid {
            border-color: var(--success-color);
            background-color: rgba(16, 185, 129, 0.05);
        }

        .form-input.invalid {
            border-color: var(--error-color);
            background-color: rgba(239, 68, 68, 0.05);
        }

        .telefono-container {
            display: flex;
            border: 2px solid var(--border-color);
            border-radius: var(--border-radius);
            overflow: hidden;
            transition: var(--transition);
        }

        .telefono-container:focus-within {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(15, 7, 88, 0.1);
        }

        .telefono-prefix {
            background: var(--secondary-color);
            padding: 0.875rem 1rem;
            color: var(--text-secondary);
            font-weight: 500;
            border-right: 1px solid var(--border-color);
        }

        .telefono-container .form-input {
            border: none;
            border-radius: 0;
        }

        .telefono-container .form-input:focus {
            box-shadow: none;
        }

        /* File Upload Styling */
        .file-upload-area {
            border: 2px dashed var(--border-color);
            border-radius: var(--border-radius);
            padding: 2rem;
            text-align: center;
            transition: var(--transition);
            background: var(--secondary-color);
            margin-bottom: 1.5rem;
        }

        .file-upload-area:hover {
            border-color: var(--primary-color);
            background: rgba(15, 7, 88, 0.02);
        }

        .file-upload-item {
            background: white;
            border: 1px solid var(--border-color);
            border-radius: var(--border-radius);
            padding: 1.5rem;
            margin-bottom: 1rem;
            transition: var(--transition);
        }

        .file-upload-item:hover {
            box-shadow: var(--shadow-md);
        }

        .file-label {
            font-weight: 500;
            color: var(--text-primary);
            margin-bottom: 1rem;
            display: block;
        }

        .file-required {
            color: var(--error-color);
        }

        .file-input-wrapper {
            position: relative;
            display: inline-block;
        }

        .file-input {
            position: absolute;
            opacity: 0;
            width: 100%;
            height: 100%;
            cursor: pointer;
        }

        .file-button {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.75rem 1.5rem;
            background: var(--primary-color);
            color: white;
            border: none;
            border-radius: var(--border-radius);
            font-weight: 500;
            cursor: pointer;
            transition: var(--transition);
        }

        .file-button:hover {
            background: var(--primary-light);
            transform: translateY(-1px);
        }

        .file-status {
            margin-top: 0.5rem;
            font-size: 0.9rem;
            color: var(--text-secondary);
        }

        .file-status {
            margin-top: 0.5rem;
            font-size: 0.9rem;
            color: var(--text-secondary);
            display: flex;
            align-items: center;
        }

        .file-status.selected {
            color: var(--success-color);
            font-weight: 500;
        }

        .file-remove {
            background: none;
            color: var(--error-color);
            border: none;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            font-size: 1.2rem;
            font-weight: bold;
            transition: var(--transition);
            margin-left: 0.5rem;
        }

        .file-remove:hover {
            color: #dc2626;
            transform: scale(1.1);
        }

        /* Barra de progreso de archivos */
        .file-progress-container {
            margin-top: 1.5rem;
            padding: 1rem;
            background: var(--background-light);
            border-radius: var(--border-radius);
            border: 1px solid var(--border-color);
        }

        .file-progress-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 0.5rem;
        }

        .file-progress-text {
            font-size: 0.9rem;
            color: var(--text-secondary);
            font-weight: 500;
        }

        .file-progress-percentage {
            font-size: 0.85rem;
            color: var(--text-muted);
            font-weight: 600;
        }

        .file-progress-bar {
            width: 100%;
            height: 8px;
            background: #e5e7eb;
            border-radius: 4px;
            overflow: hidden;
        }

        .file-progress-fill {
            height: 100%;
            background: linear-gradient(90deg, var(--success-color), #059669);
            border-radius: 4px;
            transition: width 0.3s ease;
            width: 0%;
        }

        .file-progress-fill.warning {
            background: linear-gradient(90deg, var(--warning-color), #d97706);
        }

        .file-progress-fill.danger {
            background: linear-gradient(90deg, var(--error-color), #dc2626);
        }

        /* Estilos para alertas */
        .alert {
            margin: 1rem 0;
            padding: 1rem 1.25rem;
            border-radius: 12px;
            display: flex;
            align-items: center;
            gap: 0.75rem;
            animation: slideIn 0.5s ease-out;
            font-weight: 500;
        }

        .alert-error {
            background: rgba(239, 68, 68, 0.1);
            color: #991b1b;
            border: 1px solid rgba(239, 68, 68, 0.2);
        }

        .alert-info {
            background: rgba(59, 130, 246, 0.1);
            color: #1e40af;
            border: 1px solid rgba(59, 130, 246, 0.2);
        }

        @keyframes slideIn {
            from { opacity: 0; transform: translateY(-10px); }
            to { opacity: 1; transform: translateY(0); }
        }

        /* Navigation Buttons */
        .form-navigation {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 2rem;
            border-top: 1px solid var(--border-color);
            background: var(--secondary-color);
        }

        .btn {
            padding: 0.875rem 2rem;
            border: none;
            border-radius: var(--border-radius);
            font-weight: 500;
            font-size: 1rem;
            cursor: pointer;
            transition: var(--transition);
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }

        .btn-primary {
            background: var(--primary-color);
            color: white;
        }

        .btn-primary:hover {
            background: var(--primary-light);
            transform: translateY(-2px);
            box-shadow: var(--shadow-md);
        }

        .btn-secondary {
            background: white;
            color: var(--text-primary);
            border: 2px solid var(--border-color);
        }

        .btn-secondary:hover {
            border-color: var(--primary-color);
            color: var(--primary-color);
        }

        .btn:disabled {
            opacity: 0.6;
            cursor: not-allowed;
            transform: none !important;
        }

        /* Alerts */
        .alert {
            padding: 1.5rem;
            border-radius: var(--border-radius);
            margin-bottom: 2rem;
            display: flex;
            align-items: center;
            gap: 1rem;
            animation: slideIn 0.5s ease-out;
        }

        .alert-success {
            background: rgba(16, 185, 129, 0.1);
            color: #065f46;
            border: 1px solid rgba(16, 185, 129, 0.2);
        }

        .alert-error {
            background: rgba(239, 68, 68, 0.1);
            color: #991b1b;
            border: 1px solid rgba(239, 68, 68, 0.2);
        }

        @keyframes slideIn {
            from { opacity: 0; transform: translateY(-20px); }
            to { opacity: 1; transform: translateY(0); }
        }

        /* Info Box */
        .info-box {
            background: rgba(59, 130, 246, 0.1);
            border: 1px solid rgba(59, 130, 246, 0.2);
            border-radius: var(--border-radius);
            padding: 1.5rem;
            margin-bottom: 2rem;
        }

        .info-box h4 {
            color: #1e40af;
            margin-bottom: 0.5rem;
            font-weight: 600;
        }

        .info-box ul {
            color: #1e40af;
            margin-left: 1.5rem;
        }

        .info-box li {
            margin-bottom: 0.25rem;
        }

        /* Responsive Design */
        @media (max-width: 768px) {
            .container {
                margin: 1rem auto;
                padding: 0 0.5rem;
            }

            .form-header {
                padding: 2rem 1rem;
            }

            .form-header h1 {
                font-size: 1.5rem;
            }

            .form-content {
                padding: 2rem 1rem;
            }

            .form-row {
                grid-template-columns: 1fr;
                gap: 1rem;
            }

            .progress-stepper {
                padding: 1rem;
            }

            .step-connector {
                width: 50px;
                margin: 0 1rem;
            }

            .step-label {
                display: none;
            }

            .form-navigation {
                padding: 1rem;
            }

            .btn {
                padding: 0.75rem 1.5rem;
                font-size: 0.9rem;
            }
        }

        /* Loading Animation */
        .loading-container {
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
        }

        .loading-spinner {
            width: 50px;
            height: 50px;
            border: 4px solid #f3f4f6;
            border-top: 4px solid var(--primary-color);
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        /* Success Modal */
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
        }

        .modal-content {
            background: white;
            padding: 3rem;
            border-radius: 20px;
            text-align: center;
            max-width: 500px;
            width: 90%;
            box-shadow: var(--shadow-lg);
        }

        .modal-icon {
            font-size: 4rem;
            color: var(--success-color);
            margin-bottom: 1rem;
        }

        .modal-icon.error {
            color: var(--error-color);
        }

        .modal-close {
            position: absolute;
            top: 1rem;
            right: 1rem;
            background: none;
            border: none;
            font-size: 1.5rem;
            cursor: pointer;
            color: var(--text-secondary);
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="form-wrapper">
            <!-- Header -->
            <div class="form-header">
                <img src="imagenes/logo_blanco.png" alt="Logo Melipilla" class="header-logo">
                <h1>Solicitud de Traslado</h1>
                <p>Permisos de Circulación Otras Comunas</p>
            </div>

            <!-- Progress Stepper -->
            <div class="progress-stepper">
                <div class="step">
                    <div class="step-circle active" id="step1-circle">1</div>
                    <div class="step-label">Información Personal</div>
                </div>
                <div class="step-connector" id="connector1"></div>
                <div class="step">
                    <div class="step-circle inactive" id="step2-circle">2</div>
                    <div class="step-label">Documentos</div>
                </div>
            </div>

            <!-- Alert Messages -->
            <?php echo $mensaje; ?>

            <!-- Form -->
            <form id="multiStepForm" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="POST" enctype="multipart/form-data">
                <div class="form-content">
                    <!-- Step 1: Personal Information -->
                    <div class="step-content active" id="step1">
                        <h2 class="step-title">Información Personal</h2>
                        <p class="step-description">Complete sus datos personales y del vehículo</p>

                        <div class="form-row">
                            <div class="form-group">
                                <label for="rut" class="form-label">RUT <span class="file-required">*</span></label>
                                <input type="text" id="rut" name="rut" class="form-input" placeholder="11.111.111-1" required>
                            </div>
                            <div class="form-group">
                                <label for="placa_patente" class="form-label">Placa Patente <span class="file-required">*</span></label>
                                <input type="text" id="placa_patente" name="placa_patente" class="form-input" placeholder="ABCD34" required>
                            </div>
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label for="nombre" class="form-label">Nombre <span class="file-required">*</span></label>
                                <input type="text" id="nombre" name="nombre" class="form-input" required>
                            </div>
                            <div class="form-group">
                                <label for="apellido_paterno" class="form-label">Apellido Paterno <span class="file-required">*</span></label>
                                <input type="text" id="apellido_paterno" name="apellido_paterno" class="form-input" required>
                            </div>
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label for="apellido_materno" class="form-label">Apellido Materno <span class="file-required">*</span></label>
                                <input type="text" id="apellido_materno" name="apellido_materno" class="form-input" required>
                            </div>
                            <div class="form-group">
                                <label for="comuna" class="form-label">Comuna <span class="file-required">*</span></label>
                                <input type="text" id="comuna" name="comuna" class="form-input comuna-autocomplete" placeholder="Escriba para buscar comuna" required>
                            </div>
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label for="calle" class="form-label">Calle <span class="file-required">*</span></label>
                                <input type="text" id="calle" name="calle" class="form-input" required>
                            </div>
                            <div class="form-group">
                                <label for="numero" class="form-label">Número <span class="file-required">*</span></label>
                                <input type="text" id="numero" name="numero" class="form-input" required>
                            </div>
                        </div>

                        <div class="form-group full-width">
                            <label for="aclaratoria" class="form-label">Complemento (Block, Depto, Etc.)</label>
                            <input type="text" id="aclaratoria" name="aclaratoria" class="form-input">
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label for="telefono" class="form-label">Teléfono <span class="file-required">*</span></label>
                                <div class="telefono-container">
                                    <span class="telefono-prefix">+56</span>
                                    <input type="tel" id="telefono" name="telefono" class="form-input" required>
                                </div>
                            </div>
                            <div class="form-group">
                                <label for="email" class="form-label">Email <span class="file-required">*</span></label>
                                <input type="email" id="email" name="email" class="form-input" required>
                            </div>
                        </div>

                        <div class="form-group full-width">
                            <label for="confirmar_email" class="form-label">Confirmar Email <span class="file-required">*</span></label>
                            <input type="email" id="confirmar_email" name="confirmar_email" class="form-input" required>
                        </div>
                    </div>

                    <!-- Step 2: Documents -->
                    <div class="step-content" id="step2">
                        <h2 class="step-title">Documentos Requeridos</h2>
                        <p class="step-description">Adjunte los documentos necesarios para procesar su solicitud</p>

                        <div class="info-box">
                            <h4><i class="fas fa-info-circle"></i> Información importante:</h4>
                            <ul>
                                <li>Formatos permitidos: PDF, JPG, PNG, DOC, DOCX</li>
                                <li>Tamaño máximo total: 5 megabytes (MB)</li>
                                <li>Los campos marcados con <span class="file-required">*</span> son obligatorios</li>
                            </ul>
                        </div>

                        <div class="file-upload-item">
                            <label class="file-label">
                                Permiso de circulación año anterior <span class="file-required">*</span>
                            </label>
                            <div class="file-input-wrapper">
                                <input type="file" name="permiso_circulacion_a" class="file-input" required>
                                <button type="button" class="file-button">
                                    <i class="fas fa-upload"></i>
                                    Seleccionar archivo
                                </button>
                            </div>
                            <div class="file-status">
                                <span class="file-name">No se ha seleccionado ningún archivo</span>
                                <button type="button" class="file-remove" style="display: none;">
                                    <i class="fas fa-times"></i>
                                </button>
                            </div>
                        </div>

                        <div class="file-upload-item">
                            <label class="file-label">
                                Certificado de homologación o revisión técnica y análisis de gases (Debe estar vigente) <span class="file-required">*</span>
                            </label>
                            <div class="file-input-wrapper">
                                <input type="file" name="certificado_homologacion" class="file-input" required>
                                <button type="button" class="file-button">
                                    <i class="fas fa-upload"></i>
                                    Seleccionar archivo
                                </button>
                            </div>
                            <div class="file-status">
                                <span class="file-name">No se ha seleccionado ningún archivo</span>
                                <button type="button" class="file-remove" style="display: none;">
                                    <i class="fas fa-times"></i>
                                </button>
                            </div>
                        </div>

                        <div class="file-upload-item">
                            <label class="file-label">
                                Seguro obligatorio (con vencimiento el 31 de marzo de 2026) <span class="file-required">*</span>
                            </label>
                            <div class="file-input-wrapper">
                                <input type="file" name="certificado_inscripcion_a" class="file-input" required>
                                <button type="button" class="file-button">
                                    <i class="fas fa-upload"></i>
                                    Seleccionar archivo
                                </button>
                            </div>
                            <div class="file-status">
                                <span class="file-name">No se ha seleccionado ningún archivo</span>
                                <button type="button" class="file-remove" style="display: none;">
                                    <i class="fas fa-times"></i>
                                </button>
                            </div>
                        </div>

                        <div class="file-upload-item">
                            <label class="file-label">
                                Certificado de inscripción
                            </label>
                            <div class="file-input-wrapper">
                                <input type="file" name="certificado_inscripcion_b" class="file-input">
                                <button type="button" class="file-button">
                                    <i class="fas fa-upload"></i>
                                    Seleccionar archivo
                                </button>
                            </div>
                            <div class="file-status">
                                <span class="file-name">No se ha seleccionado ningún archivo</span>
                                <button type="button" class="file-remove" style="display: none;">
                                    <i class="fas fa-times"></i>
                                </button>
                            </div>
                        </div>

                        <div class="file-upload-item">
                            <label class="file-label">
                                Factura (solo para vehículos del año 2025)
                            </label>
                            <div class="file-input-wrapper">
                                <input type="file" name="factura" class="file-input">
                                <button type="button" class="file-button">
                                    <i class="fas fa-upload"></i>
                                    Seleccionar archivo
                                </button>
                            </div>
                            <div class="file-status">
                                <span class="file-name">No se ha seleccionado ningún archivo</span>
                                <button type="button" class="file-remove" style="display: none;">
                                    <i class="fas fa-times"></i>
                                </button>
                            </div>
                        </div>
                        
                        <!-- Barra de progreso de archivos -->
                        <div class="file-progress-container">
                            <div class="file-progress-header">
                                <span class="file-progress-text">Espacio utilizado: <span id="usedSpace">0</span>MB / 5MB</span>
                                <span class="file-progress-percentage" id="progressPercentage">0%</span>
                            </div>
                            <div class="file-progress-bar">
                                <div class="file-progress-fill" id="progressFill"></div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Navigation -->
                <div class="form-navigation">
                    <button type="button" id="prevBtn" class="btn btn-secondary" style="display: none;">
                        <i class="fas fa-arrow-left"></i>
                        Anterior
                    </button>
                    <div></div>
                    <button type="button" id="nextBtn" class="btn btn-primary">
                        Siguiente
                        <i class="fas fa-arrow-right"></i>
                    </button>
                    <button type="submit" id="submitBtn" class="btn btn-primary" style="display: none;">
                        <i class="fas fa-paper-plane"></i>
                        Enviar Solicitud
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Success Modal -->
    <div class="modal-overlay" id="successModal">
        <div class="modal-content">
            <div class="modal-icon">
                <i class="fas fa-check-circle"></i>
            </div>
            <h3>¡Formulario enviado exitosamente!</h3>
            <p>Su solicitud ha sido recibida y está en proceso de revisión.</p>
            <p>En las próximas horas o días recibirá un correo electrónico con la respuesta a su solicitud.</p>
        </div>
    </div>

    <!-- Error Modal -->
    <div class="modal-overlay" id="errorModal">
        <div class="modal-content">
            <div class="modal-icon error">
                <i class="fas fa-exclamation-triangle"></i>
            </div>
            <h3>Archivo demasiado grande</h3>
            <p id="errorModalMessage">Ha ocurrido un error al procesar su solicitud.</p>
        </div>
    </div>

    <!-- Loading Animation -->
    <div class="loading-container" id="loadingContainer">
        <div class="loading-spinner"></div>
    </div>

    <script>
        // Multi-step form functionality
        let currentStep = 1;
        const totalSteps = 2;

        // Elements
        const step1Content = document.getElementById('step1');
        const step2Content = document.getElementById('step2');
        const step1Circle = document.getElementById('step1-circle');
        const step2Circle = document.getElementById('step2-circle');
        const connector1 = document.getElementById('connector1');
        const prevBtn = document.getElementById('prevBtn');
        const nextBtn = document.getElementById('nextBtn');
        const submitBtn = document.getElementById('submitBtn');
        const form = document.getElementById('multiStepForm');

        // Navigation functions
        function showStep(step) {
            // Hide all steps
            document.querySelectorAll('.step-content').forEach(content => {
                content.classList.remove('active');
            });

            // Show current step
            if (step === 1) {
                step1Content.classList.add('active');
                step1Circle.classList.add('active');
                step1Circle.classList.remove('completed', 'inactive');
                step2Circle.classList.add('inactive');
                step2Circle.classList.remove('active', 'completed');
                connector1.classList.remove('completed');
                prevBtn.style.display = 'none';
                nextBtn.style.display = 'inline-flex';
                submitBtn.style.display = 'none';
            } else if (step === 2) {
                step2Content.classList.add('active');
                step1Circle.classList.add('completed');
                step1Circle.classList.remove('active', 'inactive');
                step2Circle.classList.add('active');
                step2Circle.classList.remove('inactive', 'completed');
                connector1.classList.add('completed');
                prevBtn.style.display = 'inline-flex';
                nextBtn.style.display = 'none';
                submitBtn.style.display = 'inline-flex';
            }
        }

        function validateStep1() {
            const requiredFields = [
                'rut', 'placa_patente', 'nombre', 'apellido_paterno', 
                'apellido_materno', 'comuna', 'calle', 'numero', 
                'telefono', 'email', 'confirmar_email'
            ];

            let isValid = true;

            requiredFields.forEach(fieldId => {
                const field = document.getElementById(fieldId);
                if (!field.value.trim()) {
                    field.classList.add('invalid');
                    isValid = false;
                } else {
                    field.classList.remove('invalid');
                    field.classList.add('valid');
                }
            });

            // Validate email confirmation
            const email = document.getElementById('email').value;
            const confirmEmail = document.getElementById('confirmar_email').value;
            
            if (email !== confirmEmail) {
                document.getElementById('confirmar_email').classList.add('invalid');
                isValid = false;
            }

            return isValid;
        }

        function validateStep2() {
            const requiredFiles = [
                'permiso_circulacion_a',
                'certificado_homologacion', 
                'certificado_inscripcion_a'
            ];

            let isValid = true;

            requiredFiles.forEach(fileName => {
                const fileInput = document.querySelector(`input[name="${fileName}"]`);
                if (!fileInput.files.length) {
                    fileInput.closest('.file-upload-item').style.borderColor = 'var(--error-color)';
                    isValid = false;
                } else {
                    fileInput.closest('.file-upload-item').style.borderColor = 'var(--success-color)';
                }
            });

            return isValid;
        }

        // Event listeners
        nextBtn.addEventListener('click', () => {
            if (currentStep === 1 && validateStep1()) {
                currentStep = 2;
                showStep(currentStep);
            }
        });

        prevBtn.addEventListener('click', () => {
            if (currentStep > 1) {
                currentStep = 1;
                showStep(currentStep);
            }
        });

        // Variables globales para el manejo de archivos
        let totalFileSize = 0;
        const MAX_TOTAL_SIZE = 5 * 1024 * 1024; // 5MB en bytes
        const MAX_FILE_SIZE = 2 * 1024 * 1024; // 2MB en bytes
        const fileData = new Map(); // Almacena información de cada archivo

        // Función para mostrar modal de error
        function showErrorModal(message) {
            const errorModal = document.getElementById('errorModal');
            const errorMessage = document.getElementById('errorModalMessage');
            
            errorMessage.textContent = message;
            errorModal.style.display = 'flex';
        }

        // Evento para cerrar modal al hacer clic en cualquier parte
        document.getElementById('errorModal').addEventListener('click', function(e) {
            this.style.display = 'none';
        });

        // Función para actualizar la barra de progreso
        function updateProgressBar() {
            const usedSpaceMB = (totalFileSize / (1024 * 1024)).toFixed(2);
            const percentage = Math.min((totalFileSize / MAX_TOTAL_SIZE) * 100, 100);
            
            document.getElementById('usedSpace').textContent = usedSpaceMB;
            document.getElementById('progressPercentage').textContent = `${percentage.toFixed(1)}%`;
            
            const progressFill = document.getElementById('progressFill');
            progressFill.style.width = `${percentage}%`;
            
            // Cambiar color según el porcentaje
            progressFill.classList.remove('warning', 'danger');
            if (percentage >= 80) {
                progressFill.classList.add('danger');
            } else if (percentage >= 60) {
                progressFill.classList.add('warning');
            }
        }

        // Función para eliminar archivo
        function removeFile(input) {
            const fileItem = input.closest('.file-upload-item');
            const button = fileItem.querySelector('.file-button');
            const status = fileItem.querySelector('.file-status');
            const fileName = fileItem.querySelector('.file-name');
            const removeBtn = fileItem.querySelector('.file-remove');
            
            // Restar el tamaño del archivo del total
            const fileInfo = fileData.get(input.name);
            if (fileInfo) {
                totalFileSize -= fileInfo.size;
                fileData.delete(input.name);
            }
            
            // Resetear el input
            input.value = '';
            
            // Actualizar UI
            fileName.textContent = 'No se ha seleccionado ningún archivo';
            status.classList.remove('selected');
            button.innerHTML = '<i class="fas fa-upload"></i> Seleccionar archivo';
            button.style.background = 'var(--primary-color)';
            removeBtn.style.display = 'none';
            
            // Actualizar barra de progreso
            updateProgressBar();
        }

        // File upload handling
        document.querySelectorAll('.file-input').forEach(input => {
            const button = input.parentElement.querySelector('.file-button');
            const status = input.parentElement.parentElement.querySelector('.file-status');
            const fileName = status.querySelector('.file-name');
            const removeBtn = status.querySelector('.file-remove');

            button.addEventListener('click', () => {
                input.click();
            });

            // Evento para eliminar archivo
            removeBtn.addEventListener('click', () => {
                removeFile(input);
            });

            input.addEventListener('change', function() {
                if (this.files.length > 0) {
                    const file = this.files[0];
                    const fileSize = file.size;
                    
                    // Validar tamaño individual del archivo (2MB)
                    if (fileSize > MAX_FILE_SIZE) {
                        showErrorModal(`El archivo "${file.name}" excede el límite de 2MB por archivo. El tamaño máximo permitido por archivo es de 2MB.`);
                        this.value = '';
                        return;
                    }
                    
                    // Validar tamaño total (5MB)
                    const newTotalSize = totalFileSize + fileSize;
                    if (newTotalSize > MAX_TOTAL_SIZE) {
                        showErrorModal(`No se puede agregar "${file.name}". El tamaño total excedería el límite de 5MB. El tamaño máximo total permitido es de 5MB.`);
                        this.value = '';
                        return;
                    }
                    
                    // Validar formato de archivo
                    const allowedTypes = ['application/pdf', 'image/jpeg', 'image/png', 'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'];
                    if (!allowedTypes.includes(file.type)) {
                        showErrorModal(`El archivo "${file.name}" no tiene un formato válido. Solo se permiten archivos PDF, JPG, PNG, DOC, DOCX.`);
                        this.value = '';
                        return;
                    }
                    
                    // Agregar archivo al total
                    totalFileSize += fileSize;
                    fileData.set(this.name, { name: file.name, size: fileSize });
                    
                    // Actualizar UI
                    fileName.textContent = file.name;
                    status.classList.add('selected');
                    button.innerHTML = '<i class="fas fa-check"></i> Archivo seleccionado';
                    button.style.background = 'var(--success-color)';
                    removeBtn.style.display = 'flex';
                    
                    // Actualizar barra de progreso
                    updateProgressBar();
                    
                } else {
                    removeFile(this);
                }
            });
        });

        // Form validation
        document.getElementById('rut').addEventListener('input', function() {
            let rutValue = this.value.replace(/[^\dkK]/g, '');
            
            if (rutValue.length > 1) {
                const dv = rutValue.slice(-1).toUpperCase();
                const cuerpo = rutValue.slice(0, -1);
                
                let rutFormateado = '';
                let i = cuerpo.length;
                while (i > 0) {
                    const inicio = Math.max(0, i - 3);
                    rutFormateado = cuerpo.substring(inicio, i) + (rutFormateado ? '.' + rutFormateado : '');
                    i = inicio;
                }
                
                rutFormateado = rutFormateado + '-' + dv;
                this.value = rutFormateado;
                this.classList.add('valid');
                this.classList.remove('invalid');
            }
        });

        document.getElementById('placa_patente').addEventListener('input', function() {
            this.value = this.value.toUpperCase().replace(/[^A-Z0-9]/g, '');
            
            if (this.value.length > 6) {
                this.value = this.value.slice(0, 6);
            }
            
            if (this.value.length > 0 && this.value.length <= 6) {
                this.classList.add('valid');
                this.classList.remove('invalid');
            }
        });

        document.getElementById('email').addEventListener('input', function() {
            const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            if (emailRegex.test(this.value)) {
                this.classList.add('valid');
                this.classList.remove('invalid');
            } else if (this.value.length > 0) {
                this.classList.add('invalid');
                this.classList.remove('valid');
            }
        });

        document.getElementById('confirmar_email').addEventListener('input', function() {
            const email = document.getElementById('email').value;
            if (this.value === email && this.value.length > 0) {
                this.classList.add('valid');
                this.classList.remove('invalid');
            } else if (this.value.length > 0) {
                this.classList.add('invalid');
                this.classList.remove('valid');
            }
        });

        document.getElementById('telefono').addEventListener('input', function() {
            this.value = this.value.replace(/[^0-9]/g, '');
            const container = this.closest('.telefono-container');
            
            if (this.value.length === 9) {
                container.style.borderColor = 'var(--success-color)';
                this.classList.add('valid');
                this.classList.remove('invalid');
            } else {
                container.style.borderColor = 'var(--error-color)';
                this.classList.add('invalid');
                this.classList.remove('valid');
            }
        });

        // Form submission
        form.addEventListener('submit', function(e) {
            if (currentStep === 2 && validateStep2()) {
                document.getElementById('loadingContainer').style.display = 'flex';
                submitBtn.disabled = true;
                submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Procesando...';
            } else {
                e.preventDefault();
            }
        });

        // Comuna autocomplete
        $(document).ready(function() {
            $.getJSON('comunas.json', function(data) {
                $("#comuna").autocomplete({
                    source: data.comunas,
                    minLength: 1,
                    select: function(event, ui) {
                        $(this).addClass('valid');
                        $(this).removeClass('invalid');
                    }
                });
            });
        });

        // Success modal handling
        document.addEventListener('DOMContentLoaded', function() {
            const alertElement = document.querySelector('.alert-success');
            const successModal = document.getElementById('successModal');
            const loadingContainer = document.getElementById('loadingContainer');
            
            if (alertElement) {
                setTimeout(function() {
                    alertElement.style.opacity = '0';
                    
                    setTimeout(function() {
                        successModal.style.display = 'flex';
                        
                        // Cerrar modal al hacer clic en cualquier parte
                        successModal.addEventListener('click', function(e) {
                            successModal.style.display = 'none';
                            loadingContainer.style.display = 'flex';
                            
                            setTimeout(function() {
                                window.location.href = 'https://www.melipilla.cl';
                            }, 2000);
                        });
                    }, 500);
                }, 1000);
            }
        });

        // Initialize
        showStep(1);
    </script>
</body>
</html>
