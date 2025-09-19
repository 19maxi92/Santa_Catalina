<?php
/**
 * Módulo de Productos Simplificado
 * admin/modules/productos/index.php - REEMPLAZAR EL ACTUAL
 * Solo para actualizar precios de manera simple y rápida
 */

require_once '../../config.php';
requireLogin();

$pdo = getConnection();

$mensaje = '';
$error = '';

// Procesar actualización masiva
if ($_POST && isset($_POST['actualizar_masivo'])) {
    try {
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
                    // Actualizar precio
                    $stmt = $pdo->prepare("UPDATE productos SET precio_efectivo = ?, precio_transferencia = ?, updated_at = NOW(), updated_by = ? WHERE id = ?");
                    $stmt->execute([$precio_efectivo, $precio_transferencia, $_SESSION['admin_user'] ?? 'admin', $id]);
                    
                    // Guardar en historial si cambió
                    if ($producto_anterior['precio_efectivo'] != $precio_efectivo || $producto_anterior['precio_transferencia'] != $precio_transferencia) {
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
                            'Actualización masiva',
                            $_SESSION['admin_user'] ?? 'admin'
                        ]);
                    }
                    
                    $productos_actualizados++;
                }
            }
        }
        
        $mensaje = "✅ $productos_actualizados productos actualizados correctamente";
        
    } catch (Exception $e) {
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
            INSERT INTO productos (nombre, precio_efectivo, precio_transferencia, categoria, activo, created_at, updated_at, updated_by) 
            VALUES (?, ?, ?, ?, 1, NOW(), NOW(), ?)
        ");
        $stmt->execute([$nombre, $precio_efectivo, $precio_transferencia, $categoria, $_SESSION['admin_user'] ?? 'admin']);
        
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

// Obtener todos los productos
$productos = $pdo->query("
    SELECT id, nombre, precio_efectivo, precio_transferencia, categoria, activo, updated_at 
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

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión de Precios - <?= APP_NAME ?></title>
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
                    <i class="fas fa-tags text-green-500 mr-2"></i>Gestión de Precios
                </h1>
            </div>
            <a href="../../logout.php" class="bg-red-500 hover:bg-red-600 text-white px-3 py-2 rounded">
                <i class="fas fa-sign-out-alt mr-1"></i>Salir
            </a>
        </div>
    </header>

    <main class="container mx-auto px-4 py-6">
        <!-- Mensajes -->
        <?php if ($mensaje): ?>
        <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4">
            <?= $mensaje ?>
        </div>
        <?php endif; ?>

        <?php if ($error): ?>
        <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
            <?= $error ?>
        </div>
        <?php endif; ?>

        <!-- Crear nuevo producto -->
        <div class="bg-white rounded-lg shadow p-6 mb-6">
            <h3 class="text-lg font-semibold mb-4">
                <i class="fas fa-plus text-blue-500 mr-2"></i>Crear Nuevo Producto
            </h3>
            
            <form method="POST" class="grid grid-cols-1 md:grid-cols-5 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Nombre</label>
                    <input type="text" name="nombre_nuevo" required 
                           placeholder="Ej: 24 Jamón y Queso"
                           class="w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500">
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Precio Efectivo</label>
                    <input type="number" name="precio_efectivo_nuevo" required min="0" step="0.01"
                           placeholder="12500"
                           class="w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500">
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Precio Transferencia</label>
                    <input type="number" name="precio_transferencia_nuevo" required min="0" step="0.01"
                           placeholder="12500"
                           class="w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500">
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Categoría</label>
                    <select name="categoria_nueva" class="w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500">
                        <option value="Clásicos">Clásicos</option>
                        <option value="Especiales">Especiales</option>
                        <option value="Premium">Premium</option>
                        <option value="Elegidos">Elegidos</option>
                        <?php foreach ($categorias as $cat): ?>
                            <option value="<?= htmlspecialchars($cat) ?>"><?= htmlspecialchars($cat) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="flex items-end">
                    <button type="submit" name="crear_producto" 
                            class="w-full bg-blue-500 hover:bg-blue-600 text-white px-4 py-2 rounded-lg font-medium">
                        <i class="fas fa-plus mr-1"></i>Crear
                    </button>
                </div>
            </form>
        </div>

        <!-- Lista de productos -->
        <div class="bg-white rounded-lg shadow overflow-hidden">
            <div class="bg-gray-50 px-6 py-4 border-b">
                <h3 class="text-lg font-semibold flex items-center justify-between">
                    <span><i class="fas fa-list text-green-500 mr-2"></i>Productos Actuales (<?= count($productos) ?>)</span>
                    <span class="text-sm text-gray-600">Última actualización: <?= date('d/m/Y H:i') ?></span>
                </h3>
            </div>

            <form method="POST" id="formActualizacion">
                <div class="overflow-x-auto">
                    <table class="min-w-full">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Estado</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Producto</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Precio Efectivo</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Precio Transferencia</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Diferencia</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Acciones</th>
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
                                <tr class="bg-blue-50">
                                    <td colspan="6" class="px-6 py-2 text-sm font-semibold text-blue-800 uppercase">
                                        📁 <?= htmlspecialchars($categoria_actual ?: 'Sin categoría') ?>
                                    </td>
                                </tr>
                            <?php endif; ?>
                            
                            <tr class="<?= $producto['activo'] ? 'hover:bg-gray-50' : 'bg-gray-100 opacity-75' ?>">
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <form method="POST" class="inline">
                                        <input type="hidden" name="producto_id" value="<?= $producto['id'] ?>">
                                        <input type="hidden" name="nuevo_estado" value="<?= $producto['activo'] ? 0 : 1 ?>">
                                        <button type="submit" name="toggle_activo" 
                                                class="<?= $producto['activo'] ? 'text-green-600 hover:text-red-600' : 'text-red-600 hover:text-green-600' ?> text-lg"
                                                title="<?= $producto['activo'] ? 'Desactivar' : 'Activar' ?>">
                                            <i class="fas <?= $producto['activo'] ? 'fa-toggle-on' : 'fa-toggle-off' ?>"></i>
                                        </button>
                                    </form>
                                </td>
                                
                                <td class="px-6 py-4">
                                    <div class="font-medium text-gray-900"><?= htmlspecialchars($producto['nombre']) ?></div>
                                    <div class="text-sm text-gray-500">ID: <?= $producto['id'] ?></div>
                                </td>
                                
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <input type="number" 
                                           name="productos[<?= $producto['id'] ?>][precio_efectivo]" 
                                           value="<?= $producto['precio_efectivo'] ?>"
                                           min="0" step="0.01"
                                           class="w-24 px-2 py-1 border rounded text-center focus:ring-2 focus:ring-green-500"
                                           <?= $producto['activo'] ? '' : 'disabled' ?>>
                                </td>
                                
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <input type="number" 
                                           name="productos[<?= $producto['id'] ?>][precio_transferencia]" 
                                           value="<?= $producto['precio_transferencia'] ?>"
                                           min="0" step="0.01"
                                           class="w-24 px-2 py-1 border rounded text-center focus:ring-2 focus:ring-green-500"
                                           <?= $producto['activo'] ? '' : 'disabled' ?>>
                                </td>
                                
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span class="<?= $diferencia > 0 ? 'text-green-600' : ($diferencia < 0 ? 'text-red-600' : 'text-gray-600') ?>">
                                        $<?= number_format(abs($diferencia)) ?>
                                        <?php if ($porcentaje_diferencia != 0): ?>
                                            <br><span class="text-xs">(<?= number_format($porcentaje_diferencia, 1) ?>%)</span>
                                        <?php endif; ?>
                                    </span>
                                </td>
                                
                                <td class="px-6 py-4 whitespace-nowrap text-sm">
                                    <span class="text-gray-500">
                                        <?= date('d/m H:i', strtotime($producto['updated_at'])) ?>
                                    </span>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                
                <div class="bg-gray-50 px-6 py-4 border-t">
                    <div class="flex justify-between items-center">
                        <div class="text-sm text-gray-600">
                            <i class="fas fa-info-circle mr-1"></i>
                            Los cambios se guardan en el historial automáticamente
                        </div>
                        <button type="submit" name="actualizar_masivo" 
                                class="bg-green-500 hover:bg-green-600 text-white px-6 py-2 rounded-lg font-medium"
                                onclick="return confirm('¿Actualizar todos los precios modificados?')">
                            <i class="fas fa-save mr-2"></i>Actualizar Precios
                        </button>
                    </div>
                </div>
            </form>
        </div>

        <!-- Acciones rápidas -->
        <div class="mt-6 grid grid-cols-1 md:grid-cols-3 gap-4">
            <a href="historial.php" class="bg-blue-500 hover:bg-blue-600 text-white p-4 rounded-lg text-center">
                <i class="fas fa-history text-2xl mb-2"></i>
                <div class="font-medium">Ver Historial</div>
                <div class="text-sm opacity-75">Cambios de precios</div>
            </a>
            
            <button onclick="aplicarAumentoMasivo()" 
                    class="bg-yellow-500 hover:bg-yellow-600 text-white p-4 rounded-lg text-center">
                <i class="fas fa-percentage text-2xl mb-2"></i>
                <div class="font-medium">Aumento Masivo</div>
                <div class="text-sm opacity-75">Aplicar % a todos</div>
            </button>
            
            <a href="../pedidos/crear_pedido.php" class="bg-green-500 hover:bg-green-600 text-white p-4 rounded-lg text-center">
                <i class="fas fa-plus-circle text-2xl mb-2"></i>
                <div class="font-medium">Crear Pedido</div>
                <div class="text-sm opacity-75">Con precios actuales</div>
            </a>
        </div>
    </main>

    <script>
        function aplicarAumentoMasivo() {
            const porcentaje = prompt('¿Qué porcentaje de aumento aplicar?\nEjemplo: 10 para aumentar 10%');
            
            if (porcentaje && !isNaN(porcentaje)) {
                const aumento = parseFloat(porcentaje) / 100;
                
                if (confirm(`¿Aplicar ${porcentaje}% de aumento a todos los productos activos?`)) {
                    document.querySelectorAll('input[name*="precio_efectivo"]').forEach(input => {
                        if (!input.disabled) {
                            const valorActual = parseFloat(input.value) || 0;
                            input.value = Math.round(valorActual * (1 + aumento));
                        }
                    });
                    
                    document.querySelectorAll('input[name*="precio_transferencia"]').forEach(input => {
                        if (!input.disabled) {
                            const valorActual = parseFloat(input.value) || 0;
                            input.value = Math.round(valorActual * (1 + aumento));
                        }
                    });
                    
                    alert('✅ Aumento aplicado. ¡No olvides hacer click en "Actualizar Precios"!');
                }
            }
        }

        // Resaltar campos modificados
        document.addEventListener('DOMContentLoaded', function() {
            const inputs = document.querySelectorAll('input[type="number"]');
            
            inputs.forEach(input => {
                const valorOriginal = input.value;
                
                input.addEventListener('input', function() {
                    if (this.value != valorOriginal) {
                        this.style.backgroundColor = '#fef3c7'; // yellow-100
                        this.style.borderColor = '#f59e0b'; // yellow-500
                    } else {
                        this.style.backgroundColor = '';
                        this.style.borderColor = '';
                    }
                });
            });
        });
    </script>
</body>
</html>