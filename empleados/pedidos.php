<?php
require_once '../admin/config.php';
session_start();

if (!isset($_SESSION['empleado_logged']) || $_SESSION['empleado_logged'] !== true) {
    header('Location: login.php');
    exit;
}

$pdo = getConnection();

// Procesar acciones
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion'])) {
    switch ($_POST['accion']) {
        case 'cambiar_estado':
            $id = (int)$_POST['id'];
            $estado = htmlspecialchars(trim($_POST['estado']));
            $stmt = $pdo->prepare("UPDATE pedidos SET estado = ? WHERE id = ?");
            $stmt->execute([$estado, $id]);
            header('Location: pedidos.php');
            exit;
            
        case 'marcar_impreso':
            $id = (int)$_POST['id'];
            $stmt = $pdo->prepare("UPDATE pedidos SET impreso = 1 WHERE id = ?");
            $stmt->execute([$id]);
            header('Location: pedidos.php');
            exit;
    }
}

// Filtros
$filtro_estado = $_GET['estado'] ?? '';
$filtro_fecha_desde = $_GET['fecha_desde'] ?? date('Y-m-d');
$filtro_fecha_hasta = $_GET['fecha_hasta'] ?? date('Y-m-d');
$filtro_buscar = $_GET['buscar'] ?? '';

// Query base
$sql = "SELECT id, nombre, apellido, producto, precio, estado, modalidad,
               observaciones, telefono, forma_pago, cantidad, impreso,
               created_at, fecha_entrega, fecha_display,
               TIMESTAMPDIFF(MINUTE, created_at, NOW()) as minutos_transcurridos
        FROM pedidos
        WHERE ubicacion = 'Local 1'
        AND (
            DATE(created_at) BETWEEN :fecha_desde AND :fecha_hasta
            OR (fecha_entrega IS NOT NULL AND DATE(fecha_entrega) BETWEEN :fecha_desde AND :fecha_hasta)
        )";

$params = [
    'fecha_desde' => $filtro_fecha_desde,
    'fecha_hasta' => $filtro_fecha_hasta
];

if ($filtro_estado) {
    $sql .= " AND estado = :estado";
    $params['estado'] = $filtro_estado;
}

if ($filtro_buscar) {
    $sql .= " AND (nombre LIKE :buscar OR apellido LIKE :buscar OR producto LIKE :buscar OR CAST(id AS CHAR) LIKE :buscar)";
    $params['buscar'] = "%$filtro_buscar%";
}

$sql .= " ORDER BY created_at DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$pedidos = $stmt->fetchAll();

