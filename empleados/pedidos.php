<?php
require_once '../config.php';
session_start();

// Verificar acceso de empleado
if (!isset($_SESSION['empleado_logged']) || $_SESSION['empleado_logged'] !== true) {
    header('Location: login.php');
    exit;
}

$pdo = getConnection();

// Manejar acciones (solo cambio de estado)
$mensaje = '';
$error = '';

if ($_POST) {
    if (isset($_POST['accion']) && $_POST['accion'] === 'cambiar_estado') {
        $id = (int)$_POST['id'];
        $estado = $_POST['estado'];
        $estados_validos = ['Pendiente', 'Preparando', 'Listo', 'Entregado'];
        
        if (in_array($estado, $estados_validos)) {
            try {
                $stmt = $pdo->prepare("UPDATE pedidos SET estado = ?, updated_at = NOW() WHERE id = ?");
                $stmt->execute([$estado, $id]);
                $mensaje = 'Estado actualizado correctamente';
            } catch (Exception $e) {
                $error = 'Error al actualizar estado';
            }
        } else {
            $error = 'Estado no válido';
        }
    }
}

// FILTROS MEJORADOS - NUEVO: Rango de fechas
$filtro_estado = isset($_GET['estado']) ? sanitize($_GET['estado']) : '';
$filtro_fecha_desde = isset($_GET['fecha_desde']) ? sanitize($_GET['fecha_desde']) : '';
$filtro_fecha_hasta = isset($_GET['fecha_hasta']) ? sanitize($_GET['fecha_hasta']) : '';
$buscar = isset($_GET['buscar']) ? sanitize($_GET['buscar']) : '';

// Si no hay rango específico, mostrar solo hoy por defecto (comportamiento para empleados)
if (!$filtro_fecha_desde && !$filtro_fecha_hasta && !$buscar && !$filtro_estado) {
    $filtro_fecha_desde = date('Y-m-d');
    $filtro_fecha_hasta = date('Y-m-d');
}

// Construir consulta
$sql = "SELECT p.*, cf.nombre as cliente_nombre, cf.apellido as cliente_apellido 
        FROM pedidos p 
        LEFT JOIN clientes_fijos cf ON p.cliente_fijo_id = cf.id 
        WHERE 1=1";
$params = [];

// Filtro por rango de fechas
if ($filtro_fecha_desde && $filtro_fecha_hasta) {
    $sql .= " AND DATE(p.created_at) BETWEEN ? AND ?";
    $params[] = $filtro_fecha_desde;
    $params[] = $filtro_fecha_hasta;
} elseif ($filtro_fecha_desde) {
    $sql .= " AND DATE(p.created_at) >= ?";
    $params[] = $filtro_fecha_desde;
} elseif ($filtro_fecha_hasta) {
    $sql .= " AND DATE(p.created_at) <= ?";
    $params[] = $filtro_fecha_hasta;
}

if ($filtro_estado) {
    $sql .= " AND p.estado = ?";
    $params[] = $filtro_estado;
}

if ($buscar) {
    $sql .= " AND (p.nombre LIKE ? OR p.apellido LIKE ? OR p.telefono LIKE ? OR p.producto LIKE ?)";
    $buscarParam = "%$buscar%";
    $params = array_merge($params, [$buscarParam, $buscarParam, $buscarParam, $buscarParam]);
}

$sql .= " ORDER BY 
    CASE p.estado 
        WHEN 'Pendiente' THEN 1 
        WHEN 'Preparando' THEN 2 
        WHEN 'Listo' THEN 3 
        WHEN 'Entregado' THEN 4 
    END, 
    p.created_at ASC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$pedidos = $stmt->fetchAll();

// Estadísticas del rango seleccionado
$stats_sql = "SELECT 
    COUNT(*) as total,
    SUM(CASE WHEN estado = 'Pendiente' THEN 1 ELSE 0 END) as pendientes,
    SUM(CASE WHEN estado = 'Preparando' THEN 1 ELSE 0 END) as preparando,
    SUM(CASE WHEN estado = 'Listo' THEN 1 ELSE 0 END) as listos,
    SUM(CASE WHEN estado = 'Entregado' THEN 1 ELSE 0 END) as entregados,
    SUM(precio) as total_ventas
    FROM pedidos WHERE 1=1";

