<?php
/**
 * api-impresion/estaciones.php
 * Panel chico para admin: crear/ver estaciones de impresión y sus tokens.
 * No es parte de admin/ a propósito (carpeta separada, sin mezclar).
 */

require_once __DIR__ . '/../admin/config.php';
requireLogin(); // Solo admin puede crear estaciones/tokens

$pdo = getConnection();
$mensaje = '';
$token_generado = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion'])) {
    if ($_POST['accion'] === 'crear') {
        $nombre = trim($_POST['nombre'] ?? '');
        $ubicacion = trim($_POST['ubicacion'] ?? '');
        if ($nombre && $ubicacion) {
            $token_generado = bin2hex(random_bytes(24)); // 48 caracteres
            $stmt = $pdo->prepare("INSERT INTO estaciones_impresion (nombre, ubicacion, token) VALUES (?, ?, ?)");
            $stmt->execute([$nombre, $ubicacion, $token_generado]);
            $mensaje = "✅ Estación creada. Copiá el token de abajo y pegalo en la app de esa PC.";
        } else {
            $mensaje = "⚠️ Completá nombre y ubicación.";
        }
    } elseif ($_POST['accion'] === 'desactivar') {
        $id = (int)($_POST['id'] ?? 0);
        $pdo->prepare("UPDATE estaciones_impresion SET activa = 0 WHERE id = ?")->execute([$id]);
        $mensaje = "Estación desactivada.";
    } elseif ($_POST['accion'] === 'activar') {
        $id = (int)($_POST['id'] ?? 0);
        $pdo->prepare("UPDATE estaciones_impresion SET activa = 1 WHERE id = ?")->execute([$id]);
        $mensaje = "Estación reactivada.";
    }
}

$estaciones = $pdo->query("SELECT * FROM estaciones_impresion ORDER BY id DESC")->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Estaciones de Impresión</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 min-h-screen p-6">
    <div class="max-w-3xl mx-auto">
        <h1 class="text-2xl font-bold mb-6">🖨️ Estaciones de Impresión</h1>

        <?php if ($mensaje): ?>
            <div class="bg-blue-100 border border-blue-300 text-blue-800 px-4 py-3 rounded-lg mb-4"><?= htmlspecialchars($mensaje) ?></div>
        <?php endif; ?>

        <?php if ($token_generado): ?>
            <div class="bg-yellow-50 border-2 border-yellow-400 rounded-lg p-4 mb-6">
                <p class="font-semibold mb-2">Token (se muestra una sola vez, guardalo):</p>
                <code class="block bg-white p-3 rounded border text-sm break-all"><?= htmlspecialchars($token_generado) ?></code>
            </div>
        <?php endif; ?>

        <div class="bg-white rounded-lg shadow p-5 mb-6">
            <h2 class="font-semibold mb-3">Nueva estación</h2>
            <form method="POST" class="flex flex-wrap gap-3 items-end">
                <input type="hidden" name="accion" value="crear">
                <div>
                    <label class="block text-sm text-gray-600 mb-1">Nombre (para identificarla)</label>
                    <input type="text" name="nombre" placeholder="PC Mostrador Local 1" class="border rounded px-3 py-2" required>
                </div>
                <div>
                    <label class="block text-sm text-gray-600 mb-1">Ubicación que imprime</label>
                    <select name="ubicacion" class="border rounded px-3 py-2" required>
                        <option value="Local 1">Local 1</option>
                        <option value="Fábrica">Fábrica</option>
                        <option value="Villa Elisa">Villa Elisa</option>
                    </select>
                </div>
                <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded font-semibold">
                    Crear
                </button>
            </form>
        </div>

        <div class="bg-white rounded-lg shadow p-5">
            <h2 class="font-semibold mb-3">Estaciones existentes</h2>
            <?php if (empty($estaciones)): ?>
                <p class="text-gray-500 text-sm">Todavía no hay ninguna.</p>
            <?php else: ?>
                <div class="space-y-2">
                    <?php foreach ($estaciones as $e): ?>
                        <div class="flex items-center justify-between border rounded p-3 <?= $e['activa'] ? '' : 'opacity-50' ?>">
                            <div>
                                <div class="font-semibold"><?= htmlspecialchars($e['nombre']) ?> <span class="text-xs text-gray-500">(<?= htmlspecialchars($e['ubicacion']) ?>)</span></div>
                                <div class="text-xs text-gray-500">
                                    Última conexión: <?= $e['ultima_conexion'] ? htmlspecialchars($e['ultima_conexion']) : 'nunca' ?>
                                </div>
                            </div>
                            <form method="POST">
                                <input type="hidden" name="id" value="<?= $e['id'] ?>">
                                <?php if ($e['activa']): ?>
                                    <input type="hidden" name="accion" value="desactivar">
                                    <button class="text-red-600 text-sm font-semibold hover:underline">Desactivar</button>
                                <?php else: ?>
                                    <input type="hidden" name="accion" value="activar">
                                    <button class="text-green-600 text-sm font-semibold hover:underline">Reactivar</button>
                                <?php endif; ?>
                            </form>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
