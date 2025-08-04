<?php
// Habilitar reporte de errores para depuración
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Configuración para XAMPP - sin contraseña para root
$host = '127.0.0.1';
$dbname = 'formulario';
$username = 'root';
$password = ''; // Contraseña vacía para XAMPP por defecto

try {
    // Añadir opciones de conexión para mejorar la estabilidad
    $options = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
        PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci"
    ];
    
    $pdo = new PDO("mysql:host=$host;dbname=$dbname", $username, $password, $options);
} catch (PDOException $e) {
    // Registrar el error en un archivo de log
    $error_message = "Error de conexión a la base de datos: " . $e->getMessage();
    error_log($error_message);
    die($error_message);
}

// Definir URL base para desarrollo local
define('BASE_URL', 'http://localhost/formulario');
?> 