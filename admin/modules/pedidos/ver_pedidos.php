<?php
// admin/modules/pedidos/ver_pedidos.php - VERSIÓN COMPLETA MEJORADA
require_once '../../config.php';
requireLogin();

$pdo = getConnection();

// ============================================
// PROCESAR ACCIONES POST
// ============================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $accion = $_POST['accion'] ?? '';
    $id = (int)($_POST['id'] ?? 0);
    
    try {
        switch ($accion) {
            case 'cambiar_estado':
                $estado = $_POST['estado'] ?? '';
                if ($id && $estado) {
                    $stmt = $pdo->prepare("UPDATE pedidos SET estado = ?, updated_at = NOW() WHERE id = ?");
                    $stmt->execute([$estado, $id]);
                    $_SESSION['mensaje'] = "✅ Estado actualizado";
                }
                break;
                
            case 'eliminar':
                if ($id) {
                    $stmt = $pdo->prepare("DELETE FROM pedidos WHERE id = ?");
                    $stmt->execute([$id]);
                    $_SESSION['mensaje'] = "✅ Pedido eliminado";
                }
                break;
                
            case 'marcar_impreso':
                if ($id) {
                    $stmt = $pdo->prepare("UPDATE pedidos SET impreso = 1, fecha_impresion = NOW() WHERE id = ?");
                    $stmt->execute([$id]);
                    $_SESSION['mensaje'] = "✅ Marcado como impreso";
                }
                break;
                
            // ACCIONES MASIVAS
            case 'accion_masiva':
                $pedidos = $_POST['pedidos'] ?? [];
                $tipo_accion = $_POST['tipo_accion'] ?? '';
                
                if (!empty($pedidos)) {
                    $placeholders = str_repeat('?,', count($pedidos) - 1) . '?';
                    
                    switch ($tipo_accion) {
                        case 'eliminar':
                            $stmt = $pdo->prepare("DELETE FROM pedidos WHERE id IN ($placeholders)");
                            $stmt->execute($pedidos);
                            $_SESSION['mensaje'] = "✅ " . count($pedidos) . " pedido(s) eliminado(s)";
                            break;
                            
                        case 'cambiar_estado':
                            $nuevo_estado = $_POST['nuevo_estado'] ?? '';
                            if ($nuevo_estado) {
                                $stmt = $pdo->prepare("UPDATE pedidos SET estado = ?, updated_at = NOW() WHERE id IN ($placeholders)");
                                $stmt->execute(array_merge([$nuevo_estado], $pedidos));
                                $_SESSION['mensaje'] = "✅ " . count($pedidos) . " pedido(s) → '$nuevo_estado'";
                            }
                            break;
                            
                        case 'marcar_impreso':
                            $stmt = $pdo->prepare("UPDATE pedidos SET impreso = 1, fecha_impresion = NOW() WHERE id IN ($placeholders)");
                            $stmt->execute($pedidos);
                            $_SESSION['mensaje'] = "✅ " . count($pedidos) . " marcado(s) como impreso";
                            break;
                    }
                }
                break;
        }
        
        header('Location: ' . $_SERVER['REQUEST_URI']);
        exit;
        
    } catch (Exception $e) {
        $_SESSION['error'] = "❌ Error: " . $e->getMessage();
    }
}

// ============================================
// FILTROS
// ============================================
$filtro_estado = $_GET['estado'] ?? '';
$filtro_modalidad = $_GET['modalidad'] ?? '';
$filtro_ubicacion = $_GET['ubicacion'] ?? '';
$fecha_desde = $_GET['fecha_desde'] ?? '';
$fecha_hasta = $_GET['fecha_hasta'] ?? '';
$busqueda = $_GET['buscar'] ?? '';
$orden = $_GET['orden'] ?? 'created_at DESC';

// Por defecto: pedidos de hoy
if (!$fecha_desde && !$fecha_hasta && !$busqueda && !$filtro_estado && !$filtro_modalidad && !$filtro_ubicacion) {
    $fecha_desde = date('Y-m-d');
    $fecha_hasta = date('Y-m-d');
}

// ============================================
// CONSTRUIR SQL
// ============================================
$sql = "SELECT p.*, cf.nombre as cliente_fijo_nombre, cf.apellido as cliente_fijo_apellido,
               TIMESTAMPDIFF(MINUTE, p.created_at, NOW()) as minutos_transcurridos,
               CASE 
                   WHEN TIMESTAMPDIFF(MINUTE, p.created_at, NOW()) > 120 THEN 'urgente'
                   WHEN TIMESTAMPDIFF(MINUTE, p.created_at, NOW()) > 60 THEN 'atencion'
                   ELSE 'normal'
               END as prioridad
        FROM pedidos p 
        LEFT JOIN clientes_fijos cf ON p.cliente_fijo_id = cf.id 
        WHERE 1=1";

$params = [];

if ($filtro_estado) {
    $sql .= " AND p.estado = ?";
    $params[] = $filtro_estado;
}

if ($filtro_modalidad) {
    $sql .= " AND p.modalidad = ?";
    $params[] = $filtro_modalidad;
}

if ($filtro_ubicacion) {
    $sql .= " AND p.ubicacion = ?";
    $params[] = $filtro_ubicacion;
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
    $sql .= " AND (p.nombre LIKE ? OR p.apellido LIKE ? OR p.telefono LIKE ? OR p.producto LIKE ? OR CAST(p.id AS CHAR) LIKE ? OR cf.nombre LIKE ? OR cf.apellido LIKE ?)";
    $busqueda_param = '%' . $busqueda . '%';
    $params = array_merge($params, array_fill(0, 7, $busqueda_param));
}

// Ordenamiento
$ordenes_validos = [
    'created_at DESC', 'created_at ASC', 'precio DESC', 'precio ASC', 'estado ASC', 'nombre ASC'
];

if (!in_array($orden, $ordenes_validos)) {
    $orden = 'created_at DESC';
}

$sql .= " ORDER BY " . $orden;

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$pedidos = $stmt->fetchAll();

