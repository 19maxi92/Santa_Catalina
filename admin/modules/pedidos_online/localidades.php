<?php
/**
 * CRUD de localidades habilitadas para Delivery
 */
require_once '../../config.php';
requireLogin();

$pdo = getConnection();

// Migración
try {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS localidades_delivery (
            id INT AUTO_INCREMENT PRIMARY KEY,
            nombre VARCHAR(100) NOT NULL,
            activo TINYINT(1) NOT NULL DEFAULT 1,
            orden INT NOT NULL DEFAULT 0,
            UNIQUE KEY uk_nombre (nombre)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    $cnt = (int)$pdo->query("SELECT COUNT(*) FROM localidades_delivery")->fetchColumn();
    if ($cnt === 0) {
        $pdo->exec("INSERT INTO localidades_delivery (nombre, activo, orden) VALUES
            ('Juan María Gutiérrez', 1, 1),
            ('City Bell', 1, 2),
            ('Villa Elisa', 1, 3),
            ('Gonnet', 1, 4),
            ('Ringuelet', 1, 5)");
    }
} catch (PDOException $e) {}

$mensaje = null;
$error   = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion'])) {

    if ($_POST['accion'] === 'agregar') {
        $nombre = trim($_POST['nombre'] ?? '');
        if ($nombre === '') {
            $error = 'El nombre no puede estar vacío';
        } else {
            try {
                $maxOrden = (int)$pdo->query("SELECT COALESCE(MAX(orden),0) FROM localidades_delivery")->fetchColumn();
                $pdo->prepare("INSERT INTO localidades_delivery (nombre, activo, orden) VALUES (?, 1, ?)")
                    ->execute([$nombre, $maxOrden + 1]);
                $mensaje = "Localidad \"{$nombre}\" agregada";
            } catch (PDOException $e) {
                $error = 'Ya existe una localidad con ese nombre';
            }
        }
    }

    if ($_POST['accion'] === 'editar') {
        $id     = (int)$_POST['id'];
        $nombre = trim($_POST['nombre'] ?? '');
        if ($nombre === '') { $error = 'El nombre no puede estar vacío'; }
        else {
            $pdo->prepare("UPDATE localidades_delivery SET nombre = ? WHERE id = ?")
                ->execute([$nombre, $id]);
            $mensaje = "Localidad actualizada";
        }
    }

    if ($_POST['accion'] === 'toggle') {
        $id = (int)$_POST['id'];
        $pdo->prepare("UPDATE localidades_delivery SET activo = 1 - activo WHERE id = ?")
            ->execute([$id]);
        $mensaje = "Estado actualizado";
    }

    if ($_POST['accion'] === 'eliminar') {
        $id = (int)$_POST['id'];
        $pdo->prepare("DELETE FROM localidades_delivery WHERE id = ?")
            ->execute([$id]);
        $mensaje = "Localidad eliminada";
    }
}

$localidades = $pdo->query("SELECT * FROM localidades_delivery ORDER BY orden, nombre")->fetchAll();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Localidades Delivery - <?= APP_NAME ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="bg-gray-100">

<header class="bg-white shadow-md">
    <div class="container mx-auto px-4 py-4 flex items-center gap-4">
        <a href="configuracion.php" class="text-gray-600 hover:text-gray-800">
            <i class="fas fa-arrow-left text-xl"></i>
        </a>
        <h1 class="text-2xl font-bold text-gray-800">
            <i class="fas fa-map-marker-alt text-blue-600 mr-2"></i>Localidades Delivery
        </h1>
    </div>
</header>

<main class="container mx-auto px-4 py-8 max-w-2xl">

    <?php if ($mensaje): ?>
        <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-6 rounded-lg">
            <i class="fas fa-check-circle mr-2"></i><?= htmlspecialchars($mensaje) ?>
        </div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-6 rounded-lg">
            <i class="fas fa-exclamation-circle mr-2"></i><?= htmlspecialchars($error) ?>
        </div>
    <?php endif; ?>

    <!-- Agregar nueva localidad -->
    <div class="bg-white rounded-xl shadow-lg p-6 mb-6">
        <h2 class="font-bold text-gray-900 mb-4"><i class="fas fa-plus text-blue-500 mr-2"></i>Agregar localidad</h2>
        <form method="POST" class="flex gap-3">
            <input type="hidden" name="accion" value="agregar">
            <input type="text" name="nombre" placeholder="Ej: Villa Elisa"
                   class="flex-1 border-2 border-gray-300 rounded-xl px-4 py-2 focus:border-blue-500" required>
            <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white px-5 py-2 rounded-xl font-bold">
                Agregar
            </button>
        </form>
    </div>

    <!-- Lista de localidades -->
    <div class="bg-white rounded-xl shadow-lg overflow-hidden">
        <div class="bg-gradient-to-r from-blue-500 to-indigo-600 text-white px-6 py-4">
            <h2 class="font-bold text-lg">Localidades configuradas (<?= count($localidades) ?>)</h2>
            <p class="text-sm opacity-80">Solo localidades activas aparecen como válidas en el formulario online</p>
        </div>
        <div class="divide-y divide-gray-100">
            <?php if (empty($localidades)): ?>
                <div class="p-8 text-center text-gray-400">No hay localidades configuradas</div>
            <?php endif; ?>
            <?php foreach ($localidades as $loc): ?>
                <div class="flex items-center gap-3 px-5 py-3" id="row-<?= $loc['id'] ?>">
                    <div class="flex-1">
                        <span id="text-<?= $loc['id'] ?>" class="font-semibold text-gray-900 <?= !$loc['activo'] ? 'line-through text-gray-400' : '' ?>">
                            <?= htmlspecialchars($loc['nombre']) ?>
                        </span>
                        <input type="text" id="input-<?= $loc['id'] ?>" value="<?= htmlspecialchars($loc['nombre']) ?>"
                               class="hidden border-2 border-blue-400 rounded-lg px-2 py-1 text-sm font-semibold">
                    </div>
                    <span class="text-xs px-2 py-1 rounded-full font-bold <?= $loc['activo'] ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-400' ?>">
                        <?= $loc['activo'] ? 'Activa' : 'Inactiva' ?>
                    </span>

                    <!-- Botón editar -->
                    <button onclick="editarLoc(<?= $loc['id'] ?>)" id="btn-edit-<?= $loc['id'] ?>"
                            class="text-blue-500 hover:text-blue-700 text-sm px-2 py-1 rounded hover:bg-blue-50" title="Editar">
                        <i class="fas fa-pencil-alt"></i>
                    </button>

                    <!-- Formulario guardar edición -->
                    <form method="POST" id="form-edit-<?= $loc['id'] ?>" class="hidden">
                        <input type="hidden" name="accion" value="editar">
                        <input type="hidden" name="id" value="<?= $loc['id'] ?>">
                        <input type="hidden" name="nombre" id="hidden-nombre-<?= $loc['id'] ?>">
                        <button type="submit" class="text-green-600 hover:text-green-800 text-sm px-2 py-1 rounded hover:bg-green-50" title="Guardar">
                            <i class="fas fa-check"></i>
                        </button>
                    </form>

                    <!-- Toggle activo -->
                    <form method="POST" class="inline">
                        <input type="hidden" name="accion" value="toggle">
                        <input type="hidden" name="id" value="<?= $loc['id'] ?>">
                        <button type="submit" class="text-<?= $loc['activo'] ? 'yellow' : 'green' ?>-500 hover:text-<?= $loc['activo'] ? 'yellow' : 'green' ?>-700 text-sm px-2 py-1 rounded"
                                title="<?= $loc['activo'] ? 'Desactivar' : 'Activar' ?>">
                            <i class="fas fa-<?= $loc['activo'] ? 'eye-slash' : 'eye' ?>"></i>
                        </button>
                    </form>

                    <!-- Eliminar -->
                    <form method="POST" class="inline" onsubmit="return confirm('¿Eliminar esta localidad?')">
                        <input type="hidden" name="accion" value="eliminar">
                        <input type="hidden" name="id" value="<?= $loc['id'] ?>">
                        <button type="submit" class="text-red-400 hover:text-red-600 text-sm px-2 py-1 rounded hover:bg-red-50" title="Eliminar">
                            <i class="fas fa-trash"></i>
                        </button>
                    </form>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <div class="mt-6 bg-yellow-50 border-l-4 border-yellow-400 p-4 rounded-lg text-sm text-yellow-800">
        <i class="fas fa-info-circle mr-2"></i>
        Cuando un cliente ingresa una localidad de Delivery no incluida en esta lista, el sistema le mostrará un aviso y no podrá continuar con el pedido.
    </div>

</main>

<script>
function editarLoc(id) {
    document.getElementById('text-' + id).classList.add('hidden');
    const input = document.getElementById('input-' + id);
    input.classList.remove('hidden');
    input.focus();
    input.select();

    const btnEdit = document.getElementById('btn-edit-' + id);
    btnEdit.classList.add('hidden');

    const formEdit = document.getElementById('form-edit-' + id);
    formEdit.classList.remove('hidden');

    // Cuando el form se envía, copiar el valor del input al hidden
    formEdit.addEventListener('submit', () => {
        document.getElementById('hidden-nombre-' + id).value = input.value.trim();
    });
}
</script>
</body>
</html>
