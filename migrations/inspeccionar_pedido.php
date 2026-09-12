<?php
/**
 * Diagnóstico (solo lectura): inspecciona en detalle UN pedido puntual,
 * mostrando el valor crudo (hex) de ubicacion y modalidad, para confirmar
 * si realmente quedaron vacíos o si tienen algún carácter invisible.
 *
 * Ejecutar: /migrations/inspeccionar_pedido.php?id=21717
 */

require_once __DIR__ . '/../admin/config.php';

header('Content-Type: text/plain; charset=utf-8');

$id = (int)($_GET['id'] ?? 0);
if (!$id) {
    echo "Uso: ?id=NUMERO_DE_PEDIDO\n";
    exit;
}

try {
    $pdo = getConnection();
    $stmt = $pdo->prepare("SELECT * FROM pedidos WHERE id = ?");
    $stmt->execute([$id]);
    $p = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$p) {
        echo "No se encontró el pedido #$id\n";
        exit;
    }

    echo "🔎 Pedido #$id — todos los campos crudos\n";
    echo str_repeat('=', 70) . "\n\n";

    foreach ($p as $campo => $valor) {
        $es_texto_corto = is_string($valor) && strlen($valor) < 60;
        echo str_pad($campo, 20) . ": ";
        if ($valor === null) {
            echo "NULL\n";
        } elseif ($es_texto_corto) {
            echo "\"$valor\"  (hex: " . bin2hex($valor) . ", longitud: " . strlen($valor) . ")\n";
        } else {
            echo (is_string($valor) ? substr($valor, 0, 80) . '...' : $valor) . "\n";
        }
    }

} catch (Exception $e) {
    echo "\n❌ ERROR: " . $e->getMessage() . "\n";
}
