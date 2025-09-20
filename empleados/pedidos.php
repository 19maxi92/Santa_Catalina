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

// FILTROS MEJORADOS - Por defecto mostrar HOY
$filtro_estado = isset($_GET['estado']) ? sanitize($_GET['estado']) : '';
$filtro_fecha_desde = isset($_GET['fecha_desde']) ? sanitize($_GET['fecha_desde']) : date('Y-m-d');
$filtro_fecha_hasta = isset($_GET['fecha_hasta']) ? sanitize($_GET['fecha_hasta']) : date('Y-m-d');
$buscar = isset($_GET['buscar']) ? sanitize($_GET['buscar']) : '';

// Si se selecciona "todo", limpiar filtros de fecha
if (isset($_GET['filtro_rapido']) && $_GET['filtro_rapido'] === 'todo') {
    $filtro_fecha_desde = '';
    $filtro_fecha_hasta = '';
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
    $params[] = "%$buscar%";
    $params[] = "%$buscar%";
    $params[] = "%$buscar%";
    $params[] = "%$buscar%";
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

// Estadísticas del período filtrado
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
    <style>
        @media print {
            .no-print { display: none !important; }
            body { font-size: 12px; }
            .print-comanda { page-break-after: always; }
        }
        .btn-imprimir:hover { transform: scale(1.05); }
        
        @keyframes pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.7; }
        }
        
        .border-r-red-500 {
            box-shadow: 0 0 10px rgba(239, 68, 68, 0.3);
        }
        
        .border-r-orange-500 {
            box-shadow: 0 0 5px rgba(245, 158, 11, 0.3);
        }
    </style>
