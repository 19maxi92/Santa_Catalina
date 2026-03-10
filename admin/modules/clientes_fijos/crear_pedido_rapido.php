<?php
/**
 * Procesar pedido rápido desde cliente fijo
 */

require_once '../../../config/database.php';

header('Content-Type: application/json');

try {
    $nombre = $_POST['nombre'] ?? '';
    $apellido = $_POST['apellido'] ?? '';
    $telefono = $_POST['telefono'] ?? '';
    $direccion = $_POST['direccion'] ?? '';
    $producto = $_POST['producto'] ?? '';
    $precio = (float)($_POST['precio'] ?? 0);
    $modalidad = $_POST['modalidad'] ?? 'Retiro';
    $turno = $_POST['turno'] ?? '';
    $ubicacion = $_POST['ubicacion'] ?? 'Local 1';
    $forma_pago = $_POST['forma_pago'] ?? 'Efectivo';
    $ya_pagado = isset($_POST['ya_pagado']) ? 1 : 0;
    $observaciones = $_POST['observaciones'] ?? '';

    if (!$nombre || !$producto || !$turno) {
        throw new Exception('Faltan datos obligatorios');
    }

    // Agregar info del turno a observaciones
    $observaciones = "Turno: $turno" . ($observaciones ? "\n$observaciones" : '');
    if ($ya_pagado) {
        $observaciones .= "\n✅ PAGADO";
    }

    $stmt = $pdo->prepare("
        INSERT INTO pedidos (nombre, apellido, telefono, direccion, producto, precio, modalidad, ubicacion, forma_pago, observaciones, estado, fecha_entrega, created_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'Pendiente', CURDATE(), NOW())
    ");

    $stmt->execute([
        $nombre,
        $apellido,
        $telefono,
        $direccion,
        $producto,
        $precio,
        $modalidad,
        $ubicacion,
        $forma_pago,
        $observaciones
    ]);

    $pedidoId = $pdo->lastInsertId();

    echo json_encode([
        'success' => true,
        'pedido_id' => $pedidoId,
        'producto' => $producto,
        'precio' => $precio
    ]);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
