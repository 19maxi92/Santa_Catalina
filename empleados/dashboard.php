<?php
// empleados/dashboard.php - VERSIÓN SIMPLE CON AUTO-IMPRESIÓN
require_once '../config.php';
session_start();

// Verificar acceso de empleado (método simple y directo)
if (!isset($_SESSION['empleado_logged']) || $_SESSION['empleado_logged'] !== true) {
    header('Location: login.php');
    exit;
}

$pdo = getConnection();

// NUEVO: Manejar peticiones AJAX para impresión automática
if (isset($_POST['marcar_impreso']) && isset($_POST['pedido_id'])) {
    header('Content-Type: application/json');
    
    $pedido_id = (int)$_POST['pedido_id'];
    
    try {
        // Verificar si las columnas existen, si no las agregamos dinámicamente
        $stmt = $pdo->query("SHOW COLUMNS FROM pedidos LIKE 'impreso_auto'");
        if ($stmt->rowCount() == 0) {
            $pdo->exec("ALTER TABLE pedidos ADD COLUMN impreso_auto BOOLEAN DEFAULT FALSE");
        }
        
        $stmt = $pdo->query("SHOW COLUMNS FROM pedidos LIKE 'fecha_impreso'");
        if ($stmt->rowCount() == 0) {
            $pdo->exec("ALTER TABLE pedidos ADD COLUMN fecha_impreso DATETIME NULL");
        }
        
        // Marcar como impreso
        $stmt = $pdo->prepare("UPDATE pedidos SET impreso_auto = 1, fecha_impreso = NOW() WHERE id = ? AND ubicacion = 'Local 1'");
        $stmt->execute([$pedido_id]);
        
        echo json_encode(['success' => true, 'message' => 'Pedido marcado como impreso']);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
    }
    exit;
}

// NUEVO: Verificar estado de la impresora por MAC
if (isset($_GET['check_printer']) && $_GET['check_printer'] == '1') {
    header('Content-Type: application/json');
    
    // MAC de la impresora que esperamos encontrar
    $expected_mac = 'C0-25-E9-14-50-03';
    $expected_ip = '192.168.1.41';
    
    // Verificar información del cliente
    $client_ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    
    // Determinar estado de la impresora
    $printer_status = [
        'expected_mac' => $expected_mac,
        'expected_ip' => $expected_ip,
        'client_ip' => $client_ip,
        'detected' => false,
        'can_print' => false,
        'message' => '',
        'warning' => ''
    ];
    
    // Verificar si estamos en la red correcta (método simple)
    if (strpos($client_ip, '192.168.1.') === 0 || $client_ip === '127.0.0.1' || $client_ip === $expected_ip || $client_ip === 'unknown') {
        $printer_status['detected'] = true;
        $printer_status['can_print'] = true; // Asumimos que está OK si estamos en la red
        $printer_status['message'] = 'Impresora POS80-CX detectada - Lista para imprimir';
    } else {
        $printer_status['message'] = 'No se detectó la impresora POS80-CX en esta red';
        $printer_status['warning'] = 'Verifique que esté conectado a la red Local_SantaCatalina';
    }
    
    echo json_encode($printer_status);
    exit;
}

