<?php
/**
 * Diagnóstico (solo lectura): muestra TODOS los pedidos en un rango de
 * fecha de entrega, sin importar su ubicación actual — para encontrar dónde
 * quedaron guardados pedidos que "deberían" estar en una sucursal pero no
 * aparecen ahí filtrando. Usa el mismo criterio de fecha que Ver Pedidos.
 *
 * Ejecutar: /migrations/buscar_pedidos_fecha.php?desde=2026-09-11&hasta=2026-09-14
 * Sin parámetros, usa de hoy a +3 días (mismo rango que se vio en Ver Pedidos).
 */

require_once __DIR__ . '/../admin/config.php';

header('Content-Type: text/plain; charset=utf-8');

$desde = $_GET['desde'] ?? date('Y-m-d');
$hasta = $_GET['hasta'] ?? date('Y-m-d', strtotime('+3 day'));

echo "🔎 PEDIDOS con fecha de entrega entre $desde y $hasta\n";
echo "   (mismo criterio que usa Ver Pedidos: fecha_entrega, o created_at si no tiene)\n";
echo str_repeat('=', 70) . "\n\n";

try {
    $pdo = getConnection();

    $stmt = $pdo->prepare("
        SELECT id, nombre, apellido, telefono, ubicacion, modalidad, direccion,
               observaciones, fecha_entrega, turno_entrega, created_at
        FROM pedidos
        WHERE DATE(COALESCE(fecha_entrega, DATE(created_at))) BETWEEN ? AND ?
        ORDER BY ubicacion, id DESC
    ");
    $stmt->execute([$desde, $hasta]);
    $filas = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($filas)) {
        echo "No se encontró ningún pedido en ese rango de fechas.\n";
        exit;
    }

    echo "Se encontraron " . count($filas) . " pedido(s) en total:\n\n";

    // Agrupar por ubicación para ver de un vistazo dónde quedó cada uno
    $por_ubicacion = [];
    foreach ($filas as $p) {
        $por_ubicacion[$p['ubicacion']][] = $p;
    }

    foreach ($por_ubicacion as $ubicacion => $pedidos) {
        echo "════ UBICACIÓN: $ubicacion (" . count($pedidos) . ") ════\n";
        foreach ($pedidos as $p) {
            echo str_repeat('-', 70) . "\n";
            echo "ID #{$p['id']} — {$p['nombre']} {$p['apellido']} — {$p['telefono']}\n";
            echo "  modalidad: {$p['modalidad']}   turno: {$p['turno_entrega']}   entrega: {$p['fecha_entrega']}   creado: {$p['created_at']}\n";
            echo "  direccion: " . ($p['direccion'] ?: '(vacío — es retiro)') . "\n";
            $obs = str_replace("\n", ' / ', $p['observaciones'] ?: '');
            echo "  observ.:   " . mb_substr($obs, 0, 150) . "\n";
        }
        echo "\n";
    }

} catch (Exception $e) {
    echo "\n❌ ERROR: " . $e->getMessage() . "\n";
}
