<?php
// Habilitar reporte de errores para depuración
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();

// Verificar si el usuario está logueado
if (!isset($_SESSION['usuario_id'])) {
    header('Location: login.php');
    exit;
}

// Database connection
require_once 'config/config.php';

// Status message handling
$statusMessage = '';
$statusClass = '';

if (isset($_GET['status'])) {
    // Store status in session
    $_SESSION['status'] = $_GET['status'];
    $_SESSION['status_time'] = time();
    
    // Redirect to clean URL if coming from a status parameter
    header("Location: index.php");
    exit;
}

// Check if we have a status in session that's less than 5 minutes old
if (isset($_SESSION['status']) && isset($_SESSION['status_time']) && (time() - $_SESSION['status_time'] < 300)) {
    $status = $_SESSION['status'];
    
    switch ($status) {
        case 'approved':
            $statusMessage = 'El permiso ha sido aprobado exitosamente.';
            $statusClass = 'alert-success';
            break;
        case 'rejected':
            $statusMessage = 'El permiso ha sido rechazado y se ha enviado una notificación al solicitante.';
            $statusClass = 'alert-danger';
            break;
    }
}

// Clear old status messages (older than 5 minutes)
if (isset($_SESSION['status_time']) && (time() - $_SESSION['status_time'] >= 300)) {
    unset($_SESSION['status']);
    unset($_SESSION['status_time']);
}

