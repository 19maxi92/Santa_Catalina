<?php
require_once '../../config.php';
requireLogin();

$pdo = getConnection();

// Obtener historial de cambios
$filtro_tipo = isset($_GET['tipo']) ? sanitize($_GET['tipo']) : '';
$filtro_fecha_desde = isset($_GET['fecha_desde']) ? sanitize($_GET['fecha_desde']) : '';
$filtro_fecha_hasta = isset($_GET['fecha_hasta']) ? sanitize($_GET['fecha_hasta']) : '';

$sql = "SELECT h.*, p.nombre as producto_nombre, pr.nombre as promo_nombre
        FROM historial_precios h
        LEFT JOIN productos p ON h.producto_id = p.id
        LEFT JOIN promos pr ON h.promo_id = pr.id
        WHERE 1=1";
$params = [];

if ($filtro_tipo) {
    $sql .= " AND h.tipo = ?";
    $params[] = $filtro_tipo;
}

if ($filtro_fecha_desde) {
    $sql .= " AND DATE(h.fecha_cambio) >= ?";
    $params[] = $filtro_fecha_desde;
}

if ($filtro_fecha_hasta) {
    $sql .= " AND DATE(h.fecha_cambio) <= ?";
    $params[] = $filtro_fecha_hasta;
}

$sql .= " ORDER BY h.fecha_cambio DESC LIMIT 100";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$historial = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Historial de Cambios - <?= APP_NAME ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="bg-gray-100">
    <!-- Header -->
    <header class="bg-white shadow-md">
        <div class="container mx-auto px-4 py-3 flex justify-between items-center">
            <div class="flex items-center">
                <a href="index.php" class="text-gray-600 hover:text-gray-800 mr-4">
                    <i class="fas fa-arrow-left"></i>
                </a>
                <h1 class="text-xl font-bold text-gray-800">
                    <i class="fas fa-history text-blue-500 mr-2"></i>Historial de Cambios
                </h1>
            </div>
            <a href="../../logout.php" class="bg-red-500 hover:bg-red-600 text-white px-3 py-2 rounded">
                <i class="fas fa-sign-out-alt mr-1"></i>Salir
            </a>
        </div>
    </header>

    <main class="container mx-auto px-4 py-6">
        
        <!-- Filtros -->
        <div class="bg-white rounded-lg shadow mb-6 p-4">
            <form method="GET" class="grid grid-cols-1 md:grid-cols-4 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Tipo:</label>
                    <select name="tipo" class="w-full px-3 py-2 border rounded-lg">
                        <option value="">Todos</option>
                        <option value="producto" <?= $filtro_tipo === 'producto' ? 'selected' : '' ?>>Productos</option>
                        <option value="promo" <?= $filtro_tipo === 'promo' ? 'selected' : '' ?>>Promos</option>
                        <option value="ajuste_masivo" <?= $filtro_tipo === 'ajuste_masivo' ? 'selected' : '' ?>>Ajuste Masivo</option>
                    </select>
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Desde:</label>
                    <input type="date" name="fecha_desde" value="<?= htmlspecialchars($filtro_fecha_desde) ?>" 
                           class="w-full px-3 py-2 border rounded-lg">
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Hasta:</label>
                    <input type="date" name="fecha_hasta" value="<?= htmlspecialchars($filtro_fecha_hasta) ?>" 
                           class="w-full px-3 py-2 border rounded-lg">
                </div>
                
                <div class="flex items-end">
                    <button type="submit" class="w-full bg-blue-500 hover:bg-blue-600 text-white py-2 px-4 rounded-lg">
                        <i class="fas fa-search mr-1"></i>Filtrar
                    </button>
                </div>
            </form>
        </div>

        <!-- Lista de cambios -->
        <div class="bg-white rounded-lg shadow">
            <?php if (empty($historial)): ?>
                <div class="p-8 text-center text-gray-500">
                    <i class="fas fa-history text-6xl mb-4 text-gray-300"></i>
                    <h3 class="text-xl mb-2">No hay cambios registrados</h3>
                    <p>Los cambios de precios aparecerán aquí</p>
                </div>
            <?php else: ?>
                <div class="overflow-x-auto">
                    <table class="min-w-full">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Fecha</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Tipo</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Item</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Cambio</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Usuario</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Motivo</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200">
                            <?php foreach ($historial as $cambio): ?>
                                <tr class="hover:bg-gray-50">
                                    <td class="px-6 py-4 text-sm text-gray-900">
                                        <?= date('d/m/Y H:i', strtotime($cambio['fecha_cambio'])) ?>
                                    </td>
                                    <td class="px-6 py-4">
                                        <?php
                                        $tipo_colors = [
                                            'producto' => 'bg-blue-100 text-blue-800',
                                            'promo' => 'bg-purple-100 text-purple-800',
                                            'ajuste_masivo' => 'bg-orange-100 text-orange-800'
                                        ];
                                        ?>
                                        <span class="px-2 py-1 text-xs font-medium rounded-full <?= $tipo_colors[$cambio['tipo']] ?? 'bg-gray-100 text-gray-800' ?>">
                                            <?= ucfirst($cambio['tipo']) ?>
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 text-sm text-gray-900">
                                        <?= htmlspecialchars($cambio['producto_nombre'] ?: $cambio['promo_nombre'] ?: 'N/A') ?>
                                    </td>
                                    <td class="px-6 py-4 text-sm">
                                        <?php if ($cambio['precio_anterior_efectivo']): ?>
                                            <div class="space-y-1">
                                                <div>
                                                    <span class="text-gray-500">Efectivo:</span>
                                                    <span class="line-through text-red-500"><?= formatPrice($cambio['precio_anterior_efectivo']) ?></span>
                                                    <span class="text-green-600">→ <?= formatPrice($cambio['precio_nuevo_efectivo']) ?></span>
                                                </div>
                                                <div>
                                                    <span class="text-gray-500">Transfer:</span>
                                                    <span class="line-through text-red-500"><?= formatPrice($cambio['precio_anterior_transferencia']) ?></span>
                                                    <span class="text-green-600">→ <?= formatPrice($cambio['precio_nuevo_transferencia']) ?></span>
                                                </div>
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-6 py-4 text-sm text-gray-900">
                                        <?= htmlspecialchars($cambio['usuario'] ?: 'Sistema') ?>
                                    </td>
                                    <td class="px-6 py-4 text-sm text-gray-500">
                                        <?= htmlspecialchars($cambio['motivo'] ?: 'Sin motivo especificado') ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>

        <!-- Footer -->
        <div class="mt-6 text-center text-gray-500">
            <p>Mostrando últimos 100 cambios | <a href="index.php" class="text-blue-600 hover:underline">Volver a productos</a></p>
        </div>
    </main>
</body>
</html>