<?php
// admin/modules/pedidos/delivery_simple.php - SISTEMA AVANZADO DE DELIVERY CON 3 COLUMNAS
require_once '../../config.php';
requireLogin();

$pdo = getConnection();

// DIRECCIÓN FIJA DE SALIDA (FÁBRICA)
define('DIRECCION_FABRICA', 'Cno. Gral. Belgrano 7287, B1890 Juan María Gutiérrez, Provincia de Buenos Aires');

// Filtros
$filtro_estado = $_GET['estado'] ?? '';
$fecha_desde = $_GET['fecha_desde'] ?? '';
$fecha_hasta = $_GET['fecha_hasta'] ?? '';
$busqueda = $_GET['buscar'] ?? '';

// Por defecto: pedidos de hoy que no estén entregados
if (!$fecha_desde && !$fecha_hasta && !$busqueda && !$filtro_estado) {
    $fecha_desde = date('Y-m-d');
    $fecha_hasta = date('Y-m-d');
    $filtro_estado = 'pendientes';
}

// Construir query para pedidos delivery
$sql = "SELECT p.*,
               cf.nombre as cliente_fijo_nombre,
               cf.apellido as cliente_fijo_apellido,
               TIMESTAMPDIFF(MINUTE, p.created_at, NOW()) as minutos_transcurridos
        FROM pedidos p
        LEFT JOIN clientes_fijos cf ON p.cliente_fijo_id = cf.id
        WHERE p.modalidad = 'Delivery'";

$params = [];

if ($filtro_estado && $filtro_estado === 'pendientes') {
    $sql .= " AND p.estado IN ('Pendiente', 'Preparando', 'Listo')";
} elseif ($filtro_estado && $filtro_estado !== 'Todos') {
    $sql .= " AND p.estado = ?";
    $params[] = $filtro_estado;
}

if ($fecha_desde) {
    $sql .= " AND DATE(p.created_at) >= ?";
    $params[] = $fecha_desde;
}

if ($fecha_hasta) {
    $sql .= " AND DATE(p.created_at) <= ?";
    $params[] = $fecha_hasta;
}

if ($busqueda) {
    $sql .= " AND (p.nombre LIKE ? OR p.apellido LIKE ? OR p.telefono LIKE ? OR p.direccion LIKE ? OR CAST(p.id AS CHAR) LIKE ?)";
    $busqueda_param = '%' . $busqueda . '%';
    $params = array_merge($params, array_fill(0, 5, $busqueda_param));
}

$sql .= " ORDER BY
    CASE p.estado
        WHEN 'Listo' THEN 1
        WHEN 'Preparando' THEN 2
        WHEN 'Pendiente' THEN 3
        ELSE 4
    END,
    p.created_at ASC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$pedidos = $stmt->fetchAll();

// Estadísticas
$total = count($pedidos);
$pendientes = count(array_filter($pedidos, fn($p) => $p['estado'] === 'Pendiente'));
$preparando = count(array_filter($pedidos, fn($p) => $p['estado'] === 'Preparando'));
$listos = count(array_filter($pedidos, fn($p) => $p['estado'] === 'Listo'));
$entregados = count(array_filter($pedidos, fn($p) => $p['estado'] === 'Entregado'));
$total_ventas = array_sum(array_column($pedidos, 'precio'));

// Preparar datos para el mapa (solo pedidos con dirección)
$pedidos_mapa = array_filter($pedidos, fn($p) => !empty($p['direccion']));

