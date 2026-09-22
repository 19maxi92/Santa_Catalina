<?php
/**
 * migrations/diagnostico_sheets.php
 * Diagnóstico de solo lectura (no modifica nada en la base): dispara un POST
 * de prueba al Apps Script tal cual lo hace la app real, y muestra la
 * respuesta CRUDA en pantalla — sin depender de ningún log.
 *
 * Solo para admin logueado.
 *
 * Uso:
 *   /migrations/diagnostico_sheets.php              → payload de prueba sintético
 *   /migrations/diagnostico_sheets.php?pedido=23184  → reenvía un pedido real (por ID) tal cual está en la base
 */

require_once __DIR__ . '/../admin/config.php';
require_once __DIR__ . '/../google_sheets_helper.php';

if (!isLoggedIn()) {
    http_response_code(403);
    echo "Hay que estar logueado como admin para ver esto.";
    exit;
}

$pdo = getConnection();

$pedido_id_param = $_GET['pedido'] ?? null;
$tipo = 'comun';

if ($pedido_id_param) {
    $stmt = $pdo->prepare("SELECT * FROM pedidos WHERE id = ?");
    $stmt->execute([(int)$pedido_id_param]);
    $pedido = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$pedido) {
        die("No existe el pedido #$pedido_id_param");
    }
    $pedido_id = $pedido['id'];
    $datos = [
        'nombre'        => $pedido['nombre'],
        'apellido'      => $pedido['apellido'],
        'telefono'      => $pedido['telefono'],
        'direccion'     => $pedido['direccion'],
        'producto'      => $pedido['producto'],
        'cantidad'      => $pedido['cantidad'],
        'precio'        => $pedido['precio'],
        'forma_pago'    => $pedido['forma_pago'],
        'modalidad'     => $pedido['modalidad'],
        'ubicacion'     => $pedido['ubicacion'],
        'estado'        => $pedido['estado'],
        'observaciones' => $pedido['observaciones'],
        'fecha_entrega' => $pedido['fecha_entrega'],
    ];
} else {
    $pedido_id = 'TEST-' . time();
    $datos = [
        'nombre'        => 'DIAGNOSTICO',
        'apellido'      => 'BORRAR',
        'telefono'      => '000',
        'direccion'     => '',
        'producto'      => 'Fila de prueba — se puede borrar',
        'cantidad'      => 1,
        'precio'        => 1,
        'forma_pago'    => 'Efectivo',
        'modalidad'     => 'Retiro',
        'ubicacion'     => 'Local 1',
        'estado'        => 'Pendiente',
        'observaciones' => 'Generado por diagnostico_sheets.php',
        'fecha_entrega' => '',
    ];
}

$tz = new DateTimeZone('America/Argentina/Buenos_Aires');
$dt = new DateTime('now', $tz);
$payload = json_encode([
    'tipo'          => $tipo,
    'id'            => $pedido_id,
    'fecha_hora'    => $dt->format('d/m/Y H:i'),
    'nombre'        => $datos['nombre'],
    'apellido'      => $datos['apellido'],
    'telefono'      => $datos['telefono'],
    'direccion'     => $datos['direccion'],
    'producto'      => $datos['producto'],
    'cantidad'      => $datos['cantidad'],
    'precio'        => $datos['precio'],
    'forma_pago'    => $datos['forma_pago'],
    'modalidad'     => $datos['modalidad'],
    'ubicacion'     => $datos['ubicacion'],
    'estado'        => $datos['estado'],
    'fecha_entrega' => $datos['fecha_entrega'],
    'observaciones' => $datos['observaciones'],
]);

// POST directo, sin reintentos ni nada — la versión cruda, para ver exactamente qué contesta Google ahora mismo.
$inicio = microtime(true);
$ch = curl_init(GOOGLE_SHEETS_URL);
curl_setopt($ch, CURLOPT_POST,           true);
curl_setopt($ch, CURLOPT_POSTFIELDS,     $payload);
curl_setopt($ch, CURLOPT_HTTPHEADER,     ['Content-Type: application/json']);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HEADER,         true);
curl_setopt($ch, CURLOPT_TIMEOUT,        20);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
// Seguir el redirect de Apps Script como GET (no reenviar el POST): el doPost()
// ya se ejecutó en el primer request, este segundo salto es solo para leer la
// respuesta y la URL de eco de Google solo acepta GET/HEAD.
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
curl_setopt($ch, CURLINFO_HEADER_OUT,    true);
$respuesta_completa = curl_exec($ch);
$duracion = round(microtime(true) - $inicio, 2);
$curl_error = curl_error($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$header_size = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
$request_headers = curl_getinfo($ch, CURLINFO_HEADER_OUT);
$redirect_count = curl_getinfo($ch, CURLINFO_REDIRECT_COUNT);
curl_close($ch);

$headers_respuesta = $respuesta_completa !== false ? substr($respuesta_completa, 0, $header_size) : '';
$body_respuesta     = $respuesta_completa !== false ? substr($respuesta_completa, $header_size) : '';

header('Content-Type: text/plain; charset=utf-8');

echo "=== DIAGNÓSTICO ENVÍO A GOOGLE SHEETS ===\n";
echo "Hora: " . $dt->format('d/m/Y H:i:s') . "\n";
echo "URL: " . GOOGLE_SHEETS_URL . "\n";
echo "Pedido: #$pedido_id" . ($pedido_id_param ? " (reenviado desde la base)" : " (sintético de prueba)") . "\n";
echo "Duración del request: {$duracion}s\n";
echo "Redirects seguidos: $redirect_count\n";
echo str_repeat('-', 70) . "\n";
echo "PAYLOAD ENVIADO:\n$payload\n";
echo str_repeat('-', 70) . "\n";

if ($curl_error) {
    echo "❌ ERROR DE CURL (no llegó a conectar): $curl_error\n";
} else {
    echo "HTTP CODE: $http_code\n";
    echo "HEADERS DE RESPUESTA:\n$headers_respuesta\n";
    echo "BODY DE RESPUESTA:\n$body_respuesta\n";
    echo str_repeat('-', 70) . "\n";
    if ($http_code === 200 && stripos($body_respuesta, 'error') === false && trim($body_respuesta) !== '') {
        echo "✅ Google respondió OK. Si aun así no aparece la fila en el Sheet, el problema\n";
        echo "   está del lado de Apps Script (permisos, SHEET_ID, o el propio código doPost),\n";
        echo "   no en el envío desde PHP.\n";
    } elseif (trim($body_respuesta) === '') {
        echo "⚠️ Respuesta vacía — probable que el request no haya llegado a ejecutar doPost()\n";
        echo "   (revisar 'Quién tiene acceso' del deployment: tiene que ser 'Cualquier usuario',\n";
        echo "   no 'Cualquier usuario de [tu organización]' ni 'Solo yo').\n";
    } else {
        echo "❌ Algo falló — mirar el HTTP code y el body de arriba para el motivo exacto.\n";
    }
}

if ($pedido_id_param) {
    echo "\nEste pedido se reenvió con datos reales de la base (#$pedido_id_param) — si\n";
    echo "generó una fila nueva en el Sheet, puede quedar duplicada; se puede borrar a mano.\n";
} else {
    echo "\nSi este test generó una fila 'DIAGNOSTICO BORRAR' en el Sheet, se puede borrar a mano.\n";
}