// Stats
$total = count($pedidos);
$pendientes = count(array_filter($pedidos, fn($p) => $p['estado'] === 'Pendiente'));
$preparando = count(array_filter($pedidos, fn($p) => $p['estado'] === 'Preparando'));
$listos = count(array_filter($pedidos, fn($p) => $p['estado'] === 'Listo'));
$entregados = count(array_filter($pedidos, fn($p) => $p['estado'] === 'Entregado'));
$sin_imprimir = count(array_filter($pedidos, fn($p) => $p['impreso'] == 0));
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ver Pedidos - Local 1</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { font-family: 'Arial', sans-serif; }
        
        .pedido-item {
            transition: all 0.2s ease;
            border-left: 4px solid #ccc;
        }
        .pedido-item:hover {
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
            transform: translateX(2px);
        }
        .pedido-pendiente { border-left-color: #f59e0b; background: #fffbeb; }
        .pedido-preparando { border-left-color: #3b82f6; background: #eff6ff; }
        .pedido-listo { border-left-color: #10b981; background: #f0fdf4; }
        .pedido-entregado { border-left-color: #6b7280; background: #f9fafb; }
        
        .urgente { animation: pulse 2s infinite; }
        @keyframes pulse { 0%, 100% { opacity: 1; } 50% { opacity: 0.85; } }
        
        .filter-tab {
            padding: 8px 16px;
            border-radius: 6px;
            cursor: pointer;
            transition: all 0.2s;
            font-weight: 600;
            font-size: 13px;
        }
        .filter-tab.active {
            background: #3b82f6;
            color: white;
            box-shadow: 0 4px 8px rgba(59, 130, 246, 0.3);
        }
        
        .badge {
            display: inline-block;
            padding: 3px 8px;
            border-radius: 12px;
            font-size: 10px;
            font-weight: 700;
            text-transform: uppercase;
        }
        
        .btn-compact {
            padding: 6px 12px;
            border-radius: 4px;
            font-size: 11px;
            font-weight: 600;
            border: none;
            cursor: pointer;
            transition: all 0.2s;
            display: inline-flex;
            align-items: center;
            gap: 4px;
        }
        .btn-compact:hover { transform: scale(1.05); }
        
        .pedidos-container {
            max-height: calc(100vh - 200px); /* AUMENTADO: antes era 280px */
            overflow-y: auto;
            padding-bottom: 20px; /* Un poco de espacio al final */
        }
        .pedidos-container::-webkit-scrollbar {
            width: 8px;
        }
        .pedidos-container::-webkit-scrollbar-track {
            background: #f1f1f1;
        }
        .pedidos-container::-webkit-scrollbar-thumb {
            background: #888;
            border-radius: 4px;
        }
        .pedidos-container::-webkit-scrollbar-thumb:hover {
            background: #555;
        }
        
        /* Eliminar espacios innecesarios al final */
        body {
            overflow-x: hidden;
        }
        main {
            min-height: calc(100vh - 200px);
        }
    </style>
</head>
<body class="bg-gray-50">
    
    <!-- HEADER COMPACTO -->
    <header class="bg-green-600 text-white p-3 shadow-lg sticky top-0 z-50">
        <div class="max-w-7xl mx-auto flex justify-between items-center">
            <div class="flex items-center space-x-3">
                <h1 class="text-xl font-bold">📋 VER PEDIDOS - LOCAL 1</h1>
            </div>
            
            <!-- STATS COMPACTOS -->
            <div class="flex space-x-4 text-xs">
                <div class="text-center">
                    <div class="text-lg font-bold"><?= $total ?></div>
                    <div class="text-green-200">Total</div>
                </div>
                <div class="text-center text-yellow-300">
                    <div class="text-lg font-bold"><?= $pendientes ?></div>
                    <div>Pend.</div>
                </div>
                <div class="text-center text-blue-200">
                    <div class="text-lg font-bold"><?= $preparando ?></div>
                    <div>Prep.</div>
                </div>
                <div class="text-center text-green-200">
                    <div class="text-lg font-bold"><?= $listos ?></div>
                    <div>Listos</div>
                </div>
                <div class="text-center text-gray-200">
                    <div class="text-lg font-bold"><?= $entregados ?></div>
                    <div>Entre.</div>
                </div>
                <?php if ($sin_imprimir > 0): ?>
                <div class="text-center text-red-200">
                    <div class="text-lg font-bold"><?= $sin_imprimir ?></div>
                    <div>Sin Imp.</div>
                </div>
                <?php endif; ?>
            </div>
            
            <div class="flex items-center space-x-2">
                <a href="dashboard.php" class="bg-green-500 hover:bg-green-400 px-3 py-1.5 rounded text-xs">
                    <i class="fas fa-arrow-left mr-1"></i>Dashboard
                </a>
                <a href="logout.php" class="bg-red-500 hover:bg-red-600 px-3 py-1.5 rounded text-xs">
                    <i class="fas fa-sign-out-alt mr-1"></i>Salir
                </a>
            </div>
        </div>
    </header>

    <!-- FILTROS COMPACTOS -->
    <div class="bg-white border-b p-3 sticky top-14 z-40 shadow-sm">
        <div class="max-w-7xl mx-auto">
            <!-- TABS DE FILTRO POR ESTADO -->
            <div class="flex space-x-2 mb-3">
                <a href="?fecha_desde=<?= $filtro_fecha_desde ?>&fecha_hasta=<?= $filtro_fecha_hasta ?>" 
                   class="filter-tab <?= empty($filtro_estado) ? 'active' : 'bg-gray-100 text-gray-700' ?>">
                    Todos (<?= $total ?>)
                </a>
                <a href="?estado=Pendiente&fecha_desde=<?= $filtro_fecha_desde ?>&fecha_hasta=<?= $filtro_fecha_hasta ?>" 
                   class="filter-tab <?= $filtro_estado === 'Pendiente' ? 'active' : 'bg-yellow-100 text-yellow-800' ?>">
                    Pendientes (<?= $pendientes ?>)
                </a>
                <a href="?estado=Preparando&fecha_desde=<?= $filtro_fecha_desde ?>&fecha_hasta=<?= $filtro_fecha_hasta ?>" 
                   class="filter-tab <?= $filtro_estado === 'Preparando' ? 'active' : 'bg-blue-100 text-blue-800' ?>">
                    Preparando (<?= $preparando ?>)
                </a>
                <a href="?estado=Listo&fecha_desde=<?= $filtro_fecha_desde ?>&fecha_hasta=<?= $filtro_fecha_hasta ?>" 
                   class="filter-tab <?= $filtro_estado === 'Listo' ? 'active' : 'bg-green-100 text-green-800' ?>">
                    Listos (<?= $listos ?>)
                </a>
                <a href="?estado=Entregado&fecha_desde=<?= $filtro_fecha_desde ?>&fecha_hasta=<?= $filtro_fecha_hasta ?>" 
                   class="filter-tab <?= $filtro_estado === 'Entregado' ? 'active' : 'bg-gray-100 text-gray-700' ?>">
                    Entregados (<?= $entregados ?>)
                </a>
            </div>
            
            <!-- FILTROS ADICIONALES (COLAPSABLE) -->
            <details class="text-sm">
                <summary class="cursor-pointer text-gray-600 hover:text-gray-800 font-medium">
                    <i class="fas fa-filter mr-1"></i>Filtros avanzados
                </summary>
                <form method="GET" class="mt-3 grid grid-cols-1 md:grid-cols-4 gap-3">
                    <input type="text" name="buscar" value="<?= htmlspecialchars($filtro_buscar) ?>" 
                           placeholder="Buscar por nombre, #ID o producto..." 
                           class="px-3 py-2 border rounded text-sm">
                    
                    <input type="date" name="fecha_desde" value="<?= $filtro_fecha_desde ?>" 
                           class="px-3 py-2 border rounded text-sm">
                    
                    <input type="date" name="fecha_hasta" value="<?= $filtro_fecha_hasta ?>" 
                           class="px-3 py-2 border rounded text-sm">
                    
                    <div class="flex gap-2">
                        <button type="submit" class="flex-1 bg-blue-500 hover:bg-blue-600 text-white px-4 py-2 rounded text-sm">
                            <i class="fas fa-search mr-1"></i>Buscar
                        </button>
                        <a href="pedidos.php" class="flex-1 text-center bg-gray-500 hover:bg-gray-600 text-white px-4 py-2 rounded text-sm">
                            <i class="fas fa-times"></i>
                        </a>
                    </div>
                </form>
            </details>
        </div>
    </div>

    <!-- LISTA DE PEDIDOS -->
    <main class="max-w-7xl mx-auto p-3"> <!-- REDUCIDO: antes era p-4 -->
        <?php if (empty($pedidos)): ?>
            <div class="bg-white rounded-lg shadow p-12 text-center">
                <i class="fas fa-inbox text-6xl text-gray-300 mb-4"></i>
                <h2 class="text-2xl text-gray-500 mb-2">No hay pedidos</h2>
                <p class="text-gray-400 mb-4">Intenta ajustar los filtros</p>
                <a href="pedidos.php" class="bg-blue-500 hover:bg-blue-600 text-white px-6 py-3 rounded-lg">
                    <i class="fas fa-list mr-2"></i>Ver Todos
                </a>
            </div>
        <?php else: ?>
            <div class="pedidos-container space-y-2"> <!-- REDUCIDO: antes era space-y-2, ahora más compacto -->
                <?php foreach ($pedidos as $pedido): ?>
                    <div class="pedido-item pedido-<?= strtolower($pedido['estado']) ?> 
                                <?= $pedido['minutos_transcurridos'] > 60 && $pedido['estado'] !== 'Entregado' ? 'urgente' : '' ?>
                                bg-white rounded-lg shadow p-3"> <!-- REDUCIDO: antes era p-4 -->
                        
                        <div class="flex items-start justify-between gap-4">
                            <!-- INFO PRINCIPAL (70%) -->
                            <div class="flex-1 grid grid-cols-1 md:grid-cols-3 gap-3">
                                <!-- COLUMNA 1: CLIENTE -->
                                <div>
                                    <div class="font-bold text-sm text-gray-800 mb-1">
                                        #<?= $pedido['id'] ?> - <?= htmlspecialchars($pedido['nombre'] . ' ' . $pedido['apellido']) ?>
                                    </div>
                                    <div class="text-xs text-gray-600">
                                        <i class="fas fa-phone"></i> <?= htmlspecialchars($pedido['telefono']) ?>
                                    </div>
                                    <div class="text-xs text-gray-500 mt-1">
                                        <i class="fas fa-clock"></i> <?= $pedido['minutos_transcurridos'] ?>min
                                        <?php if ($pedido['minutos_transcurridos'] > 60 && $pedido['estado'] !== 'Entregado'): ?>
                                            <span class="badge bg-red-500 text-white ml-1">URGENTE</span>
                                        <?php endif; ?>
                                        <?php if (!$pedido['impreso']): ?>
                                            <span class="badge bg-orange-500 text-white ml-1">SIN IMP</span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                
                                <!-- COLUMNA 2: PRODUCTO -->
                                <div>
                                    <div class="font-bold text-sm text-gray-800"><?= htmlspecialchars($pedido['producto']) ?></div>
                                    <div class="text-xs text-gray-600 mt-1">
                                        <span><i class="fas fa-<?= $pedido['modalidad'] === 'Retiro' ? 'store' : 'motorcycle' ?>"></i> <?= $pedido['modalidad'] ?></span>
                                        <span class="ml-2"><i class="fas fa-money-bill"></i> <?= $pedido['forma_pago'] ?></span>
                                    </div>
                                    <div class="text-green-600 font-bold text-lg mt-1">
                                        $<?= number_format($pedido['precio'], 0, ',', '.') ?>
                                    </div>
                                </div>
                                
                                <!-- COLUMNA 3: OBSERVACIONES -->
                                <div>
                                    <?php if (!empty($pedido['fecha_entrega'])): ?>
                                        <div class="text-xs mb-2">
                                            <span class="badge bg-purple-500 text-white">
                                                📅 Entrega: <?= date('d/m/Y', strtotime($pedido['fecha_entrega'])) ?>
                                            </span>
                                        </div>
                                    <?php endif; ?>
                                    <?php if ($pedido['observaciones']): ?>
                                        <details>
                                            <summary class="text-xs text-blue-600 cursor-pointer hover:underline">
                                                <i class="fas fa-sticky-note"></i> Ver observaciones
                                            </summary>
                                            <div class="text-xs bg-blue-50 p-2 mt-1 rounded max-h-20 overflow-y-auto">
                                                <?= nl2br(htmlspecialchars($pedido['observaciones'])) ?>
                                            </div>
                                        </details>
                                    <?php else: ?>
                                        <span class="text-xs text-gray-400">Sin observaciones</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                            
                            <!-- ACCIONES (30%) -->
                            <div class="flex flex-col gap-2 items-end">
                                <!-- CAMBIAR ESTADO -->
                                <form method="POST" class="w-full">
                                    <input type="hidden" name="accion" value="cambiar_estado">
                                    <input type="hidden" name="id" value="<?= $pedido['id'] ?>">
                                    <select name="estado" onchange="if(confirm('¿Cambiar estado?')) this.form.submit()" 
                                            class="w-full text-xs border rounded px-2 py-1">
                                        <option value="Pendiente" <?= $pedido['estado'] === 'Pendiente' ? 'selected' : '' ?>>🔴 Pendiente</option>
                                        <option value="Preparando" <?= $pedido['estado'] === 'Preparando' ? 'selected' : '' ?>>🔵 Preparando</option>
                                        <option value="Listo" <?= $pedido['estado'] === 'Listo' ? 'selected' : '' ?>>🟢 Listo</option>
                                        <option value="Entregado" <?= $pedido['estado'] === 'Entregado' ? 'selected' : '' ?>>⚫ Entregado</option>
                                    </select>
                                </form>
                                
                                <!-- BOTONES -->
                                <div class="flex gap-1 w-full">
                                    <!-- IMPRIMIR -->
                                    <button onclick="imprimir(<?= $pedido['id'] ?>)" 
                                            class="btn-compact flex-1" style="background: #f59e0b; color: white;"
                                            title="Imprimir comanda">
                                        <i class="fas fa-print"></i>
                                    </button>
                                    
                                    <!-- MARCAR IMPRESO -->
                                    <?php if (!$pedido['impreso']): ?>
                                        <form method="POST" class="flex-1">
                                            <input type="hidden" name="accion" value="marcar_impreso">
                                            <input type="hidden" name="id" value="<?= $pedido['id'] ?>">
                                            <button type="submit" class="btn-compact w-full" style="background: #10b981; color: white;"
                                                    title="Marcar como impreso">
                                                <i class="fas fa-check"></i>
                                            </button>
                                        </form>
                                    <?php endif; ?>
                                    
                                    <!-- WHATSAPP -->
                                    <?php if ($pedido['telefono']): ?>
                                        <a href="https://wa.me/54<?= preg_replace('/[^0-9]/', '', $pedido['telefono']) ?>" 
                                           target="_blank"
                                           class="btn-compact flex-1" style="background: #25D366; color: white;"
                                           title="Enviar WhatsApp">
                                            <i class="fab fa-whatsapp"></i>
                                        </a>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </main>

    <script>
    // FUNCIÓN UNIFICADA - USA COMANDA_SIMPLE.PHP
    function imprimir(pedidoId) {
        console.log('🖨️ Imprimiendo comanda simple - Pedido #' + pedidoId);
        
        const url = `comanda_simple.php?pedido=${pedidoId}`;
        const ventana = window.open(url, '_blank', 'width=400,height=650,scrollbars=yes');
        
        if (!ventana) {
            alert('❌ Error: No se pudo abrir la ventana.\nPermite ventanas emergentes.');
            return false;
        }
        
        ventana.focus();
        console.log('✅ Comanda abierta exitosamente');
        return true;
    }

    console.log('📋 Ver Pedidos cargado - Total: <?= $total ?> pedidos');
    </script>

</body>
</html>