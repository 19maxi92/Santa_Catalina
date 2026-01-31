<?php
/**
 * Panel de configuración de pedidos online
 * Administrar stock y límites por turno
 */
require_once '../../config.php';
requireLogin();

$pdo = getConnection();

// Procesar actualización
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion'])) {
    if ($_POST['accion'] === 'actualizar_turno') {
        $turno = $_POST['turno'];
        $max_pedidos = (int)$_POST['max_pedidos'];
        $stock_actual = (int)$_POST['stock_actual'];
        $activo = isset($_POST['activo']) ? 1 : 0;

        $stmt = $pdo->prepare("
            UPDATE config_pedidos_online
            SET max_pedidos = ?, stock_actual = ?, activo = ?
            WHERE turno = ?
        ");
        $stmt->execute([$max_pedidos, $stock_actual, $activo, $turno]);

        $mensaje_exito = "✅ Configuración de {$turno} actualizada";
    }

    if ($_POST['accion'] === 'resetear_stock') {
        $pdo->query("UPDATE config_pedidos_online SET stock_actual = max_pedidos");
        $mensaje_exito = "✅ Stock reseteado para todos los turnos";
    }
}

// Obtener configuración actual
$stmt = $pdo->query("
    SELECT * FROM config_pedidos_online
    ORDER BY FIELD(turno, 'Mañana', 'Siesta', 'Tarde')
");
$config_turnos = $stmt->fetchAll();

// Obtener estadísticas de pedidos online de hoy
$stmt = $pdo->query("
    SELECT COUNT(*) as total
    FROM pedidos
    WHERE DATE(created_at) = CURDATE()
    AND observaciones LIKE '%PEDIDO ONLINE%'
");
$stats = $stmt->fetch();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Configuración Pedidos Online - <?= APP_NAME ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="bg-gray-100">

    <!-- Header -->
    <header class="bg-white shadow-md">
        <div class="container mx-auto px-4 py-4 flex justify-between items-center">
            <div class="flex items-center">
                <a href="../../index.php" class="text-gray-600 hover:text-gray-800 mr-4">
                    <i class="fas fa-arrow-left text-xl"></i>
                </a>
                <h1 class="text-2xl font-bold text-gray-800">
                    <i class="fas fa-cog text-purple-600 mr-2"></i>
                    Configuración Pedidos Online
                </h1>
            </div>
            <div class="text-sm text-gray-600">
                <?= $_SESSION['admin_name'] ?? 'Admin' ?>
            </div>
        </div>
    </header>

    <main class="container mx-auto px-4 py-8 max-w-6xl">

        <?php if (isset($mensaje_exito)): ?>
            <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-6 rounded-lg">
                <i class="fas fa-check-circle mr-2"></i><?= $mensaje_exito ?>
            </div>
        <?php endif; ?>

        <!-- Estadísticas de hoy -->
        <div class="bg-gradient-to-r from-blue-500 to-purple-600 text-white rounded-xl shadow-lg p-6 mb-8">
            <div class="flex items-center justify-between">
                <div>
                    <div class="text-sm font-semibold opacity-90">Pedidos Online Hoy</div>
                    <div class="text-5xl font-black"><?= $stats['total'] ?></div>
                </div>
                <div class="text-6xl opacity-20">
                    <i class="fas fa-shopping-cart"></i>
                </div>
            </div>
        </div>

        <!-- Botón resetear stock -->
        <div class="mb-6 flex justify-end">
            <form method="POST" onsubmit="return confirm('¿Resetear el stock de todos los turnos al máximo configurado?')">
                <input type="hidden" name="accion" value="resetear_stock">
                <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white px-6 py-3 rounded-lg font-bold shadow-lg">
                    <i class="fas fa-sync-alt mr-2"></i>
                    Resetear Stock (Todos los turnos)
                </button>
            </form>
        </div>

        <!-- Configuración por turno -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
            <?php foreach ($config_turnos as $config): ?>
                <div class="bg-white rounded-xl shadow-lg overflow-hidden">
                    <div class="bg-gradient-to-r from-orange-500 to-red-500 text-white p-4">
                        <h3 class="text-2xl font-black"><?= $config['turno'] ?></h3>
                        <p class="text-sm opacity-90">
                            <?= substr($config['hora_inicio'], 0, 5) ?> - <?= substr($config['hora_fin'], 0, 5) ?>
                        </p>
                    </div>

                    <form method="POST" class="p-6 space-y-4">
                        <input type="hidden" name="accion" value="actualizar_turno">
                        <input type="hidden" name="turno" value="<?= $config['turno'] ?>">

                        <!-- Max Pedidos -->
                        <div>
                            <label class="block text-sm font-bold text-gray-700 mb-2">
                                <i class="fas fa-layer-group mr-1"></i>
                                Máximo de Pedidos
                            </label>
                            <input type="number" name="max_pedidos" value="<?= $config['max_pedidos'] ?>"
                                   min="0" max="200"
                                   class="w-full px-4 py-2 border-2 border-gray-300 rounded-lg focus:border-orange-500 focus:ring-2 focus:ring-orange-200 font-semibold text-lg">
                        </div>

                        <!-- Stock Actual -->
                        <div>
                            <label class="block text-sm font-bold text-gray-700 mb-2">
                                <i class="fas fa-box mr-1"></i>
                                Stock Disponible Ahora
                            </label>
                            <input type="number" name="stock_actual" value="<?= $config['stock_actual'] ?>"
                                   min="0" max="200"
                                   class="w-full px-4 py-2 border-2 border-gray-300 rounded-lg focus:border-orange-500 focus:ring-2 focus:ring-orange-200 font-semibold text-lg">
                            <p class="text-xs text-gray-500 mt-1">
                                Ocupados: <?= $config['max_pedidos'] - $config['stock_actual'] ?> /
                                <?= $config['max_pedidos'] ?>
                            </p>
                        </div>

                        <!-- Activo -->
                        <div>
                            <label class="flex items-center cursor-pointer">
                                <input type="checkbox" name="activo" value="1"
                                       <?= $config['activo'] ? 'checked' : '' ?>
                                       class="w-5 h-5 text-orange-600 mr-3">
                                <div>
                                    <span class="font-bold text-gray-800">Turno Activo</span>
                                    <p class="text-xs text-gray-600">Los clientes pueden hacer pedidos</p>
                                </div>
                            </label>
                        </div>

                        <!-- Botón -->
                        <button type="submit"
                                class="w-full bg-gradient-to-r from-orange-500 to-red-500 text-white py-3 rounded-lg font-bold hover:from-orange-600 hover:to-red-600 shadow-lg">
                            <i class="fas fa-save mr-2"></i>
                            Guardar
                        </button>
                    </form>
                </div>
            <?php endforeach; ?>
        </div>

        <!-- Información importante -->
        <div class="mt-8 bg-blue-50 border-l-4 border-blue-500 p-6 rounded-lg">
            <h3 class="text-lg font-bold text-blue-900 mb-3">
                <i class="fas fa-info-circle mr-2"></i>
                Información
            </h3>
            <ul class="space-y-2 text-sm text-blue-800">
                <li><i class="fas fa-check mr-2 text-blue-600"></i>
                    <strong>Máximo de Pedidos:</strong> Cantidad total que puede haber por turno
                </li>
                <li><i class="fas fa-check mr-2 text-blue-600"></i>
                    <strong>Stock Disponible:</strong> Cupos que quedan libres (se descuenta automáticamente al confirmar pedido)
                </li>
                <li><i class="fas fa-check mr-2 text-blue-600"></i>
                    <strong>Resetear Stock:</strong> Vuelve el stock disponible al máximo configurado (útil al inicio del día)
                </li>
                <li><i class="fas fa-check mr-2 text-blue-600"></i>
                    <strong>Link para clientes:</strong> <code class="bg-white px-2 py-1 rounded text-blue-700"><?= $_SERVER['HTTP_HOST'] ?>/pedido_online/</code>
                </li>
            </ul>
        </div>

    </main>

</body>
</html>
