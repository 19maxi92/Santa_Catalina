<?php
require_once '../../config.php';
requireLogin();

$pdo = getConnection();

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

        // Validaciones
        if (empty($nombre)) {
            throw new Exception('El nombre del producto es obligatorio');
        }

        if ($precio_efectivo <= 0 || $precio_transferencia <= 0) {
            throw new Exception('Los precios deben ser mayores a 0');
        }

        if ($precio_efectivo >= $precio_transferencia) {
            throw new Exception('El precio en efectivo debe ser menor al precio por transferencia (descuento)');
        }

        // Verificar que no existe otro producto con el mismo nombre
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM productos WHERE nombre = ?");
        $stmt->execute([$nombre]);
        if ($stmt->fetchColumn() > 0) {
            throw new Exception('Ya existe un producto con ese nombre');
        }

        // Insertar producto
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

        $mensaje = 'Producto creado correctamente';
        
        // Limpiar formulario
        $_POST = [];

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

// Obtener último orden usado
$ultimo_orden = $pdo->query("SELECT MAX(orden_mostrar) FROM productos")->fetchColumn() + 10;
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Crear Producto - <?= APP_NAME ?></title>
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
                    <i class="fas fa-plus-circle text-green-500 mr-2"></i>Crear Nuevo Producto
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
            <div class="mt-2">
                <a href="index.php" class="text-green-800 underline">Ver lista de productos</a> |
                <a href="?" class="text-green-800 underline">Crear otro producto</a>
            </div>
        </div>
        <?php endif; ?>

        <?php if ($error): ?>
        <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
            <i class="fas fa-exclamation-circle mr-2"></i><?= $error ?>
        </div>
        <?php endif; ?>

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
                            <input type="text" name="nombre" value="<?= htmlspecialchars($_POST['nombre'] ?? '') ?>" 
                                   required maxlength="100"
                                   class="w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-green-500"
                                   placeholder="Ej: 24 Especial Verano">
                            <div class="text-xs text-gray-500 mt-1">
                                Este nombre aparecerá en los pedidos y será visible para los clientes
                            </div>
                        </div>

                        <div>
                            <label class="block text-gray-700 mb-2 font-medium">Descripción</label>
                            <textarea name="descripcion" rows="3" maxlength="500"
                                      class="w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-green-500"
                                      placeholder="Describe los ingredientes, sabores o características especiales..."><?= htmlspecialchars($_POST['descripcion'] ?? '') ?></textarea>
                            <div class="text-xs text-gray-500 mt-1">
                                Ayuda al equipo a conocer mejor el producto
                            </div>
                        </div>

                        <div>
                            <label class="block text-gray-700 mb-2 font-medium">
                                Categoría <span class="text-red-500">*</span>
                            </label>
                            <div class="space-y-2">
                                <select name="categoria" id="categoria_select" 
                                        class="w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-green-500">
                                    <option value="">Seleccionar categoría...</option>
                                    <?php foreach ($categorias_existentes as $cat): ?>
                                        <option value="<?= $cat['categoria'] ?>" 
                                                <?= (isset($_POST['categoria']) && $_POST['categoria'] === $cat['categoria']) ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($cat['categoria']) ?> (<?= $cat['cantidad'] ?> productos)
                                        </option>
                                    <?php endforeach; ?>
                                    <option value="nueva">+ Crear nueva categoría</option>
                                </select>
                                
                                <input type="text" name="categoria_nueva" id="categoria_nueva" 
                                       class="w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-green-500 hidden"
                                       placeholder="Nombre de la nueva categoría">
                            </div>
                        </div>

                        <div>
                            <label class="block text-gray-700 mb-2 font-medium">Orden de Aparición</label>
                            <input type="number" name="orden_mostrar" value="<?= $_POST['orden_mostrar'] ?? $ultimo_orden ?>" 
                                   min="0" step="10"
                                   class="w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-green-500">
                            <div class="text-xs text-gray-500 mt-1">
                                Número menor = aparece primero. Sugerido: <?= $ultimo_orden ?>
                            </div>
                        </div>

                        <div>
                            <label class="flex items-center">
                                <input type="checkbox" name="activo" value="1" 
                                       <?= (isset($_POST['activo']) || !isset($_POST['nombre'])) ? 'checked' : '' ?>
                                       class="mr-2">
                                <span class="text-gray-700">Producto activo (disponible para pedidos)</span>
                            </label>
                        </div>
                    </div>
            </div>

            <!-- Columna 2: Precios -->
            <div class="bg-white rounded-lg shadow p-6">
                <h3 class="text-lg font-semibold mb-4">
                    <i class="fas fa-dollar-sign text-green-500 mr-2"></i>Configuración de Precios
                </h3>

                <div class="space-y-4">
                    <div class="bg-green-50 border border-green-200 rounded-lg p-4">
                        <label class="block text-gray-700 mb-2 font-medium">
                            Precio Efectivo <span class="text-red-500">*</span>
                        </label>
                        <div class="relative">
                            <span class="absolute left-3 top-2 text-gray-500">$</span>
                            <input type="number" name="precio_efectivo" 
                                   value="<?= $_POST['precio_efectivo'] ?? '' ?>" 
                                   required min="100" step="100" id="precio_efectivo"
                                   class="w-full pl-8 pr-4 py-2 border rounded-lg focus:ring-2 focus:ring-green-500"
                                   placeholder="11000">
                        </div>
                        <div class="text-xs text-green-700 mt-1">
                            <i class="fas fa-info-circle mr-1"></i>
                            Precio con descuento por pago en efectivo
                        </div>
                    </div>

                    <div class="bg-blue-50 border border-blue-200 rounded-lg p-4">
                        <label class="block text-gray-700 mb-2 font-medium">
                            Precio Transferencia <span class="text-red-500">*</span>
                        </label>
                        <div class="relative">
                            <span class="absolute left-3 top-2 text-gray-500">$</span>
                            <input type="number" name="precio_transferencia" 
                                   value="<?= $_POST['precio_transferencia'] ?? '' ?>" 
                                   required min="100" step="100" id="precio_transferencia"
                                   class="w-full pl-8 pr-4 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500"
                                   placeholder="12000">
                        </div>
                        <div class="text-xs text-blue-700 mt-1">
                            <i class="fas fa-info-circle mr-1"></i>
                            Precio normal para transferencias y tarjetas
                        </div>
                    </div>

                    <!-- Calculadora de descuento -->
                    <div class="bg-gray-50 border border-gray-200 rounded-lg p-4">
                        <h4 class="font-medium text-gray-700 mb-2">
                            <i class="fas fa-calculator mr-1"></i>Calculadora de Descuento
                        </h4>
                        <div class="space-y-2">
                            <div class="flex justify-between text-sm">
                                <span>Descuento efectivo:</span>
                                <span id="descuento_pesos" class="font-medium">$0</span>
                            </div>
                            <div class="flex justify-between text-sm">
                                <span>Porcentaje descuento:</span>
                                <span id="descuento_porcentaje" class="font-medium">0%</span>
                            </div>
                        </div>
                        <div class="mt-3">
                            <button type="button" onclick="aplicarDescuentoComun(10)" 
                                    class="bg-gray-200 hover:bg-gray-300 px-3 py-1 rounded text-sm mr-2">10%</button>
                            <button type="button" onclick="aplicarDescuentoComun(15)" 
                                    class="bg-gray-200 hover:bg-gray-300 px-3 py-1 rounded text-sm mr-2">15%</button>
                            <button type="button" onclick="aplicarDescuentoComun(20)" 
                                    class="bg-gray-200 hover:bg-gray-300 px-3 py-1 rounded text-sm">20%</button>
                        </div>
                    </div>

                    <!-- Previsualización -->
                    <div class="bg-purple-50 border border-purple-200 rounded-lg p-4">
                        <h4 class="font-medium text-purple-700 mb-2">
                            <i class="fas fa-eye mr-1"></i>Cómo se verá al cliente
                        </h4>
                        <div class="text-sm space-y-1" id="preview_precios">
                            <div class="line-through text-gray-500">Precio normal: $0</div>
                            <div class="text-green-600 font-bold">Efectivo: $0</div>
                            <div class="text-xs text-green-600">¡Ahorrás $0!</div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Columna 3: Vista Previa y Acciones -->
            <div class="bg-white rounded-lg shadow p-6">
                <h3 class="text-lg font-semibold mb-4">
                    <i class="fas fa-eye text-purple-500 mr-2"></i>Vista Previa del Producto
                </h3>

                <!-- Card de preview -->
                <div class="border-2 border-dashed border-gray-300 rounded-lg p-4 mb-6" id="producto_preview">
                    <div class="text-center text-gray-400">
                        <i class="fas fa-image text-4xl mb-2"></i>
                        <div class="text-sm">Completa el formulario para ver la vista previa</div>
                    </div>
                </div>

                <!-- Acciones del formulario -->
                <div class="space-y-3">
                    <button type="submit" class="w-full bg-green-500 hover:bg-green-600 text-white py-3 px-4 rounded-lg font-medium">
                        <i class="fas fa-plus-circle mr-2"></i>Crear Producto
                    </button>
                    
                    <button type="button" onclick="previsualizarProducto()" 
                            class="w-full bg-blue-500 hover:bg-blue-600 text-white py-2 px-4 rounded-lg">
                        <i class="fas fa-eye mr-2"></i>Actualizar Vista Previa
                    </button>
                    
                    <a href="index.php" class="w-full bg-gray-500 hover:bg-gray-600 text-white py-2 px-4 rounded-lg text-center block">
                        <i class="fas fa-times mr-2"></i>Cancelar
                    </a>
                </div>

                <!-- Productos sugeridos -->
                <div class="mt-6 pt-4 border-t">
                    <h4 class="font-medium text-gray-700 mb-3">
                        <i class="fas fa-lightbulb mr-1"></i>Productos Populares
                    </h4>
                    <div class="space-y-2 text-sm">
                        <div class="flex justify-between">
                            <span>24 Jamón y Queso</span>
                            <button type="button" onclick="copiarProducto('24 Jamón y Queso', 'Clásicos', 11000, 12000)" 
                                    class="text-blue-600 hover:underline text-xs">Copiar</button>
                        </div>
                        <div class="flex justify-between">
                            <span>48 Premium</span>
                            <button type="button" onclick="copiarProducto('48 Premium', 'Premium', 42000, 44000)" 
                                    class="text-blue-600 hover:underline text-xs">Copiar</button>
                        </div>
                        <div class="flex justify-between">
                            <span>24 Surtidos</span>
                            <button type="button" onclick="copiarProducto('24 Surtidos', 'Surtidos', 11000, 12000)" 
                                    class="text-blue-600 hover:underline text-xs">Copiar</button>
                        </div>
                    </div>
                </div>
            </div>
        </form>
        </div>

        <!-- Tips para crear productos -->
        <div class="mt-8 bg-blue-50 border border-blue-200 rounded-lg p-6">
            <h3 class="font-semibold text-blue-800 mb-3">
                <i class="fas fa-lightbulb mr-2"></i>Tips para Crear Productos Efectivos
            </h3>
            <div class="grid md:grid-cols-2 gap-4 text-sm text-blue-700">
                <div>
                    <h4 class="font-medium mb-2">📝 Nombres Descriptivos</h4>
                    <ul class="space-y-1">
                        <li>• Incluye la cantidad (24, 48, etc.)</li>
                        <li>• Especifica el tipo (Premium, Clásico)</li>
                        <li>• Menciona lo especial (Temporada, Promo)</li>
                    </ul>
                </div>
                <div>
                    <h4 class="font-medium mb-2">💰 Precios Estratégicos</h4>
                    <ul class="space-y-1">
                        <li>• Efectivo: 10-15% menos que transferencia</li>
                        <li>• Usa números redondos (11000, no 10857)</li>
                        <li>• Considera costos de materia prima</li>
                    </ul>
                </div>
                <div>
                    <h4 class="font-medium mb-2">🏷️ Categorización</h4>
                    <ul class="space-y-1">
                        <li>• Clásicos: tradicionales jamón y queso</li>
                        <li>• Surtidos: variedad de sabores</li>
                        <li>• Premium: ingredientes gourmet</li>
                    </ul>
                </div>
                <div>
                    <h4 class="font-medium mb-2">📊 Orden de Aparición</h4>
                    <ul class="space-y-1">
                        <li>• Productos populares: orden bajo (1-10)</li>
                        <li>• Productos especiales: orden medio (11-50)</li>
                        <li>• Productos temporales: orden alto (51+)</li>
                    </ul>
                </div>
            </div>
        </div>
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

        // Calcular descuentos automáticamente
        function calcularDescuentos() {
            const efectivo = parseFloat(document.getElementById('precio_efectivo').value) || 0;
            const transferencia = parseFloat(document.getElementById('precio_transferencia').value) || 0;
            
            if (efectivo > 0 && transferencia > 0 && transferencia > efectivo) {
                const descuentoPesos = transferencia - efectivo;
                const descuentoPorcentaje = (descuentoPesos / transferencia * 100).toFixed(1);
                
                document.getElementById('descuento_pesos').textContent = ' + descuentoPesos.toLocaleString();
                document.getElementById('descuento_porcentaje').textContent = descuentoPorcentaje + '%';
                
                // Actualizar preview
                document.getElementById('preview_precios').innerHTML = `
                    <div class="line-through text-gray-500">Precio normal: ${transferencia.toLocaleString()}</div>
                    <div class="text-green-600 font-bold">Efectivo: ${efectivo.toLocaleString()}</div>
                    <div class="text-xs text-green-600">¡Ahorrás ${descuentoPesos.toLocaleString()}!</div>
                `;
            }
        }

        // Aplicar descuentos comunes
        function aplicarDescuentoComun(porcentaje) {
            const transferencia = parseFloat(document.getElementById('precio_transferencia').value) || 0;
            if (transferencia > 0) {
                const efectivo = Math.round(transferencia * (1 - porcentaje / 100) / 100) * 100;
                document.getElementById('precio_efectivo').value = efectivo;
                calcularDescuentos();
            }
        }

        // Event listeners para precios
        document.getElementById('precio_efectivo').addEventListener('input', calcularDescuentos);
        document.getElementById('precio_transferencia').addEventListener('input', calcularDescuentos);

        // Previsualizar producto
        function previsualizarProducto() {
            const nombre = document.querySelector('input[name="nombre"]').value;
            const descripcion = document.querySelector('textarea[name="descripcion"]').value;
            const categoria = document.querySelector('select[name="categoria"]').value;
            const efectivo = parseFloat(document.getElementById('precio_efectivo').value) || 0;
            const transferencia = parseFloat(document.getElementById('precio_transferencia').value) || 0;
            const activo = document.querySelector('input[name="activo"]').checked;

            if (!nombre) {
                alert('Ingrese un nombre para el producto');
                return;
            }

            const preview = document.getElementById('producto_preview');
            
            let estadoClass = activo ? 'bg-green-50 border-green-200' : 'bg-red-50 border-red-200';
            let estadoText = activo ? '✅ Activo' : '❌ Inactivo';
            let estadoColor = activo ? 'text-green-600' : 'text-red-600';

            let categoriaColor = 'bg-gray-100 text-gray-800';
            switch(categoria) {
                case 'Premium': categoriaColor = 'bg-purple-100 text-purple-800'; break;
                case 'Surtidos': categoriaColor = 'bg-blue-100 text-blue-800'; break;
                case 'Clásicos': categoriaColor = 'bg-green-100 text-green-800'; break;
            }

            preview.className = `border-2 rounded-lg p-4 mb-6 ${estadoClass}`;
            preview.innerHTML = `
                <div class="space-y-3">
                    <div class="flex justify-between items-start">
                        <h4 class="font-bold text-lg text-gray-800">${nombre}</h4>
                        <span class="px-2 py-1 text-xs font-medium rounded-full ${categoriaColor}">
                            ${categoria || 'Sin categoría'}
                        </span>
                    </div>
                    
                    ${descripcion ? `<p class="text-sm text-gray-600">${descripcion}</p>` : ''}
                    
                    <div class="flex justify-between items-center">
                        <div>
                            ${transferencia > efectivo && efectivo > 0 ? 
                                `<div class="text-sm text-gray-400 line-through">${transferencia.toLocaleString()}</div>` : ''
                            }
                            <div class="text-lg font-bold text-green-600">
                                Efectivo: ${efectivo.toLocaleString()}
                            </div>
                            <div class="text-sm text-gray-600">
                                Transfer: ${transferencia.toLocaleString()}
                            </div>
                        </div>
                        <div class="${estadoColor} font-medium text-sm">
                            ${estadoText}
                        </div>
                    </div>
                </div>
            `;
        }

        // Copiar datos de producto existente
        function copiarProducto(nombre, categoria, efectivo, transferencia) {
            document.querySelector('input[name="nombre"]').value = nombre;
            document.querySelector('select[name="categoria"]').value = categoria;
            document.getElementById('precio_efectivo').value = efectivo;
            document.getElementById('precio_transferencia').value = transferencia;
            
            calcularDescuentos();
            previsualizarProducto();
        }

        // Auto-previsualizar cuando cambian los campos
        document.addEventListener('DOMContentLoaded', function() {
            const campos = ['input[name="nombre"]', 'textarea[name="descripcion"]', 'select[name="categoria"]', 'input[name="activo"]'];
            campos.forEach(selector => {
                const elemento = document.querySelector(selector);
                if (elemento) {
                    elemento.addEventListener('input', previsualizarProducto);
                    elemento.addEventListener('change', previsualizarProducto);
                }
            });
            
            // Previsualizar inicial si hay datos
            if (document.querySelector('input[name="nombre"]').value) {
                previsualizarProducto();
            }
        });

        // Validaciones antes de enviar
        document.getElementById('productoForm').addEventListener('submit', function(e) {
            const efectivo = parseFloat(document.getElementById('precio_efectivo').value) || 0;
            const transferencia = parseFloat(document.getElementById('precio_transferencia').value) || 0;
            
            if (efectivo >= transferencia) {
                e.preventDefault();
                alert('El precio en efectivo debe ser menor al precio por transferencia para aplicar descuento.');
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
    </script>
</body>
</html>