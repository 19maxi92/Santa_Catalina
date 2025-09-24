
                <?php
require_once '../admin/config.php';
session_start();

// Verificar acceso de empleado
if (!isset($_SESSION['empleado_logged']) || $_SESSION['empleado_logged'] !== true) {
    header('Location: login.php');
    exit;
}

$pdo = getConnection();

// Procesar acciones AJAX
if ($_POST && isset($_POST['accion'])) {
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
            $nuevo_estado = sanitize($_POST['nuevo_estado']);
            $stmt = $pdo->prepare("UPDATE pedidos SET estado = ? WHERE id = ?");
            $result = $stmt->execute([$nuevo_estado, $pedido_id]);
            echo json_encode(['success' => $result]);
            exit;
    }
}

// Obtener solo pedidos de Local 1 de hoy que no estén entregados
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

// Contar por estados
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
    <title>Local 1 - Santa Catalina - Dashboard</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { font-family: 'Arial', sans-serif; }
        .pedido-card {
            transition: all 0.3s ease;
            border-left: 6px solid #ccc;
        }
        .pedido-pendiente { border-left-color: #f59e0b; background: #fef3c7; }
        .pedido-preparando { border-left-color: #3b82f6; background: #dbeafe; }
        .pedido-listo { border-left-color: #10b981; background: #d1fae5; }
        .sin-imprimir { border-right: 6px solid #ef4444; }
        .urgente { animation: pulse 2s infinite; }
        @keyframes pulse { 0%, 100% { opacity: 1; } 50% { opacity: 0.7; } }
        .btn { 
            padding: 8px 16px; 
            border-radius: 6px; 
            border: none; 
            cursor: pointer; 
            font-weight: 600;
            transition: all 0.2s;
        }
        .btn:hover { transform: translateY(-1px); }
        .btn-preparando { background: #3b82f6; color: white; }
        .btn-listo { background: #10b981; color: white; }
        .btn-entregado { background: #6b7280; color: white; }
        .btn-imprimir { background: #f59e0b; color: white; }
        
        /* Estilos del modal de pedidos express - MEJORADOS */
        .modal { 
            backdrop-filter: blur(4px);
            -webkit-backdrop-filter: blur(4px);
        }
        .btn-express {
            transition: all 0.2s ease;
            transform: scale(1);
        }
        .btn-express:hover {
            transform: scale(1.02);
        }
        .btn-express:active {
            transform: scale(0.98);
        }
        
        /* ESTILOS SÚPER VISUALES PARA SELECCIÓN */
        .pedido-card {
            transition: all 0.3s ease;
            position: relative;
        }
        .pedido-card.seleccionado {
            border-color: #3b82f6 !important;
            background: linear-gradient(135deg, #dbeafe 0%, #bfdbfe 100%) !important;
            box-shadow: 0 8px 25px rgba(59, 130, 246, 0.4) !important;
            transform: scale(1.05);
        }
        .pedido-card.seleccionado::after {
            content: '✓';
            position: absolute;
            top: 4px;
            right: 8px;
            color: #10b981;
            font-weight: bold;
            font-size: 20px;
            text-shadow: 1px 1px 2px rgba(0,0,0,0.3);
        }
        
        .seccion-bloqueada {
            opacity: 0.3 !important;
            pointer-events: none !important;
            filter: grayscale(80%) !important;
            transform: scale(0.98) !important;
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
        
        .sabor-checkbox {
            appearance: none;
            background: #f3f4f6;
            border: 2px solid #d1d5db;
            border-radius: 8px;
            padding: 8px 12px;
            cursor: pointer;
            transition: all 0.2s;
            font-size: 14px;
        }
        .sabor-checkbox:checked {
            background: #3b82f6;
            color: white;
            border-color: #3b82f6;
        }
        .sabor-checkbox:hover {
            border-color: #3b82f6;
            background: #e0e7ff;
        }
        .sabor-checkbox:checked:hover {
            background: #2563eb;
        }
    </style>
</head>
<body class="bg-gray-50">
    <!-- Header Simple -->
    <header class="bg-blue-600 text-white p-4 shadow-lg">
        <div class="max-w-7xl mx-auto flex justify-between items-center">
            <div class="flex items-center space-x-4">
                <h1 class="text-2xl font-bold">🏪 LOCAL 1 - Dashboard</h1>
                <div id="clock" class="text-blue-100 text-lg"></div>
            </div>
            
            <!-- Stats Header -->
            <div class="flex space-x-6 text-sm">
                <div class="text-center text-yellow-200">
                    <div class="text-2xl font-bold"><?= $total ?></div>
                    <div>Total</div>
                </div>
                <div class="text-center text-yellow-200">
                    <div class="text-2xl font-bold"><?= $pendientes ?></div>
                    <div>Pendientes</div>
                </div>
                <div class="text-center text-blue-200">
                    <div class="text-2xl font-bold"><?= $preparando ?></div>
                    <div>Preparando</div>
                </div>
                <div class="text-center text-green-200">
                    <div class="text-2xl font-bold"><?= $listos ?></div>
                    <div>Listos</div>
                </div>
                <div class="text-center text-red-200">
                    <div class="text-2xl font-bold"><?= $sin_imprimir ?></div>
                    <div>Sin Imprimir</div>
                </div>
            </div>
            
            <div class="flex items-center space-x-2">
                <!-- Botón a Pedidos -->
                <a href="pedidos.php" class="bg-blue-500 hover:bg-blue-400 px-3 py-2 rounded text-sm">
                    <i class="fas fa-list mr-1"></i>Ver Pedidos
                </a>
                <!-- Logout -->
                <a href="logout.php" class="bg-red-500 hover:bg-red-600 px-3 py-2 rounded text-sm">
                    <i class="fas fa-sign-out-alt mr-1"></i>Salir
                </a>
            </div>
        </div>
    </header>

    <!-- Lista de Pedidos Estilo Turnos -->
    <main class="max-w-7xl mx-auto p-6">
        <?php if (empty($pedidos)): ?>
            <div class="text-center py-20">
                <i class="fas fa-coffee text-6xl text-gray-300 mb-4"></i>
                <h2 class="text-2xl text-gray-500">No hay pedidos pendientes</h2>
                <p class="text-gray-400">Local 1 está al día</p>
                <div class="mt-6">
                    <a href="pedidos.php" class="bg-blue-500 hover:bg-blue-600 text-white px-6 py-3 rounded-lg">
                        <i class="fas fa-list mr-2"></i>Ver Todos los Pedidos
                    </a>
                </div>
            </div>
        <?php else: ?>
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                <?php foreach ($pedidos as $pedido): ?>
                    <div class="pedido-card pedido-<?= strtolower($pedido['estado']) ?> 
                                <?= !$pedido['impreso'] ? 'sin-imprimir' : '' ?>
                                <?= $pedido['minutos_transcurridos'] > 60 ? 'urgente' : '' ?>
                                p-6 rounded-lg shadow-lg"
                         data-id="<?= $pedido['id'] ?>">
                        
                        <!-- Header del Pedido -->
                        <div class="flex justify-between items-center mb-4">
                            <div class="text-2xl font-bold text-gray-800">
                                #<?= $pedido['id'] ?>
                            </div>
                            
                            <div class="flex items-center space-x-2">
                                <!-- Tiempo -->
                                <span class="text-sm px-2 py-1 rounded 
                                           <?= $pedido['minutos_transcurridos'] > 60 ? 
                                               'bg-red-100 text-red-800' : 
                                               'bg-gray-100 text-gray-800' ?>">
                                    <?= $pedido['minutos_transcurridos'] ?>min
                                </span>
                                
                                <!-- Sin imprimir -->
                                <?php if (!$pedido['impreso']): ?>
                                    <span class="bg-red-500 text-white text-xs px-2 py-1 rounded-full font-bold">
                                        SIN IMPRIMIR
                                    </span>
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- Cliente -->
                        <div class="mb-4">
                            <h3 class="font-bold text-lg text-gray-800">
                                <?= htmlspecialchars($pedido['nombre'] . ' ' . $pedido['apellido']) ?>
                            </h3>
                            <p class="text-gray-600">
                                <i class="fas fa-phone mr-2"></i><?= htmlspecialchars($pedido['telefono']) ?>
                            </p>
                        </div>

                        <!-- Producto -->
                        <div class="mb-4 bg-gray-50 p-3 rounded">
                            <h4 class="font-bold text-gray-800"><?= htmlspecialchars($pedido['producto']) ?></h4>
                            <div class="flex justify-between items-center mt-2">
                                <span class="text-sm text-gray-600">
                                    <?= $pedido['modalidad'] ?> | <?= $pedido['forma_pago'] ?>
                                </span>
                                <span class="font-bold text-green-600 text-xl">
                                    $<?= number_format($pedido['precio'], 0, ',', '.') ?>
                                </span>
                            </div>
                        </div>

                        <!-- Observaciones -->
                        <?php if ($pedido['observaciones']): ?>
                            <div class="mb-4 bg-blue-50 p-3 rounded">
                                <h5 class="font-semibold text-blue-900 text-sm mb-1">Observaciones:</h5>
                                <p class="text-blue-800 text-sm"><?= htmlspecialchars($pedido['observaciones']) ?></p>
                            </div>
                        <?php endif; ?>

                        <!-- Estado y Acciones -->
                        <div class="flex justify-between items-center">
                            <div class="text-left">
                                <span class="text-lg font-bold <?php
                                    switch ($pedido['estado']) {
                                        case 'Pendiente': echo 'text-yellow-600'; break;
                                        case 'Preparando': echo 'text-blue-600'; break;
                                        case 'Listo': echo 'text-green-600'; break;
                                        default: echo 'text-gray-600';
                                    }
                                ?>">
                                    <?= $pedido['estado'] ?>
                                </span>
                            </div>
                            
                            <div class="flex space-x-1">
                                <!-- Botón de acción principal -->
                                <?php if ($pedido['estado'] === 'Pendiente'): ?>
                                    <button onclick="cambiarEstado(<?= $pedido['id'] ?>, 'Preparando')" 
                                            class="btn btn-preparando text-xs">
                                        <i class="fas fa-fire mr-1"></i>Preparar
                                    </button>
                                <?php elseif ($pedido['estado'] === 'Preparando'): ?>
                                    <button onclick="cambiarEstado(<?= $pedido['id'] ?>, 'Listo')" 
                                            class="btn btn-listo text-xs">
                                        <i class="fas fa-check mr-1"></i>Listo
                                    </button>
                                <?php elseif ($pedido['estado'] === 'Listo'): ?>
                                    <button onclick="cambiarEstado(<?= $pedido['id'] ?>, 'Entregado')" 
                                            class="btn btn-entregado text-xs">
                                        <i class="fas fa-handshake mr-1"></i>Entregar
                                    </button>
                                <?php endif; ?>
                                
                                <!-- Botón imprimir -->
                                <button onclick="imprimirComanda(<?= $pedido['id'] ?>)" 
                                        class="btn btn-imprimir text-xs"
                                        title="Imprimir comanda">
                                    <i class="fas fa-print"></i>
                                </button>
                                
                                <!-- Marcar como impreso si no está impreso -->
                                <?php if (!$pedido['impreso']): ?>
                                    <button onclick="marcarImpreso(<?= $pedido['id'] ?>)" 
                                            class="btn" style="background: #6b7280; color: white; font-size: 12px;">
                                        <i class="fas fa-check-square"></i>
                                    </button>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </main>

    <!-- BOTÓN FLOTANTE DE PEDIDOS EXPRESS -->
    <div class="fixed bottom-4 right-4 z-50">
        <button onclick="abrirPedidoExpress()" class="bg-green-600 hover:bg-green-700 text-white font-bold py-4 px-6 rounded-full shadow-lg btn-express">
            <i class="fas fa-plus-circle mr-2"></i>
            Pedido Express
        </button>
    </div>

    <!-- MODAL DE PEDIDO EXPRESS -->
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
                            <div class="turno-card relative cursor-pointer bg-white p-4 rounded-lg border-2 border-gray-300 hover:border-blue-400 text-center" onclick="seleccionarTurno('M', this)">
                                <i class="fas fa-sun text-yellow-500 text-2xl mb-2"></i>
                                <div class="font-bold">Mañana</div>
                                <div class="text-xs text-gray-500">6am - 2pm</div>
                                <input type="radio" name="turno" value="M" class="hidden">
                            </div>
                            <div class="turno-card relative cursor-pointer bg-white p-4 rounded-lg border-2 border-gray-300 hover:border-blue-400 text-center" onclick="seleccionarTurno('S', this)">
                                <i class="fas fa-cloud-sun text-orange-500 text-2xl mb-2"></i>
                                <div class="font-bold">Siesta</div>
                                <div class="text-xs text-gray-500">2pm - 6pm</div>
                                <input type="radio" name="turno" value="S" class="hidden">
                            </div>
                            <div class="turno-card relative cursor-pointer bg-white p-4 rounded-lg border-2 border-gray-300 hover:border-blue-400 text-center" onclick="seleccionarTurno('T', this)">
                                <i class="fas fa-moon text-purple-500 text-2xl mb-2"></i>
                                <div class="font-bold">Tarde</div>
                                <div class="text-xs text-gray-500">6pm - 11pm</div>
                                <input type="radio" name="turno" value="T" class="hidden">
                            </div>
                        </div>
                    </div>
                </div>

                <!-- PEDIDOS COMUNES - MEJORADO -->
                <div id="seccionComunes" class="bg-yellow-50 rounded-lg p-4 mb-6 transition-all duration-300">
                    <h3 class="font-bold text-yellow-900 mb-4">
                        <i class="fas fa-star mr-2"></i>¿Qué va a llevar?
                    </h3>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        
                        <!-- J y Q x24 -->
                        <div class="pedido-card bg-white rounded-lg border-2 border-yellow-200 p-4 hover:border-yellow-400 cursor-pointer transition-all" onclick="seleccionarComun('jyq24')" data-tipo="comun">
                            <div class="flex items-center justify-between">
                                <div>
                                    <h4 class="font-bold text-gray-800 text-lg">Jamón y Queso x24</h4>
                                    <p class="text-sm text-gray-600">3 planchas - Clásico</p>
                                    <p class="font-bold text-green-600 text-xl">$18.000</p>
                                </div>
                                <div class="text-yellow-500">
                                    <i class="fas fa-bread-slice text-4xl"></i>
                                </div>
                            </div>
                            <input type="radio" name="pedido_tipo" value="jyq24" class="hidden">
                        </div>

                        <!-- J y Q x48 -->
                        <div class="pedido-card bg-white rounded-lg border-2 border-yellow-200 p-4 hover:border-yellow-400 cursor-pointer transition-all" onclick="seleccionarComun('jyq48')" data-tipo="comun">
                            <div class="flex items-center justify-between">
                                <div>
                                    <h4 class="font-bold text-gray-800 text-lg">Jamón y Queso x48</h4>
                                    <p class="text-sm text-gray-600">6 planchas - Clásico</p>
                                    <p class="font-bold text-green-600 text-xl">$22.000</p>
                                </div>
                                <div class="text-yellow-500">
                                    <i class="fas fa-hamburger text-4xl"></i>
                                </div>
                            </div>
                            <input type="radio" name="pedido_tipo" value="jyq48" class="hidden">
                        </div>

                        <!-- Surtido Clásico x48 -->
                        <div class="pedido-card bg-white rounded-lg border-2 border-yellow-200 p-4 hover:border-yellow-400 cursor-pointer transition-all" onclick="seleccionarComun('surtido_clasico48')" data-tipo="comun">
                            <div class="flex items-center justify-between">
                                <div>
                                    <h4 class="font-bold text-gray-800 text-lg">Surtido Clásico x48</h4>
                                    <p class="text-sm text-gray-600">J y Q, Lechuga, Tomate, Huevo</p>
                                    <p class="font-bold text-green-600 text-xl">$20.000</p>
                                </div>
                                <div class="text-orange-500">
                                    <i class="fas fa-layer-group text-4xl"></i>
                                </div>
                            </div>
                            <input type="radio" name="pedido_tipo" value="surtido_clasico48" class="hidden">
                        </div>

                        <!-- Surtido Especial x48 -->
                        <div class="pedido-card bg-white rounded-lg border-2 border-yellow-200 p-4 hover:border-yellow-400 cursor-pointer transition-all" onclick="seleccionarComun('surtido_especial48')" data-tipo="comun">
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

                <!-- PERSONALIZADO - MEJORADO -->
                <div id="seccionPersonalizado" class="bg-purple-50 rounded-lg p-4 mb-6 transition-all duration-300">
                    <h3 class="font-bold text-purple-900 mb-4">
                        <i class="fas fa-palette mr-2"></i>¿Algo Diferente?
                    </h3>
                    
                    <div class="pedido-card bg-white rounded-lg border-2 border-purple-200 p-4 hover:border-purple-400 cursor-pointer transition-all" onclick="seleccionarPersonalizado()" data-tipo="personalizado">
                        <div class="flex items-center justify-between">
                            <div>
                                <h4 class="font-bold text-gray-800 text-lg">Personalizado</h4>
                                <p class="text-sm text-gray-600">Elegí cantidad y sabores</p>
                            </div>
                            <div class="text-purple-500">
                                <i class="fas fa-sliders-h text-4xl"></i>
                            </div>
                        </div>
                        <input type="radio" name="pedido_tipo" value="personalizado" class="hidden">
                    </div>

                    <!-- Panel personalizado (oculto por defecto) -->
                    <div id="panelPersonalizado" class="mt-4 hidden">
                        
                        <!-- Cantidad -->
                        <div class="mb-4">
                            <label class="block text-sm font-medium text-gray-700 mb-2">Cantidad de sándwiches</label>
                            <div class="flex items-center gap-4">
                                <button type="button" onclick="cambiarCantidad(-8)" class="bg-gray-200 hover:bg-gray-300 px-3 py-1 rounded">-8</button>
                                <button type="button" onclick="cambiarCantidad(-1)" class="bg-gray-200 hover:bg-gray-300 px-3 py-1 rounded">-1</button>
                                <input type="number" id="cantidadPersonalizada" value="24" min="1" max="200" class="w-20 text-center p-2 border border-gray-300 rounded-lg" onchange="actualizarPlanchas()">
                                <button type="button" onclick="cambiarCantidad(1)" class="bg-gray-200 hover:bg-gray-300 px-3 py-1 rounded">+1</button>
                                <button type="button" onclick="cambiarCantidad(8)" class="bg-gray-200 hover:bg-gray-300 px-3 py-1 rounded">+8</button>
                                <span class="text-sm text-gray-600">(<span id="planchasInfo">3</span> planchas)</span>
                            </div>
                        </div>

                        <!-- Sabores -->
                        <div class="mb-4">
                            <label class="block text-sm font-medium text-gray-700 mb-2">Seleccionar Sabores</label>
                            
                            <!-- Sabores Comunes -->
                            <h5 class="font-semibold text-gray-800 mb-2">Comunes</h5>
                            <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-2 mb-4">
                                <label><input type="checkbox" value="Jamón y Queso" class="sabor-checkbox">Jamón y Queso</label>
                                <label><input type="checkbox" value="Lechuga" class="sabor-checkbox">Lechuga</label>
                                <label><input type="checkbox" value="Tomate" class="sabor-checkbox">Tomate</label>
                                <label><input type="checkbox" value="Huevo" class="sabor-checkbox">Huevo</label>
                                <label><input type="checkbox" value="Choclo" class="sabor-checkbox">Choclo</label>
                                <label><input type="checkbox" value="Aceitunas" class="sabor-checkbox">Aceitunas</label>
                            </div>

                            <!-- Sabores Premium -->
                            <h5 class="font-semibold text-gray-800 mb-2">Premium (+$100 c/u)</h5>
                            <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-2">
                                <label><input type="checkbox" value="Ananá" class="sabor-checkbox premium">Ananá</label>
                                <label><input type="checkbox" value="Atún" class="sabor-checkbox premium">Atún</label>
                                <label><input type="checkbox" value="Berenjena" class="sabor-checkbox premium">Berenjena</label>
                                <label><input type="checkbox" value="Durazno" class="sabor-checkbox premium">Durazno</label>
                                <label><input type="checkbox" value="Jamón Crudo" class="sabor-checkbox premium">Jamón Crudo</label>
                                <label><input type="checkbox" value="Morrón" class="sabor-checkbox premium">Morrón</label>
                                <label><input type="checkbox" value="Palmito" class="sabor-checkbox premium">Palmito</label>
                                <label><input type="checkbox" value="Panceta" class="sabor-checkbox premium">Panceta</label>
                                <label><input type="checkbox" value="Pollo" class="sabor-checkbox premium">Pollo</label>
                                <label><input type="checkbox" value="Roquefort" class="sabor-checkbox premium">Roquefort</label>
                                <label><input type="checkbox" value="Salame" class="sabor-checkbox premium">Salame</label>
                            </div>
                        </div>

                        <!-- Precio estimado -->
                        <div class="bg-green-100 rounded-lg p-3">
                            <p class="font-bold text-green-800">Precio estimado: $<span id="precioEstimado">18000</span></p>
                            <p class="text-sm text-green-600">*Precio final puede variar según sabores premium seleccionados</p>
                        </div>
                    </div>
                </div>

                <!-- Forma de pago -->
                <div class="bg-gray-50 rounded-lg p-4 mb-6">
                    <h3 class="font-bold text-gray-900 mb-3">
                        <i class="fas fa-credit-card mr-2"></i>Forma de Pago
                    </h3>
                    <div class="grid grid-cols-2 md:grid-cols-4 gap-2">
                        <label class="flex items-center cursor-pointer bg-white p-3 rounded-lg border border-gray-300 hover:border-green-500">
                            <input type="radio" name="forma_pago" value="Efectivo" class="mr-2">
                            <i class="fas fa-money-bill-wave text-green-500 mr-2"></i>
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

    <script>
    // Variables globales
let pedidoSeleccionado = null;
let precioBase = 0;
let turnoSeleccionado = null;

// Precios predefinidos
const precios = {
    'jyq24': 18000,
    'jyq48': 22000,
    'surtido_clasico48': 20000,
    'surtido_especial48': 25000,
    'personalizado_base': 750 // por sándwich
};

// FUNCIÓN DE SELECCIÓN DE TURNOS (CORREGIDA)
function seleccionarTurno(turno, elemento) {
    // Limpiar selecciones anteriores de turnos
    document.querySelectorAll('.turno-card').forEach(card => {
        card.classList.remove('seleccionado');
    });
    
    // Marcar el turno seleccionado
    elemento.classList.add('seleccionado');
    elemento.querySelector('input[type="radio"]').checked = true;
    
    turnoSeleccionado = turno;
    console.log(`✅ TURNO SELECCIONADO: ${turno}`);
}

// SELECCIÓN DE PEDIDOS COMUNES - SÚPER VISUAL
function seleccionarComun(tipo) {
    // Bloquear sección personalizada
    document.getElementById('seccionPersonalizado').classList.add('seccion-bloqueada');
    document.getElementById('seccionComunes').classList.remove('seccion-bloqueada');
    
    // Limpiar todas las selecciones
    document.querySelectorAll('.pedido-card').forEach(card => {
        card.classList.remove('seleccionado');
    });
    
    // Ocultar panel personalizado
    document.getElementById('panelPersonalizado').classList.add('hidden');
    
    // Marcar el seleccionado con efectos súper visuales
    event.currentTarget.classList.add('seleccionado');
    event.currentTarget.querySelector('input[type="radio"]').checked = true;
    
    pedidoSeleccionado = tipo;
    precioBase = precios[tipo];
    
    console.log(`✅ COMÚN SELECCIONADO: ${tipo} - $${precioBase.toLocaleString()}`);
}

// SELECCIÓN PERSONALIZADO - SÚPER VISUAL
function seleccionarPersonalizado() {
    // Bloquear sección comunes
    document.getElementById('seccionComunes').classList.add('seccion-bloqueada');
    document.getElementById('seccionPersonalizado').classList.remove('seccion-bloqueada');
    
    // Limpiar todas las selecciones
    document.querySelectorAll('.pedido-card').forEach(card => {
        card.classList.remove('seleccionado');
    });
    
    // Marcar personalizado con efectos súper visuales
    event.currentTarget.classList.add('seleccionado');
    event.currentTarget.querySelector('input[type="radio"]').checked = true;
    
    // Mostrar panel personalizado
    document.getElementById('panelPersonalizado').classList.remove('hidden');
    
    pedidoSeleccionado = 'personalizado';
    actualizarPrecioPersonalizado();
    
    console.log('✅ PERSONALIZADO SELECCIONADO');
}

// Funciones para cantidad personalizada
function cambiarCantidad(delta) {
    const input = document.getElementById('cantidadPersonalizada');
    const nuevaCantidad = parseInt(input.value) + delta;
    if (nuevaCantidad >= 1 && nuevaCantidad <= 200) {
        input.value = nuevaCantidad;
        actualizarPlanchas();
        actualizarPrecioPersonalizado();
    }
}

function actualizarPlanchas() {
    const cantidad = parseInt(document.getElementById('cantidadPersonalizada').value) || 0;
    const planchas = Math.ceil(cantidad / 8);
    document.getElementById('planchasInfo').textContent = planchas;
}

function actualizarPrecioPersonalizado() {
    const cantidad = parseInt(document.getElementById('cantidadPersonalizada').value) || 0;
    const saboresPremium = document.querySelectorAll('.sabor-checkbox.premium:checked').length;
    
    let precio = cantidad * precios.personalizado_base;
    precio += saboresPremium * 100; // $100 extra por cada sabor premium
    
    document.getElementById('precioEstimado').textContent = precio.toLocaleString();
    precioBase = precio;
}

// Resetear formulario mejorado
function resetearFormulario() {
    document.getElementById('formPedidoExpress').reset();
    
    // Limpiar todas las selecciones visuales
    document.querySelectorAll('.pedido-card').forEach(card => {
        card.classList.remove('seleccionado');
    });
    document.querySelectorAll('.turno-card').forEach(card => {
        card.classList.remove('seleccionado');
    });
    document.querySelectorAll('.seccion-bloqueada').forEach(seccion => {
        seccion.classList.remove('seccion-bloqueada');
    });
    
    document.getElementById('panelPersonalizado').classList.add('hidden');
    pedidoSeleccionado = null;
    turnoSeleccionado = null;
    precioBase = 0;
}

// Event listeners para checkboxes de sabores
document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('.sabor-checkbox.premium').forEach(checkbox => {
        checkbox.addEventListener('change', actualizarPrecioPersonalizado);
    });
    
    // Inicializar precio personalizado
    actualizarPlanchas();
    actualizarPrecioPersonalizado();
});

// ENVÍO DEL FORMULARIO - CON TURNOS (CORREGIDO)
document.getElementById('formPedidoExpress').addEventListener('submit', function(e) {
    e.preventDefault();
    
    // Validaciones esenciales
    const nombre = document.getElementById('nombre').value.trim();
    const apellido = document.getElementById('apellido').value.trim();
    const turno = document.querySelector('input[name="turno"]:checked')?.value;
    const formaPago = document.querySelector('input[name="forma_pago"]:checked')?.value;
    
    if (!nombre || !apellido) {
        alert('Solo necesito nombre y apellido del cliente');
        document.getElementById('nombre').focus();
        return;
    }
    
    if (!turno) {
        alert('¿Para qué turno es el pedido?');
        return;
    }
    
    if (!formaPago) {
        alert('¿Cómo va a pagar?');
        return;
    }
    
    if (!pedidoSeleccionado) {
        alert('¿Qué va a llevar?');
        return;
    }
    
    // Construir pedido súper rápido - SIEMPRE RETIRO EN LOCAL 1
    const pedido = {
        nombre: nombre,
        apellido: apellido,
        telefono: document.getElementById('telefono').value.trim(),
        modalidad: 'Retiro', // FIJO - Todos los pedidos express son retiro
        forma_pago: formaPago,
        observaciones: `Turno: ${turno}\n${document.getElementById('observaciones').value.trim()}`,
        tipo_pedido: pedidoSeleccionado,
        precio: precioBase,
        ubicacion: 'Local 1', // FIJO - Siempre Local 1
        estado: 'Pendiente'
    };
    
    // Si es personalizado, agregar detalles
    if (pedidoSeleccionado === 'personalizado') {
        const cantidad = parseInt(document.getElementById('cantidadPersonalizada').value);
        const saboresSeleccionados = Array.from(document.querySelectorAll('.sabor-checkbox:checked')).map(cb => cb.value);
        
        pedido.cantidad = cantidad;
        pedido.sabores = saboresSeleccionados;
        pedido.producto = `Personalizado x${cantidad} (${Math.ceil(cantidad/8)} plancha${cantidad > 8 ? 's' : ''})`;
        
        if (saboresSeleccionados.length > 0) {
            pedido.observaciones += `\nSabores: ${saboresSeleccionados.join(', ')}`;
        }
    } else {
        // Pedidos comunes
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
    // Mostrar indicador de carga
    const submitBtn = document.querySelector('button[type="submit"]');
    const originalText = submitBtn.innerHTML;
    submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Creando...';
    submitBtn.disabled = true;
    
    // Enviar pedido al servidor
    fetch('procesar_pedido_express.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify(pedido)
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Mostrar confirmación exitosa
            alert(`✅ Pedido Express #${data.pedido_id} creado exitosamente!\n\nCliente: ${data.data.cliente}\nProducto: ${data.data.producto}\nPrecio: $${data.data.precio.toLocaleString()}\nTurno: ${document.querySelector('input[name="turno"]:checked')?.value}`);
            
            // Cerrar modal
            cerrarPedidoExpress();
            
            // Recargar la página para mostrar el nuevo pedido
            setTimeout(() => {
                window.location.reload();
            }, 1000);
            
        } else {
            alert('❌ Error al crear pedido: ' + data.error);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('❌ Error de conexión. Verifique su conexión a internet.');
    })
    .finally(() => {
        // Restaurar botón
        submitBtn.innerHTML = originalText;
        submitBtn.disabled = false;
    });
}

// Funciones del modal
function abrirPedidoExpress() {
    document.getElementById('modalPedidoExpress').classList.remove('hidden');
    document.body.style.overflow = 'hidden';
}

function cerrarPedidoExpress() {
    document.getElementById('modalPedidoExpress').classList.add('hidden');
    document.body.style.overflow = 'auto';
    resetearFormulario();
}

// Cerrar modal con ESC
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        cerrarPedidoExpress();
    }
});
    </script>

</body>
</html>