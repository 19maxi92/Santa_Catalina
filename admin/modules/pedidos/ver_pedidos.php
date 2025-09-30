<?php
// admin/modules/pedidos/ver_pedidos.php
require_once '../../config.php';
requireLogin();

$pdo = getConnection();

// Procesar acciones POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $accion = $_POST['accion'] ?? '';
    $id = (int)($_POST['id'] ?? 0);
    
    try {
        switch ($accion) {
            case 'cambiar_estado':
                $estado = $_POST['estado'] ?? '';
                if ($id && $estado) {
                    $stmt = $pdo->prepare("UPDATE pedidos SET estado = ? WHERE id = ?");
                    $stmt->execute([$estado, $id]);
                    $_SESSION['mensaje'] = "Estado actualizado correctamente";
                }
                break;
                
            case 'eliminar':
                if ($id) {
                    $stmt = $pdo->prepare("DELETE FROM pedidos WHERE id = ?");
                    $stmt->execute([$id]);
                    $_SESSION['mensaje'] = "Pedido eliminado correctamente";
                }
                break;
                
            case 'marcar_impreso':
                if ($id) {
                    $stmt = $pdo->prepare("UPDATE pedidos SET impreso = 1, fecha_impresion = NOW() WHERE id = ?");
                    $stmt->execute([$id]);
                    $_SESSION['mensaje'] = "Pedido marcado como impreso";
                }
                break;
                
            case 'reimprimir':
                if ($id) {
                    $stmt = $pdo->prepare("UPDATE pedidos SET impreso = 1, fecha_impresion = NOW() WHERE id = ?");
                    $stmt->execute([$id]);
                    $_SESSION['mensaje'] = "Pedido reimpreso correctamente";
                }
                break;
        }
        
        // Redireccionar para evitar reenvío de formulario
        header('Location: ' . $_SERVER['REQUEST_URI']);
        exit;
        
    } catch (Exception $e) {
        $_SESSION['error'] = "Error: " . $e->getMessage();
    }
}

// Obtener parámetros de filtro (NOMBRES ORIGINALES RESTAURADOS)
$filtro_estado = $_GET['estado'] ?? '';
$filtro_modalidad = $_GET['modalidad'] ?? '';
$filtro_ubicacion = $_GET['ubicacion'] ?? '';
$fecha_desde = $_GET['fecha_desde'] ?? '';
$fecha_hasta = $_GET['fecha_hasta'] ?? '';
$busqueda = $_GET['buscar'] ?? ''; // Nombre original: 'buscar'
$orden = $_GET['orden'] ?? 'created_at DESC';

// Si no hay filtros, mostrar pedidos de hoy por defecto
if (!$fecha_desde && !$fecha_hasta && !$busqueda && !$filtro_estado && !$filtro_modalidad && !$filtro_ubicacion) {
    $fecha_desde = date('Y-m-d');
    $fecha_hasta = date('Y-m-d');
}

// Construir consulta SQL
$sql = "SELECT p.*, cf.nombre as cliente_fijo_nombre, cf.apellido as cliente_fijo_apellido 
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
    $sql .= " AND (p.nombre LIKE ? OR p.apellido LIKE ? OR p.telefono LIKE ? OR p.producto LIKE ? OR cf.nombre LIKE ? OR cf.apellido LIKE ?)";
    $busqueda_param = '%' . $busqueda . '%';
    $params = array_merge($params, [$busqueda_param, $busqueda_param, $busqueda_param, $busqueda_param, $busqueda_param, $busqueda_param]);
}

// Ordenamiento válido
$ordenes_validos = [
    'created_at DESC' => 'Más recientes',
    'created_at ASC' => 'Más antiguos', 
    'precio DESC' => 'Mayor precio',
    'precio ASC' => 'Menor precio',
    'estado ASC' => 'Por estado',
    'nombre ASC' => 'Por nombre'
];

if (!array_key_exists($orden, $ordenes_validos)) {
    $orden = 'created_at DESC';
}

$sql .= " ORDER BY " . $orden;

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$pedidos = $stmt->fetchAll();

// Estadísticas rápidas
$stats_sql = "SELECT 
    COUNT(*) as total,
    COUNT(CASE WHEN estado = 'Pendiente' THEN 1 END) as pendientes,
    COUNT(CASE WHEN estado = 'Preparando' THEN 1 END) as preparando,
    COUNT(CASE WHEN estado = 'Listo' THEN 1 END) as listos,
    COUNT(CASE WHEN estado = 'Entregado' THEN 1 END) as entregados,
    COUNT(CASE WHEN impreso = 1 THEN 1 END) as impresos,
    SUM(precio) as total_ventas
    FROM pedidos 
    WHERE DATE(created_at) = CURDATE()";

