<?php
// google_sheets_helper.php
// Envía pedidos a Google Sheets automáticamente (sin bloquear el flujo principal)

define('GOOGLE_SHEETS_URL', 'https://script.google.com/macros/s/AKfycbxJvCApuEzxrbAxHpMuY_l8mw0FQwQGb-p5FlEtnXtDVPdnJL5wti63zXeaCqAKbFwXCg/exec');

/**
 * Envía los datos de un pedido a la hoja de Google Sheets correspondiente.
 *
 * @param int    $pedido_id  ID del pedido recién insertado en la DB
 * @param array  $datos      Campos del pedido (ver keys abajo)
 * @param string $tipo       'comun' → hoja pedidos_comunes | 'online' → hoja pedidos_online
 */
function enviarPedidoASheets($pedido_id, $datos, $tipo = 'comun') {
    $payload = json_encode([
        'tipo'         => $tipo,
        'id'           => $pedido_id,
        'fecha'        => date('d/m/Y H:i:s'),
        'nombre'       => $datos['nombre']       ?? '',
        'apellido'     => $datos['apellido']     ?? '',
        'telefono'     => $datos['telefono']     ?? '',
        'producto'     => $datos['producto']     ?? '',
        'cantidad'     => $datos['cantidad']     ?? '',
        'precio'       => $datos['precio']       ?? '',
        'forma_pago'   => $datos['forma_pago']   ?? '',
        'modalidad'    => $datos['modalidad']    ?? '',
        'ubicacion'    => $datos['ubicacion']    ?? '',
        'estado'       => $datos['estado']       ?? 'Pendiente',
        'direccion'    => $datos['direccion']    ?? '',
        'observaciones'=> $datos['observaciones']?? '',
    ]);

    $ch = curl_init(GOOGLE_SHEETS_URL);
    curl_setopt($ch, CURLOPT_POST,           true);
    curl_setopt($ch, CURLOPT_POSTFIELDS,     $payload);
    curl_setopt($ch, CURLOPT_HTTPHEADER,     ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT,        5);   // máx 5 s; si falla, el pedido ya está en DB
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_exec($ch);
    curl_close($ch);
    // Silencioso: si falla, el pedido sigue guardado en la base de datos
}

/**
 * Pinta de rojo la fila del pedido en Google Sheets cuando se elimina de la DB.
 * Busca el ID en ambas hojas (pedidos_comunes y pedidos_online).
 *
 * @param int|array $pedido_ids  ID o array de IDs a marcar como eliminados
 */
function marcarEliminadoEnSheets($pedido_ids) {
    $ids = is_array($pedido_ids) ? array_values($pedido_ids) : [$pedido_ids];

    $payload = json_encode([
        'action' => 'marcar_eliminado',
        'ids'    => $ids,
    ]);

    $ch = curl_init(GOOGLE_SHEETS_URL);
    curl_setopt($ch, CURLOPT_POST,           true);
    curl_setopt($ch, CURLOPT_POSTFIELDS,     $payload);
    curl_setopt($ch, CURLOPT_HTTPHEADER,     ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT,        5);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_exec($ch);
    curl_close($ch);
}
?>
