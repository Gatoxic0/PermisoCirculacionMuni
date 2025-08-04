<?php
// Habilitar reporte de errores para depuración
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();

// Redirigir si ya está logueado
if (isset($_SESSION['usuario_id'])) {
    header('Location: index.php');
    exit;
}

// Mensaje de error
$error = '';
if (isset($_SESSION['login_error'])) {
    $error = $_SESSION['login_error'];
    unset($_SESSION['login_error']);
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Iniciar Sesión - Sistema de Permisos de Circulación</title>
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
            --text-muted: #9ca3af;
            --border-color: #e5e7eb;
            --background-light: #f9fafb;
            --white: #ffffff;
            --border-radius: 16px;
            --shadow-sm: 0 1px 2px 0 rgb(0 0 0 / 0.05);
            --shadow-md: 0 4px 6px -1px rgb(0 0 0 / 0.1), 0 2px 4px -2px rgb(0 0 0 / 0.1);
            --shadow-lg: 0 10px 15px -3px rgb(0 0 0 / 0.1), 0 4px 6px -4px rgb(0 0 0 / 0.1);
            --shadow-xl: 0 20px 25px -5px rgb(0 0 0 / 0.1), 0 8px 10px -6px rgb(0 0 0 / 0.1);
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
            display: flex;
            align-items: center;
            justify-content: center;
            position: relative;
            overflow: hidden;
        }

        /* Background Pattern */
        body::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-image: 
                radial-gradient(circle at 20% 20%, rgba(255, 255, 255, 0.03) 0%, transparent 50%),
                radial-gradient(circle at 80% 80%, rgba(255, 255, 255, 0.03) 0%, transparent 50%),
                radial-gradient(circle at 40% 60%, rgba(255, 255, 255, 0.02) 0%, transparent 50%);
            pointer-events: none;
        }

        /* Municipal Logo Pattern */
        .background-pattern {
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            opacity: 0.03;
            background-image: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 200 200"><g fill="white"><circle cx="50" cy="50" r="20"/><rect x="30" y="30" width="40" height="40" rx="8"/><polygon points="100,30 120,50 100,70 80,50"/><circle cx="150" cy="50" r="15"/><rect x="130" y="130" width="40" height="40" rx="8"/><circle cx="50" cy="150" r="18"/><polygon points="100,130 120,150 100,170 80,150"/></g></svg>');
            background-size: 200px 200px;
            background-repeat: repeat;
            animation: float 20s ease-in-out infinite;
        }

        @keyframes float {
            0%, 100% { transform: translateY(0px) rotate(0deg); }
            50% { transform: translateY(-10px) rotate(2deg); }
        }

        /* Login Container */
        .login-container {
            position: relative;
            z-index: 10;
            width: 100%;
            max-width: 450px;
            padding: 2rem;
        }

        /* Login Card */
        .login-card {
            background: var(--primary-color);
            backdrop-filter: blur(20px);
            border-radius: 24px;
            box-shadow: var(--shadow-xl);
            border: 1px solid rgba(255, 255, 255, 0.2);
            overflow: hidden;
            position: relative;
        }

        .login-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
        }

        /* Header Section */
        .login-header {
            text-align: center;
            padding: 3rem 2rem 2rem 2rem;
            background: transparent;
        }

        .municipal-logo {
            width: 200px;
            height: 80px;
            background: var(--primary-color);
            border-radius: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1.5rem auto;
            box-shadow: var(--shadow-lg);
            position: relative;
            overflow: hidden;
            padding: 10px;
        }

        .municipal-logo::before {
            content: '';
            position: absolute;
            top: -50%;
            left: -50%;
            width: 200%;
            height: 200%;
            background: linear-gradient(45deg, transparent, rgba(255, 255, 255, 0.1), transparent);
            transform: rotate(45deg);
            animation: shine 3s ease-in-out infinite;
        }

        @keyframes shine {
            0% { transform: translateX(-100%) translateY(-100%) rotate(45deg); }
            50% { transform: translateX(100%) translateY(100%) rotate(45deg); }
            100% { transform: translateX(-100%) translateY(-100%) rotate(45deg); }
        }

        .municipal-logo i {
            font-size: 2.5rem;
            color: white;
            position: relative;
            z-index: 2;
        }

        .login-title {
            font-size: 1.75rem;
            font-weight: 700;
            color: white;
            margin-bottom: 0.5rem;
            letter-spacing: -0.025em;
        }

        .login-subtitle {
            color: rgba(255, 255, 255, 0.9);
            font-size: 1rem;
            font-weight: 500;
            margin-bottom: 0.5rem;
        }

        .municipality-name {
            color: rgba(255, 255, 255, 0.8);
            font-size: 0.9rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.1em;
        }

        /* Alert Messages */
        .alert {
            margin: 1.5rem 2rem;
            padding: 1rem 1.25rem;
            border-radius: 12px;
            display: flex;
            align-items: center;
            gap: 0.75rem;
            animation: slideIn 0.5s ease-out;
        }

        .alert-danger {
            background: rgba(239, 68, 68, 0.1);
            color: #991b1b;
            border: 1px solid rgba(239, 68, 68, 0.2);
        }

        @keyframes slideIn {
            from { opacity: 0; transform: translateY(-10px); }
            to { opacity: 1; transform: translateY(0); }
        }

        /* Form Section */
        .login-form {
            padding: 0 2rem 3rem 2rem;
        }

        .form-group {
            margin-bottom: 1.5rem;
            position: relative;
        }

        .form-label {
            display: block;
            font-weight: 500;
            color: #ffffff;
            margin-bottom: 0.5rem;
            font-size: 0.95rem;
        }

        .form-input-wrapper {
            position: relative;
        }

        .form-input {
            width: 100%;
            padding: 1rem 1rem 1rem 3rem;
            border: 2px solid var(--border-color);
            border-radius: 12px;
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

        .form-input:focus + .input-icon {
            color: var(--primary-color);
        }

        .input-icon {
            position: absolute;
            left: 1rem;
            top: 50%;
            transform: translateY(-50%);
            color: var(--text-muted);
            font-size: 1.1rem;
            transition: var(--transition);
            pointer-events: none;
        }

        /* Login Button */
        .login-button {
            width: 100%;
            padding: 1rem 2rem;
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--primary-light) 100%);
            color: white;
            border: none;
            border-radius: 12px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: var(--transition);
            position: relative;
            overflow: hidden;
            margin-top: 1rem;
        }

        .login-button::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.2), transparent);
            transition: left 0.5s;
        }

        .login-button:hover::before {
            left: 100%;
        }

        .login-button:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-lg);
        }

        .login-button:active {
            transform: translateY(0);
        }

        .login-button i {
            margin-right: 0.5rem;
        }

        /* Loading State */
        .login-button.loading {
            pointer-events: none;
            opacity: 0.8;
        }

        .login-button.loading i {
            animation: spin 1s linear infinite;
        }

        @keyframes spin {
            from { transform: rotate(0deg); }
            to { transform: rotate(360deg); }
        }

        /* Footer */
        .login-footer {
            text-align: center;
            padding: 1.5rem 2rem;
            border-top: 1px solid rgba(255, 255, 255, 0.2);
            background: transparent;
        }

        .footer-text {
            color: rgba(255, 255, 255, 0.8);
            font-size: 0.85rem;
            line-height: 1.5;
        }

        .footer-text strong {
            color: white;
            font-weight: 600;
        }
        
        /* Footer transparente para pantallas grandes */
        @media (min-width: 768px) {
            .login-footer {
                background: transparent;
                border-top: none;
            }
            
            .footer-text {
                color: rgba(255, 255, 255, 0.9);
                font-size: 0.9rem;
            }
            
            .footer-text strong {
                color: white;
                font-weight: 700;
            }
        }

        /* Floating Elements */
        .floating-element {
            position: absolute;
            pointer-events: none;
            opacity: 0.1;
        }

        .floating-element-1 {
            top: 10%;
            left: 10%;
            animation: float1 6s ease-in-out infinite;
        }

        .floating-element-2 {
            top: 20%;
            right: 15%;
            animation: float2 8s ease-in-out infinite;
        }

        .floating-element-3 {
            bottom: 15%;
            left: 20%;
            animation: float3 7s ease-in-out infinite;
        }

        .floating-element-4 {
            bottom: 25%;
            right: 10%;
            animation: float4 9s ease-in-out infinite;
        }

        @keyframes float1 {
            0%, 100% { transform: translateY(0px) rotate(0deg); }
            50% { transform: translateY(-20px) rotate(180deg); }
        }

        @keyframes float2 {
            0%, 100% { transform: translateY(0px) rotate(0deg); }
            50% { transform: translateY(-15px) rotate(-180deg); }
        }

        @keyframes float3 {
            0%, 100% { transform: translateY(0px) rotate(0deg); }
            50% { transform: translateY(-25px) rotate(90deg); }
        }

        @keyframes float4 {
            0%, 100% { transform: translateY(0px) rotate(0deg); }
            50% { transform: translateY(-18px) rotate(-90deg); }
        }

        /* Responsive Design */
        @media (max-width: 768px) {
            .login-container {
                padding: 1rem;
                max-width: 100%;
            }

            .login-header {
                padding: 2rem 1.5rem 1.5rem 1.5rem;
            }

            .login-form {
                padding: 0 1.5rem 2rem 1.5rem;
            }

            .municipal-logo {
                width: 200px;
                height: 80px;
            }

            .municipal-logo i {
                font-size: 2rem;
            }

            .login-title {
                font-size: 1.5rem;
            }

            .alert {
                margin: 1rem 1.5rem;
            }

            .floating-element {
                display: none;
            }
        }

        @media (max-width: 480px) {
            .login-container {
                padding: 0.5rem;
            }

            .login-card {
                border-radius: 16px;
            }

            .login-header {
                padding: 1.5rem 1rem 1rem 1rem;
            }

            .municipal-logo {
                width: 200px;
                height: 80px;
            }

            .municipal-logo img {
                width: 100%;
                height: 100%;
                object-fit: contain;
            }

            .login-form {
                padding: 0 1rem 1.5rem 1rem;
            }

            .login-footer {
                padding: 1rem;
            }
        }

        /* Accessibility */
        @media (prefers-reduced-motion: reduce) {
            * {
                animation-duration: 0.01ms !important;
                animation-iteration-count: 1 !important;
                transition-duration: 0.01ms !important;
            }
        }

        /* Focus visible for keyboard navigation */
        .form-input:focus-visible,
        .login-button:focus-visible {
            outline: 2px solid var(--primary-color);
            outline-offset: 2px;
        }
    </style>
