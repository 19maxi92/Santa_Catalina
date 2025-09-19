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
        $ubicacion = $_POST['ubicacion'];
        $forma_pago = $_POST['forma_pago'];
        $observaciones = sanitize($_POST['observaciones']);
        
        // ========== MODIFICACIÓN 1: HORARIOS CON 3 TURNOS ==========
        $fecha_entrega = $_POST['fecha_entrega'] ?? null;
        $hora_entrega = null;
        $notas_horario = '';
        
        // Procesar turnos de delivery si es delivery
        if ($modalidad === 'Delivery') {
            $turno_delivery = $_POST['turno_delivery'] ?? '';
            if (empty($turno_delivery)) {
                throw new Exception('Debe seleccionar un turno de delivery');
            }
            
            // Mapear turnos a horarios específicos
            $turnos_horarios = [
                'mañana' => ['hora' => '10:00:00', 'nombre' => 'Mañana (9:00-11:30)'],
                'merienda' => ['hora' => '16:00:00', 'nombre' => 'Merienda (15:00-17:00)'], 
                'tarde' => ['hora' => '19:00:00', 'nombre' => 'Tarde (18:00-20:00)']
            ];
            
            if (!isset($turnos_horarios[$turno_delivery])) {
                throw new Exception('Turno de delivery inválido');
            }
            
            $hora_entrega = $turnos_horarios[$turno_delivery]['hora'];
            $notas_horario = 'Turno: ' . $turnos_horarios[$turno_delivery]['nombre'];
            
            if (!empty($_POST['notas_horario_adicional'])) {
                $notas_horario .= ' - ' . sanitize($_POST['notas_horario_adicional']);
            }
        } else {
            // Para retiro, usar horario original
            $hora_entrega = $_POST['hora_entrega'] ?? null;
            $notas_horario = sanitize($_POST['notas_horario_adicional'] ?? '');
        }
        
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
                            
                            // Extraer cantidad del nombre del producto
                            preg_match('/(\d+)/', $producto, $matches);
                            $cantidad = isset($matches[1]) ? (int)$matches[1] : 24;
                        } else {
                            throw new Exception('Producto no encontrado');
                        }
                    } else {
                        throw new Exception('Debe seleccionar un producto');
                    }
                    break;
                    
                case 'personalizado':
                    $cant_personalizado = (int)$_POST['cant_personalizado'];
                    $tipo_personalizado = $_POST['tipo_personalizado'];
                    $sabores_personalizados = $_POST['sabores_personalizados'] ?? [];
                    
                    if ($cant_personalizado <= 0) {
                        throw new Exception('La cantidad debe ser mayor a 0');
                    }
                    
                    if (empty($sabores_personalizados)) {
                        throw new Exception('Debe seleccionar al menos un sabor');
                    }
                    
                    // Calcular planchas necesarias
                    $planchas = ceil($cant_personalizado / 8);
                    
                    // Precio base por plancha según tipo
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
            <a href="../../logout.php" class="bg-red-500 hover:bg-red-600 text-white px-3 py-2 rounded">
                <i class="fas fa-sign-out-alt mr-1"></i>Salir
            </a>
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
                            <input type="text" name="nombre" value="<?= $cliente_seleccionado['nombre'] ?? htmlspecialchars($_POST['nombre'] ?? '') ?>" 
                                   required maxlength="100" class="w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-green-500">
                        </div>
                        
                        <div>
                            <label class="block text-gray-700 mb-2 font-medium">Apellido <span class="text-red-500">*</span></label>
                            <input type="text" name="apellido" value="<?= $cliente_seleccionado['apellido'] ?? htmlspecialchars($_POST['apellido'] ?? '') ?>" 
                                   required maxlength="100" class="w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-green-500">
                        </div>
                        
                        <div>
                            <label class="block text-gray-700 mb-2 font-medium">Teléfono <span class="text-red-500">*</span></label>
                            <input type="tel" name="telefono" value="<?= $cliente_seleccionado['telefono'] ?? htmlspecialchars($_POST['telefono'] ?? '') ?>" 
                                   required maxlength="20" class="w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-green-500">
                        </div>
                        
                        <div>
                            <label class="block text-gray-700 mb-2 font-medium">Dirección</label>
                            <textarea name="direccion" rows="2" class="w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-green-500" 
                                      placeholder="Requerida para delivery"><?= $cliente_seleccionado['direccion'] ?? htmlspecialchars($_POST['direccion'] ?? '') ?></textarea>
                        </div>

                        <div>
                            <label class="block text-gray-700 mb-2 font-medium">Modalidad <span class="text-red-500">*</span></label>
                            <div class="space-y-2">
                                <label class="flex items-center">
                                    <input type="radio" name="modalidad" value="Retira" required class="mr-2" 
                                           <?= (isset($_POST['modalidad']) && $_POST['modalidad'] === 'Retira') ? 'checked' : '' ?>>
                                    <span>🏪 Retira en local</span>
                                </label>
                                <label class="flex items-center">
                                    <input type="radio" name="modalidad" value="Delivery" required class="mr-2"
                                           <?= (isset($_POST['modalidad']) && $_POST['modalidad'] === 'Delivery') ? 'checked' : '' ?>>
                                    <span>🚚 Delivery</span>
                                </label>
                            </div>
                        </div>

                        <div>
                            <label class="block text-gray-700 mb-2 font-medium">Ubicación <span class="text-red-500">*</span></label>
                            <select name="ubicacion" required class="w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-green-500">
                                <option value="">Seleccionar ubicación...</option>
                                <option value="Fábrica" <?= (isset($_POST['ubicacion']) && $_POST['ubicacion'] === 'Fábrica') ? 'selected' : '' ?>>Fábrica</option>
                                <option value="Local 1" <?= (isset($_POST['ubicacion']) && $_POST['ubicacion'] === 'Local 1') ? 'selected' : '' ?>>Local 1</option>
                                <option value="Local 2" <?= (isset($_POST['ubicacion']) && $_POST['ubicacion'] === 'Local 2') ? 'selected' : '' ?>>Local 2</option>
                            </select>
                        </div>

                        <div>
                            <label class="block text-gray-700 mb-2 font-medium">Forma de Pago <span class="text-red-500">*</span></label>
                            <select name="forma_pago" required class="w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-green-500">
                                <option value="">Seleccionar forma de pago...</option>
                                <option value="Efectivo" <?= (isset($_POST['forma_pago']) && $_POST['forma_pago'] === 'Efectivo') ? 'selected' : '' ?>>Efectivo</option>
                                <option value="Transferencia" <?= (isset($_POST['forma_pago']) && $_POST['forma_pago'] === 'Transferencia') ? 'selected' : '' ?>>Transferencia</option>
                                <option value="MercadoPago" <?= (isset($_POST['forma_pago']) && $_POST['forma_pago'] === 'MercadoPago') ? 'selected' : '' ?>>MercadoPago</option>
                            </select>
                        </div>

                        <!-- ========== MODIFICACIÓN 2: HORARIOS CON 3 TURNOS ========== -->
                        <div class="bg-orange-50 p-4 rounded-lg">
                            <h4 class="font-medium text-gray-800 mb-3">
                                <i class="fas fa-clock text-orange-500 mr-2"></i>
                                Horario de entrega
                            </h4>
                            
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                                <div>
                                    <label class="block text-sm text-gray-700 mb-1">Fecha de entrega</label>
                                    <input type="date" name="fecha_entrega" 
                                           value="<?= $_POST['fecha_entrega'] ?? date('Y-m-d') ?>"
                                           min="<?= date('Y-m-d') ?>"
                                           class="w-full px-3 py-2 border rounded-lg text-sm focus:ring-2 focus:ring-orange-500">
                                </div>
                                
                                <!-- SECCIÓN CONDICIONAL SEGÚN MODALIDAD -->
                                <div id="horario_delivery" class="hidden">
                                    <label class="block text-sm text-gray-700 mb-1">Turno de delivery <span class="text-red-500">*</span></label>
                                    <select name="turno_delivery" class="w-full px-3 py-2 border rounded-lg text-sm focus:ring-2 focus:ring-orange-500">
                                        <option value="">Seleccionar turno...</option>
                                        <option value="mañana">🌅 Mañana (9:00 - 11:30)</option>
                                        <option value="merienda">☕ Merienda (15:00 - 17:00)</option>
                                        <option value="tarde">🌆 Tarde (18:00 - 20:00)</option>
                                    </select>
                                </div>
                                
                                <div id="horario_retiro" class="hidden">
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
                                <input type="text" name="notas_horario_adicional" 
                                       placeholder="Ej: Flexible, después de las 15:00, urgente..."
                                       class="w-full px-3 py-2 border rounded-lg text-sm focus:ring-2 focus:ring-orange-500">
                            </div>
                        </div>

                        <div>
                            <label class="block text-gray-700 mb-2 font-medium">Observaciones</label>
                            <textarea name="observaciones" rows="3" class="w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-green-500" 
                                      placeholder="Instrucciones especiales, sabores específicos..."><?= htmlspecialchars($_POST['observaciones'] ?? '') ?></textarea>
                        </div>
                    </div>
                </div>

                <!-- Columna 2: Productos -->
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
                                        <div class="font-medium"><?= htmlspecialchars($prod['nombre']) ?></div>
                                        <div class="text-sm text-gray-500">
                                            Efectivo: <?= formatPrice($prod['precio_efectivo']) ?> | 
                                            Transferencia: <?= formatPrice($prod['precio_transferencia']) ?>
                                        </div>
                                    </div>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    
                    <!-- Tab Personalizado -->
                    <div id="content-personalizado" class="tab-content hidden">
                        <div class="space-y-4">
                            <div>
                                <label class="block text-gray-700 mb-2">Cantidad:</label>
                                <input type="number" name="cant_personalizado" min="1" max="200" value="24" 
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
                    
                    <div class="mt-6">
                        <button type="submit" class="w-full bg-green-500 hover:bg-green-600 text-white py-3 px-4 rounded-lg font-semibold">
                            <i class="fas fa-plus-circle mr-2"></i>Crear Pedido
                        </button>
                    </div>
                </div>
            </div>
        </form>
    </main>

    <!-- ========== MODIFICACIÓN 3: JAVASCRIPT CON TURNOS ========== -->
    <script>
        let selectedProduct = null;

        function showTab(tabName) {
            // Ocultar todos los contenidos
            document.querySelectorAll('.tab-content').forEach(content => {
                content.classList.add('hidden');
            });
            
            // Mostrar el contenido seleccionado
            document.getElementById(`content-${tabName}`).classList.remove('hidden');
            
            // Actualizar botones de tab
            document.querySelectorAll('.tab-btn').forEach(btn => {
                btn.classList.remove('border-blue-500', 'text-blue-600', 'active');
                btn.classList.add('border-transparent', 'text-gray-500');
            });
            
            document.getElementById(`tab-${tabName}`).classList.add('border-blue-500', 'text-blue-600', 'active');
            document.getElementById(`tab-${tabName}`).classList.remove('border-transparent', 'text-gray-500');
            
            // Actualizar campo oculto
            document.getElementById('tipo_pedido_hidden').value = tabName;
            
            // Limpiar selección si cambia de tab
            if (tabName === 'predefinido') {
                document.querySelectorAll('input[name="producto_id"]').forEach(input => {
                    input.checked = false;
                });
            }
            
            updateResumen();
        }

        function selectProduct(id, nombre, precioEfectivo, precioTransferencia) {
            selectedProduct = {
                id: id,
                nombre: nombre,
                precioEfectivo: precioEfectivo,
                precioTransferencia: precioTransferencia
            };
            updateResumen();
        }

        function updateResumen() {
            const resumenDiv = document.getElementById('resumen-pedido');
            const formaPago = document.querySelector('select[name="forma_pago"]').value;
            
            if (selectedProduct && formaPago) {
                const precio = formaPago === 'Efectivo' ? selectedProduct.precioEfectivo : selectedProduct.precioTransferencia;
                
                resumenDiv.innerHTML = `
                    <div class="border-b pb-3 mb-3">
                        <div class="font-medium">${selectedProduct.nombre}</div>
                        <div class="text-sm text-gray-500">Forma de pago: ${formaPago}</div>
                    </div>
                    <div class="flex justify-between items-center text-lg font-semibold">
                        <span>Total:</span>
                        <span class="text-green-600">$${precio.toLocaleString()}</span>
                    </div>
                `;
            } else if (selectedProduct) {
                resumenDiv.innerHTML = `
                    <div class="border-b pb-3 mb-3">
                        <div class="font-medium">${selectedProduct.nombre}</div>
                        <div class="text-sm text-red-500">Selecciona forma de pago para ver el precio</div>
                    </div>
                    <div class="text-sm text-gray-500">
                        Efectivo: $${selectedProduct.precioEfectivo.toLocaleString()}<br>
                        Transferencia: $${selectedProduct.precioTransferencia.toLocaleString()}
                    </div>
                `;
            } else {
                resumenDiv.innerHTML = `
                    <div class="text-gray-500 text-center py-8">
                        <i class="fas fa-shopping-cart text-4xl mb-2"></i>
                        <div>Seleccioná un producto para ver el resumen</div>
                    </div>
                `;
            }
        }

        document.addEventListener('DOMContentLoaded', function() {
            // NUEVO: Manejo de turnos de delivery
            const modalidadInputs = document.querySelectorAll('input[name="modalidad"]');
            const horarioDelivery = document.getElementById('horario_delivery');
            const horarioRetiro = document.getElementById('horario_retiro');
            const turnoSelect = document.querySelector('select[name="turno_delivery"]');
            
            function toggleHorarios() {
                const modalidad = document.querySelector('input[name="modalidad"]:checked');
                
                if (modalidad) {
                    if (modalidad.value === 'Delivery') {
                        horarioDelivery.classList.remove('hidden');
                        horarioRetiro.classList.add('hidden');
                        if (turnoSelect) turnoSelect.required = true;
                    } else {
                        horarioDelivery.classList.add('hidden');
                        horarioRetiro.classList.remove('hidden');
                        if (turnoSelect) {
                            turnoSelect.required = false;
                            turnoSelect.value = '';
                        }
                    }
                }
            }
            
            // Event listeners para modalidad
            modalidadInputs.forEach(input => {
                input.addEventListener('change', toggleHorarios);
            });
            
            // Ejecutar al cargar
            toggleHorarios();

            // Event listeners para actualizar resumen
            document.querySelector('select[name="forma_pago"]').addEventListener('change', updateResumen);
            
            // Validación del formulario
            document.getElementById('pedidoForm').addEventListener('submit', function(e) {
                const modalidad = document.querySelector('input[name="modalidad"]:checked');
                const formaPago = document.querySelector('select[name="forma_pago"]').value;
                const ubicacion = document.querySelector('select[name="ubicacion"]').value;
                
                // Validación básica
                if (!formaPago) {
                    e.preventDefault();
                    alert('⚠️ Selecciona una forma de pago');
                    return false;
                }
                
                if (!ubicacion) {
                    e.preventDefault();
                    alert('⚠️ Selecciona una ubicación');
                    return false;
                }
                
                // Validación específica para delivery
                if (modalidad && modalidad.value === 'Delivery') {
                    const turno = document.querySelector('select[name="turno_delivery"]').value;
                    const direccion = document.querySelector('textarea[name="direccion"]').value.trim();
                    
                    if (!turno) {
                        e.preventDefault();
                        alert('⚠️ Por favor selecciona un turno de delivery');
                        return false;
                    }
                    
                    if (!direccion) {
                        e.preventDefault();
                        alert('⚠️ La dirección es obligatoria para delivery');
                        document.querySelector('textarea[name="direccion"]').focus();
                        return false;
                    }
                }
                
                // Validación de producto
                const tipoTab = document.getElementById('tipo_pedido_hidden').value;
                
                if (tipoTab === 'predefinido') {
                    const productoSeleccionado = document.querySelector('input[name="producto_id"]:checked');
                    if (!productoSeleccionado) {
                        e.preventDefault();
                        alert('⚠️ Selecciona un producto');
                        return false;
                    }
                } else if (tipoTab === 'personalizado') {
                    const cantidad = document.querySelector('input[name="cant_personalizado"]').value;
                    const sabores = document.querySelectorAll('input[name="sabores_personalizados[]"]:checked');
                    
                    if (!cantidad || cantidad <= 0) {
                        e.preventDefault();
                        alert('⚠️ Ingresa una cantidad válida');
                        return false;
                    }
                    
                    if (sabores.length === 0) {
                        e.preventDefault();
                        alert('⚠️ Selecciona al menos un sabor');
                        return false;
                    }
                }
                
                return true;
            });

            // Inicialización
            showTab('predefinido');
        });
    </script>
</body>
</html>