$stats = $pdo->query($stats_sql)->fetch();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ver Pedidos - Santa Catalina</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="bg-gray-100">
    <div class="container mx-auto px-4 py-6">
        
            <div class="flex justify-between items-center mb-6">
            <h1 class="text-3xl font-bold text-gray-800">
                <i class="fas fa-list-alt text-blue-500 mr-2"></i>Pedidos
            </h1>
            <div class="flex space-x-3">
                <a href="https://santacatalina.online/admin/" class="bg-purple-500 hover:bg-purple-600 text-white px-4 py-2 rounded">
                    <i class="fas fa-home mr-2"></i>Volver al Inicio
                </a>
                <a href="crear_pedido.php" class="bg-green-500 hover:bg-green-600 text-white px-4 py-2 rounded">
                    <i class="fas fa-plus mr-2"></i>Nuevo Pedido
                </a>
            </div>
        </div>

        <!-- Mensajes -->
        <?php if (isset($_SESSION['mensaje'])): ?>
            <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4">
                <?= htmlspecialchars($_SESSION['mensaje']) ?>
            </div>
            <?php unset($_SESSION['mensaje']); ?>
        <?php endif; ?>

        <?php if (isset($_SESSION['error'])): ?>
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
                <?= htmlspecialchars($_SESSION['error']) ?>
            </div>
            <?php unset($_SESSION['error']); ?>
        <?php endif; ?>

        <!-- Estadísticas del día -->
        <div class="grid grid-cols-2 md:grid-cols-7 gap-4 mb-6">
            <div class="bg-white p-4 rounded-lg shadow text-center">
                <div class="text-2xl font-bold text-gray-700"><?= $stats['total'] ?></div>
                <div class="text-sm text-gray-500">Total Hoy</div>
            </div>
            <div class="bg-yellow-100 p-4 rounded-lg shadow text-center">
                <div class="text-2xl font-bold text-yellow-700"><?= $stats['pendientes'] ?></div>
                <div class="text-sm text-yellow-600">Pendientes</div>
            </div>
            <div class="bg-blue-100 p-4 rounded-lg shadow text-center">
                <div class="text-2xl font-bold text-blue-700"><?= $stats['preparando'] ?></div>
                <div class="text-sm text-blue-600">Preparando</div>
            </div>
            <div class="bg-green-100 p-4 rounded-lg shadow text-center">
                <div class="text-2xl font-bold text-green-700"><?= $stats['listos'] ?></div>
                <div class="text-sm text-green-600">Listos</div>
            </div>
            <div class="bg-gray-100 p-4 rounded-lg shadow text-center">
                <div class="text-2xl font-bold text-gray-700"><?= $stats['entregados'] ?></div>
                <div class="text-sm text-gray-600">Entregados</div>
            </div>
            <div class="bg-purple-100 p-4 rounded-lg shadow text-center">
                <div class="text-2xl font-bold text-purple-700"><?= $stats['impresos'] ?></div>
                <div class="text-sm text-purple-600">Impresos</div>
            </div>
            <div class="bg-emerald-100 p-4 rounded-lg shadow text-center">
                <div class="text-2xl font-bold text-emerald-700">$<?= number_format($stats['total_ventas'], 0, ',', '.') ?></div>
                <div class="text-sm text-emerald-600">Ventas</div>
            </div>
        </div>

        <!-- FILTROS MEJORADOS CON FILTROS RÁPIDOS -->
        <div class="bg-white rounded-lg shadow mb-6 p-6">
            <form method="GET" id="filtrosForm">
                
                <!-- SECCIÓN: Filtros rápidos de fecha -->
                <div class="mb-4">
                    <h4 class="font-semibold text-gray-700 mb-3">
                        <i class="fas fa-calendar-week mr-2"></i>Filtros rápidos:
                    </h4>
                    <div class="flex flex-wrap gap-2">
                        <button type="button" onclick="setFiltroRapido('hoy')" 
                                class="bg-blue-100 hover:bg-blue-200 text-blue-800 px-3 py-1 rounded text-sm">
                            <i class="fas fa-calendar-day mr-1"></i>Hoy
                        </button>
                        <button type="button" onclick="setFiltroRapido('ayer')" 
                                class="bg-gray-100 hover:bg-gray-200 text-gray-800 px-3 py-1 rounded text-sm">
                            <i class="fas fa-calendar-minus mr-1"></i>Ayer
                        </button>
                        <button type="button" onclick="setFiltroRapido('semana')" 
                                class="bg-green-100 hover:bg-green-200 text-green-800 px-3 py-1 rounded text-sm">
                            <i class="fas fa-calendar-week mr-1"></i>Esta semana
                        </button>
                        <button type="button" onclick="setFiltroRapido('mes')" 
                                class="bg-purple-100 hover:bg-purple-200 text-purple-800 px-3 py-1 rounded text-sm">
                            <i class="fas fa-calendar mr-1"></i>Este mes
                        </button>
                        <button type="button" onclick="setFiltroRapido('todo')" 
                                class="bg-orange-100 hover:bg-orange-200 text-orange-800 px-3 py-1 rounded text-sm">
                            <i class="fas fa-calendar-alt mr-1"></i>Todo
                        </button>
                    </div>
                </div>

                <!-- Filtros principales -->
                <div class="grid grid-cols-1 md:grid-cols-6 gap-4 mb-4">
                    <!-- Buscador -->
                    <div class="md:col-span-2">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Buscar:</label>
                        <input type="text" name="buscar" value="<?= htmlspecialchars($busqueda) ?>" 
                               placeholder="Cliente, producto, teléfono..." 
                               class="w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500">
                    </div>
                    
                    <!-- Estado -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Estado:</label>
                        <select name="estado" class="w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500">
                            <option value="">Todos</option>
                            <option value="Pendiente" <?= $filtro_estado === 'Pendiente' ? 'selected' : '' ?>>Pendiente</option>
                            <option value="Preparando" <?= $filtro_estado === 'Preparando' ? 'selected' : '' ?>>Preparando</option>
                            <option value="Listo" <?= $filtro_estado === 'Listo' ? 'selected' : '' ?>>Listo</option>
                            <option value="Entregado" <?= $filtro_estado === 'Entregado' ? 'selected' : '' ?>>Entregado</option>
                        </select>
                    </div>
                    
                    <!-- Fecha desde -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Desde:</label>
                        <input type="date" name="fecha_desde" value="<?= htmlspecialchars($fecha_desde) ?>" 
                               class="w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500">
                    </div>
                    
                    <!-- Fecha hasta -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Hasta:</label>
                        <input type="date" name="fecha_hasta" value="<?= htmlspecialchars($fecha_hasta) ?>" 
                               class="w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500">
                    </div>
                    
                    <!-- Modalidad -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Modalidad:</label>
                        <select name="modalidad" class="w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500">
                            <option value="">Todas</option>
                            <option value="Retira" <?= $filtro_modalidad === 'Retira' ? 'selected' : '' ?>>Retira</option>
                            <option value="Delivery" <?= $filtro_modalidad === 'Delivery' ? 'selected' : '' ?>>Delivery</option>
                        </select>
                    </div>
                </div>

                <!-- FILA: Filtro de ubicación -->
                <div class="grid grid-cols-1 md:grid-cols-6 gap-4 mb-4">
                    <!-- Ubicación -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Ubicación:</label>
                        <select name="ubicacion" class="w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-purple-500">
                            <option value="">Todas las ubicaciones</option>
                            <option value="Local 1" <?= $filtro_ubicacion === 'Local 1' ? 'selected' : '' ?>>🏪 Local 1</option>
                            <option value="Fábrica" <?= $filtro_ubicacion === 'Fábrica' ? 'selected' : '' ?>>🏭 Fábrica</option>
                        </select>
                    </div>
                    
                    <!-- Ordenamiento -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Ordenar por:</label>
                        <select name="orden" class="w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500">
                            <?php foreach ($ordenes_validos as $valor => $nombre): ?>
                                <option value="<?= $valor ?>" <?= $orden === $valor ? 'selected' : '' ?>><?= $nombre ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <!-- Botones -->
                    <div class="md:col-span-2 flex items-end space-x-2">
                        <button type="submit" class="bg-blue-500 hover:bg-blue-600 text-white px-6 py-2 rounded-lg">
                            <i class="fas fa-search mr-2"></i>Filtrar
                        </button>
                        <button type="button" onclick="limpiarFiltros()" 
                                class="bg-gray-500 hover:bg-gray-600 text-white px-6 py-2 rounded-lg">
                            <i class="fas fa-times mr-2"></i>Limpiar
                        </button>
                    </div>
                </div>
            </form>
            
            <!-- Filtros rápidos por ubicación -->
            <div class="mt-4 pt-4 border-t">
                <div class="flex flex-wrap gap-2">
                    <span class="text-sm font-medium">Filtro rápido ubicación:</span>
                    <button onclick="filtrarPorUbicacion('todas')" 
                            class="px-3 py-1 text-xs rounded <?= !$filtro_ubicacion ? 'bg-blue-500 text-white' : 'bg-gray-200 text-gray-700' ?>">
                        Todas
                    </button>
                    <button onclick="filtrarPorUbicacion('Local 1')" 
                            class="px-3 py-1 text-xs rounded <?= $filtro_ubicacion === 'Local 1' ? 'bg-blue-500 text-white' : 'bg-gray-200 text-gray-700' ?>">
                        🏪 Local 1
                    </button>
                    <button onclick="filtrarPorUbicacion('Fábrica')" 
                            class="px-3 py-1 text-xs rounded <?= $filtro_ubicacion === 'Fábrica' ? 'bg-orange-500 text-white' : 'bg-gray-200 text-gray-700' ?>">
                        🏭 Fábrica
                    </button>
                </div>
            </div>
        </div>

        <!-- Tabla de pedidos -->
        <div class="bg-white rounded-lg shadow overflow-hidden">
            <div class="px-6 py-4 border-b border-gray-200">
                <h2 class="text-lg font-semibold">
                    Pedidos encontrados: <?= count($pedidos) ?>
                </h2>
            </div>
            
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">ID</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Cliente</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Producto</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Modalidad</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Ubicación</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Estado</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Impresión</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Acciones</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php foreach ($pedidos as $pedido): ?>
                            <?php
                            $nombre_completo = $pedido['cliente_fijo_nombre'] ? 
                                $pedido['cliente_fijo_nombre'] . ' ' . $pedido['cliente_fijo_apellido'] : 
                                $pedido['nombre'] . ' ' . $pedido['apellido'];
                            
                            $es_cliente_fijo = !empty($pedido['cliente_fijo_nombre']);
                            
                            // Calcular tiempo transcurrido
                            $tiempo_transcurrido = time() - strtotime($pedido['created_at']);
                            $minutos = floor($tiempo_transcurrido / 60);
                            
                            // Determinar urgencia por color
                            $urgencia_class = '';
                            if ($minutos > 60) {
                                $urgencia_class = 'bg-red-100 border-red-200';
                            } elseif ($minutos > 30) {
                                $urgencia_class = 'bg-yellow-100 border-yellow-200';
                            }
                            ?>
                            <tr class="hover:bg-gray-50 <?= $urgencia_class ?>">
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                    #<?= $pedido['id'] ?>
                                    <?php if ($minutos > 30): ?>
                                        <div class="text-xs text-red-600">
                                            <?php if ($minutos > 60): ?>
                                                🚨 <?= floor($minutos/60) ?>h <?= $minutos%60 ?>m
                                            <?php else: ?>
                                                ⚠️ <?= $minutos ?>m
                                            <?php endif; ?>
                                        </div>
                                    <?php endif; ?>
                                </td>
                                
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                    <div><?= htmlspecialchars($nombre_completo) ?></div>
                                    <div class="text-xs text-gray-500">
                                        <?= htmlspecialchars($pedido['telefono']) ?>
                                        <?php if ($es_cliente_fijo): ?>
                                            <span class="bg-green-100 text-green-800 px-1 rounded text-xs ml-1">FIJO</span>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                
                                <td class="px-6 py-4 text-sm text-gray-900">
                                    <div class="font-medium"><?= htmlspecialchars($pedido['producto']) ?></div>
                                    <div class="text-xs text-gray-500">
                                        Cant: <?= $pedido['cantidad'] ?> | $<?= number_format($pedido['precio'], 0, ',', '.') ?>
                                    </div>
                                </td>
                                
                                <td class="px-6 py-4 whitespace-nowrap text-sm">
                                    <?php if ($pedido['modalidad'] === 'Delivery'): ?>
                                        <span class="bg-blue-100 text-blue-800 px-2 py-1 rounded-full text-xs">
                                            <i class="fas fa-truck mr-1"></i>Delivery
                                        </span>
                                    <?php else: ?>
                                        <span class="bg-green-100 text-green-800 px-2 py-1 rounded-full text-xs">
                                            <i class="fas fa-store mr-1"></i>Retira
                                        </span>
                                    <?php endif; ?>
                                </td>
                                
                                <td class="px-6 py-4 whitespace-nowrap text-sm">
                                    <?php if ($pedido['ubicacion'] === 'Local 1'): ?>
                                        <span class="bg-blue-100 text-blue-800 px-2 py-1 rounded-full text-xs font-medium">
                                            🏪 Local 1
                                        </span>
                                    <?php else: ?>
                                        <span class="bg-orange-100 text-orange-800 px-2 py-1 rounded-full text-xs font-medium">
                                            🏭 Fábrica
                                        </span>
                                    <?php endif; ?>
                                </td>
                                
                                <td class="px-6 py-4 whitespace-nowrap text-sm">
                                    <select onchange="cambiarEstado(<?= $pedido['id'] ?>, this.value)" 
                                            class="text-xs px-2 py-1 rounded border
                                            <?php
                                            switch ($pedido['estado']) {
                                                case 'Pendiente': echo 'bg-yellow-100 text-yellow-800 border-yellow-300'; break;
                                                case 'Preparando': echo 'bg-blue-100 text-blue-800 border-blue-300'; break;
                                                case 'Listo': echo 'bg-green-100 text-green-800 border-green-300'; break;
                                                case 'Entregado': echo 'bg-gray-100 text-gray-800 border-gray-300'; break;
                                                default: echo 'bg-gray-100 text-gray-800 border-gray-300';
                                            }
                                            ?>">
                                        <option value="Pendiente" <?= $pedido['estado'] === 'Pendiente' ? 'selected' : '' ?>>Pendiente</option>
                                        <option value="Preparando" <?= $pedido['estado'] === 'Preparando' ? 'selected' : '' ?>>Preparando</option>
                                        <option value="Listo" <?= $pedido['estado'] === 'Listo' ? 'selected' : '' ?>>Listo</option>
                                        <option value="Entregado" <?= $pedido['estado'] === 'Entregado' ? 'selected' : '' ?>>Entregado</option>
                                    </select>
                                </td>
                                
                                <td class="px-6 py-4 whitespace-nowrap text-sm">
                                    <?php if ($pedido['impreso']): ?>
                                        <div class="flex flex-col space-y-1">
                                            <span class="bg-green-100 text-green-800 px-2 py-1 rounded-full text-xs">
                                                <i class="fas fa-check-circle mr-1"></i>Impreso
                                            </span>
                                            <button onclick="reimprimir(<?= $pedido['id'] ?>)" 
                                                    class="bg-gray-500 hover:bg-gray-600 text-white px-2 py-1 rounded text-xs">
                                                <i class="fas fa-redo mr-1"></i>Reimprimir
                                            </button>
                                        </div>
                                    <?php else: ?>
                                        <div class="flex flex-col space-y-1">
                                            <span class="bg-red-100 text-red-800 px-2 py-1 rounded-full text-xs">
                                                <i class="fas fa-exclamation-circle mr-1"></i>Sin imprimir
                                            </span>
                                            
                                            <?php if ($pedido['ubicacion'] === 'Local 1'): ?>
                                                <button onclick="imprimirLocal1(<?= $pedido['id'] ?>)" 
                                                        class="bg-blue-500 hover:bg-blue-600 text-white px-2 py-1 rounded text-xs font-medium">
                                                    <i class="fas fa-print mr-1"></i>🏪 Local 1
                                                </button>
                                            <?php else: ?>
                                                <button onclick="imprimirComanda(<?= $pedido['id'] ?>)" 
                                                        class="bg-orange-500 hover:bg-orange-600 text-white px-2 py-1 rounded text-xs">
                                                    <i class="fas fa-print mr-1"></i>🏭 Fábrica
                                                </button>
                                            <?php endif; ?>
                                        </div>
                                    <?php endif; ?>
                                </td>
                                
                                <td class="px-6 py-4 text-sm font-medium">
                                    <div class="flex space-x-2">
                                        <button onclick="verDetalles(<?= $pedido['id'] ?>)" 
                                                class="text-blue-600 hover:text-blue-900 bg-blue-50 hover:bg-blue-100 px-2 py-1 rounded" 
                                                title="Ver detalles completos">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                        
                                        <?php if ($pedido['modalidad'] === 'Delivery' || $pedido['telefono']): ?>
                                            <button onclick="contactarCliente('<?= htmlspecialchars($pedido['telefono']) ?>', '<?= htmlspecialchars($nombre_completo) ?>', <?= $pedido['id'] ?>, '<?= htmlspecialchars($pedido['estado']) ?>')" 
                                                    class="text-green-600 hover:text-green-900 bg-green-50 hover:bg-green-100 px-2 py-1 rounded" 
                                                    title="Contactar por WhatsApp">
                                                <i class="fab fa-whatsapp"></i>
                                            </button>
                                        <?php endif; ?>
                                        
                                        <button onclick="eliminarPedido(<?= $pedido['id'] ?>, '#<?= $pedido['id'] ?> - <?= htmlspecialchars($nombre_completo) ?>')" 
                                                class="text-red-600 hover:text-red-900 bg-red-50 hover:bg-red-100 px-2 py-1 rounded" 
                                                title="Eliminar pedido">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                
                <?php if (empty($pedidos)): ?>
                    <div class="text-center py-12">
                        <i class="fas fa-search text-4xl text-gray-400 mb-4"></i>
                        <p class="text-gray-500 text-lg">No se encontraron pedidos con los filtros aplicados</p>
                        <a href="ver_pedidos.php" class="text-blue-600 hover:text-blue-800 mt-2 inline-block">
                            Ver todos los pedidos
                        </a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Modal de Detalles -->
    <div id="detallesModal" class="hidden fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50">
        <div class="relative top-10 mx-auto p-5 border max-w-4xl shadow-lg rounded-md bg-white">
            <div class="flex justify-between items-center mb-4">
                <h3 class="text-xl font-bold">Detalles del Pedido</h3>
                <button onclick="cerrarDetallesModal()" class="text-gray-400 hover:text-gray-600">
                    <i class="fas fa-times text-xl"></i>
                </button>
            </div>
            <div id="detallesContent">
                <!-- Contenido dinámico -->
            </div>
        </div>
    </div>

    <script>
        let pedidoActualModal = null;

        // Funciones para filtros rápidos (RESTAURADAS)
        function setFiltroRapido(periodo) {
            const hoy = new Date();
            let fechaDesde = '';
            let fechaHasta = '';
            
            switch(periodo) {
                case 'hoy':
                    fechaDesde = fechaHasta = formatDate(hoy);
                    break;
                    
                case 'ayer':
                    const ayer = new Date(hoy);
                    ayer.setDate(ayer.getDate() - 1);
                    fechaDesde = fechaHasta = formatDate(ayer);
                    break;
                    
                case 'semana':
                    const inicioSemana = new Date(hoy);
                    inicioSemana.setDate(hoy.getDate() - hoy.getDay());
                    fechaDesde = formatDate(inicioSemana);
                    fechaHasta = formatDate(hoy);
                    break;
                    
                case 'mes':
                    const inicioMes = new Date(hoy.getFullYear(), hoy.getMonth(), 1);
                    fechaDesde = formatDate(inicioMes);
                    fechaHasta = formatDate(hoy);
                    break;
                    
                case 'todo':
                    fechaDesde = fechaHasta = '';
                    break;
            }
            
            document.querySelector('input[name="fecha_desde"]').value = fechaDesde;
            document.querySelector('input[name="fecha_hasta"]').value = fechaHasta;
            
            // Aplicar filtros automáticamente
            aplicarFiltros();
        }

        function formatDate(date) {
            const año = date.getFullYear();
            const mes = String(date.getMonth() + 1).padStart(2, '0');
            const dia = String(date.getDate()).padStart(2, '0');
            return `${año}-${mes}-${dia}`;
        }

        function limpiarFiltros() {
            // Limpiar todos los campos
            document.querySelector('input[name="buscar"]').value = '';
            document.querySelector('select[name="estado"]').value = '';
            document.querySelector('select[name="modalidad"]').value = '';
            document.querySelector('select[name="ubicacion"]').value = '';
            document.querySelector('input[name="fecha_desde"]').value = '';
            document.querySelector('input[name="fecha_hasta"]').value = '';
            document.querySelector('select[name="orden"]').value = 'created_at DESC';
            
            // Aplicar filtros vacíos
            aplicarFiltros();
        }

        function aplicarFiltros() {
            const form = document.getElementById('filtrosForm');
            if (form) {
                console.log('Aplicando filtros...');
                form.submit();
            } else {
                console.error('Formulario filtrosForm no encontrado');
            }
        }

        function filtrarPorUbicacion(ubicacion) {
            const params = new URLSearchParams(window.location.search);
            if (ubicacion === 'todas') {
                params.delete('ubicacion');
            } else {
                params.set('ubicacion', ubicacion);
            }
            window.location.search = params.toString();
        }

        // FUNCIÓN PARA LOCAL 1 - Usar la nueva comanda simple del admin
