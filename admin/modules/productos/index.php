<?php
/**
 * Módulo de Productos Mejorado y Amigable
 * admin/modules/productos/index.php
 * Gestión completa de productos con búsqueda, filtros y sincronización de precios
 */

require_once '../../config.php';
requireLogin();

$pdo = getConnection();

$mensaje = '';
$error = '';

// Procesar actualización masiva con sincronización
if ($_POST && isset($_POST['actualizar_masivo'])) {
    try {
        $pdo->beginTransaction();
        $productos_actualizados = 0;

        foreach ($_POST['productos'] as $id => $datos) {
            $precio_efectivo = (float)$datos['precio_efectivo'];
            $precio_transferencia = (float)$datos['precio_transferencia'];

            if ($precio_efectivo > 0 && $precio_transferencia > 0) {
                // Obtener precio anterior para historial
                $stmt = $pdo->prepare("SELECT nombre, precio_efectivo, precio_transferencia FROM productos WHERE id = ?");
                $stmt->execute([$id]);
                $producto_anterior = $stmt->fetch();

                if ($producto_anterior) {
                    // Verificar si realmente cambió
                    $cambio_efectivo = $producto_anterior['precio_efectivo'] != $precio_efectivo;
                    $cambio_transferencia = $producto_anterior['precio_transferencia'] != $precio_transferencia;

                    if ($cambio_efectivo || $cambio_transferencia) {
                        // Actualizar precio en tabla productos
                        $stmt = $pdo->prepare("UPDATE productos SET precio_efectivo = ?, precio_transferencia = ?, updated_at = NOW(), updated_by = ? WHERE id = ?");
                        $stmt->execute([$precio_efectivo, $precio_transferencia, $_SESSION['admin_user'] ?? 'admin', $id]);

                        // Guardar en historial
                        $stmt = $pdo->prepare("
                            INSERT INTO historial_precios
                            (producto_id, tipo, precio_anterior_efectivo, precio_anterior_transferencia,
                             precio_nuevo_efectivo, precio_nuevo_transferencia, motivo, usuario, fecha_cambio)
                            VALUES (?, 'producto', ?, ?, ?, ?, ?, ?, NOW())
                        ");
                        $stmt->execute([
                            $id,
                            $producto_anterior['precio_efectivo'],
                            $producto_anterior['precio_transferencia'],
                            $precio_efectivo,
                            $precio_transferencia,
                            'Actualización manual desde módulo de productos',
                            $_SESSION['admin_user'] ?? 'admin'
                        ]);

                        $productos_actualizados++;
                    }
                }
            }
        }

        $pdo->commit();
        $mensaje = "✅ $productos_actualizados productos actualizados correctamente. Los precios se han sincronizado en todo el sistema.";

    } catch (Exception $e) {
        $pdo->rollBack();
        $error = "❌ Error: " . $e->getMessage();
    }
}

// Crear producto nuevo
if ($_POST && isset($_POST['crear_producto'])) {
    try {
        $nombre = sanitize($_POST['nombre_nuevo']);
        $precio_efectivo = (float)$_POST['precio_efectivo_nuevo'];
        $precio_transferencia = (float)$_POST['precio_transferencia_nuevo'];
        $categoria = sanitize($_POST['categoria_nueva'] ?? 'Standard');
        $descripcion = sanitize($_POST['descripcion_nueva'] ?? '');

        if (empty($nombre)) {
            throw new Exception('El nombre es obligatorio');
        }

        if ($precio_efectivo <= 0 || $precio_transferencia <= 0) {
            throw new Exception('Los precios deben ser mayores a 0');
        }

        // Verificar que no exista
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM productos WHERE nombre = ?");
        $stmt->execute([$nombre]);
        if ($stmt->fetchColumn() > 0) {
            throw new Exception('Ya existe un producto con ese nombre');
        }

        // Crear producto
        $stmt = $pdo->prepare("
            INSERT INTO productos (nombre, precio_efectivo, precio_transferencia, categoria, descripcion, activo, created_at, updated_at, updated_by)
            VALUES (?, ?, ?, ?, ?, 1, NOW(), NOW(), ?)
        ");
        $stmt->execute([$nombre, $precio_efectivo, $precio_transferencia, $categoria, $descripcion, $_SESSION['admin_user'] ?? 'admin']);

        $mensaje = "✅ Producto '$nombre' creado correctamente";

    } catch (Exception $e) {
        $error = "❌ Error: " . $e->getMessage();
    }
}

// Activar/Desactivar producto
if ($_POST && isset($_POST['toggle_activo'])) {
    try {
        $producto_id = (int)$_POST['producto_id'];
        $nuevo_estado = (int)$_POST['nuevo_estado'];

        $stmt = $pdo->prepare("UPDATE productos SET activo = ?, updated_at = NOW(), updated_by = ? WHERE id = ?");
        $stmt->execute([$nuevo_estado, $_SESSION['admin_user'] ?? 'admin', $producto_id]);

        $estado_texto = $nuevo_estado ? 'activado' : 'desactivado';
        $mensaje = "✅ Producto $estado_texto correctamente";

    } catch (Exception $e) {
        $error = "❌ Error: " . $e->getMessage();
    }
}

// Eliminar producto (solo si no tiene pedidos asociados)
if ($_POST && isset($_POST['eliminar_producto'])) {
    try {
        $producto_id = (int)$_POST['producto_id'];

        // Verificar si tiene pedidos asociados
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM pedidos_items WHERE producto_id = ?");
        $stmt->execute([$producto_id]);
        $count = $stmt->fetchColumn();

        if ($count > 0) {
            throw new Exception("No se puede eliminar el producto porque tiene $count pedidos asociados. Puedes desactivarlo en su lugar.");
        }

        // Eliminar producto
        $stmt = $pdo->prepare("DELETE FROM productos WHERE id = ?");
        $stmt->execute([$producto_id]);

        $mensaje = "✅ Producto eliminado correctamente";

    } catch (Exception $e) {
        $error = "❌ Error: " . $e->getMessage();
    }
}

// Obtener todos los productos
$productos = $pdo->query("
    SELECT id, nombre, precio_efectivo, precio_transferencia, categoria, activo, updated_at, descripcion
    FROM productos
    ORDER BY categoria, nombre
")->fetchAll();

// Obtener categorías para el selector
$categorias = $pdo->query("
    SELECT DISTINCT categoria
    FROM productos
    WHERE categoria IS NOT NULL
    ORDER BY categoria
")->fetchAll(PDO::FETCH_COLUMN);

// Estadísticas
$stats = [
    'total' => count($productos),
    'activos' => count(array_filter($productos, fn($p) => $p['activo'] == 1)),
    'inactivos' => count(array_filter($productos, fn($p) => $p['activo'] == 0)),
    'categorias' => count($categorias)
];

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión de Productos - <?= APP_NAME ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .producto-row { transition: all 0.2s; }
        .producto-row:hover { transform: translateY(-2px); box-shadow: 0 4px 6px rgba(0,0,0,0.1); }
        .filtro-activo { background-color: #3b82f6 !important; color: white !important; }
        .highlight { background-color: #fef3c7 !important; }
    </style>
</head>
<body class="bg-gray-100">
    <!-- Header -->
    <header class="bg-white shadow-md sticky top-0 z-50">
        <div class="container mx-auto px-4 py-3 flex justify-between items-center">
            <div class="flex items-center space-x-4">
                <a href="../../" class="text-gray-600 hover:text-gray-800">
                    <i class="fas fa-arrow-left text-xl"></i>
                </a>
                <div>
                    <h1 class="text-2xl font-bold text-gray-800">
                        <i class="fas fa-box-open text-green-500 mr-2"></i>Gestión de Productos
                    </h1>
                    <p class="text-xs text-gray-500">Administra precios y productos de forma centralizada</p>
                </div>
            </div>
            <a href="../../logout.php" class="bg-red-500 hover:bg-red-600 text-white px-4 py-2 rounded-lg transition">
                <i class="fas fa-sign-out-alt mr-2"></i>Salir
            </a>
        </div>
    </header>

    <main class="container mx-auto px-4 py-6">
        <!-- Mensajes -->
        <?php if ($mensaje): ?>
        <div class="bg-green-100 border-l-4 border-green-500 text-green-700 px-6 py-4 rounded-lg mb-6 shadow-md animate-pulse">
            <div class="flex items-center">
                <i class="fas fa-check-circle text-2xl mr-3"></i>
                <div>
                    <p class="font-bold">Éxito</p>
                    <p><?= $mensaje ?></p>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <?php if ($error): ?>
        <div class="bg-red-100 border-l-4 border-red-500 text-red-700 px-6 py-4 rounded-lg mb-6 shadow-md">
            <div class="flex items-center">
                <i class="fas fa-exclamation-triangle text-2xl mr-3"></i>
                <div>
                    <p class="font-bold">Error</p>
                    <p><?= $error ?></p>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Estadísticas -->
        <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
            <div class="bg-gradient-to-br from-blue-500 to-blue-600 text-white rounded-lg p-6 shadow-lg">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm opacity-90">Total Productos</p>
                        <p class="text-4xl font-bold"><?= $stats['total'] ?></p>
                    </div>
                    <i class="fas fa-boxes text-5xl opacity-30"></i>
                </div>
            </div>

            <div class="bg-gradient-to-br from-green-500 to-green-600 text-white rounded-lg p-6 shadow-lg">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm opacity-90">Activos</p>
                        <p class="text-4xl font-bold"><?= $stats['activos'] ?></p>
                    </div>
                    <i class="fas fa-check-circle text-5xl opacity-30"></i>
                </div>
            </div>

            <div class="bg-gradient-to-br from-red-500 to-red-600 text-white rounded-lg p-6 shadow-lg">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm opacity-90">Inactivos</p>
                        <p class="text-4xl font-bold"><?= $stats['inactivos'] ?></p>
                    </div>
                    <i class="fas fa-times-circle text-5xl opacity-30"></i>
                </div>
            </div>

            <div class="bg-gradient-to-br from-purple-500 to-purple-600 text-white rounded-lg p-6 shadow-lg">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm opacity-90">Categorías</p>
                        <p class="text-4xl font-bold"><?= $stats['categorias'] ?></p>
                    </div>
                    <i class="fas fa-folder text-5xl opacity-30"></i>
                </div>
            </div>
        </div>

        <!-- Buscador y Filtros -->
        <div class="bg-white rounded-lg shadow p-6 mb-6">
            <div class="flex flex-col md:flex-row gap-4">
                <!-- Buscador -->
                <div class="flex-1">
                    <label class="block text-sm font-medium text-gray-700 mb-2">
                        <i class="fas fa-search mr-1"></i>Buscar Productos
                    </label>
                    <input type="text" id="searchInput" placeholder="Buscar por nombre..."
                           class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500">
                </div>

                <!-- Filtro por categoría -->
                <div class="w-full md:w-64">
                    <label class="block text-sm font-medium text-gray-700 mb-2">
                        <i class="fas fa-filter mr-1"></i>Categoría
                    </label>
                    <select id="categoriaFilter" class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500">
                        <option value="">Todas las categorías</option>
                        <?php foreach ($categorias as $cat): ?>
                            <option value="<?= htmlspecialchars($cat) ?>"><?= htmlspecialchars($cat) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Filtro por estado -->
                <div class="w-full md:w-48">
                    <label class="block text-sm font-medium text-gray-700 mb-2">
                        <i class="fas fa-toggle-on mr-1"></i>Estado
                    </label>
                    <select id="estadoFilter" class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500">
                        <option value="">Todos</option>
                        <option value="1" selected>Solo Activos</option>
                        <option value="0">Solo Inactivos</option>
                    </select>
                </div>
            </div>
        </div>

        <!-- Crear nuevo producto (colapsable) -->
        <div class="bg-white rounded-lg shadow mb-6 overflow-hidden">
            <button onclick="toggleCrearProducto()" class="w-full px-6 py-4 bg-gradient-to-r from-blue-500 to-blue-600 text-white font-semibold flex items-center justify-between hover:from-blue-600 hover:to-blue-700 transition">
                <span>
                    <i class="fas fa-plus-circle mr-2"></i>Crear Nuevo Producto
                </span>
                <i id="iconToggle" class="fas fa-chevron-down transition-transform"></i>
            </button>

            <div id="formCrearProducto" class="hidden p-6 border-t">
                <form method="POST" class="grid grid-cols-1 md:grid-cols-6 gap-4">
                    <div class="md:col-span-2">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Nombre *</label>
                        <input type="text" name="nombre_nuevo" required
                               placeholder="Ej: 24 Jamón y Queso"
                               class="w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500">
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Precio Efectivo *</label>
                        <input type="number" name="precio_efectivo_nuevo" required min="0" step="0.01"
                               placeholder="12500"
                               class="w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500">
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Precio Transfer. *</label>
                        <input type="number" name="precio_transferencia_nuevo" required min="0" step="0.01"
                               placeholder="12500"
                               class="w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500">
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Categoría *</label>
                        <select name="categoria_nueva" class="w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500">
                            <option value="Clásicos">Clásicos</option>
                            <option value="Especiales">Especiales</option>
                            <option value="Premium">Premium</option>
                            <option value="Elegidos">Elegidos</option>
                            <?php foreach ($categorias as $cat): ?>
                                <?php if (!in_array($cat, ['Clásicos', 'Especiales', 'Premium', 'Elegidos'])): ?>
                                    <option value="<?= htmlspecialchars($cat) ?>"><?= htmlspecialchars($cat) ?></option>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="flex items-end">
                        <button type="submit" name="crear_producto"
                                class="w-full bg-green-500 hover:bg-green-600 text-white px-4 py-2 rounded-lg font-medium transition">
                            <i class="fas fa-check mr-1"></i>Crear
                        </button>
                    </div>

                    <div class="md:col-span-6">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Descripción (Opcional)</label>
                        <textarea name="descripcion_nueva" rows="2" placeholder="Descripción del producto..."
                                  class="w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500"></textarea>
                    </div>
                </form>
            </div>
        </div>

        <!-- Lista de productos -->
        <div class="bg-white rounded-lg shadow overflow-hidden">
            <div class="bg-gradient-to-r from-gray-50 to-gray-100 px-6 py-4 border-b">
                <div class="flex items-center justify-between">
                    <h3 class="text-lg font-semibold">
                        <i class="fas fa-list text-green-500 mr-2"></i>
                        <span id="productoCount"><?= count($productos) ?></span> Productos
                        <span class="text-sm text-gray-500 font-normal">(<span id="productoVisible"><?= count($productos) ?></span> visibles)</span>
                    </h3>
                    <span class="text-sm text-gray-600">
                        <i class="fas fa-clock mr-1"></i>Última actualización: <?= date('d/m/Y H:i') ?>
                    </span>
                </div>
            </div>

            <form method="POST" id="formActualizacion">
                <div class="overflow-x-auto">
                    <table class="min-w-full" id="productosTable">
                        <thead class="bg-gray-100 sticky top-0">
                            <tr>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-600 uppercase">Estado</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-600 uppercase">Producto</th>
                                <th class="px-4 py-3 text-center text-xs font-medium text-gray-600 uppercase">Precio Efectivo</th>
                                <th class="px-4 py-3 text-center text-xs font-medium text-gray-600 uppercase">Precio Transfer.</th>
                                <th class="px-4 py-3 text-center text-xs font-medium text-gray-600 uppercase">Diferencia</th>
                                <th class="px-4 py-3 text-center text-xs font-medium text-gray-600 uppercase">Última Mod.</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php
                            $categoria_actual = '';
                            foreach ($productos as $producto):
                                $diferencia = $producto['precio_transferencia'] - $producto['precio_efectivo'];
                                $porcentaje_diferencia = $producto['precio_efectivo'] > 0 ? ($diferencia / $producto['precio_efectivo']) * 100 : 0;

                                // Separador por categoría
                                if ($categoria_actual !== $producto['categoria']):
                                    $categoria_actual = $producto['categoria'];
                            ?>
                                <tr class="categoria-header bg-gradient-to-r from-blue-50 to-blue-100" data-categoria="<?= htmlspecialchars($categoria_actual) ?>">
                                    <td colspan="6" class="px-6 py-3 text-sm font-bold text-blue-800 uppercase">
                                        <i class="fas fa-folder-open mr-2"></i><?= htmlspecialchars($categoria_actual ?: 'Sin categoría') ?>
                                    </td>
                                </tr>
                            <?php endif; ?>

                            <tr class="producto-row <?= $producto['activo'] ? 'hover:bg-blue-50' : 'bg-gray-50 opacity-60' ?>"
                                data-nombre="<?= htmlspecialchars(strtolower($producto['nombre'])) ?>"
                                data-categoria="<?= htmlspecialchars($categoria_actual) ?>"
                                data-activo="<?= $producto['activo'] ?>">

                                <td class="px-4 py-3 whitespace-nowrap text-center">
                                    <form method="POST" class="inline">
                                        <input type="hidden" name="producto_id" value="<?= $producto['id'] ?>">
                                        <input type="hidden" name="nuevo_estado" value="<?= $producto['activo'] ? 0 : 1 ?>">
                                        <button type="submit" name="toggle_activo"
                                                class="<?= $producto['activo'] ? 'text-green-600 hover:text-red-600' : 'text-red-600 hover:text-green-600' ?> text-2xl transition"
                                                title="<?= $producto['activo'] ? 'Desactivar' : 'Activar' ?>"
                                                onclick="return confirm('¿Cambiar estado del producto?')">
                                            <i class="fas <?= $producto['activo'] ? 'fa-toggle-on' : 'fa-toggle-off' ?>"></i>
                                        </button>
                                    </form>
                                </td>

                                <td class="px-4 py-3">
                                    <div>
                                        <div class="font-medium text-gray-900"><?= htmlspecialchars($producto['nombre']) ?></div>
                                        <div class="text-xs text-gray-500 flex items-center gap-2">
                                            <span>ID: <?= $producto['id'] ?></span>
                                            <?php if ($producto['descripcion']): ?>
                                                <span class="px-2 py-0.5 bg-purple-100 text-purple-700 rounded text-xs" title="<?= htmlspecialchars($producto['descripcion']) ?>">
                                                    <i class="fas fa-info-circle"></i>
                                                </span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </td>

                                <td class="px-4 py-3 whitespace-nowrap text-center">
                                    <div class="flex items-center justify-center gap-1">
                                        <span class="text-gray-500">$</span>
                                        <input type="number"
                                               name="productos[<?= $producto['id'] ?>][precio_efectivo]"
                                               value="<?= $producto['precio_efectivo'] ?>"
                                               data-original="<?= $producto['precio_efectivo'] ?>"
                                               min="0" step="0.01"
                                               class="precio-input w-28 px-2 py-1.5 border rounded-lg text-center focus:ring-2 focus:ring-green-500 font-medium"
                                               <?= $producto['activo'] ? '' : 'disabled' ?>>
                                    </div>
                                </td>

                                <td class="px-4 py-3 whitespace-nowrap text-center">
                                    <div class="flex items-center justify-center gap-1">
                                        <span class="text-gray-500">$</span>
                                        <input type="number"
                                               name="productos[<?= $producto['id'] ?>][precio_transferencia]"
                                               value="<?= $producto['precio_transferencia'] ?>"
                                               data-original="<?= $producto['precio_transferencia'] ?>"
                                               min="0" step="0.01"
                                               class="precio-input w-28 px-2 py-1.5 border rounded-lg text-center focus:ring-2 focus:ring-blue-500 font-medium"
                                               <?= $producto['activo'] ? '' : 'disabled' ?>>
                                    </div>
                                </td>

                                <td class="px-4 py-3 whitespace-nowrap text-center">
                                    <div class="font-semibold <?= $diferencia > 0 ? 'text-green-600' : ($diferencia < 0 ? 'text-red-600' : 'text-gray-600') ?>">
                                        <?= $diferencia >= 0 ? '+' : '' ?>$<?= number_format($diferencia, 0, ',', '.') ?>
                                    </div>
                                    <?php if ($porcentaje_diferencia != 0): ?>
                                        <div class="text-xs text-gray-500">(<?= number_format(abs($porcentaje_diferencia), 1) ?>%)</div>
                                    <?php endif; ?>
                                </td>

                                <td class="px-4 py-3 whitespace-nowrap text-center text-sm text-gray-500">
                                    <i class="fas fa-clock mr-1"></i><?= date('d/m H:i', strtotime($producto['updated_at'])) ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                
                <div class="bg-gradient-to-r from-gray-50 to-gray-100 px-6 py-4 border-t flex items-center justify-between">
                    <div class="flex items-center space-x-4">
                        <div class="text-sm text-gray-600">
                            <i class="fas fa-database mr-1"></i>
                            <span id="cambiosCount">0</span> cambios pendientes
                        </div>
                        <div class="text-xs text-gray-500">
                            <i class="fas fa-info-circle mr-1"></i>
                            Todos los cambios se registran en el historial
                        </div>
                    </div>
                    <button type="submit" name="actualizar_masivo" id="btnActualizar"
                            class="bg-gradient-to-r from-green-500 to-green-600 hover:from-green-600 hover:to-green-700 text-white px-8 py-3 rounded-lg font-bold shadow-lg transition transform hover:scale-105"
                            onclick="return confirm('¿Actualizar todos los precios modificados?\n\nEsto guardará los cambios en el sistema.')">
                        <i class="fas fa-save mr-2"></i>Guardar Cambios
                    </button>
                </div>
            </form>
        </div>

        <!-- Acciones rápidas -->
        <div class="mt-6 grid grid-cols-1 md:grid-cols-4 gap-4">
            <a href="historial.php" class="bg-gradient-to-br from-blue-500 to-blue-600 hover:from-blue-600 hover:to-blue-700 text-white p-6 rounded-lg text-center shadow-lg transition transform hover:scale-105">
                <i class="fas fa-history text-3xl mb-3"></i>
                <div class="font-bold text-lg">Historial</div>
                <div class="text-sm opacity-90">Ver cambios de precios</div>
            </a>

            <button onclick="aplicarAumentoMasivo()"
                    class="bg-gradient-to-br from-yellow-500 to-orange-500 hover:from-yellow-600 hover:to-orange-600 text-white p-6 rounded-lg text-center shadow-lg transition transform hover:scale-105">
                <i class="fas fa-percentage text-3xl mb-3"></i>
                <div class="font-bold text-lg">Aumento Masivo</div>
                <div class="text-sm opacity-90">Aplicar % a todos</div>
            </button>

            <button onclick="copiarPrecios()"
                    class="bg-gradient-to-br from-purple-500 to-purple-600 hover:from-purple-600 hover:to-purple-700 text-white p-6 rounded-lg text-center shadow-lg transition transform hover:scale-105">
                <i class="fas fa-copy text-3xl mb-3"></i>
                <div class="font-bold text-lg">Copiar Precios</div>
                <div class="text-sm opacity-90">Efectivo → Transfer.</div>
            </button>

            <a href="../pedidos/crear_pedido.php" class="bg-gradient-to-br from-green-500 to-green-600 hover:from-green-600 hover:to-green-700 text-white p-6 rounded-lg text-center shadow-lg transition transform hover:scale-105">
                <i class="fas fa-cart-plus text-3xl mb-3"></i>
                <div class="font-bold text-lg">Crear Pedido</div>
                <div class="text-sm opacity-90">Con precios actuales</div>
            </a>
        </div>
    </main>

    <script>
        // Toggle formulario crear producto
        function toggleCrearProducto() {
            const form = document.getElementById('formCrearProducto');
            const icon = document.getElementById('iconToggle');
            form.classList.toggle('hidden');
            icon.classList.toggle('fa-chevron-down');
            icon.classList.toggle('fa-chevron-up');
        }

        // Búsqueda y filtros en tiempo real
        document.addEventListener('DOMContentLoaded', function() {
            const searchInput = document.getElementById('searchInput');
            const categoriaFilter = document.getElementById('categoriaFilter');
            const estadoFilter = document.getElementById('estadoFilter');

            function filtrarProductos() {
                const searchTerm = searchInput.value.toLowerCase();
                const categoriaSeleccionada = categoriaFilter.value;
                const estadoSeleccionado = estadoFilter.value;

                const productoRows = document.querySelectorAll('.producto-row');
                const categoriaHeaders = document.querySelectorAll('.categoria-header');
                let visibleCount = 0;

                // Ocultar todos los headers primero
                categoriaHeaders.forEach(header => header.style.display = 'none');

                productoRows.forEach(row => {
                    const nombre = row.dataset.nombre;
                    const categoria = row.dataset.categoria;
                    const activo = row.dataset.activo;

                    let mostrar = true;

                    // Filtro de búsqueda
                    if (searchTerm && !nombre.includes(searchTerm)) {
                        mostrar = false;
                    }

                    // Filtro de categoría
                    if (categoriaSeleccionada && categoria !== categoriaSeleccionada) {
                        mostrar = false;
                    }

                    // Filtro de estado
                    if (estadoSeleccionado !== '' && activo !== estadoSeleccionado) {
                        mostrar = false;
                    }

                    row.style.display = mostrar ? '' : 'none';
                    if (mostrar) visibleCount++;
                });

                // Mostrar headers de categorías que tengan productos visibles
                categoriaHeaders.forEach(header => {
                    const categoria = header.dataset.categoria;
                    const tieneVisibles = Array.from(productoRows).some(row =>
                        row.dataset.categoria === categoria && row.style.display !== 'none'
                    );
                    if (tieneVisibles) {
                        header.style.display = '';
                    }
                });

                document.getElementById('productoVisible').textContent = visibleCount;
            }

            searchInput.addEventListener('input', filtrarProductos);
            categoriaFilter.addEventListener('change', filtrarProductos);
            estadoFilter.addEventListener('change', filtrarProductos);

            // Aplicar filtro inicial (solo activos)
            filtrarProductos();
        });

        // Resaltar campos modificados y contar cambios
        document.addEventListener('DOMContentLoaded', function() {
            const priceInputs = document.querySelectorAll('.precio-input');
            let cambiosPendientes = 0;

            priceInputs.forEach(input => {
                const valorOriginal = input.dataset.original;

                input.addEventListener('input', function() {
                    const cambio = this.value != valorOriginal;

                    if (cambio) {
                        this.classList.add('highlight');
                        this.style.borderWidth = '2px';
                        if (!this.dataset.modificado) {
                            this.dataset.modificado = 'true';
                            cambiosPendientes++;
                        }
                    } else {
                        this.classList.remove('highlight');
                        this.style.borderWidth = '1px';
                        if (this.dataset.modificado) {
                            delete this.dataset.modificado;
                            cambiosPendientes--;
                        }
                    }

                    document.getElementById('cambiosCount').textContent = cambiosPendientes;

                    // Cambiar estilo del botón si hay cambios
                    const btnActualizar = document.getElementById('btnActualizar');
                    if (cambiosPendientes > 0) {
                        btnActualizar.classList.add('animate-pulse');
                    } else {
                        btnActualizar.classList.remove('animate-pulse');
                    }
                });
            });
        });

        // Aplicar aumento masivo
        function aplicarAumentoMasivo() {
            const porcentaje = prompt('¿Qué porcentaje de aumento deseas aplicar?\n\nEjemplos:\n• 10 = +10%\n• -5 = -5%\n• 15.5 = +15.5%');

            if (porcentaje && !isNaN(porcentaje)) {
                const factor = 1 + (parseFloat(porcentaje) / 100);

                if (confirm(`¿Aplicar ${porcentaje}% a todos los productos activos?\n\n${porcentaje > 0 ? 'Esto aumentará' : 'Esto reducirá'} los precios en ${Math.abs(porcentaje)}%`)) {
                    let cambiosAplicados = 0;

                    document.querySelectorAll('.precio-input:not([disabled])').forEach(input => {
                        const valorActual = parseFloat(input.value) || 0;
                        const nuevoValor = Math.round(valorActual * factor);
                        input.value = nuevoValor;
                        input.dispatchEvent(new Event('input'));
                        cambiosAplicados++;
                    });

                    alert(`✅ Se aplicó ${porcentaje}% a ${cambiosAplicados} precios.\n\n¡No olvides hacer click en "Guardar Cambios"!`);
                }
            }
        }

        // Copiar precios efectivo → transferencia
        function copiarPrecios() {
            if (confirm('¿Copiar todos los precios de EFECTIVO a TRANSFERENCIA?\n\nEsto sobrescribirá los precios de transferencia actuales.')) {
                let copiados = 0;

                document.querySelectorAll('input[name*="precio_efectivo"]:not([disabled])').forEach(efectivoInput => {
                    const id = efectivoInput.name.match(/\[(\d+)\]/)[1];
                    const transferenciaInput = document.querySelector(`input[name="productos[${id}][precio_transferencia]"]`);

                    if (transferenciaInput) {
                        transferenciaInput.value = efectivoInput.value;
                        transferenciaInput.dispatchEvent(new Event('input'));
                        copiados++;
                    }
                });

                alert(`✅ Se copiaron ${copiados} precios.\n\n¡No olvides hacer click en "Guardar Cambios"!`);
            }
        }
    </script>
</body>
</html>