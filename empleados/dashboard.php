<?php
require_once '../admin/config.php';
session_start();

if (!isset($_SESSION['empleado_logged']) || $_SESSION['empleado_logged'] !== true) {
    header('Location: login.php');
    exit;
}

$mi_ubicacion = $_SESSION['empleado_ubicacion'] ?? 'Local 1';
$mi_nombre    = $_SESSION['empleado_name'] ?? 'Empleado';
$ubicaciones_visibles = ubicacionesVisibles($mi_ubicacion);
$placeholders_ubicacion = implode(',', array_fill(0, count($ubicaciones_visibles), '?'));

$pdo = getConnection();

$stats = [
    'pedidos_hoy' => $pdo->prepare("SELECT COUNT(*) FROM pedidos WHERE ubicacion IN ($placeholders_ubicacion) AND DATE(created_at) = CURDATE() AND (tomado = 1 OR tomado IS NULL)"),
    'pedidos_pendientes' => $pdo->prepare("SELECT COUNT(*) FROM pedidos WHERE ubicacion IN ($placeholders_ubicacion) AND estado = 'Pendiente' AND (tomado = 1 OR tomado IS NULL)"),
    'pedidos_online_hoy' => $pdo->prepare("SELECT COUNT(*) FROM pedidos WHERE ubicacion IN ($placeholders_ubicacion) AND DATE(created_at) = CURDATE() AND observaciones LIKE '%PEDIDO ONLINE%'"),
    'pedidos_online_pendientes' => $pdo->prepare("SELECT COUNT(*) FROM pedidos WHERE ubicacion IN ($placeholders_ubicacion) AND estado = 'Pendiente' AND observaciones LIKE '%PEDIDO ONLINE%'"),
];
foreach ($stats as $key => $stmt) {
    $stmt->execute($ubicaciones_visibles);
    $stats[$key] = (int)$stmt->fetchColumn();
}