// Choferes disponibles (simulado - podrías tenerlo en BD)
$choferes = [
    ['id' => 1, 'nombre' => 'Juan', 'apellido' => 'Pérez', 'activo' => true, 'color' => '#3b82f6'],
    ['id' => 2, 'nombre' => 'María', 'apellido' => 'González', 'activo' => true, 'color' => '#22c55e'],
    ['id' => 3, 'nombre' => 'Carlos', 'apellido' => 'Rodríguez', 'activo' => true, 'color' => '#f59e0b'],
    ['id' => 4, 'nombre' => 'Ana', 'apellido' => 'Martínez', 'activo' => false, 'color' => '#6b7280'],
];
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>🏍️ Delivery Pro - Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />

    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap');

        * {
            font-family: 'Inter', sans-serif;
        }

        body {
            margin: 0;
            padding: 0;
            overflow: hidden;
            background: #1f2937;
        }

        .layout-container {
            display: flex;
            height: 100vh;
            flex-direction: column;
        }

        .header-bar {
            background: linear-gradient(135deg, #16a34a 0%, #15803d 100%);
            color: white;
            padding: 12px 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 4px 20px rgba(0,0,0,0.2);
            z-index: 1000;
        }

        .main-content {
            display: flex;
            flex: 1;
            overflow: hidden;
        }

        /* SIDEBAR IZQUIERDA - Opciones y Choferes */
        .sidebar-left {
            width: 280px;
            background: rgba(255, 255, 255, 0.98);
            backdrop-filter: blur(20px);
            border-right: 1px solid rgba(0,0,0,0.1);
            display: flex;
            flex-direction: column;
            overflow-y: auto;
            box-shadow: 2px 0 15px rgba(0,0,0,0.1);
        }

        /* MAPA CENTRO */
        .map-container {
            flex: 1;
            position: relative;
        }

        /* SIDEBAR DERECHA - Lista de Pedidos */
        .sidebar-right {
            width: 320px;
            background: rgba(255, 255, 255, 0.98);
            backdrop-filter: blur(20px);
            border-left: 1px solid rgba(0,0,0,0.1);
            display: flex;
            flex-direction: column;
            overflow: hidden;
            box-shadow: -2px 0 15px rgba(0,0,0,0.1);
        }

        .sidebar-header {
            padding: 16px;
            background: linear-gradient(135deg, rgba(22, 163, 74, 0.1) 0%, rgba(21, 128, 61, 0.05) 100%);
            border-bottom: 1px solid rgba(22, 163, 74, 0.2);
        }

        .sidebar-content {
            flex: 1;
            overflow-y: auto;
            padding: 12px;
        }

        .sidebar-content::-webkit-scrollbar {
            width: 8px;
        }

        .sidebar-content::-webkit-scrollbar-track {
            background: rgba(0,0,0,0.05);
        }

        .sidebar-content::-webkit-scrollbar-thumb {
            background: linear-gradient(135deg, #16a34a, #15803d);
            border-radius: 4px;
        }

        #map {
            width: 100%;
            height: 100%;
        }

        /* PEDIDO CARD - MÁS COMPACTO */
        .pedido-card {
            background: white;
            border: 2px solid #e5e7eb;
            border-radius: 12px;
            padding: 10px;
            margin-bottom: 8px;
            cursor: pointer;
            transition: all 0.2s;
            position: relative;
        }

        .pedido-card:hover {
            border-color: #16a34a;
            box-shadow: 0 4px 15px rgba(22, 163, 74, 0.2);
            transform: translateX(4px);
        }

        .pedido-card.selected {
            border-color: #16a34a;
            background: linear-gradient(135deg, #f0fdf4 0%, #dcfce7 100%);
        }

        .pedido-card.in-route {
            border-color: #f59e0b;
            background: linear-gradient(135deg, #fffbeb 0%, #fef3c7 100%);
        }

        .pedido-card.estado-Pendiente { border-left: 4px solid #eab308; }
        .pedido-card.estado-Preparando { border-left: 4px solid #3b82f6; }
        .pedido-card.estado-Listo { border-left: 4px solid #22c55e; }
        .pedido-card.estado-Entregado { border-left: 4px solid #6b7280; opacity: 0.6; }

        .urgente-badge {
            background: linear-gradient(135deg, #dc2626, #991b1b);
            color: white;
            padding: 2px 8px;
            border-radius: 12px;
            font-size: 9px;
            font-weight: 700;
            text-transform: uppercase;
            animation: pulseGlow 2s infinite;
        }

        @keyframes pulseGlow {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.8; }
        }

        .stat-box {
            background: white;
            padding: 12px;
            border-radius: 10px;
            text-align: center;
            border: 2px solid #f3f4f6;
            margin-bottom: 10px;
            transition: all 0.2s;
        }

        .stat-box:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        }

        .stat-number {
            font-size: 24px;
            font-weight: 800;
        }

        .stat-label {
            font-size: 10px;
            color: #6b7280;
            text-transform: uppercase;
            font-weight: 600;
            margin-top: 2px;
        }

        /* CHOFERES */
        .chofer-card {
            background: white;
            border: 2px solid #e5e7eb;
            border-radius: 10px;
            padding: 10px;
            margin-bottom: 8px;
            display: flex;
            align-items: center;
            gap: 10px;
            cursor: pointer;
            transition: all 0.2s;
        }

        .chofer-card:hover {
            transform: translateX(4px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        }

        .chofer-card.activo {
            border-color: #22c55e;
        }

        .chofer-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 700;
            font-size: 16px;
        }

        .chofer-info {
            flex: 1;
        }

        .chofer-nombre {
            font-weight: 600;
            font-size: 13px;
            color: #1f2937;
        }

        .chofer-estado {
            font-size: 10px;
            color: #6b7280;
        }

        .btn {
            padding: 8px 14px;
            border-radius: 8px;
            border: none;
            cursor: pointer;
            font-weight: 600;
            transition: all 0.2s;
            font-size: 12px;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }

        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(0,0,0,0.15);
        }

        .btn-primary {
            background: linear-gradient(135deg, #16a34a, #15803d);
            color: white;
        }

        .btn-secondary {
            background: linear-gradient(135deg, #6b7280, #4b5563);
            color: white;
        }

        .btn-warning {
            background: linear-gradient(135deg, #f59e0b, #d97706);
            color: white;
        }

        .btn-sm {
            padding: 4px 10px;
            font-size: 11px;
        }

        .selection-toolbar {
            display: flex;
            gap: 6px;
            margin-bottom: 12px;
            flex-wrap: wrap;
        }

        .filter-btn {
            padding: 8px 12px;
            border-radius: 8px;
            border: 2px solid #e5e7eb;
            background: white;
            cursor: pointer;
            font-size: 11px;
            font-weight: 600;
            transition: all 0.2s;
        }

        .filter-btn.active {
            background: linear-gradient(135deg, #16a34a, #15803d);
            color: white;
            border-color: #16a34a;
        }

        .filter-btn:hover:not(.active) {
            border-color: #16a34a;
            color: #16a34a;
        }

        .checkbox-container input[type="checkbox"] {
            width: 20px;
            height: 20px;
            cursor: pointer;
            accent-color: #16a34a;
        }

        .orden-badge {
            position: absolute;
            top: 8px;
            right: 8px;
            background: linear-gradient(135deg, #f59e0b, #d97706);
            color: white;
            padding: 4px 10px;
            border-radius: 12px;
            font-weight: 800;
            font-size: 11px;
            box-shadow: 0 2px 8px rgba(245, 158, 11, 0.4);
        }

        .route-panel {
            position: absolute;
            bottom: 20px;
            right: 20px;
            background: rgba(255, 255, 255, 0.98);
            backdrop-filter: blur(20px);
            border-radius: 16px;
            padding: 20px;
            min-width: 280px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.2);
            z-index: 999;
            display: none;
        }

        .route-panel.active {
            display: block;
            animation: slideInUp 0.3s ease-out;
        }

        @keyframes slideInUp {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .route-summary {
            display: flex;
            flex-direction: column;
            gap: 10px;
        }

        .route-stat {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 10px;
            background: #f9fafb;
            border-radius: 8px;
        }

        .route-stat-label {
            font-size: 12px;
            color: #6b7280;
            font-weight: 600;
        }

        .route-stat-value {
            font-size: 16px;
            font-weight: 800;
            color: #16a34a;
        }

        .section-title {
            font-size: 13px;
            font-weight: 700;
            color: #1f2937;
            margin: 16px 0 10px;
            padding-bottom: 6px;
            border-bottom: 2px solid #e5e7eb;
        }

        /* Responsive */
        @media (max-width: 1024px) {
            .sidebar-left {
                width: 240px;
            }
            .sidebar-right {
                width: 280px;
            }
        }

        @media (max-width: 768px) {
            .sidebar-left, .sidebar-right {
                display: none;
            }
            .main-content {
                flex-direction: column;
            }
        }
    </style>
</head>
<body>
    <div class="layout-container">
        <!-- Header -->
        <div class="header-bar">
            <div class="flex items-center gap-3">
                <h1 class="text-xl font-bold flex items-center gap-2">
                    <i class="fas fa-motorcycle"></i>
                    DELIVERY PRO
                </h1>
                <span class="bg-white text-green-700 px-3 py-1 rounded-full text-xs font-bold">
                    <?= $total ?> pedidos
                </span>
            </div>

            <div class="flex items-center gap-2">
                <button onclick="optimizarRuta()" class="btn btn-warning">
                    <i class="fas fa-route"></i>
                    Optimizar
                </button>
                <button onclick="centrarMapa()" class="btn btn-secondary btn-sm">
                    <i class="fas fa-crosshairs"></i>
                    Centrar
                </button>
                <a href="ver_pedidos.php" class="btn btn-secondary btn-sm">
                    <i class="fas fa-arrow-left"></i>
                    Volver
                </a>
            </div>
        </div>

        <!-- Main Content - 3 COLUMNAS -->
        <div class="main-content">

            <!-- SIDEBAR IZQUIERDA - Opciones y Choferes -->
            <div class="sidebar-left">
                <div style="padding: 16px;">

                    <!-- Estadísticas Compactas -->
                    <div class="section-title">
                        <i class="fas fa-chart-bar"></i> Estadísticas
                    </div>

                    <div class="stat-box" style="border-left: 4px solid #eab308;">
                        <div class="stat-number" style="color: #eab308;"><?= $pendientes ?></div>
                        <div class="stat-label">Pendientes</div>
                    </div>
                    <div class="stat-box" style="border-left: 4px solid #3b82f6;">
                        <div class="stat-number" style="color: #3b82f6;"><?= $preparando ?></div>
                        <div class="stat-label">Preparando</div>
                    </div>
                    <div class="stat-box" style="border-left: 4px solid #22c55e;">
                        <div class="stat-number" style="color: #22c55e;"><?= $listos ?></div>
                        <div class="stat-label">Listos</div>
                    </div>

                    <!-- Punto de Partida (FIJO) -->
                    <div class="section-title">
                        <i class="fas fa-home"></i> Punto de Partida
                    </div>
                    <div style="background: #f3f4f6; padding: 12px; border-radius: 8px; font-size: 11px; line-height: 1.4; color: #374151;">
                        <strong>Fábrica</strong><br>
                        <?= DIRECCION_FABRICA ?>
                    </div>

                    <!-- Choferes -->
                    <div class="section-title">
                        <i class="fas fa-user-tie"></i> Choferes Disponibles
                    </div>

                    <?php foreach ($choferes as $chofer): ?>
                        <div class="chofer-card <?= $chofer['activo'] ? 'activo' : '' ?>"
                             onclick="seleccionarChofer(<?= $chofer['id'] ?>)">
                            <div class="chofer-avatar" style="background: <?= $chofer['color'] ?>;">
                                <?= substr($chofer['nombre'], 0, 1) ?>
                            </div>
                            <div class="chofer-info">
                                <div class="chofer-nombre"><?= $chofer['nombre'] ?> <?= $chofer['apellido'] ?></div>
                                <div class="chofer-estado">
                                    <?= $chofer['activo'] ? '🟢 Disponible' : '⚫ No disponible' ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>

                    <!-- Filtros -->
                    <div class="section-title">
                        <i class="fas fa-filter"></i> Filtros
                    </div>
                    <div style="display: flex; flex-direction: column; gap: 6px;">
                        <button class="filter-btn <?= $filtro_estado === 'pendientes' ? 'active' : '' ?>"
                                onclick="filtrarPorEstado('pendientes')" style="width: 100%;">
                            <i class="fas fa-clock"></i> Activos
                        </button>
                        <button class="filter-btn <?= $filtro_estado === 'Listo' ? 'active' : '' ?>"
                                onclick="filtrarPorEstado('Listo')" style="width: 100%;">
                            <i class="fas fa-check-circle"></i> Listos
                        </button>
                        <button class="filter-btn <?= empty($filtro_estado) ? 'active' : '' ?>"
                                onclick="filtrarPorEstado('')" style="width: 100%;">
                            <i class="fas fa-list"></i> Todos
                        </button>
                    </div>
                </div>
            </div>

            <!-- MAPA CENTRO -->
            <div class="map-container">
                <div id="map"></div>

                <!-- Panel de Resumen de Ruta -->
                <div id="routePanel" class="route-panel">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px;">
                        <h3 style="font-size: 16px; font-weight: 700; color: #16a34a;">
                            <i class="fas fa-route"></i> Resumen
                        </h3>
                        <button onclick="cerrarPanelRuta()" style="background: none; border: none; color: #6b7280; cursor: pointer;">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>

                    <div class="route-summary">
                        <div class="route-stat">
                            <span class="route-stat-label"><i class="fas fa-box"></i> Pedidos</span>
                            <span class="route-stat-value" id="routePedidos">0</span>
                        </div>
                        <div class="route-stat">
                            <span class="route-stat-label"><i class="fas fa-road"></i> Distancia</span>
                            <span class="route-stat-value" id="routeDistancia">0 km</span>
                        </div>
                        <div class="route-stat">
                            <span class="route-stat-label"><i class="fas fa-clock"></i> Tiempo</span>
                            <span class="route-stat-value" id="routeTiempo">0 min</span>
                        </div>
                        <div class="route-stat">
                            <span class="route-stat-label"><i class="fas fa-dollar-sign"></i> Total</span>
                            <span class="route-stat-value" id="routeTotal">$0</span>
                        </div>
                    </div>

                    <button onclick="exportarRuta()" class="btn btn-primary" style="width: 100%; margin-top: 12px;">
                        <i class="fas fa-download"></i>
                        Exportar CSV
                    </button>
                </div>
            </div>

            <!-- SIDEBAR DERECHA - Lista de Pedidos -->
            <div class="sidebar-right">
                <div class="sidebar-header">
                    <div style="font-weight: 700; font-size: 14px; margin-bottom: 10px; color: #1f2937;">
                        <i class="fas fa-list"></i> Pedidos de Delivery
                    </div>

                    <!-- Selection Toolbar -->
                    <div class="selection-toolbar">
                        <button onclick="seleccionarTodos()" class="btn btn-sm btn-primary">
                            <i class="fas fa-check-double"></i> Todos
                        </button>
                        <button onclick="limpiarSeleccion()" class="btn btn-sm btn-secondary">
                            <i class="fas fa-times"></i> Limpiar
                        </button>
                        <button onclick="seleccionarListos()" class="btn btn-sm" style="background: #22c55e; color: white;">
                            <i class="fas fa-check"></i> Listos
                        </button>
                    </div>

                    <div id="contadorSeleccion" style="font-size: 11px; font-weight: 600; color: #16a34a; margin-top: 8px;">
                        <i class="fas fa-check-circle"></i> 0 seleccionados
                    </div>
                </div>

                <div class="sidebar-content" id="pedidosList">
                    <?php if (empty($pedidos)): ?>
                        <div class="text-center py-12 text-gray-500">
                            <i class="fas fa-motorcycle text-5xl mb-3 opacity-20"></i>
                            <p class="font-semibold">No hay pedidos</p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($pedidos as $pedido): ?>
                            <?php
                            $nombre_completo = $pedido['cliente_fijo_nombre'] ?
                                $pedido['cliente_fijo_nombre'] . ' ' . $pedido['cliente_fijo_apellido'] :
                                $pedido['nombre'] . ' ' . $pedido['apellido'];

                            $estado_icon = match($pedido['estado']) {
                                'Pendiente' => '⏱️',
                                'Preparando' => '🔥',
                                'Listo' => '✅',
                                'Entregado' => '📦',
                                default => '❓'
                            };

                            $urgente = ($pedido['minutos_transcurridos'] > 60 && $pedido['estado'] !== 'Entregado');
                            ?>

                            <div class="pedido-card estado-<?= $pedido['estado'] ?>"
                                 id="pedido-card-<?= $pedido['id'] ?>"
                                 onclick="focusPedido(<?= $pedido['id'] ?>)"
                                 data-lat=""
                                 data-lng=""
                                 data-precio="<?= $pedido['precio'] ?>"
                                 data-direccion="<?= htmlspecialchars($pedido['direccion']) ?>">

                                <div style="display: flex; justify-between; align-items: start; margin-bottom: 8px;">
                                    <div style="display: flex; align-items: center; gap: 8px;">
                                        <div class="checkbox-container" onclick="event.stopPropagation();">
                                            <input type="checkbox"
                                                   class="pedido-checkbox"
                                                   data-pedido-id="<?= $pedido['id'] ?>"
                                                   onchange="actualizarSeleccion()">
                                        </div>
                                        <div>
                                            <span style="font-size: 12px; font-weight: 700; color: #2563eb;">#<?= $pedido['id'] ?></span>
                                            <span style="font-size: 10px; color: #6b7280; margin-left: 4px;">
                                                <?= $pedido['minutos_transcurridos'] ?> min
                                            </span>
                                            <?php if ($urgente): ?>
                                                <span class="urgente-badge" style="margin-left: 4px;">!</span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    <div style="font-size: 18px;"><?= $estado_icon ?></div>
                                </div>

                                <div style="font-weight: 600; font-size: 13px; color: #1f2937; margin-bottom: 4px;">
                                    <?= htmlspecialchars($nombre_completo) ?>
                                </div>

                                <?php if ($pedido['direccion']): ?>
                                    <div style="font-size: 11px; font-weight: 600; color: #16a34a; margin-bottom: 4px;">
                                        <i class="fas fa-map-marker-alt" style="width: 12px;"></i>
                                        <?= htmlspecialchars(substr($pedido['direccion'], 0, 35)) ?><?= strlen($pedido['direccion']) > 35 ? '...' : '' ?>
                                    </div>
                                <?php else: ?>
                                    <div style="font-size: 10px; color: #dc2626; margin-bottom: 4px;">
                                        <i class="fas fa-exclamation-triangle"></i> Sin dirección
                                    </div>
                                <?php endif; ?>

                                <div style="font-size: 10px; color: #6b7280; margin-bottom: 6px;">
                                    <?= htmlspecialchars(substr($pedido['producto'], 0, 30)) ?><?= strlen($pedido['producto']) > 30 ? '...' : '' ?>
                                </div>

                                <div style="display: flex; justify-content: space-between; align-items: center;">
                                    <span style="font-size: 14px; font-weight: 700; color: #16a34a;">
                                        $<?= number_format($pedido['precio'], 0, ',', '.') ?>
                                    </span>
                                    <div style="display: flex; gap: 4px;">
                                        <button onclick="event.stopPropagation(); imprimir(<?= $pedido['id'] ?>)"
                                                class="btn btn-sm" style="background: #f97316; color: white; padding: 3px 8px;">
                                            <i class="fas fa-print"></i>
                                        </button>
                                        <a href="https://wa.me/54<?= preg_replace('/[^0-9]/', '', $pedido['telefono']) ?>"
                                           target="_blank"
                                           onclick="event.stopPropagation()"
                                           class="btn btn-sm" style="background: #25d366; color: white; padding: 3px 8px;">
                                            <i class="fab fa-whatsapp"></i>
                                        </a>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

        </div>
    </div>

    <!-- Leaflet JS -->
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>

    <script>
        console.log('🗺️ Iniciando Delivery Pro - 3 Columnas...');

        // DIRECCIÓN FIJA DE FÁBRICA
        const DIRECCION_FABRICA = '<?= DIRECCION_FABRICA ?>';

        // Datos
        const pedidos = <?= json_encode($pedidos_mapa) ?>;
        const choferes = <?= json_encode($choferes) ?>;
        console.log('📦 Pedidos:', pedidos.length);
        console.log('🚗 Choferes:', choferes.length);

        // Config mapa
        const MAP_CENTER = [-34.9214, -57.9544];
        let map, markers = {}, originMarker = null;
        let selectedPedidoId = null;
        let selectedChoferId = null;
        let rutaActual = [];

        // Inicializar mapa
        function initMap() {
            console.log('🗺️ Inicializando mapa...');

            map = L.map('map').setView(MAP_CENTER, 13);

            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                attribution: '© OpenStreetMap contributors',
                maxZoom: 19
            }).addTo(map);

            console.log('✅ Mapa listo');

            geocodificarPedidos();
        }

        // Geocodificar
        async function geocodificarPedidos() {
            console.log('🔍 Geocodificando...');

            for (const pedido of pedidos) {
                if (!pedido.direccion) continue;

                try {
                    const coords = await geocodificar(pedido.direccion);

                    if (coords) {
                        agregarMarcador(pedido, coords.lat, coords.lon);

                        const card = document.getElementById(`pedido-card-${pedido.id}`);
                        if (card) {
                            card.dataset.lat = coords.lat;
                            card.dataset.lng = coords.lon;
                        }
                    }

                    await new Promise(resolve => setTimeout(resolve, 1000));

                } catch (error) {
                    console.error(`❌ Error #${pedido.id}:`, error);
                }
            }

            console.log('✅ Geocodificación completa');

            if (Object.keys(markers).length > 0) {
                const group = L.featureGroup(Object.values(markers));
                map.fitBounds(group.getBounds().pad(0.1));
            }
        }

        async function geocodificar(direccion) {
            const query = `${direccion}, La Plata, Buenos Aires, Argentina`;
            const url = `https://nominatim.openstreetmap.org/search?format=json&q=${encodeURIComponent(query)}&limit=1`;

            try {
                const response = await fetch(url, {
                    headers: { 'User-Agent': 'SantaCatalinaDelivery/1.0' }
                });

                const data = await response.json();

                if (data && data.length > 0) {
                    return {
                        lat: parseFloat(data[0].lat),
                        lon: parseFloat(data[0].lon)
                    };
                }

                return null;
            } catch (error) {
                console.error('Error geocodificación:', error);
                return null;
            }
        }

        function agregarMarcador(pedido, lat, lng) {
            const nombre = pedido.cliente_fijo_nombre ?
                `${pedido.cliente_fijo_nombre} ${pedido.cliente_fijo_apellido}` :
                `${pedido.nombre} ${pedido.apellido}`;

            const color = {
                'Pendiente': '#eab308',
                'Preparando': '#3b82f6',
                'Listo': '#22c55e',
                'Entregado': '#6b7280'
            }[pedido.estado] || '#6b7280';

            const icon = L.divIcon({
                className: 'custom-marker',
                html: `<div style="
                    background-color: ${color};
                    width: 30px;
                    height: 30px;
                    border-radius: 50% 50% 50% 0;
                    border: 3px solid white;
                    transform: rotate(-45deg);
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    box-shadow: 0 4px 12px rgba(0,0,0,0.3);
                ">
                    <span style="
                        transform: rotate(45deg);
                        color: white;
                        font-weight: 700;
                        font-size: 11px;
                    ">${pedido.id}</span>
                </div>`,
                iconSize: [30, 30],
                iconAnchor: [15, 30]
            });

            const marker = L.marker([lat, lng], { icon }).addTo(map);

            const popupContent = `
                <div style="font-family: Inter, sans-serif; min-width: 200px;">
                    <div style="font-size: 14px; font-weight: 700; margin-bottom: 8px; color: #16a34a;">
                        Pedido #${pedido.id}
                    </div>
                    <div style="font-size: 11px; line-height: 1.6;">
                        <strong>Cliente:</strong> ${nombre}<br>
                        <strong>Dirección:</strong> ${pedido.direccion}<br>
                        <strong>Precio:</strong> <span style="color: #16a34a; font-weight: 700;">$${pedido.precio.toLocaleString()}</span>
                    </div>
                    <div style="margin-top: 8px; display: flex; gap: 4px;">
                        <button onclick="imprimir(${pedido.id})" class="btn btn-sm" style="background: #f97316; color: white; padding: 4px 10px; font-size: 10px;">
                            <i class="fas fa-print"></i>
                        </button>
                        <button onclick="abrirGoogleMaps('${pedido.direccion}')" class="btn btn-sm btn-primary" style="padding: 4px 10px; font-size: 10px;">
                            <i class="fas fa-directions"></i>
                        </button>
                    </div>
                </div>
            `;

            marker.bindPopup(popupContent, { maxWidth: 250 });

            marker.on('click', () => {
                selectPedido(pedido.id);
            });

            markers[pedido.id] = marker;
        }

        function selectPedido(pedidoId) {
            document.querySelectorAll('.pedido-card').forEach(card => {
                card.classList.remove('selected');
            });

            const card = document.getElementById(`pedido-card-${pedidoId}`);
            if (card) {
                card.classList.add('selected');
                card.scrollIntoView({ behavior: 'smooth', block: 'center' });
            }

            selectedPedidoId = pedidoId;
        }

        function focusPedido(pedidoId) {
            selectPedido(pedidoId);

            const marker = markers[pedidoId];
            if (marker) {
                map.setView(marker.getLatLng(), 16);
                marker.openPopup();
            }
        }

        function centrarMapa() {
            if (Object.keys(markers).length > 0) {
                const group = L.featureGroup(Object.values(markers));
                map.fitBounds(group.getBounds().pad(0.1));
            } else {
                map.setView(MAP_CENTER, 13);
            }
        }

        function seleccionarTodos() {
            document.querySelectorAll('.pedido-checkbox').forEach(cb => cb.checked = true);
            actualizarSeleccion();
        }

        function limpiarSeleccion() {
            document.querySelectorAll('.pedido-checkbox').forEach(cb => cb.checked = false);
            actualizarSeleccion();
        }

        function seleccionarListos() {
            document.querySelectorAll('.pedido-checkbox').forEach(cb => {
                const card = document.getElementById(`pedido-card-${cb.dataset.pedidoId}`);
                cb.checked = card && card.classList.contains('estado-Listo');
            });
            actualizarSeleccion();
        }

        function actualizarSeleccion() {
            const count = document.querySelectorAll('.pedido-checkbox:checked').length;
            document.getElementById('contadorSeleccion').innerHTML = `
                <i class="fas fa-check-circle"></i> ${count} seleccionados
            `;
        }

        function seleccionarChofer(choferId) {
            selectedChoferId = choferId;
            console.log('🚗 Chofer seleccionado:', choferId);
            // Aquí podrías resaltar el chofer seleccionado
        }

        // OPTIMIZAR RUTA - FIX: Tomar TODOS los seleccionados
        async function optimizarRuta() {
            console.log('🔄 Optimizando ruta con TODOS los seleccionados...');

            // Obtener TODOS los pedidos seleccionados
            const checkboxes = document.querySelectorAll('.pedido-checkbox:checked');
            console.log('📋 Checkboxes marcados:', checkboxes.length);

            const seleccionados = [];
            checkboxes.forEach(cb => {
                const pedidoId = cb.dataset.pedidoId;
                const card = document.getElementById(`pedido-card-${pedidoId}`);

                if (card && card.dataset.lat && card.dataset.lng) {
                    const pedido = pedidos.find(p => p.id == pedidoId);
                    if (pedido) {
                        seleccionados.push({
                            ...pedido,
                            lat: parseFloat(card.dataset.lat),
                            lng: parseFloat(card.dataset.lng)
                        });
                    }
                }
            });

            console.log('✅ Pedidos seleccionados con coords:', seleccionados.length);

            if (seleccionados.length === 0) {
                alert('⚠️ Selecciona al menos un pedido');
                return;
            }

            // Geocodificar origen (FÁBRICA FIJA)
            console.log('📍 Geocodificando fábrica...');
            const origenCoords = await geocodificar(DIRECCION_FABRICA);

            if (origenCoords) {
                // Marcador de origen
                if (originMarker) {
                    map.removeLayer(originMarker);
                }

                const originIcon = L.divIcon({
                    className: 'origin-marker',
                    html: `<div style="
                        background: linear-gradient(135deg, #16a34a, #15803d);
                        width: 40px;
                        height: 40px;
                        border-radius: 50%;
                        border: 4px solid white;
                        display: flex;
                        align-items: center;
                        justify-content: center;
                        box-shadow: 0 4px 15px rgba(22, 163, 74, 0.5);
                    ">
                        <i class="fas fa-home" style="color: white; font-size: 18px;"></i>
                    </div>`,
                    iconSize: [40, 40],
                    iconAnchor: [20, 20]
                });

                originMarker = L.marker([origenCoords.lat, origenCoords.lon], { icon: originIcon }).addTo(map);
                originMarker.bindPopup('<strong>🏭 Fábrica</strong><br>' + DIRECCION_FABRICA);

                // Agregar origen al inicio
                seleccionados.unshift({
                    id: 'origen',
                    lat: origenCoords.lat,
                    lng: origenCoords.lon,
                    direccion: DIRECCION_FABRICA
                });
            }

            // Algoritmo Nearest Neighbor
            const rutaOptimizada = [];
            let actual = seleccionados[0];
            let disponibles = [...seleccionados.slice(1)];

            rutaOptimizada.push(actual);

            while (disponibles.length > 0) {
                let minDist = Infinity;
                let mejorIdx = 0;

                disponibles.forEach((pedido, idx) => {
                    const dist = calcularDistancia(
                        actual.lat, actual.lng,
                        pedido.lat, pedido.lng
                    );

                    if (dist < minDist) {
                        minDist = dist;
                        mejorIdx = idx;
                    }
                });

                actual = disponibles[mejorIdx];
                rutaOptimizada.push(actual);
                disponibles.splice(mejorIdx, 1);
            }

            console.log('✅ Ruta optimizada:', rutaOptimizada.map(p => p.id));

            rutaActual = rutaOptimizada.filter(p => p.id !== 'origen');

            // Reorganizar sidebar
            document.querySelectorAll('.orden-badge').forEach(b => b.remove());
            document.querySelectorAll('.pedido-card').forEach(c => c.classList.remove('in-route'));

            rutaOptimizada.forEach((pedido, idx) => {
                if (pedido.id === 'origen') return;

                const card = document.getElementById(`pedido-card-${pedido.id}`);
                if (card) {
                    card.style.order = idx;
                    card.classList.add('in-route');

                    const badge = document.createElement('span');
                    badge.className = 'orden-badge';
                    badge.textContent = `#${idx}`;
                    card.style.position = 'relative';
                    card.appendChild(badge);
                }
            });

            // Dibujar ruta
            const coords = rutaOptimizada.map(p => [p.lat, p.lng]);

            if (window.rutaPolyline) {
                map.removeLayer(window.rutaPolyline);
            }

            window.rutaPolyline = L.polyline(coords, {
                color: '#f59e0b',
                weight: 5,
                opacity: 0.8,
                dashArray: '15, 10',
                lineJoin: 'round'
            }).addTo(map);

            // Estadísticas
            const distanciaTotal = calcularDistanciaTotal(coords);
            const tiempoEstimado = Math.round(distanciaTotal / 30 * 60);
            const totalPedidos = rutaActual.length;
            const totalVentas = rutaActual.reduce((sum, p) => {
                const card = document.getElementById(`pedido-card-${p.id}`);
                return sum + (card ? parseFloat(card.dataset.precio) : 0);
            }, 0);

            // Mostrar panel
            document.getElementById('routePanel').classList.add('active');
            document.getElementById('routePedidos').textContent = totalPedidos;
            document.getElementById('routeDistancia').textContent = distanciaTotal.toFixed(1) + ' km';
            document.getElementById('routeTiempo').textContent = tiempoEstimado + ' min';
            document.getElementById('routeTotal').textContent = '$' + totalVentas.toLocaleString();

            map.fitBounds(window.rutaPolyline.getBounds().pad(0.1));

            alert(`✅ Ruta optimizada\n📦 ${totalPedidos} pedidos\n📏 ${distanciaTotal.toFixed(1)} km - ⏱️ ${tiempoEstimado} min`);
        }

        function calcularDistancia(lat1, lng1, lat2, lng2) {
            const R = 6371;
            const dLat = (lat2 - lat1) * Math.PI / 180;
            const dLng = (lng2 - lng1) * Math.PI / 180;
            const a =
                Math.sin(dLat/2) * Math.sin(dLat/2) +
                Math.cos(lat1 * Math.PI / 180) * Math.cos(lat2 * Math.PI / 180) *
                Math.sin(dLng/2) * Math.sin(dLng/2);
            const c = 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1-a));
            return R * c;
        }

        function calcularDistanciaTotal(coords) {
            let total = 0;
            for (let i = 0; i < coords.length - 1; i++) {
                total += calcularDistancia(
                    coords[i][0], coords[i][1],
                    coords[i+1][0], coords[i+1][1]
                );
            }
            return total;
        }

        function cerrarPanelRuta() {
            document.getElementById('routePanel').classList.remove('active');
        }

        function exportarRuta() {
            if (rutaActual.length === 0) {
                alert('⚠️ No hay ruta');
                return;
            }

            const data = rutaActual.map((p, idx) => ({
                orden: idx + 1,
                pedido_id: p.id,
                nombre: p.nombre + ' ' + p.apellido,
                telefono: p.telefono,
                direccion: p.direccion,
                producto: p.producto,
                precio: p.precio,
                coordenadas: `${p.lat}, ${p.lng}`
            }));

            const csv = [
                ['Orden', 'Pedido', 'Cliente', 'Teléfono', 'Dirección', 'Producto', 'Precio', 'Coords'],
                ...data.map(d => [d.orden, d.pedido_id, d.nombre, d.telefono, d.direccion, d.producto, d.precio, d.coordenadas])
            ].map(row => row.join(',')).join('\n');

            const blob = new Blob([csv], { type: 'text/csv' });
            const url = window.URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = `ruta_${new Date().toISOString().split('T')[0]}.csv`;
            a.click();
            window.URL.revokeObjectURL(url);

            alert('✅ Ruta exportada');
        }

        function filtrarPorEstado(estado) {
            const params = new URLSearchParams(window.location.search);
            if (estado === 'pendientes') {
                params.set('estado', 'pendientes');
            } else if (estado) {
                params.set('estado', estado);
            } else {
                params.delete('estado');
            }
            window.location.search = params.toString();
        }

        function imprimir(pedidoId) {
            const url = `../impresion/comanda_simple.php?pedido=${pedidoId}`;
            window.open(url, '_blank', 'width=320,height=500');
        }

        function abrirGoogleMaps(direccion) {
            const url = `https://www.google.com/maps/dir/?api=1&destination=${encodeURIComponent(direccion + ', La Plata, Buenos Aires')}`;
            window.open(url, '_blank');
        }

        // Inicializar
        document.addEventListener('DOMContentLoaded', () => {
            initMap();
            actualizarSeleccion();
        });

        console.log('✅ Delivery Pro cargado');
    </script>
</body>
</html>
