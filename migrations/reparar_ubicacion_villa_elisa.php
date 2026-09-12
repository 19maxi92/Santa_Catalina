<?php
/**
 * Reparación puntual: pedidos online de delivery a Villa Elisa que quedaron
 * grabados como ubicacion='Local 1' (antes del fix en pedido_online/index.php).
 *
 * Por defecto solo MUESTRA qué se cambiaría (no toca nada).
 * Ejecutar desde navegador: /migrations/reparar_ubicacion_villa_elisa.php
 * Para aplicar de verdad:   /migrations/reparar_ubicacion_villa_elisa.php?confirmar=1
 */

require_once __DIR__ . '/../admin/config.php';

header('Content-Type: text/plain; charset=utf-8');

$confirmar = isset($_GET['confirmar']) && $_GET['confirmar'] === '1';

echo "🔧 REPARAR UBICACIÓN: pedidos delivery a Villa Elisa mal etiquetados como Local 1\n";
echo str_repeat('=', 70) . "\n\n";

try {
    $pdo = getConnection();

    // Mismo patrón que compone pedido_online/index.php: "{calle} {numero}, {localidad} (entre {entrecalles})"
    $stmt = $pdo->query("
        SELECT id, nombre, apellido, telefono, direccion, created_at
        FROM pedidos
        WHERE ubicacion = 'Local 1'
          AND modalidad = 'Delivery'
          AND direccion LIKE '%, Villa Elisa (%'
        ORDER BY id ASC
    ");
    $candidatos = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $total = count($candidatos);

    if ($total === 0) {
        echo "✅ No se encontró ningún pedido para corregir. Nada para hacer.\n";
        exit;
    }

    echo "Se encontraron $total pedido(s) marcados como 'Local 1' cuya dirección dice Villa Elisa:\n\n";
    printf("%-5s | %-25s | %-14s | %-19s | %s\n", "ID", "Cliente", "Teléfono", "Creado", "Dirección");
    echo str_repeat('-', 100) . "\n";
    foreach ($candidatos as $p) {
        printf(
            "%-5s | %-25s | %-14s | %-19s | %s\n",
            $p['id'],
            mb_substr($p['nombre'] . ' ' . $p['apellido'], 0, 25),
            $p['telefono'],
            $p['created_at'],
            $p['direccion']
        );
    }
    echo "\n";

    if (!$confirmar) {
        echo "👀 Esto es solo una VISTA PREVIA. No se modificó nada todavía.\n";
        echo "Si la lista de arriba es correcta, volvé a entrar a esta misma URL agregando ?confirmar=1 para aplicar el cambio.\n";
        exit;
    }

    $ids = array_column($candidatos, 'id');
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $upd = $pdo->prepare("UPDATE pedidos SET ubicacion = 'Villa Elisa' WHERE id IN ($placeholders)");
    $upd->execute($ids);

    echo "✅ Listo: $total pedido(s) pasados de 'Local 1' a 'Villa Elisa'.\n";
    echo "Ya deberían verse en Ver Pedidos filtrando por la sucursal Villa Elisa.\n";

} catch (Exception $e) {
    echo "\n❌ ERROR: " . $e->getMessage() . "\n";
}
