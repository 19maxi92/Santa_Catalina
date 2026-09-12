<?php
/**
 * Diagnóstico (solo lectura): inspecciona en detalle los pedidos cuya
 * ubicacion quedó vacía/rara (el grupo "════ UBICACIÓN:  ════" que apareció
 * en buscar_pedidos_fecha.php), para confirmar la causa exacta.
 * Muestra el valor de ubicacion en hexadecimal (para detectar espacios
 * invisibles) y otros campos que puedan dar una pista (modalidad,
 * cliente_fijo_id, forma_pago, estado).
 *
 * Ejecutar: /migrations/inspeccionar_ubicacion_vacia.php?desde=2026-09-11&hasta=2026-09-14
 */

require_once __DIR__ . '/../admin/config.php';

header('Content-Type: text/plain; charset=utf-8');

$desde = $_GET['desde'] ?? date('Y-m-d');
$hasta = $_GET['hasta'] ?? date('Y-m-d', strtotime('+3 day'));

echo "🔎 Pedidos con ubicación vacía/rara entre $desde y $hasta\n";
echo str_repeat('=', 70) . "\n\n";

try {
    $pdo = getConnection();

    $stmt = $pdo->prepare("
        SELECT id, nombre, apellido, telefono, ubicacion, modalidad, forma_pago,
               estado, cliente_fijo_id, precio, fecha_entrega, turno_entrega, created_at
        FROM pedidos
        WHERE DATE(COALESCE(fecha_entrega, DATE(created_at))) BETWEEN ? AND ?
          AND (ubicacion IS NULL OR TRIM(ubicacion) = '' OR ubicacion NOT IN ('Local 1', 'Fábrica', 'Villa Elisa'))
        ORDER BY id DESC
    ");
    $stmt->execute([$desde, $hasta]);
    $filas = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($filas)) {
        echo "No se encontró ningún pedido con ubicación vacía/rara en ese rango.\n";
        exit;
    }

    echo "Se encontraron " . count($filas) . " pedido(s):\n\n";

    foreach ($filas as $p) {
        echo str_repeat('-', 70) . "\n";
        echo "ID #{$p['id']} — {$p['nombre']} {$p['apellido']} — {$p['telefono']}\n";
        $ubic_raw = $p['ubicacion'];
        $ubic_hex = $ubic_raw === null ? 'NULL' : bin2hex($ubic_raw);
        $ubic_len = $ubic_raw === null ? 0 : strlen($ubic_raw);
        echo "  ubicacion:        \"{$ubic_raw}\"  (hex: {$ubic_hex}, longitud: {$ubic_len})\n";
        echo "  modalidad:        \"{$p['modalidad']}\"\n";
        echo "  forma_pago:       \"{$p['forma_pago']}\"\n";
        echo "  estado:           \"{$p['estado']}\"\n";
        echo "  cliente_fijo_id:  " . ($p['cliente_fijo_id'] ?? 'NULL') . "\n";
        echo "  precio:           {$p['precio']}\n";
        echo "  fecha_entrega:    " . ($p['fecha_entrega'] ?? 'NULL') . "\n";
        echo "  turno_entrega:    " . ($p['turno_entrega'] ?? 'NULL') . "\n";
        echo "  created_at:       {$p['created_at']}\n";
    }
    echo str_repeat('-', 70) . "\n";

} catch (Exception $e) {
    echo "\n❌ ERROR: " . $e->getMessage() . "\n";
}
