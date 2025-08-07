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

    <!-- Modal para impresión -->
    <div id="impresionModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 hidden">
        <div class="bg-white rounded-lg p-6 w-full max-w-md mx-4">
            <h3 class="text-xl font-bold mb-4">
                <i class="fas fa-print text-orange-500 mr-2"></i>Imprimir Comanda
            </h3>
            <div id="impresionContent">
                <p>Preparando impresión...</p>
            </div>
            <div class="flex justify-end space-x-2 mt-6">
                <button onclick="cerrarModalImpresion()" class="bg-gray-500 hover:bg-gray-600 text-white px-4 py-2 rounded">
                    Cancelar
                </button>
                <button onclick="confirmarImpresion()" class="bg-orange-500 hover:bg-orange-600 text-white px-4 py-2 rounded">
                    <i class="fas fa-print mr-1"></i>Imprimir
                </button>
            </div>
        </div>
    </div>

    <script>
        let pedidoAImprimir = null;

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
                    <i class="fas fa-print text-4xl text-orange-500 mb-3"></i>
                    <p class="mb-2">Se enviará la comanda del pedido <strong>#${pedidoId}</strong> a la impresora.</p>
                    <p class="text-sm text-gray-600 mb-4">Comandita 3nstar RPT006S - 80mm</p>
                    <div class="bg-gray-50 p-3 rounded text-left text-sm">
                        <p class="font-medium mb-1">Se imprimirá:</p>
                        <ul class="text-gray-600 text-xs space-y-1">
                            <li>• Datos del cliente</li>
                            <li>• Producto y cantidad</li>
                            <li>• Precio y forma de pago</li>
                            <li>• Modalidad (Retira/Delivery)</li>
                            <li>• Observaciones</li>
                        </ul>
                    </div>
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
            cerrarModalImpresion();
        }

        function reimprimir(pedidoId) {
            if (confirm('¿Reimprimir la comanda del pedido #' + pedidoId + '?\n\nEsto creará una nueva impresión.')) {
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

        function cerrarModalImpresion() {
            document.getElementById('impresionModal').classList.add('hidden');
            pedidoAImprimir = null;
        }

        // Cerrar modal al hacer clic fuera
        document.getElementById('impresionModal').addEventListener('click', function(e) {
            if (e.target === this) {
                cerrarModalImpresion();
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
</html><?php
require_once '../../config.php';
requireLogin();

$pdo = getConnection();

// Manejar acciones (cambiar estado, eliminar)
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
        }
    }
}

// Filtros
$filtro_estado = isset($_GET['estado']) ? sanitize($_GET['estado']) : '';
$filtro_fecha = isset($_GET['fecha']) ? sanitize($_GET['fecha']) : '';
$filtro_modalidad = isset($_GET['modalidad']) ? sanitize($_GET['modalidad']) : '';
$buscar = isset($_GET['buscar']) ? sanitize($_GET['buscar']) : '';
$orden = isset($_GET['orden']) ? sanitize($_GET['orden']) : 'created_at DESC';

// Construir consulta
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
                                            <i class="fas fa-map-marker-alt text-xs mr-1"></i><?= htmlspecialchars($pedido['direccion']) ?>
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
                                        <?php if ($pedido['impreso']): ?>
                                        <div class="text-xs text-green-600 mt-1">
                                            <i class="fas fa-print text-xs mr-1"></i>Comanda impresa
                                        </div>
                                        <?php else: ?>
                                        <div class="text-xs text-red-600 mt-1">
                                            <i class="fas fa-exclamation-circle text-xs mr-1"></i>Sin imprimir
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
                                    <td class="px-6 py-4 text-sm font-medium">
                                        <div class="flex space-x-2">
                                            <?php if ($pedido['impreso']): ?>
                                                <button onclick="reimprimir(<?= $pedido['id'] ?>)" 
                                                        class="text-gray-600 hover:text-gray-900" title="Reimprimir">
                                                    <i class="fas fa-print"></i>
                                                </button>
                                            <?php else: ?>
                                                <button onclick="imprimirComanda(<?= $pedido['id'] ?>)" 
                                                        class="text-blue-600 hover:text-blue-900" title="Imprimir comanda">
                                                    <i class="fas fa-print"></i>
                                                </button>
                                            <?php endif; ?>
                                            <a href="https://wa.me/<?= preg_replace('/[^0-9]/', '', $pedido['telefono']) ?>?text=Hola%20<?= urlencode($pedido['nombre']) ?>,%20tu%20pedido%20está%20<?= urlencode(st