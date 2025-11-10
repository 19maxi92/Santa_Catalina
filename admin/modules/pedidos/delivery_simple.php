<?php
// admin/modules/pedidos/delivery_simple.php - SISTEMA DE DELIVERY CON MAPA INTERACTIVO
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
    $filtro_estado = 'pendientes'; // todos menos entregados
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
    <title>🏍️ Delivery con Mapa - Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <!-- Leaflet CSS -->
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />

    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            margin: 0;
            padding: 0;
            overflow: hidden;
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
            box-shadow: 0 2px 8px rgba(0,0,0,0.2);
            z-index: 1000;
        }

        .main-content {
            display: flex;
            flex: 1;
            overflow: hidden;
        }

        .sidebar {
            width: 400px;
            background: white;
            border-right: 2px solid #e5e7eb;
            display: flex;
            flex-direction: column;
            overflow: hidden;
        }

        .sidebar-header {
            padding: 15px;
            background: #f9fafb;
            border-bottom: 2px solid #e5e7eb;
        }

        .sidebar-content {
            flex: 1;
            overflow-y: auto;
            padding: 10px;
        }

        .sidebar-content::-webkit-scrollbar {
            width: 8px;
        }

        .sidebar-content::-webkit-scrollbar-thumb {
            background: #94a3b8;
            border-radius: 4px;
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
            border-radius: 8px;
            padding: 12px;
            margin-bottom: 10px;
            cursor: pointer;
            transition: all 0.2s;
        }

        .pedido-card:hover {
            border-color: #16a34a;
            box-shadow: 0 4px 12px rgba(22, 163, 74, 0.2);
            transform: translateX(4px);
        }

        .pedido-card.selected {
            border-color: #16a34a;
            background: #f0fdf4;
            box-shadow: 0 4px 12px rgba(22, 163, 74, 0.3);
        }

        .pedido-card.estado-Pendiente {
            border-left: 4px solid #eab308;
        }

        .pedido-card.estado-Preparando {
            border-left: 4px solid #3b82f6;
        }

        .pedido-card.estado-Listo {
            border-left: 4px solid #22c55e;
        }

        .pedido-card.estado-Entregado {
            border-left: 4px solid #6b7280;
            opacity: 0.6;
        }

        .urgente-badge {
            background: #dc2626;
            color: white;
            padding: 2px 8px;
            border-radius: 12px;
            font-size: 10px;
            font-weight: bold;
            animation: pulse 2s infinite;
        }

        @keyframes pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.7; }
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 10px;
            margin-bottom: 15px;
        }

        .stat-box {
            background: white;
            padding: 10px;
            border-radius: 6px;
            text-align: center;
            border: 1px solid #e5e7eb;
        }

        .stat-number {
            font-size: 24px;
            font-weight: bold;
        }

        .stat-label {
            font-size: 11px;
            color: #6b7280;
            text-transform: uppercase;
        }

        .btn {
            padding: 8px 16px;
            border-radius: 6px;
            border: none;
            cursor: pointer;
            font-weight: 600;
            transition: all 0.2s;
            font-size: 13px;
        }

        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        }

        .btn-primary {
            background: #16a34a;
            color: white;
        }

        .btn-secondary {
            background: #6b7280;
            color: white;
        }

        .btn-sm {
            padding: 4px 10px;
            font-size: 11px;
        }

        .leaflet-popup-content {
            min-width: 250px;
        }

        .popup-content {
            font-family: 'Segoe UI', sans-serif;
        }

        .popup-header {
            font-size: 16px;
            font-weight: bold;
            margin-bottom: 8px;
            color: #16a34a;
        }

        .popup-info {
            font-size: 13px;
            line-height: 1.6;
            color: #374151;
        }

        .popup-actions {
            margin-top: 10px;
            display: flex;
            gap: 5px;
        }

        .filters-container {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
            margin-bottom: 15px;
        }

        .filter-btn {
            padding: 6px 12px;
            border-radius: 6px;
            border: 2px solid #e5e7eb;
            background: white;
            cursor: pointer;
            font-size: 12px;
            font-weight: 600;
            transition: all 0.2s;
        }

        .filter-btn.active {
            background: #16a34a;
            color: white;
            border-color: #16a34a;
        }

        .filter-btn:hover:not(.active) {
            border-color: #16a34a;
            color: #16a34a;
        }

        /* Responsive */
        @media (max-width: 768px) {
            .sidebar {
                width: 100%;
                max-width: 100%;
                position: absolute;
                z-index: 999;
                height: 40%;
                bottom: 0;
                border-right: none;
                border-top: 2px solid #e5e7eb;
            }

            .map-container {
                height: 60%;
            }
        }

        .loading-spinner {
            display: inline-block;
            width: 20px;
            height: 20px;
            border: 3px solid rgba(255,255,255,.3);
            border-radius: 50%;
            border-top-color: white;
            animation: spin 1s ease-in-out infinite;
        }

        @keyframes spin {
            to { transform: rotate(360deg); }
        }

        .optimize-route-btn {
            background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
            color: white;
            margin-top: 10px;
        }
    </style>
