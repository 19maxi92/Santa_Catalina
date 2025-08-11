<?php
require_once '../../config.php';
requireLogin();

$pdo = getConnection();

$producto_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$producto_id) {
    header('Location: index.php');
    exit;
}

// Obtener producto
$stmt = $pdo->prepare("SELECT * FROM productos WHERE id = ?");
$stmt->execute([$producto_id]);
$producto = $stmt->fetch();

if (!$producto) {
    header('Location: index.php');
    exit;
}

$mensaje = '';
$error = '';

// Procesar formulario
if ($_POST) {
    try {
        $nombre = sanitize($_POST['nombre']);
        $descripcion = sanitize($_POST['descripcion']);
        $categoria = sanitize($_POST['categoria']);
        $precio_efectivo = (float)$_POST['precio_efectivo'];
        $precio_transferencia = (float)$_POST['precio_transferencia'];
        $orden_mostrar = (int)$_POST['orden_mostrar'];
        $activo = isset($_POST['activo']) ? 1 : 0;
        $motivo_cambio = sanitize($_POST['motivo_cambio']);

        // Validaciones
        if (empty($nombre)) {
            throw new Exception('El nombre del producto es obligatorio');
        }

        if ($precio_efectivo <= 0 || $precio_transferencia <= 0) {
            throw new Exception('Los precios deben ser mayores a 0');
        }

        if ($precio_efectivo >= $precio_transferencia) {
            throw new Exception('El precio en efectivo debe ser menor al precio por transferencia');
        }

        // Verificar si cambió el nombre y no conflicta
        if ($nombre !== $producto['nombre']) {
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM productos WHERE nombre = ? AND id != ?");
            $stmt->execute([$nombre, $producto_id]);
            if ($stmt->fetchColumn() > 0) {
                throw new Exception('Ya existe otro producto con ese nombre');
            }
        }

        // Guardar historial si cambiaron los precios
        if ($precio_efectivo != $producto['precio_efectivo'] || $precio_transferencia != $producto['precio_transferencia']) {
            $stmt = $pdo->prepare("
                INSERT INTO historial_precios 
                (producto_id, tipo, precio_anterior_efectivo, precio_anterior_transferencia, 
                 precio_nuevo_efectivo, precio_nuevo_transferencia, motivo, usuario, fecha_cambio) 
                VALUES (?, 'producto', ?, ?, ?, ?, ?, ?, NOW())
            ");
            $stmt->execute([
                $producto_id,
                $producto['precio_efectivo'],
                $producto['precio_transferencia'],
                $precio_efectivo,
                $precio_transferencia,
                $motivo_cambio ?: 'Edición individual de producto',
                $_SESSION['admin_user']
            ]);
        }

        // Actualizar producto
        $stmt = $pdo->prepare("
            UPDATE productos 
            SET nombre = ?, descripcion = ?, categoria = ?, precio_efectivo = ?, precio_transferencia = ?, 
                orden_mostrar = ?, activo = ?, updated_by = ?, updated_at = NOW() 
            WHERE id = ?
        ");
        
        $stmt->execute([
            $nombre, $descripcion, $categoria, $precio_efectivo, $precio_transferencia,
            $orden_mostrar, $activo, $_SESSION['admin_user'], $producto_id
        ]);

        $mensaje = 'Producto actualizado correctamente';
        
        // Recargar datos
        $stmt = $pdo->prepare("SELECT * FROM productos WHERE id = ?");
        $stmt->execute([$producto_id]);
        $producto = $stmt->fetch();

    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

// Obtener categorías existentes
$categorias_existentes = $pdo->query("
    SELECT DISTINCT categoria, COUNT(*) as cantidad 
    FROM productos 
    WHERE categoria IS NOT NULL 
    GROUP BY categoria 
    ORDER BY categoria
")->fetchAll();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Editar Producto - <?= APP_NAME ?></title>
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
                    <i class="fas fa-edit text-blue-500 mr-2"></i>Editar Producto
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
            <i class="fas fa-check-circle mr-2"></i><?= $mensaje ?>
        </div>
        <?php endif; ?>

        <?php if ($error): ?>
        <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
            <i class="fas fa-exclamation-circle mr-2"></i><?= $error ?>
        </div>
        <?php endif; ?>

        <!-- Info del producto -->
        <div class="bg-blue-50 border border-blue-200 rounded-lg p-4 mb-6">
            <h3 class="font-semibold text-blue-800 mb-2">
                <i class="fas fa-info-circle mr-2"></i>Editando: <?= htmlspecialchars($producto['nombre']) ?>
            </h3>
            <p class="text-blue-700 text-sm">
                Creado: <?= date('d/m/Y H:i', strtotime($producto['created_at'])) ?>
                <?php if ($producto['updated_at'] != $producto['created_at']): ?>
                    | Última modificación: <?= date('d/m/Y H:i', strtotime($producto['updated_at'])) ?>
                    <?php if ($producto['updated_by']): ?>
                        por <?= htmlspecialchars($producto['updated_by']) ?>
                    <?php endif; ?>
                <?php endif; ?>
            </p>
        </div>

        <!-- Formulario -->
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            
            <!-- Columna 1: Información Básica -->
            <div class="bg-white rounded-lg shadow p-6">
                <h3 class="text-lg font-semibold mb-4">
                    <i class="fas fa-info-circle text-blue-500 mr-2"></i>Información Básica
                </h3>
                
                <form method="POST" id="productoForm">
                    <div class="space-y-4">
                        <div>
                            <label class="block text-gray-700 mb-2 font-medium">
                                Nombre del Producto <span class="text-red-500">*</span>
                            </label>
                            <input type="text" name="nombre" value="<?= htmlspecialchars($producto['nombre']) ?>" 
                                   required maxlength="100"
                                   class="w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500">
                        </div>

                        <div>
                            <label class="block text-gray-700 mb-2 font-medium">Descripción</label>
                            <textarea name="descripcion" rows="3" maxlength="500"
                                      class="w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500"><?= htmlspecialchars($producto['descripcion'] ?: '') ?></textarea>
                        </div>

                        <div>
                            <label class="block text-gray-700 mb-2 font-medium">
                                Categoría <span class="text-red-500">*</span>
                            </label>
                            <div class="space-y-2">
                                <select name="categoria" id="categoria_select" 
                                        class="w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500">
                                    <?php foreach ($categorias_existentes as $cat): ?>
                                        <option value="<?= $cat['categoria'] ?>" 
                                                <?= $producto['categoria'] === $cat['categoria'] ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($cat['categoria']) ?> (<?= $cat['cantidad'] ?> productos)
                                        </option>
                                    <?php endforeach; ?>
                                    <option value="nueva">+ Crear nueva categoría</option>
                                </select>
                                
                                <input type="text" name="categoria_nueva" id="categoria_nueva" 
                                       class="w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500 hidden"
                                       placeholder="Nombre de la nueva categoría">
                            </div>
                        </div>

                        <div>
                            <label class="block text-gray-700 mb-2 font-medium">Orden de Aparición</label>
                            <input type="number" name="orden_mostrar" value="<?= $producto['orden_mostrar'] ?>" 
                                   min="0" step="10"
                                   class="w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500">
                        </div>

                        <div>
                            <label class="flex items-center">
                                <input type="checkbox" name="activo" value="1" 
                                       <?= $producto['activo'] ? 'checked' : '' ?>
                                       class="mr-2">
                                <span class="text-gray-700">Producto activo</span>
                            </label>
                        </div>
                    </div>
            </div>

            <!-- Columna 2: Precios -->
            <div class="bg-white rounded-lg shadow p-6">
                <h3 class="text-lg font-semibold mb-4">
                    <i class="fas fa-dollar-sign text-green-500 mr-2"></i>Actualizar Precios
                </h3>

                <div class="space-y-4">
                    <!-- Precios actuales -->
                    <div class="bg-gray-50 border border-gray-200 rounded-lg p-4">
                        <h4 class="font-medium text-gray-700 mb-2">Precios Actuales:</h4>
                        <div class="space-y-1 text-sm">
                            <div>Efectivo: <span class="font-bold text-green-600"><?= formatPrice($producto['precio_efectivo']) ?></span></div>
                            <div>Transfer: <span class="font-bold text-blue-600"><?= formatPrice($producto['precio_transferencia']) ?></span></div>
                        </div>
                    </div>

                    <div class="bg-green-50 border border-green-200 rounded-lg p-4">
                        <label class="block text-gray-700 mb-2 font-medium">
                            Nuevo Precio Efectivo <span class="text-red-500">*</span>
                        </label>
                        <div class="relative">
                            <span class="absolute left-3 top-2 text-gray-500">$</span>
                            <input type="number" name="precio_efectivo" 
                                   value="<?= $producto['precio_efectivo'] ?>" 
                                   required min="100" step="100" id="precio_efectivo"
                                   class="w-full pl-8 pr-4 py-2 border rounded-lg focus:ring-2 focus:ring-green-500">
                        </div>
                    </div>

                    <div class="bg-blue-50 border border-blue-200 rounded-lg p-4">
                        <label class="block text-gray-700 mb-2 font-medium">
                            Nuevo Precio Transferencia <span class="text-red-500">*</span>
                        </label>
                        <div class="relative">
                            <span class="absolute left-3 top-2 text-gray-500">$</span>
                            <input type="number" name="precio_transferencia" 
                                   value="<?= $producto['precio_transferencia'] ?>" 
                                   required min="100" step="100" id="precio_transferencia"
                                   class="w-full pl-8 pr-4 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500">
                        </div>
                    </div>

                    <!-- Motivo del cambio -->
                    <div>
                        <label class="block text-gray-700 mb-2 font-medium">Motivo del cambio</label>
                        <select name="motivo_cambio" class="w-full px-3 py-2 border rounded-lg">
                            <option value="">Sin motivo específico</option>
                            <option value="Ajuste por inflación">Ajuste por inflación</option>
                            <option value="Incremento de costos">Incremento de costos</option>
                            <option value="Promoción temporal">Promoción temporal</option>
                            <option value="Corrección de precio">Corrección de precio</option>
                            <option value="Actualización de lista">Actualización de lista</option>
                        </select>
                    </div>

                    <!-- Calculadora de diferencia -->
                    <div class="bg-gray-50 border border-gray-200 rounded-lg p-4">
                        <h4 class="font-medium text-gray-700 mb-2">
                            <i class="fas fa-calculator mr-1"></i>Diferencia de Precios
                        </h4>
                        <div class="space-y-2 text-sm" id="diferencias">
                            <div>Diferencia efectivo: <span id="diff_efectivo" class="font-medium">$0</span></div>
                            <div>Diferencia transfer: <span id="diff_transferencia" class="font-medium">$0</span></div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Columna 3: Vista Previa y Acciones -->
            <div class="bg-white rounded-lg shadow p-6">
                <h3 class="text-lg font-semibold mb-4">
                    <i class="fas fa-eye text-purple-500 mr-2"></i>Vista Previa
                </h3>

                <!-- Preview -->
                <div class="border-2 border-dashed border-gray-300 rounded-lg p-4 mb-6" id="producto_preview">
                    <div class="space-y-3">
                        <div class="flex justify-between items-start">
                            <h4 class="font-bold text-lg text-gray-800"><?= htmlspecialchars($producto['nombre']) ?></h4>
                            <span class="px-2 py-1 text-xs font-medium rounded-full bg-blue-100 text-blue-800">
                                <?= htmlspecialchars($producto['categoria'] ?: 'Sin categoría') ?>
                            </span>
                        </div>
                        
                        <div class="flex justify-between items-center">
                            <div>
                                <div class="text-lg font-bold text-green-600">
                                    Efectivo: <?= formatPrice($producto['precio_efectivo']) ?>
                                </div>
                                <div class="text-sm text-gray-600">
                                    Transfer: <?= formatPrice($producto['precio_transferencia']) ?>
                                </div>
                            </div>
                            <div class="text-<?= $producto['activo'] ? 'green' : 'red' ?>-600 font-medium text-sm">
                                <?= $producto['activo'] ? '✅ Activo' : '❌ Inactivo' ?>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Acciones -->
                <div class="space-y-3">
                    <button type="submit" class="w-full bg-blue-500 hover:bg-blue-600 text-white py-3 px-4 rounded-lg font-medium">
                        <i class="fas fa-save mr-2"></i>Guardar Cambios
                    </button>
                    
                    <a href="duplicar_producto.php?id=<?= $producto['id'] ?>" 
                       class="w-full bg-green-500 hover:bg-green-600 text-white py-2 px-4 rounded-lg text-center block">
                        <i class="fas fa-copy mr-2"></i>Duplicar Producto
                    </a>
                    
                    <a href="index.php" class="w-full bg-gray-500 hover:bg-gray-600 text-white py-2 px-4 rounded-lg text-center block">
                        <i class="fas fa-times mr-2"></i>Cancelar
                    </a>
                </div>

                <!-- Información adicional -->
                <div class="mt-6 pt-4 border-t">
                    <h4 class="font-medium text-gray-700 mb-3">
                        <i class="fas fa-info-circle mr-1"></i>Información del Producto
                    </h4>
                    <div class="space-y-2 text-sm text-gray-600">
                        <div>ID: #<?= $producto['id'] ?></div>
                        <div>Orden: <?= $producto['orden_mostrar'] ?></div>
                        <div>Estado: <?= $producto['activo'] ? 'Activo' : 'Inactivo' ?></div>
                        <?php if ($producto['updated_by']): ?>
                            <div>Modificado por: <?= htmlspecialchars($producto['updated_by']) ?></div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </form>
        </div>

        <!-- Historial de cambios del producto -->
        <?php
        $stmt = $pdo->prepare("
            SELECT * FROM historial_precios 
            WHERE producto_id = ? 
            ORDER BY fecha_cambio DESC 
            LIMIT 5
        ");
        $stmt->execute([$producto_id]);
        $historial_producto = $stmt->fetchAll();
        ?>

        <?php if (!empty($historial_producto)): ?>
        <div class="mt-8 bg-white rounded-lg shadow p-6">
            <h3 class="text-lg font-semibold text-gray-800 mb-4">
                <i class="fas fa-history text-gray-500 mr-2"></i>Últimos Cambios de Precio
            </h3>
            
            <div class="space-y-3">
                <?php foreach ($historial_producto as $cambio): ?>
                    <div class="border-l-4 border-blue-400 bg-blue-50 p-3 rounded">
                        <div class="flex justify-between items-start">
                            <div>
                                <div class="font-medium text-gray-800">
                                    <?= date('d/m/Y H:i', strtotime($cambio['fecha_cambio'])) ?>
                                </div>
                                <div class="text-sm text-gray-600">
                                    Por: <?= htmlspecialchars($cambio['usuario'] ?: 'Sistema') ?>
                                </div>
                                <?php if ($cambio['motivo']): ?>
                                    <div class="text-sm text-gray-500 italic">
                                        <?= htmlspecialchars($cambio['motivo']) ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                            <div class="text-right text-sm">
                                <div>
                                    <span class="line-through text-red-500"><?= formatPrice($cambio['precio_anterior_efectivo']) ?></span>
                                    <span class="text-green-600">→ <?= formatPrice($cambio['precio_nuevo_efectivo']) ?></span>
                                </div>
                                <div>
                                    <span class="line-through text-red-500"><?= formatPrice($cambio['precio_anterior_transferencia']) ?></span>
                                    <span class="text-green-600">→ <?= formatPrice($cambio['precio_nuevo_transferencia']) ?></span>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
            
            <div class="mt-4 text-center">
                <a href="historial.php?producto_id=<?= $producto['id'] ?>" class="text-blue-600 hover:underline text-sm">
                    Ver historial completo →
                </a>
            </div>
        </div>
        <?php endif; ?>
    </main>

    <script>
        // Manejo de categorías
        document.getElementById('categoria_select').addEventListener('change', function() {
            const nuevaCategoria = document.getElementById('categoria_nueva');
            if (this.value === 'nueva') {
                nuevaCategoria.classList.remove('hidden');
                nuevaCategoria.required = true;
                nuevaCategoria.focus();
            } else {
                nuevaCategoria.classList.add('hidden');
                nuevaCategoria.required = false;
                nuevaCategoria.value = '';
            }
        });

        // Calcular diferencias de precios
        function calcularDiferencias() {
            const precioEfectivoActual = <?= $producto['precio_efectivo'] ?>;
            const precioTransferenciaActual = <?= $producto['precio_transferencia'] ?>;
            
            const nuevoEfectivo = parseFloat(document.getElementById('precio_efectivo').value) || 0;
            const nuevaTransferencia = parseFloat(document.getElementById('precio_transferencia').value) || 0;
            
            const diffEfectivo = nuevoEfectivo - precioEfectivoActual;
            const diffTransferencia = nuevaTransferencia - precioTransferenciaActual;
            
            const diffEfectivoElement = document.getElementById('diff_efectivo');
            const diffTransferenciaElement = document.getElementById('diff_transferencia');
            
            // Formatear y colorear diferencias
            diffEfectivoElement.textContent = (diffEfectivo >= 0 ? '+' : '') + diffEfectivo.toLocaleString();
            diffEfectivoElement.className = 'font-medium ' + (diffEfectivo > 0 ? 'text-green-600' : diffEfectivo < 0 ? 'text-red-600' : 'text-gray-600');
            
            diffTransferenciaElement.textContent = (diffTransferencia >= 0 ? '+' : '') + diffTransferencia.toLocaleString();
            diffTransferenciaElement.className = 'font-medium ' + (diffTransferencia > 0 ? 'text-green-600' : diffTransferencia < 0 ? 'text-red-600' : 'text-gray-600');
        }

        // Event listeners
        document.getElementById('precio_efectivo').addEventListener('input', calcularDiferencias);
        document.getElementById('precio_transferencia').addEventListener('input', calcularDiferencias);

        // Validaciones
        document.getElementById('productoForm').addEventListener('submit', function(e) {
            const efectivo = parseFloat(document.getElementById('precio_efectivo').value) || 0;
            const transferencia = parseFloat(document.getElementById('precio_transferencia').value) || 0;
            
            if (efectivo >= transferencia) {
                e.preventDefault();
                alert('El precio en efectivo debe ser menor al precio por transferencia.');
                return;
            }

            const categoria = document.querySelector('select[name="categoria"]').value;
            const categoriaNueva = document.getElementById('categoria_nueva').value;
            
            if (categoria === 'nueva' && !categoriaNueva.trim()) {
                e.preventDefault();
                alert('Ingrese el nombre de la nueva categoría.');
                document.getElementById('categoria_nueva').focus();
                return;
            }

            // Si es categoría nueva, usar ese valor
            if (categoria === 'nueva' && categoriaNueva.trim()) {
                document.querySelector('select[name="categoria"]').value = categoriaNueva.trim();
            }
        });

        // Inicializar cálculos
        calcularDiferencias();
    </script>
</body>
</html>