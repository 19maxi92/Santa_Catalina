<?php
require_once '../config.php';
session_start();

// Verificar acceso de empleado
if (!isset($_SESSION['empleado_logged']) || $_SESSION['empleado_logged'] !== true) {
    header('Location: login.php');
    exit;
}

$pdo = getConnection();

// Manejar acciones
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
                    // Registrar reimpresión (podrías crear una tabla de log si quieres)
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

// Filtros (similar al admin pero adaptado)
$filtro_estado = isset($_GET['estado']) ? sanitize($_GET['estado']) : '';
$filtro_fecha = isset($_GET['fecha']) ? sanitize($_GET['fecha']) : date('Y-m-d'); // Por defecto hoy
$buscar = isset($_GET['buscar']) ? sanitize($_GET['buscar']) : '';

// Construir consulta
$sql = "SELECT p.*, cf.nombre as cliente_nombre, cf.apellido as cliente_apellido 
        FROM pedidos p 
        LEFT JOIN clientes_fijos cf ON p.cliente_fijo_id = cf.id 
        WHERE DATE(p.created_at) = ?";
$params = [$filtro_fecha];

if ($filtro_estado) {
    $sql .= " AND p.estado = ?";
    $params[] = $filtro_estado;
}

if ($buscar) {
    $sql .= " AND (p.nombre LIKE ? OR p.apellido LIKE ? OR p.telefono LIKE ? OR p.producto LIKE ?)";
    $buscarParam = "%$buscar%";
    $params = array_merge($params, [$buscarParam, $buscarParam, $buscarParam, $buscarParam]);
}

$sql .= " ORDER BY 
    CASE p.estado 
        WHEN 'Pendiente' THEN 1 
        WHEN 'Preparando' THEN 2 
        WHEN 'Listo' THEN 3 
        WHEN 'Entregado' THEN 4 
    END, 
    p.created_at ASC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$pedidos = $stmt->fetchAll();

// Estadísticas del día seleccionado
$stats_sql = "SELECT 
    COUNT(*) as total,
    SUM(CASE WHEN estado = 'Pendiente' THEN 1 ELSE 0 END) as pendientes,
    SUM(CASE WHEN estado = 'Preparando' THEN 1 ELSE 0 END) as preparando,
    SUM(CASE WHEN estado = 'Listo' THEN 1 ELSE 0 END) as listos,
    SUM(CASE WHEN estado = 'Entregado' THEN 1 ELSE 0 END) as entregados,
    SUM(CASE WHEN impreso = 1 THEN 1 ELSE 0 END) as impresos,
    SUM(precio) as total_ventas
    FROM pedidos WHERE DATE(created_at) = ?";

