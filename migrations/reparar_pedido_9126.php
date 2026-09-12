<?php
/**
 * Reparación puntual de UN pedido específico (#9126): quedó cargado como
 * ubicacion='Fábrica' (convención vieja de "Fábrica = reparto") pero su
 * dirección es un delivery a Villa Elisa, que ahora es su propia sucursal.
 *
 * A propósito NO se hace por búsqueda de texto: se detectó un falso
 * positivo (pedido #3583, cuya clienta se apellida "Villa Elisa" — no
 * tiene nada que ver con la sucursal). Por eso este script solo toca el
 * ID exacto que se confirmó a mano.
 *
 * Por defecto solo MUESTRA el pedido (no toca nada).
 * Ejecutar: /migrations/reparar_pedido_9126.php
 * Para aplicar:  /migrations/reparar_pedido_9126.php?confirmar=1
 */

require_once __DIR__ . '/../admin/config.php';

header('Content-Type: text/plain; charset=utf-8');

$confirmar = isset($_GET['confirmar']) && $_GET['confirmar'] === '1';
$PEDIDO_ID = 9126;

echo "🔧 REPARAR PEDIDO #$PEDIDO_ID (Fábrica → Villa Elisa)\n";
echo str_repeat('=', 60) . "\n\n";

try {
    $pdo = getConnection();

    $stmt = $pdo->prepare("SELECT id, nombre, apellido, telefono, ubicacion, modalidad, direccion, created_at FROM pedidos WHERE id = ?");
    $stmt->execute([$PEDIDO_ID]);
    $p = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$p) {
        echo "❌ No se encontró el pedido #$PEDIDO_ID. No se hizo nada.\n";
        exit;
    }

    echo "Pedido encontrado:\n";
    echo "  Cliente:    {$p['nombre']} {$p['apellido']} ({$p['telefono']})\n";
    echo "  Creado:     {$p['created_at']}\n";
    echo "  Ubicación actual: {$p['ubicacion']}\n";
    echo "  Modalidad:  {$p['modalidad']}\n";
    echo "  Dirección:  {$p['direccion']}\n\n";

    if ($p['ubicacion'] === 'Villa Elisa') {
        echo "✅ Ya está en 'Villa Elisa'. No hace falta hacer nada.\n";
        exit;
    }

    if (!$confirmar) {
        echo "👀 VISTA PREVIA. No se modificó nada todavía.\n";
        echo "Si los datos de arriba son correctos, volvé a esta misma URL agregando ?confirmar=1\n";
        exit;
    }

    $pdo->prepare("UPDATE pedidos SET ubicacion = 'Villa Elisa' WHERE id = ?")->execute([$PEDIDO_ID]);
    echo "✅ Listo: el pedido #$PEDIDO_ID ahora tiene ubicacion = 'Villa Elisa'.\n";

} catch (Exception $e) {
    echo "\n❌ ERROR: " . $e->getMessage() . "\n";
}
