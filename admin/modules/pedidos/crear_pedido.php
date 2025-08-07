<?php
require_once '../../config.php';
requireLogin();

$pdo = getConnection();

// Obtener productos y precios
$productos = $pdo->query("SELECT * FROM productos WHERE activo = 1 ORDER BY nombre")->fetchAll();

// Obtener cliente si viene por parámetro
$cliente_seleccionado = null;
if (isset($_GET['cliente_id'])) {
    $stmt = $pdo->prepare("SELECT * FROM clientes_fijos WHERE id = ? AND activo = 1");
    $stmt->execute([$_GET['cliente_id']]);
    $cliente_seleccionado = $stmt->fetch();
}

// Sabores premium disponibles
$sabores_premium = [
    'Jamón y Queso', 'Ananá', 'Atún', 'Berenjena', 'Durazno', 
    'Jamón Crudo', 'Morrón', 'Palmito', 'Panceta', 'Pollo', 'Roquefort', 'Salame'
];

$mensaje = '';
$error = '';

// Procesar formulario
if ($_POST) {
    try {
        $nombre = sanitize($_POST['nombre']);
        $apellido = sanitize($_POST['apellido']);
        $telefono = sanitize($_POST['telefono']);
        $direccion = sanitize($_POST['direccion']);
        $modalidad = $_POST['modalidad'];
        $forma_pago = $_POST['forma_pago'];
        $observaciones = sanitize($_POST['observaciones']);
        
        // Validar campos obligatorios
        if (!$nombre || !$apellido || !$telefono || !$modalidad || !$forma_pago) {
            throw new Exception('Todos los campos obligatorios deben completarse');
        }
        
        // Procesar producto seleccionado
        if (isset($_POST['tipo_pedido'])) {
            $tipo = $_POST['tipo_pedido'];
            $producto = '';
            $cantidad = 0;
            $precio = 0;
            
            switch ($tipo) {
                case 'predefinido':
                    if (isset($_POST['producto_id']) && $_POST['producto_id']) {
                        $producto_id = (int)$_POST['producto_id'];
                        $stmt = $pdo->prepare("SELECT * FROM productos WHERE id = ?");
                        $stmt->execute([$producto_id]);
                        $prod_data = $stmt->fetch();
                        
                        if ($prod_data) {
                            $producto = $prod_data['nombre'];
                            $precio = ($forma_pago === 'Efectivo') ? $prod_data['precio_efectivo'] : $prod_data['precio_transferencia'];
                            $cantidad = (int)explode(' ', $producto)[0]; // Extraer cantidad del nombre
                            
                            // Si es premium, agregar sabores
                            if (strpos($producto, 'Premium') !== false) {
                                $sabores_seleccionados = $_POST['sabores_premium'] ?? [];
                                if (!empty($sabores_seleccionados)) {
                                    $observaciones .= "\nSabores: " . implode(', ', $sabores_seleccionados);
                                }
                            }
                        } else {
                            throw new Exception('Producto no encontrado');
                        }
                    } else {
                        throw new Exception('Debe seleccionar un producto');
                    }
                    break;
                    
                case 'personalizado':
                    $cant_personalizado = (int)($_POST['cantidad_personalizada'] ?? 0);
                    $sabores_personalizados = $_POST['sabores_personalizados'] ?? [];
                    $tipo_personalizado = $_POST['tipo_personalizado'] ?? 'comun';
                    
                    if ($cant_personalizado <= 0) {
                        throw new Exception('La cantidad debe ser mayor a 0');
                    }
                    
                    if (empty($sabores_personalizados)) {
                        throw new Exception('Debe seleccionar al menos un sabor');
                    }
                    
                    // Calcular planchas (cada 8 sándwiches = 1 plancha)
                    $planchas = ceil($cant_personalizado / 8);
                    
                    // Precio por plancha según tipo
                    $precio_plancha_base = ($tipo_personalizado === 'premium') ? 7000 : 3500;
                    
                    // Aplicar descuento por efectivo
                    $precio_plancha = ($forma_pago === 'Efectivo') ? $precio_plancha_base * 0.9 : $precio_plancha_base;
                    
                    $producto = "Personalizado " . ucfirst($tipo_personalizado) . " x$cant_personalizado ($planchas plancha" . ($planchas > 1 ? 's' : '') . ")";
                    $cantidad = $cant_personalizado;
                    $precio = $planchas * $precio_plancha;
                    
                    $observaciones .= "\nSabores personalizados: " . implode(', ', $sabores_personalizados);
                    break;
                    
                default:
                    throw new Exception('Tipo de pedido no válido');
            }
            
            // Validar que tengamos todos los datos necesarios
            if (empty($producto) || $cantidad <= 0 || $precio <= 0) {
                throw new Exception("Error en los datos del producto. Producto: '$producto', Cantidad: $cantidad, Precio: $precio");
            }
            
            // Insertar pedido
            $stmt = $pdo->prepare("
                INSERT INTO pedidos (nombre, apellido, telefono, direccion, producto, cantidad, precio, 
                                   forma_pago, modalidad, observaciones, cliente_fijo_id) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            
            $cliente_fijo_id = $cliente_seleccionado ? $cliente_seleccionado['id'] : null;
            
            $stmt->execute([
                $nombre, $apellido, $telefono, $direccion, $producto, $cantidad, 
                $precio, $forma_pago, $modalidad, $observaciones, $cliente_fijo_id
            ]);
            
            $mensaje = 'Pedido creado correctamente';
            
            // Limpiar formulario
            $_POST = [];
        } else {
            throw new Exception('Debe seleccionar un tipo de pedido');
        }
        
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Nuevo Pedido - <?= APP_NAME ?></title>
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
                    <i class="fas fa-plus-circle text-green-500 mr-2"></i>Nuevo Pedido
                </h1>
            </div>
        </div>
    </header>

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

        <form method="POST" id="pedidoForm">
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                
                <!-- Columna 1: Datos del Cliente -->
                <div class="bg-white rounded-lg shadow p-6">
                    <h3 class="text-lg font-semibold mb-4">
                        <i class="fas fa-user text-blue-500 mr-2"></i>Datos del Cliente
                    </h3>
                    
                    <div class="space-y-4">
                        <div>
                            <label class="block text-gray-700 mb-2">Nombre <span class="text-red-500">*</span></label>
                            <input type="text" name="nombre" value="<?= $cliente_seleccionado['nombre'] ?? ($_POST['nombre'] ?? '') ?>" required
                                   class="w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500">
                        </div>
                        
                        <div>
                            <label class="block text-gray-700 mb-2">Apellido <span class="text-red-500">*</span></label>
                            <input type="text" name="apellido" value="<?= $cliente_seleccionado['apellido'] ?? ($_POST['apellido'] ?? '') ?>" required
                                   class="w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500">
                        </div>
                        
                        <div>
                            <label class="block text-gray-700 mb-2">Teléfono <span class="text-red-500">*</span></label>
                            <input type="tel" name="telefono" value="<?= $cliente_seleccionado['telefono'] ?? ($_POST['telefono'] ?? '') ?>" required
                                   class="w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500">
                        </div>
                        
                        <div>
                            <label class="block text-gray-700 mb-2">Dirección</label>
                            <input type="text" name="direccion" value="<?= $cliente_seleccionado['direccion'] ?? ($_POST['direccion'] ?? '') ?>"
                                   class="w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500">
                        </div>
                        
                        <div>
                            <label class="block text-gray-700 mb-2">Modalidad <span class="text-red-500">*</span></label>
                            <div class="space-y-2">
                                <label class="flex items-center">
                                    <input type="radio" name="modalidad" value="Retira" required class="mr-2" 
                                           <?= (isset($_POST['modalidad']) && $_POST['modalidad'] === 'Retira') ? 'checked' : '' ?>>
                                    <i class="fas fa-store mr-2 text-blue-500"></i>Retira en Local
                                </label>
                                <label class="flex items-center">
                                    <input type="radio" name="modalidad" value="Delivery" required class="mr-2"
                                           <?= (isset($_POST['modalidad']) && $_POST['modalidad'] === 'Delivery') ? 'checked' : '' ?>>
                                    <i class="fas fa-truck mr-2 text-green-500"></i>Delivery
                                </label>
                            </div>
                        </div>
                        
                        <div>
                            <label class="block text-gray-700 mb-2">Forma de Pago <span class="text-red-500">*</span></label>
                            <div class="space-y-2">
                                <label class="flex items-center">
                                    <input type="radio" name="forma_pago" value="Efectivo" required class="mr-2" onchange="updatePrecios()"
                                           <?= (isset($_POST['forma_pago']) && $_POST['forma_pago'] === 'Efectivo') ? 'checked' : '' ?>>
                                    <i class="fas fa-money-bill mr-2 text-green-500"></i>Efectivo (con descuento)
                                </label>
                                <label class="flex items-center">
                                    <input type="radio" name="forma_pago" value="Transferencia" required class="mr-2" onchange="updatePrecios()"
                                           <?= (isset($_POST['forma_pago']) && $_POST['forma_pago'] === 'Transferencia') ? 'checked' : '' ?>>
                                    <i class="fas fa-credit-card mr-2 text-blue-500"></i>Transferencia
                                </label>
                            </div>
                        </div>
                        
                        <div>
                            <label class="block text-gray-700 mb-2">Observaciones</label>
                            <textarea name="observaciones" rows="3"
                                      class="w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500"
                                      placeholder="Observaciones adicionales..."><?= $cliente_seleccionado['observaciones'] ?? ($_POST['observaciones'] ?? '') ?></textarea>
                        </div>
                    </div>
                </div>

                <!-- Columna 2: Selección de Producto -->
                <div class="bg-white rounded-lg shadow p-6">
                    <h3 class="text-lg font-semibold mb-4">
                        <i class="fas fa-shopping-cart text-orange-500 mr-2"></i>Seleccionar Producto
                    </h3>
                    
                    <!-- Campo oculto para tipo de pedido -->
                    <input type="hidden" name="tipo_pedido" id="tipo_pedido_hidden" value="predefinido">
                    
                    <!-- Tabs -->
                    <div class="border-b mb-4">
                        <nav class="flex space-x-4">
                            <div class="tab-btn py-2 px-4 border-b-2 font-medium text-sm active cursor-pointer border-blue-500 text-blue-600"
                                    id="tab-predefinido" onclick="showTab('predefinido')">
                                Productos
                            </div>
                            <div class="tab-btn py-2 px-4 border-b-2 font-medium text-sm cursor-pointer border-transparent text-gray-500 hover:text-blue-600"
                                    id="tab-personalizado" onclick="showTab('personalizado')">
                                Personalizado
                            </div>
                        </nav>
                    </div>
                    
                    <!-- Tab Productos Predefinidos -->
                    <div id="content-predefinido" class="tab-content">
                        <div class="space-y-3 max-h-80 overflow-y-auto">
                            <?php foreach ($productos as $prod): ?>
                                <label class="flex items-center p-3 border rounded-lg hover:bg-gray-50 cursor-pointer">
                                    <input type="radio" name="producto_id" value="<?= $prod['id'] ?>" 
                                           class="mr-3" onchange="selectProduct(<?= $prod['id'] ?>, '<?= htmlspecialchars($prod['nombre']) ?>', <?= $prod['precio_efectivo'] ?>, <?= $prod['precio_transferencia'] ?>)">
                                    <div class="flex-1">
                                        <div class="font-medium"><?= $prod['nombre'] ?></div>
                                        <div class="text-sm text-gray-600">
                                            <span class="precio-efectivo">Efectivo: <?= formatPrice($prod['precio_efectivo']) ?></span> | 
                                            <span class="precio-transferencia">Transfer: <?= formatPrice($prod['precio_transferencia']) ?></span>
                                        </div>
                                    </div>
                                </label>
                            <?php endforeach; ?>
                        </div>
                        
                        <!-- Selección de sabores Premium -->
                        <div id="sabores-premium" class="mt-4 hidden">
                            <h4 class="font-medium mb-2">Seleccionar Sabores (<span id="sabores-cantidad">6</span>):</h4>
                            <div class="grid grid-cols-2 gap-2 max-h-40 overflow-y-auto">
                                <?php foreach ($sabores_premium as $sabor): ?>
                                    <label class="flex items-center text-sm">
                                        <input type="checkbox" name="sabores_premium[]" value="<?= $sabor ?>" class="mr-2 sabor-checkbox">
                                        <?= $sabor ?>
                                    </label>
                                <?php endforeach; ?>
                            </div>
                            <div class="text-sm text-gray-600 mt-2">
                                Podés repetir sabores. Seleccionados: <span id="sabores-seleccionados">0</span>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Tab Personalizado -->
                    <div id="content-personalizado" class="tab-content hidden">
                        <div class="space-y-4">
                            <!-- Tipo de sándwich -->
                            <div>
                                <label class="block text-gray-700 mb-2">Tipo de Sándwich:</label>
                                <div class="space-y-2">
                                    <label class="flex items-center">
                                        <input type="radio" name="tipo_personalizado" value="comun" checked class="mr-2" onchange="updatePersonalizado()">
                                        <div>
                                            <div class="font-medium">Común</div>
                                            <div class="text-sm text-gray-600">$3,500 por plancha (8 sándwiches)</div>
                                        </div>
                                    </label>
                                    <label class="flex items-center">
                                        <input type="radio" name="tipo_personalizado" value="premium" class="mr-2" onchange="updatePersonalizado()">
                                        <div>
                                            <div class="font-medium">Premium</div>
                                            <div class="text-sm text-gray-600">$7,000 por plancha (8 sándwiches)</div>
                                        </div>
                                    </label>
                                </div>
                            </div>
                            
                            <div>
                                <label class="block text-gray-700 mb-2">Cantidad de Sándwiches:</label>
                                <input type="number" name="cantidad_personalizada" min="1" max="200" value="8"
                                       class="w-full px-3 py-2 border rounded-lg" onchange="updatePersonalizado()">
                                <div class="text-sm text-gray-600 mt-1">
                                    Planchas: <span id="planchas-necesarias">1</span> | 
                                    Sabores permitidos: <span id="sabores-permitidos">1</span>
                                </div>
                            </div>
                            
                            <div>
                                <label class="block text-gray-700 mb-2">Sabores:</label>
                                <div class="grid grid-cols-2 gap-2 max-h-40 overflow-y-auto">
                                    <?php foreach ($sabores_premium as $sabor): ?>
                                        <label class="flex items-center text-sm">
                                            <input type="checkbox" name="sabores_personalizados[]" value="<?= $sabor ?>" class="mr-2 sabor-personalizado">
                                            <?= $sabor ?>
                                        </label>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                            
                            <div class="bg-blue-50 p-3 rounded">
                                <div class="text-sm space-y-1">
                                    <div>Tipo: <span id="tipo-mostrar">Común</span></div>
                                    <div>Precio por plancha: <span id="precio-plancha">$3,500</span></div>
                                    <div>Planchas necesarias: <span id="planchas-mostrar">1</span></div>
                                    <div class="font-medium text-lg">Total: <span id="total-personalizado">$3,500</span></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Columna 3: Resumen -->
                <div class="bg-white rounded-lg shadow p-6">
                    <h3 class="text-lg font-semibold mb-4">
                        <i class="fas fa-receipt text-purple-500 mr-2"></i>Resumen del Pedido
                    </h3>
                    
                    <div id="resumen-pedido" class="space-y-3">
                        <div class="text-gray-500 text-center py-8">
                            <i class="fas fa-shopping-cart text-4xl mb-2"></i>
                            <div>Seleccioná un producto para ver el resumen</div>
                        </div>
                    </div>
                    
                    <button type="submit" class="w-full bg-green-500 hover:bg-green-600 text-white py-3 px-4 rounded-lg font-medium mt-6">
                        <i class="fas fa-save mr-2"></i>Crear Pedido
                    </button>
                </div>
            </div>
        </form>
    </main>

    <script>
        let currentProduct = null;
        let paymentMethod = 'Efectivo';
        
        // Manejo de tabs - FUNCIÓN GLOBAL
        function showTab(tab) {
            console.log('Cambiando a tab:', tab);
            
            // Hide all tabs
            document.getElementById('content-predefinido').style.display = 'none';
            document.getElementById('content-personalizado').style.display = 'none';
            
            // Remove active classes
            document.getElementById('tab-predefinido').className = 'tab-btn py-2 px-4 border-b-2 font-medium text-sm cursor-pointer border-transparent text-gray-500 hover:text-blue-600';
            document.getElementById('tab-personalizado').className = 'tab-btn py-2 px-4 border-b-2 font-medium text-sm cursor-pointer border-transparent text-gray-500 hover:text-blue-600';
            
            // Show selected tab and activate button
            if (tab === 'predefinido') {
                document.getElementById('content-predefinido').style.display = 'block';
                document.getElementById('tab-predefinido').className = 'tab-btn py-2 px-4 border-b-2 font-medium text-sm active cursor-pointer border-blue-500 text-blue-600';
            } else {
                document.getElementById('content-personalizado').style.display = 'block';
                document.getElementById('tab-personalizado').className = 'tab-btn py-2 px-4 border-b-2 font-medium text-sm active cursor-pointer border-blue-500 text-blue-600';
            }
            
            // Update hidden input
            document.getElementById('tipo_pedido_hidden').value = tab;
            
            updateResumen();
        }
        
        // Seleccionar producto predefinido
        function selectProduct(id, name, precioEfectivo, precioTransferencia) {
            currentProduct = {
                id: id,
                name: name,
                precioEfectivo: precioEfectivo,
                precioTransferencia: precioTransferencia
            };
            
            // Mostrar sabores premium si es necesario
            const saboresDiv = document.getElementById('sabores-premium');
            if (name.includes('Premium')) {
                saboresDiv.classList.remove('hidden');
                const cantidad = name.includes('48') ? 6 : 3;
                document.getElementById('sabores-cantidad').textContent = cantidad;
                
                // Reset sabores
                document.querySelectorAll('.sabor-checkbox').forEach(cb => cb.checked = false);
                updateSaboresPremium();
            } else {
                saboresDiv.classList.add('hidden');
            }
            
            updateResumen();
        }
        
        // Actualizar precios según forma de pago
        function updatePrecios() {
            const formaPago = document.querySelector('input[name="forma_pago"]:checked')?.value;
            if (formaPago) {
                paymentMethod = formaPago;
                updateResumen();
                updatePersonalizado();
            }
        }
        
        // Actualizar sabores premium
        function updateSaboresPremium() {
            const maxSabores = parseInt(document.getElementById('sabores-cantidad').textContent);
            const selected = document.querySelectorAll('.sabor-checkbox:checked').length;
            
            document.getElementById('sabores-seleccionados').textContent = selected;
            
            // Disable checkboxes if limit reached
            document.querySelectorAll('.sabor-checkbox:not(:checked)').forEach(cb => {
                cb.disabled = selected >= maxSabores;
            });
        }
        
        // Actualizar personalizado
        function updatePersonalizado() {
            const cantidad = parseInt(document.querySelector('input[name="cantidad_personalizada"]').value) || 8;
            const tipo = document.querySelector('input[name="tipo_personalizado"]:checked').value;
            const planchas = Math.ceil(cantidad / 8);
            const saboresPermitidos = planchas; // 1 sabor por plancha
            
            // Precios base por plancha
            const preciosBase = {
                'comun': 3500,
                'premium': 7000
            };
            
            const precioBase = preciosBase[tipo];
            const descuento = paymentMethod === 'Efectivo' ? 0.9 : 1; // 10% desc efectivo
            const precioPlancha = Math.round(precioBase * descuento);
            const total = planchas * precioPlancha;
            
            // Actualizar interfaz
            document.getElementById('planchas-necesarias').textContent = planchas;
            document.getElementById('sabores-permitidos').textContent = saboresPermitidos;
            document.getElementById('tipo-mostrar').textContent = tipo.charAt(0).toUpperCase() + tipo.slice(1);
            document.getElementById('precio-plancha').textContent = `$${precioPlancha.toLocaleString()}`;
            document.getElementById('planchas-mostrar').textContent = planchas;
            document.getElementById('total-personalizado').textContent = `$${total.toLocaleString()}`;
            
            // Limit sabores selection
            const selectedSabores = document.querySelectorAll('.sabor-personalizado:checked').length;
            document.querySelectorAll('.sabor-personalizado:not(:checked)').forEach(cb => {
                cb.disabled = selectedSabores >= saboresPermitidos;
            });
            
            updateResumen();
        }
        
        // Actualizar resumen
        function updateResumen() {
            const resumenDiv = document.getElementById('resumen-pedido');
            const activeTab = document.getElementById('tipo_pedido_hidden').value;
            
            let html = '';
            
            if (activeTab === 'predefinido' && currentProduct) {
                const precio = paymentMethod === 'Efectivo' ? currentProduct.precioEfectivo : currentProduct.precioTransferencia;
                const descuento = paymentMethod === 'Efectivo' ? ' (con descuento)' : '';
                
                html = `
                    <div class="border-b pb-3">
                        <div class="font-medium">${currentProduct.name}</div>
                        <div class="text-sm text-gray-600">Pago: ${paymentMethod}${descuento}</div>
                    </div>
                    <div class="flex justify-between items-center font-bold text-lg">
                        <span>Total:</span>
                        <span class="text-green-600">$${precio.toLocaleString()}</span>
                    </div>
                `;
                
                if (currentProduct.name.includes('Premium')) {
                    const saboresSeleccionados = Array.from(document.querySelectorAll('.sabor-checkbox:checked'))
                        .map(cb => cb.value);
                    if (saboresSeleccionados.length > 0) {
                        html += `
                            <div class="mt-3 text-sm">
                                <div class="font-medium">Sabores:</div>
                                <div class="text-gray-600">${saboresSeleccionados.join(', ')}</div>
                        </div>
                    `;
                }
            } else {
                html = `
                    <div class="text-gray-500 text-center py-8">
                        <i class="fas fa-shopping-cart text-4xl mb-2"></i>
                        <div>Seleccioná un producto para ver el resumen</div>
                    </div>
                `;
            }
            
            resumenDiv.innerHTML = html;
        }
        
        // Event listeners
        document.addEventListener('DOMContentLoaded', function() {
            // Initialize tabs
            showTab('predefinido');
            
            // Sabores premium checkboxes
            document.querySelectorAll('.sabor-checkbox').forEach(cb => {
                cb.addEventListener('change', updateSaboresPremium);
            });
            
            // Sabores personalizados checkboxes
            document.querySelectorAll('.sabor-personalizado').forEach(cb => {
                cb.addEventListener('change', updatePersonalizado);
            });
            
            // Tipo personalizado radio buttons
            document.querySelectorAll('input[name="tipo_personalizado"]').forEach(radio => {
                radio.addEventListener('change', updatePersonalizado);
            });
            
            // Cantidad personalizada input
            const cantidadInput = document.querySelector('input[name="cantidad_personalizada"]');
            if (cantidadInput) {
                cantidadInput.addEventListener('input', updatePersonalizado);
            }
            
            // Payment method change
            document.querySelectorAll('input[name="forma_pago"]').forEach(radio => {
                radio.addEventListener('change', updatePrecios);
            });
            
            // Initialize payment method
            const selectedPayment = document.querySelector('input[name="forma_pago"]:checked');
            if (selectedPayment) {
                paymentMethod = selectedPayment.value;
            }
            
            // Initialize personalizado calculations
            updatePersonalizado();
        });
        
        // Validación antes de enviar
        document.getElementById('pedidoForm').addEventListener('submit', function(e) {
            const tipoePedido = document.getElementById('tipo_pedido_hidden').value;
            
            if (tipoePedido === 'predefinido') {
                const productoSeleccionado = document.querySelector('input[name="producto_id"]:checked');
                if (!productoSeleccionado) {
                    e.preventDefault();
                    alert('Por favor selecciona un producto.');
                    return false;
                }
            } else if (tipoePedido === 'personalizado') {
                const cantidad = parseInt(document.querySelector('input[name="cantidad_personalizada"]').value);
                const saboresSeleccionados = document.querySelectorAll('.sabor-personalizado:checked').length;
                
                if (cantidad <= 0) {
                    e.preventDefault();
                    alert('La cantidad debe ser mayor a 0.');
                    return false;
                }
                
                if (saboresSeleccionados === 0) {
                    e.preventDefault();
                    alert('Debes seleccionar al menos un sabor.');
                    return false;
                }
            }
            
            return true;
        });
    </script>

    <style>
        .tab-btn.active {
            border-bottom-color: #3b82f6 !important;
            color: #3b82f6 !important;
        }
        
        .tab-btn {
            border-bottom-color: transparent;
        }
        
        /* Estilos para radio buttons y checkboxes */
        input[type="radio"]:checked {
            accent-color: #3b82f6;
        }
        
        input[type="checkbox"]:checked {
            accent-color: #10b981;
        }
        
        /* Hover effects */
        .tab-btn:hover {
            color: #3b82f6;
            cursor: pointer;
        }
        
        .tab-btn {
            cursor: pointer;
            transition: all 0.2s ease;
        }
        
        label:has(input[type="radio"]):hover {
            background-color: #f9fafb;
        }
        
        /* Disabled state for checkboxes */
        input[type="checkbox"]:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }
        
        label:has(input[type="checkbox"]:disabled) {
            opacity: 0.5;
            cursor: not-allowed;
        }
    </style>
</body>
</html></div>
                            </div>
                        `;
                    }
                }
            } else if (activeTab === 'personalizado') {
                const cantidad = parseInt(document.querySelector('input[name="cantidad_personalizada"]').value) || 8;
                const tipo = document.querySelector('input[name="tipo_personalizado"]:checked').value;
                const planchas = Math.ceil(cantidad / 8);
                
                // Calcular precio
                const preciosBase = { 'comun': 3500, 'premium': 7000 };
                const precioBase = preciosBase[tipo];
                const descuento = paymentMethod === 'Efectivo' ? 0.9 : 1;
                const precioPlancha = Math.round(precioBase * descuento);
                const total = planchas * precioPlancha;
                
                const descuentoText = paymentMethod === 'Efectivo' ? ' (con descuento)' : '';
                
                html = `
                    <div class="border-b pb-3">
                        <div class="font-medium">Personalizado ${tipo.charAt(0).toUpperCase() + tipo.slice(1)} x${cantidad}</div>
                        <div class="text-sm text-gray-600">${planchas} plancha${planchas > 1 ? 's' : ''} - Pago: ${paymentMethod}${descuentoText}</div>
                        <div class="text-sm text-gray-600">$${precioPlancha.toLocaleString()} por plancha</div>
                    </div>
                    <div class="flex justify-between items-center font-bold text-lg">
                        <span>Total:</span>
                        <span class="text-green-600">$${total.toLocaleString()}</span>
                    </div>
                `;
                
                const saboresSeleccionados = Array.from(document.querySelectorAll('.sabor-personalizado:checked'))
                    .map(cb => cb.value);
                if (saboresSeleccionados.length > 0) {
                    html += `
                        <div class="mt-3 text-sm">
                            <div class="font-medium">Sabores:</div>
                            <div class="text-gray-600">${saboresSeleccionados.join(', ')}