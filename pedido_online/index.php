<?php
/**
 * Formulario público de pedidos online - Versión mejorada tipo pedido express
 * Los clientes acceden vía link directo / acceso desde pantalla de inicio (PWA)
 */
require_once '../admin/config.php';

$pdo = getConnection();

// Config estática de turnos (solo hora/corte, sin stock — stock se consulta por AJAX)
$stmt = $pdo->query("
    SELECT turno, hora_inicio, hora_fin, minutos_antes_corte
    FROM config_pedidos_online
    ORDER BY FIELD(turno, 'Mañana', 'Siesta', 'Tarde')
");
$turnos_config_json = json_encode(array_values(array_map(fn($t) => [
    'turno'               => $t['turno'],
    'hora_inicio'         => substr($t['hora_inicio'], 0, 5),
    'hora_fin'            => substr($t['hora_fin'], 0, 5),
    'minutos_antes_corte' => (int)($t['minutos_antes_corte'] ?? 30),
], $stmt->fetchAll())));

// Precios de planchas elegidos
$precios_elegidos_json = json_encode(['comun' => ['ef' => 4200, 'tr' => 4200], 'premium' => ['ef' => 5500, 'tr' => 5500]]);
try {
    $pe = $pdo->query("SELECT tipo, precio_efectivo, precio_transferencia FROM config_precios_elegidos")->fetchAll();
    $tmp = [];
    foreach ($pe as $r) { $tmp[$r['tipo']] = ['ef' => (float)$r['precio_efectivo'], 'tr' => (float)$r['precio_transferencia']]; }
    if (!empty($tmp)) $precios_elegidos_json = json_encode($tmp);
} catch (PDOException $e) {}

// Localidades habilitadas para delivery
$localidades_activas_json = json_encode([]);
try {
    $locs = $pdo->query("SELECT nombre FROM localidades_delivery WHERE activo = 1 ORDER BY orden, nombre")->fetchAll(PDO::FETCH_COLUMN);
    $localidades_activas_json = json_encode(array_values($locs));
} catch (PDOException $e) {}

// Obtener productos activos
$stmt = $pdo->query("SELECT * FROM productos WHERE activo = 1 ORDER BY nombre ASC");
$productos_todos = $stmt->fetchAll();

// Separar productos simples de elegidos
$productos_simples = [];
$precio_elegido_8 = null;
$precio_elegido_16 = null;
$precio_elegido_24 = null;
$precio_elegido_32 = null;
$precio_elegido_40 = null;
$precio_elegido_48 = null;

foreach ($productos_todos as $prod) {
    $nombre_lower = strtolower($prod['nombre']);
    // Los "Surtidos Premium xN" son solo tabla de precios del personalizado, no combos
    if (strpos($nombre_lower, 'surtidos premium') !== false) {
        continue;
    }
    if (strpos($nombre_lower, 'elegido') !== false || strpos($nombre_lower, 'elegidos') !== false) {
        // Es un producto personalizable
        if (strpos($nombre_lower, '8') !== false)  $precio_elegido_8  = $prod;
        if (strpos($nombre_lower, '16') !== false) $precio_elegido_16 = $prod;
        if (strpos($nombre_lower, '24') !== false && strpos($nombre_lower, 'elegido') !== false) $precio_elegido_24 = $prod;
        if (strpos($nombre_lower, '32') !== false) $precio_elegido_32 = $prod;
        if (strpos($nombre_lower, '40') !== false) $precio_elegido_40 = $prod;
        if (strpos($nombre_lower, '48') !== false && strpos($nombre_lower, 'elegido') !== false) $precio_elegido_48 = $prod;
    } else {
        $productos_simples[] = $prod;
    }
}

// Tabla de precios por planchas (Surtidos Premium/Elegidos xN de productos)
// Fallback: valores del menú oficial
$tabla_personalizado = [
    'premium'  => [1=>9000, 2=>18000, 3=>27000, 4=>36000, 5=>45000, 6=>54000],
    'elegidos' => [1=>5400, 2=>10800, 3=>16000, 4=>21400, 5=>26800, 6=>32000],
];
foreach ($productos_todos as $prod) {
    if (preg_match('/^Surtidos (Premium|Elegidos) x(\d+)$/i', trim($prod['nombre']), $m)) {
        $cat = strtolower($m[1]) === 'premium' ? 'premium' : 'elegidos';
        $planchas = (int)$m[2] / 8;
        if ($planchas >= 1 && $planchas == (int)$planchas) {
            $tabla_personalizado[$cat][(int)$planchas] = (int)$prod['precio_transferencia'];
        }
    }
}

// Sabores disponibles — idénticos al pedido express del admin
$sabores_disponibles = [
    // Comunes
    ['id' => 'jamon_queso',     'nombre' => 'Jamón y Queso',    'emoji' => '🧀', 'tipo' => 'comun'],
    ['id' => 'lechuga',         'nombre' => 'Lechuga',           'emoji' => '🥬', 'tipo' => 'comun'],
    ['id' => 'tomate',          'nombre' => 'Tomate',            'emoji' => '🍅', 'tipo' => 'comun'],
    ['id' => 'huevo',           'nombre' => 'Huevo',             'emoji' => '🥚', 'tipo' => 'comun'],
    ['id' => 'choclo',          'nombre' => 'Choclo',            'emoji' => '🌽', 'tipo' => 'comun'],
    ['id' => 'aceitunas',       'nombre' => 'Aceitunas',         'emoji' => '🟢', 'tipo' => 'comun'],
    ['id' => 'zanahoria_queso', 'nombre' => 'Zanahoria y Queso', 'emoji' => '🥕', 'tipo' => 'comun'],
    ['id' => 'zanahoria_huevo', 'nombre' => 'Zanahoria y Huevo', 'emoji' => '🥕', 'tipo' => 'comun'],
    // Premium
    ['id' => 'anana',       'nombre' => 'Ananá',       'emoji' => '🍍', 'tipo' => 'premium'],
    ['id' => 'atun',        'nombre' => 'Atún',        'emoji' => '🐟', 'tipo' => 'premium'],
    ['id' => 'berenjena',   'nombre' => 'Berenjena',   'emoji' => '🍆', 'tipo' => 'premium'],
    ['id' => 'jamon_crudo', 'nombre' => 'Jamón Crudo', 'emoji' => '🥓', 'tipo' => 'premium'],
    ['id' => 'morron',      'nombre' => 'Morrón',      'emoji' => '🌶️', 'tipo' => 'premium'],
    ['id' => 'palmito',     'nombre' => 'Palmito',     'emoji' => '🥗', 'tipo' => 'premium'],
    ['id' => 'panceta',     'nombre' => 'Panceta',     'emoji' => '🥓', 'tipo' => 'premium'],
    ['id' => 'pollo',       'nombre' => 'Pollo',       'emoji' => '🍗', 'tipo' => 'premium'],
    ['id' => 'roquefort',   'nombre' => 'Roquefort',   'emoji' => '🧀', 'tipo' => 'premium'],
    ['id' => 'salame',      'nombre' => 'Salame',      'emoji' => '🥩', 'tipo' => 'premium'],
];

// Procesar formulario
$mensaje = null;
$error = null;
$pedido_confirmado = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $nombre       = trim($_POST['nombre'] ?? '');
        $apellido     = trim($_POST['apellido'] ?? '');
        $telefono     = trim($_POST['telefono'] ?? '');
        $turno        = trim($_POST['turno'] ?? '');
        $forma_pago    = trim($_POST['forma_pago'] ?? '');
        $modalidad     = trim($_POST['modalidad'] ?? 'Retiro');
        $direccion     = trim($_POST['direccion'] ?? '');
        $fecha_pedido  = trim($_POST['fecha_pedido'] ?? '');
        $observaciones = trim($_POST['observaciones'] ?? '');

        // Validaciones
        if (empty($nombre) || empty($apellido) || empty($telefono)) {
            throw new Exception('Por favor completá nombre, apellido y teléfono');
        }
        $tel_digits = preg_replace('/\D/', '', $telefono);
        if (strlen($tel_digits) < 8 || strlen($tel_digits) > 13) {
            throw new Exception('Ingresá un teléfono válido (ej: 221 123-4567 o 11 5981-3546)');
        }
        if (preg_match('/^(.)\1+$/', $tel_digits)) {
            throw new Exception('Ingresá un número de teléfono real');
        }
        if (in_array($tel_digits, ['12345678','123456789','1234567890','0987654321','87654321'])) {
            throw new Exception('Ingresá un número de teléfono real');
        }
        if (empty($turno)) {
            throw new Exception('Por favor seleccioná un turno');
        }
        // Forma de pago: solo informativa (no cambia el precio), se guarda para que quede registrada
        if (!in_array($forma_pago, ['Efectivo', 'Transferencia'])) {
            $forma_pago = 'Transferencia';
        }
        if ($modalidad === 'Delivery' && empty($direccion)) {
            throw new Exception('Si elegís Delivery, ingresá la dirección de entrega');
        }
        if ($modalidad === 'Delivery' && strpos($direccion, 'entre ') === false) {
            throw new Exception('Para delivery, ingresá las calles entre las que está tu domicilio');
        }
        if ($modalidad === 'Delivery' && empty($fecha_pedido)) {
            throw new Exception('Seleccioná la fecha de entrega');
        }

        // Validar que la fecha no sea pasada
        if (!empty($fecha_pedido)) {
            $tz = new DateTimeZone('America/Argentina/Buenos_Aires');
            $hoy = new DateTime('today', $tz);
            try {
                $fecha_dt = new DateTime($fecha_pedido, $tz);
            } catch (Exception $ex) {
                throw new Exception('Fecha de entrega inválida');
            }
            if ($fecha_dt < $hoy) {
                throw new Exception('La fecha de entrega no puede ser en el pasado');
            }
        }

        // Verificar stock del turno para la fecha concreta
        $fecha_entrega = !empty($fecha_pedido) ? $fecha_pedido : date('Y-m-d');
        $dia_semana    = (int)date('w', strtotime($fecha_entrega));

        $stmt = $pdo->prepare("SELECT * FROM config_pedidos_online WHERE turno = ?");
        $stmt->execute([$turno]);
        $config_turno = $stmt->fetch();
        if (!$config_turno) {
            throw new Exception('El turno seleccionado no está disponible');
        }

        // Config por día de semana (cupo independiente para Retiro vs Delivery)
        try { $pdo->exec("ALTER TABLE config_pedidos_online_dias ADD COLUMN max_pedidos_retiro INT NOT NULL DEFAULT 30"); } catch (PDOException $e) {}
        try { $pdo->exec("ALTER TABLE config_pedidos_online_dias ADD COLUMN max_pedidos_delivery INT NOT NULL DEFAULT 30"); } catch (PDOException $e) {}

        $colMax = $modalidad === 'Delivery' ? 'max_pedidos_delivery' : 'max_pedidos_retiro';
        $stmtDia = $pdo->prepare("SELECT max_pedidos, max_pedidos_retiro, max_pedidos_delivery, activo FROM config_pedidos_online_dias WHERE turno = ? AND dia_semana = ?");
        $stmtDia->execute([$turno, $dia_semana]);
        $dayConfig = $stmtDia->fetch();
        $maxPedidos = $dayConfig ? (int)$dayConfig[$colMax] : (int)$config_turno['max_pedidos'];
        $turnoActivo = $dayConfig ? (bool)$dayConfig['activo'] : true;
        if (!$turnoActivo) {
            throw new Exception('El turno no está disponible ese día');
        }

        // Contar ocupados para esa fecha, turno y modalidad (mismo criterio que disponibilidad.php)
        $cntStmt = $pdo->prepare("
            SELECT COUNT(*) FROM pedidos
            WHERE DATE(fecha_entrega) = ?
              AND estado != 'Cancelado'
              AND modalidad = ?
              AND (
                turno_entrega = ?
                OR (turno_entrega IS NULL AND observaciones LIKE ?)
              )
        ");
        $cntStmt->execute([$fecha_entrega, $modalidad, $turno, '%PEDIDO ONLINE%Turno: ' . $turno . '%']);
        $ocupados = (int)$cntStmt->fetchColumn();
        if ($ocupados >= $maxPedidos) {
            throw new Exception('¡Lo sentimos! No hay cupos disponibles para ese turno y fecha. Elegí otro.');
        }

        // Validar corte de horario server-side solo para Delivery (Retiro no tiene corte)
        if ($modalidad === 'Delivery') {
            $tz_ar = new DateTimeZone('America/Argentina/Buenos_Aires');
            $minutos_corte = (int)($config_turno['minutos_antes_corte'] ?? 30);
            $turno_start = new DateTime($fecha_entrega . ' ' . $config_turno['hora_inicio'], $tz_ar);
            $cutoff = clone $turno_start;
            $cutoff->modify("-{$minutos_corte} minutes");
            $now_ar = new DateTime('now', $tz_ar);
            if ($now_ar >= $cutoff) {
                throw new Exception('Ya no se pueden tomar pedidos para ese turno. El plazo de pedido venció.');
            }
        }

        $precio = 0;
        $nombre_producto = '';
        $cantidad_sandwiches = 0;
        $wa_lineas = [];
        $obs_interna = "🌐 PEDIDO ONLINE\nTurno: {$turno}";
        if ($modalidad === 'Delivery' && !empty($fecha_pedido)) {
            $obs_interna .= "\nFecha entrega: " . date('d/m/Y', strtotime($fecha_pedido));
        }

        // Pedido combinado: uno o más ítems, cada uno combo o personalizado, todo en un solo pedido
        $items = json_decode($_POST['pedidos_json'] ?? '[]', true);
        if (!is_array($items) || empty($items)) {
            throw new Exception('Seleccioná al menos un producto para tu pedido');
        }

        $sabores_premium_ids = ['anana','atun','berenjena','jamon_crudo','morron','palmito','panceta','pollo','roquefort','salame'];

        $partes_nombre = [];
        $detalle_personalizados = [];

        foreach ($items as $item) {
            $tipoItem = $item['tipo'] ?? '';

            if ($tipoItem === 'combo') {
                $combo_id  = (int)($item['id'] ?? 0);
                $combo_qty = (int)($item['cantidad'] ?? 0);
                if ($combo_id <= 0 || $combo_qty <= 0) continue;

                $stmt = $pdo->prepare("SELECT * FROM productos WHERE id = ? AND activo = 1");
                $stmt->execute([$combo_id]);
                $prod = $stmt->fetch();
                if (!$prod) continue;

                $precio_unit = ($forma_pago === 'Efectivo')
                    ? (float)$prod['precio_efectivo']
                    : (float)$prod['precio_transferencia'];

                // Sándwiches por caja: primer número que aparece en el nombre del producto (ej: "24 Jamón y Queso" -> 24)
                preg_match('/(\d+)/', $prod['nombre'], $m_unid);
                $unidades_por_caja = (int)($m_unid[1] ?? 1);

                $precio              += $precio_unit * $combo_qty;
                $cantidad_sandwiches += $combo_qty * $unidades_por_caja;
                $partes_nombre[]      = "{$combo_qty}x {$prod['nombre']}";
                $wa_lineas[]          = "{$combo_qty}x {$prod['nombre']}";

            } elseif ($tipoItem === 'personalizado') {
                $elegidos_cantidad = (int)($item['elegidos_cantidad'] ?? 0);
                $sabores           = is_array($item['sabores'] ?? null) ? $item['sabores'] : [];
                $total_sabores     = array_sum($sabores);
                if ($elegidos_cantidad === 0 || $total_sabores === 0) continue;

                $planchas_comun   = 0;
                $planchas_premium = 0;
                foreach ($sabores as $sabor_id => $cant) {
                    if ($cant > 0) {
                        $planchas = (int)($cant / 8);
                        if (in_array($sabor_id, $sabores_premium_ids)) $planchas_premium += $planchas;
                        else                                            $planchas_comun   += $planchas;
                    }
                }

                $precio_item = 0;
                if ($planchas_premium > 0) {
                    $precio_item += $tabla_personalizado['premium'][$planchas_premium]
                        ?? ($planchas_premium * ($tabla_personalizado['premium'][1] ?? 9000));
                }
                if ($planchas_comun > 0) {
                    $precio_item += $tabla_personalizado['elegidos'][$planchas_comun]
                        ?? ($planchas_comun * ($tabla_personalizado['elegidos'][1] ?? 5400));
                }

                // Descuento efectivo: $1.000 cada 3 planchas
                if ($forma_pago === 'Efectivo') {
                    $planchas_totales_item = $planchas_premium + $planchas_comun;
                    $precio_item = max(0, $precio_item - floor($planchas_totales_item / 3) * 1000);
                }

                $precio              += $precio_item;
                $cantidad_sandwiches += $elegidos_cantidad;
                $partes_nombre[]      = "{$elegidos_cantidad} Surtidos Elegidos";
                $wa_lineas[]          = "{$elegidos_cantidad} Surtidos Elegidos";

                $lista_sabores = [];
                foreach ($sabores as $sabor_id => $cant_sabor) {
                    if ($cant_sabor > 0) {
                        $sabor_info = array_values(array_filter($sabores_disponibles, fn($s) => $s['id'] === $sabor_id));
                        if (!empty($sabor_info)) {
                            $pl = (int)($cant_sabor / 8);
                            $lista_sabores[] = "{$cant_sabor}x {$sabor_info[0]['nombre']}";
                            $wa_lineas[]     = "  - {$sabor_info[0]['nombre']}: {$pl} plancha" . ($pl > 1 ? 's' : '');
                        }
                    }
                }
                $detalle_personalizados[] = "Sabores ({$elegidos_cantidad}): " . implode(', ', $lista_sabores)
                    . "\n[Datos sabores: " . json_encode($sabores) . "]";
            }
        }

        if ($cantidad_sandwiches === 0 || empty($partes_nombre)) {
            throw new Exception('Seleccioná al menos un producto para tu pedido');
        }

        $nombre_producto = implode(' + ', $partes_nombre);

        if (!empty($detalle_personalizados)) {
            $obs_interna .= "\n\n🎨 Pedido Personalizado\n" . implode("\n", $detalle_personalizados);
        }

        // Mínimo de 3 planchas (24 sándwiches) para delivery
        if ($modalidad === 'Delivery' && $cantidad_sandwiches < 24) {
            throw new Exception('El mínimo para delivery es 24 sándwiches (3 planchas)');
        }

        if (!empty($observaciones)) {
            $obs_interna .= "\n\nNotas del cliente:\n{$observaciones}";
        }

        // Insertar pedido ($fecha_entrega ya fue definida arriba)
        $fecha_display = date('d/m H:i');

        $stmt = $pdo->prepare("
            INSERT INTO pedidos (
                nombre, apellido, telefono, direccion,
                producto, cantidad, precio,
                modalidad, forma_pago, ubicacion,
                estado, observaciones, fecha_entrega, turno_entrega,
                created_at, fecha_display, tomado
            ) VALUES (
                ?, ?, ?, ?,
                ?, ?, ?,
                ?, ?, 'Local 1',
                'Pendiente', ?, ?, ?,
                NOW(), ?, 0
            )
        ");

        $stmt->execute([
            $nombre, $apellido, $telefono, $direccion,
            $nombre_producto, $cantidad_sandwiches, $precio,
            $modalidad, $forma_pago,
            $obs_interna, $fecha_entrega, $turno,
            $fecha_display
        ]);

        $pedido_id = $pdo->lastInsertId();
        // Stock se calcula dinámicamente desde la tabla pedidos (no hay columna stock que decrementar)

        // Guardar datos para envio a Sheets en background (al final de la pagina)
        $sheets_pedido_id = $pedido_id;
        $sheets_data_online = [
            'nombre'        => $nombre,
            'apellido'      => $apellido,
            'telefono'      => $telefono,
            'producto'      => $nombre_producto,
            'cantidad'      => $cantidad_sandwiches,
            'precio'        => $precio,
            'forma_pago'    => $forma_pago,
            'modalidad'     => $modalidad,
            'ubicacion'     => 'Local 1',
            'estado'        => 'Pendiente',
            'direccion'     => $direccion,
            'observaciones' => $obs_interna,
            'fecha_entrega' => $fecha_entrega,
        ];

        $pedido_confirmado = [
            'id'          => $pedido_id,
            'nombre'      => $nombre,
            'apellido'    => $apellido,
            'turno'       => $turno,
            'producto'    => $nombre_producto,
            'precio'      => $precio,
            'modalidad'   => $modalidad,
            'forma_pago'  => $forma_pago,
            'fecha_pedido'=> $fecha_entrega,
            'direccion'   => $direccion,
            'observaciones'=> $obs_interna,
        ];

        // Armar mensaje WhatsApp
        $dias = ['Dom','Lun','Mar','Mié','Jue','Vie','Sáb'];
        $fecha_ts  = strtotime($fecha_entrega);
        $fecha_str = $dias[date('w', $fecha_ts)] . ' ' . date('d/m', $fecha_ts);

        $wamsg  = "*NUEVO PEDIDO - Santa Catalina*\n\n";
        $wamsg .= "*Pedido nº #$pedido_id*\n\n";
        $wamsg .= "*$nombre $apellido*\n";
        $wamsg .= "$fecha_str - Turno $turno\n";
        $wamsg .= ($modalidad === 'Delivery' ? 'Reparto' : $modalidad);
        if ($modalidad === 'Delivery' && !empty($direccion)) {
            $wamsg .= "\n$direccion";
        }
        $wamsg .= "\n\n*Pedido:*\n";

        if (empty($wa_lineas)) $wa_lineas = [$nombre_producto];
        foreach ($wa_lineas as $linea) {
            $esSubitem = strpos($linea, '  - ') === 0;
            $wamsg .= ($esSubitem ? "$linea\n" : "- $linea\n");
        }

        if (!empty($observaciones)) {
            $wamsg .= "Obs: " . trim($observaciones) . "\n";
        }

        $precio_formateado = '$' . number_format($precio, 0, ',', '.');
        $wamsg .= "\nForma de pago: {$forma_pago}\nTotal: {$precio_formateado}\n";

        $pedido_confirmado['wamsg'] = $wamsg;

    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Pedí Online - Santa Catalina 🥪</title>
    <meta name="description" content="Hacé tu pedido online en Sandwichería Santa Catalina. Sándwiches triples frescos con delivery y retiro.">
    <meta name="theme-color" content="#ea580c">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="apple-mobile-web-app-title" content="Santa Catalina">
    <link rel="manifest" href="manifest.json">
    <link rel="apple-touch-icon" href="/img/icon-192.png">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        body { font-family: 'Arial', sans-serif; }

        .paso-indicador { display: flex; flex-direction: column; align-items: center; transition: all 0.3s; }
        .paso-indicador.activo div:first-child { background: #ea580c; color: white; box-shadow: 0 0 0 4px rgba(234,88,12,0.3); }
        .paso-indicador.completado div:first-child { background: #16a34a; color: white; }

        .producto-card {
            border: 3px solid #e5e7eb;
            border-radius: 12px;
            cursor: pointer;
            transition: all 0.25s;
        }
        .producto-card:hover { border-color: #ea580c; transform: translateY(-2px); box-shadow: 0 8px 20px rgba(234,88,12,0.15); }
        .producto-card.seleccionado { border-color: #ea580c; background: #fff7ed; }

        .pago-card { border: 3px solid #e5e7eb; border-radius: 12px; cursor: pointer; transition: all 0.25s; }
        .pago-card:hover { border-color: #ea580c; }
        .pago-card.seleccionado { border-color: #ea580c; background: #fff7ed; }

        .turno-card { border: 3px solid #e5e7eb; border-radius: 12px; cursor: pointer; transition: all 0.25s; }
        .turno-card:hover { border-color: #7c3aed; }
        .turno-card.seleccionado { border-color: #7c3aed; background: #faf5ff; }
        .turno-card.sin-stock { opacity: 0.5; cursor: not-allowed; }

        .modalidad-card { border: 3px solid #e5e7eb; border-radius: 12px; cursor: pointer; transition: all 0.25s; }
        .modalidad-card:hover { border-color: #0284c7; }
        .modalidad-card.seleccionado { border-color: #0284c7; background: #f0f9ff; }

        .tipo-card { border: 4px solid #e5e7eb; border-radius: 16px; padding: 30px; text-align: center; cursor: pointer; transition: all 0.3s; }
        .tipo-card:hover { transform: scale(1.03); box-shadow: 0 10px 30px rgba(0,0,0,0.1); }
        .tipo-card.seleccionado { border-color: #ea580c; background: #fff7ed; }

        .sabor-btn { border: 2px solid #e5e7eb; border-radius: 10px; padding: 10px; cursor: pointer; transition: all 0.2s; }
        .sabor-btn:hover { border-color: #ea580c; background: #fff7ed; }
        .sabor-btn.activo { border-color: #ea580c; background: #fff7ed; }

        .paso { display: none; }
        .paso.activo { display: block; }

        @keyframes fadeIn { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }
        .paso.activo { animation: fadeIn 0.3s ease; }

        .btn-instalar-app {
            background: linear-gradient(135deg, #ea580c, #dc2626);
            animation: pulse-app 2s infinite;
        }
        @keyframes pulse-app {
            0%, 100% { box-shadow: 0 0 0 0 rgba(234,88,12,0.4); }
            50% { box-shadow: 0 0 0 8px rgba(234,88,12,0); }
        }
    </style>
</head>
<body class="bg-gradient-to-br from-orange-50 to-amber-50 min-h-screen">

    <!-- Banner instalar app (solo en móvil, solo en navegador, no en PWA) -->
    <div id="bannerInstalarApp" class="hidden bg-gradient-to-r from-orange-600 to-red-600 text-white py-2 px-4 text-center text-sm">
        <span class="mr-2">📱 Agregá Santa Catalina a tu pantalla de inicio para pedir más rápido</span>
        <button onclick="instalarApp()" class="bg-white text-orange-600 font-bold px-3 py-1 rounded-full text-xs">
            Instalar
        </button>
        <button onclick="document.getElementById('bannerInstalarApp').remove()" class="ml-2 opacity-70">✕</button>
    </div>

    <!-- Header -->
    <header class="bg-white shadow-md sticky top-0 z-50">
        <div class="container mx-auto px-4 py-3 flex justify-between items-center">
            <div class="flex items-center">
                <div class="w-10 h-10 bg-orange-500 rounded-full flex items-center justify-center mr-3 shadow">
                    <i class="fas fa-hamburger text-white text-lg"></i>
                </div>
                <div>
                    <h1 class="text-lg font-black text-gray-900 leading-tight">Santa Catalina</h1>
                    <p class="text-xs text-orange-600 font-semibold">Pedido Online</p>
                </div>
            </div>
            <div class="flex items-center space-x-2">
                <a href="tel:+541159813546" class="text-green-600 hover:text-green-700 text-sm font-semibold hidden sm:flex items-center">
                    <i class="fas fa-phone mr-1"></i> 11 5981-3546
                </a>
            </div>
        </div>
    </header>

    <div class="container mx-auto px-4 py-6 max-w-2xl">

    <?php if ($pedido_confirmado): ?>
        <!-- ============ CONFIRMACIÓN ============ -->
        <div class="bg-white rounded-2xl shadow-xl overflow-hidden">
            <div class="bg-gradient-to-r from-green-500 to-emerald-600 p-8 text-white text-center">
                <div class="w-20 h-20 bg-white rounded-full flex items-center justify-center mx-auto mb-4 shadow-lg">
                    <i class="fas fa-check text-green-500 text-4xl"></i>
                </div>
                <h2 class="text-3xl font-black mb-2">¡Orden Generada!</h2>
                <p class="text-green-100 text-lg">Pedido #<?= $pedido_confirmado['id'] ?></p>
            </div>
            <div class="p-6 space-y-4">
                <div class="bg-gray-50 rounded-xl p-4 space-y-3">
                    <div class="flex justify-between items-center">
                        <span class="text-gray-600 font-medium">Cliente</span>
                        <span class="font-bold text-gray-900"><?= htmlspecialchars($pedido_confirmado['nombre']) ?></span>
                    </div>
                    <div class="flex justify-between items-center">
                        <span class="text-gray-600 font-medium">Producto</span>
                        <span class="font-bold text-gray-900 text-right max-w-xs"><?= htmlspecialchars($pedido_confirmado['producto']) ?></span>
                    </div>
                    <div class="flex justify-between items-center">
                        <span class="text-gray-600 font-medium">Turno</span>
                        <span class="font-bold text-purple-700"><?= htmlspecialchars($pedido_confirmado['turno']) ?></span>
                    </div>
                    <div class="flex justify-between items-center">
                        <span class="text-gray-600 font-medium">Modalidad</span>
                        <span class="font-bold"><?= htmlspecialchars($pedido_confirmado['modalidad'] === 'Delivery' ? 'Reparto' : $pedido_confirmado['modalidad']) ?></span>
                    </div>
                    <?php if ($pedido_confirmado['modalidad'] === 'Delivery' && !empty($pedido_confirmado['fecha_pedido'])): ?>
                    <div class="flex justify-between items-center">
                        <span class="text-gray-600 font-medium">Fecha entrega</span>
                        <span class="font-bold text-blue-700"><?= date('d/m/Y', strtotime($pedido_confirmado['fecha_pedido'])) ?></span>
                    </div>
                    <?php endif; ?>
                    <div class="flex justify-between items-center border-t border-gray-200 pt-2 mt-1">
                        <span class="text-gray-700 font-bold">Total</span>
                        <span class="font-black text-green-700 text-lg">$<?= number_format($pedido_confirmado['precio'], 0, ',', '.') ?></span>
                    </div>
                </div>

                <!-- Botón principal WhatsApp -->
                <a href="https://wa.me/541159813546?text=<?= rawurlencode($pedido_confirmado['wamsg']) ?>"
                   target="_blank"
                   class="block w-full bg-green-500 hover:bg-green-600 text-white py-4 rounded-xl font-bold text-lg transition-all text-center shadow-lg">
                    <i class="fab fa-whatsapp mr-2 text-xl"></i>Confirmar pedido
                </a>
                <p class="text-xs text-center text-gray-500 -mt-1">Se abre WhatsApp con tu pedido para confirmarlo con nosotros</p>

                <button onclick="window.location.href='/pedido_online/index.php'"
                        class="w-full bg-orange-100 hover:bg-orange-200 text-orange-700 py-3 rounded-xl font-semibold transition-all">
                    <i class="fas fa-plus mr-2"></i>Hacer otro pedido
                </button>
            </div>
        </div>

    <?php else: ?>
        <!-- ============ FORMULARIO MULTI-PASO ============ -->

        <?php if ($error): ?>
            <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-4 rounded-lg flex items-start">
                <i class="fas fa-exclamation-triangle text-xl mr-3 mt-0.5"></i>
                <div>
                    <p class="font-bold">¡Ups!</p>
                    <p><?= htmlspecialchars($error) ?></p>
                </div>
            </div>
        <?php endif; ?>

        <div class="bg-white rounded-2xl shadow-xl overflow-hidden">

            <!-- Indicador de pasos -->
            <div class="bg-gradient-to-r from-orange-500 to-red-500 text-white p-5">
                <div class="flex items-center justify-center space-x-2">
                    <?php
                    $pasos = ['Datos', 'Producto', 'Entrega'];
                    foreach ($pasos as $i => $nombre_paso):
                        $num = $i + 1;
                    ?>
                        <?php if ($i > 0): ?>
                            <div class="flex-1 h-1 bg-white bg-opacity-30 max-w-12"></div>
                        <?php endif; ?>
                        <div id="indicador-paso-<?= $num ?>" class="paso-indicador <?= $num === 1 ? 'activo' : '' ?>">
                            <div class="w-10 h-10 rounded-full bg-white bg-opacity-30 text-white flex items-center justify-center font-bold text-sm">
                                <?= $num ?>
                            </div>
                            <span class="text-xs mt-1 font-medium"><?= $nombre_paso ?></span>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <form id="formPedido" method="POST" onsubmit="return enviarFormulario(event)">
                <input type="hidden" name="pedidos_json" id="campo_pedidos_json" value="[]">
                <input type="hidden" name="turno" id="campo_turno" value="">
                <input type="hidden" name="forma_pago" id="campo_forma_pago" value="Transferencia">
                <input type="hidden" name="modalidad" id="campo_modalidad" value="Retiro">
                <input type="hidden" name="direccion" id="campo_direccion" value="">
                <input type="hidden" name="fecha_pedido" id="campo_fecha_pedido" value="">

                <div class="p-5 sm:p-6">

                    <!-- ===== PASO 1: DATOS PERSONALES ===== -->
                    <div id="paso-1" class="paso activo space-y-4">
                        <h2 class="text-xl font-black text-gray-900 mb-4">
                            <i class="fas fa-user text-orange-500 mr-2"></i>Tus datos
                        </h2>
                        <div>
                            <label class="block text-sm font-bold text-gray-700 mb-2">Nombre *</label>
                            <input type="text" id="campo_nombre" name="nombre" required
                                   value="<?= htmlspecialchars($_POST['nombre'] ?? '') ?>"
                                   placeholder="Ej: Juan"
                                   class="w-full px-4 py-3 border-2 border-gray-300 rounded-xl focus:border-orange-500 focus:ring-2 focus:ring-orange-200 text-lg">
                        </div>
                        <div>
                            <label class="block text-sm font-bold text-gray-700 mb-2">Apellido *</label>
                            <input type="text" id="campo_apellido" name="apellido" required
                                   value="<?= htmlspecialchars($_POST['apellido'] ?? '') ?>"
                                   placeholder="Ej: Pérez"
                                   class="w-full px-4 py-3 border-2 border-gray-300 rounded-xl focus:border-orange-500 focus:ring-2 focus:ring-orange-200 text-lg">
                        </div>
                        <div>
                            <label class="block text-sm font-bold text-gray-700 mb-2">Teléfono *</label>
                            <input type="tel" id="campo_telefono" name="telefono" required
                                   value="<?= htmlspecialchars($_POST['telefono'] ?? '') ?>"
                                   placeholder="Ej: 221 123-4567 o 11 5981-3546"
                                   class="w-full px-4 py-3 border-2 border-gray-300 rounded-xl focus:border-orange-500 focus:ring-2 focus:ring-orange-200 text-lg">
                        </div>
                        <div class="bg-green-50 border border-green-200 rounded-xl p-3 text-sm text-green-800 flex items-start gap-2">
                            <i class="fab fa-whatsapp text-green-600 text-lg mt-0.5"></i>
                            <span>Te confirmamos el pedido y coordinamos por <strong>WhatsApp</strong></span>
                        </div>
                        <button type="button" onclick="irAPaso(3)"
                                class="w-full bg-gradient-to-r from-orange-500 to-red-500 hover:from-orange-600 hover:to-red-600 text-white py-4 rounded-xl font-black text-lg shadow transition-all mt-2">
                            Continuar <i class="fas fa-arrow-right ml-2"></i>
                        </button>
                    </div>

                    <!-- ===== PASO 3: PRODUCTO (CARRITO) ===== -->
                    <div id="paso-3-simple" class="paso space-y-4">
                        <h2 class="text-xl font-black text-gray-900 mb-1">
                            <i class="fas fa-list text-orange-500 mr-2"></i>Elegí tu combo
                        </h2>
                        <p class="text-sm text-gray-500 mb-3">Podés combinar varios tipos. Usá + y − para ajustar cantidades.</p>
                        <div class="space-y-3" id="lista-productos-simples">
                            <?php foreach ($productos_simples as $prod): ?>
                                <div class="bg-white border-2 border-gray-200 rounded-xl p-4 transition-all" id="card-prod-<?= $prod['id'] ?>">
                                    <div class="flex items-center justify-between gap-3">
                                        <div class="flex-1 min-w-0">
                                            <h3 class="font-bold text-gray-900"><?= htmlspecialchars($prod['nombre']) ?></h3>
                                            <?php if (!empty($prod['descripcion'])): ?>
                                                <p class="text-xs text-gray-500 mt-0.5"><?= htmlspecialchars($prod['descripcion']) ?></p>
                                            <?php endif; ?>
                                            <div class="flex gap-3 mt-1">
                                                <span class="text-sm font-bold text-green-600">💵 $<?= number_format($prod['precio_efectivo'], 0, ',', '.') ?></span>
                                                <span class="text-xs text-blue-500 self-center">🏦 $<?= number_format($prod['precio_transferencia'], 0, ',', '.') ?></span>
                                            </div>
                                        </div>
                                        <div class="flex items-center gap-2 flex-shrink-0">
                                            <?php
                                                preg_match('/(\d+)/', $prod['nombre'], $m_unid);
                                                $unidades_prod = (int)($m_unid[1] ?? 1);
                                            ?>
                                            <button type="button"
                                                    onclick="cambiarCantidadCarrito(<?= $prod['id'] ?>, '<?= htmlspecialchars(addslashes($prod['nombre'])) ?>', <?= $prod['precio_efectivo'] ?>, <?= $prod['precio_transferencia'] ?>, -1, <?= $unidades_prod ?>)"
                                                    class="w-9 h-9 border-2 border-gray-300 rounded-lg text-gray-500 font-black text-lg hover:border-orange-400 hover:text-orange-500 transition-all">−</button>
                                            <span id="cant-carrito-<?= $prod['id'] ?>" class="text-xl font-black text-gray-900 w-6 text-center">0</span>
                                            <button type="button"
                                                    onclick="cambiarCantidadCarrito(<?= $prod['id'] ?>, '<?= htmlspecialchars(addslashes($prod['nombre'])) ?>', <?= $prod['precio_efectivo'] ?>, <?= $prod['precio_transferencia'] ?>, 1, <?= $unidades_prod ?>)"
                                                    class="w-9 h-9 border-2 border-orange-500 rounded-lg text-orange-600 font-black text-lg hover:bg-orange-500 hover:text-white transition-all">+</button>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>

                        <!-- Resumen del carrito -->
                        <div id="resumen-carrito" class="hidden bg-orange-50 border border-orange-200 rounded-xl p-4">
                            <p class="text-sm font-bold text-orange-800 mb-2"><i class="fas fa-shopping-cart mr-1"></i>Tu pedido:</p>
                            <ul id="lista-carrito" class="text-sm text-gray-800 space-y-1"></ul>
                        </div>

                        <!-- Acceso a pedido personalizado -->
                        <button type="button" onclick="abrirPersonalizado()"
                                class="w-full flex items-center justify-center gap-2 bg-purple-50 border-2 border-purple-300 hover:bg-purple-100 text-purple-800 py-3 px-4 rounded-xl font-bold text-sm transition-all">
                            🎨 ¿Querés armarlo a tu gusto? Pedido personalizado
                        </button>

                        <div class="flex gap-3 mt-2">
                            <button type="button" onclick="volverDesdeProducto('combo')"
                                    class="flex-1 bg-gray-200 hover:bg-gray-300 text-gray-700 py-3 rounded-xl font-bold transition-all">
                                <i class="fas fa-arrow-left mr-2"></i>Volver
                            </button>
                            <button type="button" onclick="agregarComboAlPedido()"
                                    class="flex-1 bg-gradient-to-r from-orange-500 to-red-500 hover:from-orange-600 hover:to-red-600 text-white py-3 rounded-xl font-black transition-all">
                                Agregar a mi pedido <i class="fas fa-plus ml-2"></i>
                            </button>
                        </div>
                    </div>

                    <!-- ===== PASO 3B: ELEGIDOS / PERSONALIZADO ===== -->
                    <div id="paso-3-personalizado" class="paso space-y-4">
                        <h2 class="text-xl font-black text-gray-900 mb-1">
                            <i class="fas fa-sliders-h text-orange-500 mr-2"></i>Armá tu pedido
                        </h2>

                        <!-- Selector de sabores -->
                        <div id="bloque-sabores">
                            <div class="flex items-center justify-between mb-3">
                                <label class="text-sm font-bold text-gray-700">
                                    Elegí tus sabores <span class="text-orange-500 font-normal text-xs">(máx. 6 planchas)</span>
                                </label>
                                <span class="text-sm font-bold text-orange-600 bg-orange-50 border border-orange-200 rounded-lg px-2 py-1">
                                    <span id="contador-planchas">0</span>/6 planchas
                                </span>
                            </div>

                            <!-- COMUNES -->
                            <p class="text-xs font-bold text-green-700 mb-2 mt-1">🟢 SABORES CLÁSICOS</p>
                            <div class="space-y-2 mb-4">
                                <?php foreach (array_filter($sabores_disponibles, fn($s) => $s['tipo'] === 'comun') as $sabor): ?>
                                    <div class="flex items-center justify-between border-2 border-green-200 rounded-lg px-3 py-2"
                                         id="sabor-<?= $sabor['id'] ?>">
                                        <div class="flex items-center gap-2">
                                            <span class="text-lg"><?= $sabor['emoji'] ?></span>
                                            <span class="text-sm font-medium text-gray-700"><?= htmlspecialchars($sabor['nombre']) ?></span>
                                        </div>
                                        <div class="flex items-center gap-2">
                                            <button type="button" onclick="cambiarSabor('<?= $sabor['id'] ?>', -1)"
                                                    class="w-8 h-8 border-2 border-green-300 rounded-lg text-green-700 font-bold hover:bg-green-500 hover:text-white hover:border-green-500 transition-all">−</button>
                                            <span id="cant-sabor-<?= $sabor['id'] ?>" class="w-6 text-center font-bold text-gray-900">0</span>
                                            <button type="button" onclick="cambiarSabor('<?= $sabor['id'] ?>', 1)"
                                                    class="w-8 h-8 border-2 border-green-300 rounded-lg text-green-700 font-bold hover:bg-green-500 hover:text-white hover:border-green-500 transition-all">+</button>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>

                            <!-- PREMIUM -->
                            <p class="text-xs font-bold text-orange-600 mb-2">🟠 SABORES PREMIUM</p>
                            <div class="space-y-2">
                                <?php foreach (array_filter($sabores_disponibles, fn($s) => $s['tipo'] === 'premium') as $sabor): ?>
                                    <div class="flex items-center justify-between border-2 border-orange-200 rounded-lg px-3 py-2"
                                         id="sabor-<?= $sabor['id'] ?>">
                                        <div class="flex items-center gap-2">
                                            <span class="text-lg"><?= $sabor['emoji'] ?></span>
                                            <span class="text-sm font-medium text-gray-700"><?= htmlspecialchars($sabor['nombre']) ?></span>
                                        </div>
                                        <div class="flex items-center gap-2">
                                            <button type="button" onclick="cambiarSabor('<?= $sabor['id'] ?>', -1)"
                                                    class="w-8 h-8 border-2 border-orange-300 rounded-lg text-orange-700 font-bold hover:bg-orange-500 hover:text-white hover:border-orange-500 transition-all">−</button>
                                            <span id="cant-sabor-<?= $sabor['id'] ?>" class="w-6 text-center font-bold text-gray-900">0</span>
                                            <button type="button" onclick="cambiarSabor('<?= $sabor['id'] ?>', 1)"
                                                    class="w-8 h-8 border-2 border-orange-300 rounded-lg text-orange-700 font-bold hover:bg-orange-500 hover:text-white hover:border-orange-500 transition-all">+</button>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>

                        <!-- Precio dinámico elegidos -->
                        <div id="precio-elegidos-preview" class="hidden bg-orange-50 border-2 border-orange-200 rounded-xl p-3 text-center">
                            <div class="text-xs text-gray-500 mb-1">Precio estimado (según sabores)</div>
                            <div class="font-black text-green-700 text-xl" id="precio-elegidos-display">—</div>
                            <div class="text-xs text-blue-500 mt-0.5" id="precio-elegidos-trans"></div>
                            <div class="text-xs text-gray-400 mt-1">Te confirmamos el precio final por WhatsApp</div>
                        </div>

                        <div class="flex gap-3 mt-2">
                            <button type="button" onclick="volverDesdeProducto('personalizado')"
                                    class="flex-1 bg-gray-200 hover:bg-gray-300 text-gray-700 py-3 rounded-xl font-bold transition-all">
                                <i class="fas fa-arrow-left mr-2"></i>Volver
                            </button>
                            <button type="button" onclick="agregarPersonalizadoAlPedido()"
                                    class="flex-1 bg-gradient-to-r from-orange-500 to-red-500 hover:from-orange-600 hover:to-red-600 text-white py-3 rounded-xl font-black transition-all">
                                Agregar a mi pedido <i class="fas fa-plus ml-2"></i>
                            </button>
                        </div>
                    </div>

                    <!-- ===== PASO 3.5: RESUMEN ACUMULADO ===== -->
                    <div id="paso-resumen-acumulado" class="paso space-y-4">
                        <h2 class="text-xl font-black text-gray-900 mb-1">
                            <i class="fas fa-list-check text-orange-500 mr-2"></i>Tu pedido hasta ahora
                        </h2>
                        <p class="text-sm text-gray-500 mb-3">Podés seguir agregando combos o pedidos personalizados antes de confirmar.</p>

                        <div id="lista-acumulados" class="space-y-2"></div>

                        <div class="bg-orange-50 border-2 border-orange-300 rounded-xl p-4 flex justify-between items-center">
                            <span class="font-bold text-gray-700">Total estimado</span>
                            <span id="total-acumulado-display" class="text-2xl font-black text-green-700">$0</span>
                        </div>

                        <div class="grid grid-cols-2 gap-3">
                            <button type="button" onclick="mostrarPaso(3)"
                                    class="bg-blue-50 border-2 border-blue-300 hover:bg-blue-100 text-blue-800 py-3 rounded-xl font-bold text-sm transition-all">
                                🍔 + Combo
                            </button>
                            <button type="button" onclick="abrirPersonalizado()"
                                    class="bg-purple-50 border-2 border-purple-300 hover:bg-purple-100 text-purple-800 py-3 rounded-xl font-bold text-sm transition-all">
                                🎨 + Personalizado
                            </button>
                        </div>

                        <button type="button" onclick="continuarAEntrega()"
                                class="w-full bg-gradient-to-r from-orange-500 to-red-500 hover:from-orange-600 hover:to-red-600 text-white py-3 rounded-xl font-black transition-all mt-2">
                            Continuar a Entrega y Pago <i class="fas fa-arrow-right ml-2"></i>
                        </button>
                    </div>

                    <!-- ===== PASO 4: ENTREGA Y PAGO ===== -->
                    <div id="paso-4" class="paso space-y-5">
                        <h2 class="text-xl font-black text-gray-900 mb-4">
                            <i class="fas fa-truck text-orange-500 mr-2"></i>Entrega y pago
                        </h2>

                        <!-- 1. Modalidad -->
                        <div>
                            <label class="block text-sm font-bold text-gray-700 mb-3">
                                <i class="fas fa-map-marker-alt text-blue-500 mr-1"></i>¿Cómo lo recibís?
                            </label>
                            <div class="grid grid-cols-2 gap-3">
                                <div class="modalidad-card p-4 text-center seleccionado" onclick="seleccionarModalidad('Retiro')">
                                    <i class="fas fa-shopping-bag text-3xl text-orange-500 mb-2"></i>
                                    <div class="font-bold text-gray-900">Retiro</div>
                                    <div class="text-xs text-gray-500 mt-1">Pasás a buscarlo</div>
                                </div>
                                <div class="modalidad-card p-4 text-center" onclick="seleccionarModalidad('Delivery')">
                                    <i class="fas fa-motorcycle text-3xl text-blue-500 mb-2"></i>
                                    <div class="font-bold text-gray-900">Reparto</div>
                                    <div class="text-xs text-gray-500 mt-1">Te lo llevamos</div>
                                </div>
                            </div>
                        </div>

                        <!-- 2. Fecha de entrega (retiro y delivery) -->
                        <div id="bloque-fecha" class="hidden">
                            <label class="block text-sm font-bold text-gray-700 mb-3">
                                <i class="fas fa-calendar text-purple-500 mr-1"></i>¿Para qué día?
                            </label>
                            <div id="opciones-fecha" class="flex gap-2 flex-wrap"></div>
                            <p class="text-xs text-gray-500 mt-2" id="hint-fecha">
                                <i class="fas fa-info-circle mr-1"></i>
                                Los turnos disponibles se actualizan según el día seleccionado
                            </p>
                        </div>

                        <!-- 3. Turno (renderizado por JS) -->
                        <div>
                            <label class="block text-sm font-bold text-gray-700 mb-3">
                                <i class="fas fa-clock text-purple-500 mr-1"></i>¿En qué turno?
                            </label>
                            <div id="grid-turnos" class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                                <!-- Se renderiza por JS en mostrarPaso(4) -->
                            </div>
                        </div>

                        <!-- 4. Dirección delivery -->
                        <div id="bloque-direccion" class="hidden space-y-2">
                            <label class="block text-sm font-bold text-gray-700">Dirección de entrega *</label>

                            <!-- Localidades habilitadas -->
                            <?php
                            $locs_html = [];
                            try {
                                $locs_html = $pdo->query("SELECT nombre FROM localidades_delivery WHERE activo = 1 ORDER BY orden, nombre")->fetchAll(PDO::FETCH_COLUMN);
                            } catch (PDOException $e) {}
                            if (!empty($locs_html)):
                            ?>
                            <div class="bg-blue-50 border border-blue-200 rounded-xl p-3 text-xs text-blue-800">
                                <div class="font-bold mb-1"><i class="fas fa-map-marker-alt mr-1"></i>Localidades con delivery habilitado:</div>
                                <div class="flex flex-wrap gap-1">
                                    <?php foreach ($locs_html as $loc): ?>
                                        <span class="bg-blue-100 text-blue-700 px-2 py-0.5 rounded-full"><?= htmlspecialchars($loc) ?></span>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                            <?php endif; ?>

                            <div class="grid grid-cols-3 gap-2">
                                <input type="text" id="dir_calle" placeholder="Calle *"
                                       class="col-span-2 px-3 py-3 border-2 border-gray-300 rounded-xl focus:border-blue-500 focus:ring-2 focus:ring-blue-200 text-sm">
                                <input type="text" id="dir_numero" placeholder="Número *"
                                       class="px-3 py-3 border-2 border-gray-300 rounded-xl focus:border-blue-500 focus:ring-2 focus:ring-blue-200 text-sm">
                            </div>
                            <select id="dir_localidad"
                                    class="w-full px-3 py-3 border-2 border-gray-300 rounded-xl focus:border-blue-500 focus:ring-2 focus:ring-blue-200 text-sm bg-white">
                                <option value="">— Seleccioná tu localidad *</option>
                                <?php foreach ($locs_html as $loc): ?>
                                    <option value="<?= htmlspecialchars($loc) ?>"><?= htmlspecialchars($loc) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <input type="text" id="dir_entre_calles" placeholder="Entre calles * (ej: Belgrano y San Martín)"
                                   class="w-full px-3 py-3 border-2 border-gray-300 rounded-xl focus:border-blue-500 focus:ring-2 focus:ring-blue-200 text-sm">
                        </div>

                        <!-- 5b. Forma de pago (informativo, no cambia el precio) -->
                        <div>
                            <label class="block text-sm font-bold text-gray-700 mb-3">
                                <i class="fas fa-wallet text-green-600 mr-1"></i>¿Cómo vas a pagar?
                            </label>
                            <div class="grid grid-cols-2 gap-3">
                                <div class="pago-card p-4 text-center seleccionado" onclick="seleccionarPago('Transferencia', this)">
                                    <i class="fas fa-university text-3xl text-blue-500 mb-2"></i>
                                    <div class="font-bold text-gray-900">Transferencia</div>
                                </div>
                                <div class="pago-card p-4 text-center" onclick="seleccionarPago('Efectivo', this)">
                                    <i class="fas fa-money-bill-wave text-3xl text-green-500 mb-2"></i>
                                    <div class="font-bold text-gray-900">Efectivo</div>
                                </div>
                            </div>
                        </div>

                        <!-- 6. Observaciones -->
                        <div>
                            <label class="block text-sm font-bold text-gray-700 mb-2">
                                Observaciones <span class="text-gray-400 font-normal">(opcional)</span>
                            </label>
                            <textarea name="observaciones" rows="2"
                                      placeholder="Ej: sin cebolla, alergias, aclaración para el delivery..."
                                      class="w-full px-4 py-3 border-2 border-gray-300 rounded-xl focus:border-orange-500 focus:ring-2 focus:ring-orange-200 text-sm"></textarea>
                        </div>

                        <!-- 7. Resumen del pedido -->
                        <div id="resumen-pedido" class="hidden bg-orange-50 border-2 border-orange-200 rounded-xl p-4">
                            <h3 class="font-bold text-gray-800 mb-3 flex items-center">
                                <i class="fas fa-receipt text-orange-500 mr-2"></i>Resumen del pedido
                            </h3>
                            <div class="space-y-2 text-sm">
                                <div class="flex justify-between">
                                    <span class="text-gray-600">Producto:</span>
                                    <span class="font-semibold text-gray-900 text-right max-w-xs" id="resumen-producto">—</span>
                                </div>
                                <div id="resumen-fila-fecha" class="hidden flex justify-between">
                                    <span class="text-gray-600">Fecha entrega:</span>
                                    <span class="font-semibold text-blue-700" id="resumen-fecha">—</span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-gray-600">Turno:</span>
                                    <span class="font-semibold text-gray-900" id="resumen-turno">—</span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-gray-600">Pago:</span>
                                    <span class="font-semibold text-gray-900" id="resumen-pago">—</span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-gray-600">Modalidad:</span>
                                    <span class="font-semibold text-gray-900" id="resumen-modalidad">—</span>
                                </div>
                                <div id="resumen-fila-precio" class="hidden flex justify-between border-t border-orange-300 pt-2 mt-1">
                                    <span class="text-gray-700 font-bold">Total:</span>
                                    <span class="font-black text-green-700 text-base" id="resumen-precio">—</span>
                                </div>
                            </div>
                        </div>

                        <div class="flex gap-3">
                            <button type="button" onclick="mostrarResumenAcumulado()"
                                    class="flex-1 bg-gray-200 hover:bg-gray-300 text-gray-700 py-3 rounded-xl font-bold transition-all">
                                <i class="fas fa-arrow-left mr-2"></i>Volver
                            </button>
                            <button type="submit" id="btn-confirmar"
                                    class="flex-2 flex-grow-[2] bg-gradient-to-r from-orange-500 to-red-500 hover:from-orange-600 hover:to-red-600 text-white py-4 rounded-xl font-black text-lg shadow transition-all">
                                <i class="fas fa-check-circle mr-2"></i>Confirmar Pedido
                            </button>
                        </div>
                    </div>

                </div><!-- /p-5 -->
            </form>
        </div><!-- /card -->
    <?php endif; ?>

    </div><!-- /container -->

    <script>
    // ============================================================
    // CONFIG ESTÁTICA DE TURNOS (hora/corte — sin stock)
    // ============================================================
    const turnosConfig = <?= $turnos_config_json ?>;
    const preciosElegidos = <?= $precios_elegidos_json ?>;
    const localidadesActivas = <?= $localidades_activas_json ?>;
    // Tabla de precios personalizados por planchas (transferencia)
    const TABLA_PERSONALIZADO = <?= json_encode($tabla_personalizado) ?>;

    // ============================================================
    // UTILIDADES ZONA HORARIA ARGENTINA (UTC-3, sin DST)
    // ============================================================
    function getArgentinaDate(offsetDays = 0) {
        // Restar 3h a UTC = tiempo en Argentina
        const arMs = Date.now() - 3 * 3600000;
        const d = new Date(arMs);
        d.setUTCDate(d.getUTCDate() + offsetDays);
        const y = d.getUTCFullYear();
        const m = String(d.getUTCMonth() + 1).padStart(2, '0');
        const dd = String(d.getUTCDate()).padStart(2, '0');
        return `${y}-${m}-${dd}`;
    }

    function formatFechaCorta(isoDate) {
        const [y, m, d] = isoDate.split('-').map(Number);
        const dias = ['dom','lun','mar','mié','jue','vie','sáb'];
        const dt = new Date(y, m - 1, d);
        return `${dias[dt.getDay()]} ${d}/${m}`;
    }

    // ============================================================
    // DISPONIBILIDAD POR FECHA (AJAX)
    // ============================================================
    // Estado de disponibilidad: { "Mañana": {disponible, max, activo, ...}, ... }
    // Se llena con cargarDisponibilidad()

    async function cargarDisponibilidad(fechaISO) {
        const grid = document.getElementById('grid-turnos');
        grid.innerHTML = `<div class="col-span-3 text-center text-gray-400 py-4">
            <i class="fas fa-spinner fa-spin mr-2"></i>Verificando disponibilidad...</div>`;

        const idPeticion = ++estado._dispReqId;
        const controller = new AbortController();
        const timeoutId = setTimeout(() => controller.abort(), 8000);

        try {
            const res = await fetch(`/pedido_online/disponibilidad.php?fecha=${fechaISO}&modalidad=${estado.modalidad}`, { signal: controller.signal });
            clearTimeout(timeoutId);
            if (!res.ok) throw new Error('Error');
            const data = await res.json();
            if (idPeticion !== estado._dispReqId) return; // llegó una respuesta vieja, ignorar
            estado.disponibilidad = data;
            renderTurnos(fechaISO);
        } catch (e) {
            clearTimeout(timeoutId);
            if (idPeticion !== estado._dispReqId) return;
            grid.innerHTML = `<div class="col-span-3 bg-red-50 border border-red-200 rounded-xl p-4 text-center text-red-600">
                <p class="font-bold">No se pudo verificar disponibilidad</p>
                <p class="text-sm mb-2">Intentá de nuevo o consultanos por WhatsApp</p>
                <button type="button" onclick="cargarDisponibilidad('${fechaISO}')"
                        class="bg-red-500 hover:bg-red-600 text-white px-4 py-2 rounded-lg text-sm font-semibold">
                    <i class="fas fa-redo mr-1"></i>Reintentar
                </button></div>`;
        }
    }

    function turnoDisponibleDeDisp(turno, fechaISO) {
        const disp = estado.disponibilidad?.[turno];
        if (!disp || !disp.activo || disp.disponible <= 0) return false;
        const cfg = turnosConfig.find(t => t.turno === turno);
        if (!cfg) return false;

        const [y, mo, d] = fechaISO.split('-').map(Number);
        const [h, min] = cfg.hora_inicio.split(':').map(Number);
        const turnoInicioUTC = Date.UTC(y, mo - 1, d, h + 3, min);
        const cutoffUTC = turnoInicioUTC - cfg.minutos_antes_corte * 60000;

        if (Date.now() < cutoffUTC) return true;

        // Retiro: si el turno ya arrancó pero sigue vigente (no pasó hora_fin), se puede pedir para retirar hoy
        if (estado.modalidad === 'Retiro' && cfg.hora_fin) {
            const [hf, minf] = cfg.hora_fin.split(':').map(Number);
            const turnoFinUTC = Date.UTC(y, mo - 1, d, hf + 3, minf);
            if (Date.now() >= turnoInicioUTC && Date.now() < turnoFinUTC) return true;
        }

        return false;
    }

    function turnoMotivoBloqueo(turno, fechaISO) {
        const disp = estado.disponibilidad?.[turno];
        if (!disp || !disp.activo) return 'No disponible';
        if (disp.disponible <= 0) return 'Sin cupos';
        return 'Fuera de horario';
    }

    // ============================================================
    // RENDER DINÁMICO DE TURNOS
    // ============================================================
    function renderTurnos(fechaISO) {
        fechaISO = fechaISO || estado.fechaPedido || getArgentinaDate(0);
        const grid = document.getElementById('grid-turnos');
        if (!turnosConfig || turnosConfig.length === 0) {
            grid.innerHTML = `<div class="col-span-3 bg-red-50 border border-red-200 rounded-xl p-4 text-center text-red-600">
                <i class="fas fa-exclamation-circle text-2xl mb-2"></i>
                <p class="font-bold">No hay turnos configurados</p>
                <p class="text-sm">Consultanos por WhatsApp</p></div>`;
            return;
        }
        if (!estado.disponibilidad) {
            grid.innerHTML = `<div class="col-span-3 text-center text-gray-400 py-4">Cargando disponibilidad...</div>`;
            return;
        }
        grid.innerHTML = turnosConfig.map(cfg => {
            const ok  = turnoDisponibleDeDisp(cfg.turno, fechaISO);
            const sel = estado.turno === cfg.turno;
            const disp = estado.disponibilidad?.[cfg.turno];
            return `<div class="turno-card p-4 text-center ${!ok ? 'sin-stock' : ''} ${sel ? 'seleccionado' : ''}"
                         onclick="${ok ? `seleccionarTurno('${cfg.turno}','${fechaISO}')` : ''}">
                <div class="text-2xl font-black text-gray-900">${cfg.turno}</div>
                <div class="text-sm text-gray-500 mt-1">${cfg.hora_inicio}${cfg.hora_fin ? ' – ' + cfg.hora_fin : ''}</div>
                <div class="mt-2 text-xs font-bold ${ok ? 'text-green-600' : 'text-red-500'}">
                    ${ok ? `✅ ${disp?.disponible ?? '—'} cupos` : `❌ ${turnoMotivoBloqueo(cfg.turno, fechaISO)}`}
                </div>
            </div>`;
        }).join('');
    }

    // ============================================================
    // SELECTOR DE FECHAS (delivery)
    // ============================================================
    function generarFechas() {
        const cont = document.getElementById('opciones-fecha');
        const opciones = [
            { iso: getArgentinaDate(0), label: 'Hoy' },
            { iso: getArgentinaDate(1), label: 'Mañana' },
            { iso: getArgentinaDate(2), label: formatFechaCorta(getArgentinaDate(2)) },
            { iso: getArgentinaDate(3), label: formatFechaCorta(getArgentinaDate(3)) },
            { iso: getArgentinaDate(4), label: formatFechaCorta(getArgentinaDate(4)) },
            { iso: getArgentinaDate(5), label: formatFechaCorta(getArgentinaDate(5)) },
            { iso: getArgentinaDate(6), label: formatFechaCorta(getArgentinaDate(6)) },
        ];
        cont.innerHTML = opciones.map(op => {
            const activo = estado.fechaPedido === op.iso;
            return `<button type="button" onclick="seleccionarFecha('${op.iso}')"
                class="px-4 py-2 rounded-xl border-2 font-bold text-sm transition-all flex-1 min-w-[70px]
                       ${activo ? 'border-blue-500 bg-blue-50 text-blue-700' : 'border-gray-300 hover:border-blue-400 text-gray-700'}">
                ${op.label}
            </button>`;
        }).join('');
    }

    function seleccionarFecha(fechaISO) {
        if (estado.fechaPedido !== fechaISO) {
            estado.turno = '';
            document.getElementById('campo_turno').value = '';
            estado.disponibilidad = null;
        }
        estado.fechaPedido = fechaISO;
        document.getElementById('campo_fecha_pedido').value = fechaISO;
        generarFechas();
        cargarDisponibilidad(fechaISO);
        actualizarDisplayResumen();
    }

    // ============================================================
    // INICIALIZAR PASO 4
    // ============================================================
    function initializarPaso4() {
        if (!estado.fechaPedido) {
            estado.fechaPedido = getArgentinaDate(0);
            document.getElementById('campo_fecha_pedido').value = estado.fechaPedido;
        }
        document.getElementById('bloque-fecha').classList.remove('hidden');
        generarFechas();
        estado.disponibilidad = null;
        cargarDisponibilidad(estado.fechaPedido);

        // Deshabilitar Delivery si el pedido no llega al mínimo (3 planchas / 24 sándwiches)
        const cardDelivery = document.querySelector('.modalidad-card[onclick*="Delivery"]');
        if (cardDelivery) {
            if (totalUnidadesPedido() < 24) {
                cardDelivery.classList.add('opacity-40', 'pointer-events-none');
                if (!cardDelivery.querySelector('.aviso-minimo')) {
                    const aviso = document.createElement('div');
                    aviso.className = 'aviso-minimo text-xs text-red-500 font-bold mt-1';
                    aviso.textContent = 'Mínimo 24 sándwiches (3 planchas)';
                    cardDelivery.appendChild(aviso);
                }
            } else {
                cardDelivery.classList.remove('opacity-40', 'pointer-events-none');
                cardDelivery.querySelector('.aviso-minimo')?.remove();
            }
        }
    }

    // ============================================================
    // ESTADO DEL PEDIDO
    // ============================================================
    let estado = {
        tipoPedido: 'simple',
        carrito: {},   // { productoId: {nombre, cantidad, precioEf, precioTr} }
        sabores: {},
        precioCalculadoEf: 0,
        precioCalculadoTr: 0,
        pedidosAcumulados: [], // { tipo:'combo'|'personalizado', ... } — se puede combinar varios antes de confirmar
        turno: '',
        modalidad: 'Retiro',
        formaPago: 'Transferencia',
        fechaPedido: '',
        pasoActual: 1,
        disponibilidad: null,
        _dispReqId: 0,
    };

    // ============================================================
    // NAVEGACIÓN ENTRE PASOS
    // ============================================================
    function actualizarIndicadorPaso(indicadorActivo) {
        document.querySelectorAll('[id^="indicador-paso-"]').forEach((el, i) => {
            const indicadorNum = i + 1;
            el.classList.remove('activo', 'completado');
            if (indicadorNum < indicadorActivo) el.classList.add('completado');
            if (indicadorNum === indicadorActivo) el.classList.add('activo');
        });
    }

    function mostrarPaso(num) {
        document.querySelectorAll('.paso').forEach(p => p.classList.remove('activo'));

        let pasoId = 'paso-' + num;
        if (num === 3) pasoId = 'paso-3-simple';
        document.getElementById(pasoId)?.classList.add('activo');

        // Mapeo paso→indicador: 1→1, 3→2, 4→3
        actualizarIndicadorPaso(num === 1 ? 1 : num === 3 ? 2 : 3);

        estado.pasoActual = num;
        window.scrollTo({ top: 0, behavior: 'smooth' });
        if (num === 4) initializarPaso4();
    }

    function irAPaso(num) {
        mostrarPaso(num);
    }

    function abrirPersonalizado() {
        document.querySelectorAll('.paso').forEach(p => p.classList.remove('activo'));
        document.getElementById('paso-3-personalizado').classList.add('activo');
        actualizarIndicadorPaso(2);
        estado.pasoActual = 3;
        window.scrollTo({ top: 0, behavior: 'smooth' });
    }

    function volverDesdeProducto(origen) {
        if (estado.pedidosAcumulados.length > 0) {
            mostrarResumenAcumulado();
        } else if (origen === 'personalizado') {
            mostrarPaso(3);
        } else {
            irAPaso(1);
        }
    }

    // ============================================================
    // ACUMULADOR DE PEDIDOS (combinar combos + personalizados)
    // ============================================================
    function agregarComboAlPedido() {
        const totalUnidades = Object.values(estado.carrito).reduce((s, c) => s + c.cantidad, 0);
        if (totalUnidades === 0) {
            alert('Por favor seleccioná al menos un combo');
            return;
        }
        const items = Object.entries(estado.carrito).map(([id, c]) => ({
            tipo: 'combo', id: parseInt(id), nombre: c.nombre, cantidad: c.cantidad,
            unidades: c.unidades || 1, precioEf: c.precioEf, precioTr: c.precioTr
        }));
        estado.pedidosAcumulados.push(...items);

        // Reset carrito de combos
        estado.carrito = {};
        document.querySelectorAll('[id^="cant-carrito-"]').forEach(el => el.textContent = '0');
        document.getElementById('resumen-carrito')?.classList.add('hidden');

        mostrarResumenAcumulado();
    }

    function agregarPersonalizadoAlPedido() {
        const totalSabores = Object.values(estado.sabores).reduce((a, b) => a + b, 0);
        if (totalSabores === 0) {
            alert('Por favor elegí al menos un sabor');
            return;
        }

        estado.pedidosAcumulados.push({
            tipo: 'personalizado',
            elegidos_cantidad: totalSabores,
            sabores: { ...estado.sabores }
        });

        // Reset selector de sabores
        estado.sabores = {};
        document.querySelectorAll('[id^="cant-sabor-"]').forEach(el => el.textContent = '0');
        document.getElementById('contador-planchas').textContent = '0';
        document.getElementById('precio-elegidos-preview')?.classList.add('hidden');

        mostrarResumenAcumulado();
    }

    function precioDeSabores(sabores, formaPago) {
        let planchasComun = 0, planchasPremium = 0;
        for (const [id, cant] of Object.entries(sabores)) {
            if (cant > 0) {
                const p = cant / 8;
                if (SABORES_PREMIUM.includes(id)) planchasPremium += p; else planchasComun += p;
            }
        }
        let precio = 0;
        if (planchasPremium > 0) precio += TABLA_PERSONALIZADO.premium[planchasPremium] ?? planchasPremium * (TABLA_PERSONALIZADO.premium[1] ?? 9000);
        if (planchasComun  > 0) precio += TABLA_PERSONALIZADO.elegidos[planchasComun]  ?? planchasComun  * (TABLA_PERSONALIZADO.elegidos[1] ?? 5400);
        if (formaPago === 'Efectivo') {
            const descuento = Math.floor((planchasPremium + planchasComun) / 3) * 1000;
            precio = Math.max(0, precio - descuento);
        }
        return precio;
    }

    const NOMBRES_SABORES = {
        jamon_queso:'Jamón y Queso', lechuga:'Lechuga', tomate:'Tomate', huevo:'Huevo',
        choclo:'Choclo', aceitunas:'Aceitunas', zanahoria_queso:'Zanahoria y Queso', zanahoria_huevo:'Zanahoria y Huevo',
        anana:'Ananá', atun:'Atún', berenjena:'Berenjena', jamon_crudo:'Jamón Crudo', morron:'Morrón',
        palmito:'Palmito', panceta:'Panceta', pollo:'Pollo', roquefort:'Roquefort', salame:'Salame'
    };

    function nombreDeSabores(sabores) {
        return Object.entries(sabores).filter(([,c]) => c > 0)
            .map(([id, c]) => `${c / 8}pl ${NOMBRES_SABORES[id] || id}`).join(', ');
    }

    function itemPrecio(item, formaPago) {
        formaPago = formaPago || 'Transferencia';
        if (item.tipo === 'combo') {
            const precioUnit = formaPago === 'Efectivo' ? item.precioEf : item.precioTr;
            return item.cantidad * precioUnit;
        }
        return precioDeSabores(item.sabores, formaPago);
    }

    function itemNombre(item) {
        return item.tipo === 'combo' ? `${item.cantidad}x ${item.nombre}` : `${item.elegidos_cantidad} Surtidos Elegidos`;
    }

    function mostrarResumenAcumulado() {
        document.querySelectorAll('.paso').forEach(p => p.classList.remove('activo'));
        document.getElementById('paso-resumen-acumulado').classList.add('activo');
        renderListaAcumulados();
        actualizarIndicadorPaso(2);
        estado.pasoActual = 3;
        window.scrollTo({ top: 0, behavior: 'smooth' });
    }

    function renderListaAcumulados() {
        const cont = document.getElementById('lista-acumulados');
        let total = 0;
        cont.innerHTML = estado.pedidosAcumulados.map((item, idx) => {
            const precio = itemPrecio(item);
            total += precio;
            const detalle = item.tipo === 'personalizado' ? nombreDeSabores(item.sabores) : '';
            return `<div class="bg-white border-2 border-gray-200 rounded-xl p-3 flex justify-between items-start gap-2">
                <div class="flex-1 min-w-0">
                    <div class="font-bold text-gray-900">${itemNombre(item)}</div>
                    ${detalle ? `<div class="text-xs text-gray-500 mt-0.5">${detalle}</div>` : ''}
                </div>
                <div class="flex items-center gap-2 flex-shrink-0">
                    <span class="font-bold text-green-600 text-sm">${formatPrecio(precio)}</span>
                    <button type="button" onclick="quitarAcumulado(${idx})" class="text-red-400 hover:text-red-600 w-7 h-7 flex items-center justify-center">
                        <i class="fas fa-trash"></i>
                    </button>
                </div>
            </div>`;
        }).join('') || '<div class="text-center text-gray-400 py-6 text-sm">Todavía no agregaste nada</div>';
        document.getElementById('total-acumulado-display').textContent = formatPrecio(total);
    }

    function quitarAcumulado(idx) {
        estado.pedidosAcumulados.splice(idx, 1);
        renderListaAcumulados();
    }

    function continuarAEntrega() {
        if (estado.pedidosAcumulados.length === 0) {
            alert('Agregá al menos un producto a tu pedido');
            return;
        }
        actualizarDisplayResumen();
        mostrarPaso(4);
    }

    // ============================================================
    // VALIDACIONES DE PASOS
    // ============================================================
    function validarTelefono(raw) {
        const digits = raw.replace(/\D/g, '');
        if (digits.length < 8 || digits.length > 13) {
            return 'Ingresá un teléfono válido (ej: 221 123-4567 o 11 5981-3546)';
        }
        // Todos los dígitos iguales: 11111111, 00000000, etc.
        if (/^(.)\1+$/.test(digits)) {
            return 'Ingresá un número de teléfono real';
        }
        // Secuencias obvias
        if (['12345678','123456789','1234567890','0987654321','87654321'].includes(digits)) {
            return 'Ingresá un número de teléfono real';
        }
        return null;
    }

    function irAPaso(num) {
        if (num === 3) {
            const nombre   = document.getElementById('campo_nombre').value.trim();
            const apellido = document.getElementById('campo_apellido').value.trim();
            const telefono = document.getElementById('campo_telefono').value.trim();
            if (!nombre || !apellido || !telefono) {
                alert('Por favor completá nombre, apellido y teléfono');
                return;
            }
            const errTel = validarTelefono(telefono);
            if (errTel) { alert(errTel); return; }
        }
        mostrarPaso(num);
    }

    // ============================================================
    // ============================================================
    // CARRITO DE COMBOS CLÁSICOS
    // ============================================================
    function cambiarCantidadCarrito(id, nombre, precioEf, precioTr, delta, unidades) {
        unidades = unidades || 1;
        const actual = estado.carrito[id]?.cantidad ?? 0;
        const nueva  = Math.max(0, Math.min(10, actual + delta));

        if (nueva === 0) {
            delete estado.carrito[id];
        } else {
            estado.carrito[id] = { nombre, cantidad: nueva, precioEf, precioTr, unidades };
        }

        // Actualizar contador en la card
        document.getElementById('cant-carrito-' + id).textContent = nueva;
        const card = document.getElementById('card-prod-' + id);
        if (nueva > 0) {
            card.classList.add('border-orange-400', 'bg-orange-50');
            card.classList.remove('border-gray-200');
        } else {
            card.classList.remove('border-orange-400', 'bg-orange-50');
            card.classList.add('border-gray-200');
        }

        // Actualizar resumen del carrito
        const items = Object.values(estado.carrito);
        const resumenDiv = document.getElementById('resumen-carrito');
        const listaEl   = document.getElementById('lista-carrito');
        if (items.length > 0) {
            resumenDiv.classList.remove('hidden');
            listaEl.innerHTML = items.map(c =>
                `<li>• ${c.cantidad}x ${c.nombre}</li>`
            ).join('');
        } else {
            resumenDiv.classList.add('hidden');
        }

        actualizarDisplayResumen();
    }

    // ============================================================
    // ELEGIDOS / PERSONALIZADO
    // ============================================================
    const SABORES_PREMIUM = ['anana','atun','berenjena','jamon_crudo','morron','palmito','panceta','pollo','roquefort','salame'];

    function calcularPrecioElegidos() {
        const totalPlanchas = Object.values(estado.sabores).reduce((a, b) => a + b, 0) / 8;
        const precio = precioDeSabores(estado.sabores, estado.formaPago);
        estado.precioCalculadoEf = precio;
        estado.precioCalculadoTr = precio;

        // Mostrar preview de precio
        const preview = document.getElementById('precio-elegidos-preview');
        if (totalPlanchas > 0) {
            preview.classList.remove('hidden');
            document.getElementById('precio-elegidos-display').textContent = formatPrecio(precio);
            document.getElementById('precio-elegidos-trans').textContent   = '';
        } else {
            preview.classList.add('hidden');
        }
        actualizarDisplayResumen();
    }

    const MAX_PLANCHAS_PERSONALIZADO = 6;

    function cambiarSabor(saborId, delta) {
        const paso = 8; // 1 plancha = 8 sándwiches
        const maxTotal = MAX_PLANCHAS_PERSONALIZADO * 8;
        const actual = estado.sabores[saborId] || 0;
        const totalActual = Object.values(estado.sabores).reduce((a, b) => a + b, 0);

        if (delta > 0 && totalActual + paso > maxTotal) {
            alert(`Máximo ${MAX_PLANCHAS_PERSONALIZADO} planchas (${maxTotal} sándwiches) por pedido personalizado`);
            return;
        }

        const nuevo = Math.max(0, actual + delta * paso);
        estado.sabores[saborId] = nuevo;

        const el = document.getElementById('cant-sabor-' + saborId);
        if (el) el.textContent = nuevo / 8;

        const total = Object.values(estado.sabores).reduce((a, b) => a + b, 0);
        document.getElementById('contador-planchas').textContent = total / 8;
        calcularPrecioElegidos();
    }

    // ============================================================
    // TURNO, MODALIDAD, PAGO
    // ============================================================
    function seleccionarTurno(turno, fechaISO) {
        estado.turno = turno;
        estado.fechaPedido = fechaISO || estado.fechaPedido || getArgentinaDate(0);
        document.getElementById('campo_turno').value = turno;
        document.getElementById('campo_fecha_pedido').value = estado.fechaPedido;
        renderTurnos(estado.fechaPedido); // Re-renderiza con el tick de seleccionado
        actualizarDisplayResumen();
    }

    function seleccionarModalidad(modalidad) {
        if (modalidad === 'Delivery' && totalUnidadesPedido() < 24) {
            alert('El mínimo para delivery es 24 sándwiches (3 planchas). Tu pedido actual no llega al mínimo, pero podés retirarlo en el local.');
            return;
        }
        estado.modalidad = modalidad;
        document.getElementById('campo_modalidad').value = modalidad;
        document.querySelectorAll('.modalidad-card').forEach(c => c.classList.remove('seleccionado'));
        event.currentTarget.classList.add('seleccionado');
        const bloqueDir   = document.getElementById('bloque-direccion');
        const bloqueFecha = document.getElementById('bloque-fecha');

        // Siempre mostrar selector de fecha
        bloqueFecha.classList.remove('hidden');
        if (!estado.fechaPedido) {
            estado.fechaPedido = getArgentinaDate(0);
            document.getElementById('campo_fecha_pedido').value = estado.fechaPedido;
        }
        generarFechas();

        if (modalidad === 'Delivery') {
            bloqueDir.classList.remove('hidden');
            document.getElementById('hint-fecha').innerHTML =
                '<i class="fas fa-info-circle mr-1"></i>Los turnos se actualizan según el día y la hora de corte de pedidos';
        } else {
            bloqueDir.classList.add('hidden');
            ['dir_calle', 'dir_numero', 'dir_localidad', 'dir_entre_calles'].forEach(id => {
                const el = document.getElementById(id);
                if (el) el.value = '';
            });
            document.getElementById('campo_direccion').value = '';
            document.getElementById('hint-fecha').innerHTML =
                '<i class="fas fa-info-circle mr-1"></i>Los turnos disponibles se actualizan según el día seleccionado';
        }

        estado.turno = '';
        document.getElementById('campo_turno').value = '';
        estado.disponibilidad = null;
        cargarDisponibilidad(estado.fechaPedido);
    }

    function seleccionarPago(tipo, el) {
        estado.formaPago = tipo;
        document.getElementById('campo_forma_pago').value = tipo;
        document.querySelectorAll('.pago-card').forEach(c => c.classList.remove('seleccionado'));
        el.classList.add('seleccionado');
        actualizarDisplayResumen();
    }

    // ============================================================
    // RESUMEN
    // ============================================================
    function formatPrecio(n) {
        return '$' + Math.round(n).toLocaleString('es-AR');
    }

    function totalUnidadesPedido() {
        return estado.pedidosAcumulados.reduce((sum, item) => {
            return sum + (item.tipo === 'combo' ? item.cantidad * (item.unidades || 1) : item.elegidos_cantidad);
        }, 0);
    }

    function actualizarDisplayResumen() {
        const items = estado.pedidosAcumulados;
        const nombre = items.map(itemNombre).join(' + ');
        const precio = items.reduce((s, item) => s + itemPrecio(item, estado.formaPago), 0);

        const resumen = document.getElementById('resumen-pedido');
        if (nombre && estado.turno) {
            resumen.classList.remove('hidden');
            document.getElementById('resumen-producto').textContent  = nombre;
            document.getElementById('resumen-turno').textContent     = estado.turno;
            document.getElementById('resumen-pago').textContent      = estado.formaPago;
            document.getElementById('resumen-modalidad').textContent = estado.modalidad === 'Delivery' ? 'Reparto' : estado.modalidad;

            const filaFecha = document.getElementById('resumen-fila-fecha');
            if (estado.fechaPedido) {
                const [y, m, d] = estado.fechaPedido.split('-');
                document.getElementById('resumen-fecha').textContent = `${d}/${m}/${y}`;
                filaFecha?.classList.remove('hidden');
            } else {
                filaFecha?.classList.add('hidden');
            }

            if (precio > 0) {
                document.getElementById('resumen-fila-precio').classList.remove('hidden');
                document.getElementById('resumen-precio').textContent = formatPrecio(precio);
            }
        }
    }


    // ============================================================
    // ENVÍO DEL FORMULARIO
    // ============================================================
    function enviarFormulario(e) {
        if (estado.pedidosAcumulados.length === 0) {
            alert('Tu pedido está vacío');
            e.preventDefault();
            return false;
        }
        if (!document.getElementById('campo_turno').value) {
            alert('Por favor seleccioná un turno');
            e.preventDefault();
            return false;
        }
        if (estado.modalidad === 'Delivery') {
            if (totalUnidadesPedido() < 24) {
                alert('El mínimo para delivery es 24 sándwiches (3 planchas)');
                e.preventDefault();
                return false;
            }
            if (!document.getElementById('campo_fecha_pedido').value) {
                alert('Seleccioná la fecha de entrega');
                e.preventDefault();
                return false;
            }
            const calle       = document.getElementById('dir_calle')?.value.trim();
            const numero      = document.getElementById('dir_numero')?.value.trim();
            const localidad   = document.getElementById('dir_localidad')?.value.trim();
            const entrecalles = document.getElementById('dir_entre_calles')?.value.trim();
            if (!calle || !numero || !localidad || !entrecalles) {
                alert('Ingresá calle, número, localidad y entre calles para el delivery');
                e.preventDefault();
                return false;
            }

            let dirCompuesta = `${calle} ${numero}, ${localidad} (entre ${entrecalles})`;
            document.getElementById('campo_direccion').value = dirCompuesta;
        }

        // Armar el payload combinado con todos los ítems acumulados
        const pedidosPayload = estado.pedidosAcumulados.map(item => {
            if (item.tipo === 'combo') {
                return { tipo: 'combo', id: item.id, cantidad: item.cantidad };
            }
            return { tipo: 'personalizado', elegidos_cantidad: item.elegidos_cantidad, sabores: item.sabores };
        });
        document.getElementById('campo_pedidos_json').value = JSON.stringify(pedidosPayload);

        const btn = document.getElementById('btn-confirmar');
        btn.disabled = true;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Enviando...';
        return true;
    }

    // ============================================================
    // PWA - INSTALACIÓN
    // ============================================================
    let deferredPrompt = null;

    window.addEventListener('beforeinstallprompt', (e) => {
        e.preventDefault();
        deferredPrompt = e;
        // Mostrar banner solo si no está en PWA
        if (!window.matchMedia('(display-mode: standalone)').matches) {
            document.getElementById('bannerInstalarApp').classList.remove('hidden');
        }
    });

    function instalarApp() {
        if (deferredPrompt) {
            deferredPrompt.prompt();
            deferredPrompt.userChoice.then((choiceResult) => {
                deferredPrompt = null;
                document.getElementById('bannerInstalarApp').remove();
            });
        } else {
            // Instrucciones manuales para iOS
            alert('Para instalar: tocá el botón Compartir (□↑) y luego "Agregar a pantalla de inicio"');
        }
    }

    // Registrar Service Worker
    if ('serviceWorker' in navigator) {
        window.addEventListener('load', () => {
            navigator.serviceWorker.register('/pedido_online/sw.js')
                .then(reg => console.log('SW registrado'))
                .catch(err => console.log('SW error:', err));
        });
    }
    </script>

</body>
</html>
<?php
// Enviar a Google Sheets en background (sin bloquear al usuario)
if (isset($sheets_pedido_id) && isset($sheets_data_online)) {
    if (function_exists('fastcgi_finish_request')) {
        fastcgi_finish_request();
    }
    require_once '../google_sheets_helper.php';
    enviarPedidoASheets($sheets_pedido_id, $sheets_data_online, 'online');
}
?>
