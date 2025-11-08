<?php
require_once '../../config.php';
requireLogin();

$empleado_id = (int)($_GET['empleado_id'] ?? 0);

if (!$empleado_id) {
    echo "<p class='text-red-500'>ID de empleado inválido</p>";
    exit;
}

$pdo = getConnection();

// Obtener info del empleado
$stmt = $pdo->prepare("SELECT * FROM empleados_nomina WHERE id = ?");
$stmt->execute([$empleado_id]);
$empleado = $stmt->fetch();

if (!$empleado) {
    echo "<p class='text-red-500'>Empleado no encontrado</p>";
    exit;
}

// Obtener historial de pagos
$stmt = $pdo->prepare("
    SELECT *
    FROM pagos_empleados
    WHERE empleado_id = ?
    ORDER BY fecha_pago DESC
");
$stmt->execute([$empleado_id]);
$pagos = $stmt->fetchAll();

$total_pagado = array_sum(array_column($pagos, 'monto'));
?>

<div class="mb-4 p-4 bg-blue-50 rounded-lg">
    <div class="font-bold text-lg"><?= $empleado['nombre'] . ' ' . $empleado['apellido'] ?></div>
    <div class="text-sm text-gray-600">Salario: $<?= number_format($empleado['salario_mensual'], 0, ',', '.') ?>/mes</div>
    <div class="text-sm text-gray-600">Total pagado: $<?= number_format($total_pagado, 0, ',', '.') ?></div>
    <div class="text-sm text-gray-600">Cantidad de pagos: <?= count($pagos) ?></div>
</div>

<?php if (empty($pagos)): ?>
    <p class="text-gray-500 text-center py-4">No hay pagos registrados</p>
<?php else: ?>
<div class="space-y-2">
    <?php foreach ($pagos as $pago): ?>
    <div class="border rounded-lg p-3 hover:bg-gray-50">
        <div class="flex justify-between items-start">
            <div>
                <div class="font-bold text-green-600">$<?= number_format($pago['monto'], 0, ',', '.') ?></div>
                <div class="text-xs text-gray-600">
                    <?= date('d/m/Y', strtotime($pago['fecha_pago'])) ?>
                    <?php if ($pago['periodo_mes']): ?>
                        - Período: <?= $pago['periodo_mes'] ?>/<?= $pago['periodo_anio'] ?>
                    <?php endif; ?>
                </div>
                <div class="text-xs text-gray-500"><?= $pago['metodo_pago'] ?></div>
                <?php if ($pago['notas']): ?>
                    <div class="text-xs text-gray-600 mt-1"><?= htmlspecialchars($pago['notas']) ?></div>
                <?php endif; ?>
            </div>
            <div class="text-xs text-gray-400">
                ID: <?= $pago['id'] ?>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>
