<?php
require_once '../config.php';
session_start();

// Verificar acceso de empleado
if (!isset($_SESSION['empleado_logged']) || $_SESSION['empleado_logged'] !== true) {
    header('Location: login.php');
    exit;
}

$pdo = getConnection();

// Estadísticas generales para empleados
$stats = [
    'pendientes' => $pdo->query("SELECT COUNT(*) FROM pedidos WHERE estado = 'Pendiente'")->fetchColumn(),
    'preparando' => $pdo->query("SELECT COUNT(*) FROM pedidos WHERE estado = 'Preparando'")->fetchColumn(),
    'listos' => $pdo->query("SELECT COUNT(*) FROM pedidos WHERE estado = 'Listo'")->fetchColumn(),
    'pedidos_hoy' => $pdo->query("SELECT COUNT(*) FROM pedidos WHERE DATE(created_at) = CURDATE()")->fetchColumn()
];

// NUEVO: Estadísticas por ubicación para hoy
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

// Pedidos del día con información completa (incluyendo ubicación)
$pedidos_hoy = $pdo->query("
    SELECT id, nombre, apellido, producto, precio, estado, modalidad, ubicacion,
           observaciones, direccion, telefono, forma_pago,
           created_at, TIMESTAMPDIFF(MINUTE, created_at, NOW()) as minutos_transcurridos,
           fecha_entrega, hora_entrega, notas_horario, impreso
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

// NUEVO: Pedidos urgentes (más de 1 hora)
$pedidos_urgentes = array_filter($pedidos_hoy, function($pedido) {
    return $pedido['minutos_transcurridos'] > 60 && in_array($pedido['estado'], ['Pendiente', 'Preparando']);
});

// NUEVO: Próximas entregas programadas
$proximas_entregas = $pdo->query("
    SELECT id, nombre, apellido, producto, ubicacion, fecha_entrega, hora_entrega, notas_horario
    FROM pedidos 
    WHERE fecha_entrega >= CURDATE() 
    AND estado NOT IN ('Entregado')
    ORDER BY fecha_entrega, hora_entrega
    LIMIT 5
")->fetchAll();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Panel Empleados - Santa Catalina</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="bg-gray-100">
    <!-- Header -->
    <header class="bg-blue-600 text-white shadow-md">
        <div class="container mx-auto px-4 py-3 flex justify-between items-center">
            <h1 class="text-xl font-bold">
                <i class="fas fa-clipboard-list mr-2"></i>Panel de Empleados - Santa Catalina
            </h1>
            <div class="flex items-center space-x-4">
                <span class="text-blue-100">👋 Hola, <?= $_SESSION['empleado_name'] ?></span>
                <a href="logout.php" class="bg-red-500 hover:bg-red-600 px-3 py-2 rounded transition">
                    <i class="fas fa-sign-out-alt mr-1"></i>Salir
                </a>
            </div>
        </div>
    </header>

    <!-- Main Content -->
    <main class="container mx-auto px-4 py-6">
        
        <!-- NUEVA SECCIÓN: Alerta de pedidos urgentes -->
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

        <!-- Stats Cards Generales -->
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

        <!-- NUEVA SECCIÓN: Estadísticas por Ubicación -->
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
                            <h3 class="font-bold text-lg <?= $stat['ubicacion'] === 'Local 1' ? 'text-blue-800' : 'text-orange-800' ?>">
                                <?php if ($stat['ubicacion'] === 'Local 1'): ?>
                                    🏪 Local 1
                                <?php else: ?>
                                    🏭 Fábrica
                                <?php endif; ?>
                            </h3>
                            <span class="text-2xl font-bold <?= $stat['ubicacion'] === 'Local 1' ? 'text-blue-600' : 'text-orange-600' ?>">
                                <?= $stat['total'] ?>
                            </span>
                        </div>
                        
                        <div class="grid grid-cols-4 gap-2 text-sm">
                            <div class="text-center">
                                <div class="font-bold text-yellow-600"><?= $stat['pendientes'] ?></div>
                                <div class="text-gray-500 text-xs">Pend.</div>
                            </div>
                            <div class="text-center">
                                <div class="font-bold text-blue-600"><?= $stat['preparando'] ?></div>
                                <div class="text-gray-500 text-xs">Prep.</div>
                            </div>
                            <div class="text-center">
                                <div class="font-bold text-green-600"><?= $stat['listos'] ?></div>
                                <div class="text-gray-500 text-xs">List.</div>
                            </div>
                            <div class="text-center">
                                <div class="font-bold text-gray-600"><?= $stat['entregados'] ?></div>
                                <div class="text-gray-500 text-xs">Entr.</div>
                            </div>
                        </div>
                        
                        <div class="mt-3 text-center">
                            <a href="pedidos.php?ubicacion=<?= urlencode($stat['ubicacion']) ?>" 
                               class="text-xs <?= $stat['ubicacion'] === 'Local 1' ? 'text-blue-600 hover:text-blue-800' : 'text-orange-600 hover:text-orange-800' ?> hover:underline">
                                Ver solo <?= $stat['ubicacion'] ?>
                            </a>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- NUEVA SECCIÓN: Próximas entregas programadas -->
        <?php if (!empty($proximas_entregas)): ?>
        <div class="bg-white rounded-lg shadow mb-6 p-6">
            <h2 class="text-xl font-semibold text-gray-800 mb-4 flex items-center">
                <i class="fas fa-calendar-check text-green-500 mr-2"></i>
                Próximas Entregas Programadas
            </h2>
            
            <div class="space-y-3">
                <?php foreach ($proximas_entregas as $entrega): ?>
                    <div class="flex items-center justify-between p-3 border-l-4 border-green-400 bg-green-50 rounded">
                        <div>
                            <div class="font-semibold text-gray-800">
                                #<?= $entrega['id'] ?> - <?= htmlspecialchars($entrega['nombre'] . ' ' . $entrega['apellido']) ?>
                            </div>
                            <div class="text-sm text-gray-600">
                                <?= htmlspecialchars($entrega['producto']) ?>
                            </div>
                        </div>
                        <div class="text-right">
                            <div class="font-bold text-green-600">
                                <?= date('d/m', strtotime($entrega['fecha_entrega'])) ?>
                                <?php if ($entrega['hora_entrega']): ?>
                                    <?= substr($entrega['hora_entrega'], 0, 5) ?>
                                <?php endif; ?>
                            </div>
                            <div class="text-xs text-gray-500">
                                <?= $entrega['ubicacion'] === 'Local 1' ? '🏪 Local' : '🏭 Fábrica' ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- Botones de acceso mejorados -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-8">
            <a href="pedidos.php" class="bg-blue-600 hover:bg-blue-700 text-white p-6 rounded-lg text-center font-semibold transition shadow-lg">
                <i class="fas fa-list-alt text-3xl mb-3"></i>
                <div class="text-lg">Todos los Pedidos</div>
                <div class="text-sm opacity-90">Gestión completa</div>
            </a>
            
            <a href="pedidos.php?ubicacion=Local 1" class="bg-blue-500 hover:bg-blue-600 text-white p-6 rounded-lg text-center font-semibold transition shadow-lg">
                <i class="fas fa-store text-3xl mb-3"></i>
                <div class="text-lg">Solo Local 1</div>
                <div class="text-sm opacity-90">🏪 Atención al público</div>
            </a>
            
            <a href="pedidos.php?ubicacion=Fábrica" class="bg-orange-500 hover:bg-orange-600 text-white p-6 rounded-lg text-center font-semibold transition shadow-lg">
                <i class="fas fa-industry text-3xl mb-3"></i>
                <div class="text-lg">Solo Fábrica</div>
                <div class="text-sm opacity-90">🏭 Producción central</div>
            </a>
        </div>

        <!-- Lista de pedidos de hoy MEJORADA -->
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
                    <p>No hay pedidos para hoy aún</p>
                </div>
            <?php else: ?>
                <div class="divide-y divide-gray-200">
                    <?php foreach ($pedidos_hoy as $pedido): ?>
                        <?php
                        $estado_colors = [
                            'Pendiente' => 'bg-yellow-100 border-l-yellow-400 text-yellow-800',
                            'Preparando' => 'bg-blue-100 border-l-blue-400 text-blue-800',
                            'Listo' => 'bg-green-100 border-l-green-400 text-green-800',
                            'Entregado' => 'bg-gray-100 border-l-gray-400 text-gray-800'
                        ];
                        
                        $urgencia = '';
                        if ($pedido['minutos_transcurridos'] > 60) {
                            $urgencia = 'border-r-4 border-r-red-500';
                        } elseif ($pedido['minutos_transcurridos'] > 30) {
                            $urgencia = 'border-r-4 border-r-orange-500';
                        }

                        // Color de ubicación
                        $ubicacion_color = $pedido['ubicacion'] === 'Local 1' ? 'text-blue-600' : 'text-orange-600';
                        $ubicacion_bg = $pedido['ubicacion'] === 'Local 1' ? 'bg-blue-100' : 'bg-orange-100';
                        ?>
                        <div class="p-4 hover:bg-gray-50 border-l-4 <?= $estado_colors[$pedido['estado']] ?> <?= $urgencia ?>">
                            <div class="flex justify-between items-start">
                                <div class="flex-1">
                                    <div class="flex items-center space-x-3 mb-2">
                                        <span class="font-bold text-lg text-gray-900">#<?= $pedido['id'] ?></span>
                                        <span class="font-medium text-gray-800">
                                            <?= htmlspecialchars($pedido['nombre'] . ' ' . $pedido['apellido']) ?>
                                        </span>
                                        <span class="px-2 py-1 text-xs font-medium rounded-full <?= str_replace('border-l-', 'bg-', $estado_colors[$pedido['estado']]) ?>">
                                            <?= $pedido['estado'] ?>
                                        </span>
                                        
                                        <!-- NUEVO: Badge de ubicación -->
                                        <span class="px-2 py-1 text-xs font-medium rounded-full <?= $ubicacion_bg ?> <?= $ubicacion_color ?>">
                                            <?= $pedido['ubicacion'] === 'Local 1' ? '🏪 Local 1' : '🏭 Fábrica' ?>
                                        </span>
                                        
                                        <!-- Modalidad -->
                                        <?php if ($pedido['modalidad'] === 'Delivery'): ?>
                                            <i class="fas fa-truck text-green-600" title="Delivery"></i>
                                        <?php else: ?>
                                            <i class="fas fa-store text-blue-600" title="Retira"></i>
                                        <?php endif; ?>

                                        <!-- NUEVO: Estado de impresión -->
                                        <?php if ($pedido['impreso']): ?>
                                            <i class="fas fa-print text-green-600" title="Comanda impresa"></i>
                                        <?php else: ?>
                                            <i class="fas fa-print text-red-600" title="Sin imprimir"></i>
                                        <?php endif; ?>
                                    </div>
                                    
                                    <div class="text-gray-700 mb-1">
                                        <i class="fas fa-sandwich text-orange-500 mr-1"></i>
                                        <strong><?= htmlspecialchars($pedido['producto']) ?></strong>
                                        <span class="text-green-600 font-semibold ml-3">
                                            <?= formatPrice($pedido['precio']) ?>
                                        </span>
                                        <span class="text-gray-500 text-sm ml-2">
                                            (<?= $pedido['forma_pago'] ?>)
                                        </span>
                                    </div>

                                    <!-- NUEVA: Información de dirección para delivery -->
                                    <?php if ($pedido['modalidad'] === 'Delivery'): ?>
                                        <div class="text-sm text-gray-600 mb-1">
                                            <?php if ($pedido['direccion']): ?>
                                                <i class="fas fa-map-marker-alt text-red-500 mr-1"></i>
                                                <span class="font-medium"><?= htmlspecialchars($pedido['direccion']) ?></span>
                                            <?php else: ?>
                                                <i class="fas fa-exclamation-triangle text-red-500 mr-1"></i>
                                                <span class="text-red-600 font-medium">¡SIN DIRECCIÓN!</span>
                                            <?php endif; ?>
                                        </div>
                                    <?php endif; ?>

                                    <!-- Teléfono -->
                                    <div class="text-sm text-gray-600 mb-1">
                                        <i class="fas fa-phone mr-1"></i>
                                        <a href="tel:<?= $pedido['telefono'] ?>" class="text-blue-600 hover:underline">
                                            <?= htmlspecialchars($pedido['telefono']) ?>
                                        </a>
                                    </div>
                                    
                                    <?php if ($pedido['observaciones']): ?>
                                    <div class="text-sm text-gray-600 mb-1">
                                        <i class="fas fa-comment mr-1"></i>
                                        <?= htmlspecialchars($pedido['observaciones']) ?>
                                    </div>
                                    <?php endif; ?>

                                    <!-- NUEVA: Información de entrega programada -->
                                    <?php if ($pedido['fecha_entrega'] || $pedido['hora_entrega'] || $pedido['notas_horario']): ?>
                                    <div class="text-xs text-orange-600 bg-orange-50 px-2 py-1 rounded mt-1">
                                        <i class="fas fa-calendar-clock mr-1"></i>
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
                                    
                                    <div class="text-sm text-gray-500">
                                        <i class="fas fa-clock mr-1"></i>
                                        Hace <?= $pedido['minutos_transcurridos'] ?> min
                                        (<?= date('H:i', strtotime($pedido['created_at'])) ?>)
                                    </div>
                                </div>
                                
                                <div class="flex flex-col space-y-2 ml-4">
                                    <?php if ($pedido['minutos_transcurridos'] > 60): ?>
                                        <span class="bg-red-100 text-red-800 px-2 py-1 rounded text-xs font-medium">
                                            <i class="fas fa-exclamation-triangle mr-1"></i>URGENTE
                                        </span>
                                    <?php elseif ($pedido['minutos_transcurridos'] > 30): ?>
                                        <span class="bg-orange-100 text-orange-800 px-2 py-1 rounded text-xs font-medium">
                                            <i class="fas fa-clock mr-1"></i>PRIORIDAD
                                        </span>
                                    <?php endif; ?>

                                    <!-- NUEVO: Botón WhatsApp directo -->
                                    <a href="https://wa.me/<?= preg_replace('/[^0-9]/', '', $pedido['telefono']) ?>?text=Hola%20<?= urlencode($pedido['nombre']) ?>,%20tu%20pedido%20#<?= $pedido['id'] ?>%20está%20<?= urlencode(strtolower($pedido['estado'])) ?>" 
                                       target="_blank"
                                       class="bg-green-500 hover:bg-green-600 text-white px-2 py-1 rounded text-xs text-center">
                                        <i class="fab fa-whatsapp mr-1"></i>WhatsApp
                                    </a>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <!-- Footer info mejorado -->
        <div class="mt-8 text-center text-gray-500">
            <div class="bg-white rounded-lg p-6 shadow">
                <p class="mb-2">
                    <i class="fas fa-sync-alt mr-1"></i>
                    Página actualizada automáticamente cada 30 segundos
                </p>
                
                <div class="grid grid-cols-2 md:grid-cols-4 gap-4 text-sm mb-4">
                    <div class="text-center">
                        <div class="font-bold text-blue-600"><?= count($pedidos_hoy) ?></div>
                        <div>Pedidos Hoy</div>
                    </div>
                    <div class="text-center">
                        <div class="font-bold text-red-600"><?= count($pedidos_urgentes) ?></div>
                        <div>Urgentes</div>
                    </div>
                    <div class="text-center">
                        <div class="font-bold text-green-600"><?= count($proximas_entregas) ?></div>
                        <div>Próximas</div>
                    </div>
                    <div class="text-center">
                        <div class="font-bold text-purple-600"><?= count($stats_ubicacion) ?></div>
                        <div>Ubicaciones</div>
                    </div>
                </div>
                
                <p class="text-xs text-blue-600 mb-2">
                    🔴 Más de 1 hora = Urgente | 🟠 Más de 30 min = Prioridad
                </p>
                <p class="text-xs text-gray-500">
                    🏪 Local 1 = Atención al público | 🏭 Fábrica = Producción central
                </p>
            </div>
        </div>
    </main>

    <!-- Auto refresh script mejorado -->
    <script>
        // Auto-refresh cada 30 segundos
        setInterval(function() {
            location.reload();
        }, 30000);
        
        // Mostrar última actualización
        const lastUpdate = new Date().toLocaleTimeString();
        console.log('Última actualización:', lastUpdate);
        
        // Efectos visuales mejorados
        document.addEventListener('DOMContentLoaded', function() {
            // Animación para las stats cards
            const statsCards = document.querySelectorAll('.bg-yellow-500, .bg-blue-500, .bg-green-500, .bg-purple-500');
            statsCards.forEach((card, index) => {
                setTimeout(() => {
                    card.style.transform = 'scale(1.05)';
                    setTimeout(() => {
                        card.style.transform = 'scale(1)';
                        card.style.transition = 'transform 0.2s ease';
                    }, 100);
                }, index * 50);
            });

            // Destacar pedidos urgentes con animación
            const urgentes = document.querySelectorAll('.border-r-red-500');
            urgentes.forEach(el => {
                el.style.animation = 'pulse 2s infinite';
            });

            // Notificación de pedidos urgentes
            const pedidosUrgentes = <?= count($pedidos_urgentes) ?>;
            if (pedidosUrgentes > 0) {
                console.log(`⚠️ Hay ${pedidosUrgentes} pedido(s) urgente(s)`);
                
                // Solo mostrar notificación si hay muchos urgentes
                if (pedidosUrgentes > 2) {
                    const notification = document.createElement('div');
                    notification.className = 'fixed top-4 right-4 bg-red-500 text-white px-4 py-2 rounded-lg shadow-lg z-50';
                    notification.innerHTML = `<i class="fas fa-exclamation-triangle mr-2"></i>${pedidosUrgentes} pedidos urgentes`;
                    document.body.appendChild(notification);
                    
                    setTimeout(() => {
                        notification.remove();
                    }, 5000);
                }
            }

            // Destacar ubicaciones con colores
            const ubicacionElements = document.querySelectorAll('[class*="bg-blue-100"], [class*="bg-orange-100"]');
            ubicacionElements.forEach(el => {
                el.style.transition = 'all 0.3s ease';
            });

            // Info de debug para empleados
            console.log('=== DASHBOARD EMPLEADO ===');
            console.log('Pedidos hoy:', <?= count($pedidos_hoy) ?>);
            console.log('Pedidos urgentes:', pedidosUrgentes);
            console.log('Ubicaciones activas:', <?= count($stats_ubicacion) ?>);
            console.log('========================');
        });

        // Función para mostrar tiempo real
        function updateClock() {
            const now = new Date();
            const timeString = now.toLocaleTimeString('es-AR', { 
                hour: '2-digit', 
                minute: '2-digit',
                second: '2-digit'
            });
            
            let clockElement = document.getElementById('live-clock');
            if (!clockElement) {
                clockElement = document.createElement('div');
                clockElement.id = 'live-clock';
                clockElement.className = 'fixed bottom-4 left-4 bg-blue-600 text-white px-3 py-2 rounded-lg text-sm font-mono shadow-lg z-40';
                document.body.appendChild(clockElement);
            }
            
            clockElement.textContent = timeString;
        }

        // Actualizar reloj cada segundo
        setInterval(updateClock, 1000);
        updateClock();

        // Keyboard shortcuts para empleados
        document.addEventListener('keydown', function(e) {
            // F5 o Ctrl + R = Refresh
            if (e.key === 'F5' || (e.ctrlKey && e.key === 'r')) {
                console.log('🔄 Actualizando dashboard...');
            }
            
            // Ctrl + 1 = Pedidos Local 1
            if (e.ctrlKey && e.key === '1') {
                e.preventDefault();
                window.location.href = 'pedidos.php?ubicacion=Local 1';
            }
            
            // Ctrl + 2 = Pedidos Fábrica
            if (e.ctrlKey && e.key === '2') {
                e.preventDefault();
                window.location.href = 'pedidos.php?ubicacion=Fábrica';
            }
            
            // Ctrl + U = Pedidos urgentes
            if (e.ctrlKey && e.key === 'u') {
                e.preventDefault();
                window.location.href = 'pedidos.php?estado=Pendiente';
            }
        });

        // Sonido de alerta para pedidos urgentes (opcional)
        function alertaUrgentes() {
            const urgentes = <?= count($pedidos_urgentes) ?>;
            if (urgentes >= 3) {
                // Solo hacer sonido si hay 3 o más pedidos urgentes
                console.log('🚨 MUCHOS PEDIDOS URGENTES:', urgentes);
                
                // Crear un sonido simple (beep)
                try {
                    const audioContext = new (window.AudioContext || window.webkitAudioContext)();
                    const oscillator = audioContext.createOscillator();
                    const gainNode = audioContext.createGain();
                    
                    oscillator.connect(gainNode);
                    gainNode.connect(audioContext.destination);
                    
                    oscillator.frequency.value = 800;
                    oscillator.type = 'sine';
                    
                    gainNode.gain.setValueAtTime(0, audioContext.currentTime);
                    gainNode.gain.linearRampToValueAtTime(0.1, audioContext.currentTime + 0.1);
                    gainNode.gain.linearRampToValueAtTime(0, audioContext.currentTime + 0.5);
                    
                    oscillator.start(audioContext.currentTime);
                    oscillator.stop(audioContext.currentTime + 0.5);
                } catch (e) {
                    console.log('No se pudo reproducir sonido de alerta');
                }
            }
        }

        // Ejecutar alerta después de cargar
        setTimeout(alertaUrgentes, 2000);
    </script>

    <!-- CSS adicional -->
    <style>
        @keyframes pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.7; }
        }

        /* Mejoras visuales para ubicaciones */
        .border-l-blue-400 { border-left-color: #60a5fa !important; }
        .border-l-orange-400 { border-left-color: #fb923c !important; }
        
        .bg-blue-50 { background-color: #eff6ff !important; }
        .bg-orange-50 { background-color: #fff7ed !important; }

        /* Hover effects mejorados */
        .hover\\:bg-gray-50:hover {
            background-color: #f9fafb;
            transition: background-color 0.2s ease;
        }

        /* Responsive improvements */
        @media (max-width: 768px) {
            #live-clock {
                bottom: 80px;
                right: 4px;
                left: auto;
                font-size: 12px;
                padding: 6px 8px;
            }
            
            .grid.grid-cols-4.gap-2 {
                grid-template-columns: repeat(2, 1fr);
            }
        }

        /* Animaciones suaves */
        .transition-all {
            transition: all 0.3s ease;
        }

        /* Destacar pedidos por ubicación */
        [data-ubicacion="Local 1"] {
            border-left: 3px solid #3b82f6;
        }
        
        [data-ubicacion="Fábrica"] {
            border-left: 3px solid #f97316;
        }

        /* Mejoras para botones */
        .shadow-lg {
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
        }

        .shadow-lg:hover {
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
            transform: translateY(-2px);
        }
    </style>
</body>
</html>