// Últimos pedidos de la sucursal (incluye reparto si corresponde)
$stmt = $pdo->prepare("
    SELECT id, nombre, apellido, producto, precio, estado, ubicacion,
           fecha_display, created_at, observaciones
    FROM pedidos
    WHERE ubicacion IN ($placeholders_ubicacion)
    AND (tomado = 1 OR tomado IS NULL)
    ORDER BY created_at DESC
    LIMIT 5
");
$stmt->execute($ubicaciones_visibles);
$ultimos_pedidos = $stmt->fetchAll();

$ICONO_UBICACION = ['Local 1' => '🏪', 'Fábrica' => '🏭', 'Villa Elisa' => '🏬'];
$icono_mi_ubicacion = $ICONO_UBICACION[$mi_ubicacion] ?? '🏪';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Empleados - <?= htmlspecialchars($mi_ubicacion) ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="bg-gray-100">
    <!-- Header -->
    <header class="bg-white shadow-md sticky top-0 z-50">
        <div class="container mx-auto px-3 sm:px-4 py-3 flex justify-between items-center">
            <h1 class="text-lg sm:text-xl font-bold text-gray-800">
                <span class="mr-1 sm:mr-2"><?= $icono_mi_ubicacion ?></span>
                <span class="hidden sm:inline">Santa Catalina - <?= htmlspecialchars($mi_ubicacion) ?></span>
                <span class="sm:hidden"><?= htmlspecialchars($mi_ubicacion) ?></span>
            </h1>
            <div class="flex items-center space-x-1 sm:space-x-2">
                <button onclick="sincronizarFechas()" id="btnSincronizar" class="bg-green-500 hover:bg-green-600 text-white px-2 sm:px-3 py-2 rounded text-xs sm:text-sm" title="Sincronizar fechas de pedidos">
                    <i class="fas fa-sync-alt sm:mr-1"></i><span class="hidden lg:inline">Sync</span>
                </button>
                <span class="text-sm text-gray-600 hidden xl:inline">Hola, <?= htmlspecialchars($mi_nombre) ?></span>
                <a href="logout.php" class="bg-red-500 hover:bg-red-600 text-white px-2 sm:px-3 py-2 rounded text-xs sm:text-sm">
                    <i class="fas fa-sign-out-alt sm:mr-1"></i><span class="hidden sm:inline">Salir</span>
                </a>
            </div>
        </div>
    </header>

    <main class="container mx-auto px-3 sm:px-4 py-4 sm:py-6">

        <!-- KPIs -->
        <div class="grid grid-cols-2 gap-3 sm:gap-4 md:gap-6 mb-6 sm:mb-8">
            <div class="bg-blue-500 text-white p-4 sm:p-6 rounded-lg shadow hover:shadow-lg transition-shadow">
                <div class="flex items-center">
                    <i class="fas fa-clock text-xl sm:text-2xl mr-2 sm:mr-3"></i>
                    <div>
                        <p class="text-xs sm:text-sm opacity-80">Pedidos Hoy</p>
                        <p class="text-xl sm:text-2xl font-bold"><?= $stats['pedidos_hoy'] ?></p>
                    </div>
                </div>
            </div>

            <div class="bg-yellow-500 text-white p-4 sm:p-6 rounded-lg shadow hover:shadow-lg transition-shadow">
                <div class="flex items-center">
                    <i class="fas fa-hourglass-half text-xl sm:text-2xl mr-2 sm:mr-3"></i>
                    <div>
                        <p class="text-xs sm:text-sm opacity-80">Pendientes</p>
                        <p class="text-xl sm:text-2xl font-bold"><?= $stats['pedidos_pendientes'] ?></p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Nuevo Pedido -->
        <div class="mb-4 sm:mb-6">
            <a href="../admin/modules/pedidos/crear_pedido.php"
               class="block bg-gradient-to-r from-blue-600 to-blue-700 hover:from-blue-700 hover:to-blue-800
                      text-white p-5 sm:p-6 rounded-xl shadow-lg hover:shadow-xl transition-all transform hover:scale-[1.02]">
                <div class="flex items-center justify-center">
                    <i class="fas fa-plus-circle text-3xl sm:text-4xl mr-3 sm:mr-4"></i>
                    <div class="text-left">
                        <h3 class="text-xl sm:text-2xl font-bold">Nuevo Pedido</h3>
                        <p class="text-blue-100 text-sm sm:text-base">Click aquí para tomar pedidos rápidamente</p>
                    </div>
                </div>
            </a>
        </div>

        <!-- Ver Pedidos -->
        <div class="mb-6 sm:mb-8">
            <a href="../admin/modules/pedidos/ver_pedidos.php" class="bg-white p-4 sm:p-6 rounded-lg shadow hover:shadow-lg transition-all block text-center">
                <i class="fas fa-list text-2xl sm:text-3xl text-orange-500 mb-2 sm:mb-3"></i>
                <h3 class="text-sm sm:text-base md:text-lg font-semibold text-gray-800">Ver Pedidos</h3>
                <p class="text-xs sm:text-sm text-gray-600">Listado completo de <?= htmlspecialchars($mi_ubicacion) ?></p>
            </a>
        </div>

        <!-- Pedidos Online -->
        <div class="bg-gradient-to-r from-teal-500 to-cyan-600 rounded-xl shadow-lg mb-6 sm:mb-8 p-4 sm:p-5 text-white">
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                <div class="flex items-center">
                    <div class="w-12 h-12 bg-white bg-opacity-20 rounded-xl flex items-center justify-center mr-4 flex-shrink-0">
                        <i class="fas fa-globe text-2xl"></i>
                    </div>
                    <div>
                        <h2 class="text-lg font-black">Pedidos Online</h2>
                        <p class="text-teal-100 text-sm">Desde la app / link web</p>
                    </div>
                </div>
                <div class="flex items-center gap-4">
                    <div class="text-center">
                        <div class="text-3xl font-black"><?= $stats['pedidos_online_hoy'] ?></div>
                        <div class="text-xs text-teal-100">Hoy</div>
                    </div>
                    <?php if ($stats['pedidos_online_pendientes'] > 0): ?>
                    <div class="text-center bg-yellow-400 text-yellow-900 rounded-lg px-3 py-2">
                        <div class="text-2xl font-black"><?= $stats['pedidos_online_pendientes'] ?></div>
                        <div class="text-xs font-bold">Pendientes</div>
                    </div>
                    <?php endif; ?>
                    <a href="pedidos_online.php"
                       class="bg-white text-teal-700 hover:bg-teal-50 px-4 py-2 rounded-lg font-bold text-sm text-center transition-all">
                        <i class="fas fa-list mr-1"></i>Ver todos
                    </a>
                </div>
            </div>
        </div>

        <!-- Últimos Pedidos -->
        <div class="bg-white rounded-lg shadow">
            <div class="p-4 sm:p-6 border-b">
                <h2 class="text-lg sm:text-xl font-semibold text-gray-800">
                    <i class="fas fa-history mr-2"></i>Últimos Pedidos
                </h2>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-3 sm:px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">ID</th>
                            <th class="px-3 sm:px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Cliente</th>
                            <th class="px-3 sm:px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase hidden md:table-cell">Producto</th>
                            <th class="px-3 sm:px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Precio</th>
                            <th class="px-3 sm:px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Estado</th>
                            <th class="px-3 sm:px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase hidden lg:table-cell">Fecha</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        <?php if (empty($ultimos_pedidos)): ?>
                            <tr>
                                <td colspan="6" class="px-3 sm:px-6 py-4 text-center text-gray-500 text-sm">
                                    No hay pedidos registrados
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($ultimos_pedidos as $pedido): ?>
                                <tr class="hover:bg-gray-50">
                                    <td class="px-3 sm:px-6 py-3 sm:py-4 text-xs sm:text-sm font-medium text-gray-900">
                                        #<?= $pedido['id'] ?>
                                        <?php if (strpos($pedido['observaciones'] ?? '', 'PEDIDO ONLINE') !== false): ?>
                                            <span class="ml-1 px-1.5 py-0.5 bg-teal-100 text-teal-700 text-xs rounded-full font-bold hidden sm:inline">🌐</span>
                                        <?php endif; ?>
                                        <?php if ($pedido['ubicacion'] === 'Fábrica'): ?>
                                            <span class="ml-1 px-1.5 py-0.5 bg-purple-100 text-purple-700 text-xs rounded-full font-bold hidden sm:inline">🚚 Reparto</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-3 sm:px-6 py-3 sm:py-4 text-xs sm:text-sm text-gray-900">
                                        <?= htmlspecialchars($pedido['nombre'] . ' ' . $pedido['apellido']) ?>
                                    </td>
                                    <td class="px-3 sm:px-6 py-3 sm:py-4 text-xs sm:text-sm text-gray-900 hidden md:table-cell">
                                        <?= htmlspecialchars($pedido['producto']) ?>
                                    </td>
                                    <td class="px-3 sm:px-6 py-3 sm:py-4 text-xs sm:text-sm text-gray-900 font-semibold">
                                        <?= formatPrice($pedido['precio']) ?>
                                    </td>
                                    <td class="px-3 sm:px-6 py-3 sm:py-4">
                                        <?php
                                        $estado_color = [
                                            'Pendiente' => 'bg-yellow-100 text-yellow-800',
                                            'Preparando' => 'bg-blue-100 text-blue-800',
                                            'Listo' => 'bg-green-100 text-green-800',
                                            'Entregado' => 'bg-gray-100 text-gray-800'
                                        ];
                                        ?>
                                        <span class="px-2 py-1 text-xs font-medium rounded-full whitespace-nowrap <?= $estado_color[$pedido['estado']] ?? 'bg-gray-100 text-gray-800' ?>">
                                            <?= $pedido['estado'] ?>
                                        </span>
                                    </td>
                                    <td class="px-3 sm:px-6 py-3 sm:py-4 text-xs sm:text-sm text-gray-500 hidden lg:table-cell">
                                        <?= $pedido['fecha_display'] ?? formatDateTime($pedido['created_at'], 'd/m H:i') ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </main>

    <script>
    function sincronizarFechas() {
        const btn = document.getElementById('btnSincronizar');
        const originalHTML = btn.innerHTML;
        btn.disabled = true;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin mr-1"></i><span class="hidden lg:inline">Sync...</span>';

        fetch('../migrations/api_reparar_fechas.php')
            .then(response => response.json())
            .then(data => {
                btn.disabled = false;
                btn.innerHTML = originalHTML;
                if (data.success) {
                    location.reload();
                } else {
                    alert('Error al sincronizar fechas');
                }
            })
            .catch(() => {
                btn.disabled = false;
                btn.innerHTML = originalHTML;
                alert('Error de conexión');
            });
    }

    // ============================================
    // SONIDO DE NOTIFICACIÓN (con persistencia)
    // ============================================
    const SONIDO_NOTIFICACION_URL = '../sound/noti.mp3';
    const STORAGE_KEY_EMP = 'santacatalina_sonido_empleado';

    let audioNotifEmp = null;
    let sonidoHabilitadoEmp = localStorage.getItem(STORAGE_KEY_EMP) === 'true';

    function crearAudioEmp() {
        if (!audioNotifEmp) {
            audioNotifEmp = new Audio(SONIDO_NOTIFICACION_URL);
            audioNotifEmp.volume = 0.8;
        }
        return audioNotifEmp;
    }

    function actualizarBotonSonidoEmp(activo) {
        const btn = document.getElementById('btnSonidoEmp');
        if (!btn) return;
        if (activo) {
            btn.innerHTML = '<i class="fas fa-volume-up sm:mr-1"></i><span class="hidden lg:inline">Sonido ON</span>';
            btn.classList.remove('bg-red-500', 'hover:bg-red-600');
            btn.classList.add('bg-green-500', 'hover:bg-green-600');
        } else {
            btn.innerHTML = '<i class="fas fa-volume-mute sm:mr-1"></i><span class="hidden lg:inline">Sonido</span>';
            btn.classList.remove('bg-green-500', 'hover:bg-green-600');
            btn.classList.add('bg-red-500', 'hover:bg-red-600');
        }
    }

    function toggleSonidoEmp() {
        const audio = crearAudioEmp();
        if (sonidoHabilitadoEmp) {
            sonidoHabilitadoEmp = false;
            localStorage.setItem(STORAGE_KEY_EMP, 'false');
            actualizarBotonSonidoEmp(false);
        } else {
            audio.play().then(() => {
                sonidoHabilitadoEmp = true;
                localStorage.setItem(STORAGE_KEY_EMP, 'true');
                actualizarBotonSonidoEmp(true);
            }).catch(() => {
                sonidoHabilitadoEmp = true;
                localStorage.setItem(STORAGE_KEY_EMP, 'true');
                actualizarBotonSonidoEmp(true);
            });
        }
    }

    function reproducirSonidoEmp() {
        if (sonidoHabilitadoEmp) {
            const audio = crearAudioEmp();
            audio.currentTime = 0;
            audio.play().catch(() => {});
        }
    }

    function mostrarNotificacionVisualEmp(cantidad) {
        const notif = document.createElement('div');
        notif.className = 'fixed top-20 right-4 bg-green-500 text-white px-6 py-4 rounded-lg shadow-2xl z-50 animate-pulse';
        notif.innerHTML = `
            <div class="flex items-center">
                <i class="fas fa-bell text-2xl mr-3"></i>
                <div>
                    <div class="font-bold text-lg">${cantidad} nuevo(s) pedido(s)</div>
                    <div class="text-sm">Actualizando...</div>
                </div>
            </div>
        `;
        document.body.appendChild(notif);
        setTimeout(() => notif.remove(), 3000);
    }

    function checkearNuevosPedidosEmp() {
        fetch('check_nuevos_pedidos_sound.php')
            .then(response => response.json())
            .then(data => {
                if (data.success && data.hay_nuevos) {
                    reproducirSonidoEmp();
                    mostrarNotificacionVisualEmp(data.cantidad);
                    setTimeout(() => location.reload(), 2500);
                }
            })
            .catch(() => {});
    }

    setInterval(checkearNuevosPedidosEmp, 30000);
    setTimeout(checkearNuevosPedidosEmp, 10000);

    document.addEventListener('DOMContentLoaded', function() {
        const headerButtons = document.querySelector('header .container .flex.items-center');
        if (headerButtons) {
            const btnSonido = document.createElement('button');
            btnSonido.id = 'btnSonidoEmp';
            btnSonido.onclick = toggleSonidoEmp;
            btnSonido.className = 'bg-red-500 hover:bg-red-600 text-white px-2 sm:px-3 py-2 rounded text-xs sm:text-sm';
            btnSonido.title = 'Activar/desactivar notificaciones de sonido';
            btnSonido.innerHTML = '<i class="fas fa-volume-mute sm:mr-1"></i><span class="hidden lg:inline">Sonido</span>';
            headerButtons.insertBefore(btnSonido, headerButtons.firstChild);

            if (sonidoHabilitadoEmp) {
                actualizarBotonSonidoEmp(true);
                crearAudioEmp();
            }
        }
    });
    </script>
</body>
</html>
