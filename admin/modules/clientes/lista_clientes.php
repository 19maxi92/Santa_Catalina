<?php
require_once '../../config.php';
requireLogin();

$pdo = getConnection();

// Manejar acciones (agregar/editar/eliminar)
$mensaje = '';
$error = '';

if ($_POST) {
    if (isset($_POST['accion'])) {
        switch ($_POST['accion']) {
            case 'agregar':
                $nombre = sanitize($_POST['nombre']);
                $apellido = sanitize($_POST['apellido']);
                $telefono = sanitize($_POST['telefono']);
                $direccion = sanitize($_POST['direccion']);
                $observaciones = sanitize($_POST['observaciones']);

                if ($nombre && $apellido && $telefono) {
                    try {
                        $stmt = $pdo->prepare("INSERT INTO clientes_fijos (nombre, apellido, telefono, direccion, observaciones) VALUES (?, ?, ?, ?, ?)");
                        $stmt->execute([$nombre, $apellido, $telefono, $direccion, $observaciones]);
                        $mensaje = 'Cliente agregado correctamente';
                    } catch (Exception $e) {
                        $error = 'Error al agregar cliente';
                    }
                } else {
                    $error = 'Nombre, apellido y teléfono son obligatorios';
                }
                break;

            case 'eliminar':
                $id = (int)$_POST['id'];
                try {
                    $stmt = $pdo->prepare("UPDATE clientes_fijos SET activo = 0 WHERE id = ?");
                    $stmt->execute([$id]);
                    $mensaje = 'Cliente eliminado correctamente';
                } catch (Exception $e) {
                    $error = 'Error al eliminar cliente';
                }
                break;
        }
    }
}

// Buscar clientes
$buscar = isset($_GET['buscar']) ? sanitize($_GET['buscar']) : '';
$sql = "SELECT cf.*,
        (SELECT COUNT(*) FROM pedidos WHERE telefono = cf.telefono) as total_pedidos,
        (SELECT MAX(created_at) FROM pedidos WHERE telefono = cf.telefono) as ultimo_pedido,
        (SELECT SUM(precio) FROM pedidos WHERE telefono = cf.telefono) as total_gastado
        FROM clientes_fijos cf WHERE cf.activo = 1";
$params = [];

if ($buscar) {
    $sql .= " AND (cf.nombre LIKE ? OR cf.apellido LIKE ? OR cf.telefono LIKE ?)";
    $buscarParam = "%$buscar%";
    $params = [$buscarParam, $buscarParam, $buscarParam];
}

$sql .= " ORDER BY total_pedidos DESC, cf.nombre, cf.apellido";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$clientes = $stmt->fetchAll();

