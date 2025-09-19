<?php
/*
=== DASHBOARD ESPECÍFICO LOCAL 1 ===
Dashboard minimalista y optimizado para la estación Local 1
- Solo muestra pedidos de Local 1
- Auto-refresh cada 10 segundos
- Indicador de auto-impresión activa
- Interface simplificada para atención al cliente
*/

require_once '../admin/config.php';
session_start();

// Verificar autenticación específica de Local 1
if (!isset($_SESSION['empleado_logged']) || !isset($_SESSION['empleado_rol']) || $_SESSION['empleado_rol'] !== 'local1') {
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
                    $mensaje = "Pedido #$pedido_id marcado como entregado";
                }
                break;
        }
    } catch (PDOException $e) {
        $error = 'Error al procesar la acción';
        error_log("LOCAL1 ERROR: " . $e->getMessage());
    }
}

// Obtener pedidos solo de Local 1
try {
    // Pedidos activos (no entregados)
    $stmt = $pdo->prepare("
        SELECT *, 
               TIMESTAMPDIFF(MINUTE, created_at, NOW()) as minutos_espera,
               CASE 
                   WHEN TIMESTAMPDIFF(MINUTE, created_at, NOW()) > 60 THEN 'urgente'
                   WHEN TIMESTAMPDIFF(MINUTE, created_at, NOW()) > 30 THEN 'atencion'
                   ELSE 'normal'
               END as prioridad
        FROM pedidos 
        WHERE ubicacion = 'Local 1' 
          AND estado != 'Entregado'
          AND DATE(created_at) = CURDATE()
        ORDER BY 
          FIELD(estado, 'Pendiente', 'Preparando', 'Listo') ASC,
          created_at ASC
    ");
    $stmt->execute();
    $pedidos_activos = $stmt->fetchAll();
    
    // Estadísticas del día para Local 1
    $stats_stmt = $pdo->prepare("
        SELECT 
            COUNT(*) as total_dia,
            SUM(CASE WHEN estado = 'Pendiente' THEN 1 ELSE 0 END) as pendientes,
            SUM(CASE WHEN estado = 'Preparando' THEN 1 ELSE 0 END) as preparando,
            SUM(CASE WHEN estado = 'Listo' THEN 1 ELSE 0 END) as listos,
            SUM(CASE WHEN estado = 'Entregado' THEN 1 ELSE 0 END) as entregados,
            SUM(precio) as total_ventas,
            AVG(TIMESTAMPDIFF(MINUTE, created_at, 
                CASE 
                    WHEN estado = 'Entregado' THEN updated_at 
                    ELSE NOW() 
                END)) as tiempo_promedio
        FROM pedidos 
        WHERE ubicacion = 'Local 1' AND DATE(created_at) = CURDATE()
    ");
    $stats_stmt->execute();
    $stats = $stats_stmt->fetch();
    
    // Últimos entregados (para referencia)
    $entregados_stmt = $pdo->prepare("
        SELECT *, TIMESTAMPDIFF(MINUTE, created_at, updated_at) as tiempo_total
        FROM pedidos 
        WHERE ubicacion = 'Local 1' 
          AND estado = 'Entregado'
          AND DATE(created_at) = CURDATE()
        ORDER BY updated_at DESC 
        LIMIT 5
    ");
    $entregados_stmt->execute();
    $ultimos_entregados = $entregados_stmt->fetchAll();
    
} catch (PDOException $e) {
    $error = 'Error al cargar datos';
    error_log("LOCAL1 DASHBOARD ERROR: " . $e->getMessage());
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
    </style>
</head>
<body class="bg-gray-100 min-h-screen">
    
    <!-- Header fijo -->
    <header class="local1-gradient text-white shadow-lg sticky top-0 z-50">
        <div class="container mx-auto px-4 py-3">
            <div class="flex justify-between items-center">
                <div class="flex items-center">
                    <i class="fas fa-store text-2xl mr-3"></i>
                    <div>
                        <h1 class="text-xl font-bold">🏪 Local 1 - Dashboard</h1>
                        <p class="text-sm text-blue-200">
                            <?= htmlspecialchars($_SESSION['empleado_name']) ?> | 
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
                                    <div class="flex items-center justify-between mb-3">
                                        <h3 class="text-lg font-bold text-gray-800">
                                            Pedido #<?= $pedido['id'] ?>
                                            <?php if ($pedido['prioridad'] === 'urgente'): ?>
                                                <span class="ml-2 bg-red-500 text-white px-2 py-1 rounded text-xs">
                                                    <i class="fas fa-exclamation-triangle mr-1"></i>URGENTE
                                                </span>
                                            <?php elseif ($pedido['prioridad'] === 'atencion'): ?>
                                                <span class="ml-2 bg-orange-500 text-white px-2 py-1 rounded text-xs">
                                                    <i class="fas fa-clock mr-1"></i>ATENCIÓN
                                                </span>
                                            <?php endif; ?>
                                        </h3>
                                        
                                        <div class="text-right text-sm text-gray-500">
                                            <div>Hace <?= $pedido['minutos_espera'] ?> minutos</div>
                                            <div class="font-mono"><?= date('H:i', strtotime($pedido['created_at'])) ?></div>
                                        </div>
                                    </div>

                                    <!-- Información del cliente -->
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
                                                <p class="font-bold text-lg text-gray-800">
                                                    <i class="fas fa-sandwich mr-2 text-orange-500"></i>
                                                    <?= htmlspecialchars($pedido['producto']) ?>
                                                </p>
                                                <p class="text-sm text-gray-600">
                                                    Cantidad: <?= $pedido['cantidad'] ?> unidades
                                                </p>
                                            </div>
                                            <div class="text-right">
                                                <p class="text-2xl font-bold text-green-600">
                                                    <?= formatPrice($pedido['precio']) ?>
                                                </p>
                                                <p class="text-sm text-gray-600">
                                                    <?= $pedido['forma_pago'] ?>
                                                </p>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Observaciones si las hay -->
                                    <?php if ($pedido['observaciones']): ?>
                                        <div class="bg-blue-50 border-l-4 border-blue-400 p-3 mb-4">
                                            <p class="text-sm font-medium text-blue-800">
                                                <i class="fas fa-sticky-note mr-2"></i>Observaciones:
                                            </p>
                                            <p class="text-sm text-blue-700 mt-1">
                                                <?= htmlspecialchars($pedido['observaciones']) ?>
                                            </p>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <!-- Botones de acción -->
                            <div class="flex justify-between items-center pt-4 border-t border-gray-200">
                                <!-- Estado actual -->
                                <div class="flex items-center">
                                    <span class="text-sm font-medium text-gray-600 mr-3">Estado:</span>
                                    <span class="px-3 py-1 rounded-full text-sm font-semibold
                                        <?php
                                        switch($pedido['estado']) {
                                            case 'Pendiente': echo 'bg-yellow-100 text-yellow-800'; break;
                                            case 'Preparando': echo 'bg-blue-100 text-blue-800'; break;
                                            case 'Listo': echo 'bg-green-100 text-green-800'; break;
                                            default: echo 'bg-gray-100 text-gray-800';
                                        }
                                        ?>">
                                        <?= $pedido['estado'] ?>
                                    </span>
                                </div>

                                <!-- Botones de cambio de estado -->
                                <div class="flex space-x-2">
                                    <?php if ($pedido['estado'] === 'Pendiente'): ?>
                                        <form method="POST" class="inline">
                                            <input type="hidden" name="accion" value="cambiar_estado">
                                            <input type="hidden" name="pedido_id" value="<?= $pedido['id'] ?>">
                                            <input type="hidden" name="nuevo_estado" value="Preparando">
                                            <button type="submit" class="bg-blue-500 hover:bg-blue-600 text-white px-4 py-2 rounded text-sm font-semibold transition-colors">
                                                <i class="fas fa-fire mr-1"></i>Iniciar
                                            </button>
                                        </form>
                                    <?php elseif ($pedido['estado'] === 'Preparando'): ?>
                                        <form method="POST" class="inline">
                                            <input type="hidden" name="accion" value="cambiar_estado">
                                            <input type="hidden" name="pedido_id" value="<?= $pedido['id'] ?>">
                                            <input type="hidden" name="nuevo_estado" value="Listo">
                                            <button type="submit" class="bg-green-500 hover:bg-green-600 text-white px-4 py-2 rounded text-sm font-semibold transition-colors">
                                                <i class="fas fa-check mr-1"></i>Listo
                                            </button>
                                        </form>
                                    <?php elseif ($pedido['estado'] === 'Listo'): ?>
                                        <form method="POST" class="inline">
                                            <input type="hidden" name="accion" value="marcar_entregado">
                                            <input type="hidden" name="pedido_id" value="<?= $pedido['id'] ?>">
                                            <button type="submit" class="bg-purple-500 hover:bg-purple-600 text-white px-4 py-2 rounded text-sm font-semibold transition-colors">
                                                <i class="fas fa-handshake mr-1"></i>Entregar
                                            </button>
                                        </form>
                                    <?php endif; ?>

                                    <!-- Botón de impresión manual -->
                                    <a href="../admin/modules/impresion/comanda.php?pedido=<?= $pedido['id'] ?>" 
                                       target="_blank"
                                       class="bg-gray-500 hover:bg-gray-600 text-white px-3 py-2 rounded text-sm transition-colors"
                                       title="Imprimir comanda">
                                        <i class="fas fa-print"></i>
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <!-- Últimos entregados (referencia rápida) -->
        <?php if (!empty($ultimos_entregados)): ?>
        <div class="mt-8">
            <h3 class="text-lg font-semibold text-gray-700 mb-4 flex items-center">
                <i class="fas fa-history mr-2 text-green-500"></i>
                Últimos Entregados Hoy
            </h3>
            <div class="bg-white rounded-lg shadow overflow-hidden">
                <div class="divide-y divide-gray-200">
                    <?php foreach ($ultimos_entregados as $entregado): ?>
                        <div class="p-4 hover:bg-gray-50 transition-colors">
                            <div class="flex justify-between items-center">
                                <div>
                                    <span class="font-semibold text-gray-800">
                                        #<?= $entregado['id'] ?> - <?= htmlspecialchars($entregado['nombre'] . ' ' . $entregado['apellido']) ?>
                                    </span>
                                    <p class="text-sm text-gray-600">
                                        <?= htmlspecialchars($entregado['producto']) ?>
                                    </p>
                                </div>
                                <div class="text-right text-sm">
                                    <p class="font-semibold text-green-600">
                                        <?= formatPrice($entregado['precio']) ?>
                                    </p>
                                    <p class="text-gray-500">
                                        <?= $entregado['tiempo_total'] ?? 'N/A' ?> min
                                    </p>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
        <?php endif; ?>

    </main>

    <!-- Footer con información de la estación -->
    <footer class="bg-gray-800 text-white py-4 mt-8">
        <div class="container mx-auto px-4 text-center">
            <div class="flex justify-center items-center space-x-6 text-sm">
                <div class="flex items-center">
                    <i class="fas fa-store mr-2 text-blue-400"></i>
                    <span>Estación Local 1</span>
                </div>
                <div class="flex items-center">
                    <i class="fas fa-print mr-2 text-green-400"></i>
                    <span>Auto-impresión: <?= $auto_impresion_activa ? 'Activa' : 'Inactiva' ?></span>
                </div>
                <div class="flex items-center">
                    <i class="fas fa-sync-alt mr-2 text-yellow-400"></i>
                    <span>Última actualización: <span id="ultima-actualizacion"></span></span>
                </div>
            </div>
            <div class="mt-2 text-xs text-gray-400">
                Santa Catalina - Sistema Local 1 v2.0
            </div>
        </div>
    </footer>

    <!-- Scripts -->
    <script>
        // Actualizar hora actual
        function actualizarHora() {
            const ahora = new Date();
            const hora = ahora.toLocaleTimeString('es-AR', { 
                hour: '2-digit', 
                minute: '2-digit', 
                second: '2-digit' 
            });
            document.getElementById('hora-actual').textContent = hora;
            
            const ultimaActualizacion = document.getElementById('ultima-actualizacion');
            if (ultimaActualizacion) {
                ultimaActualizacion.textContent = hora;
            }