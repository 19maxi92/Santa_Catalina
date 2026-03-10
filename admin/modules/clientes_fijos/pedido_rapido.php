<?php
/**
 * Formulario de Pedido Rápido para Cliente Fijo
 * Solo muestra las opciones mínimas necesarias
 */

require_once '../../../config/database.php';

$telefono = $_GET['telefono'] ?? '';
if (!$telefono) {
    echo '<div class="text-red-500">Teléfono no especificado</div>';
    exit;
}

// Obtener datos del cliente
$stmt = $pdo->prepare("
    SELECT nombre, apellido, telefono, direccion, modalidad, forma_pago
    FROM pedidos
    WHERE telefono = ?
    ORDER BY created_at DESC
    LIMIT 1
");
$stmt->execute([$telefono]);
$cliente = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$cliente) {
    echo '<div class="text-red-500">Cliente no encontrado</div>';
    exit;
}

// Obtener productos más pedidos por este cliente
$stmt = $pdo->prepare("
    SELECT producto, precio, COUNT(*) as veces
    FROM pedidos
    WHERE telefono = ?
    GROUP BY producto, precio
    ORDER BY veces DESC
    LIMIT 5
");
$stmt->execute([$telefono]);
$productosFrecuentes = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Obtener turnos disponibles
$stmt = $pdo->query("SELECT * FROM turnos WHERE activo = 1 ORDER BY hora_inicio");
$turnos = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Obtener productos del menú
$stmt = $pdo->query("SELECT * FROM productos WHERE activo = 1 ORDER BY categoria, nombre");
$productos = $stmt->fetchAll(PDO::FETCH_ASSOC);
$productosPorCategoria = [];
foreach ($productos as $p) {
    $productosPorCategoria[$p['categoria']][] = $p;
}
?>

<!-- Datos del cliente (solo lectura) -->
<div class="bg-gray-50 rounded-lg p-3 mb-4">
    <div class="font-bold text-lg"><?= htmlspecialchars($cliente['nombre'] . ' ' . $cliente['apellido']) ?></div>
    <div class="text-sm text-gray-600">
        <i class="fas fa-phone text-green-500"></i> <?= htmlspecialchars($cliente['telefono']) ?>
    </div>
    <?php if ($cliente['direccion']): ?>
    <div class="text-sm text-gray-600">
        <i class="fas fa-map-marker-alt text-red-500"></i> <?= htmlspecialchars($cliente['direccion']) ?>
    </div>
    <?php endif; ?>
</div>

<!-- Productos frecuentes del cliente -->
<?php if (!empty($productosFrecuentes)): ?>
<div class="mb-4">
    <label class="block text-sm font-bold text-gray-700 mb-2">
        <i class="fas fa-star text-yellow-500"></i> Sus favoritos (click para seleccionar)
    </label>
    <div class="flex flex-wrap gap-2">
        <?php foreach ($productosFrecuentes as $pf): ?>
        <button type="button"
                onclick="seleccionarProductoFavorito('<?= htmlspecialchars($pf['producto'], ENT_QUOTES) ?>', <?= $pf['precio'] ?>)"
                class="favorito-btn bg-yellow-100 hover:bg-yellow-200 border-2 border-yellow-300 px-3 py-2 rounded-lg text-sm transition-all">
            <?= htmlspecialchars($pf['producto']) ?>
            <span class="text-yellow-700 font-bold">$<?= number_format($pf['precio'], 0, ',', '.') ?></span>
            <span class="text-xs text-gray-500">(<?= $pf['veces'] ?>x)</span>
        </button>
        <?php endforeach; ?>
    </div>
</div>
<?php endif; ?>

<form id="formPedidoRapido" onsubmit="return enviarPedidoRapido(event)">
    <input type="hidden" name="telefono" value="<?= htmlspecialchars($telefono) ?>">
    <input type="hidden" name="nombre" value="<?= htmlspecialchars($cliente['nombre']) ?>">
    <input type="hidden" name="apellido" value="<?= htmlspecialchars($cliente['apellido']) ?>">
    <input type="hidden" name="direccion" value="<?= htmlspecialchars($cliente['direccion'] ?? '') ?>">

    <!-- Producto (si no elige favorito) -->
    <div class="mb-4">
        <label class="block text-sm font-bold text-gray-700 mb-2">Producto *</label>
        <select name="producto" id="selectProducto" required
                class="w-full p-3 border-2 border-gray-200 rounded-lg focus:border-green-500"
                onchange="actualizarPrecio()">
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
        <input type="hidden" name="precio" id="inputPrecio" value="">
    </div>

    <!-- Modalidad -->
    <div class="mb-4">
        <label class="block text-sm font-bold text-gray-700 mb-2">Modalidad *</label>
        <div class="grid grid-cols-2 gap-3">
            <label class="cursor-pointer">
                <input type="radio" name="modalidad" value="Retiro" class="hidden peer" <?= $cliente['modalidad'] === 'Retiro' ? 'checked' : '' ?>>
                <div class="p-3 border-2 border-gray-200 rounded-lg text-center peer-checked:border-green-500 peer-checked:bg-green-50 transition-all">
                    <div class="text-2xl">🏪</div>
                    <div class="font-bold">Retiro</div>
                </div>
            </label>
            <label class="cursor-pointer">
                <input type="radio" name="modalidad" value="Delivery" class="hidden peer" <?= $cliente['modalidad'] === 'Delivery' ? 'checked' : '' ?>>
                <div class="p-3 border-2 border-gray-200 rounded-lg text-center peer-checked:border-green-500 peer-checked:bg-green-50 transition-all">
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
                <div class="p-2 border-2 border-gray-200 rounded-lg text-center peer-checked:border-green-500 peer-checked:bg-green-50 text-sm transition-all">
                    <?= htmlspecialchars($turno['nombre']) ?>
                </div>
            </label>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- Ubicación -->
    <div class="mb-4">
        <label class="block text-sm font-bold text-gray-700 mb-2">Ubicación *</label>
        <div class="grid grid-cols-2 gap-3">
            <label class="cursor-pointer">
                <input type="radio" name="ubicacion" value="Local 1" class="hidden peer" checked>
                <div class="p-2 border-2 border-gray-200 rounded-lg text-center peer-checked:border-green-500 peer-checked:bg-green-50 transition-all">
                    🏪 Local 1
                </div>
            </label>
            <label class="cursor-pointer">
                <input type="radio" name="ubicacion" value="Fábrica" class="hidden peer">
                <div class="p-2 border-2 border-gray-200 rounded-lg text-center peer-checked:border-green-500 peer-checked:bg-green-50 transition-all">
                    🏭 Fábrica
                </div>
            </label>
        </div>
    </div>

    <!-- Forma de pago -->
    <div class="mb-4">
        <label class="block text-sm font-bold text-gray-700 mb-2">Forma de Pago *</label>
        <div class="grid grid-cols-2 gap-3">
            <label class="cursor-pointer">
                <input type="radio" name="forma_pago" value="Efectivo" class="hidden peer" <?= $cliente['forma_pago'] === 'Efectivo' ? 'checked' : '' ?>>
                <div class="p-3 border-2 border-gray-200 rounded-lg text-center peer-checked:border-green-500 peer-checked:bg-green-50 transition-all">
                    <div class="text-2xl">💵</div>
                    <div class="font-bold">Efectivo</div>
                </div>
            </label>
            <label class="cursor-pointer">
                <input type="radio" name="forma_pago" value="Transferencia" class="hidden peer" <?= $cliente['forma_pago'] === 'Transferencia' ? 'checked' : '' ?>>
                <div class="p-3 border-2 border-gray-200 rounded-lg text-center peer-checked:border-green-500 peer-checked:bg-green-50 transition-all">
                    <div class="text-2xl">💳</div>
                    <div class="font-bold">Transferencia</div>
                </div>
            </label>
        </div>
    </div>

    <!-- Ya pagó -->
    <div class="mb-4">
        <label class="flex items-center gap-3 cursor-pointer bg-blue-50 p-3 rounded-lg border-2 border-blue-200">
            <input type="checkbox" name="ya_pagado" value="1" class="w-5 h-5 text-blue-600">
            <span class="font-bold">✅ Ya está pagado</span>
        </label>
    </div>

    <!-- Observaciones -->
    <div class="mb-4">
        <label class="block text-sm font-bold text-gray-700 mb-2">Observaciones (opcional)</label>
        <textarea name="observaciones" rows="2"
                  class="w-full p-3 border-2 border-gray-200 rounded-lg focus:border-green-500"
                  placeholder="Notas adicionales..."></textarea>
    </div>

    <!-- Botón crear -->
    <button type="submit" id="btnCrear"
            class="w-full bg-green-500 hover:bg-green-600 text-white py-4 rounded-lg font-bold text-lg flex items-center justify-center gap-2">
        <i class="fas fa-check"></i> CREAR PEDIDO
    </button>
</form>

<script>
function seleccionarProductoFavorito(nombre, precio) {
    // Buscar en el select
    const select = document.getElementById('selectProducto');
    for (let i = 0; i < select.options.length; i++) {
        if (select.options[i].value === nombre) {
            select.selectedIndex = i;
            break;
        }
    }
    document.getElementById('inputPrecio').value = precio;

    // Marcar visualmente
    document.querySelectorAll('.favorito-btn').forEach(btn => {
        btn.classList.remove('ring-2', 'ring-green-500', 'bg-green-100');
    });
    event.target.closest('.favorito-btn').classList.add('ring-2', 'ring-green-500', 'bg-green-100');
}

function actualizarPrecio() {
    const select = document.getElementById('selectProducto');
    const option = select.options[select.selectedIndex];
    if (option && option.dataset.precio) {
        document.getElementById('inputPrecio').value = option.dataset.precio;
    }
}

function enviarPedidoRapido(e) {
    e.preventDefault();

    const form = document.getElementById('formPedidoRapido');
    const formData = new FormData(form);

    // Validaciones
    if (!formData.get('producto')) {
        alert('Seleccioná un producto');
        return false;
    }
    if (!formData.get('turno')) {
        alert('Seleccioná un turno');
        return false;
    }
    if (!formData.get('modalidad')) {
        alert('Seleccioná modalidad');
        return false;
    }
    if (!formData.get('forma_pago')) {
        alert('Seleccioná forma de pago');
        return false;
    }

    const btn = document.getElementById('btnCrear');
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Creando...';

    fetch('crear_pedido_rapido.php', {
        method: 'POST',
        body: formData
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            alert(`✅ Pedido #${data.pedido_id} creado!\n\n${data.producto}\n$${parseInt(data.precio).toLocaleString()}`);
            cerrarModal();
            location.reload();
        } else {
            alert('❌ Error: ' + (data.error || 'No se pudo crear'));
            btn.disabled = false;
            btn.innerHTML = '<i class="fas fa-check"></i> CREAR PEDIDO';
        }
    })
    .catch(err => {
        alert('❌ Error de conexión');
        btn.disabled = false;
        btn.innerHTML = '<i class="fas fa-check"></i> CREAR PEDIDO';
    });

    return false;
}
</script>
