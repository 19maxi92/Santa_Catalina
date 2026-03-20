<?php
require_once '../../config.php';
requireLogin();

$pdo = getConnection();

// Auto-migración: crear tabla e insertar datos si no existen
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS `bebidas` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `nombre` varchar(100) NOT NULL,
        `activo` tinyint(1) NOT NULL DEFAULT 1,
        `orden` int(11) NOT NULL DEFAULT 0,
        `created_at` timestamp NULL DEFAULT current_timestamp(),
        PRIMARY KEY (`id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    // Columnas en pedidos
    foreach (['bebidas_json TEXT DEFAULT NULL', 'bebidas_precio INT DEFAULT NULL'] as $col) {
        try { $pdo->exec("ALTER TABLE pedidos ADD COLUMN $col"); } catch (Exception $e) { /* ya existe */ }
    }

    // Seed inicial si la tabla está vacía
    $count = $pdo->query("SELECT COUNT(*) FROM bebidas")->fetchColumn();
    if ($count == 0) {
        $pdo->exec("INSERT INTO bebidas (nombre, orden) VALUES
            ('Monster Ultra',1),('Monster Azul',2),('Monster Verde',3),
            ('Monster Negro',4),('Monster Reserva Ananás',5),
            ('Coca-Cola 500ml',6),('Coca-Cola 2.25L',7),
            ('Sprite 500ml',8),('Sprite 2L',9),
            ('Fanta 500ml',10),('Fanta 2L',11),
            ('Baggio Pronto Naranja',12),('Baggio Pronto Multifrutas',13),
            ('Baggio Fresh Manzana',14),('Baggio Fresh',15)");
    }
} catch (Exception $e) { /* ignorar */ }

$msg = '';
$error = '';

// Acciones POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $accion = $_POST['accion'] ?? '';

    if ($accion === 'agregar') {
        $nombre = trim($_POST['nombre'] ?? '');
        if ($nombre) {
            $orden = (int)$pdo->query("SELECT COALESCE(MAX(orden),0)+1 FROM bebidas")->fetchColumn();
            $pdo->prepare("INSERT INTO bebidas (nombre, orden) VALUES (?,?)")->execute([$nombre, $orden]);
            $msg = "Bebida \"$nombre\" agregada.";
        }
    } elseif ($accion === 'toggle') {
        $id = (int)($_POST['id'] ?? 0);
        $pdo->prepare("UPDATE bebidas SET activo = 1 - activo WHERE id = ?")->execute([$id]);
    } elseif ($accion === 'eliminar') {
        $id = (int)($_POST['id'] ?? 0);
        $pdo->prepare("DELETE FROM bebidas WHERE id = ?")->execute([$id]);
        $msg = "Bebida eliminada.";
    } elseif ($accion === 'renombrar') {
        $id     = (int)($_POST['id'] ?? 0);
        $nombre = trim($_POST['nombre'] ?? '');
        if ($id && $nombre) {
            $pdo->prepare("UPDATE bebidas SET nombre = ? WHERE id = ?")->execute([$nombre, $id]);
            $msg = "Bebida actualizada.";
        }
    } elseif ($accion === 'reordenar') {
        $ids = json_decode($_POST['ids'] ?? '[]', true);
        foreach ($ids as $i => $id) {
            $pdo->prepare("UPDATE bebidas SET orden = ? WHERE id = ?")->execute([$i + 1, (int)$id]);
        }
        echo json_encode(['success' => true]); exit;
    }

    header('Location: index.php' . ($msg ? '?ok=' . urlencode($msg) : ''));
    exit;
}

if (isset($_GET['ok'])) $msg = $_GET['ok'];

$bebidas = $pdo->query("SELECT * FROM bebidas ORDER BY orden ASC, id ASC")->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión de Bebidas - Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="bg-gray-100 min-h-screen">

<div class="max-w-2xl mx-auto py-8 px-4">

    <!-- Header -->
    <div class="flex items-center gap-3 mb-6">
        <a href="../../index.php" class="text-gray-500 hover:text-gray-700">
            <i class="fas fa-arrow-left"></i>
        </a>
        <h1 class="text-2xl font-bold text-gray-800">
            🥤 Gestión de Bebidas
        </h1>
    </div>

    <?php if ($msg): ?>
    <div class="bg-green-100 border border-green-300 text-green-800 rounded-lg px-4 py-3 mb-4">
        <?= htmlspecialchars($msg) ?>
    </div>
    <?php endif; ?>

    <!-- Agregar bebida -->
    <div class="bg-white rounded-xl shadow p-5 mb-6">
        <h2 class="font-semibold text-gray-700 mb-3"><i class="fas fa-plus-circle text-blue-500 mr-1"></i> Agregar bebida</h2>
        <form method="POST" class="flex gap-2">
            <input type="hidden" name="accion" value="agregar">
            <input type="text" name="nombre" placeholder="Nombre de la bebida (ej: Agua Mineral 500ml)"
                   class="flex-1 border border-gray-300 rounded-lg px-4 py-2 text-sm focus:ring-2 focus:ring-blue-400" required>
            <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white px-5 py-2 rounded-lg text-sm font-semibold">
                Agregar
            </button>
        </form>
    </div>

    <!-- Lista de bebidas -->
    <div class="bg-white rounded-xl shadow p-5">
        <h2 class="font-semibold text-gray-700 mb-4">
            <i class="fas fa-list text-gray-400 mr-1"></i>
            Lista de bebidas (<?= count($bebidas) ?>)
            <span class="text-xs text-gray-400 font-normal ml-2">Arrastrá para reordenar</span>
        </h2>

        <ul id="lista-bebidas" class="space-y-2">
            <?php foreach ($bebidas as $b): ?>
            <li class="bebida-item flex items-center gap-3 p-3 rounded-lg border <?= $b['activo'] ? 'border-gray-200 bg-white' : 'border-gray-100 bg-gray-50 opacity-60' ?>"
                data-id="<?= $b['id'] ?>">
                <!-- drag handle -->
                <span class="cursor-grab text-gray-300 hover:text-gray-500 drag-handle">
                    <i class="fas fa-grip-vertical"></i>
                </span>

                <!-- nombre / edición inline -->
                <span class="flex-1 font-medium text-gray-800 text-sm nombre-display">
                    <?= htmlspecialchars($b['nombre']) ?>
                </span>
                <form class="hidden flex-1 rename-form" method="POST">
                    <input type="hidden" name="accion" value="renombrar">
                    <input type="hidden" name="id" value="<?= $b['id'] ?>">
                    <input type="text" name="nombre" value="<?= htmlspecialchars($b['nombre']) ?>"
                           class="w-full border border-blue-400 rounded px-2 py-1 text-sm focus:ring-1 focus:ring-blue-400">
                </form>

                <!-- badge activo/inactivo -->
                <span class="text-xs px-2 py-0.5 rounded-full <?= $b['activo'] ? 'bg-green-100 text-green-700' : 'bg-gray-200 text-gray-500' ?>">
                    <?= $b['activo'] ? 'Activa' : 'Inactiva' ?>
                </span>

                <!-- acciones -->
                <div class="flex gap-1 shrink-0">
                    <button onclick="editarBebida(<?= $b['id'] ?>)" type="button"
                            class="text-blue-500 hover:text-blue-700 text-sm px-2 py-1" title="Editar">
                        <i class="fas fa-pen"></i>
                    </button>
                    <form method="POST" class="inline">
                        <input type="hidden" name="accion" value="toggle">
                        <input type="hidden" name="id" value="<?= $b['id'] ?>">
                        <button type="submit" class="text-sm px-2 py-1 <?= $b['activo'] ? 'text-yellow-500 hover:text-yellow-700' : 'text-green-500 hover:text-green-700' ?>"
                                title="<?= $b['activo'] ? 'Desactivar' : 'Activar' ?>">
                            <i class="fas <?= $b['activo'] ? 'fa-eye-slash' : 'fa-eye' ?>"></i>
                        </button>
                    </form>
                    <form method="POST" class="inline" onsubmit="return confirm('¿Eliminar esta bebida?')">
                        <input type="hidden" name="accion" value="eliminar">
                        <input type="hidden" name="id" value="<?= $b['id'] ?>">
                        <button type="submit" class="text-red-400 hover:text-red-600 text-sm px-2 py-1" title="Eliminar">
                            <i class="fas fa-trash"></i>
                        </button>
                    </form>
                </div>
            </li>
            <?php endforeach; ?>
        </ul>

        <?php if (empty($bebidas)): ?>
        <p class="text-gray-400 text-center py-6 italic">No hay bebidas cargadas.</p>
        <?php endif; ?>
    </div>
</div>

<!-- Sortable.js para drag & drop -->
<script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.3/Sortable.min.js"></script>
<script>
    // Drag & drop reorder
    const lista = document.getElementById('lista-bebidas');
    if (lista) {
        Sortable.create(lista, {
            handle: '.drag-handle',
            animation: 150,
            onEnd: () => {
                const ids = Array.from(lista.querySelectorAll('.bebida-item')).map(el => el.dataset.id);
                const fd = new FormData();
                fd.append('accion', 'reordenar');
                fd.append('ids', JSON.stringify(ids));
                fetch('index.php', { method: 'POST', body: fd });
            }
        });
    }

    // Edición inline
    function editarBebida(id) {
        const li = document.querySelector(`.bebida-item[data-id="${id}"]`);
        li.querySelector('.nombre-display').classList.add('hidden');
        const form = li.querySelector('.rename-form');
        form.classList.remove('hidden');
        form.classList.add('flex');
        form.querySelector('input[type=text]').focus();
        form.onsubmit = () => true;
    }
</script>
</body>
</html>
