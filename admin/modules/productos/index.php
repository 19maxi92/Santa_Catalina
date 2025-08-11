<?php
// admin/modules/productos/index.php - Versión corregida para evitar error 500

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

// Manejar acciones
$mensaje = '';
$error = '';

if ($_POST) {
    try {
        switch ($_POST['accion']) {
            case 'toggle_estado':
                $id = (int)$_POST['id'];
                $estado = $_POST['estado'] === '1' ? 0 : 1;
                
                $stmt = $pdo->prepare("UPDATE productos SET activo = ?, updated_by = ?, updated_at = NOW() WHERE id = ?");
                $stmt->execute([$estado, $_SESSION['admin_user'] ?? 'admin', $id]);
                $mensaje = $estado ? 'Producto activado' : 'Producto desactivado';
                break;

            case 'eliminar':
                $id = (int)$_POST['id'];
                
                // Verificar si tiene pedidos asociados
                $stmt = $pdo->prepare("SELECT COUNT(*) FROM pedidos WHERE producto = (SELECT nombre FROM productos WHERE id = ?)");
                $stmt->execute([$id]);
                $tiene_pedidos = $stmt->fetchColumn();

                if ($tiene_pedidos > 0) {
                    $error = 'No se puede eliminar: el producto tiene pedidos asociados. Use desactivar en su lugar.';
                } else {
                    $stmt = $pdo->prepare("DELETE FROM productos WHERE id = ?");
                    $stmt->execute([$id]);
                    $mensaje = 'Producto eliminado correctamente';
                }
                break;

            case 'cambio_rapido_precio':
                $id = (int)$_POST['id'];
                $nuevo_efectivo = (float)$_POST['precio_efectivo'];
                $nuevo_transferencia = (float)$_POST['precio_transferencia'];
                $motivo = sanitize($_POST['motivo'] ?? '');

                if ($nuevo_efectivo <= 0 || $nuevo_transferencia <= 0) {
                    throw new Exception('Los precios deben ser mayores a 0');
                }

                $stmt = $pdo->prepare("UPDATE productos SET precio_efectivo = ?, precio_transferencia = ?, updated_by = ?, updated_at = NOW() WHERE id = ?");
                $stmt->execute([$nuevo_efectivo, $nuevo_transferencia, $_SESSION['admin_user'] ?? 'admin', $id]);
                $mensaje = 'Precios actualizados correctamente';
                break;
        }
    } catch (Exception $e) {
        $error = 'Error procesando la acción: ' . $e->getMessage();
    }
}

// Obtener filtros
$filtro_categoria = isset($_GET['categoria']) ? sanitize($_GET['categoria']) : '';
$filtro_estado = isset($_GET['estado']) ? sanitize($_GET['estado']) : '';
$buscar = isset($_GET['buscar']) ? sanitize($_GET['buscar']) : '';

// Construir consulta de productos
try {
    $sql = "SELECT p.*, 
                   COUNT(pe.id) as total_pedidos,
                   SUM(pe.cantidad) as unidades_vendidas,
                   SUM(pe.precio) as total_facturado,
                   MAX(pe.created_at) as ultima_venta
            FROM productos p
            LEFT JOIN pedidos pe ON pe.producto = p.nombre
            WHERE 1=1";
    $params = [];

    if ($filtro_categoria) {
        $sql .= " AND p.categoria = ?";
        $params[] = $filtro_categoria;
    }

    if ($filtro_estado !== '') {
        $sql .= " AND p.activo = ?";
        $params[] = $filtro_estado;
    }

    if ($buscar) {
        $sql .= " AND (p.nombre LIKE ? OR p.descripcion LIKE ?)";
        $buscarParam = "%$buscar%";
        $params[] = $buscarParam;
        $params[] = $buscarParam;
    }

    $sql .= " GROUP BY p.id ORDER BY p.orden_mostrar, p.nombre";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $productos = $stmt->fetchAll();

} catch (Exception $e) {
    $error = 'Error obteniendo productos: ' . $e->getMessage();
    $productos = [];
}

// Obtener categorías para filtro
try {
    $categorias = $pdo->query("SELECT DISTINCT categoria FROM productos WHERE categoria IS NOT NULL ORDER BY categoria")->fetchAll();
} catch (Exception $e) {
    $categorias = [];
}

