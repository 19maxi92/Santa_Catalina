<?php
require_once 'config.php';
requireLogin();

// Obtener estadísticas básicas
$pdo = getConnection();

$stats = [
    'pedidos_hoy' => $pdo->query("SELECT COUNT(*) FROM pedidos WHERE DATE(created_at) = CURDATE()")->fetchColumn(),
    'pedidos_pendientes' => $pdo->query("SELECT COUNT(*) FROM pedidos WHERE estado = 'Pendiente'")->fetchColumn(),
    'clientes_fijos' => $pdo->query("SELECT COUNT(*) FROM clientes_fijos WHERE activo = 1")->fetchColumn(),
    'ventas_hoy' => $pdo->query("SELECT COALESCE(SUM(precio), 0) FROM pedidos WHERE DATE(created_at) = CURDATE()")->fetchColumn()
];

// Últimos pedidos
$ultimos_pedidos = $pdo->query("
    SELECT id, nombre, apellido, producto, precio, estado, created_at 
    FROM pedidos 
    ORDER BY created_at DESC 
    LIMIT 5
")->fetchAll();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= APP_NAME ?> - Dashboard</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="bg-gray-100">
    <!-- Header -->
    <header class="bg-white shadow-md">
        <div class="container mx-auto px-4 py-3 flex justify-between items-center">
            <h1 class="text-xl font-bold text-gray-800">
                <i class="fas fa-utensils text-orange-500 mr-2"></i><?= APP_NAME ?>
            </h1>
            <div class="flex items-center space-x-4">
                <span class="text-gray-600">Hola, <?= $_SESSION['admin_name'] ?></span>
                <a href="logout.php" class="bg-red-500 hover:bg-red-600 text-white px-3 py-2 rounded">
                    <i class="fas fa-sign-out-alt mr-1"></i>Salir
                </a>
            </div>
        </div>
    </header>

    <!-- Main Content -->
    <main class="container mx-auto px-4 py-6">
        
        <!-- Stats Cards -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
            <div class="bg-blue-500 text-white p-6 rounded-lg shadow">
                <div class="flex items-center">
                    <i class="fas fa-clock text-2xl mr-3"></i>
                    <div>
                        <p class="text-sm opacity-80">Pedidos Hoy</p>
                        <p class="text-2xl font-bold"><?= $stats['pedidos_hoy'] ?></p>
                    </div>
                </div>
            </div>
            
            <div class="bg-yellow-500 text-white p-6 rounded-lg shadow">
                <div class="flex items-center">
                    <i class="fas fa-hourglass-half text-2xl mr-3"></i>
                    <div>
                        <p class="text-sm opacity-80">Pendientes</p>
                        <p class="text-2xl font-bold"><?= $stats['pedidos_pendientes'] ?></p>
                    </div>
                </div>
            </div>
            
            <div class="bg-green-500 text-white p-6 rounded-lg shadow">
                <div class="flex items-center">
                    <i class="fas fa-users text-2xl mr-3"></i>
                    <div>
                        <p class="text-sm opacity-80">Clientes Fijos</p>
                        <p class="text-2xl font-bold"><?= $stats['clientes_fijos'] ?></p>
                    </div>
                </div>
            </div>
            
            <div class="bg-purple-500 text-white p-6 rounded-lg shadow">
                <div class="flex items-center">
                    <i class="fas fa-dollar-sign text-2xl mr-3"></i>
                    <div>
                        <p class="text-sm opacity-80">Ventas Hoy</p>
                        <p class="text-2xl font-bold"><?= formatPrice($stats['ventas_hoy']) ?></p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Quick Actions -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
            <a href="modules/pedidos/crear_pedido.php" class="bg-white p-6 rounded-lg shadow hover:shadow-lg transition block text-center">
                <i class="fas fa-plus-circle text-3xl text-blue-500 mb-3"></i>
                <h3 class="text-lg font-semibold text-gray-800">Nuevo Pedido</h3>
                <p class="text-gray-600">Cargar pedido rápido</p>
            </a>
            
            <a href="modules/clientes/lista_clientes.php" class="bg-white p-6 rounded-lg shadow hover:shadow-lg transition block text-center">
                <i class="fas fa-address-book text-3xl text-green-500 mb-3"></i>
                <h3 class="text-lg font-semibold text-gray-800">Clientes Fijos</h3>
                <p class="text-gray-600">Gestionar clientes</p>
            </a>
            
            <a href="modules/pedidos/ver_pedidos.php" class="bg-white p-6 rounded-lg shadow hover:shadow-lg transition block text-center">
                <i class="fas fa-list text-3xl text-orange-500 mb-3"></i>
                <h3 class="text-lg font-semibold text-gray-800">Ver Pedidos</h3>
                <p class="text-gray-600">Listado completo</p>
            </a>
        </div>

        <!-- Recent Orders -->
        <div class="bg-white rounded-lg shadow">
            <div class="p-6 border-b">
                <h2 class="text-xl font-semibold text-gray-800">
                    <i class="fas fa-history mr-2"></i>Últimos Pedidos
                </h2>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">ID</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Cliente</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Producto</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Precio</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Estado</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Fecha</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        <?php if (empty($ultimos_pedidos)): ?>
                            <tr>
                                <td colspan="6" class="px-6 py-4 text-center text-gray-500">
                                    No hay pedidos registrados
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($ultimos_pedidos as $pedido): ?>
                                <tr class="hover:bg-gray-50">
                                    <td class="px-6 py-4 text-sm font-medium text-gray-900">
                                        #<?= $pedido['id'] ?>
                                    </td>
                                    <td class="px-6 py-4 text-sm text-gray-900">
                                        <?= htmlspecialchars($pedido['nombre'] . ' ' . $pedido['apellido']) ?>
                                    </td>
                                    <td class="px-6 py-4 text-sm text-gray-900">
                                        <?= htmlspecialchars($pedido['producto']) ?>
                                    </td>
                                    <td class="px-6 py-4 text-sm text-gray-900">
                                        <?= formatPrice($pedido['precio']) ?>
                                    </td>
                                    <td class="px-6 py-4">
                                        <?php
                                        $estado_color = [
                                            'Pendiente' => 'bg-yellow-100 text-yellow-800',
                                            'Preparando' => 'bg-blue-100 text-blue-800',
                                            'Listo' => 'bg-green-100 text-green-800',
                                            'Entregado' => 'bg-gray-100 text-gray-800'
                                        ];
                                        ?>
                                        <span class="px-2 py-1 text-xs font-medium rounded-full <?= $estado_color[$pedido['estado']] ?? 'bg-gray-100 text-gray-800' ?>">
                                            <?= $pedido['estado'] ?>
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 text-sm text-gray-500">
                                        <?= date('d/m H:i', strtotime($pedido['created_at'])) ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </main>
</body>
</html>