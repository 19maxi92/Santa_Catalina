<?php
/**
 * Módulo Clientes Fijos - Pedido Rápido
 * Lista clientes frecuentes y permite hacer pedidos rápidos
 */

require_once '../../../config/database.php';

// Obtener clientes únicos con más de 1 pedido (ordenados por cantidad de pedidos)
$stmt = $pdo->query("
    SELECT
        telefono,
        nombre,
        apellido,
        direccion,
        COUNT(*) as total_pedidos,
        MAX(created_at) as ultimo_pedido,
        SUM(precio) as total_gastado
    FROM pedidos
    WHERE telefono IS NOT NULL AND telefono != ''
    GROUP BY telefono
    HAVING COUNT(*) >= 1
    ORDER BY total_pedidos DESC, ultimo_pedido DESC
    LIMIT 100
");
$clientes = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Clientes Fijos - Pedido Rápido</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .cliente-card {
            transition: all 0.2s;
        }
        .cliente-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        }
        .badge-pedidos {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }
    </style>
</head>
<body class="bg-gray-100 min-h-screen">
    <!-- Header -->
    <div class="bg-gradient-to-r from-purple-600 to-indigo-600 text-white p-4 shadow-lg">
        <div class="max-w-6xl mx-auto flex items-center justify-between">
            <div class="flex items-center gap-4">
                <a href="../../index.php" class="bg-white/20 hover:bg-white/30 p-2 rounded-lg">
                    <i class="fas fa-arrow-left"></i>
                </a>
                <div>
                    <h1 class="text-2xl font-bold flex items-center gap-2">
                        <i class="fas fa-users"></i> Clientes Fijos
                    </h1>
                    <p class="text-purple-200 text-sm">Pedido rápido para clientes frecuentes</p>
                </div>
            </div>
        </div>
    </div>

    <div class="max-w-6xl mx-auto p-4">
        <!-- Buscador -->
        <div class="bg-white rounded-xl shadow-lg p-4 mb-6">
            <div class="flex gap-4 items-center">
                <div class="flex-1 relative">
                    <i class="fas fa-search absolute left-3 top-1/2 -translate-y-1/2 text-gray-400"></i>
                    <input type="text" id="buscador"
                           class="w-full pl-10 pr-4 py-3 border-2 border-gray-200 rounded-lg focus:border-purple-500 focus:ring-2 focus:ring-purple-200 text-lg"
                           placeholder="Buscar por teléfono o nombre..."
                           oninput="filtrarClientes(this.value)">
                </div>
                <a href="../pedidos/crear_pedido.php"
                   class="bg-green-500 hover:bg-green-600 text-white px-6 py-3 rounded-lg font-bold flex items-center gap-2">
                    <i class="fas fa-plus"></i> Nuevo Cliente
                </a>
            </div>
        </div>

        <!-- Stats -->
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
            <div class="bg-white rounded-xl p-4 shadow">
                <div class="text-3xl font-bold text-purple-600"><?= count($clientes) ?></div>
                <div class="text-gray-600 text-sm">Clientes registrados</div>
            </div>
            <div class="bg-white rounded-xl p-4 shadow">
                <div class="text-3xl font-bold text-green-600">
                    <?= count(array_filter($clientes, fn($c) => $c['total_pedidos'] >= 3)) ?>
                </div>
                <div class="text-gray-600 text-sm">Clientes frecuentes (3+)</div>
            </div>
            <div class="bg-white rounded-xl p-4 shadow">
                <div class="text-3xl font-bold text-blue-600">
                    <?= count(array_filter($clientes, fn($c) => $c['total_pedidos'] >= 5)) ?>
                </div>
                <div class="text-gray-600 text-sm">Clientes VIP (5+)</div>
            </div>
            <div class="bg-white rounded-xl p-4 shadow">
                <div class="text-3xl font-bold text-orange-600">
                    $<?= number_format(array_sum(array_column($clientes, 'total_gastado')), 0, ',', '.') ?>
                </div>
                <div class="text-gray-600 text-sm">Total facturado</div>
            </div>
        </div>

        <!-- Lista de clientes -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4" id="listaClientes">
            <?php foreach ($clientes as $cliente):
                $esVIP = $cliente['total_pedidos'] >= 5;
                $esFrecuente = $cliente['total_pedidos'] >= 3;
            ?>
            <div class="cliente-card bg-white rounded-xl shadow-lg overflow-hidden"
                 data-telefono="<?= htmlspecialchars($cliente['telefono']) ?>"
                 data-nombre="<?= htmlspecialchars(strtolower($cliente['nombre'] . ' ' . $cliente['apellido'])) ?>">

                <!-- Header del cliente -->
                <div class="p-4 <?= $esVIP ? 'bg-gradient-to-r from-yellow-400 to-orange-400' : ($esFrecuente ? 'bg-gradient-to-r from-purple-500 to-indigo-500' : 'bg-gray-100') ?> <?= ($esVIP || $esFrecuente) ? 'text-white' : '' ?>">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center gap-3">
                            <div class="w-12 h-12 rounded-full bg-white/20 flex items-center justify-center text-xl font-bold <?= (!$esVIP && !$esFrecuente) ? 'bg-purple-100 text-purple-600' : '' ?>">
                                <?= strtoupper(substr($cliente['nombre'], 0, 1)) ?>
                            </div>
                            <div>
                                <div class="font-bold text-lg">
                                    <?= htmlspecialchars($cliente['nombre'] . ' ' . $cliente['apellido']) ?>
                                </div>
                                <div class="text-sm <?= ($esVIP || $esFrecuente) ? 'text-white/80' : 'text-gray-600' ?>">
                                    <i class="fas fa-phone"></i> <?= htmlspecialchars($cliente['telefono']) ?>
                                </div>
                            </div>
                        </div>
                        <div class="badge-pedidos text-white px-3 py-1 rounded-full text-sm font-bold">
                            <?= $cliente['total_pedidos'] ?> pedidos
                        </div>
                    </div>
                </div>

                <!-- Info del cliente -->
                <div class="p-4 border-b">
                    <?php if ($cliente['direccion']): ?>
                    <div class="text-sm text-gray-600 mb-2">
                        <i class="fas fa-map-marker-alt text-red-400 mr-1"></i>
                        <?= htmlspecialchars($cliente['direccion']) ?>
                    </div>
                    <?php endif; ?>
                    <div class="flex justify-between text-sm">
                        <span class="text-gray-500">
                            <i class="fas fa-clock"></i> Último: <?= date('d/m/Y', strtotime($cliente['ultimo_pedido'])) ?>
                        </span>
                        <span class="text-green-600 font-semibold">
                            Total: $<?= number_format($cliente['total_gastado'], 0, ',', '.') ?>
                        </span>
                    </div>
                </div>

                <!-- Acciones -->
                <div class="p-4 bg-gray-50 flex gap-2">
                    <button onclick="pedidoRapido('<?= htmlspecialchars($cliente['telefono']) ?>')"
                            class="flex-1 bg-green-500 hover:bg-green-600 text-white py-3 rounded-lg font-bold flex items-center justify-center gap-2">
                        <i class="fas fa-bolt"></i> Pedido Rápido
                    </button>
                    <button onclick="verHistorial('<?= htmlspecialchars($cliente['telefono']) ?>')"
                            class="bg-blue-500 hover:bg-blue-600 text-white px-4 py-3 rounded-lg"
                            title="Ver historial">
                        <i class="fas fa-history"></i>
                    </button>
                </div>
            </div>
            <?php endforeach; ?>
        </div>

        <?php if (empty($clientes)): ?>
        <div class="bg-white rounded-xl p-8 text-center">
            <i class="fas fa-users text-gray-300 text-6xl mb-4"></i>
            <p class="text-gray-500 text-lg">No hay clientes registrados aún</p>
            <a href="../pedidos/crear_pedido.php" class="inline-block mt-4 bg-purple-500 text-white px-6 py-2 rounded-lg">
                Crear primer pedido
            </a>
        </div>
        <?php endif; ?>
    </div>

    <!-- Modal Pedido Rápido -->
    <div id="modalPedidoRapido" class="fixed inset-0 bg-black/50 hidden z-50 flex items-center justify-center p-4">
        <div class="bg-white rounded-2xl shadow-2xl w-full max-w-lg max-h-[90vh] overflow-hidden">
            <div class="bg-gradient-to-r from-green-500 to-emerald-500 text-white p-4">
                <div class="flex items-center justify-between">
                    <h2 class="text-xl font-bold flex items-center gap-2">
                        <i class="fas fa-bolt"></i> Pedido Rápido
                    </h2>
                    <button onclick="cerrarModal()" class="text-white/80 hover:text-white text-2xl">&times;</button>
                </div>
                <div id="clienteInfo" class="text-green-100 text-sm mt-1"></div>
            </div>

            <div class="p-4 overflow-y-auto max-h-[60vh]" id="modalContent">
                <!-- Se llena dinámicamente -->
            </div>
        </div>
    </div>

    <!-- Modal Historial -->
    <div id="modalHistorial" class="fixed inset-0 bg-black/50 hidden z-50 flex items-center justify-center p-4">
        <div class="bg-white rounded-2xl shadow-2xl w-full max-w-2xl max-h-[90vh] overflow-hidden">
            <div class="bg-gradient-to-r from-blue-500 to-indigo-500 text-white p-4">
                <div class="flex items-center justify-between">
                    <h2 class="text-xl font-bold flex items-center gap-2">
                        <i class="fas fa-history"></i> Historial de Pedidos
                    </h2>
                    <button onclick="cerrarHistorial()" class="text-white/80 hover:text-white text-2xl">&times;</button>
                </div>
                <div id="historialClienteInfo" class="text-blue-100 text-sm mt-1"></div>
            </div>

            <div class="p-4 overflow-y-auto max-h-[70vh]" id="historialContent">
                <!-- Se llena dinámicamente -->
            </div>
        </div>
    </div>

    <script>
    function filtrarClientes(texto) {
        texto = texto.toLowerCase();
        document.querySelectorAll('.cliente-card').forEach(card => {
            const telefono = card.dataset.telefono.toLowerCase();
            const nombre = card.dataset.nombre;
            if (telefono.includes(texto) || nombre.includes(texto)) {
                card.style.display = '';
            } else {
                card.style.display = 'none';
            }
        });
    }

    function pedidoRapido(telefono) {
        document.getElementById('modalPedidoRapido').classList.remove('hidden');
        document.getElementById('modalContent').innerHTML = '<div class="text-center py-8"><i class="fas fa-spinner fa-spin text-4xl text-green-500"></i></div>';

        fetch(`pedido_rapido.php?telefono=${encodeURIComponent(telefono)}`)
            .then(r => r.text())
            .then(html => {
                document.getElementById('modalContent').innerHTML = html;
            })
            .catch(err => {
                document.getElementById('modalContent').innerHTML = '<div class="text-red-500 text-center">Error al cargar</div>';
            });
    }

    function verHistorial(telefono) {
        document.getElementById('modalHistorial').classList.remove('hidden');
        document.getElementById('historialContent').innerHTML = '<div class="text-center py-8"><i class="fas fa-spinner fa-spin text-4xl text-blue-500"></i></div>';

        fetch(`historial.php?telefono=${encodeURIComponent(telefono)}`)
            .then(r => r.text())
            .then(html => {
                document.getElementById('historialContent').innerHTML = html;
            })
            .catch(err => {
                document.getElementById('historialContent').innerHTML = '<div class="text-red-500 text-center">Error al cargar</div>';
            });
    }

    function cerrarModal() {
        document.getElementById('modalPedidoRapido').classList.add('hidden');
    }

    function cerrarHistorial() {
        document.getElementById('modalHistorial').classList.add('hidden');
    }

    function repetirPedido(pedidoId) {
        if (!confirm('¿Repetir este pedido exactamente igual?')) return;

        fetch(`repetir_pedido.php?id=${pedidoId}`, { method: 'POST' })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    alert(`✅ Pedido #${data.nuevo_id} creado!\n\n${data.producto}\n$${data.precio.toLocaleString()}`);
                    cerrarModal();
                    cerrarHistorial();
                    location.reload();
                } else {
                    alert('❌ Error: ' + (data.error || 'No se pudo crear'));
                }
            });
    }

    // Cerrar modales con Escape
    document.addEventListener('keydown', e => {
        if (e.key === 'Escape') {
            cerrarModal();
            cerrarHistorial();
        }
    });
    </script>
</body>
</html>