$stats_params = [];
if ($filtro_fecha_desde && $filtro_fecha_hasta) {
    $stats_sql .= " AND DATE(created_at) BETWEEN ? AND ?";
    $stats_params[] = $filtro_fecha_desde;
    $stats_params[] = $filtro_fecha_hasta;
} elseif ($filtro_fecha_desde) {
    $stats_sql .= " AND DATE(created_at) >= ?";
    $stats_params[] = $filtro_fecha_desde;
} elseif ($filtro_fecha_hasta) {
    $stats_sql .= " AND DATE(created_at) <= ?";
    $stats_params[] = $filtro_fecha_hasta;
} else {
    // Si no hay filtros de fecha específicos, mostrar estadísticas de hoy
    $stats_sql .= " AND DATE(created_at) = CURDATE()";
}

$stats_stmt = $pdo->prepare($stats_sql);
$stats_stmt->execute($stats_params);
$stats = $stats_stmt->fetch();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión de Pedidos - Empleados</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="bg-gray-100">
    <!-- Header -->
    <header class="bg-blue-600 text-white shadow-md">
        <div class="container mx-auto px-4 py-3 flex justify-between items-center">
            <div class="flex items-center">
                <a href="dashboard.php" class="text-blue-100 hover:text-white mr-4">
                    <i class="fas fa-arrow-left"></i>
                </a>
                <h1 class="text-xl font-bold">
                    <i class="fas fa-clipboard-list mr-2"></i>Gestión de Pedidos
                </h1>
            </div>
            <a href="logout.php" class="bg-red-500 hover:bg-red-600 px-3 py-2 rounded">
                <i class="fas fa-sign-out-alt mr-1"></i>Salir
            </a>
        </div>
    </header>

    <main class="container mx-auto px-4 py-6">
        
        <!-- Mensajes -->
        <?php if ($mensaje): ?>
        <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4">
            <i class="fas fa-check-circle mr-2"></i><?= $mensaje ?>
        </div>
        <?php endif; ?>

        <?php if ($error): ?>
        <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
            <i class="fas fa-exclamation-circle mr-2"></i><?= $error ?>
        </div>
        <?php endif; ?>

        <!-- NUEVA SECCIÓN: Info del período seleccionado -->
        <?php if ($filtro_fecha_desde || $filtro_fecha_hasta): ?>
        <div class="bg-blue-50 border border-blue-200 rounded-lg p-4 mb-6">
            <div class="flex items-center justify-between">
                <div>
                    <h3 class="font-semibold text-blue-800">
                        <i class="fas fa-calendar-alt mr-2"></i>Período de trabajo:
                    </h3>
                    <p class="text-blue-700">
                        <?php if ($filtro_fecha_desde && $filtro_fecha_hasta): ?>
                            <?php if ($filtro_fecha_desde === $filtro_fecha_hasta): ?>
                                <?= date('d/m/Y', strtotime($filtro_fecha_desde)) ?>
                                <?php if ($filtro_fecha_desde === date('Y-m-d')): ?>
                                    <span class="text-green-600 font-medium">(Hoy)</span>
                                <?php endif; ?>
                            <?php else: ?>
                                Desde <?= date('d/m/Y', strtotime($filtro_fecha_desde)) ?> hasta <?= date('d/m/Y', strtotime($filtro_fecha_hasta)) ?>
                            <?php endif; ?>
                        <?php elseif ($filtro_fecha_desde): ?>
                            Desde <?= date('d/m/Y', strtotime($filtro_fecha_desde)) ?>
                        <?php else: ?>
                            Hasta <?= date('d/m/Y', strtotime($filtro_fecha_hasta)) ?>
                        <?php endif; ?>
                    </p>
                </div>
                <!-- Los empleados no necesitan exportar Excel, pero sí pueden ver el total de pedidos -->
                <div class="text-right">
                    <div class="text-lg font-bold text-blue-800"><?= count($pedidos) ?> pedidos</div>
                    <div class="text-sm text-blue-600">en este período</div>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Estadísticas -->
        <div class="grid grid-cols-2 md:grid-cols-6 gap-4 mb-6">
            <div class="bg-white p-4 rounded-lg shadow text-center">
                <div class="text-2xl font-bold text-gray-800"><?= $stats['total'] ?></div>
                <div class="text-sm text-gray-600">Total</div>
            </div>
            <div class="bg-white p-4 rounded-lg shadow text-center">
                <div class="text-2xl font-bold text-yellow-600"><?= $stats['pendientes'] ?></div>
                <div class="text-sm text-gray-600">Pendientes</div>
            </div>
            <div class="bg-white p-4 rounded-lg shadow text-center">
                <div class="text-2xl font-bold text-blue-600"><?= $stats['preparando'] ?></div>
                <div class="text-sm text-gray-600">Preparando</div>
            </div>
            <div class="bg-white p-4 rounded-lg shadow text-center">
                <div class="text-2xl font-bold text-green-600"><?= $stats['listos'] ?></div>
                <div class="text-sm text-gray-600">Listos</div>
            </div>
            <div class="bg-white p-4 rounded-lg shadow text-center">
                <div class="text-2xl font-bold text-gray-600"><?= $stats['entregados'] ?></div>
                <div class="text-sm text-gray-600">Entregados</div>
            </div>
            <div class="bg-white p-4 rounded-lg shadow text-center">
                <div class="text-2xl font-bold text-green-600"><?= formatPrice($stats['total_ventas']) ?></div>
                <div class="text-sm text-gray-600">Ventas</div>
            </div>
        </div>

        <!-- FILTROS MEJORADOS PARA EMPLEADOS -->
        <div class="bg-white rounded-lg shadow mb-6 p-6">
            <form method="GET" id="filtrosForm">
                
                <!-- NUEVA SECCIÓN: Filtros rápidos de fecha -->
                <div class="mb-4">
                    <h4 class="font-semibold text-gray-700 mb-3">
                        <i class="fas fa-calendar-week mr-2"></i>Filtros rápidos:
                    </h4>
                    <div class="flex flex-wrap gap-2">
                        <button type="button" onclick="setFiltroRapido('hoy')" 
                                class="bg-blue-100 hover:bg-blue-200 text-blue-800 px-3 py-1 rounded text-sm">
                            <i class="fas fa-calendar-day mr-1"></i>Hoy
                        </button>
                        <button type="button" onclick="setFiltroRapido('ayer')" 
                                class="bg-gray-100 hover:bg-gray-200 text-gray-800 px-3 py-1 rounded text-sm">
                            <i class="fas fa-calendar-minus mr-1"></i>Ayer
                        </button>
                        <button type="button" onclick="setFiltroRapido('semana')" 
                                class="bg-green-100 hover:bg-green-200 text-green-800 px-3 py-1 rounded text-sm">
                            <i class="fas fa-calendar-week mr-1"></i>Esta semana
                        </button>
                        <button type="button" onclick="setFiltroRapido('mes')" 
                                class="bg-purple-100 hover:bg-purple-200 text-purple-800 px-3 py-1 rounded text-sm">
                            <i class="fas fa-calendar mr-1"></i>Este mes
                        </button>
                        <button type="button" onclick="setFiltroRapido('todo')" 
                                class="bg-orange-100 hover:bg-orange-200 text-orange-800 px-3 py-1 rounded text-sm">
                            <i class="fas fa-calendar-alt mr-1"></i>Todo
                        </button>
                    </div>
                </div>

                <!-- Filtros principales adaptados para empleados -->
                <div class="grid grid-cols-1 md:grid-cols-5 gap-4 mb-4">
                    <!-- Buscador -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Buscar:</label>
                        <input type="text" name="buscar" value="<?= htmlspecialchars($buscar) ?>" 
                               placeholder="Cliente, producto..." 
                               class="w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500">
                    </div>
                    
                    <!-- Estado -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Estado:</label>
                        <select name="estado" class="w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500">
                            <option value="">Todos los estados</option>
                            <option value="Pendiente" <?= $filtro_estado === 'Pendiente' ? 'selected' : '' ?>>⏳ Pendiente</option>
                            <option value="Preparando" <?= $filtro_estado === 'Preparando' ? 'selected' : '' ?>>🔥 Preparando</option>
                            <option value="Listo" <?= $filtro_estado === 'Listo' ? 'selected' : '' ?>>✅ Listo</option>
                            <option value="Entregado" <?= $filtro_estado === 'Entregado' ? 'selected' : '' ?>>📦 Entregado</option>
                        </select>
                    </div>
                    
                    <!-- Fecha desde -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Desde:</label>
                        <input type="date" name="fecha_desde" value="<?= htmlspecialchars($filtro_fecha_desde) ?>" 
                               class="w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500">
                    </div>
                    
                    <!-- Fecha hasta -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Hasta:</label>
                        <input type="date" name="fecha_hasta" value="<?= htmlspecialchars($filtro_fecha_hasta) ?>" 
                               class="w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500">
                    </div>
                    
                    <!-- Botón buscar -->
                    <div class="flex items-end">
                        <button type="submit" class="w-full bg-blue-500 hover:bg-blue-600 text-white py-2 px-4 rounded-lg">
                            <i class="fas fa-search mr-1"></i>Filtrar
                        </button>
                    </div>
                </div>
                
                <!-- Botones adicionales -->
                <div class="flex justify-between items-center">
                    <div>
                        <a href="?" class="bg-gray-500 hover:bg-gray-600 text-white px-4 py-2 rounded-lg text-sm">
                            <i class="fas fa-eraser mr-2"></i>Limpiar Filtros
                        </a>
                    </div>
                    
                    <!-- Info útil para empleados -->
                    <div class="text-sm text-gray-600">
                        <i class="fas fa-info-circle mr-1"></i>
                        Los pedidos se ordenan por prioridad de estado
                    </div>
                </div>
            </form>
        </div>

        <!-- Lista de pedidos (adaptada para empleados) -->
        <div class="space-y-4">
            <?php if (empty($pedidos)): ?>
                <div class="bg-white p-12 rounded-lg shadow text-center text-gray-500">
                    <i class="fas fa-inbox text-6xl mb-4 text-gray-300"></i>
                    <h3 class="text-xl mb-2">No hay pedidos</h3>
                    <p>No se encontraron pedidos para los filtros seleccionados</p>
                    <div class="mt-4">
                        <a href="?fecha_desde=<?= date('Y-m-d') ?>&fecha_hasta=<?= date('Y-m-d') ?>" 
                           class="text-blue-600 hover:underline">Ver pedidos de hoy</a>
                    </div>
                </div>
            <?php else: ?>
                <?php foreach ($pedidos as $pedido): ?>
                    <?php
                    $minutos = round((time() - strtotime($pedido['created_at'])) / 60);
                    $estado_colors = [
                        'Pendiente' => 'border-l-yellow-400 bg-yellow-50',
                        'Preparando' => 'border-l-blue-400 bg-blue-50',
                        'Listo' => 'border-l-green-400 bg-green-50',
                        'Entregado' => 'border-l-gray-400 bg-gray-50'
                    ];
                    
                    $urgencia_class = '';
                    if ($minutos > 60) {
                        $urgencia_class = 'border-r-4 border-r-red-500';
                    } elseif ($minutos > 30) {
                        $urgencia_class = 'border-r-4 border-r-orange-500';
                    }
                    ?>
                    
                    <div class="bg-white rounded-lg shadow border-l-4 <?= $estado_colors[$pedido['estado']] ?> <?= $urgencia_class ?>">
                        <div class="p-6">
                            <div class="flex justify-between items-start mb-4">
                                <!-- Info principal -->
                                <div class="flex-1">
                                    <div class="flex items-center space-x-3 mb-3">
                                        <span class="text-2xl font-bold text-gray-800">#<?= $pedido['id'] ?></span>
                                        <span class="text-lg font-semibold text-gray-700">
                                            <?= htmlspecialchars($pedido['nombre'] . ' ' . $pedido['apellido']) ?>
                                        </span>
                                        <?php if ($pedido['cliente_nombre']): ?>
                                            <span class="bg-blue-100 text-blue-800 px-2 py-1 rounded-full text-xs">
                                                Cliente fijo
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                    
                                    <div class="grid md:grid-cols-2 gap-4 text-sm">
                                        <div>
                                            <p class="text-gray-600 mb-1">
                                                <i class="fas fa-phone w-4"></i> <?= htmlspecialchars($pedido['telefono']) ?>
                                            </p>
                                            <?php if ($pedido['direccion']): ?>
                                            <p class="text-gray-600 mb-1">
                                                <i class="fas fa-map-marker-alt w-4 text-red-500"></i> 
                                                <span class="font-medium"><?= htmlspecialchars($pedido['direccion']) ?></span>
                                            </p>
                                            <?php else: ?>
                                            <p class="text-red-600 mb-1">
                                                <i class="fas fa-exclamation-triangle w-4"></i>
                                                <span class="font-medium">Sin dirección</span>
                                            </p>
                                            <?php endif; ?>
                                            <p class="text-gray-600 mb-1">
                                                <i class="fas fa-<?= $pedido['modalidad'] === 'Delivery' ? 'truck text-green-500' : 'store text-blue-500' ?> w-4"></i>
                                                <?= $pedido['modalidad'] ?>
                                            </p>
                                        </div>
                                        <div>
                                            <p class="text-gray-600 mb-1">
                                                <i class="fas fa-credit-card w-4"></i> <?= $pedido['forma_pago'] ?>
                                            </p>
                                            <p class="text-gray-600 mb-1">
                                                <i class="fas fa-clock w-4"></i> 
                                                <?= date('H:i', strtotime($pedido['created_at'])) ?> (Hace <?= $minutos ?> min)
                                            </p>
                                            <?php if ($pedido['observaciones']): ?>
                                            <p class="text-gray-600 mb-1">
                                                <i class="fas fa-comment w-4"></i> <?= htmlspecialchars($pedido['observaciones']) ?>
                                            </p>
                                            <?php endif; ?>

                                            <!-- Información de fecha/hora de entrega -->
                                            <?php if ($pedido['fecha_entrega'] || $pedido['hora_entrega'] || $pedido['notas_horario']): ?>
                                            <div class="text-xs text-orange-600 bg-orange-50 px-2 py-1 rounded mt-2">
                                                <i class="fas fa-clock mr-1"></i>
                                                <strong>Para:</strong>
                                                <?php if ($pedido['fecha_entrega']): ?>
                                                    <?= date('d/m', strtotime($pedido['fecha_entrega'])) ?>
                                                <?php endif; ?>
                                                <?php if ($pedido['hora_entrega']): ?>
                                                    <?= substr($pedido['hora_entrega'], 0, 5) ?>
                                                <?php endif; ?>
                                                <?php if ($pedido['notas_horario']): ?>
                                                    (<?= htmlspecialchars($pedido['notas_horario']) ?>)
                                                <?php endif; ?>
                                            </div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- Estado y urgencia -->
                                <div class="ml-4 text-right">
                                    <?php if ($minutos > 60): ?>
                                        <div class="bg-red-100 text-red-800 px-3 py-1 rounded-full text-sm font-medium mb-2">
                                            🚨 URGENTE
                                        </div>
                                    <?php elseif ($minutos > 30): ?>
                                        <div class="bg-orange-100 text-orange-800 px-3 py-1 rounded-full text-sm font-medium mb-2">
                                            ⚠️ PRIORIDAD
                                        </div>
                                    <?php endif; ?>
                                    
                                    <form method="POST" class="inline-block">
                                        <input type="hidden" name="accion" value="cambiar_estado">
                                        <input type="hidden" name="id" value="<?= $pedido['id'] ?>">
                                        <select name="estado" onchange="this.form.submit()" 
                                                class="px-3 py-2 border rounded text-sm font-medium">
                                            <option value="Pendiente" <?= $pedido['estado'] === 'Pendiente' ? 'selected' : '' ?>>⏳ Pendiente</option>
                                            <option value="Preparando" <?= $pedido['estado'] === 'Preparando' ? 'selected' : '' ?>>🔥 Preparando</option>
                                            <option value="Listo" <?= $pedido['estado'] === 'Listo' ? 'selected' : '' ?>>✅ Listo</option>
                                            <option value="Entregado" <?= $pedido['estado'] === 'Entregado' ? 'selected' : '' ?>>📦 Entregado</option>
                                        </select>
                                    </form>
                                </div>
                            </div>
                            
                            <!-- Producto y precio -->
                            <div class="bg-gray-50 rounded-lg p-4 mb-4">
                                <div class="flex justify-between items-center">
                                    <div>
                                        <h3 class="text-lg font-semibold text-gray-800">
                                            <?= htmlspecialchars($pedido['producto']) ?>
                                        </h3>
                                        <p class="text-gray-600">Cantidad: <?= $pedido['cantidad'] ?> unidades</p>
                                    </div>
                                    <div class="text-right">
                                        <div class="text-2xl font-bold text-green-600">
                                            <?= formatPrice($pedido['precio']) ?>
                                        </div>
                                        <div class="text-sm text-gray-500"><?= $pedido['forma_pago'] ?></div>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Acciones para empleados -->
                            <div class="flex justify-between items-center">
                                <div class="flex items-center space-x-3">
                                    <?php if ($pedido['impreso']): ?>
                                        <span class="bg-green-100 text-green-800 px-3 py-1 rounded-full text-sm">
                                            <i class="fas fa-check-circle mr-1"></i>Comanda Impresa
                                        </span>
                                    <?php else: ?>
                                        <span class="bg-red-100 text-red-800 px-3 py-1 rounded-full text-sm">
                                            <i class="fas fa-exclamation-circle mr-1"></i>Sin Imprimir
                                        </span>
                                    <?php endif; ?>

                                    <!-- Fecha del pedido -->
                                    <span class="text-gray-500 text-sm">
                                        <i class="fas fa-calendar mr-1"></i>
                                        <?= date('d/m/Y', strtotime($pedido['created_at'])) ?>
                                    </span>
                                </div>
                                
                                <div class="flex space-x-2">
                                    <!-- Solo WhatsApp para empleados -->
                                    <a href="https://wa.me/<?= preg_replace('/[^0-9]/', '', $pedido['telefono']) ?>?text=Hola%20<?= urlencode($pedido['nombre']) ?>,%20tu%20pedido%20#<?= $pedido['id'] ?>%20está%20<?= urlencode(strtolower($pedido['estado'])) ?>" 
                                       target="_blank" 
                                       class="bg-green-500 hover:bg-green-600 text-white px-3 py-2 rounded text-sm">
                                        <i class="fab fa-whatsapp mr-1"></i>WhatsApp
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <!-- Footer para empleados -->
        <div class="mt-8 text-center text-gray-500">
            <div class="bg-white rounded-lg p-4 shadow">
                <p class="mb-2">
                    <i class="fas fa-sync-alt mr-1"></i>
                    Página actualizada automáticamente cada 30 segundos
                </p>
                <p class="text-sm mb-2">
                    Mostrando <?= count($pedidos) ?> pedido<?= count($pedidos) !== 1 ? 's' : '' ?> 
                    <?php if ($filtro_fecha_desde && $filtro_fecha_hasta): ?>
                        para el período seleccionado
                    <?php else: ?>
                        (usar filtros para ver otros períodos)
                    <?php endif; ?>
                </p>
                <p class="text-xs text-blue-600">
                    🔴 Más de 1 hora = Urgente | 🟠 Más de 30 min = Prioridad
                </p>
                <p class="text-xs mt-2 text-gray-500">
                    <i class="fas fa-info-circle mr-1"></i>
                    Para impresión de comandas, contactar al administrador
                </p>
            </div>
        </div>
    </main>

    <script>
        // FILTROS RÁPIDOS PARA EMPLEADOS
        function setFiltroRapido(tipo) {
            const hoy = new Date();
            let desde, hasta;
            
            console.log(`Empleado aplicando filtro: ${tipo}`);
            
            switch(tipo) {
                case 'hoy':
                    desde = hasta = formatDate(hoy);
                    break;
                    
                case 'ayer':
                    const ayer = new Date(hoy);
                    ayer.setDate(ayer.getDate() - 1);
                    desde = hasta = formatDate(ayer);
                    break;
                    
                case 'semana':
                    // Esta semana desde el lunes
                    const inicioSemana = new Date(hoy);
                    const diaSemana = inicioSemana.getDay();
                    const diasAtras = diaSemana === 0 ? 6 : diaSemana - 1; // Si es domingo (0), retroceder 6 días
                    inicioSemana.setDate(hoy.getDate() - diasAtras);
                    desde = formatDate(inicioSemana);
                    hasta = formatDate(hoy);
                    break;
                    
                case 'mes':
                    // Este mes desde el día 1
                    const inicioMes = new Date(hoy.getFullYear(), hoy.getMonth(), 1);
                    desde = formatDate(inicioMes);
                    hasta = formatDate(hoy);
                    break;
                    
                case 'todo':
                    desde = '';
                    hasta = '';
                    break;
            }
            
            // Verificar que los campos existen antes de asignar valores
            const fechaDesdeInput = document.querySelector('input[name="fecha_desde"]');
            const fechaHastaInput = document.querySelector('input[name="fecha_hasta"]');
            
            if (fechaDesdeInput) {
                fechaDesdeInput.value = desde;
                console.log(`Fecha desde: ${desde}`);
            } else {
                console.error('Campo fecha_desde no encontrado');
                return;
            }
            
            if (fechaHastaInput) {
                fechaHastaInput.value = hasta;
                console.log(`Fecha hasta: ${hasta}`);
            } else {
                console.error('Campo fecha_hasta no encontrado');
                return;
            }
            
            // Auto-submit form
            const form = document.getElementById('filtrosForm');
            if (form) {
                console.log('Enviando formulario...');
                form.submit();
            } else {
                console.error('Formulario filtrosForm no encontrado');
            }
        }
        
        function formatDate(date) {
            const año = date.getFullYear();
            const mes = String(date.getMonth() + 1).padStart(2, '0');
            const dia = String(date.getDate()).padStart(2, '0');
            return `${año}-${mes}-${dia}`;
        }

        // Auto refresh cada 30 segundos (importante para empleados)
        setInterval(function() {
            // Solo hacer refresh si no hay un formulario siendo editado
            if (!document.activeElement || document.activeElement.tagName !== 'SELECT') {
                location.reload();
            }
        }, 30000);

        // Efectos visuales para empleados
        document.addEventListener('DOMContentLoaded', function() {
            // Highlight de pedidos urgentes
            const urgentes = document.querySelectorAll('.border-r-red-500');
            urgentes.forEach(el => {
                el.style.animation = 'pulse 2s infinite';
            });

            // Mostrar notificación de pedidos urgentes
            const pedidosUrgentes = document.querySelectorAll('.border-r-red-500').length;
            if (pedidosUrgentes > 0) {
                console.log(`⚠️ Hay ${pedidosUrgentes} pedido(s) urgente(s)`);
                
                // Opcional: mostrar una notificación discreta
                if (pedidosUrgentes > 2) {
                    const notification = document.createElement('div');
                    notification.className = 'fixed top-4 right-4 bg-red-500 text-white px-4 py-2 rounded-lg shadow-lg z-50';
                    notification.innerHTML = `<i class="fas fa-exclamation-triangle mr-2"></i>${pedidosUrgentes} pedidos urgentes`;
                    document.body.appendChild(notification);
                    
                    // Auto-remove después de 5 segundos
                    setTimeout(() => {
                        notification.remove();
                    }, 5000);
                }
            }

            // Animación sutil para las estadísticas
            const statsCards = document.querySelectorAll('.bg-white.p-4.rounded-lg.shadow.text-center');
            statsCards.forEach((card, index) => {
                setTimeout(() => {
                    card.style.transform = 'scale(1.05)';
                    setTimeout(() => {
                        card.style.transform = 'scale(1)';
                        card.style.transition = 'transform 0.2s ease';
                    }, 100);
                }, index * 50);
            });

            // Destacar pedidos del día actual
            const hoy = new Date().toISOString().split('T')[0];
            const fechaDesde = document.querySelector('input[name="fecha_desde"]').value;
            const fechaHasta = document.querySelector('input[name="fecha_hasta"]').value;
            
            if (fechaDesde === hoy && fechaHasta === hoy) {
                document.body.classList.add('hoy-mode');
                console.log('Modo "HOY" activo - Mostrando pedidos de hoy');
            }
        });

        // Función para destacar visualmente los cambios de estado
        function highlightEstadoChange(pedidoId) {
            const pedidoCard = document.querySelector(`[data-pedido-id="${pedidoId}"]`);
            if (pedidoCard) {
                pedidoCard.style.backgroundColor = '#f0f9ff';
                setTimeout(() => {
                    pedidoCard.style.backgroundColor = '';
                }, 2000);
            }
        }

        // Keyboard shortcuts útiles para empleados
        document.addEventListener('keydown', function(e) {
            // Ctrl + H = Filtro HOY
            if (e.ctrlKey && e.key === 'h') {
                e.preventDefault();
                setFiltroRapido('hoy');
            }
            
            // Ctrl + R = Refresh manual
            if (e.ctrlKey && e.key === 'r') {
                e.preventDefault();
                location.reload();
            }
        });

        // Mostrar tiempo real en la esquina (útil para empleados)
        function updateClock() {
            const now = new Date();
            const timeString = now.toLocaleTimeString('es-AR', { 
                hour: '2-digit', 
                minute: '2-digit',
                second: '2-digit'
            });
            
            // Solo mostrar si no existe ya
            let clockElement = document.getElementById('live-clock');
            if (!clockElement) {
                clockElement = document.createElement('div');
                clockElement.id = 'live-clock';
                clockElement.className = 'fixed bottom-4 left-4 bg-blue-600 text-white px-3 py-2 rounded-lg text-sm font-mono shadow-lg';
                document.body.appendChild(clockElement);
            }
            
            clockElement.textContent = timeString;
        }

        // Actualizar reloj cada segundo
        setInterval(updateClock, 1000);
        updateClock(); // Mostrar inmediatamente

        // Debug para empleados
        function mostrarInfoDebug() {
            const totalPedidos = document.querySelectorAll('.bg-white.rounded-lg.shadow.border-l-4').length;
            const urgentes = document.querySelectorAll('.border-r-red-500').length;
            const prioridad = document.querySelectorAll('.border-r-orange-500').length;
            
            console.log('=== INFO PARA EMPLEADO ===');
            console.log(`Total pedidos mostrados: ${totalPedidos}`);
            console.log(`Pedidos urgentes (>1h): ${urgentes}`);
            console.log(`Pedidos prioridad (>30min): ${prioridad}`);
            console.log(`Última actualización: ${new Date().toLocaleString()}`);
            console.log('========================');
        }

        // Mostrar info debug cada vez que se carga
        setTimeout(mostrarInfoDebug, 1000);
    </script>

    <style>
        @keyframes pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.7; }
        }

        /* Estilo especial para el modo "HOY" */
        .hoy-mode .bg-blue-50 {
            background: linear-gradient(135deg, #dbeafe 0%, #bfdbfe 100%);
        }

        /* Mejorar la legibilidad de estados urgentes */
        .border-r-red-500 {
            box-shadow: 0 0 10px rgba(239, 68, 68, 0.3);
        }

        .border-r-orange-500 {
            box-shadow: 0 0 5px rgba(245, 158, 11, 0.3);
        }

        /* Hover suave para los botones de filtro rápido */
        button[onclick^="setFiltroRapido"] {
            transition: all 0.2s ease;
        }

        button[onclick^="setFiltroRapido"]:hover {
            transform: translateY(-1px);
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }

        /* Estilo para el reloj en vivo */
        #live-clock {
            z-index: 40;
            font-family: 'Courier New', monospace;
        }

        /* Responsive mejoras */
        @media (max-width: 768px) {
            .grid.md\\:grid-cols-2 {
                grid-template-columns: 1fr;
            }
            
            #live-clock {
                bottom: 60px; /* Evitar superposición en móviles */
                right: 4px;
                left: auto;
                font-size: 12px;
                padding: 8px;
            }
        }
    </style>
</body>
</html>