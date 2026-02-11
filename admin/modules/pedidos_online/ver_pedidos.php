<?php
/**
 * Vista de pedidos online
 * Muestra solo los pedidos que vienen del sistema online
 */
require_once '../../config.php';
requireLogin();

$pdo = getConnection();

// Filtros
$fecha = $_GET['fecha'] ?? date('Y-m-d');

// Obtener pedidos online
$stmt = $pdo->prepare("
    SELECT * FROM pedidos
    WHERE observaciones LIKE '%PEDIDO ONLINE%'
    AND DATE(created_at) = ?
    ORDER BY created_at DESC
");
$stmt->execute([$fecha]);
$pedidos = $stmt->fetchAll();

// Estadísticas
$total = count($pedidos);
$total_ventas = array_sum(array_column($pedidos, 'precio'));
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pedidos Online - <?= APP_NAME ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="bg-gray-100">

    <!-- Header -->
    <header class="bg-white shadow-md">
        <div class="container mx-auto px-4 py-4 flex justify-between items-center">
            <div class="flex items-center">
                <a href="../../index.php" class="text-gray-600 hover:text-gray-800 mr-4">
                    <i class="fas fa-arrow-left text-xl"></i>
                </a>
                <h1 class="text-2xl font-bold text-gray-800">
                    <i class="fas fa-globe text-green-600 mr-2"></i>
                    Pedidos Online
                </h1>
            </div>
            <div class="text-sm text-gray-600">
                <?= $_SESSION['admin_name'] ?? 'Admin' ?>
            </div>
        </div>
    </header>

    <main class="container mx-auto px-4 py-8 max-w-7xl">

        <!-- Estadísticas -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
            <div class="bg-gradient-to-br from-green-500 to-green-600 text-white rounded-xl shadow-lg p-6">
                <div class="flex items-center justify-between">
                    <div>
                        <div class="text-sm font-semibold opacity-90">Total Pedidos</div>
                        <div class="text-4xl font-black"><?= $total ?></div>
                    </div>
                    <div class="text-5xl opacity-20">
                        <i class="fas fa-shopping-cart"></i>
                    </div>
                </div>
            </div>

            <div class="bg-gradient-to-br from-blue-500 to-blue-600 text-white rounded-xl shadow-lg p-6">
                <div class="flex items-center justify-between">
                    <div>
                        <div class="text-sm font-semibold opacity-90">Total Ventas</div>
                        <div class="text-4xl font-black">$<?= number_format($total_ventas, 0, ',', '.') ?></div>
                    </div>
                    <div class="text-5xl opacity-20">
                        <i class="fas fa-dollar-sign"></i>
                    </div>
                </div>
            </div>

            <div class="bg-gradient-to-br from-purple-500 to-purple-600 text-white rounded-xl shadow-lg p-6">
                <div class="flex items-center justify-between">
                    <div>
                        <div class="text-sm font-semibold opacity-90">Ticket Promedio</div>
                        <div class="text-4xl font-black">
                            $<?= $total > 0 ? number_format($total_ventas / $total, 0, ',', '.') : '0' ?>
                        </div>
                    </div>
                    <div class="text-5xl opacity-20">
                        <i class="fas fa-receipt"></i>
                    </div>
                </div>
            </div>
        </div>

        <!-- Filtros -->
        <div class="bg-white rounded-lg shadow p-4 mb-6">
            <form method="GET" class="flex items-center gap-4">
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">Fecha</label>
                    <input type="date" name="fecha" value="<?= $fecha ?>"
                           class="px-4 py-2 border-2 border-gray-300 rounded-lg focus:border-blue-500">
                </div>
                <div class="mt-6">
                    <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white px-6 py-2 rounded-lg font-semibold">
                        <i class="fas fa-search mr-2"></i>Filtrar
                    </button>
                </div>
                <div class="mt-6">
                    <a href="?" class="bg-gray-500 hover:bg-gray-600 text-white px-6 py-2 rounded-lg font-semibold inline-block">
                        <i class="fas fa-redo mr-2"></i>Hoy
                    </a>
                </div>
                <div class="mt-6 ml-auto">
                    <a href="configuracion.php" class="bg-purple-600 hover:bg-purple-700 text-white px-6 py-2 rounded-lg font-semibold inline-block">
                        <i class="fas fa-cog mr-2"></i>Configuración
                    </a>
                </div>
            </form>
        </div>

        <!-- Lista de pedidos -->
        <?php if (empty($pedidos)): ?>
            <div class="bg-white rounded-lg shadow p-12 text-center">
                <i class="fas fa-inbox text-6xl text-gray-300 mb-4"></i>
                <h3 class="text-2xl font-bold text-gray-600">No hay pedidos online</h3>
                <p class="text-gray-500 mt-2">Para la fecha seleccionada</p>
            </div>
        <?php else: ?>
            <div class="bg-white rounded-lg shadow overflow-hidden">
                <table class="min-w-full">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">ID</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Cliente</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Producto</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Precio</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Estado</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Turno</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Hora</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php foreach ($pedidos as $pedido): ?>
                            <?php
                            // Extraer turno de observaciones
                            preg_match('/Turno:\s*(\w+)/', $pedido['observaciones'], $match);
                            $turno = $match[1] ?? '-';
                            ?>
                            <tr class="hover:bg-gray-50">
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                    #<?= $pedido['id'] ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm font-medium text-gray-900">
                                        <?= htmlspecialchars($pedido['nombre'] . ' ' . $pedido['apellido']) ?>
                                    </div>
                                    <div class="text-sm text-gray-500">
                                        <?= htmlspecialchars($pedido['telefono']) ?>
                                    </div>
                                </td>
                                <td class="px-6 py-4">
                                    <div class="text-sm text-gray-900"><?= htmlspecialchars($pedido['producto']) ?></div>
                                    <div class="text-xs text-gray-500"><?= $pedido['modalidad'] ?> · <?= $pedido['forma_pago'] ?></div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-bold text-gray-900">
                                    $<?= number_format($pedido['precio'], 0, ',', '.') ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full
                                        <?= $pedido['estado'] === 'Entregado' ? 'bg-green-100 text-green-800' :
                                            ($pedido['estado'] === 'Cancelado' ? 'bg-red-100 text-red-800' :
                                            'bg-yellow-100 text-yellow-800') ?>">
                                        <?= $pedido['estado'] ?>
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                    <?= $turno ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    <?= formatDateTime($pedido['created_at'], 'H:i') ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>

    </main>

</body>
</html>