// Obtener turnos y productos para el modal de pedido rápido
$turnos = $pdo->query("SELECT * FROM turnos WHERE activo = 1 ORDER BY hora_inicio")->fetchAll();
$productos = $pdo->query("SELECT * FROM productos WHERE activo = 1 ORDER BY categoria, nombre")->fetchAll();
$productosPorCategoria = [];
foreach ($productos as $p) {
    $productosPorCategoria[$p['categoria']][] = $p;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Clientes Fijos - <?= APP_NAME ?></title>
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
                    <i class="fas fa-users text-blue-500 mr-2"></i>Clientes Fijos
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
        </div>
        <?php endif; ?>

        <?php if ($error): ?>
        <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
            <i class="fas fa-exclamation-circle mr-2"></i><?= $error ?>
        </div>
        <?php endif; ?>

        <!-- Barra de herramientas -->
        <div class="bg-white rounded-lg shadow mb-6 p-4">
            <div class="flex flex-col md:flex-row justify-between items-center space-y-4 md:space-y-0">
                <!-- Buscador -->
                <form method="GET" class="flex-1 max-w-md">
                    <div class="flex">
                        <input type="text" name="buscar" value="<?= htmlspecialchars($buscar) ?>" 
                               placeholder="Buscar por nombre, apellido o teléfono..." 
                               class="flex-1 px-4 py-2 border border-gray-300 rounded-l-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                        <button type="submit" class="bg-blue-500 hover:bg-blue-600 text-white px-4 py-2 rounded-r-lg">
                            <i class="fas fa-search"></i>
                        </button>
                    </div>
                </form>
                
                <!-- Botón agregar -->
                <button onclick="toggleModal()" class="bg-green-500 hover:bg-green-600 text-white px-4 py-2 rounded-lg">
                    <i class="fas fa-plus mr-2"></i>Nuevo Cliente
                </button>
            </div>
        </div>

        <!-- Lista de clientes -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
            <?php if (empty($clientes)): ?>
                <div class="col-span-full p-8 text-center text-gray-500 bg-white rounded-lg shadow">
                    <i class="fas fa-users text-6xl mb-4"></i>
                    <h3 class="text-xl mb-2">No hay clientes registrados</h3>
                    <p>Agrega tu primer cliente fijo para empezar</p>
                </div>
            <?php else: ?>
                <?php foreach ($clientes as $cliente):
                    $esVIP = $cliente['total_pedidos'] >= 5;
                    $esFrecuente = $cliente['total_pedidos'] >= 3;
                ?>
                <div class="bg-white rounded-lg shadow-lg overflow-hidden hover:shadow-xl transition-shadow">
                    <!-- Header del cliente -->
                    <div class="p-4 <?= $esVIP ? 'bg-gradient-to-r from-yellow-400 to-orange-400 text-white' : ($esFrecuente ? 'bg-gradient-to-r from-purple-500 to-indigo-500 text-white' : 'bg-gray-100') ?>">
                        <div class="flex items-center justify-between">
                            <div class="flex items-center gap-3">
                                <div class="w-12 h-12 rounded-full <?= ($esVIP || $esFrecuente) ? 'bg-white/20' : 'bg-blue-100' ?> flex items-center justify-center text-xl font-bold <?= (!$esVIP && !$esFrecuente) ? 'text-blue-600' : '' ?>">
                                    <?= strtoupper(substr($cliente['nombre'], 0, 1)) ?>
                                </div>
                                <div>
                                    <div class="font-bold"><?= htmlspecialchars($cliente['nombre'] . ' ' . $cliente['apellido']) ?></div>
                                    <div class="text-sm <?= ($esVIP || $esFrecuente) ? 'text-white/80' : 'text-gray-600' ?>">
                                        <i class="fas fa-phone"></i> <?= htmlspecialchars($cliente['telefono']) ?>
                                    </div>
                                </div>
                            </div>
                            <?php if ($cliente['total_pedidos'] > 0): ?>
                            <div class="bg-white/20 <?= (!$esVIP && !$esFrecuente) ? 'bg-purple-100 text-purple-700' : '' ?> px-3 py-1 rounded-full text-sm font-bold">
                                <?= $cliente['total_pedidos'] ?> pedidos
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Info -->
                    <div class="p-4 border-b text-sm">
                        <?php if ($cliente['direccion']): ?>
                        <div class="text-gray-600 mb-2">
                            <i class="fas fa-map-marker-alt text-red-400 mr-1"></i>
                            <?= htmlspecialchars($cliente['direccion']) ?>
                        </div>
                        <?php endif; ?>
                        <?php if ($cliente['observaciones']): ?>
                        <div class="text-gray-500 text-xs italic mb-2">
                            <i class="fas fa-sticky-note text-yellow-500 mr-1"></i>
                            <?= htmlspecialchars($cliente['observaciones']) ?>
                        </div>
                        <?php endif; ?>
                        <div class="flex justify-between text-xs text-gray-500">
                            <?php if ($cliente['ultimo_pedido']): ?>
                            <span><i class="fas fa-clock"></i> Ultimo: <?= date('d/m/Y', strtotime($cliente['ultimo_pedido'])) ?></span>
                            <?php else: ?>
                            <span>Sin pedidos aun</span>
                            <?php endif; ?>
                            <?php if ($cliente['total_gastado']): ?>
                            <span class="text-green-600 font-semibold">Total: $<?= number_format($cliente['total_gastado'], 0, ',', '.') ?></span>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Acciones -->
                    <div class="p-3 bg-gray-50 flex gap-2">
                        <button onclick="pedidoRapido(<?= $cliente['id'] ?>, '<?= htmlspecialchars($cliente['nombre'], ENT_QUOTES) ?>', '<?= htmlspecialchars($cliente['apellido'], ENT_QUOTES) ?>', '<?= htmlspecialchars($cliente['telefono'], ENT_QUOTES) ?>', '<?= htmlspecialchars($cliente['direccion'] ?? '', ENT_QUOTES) ?>')"
                                class="flex-1 bg-green-500 hover:bg-green-600 text-white py-2 rounded-lg font-bold text-sm flex items-center justify-center gap-1">
                            <i class="fas fa-bolt"></i> Pedido Rapido
                        </button>
                        <?php if ($cliente['total_pedidos'] > 0): ?>
                        <button onclick="verHistorial('<?= htmlspecialchars($cliente['telefono'], ENT_QUOTES) ?>', '<?= htmlspecialchars($cliente['nombre'] . ' ' . $cliente['apellido'], ENT_QUOTES) ?>')"
                                class="bg-blue-500 hover:bg-blue-600 text-white px-3 py-2 rounded-lg text-sm"
                                title="Ver historial">
                            <i class="fas fa-history"></i>
                        </button>
                        <?php endif; ?>
                        <button onclick="editarCliente(<?= $cliente['id'] ?>, '<?= htmlspecialchars($cliente['nombre'], ENT_QUOTES) ?>', '<?= htmlspecialchars($cliente['apellido'], ENT_QUOTES) ?>', '<?= htmlspecialchars($cliente['telefono'], ENT_QUOTES) ?>', '<?= htmlspecialchars($cliente['direccion'] ?? '', ENT_QUOTES) ?>', '<?= htmlspecialchars($cliente['observaciones'] ?? '', ENT_QUOTES) ?>')"
                                class="bg-gray-300 hover:bg-gray-400 text-gray-700 px-3 py-2 rounded-lg text-sm"
                                title="Editar">
                            <i class="fas fa-edit"></i>
                        </button>
                        <button onclick="eliminarCliente(<?= $cliente['id'] ?>, '<?= htmlspecialchars($cliente['nombre'] . ' ' . $cliente['apellido'], ENT_QUOTES) ?>')"
                                class="bg-red-100 hover:bg-red-200 text-red-600 px-3 py-2 rounded-lg text-sm"
                                title="Eliminar">
                            <i class="fas fa-trash"></i>
                        </button>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <!-- Stats -->
        <div class="mt-6 text-center text-gray-500">
            <p>Total de clientes activos: <?= count($clientes) ?></p>
        </div>
    </main>

    <!-- Modal Agregar/Editar Cliente -->
    <div id="clienteModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 hidden">
        <div class="bg-white rounded-lg p-6 w-full max-w-md mx-4">
            <h3 id="modalTitle" class="text-xl font-bold mb-4">Nuevo Cliente Fijo</h3>

            <form id="clienteForm" method="POST">
                <input type="hidden" name="accion" id="modalAccion" value="agregar">
                <input type="hidden" name="id" id="modalId">

                <div class="space-y-4">
                    <div>
                        <label class="block text-gray-700 mb-2">Nombre <span class="text-red-500">*</span></label>
                        <input type="text" name="nombre" id="modalNombre" required
                               class="w-full px-4 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                    </div>

                    <div>
                        <label class="block text-gray-700 mb-2">Apellido <span class="text-red-500">*</span></label>
                        <input type="text" name="apellido" id="modalApellido" required
                               class="w-full px-4 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                    </div>

                    <div>
                        <label class="block text-gray-700 mb-2">Telefono <span class="text-red-500">*</span></label>
                        <input type="tel" name="telefono" id="modalTelefono" required
                               class="w-full px-4 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                    </div>

                    <div>
                        <label class="block text-gray-700 mb-2">Direccion</label>
                        <input type="text" name="direccion" id="modalDireccion"
                               class="w-full px-4 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                    </div>

                    <div>
                        <label class="block text-gray-700 mb-2">Observaciones</label>
                        <textarea name="observaciones" id="modalObservaciones" rows="3"
                                  class="w-full px-4 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
                                  placeholder="Producto habitual, preferencias, etc..."></textarea>
                    </div>
                </div>

                <div class="flex justify-end space-x-2 mt-6">
                    <button type="button" onclick="toggleModal()" class="bg-gray-500 hover:bg-gray-600 text-white px-4 py-2 rounded-lg">
                        Cancelar
                    </button>
                    <button type="submit" class="bg-blue-500 hover:bg-blue-600 text-white px-4 py-2 rounded-lg">
                        <i class="fas fa-save mr-2"></i>Guardar
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal Pedido Rapido -->
    <div id="pedidoRapidoModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 hidden">
        <div class="bg-white rounded-2xl shadow-2xl w-full max-w-lg mx-4 max-h-[90vh] overflow-hidden">
            <div class="bg-gradient-to-r from-green-500 to-emerald-500 text-white p-4">
                <div class="flex items-center justify-between">
                    <h2 class="text-xl font-bold flex items-center gap-2">
                        <i class="fas fa-bolt"></i> Pedido Rapido
                    </h2>
                    <button onclick="cerrarPedidoRapido()" class="text-white/80 hover:text-white text-2xl">&times;</button>
                </div>
                <div id="pedidoClienteInfo" class="text-green-100 text-sm mt-1"></div>
            </div>

            <form id="formPedidoRapido" class="p-4 overflow-y-auto max-h-[70vh]" onsubmit="return enviarPedidoRapido(event)">
                <input type="hidden" name="cliente_id" id="prClienteId">
                <input type="hidden" name="nombre" id="prNombre">
                <input type="hidden" name="apellido" id="prApellido">
                <input type="hidden" name="telefono" id="prTelefono">
                <input type="hidden" name="direccion" id="prDireccion">

                <!-- Productos favoritos -->
                <div id="favoritosContainer" class="mb-4 hidden">
                    <label class="block text-sm font-bold text-gray-700 mb-2">
                        <i class="fas fa-star text-yellow-500"></i> Sus favoritos
                    </label>
                    <div id="favoritosList" class="flex flex-wrap gap-2"></div>
                </div>

                <!-- Producto -->
                <div class="mb-4">
                    <label class="block text-sm font-bold text-gray-700 mb-2">Producto *</label>
                    <select name="producto" id="prProducto" required class="w-full p-3 border-2 border-gray-200 rounded-lg focus:border-green-500" onchange="actualizarPrecioRapido()">
                        <option value="">-- Seleccionar producto --</option>
                        <?php foreach ($productosPorCategoria as $categoria => $prods): ?>
                        <optgroup label="<?= htmlspecialchars($categoria) ?>">
                            <?php foreach ($prods as $p): ?>
                            <option value="<?= htmlspecialchars($p['nombre']) ?>" data-precio="<?= $p['precio'] ?>">
                                <?= htmlspecialchars($p['nombre']) ?> - $<?= number_format($p['precio'], 0, ',', '.') ?>
                            </option>
                            <?php endforeach; ?>
                        </optgroup>
                        <?php endforeach; ?>
                    </select>
                    <input type="hidden" name="precio" id="prPrecio">
                </div>

                <!-- Modalidad -->
                <div class="mb-4">
                    <label class="block text-sm font-bold text-gray-700 mb-2">Modalidad *</label>
                    <div class="grid grid-cols-2 gap-3">
                        <label class="cursor-pointer">
                            <input type="radio" name="modalidad" value="Retiro" class="hidden peer" checked>
                            <div class="p-3 border-2 border-gray-200 rounded-lg text-center peer-checked:border-green-500 peer-checked:bg-green-50">
                                <div class="text-2xl">🏪</div>
                                <div class="font-bold">Retiro</div>
                            </div>
                        </label>
                        <label class="cursor-pointer">
                            <input type="radio" name="modalidad" value="Delivery" class="hidden peer">
                            <div class="p-3 border-2 border-gray-200 rounded-lg text-center peer-checked:border-green-500 peer-checked:bg-green-50">
                                <div class="text-2xl">🛵</div>
                                <div class="font-bold">Delivery</div>
                            </div>
                        </label>
                    </div>
                </div>

                <!-- Turno -->
                <div class="mb-4">
                    <label class="block text-sm font-bold text-gray-700 mb-2">Turno *</label>
                    <div class="grid grid-cols-2 gap-2">
                        <?php foreach ($turnos as $turno): ?>
                        <label class="cursor-pointer">
                            <input type="radio" name="turno" value="<?= htmlspecialchars($turno['nombre']) ?>" class="hidden peer">
                            <div class="p-2 border-2 border-gray-200 rounded-lg text-center peer-checked:border-green-500 peer-checked:bg-green-50 text-sm">
                                <?= htmlspecialchars($turno['nombre']) ?>
                            </div>
                        </label>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- Ubicacion -->
                <div class="mb-4">
                    <label class="block text-sm font-bold text-gray-700 mb-2">Ubicacion *</label>
                    <div class="grid grid-cols-2 gap-3">
                        <label class="cursor-pointer">
                            <input type="radio" name="ubicacion" value="Local 1" class="hidden peer" checked>
                            <div class="p-2 border-2 border-gray-200 rounded-lg text-center peer-checked:border-green-500 peer-checked:bg-green-50">
                                🏪 Local 1
                            </div>
                        </label>
                        <label class="cursor-pointer">
                            <input type="radio" name="ubicacion" value="Fabrica" class="hidden peer">
                            <div class="p-2 border-2 border-gray-200 rounded-lg text-center peer-checked:border-green-500 peer-checked:bg-green-50">
                                🏭 Fabrica
                            </div>
                        </label>
                    </div>
                </div>

                <!-- Forma de pago -->
                <div class="mb-4">
                    <label class="block text-sm font-bold text-gray-700 mb-2">Forma de Pago *</label>
                    <div class="grid grid-cols-2 gap-3">
                        <label class="cursor-pointer">
                            <input type="radio" name="forma_pago" value="Efectivo" class="hidden peer" checked>
                            <div class="p-3 border-2 border-gray-200 rounded-lg text-center peer-checked:border-green-500 peer-checked:bg-green-50">
                                <div class="text-2xl">💵</div>
                                <div class="font-bold">Efectivo</div>
                            </div>
                        </label>
                        <label class="cursor-pointer">
                            <input type="radio" name="forma_pago" value="Transferencia" class="hidden peer">
                            <div class="p-3 border-2 border-gray-200 rounded-lg text-center peer-checked:border-green-500 peer-checked:bg-green-50">
                                <div class="text-2xl">💳</div>
                                <div class="font-bold">Transferencia</div>
                            </div>
                        </label>
                    </div>
                </div>

                <!-- Ya pago -->
                <div class="mb-4">
                    <label class="flex items-center gap-3 cursor-pointer bg-blue-50 p-3 rounded-lg border-2 border-blue-200">
                        <input type="checkbox" name="ya_pagado" value="1" class="w-5 h-5 text-blue-600">
                        <span class="font-bold">✅ Ya esta pagado</span>
                    </label>
                </div>

                <!-- Observaciones -->
                <div class="mb-4">
                    <label class="block text-sm font-bold text-gray-700 mb-2">Observaciones</label>
                    <textarea name="observaciones" rows="2" class="w-full p-3 border-2 border-gray-200 rounded-lg focus:border-green-500" placeholder="Notas..."></textarea>
                </div>

                <button type="submit" id="btnCrearRapido" class="w-full bg-green-500 hover:bg-green-600 text-white py-4 rounded-lg font-bold text-lg flex items-center justify-center gap-2">
                    <i class="fas fa-check"></i> CREAR PEDIDO
                </button>
            </form>
        </div>
    </div>

    <!-- Modal Historial -->
    <div id="historialModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 hidden">
        <div class="bg-white rounded-2xl shadow-2xl w-full max-w-2xl mx-4 max-h-[90vh] overflow-hidden">
            <div class="bg-gradient-to-r from-blue-500 to-indigo-500 text-white p-4">
                <div class="flex items-center justify-between">
                    <h2 class="text-xl font-bold flex items-center gap-2">
                        <i class="fas fa-history"></i> Historial de Pedidos
                    </h2>
                    <button onclick="cerrarHistorial()" class="text-white/80 hover:text-white text-2xl">&times;</button>
                </div>
                <div id="historialClienteNombre" class="text-blue-100 text-sm mt-1"></div>
            </div>
            <div class="p-4 overflow-y-auto max-h-[70vh]" id="historialContent">
                <div class="text-center py-8"><i class="fas fa-spinner fa-spin text-4xl text-blue-500"></i></div>
            </div>
        </div>
    </div>

    <script>
        function toggleModal() {
            const modal = document.getElementById('clienteModal');
            modal.classList.toggle('hidden');

            if (!modal.classList.contains('hidden')) {
                document.getElementById('clienteForm').reset();
                document.getElementById('modalTitle').textContent = 'Nuevo Cliente Fijo';
                document.getElementById('modalAccion').value = 'agregar';
            }
        }

        function editarCliente(id, nombre, apellido, telefono, direccion, observaciones) {
            document.getElementById('modalTitle').textContent = 'Editar Cliente';
            document.getElementById('modalAccion').value = 'editar';
            document.getElementById('modalId').value = id;
            document.getElementById('modalNombre').value = nombre;
            document.getElementById('modalApellido').value = apellido;
            document.getElementById('modalTelefono').value = telefono;
            document.getElementById('modalDireccion').value = direccion;
            document.getElementById('modalObservaciones').value = observaciones;
            toggleModal();
        }

        function eliminarCliente(id, nombre) {
            if (confirm(`Estas seguro de eliminar a ${nombre}?`)) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `<input type="hidden" name="accion" value="eliminar"><input type="hidden" name="id" value="${id}">`;
                document.body.appendChild(form);
                form.submit();
            }
        }

        // ========== PEDIDO RAPIDO ==========
        function pedidoRapido(id, nombre, apellido, telefono, direccion) {
            document.getElementById('prClienteId').value = id;
            document.getElementById('prNombre').value = nombre;
            document.getElementById('prApellido').value = apellido;
            document.getElementById('prTelefono').value = telefono;
            document.getElementById('prDireccion').value = direccion;
            document.getElementById('pedidoClienteInfo').textContent = `${nombre} ${apellido} - ${telefono}`;

            document.getElementById('formPedidoRapido').reset();
            document.getElementById('prClienteId').value = id;
            document.getElementById('prNombre').value = nombre;
            document.getElementById('prApellido').value = apellido;
            document.getElementById('prTelefono').value = telefono;
            document.getElementById('prDireccion').value = direccion;

            // Cargar favoritos
            cargarFavoritos(telefono);

            document.getElementById('pedidoRapidoModal').classList.remove('hidden');
        }

        function cargarFavoritos(telefono) {
            fetch(`historial.php?telefono=${encodeURIComponent(telefono)}&solo_favoritos=1`)
                .then(r => r.json())
                .then(data => {
                    const container = document.getElementById('favoritosContainer');
                    const list = document.getElementById('favoritosList');
                    if (data.favoritos && data.favoritos.length > 0) {
                        list.innerHTML = data.favoritos.map(f =>
                            `<button type="button" onclick="seleccionarFavorito('${f.producto.replace(/'/g, "\\'")}', ${f.precio})"
                                class="bg-yellow-100 hover:bg-yellow-200 border-2 border-yellow-300 px-3 py-2 rounded-lg text-sm transition-all">
                                ${f.producto} <span class="text-yellow-700 font-bold">$${parseInt(f.precio).toLocaleString()}</span>
                                <span class="text-xs text-gray-500">(${f.veces}x)</span>
                            </button>`
                        ).join('');
                        container.classList.remove('hidden');
                    } else {
                        container.classList.add('hidden');
                    }
                })
                .catch(() => {
                    document.getElementById('favoritosContainer').classList.add('hidden');
                });
        }

        function seleccionarFavorito(producto, precio) {
            const select = document.getElementById('prProducto');
            for (let i = 0; i < select.options.length; i++) {
                if (select.options[i].value === producto) {
                    select.selectedIndex = i;
                    break;
                }
            }
            document.getElementById('prPrecio').value = precio;
        }

        function actualizarPrecioRapido() {
            const select = document.getElementById('prProducto');
            const option = select.options[select.selectedIndex];
            if (option && option.dataset.precio) {
                document.getElementById('prPrecio').value = option.dataset.precio;
            }
        }

        function cerrarPedidoRapido() {
            document.getElementById('pedidoRapidoModal').classList.add('hidden');
        }

        function enviarPedidoRapido(e) {
            e.preventDefault();
            const form = document.getElementById('formPedidoRapido');
            const formData = new FormData(form);

            if (!formData.get('producto')) { alert('Selecciona un producto'); return false; }
            if (!formData.get('turno')) { alert('Selecciona un turno'); return false; }

            const btn = document.getElementById('btnCrearRapido');
            btn.disabled = true;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Creando...';

            fetch('crear_pedido_rapido.php', { method: 'POST', body: formData })
                .then(r => r.json())
                .then(data => {
                    if (data.success) {
                        alert(`Pedido #${data.pedido_id} creado!\n\n${data.producto}\n$${parseInt(data.precio).toLocaleString()}`);
                        cerrarPedidoRapido();
                        location.reload();
                    } else {
                        alert('Error: ' + (data.error || 'No se pudo crear'));
                        btn.disabled = false;
                        btn.innerHTML = '<i class="fas fa-check"></i> CREAR PEDIDO';
                    }
                })
                .catch(() => {
                    alert('Error de conexion');
                    btn.disabled = false;
                    btn.innerHTML = '<i class="fas fa-check"></i> CREAR PEDIDO';
                });
            return false;
        }

        // ========== HISTORIAL ==========
        function verHistorial(telefono, nombreCompleto) {
            document.getElementById('historialClienteNombre').textContent = nombreCompleto;
            document.getElementById('historialContent').innerHTML = '<div class="text-center py-8"><i class="fas fa-spinner fa-spin text-4xl text-blue-500"></i></div>';
            document.getElementById('historialModal').classList.remove('hidden');

            fetch(`historial.php?telefono=${encodeURIComponent(telefono)}`)
                .then(r => r.text())
                .then(html => {
                    document.getElementById('historialContent').innerHTML = html;
                });
        }

        function cerrarHistorial() {
            document.getElementById('historialModal').classList.add('hidden');
        }

        function repetirPedido(pedidoId) {
            if (!confirm('Repetir este pedido exactamente igual?')) return;

            fetch(`repetir_pedido.php?id=${pedidoId}`, { method: 'POST' })
                .then(r => r.json())
                .then(data => {
                    if (data.success) {
                        alert(`Pedido #${data.nuevo_id} creado!\n\n${data.producto}\n$${parseInt(data.precio).toLocaleString()}`);
                        cerrarHistorial();
                        location.reload();
                    } else {
                        alert('Error: ' + (data.error || 'No se pudo crear'));
                    }
                });
        }

        // Cerrar modales con click afuera o Escape
        document.querySelectorAll('#clienteModal, #pedidoRapidoModal, #historialModal').forEach(modal => {
            modal.addEventListener('click', function(e) {
                if (e.target === this) {
                    this.classList.add('hidden');
                }
            });
        });

        document.addEventListener('keydown', e => {
            if (e.key === 'Escape') {
                document.querySelectorAll('#clienteModal, #pedidoRapidoModal, #historialModal').forEach(m => m.classList.add('hidden'));
            }
        });
    </script>
</body>
</html>