</head>
<body class="bg-gray-100">
    <!-- Header -->
    <header class="bg-blue-600 text-white shadow-md no-print">
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
        <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4 no-print">
            <i class="fas fa-check-circle mr-2"></i><?= $mensaje ?>
        </div>
        <?php endif; ?>

        <?php if ($error): ?>
        <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4 no-print">
            <i class="fas fa-exclamation-circle mr-2"></i><?= $error ?>
        </div>
        <?php endif; ?>

        <!-- Estadísticas del período -->
        <div class="grid grid-cols-2 md:grid-cols-6 gap-4 mb-6 no-print">
            <div class="bg-white rounded-lg p-4 shadow">
                <div class="flex items-center">
                    <div class="p-2 bg-blue-100 rounded-lg">
                        <i class="fas fa-list-ol text-blue-600"></i>
                    </div>
                    <div class="ml-3">
                        <p class="text-sm font-medium text-gray-500">Total</p>
                        <p class="text-lg font-semibold text-gray-900"><?= $stats['total'] ?></p>
                    </div>
                </div>
            </div>
            
            <div class="bg-white rounded-lg p-4 shadow">
                <div class="flex items-center">
                    <div class="p-2 bg-orange-100 rounded-lg">
                        <i class="fas fa-clock text-orange-600"></i>
                    </div>
                    <div class="ml-3">
                        <p class="text-sm font-medium text-gray-500">Pendientes</p>
                        <p class="text-lg font-semibold text-gray-900"><?= $stats['pendientes'] ?></p>
                    </div>
                </div>
            </div>
            
            <div class="bg-white rounded-lg p-4 shadow">
                <div class="flex items-center">
                    <div class="p-2 bg-yellow-100 rounded-lg">
                        <i class="fas fa-fire text-yellow-600"></i>
                    </div>
                    <div class="ml-3">
                        <p class="text-sm font-medium text-gray-500">Preparando</p>
                        <p class="text-lg font-semibold text-gray-900"><?= $stats['preparando'] ?></p>
                    </div>
                </div>
            </div>
            
            <div class="bg-white rounded-lg p-4 shadow">
                <div class="flex items-center">
                    <div class="p-2 bg-green-100 rounded-lg">
                        <i class="fas fa-check text-green-600"></i>
                    </div>
                    <div class="ml-3">
                        <p class="text-sm font-medium text-gray-500">Listos</p>
                        <p class="text-lg font-semibold text-gray-900"><?= $stats['listos'] ?></p>
                    </div>
                </div>
            </div>
            
            <div class="bg-white rounded-lg p-4 shadow">
                <div class="flex items-center">
                    <div class="p-2 bg-purple-100 rounded-lg">
                        <i class="fas fa-truck text-purple-600"></i>
                    </div>
                    <div class="ml-3">
                        <p class="text-sm font-medium text-gray-500">Entregados</p>
                        <p class="text-lg font-semibold text-gray-900"><?= $stats['entregados'] ?></p>
                    </div>
                </div>
            </div>
            
            <div class="bg-white rounded-lg p-4 shadow">
                <div class="flex items-center">
                    <div class="p-2 bg-green-100 rounded-lg">
                        <i class="fas fa-dollar-sign text-green-600"></i>
                    </div>
                    <div class="ml-3">
                        <p class="text-sm font-medium text-gray-500">Ventas</p>
                        <p class="text-lg font-semibold text-gray-900">$<?= number_format($stats['total_ventas'], 0, ',', '.') ?></p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Filtros -->
        <div class="bg-white rounded-lg shadow p-6 mb-6 no-print">
            <form method="GET" id="filtrosForm">
                <!-- Botones de filtro rápido -->
                <div class="mb-4">
                    <h3 class="text-lg font-semibold text-gray-800 mb-3">
                        <i class="fas fa-filter text-blue-500 mr-2"></i>Filtros Rápidos
                    </h3>
                    <div class="flex flex-wrap gap-2">
                        <button type="button" onclick="filtrarHoy()" 
                                class="bg-blue-100 hover:bg-blue-200 text-blue-800 px-3 py-1 rounded text-sm transition">
                            <i class="fas fa-calendar-day mr-1"></i>Hoy
                        </button>
                        <button type="button" onclick="filtrarAyer()" 
                                class="bg-gray-100 hover:bg-gray-200 text-gray-800 px-3 py-1 rounded text-sm transition">
                            <i class="fas fa-calendar-minus mr-1"></i>Ayer
                        </button>
                        <button type="button" onclick="filtrarSemana()" 
                                class="bg-green-100 hover:bg-green-200 text-green-800 px-3 py-1 rounded text-sm transition">
                            <i class="fas fa-calendar-week mr-1"></i>Esta semana
                        </button>
                        <button type="button" onclick="filtrarMes()" 
                                class="bg-purple-100 hover:bg-purple-200 text-purple-800 px-3 py-1 rounded text-sm transition">
                            <i class="fas fa-calendar mr-1"></i>Este mes
                        </button>
                        <button type="button" onclick="filtrarTodo()" 
                                class="bg-orange-100 hover:bg-orange-200 text-orange-800 px-3 py-1 rounded text-sm transition">
                            <i class="fas fa-calendar-alt mr-1"></i>Todo
                        </button>
                    </div>
                </div>

                <!-- Filtros detallados -->
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
                            <option value="">Todos</option>
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
                    
                    <!-- Botón filtrar -->
                    <div class="flex items-end">
                        <button type="submit" class="w-full bg-blue-600 hover:bg-blue-700 text-white font-medium py-2 px-4 rounded-lg transition">
                            <i class="fas fa-search mr-1"></i>Filtrar
                        </button>
                    </div>
                </div>
                
                <!-- Botón limpiar filtros -->
                <div class="flex justify-between items-center mt-4">
                    <a href="?" class="bg-gray-500 hover:bg-gray-600 text-white px-4 py-2 rounded-lg text-sm">
                        <i class="fas fa-eraser mr-2"></i>Limpiar Filtros
                    </a>
                    <div class="text-sm text-gray-600">
                        <i class="fas fa-info-circle mr-1"></i>
                        Los pedidos se ordenan por prioridad de estado
                    </div>
                </div>
            </form>
        </div>

        <!-- Header con botón imprimir todos -->
        <div class="flex justify-between items-center mb-4 no-print">
            <h3 class="text-lg font-semibold text-gray-800">
                <i class="fas fa-list mr-2"></i>Pedidos 
                <?php if ($filtro_fecha_desde === $filtro_fecha_hasta && $filtro_fecha_desde === date('Y-m-d')): ?>
                    de Hoy
                <?php elseif ($filtro_fecha_desde && $filtro_fecha_hasta): ?>
                    del <?= date('d/m/Y', strtotime($filtro_fecha_desde)) ?> al <?= date('d/m/Y', strtotime($filtro_fecha_hasta)) ?>
                <?php else: ?>
                    (Todos)
                <?php endif; ?>
                (<?= count($pedidos) ?>)
            </h3>
            
            <?php if (count($pedidos) > 0): ?>
            <button onclick="imprimirTodos()" class="bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded-lg transition">
                <i class="fas fa-print mr-2"></i>Imprimir Todos
            </button>
            <?php endif; ?>
        </div>

        <!-- Lista de pedidos compacta -->
        <div class="space-y-3">
            <?php if (empty($pedidos)): ?>
                <div class="bg-white p-12 rounded-lg shadow text-center text-gray-500">
                    <i class="fas fa-inbox text-6xl mb-4 text-gray-300"></i>
                    <h3 class="text-xl mb-2">No hay pedidos</h3>
                    <p>No se encontraron pedidos para los filtros seleccionados</p>
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
                    if ($minutos > 60 && in_array($pedido['estado'], ['Pendiente', 'Preparando'])) {
                        $urgencia_class = 'border-r-4 border-r-red-500';
                    } elseif ($minutos > 30 && in_array($pedido['estado'], ['Pendiente', 'Preparando'])) {
                        $urgencia_class = 'border-r-4 border-r-orange-500';
                    }
                    ?>
                    
                    <div class="bg-white rounded-lg shadow border-l-4 <?= $estado_colors[$pedido['estado']] ?> <?= $urgencia_class ?> print-comanda" 
                         id="pedido-<?= $pedido['id'] ?>">
                        <div class="p-4">
                            <div class="flex justify-between items-start">
                                <!-- Info principal compacta -->
                                <div class="flex-1">
                                    <div class="flex items-center space-x-3 mb-2">
                                        <span class="text-lg font-bold text-gray-800">#<?= $pedido['id'] ?></span>
                                        <span class="font-semibold text-gray-700">
                                            <?= htmlspecialchars($pedido['nombre'] . ' ' . $pedido['apellido']) ?>
                                        </span>
                                        <?php if ($minutos > 60 && in_array($pedido['estado'], ['Pendiente', 'Preparando'])): ?>
                                            <span class="bg-red-100 text-red-800 px-2 py-1 rounded-full text-xs font-medium">
                                                🚨 URGENTE
                                            </span>
                                        <?php elseif ($minutos > 30 && in_array($pedido['estado'], ['Pendiente', 'Preparando'])): ?>
                                            <span class="bg-orange-100 text-orange-800 px-2 py-1 rounded-full text-xs font-medium">
                                                ⚠️ PRIORIDAD
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                    
                                    <div class="grid md:grid-cols-3 gap-4 text-sm mb-3">
                                        <!-- Columna 1: Contacto -->
                                        <div>
                                            <?php if ($pedido['telefono']): ?>
                                            <p class="text-gray-600 mb-1">
                                                <i class="fas fa-phone w-4 text-blue-500"></i> <?= htmlspecialchars($pedido['telefono']) ?>
                                            </p>
                                            <?php endif; ?>
                                            <?php if ($pedido['direccion']): ?>
                                            <p class="text-gray-600 mb-1">
                                                <i class="fas fa-map-marker-alt w-4 text-red-500"></i> 
                                                <span class="font-medium"><?= htmlspecialchars(substr($pedido['direccion'], 0, 40)) ?><?= strlen($pedido['direccion']) > 40 ? '...' : '' ?></span>
                                            </p>
                                            <?php endif; ?>
                                        </div>
                                        
                                        <!-- Columna 2: Producto -->
                                        <div>
                                            <p class="font-medium text-gray-800 mb-1"><?= htmlspecialchars($pedido['producto']) ?></p>
                                            <p class="text-gray-600">Cantidad: <?= $pedido['cantidad'] ?></p>
                                        </div>
                                        
                                        <!-- Columna 3: Detalles -->
                                        <div>
                                            <p class="text-gray-600 mb-1">
                                                <i class="fas fa-<?= $pedido['modalidad'] === 'Delivery' ? 'truck text-green-500' : 'store text-blue-500' ?> w-4"></i>
                                                <?= $pedido['modalidad'] ?> | <?= $pedido['ubicacion'] ?>
                                            </p>
                                            <p class="text-gray-600">
                                                <i class="fas fa-clock w-4"></i> 
                                                <?= date('H:i', strtotime($pedido['created_at'])) ?> (<?= $minutos ?>min)
                                            </p>
                                        </div>
                                    </div>
                                    
                                    <!-- Observaciones si existen -->
                                    <?php if ($pedido['observaciones']): ?>
                                    <div class="text-xs text-gray-600 bg-gray-50 p-2 rounded mb-2">
                                        <i class="fas fa-comment mr-1"></i>
                                        <?= htmlspecialchars($pedido['observaciones']) ?>
                                    </div>
                                    <?php endif; ?>
                                </div>
                                
                                <!-- Panel derecho: Estado, precio y acciones -->
                                <div class="ml-4 text-right flex flex-col items-end space-y-2">
                                    <!-- Precio -->
                                    <div class="text-lg font-bold text-green-600">
                                        $<?= number_format($pedido['precio'], 0, ',', '.') ?>
                                    </div>
                                    
                                    <!-- Estado -->
                                    <form method="POST" class="no-print">
                                        <input type="hidden" name="accion" value="cambiar_estado">
                                        <input type="hidden" name="id" value="<?= $pedido['id'] ?>">
                                        <select name="estado" onchange="this.form.submit()" 
                                                class="text-xs rounded-full px-2 py-1 font-medium border-0 focus:ring-2 focus:ring-blue-500
                                                <?php 
                                                    switch($pedido['estado']) {
                                                        case 'Pendiente': echo 'bg-orange-100 text-orange-800'; break;
                                                        case 'Preparando': echo 'bg-yellow-100 text-yellow-800'; break;
                                                        case 'Listo': echo 'bg-green-100 text-green-800'; break;
                                                        case 'Entregado': echo 'bg-gray-100 text-gray-800'; break;
                                                    }
                                                ?>">
                                            <option value="Pendiente" <?= $pedido['estado'] === 'Pendiente' ? 'selected' : '' ?>>⏳ Pendiente</option>
                                            <option value="Preparando" <?= $pedido['estado'] === 'Preparando' ? 'selected' : '' ?>>🔥 Preparando</option>
                                            <option value="Listo" <?= $pedido['estado'] === 'Listo' ? 'selected' : '' ?>>✅ Listo</option>
                                            <option value="Entregado" <?= $pedido['estado'] === 'Entregado' ? 'selected' : '' ?>>📦 Entregado</option>
                                        </select>
                                    </form>
                                    
                                    <!-- Acciones -->
                                    <div class="flex space-x-1 no-print">
                                        <!-- Botón imprimir -->
                                        <button onclick="imprimirPedido(<?= $pedido['id'] ?>)" 
                                                class="bg-blue-600 hover:bg-blue-700 text-white px-2 py-1 rounded text-xs transition btn-imprimir"
                                                title="Imprimir comanda">
                                            <i class="fas fa-print"></i>
                                        </button>
                                        
                                        <!-- Botón WhatsApp -->
                                        <a href="https://wa.me/<?= preg_replace('/[^0-9]/', '', $pedido['telefono']) ?>?text=Hola%20<?= urlencode($pedido['nombre']) ?>,%20tu%20pedido%20#<?= $pedido['id'] ?>%20está%20<?= urlencode(strtolower($pedido['estado'])) ?>" 
                                           target="_blank" 
                                           class="bg-green-500 hover:bg-green-600 text-white px-2 py-1 rounded text-xs transition"
                                           title="WhatsApp">
                                            <i class="fab fa-whatsapp"></i>
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <!-- Info adicional -->
        <div class="mt-6 no-print">
            <div class="bg-blue-50 border border-blue-200 rounded-lg p-4">
                <h3 class="font-semibold text-blue-800 mb-2">
                    <i class="fas fa-info-circle mr-2"></i>Información
                </h3>
                <div class="text-sm text-blue-700 space-y-1">
                    <p>• Los pedidos se muestran ordenados por estado y fecha de creación</p>
                    <p>• 🔴 Pedidos urgentes (más de 1 hora) | 🟠 Pedidos con prioridad (más de 30 min)</p>
                    <p>• Use los filtros rápidos para navegar por fechas específicas</p>
                    <p>• La impresión funciona directamente con su impresora USB conectada</p>
                </div>
            </div>
        </div>
    </main>

    <script>
        // FILTROS RÁPIDOS - CORREGIDOS
        function setFiltroRapido(tipo) {
            console.log('Aplicando filtro:', tipo);
            
            const hoy = new Date();
            let desde, hasta;
            
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
                    const diasAtras = diaSemana === 0 ? 6 : diaSemana - 1;
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
            
            console.log('Fechas calculadas:', {desde, hasta});
            
            // Actualizar campos de forma más robusta
            const fechaDesdeInput = document.querySelector('input[name="fecha_desde"]');
            const fechaHastaInput = document.querySelector('input[name="fecha_hasta"]');
            
            if (fechaDesdeInput && fechaHastaInput) {
                fechaDesdeInput.value = desde;
                fechaHastaInput.value = hasta;
                
                console.log('Campos actualizados:', {
                    desde: fechaDesdeInput.value,
                    hasta: fechaHastaInput.value
                });
                
                // Enviar formulario
                const form = document.getElementById('filtrosForm');
                if (form) {
                    console.log('Enviando formulario...');
                    form.submit();
                } else {
                    console.error('Formulario no encontrado');
                }
            } else {
                console.error('No se encontraron los campos de fecha');
                console.log('fechaDesdeInput:', fechaDesdeInput);
                console.log('fechaHastaInput:', fechaHastaInput);
            }
        }
        
        function formatDate(date) {
            const año = date.getFullYear();
            const mes = String(date.getMonth() + 1).padStart(2, '0');
            const dia = String(date.getDate()).padStart(2, '0');
            return `${año}-${mes}-${dia}`;
        }

        // FUNCIONES DE IMPRESIÓN LOCAL - FORMATO COMANDERA
        function imprimirPedido(pedidoId) {
            console.log('Intentando imprimir pedido:', pedidoId);
            
            // Buscar los datos del pedido en la tabla
            const fila = document.getElementById('pedido-' + pedidoId);
            if (!fila) {
                console.error('No se encontró la fila para pedido:', pedidoId);
                alert('Error: No se encontró el pedido #' + pedidoId);
                return;
            }
            
            // Extraer datos de la fila de manera más robusta
            let nombre = 'Sin nombre';
            let producto = 'Sin producto';
            let precio = '$0';
            
            try {
                // Buscar nombre en diferentes posibles ubicaciones
                const nombreElement = fila.querySelector('.font-semibold') || 
                                    fila.querySelector('[class*="font-bold"]');
                if (nombreElement) {
                    // Extraer solo el nombre, quitando el # del pedido
                    const textoCompleto = nombreElement.textContent.trim();
                    const match = textoCompleto.match(/#\d+\s+(.+)/);
                    nombre = match ? match[1] : textoCompleto.replace(/^#\d+\s*/, '');
                }
                
                // Buscar producto
                const productoElement = fila.querySelector('.font-medium.text-gray-800') ||
                                      fila.querySelector('.text-gray-800');
                if (productoElement) {
                    producto = productoElement.textContent.trim();
                }
                
                // Buscar precio
                const precioElement = fila.querySelector('.text-green-600') ||
                                    fila.querySelector('[class*="green"]');
                if (precioElement) {
                    precio = precioElement.textContent.trim();
                }
            } catch (e) {
                console.error('Error extrayendo datos:', e);
            }
            
            console.log('Datos extraídos:', {nombre, producto, precio});
            
            // Determinar turno
            const ahora = new Date();
            let turno = 'T';
            const hora = ahora.getHours();
            
            // Buscar turno específico en observaciones
            try {
                const obsElement = fila.querySelector('.text-gray-600') ||
                                 fila.querySelector('[class*="gray-6"]');
                if (obsElement && obsElement.textContent.includes('Turno delivery:')) {
                    const textoObs = obsElement.textContent.toLowerCase();
                    if (textoObs.includes('mañana')) turno = 'M';
                    else if (textoObs.includes('siesta')) turno = 'S';
                    else if (textoObs.includes('tarde')) turno = 'T';
                }
            } catch (e) {
                console.log('No se pudo extraer turno de observaciones');
            }
            
            // Determinar turno por hora si no se encontró en observaciones
            if (turno === 'T') {
                if (hora >= 8 && hora < 12) turno = 'M';
                else if (hora >= 12 && hora < 16) turno = 'S';
                else turno = 'T';
            }
            
            // Formatear fecha
            const fecha = ahora.getDate() + '-' + ahora.toLocaleDateString('es', {month: 'short'});
            
            // Crear HTML para impresión
            const htmlComanda = `
                <!DOCTYPE html>
                <html>
                <head>
                    <title>Comanda #${pedidoId}</title>
                    <meta charset="UTF-8">
                    <style>
                        @page { 
                            size: 80mm auto; 
                            margin: 2mm; 
                        }
                        body { 
                            font-family: 'Courier New', monospace; 
                            font-size: 12px; 
                            margin: 0; 
                            padding: 5px;
                            width: 75mm;
                            line-height: 1.1;
                            color: #000;
                        }
                        .header {
                            border: 2px solid #000;
                            padding: 5px;
                            margin-bottom: 8px;
                            display: flex;
                            justify-content: space-between;
                            align-items: center;
                        }
                        .fecha {
                            font-weight: bold;
                            font-size: 11px;
                        }
                        .turno {
                            font-size: 16px;
                            font-weight: bold;
                        }
                        .nombre {
                            font-size: 14px;
                            font-weight: bold;
                            text-align: center;
                            margin: 8px 0;
                            word-wrap: break-word;
                        }
                        .producto {
                            font-size: 16px;
                            font-weight: bold;
                            text-align: center;
                            margin: 12px 0;
                            word-wrap: break-word;
                        }
                        .precio-box {
                            border: 2px solid #000;
                            padding: 4px 8px;
                            margin-top: 8px;
                            display: flex;
                            justify-content: space-between;
                            align-items: center;
                        }
                        .precio-principal {
                            font-size: 14px;
                            font-weight: bold;
                        }
                        .precio-numerico {
                            font-size: 11px;
                        }
                        @media print {
                            body { 
                                width: auto; 
                                -webkit-print-color-adjust: exact;
                                color-adjust: exact;
                            }
                        }
                    </style>
                </head>
                <body>
                    <div class="header">
                        <span class="fecha">${fecha}</span>
                        <span class="turno">${turno}</span>
                    </div>
                    
                    <div class="nombre">${nombre}</div>
                    
                    <div class="producto">${producto.replace(/(\d+)\s+/, '$1')}</div>
                    
                    <div class="precio-box">
                        <span class="precio-principal">${precio}</span>
                        <span class="precio-numerico">${precio.replace(', '').replace(/\./g, '')}</span>
                    </div>
                </body>
                </html>
            `;
            
            // Crear nueva ventana para impresión
            const ventanaImpresion = window.open('', '_blank', 'width=320,height=500,scrollbars=no,resizable=no');
            
            if (!ventanaImpresion) {
                alert('No se pudo abrir la ventana de impresión. Verifica que no esté bloqueada por el navegador.');
                return;
            }
            
            try {
                ventanaImpresion.document.write(htmlComanda);
                ventanaImpresion.document.close();
                
                // Esperar a que cargue completamente
                ventanaImpresion.onload = function() {
                    setTimeout(() => {
                        ventanaImpresion.print();
                        // Cerrar después de un delay para dar tiempo a la impresión
                        setTimeout(() => {
                            ventanaImpresion.close();
                        }, 1000);
                    }, 500);
                };
                
                // Fallback si onload no funciona
                setTimeout(() => {
                    try {
                        ventanaImpresion.print();
                        setTimeout(() => {
                            ventanaImpresion.close();
                        }, 1000);
                    } catch (e) {
                        console.error('Error en fallback de impresión:', e);
                    }
                }, 1500);
                
            } catch (error) {
                console.error('Error durante la impresión:', error);
                alert('Error al preparar la impresión: ' + error.message);
                ventanaImpresion.close();
            }
        }
        
        function imprimirTodos() {
            console.log('Iniciando impresión de todos los pedidos');
            
            const filas = document.querySelectorAll('[id^="pedido-"]');
            console.log('Encontradas', filas.length, 'filas para imprimir');
            
            if (filas.length === 0) {
                alert('No hay pedidos para imprimir');
                return;
            }
            
            // Confirmar impresión múltiple
            if (!confirm(`¿Imprimir ${filas.length} comandas individuales?`)) {
                return;
            }
            
            filas.forEach((fila, index) => {
                const pedidoId = fila.id.replace('pedido-', '');
                console.log('Programando impresión del pedido', pedidoId, 'con delay', index * 1500);
                
                setTimeout(() => {
                    imprimirPedido(pedidoId);
                }, index * 1500); // Delay de 1.5 segundos entre cada impresión
            });
        }

        // Auto refresh cada 30 segundos (importante para empleados)
        setInterval(function() {
            // Solo hacer refresh si no hay un formulario siendo editado
            if (!document.activeElement || document.activeElement.tagName !== 'SELECT') {
                location.reload();
            }
        }, 30000);

        // Efectos visuales y debugging para empleados
        document.addEventListener('DOMContentLoaded', function() {
            console.log('=== DASHBOARD EMPLEADOS CARGADO ===');
            
            // Verificar que las funciones están disponibles
            console.log('Función imprimirPedido:', typeof window.imprimirPedido);
            console.log('Función imprimirTodos:', typeof window.imprimirTodos);
            console.log('Función setFiltroRapido:', typeof window.setFiltroRapido);
            
            // Verificar formulario
            const form = document.getElementById('filtrosForm');
            console.log('Formulario filtrosForm encontrado:', !!form);
            
            // Verificar campos de fecha
            const fechaDesde = document.querySelector('input[name="fecha_desde"]');
            const fechaHasta = document.querySelector('input[name="fecha_hasta"]');
            console.log('Campo fecha_desde encontrado:', !!fechaDesde);
            console.log('Campo fecha_hasta encontrado:', !!fechaHasta);
            
            // Contar pedidos y botones
            const pedidos = document.querySelectorAll('[id^="pedido-"]');
            const botonesImprimir = document.querySelectorAll('button[onclick*="imprimirPedido"]');
            console.log('Pedidos encontrados:', pedidos.length);
            console.log('Botones imprimir encontrados:', botonesImprimir.length);
            
            // Highlight de pedidos urgentes
            const urgentes = document.querySelectorAll('.border-r-red-500');
            urgentes.forEach(el => {
                el.style.animation = 'pulse 2s infinite';
            });

            // Mostrar notificación de pedidos urgentes
            const pedidosUrgentes = urgentes.length;
            if (pedidosUrgentes > 0) {
                console.log(`⚠️ Hay ${pedidosUrgentes} pedido(s) urgente(s)`);
            }
            
            // Test rápido de botones
            console.log('=== TEST DE BOTONES ===');
            botonesImprimir.forEach((btn, index) => {
                console.log(`Botón ${index + 1}:`, btn.getAttribute('onclick'));
            });
            
            console.log('===========================');
        });
        
        // Función de test manual
        function testImpresion() {
            console.log('Test manual de impresión');
            const primerPedido = document.querySelector('[id^="pedido-"]');
            if (primerPedido) {
                const pedidoId = primerPedido.id.replace('pedido-', '');
                console.log('Probando con pedido:', pedidoId);
                imprimirPedido(pedidoId);
            } else {
                console.log('No hay pedidos para probar');
            }
        }
        
        // Función de test de filtros
        function testFiltros() {
            console.log('Test manual de filtros');
            setFiltroRapido('hoy');
        }
        
        // Exponer funciones de test
        window.testImpresion = testImpresion;
        window.testFiltros = testFiltros;
    </script>
</body>
</html>