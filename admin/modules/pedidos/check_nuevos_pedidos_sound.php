<?php
/**
 * API para detectar nuevos pedidos (ADMIN DASHBOARD PEDIDOS)
 * Detecta pedidos nuevos para cualquier ubicacion
 */
session_start();
require_once '../../config.php';

header('Content-Type: application/json');

if (!isset($_SESSION['admin_logged']) || $_SESSION['admin_logged'] !== true) {
    echo json_encode(['success' => false, 'error' => 'No autenticado']);
    exit;
}

$pdo = getConnection();

// Obtener filtro de ubicacion si existe
$ubicacion_filtro = isset($_GET['ubicacion']) ? $_GET['ubicacion'] : 'todas';

// Obtener el ID del ultimo pedido visto
$session_key = 'admin_dashboard_ultimo_pedido_' . md5($ubicacion_filtro);
$ultimo_id_visto = isset($_SESSION[$session_key]) ? (int)$_SESSION[$session_key] : 0;

// Si es la primera vez, obtener el max id actual
if ($ultimo_id_visto === 0) {
    $max_actual = $pdo->query("SELECT MAX(id) FROM pedidos")->fetchColumn();
    $_SESSION[$session_key] = $max_actual ?: 0;
    echo json_encode([
        'success' => true,
        'hay_nuevos' => false,
        'cantidad' => 0,
        'ultimo_id' => $max_actual ?: 0
    ]);
    exit;
}

// Construir query segun filtro
$where_ubicacion = '';
$params = [$ultimo_id_visto];

if ($ubicacion_filtro !== 'todas') {
    $where_ubicacion = 'AND ubicacion = ?';
    $params[] = $ubicacion_filtro;
}

$stmt = $pdo->prepare("
    SELECT MAX(id) as max_id, COUNT(*) as nuevos
    FROM pedidos
    WHERE id > ?
    $where_ubicacion
");
$stmt->execute($params);
$resultado = $stmt->fetch();

$hay_nuevos = $resultado['nuevos'] > 0;
$max_id = $resultado['max_id'] ?? $ultimo_id_visto;

// Actualizar el ultimo ID visto
if ($hay_nuevos) {
    $_SESSION[$session_key] = $max_id;
}

echo json_encode([
    'success' => true,
    'hay_nuevos' => $hay_nuevos,
    'cantidad' => (int)$resultado['nuevos'],
    'ultimo_id' => $max_id,
    'ubicacion' => $ubicacion_filtro
]);
?>
