<?php
require_once '../admin/config.php';
session_start();

if (!isset($_SESSION['empleado_logged']) || $_SESSION['empleado_logged'] !== true) {
    header('Location: login.php');
    exit;
}

$pdo = getConnection();

// Procesar acciones AJAX
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion'])) {
    header('Content-Type: application/json');
    
    switch ($_POST['accion']) {
        case 'marcar_impreso':
            $pedido_id = (int)$_POST['pedido_id'];
            $stmt = $pdo->prepare("UPDATE pedidos SET impreso = 1 WHERE id = ?");
            $result = $stmt->execute([$pedido_id]);
            echo json_encode(['success' => $result]);
            exit;
            
        case 'cambiar_estado':
            $pedido_id = (int)$_POST['pedido_id'];
            $nuevo_estado = htmlspecialchars(strip_tags(trim($_POST['nuevo_estado'])));
            $stmt = $pdo->prepare("UPDATE pedidos SET estado = ? WHERE id = ?");
            $result = $stmt->execute([$nuevo_estado, $pedido_id]);
            echo json_encode(['success' => $result]);
            exit;
    }
}

// Obtener pedidos
$pedidos = $pdo->query("
    SELECT id, nombre, apellido, producto, precio, estado, modalidad,
           observaciones, direccion, telefono, forma_pago, cantidad,
           created_at, TIMESTAMPDIFF(MINUTE, created_at, NOW()) as minutos_transcurridos,
           impreso
    FROM pedidos 
    WHERE ubicacion = 'Local 1'
    AND DATE(created_at) = CURDATE()
    AND estado != 'Entregado'
    ORDER BY 
        CASE estado 
            WHEN 'Pendiente' THEN 1 
            WHEN 'Preparando' THEN 2 
            WHEN 'Listo' THEN 3 
        END, 
        created_at ASC
")->fetchAll();

// Stats
$total = count($pedidos);
$pendientes = count(array_filter($pedidos, fn($p) => $p['estado'] === 'Pendiente'));
$preparando = count(array_filter($pedidos, fn($p) => $p['estado'] === 'Preparando'));
$listos = count(array_filter($pedidos, fn($p) => $p['estado'] === 'Listo'));
$sin_imprimir = count(array_filter($pedidos, fn($p) => $p['impreso'] == 0));
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Local 1 - Dashboard</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { font-family: 'Arial', sans-serif; }
        
        /* VISTA COMPACTA */
        .pedido-compacto {
            transition: all 0.2s ease;
            border-left: 4px solid #ccc;
        }
        .pedido-compacto:hover {
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
            transform: translateY(-2px);
        }
        .pedido-pendiente { border-left-color: #f59e0b; background: #fffbeb; }
        .pedido-preparando { border-left-color: #3b82f6; background: #eff6ff; }
        .pedido-listo { border-left-color: #10b981; background: #f0fdf4; }
        .sin-imprimir { border-right: 4px solid #ef4444; }
        .urgente { animation: pulse 2s infinite; }
        @keyframes pulse { 0%, 100% { opacity: 1; } 50% { opacity: 0.8; } }
        
        /* BOTONES COMPACTOS */
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
        
        /* TABS DE FILTRO */
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
        
        /* TOGGLE VISTA */
        .view-toggle {
            background: white;
            border: 2px solid #e5e7eb;
            padding: 6px;
            border-radius: 8px;
            display: flex;
            gap: 4px;
        }
        .view-toggle button {
            padding: 6px 12px;
            border-radius: 4px;
            border: none;
            background: transparent;
            cursor: pointer;
            transition: all 0.2s;
        }
        .view-toggle button.active {
            background: #3b82f6;
            color: white;
        }
        
        /* VISTA LISTA */
        .lista-item {
            display: grid;
            grid-template-columns: 60px 1fr 200px 100px 140px;
            gap: 12px;
            align-items: center;
            padding: 12px;
            border-bottom: 1px solid #e5e7eb;
            transition: all 0.2s;
        }
        .lista-item:hover {
            background: #f9fafb;
        }
        
        /* BADGE PEQUEÑO */
        .badge {
            display: inline-block;
            padding: 2px 8px;
            border-radius: 12px;
            font-size: 10px;
            font-weight: 700;
            text-transform: uppercase;
        }
        
        /* BOTÓN FLOTANTE REPOSICIONADO */
        .btn-flotante {
            position: fixed;
            bottom: 20px;
            left: 20px; /* CAMBIADO: ahora a la izquierda */
            z-index: 40; /* REDUCIDO: para que no tape */
            box-shadow: 0 4px 12px rgba(0,0,0,0.2);
        }
        
        /* SCROLL OPTIMIZADO */
        .pedidos-container {
            max-height: calc(100vh - 200px);
            overflow-y: auto;
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
    </style>
</head>
<body class="bg-gray-50">
    
    <!-- HEADER COMPACTO RESPONSIVE -->
    <header class="bg-blue-600 text-white p-2 sm:p-3 shadow-lg sticky top-0 z-50">
        <div class="max-w-7xl mx-auto">
            <div class="flex justify-between items-center mb-2 sm:mb-0">
                <div class="flex items-center space-x-2 sm:space-x-3">
                    <h1 class="text-base sm:text-xl font-bold">🏪 <span class="hidden sm:inline">LOCAL 1</span><span class="sm:hidden">L1</span></h1>
                    <div id="clock" class="text-blue-100 text-xs sm:text-sm"></div>
                </div>

                <div class="flex items-center space-x-1 sm:space-x-2">
                    <a href="pedidos.php" class="bg-blue-500 hover:bg-blue-400 px-2 sm:px-3 py-1 sm:py-1.5 rounded text-xs">
                        <i class="fas fa-list sm:mr-1"></i><span class="hidden sm:inline">Ver Todos</span>
                    </a>
                    <a href="logout.php" class="bg-red-500 hover:bg-red-600 px-2 sm:px-3 py-1 sm:py-1.5 rounded text-xs">
                        <i class="fas fa-sign-out-alt sm:mr-1"></i><span class="hidden sm:inline">Salir</span>
                    </a>
                </div>
            </div>

            <!-- STATS COMPACTOS RESPONSIVE -->
            <div class="grid grid-cols-5 gap-1 sm:gap-4 text-center text-xs mt-2">
                <div>
                    <div class="text-sm sm:text-lg font-bold"><?= $total ?></div>
                    <div class="text-blue-200 text-xs">Total</div>
                </div>
                <div class="text-yellow-300">
                    <div class="text-sm sm:text-lg font-bold"><?= $pendientes ?></div>
                    <div class="text-xs">Pend.</div>
                </div>
                <div class="text-blue-200">
                    <div class="text-sm sm:text-lg font-bold"><?= $preparando ?></div>
                    <div class="text-xs">Prep.</div>
                </div>
                <div class="text-green-200">
                    <div class="text-sm sm:text-lg font-bold"><?= $listos ?></div>
                    <div class="text-xs">Listos</div>
                </div>
                <div class="text-red-200">
                    <div class="text-sm sm:text-lg font-bold"><?= $sin_imprimir ?></div>
                    <div class="text-xs hidden sm:inline">Sin Imp.</div>
                    <div class="text-xs sm:hidden">S/I</div>
                </div>
            </div>
        </div>
    </header>

    <!-- BARRA DE FILTROS Y VISTA RESPONSIVE -->
    <div class="bg-white border-b p-2 sm:p-3 sticky top-16 sm:top-20 z-40 shadow-sm">
        <div class="max-w-7xl mx-auto">
            <!-- FILTROS POR ESTADO RESPONSIVE -->
            <div class="flex flex-wrap gap-1 sm:gap-2 mb-2">
                <div class="filter-tab active text-xs sm:text-sm" onclick="filtrarEstado('todos')" data-estado="todos">
                    Todos (<?= $total ?>)
                </div>
                <div class="filter-tab bg-yellow-100 text-yellow-800 text-xs sm:text-sm" onclick="filtrarEstado('Pendiente')" data-estado="Pendiente">
                    <span class="hidden sm:inline">Pendientes</span><span class="sm:hidden">Pend.</span> (<?= $pendientes ?>)
                </div>
                <div class="filter-tab bg-blue-100 text-blue-800 text-xs sm:text-sm" onclick="filtrarEstado('Preparando')" data-estado="Preparando">
                    <span class="hidden sm:inline">Preparando</span><span class="sm:hidden">Prep.</span> (<?= $preparando ?>)
                </div>
                <div class="filter-tab bg-green-100 text-green-800 text-xs sm:text-sm" onclick="filtrarEstado('Listo')" data-estado="Listo">
                    Listos (<?= $listos ?>)
                </div>
            </div>
            
            <!-- TOGGLE VISTA -->
            <div class="view-toggle">
                <button class="active" onclick="cambiarVista('cards')" data-vista="cards">
                    <i class="fas fa-th-large"></i>
                </button>
                <button onclick="cambiarVista('lista')" data-vista="lista">
                    <i class="fas fa-list"></i>
                </button>
            </div>
        </div>
    </div>

    <main class="max-w-7xl mx-auto p-4">
        <?php if (empty($pedidos)): ?>
            <div class="text-center py-20">
                <i class="fas fa-coffee text-6xl text-gray-300 mb-4"></i>
                <h2 class="text-2xl text-gray-500">No hay pedidos pendientes</h2>
                <p class="text-gray-400">Local 1 está al día</p>
            </div>
        <?php else: ?>
            
            <!-- VISTA CARDS COMPACTA -->
            <div id="vistaCards" class="pedidos-container grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-3">
                <?php foreach ($pedidos as $pedido): ?>
                    <div class="pedido-compacto pedido-<?= strtolower($pedido['estado']) ?> 
                                <?= !$pedido['impreso'] ? 'sin-imprimir' : '' ?>
                                <?= $pedido['minutos_transcurridos'] > 60 ? 'urgente' : '' ?>
                                bg-white rounded-lg shadow p-3"
                         data-estado="<?= $pedido['estado'] ?>"
                         data-id="<?= $pedido['id'] ?>">
                        
                        <!-- Header Compacto -->
                        <div class="flex justify-between items-start mb-2">
                            <div class="flex-1">
                                <div class="font-bold text-sm text-gray-800">
                                    #<?= $pedido['id'] ?> - <?= htmlspecialchars($pedido['nombre'] . ' ' . $pedido['apellido']) ?>
                                </div>
                                <div class="text-xs text-gray-500 flex items-center gap-2">
                                    <span><i class="fas fa-clock"></i> <?= $pedido['minutos_transcurridos'] ?>min</span>
                                    <?php if (!$pedido['impreso']): ?>
                                        <span class="badge bg-red-500 text-white">SIN IMP.</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>

                        <!-- Producto Compacto -->
                        <div class="mb-2 text-sm">
                            <div class="font-bold text-gray-800"><?= htmlspecialchars($pedido['producto']) ?></div>
                            <div class="text-green-600 font-bold text-lg">$<?= number_format($pedido['precio'], 0, ',', '.') ?></div>
                            <div class="text-xs text-gray-600 flex gap-2">
                                <span><i class="fas fa-<?= $pedido['modalidad'] === 'Retiro' ? 'store' : 'motorcycle' ?>"></i> <?= $pedido['modalidad'] ?></span>
                                <span><i class="fas fa-money-bill"></i> <?= $pedido['forma_pago'] ?></span>
                            </div>
                        </div>

                        <!-- Observaciones Colapsables -->
                        <?php if ($pedido['observaciones']): ?>
                            <details class="mb-2">
                                <summary class="text-xs text-blue-600 cursor-pointer hover:underline">
                                    <i class="fas fa-sticky-note"></i> Ver obs.
                                </summary>
                                <div class="text-xs bg-blue-50 p-2 mt-1 rounded">
                                    <?= nl2br(htmlspecialchars(substr($pedido['observaciones'], 0, 100))) ?>
                                    <?= strlen($pedido['observaciones']) > 100 ? '...' : '' ?>
                                </div>
                            </details>
                        <?php endif; ?>

                        <!-- Acciones Compactas -->
                        <div class="flex gap-1 flex-wrap">
                            <?php if ($pedido['estado'] === 'Pendiente'): ?>
                                <button onclick="cambiarEstado(<?= $pedido['id'] ?>, 'Preparando')" 
                                        class="btn-compact" style="background: #3b82f6; color: white;">
                                    <i class="fas fa-fire"></i> Preparar
                                </button>
                            <?php elseif ($pedido['estado'] === 'Preparando'): ?>
                                <button onclick="cambiarEstado(<?= $pedido['id'] ?>, 'Listo')" 
                                        class="btn-compact" style="background: #10b981; color: white;">
                                    <i class="fas fa-check"></i> Listo
                                </button>
                            <?php elseif ($pedido['estado'] === 'Listo'): ?>
                                <button onclick="cambiarEstado(<?= $pedido['id'] ?>, 'Entregado')" 
                                        class="btn-compact" style="background: #6b7280; color: white;">
                                    <i class="fas fa-handshake"></i> Entregar
                                </button>
                            <?php endif; ?>

                            <button onclick="imprimir(<?= $pedido['id'] ?>)" 
                                    class="btn-compact" style="background: #f59e0b; color: white;">
                                <i class="fas fa-print"></i>
                            </button>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <!-- VISTA LISTA -->
            <div id="vistaLista" class="pedidos-container hidden bg-white rounded-lg shadow">
                <div class="lista-item font-bold text-xs text-gray-600 bg-gray-50 border-b-2 border-gray-300">
                    <div>#ID</div>
                    <div>CLIENTE / PRODUCTO</div>
                    <div>MODALIDAD / PAGO</div>
                    <div>PRECIO</div>
                    <div class="text-center">ACCIONES</div>
                </div>
                <?php foreach ($pedidos as $pedido): ?>
                    <div class="lista-item pedido-<?= strtolower($pedido['estado']) ?> 
                                <?= !$pedido['impreso'] ? 'sin-imprimir' : '' ?>"
                         data-estado="<?= $pedido['estado'] ?>"
                         data-id="<?= $pedido['id'] ?>">
                        
                        <div class="font-bold text-sm">
                            #<?= $pedido['id'] ?>
                            <div class="text-xs text-gray-500"><?= $pedido['minutos_transcurridos'] ?>min</div>
                            <?php if (!$pedido['impreso']): ?>
                                <span class="badge bg-red-500 text-white mt-1">SIN IMP</span>
                            <?php endif; ?>
                        </div>
                        
                        <div>
                            <div class="font-bold text-sm"><?= htmlspecialchars($pedido['nombre'] . ' ' . $pedido['apellido']) ?></div>
                            <div class="text-xs text-gray-600"><?= htmlspecialchars($pedido['producto']) ?></div>
                        </div>
                        
                        <div class="text-xs">
                            <div><i class="fas fa-<?= $pedido['modalidad'] === 'Retiro' ? 'store' : 'motorcycle' ?>"></i> <?= $pedido['modalidad'] ?></div>
                            <div><i class="fas fa-money-bill"></i> <?= $pedido['forma_pago'] ?></div>
                        </div>
                        
                        <div class="font-bold text-green-600">
                            $<?= number_format($pedido['precio'], 0, ',', '.') ?>
                        </div>
                        
                        <div class="flex gap-1 justify-center flex-wrap">
                            <?php if ($pedido['estado'] === 'Pendiente'): ?>
                                <button onclick="cambiarEstado(<?= $pedido['id'] ?>, 'Preparando')" 
                                        class="btn-compact" style="background: #3b82f6; color: white;">
                                    <i class="fas fa-fire"></i>
                                </button>
                            <?php elseif ($pedido['estado'] === 'Preparando'): ?>
                                <button onclick="cambiarEstado(<?= $pedido['id'] ?>, 'Listo')" 
                                        class="btn-compact" style="background: #10b981; color: white;">
                                    <i class="fas fa-check"></i>
                                </button>
                            <?php elseif ($pedido['estado'] === 'Listo'): ?>
                                <button onclick="cambiarEstado(<?= $pedido['id'] ?>, 'Entregado')" 
                                        class="btn-compact" style="background: #6b7280; color: white;">
                                    <i class="fas fa-handshake"></i>
                                </button>
                            <?php endif; ?>

                            <button onclick="imprimir(<?= $pedido['id'] ?>)" 
                                    class="btn-compact" style="background: #f59e0b; color: white;">
                                <i class="fas fa-print"></i>
                            </button>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

        <?php endif; ?>
    </main>

    <!-- BOTÓN FLOTANTE (IZQUIERDA ABAJO) -->
    <button onclick="abrirPedidoExpress()" 
            class="btn-flotante bg-green-600 hover:bg-green-700 text-white font-bold py-3 px-5 rounded-full">
        <i class="fas fa-plus-circle mr-2"></i>
        Express
    </button>

    <!-- MODAL PEDIDO EXPRESS -->
    <div id="modalPedidoExpress" class="modal fixed inset-0 bg-black bg-opacity-50 hidden flex items-center justify-center p-4 z-50">
        <div class="bg-white rounded-xl shadow-2xl max-w-4xl w-full max-h-[95vh] overflow-y-auto">
            
            <!-- Header -->
            <div class="bg-green-600 text-white p-4 rounded-t-xl flex justify-between items-center">
                <h2 class="text-xl font-bold">
                    <i class="fas fa-tachometer-alt mr-2"></i>
                    Pedido Express - Local 1
                </h2>
                <button onclick="cerrarPedidoExpress()" class="text-white hover:text-gray-200">
                    <i class="fas fa-times text-xl"></i>
                </button>
            </div>

            <!-- Formulario -->
            <form id="formPedidoExpress" class="p-6">
                
                <!-- Datos del Cliente + TURNOS -->
                <div class="bg-blue-50 rounded-lg p-4 mb-6">
                    <h3 class="font-bold text-blue-900 mb-3">
                        <i class="fas fa-user mr-2"></i>Datos del Cliente
                    </h3>
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Nombre *</label>
                            <input type="text" id="nombre" required class="w-full p-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent text-lg" placeholder="Juan">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Apellido *</label>
                            <input type="text" id="apellido" required class="w-full p-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent text-lg" placeholder="Pérez">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Teléfono</label>
                            <input type="tel" id="telefono" class="w-full p-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent text-lg" placeholder="11 1234-5678">
                        </div>
                    </div>
                    
                    <!-- TURNOS -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">¿Para qué turno? *</label>
                        <div class="grid grid-cols-3 gap-3">
                            <div class="turno-card cursor-pointer bg-white p-4 rounded-lg border-2 border-gray-300 hover:border-blue-400 text-center" onclick="seleccionarTurno('M', this)">
                                <i class="fas fa-sun text-yellow-500 text-2xl mb-2"></i>
                                <div class="font-bold">Mañana</div>
                                <div class="text-xs text-gray-500">6am - 2pm</div>
                                <input type="radio" name="turno" value="M" class="hidden">
                            </div>
                            <div class="turno-card cursor-pointer bg-white p-4 rounded-lg border-2 border-gray-300 hover:border-blue-400 text-center" onclick="seleccionarTurno('S', this)">
                                <i class="fas fa-cloud-sun text-orange-500 text-2xl mb-2"></i>
                                <div class="font-bold">Siesta</div>
                                <div class="text-xs text-gray-500">2pm - 6pm</div>
                                <input type="radio" name="turno" value="S" class="hidden">
                            </div>
                            <div class="turno-card cursor-pointer bg-white p-4 rounded-lg border-2 border-gray-300 hover:border-blue-400 text-center" onclick="seleccionarTurno('T', this)">
                                <i class="fas fa-moon text-purple-500 text-2xl mb-2"></i>
                                <div class="font-bold">Tarde</div>
                                <div class="text-xs text-gray-500">6pm - 11pm</div>
                                <input type="radio" name="turno" value="T" class="hidden">
                            </div>
                        </div>
                    </div>
                </div>

                <!-- PEDIDOS COMUNES -->
                <div id="seccionComunes" class="bg-yellow-50 rounded-lg p-4 mb-6">
                    <h3 class="font-bold text-yellow-900 mb-4">
                        <i class="fas fa-star mr-2"></i>¿Qué va a llevar?
                    </h3>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        
                        <!-- JyQ x24 -->
                        <div class="pedido-card bg-white rounded-lg border-2 border-yellow-200 p-4 hover:border-yellow-400 cursor-pointer transition-all" onclick="seleccionarComun('jyq24')">
                            <div class="flex items-center justify-between">
                                <div>
                                    <h4 class="font-bold text-gray-800 text-lg">Jamón y Queso x24</h4>
                                    <p class="text-sm text-gray-600">3 planchas</p>
                                    <p class="font-bold text-green-600 text-xl">$18.000</p>
                                </div>
                                <div class="text-yellow-500">
                                    <i class="fas fa-bread-slice text-4xl"></i>
                                </div>
                            </div>
                            <input type="radio" name="pedido_tipo" value="jyq24" class="hidden">
                        </div>

                        <!-- JyQ x48 -->
                        <div class="pedido-card bg-white rounded-lg border-2 border-yellow-200 p-4 hover:border-yellow-400 cursor-pointer transition-all" onclick="seleccionarComun('jyq48')">
                            <div class="flex items-center justify-between">
                                <div>
                                    <h4 class="font-bold text-gray-800 text-lg">Jamón y Queso x48</h4>
                                    <p class="text-sm text-gray-600">6 planchas</p>
                                    <p class="font-bold text-green-600 text-xl">$22.000</p>
                                </div>
                                <div class="text-yellow-600">
                                    <i class="fas fa-layer-group text-4xl"></i>
                                </div>
                            </div>
                            <input type="radio" name="pedido_tipo" value="jyq48" class="hidden">
                        </div>

                        <!-- Surtido Clásico x48 -->
                        <div class="pedido-card bg-white rounded-lg border-2 border-yellow-200 p-4 hover:border-yellow-400 cursor-pointer transition-all" onclick="seleccionarComun('surtido_clasico48')">
                            <div class="flex items-center justify-between">
                                <div>
                                    <h4 class="font-bold text-gray-800 text-lg">Surtido Clásico x48</h4>
                                    <p class="text-sm text-gray-600">Sabores comunes</p>
                                    <p class="font-bold text-green-600 text-xl">$20.000</p>
                                </div>
                                <div class="text-blue-500">
                                    <i class="fas fa-list text-4xl"></i>
                                </div>
                            </div>
                            <input type="radio" name="pedido_tipo" value="surtido_clasico48" class="hidden">
                        </div>

                        <!-- Surtido Especial x48 -->
                        <div class="pedido-card bg-white rounded-lg border-2 border-yellow-200 p-4 hover:border-yellow-400 cursor-pointer transition-all" onclick="seleccionarComun('surtido_especial48')">
                            <div class="flex items-center justify-between">
                                <div>
                                    <h4 class="font-bold text-gray-800 text-lg">Surtido Especial x48</h4>
                                    <p class="text-sm text-gray-600">Con sabores premium</p>
                                    <p class="font-bold text-green-600 text-xl">$25.000</p>
                                </div>
                                <div class="text-purple-500">
                                    <i class="fas fa-crown text-4xl"></i>
                                </div>
                            </div>
                            <input type="radio" name="pedido_tipo" value="surtido_especial48" class="hidden">
                        </div>
                    </div>
                </div>

                <!-- PERSONALIZADO CON PLANCHAS -->
                <div id="seccionPersonalizado" class="bg-purple-50 rounded-lg p-4 mb-6">
                    <h3 class="font-bold text-purple-900 mb-4">
                        <i class="fas fa-palette mr-2"></i>¿Algo Diferente?
                    </h3>
                    
                    <div class="pedido-card bg-white rounded-lg border-2 border-purple-200 p-4 hover:border-purple-400 cursor-pointer transition-all" onclick="seleccionarPersonalizado()" data-tipo="personalizado">
                        <div class="flex items-center justify-between">
                            <div>
                                <h4 class="font-bold text-gray-800 text-lg">Personalizado por Planchas</h4>
                                <p class="text-sm text-gray-600">Clickeá cada sabor para agregar planchas</p>
                            </div>
                            <div class="text-purple-500">
                                <i class="fas fa-sliders-h text-4xl"></i>
                            </div>
                        </div>
                        <input type="radio" name="pedido_tipo" value="personalizado" class="hidden">
                    </div>

                    <!-- Panel personalizado (oculto por defecto) -->
                    <div id="panelPersonalizado" class="mt-4 hidden">
                        
                        <!-- Precio Final Total -->
                        <div class="p-4 bg-white rounded-lg shadow border-2 border-green-500 mb-4">
                            <label class="block text-sm font-medium text-gray-700 mb-2">
                                <i class="fas fa-dollar-sign text-green-500 mr-2"></i>Precio Final Total *
                            </label>
                            <input type="number" 
                                   id="precioPersonalizado"
                                   min="0" 
                                   step="100" 
                                   placeholder="$0"
                                   class="w-full px-4 py-3 text-2xl font-bold text-center rounded-lg border-2 border-green-500 focus:ring-2 focus:ring-green-300">
                            <p class="text-xs text-gray-600 mt-1 text-center">Ingresá el precio total del pedido</p>
                        </div>

                        <!-- Botón Deshacer -->
                        <div class="flex justify-end mb-4">
                            <button type="button" onclick="deshacer()" class="px-4 py-2 bg-red-500 text-white rounded-lg text-sm hover:bg-red-600 font-medium">
                                <i class="fas fa-undo mr-1"></i>Deshacer
                            </button>
                        </div>

                        <!-- Resumen de Planchas -->
                        <div class="p-4 bg-blue-100 rounded-lg text-center mb-4">
                            <div class="text-sm text-gray-700">Total de planchas seleccionadas:</div>
                            <div id="totalPlanchas" class="text-4xl font-bold text-blue-600">0</div>
                            <div class="text-xs text-gray-600 mt-1">1 plancha = 8 sándwiches</div>
                        </div>

                        <!-- Sabores Clickeables -->
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <!-- COMUNES -->
                            <div class="p-4 bg-green-50 border-2 border-green-300 rounded-lg">
                                <h4 class="font-bold text-green-800 mb-3 text-sm">
                                    <i class="fas fa-circle text-green-500 mr-2"></i>SABORES COMUNES
                                </h4>
                                <div class="grid grid-cols-2 gap-2" id="saboresComunes">
                                    <!-- Se generan con JS -->
                                </div>
                            </div>

                            <!-- PREMIUM -->
                            <div class="p-4 bg-orange-50 border-2 border-orange-300 rounded-lg">
                                <h4 class="font-bold text-orange-800 mb-3 text-sm">
                                    <i class="fas fa-star text-orange-500 mr-2"></i>SABORES PREMIUM
                                </h4>
                                <div class="grid grid-cols-2 gap-2" id="saboresPremium">
                                    <!-- Se generan con JS -->
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Forma de Pago -->
                <div class="bg-green-50 rounded-lg p-4 mb-6">
                    <h3 class="font-bold text-green-900 mb-3">
                        <i class="fas fa-money-bill-wave mr-2"></i>¿Cómo va a pagar? *
                    </h3>
                    <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
                        <label class="flex items-center cursor-pointer bg-white p-3 rounded-lg border border-gray-300 hover:border-green-500">
                            <input type="radio" name="forma_pago" value="Efectivo" class="mr-2">
                            <i class="fas fa-money-bill text-green-500 mr-2"></i>
                            Efectivo
                        </label>
                        <label class="flex items-center cursor-pointer bg-white p-3 rounded-lg border border-gray-300 hover:border-green-500">
                            <input type="radio" name="forma_pago" value="Transferencia" class="mr-2">
                            <i class="fas fa-exchange-alt text-blue-500 mr-2"></i>
                            Transferencia
                        </label>
                        <label class="flex items-center cursor-pointer bg-white p-3 rounded-lg border border-gray-300 hover:border-green-500">
                            <input type="radio" name="forma_pago" value="Tarjeta" class="mr-2">
                            <i class="fas fa-credit-card text-purple-500 mr-2"></i>
                            Tarjeta
                        </label>
                        <label class="flex items-center cursor-pointer bg-white p-3 rounded-lg border border-gray-300 hover:border-green-500">
                            <input type="radio" name="forma_pago" value="MercadoPago" class="mr-2">
                            <i class="fas fa-mobile-alt text-cyan-500 mr-2"></i>
                            MercadoPago
                        </label>
                    </div>
                </div>

                <!-- Observaciones -->
                <div class="mb-6">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Observaciones</label>
                    <textarea id="observaciones" rows="3" class="w-full p-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent" placeholder="Observaciones adicionales del pedido..."></textarea>
                </div>

                <!-- Botones -->
                <div class="flex gap-4 justify-end">
                    <button type="button" onclick="cerrarPedidoExpress()" class="px-6 py-2 bg-gray-500 hover:bg-gray-600 text-white rounded-lg font-semibold">
                        <i class="fas fa-times mr-2"></i>Cancelar
                    </button>
                    <button type="submit" class="px-6 py-2 bg-green-600 hover:bg-green-700 text-white rounded-lg font-semibold">
                        <i class="fas fa-check mr-2"></i>Crear Pedido Express
                    </button>
                </div>
            </form>
        </div>
    </div>

    <style>
    .modal { 
        backdrop-filter: blur(4px);
        -webkit-backdrop-filter: blur(4px);
    }
    .turno-card {
        transition: all 0.2s ease;
        position: relative;
    }
    .turno-card.seleccionado {
        border-color: #3b82f6 !important;
        background: #dbeafe !important;
        box-shadow: 0 4px 12px rgba(59, 130, 246, 0.3) !important;
        transform: scale(1.02);
    }
    .turno-card.seleccionado::after {
        content: '✓';
        position: absolute;
        top: 4px;
        right: 8px;
        color: #3b82f6;
        font-weight: bold;
        font-size: 16px;
    }
    .pedido-card.seleccionado {
        border-color: #3b82f6 !important;
        background: linear-gradient(135deg, #dbeafe 0%, #bfdbfe 100%) !important;
        box-shadow: 0 8px 25px rgba(59, 130, 246, 0.4) !important;
        transform: scale(1.02);
    }
    .pedido-card.seleccionado::after {
        content: '✓';
        position: absolute;
        top: 4px;
        right: 8px;
        color: #10b981;
        font-weight: bold;
        font-size: 20px;
    }
    .seccion-bloqueada {
        opacity: 0.3 !important;
        pointer-events: none !important;
        filter: grayscale(80%) !important;
    }
    .sabor-btn {
        transition: all 0.2s ease;
    }
    .sabor-btn:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 8px rgba(0,0,0,0.1);
    }
    </style>

    <script>
    // Variables globales para Express
    let pedidoSeleccionado = null;
    let precioBase = 0;
    let turnoSeleccionado = null;
    let planchasPorSabor = {};
    let historial = [];

    const precios = {
        'jyq24': 18000,
        'jyq48': 22000,
        'surtido_clasico48': 20000,
        'surtido_especial48': 25000
    };

    const saboresComunes = [
        'Jamón y Queso', 'Lechuga', 'Tomate', 'Huevo',
        'Choclo', 'Aceitunas', 'Zanahoria y Queso', 'Zanahoria y Huevo'
    ];

    const saboresPremium = [
        'Ananá', 'Atún', 'Berenjena', 'Jamón Crudo',
        'Morrón', 'Palmito', 'Panceta', 'Pollo',
        'Roquefort', 'Salame'
    ];

    function generarBotonesSabores() {
        const contenedorComunes = document.getElementById('saboresComunes');
        contenedorComunes.innerHTML = saboresComunes.map(sabor => `
            <button type="button" 
                    onclick="agregarPlancha('${sabor}')" 
                    class="sabor-btn p-3 bg-white border-2 border-green-300 rounded-lg text-xs font-medium hover:bg-green-100 hover:border-green-500 transition-all">
                <div class="font-bold">${sabor}</div>
                <div id="count-${sabor.replace(/\s+/g, '-')}" class="text-green-600 font-bold mt-1 text-lg">0</div>
            </button>
        `).join('');

        const contenedorPremium = document.getElementById('saboresPremium');
        contenedorPremium.innerHTML = saboresPremium.map(sabor => `
            <button type="button" 
                    onclick="agregarPlancha('${sabor}')" 
                    class="sabor-btn p-3 bg-white border-2 border-orange-300 rounded-lg text-xs font-medium hover:bg-orange-100 hover:border-orange-500 transition-all">
                <div class="font-bold">${sabor}</div>
                <div id="count-${sabor.replace(/\s+/g, '-')}" class="text-orange-600 font-bold mt-1 text-lg">0</div>
            </button>
        `).join('');
    }

    function agregarPlancha(sabor) {
        historial.push(JSON.parse(JSON.stringify(planchasPorSabor)));
        if (!planchasPorSabor[sabor]) planchasPorSabor[sabor] = 0;
        planchasPorSabor[sabor]++;
        actualizarVista();
    }

    function deshacer() {
        if (historial.length > 0) {
            planchasPorSabor = historial.pop();
            actualizarVista();
        } else {
            alert('No hay acciones para deshacer');
        }
    }

    function actualizarVista() {
        [...saboresComunes, ...saboresPremium].forEach(sabor => {
            const id = 'count-' + sabor.replace(/\s+/g, '-');
            const elemento = document.getElementById(id);
            if (elemento) elemento.textContent = 0;
        });
        
        Object.keys(planchasPorSabor).forEach(sabor => {
            const count = planchasPorSabor[sabor];
            if (count > 0) {
                const id = 'count-' + sabor.replace(/\s+/g, '-');
                const elemento = document.getElementById(id);
                if (elemento) elemento.textContent = count;
            }
        });

        const totalPlanchas = Object.values(planchasPorSabor).reduce((a, b) => a + b, 0);
        document.getElementById('totalPlanchas').textContent = totalPlanchas;
    }

    function seleccionarTurno(turno, elemento) {
        document.querySelectorAll('.turno-card').forEach(card => card.classList.remove('seleccionado'));
        elemento.classList.add('seleccionado');
        elemento.querySelector('input[type="radio"]').checked = true;
        turnoSeleccionado = turno;
    }

    function seleccionarComun(tipo) {
        document.getElementById('seccionPersonalizado').classList.add('seccion-bloqueada');
        document.getElementById('seccionComunes').classList.remove('seccion-bloqueada');
        document.querySelectorAll('.pedido-card').forEach(card => card.classList.remove('seleccionado'));
        document.getElementById('panelPersonalizado').classList.add('hidden');
        event.currentTarget.classList.add('seleccionado');
        event.currentTarget.querySelector('input[type="radio"]').checked = true;
        pedidoSeleccionado = tipo;
        precioBase = precios[tipo];
    }

    function seleccionarPersonalizado() {
        document.getElementById('seccionComunes').classList.add('seccion-bloqueada');
        document.getElementById('seccionPersonalizado').classList.remove('seccion-bloqueada');
        document.querySelectorAll('.pedido-card').forEach(card => card.classList.remove('seleccionado'));
        event.currentTarget.classList.add('seleccionado');
        event.currentTarget.querySelector('input[type="radio"]').checked = true;
        document.getElementById('panelPersonalizado').classList.remove('hidden');
        
        if (document.getElementById('saboresComunes').children.length === 0) {
            generarBotonesSabores();
            actualizarVista();
        }
        pedidoSeleccionado = 'personalizado';
    }

    function abrirPedidoExpress() {
        document.getElementById('modalPedidoExpress').classList.remove('hidden');
        document.body.style.overflow = 'hidden';
    }

    function cerrarPedidoExpress() {
        document.getElementById('modalPedidoExpress').classList.add('hidden');
        document.body.style.overflow = 'auto';
        resetearFormulario();
    }

    function resetearFormulario() {
        document.getElementById('formPedidoExpress').reset();
        document.querySelectorAll('.pedido-card, .turno-card').forEach(card => card.classList.remove('seleccionado'));
        document.querySelectorAll('.seccion-bloqueada').forEach(s => s.classList.remove('seccion-bloqueada'));
        document.getElementById('panelPersonalizado').classList.add('hidden');
        pedidoSeleccionado = null;
        turnoSeleccionado = null;
        planchasPorSabor = {};
        historial = [];
        precioBase = 0;
        actualizarVista();
    }

    document.getElementById('formPedidoExpress').addEventListener('submit', function(e) {
        e.preventDefault();
        
        const nombre = document.getElementById('nombre').value.trim();
        const apellido = document.getElementById('apellido').value.trim();
        const turno = document.querySelector('input[name="turno"]:checked')?.value;
        const formaPago = document.querySelector('input[name="forma_pago"]:checked')?.value;
        
        if (!nombre || !apellido) {
            alert('Ingresá nombre y apellido');
            return;
        }
        
        if (!turno) {
            alert('Seleccioná el turno');
            return;
        }
        
        if (!formaPago) {
            alert('Seleccioná la forma de pago');
            return;
        }
        
        if (!pedidoSeleccionado) {
            alert('Seleccioná un tipo de pedido');
            return;
        }
        
        const pedido = {
            nombre: nombre,
            apellido: apellido,
            telefono: document.getElementById('telefono').value.trim(),
            modalidad: 'Retiro',
            forma_pago: formaPago,
            observaciones: `Turno: ${turno}\n${document.getElementById('observaciones').value.trim()}`,
            tipo_pedido: pedidoSeleccionado,
            precio: precioBase,
            ubicacion: 'Local 1',
            estado: 'Pendiente'
        };
        
        if (pedidoSeleccionado === 'personalizado') {
            const precioPersonalizado = parseFloat(document.getElementById('precioPersonalizado').value) || 0;
            
            if (precioPersonalizado <= 0) {
                alert('Ingresá el precio del pedido');
                return;
            }
            
            const totalPlanchas = Object.values(planchasPorSabor).reduce((sum, val) => sum + val, 0);
            
            if (totalPlanchas === 0) {
                alert('Seleccioná al menos 1 plancha');
                return;
            }
            
            const totalSandwiches = totalPlanchas * 8;
            
            pedido.cantidad = totalSandwiches;
            pedido.producto = `Personalizado x${totalSandwiches} (${totalPlanchas} plancha${totalPlanchas > 1 ? 's' : ''})`;
            pedido.precio = precioPersonalizado;
            pedido.sabores_personalizados_json = JSON.stringify(planchasPorSabor);
            
            let detalleSabores = '\n=== SABORES PERSONALIZADOS ===';
            for (let sabor in planchasPorSabor) {
                const planchas = planchasPorSabor[sabor];
                const sandwiches = planchas * 8;
                detalleSabores += `\n• ${sabor}: ${planchas} plancha${planchas > 1 ? 's' : ''} (${sandwiches} sándwiches)`;
            }
            pedido.observaciones += detalleSabores;
            precioBase = precioPersonalizado;
            
        } else {
            const productos = {
                'jyq24': 'Jamón y Queso x24',
                'jyq48': 'Jamón y Queso x48', 
                'surtido_clasico48': 'Surtido Clásico x48',
                'surtido_especial48': 'Surtido Especial x48'
            };
            pedido.producto = productos[pedidoSeleccionado];
            pedido.cantidad = pedidoSeleccionado.includes('24') ? 24 : 48;
        }
        
        procesarPedidoExpress(pedido);
    });

    function procesarPedidoExpress(pedido) {
        const submitBtn = document.querySelector('button[type="submit"]');
        const originalText = submitBtn.innerHTML;
        submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Creando...';
        submitBtn.disabled = true;
        
        fetch('procesar_pedido_express.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify(pedido)
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert(`✅ Pedido #${data.pedido_id} creado!\n\n${data.data.cliente}\n${data.data.producto}\n${data.data.precio.toLocaleString()}`);
                cerrarPedidoExpress();
                setTimeout(() => location.reload(), 1000);
            } else {
                alert('❌ Error: ' + data.error);
            }
        })
        .catch(error => {
            alert('❌ Error de conexión');
        })
        .finally(() => {
            submitBtn.innerHTML = originalText;
            submitBtn.disabled = false;
        });
    }

    // ESC para cerrar modal
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') cerrarPedidoExpress();
    });
    // Filtrar por estado
    function filtrarEstado(estado) {
        // Remover active de todos los tabs
        document.querySelectorAll('.filter-tab').forEach(tab => {
            tab.classList.remove('active');
        });
        
        // Marcar el tab clickeado como activo
        const tabClickeado = document.querySelector(`.filter-tab[data-estado="${estado}"]`);
        if (tabClickeado) {
            tabClickeado.classList.add('active');
        }
        
        // Filtrar SOLO los pedidos (cards y lista items)
        // IMPORTANTE: usar selectores más específicos para no tocar los tabs
        const pedidosCards = document.querySelectorAll('#vistaCards > [data-estado]');
        const pedidosLista = document.querySelectorAll('#vistaLista > [data-estado]');
        
        let visibles = 0;
        
        // Filtrar cards
        pedidosCards.forEach(pedido => {
            if (estado === 'todos' || pedido.dataset.estado === estado) {
                pedido.style.display = '';
                visibles++;
            } else {
                pedido.style.display = 'none';
            }
        });
        
        // Filtrar items de lista
        pedidosLista.forEach(pedido => {
            if (estado === 'todos' || pedido.dataset.estado === estado) {
                pedido.style.display = '';
            } else {
                pedido.style.display = 'none';
            }
        });
        
        // Mostrar mensaje si no hay pedidos
        const vistaCards = document.getElementById('vistaCards');
        const vistaLista = document.getElementById('vistaLista');
        let mensajeVacio = document.getElementById('mensajeVacio');
        
        if (visibles === 0) {
            if (!mensajeVacio) {
                mensajeVacio = document.createElement('div');
                mensajeVacio.id = 'mensajeVacio';
                mensajeVacio.className = 'col-span-full text-center py-20';
                mensajeVacio.innerHTML = `
                    <i class="fas fa-search text-6xl text-gray-300 mb-4"></i>
                    <h3 class="text-xl text-gray-500">No hay pedidos "${estado}"</h3>
                    <button onclick="filtrarEstado('todos')" class="mt-4 px-4 py-2 bg-blue-500 text-white rounded-lg hover:bg-blue-600">
                        <i class="fas fa-list mr-2"></i>Ver Todos
                    </button>
                `;
                
                if (!vistaCards.classList.contains('hidden')) {
                    vistaCards.appendChild(mensajeVacio);
                } else {
                    vistaLista.appendChild(mensajeVacio);
                }
            }
        } else {
            if (mensajeVacio) {
                mensajeVacio.remove();
            }
        }
        
        console.log(`✅ Filtro: ${estado} | Visibles: ${visibles}`);
    }

    // Cambiar vista
    function cambiarVista(vista) {
        document.querySelectorAll('.view-toggle button').forEach(btn => {
            btn.classList.remove('active');
        });
        event.target.classList.add('active');
        
        if (vista === 'cards') {
            document.getElementById('vistaCards').classList.remove('hidden');
            document.getElementById('vistaLista').classList.add('hidden');
        } else {
            document.getElementById('vistaCards').classList.add('hidden');
            document.getElementById('vistaLista').classList.remove('hidden');
        }
        
        localStorage.setItem('vistaPreferida', vista);
    }

    // Restaurar vista preferida
    window.addEventListener('DOMContentLoaded', () => {
        const vistaGuardada = localStorage.getItem('vistaPreferida');
        if (vistaGuardada === 'lista') {
            document.querySelector('[data-vista="lista"]').click();
        }
    });

    // Funciones del dashboard
    function cambiarEstado(pedidoId, nuevoEstado) {
        if (confirm(`¿Cambiar a "${nuevoEstado}"?`)) {
            const formData = new FormData();
            formData.append('accion', 'cambiar_estado');
            formData.append('pedido_id', pedidoId);
            formData.append('nuevo_estado', nuevoEstado);
            
            fetch('dashboard.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) location.reload();
                else alert('Error al cambiar estado');
            });
        }
    }

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
        setTimeout(() => marcarImpreso(pedidoId), 2000);
        
        console.log('✅ Comanda abierta exitosamente');
        return true;
    }

    function marcarImpreso(pedidoId) {
        const formData = new FormData();
        formData.append('accion', 'marcar_impreso');
        formData.append('pedido_id', pedidoId);
        
        fetch('dashboard.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                console.log(`✅ Pedido #${pedidoId} marcado como impreso`);
                setTimeout(() => location.reload(), 1000);
            }
        })
        .catch(error => console.error('Error:', error));
    }

    // Reloj
    function updateClock() {
        const now = new Date();
        const h = String(now.getHours()).padStart(2, '0');
        const m = String(now.getMinutes()).padStart(2, '0');
        const s = String(now.getSeconds()).padStart(2, '0');
        document.getElementById('clock').textContent = `${h}:${m}:${s}`;
    }
    setInterval(updateClock, 1000);
    updateClock();

    // AUTO-REFRESH cada 30 segundos
    setTimeout(() => location.reload(), 30000);

    console.log('🚀 Dashboard optimizado cargado');
    </script>

</body>
</html>