// ============================================
// ESTADÍSTICAS
// ============================================
$total = count($pedidos);
$pendientes = count(array_filter($pedidos, fn($p) => $p['estado'] === 'Pendiente'));
$preparando = count(array_filter($pedidos, fn($p) => $p['estado'] === 'Preparando'));
$listos = count(array_filter($pedidos, fn($p) => $p['estado'] === 'Listo'));
$entregados = count(array_filter($pedidos, fn($p) => $p['estado'] === 'Entregado'));
$impresos = count(array_filter($pedidos, fn($p) => $p['impreso'] == 1));
$total_ventas = array_sum(array_column($pedidos, 'precio'));
$urgentes = count(array_filter($pedidos, fn($p) => $p['prioridad'] === 'urgente' && $p['estado'] !== 'Entregado'));
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ver Pedidos - Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; }
        
        /* ANIMACIONES */
        @keyframes slideIn {
            from { opacity: 0; transform: translateY(-10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        @keyframes pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.7; }
        }
        
        .urgente {
            animation: pulse 2s infinite;
            background: linear-gradient(90deg, #fee2e2 0%, #fef2f2 100%) !important;
            border-left: 4px solid #dc2626 !important;
        }
        
        .atencion {
            background: linear-gradient(90deg, #fef3c7 0%, #fffbeb 100%) !important;
            border-left: 4px solid #f59e0b !important;
        }
        
        /* TARJETAS */
        .pedido-card {
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            animation: slideIn 0.3s ease-out;
        }
        
        .pedido-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 12px 24px -10px rgba(0,0,0,0.15);
        }
        
        /* BADGES */
        .badge {
            display: inline-flex;
            align-items: center;
            padding: 4px 10px;
            border-radius: 9999px;
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.025em;
        }
        
        /* BOTONES */
        .btn {
            transition: all 0.2s;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
        }
        
        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        }
        
        .btn:active {
            transform: translateY(0);
        }
        
        /* TABS */
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
            background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%);
            color: white;
            box-shadow: 0 4px 12px rgba(59, 130, 246, 0.4);
        }
        
        .filter-tab:not(.active):hover {
            background: #f3f4f6;
            transform: translateY(-2px);
        }
        
        /* CHECKBOX CUSTOM */
        .checkbox-custom {
            width: 20px;
            height: 20px;
            cursor: pointer;
            accent-color: #3b82f6;
        }
        
        /* SCROLL SUAVE */
        .tabla-container {
            max-height: calc(100vh - 280px);
            overflow-y: auto;
            scroll-behavior: smooth;
        }
        
        .tabla-container::-webkit-scrollbar {
            width: 10px;
        }
        
        .tabla-container::-webkit-scrollbar-track {
            background: #f1f5f9;
            border-radius: 10px;
        }
        
        .tabla-container::-webkit-scrollbar-thumb {
            background: linear-gradient(180deg, #94a3b8, #64748b);
            border-radius: 10px;
        }
        
        .tabla-container::-webkit-scrollbar-thumb:hover {
            background: linear-gradient(180deg, #64748b, #475569);
        }
        
        /* SELECT MEJORADO */
        select {
            cursor: pointer;
            transition: all 0.2s;
        }
        
        select:hover {
            border-color: #3b82f6;
        }
        
        select:focus {
            outline: none;
            border-color: #3b82f6;
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
        }
        
        /* MODAL DE DETALLES */
        .modal-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.5);
            z-index: 9999;
            animation: fadeIn 0.2s;
        }
        
        .modal-overlay.active {
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .modal-content {
            background: white;
            border-radius: 16px;
            padding: 24px;
            max-width: 500px;
            width: 90%;
            max-height: 80vh;
            overflow-y: auto;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            animation: slideUp 0.3s;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }
        
        @keyframes slideUp {
            from { transform: translateY(20px); opacity: 0; }
            to { transform: translateY(0); opacity: 1; }
        }
        
        .modal-content::-webkit-scrollbar {
            width: 6px;
        }
        
        .modal-content::-webkit-scrollbar-thumb {
            background: #cbd5e1;
            border-radius: 3px;
        }
        
        .detalle-row {
            display: flex;
            padding: 12px;
            border-bottom: 1px solid #f1f5f9;
        }
        
        .detalle-row:hover {
            background: #f8fafc;
        }
        
        .detalle-label {
            font-weight: 600;
            color: #475569;
            min-width: 120px;
        }
        
        .detalle-valor {
            color: #1e293b;
            flex: 1;
        }
    </style>
</head>
<body class="bg-gradient-to-br from-gray-50 to-gray-100">
    
    <!-- ============================================ -->
    <!-- HEADER MEJORADO -->
    <!-- ============================================ -->
    <header class="bg-gradient-to-r from-blue-600 to-blue-700 text-white shadow-xl sticky top-0 z-50">
        <div class="max-w-7xl mx-auto px-4 py-4">
            <div class="flex justify-between items-center">
                <!-- TÍTULO -->
                <div class="flex items-center space-x-4">
                    <h1 class="text-2xl font-bold flex items-center gap-2">
                        <i class="fas fa-clipboard-list"></i>
                        ADMIN - PEDIDOS
                    </h1>
                    <?php if ($urgentes > 0): ?>
                        <span class="badge bg-red-500 text-white animate-pulse">
                            <i class="fas fa-exclamation-triangle"></i>
                            <?= $urgentes ?> URGENTES
                        </span>
                    <?php endif; ?>
                </div>
                
                <!-- STATS COMPACTOS -->
                <div class="flex space-x-6 text-sm">
                    <div class="text-center">
                        <div class="text-2xl font-bold"><?= $total ?></div>
                        <div class="text-blue-200">Total</div>
                    </div>
                    <div class="text-center">
                        <div class="text-2xl font-bold text-yellow-300"><?= $pendientes ?></div>
                        <div class="text-blue-200">Pendientes</div>
                    </div>
                    <div class="text-center">
                        <div class="text-2xl font-bold text-blue-300"><?= $preparando ?></div>
                        <div class="text-blue-200">Preparando</div>
                    </div>
                    <div class="text-center">
                        <div class="text-2xl font-bold text-green-300"><?= $listos ?></div>
                        <div class="text-blue-200">Listos</div>
                    </div>
                    <div class="text-center">
                        <div class="text-2xl font-bold text-emerald-200">$<?= number_format($total_ventas/1000, 1) ?>K</div>
                        <div class="text-blue-200">Ventas</div>
                    </div>
                </div>
                
                <!-- BOTONES -->
                <div class="flex items-center space-x-2">
                    <a href="delivery_simple.php" class="btn bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded-lg text-sm flex items-center gap-2">
                        <i class="fas fa-motorcycle"></i>
                        🏍️ Delivery
                    </a>
                    <a href="vista_ejecutiva.php" class="btn bg-purple-600 hover:bg-purple-700 text-white px-4 py-2 rounded-lg text-sm">
                        <i class="fas fa-table"></i>
                        Vista Ejecutiva
                    </a>
                    <a href="crear_pedido.php" class="btn bg-green-500 hover:bg-green-600 text-white px-4 py-2 rounded-lg text-sm">
                        <i class="fas fa-plus"></i>
                        Nuevo
                    </a>
                    <a href="../../index.php" class="btn bg-blue-500 hover:bg-blue-600 text-white px-4 py-2 rounded-lg text-sm">
                        <i class="fas fa-home"></i>
                    </a>
                    <a href="../../logout.php" class="btn bg-red-500 hover:bg-red-600 text-white px-4 py-2 rounded-lg text-sm">
                        <i class="fas fa-sign-out-alt"></i>
                    </a>
                </div>
            </div>
        </div>
    </header>

    <!-- ============================================ -->
    <!-- FILTROS Y TABS -->
    <!-- ============================================ -->
    <div class="bg-white shadow-md sticky top-[73px] z-40">
        <div class="max-w-7xl mx-auto px-4 py-3">
            
            <!-- TABS DE FILTRO RÁPIDO -->
            <div class="flex space-x-2 mb-4 flex-wrap gap-y-2">
                <a href="?fecha_desde=<?= $fecha_desde ?>&fecha_hasta=<?= $fecha_hasta ?>&ubicacion=<?= $filtro_ubicacion ?>" 
                   class="filter-tab <?= empty($filtro_estado) ? 'active' : 'bg-gray-100 text-gray-700' ?>">
                    <i class="fas fa-th-large"></i>
                    Todos (<?= $total ?>)
                </a>
                <a href="?estado=Pendiente&fecha_desde=<?= $fecha_desde ?>&fecha_hasta=<?= $fecha_hasta ?>&ubicacion=<?= $filtro_ubicacion ?>" 
                   class="filter-tab <?= $filtro_estado === 'Pendiente' ? 'active' : 'bg-yellow-100 text-yellow-800' ?>">
                    <i class="fas fa-clock"></i>
                    Pendientes (<?= $pendientes ?>)
                </a>
                <a href="?estado=Preparando&fecha_desde=<?= $fecha_desde ?>&fecha_hasta=<?= $fecha_hasta ?>&ubicacion=<?= $filtro_ubicacion ?>" 
                   class="filter-tab <?= $filtro_estado === 'Preparando' ? 'active' : 'bg-blue-100 text-blue-800' ?>">
                    <i class="fas fa-fire"></i>
                    Preparando (<?= $preparando ?>)
                </a>
                <a href="?estado=Listo&fecha_desde=<?= $fecha_desde ?>&fecha_hasta=<?= $fecha_hasta ?>&ubicacion=<?= $filtro_ubicacion ?>" 
                   class="filter-tab <?= $filtro_estado === 'Listo' ? 'active' : 'bg-green-100 text-green-800' ?>">
                    <i class="fas fa-check-circle"></i>
                    Listos (<?= $listos ?>)
                </a>
                <a href="?estado=Entregado&fecha_desde=<?= $fecha_desde ?>&fecha_hasta=<?= $fecha_hasta ?>&ubicacion=<?= $filtro_ubicacion ?>" 
                   class="filter-tab <?= $filtro_estado === 'Entregado' ? 'active' : 'bg-gray-100 text-gray-700' ?>">
                    <i class="fas fa-check-double"></i>
                    Entregados (<?= $entregados ?>)
                </a>
                
                <!-- SEPARADOR -->
                <span class="text-gray-300 mx-2">|</span>
                
                <!-- FILTRO POR UBICACIÓN -->
                <a href="?estado=<?= $filtro_estado ?>&fecha_desde=<?= $fecha_desde ?>&fecha_hasta=<?= $fecha_hasta ?>" 
                   class="filter-tab <?= empty($filtro_ubicacion) ? 'active' : 'bg-gray-100 text-gray-700' ?>">
                    <i class="fas fa-map-marked-alt"></i>
                    Todas
                </a>
                <a href="?estado=<?= $filtro_estado ?>&ubicacion=Local 1&fecha_desde=<?= $fecha_desde ?>&fecha_hasta=<?= $fecha_hasta ?>" 
                   class="filter-tab <?= $filtro_ubicacion === 'Local 1' ? 'active' : 'bg-purple-100 text-purple-800' ?>">
                    🏪 Local 1
                </a>
                <a href="?estado=<?= $filtro_estado ?>&ubicacion=Fábrica&fecha_desde=<?= $fecha_desde ?>&fecha_hasta=<?= $fecha_hasta ?>" 
                   class="filter-tab <?= $filtro_ubicacion === 'Fábrica' ? 'active' : 'bg-orange-100 text-orange-800' ?>">
                    🏭 Fábrica
                </a>
            </div>
            
            <!-- FILTROS AVANZADOS (COLAPSABLES) -->
            <details class="text-sm">
                <summary class="cursor-pointer text-gray-600 hover:text-gray-800 font-semibold mb-3 inline-flex items-center gap-2">
                    <i class="fas fa-sliders-h"></i>
                    Filtros Avanzados
                </summary>
                <form method="GET" class="grid grid-cols-1 md:grid-cols-6 gap-3 mt-2">
                    <input type="text" name="buscar" value="<?= htmlspecialchars($busqueda) ?>" 
                           placeholder="🔍 Buscar por nombre, tel, producto..." 
                           class="col-span-2 px-4 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                    
                    <input type="date" name="fecha_desde" value="<?= $fecha_desde ?>" 
                           class="px-4 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500">
                    
                    <input type="date" name="fecha_hasta" value="<?= $fecha_hasta ?>" 
                           class="px-4 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500">
                    
                    <select name="modalidad" class="px-4 py-2 border border-gray-300 rounded-lg text-sm">
                        <option value="">🚚 Todas modalidades</option>
                        <option value="Retiro" <?= $filtro_modalidad === 'Retiro' ? 'selected' : '' ?>>📦 Retiro</option>
                        <option value="Delivery" <?= $filtro_modalidad === 'Delivery' ? 'selected' : '' ?>>🏍️ Delivery</option>
                    </select>
                    
                    <div class="flex gap-2">
                        <button type="submit" class="btn flex-1 bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg text-sm font-semibold">
                            <i class="fas fa-search"></i>
                            Buscar
                        </button>
                        <a href="ver_pedidos.php" class="btn flex-1 bg-gray-500 hover:bg-gray-600 text-white px-4 py-2 rounded-lg text-sm font-semibold text-center">
                            <i class="fas fa-redo"></i>
                        </a>
                    </div>
                </form>
            </details>
        </div>
    </div>

    <!-- ============================================ -->
    <!-- MENSAJES -->
    <!-- ============================================ -->
    <?php if (isset($_SESSION['mensaje'])): ?>
        <div class="max-w-7xl mx-auto px-4 mt-4">
            <div class="bg-green-50 border-l-4 border-green-500 text-green-800 px-4 py-3 rounded-lg shadow animate-pulse">
                <i class="fas fa-check-circle mr-2"></i><?= htmlspecialchars($_SESSION['mensaje']) ?>
            </div>
        </div>
        <?php unset($_SESSION['mensaje']); ?>
    <?php endif; ?>

    <?php if (isset($_SESSION['error'])): ?>
        <div class="max-w-7xl mx-auto px-4 mt-4">
            <div class="bg-red-50 border-l-4 border-red-500 text-red-800 px-4 py-3 rounded-lg shadow">
                <i class="fas fa-exclamation-triangle mr-2"></i><?= htmlspecialchars($_SESSION['error']) ?>
            </div>
        </div>
        <?php unset($_SESSION['error']); ?>
    <?php endif; ?>

    <!-- ============================================ -->
    <!-- BARRA DE ACCIONES MASIVAS -->
    <!-- ============================================ -->
    <div class="max-w-7xl mx-auto px-4 mt-4">
        <div class="bg-gradient-to-r from-gray-800 to-gray-700 text-white rounded-lg shadow-lg p-3">
            <form method="POST" id="accionMasivaForm">
                <input type="hidden" name="accion" value="accion_masiva">
                <input type="hidden" name="tipo_accion" id="tipo_accion">
                <input type="hidden" name="nuevo_estado" id="nuevo_estado_hidden">
                
                <div class="flex items-center justify-between flex-wrap gap-3">
                    <div class="flex items-center gap-3">
                        <label class="flex items-center gap-2 cursor-pointer">
                            <input type="checkbox" id="selectAll" class="checkbox-custom">
                            <span class="font-semibold">Seleccionar Todos</span>
                        </label>
                        <span id="contador" class="badge bg-blue-500 text-white">0 seleccionados</span>
                    </div>
                    
                    <div class="flex items-center gap-2 flex-wrap">
                        <!-- CAMBIAR ESTADO -->
                        <select id="estado_masivo" class="px-3 py-2 border border-gray-600 rounded-lg text-sm bg-gray-700 text-white">
                            <option value="">Cambiar estado a...</option>
                            <option value="Pendiente">⏱️ Pendiente</option>
                            <option value="Preparando">🔥 Preparando</option>
                            <option value="Listo">✅ Listo</option>
                            <option value="Entregado">📦 Entregado</option>
                        </select>
                        
                        <button type="button" onclick="cambiarEstadoMasivo()" class="btn bg-blue-500 hover:bg-blue-600 text-white px-4 py-2 rounded-lg text-sm font-semibold">
                            <i class="fas fa-sync-alt"></i>
                            Cambiar Estado
                        </button>
                        
                        <!-- IMPRIMIR -->
                        <button type="button" onclick="imprimirSeleccionados()" class="btn bg-orange-500 hover:bg-orange-600 text-white px-4 py-2 rounded-lg text-sm font-semibold">
                            <i class="fas fa-print"></i>
                            Imprimir
                        </button>
                        
                        <!-- MARCAR IMPRESO -->
                        <button type="button" onclick="marcarImpresoMasivo()" class="btn bg-purple-500 hover:bg-purple-600 text-white px-4 py-2 rounded-lg text-sm font-semibold">
                            <i class="fas fa-stamp"></i>
                            Marcar Impreso
                        </button>
                        
                        <!-- ELIMINAR -->
                        <button type="button" onclick="eliminarMasivo()" class="btn bg-red-500 hover:bg-red-600 text-white px-4 py-2 rounded-lg text-sm font-semibold">
                            <i class="fas fa-trash-alt"></i>
                            Eliminar
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- ============================================ -->
    <!-- LISTA DE PEDIDOS -->
    <!-- ============================================ -->
    <main class="max-w-7xl mx-auto px-4 py-4">
        <?php if (empty($pedidos)): ?>
            <div class="bg-white rounded-xl shadow-lg p-12 text-center">
                <i class="fas fa-inbox text-gray-300 text-6xl mb-4"></i>
                <h2 class="text-2xl font-bold text-gray-600 mb-2">No hay pedidos</h2>
                <p class="text-gray-400 mb-6">Intenta ajustar los filtros o crear un nuevo pedido</p>
                <div class="flex gap-3 justify-center">
                    <a href="ver_pedidos.php" class="btn bg-blue-500 hover:bg-blue-600 text-white px-6 py-3 rounded-lg font-semibold">
                        <i class="fas fa-list"></i>
                        Ver Todos
                    </a>
                    <a href="crear_pedido.php" class="btn bg-green-500 hover:bg-green-600 text-white px-6 py-3 rounded-lg font-semibold">
                        <i class="fas fa-plus"></i>
                        Crear Pedido
                    </a>
                </div>
            </div>
        <?php else: ?>
            <div class="tabla-container">
                <div class="grid grid-cols-1 gap-4">
                    <?php foreach ($pedidos as $pedido): ?>
                        <?php
                        $nombre_completo = $pedido['cliente_fijo_nombre'] ? 
                            $pedido['cliente_fijo_nombre'] . ' ' . $pedido['cliente_fijo_apellido'] : 
                            $pedido['nombre'] . ' ' . $pedido['apellido'];
                        
                        // Clases dinámicas según prioridad
                        $clase_prioridad = '';
                        if ($pedido['prioridad'] === 'urgente' && $pedido['estado'] !== 'Entregado') {
                            $clase_prioridad = 'urgente';
                        } elseif ($pedido['prioridad'] === 'atencion' && $pedido['estado'] !== 'Entregado') {
                            $clase_prioridad = 'atencion';
                        }
                        
                        // Color del badge de estado
                        $estado_color = match($pedido['estado']) {
                            'Pendiente' => 'bg-yellow-100 text-yellow-800 border-yellow-300',
                            'Preparando' => 'bg-blue-100 text-blue-800 border-blue-300',
                            'Listo' => 'bg-green-100 text-green-800 border-green-300',
                            'Entregado' => 'bg-gray-100 text-gray-800 border-gray-300',
                            default => 'bg-white text-gray-800'
                        };
                        ?>
                        
                        <div class="pedido-card bg-white rounded-lg shadow-md hover:shadow-xl p-3 <?= $clase_prioridad ?>">
                            <div class="flex items-center gap-3">
                                
                                <!-- CHECKBOX -->
                                <input type="checkbox" name="pedidos[]" value="<?= $pedido['id'] ?>" 
                                       class="checkbox-pedido checkbox-custom"
                                       onchange="actualizarContador()">
                                
                                <!-- INFO COMPACTA EN UNA SOLA LÍNEA -->
                                <div class="flex-1 flex items-center justify-between gap-4">
                                    
                                    <!-- ID + TIEMPO + BADGES -->
                                    <div class="flex items-center gap-2 min-w-[120px]">
                                        <span class="text-xl font-bold text-blue-600">#<?= $pedido['id'] ?></span>
                                        <span class="text-xs text-gray-500"><?= $pedido['minutos_transcurridos'] ?>'</span>
                                        <?php if ($pedido['prioridad'] === 'urgente'): ?>
                                            <i class="fas fa-exclamation-triangle text-red-500 text-sm" title="URGENTE"></i>
                                        <?php endif; ?>
                                        <?php if (!$pedido['impreso']): ?>
                                            <i class="fas fa-print text-orange-500 text-xs" title="Sin imprimir"></i>
                                        <?php endif; ?>
                                    </div>
                                    
                                    <!-- CLIENTE -->
                                    <div class="flex-1 min-w-[150px]">
                                        <div class="font-semibold text-sm text-gray-800 truncate"><?= htmlspecialchars($nombre_completo) ?></div>
                                        <div class="text-xs text-gray-600"><?= htmlspecialchars($pedido['telefono']) ?></div>
                                    </div>
                                    
                                    <!-- PRODUCTO + MINI BADGES + BOTÓN OJO -->
                                    <div class="flex-1 min-w-[180px] flex items-center gap-2">
                                        <div class="flex-1">
                                            <div class="font-medium text-sm text-gray-800 truncate"><?= htmlspecialchars($pedido['producto']) ?></div>
                                            <div class="flex items-center gap-1 text-xs mt-0.5">
                                                <span title="<?= $pedido['modalidad'] ?>">
                                                    <?= $pedido['modalidad'] === 'Retiro' ? '📦' : '🏍️' ?>
                                                </span>
                                                <span title="<?= $pedido['forma_pago'] ?>">
                                                    <?= $pedido['forma_pago'] === 'Efectivo' ? '💵' : '💳' ?>
                                                </span>
                                                <span title="<?= $pedido['ubicacion'] ?>">
                                                    <?= $pedido['ubicacion'] === 'Local 1' ? '🏪' : '🏭' ?>
                                                </span>
                                                <?php if ($pedido['observaciones']): ?>
                                                    <i class="fas fa-sticky-note text-yellow-600" title="<?= htmlspecialchars($pedido['observaciones']) ?>"></i>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                        
                                        <!-- BOTÓN OJO -->
                                        <button onclick="verDetalles(<?= $pedido['id'] ?>)" 
                                                class="btn bg-blue-500 hover:bg-blue-600 text-white p-2 rounded text-sm"
                                                title="Ver detalles completos">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                    </div>
                                    
                                    <!-- ESTADO COMPACTO -->
                                    <div class="min-w-[130px]">
                                        <form method="POST" class="inline">
                                            <input type="hidden" name="accion" value="cambiar_estado">
                                            <input type="hidden" name="id" value="<?= $pedido['id'] ?>">
                                            <select name="estado" 
                                                    onchange="if(confirm('¿Cambiar estado?')) this.form.submit()" 
                                                    class="w-full text-xs font-semibold border rounded-lg px-2 py-1 <?= $estado_color ?> cursor-pointer">
                                                <option value="Pendiente" <?= $pedido['estado'] === 'Pendiente' ? 'selected' : '' ?>>⏱️ Pendiente</option>
                                                <option value="Preparando" <?= $pedido['estado'] === 'Preparando' ? 'selected' : '' ?>>🔥 Preparando</option>
                                                <option value="Listo" <?= $pedido['estado'] === 'Listo' ? 'selected' : '' ?>>✅ Listo</option>
                                                <option value="Entregado" <?= $pedido['estado'] === 'Entregado' ? 'selected' : '' ?>>📦 Entregado</option>
                                            </select>
                                        </form>
                                    </div>
                                    
                                    <!-- PRECIO -->
                                    <div class="text-right min-w-[80px]">
                                        <div class="text-lg font-bold text-green-600">
                                            $<?= number_format($pedido['precio']/1000, 0) ?>K
                                        </div>
                                    </div>
                                    
                                    <!-- ACCIONES COMPACTAS -->
                                    <div class="flex items-center gap-1">
                                        <!-- IMPRIMIR -->
                                        <button onclick="imprimir(<?= $pedido['id'] ?>)" 
                                                class="btn bg-orange-500 hover:bg-orange-600 text-white p-2 rounded text-xs"
                                                title="Imprimir">
                                            <i class="fas fa-print"></i>
                                        </button>
                                        
                                        <!-- WHATSAPP -->
                                        <?php if ($pedido['telefono']): ?>
                                            <a href="https://wa.me/54<?= preg_replace('/[^0-9]/', '', $pedido['telefono']) ?>?text=Hola%20<?= urlencode($nombre_completo) ?>,%20tu%20pedido%20#<?= $pedido['id'] ?>%20está%20<?= urlencode(strtolower($pedido['estado'])) ?>" 
                                               target="_blank"
                                               class="btn bg-green-500 hover:bg-green-600 text-white p-2 rounded text-xs"
                                               title="WhatsApp">
                                                <i class="fab fa-whatsapp"></i>
                                            </a>
                                        <?php endif; ?>
                                        
                                        <!-- ELIMINAR -->
                                        <form method="POST" class="inline" onsubmit="return confirm('⚠️ ¿ELIMINAR #<?= $pedido['id'] ?>?')">
                                            <input type="hidden" name="accion" value="eliminar">
                                            <input type="hidden" name="id" value="<?= $pedido['id'] ?>">
                                            <button type="submit" class="btn bg-red-500 hover:bg-red-600 text-white p-2 rounded text-xs" title="Eliminar">
                                                <i class="fas fa-trash-alt"></i>
                                            </button>
                                        </form>
                                    </div>
                                    
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
            
            <!-- MODAL DE DETALLES -->
            <div id="modalDetalles" class="modal-overlay" onclick="cerrarModal(event)">
                <div class="modal-content" onclick="event.stopPropagation()">
                    <div class="flex justify-between items-center mb-4 pb-4 border-b-2 border-blue-500">
                        <h3 class="text-2xl font-bold text-gray-800 flex items-center gap-2">
                            <i class="fas fa-receipt text-blue-600"></i>
                            Detalles del Pedido
                        </h3>
                        <button onclick="cerrarModal()" class="text-gray-500 hover:text-gray-800 text-2xl">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>
                    <div id="modalBody"></div>
                </div>
            </div>
            
        <?php endif; ?>
    </main>

    <!-- DATOS DE PEDIDOS EN JSON PARA JAVASCRIPT -->
    <script>
    const pedidosData = <?= json_encode(array_map(function($p) {
        return [
            'id' => $p['id'],
            'nombre' => $p['cliente_fijo_nombre'] ?: $p['nombre'],
            'apellido' => $p['cliente_fijo_apellido'] ?: $p['apellido'],
            'telefono' => $p['telefono'],
            'direccion' => $p['direccion'] ?? '',
            'producto' => $p['producto'],
            'precio' => $p['precio'],
            'modalidad' => $p['modalidad'],
            'forma_pago' => $p['forma_pago'],
            'ubicacion' => $p['ubicacion'],
            'estado' => $p['estado'],
            'observaciones' => $p['observaciones'] ?? '',
            'created_at' => $p['created_at'],
            'minutos' => $p['minutos_transcurridos'],
            'impreso' => $p['impreso']
        ];
    }, $pedidos)) ?>;
    </script>

    <!-- ============================================ -->
    <!-- JAVASCRIPT -->
    <!-- ============================================ -->
    <script>
    console.log('🎯 Admin Ver Pedidos - Sistema Completo Cargado');
    console.log('📊 Total pedidos: <?= $total ?>');
    console.log('⚡ Pedidos urgentes: <?= $urgentes ?>');
    
    // ============================================
    // MODAL DE DETALLES
    // ============================================
    
    function verDetalles(pedidoId) {
        const pedido = pedidosData.find(p => p.id == pedidoId);
        
        if (!pedido) {
            alert('❌ No se encontraron datos del pedido');
            return;
        }
        
        const nombreCompleto = `${pedido.nombre} ${pedido.apellido}`.trim();
        const fechaCreacion = new Date(pedido.created_at.replace(' ', 'T'));
        const fechaFormateada = fechaCreacion.toLocaleString('es-AR', {
            day: '2-digit',
            month: '2-digit',
            year: 'numeric',
            hour: '2-digit',
            minute: '2-digit'
        });
        
        // Determinar estado visual
        let estadoBadge = '';
        switch(pedido.estado) {
            case 'Pendiente':
                estadoBadge = '<span class="badge bg-yellow-500 text-white">⏱️ Pendiente</span>';
                break;
            case 'Preparando':
                estadoBadge = '<span class="badge bg-blue-500 text-white">🔥 Preparando</span>';
                break;
            case 'Listo':
                estadoBadge = '<span class="badge bg-green-500 text-white">✅ Listo</span>';
                break;
            case 'Entregado':
                estadoBadge = '<span class="badge bg-gray-500 text-white">📦 Entregado</span>';
                break;
        }
        
        const html = `
            <div class="space-y-2">
                <!-- ID y Estado -->
                <div class="bg-gradient-to-r from-blue-50 to-blue-100 rounded-lg p-4 mb-4">
                    <div class="flex justify-between items-center">
                        <div>
                            <div class="text-sm text-gray-600">Pedido</div>
                            <div class="text-3xl font-bold text-blue-600">#${pedido.id}</div>
                        </div>
                        <div class="text-right">
                            ${estadoBadge}
                            <div class="text-xs text-gray-600 mt-1">
                                ${pedido.impreso ? '✅ Impreso' : '⚠️ Sin imprimir'}
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Cliente -->
                <div class="detalle-row">
                    <div class="detalle-label">
                        <i class="fas fa-user mr-2"></i>Cliente
                    </div>
                    <div class="detalle-valor font-semibold">${nombreCompleto}</div>
                </div>
                
                <!-- Teléfono -->
                <div class="detalle-row">
                    <div class="detalle-label">
                        <i class="fas fa-phone mr-2"></i>Teléfono
                    </div>
                    <div class="detalle-valor">
                        <a href="https://wa.me/54${pedido.telefono.replace(/[^0-9]/g, '')}" 
                           target="_blank"
                           class="text-green-600 hover:text-green-700 font-medium">
                            ${pedido.telefono}
                            <i class="fab fa-whatsapp ml-1"></i>
                        </a>
                    </div>
                </div>
                
                <!-- Dirección -->
                ${pedido.direccion ? `
                <div class="detalle-row">
                    <div class="detalle-label">
                        <i class="fas fa-map-marker-alt mr-2"></i>Dirección
                    </div>
                    <div class="detalle-valor">${pedido.direccion}</div>
                </div>
                ` : ''}
                
                <!-- Producto -->
                <div class="detalle-row bg-yellow-50">
                    <div class="detalle-label">
                        <i class="fas fa-hamburger mr-2"></i>Producto
                    </div>
                    <div class="detalle-valor font-bold text-lg">${pedido.producto}</div>
                </div>
                
                <!-- Precio -->
                <div class="detalle-row">
                    <div class="detalle-label">
                        <i class="fas fa-dollar-sign mr-2"></i>Precio
                    </div>
                    <div class="detalle-valor text-2xl font-bold text-green-600">
                        ${pedido.precio.toLocaleString('es-AR')}
                    </div>
                </div>
                
                <!-- Modalidad -->
                <div class="detalle-row">
                    <div class="detalle-label">
                        <i class="fas fa-shipping-fast mr-2"></i>Modalidad
                    </div>
                    <div class="detalle-valor">
                        <span class="badge ${pedido.modalidad === 'Retiro' ? 'bg-blue-100 text-blue-800' : 'bg-green-100 text-green-800'}">
                            ${pedido.modalidad === 'Retiro' ? '📦' : '🏍️'} ${pedido.modalidad}
                        </span>
                    </div>
                </div>
                
                <!-- Forma de Pago -->
                <div class="detalle-row">
                    <div class="detalle-label">
                        <i class="fas fa-credit-card mr-2"></i>Pago
                    </div>
                    <div class="detalle-valor">
                        <span class="badge bg-purple-100 text-purple-800">
                            ${pedido.forma_pago === 'Efectivo' ? '💵' : '💳'} ${pedido.forma_pago}
                        </span>
                    </div>
                </div>
                
                <!-- Ubicación -->
                <div class="detalle-row">
                    <div class="detalle-label">
                        <i class="fas fa-store mr-2"></i>Ubicación
                    </div>
                    <div class="detalle-valor">
                        <span class="badge ${pedido.ubicacion === 'Local 1' ? 'bg-purple-100 text-purple-800' : 'bg-orange-100 text-orange-800'}">
                            ${pedido.ubicacion === 'Local 1' ? '🏪' : '🏭'} ${pedido.ubicacion}
                        </span>
                    </div>
                </div>
                
                <!-- Observaciones -->
                ${pedido.observaciones ? `
                <div class="detalle-row bg-yellow-50 border-l-4 border-yellow-500">
                    <div class="detalle-label">
                        <i class="fas fa-sticky-note mr-2"></i>Observaciones
                    </div>
                    <div class="detalle-valor font-medium">${pedido.observaciones}</div>
                </div>
                ` : ''}
                
                <!-- Fecha y Tiempo -->
                <div class="detalle-row">
                    <div class="detalle-label">
                        <i class="far fa-clock mr-2"></i>Creado
                    </div>
                    <div class="detalle-valor">
                        ${fechaFormateada}
                        <span class="text-sm text-gray-500 ml-2">(${pedido.minutos} minutos)</span>
                    </div>
                </div>
            </div>
            
            <!-- Acciones del Modal -->
            <div class="mt-6 pt-4 border-t flex gap-2">
                <button onclick="imprimir(${pedido.id}); cerrarModal();" 
                        class="btn flex-1 bg-orange-500 hover:bg-orange-600 text-white py-3 rounded-lg font-semibold">
                    <i class="fas fa-print mr-2"></i>Imprimir
                </button>
                <button onclick="cerrarModal()" 
                        class="btn flex-1 bg-gray-500 hover:bg-gray-600 text-white py-3 rounded-lg font-semibold">
                    <i class="fas fa-times mr-2"></i>Cerrar
                </button>
            </div>
        `;
        
        document.getElementById('modalBody').innerHTML = html;
        document.getElementById('modalDetalles').classList.add('active');
        document.body.style.overflow = 'hidden';
        
        console.log('👁️ Viendo detalles del pedido #' + pedidoId);
    }
    
    function cerrarModal(event) {
        if (event && event.target !== event.currentTarget) return;
        
        document.getElementById('modalDetalles').classList.remove('active');
        document.body.style.overflow = 'auto';
        console.log('✅ Modal cerrado');
    }
    
    // Cerrar modal con ESC
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            cerrarModal();
        }
    });
    
    // ============================================
    // SELECCIÓN DE PEDIDOS
    // ============================================
    
    // Seleccionar todos
    document.getElementById('selectAll')?.addEventListener('change', function() {
        const checkboxes = document.querySelectorAll('.checkbox-pedido');
        checkboxes.forEach(cb => cb.checked = this.checked);
        actualizarContador();
    });
    
    // Actualizar contador de seleccionados
    function actualizarContador() {
        const seleccionados = document.querySelectorAll('.checkbox-pedido:checked').length;
        const contador = document.getElementById('contador');
        
        if (contador) {
            contador.textContent = seleccionados + ' seleccionados';
            contador.className = seleccionados > 0 ? 
                'badge bg-green-500 text-white animate-pulse' : 
                'badge bg-blue-500 text-white';
        }
        
        // Actualizar checkbox "Seleccionar todos"
        const selectAll = document.getElementById('selectAll');
        const total = document.querySelectorAll('.checkbox-pedido').length;
        if (selectAll) {
            selectAll.checked = seleccionados === total && total > 0;
            selectAll.indeterminate = seleccionados > 0 && seleccionados < total;
        }
    }
    
    // Obtener IDs seleccionados
    function getSeleccionados() {
        const checks = document.querySelectorAll('.checkbox-pedido:checked');
        return Array.from(checks).map(c => c.value);
    }
    
    // ============================================
    // ACCIONES MASIVAS
    // ============================================
    
    function cambiarEstadoMasivo() {
        const seleccionados = getSeleccionados();
        const nuevoEstado = document.getElementById('estado_masivo').value;
        
        if (seleccionados.length === 0) {
            alert('⚠️ Debes seleccionar al menos un pedido');
            return;
        }
        
        if (!nuevoEstado) {
            alert('⚠️ Debes seleccionar un estado');
            return;
        }
        
        if (confirm(`¿Cambiar ${seleccionados.length} pedido(s) a "${nuevoEstado}"?`)) {
            const form = document.getElementById('accionMasivaForm');
            document.getElementById('tipo_accion').value = 'cambiar_estado';
            document.getElementById('nuevo_estado_hidden').value = nuevoEstado;
            
            // Agregar inputs hidden con los IDs
            seleccionados.forEach(id => {
                const input = document.createElement('input');
                input.type = 'hidden';
                input.name = 'pedidos[]';
                input.value = id;
                form.appendChild(input);
            });
            
            form.submit();
        }
    }
    
    function marcarImpresoMasivo() {
        const seleccionados = getSeleccionados();
        
        if (seleccionados.length === 0) {
            alert('⚠️ Debes seleccionar al menos un pedido');
            return;
        }
        
        if (confirm(`¿Marcar ${seleccionados.length} pedido(s) como impreso?`)) {
            const form = document.getElementById('accionMasivaForm');
            document.getElementById('tipo_accion').value = 'marcar_impreso';
            
            seleccionados.forEach(id => {
                const input = document.createElement('input');
                input.type = 'hidden';
                input.name = 'pedidos[]';
                input.value = id;
                form.appendChild(input);
            });
            
            form.submit();
        }
    }
    
    function eliminarMasivo() {
        const seleccionados = getSeleccionados();
        
        if (seleccionados.length === 0) {
            alert('⚠️ Debes seleccionar al menos un pedido');
            return;
        }
        
        if (confirm(`⚠️ ¿ELIMINAR ${seleccionados.length} pedido(s)?\n\n⚠️ ESTA ACCIÓN NO SE PUEDE DESHACER ⚠️`)) {
            const form = document.getElementById('accionMasivaForm');
            document.getElementById('tipo_accion').value = 'eliminar';
            
            seleccionados.forEach(id => {
                const input = document.createElement('input');
                input.type = 'hidden';
                input.name = 'pedidos[]';
                input.value = id;
                form.appendChild(input);
            });
            
            form.submit();
        }
    }
    
    // ============================================
    // IMPRESIÓN
    // ============================================
    
    function imprimir(pedidoId) {
        console.log('🖨️ Imprimiendo comanda - Pedido #' + pedidoId);
        
        const url = `../impresion/comanda_simple.php?pedido=${pedidoId}`;
        const ventana = window.open(url, '_blank', 'width=320,height=500,scrollbars=yes');
        
        if (!ventana) {
            alert('❌ Error: No se pudo abrir la ventana.\n\n💡 Habilita las ventanas emergentes en tu navegador.');
            return false;
        }
        
        ventana.focus();
        console.log('✅ Comanda abierta (80mm)');
        return true;
    }
    
    function imprimirSeleccionados() {
        const seleccionados = getSeleccionados();
        
        if (seleccionados.length === 0) {
            alert('⚠️ Debes seleccionar al menos un pedido');
            return;
        }
        
        if (seleccionados.length > 10) {
            if (!confirm(`⚠️ Vas a imprimir ${seleccionados.length} comandas.\n\n¿Continuar?`)) {
                return;
            }
        }
        
        console.log(`🖨️ Imprimiendo ${seleccionados.length} comandas...`);
        
        // Imprimir cada pedido con delay
        seleccionados.forEach((id, index) => {
            setTimeout(() => {
                imprimir(id);
            }, index * 500); // 500ms entre cada impresión
        });
    }
    
    // ============================================
    // AUTO-REFRESH (OPCIONAL)
    // ============================================
    
    // Descomentar para auto-refresh cada 60 segundos
    // setInterval(() => {
    //     console.log('🔄 Auto-refresh...');
    //     location.reload();
    // }, 60000);
    
    // ============================================
    // INICIALIZACIÓN
    // ============================================
    
    document.addEventListener('DOMContentLoaded', function() {
        console.log('✅ Sistema inicializado correctamente');
        actualizarContador();
        
        // Scroll suave al hacer clic en un pedido urgente
        document.querySelectorAll('.urgente').forEach(card => {
            card.style.scrollMarginTop = '150px';
        });
    });
    
    // Atajos de teclado
    document.addEventListener('keydown', function(e) {
        // Ctrl + A: Seleccionar todos
        if (e.ctrlKey && e.key === 'a') {
            e.preventDefault();
            const selectAll = document.getElementById('selectAll');
            if (selectAll) {
                selectAll.checked = !selectAll.checked;
                selectAll.dispatchEvent(new Event('change'));
            }
        }
        
        // Ctrl + P: Imprimir seleccionados
        if (e.ctrlKey && e.key === 'p') {
            e.preventDefault();
            imprimirSeleccionados();
        }
    });
    
    console.log('⌨️ Atajos: Ctrl+A (Seleccionar todos) | Ctrl+P (Imprimir)');
    </script>

</body>
</html>