// Fetch permits data
try {
    // Base query
    $baseQuery = "SELECT v.id as vehiculo_id, v.placa_patente, u.nombre, u.apellido_paterno, u.apellido_materno, 
              LOWER(s.estado) as estado, s.fecha_solicitud, s.fecha_actualizacion, u.rut 
              FROM vehiculos v 
              JOIN usuarios u ON v.usuario_id = u.id 
              JOIN solicitudes s ON v.id = s.vehiculo_id";
    
    // Filtro por estado si está especificado
    $filtroEstado = isset($_GET['estado']) ? $_GET['estado'] : '';
    $whereClause = '';
    
    if ($filtroEstado && in_array($filtroEstado, ['pendiente', 'aprobada', 'rechazada'])) {
        $whereClause = " WHERE LOWER(s.estado) = :estado";
    }
    
    // Búsqueda por texto si está especificada
    $busqueda = isset($_GET['busqueda']) ? trim($_GET['busqueda']) : '';
    if (!empty($busqueda)) {
        $whereClause = empty($whereClause) ? " WHERE " : $whereClause . " AND ";
        $whereClause .= "(v.placa_patente LIKE :busqueda OR u.rut LIKE :busqueda OR 
                       CONCAT(u.nombre, ' ', u.apellido_paterno, ' ', u.apellido_materno) LIKE :busqueda)";
    }
    
    // Ordenar por fecha de solicitud descendente
    $orderClause = " ORDER BY s.fecha_solicitud DESC";
    
    // Construir consulta completa
    $query = $baseQuery . $whereClause . $orderClause;
    $stmt = $pdo->prepare($query);
    
    // Bind parameters if needed
    if ($filtroEstado && in_array($filtroEstado, ['pendiente', 'aprobada', 'rechazada'])) {
        $stmt->bindParam(':estado', $filtroEstado);
    }
    
    if (!empty($busqueda)) {
        $busquedaParam = "%$busqueda%";
        $stmt->bindParam(':busqueda', $busquedaParam);
    }
    
    // Forzar la carga completa de los datos
    $pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
    
    $stmt->execute();
    $permits = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    die("Error al obtener datos: " . $e->getMessage());
}

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Permisos de Circulación - Panel de Administración</title>
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
            padding: 1rem 0;
            box-shadow: var(--shadow-lg);
            position: sticky;
            top: 0;
            z-index: 100;
        }

        .navbar-container {
            max-width: 1400px;
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
        }

        .navbar-actions {
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .btn-logout {
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
            min-width: 177px;
        }

        .btn-logout:hover {
            background: rgba(255, 255, 255, 0.2);
            color: white;
            transform: translateY(-1px);
        }

        /* Main Container */
        .main-container {
            max-width: 1400px;
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

        .alert-danger {
            background: rgba(239, 68, 68, 0.1);
            color: #991b1b;
            border-color: rgba(239, 68, 68, 0.2);
        }

        @keyframes slideIn {
            from { opacity: 0; transform: translateY(-20px); }
            to { opacity: 1; transform: translateY(0); }
        }

        /* Main Card */
        .main-card {
            background: white;
            border-radius: 20px;
            box-shadow: var(--shadow-lg);
            overflow: hidden;
        }

        .card-header {
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--primary-light) 100%);
            padding: 2rem;
            color: white;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .card-title {
            font-size: 1.5rem;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .btn-export {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.75rem 1.5rem;
            background: var(--success-color);
            color: white;
            text-decoration: none;
            border-radius: var(--border-radius);
            font-weight: 500;
            transition: var(--transition);
        }

        .btn-export:hover {
            background: #059669;
            color: white;
            transform: translateY(-2px);
            box-shadow: var(--shadow-md);
        }

        /* Filters Section */
        .filters-section {
            padding: 2rem;
            background: var(--secondary-color);
            border-bottom: 1px solid var(--border-color);
        }

        .filters-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 2rem;
        }

        .search-container {
            position: relative;
            flex: 1;
            max-width: 100%;
        }

        .search-icon {
            position: absolute;
            left: 1rem;
            top: 50%;
            transform: translateY(-50%);
            color: var(--text-secondary);
            z-index: 10;
        }

        .search-input {
            width: 100%;
            padding: 0.875rem 1rem 0.875rem 3rem;
            border: 2px solid var(--border-color);
            border-radius: var(--border-radius);
            font-size: 1rem;
            transition: var(--transition);
            background: white;
        }

        .search-input:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(15, 7, 88, 0.1);
        }

        .filter-buttons {
            display: flex;
            gap: 0.5rem;
        }

        .filter-btn {
            padding: 0.75rem 1.25rem;
            border: 2px solid var(--border-color);
            background: white;
            color: var(--text-primary);
            border-radius: var(--border-radius);
            font-weight: 500;
            cursor: pointer;
            transition: var(--transition);
            font-size: 0.9rem;
        }

        .filter-btn:hover {
            border-color: var(--primary-color);
            color: var(--primary-color);
        }

        .filter-btn.active {
            background: var(--primary-color);
            color: white;
            border-color: var(--primary-color);
        }

        .filter-btn.warning {
            background: var(--warning-color);
            color: white;
            border-color: var(--warning-color);
        }

        .filter-btn.success {
            background: var(--success-color);
            color: white;
            border-color: var(--success-color);
        }

        .filter-btn.danger {
            background: var(--error-color);
            color: white;
            border-color: var(--error-color);
        }

        /* Table */
        .table-container {
            padding: 2rem;
            overflow-x: auto;
        }

        .permits-table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
        }

        .permits-table th {
            background: var(--secondary-color);
            padding: 1.25rem 1rem;
            text-align: left;
            font-weight: 600;
            color: var(--text-primary);
            border-bottom: 2px solid var(--border-color);
            font-size: 0.9rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }

        .permits-table th:first-child {
            border-top-left-radius: var(--border-radius);
        }

        .permits-table th:last-child {
            border-top-right-radius: var(--border-radius);
        }

        .permits-table td {
            padding: 1.25rem 1rem;
            border-bottom: 1px solid var(--border-color);
            vertical-align: middle;
            background: white;
            transition: var(--transition);
        }

        .permits-table tr:hover td {
            background: var(--secondary-color);
        }

        .permits-table tr:last-child td:first-child {
            border-bottom-left-radius: var(--border-radius);
        }

        .permits-table tr:last-child td:last-child {
            border-bottom-right-radius: var(--border-radius);
        }

        /* Status Badges */
        .status-badge {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.5rem 1rem;
            border-radius: 50px;
            font-weight: 500;
            font-size: 0.85rem;
            text-transform: capitalize;
        }

        .status-badge.success {
            background: rgba(16, 185, 129, 0.1);
            color: #065f46;
            border: 1px solid rgba(16, 185, 129, 0.2);
        }

        .status-badge.warning {
            background: rgba(245, 158, 11, 0.1);
            color: #92400e;
            border: 1px solid rgba(245, 158, 11, 0.2);
        }

        .status-badge.danger {
            background: rgba(239, 68, 68, 0.1);
            color: #991b1b;
            border: 1px solid rgba(239, 68, 68, 0.2);
        }

        /* Action Button */
        .btn-details {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.75rem 1.25rem;
            background: var(--accent-color);
            color: white;
            text-decoration: none;
            border-radius: var(--border-radius);
            font-weight: 500;
            font-size: 0.9rem;
            transition: var(--transition);
        }

        .btn-details:hover {
            background: #2563eb;
            color: white;
            transform: translateY(-1px);
            box-shadow: var(--shadow-md);
        }

        /* Time Display */
        .time-info {
            font-size: 0.85rem;
            color: var(--text-secondary);
            margin-top: 0.25rem;
        }

        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 4rem 2rem;
            color: var(--text-secondary);
        }

        .empty-state i {
            font-size: 4rem;
            margin-bottom: 1rem;
            opacity: 0.5;
        }

        /* Responsive Design */
        @media (max-width: 1024px) {
            .main-container {
                padding: 0 1rem;
            }

            .navbar-container {
                padding: 0 1rem;
            }

            .filters-row {
                flex-direction: column;
                align-items: stretch;
                gap: 1rem;
            }

            .filter-buttons {
                flex-wrap: wrap;
            }
        }

        @media (max-width: 768px) {
            .page-title {
                font-size: 2rem;
            }

            .card-header {
                flex-direction: column;
                gap: 1rem;
                text-align: center;
            }

            .permits-table {
                font-size: 0.85rem;
            }

            .permits-table th,
            .permits-table td {
                padding: 0.75rem 0.5rem;
            }

            .navbar-logo {
                width: 120px;
            }
        }
        @media (max-width: 705px) {
            .filter-btn {
                width: 100%;
            }
        }

        /* Loading States */
        .loading {
            opacity: 0.6;
            pointer-events: none;
        }

        /* Animations */
        @keyframes highlightNew {
            0% { background-color: rgba(16, 185, 129, 0.2); }
            100% { background-color: transparent; }
        }

        @keyframes updateHighlight {
            0% { background-color: rgba(59, 130, 246, 0.3); }
            100% { background-color: transparent; }
        }

        .highlight-new {
            animation: highlightNew 2s ease-out;
        }

        .highlight-update {
            animation: updateHighlight 1s ease-out;
        }
        @media (max-width: 595px) {
            button.filter-btn {
                width: 100%;
            }
        }
        
        /* Mejoras de responsividad para la tabla */
        @media (max-width: 1110px) {
            .permits-table th,
            .permits-table td {
                padding: 0.75rem 0.5rem;
                font-size: 0.85rem;
            }
            
            .status-badge {
                padding: 0.4rem 0.8rem;
                font-size: 0.8rem;
            }
            
            .btn-details {
                padding: 0.6rem 1rem;
                font-size: 0.85rem;
            }
        }
        
        @media (max-width: 768px) {
            .table-container {
                padding: 1rem;
                overflow-x: auto;
            }
            
            .permits-table {
                min-width: 800px; /* Asegura que la tabla mantenga su estructura */
            }
            
            .permits-table th,
            .permits-table td {
                padding: 0.6rem 0.4rem;
                font-size: 0.8rem;
                white-space: nowrap;
            }
            
            .status-badge {
                padding: 0.3rem 0.6rem;
                font-size: 0.75rem;
            }
            
            .btn-details {
                padding: 0.5rem 0.8rem;
                font-size: 0.8rem;
            }
            
            .time-info {
                font-size: 0.75rem;
            }
        }
        
        @media (max-width: 480px) {
            .table-container {
                padding: 0.5rem;
            }
            
            .permits-table th,
            .permits-table td {
                padding: 0.5rem 0.3rem;
                font-size: 0.75rem;
            }
            
            .status-badge {
                padding: 0.25rem 0.5rem;
                font-size: 0.7rem;
            }
            
            .btn-details {
                padding: 0.4rem 0.6rem;
                font-size: 0.75rem;
            }
            
            .page-title {
                font-size: 1.8rem;
            }
            
            .page-subtitle {
                font-size: 1rem;
            }
        }
    </style>
