<?php
/**
 * API: devuelve cupos disponibles por turno para una fecha específica.
 * Calcula dinámicamente: max_pedidos - pedidos reales para esa fecha.
 * GET /pedido_online/get_cupos.php?fecha=2026-03-27
 */
require_once '../admin/config.php';
header('Content-Type: application/json');
header('Cache-Control: no-store');

$fecha = $_GET['fecha'] ?? '';
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha)) {
    http_response_code(400);
    echo json_encode(['error' => 'fecha inválida']);
    exit;
}

try {
    $pdo = getConnection();

    $stmt = $pdo->query("
        SELECT turno, max_pedidos, activo, hora_inicio, minutos_antes_corte
        FROM config_pedidos_online
        WHERE activo = 1
        ORDER BY FIELD(turno, 'Mañana', 'Siesta', 'Tarde')
    ");
    $configs = $stmt->fetchAll();

    $resultado = [];
    foreach ($configs as $c) {
        // Contar pedidos online confirmados para ese turno+fecha
        $cnt = $pdo->prepare("
            SELECT COUNT(*) FROM pedidos
            WHERE turno_entrega = ?
              AND DATE(fecha_entrega) = ?
              AND estado NOT IN ('Cancelado')
              AND observaciones LIKE '%PEDIDO ONLINE%'
        ");
        $cnt->execute([$c['turno'], $fecha]);
        $ocupados = (int)$cnt->fetchColumn();

        $resultado[] = [
            'turno'               => $c['turno'],
            'hora_inicio'         => substr($c['hora_inicio'], 0, 5),
            'minutos_antes_corte' => (int)($c['minutos_antes_corte'] ?? 30),
            'stock_actual'        => max(0, $c['max_pedidos'] - $ocupados),
            'activo'              => (bool)$c['activo'],
        ];
    }

    echo json_encode($resultado);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
