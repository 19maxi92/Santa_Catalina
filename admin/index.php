<?php
require_once 'config.php';
requireLogin();

// Obtener estadísticas básicas
$pdo = getConnection();

$stats = [
    'pedidos_hoy' => $pdo->query("SELECT COUNT(*) FROM pedidos WHERE DATE(created_at) = CURDATE()")->fetchColumn(),
    'pedidos_pendientes' => $pdo->query("SELECT COUNT(*) FROM pedidos WHERE estado = 'Pendiente'")->fetchColumn(),
    'clientes_fijos' => $pdo->query("SELECT COUNT(*) FROM clientes_fijos WHERE activo = 1")->fetchColumn(),
    'ventas_hoy' => $pdo->query("SELECT COALESCE(SUM(precio), 0) FROM pedidos WHERE DATE(created_at) = CURDATE()")->fetchColumn(),
    // ESTADÍSTICAS PARA PRODUCTOS
    'productos_activos' => $pdo->query("SELECT COUNT(*) FROM productos WHERE activo = 1")->fetchColumn(),
    'promos_activas' => 0 // Default por si no existe la tabla aún
];

// Verificar si existe tabla promos (para compatibilidad)
try {
    $stats['promos_activas'] = $pdo->query("
        SELECT COUNT(*) FROM promos
        WHERE activa = 1
        AND (fecha_inicio IS NULL OR CURDATE() >= fecha_inicio)
        AND (fecha_fin IS NULL OR CURDATE() <= fecha_fin)
    ")->fetchColumn();
} catch (Exception $e) {
    // Tabla promos no existe aún
    $stats['promos_activas'] = 0;
}

// Últimos pedidos
$ultimos_pedidos = $pdo->query("
    SELECT id, nombre, apellido, producto, precio, estado,
           fecha_display, created_at
    FROM pedidos
    ORDER BY created_at DESC
    LIMIT 5
")->fetchAll();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= APP_NAME ?> - Dashboard</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <!-- Day.js para formateo de fechas con timezone -->
    <script src="https://cdn.jsdelivr.net/npm/dayjs@1/dayjs.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/dayjs@1/plugin/utc.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/dayjs@1/plugin/timezone.js"></script>
</head>
<body class="bg-gray-100">
    <!-- Header Sticky Responsive -->
    <header class="bg-white shadow-md sticky top-0 z-50">
        <div class="container mx-auto px-3 sm:px-4 py-3 flex justify-between items-center">
            <h1 class="text-lg sm:text-xl font-bold text-gray-800">
                <i class="fas fa-utensils text-orange-500 mr-1 sm:mr-2"></i>
                <span class="hidden sm:inline"><?= APP_NAME ?></span>
                <span class="sm:hidden">SC</span>
            </h1>
            <div class="flex items-center space-x-1 sm:space-x-2">
                <a href="modules/dashboard/dashboard.php" class="bg-blue-500 hover:bg-blue-600 text-white px-2 sm:px-3 py-2 rounded text-xs sm:text-sm" title="Dashboard de Ventas">
                    <i class="fas fa-chart-line sm:mr-1"></i><span class="hidden lg:inline">Dashboard</span>
                </a>
                <a href="modules/empleados/index.php" class="bg-purple-500 hover:bg-purple-600 text-white px-2 sm:px-3 py-2 rounded text-xs sm:text-sm" title="Gestión de Empleados">
                    <i class="fas fa-users sm:mr-1"></i><span class="hidden lg:inline">Empleados</span>
                </a>
                <button onclick="sincronizarFechas()" id="btnSincronizar" class="bg-green-500 hover:bg-green-600 text-white px-2 sm:px-3 py-2 rounded text-xs sm:text-sm" title="Sincronizar fechas de pedidos">
                    <i class="fas fa-sync-alt sm:mr-1"></i><span class="hidden lg:inline">Sync</span>
                </button>
                <span class="text-sm text-gray-600 hidden xl:inline">Hola, <?= $_SESSION['admin_name'] ?></span>
                <a href="logout.php" class="bg-red-500 hover:bg-red-600 text-white px-2 sm:px-3 py-2 rounded text-xs sm:text-sm">
                    <i class="fas fa-sign-out-alt sm:mr-1"></i><span class="hidden sm:inline">Salir</span>
                </a>
            </div>
        </div>
    </header>

    <!-- Main Content -->
    <main class="container mx-auto px-3 sm:px-4 py-4 sm:py-6">

        <!-- Stats Cards Responsive -->
        <div class="grid grid-cols-2 lg:grid-cols-4 gap-3 sm:gap-4 md:gap-6 mb-6 sm:mb-8">
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

            <div class="bg-green-500 text-white p-4 sm:p-6 rounded-lg shadow hover:shadow-lg transition-shadow">
                <div class="flex items-center">
                    <i class="fas fa-users text-xl sm:text-2xl mr-2 sm:mr-3"></i>
                    <div>
                        <p class="text-xs sm:text-sm opacity-80">Clientes Fijos</p>
                        <p class="text-xl sm:text-2xl font-bold"><?= $stats['clientes_fijos'] ?></p>
                    </div>
                </div>
            </div>

            <div class="bg-purple-500 text-white p-4 sm:p-6 rounded-lg shadow hover:shadow-lg transition-shadow">
                <div class="flex items-center">
                    <i class="fas fa-dollar-sign text-xl sm:text-2xl mr-2 sm:mr-3"></i>
                    <div>
                        <p class="text-xs sm:text-sm opacity-80">Ventas Hoy</p>
                        <p class="text-xl sm:text-2xl font-bold"><?= formatPrice($stats['ventas_hoy']) ?></p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Botón Destacado: Nuevo Pedido -->
        <div class="mb-4 sm:mb-6">
            <a href="modules/pedidos/crear_pedido.php"
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

        <!-- Quick Actions Responsive -->
        <div class="grid grid-cols-2 md:grid-cols-3 gap-3 sm:gap-4 md:gap-6 mb-6 sm:mb-8">
            <a href="modules/clientes/lista_clientes.php" class="bg-white p-4 sm:p-6 rounded-lg shadow hover:shadow-lg transition-all block text-center">
                <i class="fas fa-address-book text-2xl sm:text-3xl text-green-500 mb-2 sm:mb-3"></i>
                <h3 class="text-sm sm:text-base md:text-lg font-semibold text-gray-800">Clientes Fijos</h3>
                <p class="text-xs sm:text-sm text-gray-600 hidden sm:block">Gestionar clientes</p>
            </a>

            <a href="modules/productos/index.php" class="bg-white p-4 sm:p-6 rounded-lg shadow hover:shadow-lg transition-all block text-center group">
                <i class="fas fa-boxes text-2xl sm:text-3xl text-purple-500 mb-2 sm:mb-3 group-hover:scale-110 transition-transform"></i>
                <h3 class="text-sm sm:text-base md:text-lg font-semibold text-gray-800">Productos</h3>
                <p class="text-xs sm:text-sm text-gray-600 hidden sm:block">Precios y promos</p>
                <div class="mt-1 sm:mt-2 text-xs text-purple-600">
                    <?= $stats['productos_activos'] ?> productos
                    <?php if ($stats['promos_activas'] > 0): ?>
                        <span class="hidden sm:inline">|</span> <?= $stats['promos_activas'] ?> <span class="hidden sm:inline">promos</span>
                    <?php endif; ?>
                </div>
            </a>

            <a href="modules/pedidos/ver_pedidos.php" class="bg-white p-4 sm:p-6 rounded-lg shadow hover:shadow-lg transition-all block text-center">
                <i class="fas fa-list text-2xl sm:text-3xl text-orange-500 mb-2 sm:mb-3"></i>
                <h3 class="text-sm sm:text-base md:text-lg font-semibold text-gray-800">Ver Pedidos</h3>
                <p class="text-xs sm:text-sm text-gray-600 hidden sm:block">Listado completo</p>
            </a>

            <a href="modules/pedidos_online/ver_pedidos.php" class="bg-white p-4 sm:p-6 rounded-lg shadow hover:shadow-lg transition-all block text-center group col-span-2 md:col-span-1">
                <i class="fas fa-globe text-2xl sm:text-3xl text-green-500 mb-2 sm:mb-3 group-hover:scale-110 transition-transform"></i>
                <h3 class="text-sm sm:text-base md:text-lg font-semibold text-gray-800">Pedidos Online</h3>
                <p class="text-xs sm:text-sm text-gray-600 hidden sm:block">Gestión web</p>
            </a>
        </div>

        <!-- Sección de Gestión de Productos Responsive -->
        <div class="bg-white rounded-lg shadow mb-6 sm:mb-8 p-4 sm:p-6">
            <h2 class="text-lg sm:text-xl font-semibold text-gray-800 mb-3 sm:mb-4">
                <i class="fas fa-boxes text-purple-500 mr-2"></i>Gestión de Productos
            </h2>
            <div class="grid grid-cols-2 lg:grid-cols-4 gap-3 sm:gap-4">
                <a href="modules/productos/crear_producto.php" class="bg-purple-50 hover:bg-purple-100 p-3 sm:p-4 rounded-lg text-center transition-all">
                    <i class="fas fa-plus text-purple-500 text-lg sm:text-xl mb-1 sm:mb-2 block"></i>
                    <div class="text-xs sm:text-sm font-medium text-purple-700">Nuevo Producto</div>
                </a>

                <a href="modules/productos/promos.php" class="bg-orange-50 hover:bg-orange-100 p-3 sm:p-4 rounded-lg text-center transition-all">
                    <i class="fas fa-tags text-orange-500 text-lg sm:text-xl mb-1 sm:mb-2 block"></i>
                    <div class="text-xs sm:text-sm font-medium text-orange-700">Gestionar Promos</div>
                    <?php if ($stats['promos_activas'] > 0): ?>
                        <div class="text-xs text-orange-600 mt-1"><?= $stats['promos_activas'] ?> activas</div>
                    <?php endif; ?>
                </a>

                <a href="modules/productos/index.php?estado=1" class="bg-green-50 hover:bg-green-100 p-3 sm:p-4 rounded-lg text-center transition-all">
                    <i class="fas fa-check-circle text-green-500 text-lg sm:text-xl mb-1 sm:mb-2 block"></i>
                    <div class="text-xs sm:text-sm font-medium text-green-700">Productos Activos</div>
                    <div class="text-xs text-green-600 mt-1"><?= $stats['productos_activos'] ?> productos</div>
                </a>

                <a href="modules/productos/historial.php" class="bg-blue-50 hover:bg-blue-100 p-3 sm:p-4 rounded-lg text-center transition-all">
                    <i class="fas fa-history text-blue-500 text-lg sm:text-xl mb-1 sm:mb-2 block"></i>
                    <div class="text-xs sm:text-sm font-medium text-blue-700">Historial Precios</div>
                </a>
            </div>
        </div>

        <!-- Tabla Últimos Pedidos Responsive -->
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

        <!-- Footer Responsive -->
        <div class="mt-6 sm:mt-8 bg-gradient-to-r from-blue-50 to-purple-50 border border-blue-200 rounded-lg p-4 sm:p-6">
            <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-3 sm:gap-4">
                <div>
                    <h3 class="font-bold text-gray-800 text-sm sm:text-base">¡Sistema actualizado!</h3>
                    <p class="text-xs sm:text-sm text-gray-600">Ahora podés gestionar productos, precios y promos desde el panel.</p>
                </div>
                <a href="modules/productos/index.php" class="bg-purple-500 hover:bg-purple-600 text-white px-4 py-2 rounded-lg text-xs sm:text-sm whitespace-nowrap">
                    <i class="fas fa-rocket mr-1"></i>Explorar Productos
                </a>
            </div>
        </div>
    </main>

    <script>
    // Función para sincronizar fechas (manual - con feedback visual)
    function sincronizarFechas() {
        const btn = document.getElementById('btnSincronizar');
        const originalHTML = btn.innerHTML;

        // Mostrar loading
        btn.disabled = true;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin mr-1"></i><span class="hidden lg:inline">Sync...</span>';

        fetch('../migrations/api_reparar_fechas.php')
            .then(response => response.json())
            .then(data => {
                btn.disabled = false;
                btn.innerHTML = originalHTML;

                if (data.success) {
                    // Recargar página silenciosamente
                    location.reload();
                } else {
                    console.error('Error al sincronizar:', data.error);
                    alert('Error al sincronizar fechas');
                }
            })
            .catch(error => {
                btn.disabled = false;
                btn.innerHTML = originalHTML;
                console.error('Error:', error);
                alert('Error de conexión');
            });
    }

    // Función para sincronizar fechas automáticamente (silenciosa, sin recargar página)
    function sincronizarFechasAutomatico() {
        fetch('../migrations/api_reparar_fechas.php')
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    console.log('✅ Sincronización automática de fechas completada');
                } else {
                    console.error('⚠️ Error en sincronización automática:', data.error);
                }
            })
            .catch(error => {
                console.error('❌ Error de red en sincronización automática:', error);
            });
    }

    // Ejecutar sincronización automática cada 3 minutos (180,000 ms)
    setInterval(sincronizarFechasAutomatico, 180000);

    // Ejecutar una vez al cargar la página
    setTimeout(sincronizarFechasAutomatico, 5000); // Esperar 5 segundos después de cargar

    console.log('🔄 Sincronización automática de fechas activada (cada 3 minutos)');

    // ============================================
    // SISTEMA DE NOTIFICACIÓN DE SONIDO
    // ============================================
    const audioNotificacion = new Audio('sound/noti.mp3');

    function checkearNuevosPedidos() {
        fetch('modules/pedidos/check_nuevos_pedidos_sound.php')
            .then(response => response.json())
            .then(data => {
                if (data.success && data.hay_nuevos) {
                    // Reproducir sonido
                    audioNotificacion.play().catch(err => {
                        console.log('No se pudo reproducir el sonido (requiere interacción del usuario):', err);
                    });

                    // Mostrar notificación visual
                    console.log(`🔔 ${data.cantidad} nuevo(s) pedido(s) para Local 1`);

                    // Opcional: Mostrar una notificación visual temporal
                    const notif = document.createElement('div');
                    notif.style.cssText = 'position:fixed;top:80px;right:20px;background:#10b981;color:white;padding:16px 24px;border-radius:8px;box-shadow:0 4px 12px rgba(0,0,0,0.3);z-index:9999;font-weight:bold;';
                    notif.innerHTML = `🔔 ${data.cantidad} nuevo(s) pedido(s) para Local 1`;
                    document.body.appendChild(notif);

                    setTimeout(() => {
                        notif.style.transition = 'opacity 0.5s';
                        notif.style.opacity = '0';
                        setTimeout(() => notif.remove(), 500);
                    }, 5000);
                }
            })
            .catch(err => {
                console.error('Error checkeando nuevos pedidos:', err);
            });
    }

    // Chequear cada 30 segundos
    setInterval(checkearNuevosPedidos, 30000);

    // Primera verificación después de 10 segundos
    setTimeout(checkearNuevosPedidos, 10000);

    </script>
</body>
</html>