</head>
<body>
    <!-- Background Pattern -->
    <div class="background-pattern"></div>
    
    <!-- Floating Elements -->
    <div class="floating-element floating-element-1">
        <i class="fas fa-building" style="font-size: 3rem; color: rgba(255, 255, 255, 0.1);"></i>
    </div>
    <div class="floating-element floating-element-2">
        <i class="fas fa-car" style="font-size: 2.5rem; color: rgba(255, 255, 255, 0.1);"></i>
    </div>
    <div class="floating-element floating-element-3">
        <i class="fas fa-file-alt" style="font-size: 2rem; color: rgba(255, 255, 255, 0.1);"></i>
    </div>
    <div class="floating-element floating-element-4">
        <i class="fas fa-shield-alt" style="font-size: 2.8rem; color: rgba(255, 255, 255, 0.1);"></i>
    </div>

    <!-- Login Container -->
    <div class="login-container">
        <div class="login-card">
            <!-- Header -->
            <div class="login-header">
                <div class="municipal-logo">
                    <img src="imagenes/logo_blanco.png" alt="Logo Melipilla" style="width: 100%; height: 100%; object-fit: contain;">
                </div>
                <h1 class="login-title">Bienvenido</h1>
                <p class="login-subtitle">Sistema de Permisos de Circulación</p>
                <p class="municipality-name">Municipalidad de Melipilla</p>
            </div>
            
            <!-- Alert Messages -->
            <?php if (!empty($error)): ?>
            <div class="alert alert-danger">
                <i class="fas fa-exclamation-triangle"></i>
                <span><?php echo htmlspecialchars($error); ?></span>
            </div>
            <?php endif; ?>
            
            <!-- Login Form -->
            <div class="login-form">
                <form action="procesar_login.php" method="post" id="loginForm">
                    <div class="form-group">
                        <label for="username" class="form-label">Usuario</label>
                        <div class="form-input-wrapper">
                            <input type="text" 
                                   class="form-input" 
                                   id="username" 
                                   name="username" 
                                   placeholder="Ingrese su usuario"
                                   required 
                                   autocomplete="username">
                            <i class="fas fa-user input-icon"></i>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="password" class="form-label">Contraseña</label>
                        <div class="form-input-wrapper">
                            <input type="password" 
                                   class="form-input" 
                                   id="password" 
                                   name="password" 
                                   placeholder="Ingrese su contraseña"
                                   required 
                                   autocomplete="current-password">
                            <i class="fas fa-lock input-icon"></i>
                        </div>
                    </div>
                    
                    <button type="submit" class="login-button" id="loginBtn">
                        <i class="fas fa-sign-in-alt"></i>
                        Iniciar Sesión
                    </button>
                </form>
            </div>
            
            <!-- Footer -->
            <div class="login-footer">
                <p class="footer-text">
                    © 2025 <strong>Municipalidad de Melipilla</strong><br>
                    Todos los derechos reservados
                </p>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const form = document.getElementById('loginForm');
            const loginBtn = document.getElementById('loginBtn');
            const usernameInput = document.getElementById('username');
            const passwordInput = document.getElementById('password');

            // Form submission handling
            form.addEventListener('submit', function(e) {
                // Add loading state
                loginBtn.classList.add('loading');
                loginBtn.innerHTML = '<i class="fas fa-spinner"></i> Iniciando sesión...';
                loginBtn.disabled = true;
            });

            // Input focus effects
            const inputs = document.querySelectorAll('.form-input');
            inputs.forEach(input => {
                input.addEventListener('focus', function() {
                    this.parentElement.classList.add('focused');
                });

                input.addEventListener('blur', function() {
                    this.parentElement.classList.remove('focused');
                });

                // Real-time validation
                input.addEventListener('input', function() {
                    if (this.value.trim() !== '') {
                        this.classList.add('has-value');
                    } else {
                        this.classList.remove('has-value');
                    }
                });
            });

            // Auto-dismiss alerts
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(alert => {
                setTimeout(() => {
                    alert.style.opacity = '0';
                    alert.style.transform = 'translateY(-10px)';
                    setTimeout(() => {
                        alert.remove();
                    }, 300);
                }, 5000);
            });

            // Keyboard shortcuts
            document.addEventListener('keydown', function(e) {
                // Enter key to submit form when focused on inputs
                if (e.key === 'Enter' && (e.target === usernameInput || e.target === passwordInput)) {
                    form.submit();
                }
            });

            // Prevent multiple submissions
            let isSubmitting = false;
            form.addEventListener('submit', function(e) {
                if (isSubmitting) {
                    e.preventDefault();
                    return false;
                }
                isSubmitting = true;
            });

            // Auto-focus username field
            usernameInput.focus();
        });
    </script>
</body>
</html>
