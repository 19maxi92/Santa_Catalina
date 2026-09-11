<?php
/**
 * api-impresion/error.php
 * POST { codigo, mensaje } — la estación avisa "intenté imprimir esto y falló"
 * (sin papel, impresora apagada, etc). Libera el trabajo para que se pueda
 * reintentar, y guarda cuántas veces falló.
 */

require_once __DIR__ . '/_estacion.php';

$pdo = getConnection();
$estacion = autenticarEstacion($pdo);

$body = json_decode(file_get_contents('php://input'), true) ?? [];
$codigo = trim($body['codigo'] ?? ($_POST['codigo'] ?? ''));
$mensaje = trim($body['mensaje'] ?? ($_POST['mensaje'] ?? 'Error desconocido'));

if ($codigo === '') {
    jsonError('Falta código del trabajo');
}

$stmt = $pdo->prepare("
    SELECT intentos FROM cola_impresion
    WHERE codigo = ? AND estacion_id = ? AND estado = 'reservado'
");
$stmt->execute([$codigo, $estacion['id']]);
$job = $stmt->fetch();

if (!$job) {
    jsonError('Ese trabajo no está reservado por esta estación', 409);
}

$intentos = (int)$job['intentos'] + 1;
// Después de 5 intentos fallidos, se deja en 'error' fijo (no se reintenta más
// solo; alguien tiene que mirarlo a mano, ya que probablemente sea la impresora).
$nuevoEstado = $intentos >= 5 ? 'error' : 'pendiente';

$pdo->prepare("
    UPDATE cola_impresion
    SET estado = ?, intentos = ?, error_mensaje = ?, estacion_id = NULL, reservado_en = NULL
    WHERE codigo = ?
")->execute([$nuevoEstado, $intentos, $mensaje, $codigo]);

jsonOk(['intentos' => $intentos, 'estado' => $nuevoEstado]);
