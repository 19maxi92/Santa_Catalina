
                <?php
require_once '../admin/config.php';
session_start();

// Verificar acceso de empleado
if (!isset($_SESSION['empleado_logged']) || $_SESSION['empleado_logged'] !== true) {
    header('Location: login.php');
    exit;
}
// Verificar que es una estación autorizada
function esEstacionLocal1Autorizada() {
    return (
        isset($_COOKIE['ESTACION_LOCAL1']) && $_COOKIE['ESTACION_LOCAL1'] === 'true' &&
        isset($_SESSION['estacion_tipo']) && $_SESSION['estacion_tipo'] === 'LOCAL1' &&
        isset($_SESSION['ubicacion_asignada']) && $_SESSION['ubicacion_asignada'] === 'Local 1'
    );
}

if (!esEstacionLocal1Autorizada()) {
    session_destroy();
    header('Location: login.php');
    exit;
}

$pdo = getConnection();

// Procesar cambios de estado
$mensaje = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion'])) {
    try {
        switch ($_POST['accion']) {
            case 'cambiar_estado':
                $pedido_id = (int)$_POST['pedido_id'];
                $nuevo_estado = $_POST['nuevo_estado'];
                
                // Verificar que el pedido pertenece a Local 1
                $verify_stmt = $pdo->prepare("SELECT ubicacion FROM pedidos WHERE id = ?");
                $verify_stmt->execute([$pedido_id]);
                $pedido_ubicacion = $verify_stmt->fetchColumn();
                
                if ($pedido_ubicacion === 'Local 1') {
                    $stmt = $pdo->prepare("UPDATE pedidos SET estado = ? WHERE id = ?");
                    $stmt->execute([$nuevo_estado, $pedido_id]);
                    $mensaje = "Estado actualizado a: $nuevo_estado";
                    
                    // Log del cambio
                    error_log("LOCAL1: Estado pedido #$pedido_id cambiado a $nuevo_estado por usuario #{$_SESSION['empleado_id']}");
                } else {
                    $error = "No tiene permisos para modificar este pedido";
                }
                break;
                
            case 'marcar_entregado':
                $pedido_id = (int)$_POST['pedido_id'];
                
                $verify_stmt = $pdo->prepare("SELECT ubicacion FROM pedidos WHERE id = ?");
                $verify_stmt->execute([$pedido_id]);
                $pedido_ubicacion = $verify_stmt->fetchColumn();
                
                if ($pedido_ubicacion === 'Local 1') {
                    $stmt = $pdo->prepare("UPDATE pedidos SET estado = 'Entregado' WHERE id = ?");
                    $stmt->execute([$pedido_id]);
                    $mensaje = "Pedido marcado como entregado";
                    
                    error_log("LOCAL1: Pedido #$pedido_id entregado por usuario #{$_SESSION['empleado_id']}");
                } else {
                    $error = "No tiene permisos para modificar este pedido";
                }
                break;
        }
    } catch (Exception $e) {
        $error = "Error: " . $e->getMessage();
    }
}

