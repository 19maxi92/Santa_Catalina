<?php
/**
 * API para detectar nuevos pedidos en Local 1
 * Devuelve true si hay pedidos nuevos desde la última verificación
 */
session_start();
require_once '../admin/config.php';

header('Content-Type: application/json');

if (!isset($_SESSION['empleado_logged']) || $_SESSION['empleado_logged'] !== true) {
    echo json_encode(['success' => false, 'error' => 'No autenticado']);
    exit;
}

$pdo = getConnection();

// Obtener el ID del último pedido que vio este empleado
$ultimo_id_visto = isset($_SESSION['ultimo_pedido_visto']) ? (int)$_SESSION['ultimo_pedido_visto'] : 0;

// Buscar pedidos más recientes para Local 1
$stmt = $pdo->prepare("
    SELECT MAX(id) as max_id, COUNT(*) as nuevos
    FROM pedidos
    WHERE ubicacion = 'Local 1'
    AND id > ?
    AND DATE(created_at) = CURDATE()
");
$stmt->execute([$ultimo_id_visto]);
$resultado = $stmt->fetch();

$hay_nuevos = $resultado['nuevos'] > 0;
$max_id = $resultado['max_id'] ?? $ultimo_id_visto;

// Actualizar el último ID visto en la sesión
if ($hay_nuevos) {
    $_SESSION['ultimo_pedido_visto'] = $max_id;
}

echo json_encode([
    'success' => true,
    'hay_nuevos' => $hay_nuevos,
    'cantidad' => (int)$resultado['nuevos'],
    'ultimo_id' => $max_id
]);
?>
