<?php
/**
 * Diagnóstico (solo lectura): muestra TODOS los pedidos para una fecha de
 * entrega puntual, sin importar su ubicación actual — para encontrar dónde
 * quedaron guardados pedidos que "deberían" estar en una sucursal pero no
 * aparecen ahí filtrando.
 *
 * Ejecutar: /migrations/buscar_pedidos_fecha.php?fecha=2026-09-13
 * Si no se pasa "fecha", usa mañana por defecto.
 */

require_once __DIR__ . '/../admin/config.php';

header('Content-Type: text/plain; charset=utf-8');

$fecha = $_GET['fecha'] ?? date('Y-m-d', strtotime('+1 day'));

echo "🔎 PEDIDOS con fecha de entrega: $fecha (o creados hoy sin fecha_entrega)\n";
echo str_repeat('=', 70) . "\n\n";

try {
    $pdo = getConnection();

    $stmt = $pdo->prepare("
        SELECT id, nombre, apellido, telefono, ubicacion, modalidad, direccion,
               observaciones, fecha_entrega, turno_entrega, created_at
        FROM pedidos
        WHERE fecha_entrega = ?
           OR (fecha_entrega IS NULL AND DATE(created_at) = CURDATE())
        ORDER BY id DESC
    ");
    $stmt->execute([$fecha]);
    $filas = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($filas)) {
        echo "No se encontró ningún pedido con fecha_entrega = $fecha (ni de hoy sin fecha).\n";
        exit;
    }

    echo "Se encontraron " . count($filas) . " pedido(s):\n\n";

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
            echo "  modalidad: {$p['modalidad']}   turno: {$p['turno_entrega']}   creado: {$p['created_at']}\n";
            echo "  direccion: " . ($p['direccion'] ?: '(vacío — es retiro)') . "\n";
            $obs = str_replace("\n", ' / ', $p['observaciones'] ?: '');
            echo "  observ.:   " . mb_substr($obs, 0, 150) . "\n";
        }
        echo "\n";
    }

} catch (Exception $e) {
    echo "\n❌ ERROR: " . $e->getMessage() . "\n";
}
