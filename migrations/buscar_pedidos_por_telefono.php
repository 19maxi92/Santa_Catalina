<?php
/**
 * Diagnóstico (solo lectura): busca TODOS los pedidos históricos de una
 * lista de teléfonos, sin importar la fecha, para ver si las personas
 * afectadas por el bug de Villa Elisa tienen otros pedidos (bien
 * cargados o también rotos) que convenga revisar antes de reparar.
 *
 * Ejecutar: /migrations/buscar_pedidos_por_telefono.php
 */

require_once __DIR__ . '/../admin/config.php';

header('Content-Type: text/plain; charset=utf-8');

// Teléfonos de las 7 personas encontradas en el grupo de ubicación vacía
$telefonos = [
    '2216236961', // Griselda Alzogaray
    '2214182114', // Marcos Da Silva
    '1153151979', // Ana Karina Garcia
    '1149163154', // Damian Carrizo
    '1156538687', // Monica Gauna
    '1124026002', // Silvia Montero
    // Margarita (tia de juani) no tiene teléfono cargado
];

echo "🔎 Todos los pedidos históricos de estos teléfonos\n";
echo str_repeat('=', 70) . "\n\n";

try {
    $pdo = getConnection();

    foreach ($telefonos as $tel) {
        $stmt = $pdo->prepare("
            SELECT id, nombre, apellido, ubicacion, modalidad, estado, fecha_entrega, created_at
            FROM pedidos
            WHERE telefono = ?
            ORDER BY created_at DESC
        ");
        $stmt->execute([$tel]);
        $filas = $stmt->fetchAll(PDO::FETCH_ASSOC);

        echo "📞 $tel — " . count($filas) . " pedido(s)\n";
        foreach ($filas as $p) {
            $marca = (trim($p['ubicacion']) === '' || trim($p['modalidad']) === '') ? '  ⚠️ ROTO' : '';
            echo "   #{$p['id']} — {$p['nombre']} {$p['apellido']} — ubicacion: \"{$p['ubicacion']}\" — modalidad: \"{$p['modalidad']}\" — entrega: {$p['fecha_entrega']} — creado: {$p['created_at']}$marca\n";
        }
        echo "\n";
    }

} catch (Exception $e) {
    echo "\n❌ ERROR: " . $e->getMessage() . "\n";
}