function imprimirLocal1(pedidoId) {
    console.log('🏪 Imprimiendo Local 1 - Pedido #' + pedidoId);
    
    // Usar la nueva comanda simple en el módulo de impresión del admin
    const url = `../impresion/comanda_simple.php?pedido=${pedidoId}`;
    const ventana = window.open(url, '_blank', 'width=400,height=600,scrollbars=yes');
    
    if (!ventana) {
        alert('❌ Error: No se pudo abrir la ventana de impresión.\n\n' +
              'Por favor, permite ventanas emergentes y vuelve a intentar.');
        return false;
    }
    
    ventana.focus();
    
    // Marcar como impreso automáticamente
    setTimeout(() => {
        marcarPedidoComoImpreso(pedidoId);
    }, 2000);
    
    console.log('✅ Comanda Local 1 - desde módulo admin');
    return true;
}

       // FUNCIÓN PARA FÁBRICA - También usar la misma comanda simple
function imprimirComanda(pedidoId) {
    console.log('🏭 Imprimiendo Fábrica - Pedido #' + pedidoId);
    
    // Usar la misma comanda simple (funcionará para ambas ubicaciones)
    const url = `../impresion/comanda_simple.php?pedido=${pedidoId}`;
    const ventana = window.open(url, '_blank', 'width=400,height=600,scrollbars=yes');
    
    if (!ventana) {
        alert('❌ Error: No se pudo abrir la ventana.\n\n' +
              'Por favor, permite ventanas emergentes y vuelve a intentar.');
        return false;
    }
    
    ventana.focus();
    
    // Marcar como impreso automáticamente
    setTimeout(() => {
        marcarPedidoComoImpreso(pedidoId);
    }, 2000);
    
    console.log('✅ Comanda Fábrica - desde módulo admin');
    return true;
}

        // Función auxiliar para marcar pedido como impreso
        function marcarPedidoComoImpreso(pedidoId) {
            fetch(window.location.href, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `accion=marcar_impreso&id=${pedidoId}`
            })
            .then(response => {
                if (response.ok) {
                    console.log(`✅ Pedido #${pedidoId} marcado como impreso`);
                    
                    // Actualizar interfaz sin recargar página
                    actualizarEstadoImpresion(pedidoId);
                    
                } else {
                    console.error('Error marcando como impreso');
                }
            })
            .catch(error => {
                console.error('Error en petición:', error);
            });
        }

        // Función para actualizar estado visual sin recargar
        function actualizarEstadoImpresion(pedidoId) {
            const filas = document.querySelectorAll('tbody tr');
            
            filas.forEach(fila => {
                const idCell = fila.querySelector('td:first-child');
                if (idCell && idCell.textContent.includes('#' + pedidoId)) {
                    
                    // Buscar columna de impresión (índice 6)
                    const impresionCell = fila.children[6];
                    
                    if (impresionCell) {
                        impresionCell.innerHTML = `
                            <div class="flex flex-col space-y-1">
                                <span class="bg-green-100 text-green-800 px-2 py-1 rounded-full text-xs">
                                    <i class="fas fa-check-circle mr-1"></i>Impreso
                                </span>
                                <button onclick="reimprimir(${pedidoId})" 
                                        class="bg-gray-500 hover:bg-gray-600 text-white px-2 py-1 rounded text-xs">
                                    <i class="fas fa-redo mr-1"></i>Reimprimir
                                </button>
                            </div>
                        `;
                    }
                }
            });
        }

        function cambiarEstado(id, nuevoEstado) {
            if (nuevoEstado) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="accion" value="cambiar_estado">
                    <input type="hidden" name="id" value="${id}">
                    <input type="hidden" name="estado" value="${nuevoEstado}">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        }

        function eliminarPedido(id, descripcion) {
            if (confirm(`¿Estás seguro de eliminar el pedido ${descripcion}?`)) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="accion" value="eliminar">
                    <input type="hidden" name="id" value="${id}">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        }

        function reimprimir(pedidoId) {
            console.log('🔄 Reimprimiendo pedido #' + pedidoId);
            
            if (confirm(`¿Reimprimir comanda del pedido #${pedidoId}?`)) {
                // Buscar la ubicación del pedido en la tabla
                const filas = document.querySelectorAll('tbody tr');
                let ubicacion = 'Local 1'; // Por defecto
                
                filas.forEach(fila => {
                    const idCell = fila.querySelector('td:first-child');
                    if (idCell && idCell.textContent.includes('#' + pedidoId)) {
                        const ubicacionCell = fila.children[4]; // Columna de ubicación
                        if (ubicacionCell.textContent.includes('Fábrica')) {
                            ubicacion = 'Fábrica';
                        }
                    }
                });
                
                // Reimprimir según la ubicación
                if (ubicacion === 'Local 1') {
                    return imprimirLocal1(pedidoId);
                } else {
                    return imprimirComanda(pedidoId);
                }
            }
            
            return false;
        }

        function contactarCliente(telefono, nombre, pedidoId, estado) {
            const telefonoLimpio = telefono.replace(/[^0-9]/g, '');
            const nombreLimpio = nombre.split('(')[0].trim();
            const estadoMinuscula = estado.toLowerCase();
            
            const url = `https://wa.me/${telefonoLimpio}?text=Hola%20${encodeURIComponent(nombreLimpio)},%20tu%20pedido%20#${pedidoId}%20está%20${encodeURIComponent(estadoMinuscula)}`;
            window.open(url, '_blank');
        }

        // VER DETALLES - MODAL COMPLETO
        function verDetalles(pedidoId) {
            console.log('Cargando detalles del pedido:', pedidoId);
            
            const filas = document.querySelectorAll('tbody tr');
            let pedidoData = null;
            
            filas.forEach(fila => {
                const idCell = fila.querySelector('td:first-child');
                if (idCell && idCell.textContent.includes('#' + pedidoId)) {
                    pedidoData = extraerDatosDeFila(fila, pedidoId);
                }
            });
            
            if (pedidoData) {
                mostrarModalDetalles(pedidoData);
            } else {
                alert('No se pudieron cargar los detalles del pedido');
            }
        }

        function extraerDatosDeFila(fila, pedidoId) {
            const celdas = fila.children;
            
            return {
                id: pedidoId,
                cliente: {
                    nombre: celdas[1].querySelector('div').textContent.trim(),
                    telefono: celdas[1].querySelector('.text-xs').textContent.replace('FIJO', '').trim()
                },
                producto: celdas[2].querySelector('.font-medium').textContent.trim(),
                detalles: celdas[2].querySelector('.text-xs').textContent.trim(),
                modalidad: celdas[3].textContent.trim(),
                ubicacion: celdas[4].textContent.trim(),
                estado: celdas[5].querySelector('select').value,
                impreso: celdas[6].textContent.includes('Impreso')
            };
        }

        function mostrarModalDetalles(pedido) {
            pedidoActualModal = pedido;
            
            const detallesContent = document.getElementById('detallesContent');
            detallesContent.innerHTML = `
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    
                    <!-- INFO PRINCIPAL -->
                    <div class="bg-blue-50 border border-blue-200 rounded-lg p-4">
                        <h4 class="font-bold text-lg text-blue-800 mb-3">
                            <i class="fas fa-info-circle mr-2"></i>PEDIDO #${pedido.id}
                        </h4>
                        <div class="space-y-2">
                            <div><strong>Cliente:</strong> ${pedido.cliente.nombre}</div>
                            <div><strong>Teléfono:</strong> ${pedido.cliente.telefono}</div>
                            <div><strong>Estado:</strong> 
                                <span class="px-2 py-1 rounded text-xs bg-blue-100 text-blue-800">${pedido.estado}</span>
                            </div>
                            <div><strong>Ubicación:</strong> ${pedido.ubicacion}</div>
                            <div><strong>Modalidad:</strong> ${pedido.modalidad}</div>
                        </div>
                    </div>
                    
                    <!-- PRODUCTO -->
                    <div class="bg-green-50 border border-green-200 rounded-lg p-4">
                        <h4 class="font-bold text-lg text-green-800 mb-3">
                            <i class="fas fa-shopping-bag mr-2"></i>PRODUCTO
                        </h4>
                        <div class="space-y-2">
                            <div class="font-semibold text-lg">${pedido.producto}</div>
                            <div class="text-sm text-gray-600">${pedido.detalles}</div>
                        </div>
                    </div>
                </div>
                
                <!-- ACCIONES -->
                <div class="mt-6 flex justify-center space-x-4">
                    ${pedido.ubicacion.includes('Local 1') ? 
                        `<button onclick="imprimirComandaDesdeModal()" 
                                class="bg-blue-500 hover:bg-blue-600 text-white px-6 py-2 rounded">
                            <i class="fas fa-print mr-2"></i>🏪 Imprimir Local 1
                        </button>` :
                        `<button onclick="imprimirComandaDesdeModal()" 
                                class="bg-orange-500 hover:bg-orange-600 text-white px-6 py-2 rounded">
                            <i class="fas fa-print mr-2"></i>🏭 Imprimir Fábrica
                        </button>`
                    }
                    
                    <button onclick="contactarClienteDesdeModal()" 
                            class="bg-green-500 hover:bg-green-600 text-white px-6 py-2 rounded">
                        <i class="fab fa-whatsapp mr-2"></i>WhatsApp
                    </button>
                    
                    <button onclick="cerrarDetallesModal()" 
                            class="bg-gray-500 hover:bg-gray-600 text-white px-6 py-2 rounded">
                        Cerrar
                    </button>
                </div>
            `;
            
            document.getElementById('detallesModal').classList.remove('hidden');
        }

        function imprimirComandaDesdeModal() {
            if (pedidoActualModal) {
                if (pedidoActualModal.ubicacion.includes('Local 1')) {
                    imprimirLocal1(pedidoActualModal.id);
                } else {
                    imprimirComanda(pedidoActualModal.id);
                }
                cerrarDetallesModal();
            }
        }

        function contactarClienteDesdeModal() {
            if (pedidoActualModal) {
                const telefono = pedidoActualModal.cliente.telefono.replace(/[^0-9]/g, '');
                const nombre = pedidoActualModal.cliente.nombre.split('(')[0].trim();
                const estado = pedidoActualModal.estado.toLowerCase();
                
                const url = `https://wa.me/${telefono}?text=Hola%20${encodeURIComponent(nombre)},%20tu%20pedido%20#${pedidoActualModal.id}%20está%20${encodeURIComponent(estado)}`;
                window.open(url, '_blank');
            }
        }

        function cerrarDetallesModal() {
            const modal = document.getElementById('detallesModal');
            if (modal) {
                modal.classList.add('hidden');
            }
            pedidoActualModal = null;
        }

        // Cerrar modales al hacer clic fuera
        document.addEventListener('DOMContentLoaded', function() {
            const detallesModal = document.getElementById('detallesModal');
            
            if (detallesModal) {
                detallesModal.addEventListener('click', function(e) {
                    if (e.target === this) {
                        cerrarDetallesModal();
                    }
                });
            }
            
            // Información de depuración
            console.log('🏪 Sistema Santa Catalina - Ver Pedidos');
            console.log('🖨️ Local 1: POS80-CX configurada');
            console.log('🏭 Fábrica: 3nstar RPT006S configurada');
        });

        // Atajos de teclado
        document.addEventListener('keydown', function(e) {
            // Escape para cerrar modales
            if (e.key === 'Escape') {
                cerrarDetallesModal();
            }
        });
    </script>
</body>
</html>