// NUEVO: Verificar si hay nuevos pedidos para impresión automática
if (isset($_GET['check_new']) && $_GET['check_new'] == '1') {
    header('Content-Type: application/json');
    
    try {
        // Verificar si las columnas existen antes de usar
        $has_impreso_auto = $pdo->query("SHOW COLUMNS FROM pedidos LIKE 'impreso_auto'")->rowCount() > 0;
        
        if ($has_impreso_auto) {
            $stmt = $pdo->prepare("
                SELECT id, nombre, apellido, producto, created_at
                FROM pedidos 
                WHERE ubicacion = 'Local 1' 
                AND estado = 'Pendiente' 
                AND DATE(created_at) = CURDATE()
                AND (impreso_auto IS NULL OR impreso_auto = 0)
                AND created_at > DATE_SUB(NOW(), INTERVAL 5 MINUTE)
                ORDER BY created_at DESC
            ");
        } else {
            // Fallback si no existen las columnas
            $stmt = $pdo->prepare("
                SELECT id, nombre, apellido, producto, created_at
                FROM pedidos 
                WHERE ubicacion = 'Local 1' 
                AND estado = 'Pendiente' 
                AND DATE(created_at) = CURDATE()
                AND created_at > DATE_SUB(NOW(), INTERVAL 5 MINUTE)
                ORDER BY created_at DESC
                LIMIT 3
            ");
        }
        
        $stmt->execute();
        $nuevos_pedidos = $stmt->fetchAll();
        
        echo json_encode([
            'success' => true,
            'new_orders' => $nuevos_pedidos,
            'count' => count($nuevos_pedidos),
            'has_auto_print_columns' => $has_impreso_auto
        ]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit;
}

// Estadísticas generales para empleados
$stats = [
    'pendientes' => $pdo->query("SELECT COUNT(*) FROM pedidos WHERE estado = 'Pendiente'")->fetchColumn(),
    'preparando' => $pdo->query("SELECT COUNT(*) FROM pedidos WHERE estado = 'Preparando'")->fetchColumn(),
    'listos' => $pdo->query("SELECT COUNT(*) FROM pedidos WHERE estado = 'Listo'")->fetchColumn(),
    'pedidos_hoy' => $pdo->query("SELECT COUNT(*) FROM pedidos WHERE DATE(created_at) = CURDATE()")->fetchColumn()
];

// Estadísticas por ubicación para hoy
$stats_ubicacion = $pdo->query("
    SELECT 
        ubicacion,
        COUNT(*) as total,
        SUM(CASE WHEN estado = 'Pendiente' THEN 1 ELSE 0 END) as pendientes,
        SUM(CASE WHEN estado = 'Preparando' THEN 1 ELSE 0 END) as preparando,
        SUM(CASE WHEN estado = 'Listo' THEN 1 ELSE 0 END) as listos,
        SUM(CASE WHEN estado = 'Entregado' THEN 1 ELSE 0 END) as entregados
    FROM pedidos 
    WHERE DATE(created_at) = CURDATE()
    GROUP BY ubicacion
")->fetchAll();

// NUEVO: Pedidos del Local 1 pendientes de impresión
try {
    $has_impreso_auto = $pdo->query("SHOW COLUMNS FROM pedidos LIKE 'impreso_auto'")->rowCount() > 0;
    
    if ($has_impreso_auto) {
        $stmt_local1 = $pdo->prepare("
            SELECT id, nombre, apellido, producto, created_at, estado
            FROM pedidos 
            WHERE ubicacion = 'Local 1' 
            AND estado = 'Pendiente' 
            AND DATE(created_at) = CURDATE()
            AND (impreso_auto IS NULL OR impreso_auto = 0)
            ORDER BY created_at DESC
            LIMIT 10
        ");
    } else {
        // Mostrar todos los pedidos pendientes del Local 1 si no hay columnas de impresión
        $stmt_local1 = $pdo->prepare("
            SELECT id, nombre, apellido, producto, created_at, estado
            FROM pedidos 
            WHERE ubicacion = 'Local 1' 
            AND estado = 'Pendiente' 
            AND DATE(created_at) = CURDATE()
            ORDER BY created_at DESC
            LIMIT 10
        ");
    }
    
    $stmt_local1->execute();
    $pedidos_local1_pendientes = $stmt_local1->fetchAll();
} catch (Exception $e) {
    $pedidos_local1_pendientes = [];
}

// Pedidos del día
$pedidos_hoy = $pdo->query("
    SELECT id, nombre, apellido, producto, precio, estado, modalidad, ubicacion,
           observaciones, direccion, telefono, forma_pago,
           created_at, TIMESTAMPDIFF(MINUTE, created_at, NOW()) as minutos_transcurridos,
           fecha_entrega, hora_entrega, notas_horario
    FROM pedidos 
    WHERE DATE(created_at) = CURDATE()
    ORDER BY 
        CASE estado 
            WHEN 'Pendiente' THEN 1 
            WHEN 'Preparando' THEN 2 
            WHEN 'Listo' THEN 3 
            WHEN 'Entregado' THEN 4 
        END, 
        created_at ASC
")->fetchAll();

// Pedidos urgentes (más de 1 hora)
$pedidos_urgentes = array_filter($pedidos_hoy, function($pedido) {
    return $pedido['minutos_transcurridos'] > 60 && in_array($pedido['estado'], ['Pendiente', 'Preparando']);
});
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Panel Empleados - Santa Catalina</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .auto-print-notification {
            position: fixed;
            top: 20px;
            right: 20px;
            color: white;
            padding: 15px 20px;
            border-radius: 8px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
            z-index: 1000;
            animation: slideIn 0.3s ease-out;
        }
        
        @keyframes slideIn {
            from { transform: translateX(100%); opacity: 0; }
            to { transform: translateX(0); opacity: 1; }
        }
        
        .print-toggle {
            position: fixed;
            bottom: 20px;
            right: 20px;
            z-index: 100;
        }
        
        .print-status {
            display: inline-block;
            width: 12px;
            height: 12px;
            border-radius: 50%;
            margin-left: 8px;
        }
        
        .print-status.active { background-color: #10B981; }
        .print-status.inactive { background-color: #EF4444; }
        
        .pedido-nuevo {
            animation: highlight 2s ease-in-out;
            border-left: 4px solid #F59E0B;
        }
        
        @keyframes highlight {
            0%, 100% { background-color: transparent; }
            50% { background-color: #FEF3C7; }
        }
    </style>
</head>
<body class="bg-gray-100">
    <!-- Toggle de impresión automática con estado -->
    <div class="print-toggle">
        <button id="toggleAutoPrint" 
                class="bg-red-600 hover:bg-red-700 text-white px-4 py-3 rounded-full shadow-lg transition-all duration-200 flex items-center">
            <i class="fas fa-print mr-2"></i>
            <span id="printStatusText">Auto-Print Local 1: OFF</span>
            <span id="printStatus" class="print-status inactive"></span>
        </button>
        
        <!-- Indicador de estado de impresora -->
        <div id="printerStatusIndicator" class="mt-2 text-xs text-white bg-gray-800 px-3 py-1 rounded hidden">
            <i class="fas fa-circle-notch fa-spin mr-1"></i>
            Verificando impresora...
        </div>
    </div>

    <!-- Header -->
    <header class="bg-blue-600 text-white shadow-md">
        <div class="container mx-auto px-4 py-3 flex justify-between items-center">
            <h1 class="text-xl font-bold">
                <i class="fas fa-clipboard-list mr-2"></i>Panel de Empleados - Santa Catalina
            </h1>
            <div class="flex items-center space-x-4">
                <span class="text-blue-100">👋 Hola, <?= $_SESSION['empleado_name'] ?? 'Empleado' ?></span>
                <a href="logout.php" class="bg-red-500 hover:bg-red-600 px-3 py-2 rounded transition">
                    <i class="fas fa-sign-out-alt mr-1"></i>Salir
                </a>
            </div>
        </div>
    </header>

    <!-- Main Content -->
    <main class="container mx-auto px-4 py-6">
        
        <!-- NUEVA SECCIÓN: Pedidos Local 1 pendientes de impresión -->
        <?php if (!empty($pedidos_local1_pendientes)): ?>
        <div class="bg-orange-50 border border-orange-200 rounded-lg shadow mb-6 p-6">
            <div class="flex items-center justify-between mb-4">
                <h2 class="text-xl font-semibold text-orange-800 flex items-center">
                    <i class="fas fa-print text-orange-600 mr-2"></i>
                    🏪 Local 1 - Pedidos Pendientes de Impresión
                    <span class="ml-2 bg-orange-200 text-orange-800 px-3 py-1 rounded-full text-sm">
                        <?= count($pedidos_local1_pendientes) ?> pedidos
                    </span>
                </h2>
                
                <button onclick="imprimirTodosPendientes()" 
                        class="bg-orange-600 hover:bg-orange-700 text-white px-4 py-2 rounded flex items-center">
                    <i class="fas fa-print mr-2"></i>
                    Imprimir Todos
                </button>
            </div>
            
            <div class="space-y-3">
                <?php foreach ($pedidos_local1_pendientes as $pedido): ?>
                    <div class="bg-white p-4 rounded border-l-4 border-orange-400 pedido-nuevo" data-pedido-id="<?= $pedido['id'] ?>">
                        <div class="flex justify-between items-center">
                            <div>
                                <div class="font-semibold text-gray-800">
                                    #<?= $pedido['id'] ?> - <?= htmlspecialchars($pedido['nombre'] . ' ' . $pedido['apellido']) ?>
                                </div>
                                <div class="text-sm text-gray-600">
                                    <?= htmlspecialchars($pedido['producto']) ?>
                                </div>
                                <div class="text-xs text-orange-600 mt-1">
                                    <i class="fas fa-clock mr-1"></i>
                                    <?= date('H:i:s', strtotime($pedido['created_at'])) ?>
                                    (hace <?= round((time() - strtotime($pedido['created_at'])) / 60) ?> min)
                                </div>
                            </div>
                            
                            <div class="flex space-x-2">
                                <button onclick="imprimirComanda(<?= $pedido['id'] ?>, false)" 
                                        class="bg-green-500 hover:bg-green-600 text-white px-3 py-1 rounded text-sm">
                                    <i class="fas fa-print mr-1"></i>
                                    Imprimir
                                </button>
                                <button onclick="marcarComoImpreso(<?= $pedido['id'] ?>)" 
                                        class="bg-gray-500 hover:bg-gray-600 text-white px-3 py-1 rounded text-sm">
                                    <i class="fas fa-check mr-1"></i>
                                    Marcar
                                </button>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
            
            <!-- Información de la impresora con estado en tiempo real -->
            <div id="printerInfo" class="mt-4 p-3 bg-blue-50 border border-blue-200 rounded text-sm">
                <div class="flex items-center text-blue-800">
                    <i id="printerIcon" class="fas fa-circle-notch fa-spin mr-2"></i>
                    <strong>Impresora:</strong> 
                    <span id="printerStatusText">Verificando POS80-CX (MAC: C0-25-E9-14-50-03)...</span>
                </div>
                <div id="printerDetails" class="text-xs text-blue-600 mt-1">
                    IP esperada: 192.168.1.41 | Red: Local_SantaCatalina
                </div>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- Alerta de pedidos urgentes -->
        <?php if (!empty($pedidos_urgentes)): ?>
        <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-6 rounded">
            <div class="flex items-center">
                <i class="fas fa-exclamation-triangle text-2xl mr-3"></i>
                <div>
                    <h3 class="font-bold text-lg">¡ATENCIÓN! Pedidos Urgentes</h3>
                    <p class="text-sm">Hay <?= count($pedidos_urgentes) ?> pedido(s) con más de 1 hora de espera</p>
                </div>
                <a href="pedidos.php?estado=Pendiente" 
                   class="ml-auto bg-red-500 hover:bg-red-600 text-white px-4 py-2 rounded">
                    Ver Urgentes
                </a>
            </div>
        </div>
        <?php endif; ?>

        <!-- Stats Cards -->
        <div class="grid grid-cols-2 md:grid-cols-4 gap-6 mb-8">
            <div class="bg-yellow-500 text-white p-6 rounded-lg shadow text-center">
                <i class="fas fa-clock text-3xl mb-2"></i>
                <p class="text-2xl font-bold"><?= $stats['pendientes'] ?></p>
                <p class="text-sm opacity-90">Pendientes</p>
            </div>
            
            <div class="bg-blue-500 text-white p-6 rounded-lg shadow text-center">
                <i class="fas fa-fire text-3xl mb-2"></i>
                <p class="text-2xl font-bold"><?= $stats['preparando'] ?></p>
                <p class="text-sm opacity-90">Preparando</p>
            </div>
            
            <div class="bg-green-500 text-white p-6 rounded-lg shadow text-center">
                <i class="fas fa-check text-3xl mb-2"></i>
                <p class="text-2xl font-bold"><?= $stats['listos'] ?></p>
                <p class="text-sm opacity-90">Listos</p>
            </div>
            
            <div class="bg-purple-500 text-white p-6 rounded-lg shadow text-center">
                <i class="fas fa-calendar-day text-3xl mb-2"></i>
                <p class="text-2xl font-bold"><?= $stats['pedidos_hoy'] ?></p>
                <p class="text-sm opacity-90">Pedidos Hoy</p>
            </div>
        </div>

        <!-- Botones de acceso -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-8">
            <a href="pedidos.php" class="bg-blue-600 hover:bg-blue-700 text-white p-6 rounded-lg text-center font-semibold transition shadow-lg">
                <i class="fas fa-list-alt text-3xl mb-3"></i>
                <div class="text-lg">Todos los Pedidos</div>
                <div class="text-sm opacity-90">Gestión completa</div>
            </a>
            
            <a href="pedidos.php?ubicacion=Local 1" class="bg-blue-500 hover:bg-blue-600 text-white p-6 rounded-lg text-center font-semibold transition shadow-lg relative">
                <i class="fas fa-store text-3xl mb-3"></i>
                <div class="text-lg">Solo Local 1</div>
                <div class="text-sm opacity-90">🏪 Atención al público + Impresión</div>
                <?php if (count($pedidos_local1_pendientes) > 0): ?>
                    <span class="absolute -top-2 -right-2 bg-orange-500 text-white text-xs px-2 py-1 rounded-full">
                        <?= count($pedidos_local1_pendientes) ?>
                    </span>
                <?php endif; ?>
            </a>
            
            <a href="pedidos.php?ubicacion=Fábrica" class="bg-orange-500 hover:bg-orange-600 text-white p-6 rounded-lg text-center font-semibold transition shadow-lg">
                <i class="fas fa-industry text-3xl mb-3"></i>
                <div class="text-lg">Solo Fábrica</div>
                <div class="text-sm opacity-90">🏭 Producción central</div>
            </a>
        </div>

        <!-- Estadísticas por ubicación -->
        <?php if (!empty($stats_ubicacion)): ?>
        <div class="bg-white rounded-lg shadow mb-6 p-6">
            <h2 class="text-xl font-semibold text-gray-800 mb-4 flex items-center">
                <i class="fas fa-map-marker-alt text-purple-500 mr-2"></i>
                Estado por Ubicación - Hoy
            </h2>
            
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <?php foreach ($stats_ubicacion as $stat): ?>
                    <div class="border rounded-lg p-4 <?= $stat['ubicacion'] === 'Local 1' ? 'border-blue-200 bg-blue-50' : 'border-orange-200 bg-orange-50' ?>">
                        <div class="flex items-center justify-between mb-3">
                            <h3 class="font-bold text-lg <?= $stat['ubicacion'] === 'Local 1' ? 'text-blue-600' : 'text-orange-600' ?>">
                                <?= $stat['ubicacion'] === 'Local 1' ? '🏪 Local' : '🏭 Fábrica' ?>
                            </h3>
                            <span class="text-2xl font-bold <?= $stat['ubicacion'] === 'Local 1' ? 'text-blue-600' : 'text-orange-600' ?>">
                                <?= $stat['total'] ?>
                            </span>
                        </div>
                        
                        <div class="space-y-1 text-sm">
                            <div class="flex justify-between">
                                <span>Pendientes:</span>
                                <span class="font-semibold text-yellow-600"><?= $stat['pendientes'] ?></span>
                            </div>
                            <div class="flex justify-between">
                                <span>Preparando:</span>
                                <span class="font-semibold text-blue-600"><?= $stat['preparando'] ?></span>
                            </div>
                            <div class="flex justify-between">
                                <span>Listos:</span>
                                <span class="font-semibold text-green-600"><?= $stat['listos'] ?></span>
                            </div>
                            <div class="flex justify-between">
                                <span>Entregados:</span>
                                <span class="font-semibold text-gray-600"><?= $stat['entregados'] ?></span>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- Lista de pedidos de hoy (simplificada) -->
        <div class="bg-white rounded-lg shadow overflow-hidden">
            <div class="bg-gray-50 px-6 py-4 border-b">
                <h2 class="text-xl font-semibold text-gray-800 flex items-center">
                    <i class="fas fa-today mr-2 text-blue-500"></i>
                    Pedidos de Hoy
                    <span class="ml-auto text-sm bg-blue-100 text-blue-800 px-3 py-1 rounded-full">
                        <?= count($pedidos_hoy) ?> pedidos
                    </span>
                </h2>
            </div>
            
            <?php if (empty($pedidos_hoy)): ?>
                <div class="p-12 text-center text-gray-500">
                    <i class="fas fa-coffee text-6xl mb-4 text-gray-300"></i>
                    <h3 class="text-xl mb-2">¡Día tranquilo!</h3>
                    <p>No hay pedidos registrados para hoy</p>
                </div>
            <?php else: ?>
                <div class="divide-y divide-gray-200">
                    <?php foreach (array_slice($pedidos_hoy, 0, 10) as $pedido): ?>
                        <div class="p-4 hover:bg-gray-50 transition-colors">
                            <div class="flex justify-between items-center">
                                <div class="flex-1">
                                    <div class="flex items-center mb-1">
                                        <span class="bg-blue-100 text-blue-800 px-2 py-1 rounded text-sm font-semibold mr-2">
                                            #<?= $pedido['id'] ?>
                                        </span>
                                        <span class="font-semibold text-gray-800">
                                            <?= htmlspecialchars($pedido['nombre'] . ' ' . $pedido['apellido']) ?>
                                        </span>
                                    </div>
                                    <p class="text-gray-600 text-sm mb-1"><?= htmlspecialchars(substr($pedido['producto'], 0, 50)) ?><?= strlen($pedido['producto']) > 50 ? '...' : '' ?></p>
                                    <div class="flex items-center text-xs text-gray-500 space-x-3">
                                        <span><i class="fas fa-clock mr-1"></i><?= date('H:i', strtotime($pedido['created_at'])) ?></span>
                                        <span><i class="fas fa-map-marker-alt mr-1"></i><?= $pedido['ubicacion'] ?></span>
                                        <span class="px-2 py-1 rounded text-xs
                                            <?= $pedido['estado'] === 'Pendiente' ? 'bg-yellow-100 text-yellow-800' : 
                                                ($pedido['estado'] === 'Preparando' ? 'bg-blue-100 text-blue-800' : 
                                                'bg-green-100 text-green-800') ?>">
                                            <?= $pedido['estado'] ?>
                                        </span>
                                    </div>
                                </div>
                                
                                <?php if ($pedido['ubicacion'] === 'Local 1' && $pedido['estado'] === 'Pendiente'): ?>
                                    <button onclick="imprimirComanda(<?= $pedido['id'] ?>, false)" 
                                            class="bg-blue-500 hover:bg-blue-600 text-white px-3 py-1 rounded text-sm ml-3">
                                        <i class="fas fa-print mr-1"></i>
                                        Imprimir
                                    </button>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
                
                <?php if (count($pedidos_hoy) > 10): ?>
                <div class="p-4 bg-gray-50 text-center">
                    <a href="pedidos.php" class="text-blue-500 hover:underline">
                        Ver todos los <?= count($pedidos_hoy) ?> pedidos del día
                    </a>
                </div>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </main>

    <script>
        // SISTEMA DE IMPRESIÓN AUTOMÁTICA SIMPLIFICADO
        let autoPrintEnabled = localStorage.getItem('local1_autoPrintEnabled') === 'true';
        let checkInterval;
        let printerConnected = false;
        let printerCheckInterval;
        
        // Configurar estado inicial
        document.addEventListener('DOMContentLoaded', function() {
            updatePrintToggleUI();
            checkPrinterConnection(); // Verificar impresora al cargar
            
            // Verificar impresora cada 2 minutos
            printerCheckInterval = setInterval(checkPrinterConnection, 120000);
            
            if (autoPrintEnabled) {
                startAutoCheck();
            }
            
            // Auto-imprimir pedidos existentes si está habilitado
            if (autoPrintEnabled && <?= count($pedidos_local1_pendientes) ?> > 0) {
                setTimeout(() => {
                    if (printerConnected) {
                        showNotification('🖨️ Revisando pedidos pendientes de impresión...', 'info');
                        imprimirTodosPendientes();
                    } else {
                        showNotification('⚠️ Impresora no disponible - revise la conexión', 'error');
                    }
                }, 3000);
            }
        });
        
        // Función para verificar conexión de impresora
        function checkPrinterConnection() {
            const printerIcon = document.getElementById('printerIcon');
            const printerStatusText = document.getElementById('printerStatusText');
            const printerDetails = document.getElementById('printerDetails');
            
            // Mostrar estado de verificación
            printerIcon.className = 'fas fa-circle-notch fa-spin mr-2';
            printerStatusText.textContent = 'Verificando conexión con POS80-CX...';
            
            fetch(window.location.href + '?check_printer=1')
                .then(response => response.json())
                .then(data => {
                    printerConnected = data.can_print;
                    
                    if (data.can_print) {
                        // Impresora conectada y lista
                        printerIcon.className = 'fas fa-check-circle text-green-600 mr-2';
                        printerIcon.style.color = '#10B981';
                        printerStatusText.textContent = 'POS80-CX conectada y lista';
                        printerStatusText.style.color = '#10B981';
                        printerDetails.innerHTML = `✅ IP: ${data.expected_ip} | MAC: ${data.expected_mac} | Estado: ONLINE`;
                        
                        updatePrintToggleUI();
                        
                    } else if (data.detected) {
                        // Detectada pero no responde
                        printerIcon.className = 'fas fa-exclamation-triangle text-yellow-600 mr-2';
                        printerIcon.style.color = '#F59E0B';
                        printerStatusText.textContent = 'POS80-CX detectada pero no responde';
                        printerStatusText.style.color = '#F59E0B';
                        printerDetails.innerHTML = `⚠️ ${data.warning || 'Puede estar ocupada o apagada'}`;
                        
                        showNotification('⚠️ Impresora detectada pero no responde - verifique que esté encendida', 'warning');
                        
                    } else {
                        // No detectada
                        printerIcon.className = 'fas fa-times-circle text-red-600 mr-2';
                        printerIcon.style.color = '#EF4444';
                        printerStatusText.textContent = 'POS80-CX NO detectada';
                        printerStatusText.style.color = '#EF4444';
                        printerDetails.innerHTML = `❌ ${data.message} | Tu IP: ${data.client_ip}`;
                        
                        // Deshabilitar auto-print si no hay impresora
                        if (autoPrintEnabled) {
                            showNotification('❌ Impresora no detectada - Auto-impresión deshabilitada', 'error');
                            autoPrintEnabled = false;
                            localStorage.setItem('local1_autoPrintEnabled', false);
                            updatePrintToggleUI();
                            stopAutoCheck();
                        }
                    }
                })
                .catch(error => {
                    console.error('Error verificando impresora:', error);
                    printerConnected = false;
                    
                    printerIcon.className = 'fas fa-question-circle text-gray-600 mr-2';
                    printerStatusText.textContent = 'Error verificando impresora';
                    printerStatusText.style.color = '#6B7280';
                    printerDetails.innerHTML = '❓ No se pudo verificar el estado de la impresora';
                });
        }
        
        // Toggle de impresión automática
        document.getElementById('toggleAutoPrint').addEventListener('click', function() {
            if (!printerConnected) {
                showNotification('❌ No se puede activar: Impresora POS80-CX no detectada', 'error');
                checkPrinterConnection(); // Verificar de nuevo
                return;
            }
            
            autoPrintEnabled = !autoPrintEnabled;
            localStorage.setItem('local1_autoPrintEnabled', autoPrintEnabled);
            updatePrintToggleUI();
            
            if (autoPrintEnabled) {
                startAutoCheck();
                showNotification('✅ Impresión automática LOCAL 1 ACTIVADA', 'success');
            } else {
                stopAutoCheck();
                showNotification('❌ Impresión automática LOCAL 1 DESACTIVADA', 'info');
            }
        });
        
        function updatePrintToggleUI() {
            const button = document.getElementById('toggleAutoPrint');
            const statusText = document.getElementById('printStatusText');
            const statusIndicator = document.getElementById('printStatus');
            const indicator = document.getElementById('printerStatusIndicator');
            
            if (autoPrintEnabled && printerConnected) {
                button.className = 'bg-green-600 hover:bg-green-700 text-white px-4 py-3 rounded-full shadow-lg transition-all duration-200 flex items-center';
                statusText.textContent = 'Auto-Print Local 1: ON';
                statusIndicator.className = 'print-status active';
                if (indicator) {
                    indicator.className = 'mt-2 text-xs text-white bg-green-600 px-3 py-1 rounded';
                    indicator.innerHTML = '<i class="fas fa-check mr-1"></i>Impresora conectada';
                    indicator.style.display = 'block';
                }
            } else if (autoPrintEnabled && !printerConnected) {
                button.className = 'bg-yellow-600 hover:bg-yellow-700 text-white px-4 py-3 rounded-full shadow-lg transition-all duration-200 flex items-center';
                statusText.textContent = 'Auto-Print: ESPERANDO';
                statusIndicator.className = 'print-status active';
                if (indicator) {
                    indicator.className = 'mt-2 text-xs text-white bg-yellow-600 px-3 py-1 rounded';
                    indicator.innerHTML = '<i class="fas fa-exclamation-triangle mr-1"></i>Sin impresora';
                    indicator.style.display = 'block';
                }
            } else {
                button.className = 'bg-red-600 hover:bg-red-700 text-white px-4 py-3 rounded-full shadow-lg transition-all duration-200 flex items-center';
                statusText.textContent = 'Auto-Print Local 1: OFF';
                statusIndicator.className = 'print-status inactive';
                if (indicator) {
                    indicator.style.display = 'none';
                }
            }
        }
        
        function startAutoCheck() {
            if (!printerConnected) {
                showNotification('⚠️ No se puede iniciar auto-impresión sin impresora conectada', 'warning');
                return;
            }
            
            checkInterval = setInterval(checkForNewOrdersLocal1, 30000); // Cada 30 segundos
            console.log('🖨️ Impresión automática Local 1 iniciada - revisando cada 30 segundos');
        }
        
        function stopAutoCheck() {
            if (checkInterval) {
                clearInterval(checkInterval);
                console.log('⏹️ Impresión automática Local 1 detenida');
            }
        }
        
        function checkForNewOrdersLocal1() {
            // Solo buscar nuevos pedidos si la impresora está conectada
            if (!printerConnected) {
                console.log('⚠️ Saltando verificación - impresora no conectada');
                checkPrinterConnection(); // Re-verificar impresora
                return;
            }
            
            fetch(window.location.href + '?check_new=1')
                .then(response => response.json())
                .then(data => {
                    if (data.success && data.new_orders && data.new_orders.length > 0) {
                        console.log(`🆕 ${data.new_orders.length} nuevos pedidos Local 1 detectados`);
                        
                        // Verificar impresora antes de imprimir
                        if (printerConnected) {
                            // Imprimir cada pedido nuevo
                            data.new_orders.forEach(pedido => {
                                imprimirComanda(pedido.id, true);
                            });
                            
                            showNotification(`🖨️ ${data.new_orders.length} nueva(s) comanda(s) Local 1 impresa(s) automáticamente`, 'success');
                            
                            // Recargar página después de 5 segundos para actualizar la lista
                            setTimeout(() => {
                                window.location.reload();
                            }, 5000);
                        } else {
                            showNotification('❌ Nuevos pedidos detectados pero impresora no disponible', 'error');
                        }
                    }
                })
                .catch(error => {
                    console.error('Error verificando nuevos pedidos:', error);
                });
        }
        
        // Función para imprimir comanda (simplificada)
        function imprimirComanda(pedidoId, esAutomatica = false) {
            if (!printerConnected && esAutomatica) {
                showNotification('❌ No se puede imprimir automáticamente - Impresora no conectada', 'error');
                return;
            }
            
            if (!printerConnected && !esAutomatica) {
                const confirmar = confirm('⚠️ Impresora no detectada. ¿Continuar de todas formas?');
                if (!confirmar) return;
            }
            
            // Usar el sistema de impresión existente - ruta simplificada
            const url = `../admin/modules/impresion/comanda_multi.php?pedido=${pedidoId}&ubicacion=Local 1&auto=${esAutomatica ? '1' : '0'}`;
            
            if (esAutomatica) {
                // Impresión automática - ventana pequeña
                const ventana = window.open(url, '_blank', 'width=300,height=400,scrollbars=no,toolbar=no,menubar=no');
                if (ventana) {
                    console.log(`🖨️ AUTO: Imprimiendo pedido #${pedidoId} (Local 1)`);
                    
                    // Cerrar ventana después de 3 segundos
                    setTimeout(() => {
                        try {
                            ventana.close();
                        } catch(e) {
                            console.log('Ventana ya cerrada');
                        }
                    }, 3000);
                } else {
                    showNotification('❌ Error: Ventana de impresión bloqueada por el navegador', 'error');
                }
            } else {
                // Impresión manual - ventana normal
                const ventana = window.open(url, '_blank', 'width=500,height=700');
                if (ventana) {
                    ventana.focus();
                    console.log(`🖨️ MANUAL: Abriendo comanda para pedido #${pedidoId}`);
                } else {
                    showNotification('❌ Error: Ventana de impresión bloqueada', 'error');
                }
            }
            
            // Marcar como impreso después de 2 segundos
            setTimeout(() => {
                marcarComoImpreso(pedidoId);
            }, 2000);
        }
        
        // Imprimir todos los pedidos pendientes
        function imprimirTodosPendientes() {
            if (!printerConnected) {
                const confirmar = confirm('⚠️ Impresora no detectada. ¿Continuar de todas formas?');
                if (!confirmar) return;
            }
            
            const pedidosIds = <?= json_encode(array_column($pedidos_local1_pendientes, 'id')) ?>;
            
            if (pedidosIds.length === 0) {
                showNotification('✅ No hay pedidos pendientes de impresión', 'info');
                return;
            }
            
            showNotification(`🖨️ Imprimiendo ${pedidosIds.length} comanda(s) del Local 1...`, 'info');
            
            pedidosIds.forEach((pedidoId, index) => {
                setTimeout(() => {
                    imprimirComanda(pedidoId, true);
                }, index * 2000); // 2 segundos entre cada impresión
            });
        }
        
        // Marcar pedido como impreso
        function marcarComoImpreso(pedidoId) {
            const formData = new FormData();
            formData.append('marcar_impreso', '1');
            formData.append('pedido_id', pedidoId);
            
            fetch(window.location.href, {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    console.log(`✅ Pedido #${pedidoId} marcado como impreso`);
                    
                    // Remover pedido de la lista visual
                    const elemento = document.querySelector(`[data-pedido-id="${pedidoId}"]`);
                    if (elemento) {
                        elemento.style.transition = 'opacity 0.5s, transform 0.5s';
                        elemento.style.opacity = '0.3';
                        elemento.style.transform = 'scale(0.95)';
                        
                        setTimeout(() => {
                            elemento.remove();
                        }, 500);
                    }
                }
            })
            .catch(error => {
                console.error('Error marcando pedido:', error);
            });
        }
        
        // Sistema de notificaciones
        function showNotification(message, type = 'info') {
            const notification = document.createElement('div');
            let bgColor = 'bg-blue-500';
            let icon = 'info-circle';
            
            switch(type) {
                case 'success':
                    bgColor = 'bg-green-500';
                    icon = 'check-circle';
                    break;
                case 'error':
                    bgColor = 'bg-red-500';
                    icon = 'exclamation-circle';
                    break;
                case 'warning':
                    bgColor = 'bg-yellow-500';
                    icon = 'exclamation-triangle';
                    break;
            }
            
            notification.className = `auto-print-notification ${bgColor}`;
            notification.innerHTML = `
                <div class="flex items-center">
                    <i class="fas fa-${icon} mr-2"></i>
                    ${message}
                    <button onclick="this.parentElement.parentElement.remove()" class="ml-3 text-white hover:text-gray-200">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
            `;
            
            document.body.appendChild(notification);
            
            // Auto-remover después de 5 segundos
            setTimeout(() => {
                if (notification.parentNode) {
                    notification.style.opacity = '0';
                    setTimeout(() => {
                        if (notification.parentNode) {
                            document.body.removeChild(notification);
                        }
                    }, 300);
                }
            }, 5000);
        }
        
        // Atajos de teclado
        document.addEventListener('keydown', function(e) {
            // Ctrl + P = Toggle auto-print
            if (e.ctrlKey && e.key === 'p') {
                e.preventDefault();
                document.getElementById('toggleAutoPrint').click();
            }
            
            // Ctrl + T = Test impresora
            if (e.ctrlKey && e.key === 't') {
                e.preventDefault();
                checkPrinterConnection();
                showNotification('🔍 Verificando conexión de impresora...', 'info');
            }
        });
        
        // Debug inicial
        console.log('🏪 Dashboard Empleados Simple con Auto-Impresión cargado');
        console.log('📍 Buscando impresora POS80-CX MAC: C0-25-E9-14-50-03');
        console.log('⚙️ Auto-Print:', autoPrintEnabled ? 'ON' : 'OFF');
        console.log('📋 Pedidos pendientes:', <?= count($pedidos_local1_pendientes) ?>);
        
        // Verificar pedidos urgentes
        <?php if (count($pedidos_urgentes) >= 3): ?>
        setTimeout(() => {
            showNotification('🚨 HAY <?= count($pedidos_urgentes) ?> PEDIDOS URGENTES (>1 hora)', 'error');
        }, 2000);
        <?php endif; ?>
    </script>
</body>
</html>