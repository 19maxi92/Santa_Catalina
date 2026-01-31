<?php
/**
 * Formulario público de pedidos online
 * Los clientes acceden vía link de WhatsApp
 */
require_once '../admin/config.php';

$pdo = getConnection();

// Obtener configuración de turnos activos
$stmt = $pdo->query("
    SELECT * FROM config_pedidos_online
    WHERE activo = 1
    ORDER BY FIELD(turno, 'Mañana', 'Siesta', 'Tarde')
");
$turnos_disponibles = $stmt->fetchAll();

// Obtener productos activos (para los combos)
$stmt = $pdo->query("SELECT * FROM productos WHERE activo = 1 ORDER BY nombre ASC");
$productos = $stmt->fetchAll();

// Procesar formulario
$mensaje = null;
$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $nombre = trim($_POST['nombre'] ?? '');
        $apellido = trim($_POST['apellido'] ?? '');
        $telefono = trim($_POST['telefono'] ?? '');
        $turno = trim($_POST['turno'] ?? '');
        $producto_id = (int)($_POST['producto'] ?? 0);
        $cantidad = (int)($_POST['cantidad'] ?? 1);
        $forma_pago = trim($_POST['forma_pago'] ?? '');
        $modalidad = trim($_POST['modalidad'] ?? 'Retiro');
        $direccion = trim($_POST['direccion'] ?? '');
        $observaciones = trim($_POST['observaciones'] ?? '');

        // Validaciones
        if (empty($nombre) || empty($apellido) || empty($telefono)) {
            throw new Exception('Por favor completá nombre, apellido y teléfono');
        }

        if (empty($turno)) {
            throw new Exception('Por favor seleccioná un turno');
        }

        if ($producto_id === 0) {
            throw new Exception('Por favor seleccioná un producto');
        }

        // Verificar stock del turno
        $stmt = $pdo->prepare("SELECT * FROM config_pedidos_online WHERE turno = ? AND activo = 1");
        $stmt->execute([$turno]);
        $config_turno = $stmt->fetch();

        if (!$config_turno) {
            throw new Exception('El turno seleccionado no está disponible');
        }

        if ($config_turno['stock_actual'] <= 0) {
            throw new Exception('¡Lo sentimos! No hay stock disponible para el turno seleccionado');
        }

        // Obtener datos del producto
        $stmt = $pdo->prepare("SELECT * FROM productos WHERE id = ? AND activo = 1");
        $stmt->execute([$producto_id]);
        $producto = $stmt->fetch();

        if (!$producto) {
            throw new Exception('Producto no válido');
        }

        // Calcular precio según forma de pago
        $precio = ($forma_pago === 'Efectivo')
            ? $producto['precio_efectivo'] * $cantidad
            : $producto['precio_transferencia'] * $cantidad;

        // Crear el pedido
        $fecha_entrega = date('Y-m-d');
        $ubicacion = 'Local 1'; // Por defecto, puedes agregar selector después

        $obs_completa = "🌐 PEDIDO ONLINE\nTurno: {$turno}";
        if (!empty($observaciones)) {
            $obs_completa .= "\n\nObservaciones:\n{$observaciones}";
        }

        $stmt = $pdo->prepare("
            INSERT INTO pedidos (
                nombre, apellido, telefono, direccion,
                producto, cantidad, precio,
                modalidad, forma_pago, ubicacion,
                estado, observaciones, fecha_entrega,
                created_at, fecha_display
            ) VALUES (
                ?, ?, ?, ?,
                ?, ?, ?,
                ?, ?, ?,
                'Pendiente', ?, ?,
                NOW(), ?
            )
        ");

        $fecha_display = date('d/m H:i');

        $stmt->execute([
            $nombre, $apellido, $telefono, $direccion,
            $producto['nombre'], $cantidad, $precio,
            $modalidad, $forma_pago, $ubicacion,
            $obs_completa, $fecha_entrega,
            $fecha_display
        ]);

        $pedido_id = $pdo->lastInsertId();

        // Descontar del stock
        $pdo->prepare("UPDATE config_pedidos_online SET stock_actual = stock_actual - 1 WHERE turno = ?")
            ->execute([$turno]);

        $mensaje = "✅ ¡Pedido #$pedido_id confirmado!\n\nTe esperamos en el turno de {$turno}.\nTotal: $" . number_format($precio, 0, ',', '.');

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
    <title>Pedido Online - <?= APP_NAME ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="bg-gradient-to-br from-orange-50 to-yellow-50 min-h-screen">

    <div class="container mx-auto px-4 py-8 max-w-2xl">

        <!-- Header -->
        <div class="text-center mb-8">
            <h1 class="text-4xl font-black text-gray-900 mb-2">
                <i class="fas fa-shopping-cart text-orange-500"></i>
                Pedido Online
            </h1>
            <p class="text-gray-600">Hace tu pedido rápido y fácil</p>
        </div>

        <?php if ($mensaje): ?>
            <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-6 rounded-lg">
                <div class="flex items-start">
                    <i class="fas fa-check-circle text-2xl mr-3 mt-1"></i>
                    <div>
                        <p class="font-bold text-lg">¡Pedido confirmado!</p>
                        <p class="whitespace-pre-line mt-2"><?= htmlspecialchars($mensaje) ?></p>
                        <button onclick="location.reload()" class="mt-4 bg-green-600 text-white px-4 py-2 rounded-lg hover:bg-green-700">
                            <i class="fas fa-plus mr-2"></i>Hacer otro pedido
                        </button>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-6 rounded-lg">
                <div class="flex items-start">
                    <i class="fas fa-exclamation-triangle text-2xl mr-3 mt-1"></i>
                    <div>
                        <p class="font-bold">Error</p>
                        <p><?= htmlspecialchars($error) ?></p>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <?php if (!$mensaje): ?>
        <!-- Formulario -->
        <form method="POST" class="bg-white rounded-2xl shadow-xl p-6 space-y-6">

            <!-- Datos personales -->
            <div class="bg-blue-50 rounded-xl p-4">
                <h2 class="text-lg font-bold text-gray-800 mb-4">
                    <i class="fas fa-user mr-2 text-blue-600"></i>
                    Tus datos
                </h2>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">Nombre *</label>
                        <input type="text" name="nombre" required
                               class="w-full px-4 py-3 border-2 border-gray-300 rounded-lg focus:border-blue-500 focus:ring-2 focus:ring-blue-200">
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">Apellido *</label>
                        <input type="text" name="apellido" required
                               class="w-full px-4 py-3 border-2 border-gray-300 rounded-lg focus:border-blue-500 focus:ring-2 focus:ring-blue-200">
                    </div>
                    <div class="md:col-span-2">
                        <label class="block text-sm font-semibold text-gray-700 mb-2">Teléfono *</label>
                        <input type="tel" name="telefono" required placeholder="Ej: 2604123456"
                               class="w-full px-4 py-3 border-2 border-gray-300 rounded-lg focus:border-blue-500 focus:ring-2 focus:ring-blue-200">
                    </div>
                </div>
            </div>

            <!-- Selección de turno -->
            <div class="bg-purple-50 rounded-xl p-4">
                <h2 class="text-lg font-bold text-gray-800 mb-4">
                    <i class="fas fa-clock mr-2 text-purple-600"></i>
                    ¿Cuándo lo retirás? *
                </h2>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
                    <?php foreach ($turnos_disponibles as $turno): ?>
                        <label class="cursor-pointer">
                            <input type="radio" name="turno" value="<?= $turno['turno'] ?>" required class="peer hidden">
                            <div class="border-2 border-gray-300 peer-checked:border-purple-600 peer-checked:bg-purple-100 rounded-xl p-4 text-center hover:border-purple-400 transition-all">
                                <div class="text-2xl font-black text-gray-900"><?= $turno['turno'] ?></div>
                                <div class="text-sm text-gray-600"><?= substr($turno['hora_inicio'], 0, 5) ?> - <?= substr($turno['hora_fin'], 0, 5) ?></div>
                                <div class="mt-2 text-xs font-semibold <?= $turno['stock_actual'] > 0 ? 'text-green-600' : 'text-red-600' ?>">
                                    <?= $turno['stock_actual'] > 0
                                        ? "✅ Disponible ({$turno['stock_actual']} cupos)"
                                        : "❌ Sin stock" ?>
                                </div>
                            </div>
                        </label>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Producto -->
            <div class="bg-green-50 rounded-xl p-4">
                <h2 class="text-lg font-bold text-gray-800 mb-4">
                    <i class="fas fa-hamburger mr-2 text-green-600"></i>
                    ¿Qué querés pedir? *
                </h2>
                <select name="producto" required
                        class="w-full px-4 py-3 border-2 border-gray-300 rounded-lg focus:border-green-500 focus:ring-2 focus:ring-green-200 font-semibold text-lg">
                    <option value="">-- Seleccioná un producto --</option>
                    <?php foreach ($productos as $prod): ?>
                        <option value="<?= $prod['id'] ?>"
                                data-precio-efectivo="<?= $prod['precio_efectivo'] ?>"
                                data-precio-transferencia="<?= $prod['precio_transferencia'] ?>">
                            <?= htmlspecialchars($prod['nombre']) ?>
                            - Efectivo: $<?= number_format($prod['precio_efectivo'], 0, ',', '.') ?>
                            | Transferencia: $<?= number_format($prod['precio_transferencia'], 0, ',', '.') ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- Cantidad -->
            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-2">Cantidad</label>
                <input type="number" name="cantidad" value="1" min="1" max="10"
                       class="w-full px-4 py-3 border-2 border-gray-300 rounded-lg focus:border-blue-500 focus:ring-2 focus:ring-blue-200">
            </div>

            <!-- Forma de pago -->
            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-2">Forma de pago *</label>
                <div class="grid grid-cols-2 gap-3">
                    <label class="cursor-pointer">
                        <input type="radio" name="forma_pago" value="Efectivo" required class="peer hidden">
                        <div class="border-2 border-gray-300 peer-checked:border-green-600 peer-checked:bg-green-100 rounded-lg p-3 text-center hover:border-green-400 transition-all">
                            <i class="fas fa-money-bill-wave text-2xl text-green-600"></i>
                            <div class="font-bold mt-1">Efectivo</div>
                        </div>
                    </label>
                    <label class="cursor-pointer">
                        <input type="radio" name="forma_pago" value="Transferencia" required class="peer hidden">
                        <div class="border-2 border-gray-300 peer-checked:border-blue-600 peer-checked:bg-blue-100 rounded-lg p-3 text-center hover:border-blue-400 transition-all">
                            <i class="fas fa-university text-2xl text-blue-600"></i>
                            <div class="font-bold mt-1">Transferencia</div>
                        </div>
                    </label>
                </div>
            </div>

            <!-- Modalidad -->
            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-2">Modalidad *</label>
                <div class="grid grid-cols-2 gap-3">
                    <label class="cursor-pointer">
                        <input type="radio" name="modalidad" value="Retiro" checked class="peer hidden" onchange="toggleDireccion()">
                        <div class="border-2 border-gray-300 peer-checked:border-orange-600 peer-checked:bg-orange-100 rounded-lg p-3 text-center hover:border-orange-400 transition-all">
                            <i class="fas fa-shopping-bag text-2xl text-orange-600"></i>
                            <div class="font-bold mt-1">Retiro</div>
                        </div>
                    </label>
                    <label class="cursor-pointer">
                        <input type="radio" name="modalidad" value="Delivery" class="peer hidden" onchange="toggleDireccion()">
                        <div class="border-2 border-gray-300 peer-checked:border-purple-600 peer-checked:bg-purple-100 rounded-lg p-3 text-center hover:border-purple-400 transition-all">
                            <i class="fas fa-motorcycle text-2xl text-purple-600"></i>
                            <div class="font-bold mt-1">Delivery</div>
                        </div>
                    </label>
                </div>
            </div>

            <!-- Dirección (solo si es delivery) -->
            <div id="direccionContainer" class="hidden">
                <label class="block text-sm font-semibold text-gray-700 mb-2">Dirección de entrega</label>
                <input type="text" name="direccion" id="direccion"
                       class="w-full px-4 py-3 border-2 border-gray-300 rounded-lg focus:border-blue-500 focus:ring-2 focus:ring-blue-200"
                       placeholder="Calle y número">
            </div>

            <!-- Observaciones -->
            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-2">
                    Observaciones (opcional)
                    <span class="text-xs text-gray-500">Ej: sin cebolla, vegetariano, etc.</span>
                </label>
                <textarea name="observaciones" rows="3"
                          class="w-full px-4 py-3 border-2 border-gray-300 rounded-lg focus:border-blue-500 focus:ring-2 focus:ring-blue-200"
                          placeholder="Dejanos tus comentarios..."></textarea>
            </div>

            <!-- Botón -->
            <button type="submit"
                    class="w-full bg-gradient-to-r from-orange-500 to-red-500 text-white py-4 rounded-xl font-black text-xl hover:from-orange-600 hover:to-red-600 shadow-lg transition-all">
                <i class="fas fa-check-circle mr-2"></i>
                CONFIRMAR PEDIDO
            </button>

        </form>
        <?php endif; ?>

    </div>

    <script>
    function toggleDireccion() {
        const modalidad = document.querySelector('input[name="modalidad"]:checked').value;
        const container = document.getElementById('direccionContainer');
        const input = document.getElementById('direccion');

        if (modalidad === 'Delivery') {
            container.classList.remove('hidden');
            input.required = true;
        } else {
            container.classList.add('hidden');
            input.required = false;
        }
    }
    </script>

</body>
</html>
