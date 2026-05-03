<?php
// google_sheets_helper.php

define('GOOGLE_SHEETS_URL', 'https://script.google.com/macros/s/AKfycbxJvCApuEzxrbAxHpMuY_l8mw0FQwQGb-p5FlEtnXtDVPdnJL5wti63zXeaCqAKbFwXCg/exec');

/**
 * Para pedidos personalizados extrae el detalle legible de observaciones
 * en lugar de "Personalizado x48 (6 planchas)".
 */
function _sheets_producto($producto, $observaciones) {
    if (stripos($producto, 'personalizado') === false) {
        return $producto;
    }
    $detalle = $observaciones;
    foreach (['--- Info del Sistema ---', '[Datos sabores:'] as $corte) {
        $pos = strpos($detalle, $corte);
        if ($pos !== false) {
            $detalle = substr($detalle, 0, $pos);
        }
    }
    $detalle = trim($detalle);
    return $detalle ?: $producto;
}

function _sheets_curl($payload) {
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

/**
 * Envía los datos de un pedido a Google Sheets.
 * Columnas: ID | Fecha | Hora | Nombre | Apellido | Teléfono | Producto |
 *           Cantidad | Precio | Pago | Modalidad | Ubicación | Estado |
 *           Fecha Entrega | Dirección | Observaciones
 *
 * @param int    $pedido_id
 * @param array  $datos
 * @param string $tipo  'comun' | 'online'
 */
function enviarPedidoASheets($pedido_id, $datos, $tipo = 'comun') {
    $tz = new DateTimeZone('America/Argentina/Buenos_Aires');
    $dt = new DateTime('now', $tz);

    $payload = json_encode([
        'tipo'          => $tipo,
        'id'            => $pedido_id,
        'fecha'         => $dt->format('d/m/Y'),
        'hora'          => $dt->format('H:i'),
        'nombre'        => $datos['nombre']        ?? '',
        'apellido'      => $datos['apellido']       ?? '',
        'telefono'      => $datos['telefono']       ?? '',
        'producto'      => _sheets_producto($datos['producto'] ?? '', $datos['observaciones'] ?? ''),
        'cantidad'      => $datos['cantidad']       ?? '',
        'precio'        => $datos['precio']         ?? '',
        'forma_pago'    => $datos['forma_pago']     ?? '',
        'modalidad'     => $datos['modalidad']      ?? '',
        'ubicacion'     => $datos['ubicacion']      ?? '',
        'estado'        => $datos['estado']         ?? 'Pendiente',
        'fecha_entrega' => $datos['fecha_entrega']  ?? '',
        'direccion'     => $datos['direccion']      ?? '',
        'observaciones' => $datos['observaciones']  ?? '',
    ]);

    _sheets_curl($payload);
}

/**
 * Actualiza el estado de un pedido en Sheets (busca por ID en ambas hojas).
 *
 * @param int    $pedido_id
 * @param string $estado
 */
function actualizarEstadoEnSheets($pedido_id, $estado) {
    $payload = json_encode([
        'action' => 'actualizar_estado',
        'id'     => (int)$pedido_id,
        'estado' => $estado,
    ]);
    _sheets_curl($payload);
}

/**
 * Pinta de rojo las filas eliminadas en Sheets.
 *
 * @param int|array $pedido_ids
 */
function marcarEliminadoEnSheets($pedido_ids) {
    $ids = is_array($pedido_ids) ? array_values($pedido_ids) : [$pedido_ids];
    $payload = json_encode([
        'action' => 'marcar_eliminado',
        'ids'    => $ids,
    ]);
    _sheets_curl($payload);
}
?>
