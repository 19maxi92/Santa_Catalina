<?php
/**
 * Script para reparar todas las fechas_display
 * Ejecutar desde navegador: http://tu-dominio.com/migrations/reparar_fechas.php
 */

require_once __DIR__ . '/../config.php';

header('Content-Type: text/plain; charset=utf-8');

echo "🔧 REPARANDO TODAS LAS FECHAS\n";
echo str_repeat('=', 60) . "\n\n";

try {
    $pdo = getConnection();

    // Contar pedidos
    echo "1. Contando pedidos...\n";
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM pedidos");
    $total = $stmt->fetch()['total'];
    echo "   📊 Total de pedidos: $total\n\n";

    // Re-calcular TODAS las fechas usando MySQL (más confiable)
    echo "2. Re-calculando fechas con MySQL...\n";
    $pdo->exec("
        UPDATE pedidos
        SET fecha_display = DATE_FORMAT(
            CONVERT_TZ(created_at, '+00:00', '-03:00'),
            '%d/%m %H:%i'
        )
    ");
    echo "   ✅ $total fechas actualizadas!\n\n";

    // Verificar resultado
    echo "3. Verificando últimos 10 pedidos...\n";
    echo str_repeat('-', 60) . "\n";
    printf("%-5s | %-19s | %-15s\n", "ID", "created_at (UTC)", "fecha_display");
    echo str_repeat('-', 60) . "\n";

    $stmt = $pdo->query("
        SELECT id, created_at, fecha_display
        FROM pedidos
        ORDER BY id DESC
        LIMIT 10
    ");

    while ($row = $stmt->fetch()) {
        printf(
            "%-5s | %-19s | %-15s\n",
            $row['id'],
            $row['created_at'],
            $row['fecha_display'] ?? 'NULL'
        );
    }

    echo str_repeat('-', 60) . "\n\n";
    echo "✅ REPARACIÓN COMPLETADA!\n";
    echo "✅ Todas las fechas fueron re-calculadas correctamente.\n";
    echo "\nℹ️  Los NUEVOS pedidos ahora usarán DateTime con timezone explícito.\n";

} catch (Exception $e) {
    echo "\n❌ ERROR:\n";
    echo "   " . $e->getMessage() . "\n\n";
    exit(1);
}