$stats = $pdo->prepare($stats_sql);
$stats->execute([$filtro_fecha]);
$stats = $stats->fetch();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión de Pedidos - Empleados</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="bg-gray-100">
    <!-- Header -->
    <header class="bg-blue-600 text-white shadow-md">
        <div class="container mx-auto px-4 py-3 flex justify-between items-center">
            <div class="flex items-center">
                <a href="dashboard.php" class="text-blue-100 hover:text-white mr-4">
                    <i class="fas fa-arrow-left"></i>
                </a>
                <h1 class="text-xl font-bold">
                    <i class="fas fa-clipboard-list mr-2"></i>Gestión de Pedidos
                </h1>
            </div>
            <a href="logout.php" class="bg-red-500 hover:bg-red-600 px-3 py-2 rounded">
                <i class="fas fa-sign-out-alt mr-1"></i>Salir
            </a>
        </div>
    </header>

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

        <!-- Estadísticas -->
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
            <form method="GET" class="grid grid-cols-1 md:grid-cols-4 gap-4">
                <!-- Buscador -->
                <div>
                    <input type="text" name="buscar" value="<?= htmlspecialchars($buscar) ?>" 
                           placeholder="Buscar cliente, producto..." 
                           class="w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500">
                </div>
                
                <!-- Estado -->
                <div>
                    <select name="estado" class="w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500">
                        <option value="">Todos los estados</option>
                        <option value="Pendiente" <?= $filtro_estado === 'Pendiente' ? 'selected' : '' ?>>⏳ Pendiente</option>
                        <option value="Preparando" <?= $filtro_estado === 'Preparando' ? 'selected' : '' ?>>🔥 Preparando</option>
                        <option value="Listo" <?= $filtro_estado === 'Listo' ? 'selected' : '' ?>>✅ Listo</option>
                        <option value="Entregado" <?= $filtro_estado === 'Entregado' ? 'selected' : '' ?>>📦 Entregado</option>
                    </select>
                </div>
                
                <!-- Fecha -->
                <div>
                    <input type="date" name="fecha" value="<?= htmlspecialchars($filtro_fecha) ?>" 
                           class="w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500">
                </div>
                
                <!-- Botón buscar -->
                <div>
                    <button type="submit" class="w-full bg-blue-500 hover:bg-blue-600 text-white py-2 px-4 rounded-lg">
                        <i class="fas fa-search mr-1"></i>Filtrar
                    </button>
                </div>
            </form>
        </div>

        <!-- Lista de pedidos -->
        <div class="space-y-4">
            <?php if (empty($pedidos)): ?>
                <div class="bg-white p-12 rounded-lg shadow text-center text-gray-500">
                    <i class="fas fa-inbox text-6xl mb-4 text-gray-300"></i>
                    <h3 class="text-xl mb-2">No hay pedidos</h3>
                    <p>No se encontraron pedidos para la fecha seleccionada</p>
                </div>
            <?php else: ?>
                <?php foreach ($pedidos as $pedido): ?>
                    <?php
                    $minutos = round((time() - strtotime($pedido['created_at'])) / 60);
                    $estado_colors = [
                        'Pendiente' => 'border-l-yellow-400 bg-yellow-50',
                        'Preparando' => 'border-l-blue-400 bg-blue-50',
                        'Listo' => 'border-l-green-400 bg-green-50',
                        'Entregado' => 'border-l-gray-400 bg-gray-50'
                    ];
                    
                    $urgencia_class = '';
                    if ($minutos > 60) {
                        $urgencia_class = 'border-r-4 border-r-red-500';
                    } elseif ($minutos > 30) {
                        $urgencia_class = 'border-r-4 border-r-orange-500';
                    }
                    ?>
                    
                    <div class="bg-white rounded-lg shadow border-l-4 <?= $estado_colors[$pedido['estado']] ?> <?= $urgencia_class ?>">
                        <div class="p-6">
                            <div class="flex justify-between items-start mb-4">
                                <!-- Info principal -->
                                <div class="flex-1">
                                    <div class="flex items-center space-x-3 mb-3">
                                        <span class="text-2xl font-bold text-gray-800">#<?= $pedido['id'] ?></span>
                                        <span class="text-lg font-semibold text-gray-700">
                                            <?= htmlspecialchars($pedido['nombre'] . ' ' . $pedido['apellido']) ?>
                                        </span>
                                        <?php if ($pedido['cliente_nombre']): ?>
                                            <span class="bg-blue-100 text-blue-800 px-2 py-1 rounded-full text-xs">
                                                Cliente fijo
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                    
                                    <div class="grid md:grid-cols-2 gap-4 text-sm">
                                        <div>
                                            <p class="text-gray-600 mb-1">
                                                <i class="fas fa-phone w-4"></i> <?= htmlspecialchars($pedido['telefono']) ?>
                                            </p>
                                            <?php if ($pedido['direccion']): ?>
                                            <p class="text-gray-600 mb-1">
                                                <i class="fas fa-map-marker-alt w-4"></i> <?= htmlspecialchars($pedido['direccion']) ?>
                                            </p>
                                            <?php endif; ?>
                                            <p class="text-gray-600 mb-1">
                                                <i class="fas fa-<?= $pedido['modalidad'] === 'Delivery' ? 'truck' : 'store' ?> w-4"></i>
                                                <?= $pedido['modalidad'] ?>
                                            </p>
                                        </div>
                                        <div>
                                            <p class="text-gray-600 mb-1">
                                                <i class="fas fa-credit-card w-4"></i> <?= $pedido['forma_pago'] ?>
                                            </p>
                                            <p class="text-gray-600 mb-1">
                                                <i class="fas fa-clock w-4"></i> 
                                                <?= date('H:i', strtotime($pedido['created_at'])) ?> (Hace <?= $minutos ?> min)
                                            </p>
                                            <?php if ($pedido['observaciones']): ?>
                                            <p class="text-gray-600 mb-1">
                                                <i class="fas fa-comment w-4"></i> <?= htmlspecialchars($pedido['observaciones']) ?>
                                            </p>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- Estado y urgencia -->
                                <div class="ml-4 text-right">
                                    <?php if ($minutos > 60): ?>
                                        <div class="bg-red-100 text-red-800 px-3 py-1 rounded-full text-sm font-medium mb-2">
                                            🚨 URGENTE
                                        </div>
                                    <?php elseif ($minutos > 30): ?>
                                        <div class="bg-orange-100 text-orange-800 px-3 py-1 rounded-full text-sm font-medium mb-2">
                                            ⚠️ PRIORIDAD
                                        </div>
                                    <?php endif; ?>
                                    
                                    <form method="POST" class="inline-block">
                                        <input type="hidden" name="accion" value="cambiar_estado">
                                        <input type="hidden" name="id" value="<?= $pedido['id'] ?>">
                                        <select name="estado" onchange="this.form.submit()" 
                                                class="px-3 py-2 border rounded text-sm font-medium">
                                            <option value="Pendiente" <?= $pedido['estado'] === 'Pendiente' ? 'selected' : '' ?>>⏳ Pendiente</option>
                                            <option value="Preparando" <?= $pedido['estado'] === 'Preparando' ? 'selected' : '' ?>>🔥 Preparando</option>
                                            <option value="Listo" <?= $pedido['estado'] === 'Listo' ? 'selected' : '' ?>>✅ Listo</option>
                                            <option value="Entregado" <?= $pedido['estado'] === 'Entregado' ? 'selected' : '' ?>>📦 Entregado</option>
                                        </select>
                                    </form>
                                </div>
                            </div>
                            
                            <!-- Producto y precio -->
                            <div class="bg-gray-50 rounded-lg p-4 mb-4">
                                <div class="flex justify-between items-center">
                                    <div>
                                        <h3 class="text-lg font-semibold text-gray-800">
                                            <?= htmlspecialchars($pedido['producto']) ?>
                                        </h3>
                                        <p class="text-gray-600">Cantidad: <?= $pedido['cantidad'] ?> unidades</p>
                                    </div>
                                    <div class="text-right">
                                        <div class="text-2xl font-bold text-green-600">
                                            <?= formatPrice($pedido['precio']) ?>
                                        </div>
                                        <div class="text-sm text-gray-500"><?= $pedido['forma_pago'] ?></div>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Acciones e impresión -->
                            <div class="flex justify-between items-center">
                                <div class="flex items-center space-x-3">
                                    <?php if ($pedido['impreso']): ?>
                                        <span class="bg-green-100 text-green-800 px-3 py-1 rounded-full text-sm">
                                            <i class="fas fa-check-circle mr-1"></i>Comanda Impresa
                                        </span>
                                        <button onclick="reimprimir(<?= $pedido['id'] ?>)" 
                                                class="bg-gray-500 hover:bg-gray-600 text-white px-3 py-1 rounded text-sm">
                                            <i class="fas fa-print mr-1"></i>Reimprimir
                                        </button>
                                    <?php else: ?>
                                        <span class="bg-red-100 text-red-800 px-3 py-1 rounded-full text-sm">
                                            <i class="fas fa-exclamation-circle mr-1"></i>Sin Imprimir
                                        </span>
                                        <button onclick="imprimirComanda(<?= $pedido['id'] ?>)" 
                                                class="bg-blue-500 hover:bg-blue-600 text-white px-4 py-2 rounded font-medium">
                                            <i class="fas fa-print mr-1"></i>Imprimir Comanda
                                        </button>
                                    <?php endif; ?>
                                </div>
                                
                                <div class="flex space-x-2">
                                    <a href="https://wa.me/<?= preg_replace('/[^0-9]/', '', $pedido['telefono']) ?>?text=Hola%20<?= urlencode($pedido['nombre']) ?>,%20tu%20pedido%20#<?= $pedido['id'] ?>%20está%20<?= urlencode(strtolower($pedido['estado'])) ?>" 
                                       target="_blank" 
                                       class="bg-green-500 hover:bg-green-600 text-white px-3 py-2 rounded text-sm">
                                        <i class="fab fa-whatsapp mr-1"></i>WhatsApp
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <!-- Footer -->
        <div class="mt-8 text-center text-gray-500">
            <p class="mb-2">
                <i class="fas fa-sync-alt mr-1"></i>
                Actualizando automáticamente cada 30 segundos
            </p>
            <p class="text-sm">
                Mostrando <?= count($pedidos) ?> pedido<?= count($pedidos) !== 1 ? 's' : '' ?> 
                para el día <?= date('d/m/Y', strtotime($filtro_fecha)) ?>
            </p>
        </div>
    </main>

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

        // Auto refresh cada 30 segundos
        setInterval(function() {
            location.reload();
        }, 30000);

        function imprimirComanda(pedidoId) {
            pedidoAImprimir = pedidoId;
            
            // Mostrar modal con vista previa
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
                // Aquí iría la lógica de impresión real
                // Por ahora simulamos con una llamada que marca como impreso
                
                fetch(window.location.href, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: `accion=marcar_impreso&id=${pedidoAImprimir}`
                }).then(() => {
                    // Recargar página para ver cambios
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

        // Cerrar modal al hacer clic fuera
        document.getElementById('impresionModal').addEventListener('click', function(e) {
            if (e.target === this) {
                cerrarModal();
            }
        });

        // Efectos visuales al cargar
        document.addEventListener('DOMContentLoaded', function() {
            // Highlight de pedidos urgentes
            const urgentes = document.querySelectorAll('.border-r-red-500');
            urgentes.forEach(el => {
                el.style.animation = 'pulse 2s infinite';
            });
            
            // Sonido de notificación para pedidos nuevos (opcional)
            // Podrías implementar WebSocket o polling para detectar nuevos pedidos
        });
    </script>

    <style>
        @keyframes pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.7; }
        }
    </style>
</body>
</html>