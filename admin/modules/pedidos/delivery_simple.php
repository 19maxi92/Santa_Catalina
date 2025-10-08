<?php
// admin/modules/pedidos/delivery_simple.php - VISTA DELIVERY SIN MAPA
require_once '../../config.php';
requireLogin();

$pdo = getConnection();

// Filtros
$filtro_estado = $_GET['estado'] ?? '';
$fecha_desde = $_GET['fecha_desde'] ?? '';
$fecha_hasta = $_GET['fecha_hasta'] ?? '';
$busqueda = $_GET['buscar'] ?? '';

// Por defecto: pedidos de hoy
if (!$fecha_desde && !$fecha_hasta && !$busqueda && !$filtro_estado) {
    $fecha_desde = date('Y-m-d');
    $fecha_hasta = date('Y-m-d');
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

if ($filtro_estado && $filtro_estado !== 'Todos') {
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
    $sql .= " AND (p.nombre LIKE ? OR p.apellido LIKE ? OR p.telefono LIKE ? OR p.direccion LIKE ? OR CAST(p.id AS CHAR) LIKE ? OR cf.nombre LIKE ? OR cf.apellido LIKE ?)";
    $busqueda_param = '%' . $busqueda . '%';
    $params = array_merge($params, array_fill(0, 7, $busqueda_param));
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
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>🏍️ Delivery - Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- JAVASCRIPT ANTES DEL HTML -->
    <script>
    // ============================================
    // DEFINIR FUNCIONES GLOBALES PRIMERO
    // ============================================
    
    console.log('🚀 Definiendo funciones globales...');
    
    // Variables globales
    window.direccionActual = '';
    window.nombreActual = '';
    window.pedidoIdActual = 0;
    
    // FUNCIÓN PRINCIPAL DEL MAPA
    window.abrirMiniMapa = function(direccion, nombre, pedidoId) {
        console.log('=== INTENTANDO ABRIR MINI MAPA ===');
        console.log('Dirección:', direccion);
        console.log('Nombre:', nombre);
        console.log('Pedido ID:', pedidoId);
        
        // Esperar a que el DOM esté listo
        if (document.readyState === 'loading') {
            console.log('⏳ DOM no está listo, esperando...');
            document.addEventListener('DOMContentLoaded', function() {
                window.abrirMiniMapa(direccion, nombre, pedidoId);
            });
            return;
        }
        
        try {
            window.direccionActual = direccion;
            window.nombreActual = nombre;
            window.pedidoIdActual = pedidoId;
            
            // Buscar elementos
            var modal = document.getElementById('modalMapa');
            var titulo = document.getElementById('mapaTitulo');
            var direccionElement = document.getElementById('mapaDireccion');
            var mapaBody = document.getElementById('mapaBody');
            
            console.log('Elementos encontrados:');
            console.log('- Modal:', !!modal);
            console.log('- Titulo:', !!titulo);
            console.log('- Dirección:', !!direccionElement);
            console.log('- MapaBody:', !!mapaBody);
            
            if (!modal) {
                alert('❌ Error: No se encontró el modal del mapa (ID: modalMapa)');
                return;
            }
            
            if (!titulo || !direccionElement || !mapaBody) {
                alert('❌ Error: Faltan elementos del modal');
                return;
            }
            
            // Actualizar contenido
            titulo.textContent = 'Pedido #' + pedidoId + ' - ' + nombre;
            direccionElement.textContent = direccion;
            
            // Crear iframe
            var direccionCompleta = encodeURIComponent(direccion + ', La Plata, Buenos Aires, Argentina');
            var iframeHTML = '<iframe src="https://maps.google.com/maps?q=' + direccionCompleta + '&output=embed&z=16" style="border:0; width:100%; height:100%;" allowfullscreen="" loading="lazy"></iframe>';
            
            mapaBody.innerHTML = iframeHTML;
            
            // Mostrar modal
            modal.classList.add('active');
            document.body.style.overflow = 'hidden';
            
            console.log('✅ MODAL ABIERTO EXITOSAMENTE');
            
        } catch (error) {
            console.error('❌ ERROR AL ABRIR MAPA:', error);
            alert('Error al abrir el mapa: ' + error.message);
        }
    };
    
    window.cerrarMiniMapa = function(event) {
        if (event && event.target !== event.currentTarget) {
            return;
        }
        
        var modal = document.getElementById('modalMapa');
        if (modal) {
            modal.classList.remove('active');
            document.body.style.overflow = 'auto';
            console.log('✅ Modal cerrado');
        }
    };
    
    window.abrirGoogleMapsCompleto = function() {
        var url = 'https://www.google.com/maps/dir/?api=1&destination=' + encodeURIComponent(window.direccionActual + ', La Plata');
        window.open(url, '_blank');
    };
    
    window.imprimir = function(pedidoId) {
        var url = '../impresion/comanda_simple.php?pedido=' + pedidoId;
        var ventana = window.open(url, '_blank', 'width=320,height=500,scrollbars=yes');
        
        if (!ventana) {
            alert('❌ Habilita las ventanas emergentes para imprimir');
            return false;
        }
        
        ventana.focus();
        return true;
    };
    
    window.imprimirTodos = function() {
        var pedidos = <?= json_encode(array_column($pedidos, 'id')) ?>;
        
        if (!pedidos || pedidos.length === 0) {
            alert('⚠️ No hay pedidos para imprimir');
            return;
        }
        
        if (pedidos.length > 15) {
            if (!confirm('⚠️ Vas a imprimir ' + pedidos.length + ' comandas.\n\n¿Continuar?')) {
                return;
            }
        }
        
        pedidos.forEach(function(id, index) {
            setTimeout(function() {
                window.imprimir(id);
            }, index * 600);
        });
    };
    
    console.log('✅ Funciones globales definidas');
    console.log('📋 abrirMiniMapa:', typeof window.abrirMiniMapa);
    console.log('📋 cerrarMiniMapa:', typeof window.cerrarMiniMapa);
    console.log('📋 imprimir:', typeof window.imprimir);
    
    // Evento ESC al cargar
    document.addEventListener('DOMContentLoaded', function() {
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape' || e.key === 'Esc') {
                window.cerrarMiniMapa();
            }
        });
        
        console.log('✅ Sistema Delivery completamente cargado');
        
        // Verificar modal
        setTimeout(function() {
            var modal = document.getElementById('modalMapa');
            console.log('🔍 Verificación final - Modal existe:', !!modal);
        }, 500);
    });
    </script>
    
    <style>
        body { 
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        .pedido-row {
            transition: all 0.2s;
        }
        
        .pedido-row:hover {
            background: #f9fafb !important;
            transform: scale(1.01);
        }
        
        .urgente {
            animation: pulse 2s infinite;
            background: linear-gradient(90deg, #fee2e2 0%, #fef2f2 100%) !important;
            border-left: 4px solid #dc2626 !important;
        }
        
        @keyframes pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.85; }
        }
        
        .badge {
            display: inline-flex;
            align-items: center;
            padding: 2px 8px;
            border-radius: 9999px;
            font-size: 10px;
            font-weight: 700;
            text-transform: uppercase;
        }
        
        .direccion-bold {
            font-weight: 700;
            font-size: 15px;
            color: #166534;
        }
        
        .tabla-container {
            max-height: calc(100vh - 220px);
            overflow-y: auto;
        }
        
        .tabla-container::-webkit-scrollbar {
            width: 8px;
        }
        
        .tabla-container::-webkit-scrollbar-thumb {
            background: linear-gradient(180deg, #94a3b8, #64748b);
            border-radius: 10px;
        }
        
        .btn {
            transition: all 0.2s;
            cursor: pointer;
        }
        
        .btn:hover {
            transform: translateY(-2px);
        }
        
        .btn-compact {
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 11px;
            border: none;
            cursor: pointer;
        }
        
        /* Links que parecen botones */
        a[onclick] {
            cursor: pointer;
        }
        
        button[onclick] {
            cursor: pointer;
        }
        
        /* MODAL MINI MAPA */
        .modal-mapa {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.7);
            z-index: 9999;
            align-items: center;
            justify-content: center;
        }
        
        .modal-mapa.active {
            display: flex;
        }
        
        .modal-mapa-content {
            background: white;
            border-radius: 12px;
            width: 90%;
            max-width: 800px;
            height: 80vh;
            display: flex;
            flex-direction: column;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.5);
            overflow: hidden;
        }
        
        .modal-mapa-header {
            background: linear-gradient(135deg, #16a34a 0%, #15803d 100%);
            color: white;
            padding: 15px 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .modal-mapa-body {
            flex: 1;
            position: relative;
        }
        
        .modal-mapa-body iframe {
            width: 100%;
            height: 100%;
            border: none;
        }
    </style>
</head>
<body class="bg-gradient-to-br from-gray-50 to-gray-100">
    
    <!-- HEADER -->
    <header class="bg-gradient-to-r from-green-600 to-emerald-700 text-white shadow-xl sticky top-0 z-50">
        <div class="max-w-7xl mx-auto px-4 py-4">
            <div class="flex justify-between items-center">
                <div class="flex items-center gap-4">
                    <h1 class="text-2xl font-bold flex items-center gap-2">
                        <i class="fas fa-motorcycle"></i>
                        DELIVERY
                    </h1>
                    <span class="badge bg-white text-green-700">
                        <?= $total ?> pedidos
                    </span>
                </div>
                
                <!-- STATS -->
                <div class="hidden md:flex items-center gap-6 text-sm">
                    <div class="text-center">
                        <div class="text-2xl font-bold text-yellow-300"><?= $pendientes ?></div>
                        <div class="text-green-200">Pendientes</div>
                    </div>
                    <div class="text-center">
                        <div class="text-2xl font-bold text-blue-300"><?= $preparando ?></div>
                        <div class="text-green-200">Preparando</div>
                    </div>
                    <div class="text-center">
                        <div class="text-2xl font-bold text-green-300"><?= $listos ?></div>
                        <div class="text-green-200">Listos</div>
                    </div>
                    <div class="text-center">
                        <div class="text-2xl font-bold text-emerald-200">$<?= number_format($total_ventas/1000, 1) ?>K</div>
                        <div class="text-green-200">Ventas</div>
                    </div>
                </div>
                
                <!-- BOTONES -->
                <div class="flex items-center gap-2">
                    <button onclick="imprimirTodos()" class="btn bg-orange-500 hover:bg-orange-600 px-4 py-2 rounded-lg text-sm font-semibold">
                        <i class="fas fa-print mr-2"></i>
                        Imprimir Todos
                    </button>
                    <a href="ver_pedidos.php" class="btn bg-blue-500 hover:bg-blue-600 px-4 py-2 rounded-lg text-sm font-semibold">
                        <i class="fas fa-arrow-left mr-2"></i>
                        Volver
                    </a>
                </div>
            </div>
        </div>
    </header>

    <!-- FILTROS CON TABS -->
    <div class="bg-white border-b shadow-sm sticky top-[73px] z-40">
        <div class="max-w-7xl mx-auto px-4 py-3">
            
            <!-- TABS DE FILTRO RÁPIDO POR ESTADO -->
            <div class="flex space-x-2 mb-3 flex-wrap gap-y-2">
                <a href="?fecha_desde=<?= $fecha_desde ?>&fecha_hasta=<?= $fecha_hasta ?>" 
                   class="filter-tab <?= empty($filtro_estado) ? 'active' : 'bg-gray-100 text-gray-700' ?>">
                    <i class="fas fa-motorcycle"></i>
                    Todos (<?= $total ?>)
                </a>
                <a href="?estado=Pendiente&fecha_desde=<?= $fecha_desde ?>&fecha_hasta=<?= $fecha_hasta ?>" 
                   class="filter-tab <?= $filtro_estado === 'Pendiente' ? 'active' : 'bg-yellow-100 text-yellow-800' ?>">
                    <i class="fas fa-clock"></i>
                    Pendientes (<?= $pendientes ?>)
                </a>
                <a href="?estado=Preparando&fecha_desde=<?= $fecha_desde ?>&fecha_hasta=<?= $fecha_hasta ?>" 
                   class="filter-tab <?= $filtro_estado === 'Preparando' ? 'active' : 'bg-blue-100 text-blue-800' ?>">
                    <i class="fas fa-fire"></i>
                    Preparando (<?= $preparando ?>)
                </a>
                <a href="?estado=Listo&fecha_desde=<?= $fecha_desde ?>&fecha_hasta=<?= $fecha_hasta ?>" 
                   class="filter-tab <?= $filtro_estado === 'Listo' ? 'active' : 'bg-green-100 text-green-800' ?>">
                    <i class="fas fa-check-circle"></i>
                    Listos (<?= $listos ?>)
                </a>
                <a href="?estado=Entregado&fecha_desde=<?= $fecha_desde ?>&fecha_hasta=<?= $fecha_hasta ?>" 
                   class="filter-tab <?= $filtro_estado === 'Entregado' ? 'active' : 'bg-gray-100 text-gray-700' ?>">
                    <i class="fas fa-check-double"></i>
                    Entregados (<?= $entregados ?>)
                </a>
            </div>
            
            <!-- FILTROS AVANZADOS (COLAPSABLES) -->
            <details class="text-sm">
                <summary class="cursor-pointer text-gray-600 hover:text-gray-800 font-semibold mb-3 inline-flex items-center gap-2">
                    <i class="fas fa-sliders-h"></i>
                    Filtros Avanzados
                </summary>
                <form method="GET" class="grid grid-cols-1 md:grid-cols-5 gap-3 mt-2">
                    <input type="text" name="buscar" value="<?= htmlspecialchars($busqueda) ?>" 
                           placeholder="🔍 Buscar cliente, tel, dirección..." 
                           class="col-span-2 px-4 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-green-500">
                    
                    <input type="date" name="fecha_desde" value="<?= $fecha_desde ?>" 
                           class="px-4 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-green-500"
                           placeholder="Desde">
                    
                    <input type="date" name="fecha_hasta" value="<?= $fecha_hasta ?>" 
                           class="px-4 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-green-500"
                           placeholder="Hasta">
                    
                    <div class="flex gap-2">
                        <button type="submit" class="btn flex-1 bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded-lg text-sm font-semibold">
                            <i class="fas fa-search"></i>
                            Buscar
                        </button>
                        <a href="delivery_simple.php" class="btn flex-1 bg-gray-500 hover:bg-gray-600 text-white px-4 py-2 rounded-lg text-sm font-semibold text-center">
                            <i class="fas fa-redo"></i>
                        </a>
                    </div>
                </form>
            </details>
        </div>
    </div>
    
    <style>
        .filter-tab {
            position: relative;
            padding: 10px 18px;
            border-radius: 10px;
            cursor: pointer;
            transition: all 0.2s;
            font-weight: 600;
            font-size: 13px;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }
        
        .filter-tab.active {
            background: linear-gradient(135deg, #16a34a 0%, #15803d 100%);
            color: white;
            box-shadow: 0 4px 12px rgba(22, 163, 74, 0.4);
        }
        
        .filter-tab:not(.active):hover {
            background: #f3f4f6;
            transform: translateY(-2px);
        }
    </style>

    <!-- LISTA DE PEDIDOS -->
    <main class="max-w-7xl mx-auto px-4 py-4">
        <?php if (empty($pedidos)): ?>
            <div class="bg-white rounded-xl shadow-lg p-12 text-center">
                <i class="fas fa-motorcycle text-gray-300 text-6xl mb-4"></i>
                <h2 class="text-2xl font-bold text-gray-600 mb-2">No hay pedidos delivery</h2>
                <p class="text-gray-500 mb-6">Para la fecha seleccionada</p>
                <a href="delivery_simple.php" class="btn bg-green-500 hover:bg-green-600 text-white px-6 py-3 rounded-lg font-semibold">
                    <i class="fas fa-list mr-2"></i>Ver Todos
                </a>
            </div>
        <?php else: ?>
            <div class="bg-white rounded-lg shadow-md overflow-hidden">
                <div class="tabla-container">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50 sticky top-0">
                            <tr class="text-xs text-gray-600 uppercase">
                                <th class="px-3 py-3 text-left">#</th>
                                <th class="px-3 py-3 text-left">Cliente</th>
                                <th class="px-3 py-3 text-left">📍 Dirección</th>
                                <th class="px-3 py-3 text-left">Producto</th>
                                <th class="px-3 py-3 text-center">Estado</th>
                                <th class="px-3 py-3 text-left">Fechas</th>
                                <th class="px-3 py-3 text-right">Precio</th>
                                <th class="px-3 py-3 text-center">Acciones</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200">
                            <?php foreach ($pedidos as $pedido): ?>
                                <?php
                                $nombre_completo = $pedido['cliente_fijo_nombre'] ? 
                                    $pedido['cliente_fijo_nombre'] . ' ' . $pedido['cliente_fijo_apellido'] : 
                                    $pedido['nombre'] . ' ' . $pedido['apellido'];
                                
                                $bg_color = match($pedido['estado']) {
                                    'Pendiente' => 'bg-yellow-50',
                                    'Preparando' => 'bg-blue-50',
                                    'Listo' => 'bg-green-50',
                                    'Entregado' => 'bg-gray-50',
                                    default => 'bg-white'
                                };
                                
                                $estado_icon = match($pedido['estado']) {
                                    'Pendiente' => '⏱️',
                                    'Preparando' => '🔥',
                                    'Listo' => '✅',
                                    'Entregado' => '📦',
                                    default => '❓'
                                };
                                
                                $clase_urgente = ($pedido['minutos_transcurridos'] > 60 && $pedido['estado'] !== 'Entregado') ? 'urgente' : '';
                                
                                $fecha_pedido = date('d/m H:i', strtotime($pedido['created_at']));
                                $fecha_entrega = $pedido['fecha_entrega'] ?? null;
                                ?>
                                
                                <tr class="pedido-row <?= $bg_color ?> <?= $clase_urgente ?>">
                                    <!-- ID -->
                                    <td class="px-3 py-2">
                                        <div class="font-bold text-blue-600 text-base">#<?= $pedido['id'] ?></div>
                                        <div class="text-xs text-gray-500">
                                            <?= $pedido['minutos_transcurridos'] ?> min
                                            <?php if ($clase_urgente): ?>
                                                <span class="badge bg-red-500 text-white ml-1">!</span>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                    
                                    <!-- CLIENTE -->
                                    <td class="px-3 py-2">
                                        <div class="font-semibold text-sm text-gray-800">
                                            <?= htmlspecialchars($nombre_completo) ?>
                                        </div>
                                        <div class="text-xs text-gray-600">
                                            <i class="fas fa-phone text-xs"></i>
                                            <?= htmlspecialchars($pedido['telefono']) ?>
                                        </div>
                                    </td>
                                    
                                    <!-- DIRECCIÓN DESTACADA -->
                                    <td class="px-3 py-2">
                                        <div class="direccion-bold mb-1">
                                            <?= htmlspecialchars($pedido['direccion'] ?: 'Sin dirección') ?>
                                        </div>
                                        <?php if ($pedido['direccion']): ?>
                                            <a href="#" 
                                               onclick="abrirMiniMapa(<?= htmlspecialchars(json_encode($pedido['direccion'])) ?>, <?= htmlspecialchars(json_encode($nombre_completo)) ?>, <?= $pedido['id'] ?>); return false;"
                                               class="text-xs text-green-600 hover:text-green-700 font-medium">
                                                <i class="fas fa-map-marker-alt"></i> Ver mapa rápido
                                            </a>
                                        <?php endif; ?>
                                        <?php if ($pedido['observaciones']): ?>
                                            <div class="text-xs text-yellow-700 mt-1 bg-yellow-50 px-2 py-1 rounded">
                                                <i class="fas fa-sticky-note"></i> <?= htmlspecialchars(substr($pedido['observaciones'], 0, 50)) ?><?= strlen($pedido['observaciones']) > 50 ? '...' : '' ?>
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                    
                                    <!-- PRODUCTO SIMPLIFICADO -->
                                    <td class="px-3 py-2">
                                        <?php
                                        // Simplificar el producto
                                        $producto = $pedido['producto'];
                                        $producto_simple = $producto;
                                        
                                        // Detectar si es personalizado
                                        if (stripos($producto, 'personalizado') !== false) {
                                            // Extraer cantidad si está
                                            if (preg_match('/x?(\d+)/i', $producto, $matches)) {
                                                $cantidad = $matches[1];
                                                $producto_simple = "🎨 Personalizado x{$cantidad}";
                                            } else {
                                                $producto_simple = "🎨 Personalizado";
                                            }
                                        } else {
                                            // Simplificar nombres comunes
                                            $producto_simple = str_ireplace([
                                                'Jamón y Queso',
                                                'Jamon y Queso',
                                                'jamón y queso'
                                            ], 'J y Q', $producto);
                                            
                                            $producto_simple = str_ireplace([
                                                'Surtidos',
                                                'surtidos'
                                            ], 'Surtidos', $producto_simple);
                                        }
                                        ?>
                                        <div class="font-bold text-sm text-gray-800">
                                            <?= htmlspecialchars($producto_simple) ?>
                                        </div>
                                        <div class="flex gap-1 mt-1">
                                            <span class="badge <?= $pedido['forma_pago'] === 'Efectivo' ? 'bg-green-100 text-green-800' : 'bg-blue-100 text-blue-800' ?>">
                                                <?= $pedido['forma_pago'] === 'Efectivo' ? '💵' : '💳' ?>
                                            </span>
                                            <span class="badge <?= $pedido['ubicacion'] === 'Local 1' ? 'bg-purple-100 text-purple-800' : 'bg-orange-100 text-orange-800' ?>">
                                                <?= $pedido['ubicacion'] === 'Local 1' ? '🏪' : '🏭' ?>
                                            </span>
                                        </div>
                                    </td>
                                    
                                    <!-- ESTADO -->
                                    <td class="px-3 py-2 text-center">
                                        <div class="text-2xl mb-1"><?= $estado_icon ?></div>
                                        <div class="text-xs font-semibold text-gray-700">
                                            <?= $pedido['estado'] ?>
                                        </div>
                                    </td>
                                    
                                    <!-- FECHAS -->
                                    <td class="px-3 py-2">
                                        <div class="text-xs text-gray-700">
                                            <div class="mb-1">
                                                <strong>Pedido:</strong><br>
                                                <?= $fecha_pedido ?>
                                            </div>
                                            <?php if ($fecha_entrega): ?>
                                                <div class="text-green-700 font-semibold">
                                                    <strong>Entrega:</strong><br>
                                                    <?= date('d/m/Y', strtotime($fecha_entrega)) ?>
                                                </div>
                                            <?php else: ?>
                                                <div class="text-gray-400">
                                                    Sin fecha
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                    
                                    <!-- PRECIO -->
                                    <td class="px-3 py-2 text-right">
                                        <div class="font-bold text-green-600 text-base">
                                            $<?= number_format($pedido['precio'], 0, ',', '.') ?>
                                        </div>
                                    </td>
                                    
                                    <!-- ACCIONES -->
                                    <td class="px-3 py-2">
                                        <div class="flex gap-1 justify-center">
                                            <!-- Imprimir -->
                                            <button onclick="imprimir(<?= $pedido['id'] ?>)" 
                                                    class="btn-compact bg-orange-500 hover:bg-orange-600 text-white"
                                                    title="Imprimir">
                                                <i class="fas fa-print"></i>
                                            </button>
                                            
                                            <!-- WhatsApp -->
                                            <a href="https://wa.me/54<?= preg_replace('/[^0-9]/', '', $pedido['telefono']) ?>?text=Hola%20<?= urlencode($nombre_completo) ?>,%20tu%20pedido%20#<?= $pedido['id'] ?>%20está%20<?= urlencode(strtolower($pedido['estado'])) ?>" 
                                               target="_blank"
                                               class="btn-compact bg-green-500 hover:bg-green-600 text-white inline-flex items-center"
                                               title="WhatsApp">
                                                <i class="fab fa-whatsapp"></i>
                                            </a>
                                            
                                            <!-- Mapa Mini -->
                                            <?php if ($pedido['direccion']): ?>
                                                <a href="#" 
                                                   onclick="abrirMiniMapa(<?= htmlspecialchars(json_encode($pedido['direccion'])) ?>, <?= htmlspecialchars(json_encode($nombre_completo)) ?>, <?= $pedido['id'] ?>); return false;"
                                                   class="btn-compact bg-blue-500 hover:bg-blue-600 text-white inline-flex items-center"
                                                   title="Ver mapa">
                                                    <i class="fas fa-map-marker-alt"></i>
                                                </a>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php endif; ?>
    </main>

    <!-- MODAL MINI MAPA -->
    <div id="modalMapa" class="modal-mapa" onclick="cerrarMiniMapa(event)">
        <div class="modal-mapa-content" onclick="event.stopPropagation()">
            <div class="modal-mapa-header">
                <div>
                    <h3 class="text-lg font-bold" id="mapaTitulo">Ubicación</h3>
                    <p class="text-sm text-green-100" id="mapaDireccion"></p>
                </div>
                <button onclick="cerrarMiniMapa()" class="text-white hover:text-gray-200 text-2xl">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="modal-mapa-body" id="mapaBody">
                <!-- El iframe se cargará dinámicamente -->
            </div>
            <div class="bg-gray-50 p-3 flex gap-2">
                <button onclick="abrirGoogleMapsCompleto()" class="flex-1 btn bg-green-600 hover:bg-green-700 text-white py-2 rounded font-semibold">
                    <i class="fas fa-directions mr-2"></i>
                    Abrir en Google Maps
                </button>
                <button onclick="cerrarMiniMapa()" class="flex-1 btn bg-gray-500 hover:bg-gray-600 text-white py-2 rounded font-semibold">
                    <i class="fas fa-times mr-2"></i>
                    Cerrar
                </button>
            </div>
        </div>
    </div>

    <script>
    console.log('🏍️ Sistema Delivery Simple cargado');
    console.log('📦 Total pedidos: <?= $total ?>');
    
    // Imprimir individual
    function imprimir(pedidoId) {
        console.log('🖨️ Imprimiendo pedido #' + pedidoId);
        
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
        const pedidos = <?= json_encode(array_filter(array_column($pedidos, 'id'), fn($id) => true)) ?>;
        
        if (pedidos.length === 0) {
            alert('⚠️ No hay pedidos para imprimir');
            return;
        }
        
        if (pedidos.length > 15) {
            if (!confirm(`⚠️ Vas a imprimir ${pedidos.length} comandas.\n\n¿Continuar?`)) {
                return;
            }
        }
        
        console.log(`🖨️ Imprimiendo ${pedidos.length} comandas...`);
        
        pedidos.forEach((id, index) => {
            setTimeout(() => {
                imprimir(id);
            }, index * 600);
        });
    }
    
    // Auto-scroll suave
    document.querySelectorAll('.pedido-card').forEach((card, index) => {
        card.style.animationDelay = (index * 0.05) + 's';
    });
    </script>

</body>
</html>