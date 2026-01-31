<?php
require_once '../../config.php';
requireLogin();

$pdo = getConnection();
header('Content-Type: application/json');

// GET: Obtener datos del pedido
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['id'])) {
    $pedido_id = (int)$_GET['id'];

    try {
        $stmt = $pdo->prepare("SELECT * FROM pedidos WHERE id = ?");
        $stmt->execute([$pedido_id]);
        $pedido = $stmt->fetch();

        if ($pedido) {
            echo json_encode(['success' => true, 'pedido' => $pedido]);
        } else {
            echo json_encode(['success' => false, 'error' => 'Pedido no encontrado']);
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    exit;
}

// POST: Guardar cambios
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $json = file_get_contents('php://input');
    $data = json_decode($json, true);

    if (!$data) {
        echo json_encode(['success' => false, 'error' => 'Datos inválidos']);
        exit;
    }

    $pedido_id = (int)$data['id'];
    $producto = htmlspecialchars(strip_tags(trim($data['producto'])));
    $cantidad = (int)$data['cantidad'];
    $precio = (float)$data['precio'];
    $observaciones = htmlspecialchars(strip_tags(trim($data['observaciones'])));

    try {
        $stmt = $pdo->prepare("UPDATE pedidos SET producto = ?, cantidad = ?, precio = ?, observaciones = ?, updated_at = NOW() WHERE id = ?");
        $result = $stmt->execute([$producto, $cantidad, $precio, $observaciones, $pedido_id]);

        echo json_encode(['success' => $result]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    exit;
}

echo json_encode(['success' => false, 'error' => 'Método no permitido']);
