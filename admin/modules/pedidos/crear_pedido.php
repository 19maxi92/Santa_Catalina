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
        $ubicacion = $_POST['ubicacion']; // NUEVO CAMPO
        $forma_pago = $_POST['forma_pago'];
        $observaciones = sanitize($_POST['observaciones']);
        
        // Campos de fecha y hora
        $fecha_entrega = $_POST['fecha_entrega'] ?? null;
        $hora_entrega = $_POST['hora_entrega'] ?? null;
        $notas_horario = sanitize($_POST['notas_horario'] ?? '');
        
        // Validar campos obligatorios
        if (!$nombre || !$apellido || !$telefono || !$modalidad || !$ubicacion || !$forma_pago) {
            throw new Exception('Todos los campos obligatorios deben completarse');
        }
        
        // Validar fecha de entrega
        if ($fecha_entrega && $fecha_entrega < date('Y-m-d')) {
            throw new Exception('La fecha de entrega no puede ser anterior a hoy');
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
                            $cantidad = (int)explode(' ', $producto)[0];
                            
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
                    
                    // Calcular planchas
                    $planchas = ceil($cant_personalizado / 8);
                    $precio_plancha_base = ($tipo_personalizado === 'premium') ? 7000 : 3500;
                    $precio_plancha = ($forma_pago === 'Efectivo') ? $precio_plancha_base * 0.9 : $precio_plancha_base;
                    
                    $producto = "Personalizado " . ucfirst($tipo_personalizado) . " x$cant_personalizado ($planchas plancha" . ($planchas > 1 ? 's' : '') . ")";
                    $cantidad = $cant_personalizado;
                    $precio = $planchas * $precio_plancha;
                    
                    $observaciones .= "\nSabores personalizados: " . implode(', ', $sabores_personalizados);
                    break;
                    
                default:
                    throw new Exception('Tipo de pedido no válido');
            }
            
            if (empty($producto) || $cantidad <= 0 || $precio <= 0) {
                throw new Exception("Error en los datos del producto. Producto: '$producto', Cantidad: $cantidad, Precio: $precio");
            }
            
            // Insertar pedido CON UBICACIÓN
            $stmt = $pdo->prepare("
                INSERT INTO pedidos (nombre, apellido, telefono, direccion, producto, cantidad, precio, 
                                   forma_pago, modalidad, ubicacion, observaciones, cliente_fijo_id, 
                                   fecha_entrega, hora_entrega, notas_horario) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            
            $cliente_fijo_id = $cliente_seleccionado ? $cliente_seleccionado['id'] : null;
            
            $stmt->execute([
                $nombre, $apellido, $telefono, $direccion, $producto, $cantidad, 
                $precio, $forma_pago, $modalidad, $ubicacion, $observaciones, $cliente_fijo_id,
                $fecha_entrega, $hora_entrega, $notas_horario
            ]);
            
            $mensaje = 'Pedido creado correctamente';
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
                            <label class="block text-gray-700 mb-2 font-medium">Nombre <span class="text-red-500">*</span></label>
                            <input type="text" name="nombre" value="<?= $cliente_seleccionado['nombre'] ?? ($_POST['nombre'] ?? '') ?>" required
                                   class="w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500">
                        </div>
                        
                        <div>
                            <label class="block text-gray-700 mb-2 font-medium">Apellido <span class="text-red-500">*</span></label>
                            <input type="text" name="apellido" value="<?= $cliente_seleccionado['apellido'] ?? ($_POST['apellido'] ?? '') ?>" required
                                   class="w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500">
                        </div>
                        
                        <div>
                            <label class="block text-gray-700 mb-2 font-medium">Teléfono <span class="text-red-500">*</span></label>
                            <input type="tel" name="telefono" value="<?= $cliente_seleccionado['telefono'] ?? ($_POST['telefono'] ?? '') ?>" required
                                   class="w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500">
                        </div>
                        
                        <div>
                            <label class="block text-gray-700 mb-2 font-medium">Dirección</label>
                            <input type="text" name="direccion" value="<?= $cliente_seleccionado['direccion'] ?? ($_POST['direccion'] ?? '') ?>"
                                   class="w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500">
                        </div>
                        
                        <!-- FECHA/HORA -->
                        <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-4">
                            <h4 class="font-medium text-gray-800 mb-3">
                                <i class="fas fa-calendar-clock text-orange-500 mr-2"></i>¿Para cuándo es el pedido?
                            </h4>
                            
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                                <div>
                                    <label class="block text-sm text-gray-700 mb-1">Fecha de entrega</label>
                                    <input type="date" name="fecha_entrega" 
                                           value="<?= $_POST['fecha_entrega'] ?? date('Y-m-d') ?>"
                                           min="<?= date('Y-m-d') ?>"
                                           class="w-full px-3 py-2 border rounded-lg text-sm focus:ring-2 focus:ring-orange-500">
                                </div>
                                
                                <div>
                                    <label class="block text-sm text-gray-700 mb-1">Hora aproximada</label>
                                    <select name="hora_entrega" class="w-full px-3 py-2 border rounded-lg text-sm focus:ring-2 focus:ring-orange-500">
                                        <option value="">Sin horario específico</option>
                                        <option value="09:00">9:00 AM</option>
                                        <option value="10:00">10:00 AM</option>
                                        <option value="11:00">11:00 AM</option>
                                        <option value="12:00">12:00 PM</option>
                                        <option value="13:00">1:00 PM</option>
                                        <option value="14:00">2:00 PM</option>
                                        <option value="15:00">3:00 PM</option>
                                        <option value="16:00">4:00 PM</option>
                                        <option value="17:00">5:00 PM</option>
                                        <option value="18:00">6:00 PM</option>
                                        <option value="19:00">7:00 PM</option>
                                        <option value="20:00">8:00 PM</option>
                                    </select>
                                </div>
                            </div>
                            
                            <div class="mt-3">
                                <label class="block text-sm text-gray-700 mb-1">Notas sobre horario</label>
                                <input type="text" name="notas_horario" 
                                       placeholder="Ej: Flexible, después de las 15:00, urgente..."
                                       class="w-full px-3 py-2 border rounded-lg text-sm focus:ring-2 focus:ring-orange-500">
                            </div>
                        </div>
                        
                        <div>
                            <label class="block text-gray-700 mb-2 font-medium">Modalidad <span class="text-red-500">*</span></label>
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

                        <!-- NUEVA SECCIÓN: Selección de Ubicación -->
                        <div class="bg-purple-50 border border-purple-200 rounded-lg p-4">
                            <h4 class="font-medium text-purple-800 mb-3">
                                <i class="fas fa-map-marker-alt text-purple-500 mr-2"></i>¿Dónde se procesa este pedido? <span class="text-red-500">*</span>
                            </h4>
                            
                            <div class="grid grid-cols-2 gap-3">
                                <label class="cursor-pointer">
                                    <input type="radio" name="ubicacion" value="Local 1" required class="sr-only ubicacion-radio">
                                    <div class="ubicacion-btn bg-blue-50 hover:bg-blue-100 border-2 border-blue-200 rounded-lg p-4 text-center transition-all duration-200">
                                        <div class="text-3xl mb-2">🏪</div>
                                        <div class="font-bold text-blue-800">LOCAL 1</div>
                                        <div class="text-xs text-blue-600 mt-1">Atención público</div>
                                    </div>
                                </label>
                                
                                <label class="cursor-pointer">
                                    <input type="radio" name="ubicacion" value="Fábrica" required class="sr-only ubicacion-radio">
                                    <div class="ubicacion-btn bg-orange-50 hover:bg-orange-100 border-2 border-orange-200 rounded-lg p-4 text-center transition-all duration-200">
                                        <div class="text-3xl mb-2">🏭</div>
                                        <div class="font-bold text-orange-800">FÁBRICA</div>
                                        <div class="text-xs text-orange-600 mt-1">Producción central</div>
                                    </div>
                                </label>
                            </div>
                            
                            <div class="text-xs text-gray-500 mt-2 text-center">
                                <i class="fas fa-info-circle mr-1"></i>
                                Determina dónde se prepara y desde dónde se entrega
                            </div>
                        </div>
                        
                        <div>
                            <label class="block text-gray-700 mb-2 font-medium">Forma de Pago <span class="text-red-500">*</span></label>
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
                            <label class="block text-gray-700 mb-2 font-medium">Observaciones</label>
                            <textarea name="observaciones" rows="3"
                                      class="w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500"
                                      placeholder="Observaciones adicionales..."><?= $cliente_seleccionado['observaciones'] ?? ($_POST['observaciones'] ?? '') ?></textarea>
                        </div>
                    </div>
                </div>

                <!-- Columna 2: Productos (sin cambios) -->
                <div class="bg-white rounded-lg shadow p-6">
                    <h3 class="text-lg font-semibold mb-4">
                        <i class="fas fa-shopping-cart text-orange-500 mr-2"></i>Seleccionar Producto
                    </h3>
                    
                    <input type="hidden" name="tipo_pedido" id="tipo_pedido_hidden" value="predefinido">
                    
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
                        
                        <!-- Premium sabores -->
                        <div id="sabores-premium" class="mt-4 hidden">
                            <h4 class="font-medium mb-2">Seleccionar Sabores:</h4>
                            <div class="grid grid-cols-2 gap-2 max-h-40 overflow-y-auto">
                                <?php foreach ($sabores_premium as $sabor): ?>
                                    <label class="flex items-center">
                                        <input type="checkbox" name="sabores_premium[]" value="<?= $sabor ?>" class="mr-2">
                                        <span class="text-sm"><?= $sabor ?></span>
                                    </label>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Tab Personalizado -->
                    <div id="content-personalizado" class="tab-content hidden">
                        <div class="space-y-4">
                            <div>
                                <label class="block text-gray-700 mb-2">Cantidad:</label>
                                <input type="number" name="cantidad_personalizada" min="1" max="200" value="8"
                                       class="w-full px-3 py-2 border rounded-lg">
                            </div>
                            
                            <div>
                                <label class="block text-gray-700 mb-2">Tipo:</label>
                                <select name="tipo_personalizado" class="w-full px-3 py-2 border rounded-lg">
                                    <option value="comun">Común ($3,500 por plancha)</option>
                                    <option value="premium">Premium ($7,000 por plancha)</option>
                                </select>
                            </div>
                            
                            <div>
                                <label class="block text-gray-700 mb-2">Sabores:</label>
                                <div class="grid grid-cols-2 gap-2 max-h-40 overflow-y-auto">
                                    <?php foreach ($sabores_premium as $sabor): ?>
                                        <label class="flex items-center">
                                            <input type="checkbox" name="sabores_personalizados[]" value="<?= $sabor ?>" class="mr-2">
                                            <span class="text-sm"><?= $sabor ?></span>
                                        </label>
                                    <?php endforeach; ?>
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

    <!-- CSS y JavaScript -->
    <style>
    .ubicacion-radio:checked + .ubicacion-btn {
        border-color: currentColor;
        box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
        transform: translateY(-2px);
    }

    .ubicacion-btn:hover {
        transform: translateY(-1px);
        box-shadow: 0 4px 12px rgba(0,0,0,0.1);
    }

    .tab-btn.active {
        border-bottom-color: #3b82f6 !important;
        color: #3b82f6 !important;
    }
    </style>

    <script>
        let currentProduct = null;
        let paymentMethod = 'Efectivo';
        
        // Manejo de ubicación
        document.addEventListener('DOMContentLoaded', function() {
            const radios = document.querySelectorAll('input[name="ubicacion"]');
            
            radios.forEach(radio => {
                radio.addEventListener('change', function() {
                    document.querySelectorAll('.ubicacion-btn').forEach(btn => {
                        btn.classList.remove('ring-2', 'ring-blue-400', 'ring-orange-400');
                    });
                    
                    const btn = this.nextElementSibling;
                    if (this.value === 'Local 1') {
                        btn.classList.add('ring-2', 'ring-blue-400');
                    } else {
                        btn.classList.add('ring-2', 'ring-orange-400');
                    }
                    
                    updateResumen();
                });
            });
        });
        
        // Tabs
        function showTab(tab) {
            document.getElementById('content-predefinido').style.display = tab === 'predefinido' ? 'block' : 'none';
            document.getElementById('content-personalizado').style.display = tab === 'personalizado' ? 'block' : 'none';
            
            document.getElementById('tab-predefinido').className = tab === 'predefinido' 
                ? 'tab-btn py-2 px-4 border-b-2 font-medium text-sm active cursor-pointer border-blue-500 text-blue-600'
                : 'tab-btn py-2 px-4 border-b-2 font-medium text-sm cursor-pointer border-transparent text-gray-500 hover:text-blue-600';
            
            document.getElementById('tab-personalizado').className = tab === 'personalizado' 
                ? 'tab-btn py-2 px-4 border-b-2 font-medium text-sm active cursor-pointer border-blue-500 text-blue-600'
                : 'tab-btn py-2 px-4 border-b-2 font-medium text-sm cursor-pointer border-transparent text-gray-500 hover:text-blue-600';
            
            document.getElementById('tipo_pedido_hidden').value = tab;
            updateResumen();
        }

        // Seleccionar producto
        function selectProduct(id, name, precioEfectivo, precioTransferencia) {
            currentProduct = { id, name, precioEfectivo, precioTransferencia };
            
            const saboresDiv = document.getElementById('sabores-premium');
            if (name.includes('Premium')) {
                saboresDiv.classList.remove('hidden');
            } else {
                saboresDiv.classList.add('hidden');
            }
            
            updateResumen();
        }

        // Actualizar precios
        function updatePrecios() {
            const formaPago = document.querySelector('input[name="forma_pago"]:checked')?.value;
            if (formaPago) {
                paymentMethod = formaPago;
                updateResumen();
            }
        }

        // Actualizar resumen
        function updateResumen() {
            const resumenDiv = document.getElementById('resumen-pedido');
            const activeTab = document.getElementById('tipo_pedido_hidden').value;
            const ubicacion = document.querySelector('input[name="ubicacion"]:checked')?.value;
            
            let html = '';
            
            if (activeTab === 'predefinido' && currentProduct) {
                const precio = paymentMethod === 'Efectivo' ? currentProduct.precioEfectivo : currentProduct.precioTransferencia;
                
                html = `
                    <div class="border-b pb-3">
                        <div class="font-medium">${currentProduct.name}</div>
                        <div class="text-sm text-gray-600">Pago: ${paymentMethod}</div>
                        ${ubicacion ? `<div class="text-xs ${ubicacion === 'Local 1' ? 'text-blue-600' : 'text-orange-600'}">${ubicacion === 'Local 1' ? '🏪 Local 1' : '🏭 Fábrica'}</div>` : ''}
                    </div>
                    <div class="flex justify-between items-center font-bold text-lg">
                        <span>Total:</span>
                        <span class="text-green-600">${precio.toLocaleString()}</span>
                    </div>
                `;
            } else if (activeTab === 'personalizado') {
                const cantidad = document.querySelector('input[name="cantidad_personalizada"]')?.value || 8;
                const tipo = document.querySelector('select[name="tipo_personalizado"]')?.value || 'comun';
                const planchas = Math.ceil(cantidad / 8);
                const precioBase = tipo === 'premium' ? 7000 : 3500;
                const precio = paymentMethod === 'Efectivo' ? precioBase * 0.9 * planchas : precioBase * planchas;
                
                html = `
                    <div class="border-b pb-3">
                        <div class="font-medium">Personalizado ${tipo} x${cantidad}</div>
                        <div class="text-sm text-gray-600">${planchas} plancha${planchas > 1 ? 's' : ''} - ${paymentMethod}</div>
                        ${ubicacion ? `<div class="text-xs ${ubicacion === 'Local 1' ? 'text-blue-600' : 'text-orange-600'}">${ubicacion === 'Local 1' ? '🏪 Local 1' : '🏭 Fábrica'}</div>` : ''}
                    </div>
                    <div class="flex justify-between items-center font-bold text-lg">
                        <span>Total:</span>
                        <span class="text-green-600">${Math.round(precio).toLocaleString()}</span>
                    </div>
                `;
            } else {
                html = `
                    <div class="text-gray-500 text-center py-8">
                        <i class="fas fa-shopping-cart text-4xl mb-2"></i>
                        <div>Seleccioná un producto para ver el resumen</div>
                        ${ubicacion ? `<div class="text-xs mt-2 ${ubicacion === 'Local 1' ? 'text-blue-600' : 'text-orange-600'}">${ubicacion === 'Local 1' ? '🏪 Local 1' : '🏭 Fábrica'}</div>` : ''}
                    </div>
                `;
            }
            
            resumenDiv.innerHTML = html;
        }

        // Validaciones del formulario
        document.getElementById('pedidoForm').addEventListener('submit', function(e) {
            const ubicacion = document.querySelector('input[name="ubicacion"]:checked');
            if (!ubicacion) {
                e.preventDefault();
                alert('Debe seleccionar la ubicación del pedido (Local 1 o Fábrica).');
                return false;
            }

            const tipoPedido = document.getElementById('tipo_pedido_hidden').value;
            
            if (tipoPedido === 'predefinido') {
                const productoSeleccionado = document.querySelector('input[name="producto_id"]:checked');
                if (!productoSeleccionado) {
                    e.preventDefault();
                    alert('Por favor selecciona un producto.');
                    return false;
                }
            }
            
            return true;
        });

        // Inicializar
        document.addEventListener('DOMContentLoaded', function() {
            showTab('predefinido');
            
            // Event listeners para form changes
            document.querySelectorAll('input[name="forma_pago"]').forEach(radio => {
                radio.addEventListener('change', updatePrecios);
            });
        });
    </script>
</body>
</html>