</head>
<body data-user-id="<?php echo $_SESSION['usuario_id']; ?>">
    <!-- Navigation -->
    <nav class="navbar">
        <div class="navbar-container">
            <a href="index.php" class="navbar-brand">
                <img src="imagenes/logo_blanco.png" alt="Logo Melipilla" class="navbar-logo">
            </a>
            <div class="navbar-actions">
                <a href="cerrar_sesion.php" class="btn-logout">
                    <i class="fas fa-sign-out-alt"></i>
                    Cerrar sesión
                </a>
            </div>
        </div>
    </nav>

    <!-- Main Container -->
    <div class="main-container">
        <!-- Page Header -->
        <div class="page-header">
            <h1 class="page-title">Panel de Administración</h1>
            <p class="page-subtitle">Gestión de permisos de circulación</p>
        </div>
        
        <!-- Status Messages -->
        <?php if (!empty($statusMessage)): ?>
        <div class="alert <?php echo $statusClass; ?>">
            <i class="fas <?php echo $statusClass == 'alert-success' ? 'fa-check-circle' : 'fa-exclamation-circle'; ?>"></i>
            <?php echo $statusMessage; ?>
        </div>
        <?php endif; ?>
        
        <!-- Main Card -->
        <div class="main-card">
            <!-- Card Header -->
            <div class="card-header">
                <div class="card-title">
                    <i class="fas fa-list-alt"></i>
                    Listado de Permisos
                </div>
                <a href="includes/exportar_excel.php" class="btn-export">
                    <i class="fas fa-file-excel"></i>
                    Exportar a Excel
                </a>
            </div>

            <!-- Filters Section -->
            <div class="filters-section">
                <div class="filters-row">
                    <div class="search-container">
                        <i class="fas fa-search search-icon"></i>
                        <input type="text" id="searchInput" class="search-input" 
                               placeholder="Buscar por patente, RUT o nombre..." 
                               value="<?php echo isset($_GET['busqueda']) ? htmlspecialchars($_GET['busqueda']) : ''; ?>">
                    </div>
                    <div class="filter-buttons">
                        <?php 
                        // Contar permisos por estado
                        $totalPermisos = count($permits);
                        $pendientes = 0;
                        $aprobados = 0;
                        $rechazados = 0;
                        
                        foreach ($permits as $p) {
                            $estado = strtolower($p['estado']);
                            if ($estado === 'pendiente') $pendientes++;
                            elseif ($estado === 'aprobada') $aprobados++;
                            elseif ($estado === 'rechazada') $rechazados++;
                        }
                        ?>
                        <button type="button" class="filter-btn active" data-filter="">
                            <i class="fas fa-list"></i>
                            Todos (<?php echo $totalPermisos; ?>)
                        </button>
                        <button type="button" class="filter-btn" data-filter="pendiente">
                            <i class="fas fa-clock"></i>
                            Pendientes (<?php echo $pendientes; ?>)
                        </button>
                        <button type="button" class="filter-btn" data-filter="aprobada">
                            <i class="fas fa-check"></i>
                            Aprobados (<?php echo $aprobados; ?>)
                        </button>
                        <button type="button" class="filter-btn" data-filter="rechazada">
                            <i class="fas fa-times"></i>
                            Rechazados (<?php echo $rechazados; ?>)
                        </button>
                    </div>
                </div>
            </div>

            <!-- Table Container -->
            <div class="table-container">
                <?php if (empty($permits)): ?>
                <div class="empty-state">
                    <i class="fas fa-inbox"></i>
                    <h3>No hay permisos registrados</h3>
                    <p>Cuando se registren nuevas solicitudes aparecerán aquí.</p>
                </div>
                <?php else: ?>
                <table class="permits-table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Patente</th>
                            <th>RUT</th>
                            <th>Solicitante</th>
                            <th>Estado</th>
                            <th>Fecha Solicitud</th>
                            <th>Última Actualización</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($permits as $permit): ?>
                            <tr data-id="<?php echo $permit['vehiculo_id']; ?>">
                                <td><strong>#<?php echo $permit['vehiculo_id']; ?></strong></td>
                                <td>
                                    <strong><?php echo $permit['placa_patente']; ?></strong>
                                </td>
                                <td><?php echo $permit['rut']; ?></td>
                                <td>
                                    <div>
                                        <strong><?php echo $permit['nombre'] . ' ' . $permit['apellido_paterno'] . ' ' . $permit['apellido_materno']; ?></strong>
                                    </div>
                                </td>
                                <td class="estado-cell">
                                    <?php if (strtolower($permit['estado']) === 'aprobada'): ?>
                                        <span class="status-badge success">
                                            <i class="fas fa-check-circle"></i>
                                            Aprobada
                                        </span>
                                    <?php elseif (strtolower($permit['estado']) === 'rechazada'): ?>
                                        <span class="status-badge danger">
                                            <i class="fas fa-times-circle"></i>
                                            Rechazada
                                        </span>
                                    <?php else: ?>
                                        <span class="status-badge warning">
                                            <i class="fas fa-clock"></i>
                                            Pendiente
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div><?php echo date('d/m/Y', strtotime($permit['fecha_solicitud'])); ?></div>
                                    <div class="time-info"><?php echo date('H:i', strtotime($permit['fecha_solicitud'])); ?></div>
                                </td>
                                <td>
                                    <?php if (!empty($permit['fecha_actualizacion']) && $permit['fecha_actualizacion'] != $permit['fecha_solicitud']): ?>
                                        <div><?php echo date('d/m/Y', strtotime($permit['fecha_actualizacion'])); ?></div>
                                        <div class="time-info">
                                            <?php echo date('H:i', strtotime($permit['fecha_actualizacion'])); ?>
                                            <?php 
                                                // Calcular tiempo transcurrido
                                                $fecha1 = new DateTime($permit['fecha_solicitud']);
                                                $fecha2 = new DateTime($permit['fecha_actualizacion']);
                                                $intervalo = $fecha1->diff($fecha2);
                                                
                                                if ($intervalo->days > 0) {
                                                    echo '<br><small>(' . $intervalo->days . ' días)</small>';
                                                } else {
                                                    $minutos_totales = ($intervalo->h * 60) + $intervalo->i;
                                                    
                                                    if ($minutos_totales >= 60) {
                                                        $horas = floor($minutos_totales / 60);
                                                        $minutos = $minutos_totales % 60;
                                                        
                                                        if ($minutos > 0) {
                                                            echo '<br><small>(' . $horas . 'h ' . $minutos . 'm)</small>';
                                                        } else {
                                                            echo '<br><small>(' . $horas . 'h)</small>';
                                                        }
                                                    } else {
                                                        echo '<br><small>(' . $minutos_totales . 'm)</small>';
                                                    }
                                                }
                                            ?>
                                        </div>
                                    <?php else: ?>
                                        <span class="time-info">Sin actualizar</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if (!empty($permit['vehiculo_id'])): ?>
                                        <a href="ver_detalles.php?id=<?php echo $permit['vehiculo_id']; ?>" class="btn-details">
                                            <i class="fas fa-eye"></i>
                                            Ver Detalles
                                            <span class="visualizacion-badge" id="visualizacion-<?php echo $permit['vehiculo_id']; ?>"></span>
                                        </a>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Scripts -->
    <script src="assets/js/notificaciones.js"></script>
    <script src="assets/js/visualizaciones.js"></script>
    <script src="assets/js/estado-tiempo-real.js"></script>
    
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // Variables para los filtros
        let currentFilter = '<?php echo isset($_GET["estado"]) ? $_GET["estado"] : ""; ?>';
        let searchTerm = '<?php echo isset($_GET["busqueda"]) ? htmlspecialchars($_GET["busqueda"]) : ""; ?>';
        
        // Función para aplicar filtros
        function applyFilters() {
            const rows = document.querySelectorAll('tbody tr');
            
            rows.forEach(row => {
                let showRow = true;
                
                // Filtrar por estado
                if (currentFilter) {
                    const statusBadge = row.querySelector('.status-badge');
                    const estado = statusBadge.textContent.toLowerCase().trim();
                    
                    if (!estado.includes(currentFilter)) {
                        showRow = false;
                    }
                }
                
                // Filtrar por término de búsqueda
                if (searchTerm && showRow) {
                    const patente = row.querySelector('td:nth-child(2)').textContent.toLowerCase();
                    const rut = row.querySelector('td:nth-child(3)').textContent.toLowerCase();
                    const nombre = row.querySelector('td:nth-child(4)').textContent.toLowerCase();
                    
                    if (!patente.includes(searchTerm.toLowerCase()) && 
                        !rut.includes(searchTerm.toLowerCase()) && 
                        !nombre.includes(searchTerm.toLowerCase())) {
                        showRow = false;
                    }
                }
                
                // Mostrar u ocultar fila
                row.style.display = showRow ? '' : 'none';
            });
        }
        
        // Manejar búsqueda en tiempo real
        const searchInput = document.getElementById('searchInput');
        searchInput.addEventListener('input', function() {
            searchTerm = this.value.trim();
            applyFilters();
        });
        
        // Manejar botones de filtro
        const filterButtons = document.querySelectorAll('.filter-btn');
        filterButtons.forEach(button => {
            button.addEventListener('click', function() {
                // Remover clase active de todos los botones
                filterButtons.forEach(btn => {
                    btn.classList.remove('active', 'warning', 'success', 'danger');
                });
                
                // Agregar clase active al botón actual
                this.classList.add('active');
                
                // Agregar clase específica según el filtro
                const filter = this.dataset.filter;
                if (filter === 'pendiente') {
                    this.classList.add('warning');
                } else if (filter === 'aprobada') {
                    this.classList.add('success');
                } else if (filter === 'rechazada') {
                    this.classList.add('danger');
                }
                
                // Actualizar filtro actual
                currentFilter = filter;
                applyFilters();
            });
        });
        
        // Aplicar filtros iniciales
        applyFilters();
        
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
    });
    </script>
</body>
</html>
