<?php
require_once '../../config.php';
requireLogin();

$pdo = getConnection();

// OBTENER PRECIOS ACTUALIZADOS DESDE LA BASE DE DATOS
$preciosDB = [
    'jyq24' => ['nombre' => 'Jamón y Queso x24', 'precio' => 18000, 'cantidad' => 24],
    'jyq48' => ['nombre' => 'Jamón y Queso x48', 'precio' => 22000, 'cantidad' => 48],
    'surtido_clasico48' => ['nombre' => 'Surtido Clásico x48', 'precio' => 20000, 'cantidad' => 48],
    'surtido_especial48' => ['nombre' => 'Surtido Especial x48', 'precio' => 25000, 'cantidad' => 48]
];

try {
    $stmt = $pdo->query("SELECT nombre, precio_efectivo FROM productos WHERE activo = 1");
    while ($producto = $stmt->fetch()) {
        $nombre_lower = strtolower($producto['nombre']);

        // Detectar y mapear productos a las claves de pedidos express
        if (strpos($nombre_lower, 'jamón') !== false && strpos($nombre_lower, 'queso') !== false) {
            if (strpos($nombre_lower, '24') !== false || strpos($nombre_lower, 'x24') !== false) {
                $preciosDB['jyq24']['precio'] = (float)$producto['precio_efectivo'];
                $preciosDB['jyq24']['nombre'] = $producto['nombre'];
            } elseif (strpos($nombre_lower, '48') !== false || strpos($nombre_lower, 'x48') !== false) {
                $preciosDB['jyq48']['precio'] = (float)$producto['precio_efectivo'];
                $preciosDB['jyq48']['nombre'] = $producto['nombre'];
            }
        }

        if (strpos($nombre_lower, 'surtido') !== false && strpos($nombre_lower, 'clásico') !== false) {
            $preciosDB['surtido_clasico48']['precio'] = (float)$producto['precio_efectivo'];
            $preciosDB['surtido_clasico48']['nombre'] = $producto['nombre'];
        }

        if (strpos($nombre_lower, 'surtido') !== false && strpos($nombre_lower, 'especial') !== false) {
            $preciosDB['surtido_especial48']['precio'] = (float)$producto['precio_efectivo'];
            $preciosDB['surtido_especial48']['nombre'] = $producto['nombre'];
        }
    }
} catch (Exception $e) {
    error_log("Error al cargar precios: " . $e->getMessage());
    // Usar precios por defecto si falla
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
        body { font-family: 'Arial', sans-serif; }

        .paso-indicador {
            display: flex;
            flex-direction: column;
            align-items: center;
            transition: all 0.3s;
        }

        .paso-indicador.activo div:first-child {
            background: #3b82f6;
            color: white;
            box-shadow: 0 0 0 4px rgba(59, 130, 246, 0.3);
        }

        .paso-indicador.completado div:first-child {
            background: #10b981;
            color: white;
        }

        .turno-card {
            border: 3px solid #e5e7eb;
            border-radius: 12px;
            padding: 20px;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s;
        }

        .turno-card:hover {
            border-color: #3b82f6;
            transform: translateY(-2px);
        }

        .turno-card.seleccionado {
            border-color: #2563eb;
            background: #eff6ff;
        }

        .pago-card {
            border: 3px solid #e5e7eb;
            border-radius: 12px;
            padding: 20px;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s;
            display: block;
        }

        .pago-card:hover {
            border-color: #3b82f6;
            transform: translateY(-2px);
        }

        .pago-card:has(input:checked) {
            border-color: #2563eb;
            background: #eff6ff;
        }

        .tipo-pedido-card {
            border: 4px solid #e5e7eb;
            border-radius: 16px;
            padding: 40px;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s;
        }

        .tipo-pedido-card:hover {
            border-color: #3b82f6;
            transform: scale(1.05);
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
        }

        .cantidad-btn {
            width: 36px;
            height: 36px;
            border: 2px solid #3b82f6;
            border-radius: 8px;
            background: white;
            color: #2563eb;
            font-weight: bold;
            font-size: 18px;
            cursor: pointer;
            transition: all 0.2s;
        }

        .cantidad-btn:hover {
            background: #3b82f6;
            color: white;
        }

        .combo-item label:has(input:checked) {
            border-color: #2563eb !important;
            background: #eff6ff !important;
        }

        .sabor-btn {
            transition: all 0.2s;
        }

        .sabor-btn:active {
            transform: scale(0.95);
        }
    </style>
</head>
<body class="bg-gradient-to-br from-blue-50 to-indigo-100 min-h-screen">

    <!-- Header -->
    <header class="bg-white shadow-md sticky top-0 z-50">
        <div class="container mx-auto px-4 py-3 flex justify-between items-center">
            <div class="flex items-center">
                <a href="../../index.php" class="text-gray-600 hover:text-gray-800 mr-4">
                    <i class="fas fa-arrow-left text-xl"></i>
                </a>
                <h1 class="text-xl font-bold text-gray-800">
                    <i class="fas fa-plus-circle text-blue-500 mr-2"></i>Nuevo Pedido Express
                </h1>
            </div>
            <div class="text-sm text-gray-600">
                <i class="fas fa-user-shield mr-1"></i>Admin
            </div>
        </div>
    </header>

    <main class="container mx-auto px-4 py-6 max-w-5xl">
        <div class="bg-white rounded-lg shadow-2xl overflow-hidden">

            <!-- Indicador de pasos -->
            <div class="bg-gradient-to-r from-blue-600 to-indigo-600 text-white p-6">
                <div class="flex items-center justify-center space-x-2">
                    <div id="indicador-paso-1" class="paso-indicador activo">
                        <div class="w-12 h-12 rounded-full bg-gray-300 text-gray-600 flex items-center justify-center font-bold text-lg">1</div>
                        <span class="text-xs mt-2 font-medium">Datos</span>
                    </div>
                    <div class="flex-1 h-1 bg-white bg-opacity-30 mx-2"></div>
                    <div id="indicador-paso-2" class="paso-indicador">
                        <div class="w-12 h-12 rounded-full bg-white bg-opacity-30 text-white flex items-center justify-center font-bold text-lg">2</div>
                        <span class="text-xs mt-2 font-medium">Tipo</span>
                    </div>
                    <div class="flex-1 h-1 bg-white bg-opacity-30 mx-2"></div>
                    <div id="indicador-paso-3" class="paso-indicador">
                        <div class="w-12 h-12 rounded-full bg-white bg-opacity-30 text-white flex items-center justify-center font-bold text-lg">3</div>
                        <span class="text-xs mt-2 font-medium">Pedido</span>
                    </div>
                    <div class="flex-1 h-1 bg-white bg-opacity-30 mx-2"></div>
                    <div id="indicador-paso-4" class="paso-indicador">
                        <div class="w-12 h-12 rounded-full bg-white bg-opacity-30 text-white flex items-center justify-center font-bold text-lg">4</div>
                        <span class="text-xs mt-2 font-medium">Resumen</span>
                    </div>
                </div>
            </div>

            <form id="formPedidoExpress">
                <div class="p-6 sm:p-8">

                    <!-- ============================================ -->
                    <!-- PASO 1: DATOS DEL CLIENTE -->
                    <!-- ============================================ -->
                    <div id="paso1" class="paso-container">
                        <h3 class="text-2xl font-bold text-gray-800 mb-6 flex items-center">
                            <i class="fas fa-user-circle mr-3 text-blue-600"></i>
                            Datos del Cliente
                        </h3>

                        <!-- Datos personales -->
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">
                                    Nombre *
                                </label>
                                <input type="text" id="nombre" required
                                       class="w-full px-4 py-3 border-2 border-gray-300 rounded-lg focus:border-blue-500 focus:ring-2 focus:ring-blue-200 text-lg"
                                       placeholder="Juan">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">
                                    Apellido *
                                </label>
                                <input type="text" id="apellido" required
                                       class="w-full px-4 py-3 border-2 border-gray-300 rounded-lg focus:border-blue-500 focus:ring-2 focus:ring-blue-200 text-lg"
                                       placeholder="Pérez">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">
                                    Teléfono
                                </label>
                                <input type="tel" id="telefono"
                                       class="w-full px-4 py-3 border-2 border-gray-300 rounded-lg focus:border-blue-500 focus:ring-2 focus:ring-blue-200 text-lg"
                                       placeholder="11 1234-5678">
                            </div>
                        </div>

                        <!-- Modalidad -->
                        <div class="mb-6">
                            <label class="block text-sm font-medium text-gray-700 mb-3">Modalidad *</label>
                            <div class="grid grid-cols-2 gap-4">
                                <label class="pago-card">
                                    <input type="radio" name="modalidad" value="Retiro" class="hidden" checked>
                                    <div class="text-4xl mb-2">🏪</div>
                                    <div class="font-bold">Retiro</div>
                                </label>
                                <label class="pago-card">
                                    <input type="radio" name="modalidad" value="Delivery" class="hidden">
                                    <div class="text-4xl mb-2">🛵</div>
                                    <div class="font-bold">Delivery</div>
                                </label>
                            </div>
                        </div>

                        <!-- Dirección (solo si es delivery) -->
                        <div id="direccion-container" class="mb-6 hidden">
                            <label class="block text-sm font-medium text-gray-700 mb-2">
                                Dirección de Entrega *
                            </label>
                            <textarea id="direccion" rows="2"
                                      class="w-full px-4 py-3 border-2 border-gray-300 rounded-lg focus:border-blue-500 focus:ring-2 focus:ring-blue-200"
                                      placeholder="Calle, número, barrio"></textarea>
                        </div>

                        <!-- Ubicación (ADMIN selecciona ubicación) -->
                        <div class="mb-6">
                            <label class="block text-sm font-medium text-gray-700 mb-3">Ubicación / Local *</label>
                            <div class="grid grid-cols-2 gap-4">
                                <label class="pago-card">
                                    <input type="radio" name="ubicacion" value="Local 1" class="hidden" checked>
                                    <div class="text-4xl mb-2">🏪</div>
                                    <div class="font-bold">Local 1</div>
                                </label>
                                <label class="pago-card">
                                    <input type="radio" name="ubicacion" value="Fábrica" class="hidden">
                                    <div class="text-4xl mb-2">🏭</div>
                                    <div class="font-bold">Fábrica</div>
                                </label>
                            </div>
                        </div>

                        <!-- Fecha de Entrega -->
                        <div class="mb-6">
                            <label class="block text-sm font-medium text-gray-700 mb-2">
                                Fecha de Entrega *
                                <span class="text-xs text-gray-500">(¿Para cuándo es el pedido?)</span>
                            </label>
                            <input type="date" id="fecha_entrega" required
                                   class="w-full px-4 py-3 border-2 border-gray-300 rounded-lg focus:border-blue-500 focus:ring-2 focus:ring-blue-200 text-lg"
                                   value="<?php echo date('Y-m-d'); ?>"
                                   min="<?php echo date('Y-m-d'); ?>">
                        </div>

                        <!-- Turno -->
                        <div class="mb-6">
                            <label class="block text-sm font-medium text-gray-700 mb-3">Turno de Entrega *</label>
                            <div class="grid grid-cols-3 gap-4">
                                <div class="turno-card" onclick="seleccionarTurno('Mañana', this)">
                                    <input type="radio" name="turno" value="Mañana" class="hidden">
                                    <div class="text-4xl mb-2">🌅</div>
                                    <div class="font-bold">MAÑANA</div>
                                    <div class="text-sm text-gray-600">06:00 - 14:00</div>
                                </div>
                                <div class="turno-card" onclick="seleccionarTurno('Siesta', this)">
                                    <input type="radio" name="turno" value="Siesta" class="hidden">
                                    <div class="text-4xl mb-2">☀️</div>
                                    <div class="font-bold">SIESTA</div>
                                    <div class="text-sm text-gray-600">14:00 - 18:00</div>
                                </div>
                                <div class="turno-card" onclick="seleccionarTurno('Tarde', this)">
                                    <input type="radio" name="turno" value="Tarde" class="hidden">
                                    <div class="text-4xl mb-2">🌙</div>
                                    <div class="font-bold">TARDE</div>
                                    <div class="text-sm text-gray-600">18:00 - 23:00</div>
                                </div>
                            </div>
                        </div>

                        <!-- Forma de pago -->
                        <div class="mb-6">
                            <label class="block text-sm font-medium text-gray-700 mb-3">Forma de Pago *</label>
                            <div class="grid grid-cols-2 gap-4">
                                <label class="pago-card">
                                    <input type="radio" name="forma_pago" value="Efectivo" class="hidden">
                                    <div class="text-4xl mb-2">💵</div>
                                    <div class="font-bold">Efectivo</div>
                                    <div class="text-xs text-green-600 mt-1">10% descuento</div>
                                </label>
                                <label class="pago-card">
                                    <input type="radio" name="forma_pago" value="Transferencia" class="hidden">
                                    <div class="text-4xl mb-2">💳</div>
                                    <div class="font-bold">Transferencia</div>
                                </label>
                            </div>
                        </div>

                        <!-- Observaciones generales -->
                        <div class="mb-6">
                            <label class="block text-sm font-medium text-gray-700 mb-2">
                                Observaciones Generales (opcional)
                            </label>
                            <textarea id="observaciones_generales" rows="2"
                                      class="w-full px-4 py-3 border-2 border-gray-300 rounded-lg focus:border-blue-500 focus:ring-2 focus:ring-blue-200"
                                      placeholder="Notas adicionales sobre el pedido..."></textarea>
                        </div>

                        <!-- Botón siguiente -->
                        <div class="flex justify-end">
                            <button type="button" onclick="irAPaso(2)"
                                    class="px-8 py-3 bg-blue-600 hover:bg-blue-700 text-white rounded-lg font-bold text-lg shadow-lg">
                                SIGUIENTE <i class="fas fa-arrow-right ml-2"></i>
                            </button>
                        </div>
                    </div>

                    <!-- ============================================ -->
                    <!-- PASO 2: SELECCIONAR TIPO DE PEDIDO -->
                    <!-- ============================================ -->
                    <div id="paso2" class="paso-container hidden">
                        <h3 class="text-2xl font-bold text-gray-800 mb-6 flex items-center">
                            <i class="fas fa-clipboard-list mr-3 text-blue-600"></i>
                            ¿Qué tipo de pedido?
                        </h3>

                        <div class="grid grid-cols-2 gap-6 mb-6">
                            <!-- Pedido Común -->
                            <div class="tipo-pedido-card" onclick="seleccionarTipoPedido('comun')">
                                <div class="text-6xl mb-4">🍔</div>
                                <h4 class="text-2xl font-bold mb-2">COMÚN</h4>
                                <p class="text-gray-600">Combos armados del menú</p>
                            </div>

                            <!-- Pedido Personalizado -->
                            <div class="tipo-pedido-card" onclick="seleccionarTipoPedido('personalizado')">
                                <div class="text-6xl mb-4">🎨</div>
                                <h4 class="text-2xl font-bold mb-2">PERSONALIZADO</h4>
                                <p class="text-gray-600">Elegir planchas y sabores</p>
                            </div>
                        </div>

                        <!-- Botones navegación -->
                        <div class="flex justify-between">
                            <button type="button" onclick="irAPaso(1)"
                                    class="px-6 py-3 bg-gray-500 hover:bg-gray-600 text-white rounded-lg font-semibold">
                                <i class="fas fa-arrow-left mr-2"></i>VOLVER
                            </button>
                            <a href="../../index.php"
                               class="px-6 py-3 bg-red-500 hover:bg-red-600 text-white rounded-lg font-semibold inline-block">
                                <i class="fas fa-times mr-2"></i>CANCELAR
                            </a>
                        </div>
                    </div>

                    <!-- ============================================ -->
                    <!-- PASO 3A: PEDIDO COMÚN -->
                    <!-- ============================================ -->
                    <div id="paso3comun" class="paso-container hidden">
                        <h3 class="text-2xl font-bold text-gray-800 mb-6 flex items-center">
                            <i class="fas fa-hamburger mr-3 text-blue-600"></i>
                            Seleccioná los combos
                        </h3>

                        <div class="space-y-3 mb-6">
                            <!-- JyQ x24 -->
                            <div class="combo-item" data-tipo="jyq24" data-precio="<?= $preciosDB['jyq24']['precio'] ?>">
                                <label class="flex items-center p-4 border-2 border-gray-300 rounded-lg hover:border-blue-500 cursor-pointer transition-all">
                                    <input type="checkbox" class="combo-checkbox w-5 h-5 mr-4">
                                    <div class="flex-1">
                                        <div class="font-bold text-lg"><?= htmlspecialchars($preciosDB['jyq24']['nombre']) ?></div>
                                        <div class="text-blue-600 font-bold text-xl"><?= formatPrice($preciosDB['jyq24']['precio']) ?></div>
                                    </div>
                                    <div class="flex items-center space-x-3">
                                        <button type="button" onclick="cambiarCantidadCombo(this, -1)" class="cantidad-btn">-</button>
                                        <span class="cantidad-display font-bold text-lg w-8 text-center">1</span>
                                        <button type="button" onclick="cambiarCantidadCombo(this, 1)" class="cantidad-btn">+</button>
                                    </div>
                                </label>
                            </div>

                            <!-- JyQ x48 -->
                            <div class="combo-item" data-tipo="jyq48" data-precio="<?= $preciosDB['jyq48']['precio'] ?>">
                                <label class="flex items-center p-4 border-2 border-gray-300 rounded-lg hover:border-blue-500 cursor-pointer transition-all">
                                    <input type="checkbox" class="combo-checkbox w-5 h-5 mr-4">
                                    <div class="flex-1">
                                        <div class="font-bold text-lg"><?= htmlspecialchars($preciosDB['jyq48']['nombre']) ?></div>
                                        <div class="text-blue-600 font-bold text-xl"><?= formatPrice($preciosDB['jyq48']['precio']) ?></div>
                                    </div>
                                    <div class="flex items-center space-x-3">
                                        <button type="button" onclick="cambiarCantidadCombo(this, -1)" class="cantidad-btn">-</button>
                                        <span class="cantidad-display font-bold text-lg w-8 text-center">1</span>
                                        <button type="button" onclick="cambiarCantidadCombo(this, 1)" class="cantidad-btn">+</button>
                                    </div>
                                </label>
                            </div>

                            <!-- Surtido Clásico -->
                            <div class="combo-item" data-tipo="surtido_clasico48" data-precio="<?= $preciosDB['surtido_clasico48']['precio'] ?>">
                                <label class="flex items-center p-4 border-2 border-gray-300 rounded-lg hover:border-blue-500 cursor-pointer transition-all">
                                    <input type="checkbox" class="combo-checkbox w-5 h-5 mr-4">
                                    <div class="flex-1">
                                        <div class="font-bold text-lg"><?= htmlspecialchars($preciosDB['surtido_clasico48']['nombre']) ?></div>
                                        <div class="text-blue-600 font-bold text-xl"><?= formatPrice($preciosDB['surtido_clasico48']['precio']) ?></div>
                                    </div>
                                    <div class="flex items-center space-x-3">
                                        <button type="button" onclick="cambiarCantidadCombo(this, -1)" class="cantidad-btn">-</button>
                                        <span class="cantidad-display font-bold text-lg w-8 text-center">1</span>
                                        <button type="button" onclick="cambiarCantidadCombo(this, 1)" class="cantidad-btn">+</button>
                                    </div>
                                </label>
                            </div>

                            <!-- Surtido Especial -->
                            <div class="combo-item" data-tipo="surtido_especial48" data-precio="<?= $preciosDB['surtido_especial48']['precio'] ?>">
                                <label class="flex items-center p-4 border-2 border-gray-300 rounded-lg hover:border-blue-500 cursor-pointer transition-all">
                                    <input type="checkbox" class="combo-checkbox w-5 h-5 mr-4">
                                    <div class="flex-1">
                                        <div class="font-bold text-lg"><?= htmlspecialchars($preciosDB['surtido_especial48']['nombre']) ?></div>
                                        <div class="text-blue-600 font-bold text-xl"><?= formatPrice($preciosDB['surtido_especial48']['precio']) ?></div>
                                    </div>
                                    <div class="flex items-center space-x-3">
                                        <button type="button" onclick="cambiarCantidadCombo(this, -1)" class="cantidad-btn">-</button>
                                        <span class="cantidad-display font-bold text-lg w-8 text-center">1</span>
                                        <button type="button" onclick="cambiarCantidadCombo(this, 1)" class="cantidad-btn">+</button>
                                    </div>
                                </label>
                            </div>
                        </div>

                        <!-- Observaciones -->
                        <div class="mb-6">
                            <label class="block text-sm font-medium text-gray-700 mb-2">Observaciones (opcional)</label>
                            <textarea id="observaciones_comun" rows="3"
                                      class="w-full px-4 py-3 border-2 border-gray-300 rounded-lg focus:border-blue-500"
                                      placeholder="Ej: Sin lechuga, tomate a parte..."></textarea>
                        </div>

                        <!-- Botones navegación -->
                        <div class="flex justify-between">
                            <button type="button" onclick="irAPaso(2)"
                                    class="px-6 py-3 bg-gray-500 hover:bg-gray-600 text-white rounded-lg font-semibold">
                                <i class="fas fa-arrow-left mr-2"></i>VOLVER
                            </button>
                            <button type="button" onclick="agregarPedidosComunes()"
                                    class="px-8 py-3 bg-blue-600 hover:bg-blue-700 text-white rounded-lg font-bold">
                                <i class="fas fa-check mr-2"></i>AGREGAR PEDIDO(S)
                            </button>
                        </div>
                    </div>

                    <!-- ============================================ -->
                    <!-- PASO 3B: PEDIDO PERSONALIZADO -->
                    <!-- ============================================ -->
                    <div id="paso3personalizado" class="paso-container hidden">
                        <h3 class="text-2xl font-bold text-gray-800 mb-4 flex items-center">
                            <i class="fas fa-palette mr-3 text-blue-600"></i>
                            Armá tu pedido personalizado
                        </h3>

                        <!-- Contador total -->
                        <div class="bg-blue-50 border-2 border-blue-300 rounded-lg p-4 mb-4">
                            <div class="flex justify-between items-center">
                                <span class="text-lg font-semibold">Total de planchas:</span>
                                <span id="totalPlanchas" class="text-4xl font-bold text-blue-600">0</span>
                            </div>
                            <div class="text-sm text-gray-600 mt-1">
                                <span id="totalSandwiches">0</span> sándwiches totales (8 por plancha)
                            </div>
                        </div>

                        <!-- Sabores Comunes -->
                        <div class="mb-4">
                            <h4 class="font-bold text-green-700 mb-2">🟢 SABORES COMUNES</h4>
                            <div id="saboresComunes" class="grid grid-cols-4 gap-2"></div>
                        </div>

                        <!-- Sabores Premium -->
                        <div class="mb-4">
                            <h4 class="font-bold text-orange-600 mb-2">🟠 SABORES PREMIUM</h4>
                            <div id="saboresPremium" class="grid grid-cols-5 gap-2"></div>
                        </div>

                        <!-- Botón deshacer -->
                        <div class="mb-4">
                            <button type="button" onclick="deshacer()"
                                    class="px-4 py-2 bg-yellow-500 hover:bg-yellow-600 text-white rounded font-semibold">
                                <i class="fas fa-undo mr-2"></i>Deshacer última plancha
                            </button>
                        </div>

                        <!-- Precio -->
                        <div class="mb-4">
                            <label class="block text-sm font-medium text-gray-700 mb-2">Precio Total *</label>
                            <input type="number" id="precioPersonalizado" step="500" required
                                   class="w-full px-4 py-3 border-2 border-gray-300 rounded-lg focus:border-blue-500 text-lg"
                                   placeholder="Ej: 14500">
                        </div>

                        <!-- Observaciones -->
                        <div class="mb-6">
                            <label class="block text-sm font-medium text-gray-700 mb-2">Observaciones (opcional)</label>
                            <textarea id="observaciones_personalizado" rows="3"
                                      class="w-full px-4 py-3 border-2 border-gray-300 rounded-lg focus:border-blue-500"
                                      placeholder="Ej: Con pan y queso extra..."></textarea>
                        </div>

                        <!-- Botones navegación -->
                        <div class="flex justify-between">
                            <button type="button" onclick="irAPaso(2)"
                                    class="px-6 py-3 bg-gray-500 hover:bg-gray-600 text-white rounded-lg font-semibold">
                                <i class="fas fa-arrow-left mr-2"></i>VOLVER
                            </button>
                            <button type="button" onclick="agregarPedidoPersonalizado()"
                                    class="px-8 py-3 bg-blue-600 hover:bg-blue-700 text-white rounded-lg font-bold">
                                <i class="fas fa-check mr-2"></i>AGREGAR PEDIDO
                            </button>
                        </div>
                    </div>

                    <!-- ============================================ -->
                    <!-- PASO 4: RESUMEN -->
                    <!-- ============================================ -->
                    <div id="paso4" class="paso-container hidden">
                        <h3 class="text-2xl font-bold text-gray-800 mb-6 flex items-center">
                            <i class="fas fa-list-check mr-3 text-blue-600"></i>
                            Resumen del Pedido
                        </h3>

                        <!-- Info del cliente -->
                        <div class="bg-blue-50 border-2 border-blue-300 rounded-lg p-4 mb-6">
                            <div class="font-bold text-lg mb-2" id="resumenCliente">Cliente: -</div>
                            <div class="text-sm text-gray-700">
                                <span id="resumenUbicacion">Ubicación: -</span> |
                                <span id="resumenModalidad">Modalidad: -</span> |
                                <span id="resumenTurno">Turno: -</span> |
                                <span id="resumenPago">Pago: -</span>
                            </div>
                            <div id="resumenDireccion" class="text-sm text-gray-700 mt-2 hidden">
                                <i class="fas fa-map-marker-alt mr-1"></i><span></span>
                            </div>
                        </div>

                        <!-- Lista de pedidos -->
                        <div id="listaPedidosResumen" class="space-y-3 mb-6">
                            <!-- Se llena dinámicamente -->
                        </div>

                        <!-- Total -->
                        <div class="bg-green-50 border-2 border-green-500 rounded-lg p-4 mb-6">
                            <div class="flex justify-between items-center">
                                <span class="text-xl font-bold">TOTAL:</span>
                                <span id="totalFinal" class="text-4xl font-bold text-green-600">$0</span>
                            </div>
                        </div>

                        <!-- Botones finales -->
                        <div class="flex flex-col gap-3">
                            <button type="button" onclick="irAPaso(1)"
                                    class="px-6 py-3 bg-gray-400 hover:bg-gray-500 text-white rounded-lg font-semibold">
                                <i class="fas fa-edit mr-2"></i>EDITAR DATOS DEL CLIENTE
                            </button>
                            <button type="button" onclick="irAPaso(2)"
                                    class="px-6 py-3 bg-blue-500 hover:bg-blue-600 text-white rounded-lg font-bold text-lg">
                                <i class="fas fa-plus mr-2"></i>AGREGAR OTRO PEDIDO
                            </button>
                            <button type="button" onclick="finalizarYCrearPedidos()"
                                    class="px-6 py-3 bg-green-600 hover:bg-green-700 text-white rounded-lg font-bold text-xl">
                                <i class="fas fa-check-circle mr-2"></i>FINALIZAR Y CREAR PEDIDOS
                            </button>
                            <a href="../../index.php"
                               class="px-6 py-3 bg-red-500 hover:bg-red-600 text-white rounded-lg font-semibold text-center">
                                <i class="fas fa-times-circle mr-2"></i>CANCELAR TODO
                            </a>
                        </div>
                    </div>

                </div>
            </form>
        </div>
    </main>

    <script>
// ============================================
// 🎯 SISTEMA DE PASOS - PEDIDO EXPRESS ADMIN
// ============================================

// Variables globales
let pasoActual = 1;
let pedidosAcumulados = [];
let datosCliente = null;
let planchasPorSabor = {};
let historial = [];

// IMPORTANTE: Precios cargados desde la base de datos (PHP)
const precios = <?= json_encode($preciosDB) ?>;

// Función para cargar precios dinámicamente desde la API
async function cargarPreciosActualizados() {
    try {
        const response = await fetch('/api_precios.php');
        const data = await response.json();

        if (data.success) {
            // Actualizar el objeto precios con los valores de la base de datos
            precios = data.precios;

            // Actualizar los elementos HTML con los nuevos precios
            document.querySelectorAll('.combo-item').forEach(item => {
                const tipo = item.dataset.tipo;
                if (precios[tipo]) {
                    const precioValor = precios[tipo].precio;

                    // Actualizar data-precio
                    item.dataset.precio = precioValor;

                    // Actualizar el texto del precio mostrado
                    const precioDisplay = item.querySelector('.text-blue-600');
                    if (precioDisplay) {
                        precioDisplay.textContent = '$' + new Intl.NumberFormat('es-AR').format(precioValor);
                    }
                }
            });

            console.log('Precios actualizados correctamente:', precios);
        } else {
            console.error('Error al cargar precios:', data.error);
        }
    } catch (error) {
        console.error('Error al cargar precios desde API:', error);
        // En caso de error, se usan los precios por defecto
    }
}

const saboresComunes = [
    'Jamón y Queso', 'Lechuga', 'Tomate', 'Huevo',
    'Choclo', 'Aceitunas', 'Zanahoria y Queso', 'Zanahoria y Huevo'
];

const saboresPremium = [
    'Ananá', 'Atún', 'Berenjena', 'Jamón Crudo',
    'Morrón', 'Palmito', 'Panceta', 'Pollo', 'Roquefort', 'Salame'
];

// ============================================
// FUNCIONES DE NAVEGACIÓN
// ============================================

function irAPaso(numeroPaso) {
    // Validar antes de avanzar
    if (numeroPaso > pasoActual) {
        if (pasoActual === 1 && !validarPaso1()) return;
    }

    // Ocultar todos los pasos
    document.querySelectorAll('.paso-container').forEach(p => p.classList.add('hidden'));

    // Mostrar el paso solicitado
    if (numeroPaso === 1) {
        document.getElementById('paso1').classList.remove('hidden');
    } else if (numeroPaso === 2) {
        document.getElementById('paso2').classList.remove('hidden');
    } else if (numeroPaso === 4) {
        actualizarResumen();
        document.getElementById('paso4').classList.remove('hidden');
    }

    // Actualizar indicadores
    actualizarIndicadores(numeroPaso);
    pasoActual = numeroPaso;
}

function actualizarIndicadores(paso) {
    for (let i = 1; i <= 4; i++) {
        const indicador = document.getElementById(`indicador-paso-${i}`);
        if (!indicador) continue;

        indicador.classList.remove('activo', 'completado');

        if (i === paso) {
            indicador.classList.add('activo');
        } else if (i < paso) {
            indicador.classList.add('completado');
        }
    }
}

// ============================================
// VALIDACIONES
// ============================================

function validarPaso1() {
    const nombre = document.getElementById('nombre').value.trim();
    const apellido = document.getElementById('apellido').value.trim();
    const modalidad = document.querySelector('input[name="modalidad"]:checked');
    const ubicacion = document.querySelector('input[name="ubicacion"]:checked');
    const turno = document.querySelector('input[name="turno"]:checked');
    const formaPago = document.querySelector('input[name="forma_pago"]:checked');

    if (!nombre || !apellido) {
        alert('🏃‍♂️ Ingresá nombre y apellido');
        return false;
    }

    if (!modalidad) {
        alert('📦 Seleccioná la modalidad (Retiro o Delivery)');
        return false;
    }

    // Validar dirección si es delivery
    if (modalidad.value === 'Delivery') {
        const direccion = document.getElementById('direccion').value.trim();
        if (!direccion) {
            alert('📍 La dirección es obligatoria para delivery');
            return false;
        }
    }

    if (!ubicacion) {
        alert('📍 Seleccioná la ubicación');
        return false;
    }

    if (!turno) {
        alert('⏰ Seleccioná el turno');
        return false;
    }

    if (!formaPago) {
        alert('💳 Seleccioná la forma de pago');
        return false;
    }

    // Guardar datos del cliente
    datosCliente = {
        nombre: nombre,
        apellido: apellido,
        telefono: document.getElementById('telefono').value.trim(),
        modalidad: modalidad.value,
        direccion: document.getElementById('direccion').value.trim(),
        ubicacion: ubicacion.value,
        fecha_entrega: document.getElementById('fecha_entrega').value,
        turno: turno.value,
        formaPago: formaPago.value,
        observacionesGenerales: document.getElementById('observaciones_generales').value.trim()
    };

    return true;
}

// ============================================
// PASO 1: SELECCIÓN DE DATOS
// ============================================

function seleccionarTurno(turno, elemento) {
    document.querySelectorAll('.turno-card').forEach(c => c.classList.remove('seleccionado'));
    elemento.classList.add('seleccionado');
    elemento.querySelector('input[type="radio"]').checked = true;
}

// Mostrar/ocultar dirección según modalidad
document.addEventListener('DOMContentLoaded', function() {
    const modalidadInputs = document.querySelectorAll('input[name="modalidad"]');
    const direccionContainer = document.getElementById('direccion-container');

    modalidadInputs.forEach(input => {
        input.addEventListener('change', function() {
            if (this.value === 'Delivery') {
                direccionContainer.classList.remove('hidden');
                document.getElementById('direccion').required = true;
            } else {
                direccionContainer.classList.add('hidden');
                document.getElementById('direccion').required = false;
            }
        });
    });
});

// ============================================
// PASO 2: TIPO DE PEDIDO
// ============================================

function seleccionarTipoPedido(tipo) {
    if (tipo === 'comun') {
        document.getElementById('paso2').classList.add('hidden');
        document.getElementById('paso3comun').classList.remove('hidden');
        pasoActual = 3;
        actualizarIndicadores(3);
    } else if (tipo === 'personalizado') {
        document.getElementById('paso2').classList.add('hidden');
        document.getElementById('paso3personalizado').classList.remove('hidden');
        pasoActual = 3;
        actualizarIndicadores(3);

        // Generar botones de sabores si no existen
        if (document.getElementById('saboresComunes').children.length === 0) {
            generarBotonesSabores();
        }
    }
}

// ============================================
// PASO 3A: PEDIDOS COMUNES
// ============================================

function cambiarCantidadCombo(boton, cambio) {
    const item = boton.closest('.combo-item');
    const display = item.querySelector('.cantidad-display');
    let cantidad = parseInt(display.textContent);

    cantidad += cambio;
    if (cantidad < 1) cantidad = 1;
    if (cantidad > 99) cantidad = 99;

    display.textContent = cantidad;

    // Marcar checkbox si cantidad > 1
    const checkbox = item.querySelector('.combo-checkbox');
    if (cantidad > 1) {
        checkbox.checked = true;
    }
}

function agregarPedidosComunes() {
    const combosSeleccionados = [];
    const items = document.querySelectorAll('.combo-item');

    items.forEach(item => {
        const checkbox = item.querySelector('.combo-checkbox');
        if (checkbox.checked) {
            const tipo = item.dataset.tipo;
            const cantidad = parseInt(item.querySelector('.cantidad-display').textContent);
            const info = precios[tipo];

            // Crear un pedido por cada cantidad
            for (let i = 0; i < cantidad; i++) {
                combosSeleccionados.push({
                    tipo_pedido: tipo,
                    producto: info.nombre,
                    cantidad: info.cantidad,
                    precio: info.precio,
                    observaciones: document.getElementById('observaciones_comun').value.trim()
                });
            }
        }
    });

    if (combosSeleccionados.length === 0) {
        alert('⚠️ Seleccioná al menos un combo');
        return;
    }

    // Agregar a la lista de pedidos
    pedidosAcumulados.push(...combosSeleccionados);

    // Resetear selección de combos
    items.forEach(item => {
        item.querySelector('.combo-checkbox').checked = false;
        item.querySelector('.cantidad-display').textContent = '1';
    });
    document.getElementById('observaciones_comun').value = '';

    // Ir al resumen
    irAPaso(4);

    alert(`✅ ${combosSeleccionados.length} pedido(s) agregado(s)`);
}

// ============================================
// PASO 3B: PEDIDO PERSONALIZADO
// ============================================

function generarBotonesSabores() {
    const contenedorComunes = document.getElementById('saboresComunes');
    contenedorComunes.innerHTML = saboresComunes.map(sabor => `
        <button type="button" onclick="agregarPlancha('${sabor}')"
                class="sabor-btn p-3 bg-white border-2 border-green-300 rounded-lg text-xs font-medium hover:bg-green-100 transition-all">
            <div class="font-bold">${sabor}</div>
            <div id="count-${sabor.replace(/\s+/g, '-')}" class="text-green-600 font-bold mt-1 text-lg">0</div>
        </button>
    `).join('');

    const contenedorPremium = document.getElementById('saboresPremium');
    contenedorPremium.innerHTML = saboresPremium.map(sabor => `
        <button type="button" onclick="agregarPlancha('${sabor}')"
                class="sabor-btn p-3 bg-white border-2 border-orange-300 rounded-lg text-xs font-medium hover:bg-orange-100 transition-all">
            <div class="font-bold">${sabor}</div>
            <div id="count-${sabor.replace(/\s+/g, '-')}" class="text-orange-600 font-bold mt-1 text-lg">0</div>
        </button>
    `).join('');
}

function agregarPlancha(sabor) {
    historial.push(JSON.parse(JSON.stringify(planchasPorSabor)));
    planchasPorSabor[sabor] = (planchasPorSabor[sabor] || 0) + 1;
    actualizarContadores();
}

function deshacer() {
    if (historial.length > 0) {
        planchasPorSabor = historial.pop();
        actualizarContadores();
    }
}

function actualizarContadores() {
    [...saboresComunes, ...saboresPremium].forEach(sabor => {
        const id = 'count-' + sabor.replace(/\s+/g, '-');
        const elemento = document.getElementById(id);
        if (elemento) {
            elemento.textContent = planchasPorSabor[sabor] || 0;
        }
    });

    const totalPlanchas = Object.values(planchasPorSabor).reduce((a, b) => a + b, 0);
    const totalSandwiches = totalPlanchas * 8;

    const elemPlanchas = document.getElementById('totalPlanchas');
    const elemSandwiches = document.getElementById('totalSandwiches');

    if (elemPlanchas) elemPlanchas.textContent = totalPlanchas;
    if (elemSandwiches) elemSandwiches.textContent = totalSandwiches;
}

function agregarPedidoPersonalizado() {
    const totalPlanchas = Object.values(planchasPorSabor).reduce((sum, val) => sum + val, 0);

    if (totalPlanchas === 0) {
        alert('⚠️ Agregá al menos una plancha');
        return;
    }

    const precio = parseFloat(document.getElementById('precioPersonalizado').value);

    if (!precio || precio <= 0) {
        alert('💰 Ingresá el precio del pedido personalizado');
        return;
    }

    const totalSandwiches = totalPlanchas * 8;

    // Crear detalle de sabores
    let detalleSabores = '\n=== SABORES PERSONALIZADOS ===';
    for (let sabor in planchasPorSabor) {
        const planchas = planchasPorSabor[sabor];
        const sandwiches = planchas * 8;
        detalleSabores += `\n• ${sabor}: ${planchas} plancha${planchas > 1 ? 's' : ''} (${sandwiches} sándwiches)`;
    }

    const observaciones = document.getElementById('observaciones_personalizado').value.trim();

    // Agregar pedido
    pedidosAcumulados.push({
        tipo_pedido: 'personalizado',
        producto: `Personalizado x${totalSandwiches} (${totalPlanchas} plancha${totalPlanchas > 1 ? 's' : ''})`,
        cantidad: totalSandwiches,
        precio: precio,
        sabores_personalizados_json: JSON.stringify(planchasPorSabor),
        observaciones: observaciones + detalleSabores
    });

    // Resetear personalizado
    planchasPorSabor = {};
    historial = [];
    actualizarContadores();
    document.getElementById('precioPersonalizado').value = '';
    document.getElementById('observaciones_personalizado').value = '';

    // Ir al resumen
    irAPaso(4);

    alert('✅ Pedido personalizado agregado');
}

// ============================================
// PASO 4: RESUMEN
// ============================================

function actualizarResumen() {
    if (!datosCliente) return;

    // Actualizar info del cliente
    document.getElementById('resumenCliente').textContent =
        `Cliente: ${datosCliente.nombre} ${datosCliente.apellido}`;
    document.getElementById('resumenUbicacion').textContent =
        `Ubicación: ${datosCliente.ubicacion}`;
    document.getElementById('resumenModalidad').textContent =
        `Modalidad: ${datosCliente.modalidad}`;
    document.getElementById('resumenTurno').textContent =
        `Turno: ${datosCliente.turno}`;
    document.getElementById('resumenPago').textContent =
        `Pago: ${datosCliente.formaPago}`;

    // Mostrar dirección si es delivery
    const resumenDireccion = document.getElementById('resumenDireccion');
    if (datosCliente.modalidad === 'Delivery' && datosCliente.direccion) {
        resumenDireccion.classList.remove('hidden');
        resumenDireccion.querySelector('span').textContent = datosCliente.direccion;
    } else {
        resumenDireccion.classList.add('hidden');
    }

    // Actualizar lista de pedidos
    const lista = document.getElementById('listaPedidosResumen');
    lista.innerHTML = '';

    let total = 0;

    pedidosAcumulados.forEach((pedido, index) => {
        total += pedido.precio;

        const div = document.createElement('div');
        div.className = 'bg-white border-2 border-blue-300 rounded-lg p-4';
        div.innerHTML = `
            <div class="flex justify-between items-start">
                <div class="flex-1">
                    <div class="font-bold text-lg">${index + 1}. ${pedido.producto}</div>
                    ${pedido.observaciones ? `<div class="text-sm text-gray-600 mt-1">${pedido.observaciones.split('\n')[0]}</div>` : ''}
                </div>
                <div class="text-right">
                    <div class="text-xl font-bold text-blue-600">$${pedido.precio.toLocaleString()}</div>
                    <button type="button" onclick="eliminarPedidoResumen(${index})"
                            class="text-red-500 hover:text-red-700 text-sm mt-1">
                        <i class="fas fa-trash"></i> Eliminar
                    </button>
                </div>
            </div>
        `;
        lista.appendChild(div);
    });

    // Actualizar total
    document.getElementById('totalFinal').textContent = `$${total.toLocaleString()}`;

    // Mostrar mensaje si no hay pedidos
    if (pedidosAcumulados.length === 0) {
        lista.innerHTML = '<div class="text-center text-gray-500 py-8">No hay pedidos agregados aún</div>';
    }
}

function eliminarPedidoResumen(index) {
    if (confirm('¿Eliminar este pedido?')) {
        pedidosAcumulados.splice(index, 1);
        actualizarResumen();
    }
}

// ============================================
// FINALIZAR Y CREAR PEDIDOS
// ============================================

function finalizarYCrearPedidos() {
    if (pedidosAcumulados.length === 0) {
        alert('⚠️ No hay pedidos para crear');
        return;
    }

    if (!confirm(`¿Crear ${pedidosAcumulados.length} pedido(s) para ${datosCliente.nombre} ${datosCliente.apellido}?`)) {
        return;
    }

    const btn = event.target;
    const originalText = btn.innerHTML;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Creando...';
    btn.disabled = true;

    // Crear promesas para todos los pedidos
    const promesas = pedidosAcumulados.map((item, index) => {
        let observacionesCompletas = `Turno: ${datosCliente.turno}\n${item.observaciones || ''}`;

        if (datosCliente.observacionesGenerales) {
            observacionesCompletas += `\n\nObservaciones generales:\n${datosCliente.observacionesGenerales}`;
        }

        if (pedidosAcumulados.length > 1) {
            observacionesCompletas += `\n🔗 PEDIDO COMBINADO (${index + 1}/${pedidosAcumulados.length})`;
        }

        const pedidoCompleto = {
            nombre: datosCliente.nombre,
            apellido: datosCliente.apellido,
            telefono: datosCliente.telefono,
            direccion: datosCliente.direccion,
            modalidad: datosCliente.modalidad,
            forma_pago: datosCliente.formaPago,
            tipo_pedido: item.tipo_pedido,
            precio: item.precio,
            producto: item.producto,
            cantidad: item.cantidad,
            ubicacion: datosCliente.ubicacion,
            fecha_entrega: datosCliente.fecha_entrega,
            estado: 'Pendiente',
            observaciones: observacionesCompletas
        };

        if (item.sabores_personalizados_json) {
            pedidoCompleto.sabores_personalizados_json = item.sabores_personalizados_json;
        }

        return fetch('procesar_pedido_express.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(pedidoCompleto)
        })
        .then(response => {
            if (!response.ok) {
                throw new Error(`HTTP ${response.status}: ${response.statusText}`);
            }
            return response.json();
        })
        .catch(error => {
            console.error('Error en pedido:', error);
            return { success: false, error: error.message };
        });
    });

    Promise.all(promesas)
        .then(resultados => {
            if (resultados.every(r => r.success)) {
                const ids = resultados.map(r => `#${r.pedido_id}`).join(', ');
                const total = resultados.reduce((sum, r) => sum + r.data.precio, 0);

                let msg = `✅ ${resultados.length} pedido(s) creado(s) exitosamente!\n\n`;
                msg += `IDs: ${ids}\n`;
                msg += `Cliente: ${resultados[0].data.cliente}\n`;
                msg += `Ubicación: ${resultados[0].data.ubicacion}\n\n`;
                resultados.forEach((r, i) => {
                    msg += `${i + 1}. ${r.data.producto} - $${r.data.precio.toLocaleString()}\n`;
                });
                msg += `\nTOTAL: $${total.toLocaleString()}`;

                alert(msg);
                window.location.href = '../../index.php';
            } else {
                // Mostrar errores específicos
                let errorMsg = '❌ Error al crear pedidos:\n\n';
                resultados.forEach((r, i) => {
                    if (!r.success) {
                        errorMsg += `Pedido ${i + 1}: ${r.error || 'Error desconocido'}\n`;
                    }
                });
                alert(errorMsg);
                console.error('Errores detallados:', resultados);
            }
        })
        .catch(error => {
            alert('❌ Error de conexión al servidor.\n\nDetalles: ' + error.message);
            console.error('Error completo:', error);
        })
        .finally(() => {
            btn.innerHTML = originalText;
            btn.disabled = false;
        });
}

console.log('🚀 Sistema Express Admin cargado');

// Cargar precios actualizados al iniciar
cargarPreciosActualizados();
    </script>

</body>
</html>
