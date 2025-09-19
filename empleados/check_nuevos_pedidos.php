<?php
// empleados/check_nuevos_pedidos.php
require_once '../config.php';
session_start();

// Verificar acceso de empleado
if (!isset($_SESSION['empleado_logged']) || $_SESSION['empleado_logged'] !== true) {
    http_response_code(403);
    exit;
}

// Configurar headers para respuesta JSON
header('Content-Type: application/json');
header('Cache-Control: no-cache, must-revalidate');

$pdo = getConnection();

// Obtener timestamp de última verificación desde la sesión
$ultima_verificacion = $_SESSION['ultima_verificacion_pedidos'] ?? date('Y-m-d H:i:s', strtotime('-1 hour'));

// Buscar pedidos nuevos desde la última verificación
$stmt = $pdo->prepare("
    SELECT COUNT(*) as nuevos_pedidos,
           GROUP_CONCAT(CONCAT('#', id, ' - ', nombre, ' ', apellido) SEPARATOR ', ') as lista_pedidos
    FROM pedidos 
    WHERE created_at > ? 
    AND estado IN ('Pendiente', 'Preparando')
");
$stmt->execute([$ultima_verificacion]);
$resultado = $stmt->fetch();

// Obtener detalles de los pedidos nuevos para la notificación
$pedidos_nuevos = [];
if ($resultado['nuevos_pedidos'] > 0) {
    $stmt_detalles = $pdo->prepare("
        SELECT id, nombre, apellido, producto, modalidad, ubicacion, 
               precio, estado, created_at,
               TIMESTAMPDIFF(MINUTE, created_at, NOW()) as minutos_transcurridos
        FROM pedidos 
        WHERE created_at > ? 
        AND estado IN ('Pendiente', 'Preparando')
        ORDER BY created_at DESC
        LIMIT 10
    ");
    $stmt_detalles->execute([$ultima_verificacion]);
    $pedidos_nuevos = $stmt_detalles->fetchAll();
}

// Actualizar timestamp de última verificación
$_SESSION['ultima_verificacion_pedidos'] = date('Y-m-d H:i:s');

// Respuesta JSON
$response = [
    'nuevos_pedidos' => (int)$resultado['nuevos_pedidos'],
    'pedidos' => $pedidos_nuevos,
    'ultima_verificacion' => $ultima_verificacion,
    'timestamp' => date('Y-m-d H:i:s')
];

echo json_encode($response);
?>