// Obtener pedidos activos de Local 1
try {
    $stmt = $pdo->prepare("
        SELECT p.*, 
               TIMESTAMPDIFF(MINUTE, p.created_at, NOW()) as minutos_transcurridos,
               CASE 
                   WHEN TIMESTAMPDIFF(MINUTE, p.created_at, NOW()) > 120 THEN 'urgente'
                   WHEN TIMESTAMPDIFF(MINUTE, p.created_at, NOW()) > 60 THEN 'atencion'
                   ELSE 'normal'
               END as prioridad
        FROM pedidos p
        WHERE p.ubicacion = 'Local 1' 
          AND DATE(p.created_at) = CURDATE()
          AND p.estado != 'Entregado'
        ORDER BY 
            CASE p.estado 
                WHEN 'Pendiente' THEN 1 
                WHEN 'Preparando' THEN 2 
                WHEN 'Listo' THEN 3 
            END, 
            p.created_at ASC
    ");
    $stmt->execute();
    $pedidos_activos = $stmt->fetchAll();
    
    // Estadísticas del día
    $stats_stmt = $pdo->prepare("
        SELECT 
            COUNT(*) as total_dia,
            SUM(CASE WHEN estado = 'Pendiente' THEN 1 ELSE 0 END) as pendientes,
            SUM(CASE WHEN estado = 'Preparando' THEN 1 ELSE 0 END) as preparando,
            SUM(CASE WHEN estado = 'Listo' THEN 1 ELSE 0 END) as listos,
            SUM(CASE WHEN estado = 'Entregado' THEN 1 ELSE 0 END) as entregados,
            COALESCE(SUM(CASE WHEN estado = 'Entregado' THEN precio ELSE 0 END), 0) as total_ventas,
            COALESCE(AVG(CASE WHEN estado = 'Entregado' THEN TIMESTAMPDIFF(MINUTE, created_at, updated_at) END), 0) as tiempo_promedio
        FROM pedidos 
        WHERE ubicacion = 'Local 1' 
          AND DATE(created_at) = CURDATE()
    ");
    $stats_stmt->execute();
    $stats = $stats_stmt->fetch();
    
    // Últimos entregados
    $entregados_stmt = $pdo->prepare("
        SELECT nombre, apellido, producto, precio, updated_at
        FROM pedidos 
        WHERE ubicacion = 'Local 1' 
          AND estado = 'Entregado'
          AND DATE(created_at) = CURDATE()
        ORDER BY updated_at DESC 
        LIMIT 5
    ");
    $entregados_stmt->execute();
    $ultimos_entregados = $entregados_stmt->fetchAll();
    
} catch (Exception $e) {
    error_log("ERROR DASHBOARD LOCAL1: " . $e->getMessage());
    $pedidos_activos = [];
    $stats = ['total_dia' => 0, 'pendientes' => 0, 'preparando' => 0, 'listos' => 0, 'entregados' => 0, 'total_ventas' => 0, 'tiempo_promedio' => 0];
    $ultimos_entregados = [];
}

// Función para formatear precios
function formatPrice($price) {
    return '$' . number_format($price, 0, ',', '.');
}

// Función para obtener clase CSS según prioridad
function getPrioridadClass($prioridad, $estado) {
    if ($prioridad === 'urgente') return 'border-l-red-500 bg-red-50';
    if ($prioridad === 'atencion') return 'border-l-orange-500 bg-orange-50';
    
    switch ($estado) {
        case 'Pendiente': return 'border-l-yellow-500 bg-yellow-50';
        case 'Preparando': return 'border-l-blue-500 bg-blue-50';
        case 'Listo': return 'border-l-green-500 bg-green-50';
        default: return 'border-l-gray-500 bg-gray-50';
    }
}

// Estado de auto-impresión
$auto_impresion_activa = isset($_SESSION['auto_impresion']) && $_SESSION['auto_impresion'];
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>🏪 Local 1 - Dashboard</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <meta http-equiv="refresh" content="15"> <!-- Auto-refresh cada 15 segundos -->
    <style>
        .pulse-dot { animation: pulse 2s infinite; }
        .local1-gradient { background: linear-gradient(135deg, #3B82F6 0%, #1D4ED8 100%); }
        @keyframes pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.5; }
        }
        .priority-urgent { border-left: 4px solid #EF4444; background-color: #FEF2F2; }
        .priority-attention { border-left: 4px solid #F59E0B; background-color: #FFFBEB; }
        .priority-normal { border-left: 4px solid #10B981; background-color: #F0FDF4; }
        
        /* Estilos del modal de pedidos express */
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
<body class="bg-gray-100 min-h-screen">
    
    <!-- Header fijo -->
    <header class="local1-gradient text-white shadow-lg sticky top-0 z-40">
        <div class="container mx-auto px-4 py-3">
            <div class="flex justify-between items-center">
                <div class="flex items-center">
                    <i class="fas fa-store text-2xl mr-3"></i>
                    <div>
                        <h1 class="text-xl font-bold">🏪 Local 1 - Dashboard</h1>
                        <p class="text-sm text-blue-200">
                            <?= htmlspecialchars($_SESSION['empleado_name'] ?? 'Empleado') ?> | 
                            Auto-impresión: 
                            <?php if ($auto_impresion_activa): ?>
                                <span class="text-green-300"><i class="fas fa-print pulse-dot mr-1"></i>ACTIVA</span>
                            <?php else: ?>
                                <span class="text-red-300"><i class="fas fa-print-slash mr-1"></i>INACTIVA</span>
                            <?php endif; ?>
                        </p>
                    </div>
                </div>
                
                <div class="flex items-center space-x-4">
                    <!-- Indicador de conexión -->
                    <div class="flex items-center text-sm">
                        <div class="w-2 h-2 bg-green-400 rounded-full pulse-dot mr-2"></div>
                        <span>En línea</span>
                    </div>
                    
                    <!-- Hora actual -->
                    <div class="text-sm">
                        <div id="hora-actual" class="font-mono"></div>
                    </div>
                    
                    <!-- Links útiles -->
                    <a href="dashboard.php" class="bg-blue-500 hover:bg-blue-400 px-3 py-2 rounded text-sm">
                        <i class="fas fa-th-large mr-1"></i>Vista Normal
                    </a>
                    
                    <!-- Logout -->
                    <a href="logout.php" class="bg-red-500 hover:bg-red-600 px-3 py-2 rounded text-sm">
                        <i class="fas fa-sign-out-alt mr-1"></i>Salir
                    </a>
                </div>
            </div>
        </div>
    </header>

    <main class="container mx-auto px-4 py-6">
        
        <!-- Mensajes -->
        <?php if ($mensaje): ?>
        <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4 flex items-center">
            <i class="fas fa-check-circle mr-2"></i>
            <span><?= htmlspecialchars($mensaje) ?></span>
        </div>
        <?php endif; ?>

        <?php if ($error): ?>
        <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4 flex items-center">
            <i class="fas fa-exclamation-circle mr-2"></i>
            <span><?= htmlspecialchars($error) ?></span>
        </div>
        <?php endif; ?>

        <!-- Estadísticas rápidas del día -->
        <div class="grid grid-cols-2 md:grid-cols-5 gap-4 mb-6">
            <div class="bg-yellow-500 text-white p-4 rounded-lg text-center shadow">
                <i class="fas fa-clock text-2xl mb-1"></i>
                <p class="text-xl font-bold"><?= $stats['pendientes'] ?></p>
                <p class="text-sm">Pendientes</p>
            </div>
            
            <div class="bg-blue-500 text-white p-4 rounded-lg text-center shadow">
                <i class="fas fa-fire text-2xl mb-1"></i>
                <p class="text-xl font-bold"><?= $stats['preparando'] ?></p>
                <p class="text-sm">Preparando</p>
            </div>
            
            <div class="bg-green-500 text-white p-4 rounded-lg text-center shadow">
                <i class="fas fa-check text-2xl mb-1"></i>
                <p class="text-xl font-bold"><?= $stats['listos'] ?></p>
                <p class="text-sm">Listos</p>
            </div>
            
            <div class="bg-purple-500 text-white p-4 rounded-lg text-center shadow">
                <i class="fas fa-handshake text-2xl mb-1"></i>
                <p class="text-xl font-bold"><?= $stats['entregados'] ?></p>
                <p class="text-sm">Entregados</p>
            </div>
            
            <div class="bg-indigo-500 text-white p-4 rounded-lg text-center shadow">
                <i class="fas fa-dollar-sign text-2xl mb-1"></i>
                <p class="text-lg font-bold"><?= formatPrice($stats['total_ventas']) ?></p>
                <p class="text-sm">Ventas Hoy</p>
            </div>
        </div>

        <!-- Pedidos activos -->
        <?php if (empty($pedidos_activos)): ?>
            <div class="bg-white rounded-lg shadow-lg p-12 text-center">
                <i class="fas fa-coffee text-6xl text-gray-300 mb-4"></i>
                <h2 class="text-2xl font-bold text-gray-600 mb-2">¡Todo al día en Local 1!</h2>
                <p class="text-gray-500">No hay pedidos pendientes</p>
                <div class="mt-4 text-sm text-gray-400">
                    <i class="fas fa-sync-alt fa-spin mr-2"></i>
                    Actualizando cada 15 segundos
                </div>
                <div class="mt-6">
                    <a href="dashboard.php" class="bg-blue-500 hover:bg-blue-600 text-white px-6 py-3 rounded-lg">
                        <i class="fas fa-th-large mr-2"></i>Ver Dashboard General
                    </a>
                </div>
            </div>
        <?php else: ?>
            <!-- Título con contador -->
            <div class="flex justify-between items-center mb-4">
                <h2 class="text-2xl font-bold text-gray-800 flex items-center">
                    <i class="fas fa-list-ul mr-3 text-blue-500"></i>
                    Pedidos Activos - Local 1
                    <span class="ml-3 bg-blue-100 text-blue-800 text-lg px-3 py-1 rounded-full">
                        <?= count($pedidos_activos) ?>
                    </span>
                </h2>
                <div class="text-sm text-gray-500 flex items-center">
                    <i class="fas fa-sync-alt fa-spin mr-2 text-blue-500"></i>
                    Actualizando automáticamente
                </div>
            </div>

            <!-- Lista de pedidos -->
            <div class="space-y-4">
                <?php foreach ($pedidos_activos as $pedido): ?>
                    <div class="bg-white rounded-lg shadow-md border-l-4 <?= getPrioridadClass($pedido['prioridad'], $pedido['estado']) ?>">
                        <div class="p-6">
                            <div class="flex justify-between items-start mb-4">
                                <div class="flex-1">
                                    <!-- Header del pedido -->
                                    <div class="flex items-center justify-between mb-2">
                                        <h3 class="text-lg font-bold text-gray-800">
                                            Pedido #<?= $pedido['id'] ?> - <?= htmlspecialchars($pedido['nombre'] . ' ' . $pedido['apellido']) ?>
                                        </h3>
                                        <div class="flex items-center space-x-2">
                                            <?php if ($pedido['prioridad'] === 'urgente'): ?>
                                                <span class="bg-red-500 text-white text-xs px-2 py-1 rounded-full font-bold">
                                                    🔥 URGENTE
                                                </span>
                                            <?php elseif ($pedido['prioridad'] === 'atencion'): ?>
                                                <span class="bg-orange-500 text-white text-xs px-2 py-1 rounded-full font-bold">
                                                    ⚠️ ATENCIÓN
                                                </span>
                                            <?php endif; ?>
                                            <span class="text-sm text-gray-500">
                                                <?= $pedido['minutos_transcurridos'] ?>min
                                            </span>
                                        </div>
                                    </div>

                                    <!-- Datos del cliente -->
                                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                                        <div>
                                            <p class="text-sm text-gray-600">Cliente:</p>
                                            <p class="font-semibold text-gray-800">
                                                <i class="fas fa-user mr-2 text-blue-500"></i>
                                                <?= htmlspecialchars($pedido['nombre'] . ' ' . $pedido['apellido']) ?>
                                            </p>
                                            <p class="text-sm text-gray-600 mt-1">
                                                <i class="fas fa-phone mr-2 text-green-500"></i>
                                                <?= htmlspecialchars($pedido['telefono']) ?>
                                            </p>
                                        </div>
                                        
                                        <div>
                                            <p class="text-sm text-gray-600">Modalidad:</p>
                                            <p class="font-semibold text-gray-800">
                                                <?php if ($pedido['modalidad'] === 'Delivery'): ?>
                                                    <i class="fas fa-truck mr-2 text-orange-500"></i>Delivery
                                                <?php else: ?>
                                                    <i class="fas fa-store mr-2 text-blue-500"></i>Retira en local
                                                <?php endif; ?>
                                            </p>
                                            <?php if ($pedido['modalidad'] === 'Delivery' && $pedido['direccion']): ?>
                                                <p class="text-sm text-gray-600 mt-1">
                                                    <i class="fas fa-map-marker-alt mr-2 text-red-500"></i>
                                                    <?= htmlspecialchars($pedido['direccion']) ?>
                                                </p>
                                            <?php endif; ?>
                                        </div>
                                    </div>

                                    <!-- Producto y precio -->
                                    <div class="bg-gray-50 rounded-lg p-4 mb-4">
                                        <div class="flex justify-between items-center">
                                            <div class="flex-1">
                                                <h4 class="font-bold text-gray-800 text-lg">
                                                    <?= htmlspecialchars($pedido['producto']) ?>
                                                </h4>
                                                <p class="text-sm text-gray-600">
                                                    Cantidad: <?= $pedido['cantidad'] ?> | 
                                                    Pago: <?= htmlspecialchars($pedido['forma_pago']) ?>
                                                </p>
                                            </div>
                                            <div class="text-right">
                                                <p class="text-2xl font-bold text-green-600">
                                                    <?= formatPrice($pedido['precio']) ?>
                                                </p>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Observaciones -->
                                    <?php if ($pedido['observaciones']): ?>
                                        <div class="bg-blue-50 rounded-lg p-3 mb-4">
                                            <h5 class="font-semibold text-blue-900 mb-1">
                                                <i class="fas fa-sticky-note mr-2"></i>Observaciones:
                                            </h5>
                                            <p class="text-blue-800 text-sm"><?= htmlspecialchars($pedido['observaciones']) ?></p>
                                        </div>
                                    <?php endif; ?>

                                    <!-- Botones de acciones -->
                                    <div class="flex justify-between items-center mt-4">
                                        <div class="flex space-x-2">
                                            <?php if ($pedido['estado'] === 'Pendiente'): ?>
                                                <form method="POST" class="inline">
                                                    <input type="hidden" name="accion" value="cambiar_estado">
                                                    <input type="hidden" name="pedido_id" value="<?= $pedido['id'] ?>">
                                                    <input type="hidden" name="nuevo_estado" value="Preparando">
                                                    <button type="submit" class="bg-blue-500 hover:bg-blue-600 text-white px-4 py-2 rounded-lg text-sm">
                                                        <i class="fas fa-fire mr-2"></i>Iniciar Preparación
                                                    </button>
                                                </form>
                                            <?php elseif ($pedido['estado'] === 'Preparando'): ?>
                                                <form method="POST" class="inline">
                                                    <input type="hidden" name="accion" value="cambiar_estado">
                                                    <input type="hidden" name="pedido_id" value="<?= $pedido['id'] ?>">
                                                    <input type="hidden" name="nuevo_estado" value="Listo">
                                                    <button type="submit" class="bg-green-500 hover:bg-green-600 text-white px-4 py-2 rounded-lg text-sm">
                                                        <i class="fas fa-check mr-2"></i>Marcar Listo
                                                    </button>
                                                </form>
                                            <?php elseif ($pedido['estado'] === 'Listo'): ?>
                                                <form method="POST" class="inline">
                                                    <input type="hidden" name="accion" value="marcar_entregado">
                                                    <input type="hidden" name="pedido_id" value="<?= $pedido['id'] ?>">
                                                    <button type="submit" class="bg-purple-500 hover:bg-purple-600 text-white px-4 py-2 rounded-lg text-sm">
                                                        <i class="fas fa-handshake mr-2"></i>Entregar
                                                    </button>
                                                </form>
                                            <?php endif; ?>

                                            <!-- Botón imprimir comanda -->
                                            <a href="../admin/modules/impresion/comanda.php?pedido=<?= $pedido['id'] ?>&auto=1" 
                                               target="_blank" 
                                               class="bg-gray-500 hover:bg-gray-600 text-white px-4 py-2 rounded-lg text-sm">
                                                <i class="fas fa-print mr-2"></i>Imprimir
                                            </a>
                                        </div>

                                        <!-- Estado actual -->
                                        <div class="text-right">
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
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <!-- Resumen de últimos entregados -->
        <?php if (!empty($ultimos_entregados)): ?>
        <div class="mt-8 bg-white rounded-lg shadow-lg p-6">
            <h3 class="text-lg font-bold text-gray-800 mb-4">
                <i class="fas fa-history mr-2 text-green-500"></i>
                Últimos Entregados Hoy
            </h3>
            <div class="space-y-2">
                <?php foreach ($ultimos_entregados as $entregado): ?>
                    <div class="flex justify-between items-center p-2 bg-green-50 rounded border-l-4 border-l-green-500">
                        <div>
                            <span class="font-semibold"><?= htmlspecialchars($entregado['nombre'] . ' ' . $entregado['apellido']) ?></span>
                            <span class="text-gray-600 ml-2"><?= htmlspecialchars($entregado['producto']) ?></span>
                        </div>
                        <div class="text-right">
                            <div class="font-bold text-green-600"><?= formatPrice($entregado['precio']) ?></div>
                            <div class="text-xs text-gray-500"><?= date('H:i', strtotime($entregado['updated_at'])) ?></div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
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
                
                <!-- Datos del Cliente (simplificado) -->
                <div class="bg-blue-50 rounded-lg p-4 mb-6">
                    <h3 class="font-bold text-blue-900 mb-3">
                        <i class="fas fa-user mr-2"></i>Datos del Cliente
                    </h3>
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Nombre *</label>
                            <input type="text" id="nombre" required class="w-full p-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Apellido *</label>
                            <input type="text" id="apellido" required class="w-full p-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Teléfono</label>
                            <input type="tel" id="telefono" class="w-full p-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                        </div>
                    </div>
                    
                    <!-- Modalidad -->
                    <div class="mt-4">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Modalidad *</label>
                        <div class="flex gap-4">
                            <label class="flex items-center cursor-pointer bg-white p-3 rounded-lg border border-gray-300 hover:border-blue-500">
                                <input type="radio" name="modalidad" value="Retiro" checked class="mr-2">
                                <i class="fas fa-store text-blue-500 mr-2"></i>
                                Retira en Local
                            </label>
                            <label class="flex items-center cursor-pointer bg-white p-3 rounded-lg border border-gray-300 hover:border-blue-500">
                                <input type="radio" name="modalidad" value="Delivery" class="mr-2">
                                <i class="fas fa-truck text-orange-500 mr-2"></i>
                                Delivery
                            </label>
                        </div>
                    </div>
                </div>

                <!-- PEDIDOS COMUNES -->
                <div class="bg-yellow-50 rounded-lg p-4 mb-6">
                    <h3 class="font-bold text-yellow-900 mb-4">
                        <i class="fas fa-star mr-2"></i>Pedidos Comunes
                    </h3>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        
                        <!-- J y Q x24 -->
                        <div class="bg-white rounded-lg border border-yellow-200 p-4 hover:border-yellow-400 cursor-pointer" onclick="seleccionarComun('jyq24')">
                            <div class="flex items-center justify-between">
                                <div>
                                    <h4 class="font-semibold text-gray-800">Jamón y Queso x24</h4>
                                    <p class="text-sm text-gray-600">3 planchas - Clásico</p>
                                    <p class="font-bold text-green-600 text-lg">$18.000</p>
                                </div>
                                <div class="text-yellow-500">
                                    <i class="fas fa-bread-slice text-3xl"></i>
                                </div>
                            </div>
                            <input type="radio" name="pedido_tipo" value="jyq24" class="hidden">
                        </div>

                        <!-- J y Q x48 -->
                        <div class="bg-white rounded-lg border border-yellow-200 p-4 hover:border-yellow-400 cursor-pointer" onclick="seleccionarComun('jyq48')">
                            <div class="flex items-center justify-between">
                                <div>
                                    <h4 class="font-semibold text-gray-800">Jamón y Queso x48</h4>
                                    <p class="text-sm text-gray-600">6 planchas - Clásico</p>
                                    <p class="font-bold text-green-600 text-lg">$22.000</p>
                                </div>
                                <div class="text-yellow-500">
                                    <i class="fas fa-hamburger text-3xl"></i>
                                </div>
                            </div>
                            <input type="radio" name="pedido_tipo" value="jyq48" class="hidden">
                        </div>

                        <!-- Surtido Clásico x48 -->
                        <div class="bg-white rounded-lg border border-yellow-200 p-4 hover:border-yellow-400 cursor-pointer" onclick="seleccionarComun('surtido_clasico48')">
                            <div class="flex items-center justify-between">
                                <div>
                                    <h4 class="font-semibold text-gray-800">Surtido Clásico x48</h4>
                                    <p class="text-sm text-gray-600">6 planchas - J y Q, Lechuga, Tomate, Huevo</p>
                                    <p class="font-bold text-green-600 text-lg">$20.000</p>
                                </div>
                                <div class="text-orange-500">
                                    <i class="fas fa-layer-group text-3xl"></i>
                                </div>
                            </div>
                            <input type="radio" name="pedido_tipo" value="surtido_clasico48" class="hidden">
                        </div>

                        <!-- Surtido Especial x48 -->
                        <div class="bg-white rounded-lg border border-yellow-200 p-4 hover:border-yellow-400 cursor-pointer" onclick="seleccionarComun('surtido_especial48')">
                            <div class="flex items-center justify-between">
                                <div>
                                    <h4 class="font-semibold text-gray-800">Surtido Especial x48</h4>
                                    <p class="text-sm text-gray-600">6 planchas - Con sabores premium</p>
                                    <p class="font-bold text-green-600 text-lg">$25.000</p>
                                </div>
                                <div class="text-purple-500">
                                    <i class="fas fa-crown text-3xl"></i>
                                </div>
                            </div>
                            <input type="radio" name="pedido_tipo" value="surtido_especial48" class="hidden">
                        </div>
                    </div>
                </div>

                <!-- PERSONALIZADO -->
                <div class="bg-purple-50 rounded-lg p-4 mb-6">
                    <h3 class="font-bold text-purple-900 mb-4">
                        <i class="fas fa-palette mr-2"></i>Pedido Personalizado
                    </h3>
                    
                    <div class="bg-white rounded-lg border border-purple-200 p-4 hover:border-purple-400 cursor-pointer" onclick="seleccionarPersonalizado()">
                        <div class="flex items-center justify-between mb-4">
                            <div>
                                <h4 class="font-semibold text-gray-800">Personalizado</h4>
                                <p class="text-sm text-gray-600">Elegí cantidad y sabores</p>
                            </div>
                            <div class="text-purple-500">
                                <i class="fas fa-sliders-h text-3xl"></i>
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

        // Precios predefinidos
        const precios = {
            'jyq24': 18000,
            'jyq48': 22000,
            'surtido_clasico48': 20000,
            'surtido_especial48': 25000,
            'personalizado_base': 750 // por sándwich
        };

        // Actualizar hora actual
        function actualizarHora() {
            const ahora = new Date();
            document.getElementById('hora-actual').textContent = ahora.toLocaleTimeString();
        }
        setInterval(actualizarHora, 1000);
        actualizarHora();

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

        function resetearFormulario() {
            document.getElementById('formPedidoExpress').reset();
            document.querySelectorAll('.bg-white.rounded-lg.border').forEach(card => {
                card.classList.remove('border-blue-500', 'bg-blue-50');
                card.classList.add('border-gray-200');
            });
            document.getElementById('panelPersonalizado').classList.add('hidden');
            pedidoSeleccionado = null;
        }

        // Selección de pedidos comunes
        function seleccionarComun(tipo) {
            // Limpiar selecciones anteriores
            document.querySelectorAll('.bg-white.rounded-lg.border').forEach(card => {
                card.classList.remove('border-blue-500', 'bg-blue-50');
                card.classList.add('border-yellow-200');
            });
            
            // Ocultar panel personalizado
            document.getElementById('panelPersonalizado').classList.add('hidden');
            
            // Marcar el seleccionado
            event.currentTarget.classList.remove('border-yellow-200');
            event.currentTarget.classList.add('border-blue-500', 'bg-blue-50');
            
            // Marcar el radio correspondiente
            const radio = event.currentTarget.querySelector('input[type="radio"]');
            radio.checked = true;
            
            pedidoSeleccionado = tipo;
            precioBase = precios[tipo];
            
            console.log(`Seleccionado: ${tipo} - Precio: ${precioBase}`);
        }

        // Selección personalizado
        function seleccionarPersonalizado() {
            // Limpiar selecciones anteriores
            document.querySelectorAll('.bg-white.rounded-lg.border').forEach(card => {
                card.classList.remove('border-blue-500', 'bg-blue-50');
                card.classList.add('border-gray-200');
            });
            
            // Marcar personalizado
            event.currentTarget.classList.remove('border-purple-200');
            event.currentTarget.classList.add('border-blue-500', 'bg-blue-50');
            
            // Mostrar panel personalizado
            document.getElementById('panelPersonalizado').classList.remove('hidden');
            
            // Marcar radio
            const radio = event.currentTarget.querySelector('input[type="radio"]');
            radio.checked = true;
            
            pedidoSeleccionado = 'personalizado';
            actualizarPrecioPersonalizado();
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

        // Event listeners para checkboxes de sabores
        document.addEventListener('DOMContentLoaded', function() {
            document.querySelectorAll('.sabor-checkbox.premium').forEach(checkbox => {
                checkbox.addEventListener('change', actualizarPrecioPersonalizado);
            });
            
            // Inicializar precio personalizado
            actualizarPlanchas();
            actualizarPrecioPersonalizado();
        });

        // Envío del formulario
        document.getElementById('formPedidoExpress').addEventListener('submit', function(e) {
            e.preventDefault();
            
            // Validaciones básicas
            const nombre = document.getElementById('nombre').value.trim();
            const apellido = document.getElementById('apellido').value.trim();
            const modalidad = document.querySelector('input[name="modalidad"]:checked')?.value;
            const formaPago = document.querySelector('input[name="forma_pago"]:checked')?.value;
            
            if (!nombre || !apellido) {
                alert('Por favor, ingrese nombre y apellido del cliente');
                return;
            }
            
            if (!modalidad) {
                alert('Por favor, seleccione la modalidad (Retiro/Delivery)');
                return;
            }
            
            if (!formaPago) {
                alert('Por favor, seleccione la forma de pago');
                return;
            }
            
            if (!pedidoSeleccionado) {
                alert('Por favor, seleccione un tipo de pedido');
                return;
            }
            
            // Construir objeto del pedido
            const pedido = {
                nombre: nombre,
                apellido: apellido,
                telefono: document.getElementById('telefono').value.trim(),
                modalidad: modalidad,
                forma_pago: formaPago,
                observaciones: document.getElementById('observaciones').value.trim(),
                tipo_pedido: pedidoSeleccionado,
                precio: precioBase,
                ubicacion: 'Local 1',
                estado: 'Pendiente'
            };
            
            // Si es personalizado, agregar detalles
            if (pedidoSeleccionado === 'personalizado') {
                const cantidad = parseInt(document.getElementById('cantidadPersonalizada').value);
                const saboresSeleccionados = Array.from(document.querySelectorAll('.sabor-checkbox:checked')).map(cb => cb.value);
                
                pedido.cantidad = cantidad;
                pedido.sabores = saboresSeleccionados;
                pedido.producto = `Personalizado x${cantidad} (${Math.ceil(cantidad/8)} plancha${cantidad > 8 ? 's' : ''})`;
                
                // Agregar sabores a observaciones
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
                    alert(`✅ Pedido Express #${data.pedido_id} creado exitosamente!\n\nCliente: ${data.data.cliente}\nProducto: ${data.data.producto}\nPrecio: ${data.data.precio.toLocaleString()}\nModalidad: ${data.data.modalidad}`);
                    
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

        // Cerrar modal con ESC
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                cerrarPedidoExpress();
            }
        });
    </script>

</body>
</html>