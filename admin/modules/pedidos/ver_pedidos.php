<?php
require_once '../../config.php';
requireLogin();

$pdo = getConnection();

// Manejar acciones (cambiar estado, eliminar, impresión)
$mensaje = '';
$error = '';

if ($_POST) {
    if (isset($_POST['accion'])) {
        switch ($_POST['accion']) {
            case 'cambiar_estado':
                $id = (int)$_POST['id'];
                $estado = $_POST['estado'];
                $estados_validos = ['Pendiente', 'Preparando', 'Listo', 'Entregado'];
                
                if (in_array($estado, $estados_validos)) {
                    try {
                        $stmt = $pdo->prepare("UPDATE pedidos SET estado = ?, updated_at = NOW() WHERE id = ?");
                        $stmt->execute([$estado, $id]);
                        $mensaje = 'Estado actualizado correctamente';
                    } catch (Exception $e) {
                        $error = 'Error al actualizar estado';
                    }
                } else {
                    $error = 'Estado no válido';
                }
                break;
                
            case 'marcar_impreso':
                $id = (int)$_POST['id'];
                try {
                    $stmt = $pdo->prepare("UPDATE pedidos SET impreso = 1, updated_at = NOW() WHERE id = ?");
                    $stmt->execute([$id]);
                    $mensaje = 'Comanda marcada como impresa';
                } catch (Exception $e) {
                    $error = 'Error al marcar como impreso';
                }
                break;
                
            case 'reimprimir':
                $id = (int)$_POST['id'];
                try {
                    $stmt = $pdo->prepare("UPDATE pedidos SET updated_at = NOW() WHERE id = ?");
                    $stmt->execute([$id]);
                    $mensaje = 'Comanda reimpresa - Verificar impresora';
                } catch (Exception $e) {
                    $error = 'Error al reimprimir';
                }
                break;
                
            case 'eliminar':
                $id = (int)$_POST['id'];
                try {
                    $stmt = $pdo->prepare("DELETE FROM pedidos WHERE id = ?");
                    $stmt->execute([$id]);
                    $mensaje = 'Pedido eliminado correctamente';
                } catch (Exception $e) {
                    $error = 'Error al eliminar pedido';
                }
                break;
        }
    }
}

// Filtros
$filtro_estado = isset($_GET['estado']) ? sanitize($_GET['estado']) : '';
$filtro_fecha = isset($_GET['fecha']) ? sanitize($_GET['fecha']) : '';
$filtro_modalidad = isset($_GET['modalidad']) ? sanitize($_GET['modalidad']) : '';
$buscar = isset($_GET['buscar']) ? sanitize($_GET['buscar']) : '';
$orden = isset($_GET['orden']) ? sanitize($_GET['orden']) : 'created_at DESC';

// Construir consulta - INCLUIR TODOS LOS CAMPOS NECESARIOS
$sql = "SELECT p.*, cf.nombre as cliente_nombre, cf.apellido as cliente_apellido 
        FROM pedidos p 
        LEFT JOIN clientes_fijos cf ON p.cliente_fijo_id = cf.id 
        WHERE 1=1";
$params = [];

if ($filtro_estado) {
    $sql .= " AND p.estado = ?";
    $params[] = $filtro_estado;
}

if ($filtro_fecha) {
    $sql .= " AND DATE(p.created_at) = ?";
    $params[] = $filtro_fecha;
}

if ($filtro_modalidad) {
    $sql .= " AND p.modalidad = ?";
    $params[] = $filtro_modalidad;
}

