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

// Sabores separados por tipo
$sabores_comunes = [
    'Jamón y Queso', 'Lechuga', 'Tomate', 'Huevo', 'Choclo', 'Aceitunas'
];

$sabores_premium = [
    'Ananá', 'Atún', 'Berenjena', 'Durazno', 'Jamón Crudo', 'Morrón', 
    'Palmito', 'Panceta', 'Pollo', 'Roquefort', 'Salame'
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
        $ubicacion = $_POST['ubicacion'];
        $forma_pago = $_POST['forma_pago'];
        $turno_delivery = $_POST['turno_delivery'] ?? null;
        $observaciones = sanitize($_POST['observaciones']);
        
        // Agregar turno a observaciones si es delivery
        if ($modalidad === 'Delivery' && $turno_delivery) {
            $observaciones .= "\nTurno delivery: " . ucfirst($turno_delivery);
        }
        
        // Campos de fecha y hora
        $fecha_entrega = $_POST['fecha_entrega'] ?? null;
        $hora_entrega = $_POST['hora_entrega'] ?? null;
        $notas_horario = sanitize($_POST['notas_horario'] ?? '');
        
        // Validar campos obligatorios
        if (!$nombre || !$apellido || !$modalidad || !$ubicacion || !$forma_pago) {
            throw new Exception('Todos los campos obligatorios deben completarse');
        }
        
        // Validar dirección y turno si es delivery
        if ($modalidad === 'Delivery') {
            if (empty(trim($direccion))) {
                throw new Exception('La dirección es obligatoria para pedidos de delivery');
            }
            if (empty($turno_delivery)) {
                throw new Exception('Debe seleccionar un turno para delivery');
            }
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
                    $tipo_personalizado = $_POST['tipo_personalizado'] ?? 'comun';
                    
                    if ($cant_personalizado <= 0) {
                        throw new Exception('La cantidad debe ser mayor a 0');
                    }
                    
                    // Calcular planchas
                    $planchas = ceil($cant_personalizado / 8);
                    
                    // Recopilar información de cada plancha
                    $detalles_planchas = [];
                    $precio_total = 0;
                    $sabores_todos = [];
                    
                    for ($i = 1; $i <= $planchas; $i++) {
                        $tipo_plancha = $_POST["plancha_{$i}_tipo"] ?? 'comun';
                        $sabores_plancha = trim($_POST["plancha_{$i}_sabores"] ?? '');
                        
                        $precio_plancha_base = ($tipo_plancha === 'premium') ? 7000 : 3500;
                        $precio_plancha = ($forma_pago === 'Efectivo') ? $precio_plancha_base * 0.9 : $precio_plancha_base;
                        $precio_total += $precio_plancha;
                        
                        $detalles_planchas[] = "Plancha $i: " . ucfirst($tipo_plancha) . " ($" . number_format($precio_plancha, 0, ',', '.') . ")" . ($sabores_plancha ? " - $sabores_plancha" : "");
                        
                        if ($sabores_plancha) {
                            $sabores_todos[] = $sabores_plancha;
                        }
                    }
                    
                    $producto = "Personalizado x$cant_personalizado ($planchas plancha" . ($planchas > 1 ? 's' : '') . ")";
                    $cantidad = $cant_personalizado;
                    $precio = $precio_total;
                    
                    $observaciones .= "\nDetalle de planchas:\n" . implode("\n", $detalles_planchas);
                    if (!empty($sabores_todos)) {
                        $observaciones .= "\nSabores: " . implode(" | ", $sabores_todos);
                    }
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
                            <input type="text" name="nombre" value="<?= $cliente_seleccionado['nombre'] ?? ($_POST['nombre'] ?? '') ?>" 
                                   required class="w-full px-3 py-2 border rounded-lg focus:outline-none focus:border-blue-500">
                        </div>
                        
                        <div>
                            <label class="block text-gray-700 mb-2 font-medium">Apellido <span class="text-red-500">*</span></label>
                            <input type="text" name="apellido" value="<?= $cliente_seleccionado['apellido'] ?? ($_POST['apellido'] ?? '') ?>" 
                                   required class="w-full px-3 py-2 border rounded-lg focus:outline-none focus:border-blue-500">
                        </div>
                        
                        <div>
                            <label class="block text-gray-700 mb-2 font-medium">Teléfono</label>
                            <input type="tel" name="telefono" value="<?= $cliente_seleccionado['telefono'] ?? ($_POST['telefono'] ?? '') ?>" 
                                   class="w-full px-3 py-2 border rounded-lg focus:outline-none focus:border-blue-500">
                        </div>
                        
                        <!-- Modalidad y Ubicación -->
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-gray-700 mb-2 font-medium">Modalidad <span class="text-red-500">*</span></label>
                                <div class="space-y-2">
                                    <label class="flex items-center">
                                        <input type="radio" name="modalidad" value="Retira" required class="mr-2"
                                               <?= ($_POST['modalidad'] ?? '') === 'Retira' ? 'checked' : '' ?> onchange="toggleDireccion()">
                                        <i class="fas fa-store mr-2 text-blue-500"></i>Retira en Local
                                    </label>
                                    <label class="flex items-center">
                                        <input type="radio" name="modalidad" value="Delivery" required class="mr-2"
                                               <?= ($_POST['modalidad'] ?? '') === 'Delivery' ? 'checked' : '' ?> onchange="toggleDireccion()">
                                        <i class="fas fa-truck mr-2 text-green-500"></i>Delivery
                                    </label>
                                </div>
                            </div>
                            
                            <div>
                                <label class="block text-gray-700 mb-2 font-medium">Ubicación <span class="text-red-500">*</span></label>
                                <select name="ubicacion" required class="w-full px-3 py-2 border rounded-lg focus:outline-none focus:border-blue-500">
                                    <option value="">Seleccionar...</option>
                                    <option value="Fábrica" <?= ($_POST['ubicacion'] ?? '') === 'Fábrica' ? 'selected' : '' ?>>Fábrica</option>
                                    <option value="Local 1" <?= ($_POST['ubicacion'] ?? '') === 'Local 1' ? 'selected' : '' ?>>Local 1</option>
                                </select>
                            </div>
                        </div>
                        
                        <!-- Dirección y Turno - Se ajusta según modalidad -->
                        <div id="direccion-container">
                            <label class="block text-gray-700 mb-2 font-medium">
                                Dirección <span id="direccion-required" class="text-red-500 hidden">*</span>
                            </label>
                            <textarea name="direccion" rows="2" id="direccion-input"
                                      class="w-full px-3 py-2 border rounded-lg focus:outline-none focus:border-blue-500"><?= $cliente_seleccionado['direccion'] ?? ($_POST['direccion'] ?? '') ?></textarea>
                            <div id="direccion-help" class="text-xs text-gray-500 mt-1 hidden">
                                Campo obligatorio para delivery
                            </div>
                        </div>
                        
                        <!-- Turno de Delivery - Solo visible para delivery -->
                        <div id="turno-delivery-container" class="hidden">
                            <label class="block text-gray-700 mb-2 font-medium">Turno de Delivery <span class="text-red-500">*</span></label>
                            <div class="grid grid-cols-3 gap-2">
                                <label class="flex items-center p-2 border rounded-lg cursor-pointer hover:bg-blue-50">
                                    <input type="radio" name="turno_delivery" value="mañana" class="mr-2">
                                    <div class="text-center w-full">
                                        <i class="fas fa-sun text-yellow-500 block mb-1"></i>
                                        <span class="text-sm font-medium">Mañana</span>
                                        <div class="text-xs text-gray-500">8:00-12:00</div>
                                    </div>
                                </label>
                                <label class="flex items-center p-2 border rounded-lg cursor-pointer hover:bg-orange-50">
                                    <input type="radio" name="turno_delivery" value="siesta" class="mr-2">
                                    <div class="text-center w-full">
                                        <i class="fas fa-cloud-sun text-orange-500 block mb-1"></i>
                                        <span class="text-sm font-medium">Siesta</span>
                                        <div class="text-xs text-gray-500">12:00-16:00</div>
                                    </div>
                                </label>
                                <label class="flex items-center p-2 border rounded-lg cursor-pointer hover:bg-purple-50">
                                    <input type="radio" name="turno_delivery" value="tarde" class="mr-2">
                                    <div class="text-center w-full">
                                        <i class="fas fa-moon text-purple-500 block mb-1"></i>
                                        <span class="text-sm font-medium">Tarde</span>
                                        <div class="text-xs text-gray-500">16:00-20:00</div>
                                    </div>
                                </label>
                            </div>
                        </div>
                        
                        <!-- Forma de Pago -->
                        <div>
                            <label class="block text-gray-700 mb-2 font-medium">Forma de Pago <span class="text-red-500">*</span></label>
                            <div class="space-y-2">
                                <label class="flex items-center">
                                    <input type="radio" name="forma_pago" value="Efectivo" <?= ($_POST['forma_pago'] ?? '') === 'Efectivo' ? 'checked' : '' ?> 
                                           class="mr-2" required>
                                    <span>Efectivo (10% descuento)</span>
                                </label>
                                <label class="flex items-center">
                                    <input type="radio" name="forma_pago" value="Transferencia" <?= ($_POST['forma_pago'] ?? '') === 'Transferencia' ? 'checked' : '' ?> 
                                           class="mr-2" required>
                                    <span>Transferencia</span>
                                </label>
                            </div>
                        </div>
                        
                        <!-- Fecha y Hora de Entrega -->
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-gray-700 mb-2">Fecha Entrega</label>
                                <input type="date" name="fecha_entrega" value="<?= $_POST['fecha_entrega'] ?? date('Y-m-d') ?>" 
                                       min="<?= date('Y-m-d') ?>" class="w-full px-3 py-2 border rounded-lg">
                            </div>
                            <div>
                                <label class="block text-gray-700 mb-2" id="hora-label">Hora Entrega</label>
                                <input type="time" name="hora_entrega" value="<?= $_POST['hora_entrega'] ?? '' ?>" 
                                       id="hora-input" class="w-full px-3 py-2 border rounded-lg">
                                <div class="text-xs text-gray-500 mt-1" id="hora-help">
                                    Opcional - Hora específica si es necesario
                                </div>
                            </div>
                        </div>
                        
                        <div>
                            <label class="block text-gray-700 mb-2">Notas de Horario</label>
                            <textarea name="notas_horario" rows="2" placeholder="Ej: Entregar después de las 14hs" 
                                      class="w-full px-3 py-2 border rounded-lg"><?= $_POST['notas_horario'] ?? '' ?></textarea>
                        </div>
                        
                        <div>
                            <label class="block text-gray-700 mb-2">Observaciones</label>
                            <textarea name="observaciones" rows="3" 
                                      class="w-full px-3 py-2 border rounded-lg"><?= $_POST['observaciones'] ?? '' ?></textarea>
                        </div>
                    </div>
                </div>
                
                <!-- Columna 2: Selección de Producto -->
                <div class="bg-white rounded-lg shadow p-6">
                    <h3 class="text-lg font-semibold mb-4">
                        <i class="fas fa-sandwich text-orange-500 mr-2"></i>Seleccionar Producto
                    </h3>
                    
                    <!-- Tabs -->
                    <div class="flex mb-4 border-b">
                        <button type="button" class="tab-button px-4 py-2 font-medium border-b-2 border-blue-500 text-blue-600" data-tab="predefinido">
                            Predefinidos
                        </button>
                        <button type="button" class="tab-button px-4 py-2 font-medium text-gray-500 hover:text-gray-700" data-tab="personalizado">
                            Personalizado
                        </button>
                    </div>
                    
                    <!-- Tab Predefinido -->
                    <div id="content-predefinido" class="tab-content">
                        <input type="hidden" name="tipo_pedido" value="predefinido">
                        <div class="space-y-3 max-h-96 overflow-y-auto">
                            <?php foreach ($productos as $prod): ?>
                                <label class="flex items-center p-3 border rounded-lg hover:bg-gray-50 cursor-pointer producto-option" 
                                       data-precio-efectivo="<?= $prod['precio_efectivo'] ?>" 
                                       data-precio-transferencia="<?= $prod['precio_transferencia'] ?>">
                                    <input type="radio" name="producto_id" value="<?= $prod['id'] ?>" class="mr-3">
                                    <div class="flex-1">
                                        <div class="font-medium"><?= $prod['nombre'] ?></div>
                                        <div class="text-sm text-gray-600">
                                            <span class="precio-efectivo">Efectivo: $<?= number_format($prod['precio_efectivo'], 0, ',', '.') ?></span> | 
                                            <span class="precio-transferencia">Transfer: $<?= number_format($prod['precio_transferencia'], 0, ',', '.') ?></span>
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
                    
                    <!-- Tab Personalizado - MODIFICADO -->
                    <div id="content-personalizado" class="tab-content hidden">
                        <input type="hidden" name="tipo_pedido" value="personalizado">
                        <div class="space-y-4">
                            <div>
                                <label class="block text-gray-700 mb-2">Cantidad (múltiplos de 8 - una plancha):</label>
                                <div class="flex items-center space-x-2">
                                    <button type="button" onclick="ajustarCantidad(-8)" class="bg-red-500 text-white px-3 py-2 rounded hover:bg-red-600">-8</button>
                                    <input type="number" name="cantidad_personalizada" min="8" max="200" value="8" step="8"
                                           class="w-24 text-center px-3 py-2 border rounded-lg" onchange="validarCantidad(this)">
                                    <button type="button" onclick="ajustarCantidad(8)" class="bg-green-500 text-white px-3 py-2 rounded hover:bg-green-600">+8</button>
                                    <span class="text-sm text-gray-600" id="plancha-info">1 plancha</span>
                                </div>
                                <div class="text-xs text-gray-500 mt-1">Cada plancha tiene 8 sándwiches</div>
                            </div>
                            
                            <!-- Configuración por plancha -->
                            <div id="planchas-config" class="space-y-4">
                                <!-- Se generará dinámicamente con JavaScript -->
                            </div>
                            
                            <!-- Sabores disponibles - Siempre visibles -->
                            <div class="border-t pt-4">
                                <label class="block text-gray-700 mb-3 font-medium">Sabores Disponibles:</label>
                                
                                <!-- Sabores Comunes -->
                                <div class="sabores-container mb-4">
                                    <h4 class="font-medium text-sm text-blue-700 mb-2">Sabores Comunes ($3,500 por plancha):</h4>
                                    <div class="grid grid-cols-2 gap-2 border rounded p-2 bg-blue-50">
                                        <?php foreach ($sabores_comunes as $sabor): ?>
                                            <div class="flex items-center text-sm text-blue-800">
                                                <i class="fas fa-circle text-xs text-blue-500 mr-2"></i>
                                                <span><?= $sabor ?></span>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                                
                                <!-- Sabores Premium -->
                                <div class="sabores-container">
                                    <h4 class="font-medium text-sm text-orange-700 mb-2">Sabores Premium ($7,000 por plancha):</h4>
                                    <div class="grid grid-cols-2 gap-2 border rounded p-2 bg-orange-50">
                                        <?php foreach ($sabores_premium as $sabor): ?>
                                            <div class="flex items-center text-sm text-orange-800">
                                                <i class="fas fa-circle text-xs text-orange-500 mr-2"></i>
                                                <span><?= $sabor ?></span>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
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
                    
                    <div class="mt-6">
                        <button type="submit" class="w-full bg-green-500 hover:bg-green-600 text-white font-bold py-3 px-4 rounded-lg transition duration-200">
                            <i class="fas fa-save mr-2"></i>Crear Pedido
                        </button>
                    </div>
                </div>
            </div>
        </form>
    </main>

    <script>
        // Manejo de tabs
        document.querySelectorAll('.tab-button').forEach(button => {
            button.addEventListener('click', function() {
                const tab = this.dataset.tab;
                
                // Actualizar botones
                document.querySelectorAll('.tab-button').forEach(btn => {
                    btn.classList.remove('border-blue-500', 'text-blue-600');
                    btn.classList.add('text-gray-500');
                });
                this.classList.add('border-blue-500', 'text-blue-600');
                this.classList.remove('text-gray-500');
                
                // Mostrar contenido
                document.querySelectorAll('.tab-content').forEach(content => {
                    content.classList.add('hidden');
                });
                document.getElementById('content-' + tab).classList.remove('hidden');
                
                // Actualizar input hidden
                document.querySelector('input[name="tipo_pedido"]').value = tab;
                
                // Limpiar resumen
                document.getElementById('resumen-pedido').innerHTML = `
                    <div class="text-gray-500 text-center py-8">
                        <i class="fas fa-shopping-cart text-4xl mb-2"></i>
                        <div>Seleccioná un producto para ver el resumen</div>
                    </div>
                `;
            });
        });

        // Función para mostrar/ocultar dirección y turnos según modalidad
        function toggleDireccion() {
            const modalidadDelivery = document.querySelector('input[name="modalidad"][value="Delivery"]').checked;
            const direccionContainer = document.getElementById('direccion-container');
            const direccionInput = document.getElementById('direccion-input');
            const direccionRequired = document.getElementById('direccion-required');
            const direccionHelp = document.getElementById('direccion-help');
            const turnoContainer = document.getElementById('turno-delivery-container');
            const horaLabel = document.getElementById('hora-label');
            const horaHelp = document.getElementById('hora-help');
            const turnoInputs = document.querySelectorAll('input[name="turno_delivery"]');
            
            if (modalidadDelivery) {
                // DELIVERY: Mostrar dirección, turnos y cambiar etiqueta de hora
                direccionContainer.style.display = 'block';
                direccionInput.required = true;
                direccionRequired.classList.remove('hidden');
                direccionHelp.classList.remove('hidden');
                direccionContainer.classList.add('bg-yellow-50', 'border', 'border-yellow-200', 'rounded-lg', 'p-3');
                
                // Mostrar turnos
                turnoContainer.classList.remove('hidden');
                turnoInputs.forEach(input => input.required = true);
                
                // Cambiar etiquetas de hora
                horaLabel.textContent = 'Hora Específica (dentro del turno)';
                horaHelp.textContent = 'Opcional - Solo si necesitas una hora específica dentro del turno seleccionado';
                
            } else {
                // RETIRA: Ocultar turnos, cambiar etiquetas
                direccionInput.required = false;
                direccionRequired.classList.add('hidden');
                direccionHelp.classList.add('hidden');
                direccionContainer.classList.remove('bg-yellow-50', 'border', 'border-yellow-200', 'rounded-lg', 'p-3');
                
                // Ocultar turnos
                turnoContainer.classList.add('hidden');
                turnoInputs.forEach(input => {
                    input.required = false;
                    input.checked = false;
                });
                
                // Cambiar etiquetas de hora
                horaLabel.textContent = 'Hora de Retiro';
                horaHelp.textContent = 'Opcional - Hora que prefiere retirar el pedido';
            }
        }

        // Función para ajustar cantidad en múltiplos de 8
        function ajustarCantidad(cambio) {
            const input = document.querySelector('input[name="cantidad_personalizada"]');
            let valor = parseInt(input.value) || 8;
            valor += cambio;
            
            // Mínimo 8, máximo 200
            if (valor < 8) valor = 8;
            if (valor > 200) valor = 200;
            
            input.value = valor;
            actualizarInfoPlancha(valor);
            
            // Trigger change event para actualizar resumen
            input.dispatchEvent(new Event('change'));
        }

        // Función para validar que sea múltiplo de 8
        function validarCantidad(input) {
            let valor = parseInt(input.value) || 8;
            
            // Redondear al múltiplo de 8 más cercano
            valor = Math.round(valor / 8) * 8;
            
            // Límites
            if (valor < 8) valor = 8;
            if (valor > 200) valor = 200;
            
            input.value = valor;
            actualizarInfoPlancha(valor);
        }

        // Función para actualizar info de planchas y generar configuración
        function actualizarInfoPlancha(cantidad) {
            const planchas = Math.ceil(cantidad / 8);
            const info = document.getElementById('plancha-info');
            if (info) {
                info.textContent = `${planchas} plancha${planchas > 1 ? 's' : ''}`;
            }
            
            // Generar configuración por plancha
            generarConfigPlanchas(planchas);
        }
        
        // Función para generar configuración de planchas
        function generarConfigPlanchas(numPlanchas) {
            const container = document.getElementById('planchas-config');
            if (!container) return;
            
            // Guardar valores existentes antes de regenerar
            const valoresExistentes = {};
            for (let i = 1; i <= 20; i++) { // Máximo 20 planchas posibles
                const tipoSelect = document.querySelector(`select[name="plancha_${i}_tipo"]`);
                const saboresTextarea = document.querySelector(`textarea[name="plancha_${i}_sabores"]`);
                if (tipoSelect && saboresTextarea) {
                    valoresExistentes[i] = {
                        tipo: tipoSelect.value,
                        sabores: saboresTextarea.value
                    };
                }
            }
            
            container.innerHTML = '';
            
            for (let i = 1; i <= numPlanchas; i++) {
                const planchaDiv = document.createElement('div');
                planchaDiv.className = 'border rounded-lg p-4 bg-gray-50';
                
                // Recuperar valores existentes si los hay
                const valorTipo = valoresExistentes[i]?.tipo || 'comun';
                const valorSabores = valoresExistentes[i]?.sabores || '';
                
                planchaDiv.innerHTML = `
                    <h5 class="font-medium text-gray-800 mb-3">
                        <i class="fas fa-layer-group text-gray-600 mr-2"></i>Plancha ${i} (8 sándwiches)
                    </h5>
                    
                    <div class="grid grid-cols-1 gap-3">
                        <!-- Tipo de plancha -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Tipo:</label>
                            <select name="plancha_${i}_tipo" class="w-full px-2 py-1 text-sm border rounded plancha-tipo" onchange="actualizarResumenPersonalizado()">
                                <option value="comun" ${valorTipo === 'comun' ? 'selected' : ''}>Común ($3,500)</option>
                                <option value="premium" ${valorTipo === 'premium' ? 'selected' : ''}>Premium ($7,000)</option>
                            </select>
                        </div>
                        
                        <!-- Sabores para esta plancha -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Sabores:</label>
                            <textarea name="plancha_${i}_sabores" rows="2" placeholder="Ej: Jamón y Queso, Lechuga, Tomate..." 
                                      class="w-full px-2 py-1 text-sm border rounded plancha-sabores" 
                                      onchange="actualizarResumenPersonalizado()">${valorSabores}</textarea>
                            <div class="text-xs text-gray-500 mt-1">Escribí los sabores que quieras para esta plancha</div>
                        </div>
                    </div>
                `;
                container.appendChild(planchaDiv);
            }
            
            // Actualizar resumen después de regenerar
            actualizarResumenPersonalizado();
        }

        // Función para mostrar sabores según el tipo - YA NO SE USA
        function mostrarSabores(tipo) {
            // Esta función ya no es necesaria pero la dejamos por compatibilidad
        }

        // Función para actualizar resumen personalizado
        function actualizarResumenPersonalizado() {
            const cantInput = document.querySelector('input[name="cantidad_personalizada"]');
            const tipoSelect = document.querySelector('select[name="tipo_personalizado"]');
            const saboresChecked = document.querySelectorAll('input[name="sabores_personalizados[]"]:checked');
            const formaPago = document.querySelector('input[name="forma_pago"]:checked');
            
            if (!cantInput || !tipoSelect || !formaPago) return;
            
            const cantidad = parseInt(cantInput.value) || 0;
            const tipo = tipoSelect.value;
            const planchas = Math.ceil(cantidad / 8);
            
            // Precios base por plancha
            const precioPlanchaBase = tipo === 'premium' ? 7000 : 3500;
            const descuentoEfectivo = formaPago.value === 'Efectivo' ? 0.9 : 1;
            const precioPlancha = precioPlanchaBase * descuentoEfectivo;
            const precioTotal = planchas * precioPlancha;
            
            // Crear descripción del producto
            let descripcion = `Personalizado ${tipo.charAt(0).toUpperCase() + tipo.slice(1)} x${cantidad}`;
            descripcion += ` (${planchas} plancha${planchas > 1 ? 's' : ''})`;
            
            // Agregar sabores seleccionados
            if (saboresChecked.length > 0) {
                const sabores = Array.from(saboresChecked).map(cb => cb.value);
                descripcion += `\nSabores: ${sabores.join(', ')}`;
            }
            
            // Actualizar el resumen
            const resumen = document.getElementById('resumen-pedido');
            if (resumen && cantidad > 0) {
                resumen.innerHTML = `
                    <div class="border-b pb-3 mb-3">
                        <h4 class="font-medium">${descripcion}</h4>
                        <div class="text-sm text-gray-600 mt-1">
                            <div>Cantidad: ${cantidad} sándwiches</div>
                            <div>Planchas: ${planchas} x ${precioPlancha.toLocaleString()}</div>
                            <div>Tipo: ${tipo === 'premium' ? 'Premium' : 'Común'}</div>
                        </div>
                    </div>
                    <div class="flex justify-between items-center font-bold text-lg">
                        <span>Total:</span>
                        <span class="text-green-600">${precioTotal.toLocaleString()}</span>
                    </div>
                    ${formaPago.value === 'Efectivo' ? 
                        '<div class="text-xs text-green-600">✓ Descuento efectivo aplicado</div>' : 
                        ''
                    }
                `;
            }
        }

        // Manejo de productos predefinidos
        document.addEventListener('change', function(e) {
            if (e.target.name === 'producto_id') {
                const formaPago = document.querySelector('input[name="forma_pago"]:checked');
                if (!formaPago) return;
                
                const option = e.target.closest('.producto-option');
                const precioEfectivo = parseInt(option.dataset.precioEfectivo);
                const precioTransferencia = parseInt(option.dataset.precioTransferencia);
                const precio = formaPago.value === 'Efectivo' ? precioEfectivo : precioTransferencia;
                const producto = option.querySelector('.font-medium').textContent;
                
                // Mostrar sabores premium si es necesario
                const saboresPremiumDiv = document.getElementById('sabores-premium');
                if (producto.includes('Premium')) {
                    saboresPremiumDiv.classList.remove('hidden');
                } else {
                    saboresPremiumDiv.classList.add('hidden');
                }
                
                // Actualizar resumen
                const resumen = document.getElementById('resumen-pedido');
                resumen.innerHTML = `
                    <div class="border-b pb-3 mb-3">
                        <h4 class="font-medium">${producto}</h4>
                        <div class="text-sm text-gray-600 mt-1">
                            <div>Precio: ${precio.toLocaleString()}</div>
                        </div>
                    </div>
                    <div class="flex justify-between items-center font-bold text-lg">
                        <span>Total:</span>
                        <span class="text-green-600">${precio.toLocaleString()}</span>
                    </div>
                    ${formaPago.value === 'Efectivo' ? 
                        '<div class="text-xs text-green-600">✓ Descuento efectivo aplicado</div>' : 
                        ''
                    }
                `;
            }
            
            // Actualizar precios cuando cambia forma de pago
            if (e.target.name === 'forma_pago') {
                const tipoActivo = document.querySelector('input[name="tipo_pedido"]').value;
                if (tipoActivo === 'personalizado') {
                    actualizarResumenPersonalizado();
                } else {
                    const productoSeleccionado = document.querySelector('input[name="producto_id"]:checked');
                    if (productoSeleccionado) {
                        productoSeleccionado.dispatchEvent(new Event('change', { bubbles: true }));
                    }
                }
            }
            
            // Actualizar resumen personalizado
            if (e.target.name === 'cantidad_personalizada' || 
                e.target.classList.contains('plancha-tipo') ||
                e.target.classList.contains('plancha-sabores')) {
                actualizarResumenPersonalizado();
            }
        });

        // Inicialización
        document.addEventListener('DOMContentLoaded', function() {
            // Configurar cantidad inicial
            const inputCantidad = document.querySelector('input[name="cantidad_personalizada"]');
            if (inputCantidad) {
                actualizarInfoPlancha(parseInt(inputCantidad.value) || 8);
                
                // Event listener para cambios manuales en el input
                inputCantidad.addEventListener('input', function() {
                    validarCantidad(this);
                });
            }
            
            // Configurar toggle de dirección inicial
            toggleDireccion();
        });
    </script>
</body>
</html>