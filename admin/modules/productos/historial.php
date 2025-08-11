<?php
// admin/modules/productos/historial.php - Versión corregida COMPLETAMENTE

// Error handling mejorado
ini_set('display_errors', 1);
error_reporting(E_ALL);

// Verificar que el archivo existe antes de incluir
$config_path = '../../config.php';
if (!file_exists($config_path)) {
    die("Error: No se puede encontrar config.php en la ruta: $config_path");
}

try {
    require_once $config_path;
} catch (Exception $e) {
    die("Error cargando configuración: " . $e->getMessage());
}

// Verificar que las funciones necesarias existen
if (!function_exists('getConnection')) {
    die("Error: Función getConnection no está disponible");
}

if (!function_exists('requireLogin')) {
    die("Error: Función requireLogin no está disponible");
}

// Iniciar sesión y verificar login
try {
    requireLogin();
} catch (Exception $e) {
    die("Error en verificación de login: " . $e->getMessage());
}

// Obtener conexión a BD
try {
    $pdo = getConnection();
} catch (Exception $e) {
    die("Error de conexión a base de datos: " . $e->getMessage());
}

// ARREGLAR TABLA HISTORIAL_PRECIOS - Agregar campos faltantes
try {
    // Verificar si existen las columnas necesarias
    $columnas = $pdo->query("SHOW COLUMNS FROM historial_precios")->fetchAll(PDO::FETCH_COLUMN);
    
    $columnas_requeridas = [
        'tipo' => "varchar(50) NOT NULL DEFAULT 'producto'",
        'promo_id' => "int(11) DEFAULT NULL"
    ];
    
    foreach ($columnas_requeridas as $columna => $definicion) {
        if (!in_array($columna, $columnas)) {
            $pdo->exec("ALTER TABLE historial_precios ADD COLUMN $columna $definicion");
        }
    }
} catch (Exception $e) {
    // Si falla, continuar pero mostrar warning
    $error_estructura = "Advertencia: No se pudieron verificar/crear las columnas necesarias";
}

// Obtener historial de cambios con manejo de errores MEJORADO
try {
    $filtro_tipo = isset($_GET['tipo']) ? sanitize($_GET['tipo']) : '';
    $filtro_fecha_desde = isset($_GET['fecha_desde']) ? sanitize($_GET['fecha_desde']) : '';
    $filtro_fecha_hasta = isset($_GET['fecha_hasta']) ? sanitize($_GET['fecha_hasta']) : '';
    $filtro_producto_id = isset($_GET['producto_id']) ? (int)$_GET['producto_id'] : 0;

    $sql = "SELECT h.*, p.nombre as producto_nombre
            FROM historial_precios h
            LEFT JOIN productos p ON h.producto_id = p.id
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

    if ($filtro_producto_id > 0) {
        $sql .= " AND h.producto_id = ?";
        $params[] = $filtro_producto_id;
    }

    $sql .= " ORDER BY h.fecha_cambio DESC LIMIT 100";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $historial = $stmt->fetchAll();

} catch (Exception $e) {
    $historial = [];
    $error = 'Error obteniendo historial: ' . $e->getMessage();
}

