<?php
/**
 * API para detectar nuevos pedidos (ADMIN)
 * Devuelve true si hay pedidos nuevos desde la ultima verificacion
 */
session_start();
require_once 'config.php';

header('Content-Type: application/json');

if (!isset($_SESSION['admin_logged']) || $_SESSION['admin_logged'] !== true) {
    echo json_encode(['success' => false, 'error' => 'No autenticado']);
    exit;
}

$pdo = getConnection();

// Obtener el ID del ultimo pedido que vio este admin
$ultimo_id_visto = isset($_SESSION['admin_ultimo_pedido_visto']) ? (int)$_SESSION['admin_ultimo_pedido_visto'] : 0;

// Si es la primera vez, obtener el max id actual para no notificar pedidos viejos
if ($ultimo_id_visto === 0) {
    $max_actual = $pdo->query("SELECT MAX(id) FROM pedidos")->fetchColumn();
    $_SESSION['admin_ultimo_pedido_visto'] = $max_actual ?: 0;
    echo json_encode([
        'success' => true,
        'hay_nuevos' => false,
        'cantidad' => 0,
        'ultimo_id' => $max_actual ?: 0
    ]);
    exit;
}

// Buscar pedidos mas recientes
$stmt = $pdo->prepare("
    SELECT MAX(id) as max_id, COUNT(*) as nuevos
    FROM pedidos
    WHERE id > ?
");
$stmt->execute([$ultimo_id_visto]);
$resultado = $stmt->fetch();

$hay_nuevos = $resultado['nuevos'] > 0;
$max_id = $resultado['max_id'] ?? $ultimo_id_visto;

// Actualizar el ultimo ID visto en la sesion
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
