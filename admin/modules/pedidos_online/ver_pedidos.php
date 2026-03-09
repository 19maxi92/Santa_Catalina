<?php
/**
 * Vista de pedidos online con gestión y notificaciones vía WhatsApp
 */
require_once '../../config.php';
requireLogin();

$pdo = getConnection();

// Procesar acciones
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $accion = $_POST['accion'] ?? '';
    $id     = (int)($_POST['id'] ?? 0);

    try {
        switch ($accion) {
            case 'tomar_pedido':
                if ($id) {
                    $pdo->prepare("UPDATE pedidos SET estado = 'Preparando', updated_at = NOW() WHERE id = ?")
                        ->execute([$id]);
                    echo json_encode(['success' => true, 'mensaje' => 'Pedido tomado']);
                }
                exit;

            case 'cambiar_estado':
                $estado = $_POST['estado'] ?? '';
                if ($id && $estado) {
                    $pdo->prepare("UPDATE pedidos SET estado = ?, updated_at = NOW() WHERE id = ?")
                        ->execute([$estado, $id]);
                    $_SESSION['mensaje'] = "✅ Estado actualizado";
                }
                break;

            case 'eliminar':
                if ($id) {
                    $pdo->prepare("DELETE FROM pedidos WHERE id = ?")->execute([$id]);
                    $_SESSION['mensaje'] = "✅ Pedido eliminado";
                }
                break;
        }

        header('Location: ' . $_SERVER['REQUEST_URI']);
        exit;

    } catch (Exception $e) {
        if (isset($_POST['accion']) && $_POST['accion'] === 'tomar_pedido') {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
            exit;
        }
        $_SESSION['error'] = "❌ Error: " . $e->getMessage();
    }
}

// Filtros
$fecha    = $_GET['fecha'] ?? date('Y-m-d');
$filtro_estado = $_GET['estado'] ?? '';

// Obtener pedidos online
$sql = "SELECT * FROM pedidos WHERE observaciones LIKE '%PEDIDO ONLINE%' AND DATE(created_at) = ?";
$params = [$fecha];

if ($filtro_estado) {
    $sql .= " AND estado = ?";
    $params[] = $filtro_estado;
}

$sql .= " ORDER BY created_at DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$pedidos = $stmt->fetchAll();