// Obtener productos para filtro
try {
    $productos_filtro = $pdo->query("SELECT id, nombre FROM productos ORDER BY nombre")->fetchAll();
} catch (Exception $e) {
    $productos_filtro = [];
}
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
        
        <!-- Mensaje de error si existe -->
        <?php if (isset($error)): ?>
        <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
            <i class="fas fa-exclamation-circle mr-2"></i><?= htmlspecialchars($error) ?>
        </div>
        <?php endif; ?>

        <?php if (isset($error_estructura)): ?>
        <div class="bg-yellow-100 border border-yellow-400 text-yellow-700 px-4 py-3 rounded mb-4">
            <i class="fas fa-exclamation-triangle mr-2"></i><?= htmlspecialchars($error_estructura) ?>
        </div>
        <?php endif; ?>
        
        <!-- Filtros -->
        <div class="bg-white rounded-lg shadow mb-6 p-4">
            <form method="GET" class="grid grid-cols-1 md:grid-cols-5 gap-4">
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
                    <label class="block text-sm font-medium text-gray-700 mb-1">Producto:</label>
                    <select name="producto_id" class="w-full px-3 py-2 border rounded-lg">
                        <option value="">Todos los productos</option>
                        <?php foreach ($productos_filtro as $prod): ?>
                            <option value="<?= $prod['id'] ?>" <?= $filtro_producto_id == $prod['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($prod['nombre']) ?>
                            </option>
                        <?php endforeach; ?>
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
            
            <div class="mt-4 text-center">
                <a href="?" class="text-gray-500 hover:text-gray-700 text-sm">
                    <i class="fas fa-eraser mr-1"></i>Limpiar filtros
                </a>
            </div>
        </div>

        <!-- Lista de cambios -->
        <div class="bg-white rounded-lg shadow">
            <?php if (empty($historial)): ?>
                <div class="p-8 text-center text-gray-500">
                    <i class="fas fa-history text-6xl mb-4 text-gray-300"></i>
                    <h3 class="text-xl mb-2">No hay cambios registrados</h3>
                    <p>Los cambios de precios aparecerán aquí cuando se realicen modificaciones</p>
                    <?php if (!empty($productos_filtro)): ?>
                        <div class="mt-4">
                            <a href="index.php" class="text-blue-600 hover:underline">
                                Ir a gestión de productos
                            </a>
                        </div>
                    <?php endif; ?>
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
                                        <div>
                                            <?= date('d/m/Y H:i', strtotime($cambio['fecha_cambio'])) ?>
                                        </div>
                                        <div class="text-xs text-gray-500">
                                            Hace <?= timeAgo($cambio['fecha_cambio']) ?>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4">
                                        <?php
                                        $tipo = $cambio['tipo'] ?? 'producto';
                                        $tipo_colors = [
                                            'producto' => 'bg-blue-100 text-blue-800',
                                            'promo' => 'bg-purple-100 text-purple-800',
                                            'ajuste_masivo' => 'bg-orange-100 text-orange-800'
                                        ];
                                        $tipo_icons = [
                                            'producto' => 'fas fa-box',
                                            'promo' => 'fas fa-tags',
                                            'ajuste_masivo' => 'fas fa-calculator'
                                        ];
                                        ?>
                                        <span class="px-2 py-1 text-xs font-medium rounded-full <?= $tipo_colors[$tipo] ?? 'bg-gray-100 text-gray-800' ?>">
                                            <i class="<?= $tipo_icons[$tipo] ?? 'fas fa-edit' ?> mr-1"></i>
                                            <?= ucfirst($tipo) ?>
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 text-sm text-gray-900">
                                        <div class="font-medium">
                                            <?= htmlspecialchars($cambio['producto_nombre'] ?: 'Item eliminado') ?>
                                        </div>
                                        <?php if ($cambio['producto_id']): ?>
                                            <div class="text-xs text-gray-500">
                                                ID: <?= $cambio['producto_id'] ?>
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-6 py-4 text-sm">
                                        <?php if ($cambio['precio_anterior_efectivo'] && $cambio['precio_nuevo_efectivo']): ?>
                                            <div class="space-y-2">
                                                <div class="flex items-center space-x-2">
                                                    <span class="text-xs text-gray-500 w-16">Efectivo:</span>
                                                    <span class="line-through text-red-500 text-sm">
                                                        <?= formatPrice($cambio['precio_anterior_efectivo']) ?>
                                                    </span>
                                                    <i class="fas fa-arrow-right text-gray-400 text-xs"></i>
                                                    <span class="text-green-600 font-medium">
                                                        <?= formatPrice($cambio['precio_nuevo_efectivo']) ?>
                                                    </span>
                                                    <?php 
                                                    $diff_efectivo = $cambio['precio_nuevo_efectivo'] - $cambio['precio_anterior_efectivo'];
                                                    $color_diff = $diff_efectivo > 0 ? 'text-red-600' : 'text-green-600';
                                                    ?>
                                                    <span class="text-xs <?= $color_diff ?>">
                                                        (<?= $diff_efectivo > 0 ? '+' : '' ?><?= formatPrice($diff_efectivo) ?>)
                                                    </span>
                                                </div>
                                                <div class="flex items-center space-x-2">
                                                    <span class="text-xs text-gray-500 w-16">Transfer:</span>
                                                    <span class="line-through text-red-500 text-sm">
                                                        <?= formatPrice($cambio['precio_anterior_transferencia']) ?>
                                                    </span>
                                                    <i class="fas fa-arrow-right text-gray-400 text-xs"></i>
                                                    <span class="text-blue-600 font-medium">
                                                        <?= formatPrice($cambio['precio_nuevo_transferencia']) ?>
                                                    </span>
                                                    <?php 
                                                    $diff_transfer = $cambio['precio_nuevo_transferencia'] - $cambio['precio_anterior_transferencia'];
                                                    $color_diff = $diff_transfer > 0 ? 'text-red-600' : 'text-green-600';
                                                    ?>
                                                    <span class="text-xs <?= $color_diff ?>">
                                                        (<?= $diff_transfer > 0 ? '+' : '' ?><?= formatPrice($diff_transfer) ?>)
                                                    </span>
                                                </div>
                                            </div>
                                        <?php else: ?>
                                            <span class="text-gray-400 text-sm">Sin información de precios</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-6 py-4 text-sm text-gray-900">
                                        <div class="font-medium">
                                            <?= htmlspecialchars($cambio['usuario'] ?: 'Sistema') ?>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 text-sm text-gray-500">
                                        <div class="max-w-xs">
                                            <?= htmlspecialchars($cambio['motivo'] ?: 'Sin motivo especificado') ?>
                                        </div>
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
            <div class="space-y-2">
                <p>
                    Mostrando <?= count($historial) ?> cambio<?= count($historial) !== 1 ? 's' : '' ?>
                    <?php if (count($historial) >= 100): ?>
                        <span class="text-orange-600">(limitado a últimos 100 registros)</span>
                    <?php endif; ?>
                </p>
                
                <?php if ($filtro_tipo || $filtro_fecha_desde || $filtro_fecha_hasta || $filtro_producto_id): ?>
                    <p>
                        <a href="?" class="text-blue-600 hover:underline">Limpiar filtros</a>
                        para ver todos los cambios
                    </p>
                <?php endif; ?>
                
                <p class="text-xs">
                    <a href="index.php" class="text-blue-600 hover:underline">Volver a productos</a>
                    | 
                    <a href="../../" class="text-blue-600 hover:underline">Dashboard principal</a>
                </p>
            </div>
        </div>
    </main>

    <!-- Información adicional -->
    <div class="container mx-auto px-4 pb-6">
        <div class="bg-blue-50 border border-blue-200 rounded-lg p-4">
            <h3 class="font-semibold text-blue-800 mb-2">
                <i class="fas fa-info-circle mr-2"></i>Información del Historial
            </h3>
            <div class="text-sm text-blue-700 space-y-1">
                <p>• <strong>Productos:</strong> Cambios individuales de precios en productos específicos</p>
                <p>• <strong>Promos:</strong> Modificaciones en promociones y ofertas especiales</p>
                <p>• <strong>Ajuste Masivo:</strong> Cambios aplicados a múltiples productos simultáneamente</p>
                <p>• Se conservan los últimos 100 registros más recientes para auditoría</p>
                <p>• Los cambios se registran automáticamente con fecha, usuario y motivo</p>
            </div>
        </div>
    </div>
</body>
</html>

<?php
// Función auxiliar para mostrar tiempo transcurrido
function timeAgo($datetime) {
    $time = time() - strtotime($datetime);
    
    if ($time < 60) return 'menos de 1 minuto';
    if ($time < 3600) return floor($time/60) . ' minuto' . (floor($time/60) != 1 ? 's' : '');
    if ($time < 86400) return floor($time/3600) . ' hora' . (floor($time/3600) != 1 ? 's' : '');
    if ($time < 2592000) return floor($time/86400) . ' día' . (floor($time/86400) != 1 ? 's' : '');
    if ($time < 31536000) return floor($time/2592000) . ' mes' . (floor($time/2592000) != 1 ? 'es' : '');
    return floor($time/31536000) . ' año' . (floor($time/31536000) != 1 ? 's' : '');
}
?>