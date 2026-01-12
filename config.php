<?php
// admin/config.php - Versión corregida con manejo de rutas mejorado

// IMPORTANTE: Configurar timezone de Argentina (GMT-3) para solucionar diferencia de horarios
date_default_timezone_set('America/Argentina/Buenos_Aires');

// Auto-detectar si estamos en subdirectorio admin o no
$base_dir = dirname(__FILE__);
$is_in_admin = basename($base_dir) === 'admin';

// Si estamos en admin/modules/productos/, necesitamos subir 3 niveles hasta la raíz
// Si estamos en admin/, necesitamos subir 1 nivel hasta la raíz
$levels_up = $is_in_admin ? 1 : 3;

// Configuración de base de datos para Hostinger
define('DB_HOST', 'localhost');
define('DB_NAME', 'u246760540_santa_catalina'); 
define('DB_USER', 'u246760540_admin_sc');
define('DB_PASS', "Sangu2025!");

// Configuración general
define('APP_NAME', 'Santa Catalina');
define('APP_VERSION', '1.0');

// Debug info (solo para desarrollo)
if (!defined('CONFIG_DEBUG_LOADED')) {
    define('CONFIG_DEBUG_LOADED', true);
    // echo "<!-- Config cargado desde: " . __FILE__ . " -->\n";
}

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

        // IMPORTANTE: Configurar timezone de MySQL a Argentina (GMT-3)
        // Esto soluciona el problema de diferencia de 3 horas en los timestamps
        $pdo->exec("SET time_zone = '-03:00'");

        return $pdo;
    } catch (PDOException $e) {
        // Log error instead of die for better error handling
        error_log("Database connection error: " . $e->getMessage());
        die("Error de conexión: " . $e->getMessage());
    }
}

// Funciones de utilidad
function sanitize($data) {
    if (is_null($data)) return null;
    return htmlspecialchars(strip_tags(trim($data)));
}

function formatPrice($price) {
    if (is_null($price) || !is_numeric($price)) return '$0';
    return '$' . number_format($price, 0, ',', '.');
}

// Verificar login ADMIN
function isLoggedIn() {
    if (session_status() == PHP_SESSION_NONE) {
        session_start();
    }
    return isset($_SESSION['admin_logged']) && $_SESSION['admin_logged'] === true;
}

// Requerir login ADMIN
function requireLogin() {
    if (!isLoggedIn()) {
        // Determinar ruta de login según ubicación actual
        $current_dir = basename(dirname($_SERVER['SCRIPT_NAME']));
        
        if ($current_dir === 'productos' || $current_dir === 'modules') {
            // Estamos en admin/modules/productos/
            header('Location: ../../login.php');
        } elseif ($current_dir === 'admin') {
            // Estamos en admin/
            header('Location: login.php');
        } else {
            // Estamos en raíz o subdirectorio desconocido
            header('Location: admin/login.php');
        }
        exit;
    }
}

// Verificar login EMPLEADO
function isEmpleadoLoggedIn() {
    if (session_status() == PHP_SESSION_NONE) {
        session_start();
    }
    return isset($_SESSION['empleado_logged']) && $_SESSION['empleado_logged'] === true;
}

// Requerir login EMPLEADO
function requireEmpleadoLogin() {
    if (!isEmpleadoLoggedIn()) {
        header('Location: login.php');
        exit;
    }
}

// Función para debug (remover en producción)
function debugPath() {
    return [
        'current_file' => __FILE__,
        'current_dir' => __DIR__,
        'script_name' => $_SERVER['SCRIPT_NAME'],
        'request_uri' => $_SERVER['REQUEST_URI'],
        'base_dir' => basename(dirname($_SERVER['SCRIPT_NAME']))
    ];
}
?>