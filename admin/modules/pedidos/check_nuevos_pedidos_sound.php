<?php
/**
 * API para detectar nuevos pedidos en Local 1 (versión admin)
 * Devuelve true si hay pedidos nuevos desde la última verificación
 */
session_start();
require_once '../../config.php';

header('Content-Type: application/json');

if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'error' => 'No autenticado']);
    exit;
}

$pdo = getConnection();

// Obtener el ID del último pedido que vio este admin
$ultimo_id_visto = isset($_SESSION['admin_ultimo_pedido_visto']) ? (int)$_SESSION['admin_ultimo_pedido_visto'] : 0;

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
    $_SESSION['admin_ultimo_pedido_visto'] = $max_id;
}

echo json_encode([
    'success' => true,
    'hay_nuevos' => $hay_nuevos,
    'cantidad' => (int)$resultado['nuevos'],
    'ultimo_id' => $max_id
]);
?>
