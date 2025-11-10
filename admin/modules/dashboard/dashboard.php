<?php
require_once '../../config.php';
requireLogin();

$pdo = getConnection();

// Obtener rango de fechas (últimos 30 días por defecto)
$fecha_inicio = $_GET['fecha_inicio'] ?? date('Y-m-d', strtotime('-30 days'));
$fecha_fin = $_GET['fecha_fin'] ?? date('Y-m-d');

// ============================================
// ESTADÍSTICAS GENERALES
// ============================================

// Total de ventas en el período
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

// Pedidos por estado
$stmt = $pdo->prepare("
    SELECT
        estado,
        COUNT(*) as cantidad,
        SUM(precio) as total
    FROM pedidos
    WHERE DATE(created_at) BETWEEN ? AND ?
    GROUP BY estado
    ORDER BY cantidad DESC
");
$stmt->execute([$fecha_inicio, $fecha_fin]);
$pedidos_por_estado = $stmt->fetchAll();

// ============================================
// VENTAS POR DÍA (GRÁFICO DE LÍNEA)
// ============================================
$stmt = $pdo->prepare("
    SELECT
        DATE(created_at) as fecha,
        COUNT(*) as cantidad_pedidos,
        SUM(precio) as total_ventas
    FROM pedidos
    WHERE DATE(created_at) BETWEEN ? AND ?
    AND estado != 'Cancelado'
    GROUP BY DATE(created_at)
    ORDER BY fecha ASC
");
$stmt->execute([$fecha_inicio, $fecha_fin]);
$ventas_por_dia = $stmt->fetchAll();

// ============================================
// PRODUCTOS MÁS VENDIDOS
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
    LIMIT 10
");
$stmt->execute([$fecha_inicio, $fecha_fin]);
$productos_mas_vendidos = $stmt->fetchAll();

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
$ventas_por_ubicacion = $stmt->fetchAll();

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
$ventas_por_pago = $stmt->fetchAll();

// ============================================
// VENTAS POR MODALIDAD
// ============================================
$stmt = $pdo->prepare("
    SELECT
        modalidad,
        COUNT(*) as cantidad,
        SUM(precio) as total_ventas
    FROM pedidos
    WHERE DATE(created_at) BETWEEN ? AND ?
    AND estado != 'Cancelado'
    GROUP BY modalidad
    ORDER BY total_ventas DESC
");
$stmt->execute([$fecha_inicio, $fecha_fin]);
$ventas_por_modalidad = $stmt->fetchAll();

// ============================================
// DÍA DE LA SEMANA CON MÁS VENTAS
// ============================================
$stmt = $pdo->prepare("
    SELECT
        DAYNAME(created_at) as dia_semana,
        DAYOFWEEK(created_at) as dia_num,
        COUNT(*) as cantidad,
        SUM(precio) as total_ventas
    FROM pedidos
    WHERE DATE(created_at) BETWEEN ? AND ?
    AND estado != 'Cancelado'
    GROUP BY dia_semana, dia_num
    ORDER BY total_ventas DESC
");
$stmt->execute([$fecha_inicio, $fecha_fin]);
$ventas_por_dia_semana = $stmt->fetchAll();

// Traducir nombres de días
$dias_es = [
    'Monday' => 'Lunes',
    'Tuesday' => 'Martes',
    'Wednesday' => 'Miércoles',
    'Thursday' => 'Jueves',
    'Friday' => 'Viernes',
    'Saturday' => 'Sábado',
    'Sunday' => 'Domingo'
];
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard de Ventas - <?= APP_NAME ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        .stat-card {
            transition: all 0.3s ease;
        }
        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 25px rgba(0,0,0,0.1);
        }
    </style>
</head>
<body class="bg-gray-100">

    <!-- Header -->
    <header class="bg-white shadow-md sticky top-0 z-50">
        <div class="container mx-auto px-4 py-3 flex justify-between items-center">
            <div class="flex items-center">
                <a href="../../index.php" class="text-gray-600 hover:text-gray-800 mr-4">
                    <i class="fas fa-arrow-left text-xl"></i>
                </a>
                <h1 class="text-xl font-bold text-gray-800">
                    <i class="fas fa-chart-line text-blue-500 mr-2"></i>Dashboard de Ventas
                </h1>
            </div>
            <div class="text-sm text-gray-600">
                <i class="fas fa-calendar-alt mr-1"></i>
                <?= date('d/m/Y', strtotime($fecha_inicio)) ?> - <?= date('d/m/Y', strtotime($fecha_fin)) ?>
            </div>
        </div>
    </header>

    <main class="container mx-auto px-4 py-6">

        <!-- Filtros de fecha -->
        <div class="bg-white rounded-lg shadow p-4 mb-6">
            <form method="GET" class="flex flex-wrap items-end gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Fecha Inicio</label>
                    <input type="date" name="fecha_inicio" value="<?= $fecha_inicio ?>"
                           class="px-3 py-2 border rounded-lg">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Fecha Fin</label>
                    <input type="date" name="fecha_fin" value="<?= $fecha_fin ?>"
                           class="px-3 py-2 border rounded-lg">
                </div>
                <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700">
                    <i class="fas fa-filter mr-2"></i>Filtrar
                </button>
                <a href="?" class="px-4 py-2 bg-gray-500 text-white rounded-lg hover:bg-gray-600">
                    <i class="fas fa-refresh mr-2"></i>Últimos 30 días
                </a>
            </form>
        </div>

        <!-- Tarjetas de estadísticas -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-6">
            <!-- Total Ventas -->
            <div class="stat-card bg-gradient-to-br from-blue-500 to-blue-600 text-white rounded-lg shadow-lg p-6">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-blue-100 text-sm mb-1">Total Ventas</p>
                        <p class="text-3xl font-bold">$<?= number_format($stats['total_ventas'], 0, ',', '.') ?></p>
                    </div>
                    <div class="text-5xl opacity-20">
                        <i class="fas fa-dollar-sign"></i>
                    </div>
                </div>
            </div>

            <!-- Total Pedidos -->
            <div class="stat-card bg-gradient-to-br from-green-500 to-green-600 text-white rounded-lg shadow-lg p-6">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-green-100 text-sm mb-1">Total Pedidos</p>
                        <p class="text-3xl font-bold"><?= $stats['total_pedidos'] ?></p>
                    </div>
                    <div class="text-5xl opacity-20">
                        <i class="fas fa-shopping-cart"></i>
                    </div>
                </div>
            </div>

            <!-- Ticket Promedio -->
            <div class="stat-card bg-gradient-to-br from-purple-500 to-purple-600 text-white rounded-lg shadow-lg p-6">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-purple-100 text-sm mb-1">Ticket Promedio</p>
                        <p class="text-3xl font-bold">$<?= number_format($stats['ticket_promedio'], 0, ',', '.') ?></p>
                    </div>
                    <div class="text-5xl opacity-20">
                        <i class="fas fa-receipt"></i>
                    </div>
                </div>
            </div>

            <!-- Mejor Día -->
            <div class="stat-card bg-gradient-to-br from-orange-500 to-orange-600 text-white rounded-lg shadow-lg p-6">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-orange-100 text-sm mb-1">Mejor Día</p>
                        <p class="text-2xl font-bold"><?= $dias_es[$ventas_por_dia_semana[0]['dia_semana']] ?? 'N/A' ?></p>
                        <p class="text-sm text-orange-100">$<?= number_format($ventas_por_dia_semana[0]['total_ventas'] ?? 0, 0, ',', '.') ?></p>
                    </div>
                    <div class="text-5xl opacity-20">
                        <i class="fas fa-calendar-day"></i>
                    </div>
                </div>
            </div>
        </div>

        <!-- Gráficos principales -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">

            <!-- Gráfico de Ventas por Día -->
            <div class="bg-white rounded-lg shadow p-6">
                <h3 class="text-lg font-bold text-gray-800 mb-4 flex items-center">
                    <i class="fas fa-chart-line text-blue-500 mr-2"></i>
                    Ventas por Día
                </h3>
                <canvas id="chartVentasPorDia"></canvas>
            </div>

            <!-- Gráfico de Productos Más Vendidos -->
            <div class="bg-white rounded-lg shadow p-6">
                <h3 class="text-lg font-bold text-gray-800 mb-4 flex items-center">
                    <i class="fas fa-trophy text-yellow-500 mr-2"></i>
                    Top 10 Productos Más Vendidos
                </h3>
                <canvas id="chartProductos"></canvas>
            </div>

        </div>

        <!-- Gráficos secundarios -->
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-6">

            <!-- Ventas por Ubicación -->
            <div class="bg-white rounded-lg shadow p-6">
                <h3 class="text-lg font-bold text-gray-800 mb-4 flex items-center">
                    <i class="fas fa-map-marker-alt text-red-500 mr-2"></i>
                    Por Ubicación
                </h3>
                <canvas id="chartUbicacion"></canvas>
            </div>

            <!-- Ventas por Forma de Pago -->
            <div class="bg-white rounded-lg shadow p-6">
                <h3 class="text-lg font-bold text-gray-800 mb-4 flex items-center">
                    <i class="fas fa-credit-card text-green-500 mr-2"></i>
                    Por Forma de Pago
                </h3>
                <canvas id="chartPago"></canvas>
            </div>

            <!-- Ventas por Modalidad -->
            <div class="bg-white rounded-lg shadow p-6">
                <h3 class="text-lg font-bold text-gray-800 mb-4 flex items-center">
                    <i class="fas fa-truck text-purple-500 mr-2"></i>
                    Por Modalidad
                </h3>
                <canvas id="chartModalidad"></canvas>
            </div>

        </div>

        <!-- Tabla de Pedidos por Estado -->
        <div class="bg-white rounded-lg shadow p-6">
            <h3 class="text-lg font-bold text-gray-800 mb-4 flex items-center">
                <i class="fas fa-list text-indigo-500 mr-2"></i>
                Pedidos por Estado
            </h3>
            <div class="overflow-x-auto">
                <table class="min-w-full">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Estado</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Cantidad</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Total Ventas</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Promedio</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php foreach ($pedidos_por_estado as $estado): ?>
                        <tr class="hover:bg-gray-50">
                            <td class="px-6 py-4 whitespace-nowrap">
                                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full
                                    <?= $estado['estado'] === 'Entregado' ? 'bg-green-100 text-green-800' :
                                        ($estado['estado'] === 'Cancelado' ? 'bg-red-100 text-red-800' :
                                        'bg-yellow-100 text-yellow-800') ?>">
                                    <?= $estado['estado'] ?>
                                </span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                <?= $estado['cantidad'] ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                $<?= number_format($estado['total'], 0, ',', '.') ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                $<?= number_format($estado['total'] / $estado['cantidad'], 0, ',', '.') ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

    </main>

    <script>
    // Configuración global de Chart.js
    Chart.defaults.font.family = "'Inter', 'Arial', sans-serif";
    Chart.defaults.color = '#4B5563';

    // ============================================
    // GRÁFICO DE VENTAS POR DÍA
    // ============================================
    const ventasPorDiaData = {
        labels: [<?php foreach($ventas_por_dia as $v) echo "'" . date('d/m', strtotime($v['fecha'])) . "',"; ?>],
        datasets: [{
            label: 'Ventas ($)',
            data: [<?php foreach($ventas_por_dia as $v) echo $v['total_ventas'] . ','; ?>],
            borderColor: 'rgb(59, 130, 246)',
            backgroundColor: 'rgba(59, 130, 246, 0.1)',
            tension: 0.4,
            fill: true
        }]
    };

    new Chart(document.getElementById('chartVentasPorDia'), {
        type: 'line',
        data: ventasPorDiaData,
        options: {
            responsive: true,
            maintainAspectRatio: true,
            plugins: {
                legend: { display: false }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        callback: function(value) {
                            return '$' + value.toLocaleString();
                        }
                    }
                }
            }
        }
    });

    // ============================================
    // GRÁFICO DE PRODUCTOS MÁS VENDIDOS
    // ============================================
    const productosData = {
        labels: [<?php foreach($productos_mas_vendidos as $p) echo "'" . addslashes(substr($p['producto'], 0, 20)) . "',"; ?>],
        datasets: [{
            label: 'Cantidad',
            data: [<?php foreach($productos_mas_vendidos as $p) echo $p['cantidad'] . ','; ?>],
            backgroundColor: [
                'rgba(59, 130, 246, 0.8)',
                'rgba(16, 185, 129, 0.8)',
                'rgba(245, 158, 11, 0.8)',
                'rgba(239, 68, 68, 0.8)',
                'rgba(139, 92, 246, 0.8)',
                'rgba(236, 72, 153, 0.8)',
                'rgba(34, 197, 94, 0.8)',
                'rgba(249, 115, 22, 0.8)',
                'rgba(6, 182, 212, 0.8)',
                'rgba(168, 85, 247, 0.8)'
            ]
        }]
    };

    new Chart(document.getElementById('chartProductos'), {
        type: 'bar',
        data: productosData,
        options: {
            indexAxis: 'y',
            responsive: true,
            maintainAspectRatio: true,
            plugins: {
                legend: { display: false }
            }
        }
    });

    // ============================================
    // GRÁFICO DE UBICACIÓN
    // ============================================
    const ubicacionData = {
        labels: [<?php foreach($ventas_por_ubicacion as $u) echo "'" . $u['ubicacion'] . "',"; ?>],
        datasets: [{
            data: [<?php foreach($ventas_por_ubicacion as $u) echo $u['total_ventas'] . ','; ?>],
            backgroundColor: [
                'rgba(239, 68, 68, 0.8)',
                'rgba(59, 130, 246, 0.8)',
                'rgba(16, 185, 129, 0.8)'
            ]
        }]
    };

    new Chart(document.getElementById('chartUbicacion'), {
        type: 'doughnut',
        data: ubicacionData,
        options: {
            responsive: true,
            maintainAspectRatio: true
        }
    });

    // ============================================
    // GRÁFICO DE FORMA DE PAGO
    // ============================================
    const pagoData = {
        labels: [<?php foreach($ventas_por_pago as $p) echo "'" . $p['forma_pago'] . "',"; ?>],
        datasets: [{
            data: [<?php foreach($ventas_por_pago as $p) echo $p['total_ventas'] . ','; ?>],
            backgroundColor: [
                'rgba(16, 185, 129, 0.8)',
                'rgba(59, 130, 246, 0.8)',
                'rgba(245, 158, 11, 0.8)'
            ]
        }]
    };

    new Chart(document.getElementById('chartPago'), {
        type: 'pie',
        data: pagoData,
        options: {
            responsive: true,
            maintainAspectRatio: true
        }
    });

    // ============================================
    // GRÁFICO DE MODALIDAD
    // ============================================
    const modalidadData = {
        labels: [<?php foreach($ventas_por_modalidad as $m) echo "'" . $m['modalidad'] . "',"; ?>],
        datasets: [{
            data: [<?php foreach($ventas_por_modalidad as $m) echo $m['total_ventas'] . ','; ?>],
            backgroundColor: [
                'rgba(139, 92, 246, 0.8)',
                'rgba(236, 72, 153, 0.8)'
            ]
        }]
    };

    new Chart(document.getElementById('chartModalidad'), {
        type: 'doughnut',
        data: modalidadData,
        options: {
            responsive: true,
            maintainAspectRatio: true
        }
    });
    </script>

</body>
</html>
