<?php
/**
 * api-impresion/reclamar.php
 * POST { codigo } — la estación dice "este lo agarro yo".
 * UPDATE atómico: si dos estaciones lo piden al mismo tiempo, la base
 * solo le dice que sí a una (WHERE estado = 'pendiente' se vuelve falso
 * apenas la primera lo cambia). Así nunca se imprime el mismo pedido dos veces.
 */

require_once __DIR__ . '/_estacion.php';

$pdo = getConnection();
$estacion = autenticarEstacion($pdo);

$body = json_decode(file_get_contents('php://input'), true) ?? [];
$codigo = trim($body['codigo'] ?? ($_POST['codigo'] ?? ''));

if ($codigo === '') {
    jsonError('Falta código del trabajo');
}

// Claim atómico: solo afecta la fila si sigue "pendiente" y es de esta ubicación.
$stmt = $pdo->prepare("
    UPDATE cola_impresion
    SET estado = 'reservado', estacion_id = ?, reservado_en = NOW()
    WHERE codigo = ? AND ubicacion = ? AND estado = 'pendiente'
");
$stmt->execute([$estacion['id'], $codigo, $estacion['ubicacion']]);

if ($stmt->rowCount() === 0) {
    // No es error: otra estación ya lo tomó, o ya no existe. La app simplemente lo descarta.
    jsonError('Ese trabajo ya no está disponible (probablemente lo tomó otra estación)', 409);
}

// Traer los datos del pedido para armar la comanda
$stmt = $pdo->prepare("
    SELECT ci.codigo, p.*, cf.nombre as cliente_fijo_nombre, cf.apellido as cliente_fijo_apellido
    FROM cola_impresion ci
    JOIN pedidos p ON p.id = ci.pedido_id
    LEFT JOIN clientes_fijos cf ON p.cliente_fijo_id = cf.id
    WHERE ci.codigo = ?
");
$stmt->execute([$codigo]);
$pedido = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$pedido) {
    jsonError('El pedido de este trabajo ya no existe', 410);
}

jsonOk(['pedido' => $pedido]);