// Estadísticas generales
try {
    $stats = $pdo->query("
        SELECT 
            COUNT(*) as total_productos,
            SUM(CASE WHEN activo = 1 THEN 1 ELSE 0 END) as productos_activos,
            SUM(CASE WHEN activo = 0 THEN 1 ELSE 0 END) as productos_inactivos
        FROM productos
    ")->fetch();

    $promos_activas = $pdo->query("
        SELECT COUNT(*) FROM promos 
        WHERE activa = 1 
        AND (fecha_inicio IS NULL OR CURDATE() >= fecha_inicio)
        AND (fecha_fin IS NULL OR CURDATE() <= fecha_fin)
    ")->fetchColumn();
} catch (Exception $e) {
    $stats = ['total_productos' => 0, 'productos_activos' => 0, 'productos_inactivos' => 0];
    $promos_activas = 0;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión de Productos - <?= APP_NAME ?></title>
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
                    <i class="fas fa-boxes text-purple-500 mr-2"></i>Gestión de Productos
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
            <i class="fas fa-check-circle mr-2"></i><?= htmlspecialchars($mensaje) ?>
        </div>
        <?php endif; ?>

        <?php if ($error): ?>
        <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
            <i class="fas fa-exclamation-circle mr-2"></i><?= htmlspecialchars($error) ?>
        </div>
        <?php endif; ?>

        <!-- Estadísticas -->
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
            <div class="bg-white p-4 rounded-lg shadow text-center">
                <div class="text-2xl font-bold text-blue-600"><?= $stats['productos_activos'] ?></div>
                <div class="text-sm text-gray-600">Productos Activos</div>
            </div>
            <div class="bg-white p-4 rounded-lg shadow text-center">
                <div class="text-2xl font-bold text-gray-600"><?= $stats['productos_inactivos'] ?></div>
                <div class="text-sm text-gray-600">Productos Inactivos</div>
            </div>
            <div class="bg-white p-4 rounded-lg shadow text-center">
                <div class="text-2xl font-bold text-purple-600"><?= $promos_activas ?></div>
                <div class="text-sm text-gray-600">Promos Activas</div>
            </div>
            <div class="bg-white p-4 rounded-lg shadow text-center">
                <div class="text-2xl font-bold text-green-600"><?= $stats['total_productos'] ?></div>
                <div class="text-sm text-gray-600">Total Productos</div>
            </div>
        </div>

        <!-- Barra de herramientas -->
        <div class="bg-white rounded-lg shadow mb-6 p-6">
            <div class="flex flex-col md:flex-row justify-between items-center space-y-4 md:space-y-0 mb-4">
                <div class="flex space-x-3">
                    <a href="crear_producto.php" class="bg-green-500 hover:bg-green-600 text-white px-4 py-2 rounded-lg">
                        <i class="fas fa-plus mr-2"></i>Nuevo Producto
                    </a>
                    <a href="promos.php" class="bg-purple-500 hover:bg-purple-600 text-white px-4 py-2 rounded-lg">
                        <i class="fas fa-tags mr-2"></i>Gestionar Promos
                    </a>
                    <a href="historial.php" class="bg-gray-500 hover:bg-gray-600 text-white px-4 py-2 rounded-lg">
                        <i class="fas fa-history mr-2"></i>Historial Cambios
                    </a>
                </div>

                <!-- Acciones rápidas -->
                <div class="flex space-x-2">
                    <button onclick="actualizarTodosPrecios()" class="bg-orange-500 hover:bg-orange-600 text-white px-3 py-2 rounded text-sm">
                        <i class="fas fa-calculator mr-1"></i>Ajuste Masivo
                    </button>
                </div>
            </div>

            <!-- Filtros -->
            <form method="GET" class="grid grid-cols-1 md:grid-cols-4 gap-4">
                <!-- Buscador -->
                <div>
                    <input type="text" name="buscar" value="<?= htmlspecialchars($buscar) ?>" 
                           placeholder="Buscar producto..." 
                           class="w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-purple-500">
                </div>
                
                <!-- Categoría -->
                <div>
                    <select name="categoria" class="w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-purple-500">
                        <option value="">Todas las categorías</option>
                        <?php foreach ($categorias as $cat): ?>
                            <option value="<?= htmlspecialchars($cat['categoria']) ?>" <?= $filtro_categoria === $cat['categoria'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($cat['categoria']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <!-- Estado -->
                <div>
                    <select name="estado" class="w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-purple-500">
                        <option value="">Todos los estados</option>
                        <option value="1" <?= $filtro_estado === '1' ? 'selected' : '' ?>>Solo Activos</option>
                        <option value="0" <?= $filtro_estado === '0' ? 'selected' : '' ?>>Solo Inactivos</option>
                    </select>
                </div>
                
                <!-- Botón buscar -->
                <div>
                    <button type="submit" class="w-full bg-purple-500 hover:bg-purple-600 text-white py-2 px-4 rounded-lg">
                        <i class="fas fa-search mr-1"></i>Filtrar
                    </button>
                </div>
            </form>
        </div>

        <!-- Lista de productos -->
        <div class="bg-white rounded-lg shadow overflow-hidden">
            <?php if (empty($productos)): ?>
                <div class="p-8 text-center text-gray-500">
                    <i class="fas fa-box-open text-6xl mb-4"></i>
                    <h3 class="text-xl mb-2">No hay productos</h3>
                    <p>No se encontraron productos con los filtros aplicados</p>
                    <div class="mt-4">
                        <a href="crear_producto.php" class="bg-green-500 hover:bg-green-600 text-white px-4 py-2 rounded-lg">
                            <i class="fas fa-plus mr-2"></i>Crear Primer Producto
                        </a>
                    </div>
                </div>
            <?php else: ?>
                <div class="overflow-x-auto">
                    <table class="min-w-full">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Producto</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Categoría</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Precios</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Ventas</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Estado</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Acciones</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200">
                            <?php foreach ($productos as $producto): ?>
                                <tr class="hover:bg-gray-50 <?= $producto['activo'] ? '' : 'opacity-50' ?>">
                                    <td class="px-6 py-4">
                                        <div class="flex items-center">
                                            <div>
                                                <div class="text-sm font-medium text-gray-900">
                                                    <?= htmlspecialchars($producto['nombre']) ?>
                                                </div>
                                                <div class="text-sm text-gray-500 max-w-xs truncate">
                                                    <?= htmlspecialchars($producto['descripcion'] ?: 'Sin descripción') ?>
                                                </div>
                                                <?php if ($producto['orden_mostrar'] > 0): ?>
                                                    <div class="text-xs text-blue-600">Orden: <?= $producto['orden_mostrar'] ?></div>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4">
                                        <span class="px-2 py-1 text-xs font-medium rounded-full 
                                            <?php
                                            switch($producto['categoria']) {
                                                case 'Premium': echo 'bg-purple-100 text-purple-800'; break;
                                                case 'Surtidos': echo 'bg-blue-100 text-blue-800'; break;
                                                case 'Clásicos': echo 'bg-green-100 text-green-800'; break;
                                                default: echo 'bg-gray-100 text-gray-800';
                                            }
                                            ?>">
                                            <?= htmlspecialchars($producto['categoria'] ?: 'Standard') ?>
                                        </span>
                                    </td>
                                    <td class="px-6 py-4">
                                        <div class="text-sm">
                                            <div class="font-medium text-green-600">
                                                Efectivo: <?= formatPrice($producto['precio_efectivo']) ?>
                                            </div>
                                            <div class="text-gray-500">
                                                Transfer: <?= formatPrice($producto['precio_transferencia']) ?>
                                            </div>
                                            <button onclick="editarPrecioRapido(<?= $producto['id'] ?>, '<?= htmlspecialchars($producto['nombre']) ?>', <?= $producto['precio_efectivo'] ?>, <?= $producto['precio_transferencia'] ?>)" 
                                                    class="text-xs text-blue-600 hover:underline mt-1">
                                                <i class="fas fa-edit mr-1"></i>Editar rápido
                                            </button>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 text-sm text-gray-900">
                                        <?php if ($producto['total_pedidos'] > 0): ?>
                                            <div class="space-y-1">
                                                <div><strong><?= $producto['total_pedidos'] ?></strong> pedidos</div>
                                                <div><strong><?= $producto['unidades_vendidas'] ?></strong> unidades</div>
                                                <div class="text-green-600 font-medium"><?= formatPrice($producto['total_facturado']) ?></div>
                                                <?php if ($producto['ultima_venta']): ?>
                                                    <div class="text-xs text-gray-500">
                                                        Última: <?= date('d/m/Y', strtotime($producto['ultima_venta'])) ?>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                        <?php else: ?>
                                            <span class="text-gray-400">Sin ventas aún</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-6 py-4">
                                        <form method="POST" class="inline">
                                            <input type="hidden" name="accion" value="toggle_estado">
                                            <input type="hidden" name="id" value="<?= $producto['id'] ?>">
                                            <input type="hidden" name="estado" value="<?= $producto['activo'] ?>">
                                            <button type="submit" 
                                                    class="px-2 py-1 text-xs font-medium rounded-full border-0 
                                                    <?= $producto['activo'] 
                                                        ? 'bg-green-100 text-green-800' 
                                                        : 'bg-red-100 text-red-800' ?>">
                                                <?= $producto['activo'] ? '✅ Activo' : '❌ Inactivo' ?>
                                            </button>
                                        </form>
                                    </td>
                                    <td class="px-6 py-4 text-sm font-medium">
                                        <div class="flex space-x-2">
                                            <a href="editar_producto.php?id=<?= $producto['id'] ?>" 
                                               class="text-blue-600 hover:text-blue-900" title="Editar completo">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                            <a href="duplicar_producto.php?id=<?= $producto['id'] ?>" 
                                               class="text-green-600 hover:text-green-900" title="Duplicar">
                                                <i class="fas fa-copy"></i>
                                            </a>
                                            <?php if ($producto['total_pedidos'] == 0): ?>
                                                <button onclick="eliminarProducto(<?= $producto['id'] ?>, '<?= htmlspecialchars($producto['nombre']) ?>')" 
                                                        class="text-red-600 hover:text-red-900" title="Eliminar">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            <?php else: ?>
                                                <span class="text-gray-400" title="No se puede eliminar: tiene pedidos">
                                                    <i class="fas fa-trash"></i>
                                                </span>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>

        <!-- Footer con información -->
        <div class="mt-6 text-center text-gray-500">
            <p>
                Mostrando <?= count($productos) ?> producto<?= count($productos) !== 1 ? 's' : '' ?>
                <?php if ($buscar || $filtro_categoria || $filtro_estado !== ''): ?>
                    | <a href="?" class="text-purple-600 hover:underline">Limpiar filtros</a>
                <?php endif; ?>
            </p>
        </div>
    </main>

    <!-- Modal Edición Rápida de Precios -->
    <div id="precioModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 hidden">
        <div class="bg-white rounded-lg p-6 w-full max-w-md mx-4">
            <h3 class="text-xl font-bold mb-4">
                <i class="fas fa-dollar-sign text-green-500 mr-2"></i>Editar Precios
            </h3>
            
            <form id="precioForm" method="POST">
                <input type="hidden" name="accion" value="cambio_rapido_precio">
                <input type="hidden" name="id" id="precio_producto_id">
                
                <div class="mb-4">
                    <div class="font-medium text-gray-800" id="precio_producto_nombre"></div>
                    <div class="text-sm text-gray-600">Actualización rápida de precios</div>
                </div>
                
                <div class="space-y-4">
                    <div>
                        <label class="block text-gray-700 mb-2">Precio Efectivo</label>
                        <div class="relative">
                            <span class="absolute left-3 top-2 text-gray-500">$</span>
                            <input type="number" name="precio_efectivo" id="precio_efectivo" step="100" required
                                   class="w-full pl-8 pr-4 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500">
                        </div>
                    </div>
                    
                    <div>
                        <label class="block text-gray-700 mb-2">Precio Transferencia</label>
                        <div class="relative">
                            <span class="absolute left-3 top-2 text-gray-500">$</span>
                            <input type="number" name="precio_transferencia" id="precio_transferencia" step="100" required
                                   class="w-full pl-8 pr-4 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                        </div>
                    </div>

                    <div>
                        <label class="block text-gray-700 mb-2">Motivo del cambio</label>
                        <select name="motivo" class="w-full px-3 py-2 border rounded-lg">
                            <option value="Ajuste por inflación">Ajuste por inflación</option>
                            <option value="Incremento costos">Incremento de costos</option>
                            <option value="Promoción temporal">Promoción temporal</option>
                            <option value="Corrección precio">Corrección de precio</option>
                            <option value="Otro">Otro motivo</option>
                        </select>
                    </div>
                </div>
                
                <div class="flex justify-end space-x-2 mt-6">
                    <button type="button" onclick="cerrarPrecioModal()" 
                            class="bg-gray-500 hover:bg-gray-600 text-white px-4 py-2 rounded">
                        Cancelar
                    </button>
                    <button type="submit" 
                            class="bg-green-500 hover:bg-green-600 text-white px-4 py-2 rounded">
                        <i class="fas fa-save mr-1"></i>Actualizar Precios
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        // Edición rápida de precios
        function editarPrecioRapido(id, nombre, precioEfectivo, precioTransferencia) {
            document.getElementById('precio_producto_id').value = id;
            document.getElementById('precio_producto_nombre').textContent = nombre;
            document.getElementById('precio_efectivo').value = precioEfectivo;
            document.getElementById('precio_transferencia').value = precioTransferencia;
            document.getElementById('precioModal').classList.remove('hidden');
        }

        function cerrarPrecioModal() {
            document.getElementById('precioModal').classList.add('hidden');
        }

        // Eliminar producto
        function eliminarProducto(id, nombre) {
            if (confirm(`¿Estás seguro de eliminar "${nombre}"?\n\nEsta acción no se puede deshacer.`)) {
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

        // Ajuste masivo de precios
        function actualizarTodosPrecios() {
            window.location.href = 'ajuste_masivo.php';
        }

        // Cerrar modal al hacer clic fuera
        document.addEventListener('DOMContentLoaded', function() {
            document.getElementById('precioModal').addEventListener('click', function(e) {
                if (e.target === this) {
                    cerrarPrecioModal();
                }
            });
        });
    </script>
</body>
</html>