<?php
/**
 * Diagnóstico (solo lectura, no cambia nada): busca "Villa Elisa" en
 * CUALQUIER lado de un pedido (dirección, observaciones, nombre) sin
 * importar su ubicacion o modalidad actual, para ver cómo quedaron
 * guardados de verdad y poder afinar la reparación.
 * Ejecutar desde navegador: /migrations/buscar_villa_elisa.php
 */

require_once __DIR__ . '/../admin/config.php';

header('Content-Type: text/plain; charset=utf-8');

echo "🔎 BUSCANDO 'Villa Elisa' en cualquier parte de los pedidos\n";
echo str_repeat('=', 70) . "\n\n";

try {
    $pdo = getConnection();

    $stmt = $pdo->query("
        SELECT id, nombre, apellido, telefono, ubicacion, modalidad, direccion, observaciones, created_at
        FROM pedidos
        WHERE direccion LIKE '%Villa Elisa%'
           OR observaciones LIKE '%Villa Elisa%'
           OR nombre LIKE '%Villa Elisa%'
           OR apellido LIKE '%Villa Elisa%'
        ORDER BY id DESC
        LIMIT 50
    ");
    $filas = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($filas)) {
        echo "No se encontró NINGÚN pedido que mencione 'Villa Elisa' en dirección, observaciones o nombre.\n";
        echo "Esto sugeriría que esos pedidos usan otra palabra (ej: 'V. Elisa', 'V Elisa') o que la\n";
        echo "ubicación se seleccionó directamente sin que el texto 'Villa Elisa' quede en ningún campo.\n";
        exit;
    }

    echo "Se encontraron " . count($filas) . " pedido(s):\n\n";
    foreach ($filas as $p) {
        echo str_repeat('-', 70) . "\n";
        echo "ID #{$p['id']} — {$p['nombre']} {$p['apellido']} — {$p['telefono']} — {$p['created_at']}\n";
        echo "  ubicacion actual: {$p['ubicacion']}   |   modalidad: {$p['modalidad']}\n";
        echo "  direccion:        " . ($p['direccion'] ?: '(vacío)') . "\n";
        echo "  observaciones:    " . str_replace("\n", ' / ', $p['observaciones'] ?: '(vacío)') . "\n";
    }
    echo str_repeat('-', 70) . "\n";

} catch (Exception $e) {
    echo "\n❌ ERROR: " . $e->getMessage() . "\n";
}
