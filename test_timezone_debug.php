<?php
require_once 'config.php';

header('Content-Type: text/plain; charset=utf-8');

echo "🔍 DEBUG DE TIMEZONE\n";
echo str_repeat('=', 60) . "\n\n";

// 1. Verificar configuración PHP
echo "1. CONFIGURACIÓN PHP:\n";
echo "   - date_default_timezone_get(): " . date_default_timezone_get() . "\n";
echo "   - date('Y-m-d H:i:s'): " . date('Y-m-d H:i:s') . "\n";
echo "   - date('d/m H:i'): " . date('d/m H:i') . "\n\n";

// 2. DateTime con timezone explícito
echo "2. DATETIME CON TIMEZONE EXPLÍCITO:\n";
$dt = new DateTime('now', new DateTimeZone('America/Argentina/Buenos_Aires'));
echo "   - DateTime Argentina: " . $dt->format('Y-m-d H:i:s') . "\n";
echo "   - Formato display: " . $dt->format('d/m H:i') . "\n\n";

// 3. Verificar últimos 5 pedidos en BD
echo "3. ÚLTIMOS 5 PEDIDOS EN BASE DE DATOS:\n";
echo str_repeat('-', 60) . "\n";

$pdo = getConnection();
$stmt = $pdo->query("
    SELECT id, created_at, fecha_display,
           DATE_FORMAT(CONVERT_TZ(created_at, '+00:00', '-03:00'), '%d/%m %H:%i') as fecha_convertida
    FROM pedidos
    ORDER BY id DESC
    LIMIT 5
");

printf("%-5s | %-19s | %-15s | %-15s\n", "ID", "created_at (UTC)", "fecha_display", "fecha_convertida");
echo str_repeat('-', 60) . "\n";

while ($row = $stmt->fetch()) {
    printf(
        "%-5s | %-19s | %-15s | %-15s\n",
        $row['id'],
        $row['created_at'],
        $row['fecha_display'] ?? 'NULL',
        $row['fecha_convertida']
    );
}

echo "\n" . str_repeat('=', 60) . "\n";
echo "4. ANÁLISIS:\n";
echo "   - Si fecha_display es NULL, la migración no se ejecutó correctamente\n";
echo "   - Si fecha_display != fecha_convertida, hay problema con PHP date()\n";
echo "   - fecha_convertida SIEMPRE debería ser correcta (calculada en MySQL)\n";
