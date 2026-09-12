<?php
/**
 * api-impresion/confirmar.php
 * POST { codigo } — la estación avisa "esto ya salió por la impresora".
 * Refleja el resultado en la tabla `pedidos` para que se vea en Ver Pedidos
 * (el mismo campo `impreso` que ya usa el botón manual de siempre).
 */

require_once __DIR__ . '/_estacion.php';

$pdo = getConnection();
$estacion = autenticarEstacion($pdo);

$body = json_decode(file_get_contents('php://input'), true) ?? [];
$codigo = trim($body['codigo'] ?? ($_POST['codigo'] ?? ''));

if ($codigo === '') {
    jsonError('Falta código del trabajo');
}

// Solo confirma un trabajo que ESTA estación reservó (evita que una estación
// confirme el trabajo de otra por un token robado/copiado)
$stmt = $pdo->prepare("
    SELECT pedido_id FROM cola_impresion
    WHERE codigo = ? AND estacion_id = ? AND estado = 'reservado'
");
$stmt->execute([$codigo, $estacion['id']]);
$job = $stmt->fetch();

if (!$job) {
    jsonError('Ese trabajo no está reservado por esta estación', 409);
}

$pdo->beginTransaction();
try {
    $pdo->prepare("
        UPDATE cola_impresion SET estado = 'impreso', impreso_en = NOW()
        WHERE codigo = ?
    ")->execute([$codigo]);

    $pdo->prepare("UPDATE pedidos SET impreso = 1 WHERE id = ?")->execute([$job['pedido_id']]);

    $pdo->commit();
} catch (Exception $e) {
    $pdo->rollBack();
    jsonError('Error guardando la confirmación: ' . $e->getMessage(), 500);
}

jsonOk();
