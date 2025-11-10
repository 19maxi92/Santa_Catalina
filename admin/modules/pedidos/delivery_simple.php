<?php
// admin/modules/pedidos/delivery_simple.php - SISTEMA AVANZADO DE DELIVERY CON MAPA INTERACTIVO
require_once '../../config.php';
requireLogin();

$pdo = getConnection();

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
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }

        .layout-container {
            display: flex;
            height: 100vh;
            flex-direction: column;
        }

        .header-bar {
            background: linear-gradient(135deg, #16a34a 0%, #15803d 100%);
            color: white;
            padding: 16px 24px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 4px 20px rgba(0,0,0,0.15);
            z-index: 1000;
            backdrop-filter: blur(10px);
        }

        .main-content {
            display: flex;
            flex: 1;
            overflow: hidden;
        }

        .sidebar {
            width: 420px;
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            border-right: 1px solid rgba(255, 255, 255, 0.3);
            display: flex;
            flex-direction: column;
            overflow: hidden;
            box-shadow: 4px 0 30px rgba(0,0,0,0.1);
        }

        .sidebar-header {
            padding: 20px;
            background: linear-gradient(135deg, rgba(22, 163, 74, 0.1) 0%, rgba(21, 128, 61, 0.05) 100%);
            border-bottom: 1px solid rgba(22, 163, 74, 0.2);
        }

        .sidebar-content {
            flex: 1;
            overflow-y: auto;
            padding: 16px;
        }

        .sidebar-content::-webkit-scrollbar {
            width: 10px;
        }

        .sidebar-content::-webkit-scrollbar-track {
            background: rgba(0,0,0,0.05);
            border-radius: 10px;
        }

        .sidebar-content::-webkit-scrollbar-thumb {
            background: linear-gradient(135deg, #16a34a, #15803d);
            border-radius: 10px;
        }

        .map-container {
            flex: 1;
            position: relative;
        }

        #map {
            width: 100%;
            height: 100%;
        }

        .pedido-card {
            background: white;
            border: 2px solid #e5e7eb;
            border-radius: 16px;
            padding: 16px;
            margin-bottom: 12px;
            cursor: pointer;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            position: relative;
            overflow: hidden;
        }

        .pedido-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: linear-gradient(135deg, transparent 0%, rgba(22, 163, 74, 0.05) 100%);
            opacity: 0;
            transition: opacity 0.3s;
        }

        .pedido-card:hover::before {
            opacity: 1;
        }

        .pedido-card:hover {
            border-color: #16a34a;
            box-shadow: 0 8px 30px rgba(22, 163, 74, 0.25);
            transform: translateX(8px) translateY(-2px);
        }

        .pedido-card.selected {
            border-color: #16a34a;
            background: linear-gradient(135deg, #f0fdf4 0%, #dcfce7 100%);
            box-shadow: 0 8px 30px rgba(22, 163, 74, 0.3);
        }

        .pedido-card.in-route {
            border-color: #f59e0b;
            background: linear-gradient(135deg, #fffbeb 0%, #fef3c7 100%);
        }

        .pedido-card.estado-Pendiente {
            border-left: 5px solid #eab308;
        }

        .pedido-card.estado-Preparando {
            border-left: 5px solid #3b82f6;
        }

        .pedido-card.estado-Listo {
            border-left: 5px solid #22c55e;
        }

        .pedido-card.estado-Entregado {
            border-left: 5px solid #6b7280;
            opacity: 0.6;
        }

        .urgente-badge {
            background: linear-gradient(135deg, #dc2626, #991b1b);
            color: white;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 10px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            animation: pulseGlow 2s infinite;
            box-shadow: 0 4px 15px rgba(220, 38, 38, 0.4);
        }

        @keyframes pulseGlow {
            0%, 100% {
                opacity: 1;
                box-shadow: 0 4px 15px rgba(220, 38, 38, 0.4);
            }
            50% {
                opacity: 0.8;
                box-shadow: 0 4px 25px rgba(220, 38, 38, 0.6);
            }
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 12px;
            margin-bottom: 20px;
        }

        .stat-box {
            background: white;
            padding: 16px;
            border-radius: 12px;
            text-align: center;
            border: 2px solid #f3f4f6;
            transition: all 0.3s;
            cursor: pointer;
        }

        .stat-box:hover {
            transform: translateY(-4px);
            box-shadow: 0 8px 20px rgba(0,0,0,0.1);
        }

        .stat-number {
            font-size: 28px;
            font-weight: 800;
            line-height: 1;
        }

        .stat-label {
            font-size: 11px;
            color: #6b7280;
            text-transform: uppercase;
            font-weight: 600;
            letter-spacing: 0.5px;
            margin-top: 4px;
        }

        .btn {
            padding: 10px 18px;
            border-radius: 10px;
            border: none;
            cursor: pointer;
            font-weight: 600;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            font-size: 13px;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }

        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(0,0,0,0.2);
        }

        .btn:active {
            transform: translateY(0);
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
            padding: 6px 12px;
            font-size: 12px;
        }

        .origin-selector {
            background: white;
            border-radius: 12px;
            padding: 16px;
            margin-bottom: 20px;
            border: 2px solid #e5e7eb;
        }

        .origin-selector input {
            width: 100%;
            padding: 12px 16px;
            border: 2px solid #e5e7eb;
            border-radius: 10px;
            font-size: 14px;
            transition: all 0.3s;
        }

        .origin-selector input:focus {
            outline: none;
            border-color: #16a34a;
            box-shadow: 0 0 0 3px rgba(22, 163, 74, 0.1);
        }

        .origin-suggestions {
            display: flex;
            gap: 8px;
            margin-top: 10px;
            flex-wrap: wrap;
        }

        .origin-suggestion {
            padding: 6px 14px;
            background: #f3f4f6;
            border-radius: 20px;
            font-size: 12px;
            cursor: pointer;
            transition: all 0.2s;
            font-weight: 500;
        }

        .origin-suggestion:hover {
            background: #16a34a;
            color: white;
            transform: scale(1.05);
        }

        .route-panel {
            position: absolute;
            top: 20px;
            right: 20px;
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            border-radius: 16px;
            padding: 20px;
            min-width: 300px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.2);
            z-index: 999;
            display: none;
        }

        .route-panel.active {
            display: block;
            animation: slideInRight 0.3s ease-out;
        }

        @keyframes slideInRight {
            from {
                opacity: 0;
                transform: translateX(20px);
            }
            to {
                opacity: 1;
                transform: translateX(0);
            }
        }

        .route-summary {
            display: flex;
            flex-direction: column;
            gap: 12px;
        }

        .route-stat {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 12px;
            background: #f9fafb;
            border-radius: 10px;
        }

        .route-stat-label {
            font-size: 13px;
            color: #6b7280;
            font-weight: 600;
        }

        .route-stat-value {
            font-size: 18px;
            font-weight: 800;
            color: #16a34a;
        }

        .checkbox-container {
            width: 24px;
            height: 24px;
            position: relative;
            cursor: pointer;
        }

        .checkbox-container input[type="checkbox"] {
            width: 24px;
            height: 24px;
            cursor: pointer;
            accent-color: #16a34a;
        }

        .orden-badge {
            position: absolute;
            top: 12px;
            right: 12px;
            background: linear-gradient(135deg, #f59e0b, #d97706);
            color: white;
            padding: 6px 12px;
            border-radius: 20px;
            font-weight: 800;
            font-size: 12px;
            box-shadow: 0 4px 15px rgba(245, 158, 11, 0.4);
            animation: bounce 0.5s ease-out;
        }

        @keyframes bounce {
            0%, 100% { transform: translateY(0); }
            50% { transform: translateY(-10px); }
        }

        .filters-container {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            margin-bottom: 16px;
        }

        .filter-btn {
            padding: 10px 16px;
            border-radius: 10px;
            border: 2px solid #e5e7eb;
            background: white;
            cursor: pointer;
            font-size: 13px;
            font-weight: 600;
            transition: all 0.3s;
        }

        .filter-btn.active {
            background: linear-gradient(135deg, #16a34a, #15803d);
            color: white;
            border-color: #16a34a;
            box-shadow: 0 4px 15px rgba(22, 163, 74, 0.3);
        }

        .filter-btn:hover:not(.active) {
            border-color: #16a34a;
            color: #16a34a;
            transform: translateY(-2px);
        }

        .selection-toolbar {
            display: flex;
            gap: 8px;
            margin-bottom: 16px;
        }

        .leaflet-popup-content {
            min-width: 280px;
        }

        .popup-content {
            font-family: 'Inter', sans-serif;
        }

        .popup-header {
            font-size: 18px;
            font-weight: 700;
            margin-bottom: 12px;
            color: #16a34a;
        }

        .popup-info {
            font-size: 13px;
            line-height: 1.8;
            color: #374151;
        }

        .popup-actions {
            margin-top: 12px;
            display: flex;
            gap: 6px;
            flex-wrap: wrap;
        }

        /* Responsive */
        @media (max-width: 768px) {
            .sidebar {
                width: 100%;
                max-width: 100%;
                position: absolute;
                z-index: 999;
                height: 45%;
                bottom: 0;
                border-right: none;
                border-top: 1px solid rgba(255, 255, 255, 0.3);
            }

            .map-container {
                height: 55%;
            }

            .route-panel {
                top: 10px;
                right: 10px;
                min-width: 250px;
                padding: 15px;
            }
        }

        /* Animaciones adicionales */
        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }

        .fade-in {
            animation: fadeIn 0.3s ease-out;
        }

        /* Mejoras en marcadores */
        .custom-marker {
            animation: markerPop 0.3s ease-out;
        }

        @keyframes markerPop {
            0% { transform: scale(0) rotate(-45deg); }
            50% { transform: scale(1.2) rotate(-45deg); }
            100% { transform: scale(1) rotate(-45deg); }
        }
    </style>
</head>
<body>
    <div class="layout-container">
        <!-- Header -->
        <div class="header-bar">
            <div class="flex items-center gap-4">
                <h1 class="text-2xl font-bold flex items-center gap-3">
                    <i class="fas fa-motorcycle"></i>
                    DELIVERY PRO
                </h1>
                <span class="bg-white text-green-700 px-4 py-1.5 rounded-full text-sm font-bold shadow-lg">
                    <?= $total ?> pedidos
                </span>
                <span class="text-green-100 text-sm font-medium">
                    <i class="fas fa-map-marked-alt"></i>
                    OpenStreetMap
                </span>
            </div>

            <div class="flex items-center gap-2">
                <button onclick="optimizarRuta()" class="btn btn-warning">
                    <i class="fas fa-route"></i>
                    Optimizar Ruta
                </button>
                <button onclick="centrarMapa()" class="btn btn-secondary">
                    <i class="fas fa-crosshairs"></i>
                    Centrar
                </button>
                <button onclick="imprimirTodos()" class="btn btn-sm" style="background: linear-gradient(135deg, #f97316, #ea580c); color: white;">
                    <i class="fas fa-print"></i>
                    Imprimir
                </button>
                <a href="ver_pedidos.php" class="btn btn-secondary">
                    <i class="fas fa-arrow-left"></i>
                    Volver
                </a>
            </div>
        </div>

        <!-- Main Content -->
        <div class="main-content">
            <!-- Sidebar -->
            <div class="sidebar">
                <div class="sidebar-header">
                    <!-- Stats -->
                    <div class="stats-grid">
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
                        <div class="stat-box" style="border-left: 4px solid #16a34a;">
                            <div class="stat-number" style="color: #16a34a;">$<?= number_format($total_ventas/1000, 1) ?>K</div>
                            <div class="stat-label">Total</div>
                        </div>
                    </div>

                    <!-- Selector de Origen -->
                    <div class="origin-selector">
                        <label style="display: block; font-weight: 600; margin-bottom: 8px; color: #374151; font-size: 13px;">
                            <i class="fas fa-map-pin"></i> Punto de Partida
                        </label>
                        <input type="text"
                               id="origenInput"
                               placeholder="Escribe la dirección de salida..."
                               style="margin-bottom: 0;">
                        <div class="origin-suggestions">
                            <span class="origin-suggestion" onclick="setOrigen('Calle 7 1234, La Plata')">
                                <i class="fas fa-store"></i> Local 1
                            </span>
                            <span class="origin-suggestion" onclick="setOrigen('Calle 50 567, La Plata')">
                                <i class="fas fa-industry"></i> Fábrica
                            </span>
                            <span class="origin-suggestion" onclick="setOrigen('')">
                                <i class="fas fa-times"></i> Limpiar
                            </span>
                        </div>
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
                            <i class="fas fa-check-circle"></i> Solo Listos
                        </button>
                        <span id="contadorSeleccion" style="margin-left: auto; font-weight: 600; color: #16a34a; font-size: 13px; display: flex; align-items: center;">
                            0 seleccionados
                        </span>
                    </div>

                    <!-- Filters -->
                    <div class="filters-container">
                        <button class="filter-btn <?= $filtro_estado === 'pendientes' ? 'active' : '' ?>"
                                onclick="filtrarPorEstado('pendientes')">
                            <i class="fas fa-clock"></i> Activos
                        </button>
                        <button class="filter-btn <?= empty($filtro_estado) ? 'active' : '' ?>"
                                onclick="filtrarPorEstado('')">
                            <i class="fas fa-list"></i> Todos
                        </button>
                        <button class="filter-btn <?= $filtro_estado === 'Listo' ? 'active' : '' ?>"
                                onclick="filtrarPorEstado('Listo')">
                            <i class="fas fa-check-circle"></i> Listos
                        </button>
                    </div>
                </div>

                <div class="sidebar-content" id="pedidosList">
                    <?php if (empty($pedidos)): ?>
                        <div class="text-center py-12 text-gray-500">
                            <i class="fas fa-motorcycle text-6xl mb-4 opacity-20"></i>
                            <p class="font-semibold text-lg">No hay pedidos de delivery</p>
                            <p class="text-sm">Para la fecha seleccionada</p>
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

                            <div class="pedido-card estado-<?= $pedido['estado'] ?> fade-in"
                                 id="pedido-card-<?= $pedido['id'] ?>"
                                 onclick="focusPedido(<?= $pedido['id'] ?>)"
                                 data-lat=""
                                 data-lng=""
                                 data-precio="<?= $pedido['precio'] ?>"
                                 data-direccion="<?= htmlspecialchars($pedido['direccion']) ?>">

                                <div class="flex justify-between items-start mb-3">
                                    <div class="flex items-center gap-3">
                                        <div class="checkbox-container" onclick="event.stopPropagation();">
                                            <input type="checkbox"
                                                   class="pedido-checkbox"
                                                   data-pedido-id="<?= $pedido['id'] ?>"
                                                   onchange="actualizarSeleccion()">
                                        </div>
                                        <div>
                                            <span class="text-sm font-bold text-blue-600">#<?= $pedido['id'] ?></span>
                                            <span class="text-xs text-gray-500 ml-2">
                                                <?= $pedido['minutos_transcurridos'] ?> min
                                            </span>
                                            <?php if ($urgente): ?>
                                                <span class="urgente-badge ml-2">Urgente</span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    <div class="text-2xl"><?= $estado_icon ?></div>
                                </div>

                                <div class="font-bold text-base text-gray-800 mb-2">
                                    <?= htmlspecialchars($nombre_completo) ?>
                                </div>

                                <div class="text-xs text-gray-600 mb-2">
                                    <i class="fas fa-phone w-4"></i>
                                    <?= htmlspecialchars($pedido['telefono']) ?>
                                </div>

                                <?php if ($pedido['direccion']): ?>
                                    <div class="text-sm font-semibold text-green-700 mb-2">
                                        <i class="fas fa-map-marker-alt w-4"></i>
                                        <?= htmlspecialchars($pedido['direccion']) ?>
                                    </div>
                                <?php else: ?>
                                    <div class="text-xs text-red-500 mb-2 font-semibold">
                                        <i class="fas fa-exclamation-triangle"></i>
                                        Sin dirección
                                    </div>
                                <?php endif; ?>

                                <div class="text-xs text-gray-700 mb-3">
                                    <?= htmlspecialchars(substr($pedido['producto'], 0, 45)) ?><?= strlen($pedido['producto']) > 45 ? '...' : '' ?>
                                </div>

                                <div class="flex justify-between items-center">
                                    <span class="text-lg font-bold text-green-600">
                                        $<?= number_format($pedido['precio'], 0, ',', '.') ?>
                                    </span>
                                    <div class="flex gap-2">
                                        <button onclick="event.stopPropagation(); imprimir(<?= $pedido['id'] ?>)"
                                                class="btn btn-sm" style="background: linear-gradient(135deg, #f97316, #ea580c); color: white;"
                                                title="Imprimir">
                                            <i class="fas fa-print"></i>
                                        </button>
                                        <a href="https://wa.me/54<?= preg_replace('/[^0-9]/', '', $pedido['telefono']) ?>?text=Hola%20<?= urlencode($nombre_completo) ?>,%20tu%20pedido%20#<?= $pedido['id'] ?>%20está%20<?= urlencode(strtolower($pedido['estado'])) ?>"
                                           target="_blank"
                                           onclick="event.stopPropagation()"
                                           class="btn btn-sm" style="background: #25d366; color: white;"
                                           title="WhatsApp">
                                            <i class="fab fa-whatsapp"></i>
                                        </a>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Map Container -->
            <div class="map-container">
                <div id="map"></div>

                <!-- Panel de Resumen de Ruta -->
                <div id="routePanel" class="route-panel">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px;">
                        <h3 style="font-size: 18px; font-weight: 700; color: #16a34a;">
                            <i class="fas fa-route"></i> Resumen de Ruta
                        </h3>
                        <button onclick="cerrarPanelRuta()" style="background: none; border: none; color: #6b7280; cursor: pointer; font-size: 18px;">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>

                    <div class="route-summary">
                        <div class="route-stat">
                            <span class="route-stat-label">
                                <i class="fas fa-box"></i> Pedidos
                            </span>
                            <span class="route-stat-value" id="routePedidos">0</span>
                        </div>
                        <div class="route-stat">
                            <span class="route-stat-label">
                                <i class="fas fa-road"></i> Distancia
                            </span>
                            <span class="route-stat-value" id="routeDistancia">0 km</span>
                        </div>
                        <div class="route-stat">
                            <span class="route-stat-label">
                                <i class="fas fa-clock"></i> Tiempo Est.
                            </span>
                            <span class="route-stat-value" id="routeTiempo">0 min</span>
                        </div>
                        <div class="route-stat">
                            <span class="route-stat-label">
                                <i class="fas fa-dollar-sign"></i> Total
                            </span>
                            <span class="route-stat-value" id="routeTotal">$0</span>
                        </div>
                    </div>

                    <button onclick="exportarRuta()" class="btn btn-primary" style="width: 100%; margin-top: 16px;">
                        <i class="fas fa-download"></i>
                        Exportar Ruta
                    </button>
                </div>

                <!-- Botón flotante para toggle sidebar en mobile -->
                <button onclick="toggleSidebar()"
                        class="btn btn-primary"
                        style="position: absolute; bottom: 20px; left: 20px; z-index: 999; display: none;"
                        id="toggleSidebarBtn">
                    <i class="fas fa-list"></i>
                    Pedidos
                </button>
            </div>
        </div>
    </div>

    <!-- Leaflet JS -->
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>

    <script>
        console.log('🗺️ Iniciando Delivery Pro...');

        // Datos de pedidos
        const pedidos = <?= json_encode($pedidos_mapa) ?>;
        console.log('📦 Pedidos cargados:', pedidos.length);

        // Configuración del mapa
        const MAP_CENTER = [-34.9214, -57.9544]; // La Plata, Buenos Aires
        let map, markers = {}, originMarker = null;
        let selectedPedidoId = null;
        let rutaActual = [];
        let origenCoords = null;

        // Inicializar mapa
        function initMap() {
            console.log('🗺️ Inicializando mapa Leaflet + OSM...');

            map = L.map('map').setView(MAP_CENTER, 13);

            // Agregar capa de OpenStreetMap
            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                attribution: '© OpenStreetMap contributors',
                maxZoom: 19
            }).addTo(map);

            console.log('✅ Mapa inicializado');

            // Geocodificar y agregar marcadores
            geocodificarPedidos();
        }

        // Geocodificar direcciones usando Nominatim (OSM)
        async function geocodificarPedidos() {
            console.log('🔍 Geocodificando direcciones...');

            let procesados = 0;
            const total = pedidos.length;

            for (const pedido of pedidos) {
                if (!pedido.direccion) continue;

                try {
                    const coords = await geocodificar(pedido.direccion);

                    if (coords) {
                        agregarMarcador(pedido, coords.lat, coords.lon);

                        // Actualizar data attributes
                        const card = document.getElementById(`pedido-card-${pedido.id}`);
                        if (card) {
                            card.dataset.lat = coords.lat;
                            card.dataset.lng = coords.lon;
                        }
                    }

                    procesados++;

                    // Delay para no saturar la API de Nominatim
                    await new Promise(resolve => setTimeout(resolve, 1000));

                } catch (error) {
                    console.error(`❌ Error geocodificando pedido #${pedido.id}:`, error);
                }
            }

            console.log(`✅ Geocodificación completa: ${procesados}/${total} pedidos`);

            // Ajustar vista del mapa a todos los marcadores
            if (Object.keys(markers).length > 0) {
                const group = L.featureGroup(Object.values(markers));
                map.fitBounds(group.getBounds().pad(0.1));
            }
        }

        // Geocodificar una dirección usando Nominatim API
        async function geocodificar(direccion) {
            const query = `${direccion}, La Plata, Buenos Aires, Argentina`;
            const url = `https://nominatim.openstreetmap.org/search?format=json&q=${encodeURIComponent(query)}&limit=1`;

            try {
                const response = await fetch(url, {
                    headers: {
                        'User-Agent': 'SantaCatalinaDelivery/1.0'
                    }
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
                console.error('Error en geocodificación:', error);
                return null;
            }
        }

        // Agregar marcador al mapa
        function agregarMarcador(pedido, lat, lng) {
            const nombre = pedido.cliente_fijo_nombre ?
                `${pedido.cliente_fijo_nombre} ${pedido.cliente_fijo_apellido}` :
                `${pedido.nombre} ${pedido.apellido}`;

            // Color según estado
            const color = {
                'Pendiente': '#eab308',
                'Preparando': '#3b82f6',
                'Listo': '#22c55e',
                'Entregado': '#6b7280'
            }[pedido.estado] || '#6b7280';

            // Icono personalizado
            const icon = L.divIcon({
                className: 'custom-marker',
                html: `<div style="
                    background-color: ${color};
                    width: 32px;
                    height: 32px;
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
                        font-size: 12px;
                    ">${pedido.id}</span>
                </div>`,
                iconSize: [32, 32],
                iconAnchor: [16, 32]
            });

            const marker = L.marker([lat, lng], { icon }).addTo(map);

            // Popup
            const popupContent = `
                <div class="popup-content">
                    <div class="popup-header">
                        Pedido #${pedido.id}
                    </div>
                    <div class="popup-info">
                        <strong>Cliente:</strong> ${nombre}<br>
                        <strong>Teléfono:</strong> ${pedido.telefono}<br>
                        <strong>Dirección:</strong> ${pedido.direccion}<br>
                        <strong>Producto:</strong> ${pedido.producto.substring(0, 50)}...<br>
                        <strong>Estado:</strong> <span style="color: ${color}; font-weight: 700;">${pedido.estado}</span><br>
                        <strong>Precio:</strong> <span style="color: #16a34a; font-weight: 700;">$${pedido.precio.toLocaleString()}</span>
                    </div>
                    <div class="popup-actions">
                        <button onclick="imprimir(${pedido.id})" class="btn btn-sm" style="background: linear-gradient(135deg, #f97316, #ea580c); color: white;">
                            <i class="fas fa-print"></i> Imprimir
                        </button>
                        <a href="https://wa.me/54${pedido.telefono.replace(/[^0-9]/g, '')}"
                           target="_blank"
                           class="btn btn-sm" style="background: #25d366; color: white;">
                            <i class="fab fa-whatsapp"></i> WhatsApp
                        </a>
                        <button onclick="abrirGoogleMaps('${pedido.direccion}')" class="btn btn-sm btn-primary">
                            <i class="fas fa-directions"></i> Ir
                        </button>
                    </div>
                </div>
            `;

            marker.bindPopup(popupContent, { maxWidth: 320 });

            // Al hacer clic, seleccionar el pedido
            marker.on('click', () => {
                selectPedido(pedido.id);
            });

            markers[pedido.id] = marker;
        }

        // Seleccionar pedido
        function selectPedido(pedidoId) {
            // Remover selección previa
            document.querySelectorAll('.pedido-card').forEach(card => {
                card.classList.remove('selected');
            });

            // Seleccionar nuevo
            const card = document.getElementById(`pedido-card-${pedidoId}`);
            if (card) {
                card.classList.add('selected');
                card.scrollIntoView({ behavior: 'smooth', block: 'center' });
            }

            selectedPedidoId = pedidoId;
        }

        // Focus en pedido desde sidebar
        function focusPedido(pedidoId) {
            selectPedido(pedidoId);

            const marker = markers[pedidoId];
            if (marker) {
                map.setView(marker.getLatLng(), 16);
                marker.openPopup();
            }
        }

        // Centrar mapa
        function centrarMapa() {
            if (Object.keys(markers).length > 0) {
                const group = L.featureGroup(Object.values(markers));
                map.fitBounds(group.getBounds().pad(0.1));
            } else {
                map.setView(MAP_CENTER, 13);
            }
        }

        // Set origen
        function setOrigen(direccion) {
            document.getElementById('origenInput').value = direccion;
        }

        // Seleccionar todos
        function seleccionarTodos() {
            document.querySelectorAll('.pedido-checkbox').forEach(cb => {
                cb.checked = true;
            });
            actualizarSeleccion();
        }

        // Limpiar selección
        function limpiarSeleccion() {
            document.querySelectorAll('.pedido-checkbox').forEach(cb => {
                cb.checked = false;
            });
            actualizarSeleccion();
        }

        // Seleccionar solo listos
        function seleccionarListos() {
            document.querySelectorAll('.pedido-checkbox').forEach(cb => {
                const card = document.getElementById(`pedido-card-${cb.dataset.pedidoId}`);
                if (card && card.classList.contains('estado-Listo')) {
                    cb.checked = true;
                } else {
                    cb.checked = false;
                }
            });
            actualizarSeleccion();
        }

        // Actualizar selección
        function actualizarSeleccion() {
            const seleccionados = document.querySelectorAll('.pedido-checkbox:checked').length;
            document.getElementById('contadorSeleccion').innerHTML = `
                <i class="fas fa-check-circle"></i> ${seleccionados} seleccionados
            `;
        }

        // Optimizar ruta con pedidos seleccionados
        async function optimizarRuta() {
            console.log('🔄 Optimizando ruta...');

            // Obtener pedidos seleccionados con coordenadas
            const seleccionados = [];
            document.querySelectorAll('.pedido-checkbox:checked').forEach(cb => {
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

            if (seleccionados.length === 0) {
                alert('⚠️ Selecciona al menos un pedido con dirección válida');
                return;
            }

            // Obtener punto de origen
            const origenInput = document.getElementById('origenInput').value.trim();

            if (origenInput) {
                // Geocodificar origen
                const origenCoords = await geocodificar(origenInput);
                if (origenCoords) {
                    // Agregar marcador de origen si no existe
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
                    originMarker.bindPopup('<strong>Punto de Partida</strong><br>' + origenInput);

                    // Usar origen como punto inicial
                    seleccionados.unshift({
                        id: 'origen',
                        lat: origenCoords.lat,
                        lng: origenCoords.lon,
                        direccion: origenInput
                    });
                }
            }

            // Algoritmo greedy: nearest neighbor
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

            // Guardar ruta actual
            rutaActual = rutaOptimizada.filter(p => p.id !== 'origen');

            // Reorganizar sidebar
            const container = document.getElementById('pedidosList');

            // Limpiar badges anteriores
            document.querySelectorAll('.orden-badge').forEach(b => b.remove());

            // Remover clases in-route
            document.querySelectorAll('.pedido-card').forEach(c => c.classList.remove('in-route'));

            rutaOptimizada.forEach((pedido, idx) => {
                if (pedido.id === 'origen') return;

                const card = document.getElementById(`pedido-card-${pedido.id}`);
                if (card) {
                    card.style.order = idx;
                    card.classList.add('in-route');

                    // Agregar número de orden
                    const badge = document.createElement('span');
                    badge.className = 'orden-badge';
                    badge.textContent = `#${idx}`;
                    card.style.position = 'relative';
                    card.appendChild(badge);
                }
            });

            container.style.display = 'flex';
            container.style.flexDirection = 'column';

            // Dibujar ruta en el mapa
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

            // Calcular estadísticas
            const distanciaTotal = calcularDistanciaTotal(coords);
            const tiempoEstimado = Math.round(distanciaTotal / 30 * 60); // 30 km/h promedio
            const totalPedidos = rutaActual.length;
            const totalVentas = rutaActual.reduce((sum, p) => {
                const card = document.getElementById(`pedido-card-${p.id}`);
                return sum + (card ? parseFloat(card.dataset.precio) : 0);
            }, 0);

            // Mostrar panel de resumen
            document.getElementById('routePanel').classList.add('active');
            document.getElementById('routePedidos').textContent = totalPedidos;
            document.getElementById('routeDistancia').textContent = distanciaTotal.toFixed(1) + ' km';
            document.getElementById('routeTiempo').textContent = tiempoEstimado + ' min';
            document.getElementById('routeTotal').textContent = '$' + totalVentas.toLocaleString();

            // Ajustar vista
            map.fitBounds(window.rutaPolyline.getBounds().pad(0.1));

            alert(`✅ Ruta optimizada con ${totalPedidos} pedidos\n📏 ${distanciaTotal.toFixed(1)} km - ⏱️ ${tiempoEstimado} min`);
        }

        // Calcular distancia entre dos puntos (Haversine)
        function calcularDistancia(lat1, lng1, lat2, lng2) {
            const R = 6371; // Radio de la Tierra en km
            const dLat = (lat2 - lat1) * Math.PI / 180;
            const dLng = (lng2 - lng1) * Math.PI / 180;
            const a =
                Math.sin(dLat/2) * Math.sin(dLat/2) +
                Math.cos(lat1 * Math.PI / 180) * Math.cos(lat2 * Math.PI / 180) *
                Math.sin(dLng/2) * Math.sin(dLng/2);
            const c = 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1-a));
            return R * c;
        }

        // Calcular distancia total de una ruta
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

        // Cerrar panel de ruta
        function cerrarPanelRuta() {
            document.getElementById('routePanel').classList.remove('active');
        }

        // Exportar ruta
        function exportarRuta() {
            if (rutaActual.length === 0) {
                alert('⚠️ No hay ruta para exportar');
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
                ['Orden', 'Pedido ID', 'Cliente', 'Teléfono', 'Dirección', 'Producto', 'Precio', 'Coordenadas'],
                ...data.map(d => [d.orden, d.pedido_id, d.nombre, d.telefono, d.direccion, d.producto, d.precio, d.coordenadas])
            ].map(row => row.join(',')).join('\n');

            const blob = new Blob([csv], { type: 'text/csv' });
            const url = window.URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = `ruta_delivery_${new Date().toISOString().split('T')[0]}.csv`;
            a.click();
            window.URL.revokeObjectURL(url);

            alert('✅ Ruta exportada correctamente');
        }

        // Filtrar por estado
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

        // Imprimir pedido
        function imprimir(pedidoId) {
            const url = `../impresion/comanda_simple.php?pedido=${pedidoId}`;
            const ventana = window.open(url, '_blank', 'width=320,height=500,scrollbars=yes');

            if (!ventana) {
                alert('❌ Habilita las ventanas emergentes para imprimir');
                return false;
            }

            ventana.focus();
            return true;
        }

        // Imprimir todos
        function imprimirTodos() {
            const ids = pedidos.map(p => p.id);

            if (ids.length === 0) {
                alert('⚠️ No hay pedidos para imprimir');
                return;
            }

            if (ids.length > 15) {
                if (!confirm(`⚠️ Vas a imprimir ${ids.length} comandas.\n\n¿Continuar?`)) {
                    return;
                }
            }

            ids.forEach((id, index) => {
                setTimeout(() => {
                    imprimir(id);
                }, index * 600);
            });
        }

        // Abrir en Google Maps
        function abrirGoogleMaps(direccion) {
            const url = `https://www.google.com/maps/dir/?api=1&destination=${encodeURIComponent(direccion + ', La Plata, Buenos Aires')}`;
            window.open(url, '_blank');
        }

        // Toggle sidebar en mobile
        function toggleSidebar() {
            const sidebar = document.querySelector('.sidebar');
            sidebar.style.display = sidebar.style.display === 'none' ? 'flex' : 'none';
        }

        // Responsive
        function checkResponsive() {
            const toggleBtn = document.getElementById('toggleSidebarBtn');
            const sidebar = document.querySelector('.sidebar');

            if (window.innerWidth <= 768) {
                toggleBtn.style.display = 'block';
            } else {
                toggleBtn.style.display = 'none';
                sidebar.style.display = 'flex';
            }
        }

        window.addEventListener('resize', checkResponsive);

        // Inicializar al cargar
        document.addEventListener('DOMContentLoaded', () => {
            initMap();
            checkResponsive();
            actualizarSeleccion();
        });

        console.log('✅ Delivery Pro cargado completamente');
    </script>
</body>
</html>
