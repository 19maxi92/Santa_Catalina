<?php
/**
 * api-impresion/_estacion.php
 * Helper compartido por los endpoints de la cola de impresión.
 * No lo llama nadie directo (el "_" es a propósito).
 */

require_once __DIR__ . '/../admin/config.php';

header('Content-Type: application/json; charset=utf-8');

function jsonError($mensaje, $code = 400) {
    http_response_code($code);
    echo json_encode(['success' => false, 'error' => $mensaje]);
    exit;
}

function jsonOk($data = []) {
    echo json_encode(array_merge(['success' => true], $data));
    exit;
}

/**
 * Autentica la estación por token (header X-Estacion-Token o ?token=).
 * Devuelve la fila de estaciones_impresion o corta con 401.
 */
function autenticarEstacion(PDO $pdo) {
    $token = $_SERVER['HTTP_X_ESTACION_TOKEN'] ?? ($_GET['token'] ?? $_POST['token'] ?? '');
    $token = trim($token);

    if ($token === '') {
        jsonError('Falta token de estación', 401);
    }

    $stmt = $pdo->prepare("SELECT * FROM estaciones_impresion WHERE token = ? AND activa = 1");
    $stmt->execute([$token]);
    $estacion = $stmt->fetch();

    if (!$estacion) {
        jsonError('Token inválido o estación desactivada', 401);
    }

    // Heartbeat: registra que esta estación sigue viva.
    // Primera conexión: fija el cursor "a partir de ahora" (nunca mira pedidos anteriores).
    $pdo->prepare("
        UPDATE estaciones_impresion
        SET ultima_conexion = NOW(),
            primera_conexion = COALESCE(primera_conexion, NOW())
        WHERE id = ?
    ")->execute([$estacion['id']]);

    if ($estacion['primera_conexion'] === null) {
        $estacion['primera_conexion'] = date('Y-m-d H:i:s');
    }

    return $estacion;
}
