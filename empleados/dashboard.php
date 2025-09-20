<?php
require_once '../admin/config.php';
session_start();

// Verificar acceso de empleado
if (!isset($_SESSION['empleado_logged']) || $_SESSION['empleado_logged'] !== true) {
    header('Location: login.php');
    exit;
}

$pdo = getConnection();

// Procesar acciones AJAX
if ($_POST && isset($_POST['accion'])) {
    header('Content-Type: application/json');
    
    switch ($_POST['accion']) {
        case 'marcar_impreso':
            $pedido_id = (int)$_POST['pedido_id'];
            $stmt = $pdo->prepare("UPDATE pedidos SET impreso = 1 WHERE id = ?");
            $result = $stmt->execute([$pedido_id]);
            echo json_encode(['success' => $result]);
            exit;
            
        case 'cambiar_estado':
            $pedido_id = (int)$_POST['pedido_id'];
            $nuevo_estado = sanitize($_POST['nuevo_estado']);
            $stmt = $pdo->prepare("UPDATE pedidos SET estado = ? WHERE id = ?");
            $result = $stmt->execute([$nuevo_estado, $pedido_id]);
            echo json_encode(['success' => $result]);
            exit;
    }
}

// Obtener solo pedidos de Local 1 de hoy que no estén entregados
$pedidos = $pdo->query("
    SELECT id, nombre, apellido, producto, precio, estado, modalidad,
           observaciones, direccion, telefono, forma_pago, cantidad,
           created_at, TIMESTAMPDIFF(MINUTE, created_at, NOW()) as minutos_transcurridos,
           impreso
    FROM pedidos 
    WHERE ubicacion = 'Local 1'
    AND DATE(created_at) = CURDATE()
    AND estado != 'Entregado'
    ORDER BY 
        CASE estado 
            WHEN 'Pendiente' THEN 1 
            WHEN 'Preparando' THEN 2 
            WHEN 'Listo' THEN 3 
        END, 
        created_at ASC
")->fetchAll();

// Contar por estados
$total = count($pedidos);
$pendientes = count(array_filter($pedidos, fn($p) => $p['estado'] === 'Pendiente'));
$preparando = count(array_filter($pedidos, fn($p) => $p['estado'] === 'Preparando'));
$listos = count(array_filter($pedidos, fn($p) => $p['estado'] === 'Listo'));
$sin_imprimir = count(array_filter($pedidos, fn($p) => $p['impreso'] == 0));
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Local 1 - Santa Catalina</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { font-family: 'Arial', sans-serif; }
        .pedido-card {
            transition: all 0.3s ease;
            border-left: 6px solid #ccc;
        }
        .pedido-pendiente { border-left-color: #f59e0b; background: #fef3c7; }
        .pedido-preparando { border-left-color: #3b82f6; background: #dbeafe; }
        .pedido-listo { border-left-color: #10b981; background: #d1fae5; }
        .sin-imprimir { border-right: 6px solid #ef4444; }
        .urgente { animation: pulse 2s infinite; }
        @keyframes pulse { 0%, 100% { opacity: 1; } 50% { opacity: 0.7; } }
        .btn { 
            padding: 8px 16px; 
            border-radius: 6px; 
            border: none; 
            cursor: pointer; 
            font-weight: 600;
            transition: all 0.2s;
        }
        .btn:hover { transform: translateY(-1px); }
        .btn-preparando { background: #3b82f6; color: white; }
        .btn-listo { background: #10b981; color: white; }
        .btn-entregado { background: #6b7280; color: white; }
        .btn-imprimir { background: #f59e0b; color: white; }
    </style>
</head>
<body class="bg-gray-50">
    <!-- Header Simple -->
    <header class="bg-blue-600 text-white p-4 shadow-lg">
        <div class="max-w-7xl mx-auto flex justify-between items-center">
            <div class="flex items-center space-x-4">
                <h1 class="text-2xl font-bold">🏪 LOCAL 1</h1>
                <div id="clock" class="text-blue-100 text-lg"></div>
            </div>
            
            <!-- Stats Header -->
            <div class="flex space-x-6 text-sm">
                <div class="text-center">
                    <div class="text-2xl font-bold"><?= $total ?></div>
                    <div>Total</div>
                </div>
                <div class="text-center text-yellow-200">
                    <div class="text-2xl font-bold"><?= $pendientes ?></div>
                    <div>Pendientes</div>
                </div>
                <div class="text-center text-blue-200">
                    <div class="text-2xl font-bold"><?= $preparando ?></div>
                    <div>Preparando</div>
                </div>
                <div class="text-center text-green-200">
                    <div class="text-2xl font-bold"><?= $listos ?></div>
                    <div>Listos</div>
                </div>
                <div class="text-center text-red-200">
                    <div class="text-2xl font-bold"><?= $sin_imprimir ?></div>
                    <div>Sin Imprimir</div>
                </div>
            </div>
            
            <a href="logout.php" class="bg-red-500 hover:bg-red-600 px-4 py-2 rounded">
                <i class="fas fa-sign-out-alt mr-1"></i>Salir
            </a>
        </div>
    </header>

    <!-- Lista de Pedidos Estilo Turnos -->
    <main class="max-w-7xl mx-auto p-6">
        <?php if (empty($pedidos)): ?>
            <div class="text-center py-20">
                <i class="fas fa-coffee text-6xl text-gray-300 mb-4"></i>
                <h2 class="text-2xl text-gray-500">No hay pedidos pendientes</h2>
                <p class="text-gray-400">Local 1 está al día</p>
            </div>
        <?php else: ?>
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                <?php foreach ($pedidos as $pedido): ?>
                    <div class="pedido-card pedido-<?= strtolower($pedido['estado']) ?> 
                                <?= !$pedido['impreso'] ? 'sin-imprimir' : '' ?>
                                <?= $pedido['minutos_transcurridos'] > 60 ? 'urgente' : '' ?>
                                p-6 rounded-lg shadow-lg"
                         data-id="<?= $pedido['id'] ?>">
                        
                        <!-- Header del Pedido -->
                        <div class="flex justify-between items-center mb-4">
                            <div class="text-2xl font-bold text-gray-800">
                                #<?= $pedido['id'] ?>
                            </div>
                            
                            <div class="flex items-center space-x-2">
                                <!-- Tiempo -->
                                <span class="text-sm px-2 py-1 rounded 
                                           <?= $pedido['minutos_transcurridos'] > 60 ? 'bg-red-100 text-red-800' : 'bg-gray-100 text-gray-600' ?>">
                                    <?php if ($pedido['minutos_transcurridos'] > 60): ?>
                                        ⚠️ <?= round($pedido['minutos_transcurridos']/60, 1) ?>h
                                    <?php else: ?>
                                        <?= $pedido['minutos_transcurridos'] ?>min
                                    <?php endif; ?>
                                </span>
                                
                                <!-- Modalidad -->
                                <?php if ($pedido['modalidad'] === 'Delivery'): ?>
                                    <i class="fas fa-truck text-green-600 text-xl" title="Delivery"></i>
                                <?php else: ?>
                                    <i class="fas fa-store text-blue-600 text-xl" title="Retira"></i>
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- Info Cliente -->
                        <div class="mb-4">
                            <div class="text-lg font-semibold text-gray-800 mb-1">
                                <?= htmlspecialchars($pedido['nombre'] . ' ' . $pedido['apellido']) ?>
                            </div>
                            <div class="text-sm text-gray-600">
                                📞 <?= htmlspecialchars($pedido['telefono']) ?>
                            </div>
                        </div>

                        <!-- Producto -->
                        <div class="mb-4 p-3 bg-white rounded border-l-4 border-orange-400">
                            <div class="font-bold text-gray-800 mb-1">
                                <?= htmlspecialchars($pedido['producto']) ?>
                            </div>
                            <div class="flex justify-between items-center">
                                <span class="text-sm text-gray-600">
                                    Cant: <?= $pedido['cantidad'] ?: 1 ?>
                                </span>
                                <span class="text-lg font-bold text-green-600">
                                    <?= formatPrice($pedido['precio']) ?>
                                </span>
                            </div>
                        </div>

                        <!-- Observaciones -->
                        <?php if ($pedido['observaciones']): ?>
                            <div class="mb-4 p-2 bg-yellow-50 rounded border-l-2 border-yellow-400">
                                <div class="text-sm text-gray-700">
                                    💬 <?= htmlspecialchars($pedido['observaciones']) ?>
                                </div>
                            </div>
                        <?php endif; ?>

                        <!-- Dirección para Delivery -->
                        <?php if ($pedido['modalidad'] === 'Delivery' && $pedido['direccion']): ?>
                            <div class="mb-4 p-2 bg-blue-50 rounded border-l-2 border-blue-400">
                                <div class="text-sm text-gray-700">
                                    📍 <?= htmlspecialchars($pedido['direccion']) ?>
                                </div>
                            </div>
                        <?php endif; ?>

                        <!-- Estado Actual -->
                        <div class="mb-4 text-center">
                            <span class="inline-block px-4 py-2 rounded-full text-sm font-bold
                                       <?php 
                                       switch($pedido['estado']) {
                                           case 'Pendiente': echo 'bg-yellow-200 text-yellow-800'; break;
                                           case 'Preparando': echo 'bg-blue-200 text-blue-800'; break;
                                           case 'Listo': echo 'bg-green-200 text-green-800'; break;
                                       }
                                       ?>">
                                <?php 
                                switch($pedido['estado']) {
                                    case 'Pendiente': echo '📋 PENDIENTE'; break;
                                    case 'Preparando': echo '🔥 PREPARANDO'; break;
                                    case 'Listo': echo '✅ LISTO'; break;
                                }
                                ?>
                            </span>
                        </div>

                        <!-- Botones de Acción -->
                        <div class="space-y-2">
                            <!-- Estado de Impresión -->
                            <?php if (!$pedido['impreso']): ?>
                                <div class="grid grid-cols-2 gap-2">
                                    <button onclick="marcarImpreso(<?= $pedido['id'] ?>)" 
                                            class="btn btn-imprimir">
                                        🖨️ IMPRESO
                                    </button>
                                    <button onclick="imprimirComanda(<?= $pedido['id'] ?>)" 
                                            class="btn bg-purple-600 text-white">
                                        📄 IMPRIMIR
                                    </button>
                                </div>
                            <?php else: ?>
                                <div class="text-center text-green-600 font-bold text-sm py-2">
                                    ✅ IMPRESO
                                </div>
                            <?php endif; ?>

                            <!-- Botones de Estado -->
                            <div class="grid grid-cols-1 gap-2">
                                <?php if ($pedido['estado'] === 'Pendiente'): ?>
                                    <button onclick="cambiarEstado(<?= $pedido['id'] ?>, 'Preparando')" 
                                            class="btn btn-preparando w-full">
                                        🔥 PREPARANDO
                                    </button>
                                <?php elseif ($pedido['estado'] === 'Preparando'): ?>
                                    <button onclick="cambiarEstado(<?= $pedido['id'] ?>, 'Listo')" 
                                            class="btn btn-listo w-full">
                                        ✅ LISTO
                                    </button>
                                <?php elseif ($pedido['estado'] === 'Listo'): ?>
                                    <button onclick="cambiarEstado(<?= $pedido['id'] ?>, 'Entregado')" 
                                            class="btn btn-entregado w-full">
                                        🚚 ENTREGADO
                                    </button>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </main>

    <script>
        // Reloj en tiempo real
        function updateClock() {
            const now = new Date();
            const timeString = now.toLocaleTimeString('es-AR', { 
                hour: '2-digit', 
                minute: '2-digit',
                second: '2-digit'
            });
            document.getElementById('clock').textContent = `🕐 ${timeString}`;
        }
        setInterval(updateClock, 1000);
        updateClock();

        // Marcar como impreso
        function marcarImpreso(id) {
            fetch(window.location.href, {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: `accion=marcar_impreso&pedido_id=${id}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    location.reload();
                } else {
                    alert('Error al marcar como impreso');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Error de conexión');
            });
        }

        // Cambiar estado
        function cambiarEstado(id, estado) {
            fetch(window.location.href, {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: `accion=cambiar_estado&pedido_id=${id}&nuevo_estado=${estado}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    location.reload();
                } else {
                    alert('Error al cambiar estado');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Error de conexión');
            });
        }

        // Auto-refresh cada 30 segundos
        setInterval(() => {
            location.reload();
        }, 30000);

        // Sonido cuando hay pedidos urgentes
        document.addEventListener('DOMContentLoaded', function() {
            const urgentes = document.querySelectorAll('.urgente');
            if (urgentes.length > 0) {
                console.log(`⚠️ ${urgentes.length} pedido(s) urgente(s) detectado(s)`);
            }
        });

        // Imprimir comanda
        function imprimirComanda(id) {
            // Abrir módulo de impresión
            const url = `../admin/modules/impresion/comanda_multi.php?pedido=${id}&ubicacion=Local 1&auto=1`;
            const ventana = window.open(url, '_blank', 'width=500,height=700,scrollbars=yes');
            
            if (!ventana) {
                alert('Error: No se pudo abrir la ventana de impresión.\nVerificar que no esté bloqueada por el navegador.');
                return;
            }
            
            ventana.focus();
            console.log('🖨️ Imprimiendo comanda #' + id);
        }
        document.addEventListener('keydown', function(e) {
            if (e.key === 'F5') {
                e.preventDefault();
                location.reload();
            }
        });
    </script>
</body>
</html>