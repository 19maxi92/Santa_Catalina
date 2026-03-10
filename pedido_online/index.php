<?php
/**
 * Formulario público de pedidos online - Versión mejorada tipo pedido express
 * Los clientes acceden vía link directo / acceso desde pantalla de inicio (PWA)
 */
require_once '../admin/config.php';

$pdo = getConnection();

// Obtener configuración de turnos activos
$stmt = $pdo->query("
    SELECT * FROM config_pedidos_online
    WHERE activo = 1
    ORDER BY FIELD(turno, 'Mañana', 'Siesta', 'Tarde')
");
$turnos_disponibles = $stmt->fetchAll();

// JSON de config de turnos para JS (incluye minutos de corte)
$turnos_config_json = json_encode(array_values(array_map(fn($t) => [
    'turno'              => $t['turno'],
    'hora_inicio'        => substr($t['hora_inicio'], 0, 5),
    'minutos_antes_corte'=> (int)($t['minutos_antes_corte'] ?? 30),
    'stock_actual'       => (int)$t['stock_actual'],
    'activo'             => (bool)$t['activo'],
], $turnos_disponibles)));

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

// Sabores disponibles — idénticos al pedido express del admin
$sabores_disponibles = [
    // Comunes
    ['id' => 'jamon_queso',     'nombre' => 'Jamón y Queso',    'emoji' => '🧀', 'tipo' => 'comun'],
    ['id' => 'lechuga',         'nombre' => 'Lechuga',           'emoji' => '🥬', 'tipo' => 'comun'],
    ['id' => 'tomate',          'nombre' => 'Tomate',            'emoji' => '🍅', 'tipo' => 'comun'],
    ['id' => 'huevo',           'nombre' => 'Huevo',             'emoji' => '🥚', 'tipo' => 'comun'],
    ['id' => 'choclo',          'nombre' => 'Choclo',            'emoji' => '🌽', 'tipo' => 'comun'],
    ['id' => 'aceitunas',       'nombre' => 'Aceitunas',         'emoji' => '🫒', 'tipo' => 'comun'],
    ['id' => 'zanahoria_queso', 'nombre' => 'Zanahoria y Queso', 'emoji' => '🥕', 'tipo' => 'comun'],
    ['id' => 'zanahoria_huevo', 'nombre' => 'Zanahoria y Huevo', 'emoji' => '🥕', 'tipo' => 'comun'],
    // Premium
    ['id' => 'anana',       'nombre' => 'Ananá',       'emoji' => '🍍', 'tipo' => 'premium'],
    ['id' => 'atun',        'nombre' => 'Atún',        'emoji' => '🐟', 'tipo' => 'premium'],
    ['id' => 'berenjena',   'nombre' => 'Berenjena',   'emoji' => '🍆', 'tipo' => 'premium'],
    ['id' => 'jamon_crudo', 'nombre' => 'Jamón Crudo', 'emoji' => '🥓', 'tipo' => 'premium'],
    ['id' => 'morron',      'nombre' => 'Morrón',      'emoji' => '🌶️', 'tipo' => 'premium'],
    ['id' => 'palmito',     'nombre' => 'Palmito',     'emoji' => '🌿', 'tipo' => 'premium'],
    ['id' => 'panceta',     'nombre' => 'Panceta',     'emoji' => '🥓', 'tipo' => 'premium'],
    ['id' => 'pollo',       'nombre' => 'Pollo',       'emoji' => '🍗', 'tipo' => 'premium'],
    ['id' => 'roquefort',   'nombre' => 'Roquefort',   'emoji' => '🧀', 'tipo' => 'premium'],
    ['id' => 'salame',      'nombre' => 'Salame',      'emoji' => '🍕', 'tipo' => 'premium'],
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
        $email        = trim($_POST['email'] ?? '');
        $turno        = trim($_POST['turno'] ?? '');
        $tipo_pedido  = trim($_POST['tipo_pedido'] ?? 'simple');
        $producto_id  = (int)($_POST['producto_id'] ?? 0);
        $cantidad     = (int)($_POST['cantidad'] ?? 1);
        $forma_pago    = trim($_POST['forma_pago'] ?? '');
        $modalidad     = trim($_POST['modalidad'] ?? 'Retiro');
        $direccion     = trim($_POST['direccion'] ?? '');
        $fecha_pedido  = trim($_POST['fecha_pedido'] ?? '');
        $observaciones = trim($_POST['observaciones'] ?? '');

        // Validaciones
        if (empty($nombre) || empty($apellido) || empty($telefono)) {
            throw new Exception('Por favor completá nombre, apellido y teléfono');
        }
        if (strlen($telefono) < 8) {
            throw new Exception('Ingresá un teléfono válido');
        }
        if (empty($email)) {
            throw new Exception('Por favor ingresá tu email');
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new Exception('Ingresá un email válido (ej: juan@gmail.com)');
        }
        if (empty($turno)) {
            throw new Exception('Por favor seleccioná un turno');
        }
        if (empty($forma_pago)) {
            throw new Exception('Por favor seleccioná la forma de pago');
        }
        if ($modalidad === 'Delivery' && empty($direccion)) {
            throw new Exception('Si elegís Delivery, ingresá la dirección de entrega');
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

        // Verificar stock del turno
        $stmt = $pdo->prepare("SELECT * FROM config_pedidos_online WHERE turno = ? AND activo = 1");
        $stmt->execute([$turno]);
        $config_turno = $stmt->fetch();

        if (!$config_turno) {
            throw new Exception('El turno seleccionado no está disponible');
        }
        if ($config_turno['stock_actual'] <= 0) {
            throw new Exception('¡Lo sentimos! No hay cupos disponibles para el turno seleccionado. Elegí otro turno.');
        }

        // Validar corte de horario server-side (zona horaria Argentina)
        $tz_ar = new DateTimeZone('America/Argentina/Buenos_Aires');
        $fecha_para_turno = !empty($fecha_pedido) ? $fecha_pedido : date('Y-m-d');
        $minutos_corte = (int)($config_turno['minutos_antes_corte'] ?? 30);
        $turno_start = new DateTime($fecha_para_turno . ' ' . $config_turno['hora_inicio'], $tz_ar);
        $cutoff = clone $turno_start;
        $cutoff->modify("-{$minutos_corte} minutes");
        $now_ar = new DateTime('now', $tz_ar);
        if ($now_ar >= $cutoff) {
            throw new Exception('Ya no se pueden tomar pedidos para ese turno. El plazo de pedido venció.');
        }

        $precio = 0;
        $nombre_producto = '';
        $cantidad_sandwiches = 0;
        $fecha_entrega = !empty($fecha_pedido) ? $fecha_pedido : date('Y-m-d');
        $obs_interna = "🌐 PEDIDO ONLINE\nTurno: {$turno}\nEmail: {$email}";
        if ($modalidad === 'Delivery' && !empty($fecha_pedido)) {
            $obs_interna .= "\nFecha entrega: " . date('d/m/Y', strtotime($fecha_pedido));
        }

        if ($tipo_pedido === 'personalizado') {
            // Pedido de elegidos con sabores
            $elegidos_cantidad = (int)($_POST['elegidos_cantidad'] ?? 0);
            $elegidos_prod_id  = (int)($_POST['elegidos_prod_id'] ?? 0);
            $sabores_json      = $_POST['sabores_json'] ?? '{}';

            if ($elegidos_prod_id === 0) {
                throw new Exception('Seleccioná la cantidad de elegidos');
            }

            $stmt = $pdo->prepare("SELECT * FROM productos WHERE id = ? AND activo = 1");
            $stmt->execute([$elegidos_prod_id]);
            $prod_elegido = $stmt->fetch();

            if (!$prod_elegido) {
                throw new Exception('Producto no válido');
            }

            $precio = ($forma_pago === 'Efectivo')
                ? (float)$prod_elegido['precio_efectivo']
                : (float)$prod_elegido['precio_transferencia'];

            $nombre_producto = $prod_elegido['nombre'];
            $cantidad_sandwiches = $elegidos_cantidad;

            // Decodificar sabores
            $sabores = json_decode($sabores_json, true) ?? [];
            $total_sabores = array_sum($sabores);

            if ($total_sabores === 0) {
                throw new Exception('Elegí al menos un sabor para tu pedido personalizado');
            }

            // Construir lista de sabores
            $lista_sabores = [];
            foreach ($sabores as $sabor_id => $cant_sabor) {
                if ($cant_sabor > 0) {
                    $sabor_info = array_filter($sabores_disponibles, fn($s) => $s['id'] === $sabor_id);
                    $sabor_info = array_values($sabor_info);
                    if (!empty($sabor_info)) {
                        $lista_sabores[] = "{$cant_sabor}x {$sabor_info[0]['nombre']}";
                    }
                }
            }

            $obs_interna .= "\n\n🎨 Pedido Personalizado\nSabores: " . implode(', ', $lista_sabores);
            $obs_interna .= "\n[Datos sabores: " . $sabores_json . "]";

        } else {
            // Pedido simple
            if ($producto_id === 0) {
                throw new Exception('Seleccioná un producto');
            }

            $stmt = $pdo->prepare("SELECT * FROM productos WHERE id = ? AND activo = 1");
            $stmt->execute([$producto_id]);
            $producto = $stmt->fetch();

            if (!$producto) {
                throw new Exception('Producto no válido');
            }

            $precio = ($forma_pago === 'Efectivo')
                ? (float)$producto['precio_efectivo'] * $cantidad
                : (float)$producto['precio_transferencia'] * $cantidad;

            $nombre_producto = $producto['nombre'];
            $cantidad_sandwiches = $cantidad;
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
                estado, observaciones, fecha_entrega,
                created_at, fecha_display
            ) VALUES (
                ?, ?, ?, ?,
                ?, ?, ?,
                ?, ?, 'Local 1',
                'Pendiente', ?, ?,
                NOW(), ?
            )
        ");

        $stmt->execute([
            $nombre, $apellido, $telefono, $direccion,
            $nombre_producto, $cantidad_sandwiches, $precio,
            $modalidad, $forma_pago,
            $obs_interna, $fecha_entrega,
            $fecha_display
        ]);

        $pedido_id = $pdo->lastInsertId();

        // Descontar del stock del turno
        $pdo->prepare("UPDATE config_pedidos_online SET stock_actual = stock_actual - 1 WHERE turno = ?")
            ->execute([$turno]);

        $pedido_confirmado = [
            'id'          => $pedido_id,
            'nombre'      => $nombre,
            'turno'       => $turno,
            'producto'    => $nombre_producto,
            'precio'      => $precio,
            'modalidad'   => $modalidad,
            'forma_pago'  => $forma_pago,
            'fecha_pedido'=> $fecha_entrega,
        ];

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
                <h2 class="text-3xl font-black mb-2">¡Pedido Confirmado!</h2>
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
                        <span class="font-bold"><?= htmlspecialchars($pedido_confirmado['modalidad']) ?></span>
                    </div>
                    <?php if ($pedido_confirmado['modalidad'] === 'Delivery' && !empty($pedido_confirmado['fecha_pedido'])): ?>
                    <div class="flex justify-between items-center">
                        <span class="text-gray-600 font-medium">Fecha entrega</span>
                        <span class="font-bold text-blue-700"><?= date('d/m/Y', strtotime($pedido_confirmado['fecha_pedido'])) ?></span>
                    </div>
                    <?php endif; ?>
                    <div class="flex justify-between items-center">
                        <span class="text-gray-600 font-medium">Pago</span>
                        <span class="font-bold"><?= htmlspecialchars($pedido_confirmado['forma_pago']) ?></span>
                    </div>
                    <div class="flex justify-between items-center border-t border-gray-200 pt-2 mt-1">
                        <span class="text-gray-700 font-bold">Total</span>
                        <span class="font-black text-green-700 text-lg">$<?= number_format($pedido_confirmado['precio'], 0, ',', '.') ?></span>
                    </div>
                </div>

                <div class="bg-orange-50 border border-orange-200 rounded-xl p-4 text-center">
                    <?php if ($pedido_confirmado['modalidad'] === 'Delivery'): ?>
                        <p class="text-orange-800 font-semibold text-lg mb-1">
                            🛵 ¡Pronto estaremos entregando tu pedido!
                        </p>
                        <p class="text-orange-600 text-sm">
                            Coordinamos el horario exacto de entrega por WhatsApp. ¡Gracias por tu pedido!
                        </p>
                    <?php else: ?>
                        <p class="text-orange-800 font-semibold text-lg mb-1">
                            <i class="fas fa-clock mr-2"></i>¡Te esperamos en el local!
                        </p>
                        <p class="text-orange-600 text-sm mb-1">
                            Turno <strong><?= htmlspecialchars($pedido_confirmado['turno']) ?></strong>
                        </p>
                        <p class="text-orange-500 text-xs">Cno. Gral. Belgrano 7287, Juan María Gutiérrez</p>
                    <?php endif; ?>
                </div>

                <?php if ($pedido_confirmado['forma_pago'] === 'Transferencia'): ?>
                    <?php if ($pedido_confirmado['modalidad'] === 'Delivery'): ?>
                        <!-- Datos transferencia DELIVERY -->
                        <div class="bg-blue-50 border-2 border-blue-200 rounded-xl p-4">
                            <p class="font-black text-blue-800 mb-3 flex items-center">
                                🔄 Datos para transferir — Reparto
                            </p>
                            <div class="space-y-1 text-sm">
                                <div class="flex justify-between">
                                    <span class="text-gray-600">Alias CBU</span>
                                    <span class="font-black text-blue-700 tracking-wide">MIGA.SANTA.CATALINA</span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-gray-600">Banco</span>
                                    <span class="font-semibold text-gray-800">Santander</span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-gray-600">Titular</span>
                                    <span class="font-semibold text-gray-800">Bozanic Juan Ignacio</span>
                                </div>
                                <div class="flex justify-between border-t border-blue-200 pt-2 mt-1">
                                    <span class="text-gray-600 font-bold">Monto</span>
                                    <span class="font-black text-green-700">$<?= number_format($pedido_confirmado['precio'], 0, ',', '.') ?></span>
                                </div>
                            </div>
                            <div class="mt-3 bg-yellow-100 border border-yellow-300 rounded-lg p-2 text-center">
                                <p class="text-yellow-800 font-bold text-sm">📎 Enviá el comprobante por WhatsApp</p>
                            </div>
                        </div>
                    <?php else: ?>
                        <!-- Datos transferencia RETIRO -->
                        <div class="bg-blue-50 border-2 border-blue-200 rounded-xl p-4">
                            <p class="font-black text-blue-800 mb-3 flex items-center">
                                🔄 Datos para transferir — Retiro por local
                            </p>
                            <div class="space-y-1 text-sm">
                                <div class="flex justify-between">
                                    <span class="text-gray-600">Alias CBU</span>
                                    <span class="font-black text-blue-700 tracking-wide">SANTA.CATALINA.1</span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-gray-600">Banco</span>
                                    <span class="font-semibold text-gray-800">Mercado Pago</span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-gray-600">Titular</span>
                                    <span class="font-semibold text-gray-800">Bassi Eliana Melisa</span>
                                </div>
                                <div class="flex justify-between border-t border-blue-200 pt-2 mt-1">
                                    <span class="text-gray-600 font-bold">Monto</span>
                                    <span class="font-black text-green-700">$<?= number_format($pedido_confirmado['precio'], 0, ',', '.') ?></span>
                                </div>
                            </div>
                            <div class="mt-3 bg-yellow-100 border border-yellow-300 rounded-lg p-2 text-center">
                                <p class="text-yellow-800 font-bold text-sm">📎 Enviá el comprobante por WhatsApp</p>
                            </div>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>

                <div class="grid grid-cols-2 gap-3">
                    <button onclick="window.location.href='/pedido_online/index.php'"
                            class="bg-orange-500 hover:bg-orange-600 text-white py-3 rounded-xl font-bold transition-all">
                        <i class="fas fa-plus mr-2"></i>Otro pedido
                    </button>
                    <a href="https://wa.me/541159813546?text=Hice+un+pedido+online+%23<?= $pedido_confirmado['id'] ?>+y+quiero+consultar"
                       target="_blank"
                       class="bg-green-500 hover:bg-green-600 text-white py-3 rounded-xl font-bold transition-all text-center flex items-center justify-center">
                        <i class="fab fa-whatsapp mr-2"></i>Consultar
                    </a>
                </div>
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
                    $pasos = ['Datos', 'Tipo', 'Producto', 'Entrega'];
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
                <input type="hidden" name="tipo_pedido" id="campo_tipo_pedido" value="simple">
                <input type="hidden" name="producto_id" id="campo_producto_id" value="">
                <input type="hidden" name="cantidad" id="campo_cantidad" value="1">
                <input type="hidden" name="turno" id="campo_turno" value="">
                <input type="hidden" name="forma_pago" id="campo_forma_pago" value="">
                <input type="hidden" name="modalidad" id="campo_modalidad" value="Retiro">
                <input type="hidden" name="direccion" id="campo_direccion" value="">
                <input type="hidden" name="fecha_pedido" id="campo_fecha_pedido" value="">
                <input type="hidden" name="elegidos_prod_id" id="campo_elegidos_prod_id" value="">
                <input type="hidden" name="elegidos_cantidad" id="campo_elegidos_cantidad" value="">
                <input type="hidden" name="sabores_json" id="campo_sabores_json" value="{}">

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
                                   placeholder="Ej: 2604123456"
                                   class="w-full px-4 py-3 border-2 border-gray-300 rounded-xl focus:border-orange-500 focus:ring-2 focus:ring-orange-200 text-lg">
                        </div>
                        <div>
                            <label class="block text-sm font-bold text-gray-700 mb-2">
                                Email * <span class="text-gray-400 font-normal">(te avisamos cuando tu pedido esté listo)</span>
                            </label>
                            <input type="email" id="campo_email" name="email" required
                                   value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
                                   placeholder="Ej: juan@gmail.com"
                                   class="w-full px-4 py-3 border-2 border-gray-300 rounded-xl focus:border-orange-500 focus:ring-2 focus:ring-orange-200 text-lg">
                        </div>
                        <button type="button" onclick="irAPaso(2)"
                                class="w-full bg-gradient-to-r from-orange-500 to-red-500 hover:from-orange-600 hover:to-red-600 text-white py-4 rounded-xl font-black text-lg shadow transition-all mt-2">
                            Continuar <i class="fas fa-arrow-right ml-2"></i>
                        </button>
                    </div>

                    <!-- ===== PASO 2: TIPO DE PEDIDO ===== -->
                    <div id="paso-2" class="paso space-y-4">
                        <h2 class="text-xl font-black text-gray-900 mb-4">
                            <i class="fas fa-hamburger text-orange-500 mr-2"></i>¿Qué tipo de pedido?
                        </h2>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div class="tipo-card" onclick="seleccionarTipo('simple')">
                                <div class="text-5xl mb-3">🥪</div>
                                <h3 class="text-xl font-black text-gray-900 mb-2">Clásico</h3>
                                <p class="text-gray-500 text-sm">Elegí de nuestros combos armados (JyQ, Surtidos, etc.)</p>
                            </div>
                            <div class="tipo-card" onclick="seleccionarTipo('personalizado')">
                                <div class="text-5xl mb-3">🎨</div>
                                <h3 class="text-xl font-black text-gray-900 mb-2">A mi gusto</h3>
                                <p class="text-gray-500 text-sm">Elegí la cantidad y armá tu combinación de sabores</p>
                            </div>
                        </div>
                        <button type="button" onclick="irAPaso(1)"
                                class="w-full bg-gray-200 hover:bg-gray-300 text-gray-700 py-3 rounded-xl font-bold transition-all">
                            <i class="fas fa-arrow-left mr-2"></i>Volver
                        </button>
                    </div>

                    <!-- ===== PASO 3A: PRODUCTO SIMPLE ===== -->
                    <div id="paso-3-simple" class="paso space-y-4">
                        <h2 class="text-xl font-black text-gray-900 mb-4">
                            <i class="fas fa-list text-orange-500 mr-2"></i>Elegí tu combo
                        </h2>
                        <div class="space-y-3" id="lista-productos-simples">
                            <?php foreach ($productos_simples as $prod): ?>
                                <div class="producto-card p-4" onclick="seleccionarProducto(<?= $prod['id'] ?>, '<?= htmlspecialchars(addslashes($prod['nombre'])) ?>', <?= $prod['precio_efectivo'] ?>, <?= $prod['precio_transferencia'] ?>)">
                                    <div class="flex items-center justify-between">
                                        <div class="flex-1">
                                            <h3 class="font-bold text-gray-900"><?= htmlspecialchars($prod['nombre']) ?></h3>
                                            <?php if (!empty($prod['descripcion'])): ?>
                                                <p class="text-sm text-gray-500"><?= htmlspecialchars($prod['descripcion']) ?></p>
                                            <?php endif; ?>
                                            <div class="flex gap-3 mt-1">
                                                <span class="text-sm font-bold text-green-600">💵 $<?= number_format($prod['precio_efectivo'], 0, ',', '.') ?></span>
                                                <span class="text-xs text-blue-500 self-center">🏦 $<?= number_format($prod['precio_transferencia'], 0, ',', '.') ?></span>
                                            </div>
                                        </div>
                                        <div class="ml-3 flex-shrink-0 w-8 h-8 border-2 border-gray-300 rounded-full flex items-center justify-center check-icon">
                                            <i class="fas fa-check text-orange-500 hidden"></i>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>

                        <!-- Cantidad -->
                        <div id="bloque-cantidad-simple" class="hidden mt-4">
                            <label class="block text-sm font-bold text-gray-700 mb-2">Cantidad de combos</label>
                            <div class="flex items-center space-x-4">
                                <button type="button" onclick="cambiarCantidad(-1)"
                                        class="w-10 h-10 border-2 border-orange-500 rounded-lg text-orange-600 font-black text-xl hover:bg-orange-500 hover:text-white transition-all">
                                    −
                                </button>
                                <span id="display-cantidad" class="text-2xl font-black text-gray-900 w-8 text-center">1</span>
                                <button type="button" onclick="cambiarCantidad(1)"
                                        class="w-10 h-10 border-2 border-orange-500 rounded-lg text-orange-600 font-black text-xl hover:bg-orange-500 hover:text-white transition-all">
                                    +
                                </button>
                                <span class="text-gray-500 text-sm">combo(s)</span>
                            </div>
                        </div>

                        <div class="flex gap-3 mt-2">
                            <button type="button" onclick="irAPaso(2)"
                                    class="flex-1 bg-gray-200 hover:bg-gray-300 text-gray-700 py-3 rounded-xl font-bold transition-all">
                                <i class="fas fa-arrow-left mr-2"></i>Volver
                            </button>
                            <button type="button" onclick="irAPasoDesdeProducto()"
                                    class="flex-1 bg-gradient-to-r from-orange-500 to-red-500 hover:from-orange-600 hover:to-red-600 text-white py-3 rounded-xl font-black transition-all">
                                Continuar <i class="fas fa-arrow-right ml-2"></i>
                            </button>
                        </div>
                    </div>

                    <!-- ===== PASO 3B: ELEGIDOS / PERSONALIZADO ===== -->
                    <div id="paso-3-personalizado" class="paso space-y-4">
                        <h2 class="text-xl font-black text-gray-900 mb-4">
                            <i class="fas fa-sliders-h text-orange-500 mr-2"></i>Armá tu pedido
                        </h2>

                        <!-- Selector de cantidad de elegidos -->
                        <div>
                            <label class="block text-sm font-bold text-gray-700 mb-1">¿Cuántos sándwiches querés?</label>
                            <p class="text-xs text-orange-600 mb-3">Los pedidos elegidos se arman en planchas de 8 unidades</p>
                            <div class="grid grid-cols-3 gap-2" id="grid-elegidos">
                                <?php
                                $opciones_elegidos = [
                                    8  => $precio_elegido_8,
                                    16 => $precio_elegido_16,
                                    24 => $precio_elegido_24,
                                    32 => $precio_elegido_32,
                                    40 => $precio_elegido_40,
                                    48 => $precio_elegido_48,
                                ];
                                foreach ($opciones_elegidos as $cant => $prod_e):
                                    if ($prod_e):
                                    $planchas = $cant / 8;
                                ?>
                                    <button type="button"
                                            class="elegido-qty-btn border-2 border-gray-300 rounded-xl p-3 text-center hover:border-orange-500 hover:bg-orange-50 transition-all"
                                            onclick="seleccionarElegidos(<?= $cant ?>, <?= $prod_e['id'] ?>, <?= $prod_e['precio_efectivo'] ?>, <?= $prod_e['precio_transferencia'] ?>, '<?= htmlspecialchars(addslashes($prod_e['nombre'])) ?>')">
                                        <div class="text-2xl font-black text-gray-900"><?= $cant ?></div>
                                        <div class="text-xs text-gray-500"><?= $planchas ?> plancha<?= $planchas > 1 ? 's' : '' ?></div>
                                        <div class="text-xs font-bold text-green-600 mt-1">$<?= number_format($prod_e['precio_efectivo'], 0, ',', '.') ?></div>
                                        <div class="text-xs text-blue-400">Trans: $<?= number_format($prod_e['precio_transferencia'], 0, ',', '.') ?></div>
                                    </button>
                                <?php endif; endforeach; ?>
                            </div>
                        </div>

                        <!-- Selector de sabores -->
                        <div id="bloque-sabores" class="hidden">
                            <div class="flex items-center justify-between mb-3">
                                <label class="text-sm font-bold text-gray-700">
                                    Elegí tus sabores <span class="text-orange-500 font-normal text-xs">(de a planchas de 8)</span>
                                </label>
                                <span class="text-sm font-bold text-orange-600 bg-orange-50 border border-orange-200 rounded-lg px-2 py-1">
                                    <span id="contador-planchas">0</span>/<span id="max-planchas">0</span> planchas
                                </span>
                            </div>

                            <!-- COMUNES -->
                            <p class="text-xs font-bold text-green-700 mb-2 mt-1">🟢 SABORES COMUNES</p>
                            <div class="grid grid-cols-2 gap-2 mb-4">
                                <?php foreach (array_filter($sabores_disponibles, fn($s) => $s['tipo'] === 'comun') as $sabor): ?>
                                    <div class="sabor-btn flex items-center justify-between border-green-200 hover:border-green-500 hover:bg-green-50"
                                         id="sabor-<?= $sabor['id'] ?>">
                                        <div class="flex items-center">
                                            <span class="text-lg mr-2"><?= $sabor['emoji'] ?></span>
                                            <span class="text-xs font-medium text-gray-700 leading-tight"><?= htmlspecialchars($sabor['nombre']) ?></span>
                                        </div>
                                        <div class="flex items-center space-x-1">
                                            <button type="button" onclick="cambiarSabor('<?= $sabor['id'] ?>', -1)"
                                                    class="w-6 h-6 border border-green-300 rounded text-green-700 font-bold text-xs hover:bg-green-500 hover:text-white hover:border-green-500 transition-all">−</button>
                                            <span id="cant-sabor-<?= $sabor['id'] ?>" class="w-6 text-center font-bold text-gray-900 text-sm">0</span>
                                            <button type="button" onclick="cambiarSabor('<?= $sabor['id'] ?>', 1)"
                                                    class="w-6 h-6 border border-green-300 rounded text-green-700 font-bold text-xs hover:bg-green-500 hover:text-white hover:border-green-500 transition-all">+</button>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>

                            <!-- PREMIUM -->
                            <p class="text-xs font-bold text-orange-600 mb-2">🟠 SABORES PREMIUM</p>
                            <div class="grid grid-cols-2 gap-2">
                                <?php foreach (array_filter($sabores_disponibles, fn($s) => $s['tipo'] === 'premium') as $sabor): ?>
                                    <div class="sabor-btn flex items-center justify-between border-orange-200 hover:border-orange-500 hover:bg-orange-50"
                                         id="sabor-<?= $sabor['id'] ?>">
                                        <div class="flex items-center">
                                            <span class="text-lg mr-2"><?= $sabor['emoji'] ?></span>
                                            <span class="text-xs font-medium text-gray-700 leading-tight"><?= htmlspecialchars($sabor['nombre']) ?></span>
                                        </div>
                                        <div class="flex items-center space-x-1">
                                            <button type="button" onclick="cambiarSabor('<?= $sabor['id'] ?>', -1)"
                                                    class="w-6 h-6 border border-orange-300 rounded text-orange-700 font-bold text-xs hover:bg-orange-500 hover:text-white hover:border-orange-500 transition-all">−</button>
                                            <span id="cant-sabor-<?= $sabor['id'] ?>" class="w-6 text-center font-bold text-gray-900 text-sm">0</span>
                                            <button type="button" onclick="cambiarSabor('<?= $sabor['id'] ?>', 1)"
                                                    class="w-6 h-6 border border-orange-300 rounded text-orange-700 font-bold text-xs hover:bg-orange-500 hover:text-white hover:border-orange-500 transition-all">+</button>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>

                        <div class="flex gap-3 mt-2">
                            <button type="button" onclick="irAPaso(2)"
                                    class="flex-1 bg-gray-200 hover:bg-gray-300 text-gray-700 py-3 rounded-xl font-bold transition-all">
                                <i class="fas fa-arrow-left mr-2"></i>Volver
                            </button>
                            <button type="button" onclick="irAPasoDesdeElegidos()"
                                    class="flex-1 bg-gradient-to-r from-orange-500 to-red-500 hover:from-orange-600 hover:to-red-600 text-white py-3 rounded-xl font-black transition-all">
                                Continuar <i class="fas fa-arrow-right ml-2"></i>
                            </button>
                        </div>
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
                                    <div class="font-bold text-gray-900">Delivery</div>
                                    <div class="text-xs text-gray-500 mt-1">Te lo llevamos</div>
                                </div>
                            </div>
                        </div>

                        <!-- 2. Fecha de entrega (solo delivery) -->
                        <div id="bloque-fecha" class="hidden">
                            <label class="block text-sm font-bold text-gray-700 mb-3">
                                <i class="fas fa-calendar text-purple-500 mr-1"></i>¿Para qué día?
                            </label>
                            <div id="opciones-fecha" class="flex gap-2 flex-wrap"></div>
                            <p class="text-xs text-gray-500 mt-2">
                                <i class="fas fa-info-circle mr-1"></i>
                                Los turnos disponibles se actualizan según el día y la hora de corte de pedidos
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
                            <div class="grid grid-cols-3 gap-2">
                                <input type="text" id="dir_calle" placeholder="Calle *"
                                       class="col-span-2 px-3 py-3 border-2 border-gray-300 rounded-xl focus:border-blue-500 focus:ring-2 focus:ring-blue-200 text-sm">
                                <input type="text" id="dir_numero" placeholder="Número *"
                                       class="px-3 py-3 border-2 border-gray-300 rounded-xl focus:border-blue-500 focus:ring-2 focus:ring-blue-200 text-sm">
                            </div>
                            <input type="text" id="dir_localidad" placeholder="Localidad *"
                                   class="w-full px-3 py-3 border-2 border-gray-300 rounded-xl focus:border-blue-500 focus:ring-2 focus:ring-blue-200 text-sm">
                            <input type="text" id="dir_entre_calles" placeholder="Entre calles (ej: Belgrano y San Martín)"
                                   class="w-full px-3 py-3 border-2 border-gray-300 rounded-xl focus:border-blue-500 focus:ring-2 focus:ring-blue-200 text-sm">
                        </div>

                        <!-- 5. Forma de pago -->
                        <div>
                            <label class="block text-sm font-bold text-gray-700 mb-3">
                                <i class="fas fa-credit-card text-green-500 mr-1"></i>Forma de pago
                            </label>
                            <div class="grid grid-cols-2 gap-3">
                                <div class="pago-card p-4 text-center" onclick="seleccionarPago('Efectivo')">
                                    <i class="fas fa-money-bill-wave text-3xl text-green-500 mb-2"></i>
                                    <div class="font-bold text-gray-900">Efectivo</div>
                                    <div class="text-xs text-gray-500 mt-1">En el momento</div>
                                </div>
                                <div class="pago-card p-4 text-center" onclick="seleccionarPago('Transferencia')">
                                    <i class="fas fa-university text-3xl text-blue-500 mb-2"></i>
                                    <div class="font-bold text-gray-900">Transferencia</div>
                                    <div class="text-xs text-gray-500 mt-1">Te pasamos el CBU</div>
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
                            <button type="button" onclick="volverAlProducto()"
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
    // CONFIG DE TURNOS (desde PHP)
    // ============================================================
    const turnosConfig = <?= $turnos_config_json ?>;

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
    // DISPONIBILIDAD DE TURNOS POR FECHA
    // ============================================================
    function turnoDisponible(cfg, fechaISO) {
        if (!cfg.activo || cfg.stock_actual <= 0) return false;
        const [h, min] = cfg.hora_inicio.split(':').map(Number);
        const [y, mo, d] = fechaISO.split('-').map(Number);
        // AR = UTC-3 → AR h:min = UTC (h+3):min
        const turnoUTC = Date.UTC(y, mo - 1, d, h + 3, min);
        const cutoffUTC = turnoUTC - cfg.minutos_antes_corte * 60000;
        return Date.now() < cutoffUTC;
    }

    function turnoMotivoBloqueo(cfg, fechaISO) {
        if (!cfg.activo) return 'No disponible';
        if (cfg.stock_actual <= 0) return 'Sin cupos';
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
        grid.innerHTML = turnosConfig.map(cfg => {
            const ok = turnoDisponible(cfg, fechaISO);
            const sel = estado.turno === cfg.turno;
            return `<div class="turno-card p-4 text-center ${!ok ? 'sin-stock' : ''} ${sel ? 'seleccionado' : ''}"
                         onclick="${ok ? `seleccionarTurno('${cfg.turno}','${fechaISO}')` : ''}">
                <div class="text-2xl font-black text-gray-900">${cfg.turno}</div>
                <div class="text-sm text-gray-500 mt-1">${cfg.hora_inicio}</div>
                <div class="mt-2 text-xs font-bold ${ok ? 'text-green-600' : 'text-red-500'}">
                    ${ok ? `✅ ${cfg.stock_actual} cupos` : `❌ ${turnoMotivoBloqueo(cfg, fechaISO)}`}
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
        // Si cambió la fecha, resetear el turno seleccionado
        if (estado.fechaPedido !== fechaISO) {
            estado.turno = '';
            document.getElementById('campo_turno').value = '';
        }
        estado.fechaPedido = fechaISO;
        document.getElementById('campo_fecha_pedido').value = fechaISO;
        generarFechas();
        renderTurnos(fechaISO);
        actualizarDisplayResumen();
    }

    // ============================================================
    // INICIALIZAR PASO 4
    // ============================================================
    function initializarPaso4() {
        // Para retiro: siempre hoy
        if (estado.modalidad !== 'Delivery' || !estado.fechaPedido) {
            estado.fechaPedido = getArgentinaDate(0);
            document.getElementById('campo_fecha_pedido').value = estado.fechaPedido;
        }
        renderTurnos(estado.fechaPedido);
        if (estado.modalidad === 'Delivery') {
            document.getElementById('bloque-fecha').classList.remove('hidden');
            generarFechas();
        } else {
            document.getElementById('bloque-fecha').classList.add('hidden');
        }
    }

    // ============================================================
    // ESTADO DEL PEDIDO
    // ============================================================
    let estado = {
        tipoPedido: 'simple',
        productoId: null,
        productoNombre: '',
        cantidad: 1,
        precioEfectivo: 0,
        precioTransferencia: 0,
        elegidosId: null,
        elegidosNombre: '',
        elegidosCantidad: 0,
        elegidosPrecioEfectivo: 0,
        elegidosPrecioTransferencia: 0,
        sabores: {},
        turno: '',
        modalidad: 'Retiro',
        formaPago: '',
        fechaPedido: '',
        pasoActual: 1,
    };

    // ============================================================
    // NAVEGACIÓN ENTRE PASOS
    // ============================================================
    function mostrarPaso(num) {
        document.querySelectorAll('.paso').forEach(p => p.classList.remove('activo'));

        let pasoId = 'paso-' + num;
        if (num === 3) {
            pasoId = estado.tipoPedido === 'personalizado' ? 'paso-3-personalizado' : 'paso-3-simple';
        }
        document.getElementById(pasoId)?.classList.add('activo');

        // Actualizar indicadores (paso 3 y 3-personalizado = indicador 3)
        document.querySelectorAll('[id^="indicador-paso-"]').forEach((el, i) => {
            const indicadorNum = i + 1;
            el.classList.remove('activo', 'completado');
            if (indicadorNum < num) el.classList.add('completado');
            if (indicadorNum === num) el.classList.add('activo');
        });

        estado.pasoActual = num;
        window.scrollTo({ top: 0, behavior: 'smooth' });
        if (num === 4) initializarPaso4();
    }

    function irAPaso(num) {
        mostrarPaso(num);
    }

    function irAPasoDesdeProducto() {
        if (!estado.productoId) {
            alert('Por favor seleccioná un producto');
            return;
        }
        document.getElementById('campo_producto_id').value = estado.productoId;
        document.getElementById('campo_cantidad').value = estado.cantidad;
        actualizarDisplayResumen();
        mostrarPaso(4);
    }

    function irAPasoDesdeElegidos() {
        if (!estado.elegidosId) {
            alert('Por favor seleccioná la cantidad de elegidos');
            return;
        }
        const totalSabores = Object.values(estado.sabores).reduce((a, b) => a + b, 0);
        if (totalSabores === 0) {
            alert('Por favor elegí al menos un sabor');
            return;
        }
        if (totalSabores !== estado.elegidosCantidad) {
            const planchasActuales  = totalSabores / 8;
            const planchasNecesarias = estado.elegidosCantidad / 8;
            alert(`Tenés ${planchasActuales} plancha${planchasActuales !== 1 ? 's' : ''} elegida${planchasActuales !== 1 ? 's' : ''} pero necesitás ${planchasNecesarias}. Ajustá los sabores.`);
            return;
        }
        document.getElementById('campo_elegidos_prod_id').value = estado.elegidosId;
        document.getElementById('campo_elegidos_cantidad').value = estado.elegidosCantidad;
        document.getElementById('campo_sabores_json').value = JSON.stringify(estado.sabores);
        actualizarDisplayResumen();
        mostrarPaso(4);
    }

    function volverAlProducto() {
        mostrarPaso(3);
    }

    // ============================================================
    // VALIDACIONES DE PASOS
    // ============================================================
    function irAPaso(num) {
        if (num === 2) {
            // Validar paso 1
            const nombre   = document.getElementById('campo_nombre').value.trim();
            const apellido = document.getElementById('campo_apellido').value.trim();
            const telefono = document.getElementById('campo_telefono').value.trim();
            const email    = document.getElementById('campo_email').value.trim();
            if (!nombre || !apellido || !telefono) {
                alert('Por favor completá todos tus datos');
                return;
            }
            if (telefono.length < 8) {
                alert('Ingresá un teléfono válido');
                return;
            }
            if (!email) {
                alert('Por favor ingresá tu email');
                return;
            }
            const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            if (!emailRegex.test(email)) {
                alert('Ingresá un email válido (ej: juan@gmail.com)');
                return;
            }
        }
        mostrarPaso(num);
    }

    // ============================================================
    // SELECCIÓN DE TIPO
    // ============================================================
    function seleccionarTipo(tipo) {
        estado.tipoPedido = tipo;
        document.getElementById('campo_tipo_pedido').value = tipo;
        // Reset estado del producto
        estado.productoId = null;
        estado.elegidosId = null;
        estado.sabores = {};
        document.querySelectorAll('.tipo-card').forEach(c => c.classList.remove('seleccionado'));
        event.currentTarget.classList.add('seleccionado');
        setTimeout(() => mostrarPaso(3), 200);
    }

    // ============================================================
    // PRODUCTOS SIMPLES
    // ============================================================
    function seleccionarProducto(id, nombre, precioEf, precioTrans) {
        estado.productoId = id;
        estado.productoNombre = nombre;
        estado.precioEfectivo = precioEf;
        estado.precioTransferencia = precioTrans;
        estado.cantidad = 1;
        document.getElementById('display-cantidad').textContent = 1;

        document.querySelectorAll('.producto-card').forEach(c => c.classList.remove('seleccionado'));
        event.currentTarget.closest('.producto-card').classList.add('seleccionado');

        document.getElementById('bloque-cantidad-simple').classList.remove('hidden');
        actualizarDisplayResumen();
    }

    function cambiarCantidad(delta) {
        estado.cantidad = Math.max(1, Math.min(10, estado.cantidad + delta));
        document.getElementById('display-cantidad').textContent = estado.cantidad;
        document.getElementById('campo_cantidad').value = estado.cantidad;
        actualizarDisplayResumen();
    }

    // ============================================================
    // ELEGIDOS / PERSONALIZADO
    // ============================================================
    function seleccionarElegidos(cantidad, prodId, precioEf, precioTrans, nombre) {
        estado.elegidosId = prodId;
        estado.elegidosCantidad = cantidad;
        estado.elegidosPrecioEfectivo = precioEf;
        estado.elegidosPrecioTransferencia = precioTrans;
        estado.elegidosNombre = nombre;
        estado.sabores = {};

        // Actualizar UI botones
        document.querySelectorAll('.elegido-qty-btn').forEach(b => {
            b.classList.remove('border-orange-500', 'bg-orange-50');
            b.classList.add('border-gray-300');
        });
        event.currentTarget.classList.add('border-orange-500', 'bg-orange-50');
        event.currentTarget.classList.remove('border-gray-300');

        // Resetear sabores
        document.querySelectorAll('[id^="cant-sabor-"]').forEach(el => el.textContent = '0');
        document.querySelectorAll('.sabor-btn').forEach(el => el.classList.remove('activo'));

        // Actualizar contador de planchas
        document.getElementById('max-planchas').textContent = cantidad / 8;
        document.getElementById('contador-planchas').textContent = 0;

        document.getElementById('bloque-sabores').classList.remove('hidden');
        actualizarDisplayResumen();
    }

    function cambiarSabor(saborId, delta) {
        const paso = 8; // 1 unidad = 1 plancha de 8 sándwiches
        const maxTotal = estado.elegidosCantidad;
        const actual = estado.sabores[saborId] || 0;
        const totalActual = Object.values(estado.sabores).reduce((a, b) => a + b, 0);

        if (delta > 0 && totalActual + paso > maxTotal) {
            const maxPlanchas = maxTotal / 8;
            alert(`Máximo ${maxPlanchas} plancha${maxPlanchas > 1 ? 's' : ''} (${maxTotal} sándwiches) en total`);
            return;
        }

        const nuevo = Math.max(0, actual + delta * paso);
        estado.sabores[saborId] = nuevo;

        const el = document.getElementById('cant-sabor-' + saborId);
        if (el) el.textContent = nuevo > 0 ? `${nuevo / 8}P` : '0';

        const saborBtn = document.getElementById('sabor-' + saborId);
        if (nuevo > 0) saborBtn?.classList.add('activo');
        else saborBtn?.classList.remove('activo');

        const total = Object.values(estado.sabores).reduce((a, b) => a + b, 0);
        document.getElementById('contador-planchas').textContent = total / 8;
        actualizarDisplayResumen();
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
        estado.modalidad = modalidad;
        document.getElementById('campo_modalidad').value = modalidad;
        document.querySelectorAll('.modalidad-card').forEach(c => c.classList.remove('seleccionado'));
        event.currentTarget.classList.add('seleccionado');
        const bloqueDir   = document.getElementById('bloque-direccion');
        const bloqueFecha = document.getElementById('bloque-fecha');

        if (modalidad === 'Delivery') {
            bloqueDir.classList.remove('hidden');
            bloqueFecha.classList.remove('hidden');
            if (!estado.fechaPedido) {
                estado.fechaPedido = getArgentinaDate(0);
                document.getElementById('campo_fecha_pedido').value = estado.fechaPedido;
            }
            generarFechas();
            renderTurnos(estado.fechaPedido);
        } else {
            bloqueDir.classList.add('hidden');
            bloqueFecha.classList.add('hidden');
            ['dir_calle', 'dir_numero', 'dir_localidad', 'dir_entre_calles'].forEach(id => {
                const el = document.getElementById(id);
                if (el) el.value = '';
            });
            document.getElementById('campo_direccion').value = '';
            // Retiro: siempre hoy
            estado.fechaPedido = getArgentinaDate(0);
            document.getElementById('campo_fecha_pedido').value = estado.fechaPedido;
            renderTurnos(estado.fechaPedido);
        }
    }

    function seleccionarPago(tipo) {
        estado.formaPago = tipo;
        document.getElementById('campo_forma_pago').value = tipo;
        document.querySelectorAll('.pago-card').forEach(c => c.classList.remove('seleccionado'));
        event.currentTarget.classList.add('seleccionado');
        actualizarDisplayResumen();
    }

    // ============================================================
    // RESUMEN
    // ============================================================
    function formatPrecio(n) {
        return '$' + Math.round(n).toLocaleString('es-AR');
    }

    function actualizarDisplayResumen() {
        const nombre = estado.tipoPedido === 'personalizado' ? estado.elegidosNombre : estado.productoNombre;
        const resumen = document.getElementById('resumen-pedido');
        if (nombre && estado.turno && estado.formaPago) {
            resumen.classList.remove('hidden');
            document.getElementById('resumen-producto').textContent  = nombre;
            document.getElementById('resumen-turno').textContent     = estado.turno;
            document.getElementById('resumen-pago').textContent      = estado.formaPago;
            document.getElementById('resumen-modalidad').textContent = estado.modalidad;

            // Fecha de entrega (solo delivery)
            const filaFecha = document.getElementById('resumen-fila-fecha');
            if (estado.modalidad === 'Delivery' && estado.fechaPedido) {
                const [y, m, d] = estado.fechaPedido.split('-');
                document.getElementById('resumen-fecha').textContent = `${d}/${m}/${y}`;
                filaFecha?.classList.remove('hidden');
            } else {
                filaFecha?.classList.add('hidden');
            }

            // Precio según forma de pago y tipo de pedido
            let precio = 0;
            if (estado.tipoPedido === 'personalizado') {
                precio = estado.formaPago === 'Efectivo'
                    ? estado.elegidosPrecioEfectivo
                    : estado.elegidosPrecioTransferencia;
            } else {
                precio = estado.formaPago === 'Efectivo'
                    ? estado.precioEfectivo * estado.cantidad
                    : estado.precioTransferencia * estado.cantidad;
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
        // Validaciones finales
        if (!document.getElementById('campo_turno').value) {
            alert('Por favor seleccioná un turno');
            e.preventDefault();
            return false;
        }
        if (!document.getElementById('campo_forma_pago').value) {
            alert('Por favor seleccioná la forma de pago');
            e.preventDefault();
            return false;
        }
        if (estado.modalidad === 'Delivery') {
            if (!document.getElementById('campo_fecha_pedido').value) {
                alert('Seleccioná la fecha de entrega');
                e.preventDefault();
                return false;
            }
            const calle       = document.getElementById('dir_calle')?.value.trim();
            const numero      = document.getElementById('dir_numero')?.value.trim();
            const localidad   = document.getElementById('dir_localidad')?.value.trim();
            const entrecalles = document.getElementById('dir_entre_calles')?.value.trim();
            if (!calle || !numero || !localidad) {
                alert('Ingresá calle, número y localidad para el delivery');
                e.preventDefault();
                return false;
            }
            let dirCompuesta = `${calle} ${numero}, ${localidad}`;
            if (entrecalles) dirCompuesta += ` (entre ${entrecalles})`;
            document.getElementById('campo_direccion').value = dirCompuesta;
        }

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