</head>
<body>
    <div class="layout-container">
        <!-- Header -->
        <div class="header-bar">
            <div class="flex items-center gap-4">
                <h1 class="text-xl font-bold flex items-center gap-2">
                    <i class="fas fa-motorcycle"></i>
                    DELIVERY CON MAPA
                </h1>
                <span class="bg-white text-green-700 px-3 py-1 rounded-full text-sm font-bold">
                    <?= $total ?> pedidos
                </span>
                <span class="text-green-100 text-sm">
                    <i class="fas fa-map-marked-alt"></i>
                    OpenStreetMap
                </span>
            </div>

            <div class="flex items-center gap-2">
                <button onclick="optimizarRuta()" class="btn btn-sm" style="background: #f59e0b; color: white;">
                    <i class="fas fa-route"></i>
                    Optimizar Ruta
                </button>
                <button onclick="centrarMapa()" class="btn btn-sm btn-secondary">
                    <i class="fas fa-crosshairs"></i>
                    Centrar
                </button>
                <button onclick="imprimirTodos()" class="btn btn-sm" style="background: #f97316; color: white;">
                    <i class="fas fa-print"></i>
                    Imprimir
                </button>
                <a href="ver_pedidos.php" class="btn btn-sm btn-secondary">
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
                        <div class="stat-box" style="border-left: 3px solid #eab308;">
                            <div class="stat-number" style="color: #eab308;"><?= $pendientes ?></div>
                            <div class="stat-label">Pendientes</div>
                        </div>
                        <div class="stat-box" style="border-left: 3px solid #3b82f6;">
                            <div class="stat-number" style="color: #3b82f6;"><?= $preparando ?></div>
                            <div class="stat-label">Preparando</div>
                        </div>
                        <div class="stat-box" style="border-left: 3px solid #22c55e;">
                            <div class="stat-number" style="color: #22c55e;"><?= $listos ?></div>
                            <div class="stat-label">Listos</div>
                        </div>
                        <div class="stat-box" style="border-left: 3px solid #16a34a;">
                            <div class="stat-number" style="color: #16a34a;">$<?= number_format($total_ventas/1000, 1) ?>K</div>
                            <div class="stat-label">Total</div>
                        </div>
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
                            <i class="fas fa-motorcycle text-5xl mb-3 opacity-30"></i>
                            <p class="font-semibold">No hay pedidos de delivery</p>
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

                            <div class="pedido-card estado-<?= $pedido['estado'] ?>"
                                 id="pedido-card-<?= $pedido['id'] ?>"
                                 onclick="focusPedido(<?= $pedido['id'] ?>)"
                                 data-lat=""
                                 data-lng=""
                                 data-direccion="<?= htmlspecialchars($pedido['direccion']) ?>">

                                <div class="flex justify-between items-start mb-2">
                                    <div>
                                        <span class="text-sm font-bold text-blue-600">#<?= $pedido['id'] ?></span>
                                        <span class="text-xs text-gray-500 ml-2">
                                            <?= $pedido['minutos_transcurridos'] ?> min
                                        </span>
                                        <?php if ($urgente): ?>
                                            <span class="urgente-badge ml-2">¡URGENTE!</span>
                                        <?php endif; ?>
                                    </div>
                                    <div class="text-xl"><?= $estado_icon ?></div>
                                </div>

                                <div class="font-bold text-sm text-gray-800 mb-1">
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
                                    <div class="text-xs text-red-500 mb-2">
                                        <i class="fas fa-exclamation-triangle"></i>
                                        Sin dirección
                                    </div>
                                <?php endif; ?>

                                <div class="text-xs text-gray-700 mb-2">
                                    <?= htmlspecialchars(substr($pedido['producto'], 0, 40)) ?><?= strlen($pedido['producto']) > 40 ? '...' : '' ?>
                                </div>

                                <div class="flex justify-between items-center">
                                    <span class="text-base font-bold text-green-600">
                                        $<?= number_format($pedido['precio'], 0, ',', '.') ?>
                                    </span>
                                    <div class="flex gap-1">
                                        <button onclick="event.stopPropagation(); imprimir(<?= $pedido['id'] ?>)"
                                                class="btn btn-sm" style="background: #f97316; color: white;"
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
        console.log('🗺️ Iniciando sistema de delivery con mapa...');

        // Datos de pedidos
        const pedidos = <?= json_encode($pedidos_mapa) ?>;
        console.log('📦 Pedidos cargados:', pedidos.length);

        // Configuración del mapa
        const MAP_CENTER = [-34.9214, -57.9544]; // La Plata, Buenos Aires
        let map, markers = {};
        let selectedPedidoId = null;

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
                    width: 30px;
                    height: 30px;
                    border-radius: 50% 50% 50% 0;
                    border: 3px solid white;
                    transform: rotate(-45deg);
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    box-shadow: 0 3px 10px rgba(0,0,0,0.3);
                ">
                    <span style="
                        transform: rotate(45deg);
                        color: white;
                        font-weight: bold;
                        font-size: 12px;
                    ">${pedido.id}</span>
                </div>`,
                iconSize: [30, 30],
                iconAnchor: [15, 30]
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
                        <strong>Estado:</strong> <span style="color: ${color}; font-weight: bold;">${pedido.estado}</span><br>
                        <strong>Precio:</strong> $${pedido.precio.toLocaleString()}
                    </div>
                    <div class="popup-actions">
                        <button onclick="imprimir(${pedido.id})" class="btn btn-sm" style="background: #f97316; color: white;">
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

            marker.bindPopup(popupContent, { maxWidth: 300 });

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

        // Optimizar ruta (algoritmo simple: nearest neighbor)
        function optimizarRuta() {
            console.log('🔄 Optimizando ruta...');

            // Solo pedidos listos con coordenadas
            const pedidosListos = pedidos.filter(p => {
                const card = document.getElementById(`pedido-card-${p.id}`);
                return p.estado === 'Listo' && card && card.dataset.lat && card.dataset.lng;
            });

            if (pedidosListos.length === 0) {
                alert('⚠️ No hay pedidos listos para optimizar');
                return;
            }

            // Algoritmo greedy: siempre ir al más cercano
            const rutaOptimizada = [];
            let actual = null;
            let disponibles = [...pedidosListos];

            // Empezar desde el más antiguo
            actual = disponibles.shift();
            rutaOptimizada.push(actual);

            while (disponibles.length > 0) {
                const card = document.getElementById(`pedido-card-${actual.id}`);
                const latActual = parseFloat(card.dataset.lat);
                const lngActual = parseFloat(card.dataset.lng);

                // Encontrar el más cercano
                let minDist = Infinity;
                let mejorIdx = 0;

                disponibles.forEach((pedido, idx) => {
                    const cardPedido = document.getElementById(`pedido-card-${pedido.id}`);
                    const lat = parseFloat(cardPedido.dataset.lat);
                    const lng = parseFloat(cardPedido.dataset.lng);

                    const dist = Math.sqrt(
                        Math.pow(lat - latActual, 2) +
                        Math.pow(lng - lngActual, 2)
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

            // Reorganizar sidebar
            const container = document.getElementById('pedidosList');
            rutaOptimizada.forEach((pedido, idx) => {
                const card = document.getElementById(`pedido-card-${pedido.id}`);
                if (card) {
                    card.style.order = idx;

                    // Agregar número de orden
                    if (!card.querySelector('.orden-badge')) {
                        const badge = document.createElement('span');
                        badge.className = 'orden-badge';
                        badge.style.cssText = 'position: absolute; top: 10px; right: 10px; background: #f59e0b; color: white; padding: 4px 8px; border-radius: 12px; font-weight: bold; font-size: 11px;';
                        badge.textContent = `Orden ${idx + 1}`;
                        card.style.position = 'relative';
                        card.appendChild(badge);
                    }
                }
            });

            container.style.display = 'flex';
            container.style.flexDirection = 'column';

            // Dibujar ruta en el mapa
            const coords = rutaOptimizada.map(p => {
                const card = document.getElementById(`pedido-card-${p.id}`);
                return [parseFloat(card.dataset.lat), parseFloat(card.dataset.lng)];
            });

            if (window.rutaPolyline) {
                map.removeLayer(window.rutaPolyline);
            }

            window.rutaPolyline = L.polyline(coords, {
                color: '#f59e0b',
                weight: 4,
                opacity: 0.7,
                dashArray: '10, 10'
            }).addTo(map);

            alert(`✅ Ruta optimizada con ${rutaOptimizada.length} pedidos`);
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
        });

        console.log('✅ Sistema de delivery con mapa cargado');
    </script>
</body>
</html>
