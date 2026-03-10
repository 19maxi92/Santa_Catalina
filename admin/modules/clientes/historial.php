<?php
/**
 * Historial de pedidos de un cliente
 */
require_once '../../config.php';
requireLogin();

$pdo = getConnection();

$telefono = $_GET['telefono'] ?? '';
if (!$telefono) {
    echo '<div class="text-red-500">Telefono no especificado</div>';
    exit;
}

// Si piden solo favoritos, devolver JSON
if (isset($_GET['solo_favoritos'])) {
    header('Content-Type: application/json');
    $stmt = $pdo->prepare("
        SELECT producto, precio, COUNT(*) as veces
        FROM pedidos WHERE telefono = ?
        GROUP BY producto, precio
        ORDER BY veces DESC LIMIT 5
    ");
    $stmt->execute([$telefono]);
    echo json_encode(['favoritos' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
    exit;
}

// Obtener pedidos del cliente
$stmt = $pdo->prepare("SELECT * FROM pedidos WHERE telefono = ? ORDER BY created_at DESC LIMIT 20");
$stmt->execute([$telefono]);
$pedidos = $stmt->fetchAll();

if (empty($pedidos)):
?>
<div class="text-center py-8 text-gray-500">
    <i class="fas fa-inbox text-4xl mb-2"></i>
    <p>No hay pedidos registrados</p>
</div>
<?php else: ?>

<div class="mb-4 text-sm text-gray-600">
    <strong><?= count($pedidos) ?></strong> pedidos encontrados
</div>

<div class="space-y-3">
    <?php foreach ($pedidos as $pedido): ?>
    <div class="bg-gray-50 rounded-lg p-4 border-l-4 <?= $pedido['estado'] === 'Entregado' ? 'border-green-500' : 'border-blue-500' ?>">
        <div class="flex items-start justify-between">
            <div class="flex-1">
                <div class="flex items-center gap-2 mb-1">
                    <span class="font-bold text-blue-600">#<?= $pedido['id'] ?></span>
                    <span class="text-xs text-gray-500"><?= date('d/m/Y H:i', strtotime($pedido['created_at'])) ?></span>
                    <span class="text-xs px-2 py-0.5 rounded-full <?= $pedido['estado'] === 'Entregado' ? 'bg-green-100 text-green-700' : 'bg-yellow-100 text-yellow-700' ?>">
                        <?= $pedido['estado'] ?>
                    </span>
                </div>
                <div class="font-semibold"><?= htmlspecialchars($pedido['producto']) ?></div>
                <div class="text-sm text-gray-600 flex items-center gap-3">
                    <span class="text-green-600 font-bold">$<?= number_format($pedido['precio'], 0, ',', '.') ?></span>
                    <span><?= $pedido['modalidad'] === 'Retiro' ? '🏪' : '🛵' ?> <?= $pedido['modalidad'] ?></span>
                    <span><?= $pedido['forma_pago'] === 'Efectivo' ? '💵' : '💳' ?> <?= $pedido['forma_pago'] ?></span>
                </div>
            </div>
            <button onclick="repetirPedido(<?= $pedido['id'] ?>)"
                    class="bg-green-500 hover:bg-green-600 text-white px-3 py-2 rounded-lg text-sm font-bold flex items-center gap-1">
                <i class="fas fa-redo"></i> Repetir
            </button>
        </div>
    </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>