// Estadísticas del día (sin filtro de estado)
$stmt_stats = $pdo->prepare("SELECT COUNT(*), COALESCE(SUM(precio),0), COUNT(CASE WHEN estado='Pendiente' THEN 1 END) FROM pedidos WHERE observaciones LIKE '%PEDIDO ONLINE%' AND DATE(created_at) = ?");
$stmt_stats->execute([$fecha]);
[$total, $total_ventas, $pendientes] = $stmt_stats->fetch(PDO::FETCH_NUM);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pedidos Online - <?= APP_NAME ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        @keyframes slideIn { from { opacity:0; transform:translateY(-8px); } to { opacity:1; transform:translateY(0); } }
        .pedido-card { animation: slideIn 0.3s ease; transition: all 0.2s; }
        .pedido-card:hover { transform: translateY(-2px); box-shadow: 0 8px 20px rgba(0,0,0,0.1); }
        .estado-Pendiente   { background:#fef3c7; color:#92400e; border-color:#f59e0b; }
        .estado-Preparando  { background:#dbeafe; color:#1e3a8a; border-color:#3b82f6; }
        .estado-Listo       { background:#d1fae5; color:#064e3b; border-color:#10b981; }
        .estado-Entregado   { background:#f3f4f6; color:#374151; border-color:#9ca3af; }
    </style>
</head>
<body class="bg-gray-100 min-h-screen">

    <!-- Header -->
    <header class="bg-gradient-to-r from-teal-600 to-cyan-700 text-white shadow-xl">
        <div class="max-w-6xl mx-auto px-4 py-4 flex justify-between items-center">
            <div class="flex items-center gap-4">
                <a href="../../index.php" class="text-teal-200 hover:text-white transition-colors">
                    <i class="fas fa-arrow-left text-xl"></i>
                </a>
                <div>
                    <h1 class="text-2xl font-black flex items-center gap-2">
                        <i class="fas fa-globe"></i>
                        Pedidos Online
                    </h1>
                    <p class="text-teal-200 text-sm">Desde la app / formulario web</p>
                </div>
                <?php if ($pendientes > 0): ?>
                    <span class="bg-yellow-400 text-yellow-900 font-black px-3 py-1 rounded-full text-sm animate-pulse">
                        <?= $pendientes ?> pendiente<?= $pendientes > 1 ? 's' : '' ?>
                    </span>
                <?php endif; ?>
            </div>
            <div class="flex items-center gap-3">
                <a href="configuracion.php"
                   class="bg-white bg-opacity-20 hover:bg-opacity-30 text-white px-4 py-2 rounded-lg text-sm font-semibold transition-all">
                    <i class="fas fa-cog mr-2"></i>Turnos
                </a>
                <span class="text-teal-200 text-sm"><?= $_SESSION['admin_name'] ?? 'Admin' ?></span>
            </div>
        </div>
    </header>

    <main class="max-w-6xl mx-auto px-4 py-6">

        <!-- Mensajes -->
        <?php if (isset($_SESSION['mensaje'])): ?>
            <div class="bg-green-100 border-l-4 border-green-500 text-green-800 px-4 py-3 rounded-lg mb-4">
                <?= htmlspecialchars($_SESSION['mensaje']) ?>
            </div>
            <?php unset($_SESSION['mensaje']); ?>
        <?php endif; ?>

        <!-- Stats -->
        <div class="grid grid-cols-3 gap-4 mb-6">
            <div class="bg-gradient-to-br from-teal-500 to-teal-600 text-white rounded-xl p-4 text-center shadow">
                <div class="text-3xl font-black"><?= $total ?></div>
                <div class="text-teal-200 text-sm">Pedidos hoy</div>
            </div>
            <div class="bg-gradient-to-br from-yellow-500 to-orange-500 text-white rounded-xl p-4 text-center shadow">
                <div class="text-3xl font-black"><?= $pendientes ?></div>
                <div class="text-yellow-100 text-sm">Pendientes</div>
            </div>
            <div class="bg-gradient-to-br from-green-500 to-emerald-600 text-white rounded-xl p-4 text-center shadow">
                <div class="text-2xl font-black">$<?= number_format($total_ventas/1000, 1) ?>K</div>
                <div class="text-green-100 text-sm">Total ventas</div>
            </div>
        </div>

        <!-- Filtros -->
        <div class="bg-white rounded-xl shadow p-4 mb-6">
            <form method="GET" class="flex flex-wrap items-end gap-4">
                <div>
                    <label class="block text-xs font-bold text-gray-600 mb-1">Fecha</label>
                    <input type="date" name="fecha" value="<?= $fecha ?>"
                           class="px-3 py-2 border-2 border-gray-300 rounded-lg text-sm focus:border-teal-500">
                </div>
                <div>
                    <label class="block text-xs font-bold text-gray-600 mb-1">Estado</label>
                    <select name="estado" class="px-3 py-2 border-2 border-gray-300 rounded-lg text-sm focus:border-teal-500">
                        <option value="">Todos</option>
                        <option value="Pendiente"  <?= $filtro_estado === 'Pendiente'  ? 'selected' : '' ?>>⏱️ Pendientes</option>
                        <option value="Preparando" <?= $filtro_estado === 'Preparando' ? 'selected' : '' ?>>🔥 Preparando</option>
                        <option value="Listo"      <?= $filtro_estado === 'Listo'      ? 'selected' : '' ?>>✅ Listos</option>
                        <option value="Entregado"  <?= $filtro_estado === 'Entregado'  ? 'selected' : '' ?>>📦 Entregados</option>
                    </select>
                </div>
                <button type="submit" class="bg-teal-600 hover:bg-teal-700 text-white px-5 py-2 rounded-lg font-semibold text-sm transition-all">
                    <i class="fas fa-search mr-1"></i>Filtrar
                </button>
                <a href="?" class="bg-gray-200 hover:bg-gray-300 text-gray-700 px-5 py-2 rounded-lg font-semibold text-sm transition-all">
                    <i class="fas fa-redo mr-1"></i>Hoy
                </a>
                <button type="button" onclick="location.reload()"
                        class="bg-blue-500 hover:bg-blue-600 text-white px-4 py-2 rounded-lg text-sm font-semibold transition-all">
                    <i class="fas fa-sync-alt mr-1"></i>Actualizar
                </button>
            </form>
        </div>

        <!-- Lista de pedidos -->
        <?php if (empty($pedidos)): ?>
            <div class="bg-white rounded-xl shadow p-12 text-center">
                <i class="fas fa-inbox text-6xl text-gray-300 mb-4"></i>
                <h3 class="text-2xl font-bold text-gray-600">No hay pedidos online</h3>
                <p class="text-gray-400 mt-2">Para la fecha seleccionada</p>
            </div>
        <?php else: ?>
            <div class="space-y-3">
                <?php foreach ($pedidos as $pedido):
                    // Extraer turno del campo observaciones
                    preg_match('/Turno:\s*([^\n]+)/', $pedido['observaciones'] ?? '', $m_turno);
                    $turno_pedido = trim($m_turno[1] ?? '—');

                    // Extraer email
                    preg_match('/Email:\s*([^\n]+)/', $pedido['observaciones'] ?? '', $m_email);
                    $email_cliente = trim($m_email[1] ?? '');

                    // Extraer sabores si es personalizado
                    $es_personalizado = strpos($pedido['observaciones'] ?? '', 'Sabores:') !== false;
                    preg_match('/Sabores:\s*([^\[]+)/', $pedido['observaciones'] ?? '', $m_sabores);
                    $sabores_texto = trim($m_sabores[1] ?? '');

                    // Preparar número WhatsApp limpio
                    $tel_limpio = preg_replace('/[^0-9]/', '', $pedido['telefono']);
                    if (strlen($tel_limpio) === 10) $tel_limpio = '54' . $tel_limpio;
                    if (strlen($tel_limpio) === 11 && $tel_limpio[0] !== '5') $tel_limpio = '54' . $tel_limpio;

                    // Mensajes WhatsApp
                    $nombre_cliente = htmlspecialchars($pedido['nombre'] . ' ' . $pedido['apellido']);
                    $msg_tomar = urlencode("Hola {$pedido['nombre']}! 👋 Tu pedido en Santa Catalina fue *recibido* y ya lo estamos preparando 🥪\n\nTurno: *{$turno_pedido}*\nProducto: *{$pedido['producto']}*\nModalidad: *{$pedido['modalidad']}*\n\nCualquier consulta, estamos acá. ¡Gracias!");
                    $msg_listo  = urlencode("Hola {$pedido['nombre']}! ✅ Tu pedido *ya está listo* para {$pedido['modalidad']} 🥪\n\nTe esperamos en el turno *{$turno_pedido}*!\nCualquier duda, escribinos.");
                    $msg_custom = urlencode("Hola {$pedido['nombre']}, ");
                ?>
                <div class="pedido-card bg-white rounded-xl shadow-md overflow-hidden border-l-4
                    <?= $pedido['estado'] === 'Pendiente'  ? 'border-yellow-400' :
                       ($pedido['estado'] === 'Preparando' ? 'border-blue-400' :
                       ($pedido['estado'] === 'Listo'      ? 'border-green-400' : 'border-gray-300')) ?>">

                    <div class="p-4">
                        <div class="flex flex-wrap items-start justify-between gap-3">

                            <!-- INFO IZQUIERDA -->
                            <div class="flex-1 min-w-0">
                                <div class="flex items-center gap-2 mb-2 flex-wrap">
                                    <span class="text-lg font-black text-teal-600">#<?= $pedido['id'] ?></span>
                                    <span class="px-2 py-0.5 rounded-full text-xs font-bold border estado-<?= $pedido['estado'] ?>">
                                        <?= $pedido['estado'] ?>
                                    </span>
                                    <?php if ($pedido['modalidad'] === 'Delivery'): ?>
                                        <span class="bg-blue-100 text-blue-700 px-2 py-0.5 rounded-full text-xs font-semibold">
                                            🏍️ Delivery
                                        </span>
                                    <?php endif; ?>
                                    <span class="text-xs text-gray-400">
                                        <?= formatDateTime($pedido['created_at'], 'H:i') ?>
                                    </span>
                                </div>

                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-x-4 gap-y-1 text-sm">
                                    <div>
                                        <span class="text-gray-500">Cliente:</span>
                                        <span class="font-semibold text-gray-900 ml-1"><?= htmlspecialchars($pedido['nombre'] . ' ' . $pedido['apellido']) ?></span>
                                    </div>
                                    <div>
                                        <span class="text-gray-500">Turno:</span>
                                        <span class="font-semibold text-purple-700 ml-1"><?= htmlspecialchars($turno_pedido) ?></span>
                                    </div>
                                    <div>
                                        <span class="text-gray-500">Tel:</span>
                                        <span class="font-mono text-gray-800 ml-1"><?= htmlspecialchars($pedido['telefono']) ?></span>
                                    </div>
                                    <div>
                                        <span class="text-gray-500">Pago:</span>
                                        <span class="font-semibold text-gray-800 ml-1"><?= htmlspecialchars($pedido['forma_pago']) ?></span>
                                    </div>
                                    <?php if ($email_cliente): ?>
                                    <div class="sm:col-span-2">
                                        <span class="text-gray-500">Email:</span>
                                        <span class="text-gray-700 ml-1"><?= htmlspecialchars($email_cliente) ?></span>
                                    </div>
                                    <?php endif; ?>
                                    <?php if ($pedido['modalidad'] === 'Delivery' && $pedido['direccion']): ?>
                                    <div class="sm:col-span-2">
                                        <span class="text-gray-500">Dirección:</span>
                                        <span class="text-gray-800 ml-1"><?= htmlspecialchars($pedido['direccion']) ?></span>
                                    </div>
                                    <?php endif; ?>
                                </div>

                                <div class="mt-2 p-2 bg-gray-50 rounded-lg text-sm">
                                    <span class="font-bold text-gray-900"><?= htmlspecialchars($pedido['producto']) ?></span>
                                    <?php if ($es_personalizado && $sabores_texto): ?>
                                        <div class="text-xs text-gray-500 mt-1">
                                            <i class="fas fa-palette text-orange-400 mr-1"></i><?= htmlspecialchars($sabores_texto) ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <!-- ACCIONES DERECHA -->
                            <div class="flex flex-col gap-2 min-w-[180px]">

                                <!-- CAMBIAR ESTADO -->
                                <form method="POST">
                                    <input type="hidden" name="accion" value="cambiar_estado">
                                    <input type="hidden" name="id" value="<?= $pedido['id'] ?>">
                                    <select name="estado"
                                            onchange="if(confirm('¿Cambiar estado?')) this.form.submit()"
                                            class="w-full text-xs font-bold px-2 py-2 border-2 rounded-lg cursor-pointer estado-<?= $pedido['estado'] ?>">
                                        <option value="Pendiente"  <?= $pedido['estado'] === 'Pendiente'  ? 'selected' : '' ?>>⏱️ Pendiente</option>
                                        <option value="Preparando" <?= $pedido['estado'] === 'Preparando' ? 'selected' : '' ?>>🔥 Preparando</option>
                                        <option value="Listo"      <?= $pedido['estado'] === 'Listo'      ? 'selected' : '' ?>>✅ Listo</option>
                                        <option value="Entregado"  <?= $pedido['estado'] === 'Entregado'  ? 'selected' : '' ?>>📦 Entregado</option>
                                    </select>
                                </form>

                                <?php if ($pedido['estado'] === 'Pendiente'): ?>
                                <!-- TOMAR PEDIDO (cambia a Preparando + abre WhatsApp) -->
                                <button onclick="tomarPedido(<?= $pedido['id'] ?>, '<?= $tel_limpio ?>', '<?= $msg_tomar ?>')"
                                        class="w-full bg-gradient-to-r from-teal-500 to-teal-600 hover:from-teal-600 hover:to-teal-700 text-white py-2 px-3 rounded-lg font-bold text-sm transition-all flex items-center justify-center gap-2 shadow">
                                    <i class="fas fa-hands-helping"></i>
                                    Tomar Pedido
                                </button>
                                <?php endif; ?>

                                <?php if ($pedido['estado'] === 'Listo'): ?>
                                <!-- AVISAR LISTO vía WhatsApp -->
                                <a href="https://wa.me/<?= $tel_limpio ?>?text=<?= $msg_listo ?>"
                                   target="_blank"
                                   class="w-full bg-green-500 hover:bg-green-600 text-white py-2 px-3 rounded-lg font-bold text-sm transition-all flex items-center justify-center gap-2 shadow">
                                    <i class="fab fa-whatsapp"></i>
                                    Avisar "Listo"
                                </a>
                                <?php endif; ?>

                                <!-- WhatsApp LIBRE -->
                                <a href="https://wa.me/<?= $tel_limpio ?>?text=<?= $msg_custom ?>"
                                   target="_blank"
                                   class="w-full bg-green-400 hover:bg-green-500 text-white py-2 px-3 rounded-lg font-semibold text-sm transition-all flex items-center justify-center gap-2">
                                    <i class="fab fa-whatsapp"></i>
                                    WhatsApp libre
                                </a>

                                <!-- ELIMINAR -->
                                <form method="POST" onsubmit="return confirm('¿Eliminar pedido #<?= $pedido['id'] ?>?')">
                                    <input type="hidden" name="accion" value="eliminar">
                                    <input type="hidden" name="id" value="<?= $pedido['id'] ?>">
                                    <button type="submit"
                                            class="w-full bg-red-100 hover:bg-red-200 text-red-700 py-1.5 px-3 rounded-lg text-xs font-semibold transition-all">
                                        <i class="fas fa-trash mr-1"></i>Eliminar
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

    </main>

    <script>
    function tomarPedido(pedidoId, telefono, mensajeEncoded) {
        if (!confirm('¿Tomar el pedido #' + pedidoId + '?\n\nSe cambiará a "Preparando" y se abrirá WhatsApp para notificar al cliente.')) {
            return;
        }

        // Cambiar estado vía AJAX
        const fd = new FormData();
        fd.append('accion', 'tomar_pedido');
        fd.append('id', pedidoId);

        fetch(window.location.href, { method: 'POST', body: fd })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    // Abrir WhatsApp con el mensaje pre-llenado
                    window.open('https://wa.me/' + telefono + '?text=' + mensajeEncoded, '_blank');
                    // Recargar para ver el estado actualizado
                    setTimeout(() => location.reload(), 800);
                } else {
                    alert('Error al actualizar el estado');
                }
            })
            .catch(() => {
                // Si falla AJAX, igual abrir WhatsApp y recargar
                window.open('https://wa.me/' + telefono + '?text=' + mensajeEncoded, '_blank');
                location.reload();
            });
    }

    // Auto-refresh cada 60 segundos si hay pedidos pendientes
    <?php if ($pendientes > 0): ?>
    let secondsLeft = 60;
    const counter = document.createElement('div');
    counter.className = 'fixed bottom-4 right-4 bg-gray-800 text-white text-xs px-3 py-2 rounded-full opacity-70';
    counter.textContent = '🔄 Actualiza en 60s';
    document.body.appendChild(counter);

    setInterval(() => {
        secondsLeft--;
        counter.textContent = '🔄 Actualiza en ' + secondsLeft + 's';
        if (secondsLeft <= 0) location.reload();
    }, 1000);
    <?php endif; ?>
    </script>

</body>
</html>
