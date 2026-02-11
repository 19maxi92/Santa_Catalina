<?php
/**
 * API para detectar nuevos pedidos en Local 1
 * Devuelve true si hay pedidos nuevos desde la ultima verificacion
 */
session_start();
require_once '../admin/config.php';

header('Content-Type: application/json');

if (!isset($_SESSION['empleado_logged']) || $_SESSION['empleado_logged'] !== true) {
    echo json_encode(['success' => false, 'error' => 'No autenticado']);
    exit;
}

$pdo = getConnection();

// Obtener el ID del ultimo pedido visto
$ultimo_id_visto = isset($_SESSION['ultimo_pedido_visto_local1']) ? (int)$_SESSION['ultimo_pedido_visto_local1'] : 0;

// Si es la primera vez, inicializar con el maximo actual para no notificar pedidos viejos
if ($ultimo_id_visto === 0) {
    $max_actual = $pdo->query("SELECT MAX(id) FROM pedidos WHERE ubicacion = 'Local 1'")->fetchColumn();
    $_SESSION['ultimo_pedido_visto_local1'] = $max_actual ?: 0;
    echo json_encode([
        'success' => true,
        'hay_nuevos' => false,
        'cantidad' => 0,
        'ultimo_id' => $max_actual ?: 0
    ]);
    exit;
}

// Buscar pedidos mas recientes para Local 1
$stmt = $pdo->prepare("
    SELECT MAX(id) as max_id, COUNT(*) as nuevos
    FROM pedidos
    WHERE ubicacion = 'Local 1'
    AND id > ?
");
$stmt->execute([$ultimo_id_visto]);
$resultado = $stmt->fetch();

$hay_nuevos = $resultado['nuevos'] > 0;
$max_id = $resultado['max_id'] ?? $ultimo_id_visto;

// Actualizar el ultimo ID visto
if ($hay_nuevos) {
    $_SESSION['ultimo_pedido_visto_local1'] = $max_id;
}

echo json_encode([
    'success' => true,
    'hay_nuevos' => $hay_nuevos,
    'cantidad' => (int)$resultado['nuevos'],
    'ultimo_id' => $max_id
]);
?>