if ($buscar) {
    $sql .= " AND (p.nombre LIKE ? OR p.apellido LIKE ? OR p.telefono LIKE ? OR p.producto LIKE ?)";
    $buscarParam = "%$buscar%";
    $params = array_merge($params, [$buscarParam, $buscarParam, $buscarParam, $buscarParam]);
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
    SUM(CASE WHEN estado = 'Pendiente' THEN 1 ELSE 0 END) as pendientes,
    SUM(CASE WHEN estado = 'Preparando' THEN 1 ELSE 0 END) as preparando,
    SUM(CASE WHEN estado = 'Listo' THEN 1 ELSE 0 END) as listos,
    SUM(CASE WHEN estado = 'Entregado' THEN 1 ELSE 0 END) as entregados,
    SUM(CASE WHEN impreso = 1 THEN 1 ELSE 0 END) as impresos,
    SUM(precio) as total_ventas
    FROM pedidos";

if ($filtro_fecha) {
    $stats_sql .= " WHERE DATE(created_at) = '$filtro_fecha'";
} else {
    $stats_sql .= " WHERE DATE(created_at) = CURDATE()";
}

$stats = $pdo->query($stats_sql)->fetch();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ver Pedidos - <?= APP_NAME ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="bg-gray-100">
    <!-- Header -->
    <header class="bg-white shadow-md">
        <div class="container mx-auto px-4 py-3 flex justify-between items-center">
            <div class="flex items-center">
                <a href="../../" class="text-gray-600 hover:text-gray-800 mr-4">
                    <i class="fas fa-arrow-left"></i>
                </a>
                <h1 class="text-xl font-bold text-gray-800">
                    <i class="fas fa-list text-orange-500 mr-2"></i>Lista de Pedidos
                </h1>
            </div>
            <a href="../../logout.php" class="bg-red-500 hover:bg-red-600 text-white px-3 py-2 rounded">
                <i class="fas fa-sign-out-alt mr-1"></i>Salir
            </a>
        </div>
    </header>

    <!-- Main Content -->
    <main class="container mx-auto px-4 py-6">
        
        <!-- Mensajes -->
        <?php if ($mensaje): ?>
        <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4">
            <i class="fas fa-check-circle mr-2"></i><?= $mensaje ?>
        </div>
        <?php endif; ?>

        <?php if ($error): ?>
        <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
            <i class="fas fa-exclamation-circle mr-2"></i><?= $error ?>
        </div>
        <?php endif; ?>

        <!-- Estadísticas rápidas -->
        <div class="grid grid-cols-2 md:grid-cols-7 gap-4 mb-6">
            <div class="bg-white p-4 rounded-lg shadow text-center">
                <div class="text-2xl font-bold text-gray-800"><?= $stats['total'] ?></div>
                <div class="text-sm text-gray-600">Total</div>
            </div>
            <div class="bg-white p-4 rounded-lg shadow text-center">
                <div class="text-2xl font-bold text-yellow-600"><?= $stats['pendientes'] ?></div>
                <div class="text-sm text-gray-600">Pendientes</div>
            </div>
            <div class="bg-white p-4 rounded-lg shadow text-center">
                <div class="text-2xl font-bold text-blue-600"><?= $stats['preparando'] ?></div>
                <div class="text-sm text-gray-600">Preparando</div>
            </div>
            <div class="bg-white p-4 rounded-lg shadow text-center">
                <div class="text-2xl font-bold text-green-600"><?= $stats['listos'] ?></div>
                <div class="text-sm text-gray-600">Listos</div>
            </div>
            <div class="bg-white p-4 rounded-lg shadow text-center">
                <div class="text-2xl font-bold text-gray-600"><?= $stats['entregados'] ?></div>
                <div class="text-sm text-gray-600">Entregados</div>
            </div>
            <div class="bg-white p-4 rounded-lg shadow text-center">
                <div class="text-2xl font-bold text-purple-600"><?= $stats['impresos'] ?></div>
                <div class="text-sm text-gray-600">Impresos</div>
            </div>
            <div class="bg-white p-4 rounded-lg shadow text-center">
                <div class="text-2xl font-bold text-green-600"><?= formatPrice($stats['total_ventas']) ?></div>
                <div class="text-sm text-gray-600">Ventas</div>
            </div>
        </div>

        <!-- Filtros -->
        <div class="bg-white rounded-lg shadow mb-6 p-4">
            <form method="GET" class="grid grid-cols-1 md:grid-cols-6 gap-4">
                <!-- Buscador -->
                <div class="md:col-span-2">
                    <input type="text" name="buscar" value="<?= htmlspecialchars($buscar) ?>" 
                           placeholder="Buscar cliente, producto, teléfono..." 
                           class="w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500">
                </div>
                
                <!-- Estado -->
                <div>
                    <select name="estado" class="w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500">
                        <option value="">Todos los estados</option>
                        <option value="Pendiente" <?= $filtro_estado === 'Pendiente' ? 'selected' : '' ?>>Pendiente</option>
                        <option value="Preparando" <?= $filtro_estado === 'Preparando' ? 'selected' : '' ?>>Preparando</option>
                        <option value="Listo" <?= $filtro_estado === 'Listo' ? 'selected' : '' ?>>Listo</option>
                        <option value="Entregado" <?= $filtro_estado === 'Entregado' ? 'selected' : '' ?>>Entregado</option>
                    </select>
                </div>
                
                <!-- Fecha -->
                <div>
                    <input type="date" name="fecha" value="<?= htmlspecialchars($filtro_fecha) ?>" 
                           class="w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500">
                </div>
                
                <!-- Modalidad -->
                <div>
                    <select name="modalidad" class="w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500">
                        <option value="">Todas</option>
                        <option value="Retira" <?= $filtro_modalidad === 'Retira' ? 'selected' : '' ?>>Retira</option>
                        <option value="Delivery" <?= $filtro_modalidad === 'Delivery' ? 'selected' : '' ?>>Delivery</option>
                    </select>
                </div>
                
                <!-- Botón buscar -->
                <div>
                    <button type="submit" class="w-full bg-blue-500 hover:bg-blue-600 text-white py-2 px-4 rounded-lg">
                        <i class="fas fa-search mr-1"></i>Filtrar
                    </button>
                </div>
            </form>
            
            <!-- Ordenamiento -->
            <div class="mt-4 flex justify-between items-center">
                <div class="flex items-center space-x-2">
                    <span class="text-sm text-gray-600">Ordenar por:</span>
                    <form method="GET" class="inline">
                        <!-- Mantener filtros actuales -->
                        <?php foreach ($_GET as $key => $value): ?>
                            <?php if ($key !== 'orden'): ?>
                                <input type="hidden" name="<?= htmlspecialchars($key) ?>" value="<?= htmlspecialchars($value) ?>">
                            <?php endif; ?>
                        <?php endforeach; ?>
                        
                        <select name="orden" onchange="this.form.submit()" class="px-3 py-1 border rounded text-sm">
                            <?php foreach ($ordenes_validos as $val => $label): ?>
                                <option value="<?= $val ?>" <?= $orden === $val ? 'selected' : '' ?>><?= $label ?></option>
                            <?php endforeach; ?>
                        </select>
                    </form>
                </div>
                
                <a href="crear_pedido.php" class="bg-green-500 hover:bg-green-600 text-white px-4 py-2 rounded-lg text-sm">
                    <i class="fas fa-plus mr-1"></i>Nuevo Pedido
                </a>
            </div>
        </div>

        <!-- Lista de pedidos -->
        <div class="bg-white rounded-lg shadow overflow-hidden">
            <?php if (empty($pedidos)): ?>
                <div class="p-8 text-center text-gray-500">
                    <i class="fas fa-inbox text-6xl mb-4"></i>
                    <h3 class="text-xl mb-2">No hay pedidos</h3>
                    <p>No se encontraron pedidos con los filtros aplicados</p>
                </div>
            <?php else: ?>
                <div class="overflow-x-auto">
                    <table class="min-w-full">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">ID</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Cliente</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Producto</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Precio</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Estado</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Modalidad</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Fecha</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Impresión</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Acciones</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200">
                            <?php foreach ($pedidos as $pedido): ?>
                                <tr class="hover:bg-gray-50">
                                    <td class="px-6 py-4 text-sm font-medium text-gray-900">
                                        #<?= $pedido['id'] ?>
                                    </td>
                                    <td class="px-6 py-4">
                                        <div class="text-sm font-medium text-gray-900">
                                            <?= htmlspecialchars($pedido['nombre'] . ' ' . $pedido['apellido']) ?>
                                            <?php if ($pedido['cliente_nombre']): ?>
                                                <span class="text-xs text-blue-600">(Cliente fijo)</span>
                                            <?php endif; ?>
                                        </div>
                                        <div class="text-sm text-gray-500">
                                            <i class="fas fa-phone text-xs mr-1"></i><?= htmlspecialchars($pedido['telefono']) ?>
                                        </div>
                                        <?php if ($pedido['direccion']): ?>
                                        <div class="text-sm text-gray-500">
                                            <i class="fas fa-map-marker-alt text-xs mr-1 text-red-500"></i>
                                            <span class="font-medium"><?= htmlspecialchars($pedido['direccion']) ?></span>
                                        </div>
                                        <?php else: ?>
                                        <div class="text-sm text-red-500">
                                            <i class="fas fa-exclamation-triangle text-xs mr-1"></i>
                                            <span class="font-medium">Sin dirección</span>
                                        </div>
                                        <?php endif; ?>
                                        
                                        <!-- Información adicional de fecha/hora de entrega -->
                                        <?php if ($pedido['fecha_entrega'] || $pedido['hora_entrega'] || $pedido['notas_horario']): ?>
                                        <div class="text-xs text-orange-600 bg-orange-50 px-2 py-1 rounded mt-1">
                                            <i class="fas fa-clock mr-1"></i>
                                            <?php if ($pedido['fecha_entrega']): ?>
                                                Para: <?= date('d/m', strtotime($pedido['fecha_entrega'])) ?>
                                            <?php endif; ?>
                                            <?php if ($pedido['hora_entrega']): ?>
                                                <?= substr($pedido['hora_entrega'], 0, 5) ?>
                                            <?php endif; ?>
                                            <?php if ($pedido['notas_horario']): ?>
                                                (<?= htmlspecialchars($pedido['notas_horario']) ?>)
                                            <?php endif; ?>
                                        </div>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-6 py-4">
                                        <div class="text-sm font-medium text-gray-900">
                                            <?= htmlspecialchars($pedido['producto']) ?>
                                        </div>
                                        <div class="text-sm text-gray-500">
                                            Cantidad: <?= $pedido['cantidad'] ?> | <?= htmlspecialchars($pedido['forma_pago']) ?>
                                        </div>
                                        <?php if ($pedido['observaciones']): ?>
                                        <div class="text-xs text-gray-500 mt-1">
                                            <i class="fas fa-comment text-xs mr-1"></i><?= htmlspecialchars($pedido['observaciones']) ?>
                                        </div>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-6 py-4 text-sm font-medium text-gray-900">
                                        <?= formatPrice($pedido['precio']) ?>
                                    </td>
                                    <td class="px-6 py-4">
                                        <?php
                                        $estado_colors = [
                                            'Pendiente' => 'bg-yellow-100 text-yellow-800',
                                            'Preparando' => 'bg-blue-100 text-blue-800',
                                            'Listo' => 'bg-green-100 text-green-800',
                                            'Entregado' => 'bg-gray-100 text-gray-800'
                                        ];
                                        ?>
                                        <select onchange="cambiarEstado(<?= $pedido['id'] ?>, this.value)"
                                                class="px-2 py-1 text-xs font-medium rounded-full border-0 <?= $estado_colors[$pedido['estado']] ?? 'bg-gray-100 text-gray-800' ?>">
                                            <option value="Pendiente" <?= $pedido['estado'] === 'Pendiente' ? 'selected' : '' ?>>Pendiente</option>
                                            <option value="Preparando" <?= $pedido['estado'] === 'Preparando' ? 'selected' : '' ?>>Preparando</option>
                                            <option value="Listo" <?= $pedido['estado'] === 'Listo' ? 'selected' : '' ?>>Listo</option>
                                            <option value="Entregado" <?= $pedido['estado'] === 'Entregado' ? 'selected' : '' ?>>Entregado</option>
                                        </select>
                                    </td>
                                    <td class="px-6 py-4 text-sm text-gray-900">
                                        <?php if ($pedido['modalidad'] === 'Delivery'): ?>
                                            <span class="inline-flex items-center text-green-600">
                                                <i class="fas fa-truck mr-1"></i>Delivery
                                            </span>
                                        <?php else: ?>
                                            <span class="inline-flex items-center text-blue-600">
                                                <i class="fas fa-store mr-1"></i>Retira
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-6 py-4 text-sm text-gray-500">
                                        <div><?= date('d/m/Y', strtotime($pedido['created_at'])) ?></div>
                                        <div class="text-xs"><?= date('H:i', strtotime($pedido['created_at'])) ?></div>
                                    </td>
                                    <td class="px-6 py-4">
                                        <?php if ($pedido['impreso']): ?>
                                            <div class="flex flex-col space-y-1">
                                                <span class="bg-green-100 text-green-800 px-2 py-1 rounded-full text-xs">
                                                    <i class="fas fa-check-circle mr-1"></i>Impresa
                                                </span>
                                                <button onclick="reimprimir(<?= $pedido['id'] ?>)" 
                                                        class="bg-gray-400 hover:bg-gray-500 text-white px-2 py-1 rounded text-xs">
                                                    <i class="fas fa-redo mr-1"></i>Reimprimir
                                                </button>
                                            </div>
                                        <?php else: ?>
                                            <div class="flex flex-col space-y-1">
                                                <span class="bg-red-100 text-red-800 px-2 py-1 rounded-full text-xs">
                                                    <i class="fas fa-exclamation-circle mr-1"></i>Sin imprimir
                                                </span>
                                                <button onclick="imprimirComanda(<?= $pedido['id'] ?>)" 
                                                        class="bg-blue-500 hover:bg-blue-600 text-white px-2 py-1 rounded text-xs">
                                                    <i class="fas fa-print mr-1"></i>Imprimir
                                                </button>
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-6 py-4 text-sm font-medium">
                                        <div class="flex space-x-2">
                                            <button onclick="verDetalles(<?= $pedido['id'] ?>)" 
                                                    class="text-blue-600 hover:text-blue-900 bg-blue-50 hover:bg-blue-100 px-2 py-1 rounded" 
                                                    title="Ver detalles completos (Comandera)">
                                                <i class="fas fa-eye"></i>
                                            </button>
                                            <a href="https://wa.me/<?= preg_replace('/[^0-9]/', '', $pedido['telefono']) ?>?text=Hola%20<?= urlencode($pedido['nombre']) ?>,%20tu%20pedido%20está%20<?= urlencode(strtolower($pedido['estado'])) ?>" 
                                               target="_blank" class="text-green-600 hover:text-green-900" title="WhatsApp">
                                                <i class="fab fa-whatsapp"></i>
                                            </a>
                                            <button onclick="eliminarPedido(<?= $pedido['id'] ?>, '#<?= $pedido['id'] ?> - <?= htmlspecialchars($pedido['nombre']) ?>')" 
                                                    class="text-red-600 hover:text-red-900" title="Eliminar">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>

        <!-- Footer con totales -->
        <div class="mt-6 text-center text-gray-500">
            <p>
                Mostrando <?= count($pedidos) ?> pedido<?= count($pedidos) !== 1 ? 's' : '' ?>
                <?php if ($buscar || $filtro_estado || $filtro_fecha || $filtro_modalidad): ?>
                    | <a href="?" class="text-blue-600 hover:underline">Limpiar filtros</a>
                <?php endif; ?>
            </p>
        </div>
    </main>

    <!-- Modal Detalles del Pedido (Comandera) -->
    <div id="detallesModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 hidden">
        <div class="bg-white rounded-lg w-full max-w-2xl mx-4 max-h-screen overflow-y-auto">
            <!-- Header del modal -->
            <div class="bg-orange-500 text-white p-4 rounded-t-lg">
                <div class="flex justify-between items-center">
                    <h3 class="text-xl font-bold">
                        <i class="fas fa-receipt mr-2"></i>Comanda - Pedido #<span id="modal-pedido-id"></span>
                    </h3>
                    <button onclick="cerrarDetallesModal()" class="text-white hover:text-gray-200">
                        <i class="fas fa-times text-xl"></i>
                    </button>
                </div>
            </div>
            
            <!-- Contenido del modal -->
            <div class="p-6">
                
                <!-- INFO PRINCIPAL DEL CLIENTE -->
                <div class="bg-blue-50 border border-blue-200 rounded-lg p-4 mb-6">
                    <h4 class="font-bold text-lg text-blue-800 mb-3">
                        <i class="fas fa-user mr-2"></i>INFORMACIÓN DEL CLIENTE
                    </h4>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <p class="text-sm text-gray-600">Cliente:</p>
                            <p class="font-semibold text-lg" id="modal-cliente-nombre"></p>
                        </div>
                        <div>
                            <p class="text-sm text-gray-600">Teléfono:</p>
                            <p class="font-semibold text-lg text-blue-600" id="modal-cliente-telefono"></p>
                        </div>
                    </div>
                    
                    <!-- DIRECCIÓN - MUY IMPORTANTE -->
                    <div class="mt-4 bg-yellow-50 border border-yellow-300 rounded-lg p-3" id="modal-direccion-container">
                        <div class="flex items-center mb-2">
                            <i class="fas fa-map-marker-alt text-red-500 mr-2"></i>
                            <p class="font-bold text-red-700">DIRECCIÓN DE ENTREGA:</p>
                        </div>
                        <p class="font-semibold text-lg text-gray-800" id="modal-cliente-direccion"></p>
                    </div>
                    
                    <!-- MODALIDAD -->
                    <div class="mt-4">
                        <p class="text-sm text-gray-600">Modalidad:</p>
                        <p class="font-semibold text-lg" id="modal-modalidad">
                            <span id="modal-modalidad-icono"></span>
                            <span id="modal-modalidad-texto"></span>
                        </p>
                    </div>
                </div>
                
                <!-- PRODUCTO PEDIDO -->
                <div class="bg-green-50 border border-green-200 rounded-lg p-4 mb-6">
                    <h4 class="font-bold text-lg text-green-800 mb-3">
                        <i class="fas fa-sandwich mr-2"></i>PRODUCTO PEDIDO
                    </h4>
                    <div class="space-y-3">
                        <div>
                            <p class="font-bold text-xl text-gray-800" id="modal-producto-nombre"></p>
                        </div>
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 text-center">
                            <div class="bg-white rounded-lg p-3 border">
                                <p class="text-sm text-gray-600">Cantidad:</p>
                                <p class="font-bold text-2xl text-green-600" id="modal-producto-cantidad"></p>
                            </div>
                            <div class="bg-white rounded-lg p-3 border">
                                <p class="text-sm text-gray-600">Precio:</p>
                                <p class="font-bold text-2xl text-green-600" id="modal-producto-precio"></p>
                            </div>
                            <div class="bg-white rounded-lg p-3 border">
                                <p class="text-sm text-gray-600">Forma de Pago:</p>
                                <p class="font-semibold text-lg" id="modal-forma-pago"></p>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- FECHAS Y HORARIOS -->
                <div class="bg-purple-50 border border-purple-200 rounded-lg p-4 mb-6">
                    <h4 class="font-bold text-lg text-purple-800 mb-3">
                        <i class="fas fa-clock mr-2"></i>TIEMPOS
                    </h4>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <p class="text-sm text-gray-600">Pedido tomado:</p>
                            <p class="font-semibold" id="modal-fecha-pedido"></p>
                        </div>
                        <div id="modal-entrega-info" class="hidden">
                            <p class="text-sm text-gray-600">Para cuándo es:</p>
                            <p class="font-semibold text-orange-600" id="modal-fecha-entrega"></p>
                        </div>
                    </div>
                    <div id="modal-notas-horario-container" class="mt-3 hidden">
                        <p class="text-sm text-gray-600">Notas de horario:</p>
                        <p class="font-semibold bg-yellow-100 p-2 rounded" id="modal-notas-horario"></p>
                    </div>
                </div>
                
                <!-- OBSERVACIONES -->
                <div id="modal-observaciones-container" class="bg-orange-50 border border-orange-200 rounded-lg p-4 mb-6 hidden">
                    <h4 class="font-bold text-lg text-orange-800 mb-3">
                        <i class="fas fa-comment mr-2"></i>OBSERVACIONES ESPECIALES
                    </h4>
                    <div class="bg-white p-3 rounded border">
                        <p class="font-semibold text-gray-800" id="modal-observaciones"></p>
                    </div>
                </div>
                
                <!-- ESTADO ACTUAL -->
                <div class="bg-gray-50 border border-gray-200 rounded-lg p-4 mb-6">
                    <h4 class="font-bold text-lg text-gray-800 mb-3">
                        <i class="fas fa-tasks mr-2"></i>ESTADO ACTUAL
                    </h4>
                    <div class="text-center">
                        <span class="px-4 py-2 rounded-full text-lg font-bold" id="modal-estado-badge"></span>
                        <p class="text-sm text-gray-600 mt-2" id="modal-tiempo-transcurrido"></p>
                    </div>
                </div>
                
                <!-- DATOS ADICIONALES -->
                <div class="grid grid-cols-2 md:grid-cols-4 gap-4 text-center text-sm">
                    <div>
                        <p class="text-gray-600">Pedido ID:</p>
                        <p class="font-bold" id="modal-pedido-id-footer"></p>
                    </div>
                    <div id="modal-cliente-fijo-info" class="hidden">
                        <p class="text-gray-600">Tipo Cliente:</p>
                        <p class="font-bold text-blue-600">Cliente Fijo</p>
                    </div>
                    <div>
                        <p class="text-gray-600">Impresión:</p>
                        <p class="font-bold" id="modal-impreso-estado"></p>
                    </div>
                    <div>
                        <p class="text-gray-600">Última Actualización:</p>
                        <p class="font-bold" id="modal-ultima-actualizacion"></p>
                    </div>
                </div>
            </div>
            
            <!-- Footer con acciones -->
            <div class="bg-gray-50 p-4 rounded-b-lg border-t">
                <div class="flex justify-between items-center">
                    <button onclick="cerrarDetallesModal()" class="bg-gray-500 hover:bg-gray-600 text-white px-4 py-2 rounded">
                        <i class="fas fa-times mr-2"></i>Cerrar
                    </button>
                    <div class="flex space-x-2">
                        <button onclick="imprimirComandaDesdeModal()" class="bg-blue-500 hover:bg-blue-600 text-white px-4 py-2 rounded">
                            <i class="fas fa-print mr-2"></i>Imprimir Comanda
                        </button>
                        <button onclick="contactarClienteDesdeModal()" class="bg-green-500 hover:bg-green-600 text-white px-4 py-2 rounded">
                            <i class="fab fa-whatsapp mr-2"></i>Contactar Cliente
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal para impresión -->
    <div id="impresionModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 hidden">
        <div class="bg-white rounded-lg p-6 w-full max-w-md mx-4">
            <h3 class="text-xl font-bold mb-4">
                <i class="fas fa-print text-blue-500 mr-2"></i>Imprimir Comanda
            </h3>
            <div id="impresionContent">
                <p>Preparando impresión...</p>
            </div>
            <div class="flex justify-end space-x-2 mt-6">
                <button onclick="cerrarModal()" class="bg-gray-500 hover:bg-gray-600 text-white px-4 py-2 rounded">
                    Cancelar
                </button>
                <button onclick="confirmarImpresion()" class="bg-blue-500 hover:bg-blue-600 text-white px-4 py-2 rounded">
                    <i class="fas fa-print mr-1"></i>Confirmar Impresión
                </button>
            </div>
        </div>
    </div>

    <script>
        let pedidoAImprimir = null;
        let pedidoActualModal = null;

        function cambiarEstado(id, nuevoEstado) {
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

        function imprimirComanda(pedidoId) {
            pedidoAImprimir = pedidoId;
            
            document.getElementById('impresionContent').innerHTML = `
                <div class="text-center">
                    <i class="fas fa-print text-4xl text-blue-500 mb-3"></i>
                    <p class="mb-2">Se enviará la comanda del pedido <strong>#${pedidoId}</strong> a la impresora.</p>
                    <p class="text-sm text-gray-600">Asegúrate de que la impresora esté encendida y conectada.</p>
                </div>
            `;
            
            document.getElementById('impresionModal').classList.remove('hidden');
        }

        function confirmarImpresion() {
            if (pedidoAImprimir) {
                fetch(window.location.href, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: `accion=marcar_impreso&id=${pedidoAImprimir}`
                }).then(() => {
                    location.reload();
                });
            }
            cerrarModal();
        }

        function reimprimir(pedidoId) {
            if (confirm('¿Reimprimir la comanda del pedido #' + pedidoId + '?')) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="accion" value="reimprimir">
                    <input type="hidden" name="id" value="${pedidoId}">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        }

        function cerrarModal() {
            document.getElementById('impresionModal').classList.add('hidden');
            pedidoAImprimir = null;
        }

        // ========================
        // FUNCIONES DEL MODAL DE DETALLES
        // ========================

        // Función para mostrar detalles del pedido (reemplaza la función verDetalles actual)
        function verDetalles(pedidoId) {
            // Buscar la fila del pedido en la tabla
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

        // Función para extraer datos de la fila de la tabla
        function extraerDatosDeFila(fila, pedidoId) {
            const celdas = fila.querySelectorAll('td');
            
            // Extraer información del cliente (segunda celda)
            const clienteInfo = celdas[1];
            const clienteNombre = clienteInfo.querySelector('.text-sm.font-medium').textContent.trim();
            const telefonoElement = clienteInfo.querySelector('i.fa-phone').parentNode;
            const telefono = telefonoElement.textContent.trim();
            
            // Buscar dirección
            const direccionElement = clienteInfo.querySelector('i.fa-map-marker-alt');
            let direccion = 'Sin dirección especificada';
            if (direccionElement) {
                direccion = direccionElement.parentNode.textContent.trim();
            }
            
            // Extraer información del producto (tercera celda)
            const productoInfo = celdas[2];
            const productoNombre = productoInfo.querySelector('.text-sm.font-medium').textContent.trim();
            const productoDetalles = productoInfo.querySelectorAll('.text-sm.text-gray-500');
            let cantidad = 'N/A';
            let formaPago = 'N/A';
            
            productoDetalles.forEach(detalle => {
                const texto = detalle.textContent;
                if (texto.includes('Cantidad:')) {
                    cantidad = texto.split('|')[0].replace('Cantidad:', '').trim();
                    formaPago = texto.split('|')[1].trim();
                }
            });
            
            // Observaciones
            let observaciones = '';
            const obsElement = productoInfo.querySelector('i.fa-comment');
            if (obsElement) {
                observaciones = obsElement.parentNode.textContent.trim();
            }
            
            // Precio (cuarta celda)
            const precio = celdas[3].textContent.trim();
            
            // Estado (quinta celda)
            const estadoSelect = celdas[4].querySelector('select');
            const estado = estadoSelect.value;
            
            // Modalidad (sexta celda)
            const modalidadInfo = celdas[5];
            const modalidad = modalidadInfo.textContent.includes('Delivery') ? 'Delivery' : 'Retira';
            
            // Fecha (séptima celda)
            const fechaInfo = celdas[6];
            const fechas = fechaInfo.textContent.split('\n').map(f => f.trim()).filter(f => f);
            const fechaPedido = fechas.length >= 2 ? `${fechas[0]} ${fechas[1]}` : fechas[0] || 'N/A';
            
            // Estado de impresión (octava celda)
            const impresionInfo = celdas[7];
            const impreso = impresionInfo.textContent.includes('Impresa');

            // Información de entrega (buscar en cliente info)
            let fechaEntrega = '';
            let horaEntrega = '';
            let notasHorario = '';
            
            const entregaInfo = clienteInfo.querySelector('.text-xs.text-orange-600');
            if (entregaInfo) {
                const textoEntrega = entregaInfo.textContent;
                if (textoEntrega.includes('Para:')) {
                    fechaEntrega = textoEntrega;
                }
            }
            
            return {
                id: pedidoId,
                cliente: {
                    nombre: clienteNombre,
                    telefono: telefono,
                    direccion: direccion,
                    esClienteFijo: clienteNombre.includes('(Cliente fijo)')
                },
                producto: {
                    nombre: productoNombre,
                    cantidad: cantidad,
                    precio: precio,
                    formaPago: formaPago
                },
                modalidad: modalidad,
                estado: estado,
                fechaPedido: fechaPedido,
                fechaEntrega: fechaEntrega,
                observaciones: observaciones,
                impreso: impreso
            };
        }

        // Función para mostrar el modal con los datos
        function mostrarModalDetalles(data) {
            pedidoActualModal = data;
            
            // Llenar información básica
            document.getElementById('modal-pedido-id').textContent = data.id;
            document.getElementById('modal-pedido-id-footer').textContent = data.id;
            
            // Información del cliente
            document.getElementById('modal-cliente-nombre').textContent = data.cliente.nombre;
            document.getElementById('modal-cliente-telefono').textContent = data.cliente.telefono;
            
            // DIRECCIÓN - MUY IMPORTANTE
            const direccionContainer = document.getElementById('modal-direccion-container');
            const direccionElement = document.getElementById('modal-cliente-direccion');
            
            if (data.cliente.direccion && data.cliente.direccion !== 'Sin dirección especificada' && !data.cliente.direccion.includes('Sin dirección')) {
                direccionElement.textContent = data.cliente.direccion;
                direccionContainer.classList.remove('hidden');
                direccionContainer.classList.remove('bg-red-50', 'border-red-300');
                direccionContainer.classList.add('bg-yellow-50', 'border-yellow-300');
                direccionElement.classList.remove('text-red-700');
                direccionElement.classList.add('text-gray-800');
            } else {
                direccionElement.textContent = '⚠️ SIN DIRECCIÓN ESPECIFICADA - CONTACTAR CLIENTE';
                direccionContainer.classList.remove('hidden');
                direccionContainer.classList.remove('bg-yellow-50', 'border-yellow-300');
                direccionContainer.classList.add('bg-red-50', 'border-red-300');
                direccionElement.classList.add('text-red-700', 'font-bold');
            }
            
            // Modalidad
            const modalidadIcono = document.getElementById('modal-modalidad-icono');
            const modalidadTexto = document.getElementById('modal-modalidad-texto');
            
            if (data.modalidad === 'Delivery') {
                modalidadIcono.innerHTML = '<i class="fas fa-truck text-green-500 mr-2"></i>';
                modalidadTexto.textContent = 'DELIVERY';
                modalidadTexto.className = 'font-bold text-green-600';
            } else {
                modalidadIcono.innerHTML = '<i class="fas fa-store text-blue-500 mr-2"></i>';
                modalidadTexto.textContent = 'RETIRA EN LOCAL';
                modalidadTexto.className = 'font-bold text-blue-600';
            }
            
            // Información del producto
            document.getElementById('modal-producto-nombre').textContent = data.producto.nombre;
            document.getElementById('modal-producto-cantidad').textContent = data.producto.cantidad;
            document.getElementById('modal-producto-precio').textContent = data.producto.precio;
            document.getElementById('modal-forma-pago').textContent = data.producto.formaPago;
            
            // Fechas
            document.getElementById('modal-fecha-pedido').textContent = data.fechaPedido;
            
            // Información de entrega
            const entregaInfo = document.getElementById('modal-entrega-info');
            const fechaEntregaElement = document.getElementById('modal-fecha-entrega');
            
            if (data.fechaEntrega && data.fechaEntrega.length > 0) {
                fechaEntregaElement.textContent = data.fechaEntrega;
                entregaInfo.classList.remove('hidden');
            } else {
                entregaInfo.classList.add('hidden');
            }
            
            // Estado
            const estadoBadge = document.getElementById('modal-estado-badge');
            const estadoColors = {
                'Pendiente': 'bg-yellow-100 text-yellow-800',
                'Preparando': 'bg-blue-100 text-blue-800',
                'Listo': 'bg-green-100 text-green-800',
                'Entregado': 'bg-gray-100 text-gray-800'
            };
            
            estadoBadge.textContent = data.estado.toUpperCase();
            estadoBadge.className = `px-4 py-2 rounded-full text-lg font-bold ${estadoColors[data.estado] || 'bg-gray-100 text-gray-800'}`;
            
            // Observaciones
            const obsContainer = document.getElementById('modal-observaciones-container');
            const obsElement = document.getElementById('modal-observaciones');
            
            if (data.observaciones && data.observaciones.length > 0) {
                obsElement.textContent = data.observaciones;
                obsContainer.classList.remove('hidden');
            } else {
                obsContainer.classList.add('hidden');
            }
            
            // Cliente fijo
            const clienteFijoInfo = document.getElementById('modal-cliente-fijo-info');
            if (data.cliente.esClienteFijo) {
                clienteFijoInfo.classList.remove('hidden');
            } else {
                clienteFijoInfo.classList.add('hidden');
            }
            
            // Estado de impresión
            const impresoEstado = document.getElementById('modal-impreso-estado');
            if (data.impreso) {
                impresoEstado.textContent = '✅ Impresa';
                impresoEstado.className = 'font-bold text-green-600';
            } else {
                impresoEstado.textContent = '❌ Sin imprimir';
                impresoEstado.className = 'font-bold text-red-600';
            }
            
            // Tiempo transcurrido
            const tiempoElement = document.getElementById('modal-tiempo-transcurrido');
            tiempoElement.textContent = 'Ver información completa en la comandera';
            
            // Última actualización
            document.getElementById('modal-ultima-actualizacion').textContent = new Date().toLocaleString('es-AR');
            
            // Mostrar modal
            document.getElementById('detallesModal').classList.remove('hidden');
        }

        // Función para cerrar el modal de detalles
        function cerrarDetallesModal() {
            document.getElementById('detallesModal').classList.add('hidden');
            pedidoActualModal = null;
        }

        // Función para imprimir comanda desde el modal
        function imprimirComandaDesdeModal() {
            if (pedidoActualModal) {
                imprimirComanda(pedidoActualModal.id);
                cerrarDetallesModal();
            }
        }

        // Función para contactar cliente desde el modal
        function contactarClienteDesdeModal() {
            if (pedidoActualModal) {
                const telefono = pedidoActualModal.cliente.telefono.replace(/[^0-9]/g, '');
                const nombre = pedidoActualModal.cliente.nombre.split('(')[0].trim();
                const estado = pedidoActualModal.estado.toLowerCase();
                
                const url = `https://wa.me/${telefono}?text=Hola%20${encodeURIComponent(nombre)},%20tu%20pedido%20#${pedidoActualModal.id}%20está%20${encodeURIComponent(estado)}`;
                window.open(url, '_blank');
            }
        }

        // Cerrar modales al hacer clic fuera
        document.addEventListener('DOMContentLoaded', function() {
            const impresionModal = document.getElementById('impresionModal');
            const detallesModal = document.getElementById('detallesModal');
            
            if (impresionModal) {
                impresionModal.addEventListener('click', function(e) {
                    if (e.target === this) {
                        cerrarModal();
                    }
                });
            }
            
            if (detallesModal) {
                detallesModal.addEventListener('click', function(e) {
                    if (e.target === this) {
                        cerrarDetallesModal();
                    }
                });
            }
        });

        // Establecer fecha de hoy por defecto si no hay fecha seleccionada
        document.addEventListener('DOMContentLoaded', function() {
            const fechaInput = document.querySelector('input[name="fecha"]');
            if (fechaInput && !fechaInput.value) {
                const urlParams = new URLSearchParams(window.location.search);
                if (!urlParams.has('buscar') && !urlParams.has('estado') && !urlParams.has('modalidad')) {
                    const hoy = new Date().toISOString().split('T')[0];
                    fechaInput.value = hoy;
                }
            }
        });
    </script>
</body>
</html>