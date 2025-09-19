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

// Definir sabores por categoría
$sabores = [
    'comun' => [
        'Jamón y Queso' => 'Jamón y queso, lechuga, tomate, huevo',
    ],
    'especiales' => [
        'Surtidos Clásicos' => 'Jamón y queso, lechuga, tomate, huevo',
        'Surtidos Especiales' => 'Jamón y queso, lechuga, tomate, huevo, choclo, aceitunas',
        'Surtidos Premium' => 'Jamón y queso, lechuga, tomate, huevo, choclo, aceitunas'
    ],
    'premium' => [
        'Ananá' => 'Ananá fresco',
        'Atún' => 'Atún en conserva',
        'Berenjena' => 'Berenjena grillada',
        'Durazno' => 'Durazno en almíbar',
        'Jamón Crudo' => 'Jamón crudo importado',
        'Morrón' => 'Morrón asado',
        'Palmito' => 'Palmito en conserva',
        'Panceta' => 'Panceta crocante',
        'Pollo' => 'Pollo desmenuzado',
        'Roquefort' => 'Queso roquefort',
        'Salame' => 'Salame premium'
    ]
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
                            $precio = ($forma_pago === 'Efectivo') ? 
                                     $prod_data['precio_efectivo'] : $prod_data['precio_transferencia'];
                            $cantidad = (int)filter_var($producto, FILTER_SANITIZE_NUMBER_INT);
                            
                            // Agregar sabores si es premium
                            if (isset($_POST['sabores_premium']) && !empty($_POST['sabores_premium'])) {
                                $sabores_seleccionados = $_POST['sabores_premium'];
                                $observaciones .= "\nSabores: " . implode(', ', $sabores_seleccionados);
                            }
                        } else {
                            throw new Exception('Producto no encontrado');
                        }
                    } else {
                        throw new Exception('Debe seleccionar un producto');
                    }
                    break;
                    
                case 'personalizado':
                    $cant_personalizado = (int)($_POST['cantidad_personalizada'] ?? 8);
                    $precio_personalizado = (float)($_POST['precio_personalizado'] ?? 0);
                    
                    if ($cant_personalizado <= 0 || $precio_personalizado <= 0) {
                        throw new Exception('Cantidad y precio del personalizado deben ser mayores a 0');
                    }
                    
                    $planchas = ceil($cant_personalizado / 8);
                    $sabores_personalizados = $_POST['sabores_personalizados'] ?? [];
                    
                    if (empty($sabores_personalizados)) {
                        throw new Exception('Debe seleccionar al menos un sabor para el pedido personalizado');
                    }
                    
                    $producto = "Personalizado x$cant_personalizado ($planchas plancha" . ($planchas > 1 ? 's' : '') . ")";
                    $cantidad = $cant_personalizado;
                    $precio = $precio_personalizado;
                    
                    $observaciones .= "\nSabores personalizados: " . implode(', ', $sabores_personalizados);
                    $observaciones .= "\nPlanchas: $planchas";
                    break;
                    
                default:
                    throw new Exception('Tipo de pedido no válido');
            }
            
            if (empty($producto) || $cantidad <= 0 || $precio <= 0) {
                throw new Exception("Error en los datos del producto. Producto: '$producto', Cantidad: $cantidad, Precio: $precio");
            }
            
            // Insertar pedido
            $stmt = $pdo->prepare("
                INSERT INTO pedidos (nombre, apellido, telefono, direccion, producto, cantidad, precio, 
                                   forma_pago, modalidad, ubicacion, observaciones, cliente_fijo_id, 
                                   fecha_entrega, hora_entrega, notas_horario) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            
            $cliente_fijo_id = $cliente_seleccionado ? $cliente_seleccionado['id'] : null;
            
            $result = $stmt->execute([
                $nombre, $apellido, $telefono, $direccion, $producto, $cantidad, $precio,
                $forma_pago, $modalidad, $ubicacion, $observaciones, $cliente_fijo_id,
                $fecha_entrega, $hora_entrega, $notas_horario
            ]);
            
            if ($result) {
                $mensaje = "✅ Pedido creado exitosamente";
                
                // Limpiar formulario
                $_POST = [];
                $cliente_seleccionado = null;
            } else {
                throw new Exception('Error al guardar el pedido en la base de datos');
            }
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
    <title>Crear Pedido - Sistema Santa Catalina</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .tab-btn.active { @apply border-blue-500 text-blue-600; }
        .producto-card { transition: all 0.2s ease; }
        .producto-card:hover { transform: translateY(-2px); }
        .producto-card.selected { @apply ring-2 ring-blue-500 bg-blue-50; }
        .sabor-item { transition: all 0.2s ease; }
        .sabor-item:hover { @apply bg-gray-50; }
        .contador-display { font-size: 2rem; font-weight: bold; }
        .precio-input { border: 2px solid #e5e7eb; }
        .precio-input:focus { border-color: #3b82f6; outline: none; }
    </style>
</head>
<body class="bg-gray-100">
    <div class="container mx-auto px-4 py-6">
        <div class="flex items-center justify-between mb-6">
            <div>
                <h1 class="text-2xl font-bold text-gray-800">
                    <i class="fas fa-plus-circle text-green-500 mr-2"></i>Crear Nuevo Pedido
                </h1>
                <p class="text-gray-600">Sistema de gestión Santa Catalina</p>
            </div>
            <a href="../pedidos/" class="bg-gray-500 hover:bg-gray-600 text-white px-4 py-2 rounded-lg transition-colors">
                <i class="fas fa-arrow-left mr-2"></i>Volver
            </a>
        </div>

        <?php if ($mensaje): ?>
            <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4">
                <?= htmlspecialchars($mensaje) ?>
            </div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
                <i class="fas fa-exclamation-triangle mr-2"></i><?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>

        <form method="POST" id="pedidoForm" class="space-y-6">
            <!-- Información del Cliente -->
            <div class="bg-white rounded-lg shadow p-6">
                <h3 class="text-lg font-semibold mb-4">
                    <i class="fas fa-user text-blue-500 mr-2"></i>Información del Cliente
                </h3>
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-gray-700 mb-2">Nombre *</label>
                        <input type="text" name="nombre" required class="w-full px-3 py-2 border rounded-lg"
                               value="<?= $cliente_seleccionado ? htmlspecialchars($cliente_seleccionado['nombre']) : htmlspecialchars($_POST['nombre'] ?? '') ?>">
                    </div>
                    <div>
                        <label class="block text-gray-700 mb-2">Apellido *</label>
                        <input type="text" name="apellido" required class="w-full px-3 py-2 border rounded-lg"
                               value="<?= $cliente_seleccionado ? htmlspecialchars($cliente_seleccionado['apellido']) : htmlspecialchars($_POST['apellido'] ?? '') ?>">
                    </div>
                    <div>
                        <label class="block text-gray-700 mb-2">Teléfono *</label>
                        <input type="tel" name="telefono" required class="w-full px-3 py-2 border rounded-lg"
                               value="<?= $cliente_seleccionado ? htmlspecialchars($cliente_seleccionado['telefono']) : htmlspecialchars($_POST['telefono'] ?? '') ?>">
                    </div>
                    <div>
                        <label class="block text-gray-700 mb-2">Dirección</label>
                        <input type="text" name="direccion" class="w-full px-3 py-2 border rounded-lg"
                               value="<?= $cliente_seleccionado ? htmlspecialchars($cliente_seleccionado['direccion']) : htmlspecialchars($_POST['direccion'] ?? '') ?>">
                    </div>
                </div>
            </div>

            <!-- Modalidad y Ubicación -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div class="bg-white rounded-lg shadow p-6">
                    <h4 class="font-medium text-gray-800 mb-3">
                        <i class="fas fa-shipping-fast text-green-500 mr-2"></i>Modalidad de Entrega *
                    </h4>
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

                <div class="bg-white rounded-lg shadow p-6">
                    <h4 class="font-medium text-gray-800 mb-3">
                        <i class="fas fa-map-marker-alt text-purple-500 mr-2"></i>Ubicación de Procesamiento *
                    </h4>
                    <div class="space-y-2">
                        <label class="flex items-center">
                            <input type="radio" name="ubicacion" value="Local 1" required class="mr-2" onchange="updateResumen()"
                                   <?= (isset($_POST['ubicacion']) && $_POST['ubicacion'] === 'Local 1') ? 'checked' : '' ?>>
                            <i class="fas fa-store-alt mr-2 text-blue-500"></i>Local 1
                        </label>
                        <label class="flex items-center">
                            <input type="radio" name="ubicacion" value="Fábrica" required class="mr-2" onchange="updateResumen()"
                                   <?= (isset($_POST['ubicacion']) && $_POST['ubicacion'] === 'Fábrica') ? 'checked' : '' ?>>
                            <i class="fas fa-industry mr-2 text-orange-500"></i>Fábrica
                        </label>
                    </div>
                </div>
            </div>

            <!-- Contenido Principal: 3 Columnas -->
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                <!-- Columna 1: Tabs y Formas de Pago -->
                <div class="space-y-6">
                    <!-- Tabs de Tipo de Pedido -->
                    <div class="bg-white rounded-lg shadow">
                        <div class="border-b">
                            <nav class="flex">
                                <button type="button" id="tab-predefinido" class="tab-btn py-3 px-6 border-b-2 font-medium text-sm active cursor-pointer border-blue-500 text-blue-600" onclick="showTab('predefinido')">
                                    <i class="fas fa-list mr-2"></i>Predefinido
                                </button>
                                <button type="button" id="tab-personalizado" class="tab-btn py-3 px-6 border-b-2 font-medium text-sm cursor-pointer border-transparent text-gray-500 hover:text-blue-600" onclick="showTab('personalizado')">
                                    <i class="fas fa-cogs mr-2"></i>Personalizado
                                </button>
                            </nav>
                        </div>
                    </div>

                    <!-- Formas de Pago -->
                    <div class="bg-white rounded-lg shadow p-6">
                        <h4 class="font-medium text-gray-800 mb-3">
                            <i class="fas fa-credit-card text-green-500 mr-2"></i>Forma de Pago *
                        </h4>
                        <div class="space-y-2">
                            <label class="flex items-center">
                                <input type="radio" name="forma_pago" value="Efectivo" required class="mr-2" onchange="updateResumen()"
                                       <?= (isset($_POST['forma_pago']) && $_POST['forma_pago'] === 'Efectivo') ? 'checked' : '' ?>>
                                <i class="fas fa-money-bills mr-2 text-green-500"></i>Efectivo (10% desc.)
                            </label>
                            <label class="flex items-center">
                                <input type="radio" name="forma_pago" value="Transferencia" required class="mr-2" onchange="updateResumen()"
                                       <?= (isset($_POST['forma_pago']) && $_POST['forma_pago'] === 'Transferencia') ? 'checked' : '' ?>>
                                <i class="fas fa-university mr-2 text-blue-500"></i>Transferencia
                            </label>
                        </div>
                    </div>
                </div>

                <!-- Columna 2: Selección de Productos -->
                <div class="bg-white rounded-lg shadow p-6">
                    <h3 class="text-lg font-semibold mb-4">
                        <i class="fas fa-shopping-cart text-purple-500 mr-2"></i>Seleccionar Producto
                    </h3>

                    <input type="hidden" name="tipo_pedido" id="tipo_pedido_hidden" value="predefinido">

                    <!-- Tab Predefinido -->
                    <div id="content-predefinido" class="tab-content">
                        <div class="space-y-3">
                            <?php foreach ($productos as $producto): ?>
                                <div class="producto-card border rounded-lg p-4 cursor-pointer" onclick="selectProduct(<?= $producto['id'] ?>, '<?= htmlspecialchars($producto['nombre']) ?>', <?= $producto['precio_efectivo'] ?>, <?= $producto['precio_transferencia'] ?>)">
                                    <input type="radio" name="producto_id" value="<?= $producto['id'] ?>" class="sr-only">
                                    <div class="flex justify-between items-start">
                                        <div class="flex-1">
                                            <h4 class="font-medium text-gray-800"><?= htmlspecialchars($producto['nombre']) ?></h4>
                                            <?php if ($producto['descripcion']): ?>
                                                <p class="text-sm text-gray-600 mt-1"><?= htmlspecialchars($producto['descripcion']) ?></p>
                                            <?php endif; ?>
                                            <div class="mt-2">
                                                <span class="text-sm text-gray-500">Efectivo: </span>
                                                <span class="font-medium text-green-600">$<?= number_format($producto['precio_efectivo'], 0, ',', '.') ?></span>
                                                <span class="mx-2">|</span>
                                                <span class="text-sm text-gray-500">Transfer: </span>
                                                <span class="font-medium text-blue-600">$<?= number_format($producto['precio_transferencia'], 0, ',', '.') ?></span>
                                            </div>
                                        </div>
                                        <div class="text-purple-500">
                                            <i class="fas fa-chevron-right"></i>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>

                        <!-- Sabores Premium (solo si aplica) -->
                        <div id="sabores-premium" class="hidden mt-6 p-4 bg-yellow-50 border border-yellow-200 rounded-lg">
                            <h5 class="font-medium text-yellow-800 mb-3">
                                <i class="fas fa-star text-yellow-500 mr-2"></i>Seleccionar Sabores Premium
                            </h5>
                            <div class="grid grid-cols-1 gap-2 max-h-40 overflow-y-auto">
                                <?php foreach ($sabores['premium'] as $sabor => $descripcion): ?>
                                    <label class="sabor-item flex items-center p-2 rounded">
                                        <input type="checkbox" name="sabores_premium[]" value="<?= $sabor ?>" class="mr-2">
                                        <div>
                                            <span class="text-sm font-medium"><?= $sabor ?></span>
                                            <span class="text-xs text-gray-500 ml-2">(<?= $descripcion ?>)</span>
                                        </div>
                                    </label>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>

                    <!-- Tab Personalizado -->
                    <div id="content-personalizado" class="tab-content" style="display: none;">
                        <div class="space-y-6">
                            <!-- Contador de Sándwiches -->
                            <div class="text-center p-6 bg-gradient-to-r from-blue-50 to-purple-50 rounded-lg border">
                                <h4 class="font-medium text-gray-800 mb-4">
                                    <i class="fas fa-calculator text-blue-500 mr-2"></i>Cantidad de Sándwiches
                                </h4>
                                <div class="flex items-center justify-center space-x-4">
                                    <button type="button" onclick="cambiarCantidad(-8)" class="bg-red-500 hover:bg-red-600 text-white w-10 h-10 rounded-full">
                                        <i class="fas fa-minus"></i>
                                    </button>
                                    <div class="contador-display text-blue-600" id="cantidad-display">8</div>
                                    <button type="button" onclick="cambiarCantidad(8)" class="bg-green-500 hover:bg-green-600 text-white w-10 h-10 rounded-full">
                                        <i class="fas fa-plus"></i>
                                    </button>
                                </div>
                                <input type="hidden" name="cantidad_personalizada" id="cantidad_personalizada" value="8">
                                <p class="text-sm text-gray-600 mt-2">
                                    <span id="planchas-info">1 plancha</span> • <span class="text-blue-600">Se incrementa de 8 en 8</span>
                                </p>
                            </div>

                            <!-- Campo de Precio Personalizable -->
                            <div class="p-4 bg-green-50 border border-green-200 rounded-lg">
                                <label class="block text-gray-700 mb-2 font-medium">
                                    <i class="fas fa-dollar-sign text-green-500 mr-2"></i>Precio Total *
                                </label>
                                <input type="number" 
                                       name="precio_personalizado" 
                                       id="precio_personalizado"
                                       min="0" 
                                       step="0.01" 
                                       required 
                                       class="precio-input w-full px-4 py-3 text-lg font-bold text-center rounded-lg"
                                       placeholder="Ingresá el precio total"
                                       onchange="updateResumen()">
                                <p class="text-sm text-gray-600 mt-1 text-center">Ingresá el precio según los sándwiches seleccionados</p>
                            </div>

                            <!-- Sabores por Categoría -->
                            <div class="space-y-4">
                                <h4 class="font-medium text-gray-800">
                                    <i class="fas fa-list text-purple-500 mr-2"></i>Seleccionar Sabores
                                </h4>

                                <!-- Sabores Comunes -->
                                <div class="p-4 bg-blue-50 border border-blue-200 rounded-lg">
                                    <h5 class="font-medium text-blue-800 mb-3">
                                        <i class="fas fa-circle text-blue-500 mr-2"></i>Comunes
                                    </h5>
                                    <?php foreach ($sabores['comun'] as $sabor => $descripcion): ?>
                                        <label class="sabor-item flex items-center p-2 rounded">
                                            <input type="checkbox" name="sabores_personalizados[]" value="<?= $sabor ?>" class="mr-2">
                                            <div>
                                                <span class="text-sm font-medium"><?= $sabor ?></span>
                                                <span class="text-xs text-gray-600 ml-2">(<?= $descripcion ?>)</span>
                                            </div>
                                        </label>
                                    <?php endforeach; ?>
                                </div>

                                <!-- Sabores Especiales -->
                                <div class="p-4 bg-orange-50 border border-orange-200 rounded-lg">
                                    <h5 class="font-medium text-orange-800 mb-3">
                                        <i class="fas fa-star text-orange-500 mr-2"></i>Especiales
                                    </h5>
                                    <?php foreach ($sabores['especiales'] as $sabor => $descripcion): ?>
                                        <label class="sabor-item flex items-center p-2 rounded">
                                            <input type="checkbox" name="sabores_personalizados[]" value="<?= $sabor ?>" class="mr-2">
                                            <div>
                                                <span class="text-sm font-medium"><?= $sabor ?></span>
                                                <span class="text-xs text-gray-600 ml-2">(<?= $descripcion ?>)</span>
                                            </div>
                                        </label>
                                    <?php endforeach; ?>
                                </div>

                                <!-- Sabores Premium -->
                                <div class="p-4 bg-yellow-50 border border-yellow-200 rounded-lg">
                                    <h5 class="font-medium text-yellow-800 mb-3">
                                        <i class="fas fa-crown text-yellow-500 mr-2"></i>Premium
                                    </h5>
                                    <div class="grid grid-cols-1 gap-2 max-h-40 overflow-y-auto">
                                        <?php foreach ($sabores['premium'] as $sabor => $descripcion): ?>
                                            <label class="sabor-item flex items-center p-2 rounded">
                                                <input type="checkbox" name="sabores_personalizados[]" value="<?= $sabor ?>" class="mr-2">
                                                <div>
                                                    <span class="text-sm font-medium"><?= $sabor ?></span>
                                                    <span class="text-xs text-gray-600 ml-2">(<?= $descripcion ?>)</span>
                                                </div>
                                            </label>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Columna 3: Resumen del Pedido -->
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
                </div>
            </div>

            <!-- Información Adicional -->
            <div class="bg-white rounded-lg shadow p-6">
                <h3 class="text-lg font-semibold mb-4">
                    <i class="fas fa-clock text-blue-500 mr-2"></i>Información Adicional
                </h3>
                
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div>
                        <label class="block text-gray-700 mb-2">Fecha de Entrega</label>
                        <input type="date" name="fecha_entrega" class="w-full px-3 py-2 border rounded-lg"
                               value="<?= htmlspecialchars($_POST['fecha_entrega'] ?? '') ?>" min="<?= date('Y-m-d') ?>">
                    </div>
                    <div>
                        <label class="block text-gray-700 mb-2">Hora de Entrega</label>
                        <input type="time" name="hora_entrega" class="w-full px-3 py-2 border rounded-lg"
                               value="<?= htmlspecialchars($_POST['hora_entrega'] ?? '') ?>">
                    </div>
                    <div>
                        <label class="block text-gray-700 mb-2">Notas de Horario</label>
                        <input type="text" name="notas_horario" class="w-full px-3 py-2 border rounded-lg"
                               placeholder="Ej: Flexible, mañana, etc."
                               value="<?= htmlspecialchars($_POST['notas_horario'] ?? '') ?>">
                    </div>
                </div>
                
                <div class="mt-4">
                    <label class="block text-gray-700 mb-2">Observaciones</label>
                    <textarea name="observaciones" rows="3" class="w-full px-3 py-2 border rounded-lg"
                              placeholder="Observaciones adicionales sobre el pedido..."><?= htmlspecialchars($_POST['observaciones'] ?? '') ?></textarea>
                </div>
            </div>

            <!-- Botones de Acción -->
            <div class="flex justify-between">
                <a href="../pedidos/" class="bg-gray-500 hover:bg-gray-600 text-white px-6 py-3 rounded-lg transition-colors">
                    <i class="fas fa-times mr-2"></i>Cancelar
                </a>
                <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white px-8 py-3 rounded-lg transition-colors">
                    <i class="fas fa-save mr-2"></i>Crear Pedido
                </button>
            </div>
        </form>
    </div>

    <script>
        let currentProduct = null;
        let paymentMethod = 'Transferencia';
        let activeTab = 'predefinido';

        // Cambiar tabs
        function showTab(tab) {
            activeTab = tab;
            
            // Ocultar todos los contenidos
            document.getElementById('content-predefinido').style.display = tab === 'predefinido' ? 'block' : 'none';
            document.getElementById('content-personalizado').style.display = tab === 'personalizado' ? 'block' : 'none';
            
            // Actualizar estilos de tabs
            document.getElementById('tab-predefinido').className = tab === 'predefinido' 
                ? 'tab-btn py-3 px-6 border-b-2 font-medium text-sm active cursor-pointer border-blue-500 text-blue-600'
                : 'tab-btn py-3 px-6 border-b-2 font-medium text-sm cursor-pointer border-transparent text-gray-500 hover:text-blue-600';
            
            document.getElementById('tab-personalizado').className = tab === 'personalizado' 
                ? 'tab-btn py-3 px-6 border-b-2 font-medium text-sm active cursor-pointer border-blue-500 text-blue-600'
                : 'tab-btn py-3 px-6 border-b-2 font-medium text-sm cursor-pointer border-transparent text-gray-500 hover:text-blue-600';
            
            document.getElementById('tipo_pedido_hidden').value = tab;
            
            // Limpiar selecciones previas
            if (tab === 'predefinido') {
                currentProduct = null;
                // Desmarcar productos
                document.querySelectorAll('.producto-card').forEach(card => {
                    card.classList.remove('selected');
                });
                document.querySelectorAll('input[name="producto_id"]').forEach(radio => {
                    radio.checked = false;
                });
            }
            
            updateResumen();
        }

        // Seleccionar producto predefinido
        function selectProduct(id, name, precioEfectivo, precioTransferencia) {
            currentProduct = { id, name, precioEfectivo, precioTransferencia };
            
            // Marcar visualmente el producto seleccionado
            document.querySelectorAll('.producto-card').forEach(card => {
                card.classList.remove('selected');
            });
            event.currentTarget.classList.add('selected');
            
            // Marcar el radio button
            document.querySelector(`input[name="producto_id"][value="${id}"]`).checked = true;
            
            // Mostrar sabores premium si aplica
            const saboresDiv = document.getElementById('sabores-premium');
            if (name.includes('Premium') || name.includes('Surtidos Premium')) {
                saboresDiv.classList.remove('hidden');
            } else {
                saboresDiv.classList.add('hidden');
            }
            
            updateResumen();
        }

        // Cambiar cantidad personalizada (contador de 8 en 8)
        function cambiarCantidad(cambio) {
            const input = document.getElementById('cantidad_personalizada');
            const display = document.getElementById('cantidad-display');
            const planchasInfo = document.getElementById('planchas-info');
            
            let cantidad = parseInt(input.value) + cambio;
            if (cantidad < 8) cantidad = 8; // Mínimo 8
            
            input.value = cantidad;
            display.textContent = cantidad;
            
            const planchas = Math.ceil(cantidad / 8);
            planchasInfo.textContent = `${planchas} plancha${planchas > 1 ? 's' : ''}`;
            
            updateResumen();
        }

        // Actualizar formas de pago
        function updatePrecios() {
            const formaPago = document.querySelector('input[name="forma_pago"]:checked')?.value;
            if (formaPago) {
                paymentMethod = formaPago;
                updateResumen();
            }
        }

        // Actualizar resumen del pedido
        function updateResumen() {
            const resumenDiv = document.getElementById('resumen-pedido');
            const ubicacion = document.querySelector('input[name="ubicacion"]:checked')?.value;
            
            let html = '';
            
            if (activeTab === 'predefinido' && currentProduct) {
                const precio = paymentMethod === 'Efectivo' ? currentProduct.precioEfectivo : currentProduct.precioTransferencia;
                
                html = `
                    <div class="border-b pb-3">
                        <div class="font-medium">${currentProduct.name}</div>
                        <div class="text-sm text-gray-600">Pago: ${paymentMethod}</div>
                        ${ubicacion ? `<div class="text-xs mt-1 ${ubicacion === 'Local 1' ? 'text-blue-600' : 'text-orange-600'}">
                            <i class="fas fa-${ubicacion === 'Local 1' ? 'store-alt' : 'industry'} mr-1"></i>${ubicacion}
                        </div>` : ''}
                    </div>
                    <div class="flex justify-between items-center font-bold text-lg">
                        <span>Total:</span>
                        <span class="text-green-600">${precio.toLocaleString()}</span>
                    </div>
                `;
            } else if (activeTab === 'personalizado') {
                const cantidad = document.querySelector('input[name="cantidad_personalizada"]')?.value || 8;
                const precio = parseFloat(document.querySelector('input[name="precio_personalizado"]')?.value || 0);
                const planchas = Math.ceil(cantidad / 8);
                
                html = `
                    <div class="border-b pb-3">
                        <div class="font-medium">Personalizado x${cantidad}</div>
                        <div class="text-sm text-gray-600">${planchas} plancha${planchas > 1 ? 's' : ''}</div>
                        <div class="text-sm text-gray-600">Pago: ${paymentMethod}</div>
                        ${ubicacion ? `<div class="text-xs mt-1 ${ubicacion === 'Local 1' ? 'text-blue-600' : 'text-orange-600'}">
                            <i class="fas fa-${ubicacion === 'Local 1' ? 'store-alt' : 'industry'} mr-1"></i>${ubicacion}
                        </div>` : ''}
                    </div>
                    <div class="flex justify-between items-center font-bold text-lg">
                        <span>Total:</span>
                        <span class="text-green-600">${precio > 0 ? ' + precio.toLocaleString() : 'Sin precio'}</span>
                    </div>
                `;
            } else {
                html = `
                    <div class="text-gray-500 text-center py-8">
                        <i class="fas fa-shopping-cart text-4xl mb-2"></i>
                        <div>Seleccioná un producto para ver el resumen</div>
                        ${ubicacion ? `<div class="text-xs mt-2 ${ubicacion === 'Local 1' ? 'text-blue-600' : 'text-orange-600'}">
                            <i class="fas fa-${ubicacion === 'Local 1' ? 'store-alt' : 'industry'} mr-1"></i>${ubicacion}
                        </div>` : ''}
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
            } else if (tipoPedido === 'personalizado') {
                const precio = parseFloat(document.querySelector('input[name="precio_personalizado"]')?.value || 0);
                if (precio <= 0) {
                    e.preventDefault();
                    alert('Por favor ingresa un precio válido para el pedido personalizado.');
                    return false;
                }
                
                const saboresSeleccionados = document.querySelectorAll('input[name="sabores_personalizados[]"]:checked');
                if (saboresSeleccionados.length === 0) {
                    e.preventDefault();
                    alert('Por favor selecciona al menos un sabor para el pedido personalizado.');
                    return false;
                }
            }
            
            return true;
        });

        // Event listeners
        document.addEventListener('DOMContentLoaded', function() {
            showTab('predefinido');
            
            // Event listeners para cambios en forma de pago
            document.querySelectorAll('input[name="forma_pago"]').forEach(radio => {
                radio.addEventListener('change', updatePrecios);
            });
            
            // Event listener para cambios en precio personalizado
            document.getElementById('precio_personalizado').addEventListener('input', updateResumen);
        });
    </script>
</body>
</html>