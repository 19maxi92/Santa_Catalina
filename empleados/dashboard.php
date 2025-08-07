<?php
require_once '../config.php';
session_start();

// Verificar acceso de empleado
if (!isset($_SESSION['empleado_logged']) || $_SESSION['empleado_logged'] !== true) {
    header('Location: login.php');
    exit;
}

$pdo = getConnection();

// Estadísticas para empleados
$stats = [
    'pendientes' => $pdo->query("SELECT COUNT(*) FROM pedidos WHERE estado = 'Pendiente'")->fetchColumn(),
    'preparando' => $pdo->query("SELECT COUNT(*) FROM pedidos WHERE estado = 'Preparando'")->fetchColumn(),
    'listos' => $pdo->query("SELECT COUNT(*) FROM pedidos WHERE estado = 'Listo'")->fetchColumn(),
    'pedidos_hoy' => $pdo->query("SELECT COUNT(*) FROM pedidos WHERE DATE(created_at) = CURDATE()")->fetchColumn()
];

// Pedidos del día por estado
$pedidos_hoy = $pdo->query("
    SELECT id, nombre, apellido, producto, precio, estado, modalidad, observaciones,
           created_at, TIMESTAMPDIFF(MINUTE, created_at, NOW()) as minutos_transcurridos
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

        <!-- Botón de acceso a pedidos -->
        <div class="text-center mb-8">
            <a href="pedidos.php" class="bg-blue-600 hover:bg-blue-700 text-white px-8 py-4 rounded-lg text-lg font-semibold transition inline-block shadow-lg">
                <i class="fas fa-list-alt mr-3"></i>Ver Todos los Pedidos
            </a>
        </div>

        <!-- Lista de pedidos de hoy -->
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
                                        <?php if ($pedido['modalidad'] === 'Delivery'): ?>
                                            <i class="fas fa-truck text-green-600" title="Delivery"></i>
                                        <?php else: ?>
                                            <i class="fas fa-store text-blue-600" title="Retira"></i>
                                        <?php endif; ?>
                                    </div>
                                    
                                    <div class="text-gray-700 mb-1">
                                        <i class="fas fa-sandwich text-orange-500 mr-1"></i>
                                        <strong><?= htmlspecialchars($pedido['producto']) ?></strong>
                                        <span class="text-green-600 font-semibold ml-3">
                                            <?= formatPrice($pedido['precio']) ?>
                                        </span>
                                    </div>
                                    
                                    <?php if ($pedido['observaciones']): ?>
                                    <div class="text-sm text-gray-600 mb-1">
                                        <i class="fas fa-comment mr-1"></i>
                                        <?= htmlspecialchars($pedido['observaciones']) ?>
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
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <!-- Footer info -->
        <div class="mt-8 text-center text-gray-500">
            <p class="mb-2">
                <i class="fas fa-info-circle mr-1"></i>
                Los pedidos se actualizan automáticamente cada 30 segundos
            </p>
            <p class="text-sm">
                🔴 Más de 1 hora = Urgente | 🟠 Más de 30 min = Prioridad
            </p>
            <p class="text-xs mt-2 text-blue-600">
                <i class="fas fa-print mr-1"></i>
                Para impresión de comandas, contactar al administrador
            </p>
        </div>
    </main>

    <!-- Auto refresh script -->
    <script>
        // Auto-refresh cada 30 segundos
        setInterval(function() {
            location.reload();
        }, 30000);
        
        // Mostrar última actualización
        const lastUpdate = new Date().toLocaleTimeString();
        console.log('Última actualización:', lastUpdate);
        
        // Opcional: Mostrar notificación de actualización
        document.addEventListener('DOMContentLoaded', function() {
            // Pequeña animación para indicar carga
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
        });
    </script>
</body>
</html>