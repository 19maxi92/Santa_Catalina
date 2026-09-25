<?php
// google_sheets_helper.php

define('GOOGLE_SHEETS_URL', 'https://script.google.com/macros/s/AKfycbyydEGaOItGjHr47sRt0DLW3o3_TERXR_Ro1HRNG6YJ8tWyG0kUGCyIKeG3T47bRsvqEQ/exec');

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

/**
 * Un solo intento de POST al Apps Script.
 * Devuelve true si respondió HTTP 200 sin decir "error: ..." en el cuerpo.
 */
function _sheets_curl_intento($payload) {
    $ch = curl_init(GOOGLE_SHEETS_URL);
    curl_setopt($ch, CURLOPT_POST,           true);
    curl_setopt($ch, CURLOPT_POSTFIELDS,     $payload);
    curl_setopt($ch, CURLOPT_HTTPHEADER,     ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT,        8);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    // Apps Script ya ejecuta doPost() en ESTE POST y responde con un 302 a
    // script.googleusercontent.com/.../echo — esa URL de eco solo acepta GET
    // (confirmado: "allow: HEAD, GET"). Hay que seguir el redirect para leer la
    // respuesta real, pero como GET (default de curl al seguir un 301/302), NUNCA
    // reenviando el POST — si no, ese segundo salto vuelve con 405 y hace pensar
    // que falló el envío entero, aunque el pedido ya se haya guardado en la hoja.
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    $respuesta = curl_exec($ch);
    $curl_error = curl_error($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($curl_error) {
        return ['ok' => false, 'motivo' => "error de conexión ($curl_error)"];
    }
    if ($http_code !== 200) {
        return ['ok' => false, 'motivo' => "HTTP $http_code — respuesta: " . substr((string)$respuesta, 0, 300)];
    }
    if (stripos((string)$respuesta, 'error') !== false) {
        return ['ok' => false, 'motivo' => "Apps Script devolvió error — respuesta: " . substr((string)$respuesta, 0, 300)];
    }
    return ['ok' => true, 'motivo' => null];
}

/**
 * POST al Apps Script con reintentos: cuando muchos pedidos se cargan/actualizan
 * casi al mismo tiempo (ej. el cambio masivo a "Entregado"), Apps Script rechaza
 * algunas ejecuciones por exceso de llamadas simultáneas ("Fallida", sin ningún
 * log — ni siquiera llega a correr el código). Reintentar con una pausa corta
 * alcanza para que la segunda o tercera vuelta sí entre.
 * Nunca rompe el flujo que la llama: si los 3 intentos fallan, solo lo loguea.
 */
function _sheets_curl($payload) {
    // Log incondicional (éxito o falla): hasta ahora un envío "exitoso" no dejaba
    // ningún rastro, así que no había forma de distinguir "nunca se llamó" de
    // "se llamó, Google dijo OK, pero no pasó nada". Se puede sacar una vez que
    // se confirme que el problema real quedó resuelto.
    error_log("google_sheets: enviando — payload: " . substr($payload, 0, 300));

    $intentos = [0, 400000, 1200000]; // microsegundos de espera antes de cada intento (0, 0.4s, 1.2s)
    foreach ($intentos as $i => $espera) {
        if ($espera > 0) usleep($espera);
        $resultado = _sheets_curl_intento($payload);
        error_log("google_sheets: intento #" . ($i + 1) . " — " . ($resultado['ok'] ? 'OK' : $resultado['motivo']));
        if ($resultado['ok']) return;
    }

    // Nunca rompe el flujo que la llama, pero deja rastro: si Google Sheets
    // deja de recibir pedidos (deployment vencido, cuota excedida, etc.) antes
    // esto fallaba en silencio total y nadie se enteraba hasta días después.
    error_log("google_sheets: falló tras " . count($intentos) . " intentos (" . $resultado['motivo'] . ") — payload: " . substr($payload, 0, 300));
}

/**
 * Envía los datos de un pedido a Google Sheets.
 * Columnas: ID | Fecha/Hora | Nombre | Apellido | Teléfono | Dirección |
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
        'fecha_hora'    => $dt->format('d/m/Y H:i'),
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
 *
 * Opcionalmente también actualiza la forma de pago y el precio final: es lo
 * que se define al marcar Entregado (Efectivo / Transferencia), y el precio
 * puede cambiar (descuento por efectivo). Si no se pasan, el Sheet solo
 * cambia la columna Estado, igual que siempre.
 *
 * @param int         $pedido_id
 * @param string      $estado
 * @param string|null $forma_pago  'Efectivo' | 'Transferencia' (col Pago)
 * @param float|null  $precio      precio final (col Precio)
 */
function actualizarEstadoEnSheets($pedido_id, $estado, $forma_pago = null, $precio = null) {
    $datos = [
        'action' => 'actualizar_estado',
        'id'     => (int)$pedido_id,
        'estado' => $estado,
    ];
    if ($forma_pago !== null && $forma_pago !== '') {
        $datos['forma_pago'] = $forma_pago;
    }
    if ($precio !== null && $precio !== '') {
        $datos['precio'] = (float)$precio;
    }
    _sheets_curl(json_encode($datos));
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
