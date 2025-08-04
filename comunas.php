<?php
require_once 'db.php';
$mensaje = '';

// Solo mostrar mensajes si viene de un POST
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    try {
        // Verificar si el RUT ya existe en la base de datos
        $checkRut = "SELECT COUNT(*) FROM usuarios WHERE rut = :rut";
        $stmtCheck = $pdo->prepare($checkRut);
        $stmtCheck->bindParam(':rut', $_POST['rut']);
        $stmtCheck->execute();
        
        if ($stmtCheck->fetchColumn() > 0) {
            // El RUT ya existe, mostrar mensaje de error
            $mensaje = '<div class="alert alert-error">
                            <i class="fas fa-exclamation-triangle"></i>
                            <h3>¡Error al procesar su solicitud!</h3>
                            <p>El RUT ingresado ya existe en nuestro sistema. Si necesita actualizar su información, por favor contacte a soporte.</p>
                        </div>';
        } else {
            // Iniciar transacción
            $pdo->beginTransaction();
            
            // 1. Insertar en la tabla usuarios
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
            
            // Resto del código de inserción permanece igual
            $usuario_id = $pdo->lastInsertId();
            
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
            
            // Mensaje de éxito con estilo llamativo
            $mensaje = '<div class="alert alert-success">
                            <i class="fas fa-check-circle"></i>
                            <h3>¡Formulario enviado exitosamente!</h3>
                            <p>Su solicitud ha sido recibida y está en proceso de revisión.</p>
                        </div>';
            
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
    <title>Solicitud de Renovación Permisos de Circulación Otras Comunas</title>
    <link rel="stylesheet" href="formulario.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <!-- Agregar jQuery y jQuery UI para autocompletado -->
    <link rel="stylesheet" href="https://code.jquery.com/ui/1.12.1/themes/base/jquery-ui.css">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://code.jquery.com/ui/1.12.1/jquery-ui.min.js"></script>
    <style>
        /* Estilos para las alertas llamativas */
        .alert {
            padding: 20px;
            margin: 20px 0;
            border-radius: 8px;
            text-align: center;
            animation: fadeIn 0.5s ease-in-out;
        }
        .alert-success {
            background-color: #d4edda;
            color: #155724;
            border: 2px solid #c3e6cb;
        }
        .alert-error {
            background-color: #f8d7da;
            color: #721c24;
            border: 2px solid #f5c6cb;
        }
        .alert i {
            font-size: 48px;
            margin-bottom: 10px;
        }
        .alert h3 {
            margin: 10px 0;
            font-size: 22px;
        }
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(-20px); }
            to { opacity: 1; transform: translateY(0); }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="form-header">
            <i class="fas fa-file-alt"></i>
            <h1>Solicitud de Renovación Permisos de Circulación Otras Comunas</h1>
            <p>Complete el formulario con los datos solicitados</p>
        </div>

        <?php echo $mensaje; ?>

        <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="POST" enctype="multipart/form-data">
            <div class="form-section datos-personales">
                <h2><i class="fas fa-user-circle"></i> Datos Personales</h2>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="rut">RUT</label>
                        <input type="text" id="rut" name="rut" placeholder="11.111.111-1" required>
                    </div>
                    <div class="form-group">
                        <label for="placa_patente">Placa Patente</label>
                        <input type="text" id="placa_patente" name="placa_patente" placeholder="ABCD34" required>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="nombre">Nombre</label>
                        <input type="text" id="nombre" name="nombre" required>
                    </div>
                    <div class="form-group">
                        <label for="apellido_paterno">Apellido Paterno</label>
                        <input type="text" id="apellido_paterno" name="apellido_paterno" required>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="apellido_materno">Apellido Materno</label>
                        <input type="text" id="apellido_materno" name="apellido_materno" required>
                    </div>
                    <div class="form-group">
                        <label for="comuna">Comuna</label>
                        <select id="comuna" name="comuna" required>
                            <option value="">Seleccione comuna</option>
                            <option value="Santiago">Santiago</option>
                            <option value="Providencia">Providencia</option>
                            <option value="Las Condes">Las Condes</option>
                            <option value="Ñuñoa">Ñuñoa</option>
                            <option value="La Florida">La Florida</option>
                            <option value="Maipú">Maipú</option>
                            <option value="Puente Alto">Puente Alto</option>
                            <option value="Melipilla">Melipilla</option>
                        </select>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="calle">Calle</label>
                        <input type="text" id="calle" name="calle" required>
                    </div>
                    <div class="form-group">
                        <label for="numero">Número</label>
                        <input type="text" id="numero" name="numero" required>
                    </div>
                </div>

                <div class="form-group">
                    <label for="aclaratoria">Aclaratoria (Block, Depto, Etc.)</label>
                    <input type="text" id="aclaratoria" name="aclaratoria">
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="telefono">Teléfono</label>
                        <div class="telefono-container">
                            <span class="prefix">+56</span>
                            <input type="tel" id="telefono" name="telefono" required>
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="email">Email *</label>
                        <input type="email" id="email" name="email" required>
                    </div>
                </div>

                <div class="form-group">
                    <label for="confirmar_email">Confirmar Email *</label>
                    <input type="email" id="confirmar_email" name="confirmar_email" required>
                </div>

                <p class="campos-obligatorios">* Campos Obligatorios</p>
            </div>

            <div class="form-section adjuntar-documentacion">
                <h2><i class="fas fa-file-upload"></i> Adjuntar Documentación</h2>
                
                <div class="formato-info">
                    <p><i class="fas fa-info-circle"></i> Formatos permitidos: PDF - JPG - BMP - DOC - DOCX</p>
                    <p><i class="fas fa-exclamation-circle"></i> Tamaño máximo TOTAL de los archivos a Adjuntar: 5 megabytes (MB)</p>
                    <p><i class="fas fa-asterisk"></i> Los campos marcados con * son obligatorios</p>
                </div>
                
                <p>Los documentos que debe adjuntar son los siguientes:</p>

                <div class="file-upload">
                    <label>Permiso de Circulación año anterior *</label>
                    <button type="button" class="examinar-button">
                        <i class="fas fa-folder-open"></i> Examinar...
                    </button>
                    <span class="no-file-selected">No se ha seleccionado ningún archivo</span>
                    <input type="file" name="permiso_circulacion_a" required style="display:none">
                </div>
                <div class="file-upload">
                    <label>Certificado de Homologación o Revision Tecnica y analisis de gases (Debe estar vigente) *</label>
                    <button type="button" class="examinar-button">
                        <i class="fas fa-folder-open"></i> Examinar...
                    </button>
                    <span class="no-file-selected">No se ha seleccionado ningún archivo</span>
                    <input type="file" name="certificado_homologacion" required style="display:none">
                </div>

                <div class="file-upload">
                    <label>Segura obligatoria (con vencimiento el 31 de marzo de 2026) *</label>
                    <button type="button" class="examinar-button">
                        <i class="fas fa-folder-open"></i> Examinar...
                    </button>
                    <span class="no-file-selected">No se ha seleccionado ningún archivo</span>
                    <input type="file" name="certificado_inscripcion_a" required style="display:none">
                </div>

                <div class="file-upload">
                    <label>Certificado de Inscripción *</label>
                    <button type="button" class="examinar-button">
                        <i class="fas fa-folder-open"></i> Examinar...
                    </button>
                    <span class="no-file-selected">No se ha seleccionado ningún archivo</span>
                    <input type="file" name="certificado_inscripcion_b" required style="display:none">
                </div>
                
                <div class="file-upload">
                    <label>Factura (solo para vehículos del año 2025)</label>
                    <button type="button" class="examinar-button">
                        <i class="fas fa-folder-open"></i> Examinar...
                    </button>
                    <span class="no-file-selected">No se ha seleccionado ningún archivo</span>
                    <input type="file" name="factura" style="display:none">
                </div>
            </div>

            <button type="submit" class="submit-button">Enviar Solicitud</button>
        </form>

        <footer>
            <p>© 2025 Municipalidad de Melipilla. Todos los derechos reservados.</p>
        </footer>
    </div>

    <script>
        document.querySelectorAll('.examinar-button').forEach(button => {
            button.addEventListener('click', () => {
                const fileInput = button.parentElement.querySelector('input[type="file"]');
                fileInput.click();
            });
        });

        document.querySelectorAll('input[type="file"]').forEach(input => {
            input.addEventListener('change', function() {
                const span = this.parentElement.querySelector('.no-file-selected');
                if (this.files[0]) {
                    const fileName = this.files[0].name;
                    span.textContent = fileName;
                    span.classList.add('file-selected');
                    // Cambiar el icono del botón a check
                    const buttonIcon = this.parentElement.querySelector('.examinar-button i');
                    buttonIcon.className = 'fas fa-check';
                } else {
                    span.textContent = 'No se ha seleccionado ningún archivo';
                    span.classList.remove('file-selected');
                    // Restaurar el icono original
                    const buttonIcon = this.parentElement.querySelector('.examinar-button i');
                    buttonIcon.className = 'fas fa-folder-open';
                }
            });
        });

        document.getElementById('confirmar_email').addEventListener('input', function() {
            const email = document.getElementById('email').value;
            if (this.value !== email) {
                this.setCustomValidity('Los correos electrónicos no coinciden');
            } else {
                this.setCustomValidity('');
            }
        });

        // Validación y formateo del RUT chileno en tiempo real
        document.getElementById('rut').addEventListener('input', function() {
            // Eliminar caracteres no deseados (solo permitir números y K)
            let rutValue = this.value.replace(/[^\dkK]/g, '');
            
            // Separar cuerpo y dígito verificador si hay suficientes caracteres
            if (rutValue.length > 1) {
                const dv = rutValue.slice(-1).toUpperCase();
                const cuerpo = rutValue.slice(0, -1);
                
                // Formatear el cuerpo con puntos
                let rutFormateado = '';
                let i = cuerpo.length;
                while (i > 0) {
                    const inicio = Math.max(0, i - 3);
                    rutFormateado = cuerpo.substring(inicio, i) + (rutFormateado ? '.' + rutFormateado : '');
                    i = inicio;
                }
                
                // Agregar el guión y el dígito verificador
                rutFormateado = rutFormateado + '-' + dv;
                this.value = rutFormateado;
                
                // Agregar clase visual de validación
                this.classList.add('input-valid');
                this.classList.remove('input-invalid');
            }
        });

        // Validación básica del formato del RUT
        document.getElementById('rut').addEventListener('blur', function() {
            const rutRegex = /^\d{1,2}\.\d{3}\.\d{3}-[0-9kK]$/;
            if (!rutRegex.test(this.value)) {
                this.classList.add('input-invalid');
                this.classList.remove('input-valid');
                this.setCustomValidity('Formato de RUT inválido. Ejemplo: 12.345.678-9');
            } else {
                this.classList.add('input-valid');
                this.classList.remove('input-invalid');
                this.setCustomValidity('');
            }
        });

        // Validación de la placa patente
        document.getElementById('placa_patente').addEventListener('input', function() {
            // Convertir a mayúsculas y eliminar caracteres no permitidos
            this.value = this.value.toUpperCase().replace(/[^A-Z0-9]/g, '');
            
            // Limitar a 6 caracteres
            if (this.value.length > 6) {
                this.value = this.value.slice(0, 6);
            }
            
            // Asegurar que los primeros 4 caracteres sean letras
            if (this.value.length <= 4) {
                this.value = this.value.replace(/[^A-Z]/g, '');
            }
            // Asegurar que los últimos 2 caracteres sean números
            else {
                let letras = this.value.slice(0, 4).replace(/[^A-Z]/g, '');
                let numeros = this.value.slice(4).replace(/[^0-9]/g, '');
                this.value = letras + numeros;
            }
            
            // Validación visual en tiempo real
            const patenteNueva = /^[A-Z]{4}[0-9]{2}$/;
            if (patenteNueva.test(this.value)) {
                this.classList.add('input-valid');
                this.classList.remove('input-invalid');
                this.setCustomValidity('');
            } else if (this.value.length > 0) {
                this.classList.add('input-invalid');
                this.classList.remove('input-valid');
                this.setCustomValidity('La placa patente debe tener 4 letras seguidas de 2 números');
            } else {
                this.classList.remove('input-valid');
                this.classList.remove('input-invalid');
                this.setCustomValidity('');
            }
        });

        // Validación de email
        document.getElementById('email').addEventListener('input', function() {
            const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            if (emailRegex.test(this.value)) {
                this.classList.add('input-valid');
                this.classList.remove('input-invalid');
                this.setCustomValidity('');
            } else if (this.value.length > 0) {
                this.classList.add('input-invalid');
                this.classList.remove('input-valid');
                this.setCustomValidity('Por favor ingrese un email válido');
            } else {
                this.classList.remove('input-valid');
                this.classList.remove('input-invalid');
                this.setCustomValidity('');
            }
        });

        // Validación de confirmación de email
        document.getElementById('confirmar_email').addEventListener('input', function() {
            const email = document.getElementById('email').value;
            if (this.value === email && this.value.length > 0) {
                this.classList.add('input-valid');
                this.classList.remove('input-invalid');
                this.setCustomValidity('');
            } else if (this.value.length > 0) {
                this.classList.add('input-invalid');
                this.classList.remove('input-valid');
                this.setCustomValidity('Los correos electrónicos no coinciden');
            } else {
                this.classList.remove('input-valid');
                this.classList.remove('input-invalid');
                this.setCustomValidity('');
            }
        });

        // Validación de campos de texto requeridos
        const camposTexto = ['nombre', 'apellido_paterno', 'apellido_materno', 'calle', 'numero'];
        camposTexto.forEach(campo => {
            document.getElementById(campo).addEventListener('input', function() {
                if (this.value.trim().length > 0) {
                    this.classList.add('input-valid');
                    this.classList.remove('input-invalid');
                    this.setCustomValidity('');
                } else {
                    this.classList.add('input-invalid');
                    this.classList.remove('input-valid');
                    this.setCustomValidity('Este campo es obligatorio');
                }
            });
        });

        // Validación del campo comuna
        document.getElementById('comuna').addEventListener('change', function() {
            if (this.value) {
                this.classList.add('input-valid');
                this.classList.remove('input-invalid');
                this.setCustomValidity('');
            } else {
                this.classList.add('input-invalid');
                this.classList.remove('input-valid');
                this.setCustomValidity('Por favor seleccione una comuna');
            }
        });

        // Validación del teléfono
        document.getElementById('telefono').addEventListener('input', function() {
            this.value = this.value.replace(/[^0-9]/g, '');
            const container = this.closest('.telefono-container');
            
            if (this.value.length === 9) {
                this.setCustomValidity('');
                container.classList.add('input-valid');
                container.classList.remove('input-invalid');
            } else {
                this.setCustomValidity('El número debe tener 9 dígitos');
                container.classList.add('input-invalid');
                container.classList.remove('input-valid');
            }
        });

        // Manejo del envío del formulario
        document.querySelector('form').addEventListener('submit', function(e) {
        // Ya no necesitamos actualizar el campo oculto
            
            // Continuar con el envío normal del formulario
            const submitButton = document.querySelector('.submit-button');
            submitButton.disabled = true;
            submitButton.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Procesando...';
            
            // No prevenimos el evento por defecto para permitir el envío normal del formulario
        });
        
        // Hacer desaparecer los mensajes de alerta y mostrar animación
                document.addEventListener('DOMContentLoaded', function() {
                    const alertElement = document.querySelector('.alert');
                    if (alertElement && alertElement.classList.contains('alert-success')) {
                        // Esperar 2 segundos antes de ocultar la alerta
                        setTimeout(function() {
                            alertElement.style.opacity = '0';
                            
                            // Después de que se desvanezca la alerta, mostrar la animación
                            setTimeout(function() {
                                document.querySelector('.loading-container').style.display = 'flex';
                                
                                // Esperar 3 segundos y redireccionar
                                setTimeout(function() {
                                    window.location.href = 'https://www.melipilla.cl';
                                }, 3000);
                            }, 500);
                        }, 2000);
                    } else if (alertElement) {
                        // Para mensajes de error, solo ocultar después de un tiempo
                        setTimeout(function() {
                            alertElement.style.transition = 'opacity 0.5s ease-out';
                            alertElement.style.opacity = '0';
                            setTimeout(function() {
                                alertElement.remove();
                            }, 500);
                        }, 2000);
                    }
                });
    </script>
<!-- Add before </body> -->
        <div class="modal-overlay">
            <div class="modal-content">
                <i class="fas fa-check-circle" style="font-size: 48px; color: #28a745; margin-bottom: 20px;"></i>
                <h3>¡Formulario enviado exitosamente!</h3>
                <p>Su solicitud ha sido recibida y está en proceso de revisión.</p>
            </div>
        </div>

        <!-- Contenedor de la animación de carga -->
        <div class="loading-container">
            <svg class="pl" viewBox="0 0 128 128" width="128px" height="128px" xmlns="http://www.w3.org/2000/svg">
                <defs>
                    <linearGradient id="pl-grad" x1="0" y1="0" x2="0" y2="1">
                        <stop offset="0%" stop-color="hsl(193,90%,55%)"></stop>
                        <stop offset="100%" stop-color="hsl(223,90%,55%)"></stop>
                    </linearGradient>
                </defs>
                <circle class="pl__ring" r="56" cx="64" cy="64" fill="none" stroke="hsla(0,10%,10%,0.1)" stroke-width="16" stroke-linecap="round"></circle>
                <path class="pl__worm" d="M92,15.492S78.194,4.967,66.743,16.887c-17.231,17.938-28.26,96.974-28.26,96.974L119.85,59.892l-99-31.588,57.528,89.832L97.8,19.349,13.636,88.51l89.012,16.015S81.908,38.332,66.1,22.337C50.114,6.156,36,15.492,36,15.492a56,56,0,1,0,56,0Z" fill="none" stroke="url(#pl-grad)" stroke-width="16" stroke-linecap="round" stroke-linejoin="round" stroke-dasharray="44 1111" stroke-dashoffset="10"></path>
            </svg>
        </div>
    </body>
</html>