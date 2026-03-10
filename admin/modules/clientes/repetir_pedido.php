<?php
/**
 * Repetir un pedido anterior exactamente igual
 */
require_once '../../config.php';
requireLogin();

$pdo = getConnection();

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Metodo no permitido']);
    exit;
}

$pedidoId = $_GET['id'] ?? 0;
if (!$pedidoId) {
    echo json_encode(['success' => false, 'error' => 'ID no especificado']);
    exit;
}

try {
    $stmt = $pdo->prepare("SELECT * FROM pedidos WHERE id = ?");
    $stmt->execute([$pedidoId]);
    $original = $stmt->fetch();

    if (!$original) {
        throw new Exception('Pedido no encontrado');
    }

    $stmt = $pdo->prepare("
        INSERT INTO pedidos (nombre, apellido, telefono, direccion, producto, precio, modalidad, ubicacion, forma_pago, observaciones, estado, fecha_entrega, created_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'Pendiente', CURDATE(), NOW())
    ");

    $observaciones = "Repetido de #" . $original['id'] . "\n" . ($original['observaciones'] ?? '');

    $stmt->execute([
        $original['nombre'], $original['apellido'], $original['telefono'], $original['direccion'],
        $original['producto'], $original['precio'], $original['modalidad'], $original['ubicacion'],
        $original['forma_pago'], $observaciones
    ]);

    $nuevoId = $pdo->lastInsertId();

    echo json_encode([
        'success' => true,
        'nuevo_id' => $nuevoId,
        'producto' => $original['producto'],
        'precio' => (float)$original['precio']
    ]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
