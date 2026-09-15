<?php
// IMPORTANTE: Configurar timezone de Argentina (GMT-3) para solucionar diferencia de horarios
date_default_timezone_set('America/Argentina/Buenos_Aires');

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

        // IMPORTANTE: Configurar timezone de MySQL a Argentina (GMT-3)
        $pdo->exec("SET time_zone = '-03:00'");

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

// Formatear fecha con timezone correcto de Argentina
function formatDateTime($datetime, $format = 'd/m H:i') {
    if (empty($datetime)) return '';

    // Crear DateTime en UTC (como viene de MySQL si no tiene timezone)
    $dt = new DateTime($datetime, new DateTimeZone('UTC'));

    // Convertir a timezone de Argentina
    $dt->setTimezone(new DateTimeZone('America/Argentina/Buenos_Aires'));

    // Retornar formateado
    return $dt->format($format);
}

// Verificar login
function isLoggedIn() {
    session_start();
    return isset($_SESSION['admin_logged']) && $_SESSION['admin_logged'] === true;
}

// Requerir login
function requireLogin() {
    if (!isLoggedIn()) {
        header('Location: login.php');
        exit;
    }
}

// Login de "staff": admin O empleado (para páginas compartidas como crear pedido)
function isStaffLoggedIn() {
    if (session_status() === PHP_SESSION_NONE) session_start();
    return (isset($_SESSION['admin_logged']) && $_SESSION['admin_logged'] === true)
        || (isset($_SESSION['empleado_logged']) && $_SESSION['empleado_logged'] === true);
}

// Si es empleado (no admin), devuelve su sucursal asignada; si es admin, null (sin restricción)
function staffUbicacionRestringida() {
    if (session_status() === PHP_SESSION_NONE) session_start();
    if (!empty($_SESSION['admin_logged'])) return null;
    if (!empty($_SESSION['empleado_logged'])) return $_SESSION['empleado_ubicacion'] ?? 'Local 1';
    return null;
}

function requireStaffLogin() {
    if (!isStaffLoggedIn()) {
        header('Location: /admin/login.php');
        exit;
    }
}

// Ubicaciones cuyos pedidos debe ver/gestionar un empleado, según su sucursal asignada.
// Local 1 también arma los pedidos de reparto que se cargan como Fábrica.
function ubicacionesVisibles($mi_ubicacion) {
    if ($mi_ubicacion === 'Local 1') {
        return ['Local 1', 'Fábrica'];
    }
    return [$mi_ubicacion];
}

/**
 * Encola un trabajo para la app de escritorio "Estación de Impresión"
 * (estacion-impresion/), que lo levanta sola por api-impresion/.
 *
 *   $accion = 'imprimir_comanda'  → imprime la comanda del pedido
 *   $accion = 'abrir_cajon'       → solo abre el cajón de dinero (cobro en efectivo)
 *
 * Nunca rompe el flujo que la llama: si algo falla devuelve false y lo deja
 * en el error_log. Si falta la columna `accion` (migración
 * migrations/add_accion_cola_impresion.php sin correr), la agrega sola y
 * reintenta, para que el cajón no quede "mudo" sin que nadie se entere.
 */
function encolarTrabajoImpresion(PDO $pdo, int $pedido_id, string $ubicacion, string $accion = 'imprimir_comanda'): bool {
    $prefijo = $accion === 'abrir_cajon' ? 'cajon' : 'comanda';
    // Sufijo aleatorio: dos clics en el mismo segundo no chocan contra el UNIQUE de `codigo`
    $codigo = $prefijo . '_' . $pedido_id . '_' . time() . '_' . bin2hex(random_bytes(2));
    $sql = "INSERT INTO cola_impresion (pedido_id, codigo, ubicacion, accion, estado) VALUES (?, ?, ?, ?, 'pendiente')";

    try {
        $pdo->prepare($sql)->execute([$pedido_id, $codigo, $ubicacion, $accion]);
        return true;
    } catch (\Throwable $e) {
        $msg = $e->getMessage();
        if (stripos($msg, 'accion') !== false && stripos($msg, 'column') !== false) {
            try {
                $pdo->exec("ALTER TABLE cola_impresion ADD COLUMN accion VARCHAR(30) NOT NULL DEFAULT 'imprimir_comanda' AFTER ubicacion");
                $pdo->prepare($sql)->execute([$pedido_id, $codigo, $ubicacion, $accion]);
                return true;
            } catch (\Throwable $e2) {
                $msg = $e2->getMessage();
            }
        }
        error_log("cola_impresion: no se pudo encolar '$accion' del pedido #$pedido_id ($ubicacion): $msg");
        return false;
    }
}
?>