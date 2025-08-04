<?php
session_start();
require_once 'config/config.php';

// Verificar si se enviaron los datos del formulario
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: login.php');
    exit;
}

// Obtener datos del formulario
$username = isset($_POST['username']) ? trim($_POST['username']) : '';
$password = isset($_POST['password']) ? $_POST['password'] : '';

// Validar que los campos no estén vacíos
if (empty($username) || empty($password)) {
    $_SESSION['login_error'] = 'Por favor, complete todos los campos.';
    header('Location: login.php');
    exit;
}

try {
    // Buscar usuario por nombre de usuario (sin hash)
    $query = "SELECT id, nombre, apellido, username, password, rol FROM funcionarios WHERE username = :username";
    $stmt = $pdo->prepare($query);
    $stmt->bindParam(':username', $username);
    $stmt->execute();
    
    $usuario = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Verificar si el usuario existe y la contraseña es correcta (sin hash)
    if ($usuario && $password === $usuario['password']) {
        // Guardar datos en la sesión
        $_SESSION['usuario_id'] = $usuario['id'];
        $_SESSION['usuario_nombre'] = $usuario['nombre'] . ' ' . $usuario['apellido'];
        $_SESSION['usuario_rol'] = $usuario['rol'];
        
        // Redirigir al index
        header('Location: index.php');
        exit;
    } else {
        // Credenciales incorrectas
        $_SESSION['login_error'] = 'Usuario o contraseña incorrectos.';
        header('Location: login.php');
        exit;
    }
} catch (PDOException $e) {
    $_SESSION['login_error'] = 'Error al iniciar sesión. Por favor, inténtelo de nuevo.';
    header('Location: login.php');
    exit;
}