<?php
require_once '../../config.php';
requireLogin();

$pdo = getConnection();

// Obtener rango de fechas (últimos 30 días por defecto)
$fecha_inicio = $_GET['fecha_inicio'] ?? date('Y-m-d', strtotime('-30 days'));
$fecha_fin = $_GET['fecha_fin'] ?? date('Y-m-d');

// ============================================
// MEGA KPIs - HOY
// ============================================
$stmt = $pdo->query("
    SELECT
        COUNT(*) as total_hoy,
        COALESCE(SUM(precio), 0) as ventas_hoy,
        COALESCE(AVG(precio), 0) as ticket_hoy,
        SUM(CASE WHEN estado = 'Pendiente' THEN 1 ELSE 0 END) as pendientes_hoy,
        SUM(CASE WHEN estado = 'Entregado' THEN 1 ELSE 0 END) as entregados_hoy,
        SUM(CASE WHEN impreso = 0 AND estado != 'Entregado' AND estado != 'Cancelado' THEN 1 ELSE 0 END) as sin_imprimir
    FROM pedidos
    WHERE DATE(created_at) = CURDATE()
    AND estado != 'Cancelado'
");
$hoy = $stmt->fetch();

// AYER
$stmt = $pdo->query("
    SELECT
        COUNT(*) as total_ayer,
        COALESCE(SUM(precio), 0) as ventas_ayer
    FROM pedidos
    WHERE DATE(created_at) = CURDATE() - INTERVAL 1 DAY
    AND estado != 'Cancelado'
");
$ayer = $stmt->fetch();

// ============================================
// ESTA SEMANA vs SEMANA PASADA
// ============================================
$stmt = $pdo->query("
    SELECT
        COUNT(*) as pedidos_semana,
        COALESCE(SUM(precio), 0) as ventas_semana
    FROM pedidos
    WHERE YEARWEEK(created_at, 1) = YEARWEEK(CURDATE(), 1)
    AND estado != 'Cancelado'
");
$esta_semana = $stmt->fetch();

$stmt = $pdo->query("
    SELECT
        COUNT(*) as pedidos_semana_pasada,
        COALESCE(SUM(precio), 0) as ventas_semana_pasada
    FROM pedidos
    WHERE YEARWEEK(created_at, 1) = YEARWEEK(CURDATE(), 1) - 1
    AND estado != 'Cancelado'
");
$semana_pasada = $stmt->fetch();

// ============================================
// ESTE MES vs MES PASADO
// ============================================
$stmt = $pdo->query("
    SELECT
        COUNT(*) as pedidos_mes,
        COALESCE(SUM(precio), 0) as ventas_mes,
        COALESCE(AVG(precio), 0) as ticket_mes
    FROM pedidos
    WHERE YEAR(created_at) = YEAR(CURDATE())
    AND MONTH(created_at) = MONTH(CURDATE())
    AND estado != 'Cancelado'
");
$este_mes = $stmt->fetch();

$stmt = $pdo->query("
    SELECT
        COUNT(*) as pedidos_mes_pasado,
        COALESCE(SUM(precio), 0) as ventas_mes_pasado
    FROM pedidos
    WHERE YEAR(created_at) = YEAR(CURDATE() - INTERVAL 1 MONTH)
    AND MONTH(created_at) = MONTH(CURDATE() - INTERVAL 1 MONTH)
    AND estado != 'Cancelado'
");
$mes_pasado = $stmt->fetch();

// ============================================
// ESTADÍSTICAS DEL PERÍODO SELECCIONADO
// ============================================
$stmt = $pdo->prepare("
    SELECT
        COUNT(*) as total_pedidos,
        COALESCE(SUM(precio), 0) as total_ventas,
        COALESCE(AVG(precio), 0) as ticket_promedio,
        SUM(CASE WHEN estado = 'Entregado' THEN 1 ELSE 0 END) as total_entregados,
        SUM(CASE WHEN estado = 'Cancelado' THEN 1 ELSE 0 END) as total_cancelados
    FROM pedidos
    WHERE DATE(created_at) BETWEEN ? AND ?
");
$stmt->execute([$fecha_inicio, $fecha_fin]);
$periodo = $stmt->fetch();

// Tasa de conversión (entregados / total)
$tasa_conversion = $periodo['total_pedidos'] > 0
    ? ($periodo['total_entregados'] / $periodo['total_pedidos']) * 100
    : 0;

// ============================================
// VENTAS POR SEMANA (Últimas 8 semanas)
// ============================================
$stmt = $pdo->query("
    SELECT
        YEARWEEK(created_at, 1) as semana,
        DATE(DATE_SUB(created_at, INTERVAL WEEKDAY(created_at) DAY)) as inicio_semana,
        COUNT(*) as cantidad,
        SUM(precio) as total
    FROM pedidos
    WHERE created_at >= CURDATE() - INTERVAL 8 WEEK
    AND estado != 'Cancelado'
    GROUP BY YEARWEEK(created_at, 1), inicio_semana
    ORDER BY semana ASC
");
$ventas_semanales = $stmt->fetchAll();

// ============================================
// TOP 10 PRODUCTOS DEL PERÍODO
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
$top_productos = $stmt->fetchAll();

// ============================================
// DISTRIBUCIÓN POR UBICACIÓN
// ============================================
$stmt = $pdo->prepare("
    SELECT
        ubicacion,
        COUNT(*) as cantidad,
        SUM(precio) as total,
        AVG(precio) as promedio
    FROM pedidos
    WHERE DATE(created_at) BETWEEN ? AND ?
    AND estado != 'Cancelado'
    GROUP BY ubicacion
    ORDER BY total DESC
");
$stmt->execute([$fecha_inicio, $fecha_fin]);
$por_ubicacion = $stmt->fetchAll();

// ============================================
// DISTRIBUCIÓN POR FORMA DE PAGO
// ============================================
$stmt = $pdo->prepare("
    SELECT
        forma_pago,
        COUNT(*) as cantidad,
        SUM(precio) as total
    FROM pedidos
    WHERE DATE(created_at) BETWEEN ? AND ?
    AND estado != 'Cancelado'
    GROUP BY forma_pago
    ORDER BY total DESC
");
$stmt->execute([$fecha_inicio, $fecha_fin]);
$por_pago = $stmt->fetchAll();

// Cálculos de cambios
$cambio_dia = $ayer['total_ayer'] > 0 ? (($hoy['total_hoy'] - $ayer['total_ayer']) / $ayer['total_ayer']) * 100 : 0;
$cambio_semana = $semana_pasada['pedidos_semana_pasada'] > 0 ? (($esta_semana['pedidos_semana'] - $semana_pasada['pedidos_semana_pasada']) / $semana_pasada['pedidos_semana_pasada']) * 100 : 0;
$cambio_mes = $mes_pasado['pedidos_mes_pasado'] > 0 ? (($este_mes['pedidos_mes'] - $mes_pasado['pedidos_mes_pasado']) / $mes_pasado['pedidos_mes_pasado']) * 100 : 0;

$cambio_ventas_dia = $ayer['ventas_ayer'] > 0 ? (($hoy['ventas_hoy'] - $ayer['ventas_ayer']) / $ayer['ventas_ayer']) * 100 : 0;
$cambio_ventas_semana = $semana_pasada['ventas_semana_pasada'] > 0 ? (($esta_semana['ventas_semana'] - $semana_pasada['ventas_semana_pasada']) / $semana_pasada['ventas_semana_pasada']) * 100 : 0;
$cambio_ventas_mes = $mes_pasado['ventas_mes_pasado'] > 0 ? (($este_mes['ventas_mes'] - $mes_pasado['ventas_mes_pasado']) / $mes_pasado['ventas_mes_pasado']) * 100 : 0;
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Control Center - <?= APP_NAME ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap');

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
        }

        .mega-stat {
            font-size: 4rem;
            font-weight: 900;
            line-height: 1;
            letter-spacing: -0.02em;
        }

        .stat-label {
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            opacity: 0.8;
        }

        .change-badge {
            display: inline-flex;
            align-items: center;
            gap: 0.25rem;
            padding: 0.25rem 0.5rem;
            border-radius: 9999px;
            font-size: 0.75rem;
            font-weight: 700;
        }

        .change-up {
            background: rgba(16, 185, 129, 0.15);
            color: #10b981;
        }

        .change-down {
            background: rgba(239, 68, 68, 0.15);
            color: #ef4444;
        }

        .change-neutral {
            background: rgba(107, 114, 128, 0.15);
            color: #6b7280;
        }

        .glass-card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.2);
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
        }

        .alert-critical {
            animation: pulse 2s cubic-bezier(0.4, 0, 0.6, 1) infinite;
        }

        @keyframes pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.7; }
        }

        .progress-ring {
            transform: rotate(-90deg);
        }
    </style>
</head>
<body>

    <!-- Header Compacto -->
    <header class="glass-card sticky top-0 z-50 border-b">
        <div class="container mx-auto px-6 py-3">
            <div class="flex justify-between items-center">
                <div class="flex items-center gap-4">
                    <a href="../../index.php" class="text-purple-700 hover:text-purple-900">
                        <i class="fas fa-arrow-left text-lg"></i>
                    </a>
                    <h1 class="text-2xl font-black text-gray-900">
                        <i class="fas fa-industry text-purple-600 mr-2"></i>
                        CONTROL CENTER
                    </h1>
                </div>
                <div class="flex items-center gap-4">
                    <div class="text-xs text-gray-600 font-semibold">
                        <i class="fas fa-user-shield mr-1"></i>
                        <?= $_SESSION['admin_name'] ?? 'Admin' ?>
                    </div>
                    <div class="text-xs text-gray-600 font-mono bg-gray-100 px-3 py-1 rounded">
                        <?= date('d/m/Y H:i') ?>
                    </div>
                </div>
            </div>
        </div>
    </header>

    <main class="container mx-auto px-6 py-8 max-w-[1600px]">

        <!-- ALERTAS CRÍTICAS -->
        <?php if ($hoy['sin_imprimir'] > 0): ?>
        <div class="alert-critical glass-card border-l-4 border-red-500 p-4 mb-6 rounded-lg">
            <div class="flex items-center gap-3">
                <i class="fas fa-exclamation-triangle text-red-500 text-3xl"></i>
                <div>
                    <p class="text-xl font-black text-red-900">
                        ⚠️ <?= $hoy['sin_imprimir'] ?> PEDIDOS SIN IMPRIMIR
                    </p>
                    <p class="text-sm text-red-700 font-medium">Acción requerida inmediatamente</p>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- MEGA KPIs PRINCIPALES -->
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-8">

            <!-- HOY -->
            <div class="glass-card rounded-2xl p-8 border-l-8 border-blue-500">
                <div class="stat-label text-blue-600 mb-3">
                    <i class="fas fa-calendar-day mr-1"></i>HOY
                </div>
                <div class="mega-stat text-gray-900 mb-2">
                    <?= number_format($hoy['total_hoy']) ?>
                </div>
                <div class="text-sm text-gray-600 font-semibold mb-3">
                    pedidos · $<?= number_format($hoy['ventas_hoy'], 0, ',', '.') ?>
                </div>
                <div class="change-badge <?= $cambio_dia > 0 ? 'change-up' : ($cambio_dia < 0 ? 'change-down' : 'change-neutral') ?>">
                    <i class="fas fa-arrow-<?= $cambio_dia > 0 ? 'up' : ($cambio_dia < 0 ? 'down' : 'right') ?>"></i>
                    <?= number_format(abs($cambio_dia), 1) ?>% vs ayer
                </div>
                <div class="mt-4 pt-4 border-t flex justify-between text-xs font-semibold text-gray-600">
                    <div>
                        <i class="fas fa-check-circle text-green-500 mr-1"></i>
                        <?= $hoy['entregados_hoy'] ?> entregados
                    </div>
                    <div>
                        <i class="fas fa-clock text-orange-500 mr-1"></i>
                        <?= $hoy['pendientes_hoy'] ?> pendientes
                    </div>
                </div>
            </div>

            <!-- ESTA SEMANA -->
            <div class="glass-card rounded-2xl p-8 border-l-8 border-purple-500">
                <div class="stat-label text-purple-600 mb-3">
                    <i class="fas fa-calendar-week mr-1"></i>ESTA SEMANA
                </div>
                <div class="mega-stat text-gray-900 mb-2">
                    <?= number_format($esta_semana['pedidos_semana']) ?>
                </div>
                <div class="text-sm text-gray-600 font-semibold mb-3">
                    pedidos · $<?= number_format($esta_semana['ventas_semana'], 0, ',', '.') ?>
                </div>
                <div class="change-badge <?= $cambio_semana > 0 ? 'change-up' : ($cambio_semana < 0 ? 'change-down' : 'change-neutral') ?>">
                    <i class="fas fa-arrow-<?= $cambio_semana > 0 ? 'up' : ($cambio_semana < 0 ? 'down' : 'right') ?>"></i>
                    <?= number_format(abs($cambio_semana), 1) ?>% vs semana pasada
                </div>
                <div class="mt-4 pt-4 border-t text-xs font-semibold text-gray-600">
                    <div>Promedio diario: <?= number_format($esta_semana['pedidos_semana'] / 7, 0) ?> pedidos</div>
                </div>
            </div>

            <!-- ESTE MES -->
            <div class="glass-card rounded-2xl p-8 border-l-8 border-green-500">
                <div class="stat-label text-green-600 mb-3">
                    <i class="fas fa-calendar-alt mr-1"></i>ESTE MES
                </div>
                <div class="mega-stat text-gray-900 mb-2">
                    <?= number_format($este_mes['pedidos_mes']) ?>
                </div>
                <div class="text-sm text-gray-600 font-semibold mb-3">
                    pedidos · $<?= number_format($este_mes['ventas_mes'], 0, ',', '.') ?>
                </div>
                <div class="change-badge <?= $cambio_mes > 0 ? 'change-up' : ($cambio_mes < 0 ? 'change-down' : 'change-neutral') ?>">
                    <i class="fas fa-arrow-<?= $cambio_mes > 0 ? 'up' : ($cambio_mes < 0 ? 'down' : 'right') ?>"></i>
                    <?= number_format(abs($cambio_mes), 1) ?>% vs mes pasado
                </div>
                <div class="mt-4 pt-4 border-t text-xs font-semibold text-gray-600">
                    <div>Ticket promedio: $<?= number_format($este_mes['ticket_mes'], 0, ',', '.') ?></div>
                </div>
            </div>

        </div>

        <!-- KPIs SECUNDARIOS -->
        <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-8">

            <div class="glass-card rounded-xl p-5">
                <div class="text-xs font-bold text-gray-500 uppercase mb-2">Total Período</div>
                <div class="text-3xl font-black text-gray-900"><?= number_format($periodo['total_pedidos']) ?></div>
                <div class="text-xs text-gray-600 mt-1">$<?= number_format($periodo['total_ventas'], 0, ',', '.') ?></div>
            </div>

            <div class="glass-card rounded-xl p-5">
                <div class="text-xs font-bold text-gray-500 uppercase mb-2">Ticket Promedio</div>
                <div class="text-3xl font-black text-purple-600">$<?= number_format($periodo['ticket_promedio'], 0) ?></div>
                <div class="text-xs text-gray-600 mt-1">por pedido</div>
            </div>

            <div class="glass-card rounded-xl p-5">
                <div class="text-xs font-bold text-gray-500 uppercase mb-2">Tasa Conversión</div>
                <div class="text-3xl font-black text-green-600"><?= number_format($tasa_conversion, 1) ?>%</div>
                <div class="text-xs text-gray-600 mt-1"><?= number_format($periodo['total_entregados']) ?> entregados</div>
            </div>

            <div class="glass-card rounded-xl p-5">
                <div class="text-xs font-bold text-gray-500 uppercase mb-2">Cancelados</div>
                <div class="text-3xl font-black text-red-600"><?= number_format($periodo['total_cancelados']) ?></div>
                <div class="text-xs text-gray-600 mt-1">
                    <?= $periodo['total_pedidos'] > 0 ? number_format(($periodo['total_cancelados'] / $periodo['total_pedidos']) * 100, 1) : 0 ?>% del total
                </div>
            </div>

        </div>

        <!-- Filtros -->
        <div class="glass-card rounded-xl p-4 mb-6">
            <form method="GET" class="flex flex-wrap items-center gap-3">
                <div class="flex items-center gap-2">
                    <label class="text-xs font-bold text-gray-600">DESDE</label>
                    <input type="date" name="fecha_inicio" value="<?= $fecha_inicio ?>"
                           class="px-3 py-2 border-2 border-gray-300 rounded-lg text-sm font-semibold focus:ring-2 focus:ring-purple-500 focus:border-purple-500">
                </div>
                <div class="flex items-center gap-2">
                    <label class="text-xs font-bold text-gray-600">HASTA</label>
                    <input type="date" name="fecha_fin" value="<?= $fecha_fin ?>"
                           class="px-3 py-2 border-2 border-gray-300 rounded-lg text-sm font-semibold focus:ring-2 focus:ring-purple-500 focus:border-purple-500">
                </div>
                <button type="submit" class="px-5 py-2 bg-purple-600 text-white rounded-lg hover:bg-purple-700 text-sm font-bold shadow-lg">
                    <i class="fas fa-search mr-2"></i>FILTRAR
                </button>
                <a href="?" class="px-5 py-2 bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300 text-sm font-bold">
                    <i class="fas fa-redo mr-2"></i>RESET
                </a>
            </form>
        </div>

        <!-- GRÁFICOS PRINCIPALES -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">

            <!-- Tendencia Semanal -->
            <div class="glass-card rounded-2xl p-6">
                <h3 class="text-lg font-black text-gray-900 mb-4 flex items-center">
                    <i class="fas fa-chart-area text-blue-500 mr-3 text-xl"></i>
                    TENDENCIA SEMANAL
                </h3>
                <canvas id="chartSemanal" height="300"></canvas>
            </div>

            <!-- Top 10 Productos -->
            <div class="glass-card rounded-2xl p-6">
                <h3 class="text-lg font-black text-gray-900 mb-4 flex items-center">
                    <i class="fas fa-crown text-yellow-500 mr-3 text-xl"></i>
                    TOP 10 PRODUCTOS
                </h3>
                <canvas id="chartProductos" height="300"></canvas>
            </div>

        </div>

        <!-- DATOS OPERATIVOS -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">

            <!-- Por Ubicación -->
            <div class="glass-card rounded-2xl p-6">
                <h3 class="text-lg font-black text-gray-900 mb-5 flex items-center">
                    <i class="fas fa-map-marked-alt text-red-500 mr-3 text-xl"></i>
                    RENDIMIENTO POR UBICACIÓN
                </h3>
                <div class="space-y-4">
                    <?php foreach ($por_ubicacion as $loc):
                        $porcentaje = $periodo['total_ventas'] > 0 ? ($loc['total'] / $periodo['total_ventas']) * 100 : 0;
                    ?>
                    <div class="bg-gray-50 rounded-lg p-4">
                        <div class="flex justify-between items-start mb-2">
                            <div>
                                <div class="font-black text-gray-900 text-lg"><?= htmlspecialchars($loc['ubicacion']) ?></div>
                                <div class="text-xs text-gray-600 font-semibold"><?= $loc['cantidad'] ?> pedidos · Ticket $<?= number_format($loc['promedio'], 0) ?></div>
                            </div>
                            <div class="text-right">
                                <div class="text-2xl font-black text-purple-600">$<?= number_format($loc['total'], 0, ',', '.') ?></div>
                                <div class="text-xs text-gray-600 font-bold"><?= number_format($porcentaje, 1) ?>%</div>
                            </div>
                        </div>
                        <div class="w-full bg-gray-200 rounded-full h-3 overflow-hidden">
                            <div class="bg-gradient-to-r from-purple-500 to-blue-500 h-3 rounded-full transition-all duration-500"
                                 style="width: <?= $porcentaje ?>%"></div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Por Forma de Pago -->
            <div class="glass-card rounded-2xl p-6">
                <h3 class="text-lg font-black text-gray-900 mb-5 flex items-center">
                    <i class="fas fa-wallet text-green-500 mr-3 text-xl"></i>
                    FORMAS DE PAGO
                </h3>
                <canvas id="chartPago" height="300"></canvas>
                <div class="mt-5 space-y-2">
                    <?php foreach ($por_pago as $pago):
                        $porcentaje = $periodo['total_ventas'] > 0 ? ($pago['total'] / $periodo['total_ventas']) * 100 : 0;
                    ?>
                    <div class="flex justify-between items-center text-sm">
                        <span class="font-bold text-gray-700"><?= htmlspecialchars($pago['forma_pago']) ?></span>
                        <span class="font-black text-gray-900">
                            <?= $pago['cantidad'] ?> <span class="text-gray-500">·</span>
                            $<?= number_format($pago['total'], 0, ',', '.') ?>
                            <span class="text-gray-500">(<?= number_format($porcentaje, 1) ?>%)</span>
                        </span>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>

        </div>

    </main>

    <script>
    Chart.defaults.font.family = "'Inter', sans-serif";
    Chart.defaults.font.size = 12;
    Chart.defaults.color = '#4B5563';

    // ============================================
    // GRÁFICO: Tendencia Semanal (Área)
    // ============================================
    new Chart(document.getElementById('chartSemanal'), {
        type: 'line',
        data: {
            labels: [
                <?php foreach($ventas_semanales as $v): ?>
                    'Semana <?= date('d/m', strtotime($v['inicio_semana'])) ?>',
                <?php endforeach; ?>
            ],
            datasets: [{
                label: 'Ventas ($)',
                data: [<?php foreach($ventas_semanales as $v) echo $v['total'] . ','; ?>],
                borderColor: 'rgb(139, 92, 246)',
                backgroundColor: 'rgba(139, 92, 246, 0.2)',
                tension: 0.4,
                fill: true,
                borderWidth: 3,
                pointRadius: 5,
                pointHoverRadius: 8,
                pointBackgroundColor: 'rgb(139, 92, 246)',
                pointBorderColor: '#fff',
                pointBorderWidth: 2
            }, {
                label: 'Pedidos',
                data: [<?php foreach($ventas_semanales as $v) echo $v['cantidad'] . ','; ?>],
                borderColor: 'rgb(59, 130, 246)',
                backgroundColor: 'rgba(59, 130, 246, 0.1)',
                tension: 0.4,
                fill: true,
                borderWidth: 2,
                pointRadius: 4,
                pointHoverRadius: 7,
                yAxisID: 'y1'
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            interaction: {
                mode: 'index',
                intersect: false,
            },
            plugins: {
                legend: {
                    display: true,
                    position: 'top',
                    labels: {
                        font: { weight: 'bold', size: 11 },
                        padding: 15,
                        usePointStyle: true
                    }
                }
            },
            scales: {
                y: {
                    type: 'linear',
                    display: true,
                    position: 'left',
                    beginAtZero: true,
                    ticks: {
                        callback: value => '$' + (value / 1000).toFixed(0) + 'k',
                        font: { weight: 'bold' }
                    },
                    grid: { color: 'rgba(0,0,0,0.05)' }
                },
                y1: {
                    type: 'linear',
                    display: true,
                    position: 'right',
                    beginAtZero: true,
                    grid: { drawOnChartArea: false },
                    ticks: { font: { weight: 'bold' } }
                },
                x: {
                    grid: { display: false },
                    ticks: { font: { weight: 'bold', size: 10 } }
                }
            }
        }
    });

    // ============================================
    // GRÁFICO: Top 10 Productos (Barras Horizontales)
    // ============================================
    new Chart(document.getElementById('chartProductos'), {
        type: 'bar',
        data: {
            labels: [<?php foreach($top_productos as $p) echo "'" . addslashes(substr($p['producto'], 0, 30)) . "',"; ?>],
            datasets: [{
                label: 'Cantidad',
                data: [<?php foreach($top_productos as $p) echo $p['cantidad'] . ','; ?>],
                backgroundColor: [
                    '#8b5cf6', '#6366f1', '#3b82f6', '#06b6d4', '#10b981',
                    '#84cc16', '#eab308', '#f59e0b', '#f97316', '#ef4444'
                ],
                borderRadius: 8,
                borderWidth: 0
            }]
        },
        options: {
            indexAxis: 'y',
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { display: false },
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            return 'Vendidos: ' + context.parsed.x.toLocaleString();
                        }
                    }
                }
            },
            scales: {
                x: {
                    beginAtZero: true,
                    grid: { color: 'rgba(0,0,0,0.05)' },
                    ticks: { font: { weight: 'bold' } }
                },
                y: {
                    grid: { display: false },
                    ticks: { font: { weight: 'bold', size: 10 } }
                }
            }
        }
    });

    // ============================================
    // GRÁFICO: Formas de Pago (Dona)
    // ============================================
    new Chart(document.getElementById('chartPago'), {
        type: 'doughnut',
        data: {
            labels: [<?php foreach($por_pago as $p) echo "'" . $p['forma_pago'] . "',"; ?>],
            datasets: [{
                data: [<?php foreach($por_pago as $p) echo $p['total'] . ','; ?>],
                backgroundColor: [
                    '#10b981', '#3b82f6', '#f59e0b', '#8b5cf6', '#ef4444'
                ],
                borderWidth: 3,
                borderColor: '#fff',
                hoverOffset: 15
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    display: true,
                    position: 'top',
                    labels: {
                        font: { weight: 'bold', size: 12 },
                        padding: 15,
                        usePointStyle: true,
                        pointStyle: 'circle'
                    }
                },
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            const total = context.dataset.data.reduce((a, b) => a + b, 0);
                            const percentage = ((context.parsed / total) * 100).toFixed(1);
                            return context.label + ': $' + context.parsed.toLocaleString() + ' (' + percentage + '%)';
                        }
                    }
                }
            }
        }
    });
    </script>

</body>
</html>
