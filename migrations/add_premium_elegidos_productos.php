<?php
require_once '../admin/config.php';
$pdo = getConnection();

$productos = [
    ['Surtidos Premium x8',  9000,   8100,  'Premium'],
    ['Surtidos Premium x16', 18000,  16200, 'Premium'],
    ['Surtidos Premium x24', 27000,  24300, 'Premium'],
    ['Surtidos Premium x32', 36000,  32400, 'Premium'],
    ['Surtidos Premium x40', 45000,  40500, 'Premium'],
    ['Surtidos Premium x48', 54000,  48600, 'Premium'],
    ['Surtidos Elegidos x8',  5400,  4860,  'Elegidos'],
    ['Surtidos Elegidos x16', 10800, 9720,  'Elegidos'],
    ['Surtidos Elegidos x24', 16000, 14400, 'Elegidos'],
    ['Surtidos Elegidos x32', 21400, 19260, 'Elegidos'],
    ['Surtidos Elegidos x40', 26800, 24120, 'Elegidos'],
    ['Surtidos Elegidos x48', 32000, 28800, 'Elegidos'],
];

$stmt = $pdo->prepare("
    INSERT INTO productos (nombre, precio_transferencia, precio_efectivo, categoria, activo, created_at, updated_at, updated_by)
    VALUES (?, ?, ?, ?, 1, NOW(), NOW(), 'admin')
    ON DUPLICATE KEY UPDATE precio_transferencia = VALUES(precio_transferencia), precio_efectivo = VALUES(precio_efectivo)
");

foreach ($productos as [$nombre, $tr, $ef, $cat]) {
    $stmt->execute([$nombre, $tr, $ef, $cat]);
}

echo "✅ Productos Premium y Elegidos cargados correctamente.";
