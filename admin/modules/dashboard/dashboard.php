<?php
require_once '../../config.php';
requireLogin();

$pdo = getConnection();

// Obtener rango de fechas (últimos 30 días por defecto)
$fecha_inicio = $_GET['fecha_inicio'] ?? date('Y-m-d', strtotime('-30 days'));
$fecha_fin = $_GET['fecha_fin'] ?? date('Y-m-d');

// ============================================
// ESTADÍSTICAS DE HOY (Vista rápida)
// ============================================
$stmt = $pdo->query("
    SELECT
        COUNT(*) as total_hoy,
        COALESCE(SUM(precio), 0) as ventas_hoy,
        SUM(CASE WHEN estado = 'Pendiente' THEN 1 ELSE 0 END) as pendientes_hoy,
        SUM(CASE WHEN impreso = 0 AND estado != 'Entregado' THEN 1 ELSE 0 END) as sin_imprimir
    FROM pedidos
    WHERE DATE(created_at) = CURDATE()
    AND estado != 'Cancelado'
");
$hoy = $stmt->fetch();

// ============================================
// ESTADÍSTICAS DEL PERÍODO
// ============================================
$stmt = $pdo->prepare("
    SELECT
        COUNT(*) as total_pedidos,
        COALESCE(SUM(precio), 0) as total_ventas,
        COALESCE(AVG(precio), 0) as ticket_promedio
    FROM pedidos
    WHERE DATE(created_at) BETWEEN ? AND ?
    AND estado != 'Cancelado'
");
$stmt->execute([$fecha_inicio, $fecha_fin]);
$stats = $stmt->fetch();

// ============================================
// TOP 5 PRODUCTOS MÁS VENDIDOS
// ============================================
$stmt = $pdo->prepare("
    SELECT
        producto,
        COUNT(*) as cantidad,
        SUM(precio) as total_ventas
    FROM pedidos
    WHERE DATE(created_at) BETWEEN ? AND ?
    AND estado != 'Cancelado'
    GROUP BY producto
    ORDER BY cantidad DESC
    LIMIT 5
");
$stmt->execute([$fecha_inicio, $fecha_fin]);
$top_productos = $stmt->fetchAll();

// ============================================
// VENTAS POR DÍA (Últimos 14 días)
// ============================================
$stmt = $pdo->query("
    SELECT
        DATE(created_at) as fecha,
        COUNT(*) as cantidad_pedidos,
        SUM(precio) as total_ventas
    FROM pedidos
    WHERE DATE(created_at) >= CURDATE() - INTERVAL 14 DAY
    AND estado != 'Cancelado'
    GROUP BY DATE(created_at)
    ORDER BY fecha ASC
");
$ventas_14dias = $stmt->fetchAll();

// ============================================
// VENTAS POR UBICACIÓN
// ============================================
$stmt = $pdo->prepare("
    SELECT
        ubicacion,
        COUNT(*) as cantidad,
        SUM(precio) as total_ventas
    FROM pedidos
    WHERE DATE(created_at) BETWEEN ? AND ?
    AND estado != 'Cancelado'
    GROUP BY ubicacion
    ORDER BY total_ventas DESC
");
$stmt->execute([$fecha_inicio, $fecha_fin]);
$por_ubicacion = $stmt->fetchAll();

// ============================================
// VENTAS POR FORMA DE PAGO
// ============================================
$stmt = $pdo->prepare("
    SELECT
        forma_pago,
        COUNT(*) as cantidad,
        SUM(precio) as total_ventas
    FROM pedidos
    WHERE DATE(created_at) BETWEEN ? AND ?
    AND estado != 'Cancelado'
    GROUP BY forma_pago
    ORDER BY total_ventas DESC
");
$stmt->execute([$fecha_inicio, $fecha_fin]);
$por_pago = $stmt->fetchAll();

// ============================================
// VENTAS: HOY vs AYER
// ============================================
$stmt = $pdo->query("
    SELECT
        COUNT(*) as pedidos_ayer,
        COALESCE(SUM(precio), 0) as ventas_ayer
    FROM pedidos
    WHERE DATE(created_at) = CURDATE() - INTERVAL 1 DAY
    AND estado != 'Cancelado'
");
$ayer = $stmt->fetch();

// Calcular porcentaje de cambio
$cambio_ventas = $ayer['ventas_ayer'] > 0
    ? (($hoy['ventas_hoy'] - $ayer['ventas_ayer']) / $ayer['ventas_ayer']) * 100
    : 0;
$cambio_pedidos = $ayer['pedidos_ayer'] > 0
    ? (($hoy['total_hoy'] - $ayer['pedidos_ayer']) / $ayer['pedidos_ayer']) * 100
    : 0;
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - <?= APP_NAME ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        .stat-card {
            transition: all 0.3s ease;
        }
        .stat-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(0,0,0,0.12);
        }
        .badge-up { color: #10b981; }
        .badge-down { color: #ef4444; }
    </style>
</head>
<body class="bg-gray-50">

    <!-- Header -->
    <header class="bg-white shadow-sm sticky top-0 z-50">
        <div class="container mx-auto px-4 py-4 flex justify-between items-center">
            <div class="flex items-center">
                <a href="../../index.php" class="text-gray-600 hover:text-gray-800 mr-4">
                    <i class="fas fa-arrow-left text-lg"></i>
                </a>
                <h1 class="text-2xl font-bold text-gray-800">
                    <i class="fas fa-chart-bar text-blue-500 mr-2"></i>Dashboard
                </h1>
            </div>
            <div class="text-sm text-gray-600">
                <i class="fas fa-user mr-1"></i>
                <?= $_SESSION['admin_name'] ?? 'Admin' ?>
            </div>
        </div>
    </header>

    <main class="container mx-auto px-4 py-6 max-w-7xl">

        <!-- Alertas de HOY -->
        <?php if ($hoy['pendientes_hoy'] > 0 || $hoy['sin_imprimir'] > 0): ?>
        <div class="mb-6 space-y-2">
            <?php if ($hoy['sin_imprimir'] > 0): ?>
            <div class="bg-orange-50 border-l-4 border-orange-400 p-4 rounded">
                <div class="flex items-center">
                    <i class="fas fa-exclamation-triangle text-orange-500 text-xl mr-3"></i>
                    <div>
                        <p class="font-semibold text-orange-800">
                            <?= $hoy['sin_imprimir'] ?> pedido(s) sin imprimir
                        </p>
                        <p class="text-sm text-orange-700">Revisá el módulo de pedidos</p>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <?php if ($hoy['pendientes_hoy'] > 5): ?>
            <div class="bg-blue-50 border-l-4 border-blue-400 p-4 rounded">
                <div class="flex items-center">
                    <i class="fas fa-info-circle text-blue-500 text-xl mr-3"></i>
                    <div>
                        <p class="font-semibold text-blue-800">
                            <?= $hoy['pendientes_hoy'] ?> pedidos pendientes hoy
                        </p>
                    </div>
                </div>
            </div>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <!-- Tarjetas de HOY -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
            <!-- Ventas de Hoy -->
            <div class="stat-card bg-gradient-to-br from-blue-500 to-blue-600 text-white rounded-xl shadow-lg p-6">
                <div class="flex items-center justify-between mb-2">
                    <div>
                        <p class="text-blue-100 text-xs font-medium uppercase tracking-wide">Ventas Hoy</p>
                        <p class="text-3xl font-bold mt-1">$<?= number_format($hoy['ventas_hoy'], 0, ',', '.') ?></p>
                    </div>
                    <div class="text-5xl opacity-20">
                        <i class="fas fa-dollar-sign"></i>
                    </div>
                </div>
                <div class="text-xs text-blue-100 mt-2">
                    <?php if ($cambio_ventas > 0): ?>
                        <i class="fas fa-arrow-up"></i> <?= number_format($cambio_ventas, 1) ?>% vs ayer
                    <?php elseif ($cambio_ventas < 0): ?>
                        <i class="fas fa-arrow-down"></i> <?= number_format(abs($cambio_ventas), 1) ?>% vs ayer
                    <?php else: ?>
                        = Sin cambio vs ayer
                    <?php endif; ?>
                </div>
            </div>

            <!-- Pedidos de Hoy -->
            <div class="stat-card bg-gradient-to-br from-green-500 to-green-600 text-white rounded-xl shadow-lg p-6">
                <div class="flex items-center justify-between mb-2">
                    <div>
                        <p class="text-green-100 text-xs font-medium uppercase tracking-wide">Pedidos Hoy</p>
                        <p class="text-3xl font-bold mt-1"><?= $hoy['total_hoy'] ?></p>
                    </div>
                    <div class="text-5xl opacity-20">
                        <i class="fas fa-receipt"></i>
                    </div>
                </div>
                <div class="text-xs text-green-100 mt-2">
                    <?php if ($cambio_pedidos > 0): ?>
                        <i class="fas fa-arrow-up"></i> <?= number_format($cambio_pedidos, 1) ?>% vs ayer
                    <?php elseif ($cambio_pedidos < 0): ?>
                        <i class="fas fa-arrow-down"></i> <?= number_format(abs($cambio_pedidos), 1) ?>% vs ayer
                    <?php else: ?>
                        = Sin cambio vs ayer
                    <?php endif; ?>
                </div>
            </div>

            <!-- Ticket Promedio (Período) -->
            <div class="stat-card bg-gradient-to-br from-purple-500 to-purple-600 text-white rounded-xl shadow-lg p-6">
                <div class="flex items-center justify-between mb-2">
                    <div>
                        <p class="text-purple-100 text-xs font-medium uppercase tracking-wide">Ticket Promedio</p>
                        <p class="text-3xl font-bold mt-1">$<?= number_format($stats['ticket_promedio'], 0, ',', '.') ?></p>
                    </div>
                    <div class="text-5xl opacity-20">
                        <i class="fas fa-shopping-bag"></i>
                    </div>
                </div>
                <div class="text-xs text-purple-100 mt-2">
                    Últimos <?= (strtotime($fecha_fin) - strtotime($fecha_inicio)) / 86400 ?> días
                </div>
            </div>

            <!-- Total Período -->
            <div class="stat-card bg-gradient-to-br from-orange-500 to-orange-600 text-white rounded-xl shadow-lg p-6">
                <div class="flex items-center justify-between mb-2">
                    <div>
                        <p class="text-orange-100 text-xs font-medium uppercase tracking-wide">Total Período</p>
                        <p class="text-3xl font-bold mt-1">$<?= number_format($stats['total_ventas'], 0, ',', '.') ?></p>
                    </div>
                    <div class="text-5xl opacity-20">
                        <i class="fas fa-chart-line"></i>
                    </div>
                </div>
                <div class="text-xs text-orange-100 mt-2">
                    <?= $stats['total_pedidos'] ?> pedidos totales
                </div>
            </div>
        </div>

        <!-- Filtros de Período -->
        <div class="bg-white rounded-xl shadow p-4 mb-6">
            <form method="GET" class="flex flex-wrap items-end gap-3">
                <div>
                    <label class="block text-xs font-semibold text-gray-600 mb-1">Desde</label>
                    <input type="date" name="fecha_inicio" value="<?= $fecha_inicio ?>"
                           class="px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-gray-600 mb-1">Hasta</label>
                    <input type="date" name="fecha_fin" value="<?= $fecha_fin ?>"
                           class="px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500">
                </div>
                <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 text-sm font-medium">
                    <i class="fas fa-search mr-1"></i>Filtrar
                </button>
                <a href="?" class="px-4 py-2 bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300 text-sm font-medium">
                    <i class="fas fa-redo mr-1"></i>Últimos 30 días
                </a>
            </form>
        </div>

        <!-- Gráficos principales -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">

            <!-- Ventas de los últimos 14 días -->
            <div class="bg-white rounded-xl shadow p-6">
                <h3 class="text-lg font-bold text-gray-800 mb-4 flex items-center">
                    <i class="fas fa-chart-line text-blue-500 mr-2"></i>
                    Últimos 14 Días
                </h3>
                <canvas id="chartVentas14Dias" height="250"></canvas>
            </div>

            <!-- Top 5 Productos -->
            <div class="bg-white rounded-xl shadow p-6">
                <h3 class="text-lg font-bold text-gray-800 mb-4 flex items-center">
                    <i class="fas fa-medal text-yellow-500 mr-2"></i>
                    Top 5 Productos
                </h3>
                <canvas id="chartTopProductos" height="250"></canvas>
            </div>

        </div>

        <!-- Datos por Ubicación y Forma de Pago -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">

            <!-- Por Ubicación -->
            <div class="bg-white rounded-xl shadow p-6">
                <h3 class="text-lg font-bold text-gray-800 mb-4 flex items-center">
                    <i class="fas fa-map-marker-alt text-red-500 mr-2"></i>
                    Ventas por Ubicación
                </h3>
                <div class="space-y-3">
                    <?php foreach ($por_ubicacion as $loc): ?>
                    <div>
                        <div class="flex justify-between items-center mb-1">
                            <span class="text-sm font-medium text-gray-700"><?= htmlspecialchars($loc['ubicacion']) ?></span>
                            <span class="text-sm font-bold text-gray-900">$<?= number_format($loc['total_ventas'], 0, ',', '.') ?></span>
                        </div>
                        <div class="w-full bg-gray-200 rounded-full h-2">
                            <div class="bg-blue-600 h-2 rounded-full" style="width: <?= ($loc['total_ventas'] / $stats['total_ventas']) * 100 ?>%"></div>
                        </div>
                        <div class="text-xs text-gray-500 mt-1"><?= $loc['cantidad'] ?> pedidos</div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Por Forma de Pago -->
            <div class="bg-white rounded-xl shadow p-6">
                <h3 class="text-lg font-bold text-gray-800 mb-4 flex items-center">
                    <i class="fas fa-credit-card text-green-500 mr-2"></i>
                    Formas de Pago
                </h3>
                <canvas id="chartFormaPago" height="250"></canvas>
            </div>

        </div>

    </main>

    <script>
    Chart.defaults.font.family = "'Inter', 'Segoe UI', 'Arial', sans-serif";
    Chart.defaults.color = '#6B7280';

    // ============================================
    // GRÁFICO: Últimos 14 días
    // ============================================
    new Chart(document.getElementById('chartVentas14Dias'), {
        type: 'line',
        data: {
            labels: [<?php foreach($ventas_14dias as $v) echo "'" . date('d/m', strtotime($v['fecha'])) . "',"; ?>],
            datasets: [{
                label: 'Ventas ($)',
                data: [<?php foreach($ventas_14dias as $v) echo $v['total_ventas'] . ','; ?>],
                borderColor: 'rgb(59, 130, 246)',
                backgroundColor: 'rgba(59, 130, 246, 0.1)',
                tension: 0.3,
                fill: true,
                pointRadius: 4,
                pointHoverRadius: 6
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { display: false }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        callback: value => '$' + value.toLocaleString()
                    }
                }
            }
        }
    });

    // ============================================
    // GRÁFICO: Top 5 Productos
    // ============================================
    new Chart(document.getElementById('chartTopProductos'), {
        type: 'bar',
        data: {
            labels: [<?php foreach($top_productos as $p) echo "'" . addslashes(substr($p['producto'], 0, 25)) . "',"; ?>],
            datasets: [{
                label: 'Cantidad Vendida',
                data: [<?php foreach($top_productos as $p) echo $p['cantidad'] . ','; ?>],
                backgroundColor: [
                    'rgba(59, 130, 246, 0.8)',
                    'rgba(16, 185, 129, 0.8)',
                    'rgba(245, 158, 11, 0.8)',
                    'rgba(139, 92, 246, 0.8)',
                    'rgba(236, 72, 153, 0.8)'
                ],
                borderRadius: 6
            }]
        },
        options: {
            indexAxis: 'y',
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { display: false }
            },
            scales: {
                x: {
                    beginAtZero: true
                }
            }
        }
    });

    // ============================================
    // GRÁFICO: Forma de Pago
    // ============================================
    new Chart(document.getElementById('chartFormaPago'), {
        type: 'doughnut',
        data: {
            labels: [<?php foreach($por_pago as $p) echo "'" . $p['forma_pago'] . "',"; ?>],
            datasets: [{
                data: [<?php foreach($por_pago as $p) echo $p['total_ventas'] . ','; ?>],
                backgroundColor: [
                    'rgba(16, 185, 129, 0.8)',
                    'rgba(59, 130, 246, 0.8)',
                    'rgba(245, 158, 11, 0.8)',
                    'rgba(139, 92, 246, 0.8)'
                ],
                borderWidth: 2,
                borderColor: '#fff'
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'bottom',
                    labels: {
                        padding: 15,
                        font: { size: 12 }
                    }
                }
            }
        }
    });
    </script>

</body>
</html>
