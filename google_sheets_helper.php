<?php
// google_sheets_helper.php

define('GOOGLE_SHEETS_URL', 'https://script.google.com/macros/s/AKfycbxU7ghYMIwbnGee5ekhc_wzertH_vy3Gz8F7ZnjrmjAv4XeWEXsmjRXo1CSShDGNYMsww/exec');

/**
 * Devuelve el texto para la columna Producto en Sheets.
 * - Admin personalizado ("Personalizado x48"): extrae el detalle de sabores de observaciones.
 * - Online elegidos ("N Surtidos Elegidos"): extrae la línea "Sabores: ..." de observaciones.
 * - Cualquier otro pedido: devuelve el nombre del producto tal cual.
 */
function _sheets_producto($producto, $observaciones) {
    // Admin personalizado
    if (stripos($producto, 'personalizado') !== false) {
        $detalle = $observaciones;
        foreach (['--- Info del Sistema ---', '[Datos sabores:'] as $corte) {
            $pos = strpos($detalle, $corte);
            if ($pos !== false) $detalle = substr($detalle, 0, $pos);
        }
        $detalle = trim($detalle);
        return $detalle ?: $producto;
    }

    // Online elegidos: observaciones contiene "Sabores: 8x Jamón, 16x Surtido..."
    if (stripos($producto, 'elegidos') !== false || stripos($producto, 'personalizado') !== false) {
        if (preg_match('/Sabores:\s*(.+?)(?:\n|\[|$)/s', $observaciones, $m)) {
            return $producto . ' | Sabores: ' . trim($m[1]);
        }
    }

    return $producto;
}

/**
 * Limpia las observaciones antes de enviar a Sheets:
 * elimina el bloque JSON de sabores [Datos sabores: ...] que no es legible.
 */
function _sheets_observaciones($observaciones) {
    // Quitar "[Datos sabores: {...}]" — es JSON crudo, ya están listados arriba
    $obs = preg_replace('/\[Datos sabores:.*?\]/s', '', $observaciones);
    return trim($obs);
}

/**
 * Formatea fecha_entrega (Y-m-d o d/m/Y) a d/m/Y para que Sheets no la auto-convierta.
 */
function _sheets_fecha_entrega($fecha) {
    if (empty($fecha)) return '';
    // Si viene como Y-m-d convertimos a d/m/Y
    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha)) {
        $dt = DateTime::createFromFormat('Y-m-d', $fecha);
        return $dt ? $dt->format('d/m/Y') : $fecha;
    }
    return $fecha;
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
 * Columnas: ID | Fecha | Hora | Nombre | Apellido | Teléfono | Dirección |
 *           Producto | Cantidad | Precio | Pago | Modalidad | Ubicación |
 *           Estado | Fecha Entrega | Observaciones
 *
 * @param int    $pedido_id
 * @param array  $datos
 * @param string $tipo  'comun' | 'online'
 */
function enviarPedidoASheets($pedido_id, $datos, $tipo = 'comun') {
    $tz = new DateTimeZone('America/Argentina/Buenos_Aires');
    $dt = new DateTime('now', $tz);

    $obs = $datos['observaciones'] ?? '';

    $payload = json_encode([
        'tipo'          => $tipo,
        'id'            => $pedido_id,
        'fecha'         => $dt->format('d/m/Y'),
        'hora'          => $dt->format('H:i'),
        'nombre'        => $datos['nombre']      ?? '',
        'apellido'      => $datos['apellido']    ?? '',
        'telefono'      => $datos['telefono']    ?? '',
        'direccion'     => $datos['direccion']   ?? '',
        'producto'      => _sheets_producto($datos['producto'] ?? '', $obs),
        'cantidad'      => $datos['cantidad']    ?? '',
        'precio'        => $datos['precio']      ?? '',
        'forma_pago'    => $datos['forma_pago']  ?? '',
        'modalidad'     => $datos['modalidad']   ?? '',
        'ubicacion'     => $datos['ubicacion']   ?? '',
        'estado'        => $datos['estado']      ?? 'Pendiente',
        'fecha_entrega' => _sheets_fecha_entrega($datos['fecha_entrega'] ?? ''),
        'observaciones' => _sheets_observaciones($obs),
    ]);

    _sheets_curl($payload);
}

/**
 * Actualiza el estado de un pedido en Sheets (busca por ID en ambas hojas).
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
