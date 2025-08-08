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
        
        // NUEVOS CAMPOS DE FECHA Y HORA
        $fecha_entrega = $_POST['fecha_entrega'] ?? null;
        $hora_entrega = $_POST['hora_entrega'] ?? null;
        $notas_horario = sanitize($_POST['notas_horario'] ?? '');
        
        // Validar campos obligatorios
        if (!$nombre || !$apellido || !$telefono || !$modalidad || !$forma_pago) {
            throw new Exception('Todos los campos obligatorios deben completarse');
        }
        
        // Validar fecha de entrega (no puede ser anterior a hoy)
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
            
            // Insertar pedido con los nuevos campos
            $stmt = $pdo->prepare("
                INSERT INTO pedidos (nombre, apellido, telefono, direccion, producto, cantidad, precio, 
                                   forma_pago, modalidad, observaciones, cliente_fijo_id, 
                                   fecha_entrega, hora_entrega, notas_horario) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            
            $cliente_fijo_id = $cliente_seleccionado ? $cliente_seleccionado['id'] : null;
            
            $stmt->execute([
                $nombre, $apellido, $telefono, $direccion, $producto, $cantidad, 
                $precio, $forma_pago, $modalidad, $observaciones, $cliente_fijo_id,
                $fecha_entrega, $hora_entrega, $notas_horario
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
                
                <!-- Columna 1: Datos del Cliente + FECHA/HORA -->
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
                        
                        <!-- NUEVOS CAMPOS DE FECHA Y HORA -->
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
                                        <option value="12:00">12:00 PM (Mediodía)</option>
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
                            
                            <!-- Botones rápidos -->
                            <div class="mt-3 flex flex-wrap gap-2">
                                <button type="button" onclick="setHoyAhora()" class="bg-green-100 text-green-800 px-3 py-1 rounded text-xs hover:bg-green-200 transition">
                                    <i class="fas fa-bolt mr-1"></i>Para YA
                                </button>
                                <button type="button" onclick="setMañana()" class="bg-blue-100 text-blue-800 px-3 py-1 rounded text-xs hover:bg-blue-200 transition">
                                    <i class="fas fa-calendar-plus mr-1"></i>Mañana
                                </button>
                                <button type="button" onclick="setMediadia()" class="bg-orange-100 text-orange-800 px-3 py-1 rounded text-xs hover:bg-orange-200 transition">
                                    <i class="fas fa-sun mr-1"></i>Mediodía
                                </button>
                            </div>
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
                        
                        <!-- Selección de sabores Premium MEJORADA -->
                        <div id="sabores-premium" class="mt-4 hidden">
                            <h4 class="font-medium mb-2">Seleccionar Sabores (<span id="sabores-cantidad">6</span>):</h4>
                            <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-3 mb-3">
                                <p class="text-sm text-yellow-800">
                                    <i class="fas fa-info-circle mr-1"></i>
                                    Podés repetir sabores. Ejemplo: 4 de Jamón Crudo + 2 de Atún = 6 total
                                </p>
                            </div>
                            
                            <div class="grid grid-cols-1 gap-3 max-h-60 overflow-y-auto">
                                <?php foreach ($sabores_premium as $sabor): ?>
                                    <div class="flex items-center justify-between bg-white border rounded-lg p-3">
                                        <label class="flex items-center flex-1">
                                            <span class="font-medium text-gray-700"><?= $sabor ?></span>
                                        </label>
                                        <div class="flex items-center space-x-2">
                                            <button type="button" onclick="cambiarCantidadSabor('<?= $sabor ?>', -1)" 
                                                    class="bg-red-100 text-red-700 w-8 h-8 rounded-full hover:bg-red-200 transition">
                                                <i class="fas fa-minus text-xs"></i>
                                            </button>
                                            <input type="number" id="sabor_<?= str_replace([' ', 'ó'], ['_', 'o'], $sabor) ?>" 
                                                   name="sabores_premium[<?= $sabor ?>]" value="0" min="0" 
                                                   class="w-12 text-center border rounded text-sm font-medium"
                                                   onchange="updateSaboresPremium()">
                                            <button type="button" onclick="cambiarCantidadSabor('<?= $sabor ?>', 1)" 
                                                    class="bg-green-100 text-green-700 w-8 h-8 rounded-full hover:bg-green-200 transition">
                                                <i class="fas fa-plus text-xs"></i>
                                            </button>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                            
                            <div class="bg-blue-50 border border-blue-200 rounded-lg p-3 mt-3">
                                <div class="text-sm text-blue-800">
                                    <div class="flex justify-between">
                                        <span>Total seleccionados:</span>
                                        <span id="sabores-seleccionados-total" class="font-bold">0</span>
                                    </div>
                                    <div class="flex justify-between">
                                        <span>Máximo permitido:</span>
                                        <span id="sabores-maximo" class="font-bold">6</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Tab Personalizado ARREGLADO -->
                    <div id="content-personalizado" class="tab-content hidden">
                        <div class="space-y-4">
                            <!-- Cantidad total -->
                            <div>
                                <label class="block text-gray-700 mb-2">Cantidad Total de Sándwiches:</label>
                                <input type="number" name="cantidad_personalizada" min="1" max="200" value="8"
                                       class="w-full px-3 py-2 border rounded-lg" onchange="updatePersonalizadoComplejo()">
                                <div class="text-sm text-gray-600 mt-1">
                                    Planchas necesarias: <span id="planchas-totales">1</span>
                                </div>
                            </div>
                            
                            <!-- Campos ocultos para compatibilidad con backend -->
                            <input type="hidden" name="tipo_personalizado" id="tipo_personalizado_hidden" value="comun">
                            
                            <!-- Lista de planchas dinámicas -->
                            <div id="planchas-container" class="space-y-3">
                                <!-- Las planchas se generan dinámicamente con JavaScript -->
                            </div>
                            
                            <!-- Resumen de precios -->
                            <div class="bg-blue-50 p-4 rounded-lg">
                                <h4 class="font-medium mb-2">
                                    <i class="fas fa-calculator mr-1"></i>Resumen de Facturación:
                                </h4>
                                <div id="resumen-planchas" class="space-y-1 text-sm">
                                    <div class="text-gray-500">Configurando planchas...</div>
                                </div>
                                <hr class="my-2">
                                <div class="font-bold text-lg">
                                    Total: <span id="total-personalizado-complejo">$0</span>
                                </div>
                            </div>
                            
                            <!-- Información adicional -->
                            <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-3">
                                <h4 class="font-medium text-yellow-800 mb-2">
                                    <i class="fas fa-info-circle mr-1"></i>Información:
                                </h4>
                                <ul class="text-sm text-yellow-700 space-y-1">
                                    <li>• Cada plancha contiene 8 sándwiches</li>
                                    <li>• Común: $3,500 por plancha ($7,000 premium)</li>
                                    <li>• Podés mezclar sabores comunes y premium</li>
                                    <li>• El descuento por efectivo se aplica automáticamente</li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Columna 3: Resumen + INFO DE FECHA -->
                <div class="bg-white rounded-lg shadow p-6">
                    <h3 class="text-lg font-semibold mb-4">
                        <i class="fas fa-receipt text-purple-500 mr-2"></i>Resumen del Pedido
                    </h3>
                    
                    <!-- INFO ADICIONAL DE FECHA/HORA -->
                    <div class="bg-blue-50 border border-blue-200 rounded-lg p-3 mb-4">
                        <h4 class="font-medium text-blue-800 mb-2">
                            <i class="fas fa-info-circle mr-1"></i>Información del Pedido
                        </h4>
                        <div class="text-sm text-blue-700 space-y-1">
                            <div><strong>Tomado:</strong> <?= date('d/m/Y H:i') ?></div>
                            <div id="info-entrega"><strong>Para:</strong> <span class="text-gray-500">Seleccionar fecha/hora</span></div>
                            <div id="info-tiempo-prep" class="hidden"><strong>Tiempo:</strong> <span></span></div>
                        </div>
                    </div>
                    
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
        
        // FUNCIONES PARA FECHA/HORA
        function setHoyAhora() {
            document.querySelector('input[name="fecha_entrega"]').value = new Date().toISOString().split('T')[0];
            document.querySelector('select[name="hora_entrega"]').value = '';
            document.querySelector('input[name="notas_horario"]').value = 'URGENTE - Para ya';
            updateInfoEntrega();
        }
        
        function setMañana() {
            const mañana = new Date();
            mañana.setDate(mañana.getDate() + 1);
            document.querySelector('input[name="fecha_entrega"]').value = mañana.toISOString().split('T')[0];
            document.querySelector('select[name="hora_entrega"]').value = '';
            document.querySelector('input[name="notas_horario"]').value = '';
            updateInfoEntrega();
        }
        
        function setMediadia() {
            document.querySelector('input[name="fecha_entrega"]').value = new Date().toISOString().split('T')[0];
            document.querySelector('select[name="hora_entrega"]').value = '12:00';
            document.querySelector('input[name="notas_horario"]').value = '';
            updateInfoEntrega();
        }
        
        function updateInfoEntrega() {
            const fecha = document.querySelector('input[name="fecha_entrega"]').value;
            const hora = document.querySelector('select[name="hora_entrega"]').value;
            const notas = document.querySelector('input[name="notas_horario"]').value;
            
            const infoDiv = document.getElementById('info-entrega');
            const tiempoDiv = document.getElementById('info-tiempo-prep');
            
            if (fecha) {
                const fechaObj = new Date(fecha);
                const hoy = new Date();
                const esMañana = fechaObj.toDateString() !== hoy.toDateString();
                
                let texto = fechaObj.toLocaleDateString('es-AR');
                if (hora) texto += ` a las ${hora}`;
                if (notas) texto += ` (${notas})`;
                
                infoDiv.innerHTML = `<strong>Para:</strong> ${texto}`;
                
                // Mostrar tiempo de preparación
                if (esMañana) {
                    tiempoDiv.innerHTML = '<strong>Tiempo:</strong> <span class="text-green-600">Con tiempo suficiente</span>';
                    tiempoDiv.classList.remove('hidden');
                } else if (notas.toLowerCase().includes('urgente') || notas.toLowerCase().includes('ya')) {
                    tiempoDiv.innerHTML = '<strong>Tiempo:</strong> <span class="text-red-600">URGENTE</span>';
                    tiempoDiv.classList.remove('hidden');
                } else {
                    tiempoDiv.classList.add('hidden');
                }
            } else {
                infoDiv.innerHTML = '<strong>Para:</strong> <span class="text-gray-500">Seleccionar fecha/hora</span>';
                tiempoDiv.classList.add('hidden');
            }
        }
        
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
                
                // IMPORTANTE: Generar planchas cuando se cambia a personalizado
                updatePersonalizadoComplejo();
            }
            
            // Update hidden input
            document.getElementById('tipo_pedido_hidden').value = tab;
            
            updateResumen();
        }
        
        // Seleccionar producto predefinido MEJORADO
        function selectProduct(id, name, precioEfectivo, precioTransferencia) {
            currentProduct = {
                id: id,
                name: name,
                precioEfectivo: precioEfectivo,
                precioTransferencia: precioTransferencia
            };
            
            // Mostrar sabores premium si es necesario
            const saboresDiv = document.getElementById('sabores-premium');
            if (saboresDiv && name.includes('Premium')) {
                saboresDiv.classList.remove('hidden');
                const cantidad = name.includes('48') ? 6 : 3;
                document.getElementById('sabores-cantidad').textContent = cantidad;
                document.getElementById('sabores-maximo').textContent = cantidad;
                
                // Reset todas las cantidades de sabores
                document.querySelectorAll('input[name^="sabores_premium"]').forEach(input => {
                    input.value = 0;
                });
                updateSaboresPremium();
            } else if (saboresDiv) {
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
                updatePersonalizadoComplejo(); // También actualizar personalizado
            }
        }
        
        // FUNCIONES PARA SABORES PREMIUM CON CANTIDADES
        function cambiarCantidadSabor(sabor, cambio) {
            const inputId = 'sabor_' + sabor.replace(/ /g, '_').replace(/ó/g, 'o');
            const input = document.getElementById(inputId);
            if (!input) return;
            
            let nuevaCantidad = parseInt(input.value) + cambio;
            
            if (nuevaCantidad < 0) nuevaCantidad = 0;
            
            // Verificar límite máximo
            const maximoSpan = document.getElementById('sabores-maximo');
            const maxPermitido = maximoSpan ? parseInt(maximoSpan.textContent) : 6;
            const totalActual = calcularTotalSabores() - parseInt(input.value); // Restar el valor actual
            
            if (totalActual + nuevaCantidad > maxPermitido) {
                alert(`No podés superar los ${maxPermitido} sándwiches para este producto.`);
                return;
            }
            
            input.value = nuevaCantidad;
            updateSaboresPremium();
        }

        function calcularTotalSabores() {
            let total = 0;
            document.querySelectorAll('input[name^="sabores_premium"]').forEach(input => {
                total += parseInt(input.value) || 0;
            });
            return total;
        }

        // Actualizar sabores premium MEJORADA
        function updateSaboresPremium() {
            const totalSeleccionado = calcularTotalSabores();
            const maximoSpan = document.getElementById('sabores-maximo');
            const maxPermitido = maximoSpan ? parseInt(maximoSpan.textContent) : 6;
            
            const totalSpan = document.getElementById('sabores-seleccionados-total');
            if (totalSpan) {
                totalSpan.textContent = totalSeleccionado;
                
                // Cambiar color según el estado
                if (totalSeleccionado === maxPermitido) {
                    totalSpan.className = 'font-bold text-green-600';
                } else if (totalSeleccionado > maxPermitido) {
                    totalSpan.className = 'font-bold text-red-600';
                } else {
                    totalSpan.className = 'font-bold text-blue-800';
                }
            }
            
            updateResumen();
        }
        
        // Función para manejar pedidos personalizados complejos
        function updatePersonalizadoComplejo() {
            console.log('Actualizando personalizado complejo...');
            
            const cantidadInput = document.querySelector('input[name="cantidad_personalizada"]');
            if (!cantidadInput) {
                console.log('No se encontró input de cantidad personalizada');
                return;
            }
            
            const cantidad = parseInt(cantidadInput.value) || 8;
            const planchas = Math.ceil(cantidad / 8);
            
            // Actualizar display de planchas totales
            const planchasTotalesSpan = document.getElementById('planchas-totales');
            if (planchasTotalesSpan) {
                planchasTotalesSpan.textContent = planchas;
            }
            
            // Generar contenido dinámico para las planchas
            const planchasContainer = document.getElementById('planchas-container');
            const resumenPlanchas = document.getElementById('resumen-planchas');
            const totalPersonalizadoComplejo = document.getElementById('total-personalizado-complejo');
            
            if (!planchasContainer || !resumenPlanchas || !totalPersonalizadoComplejo) {
                console.log('Elementos de personalizado complejo no encontrados');
                return;
            }
            
            // Lista de sabores disponibles
            const saboresComunes = ['Jamón y Queso', 'Lechuga y Tomate', 'Huevo', 'Choclo', 'Aceitunas'];
            const saboresPremium = ['Jamón y Queso', 'Ananá', 'Atún', 'Berenjena', 'Durazno', 'Jamón Crudo', 'Morrón', 'Palmito', 'Panceta', 'Pollo', 'Roquefort', 'Salame'];
            
            // Generar HTML para cada plancha
            let planchasHTML = '';
            
            for (let i = 1; i <= planchas; i++) {
                const sandwichesEnPlancha = (i === planchas && cantidad % 8 !== 0) ? cantidad % 8 : 8;
                
                planchasHTML += `
                    <div class="border rounded-lg p-4 bg-gray-50">
                        <h4 class="font-medium mb-3">Plancha ${i} (${sandwichesEnPlancha} sándwiches)</h4>
                        
                        <div class="space-y-3">
                            <!-- Tipo de plancha -->
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Tipo:</label>
                                <div class="space-y-2">
                                    <label class="flex items-center">
                                        <input type="radio" name="tipo_plancha_${i}" value="comun" class="mr-2" onchange="calcularPreciosPlancha()" checked>
                                        <span>Común ($3,500)</span>
                                    </label>
                                    <label class="flex items-center">
                                        <input type="radio" name="tipo_plancha_${i}" value="premium" class="mr-2" onchange="calcularPreciosPlancha()">
                                        <span>Premium ($7,000)</span>
                                    </label>
                                </div>
                            </div>
                            
                            <!-- Sabor -->
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Sabor:</label>
                                <select name="sabor_plancha_${i}" class="w-full px-3 py-2 border rounded-lg text-sm" id="sabor_select_${i}">
                                    ${saboresComunes.map(sabor => `<option value="${sabor}">${sabor}</option>`).join('')}
                                </select>
                            </div>
                        </div>
                    </div>
                `;
            }
            
            planchasContainer.innerHTML = planchasHTML;
            
            // Agregar event listeners a los radios de tipo
            for (let i = 1; i <= planchas; i++) {
                const radiosComun = document.querySelectorAll(`input[name="tipo_plancha_${i}"]`);
                radiosComun.forEach(radio => {
                    radio.addEventListener('change', function() {
                        updateSaboresDisponibles(i, this.value);
                        calcularPreciosPlancha();
                    });
                });
            }
            
            // Calcular precios iniciales
            calcularPreciosPlancha();
        }
        
        // Función para actualizar sabores disponibles según el tipo
        function updateSaboresDisponibles(planchaNum, tipo) {
            const saborSelect = document.getElementById(`sabor_select_${planchaNum}`);
            if (!saborSelect) return;
            
            const saboresComunes = ['Jamón y Queso', 'Lechuga y Tomate', 'Huevo', 'Choclo', 'Aceitunas'];
            const saboresPremium = ['Jamón y Queso', 'Ananá', 'Atún', 'Berenjena', 'Durazno', 'Jamón Crudo', 'Morrón', 'Palmito', 'Panceta', 'Pollo', 'Roquefort', 'Salame'];
            
            const sabores = tipo === 'premium' ? saboresPremium : saboresComunes;
            saborSelect.innerHTML = sabores.map(sabor => `<option value="${sabor}">${sabor}</option>`).join('');
        }
        
        // Función para calcular precios de las planchas
        function calcularPreciosPlancha() {
            const cantidadInput = document.querySelector('input[name="cantidad_personalizada"]');
            if (!cantidadInput) return;
            
            const cantidad = parseInt(cantidadInput.value) || 8;
            const planchas = Math.ceil(cantidad / 8);
            const formaPago = document.querySelector('input[name="forma_pago"]:checked')?.value || 'Efectivo';
            const descuento = formaPago === 'Efectivo' ? 0.9 : 1;
            
            let totalPrecio = 0;
            let resumenHTML = '';
            let saboresPersonalizados = [];
            
            for (let i = 1; i <= planchas; i++) {
                const tipoRadio = document.querySelector(`input[name="tipo_plancha_${i}"]:checked`);
                if (!tipoRadio) continue;
                
                const tipo = tipoRadio.value;
                const precioBase = tipo === 'premium' ? 7000 : 3500;
                const precioFinal = Math.round(precioBase * descuento);
                
                const sandwichesEnPlancha = (i === planchas && cantidad % 8 !== 0) ? cantidad % 8 : 8;
                const saborSelect = document.getElementById(`sabor_select_${i}`);
                const sabor = saborSelect ? saborSelect.value : 'Sin definir';
                
                totalPrecio += precioFinal;
                saboresPersonalizados.push(sabor);
                
                resumenHTML += `
                    <div class="flex justify-between text-sm">
                        <span>Plancha ${i} (${tipo}): ${sabor}</span>
                        <span>${precioFinal.toLocaleString()}</span>
                    </div>
                `;
            }
            
            // Actualizar tipo general (usar el más común)
            const tiposSeleccionados = [];
            for (let i = 1; i <= planchas; i++) {
                const tipoRadio = document.querySelector(`input[name="tipo_plancha_${i}"]:checked`);
                if (tipoRadio) tiposSeleccionados.push(tipoRadio.value);
            }
            
            const tipoGeneral = tiposSeleccionados.filter(tipo => tipo === 'premium').length > tiposSeleccionados.length / 2 ? 'premium' : 'comun';
            const tipoHidden = document.getElementById('tipo_personalizado_hidden');
            if (tipoHidden) {
                tipoHidden.value = tipoGeneral;
            }
            
            // Actualizar resumen
            const resumenPlanchas = document.getElementById('resumen-planchas');
            const totalPersonalizadoComplejo = document.getElementById('total-personalizado-complejo');
            
            if (resumenPlanchas) {
                resumenPlanchas.innerHTML = resumenHTML;
            }
            
            if (totalPersonalizadoComplejo) {
                totalPersonalizadoComplejo.textContent = `${totalPrecio.toLocaleString()}`;
            }
            
            // Actualizar resumen general
            updateResumen();
        }
        
        // Actualizar personalizado (mantenido para compatibilidad)
        function updatePersonalizado() {
            updatePersonalizadoComplejo();
        }
        
        // Actualizar resumen
        function updateResumen() {
            const resumenDiv = document.getElementById('resumen-pedido');
            if (!resumenDiv) return;
            
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
                        <span class="text-green-600">${precio.toLocaleString()}</span>
                    </div>
                `;
                
                if (currentProduct.name.includes('Premium')) {
                    const saboresSeleccionados = [];
                    document.querySelectorAll('input[name^="sabores_premium"]').forEach(input => {
                        const cantidad = parseInt(input.value);
                        if (cantidad > 0) {
                            const sabor = input.name.match(/\[(.*?)\]/)[1];
                            saboresSeleccionados.push(`${cantidad}x ${sabor}`);
                        }
                    });
                    if (saboresSeleccionados.length > 0) {
                        html += `
                            <div class="mt-3 text-sm">
                                <div class="font-medium">Sabores seleccionados:</div>
                                <div class="text-gray-600">${saboresSeleccionados.join(', ')}</div>
                            </div>
                        `;
                    }
                }
            } else if (activeTab === 'personalizado') {
                const cantidadInput = document.querySelector('input[name="cantidad_personalizada"]');
                const cantidad = cantidadInput ? parseInt(cantidadInput.value) || 8 : 8;
                const planchas = Math.ceil(cantidad / 8);
                
                // Calcular precio promedio
                const totalSpan = document.getElementById('total-personalizado-complejo');
                const totalTexto = totalSpan ? totalSpan.textContent : '$0';
                
                const descuentoText = paymentMethod === 'Efectivo' ? ' (con descuento)' : '';
                
                html = `
                    <div class="border-b pb-3">
                        <div class="font-medium">Personalizado x${cantidad}</div>
                        <div class="text-sm text-gray-600">${planchas} plancha${planchas > 1 ? 's' : ''} - Pago: ${paymentMethod}${descuentoText}</div>
                    </div>
                    <div class="flex justify-between items-center font-bold text-lg">
                        <span>Total:</span>
                        <span class="text-green-600">${totalTexto}</span>
                    </div>
                `;
                
                // Mostrar sabores seleccionados
                let sabores = [];
                const planchasCount = Math.ceil(cantidad / 8);
                for (let i = 1; i <= planchasCount; i++) {
                    const saborSelect = document.getElementById(`sabor_select_${i}`);
                    if (saborSelect) {
                        sabores.push(saborSelect.value);
                    }
                }
                
                if (sabores.length > 0) {
                    html += `
                        <div class="mt-3 text-sm">
                            <div class="font-medium">Sabores:</div>
                            <div class="text-gray-600">${sabores.join(', ')}</div>
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
            
            // Event listeners para sabores premium
            document.querySelectorAll('input[name^="sabores_premium"]').forEach(input => {
                input.addEventListener('change', updateSaboresPremium);
            });
            
            // Cantidad personalizada input
            const cantidadInput = document.querySelector('input[name="cantidad_personalizada"]');
            if (cantidadInput) {
                cantidadInput.addEventListener('input', updatePersonalizadoComplejo);
                cantidadInput.addEventListener('change', updatePersonalizadoComplejo);
            }
            
            // Payment method change
            document.querySelectorAll('input[name="forma_pago"]').forEach(radio => {
                radio.addEventListener('change', updatePrecios);
            });
            
            // Event listeners para fecha/hora
            const fechaInput = document.querySelector('input[name="fecha_entrega"]');
            const horaSelect = document.querySelector('select[name="hora_entrega"]');
            const notasInput = document.querySelector('input[name="notas_horario"]');
            
            if (fechaInput) fechaInput.addEventListener('change', updateInfoEntrega);
            if (horaSelect) horaSelect.addEventListener('change', updateInfoEntrega);
            if (notasInput) notasInput.addEventListener('input', updateInfoEntrega);
            
            // Initialize payment method
            const selectedPayment = document.querySelector('input[name="forma_pago"]:checked');
            if (selectedPayment) {
                paymentMethod = selectedPayment.value;
            }
            
            // Initialize personalizado calculations
            updatePersonalizadoComplejo();
            
            // Initialize info entrega
            updateInfoEntrega();
        });
        
        // Validación antes de enviar
        document.getElementById('pedidoForm').addEventListener('submit', function(e) {
            const tipoPedido = document.getElementById('tipo_pedido_hidden').value;
            
            if (tipoPedido === 'predefinido') {
                const productoSeleccionado = document.querySelector('input[name="producto_id"]:checked');
                if (!productoSeleccionado) {
                    e.preventDefault();
                    alert('Por favor selecciona un producto.');
                    return false;
                }
            } else if (tipoPedido === 'personalizado') {
                const cantidad = parseInt(document.querySelector('input[name="cantidad_personalizada"]').value);
                
                if (cantidad <= 0) {
                    e.preventDefault();
                    alert('La cantidad debe ser mayor a 0.');
                    return false;
                }
                
                // Verificar que hay al menos una plancha configurada
                const primerTipo = document.querySelector('input[name="tipo_plancha_1"]:checked');
                if (!primerTipo) {
                    e.preventDefault();
                    alert('Error en la configuración de planchas.');
                    return false;
                }
                
                // Crear elementos ocultos para los sabores (compatibilidad con backend)
                const form = document.getElementById('pedidoForm');
                
                // Limpiar sabores anteriores
                const oldSabores = form.querySelectorAll('input[name="sabores_personalizados[]"]');
                oldSabores.forEach(el => el.remove());
                
                // Agregar nuevos sabores
                const cantidadTotal = parseInt(document.querySelector('input[name="cantidad_personalizada"]').value) || 8;
                const planchas = Math.ceil(cantidadTotal / 8);
                
                for (let i = 1; i <= planchas; i++) {
                    const saborSelect = document.getElementById(`sabor_select_${i}`);
                    if (saborSelect) {
                        const input = document.createElement('input');
                        input.type = 'hidden';
                        input.name = 'sabores_personalizados[]';
                        input.value = saborSelect.value;
                        form.appendChild(input);
                    }
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
        
        /* Animaciones para botones rápidos */
        .bg-green-100:hover, .bg-blue-100:hover, .bg-orange-100:hover {
            transform: translateY(-1px);
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
    </style>
</body>
</html>