<?php
/**
 * API para detectar nuevos pedidos en la sucursal del empleado logueado
 * Devuelve true si hay pedidos nuevos desde la ultima verificacion
 */
session_start();
require_once '../admin/config.php';

header('Content-Type: application/json');

if (!isset($_SESSION['empleado_logged']) || $_SESSION['empleado_logged'] !== true) {
    echo json_encode(['success' => false, 'error' => 'No autenticado']);
    exit;
}

$mi_ubicacion = $_SESSION['empleado_ubicacion'] ?? 'Local 1';

$pdo = getConnection();

// Clave de sesión por sucursal (para no mezclar el "último visto" entre sucursales)
$session_key = 'ultimo_pedido_visto_' . preg_replace('/[^a-z0-9]/i', '', $mi_ubicacion);

$ultimo_id_visto = isset($_SESSION[$session_key]) ? (int)$_SESSION[$session_key] : 0;

// Si es la primera vez, inicializar con el maximo actual para no notificar pedidos viejos
if ($ultimo_id_visto === 0) {
    $stmt = $pdo->prepare("SELECT MAX(id) FROM pedidos WHERE ubicacion = ? AND (tomado = 1 OR tomado IS NULL)");
    $stmt->execute([$mi_ubicacion]);
    $max_actual = $stmt->fetchColumn();
    $_SESSION[$session_key] = $max_actual ?: 0;
    echo json_encode([
        'success' => true,
        'hay_nuevos' => false,
        'cantidad' => 0,
        'ultimo_id' => $max_actual ?: 0
    ]);
    exit;
}

// Buscar pedidos mas recientes para la sucursal
$stmt = $pdo->prepare("
    SELECT MAX(id) as max_id, COUNT(*) as nuevos
    FROM pedidos
    WHERE ubicacion = ?
    AND (tomado = 1 OR tomado IS NULL)
    AND id > ?
");
$stmt->execute([$mi_ubicacion, $ultimo_id_visto]);
$resultado = $stmt->fetch();

$hay_nuevos = $resultado['nuevos'] > 0;
$max_id = $resultado['max_id'] ?? $ultimo_id_visto;

if ($hay_nuevos) {
    $_SESSION[$session_key] = $max_id;
}

echo json_encode([
    'success' => true,
    'hay_nuevos' => $hay_nuevos,
    'cantidad' => (int)$resultado['nuevos'],
    'ultimo_id' => $max_id
]);
?>
