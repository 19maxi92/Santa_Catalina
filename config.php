<?php
// Configuración de base de datos para Hostinger
define('DB_HOST', 'localhost');
define('DB_NAME', 'u246760540_santa_catalina'); 
define('DB_USER', 'u246760540_admin_sc'); // ← Este es el usuario correcto
define('DB_PASS', "Sangu2025!"); // ← Con comillas dobles

// Configuración general
define('APP_NAME', 'Santa Catalina Admin');
define('APP_VERSION', '1.0');

// Conexión a la base de datos
function getConnection() {
    try {
        $pdo = new PDO(
            "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
            DB_USER,
            DB_PASS,
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false
            ]
        );
        return $pdo;
    } catch (PDOException $e) {
        die("Error de conexión: " . $e->getMessage());
    }
}

// Funciones de utilidad
function sanitize($data) {
    return htmlspecialchars(strip_tags(trim($data)));
}

function formatPrice($price) {
    return '$' . number_format($price, 0, ',', '.');
}

// Verificar login ADMIN
function isLoggedIn() {
    session_start();
    return isset($_SESSION['admin_logged']) && $_SESSION['admin_logged'] === true;
}

// Requerir login ADMIN
function requireLogin() {
    if (!isLoggedIn()) {
        header('Location: login.php');
        exit;
    }
}

// Verificar login EMPLEADO
function isEmpleadoLoggedIn() {
    session_start();
    return isset($_SESSION['empleado_logged']) && $_SESSION['empleado_logged'] === true;
}

// Requerir login EMPLEADO
function requireEmpleadoLogin() {
    if (!isEmpleadoLoggedIn()) {
        header('Location: login.php');
        exit;
    }
}
?>