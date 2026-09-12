<?php
/**
 * Reparación puntual de los 15 pedidos que quedaron con ubicacion='' y
 * modalidad='' el 12/09/2026 por la tarde (bug de Pedido Express).
 * Confirmado con el dueño del negocio: los 15 son pedidos de RETIRO en
 * Villa Elisa (Griselda Alzogaray, VILLA ELISA x6, Marcos Da Silva,
 * Ana Karina Garcia x2, Damian Carrizo, Monica Gauna, Silvia Montero x2,
 * Margarita).
 *
 * A propósito se repara por ID exacto (no por texto/heurística), para
 * evitar falsos positivos como el que se detectó antes con un cliente
 * cuyo apellido era "Villa Elisa" sin relación con la sucursal.
 *
 * Por defecto solo MUESTRA los pedidos (no toca nada).
 * Ejecutar: /migrations/reparar_pedidos_villa_elisa_12_09.php
 * Para aplicar:  /migrations/reparar_pedidos_villa_elisa_12_09.php?confirmar=1
 */

require_once __DIR__ . '/../admin/config.php';

header('Content-Type: text/plain; charset=utf-8');

$confirmar = isset($_GET['confirmar']) && $_GET['confirmar'] === '1';

$IDS = [21714, 21713, 21712, 21711, 21710, 21709, 21708, 21705, 21701, 21700, 21692, 21691, 21690, 21689, 21688];

echo "🔧 REPARAR PEDIDOS VILLA ELISA 12/09 (ubicación y modalidad en blanco)\n";
echo str_repeat('=', 70) . "\n\n";

try {
    $pdo = getConnection();

    $ph = implode(',', array_fill(0, count($IDS), '?'));
    $stmt = $pdo->prepare("SELECT id, nombre, apellido, ubicacion, modalidad, fecha_entrega FROM pedidos WHERE id IN ($ph) ORDER BY id");
    $stmt->execute($IDS);
    $filas = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (count($filas) !== count($IDS)) {
        $encontrados = array_column($filas, 'id');
        $faltantes = array_diff($IDS, $encontrados);
        echo "⚠️ Atención: no se encontraron estos IDs (puede que ya se hayan arreglado o borrado): " . implode(', ', $faltantes) . "\n\n";
    }

    $a_reparar = [];
    foreach ($filas as $p) {
        $ya_ok = ($p['ubicacion'] === 'Villa Elisa' && $p['modalidad'] === 'Retiro');
        echo "#{$p['id']} — {$p['nombre']} {$p['apellido']} — entrega: {$p['fecha_entrega']} — ";
        echo "ubicacion actual: \"" . $p['ubicacion'] . "\" — modalidad actual: \"" . $p['modalidad'] . "\"";
        echo $ya_ok ? " (ya está OK, se omite)\n" : " → se pasa a ubicacion='Villa Elisa', modalidad='Retiro'\n";
        if (!$ya_ok) {
            $a_reparar[] = $p['id'];
        }
    }

    echo "\n";

    if (empty($a_reparar)) {
        echo "✅ No hay nada para reparar, todos ya están correctos.\n";
        exit;
    }

    if (!$confirmar) {
        echo "👀 VISTA PREVIA. No se modificó nada todavía.\n";
        echo "Se van a reparar " . count($a_reparar) . " pedido(s): " . implode(', ', $a_reparar) . "\n";
        echo "Si está bien, volvé a esta misma URL agregando ?confirmar=1\n";
        exit;
    }

    $ph2 = implode(',', array_fill(0, count($a_reparar), '?'));
    $upd = $pdo->prepare("UPDATE pedidos SET ubicacion = 'Villa Elisa', modalidad = 'Retiro' WHERE id IN ($ph2)");
    $upd->execute($a_reparar);

    echo "✅ Listo: se repararon " . $upd->rowCount() . " pedido(s): " . implode(', ', $a_reparar) . "\n";

} catch (Exception $e) {
    echo "\n❌ ERROR: " . $e->getMessage() . "\n";
}
