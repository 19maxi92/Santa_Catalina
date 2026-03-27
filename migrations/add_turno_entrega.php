<?php
/**
 * Migración: agregar columna turno_entrega a tabla pedidos
 * Permite calcular cupos disponibles por turno+fecha de forma exacta.
 * Ejecutar desde navegador: /migrations/add_turno_entrega.php
 */

require_once __DIR__ . '/../admin/config.php';

header('Content-Type: text/plain; charset=utf-8');

echo "🚀 MIGRACIÓN: Agregar columna turno_entrega\n";
echo str_repeat('=', 60) . "\n\n";

try {
    $pdo = getConnection();

    // 1. Agregar columna si no existe
    $result = $pdo->query("SHOW COLUMNS FROM pedidos LIKE 'turno_entrega'");
    if ($result->rowCount() > 0) {
        echo "⚠️  La columna 'turno_entrega' ya existe. Saltando creación.\n\n";
    } else {
        $pdo->exec("ALTER TABLE pedidos ADD COLUMN turno_entrega VARCHAR(20) DEFAULT NULL COMMENT 'Turno del pedido online (Mañana/Siesta/Tarde)'");
        echo "✅ Columna 'turno_entrega' creada.\n\n";
    }

    // 2. Poblar con datos de pedidos online existentes (extraer de observaciones)
    echo "2. Migrando pedidos online existentes...\n";
    $stmt = $pdo->query("SELECT id, observaciones FROM pedidos WHERE observaciones LIKE '%PEDIDO ONLINE%' AND turno_entrega IS NULL");
    $pedidos = $stmt->fetchAll();
    echo "   📊 Pedidos online sin turno_entrega: " . count($pedidos) . "\n";

    $actualizados = 0;
    foreach ($pedidos as $p) {
        if (preg_match('/Turno:\s*(Mañana|Siesta|Tarde)/u', $p['observaciones'], $m)) {
            $upd = $pdo->prepare("UPDATE pedidos SET turno_entrega = ? WHERE id = ?");
            $upd->execute([$m[1], $p['id']]);
            $actualizados++;
        }
    }
    echo "   ✅ $actualizados pedidos actualizados.\n\n";

    echo "✅ MIGRACIÓN COMPLETADA.\n";

} catch (Exception $e) {
    echo "\n❌ ERROR: " . $e->getMessage() . "\n";
    exit(1);
}
