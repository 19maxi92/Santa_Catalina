<?php
require_once '../../config.php';
requireLogin();

$pdo = getConnection();

$producto_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$producto_id) {
    header('Location: index.php');
    exit;
}

// Obtener producto original
$stmt = $pdo->prepare("SELECT * FROM productos WHERE id = ?");
$stmt->execute([$producto_id]);
$producto_original = $stmt->fetch();

if (!$producto_original) {
    header('Location: index.php');
    exit;
}

$mensaje = '';
$error = '';

// Si es POST, crear la copia
if ($_POST) {
    try {
        $nombre = sanitize($_POST['nombre']);
        $descripcion = sanitize($_POST['descripcion']);
        $categoria = sanitize($_POST['categoria']);
        $precio_efectivo = (float)$_POST['precio_efectivo'];
        $precio_transferencia = (float)$_POST['precio_transferencia'];
        $orden_mostrar = (int)$_POST['orden_mostrar'];
        $activo = isset($_POST['activo']) ? 1 : 0;

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

        // Verificar que no existe otro producto con el mismo nombre
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM productos WHERE nombre = ?");
        $stmt->execute([$nombre]);
        if ($stmt->fetchColumn() > 0) {
            throw new Exception('Ya existe un producto con ese nombre');
        }

        // Insertar producto duplicado
        $stmt = $pdo->prepare("
            INSERT INTO productos (nombre, descripcion, categoria, precio_efectivo, precio_transferencia, 
                                 orden_mostrar, activo, updated_by, created_at, updated_at) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
        ");
        
        $stmt->execute([
            $nombre,
            $descripcion,
            $categoria,
            $precio_efectivo,
            $precio_transferencia,
            $orden_mostrar,
            $activo,
            $_SESSION['admin_user']
        ]);

        $nuevo_id = $pdo->lastInsertId();
        
        header("Location: editar_producto.php?id=$nuevo_id&mensaje=Producto duplicado correctamente");
        exit;

    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

// Preparar datos para el formulario
$datos_formulario = [
    'nombre' => 'Copia de ' . $producto_original['nombre'],
    'descripcion' => $producto_original['descripcion'],
    'categoria' => $producto_original['categoria'],
    'precio_efectivo' => $producto_original['precio_efectivo'],
    'precio_transferencia' => $producto_original['precio_transferencia'],
    'orden_mostrar' => $producto_original['orden_mostrar'] + 10,
    'activo' => $producto_original['activo']
];

// Sobrescribir con datos del POST si hay error
if ($_POST) {
    $datos_formulario = [
        'nombre' => $_POST['nombre'],
        'descripcion' => $_POST['descripcion'],
        'categoria' => $_POST['categoria'],
        'precio_efectivo' => $_POST['precio_efectivo'],
        'precio_transferencia' => $_POST['precio_transferencia'],
        'orden_mostrar' => $_POST['orden_mostrar'],
        'activo' => isset($_POST['activo'])
    ];
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
    <title>Duplicar Producto - <?= APP_NAME ?></title>
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
                    <i class="fas fa-copy text-green-500 mr-2"></i>Duplicar Producto
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
        <?php if ($error): ?>
        <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
            <i class="fas fa-exclamation-circle mr-2"></i><?= $error ?>
        </div>
        <?php endif; ?>

        <!-- Info del producto original -->
        <div class="bg-green-50 border border-green-200 rounded-lg p-4 mb-6">
            <h3 class="font-semibold text-green-800 mb-2">
                <i class="fas fa-info-circle mr-2"></i>Duplicando: <?= htmlspecialchars($producto_original['nombre']) ?>
            </h3>
            <p class="text-green-700 text-sm">
                Precio actual: <?= formatPrice($producto_original['precio_efectivo']) ?> (efectivo) / <?= formatPrice($producto_original['precio_transferencia']) ?> (transferencia)
                <br>Categoría: <?= htmlspecialchars($producto_original['categoria'] ?: 'Sin categoría') ?>
            </p>
        </div>

        <!-- Formulario -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            
            <!-- Columna 1: Formulario -->
            <div class="bg-white rounded-lg shadow p-6">
                <h3 class="text-lg font-semibold mb-4">
                    <i class="fas fa-edit text-blue-500 mr-2"></i>Datos del Nuevo Producto
                </h3>
                
                <form method="POST" id="duplicarForm">
                    <div class="space-y-4">
                        <div>
                            <label class="block text-gray-700 mb-2 font-medium">
                                Nombre del Nuevo Producto <span class="text-red-500">*</span>
                            </label>
                            <input type="text" name="nombre" value="<?= htmlspecialchars($datos_formulario['nombre']) ?>" 
                                   required maxlength="100"
                                   class="w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-green-500">
                            <div class="text-xs text-gray-500 mt-1">
                                Cambia el nombre para diferenciarlo del original
                            </div>
                        </div>

                        <div>
                            <label class="block text-gray-700 mb-2 font-medium">Descripción</label>
                            <textarea name="descripcion" rows="3" maxlength="500"
                                      class="w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-green-500"
                                      placeholder="Describe las diferencias con el producto original..."><?= htmlspecialchars($datos_formulario['descripcion'] ?: '') ?></textarea>
                        </div>

                        <div>
                            <label class="block text-gray-700 mb-2 font-medium">Categoría</label>
                            <select name="categoria" class="w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-green-500">
                                <?php foreach ($categorias_existentes as $cat): ?>
                                    <option value="<?= $cat['categoria'] ?>" 
                                            <?= $datos_formulario['categoria'] === $cat['categoria'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($cat['categoria']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-gray-700 mb-2 font-medium">
                                    Precio Efectivo <span class="text-red-500">*</span>
                                </label>
                                <div class="relative">
                                    <span class="absolute left-3 top-2 text-gray-500">$</span>
                                    <input type="number" name="precio_efectivo" 
                                           value="<?= $datos_formulario['precio_efectivo'] ?>" 
                                           required min="100" step="100" id="precio_efectivo"
                                           class="w-full pl-8 pr-4 py-2 border rounded-lg focus:ring-2 focus:ring-green-500">
                                </div>
                            </div>
                            
                            <div>
                                <label class="block text-gray-700 mb-2 font-medium">
                                    Precio Transferencia <span class="text-red-500">*</span>
                                </label>
                                <div class="relative">
                                    <span class="absolute left-3 top-2 text-gray-500">$</span>
                                    <input type="number" name="precio_transferencia" 
                                           value="<?= $datos_formulario['precio_transferencia'] ?>" 
                                           required min="100" step="100" id="precio_transferencia"
                                           class="w-full pl-8 pr-4 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500">
                                </div>
                            </div>
                        </div>

                        <div>
                            <label class="block text-gray-700 mb-2 font-medium">Orden de Aparición</label>
                            <input type="number" name="orden_mostrar" value="<?= $datos_formulario['orden_mostrar'] ?>" 
                                   min="0" step="10"
                                   class="w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-green-500">
                            <div class="text-xs text-gray-500 mt-1">
                                Se sugiere <?= $datos_formulario['orden_mostrar'] ?> (después del original)
                            </div>
                        </div>

                        <div>
                            <label class="flex items-center">
                                <input type="checkbox" name="activo" value="1" 
                                       <?= $datos_formulario['activo'] ? 'checked' : '' ?>
                                       class="mr-2">
                                <span class="text-gray-700">Producto activo (disponible para pedidos)</span>
                            </label>
                        </div>

                        <div class="flex space-x-3 pt-4">
                            <button type="submit" class="flex-1 bg-green-500 hover:bg-green-600 text-white py-3 px-4 rounded-lg font-medium">
                                <i class="fas fa-copy mr-2"></i>Duplicar Producto
                            </button>
                            <a href="index.php" class="flex-1 bg-gray-500 hover:bg-gray-600 text-white py-3 px-4 rounded-lg text-center">
                                <i class="fas fa-times mr-2"></i>Cancelar
                            </a>
                        </div>
                    </div>
                </form>
            </div>

            <!-- Columna 2: Comparación y Vista Previa -->
            <div class="space-y-6">
                
                <!-- Producto Original -->
                <div class="bg-white rounded-lg shadow p-6">
                    <h3 class="text-lg font-semibold mb-4">
                        <i class="fas fa-box text-gray-500 mr-2"></i>Producto Original
                    </h3>
                    
                    <div class="bg-gray-50 rounded-lg p-4">
                        <div class="space-y-3">
                            <div>
                                <h4 class="font-bold text-gray-800"><?= htmlspecialchars($producto_original['nombre']) ?></h4>
                                <?php if ($producto_original['descripcion']): ?>
                                    <p class="text-sm text-gray-600"><?= htmlspecialchars($producto_original['descripcion']) ?></p>
                                <?php endif; ?>
                            </div>
                            
                            <div class="flex justify-between items-center">
                                <div>
                                    <div class="text-green-600 font-bold">
                                        Efectivo: <?= formatPrice($producto_original['precio_efectivo']) ?>
                                    </div>
                                    <div class="text-blue-600">
                                        Transfer: <?= formatPrice($producto_original['precio_transferencia']) ?>
                                    </div>
                                </div>
                                <div class="text-right text-sm text-gray-500">
                                    <div>Orden: <?= $producto_original['orden_mostrar'] ?></div>
                                    <div><?= $producto_original['activo'] ? '✅ Activo' : '❌ Inactivo' ?></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Vista Previa del Nuevo -->
                <div class="bg-white rounded-lg shadow p-6">
                    <h3 class="text-lg font-semibold mb-4">
                        <i class="fas fa-eye text-purple-500 mr-2"></i>Vista Previa del Nuevo
                    </h3>
                    
                    <div class="border-2 border-dashed border-purple-300 rounded-lg p-4" id="producto_preview">
                        <div class="text-center text-gray-400">
                            <i class="fas fa-box-open text-4xl mb-2"></i>
                            <div class="text-sm">Completa el formulario para ver la vista previa</div>
                        </div>
                    </div>
                </div>

                <!-- Consejos -->
                <div class="bg-blue-50 border border-blue-200 rounded-lg p-4">
                    <h4 class="font-semibold text-blue-800 mb-2">
                        <i class="fas fa-lightbulb mr-2"></i>Consejos para Duplicar
                    </h4>
                    <ul class="text-sm text-blue-700 space-y-1">
                        <li>• Cambia el nombre para evitar confusiones</li>
                        <li>• Ajusta los precios si es necesario</li>
                        <li>• Considera un orden de aparición diferente</li>
                        <li>• Agrega una descripción que explique las diferencias</li>
                        <li>• Revisa la categoría por si debe ir en otra</li>
                    </ul>
                </div>
            </div>
        </div>
    </main>

    <script>
        // Vista previa en tiempo real
        function actualizarPreview() {
            const nombre = document.querySelector('input[name="nombre"]').value || 'Nombre del producto';
            const descripcion = document.querySelector('textarea[name="descripcion"]').value;
            const categoria = document.querySelector('select[name="categoria"]').value;
            const efectivo = parseFloat(document.getElementById('precio_efectivo').value) || 0;
            const transferencia = parseFloat(document.getElementById('precio_transferencia').value) || 0;
            const activo = document.querySelector('input[name="activo"]').checked;
            const orden = document.querySelector('input[name="orden_mostrar"]').value;

            const preview = document.getElementById('producto_preview');
            
            let estadoClass = activo ? 'border-green-300 bg-green-50' : 'border-red-300 bg-red-50';
            let estadoText = activo ? '✅ Activo' : '❌ Inactivo';
            let estadoColor = activo ? 'text-green-600' : 'text-red-600';

            preview.className = `border-2 border-dashed rounded-lg p-4 ${estadoClass}`;
            preview.innerHTML = `
                <div class="space-y-3">
                    <div class="flex justify-between items-start">
                        <h4 class="font-bold text-lg text-gray-800">${nombre}</h4>
                        <span class="px-2 py-1 text-xs font-medium rounded-full bg-blue-100 text-blue-800">
                            ${categoria || 'Sin categoría'}
                        </span>
                    </div>
                    
                    ${descripcion ? `<p class="text-sm text-gray-600">${descripcion}</p>` : ''}
                    
                    <div class="flex justify-between items-center">
                        <div>
                            <div class="text-lg font-bold text-green-600">
                                Efectivo: ${efectivo.toLocaleString()}
                            </div>
                            <div class="text-sm text-blue-600">
                                Transfer: ${transferencia.toLocaleString()}
                            </div>
                        </div>
                        <div class="text-right text-sm">
                            <div class="${estadoColor} font-medium">${estadoText}</div>
                            <div class="text-gray-500">Orden: ${orden}</div>
                        </div>
                    </div>
                    
                    <div class="text-xs text-purple-600 bg-purple-100 p-2 rounded">
                        <i class="fas fa-info-circle mr-1"></i>
                        Nuevo producto basado en "${<?= htmlspecialchars($producto_original['nombre']) ?>}"
                    </div>
                </div>
            `;
        }

        // Event listeners para actualización en tiempo real
        document.addEventListener('DOMContentLoaded', function() {
            const campos = [
                'input[name="nombre"]', 
                'textarea[name="descripcion"]', 
                'select[name="categoria"]',
                'input[name="precio_efectivo"]',
                'input[name="precio_transferencia"]',
                'input[name="orden_mostrar"]',
                'input[name="activo"]'
            ];
            
            campos.forEach(selector => {
                const elemento = document.querySelector(selector);
                if (elemento) {
                    elemento.addEventListener('input', actualizarPreview);
                    elemento.addEventListener('change', actualizarPreview);
                }
            });
            
            // Previsualizar inicial
            actualizarPreview();
        });

        // Validaciones del formulario
        document.getElementById('duplicarForm').addEventListener('submit', function(e) {
            const efectivo = parseFloat(document.getElementById('precio_efectivo').value) || 0;
            const transferencia = parseFloat(document.getElementById('precio_transferencia').value) || 0;
            const nombre = document.querySelector('input[name="nombre"]').value.trim();
            
            if (!nombre) {
                e.preventDefault();
                alert('El nombre del producto es obligatorio.');
                return;
            }
            
            if (efectivo >= transferencia) {
                e.preventDefault();
                alert('El precio en efectivo debe ser menor al precio por transferencia.');
                return;
            }

            if (efectivo <= 0 || transferencia <= 0) {
                e.preventDefault();
                alert('Los precios deben ser mayores a 0.');
                return;
            }

            // Confirmar duplicación
            if (!confirm('¿Está seguro de crear este nuevo producto?\n\nNombre: ' + nombre + '\nEfectivo:  + efectivo.toLocaleString() + '\nTransferencia:  + transferencia.toLocaleString())) {
                e.preventDefault();
                return;
            }
        });

        // Botones de acción rápida para precios
        function aplicarDescuento(porcentaje) {
            const efectivoOriginal = <?= $producto_original['precio_efectivo'] ?>;
            const transferenciaOriginal = <?= $producto_original['precio_transferencia'] ?>;
            
            const nuevoEfectivo = Math.round(efectivoOriginal * (1 - porcentaje / 100) / 100) * 100;
            const nuevaTransferencia = Math.round(transferenciaOriginal * (1 - porcentaje / 100) / 100) * 100;
            
            document.getElementById('precio_efectivo').value = nuevoEfectivo;
            document.getElementById('precio_transferencia').value = nuevaTransferencia;
            
            actualizarPreview();
        }

        function aplicarIncremento(porcentaje) {
            const efectivoOriginal = <?= $producto_original['precio_efectivo'] ?>;
            const transferenciaOriginal = <?= $producto_original['precio_transferencia'] ?>;
            
            const nuevoEfectivo = Math.round(efectivoOriginal * (1 + porcentaje / 100) / 100) * 100;
            const nuevaTransferencia = Math.round(transferenciaOriginal * (1 + porcentaje / 100) / 100) * 100;
            
            document.getElementById('precio_efectivo').value = nuevoEfectivo;
            document.getElementById('precio_transferencia').value = nuevaTransferencia;
            
            actualizarPreview();
        }

        // Agregar botones de acción rápida si no existen
        document.addEventListener('DOMContentLoaded', function() {
            const preciosContainer = document.querySelector('.grid.grid-cols-2.gap-4');
            if (preciosContainer && !document.getElementById('acciones-rapidas')) {
                const accionesDiv = document.createElement('div');
                accionesDiv.id = 'acciones-rapidas';
                accionesDiv.className = 'col-span-2 bg-gray-50 p-3 rounded-lg';
                accionesDiv.innerHTML = `
                    <div class="text-sm font-medium text-gray-700 mb-2">Ajustes Rápidos de Precio:</div>
                    <div class="flex flex-wrap gap-2">
                        <button type="button" onclick="aplicarDescuento(10)" 
                                class="bg-red-100 text-red-800 px-2 py-1 rounded text-xs hover:bg-red-200">
                            -10%
                        </button>
                        <button type="button" onclick="aplicarDescuento(5)" 
                                class="bg-red-100 text-red-800 px-2 py-1 rounded text-xs hover:bg-red-200">
                            -5%
                        </button>
                        <button type="button" onclick="actualizarPreview()" 
                                class="bg-gray-100 text-gray-800 px-2 py-1 rounded text-xs hover:bg-gray-200">
                            Igual
                        </button>
                        <button type="button" onclick="aplicarIncremento(5)" 
                                class="bg-green-100 text-green-800 px-2 py-1 rounded text-xs hover:bg-green-200">
                            +5%
                        </button>
                        <button type="button" onclick="aplicarIncremento(10)" 
                                class="bg-green-100 text-green-800 px-2 py-1 rounded text-xs hover:bg-green-200">
                            +10%
                        </button>
                        <button type="button" onclick="aplicarIncremento(15)" 
                                class="bg-green-100 text-green-800 px-2 py-1 rounded text-xs hover:bg-green-200">
                            +15%
                        </button>
                    </div>
                `;
                
                preciosContainer.appendChild(accionesDiv);
            }
        });
    </script>
</body>
</html>