<?php
/**
 * API para reparar fechas - Versión silenciosa para botón Sync
 */

require_once __DIR__ . '/../config.php';

// Retornar JSON
header('Content-Type: application/json');

try {
    $pdo = getConnection();

    // Re-calcular TODAS las fechas usando MySQL
    $pdo->exec("
        UPDATE pedidos
        SET fecha_display = DATE_FORMAT(
            CONVERT_TZ(created_at, '+00:00', '-03:00'),
            '%d/%m %H:%i'
        )
    ");

    // Contar pedidos actualizados
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM pedidos");
    $total = $stmt->fetch()['total'];

    // Respuesta exitosa
    echo json_encode([
        'success' => true,
        'total' => $total,
        'message' => 'Fechas sincronizadas correctamente'
    ]);

} catch (Exception $e) {
    // Respuesta de error
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
