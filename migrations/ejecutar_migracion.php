<?php
/**
 * Script de migración para agregar columna fecha_display
 * Ejecutar desde navegador: http://tu-dominio.com/migrations/ejecutar_migracion.php
 * O desde CLI: php migrations/ejecutar_migracion.php
 */

require_once __DIR__ . '/../config.php';

header('Content-Type: text/plain; charset=utf-8');

echo "🚀 INICIANDO MIGRACIÓN: Agregar columna fecha_display\n";
echo str_repeat('=', 60) . "\n\n";

try {
    $pdo = getConnection();

    // Verificar si la columna ya existe
    echo "1. Verificando si columna ya existe...\n";
    $result = $pdo->query("SHOW COLUMNS FROM pedidos LIKE 'fecha_display'");

    if ($result->rowCount() > 0) {
        echo "   ⚠️  La columna 'fecha_display' ya existe.\n";
        echo "   ℹ️  Saltando creación de columna.\n\n";
    } else {
        echo "   ✅ Columna no existe. Procediendo a crearla...\n\n";

        // Agregar columna
        echo "2. Agregando columna fecha_display...\n";
        $pdo->exec("
            ALTER TABLE pedidos
            ADD COLUMN fecha_display VARCHAR(20) DEFAULT NULL
            COMMENT 'Fecha formateada para mostrar (timezone Argentina)'
        ");
        echo "   ✅ Columna agregada exitosamente!\n\n";
    }

    // Contar pedidos sin fecha_display
    echo "3. Contando pedidos que necesitan migración...\n";
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM pedidos WHERE fecha_display IS NULL");
    $total = $stmt->fetch()['total'];
    echo "   📊 Pedidos a migrar: $total\n\n";

    if ($total > 0) {
        // Llenar datos existentes
        echo "4. Migrando fechas de pedidos existentes...\n";
        $pdo->exec("
            UPDATE pedidos
            SET fecha_display = DATE_FORMAT(
                CONVERT_TZ(created_at, '+00:00', '-03:00'),
                '%d/%m %H:%i'
            )
            WHERE fecha_display IS NULL
        ");
        echo "   ✅ $total pedidos migrados exitosamente!\n\n";
    } else {
        echo "   ℹ️  No hay pedidos para migrar.\n\n";
    }

    // Verificar resultado
    echo "5. Verificando migración (últimos 10 pedidos)...\n";
    echo str_repeat('-', 60) . "\n";
    $stmt = $pdo->query("
        SELECT id, created_at, fecha_display
        FROM pedidos
        ORDER BY id DESC
        LIMIT 10
    ");

    while ($row = $stmt->fetch()) {
        printf(
            "ID: %-5s | created_at: %s | fecha_display: %s\n",
            $row['id'],
            $row['created_at'],
            $row['fecha_display'] ?? 'NULL'
        );
    }

    echo str_repeat('-', 60) . "\n\n";
    echo "✅ MIGRACIÓN COMPLETADA EXITOSAMENTE!\n";
    echo "✅ La columna fecha_display está lista para usar.\n";

} catch (Exception $e) {
    echo "\n❌ ERROR EN LA MIGRACIÓN:\n";
    echo "   " . $e->getMessage() . "\n\n";
    echo "Stack trace:\n";
    echo $e->getTraceAsString() . "\n";
    exit(1);
}
