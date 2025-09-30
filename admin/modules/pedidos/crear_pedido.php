<?php
require_once '../../config.php';
requireLogin();

$pdo = getConnection();

// Obtener productos de la base de datos
$productos = $pdo->query("SELECT * FROM productos WHERE activo = 1 ORDER BY orden_mostrar, nombre")->fetchAll();

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
        // Datos del cliente
        $nombre = trim($_POST['nombre'] ?? '');
        $apellido = trim($_POST['apellido'] ?? '');
        $telefono = trim($_POST['telefono'] ?? '');
        $direccion = trim($_POST['direccion'] ?? '');
        $modalidad = $_POST['modalidad'] ?? '';
        $ubicacion = $_POST['ubicacion'] ?? '';
        $forma_pago = $_POST['forma_pago'] ?? '';
        $turno_delivery = $_POST['turno_delivery'] ?? null;
        $observaciones = trim($_POST['observaciones'] ?? '');
        
        // Agregar turno a observaciones si hay
        if ($turno_delivery) {
            $observaciones .= "\nTurno: " . ucfirst($turno_delivery);
        }
        
        // Campos de fecha y hora
        $fecha_entrega = $_POST['fecha_entrega'] ?? null;
        $hora_entrega = $_POST['hora_entrega'] ?? null;
        $notas_horario = trim($_POST['notas_horario'] ?? '');
        
        // Validar campos obligatorios
        if (!$nombre || !$apellido || !$modalidad || !$ubicacion || !$forma_pago) {
            throw new Exception('Todos los campos obligatorios deben completarse');
        }
        
        // Dirección obligatoria solo si es Delivery
        if ($modalidad === 'Delivery' && empty(trim($direccion))) {
            throw new Exception('La dirección es obligatoria para pedidos de delivery');
        }
        
        // Turno obligatorio para ambos casos (Retira o Delivery)
        if (empty($turno_delivery)) {
            throw new Exception('Debe seleccionar un turno de entrega');
        }
        
        // Validar fecha de entrega
        if ($fecha_entrega && $fecha_entrega < date('Y-m-d')) {
            throw new Exception('La fecha de entrega no puede ser anterior a hoy');
        }
        
        // Procesar el pedido según el tipo
        $tipo_pedido = $_POST['tipo_pedido'] ?? '';
        $producto = '';
        $cantidad = 0;
        $precio = 0;
        
        if ($tipo_pedido === 'predefinido') {
            // PRODUCTOS PREDEFINIDOS
            $producto_id = (int)($_POST['producto_id'] ?? 0);
            if (!$producto_id) {
                throw new Exception('Debe seleccionar un producto');
            }
            
            $stmt = $pdo->prepare("SELECT * FROM productos WHERE id = ? AND activo = 1");
            $stmt->execute([$producto_id]);
            $prod_data = $stmt->fetch();
            
            if (!$prod_data) {
                throw new Exception('Producto no encontrado');
            }
            
            $producto = $prod_data['nombre'];
            $precio = ($forma_pago === 'Efectivo') ? 
                     $prod_data['precio_efectivo'] : $prod_data['precio_transferencia'];
            
            // Extraer cantidad del nombre del producto si corresponde
            preg_match('/^(\d+)/', $producto, $matches);
            $cantidad = isset($matches[1]) ? (int)$matches[1] : 1;
            
            // Agregar sabores premium si se seleccionaron
            if (strpos($producto, 'Premium') !== false) {
                $sabores_premium_sel = $_POST['sabores_premium'] ?? [];
                if (!empty($sabores_premium_sel)) {
                    $observaciones .= "\nSabores premium: " . implode(', ', $sabores_premium_sel);
                }
            }
            
        } elseif ($tipo_pedido === 'personalizado') {
            // PEDIDOS PERSONALIZADOS
            $cantidad_personalizada = (int)($_POST['cantidad_personalizada'] ?? 8);
            if ($cantidad_personalizada < 8) {
                throw new Exception('La cantidad mínima es 8 sándwiches');
            }
            
            $planchas = ceil($cantidad_personalizada / 8);
            $precio_total = 0;
            $detalles_planchas = [];
            $sabores_todos = [];
            
            for ($i = 1; $i <= $planchas; $i++) {
                $tipo_plancha = $_POST["plancha_{$i}_tipo"] ?? 'comun';
                $sabores_plancha = trim($_POST["plancha_{$i}_sabores"] ?? '');
                
                $precio_plancha_base = ($tipo_plancha === 'premium') ? 7000 : 3500;
                $precio_plancha = ($forma_pago === 'Efectivo') ? $precio_plancha_base * 0.9 : $precio_plancha_base;
                $precio_total += $precio_plancha;
                
                $detalles_planchas[] = "Plancha $i: " . ucfirst($tipo_plancha) . 
                                      " ($" . number_format($precio_plancha, 0, ',', '.') . ")" . 
                                      ($sabores_plancha ? " - $sabores_plancha" : "");
                
                if ($sabores_plancha) {
                    $sabores_todos[] = $sabores_plancha;
                }
            }
            
            $producto = "Personalizado x$cantidad_personalizada ($planchas plancha" . ($planchas > 1 ? 's' : '') . ")";
            $observaciones .= "\nDetalle de planchas:\n" . implode("\n", $detalles_planchas);
            if (!empty($sabores_todos)) {
                $observaciones .= "\nSabores: " . implode(" | ", $sabores_todos);
            }
            
            $cantidad = $cantidad_personalizada;
            $precio = $precio_total;
            
        } else {
            throw new Exception('Debe seleccionar un tipo de pedido');
        }
        
        // Cliente fijo si aplica
        $cliente_fijo_id = $cliente_seleccionado ? $cliente_seleccionado['id'] : null;
        
        // Insertar pedido
        $stmt = $pdo->prepare("
            INSERT INTO pedidos (nombre, apellido, telefono, direccion, producto, cantidad, precio, 
                               forma_pago, modalidad, ubicacion, observaciones, cliente_fijo_id,
                               fecha_entrega, hora_entrega, notas_horario) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        
        $success = $stmt->execute([
            $nombre, $apellido, $telefono, $direccion, $producto, $cantidad, $precio,
            $forma_pago, $modalidad, $ubicacion, $observaciones, $cliente_fijo_id,
            $fecha_entrega, $hora_entrega, $notas_horario
        ]);
        
        if ($success) {
            $mensaje = "Pedido creado correctamente: $producto";
            $_POST = []; // Limpiar formulario
        } else {
            throw new Exception('Error al guardar el pedido');
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
    <style>
        .producto-card { 
            transition: all 0.2s ease; 
            cursor: pointer;
        }
        .producto-card:hover { 
            transform: translateY(-2px); 
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        }
        .producto-card.selected { 
            border-color: #3b82f6;
            background-color: #eff6ff;
            box-shadow: 0 0 0 2px #3b82f6;
        }
        .plancha-config {
            background: #f8f9fa;
            border: 1px solid #dee2e6;
            border-radius: 8px;
            padding: 12px;
            margin-bottom: 8px;
        }
    </style>
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
                
                <!-- COLUMNA 1: DATOS DEL CLIENTE -->
                <div class="bg-white rounded-lg shadow p-6">
                    <h3 class="text-lg font-semibold mb-4">
                        <i class="fas fa-user text-blue-500 mr-2"></i>Datos del Cliente
                    </h3>
                    
                    <div class="space-y-4">
                        <div>
                            <label class="block text-gray-700 mb-2 font-medium">
                                Nombre <span class="text-red-500">*</span>
                            </label>
                            <input type="text" name="nombre" required 
                                   value="<?= htmlspecialchars($_POST['nombre'] ?? $cliente_seleccionado['nombre'] ?? '') ?>"
                                   class="w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500">
                        </div>

                        <div>
                            <label class="block text-gray-700 mb-2 font-medium">
                                Apellido <span class="text-red-500">*</span>
                            </label>
                            <input type="text" name="apellido" required 
                                   value="<?= htmlspecialchars($_POST['apellido'] ?? $cliente_seleccionado['apellido'] ?? '') ?>"
                                   class="w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500">
                        </div>

                        <div>
                            <label class="block text-gray-700 mb-2 font-medium">
                                Teléfono
                            </label>
                            <input type="tel" name="telefono"
                                   value="<?= htmlspecialchars($_POST['telefono'] ?? $cliente_seleccionado['telefono'] ?? '') ?>"
                                   class="w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500">
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
                                <select name="ubicacion" required class="w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500">
                                    <option value="">Seleccionar...</option>
                                    <option value="Local 1" <?= ($_POST['ubicacion'] ?? '') === 'Local 1' ? 'selected' : '' ?>>Local 1</option>
                                    <option value="Fábrica" <?= ($_POST['ubicacion'] ?? '') === 'Fábrica' ? 'selected' : '' ?>>Fábrica</option>
                                </select>
                            </div>
                        </div>

                        <!-- Dirección y Turno - Se ajusta según modalidad -->
                        <div id="direccion-container">
                            <label class="block text-gray-700 mb-2 font-medium">
                                Dirección <span id="direccion-required" class="text-red-500 hidden">*</span>
                            </label>
                            <textarea name="direccion" rows="2" id="direccion-input"
                                      class="w-full px-3 py-2 border rounded-lg focus:outline-none focus:border-blue-500"
                                      placeholder="Solo obligatoria para delivery"><?= htmlspecialchars($_POST['direccion'] ?? $cliente_seleccionado['direccion'] ?? '') ?></textarea>
                            <div id="direccion-help" class="text-xs text-gray-500 mt-1 hidden">
                                Campo obligatorio para delivery
                            </div>
                        </div>
                        
                        <!-- Turno de Entrega - SIEMPRE VISIBLE y requerido -->
                        <div id="turno-delivery-container">
                            <label class="block text-gray-700 mb-2 font-medium">Turno de Entrega <span class="text-red-500">*</span></label>
                            <div class="grid grid-cols-3 gap-2">
                                <label class="flex items-center p-2 border rounded-lg cursor-pointer hover:bg-blue-50">
                                    <input type="radio" name="turno_delivery" value="mañana" class="mr-2" required
                                           <?= (($_POST['turno_delivery'] ?? '') === 'mañana') ? 'checked' : '' ?>>
                                    <div class="text-center w-full">
                                        <i class="fas fa-sun text-yellow-500 block mb-1"></i>
                                        <span class="text-sm font-medium">Mañana</span>
                                        <div class="text-xs text-gray-500">8:00-12:00</div>
                                    </div>
                                </label>
                                <label class="flex items-center p-2 border rounded-lg cursor-pointer hover:bg-orange-50">
                                    <input type="radio" name="turno_delivery" value="siesta" class="mr-2" required
                                           <?= (($_POST['turno_delivery'] ?? '') === 'siesta') ? 'checked' : '' ?>>
                                    <div class="text-center w-full">
                                        <i class="fas fa-cloud-sun text-orange-500 block mb-1"></i>
                                        <span class="text-sm font-medium">Siesta</span>
                                        <div class="text-xs text-gray-500">12:00-16:00</div>
                                    </div>
                                </label>
                                <label class="flex items-center p-2 border rounded-lg cursor-pointer hover:bg-purple-50">
                                    <input type="radio" name="turno_delivery" value="tarde" class="mr-2" required
                                           <?= (($_POST['turno_delivery'] ?? '') === 'tarde') ? 'checked' : '' ?>>
                                    <div class="text-center w-full">
                                        <i class="fas fa-moon text-purple-500 block mb-1"></i>
                                        <span class="text-sm font-medium">Tarde</span>
                                        <div class="text-xs text-gray-500">16:00-20:00</div>
                                    </div>
                                </label>
                            </div>
                        </div>

                        <div>
                            <label class="block text-gray-700 mb-2 font-medium">
                                Forma de Pago <span class="text-red-500">*</span>
                            </label>
                            <div class="space-y-2">
                                <label class="flex items-center">
                                    <input type="radio" name="forma_pago" value="Efectivo" required 
                                           <?= ($_POST['forma_pago'] ?? '') === 'Efectivo' ? 'checked' : '' ?>
                                           class="mr-2" onchange="actualizarPrecios()">
                                    <i class="fas fa-money-bills mr-2 text-green-500"></i>Efectivo (10% desc.)
                                </label>
                                <label class="flex items-center">
                                    <input type="radio" name="forma_pago" value="Transferencia" required 
                                           <?= ($_POST['forma_pago'] ?? '') === 'Transferencia' ? 'checked' : '' ?>
                                           class="mr-2" onchange="actualizarPrecios()">
                                    <i class="fas fa-university mr-2 text-blue-500"></i>Transferencia
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

                <!-- COLUMNA 2: SELECCIONAR PRODUCTOS -->
                <div class="bg-white rounded-lg shadow p-6">
                    <h3 class="text-lg font-semibold mb-4">
                        <i class="fas fa-shopping-cart text-purple-500 mr-2"></i>Seleccionar Producto
                    </h3>

                    <!-- Tabs -->
                    <div class="border-b border-gray-200 mb-4">
                        <nav class="-mb-px flex space-x-8">
                            <button type="button" id="tab-predefinido" onclick="mostrarTab('predefinido')"
                                    class="py-2 px-1 border-b-2 font-medium text-sm border-blue-500 text-blue-600">
                                Productos del Menú
                            </button>
                            <button type="button" id="tab-personalizado" onclick="mostrarTab('personalizado')"
                                    class="py-2 px-1 border-b-2 font-medium text-sm border-transparent text-gray-500 hover:text-blue-600">
                                Personalizado
                            </button>
                        </nav>
                    </div>

                    <input type="hidden" name="tipo_pedido" id="tipo_pedido" value="predefinido">

                    <!-- TAB PREDEFINIDOS -->
                    <div id="content-predefinido">
                        <div class="space-y-3 max-h-96 overflow-y-auto">
                            <?php foreach ($productos as $producto): ?>
                                <div class="producto-card border rounded-lg p-4" 
                                     onclick="seleccionarProducto(<?= $producto['id'] ?>, '<?= htmlspecialchars(addslashes($producto['nombre'])) ?>', <?= $producto['precio_efectivo'] ?>, <?= $producto['precio_transferencia'] ?>, this)">
                                    
                                    <input type="radio" name="producto_id" value="<?= $producto['id'] ?>" class="hidden" <?= (isset($_POST['producto_id']) && $_POST['producto_id'] == $producto['id']) ? 'checked' : '' ?>>
                                    
                                    <div class="flex justify-between items-start">
                                        <div class="flex-1">
                                            <h4 class="font-medium text-gray-800"><?= htmlspecialchars($producto['nombre']) ?></h4>
                                            <?php if ($producto['descripcion']): ?>
                                                <p class="text-sm text-gray-600 mt-1"><?= htmlspecialchars($producto['descripcion']) ?></p>
                                            <?php endif; ?>
                                        </div>
                                        <div class="text-right ml-4">
                                            <div class="text-sm text-gray-500">Efectivo:</div>
                                            <div class="font-medium text-green-600">$<?= number_format($producto['precio_efectivo'], 0, ',', '.') ?></div>
                                            <div class="text-sm text-gray-500 mt-1">Transferencia:</div>
                                            <div class="font-medium text-blue-600">$<?= number_format($producto['precio_transferencia'], 0, ',', '.') ?></div>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>

                        <!-- Sabores Premium -->
                        <div id="sabores-premium" class="hidden mt-4 p-4 bg-yellow-50 border border-yellow-200 rounded-lg">
                            <h5 class="font-medium text-yellow-800 mb-2">Seleccionar Sabores Premium:</h5>
                            <div class="grid grid-cols-2 gap-2 text-sm">
                                <?php foreach ($sabores_premium as $sabor): ?>
                                    <label class="flex items-center">
                                        <input type="checkbox" name="sabores_premium[]" value="<?= $sabor ?>" class="mr-2" <?= (isset($_POST['sabores_premium']) && in_array($sabor, $_POST['sabores_premium'])) ? 'checked' : '' ?>>
                                        <?= $sabor ?>
                                    </label>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>

                    <!-- TAB PERSONALIZADO -->
                    <div id="content-personalizado" class="hidden">
                        <div class="space-y-4">
                            <!-- Cantidad -->
                            <div>
                                <label class="block text-gray-700 mb-2 font-medium">Cantidad de Sándwiches:</label>
                                <div class="flex items-center space-x-4">
                                    <button type="button" onclick="cambiarCantidad(-8)" class="bg-red-500 text-white px-3 py-2 rounded">-8</button>
                                    <input type="number" name="cantidad_personalizada" id="cantidad_personalizada" 
                                           value="<?= $_POST['cantidad_personalizada'] ?? 8 ?>" min="8" step="8" onchange="generarPlanchas()" 
                                           class="w-20 text-center border rounded px-2 py-1">
                                    <button type="button" onclick="cambiarCantidad(8)" class="bg-green-500 text-white px-3 py-2 rounded">+8</button>
                                    <span id="planchas-info" class="text-gray-600">1 plancha</span>
                                </div>
                            </div>

                            <!-- Configuración de planchas -->
                            <div id="planchas-container">
                                <!-- Se genera dinámicamente con JavaScript -->
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

                <!-- COLUMNA 3: RESUMEN Y OBSERVACIONES -->
                <div class="bg-white rounded-lg shadow p-6">
                    <h3 class="text-lg font-semibold mb-4">
                        <i class="fas fa-clipboard-list text-orange-500 mr-2"></i>Resumen del Pedido
                    </h3>

                    <div id="resumen-pedido" class="mb-6 p-4 bg-gray-50 rounded-lg">
                        <p class="text-gray-500 text-center">Selecciona un producto para ver el resumen</p>
                    </div>

                    <button type="submit" class="w-full bg-blue-600 hover:bg-blue-700 text-white py-3 px-4 rounded-lg font-medium">
                        <i class="fas fa-save mr-2"></i>Crear Pedido
                    </button>
                </div>
            </div>
        </form>
    </main>

    <script>
        let productoSeleccionado = null;
        
        function mostrarTab(tab) {
            // Cambiar contenido
            document.getElementById('content-predefinido').style.display = tab === 'predefinido' ? 'block' : 'none';
            document.getElementById('content-personalizado').style.display = tab === 'personalizado' ? 'block' : 'none';
            
            // Cambiar tabs
            document.getElementById('tab-predefinido').className = tab === 'predefinido' 
                ? 'py-2 px-1 border-b-2 font-medium text-sm border-blue-500 text-blue-600'
                : 'py-2 px-1 border-b-2 font-medium text-sm border-transparent text-gray-500 hover:text-blue-600';
            
            document.getElementById('tab-personalizado').className = tab === 'personalizado' 
                ? 'py-2 px-1 border-b-2 font-medium text-sm border-blue-500 text-blue-600'
                : 'py-2 px-1 border-b-2 font-medium text-sm border-transparent text-gray-500 hover:text-blue-600';
            
            // Cambiar tipo de pedido
            document.getElementById('tipo_pedido').value = tab;
            
            // Limpiar selección anterior
            if (tab === 'predefinido') {
                document.querySelectorAll('.producto-card').forEach(card => card.classList.remove('selected'));
                document.querySelectorAll('input[name="producto_id"]').forEach(radio => radio.checked = false);
                productoSeleccionado = null;
            }
            
            actualizarResumen();
            
            if (tab === 'personalizado') {
                generarPlanchas();
            }
        }
        
        function seleccionarProducto(id, nombre, precioEfectivo, precioTransferencia, elemento) {
            productoSeleccionado = { id, nombre, precioEfectivo, precioTransferencia };
            
            // Marcar visualmente
            document.querySelectorAll('.producto-card').forEach(card => card.classList.remove('selected'));
            if (elemento) elemento.classList.add('selected');
            
            // Marcar radio
            const radio = document.querySelector(`input[name="producto_id"][value="${id}"]`);
            if (radio) radio.checked = true;
            
            // Mostrar sabores premium si aplica
            const saboresDiv = document.getElementById('sabores-premium');
            if (nombre.includes('Premium')) {
                saboresDiv.classList.remove('hidden');
            } else {
                saboresDiv.classList.add('hidden');
            }
            
            actualizarResumen();
        }
        
        function cambiarCantidad(cambio) {
            const input = document.getElementById('cantidad_personalizada');
            let cantidad = parseInt(input.value) + cambio;
            if (cantidad < 8) cantidad = 8;
            input.value = cantidad;
            generarPlanchas();
            actualizarResumen();
        }
        
        function generarPlanchas() {
            const cantidad = parseInt(document.getElementById('cantidad_personalizada').value);
            const planchas = Math.ceil(cantidad / 8);
            
            document.getElementById('planchas-info').textContent = `${planchas} plancha${planchas > 1 ? 's' : ''}`;
            
            const container = document.getElementById('planchas-container');
            container.innerHTML = '';
            
            for (let i = 1; i <= planchas; i++) {
                container.innerHTML += `
                    <div class="plancha-config">
                        <h5 class="font-medium mb-2">Plancha ${i} (8 sándwiches):</h5>
                        <div class="grid grid-cols-1 gap-2">
                            <div>
                                <label class="text-sm">Tipo:</label>
                                <select name="plancha_${i}_tipo" class="w-full text-sm border rounded px-2 py-1" onchange="actualizarResumen()">
                                    <option value="comun">Común ($3,500)</option>
                                    <option value="premium">Premium ($7,000)</option>
                                </select>
                            </div>
                            <div>
                                <label class="text-sm">Sabores:</label>
                                <textarea name="plancha_${i}_sabores" placeholder="Ej: J y Q, lechuga, tomate..." 
                                          class="w-full text-sm border rounded px-2 py-1" rows="2"></textarea>
                            </div>
                        </div>
                    </div>
                `;
            }
        }
        
        function toggleDireccion() {
            const modalidadDelivery = document.querySelector('input[name="modalidad"][value="Delivery"]').checked;
            const direccionContainer = document.getElementById('direccion-container');
            const direccionInput = document.getElementById('direccion-input');
            const direccionRequired = document.getElementById('direccion-required');
            const direccionHelp = document.getElementById('direccion-help');
            const horaLabel = document.getElementById('hora-label');
            const horaHelp = document.getElementById('hora-help');
            
            if (modalidadDelivery) {
                // DELIVERY: Dirección obligatoria visualmente
                direccionContainer.style.display = 'block';
                direccionInput.required = true;
                direccionRequired.classList.remove('hidden');
                direccionHelp.classList.remove('hidden');
                direccionContainer.classList.add('bg-yellow-50', 'border', 'border-yellow-200', 'rounded-lg', 'p-3');
                
                // Cambiar etiquetas de hora
                horaLabel.textContent = 'Hora Específica (dentro del turno)';
                horaHelp.textContent = 'Opcional - Solo si necesitas una hora específica dentro del turno seleccionado';
            } else {
                // RETIRA: Dirección opcional
                direccionInput.required = false;
                direccionRequired.classList.add('hidden');
                direccionHelp.classList.add('hidden');
                direccionContainer.classList.remove('bg-yellow-50', 'border', 'border-yellow-200', 'rounded-lg', 'p-3');
                
                // Cambiar etiquetas de hora
                horaLabel.textContent = 'Hora de Retiro';
                horaHelp.textContent = 'Opcional - Hora que prefiere retirar el pedido';
            }
            
            // Nota: el bloque de turno queda siempre visible y requerido (no lo ocultamos)
        }
        
        function actualizarPrecios() {
            // Actualizar precios mostrados según forma de pago seleccionada
            actualizarResumen();
        }
        
        function actualizarResumen() {
            const resumen = document.getElementById('resumen-pedido');
            const tipoPedido = document.getElementById('tipo_pedido').value;
            const formaPago = document.querySelector('input[name="forma_pago"]:checked')?.value || 'Transferencia';
            
            if (tipoPedido === 'predefinido' && productoSeleccionado) {
                const precio = formaPago === 'Efectivo' ? productoSeleccionado.precioEfectivo : productoSeleccionado.precioTransferencia;
                resumen.innerHTML = `
                    <div class="text-center">
                        <h4 class="font-semibold text-lg">${productoSeleccionado.nombre}</h4>
                        <div class="text-2xl font-bold text-blue-600 mt-2">${precio.toLocaleString()}</div>
                        <div class="text-sm text-gray-600">${formaPago}</div>
                    </div>
                `;
            } else if (tipoPedido === 'personalizado') {
                const cantidad = parseInt(document.getElementById('cantidad_personalizada').value);
                const planchas = Math.ceil(cantidad / 8);
                let precioTotal = 0;
                
                for (let i = 1; i <= planchas; i++) {
                    const tipoSelect = document.querySelector(`select[name="plancha_${i}_tipo"]`);
                    if (tipoSelect) {
                        const tipo = tipoSelect.value;
                        const precioBase = tipo === 'premium' ? 7000 : 3500;
                        const precio = formaPago === 'Efectivo' ? precioBase * 0.9 : precioBase;
                        precioTotal += precio;
                    }
                }
                
                resumen.innerHTML = `
                    <div class="text-center">
                        <h4 class="font-semibold text-lg">Personalizado x${cantidad}</h4>
                        <div class="text-sm text-gray-600">${planchas} plancha${planchas > 1 ? 's' : ''}</div>
                        <div class="text-2xl font-bold text-blue-600 mt-2">${precioTotal.toLocaleString()}</div>
                        <div class="text-sm text-gray-600">${formaPago}</div>
                    </div>
                `;
            } else {
                resumen.innerHTML = '<p class="text-gray-500 text-center">Selecciona un producto para ver el resumen</p>';
            }
        }
        
        // Inicializar
        document.addEventListener('DOMContentLoaded', function() {
            generarPlanchas();
            toggleDireccion();
            
            // Si hubo producto seleccionado en POST, marcar visualmente
            const prodId = <?= json_encode($_POST['producto_id'] ?? null) ?>;
            if (prodId) {
                const el = document.querySelector(`.producto-card input[name="producto_id"][value="${prodId}"]`);
                if (el) {
                    const parent = el.closest('.producto-card');
                    if (parent) parent.classList.add('selected');
                }
            }
            
            // Si el usuario ya viene con tab personalizado, mostrarlo
            const tipoPedido = '<?= $_POST['tipo_pedido'] ?? 'predefinido' ?>';
            mostrarTab(tipoPedido);
        });
    </script>
</body>
</html>
