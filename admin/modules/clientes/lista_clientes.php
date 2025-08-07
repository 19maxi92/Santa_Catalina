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
$sql = "SELECT * FROM clientes_fijos WHERE activo = 1";
$params = [];

if ($buscar) {
    $sql .= " AND (nombre LIKE ? OR apellido LIKE ? OR telefono LIKE ?)";
    $buscarParam = "%$buscar%";
    $params = [$buscarParam, $buscarParam, $buscarParam];
}

$sql .= " ORDER BY nombre, apellido";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$clientes = $stmt->fetchAll();
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
        <div class="bg-white rounded-lg shadow overflow-hidden">
            <?php if (empty($clientes)): ?>
                <div class="p-8 text-center text-gray-500">
                    <i class="fas fa-users text-6xl mb-4"></i>
                    <h3 class="text-xl mb-2">No hay clientes registrados</h3>
                    <p>Agregá tu primer cliente fijo para empezar</p>
                </div>
            <?php else: ?>
                <div class="overflow-x-auto">
                    <table class="min-w-full">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Cliente</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Teléfono</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Dirección</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Observaciones</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Acciones</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200">
                            <?php foreach ($clientes as $cliente): ?>
                                <tr class="hover:bg-gray-50">
                                    <td class="px-6 py-4">
                                        <div class="text-sm font-medium text-gray-900">
                                            <?= htmlspecialchars($cliente['nombre'] . ' ' . $cliente['apellido']) ?>
                                        </div>
                                        <div class="text-sm text-gray-500">
                                            Cliente desde: <?= date('d/m/Y', strtotime($cliente['created_at'])) ?>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 text-sm text-gray-900">
                                        <a href="tel:<?= $cliente['telefono'] ?>" class="text-blue-600 hover:text-blue-800">
                                            <i class="fas fa-phone mr-1"></i><?= htmlspecialchars($cliente['telefono']) ?>
                                        </a>
                                    </td>
                                    <td class="px-6 py-4 text-sm text-gray-900">
                                        <?= htmlspecialchars($cliente['direccion'] ?: 'Sin dirección') ?>
                                    </td>
                                    <td class="px-6 py-4 text-sm text-gray-500">
                                        <?= htmlspecialchars($cliente['observaciones'] ?: '-') ?>
                                    </td>
                                    <td class="px-6 py-4 text-sm font-medium space-x-2">
                                        <a href="../pedidos/crear_pedido.php?cliente_id=<?= $cliente['id'] ?>" 
                                           class="text-green-600 hover:text-green-900">
                                            <i class="fas fa-plus-circle"></i> Pedido
                                        </a>
                                        <button onclick="editarCliente(<?= $cliente['id'] ?>, '<?= htmlspecialchars($cliente['nombre']) ?>', '<?= htmlspecialchars($cliente['apellido']) ?>', '<?= htmlspecialchars($cliente['telefono']) ?>', '<?= htmlspecialchars($cliente['direccion']) ?>', '<?= htmlspecialchars($cliente['observaciones']) ?>')"
                                                class="text-blue-600 hover:text-blue-900">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <button onclick="eliminarCliente(<?= $cliente['id'] ?>, '<?= htmlspecialchars($cliente['nombre'] . ' ' . $cliente['apellido']) ?>')"
                                                class="text-red-600 hover:text-red-900">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
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
                        <label class="block text-gray-700 mb-2">Teléfono <span class="text-red-500">*</span></label>
                        <input type="tel" name="telefono" id="modalTelefono" required
                               class="w-full px-4 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                    </div>
                    
                    <div>
                        <label class="block text-gray-700 mb-2">Dirección</label>
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

    <script>
        function toggleModal() {
            const modal = document.getElementById('clienteModal');
            modal.classList.toggle('hidden');
            
            if (!modal.classList.contains('hidden')) {
                // Reset form
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
            if (confirm(`¿Estás seguro de eliminar a ${nombre}?`)) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="accion" value="eliminar">
                    <input type="hidden" name="id" value="${id}">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        }

        // Cerrar modal al hacer clic fuera
        document.getElementById('clienteModal').addEventListener('click', function(e) {
            if (e.target === this) {
                toggleModal();
            }
        });
    </script>
</body>
</html>