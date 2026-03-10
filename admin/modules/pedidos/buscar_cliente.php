<?php
/**
 * Buscar cliente frecuente por teléfono
 * Busca en pedidos anteriores para autocompletar datos
 */

require_once '../../../config/database.php';

header('Content-Type: application/json');

$telefono = $_GET['telefono'] ?? '';
$telefono = trim($telefono);

if (strlen($telefono) < 6) {
    echo json_encode(['found' => false, 'error' => 'Teléfono muy corto']);
    exit;
}

try {
    // Buscar el pedido más reciente con ese teléfono
    $stmt = $pdo->prepare("
        SELECT nombre, apellido, telefono, direccion
        FROM pedidos
        WHERE telefono LIKE ?
        ORDER BY created_at DESC
        LIMIT 1
    ");
    $stmt->execute(['%' . $telefono . '%']);
    $cliente = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($cliente) {
        echo json_encode([
            'found' => true,
            'cliente' => [
                'nombre' => $cliente['nombre'],
                'apellido' => $cliente['apellido'],
                'telefono' => $cliente['telefono'],
                'direccion' => $cliente['direccion'] ?? ''
            ]
        ]);
    } else {
        echo json_encode(['found' => false]);
    }
} catch (Exception $e) {
    echo json_encode(['found' => false, 'error' => $e->getMessage()]);
}
