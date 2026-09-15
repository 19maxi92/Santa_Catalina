<?php
/**
 * api-impresion/pendientes.php
 * GET — la app de la estación pregunta "¿hay algo para imprimir?"
 * Solo devuelve trabajos de SU ubicación, creados después de su primera conexión
 * (nunca arrastra pedidos viejos de antes de que la estación existiera).
 */

require_once __DIR__ . '/_estacion.php';

$pdo = getConnection();
$estacion = autenticarEstacion($pdo);

// Local 1 también arma los pedidos de reparto que se cargan como Fábrica
// (mismo criterio que ubicacionesVisibles() en Ver Pedidos).
$ubicaciones = ubicacionesVisibles($estacion['ubicacion']);
$ph = implode(',', array_fill(0, count($ubicaciones), '?'));

$stmt = $pdo->prepare("
    SELECT ci.id, ci.pedido_id, ci.codigo, ci.accion, ci.created_at
    FROM cola_impresion ci
    WHERE ci.ubicacion IN ($ph)
      AND ci.estado = 'pendiente'
      AND ci.created_at >= ?
    ORDER BY ci.created_at ASC
    LIMIT 20
");
$stmt->execute(array_merge($ubicaciones, [$estacion['primera_conexion']]));
$pendientes = $stmt->fetchAll(PDO::FETCH_ASSOC);

jsonOk(['pendientes' => $pendientes]);
